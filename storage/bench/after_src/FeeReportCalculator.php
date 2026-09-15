<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\FeePayment;
use App\Models\Student;
use App\Support\DashboardCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FeeReportCalculator
{
    /**
     * Fee collection snapshot, computed live from students/payments.
     * Pass a session to scope; pass null with $allSessions=true for every session combined.
     *
     * Pending/due uses month-expanded ledger balances (same as Fee Due till current month),
     * not unit structure amounts — otherwise payments for many months wipe pending to ₹0.
     *
     * Results are cached briefly (see DashboardCache) and share one ledger pass with
     * dashboard fee_collection_status when requested via summaryWithClassStatus().
     */
    public static function summary(?AcademicSession $session = null, bool $allSessions = false): array
    {
        return self::summaryWithClassStatus($session, $allSessions)['summary'];
    }

    /**
     * Same fee math as summary(), plus per-class collection status built from the same
     * per-student remaining rows (no second fee-balance pass).
     *
     * @param  Collection<int, Student>|null  $students  Optional preloaded active cohort (with schoolClass).
     * @return array{summary: array, fee_collection_status: list<array>}
     */
    public static function summaryWithClassStatus(
        ?AcademicSession $session = null,
        bool $allSessions = false,
        ?Collection $students = null
    ): array {
        if ($allSessions) {
            $sessions = AcademicSession::orderByDesc('start_date')->get();
            $sessionMeta = ['id' => null, 'name' => 'All'];
            $cacheSessionId = null;
        } else {
            $session ??= AcademicSession::where('is_current', true)->first();
            if (! $session) {
                return [
                    'summary' => self::emptySummary(),
                    'fee_collection_status' => [],
                ];
            }
            $sessions = collect([$session]);
            $sessionMeta = ['id' => $session->id, 'name' => $session->name];
            $cacheSessionId = $session->id;
        }

        if ($sessions->isEmpty()) {
            return [
                'summary' => self::emptySummary(),
                'fee_collection_status' => [],
            ];
        }

        $cacheKey = DashboardCache::feeReportKey($cacheSessionId, $allSessions);

        return Cache::remember($cacheKey, DashboardCache::TTL, function () use ($sessions, $sessionMeta, $students, $session, $allSessions) {
            return self::computeForSessions($sessions, $sessionMeta, $students, $session, $allSessions);
        });
    }

    /**
     * @param  Collection<int, AcademicSession>  $sessions
     * @param  Collection<int, Student>|null  $students
     * @return array{summary: array, fee_collection_status: list<array>}
     */
    private static function computeForSessions(
        Collection $sessions,
        array $sessionMeta,
        ?Collection $students,
        ?AcademicSession $requestSession,
        bool $allSessions
    ): array {
        $activeStudents = $students ?? self::studentsForSessions($sessions);
        if ($students !== null && $students->isNotEmpty() && ! $students->first()->relationLoaded('schoolClass')) {
            $activeStudents->load('schoolClass:id,name');
        }

        FeeCalculator::flushRuntimeCache();
        FeeCalculator::warmForStudents($activeStudents, $sessions);

        $balance = app(FeeBalanceService::class);
        $totalFee = 0.0;
        $totalDiscount = 0.0;
        $totalPaidToward = 0.0;
        $totalDue = 0.0;
        $defaulters = 0;

        // fee_collection_status always mirrors DashboardCalculator's prior rule:
        // the requested session, or the current session when browsing "All Sessions".
        $statusSession = $requestSession ?? AcademicSession::where('is_current', true)->first();
        $statusSessionId = $statusSession?->id;
        $classAgg = [];

        foreach ($activeStudents as $student) {
            $studentDue = 0.0;
            foreach ($sessions as $sess) {
                $calc = FeeCalculator::forStudent($student, $sess);
                $monthKeys = $balance->filterMonthsFromFeeStart($student, self::monthKeysTillCurrent($sess));
                $paidInfo = $balance->paidByHead($student, $sess, $calc);
                $remaining = $balance->remainingForMonths($student, $sess, $monthKeys, $calc, $paidInfo);

                $totalFee += $remaining['charge'];
                $totalDiscount += $remaining['discount'];
                $totalPaidToward += $remaining['paid'];
                $studentDue += $remaining['due'];

                if ($statusSessionId && (int) $sess->id === (int) $statusSessionId) {
                    $className = $student->schoolClass?->name ?? 'Unassigned';
                    if (! isset($classAgg[$className])) {
                        $classAgg[$className] = [
                            'class' => $className,
                            'charge' => 0.0,
                            'paid' => 0.0,
                            'due' => 0.0,
                            'pending' => 0,
                        ];
                    }
                    $classAgg[$className]['charge'] += $remaining['charge'];
                    $classAgg[$className]['paid'] += $remaining['paid'];
                    $classAgg[$className]['due'] += $remaining['due'];
                    if ($remaining['due'] > 0.0001) {
                        $classAgg[$className]['pending']++;
                    }
                }
            }
            $totalDue += $studentDue;
            if ($studentDue > 0.0001) {
                $defaulters++;
            }
        }

        $sessionIds = $sessions->pluck('id')->all();
        $paymentStats = self::paymentAggregates($sessionIds);
        $netDemand = $totalFee - $totalDiscount;

        $summary = [
            'session' => $sessionMeta,
            'total_fee' => round($totalFee, 2),
            'total_discount' => round($totalDiscount, 2),
            // Receipts collected in the selected academic session(s).
            'total_paid' => $paymentStats['total_paid'],
            // Outstanding till current month (session-scoped ledger balance).
            'total_due' => round(max(0, $totalDue), 2),
            'collection_rate' => $netDemand > 0
                ? round(($totalPaidToward / $netDemand) * 100, 1)
                : 0,
            'defaulters_count' => $defaulters,
            'payment_mode_breakdown' => $paymentStats['payment_mode_breakdown'],
            'collected_this_month' => $paymentStats['collected_this_month'],
        ];

        $feeCollectionStatus = collect($classAgg)
            ->map(function (array $row) {
                $charge = max($row['charge'], 0.0001);

                return [
                    'class' => $row['class'],
                    'collected_amount' => round($row['paid'], 0),
                    'due_amount' => round($row['due'], 0),
                    'collected' => (int) round(($row['paid'] / $charge) * 100),
                    'due_pct' => (int) round(($row['due'] / $charge) * 100),
                    'pending' => $row['pending'],
                ];
            })
            ->sortByDesc('due_amount')
            ->values()
            ->all();

        return [
            'summary' => $summary,
            'fee_collection_status' => $feeCollectionStatus,
        ];
    }

    /**
     * Receipt totals without loading every FeePayment row into PHP.
     *
     * @param  list<int>  $sessionIds
     * @return array{total_paid: float, payment_mode_breakdown: array<string, float>, collected_this_month: float}
     */
    private static function paymentAggregates(array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [
                'total_paid' => 0.0,
                'payment_mode_breakdown' => [],
                'collected_this_month' => 0.0,
            ];
        }

        $base = FeePayment::query()->whereIn('academic_session_id', $sessionIds);

        $totalPaid = round((float) (clone $base)->selectRaw('COALESCE(SUM(amount - refunded_amount), 0) as t')->value('t'), 2);

        $modeRows = (clone $base)
            ->selectRaw('payment_mode, COALESCE(SUM(amount - refunded_amount), 0) as t')
            ->groupBy('payment_mode')
            ->pluck('t', 'payment_mode');

        $modeBreakdown = collect($modeRows)->map(fn ($amount) => round((float) $amount, 2));

        $ymStart = now()->startOfMonth()->toDateString();
        $ymEnd = now()->endOfMonth()->toDateString();
        $collectedThisMonth = round((float) (clone $base)
            ->whereBetween('payment_date', [$ymStart, $ymEnd])
            ->selectRaw('COALESCE(SUM(amount - refunded_amount), 0) as t')
            ->value('t'), 2);

        return [
            'total_paid' => $totalPaid,
            'payment_mode_breakdown' => $modeBreakdown,
            'collected_this_month' => $collectedThisMonth,
        ];
    }

    /**
     * Active students belonging to the selected session(s) via session history —
     * same rule as the dashboard / People > Students list.
     */
    private static function studentsForSessions(Collection $sessions): Collection
    {
        $query = Student::where('status', 'Active')->with('schoolClass:id,name');

        if ($sessions->count() !== 1) {
            return $query->get();
        }

        $session = $sessions->first();
        $aliases = AcademicSession::nameAliases($session->name);

        return $query->where(function ($q) use ($aliases, $session) {
            $q->whereHas('sessionHistories', fn ($h) => $h->whereIn('session', $aliases));
            if ($session->is_current) {
                $q->orWhereDoesntHave('sessionHistories');
            }
        })->get();
    }

    /** Months from session start through the current month (capped at session end). */
    private static function monthKeysTillCurrent(AcademicSession $session): array
    {
        if (! $session->start_date || ! $session->end_date) {
            return collect($session->months())->map(fn ($m) => $m['key'] ?? null)->filter()->values()->all();
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

    private static function emptySummary(): array
    {
        return [
            'session' => null, 'total_fee' => 0, 'total_discount' => 0, 'total_paid' => 0, 'total_due' => 0,
            'collection_rate' => 0, 'defaulters_count' => 0, 'payment_mode_breakdown' => [], 'collected_this_month' => 0,
        ];
    }
}
