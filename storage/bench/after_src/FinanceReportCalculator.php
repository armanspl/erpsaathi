<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;

class FinanceReportCalculator
{
    /**
     * Income/expense/bank snapshot, computed live from the finance & payroll tables.
     * Income/Expense have no academic_session_id column, so a session is applied as a
     * start_date/end_date window rather than an exact FK match — pass null (default) for
     * every record regardless of session.
     */
    public static function summary(?AcademicSession $session = null, bool $allSessions = true): array
    {
        $incomeQuery = Income::query();
        $expenseQuery = Expense::with('expenseCategory:id,name');

        if (! $allSessions && $session && $session->start_date && $session->end_date) {
            $incomeQuery->whereBetween('date', [$session->start_date, $session->end_date]);
            $expenseQuery->whereBetween('date', [$session->start_date, $session->end_date]);
        }

        $incomes = $incomeQuery->get();
        $expenses = $expenseQuery->get();
        $ym = now()->format('Y-m');

        $totalIncome = (float) $incomes->sum('amount');
        $totalExpense = (float) $expenses->sum('amount');

        $topExpenseCategories = $expenses->groupBy(fn (Expense $e) => $e->expenseCategory->name ?? 'Uncategorized')
            ->map(fn ($rows) => round((float) $rows->sum('amount'), 2))
            ->sortDesc()
            ->take(5);

        return [
            'total_income' => round($totalIncome, 2),
            'total_expense' => round($totalExpense, 2),
            'net_balance' => round($totalIncome - $totalExpense, 2),
            'income_this_month' => round((float) $incomes->filter(fn (Income $i) => $i->date->format('Y-m') === $ym)->sum('amount'), 2),
            'expense_this_month' => round((float) $expenses->filter(fn (Expense $e) => $e->date->format('Y-m') === $ym)->sum('amount'), 2),
            'total_bank_balance' => round(self::totalBankBalance(), 2),
            'top_expense_categories' => $topExpenseCategories,
        ];
    }

    /**
     * Same formula as BankAccount::currentBalance(), aggregated in a few queries
     * instead of 4 queries per account.
     */
    private static function totalBankBalance(): float
    {
        $accounts = BankAccount::query()->get(['id', 'opening_balance']);
        if ($accounts->isEmpty()) {
            return 0.0;
        }

        $ids = $accounts->pluck('id')->all();

        $txRows = BankTransaction::query()
            ->whereIn('bank_account_id', $ids)
            ->selectRaw('bank_account_id, type, COALESCE(SUM(amount), 0) as total')
            ->groupBy('bank_account_id', 'type')
            ->get()
            ->groupBy('bank_account_id');

        $incomeByAccount = Income::query()
            ->whereIn('bank_account_id', $ids)
            ->selectRaw('bank_account_id, COALESCE(SUM(amount), 0) as total')
            ->groupBy('bank_account_id')
            ->pluck('total', 'bank_account_id');

        $expenseByAccount = Expense::query()
            ->whereIn('bank_account_id', $ids)
            ->selectRaw('bank_account_id, COALESCE(SUM(amount), 0) as total')
            ->groupBy('bank_account_id')
            ->pluck('total', 'bank_account_id');

        $sum = 0.0;
        foreach ($accounts as $account) {
            $tx = $txRows->get($account->id, collect());
            $deposits = (float) ($tx->firstWhere('type', 'Deposit')->total ?? 0);
            $withdrawals = (float) ($tx->firstWhere('type', 'Withdrawal')->total ?? 0);
            $bankIncome = (float) ($incomeByAccount[$account->id] ?? 0);
            $bankExpense = (float) ($expenseByAccount[$account->id] ?? 0);
            $sum += (float) $account->opening_balance + $deposits - $withdrawals + $bankIncome - $bankExpense;
        }

        return round($sum, 2);
    }
}
