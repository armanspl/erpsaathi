<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Named ParentGuardian because `Parent` is a reserved word in PHP and cannot be
// used as a class name. Backs the People > Parents module; table is `parents`.
class ParentGuardian extends Model
{
    protected $table = 'parents';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'occupation',
        'annual_income',
        'qualification',
        'dob',
        'aadhaar_no',
        'pan_no',
        // Only meaningful when this record is linked as a student's guardian_id —
        // the relation to a specific student isn't otherwise stored on this model.
        'relationship',
        'address',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'annual_income' => 'decimal:2',
        ];
    }

    public function studentsAsFather()
    {
        return $this->hasMany(Student::class, 'father_id');
    }

    public function studentsAsMother()
    {
        return $this->hasMany(Student::class, 'mother_id');
    }

    public function studentsAsGuardian()
    {
        return $this->hasMany(Student::class, 'guardian_id');
    }
}
