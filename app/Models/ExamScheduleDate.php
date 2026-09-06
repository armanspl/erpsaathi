<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamScheduleDate extends Model
{
    protected $fillable = ['exam_schedule_sheet_id', 'date', 'is_holiday'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_holiday' => 'boolean',
        ];
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(ExamScheduleSheet::class, 'exam_schedule_sheet_id');
    }

    public function cells(): HasMany
    {
        return $this->hasMany(ExamScheduleCell::class);
    }
}
