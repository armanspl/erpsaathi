<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeeDiscount;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\FeePaymentAudit;
use App\Services\AnnualFeeHead;
use App\Models\ImportExportLog;
use App\Models\Income;
use App\Models\RouteStop;
use App\Models\Student;
use App\Models\StudentUdiseDetail;
use App\Models\TransportRoute;
use App\Services\ExcelDateParser;
use App\Services\SalaryBankWorkbookService;
use App\Services\SpreadsheetImportReader;
use App\Services\StudentMasterImportService;
use App\Support\DashboardCache;
use App\Support\FeeCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Imports legacy school Excel workbooks (multi-sheet). INCOME, EXPENSES, any sheet whose name
 * starts with TRANSPORT (e.g. "TRANSPORT-26", "TRANSPORT-27" next year), any sheet whose
 * name starts with "Student Master" (e.g. "Student Master 22-26"), the "FEES DISCOUNT" sheet
 * (ADM-DISC / ANN-DISC / TUI DISC / TRA DISC → per-student FeeDiscount rows), any sheet whose
 * name starts with "BANK" and isn't a pivot (e.g. "BANK IDBI 11662 26-27" — a real statement
 * ledger, not "BANK GAS PIVOT"), and the yearly "SALARY … Bank" sheet (auto-creates
 * Teachers/Staff/Drivers + monthly salary slips) are processed; pivots, fuel, CHQ, and summary
 * dashboards are ignored. TRANSPORT runs before Student Master so stoppage routes exist when
 * bus numbers are attached — see the note at that block in store(). FEES DISCOUNT runs after
 * Student Master so admission numbers resolve to students just imported.
 *
 * The Student Master sheet is processed by {@see StudentMasterImportService} — the exact same
 * pipeline erp/dashboard/import-export?type=student-import uses on its own, so the two never
 * drift out of sync. SALARY Bank is processed by {@see SalaryBankWorkbookService}. Each BANK
 * sheet is processed by {@see importBankSheet()} — every row becomes a BankTransaction against
 * the BankAccount its own sheet title names (e.g. "BANK IDBI 11662 26-27" → account "IDBI 11662"),
 * the same account resolution an INCOME/EXPENSES row's PMNT MODE cell already uses.
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
    private const IGNORED_SHEET_HINT = 'SUMMARY, STUD_REC*, pivots (incl. BANK*PIVOT), CHQ*, FUEL*, WORKING DAYS';

    /** Reason stamped on FeeDiscount rows created from the FEES DISCOUNT sheet (idempotent re-import). */
    private const FEES_DISCOUNT_REASON = 'Imported from FEES DISCOUNT';

    /**
     * FEES DISCOUNT columns → fee-head codes resolved via INCOME_HEAD_CANONICAL_MAP.
     * ANN-DISC uses AnnualFeeHead (Annual Fee and Session Fee are the same charge — whichever
     * label this session's fee structure uses). Sheet headers vary slightly (ADM-DISC / TUI DISC).
     *
     * @var array<string, string>
     */
    private const FEES_DISCOUNT_HEAD_MAP = [
        'adm disc' => 'adm',
        'ann disc' => 'ann',
        'tui disc' => 'tui',
        'tra disc' => 'tra',
    ];

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

    /**
     * mode=preview runs the whole import inside a transaction and rolls it back, so the school
     * sees exactly what would be inserted / skipped / flagged before anything is saved.
     * A normal import only adds new records and skips unchanged ones; existing records whose
     * sheet values changed are listed for review and left as they are unless
     * apply_corrections=1 (the controlled correction step).
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:40960',
            'mode' => 'nullable|in:preview,import',
            'apply_corrections' => 'nullable|boolean',
        ]);

        $preview = $request->input('mode') === 'preview';
        $applyCorrections = $request->boolean('apply_corrections');
        if (! $preview) {
            return $this->runImport($request, false, $applyCorrections);
        }

        DB::beginTransaction();
        try {
            return $this->runImport($request, true, $applyCorrections);
        } finally {
            DB::rollBack();
        }
    }

    private function runImport(Request $request, bool $preview, bool $applyCorrections)
    {
        @set_time_limit(600);
        @ini_set('memory_limit', '1024M');

        $file = $request->file('file');
        $path = $file->getRealPath();
        $sheetNames = SpreadsheetImportReader::listSheetNames($path);

        $incomeTitle = null;
        $expenseTitle = null;
        $transportTitle = null;
        $studentMasterTitle = null;
        $feesDiscountTitle = null;
        $salaryBankTitle = null;
        $bankTitles = [];
        $toLoad = [];
        $salaryBank = app(SalaryBankWorkbookService::class);
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
            // "FEES DISCOUNT" — ADM-DISC / ANN-DISC / TUI DISC / TRA DISC per student.
            if (! $feesDiscountTitle && $this->isFeesDiscountSheetTitle($title)) {
                $feesDiscountTitle = $title;
                $toLoad[] = $title;
            }
            // "SALARY 26-27 Bank" — yearly payroll bank with NAME/DESIG/BASIC + APR–MAR.
            if (! $salaryBankTitle && $salaryBank->isSalaryBankSheetTitle($title)) {
                $salaryBankTitle = $title;
                $toLoad[] = $title;
            }
            // "BANK IDBI 11662 26-27", "BANK IDBI TRUST 6739 26-27", ... — every real statement
            // ledger sheet is imported (unlike TRANSPORT/Student Master, there can be more than
            // one open at once, one per account); "BANK GAS PIVOT" etc. are skipped as pivots.
            if (str_starts_with($lower, 'bank') && ! str_contains($lower, 'pivot')) {
                $bankTitles[] = $title;
                $toLoad[] = $title;
            }
        }

        if ($toLoad === []) {
            return response()->json([
                'message' => 'No INCOME, EXPENSES, TRANSPORT-*, Student Master*, FEES DISCOUNT, BANK*, or SALARY … Bank sheet found. Present sheets: '.implode(', ', $sheetNames)
                    .' — Only those are imported ('.self::IGNORED_SHEET_HINT.' are skipped).',
            ], 422);
        }

        // Load ONLY matched sheets — never pivots / fuel (formula-heavy).
        $spreadsheet = SpreadsheetImportReader::loadSheetsOnly($path, $toLoad);
        $incomeSheet = null;
        $expenseSheet = null;
        $transportSheet = null;
        $studentMasterSheet = null;
        $feesDiscountSheet = null;
        $salaryBankSheet = null;
        $bankSheets = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $title = trim((string) $sheet->getTitle());
            $lower = strtolower($title);
            if ($incomeTitle && strtolower($incomeTitle) === $lower) {
                // ERP exports put a TOTAL row above the header; the school's own file starts at row 1.
                $headerRow = $this->incomeHeaderRow($sheet);
                $parsed = SpreadsheetImportReader::fromWorksheet($sheet, $headerRow);
                $parsed['header_row'] = $headerRow;
                $parsed['title'] = $title;
                $incomeSheet = $parsed;
            }
            if ($expenseTitle && strtolower($expenseTitle) === $lower) {
                $parsed = SpreadsheetImportReader::fromWorksheet($sheet);
                $parsed['title'] = $title;
                $expenseSheet = $parsed;
            }
            if ($feesDiscountTitle && strtolower($feesDiscountTitle) === $lower) {
                // Some workbooks put a blank/title row above the real ADM NO. / ADM-DISC header.
                $headerRow = $this->feesDiscountHeaderRow($sheet);
                $parsed = SpreadsheetImportReader::fromWorksheet($sheet, $headerRow);
                $parsed['header_row'] = $headerRow;
                $parsed['title'] = $title;
                $feesDiscountSheet = $parsed;
            }
            if ($transportTitle && strtolower($transportTitle) === $lower) {
                // Columns beyond STOPPAGE/FARE (D = FARE×multiplier formula, E = unused, F/G =
                // class-wise fee) are deliberately never read — positional read of A/B/C only.
                $parsed = SpreadsheetImportReader::fromWorksheet($sheet);
                // FARE is often typed as a formula ("=850+50"). Rows are read without formula
                // evaluation, so use the result Excel saved with the file (or work it out when
                // the file has none) — otherwise the stop is skipped as having no fare.
                foreach ($parsed['rows'] as $i => $row) {
                    if (is_string($row[2] ?? null) && str_starts_with($row[2], '=')) {
                        $cell = $sheet->getCell('C'.($i + 2));
                        $value = $cell->getOldCalculatedValue();
                        if (! is_numeric($value)) {
                            try {
                                $value = $cell->getCalculatedValue();
                            } catch (\Throwable) {
                                $value = null;
                            }
                        }
                        $parsed['rows'][$i][2] = $value;
                    }
                }
                $parsed['title'] = $title;
                $transportSheet = $parsed;
            }
            if ($studentMasterTitle && strtolower($studentMasterTitle) === $lower) {
                $parsed = SpreadsheetImportReader::fromWorksheet($sheet);
                $parsed['title'] = $title;
                $studentMasterSheet = $parsed;
            }
            if ($salaryBankTitle && strtolower($salaryBankTitle) === $lower) {
                $salaryBankSheet = $sheet;
            }
            if ($bankTitles !== [] && in_array($lower, array_map('strtolower', $bankTitles), true)) {
                // YEAR | MON | DATE | ITEM | CHQ NO | DR/CR | AMOUNT | TOTAL (running-balance
                // formula, never read) | CATEGORY — positional read, same as TRANSPORT above.
                $parsed = SpreadsheetImportReader::fromWorksheet($sheet);
                $parsed['title'] = $title;
                $bankSheets[] = $parsed;
            }
        }

        $salaryBankResult = null;
        if ($salaryBankSheet) {
            $salaryBankResult = $salaryBank->importWorksheet($salaryBankSheet, $salaryBankTitle, Auth::guard('erp')->id());
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $ignoredSheets = [];
        foreach ($sheetNames as $name) {
            $lower = strtolower($name);
            if (in_array($lower, self::INCOME_SHEETS, true) || in_array($lower, self::EXPENSE_SHEETS, true)
                || str_starts_with($lower, 'transport') || str_starts_with($lower, 'student master')
                || $this->isFeesDiscountSheetTitle($name)
                || $salaryBank->isSalaryBankSheetTitle($name)
                || (str_starts_with($lower, 'bank') && ! str_contains($lower, 'pivot'))) {
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
            'skipped_manually_edited' => 0,
            'skipped_cancelled' => 0,
            'flagged_changed' => 0,
            'rolled_back_cancelled' => 0,
            'superseded_removed' => 0,
            'updated_existing' => 0,
            'fee_head_rows_combined' => 0,
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

        AnnualFeeHead::flush();

        // Warm caches once — avoids thousands of per-row SELECTs.
        // Only load existing receipt/voucher keys that appear in this file (not the whole table).
        // Admission types power Fee Due / Pay Fee: New students get Admission+Registration+Session;
        // Old students are charged Session Fee only (see FeeCalculator::filterBreakdownForAdmissionType).
        $cache = [
            'students' => Student::query()->pluck('id', 'admission_no')->all(),
            'admission_types' => StudentUdiseDetail::query()->pluck('admission_type', 'student_id')->all(),
            // Session each student was admitted in — see admittedInSession().
            'admission_dates' => Student::query()->whereNotNull('admission_date')->pluck('admission_date', 'id')->all(),
            'first_sessions' => $this->firstEnrolledSessions(),
            'fee_heads' => FeeHead::query()->pluck('id', 'name')->all(),
            'sessions' => AcademicSession::query()->get()->keyBy('name'),
            'expense_categories' => ExpenseCategory::query()->pluck('id', 'name')->all(),
            // Existing-row lookups, keyed by receipt/voucher no., populated by warm*Caches()
            // below — each entry is a snapshot of that row's comparable columns (plus 'id')
            // so a re-imported row can be diffed against what's already in the DB, instead of
            // the old "key already seen → skip" check that silently dropped edited rows.
            'receipts' => [],
            'income_vouchers' => [],
            'expense_vouchers' => [],
            // Keys already handled during THIS import run (inserted or matched-for-update) —
            // separate from the DB-sourced lookups above, so a second row in the same file
            // reusing the same receipt/voucher no. is still treated as a plain duplicate
            // (skipped) rather than attempted as an update against a row that only exists in
            // an unflushed buffer and has no real id yet.
            'receipts_seen' => [],
            // receipt no. => [student_id, items, kept]: rows of an earlier per-session split of a
            // receipt that buildFeePayment() has now saved whole (see removeSupersededReceipts()).
            'superseded' => [],
            'income_vouchers_seen' => [],
            'expense_vouchers_seen' => [],
            'bank_accounts' => [],
            'bank_ledger_covered_accounts' => [],
            'user_id' => Auth::guard('erp')->id(),
            'now' => now()->toDateTimeString(),
            // Controlled correction: existing records whose sheet values changed are only
            // listed in 'flagged' unless the school chose to apply corrections.
            'apply_corrections' => $applyCorrections,
            'flagged' => [],
        ];
        $studentsBefore = Student::query()->count();

        // Resolve (find-or-create) every ledger sheet's account BEFORE Income rows are
        // processed, so an income row naming the same account (e.g. PMNT MODE "GAS 11662")
        // never synthesizes its own Deposit — the ledger sheet imported further below already
        // carries that exact deposit as a real "TRANSFER DEPOSIT" line, and recording it twice
        // would double the account's balance.
        foreach ($bankSheets as $bankSheet) {
            $accId = $this->resolveBankAccount($this->bankAccountLabelFromSheetTitle($bankSheet['title']), $cache);
            if ($accId) {
                $cache['bank_ledger_covered_accounts'][$accId] = true;
            }
        }

        $buffers = [
            'fee_payments' => [],
            'incomes' => [],
            'expenses' => [],
            'bank_transactions' => [],
            'updates' => [],
            'failed_rows' => [],
            'failed_logs' => [],
        ];

        $applyResult = function (array $result) use (&$total, &$success, &$failed, &$failedRows, &$breakdown, &$stats, &$buffers) {
            // A combined multi-head fee receipt covers several sheet rows — count every one.
            $rows = max(1, (int) ($result['rows'] ?? 1));
            $total += $rows;
            $reason = $result['reason'] ?? 'other';

            if (($result['skip'] ?? false) === true) {
                if ($reason === 'header_artifact') {
                    $breakdown['skipped_header_artifact'] += $rows;
                } elseif ($reason === 'duplicate') {
                    $breakdown['skipped_duplicate'] += $rows;
                } elseif ($reason === 'old_student_new_only_fee') {
                    $breakdown['skipped_old_student_new_only_fee'] += $rows;
                } elseif ($reason === 'manually_edited') {
                    $breakdown['skipped_manually_edited'] += $rows;
                } elseif ($reason === 'cancelled') {
                    $breakdown['skipped_cancelled'] += $rows;
                } elseif ($reason === 'changed_flagged') {
                    $breakdown['flagged_changed'] += $rows;
                }

                return;
            }

            if ($result['ok'] ?? false) {
                $success += $rows;
                $breakdown['fee_head_rows_combined'] += $rows - 1;

                if (! empty($result['update'])) {
                    // Existing row, re-imported with changed values — queued for a targeted
                    // UPDATE (see flush() below) instead of the bulk INSERT buffers, since it
                    // already has a real id and must not become a duplicate row.
                    $breakdown['updated_existing'] += $rows;
                    if (! empty($result['bucket'])) {
                        $stats[$result['bucket']] = ($stats[$result['bucket']] ?? 0) + 1;
                    }
                    $buffers['updates'][$result['update']][] = ['id' => $result['update_id'], 'data' => $result['data']];
                    foreach ($result['extra_updates'] ?? [] as $table => $updates) {
                        $buffers['updates'][$table] = array_merge($buffers['updates'][$table] ?? [], $updates);
                    }

                    return;
                }

                $breakdown['imported'] += $rows;
                if (! empty($result['bucket'])) {
                    $stats[$result['bucket']] = ($stats[$result['bucket']] ?? 0) + 1;
                }
                if (! empty($result['insert'])) {
                    $buffers[$result['insert']][] = $result['data'];
                }
                foreach ($result['bank_transactions'] ?? [] as $bankTx) {
                    $buffers['bank_transactions'][] = $bankTx;
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
            // Targeted per-row updates for existing fee_payments/incomes/expenses whose sheet
            // values changed since the last import — a handful of rows at most (most re-imports
            // are byte-identical and never reach here), so a plain per-id UPDATE is fine; no
            // bulk-insert buffer applies to these since each already has its own id.
            foreach ($buffers['updates'] as $table => $rows) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row['id'])->update($row['data']);
                }
            }
            $buffers['updates'] = [];
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
            $cache['admission_dates'] = Student::query()->whereNotNull('admission_date')->pluck('admission_date', 'id')->all();
            $cache['first_sessions'] = $this->firstEnrolledSessions();
        }

        // FEES DISCOUNT after Student Master — ADM-DISC / ANN-DISC / TUI DISC / TRA DISC become
        // FeeDiscount rows (Admission / Annual≡Session / Tuition / Transport) that reduce Fee Due.
        $feesDiscountStats = [
            'fees_discount_applied' => 0,
            'fees_discount_cleared' => 0,
            'fees_discount_unchanged' => 0,
            'fees_discount_students' => 0,
            'fees_discount_missing_students' => 0,
            'fees_discount_rows_skipped' => 0,
        ];
        if ($feesDiscountSheet) {
            $feesDiscountStats = $this->importFeesDiscountSheet($feesDiscountSheet, $cache);
            if (! $preview && (
                $feesDiscountStats['fees_discount_applied'] > 0
                || $feesDiscountStats['fees_discount_cleared'] > 0
            )) {
                FeeCache::forget();
            }
        }

        if ($incomeSheet) {
            [$mapped, $junk] = $this->mapRows($incomeSheet, self::INCOME_HEADER_MAP, 'INCOME');
            $ignoredColumns = array_merge($ignoredColumns, $junk);
            $this->warmReceiptCaches($mapped, $cache);
            // Student fee rows are collected per receipt first (a receipt is usually several
            // rows — one per fee head) and saved as one payment each in a second pass.
            $feeReceipts = [];
            $cancelledReceipts = [];
            foreach (array_chunk($mapped, 400) as $chunk) {
                foreach ($chunk as $row) {
                    $result = $this->importIncomeRow($row, $log, $cache);
                    if (isset($result['fee_line'])) {
                        $feeReceipts[$result['fee_line']['group_key']][] = $result['fee_line'];

                        continue;
                    }
                    if (isset($result['cancelled_line'])) {
                        $cancelledReceipts[$result['cancelled_line']['group_key']] ??= $result['cancelled_line'];
                    }
                    $applyResult($result);
                }
                $flush();
            }
            // All fee receipts are written in ONE transaction: a failure part-way leaves no
            // half-saved receipts (re-running the import then starts from a clean state).
            DB::transaction(function () use ($feeReceipts, $cancelledReceipts, &$cache, &$breakdown, $applyResult, $flush) {
                foreach (array_chunk($feeReceipts, 400) as $chunk) {
                    foreach ($chunk as $lines) {
                        $applyResult($this->buildFeePayment($lines, $cache));
                    }
                    $flush();
                }
                // Receipts that are entirely cancelled in the sheet: an earlier import may have saved
                // them as payments — roll those back (never delete), like the Rollback action does.
                $breakdown['rolled_back_cancelled'] += $this->rollBackCancelledReceipts(
                    array_diff_key($cancelledReceipts, $feeReceipts),
                    $cache
                );
                $breakdown['superseded_removed'] += $this->removeSupersededReceipts($cache);
            });

            // Imported payments (same students and sessions as this sheet) that no row of the
            // sheet matched — e.g. a receipt no. later corrected in Excel. Reported, never deleted.
            $studentIds = [];
            $sessionIds = [];
            foreach ($feeReceipts as $lines) {
                foreach ($lines as $line) {
                    $studentIds[(int) $line['student_id']] = true;
                    $sessionIds[(int) $line['session']->id] = true;
                }
            }
            if ($studentIds !== []) {
                $admissionNos = array_flip($cache['students']);
                foreach (array_chunk(array_keys($studentIds), 500) as $chunk) {
                    foreach (DB::table('fee_payments')
                        ->whereIn('student_id', $chunk)
                        ->whereIn('academic_session_id', array_keys($sessionIds))
                        ->where('status', '!=', 'Rolled Back')
                        ->where('items', 'like', '%"source_head"%')
                        ->get(['receipt_no', 'student_id', 'amount', 'payment_date']) as $p) {
                        if (! isset($cache['receipts_seen'][$p->receipt_no])) {
                            $cache['flagged'][] = [
                                'type' => 'fee_payment_not_in_excel',
                                'receipt_no' => $p->receipt_no,
                                'admission_no' => $admissionNos[$p->student_id] ?? null,
                                'date' => substr((string) $p->payment_date, 0, 10),
                                'amount' => round((float) $p->amount, 2),
                                'note' => 'Saved from an earlier import but no row of this sheet matches it — left as it is.',
                            ];
                        }
                    }
                }
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

        // BANK sheets last — order doesn't matter for correctness (account resolution is
        // find-or-create by account number either way), but this keeps ledger imports grouped
        // with the other "extra" sheets below in the summary message.
        $bankStats = ['bank_transactions_imported' => 0, 'bank_rows_skipped' => 0, 'bank_synthetic_deposits_replaced' => 0, 'bank_sheets' => []];
        foreach ($bankSheets as $bankSheet) {
            $result = $this->importBankSheet($bankSheet, $bankSheet['title'], $cache);
            $bankStats['bank_transactions_imported'] += $result['transactions_imported'];
            $bankStats['bank_rows_skipped'] += $result['rows_skipped'];
            $bankStats['bank_synthetic_deposits_replaced'] += $result['synthetic_deposits_replaced'];
            $bankStats['bank_sheets'][] = $bankSheet['title'];
        }

        $stats = array_merge($stats, $transportStats, $feesDiscountStats);

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
        if ($feesDiscountSheet) {
            $stats['fees_discount'] = $feesDiscountStats;
            $message .= ' | Fees Discount ('.$feesDiscountSheet['title'].'): '
                .$feesDiscountStats['fees_discount_applied'].' discount(s) applied, '
                .$feesDiscountStats['fees_discount_cleared'].' cleared, '
                .$feesDiscountStats['fees_discount_students'].' student(s)'
                .($feesDiscountStats['fees_discount_missing_students'] > 0
                    ? ', '.$feesDiscountStats['fees_discount_missing_students'].' admission no.(s) not found'
                    : '')
                .'.';
        }
        if ($salaryBankTitle && $salaryBankResult) {
            $stats['salary_bank'] = $salaryBankResult;
            $message .= ' | Salary Bank ('.$salaryBankTitle.'): '
                .$salaryBankResult['employees_created'].' employee(s) created, '
                .$salaryBankResult['employees_updated'].' updated, '
                .$salaryBankResult['slips_written'].' salary slip(s) written'
                .(count($salaryBankResult['failed']) > 0 ? ', '.count($salaryBankResult['failed']).' row(s) need review' : '')
                .'.';
        }
        if ($bankSheets !== []) {
            $stats['bank'] = $bankStats;
            $message .= ' | Bank ('.implode(', ', $bankStats['bank_sheets']).'): '
                .$bankStats['bank_transactions_imported'].' transaction(s) imported, '
                .$bankStats['bank_rows_skipped'].' row(s) skipped'
                .($bankStats['bank_synthetic_deposits_replaced'] > 0
                    ? ', '.$bankStats['bank_synthetic_deposits_replaced'].' earlier income-derived deposit(s) replaced with ledger data'
                    : '')
                .'.';
        }

        if (! $preview) {
            DashboardCache::forget();
        }

        $flagged = collect($cache['flagged']);
        $studentsCreated = Student::query()->count() - $studentsBefore;
        $report = [
            'fee_payments' => [
                'inserted' => (int) ($stats['income_fee_payments'] ?? 0),
                'updated' => (int) ($stats['income_fee_payments_updated'] ?? 0),
                'changed_flagged' => $flagged->where('type', 'fee_payment_changed')->count(),
                'kept_manual_edits' => $breakdown['skipped_manually_edited'],
                'cancelled_skipped' => $breakdown['skipped_cancelled'],
                'rolled_back' => $breakdown['rolled_back_cancelled'],
                'superseded_removed' => $breakdown['superseded_removed'],
            ],
            'expenses' => [
                'inserted' => (int) ($stats['expenses'] ?? 0),
                'updated' => (int) ($stats['expenses_updated'] ?? 0),
                'changed_flagged' => $flagged->where('type', 'expense_changed')->count(),
                'not_in_excel' => $flagged->where('type', 'expense_not_in_excel')->count(),
            ],
            'students' => $studentMasterResult ? [
                'rows' => $studentMasterResult['total_rows'],
                'created' => $studentsCreated,
                'existing_updated' => max(0, $studentMasterResult['success_count'] - $studentsCreated),
                'rejected' => $studentMasterResult['failed_count'],
            ] : null,
            'transport' => $transportSheet ? [
                'stops_updated' => $transportStats['transport_stops_updated'],
                'stops_unchanged' => (int) ($transportStats['transport_stops_unchanged'] ?? 0),
                'stops_created' => $transportStats['transport_stops_created'],
                'rows_skipped' => $transportStats['transport_rows_skipped'],
            ] : null,
            'fees_discount' => $feesDiscountSheet ? [
                'applied' => $feesDiscountStats['fees_discount_applied'],
                'cleared' => $feesDiscountStats['fees_discount_cleared'],
                'unchanged' => $feesDiscountStats['fees_discount_unchanged'],
                'students' => $feesDiscountStats['fees_discount_students'],
                'missing_students' => $feesDiscountStats['fees_discount_missing_students'],
                'rows_skipped' => $feesDiscountStats['fees_discount_rows_skipped'],
            ] : null,
            'bank' => $bankSheets !== [] ? [
                'inserted' => $bankStats['bank_transactions_imported'],
                'rows_skipped' => $bankStats['bank_rows_skipped'],
            ] : null,
            // INCOME + EXPENSES sheet rows already saved exactly as in the sheet (skipped).
            'rows_unchanged' => $breakdown['skipped_duplicate'],
            'rejected_rows' => $failed,
            'flagged_total' => $flagged->count(),
            'flagged_by_type' => $flagged->countBy('type')->all(),
            'flagged' => $flagged->take(300)->values()->all(),
        ];

        return response()->json([
            'preview' => $preview,
            'corrections_applied' => $applyCorrections,
            'report' => $report,
            'log' => $preview ? null : $log->fresh(),
            'failed_rows' => array_slice($failedRows, 0, 200),
            'stats' => $stats,
            'breakdown' => $breakdown,
            'message' => ($preview ? 'PREVIEW — nothing was saved. ' : '').$message,
        ]);
    }

    private function isFeesDiscountSheetTitle(string $title): bool
    {
        $lower = strtolower(trim($title));

        return $lower === 'fees discount' || str_starts_with($lower, 'fees discount');
    }

    /**
     * Locate the FEES DISCOUNT header row (ADM NO. + at least one *-DISC column). School files
     * sometimes leave row 1 blank or put a title there.
     */
    private function feesDiscountHeaderRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): int
    {
        $highest = min(8, max(1, (int) $sheet->getHighestDataRow()));
        for ($r = 1; $r <= $highest; $r++) {
            $blob = '';
            for ($c = 1; $c <= 30; $c++) {
                $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c).$r;
                $blob .= ' '.strtolower(trim((string) $sheet->getCell($coord)->getValue()));
            }
            $norm = preg_replace('/[\s_\-]+/', ' ', $blob) ?? $blob;
            if ((str_contains($norm, 'adm no') || str_contains($norm, 'admno'))
                && (str_contains($norm, 'adm disc') || str_contains($norm, 'ann disc')
                    || str_contains($norm, 'tui disc') || str_contains($norm, 'tra disc'))) {
                return $r;
            }
        }

        return 1;
    }

    /**
     * FEES DISCOUNT sheet → FeeDiscount rows.
     * ADM-DISC → Admission Fee, ANN-DISC → Annual/Session Fee (same charge), TUI DISC → Tution Fee,
     * TRA DISC → Transport. Only positive amounts are saved; a later re-import with 0/blank
     * clears a previously imported discount for that head (manual discounts with other reasons
     * are left alone).
     *
     * @param  array{header: array<int,string>, rows: array<int, array<int, mixed>>, title?: string}  $sheet
     * @param  array<string, mixed>  $cache
     * @return array{fees_discount_applied: int, fees_discount_cleared: int, fees_discount_unchanged: int, fees_discount_students: int, fees_discount_missing_students: int, fees_discount_rows_skipped: int}
     */
    private function importFeesDiscountSheet(array $sheet, array &$cache): array
    {
        $stats = [
            'fees_discount_applied' => 0,
            'fees_discount_cleared' => 0,
            'fees_discount_unchanged' => 0,
            'fees_discount_students' => 0,
            'fees_discount_missing_students' => 0,
            'fees_discount_rows_skipped' => 0,
        ];

        $admIdx = null;
        /** @var array<string, int> code => column index */
        $discCols = [];
        foreach ($sheet['header'] as $index => $raw) {
            $norm = $this->normalizeFeesDiscountHeader((string) $raw);
            if (in_array($norm, ['adm no', 'adm no.', 'adm. no.', 'admno', 'admission no', 'admission no.'], true)) {
                $admIdx = $index;
            }
            if (isset(self::FEES_DISCOUNT_HEAD_MAP[$norm])) {
                $discCols[self::FEES_DISCOUNT_HEAD_MAP[$norm]] = $index;
            }
        }

        if ($admIdx === null || $discCols === []) {
            $stats['fees_discount_rows_skipped'] = count($sheet['rows']);

            return $stats;
        }

        $currentSession = AcademicSession::query()->where('is_current', true)->first()
            ?? AcademicSession::query()->orderByDesc('start_date')->first();
        $sessionId = $currentSession ? (int) $currentSession->id : null;

        /** @var array<string, int> code => fee_head_id */
        $headIds = [];
        foreach (array_keys($discCols) as $code) {
            $headIds[$code] = $this->resolveFeesDiscountHeadId($code, $sessionId, $cache);
        }

        $touchedStudents = [];
        foreach ($sheet['rows'] as $line) {
            $admissionNo = $this->normalizeAdmissionNoCell($line[$admIdx] ?? null);
            if ($admissionNo === '') {
                $stats['fees_discount_rows_skipped']++;

                continue;
            }

            $studentId = $cache['students'][$admissionNo] ?? null;
            if (! $studentId && is_numeric($admissionNo)) {
                // Excel sometimes stores adm nos as floats ("16696.0") — try the integer form.
                $studentId = $cache['students'][(string) (int) $admissionNo] ?? null;
            }
            if (! $studentId) {
                $stats['fees_discount_missing_students']++;

                continue;
            }

            $hadAny = false;
            foreach ($discCols as $code => $colIdx) {
                $amount = $this->positiveMoney($line[$colIdx] ?? null);
                $headId = $headIds[$code] ?? null;
                if (! $headId) {
                    continue;
                }

                $existing = FeeDiscount::query()
                    ->where('student_id', $studentId)
                    ->where('fee_head_id', $headId)
                    ->where('reason', self::FEES_DISCOUNT_REASON)
                    ->first();

                if ($amount === null) {
                    if ($existing) {
                        $existing->delete();
                        $stats['fees_discount_cleared']++;
                        $hadAny = true;
                    }
                    // Clear any discount we previously stamped onto this student's receipts.
                    $this->stampFeesDiscountOnPayments((int) $studentId, (int) $headId, null);

                    continue;
                }

                $hadAny = true;
                if ($existing) {
                    if ($existing->type === 'fixed' && abs((float) $existing->value - $amount) < 0.005) {
                        $stats['fees_discount_unchanged']++;
                    } else {
                        $existing->update(['type' => 'fixed', 'value' => $amount]);
                        $stats['fees_discount_applied']++;
                    }
                } else {
                    FeeDiscount::create([
                        'student_id' => $studentId,
                        'fee_head_id' => $headId,
                        'type' => 'fixed',
                        'value' => $amount,
                        'reason' => self::FEES_DISCOUNT_REASON,
                    ]);
                    $stats['fees_discount_applied']++;
                }

                // Stamp onto Fee Paid receipt lines so Discount / Final Amount show the concession
                // (structure FeeDiscount alone only reduces due — it does not appear on receipts).
                $this->stampFeesDiscountOnPayments((int) $studentId, (int) $headId, $amount);
            }

            if ($hadAny) {
                $touchedStudents[$studentId] = true;
            }
        }

        $stats['fees_discount_students'] = count($touchedStudents);

        return $stats;
    }

    private function normalizeFeesDiscountHeader(string $raw): string
    {
        $raw = strtolower(trim($raw));
        $raw = str_replace(['_', '-'], ' ', $raw);
        $raw = preg_replace('/\s+/', ' ', $raw) ?? $raw;

        return trim($raw);
    }

    /** @return non-empty-string|null Positive ₹ amount, or null when blank/zero/non-numeric. */
    private function positiveMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $value = str_replace([',', '₹', ' '], '', trim($value));
        }
        if (! is_numeric($value)) {
            return null;
        }
        $amount = round((float) $value, 2);

        return $amount > 0 ? $amount : null;
    }

    private function normalizeAdmissionNoCell(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_numeric($value)) {
            // Avoid "16696.0" from Excel numeric cells.
            $asFloat = (float) $value;
            if (floor($asFloat) == $asFloat) {
                return (string) (int) $asFloat;
            }
        }

        return trim((string) $value);
    }

    /**
     * Put a FEES DISCOUNT amount onto the student's receipt line(s) for that fee head so Fee Paid
     * shows Discount / Final Amount. Cash collected (payment.amount / item.amount) is unchanged —
     * only item.discount is set (tagged discount_source=fees_discount for safe re-import clears).
     */
    private function stampFeesDiscountOnPayments(int $studentId, int $headId, ?float $discountAmount): void
    {
        $payments = FeePayment::query()
            ->where('student_id', $studentId)
            ->where(function ($q) {
                $q->whereNull('status')->orWhereNotIn('status', ['Rolled Back', 'Refunded']);
            })
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get(['id', 'items', 'discount_amount']);

        if ($payments->isEmpty()) {
            return;
        }

        $clear = $discountAmount === null || $discountAmount <= 0;
        $remaining = $clear ? 0.0 : round($discountAmount, 2);

        foreach ($payments as $payment) {
            $items = is_array($payment->items) ? $payment->items : [];
            if ($items === []) {
                continue;
            }
            $changed = false;
            foreach ($items as $i => $item) {
                if ((int) ($item['fee_head_id'] ?? 0) !== $headId) {
                    continue;
                }
                $wasOurs = ($item['discount_source'] ?? null) === 'fees_discount';
                $currentDisc = (float) ($item['discount'] ?? 0);

                if ($clear) {
                    if ($wasOurs) {
                        $items[$i]['discount'] = 0;
                        unset($items[$i]['discount_source']);
                        $changed = true;
                    }

                    continue;
                }

                // Keep operator-entered discounts; only fill blank lines or refresh our own stamp.
                if ($currentDisc > 0.005 && ! $wasOurs) {
                    continue;
                }

                if ($remaining > 0.005) {
                    $items[$i]['discount'] = $remaining;
                    $items[$i]['discount_source'] = 'fees_discount';
                    $remaining = 0.0;
                    $changed = true;
                } elseif ($wasOurs) {
                    $items[$i]['discount'] = 0;
                    unset($items[$i]['discount_source']);
                    $changed = true;
                }
            }

            if (! $changed) {
                continue;
            }

            $sumDisc = round(array_sum(array_map(
                static fn ($it) => (float) ($it['discount'] ?? 0),
                $items
            )), 2);

            $payment->update([
                'items' => array_values($items),
                'discount_amount' => $sumDisc,
            ]);
        }
    }

    /**
     * Resolve (find-or-create) the FeeHead for a FEES DISCOUNT column code.
     * ANN-DISC uses AnnualFeeHead — Annual Fee and Session Fee are the same yearly charge;
     * whichever label this session's structure uses is where the discount lands (same as INCOME ANN).
     *
     * @param  array<string, mixed>  $cache
     */
    private function resolveFeesDiscountHeadId(string $code, ?int $sessionId, array &$cache): int
    {
        $headName = self::INCOME_HEAD_CANONICAL_MAP[$code] ?? strtoupper($code);
        if ($code === 'ann') {
            $headName = AnnualFeeHead::nameFor($sessionId);
        }

        if (! isset($cache['fee_heads'][$headName])) {
            // Prefer an existing head with a close label before creating a new one —
            // Fee Due matches payments/charges by fee_head_id.
            $existing = FeeHead::query()
                ->whereRaw('LOWER(name) = ?', [strtolower($headName)])
                ->first();
            if (! $existing && $code === 'ann') {
                // Annual Fee ≡ Session Fee — accept either label if the exact name is missing.
                $existing = FeeHead::query()
                    ->where(function ($q) {
                        $q->where('name', 'like', '%Annual%')
                            ->orWhere('name', 'like', '%Session%');
                    })
                    ->orderByRaw("CASE WHEN name LIKE '%Session%' THEN 0 WHEN name LIKE '%Annual%' THEN 1 ELSE 2 END")
                    ->orderBy('id')
                    ->first();
            }
            if (! $existing && $code === 'tui') {
                $existing = FeeHead::query()
                    ->where(function ($q) {
                        $q->where('name', 'like', '%Tution%')
                            ->orWhere('name', 'like', '%Tuition%');
                    })
                    ->orderBy('id')
                    ->first();
            }
            if (! $existing && $code === 'tra') {
                $existing = FeeHead::query()
                    ->where('name', 'like', '%Transport%')
                    ->orderBy('id')
                    ->first();
            }
            if ($existing) {
                $cache['fee_heads'][$headName] = (int) $existing->id;
                $cache['fee_heads'][$existing->name] = (int) $existing->id;
            } else {
                $head = FeeHead::create(['name' => $headName, 'description' => 'Imported fee head']);
                $cache['fee_heads'][$headName] = (int) $head->id;
            }
        }

        return (int) $cache['fee_heads'][$headName];
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
                    $stats['transport_stops_updated']++;
                } else {
                    $stats['transport_stops_unchanged'] = ($stats['transport_stops_unchanged'] ?? 0) + 1;
                }

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

    /**
     * "BANK IDBI 11662 26-27" / "BANK IDBI TRUST 6739 26-27" style ledger sheet:
     * YEAR | MON | DATE | ITEM | CHQ NO | DR/CR | AMOUNT | TOTAL (running-balance formula,
     * never read) | CATEGORY. The account itself is resolved from the sheet title (stripping
     * the leading "BANK " and trailing year range leaves e.g. "IDBI 11662"), reusing
     * {@see resolveBankAccount()} so a ledger row lands on the exact same BankAccount an
     * INCOME/EXPENSES row's PMNT MODE cell would already have created/matched.
     *
     * The OPENING BALANCE row (DR/CR blank or 0) is never a movement — it sets the account's
     * opening_balance, and only on the very first import for that account (a later fiscal
     * year's sheet restates the prior year's closing balance as its own "opening" row, which
     * is already reflected by that prior year's transactions, so it must not overwrite).
     *
     * A one-time cleanup runs first: any earlier income import (before this account had a
     * ledger sheet, or from before this codepath existed) may have left synthetic "Imported
     * fee payment — …" Deposit rows for this exact account — money the ledger's own CR rows
     * are about to re-supply properly. Those synthetic rows are deleted so the account isn't
     * left with the same deposit counted twice.
     *
     * @param  array{header: array<int,string>, rows: array<int, array<int, mixed>>, title?: string}  $sheet
     * @param  array<string, mixed>  $cache
     * @return array{transactions_imported: int, rows_skipped: int, synthetic_deposits_replaced: int}
     */
    private function importBankSheet(array $sheet, string $sheetTitle, array &$cache): array
    {
        $stats = ['transactions_imported' => 0, 'rows_skipped' => 0, 'synthetic_deposits_replaced' => 0];

        $accountLabel = $this->bankAccountLabelFromSheetTitle($sheetTitle);
        $bankAccountId = $this->resolveBankAccount($accountLabel, $cache);
        if (! $bankAccountId) {
            $stats['rows_skipped'] = count($sheet['rows']);

            return $stats;
        }

        // Delete-then-rebuild for this one account runs inside a transaction — if anything
        // fails partway (e.g. a schema mismatch), the account is left exactly as it was found
        // rather than with its synthetic deposits deleted but the real ledger never inserted.
        DB::transaction(function () use ($sheet, $bankAccountId, $cache, &$stats) {
            $stats['synthetic_deposits_replaced'] = BankTransaction::where('bank_account_id', $bankAccountId)
                ->where('remarks', 'like', 'Imported fee payment — %')
                ->delete();

            $isFreshAccount = ! BankTransaction::where('bank_account_id', $bankAccountId)->exists();

            // Dedup against whatever this account already has, so re-importing the same
            // workbook (or a later year's sheet repeating earlier months) never doubles a row.
            // Counted, not just flagged: two genuine identical transactions in the sheet (same
            // day, amount and item) stay two — only rows beyond what's already saved are added.
            $saved = [];
            foreach (BankTransaction::where('bank_account_id', $bankAccountId)->get(['date', 'type', 'amount', 'reference_no', 'remarks']) as $existing) {
                $key = $this->bankTxKey($existing->date->toDateString(), $existing->type, (float) $existing->amount, $existing->reference_no, $existing->remarks);
                $saved[$key] = ($saved[$key] ?? 0) + 1;
            }
            $inSheet = [];

            $now = $cache['now'];
            $buffer = [];
            $flushBuffer = function () use (&$buffer) {
                if ($buffer === []) {
                    return;
                }
                foreach (array_chunk($buffer, 250) as $chunk) {
                    DB::table('bank_transactions')->insert($chunk);
                }
                $buffer = [];
            };

            foreach ($sheet['rows'] as $row) {
                $item = trim((string) ($row[3] ?? ''));
                $drCr = strtoupper(trim((string) ($row[5] ?? '')));
                $amountRaw = $row[6] ?? null;
                $amount = is_numeric($amountRaw) ? (float) $amountRaw : null;

                if ($item === '' && $amount === null) {
                    continue; // blank trailing row past the real data
                }

                $date = ExcelDateParser::parse($row[2] ?? null);
                if (! $date) {
                    $stats['rows_skipped']++;

                    continue;
                }

                $isOpeningRow = $drCr === '' || $drCr === '0' || strtoupper($item) === 'OPENING BALANCE';
                if ($isOpeningRow) {
                    if ($isFreshAccount && $amount !== null) {
                        BankAccount::where('id', $bankAccountId)->update(['opening_balance' => round($amount, 2)]);
                        $isFreshAccount = false; // only the sheet's own first opening row counts
                    }

                    continue;
                }

                if ($amount === null || $amount == 0.0 || ! in_array($drCr, ['CR', 'DR'], true)) {
                    $stats['rows_skipped']++;

                    continue;
                }

                $type = $drCr === 'CR' ? 'Deposit' : 'Withdrawal';
                $chqNo = trim((string) ($row[4] ?? ''));
                $referenceNo = ($chqNo !== '' && $chqNo !== '0') ? $chqNo : null;
                $category = trim((string) ($row[8] ?? ''));
                $remarksParts = array_filter([
                    $item !== '' ? $item : null,
                    ($category !== '' && $category !== $item) ? $category : null,
                ]);
                $remarks = $remarksParts !== [] ? implode(' | ', $remarksParts) : null;
                $roundedAmount = round(abs($amount), 2);

                $key = $this->bankTxKey($date, $type, $roundedAmount, $referenceNo, $remarks);
                $inSheet[$key] = ($inSheet[$key] ?? 0) + 1;
                if ($inSheet[$key] <= ($saved[$key] ?? 0)) {
                    continue;
                }

                $buffer[] = [
                    'bank_account_id' => $bankAccountId,
                    'type' => $type,
                    'amount' => $roundedAmount,
                    'date' => $date,
                    'reference_no' => $referenceNo,
                    'item' => $item !== '' ? $item : null,
                    'category' => $category !== '' ? $category : null,
                    'remarks' => $remarks,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $stats['transactions_imported']++;

                if (count($buffer) >= 500) {
                    $flushBuffer();
                }
            }

            $flushBuffer();
        });

        return $stats;
    }

    private function bankTxKey(string $date, string $type, float $amount, ?string $referenceNo, ?string $remarks): string
    {
        return $date.'|'.$type.'|'.number_format($amount, 2, '.', '').'|'.($referenceNo ?? '').'|'.($remarks ?? '');
    }

    /** "BANK IDBI 11662 26-27" → "IDBI 11662" — same shape resolveBankAccount() parses from a PMNT MODE cell. */
    private function bankAccountLabelFromSheetTitle(string $title): string
    {
        $label = trim($title);
        $label = preg_replace('/^bank\s+/i', '', $label) ?? $label;
        $label = preg_replace('/\s+\d{2}-\d{2}$/', '', $label) ?? $label;

        return trim($label);
    }

    /** @param  array<string, int>  $breakdown */
    private function formatSummaryMessage(array $breakdown): string
    {
        $parts = [
            'Total rows: '.$breakdown['total_rows'],
            'Imported: '.$breakdown['imported'],
            'Skipped (header artifact): '.$breakdown['skipped_header_artifact'],
        ];
        if (($breakdown['updated_existing'] ?? 0) > 0) {
            $parts[] = 'Updated (existing record changed): '.$breakdown['updated_existing'];
        }
        if (($breakdown['fee_head_rows_combined'] ?? 0) > 0) {
            $parts[] = 'Fee-head rows combined into their receipt: '.$breakdown['fee_head_rows_combined'];
        }
        if ($breakdown['skipped_duplicate'] > 0) {
            $parts[] = 'Skipped (duplicate): '.$breakdown['skipped_duplicate'];
        }
        if (($breakdown['skipped_old_student_new_only_fee'] ?? 0) > 0) {
            $parts[] = 'Skipped (Old student ADM/REG): '.$breakdown['skipped_old_student_new_only_fee'];
        }
        if (($breakdown['skipped_manually_edited'] ?? 0) > 0) {
            $parts[] = 'Skipped (already edited/refunded/reviewed in app): '.$breakdown['skipped_manually_edited'];
        }
        if (($breakdown['skipped_cancelled'] ?? 0) > 0) {
            $parts[] = 'Skipped (PMNT MODE: CANCEL): '.$breakdown['skipped_cancelled'];
        }
        if (($breakdown['rolled_back_cancelled'] ?? 0) > 0) {
            $parts[] = 'Earlier-imported cancelled receipts rolled back: '.$breakdown['rolled_back_cancelled'];
        }
        if (($breakdown['superseded_removed'] ?? 0) > 0) {
            $parts[] = 'Split receipts merged into one (old duplicate parts removed): '.$breakdown['superseded_removed'];
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
        $rowNumber = (int) ($sheet['header_row'] ?? 1); // header consumed; first data row is the next Excel row
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

    /** First of rows 1–5 holding both ADM NO and NAME headers (row 1 when none does). */
    private function incomeHeaderRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): int
    {
        $lastCol = $sheet->getHighestDataColumn();
        for ($row = 1; $row <= min(5, $sheet->getHighestDataRow()); $row++) {
            $cells = $sheet->rangeToArray("A{$row}:{$lastCol}{$row}", null, false, false, false)[0] ?? [];
            $labels = array_map(fn ($v) => preg_replace('/\s+/', ' ', strtolower(trim((string) $v))), $cells);
            $hasAdm = (bool) array_intersect($labels, ['adm no', 'adm no.', 'adm. no.', 'admno']);
            if ($hasAdm && in_array('name', $labels, true)) {
                return $row;
            }
        }

        return 1;
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
     * Turns every INCOME line of one receipt (same session + student + RECEIPT no.) into ONE
     * fee payment: one item per fee-head row, fines summed into fine_amount, amount = items +
     * fine — exactly what Pay Fee saves for a multi-head receipt. Each returned result carries
     * 'rows' so the import summary still counts sheet rows.
     *
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $cache
     */
    private function buildFeePayment(array $lines, array &$cache): array
    {
        $first = $lines[0];
        $rows = count($lines);
        $now = $cache['now'];

        $items = [];
        $amount = 0.0;
        $fine = 0.0;
        $finesBySession = [];
        $sessions = [];
        $remarks = [];
        foreach ($lines as $line) {
            array_push($items, ...$line['items']);
            $amount += $line['amount'];
            $fine += $line['fine_amount'];
            $sid = (int) $line['session']->id;
            $sessions[$sid] = $line['session'];
            if ($line['fine_amount'] > 0) {
                $finesBySession[$sid] = round(($finesBySession[$sid] ?? 0) + $line['fine_amount'], 2);
            }
            foreach (explode(' | ', (string) $line['remarks']) as $piece) {
                if (trim($piece) !== '') {
                    $remarks[$piece] = true;
                }
            }
        }
        $remarksText = $remarks ? mb_substr(implode(' | ', array_keys($remarks)), 0, 255) : null;

        // The receipt's own session: the one its payment date falls in when its rows include it
        // (receipt 254 on 02-04-2025 → 2025-26), else the latest session among its rows.
        $multiSession = count($sessions) > 1;
        $primary = $first['session'];
        if ($multiSession) {
            $byDate = $this->sessionNameFor('', $first['date']);
            $primary = collect($sessions)->first(fn ($s) => $s->name === $byDate)
                ?? collect($sessions)->sortByDesc('name')->first();
        }

        [$receipt, $existing] = $this->claimReceiptNo(['session' => $primary] + $first, $cache);

        // Earlier imports split this receipt per session ("254" + "254/25-26"): the parts not
        // reused above are now duplicates of lines in this receipt — removed after all receipts
        // are matched (see removeSupersededReceipts()).
        if ($first['receipt_raw'] !== '') {
            foreach ($sessions as $s) {
                foreach ($this->receiptCandidates($first['receipt_raw'], $first['synthetic_receipt'], (string) $s->name, $first['admission_no']) as $candidate) {
                    if ($candidate !== $receipt && isset($cache['receipts'][$candidate])) {
                        $cache['superseded'][$candidate] = ['student_id' => (int) $first['student_id'], 'items' => $items, 'fine' => $fine, 'kept' => $receipt];
                    }
                }
            }
        }

        $newData = [
            'receipt_no' => $receipt,
            'student_id' => $first['student_id'],
            'academic_session_id' => $primary->id,
            'multi_session' => $multiSession ? 1 : 0,
            'items' => json_encode($items, JSON_THROW_ON_ERROR),
            'amount' => round($amount, 2),
            'discount_amount' => 0,
            'fine_amount' => round($fine, 2),
            'session_fines' => $multiSession && $finesBySession !== [] ? json_encode($finesBySession, JSON_THROW_ON_ERROR) : null,
            'payment_mode' => $this->normalizeFeeMode($first['mode']),
            'payment_date' => $first['date'],
            'remarks' => $remarksText,
        ];

        // fee_payments has no bank_account_id column, so the link to the account is a
        // BankTransaction (Deposit) instead — BankAccount::currentBalance() already sums
        // these, the same mechanism a manually-entered deposit uses. Skipped entirely
        // when this account has its own authoritative BANK ledger sheet in this same
        // workbook — that sheet's own "TRANSFER DEPOSIT" row already covers this money.
        $bankAmounts = [];
        foreach ($lines as $line) {
            $acc = $line['bank_account_id'];
            if ($acc && empty($cache['bank_ledger_covered_accounts'][$acc])) {
                $bankAmounts[$acc] = ($bankAmounts[$acc] ?? 0) + $line['amount'];
            }
        }

        if ($existing) {
            // A payment the school has since edited, refunded, or rolled back in the app
            // (see FeePaymentController) has diverged from the sheet on purpose — a stale
            // re-import must not silently overwrite that manual decision.
            if ($existing['status'] !== 'Paid' || (float) $existing['refunded_amount'] > 0
                || $existing['edited_at'] !== null || $existing['rolled_back_at'] !== null) {
                return ['ok' => true, 'skip' => true, 'reason' => 'manually_edited', 'rows' => $rows];
            }

            $changed = $this->diffForUpdate($existing, $newData, ['amount', 'fine_amount', 'discount_amount', 'multi_session'], ['items', 'session_fines']);
            if ($changed === []) {
                return ['ok' => true, 'skip' => true, 'reason' => 'duplicate', 'rows' => $rows];
            }

            // The sheet now says something different about a payment already saved. Never
            // overwrite it silently: list it for review unless corrections were approved.
            if (! $cache['apply_corrections']) {
                $describe = fn (array $data) => [
                    'amount' => round((float) ($data['amount'] ?? 0), 2),
                    'fine' => round((float) ($data['fine_amount'] ?? 0), 2),
                    'date' => substr((string) ($data['payment_date'] ?? ''), 0, 10),
                    'mode' => $data['payment_mode'] ?? null,
                    'lines' => collect(is_string($data['items'] ?? null) ? json_decode($data['items'], true) : ($data['items'] ?? []))
                        ->map(fn ($i) => trim(($i['source_head'] ?? $i['fee_head_name'] ?? '').' '.round((float) ($i['amount'] ?? 0), 2)
                            .' '.implode(',', (array) ($i['months'] ?? [])).' '.($i['session'] ?? '')))
                        ->values()->all(),
                ];
                $cache['flagged'][] = [
                    'type' => 'fee_payment_changed',
                    'receipt_no' => $receipt,
                    'admission_no' => $first['admission_no'],
                    'name' => $first['name'],
                    'fields' => array_keys(array_diff_key($changed, ['remarks' => true])) ?: ['remarks'],
                    'saved' => $describe($existing),
                    'excel' => $describe($newData),
                ];

                return ['ok' => true, 'skip' => true, 'reason' => 'changed_flagged', 'rows' => $rows];
            }

            // Approved correction: keep the before/after on the payment's audit trail.
            FeePaymentAudit::create([
                'fee_payment_id' => $existing['id'],
                'action' => 'Edit',
                'before' => array_intersect_key($existing, $changed),
                'after' => $changed,
                'reason' => 'Corrected from re-imported workbook (approved correction)',
                'performed_by_id' => $cache['user_id'],
            ]);

            // Keep an import-created deposit for this receipt in step with the corrected total.
            $depositUpdates = [];
            foreach ($bankAmounts as $acc => $bankAmount) {
                $depositId = DB::table('bank_transactions')
                    ->where('bank_account_id', $acc)
                    ->where('type', 'Deposit')
                    ->where('reference_no', $receipt)
                    ->where('remarks', 'like', 'Imported fee payment%')
                    ->value('id');
                if ($depositId) {
                    $depositUpdates[] = ['id' => $depositId, 'data' => ['amount' => round($bankAmount, 2), 'updated_at' => $now]];
                }
            }

            return [
                'ok' => true,
                'bucket' => 'income_fee_payments_updated',
                'update' => 'fee_payments',
                'update_id' => $existing['id'],
                'data' => array_merge($changed, ['updated_at' => $now]),
                'extra_updates' => $depositUpdates ? ['bank_transactions' => $depositUpdates] : [],
                'rows' => $rows,
            ];
        }

        $bankTxs = [];
        foreach ($bankAmounts as $acc => $bankAmount) {
            $bankTxs[] = [
                'bank_account_id' => $acc,
                'type' => 'Deposit',
                'amount' => round($bankAmount, 2),
                'date' => $first['date'],
                'reference_no' => $receipt,
                'remarks' => 'Imported fee payment — '.($first['name'] !== '' ? $first['name'] : $first['admission_no']),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return [
            'ok' => true,
            'bucket' => 'income_fee_payments',
            'insert' => 'fee_payments',
            'data' => array_merge($newData, [
                'status' => 'Paid',
                'refunded_amount' => 0,
                'collected_by_id' => $cache['user_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            'bank_transactions' => $bankTxs,
            'rows' => $rows,
        ];
    }

    /**
     * Receipt numbers restart every year, so the same RECEIPT can belong to different sessions
     * (or, by mistake, to two students). receipt_no is unique, so each payment claims the first
     * candidate that is free or already holds THIS payment (same session + student):
     *   "1898" → "1898/26-27" → "1898/26-27/<adm no>".
     * Another payment's row is never matched, overwritten or merged into. Deterministic, so a
     * re-import finds the same row again instead of inserting a duplicate.
     *
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $cache
     * @return array{0: string, 1: array<string, mixed>|null}
     */
    private function claimReceiptNo(array $line, array &$cache): array
    {
        $sessionId = (int) $line['session']->id;
        $studentId = (int) $line['student_id'];
        $candidates = $this->receiptCandidates(
            $line['receipt_raw'],
            $line['synthetic_receipt'],
            (string) $line['session']->name,
            $line['admission_no']
        );

        // 1st: a row this receipt already has (same session + student) — so a re-import keeps the
        // same receipt no. instead of moving to a number that has since become free.
        foreach ($candidates as $candidate) {
            $existing = $cache['receipts'][$candidate] ?? null;
            if ($existing && ! isset($cache['receipts_seen'][$candidate])
                && (int) $existing['academic_session_id'] === $sessionId && (int) $existing['student_id'] === $studentId) {
                $cache['receipts_seen'][$candidate] = true;

                return [$candidate, $existing];
            }
        }
        // 2nd: the first number not used by any other payment.
        foreach ($candidates as $candidate) {
            if (isset($cache['receipts_seen'][$candidate]) || isset($cache['receipts'][$candidate])) {
                continue;
            }
            $cache['receipts_seen'][$candidate] = true;

            return [$candidate, null];
        }

        // Every candidate taken (pathological sheet) — still keep the money, under a row-unique no.
        $fallback = end($candidates).'-'.$line['row_number'];
        $cache['receipts_seen'][$fallback] = true;

        return [$fallback, $cache['receipts'][$fallback] ?? null];
    }

    /** @return list<string> */
    private function receiptCandidates(string $raw, string $synthetic, string $sessionName, string $admissionNo): array
    {
        if ($raw === '') {
            return [$synthetic];
        }
        $short = preg_match('/^\d{2}(\d{2})-\d{2}(\d{2})$/', $sessionName, $m) ? $m[1].'-'.$m[2] : $sessionName;

        return [$raw, "{$raw}/{$short}", "{$raw}/{$short}/{$admissionNo}"];
    }

    /** @return array<int, string> student_id => earliest session in Student Master history ("2025-2026"). */
    private function firstEnrolledSessions(): array
    {
        $first = [];
        foreach (DB::table('student_session_history')->get(['student_id', 'session']) as $h) {
            $name = $this->sessionNameFor((string) $h->session, '');
            if (preg_match('/^\d{4}-\d{4}$/', $name) && (! isset($first[$h->student_id]) || $name < $first[$h->student_id])) {
                $first[$h->student_id] = $name;
            }
        }

        return $first;
    }

    /**
     * Was the student admitted in $sessionName? The session containing their ADM DATE — or,
     * for an advance admission taken Oct–Mar for the coming year (ADM DATE 12-03-2025, first
     * Student Master session 2025-26), that coming session. Unknown → false (rule unchanged).
     *
     * @param  array<string, mixed>  $cache
     */
    private function admittedInSession(int $studentId, string $sessionName, array $cache): bool
    {
        $firstSession = $cache['first_sessions'][$studentId] ?? null;
        $admissionDate = $cache['admission_dates'][$studentId] ?? null;
        if (! $admissionDate) {
            return $firstSession !== null && $firstSession === $sessionName;
        }

        $date = substr((string) $admissionDate, 0, 10);
        $byDate = $this->sessionNameFor('', $date);
        $month = (int) substr($date, 5, 2);
        $start = (int) substr($byDate, 0, 4);
        $nextSession = ($start + 1).'-'.($start + 2);
        if ($firstSession === $nextSession && ($month >= 10 || $month <= 3)) {
            return $sessionName === $nextSession;
        }

        return $sessionName === $byDate;
    }

    /**
     * Removes the leftover parts of receipts an earlier import split per academic session (e.g.
     * "254" holding only last session's February tuition, beside "254/25-26"), now that the whole
     * receipt is saved as one payment. Deleted only when ALL of these hold — otherwise kept:
     *  - same student, and not claimed as a receipt by any row of this import;
     *  - untouched in the app (Paid, no refund, never edited or rolled back);
     *  - every one of its items (fee head + amount + months), and its fine, is contained in the
     *    receipt that now holds that money.
     * Runs inside the import transaction; an import-created bank deposit for it goes with it.
     *
     * @param  array<string, mixed>  $cache
     */
    private function removeSupersededReceipts(array &$cache): int
    {
        $sig = fn (array $item) => ((int) ($item['fee_head_id'] ?? 0)).'|'.round((float) ($item['amount'] ?? 0), 2).'|'
            .implode(',', (array) ($item['months'] ?? array_filter([$item['month'] ?? null])));

        $removed = 0;
        foreach ($cache['superseded'] as $receiptNo => $info) {
            $existing = $cache['receipts'][$receiptNo] ?? null;
            if (! $existing || isset($cache['receipts_seen'][$receiptNo])
                || (int) $existing['student_id'] !== $info['student_id']
                || $existing['status'] !== 'Paid' || (float) $existing['refunded_amount'] > 0
                || $existing['edited_at'] !== null || $existing['rolled_back_at'] !== null) {
                continue;
            }

            $available = array_count_values(array_map($sig, array_filter($info['items'], fn ($i) => ! empty($i['fee_head_id']))));
            $contained = true;
            foreach ((array) json_decode((string) $existing['items'], true) as $item) {
                $key = $sig((array) $item);
                if (($available[$key] ?? 0) < 1) {
                    $contained = false;
                    break;
                }
                $available[$key]--;
            }
            if (! $contained || round((float) $existing['fine_amount'], 2) > round((float) $info['fine'], 2)) {
                continue;
            }

            // Removing a saved payment is a correction — only with the school's approval.
            if (! $cache['apply_corrections']) {
                $cache['flagged'][] = [
                    'type' => 'fee_payment_superseded',
                    'receipt_no' => $receiptNo,
                    'kept_receipt_no' => $info['kept'],
                    'amount' => round((float) $existing['amount'], 2),
                    'note' => "Its lines are now saved under receipt {$info['kept']}; this older copy would be removed.",
                ];

                continue;
            }

            DB::table('bank_transactions')
                ->where('reference_no', $receiptNo)
                ->where('type', 'Deposit')
                ->where('remarks', 'like', 'Imported fee payment%')
                ->delete();
            DB::table('fee_payments')->where('id', $existing['id'])->delete();
            $cache['receipts_seen'][$receiptNo] = true;
            $removed++;
        }

        return $removed;
    }

    /**
     * Rolls back (never deletes) payments an earlier import saved for receipts the sheet marks
     * cancelled — same fields and audit trail as FeePaymentController::rollback(). Payments the
     * school has edited, refunded or rolled back are left alone.
     *
     * @param  array<string, array<string, mixed>>  $lines  one cancelled line per receipt
     * @param  array<string, mixed>  $cache
     */
    private function rollBackCancelledReceipts(array $lines, array &$cache): int
    {
        $count = 0;
        foreach ($lines as $line) {
            $candidates = $this->receiptCandidates(
                $line['receipt_raw'],
                $line['synthetic_receipt'],
                (string) $line['session']->name,
                $line['admission_no']
            );
            foreach ($candidates as $candidate) {
                $existing = $cache['receipts'][$candidate] ?? null;
                if (! $existing || isset($cache['receipts_seen'][$candidate])
                    || (int) $existing['academic_session_id'] !== (int) $line['session']->id
                    || (int) $existing['student_id'] !== (int) $line['student_id']) {
                    continue;
                }
                $cache['receipts_seen'][$candidate] = true;
                if ($existing['status'] !== 'Paid' || (float) $existing['refunded_amount'] > 0
                    || $existing['edited_at'] !== null || $existing['rolled_back_at'] !== null) {
                    break;
                }

                $payment = FeePayment::query()->find($existing['id']);
                if (! $payment) {
                    break;
                }
                if (! $cache['apply_corrections']) {
                    $cache['flagged'][] = [
                        'type' => 'fee_payment_cancelled',
                        'receipt_no' => $candidate,
                        'admission_no' => $line['admission_no'],
                        'amount' => round((float) $payment->amount, 2),
                        'note' => 'Marked CANCEL in the sheet; this saved payment would be rolled back.',
                    ];
                    break;
                }
                $reason = 'Cancelled in imported workbook (PMNT MODE: CANCEL)';
                DB::transaction(function () use ($payment, $reason, $cache) {
                    $before = $payment->only(['status', 'refunded_amount', 'refund_reason']);
                    $payment->update([
                        'refunded_amount' => $payment->amount,
                        'refund_reason' => $reason,
                        'refunded_at' => now(),
                        'status' => 'Rolled Back',
                        'rollback_reason' => $reason,
                        'rolled_back_at' => now(),
                        'rolled_back_by_id' => $cache['user_id'],
                    ]);
                    FeePaymentAudit::create([
                        'fee_payment_id' => $payment->id,
                        'action' => 'Rollback',
                        'before' => $before,
                        'after' => $payment->only(['status', 'refunded_amount', 'refund_reason']),
                        'reason' => $reason,
                        'performed_by_id' => $cache['user_id'],
                    ]);
                });
                $count++;
                break;
            }
        }

        return $count;
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

                // One actual receipt = same RECEIPT no. + student + date (see buildFeePayment()). Its
                // rows may belong to different academic sessions (last session's February tuition
                // paid with this session's Session Fee) — each item keeps its own session.
                $groupKey = $studentId.'|'.$date.'|'.($receiptNo !== '' ? 'R:'.$receiptNo : 'S:'.$row['row_number']);

                // PMNT MODE "CANCEL" marks a voided receipt — not money received. Not imported;
                // an earlier import of it is rolled back by rollBackCancelledReceipts().
                if (str_contains(strtoupper((string) ($row['mode'] ?? '')), 'CANCEL')) {
                    return [
                        'ok' => true,
                        'skip' => true,
                        'reason' => 'cancelled',
                        'cancelled_line' => [
                            'group_key' => $groupKey,
                            'receipt_raw' => $receiptNo,
                            'synthetic_receipt' => sprintf('IMP-INC-%s-%s', $date, $row['row_number']),
                            'student_id' => $studentId,
                            'admission_no' => $admissionNo,
                            'session' => $session,
                        ],
                    ];
                }

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
                    // ANN lands on whatever head this session's fee structure charges once a
                    // year ("Annual Fee" in 2026-27) — "Session Fee" when none is configured.
                    if ($headCode === 'ann') {
                        $headName = AnnualFeeHead::nameFor((int) $session->id);
                    }

                    // REMARKS "RTE AMOUNT" = money received for an RTE-admitted student, typed under
                    // a fee-head column (e.g. REG) — kept as its own "RTE Amount" head so it is
                    // neither lost nor booked as that student's Registration/Admission fee.
                    $isRteAmount = (bool) preg_match('/^\s*RTE\s*AMOUNT\s*$/i', (string) ($row['remarks'] ?? ''));
                    if ($isRteAmount) {
                        $headName = 'RTE Amount';
                    }

                    // Old students are not charged Admission / Registration — skip those income
                    // lines so Fee Due / Pay Fee only expect Session Fee (and other applicable heads).
                    // ADM TYPE is the student's status TODAY: a student admitted in 2025-26 is "Old"
                    // now, yet their 2025-26 admission/registration receipt was a genuine payment —
                    // only skip when the payment is NOT in the session the student was admitted in.
                    $admissionType = strtolower(trim((string) ($cache['admission_types'][$studentId] ?? 'New')));
                    if ($admissionType === 'old' && in_array(strtolower($headName), self::NEW_ONLY_INCOME_HEADS, true)
                        && ! $this->admittedInSession($studentId, (string) $session->name, $cache)) {
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

                    // Each fee entry keeps its own source details: academic session (a receipt
                    // can pay two sessions), class, the sheet's receipt no. and head code, remarks.
                    $items[] = array_filter([
                        'fee_head_id' => $headId,
                        'fee_head_name' => $headName,
                        'amount' => $amount,
                        'months' => $monthKeys,
                        'month' => $monthKeys[0],
                        'academic_session_id' => (int) $session->id,
                        'session' => (string) $session->name,
                        'class' => trim((string) ($row['class'] ?? '')) ?: null,
                        'source_receipt' => $receiptNo !== '' ? $receiptNo : null,
                        'source_head' => $headRaw,
                        'remarks' => $this->looksLikeFormula(trim((string) ($row['remarks'] ?? ''))) ? null : (trim((string) ($row['remarks'] ?? '')) ?: null),
                    ], fn ($v) => $v !== null);
                }

                // One sheet row = one fee head of a receipt. Rows sharing RECEIPT (same student and
                // date) are combined into a single multi-head payment by buildFeePayment(), the same
                // shape the Pay Fee screen saves — a repeated receipt no. is NOT a duplicate.
                return [
                    'ok' => true,
                    'fee_line' => [
                        'group_key' => $groupKey,
                        'receipt_raw' => $receiptNo,
                        'synthetic_receipt' => sprintf('IMP-INC-%s-%s', $date, $row['row_number']),
                        'student_id' => $studentId,
                        'admission_no' => $admissionNo,
                        'name' => $name,
                        'session' => $session,
                        'items' => $items,
                        'amount' => $amount,
                        'fine_amount' => $fineAmount,
                        'mode' => (string) ($row['mode'] ?? 'Cash'),
                        'date' => $date,
                        'remarks' => $this->composeIncomeRemarks($row),
                        'bank_account_id' => $bankAccountId,
                        'row_number' => $row['row_number'],
                    ],
                ];
            }

            $voucher = $receiptNo !== '' ? 'INC-'.$receiptNo : sprintf('INC-IMP-%s-%04d', substr($date, 0, 4), $row['row_number']);
            if (isset($cache['income_vouchers_seen'][$voucher])) {
                return ['ok' => true, 'skip' => true, 'reason' => 'duplicate'];
            }
            $cache['income_vouchers_seen'][$voucher] = true;
            $source = trim((string) ($row['head'] ?? '')) ?: 'Imported income';
            $now = $cache['now'];

            $newIncomeData = [
                'voucher_no' => $voucher,
                'source' => $source,
                'amount' => $amount,
                'date' => $date,
                'payment_mode' => $this->normalizeIncomeMode($row['mode'] ?? 'Cash'),
                'bank_account_id' => $bankAccountId,
                'remarks' => $this->composeIncomeRemarks($row),
            ];

            $existingIncome = $cache['income_vouchers'][$voucher] ?? null;
            if ($existingIncome) {
                $changed = $this->diffForUpdate($existingIncome, $newIncomeData, ['amount']);
                if ($changed === []) {
                    return ['ok' => true, 'skip' => true, 'reason' => 'duplicate'];
                }
                if (! $cache['apply_corrections']) {
                    $cache['flagged'][] = [
                        'type' => 'income_changed',
                        'voucher_no' => $voucher,
                        'fields' => array_keys($changed),
                        'saved' => array_intersect_key($existingIncome, $changed),
                        'excel' => $changed,
                    ];

                    return ['ok' => true, 'skip' => true, 'reason' => 'changed_flagged'];
                }

                return [
                    'ok' => true,
                    'bucket' => 'income_misc_updated',
                    'update' => 'incomes',
                    'update_id' => $existingIncome['id'],
                    'data' => array_merge($changed, ['updated_at' => $now]),
                ];
            }

            return [
                'ok' => true,
                'bucket' => 'income_misc',
                'insert' => 'incomes',
                'data' => array_merge($newIncomeData, [
                    'received_by_id' => $cache['user_id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]),
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

            // Matched by content in warmExpenseVoucherCaches() — never by row position.
            $match = $cache['expense_match'][$row['row_number']] ?? null;
            if (! $match) {
                return $this->fail($log, $row, 'Could not match this expense row.', 'other');
            }
            if ($match['kind'] === 'same') {
                return ['ok' => true, 'skip' => true, 'reason' => 'duplicate'];
            }
            $voucher = $match['voucher'];
            $part2 = trim((string) ($row['part2'] ?? ''));
            $part3 = trim((string) ($row['part3'] ?? ''));
            $remarks = trim((string) ($row['remarks'] ?? ''));
            $now = $cache['now'];

            $newData = [
                'voucher_no' => $voucher,
                'expense_category_id' => $categoryId,
                'part2' => $part2 !== '' ? $part2 : null,
                'part3' => $part3 !== '' ? $part3 : null,
                'title' => $description,
                'amount' => $amount,
                'date' => $date,
                'remarks' => $remarks !== '' ? $remarks : null,
            ];

            $existing = $match['kind'] === 'changed' ? $match['existing'] : null;
            if ($existing) {
                // Once a school has reviewed an imported expense (Approved/Rejected via the
                // Expenses screen), a stale re-import must not silently change its figures
                // out from under that decision.
                if ($existing['status'] !== 'Pending') {
                    return ['ok' => true, 'skip' => true, 'reason' => 'manually_edited'];
                }

                $changed = $this->diffForUpdate($existing, $newData, ['amount']);
                if ($changed === []) {
                    return ['ok' => true, 'skip' => true, 'reason' => 'duplicate'];
                }
                if (! $cache['apply_corrections']) {
                    $cache['flagged'][] = [
                        'type' => 'expense_changed',
                        'voucher_no' => $voucher,
                        'date' => $date,
                        'fields' => array_keys($changed),
                        'saved' => array_intersect_key($existing, $changed),
                        'excel' => $changed,
                    ];

                    return ['ok' => true, 'skip' => true, 'reason' => 'changed_flagged'];
                }

                return [
                    'ok' => true,
                    'bucket' => 'expenses_updated',
                    'update' => 'expenses',
                    'update_id' => $existing['id'],
                    'data' => array_merge($changed, ['updated_at' => $now]),
                ];
            }

            return [
                'ok' => true,
                'bucket' => 'expenses',
                'insert' => 'expenses',
                'data' => array_merge($newData, [
                    'payment_mode' => 'Cash',
                    'paid_by_id' => $cache['user_id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]),
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
    /** "2026-27" (or blank + payment date) → "2026-2027", the academic_sessions.name used. */
    private function sessionNameFor(string $raw, string $date): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            $y = (int) substr($date, 0, 4);
            $m = (int) substr($date, 5, 2);
            $start = $m >= 4 ? $y : $y - 1;
            $raw = $start.'-'.substr((string) ($start + 1), 2);
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $raw, $m)) {
            return $m[1].'-'.substr($m[1], 0, 2).$m[2];
        }

        return $raw;
    }

    private function resolveSession(string $raw, string $date, array &$cache): AcademicSession
    {
        $name = $this->sessionNameFor($raw, $date);

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
            if (is_numeric($admissionNo) && str_contains($admissionNo, '.')) {
                $admissionNo = (string) (int) round((float) $admissionNo);
            }
            if ($admissionNo !== '') {
                // Every receipt no. claimReceiptNo() may pick for this row (plain, session- and
                // student-qualified), so a re-import finds whichever one it used last time.
                $candidates = $this->receiptCandidates(
                    $receiptNo,
                    sprintf('IMP-INC-%s-%s', $date, $row['row_number']),
                    $this->sessionNameFor((string) ($row['session'] ?? ''), $date),
                    $admissionNo
                );
                array_push($receipts, ...$candidates);
                $receipts[] = end($candidates).'-'.$row['row_number'];
            } else {
                $incomeVouchers[] = $receiptNo !== '' ? 'INC-'.$receiptNo : sprintf('INC-IMP-%s-%04d', substr($date, 0, 4), $row['row_number']);
            }
        }
        $receipts = array_values(array_unique(array_filter($receipts)));
        $incomeVouchers = array_values(array_unique(array_filter($incomeVouchers)));

        // Full rows (not just the key) — raw query-builder results (no Eloquent casts) so every
        // value compares cleanly against the freshly computed sheet values in importIncomeRow().
        foreach (array_chunk($receipts, 500) as $chunk) {
            foreach (DB::table('fee_payments')->whereIn('receipt_no', $chunk)->get([
                'id', 'receipt_no', 'student_id', 'academic_session_id', 'multi_session', 'items', 'amount',
                'discount_amount', 'fine_amount', 'session_fines', 'payment_mode', 'payment_date', 'remarks',
                'status', 'refunded_amount', 'edited_at', 'rolled_back_at',
            ]) as $r) {
                $cache['receipts'][$r->receipt_no] = (array) $r;
            }
        }
        foreach (array_chunk($incomeVouchers, 500) as $chunk) {
            foreach (DB::table('incomes')->whereIn('voucher_no', $chunk)->get([
                'id', 'voucher_no', 'source', 'amount', 'date', 'payment_mode', 'bank_account_id', 'remarks',
            ]) as $r) {
                $cache['income_vouchers'][$r->voucher_no] = (array) $r;
            }
        }
    }

    /**
     * Matches every EXPENSES row to the expense it was saved as, by content — date, description,
     * amount, PART 1-3 and remarks — never by its row position, so inserting or deleting a row
     * in the sheet doesn't shift every later row onto a different record. Identical rows are
     * matched by count (two genuine identical expenses stay two). A row with no content match
     * whose old position-based voucher ("EXP-IMP-<year>-<row>") still holds the same date and
     * description or amount is that same expense, edited in the sheet ("changed"); anything else
     * is new and gets a voucher derived from its content.
     *
     * @param  list<array<string, mixed>>  $mapped
     */
    private function warmExpenseVoucherCaches(array $mapped, array &$cache): void
    {
        $categoryNames = array_flip($cache['expense_categories']);
        $pool = [];
        $byVoucher = [];
        foreach (DB::table('expenses')->orderBy('id')->get([
            'id', 'voucher_no', 'expense_category_id', 'part2', 'part3', 'title', 'amount', 'date', 'remarks', 'status',
        ]) as $r) {
            $existing = (array) $r;
            $key = $this->expenseKey(substr((string) $r->date, 0, 10), (string) $r->title, (float) $r->amount,
                (string) ($categoryNames[$r->expense_category_id] ?? ''), (string) $r->part2, (string) $r->part3, (string) $r->remarks);
            $pool[$key][] = $existing;
            $byVoucher[$r->voucher_no] = $existing;
        }

        $claimed = [];
        $pending = [];
        $occurrence = [];
        foreach ($mapped as $row) {
            $date = $this->resolveDate($row);
            $amount = $this->parseAmount(trim((string) ($row['amount'] ?? '')));
            $title = trim((string) ($row['description'] ?? ''));
            if (! $date || $amount === null || $title === '') {
                continue; // rejected later with its own reason
            }
            $key = $this->expenseKey($date, $title, $amount, trim((string) ($row['part1'] ?? '')) ?: 'General',
                (string) ($row['part2'] ?? ''), (string) ($row['part3'] ?? ''), (string) ($row['remarks'] ?? ''));
            $n = $occurrence[$key] = ($occurrence[$key] ?? 0) + 1;
            $match = ['kind' => 'new', 'existing' => null, 'voucher' => 'EXP-IMP-'.$date.'-'.substr(md5($key), 0, 10).'-'.$n];

            while (($pool[$key] ?? []) !== []) {
                $existing = array_shift($pool[$key]);
                if (! isset($claimed[$existing['id']])) {
                    $claimed[$existing['id']] = true;
                    $match = ['kind' => 'same', 'existing' => $existing, 'voucher' => $existing['voucher_no']];
                    break;
                }
            }
            $cache['expense_match'][$row['row_number']] = $match;
            if ($match['kind'] === 'new') {
                $pending[] = [$row, $date, $title, $amount];
            }
        }

        // Second pass: rows with no content match — the same expense edited in the sheet?
        foreach ($pending as [$row, $date, $title, $amount]) {
            $legacy = $byVoucher[sprintf('EXP-IMP-%s-%04d', substr($date, 0, 4), $row['row_number'])] ?? null;
            if ($legacy && ! isset($claimed[$legacy['id']]) && substr((string) $legacy['date'], 0, 10) === $date
                && (mb_strtoupper(trim((string) $legacy['title'])) === mb_strtoupper($title) || round((float) $legacy['amount'], 2) === round($amount, 2))) {
                $claimed[$legacy['id']] = true;
                $cache['expense_match'][$row['row_number']] = ['kind' => 'changed', 'existing' => $legacy, 'voucher' => $legacy['voucher_no']];
            }
        }

        // Imported expenses this sheet no longer has (deleted or edited beyond recognition) —
        // reported, never deleted.
        $dates = array_filter(array_map(fn ($r) => $this->resolveDate($r), $mapped));
        if ($dates !== []) {
            [$from, $to] = [min($dates), max($dates)];
            foreach ($byVoucher as $voucher => $existing) {
                $d = substr((string) $existing['date'], 0, 10);
                if (! isset($claimed[$existing['id']]) && str_starts_with($voucher, 'EXP-IMP-') && $d >= $from && $d <= $to) {
                    $cache['flagged'][] = [
                        'type' => 'expense_not_in_excel',
                        'voucher_no' => $voucher,
                        'date' => $d,
                        'title' => $existing['title'],
                        'amount' => round((float) $existing['amount'], 2),
                        'note' => 'Saved from an earlier import but not found in this sheet — left as it is.',
                    ];
                }
            }
        }
    }

    private function expenseKey(string $date, string $title, float $amount, string $part1, string $part2, string $part3, string $remarks): string
    {
        $norm = fn (string $v) => mb_strtoupper(preg_replace('/\s+/', ' ', trim($v)));

        return implode('|', [$date, $norm($title), number_format($amount, 2, '.', ''), $norm($part1), $norm($part2), $norm($part3), $norm($remarks)]);
    }

    /**
     * Compares a freshly computed row ($new) against the matching existing DB row ($existing,
     * from warmReceiptCaches()/warmExpenseVoucherCaches() — a plain array via DB::table(), no
     * Eloquent casts) and returns only the $new entries that actually differ, so a re-import only
     * ever UPDATEs the columns the sheet actually changed, never touches unrelated columns
     * (status, collected_by_id, created_at, ...), and never "updates" a byte-identical row.
     *
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $new
     * @param  list<string>  $numericFields  compared as rounded floats (DB decimals come back as strings)
     * @param  list<string>  $jsonFields  compared as decoded structures, not raw strings
     * @return array<string, mixed>
     */
    private function diffForUpdate(array $existing, array $new, array $numericFields = [], array $jsonFields = []): array
    {
        $changed = [];
        foreach ($new as $field => $value) {
            $old = $existing[$field] ?? null;

            if (in_array($field, $numericFields, true)) {
                if (round((float) $old, 2) !== round((float) $value, 2)) {
                    $changed[$field] = $value;
                }

                continue;
            }

            if (in_array($field, $jsonFields, true)) {
                $oldDecoded = is_string($old) ? json_decode($old, true) : $old;
                $newDecoded = is_string($value) ? json_decode($value, true) : $value;
                if ($oldDecoded !== $newDecoded) {
                    $changed[$field] = $value;
                }

                continue;
            }

            if ((string) $old !== (string) $value) {
                $changed[$field] = $value;
            }
        }

        return $changed;
    }
}
