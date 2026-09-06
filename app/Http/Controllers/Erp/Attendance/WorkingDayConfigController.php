<?php

namespace App\Http\Controllers\Erp\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\WorkingDayConfig;
use App\Support\AttendanceCache;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkingDayConfigController extends Controller
{
    private const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    public function show(Request $request)
    {
        $config = WorkingDayConfig::current();
        $month = $request->query('month', now()->format('Y-m'));
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $daysInMonth = $start->daysInMonth;

        $holidayDates = Holiday::whereYear('date', $start->year)->whereMonth('date', $start->month)->pluck('date')->map(fn ($d) => $d->toDateString())->all();

        $workingDays = 0;
        $offDays = 0;
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = $start->copy()->day($day);
            if (in_array($date->format('l'), $config->weekly_off_days, true) || in_array($date->toDateString(), $holidayDates, true)) {
                $offDays++;
            } else {
                $workingDays++;
            }
        }

        return response()->json([
            'weekly_off_days' => $config->weekly_off_days,
            'month' => $month,
            'days_in_month' => $daysInMonth,
            'working_days' => $workingDays,
            'off_days' => $offDays,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'weekly_off_days' => 'required|array',
            'weekly_off_days.*' => Rule::in(self::WEEKDAYS),
        ]);

        $config = WorkingDayConfig::current();
        $config->update($data);
        AttendanceCache::forget();

        return response()->json($config);
    }
}
