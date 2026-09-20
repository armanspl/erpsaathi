<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
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

    public function paid()
    {
        $this->abortIfModuleDisabled('paid_fees');
        $student = $this->student();

        return response()->json($this->paymentRows($student));
    }

    public function history()
    {
        $this->abortIfModuleDisabled('fee_history');
        $student = $this->student();

        return response()->json($this->paymentRows($student, allSessions: true));
    }

    public function receipt(FeePayment $feePayment): StreamedResponse
    {
        $this->abortIfModuleDisabled('receipts');
        $student = $this->student();
        abort_unless($feePayment->student_id === $student->id, 404);

        $data = $this->dataBuilder->feeReceipt($feePayment);
        $filename = ($feePayment->receipt_no ?: 'fee-receipt').'.pdf';

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

    private function paymentRows(Student $student, bool $allSessions = false): array
    {
        $query = FeePayment::where('student_id', $student->id)
            ->where('status', '!=', 'Rolled Back')
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        if (! $allSessions) {
            $sessionId = $this->currentSession()?->id;
            if ($sessionId) {
                $query->where('academic_session_id', $sessionId);
            }
        }

        return $query->get()->map(function (FeePayment $p) {
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
            ];
        })->values()->all();
    }
}
