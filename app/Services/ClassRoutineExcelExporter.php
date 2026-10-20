<?php

namespace App\Services;

use App\Models\ClassRoutineEntry;
use App\Models\ClassRoutineSheet;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rebuilds the school's own printed Class Routine layout (see ClassRoutineImportParser's class
 * doc for the source shape this mirrors) from the saved routine:
 *  - Days with an identical timetable are merged into one sheet titled like "MON / TUE / WED"
 *    (same half-week grouping the school prints / imports);
 *  - Class-wise sheet: teacher legend + class grid (SUB / TEACH) + LEISURE free teachers;
 *  - Matching Teacher-wise sheet per day group: SUB / CLASS per teacher per period.
 */
class ClassRoutineExcelExporter
{
    private const FONT = 'Verdana';

    /** Short labels for sheet titles / headers — matches ClassRoutineView / import. */
    private const DAY_SHORT = [
        1 => 'MON',
        2 => 'TUE',
        3 => 'WED',
        4 => 'THU',
        5 => 'FRI',
        6 => 'SAT',
    ];

    /** Preferred half-week groups (school workbook prints Mon–Wed and Thu–Sat together). */
    private const DAY_GROUPS = [[1, 2, 3], [4, 5, 6]];

    public function stream(ClassRoutineSheet $sheet): StreamedResponse
    {
        $spreadsheet = $this->build($sheet);
        $writer = new Xlsx($spreadsheet);
        $filename = 'class-routine-'.$sheet->id.'.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function build(ClassRoutineSheet $sheet): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName(self::FONT)->setSize(10);
        $spreadsheet->removeSheetByIndex(0);

        $classes = SchoolClass::orderBy('sort_order')->get(['id', 'name']);
        // Inactive teachers are omitted from legend, LEISURE, and teacher-wise sheets.
        $teachers = Teacher::where('status', 'active')->orderBy('name')->get(['id', 'name', 'color']);
        $entriesByDay = $sheet->entries->groupBy('day_of_week');

        $usedTitles = [];
        $groups = $this->daySheetGroups($entriesByDay);

        // Empty routine — still emit one blank Monday pair so the download isn't an empty workbook.
        if ($groups === []) {
            $groups = [[
                'label' => 'MON',
                'days' => [1],
                'entries' => collect(),
            ]];
        }

        $classNameById = $classes->pluck('name', 'id')->all();

        foreach ($groups as $group) {
            $ws = $spreadsheet->createSheet();
            $ws->setTitle($this->uniqueSheetTitle($group['label'], $usedTitles));
            $periods = $this->periodsForGroup($sheet, $group['days']);

            $this->buildDaySheet(
                $ws,
                $group['label'],
                $classes,
                $teachers,
                $group['entries'],
                $periods
            );

            $tw = $spreadsheet->createSheet();
            $tw->setTitle($this->uniqueSheetTitle('TW '.$group['label'], $usedTitles));
            $this->buildTeacherWiseSheet(
                $tw,
                $group['label'],
                $teachers,
                $group['entries'],
                $classNameById,
                $periods
            );
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Collapse identical half-week days into one sheet (MON / TUE / WED), otherwise one sheet
     * per filled day — same rule as ClassRoutineView.
     *
     * @param  Collection<int, Collection<int, ClassRoutineEntry>>  $entriesByDay
     * @return list<array{label: string, days: list<int>, entries: Collection<int, ClassRoutineEntry>}>
     */
    private function daySheetGroups(Collection $entriesByDay): array
    {
        $out = [];
        foreach (self::DAY_GROUPS as $days) {
            $filled = array_values(array_filter(
                $days,
                fn (int $d) => $entriesByDay->get($d, collect())->isNotEmpty()
            ));
            if ($filled === []) {
                continue;
            }

            $sigs = array_unique(array_map(
                fn (int $d) => $this->daySignature($entriesByDay->get($d, collect())),
                $days
            ));

            // All three days share the same timetable (including empty matching empty) → one sheet.
            if (count($sigs) === 1) {
                $label = implode(' / ', array_map(fn (int $d) => self::DAY_SHORT[$d], $days));
                $out[] = [
                    'label' => $label,
                    'days' => $days,
                    'entries' => $entriesByDay->get($days[0], collect()),
                ];
            } else {
                foreach ($filled as $d) {
                    $out[] = [
                        'label' => self::DAY_SHORT[$d],
                        'days' => [$d],
                        'entries' => $entriesByDay->get($d, collect()),
                    ];
                }
            }
        }

        return $out;
    }

    /** Compact comparable fingerprint of a day's entries (class|period + subject/teacher). */
    private function daySignature(Collection $dayEntries): string
    {
        $parts = $dayEntries
            ->map(fn (ClassRoutineEntry $e) => implode('|', [
                (int) $e->school_class_id,
                (int) $e->period_number,
                $e->is_free ? 'FREE' : '',
                (string) ($e->subject_id ?? ''),
                (string) ($e->subject_label ?? ''),
                (string) ($e->teacher_id ?? ''),
                (string) ($e->teacher_label ?? ''),
            ]))
            ->sort()
            ->values()
            ->all();

        return implode("\n", $parts);
    }

    /**
     * @param  Collection<int, ClassRoutineEntry>  $dayEntries
     */
    private function buildDaySheet(Worksheet $ws, string $dayLabel, $classes, $teachers, $dayEntries, int $periods): void
    {
        $periodCols = [];
        for ($p = 1; $p <= $periods; $p++) {
            $periodCols[$p] = Coordinate::stringFromColumnIndex(6 + $p); // G=7 -> period 1
        }
        $lastPeriodCol = $periodCols[$periods] ?? 'G';
        $remarksCol = Coordinate::stringFromColumnIndex(6 + $periods + 1);

        $ws->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4);
        $ws->getSheetView()->setZoomScale(100);

        foreach (['A' => 3.5, 'B' => 15, 'C' => 9.5, 'D' => 2.5, 'E' => 9, 'F' => 9.5] as $col => $width) {
            $ws->getColumnDimension($col)->setWidth($width);
        }
        foreach ($periodCols as $col) {
            $ws->getColumnDimension($col)->setWidth(15);
        }
        $ws->getColumnDimension($remarksCol)->setWidth(14);

        // Title — e.g. "MON / TUE / WED — CLASS ROUTINE"
        $ws->setCellValue('A1', mb_strtoupper($dayLabel).' — CLASS ROUTINE');
        $ws->mergeCells('A1:'.$remarksCol.'1');
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header row 2
        $headers = ['A2' => '#', 'B2' => 'TEACHERS', 'C2' => 'PERIOD', 'E2' => 'CLASS', 'F2' => 'PERIOD'];
        foreach ($headers as $addr => $text) {
            $ws->setCellValue($addr, $text);
        }
        foreach ($periodCols as $p => $col) {
            $ws->setCellValue($col.'2', $p);
        }
        $ws->setCellValue($remarksCol.'2', 'REMARKS');
        $ws->getStyle('A2:'.$remarksCol.'2')->getFont()->setBold(true);
        $ws->getStyle('A2:'.$remarksCol.'2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // --- Teacher legend (A-C) — same short labels as TEACH / LEISURE so COUNTIF matches ---
        $row = 3;
        $serial = 1;
        foreach ($teachers as $teacher) {
            $ws->setCellValue('A'.$row, $serial);
            $ws->setCellValue('B'.$row, $this->shortLabel((string) $teacher->name, 14));
            if ($teacher->color) {
                $this->fillCell($ws, 'B'.$row, $teacher->color);
                $ws->getStyle('B'.$row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->contrastingFontArgb($teacher->color)));
            }
            $ws->getStyle('B'.$row)->getFont()->setBold(true);
            $row++;
            $serial++;
        }
        $legendLastRow = $row - 1;

        // Busy teachers per period (matched id or unmatched label) for LEISURE.
        $busyByPeriod = [];
        foreach ($periodCols as $p => $_col) {
            $busyByPeriod[$p] = ['ids' => [], 'labels' => []];
        }

        // --- Class grid (E-…) ---
        $gridRow = 3;
        foreach ($classes as $class) {
            $subRow = $gridRow;
            $teachRow = $gridRow + 1;

            $ws->setCellValue('E'.$subRow, $this->shortLabel((string) $class->name, 10));
            $ws->mergeCells('E'.$subRow.':E'.$teachRow);
            $ws->getStyle('E'.$subRow)->getFont()->setBold(true);
            $ws->getStyle('E'.$subRow.':E'.$teachRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

            $ws->setCellValue('F'.$subRow, 'SUB');
            $ws->setCellValue('F'.$teachRow, 'TEACH');
            $ws->getStyle('F'.$subRow.':F'.$teachRow)->getFont()->setBold(true);

            $entriesForClass = $dayEntries->where('school_class_id', $class->id)->keyBy('period_number');
            $remarksText = null;
            foreach ($periodCols as $p => $col) {
                $entry = $entriesForClass->get($p);
                if (! $entry) {
                    continue;
                }
                if ($entry->is_free || mb_strtoupper(trim((string) ($entry->subject_label ?? ''))) === 'FREE') {
                    $ws->setCellValue($col.$subRow, 'FREE');
                    $ws->setCellValue($col.$teachRow, '');
                    $this->fillCell($ws, $col.$subRow, 'ECFDF5');
                    $this->fillCell($ws, $col.$teachRow, 'ECFDF5');
                    $ws->getStyle($col.$subRow)->getFont()->setBold(true)->getColor()->setARGB('FF065F46');
                    $remarksText ??= $entry->remarks;

                    continue;
                }

                $subjectText = $entry->subject?->name ?? $entry->subject_label;
                $teacherText = $entry->teacher?->name ?? $entry->teacher_label;
                if ($subjectText) {
                    $ws->setCellValue($col.$subRow, $this->shortLabel((string) $subjectText, 12));
                }
                if ($teacherText) {
                    $ws->setCellValue($col.$teachRow, $this->shortLabel((string) $teacherText, 12));
                    $color = $entry->color ?? $entry->teacher?->color;
                    if ($color) {
                        $this->fillCell($ws, $col.$teachRow, $color);
                        $ws->getStyle($col.$teachRow)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->contrastingFontArgb($color)));
                    }
                    $ws->getStyle($col.$teachRow)->getFont()->setBold(true);

                    if ($entry->teacher_id) {
                        $busyByPeriod[$p]['ids'][(int) $entry->teacher_id] = true;
                    } else {
                        $labelKey = mb_strtoupper(trim((string) $entry->teacher_label));
                        if ($labelKey !== '') {
                            $busyByPeriod[$p]['labels'][$labelKey] = true;
                        }
                    }
                }
                $remarksText ??= $entry->remarks;
            }
            if ($remarksText) {
                $ws->setCellValue($remarksCol.$subRow, $remarksText);
            }

            $gridRow += 2;
        }
        $gridLastRow = $gridRow - 1;

        // --- LEISURE row: free teachers listed under each period ---
        $leisureRow = $gridLastRow + 1;
        $ws->setCellValue('E'.$leisureRow, 'LEISURE');
        $ws->setCellValue('F'.$leisureRow, 'FREE');
        $ws->getStyle('E'.$leisureRow.':F'.$leisureRow)->getFont()->setBold(true);
        $this->fillCell($ws, 'E'.$leisureRow, 'D1FAE5');
        $this->fillCell($ws, 'F'.$leisureRow, 'D1FAE5');

        $maxFree = 0;
        foreach ($periodCols as $p => $col) {
            $freeNames = [];
            foreach ($teachers as $teacher) {
                if (isset($busyByPeriod[$p]['ids'][(int) $teacher->id])) {
                    continue;
                }
                $nameKey = mb_strtoupper(trim((string) $teacher->name));
                if (isset($busyByPeriod[$p]['labels'][$nameKey])) {
                    continue;
                }
                $freeNames[] = $this->shortLabel((string) $teacher->name, 12);
            }
            $maxFree = max($maxFree, count($freeNames));
            if ($freeNames !== []) {
                $ws->setCellValue($col.$leisureRow, implode("\n", $freeNames));
            }
            $this->fillCell($ws, $col.$leisureRow, 'ECFDF5');
            $ws->getStyle($col.$leisureRow)->getFont()->setBold(true)->getColor()->setARGB('FF065F46');
            $ws->getStyle($col.$leisureRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_TOP)
                ->setWrapText(true);
        }
        $this->fillCell($ws, $remarksCol.$leisureRow, 'ECFDF5');
        $ws->getRowDimension($leisureRow)->setRowHeight(max(30, min(140, 14 * max(1, $maxFree))));

        // COUNTIF over the class grid only (not the LEISURE row — free names would inflate counts).
        $row = 3;
        foreach ($teachers as $teacher) {
            if ($gridLastRow >= 3) {
                $ws->setCellValue('C'.$row, '=COUNTIF(G3:'.$lastPeriodCol.$gridLastRow.',B'.$row.')');
            } else {
                $ws->setCellValue('C'.$row, 0);
            }
            $row++;
        }
        if ($legendLastRow >= 3) {
            $ws->setCellValue('C'.($legendLastRow + 1), '=SUM(C3:C'.$legendLastRow.')');
        }

        // --- Borders + alignment ---
        $lastRow = max($legendLastRow, $leisureRow);
        $usedRange = 'A2:'.$remarksCol.$lastRow;
        $ws->getStyle($usedRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
        if ($gridLastRow >= 3) {
            $ws->getStyle('G3:'.$lastPeriodCol.$gridLastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        }
        if ($legendLastRow >= 3) {
            $ws->getStyle('B3:B'.$legendLastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        foreach (range(2, $lastRow) as $r) {
            if ($r === $leisureRow) {
                continue;
            }
            $ws->getRowDimension($r)->setRowHeight(15);
        }
    }

    /**
     * Teacher-wise sheet: each teacher has SUB + CLASS rows across periods (FREE when idle).
     *
     * @param  Collection<int, ClassRoutineEntry>  $dayEntries
     * @param  array<int, string>  $classNameById
     */
    private function buildTeacherWiseSheet(
        Worksheet $ws,
        string $dayLabel,
        $teachers,
        Collection $dayEntries,
        array $classNameById,
        int $periods
    ): void {
        $periodCols = [];
        for ($p = 1; $p <= $periods; $p++) {
            $periodCols[$p] = Coordinate::stringFromColumnIndex(2 + $p); // C=3 -> period 1
        }
        $lastPeriodCol = $periodCols[$periods] ?? 'C';
        $countCol = Coordinate::stringFromColumnIndex(2 + $periods + 1);

        $ws->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4);
        $ws->getSheetView()->setZoomScale(100);

        $ws->getColumnDimension('A')->setWidth(16);
        $ws->getColumnDimension('B')->setWidth(9);
        foreach ($periodCols as $col) {
            $ws->getColumnDimension($col)->setWidth(14);
        }
        $ws->getColumnDimension($countCol)->setWidth(10);

        $ws->setCellValue('A1', 'TEACHER WISE — '.mb_strtoupper($dayLabel));
        $ws->mergeCells('A1:'.$countCol.'1');
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $ws->setCellValue('A2', 'TEACHER');
        $ws->setCellValue('B2', 'PERIOD');
        foreach ($periodCols as $p => $col) {
            $ws->setCellValue($col.'2', $p);
        }
        $ws->setCellValue($countCol.'2', 'PERIODS');
        $ws->getStyle('A2:'.$countCol.'2')->getFont()->setBold(true);
        $ws->getStyle('A2:'.$countCol.'2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // teacher_id / label-key -> period -> list of entries
        $byTeacherPeriod = [];
        foreach ($dayEntries as $entry) {
            if ($entry->teacher_id) {
                $key = 't'.$entry->teacher_id;
            } else {
                $label = mb_strtoupper(trim((string) ($entry->teacher_label ?? '')));
                if ($label === '') {
                    continue;
                }
                $key = 'l'.$label;
            }
            $byTeacherPeriod[$key][$entry->period_number][] = $entry;
        }

        $row = 3;
        foreach ($teachers as $teacher) {
            $key = 't'.$teacher->id;
            $subRow = $row;
            $classRow = $row + 1;

            $ws->setCellValue('A'.$subRow, $this->shortLabel((string) $teacher->name, 14));
            $ws->mergeCells('A'.$subRow.':A'.$classRow);
            $ws->getStyle('A'.$subRow)->getFont()->setBold(true);
            $ws->getStyle('A'.$subRow.':A'.$classRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            if ($teacher->color) {
                $this->fillCell($ws, 'A'.$subRow, $teacher->color);
                $ws->getStyle('A'.$subRow)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->contrastingFontArgb($teacher->color)));
            }

            $ws->setCellValue('B'.$subRow, 'SUB');
            $ws->setCellValue('B'.$classRow, 'CLASS');
            $ws->getStyle('B'.$subRow.':B'.$classRow)->getFont()->setBold(true);

            $periodCount = 0;
            foreach ($periodCols as $p => $col) {
                $list = $byTeacherPeriod[$key][$p] ?? [];
                if ($list === []) {
                    $ws->setCellValue($col.$subRow, 'FREE');
                    $ws->setCellValue($col.$classRow, '');
                    $this->fillCell($ws, $col.$subRow, 'ECFDF5');
                    $this->fillCell($ws, $col.$classRow, 'ECFDF5');
                    $ws->getStyle($col.$subRow)->getFont()->getColor()->setARGB('FF059669');
                } else {
                    $periodCount++;
                    $subs = [];
                    $cls = [];
                    foreach ($list as $e) {
                        $sub = $e->subject?->name ?? $e->subject_label;
                        if ($sub) {
                            $subs[] = $this->shortLabel((string) $sub, 12);
                        }
                        $className = $classNameById[(int) $e->school_class_id] ?? '#'.$e->school_class_id;
                        $cls[] = $this->shortLabel((string) $className, 10);
                    }
                    $ws->setCellValue($col.$subRow, implode(' / ', array_unique($subs)));
                    $ws->setCellValue($col.$classRow, implode(' + ', $cls));
                    $ws->getStyle($col.$subRow)->getFont()->setBold(true);

                    if (count($list) > 1) {
                        $this->fillCell($ws, $col.$subRow, 'FEE2E2');
                        $this->fillCell($ws, $col.$classRow, 'FEE2E2');
                        $ws->getStyle($col.$subRow.':'.$col.$classRow)->getFont()->getColor()->setARGB('FFB91C1C');
                    }
                }
            }

            $ws->setCellValue($countCol.$subRow, $periodCount);
            $ws->mergeCells($countCol.$subRow.':'.$countCol.$classRow);
            $ws->getStyle($countCol.$subRow)->getFont()->setBold(true);
            $ws->getStyle($countCol.$subRow.':'.$countCol.$classRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

            $row += 2;
        }

        $lastRow = max(2, $row - 1);
        $usedRange = 'A2:'.$countCol.$lastRow;
        $ws->getStyle($usedRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
        $ws->getStyle('C3:'.$lastPeriodCol.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        foreach (range(2, $lastRow) as $r) {
            $ws->getRowDimension($r)->setRowHeight(15);
        }
    }

    /** @param  list<int>  $days */
    private function periodsForGroup(ClassRoutineSheet $sheet, array $days): int
    {
        $max = 0;
        foreach ($days as $d) {
            $max = max($max, $sheet->periodsForDay((int) $d));
        }

        return max(1, $max ?: (int) ($sheet->periods_per_day ?: 7));
    }

    /**
     * @param  array<string, true>  $usedTitles
     */
    private function uniqueSheetTitle(string $label, array &$usedTitles): string
    {
        $base = mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '-', $label) ?: 'Day', 0, 31);
        $title = $base;
        $suffix = 2;
        while (isset($usedTitles[$title])) {
            $title = mb_substr($base, 0, 31 - mb_strlen((string) $suffix) - 1).'-'.$suffix;
            $suffix++;
        }
        $usedTitles[$title] = true;

        return $title;
    }

    /**
     * Fit long teacher / subject / class names into narrow period columns.
     * Multi-word names become "FIRST L." (then truncated if still too long).
     */
    private function shortLabel(?string $name, int $max = 12): string
    {
        $upper = mb_strtoupper(trim((string) $name));
        if ($upper === '') {
            return '';
        }
        if (mb_strlen($upper) <= $max) {
            return $upper;
        }

        $parts = preg_split('/\s+/u', $upper) ?: [$upper];
        if (count($parts) >= 2) {
            $first = $parts[0];
            $last = $parts[count($parts) - 1];
            $candidate = $first.' '.mb_substr($last, 0, 1).'.';
            if (mb_strlen($candidate) <= $max) {
                return $candidate;
            }
            // e.g. MOHAMMAD… → MOHAM L.
            $room = max(1, $max - 3);
            return mb_substr($first, 0, $room).' '.mb_substr($last, 0, 1).'.';
        }

        return mb_substr($upper, 0, max(1, $max - 1)).'…';
    }

    private function fillCell(Worksheet $ws, string $addr, string $hex): void
    {
        $hex = ltrim($hex, '#');
        $ws->getStyle($addr)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF'.strtoupper($hex));
    }

    /** White text on a dark fill, black text on a light one — keeps every teacher badge readable. */
    private function contrastingFontArgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (mb_strlen($hex) !== 6) {
            return 'FF000000';
        }
        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.6 ? 'FF000000' : 'FFFFFFFF';
    }
}
