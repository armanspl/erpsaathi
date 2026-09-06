<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\ImportExportLog;
use App\Models\Staff;
use App\Models\Teacher;
use App\Services\SpreadsheetImportReader;
use App\Support\PeopleCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Imports SALARY_DETAILS_2026-27.xlsx-shaped workbooks — a pure employee-master file (no
 * attendance/pay math, unlike SalaryMonthlyImportController). It creates/updates Teacher,
 * Staff and Driver records from two sheets:
 *
 *  - `SALARY DETAILS` (header row 1: EMP_CODE/NAME/POST/SUBJECT/SECTION/STATUS/BASIC SALARY AT
 *    JOINING/BASIC SALARY PRESENT) — the driver of every create/update decision. POST resolves
 *    which table (Teacher/Staff/Driver) via DESIGNATION_TYPE_MAP; rows grouped under grade
 *    labels (GRADE I/III/IV/TRANSPORT, ...) are skipped by detecting a blank NAME+POST, never
 *    by hardcoding the grade list (a grade can be missing, e.g. no GRADE II in this file).
 *  - `STAFF DETAILS` (header row 3) — bio-data only (DOB/category/exam years/phone/join
 *    date/address/remarks/basic salary), merged in by EMPL_CODE. This sheet has a second block
 *    further down that reuses the same header row but doesn't match it (designation text
 *    pasted into bio-data columns) — reading stops the moment a repeated header row is seen.
 *
 * The critical piece is matching: this file's EMP_CODE values do NOT match the codes used in
 * earlier files for the same person (a digit is inserted, e.g. old 12008 vs new 121008 for the
 * same teacher) — so a code-only match would duplicate every existing employee. Matching goes:
 * exact EMP_CODE across all three tables first, then falls back to an exact case-insensitive
 * NAME match *within the POST-resolved table only*, and only creates a new record when neither
 * matches. Any ambiguity (duplicate EMP_CODE within the sheet, EMP_CODE matching more than one
 * employee, or a name matching more than one employee) is never guessed — it fails into the
 * review queue, same pattern as SalaryMonthlyImportController.
 */
class EmployeeMasterImportController extends Controller
{
    private const SALARY_SHEET_NAME = 'salary details';

    private const STAFF_SHEET_NAME = 'staff details';

    private const STAFF_HEADER_ROW = 3;

    /** Lowercased SALARY DETAILS header -> internal field key. */
    private const SALARY_HEADER_MAP = [
        'emp_code' => 'emp_code',
        'name' => 'name',
        'post' => 'post',
        'subject' => 'subject',
        'section' => 'section',
        'status' => 'status',
        'basic salary at joining' => 'basic_salary_joining',
        'basic salary present' => 'basic_salary_present',
    ];

    /** Lowercased STAFF DETAILS header -> internal field key. */
    private const STAFF_HEADER_MAP = [
        'empl_code' => 'empl_code',
        'name' => 'name',
        'dob' => 'dob',
        'categ' => 'categ',
        'hs year' => 'hs_year',
        'inter year' => 'inter_year',
        'grad year' => 'grad_year',
        'phone' => 'phone',
        'join date' => 'join_date',
        'address' => 'address',
        'basic salary' => 'basic_salary',
        'remarks' => 'remarks',
    ];

    /** Bio field key (from STAFF_HEADER_MAP) -> label used in custom_field_values. */
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

    /** The three tables an employee can live in. */
    private const EMPLOYEE_TYPES = ['teacher', 'staff', 'driver'];

    /** POST (normalized: uppercased, periods stripped, whitespace collapsed) -> table. */
    private const DESIGNATION_TYPE_MAP = [
        'ASST TEACHER' => 'teacher',
        'COMP TEACHER' => 'teacher',
        'COMPUTER TEACHER' => 'teacher',
        'ACCOUNTANT' => 'staff',
        'OFFICE ASST' => 'staff',
        'COMP OPERATOR' => 'staff',
        'COMPUTER OPERATOR' => 'staff',
        'ADMINISTRATOR' => 'staff',
        'ADMIN' => 'staff',
        'BOOA' => 'staff',
        'GUARD' => 'staff',
        'DRIVER' => 'driver',
    ];

    /** Dry-run: parses the file and reports counts, without writing anything. */
    public function preview(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:20480']);

        @set_time_limit(300);

        [$salaryRows, $bioLookup, $salarySheetFound, $staffSheetFound] = $this->readWorkbook($request->file('file'));

        if (! $salarySheetFound) {
            return response()->json(['message' => 'A "SALARY DETAILS" sheet was not found in this file.'], 422);
        }

        $employeeLookup = $this->loadEmployeeLookup();
        $nameLookup = $this->loadNameLookup();
        $codeCounts = $this->countCodes($salaryRows);

        $createCount = 0;
        $updateCount = 0;
        $failedCount = 0;
        $failedRows = [];

        foreach ($salaryRows as $row) {
            $outcome = $this->classifyRow($row, $codeCounts, $employeeLookup, $nameLookup);
            if ($outcome['outcome'] === 'failed') {
                $failedCount++;
                $failedRows[] = ['row_number' => $row['row_number'], 'empl_code' => $row['empl_code'], 'name' => $row['name'], 'reason' => $outcome['reason']];
            } elseif ($outcome['outcome'] === 'create') {
                $createCount++;
            } else {
                $updateCount++;
            }
        }

        return response()->json([
            'salary_sheet_found' => $salarySheetFound,
            'staff_sheet_found' => $staffSheetFound,
            'bio_row_count' => count($bioLookup),
            'total_rows' => count($salaryRows),
            'create_count' => $createCount,
            'update_count' => $updateCount,
            'failed_count' => $failedCount,
            'failed_rows' => array_slice($failedRows, 0, 200),
        ]);
    }

    /** Actually writes the Teacher/Staff/Driver records. */
    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:20480']);

        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();

        [$salaryRows, $bioLookup, $salarySheetFound] = $this->readWorkbook($file);

        if (! $salarySheetFound) {
            return response()->json(['message' => 'A "SALARY DETAILS" sheet was not found in this file.'], 422);
        }

        $userId = Auth::guard('erp')->id();
        $employeeLookup = $this->loadEmployeeLookup();
        $nameLookup = $this->loadNameLookup();
        $codeCounts = $this->countCodes($salaryRows);

        $now = now()->toDateTimeString();
        $log = ImportExportLog::create([
            'direction' => 'Import',
            'entity' => 'employee-master',
            'filename' => $originalName,
            'performed_by_id' => $userId,
        ]);

        $total = 0;
        $created = 0;
        $updated = 0;
        $failed = 0;
        $rowLogRows = [];
        $failedRowRows = [];
        $failedRowsResponse = [];
        $newEmployees = [];

        foreach ($salaryRows as $row) {
            $total++;
            $rowNumber = $row['row_number'];
            $emplCode = $row['empl_code'];
            $name = $row['name'];

            $outcome = $this->classifyRow($row, $codeCounts, $employeeLookup, $nameLookup);

            if ($outcome['outcome'] === 'failed') {
                $failed++;
                $this->recordFailure($log->id, $rowNumber, $emplCode, $row, $outcome['reason'], $now, $rowLogRows, $failedRowRows, $failedRowsResponse);

                continue;
            }

            $type = $outcome['type'];
            $bio = $bioLookup[$emplCode] ?? null;
            $status = mb_strtoupper($row['status_raw']) === 'LEFT' ? 'inactive' : 'active';
            $salary = $row['basic_salary_present'] ?? $row['basic_salary_joining'] ?? null;
            if ($salary === null && $bio !== null && $bio['basic_salary'] !== null) {
                $salary = $bio['basic_salary'];
            }
            $phone = $bio['phone'] ?? '';

            $customFields = [];
            if ($type === 'teacher') {
                if ($row['subject'] !== '') {
                    $customFields[] = ['label' => 'Subject', 'value' => $row['subject']];
                }
                if ($row['section'] !== '') {
                    $customFields[] = ['label' => 'Section', 'value' => $row['section']];
                }
            }
            if ($bio !== null) {
                foreach (self::BIO_FIELD_LABELS as $key => $label) {
                    if (($bio[$key] ?? '') !== '') {
                        $customFields[] = ['label' => $label, 'value' => $bio[$key]];
                    }
                }
            }

            $modelClass = match ($type) {
                'teacher' => Teacher::class,
                'staff' => Staff::class,
                default => Driver::class,
            };

            if ($outcome['outcome'] === 'create') {
                $payload = ['employee_id' => $emplCode, 'name' => $name, 'status' => $status];
                if ($salary !== null) {
                    $payload['salary'] = $salary;
                }
                if ($phone !== '') {
                    $payload['phone'] = $phone;
                }
                if ($customFields !== []) {
                    $payload['custom_field_values'] = $customFields;
                }

                $model = $modelClass::create($payload);
                $employeeLookup[$type][$emplCode] = $model->id;
                $newEmployees[] = [
                    'type' => $type,
                    'id' => $model->id,
                    'employee_id' => $emplCode,
                    'name' => $name,
                    'incomplete' => $bio === null,
                ];
                $action = 'created';
                $created++;
            } else {
                $model = $modelClass::find($outcome['employee_id']);
                if (! $model) {
                    $failed++;
                    $this->recordFailure($log->id, $rowNumber, $emplCode, $row, 'Matched employee record could not be reloaded — try again.', $now, $rowLogRows, $failedRowRows, $failedRowsResponse);

                    continue;
                }

                $updatePayload = ['employee_id' => $emplCode, 'status' => $status];
                if ($salary !== null) {
                    $updatePayload['salary'] = $salary;
                }
                if ($phone !== '') {
                    $updatePayload['phone'] = $phone;
                }
                if ($customFields !== []) {
                    $existing = is_array($model->custom_field_values) ? $model->custom_field_values : [];
                    $updatePayload['custom_field_values'] = $this->mergeCustomFields($existing, $customFields);
                }

                $model->update($updatePayload);
                $employeeLookup[$type][$emplCode] = $model->id;
                $action = 'updated';
                $updated++;
            }

            $rowLogRows[] = [
                'import_export_log_id' => $log->id,
                'row_number' => $rowNumber,
                'status' => 'Success',
                'identifier' => $emplCode,
                'summary' => json_encode(['name' => $name, 'employee_type' => $type, 'action' => $action], JSON_THROW_ON_ERROR),
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
            'success_count' => $created + $updated,
            'failed_count' => $failed,
        ]);

        if ($created + $updated > 0) {
            PeopleCache::forget();
        }

        return response()->json([
            'log' => $log->fresh(),
            'created_count' => $created,
            'updated_count' => $updated,
            'failed_count' => $failed,
            'failed_rows' => array_slice($failedRowsResponse, 0, 200),
            'new_employees' => $newEmployees,
        ], 201);
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: array<string, array<string, mixed>>, 2: bool, 3: bool}
     *         [salaryRows, bioLookup keyed by EMP_CODE, salarySheetFound, staffSheetFound]
     */
    private function readWorkbook($file): array
    {
        $path = $file->getRealPath();
        $names = SpreadsheetImportReader::listSheetNames($path);

        $salaryName = null;
        $staffName = null;
        foreach ($names as $n) {
            $lower = strtolower(trim($n));
            if ($lower === self::SALARY_SHEET_NAME) {
                $salaryName = $n;
            } elseif ($lower === self::STAFF_SHEET_NAME) {
                $staffName = $n;
            }
        }

        if ($salaryName === null) {
            return [[], [], false, $staffName !== null];
        }

        $wanted = array_values(array_filter([$salaryName, $staffName]));
        $spreadsheet = SpreadsheetImportReader::loadSheetsOnly($path, $wanted);

        $salarySheet = $spreadsheet->getSheetByName($salaryName);
        $parsed = SpreadsheetImportReader::fromWorksheet($salarySheet, 1);
        $salaryRows = $this->parseSalaryRows($parsed['header'], $parsed['rows']);

        $bioLookup = [];
        if ($staffName !== null) {
            $staffSheet = $spreadsheet->getSheetByName($staffName);
            $bioLookup = $this->loadBioLookup($staffSheet);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [$salaryRows, $bioLookup, true, $staffName !== null];
    }

    /**
     * Parses SALARY DETAILS data rows. Rows with a blank NAME or POST are skipped silently —
     * that covers grade-label rows (GRADE I/III/IV/TRANSPORT, ...) whose only populated cell is
     * column A, plus any blank spacer rows. The grade labels themselves are never enumerated.
     *
     * @param  list<string>  $header
     * @param  list<list<mixed>>  $dataRows
     * @return list<array{row_number:int, empl_code:string, name:string, post:string, subject:string, section:string, status_raw:string, basic_salary_joining:?float, basic_salary_present:?float}>
     */
    private function parseSalaryRows(array $header, array $dataRows): array
    {
        $parsed = [];

        foreach ($dataRows as $i => $rowRaw) {
            $rowNumber = 2 + $i;

            $row = [];
            foreach ($header as $idx => $col) {
                if (isset(self::SALARY_HEADER_MAP[$col])) {
                    $row[self::SALARY_HEADER_MAP[$col]] = $rowRaw[$idx] ?? null;
                }
            }

            $name = trim((string) ($row['name'] ?? ''));
            $post = trim((string) ($row['post'] ?? ''));
            if ($name === '' || $post === '') {
                continue;
            }

            $emplCodeRaw = trim((string) ($row['emp_code'] ?? ''));
            $emplCode = $emplCodeRaw !== '' ? $this->normalizeCode($emplCodeRaw) : '';

            $parsed[] = [
                'row_number' => $rowNumber,
                'empl_code' => $emplCode,
                'name' => $name,
                'post' => $post,
                'subject' => trim((string) ($row['subject'] ?? '')),
                'section' => trim((string) ($row['section'] ?? '')),
                'status_raw' => trim((string) ($row['status'] ?? '')),
                'basic_salary_joining' => is_numeric($row['basic_salary_joining'] ?? null) ? (float) $row['basic_salary_joining'] : null,
                'basic_salary_present' => is_numeric($row['basic_salary_present'] ?? null) ? (float) $row['basic_salary_present'] : null,
            ];
        }

        return $parsed;
    }

    /**
     * Parses STAFF DETAILS bio-data. Stops the instant a row repeats the header row verbatim —
     * that marks the start of the mismatched second block further down the sheet, which is
     * never read. Rows with a blank EMPL_CODE or NAME are skipped silently (placeholder rows).
     *
     * @return array<string, array{dob:string, categ:string, hs_year:string, inter_year:string, grad_year:string, phone:string, join_date:string, address:string, basic_salary:?float, remarks:string}>
     */
    private function loadBioLookup(Worksheet $sheet): array
    {
        $parsed = SpreadsheetImportReader::fromWorksheet($sheet, self::STAFF_HEADER_ROW);
        $header = $parsed['header'];
        $headerLen = count($header);

        $lookup = [];
        foreach ($parsed['rows'] as $rowRaw) {
            $normalized = array_map(fn ($c) => strtolower(trim((string) $c)), array_slice($rowRaw, 0, $headerLen));
            if ($normalized === $header) {
                // Second, mismatched header block starts here — stop reading entirely.
                break;
            }

            $row = [];
            foreach ($header as $idx => $col) {
                if (isset(self::STAFF_HEADER_MAP[$col])) {
                    $row[self::STAFF_HEADER_MAP[$col]] = $rowRaw[$idx] ?? null;
                }
            }

            $codeRaw = trim((string) ($row['empl_code'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($codeRaw === '' || $name === '') {
                continue;
            }

            $code = $this->normalizeCode($codeRaw);
            $basicRaw = $row['basic_salary'] ?? null;

            $lookup[$code] = [
                'dob' => trim((string) ($row['dob'] ?? '')),
                'categ' => trim((string) ($row['categ'] ?? '')),
                'hs_year' => trim((string) ($row['hs_year'] ?? '')),
                'inter_year' => trim((string) ($row['inter_year'] ?? '')),
                'grad_year' => trim((string) ($row['grad_year'] ?? '')),
                'phone' => trim((string) ($row['phone'] ?? '')),
                'join_date' => trim((string) ($row['join_date'] ?? '')),
                'address' => trim((string) ($row['address'] ?? '')),
                'basic_salary' => is_numeric($basicRaw) ? (float) $basicRaw : null,
                'remarks' => trim((string) ($row['remarks'] ?? '')),
            ];
        }

        return $lookup;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function countCodes(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            if ($row['empl_code'] === '') {
                continue;
            }
            $counts[$row['empl_code']] = ($counts[$row['empl_code']] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Resolves one SALARY DETAILS row to a create/update/failed outcome, without writing
     * anything — shared between preview() (stats only) and import() (stats + the actual write).
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $codeCounts
     * @param  array<string, array<string, int>>  $employeeLookup
     * @param  array<string, array<string, list<int>>>  $nameLookup
     * @return array{outcome:string, reason?:string, type?:string, employee_type?:string, employee_id?:int}
     */
    private function classifyRow(array $row, array $codeCounts, array $employeeLookup, array $nameLookup): array
    {
        $postNormalized = $this->normalizePost($row['post']);
        $type = self::DESIGNATION_TYPE_MAP[$postNormalized] ?? null;
        if ($type === null) {
            return ['outcome' => 'failed', 'reason' => "Unrecognized designation \"{$row['post']}\" — cannot determine Teacher/Staff/Driver, please add manually."];
        }

        $emplCode = $row['empl_code'];
        if ($emplCode === '') {
            return ['outcome' => 'failed', 'reason' => 'Missing EMP_CODE.'];
        }

        if (($codeCounts[$emplCode] ?? 0) > 1) {
            return ['outcome' => 'failed', 'reason' => "EMP_CODE {$emplCode} is used by more than one row in this sheet — resolve the duplicate and re-import."];
        }

        $codeMatches = [];
        foreach (self::EMPLOYEE_TYPES as $t) {
            if (isset($employeeLookup[$t][$emplCode])) {
                $codeMatches[] = [$t, $employeeLookup[$t][$emplCode]];
            }
        }

        if (count($codeMatches) > 1) {
            $types = implode(', ', array_column($codeMatches, 0));

            return ['outcome' => 'failed', 'reason' => "EMP_CODE {$emplCode} matches more than one existing employee ({$types}) — resolve manually."];
        }

        if (count($codeMatches) === 1) {
            [$existingType, $existingId] = $codeMatches[0];

            return ['outcome' => 'update', 'type' => $type, 'employee_type' => $existingType, 'employee_id' => $existingId];
        }

        $nameKey = mb_strtolower($row['name']);
        $nameMatches = $nameLookup[$type][$nameKey] ?? [];

        if (count($nameMatches) > 1) {
            return ['outcome' => 'failed', 'reason' => "\"{$row['name']}\" matches more than one existing {$type} by name — resolve manually."];
        }

        if (count($nameMatches) === 1) {
            return ['outcome' => 'update', 'type' => $type, 'employee_type' => $type, 'employee_id' => $nameMatches[0]];
        }

        return ['outcome' => 'create', 'type' => $type];
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

    /** @return array<string, array<string, list<int>>> employee_type -> [lowercased name => [model ids]] */
    private function loadNameLookup(): array
    {
        $lookup = ['teacher' => [], 'staff' => [], 'driver' => []];
        $classes = ['teacher' => Teacher::class, 'staff' => Staff::class, 'driver' => Driver::class];

        foreach ($classes as $type => $class) {
            foreach ($class::query()->select('id', 'name')->get() as $record) {
                $key = mb_strtolower(trim($record->name));
                $lookup[$type][$key][] = $record->id;
            }
        }

        return $lookup;
    }

    /** @param  list<array{label:string,value:string}>  $updates */
    private function mergeCustomFields(array $existing, array $updates): array
    {
        foreach ($updates as $update) {
            $found = false;
            foreach ($existing as &$field) {
                if (mb_strtolower(trim((string) ($field['label'] ?? ''))) === mb_strtolower($update['label'])) {
                    $field['value'] = $update['value'];
                    $found = true;
                    break;
                }
            }
            unset($field);
            if (! $found) {
                $existing[] = $update;
            }
        }

        return $existing;
    }

    /** Excel gives whole numbers as floats (e.g. "121008.0") — normalize to a plain integer string. */
    private function normalizeCode(string $code): string
    {
        return is_numeric($code) && str_contains($code, '.')
            ? (string) (int) round((float) $code)
            : $code;
    }

    /** Uppercase, strip periods, collapse whitespace — "Asst. Teacher" / "ASST  TEACHER" -> "ASST TEACHER". */
    private function normalizePost(string $post): string
    {
        $clean = str_replace('.', '', $post);
        $clean = preg_replace('/\s+/', ' ', trim($clean)) ?? '';

        return mb_strtoupper($clean);
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
        $failedRowsResponse[] = ['row_number' => $rowNumber, 'empl_code' => $emplCode, 'name' => $row['name'] ?? '', 'error_message' => $message];
    }
}
