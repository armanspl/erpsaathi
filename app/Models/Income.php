<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Income extends Model
{
    protected $fillable = [
        'voucher_no', 'source', 'amount', 'date',
        'payment_mode', 'bank_account_id', 'remarks', 'received_by_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'received_by_id');
    }
}
