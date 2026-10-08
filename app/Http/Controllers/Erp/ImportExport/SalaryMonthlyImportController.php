<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Driver;
use App\Models\ImportExportLog;
use App\Models\SalarySlip;
use App\Models\Staff;
use App\Models\Teacher;
use App\Services\Payroll\EmployeeMatcher;
use App\Services\Payroll\ReadsStaffSheets;
use App\Services\Payroll\SalaryHistory;
use App\Services\SpreadsheetImportReader;
use App\Support\EmployeeCustomFields;
use App\Support\PeopleCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Imports the whole salary workbook in one go ("GAS SALARY 2026-27.xlsx": APR-24-1, APR-24-2, ...
 * AUG -26, AUG-26-2 — two sheets per month — plus STAFF DETAIL / STAFF DETAILS / CODE / C.L
 * sheets). Sheet names are never trusted; a sheet is a monthly salary sheet when its row 5 header
 * has EMPL_CODE/PRESENT/CL, and its period comes from the row 4 title ("SALARY PAYMENT DETAILS
 * AUGUST-2026"). Two steps:
 *
 *  - preview(): reports every sheet (period, employees, new/unresolved, slips that will be
 *    created/updated, already-paid slips that will be left alone) for review.
 *  - import(): writes the confirmed {sheet, period} list. Each sheet is one Salary History batch,
 *    so it can be rolled back on its own from the Salary Sheet page.
 *
 * Every figure is recomputed exactly like the sheet's own formulas — PER DAY = Basic / Days,
 * THIS MONTH = Basic / Days x (Present + CL), G.SALARY = THIS MONTH - ADV — rather than copied, as
 * the workbook's cached results are unreliable (#REF! columns, rows pointing at the wrong row).
 *
 * Employees: EMPL_CODE is matched through EmployeeMatcher (exact code, the dropped-3rd-digit
 * code confirmed by name, or a unique name) because these sheets write codes differently from
 * the Staff Profile file (12008 here vs 121008 there). Someone matched nowhere is created in the
 * table their designation points to — from the `STAFF DETAIL` / `STAFF DETAIL SALARY` sheet
 * (A=EMPL_CODE, B=NAME, C=DESIGNATION, D=LEFT, no header) — or sent to review when unknown.
 *
 * With "update profiles" on, the same workbook also refreshes the people records: designation
 * and LEFT (inactive) from STAFF DETAIL, bio-data from STAFF DETAILS, and the present basic
 * salary from the latest month imported.
 *
 * Where each slip sat (sheet / block between subtotal rows / row) is kept so the export can
 * rebuild the same two-sheets-per-month layout.
 */
class SalaryMonthlyImportController extends Controller
{
    use ReadsStaffSheets;

    /** Normalized source header -> internal field key; other columns are display-only/recomputed. */
    private const HEADER_MAP = [
        'empl_code' => 'empl_code',
        'emp_code' => 'empl_code',
        'name' => 'name',
        'basic salary' => 'basic_salary',
        'days in month' => 'days_in_month',
        'absent' => 'absent',
        'present' => 'present',
        'cl' => 'cl',
        'adv' => 'advance',
        // Written by our own export: "PAID 01-06-2026" and "CASH" / "BANK — SBI (A/c ••1662)".
        'signature' => 'signature',
        'pmnt mode' => 'payment_mode',
        'payment mode' => 'payment_mode',
    ];

    /** A sheet must carry at least these headers to be treated as a monthly salary sheet. */
    private const REQUIRED_HEADERS = ['empl_code', 'present', 'cl'];

    /** Designation master sheet names (positional A=code, B=name, C=designation, D=LEFT). */
    private const DESIGNATION_SHEET_NAMES = ['staff detail salary', 'staff detail'];

    private const BIO_SHEET_NAME = 'staff details';

    /** DESIGNATION -> table; anything else falls back to keywords (TEACHER / DRIVER) then Staff. */
    private const DESIGNATION_TYPE_MAP = [
        'ASST TEACHER' => 'teacher',
        'COMP TEACHER' => 'teacher',
        'COMPUTER TEACHER' => 'teacher',
        'BOOA' => 'staff',
        'OFFICE ASST' => 'staff',
        'GUARD' => 'staff',
        'ACCOUNTANT' => 'staff',
        'ADMINISTRATOR' => 'staff',
        'ADMIN' => 'staff',
        'COMP OPERATOR' => 'staff',
        'COMPUTER OPERATOR' => 'staff',
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

    /** Scans every sheet in the uploaded workbook and reports what an import would do. */
    public function preview(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:20480']);

        @set_time_limit(300);
        @ini_set('memory_limit', '1024M');

        $path = $request->file('file')->getRealPath();
        $names = SpreadsheetImportReader::listSheetNames($path);
        $spreadsheet = SpreadsheetImportReader::loadSheetsOnly($path, $names);

        $matcher = EmployeeMatcher::fromDatabase();
        $designations = $this->loadDesignations($spreadsheet, $names);
        $bioRows = $this->loadBioRows($spreadsheet, $names);

        // Existing slips, keyed type|id|period, to tell "create" from "update" / "already paid".
        $existing = [];
        foreach (SalarySlip::query()->get(['employee_type', 'employee_id', 'period', 'status']) as $s) {
            $existing["{$s->employee_type}|{$s->employee_id}|{$s->period}"] = $s->status;
        }

        $sheets = [];
        $reads = [];
        $ambiguous = [];
        foreach ($names as $name) {
            $sheet = $spreadsheet->getSheetByName($name);
            if (! $sheet) {
                continue;
            }

            $read = $this->readSalarySheet($sheet);
            if ($read === null) {
                $sheets[] = ['name' => $name, 'detected' => false, 'kind' => $this->sideSheetKind($name)];

                continue;
            }

            $reads[] = ['name' => $name, 'read' => $read];
            $counts = ['employee_count' => 0, 'new_count' => 0, 'unresolved_count' => 0, 'create_count' => 0, 'update_count' => 0, 'paid_count' => 0];
            $unresolved = [];
            $seen = [];
            foreach ($read['rows'] as $row) {
                $rowKey = $this->rowKey($row);
                if (isset($seen[$rowKey])) {
                    continue;
                }
                $seen[$rowKey] = true;
                $counts['employee_count']++;

                $designation = $this->designationFor($designations, $row);
                $match = $this->resolveRow($matcher, $row, $designation);
                if (isset($match['error'])) {
                    $counts['unresolved_count']++;
                    $unresolved[] = $row['empl_code'].' '.$row['name'];
                    $ambiguous[$match['error']] = $match['error'];

                    continue;
                }
                if ($match === null) {
                    // New person — from STAFF DETAIL, or created automatically with the suggested type.
                    $counts['new_count']++;
                    $counts['create_count']++;

                    continue;
                }
                $status = $read['period'] ? ($existing["{$match['type']}|{$match['id']}|{$read['period']}"] ?? null) : null;
                if ($status === 'Paid') {
                    $counts['paid_count']++;
                } elseif ($status !== null) {
                    $counts['update_count']++;
                } else {
                    $counts['create_count']++;
                }
            }

            $sheets[] = [
                'name' => $name,
                'detected' => true,
                'period' => $read['period'],
                'period_label' => $read['period'] ? $this->periodLabel($read['period']) : null,
                'days_in_month' => $read['days_in_month'],
                'unresolved' => array_slice($unresolved, 0, 20),
            ] + $counts;
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()->json([
            'sheets' => $sheets,
            'unknown_people' => array_values($this->collectUnknown($reads, $matcher, $designations)),
            'ambiguous' => array_values($ambiguous),
            'profiles' => [
                'designation_rows' => count($designations),
                'designation_matched' => count(array_filter($designations, fn ($d) => $this->isMatch($matcher->resolve($d['code'], $d['name'], null, $d['type'])))),
                'left_count' => count(array_filter($designations, fn ($d) => $d['left'])),
                'bio_rows' => count($bioRows),
                'bio_matched' => count(array_filter($bioRows, fn ($b) => $this->isMatch($matcher->resolve($b['code'], $b['name'])))),
            ],
        ]);
    }

    /** Imports the confirmed {name, period} sheet list (JSON-encoded in the `sheets` field). */
    public function import(Request $request)
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480',
            'sheets' => 'required|string',
            'update_profiles' => 'nullable|boolean',
            // Save the imported slips as Paid (the school's sheets are salaries already paid).
            'mark_paid' => 'nullable|boolean',
            'payment_mode' => 'nullable|in:Cash,Bank',
            'bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
            'paid_on' => 'nullable|date',
            'unknown_types' => 'nullable|string',
        ]);

        $sheetsInput = json_decode($data['sheets'], true);
        if (! is_array($sheetsInput) || $sheetsInput === []) {
            return response()->json(['message' => 'No sheets were selected to import.'], 422);
        }
        $updateProfiles = (bool) ($data['update_profiles'] ?? true);
        $pay = [
            'mark_paid' => (bool) ($data['mark_paid'] ?? false),
            'mode' => $data['payment_mode'] ?? 'Cash',
            'bank_account_id' => isset($data['bank_account_id']) ? (int) $data['bank_account_id'] : null,
            'paid_on' => $data['paid_on'] ?? null, // null = last day of each salary month
            'banks' => BankAccount::query()->get(['id', 'bank_name', 'account_name', 'account_number'])->all(),
        ];
        if ($pay['mark_paid'] && $pay['mode'] === 'Bank' && ! $pay['bank_account_id']) {
            $only = BankAccount::query()->limit(2)->pluck('id');
            if ($only->count() !== 1) {
                return response()->json(['message' => 'Choose the bank account the salaries were paid from.', 'errors' => ['bank_account_id' => ['Choose the bank account the salaries were paid from.']]], 422);
            }
            $pay['bank_account_id'] = (int) $only->first();
        }
        // NAME => teacher|staff|driver|skip, changed in the preview for people found nowhere;
        // anyone not listed is created automatically with the suggested type.
        $unknownChoices = array_filter(
            (array) json_decode((string) ($data['unknown_types'] ?? ''), true),
            fn ($t) => in_array($t, [...EmployeeMatcher::TYPES, 'skip'], true)
        );
        $latestPeriod = max(array_map(fn ($e) => (string) ($e['period'] ?? ''), $sheetsInput));
        $createdUnknown = []; // "type|id" => last period seen

        @set_time_limit(600);
        @ini_set('memory_limit', '1024M');

        $file = $request->file('file');
        $path = $file->getRealPath();
        $originalName = $file->getClientOriginalName();

        $allNames = SpreadsheetImportReader::listSheetNames($path);
        $wanted = array_values(array_filter(array_map(fn ($e) => (string) ($e['name'] ?? ''), $sheetsInput)));
        foreach ($allNames as $n) {
            $lower = strtolower(trim($n));
            if (in_array($lower, self::DESIGNATION_SHEET_NAMES, true) || $lower === self::BIO_SHEET_NAME) {
                $wanted[] = $n;
            }
        }
        $spreadsheet = SpreadsheetImportReader::loadSheetsOnly($path, array_values(array_unique($wanted)));

        $userId = Auth::guard('erp')->id();
        $matcher = EmployeeMatcher::fromDatabase();
        $designations = $this->loadDesignations($spreadsheet, $allNames);
        $bioRows = $updateProfiles ? $this->loadBioRows($spreadsheet, $allNames) : [];
        $nextSlipNo = SalarySlip::nextSlipNo();

        $results = [];
        $newEmployeesAll = [];
        $latestBasic = []; // "type|id" => [period, basic] — newest month per employee

        // Oldest month first, so if a later sheet repeats a person the newest figures win.
        usort($sheetsInput, fn ($a, $b) => strcmp((string) ($a['period'] ?? ''), (string) ($b['period'] ?? '')));

        $jobs = [];
        foreach ($sheetsInput as $entry) {
            $sheetName = (string) ($entry['name'] ?? '');
            $period = trim((string) ($entry['period'] ?? ''));

            if (trim($sheetName) === '' || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
                $results[] = ['sheet' => $sheetName, 'period' => $period, 'error' => 'Missing or invalid period.'];

                continue;
            }
            $sheet = $spreadsheet->getSheetByName($sheetName);
            $read = $sheet ? $this->readSalarySheet($sheet) : null;
            if ($read === null) {
                $results[] = ['sheet' => $sheetName, 'period' => $period, 'error' => $sheet ? 'Not a salary sheet.' : 'Sheet not found in file.'];

                continue;
            }
            $jobs[] = ['name' => $sheetName, 'period' => $period, 'read' => $read];
        }

        // Type for every person found nowhere: the admin's choice, else the suggestion.
        $unknownTypes = [];
        foreach ($this->collectUnknown($jobs, $matcher, $designations) as $key => $person) {
            $unknownTypes[$key] = $unknownChoices[$key] ?? $person['suggested_type'];
        }

        foreach ($jobs as ['name' => $sheetName, 'period' => $period, 'read' => $read]) {
            $label = "{$originalName} — ".trim($sheetName).' ('.$this->periodLabel($period).')';
            // A failing sheet is rolled back and reported on its own; the others still import.
            $before = [$nextSlipNo, $latestBasic, $createdUnknown, clone $matcher];
            try {
                // Plain closures (not fn) — the slip counter and trackers must carry over by reference.
                $result = SalaryHistory::batch('import', $label, function () use ($sheetName, $originalName, $period, $read, $matcher, $designations, $unknownTypes, $pay, $userId, &$nextSlipNo, &$latestBasic, &$createdUnknown) {
                    return DB::transaction(function () use ($sheetName, $originalName, $period, $read, $matcher, $designations, $unknownTypes, $pay, $userId, &$nextSlipNo, &$latestBasic, &$createdUnknown) {
                        return $this->importSheetRows($sheetName, $originalName, $period, $read, $matcher, $designations, $unknownTypes, $pay, $userId, $nextSlipNo, $latestBasic, $createdUnknown);
                    });
                });
            } catch (\Throwable $e) {
                [$nextSlipNo, $latestBasic, $createdUnknown, $matcher] = $before;
                report($e);
                $results[] = ['sheet' => trim($sheetName), 'period' => $period, 'error' => 'This sheet was not imported: '.mb_substr($e->getMessage(), 0, 300)];

                continue;
            }
            $result['history_batch_id'] = SalaryHistory::lastBatchId();
            $results[] = $result;
            $newEmployeesAll = array_merge($newEmployeesAll, $result['new_employees']);
        }

        // People created from the "unknown" list who are not on the newest month have left.
        foreach ($createdUnknown as $key => $lastPeriod) {
            if ($lastPeriod < $latestPeriod) {
                [$type, $id] = explode('|', $key);
                $this->modelClass($type)::whereKey($id)->update(['status' => 'inactive']);
            }
        }

        $profiles = $updateProfiles
            ? DB::transaction(fn () => $this->updateProfiles($matcher, $designations, $bioRows, $latestBasic))
            : null;

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        PeopleCache::forget();

        return response()->json([
            'results' => $results,
            'new_employees' => $newEmployeesAll,
            'profiles' => $profiles,
        ], 201);
    }

    /**
     * Reads one sheet if it is a monthly salary sheet.
     *
     * @return array{period:?string, days_in_month:?int, rows:list<array<string, mixed>>}|null
     */
    private function readSalarySheet(Worksheet $sheet): ?array
    {
        $all = $sheet->toArray(null, false, false, false);
        $headerRaw = $all[4] ?? [];
        $header = array_map(fn ($h) => $this->normalizeHeader($h), $headerRaw);
        foreach (self::REQUIRED_HEADERS as $needed) {
            if (! in_array($needed, $header, true)) {
                return null;
            }
        }

        $titleRow = $all[3] ?? [];

        return [
            'period' => $this->detectPeriod($this->extractTitleText($titleRow)),
            'days_in_month' => $this->extractDaysInMonth($titleRow),
            'rows' => $this->parseDataRows($header, array_slice($all, 5)),
        ];
    }

    /**
     * Rows 6+ -> structured values, with the block they sit in (blocks are separated by subtotal
     * rows: no EMPL_CODE but numbers in them; plain blank rows don't split a block). Rows with an
     * EMPL_CODE but no NAME or BASIC SALARY are leftover placeholders and skipped.
     *
     * @param  list<string>  $header
     * @param  list<list<mixed>>  $dataRows
     * @return list<array<string, mixed>>
     */
    private function parseDataRows(array $header, array $dataRows): array
    {
        $parsed = [];
        $block = 1;
        $rowsInBlock = 0;
        $order = 0;

        foreach ($dataRows as $i => $rowRaw) {
            $row = $this->mapRow($header, $rowRaw, self::HEADER_MAP);
            $emplCode = EmployeeMatcher::normalizeCode($row['empl_code'] ?? null);

            if ($emplCode === '') {
                $hasNumbers = count(array_filter($rowRaw, fn ($c) => is_int($c) || is_float($c) || (is_string($c) && str_starts_with($c, '=')))) > 0;
                if ($hasNumbers && $rowsInBlock > 0) {
                    $block++;
                    $rowsInBlock = 0;
                }

                continue;
            }

            $name = $this->cellString($row['name'] ?? null);
            $basicRaw = $row['basic_salary'] ?? null;
            if ($name === '' || ! is_numeric($basicRaw)) {
                continue;
            }

            $rowsInBlock++;
            $parsed[] = [
                'row_number' => 6 + $i,
                'empl_code' => $emplCode,
                'name' => $name,
                'basic_salary' => (float) $basicRaw,
                'days_in_month' => is_numeric($row['days_in_month'] ?? null) ? (int) $row['days_in_month'] : null,
                'absent' => is_numeric($row['absent'] ?? null) ? (float) $row['absent'] : 0.0,
                'present' => is_numeric($row['present'] ?? null) ? (float) $row['present'] : null,
                'cl' => is_numeric($row['cl'] ?? null) ? (float) $row['cl'] : 0.0,
                'advance' => is_numeric($row['advance'] ?? null) ? (float) $row['advance'] : 0.0,
                'signature' => $this->cellString($row['signature'] ?? null),
                'payment_mode' => $this->cellString($row['payment_mode'] ?? null),
                'block' => $block,
                'order' => ++$order,
                'raw' => $row,
            ];
        }

        return $parsed;
    }

    /**
     * Writes one sheet's rows to salary_slips (upsert per employee+period). Paid slips are never
     * overwritten. Employees matched nowhere are created from the designation master.
     *
     * @param  array{period:?string, days_in_month:?int, rows:list<array<string, mixed>>}  $read
     * @param  list<array<string, mixed>>  $designations
     * @param  array<string, array{0:string, 1:float}>  $latestBasic
     */
    private function importSheetRows(string $sheetName, string $originalFilename, string $period, array $read, EmployeeMatcher $matcher, array $designations, array $unknownTypes, array $pay, ?int $userId, string &$nextSlipNo, array &$latestBasic, array &$createdUnknown): array
    {
        $now = now()->toDateTimeString();
        $log = ImportExportLog::create([
            'direction' => 'Import',
            'entity' => 'salary-monthly',
            'filename' => "{$originalFilename} — ".trim($sheetName),
            'performed_by_id' => $userId,
        ]);

        $seenCodes = [];
        $stats = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped_paid' => 0, 'failed' => 0, 'marked_paid' => 0];
        $rowLogRows = [];
        $failedRowRows = [];
        $failedRowsResponse = [];
        $newEmployees = [];

        foreach ($read['rows'] as $row) {
            $stats['total']++;
            $emplCode = $row['empl_code'];
            $name = $row['name'];

            $designation = $this->designationFor($designations, $row);
            $match = $this->resolveRow($matcher, $row, $designation);
            if (isset($match['error'])) {
                $stats['failed']++;
                $this->recordFailure($log->id, $row, $match['error'], $now, $rowLogRows, $failedRowRows, $failedRowsResponse);

                continue;
            }

            if ($match === null) {
                $fromUnknownList = false;
                if ($designation === null) {
                    $chosen = $unknownTypes[$this->unknownKey($row)] ?? 'staff';
                    if ($chosen === 'skip') {
                        $stats['failed']++;
                        $this->recordFailure($log->id, $row, "{$name} ({$emplCode}) skipped — \"Skip\" was chosen for this name in the preview.", $now, $rowLogRows, $failedRowRows, $failedRowsResponse);

                        continue;
                    }
                    $designation = ['type' => $chosen, 'designation' => ['teacher' => 'ASST. TEACHER', 'staff' => 'STAFF', 'driver' => 'DRIVER'][$chosen], 'left' => false];
                    $fromUnknownList = true;
                }

                $type = $designation['type'];
                $payload = [
                    // The sheet's code, unless the school reused it for someone already saved
                    // (POONAM DEVI 202401 = SADIYA KHAN's code) — then "202401-2".
                    'employee_id' => $this->uniqueCode($type, $emplCode),
                    'name' => $name,
                    'status' => $designation['left'] ? 'inactive' : 'active',
                    'salary' => $row['basic_salary'],
                    'custom_field_values' => [['label' => 'Designation', 'value' => $designation['designation']]],
                ];
                if ($type === 'staff') {
                    $payload['department'] = mb_convert_case(mb_strtolower($designation['designation']), MB_CASE_TITLE);
                }
                $model = $this->modelClass($type)::create($payload);
                $matcher->add($type, (int) $model->id, (string) $model->employee_id, $name);
                $match = ['type' => $type, 'id' => (int) $model->id];
                $newEmployees[] = ['type' => $type, 'id' => $model->id, 'employee_id' => (string) $model->employee_id, 'name' => $name];
                if ($fromUnknownList) {
                    $createdUnknown["{$type}|{$model->id}"] = $period;
                }
            }

            $employeeType = $match['type'];
            $employeeId = $match['id'];
            // The same person twice in one sheet is a real duplicate; two people sharing a
            // mistyped code (JAMAL QUAISER / ASMA KHATOON both 201315) are not.
            if (isset($seenCodes["{$employeeType}|{$employeeId}"])) {
                $stats['failed']++;
                $this->recordFailure($log->id, $row, "{$name} ({$emplCode}) appears more than once in this sheet — remove the duplicate row and re-import.", $now, $rowLogRows, $failedRowRows, $failedRowsResponse);

                continue;
            }
            $seenCodes["{$employeeType}|{$employeeId}"] = true;
            if (isset($createdUnknown["{$employeeType}|{$employeeId}"])) {
                $createdUnknown["{$employeeType}|{$employeeId}"] = max($createdUnknown["{$employeeType}|{$employeeId}"], $period);
            }

            $daysInMonth = $row['days_in_month'] ?? $read['days_in_month'] ?? $this->calendarDays($period);
            $basic = $row['basic_salary'];
            $cl = $row['cl'];
            $absent = $row['absent'];
            // PRESENT is sometimes a formula (=E-F) — fall back to Days - Absent - CL.
            $present = $row['present'] ?? max(0, $daysInMonth - $absent - $cl);
            $payload = self::computeSlip($basic, $daysInMonth, $present, $absent, $cl, $row['advance']) + [
                'sheet_name' => mb_substr(trim($sheetName), 0, 60),
                'sheet_block' => $row['block'],
                'sheet_row' => $row['order'],
            ];
            $payment = $this->paymentFor($row, $period, $pay);
            $payload += $payment ?? ['status' => 'Pending'];
            if ($payment !== null) {
                $stats['marked_paid']++;
            }

            $existing = SalarySlip::where('employee_type', $employeeType)
                ->where('employee_id', $employeeId)
                ->where('period', $period)
                ->first();

            if ($existing && $existing->status === 'Paid') {
                $stats['skipped_paid']++;
                if ($payment !== null) {
                    $stats['marked_paid']--;
                }
                $action = 'skipped (already paid)';
            } elseif ($existing) {
                if ($payment === null) {
                    unset($payload['status']); // keep a Draft/Pending slip's own status
                }
                $existing->update($payload);
                $stats['updated']++;
                $action = 'updated';
            } else {
                SalarySlip::create($payload + [
                    'employee_type' => $employeeType,
                    'employee_id' => $employeeId,
                    'period' => $period,
                    'slip_no' => $nextSlipNo,
                    'generated_by_id' => $userId,
                ]);
                $nextSlipNo = $this->incrementSlipNo($nextSlipNo);
                $stats['created']++;
                $action = 'created';
            }

            $key = "{$employeeType}|{$employeeId}";
            if (! isset($latestBasic[$key]) || strcmp($period, $latestBasic[$key][0]) >= 0) {
                $latestBasic[$key] = [$period, $basic];
            }

            $rowLogRows[] = [
                'import_export_log_id' => $log->id,
                'row_number' => $row['row_number'],
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
            'total_rows' => $stats['total'],
            'success_count' => $stats['created'] + $stats['updated'] + $stats['skipped_paid'],
            'failed_count' => $stats['failed'],
        ]);

        return [
            'sheet' => trim($sheetName),
            'period' => $period,
            'log' => $log->fresh(),
            'stats' => $stats,
            'failed_rows' => array_slice($failedRowsResponse, 0, 200),
            'new_employees' => $newEmployees,
        ];
    }

    /**
     * The sheet's own formulas: PER DAY = D/E, TOTAL DAYS = PRESENT + CL,
     * THIS MONTH = (D/E) x TOTAL DAYS, G.SALARY = THIS MONTH - ADV. Also used by the
     * Create Salary Slip form so a hand-made slip matches an imported one.
     *
     * @return array<string, mixed>
     */
    public static function computeSlip(float $basic, int $daysInMonth, float $present, float $absent, float $cl, float $advance, float $otherEarnings = 0.0, float $otherDeductions = 0.0): array
    {
        $daysInMonth = max(1, $daysInMonth);
        $totalDays = $present + $cl;
        $thisMonth = round($basic / $daysInMonth * $totalDays, 2);

        return [
            'basic_salary' => $basic,
            'days_in_month' => $daysInMonth,
            'present' => $present,
            'absent' => $absent,
            'cl' => $cl,
            'total_days' => $totalDays,
            'per_day_rate' => round($basic / $daysInMonth, 2),
            'this_month_salary' => $thisMonth,
            'advance' => $advance,
            'allowances' => $otherEarnings,
            'deductions' => $otherDeductions,
            'net_salary' => round($thisMonth + $otherEarnings - $otherDeductions - $advance, 2),
        ];
    }

    /**
     * Designation, LEFT status, bio-data and the newest basic salary onto the matched profiles.
     *
     * @param  list<array<string, mixed>>  $designations
     * @param  list<array<string, mixed>>  $bioRows
     * @param  array<string, array{0:string, 1:float}>  $latestBasic
     */
    private function updateProfiles(EmployeeMatcher $matcher, array $designations, array $bioRows, array $latestBasic): array
    {
        $stats = ['designations' => 0, 'marked_left' => 0, 'bio' => 0, 'salaries' => 0];
        $models = [];
        $load = function (string $type, int $id) use (&$models) {
            return $models["{$type}|{$id}"] ??= $this->modelClass($type)::find($id);
        };

        foreach ($designations as $d) {
            $match = $matcher->resolve($d['code'], $d['name'], null, $d['type']);
            if (! $this->isMatch($match) || ! ($model = $load($match['type'], $match['id']))) {
                continue;
            }
            $model->custom_field_values = EmployeeCustomFields::set($model->custom_field_values, 'Designation', $d['designation']);
            $stats['designations']++;
            if ($d['left'] && $model->status !== 'inactive') {
                $model->status = 'inactive';
                $stats['marked_left']++;
            }
        }

        foreach ($bioRows as $bio) {
            $match = $matcher->resolve($bio['code'], $bio['name']);
            if (! $this->isMatch($match) || ! ($model = $load($match['type'], $match['id']))) {
                continue;
            }
            $fields = [];
            foreach (self::BIO_FIELD_LABELS as $key => $label) {
                if (($bio[$key] ?? '') !== '') {
                    $fields[] = ['label' => $label, 'value' => $bio[$key]];
                }
            }
            if ($fields !== []) {
                $model->custom_field_values = EmployeeCustomFields::merge($model->custom_field_values, $fields);
            }
            if ($bio['phone'] !== '' && trim((string) $model->phone) === '') {
                $model->phone = $bio['phone'];
            }
            $stats['bio']++;
        }

        // Present basic = the newest month imported, unless a newer slip already exists.
        foreach ($latestBasic as $key => [$period, $basic]) {
            [$type, $id] = explode('|', $key);
            $newer = SalarySlip::where('employee_type', $type)->where('employee_id', $id)->where('period', '>', $period)->exists();
            $model = $load($type, (int) $id);
            if (! $newer && $model && (float) $model->salary !== (float) $basic) {
                $model->salary = $basic;
                $stats['salaries']++;
            }
        }

        foreach ($models as $model) {
            if ($model && $model->isDirty()) {
                $model->save();
            }
        }

        return $stats;
    }

    /**
     * STAFF DETAIL / STAFF DETAIL SALARY: no header, A=EMPL_CODE, B=NAME, C=DESIGNATION, D=LEFT.
     *
     * @param  list<string>  $names
     * @return list<array{code:string, name:string, designation:string, type:string, left:bool}>
     */
    private function loadDesignations(Spreadsheet $spreadsheet, array $names): array
    {
        $rows = [];
        foreach ($names as $name) {
            if (! in_array(strtolower(trim($name)), self::DESIGNATION_SHEET_NAMES, true) || ! ($sheet = $spreadsheet->getSheetByName($name))) {
                continue;
            }
            foreach ($sheet->toArray(null, false, false, false) as $cells) {
                $code = EmployeeMatcher::normalizeCode($cells[0] ?? null);
                $person = $this->cellString($cells[1] ?? null);
                $designation = mb_strtoupper($this->cellString($cells[2] ?? null));
                if (($code === '' && $person === '') || $designation === '' || $designation === 'DESIGNATION') {
                    continue;
                }
                $rows[] = [
                    'code' => $code,
                    'name' => $person,
                    'designation' => $designation,
                    'type' => $this->designationType($designation),
                    'left' => in_array(mb_strtoupper($this->cellString($cells[3] ?? null)), ['LEFT', 'INACTIVE', 'RESIGNED'], true),
                ];
            }
        }

        return $rows;
    }

    /** @param  list<string>  $names */
    private function loadBioRows(Spreadsheet $spreadsheet, array $names): array
    {
        foreach ($names as $name) {
            if (strtolower(trim($name)) === self::BIO_SHEET_NAME && ($sheet = $spreadsheet->getSheetByName($name))) {
                return $this->parseBioRows($sheet);
            }
        }

        return [];
    }

    /**
     * The designation-master entry for a monthly row: same code family AND a similar (or same
     * first) name — codes get reused for different people over the years (24209 is SAHEB HUSAIN
     * in STAFF DETAIL but MD IQBAL in MARCH-25-2) — else a unique exact, then similar, name.
     *
     * @param  list<array<string, mixed>>  $designations
     * @param  array<string, mixed>  $row
     */
    private function designationFor(array $designations, array $row): ?array
    {
        $code = $row['empl_code'];
        $name = $row['name'];
        $first = EmployeeMatcher::firstName($name);
        $candidates = [
            array_filter($designations, fn ($d) => EmployeeMatcher::codesMatch($d['code'], $code)
                && (EmployeeMatcher::similarNames($d['name'], $name) || ($first !== '' && EmployeeMatcher::firstName($d['name']) === $first))),
            array_filter($designations, fn ($d) => EmployeeMatcher::normalizeName($d['name']) === EmployeeMatcher::normalizeName($name)),
            array_filter($designations, fn ($d) => EmployeeMatcher::similarNames($d['name'], $name)),
        ];
        foreach ($candidates as $found) {
            if (count($found) === 1) {
                return reset($found);
            }
            if (count($found) > 1) {
                return null;
            }
        }

        return null;
    }

    private function designationType(string $designation): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim(str_replace('.', '', $designation))) ?? '';
        if (isset(self::DESIGNATION_TYPE_MAP[$normalized])) {
            return self::DESIGNATION_TYPE_MAP[$normalized];
        }
        if (preg_match('/\b(TEACHER|TUTOR|LECTURER)\b/', $normalized)) {
            return 'teacher';
        }

        return str_contains($normalized, 'DRIVER') ? 'driver' : 'staff';
    }

    /**
     * The row's own code/name first; if that finds nobody, the name the designation sheet gives
     * for that code (the monthly sheet says "WASIM AKHTAR" where STAFF DETAIL says WASIM AKRAM).
     *
     * @param  array<string, mixed>  $row
     */
    private function resolveRow(EmployeeMatcher $matcher, array $row, ?array $designation): ?array
    {
        $match = $matcher->resolve($row['empl_code'], $row['name'], null, $designation['type'] ?? null);
        if ($match === null && $designation !== null && $designation['code'] === $row['empl_code'] && $designation['name'] !== '') {
            $byDesignation = $matcher->resolve('', $designation['name'], null, $designation['type']);
            if ($this->isMatch($byDesignation)) {
                return $byDesignation;
            }
        }

        return $match;
    }

    /**
     * People on the selected sheets who match nobody and are not in STAFF DETAIL, grouped by
     * name (MD IQBAL appears as 32210 and 24209 — one person). Each gets a suggested type from
     * who else sits in the same block of the sheet (the drivers' block -> Driver), else Staff.
     *
     * @param  list<array{name:string, read:array}>  $reads
     * @param  list<array<string, mixed>>  $designations
     * @return array<string, array{key:string, name:string, code:string, codes:list<string>, sheets:list<string>, last_period:?string, suggested_type:string}>
     */
    private function collectUnknown(array $reads, EmployeeMatcher $matcher, array $designations): array
    {
        $unknown = [];
        $votes = [];
        foreach ($reads as ['name' => $sheetName, 'read' => $read]) {
            $blockTypes = [];
            $unknownBlocks = [];
            foreach ($read['rows'] as $row) {
                $designation = $this->designationFor($designations, $row);
                $match = $this->resolveRow($matcher, $row, $designation);
                if ($match !== null && ! isset($match['error'])) {
                    $blockTypes[$row['block']][$match['type']] = ($blockTypes[$row['block']][$match['type']] ?? 0) + 1;

                    continue;
                }
                if ($match !== null || $designation !== null) {
                    continue; // ambiguous (reported separately) or created from STAFF DETAIL
                }
                $key = $this->unknownKey($row);
                $unknown[$key] ??= ['key' => $key, 'name' => $row['name'], 'code' => $row['empl_code'], 'codes' => [], 'sheets' => [], 'last_period' => null];
                if (! in_array($row['empl_code'], $unknown[$key]['codes'], true)) {
                    $unknown[$key]['codes'][] = $row['empl_code'];
                }
                $unknown[$key]['sheets'][] = trim($sheetName);
                $unknown[$key]['last_period'] = max((string) $unknown[$key]['last_period'], (string) $read['period']);
                $unknownBlocks[$key][] = $row['block'];
            }
            foreach ($unknownBlocks as $key => $blocks) {
                foreach ($blocks as $block) {
                    foreach ($blockTypes[$block] ?? [] as $type => $n) {
                        $votes[$key][$type] = ($votes[$key][$type] ?? 0) + $n;
                    }
                }
            }
        }
        foreach ($unknown as $key => &$person) {
            $person['code'] = $person['codes'][0] ?? '';
            $tally = $votes[$key] ?? [];
            arsort($tally);
            $person['suggested_type'] = $tally ? (string) array_key_first($tally) : 'staff';
        }
        unset($person);

        return $unknown;
    }

    /** A free employee code: the sheet's code, or "202401-2" when the school reused it for someone else. */
    private function uniqueCode(string $type, string $code): string
    {
        $class = $this->modelClass($type);
        $candidate = $code !== '' ? $code : 'EMP';
        for ($n = 2; $class::where('employee_id', $candidate)->exists(); $n++) {
            $candidate = "{$code}-{$n}";
        }

        return $candidate;
    }

    /** Unknown people are grouped by name (one person even when their code changed). */
    private function unknownKey(array $row): string
    {
        return EmployeeMatcher::normalizeName($row['name']);
    }

    /** @param  array<string, mixed>  $row */
    private function rowKey(array $row): string
    {
        return $row['empl_code'].'|'.EmployeeMatcher::normalizeName($row['name']);
    }

    private function isMatch(?array $match): bool
    {
        return $match !== null && ! isset($match['error']);
    }

    private function sideSheetKind(string $name): ?string
    {
        $lower = strtolower(trim($name));
        if (in_array($lower, self::DESIGNATION_SHEET_NAMES, true)) {
            return 'designations';
        }

        return $lower === self::BIO_SHEET_NAME ? 'bio' : null;
    }

    /** @return class-string<Teacher|Staff|Driver> */
    private function modelClass(string $type): string
    {
        return match ($type) {
            'teacher' => Teacher::class,
            'driver' => Driver::class,
            default => Staff::class,
        };
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
            if ($cell !== null && is_numeric($cell) && (int) $cell >= 28 && (int) $cell <= 31) {
                $days = (int) round((float) $cell);
            }
        }

        return $days;
    }

    /** Finds a 4-digit year token and treats the word immediately before it as the month name. */
    private function detectPeriod(string $title): ?string
    {
        $tokens = preg_split('/[\s\-\/,.]+/', mb_strtoupper(trim($title))) ?: [];
        foreach ($tokens as $i => $token) {
            if ($i > 0 && preg_match('/^(19|20)\d{2}$/', $token) && isset(self::MONTH_NAMES[$tokens[$i - 1]])) {
                return $token.'-'.self::MONTH_NAMES[$tokens[$i - 1]];
            }
        }

        return null;
    }

    /**
     * Paid status for one imported row, or null to leave it Pending. A row that says how it was
     * paid (our export's SIGNATURE "PAID 01-06-2026" + PMNT MODE "BANK — SBI (A/c ••1662)") wins;
     * otherwise, with "Save as Paid" on, the mode/bank/date chosen on the import screen are used
     * (date = last day of the salary month unless one was picked).
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $pay
     * @return array{status:string, payment_mode:string, bank_account_id:?int, paid_on:string}|null
     */
    private function paymentFor(array $row, string $period, array $pay): ?array
    {
        $signature = mb_strtoupper($row['signature'] ?? '');
        $rowSaysPaid = str_starts_with($signature, 'PAID');
        if (! $rowSaysPaid && ! $pay['mark_paid']) {
            return null;
        }

        $paidOn = $pay['paid_on'] ?: date('Y-m-t', strtotime($period.'-01'));
        if ($rowSaysPaid && preg_match('/(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})/', $signature, $m)) {
            $paidOn = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        $mode = $pay['mode'];
        $bankId = $pay['mode'] === 'Bank' ? $pay['bank_account_id'] : null;
        $written = mb_strtoupper($row['payment_mode'] ?? '');
        if (str_starts_with($written, 'CASH')) {
            $mode = 'Cash';
            $bankId = null;
        } elseif (str_starts_with($written, 'BANK')) {
            $mode = 'Bank';
            // If the account can't be told from the text, no bank debit is created for that row.
            $bankId = $this->bankFromText($written, $pay['banks']) ?? $pay['bank_account_id'];
        }

        return ['status' => 'Paid', 'payment_mode' => $mode, 'bank_account_id' => $bankId, 'paid_on' => $paidOn];
    }

    /** "BANK — STATE BANK OF INDIA (A/c ••7890)" -> the matching bank account (last 4 digits, else name). */
    private function bankFromText(string $text, array $banks): ?int
    {
        if (preg_match('/(\d{4})\)?\s*$/', $text, $m)) {
            foreach ($banks as $b) {
                if (substr((string) $b->account_number, -4) === $m[1]) {
                    return (int) $b->id;
                }
            }
        }
        foreach ($banks as $b) {
            if ($b->bank_name !== '' && str_contains($text, mb_strtoupper(trim((string) $b->bank_name)))) {
                return (int) $b->id;
            }
        }

        return null;
    }

    private function calendarDays(string $period): int
    {
        [$year, $month] = array_map('intval', explode('-', $period));

        return cal_days_in_month(CAL_GREGORIAN, $month, $year);
    }

    private function periodLabel(string $period): string
    {
        $months = ['01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April', '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August', '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'];
        [$year, $month] = array_pad(explode('-', $period), 2, '');

        return ($months[$month] ?? $month).' '.$year;
    }

    private function incrementSlipNo(string $slipNo): string
    {
        return preg_replace_callback('/(\d+)$/', fn ($m) => str_pad((string) ((int) $m[1] + 1), strlen($m[1]), '0', STR_PAD_LEFT), $slipNo);
    }

    /** @param  array<string, mixed>  $row */
    private function recordFailure(int $logId, array $row, string $message, string $now, array &$rowLogRows, array &$failedRowRows, array &$failedRowsResponse): void
    {
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
            'row_data' => json_encode($row['raw'], JSON_THROW_ON_ERROR),
            'error_message' => mb_substr($message, 0, 255),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $failedRowsResponse[] = ['row_number' => $row['row_number'], 'empl_code' => $row['empl_code'], 'name' => $row['name'], 'error_message' => $message];
    }
}
