<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AttendanceMonthlySummary;
use App\Models\SchoolClass;
use App\Models\Student;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The school's attendance workbook ("ATTENDANCE SHEET 2026-27"): one sheet per class (NUR, LKG, UKG, 1st–8th) with row 1 the title, row 2 the working days
 * per month, row 3 the headers and one row per student of days present — same colours, fonts,
 * widths and formulas as the school's own file. Used by the Attendance Import template
 * (students, no attendance) and the Attendance Export (attendance from the ERP); the export
 * reads back in through AttendanceImportController unchanged.
 */
class AttendanceWorkbookService
{
    /** Month columns (A = 1) => [calendar month, year offset from the session start year]. */
    public const MONTH_COLUMNS = [
        4 => [3, 0], 5 => [4, 0], 6 => [5, 0], 7 => [6, 0], 8 => [7, 0], 9 => [8, 0], 10 => [9, 0],
        13 => [10, 0], 14 => [11, 0], 15 => [12, 0], 16 => [1, 1], 17 => [2, 1], 18 => [3, 1],
    ];

    private const HEADERS = ['ENROL', 'NAME', 'ROLL', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'TOT', '%',
        'OCT', 'NOV', 'DEC', 'JAN', 'FEB', 'MAR', 'TOT', '%', 'G TOT', '%'];

    private const ACCOUNTING = '_-* #,##0_-;\-* #,##0_-;_-* "-"??_-;_-@_-';

    /** Attendance saved in the ERP for $session, laid out as the school's workbook. */
    public function export(AcademicSession $session): Spreadsheet
    {
        $startYear = (int) $session->start_date->format('Y');
        $summaries = AttendanceMonthlySummary::query()->where('session_start_year', $startYear)->get();

        $byStudent = $summaries->groupBy('student_id');
        $classOf = $byStudent->map(fn ($rows) => $rows->sortByDesc(fn ($r) => $r->year * 100 + $r->month)->first()->school_class_id);

        // Students with attendance this session, plus (current session) active students not yet marked.
        $students = Student::query()->whereIn('id', $byStudent->keys())
            ->when($session->is_current, fn ($q) => $q->orWhere('status', 'Active'))
            ->get(['id', 'admission_no', 'name', 'roll_no', 'school_class_id', 'status']);

        $sheets = [];
        foreach ($this->classesInOrder() as $class) {
            $rows = $students->filter(fn (Student $s) => (int) ($classOf[$s->id] ?? $s->school_class_id) === $class->id);
            if ($rows->isEmpty()) {
                continue;
            }
            $classSummaries = $summaries->where('school_class_id', $class->id);
            $workingDays = [];
            foreach (self::MONTH_COLUMNS as $col => [$month, $offset]) {
                $wd = (int) $classSummaries->where('year', $startYear + $offset)->where('month', $month)->max('working_days');
                $workingDays[$col] = $wd > 0 ? $wd : null;
            }
            $sheets[] = [
                'class' => $class,
                'working_days' => $workingDays,
                'students' => $this->sortByRoll($rows)->map(function (Student $s) use ($byStudent, $startYear) {
                    $months = [];
                    foreach ($byStudent[$s->id] ?? [] as $r) {
                        foreach (self::MONTH_COLUMNS as $col => [$month, $offset]) {
                            if ((int) $r->month === $month && (int) $r->year === $startYear + $offset) {
                                $months[$col] = (int) $r->days_present;
                            }
                        }
                    }

                    return [$s->admission_no, $s->name, $s->roll_no, $months];
                })->values()->all(),
            ];
        }

        return $this->build($startYear, $sheets);
    }

    /** Blank workbook for the Attendance Import: every class with its active students, no attendance. */
    public function template(?AcademicSession $session): Spreadsheet
    {
        $startYear = $session?->start_date ? (int) $session->start_date->format('Y') : (int) now()->format('Y');
        $students = Student::query()->where('status', 'Active')->get(['id', 'admission_no', 'name', 'roll_no', 'school_class_id']);

        $sheets = [];
        foreach ($this->classesInOrder() as $class) {
            $sheets[] = [
                'class' => $class,
                'working_days' => [],
                'students' => $this->sortByRoll($students->where('school_class_id', $class->id))
                    ->map(fn (Student $s) => [$s->admission_no, $s->name, $s->roll_no, []])->values()->all(),
            ];
        }

        return $this->build($startYear, $sheets);
    }

    /** Sheet title for a class, as the school names its sheets: Nursery → NUR, 1 → 1st. */
    public static function sheetTitle(string $className): string
    {
        $name = trim($className);
        if (preg_match('/^nur(sery)?$/i', $name)) {
            return 'NUR';
        }
        if (preg_match('/^\d+$/', $name)) {
            $n = (int) $name;
            $suffix = in_array($n % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th');

            return $n.$suffix;
        }

        return mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $name), 0, 31);
    }

    /**
     * @param  list<array{class: SchoolClass, working_days: array<int, ?int>, students: list<array{0: mixed, 1: ?string, 2: mixed, 3: array<int, int>}>}>  $sheets
     */
    private function build(int $startYear, array $sheets): Spreadsheet
    {
        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $label = sprintf('%04d-%02d', $startYear, ($startYear + 1) % 100);

        foreach ($sheets as $i => $sheet) {
            $this->writeClass($i === 0 ? $ss->getActiveSheet() : $ss->createSheet(), $label, $sheet);
        }
        $ss->setActiveSheetIndex(0);

        return $ss;
    }

    /** @param  array{class: SchoolClass, working_days: array<int, ?int>, students: list<array>}  $data */
    private function writeClass(Worksheet $ws, string $label, array $data): void
    {
        $title = self::sheetTitle((string) $data['class']->name);
        $ws->setTitle($title);
        $ws->getDefaultRowDimension()->setRowHeight(18);
        $first = 4;
        $last = max($first, $first + count($data['students']) - 1);

        // Row 1 — title band.
        $ws->setCellValue('A1', "Attendence Report {$label}");
        $ws->mergeCells('A1:V1');
        $this->style($ws, 'A1:V1', ['size' => 14, 'bold' => true, 'fill' => 'E6B9B8', 'h' => Alignment::HORIZONTAL_CENTER]);
        $ws->getStyle('A1:V1')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
        $ws->getStyle('A1')->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THIN);

        // Row 2 — working days per month.
        $ws->getRowDimension(2)->setRowHeight(18.75);
        $ws->setCellValue('A2', 'CLASS - '.$title);
        $ws->setCellValue('B2', 'W DAY');
        foreach (self::MONTH_COLUMNS as $col => $_) {
            if (($data['working_days'][$col] ?? null) !== null) {
                $ws->setCellValue([$col, 2], $data['working_days'][$col]);
            }
        }
        $ws->setCellValue('K2', '=SUM(B2:J2)');
        $ws->setCellValue('L2', 'XXX');
        $ws->setCellValue('S2', '=SUM(M2:R2)');
        $ws->setCellValue('T2', 'XXX');
        $ws->setCellValue('U2', '=K2+S2');
        $ws->setCellValue('V2', 'XXX');
        $ws->setCellValue('W2', "=MAX(U{$first}:U{$last})");
        $this->style($ws, 'A2:V2', ['bold' => true, 'fill' => 'FFC000', 'h' => Alignment::HORIZONTAL_CENTER, 'v' => Alignment::VERTICAL_CENTER, 'border' => true]);
        $this->style($ws, 'A2', ['size' => 9]);
        $this->style($ws, 'B2', ['h' => Alignment::HORIZONTAL_RIGHT]);
        $this->style($ws, 'M2:R2', ['h' => Alignment::HORIZONTAL_GENERAL]);
        $this->style($ws, 'K2', ['size' => 12, 'fill' => '92D050']);
        $this->style($ws, 'S2', ['size' => 12, 'fill' => '92D050']);
        $this->style($ws, 'U2', ['size' => 12, 'fill' => '00B0F0']);
        foreach (['L2', 'T2', 'V2'] as $c) {
            $ws->getStyle($c)->getNumberFormat()->setFormatCode(self::ACCOUNTING);
        }
        $this->style($ws, 'W2', ['size' => 14, 'bold' => true, 'color' => 'FFFFFF', 'fill' => '00B050', 'border' => true, 'format' => self::ACCOUNTING]);

        // Row 3 — headers.
        $ws->fromArray(self::HEADERS, null, 'A3');
        $ws->setCellValue('W3', "=MIN(U{$first}:U{$last})");
        $this->style($ws, 'A3:V3', ['bold' => true, 'color' => 'FFFFFF', 'fill' => 'E46C0A', 'h' => Alignment::HORIZONTAL_CENTER, 'v' => Alignment::VERTICAL_CENTER, 'border' => true]);
        $this->style($ws, 'A3', ['bold' => false, 'v' => Alignment::VERTICAL_BOTTOM]);
        $this->style($ws, 'B3', ['h' => Alignment::HORIZONTAL_GENERAL]);
        $this->style($ws, 'C3', ['size' => 8]);
        $this->style($ws, 'U3', ['size' => 10]);
        $ws->getStyle('L3')->getNumberFormat()->setFormatCode(self::ACCOUNTING);
        $this->style($ws, 'W3', ['size' => 14, 'bold' => true, 'color' => 'FFFFFF', 'fill' => '7030A0', 'border' => true, 'format' => self::ACCOUNTING]);

        // Student rows.
        $r = $first;
        foreach ($data['students'] as [$enrol, $name, $roll, $months]) {
            $this->setNumberish($ws, "A{$r}", $enrol);
            $ws->setCellValue("B{$r}", (string) $name);
            if ($roll !== null && $roll !== '') {
                $ws->setCellValue("C{$r}", is_numeric($roll) ? (int) $roll : $roll);
            }
            foreach ($months as $col => $present) {
                $ws->setCellValue([$col, $r], $present);
            }
            $ws->setCellValue("K{$r}", "=SUM(D{$r}:J{$r})");
            $ws->setCellValue("L{$r}", "=IFERROR(K{$r}/\$K\$2%,\"\")");
            $ws->setCellValue("S{$r}", "=SUM(M{$r}:R{$r})");
            $ws->setCellValue("T{$r}", "=IFERROR(S{$r}/\$S\$2%,\"\")");
            $ws->setCellValue("U{$r}", "=K{$r}+S{$r}");
            $ws->setCellValue("V{$r}", "=IFERROR(U{$r}/\$U\$2%,\"\")");
            $r++;
        }
        $this->style($ws, "A{$first}:V{$last}", ['border' => true]);
        $this->style($ws, "A{$first}:A{$last}", ['h' => Alignment::HORIZONTAL_CENTER]);
        $this->style($ws, "C{$first}:C{$last}", ['fill' => 'E6B9B8']);
        $this->style($ws, "D{$first}:J{$last}", ['fill' => 'FFFFFF']);
        $this->style($ws, "M{$first}:R{$last}", ['fill' => 'FFFFFF']);
        $this->style($ws, "K{$first}:K{$last}", ['size' => 12, 'fill' => 'D7E4BD']);
        $this->style($ws, "L{$first}:L{$last}", ['size' => 12, 'bold' => true, 'fill' => 'E6B9B8', 'format' => self::ACCOUNTING]);
        $this->style($ws, "T{$first}:T{$last}", ['size' => 12, 'bold' => true, 'fill' => 'E6B9B8', 'format' => self::ACCOUNTING]);
        $this->style($ws, "S{$first}:S{$last}", ['fill' => 'DDD9C3']);
        $this->style($ws, "U{$first}:U{$last}", ['fill' => 'FFFF00']);
        $this->style($ws, "V{$first}:V{$last}", ['size' => 14, 'bold' => true, 'color' => 'FFFFFF', 'fill' => '7030A0', 'format' => self::ACCOUNTING]);

        $widths = ['A' => 10.27, 'B' => 28.54, 'C' => 3.54, 'K' => 5.27, 'L' => 6.82, 'U' => 6.18];
        foreach (range('A', 'V') as $c) {
            $ws->getColumnDimension($c)->setWidth($widths[$c] ?? 5.72);
        }
        $ws->freezePane('D4');
        $this->pageSetup($ws, PageSetup::ORIENTATION_LANDSCAPE);
    }

    /** Classes in the school's sheet order: Nursery, LKG, UKG, then 1st, 2nd … and any others. */
    private function classesInOrder()
    {
        $rank = fn (SchoolClass $c) => match (true) {
            (bool) preg_match('/^(nur|nursery|pre ?nursery|play)/i', $c->name) => [0, 0],
            strcasecmp($c->name, 'LKG') === 0 => [1, 0],
            strcasecmp($c->name, 'UKG') === 0 => [2, 0],
            is_numeric($c->name) => [3, (int) $c->name],
            default => [4, 0],
        };

        return SchoolClass::query()->get(['id', 'name'])->sortBy(fn (SchoolClass $c) => [...$rank($c), $c->name])->values();
    }

    private function sortByRoll($students)
    {
        return $students->sortBy(fn (Student $s) => [$s->roll_no === null ? 1 : 0, (int) $s->roll_no, (string) $s->name]);
    }

    /** Digits-only values (admission no., phone, Aadhaar, PEN) as numbers, like the school's file. */
    private function setNumberish(Worksheet $ws, $cell, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $text = trim((string) $value);
        if (preg_match('/^[1-9]\d{0,14}$/', $text)) {
            $ws->setCellValueExplicit($cell, (int) $text, DataType::TYPE_NUMERIC);
        } else {
            $ws->setCellValue($cell, $value);
        }
    }

    private function style(Worksheet $ws, string $range, array $o): void
    {
        $style = $ws->getStyle($range);
        $font = $style->getFont();
        if (isset($o['size'])) {
            $font->setSize($o['size']);
        }
        if (isset($o['bold'])) {
            $font->setBold($o['bold']);
        }
        if (isset($o['color'])) {
            $font->getColor()->setRGB($o['color']);
        }
        if (isset($o['fill'])) {
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($o['fill']);
        }
        if (isset($o['h'])) {
            $style->getAlignment()->setHorizontal($o['h']);
        }
        if (isset($o['v'])) {
            $style->getAlignment()->setVertical($o['v']);
        }
        if (! empty($o['border'])) {
            $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        if (isset($o['format'])) {
            $style->getNumberFormat()->setFormatCode($o['format']);
        }
    }

    private function pageSetup(Worksheet $ws, string $orientation): void
    {
        $ws->getPageSetup()->setOrientation($orientation)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(1);
        $ws->getPageMargins()->setTop(0.27)->setLeft(0.31);
    }
}
