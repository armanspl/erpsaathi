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
 * Employee Master import — school "SALARY DETAILS 2026-27.xlsx"-shaped workbooks (also the file
 * produced by StaffProfileExportController). Creates/updates Teacher, Staff and Driver profiles;
 * never touches attendance or monthly payroll payments.
 *
 * Sheet roles (name + headers; school files often swap labels):
 *  - Master = `STAFF DETAILS` (or `SALARY DETAILS`): EMP_CODE + NAME + POST/STATUS. POST alone
 *    decides Teacher / Staff / Driver. Group label rows (GRADE I/III/IV/TRANSPORT) fill column A
 *    and become Grade — they never override a clear POST.
 *  - Profile = `STAFF PROFILE` (bio: DOB / phone / address / CATEG=social category). Matched by
 *    EMP_CODE (with name confirmation). Never used to pick Teacher/Staff/Driver.
 *  - `STAFF <year>` (e.g. STAFF 2026): EMP CODE / NAME / BASIC — current basic salary.
 *
 * Side-sheet codes often drop a digit (12008 vs 121008); a code match must also have a similar
 * name. Existing employees are matched by EMP_CODE then unique name within the POST-resolved
 * table. Unclear POST → review list (no guessing).
 */
class EmployeeMasterImportController extends Controller
{
    use ReadsStaffSheets;

    private const SALARY_SHEET_NAME = 'salary details';

    private const STAFF_SHEET_NAME = 'staff details';

    private const PROFILE_SHEET_NAME = 'staff profile';

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
            return response()->json(['message' => $this->missingMasterSheetMessage()], 422);
        }

        $failedRows = [];
        foreach ($plan['rows'] as $row) {
            if ($row['outcome'] === 'failed') {
                $failedRows[] = ['row_number' => $row['row_number'], 'empl_code' => $row['empl_code'], 'name' => $row['name'], 'reason' => $row['reason']];
            }
        }

        return response()->json($this->summary($plan) + [
            'failed_rows' => array_slice($failedRows, 0, 200),
            'rows' => array_map(fn ($r) => $this->previewRow($r), $plan['rows']),
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
            return response()->json(['message' => $this->missingMasterSheetMessage()], 422);
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
                    // Update only inside the POST-resolved table (Teacher / Staff / Driver).
                    $type = $row['employee_type'] ?? $row['type'];
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

        [$profileMatches, $profileUnmatched] = $this->matchSideRows($book['bio_rows'], $rows);
        [$currentMatches, $currentUnmatched] = $this->matchSideRows($book['current_rows'], $rows);

        $employeeLookup = $this->loadEmployeeLookup();
        $nameLookup = $this->loadNameLookup();
        $codeCounts = array_count_values(array_filter(array_column($rows, 'empl_code'), fn ($c) => $c !== ''));

        foreach ($rows as $i => &$row) {
            $bio = isset($profileMatches[$i]) ? $book['bio_rows'][$profileMatches[$i]] : null;
            $current = isset($currentMatches[$i]) ? $book['current_rows'][$currentMatches[$i]] : null;
            $row['bio'] = $bio;
            // Master STATUS wins; profile STATUS only fills when master left it blank.
            if ($row['status'] === null && $bio !== null && ($bio['status'] ?? '') !== '') {
                $row['status'] = $this->parseStatus($bio['status']);
            }

            // Current basic: master "present" > STAFF <year> > joining. Profile BASIC is joining-era only.
            $row['salary'] = $row['basic_salary_present']
                ?? ($current['basic'] ?? null)
                ?? $row['basic_salary_joining'];
            $joining = $row['basic_salary_joining'];
            if ($joining === null && $bio !== null && $bio['basic_salary'] !== null) {
                $joining = $bio['basic_salary'];
            }

            $row['phone'] = $row['phone'] !== '' ? $row['phone'] : ($bio['phone'] ?? '');
            $row['email'] = $row['email'] !== '' ? $row['email'] : ($bio['email'] ?? '');

            $classified = $this->classifyRow($row, $codeCounts, $employeeLookup, $nameLookup);
            $row = array_merge($row, $classified);
            $row['custom_fields'] = $this->customFieldsFor($row, $bio, $joining);
        }
        unset($row);

        $profileSheetLabel = $book['staff_sheet_name'] ?? 'STAFF PROFILE';

        return [
            'salary_sheet_found' => $book['salary_sheet_found'],
            'staff_sheet_found' => $book['staff_sheet_found'],
            'master_sheet_name' => $book['salary_sheet_name'],
            'profile_sheet_name' => $book['staff_sheet_name'],
            'current_sheet' => $book['current_sheet'],
            'bio_row_count' => count($book['bio_rows']),
            'bio_matched_count' => count($profileMatches),
            'current_row_count' => count($book['current_rows']),
            'current_matched_count' => count($currentMatches),
            'unmatched_side_rows' => array_merge(
                array_map(fn ($r) => ['sheet' => (string) $profileSheetLabel] + $r, $profileUnmatched),
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
            'master_sheet_name' => $plan['master_sheet_name'] ?? null,
            'profile_sheet_name' => $plan['profile_sheet_name'] ?? null,
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
        $bio = $row['bio'] ?? null;
        // Category always from STAFF DETAILS POST (resolveType) — never from STAFF PROFILE.
        $categoryFromPost = $row['type'] ?? null;
        $existingType = $row['outcome'] === 'update' ? ($row['employee_type'] ?? null) : null;

        return [
            'row_number' => $row['row_number'],
            'empl_code' => $row['empl_code'],
            'name' => $row['name'],
            'post' => $row['post'],
            'grade' => $row['grade'],
            'category' => $categoryFromPost,
            'type' => $categoryFromPost,
            'existing_type' => $existingType,
            'wrong_table' => $row['wrong_table'] ?? null,
            'status' => $row['status'] ?? 'active',
            'salary' => $row['salary'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'has_bio' => $bio !== null,
            'profile_matched' => $bio !== null,
            'profile_code' => $bio['code'] ?? null,
            'profile_name' => $bio['name'] ?? null,
            'outcome' => $row['outcome'],
            'reason' => $row['reason'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function readWorkbook($file): array
    {
        $path = $file->getRealPath();
        $names = SpreadsheetImportReader::listSheetNames($path);

        // Master = STAFF DETAILS (POST); profile = STAFF PROFILE (bio); current = STAFF <year>.
        $roles = $this->classifyWorkbookSheets($path, $names);
        $masterName = $roles['salary'];
        $profileName = $roles['bio'];
        $currentName = $roles['current'];

        $result = [
            'salary_sheet_found' => $masterName !== null,
            'staff_sheet_found' => $profileName !== null,
            'current_sheet' => $currentName,
            'salary_sheet_name' => $masterName,
            'staff_sheet_name' => $profileName,
            'salary_rows' => [],
            'bio_rows' => [],
            'current_rows' => [],
        ];
        if ($masterName === null) {
            return $result;
        }

        $spreadsheet = SpreadsheetImportReader::loadSheetsOnly($path, array_values(array_filter([$masterName, $profileName, $currentName])));

        $result['salary_rows'] = $this->parseSalaryRows($spreadsheet->getSheetByName($masterName));
        if ($profileName !== null) {
            $result['bio_rows'] = $this->parseBioRows($spreadsheet->getSheetByName($profileName));
        }
        if ($currentName !== null) {
            $result['current_rows'] = $this->parseCurrentRows($spreadsheet->getSheetByName($currentName));
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $result;
    }

    /**
     * Pick master (POST), profile/bio (DOB), and STAFF <year> sheets from names + headers.
     * Prefers `STAFF DETAILS` as master and `STAFF PROFILE` as bio for school workbooks.
     *
     * @param  list<string>  $names
     * @return array{salary:?string, bio:?string, current:?string}
     */
    private function classifyWorkbookSheets(string $path, array $names): array
    {
        $currentName = null;
        $currentYear = 0;
        $masterCandidates = []; // [score, name]
        $bioCandidates = [];

        $spreadsheet = SpreadsheetImportReader::loadSheetsOnly($path, $names);

        foreach ($names as $n) {
            $lower = strtolower(trim($n));
            if (preg_match('/^staff\s*-?\s*(20\d{2})\b/', $lower, $m) && (int) $m[1] > $currentYear) {
                $currentName = $n;
                $currentYear = (int) $m[1];
            }

            $sheet = $spreadsheet->getSheetByName($n);
            if (! $sheet) {
                continue;
            }

            $kind = $this->detectSheetKind($sheet);
            if ($kind === 'salary') {
                $score = 10;
                // Prefer STAFF DETAILS as the master category sheet.
                if ($lower === self::STAFF_SHEET_NAME) {
                    $score += 60;
                } elseif ($lower === self::SALARY_SHEET_NAME) {
                    $score += 40;
                } elseif (str_contains($lower, 'salary')) {
                    $score += 20;
                }
                // Never treat STAFF PROFILE as master even if a POST column somehow exists.
                if ($lower === self::PROFILE_SHEET_NAME || str_contains($lower, 'profile')) {
                    $score -= 100;
                }
                $masterCandidates[] = [$score, $n];
            } elseif ($kind === 'bio') {
                $score = 10;
                if ($lower === self::PROFILE_SHEET_NAME || str_contains($lower, 'profile')) {
                    $score += 60;
                } elseif (str_contains($lower, 'bio')) {
                    $score += 40;
                } elseif ($lower === self::STAFF_SHEET_NAME) {
                    // Only if STAFF DETAILS is the bio sheet (template layout), not master.
                    $score += 20;
                }
                $bioCandidates[] = [$score, $n];
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        usort($masterCandidates, fn ($a, $b) => $b[0] <=> $a[0]);
        usort($bioCandidates, fn ($a, $b) => $b[0] <=> $a[0]);

        $masterName = $masterCandidates[0][1] ?? null;
        $bioName = null;
        foreach ($bioCandidates as [, $candidate]) {
            if ($candidate !== $masterName) {
                $bioName = $candidate;
                break;
            }
        }

        return [
            'salary' => $masterName,
            'bio' => $bioName,
            'current' => $currentName,
        ];
    }

    /**
     * Classify a sheet from its header row: master has POST/DESIGNATION; profile/bio has DOB.
     * CATEG on the profile sheet is social category (GEN/OBC) — never treated as job role.
     */
    private function detectSheetKind(Worksheet $sheet): ?string
    {
        $all = $sheet->toArray(null, false, false, false);
        foreach (array_slice($all, 0, 10) as $cells) {
            $normalized = array_map(fn ($c) => $this->normalizeHeader($c), $cells);
            $keys = [];
            foreach ($normalized as $h) {
                if ($h === '') {
                    continue;
                }
                if (isset(self::SALARY_HEADER_MAP[$h])) {
                    $keys[self::SALARY_HEADER_MAP[$h]] = true;
                }
                if (isset(self::STAFF_HEADER_MAP[$h])) {
                    $keys[self::STAFF_HEADER_MAP[$h]] = true;
                }
            }

            $hasName = isset($keys['name']);
            $hasCode = isset($keys['emp_code']);
            if (! $hasName || ! $hasCode) {
                continue;
            }

            // Master designation sheet — POST/DESIGNATION is the only job-category column.
            if (isset($keys['post'])) {
                return 'salary';
            }
            // Profile / bio sheet — DOB (or address + phone without POST).
            if (isset($keys['dob']) || (isset($keys['address']) && isset($keys['phone']))) {
                return 'bio';
            }
        }

        return null;
    }

    private function missingMasterSheetMessage(): string
    {
        return 'No employee master sheet found. Need a sheet with EMP_CODE, NAME and POST '
            .'(usually titled "STAFF DETAILS", or "SALARY DETAILS" in some files). '
            .'Category (Teacher / Staff / Driver) comes only from that sheet — not from STAFF PROFILE.';
    }

    /**
     * Parses the master sheet (STAFF DETAILS / SALARY DETAILS). A row whose only content is
     * column A text (GRADE I, GRADE III, TRANSPORT, ...) is a group label — remembered as the
     * Grade of the rows below it. Other rows with a blank NAME or POST are skipped silently.
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
     * Resolves one master-sheet row to create/update/failed, without writing anything.
     * Category comes only from POST on STAFF DETAILS — never from STAFF PROFILE.
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
        if ($type === null) {
            return [
                'outcome' => 'failed',
                'type' => null,
                'reason' => 'POST "'.$row['post'].'" on STAFF DETAILS does not clearly map to Teacher, Staff or Driver — review manually.',
            ];
        }

        $emplCode = $row['empl_code'];
        if ($emplCode === '') {
            return ['outcome' => 'failed', 'type' => $type, 'reason' => 'Missing EMP_CODE.'];
        }

        if (($codeCounts[$emplCode] ?? 0) > 1) {
            return ['outcome' => 'failed', 'type' => $type, 'reason' => "EMP_CODE {$emplCode} is used by more than one row in this sheet — fix the duplicate and re-import."];
        }

        // POST decides the table (ASST. TEACHER → Teacher, not Staff). Match only inside that
        // table — never update a Staff row when STAFF DETAILS says Teacher.
        if (isset($employeeLookup[$type][$emplCode])) {
            return [
                'outcome' => 'update',
                'type' => $type,
                'employee_type' => $type,
                'employee_id' => $employeeLookup[$type][$emplCode],
                'matched_by' => 'code',
            ];
        }

        $wrongTables = [];
        foreach (self::EMPLOYEE_TYPES as $t) {
            if ($t !== $type && isset($employeeLookup[$t][$emplCode])) {
                $wrongTables[] = $t;
            }
        }
        // Same EMP_CODE already in Teacher and Staff (etc.) — leave for manual cleanup.
        if (count($wrongTables) > 1) {
            return [
                'outcome' => 'failed',
                'type' => $type,
                'reason' => "EMP_CODE {$emplCode} exists in more than one people list (".implode(', ', $wrongTables).') — resolve manually.',
            ];
        }

        // Same person saved earlier under the short code (12008 vs 121008) — only within the
        // POST-resolved table, and only with a similar name.
        $variantMatches = array_values(array_filter(
            $nameLookup[$type],
            fn ($e) => EmployeeMatcher::codesMatch($e['code'], $emplCode) && EmployeeMatcher::similarNames($e['name'], $row['name'])
        ));
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

        // No row in the correct table yet → create Teacher/Staff/Driver from POST.
        // (A wrong-table record with the same EMP_CODE is left alone — e.g. old Staff copy.)
        return [
            'outcome' => 'create',
            'type' => $type,
            'wrong_table' => $wrongTables[0] ?? null,
        ];
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

    /**
     * POST on STAFF DETAILS → Teacher / Staff / Driver. Returns null when unclear (manual review).
     * $group (GRADE I / TRANSPORT) is grade context only — never overrides a mapped POST.
     */
    private function resolveType(string $post, string $group): ?string
    {
        $normalized = $this->normalizePost($post);
        if ($normalized === '') {
            return null;
        }
        if (isset(self::DESIGNATION_TYPE_MAP[$normalized])) {
            return self::DESIGNATION_TYPE_MAP[$normalized];
        }
        if (preg_match('/\b(TEACHER|TUTOR|LECTURER|PGT|TGT|PRT)\b/', $normalized)) {
            return 'teacher';
        }
        if (str_contains($normalized, 'DRIVER')) {
            return 'driver';
        }

        return null;
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
