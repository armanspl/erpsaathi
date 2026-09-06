<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Income;
use App\Models\SalarySlip;

class CashBookCalculator
{
    /**
     * Build a chronological cash ledger (income in, expenses/salary payouts out) for a date
     * range, computed live from the source tables rather than storing a duplicate ledger.
     */
    public static function forRange(string $from, string $to): array
    {
        $entries = collect();

        Income::where('payment_mode', 'Cash')->whereBetween('date', [$from, $to])->get()
            ->each(fn (Income $i) => $entries->push([
                'date' => $i->date->toDateString(),
                'particulars' => $i->source,
                'voucher_no' => $i->voucher_no,
                'type' => 'Income',
                'in' => (float) $i->amount,
                'out' => 0,
            ]));

        Expense::with('expenseCategory:id,name')->where('payment_mode', 'Cash')->whereBetween('date', [$from, $to])->get()
            ->each(fn (Expense $e) => $entries->push([
                'date' => $e->date->toDateString(),
                'particulars' => $e->title,
                'voucher_no' => $e->voucher_no,
                'type' => 'Expense',
                'in' => 0,
                'out' => (float) $e->amount,
            ]));

        SalarySlip::where('payment_mode', 'Cash')->where('status', 'Paid')
            ->whereBetween('paid_on', [$from, $to])->get()
            ->each(fn (SalarySlip $s) => $entries->push([
                'date' => $s->paid_on->toDateString(),
                'particulars' => 'Salary — ' . ucfirst($s->employee_type) . ' #' . $s->employee_id . ' (' . $s->period . ')',
                'voucher_no' => null,
                'type' => 'Salary',
                'in' => 0,
                'out' => (float) $s->net_salary,
            ]));

        $sorted = $entries->sortBy('date')->values();

        $balance = 0;
        $rows = $sorted->map(function (array $row) use (&$balance) {
            $balance += $row['in'] - $row['out'];
            $row['balance'] = round($balance, 2);

            return $row;
        });

        return [
            'total_in' => round($sorted->sum('in'), 2),
            'total_out' => round($sorted->sum('out'), 2),
            'closing_balance' => round($balance, 2),
            'rows' => $rows,
        ];
    }
}
