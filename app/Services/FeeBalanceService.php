<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\FeeDiscount;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\ManualFeeDue;
use App\Models\Student;
use App\Models\StudentFeeDiscountMonth;
use App\Models\StudentFeeMonth;
use App\Models\StudentTransport;
use App\Support\FeeSchedule;
use Illuminate\Support\Collection;

/**
 * Shared fee balance math used by Fee Receipt, Pay Fee, and Fee Due.
 * Remaining dues are month-aware (FIFO attribution) and include transport.
 */
class FeeBalanceService
{
    /** @var array<int, StudentTransport|null> */
    private static array $transportByStudent = [];

    private static ?int $transportHeadId = null;

    private static bool $transportWarmed = false;

    /** @var array<string, list<int>> studentId:sessionId => calendar month numbers (1–12) */
    private static array $waiverMonthsByKey = [];

    private static bool $waiverWarmed = false;

    /** @var array<string, array{fee: ?list<int>, transport: ?list<int>}> studentId:sessionId => saved Fee Months */
    private static array $feeMonthsByKey = [];

    /** @var array<string, ?int> Y-m => academic session id containing it */
    private static array $sessionIdByMonthKey = [];

    /** @var list<array{id: int, keys: array<string, true>}>|null */
    private static ?array $sessionMonthIndex = null;

    /** @var array<int, Collection<int, FeeDiscount>> studentId => FeeDiscount rows */
    private static array $structureDiscountsByStudent = [];

    private static bool $structureDiscountsWarmed = false;

    public static function flushRuntimeCache(): void
    {
        self::$transportByStudent = [];
        self::$transportHeadId = null;
        self::$transportWarmed = false;
        self::$waiverMonthsByKey = [];
        self::$waiverWarmed = false;
        self::$feeMonthsByKey = [];
        self::$sessionIdByMonthKey = [];
        self::$sessionMonthIndex = null;
        self::$structureDiscountsByStudent = [];
        self::$structureDiscountsWarmed = false;
    }

    // ── Fee Months / Transport Fee Months (People > Students, saved per student + session) ─────

    /** Academic session id a Y-m month belongs to (null when no session covers it). */
    public function sessionIdForMonthKey(string $monthKey): ?int
    {
        $monthKey = substr($monthKey, 0, 7);
        if (array_key_exists($monthKey, self::$sessionIdByMonthKey)) {
            return self::$sessionIdByMonthKey[$monthKey];
        }

        if (self::$sessionMonthIndex === null) {
            self::$sessionMonthIndex = AcademicSession::query()->orderBy('start_date')->get()
                ->map(fn (AcademicSession $s) => [
                    'id' => (int) $s->id,
                    'keys' => array_fill_keys(array_column($s->months(), 'key'), true),
                ])->all();
        }

        foreach (self::$sessionMonthIndex as $row) {
            if (isset($row['keys'][$monthKey])) {
                return self::$sessionIdByMonthKey[$monthKey] = $row['id'];
            }
        }

        return self::$sessionIdByMonthKey[$monthKey] = null;
    }

    /**
     * Saved Fee Months for a student in a session; each list is null when not chosen yet.
     *
     * @return array{fee: ?list<int>, transport: ?list<int>}
     */
    public function savedFeeMonths(Student $student, int $sessionId): array
    {
        $key = $student->id.':'.$sessionId;
        if (! array_key_exists($key, self::$feeMonthsByKey)) {
            $row = StudentFeeMonth::query()
                ->where('student_id', $student->id)
                ->where('academic_session_id', $sessionId)
                ->first(['fee_months', 'transport_months']);
            self::$feeMonthsByKey[$key] = [
                'fee' => is_array($row?->fee_months) ? FeeSchedule::normalizeMonths($row->fee_months) : null,
                'transport' => is_array($row?->transport_months) ? FeeSchedule::normalizeMonths($row->transport_months) : null,
            ];
        }

        return self::$feeMonthsByKey[$key];
    }

    /** Prefetch saved Fee Months for a cohort (avoids one query per student in reports). */
    public function warmFeeMonths(array $studentIds, array $sessionIds): void
    {
        if ($studentIds === [] || $sessionIds === []) {
            return;
        }
        foreach ($studentIds as $sid) {
            foreach ($sessionIds as $sessId) {
                self::$feeMonthsByKey[$sid.':'.$sessId] = ['fee' => null, 'transport' => null];
            }
        }
        foreach (StudentFeeMonth::query()->whereIn('student_id', $studentIds)->whereIn('academic_session_id', $sessionIds)->get() as $row) {
            self::$feeMonthsByKey[$row->student_id.':'.$row->academic_session_id] = [
                'fee' => is_array($row->fee_months) ? FeeSchedule::normalizeMonths($row->fee_months) : null,
                'transport' => is_array($row->transport_months) ? FeeSchedule::normalizeMonths($row->transport_months) : null,
            ];
        }
    }

    /**
     * Is the student charged school fees (structure heads) in this Y-m month? The saved Fee Months
     * of that month's session decide; with none saved, the old rule — on/after Fee Start Month
     * (or admission month) — applies unchanged.
     */
    public function isFeeMonth(Student $student, string $monthKey): bool
    {
        $monthKey = substr($monthKey, 0, 7);
        $sessionId = $this->sessionIdForMonthKey($monthKey);
        if ($sessionId) {
            $saved = $this->savedFeeMonths($student, $sessionId)['fee'];
            if ($saved !== null) {
                return in_array((int) substr($monthKey, 5, 2), $saved, true);
            }
        }

        $start = $student->feeStartMonthKey();

        return ! $start || $monthKey >= $start;
    }

    /**
     * Is transport fare charged in this Y-m month? Saved Transport Fee Months decide (never before
     * the transport Start Date); with none saved, the old rule — on/after the transport fee start
     * and the student's fee start — applies unchanged.
     */
    public function isTransportMonth(Student $student, string $monthKey): bool
    {
        $monthKey = substr($monthKey, 0, 7);
        $assignment = $this->activeTransport($student);

        $sessionId = $this->sessionIdForMonthKey($monthKey);
        if ($sessionId) {
            $saved = $this->savedFeeMonths($student, $sessionId)['transport'];
            if ($saved !== null) {
                $startKey = $assignment?->start_date?->format('Y-m');

                return in_array((int) substr($monthKey, 5, 2), $saved, true)
                    && (! $startKey || $monthKey >= $startKey);
            }
        }

        $transportStart = $assignment?->feeStartMonthKey();
        $studentStart = $student->feeStartMonthKey();

        return (! $transportStart || $monthKey >= $transportStart)
            && (! $studentStart || $monthKey >= $studentStart);
    }

    private function activeTransport(Student $student): ?StudentTransport
    {
        if (self::$transportWarmed && array_key_exists((int) $student->id, self::$transportByStudent)) {
            return self::$transportByStudent[(int) $student->id];
        }

        return StudentTransport::query()
            ->where('student_id', $student->id)
            ->where('status', 'Active')
            ->with('routeStop:id,fare')
            ->first();
    }

    /**
     * Fee Months as shown on People > Students for one session: each month with whether it is
     * ticked, and what has been paid / is still due on it (fee heads and transport separately).
     * A month with any payment or waiver on it is locked (cannot be unticked).
     */
    public function feeMonthsOverview(Student $student, AcademicSession $session): array
    {
        $calc = FeeCalculator::forStudent($student, $session);
        $info = $this->paidByHead($student, $session, $calc);
        [$fare, $transportHeadId] = $this->transportFareAndHead($student);
        $transportKey = $transportHeadId ? (string) $transportHeadId : '';
        $feeHeadIds = collect($calc['breakdown'] ?? [])->pluck('fee_head_id')->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $transportHeadId && $id === (int) $transportHeadId)->unique()->values()->all();
        $saved = $this->savedFeeMonths($student, (int) $session->id);

        $touched = function (string $monthKey, bool $transport) use ($info, $transportKey): float {
            $sum = 0.0;
            foreach (['by_month', 'discount_by_month'] as $bucket) {
                foreach ($info[$bucket] ?? [] as $headKey => $months) {
                    // Array keys come back as ints for numeric head ids — compare as strings.
                    if (((string) $headKey === $transportKey) !== $transport) {
                        continue;
                    }
                    $sum += (float) ($months[$monthKey] ?? 0);
                }
            }

            return $sum;
        };

        $months = [];
        foreach ($session->months() as $m) {
            $key = $m['key'];
            $fee = $this->remainingForMonths($student, $session, [$key], $calc, $info, $feeHeadIds ?: [0]);
            $tr = $fare > 0 && $transportHeadId
                ? $this->remainingForMonths($student, $session, [$key], $calc, $info, [(int) $transportHeadId])
                : ['charge' => 0.0, 'paid' => 0.0, 'discount' => 0.0, 'due' => 0.0];
            $feeTouched = $touched($key, false);
            $trTouched = $touched($key, true);

            $months[] = [
                'key' => $key,
                'month' => (int) substr($key, 5, 2),
                'label' => \Carbon\Carbon::createFromFormat('Y-m', $key)->format('F'),
                'fee' => [
                    'selected' => $this->isFeeMonth($student, $key),
                    'locked' => $feeTouched > 0.0001,
                    'charge' => $fee['charge'], 'paid' => $fee['paid'], 'discount' => $fee['discount'], 'due' => $fee['due'],
                ],
                'transport' => [
                    'selected' => $this->isTransportMonth($student, $key),
                    'locked' => $trTouched > 0.0001,
                    'charge' => $tr['charge'], 'paid' => $tr['paid'], 'discount' => $tr['discount'], 'due' => $tr['due'],
                ],
            ];
        }

        return [
            'session' => ['id' => $session->id, 'name' => $session->name],
            'fee_saved' => $saved['fee'] !== null,
            'transport_saved' => $saved['transport'] !== null,
            'transport_fare' => (float) $fare,
            'transport_start' => $this->activeTransport($student)?->start_date?->format('Y-m-d'),
            'months' => $months,
        ];
    }

    /**
     * Prefetch active transport assignments for a student cohort (avoids N+1 in remaining math).
     *
     * @param  Collection<int, Student>|array<int, Student>  $students
     * @param  mixed  $sessions  Unused — kept for call-site symmetry with FeeCalculator::warmForStudents.
     */
    public function warmForStudents($students, $sessions = null): void
    {
        $studentIds = collect($students)->pluck('id')->filter()->unique()->values()->all();
        if ($studentIds === []) {
            self::$transportWarmed = true;

            return;
        }

        $rows = StudentTransport::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', 'Active')
            ->with('routeStop:id,fare')
            ->get();

        self::$transportByStudent = [];
        foreach ($studentIds as $id) {
            self::$transportByStudent[(int) $id] = null;
        }
        foreach ($rows as $row) {
            self::$transportByStudent[(int) $row->student_id] = $row;
        }

        self::$transportHeadId = (int) (FeeHead::query()->where('name', 'Transport')->value('id') ?? 0);
        self::$transportWarmed = true;

        $sessionIds = collect($sessions instanceof AcademicSession ? [$sessions] : ($sessions ?? []))
            ->pluck('id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $this->warmWaiverMonths($studentIds, $sessionIds);
        $this->warmFeeMonths($studentIds, $sessionIds);
        $this->warmStructureDiscounts($studentIds);
    }

    /**
     * Prefetch fee_discounts for a cohort (Fee Discounts / FEES DISCOUNT import).
     *
     * @param  list<int>  $studentIds
     */
    public function warmStructureDiscounts(array $studentIds): void
    {
        self::$structureDiscountsByStudent = [];
        foreach ($studentIds as $id) {
            self::$structureDiscountsByStudent[(int) $id] = collect();
        }
        if ($studentIds === []) {
            self::$structureDiscountsWarmed = true;

            return;
        }

        foreach (FeeDiscount::query()->whereIn('student_id', $studentIds)->get()->groupBy('student_id') as $sid => $rows) {
            self::$structureDiscountsByStudent[(int) $sid] = $rows->values();
        }
        self::$structureDiscountsWarmed = true;
    }

    /** @return Collection<int, FeeDiscount> */
    private function structureDiscountsFor(Student $student): Collection
    {
        $id = (int) $student->id;
        if (self::$structureDiscountsWarmed) {
            return self::$structureDiscountsByStudent[$id] ?? collect();
        }

        return FeeDiscount::query()->where('student_id', $id)->get();
    }

    /**
     * Structure FeeDiscount pools minus discounts already on payment items for that head
     * (e.g. FEES DISCOUNT stamped onto receipts) so due math never counts the same ₹ twice.
     *
     * @param  array<int, float>  $pools
     * @param  array<string, float>  $paidDiscounts  headKey => amount from paidByHead()
     * @return array<int, float>
     */
    private function structurePoolsNetOfPayments(array $pools, array $paidDiscounts): array
    {
        foreach ($pools as $headId => $amount) {
            if ((int) $headId <= 0) {
                continue;
            }
            $already = (float) ($paidDiscounts[(string) $headId] ?? 0);
            $pools[$headId] = max(0.0, (float) $amount - $already);
        }

        return $pools;
    }

    /**
     * Prefetch discount/waiver months for a cohort.
     *
     * @param  list<int>  $studentIds
     * @param  list<int>  $sessionIds
     */
    public function warmWaiverMonths(array $studentIds, array $sessionIds): void
    {
        if ($studentIds === [] || $sessionIds === []) {
            self::$waiverWarmed = true;

            return;
        }

        foreach ($studentIds as $sid) {
            foreach ($sessionIds as $sessId) {
                self::$waiverMonthsByKey[$sid.':'.$sessId] = [];
            }
        }

        $rows = StudentFeeDiscountMonth::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('academic_session_id', $sessionIds)
            ->get(['student_id', 'academic_session_id', 'months']);

        foreach ($rows as $row) {
            self::$waiverMonthsByKey[$row->student_id.':'.$row->academic_session_id] = $row->monthNumbers();
        }

        self::$waiverWarmed = true;
    }

    /**
     * Calendar month numbers (1–12) waived for this student in the session.
     *
     * @return list<int>
     */
    public function waiverMonthNumbers(Student $student, ?AcademicSession $session): array
    {
        if (! $session) {
            return [];
        }

        // Once Fee Months are saved for the session they replace the old Discount Months (an
        // unticked month is simply not charged); the old discount rows are kept, just unused.
        if ($this->savedFeeMonths($student, (int) $session->id)['fee'] !== null) {
            return [];
        }

        $key = $student->id.':'.$session->id;
        if (array_key_exists($key, self::$waiverMonthsByKey)) {
            return self::$waiverMonthsByKey[$key];
        }

        $row = StudentFeeDiscountMonth::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->first();

        $months = $row?->monthNumbers() ?? [];
        self::$waiverMonthsByKey[$key] = $months;

        return $months;
    }

    public function isMonthWaived(Student $student, ?AcademicSession $session, string $monthKey): bool
    {
        if (! $session || ! preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            return false;
        }

        $monthNum = (int) substr($monthKey, 5, 2);

        return in_array($monthNum, $this->waiverMonthNumbers($student, $session), true);
    }

    /**
     * Y-m keys in the session that are configured as discount months (and after fee start).
     *
     * @return list<string>
     */
    public function waivedMonthKeys(Student $student, ?AcademicSession $session): array
    {
        if (! $session) {
            return [];
        }

        $nums = array_fill_keys($this->waiverMonthNumbers($student, $session), true);
        if ($nums === []) {
            return [];
        }

        $keys = collect($session->months())->map(fn ($m) => $m['key'] ?? null)->filter()->values()->all();
        $keys = $this->filterMonthsFromFeeStart($student, $keys);

        return array_values(array_filter(
            $keys,
            fn ($k) => isset($nums[(int) substr((string) $k, 5, 2)])
        ));
    }

    /** Y-m key from which this student is billable (fee start or admission month). */
    public function feeStartKey(Student $student): ?string
    {
        return $student->feeStartMonthKey();
    }

    /**
     * Keep only months the student is charged anything in — a Fee Month (school fees) or a
     * Transport Fee Month. With nothing saved this is exactly the old "from fee start" filter.
     *
     * @param  list<string>  $monthKeys
     * @return list<string>
     */
    public function filterMonthsFromFeeStart(Student $student, array $monthKeys): array
    {
        [$fare] = $this->transportFareAndHead($student);

        return array_values(array_filter(
            $monthKeys,
            fn ($k) => $this->isFeeMonth($student, (string) $k) || ($fare > 0 && $this->isTransportMonth($student, (string) $k))
        ));
    }

    /** First Fee Month of the session — annual / one-time heads are charged here. */
    public function firstBillableMonth(Student $student, ?AcademicSession $session): ?string
    {
        if (! $session) {
            return $this->feeStartKey($student);
        }

        foreach ($session->months() as $m) {
            if (! empty($m['key']) && $this->isFeeMonth($student, $m['key'])) {
                return $m['key'];
            }
        }

        return null;
    }

    /**
     * @param  array|null  $calc  Optional FeeCalculator::forStudent() result — skips a re-fetch.
     * @return array{
     *   totals: array<string, float>,
     *   discounts: array<string, float>,
     *   by_month: array<string, array<string, float>>,
     *   discount_by_month: array<string, array<string, float>>
     * }
     */
    public function paidByHead(Student $student, ?AcademicSession $session = null, ?array $calc = null): array
    {
        $session ??= AcademicSession::where('is_current', true)->first();

        $totals = [];
        $discounts = [];
        $byMonth = [];
        $discountByMonth = [];

        $unitByHead = [];
        $freqByHead = [];
        $monthsByHead = [];
        if ($session) {
            $calc ??= FeeCalculator::forStudent($student, $session);
            foreach ($calc['breakdown'] ?? [] as $row) {
                $hid = (int) ($row['fee_head_id'] ?? 0);
                if (! $hid) {
                    continue;
                }
                $unitByHead[$hid] = (float) ($row['amount'] ?? 0);
                $freqByHead[$hid] = strtolower(str_replace(' ', '_', (string) ($row['frequency'] ?? 'monthly')));
                $monthsByHead[$hid] = FeeSchedule::chosenMonths($row);
            }
        }

        [$transportFare, $transportHeadId] = $this->transportFareAndHead($student);
        if ($transportFare > 0 && $transportHeadId) {
            $unitByHead[$transportHeadId] = $transportFare;
            $freqByHead[$transportHeadId] = 'monthly';
        }

        $payments = null;
        if ($session) {
            $payments = FeeCalculator::cachedPayments($student, $session);
        }

        if ($payments === null) {
            $query = FeePayment::query()
                ->where('student_id', $student->id)
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereNotIn('status', ['Refunded', 'Rolled Back']);
                });

            if ($session) {
                $query->touchingSessions([$session->id]);
            }

            $payments = $query->orderBy('id')->get(['id', 'academic_session_id', 'multi_session', 'items', 'amount', 'discount_amount', 'fine_amount', 'session_fines', 'refunded_amount', 'status']);
            if ($session) {
                $payments = FeePayment::slicesFor($payments, [$session->id]);
            }
        }

        foreach ($payments as $payment) {
            $gross = (float) $payment->amount;
            $net = $gross - (float) $payment->refunded_amount;
            if ($gross <= 0) {
                // ₹0 receipt = a fee waiver (discount-only lines): nothing to refund, so its
                // discounts count in full; its paid amounts are all 0.
                $scale = 1.0;
            } elseif ($net <= 0) {
                continue;
            } else {
                $scale = $net / $gross;
            }

            foreach ($payment->items ?? [] as $item) {
                $feeHeadId = (int) ($item['fee_head_id'] ?? 0);
                if (! $feeHeadId) {
                    continue;
                }

                $key = (string) $feeHeadId;
                $itemAmount = (float) ($item['amount'] ?? 0) * $scale;
                $itemDiscount = (float) ($item['discount'] ?? 0) * $scale;

                $totals[$key] = ($totals[$key] ?? 0.0) + $itemAmount;
                $discounts[$key] = ($discounts[$key] ?? 0.0) + $itemDiscount;

                $itemMonths = [];
                if (! empty($item['months']) && is_array($item['months'])) {
                    $itemMonths = $item['months'];
                } elseif (! empty($item['month'])) {
                    $itemMonths = [$item['month']];
                }

                if ($itemMonths === []) {
                    continue;
                }

                $freq = $freqByHead[$feeHeadId] ?? 'monthly';
                $unit = (float) ($unitByHead[$feeHeadId] ?? 0);

                $already = [];
                foreach (array_keys($byMonth[$key] ?? []) as $mk) {
                    $already[$mk] = (float) ($byMonth[$key][$mk] ?? 0)
                        + (float) ($discountByMonth[$key][$mk] ?? 0);
                }

                $shares = FeeMonthAllocator::distribute(
                    $itemAmount,
                    $itemDiscount,
                    $itemMonths,
                    $unit,
                    $already,
                    $freq,
                    $monthsByHead[$feeHeadId] ?? []
                );

                foreach ($shares as $monthKey => $share) {
                    $byMonth[$key][$monthKey] = ($byMonth[$key][$monthKey] ?? 0.0) + (float) $share['paid'];
                    $discountByMonth[$key][$monthKey] = ($discountByMonth[$key][$monthKey] ?? 0.0) + (float) $share['discount'];
                }
            }
        }

        return [
            'totals' => $totals,
            'discounts' => $discounts,
            'by_month' => $byMonth,
            'discount_by_month' => $discountByMonth,
        ];
    }

    /**
     * @param  list<array{fee_head_id?: int, amount?: float|int, frequency?: string}>|null  $breakdown
     * @param  array{totals: array<string, float>, discounts: array<string, float>, by_month: array<string, array<string, float>>, discount_by_month: array<string, array<string, float>>}|null  $paidInfo
     * @return list<string>
     */
    public function paidMonths(
        Student $student,
        ?AcademicSession $session = null,
        ?array $breakdown = null,
        ?array $paidInfo = null
    ): array {
        $session ??= AcademicSession::where('is_current', true)->first();

        if ($breakdown === null) {
            $calc = $session ? FeeCalculator::forStudent($student, $session) : ['breakdown' => []];
            $breakdown = $calc['breakdown'] ?? [];
        }
        $breakdown = collect($breakdown);
        $paidInfo ??= $this->paidByHead($student, $session);
        $paidTotals = $paidInfo['totals'];
        $paidDiscounts = $paidInfo['discounts'];
        $paidByMonth = $paidInfo['by_month'];
        $discountByMonth = $paidInfo['discount_by_month'];

        $normalizeFreq = static function ($row): string {
            return strtolower(str_replace(' ', '_', (string) ($row['frequency'] ?? 'monthly')));
        };

        $monthlyPlans = $breakdown->filter(fn ($r) => $normalizeFreq($r) === 'monthly')->values();
        $quarterlyPlans = $breakdown->filter(fn ($r) => $normalizeFreq($r) === 'quarterly')->values();
        $sessionPlans = $breakdown->filter(fn ($r) => in_array($normalizeFreq($r), ['annual', 'one_time'], true))->values();

        $sessionMonthKeys = $session
            ? collect($session->months())->map(fn ($m) => $m['key'] ?? null)->filter()->values()->all()
            : [];
        $firstBillableMonth = $this->firstBillableMonth($student, $session);
        // Months the student is charged anything in (Fee Months or Transport Fee Months).
        $billable = fn (string $key): bool => $this->filterMonthsFromFeeStart($student, [$key]) !== [];

        $monthKeys = [];
        foreach ($sessionMonthKeys as $key) {
            if (! $billable($key)) {
                continue;
            }
            $monthKeys[$key] = true;
        }
        foreach ($paidByMonth as $headMonths) {
            foreach (array_keys($headMonths) as $key) {
                if (! $billable((string) $key)) {
                    continue;
                }
                $monthKeys[$key] = true;
            }
        }

        $structurePools = $this->structurePoolsNetOfPayments(
            FeeCalculator::structureDiscountPools($this->structureDiscountsFor($student), $breakdown->all()),
            $paidDiscounts
        );

        $coveredToward = static function (string $headKey, float $charge, ?string $monthKey = null) use (
            &$structurePools,
            $paidTotals,
            $paidDiscounts,
            $paidByMonth,
            $discountByMonth
        ): float {
            if ($monthKey !== null) {
                $monthPaid = (float) ($paidByMonth[$headKey][$monthKey] ?? 0.0)
                    + (float) ($discountByMonth[$headKey][$monthKey] ?? 0.0);
            } else {
                $monthPaid = (float) ($paidTotals[$headKey] ?? 0.0) + (float) ($paidDiscounts[$headKey] ?? 0.0);
            }

            $got = min($charge, $monthPaid);
            $still = max(0.0, $charge - $got);
            if ($still > 0) {
                $headId = (int) $headKey;
                foreach ([$headId, 0] as $poolKey) {
                    if ($still <= 0) {
                        break;
                    }
                    $available = (float) ($structurePools[$poolKey] ?? 0);
                    if ($available <= 0) {
                        continue;
                    }
                    $take = min($still, $available);
                    $structurePools[$poolKey] = $available - $take;
                    $got += $take;
                    $still -= $take;
                }
            }

            return min($charge, $got);
        };

        [$transportFare, $transportHeadId, $transportFeeStart] = $this->transportFareAndHead($student);
        $transportHeadKey = $transportHeadId ? (string) $transportHeadId : '';

        $paid = [];

        foreach (array_keys($monthKeys) as $monthKey) {
            // Discount months are cleared for the student (not a payment) — treat as settled.
            if ($this->isMonthWaived($student, $session, (string) $monthKey)) {
                $paid[$monthKey] = true;

                continue;
            }

            $monthNum = (int) substr($monthKey, 5, 2);
            $feeMonth = $this->isFeeMonth($student, (string) $monthKey);
            $required = 0.0;
            $got = 0.0;

            foreach ($monthlyPlans as $plan) {
                $charge = (float) ($plan['amount'] ?? 0);
                if ($charge <= 0 || ! $feeMonth || ! FeeSchedule::chargesInMonth($plan, $monthNum)) {
                    continue;
                }
                $headKey = (string) (int) ($plan['fee_head_id'] ?? 0);
                $required += $charge;
                $got += $coveredToward($headKey, $charge, $monthKey);
            }

            foreach ($quarterlyPlans as $plan) {
                if (! $feeMonth || ! FeeSchedule::chargesInMonth($plan, $monthNum)) {
                    continue;
                }
                $charge = (float) ($plan['amount'] ?? 0);
                if ($charge <= 0) {
                    continue;
                }
                $headKey = (string) (int) ($plan['fee_head_id'] ?? 0);
                $required += $charge;
                $got += $coveredToward($headKey, $charge, $monthKey);
            }

            // Annual / one-time: due once on the first billable month (respects fee start).
            if ($firstBillableMonth && $monthKey === $firstBillableMonth) {
                foreach ($sessionPlans as $plan) {
                    $charge = (float) ($plan['amount'] ?? 0);
                    if ($charge <= 0) {
                        continue;
                    }
                    $headKey = (string) (int) ($plan['fee_head_id'] ?? 0);
                    $required += $charge;
                    $got += $coveredToward($headKey, $charge, null);
                }
            }

            if (
                $transportFare > 0
                && $transportHeadKey !== ''
                && $this->transportAppliesInMonth($student, $monthKey, $transportFeeStart)
            ) {
                $required += $transportFare;
                $got += $coveredToward($transportHeadKey, $transportFare, $monthKey);
            }

            if ($required <= 0.0) {
                continue;
            }

            if ($got + 0.0001 >= $required) {
                $paid[$monthKey] = true;
            }
        }

        $manualPaid = ManualFeeDue::query()
            ->where('student_id', $student->id)
            ->whereColumn('paid_amount', '>=', 'amount')
            ->where('amount', '>', 0)
            ->get(['month']);

        foreach ($manualPaid as $due) {
            if ($due->month) {
                $key = $due->month->format('Y-m');
                if (! $billable($key)) {
                    continue;
                }
                $paid[$key] = true;
            }
        }

        return array_values(array_keys($paid));
    }

    /**
     * Charge / paid / discount / due for a set of month keys (Fee Due scopes).
     * Matches Fee Receipt remaining math, including active transport.
     *
     * @param  list<string>  $monthKeys
     * @return array{charge: float, paid: float, discount: float, due: float}
     */
    /**
     * @param  list<string>  $monthKeys
     * @param  list<int>|null  $feeHeadIds  When set, only these fee heads (and Transport if its id is included) are counted.
     * @return array{charge: float, paid: float, discount: float, due: float, net: float}
     */
    public function remainingForMonths(
        Student $student,
        AcademicSession $session,
        array $monthKeys,
        ?array $calc = null,
        ?array $paidInfo = null,
        ?array $feeHeadIds = null
    ): array {
        $monthKeys = array_values(array_unique(array_filter($monthKeys)));
        $monthKeys = $this->filterMonthsFromFeeStart($student, $monthKeys);
        if ($monthKeys === []) {
            return ['charge' => 0.0, 'paid' => 0.0, 'discount' => 0.0, 'due' => 0.0, 'net' => 0.0];
        }

        $calc ??= FeeCalculator::forStudent($student, $session);
        $paidInfo ??= $this->paidByHead($student, $session);
        $breakdown = collect($calc['breakdown'] ?? []);

        $headFilter = null;
        if ($feeHeadIds !== null) {
            $headFilter = array_fill_keys(array_map('intval', $feeHeadIds), true);
        }

        $paidTotals = $paidInfo['totals'];
        $paidDiscounts = $paidInfo['discounts'];
        $paidByMonth = $paidInfo['by_month'];
        $discountByMonth = $paidInfo['discount_by_month'];

        // fee_discounts (Fee Discounts screen / FEES DISCOUNT workbook import) — consume as a
        // per-head pool so monthly heads share a session-level concession across months.
        // Shrink by discounts already stamped on payment items so re-import doesn't double-count.
        $structurePools = $this->structurePoolsNetOfPayments(
            FeeCalculator::structureDiscountPools($this->structureDiscountsFor($student), $breakdown->all()),
            $paidDiscounts
        );

        $normalizeFreq = static function ($row): string {
            return strtolower(str_replace(' ', '_', (string) ($row['frequency'] ?? 'monthly')));
        };

        $firstBillableMonth = $this->firstBillableMonth($student, $session);

        $charge = 0.0;
        $paid = 0.0;
        $discount = 0.0;

        [$transportFare, $transportHeadId, $transportFeeStart] = $this->transportFareAndHead($student);

        $applyUnit = static function (string $headKey, float $unit, ?string $monthKey, bool $waive = false) use (
            &$charge,
            &$paid,
            &$discount,
            &$structurePools,
            $paidTotals,
            $paidDiscounts,
            $paidByMonth,
            $discountByMonth
        ): void {
            if ($unit <= 0) {
                return;
            }
            $charge += $unit;
            if ($monthKey !== null) {
                $mPaid = (float) ($paidByMonth[$headKey][$monthKey] ?? 0);
                $mDisc = (float) ($discountByMonth[$headKey][$monthKey] ?? 0);
            } else {
                $mPaid = (float) ($paidTotals[$headKey] ?? 0);
                $mDisc = (float) ($paidDiscounts[$headKey] ?? 0);
            }

            if ($waive) {
                // Full month waiver: any real payment still counts as paid; remainder is discount (not income).
                $fromPaid = min($mPaid, $unit);
                $paid += $fromPaid;
                $discount += max(0.0, $unit - $fromPaid);

                return;
            }

            $covered = min($unit, $mPaid + $mDisc);
            $fromPaid = min($mPaid, $covered);
            $fromDisc = max(0.0, $covered - $fromPaid);

            // Apply remaining structure discount (FeeDiscount) against what payments didn't cover.
            $stillOwed = max(0.0, $unit - $fromPaid - $fromDisc);
            if ($stillOwed > 0) {
                $headId = (int) $headKey;
                foreach ([$headId, 0] as $poolKey) {
                    if ($stillOwed <= 0) {
                        break;
                    }
                    $available = (float) ($structurePools[$poolKey] ?? 0);
                    if ($available <= 0) {
                        continue;
                    }
                    $take = min($stillOwed, $available);
                    $structurePools[$poolKey] = $available - $take;
                    $fromDisc += $take;
                    $stillOwed -= $take;
                }
            }

            $paid += $fromPaid;
            $discount += $fromDisc;
        };

        foreach ($monthKeys as $monthKey) {
            $monthNum = (int) substr($monthKey, 5, 2);
            $waive = $this->isMonthWaived($student, $session, $monthKey);
            $feeMonth = $this->isFeeMonth($student, $monthKey);

            foreach ($breakdown as $plan) {
                $freq = $normalizeFreq($plan);
                $unit = (float) ($plan['amount'] ?? 0);
                $headId = (int) ($plan['fee_head_id'] ?? 0);
                $headKey = (string) $headId;
                if ($unit <= 0 || $headId === 0) {
                    continue;
                }
                if ($headFilter !== null && ! isset($headFilter[$headId])) {
                    continue;
                }

                if ($freq === 'monthly' || $freq === 'quarterly') {
                    if ($feeMonth && FeeSchedule::chargesInMonth($plan, $monthNum)) {
                        $applyUnit($headKey, $unit, $monthKey, $waive);
                    }
                } elseif (in_array($freq, ['annual', 'one_time'], true)) {
                    if ($firstBillableMonth && $monthKey === $firstBillableMonth) {
                        $applyUnit($headKey, $unit, null, $waive);
                    }
                }
            }

            if (
                $transportFare > 0
                && $transportHeadId
                && $this->transportAppliesInMonth($student, $monthKey, $transportFeeStart)
                && ($headFilter === null || isset($headFilter[(int) $transportHeadId]))
            ) {
                $applyUnit((string) $transportHeadId, $transportFare, $monthKey, $waive);
            }
        }

        $charge = round($charge, 2);
        $paid = round($paid, 2);
        $discount = round($discount, 2);
        $due = round(max(0, $charge - $paid - $discount), 2);
        $net = round($charge - $discount, 2); // net demand after concession

        return compact('charge', 'paid', 'discount', 'due', 'net');
    }

    /**
     * Per-month rows for Fee Due detail modal.
     *
     * @param  list<string>  $monthKeys
     * @return list<array{month_key: string, month: string, charge: float, paid: float, concession: float, due: float, categories: string}>
     */
    public function monthRows(
        Student $student,
        AcademicSession $session,
        array $monthKeys,
        ?array $calc = null,
        ?array $paidInfo = null
    ): array {
        $monthKeys = $this->filterMonthsFromFeeStart($student, $monthKeys);
        $calc ??= FeeCalculator::forStudent($student, $session);
        $paidInfo ??= $this->paidByHead($student, $session);
        $breakdown = collect($calc['breakdown'] ?? []);
        $paidByMonth = $paidInfo['by_month'];
        $discountByMonth = $paidInfo['discount_by_month'];
        $paidTotals = $paidInfo['totals'];
        $paidDiscounts = $paidInfo['discounts'];
        $structurePools = $this->structurePoolsNetOfPayments(
            FeeCalculator::structureDiscountPools($this->structureDiscountsFor($student), $breakdown->all()),
            $paidDiscounts
        );

        $normalizeFreq = static function ($row): string {
            return strtolower(str_replace(' ', '_', (string) ($row['frequency'] ?? 'monthly')));
        };

        $takeStructure = static function (string $headKey, float $stillOwed) use (&$structurePools): float {
            $taken = 0.0;
            $headId = (int) $headKey;
            foreach ([$headId, 0] as $poolKey) {
                if ($stillOwed <= 0) {
                    break;
                }
                $available = (float) ($structurePools[$poolKey] ?? 0);
                if ($available <= 0) {
                    continue;
                }
                $take = min($stillOwed, $available);
                $structurePools[$poolKey] = $available - $take;
                $taken += $take;
                $stillOwed -= $take;
            }

            return $taken;
        };

        $firstBillableMonth = $this->firstBillableMonth($student, $session);
        [$transportFare, $transportHeadId, $transportFeeStart] = $this->transportFareAndHead($student);

        $rows = [];
        foreach ($monthKeys as $monthKey) {
            $monthNum = (int) substr($monthKey, 5, 2);
            $waive = $this->isMonthWaived($student, $session, $monthKey);
            $cats = [];
            $charge = 0.0;
            $paid = 0.0;
            $concession = 0.0;

            foreach ($breakdown as $plan) {
                $freq = $normalizeFreq($plan);
                $unit = (float) ($plan['amount'] ?? 0);
                $name = (string) ($plan['fee_head_name'] ?? 'Fee');
                $headKey = (string) (int) ($plan['fee_head_id'] ?? 0);
                if ($unit <= 0) {
                    continue;
                }

                $include = match ($freq) {
                    'monthly', 'quarterly' => $this->isFeeMonth($student, $monthKey) && FeeSchedule::chargesInMonth($plan, $monthNum),
                    'annual', 'one_time' => $firstBillableMonth && $monthKey === $firstBillableMonth,
                    default => $firstBillableMonth && $monthKey === $firstBillableMonth,
                };
                if (! $include) {
                    continue;
                }

                if (in_array($freq, ['annual', 'one_time'], true)) {
                    $mPaid = (float) ($paidTotals[$headKey] ?? 0);
                    $mDisc = (float) ($paidDiscounts[$headKey] ?? 0);
                } else {
                    $mPaid = (float) ($paidByMonth[$headKey][$monthKey] ?? 0);
                    $mDisc = (float) ($discountByMonth[$headKey][$monthKey] ?? 0);
                }

                if ($waive) {
                    $fromPaid = min($mPaid, $unit);
                    $fromDisc = max(0.0, $unit - $fromPaid);
                } else {
                    $covered = min($unit, $mPaid + $mDisc);
                    $fromPaid = min($mPaid, $covered);
                    $fromDisc = max(0.0, $covered - $fromPaid);
                    $fromDisc += $takeStructure($headKey, max(0.0, $unit - $fromPaid - $fromDisc));
                }

                $cats[$name] = ($cats[$name] ?? 0) + $unit;
                $charge += $unit;
                $paid += $fromPaid;
                $concession += $fromDisc;
            }

            if (
                $transportFare > 0
                && $transportHeadId
                && $this->transportAppliesInMonth($student, $monthKey, $transportFeeStart)
            ) {
                $headKey = (string) $transportHeadId;
                $mPaid = (float) ($paidByMonth[$headKey][$monthKey] ?? 0);
                $mDisc = (float) ($discountByMonth[$headKey][$monthKey] ?? 0);
                if ($waive) {
                    $fromPaid = min($mPaid, $transportFare);
                    $fromDisc = max(0.0, $transportFare - $fromPaid);
                } else {
                    $covered = min($transportFare, $mPaid + $mDisc);
                    $fromPaid = min($mPaid, $covered);
                    $fromDisc = max(0.0, $covered - $fromPaid);
                    $fromDisc += $takeStructure($headKey, max(0.0, $transportFare - $fromPaid - $fromDisc));
                }
                $cats['Transport'] = ($cats['Transport'] ?? 0) + $transportFare;
                $charge += $transportFare;
                $paid += $fromPaid;
                $concession += $fromDisc;
            }

            $due = round(max(0, $charge - $paid - $concession), 2);
            $status = 'Due';
            if ($due <= 0.0001) {
                if ($waive && $paid <= 0.0001) {
                    $status = 'Waived';
                } elseif ($waive && $paid > 0.0001) {
                    $status = 'Waived';
                } elseif ($paid > 0.0001 && $concession > 0.0001 && abs($paid - $charge) > 0.0001) {
                    $status = 'Partial';
                } elseif ($paid > 0.0001) {
                    $status = 'Paid';
                } elseif ($concession > 0.0001) {
                    $status = 'Waived';
                } else {
                    $status = 'Paid';
                }
            } elseif ($paid > 0.0001 || $concession > 0.0001) {
                $status = 'Partial';
            }

            $rows[] = [
                'month_key' => $monthKey,
                'month' => \Carbon\Carbon::createFromFormat('Y-m', $monthKey)->format('M Y'),
                'charge' => round($charge, 2),
                'paid' => round($paid, 2),
                'concession' => round($concession, 2),
                'due' => $due,
                'waived' => $waive,
                'status' => $status,
                'categories' => collect($cats)
                    ->map(fn ($amt, $name) => $name.': ₹'.number_format($amt, 0, '.', ','))
                    ->implode(', ') ?: '—',
            ];
        }

        return $rows;
    }

    /**
     * Category-wise charge / paid / concession / due for a month scope.
     * Uses real per-head attribution — never proportional shares.
     *
     * @param  list<string>  $monthKeys
     * @return list<array{fee_head: string, charge: float, paid: float, concession: float, due: float}>
     */
    public function categoryRows(
        Student $student,
        AcademicSession $session,
        array $monthKeys,
        ?array $calc = null,
        ?array $paidInfo = null
    ): array {
        $monthKeys = $this->filterMonthsFromFeeStart($student, $monthKeys);
        $calc ??= FeeCalculator::forStudent($student, $session);
        $paidInfo ??= $this->paidByHead($student, $session);
        $breakdown = $calc['breakdown'] ?? [];
        $paidByMonth = $paidInfo['by_month'];
        $discountByMonth = $paidInfo['discount_by_month'];
        $paidTotals = $paidInfo['totals'];
        $paidDiscounts = $paidInfo['discounts'];

        $firstBillableMonth = $this->firstBillableMonth($student, $session);
        $monthKeySet = array_fill_keys($monthKeys, true);

        $rows = [];

        foreach ($breakdown as $plan) {
            $name = (string) ($plan['fee_head_name'] ?? 'Fee');
            $unit = (float) ($plan['amount'] ?? 0);
            $headKey = (string) (int) ($plan['fee_head_id'] ?? 0);
            $freq = strtolower(str_replace(' ', '_', (string) ($plan['frequency'] ?? 'one_time')));
            if ($unit <= 0 || $headKey === '0') {
                continue;
            }

            $charge = 0.0;
            $paid = 0.0;
            $concession = 0.0;

            if (in_array($freq, ['annual', 'one_time'], true)) {
                if ($firstBillableMonth && isset($monthKeySet[$firstBillableMonth])) {
                    $charge = $unit;
                    $mPaid = (float) ($paidTotals[$headKey] ?? 0);
                    $mDisc = (float) ($paidDiscounts[$headKey] ?? 0);
                    if ($this->isMonthWaived($student, $session, $firstBillableMonth)) {
                        $paid = min($mPaid, $unit);
                        $concession = max(0.0, $unit - $paid);
                    } else {
                        $covered = min($unit, $mPaid + $mDisc);
                        $paid = min($mPaid, $covered);
                        $concession = max(0.0, $covered - $paid);
                    }
                }
            } else {
                foreach ($monthKeys as $monthKey) {
                    $monthNum = (int) substr($monthKey, 5, 2);
                    if (! $this->isFeeMonth($student, $monthKey)
                        || (in_array($freq, ['monthly', 'quarterly'], true) && ! FeeSchedule::chargesInMonth($plan, $monthNum))) {
                        continue;
                    }
                    $waive = $this->isMonthWaived($student, $session, $monthKey);
                    $mPaid = (float) ($paidByMonth[$headKey][$monthKey] ?? 0);
                    $mDisc = (float) ($discountByMonth[$headKey][$monthKey] ?? 0);
                    $charge += $unit;
                    if ($waive) {
                        $fromPaid = min($mPaid, $unit);
                        $paid += $fromPaid;
                        $concession += max(0.0, $unit - $fromPaid);
                    } else {
                        $covered = min($unit, $mPaid + $mDisc);
                        $paid += min($mPaid, $covered);
                        $concession += max(0.0, $covered - min($mPaid, $covered));
                    }
                }
            }

            if ($charge <= 0) {
                continue;
            }

            $rows[] = [
                'fee_head' => $name,
                'charge' => round($charge, 2),
                'paid' => round($paid, 2),
                'concession' => round($concession, 2),
                'due' => round(max(0, $charge - $paid - $concession), 2),
            ];
        }

        [$transportFare, $transportHeadId, $transportFeeStart] = $this->transportFareAndHead($student);
        if ($transportFare > 0 && $transportHeadId && $monthKeys !== []) {
            $headKey = (string) $transportHeadId;
            $charge = 0.0;
            $paid = 0.0;
            $concession = 0.0;
            foreach ($monthKeys as $monthKey) {
                if (! $this->transportAppliesInMonth($student, $monthKey, $transportFeeStart)) {
                    continue;
                }
                $waive = $this->isMonthWaived($student, $session, $monthKey);
                $mPaid = (float) ($paidByMonth[$headKey][$monthKey] ?? 0);
                $mDisc = (float) ($discountByMonth[$headKey][$monthKey] ?? 0);
                $charge += $transportFare;
                if ($waive) {
                    $fromPaid = min($mPaid, $transportFare);
                    $paid += $fromPaid;
                    $concession += max(0.0, $transportFare - $fromPaid);
                } else {
                    $covered = min($transportFare, $mPaid + $mDisc);
                    $paid += min($mPaid, $covered);
                    $concession += max(0.0, $covered - min($mPaid, $covered));
                }
            }
            if ($charge > 0) {
                $rows[] = [
                    'fee_head' => 'Transport',
                    'charge' => round($charge, 2),
                    'paid' => round($paid, 2),
                    'concession' => round($concession, 2),
                    'due' => round(max(0, $charge - $paid - $concession), 2),
                ];
            }
        }

        return $rows;
    }

    /** @return array{0: float, 1: int, 2: ?string} fare, head id, transport fee start Y-m */
    public function transportFareAndHead(Student $student): array
    {
        if (self::$transportWarmed) {
            $assignment = self::$transportByStudent[(int) $student->id] ?? null;
            $fare = (float) ($assignment?->routeStop?->fare ?? 0);
            if ($fare <= 0) {
                return [0.0, 0, null];
            }
            $headId = self::$transportHeadId ?? 0;
            if ($headId <= 0) {
                return [0.0, 0, null];
            }

            return [$fare, $headId, $assignment?->feeStartMonthKey()];
        }

        $assignment = StudentTransport::query()
            ->where('student_id', $student->id)
            ->where('status', 'Active')
            ->with('routeStop:id,fare')
            ->first();
        $fare = (float) ($assignment?->routeStop?->fare ?? 0);
        if ($fare <= 0) {
            return [0.0, 0, null];
        }
        $headId = (int) (FeeHead::query()->where('name', 'Transport')->value('id') ?? 0);
        $feeStart = $assignment?->feeStartMonthKey();

        return [$fare, $headId, $feeStart];
    }

    /**
     * Whether transport fare applies for this month key — Transport Fee Months (see
     * isTransportMonth()). $transportFeeStart is no longer needed; kept for existing callers.
     */
    public function transportAppliesInMonth(Student $student, string $monthKey, ?string $transportFeeStart = null): bool
    {
        return $this->isTransportMonth($student, $monthKey);
    }
}
