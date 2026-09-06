<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\ManualFeeDue;
use App\Models\Student;
use App\Models\StudentTransport;

/**
 * Shared fee balance math used by Fee Receipt, Pay Fee, and Fee Due.
 * Remaining dues are month-aware (FIFO attribution) and include transport.
 */
class FeeBalanceService
{
    /** Y-m key from which this student is billable (fee start or admission month). */
    public function feeStartKey(Student $student): ?string
    {
        return $student->feeStartMonthKey();
    }

    /**
     * Drop months before the student's fee start month.
     *
     * @param  list<string>  $monthKeys
     * @return list<string>
     */
    public function filterMonthsFromFeeStart(Student $student, array $monthKeys): array
    {
        $start = $this->feeStartKey($student);
        if (! $start) {
            return array_values($monthKeys);
        }

        return array_values(array_filter($monthKeys, fn ($k) => (string) $k >= $start));
    }

    /** First session month that is on/after fee start (annual / one-time attach here). */
    public function firstBillableMonth(Student $student, ?AcademicSession $session): ?string
    {
        if (! $session) {
            return $this->feeStartKey($student);
        }

        $keys = collect($session->months())->map(fn ($m) => $m['key'] ?? null)->filter()->values()->all();
        $filtered = $this->filterMonthsFromFeeStart($student, $keys);

        return $filtered[0] ?? null;
    }

    /**
     * @return array{
     *   totals: array<string, float>,
     *   discounts: array<string, float>,
     *   by_month: array<string, array<string, float>>,
     *   discount_by_month: array<string, array<string, float>>
     * }
     */
    public function paidByHead(Student $student, ?AcademicSession $session = null): array
    {
        $session ??= AcademicSession::where('is_current', true)->first();

        $totals = [];
        $discounts = [];
        $byMonth = [];
        $discountByMonth = [];

        $unitByHead = [];
        $freqByHead = [];
        if ($session) {
            $calc = FeeCalculator::forStudent($student, $session);
            foreach ($calc['breakdown'] ?? [] as $row) {
                $hid = (int) ($row['fee_head_id'] ?? 0);
                if (! $hid) {
                    continue;
                }
                $unitByHead[$hid] = (float) ($row['amount'] ?? 0);
                $freqByHead[$hid] = strtolower(str_replace(' ', '_', (string) ($row['frequency'] ?? 'monthly')));
            }
        }

        [$transportFare, $transportHeadId] = $this->transportFareAndHead($student);
        if ($transportFare > 0 && $transportHeadId) {
            $unitByHead[$transportHeadId] = $transportFare;
            $freqByHead[$transportHeadId] = 'monthly';
        }

        $query = FeePayment::query()
            ->where('student_id', $student->id)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereNotIn('status', ['Refunded', 'Rolled Back']);
            });

        if ($session) {
            $query->where('academic_session_id', $session->id);
        }

        $payments = $query->orderBy('id')->get(['id', 'items', 'amount', 'refunded_amount', 'status']);

        foreach ($payments as $payment) {
            $gross = (float) $payment->amount;
            $net = $gross - (float) $payment->refunded_amount;
            if ($net <= 0 || $gross <= 0) {
                continue;
            }

            $scale = $net / $gross;

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
                    $freq
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
        $feeStart = $this->feeStartKey($student);

        $monthKeys = [];
        foreach ($sessionMonthKeys as $key) {
            if ($feeStart && $key < $feeStart) {
                continue;
            }
            $monthKeys[$key] = true;
        }
        foreach ($paidByMonth as $headMonths) {
            foreach (array_keys($headMonths) as $key) {
                if ($feeStart && $key < $feeStart) {
                    continue;
                }
                $monthKeys[$key] = true;
            }
        }

        $coveredToward = static function (string $headKey, float $charge, ?string $monthKey = null) use ($paidTotals, $paidDiscounts, $paidByMonth, $discountByMonth): float {
            if ($monthKey !== null) {
                $monthPaid = (float) ($paidByMonth[$headKey][$monthKey] ?? 0.0)
                    + (float) ($discountByMonth[$headKey][$monthKey] ?? 0.0);

                return min($charge, $monthPaid);
            }

            $paid = (float) ($paidTotals[$headKey] ?? 0.0) + (float) ($paidDiscounts[$headKey] ?? 0.0);

            return min($charge, $paid);
        };

        [$transportFare, $transportHeadId, $transportFeeStart] = $this->transportFareAndHead($student);
        $transportHeadKey = $transportHeadId ? (string) $transportHeadId : '';

        $paid = [];

        foreach (array_keys($monthKeys) as $monthKey) {
            $monthNum = (int) substr($monthKey, 5, 2);
            $required = 0.0;
            $got = 0.0;

            foreach ($monthlyPlans as $plan) {
                $charge = (float) ($plan['amount'] ?? 0);
                if ($charge <= 0) {
                    continue;
                }
                $headKey = (string) (int) ($plan['fee_head_id'] ?? 0);
                $required += $charge;
                $got += $coveredToward($headKey, $charge, $monthKey);
            }

            foreach ($quarterlyPlans as $plan) {
                if (! in_array($monthNum, [4, 7, 10, 1], true)) {
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
                if ($feeStart && $key < $feeStart) {
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

        $normalizeFreq = static function ($row): string {
            return strtolower(str_replace(' ', '_', (string) ($row['frequency'] ?? 'monthly')));
        };

        $firstBillableMonth = $this->firstBillableMonth($student, $session);

        $charge = 0.0;
        $paid = 0.0;
        $discount = 0.0;

        [$transportFare, $transportHeadId, $transportFeeStart] = $this->transportFareAndHead($student);

        $applyUnit = static function (string $headKey, float $unit, ?string $monthKey) use (
            &$charge,
            &$paid,
            &$discount,
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
            $covered = min($unit, $mPaid + $mDisc);
            $fromPaid = min($mPaid, $covered);
            $fromDisc = max(0.0, $covered - $fromPaid);
            $paid += $fromPaid;
            $discount += $fromDisc;
        };

        foreach ($monthKeys as $monthKey) {
            $monthNum = (int) substr($monthKey, 5, 2);

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

                if ($freq === 'monthly') {
                    $applyUnit($headKey, $unit, $monthKey);
                } elseif ($freq === 'quarterly') {
                    if (in_array($monthNum, [4, 7, 10, 1], true)) {
                        $applyUnit($headKey, $unit, $monthKey);
                    }
                } elseif (in_array($freq, ['annual', 'one_time'], true)) {
                    if ($firstBillableMonth && $monthKey === $firstBillableMonth) {
                        $applyUnit($headKey, $unit, null);
                    }
                }
            }

            if (
                $transportFare > 0
                && $transportHeadId
                && $this->transportAppliesInMonth($student, $monthKey, $transportFeeStart)
                && ($headFilter === null || isset($headFilter[(int) $transportHeadId]))
            ) {
                $applyUnit((string) $transportHeadId, $transportFare, $monthKey);
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

        $normalizeFreq = static function ($row): string {
            return strtolower(str_replace(' ', '_', (string) ($row['frequency'] ?? 'monthly')));
        };

        $firstBillableMonth = $this->firstBillableMonth($student, $session);
        [$transportFare, $transportHeadId, $transportFeeStart] = $this->transportFareAndHead($student);

        $rows = [];
        foreach ($monthKeys as $monthKey) {
            $monthNum = (int) substr($monthKey, 5, 2);
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
                    'monthly' => true,
                    'quarterly' => in_array($monthNum, [4, 7, 10, 1], true),
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
                $covered = min($unit, $mPaid + $mDisc);
                $fromPaid = min($mPaid, $covered);
                $fromDisc = max(0.0, $covered - $fromPaid);

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
                $covered = min($transportFare, $mPaid + $mDisc);
                $cats['Transport'] = ($cats['Transport'] ?? 0) + $transportFare;
                $charge += $transportFare;
                $paid += min($mPaid, $covered);
                $concession += max(0.0, $covered - min($mPaid, $covered));
            }

            $rows[] = [
                'month_key' => $monthKey,
                'month' => \Carbon\Carbon::createFromFormat('Y-m', $monthKey)->format('M Y'),
                'charge' => round($charge, 2),
                'paid' => round($paid, 2),
                'concession' => round($concession, 2),
                'due' => round(max(0, $charge - $paid - $concession), 2),
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
                    $covered = min($unit, $mPaid + $mDisc);
                    $paid = min($mPaid, $covered);
                    $concession = max(0.0, $covered - $paid);
                }
            } else {
                foreach ($monthKeys as $monthKey) {
                    $monthNum = (int) substr($monthKey, 5, 2);
                    if ($freq === 'quarterly' && ! in_array($monthNum, [4, 7, 10, 1], true)) {
                        continue;
                    }
                    $mPaid = (float) ($paidByMonth[$headKey][$monthKey] ?? 0);
                    $mDisc = (float) ($discountByMonth[$headKey][$monthKey] ?? 0);
                    $covered = min($unit, $mPaid + $mDisc);
                    $charge += $unit;
                    $paid += min($mPaid, $covered);
                    $concession += max(0.0, $covered - min($mPaid, $covered));
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
                $mPaid = (float) ($paidByMonth[$headKey][$monthKey] ?? 0);
                $mDisc = (float) ($discountByMonth[$headKey][$monthKey] ?? 0);
                $covered = min($transportFare, $mPaid + $mDisc);
                $charge += $transportFare;
                $paid += min($mPaid, $covered);
                $concession += max(0.0, $covered - min($mPaid, $covered));
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

    /** Whether transport fare applies for this month key. */
    public function transportAppliesInMonth(Student $student, string $monthKey, ?string $transportFeeStart = null): bool
    {
        if ($transportFeeStart === null) {
            [, , $transportFeeStart] = $this->transportFareAndHead($student);
        }
        if (! $transportFeeStart) {
            return true;
        }

        return $monthKey >= $transportFeeStart;
    }
}
