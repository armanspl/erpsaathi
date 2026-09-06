<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpFeeStructurePlanItem extends Model
{
    protected $table = 'erp_fee_structure_plan_items';

    protected $fillable = [
        'plan_id',
        'label',
        'amount',
        'frequency',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ErpFeeStructurePlan::class, 'plan_id');
    }
}
