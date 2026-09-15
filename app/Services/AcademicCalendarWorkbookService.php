<?php

namespace App\Services;

use App\Models\AcademicCalendarEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Parses / builds the school's "Academic Calendar … At A Glance" workbook layout:
 * three parallel columns — School Holidays | Examinations | Programme — plus Deadlines.
 */
class AcademicCalendarWorkbookService
{
    private const MONTHS = [
        'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6,
        'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12,
        'JANUARY' => 1, 'FEBRUARY' => 2, 'MARCH' => 3, 'APRIL' => 4, 'JUNE' => 6,
        'JULY' => 7, 'AUGUST' => 8, 'SEPTEMBER' => 9, 'OCTOBER' => 10, 'NOVEMBER' => 11, 'DECEMBER' => 12,
    ];

    /**
     * @return array{title: string, session_start_year: int, session_end_year: int, entries: list<array<string, mixed>>}
     */
    public function parsePath(string $path): array
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheet(0);
        $parsed = $this->parseWorksheet($sheet);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $parsed;
    }

    /**
     * @return array{title: string, session_start_year: int, session_end_year: int, entries: list<array<string, mixed>>}
     */
    public function parseWorksheet(Worksheet $sheet): array
    {
        $highestRow = (int) $sheet->getHighestDataRow();
        $title = trim((string) $sheet->getCell('A1')->getValue());
        [$startYear, $endYear] = $this->detectSessionYears($title);

        $entries = [];
        $sortHoliday = 0;
        $sortExam = 0;
        $sortProgramme = 0;
        $sortDeadline = 0;
        $inDeadlines = false;

        for ($r = 4; $r <= $highestRow; $r++) {
            $a = $this->cellString($sheet, 'A', $r);
            $b = $this->cellString($sheet, 'B', $r);
            $c = $this->cellRaw($sheet, 'C', $r);
            $d = $this->cellString($sheet, 'D', $r);
            $e = $this->cellRaw($sheet, 'E', $r);
            $f = $this->cellString($sheet, 'F', $r);
            $g = $this->cellString($sheet, 'G', $r);
            $h = $this->cellRaw($sheet, 'H', $r);

            if ($a !== '' && strtoupper($a) === 'DEADLINES') {
                $inDeadlines = true;
                continue;
            }

            // Footer / school name row — stop holiday/deadline columns when we hit it,
            // but keep reading programme column if present.
            $isFooter = $a !== '' && preg_match('/\bschool\b/i', $a) && $b === '' && $this->isBlankDate($c);

            if (! $isFooter) {
                if ($inDeadlines) {
                    if ($a !== '') {
                        $dates = $this->resolveDates($c, $b, $startYear, $endYear);
                        $entries[] = [
                            'category' => AcademicCalendarEntry::CATEGORY_DEADLINE,
                            'title' => $a,
                            'month_label' => $b !== '' ? $b : null,
                            'date_label' => $this->dateLabelFromRaw($c),
                            'start_date' => $dates['start'],
                            'end_date' => $dates['end'],
                            'sort_order' => $sortDeadline++,
                        ];
                    }
                } elseif ($a !== '' && ! in_array(strtoupper($a), ['OCCASION', 'SCHOOL HOLIDAYS'], true)) {
                    $dates = $this->resolveDates($c, $b, $startYear, $endYear);
                    $entries[] = [
                        'category' => AcademicCalendarEntry::CATEGORY_HOLIDAY,
                        'title' => $a,
                        'month_label' => $b !== '' ? $b : null,
                        'date_label' => $this->dateLabelFromRaw($c),
                        'start_date' => $dates['start'],
                        'end_date' => $dates['end'],
                        'sort_order' => $sortHoliday++,
                    ];
                }

                if ($d !== '' && ! in_array(strtoupper($d), ['TEST', 'EXAMINATIONS'], true)) {
                    $dates = $this->resolveDates($e, null, $startYear, $endYear);
                    $entries[] = [
                        'category' => AcademicCalendarEntry::CATEGORY_EXAMINATION,
                        'title' => $d,
                        'month_label' => null,
                        'date_label' => $this->dateLabelFromRaw($e),
                        'start_date' => $dates['start'],
                        'end_date' => $dates['end'],
                        'sort_order' => $sortExam++,
                    ];
                }
            }

            if ($f !== '' && ! in_array(strtoupper($f), ['EVENTS', 'PROGRAMME', 'PROGRAM'], true)) {
                $dates = $this->resolveDates($h, $g, $startYear, $endYear);
                $entries[] = [
                    'category' => AcademicCalendarEntry::CATEGORY_PROGRAMME,
                    'title' => $f,
                    'month_label' => $g !== '' ? $g : null,
                    'date_label' => $this->dateLabelFromRaw($h),
                    'start_date' => $dates['start'],
                    'end_date' => $dates['end'],
                    'sort_order' => $sortProgramme++,
                ];
            }
        }

        return [
            'title' => $title !== '' ? $title : 'Academic Calendar',
            'session_start_year' => $startYear,
            'session_end_year' => $endYear,
            'entries' => $entries,
        ];
    }

    /**
     * Build an Excel workbook matching the import glance layout.
     *
     * @param  Collection<int, AcademicCalendarEntry>|iterable<AcademicCalendarEntry>  $entries
     */
    public function buildSpreadsheet(iterable $entries, ?string $title = null, ?int $startYear = null, ?int $endYear = null): Spreadsheet
    {
        $items = collect($entries)->values();
        $startYear ??= (int) ($items->pluck('start_date')->filter()->map(fn ($d) => (int) Carbon::parse($d)->year)->min() ?: now()->year);
        $endYear ??= $startYear + 1;
        $title ??= sprintf('ACADEMIC CALENDER %d-%s AT A GLANCE', $startYear, substr((string) $endYear, -2));

        $holidays = $items->where('category', AcademicCalendarEntry::CATEGORY_HOLIDAY)->sortBy('sort_order')->values();
        $exams = $items->where('category', AcademicCalendarEntry::CATEGORY_EXAMINATION)->sortBy('sort_order')->values();
        $programmes = $items->where('category', AcademicCalendarEntry::CATEGORY_PROGRAMME)->sortBy('sort_order')->values();
        $deadlines = $items->where('category', AcademicCalendarEntry::CATEGORY_DEADLINE)->sortBy('sort_order')->values();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Table 1');

        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'SCHOOL HOLIDAYS');
        $sheet->mergeCells('A2:C2');
        $sheet->setCellValue('D2', 'EXAMINATIONS');
        $sheet->mergeCells('D2:E2');
        $sheet->setCellValue('F2', 'PROGRAMME');
        $sheet->mergeCells('F2:H2');

        $sheet->fromArray(['OCCASION', 'MONTH', 'DATE', 'TEST', 'DATE', 'EVENTS', 'MONTH', 'DATE'], null, 'A3');

        $headerFill = '1E3A5F';
        foreach (['A2:C2', 'D2:E2', 'F2:H2', 'A3:H3'] as $range) {
            $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($headerFill);
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $max = max($holidays->count(), $exams->count(), $programmes->count(), 1);
        for ($i = 0; $i < $max; $i++) {
            $row = 4 + $i;
            if ($holidays->has($i)) {
                $h = $holidays[$i];
                $sheet->setCellValue("A{$row}", $h->title);
                $sheet->setCellValue("B{$row}", $h->month_label);
                $sheet->setCellValue("C{$row}", $h->date_label ?: $this->formatDateRange($h->start_date, $h->end_date));
            }
            if ($exams->has($i)) {
                $e = $exams[$i];
                $sheet->setCellValue("D{$row}", $e->title);
                $sheet->setCellValue("E{$row}", $e->date_label ?: $this->formatDateRange($e->start_date, $e->end_date));
            }
            if ($programmes->has($i)) {
                $p = $programmes[$i];
                $sheet->setCellValue("F{$row}", $p->title);
                $sheet->setCellValue("G{$row}", $p->month_label);
                $sheet->setCellValue("H{$row}", $p->date_label ?: $this->formatDateRange($p->start_date, $p->end_date));
            }
        }

        $deadlineStart = 4 + $max + 1;
        $sheet->setCellValue("A{$deadlineStart}", 'DEADLINES');
        $sheet->setCellValue('B'.$deadlineStart, 'DATE HY');
        $sheet->setCellValue('C'.$deadlineStart, 'DATE ANNUAL');
        $sheet->getStyle("A{$deadlineStart}:C{$deadlineStart}")->getFont()->setBold(true);

        $r = $deadlineStart + 1;
        foreach ($deadlines as $deadline) {
            $sheet->setCellValue("A{$r}", $deadline->title);
            $sheet->setCellValue("B{$r}", $deadline->month_label);
            $sheet->setCellValue("C{$r}", $deadline->date_label ?: $this->formatDateRange($deadline->start_date, $deadline->end_date));
            $r++;
        }

        // Continue any leftover programme rows that started below holiday/exam block.
        if ($programmes->count() > $max) {
            // already written in main loop via max
        }

        foreach (range(1, 8) as $col) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }

        $sheet->getStyle('A2:H'.max($deadlineStart + $deadlines->count(), 4 + $max))
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        return $spreadsheet;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function detectSessionYears(string $title): array
    {
        if (preg_match('/(20\d{2})\s*[-–\/]\s*(\d{2}|\d{4})/', $title, $m)) {
            $start = (int) $m[1];
            $end = strlen($m[2]) === 2 ? (int) (substr((string) $start, 0, 2).$m[2]) : (int) $m[2];

            return [$start, $end];
        }

        $year = (int) now()->year;

        return [$year, $year + 1];
    }

    private function cellString(Worksheet $sheet, string $col, int $row): string
    {
        $v = $sheet->getCell("{$col}{$row}")->getValue();
        if ($v instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            $v = $v->getPlainText();
        }

        return trim((string) ($v ?? ''));
    }

    private function cellRaw(Worksheet $sheet, string $col, int $row): mixed
    {
        $v = $sheet->getCell("{$col}{$row}")->getValue();
        if ($v instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            return $v->getPlainText();
        }

        return $v;
    }

    private function isBlankDate(mixed $raw): bool
    {
        if ($raw === null || $raw === '') {
            return true;
        }
        $s = trim((string) $raw);

        return $s === '' || preg_match('/^-+$/', $s) === 1;
    }

    private function dateLabelFromRaw(mixed $raw): ?string
    {
        if ($this->isBlankDate($raw)) {
            return null;
        }
        if (is_numeric($raw)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $raw)->format('d-M');
            } catch (\Throwable) {
                return (string) $raw;
            }
        }

        return trim((string) $raw);
    }

    /**
     * @return array{start: ?string, end: ?string}
     */
    private function resolveDates(mixed $raw, ?string $monthHint, int $startYear, int $endYear): array
    {
        if ($this->isBlankDate($raw)) {
            return ['start' => null, 'end' => null];
        }

        if (is_numeric($raw)) {
            $iso = ExcelDateParser::parse($raw);

            return ['start' => $iso, 'end' => $iso];
        }

        $text = trim((string) $raw);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        // 25-MAY-14-JUN / 29-APR-2-MAY
        if (preg_match('/^(\d{1,2})[- ]([A-Za-z]{3})[- ](\d{1,2})[- ]([A-Za-z]{3})$/i', $text, $m)) {
            $start = $this->composeDate((int) $m[1], $m[2], $monthHint, $startYear, $endYear);
            $end = $this->composeDate((int) $m[3], $m[4], $monthHint, $startYear, $endYear);

            return ['start' => $start, 'end' => $end ?? $start];
        }

        // 24-27 JUL / 15-18 OCT
        if (preg_match('/^(\d{1,2})\s*[-–]\s*(\d{1,2})\s+([A-Za-z]{3})$/i', $text, $m)) {
            $start = $this->composeDate((int) $m[1], $m[3], $monthHint, $startYear, $endYear);
            $end = $this->composeDate((int) $m[2], $m[3], $monthHint, $startYear, $endYear);

            return ['start' => $start, 'end' => $end ?? $start];
        }

        // 28-Aug 03-Sep / 28-Aug 03-Sep
        if (preg_match('/^(\d{1,2})[- ]([A-Za-z]{3})\s+(\d{1,2})[- ]([A-Za-z]{3})$/i', $text, $m)) {
            $start = $this->composeDate((int) $m[1], $m[2], $monthHint, $startYear, $endYear);
            $end = $this->composeDate((int) $m[3], $m[4], $monthHint, $startYear, $endYear);

            return ['start' => $start, 'end' => $end ?? $start];
        }

        // 14-Apr / 22-Apr / 04-May
        if (preg_match('/^(\d{1,2})[- ]([A-Za-z]{3})(?:[- ](\d{2,4}))?$/i', $text, $m)) {
            $yearOverride = isset($m[3]) && $m[3] !== '' ? (int) (strlen($m[3]) === 2 ? '20'.$m[3] : $m[3]) : null;
            $start = $this->composeDate((int) $m[1], $m[2], $monthHint, $startYear, $endYear, $yearOverride);

            return ['start' => $start, 'end' => $start];
        }

        $iso = ExcelDateParser::parse($text);
        if ($iso) {
            return ['start' => $iso, 'end' => $iso];
        }

        return ['start' => null, 'end' => null];
    }

    private function composeDate(int $day, string $monthName, ?string $monthHint, int $startYear, int $endYear, ?int $yearOverride = null): ?string
    {
        $month = self::MONTHS[strtoupper(substr($monthName, 0, 3))] ?? self::MONTHS[strtoupper($monthName)] ?? null;
        if (! $month) {
            $hint = strtoupper((string) $monthHint);
            foreach (self::MONTHS as $name => $num) {
                if (str_contains($hint, $name)) {
                    $month = $num;
                    break;
                }
            }
        }
        if (! $month || ! checkdate($month, $day, $yearOverride ?? $startYear)) {
            // try with computed year below
        }

        $year = $yearOverride;
        if ($year === null) {
            $year = $month >= 4 ? $startYear : $endYear;
        }

        if (! $month || ! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function formatDateRange(mixed $start, mixed $end): ?string
    {
        if (! $start) {
            return null;
        }
        $s = Carbon::parse($start);
        if (! $end || Carbon::parse($end)->equalTo($s)) {
            return $s->format('d-M');
        }
        $e = Carbon::parse($end);
        if ($s->month === $e->month) {
            return $s->format('d').'-'.$e->format('d M');
        }

        return $s->format('d-M').'-'.$e->format('d-M');
    }
}
