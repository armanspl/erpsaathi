<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bed extends Model
{
    protected $fillable = ['room_id', 'bed_no', 'status'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
