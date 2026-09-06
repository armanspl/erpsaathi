<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeatPlanSheet extends Model
{
    protected $fillable = [
        'exam_id', 'branch_id', 'school_class_id', 'section_id',
        'rows', 'columns', 'students_per_bench', 'room_count',
        'fill_order', 'separate_by_gender', 'room_prefix', 'room_suffix',
    ];

    protected function casts(): array
    {
        return [
            'separate_by_gender' => 'boolean',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(SeatPlanRoom::class)->orderBy('sort_order');
    }
}
