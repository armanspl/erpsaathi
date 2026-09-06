<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeatPlanRoom extends Model
{
    protected $fillable = ['seat_plan_sheet_id', 'name', 'gender', 'sort_order'];

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(SeatPlanSheet::class, 'seat_plan_sheet_id');
    }

    public function seats(): HasMany
    {
        return $this->hasMany(SeatPlanSeat::class)->orderBy('seat_no');
    }
}
