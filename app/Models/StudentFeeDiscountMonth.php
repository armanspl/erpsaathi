<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentFeeDiscountMonth extends Model
{
    protected $fillable = [
        'student_id',
        'academic_session_id',
        'months',
    ];

    protected function casts(): array
    {
        return [
            'months' => 'array',
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

    /**
     * Normalized unique calendar month numbers (1–12).
     *
     * @return list<int>
     */
    public function monthNumbers(): array
    {
        return collect($this->months ?? [])
            ->map(fn ($m) => (int) $m)
            ->filter(fn ($m) => $m >= 1 && $m <= 12)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
