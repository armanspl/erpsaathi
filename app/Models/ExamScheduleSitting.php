<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamScheduleSitting extends Model
{
    protected $fillable = ['exam_schedule_sheet_id', 'label', 'start_time', 'end_time', 'sort_order'];

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(ExamScheduleSheet::class, 'exam_schedule_sheet_id');
    }
}
