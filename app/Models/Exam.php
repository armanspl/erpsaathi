<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    protected $fillable = [
        'name',
        'type',
        'total_marks',
        'passing_marks',
        'min_marks',
        'max_marks',
        'description',
        'exam_type_id',
        'academic_session_id',
        'academic_term_id',
        'sort_order',
        'counts_toward_term',
        'is_internal_component',
        'start_date',
        'end_date',
        'status',
        'published_at',
        'pdf_accent_color',
    ];

    protected function casts(): array
    {
        return [
            'total_marks' => 'decimal:2',
            'passing_marks' => 'decimal:2',
            'min_marks' => 'decimal:2',
            'max_marks' => 'decimal:2',
            'sort_order' => 'integer',
            'counts_toward_term' => 'boolean',
            'is_internal_component' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'published_at' => 'datetime',
        ];
    }

    /** Preferred per-subject max when creating schedules (never invent 100 if policy exists). */
    public function defaultSubjectMaxMarks(float $fallback = 100.0): float
    {
        foreach ([$this->max_marks, $this->total_marks] as $value) {
            if ($value !== null && (float) $value > 0) {
                return (float) $value;
            }
        }

        return $fallback;
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ExamSchedule::class);
    }

    public function seatPlanSheets(): HasMany
    {
        return $this->hasMany(SeatPlanSheet::class);
    }
}
