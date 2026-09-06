<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AdmissionEnquiry;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Driver;
use App\Models\Event;
use App\Models\FeePayment;
use App\Models\Homework;
use App\Models\ImportExportLog;
use App\Models\ImportFailedRow;
use App\Models\LeaveRequest;
use App\Models\SalarySlip;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Support\Facades\Cache;

class DashboardCalculator
{
    /**
     * Everything the ERP landing dashboard needs, computed live across every module.
     * $session/$allSessions mirror the header session-picker convention used across the rest
     * of the app (see AcademicSession::fromRequest()/requestWantsAll()) — pass the resolved
     * session to scope student-derived widgets to it, or $allSessions=true to combine every
     * session (today's attendance and non-academic operational counters are never scoped —
     * "today" and pending leave/enquiry/import queues aren't session-bound data).
     */
    public static function summary(?AcademicSession $session = null, bool $allSessions = false): array
    {
        $today = now()->toDateString();
        $students = self::scopedStudents($session, $allSessions)->with('schoolClass:id,name')->get();

        $feeReport = FeeReportCalculator::summary($session, $allSessions);
        $financeReport = FinanceReportCalculator::summary($session, $allSessions);

        return [
            'session_label' => $allSessions ? 'All Sessions' : ($session?->name ?? 'All Sessions'),
            'people_stats' => [
                'students' => $students->count(),
                'teachers' => Teacher::count(),
                'staff' => Staff::count(),
                'drivers' => Driver::count(),
            ],
            'money_stats' => [
                'fee_collected' => $feeReport['total_paid'],
                'fee_pending' => $feeReport['total_due'],
                'bank_balance' => $financeReport['total_bank_balance'],
                'expense' => $financeReport['total_expense'],
            ],
            'today_summary' => self::todaySummary($today),
            'class_strength' => self::classStrength($students, $session, $allSessions),
            'category_by_class' => self::categoryByClass($students, $session, $allSessions),
            'fee_collection_status' => self::feeCollectionStatus($students, $session),
            'recent_admissions' => self::recentAdmissions($session, $allSessions),
            'pending_tasks' => self::pendingTasks($feeReport['defaulters_count']),
            'upcoming_events' => Event::where('status', '!=', 'Cancelled')
                ->where('event_date', '>=', $today)
                ->orderBy('event_date')
                ->limit(5)
                ->get()
                ->map(fn (Event $e) => ['title' => $e->title, 'date' => $e->event_date->format('d M')])
                ->all(),
            'recent_activities' => self::recentActivities(),
        ];
    }

    /** Same rule the People > Students list uses: history rows when browsing a specific session, falling back to "no history yet = belongs to the current session" only for the live/current session. */
    private static function scopedStudents(?AcademicSession $session, bool $allSessions)
    {
        $query = Student::where('status', 'Active');

        if ($allSessions || ! $session) {
            return $query;
        }

        $aliases = AcademicSession::nameAliases($session->name);

        $query->where(function ($q) use ($aliases, $session) {
            $q->whereHas('sessionHistories', fn ($h) => $h->whereIn('session', $aliases));
            if ($session->is_current) {
                $q->orWhereDoesntHave('sessionHistories');
            }
        })->with(['sessionHistories' => fn ($h) => $h->whereIn('session', $aliases)->select(['id', 'student_id', 'session', 'class_name', 'section_name'])]);

        return $query;
    }

    private static function todaySummary(string $today): array
    {
        $studentAttendance = Attendance::where('attendable_type', 'student')->where('date', $today)->get();
        $staffAttendance = Attendance::whereIn('attendable_type', ['teacher', 'staff'])->where('date', $today)->get();
        $todayPayments = FeePayment::whereDate('payment_date', $today)->get();

        $net = fn (FeePayment $p) => max(0, (float) $p->amount - (float) $p->refunded_amount);
        $cash = $todayPayments
            ->filter(fn (FeePayment $p) => strcasecmp((string) $p->payment_mode, 'Cash') === 0)
            ->sum($net);
        $bank = $todayPayments
            ->reject(fn (FeePayment $p) => strcasecmp((string) $p->payment_mode, 'Cash') === 0)
            ->sum($net);

        return [
            'students' => [
                'present' => $studentAttendance->where('status', 'Present')->count(),
                'absent' => $studentAttendance->where('status', 'Absent')->count(),
            ],
            'staff' => [
                'present' => $staffAttendance->where('status', 'Present')->count(),
                'absent' => $staffAttendance->where('status', 'Absent')->count(),
            ],
            'fee' => [
                'received' => round((float) $todayPayments->sum($net), 2),
                'cash' => round((float) $cash, 2),
                'bank' => round((float) $bank, 2),
                'receipts' => $todayPayments->count(),
            ],
        ];
    }

    private static function classStrength($students, ?AcademicSession $session, bool $allSessions): array
    {
        $newIds = self::newAdmissionStudentIds($session, $allSessions);

        return $students->groupBy(fn (Student $s) => self::historicalClassName($s, $session, $allSessions))
            ->map(function ($rows, $name) use ($newIds) {
                $newRows = $rows->filter(fn (Student $s) => $newIds->contains($s->id));

                return [
                    'class' => $name,
                    'boys' => $rows->where('gender', 'Male')->count(),
                    'girls' => $rows->where('gender', 'Female')->count(),
                    'new_boys' => $newRows->where('gender', 'Male')->count(),
                    'new_girls' => $newRows->where('gender', 'Female')->count(),
                ];
            })
            ->values()
            ->all();
    }

    /** Students admitted in the active session window (or all sessions). */
    private static function newAdmissionStudentIds(?AcademicSession $session, bool $allSessions)
    {
        $query = Student::query()->where('status', 'Active')->whereNotNull('admission_date');

        if (! $allSessions && $session && $session->start_date && $session->end_date) {
            $query->whereBetween('admission_date', [$session->start_date, $session->end_date]);
        } elseif (! $allSessions && $session) {
            // No date window — treat current calendar year admissions as "new".
            $query->whereYear('admission_date', now()->year);
        }

        return $query->pluck('id');
    }

    /** Category × gender counts nested under each class. */
    private static function categoryByClass($students, ?AcademicSession $session, bool $allSessions): array
    {
        return $students->groupBy(fn (Student $s) => self::historicalClassName($s, $session, $allSessions))
            ->map(function ($classRows, $className) {
                $categories = $classRows
                    ->groupBy(fn (Student $s) => trim((string) ($s->category ?: '')) ?: 'Unassigned')
                    ->map(fn ($rows, $category) => [
                        'category' => $category,
                        'boys' => $rows->where('gender', 'Male')->count(),
                        'girls' => $rows->where('gender', 'Female')->count(),
                    ])
                    ->sortBy('category')
                    ->values()
                    ->all();

                return [
                    'class' => $className,
                    'categories' => $categories,
                    'boys' => $classRows->where('gender', 'Male')->count(),
                    'girls' => $classRows->where('gender', 'Female')->count(),
                ];
            })
            ->values()
            ->all();
    }

    /** Browsing a past session should show the class the student was in back then, not their current class — the same reasoning People > Students uses its sessionHistories snapshot for. */
    private static function historicalClassName(Student $s, ?AcademicSession $session, bool $allSessions): string
    {
        if (! $allSessions && $session && ! $session->is_current) {
            $history = $s->sessionHistories->first();
            if ($history && $history->class_name) {
                return $history->class_name.($history->section_name ? " ({$history->section_name})" : '');
            }
        }

        return $s->schoolClass?->name ?? 'Unassigned';
    }

    private static function feeCollectionStatus($students, ?AcademicSession $session): array
    {
        $session ??= AcademicSession::where('is_current', true)->first();
        if (! $session) {
            return [];
        }

        FeeCalculator::warmForStudents($students, $session);
        $balance = app(FeeBalanceService::class);
        $monthKeysBase = collect($session->months())
            ->map(fn ($m) => $m['key'] ?? null)
            ->filter()
            ->values()
            ->all();

        // Till current month — same pending basis as Fee Due / money_stats.fee_pending.
        if ($session->start_date && $session->end_date) {
            $start = $session->start_date->copy()->startOfMonth();
            $end = $session->end_date->copy()->startOfMonth();
            $now = now()->startOfMonth();
            if ($now->lt($start)) {
                $monthKeysBase = [];
            } else {
                $till = $now->gt($end) ? $end->copy() : $now->copy();
                $monthKeysBase = [];
                $cursor = $start->copy();
                while ($cursor->lte($till)) {
                    $monthKeysBase[] = $cursor->format('Y-m');
                    $cursor->addMonth();
                }
            }
        }

        return $students->groupBy(fn (Student $s) => $s->schoolClass?->name ?? 'Unassigned')
            ->map(function ($classStudents, $className) use ($session, $balance, $monthKeysBase) {
                $totalCharge = 0.0;
                $totalPaid = 0.0;
                $totalDue = 0.0;
                $pending = 0;

                foreach ($classStudents as $student) {
                    $calc = FeeCalculator::forStudent($student, $session);
                    $keys = $balance->filterMonthsFromFeeStart($student, $monthKeysBase);
                    $paidInfo = $balance->paidByHead($student, $session);
                    $remaining = $balance->remainingForMonths($student, $session, $keys, $calc, $paidInfo);
                    $totalCharge += $remaining['charge'];
                    $totalPaid += $remaining['paid'];
                    $totalDue += $remaining['due'];
                    if ($remaining['due'] > 0.0001) {
                        $pending++;
                    }
                }

                $charge = max($totalCharge, 0.0001);

                return [
                    'class' => $className,
                    'collected_amount' => round($totalPaid, 0),
                    'due_amount' => round($totalDue, 0),
                    'collected' => (int) round(($totalPaid / $charge) * 100),
                    'due_pct' => (int) round(($totalDue / $charge) * 100),
                    'pending' => $pending,
                ];
            })
            ->sortByDesc('due_amount')
            ->values()
            ->all();
    }

    private static function recentAdmissions(?AcademicSession $session, bool $allSessions): array
    {
        return Student::with('schoolClass:id,name')
            ->when(! $allSessions && $session && $session->start_date && $session->end_date, fn ($q) => $q->whereBetween('admission_date', [$session->start_date, $session->end_date]))
            ->orderByDesc('admission_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Student $s) => [
                'adm' => $s->admission_no,
                'name' => $s->name,
                'class' => $s->schoolClass?->name ?? '—',
                'date' => optional($s->admission_date)->format('d-m-y') ?? '—',
            ])
            ->all();
    }

    private static function pendingTasks(int $feeDefaultersCount): array
    {
        return [
            ['label' => 'Fee Payments Pending', 'count' => $feeDefaultersCount, 'to' => '/fee-management/fee-due'],
            ['label' => 'Leave Requests Pending', 'count' => LeaveRequest::where('status', 'Pending')->count(), 'to' => '/attendance/leave-approval'],
            ['label' => 'Admission Enquiries', 'count' => AdmissionEnquiry::whereIn('stage', ['enquiry', 'follow_up', 'registered'])->count(), 'to' => '/admissions/enquiry'],
            ['label' => 'Salary Slips Pending', 'count' => SalarySlip::where('status', 'Pending')->count(), 'to' => '/finance-and-payroll/salary-slips'],
            ['label' => 'Import Failed Records', 'count' => ImportFailedRow::count(), 'to' => '/import-export?type=failed-records'],
        ];
    }

    /**
     * Compact alert list for the header bell — same operational queues as the dashboard,
     * plus today's birthdays. Cached briefly so opening the menu stays snappy.
     */
    public static function notifications(?AcademicSession $session = null, bool $allSessions = false): array
    {
        $cacheKey = 'erp.header.notifications.'.($allSessions ? 'all' : ($session?->id ?? 'none'));

        return Cache::remember($cacheKey, 60, function () use ($session, $allSessions) {
            $feeDefaulters = (int) (FeeReportCalculator::summary($session, $allSessions)['defaulters_count'] ?? 0);
            $today = now();

            $items = [
                [
                    'key' => 'fee_due',
                    'label' => 'Fee Due',
                    'icon' => '💰',
                    'hint' => 'Students with pending fee',
                    'count' => $feeDefaulters,
                    'path' => '/fee-management/fee-due',
                ],
                [
                    'key' => 'leave_pending',
                    'label' => 'Leave Pending',
                    'icon' => '🗓️',
                    'hint' => 'Awaiting your approval',
                    'count' => LeaveRequest::where('status', 'Pending')->count(),
                    'path' => '/attendance/leave-approval',
                ],
                [
                    'key' => 'birthdays',
                    'label' => "Today's Birthday",
                    'icon' => '🎂',
                    'hint' => 'Wish them well',
                    'count' => Student::query()
                        ->where('status', 'Active')
                        ->whereNotNull('dob')
                        ->whereMonth('dob', $today->month)
                        ->whereDay('dob', $today->day)
                        ->count(),
                    'path' => '/people/students',
                ],
                [
                    'key' => 'admissions',
                    'label' => 'New Admission',
                    'icon' => '📝',
                    'hint' => 'Open enquiries & follow-ups',
                    'count' => AdmissionEnquiry::whereIn('stage', ['enquiry', 'follow_up', 'registered'])->count(),
                    'path' => '/admissions/enquiry',
                ],
                [
                    'key' => 'homework',
                    'label' => 'Homework Assigned',
                    'icon' => '📚',
                    'hint' => 'Assigned in the last 7 days',
                    'count' => Homework::query()
                        ->whereDate('assigned_date', '>=', $today->copy()->subDays(7)->toDateString())
                        ->count(),
                    'path' => '/academics/homework',
                ],
            ];

            $total = collect($items)->sum('count');

            return [
                'total' => $total,
                'items' => $items,
            ];
        });
    }

    private static function recentActivities(): array
    {
        $activities = collect();

        $lastStudent = Student::orderByDesc('created_at')->first();
        if ($lastStudent) {
            $activities->push(['text' => "New admission created — {$lastStudent->name}", 'at' => $lastStudent->created_at]);
        }

        $lastPayment = FeePayment::with('student:id,name')->orderByDesc('created_at')->first();
        if ($lastPayment) {
            $who = $lastPayment->student?->name ?? 'a student';
            $activities->push(['text' => "Fee receipt {$lastPayment->receipt_no} generated for {$who}", 'at' => $lastPayment->created_at]);
        }

        $lastSalary = SalarySlip::with('employee')->orderByDesc('created_at')->first();
        if ($lastSalary) {
            $who = $lastSalary->employee?->name ?? 'an employee';
            $activities->push(['text' => "Salary slip generated for {$who} ({$lastSalary->period})", 'at' => $lastSalary->created_at]);
        }

        $lastCertificate = Certificate::with('student:id,name')->orderByDesc('created_at')->first();
        if ($lastCertificate) {
            $who = $lastCertificate->student?->name ?? 'a student';
            $activities->push(['text' => "{$lastCertificate->type} issued for {$who}", 'at' => $lastCertificate->created_at]);
        }

        $lastImport = ImportExportLog::where('direction', 'Import')->orderByDesc('created_at')->first();
        if ($lastImport) {
            $activities->push(['text' => "Admin imported {$lastImport->entity} data ({$lastImport->success_count} records)", 'at' => $lastImport->created_at]);
        }

        return $activities->sortByDesc('at')->take(5)->pluck('text')->values()->all();
    }
}
