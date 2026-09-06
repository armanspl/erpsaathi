<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpFeeStructurePlan extends Model
{
    protected $table = 'erp_fee_structure_plans';

    protected $fillable = [
        'academic_session_id',
        'type',
        'status',
    ];

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(ErpFeeStructurePlanScope::class, 'plan_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ErpFeeStructurePlanItem::class, 'plan_id')->orderBy('sort_order')->orderBy('id');
    }
}
