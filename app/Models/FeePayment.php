<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeePayment extends Model
{
    protected $fillable = [
        'receipt_no',
        'student_id',
        'academic_session_id',
        'items',
        'amount',
        'discount_amount',
        'fine_amount',
        'payment_mode',
        'payment_date',
        'remarks',
        'status',
        'refunded_amount',
        'refund_reason',
        'refunded_at',
        'collected_by_id',
        'reference_no',
        'edited_at',
        'edited_by_id',
        'rollback_reason',
        'rolled_back_at',
        'rolled_back_by_id',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'fine_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'payment_date' => 'date',
            'refunded_at' => 'datetime',
            'edited_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'collected_by_id');
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'edited_by_id');
    }

    public function rolledBackBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'rolled_back_by_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(FeePaymentAudit::class)->latest('id');
    }
}
