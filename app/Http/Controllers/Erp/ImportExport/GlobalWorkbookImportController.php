<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\ImportExportLog;
use App\Models\Income;
use App\Models\RouteStop;
use App\Models\Student;
use App\Models\StudentUdiseDetail;
use App\Models\TransportRoute;
use App\Services\ExcelDateParser;
use App\Services\SpreadsheetImportReader;
use App\Services\StudentMasterImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Imports legacy school Excel workbooks (multi-sheet). INCOME, EXPENSES, any sheet whose name
 * starts with TRANSPORT (e.g. "TRANSPORT-26", "TRANSPORT-27" next year), and any sheet whose
 * name starts with "Student Master" (e.g. "Student Master 22-26", "Student Master 23-27") are
 * processed; pivots, bank statements, salary, fuel, student duplicates, and summary dashboards
 * are ignored. TRANSPORT runs before Student Master so stoppage routes exist when bus
 * numbers are attached — see the note at that block in store().
 *
 * The Student Master sheet is processed by {@see StudentMasterImportService} — the exact same
 * pipeline erp/dashboard/import-export?type=student-import uses on its own, so the two never
 * drift out of sync.
 *
 * Corrupted columns are skipped explicitly:
 * - Purely numeric headers (e.g. a pasted TOTAL INCOME figure used as a column title)
 * - Headers containing #REF!
 * - Known junk names: tot_income, total income, income (on expenses), balance
 */
class GlobalWorkbookImportController extends Controller
{
    private const INCOME_SHEETS = ['income'];

    private const EXPENSE_SHEETS = ['expenses', 'expense'];

    /** Sheets we never import — listed in the response for transparency. */
    private const IGNORED_SHEET_HINT = 'SUMMARY, STUD_REC*, pivots, SALARY, BANK*, CHQ*, FUEL*, WORKING DAYS';

    private const MSG_BROKEN_FORMULA = 'Skipped — column contains a broken formula reference (#REF!/#N/A), not usable data.';

    private const MSG_ZERO_AMOUNT = 'Zero or negative amount — please confirm if this row should be imported.';

    private const MSG_INVALID_DATE = 'Invalid or missing date — could not determine transaction date.';

    /** Labels that appear when a header row is pasted mid-sheet. */
    private const HEADER_ARTIFACT_TOKENS = [
        'name', 'class', 'rate', 'total', 'year', 'month', 'date', 'head', 'fee',
        'session', 'mode', 'pmnt mode', 'pmt mode', 'payment mode', 'pay mode',
        'mode of payment', 'remarks', 'remark', 'address', 'description', 'amount',
        'part-1', 'part-2', 'part-3', 'part1', 'part2', 'part3', 'adm no', 'adm no.',
        'adm. no.', 'admno', 'receipt no', 'receipt no.', 'rcpt no', 'rcpt no.',
        'receipt', 'voucher no', 'voucher no.',
    ];

    private const INCOME_HEADER_MAP = [
        'year' => 'year',
        'month' => 'month',
        'date' => 'date',
        'adm no' => 'admission_no',
        'adm no.' => 'admission_no',
        'adm. no.' => 'admission_no',
        'admno' => 'admission_no',
        'name' => 'name',
        'address' => 'address',
        'class' => 'class',
        'head' => 'head',
        'fee' => 'fee',
        'session' => 'session',
        'receipt no' => 'receipt_no',
        'receipt no.' => 'receipt_no',
        'receipt' => 'receipt_no',
        'rcpt no' => 'receipt_no',
        'rcpt no.' => 'receipt_no',
        'mode' => 'mode',
        'pmnt mode' => 'mode',
        'pmt mode' => 'mode',
        'payment mode' => 'mode',
        'pay mode' => 'mode',
        'mode of payment' => 'mode',
        'remarks' => 'remarks',
        'remark' => 'remarks',
    ];

    private const EXPENSE_HEADER_MAP = [
        'year' => 'year',
        'month' => 'month',
        'date' => 'date',
        'description' => 'description',
        'part-1' => 'part1',
        'part 1' => 'part1',
        'part1' => 'part1',
        'part-2' => 'part2',
        'part 2' => 'part2',
        'part2' => 'part2',
        'amount' => 'amount',
        'part-3' => 'part3',
        'part 3' => 'part3',
        'part3' => 'part3',
        'remarks' => 'remarks',
        'remark' => 'remarks',
        'rcpt no' => 'receipt_no',
        'rcpt no.' => 'receipt_no',
        'receipt no' => 'receipt_no',
        'receipt no.' => 'receipt_no',
        'voucher no' => 'receipt_no',
        'voucher no.' => 'receipt_no',
    ];

    /**
     * Maps raw sheet HEAD codes to the exact Fee Head label the live Fee Structure Plan uses,
     * so an imported payment offsets the same "due" the school's configured plan computes —
     * rather than landing on a separately-created literal "REG"/"TUI" head that FeeCalculator
     * never resolves to when computing what a student owes.
     */
    private const INCOME_HEAD_CANONICAL_MAP = [
        'reg' => 'Registration Fee',
        'adm' => 'Admission Fee',
        'ann' => 'Session Fee',
        'tui' => 'Tution Fee',
        'tra' => 'Transport',
    ];

    /** Fee heads charged only for New admissions — skipped on Global Workbook income for Old students. */
    private const NEW_ONLY_INCOME_HEADS = [
        'registration fee',
        'admission fee',
    ];

    /**
     * HEAD codes that represent a fine rather than a fee — recorded on fee_payments.fine_amount
     * instead of as a line item, matching how the live Pay Fee screen records fines.
     */
    private const INCOME_FINE_HEAD_CODES = ['fine', 'fin'];

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:40960',
        ]);

        @set_time_limit(600);
        @ini_set('memory_limit', '1024M');

        $file = $request->file('file');
        $path = $file->getRealPath();
        $sheetNames = SpreadsheetImportReader::listSheetNames($path);

        $incomeTitle = null;
        $expenseTitle = null;
        $transportTitle = null;
        $studentMasterTitle = null;
        $toLoad = [];
        foreach ($sheetNames as $title) {
            $lower = strtolower($title);
            if (in_array($lower, self::INCOME_SHEETS, true) && ! $incomeTitle) {
                $incomeTitle = $title;
                $toLoad[] = $title;
            }
            if (in_array($lower, self::EXPENSE_SHEETS, true) && ! $expenseTitle) {
                $expenseTitle = $title;
                $toLoad[] = $title;
            }
            // "TRANSPORT-26", "TRANSPORT-27", ... — the year suffix changes every fare revision,
            // so match on the prefix rather than an exact list like the income/expense sheets.
            if (str_starts_with($lower, 'transport') && ! $transportTitle) {
                $transportTitle = $title;
                $toLoad[] = $title;
            }
            // "Student Master 22-26", "Student Master 23-27", ... — the year range changes every
            // year, so match on the prefix rather than an exact list, same as TRANSPORT-* above.
            if (str_starts_with($lower, 'student master') && ! $studentMasterTitle) {
                $studentMasterTitle = $title;
                $toLoad[] = $title;
            }
        }

        if ($toLoad === []) {
            return response()->json([
                'message' => 'No INCOME, EXPENSES, TRANSPORT-*, or Student Master* sheet found. Present sheets: '.implode(', ', $sheetNames)
                    .' — Only those are imported ('.self::IGNORED_SHEET_HINT.' are skipped).',
            ], 422);
        }

        // Load ONLY ledger sheets — never pivots/bank/salary (those blow up formula calc time).
        $spreadsheet = SpreadsheetImportReader::loadSheetsOnly($path, $toLoad);
        $incomeSheet = null;
        $expenseSheet = null;
        $transportSheet = null;
        $studentMasterSheet = null;

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $title = trim((string) $sheet->getTitle());
            $lower = strtolower($title);
            if ($incomeTitle && strtolower($incomeTitle) === $lower) {
                $parsed = SpreadsheetImportReader::fromWorksheet($sheet);
                $parsed['title'] = $title;
                $incomeSheet = $parsed;
            }
            if ($expenseTitle && strtolower($expenseTitle) === $lower) {
                $parsed = SpreadsheetImportReader::fromWorksheet($sheet);
                $parsed['title'] = $title;
                $expenseSheet = $parsed;
            }
            if ($transportTitle && strtolower($transportTitle) === $lower) {
                // Columns beyond STOPPAGE/FARE (D = FARE×multiplier formula, E = unused, F/G =
                // class-wise fee) are deliberately never read — positional read of A/B/C only.
                $parsed = SpreadsheetImportReader::fromWorksheet($sheet);
                $parsed['title'] = $title;
                $transportSheet = $parsed;
            }
            if ($studentMasterTitle && strtolower($studentMasterTitle) === $lower) {
                $parsed = SpreadsheetImportReader::fromWorksheet($sheet);
                $parsed['title'] = $title;
                $studentMasterSheet = $parsed;
            }
        }
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $ignoredSheets = [];
        foreach ($sheetNames as $name) {
            $lower = strtolower($name);
            if (in_array($lower, self::INCOME_SHEETS, true) || in_array($lower, self::EXPENSE_SHEETS, true)
                || str_starts_with($lower, 'transport') || str_starts_with($lower, 'student master')) {
                continue;
            }
            $ignoredSheets[] = $name;
        }

        $ignoredColumns = [];
        $success = 0;
        $failed = 0;
        $total = 0;
        $failedRows = [];
        $breakdown = [
            'total_rows' => 0,
            'imported' => 0,
            'skipped_header_artifact' => 0,
            'skipped_duplicate' => 0,
            'skipped_old_student_new_only_fee' => 0,
            'failed_student_not_found' => 0,
            'failed_missing_field' => 0,
            'failed_broken_formula' => 0,
            'failed_zero_amount' => 0,
            'failed_invalid_date' => 0,
            'failed_other' => 0,
        ];
        $stats = [
            'income_fee_payments' => 0,
            'income_misc' => 0,
            'expenses' => 0,
            'sheets_present' => $sheetNames,
            'sheets_ignored' => $ignoredSheets,
        ];

        $log = ImportExportLog::create([
            'direction' => 'import',
            'entity' => 'global-workbook',
            'filename' => $file->getClientOriginalName(),
            'total_rows' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'ignored_columns' => [],
            'performed_by_id' => Auth::guard('erp')->id(),
        ]);

        // Warm caches once — avoids thousands of per-row SELECTs.
        // Only load existing receipt/voucher keys that appear in this file (not the whole table).
        // Admission types power Fee Due / Pay Fee: New students get Admission+Registration+Session;
        // Old students are charged Session Fee only (see FeeCalculator::filterBreakdownForAdmissionType).
        $cache = [
            'students' => Student::query()->pluck('id', 'admission_no')->all(),
            'admission_types' => StudentUdiseDetail::query()->pluck('admission_type', 'student_id')->all(),
            'fee_heads' => FeeHead::query()->pluck('id', 'name')->all(),
            'sessions' => AcademicSession::query()->get()->keyBy('name'),
            'expense_categories' => ExpenseCategory::query()->pluck('id', 'name')->all(),
            'receipts' => [],
            'income_vouchers' => [],
            'expense_vouchers' => [],
            'bank_accounts' => [],
            'user_id' => Auth::guard('erp')->id(),
            'now' => now()->toDateTimeString(),
        ];

        $buffers = [
            'fee_payments' => [],
            'incomes' => [],
            'expenses' => [],
            'bank_transactions' => [],
            'failed_rows' => [],
            'failed_logs' => [],
        ];

        $applyResult = function (array $result) use (&$total, &$success, &$failed, &$failedRows, &$breakdown, &$stats, &$buffers) {
            $total++;
            $reason = $result['reason'] ?? 'other';

            if (($result['skip'] ?? false) === true) {
                if ($reason === 'header_artifact') {
                    $breakdown['skipped_header_artifact']++;
                } elseif ($reason === 'duplicate') {
                    $breakdown['skipped_duplicate']++;
                } elseif ($reason === 'old_student_new_only_fee') {
                    $breakdown['skipped_old_student_new_only_fee']++;
                }

                return;
            }

            if ($result['ok'] ?? false) {
                $success++;
                $breakdown['imported']++;
                if (! empty($result['bucket'])) {
                    $stats[$result['bucket']] = ($stats[$result['bucket']] ?? 0) + 1;
                }
                if (! empty($result['insert'])) {
                    $buffers[$result['insert']][] = $result['data'];
                }
                if (! empty($result['bank_transaction'])) {
                    $buffers['bank_transactions'][] = $result['bank_transaction'];
                }

                return;
            }

            $failed++;
            $key = match ($reason) {
                'student_not_found' => 'failed_student_not_found',
                'missing_field' => 'failed_missing_field',
                'broken_formula' => 'failed_broken_formula',
                'zero_amount' => 'failed_zero_amount',
                'invalid_date' => 'failed_invalid_date',
                default => 'failed_other',
            };
            $breakdown[$key]++;
            if (! empty($result['failed_row'])) {
                $buffers['failed_rows'][] = $result['failed_row'];
            }
            if (! empty($result['failed_log'])) {
                $buffers['failed_logs'][] = $result['failed_log'];
            }
            if (! empty($result['failed'])) {
                $failedRows[] = $result['failed'];
            }
        };

        $flush = function () use (&$buffers) {
            if ($buffers['fee_payments'] !== []) {
                foreach (array_chunk($buffers['fee_payments'], 250) as $chunk) {
                    DB::table('fee_payments')->insert($chunk);
                }
                $buffers['fee_payments'] = [];
            }
            if ($buffers['incomes'] !== []) {
                foreach (array_chunk($buffers['incomes'], 250) as $chunk) {
                    DB::table('incomes')->insert($chunk);
                }
                $buffers['incomes'] = [];
            }
            if ($buffers['expenses'] !== []) {
                foreach (array_chunk($buffers['expenses'], 250) as $chunk) {
                    DB::table('expenses')->insert($chunk);
                }
                $buffers['expenses'] = [];
            }
            if ($buffers['bank_transactions'] !== []) {
                foreach (array_chunk($buffers['bank_transactions'], 250) as $chunk) {
                    DB::table('bank_transactions')->insert($chunk);
                }
                $buffers['bank_transactions'] = [];
            }
            if ($buffers['failed_rows'] !== []) {
                foreach (array_chunk($buffers['failed_rows'], 250) as $chunk) {
                    DB::table('import_failed_rows')->insert($chunk);
                }
                $buffers['failed_rows'] = [];
            }
            if ($buffers['failed_logs'] !== []) {
                foreach (array_chunk($buffers['failed_logs'], 250) as $chunk) {
                    DB::table('import_row_logs')->insert($chunk);
                }
                $buffers['failed_logs'] = [];
            }
        };

        // TRANSPORT sheet first — builds Stoppage routes/fares before Student Master attaches
        // Vehicle bus numbers (and student assignments) onto those routes.
        $transportStats = ['transport_stops_updated' => 0, 'transport_stops_created' => 0, 'transport_routes_created' => 0, 'transport_rows_skipped' => 0];
        if ($transportSheet) {
            $transportStats = $this->importTransportSheet($transportSheet);
        }

        // Student Master runs before Income/Expenses — a new admission in this workbook
        // must exist in `students` before Income rows try to resolve it by Adm No.
        $studentMasterResult = null;
        if ($studentMasterSheet) {
            $studentMasterResult = app(StudentMasterImportService::class)->import(
                $studentMasterSheet['header'],
                $studentMasterSheet['rows'],
                $file->getClientOriginalName().' — '.$studentMasterSheet['title']
            );

            // Re-warm the two caches Income row-matching depends on — new/updated students and
            // admission types from the sheet just processed must be visible to it.
            $cache['students'] = Student::query()->pluck('id', 'admission_no')->all();
            $cache['admission_types'] = StudentUdiseDetail::query()->pluck('admission_type', 'student_id')->all();
        }

        if ($incomeSheet) {
            [$mapped, $junk] = $this->mapRows($incomeSheet, self::INCOME_HEADER_MAP, 'INCOME');
            $ignoredColumns = array_merge($ignoredColumns, $junk);
            $this->warmReceiptCaches($mapped, $cache);
            foreach (array_chunk($mapped, 400) as $chunk) {
                foreach ($chunk as $row) {
                    $applyResult($this->importIncomeRow($row, $log, $cache));
                }
                $flush();
            }
        }

        if ($expenseSheet) {
            [$mapped, $junk] = $this->mapRows($expenseSheet, self::EXPENSE_HEADER_MAP, 'EXPENSES');
            $ignoredColumns = array_merge($ignoredColumns, $junk);
            $this->warmExpenseVoucherCaches($mapped, $cache);
            foreach (array_chunk($mapped, 400) as $chunk) {
                foreach ($chunk as $row) {
                    $applyResult($this->importExpenseRow($row, $log, $cache));
                }
                $flush();
            }
        }

        $flush();

        $stats = array_merge($stats, $transportStats);

        $breakdown['total_rows'] = $total;

        $log->update([
            'total_rows' => $total,
            'success_count' => $success,
            'failed_count' => $failed,
            'ignored_columns' => array_values(array_unique($ignoredColumns)),
        ]);

        $stats['breakdown'] = $breakdown;
        $message = $this->formatSummaryMessage($breakdown);
        if ($transportSheet) {
            $message .= ' | Transport ('.$transportSheet['title'].'): '
                .$transportStats['transport_stops_updated'].' fare(s) updated, '
                .$transportStats['transport_stops_created'].' new stop(s) created, '
                .$transportStats['transport_rows_skipped'].' row(s) skipped.';
        }
        if ($studentMasterSheet && $studentMasterResult) {
            $stats['student_master'] = [
                'log_id' => $studentMasterResult['log']->id,
                'total_rows' => $studentMasterResult['total_rows'],
                'success_count' => $studentMasterResult['success_count'],
                'failed_count' => $studentMasterResult['failed_count'],
                'counters' => $studentMasterResult['counters'],
                'header_warnings' => $studentMasterResult['header_warnings'],
            ];
            $vehiclesCreated = (int) ($studentMasterResult['counters']['vehicles_created'] ?? 0);
            $message .= ' | Student Master ('.$studentMasterSheet['title'].'): '
                .$studentMasterResult['success_count'].' row(s) imported, '
                .$studentMasterResult['failed_count'].' failed'
                .($vehiclesCreated > 0 ? ', '.$vehiclesCreated.' vehicle(s) auto-created' : '')
                .($studentMasterResult['header_warnings'] !== [] ? ', '.count($studentMasterResult['header_warnings']).' header warning(s)' : '')
                .'. See Import Log for details.';
        }

        return response()->json([
            'log' => $log->fresh(),
            'failed_rows' => array_slice($failedRows, 0, 200),
            'stats' => $stats,
            'breakdown' => $breakdown,
            'message' => $message,
        ]);
    }

    /**
     * TRANSPORT-NN sheet: S.NO | STOPPAGE | FARE (columns D–G are a live formula, an unused
     * legacy column, and class-wise fee data — all deliberately ignored per the school's own
     * column notes). The school's routes are already one-route-per-stop, named to match each
     * stoppage exactly, so every real stoppage is matched by normalized name and just gets its
     * fare updated — a new Route+Stop is only created for a stoppage that genuinely has none yet.
     * This is what feeds both Transport Management and Fee Structure → Transport, since that
     * screen edits these same route_stops.fare values.
     *
     * @param  array{header: array<int,string>, rows: array<int, array<int, mixed>>, title?: string}  $sheet
     * @return array{transport_stops_updated: int, transport_stops_created: int, transport_routes_created: int, transport_rows_skipped: int}
     */
    private function importTransportSheet(array $sheet): array
    {
        $stats = ['transport_stops_updated' => 0, 'transport_stops_created' => 0, 'transport_routes_created' => 0, 'transport_rows_skipped' => 0];

        $defaultBranchId = TransportRoute::query()->whereNotNull('branch_id')->value('branch_id')
            ?? Branch::query()->value('id');

        $stopsByName = RouteStop::query()->get()->keyBy(fn (RouteStop $s) => mb_strtoupper(trim($s->stop_name)));

        foreach ($sheet['rows'] as $row) {
            $stoppage = trim((string) ($row[1] ?? ''));
            if ($stoppage === '' || mb_strtoupper($stoppage) === 'NONE') {
                $stats['transport_rows_skipped']++;

                continue;
            }

            $fareRaw = $row[2] ?? null;
            if (! is_numeric($fareRaw)) {
                $stats['transport_rows_skipped']++;

                continue;
            }
            $fare = round((float) $fareRaw, 2);

            $key = mb_strtoupper($stoppage);
            $existingStop = $stopsByName->get($key);

            if ($existingStop) {
                if ((float) $existingStop->fare !== $fare) {
                    $existingStop->update(['fare' => $fare]);
                }
                $stats['transport_stops_updated']++;

                continue;
            }

            $route = TransportRoute::create([
                'branch_id' => $defaultBranchId,
                'name' => $stoppage,
                'start_point' => $stoppage,
                'end_point' => $stoppage,
                'status' => 'Active',
            ]);
            $newStop = $route->stops()->create([
                'stop_name' => $stoppage,
                'sequence_no' => 1,
                'fare' => $fare,
            ]);
            $stopsByName->put($key, $newStop);
            $stats['transport_routes_created']++;
            $stats['transport_stops_created']++;
        }

        return $stats;
    }

    /** @param  array<string, int>  $breakdown */
    private function formatSummaryMessage(array $breakdown): string
    {
        $parts = [
            'Total rows: '.$breakdown['total_rows'],
            'Imported: '.$breakdown['imported'],
            'Skipped (header artifact): '.$breakdown['skipped_header_artifact'],
        ];
        if ($breakdown['skipped_duplicate'] > 0) {
            $parts[] = 'Skipped (duplicate): '.$breakdown['skipped_duplicate'];
        }
        if (($breakdown['skipped_old_student_new_only_fee'] ?? 0) > 0) {
            $parts[] = 'Skipped (Old student ADM/REG): '.$breakdown['skipped_old_student_new_only_fee'];
        }
        $parts[] = 'Failed (student not found): '.$breakdown['failed_student_not_found'];
        $otherFailed = $breakdown['failed_missing_field']
            + $breakdown['failed_broken_formula']
            + $breakdown['failed_zero_amount']
            + $breakdown['failed_invalid_date']
            + $breakdown['failed_other'];
        $parts[] = 'Failed (other): '.$otherFailed;

        $detail = [];
        if ($breakdown['failed_missing_field'] > 0) {
            $detail[] = 'missing field '.$breakdown['failed_missing_field'];
        }
        if ($breakdown['failed_broken_formula'] > 0) {
            $detail[] = 'broken formula '.$breakdown['failed_broken_formula'];
        }
        if ($breakdown['failed_zero_amount'] > 0) {
            $detail[] = 'zero/negative amount '.$breakdown['failed_zero_amount'];
        }
        if ($breakdown['failed_invalid_date'] > 0) {
            $detail[] = 'invalid date '.$breakdown['failed_invalid_date'];
        }
        if ($breakdown['failed_other'] > 0) {
            $detail[] = 'other '.$breakdown['failed_other'];
        }

        $summary = implode(' | ', $parts);
        if ($detail) {
            $summary .= ' — detail: '.implode(', ', $detail);
        }

        return $summary;
    }

    /**
     * @param  array{header: array<int,string>, rows: array<int, array<int, mixed>>, title?: string}  $sheet
     * @param  array<string, string>  $headerMap
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    private function mapRows(array $sheet, array $headerMap, string $sheetLabel): array
    {
        $junk = [];
        $indexToField = [];

        foreach ($sheet['header'] as $index => $column) {
            $raw = trim((string) $column);
            if ($this->isJunkHeader($raw)) {
                $junk[] = "{$sheetLabel}: {$raw}";

                continue;
            }
            $key = strtolower($raw);
            $key = preg_replace('/\s+/', ' ', $key) ?? $key;
            if (! isset($headerMap[$key])) {
                continue;
            }
            $indexToField[$index] = $headerMap[$key];
        }

        $mapped = [];
        $rowNumber = 1; // header consumed; first data row is Excel row 2
        foreach ($sheet['rows'] as $line) {
            $rowNumber++;
            $row = ['row_number' => $rowNumber, '_sheet' => $sheetLabel, '_junk_fields' => []];
            $hasAny = false;
            foreach ($indexToField as $index => $field) {
                $value = trim((string) ($line[$index] ?? ''));
                if ($this->isJunkCell($value)) {
                    $row['_junk_fields'][] = $field;
                    $hasAny = true;
                    $row[$field] = $value;

                    continue;
                }
                if ($value !== '') {
                    $hasAny = true;
                }
                $row[$field] = $value;
            }
            if ($hasAny) {
                $mapped[] = $row;
            }
        }

        return [$mapped, $junk];
    }

    private function isJunkHeader(string $header): bool
    {
        $h = strtolower(trim($header));
        if ($h === '') {
            return true;
        }
        if (str_contains($h, '#ref!')) {
            return true;
        }
        if (preg_match('/^\d+(\.\d+)?$/', $h)) {
            return true;
        }
        if (in_array($h, [
            'tot_income',
            'total income',
            'total_income',
            'income',
            'balance',
        ], true)) {
            return true;
        }

        return false;
    }

    private function isJunkCell(string $value): bool
    {
        $v = strtoupper(trim($value));

        return $v === '#REF!' || $v === '#N/A' || $v === '#VALUE!' || $v === '#DIV/0!'
            || $v === '#NAME?' || $v === '#NULL!' || $v === '#NUM!';
    }

    /**
     * Mid-sheet repeated headers (NAME / CLASS / RATE / TOTAL …) are spreadsheet
     * artifacts — skip silently with no ImportRowLog / ImportFailedRow.
     *
     * @param  array<string, mixed>  $row
     */
    private function isEmbeddedHeaderRow(array $row): bool
    {
        $checks = [
            $row['name'] ?? null,
            $row['admission_no'] ?? null,
            $row['description'] ?? null,
            $row['fee'] ?? null,
            $row['amount'] ?? null,
            $row['head'] ?? null,
            $row['year'] ?? null,
            $row['month'] ?? null,
            $row['date'] ?? null,
        ];

        foreach ($checks as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $token = $this->normalizeHeaderToken((string) $value);
            if (in_array($token, self::HEADER_ARTIFACT_TOKENS, true)) {
                return true;
            }
        }

        $adm = strtoupper(trim((string) ($row['admission_no'] ?? '')));
        if ($adm !== '' && in_array($adm, ['CLASS', 'RATE', 'TOTAL', 'NAME', 'HEAD', 'FEE', 'YEAR', 'MONTH', 'DATE', 'SESSION', 'MODE', 'ADDRESS', 'REMARKS'], true)) {
            return true;
        }

        // YEAR + MONTH both literally header labels
        if ($this->normalizeHeaderToken((string) ($row['year'] ?? '')) === 'year'
            && $this->normalizeHeaderToken((string) ($row['month'] ?? '')) === 'month') {
            return true;
        }

        return false;
    }

    private function normalizeHeaderToken(string $value): string
    {
        $v = strtolower(trim($value));

        return preg_replace('/\s+/', ' ', $v) ?? $v;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $cache
     */
    private function importIncomeRow(array $row, ImportExportLog $log, array &$cache): array
    {
        if ($this->isEmbeddedHeaderRow($row)) {
            return ['ok' => true, 'skip' => true, 'reason' => 'header_artifact'];
        }

        if (! empty($row['_junk_fields'])) {
            return $this->fail($log, $row, self::MSG_BROKEN_FORMULA, 'broken_formula');
        }

        $admissionNo = trim((string) ($row['admission_no'] ?? ''));
        // Excel sometimes stores Adm No as float (16701.0)
        if (is_numeric($admissionNo) && str_contains($admissionNo, '.')) {
            $admissionNo = (string) (int) round((float) $admissionNo);
        }
        $name = trim((string) ($row['name'] ?? ''));
        $feeRaw = trim((string) ($row['fee'] ?? ''));
        $receiptNo = trim((string) ($row['receipt_no'] ?? ''));
        // A receipt-no cell that's itself an unevaluated formula (e.g. "=K830+1") is not a
        // usable receipt number — fall back to the synthesized one rather than saving garbage
        // onto a document a parent will actually see.
        if ($this->looksLikeFormula($receiptNo)) {
            $receiptNo = '';
        }

        if ($feeRaw === '') {
            return $this->fail($log, $row, 'Missing required field: FEE.', 'missing_field');
        }

        $class = trim((string) ($row['class'] ?? ''));
        if ($admissionNo === '' && $class !== '') {
            return $this->fail($log, $row, 'Missing required field: ADM NO.', 'missing_field');
        }

        if ($admissionNo !== '' && $name === '') {
            return $this->fail($log, $row, 'Missing required field: NAME.', 'missing_field');
        }

        $amount = $this->parseAmount($feeRaw);
        if ($amount === null) {
            return $this->fail($log, $row, 'Missing required field: FEE.', 'missing_field');
        }
        if ($amount <= 0) {
            return $this->fail($log, $row, self::MSG_ZERO_AMOUNT, 'zero_amount');
        }

        $date = $this->resolveDate($row);
        if (! $date) {
            return $this->fail($log, $row, self::MSG_INVALID_DATE, 'invalid_date');
        }

        // PMNT MODE values other than Cash/UPI/Card/Cheque (e.g. "GAS 11662", "TRUST 6739")
        // name a real bank/trust account the money actually landed in — auto-create it (once,
        // reused for every later row naming the same account) so its running balance can be
        // tracked, same as an account entered by hand on the Bank Accounts screen.
        $bankAccountId = $this->resolveBankAccount((string) ($row['mode'] ?? ''), $cache);

        try {
            if ($admissionNo !== '') {
                $studentId = $cache['students'][$admissionNo] ?? null;
                if (! $studentId) {
                    return $this->fail(
                        $log,
                        $row,
                        "No student found for admission no. {$admissionNo} — cannot record fee payment.",
                        'student_not_found'
                    );
                }

                $session = $this->resolveSession($row['session'] ?? '', $date, $cache);

                $headRaw = trim((string) ($row['head'] ?? 'Fee')) ?: 'Fee';
                $headCode = strtolower($headRaw);
                $isFine = in_array($headCode, self::INCOME_FINE_HEAD_CODES, true);

                $items = [];
                $fineAmount = 0.0;

                if ($isFine) {
                    $fineAmount = $amount;
                } else {
                    // Match the school's own configured Fee Structure label when the sheet's
                    // abbreviation is a known one (REG/ADM/ANN/TUI/TRA), so the payment lands on
                    // the exact fee_head_id FeeCalculator resolves when computing what's due —
                    // not a separate literal "REG"/"TUI" head that due-calculation never sees.
                    $headName = self::INCOME_HEAD_CANONICAL_MAP[$headCode] ?? $headRaw;

                    // Old students are not charged Admission / Registration — skip those income
                    // lines so Fee Due / Pay Fee only expect Session Fee (and other applicable heads).
                    $admissionType = strtolower(trim((string) ($cache['admission_types'][$studentId] ?? 'New')));
                    if ($admissionType === 'old' && in_array(strtolower($headName), self::NEW_ONLY_INCOME_HEADS, true)) {
                        return [
                            'ok' => true,
                            'skip' => true,
                            'reason' => 'old_student_new_only_fee',
                        ];
                    }

                    if (! isset($cache['fee_heads'][$headName])) {
                        $head = FeeHead::create(['name' => $headName, 'description' => 'Imported fee head']);
                        $cache['fee_heads'][$headName] = $head->id;
                    }
                    $headId = $cache['fee_heads'][$headName];

                    // Which month(s) this payment counts toward — from REMARKS when it holds a real
                    // month token ("Dec/25"), a bare month name ("DEC"/"MAR" — resolved to
                    // whichever calendar year is nearest the payment date, past or future), or a
                    // coverage range ("APR TO AUG" — a single payment spanning several months,
                    // expanded to one key per month so each gets its fair share via
                    // FeeMonthAllocator), otherwise the transaction's own month. Without this, the
                    // payment is invisible to Pay Fee/Fee Due's per-month coverage (and, for
                    // annual/one-time heads, invisible even to the head-level paid total).
                    //
                    // Deliberately NOT the sheet's SESSION cell (real sheets carry rows where
                    // SESSION was left at whatever was "current" when typed, unrelated to DATE)
                    // and NOT simply "the payment's own Apr–Mar fiscal year" either (a payment
                    // made just after 1 April can still cover the outgoing March — Payment Date
                    // and fee-coverage month are two separate things, so the month name is
                    // resolved purely against how close each candidate year is to the actual
                    // payment date, never assumed to share the payment's own month or session).
                    $monthKeys = $this->resolveIncomeMonthKeys($row, $date);

                    $items[] = [
                        'fee_head_id' => $headId,
                        'fee_head_name' => $headName,
                        'amount' => $amount,
                        'months' => $monthKeys,
                        'month' => $monthKeys[0],
                    ];
                }

                $receipt = $receiptNo !== '' ? $receiptNo : sprintf('IMP-INC-%s-%s', $date, $row['row_number']);
                if (isset($cache['receipts'][$receipt])) {
                    return ['ok' => true, 'skip' => true, 'reason' => 'duplicate'];
                }

                $cache['receipts'][$receipt] = true;
                $now = $cache['now'];

                // fee_payments has no bank_account_id column, so the link to the account is a
                // BankTransaction (Deposit) instead — BankAccount::currentBalance() already sums
                // these, the same mechanism a manually-entered deposit uses.
                $bankTx = $bankAccountId ? [
                    'bank_account_id' => $bankAccountId,
                    'type' => 'Deposit',
                    'amount' => $amount,
                    'date' => $date,
                    'reference_no' => $receipt,
                    'remarks' => 'Imported fee payment — '.($name !== '' ? $name : $admissionNo),
                    'created_at' => $now,
                    'updated_at' => $now,
                ] : null;

                return [
                    'ok' => true,
                    'bucket' => 'income_fee_payments',
                    'insert' => 'fee_payments',
                    'data' => [
                        'receipt_no' => $receipt,
                        'student_id' => $studentId,
                        'academic_session_id' => $session->id,
                        'items' => json_encode($items, JSON_THROW_ON_ERROR),
                        'amount' => $amount,
                        'discount_amount' => 0,
                        'fine_amount' => $fineAmount,
                        'payment_mode' => $this->normalizeFeeMode($row['mode'] ?? 'Cash'),
                        'payment_date' => $date,
                        'remarks' => $this->composeIncomeRemarks($row),
                        'status' => 'Paid',
                        'refunded_amount' => 0,
                        'collected_by_id' => $cache['user_id'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    'bank_transaction' => $bankTx,
                ];
            }

            $voucher = $receiptNo !== '' ? 'INC-'.$receiptNo : sprintf('INC-IMP-%s-%04d', substr($date, 0, 4), $row['row_number']);
            if (isset($cache['income_vouchers'][$voucher])) {
                return ['ok' => true, 'skip' => true, 'reason' => 'duplicate'];
            }

            $cache['income_vouchers'][$voucher] = true;
            $source = trim((string) ($row['head'] ?? '')) ?: 'Imported income';
            $now = $cache['now'];

            return [
                'ok' => true,
                'bucket' => 'income_misc',
                'insert' => 'incomes',
                'data' => [
                    'voucher_no' => $voucher,
                    'source' => $source,
                    'amount' => $amount,
                    'date' => $date,
                    'payment_mode' => $this->normalizeIncomeMode($row['mode'] ?? 'Cash'),
                    'bank_account_id' => $bankAccountId,
                    'remarks' => $this->composeIncomeRemarks($row),
                    'received_by_id' => $cache['user_id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ];
        } catch (\Throwable $e) {
            return $this->fail($log, $row, $e->getMessage(), 'other');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $cache
     */
    private function importExpenseRow(array $row, ImportExportLog $log, array &$cache): array
    {
        if ($this->isEmbeddedHeaderRow($row)) {
            return ['ok' => true, 'skip' => true, 'reason' => 'header_artifact'];
        }

        if (! empty($row['_junk_fields'])) {
            return $this->fail($log, $row, self::MSG_BROKEN_FORMULA, 'broken_formula');
        }

        $description = trim((string) ($row['description'] ?? ''));
        $amountRaw = trim((string) ($row['amount'] ?? ''));

        if ($description === '') {
            return $this->fail($log, $row, 'Missing required field: DESCRIPTION.', 'missing_field');
        }
        if ($amountRaw === '') {
            return $this->fail($log, $row, 'Missing required field: AMOUNT.', 'missing_field');
        }

        $amount = $this->parseAmount($amountRaw);
        if ($amount === null) {
            return $this->fail($log, $row, 'Missing required field: AMOUNT.', 'missing_field');
        }
        if ($amount <= 0) {
            return $this->fail($log, $row, self::MSG_ZERO_AMOUNT, 'zero_amount');
        }

        $date = $this->resolveDate($row);
        if (! $date) {
            return $this->fail($log, $row, self::MSG_INVALID_DATE, 'invalid_date');
        }

        try {
            $part1 = trim((string) ($row['part1'] ?? '')) ?: 'General';
            if (! isset($cache['expense_categories'][$part1])) {
                $category = ExpenseCategory::create([
                    'name' => $part1,
                    'description' => 'Imported from workbook',
                ]);
                $cache['expense_categories'][$part1] = $category->id;
            }
            $categoryId = $cache['expense_categories'][$part1];

            $receiptNo = trim((string) ($row['receipt_no'] ?? ''));
            if ($this->looksLikeFormula($receiptNo)) {
                $receiptNo = '';
            }
            $voucher = $receiptNo !== ''
                ? (str_starts_with(strtoupper($receiptNo), 'EXP-') ? $receiptNo : 'EXP-'.$receiptNo)
                : sprintf('EXP-IMP-%s-%04d', substr($date, 0, 4), $row['row_number']);

            if (isset($cache['expense_vouchers'][$voucher])) {
                return ['ok' => true, 'skip' => true, 'reason' => 'duplicate'];
            }

            $cache['expense_vouchers'][$voucher] = true;
            $remarksParts = array_filter([
                trim((string) ($row['part2'] ?? '')) !== '' ? 'Part-2: '.trim((string) $row['part2']) : null,
                trim((string) ($row['part3'] ?? '')) !== '' ? 'Part-3: '.trim((string) $row['part3']) : null,
                trim((string) ($row['remarks'] ?? '')) !== '' ? trim((string) $row['remarks']) : null,
            ]);
            $now = $cache['now'];

            return [
                'ok' => true,
                'bucket' => 'expenses',
                'insert' => 'expenses',
                'data' => [
                    'voucher_no' => $voucher,
                    'expense_category_id' => $categoryId,
                    'title' => $description,
                    'amount' => $amount,
                    'date' => $date,
                    'payment_mode' => 'Cash',
                    'remarks' => $remarksParts ? implode(' | ', $remarksParts) : null,
                    'paid_by_id' => $cache['user_id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ];
        } catch (\Throwable $e) {
            return $this->fail($log, $row, $e->getMessage(), 'other');
        }
    }

    /** Month-name → calendar month number, covering every spelling seen in real sheets. */
    private const MONTH_NAME_MAP = [
        'jan' => 1, 'january' => 1,
        'feb' => 2, 'february' => 2,
        'mar' => 3, 'march' => 3,
        'apr' => 4, 'april' => 4,
        'may' => 5,
        'jun' => 6, 'june' => 6,
        'jul' => 7, 'july' => 7,
        'aug' => 8, 'august' => 8,
        'sep' => 9, 'sept' => 9, 'september' => 9,
        'oct' => 10, 'october' => 10,
        'nov' => 11, 'november' => 11,
        'dec' => 12, 'december' => 12,
    ];

    /**
     * The calendar year (payment year − 1, same, or + 1 — whichever is closest in months)
     * that puts $month nearest in time to $paymentDate. This is deliberately independent of
     * any "session" concept: REMARKS never says which year it means, only a month name, and
     * the payment date is the one piece of real-world ground truth available to disambiguate
     * it — the intended month is always the one closest to when the money was actually
     * collected, whether that's a few days into the future (fee due any day now) or however
     * many months into the past (late/arrears collection). Ties prefer the earlier year.
     */
    private function nearestCalendarYear(int $month, string $paymentDate): int
    {
        $payYear = (int) substr($paymentDate, 0, 4);
        $payMonth = (int) substr($paymentDate, 5, 2);

        $best = $payYear;
        $bestDistance = PHP_INT_MAX;
        foreach ([$payYear - 1, $payYear, $payYear + 1] as $year) {
            $distance = abs(($year - $payYear) * 12 + ($month - $payMonth));
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $year;
            }
        }

        return $best;
    }

    /**
     * Which Y-m month(s) this income row's amount should count toward for month-aware fee
     * tracking. Prefers REMARKS, in order: an explicit token ("Dec/25"), a coverage range
     * ("APR TO AUG" — a single payment spanning several months, one key per month), a bare
     * month name ("DEC"/"MAR" — resolved to whichever calendar year of that month sits
     * closest to the payment date, never assumed to be the payment's own month/year). Falls
     * back to the transaction's own month only when REMARKS holds none of these (e.g.
     * one-time heads like REG/ADM, where the sheet's REMARKS just repeats the head code) —
     * Payment Date and fee-coverage month are tracked as two separate things throughout.
     *
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function resolveIncomeMonthKeys(array $row, string $transactionDate): array
    {
        $remarks = trim((string) ($row['remarks'] ?? ''));
        $fallback = [substr($transactionDate, 0, 7)];

        if ($remarks === '' || $this->looksLikeFormula($remarks)) {
            return $fallback;
        }

        $range = $this->parseMonthRangeTokens($remarks, $transactionDate);
        if ($range !== null) {
            return $range;
        }

        $single = $this->parseMonthToken($remarks);
        if ($single !== null) {
            return [$single];
        }

        $bare = strtolower($remarks);
        if (isset(self::MONTH_NAME_MAP[$bare])) {
            $month = self::MONTH_NAME_MAP[$bare];

            return [sprintf('%04d-%02d', $this->nearestCalendarYear($month, $transactionDate), $month)];
        }

        return $fallback;
    }

    /**
     * Parses a coverage range like "APR TO AUG" or "Mar-June" into every Y-m key it spans.
     * The start month's year is whichever is nearest the payment date (see
     * nearestCalendarYear()); the end month then walks forward from there, rolling into the
     * next calendar year whenever its month number is smaller than the start's (e.g.
     * "DEC TO FEB", or the real sheet value "MAR TO FEB" spanning a full 12 months) — so any
     * two month names form a valid, unambiguous forward span. Returns null only when the text
     * isn't a clean two-month-name range at all.
     *
     * @return list<string>|null
     */
    private function parseMonthRangeTokens(string $value, string $transactionDate): ?array
    {
        if (! preg_match('/^([A-Za-z]+)\s*(?:to|-)\s*([A-Za-z]+)$/i', trim($value), $m)) {
            return null;
        }

        $startMonth = self::MONTH_NAME_MAP[strtolower($m[1])] ?? null;
        $endMonth = self::MONTH_NAME_MAP[strtolower($m[2])] ?? null;
        if ($startMonth === null || $endMonth === null) {
            return null;
        }

        $startYear = $this->nearestCalendarYear($startMonth, $transactionDate);

        $keys = [];
        $year = $startYear;
        $month = $startMonth;
        while (true) {
            $keys[] = sprintf('%04d-%02d', $year, $month);
            if ($month === $endMonth) {
                break;
            }
            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
            if (count($keys) >= 12) {
                break;
            }
        }

        return $keys;
    }

    /**
     * Parses a month-only token like "Dec/25", "Dec-2025", "12/2025", or "2025-12" into 'Y-m'.
     * Plain text with no digits (e.g. a head code like "REG" repeated into REMARKS) never
     * matches, by construction, since every pattern here requires a numeric year.
     */
    private function parseMonthToken(string $value): ?string
    {
        $v = trim($value);
        if ($v === '' || $this->looksLikeFormula($v)) {
            return null;
        }

        if (preg_match('/^([A-Za-z]{3,9})[\/\-\s](\d{2,4})$/', $v, $m)) {
            $month = date_parse($m[1])['month'] ?? null;
            if (! $month) {
                return null;
            }
            $year = $m[2];
            if (strlen($year) === 2) {
                $year = ((int) $year >= 70 ? '19' : '20').$year;
            }

            return sprintf('%04d-%02d', (int) $year, $month);
        }

        if (preg_match('/^(\d{1,2})[\/\-](\d{4})$/', $v, $m)) {
            $month = (int) $m[1];
            if ($month >= 1 && $month <= 12) {
                return sprintf('%04d-%02d', (int) $m[2], $month);
            }
        }

        if (preg_match('/^(\d{4})-(\d{1,2})$/', $v, $m)) {
            $month = (int) $m[2];
            if ($month >= 1 && $month <= 12) {
                return sprintf('%04d-%02d', (int) $m[1], $month);
            }
        }

        return null;
    }

    /** A cell PhpSpreadsheet returned as raw, unevaluated formula text rather than a value. */
    private function looksLikeFormula(string $value): bool
    {
        return str_starts_with(trim($value), '=');
    }

    /** @param  array<string, mixed>  $row */
    private function resolveDate(array $row): ?string
    {
        $fromDate = ExcelDateParser::parse($row['date'] ?? null);
        if ($fromDate) {
            return $fromDate;
        }

        // Fallback: YEAR + MONTH (1–12 or month name) → first day of month
        // YEAR may be "2025-26" — take the starting calendar year.
        $yearRaw = trim((string) ($row['year'] ?? ''));
        $year = 0;
        if (preg_match('/^(\d{4})/', $yearRaw, $ym)) {
            $year = (int) $ym[1];
        }
        $monthRaw = trim((string) ($row['month'] ?? ''));
        $month = is_numeric($monthRaw) ? (int) $monthRaw : (date_parse($monthRaw)['month'] ?? 0);
        if ($year >= 1990 && $year <= 2100 && $month >= 1 && $month <= 12) {
            return sprintf('%04d-%02d-01', $year, $month);
        }

        return null;
    }

    private function parseAmount(mixed $value): ?float
    {
        $raw = trim((string) $value);
        if ($raw === '' || $this->isJunkCell($raw)) {
            return null;
        }
        $raw = str_replace([',', '₹', ' '], '', $raw);
        if (! is_numeric($raw)) {
            return null;
        }

        return round((float) $raw, 2);
    }

    /** @param  array<string, mixed>  $cache */
    private function resolveSession(string $raw, string $date, array &$cache): AcademicSession
    {
        $raw = trim($raw);
        if ($raw === '') {
            $y = (int) substr($date, 0, 4);
            $m = (int) substr($date, 5, 2);
            $start = $m >= 4 ? $y : $y - 1;
            $raw = $start.'-'.substr((string) ($start + 1), 2);
        }

        $name = $raw;
        if (preg_match('/^(\d{4})-(\d{2})$/', $raw, $m)) {
            $name = $m[1].'-'.substr($m[1], 0, 2).$m[2];
        }

        if (isset($cache['sessions'][$name])) {
            return $cache['sessions'][$name];
        }

        $startYear = (int) substr($name, 0, 4);
        $session = AcademicSession::firstOrCreate(['name' => $name], [
            'start_date' => "{$startYear}-04-01",
            'end_date' => ($startYear + 1).'-03-31',
            'status' => 'closed',
            'is_current' => false,
        ]);
        $cache['sessions'][$name] = $session;

        return $session;
    }

    private function normalizeFeeMode(string $mode): string
    {
        $m = strtoupper(trim($mode));

        return match (true) {
            $m === '' || str_contains($m, 'CASH') => 'Cash',
            str_contains($m, 'UPI') => 'UPI',
            str_contains($m, 'CARD') => 'Card',
            str_contains($m, 'CHQ') || str_contains($m, 'CHEQUE') => 'Cheque',
            default => 'Bank Transfer',
        };
    }

    private function normalizeIncomeMode(string $mode): string
    {
        $m = strtoupper(trim($mode));

        return match (true) {
            $m === '' || str_contains($m, 'CASH') => 'Cash',
            str_contains($m, 'UPI') => 'UPI',
            str_contains($m, 'CHQ') || str_contains($m, 'CHEQUE') => 'Cheque',
            default => 'Bank',
        };
    }

    /**
     * A PMNT MODE cell that isn't Cash/UPI/Card/Cheque names a real account the money landed
     * in (e.g. "GAS 11662", "TRUST 6739") — find-or-create a BankAccount for it, keyed by the
     * exact mode text so every row naming the same account reuses one row instead of duplicating
     * it. Returns null for Cash/UPI/Card/Cheque/blank, since those aren't an account.
     *
     * @param  array<string, mixed>  $cache
     */
    private function resolveBankAccount(string $rawMode, array &$cache): ?int
    {
        $raw = trim($rawMode);
        if ($raw === '' || $this->looksLikeFormula($raw)) {
            return null;
        }

        $upper = strtoupper($raw);
        $isKnownMode = str_contains($upper, 'CASH') || str_contains($upper, 'UPI') || str_contains($upper, 'CARD')
            || str_contains($upper, 'CHQ') || str_contains($upper, 'CHEQUE');
        if ($isKnownMode) {
            return null;
        }

        if (isset($cache['bank_accounts'][$upper])) {
            return $cache['bank_accounts'][$upper];
        }

        // "GAS 11662" → bank_name "GAS", account_number "11662". A label with no trailing
        // digits (rare) falls back to using the whole raw text as its own account_number —
        // account_number is unique+required, so it still needs *some* stable value.
        if (preg_match('/^(.+?)\s+(\d{3,})$/', $raw, $m)) {
            $bankName = trim($m[1]);
            $accountNumber = $m[2];
        } else {
            $bankName = $raw;
            $accountNumber = $raw;
        }

        $account = BankAccount::firstOrCreate(
            ['account_number' => $accountNumber],
            [
                'account_name' => $raw,
                'bank_name' => $bankName,
                'opening_balance' => 0,
            ]
        );

        $cache['bank_accounts'][$upper] = $account->id;

        return $account->id;
    }

    /** @param  array<string, mixed>  $row */
    private function composeIncomeRemarks(array $row): ?string
    {
        // A cell that reads back as raw formula text (e.g. a VLOOKUP into a sheet this importer
        // deliberately never loads) is not usable data — drop that piece rather than writing
        // "Name: =VLOOKUP(...)" onto the record.
        $piece = function (?string $label, mixed $value): ?string {
            $v = trim((string) $value);
            if ($v === '' || $this->looksLikeFormula($v)) {
                return null;
            }

            return $label ? "{$label}: {$v}" : $v;
        };

        $parts = array_filter([
            $piece(null, $row['remarks'] ?? null),
            $piece('Class', $row['class'] ?? null),
            $piece('Name', $row['name'] ?? null),
            $piece('Address', $row['address'] ?? null),
        ]);

        return $parts ? implode(' | ', $parts) : null;
    }

    /** @param  array<string, mixed>  $row */
    private function fail(ImportExportLog $log, array $row, string $message, string $reason = 'other'): array
    {
        $identifier = $row['admission_no'] ?? $row['receipt_no'] ?? $row['description'] ?? null;
        $sheet = $row['_sheet'] ?? '';
        $now = now()->toDateTimeString();
        $rowPayload = array_merge($row, ['_fail_reason' => $reason, '_sheet' => $sheet]);

        return [
            'ok' => false,
            'reason' => $reason,
            'failed_row' => [
                'import_export_log_id' => $log->id,
                'row_number' => $row['row_number'],
                'row_data' => json_encode($rowPayload, JSON_THROW_ON_ERROR),
                'error_message' => mb_substr($message, 0, 255),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            'failed_log' => [
                'import_export_log_id' => $log->id,
                'row_number' => $row['row_number'],
                'status' => 'Failed',
                'identifier' => is_string($identifier) ? mb_substr($identifier, 0, 255) : null,
                'summary' => json_encode(['sheet' => $sheet, 'reason' => $reason], JSON_THROW_ON_ERROR),
                'error_message' => mb_substr($message, 0, 255),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            'failed' => [
                'id' => $sheet.'-'.$row['row_number'],
                'row_number' => $row['row_number'],
                'error_message' => $message,
                'reason' => $reason,
                'sheet' => $sheet,
            ],
        ];
    }

    /** @param  list<array<string, mixed>>  $mapped */
    private function warmReceiptCaches(array $mapped, array &$cache): void
    {
        $receipts = [];
        $incomeVouchers = [];
        foreach ($mapped as $row) {
            $receiptNo = trim((string) ($row['receipt_no'] ?? ''));
            if ($this->looksLikeFormula($receiptNo)) {
                $receiptNo = '';
            }
            $date = $this->resolveDate($row) ?: '1970-01-01';
            $admissionNo = trim((string) ($row['admission_no'] ?? ''));
            if ($admissionNo !== '') {
                $receipts[] = $receiptNo !== '' ? $receiptNo : sprintf('IMP-INC-%s-%s', $date, $row['row_number']);
            } else {
                $incomeVouchers[] = $receiptNo !== '' ? 'INC-'.$receiptNo : sprintf('INC-IMP-%s-%04d', substr($date, 0, 4), $row['row_number']);
            }
        }
        $receipts = array_values(array_unique(array_filter($receipts)));
        $incomeVouchers = array_values(array_unique(array_filter($incomeVouchers)));

        foreach (array_chunk($receipts, 500) as $chunk) {
            foreach (FeePayment::query()->whereIn('receipt_no', $chunk)->pluck('receipt_no') as $no) {
                $cache['receipts'][$no] = true;
            }
        }
        foreach (array_chunk($incomeVouchers, 500) as $chunk) {
            foreach (Income::query()->whereIn('voucher_no', $chunk)->pluck('voucher_no') as $no) {
                $cache['income_vouchers'][$no] = true;
            }
        }
    }

    /** @param  list<array<string, mixed>>  $mapped */
    private function warmExpenseVoucherCaches(array $mapped, array &$cache): void
    {
        $vouchers = [];
        foreach ($mapped as $row) {
            $receiptNo = trim((string) ($row['receipt_no'] ?? ''));
            if ($this->looksLikeFormula($receiptNo)) {
                $receiptNo = '';
            }
            $date = $this->resolveDate($row) ?: '1970-01-01';
            $vouchers[] = $receiptNo !== ''
                ? (str_starts_with(strtoupper($receiptNo), 'EXP-') ? $receiptNo : 'EXP-'.$receiptNo)
                : sprintf('EXP-IMP-%s-%04d', substr($date, 0, 4), $row['row_number']);
        }
        $vouchers = array_values(array_unique(array_filter($vouchers)));
        foreach (array_chunk($vouchers, 500) as $chunk) {
            foreach (Expense::query()->whereIn('voucher_no', $chunk)->pluck('voucher_no') as $no) {
                $cache['expense_vouchers'][$no] = true;
            }
        }
    }
}
