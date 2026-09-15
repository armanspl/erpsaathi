<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\SalarySlip;
use App\Models\Staff;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Parses / imports / builds the school's yearly "SALARY 26-27 Bank" workbook sheet:
 *
 *   SL NO | NAME | DESIG | BASIC SALARY | APR … MAR | TOTAL
 *
 * Teachers (PRINCIPAL / ASST TCHR / …) and staff (NIGHT GUARD / OFFICE / …) sit in
 * separate blocks under the same header. Month cells hold the amount paid that month;
 * when paid < basic, present/absent days are inferred for salary_slips so Teachers /
 * Staff screens can manage attendance afterward.
 */
class SalaryBankWorkbookService
{
    /** Calendar month number keyed by normalized header label. */
    private const MONTH_HEADERS = [
        'apr' => 4, 'april' => 4,
        'may' => 5,
        'jun' => 6, 'june' => 6,
        'jul' => 7, 'july' => 7,
        'aug' => 8, 'august' => 8,
        'sep' => 9, 'sept' => 9, 'september' => 9,
        'oct' => 10, 'october' => 10,
        'nov' => 11, 'november' => 11,
        'dec' => 12, 'december' => 12,
        'jan' => 1, 'january' => 1,
        'feb' => 2, 'february' => 2,
        'mar' => 3, 'march' => 3,
    ];

    /** Export column order (matches school source file). */
    public const EXPORT_MONTHS = ['APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC', 'JAN', 'FEB', 'MAR'];

    /**
     * Normalized DESIG → employee table. Unrecognized designations fall back to staff.
     *
     * @var array<string, string>
     */
    private const DESIGNATION_TYPE_MAP = [
        'PRINCIPAL' => 'teacher',
        'ASST TCHR' => 'teacher',
        'ASST. TCHR' => 'teacher',
        'ASST TEACHER' => 'teacher',
        'ASSISTANT TEACHER' => 'teacher',
        'TCHR' => 'teacher',
        'TEACHER' => 'teacher',
        'COMP TCHR' => 'teacher',
        'COMP TEACHER' => 'teacher',
        'COMPUTER TEACHER' => 'teacher',
        'HEAD TEACHER' => 'teacher',
        'HT' => 'teacher',
        'NIGHT GUARD' => 'staff',
        'GUARD' => 'staff',
        'OFF CLERK' => 'staff',
        'OFFICE CLERK' => 'staff',
        'OFFICE' => 'staff',
        'OFFICE ASST' => 'staff',
        'OFFICE ASSISTANT' => 'staff',
        'ACCOUNTANT' => 'staff',
        'ADMINISTRATOR' => 'staff',
        'ADMIN' => 'staff',
        'BOOA' => 'staff',
        'COMP OPERATOR' => 'staff',
        'COMPUTER OPERATOR' => 'staff',
        'DRIVER' => 'driver',
    ];

    public function isSalaryBankSheetTitle(string $title): bool
    {
        $lower = mb_strtolower(trim($title));
        if ($lower === '') {
            return false;
        }
        // "SALARY 26-27 Bank", "SALARY 2026-27 Bank", "Salary Bank", …
        if (str_starts_with($lower, 'salary') && str_contains($lower, 'bank')) {
            return true;
        }

        return (bool) preg_match('/^salary\s+\d{2,4}\s*[-–]\s*\d{2,4}\b/i', $title);
    }

    /**
     * Detect session start year from sheet title ("SALARY 26-27 Bank" → 2026).
     */
    public function detectSessionStartYear(string $title, ?int $fallback = null): int
    {
        if (preg_match('/(?:20)?(\d{2})\s*[-–\/]\s*(\d{2}|\d{4})/', $title, $m)) {
            $yy = (int) $m[1];

            return $yy >= 100 ? $yy : 2000 + $yy;
        }

        return $fallback ?? (int) now()->year;
    }

    public function sheetTitleForYear(int $startYear): string
    {
        return sprintf('SALARY %02d-%02d Bank', $startYear % 100, ($startYear + 1) % 100);
    }

    /**
     * @return array{
     *   employees_created: int,
     *   employees_updated: int,
     *   slips_written: int,
     *   rows_skipped: int,
     *   failed: list<array{row:int,name:string,reason:string}>,
     *   created: list<array{type:string,id:int,employee_id:string,name:string}>
     * }
     */
    public function importWorksheet(Worksheet $sheet, ?string $sheetTitle = null, ?int $userId = null): array
    {
        $title = $sheetTitle ?? (string) $sheet->getTitle();
        $sessionStartYear = $this->detectSessionStartYear($title);
        $parsed = $this->withPeriodKeys($this->parseWorksheet($sheet, $sessionStartYear));

        return $this->importParsedRows($parsed, $userId ?? Auth::guard('erp')->id());
    }

    /**
     * @return array{
     *   header_row: int,
     *   session_start_year: int,
     *   rows: list<array{row_number:int,name:string,designation:string,type:string,basic_salary:float,months:array<string,float>}>
     * }
     */
    public function parseWorksheet(Worksheet $sheet, ?int $sessionStartYear = null): array
    {
        $sessionStartYear ??= $this->detectSessionStartYear((string) $sheet->getTitle());
        $highestRow = (int) $sheet->getHighestDataRow();
        $highestCol = Coordinate::columnIndexFromString($sheet->getHighestDataColumn() ?: 'A');

        $headerRow = $this->findHeaderRow($sheet, $highestRow, $highestCol);
        if ($headerRow === null) {
            return ['header_row' => 0, 'session_start_year' => $sessionStartYear, 'rows' => []];
        }

        $colMap = $this->mapHeaderColumns($sheet, $headerRow, $highestCol);
        if (! isset($colMap['name'], $colMap['desig'], $colMap['basic'])) {
            return ['header_row' => $headerRow, 'session_start_year' => $sessionStartYear, 'rows' => []];
        }

        $rows = [];
        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $name = trim((string) $this->cellValue($sheet, $colMap['name'], $r));
            $desig = trim((string) $this->cellValue($sheet, $colMap['desig'], $r));
            $basicRaw = $this->cellValue($sheet, $colMap['basic'], $r);

            // Totals / blank separators — skip.
            if ($name === '' || $desig === '') {
                continue;
            }
            if (! is_numeric($basicRaw)) {
                continue;
            }

            $months = [];
            foreach ($colMap['months'] as $period => $col) {
                $raw = $this->cellValue($sheet, $col, $r);
                if ($raw === null || trim((string) $raw) === '') {
                    continue; // month not filled yet
                }
                if (! is_numeric($raw)) {
                    continue;
                }
                $months[$period] = round((float) $raw, 2);
            }

            $rows[] = [
                'row_number' => $r,
                'name' => preg_replace('/\s+/', ' ', $name) ?? $name,
                'designation' => $desig,
                'type' => $this->resolveEmployeeType($desig),
                'basic_salary' => round((float) $basicRaw, 2),
                'months' => $months,
            ];
        }

        return [
            'header_row' => $headerRow,
            'session_start_year' => $sessionStartYear,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{rows: list<array<string,mixed>>}  $parsed
     * @return array{
     *   employees_created: int,
     *   employees_updated: int,
     *   slips_written: int,
     *   rows_skipped: int,
     *   failed: list<array{row:int,name:string,reason:string}>,
     *   created: list<array{type:string,id:int,employee_id:string,name:string}>
     * }
     */
    public function importParsedRows(array $parsed, ?int $userId = null): array
    {
        $stats = [
            'employees_created' => 0,
            'employees_updated' => 0,
            'slips_written' => 0,
            'rows_skipped' => 0,
            'failed' => [],
            'created' => [],
        ];

        $nameLookup = $this->loadNameLookup();
        $slipCounter = (int) SalarySlip::query()->where('slip_no', 'like', 'SB-'.now()->format('Ym').'-%')->count();

        DB::transaction(function () use ($parsed, $userId, &$stats, &$nameLookup, &$slipCounter) {
            foreach ($parsed['rows'] as $row) {
                $type = $row['type'];
                if ($type === null) {
                    $stats['rows_skipped']++;
                    $stats['failed'][] = [
                        'row' => $row['row_number'],
                        'name' => $row['name'],
                        'reason' => 'Unrecognized designation "'.$row['designation'].'" — could not determine Teacher/Staff/Driver.',
                    ];

                    continue;
                }

                $nameKey = mb_strtolower($row['name']);
                $matches = $nameLookup[$type][$nameKey] ?? [];

                if (count($matches) > 1) {
                    $stats['rows_skipped']++;
                    $stats['failed'][] = [
                        'row' => $row['row_number'],
                        'name' => $row['name'],
                        'reason' => 'Name matches more than one '.$type.' — resolve manually.',
                    ];

                    continue;
                }

                if (count($matches) === 1) {
                    $employee = $matches[0];
                    $employee->salary = $row['basic_salary'];
                    $employee->status = $employee->status ?: 'active';
                    $cf = is_array($employee->custom_field_values) ? $employee->custom_field_values : [];
                    $cf['Designation'] = $row['designation'];
                    $employee->custom_field_values = $cf;
                    $employee->save();
                    $stats['employees_updated']++;
                } else {
                    $employee = $this->createEmployee($type, $row['name'], $row['basic_salary'], $row['designation']);
                    $nameLookup[$type][$nameKey] = [$employee];
                    $stats['employees_created']++;
                    $stats['created'][] = [
                        'type' => $type,
                        'id' => $employee->id,
                        'employee_id' => $employee->employee_id,
                        'name' => $employee->name,
                    ];
                }

                foreach ($row['months'] as $period => $paid) {
                    $this->upsertSlip($type, $employee->id, $period, $row['basic_salary'], $paid, $userId, $slipCounter);
                    $stats['slips_written']++;
                }
            }
        });

        return $stats;
    }

    /**
     * Build export rows in the school's SALARY Bank layout.
     *
     * @return array{0: list<string>, 1: list<list<mixed>>, 2: string} [headers, rows, sheetTitle]
     */
    public function buildExport(?int $sessionStartYear = null): array
    {
        $sessionStartYear ??= (int) now()->year;
        $periods = $this->periodsForSession($sessionStartYear);
        $headers = array_merge(['SL NO', 'NAME', 'DESIG', 'BASIC SALARY'], self::EXPORT_MONTHS, ['TOTAL']);

        $teachers = Teacher::query()->orderBy('name')->get();
        $staff = Staff::query()->orderBy('name')->get();
        $drivers = Driver::query()->orderBy('name')->get();

        $slips = SalarySlip::query()
            ->whereIn('period', array_values($periods))
            ->get()
            ->groupBy(fn (SalarySlip $s) => $s->employee_type.'|'.$s->employee_id);

        $rows = [];
        $blocks = [
            ['teacher', $teachers],
            ['staff', $staff],
            ['driver', $drivers],
        ];

        foreach ($blocks as [$type, $collection]) {
            if ($collection->isEmpty()) {
                continue;
            }
            if ($rows !== []) {
                $rows[] = array_fill(0, count($headers), null);
            }

            $sl = 1;
            $blockHadRow = false;
            foreach ($collection as $employee) {
                $key = $type.'|'.$employee->id;
                $empSlips = $slips->get($key, collect());
                $byPeriod = $empSlips->keyBy('period');

                $basic = (float) ($employee->salary ?? $empSlips->max('basic_salary') ?? 0);
                $desig = $this->designationLabel($employee, $type);
                $monthValues = [];
                $total = 0.0;
                $hasAny = false;
                foreach (self::EXPORT_MONTHS as $label) {
                    $period = $periods[$label];
                    $slip = $byPeriod->get($period);
                    if ($slip) {
                        $amt = (float) ($slip->this_month_salary ?? $slip->net_salary ?? 0);
                        $monthValues[] = $amt;
                        $total += $amt;
                        $hasAny = true;
                        if ($basic <= 0) {
                            $basic = (float) $slip->basic_salary;
                        }
                    } else {
                        $monthValues[] = null;
                    }
                }

                if ($basic <= 0 && ! $hasAny) {
                    continue;
                }

                $rows[] = array_merge(
                    [$sl++, $employee->name, $desig, $basic > 0 ? $basic : null],
                    $monthValues,
                    [$hasAny ? round($total, 2) : null]
                );
                $blockHadRow = true;
            }

            // Remove trailing blank separator if block produced no rows.
            if (! $blockHadRow && $rows !== [] && $this->isBlankSeparator(end($rows))) {
                array_pop($rows);
            }
        }

        return [$headers, $rows, $this->sheetTitleForYear($sessionStartYear)];
    }

    /** @return array{0: list<string>, 1: list<list<mixed>>, 2: string} */
    public function buildSampleTemplate(?int $sessionStartYear = null): array
    {
        $sessionStartYear ??= (int) now()->year;
        $headers = array_merge(['SL NO', 'NAME', 'DESIG', 'BASIC SALARY'], self::EXPORT_MONTHS, ['TOTAL']);
        $rows = [
            [1, 'SAMPLE TEACHER', 'ASST TCHR', 20000, 20000, 18000, 20000, 20000, null, null, null, null, null, null, null, null, 78000],
            [2, 'SAMPLE PRINCIPAL', 'PRINCIPAL', 35000, 35000, 35000, 35000, 35000, null, null, null, null, null, null, null, null, 140000],
            array_fill(0, count($headers), null),
            [1, 'SAMPLE GUARD', 'NIGHT GUARD', 14000, 14000, 12000, 14000, 14000, null, null, null, null, null, null, null, null, 54000],
            [2, 'SAMPLE CLERK', 'OFF CLERK', 17000, 17000, 17000, 17000, 17000, null, null, null, null, null, null, null, null, 68000],
        ];

        return [$headers, $rows, $this->sheetTitleForYear($sessionStartYear)];
    }

    public function appendSampleSheet(Spreadsheet $spreadsheet, ?int $sessionStartYear = null): void
    {
        [$headers, $rows, $title] = $this->buildSampleTemplate($sessionStartYear);
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(mb_substr($title, 0, 31));
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:Q1')->getFont()->setBold(true);
        $sheet->getStyle('A1:Q1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }
    }

    private function isBlankSeparator(array $row): bool
    {
        foreach ($row as $v) {
            if ($v !== null && $v !== '') {
                return false;
            }
        }

        return true;
    }

    private function designationLabel(Teacher|Staff|Driver $employee, string $type): string
    {
        $cf = is_array($employee->custom_field_values) ? $employee->custom_field_values : [];
        if (! empty($cf['Designation'])) {
            return (string) $cf['Designation'];
        }
        if (! empty($cf['POST'])) {
            return (string) $cf['POST'];
        }

        return match ($type) {
            'teacher' => 'ASST TCHR',
            'driver' => 'DRIVER',
            default => 'OFFICE',
        };
    }

    /**
     * @return array<string, string> month label => YYYY-MM
     */
    private function periodsForSession(int $sessionStartYear): array
    {
        $map = [];
        foreach (self::EXPORT_MONTHS as $label) {
            $month = self::MONTH_HEADERS[strtolower($label)];
            $year = $month >= 4 ? $sessionStartYear : $sessionStartYear + 1;
            $map[$label] = sprintf('%04d-%02d', $year, $month);
        }

        return $map;
    }

    private function resolveEmployeeType(string $desig): ?string
    {
        $key = strtoupper(trim(preg_replace('/\s+/', ' ', $desig) ?? $desig));
        $key = str_replace(['.', '_'], ['', ' '], $key);
        $key = strtoupper(trim(preg_replace('/\s+/', ' ', $key) ?? $key));

        if (isset(self::DESIGNATION_TYPE_MAP[$key])) {
            return self::DESIGNATION_TYPE_MAP[$key];
        }

        // Soft fallbacks by keyword.
        if (preg_match('/\b(TCHR|TEACHER|PRINCIPAL|HT)\b/i', $desig)) {
            return 'teacher';
        }
        if (preg_match('/\bDRIVER\b/i', $desig)) {
            return 'driver';
        }
        if (preg_match('/\b(GUARD|OFFICE|CLERK|ACCOUNT|ADMIN|STAFF|BOOA)\b/i', $desig)) {
            return 'staff';
        }

        return 'staff';
    }

    private function createEmployee(string $type, string $name, float $basic, string $desig): Teacher|Staff|Driver
    {
        $code = $this->uniqueEmployeeCode($type, $name);
        $payload = [
            'employee_id' => $code,
            'name' => $name,
            'status' => 'active',
            'salary' => $basic,
            'custom_field_values' => ['Designation' => $desig],
        ];

        return match ($type) {
            'teacher' => Teacher::create($payload),
            'driver' => Driver::create($payload),
            default => Staff::create($payload),
        };
    }

    private function uniqueEmployeeCode(string $type, string $name): string
    {
        $prefix = match ($type) {
            'teacher' => 'T',
            'driver' => 'D',
            default => 'S',
        };
        $slug = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name) ?? 'EMP');
        $slug = substr($slug !== '' ? $slug : 'EMP', 0, 8);
        $base = $prefix.'-'.$slug;
        $code = $base;
        $n = 1;
        while (
            Teacher::where('employee_id', $code)->exists()
            || Staff::where('employee_id', $code)->exists()
            || Driver::where('employee_id', $code)->exists()
        ) {
            $code = $base.'-'.$n;
            $n++;
        }

        return $code;
    }

    private function upsertSlip(
        string $employeeType,
        int $employeeId,
        string $period,
        float $basic,
        float $paid,
        ?int $userId,
        int &$slipCounter
    ): void {
        [$year, $month] = array_map('intval', explode('-', $period));
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $perDay = $daysInMonth > 0 ? round($basic / $daysInMonth, 2) : 0.0;

        if ($basic <= 0) {
            $present = 0.0;
            $absent = (float) $daysInMonth;
        } elseif ($paid <= 0) {
            $present = 0.0;
            $absent = (float) $daysInMonth;
        } elseif ($paid >= $basic) {
            $present = (float) $daysInMonth;
            $absent = 0.0;
        } else {
            $present = $perDay > 0 ? round(min($daysInMonth, $paid / $perDay), 2) : 0.0;
            $absent = round(max(0, $daysInMonth - $present), 2);
        }

        $totalDays = $present;
        $thisMonthSalary = round($paid, 2);
        $advance = 0.0;
        $netSalary = $thisMonthSalary;

        $payload = [
            'basic_salary' => $basic,
            'allowances' => 0,
            'earnings' => [['label' => 'Basic', 'amount' => $basic]],
            'deductions' => 0,
            'deduction_items' => [],
            'days_in_month' => $daysInMonth,
            'present' => $present,
            'absent' => $absent,
            'cl' => 0,
            'total_days' => $totalDays,
            'per_day_rate' => $perDay,
            'this_month_salary' => $thisMonthSalary,
            'advance' => $advance,
            'net_salary' => $netSalary,
            'payment_mode' => 'Bank',
            'remarks' => 'Imported from SALARY Bank sheet',
        ];

        $existing = SalarySlip::query()
            ->where('employee_type', $employeeType)
            ->where('employee_id', $employeeId)
            ->where('period', $period)
            ->first();

        if ($existing) {
            // Don't overwrite an already-Paid slip's status; refresh figures.
            $existing->update($payload);

            return;
        }

        $slipCounter++;
        $payload['employee_type'] = $employeeType;
        $payload['employee_id'] = $employeeId;
        $payload['period'] = $period;
        $payload['slip_no'] = 'SB-'.now()->format('Ym').'-'.str_pad((string) $slipCounter, 4, '0', STR_PAD_LEFT);
        $payload['status'] = $paid > 0 ? 'Paid' : 'Pending';
        $payload['paid_on'] = $paid > 0 ? Carbon::create($year, $month, min(28, $daysInMonth))->toDateString() : null;
        $payload['generated_by_id'] = $userId;
        SalarySlip::create($payload);
    }

    /**
     * @return array<string, array<string, list<Teacher|Staff|Driver>>>
     */
    private function loadNameLookup(): array
    {
        $lookup = ['teacher' => [], 'staff' => [], 'driver' => []];
        foreach (Teacher::query()->get(['id', 'employee_id', 'name', 'status', 'salary', 'custom_field_values']) as $t) {
            $lookup['teacher'][mb_strtolower(trim($t->name))][] = $t;
        }
        foreach (Staff::query()->get(['id', 'employee_id', 'name', 'status', 'salary', 'custom_field_values']) as $s) {
            $lookup['staff'][mb_strtolower(trim($s->name))][] = $s;
        }
        foreach (Driver::query()->get(['id', 'employee_id', 'name', 'status', 'salary', 'custom_field_values']) as $d) {
            $lookup['driver'][mb_strtolower(trim($d->name))][] = $d;
        }

        return $lookup;
    }

    private function findHeaderRow(Worksheet $sheet, int $highestRow, int $highestCol): ?int
    {
        $scan = min(8, $highestRow);
        for ($r = 1; $r <= $scan; $r++) {
            $joined = '';
            for ($c = 1; $c <= min(20, $highestCol); $c++) {
                $joined .= ' '.$this->normalizeHeader((string) $this->cellValue($sheet, $c, $r));
            }
            if (str_contains($joined, 'name') && (str_contains($joined, 'desig') || str_contains($joined, 'basic'))) {
                return $r;
            }
        }

        return 1;
    }

    /**
     * @return array{name?: int, desig?: int, basic?: int, months: array<string, int>}
     */
    private function mapHeaderColumns(Worksheet $sheet, int $headerRow, int $highestCol): array
    {
        $map = ['months' => []];
        for ($c = 1; $c <= $highestCol; $c++) {
            $label = $this->normalizeHeader((string) $this->cellValue($sheet, $c, $headerRow));
            if ($label === '') {
                continue;
            }
            if (in_array($label, ['name', 'employee name', 'emp name'], true)) {
                $map['name'] = $c;
            } elseif (in_array($label, ['desig', 'designation', 'post', 'design'], true) || str_starts_with($label, 'desig')) {
                $map['desig'] = $c;
            } elseif (str_contains($label, 'basic') && str_contains($label, 'salary')) {
                $map['basic'] = $c;
            } elseif (isset(self::MONTH_HEADERS[$label])) {
                $month = self::MONTH_HEADERS[$label];
                // APR–DEC of start year, JAN–MAR of next — period string filled later via session year.
                // Store temporary key by month number; convert after we know session year.
                $map['months']['_m'.$month] = $c;
            }
        }

        // Convert month-number placeholders to YYYY-MM using session year from caller —
        // here we only keep column indexes keyed by export label; period resolved at import.
        $resolved = [];
        foreach ($map['months'] as $key => $col) {
            if (! str_starts_with($key, '_m')) {
                continue;
            }
            $month = (int) substr($key, 2);
            $label = match ($month) {
                1 => 'JAN', 2 => 'FEB', 3 => 'MAR', 4 => 'APR', 5 => 'MAY', 6 => 'JUN',
                7 => 'JUL', 8 => 'AUG', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DEC',
                default => null,
            };
            if ($label) {
                $resolved[$label] = $col;
            }
        }
        $map['months'] = $resolved;

        return $map;
    }

    /**
     * Re-key month amounts using session start year after parse.
     * Called from parseWorksheet via periodsForSession at import time —
     * parse stores month labels; importParsedRows expects YYYY-MM keys.
     */
    public function withPeriodKeys(array $parsed): array
    {
        $periods = $this->periodsForSession((int) $parsed['session_start_year']);
        foreach ($parsed['rows'] as &$row) {
            $converted = [];
            foreach ($row['months'] as $label => $amount) {
                $period = $periods[strtoupper($label)] ?? null;
                if ($period) {
                    $converted[$period] = $amount;
                }
            }
            $row['months'] = $converted;
        }
        unset($row);

        return $parsed;
    }

    private function normalizeHeader(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['.', '_'], ['', ' '], $value);

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function cellValue(Worksheet $sheet, int $col, int $row): mixed
    {
        $letter = Coordinate::stringFromColumnIndex($col);
        $cell = $sheet->getCell($letter.$row);
        $v = $cell->getCalculatedValue();
        if ($v === null || $v === '' || (is_string($v) && str_starts_with($v, '='))) {
            $v = $cell->getValue();
        }
        if ($v instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            $v = $v->getPlainText();
        }

        return $v;
    }
}
