<?php

namespace App\Http\Controllers\Erp\FinancePayroll;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\AcademicSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
                    ->orWhere('title', 'like', $term)
                    ->orWhere('notes', 'like', $term)
                    ->orWhere('remarks', 'like', $term)
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
            'expense_category_id' => $data['expense_category_id'],
            'part2' => $data['part2'] ?? null,
            'part3' => $data['part3'] ?? null,
            'title' => $data['description'],
            'paid_to' => $data['paid_to'],
            'amount' => $data['amount'],
            'date' => $data['date'],
            'status' => $data['status'],
            'remarks' => $data['remarks'] ?? null,
            'notes' => $data['notes'] ?? null,
            'voucher_no' => $this->resolveVoucherNo($data['receipt_no'] ?? null),
            'paid_by_id' => Auth::guard('erp')->id(),
        ]);

        return response()->json($expense->fresh()->load(self::RELATIONS), 201);
    }

    public function update(Request $request, Expense $expense)
    {
        $data = $this->validated($request);

        $receiptNo = trim((string) ($data['receipt_no'] ?? ''));
        $voucherNo = $receiptNo !== '' ? $this->resolveVoucherNo($receiptNo, $expense->id) : $expense->voucher_no;

        $expense->update([
            'expense_category_id' => $data['expense_category_id'],
            'part2' => $data['part2'] ?? null,
            'part3' => $data['part3'] ?? null,
            'title' => $data['description'],
            'paid_to' => $data['paid_to'],
            'amount' => $data['amount'],
            'date' => $data['date'],
            'status' => $data['status'],
            'remarks' => $data['remarks'] ?? null,
            'notes' => $data['notes'] ?? null,
            'voucher_no' => $voucherNo,
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
            'part2' => 'nullable|string|max:255',
            'part3' => 'nullable|string|max:255',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'paid_to' => 'required|string|max:255',
            'status' => ['required', Rule::in(['Pending', 'Approved', 'Rejected'])],
            'remarks' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
            'receipt_no' => 'nullable|string|max:100',
        ]);
    }

    /**
     * Blank receipt no → next auto-numbered voucher. A given receipt no is normalized the same
     * way GlobalWorkbookImportController does (EXP- prefixed) and checked for uniqueness.
     */
    private function resolveVoucherNo(?string $receiptNo, ?int $ignoreId = null): string
    {
        $receiptNo = trim((string) $receiptNo);
        if ($receiptNo === '') {
            return $this->nextVoucherNo();
        }

        $voucherNo = str_starts_with(strtoupper($receiptNo), 'EXP-') ? $receiptNo : 'EXP-'.$receiptNo;

        $exists = Expense::where('voucher_no', $voucherNo)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'receipt_no' => ['This receipt number is already in use.'],
            ]);
        }

        return $voucherNo;
    }

    private function nextVoucherNo(): string
    {
        $count = Expense::count() + 1;

        return sprintf('EXP-%05d', $count);
    }
}
