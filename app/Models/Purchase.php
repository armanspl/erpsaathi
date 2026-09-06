<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purchase extends Model
{
    protected $fillable = ['voucher_no', 'supplier_id', 'purchase_date', 'items', 'total_amount', 'remarks', 'purchased_by_id'];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'purchase_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchasedBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'purchased_by_id');
    }
}
