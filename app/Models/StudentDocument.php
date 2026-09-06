<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDocument extends Model
{
    protected $fillable = [
        'student_id',
        'photo_path',
        'aadhaar_path',
        'pan_path',
        'birth_certificate_path',
        'transfer_certificate_path',
        'marksheet_path',
        'father_aadhaar_path',
        'father_pan_path',
        'mother_aadhaar_path',
        'mother_pan_path',
        'father_photo_path',
        'mother_photo_path',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
