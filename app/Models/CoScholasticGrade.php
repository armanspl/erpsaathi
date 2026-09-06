<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoScholasticGrade extends Model
{
    public const AREAS = [
        'work_education' => 'Work Education',
        'drawing_art' => 'Drawing & Art',
        'sports' => 'Sports',
    ];

    protected $fillable = [
        'student_id',
        'academic_term_id',
        'area',
        'grade',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }
}
