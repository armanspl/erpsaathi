<?php

namespace App\Models\Concerns;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Shared by every person type (Student/Teacher/Staff/Driver) that can be marked present/absent or take leave. */
trait HasAttendance
{
    public function attendances(): MorphMany
    {
        return $this->morphMany(Attendance::class, 'attendable');
    }

    public function leaveRequests(): MorphMany
    {
        return $this->morphMany(LeaveRequest::class, 'attendable');
    }
}
