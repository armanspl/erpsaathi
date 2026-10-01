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
    ];

    protected function casts(): array
    {
        return [
            'periods_per_day' => 'integer',
        ];
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
