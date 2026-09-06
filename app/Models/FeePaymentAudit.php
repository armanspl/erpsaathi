<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeePaymentAudit extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'fee_payment_id',
        'action',
        'before',
        'after',
        'reason',
        'refund_amount',
        'refund_reason',
        'performed_by_id',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'refund_amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(FeePayment::class, 'fee_payment_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'performed_by_id');
    }
}
