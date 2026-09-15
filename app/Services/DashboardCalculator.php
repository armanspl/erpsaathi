<?php

namespace App\Services;

use App\Models\AcademicCalendarEntry;
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
use App\Support\DashboardCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardCalculator
{
    /**
     * Everything the ERP landing dashboard needs.
     * Fee money stats and fee_collection_status share one ledger pass (FeeReportCalculator).
     * The full payload is cached briefly and invalidated via DashboardCache::forget().
     */
    public static function summary(?AcademicSession $session = null, bool $allSessions = false): array
    {
        $cacheKey = DashboardCache::summaryKey($allSessions ? null : $session?->id, $allSessions);

        return Cache::remember($cacheKey, DashboardCache::TTL, function () use ($session, $allSessions) {
            return self::computeSummary($session, $allSessions);
        });
    }

    private static function computeSummary(?AcademicSession $session, bool $allSessions): array
    {
        $today = now()->toDateString();
        $students = self::scopedStudents($session, $allSessions)->with('schoolClass:id,name')->get();

        $feeBundle = FeeReportCalculator::summaryWithClassStatus($session, $allSessions, $students);
        $feeReport = $feeBundle['summary'];
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
            'fee_collection_status' => $feeBundle['fee_collection_status'],
            'recent_admissions' => self::recentAdmissions($session, $allSessions),
            'pending_tasks' => self::pendingTasks($feeReport['defaulters_count']),
            'upcoming_events' => self::upcomingEvents($today, $session, $allSessions),
            'recent_activities' => self::recentActivities(),
        ];
    }

    /**
     * Upcoming items for the dashboard tile: Academic Calendar (import / Academics page)
     * merged with Communication events. Same payload shape as before: title + date.
     */
    private static function upcomingEvents(string $today, ?AcademicSession $session, bool $allSessions): array
    {
        $items = collect();

        $calendarQuery = AcademicCalendarEntry::query()
            ->where(function ($q) use ($today) {
                $q->whereDate('start_date', '>=', $today)
                    ->orWhereDate('end_date', '>=', $today);
            })
            ->orderByRaw('start_date is null')
            ->orderBy('start_date')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->limit(10);

        if (! $allSessions && $session) {
            $calendarQuery->where(function ($q) use ($session) {
                $q->where('academic_session_id', $session->id)->orWhereNull('academic_session_id');
            });
        }

        foreach ($calendarQuery->get() as $entry) {
            $items->push([
                'title' => $entry->title,
                'date' => self::formatCalendarDate($entry),
                'sort' => optional($entry->start_date)?->toDateString()
                    ?? optional($entry->end_date)?->toDateString()
                    ?? $today,
            ]);
        }

        $commEvents = Event::where('status', '!=', 'Cancelled')
            ->where('event_date', '>=', $today)
            ->orderBy('event_date')
            ->limit(10)
            ->get();

        foreach ($commEvents as $e) {
            $items->push([
                'title' => $e->title,
                'date' => $e->event_date->format('d M'),
                'sort' => $e->event_date->toDateString(),
            ]);
        }

        return $items
            ->sortBy('sort')
            ->take(5)
            ->map(fn (array $row) => ['title' => $row['title'], 'date' => $row['date']])
            ->values()
            ->all();
    }

    private static function formatCalendarDate(AcademicCalendarEntry $entry): string
    {
        if ($entry->start_date && $entry->end_date && ! $entry->start_date->equalTo($entry->end_date)) {
            return $entry->start_date->format('d M').' – '.$entry->end_date->format('d M');
        }
        if ($entry->start_date) {
            return $entry->start_date->format('d M');
        }
        if ($entry->end_date) {
            return $entry->end_date->format('d M');
        }
        if (is_string($entry->date_label) && trim($entry->date_label) !== '') {
            return trim($entry->date_label);
        }

        return '—';
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
        $studentRows = Attendance::query()
            ->where('attendable_type', 'student')
            ->where('date', $today)
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');

        $staffRows = Attendance::query()
            ->whereIn('attendable_type', ['teacher', 'staff'])
            ->where('date', $today)
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');

        $feeAgg = FeePayment::query()
            ->whereDate('payment_date', $today)
            ->selectRaw("
                COUNT(*) as receipts,
                COALESCE(SUM(GREATEST(0, amount - refunded_amount)), 0) as received,
                COALESCE(SUM(CASE WHEN LOWER(payment_mode) = 'cash' THEN GREATEST(0, amount - refunded_amount) ELSE 0 END), 0) as cash,
                COALESCE(SUM(CASE WHEN LOWER(payment_mode) <> 'cash' THEN GREATEST(0, amount - refunded_amount) ELSE 0 END), 0) as bank
            ")
            ->first();

        return [
            'students' => [
                'present' => (int) ($studentRows['Present'] ?? 0),
                'absent' => (int) ($studentRows['Absent'] ?? 0),
            ],
            'staff' => [
                'present' => (int) ($staffRows['Present'] ?? 0),
                'absent' => (int) ($staffRows['Absent'] ?? 0),
            ],
            'fee' => [
                'received' => round((float) ($feeAgg->received ?? 0), 2),
                'cash' => round((float) ($feeAgg->cash ?? 0), 2),
                'bank' => round((float) ($feeAgg->bank ?? 0), 2),
                'receipts' => (int) ($feeAgg->receipts ?? 0),
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
     * plus today's birthdays. Reuses the cached fee report (no second full ledger pass).
     */
    public static function notifications(?AcademicSession $session = null, bool $allSessions = false): array
    {
        $cacheKey = DashboardCache::notificationsKey($allSessions ? null : $session?->id, $allSessions);

        return Cache::remember($cacheKey, DashboardCache::TTL, function () use ($session, $allSessions) {
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
