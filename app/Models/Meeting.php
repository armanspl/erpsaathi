<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meeting extends Model
{
    protected $fillable = [
        'type', 'title', 'description', 'meeting_date', 'start_time', 'end_time',
        'meeting_link', 'venue', 'audience', 'teacher_id', 'school_class_id',
        'status', 'recording_url', 'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'created_by_id');
    }
}
