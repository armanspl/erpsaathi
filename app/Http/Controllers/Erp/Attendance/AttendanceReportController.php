<?php

namespace App\Http\Controllers\Erp\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Erp\Attendance\Concerns\ResolvesAttendableType;
use App\Models\Attendance;
use Illuminate\Http\Request;

class AttendanceReportController extends Controller
{
    use ResolvesAttendableType;

    public function index(Request $request)
    {
        $type = $request->query('type', 'student');
        $modelClass = $this->attendableModelClass($type);
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $codeColumn = match ($type) {
            'student' => 'admission_no',
            default => 'employee_id',
        };

        $peopleQuery = $modelClass::query()->orderBy('name');
        if ($type === 'student') {
            $peopleQuery->where('status', 'Active');
        }

        $people = $peopleQuery->get(['id', 'name', $codeColumn]);
        if ($people->isEmpty()) {
            return response()->json(['type' => $type, 'from' => $from, 'to' => $to, 'rows' => []]);
        }

        $stats = Attendance::query()
            ->where('attendable_type', $type)
            ->whereBetween('date', [$from, $to])
            ->whereIn('attendable_id', $people->pluck('id'))
            ->selectRaw('attendable_id')
            ->selectRaw("SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent")
            ->selectRaw("SUM(CASE WHEN status = 'Leave' THEN 1 ELSE 0 END) as leave_count")
            ->selectRaw("SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late")
            ->selectRaw("SUM(CASE WHEN status = 'Half Day' THEN 1 ELSE 0 END) as half_day")
            ->selectRaw('COUNT(*) as total_marked')
            ->groupBy('attendable_id')
            ->get()
            ->keyBy('attendable_id');

        $rows = $people->map(function ($person) use ($stats, $codeColumn) {
            $entry = $stats->get($person->id);
            $present = (int) ($entry->present ?? 0);
            $absent = (int) ($entry->absent ?? 0);
            $leave = (int) ($entry->leave_count ?? 0);
            $late = (int) ($entry->late ?? 0);
            $halfDay = (int) ($entry->half_day ?? 0);
            $total = (int) ($entry->total_marked ?? 0);
            $percentage = $total > 0 ? round((($present + $late + $halfDay * 0.5) / $total) * 100, 1) : 0;

            return [
                'id' => $person->id,
                'name' => $person->name,
                'code' => $person->{$codeColumn} ?? null,
                'present' => $present,
                'absent' => $absent,
                'leave' => $leave,
                'late' => $late,
                'half_day' => $halfDay,
                'total_marked' => $total,
                'percentage' => $percentage,
            ];
        });

        return response()->json(['type' => $type, 'from' => $from, 'to' => $to, 'rows' => $rows->values()]);
    }
}
