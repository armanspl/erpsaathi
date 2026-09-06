<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSessionHistory extends Model
{
    protected $table = 'student_session_history';

    protected $fillable = [
        'student_id',
        'session',
        'class_name',
        'section_name',
        'roll_no',
        'status',
        'promotion_status',
        'previous_year_schooling_status',
        'previous_year_class',
        'exam_appeared',
        'exam_result',
        'exam_marks_percent',
        'attendance_days',
        'attendance_percent',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
