<?php

namespace App\Http\Controllers\Erp\FinancePayroll;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Income;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class IncomeController extends Controller
{
    public function index(Request $request)
    {
        $query = Income::with(['bankAccount:id,account_name', 'receivedBy:id,name']);
        AcademicSession::applyDateWindow($query, $request, 'date');

        return response()->json(
            $query->orderByDesc('date')->orderByDesc('id')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'source' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'payment_mode' => ['required', Rule::in(['Cash', 'Bank', 'UPI', 'Cheque'])],
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'remarks' => 'nullable|string|max:255',
        ]);

        $income = Income::create([
            ...$data,
            'voucher_no' => $this->nextVoucherNo(),
            'received_by_id' => Auth::guard('erp')->id(),
        ]);

        return response()->json($income->load(['bankAccount:id,account_name', 'receivedBy:id,name']), 201);
    }

    public function update(Request $request, Income $income)
    {
        $data = $request->validate([
            'source' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'payment_mode' => ['required', Rule::in(['Cash', 'Bank', 'UPI', 'Cheque'])],
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'remarks' => 'nullable|string|max:255',
        ]);

        $income->update($data);

        return response()->json($income->load(['bankAccount:id,account_name', 'receivedBy:id,name']));
    }

    public function destroy(Income $income)
    {
        $income->delete();

        return response()->json(['success' => true]);
    }

    private function nextVoucherNo(): string
    {
        $year = now()->format('Y');
        $count = Income::where('voucher_no', 'like', "INC-{$year}-%")->count() + 1;

        return sprintf('INC-%s-%04d', $year, $count);
    }
}
