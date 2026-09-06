<?php

namespace App\Http\Controllers\Erp\FinancePayroll;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BankAccountController extends Controller
{
    public function index()
    {
        $accounts = BankAccount::orderBy('account_name')->get();

        return response()->json($accounts->map(fn (BankAccount $a) => [
            ...$a->toArray(),
            'current_balance' => $a->currentBalance(),
        ]));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'account_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:50|unique:bank_accounts,account_number',
            'ifsc_code' => 'nullable|string|max:20',
            'branch' => 'nullable|string|max:255',
            'opening_balance' => 'nullable|numeric|min:0',
        ]);

        $account = BankAccount::create($data);

        return response()->json($account, 201);
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        $data = $request->validate([
            'account_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'account_number' => ['required', 'string', 'max:50', Rule::unique('bank_accounts', 'account_number')->ignore($bankAccount->id)],
            'ifsc_code' => 'nullable|string|max:20',
            'branch' => 'nullable|string|max:255',
            'opening_balance' => 'nullable|numeric|min:0',
        ]);

        $bankAccount->update($data);

        return response()->json($bankAccount);
    }

    public function destroy(BankAccount $bankAccount)
    {
        $bankAccount->delete();

        return response()->json(['success' => true]);
    }
}
