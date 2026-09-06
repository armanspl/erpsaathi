<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamScheduleSheet extends Model
{
    protected $fillable = ['exam_id', 'branch_id'];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sittings(): HasMany
    {
        return $this->hasMany(ExamScheduleSitting::class)->orderBy('sort_order');
    }

    public function dates(): HasMany
    {
        return $this->hasMany(ExamScheduleDate::class)->orderBy('date');
    }
}
