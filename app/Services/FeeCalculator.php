<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\ErpFeeStructure;
use App\Models\ErpFeeStructurePlan;
use App\Models\ErpFeeStructurePlanItem;
use App\Models\FeeDiscount;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\Student;
use App\Models\StudentSessionHistory;
use App\Models\StudentUdiseDetail;
use Illuminate\Support\Collection;

class FeeCalculator
{
    /** One-time heads charged only for New admissions (not Old). */
    private const NEW_ONLY_FEE_HEADS = [
        'admission fee',
        'registration fee',
    ];

    /**
     * Who a fee-structure line is charged to when the line has no saved "For students" choice:
     * Admission / Registration Fee → New only, everything else → all students.
     */
    public static function defaultAppliesTo(string $label): string
    {
        return in_array(strtolower(trim($label)), self::NEW_ONLY_FEE_HEADS, true) ? 'new' : 'all';
    }

    /** @var array<string, int> */
    private static array $headsByName = [];

    private static bool $headsLoaded = false;

    /** @var array<string, list<array{fee_head_id: int, fee_head_name: string, amount: float, frequency: string}>> */
    private static array $planCache = [];

    /** @var array<string, list<array{fee_head_id: int, fee_head_name: string, amount: float, frequency: string}>> sessionId|classId => breakdown */
    private static array $legacyCache = [];

    /** @var array<string, float> studentId:sessionId => paid */
    private static array $paidCache = [];

    /** @var array<string, Collection<int, FeePayment>> studentId:sessionId => payment rows (items) */
    private static array $paymentRowsCache = [];

    /** @var array<int, Collection<int, FeeDiscount>> */
    private static array $discountCache = [];

    /** @var array<int, string|null> student_id => admission_type */
    private static array $admissionTypeCache = [];

    /** @var array<int, string|null> student_id => admission_date (Y-m-d) */
    private static array $admissionDateCache = [];

    /** @var array<int, string|null> student_id => first session in student_session_history ("2026-2027") */
    private static array $firstSessionCache = [];

    /** @var Collection<int, AcademicSession>|null all sessions, oldest first */
    private static ?Collection $sessionsCache = null;

    private static bool $warmed = false;

    /**
     * Prefetch payments + discounts for a student cohort so forStudent() avoids N+1 queries.
     *
     * @param  Collection<int, Student>|array<int, Student>  $students
     * @param  Collection<int, AcademicSession>|array<int, AcademicSession>|AcademicSession  $sessions
     */
    public static function warmForStudents($students, $sessions): void
    {
        $students = collect($students);
        $sessions = $sessions instanceof AcademicSession ? collect([$sessions]) : collect($sessions);
        $studentIds = $students->pluck('id')->filter()->unique()->values()->all();
        $sessionIds = $sessions->pluck('id')->filter()->unique()->values()->all();

        if ($studentIds === [] || $sessionIds === []) {
            self::$warmed = true;

            return;
        }

        self::ensureHeadsLoaded();

        // Per-session slices: a multi-session receipt counts each item in its own session.
        $paymentRows = FeePayment::slicesFor(FeePayment::query()
            ->whereIn('student_id', $studentIds)
            ->touchingSessions($sessionIds)
            ->where(function ($q) {
                $q->whereNull('status')->orWhereNotIn('status', ['Refunded', 'Rolled Back']);
            })
            ->orderBy('id')
            ->get(['id', 'student_id', 'academic_session_id', 'multi_session', 'items', 'amount', 'discount_amount', 'fine_amount', 'session_fines', 'refunded_amount', 'status']), $sessionIds);

        self::$paymentRowsCache = [];
        self::$paidCache = [];

        foreach ($paymentRows->groupBy(fn (FeePayment $p) => $p->student_id.':'.$p->academic_session_id) as $key => $rows) {
            self::$paymentRowsCache[$key] = $rows->values();
            self::$paidCache[$key] = round((float) $rows->sum(
                fn (FeePayment $p) => (float) $p->amount - (float) $p->refunded_amount
            ), 2);
        }

        foreach ($studentIds as $id) {
            foreach ($sessionIds as $sid) {
                $key = $id.':'.$sid;
                self::$paymentRowsCache[$key] ??= collect();
                self::$paidCache[$key] ??= 0.0;
            }
        }

        self::$discountCache = FeeDiscount::query()
            ->whereIn('student_id', $studentIds)
            ->get()
            ->groupBy('student_id')
            ->all();

        $types = StudentUdiseDetail::query()
            ->whereIn('student_id', $studentIds)
            ->pluck('admission_type', 'student_id');
        foreach ($studentIds as $id) {
            self::$admissionTypeCache[$id] = $types[$id] ?? null;
        }

        $dates = Student::query()->whereIn('id', $studentIds)->pluck('admission_date', 'id');
        foreach ($studentIds as $id) {
            self::$admissionDateCache[$id] = isset($dates[$id]) ? substr((string) $dates[$id], 0, 10) : null;
            self::$firstSessionCache[$id] = null;
        }
        foreach (StudentSessionHistory::query()->whereIn('student_id', $studentIds)->get(['student_id', 'session']) as $h) {
            $name = self::normalizeSessionName((string) $h->session);
            $current = self::$firstSessionCache[$h->student_id] ?? null;
            if ($name !== null && ($current === null || $name < $current)) {
                self::$firstSessionCache[$h->student_id] = $name;
            }
        }

        self::$warmed = true;

        // Keep fee-balance payment/transport N+1 warm in sync with this cohort.
        app(FeeBalanceService::class)->warmForStudents($students, $sessions);
    }

    public static function flushRuntimeCache(): void
    {
        self::$planCache = [];
        self::$legacyCache = [];
        self::$paidCache = [];
        self::$paymentRowsCache = [];
        self::$discountCache = [];
        self::$admissionTypeCache = [];
        self::$admissionDateCache = [];
        self::$firstSessionCache = [];
        self::$sessionsCache = null;
        self::$warmed = false;
        FeeBalanceService::flushRuntimeCache();
    }

    /**
     * Prefetched non-refunded payments for a student/session, or null when not warmed.
     *
     * @return Collection<int, FeePayment>|null
     */
    public static function cachedPayments(Student $student, AcademicSession $session): ?Collection
    {
        if (! self::$warmed) {
            return null;
        }

        $key = $student->id.':'.$session->id;

        return self::$paymentRowsCache[$key] ?? collect();
    }

    /**
     * Fee-structure lines only (no payment queries). Used by bulk exports that already
     * aggregate payments separately.
     *
     * @return list<array{fee_head_id: int, fee_head_name: string, amount: float, frequency: string}>
     */
    public static function breakdownOnly(Student $student, ?AcademicSession $session = null): array
    {
        $session ??= AcademicSession::where('is_current', true)->first();
        if (! $session) {
            return [];
        }

        $breakdown = self::planBreakdown($student, $session);
        if ($breakdown === []) {
            $breakdown = self::legacyStructureBreakdown($student, $session);
        }

        return self::filterBreakdownForAdmissionType($student, $breakdown, $session);
    }

    /**
     * Compute a student's fee position for a session: what the class-wise fee structure
     * says they owe, minus discounts, minus what they've already paid (net of refunds).
     *
     * New / Old is decided per session (see admissionTypeForSession()): Fee Structure lines set
     * to "New only" are charged in the student's admission session, "Old only" in later ones.
     */
    public static function forStudent(Student $student, ?AcademicSession $session = null): array
    {
        $session ??= AcademicSession::where('is_current', true)->first();

        if (! $session) {
            return ['session' => null, 'breakdown' => [], 'total_fee' => 0, 'total_discount' => 0, 'total_paid' => 0, 'due' => 0];
        }

        $breakdown = self::breakdownOnly($student, $session);

        $totalFee = (float) collect($breakdown)->sum('amount');

        $discounts = self::$warmed
            ? (self::$discountCache[$student->id] ?? collect())
            : FeeDiscount::where('student_id', $student->id)->get();

        $totalDiscount = (float) collect($discounts)->sum(
            fn (FeeDiscount $d) => $d->type === 'percentage' ? round($totalFee * ((float) $d->value / 100), 2) : (float) $d->value
        );
        $totalDiscount = min($totalDiscount, $totalFee);

        $totalPaid = self::paidTowardBreakdown($student, $session, $breakdown);

        $due = max(0, round($totalFee - $totalDiscount - $totalPaid, 2));

        return [
            'session' => ['id' => $session->id, 'name' => $session->name],
            'breakdown' => $breakdown,
            'total_fee' => $totalFee,
            'total_discount' => $totalDiscount,
            'total_paid' => $totalPaid,
            'due' => $due,
            'admission_type' => self::admissionTypeForSession($student, $session),
        ];
    }

    /**
     * Keep only the Fee Structure lines this student is charged in $session: "All" lines, plus
     * "New only" or "Old only" lines depending on whether the student is New or Old in that
     * session (neither, in a session before the one they were admitted for).
     *
     * @param  list<array{fee_head_id: int, fee_head_name: string, amount: float, frequency: string}>  $breakdown
     * @return list<array{fee_head_id: int, fee_head_name: string, amount: float, frequency: string}>
     */
    private static function filterBreakdownForAdmissionType(Student $student, array $breakdown, AcademicSession $session): array
    {
        if ($breakdown === []) {
            return [];
        }

        $type = strtolower(self::admissionTypeForSession($student, $session));

        return array_values(array_filter(
            $breakdown,
            static function (array $row) use ($type): bool {
                // Fee Structure "For students" (All / New / Old); lines saved before that option
                // existed fall back to the Admission / Registration = New-only rule.
                $appliesTo = $row['applies_to'] ?? self::defaultAppliesTo((string) ($row['fee_head_name'] ?? ''));

                return $appliesTo === 'all' || $appliesTo === $type;
            }
        ));
    }

    /**
     * "New" in the session the student was admitted for, "Old" in every later session, "None"
     * in a session before it (not admitted yet: neither New-only nor Old-only fees apply).
     *
     * The admission session is the one containing the admission date — or, for an advance
     * admission taken Oct–Mar for the coming year (first class/session record in the next
     * session), that next session; the same rule the workbook import uses. The profile's
     * Admission Type is the student's status today, so it is only the fallback for a student
     * without an admission date.
     */
    public static function admissionTypeForSession(Student $student, AcademicSession $session): string
    {
        $admitted = self::admissionDateFor($student);
        if ($admitted === null) {
            return strtolower(trim((string) (self::admissionTypeFor($student) ?? 'New'))) === 'old' ? 'Old' : 'New';
        }

        $start = substr((string) $session->start_date, 0, 10);
        $admissionSession = self::admissionSessionFor($student, $admitted);
        if ($admissionSession === null) {
            // Admission date outside every session: compare with this session's start only.
            return $admitted < $start ? 'Old' : 'New';
        }
        if ((int) $admissionSession->id === (int) $session->id) {
            return 'New';
        }

        return substr((string) $admissionSession->start_date, 0, 10) < $start ? 'Old' : 'None';
    }

    private static function admissionSessionFor(Student $student, string $admitted): ?AcademicSession
    {
        $sessions = self::$sessionsCache ??= AcademicSession::query()->orderBy('start_date')->get();
        $index = $sessions->search(fn (AcademicSession $s) => substr((string) $s->start_date, 0, 10) <= $admitted
            && substr((string) $s->end_date, 0, 10) >= $admitted);
        $byDate = $index === false ? null : $sessions[$index];
        // The next session: after the one containing the date, or (date before any session that
        // contains it) the first session starting after the date.
        $next = $index === false
            ? $sessions->first(fn (AcademicSession $s) => substr((string) $s->start_date, 0, 10) > $admitted)
            : ($sessions[$index + 1] ?? null);
        $month = (int) substr($admitted, 5, 2);
        $nextStart = $next ? substr((string) $next->start_date, 0, 10) : null;
        // Advance admission = for the session starting within the next 6 months, never a later one.
        if ($next && ($month >= 10 || $month <= 3)
            && $nextStart <= date('Y-m-d', strtotime($admitted.' +6 months'))
            && self::firstEnrolledSessionFor($student) === self::normalizeSessionName((string) $next->name)) {
            return $next;
        }

        return $byDate;
    }

    private static function firstEnrolledSessionFor(Student $student): ?string
    {
        if (array_key_exists($student->id, self::$firstSessionCache)) {
            return self::$firstSessionCache[$student->id];
        }

        $first = null;
        foreach (StudentSessionHistory::query()->where('student_id', $student->id)->pluck('session') as $raw) {
            $name = self::normalizeSessionName((string) $raw);
            if ($name !== null && ($first === null || $name < $first)) {
                $first = $name;
            }
        }

        return self::$firstSessionCache[$student->id] = $first;
    }

    /** "2025-26", "2025-2026", "2025 - 2026" → "2025-2026"; null when not a session name. */
    private static function normalizeSessionName(string $raw): ?string
    {
        if (! preg_match('/^\s*(\d{4})\s*-\s*(\d{2}|\d{4})\s*$/', $raw, $m)) {
            return null;
        }

        return $m[1].'-'.(strlen($m[2]) === 2 ? substr($m[1], 0, 2).$m[2] : $m[2]);
    }

    private static function admissionDateFor(Student $student): ?string
    {
        if (array_key_exists($student->id, self::$admissionDateCache)) {
            return self::$admissionDateCache[$student->id];
        }

        $date = array_key_exists('admission_date', $student->getAttributes())
            ? $student->admission_date
            : Student::query()->whereKey($student->id)->value('admission_date');

        return self::$admissionDateCache[$student->id] = $date ? substr((string) $date, 0, 10) : null;
    }

    private static function admissionTypeFor(Student $student): ?string
    {
        if (array_key_exists($student->id, self::$admissionTypeCache)) {
            return self::$admissionTypeCache[$student->id];
        }

        $type = $student->relationLoaded('udiseDetail')
            ? ($student->udiseDetail?->admission_type)
            : StudentUdiseDetail::query()->where('student_id', $student->id)->value('admission_type');

        self::$admissionTypeCache[$student->id] = $type;

        return $type;
    }

    /**
     * Paid amount that can offset the given breakdown heads only (so Old students' imported
     * Admission/Registration payments do not reduce Session Fee due).
     *
     * @param  list<array{fee_head_id: int, fee_head_name: string, amount: float, frequency: string}>  $breakdown
     */
    private static function paidTowardBreakdown(Student $student, AcademicSession $session, array $breakdown): float
    {
        $allowed = [];
        foreach ($breakdown as $row) {
            $hid = (int) ($row['fee_head_id'] ?? 0);
            if ($hid) {
                $allowed[$hid] = true;
            }
        }

        if ($allowed === []) {
            return 0.0;
        }

        $payments = self::cachedPayments($student, $session);
        if ($payments === null) {
            $payments = FeePayment::slicesFor(FeePayment::query()
                ->where('student_id', $student->id)
                ->touchingSessions([$session->id])
                ->where(function ($q) {
                    $q->whereNull('status')->orWhereNotIn('status', ['Refunded', 'Rolled Back']);
                })
                ->get(['academic_session_id', 'multi_session', 'items', 'amount', 'discount_amount', 'fine_amount', 'session_fines', 'refunded_amount']), [$session->id]);
        }

        $paid = 0.0;
        foreach ($payments as $payment) {
            $gross = (float) $payment->amount;
            $net = $gross - (float) $payment->refunded_amount;
            if ($net <= 0 || $gross <= 0) {
                continue;
            }
            $scale = $net / $gross;

            foreach ($payment->items ?? [] as $item) {
                $hid = (int) ($item['fee_head_id'] ?? 0);
                if (! isset($allowed[$hid])) {
                    continue;
                }
                $paid += (float) ($item['amount'] ?? 0) * $scale;
            }
        }

        return round($paid, 2);
    }

    /**
     * Legacy per-class fee structure fallback, used when no ErpFeeStructurePlan matches this
     * student. The query is only scoped by class + session (never per-student), so it's cached
     * the same way planBreakdown() is — without this, forStudent() re-ran it for every student.
     *
     * @return list<array{fee_head_id: int, fee_head_name: string, amount: float, frequency: string}>
     */
    private static function legacyStructureBreakdown(Student $student, AcademicSession $session): array
    {
        if (! $student->school_class_id) {
            return [];
        }

        $cacheKey = $session->id.'|'.$student->school_class_id;
        if (isset(self::$legacyCache[$cacheKey])) {
            return self::$legacyCache[$cacheKey];
        }

        $structure = ErpFeeStructure::with('feeHead')
            ->where('school_class_id', $student->school_class_id)
            ->where('academic_session_id', $session->id)
            ->get();

        return self::$legacyCache[$cacheKey] = $structure->map(fn (ErpFeeStructure $s) => [
            'fee_head_id' => $s->fee_head_id,
            'fee_head_name' => $s->feeHead->name,
            'amount' => (float) $s->amount,
            'frequency' => $s->frequency,
        ])->values()->all();
    }

    /** @return list<array{fee_head_id: int, fee_head_name: string, amount: float, frequency: string}> */
    private static function planBreakdown(Student $student, AcademicSession $session): array
    {
        if (! $student->school_class_id) {
            return [];
        }

        $cacheKey = $session->id.'|'
            .$student->school_class_id.'|'
            .($student->branch_id ?? 0).'|'
            .($student->section_id ?? 0);

        if (isset(self::$planCache[$cacheKey])) {
            return self::$planCache[$cacheKey];
        }

        $plans = ErpFeeStructurePlan::query()
            ->with('items')
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->whereHas('scopes', function ($q) use ($student) {
                $q->where('school_class_id', $student->school_class_id)
                    ->where(function ($q) use ($student) {
                        $q->whereNull('branch_id');
                        if ($student->branch_id) {
                            $q->orWhere('branch_id', $student->branch_id);
                        }
                    })
                    ->where(function ($q) use ($student) {
                        $q->whereNull('section_id');
                        if ($student->section_id) {
                            $q->orWhere('section_id', $student->section_id);
                        }
                    });
            })
            ->get();

        if ($plans->isEmpty()) {
            return self::$planCache[$cacheKey] = [];
        }

        self::ensureHeadsLoaded();

        $rows = [];
        foreach ($plans as $plan) {
            foreach ($plan->items as $item) {
                /** @var ErpFeeStructurePlanItem $item */
                $headId = self::resolveHeadId($item->label, $plan->type);
                $row = [
                    'fee_head_id' => $headId,
                    'fee_head_name' => $item->label,
                    'amount' => (float) $item->amount,
                    'frequency' => $item->frequency,
                ];
                // Fee Structure "Apply in months" (e.g. Examination Fee in Sep + Mar) — see FeeSchedule.
                $months = \App\Support\FeeSchedule::normalizeMonths($item->months);
                if ($months !== [] && in_array($item->frequency, ['monthly', 'quarterly'], true)) {
                    $row['months'] = $months;
                }
                // Fee Structure "For students": all / new / old (null = default rule).
                if (in_array($item->applies_to, ['all', 'new', 'old'], true)) {
                    $row['applies_to'] = $item->applies_to;
                }
                $rows[] = $row;
            }
        }

        return self::$planCache[$cacheKey] = $rows;
    }

    private static function ensureHeadsLoaded(): void
    {
        if (self::$headsLoaded) {
            return;
        }
        self::$headsByName = FeeHead::query()->pluck('id', 'name')->all();
        self::$headsLoaded = true;
    }

    private static function resolveHeadId(string $name, string $type): int
    {
        self::ensureHeadsLoaded();
        if (! isset(self::$headsByName[$name])) {
            $head = FeeHead::query()->firstOrCreate(
                ['name' => $name],
                ['description' => $type.' fee type']
            );
            self::$headsByName[$name] = $head->id;
        }

        return (int) self::$headsByName[$name];
    }
}
