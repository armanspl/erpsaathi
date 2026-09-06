<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicTerm extends Model
{
    protected $fillable = [
        'academic_session_id',
        'name',
        'sort_order',
        'max_marks',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'max_marks' => 'decimal:2',
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class)->orderBy('sort_order')->orderBy('id');
    }

    public function coScholasticGrades(): HasMany
    {
        return $this->hasMany(CoScholasticGrade::class);
    }
}
