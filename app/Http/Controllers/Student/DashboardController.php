<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\AttendanceMonthlySummary;
use App\Models\ErpNotice;
use App\Models\ExamSchedule;
use App\Models\Student;
use App\Services\FeeBalanceService;
use App\Services\FeeCalculator;
use App\Services\StudentPortalCatalog;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(FeeBalanceService $balance)
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();
        $student->loadMissing(['schoolClass:id,name', 'section:id,name']);
        $visibility = StudentPortalCatalog::resolve();
        $session = AcademicSession::where('is_current', true)->first();

        $attendance = null;
        if ($visibility['attendance']) {
            $rows = AttendanceMonthlySummary::where('student_id', $student->id)
                ->when($session, fn ($q) => $q->where('session_start_year', (int) substr($session->name, 0, 4)))
                ->get(['working_days', 'days_present']);
            $working = (int) $rows->sum('working_days');
            $present = (int) $rows->sum('days_present');
            $attendance = $working > 0 ? round(($present / $working) * 100, 2) : null;
        }

        $pendingDue = null;
        if ($visibility['pending_fees'] && $session && $session->start_date && $session->end_date) {
            $calc = FeeCalculator::forStudent($student, $session);
            $start = $session->start_date->copy()->startOfMonth();
            $end = min($session->end_date->copy()->startOfMonth(), now()->startOfMonth());
            $monthKeys = [];
            for ($cursor = $start->copy(); $cursor <= $end; $cursor->addMonth()) {
                $monthKeys[] = $cursor->format('Y-m');
            }
            $monthKeys = $balance->filterMonthsFromFeeStart($student, $monthKeys);
            $paidInfo = $balance->paidByHead($student, $session);
            $monthRows = $balance->monthRows($student, $session, $monthKeys, $calc, $paidInfo);
            $pendingDue = round(array_sum(array_column($monthRows, 'due')), 2);
        }

        $nextExam = null;
        if ($visibility['exam_schedule']) {
            $upcoming = ExamSchedule::where('school_class_id', $student->school_class_id)
                ->whereDate('date', '>=', now()->toDateString())
                ->with(['exam:id,name', 'subject:id,name'])
                ->orderBy('date')
                ->first();
            if ($upcoming) {
                $nextExam = [
                    'exam_name' => $upcoming->exam?->name,
                    'subject' => $upcoming->subject?->name,
                    'date' => $upcoming->date?->format('Y-m-d'),
                ];
            }
        }

        $recentNotices = 0;
        if ($visibility['notices']) {
            $recentNotices = ErpNotice::whereIn('audience', ['All', 'Students'])
                ->where('status', 'Published')
                ->where('publish_date', '>=', now()->subDays(14)->toDateString())
                ->count();
        }

        return response()->json([
            'student' => [
                'name' => $student->name,
                'admission_no' => $student->admission_no,
                'roll_no' => $student->roll_no,
                'school_class' => $student->schoolClass?->name,
                'section' => $student->section?->name,
            ],
            'attendance_percentage' => $attendance,
            'pending_due' => $pendingDue,
            'next_exam' => $nextExam,
            'recent_notices' => $recentNotices,
            'visibility' => $visibility,
        ]);
    }
}
