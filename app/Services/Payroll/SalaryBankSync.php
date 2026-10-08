<?php

namespace App\Services\Payroll;

use App\Models\BankTransaction;
use App\Models\SalaryAdvance;
use App\Models\SalarySlip;

/**
 * Keeps a salary slip's bank debit in step with the slip: a slip that is Paid by Bank from an
 * account has exactly one Withdrawal on that account for its net pay (reference = slip no.);
 * any other state (Pending, Cash, no account, deleted, rolled back) has none. Called from
 * SalarySlip's model events and after a Salary History rollback.
 */
class SalaryBankSync
{
    public static function sync(int $slipId): void
    {
        $slip = SalarySlip::with('employee')->find($slipId);
        $transaction = BankTransaction::where('salary_slip_id', $slipId)->first();

        $shouldDebit = $slip
            && $slip->status === 'Paid'
            && $slip->payment_mode === 'Bank'
            && $slip->bank_account_id
            && (float) $slip->net_salary > 0;

        if (! $shouldDebit) {
            $transaction?->delete();

            return;
        }

        $payload = [
            'bank_account_id' => $slip->bank_account_id,
            'type' => 'Withdrawal',
            'amount' => (float) $slip->net_salary,
            'date' => ($slip->paid_on ?? now())->format('Y-m-d'),
            'reference_no' => $slip->slip_no,
            // Bank ledger / Global Workbook export: ITEM = who was paid, CATEGORY = "SALARY JULY-26".
            'item' => mb_substr((string) ($slip->employee->name ?? 'Staff'), 0, 255),
            'category' => self::salaryCategory($slip->period),
            'remarks' => mb_substr('Salary — '.($slip->employee->name ?? 'staff').' ('.self::periodLabel($slip->period).')', 0, 255),
        ];

        if ($transaction) {
            $transaction->update($payload);
        } else {
            BankTransaction::create($payload + ['salary_slip_id' => $slipId]);
        }
    }

    /** Same rule for a salary advance: paid by Bank from an account = one Withdrawal for its amount. */
    public static function syncAdvance(int $advanceId): void
    {
        $advance = SalaryAdvance::with('employee')->find($advanceId);
        $transaction = BankTransaction::where('salary_advance_id', $advanceId)->first();

        $shouldDebit = $advance
            && $advance->payment_mode === 'Bank'
            && $advance->bank_account_id
            && (float) $advance->amount > 0;

        if (! $shouldDebit) {
            $transaction?->delete();

            return;
        }

        $payload = [
            'bank_account_id' => $advance->bank_account_id,
            'type' => 'Withdrawal',
            'amount' => (float) $advance->amount,
            'date' => ($advance->paid_on ?? now())->format('Y-m-d'),
            'reference_no' => $advance->advance_no,
            'item' => mb_substr((string) ($advance->employee->name ?? 'Staff'), 0, 255),
            'category' => 'ADVANCE '.substr(self::salaryCategory($advance->period), 7),
            'remarks' => mb_substr('Salary advance — '.($advance->employee->name ?? 'staff').' ('.self::periodLabel($advance->period).')', 0, 255),
        ];

        if ($transaction) {
            $transaction->update($payload);
        } else {
            BankTransaction::create($payload + ['salary_advance_id' => $advanceId]);
        }
    }

    /** "2026-07" -> "SALARY JULY-26" (the salary month, as the school writes it). */
    public static function salaryCategory(string $period): string
    {
        $ts = strtotime($period.'-01');

        return 'SALARY '.($ts ? strtoupper(date('F-y', $ts)) : $period);
    }

    private static function periodLabel(string $period): string
    {
        $ts = strtotime($period.'-01');

        return $ts ? date('F Y', $ts) : $period;
    }
}
