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

        $bankTransaction->update($data);

        return response()->json($bankTransaction);
    }

    public function destroy(BankTransaction $bankTransaction)
    {
        $bankTransaction->delete();

        return response()->json(['success' => true]);
    }
}
