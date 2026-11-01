<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Erp\FeeManagement\FeePaidController;
use App\Http\Controllers\Student\Concerns\EnforcesPortalVisibility;
use App\Models\AcademicSession;
use App\Models\FeePayment;
use App\Models\Student;
use App\Services\DocumentDataBuilder;
use App\Services\DocumentRenderService;
use App\Services\FeeBalanceService;
use App\Services\FeeCalculator;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeeController extends Controller
{
    use EnforcesPortalVisibility;

    public function __construct(
        private FeeBalanceService $balance,
        private DocumentDataBuilder $dataBuilder,
        private DocumentRenderService $renderer,
    ) {}

    public function summary()
    {
        $this->abortIfModuleDisabled('fee_summary');
        $student = $this->student();
        $session = $this->currentSession();
        if (! $session) {
            return response()->json(['session' => null, 'summary' => null, 'categories' => []]);
        }

        [$monthRows, $categories, $summary] = $this->calculate($student, $session);

        return response()->json([
            'session' => ['id' => $session->id, 'name' => $session->name],
            'summary' => $summary,
            'categories' => $categories,
            'months' => $monthRows,
        ]);
    }

    public function pending()
    {
        $this->abortIfModuleDisabled('pending_fees');
        $student = $this->student();
        $session = $this->currentSession();
        if (! $session) {
            return response()->json(['months' => []]);
        }

        [$monthRows] = $this->calculate($student, $session);
        $due = collect($monthRows)->filter(fn ($m) => (float) $m['due'] > 0)->values();

        return response()->json(['session' => ['id' => $session->id, 'name' => $session->name], 'months' => $due]);
    }

    /** Same record the ERP's Fee Management → Fee Paid shows for this student (current session). */
    public function paid()
    {
        $this->abortIfModuleDisabled('paid_fees');
        $student = $this->student();
        $session = $this->currentSession();
        if (! $session) {
            return response()->json(['session' => null, 'row' => null, 'receipts' => [], 'summary' => null]);
        }

        $record = app(FeePaidController::class)->studentRecord($student, $session);
        $row = $record['row'];

        return response()->json([
            'session' => $session->name,
            'row' => $row ? collect($row)->only([
                'scope_label', 'total_fee', 'total_discount', 'total_paid', 'advance_paid', 'due', 'counted_paid',
                'session_paid', 'paid_through_label', 'advance_months_label', 'payment_status',
                'outside_session_paid', 'outside_session_months', 'fine_paid', 'unmatched_paid',
            ])->all() : null,
            'receipts' => $record['details']['receipts'] ?? [],
            'summary' => $record['details']['summary'] ?? null,
        ]);
    }

    /**
     * Session-wise fee ledger (like the ERP's Fee History): each session's whole-year charge,
     * concession, received and balance, with that session's receipts.
     */
    public function history()
    {
        $this->abortIfModuleDisabled('fee_history');
        $student = $this->student();

        $payments = FeePayment::where('student_id', $student->id)
            ->where('status', '!=', 'Rolled Back')
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();
        $paymentSessionIds = $payments->flatMap(fn (FeePayment $p) => collect(is_array($p->items) ? $p->items : [])
            ->map(fn ($item) => $p->itemSessionId((array) $item))
            ->push((int) $p->academic_session_id))->filter()->unique()->values();
        $sessions = AcademicSession::query()
            ->where(fn ($q) => $q->where('is_current', true)->orWhereIn('id', $paymentSessionIds))
            ->orderByDesc('start_date')
            ->get();

        $rows = $sessions->map(function (AcademicSession $session) use ($student, $payments) {
            // A receipt that also pays another session's fees shows only this session's lines.
            $receipts = FeePayment::slicesFor($payments, [$session->id]);
            $row = [
                'session' => $session->name,
                'is_current' => (bool) $session->is_current,
                'total' => null,
                'concession' => null,
                'received' => round((float) $receipts->sum('amount'), 2),
                'balance' => null,
                'receipts' => $this->paymentRowsFrom($receipts),
            ];

            // Whole-session ledger for the current session — same figures as the ERP's Fee History.
            // Older sessions (often imported) list their receipts only: their fee plan can't be
            // rebuilt reliably, so a computed balance there would be misleading.
            if ($session->is_current) {
                $calc = FeeCalculator::forStudent($student, $session);
                $paidInfo = $this->balance->paidByHead($student, $session);
                $keys = $this->balance->filterMonthsFromFeeStart(
                    $student,
                    collect($session->months())->pluck('key')->filter()->values()->all()
                );
                if ($keys) {
                    $ledger = $this->balance->remainingForMonths($student, $session, $keys, $calc, $paidInfo);
                    $row['total'] = round((float) $ledger['charge'], 2);
                    $row['concession'] = round((float) $ledger['discount'], 2);
                    $row['received'] = round((float) $ledger['paid'], 2);
                    $row['balance'] = round((float) $ledger['due'], 2);
                }
            }

            return $row;
        })->filter(fn ($r) => $r['is_current'] || $r['receipts'] !== [])->values();

        return response()->json(['sessions' => $rows]);
    }

    public function receipt(FeePayment $feePayment): StreamedResponse
    {
        $this->abortIfModuleDisabled('receipts');
        $student = $this->student();
        abort_unless($feePayment->student_id === $student->id, 404);

        $data = $this->dataBuilder->feeReceipt($feePayment);
        // Imported receipts can be session-qualified ("1898/26-27"); "/" is not allowed in a download filename.
        $filename = str_replace(['/', '\\'], '-', $feePayment->receipt_no ?: 'fee-receipt').'.pdf';

        return $this->renderer->streamPdf('fee_receipt', $data, $filename);
    }

    private function student(): Student
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        return $student;
    }

    private function currentSession(): ?AcademicSession
    {
        return AcademicSession::where('is_current', true)->first();
    }

    /** @return array{0: array, 1: array, 2: array} [monthRows, categoryRows, summary] */
    private function calculate(Student $student, AcademicSession $session): array
    {
        $calc = FeeCalculator::forStudent($student, $session);
        $monthKeys = $this->balance->filterMonthsFromFeeStart($student, $this->monthKeysTillNow($session));
        $paidInfo = $this->balance->paidByHead($student, $session);
        $monthRows = $this->balance->monthRows($student, $session, $monthKeys, $calc, $paidInfo);
        $categories = $this->balance->categoryRows($student, $session, $monthKeys, $calc, $paidInfo);

        $summary = [
            'total_charge' => round(array_sum(array_column($monthRows, 'charge')), 2),
            'concession' => round(array_sum(array_column($monthRows, 'concession')), 2),
            'paid' => round(array_sum(array_column($monthRows, 'paid')), 2),
            'due' => round(array_sum(array_column($monthRows, 'due')), 2),
        ];

        return [$monthRows, $categories, $summary];
    }

    /** All months from the session's start through the current (or session end, whichever is sooner). */
    private function monthKeysTillNow(AcademicSession $session): array
    {
        if (! $session->start_date || ! $session->end_date) {
            return [];
        }

        $start = $session->start_date->copy()->startOfMonth();
        $end = $session->end_date->copy()->startOfMonth();
        $now = now()->startOfMonth();

        if ($now < $start) {
            return [];
        }
        $till = $now->gt($end) ? $end->copy() : $now->copy();

        $keys = [];
        $cursor = $start->copy();
        while ($cursor <= $till) {
            $keys[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $keys;
    }

    /** @param  iterable<FeePayment>  $payments */
    private function paymentRowsFrom(iterable $payments): array
    {
        return collect($payments)->map(function (FeePayment $p) {
            $items = is_array($p->items) ? $p->items : [];
            $heads = collect($items)->pluck('fee_head_name')->filter()->unique()->implode(', ');

            return [
                'id' => $p->id,
                'receipt_no' => $p->receipt_no,
                'payment_date' => $p->payment_date?->format('Y-m-d'),
                'payment_mode' => $p->payment_mode,
                'amount' => (float) $p->amount,
                'discount_amount' => (float) $p->discount_amount,
                'fine_amount' => (float) $p->fine_amount,
                'refunded_amount' => (float) $p->refunded_amount,
                'status' => $p->status,
                'fee_heads' => $heads,
                'remarks' => $p->remarks,
                // One line per fee head on the receipt, for the "details" view
                'lines' => collect($items)->map(fn ($i) => [
                    'fee_head' => $i['fee_head_name'] ?? 'Fee',
                    'months' => collect((array) ($i['months'] ?? (isset($i['month']) ? [$i['month']] : [])))
                        ->filter()
                        ->map(fn ($m) => preg_match('/^\d{4}-\d{2}$/', (string) $m) ? \Carbon\Carbon::createFromFormat('!Y-m', $m)->format('M Y') : (string) $m)
                        ->implode(', '),
                    'charge' => (float) ($i['charge'] ?? $i['amount'] ?? 0),
                    'discount' => (float) ($i['discount'] ?? 0),
                    'amount' => (float) ($i['amount'] ?? 0),
                ])->values()->all(),
            ];
        })->values()->all();
    }
}
