<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatPlanSeat extends Model
{
    protected $fillable = ['seat_plan_room_id', 'seat_no', 'row_no', 'col_no', 'bench_slot', 'student_id'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(SeatPlanRoom::class, 'seat_plan_room_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
