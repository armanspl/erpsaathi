<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceMonthlySummary extends Model
{
    protected $fillable = [
        'student_id',
        'school_class_id',
        'class_sheet',
        'month',
        'year',
        'session_start_year',
        'working_days',
        'days_present',
        'percentage',
        'imported_by_id',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'session_start_year' => 'integer',
            'working_days' => 'integer',
            'days_present' => 'integer',
            'percentage' => 'float',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'imported_by_id');
    }

    public function monthLabel(): string
    {
        return date('F', mktime(0, 0, 0, max(1, min(12, $this->month)), 1));
    }
}
