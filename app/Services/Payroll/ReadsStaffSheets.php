<?php

namespace App\Services\Payroll;

use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Cell/sheet readers shared by the Staff Profile import (EmployeeMasterImportController) and the
 * monthly salary import (SalaryMonthlyImportController): header detection, the STAFF DETAILS
 * bio-data sheet, and cell normalizers (Excel date serials, "12008.0" codes, "  10,000 ").
 */
trait ReadsStaffSheets
{
    /** Normalized STAFF DETAILS header -> field key. */
    private const STAFF_HEADER_MAP = [
        'empl_code' => 'emp_code',
        'empl code' => 'emp_code',
        'emp_code' => 'emp_code',
        'emp code' => 'emp_code',
        'name' => 'name',
        'dob' => 'dob',
        'date of birth' => 'dob',
        'categ' => 'categ',
        'category' => 'categ',
        'hs year' => 'hs_year',
        'inter year' => 'inter_year',
        'grad year' => 'grad_year',
        'phone' => 'phone',
        'phone no' => 'phone',
        'mobile' => 'phone',
        'mobile no' => 'phone',
        'join date' => 'join_date',
        'joining date' => 'join_date',
        'address' => 'address',
        'basic salary' => 'basic_salary',
        'remarks' => 'remarks',
        'qualification' => 'remarks',
        'email' => 'email',
        'e-mail' => 'email',
        'email id' => 'email',
        'gmail' => 'email',
        'gmail id' => 'email',
        'status' => 'status',
    ];

    /** Bio field key -> label stored in custom_field_values (shown on the profile "View"). */
    private const BIO_FIELD_LABELS = [
        'dob' => 'Date of Birth',
        'categ' => 'Category',
        'hs_year' => 'HS Year',
        'inter_year' => 'Inter Year',
        'grad_year' => 'Grad Year',
        'join_date' => 'Join Date',
        'address' => 'Address',
        'remarks' => 'Remarks',
    ];

    /**
     * Parses STAFF DETAILS bio-data. Stops at a repeated header row — the start of the
     * mismatched second block further down the sheet. Rows without code+name are skipped.
     *
     * @return list<array<string, mixed>>
     */
    private function parseBioRows(Worksheet $sheet): array
    {
        [$headerIndex, $header, $dataRows] = $this->locateHeader($sheet, self::STAFF_HEADER_MAP, 3);
        if ($header === []) {
            return [];
        }

        $rows = [];
        foreach ($dataRows as $i => $rowRaw) {
            $normalized = array_map(fn ($c) => $this->normalizeHeader($c), array_slice($rowRaw, 0, count($header)));
            if ($normalized === $header) {
                break;
            }

            $row = $this->mapRow($header, $rowRaw, self::STAFF_HEADER_MAP);
            $code = $this->cellString($row['emp_code'] ?? null);
            $name = $this->cellString($row['name'] ?? null);
            if ($code === '' || $name === '') {
                continue;
            }

            $rows[] = [
                'row_number' => $headerIndex + 2 + $i,
                'code' => $code,
                'name' => $name,
                'dob' => $this->dateValue($row['dob'] ?? null),
                'categ' => $this->cellString($row['categ'] ?? null),
                'hs_year' => $this->yearValue($row['hs_year'] ?? null),
                'inter_year' => $this->yearValue($row['inter_year'] ?? null),
                'grad_year' => $this->yearValue($row['grad_year'] ?? null),
                'phone' => $this->phone($row['phone'] ?? null),
                'email' => $this->email($row['email'] ?? null),
                'join_date' => $this->dateValue($row['join_date'] ?? null),
                'address' => $this->cellString($row['address'] ?? null),
                'basic_salary' => $this->money($row['basic_salary'] ?? null),
                'remarks' => $this->cellString($row['remarks'] ?? null),
                'status' => $this->cellString($row['status'] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * Finds the header row (the first row within the top 10 that has a NAME column plus at least
     * one other known column), falling back to $defaultRow.
     *
     * @param  array<string, string>  $map
     * @return array{0:int, 1:list<string>, 2:list<list<mixed>>}  [0-based header index, normalized header, data rows]
     */
    private function locateHeader(Worksheet $sheet, array $map, int $defaultRow): array
    {
        $all = $sheet->toArray(null, false, false, false);
        $headerIndex = null;
        foreach (array_slice($all, 0, 10, true) as $idx => $cells) {
            $normalized = array_map(fn ($c) => $this->normalizeHeader($c), $cells);
            $known = array_filter($normalized, fn ($h) => isset($map[$h]));
            if (in_array('name', $normalized, true) && count($known) >= 2) {
                $headerIndex = $idx;
                break;
            }
        }
        if ($headerIndex === null) {
            $headerIndex = $defaultRow - 1;
            if (! isset($all[$headerIndex])) {
                return [$headerIndex, [], []];
            }
        }

        $header = array_map(fn ($c) => $this->normalizeHeader($c), $all[$headerIndex]);

        return [$headerIndex, $header, array_slice($all, $headerIndex + 1)];
    }

    /**
     * @param  list<string>  $header
     * @param  list<mixed>  $rowRaw
     * @param  array<string, string>  $map
     * @return array<string, mixed>
     */
    private function mapRow(array $header, array $rowRaw, array $map): array
    {
        $row = [];
        foreach ($header as $idx => $col) {
            if (isset($map[$col]) && ! array_key_exists($map[$col], $row)) {
                $row[$map[$col]] = $rowRaw[$idx] ?? null;
            }
        }

        return $row;
    }

    private function normalizeHeader($value): string
    {
        $clean = strtolower(str_replace('.', '', (string) $value));

        return preg_replace('/\s+/', ' ', trim($clean)) ?? '';
    }

    /** Cell -> trimmed string; whole-number floats lose their ".0"; inner whitespace collapsed. */
    private function cellString($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            return (string) (int) $value;
        }

        return preg_replace('/\s+/', ' ', trim((string) $value)) ?? '';
    }

    private function money($value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return $value > 0 ? (float) $value : null;
        }
        $clean = preg_replace('/[^0-9.]/', '', (string) $value);

        return is_numeric($clean) && (float) $clean > 0 ? (float) $clean : null;
    }

    private function formatMoney(float $value): string
    {
        return floor($value) === $value ? (string) (int) $value : number_format($value, 2, '.', '');
    }

    private function phone($value): string
    {
        return preg_replace('/\s+/', '', $this->cellString($value)) ?? '';
    }

    private function email($value): string
    {
        $email = mb_strtolower($this->cellString($value));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    /** "0" in HS/INTER/GRAD YEAR means not passed — keep blank. */
    private function yearValue($value): string
    {
        $year = $this->cellString($value);

        return $year === '0' ? '' : $year;
    }

    /** Excel date serials (26635) -> "02-12-1972"; text dates are kept as written. */
    private function dateValue($value): string
    {
        if (is_int($value) || is_float($value)) {
            if ($value > 0 && $value < 100000) {
                return ExcelDate::excelToDateTimeObject($value)->format('d-m-Y');
            }

            return '';
        }

        return $this->cellString($value);
    }
}
