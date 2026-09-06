<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'voucher_no', 'expense_category_id', 'title', 'paid_to', 'amount', 'date', 'status',
        'payment_mode', 'bank_account_id', 'remarks', 'notes', 'paid_by_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'paid_by_id');
    }
}
