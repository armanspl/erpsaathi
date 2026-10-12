<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoScholasticStudentGrade extends Model
{
    protected $fillable = [
        'student_id',
        'academic_session_id',
        'co_scholastic_area_id',
        'grade',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(CoScholasticArea::class, 'co_scholastic_area_id');
    }
}
