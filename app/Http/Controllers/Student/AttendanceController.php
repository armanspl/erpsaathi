<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\EnforcesPortalVisibility;
use App\Models\AttendanceMonthlySummary;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    use EnforcesPortalVisibility;

    public function index()
    {
        $this->abortIfModuleDisabled('attendance');

        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $rows = AttendanceMonthlySummary::query()
            ->where('student_id', $student->id)
            ->orderByDesc('session_start_year')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get(['id', 'month', 'year', 'session_start_year', 'working_days', 'days_present', 'percentage']);

        $totalWorking = (int) $rows->sum('working_days');
        $totalPresent = (int) $rows->sum('days_present');

        return response()->json([
            'months' => $rows->map(fn (AttendanceMonthlySummary $r) => [
                'month' => $r->month,
                'month_label' => $r->monthLabel(),
                'year' => $r->year,
                'working_days' => $r->working_days,
                'days_present' => $r->days_present,
                'percentage' => (float) $r->percentage,
            ])->values(),
            'overall' => [
                'working_days' => $totalWorking,
                'days_present' => $totalPresent,
                'percentage' => $totalWorking > 0 ? round(($totalPresent / $totalWorking) * 100, 2) : 0,
            ],
        ]);
    }
}
