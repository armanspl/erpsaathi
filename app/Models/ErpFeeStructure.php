<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Named ErpFeeStructure (table: erp_fee_structures) to avoid colliding with the
// pre-existing public-website App\Models\FeeStructure / fee_structures table,
// which is an unrelated feature (marketing site fee display, not the ERP ledger).
class ErpFeeStructure extends Model
{
    protected $table = 'erp_fee_structures';

    protected $fillable = [
        'academic_session_id',
        'school_class_id',
        'fee_head_id',
        'amount',
        'frequency',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }
}
