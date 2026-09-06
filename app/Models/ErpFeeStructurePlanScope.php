<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpFeeStructurePlanScope extends Model
{
    protected $table = 'erp_fee_structure_plan_scopes';

    protected $fillable = [
        'plan_id',
        'branch_id',
        'school_class_id',
        'section_id',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ErpFeeStructurePlan::class, 'plan_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
