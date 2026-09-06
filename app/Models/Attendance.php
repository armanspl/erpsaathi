<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attendance extends Model
{
    protected $fillable = [
        'attendable_type',
        'attendable_id',
        'date',
        'status',
        'remarks',
        'photo_path',
        'marked_by_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function attendable(): MorphTo
    {
        return $this->morphTo();
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'marked_by_id');
    }
}
