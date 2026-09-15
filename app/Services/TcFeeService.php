<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\CertificateType;
use App\Models\ErpFeeSetting;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\ManualFeeDue;
use App\Models\Student;
use App\Support\DashboardCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * One-time Transfer Certificate fee — created only when a TC is processed,
 * never auto-added to class fee structures.
 */
class TcFeeService
{
    public const HEAD_NAME = 'Transfer Certificate Fee';

    public const REMARKS = 'TC_FEE';

    public function isTransferCertificate(CertificateType $type): bool
    {
        $label = strtolower(trim((string) ($type->label ?? '')));
        $prefix = strtoupper(trim((string) ($type->prefix ?? '')));

        return $prefix === 'TC'
            || str_contains($label, 'transfer certificate')
            || (str_contains($label, 'transfer') && str_contains($label, 'cert'));
    }

    /** @return array{enabled: bool, amount: float, allow_pending_fees: bool} */
    public function settings(): array
    {
        $s = ErpFeeSetting::current();

        return [
            'enabled' => (bool) ($s->tc_fee_enabled ?? true),
            'amount' => round((float) ($s->tc_fee_amount ?? 500), 2),
            'allow_pending_fees' => (bool) ($s->tc_allow_pending_fees ?? false),
        ];
    }

    public function ensureFeeHead(): FeeHead
    {
        return FeeHead::firstOrCreate(
            ['name' => self::HEAD_NAME],
            ['description' => 'One-time Transfer Certificate fee — charged only when a TC is processed']
        );
    }

    /**
     * Ensure a student-specific TC charge exists (when enabled), then return checkout summary.
     *
     * @return array<string, mixed>
     */
    public function checkout(Student $student, bool $ensureCharge = true): array
    {
        $session = AcademicSession::where('is_current', true)->first()
            ?? AcademicSession::query()->orderByDesc('start_date')->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'session' => 'No academic session is available to process the TC fee.',
            ]);
        }

        $settings = $this->settings();
        $tcDue = null;

        if ($ensureCharge && $settings['enabled'] && $settings['amount'] > 0) {
            $tcDue = $this->ensureCharge($student, $session, $settings['amount']);
        } elseif ($settings['enabled']) {
            $tcDue = $this->findCharge($student, $session);
        }

        $tcAmount = $tcDue ? round((float) $tcDue->amount, 2) : ($settings['enabled'] ? $settings['amount'] : 0.0);
        $tcPaid = $tcDue ? round((float) $tcDue->paid_amount, 2) : 0.0;
        $tcBalance = $tcDue ? $tcDue->balance() : ($settings['enabled'] ? $settings['amount'] : 0.0);
        if (! $settings['enabled']) {
            $tcAmount = 0.0;
            $tcPaid = 0.0;
            $tcBalance = 0.0;
        }

        $feeDue = $this->feeDueBreakdown($student, $session, $tcDue?->id);
        $academicOutstanding = (float) $feeDue['total_due'];
        $totalToCollect = round($academicOutstanding + $tcBalance, 2);
        $canIssue = $this->canIssue($settings, $academicOutstanding, $tcBalance);

        return [
            'settings' => $settings,
            'session' => ['id' => $session->id, 'name' => $session->name],
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'admission_no' => $student->admission_no,
            ],
            'fee_due' => $feeDue,
            'academic_outstanding' => $academicOutstanding,
            'tc_fee' => [
                'enabled' => $settings['enabled'],
                'manual_due_id' => $tcDue?->id,
                'amount' => $tcAmount,
                'paid' => $tcPaid,
                'due' => $tcBalance,
                'status' => $tcBalance <= 0.009 ? 'Paid' : ($tcPaid > 0 ? 'Partial' : 'Unpaid'),
            ],
            'total_to_collect' => $totalToCollect,
            'can_issue' => $canIssue,
            'block_reason' => $canIssue ? null : $this->blockReason($settings, $academicOutstanding, $tcBalance),
        ];
    }

    public function assertCanIssue(Student $student): void
    {
        $status = $this->checkout($student, true);
        if ($status['can_issue']) {
            return;
        }

        throw ValidationException::withMessages([
            'tc_fee' => $status['block_reason'] ?: 'Outstanding fees must be cleared before issuing the Transfer Certificate.',
        ]);
    }

    /**
     * Collect payment against the student's TC fee due only.
     */
    public function pay(
        Student $student,
        float $amount,
        string $paymentMode,
        ?string $paymentDate = null,
        ?string $remarks = null,
    ): FeePayment {
        $status = $this->checkout($student, true);
        $dueId = $status['tc_fee']['manual_due_id'] ?? null;
        $dueBalance = (float) ($status['tc_fee']['due'] ?? 0);

        if (! $status['settings']['enabled']) {
            throw ValidationException::withMessages(['amount' => 'TC Fee is disabled in Fee Settings.']);
        }
        if (! $dueId || $dueBalance <= 0) {
            throw ValidationException::withMessages(['amount' => 'TC Fee is already paid for this student.']);
        }

        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter a payment amount greater than zero.']);
        }
        if ($amount > $dueBalance + 0.009) {
            throw ValidationException::withMessages([
                'amount' => 'Payment cannot exceed TC Fee due (₹'.number_format($dueBalance, 2).').',
            ]);
        }

        $mode = $this->normalizeMode($paymentMode);
        $sessionId = (int) $status['session']['id'];

        $payment = DB::transaction(function () use ($student, $dueId, $amount, $mode, $paymentDate, $remarks, $sessionId) {
            /** @var ManualFeeDue $due */
            $due = ManualFeeDue::query()->lockForUpdate()->findOrFail($dueId);
            $take = min($due->balance(), $amount);
            if ($take <= 0) {
                throw ValidationException::withMessages(['amount' => 'TC Fee is already paid.']);
            }

            $due->paid_amount = round((float) $due->paid_amount + $take, 2);
            $due->save();

            $head = $due->feeHead ?: FeeHead::find($due->fee_head_id);

            return FeePayment::create([
                'receipt_no' => $this->nextReceiptNo(),
                'student_id' => $student->id,
                'academic_session_id' => $sessionId,
                'items' => [[
                    'fee_head_id' => $due->fee_head_id,
                    'fee_head_name' => $head?->name ?? self::HEAD_NAME,
                    'amount' => $take,
                    'month' => $due->month?->format('Y-m'),
                ]],
                'amount' => $take,
                'discount_amount' => 0,
                'fine_amount' => 0,
                'refunded_amount' => 0,
                'payment_date' => $paymentDate ? Carbon::parse($paymentDate)->toDateString() : now()->toDateString(),
                'payment_mode' => $mode,
                'remarks' => $remarks ?: 'Transfer Certificate Fee',
                'collected_by_id' => Auth::guard('erp')->id(),
                'status' => 'Paid',
            ]);
        });

        DashboardCache::forget();

        return $payment;
    }

    private function ensureCharge(Student $student, AcademicSession $session, float $amount): ManualFeeDue
    {
        $head = $this->ensureFeeHead();
        $month = ($session->start_date?->copy() ?? now())->startOfMonth()->toDateString();

        $due = ManualFeeDue::query()->firstOrNew([
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'fee_head_id' => $head->id,
            'month' => $month,
        ]);

        if (! $due->exists) {
            $due->fill([
                'amount' => round($amount, 2),
                'paid_amount' => 0,
                'due_date' => now()->toDateString(),
                'remarks' => self::REMARKS,
                'created_by_id' => Auth::guard('erp')->id(),
            ])->save();

            return $due->fresh(['feeHead:id,name']);
        }

        // Already paid (fully or partially) — never double-charge; keep existing amount.
        if ((float) $due->paid_amount > 0) {
            if ($due->remarks !== self::REMARKS) {
                $due->remarks = self::REMARKS;
                $due->save();
            }

            return $due->loadMissing('feeHead:id,name');
        }

        // Unpaid — sync amount from current Fee Settings.
        $due->amount = round($amount, 2);
        $due->remarks = self::REMARKS;
        $due->due_date = $due->due_date ?: now()->toDateString();
        $due->save();

        return $due->fresh(['feeHead:id,name']);
    }

    private function findCharge(Student $student, AcademicSession $session): ?ManualFeeDue
    {
        $head = FeeHead::query()->where('name', self::HEAD_NAME)->first();
        if (! $head) {
            return null;
        }

        return ManualFeeDue::query()
            ->with('feeHead:id,name')
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where('fee_head_id', $head->id)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Same numbers as Fee Management → Fee Due (automatic till current month)
     * plus other manual dues (excluding TC Fee).
     *
     * @return array{
     *   automatic_charge: float,
     *   automatic_paid: float,
     *   automatic_discount: float,
     *   automatic_due: float,
     *   manual_due: float,
     *   total_due: float,
     *   scope: string
     * }
     */
    private function feeDueBreakdown(Student $student, AcademicSession $session, ?int $excludeManualDueId = null): array
    {
        $balance = app(FeeBalanceService::class);

        FeeCalculator::flushRuntimeCache();
        FeeCalculator::warmForStudents(collect([$student]), collect([$session]));

        $calc = FeeCalculator::forStudent($student, $session);
        $monthKeys = $this->monthKeysTillCurrent($session);
        $monthKeys = $balance->filterMonthsFromFeeStart($student, $monthKeys);
        $paidInfo = $balance->paidByHead($student, $session);
        $remaining = $balance->remainingForMonths($student, $session, $monthKeys, $calc, $paidInfo);

        $automaticDue = max(0, round((float) ($remaining['due'] ?? 0), 2));
        $automaticCharge = max(0, round((float) ($remaining['charge'] ?? 0), 2));
        $automaticPaid = max(0, round((float) ($remaining['paid'] ?? 0), 2));
        $automaticDiscount = max(0, round((float) ($remaining['discount'] ?? 0), 2));

        $tcHeadId = FeeHead::query()->where('name', self::HEAD_NAME)->value('id');
        $manualQuery = ManualFeeDue::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id);

        if ($excludeManualDueId) {
            $manualQuery->where('id', '!=', $excludeManualDueId);
        } elseif ($tcHeadId) {
            $manualQuery->where('fee_head_id', '!=', $tcHeadId);
        }

        $manualDue = round($manualQuery->get()->sum(fn (ManualFeeDue $d) => $d->balance()), 2);

        return [
            'automatic_charge' => $automaticCharge,
            'automatic_paid' => $automaticPaid,
            'automatic_discount' => $automaticDiscount,
            'automatic_due' => $automaticDue,
            'manual_due' => $manualDue,
            'total_due' => round($automaticDue + $manualDue, 2),
            'scope' => 'till_current',
        ];
    }

    /** @return list<string> Y-m keys from session start through current month (Fee Due default). */
    private function monthKeysTillCurrent(AcademicSession $session): array
    {
        if (! $session->start_date || ! $session->end_date) {
            return [];
        }

        $start = $session->start_date->copy()->startOfMonth();
        $end = $session->end_date->copy()->startOfMonth();
        $now = now()->startOfMonth();

        if ($now->lt($start)) {
            return [];
        }

        $till = $now->gt($end) ? $end->copy() : $now->copy();
        $keys = [];
        $cursor = $start->copy();
        while ($cursor->lte($till)) {
            $keys[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $keys;
    }

    /** @param  array{enabled: bool, amount: float, allow_pending_fees: bool}  $settings */
    private function canIssue(array $settings, float $academicOutstanding, float $tcBalance): bool
    {
        if ($settings['allow_pending_fees']) {
            return true;
        }

        if ($settings['enabled'] && $tcBalance > 0.009) {
            return false;
        }

        if ($academicOutstanding > 0.009) {
            return false;
        }

        return true;
    }

    /** @param  array{enabled: bool, amount: float, allow_pending_fees: bool}  $settings */
    private function blockReason(array $settings, float $academicOutstanding, float $tcBalance): string
    {
        $parts = [];
        if ($settings['enabled'] && $tcBalance > 0.009) {
            $parts[] = 'TC Fee due ₹'.number_format($tcBalance, 2);
        }
        if ($academicOutstanding > 0.009) {
            $parts[] = 'Fee Due ₹'.number_format($academicOutstanding, 2);
        }

        if ($parts === []) {
            return 'Outstanding fees must be cleared before issuing the Transfer Certificate.';
        }

        return 'Clear '.implode(' and ', $parts).' before issuing the Transfer Certificate (or enable “Allow TC with pending fees” in Fee Settings).';
    }

    private function normalizeMode(string $paymentMode): string
    {
        $mode = ucwords(strtolower(trim($paymentMode)));
        if (strcasecmp($paymentMode, 'upi') === 0) {
            return 'UPI';
        }
        if (! in_array($mode, ['Cash', 'UPI', 'Card', 'Bank Transfer', 'Cheque'], true)) {
            return 'Cash';
        }

        return $mode;
    }

    private function nextReceiptNo(): string
    {
        $year = now()->year;
        $prefix = "RCPT-{$year}-";
        $last = FeePayment::where('receipt_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('receipt_no');
        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
