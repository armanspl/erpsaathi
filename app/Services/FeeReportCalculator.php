<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\FeePayment;
use App\Models\Student;
use Illuminate\Support\Collection;

class FeeReportCalculator
{
    /**
     * Fee collection snapshot, computed live from students/payments.
     * Pass a session to scope; pass null with $allSessions=true for every session combined.
     *
     * Pending/due uses month-expanded ledger balances (same as Fee Due till current month),
     * not unit structure amounts — otherwise payments for many months wipe pending to ₹0.
     */
    public static function summary(?AcademicSession $session = null, bool $allSessions = false): array
    {
        if ($allSessions) {
            return self::summaryAll();
        }

        $session ??= AcademicSession::where('is_current', true)->first();
        if (! $session) {
            return self::emptySummary();
        }

        return self::summaryForSessions(collect([$session]), ['id' => $session->id, 'name' => $session->name]);
    }

    private static function summaryAll(): array
    {
        $sessions = AcademicSession::orderByDesc('start_date')->get();
        if ($sessions->isEmpty()) {
            return self::emptySummary();
        }

        return self::summaryForSessions($sessions, ['id' => null, 'name' => 'All']);
    }

    private static function summaryForSessions(Collection $sessions, array $sessionMeta): array
    {
        $activeStudents = self::studentsForSessions($sessions);

        FeeCalculator::flushRuntimeCache();
        FeeCalculator::warmForStudents($activeStudents, $sessions);

        $balance = app(FeeBalanceService::class);
        $totalFee = 0.0;
        $totalDiscount = 0.0;
        $totalPaidToward = 0.0;
        $totalDue = 0.0;
        $defaulters = 0;

        foreach ($activeStudents as $student) {
            $studentDue = 0.0;
            foreach ($sessions as $session) {
                $calc = FeeCalculator::forStudent($student, $session);
                $monthKeys = $balance->filterMonthsFromFeeStart($student, self::monthKeysTillCurrent($session));
                $paidInfo = $balance->paidByHead($student, $session);
                $remaining = $balance->remainingForMonths($student, $session, $monthKeys, $calc, $paidInfo);

                $totalFee += $remaining['charge'];
                $totalDiscount += $remaining['discount'];
                $totalPaidToward += $remaining['paid'];
                $studentDue += $remaining['due'];
            }
            $totalDue += $studentDue;
            if ($studentDue > 0.0001) {
                $defaulters++;
            }
        }

        $sessionIds = $sessions->pluck('id');
        $payments = FeePayment::whereIn('academic_session_id', $sessionIds)->get();
        $ym = now()->format('Y-m');
        $totalPaidReceipts = round((float) $payments->sum(
            fn (FeePayment $p) => (float) $p->amount - (float) $p->refunded_amount
        ), 2);

        $netDemand = $totalFee - $totalDiscount;

        return [
            'session' => $sessionMeta,
            'total_fee' => round($totalFee, 2),
            'total_discount' => round($totalDiscount, 2),
            // Receipts collected in the selected academic session(s).
            'total_paid' => $totalPaidReceipts,
            // Outstanding till current month (session-scoped ledger balance).
            'total_due' => round(max(0, $totalDue), 2),
            'collection_rate' => $netDemand > 0
                ? round(($totalPaidToward / $netDemand) * 100, 1)
                : 0,
            'defaulters_count' => $defaulters,
            'payment_mode_breakdown' => $payments->groupBy('payment_mode')->map(fn ($rows) => round((float) $rows->sum(fn (FeePayment $p) => (float) $p->amount - (float) $p->refunded_amount), 2)),
            'collected_this_month' => round((float) $payments->filter(fn (FeePayment $p) => $p->payment_date->format('Y-m') === $ym)->sum(fn (FeePayment $p) => (float) $p->amount - (float) $p->refunded_amount), 2),
        ];
    }

    /**
     * Active students belonging to the selected session(s) via session history —
     * same rule as the dashboard / People > Students list.
     */
    private static function studentsForSessions(Collection $sessions): Collection
    {
        $query = Student::where('status', 'Active');

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
