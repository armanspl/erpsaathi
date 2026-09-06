<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostelFee extends Model
{
    protected $fillable = ['hostel_allocation_id', 'period', 'amount', 'status', 'paid_on', 'payment_mode', 'collected_by_id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(HostelAllocation::class, 'hostel_allocation_id');
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'collected_by_id');
    }
}
