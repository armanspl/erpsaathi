<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\ImportExportLog;
use App\Models\Staff;
use App\Models\Teacher;
use App\Services\Payroll\EmployeeMatcher;
use App\Services\Payroll\ReadsStaffSheets;
use App\Services\SpreadsheetImportReader;
use App\Support\EmployeeCustomFields;
use App\Support\PeopleCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Staff Profile import — "SALARY DETAILS 2026-27.xlsx"-shaped workbooks (also the file produced by
 * StaffProfileExportController, so an export can be edited and re-imported). Creates/updates
 * Teacher, Staff and Driver profiles; it never touches attendance or monthly pay.
 *
 *  - `SALARY DETAILS` (header row 1: EMP_CODE/NAME/POST/SUBJECT/SECTION/STATUS/BASIC SALARY AT
 *    THE TIME OF JOINING/BASIC SALARY PRESENT, optional PHONE/EMAIL) drives every create/update.
 *    POST resolves the table (Teacher/Staff/Driver). Group label rows (GRADE I/III/IV/TRANSPORT)
 *    only have column A filled — they are remembered as the employee's Grade, never imported.
 *  - `STAFF DETAILS` (header auto-detected, row 3 in the school file) — bio-data (DOB, category,
 *    exam years, phone, join date, address, basic salary, remarks, optional email). A second block
 *    further down repeats the header with unrelated data; reading stops there.
 *  - `STAFF 2026` (any "STAFF <year>" sheet: EMP CODE/NAME/BASIC) — current basic salary.
 *
 * Matching the side sheets to SALARY DETAILS: their codes are written differently for the same
 * person (12008 in STAFF DETAILS vs 121008 in SALARY DETAILS — the 3rd digit is dropped), and
 * the dropped-digit code can collide with someone else (STAFF 2026 "25018 FILZA RIZWAN" vs SALARY
 * DETAILS "252018 SHAHZAD ANSARI"). So a code match only counts when the names are also similar;
 * otherwise an exact, then a similar, name match is used, and only when it is unique.
 *
 * Matching rows to existing employees: exact EMP_CODE across all three tables, then the
 * dropped-digit code / name within the POST-resolved table. Ambiguity is never guessed — the row
 * goes to the review list instead.
 */
class EmployeeMasterImportController extends Controller
{
    use ReadsStaffSheets;

    private const SALARY_SHEET_NAME = 'salary details';

    private const STAFF_SHEET_NAME = 'staff details';

    /** Normalized SALARY DETAILS header -> field key. */
    private const SALARY_HEADER_MAP = [
        'emp_code' => 'emp_code',
        'emp code' => 'emp_code',
        'empl_code' => 'emp_code',
        'empl code' => 'emp_code',
        'employee code' => 'emp_code',
        'name' => 'name',
        'post' => 'post',
        'designation' => 'post',
        'subject' => 'subject',
        'section' => 'section',
        'status' => 'status',
        'grade' => 'grade',
        'basic salary at joining' => 'basic_salary_joining',
        'basic salary at the time of joining' => 'basic_salary_joining',
        'basic salary present' => 'basic_salary_present',
        'present basic salary' => 'basic_salary_present',
        'phone' => 'phone',
        'phone no' => 'phone',
        'mobile' => 'phone',
        'mobile no' => 'phone',
        'contact no' => 'phone',
        'email' => 'email',
        'e-mail' => 'email',
        'email id' => 'email',
        'gmail' => 'email',
        'gmail id' => 'email',
    ];

    /** The three tables an employee can live in. */
    private const EMPLOYEE_TYPES = ['teacher', 'staff', 'driver'];

    /** POST (normalized: uppercased, periods stripped, whitespace collapsed) -> table. */
    private const DESIGNATION_TYPE_MAP = [
        'ASST TEACHER' => 'teacher',
        'COMP TEACHER' => 'teacher',
        'COMPUTER TEACHER' => 'teacher',
        'TEACHER' => 'teacher',
        'ACCOUNTANT' => 'staff',
        'OFFICE ASST' => 'staff',
        'COMP OPERATOR' => 'staff',
        'COMPUTER OPERATOR' => 'staff',
        'ADMINISTRATOR' => 'staff',
        'ADMIN' => 'staff',
        'BOOA' => 'staff',
        'GUARD' => 'staff',
        'STAFF' => 'staff',
        'DRIVER' => 'driver',
    ];

    /** Dry-run: parses the file and reports what would happen, without writing anything. */
    public function preview(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:20480']);

        @set_time_limit(300);

        $plan = $this->buildPlan($request->file('file'));
        if (! $plan['salary_sheet_found']) {
            return response()->json(['message' => 'A "SALARY DETAILS" sheet was not found in this file.'], 422);
        }

        $failedRows = [];
        foreach ($plan['rows'] as $row) {
            if ($row['outcome'] === 'failed') {
                $failedRows[] = ['row_number' => $row['row_number'], 'empl_code' => $row['empl_code'], 'name' => $row['name'], 'reason' => $row['reason']];
            }
        }

        return response()->json($this->summary($plan) + [
            'failed_rows' => array_slice($failedRows, 0, 200),
            'rows' => array_map(fn ($r) => $this->previewRow($r), array_slice($plan['rows'], 0, 500)),
        ]);
    }

    /** Actually writes the Teacher/Staff/Driver records. */
    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:20480']);

        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $file = $request->file('file');
        $plan = $this->buildPlan($file);

        if (! $plan['salary_sheet_found']) {
            return response()->json(['message' => 'A "SALARY DETAILS" sheet was not found in this file.'], 422);
        }

        $now = now()->toDateTimeString();
        $log = ImportExportLog::create([
            'direction' => 'Import',
            'entity' => 'employee-master',
            'filename' => $file->getClientOriginalName(),
            'performed_by_id' => Auth::guard('erp')->id(),
        ]);

        $total = 0;
        $created = 0;
        $updated = 0;
        $failed = 0;
        $rowLogRows = [];
        $failedRowRows = [];
        $failedRowsResponse = [];
        $newEmployees = [];

        DB::transaction(function () use ($plan, $log, $now, &$total, &$created, &$updated, &$failed, &$rowLogRows, &$failedRowRows, &$failedRowsResponse, &$newEmployees) {
            foreach ($plan['rows'] as $row) {
                $total++;

                if ($row['outcome'] === 'failed') {
                    $failed++;
                    $this->recordFailure($log->id, $row, $row['reason'], $now, $rowLogRows, $failedRowRows, $failedRowsResponse);

                    continue;
                }

                if ($row['outcome'] === 'create') {
                    $type = $row['type'];
                    $payload = ['employee_id' => $row['empl_code'], 'name' => $row['name'], 'status' => $row['status'] ?? 'active'];
                    if ($row['salary'] !== null) {
                        $payload['salary'] = $row['salary'];
                    }
                    if ($row['phone'] !== '') {
                        $payload['phone'] = $row['phone'];
                    }
                    if ($row['email'] !== '') {
                        $payload['email'] = $row['email'];
                    }
                    if ($type === 'staff') {
                        $payload['department'] = $this->titleCase($row['post']);
                    }
                    if ($row['custom_fields'] !== []) {
                        $payload['custom_field_values'] = $row['custom_fields'];
                    }

                    $model = $this->modelClass($type)::create($payload);
                    $newEmployees[] = [
                        'type' => $type,
                        'id' => $model->id,
                        'employee_id' => $row['empl_code'],
                        'name' => $row['name'],
                        'incomplete' => $row['bio'] === null,
                    ];
                    $action = 'created';
                    $created++;
                } else {
                    // Update the record in the table it already lives in, even if POST now
                    // resolves elsewhere — moving people between tables would orphan attendance/pay.
                    $type = $row['employee_type'];
                    $model = $this->modelClass($type)::find($row['employee_id']);
                    if (! $model) {
                        $failed++;
                        $this->recordFailure($log->id, $row, 'Matched employee record could not be reloaded — try again.', $now, $rowLogRows, $failedRowRows, $failedRowsResponse);

                        continue;
                    }

                    $payload = ['employee_id' => $row['empl_code']];
                    if ($row['matched_by'] === 'code') {
                        $payload['name'] = $row['name'];
                    }
                    if ($row['status'] !== null) {
                        $payload['status'] = $row['status'];
                    }
                    if ($row['salary'] !== null) {
                        $payload['salary'] = $row['salary'];
                    }
                    if ($row['phone'] !== '') {
                        $payload['phone'] = $row['phone'];
                    }
                    if ($row['email'] !== '') {
                        $payload['email'] = $row['email'];
                    }
                    if ($type === 'staff' && trim((string) $model->department) === '') {
                        $payload['department'] = $this->titleCase($row['post']);
                    }
                    if ($row['custom_fields'] !== []) {
                        $payload['custom_field_values'] = EmployeeCustomFields::merge($model->custom_field_values, $row['custom_fields']);
                    }

                    $model->update($payload);
                    $action = 'updated';
                    $updated++;
                }

                $rowLogRows[] = [
                    'import_export_log_id' => $log->id,
                    'row_number' => $row['row_number'],
                    'status' => 'Success',
                    'identifier' => $row['empl_code'],
                    'summary' => json_encode(['name' => $row['name'], 'employee_type' => $type, 'action' => $action], JSON_THROW_ON_ERROR),
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
        });

        $log->update([
            'total_rows' => $total,
            'success_count' => $created + $updated,
            'failed_count' => $failed,
        ]);

        if ($created + $updated > 0) {
            PeopleCache::forget();
        }

        return response()->json($this->summary($plan) + [
            'log' => $log->fresh(),
            'created_count' => $created,
            'updated_count' => $updated,
            'failed_count' => $failed,
            'failed_rows' => array_slice($failedRowsResponse, 0, 200),
            'new_employees' => $newEmployees,
        ], 201);
    }

    /**
     * Reads the workbook and resolves every SALARY DETAILS row to create/update/failed with the
     * exact values that would be written — shared by preview() and import().
     *
     * @return array<string, mixed>
     */
    private function buildPlan($file): array
    {
        $book = $this->readWorkbook($file);
        $rows = $book['salary_rows'];

        [$bioMatches, $bioUnmatched] = $this->matchSideRows($book['bio_rows'], $rows);
        [$currentMatches, $currentUnmatched] = $this->matchSideRows($book['current_rows'], $rows);

        $employeeLookup = $this->loadEmployeeLookup();
        $nameLookup = $this->loadNameLookup();
        $codeCounts = array_count_values(array_filter(array_column($rows, 'empl_code'), fn ($c) => $c !== ''));

        foreach ($rows as $i => &$row) {
            $bio = isset($bioMatches[$i]) ? $book['bio_rows'][$bioMatches[$i]] : null;
            $current = isset($currentMatches[$i]) ? $book['current_rows'][$currentMatches[$i]] : null;
            $row['bio'] = $bio;
            // SALARY DETAILS' STATUS decides; STAFF DETAILS' STATUS is used when that one is blank.
            if ($row['status'] === null && $bio !== null && ($bio['status'] ?? '') !== '') {
                $row['status'] = $this->parseStatus($bio['status']);
            }

            // Present salary: SALARY DETAILS "present" > STAFF <year> basic > STAFF DETAILS basic > joining.
            $row['salary'] = $row['basic_salary_present']
                ?? $current['basic']
                ?? $bio['basic_salary']
                ?? $row['basic_salary_joining'];
            // Joining salary: its own column; otherwise the older STAFF DETAILS figure when a
            // newer one exists (in the school file STAFF DETAILS holds the earlier basic).
            $joining = $row['basic_salary_joining'];
            if ($joining === null && $bio !== null && $bio['basic_salary'] !== null && $row['salary'] !== $bio['basic_salary']) {
                $joining = $bio['basic_salary'];
            }

            $row['phone'] = $row['phone'] !== '' ? $row['phone'] : ($bio['phone'] ?? '');
            $row['email'] = $row['email'] !== '' ? $row['email'] : ($bio['email'] ?? '');

            $classified = $this->classifyRow($row, $codeCounts, $employeeLookup, $nameLookup);
            $row = array_merge($row, $classified);
            $row['custom_fields'] = $this->customFieldsFor($row, $bio, $joining);
        }
        unset($row);

        return [
            'salary_sheet_found' => $book['salary_sheet_found'],
            'staff_sheet_found' => $book['staff_sheet_found'],
            'current_sheet' => $book['current_sheet'],
            'bio_row_count' => count($book['bio_rows']),
            'bio_matched_count' => count($bioMatches),
            'current_row_count' => count($book['current_rows']),
            'current_matched_count' => count($currentMatches),
            'unmatched_side_rows' => array_merge(
                array_map(fn ($r) => ['sheet' => 'STAFF DETAILS'] + $r, $bioUnmatched),
                array_map(fn ($r) => ['sheet' => (string) $book['current_sheet']] + $r, $currentUnmatched),
            ),
            'rows' => $rows,
        ];
    }

    /** @param  array<string, mixed>  $plan */
    private function summary(array $plan): array
    {
        $byType = [];
        foreach (self::EMPLOYEE_TYPES as $t) {
            $byType[$t] = ['create' => 0, 'update' => 0];
        }
        $counts = ['create' => 0, 'update' => 0, 'failed' => 0];
        foreach ($plan['rows'] as $row) {
            $counts[$row['outcome']]++;
            if ($row['outcome'] !== 'failed') {
                $byType[$row['outcome'] === 'update' ? $row['employee_type'] : $row['type']][$row['outcome']]++;
            }
        }

        return [
            'salary_sheet_found' => $plan['salary_sheet_found'],
            'staff_sheet_found' => $plan['staff_sheet_found'],
            'current_sheet' => $plan['current_sheet'],
            'bio_row_count' => $plan['bio_row_count'],
            'bio_matched_count' => $plan['bio_matched_count'],
            'current_row_count' => $plan['current_row_count'],
            'current_matched_count' => $plan['current_matched_count'],
            'unmatched_side_rows' => array_slice($plan['unmatched_side_rows'], 0, 100),
            'total_rows' => count($plan['rows']),
            'create_count' => $counts['create'],
            'update_count' => $counts['update'],
            'failed_count' => $counts['failed'],
            'by_type' => $byType,
        ];
    }

    /** @param  array<string, mixed>  $row */
    private function previewRow(array $row): array
    {
        return [
            'row_number' => $row['row_number'],
            'empl_code' => $row['empl_code'],
            'name' => $row['name'],
            'post' => $row['post'],
            'grade' => $row['grade'],
            'type' => $row['outcome'] === 'update' ? $row['employee_type'] : ($row['type'] ?? null),
            'status' => $row['status'] ?? 'active',
            'salary' => $row['salary'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'has_bio' => $row['bio'] !== null,
            'outcome' => $row['outcome'],
            'reason' => $row['reason'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function readWorkbook($file): array
    {
        $path = $file->getRealPath();
        $names = SpreadsheetImportReader::listSheetNames($path);

        $salaryName = null;
        $staffName = null;
        $currentName = null;
        $currentYear = 0;
        foreach ($names as $n) {
            $lower = strtolower(trim($n));
            if ($lower === self::SALARY_SHEET_NAME) {
                $salaryName = $n;
            } elseif ($lower === self::STAFF_SHEET_NAME) {
                $staffName = $n;
            } elseif (preg_match('/^staff\s*-?\s*(20\d{2})\b/', $lower, $m) && (int) $m[1] > $currentYear) {
                // "STAFF 2026" — the latest such sheet carries the current basic salary.
                $currentName = $n;
                $currentYear = (int) $m[1];
            }
        }

        $result = [
            'salary_sheet_found' => $salaryName !== null,
            'staff_sheet_found' => $staffName !== null,
            'current_sheet' => $currentName,
            'salary_rows' => [],
            'bio_rows' => [],
            'current_rows' => [],
        ];
        if ($salaryName === null) {
            return $result;
        }

        $spreadsheet = SpreadsheetImportReader::loadSheetsOnly($path, array_values(array_filter([$salaryName, $staffName, $currentName])));

        $result['salary_rows'] = $this->parseSalaryRows($spreadsheet->getSheetByName($salaryName));
        if ($staffName !== null) {
            $result['bio_rows'] = $this->parseBioRows($spreadsheet->getSheetByName($staffName));
        }
        if ($currentName !== null) {
            $result['current_rows'] = $this->parseCurrentRows($spreadsheet->getSheetByName($currentName));
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $result;
    }

    /**
     * Parses SALARY DETAILS. A row whose only content is column A text (GRADE I, GRADE III,
     * TRANSPORT, ...) is a group label — remembered as the Grade of the rows below it. Other rows
     * with a blank NAME or POST are skipped silently.
     *
     * @return list<array<string, mixed>>
     */
    private function parseSalaryRows(Worksheet $sheet): array
    {
        [$headerIndex, $header, $dataRows] = $this->locateHeader($sheet, self::SALARY_HEADER_MAP, 1);

        $parsed = [];
        $group = '';
        foreach ($dataRows as $i => $rowRaw) {
            $row = $this->mapRow($header, $rowRaw, self::SALARY_HEADER_MAP);
            $codeCell = $this->cellString($row['emp_code'] ?? null);
            $name = $this->cellString($row['name'] ?? null);
            $post = $this->cellString($row['post'] ?? null);

            if ($name === '' && $post === '') {
                if ($codeCell !== '' && ! is_numeric($codeCell)) {
                    $group = $codeCell;
                }

                continue;
            }
            if ($name === '' || $post === '') {
                continue;
            }

            $grade = $this->cellString($row['grade'] ?? null);

            $parsed[] = [
                'row_number' => $headerIndex + 2 + $i,
                'empl_code' => $codeCell,
                'name' => $name,
                'post' => $post,
                'grade' => $grade !== '' ? $grade : $group,
                'subject' => $this->cellString($row['subject'] ?? null),
                'section' => $this->cellString($row['section'] ?? null),
                'status' => $this->parseStatus($this->cellString($row['status'] ?? null)),
                'basic_salary_joining' => $this->money($row['basic_salary_joining'] ?? null),
                'basic_salary_present' => $this->money($row['basic_salary_present'] ?? null),
                'phone' => $this->phone($row['phone'] ?? null),
                'email' => $this->email($row['email'] ?? null),
            ];
        }

        return $parsed;
    }

    /**
     * Parses a "STAFF <year>" sheet: EMP CODE / NAME / BASIC. Group label rows (NON TEACHING,
     * DRIVERS) have no name and are skipped.
     *
     * @return list<array{row_number:int, code:string, name:string, basic:?float}>
     */
    private function parseCurrentRows(Worksheet $sheet): array
    {
        $map = ['emp code' => 'emp_code', 'emp_code' => 'emp_code', 'empl_code' => 'emp_code', 'empl code' => 'emp_code', 'name' => 'name', 'basic' => 'basic', 'basic salary' => 'basic'];
        [$headerIndex, $header, $dataRows] = $this->locateHeader($sheet, $map, 1);

        $rows = [];
        foreach ($dataRows as $i => $rowRaw) {
            $row = $this->mapRow($header, $rowRaw, $map);
            $code = $this->cellString($row['emp_code'] ?? null);
            $name = $this->cellString($row['name'] ?? null);
            if ($name === '') {
                continue;
            }
            $rows[] = ['row_number' => $headerIndex + 2 + $i, 'code' => $code, 'name' => $name, 'basic' => $this->money($row['basic'] ?? null)];
        }

        return $rows;
    }

    /**
     * Pairs each side-sheet row (STAFF DETAILS / STAFF <year>) with one SALARY DETAILS row:
     * code (exact or dropped-3rd-digit) confirmed by a similar name, else a unique exact name,
     * else a unique similar name. Each SALARY DETAILS row is claimed at most once.
     *
     * @param  list<array<string, mixed>>  $sideRows
     * @param  list<array<string, mixed>>  $salaryRows
     * @return array{0: array<int, int>, 1: list<array{row_number:int, code:string, name:string}>}
     *         [salary row index => side row index, unmatched side rows]
     */
    private function matchSideRows(array $sideRows, array $salaryRows): array
    {
        $matches = [];
        $unmatched = [];

        foreach ($sideRows as $sideIndex => $side) {
            $code = $side['code'];
            $byCode = [];
            $byExactName = [];
            $bySimilarName = [];

            foreach ($salaryRows as $i => $salary) {
                if (isset($matches[$i])) {
                    continue;
                }
                $similar = EmployeeMatcher::similarNames($side['name'], $salary['name']);
                if ($code !== '' && $similar && EmployeeMatcher::codesMatch($code, $salary['empl_code'])) {
                    $byCode[] = $i;
                }
                if (EmployeeMatcher::normalizeName($side['name']) === EmployeeMatcher::normalizeName($salary['name'])) {
                    $byExactName[] = $i;
                }
                if ($similar) {
                    $bySimilarName[] = $i;
                }
            }

            $pick = null;
            foreach ([$byCode, $byExactName, $bySimilarName] as $candidates) {
                if (count($candidates) === 1) {
                    $pick = $candidates[0];
                    break;
                }
            }

            if ($pick === null) {
                $unmatched[] = ['row_number' => $side['row_number'], 'code' => $code, 'name' => $side['name']];

                continue;
            }
            $matches[$pick] = $sideIndex;
        }

        return [$matches, $unmatched];
    }

    /**
     * Resolves one SALARY DETAILS row to create/update/failed, without writing anything.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $codeCounts
     * @param  array<string, array<string, int>>  $employeeLookup
     * @param  array<string, list<array{id:int, code:string, name:string}>>  $nameLookup
     * @return array<string, mixed>
     */
    private function classifyRow(array $row, array $codeCounts, array $employeeLookup, array $nameLookup): array
    {
        $type = $this->resolveType($row['post'], $row['grade']);

        $emplCode = $row['empl_code'];
        if ($emplCode === '') {
            return ['outcome' => 'failed', 'type' => $type, 'reason' => 'Missing EMP_CODE.'];
        }

        if (($codeCounts[$emplCode] ?? 0) > 1) {
            return ['outcome' => 'failed', 'type' => $type, 'reason' => "EMP_CODE {$emplCode} is used by more than one row in this sheet — fix the duplicate and re-import."];
        }

        $codeMatches = [];
        foreach (self::EMPLOYEE_TYPES as $t) {
            if (isset($employeeLookup[$t][$emplCode])) {
                $codeMatches[] = [$t, $employeeLookup[$t][$emplCode]];
            }
        }

        if (count($codeMatches) > 1) {
            $types = implode(', ', array_column($codeMatches, 0));

            return ['outcome' => 'failed', 'type' => $type, 'reason' => "EMP_CODE {$emplCode} matches more than one existing employee ({$types}) — resolve manually."];
        }

        if (count($codeMatches) === 1) {
            return ['outcome' => 'update', 'type' => $type, 'employee_type' => $codeMatches[0][0], 'employee_id' => $codeMatches[0][1], 'matched_by' => 'code'];
        }

        // Same person saved earlier under the short code (12008 vs 121008) or under a code typed
        // differently — only within the POST-resolved table, and only with a similar name.
        $variantMatches = array_values(array_filter(
            $nameLookup[$type],
            fn ($e) => EmployeeMatcher::codesMatch($e['code'], $emplCode) && EmployeeMatcher::similarNames($e['name'], $row['name'])
        ));
        // The file's EMP_CODE is free in every table (no exact match above), so re-coding the
        // matched record to it cannot hit the unique index.
        if (count($variantMatches) === 1) {
            return ['outcome' => 'update', 'type' => $type, 'employee_type' => $type, 'employee_id' => $variantMatches[0]['id'], 'matched_by' => 'name'];
        }

        $nameKey = EmployeeMatcher::normalizeName($row['name']);
        $nameMatches = array_values(array_filter($nameLookup[$type], fn ($e) => EmployeeMatcher::normalizeName($e['name']) === $nameKey));

        if (count($nameMatches) > 1) {
            return ['outcome' => 'failed', 'type' => $type, 'reason' => "\"{$row['name']}\" matches more than one existing {$type} by name — resolve manually."];
        }

        if (count($nameMatches) === 1) {
            return ['outcome' => 'update', 'type' => $type, 'employee_type' => $type, 'employee_id' => $nameMatches[0]['id'], 'matched_by' => 'name'];
        }

        return ['outcome' => 'create', 'type' => $type];
    }

    /**
     * Profile details kept in custom_field_values — shown on the profile "View" and written back
     * by the Staff Profile export.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $bio
     * @return list<array{label:string, value:string}>
     */
    private function customFieldsFor(array $row, ?array $bio, ?float $joining): array
    {
        $fields = [];
        $add = function (string $label, $value) use (&$fields) {
            $value = trim((string) $value);
            if ($value !== '') {
                $fields[] = ['label' => $label, 'value' => $value];
            }
        };

        $add('Designation', $row['post']);
        $add('Grade', $row['grade']);
        $type = $row['outcome'] === 'update' ? ($row['employee_type'] ?? $row['type']) : $row['type'];
        if ($type === 'teacher') {
            $add('Subject', $row['subject']);
            $add('Section', $row['section']);
        }
        if ($bio !== null) {
            foreach (self::BIO_FIELD_LABELS as $key => $label) {
                $add($label, $bio[$key] ?? '');
            }
        }
        if ($joining !== null) {
            $add('Basic Salary at Joining', $this->formatMoney($joining));
        }

        return $fields;
    }

    /** POST -> table: known designations, then keywords, then Staff (so every row gets a profile). */
    private function resolveType(string $post, string $group): string
    {
        $normalized = $this->normalizePost($post);
        if (isset(self::DESIGNATION_TYPE_MAP[$normalized])) {
            return self::DESIGNATION_TYPE_MAP[$normalized];
        }
        if (preg_match('/\b(TEACHER|TUTOR|LECTURER|PGT|TGT|PRT)\b/', $normalized)) {
            return 'teacher';
        }
        if (str_contains($normalized, 'DRIVER')) {
            return 'driver';
        }

        return 'staff';
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

    /** @return array<string, list<array{id:int, code:string, name:string}>> */
    private function loadNameLookup(): array
    {
        $lookup = [];
        foreach (self::EMPLOYEE_TYPES as $type) {
            $lookup[$type] = $this->modelClass($type)::query()
                ->select('id', 'employee_id', 'name')
                ->get()
                ->map(fn ($r) => ['id' => $r->id, 'code' => (string) $r->employee_id, 'name' => (string) $r->name])
                ->all();
        }

        return $lookup;
    }

    /** @return class-string<Teacher|Staff|Driver> */
    private function modelClass(string $type): string
    {
        return match ($type) {
            'teacher' => Teacher::class,
            'staff' => Staff::class,
            default => Driver::class,
        };
    }

    /** Uppercase, strip periods, collapse whitespace — "Asst. Teacher" -> "ASST TEACHER". */
    private function normalizePost(string $post): string
    {
        $clean = str_replace('.', '', $post);
        $clean = preg_replace('/\s+/', ' ', trim($clean)) ?? '';

        return mb_strtoupper($clean);
    }

    private function parseStatus(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }

        return in_array(mb_strtoupper($raw), ['INACTIVE', 'IN ACTIVE', 'LEFT', 'RESIGNED', 'NO', '0'], true) ? 'inactive' : 'active';
    }

    private function titleCase(string $post): string
    {
        return mb_convert_case(mb_strtolower($post), MB_CASE_TITLE);
    }

    /** @param  array<string, mixed>  $row */
    private function recordFailure(
        int $logId,
        array $row,
        string $message,
        string $now,
        array &$rowLogRows,
        array &$failedRowRows,
        array &$failedRowsResponse
    ): void {
        $rowLogRows[] = [
            'import_export_log_id' => $logId,
            'row_number' => $row['row_number'],
            'status' => 'Failed',
            'identifier' => $row['empl_code'],
            'summary' => null,
            'error_message' => $message,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $failedRowRows[] = [
            'import_export_log_id' => $logId,
            'row_number' => $row['row_number'],
            'row_data' => json_encode(array_diff_key($row, ['bio' => 1, 'custom_fields' => 1]), JSON_THROW_ON_ERROR),
            'error_message' => mb_substr($message, 0, 255),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $failedRowsResponse[] = ['row_number' => $row['row_number'], 'empl_code' => $row['empl_code'], 'name' => $row['name'], 'error_message' => $message];
    }
}
