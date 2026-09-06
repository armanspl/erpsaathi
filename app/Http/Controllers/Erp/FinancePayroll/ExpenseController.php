<?php

namespace App\Http\Controllers\Erp\FinancePayroll;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\AcademicSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    private const RELATIONS = ['expenseCategory:id,name', 'bankAccount:id,account_name', 'paidBy:id,name'];

    public function index(Request $request)
    {
        $query = Expense::with(self::RELATIONS);

        if ($request->filled('search')) {
            $term = '%' . trim($request->string('search')) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('paid_to', 'like', $term)
                    ->orWhere('notes', 'like', $term)
                    ->orWhere('voucher_no', 'like', $term);
            });
        }
        if ($request->filled('expense_category_id')) {
            $query->where('expense_category_id', $request->integer('expense_category_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->date('to'));
        }

        // Honor header Academic Session when the page didn't pass an explicit date range.
        if (! $request->filled('from') && ! $request->filled('to')) {
            AcademicSession::applyDateWindow($query, $request, 'date');
        }

        return response()->json(
            $query->orderByDesc('date')->orderByDesc('id')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $expense = Expense::create([
            ...$data,
            'title' => $data['paid_to'],
            'voucher_no' => $this->nextVoucherNo(),
            'paid_by_id' => Auth::guard('erp')->id(),
        ]);

        return response()->json($expense->fresh()->load(self::RELATIONS), 201);
    }

    public function update(Request $request, Expense $expense)
    {
        $data = $this->validated($request);

        $expense->update([
            ...$data,
            'title' => $data['paid_to'],
        ]);

        return response()->json($expense->fresh()->load(self::RELATIONS));
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return response()->json(['success' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'paid_to' => 'required|string|max:255',
            'status' => ['required', Rule::in(['Pending', 'Approved', 'Rejected'])],
            'notes' => 'nullable|string|max:2000',
        ]);
    }

    private function nextVoucherNo(): string
    {
        $count = Expense::count() + 1;

        return sprintf('EXP-%05d', $count);
    }
}
