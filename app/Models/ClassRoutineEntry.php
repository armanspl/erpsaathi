<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassRoutineEntry extends Model
{
    /** Monday=1 ... Saturday=6 — matches the labels used across the Class Routine UI/export. */
    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

    protected $fillable = [
        'class_routine_sheet_id',
        'school_class_id',
        'section_id',
        'day_of_week',
        'period_number',
        'subject_id',
        'subject_label',
        'teacher_id',
        'teacher_label',
        'color',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'period_number' => 'integer',
        ];
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(ClassRoutineSheet::class, 'class_routine_sheet_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
