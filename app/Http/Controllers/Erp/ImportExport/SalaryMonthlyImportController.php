<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Driver;
use App\Models\ImportExportLog;
use App\Models\SalarySlip;
use App\Models\Staff;
use App\Models\Teacher;
use App\Services\SpreadsheetImportReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Imports the whole salary workbook in one go. It's renamed/shaped differently every month
 * (JULY, AUGUST, "JULY-26-2" for a second run of the same month, plus unrelated sheets like
 * STAFF DETAILS / STAFF DETAIL SALARY) — so this never assumes a fixed sheet name. Two steps:
 *
 *  - preview(): scans every sheet, keeps only ones shaped like a monthly salary sheet (row 5
 *    header has EMPL_CODE/PRESENT/CL), guesses each one's period from its row 4 title text
 *    ("SALARY PAYMENT DETAILS JULY-2026"), and reports employee/new-employee counts so the
 *    admin can review (and correct any mis-detected period) before committing.
 *  - import(): given the confirmed {sheet name, period} list from that preview, actually
 *    writes the slips.
 *
 * Every number is recomputed from Present/CL/Basic Salary/Days in Month/Advance — the source
 * file's own "SALARY CALCULATE"/"This Month Salary"/"G.SALARY" cells are read but never
 * trusted (the workbook's "SALARY CALCULATE" column is a broken legacy formula, #REF!).
 *
 * An EMPL_CODE that matches no existing Teacher/Staff/Driver is auto-created rather than
 * failed — but NOT by guessing from block position: the monthly sheet's visual blocks don't
 * reliably correspond to Teacher/Staff/Driver (e.g. a block can mix Guard/Accountant/Booa/
 * Administrator/a Teacher together, and which block holds Drivers varies by sheet). Instead,
 * the `STAFF DETAIL SALARY` sheet's DESIGNATION column (A=EMPL_CODE, B=NAME, C=DESIGNATION,
 * no header row) is the actual source of truth and is read internally for this lookup only —
 * it is never imported as its own data. An EMPL_CODE missing from *both* the matched-employee
 * tables and that designation lookup fails into the review queue instead of being guessed.
 * Auto-created records only have EMPL_CODE/NAME/Basic Salary (that's all the monthly sheet
 * has) — every such record is surfaced in the response as `new_employees` so the school can
 * complete the profile from the People page.
 */
class SalaryMonthlyImportController extends Controller
{
    /** Lowercased source header -> internal field key. Anything not listed here (S.NO, TOTAL
     *  DAYS, PER DAY, SALARY CALCULATE, THIS MONTH SALARY, G.SALARY, SIGNATURE) is read but
     *  discarded — those are either display-only or values this importer always recomputes.
     */
    private const HEADER_MAP = [
        'empl_code' => 'empl_code',
        'name' => 'name',
        'basic salary' => 'basic_salary',
        'days in month' => 'days_in_month',
        'absent' => 'absent',
        'present' => 'present',
        'cl' => 'cl',
        'adv' => 'advance',
    ];

    /** A sheet must carry at least these headers to be treated as a monthly salary sheet. */
    private const REQUIRED_HEADERS = ['empl_code', 'present', 'cl'];

    /** The three tables an employee can live in. */
    private const EMPLOYEE_TYPES = ['teacher', 'staff', 'driver'];

    /** STAFF DETAIL SALARY sheet name, matched case-insensitively. */
    private const DESIGNATION_SHEET_NAME = 'staff detail salary';

    /** DESIGNATION (from STAFF DETAIL SALARY, column C) -> which table to auto-create in. */
    private const DESIGNATION_TYPE_MAP = [
        'ASST TEACHER' => 'teacher',
        'COMP TEACHER' => 'teacher',
        'BOOA' => 'staff',
        'OFFICE ASST' => 'staff',
        'GUARD' => 'staff',
        'ACCOUNTANT' => 'staff',
        'ADMINISTRATOR' => 'staff',
        'COMP OPERATOR' => 'staff',
        'DRIVER' => 'driver',
    ];

    private const MONTH_NAMES = [
        'JAN' => '01', 'JANUARY' => '01',
        'FEB' => '02', 'FEBRUARY' => '02',
        'MAR' => '03', 'MARCH' => '03',
        'APR' => '04', 'APRIL' => '04',
        'MAY' => '05',
        'JUN' => '06', 'JUNE' => '06',
        'JUL' => '07', 'JULY' => '07',
        'AUG' => '08', 'AUGUST' => '08',
        'SEP' => '09', 'SEPT' => '09', 'SEPTEMBER' => '09',
        'OCT' => '10', 'OCTOBER' => '10',
        'NOV' => '11', 'NOVEMBER' => '11',
        'DEC' => '12', 'DECEMBER' => '12',
    ];

    /** Scans every sheet in the uploaded workbook and reports which look like monthly salary sheets. */
    public function preview(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:20480']);

        @set_time_limit(300);

        $file = $request->file('file');
        $path = $file->getRealPath();
        $names = SpreadsheetImportReader::listSheetNames($path);
        $spreadsheet = SpreadsheetImportReader::loadSheetsOnly($path, $names);

        $employeeLookup = $this->loadEmployeeLookup();
        $designationLookup = $this->loadDesignationLookup($this->findDesignationSheet($spreadsheet, $names));

        $sheets = [];
        foreach ($names as $name) {
            $sheet = $spreadsheet->getSheetByName($name);
            if (! $sheet) {
                continue;
            }

            $parsed = SpreadsheetImportReader::fromWorksheet($sheet, 5);
            if (! $this->isSalarySheet($parsed['header'])) {
                $sheets[] = ['name' => $name, 'detected' => false];

                continue;
            }

            $rawRows = $sheet->toArray(null, false, false, false);
            $titleText = $this->extractTitleText($rawRows[3] ?? []);
            $period = $this->detectPeriod($titleText);

            $dataRows = $this->parseDataRows($parsed['header'], $parsed['rows']);
            $seen = [];
            $newCount = 0;
            $existingCount = 0;
            $unresolvedCount = 0;
            foreach ($dataRows as $row) {
                if (isset($seen[$row['empl_code']])) {
                    continue;
                }
                $seen[$row['empl_code']] = true;
                $found = false;
                foreach (self::EMPLOYEE_TYPES as $type) {
                    if (isset($employeeLookup[$type][$row['empl_code']])) {
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    $existingCount++;

                    continue;
                }
                $newCount++;
                if (($designationLookup[$row['empl_code']]['type'] ?? null) === null) {
                    $unresolvedCount++;
                }
            }

            $sheets[] = [
                'name' => $name,
                'detected' => true,
                'period' => $period,
                'period_label' => $period ? $this->periodLabel($period) : null,
                'employee_count' => $existingCount + $newCount,
                'new_count' => $newCount,
                'unresolved_count' => $unresolvedCount,
            ];
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()->json(['sheets' => $sheets]);
    }

    /** Imports the confirmed {name, period} sheet list (JSON-encoded in the `sheets` field). */
    public function import(Request $request)
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480',
            'sheets' => 'required|string',
        ]);

        $sheetsInput = json_decode($data['sheets'], true);
        if (! is_array($sheetsInput) || $sheetsInput === []) {
            return response()->json(['message' => 'No sheets were selected to import.'], 422);
        }

        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $file = $request->file('file');
        $path = $file->getRealPath();
        $originalName = $file->getClientOriginalName();

        $allNames = SpreadsheetImportReader::listSheetNames($path);
        $wantedNames = array_values(array_filter(array_map(fn ($e) => (string) ($e['name'] ?? ''), $sheetsInput)));

        $designationSheetName = null;
        foreach ($allNames as $n) {
            if (strtolower(trim($n)) === self::DESIGNATION_SHEET_NAME) {
                $designationSheetName = $n;
                break;
            }
        }
        if ($designationSheetName !== null && ! in_array($designationSheetName, $wantedNames, true)) {
            $wantedNames[] = $designationSheetName;
        }

        $spreadsheet = SpreadsheetImportReader::loadSheetsOnly($path, $wantedNames);

        $userId = Auth::guard('erp')->id();
        $employeeLookup = $this->loadEmployeeLookup();
        $designationLookup = $this->loadDesignationLookup($designationSheetName !== null ? $spreadsheet->getSheetByName($designationSheetName) : null);

        $results = [];
        $newEmployeesAll = [];

        foreach ($sheetsInput as $entry) {
            $sheetName = trim((string) ($entry['name'] ?? ''));
            $period = trim((string) ($entry['period'] ?? ''));

            if ($sheetName === '' || ! preg_match('/^\d{4}-\d{2}$/', $period)) {
                $results[] = ['sheet' => $sheetName, 'period' => $period, 'error' => 'Missing or invalid period.'];

                continue;
            }
            // The designation sheet itself is a lookup source, never a salary sheet to import.
            if (strtolower($sheetName) === self::DESIGNATION_SHEET_NAME) {
                continue;
            }

            $sheet = $spreadsheet->getSheetByName($sheetName);
            if (! $sheet) {
                $results[] = ['sheet' => $sheetName, 'period' => $period, 'error' => 'Sheet not found in file.'];

                continue;
            }

            $rawRows = $sheet->toArray(null, false, false, false);
            $daysInMonthDefault = $this->extractDaysInMonth($rawRows[3] ?? []);
            $parsed = SpreadsheetImportReader::fromWorksheet($sheet, 5);
            $dataRows = $this->parseDataRows($parsed['header'], $parsed['rows']);

            $result = $this->importSheetRows($sheetName, $originalName, $period, $dataRows, $daysInMonthDefault, $employeeLookup, $designationLookup, $userId);
            $results[] = $result;
            $newEmployeesAll = array_merge($newEmployeesAll, $result['new_employees']);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()->json([
            'results' => $results,
            'new_employees' => $newEmployeesAll,
        ], 201);
    }

    /** @return array<string, array<string, int>> employee_type -> [employee_id => model id] */
    private function loadEmployeeLookup(): array
    {
        return [
            'teacher' => Teacher::query()->pluck('id', 'employee_id')->all(),
            'staff' => Staff::query()->pluck('id', 'employee_id')->all(),
            'driver' => Driver::query()->pluck('id', 'employee_id')->all(),
        ];
    }

    /** Case-insensitive match for the STAFF DETAIL SALARY sheet, if present in this workbook. */
    private function findDesignationSheet(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, array $names): ?\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        foreach ($names as $name) {
            if (strtolower(trim($name)) === self::DESIGNATION_SHEET_NAME) {
                return $spreadsheet->getSheetByName($name);
            }
        }

        return null;
    }

    /**
     * STAFF DETAIL SALARY has no header row — positional columns: A=EMPL_CODE, B=NAME,
     * C=DESIGNATION, D=GRADE, E=STATUS. Only EMPL_CODE and DESIGNATION are needed here.
     *
     * @return array<string, array{type: ?string, designation: string}> EMPL_CODE -> resolved
     *         table type (null when the designation text isn't in DESIGNATION_TYPE_MAP) + the
     *         raw designation text (for the failure message when type is null).
     */
    private function loadDesignationLookup(?\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        if (! $sheet) {
            return [];
        }

        $lookup = [];
        foreach ($sheet->toArray(null, false, false, false) as $rowRaw) {
            $codeRaw = trim((string) ($rowRaw[0] ?? ''));
            if ($codeRaw === '') {
                continue;
            }
            $code = is_numeric($codeRaw) && str_contains($codeRaw, '.')
                ? (string) (int) round((float) $codeRaw)
                : $codeRaw;

            $designation = mb_strtoupper(trim((string) ($rowRaw[2] ?? '')));
            if ($designation === '') {
                continue;
            }

            $lookup[$code] = [
                'type' => self::DESIGNATION_TYPE_MAP[$designation] ?? null,
                'designation' => $designation,
            ];
        }

        return $lookup;
    }

    /** @param  list<string>  $header */
    private function isSalarySheet(array $header): bool
    {
        foreach (self::REQUIRED_HEADERS as $needed) {
            if (! in_array($needed, $header, true)) {
                return false;
            }
        }

        return true;
    }

    /** First non-numeric, non-blank cell in row 4 — the title text ("SALARY PAYMENT DETAILS JULY-2026"). */
    private function extractTitleText(array $titleRow): string
    {
        foreach ($titleRow as $cell) {
            if ($cell === null || trim((string) $cell) === '' || is_numeric($cell)) {
                continue;
            }

            return trim((string) $cell);
        }

        return '';
    }

    /** Last numeric cell in row 4 — Days in Month. */
    private function extractDaysInMonth(array $titleRow): ?int
    {
        $days = null;
        foreach ($titleRow as $cell) {
            if ($cell !== null && is_numeric($cell)) {
                $days = (int) round((float) $cell);
            }
        }

        return $days;
    }

    /** Finds a 4-digit year token and treats the word immediately before it as the month name. */
    private function detectPeriod(string $title): ?string
    {
        $tokens = preg_split('/[\s\-\/]+/', mb_strtoupper(trim($title))) ?: [];
        foreach ($tokens as $i => $token) {
            if ($i > 0 && preg_match('/^(19|20)\d{2}$/', $token) && isset(self::MONTH_NAMES[$tokens[$i - 1]])) {
                return $token.'-'.self::MONTH_NAMES[$tokens[$i - 1]];
            }
        }

        return null;
    }

    private function periodLabel(string $period): string
    {
        $months = ['01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April', '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August', '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'];
        [$year, $month] = array_pad(explode('-', $period), 2, '');

        return ($months[$month] ?? $month).' '.$year;
    }

    /**
     * Parses row 6+ into structured values. Rows with a blank EMPL_CODE are never returned as
     * data (spacer or subtotal rows), matching "skip silently, don't count". Rows with an
     * EMPL_CODE but no NAME and no BASIC SALARY are also skipped — those are leftover
     * placeholder rows for future employees, not real data, and must not be auto-created as
     * blank "Employee {code}" profiles.
     *
     * @param  list<string>  $header
     * @param  list<list<mixed>>  $dataRows
     * @return list<array{row_number:int, empl_code:string, name:string, basic_salary:float, days_in_month:?int, absent:float, present:float, cl:float, advance:float, raw:array}>
     */
    private function parseDataRows(array $header, array $dataRows): array
    {
        $parsed = [];

        foreach ($dataRows as $i => $rowRaw) {
            $rowNumber = 6 + $i;

            $row = [];
            foreach ($header as $idx => $col) {
                if (isset(self::HEADER_MAP[$col])) {
                    $row[self::HEADER_MAP[$col]] = $rowRaw[$idx] ?? null;
                }
            }

            $emplCodeRaw = trim((string) ($row['empl_code'] ?? ''));
            if ($emplCodeRaw === '') {
                continue;
            }

            $nameCell = trim((string) ($row['name'] ?? ''));
            $basicRaw = $row['basic_salary'] ?? null;
            $hasBasic = $basicRaw !== null && trim((string) $basicRaw) !== '' && is_numeric($basicRaw);
            if ($nameCell === '' || ! $hasBasic) {
                // EMPL_CODE-only placeholder row — not a real employee, skip silently.
                continue;
            }

            // Excel gives whole numbers as floats (e.g. 12008.0) — normalize "12008.0" -> "12008"
            // so a numeric EMPL_CODE still matches a plain-integer employee_id. Non-numeric codes
            // (e.g. "TCH-0001") pass through untouched — employee_id is a free-text field.
            $emplCode = is_numeric($emplCodeRaw) && str_contains($emplCodeRaw, '.')
                ? (string) (int) round((float) $emplCodeRaw)
                : $emplCodeRaw;

            $parsed[] = [
                'row_number' => $rowNumber,
                'empl_code' => $emplCode,
                'name' => $nameCell,
                'basic_salary' => (float) $basicRaw,
                'days_in_month' => is_numeric($row['days_in_month'] ?? null) ? (int) $row['days_in_month'] : null,
                'absent' => is_numeric($row['absent'] ?? null) ? (float) $row['absent'] : 0.0,
                'present' => is_numeric($row['present'] ?? null) ? (float) $row['present'] : 0.0,
                'cl' => is_numeric($row['cl'] ?? null) ? (float) $row['cl'] : 0.0,
                'advance' => is_numeric($row['advance'] ?? null) ? (float) $row['advance'] : 0.0,
                'raw' => $row,
            ];
        }

        return $parsed;
    }

    /**
     * Writes one sheet's parsed rows to salary_slips (upsert per employee+period), auto-creating
     * any EMPL_CODE matched nowhere. $employeeLookup is mutated by reference so an employee
     * created while processing an earlier sheet in the same request is recognized (not
     * re-created) if the same EMPL_CODE appears again in a later sheet.
     *
     * @param  list<array<string, mixed>>  $dataRows
     * @param  array<string, array<string, int>>  $employeeLookup
     * @param  array<string, array{type: ?string, designation: string}>  $designationLookup
     * @return array{sheet: string, period: string, log: ImportExportLog, failed_rows: array, new_employees: array}
     */
    private function importSheetRows(string $sheetName, string $originalFilename, string $period, array $dataRows, ?int $daysInMonthDefault, array &$employeeLookup, array $designationLookup, ?int $userId): array
    {
        $now = now()->toDateTimeString();
        $log = ImportExportLog::create([
            'direction' => 'Import',
            'entity' => 'salary-monthly',
            'filename' => "{$originalFilename} — {$sheetName}",
            'performed_by_id' => $userId,
        ]);

        $seenCodes = [];
        $total = 0;
        $success = 0;
        $failed = 0;
        $rowLogRows = [];
        $failedRowRows = [];
        $failedRowsResponse = [];
        $newEmployees = [];

        foreach ($dataRows as $row) {
            $total++;
            $rowNumber = $row['row_number'];
            $emplCode = $row['empl_code'];
            $name = $row['name'];

            if (isset($seenCodes[$emplCode])) {
                $failed++;
                $this->recordFailure($log->id, $rowNumber, $emplCode, $row['raw'], "EMPL_CODE {$emplCode} appears more than once in this sheet — resolve the duplicate and re-import.", $now, $rowLogRows, $failedRowRows, $failedRowsResponse);

                continue;
            }
            $seenCodes[$emplCode] = true;

            $matches = [];
            foreach (self::EMPLOYEE_TYPES as $type) {
                if (isset($employeeLookup[$type][$emplCode])) {
                    $matches[] = [$type, $employeeLookup[$type][$emplCode]];
                }
            }

            if (count($matches) > 1) {
                $failed++;
                $types = implode(', ', array_column($matches, 0));
                $this->recordFailure($log->id, $rowNumber, $emplCode, $row['raw'], "EMPL_CODE {$emplCode} matches more than one employee ({$types}) — resolve manually.", $now, $rowLogRows, $failedRowRows, $failedRowsResponse);

                continue;
            }

            if (count($matches) === 0) {
                // Not found in any table — resolve which table via STAFF DETAIL SALARY's
                // DESIGNATION column (the source of truth). Block position in the monthly
                // sheet is NOT used: blocks mix Guard/Accountant/Booa/Administrator/a Teacher
                // together, and which block holds Drivers varies sheet to sheet.
                $resolved = $designationLookup[$emplCode] ?? null;
                if ($resolved === null) {
                    $failed++;
                    $this->recordFailure($log->id, $rowNumber, $emplCode, $row['raw'], "Employee {$emplCode} not found in designation master — cannot determine Teacher/Staff/Driver, please add manually.", $now, $rowLogRows, $failedRowRows, $failedRowsResponse);

                    continue;
                }
                if ($resolved['type'] === null) {
                    $failed++;
                    $this->recordFailure($log->id, $rowNumber, $emplCode, $row['raw'], "Employee {$emplCode} has an unrecognized designation (\"{$resolved['designation']}\") — cannot determine Teacher/Staff/Driver, please add manually.", $now, $rowLogRows, $failedRowRows, $failedRowsResponse);

                    continue;
                }

                $type = $resolved['type'];
                $model = match ($type) {
                    'teacher' => Teacher::create(['employee_id' => $emplCode, 'name' => $name, 'status' => 'active', 'salary' => $row['basic_salary']]),
                    'staff' => Staff::create(['employee_id' => $emplCode, 'name' => $name, 'status' => 'active']),
                    default => Driver::create(['employee_id' => $emplCode, 'name' => $name, 'status' => 'active']),
                };
                $employeeLookup[$type][$emplCode] = $model->id;
                $employeeType = $type;
                $employeeId = $model->id;
                $newEmployees[] = ['type' => $type, 'id' => $model->id, 'employee_id' => $emplCode, 'name' => $name];
            } else {
                [$employeeType, $employeeId] = $matches[0];
            }

            $daysInMonth = $row['days_in_month'] ?? $daysInMonthDefault;
            if (! $daysInMonth) {
                $failed++;
                $this->recordFailure($log->id, $rowNumber, $emplCode, $row['raw'], 'Days in Month could not be determined for this row.', $now, $rowLogRows, $failedRowRows, $failedRowsResponse);

                continue;
            }

            $basicSalary = $row['basic_salary'];
            $present = $row['present'];
            $absent = $row['absent'];
            $cl = $row['cl'];
            $advance = $row['advance'];

            $totalDays = $present + $cl;
            $perDayRate = round($basicSalary / $daysInMonth, 2);
            $deductions = 0.0; // no manual-fine column in the source sheet — editable afterward on the slip
            $thisMonthSalary = round(($perDayRate * $totalDays) - $deductions, 2);
            $netSalary = round($thisMonthSalary - $advance, 2);

            $payload = [
                'basic_salary' => $basicSalary,
                'earnings' => [['label' => 'Basic', 'amount' => $basicSalary]],
                'deductions' => $deductions,
                'deduction_items' => [],
                'days_in_month' => $daysInMonth,
                'present' => $present,
                'absent' => $absent,
                'cl' => $cl,
                'total_days' => $totalDays,
                'per_day_rate' => $perDayRate,
                'this_month_salary' => $thisMonthSalary,
                'advance' => $advance,
                'net_salary' => $netSalary,
            ];

            $existing = SalarySlip::where('employee_type', $employeeType)
                ->where('employee_id', $employeeId)
                ->where('period', $period)
                ->first();

            if ($existing) {
                $existing->update($payload);
                $action = 'updated';
            } else {
                $payload['employee_type'] = $employeeType;
                $payload['employee_id'] = $employeeId;
                $payload['period'] = $period;
                $payload['slip_no'] = $this->nextSlipNo();
                $payload['status'] = 'Pending';
                $payload['generated_by_id'] = $userId;
                SalarySlip::create($payload);
                $action = 'created';
            }

            $success++;
            $rowLogRows[] = [
                'import_export_log_id' => $log->id,
                'row_number' => $rowNumber,
                'status' => 'Success',
                'identifier' => $emplCode,
                'summary' => json_encode(['name' => $name, 'employee_type' => $employeeType, 'action' => $action], JSON_THROW_ON_ERROR),
                'error_message' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rowLogRows, 300) as $chunk) {
            DB::table('import_row_logs')->insert($chunk);
        }
        foreach (array_chunk($failedRowRows, 300) as $chunk) {
            DB::table('import_failed_rows')->insert($chunk);
        }

        $log->update([
            'total_rows' => $total,
            'success_count' => $success,
            'failed_count' => $failed,
        ]);

        return [
            'sheet' => $sheetName,
            'period' => $period,
            'log' => $log->fresh(),
            'failed_rows' => array_slice($failedRowsResponse, 0, 200),
            'new_employees' => $newEmployees,
        ];
    }

    /** @param  array<string, mixed>  $row */
    private function recordFailure(
        int $logId,
        int $rowNumber,
        string $emplCode,
        array $row,
        string $message,
        string $now,
        array &$rowLogRows,
        array &$failedRowRows,
        array &$failedRowsResponse
    ): void {
        $rowLogRows[] = [
            'import_export_log_id' => $logId,
            'row_number' => $rowNumber,
            'status' => 'Failed',
            'identifier' => $emplCode,
            'summary' => null,
            'error_message' => $message,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $failedRowRows[] = [
            'import_export_log_id' => $logId,
            'row_number' => $rowNumber,
            'row_data' => json_encode($row, JSON_THROW_ON_ERROR),
            'error_message' => mb_substr($message, 0, 255),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $failedRowsResponse[] = ['row_number' => $rowNumber, 'empl_code' => $emplCode, 'error_message' => $message];
    }

    private function nextSlipNo(): string
    {
        $session = AcademicSession::query()->where('is_current', true)->first();
        $sessionDigits = $session ? preg_replace('/\D+/', '', (string) $session->name) : '';
        $token = $sessionDigits !== '' ? $sessionDigits : now()->format('Y');
        $prefix = "SLP-{$token}-";
        $count = SalarySlip::where('slip_no', 'like', $prefix.'%')->count() + 1;

        return sprintf('%s%05d', $prefix, $count);
    }
}
