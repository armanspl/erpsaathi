<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LeaveRequest extends Model
{
    protected $fillable = [
        'attendable_type',
        'attendable_id',
        'leave_type',
        'from_date',
        'to_date',
        'reason',
        'attachment_path',
        'status',
        'approved_by_id',
        'approved_at',
        'rejection_reason',
        'rejoined_at',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'approved_at' => 'datetime',
            'rejoined_at' => 'date',
        ];
    }

    public function attendable(): MorphTo
    {
        return $this->morphTo();
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'approved_by_id');
    }
}
