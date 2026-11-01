<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\AttendanceMonthlySummary;
use App\Models\ErpNotice;
use App\Models\Event;
use App\Models\ExamSchedule;
use App\Models\FeePayment;
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
        $attendanceDetail = null;
        if ($visibility['attendance']) {
            $rows = AttendanceMonthlySummary::where('student_id', $student->id)
                ->when($session, fn ($q) => $q->where('session_start_year', (int) substr($session->name, 0, 4)))
                ->orderBy('year')
                ->orderBy('month')
                ->get(['month', 'year', 'working_days', 'days_present', 'percentage']);
            $working = (int) $rows->sum('working_days');
            $present = (int) $rows->sum('days_present');
            $attendance = $working > 0 ? round(($present / $working) * 100, 2) : null;
            $attendanceDetail = [
                'working_days' => $working,
                'days_present' => $present,
                'days_absent' => max(0, $working - $present),
                'months' => $rows->slice(-6)->map(fn (AttendanceMonthlySummary $r) => [
                    'label' => substr($r->monthLabel(), 0, 3).' '.substr((string) $r->year, -2),
                    'working_days' => (int) $r->working_days,
                    'days_present' => (int) $r->days_present,
                    'percentage' => (float) $r->percentage,
                ])->values(),
            ];
        }

        $pendingDue = null;
        $fees = null;
        $feeVisible = $visibility['pending_fees'] || $visibility['fee_summary'];
        if ($feeVisible && $session && $session->start_date && $session->end_date) {
            $calc = FeeCalculator::forStudent($student, $session);
            $paidInfo = $balance->paidByHead($student, $session);
            $start = $session->start_date->copy()->startOfMonth();
            $sessionEnd = $session->end_date->copy()->startOfMonth();
            $tillNow = min($sessionEnd, now()->startOfMonth());
            $allKeys = [];
            for ($cursor = $start->copy(); $cursor <= $sessionEnd; $cursor->addMonth()) {
                $allKeys[] = $cursor->format('Y-m');
            }
            $allKeys = $balance->filterMonthsFromFeeStart($student, $allKeys);
            $nowKeys = array_values(array_filter($allKeys, fn ($k) => $k <= $tillNow->format('Y-m')));

            $nowRows = $nowKeys ? $balance->monthRows($student, $session, $nowKeys, $calc, $paidInfo) : [];
            $allRows = $allKeys ? $balance->monthRows($student, $session, $allKeys, $calc, $paidInfo) : [];
            $sum = fn (array $rows, string $col) => round(array_sum(array_column($rows, $col)), 2);

            $pendingDue = $visibility['pending_fees'] ? $sum($nowRows, 'due') : null;
            $fees = [
                'session' => $session->name,
                // Whole-session fee after concession — "how much in total"
                'total_payable' => round($sum($allRows, 'charge') - $sum($allRows, 'concession'), 2),
                'concession' => $sum($allRows, 'concession'),
                'paid' => $sum($allRows, 'paid'),
                // Unpaid up to this month — "how much is due now"
                'due_now' => $sum($nowRows, 'due'),
                // Unpaid for the whole session, including months still to come
                'balance' => $sum($allRows, 'due'),
                'due_months' => collect($nowRows)->filter(fn ($m) => (float) $m['due'] > 0)->pluck('month')->values(),
            ];
        }

        $recentPayments = [];
        if ($visibility['fee_history'] || $visibility['paid_fees']) {
            $recentPayments = FeePayment::where('student_id', $student->id)
                ->where('status', '!=', 'Rolled Back')
                ->orderByDesc('payment_date')
                ->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'receipt_no', 'payment_date', 'payment_mode', 'amount', 'items', 'status'])
                ->map(fn (FeePayment $p) => [
                    'id' => $p->id,
                    'receipt_no' => $p->receipt_no,
                    'payment_date' => $p->payment_date?->format('Y-m-d'),
                    'payment_mode' => $p->payment_mode,
                    'amount' => (float) $p->amount,
                    'status' => $p->status,
                    'fee_heads' => collect(is_array($p->items) ? $p->items : [])->pluck('fee_head_name')->filter()->unique()->implode(', '),
                ])->values();
        }

        $upcomingEvents = [];
        if ($visibility['events_calendar']) {
            $upcomingEvents = Event::whereDate('event_date', '>=', now()->toDateString())
                ->orderBy('event_date')
                ->limit(5)
                ->get(['id', 'title', 'venue', 'event_date', 'start_time', 'end_time', 'status'])
                ->map(fn (Event $e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'venue' => $e->venue,
                    'event_date' => $e->event_date?->format('Y-m-d'),
                    'start_time' => $e->start_time,
                    'end_time' => $e->end_time,
                ])->values();
        }

        $latestResult = null;
        if ($visibility['marks']) {
            $row = app(ExamController::class)->publishedResultRows($student, $session?->id)[0] ?? null;
            if ($row) {
                $latestResult = collect($row)->only(['exam_id', 'exam_name', 'obtained', 'max_total', 'percentage', 'grade', 'result', 'rank'])->all();
            }
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
            'attendance' => $attendanceDetail,
            'pending_due' => $pendingDue,
            'fees' => $fees,
            'recent_payments' => $recentPayments,
            'upcoming_events' => $upcomingEvents,
            'latest_result' => $latestResult,
            'next_exam' => $nextExam,
            'recent_notices' => $recentNotices,
            'visibility' => $visibility,
        ]);
    }
}
