<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamScheduleCell extends Model
{
    protected $fillable = ['exam_schedule_date_id', 'school_class_id', 'section_id', 'subject_id', 'start_time', 'end_time'];

    public function date(): BelongsTo
    {
        return $this->belongsTo(ExamScheduleDate::class, 'exam_schedule_date_id');
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
}
