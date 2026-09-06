<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    protected $fillable = ['account_name', 'bank_name', 'account_number', 'ifsc_code', 'branch', 'opening_balance'];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function currentBalance(): float
    {
        $deposits = (float) $this->transactions()->where('type', 'Deposit')->sum('amount');
        $withdrawals = (float) $this->transactions()->where('type', 'Withdrawal')->sum('amount');
        $bankIncome = (float) Income::where('bank_account_id', $this->id)->sum('amount');
        $bankExpense = (float) Expense::where('bank_account_id', $this->id)->sum('amount');

        return round((float) $this->opening_balance + $deposits - $withdrawals + $bankIncome - $bankExpense, 2);
    }
}
