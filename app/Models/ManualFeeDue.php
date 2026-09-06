<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualFeeDue extends Model
{
    protected $fillable = [
        'student_id',
        'academic_session_id',
        'fee_head_id',
        'month',
        'amount',
        'paid_amount',
        'due_date',
        'remarks',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
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

    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'created_by_id');
    }

    public function balance(): float
    {
        return max(0, round((float) $this->amount - (float) $this->paid_amount, 2));
    }
};
