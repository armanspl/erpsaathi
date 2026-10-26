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
    private const FONT = 'Arial';

    private const FONT_SIZE = 8;

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

    /** Display labels for pre-primary class names in the export grid. */
    private const CLASS_SHORT = [
        'nursery' => 'NUR',
        'nur' => 'NUR',
        'nurs' => 'NUR',
        'lkg' => 'LKG',
        'ukg' => 'UKG',
    ];

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
        $spreadsheet->getDefaultStyle()->getFont()->setName(self::FONT)->setSize(self::FONT_SIZE);
        $spreadsheet->removeSheetByIndex(0);

        // Pre-primary first (NUR / LKG / UKG), then 1, 2, 3… — not raw DB id order.
        $classes = SchoolClass::query()->get(['id', 'name', 'sort_order'])
            ->sort(fn ($a, $b) => $this->classSortKey($a) <=> $this->classSortKey($b))
            ->values();
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

        $classNameById = $classes->mapWithKeys(
            fn ($c) => [$c->id => $this->classExportLabel((string) $c->name)]
        )->all();

        foreach ($groups as $group) {
            $ws = $spreadsheet->createSheet();
            $ws->setTitle($this->uniqueSheetTitle($group['label'], $usedTitles));
            $periods = $this->periodsForGroup($sheet, $group['days']);
            // Only teachers with at least one period on these days — 0-period teachers are left out
            // of the legend, LEISURE and teacher-wise sheets (same as the on-screen Teacher wise tab).
            $counts = $this->teacherPeriodCounts($group['entries'], $teachers, $periods);
            $groupTeachers = $teachers->filter(fn ($t) => ($counts[(int) $t->id] ?? 0) > 0)->values();

            $this->buildDaySheet(
                $ws,
                $group['label'],
                $classes,
                $groupTeachers,
                $group['entries'],
                $periods
            );

            $tw = $spreadsheet->createSheet();
            $tw->setTitle($this->uniqueSheetTitle('TW '.$group['label'], $usedTitles));
            $this->buildTeacherWiseSheet(
                $tw,
                $group['label'],
                $groupTeachers,
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
        $ws->getStyle('A1')->getFont()->setName(self::FONT)->setBold(true)->setSize(10);
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

        // Prefer teacher_id; fall back to unambiguous name match (handles "IQBALUR R." ↔ "IQBALUR RAHMAN").
        $teacherById = $teachers->keyBy('id');
        $busyByPeriod = [];
        $periodHasTeaching = [];
        foreach ($periodCols as $p => $_col) {
            $busyByPeriod[$p] = [];
            $periodHasTeaching[$p] = false;
        }
        // Scan ALL day entries so a FREE-for-every-class period stays blank in LEISURE.
        foreach ($dayEntries as $entry) {
            $p = (int) $entry->period_number;
            if ($p < 1 || $p > $periods || $this->entryIsFree($entry)) {
                continue;
            }
            $periodHasTeaching[$p] = true;
            $tid = $this->resolveEntryTeacherId($entry, $teachers);
            if ($tid !== null && $teacherById->has($tid)) {
                $busyByPeriod[$p][$tid] = true;
            }
        }
        $periodCounts = $this->teacherPeriodCounts($dayEntries, $teachers, $periods);

        // --- Teacher legend (A-C) — period counts computed in PHP (not Excel COUNTIF on short labels) ---
        $row = 3;
        $serial = 1;
        foreach ($teachers as $teacher) {
            $ws->setCellValue('A'.$row, $serial);
            $ws->setCellValue('B'.$row, $this->shortTeacherName((string) $teacher->name));
            $ws->setCellValue('C'.$row, $periodCounts[(int) $teacher->id] ?? 0);
            if ($teacher->color) {
                $this->fillCell($ws, 'B'.$row, $teacher->color);
                $ws->getStyle('B'.$row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->contrastingFontArgb($teacher->color)));
            }
            $ws->getStyle('B'.$row)->getFont()->setBold(true);
            $row++;
            $serial++;
        }
        $legendLastRow = $row - 1;

        // --- Class grid (E-…) — skip classes that are FREE in every period ---
        $gridRow = 3;
        foreach ($classes as $class) {
            $entriesForClass = $dayEntries->where('school_class_id', $class->id)->keyBy('period_number');
            if (! $this->classHasTeachingEntry($entriesForClass, $periods)) {
                continue;
            }

            $subRow = $gridRow;
            $teachRow = $gridRow + 1;

            $ws->setCellValue('E'.$subRow, $this->classExportLabel((string) $class->name));
            $ws->mergeCells('E'.$subRow.':E'.$teachRow);
            $ws->getStyle('E'.$subRow)->getFont()->setName(self::FONT)->setBold(true)->setSize(self::FONT_SIZE);
            $ws->getStyle('E'.$subRow.':E'.$teachRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

            $ws->setCellValue('F'.$subRow, 'SUB');
            $ws->setCellValue('F'.$teachRow, 'TEACH');
            $ws->getStyle('F'.$subRow.':F'.$teachRow)->getFont()->setName(self::FONT)->setBold(true)->setSize(self::FONT_SIZE);

            $remarksText = null;
            foreach ($periodCols as $p => $col) {
                $entry = $entriesForClass->get($p);
                if (! $entry) {
                    continue;
                }
                if ($this->entryIsFree($entry)) {
                    $ws->setCellValue($col.$subRow, 'FREE');
                    $ws->setCellValue($col.$teachRow, '');
                    $this->fillCell($ws, $col.$subRow, 'ECFDF5');
                    $this->fillCell($ws, $col.$teachRow, 'ECFDF5');
                    $ws->getStyle($col.$subRow)->getFont()->setBold(true)->getColor()->setARGB('FF065F46');
                    $remarksText ??= $entry->remarks;

                    continue;
                }

                $subjectText = $entry->subject?->name ?? $entry->subject_label;
                $tid = $this->resolveEntryTeacherId($entry, $teachers);
                $teacherModel = $tid !== null ? $teacherById->get($tid) : null;
                $teacherText = $teacherModel?->name
                    ?? $entry->teacher?->name
                    ?? $entry->teacher_label;
                if ($subjectText) {
                    $ws->setCellValue($col.$subRow, $this->shortLabel((string) $subjectText, 12));
                }
                if ($teacherText) {
                    $ws->setCellValue($col.$teachRow, $this->shortTeacherName((string) $teacherText));
                    $color = $entry->color ?? $teacherModel?->color ?? $entry->teacher?->color;
                    if ($color) {
                        $this->fillCell($ws, $col.$teachRow, $color);
                        $ws->getStyle($col.$teachRow)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->contrastingFontArgb($color)));
                    }
                    $ws->getStyle($col.$teachRow)->getFont()->setName(self::FONT)->setBold(true)->setSize(self::FONT_SIZE);
                }
                $remarksText ??= $entry->remarks;
            }
            if ($remarksText) {
                $ws->setCellValue($remarksCol.$subRow, $remarksText);
            }

            $gridRow += 2;
        }
        $gridLastRow = $gridRow - 1;
        $hasClassGrid = $gridLastRow >= 3;

        // Legend total must be placed before LEISURE so we can park LEISURE below BOTH blocks.
        // Excel rows are shared left↔right: putting LEISURE at gridLastRow+1 while the teacher
        // legend still occupies that row (e.g. UZMA S.) made the tall LEISURE height stretch
        // that teacher row.
        $legendTotalRow = null;
        if ($legendLastRow >= 3) {
            $legendTotalRow = $legendLastRow + 1;
            $ws->setCellValue('C'.$legendTotalRow, array_sum($periodCounts));
            $ws->getStyle('C'.$legendTotalRow)->getFont()->setBold(true);
        }
        $legendBottom = $legendTotalRow ?? max(2, $legendLastRow);

        // --- LEISURE block BELOW legend + class grid: one name per cell (not a messy newline dump) ---
        $leisureFirstRow = null;
        $leisureLastRow = null;
        if ($hasClassGrid) {
            $freeByPeriod = [];
            $maxFree = 0;
            foreach ($periodCols as $p => $_col) {
                $freeByPeriod[$p] = [];
                if (! ($periodHasTeaching[$p] ?? false)) {
                    continue;
                }
                foreach ($teachers as $teacher) {
                    if (isset($busyByPeriod[$p][(int) $teacher->id])) {
                        continue;
                    }
                    $freeByPeriod[$p][] = $this->shortTeacherName((string) $teacher->name);
                }
                $maxFree = max($maxFree, count($freeByPeriod[$p]));
            }

            // At least one label row even when every period is school-wide FREE (columns stay blank).
            $leisureRows = max(1, $maxFree);
            $leisureFirstRow = max($gridLastRow, $legendBottom) + 1;
            $leisureLastRow = $leisureFirstRow + $leisureRows - 1;

            for ($i = 0; $i < $leisureRows; $i++) {
                $r = $leisureFirstRow + $i;
                foreach ($periodCols as $p => $col) {
                    $this->fillCell($ws, $col.$r, 'ECFDF5');
                    $ws->getStyle($col.$r)->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $name = $freeByPeriod[$p][$i] ?? null;
                    if ($name !== null) {
                        $ws->setCellValue($col.$r, $name);
                        $ws->getStyle($col.$r)->getFont()
                            ->setName(self::FONT)
                            ->setSize(7)
                            ->setBold(false)
                            ->getColor()->setARGB('FF065F46');
                    }
                }
                $this->fillCell($ws, $remarksCol.$r, 'ECFDF5');
                $ws->getRowDimension($r)->setRowHeight(12);
            }

            // Labels on the left of the block (merged when multiple name rows).
            $ws->setCellValue('E'.$leisureFirstRow, 'LEISURE');
            $ws->setCellValue('F'.$leisureFirstRow, 'FREE');
            if ($leisureLastRow > $leisureFirstRow) {
                $ws->mergeCells('E'.$leisureFirstRow.':E'.$leisureLastRow);
                $ws->mergeCells('F'.$leisureFirstRow.':F'.$leisureLastRow);
            }
            $ws->getStyle('E'.$leisureFirstRow.':F'.$leisureLastRow)->getFont()->setName(self::FONT)->setBold(true)->setSize(self::FONT_SIZE);
            $ws->getStyle('E'.$leisureFirstRow.':F'.$leisureLastRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
            $this->fillCell($ws, 'E'.$leisureFirstRow, 'D1FAE5');
            $this->fillCell($ws, 'F'.$leisureFirstRow, 'D1FAE5');
            // Re-fill merge range background (merge keeps first cell style; paint siblings too).
            for ($r = $leisureFirstRow; $r <= $leisureLastRow; $r++) {
                $this->fillCell($ws, 'E'.$r, 'D1FAE5');
                $this->fillCell($ws, 'F'.$r, 'D1FAE5');
            }
        }

        // --- Borders: teacher legend (A–C) and class grid / LEISURE (E–…) stay separate ---
        $thin = Border::BORDER_THIN;
        $black = 'FF000000';
        if ($legendLastRow >= 3) {
            $ws->getStyle('A2:C'.$legendBottom)->getBorders()->getAllBorders()->setBorderStyle($thin)->getColor()->setARGB($black);
        } else {
            $ws->getStyle('A2:C2')->getBorders()->getAllBorders()->setBorderStyle($thin)->getColor()->setARGB($black);
        }

        // Class grid borders stop at the last class pair — not extended through empty spacer rows.
        if ($hasClassGrid) {
            $ws->getStyle('E2:'.$remarksCol.$gridLastRow)->getBorders()->getAllBorders()->setBorderStyle($thin)->getColor()->setARGB($black);
        } else {
            $ws->getStyle('E2:'.$remarksCol.'2')->getBorders()->getAllBorders()->setBorderStyle($thin)->getColor()->setARGB($black);
        }
        if ($leisureFirstRow !== null && $leisureLastRow !== null) {
            $ws->getStyle('E'.$leisureFirstRow.':'.$remarksCol.$leisureLastRow)->getBorders()->getAllBorders()->setBorderStyle($thin)->getColor()->setARGB($black);
        }

        $fontBottom = max($legendBottom, $gridLastRow, $leisureLastRow ?? 2);
        $ws->getStyle('A1:'.$remarksCol.$fontBottom)->getFont()->setName(self::FONT)->setSize(self::FONT_SIZE);
        $ws->getStyle('A1')->getFont()->setName(self::FONT)->setBold(true)->setSize(10);
        if ($hasClassGrid) {
            $ws->getStyle('G3:'.$lastPeriodCol.$gridLastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        }
        if ($legendLastRow >= 3) {
            $ws->getStyle('B3:B'.$legendLastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        // Normal row height everywhere; LEISURE name rows already set to 12 above.
        foreach (range(2, $fontBottom) as $r) {
            if ($leisureFirstRow !== null && $r >= $leisureFirstRow && $r <= $leisureLastRow) {
                continue;
            }
            $ws->getRowDimension($r)->setRowHeight(13);
        }
    }

    /** @param  Collection<int, ClassRoutineEntry>  $entriesByPeriod */
    private function classHasTeachingEntry(Collection $entriesByPeriod, int $periods): bool
    {
        for ($p = 1; $p <= $periods; $p++) {
            $entry = $entriesByPeriod->get($p);
            if ($entry && ! $this->entryIsFree($entry)) {
                return true;
            }
        }

        return false;
    }

    private function entryIsFree(ClassRoutineEntry $entry): bool
    {
        return $entry->is_free
            || mb_strtoupper(trim((string) ($entry->subject_label ?? ''))) === 'FREE';
    }

    /**
     * Unique teaching periods per active teacher for this day group.
     * FREE / empty slots excluded; same teacher in two classes in one period counts once.
     *
     * @param  Collection<int, ClassRoutineEntry>  $dayEntries
     * @param  Collection<int, Teacher>  $teachers
     * @return array<int, int> teacher_id => period count
     */
    private function teacherPeriodCounts(Collection $dayEntries, Collection $teachers, int $periods): array
    {
        $activeIds = $teachers->pluck('id')->map(fn ($id) => (int) $id)->all();
        $activeSet = array_fill_keys($activeIds, true);
        $seen = [];
        $counts = array_fill_keys($activeIds, 0);

        foreach ($dayEntries as $entry) {
            if ($this->entryIsFree($entry)) {
                continue;
            }
            $p = (int) $entry->period_number;
            if ($p < 1 || $p > $periods) {
                continue;
            }
            $tid = $this->resolveEntryTeacherId($entry, $teachers);
            if ($tid === null || ! isset($activeSet[$tid])) {
                continue;
            }
            if (isset($seen[$tid][$p])) {
                continue;
            }
            $seen[$tid][$p] = true;
            $counts[$tid]++;
        }

        return $counts;
    }

    /**
     * Prefer teacher_id; otherwise match teacher_label to exactly one active teacher.
     *
     * @param  Collection<int, Teacher>  $teachers
     */
    private function resolveEntryTeacherId(ClassRoutineEntry $entry, Collection $teachers): ?int
    {
        if ($entry->teacher_id) {
            return (int) $entry->teacher_id;
        }
        $label = trim((string) ($entry->teacher_label ?? ''));
        if ($label === '') {
            return null;
        }

        return $this->matchTeacherIdByName($label, $teachers);
    }

    /**
     * Unambiguous name match only (exact / similar / short-form abbreviation).
     *
     * @param  Collection<int, Teacher>  $teachers
     */
    private function matchTeacherIdByName(string $label, Collection $teachers): ?int
    {
        $candidates = [];
        foreach ($teachers as $teacher) {
            if ($this->teacherNamesMatch($label, (string) $teacher->name)) {
                $candidates[] = (int) $teacher->id;
            }
        }
        $candidates = array_values(array_unique($candidates));

        return count($candidates) === 1 ? $candidates[0] : null;
    }

    /** IQBALUR R. ↔ IQBALUR RAHMAN; SANA ↔ SANA HASAN when unique. */
    private function teacherNamesMatch(string $a, string $b): bool
    {
        $na = $this->normalizeTeacherName($a);
        $nb = $this->normalizeTeacherName($b);
        if ($na === '' || $nb === '') {
            return false;
        }
        if ($na === $nb) {
            return true;
        }
        if (\App\Services\Payroll\EmployeeMatcher::similarNames($a, $b)) {
            return true;
        }
        $sa = $this->shortTeacherName($a);
        $sb = $this->shortTeacherName($b);
        if ($sa !== '' && $sa === $sb) {
            return true;
        }
        // Label already abbreviated like "IQBALUR R" vs short "IQBALUR R."
        $sa2 = $this->normalizeTeacherName($sa);
        $sb2 = $this->normalizeTeacherName($sb);
        if ($sa2 !== '' && $sa2 === $sb2) {
            return true;
        }

        // Single-token label ("SANA", "VIJAY") ↔ master's first name (uniqueness checked by caller).
        $aTokens = explode(' ', $na);
        $bTokens = explode(' ', $nb);
        if (count($aTokens) === 1 && ($bTokens[0] ?? '') === $aTokens[0]) {
            return true;
        }
        if (count($bTokens) === 1 && ($aTokens[0] ?? '') === $bTokens[0]) {
            return true;
        }

        return false;
    }

    private function normalizeTeacherName(string $name): string
    {
        $clean = mb_strtoupper(str_replace('.', ' ', $name));

        return preg_replace('/\s+/', ' ', trim($clean)) ?? '';
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
        $ws->getStyle('A1')->getFont()->setName(self::FONT)->setBold(true)->setSize(10);
        $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $ws->setCellValue('A2', 'TEACHER');
        $ws->setCellValue('B2', 'PERIOD');
        foreach ($periodCols as $p => $col) {
            $ws->setCellValue($col.'2', $p);
        }
        $ws->setCellValue($countCol.'2', 'PERIODS');
        $ws->getStyle('A2:'.$countCol.'2')->getFont()->setBold(true);
        $ws->getStyle('A2:'.$countCol.'2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // Resolve entries to teacher ids (id first, else unambiguous name match).
        $byTeacherPeriod = [];
        foreach ($dayEntries as $entry) {
            if ($this->entryIsFree($entry)) {
                continue;
            }
            $tid = $this->resolveEntryTeacherId($entry, $teachers);
            if ($tid === null) {
                continue;
            }
            $byTeacherPeriod[$tid][(int) $entry->period_number][] = $entry;
        }

        $row = 3;
        foreach ($teachers as $teacher) {
            $tid = (int) $teacher->id;
            $subRow = $row;
            $classRow = $row + 1;

            $ws->setCellValue('A'.$subRow, $this->shortTeacherName((string) $teacher->name));
            $ws->mergeCells('A'.$subRow.':A'.$classRow);
            $ws->getStyle('A'.$subRow)->getFont()->setName(self::FONT)->setBold(true)->setSize(self::FONT_SIZE);
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
                $list = $byTeacherPeriod[$tid][$p] ?? [];
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
                        $cls[] = (string) $className;
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
        $ws->getStyle('A1:'.$countCol.$lastRow)->getFont()->setName(self::FONT)->setSize(self::FONT_SIZE);
        $ws->getStyle('A1')->getFont()->setName(self::FONT)->setBold(true)->setSize(10);
        $ws->getStyle('C3:'.$lastPeriodCol.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        foreach (range(2, $lastRow) as $r) {
            $ws->getRowDimension($r)->setRowHeight(13);
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
     * Teacher names: first word full + last word's first letter only (e.g. RAM KUMAR SINGH → RAM S.).
     */
    private function shortTeacherName(?string $name): string
    {
        $upper = mb_strtoupper(trim((string) $name));
        if ($upper === '') {
            return '';
        }
        $parts = preg_split('/\s+/u', $upper) ?: [$upper];
        if (count($parts) === 1) {
            return $parts[0];
        }

        return $parts[0].' '.mb_substr($parts[count($parts) - 1], 0, 1).'.';
    }

    /** NURSERY → NUR; LKG / UKG / numbered classes kept compact. */
    private function classExportLabel(string $name): string
    {
        $key = mb_strtolower(trim(preg_replace('/\s+/u', '', $name) ?? $name));
        if (isset(self::CLASS_SHORT[$key])) {
            return self::CLASS_SHORT[$key];
        }
        // "Class 1" / "I" / "1st" → keep a short uppercase token
        if (preg_match('/^(\d+)(st|nd|rd|th)?$/i', $key, $m)) {
            return (string) (int) $m[1];
        }

        return mb_strtoupper(trim($name));
    }

    /**
     * Sort key: NUR→0, LKG→1, UKG→2, then class 1, 2, 3… then anything else by sort_order/name.
     *
     * @return array{0: int, 1: int, 2: string}
     */
    private function classSortKey(SchoolClass $class): array
    {
        $key = mb_strtolower(trim(preg_replace('/\s+/u', '', (string) $class->name) ?? ''));
        $prePrimary = ['nursery' => 0, 'nur' => 0, 'nurs' => 0, 'lkg' => 1, 'ukg' => 2];
        if (isset($prePrimary[$key])) {
            return [$prePrimary[$key], 0, $key];
        }
        if (preg_match('/^(\d+)/', $key, $m)) {
            return [100 + (int) $m[1], 0, $key];
        }
        // Roman numerals I–XII often used for classes
        $romans = ['i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4, 'v' => 5, 'vi' => 6, 'vii' => 7, 'viii' => 8, 'ix' => 9, 'x' => 10, 'xi' => 11, 'xii' => 12];
        if (isset($romans[$key])) {
            return [100 + $romans[$key], 0, $key];
        }

        return [500 + (int) ($class->sort_order ?? 0), (int) $class->id, $key];
    }

    /** Subject labels only — shrink if longer than $max. */
    private function shortLabel(?string $name, int $max = 12): string
    {
        $upper = mb_strtoupper(trim((string) $name));
        if ($upper === '') {
            return '';
        }
        if (mb_strlen($upper) <= $max) {
            return $upper;
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
