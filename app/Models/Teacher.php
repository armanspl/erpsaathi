<?php

namespace App\Models;

use App\Models\Concerns\HasAttendance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    use HasAttendance;

    protected $fillable = [
        'employee_id',
        'name',
        'phone',
        'email',
        'school_class_id',
        'status',
        'salary',
        'custom_field_values',
        'signature_path',
    ];

    protected $appends = [
        'signature_url',
    ];

    protected function casts(): array
    {
        return [
            'salary' => 'decimal:2',
            'custom_field_values' => 'array',
        ];
    }

    public function getSignatureUrlAttribute(): ?string
    {
        if (! $this->signature_path) {
            return null;
        }

        return url('/erp/api/people/teachers/'.$this->id.'/signature');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function classAssignments(): HasMany
    {
        return $this->hasMany(TeacherClassAssignment::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'teacher_subjects');
    }
}
