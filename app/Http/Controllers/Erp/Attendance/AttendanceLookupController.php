<?php

namespace App\Http\Controllers\Erp\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\WorkingDayConfig;
use App\Support\AttendanceCache;
use Illuminate\Support\Facades\Cache;

/**
 * Lightweight Attendance boot payload for sidebar prefetch.
 */
class AttendanceLookupController extends Controller
{
    public function __invoke()
    {
        $payload = Cache::remember(AttendanceCache::LOOKUPS, AttendanceCache::TTL, function () {
            $config = WorkingDayConfig::current();

            return [
                'weekly_off_days' => $config->weekly_off_days ?? [],
                'holidays' => Holiday::query()
                    ->orderBy('date')
                    ->get(['id', 'name', 'date', 'type']),
                'leave_pending' => LeaveRequest::query()->where('status', 'Pending')->count(),
                'leave_awaiting_rejoin' => LeaveRequest::query()
                    ->where('status', 'Approved')
                    ->whereNull('rejoined_at')
                    ->count(),
            ];
        });

        return response()->json($payload);
    }
}
