<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassRoutineSheet extends Model
{
    protected $fillable = [
        'academic_session_id',
        'branch_id',
        'title',
        'periods_per_day',
        'periods_by_day',
    ];

    protected function casts(): array
    {
        return [
            'periods_per_day' => 'integer',
            'periods_by_day' => 'array',
        ];
    }

    /** Periods for a given day (1=Mon…6=Sat); falls back to sheet-wide periods_per_day. */
    public function periodsForDay(int $dayOfWeek): int
    {
        $map = $this->periods_by_day ?? [];
        $n = (int) ($map[(string) $dayOfWeek] ?? $map[$dayOfWeek] ?? 0);

        return $n > 0 ? $n : max(1, (int) ($this->periods_per_day ?: 7));
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ClassRoutineEntry::class);
    }
}
