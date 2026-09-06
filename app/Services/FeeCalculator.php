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
use App\Models\StudentUdiseDetail;
use Illuminate\Support\Collection;

class FeeCalculator
{
    /** One-time heads charged only for New admissions (not Old). */
    private const NEW_ONLY_FEE_HEADS = [
        'admission fee',
        'registration fee',
    ];

    /** @var array<string, int> */
    private static array $headsByName = [];

    private static bool $headsLoaded = false;

    /** @var array<string, list<array{fee_head_id: int, fee_head_name: string, amount: float, frequency: string}>> */
    private static array $planCache = [];

    /** @var array<string, list<array{fee_head_id: int, fee_head_name: string, amount: float, frequency: string}>> sessionId|classId => breakdown */
    private static array $legacyCache = [];

    /** @var array<string, float> studentId:sessionId => paid */
    private static array $paidCache = [];

    /** @var array<int, Collection<int, FeeDiscount>> */
    private static array $discountCache = [];

    /** @var array<int, string|null> student_id => admission_type */
    private static array $admissionTypeCache = [];

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

        $paidRows = FeePayment::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('academic_session_id', $sessionIds)
            ->selectRaw('student_id, academic_session_id, COALESCE(SUM(amount - refunded_amount), 0) as paid')
            ->groupBy('student_id', 'academic_session_id')
            ->get();

        foreach ($paidRows as $row) {
            self::$paidCache[$row->student_id.':'.$row->academic_session_id] = (float) $row->paid;
        }

        foreach ($studentIds as $id) {
            foreach ($sessionIds as $sid) {
                self::$paidCache[$id.':'.$sid] ??= 0.0;
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

        self::$warmed = true;
    }

    public static function flushRuntimeCache(): void
    {
        self::$planCache = [];
        self::$legacyCache = [];
        self::$paidCache = [];
        self::$discountCache = [];
        self::$admissionTypeCache = [];
        self::$warmed = false;
    }

    /**
     * Compute a student's fee position for a session: what the class-wise fee structure
     * says they owe, minus discounts, minus what they've already paid (net of refunds).
     *
     * Admission Type (student_udise_details.admission_type):
     * - New: Admission Fee + Registration Fee + Session Fee (+ other structure heads)
     * - Old: Session Fee (+ other structure heads); Admission/Registration are not charged
     */
    public static function forStudent(Student $student, ?AcademicSession $session = null): array
    {
        $session ??= AcademicSession::where('is_current', true)->first();

        if (! $session) {
            return ['session' => null, 'breakdown' => [], 'total_fee' => 0, 'total_discount' => 0, 'total_paid' => 0, 'due' => 0];
        }

        $breakdown = self::planBreakdown($student, $session);

        if ($breakdown === []) {
            $breakdown = self::legacyStructureBreakdown($student, $session);
        }

        $breakdown = self::filterBreakdownForAdmissionType($student, $breakdown);

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
            'admission_type' => self::admissionTypeFor($student) ?? 'New',
        ];
    }

    /**
     * Drop Admission / Registration Fee lines for Old students so Fee Due / Pay Fee
     * only show Session Fee (and other non-new-only heads) as chargeable.
     *
     * @param  list<array{fee_head_id: int, fee_head_name: string, amount: float, frequency: string}>  $breakdown
     * @return list<array{fee_head_id: int, fee_head_name: string, amount: float, frequency: string}>
     */
    private static function filterBreakdownForAdmissionType(Student $student, array $breakdown): array
    {
        if ($breakdown === []) {
            return [];
        }

        $type = strtolower(trim((string) (self::admissionTypeFor($student) ?? 'New')));
        if ($type !== 'old') {
            return $breakdown;
        }

        return array_values(array_filter(
            $breakdown,
            static function (array $row): bool {
                $name = strtolower(trim((string) ($row['fee_head_name'] ?? '')));

                return ! in_array($name, self::NEW_ONLY_FEE_HEADS, true);
            }
        ));
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

        $payments = FeePayment::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where(function ($q) {
                $q->whereNull('status')->orWhereNotIn('status', ['Refunded', 'Rolled Back']);
            })
            ->get(['items', 'amount', 'refunded_amount']);

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
                $rows[] = [
                    'fee_head_id' => $headId,
                    'fee_head_name' => $item->label,
                    'amount' => (float) $item->amount,
                    'frequency' => $item->frequency,
                ];
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
