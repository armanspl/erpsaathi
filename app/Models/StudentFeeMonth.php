<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * People > Students "Fee Months" / "Transport Fee Months" for one academic session: calendar
 * month numbers (1–12) the student is charged in. NULL = not chosen — the old Fee Start Month /
 * transport fee start rule applies (see FeeBalanceService::isFeeMonth / isTransportMonth).
 */
class StudentFeeMonth extends Model
{
    protected $table = 'student_fee_months';

    protected $fillable = ['student_id', 'academic_session_id', 'fee_months', 'transport_months'];

    protected function casts(): array
    {
        return [
            'fee_months' => 'array',
            'transport_months' => 'array',
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
}
