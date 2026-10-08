<?php

namespace App\Http\Controllers\Erp\FinancePayroll;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\BankTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BankTransactionController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['bank_account_id' => 'required|exists:bank_accounts,id']);

        $query = BankTransaction::where('bank_account_id', $request->integer('bank_account_id'));
        AcademicSession::applyDateWindow($query, $request, 'date');

        return response()->json(
            $query->orderByDesc('date')->orderByDesc('id')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'type' => ['required', Rule::in(['Deposit', 'Withdrawal'])],
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'reference_no' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:255',
        ]);

        $transaction = BankTransaction::create($data);

        return response()->json($transaction, 201);
    }

    public function update(Request $request, BankTransaction $bankTransaction)
    {
        $data = $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'type' => ['required', Rule::in(['Deposit', 'Withdrawal'])],
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'reference_no' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:255',
        ]);

        $this->assertNotSalaryDebit($bankTransaction);
        $bankTransaction->update($data);

        return response()->json($bankTransaction);
    }

    public function destroy(BankTransaction $bankTransaction)
    {
        $this->assertNotSalaryDebit($bankTransaction);
        $bankTransaction->delete();

        return response()->json(['success' => true]);
    }

    /** A salary payment's debit follows its slip — change the slip (or roll it back), not the debit. */
    private function assertNotSalaryDebit(BankTransaction $transaction): void
    {
        if ($transaction->salary_slip_id) {
            abort(response()->json([
                'message' => "This is the salary payment for slip {$transaction->reference_no} — edit or roll back the slip under Finance & Payroll › Salary Slips / Salary History instead.",
            ], 422));
        }
        if ($transaction->salary_advance_id) {
            abort(response()->json([
                'message' => "This is the salary advance {$transaction->reference_no} — edit or delete the advance under Finance & Payroll › Salary Slips › Advance Payments instead.",
            ], 422));
        }
    }
}
