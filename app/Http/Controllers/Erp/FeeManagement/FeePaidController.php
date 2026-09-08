<?php

namespace App\Http\Controllers\Erp\FeeManagement;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Student;
use App\Services\FeeBalanceService;
use App\Services\FeeCalculator;
use App\Support\TabularExport;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Fee Paid — students who have cleared dues till the current month,
 * and/or have payments attributed to advance (future) months.
 */
class FeePaidController extends Controller
{
    public function index(Request $request)
    {
        $rows = $this->buildRows($request);

        return response()->json([
            'rows' => $rows,
            'summary' => [
                'students' => $rows->count(),
                'charged' => round($rows->sum('total_fee'), 2),
                'paid' => round($rows->sum('total_paid'), 2),
                'concession' => round($rows->sum('total_discount'), 2),
                'advance_paid' => round($rows->sum('advance_paid'), 2),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $data = $request->validate([
            'format' => 'nullable|in:csv,xlsx,pdf',
            'view' => 'nullable|in:till_current,advance',
        ]);
        $format = $data['format'] ?? 'xlsx';
        $view = $data['view'] ?? 'till_current';

        $rows = $this->buildRows($request);

        if ($view === 'advance') {
            $header = [
                'Admission No', 'Student', 'Roll No', 'Branch', 'Class', 'Section',
                'Father', 'Mother', 'Advance Months', 'Advance Paid', 'Paid Through',
            ];
            $table = $rows->map(fn (array $r) => [
                $r['admission_no'], $r['name'], $r['roll_no'], $r['branch'], $r['school_class'], $r['section'],
                $r['father'], $r['mother'], $r['advance_months_label'], $r['advance_paid'], $r['paid_through_label'],
            ])->all();
            $title = 'Fee Paid (Advance Months)';
        } else {
            $header = [
                'Admission No', 'Student', 'Roll No', 'Branch', 'Class', 'Section',
                'Father', 'Mother', 'Charge', 'Paid', 'Concession', 'Paid Through',
            ];
            $table = $rows->map(fn (array $r) => [
                $r['admission_no'], $r['name'], $r['roll_no'], $r['branch'], $r['school_class'], $r['section'],
                $r['father'], $r['mother'], $r['total_fee'], $r['total_paid'], $r['total_discount'], $r['paid_through_label'],
            ])->all();
            $title = 'Fee Paid (Till Current Month)';
        }

        return TabularExport::stream($header, $table, 'fee-paid-'.$view, $title, $format);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function buildRows(Request $request): \Illuminate\Support\Collection
    {
        $data = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'search' => 'nullable|string|max:100',
            'view' => 'nullable|in:till_current,advance',
        ]);

        $view = $data['view'] ?? 'till_current';

        $query = Student::with([
            'schoolClass:id,name',
            'section:id,name',
            'branch:id,name',
            'father:id,name',
            'mother:id,name',
        ])->where('status', 'Active');

        if (! empty($data['branch_id'])) {
            $query->forBranch((int) $data['branch_id']);
        }
        if (! empty($data['school_class_id'])) {
            $query->where('school_class_id', $data['school_class_id']);
        }
        if (! empty($data['section_id'])) {
            $query->where('section_id', $data['section_id']);
        }
        if (! empty($data['search'])) {
            $q = $data['search'];
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('admission_no', 'like', "%{$q}%");
            });
        }

        $students = $query->orderBy('name')->get();

        $all = AcademicSession::requestWantsAll($request);
        $sessions = $all
            ? AcademicSession::orderByDesc('start_date')->get()
            : collect([AcademicSession::fromRequest($request, true)])->filter();

        FeeCalculator::flushRuntimeCache();
        FeeCalculator::warmForStudents($students, $sessions);

        $balance = app(FeeBalanceService::class);
        $nowKey = now()->format('Y-m');

        $rows = $students->map(function (Student $student) use ($sessions, $view, $balance, $nowKey) {
            $totalFee = $totalDiscount = $totalPaid = $advancePaid = 0.0;
            $paidThrough = null;
            $advanceMonthKeys = [];
            $scopeLabel = null;
            $include = false;

            foreach ($sessions as $session) {
                $calc = FeeCalculator::forStudent($student, $session);
                $paidInfo = $balance->paidByHead($student, $session);
                $paidMonths = $balance->paidMonths($student, $session, $calc['breakdown'] ?? [], $paidInfo);

                $tillKeys = $balance->filterMonthsFromFeeStart(
                    $student,
                    $this->monthKeysTillCurrent($session)
                );
                $advanceKeys = $balance->filterMonthsFromFeeStart(
                    $student,
                    $this->monthKeysAdvance($session)
                );

                $till = $balance->remainingForMonths($student, $session, $tillKeys, $calc, $paidInfo);
                $adv = $balance->remainingForMonths($student, $session, $advanceKeys, $calc, $paidInfo);

                $sessionPaidThrough = null;
                foreach ($paidMonths as $m) {
                    if ($m <= $nowKey && ($sessionPaidThrough === null || $m > $sessionPaidThrough)) {
                        $sessionPaidThrough = $m;
                    }
                    if ($m > $nowKey) {
                        $advanceMonthKeys[$m] = true;
                    }
                }

                if ($view === 'advance') {
                    $futurePaid = array_values(array_filter($paidMonths, fn ($m) => $m > $nowKey));
                    if ($futurePaid !== []) {
                        $include = true;
                        $advancePaid += $adv['paid'];
                        $totalFee += $adv['charge'];
                        $totalPaid += $adv['paid'];
                        $totalDiscount += $adv['discount'];
                        $scopeLabel = 'Advance months after '.Carbon::createFromFormat('Y-m', $nowKey)->format('M Y');
                    }
                } else {
                    // Fully cleared through current month (no arrears) and has charge or payment in scope.
                    if ($tillKeys !== [] && $till['due'] <= 0.0001 && ($till['charge'] > 0 || $till['paid'] > 0)) {
                        $include = true;
                        $totalFee += $till['charge'];
                        $totalPaid += $till['paid'];
                        $totalDiscount += $till['discount'];
                        $advancePaid += $adv['paid'];
                        $scopeLabel = 'Till '.Carbon::createFromFormat('Y-m', $nowKey)->format('M Y');
                    }
                }

                if ($sessionPaidThrough && ($paidThrough === null || $sessionPaidThrough > $paidThrough)) {
                    $paidThrough = $sessionPaidThrough;
                }
            }

            if (! $include) {
                return null;
            }

            $advanceList = array_keys($advanceMonthKeys);
            sort($advanceList);

            return [
                'student_id' => $student->id,
                'admission_no' => $student->admission_no,
                'name' => $student->name,
                'roll_no' => $student->roll_no,
                'branch' => $student->branch?->name,
                'school_class' => $student->schoolClass?->name,
                'section' => $student->section?->name,
                'father' => $student->father?->name,
                'mother' => $student->mother?->name,
                'scope_label' => $scopeLabel,
                'total_fee' => round($totalFee, 2),
                'total_discount' => round($totalDiscount, 2),
                'total_paid' => round($totalPaid, 2),
                'advance_paid' => round($advancePaid, 2),
                'paid_through' => $paidThrough,
                'paid_through_label' => $paidThrough
                    ? Carbon::createFromFormat('Y-m', $paidThrough)->format('M Y')
                    : '—',
                'advance_months' => $advanceList,
                'advance_months_label' => $advanceList
                    ? collect($advanceList)->map(fn ($k) => Carbon::createFromFormat('Y-m', $k)->format('M Y'))->implode(', ')
                    : '—',
            ];
        })->filter()->values();

        return $rows;
    }

    /** @return list<string> */
    private function monthKeysTillCurrent(AcademicSession $session): array
    {
        return $this->monthKeysInRange($session, false);
    }

    /** Future months in the session after the current calendar month. */
    /** @return list<string> */
    private function monthKeysAdvance(AcademicSession $session): array
    {
        if (! $session->start_date || ! $session->end_date) {
            return [];
        }

        $start = $session->start_date->copy()->startOfMonth();
        $end = $session->end_date->copy()->startOfMonth();
        $now = now()->startOfMonth();
        $from = $now->copy()->addMonth();

        if ($from < $start) {
            $from = $start->copy();
        }
        if ($from > $end) {
            return [];
        }

        $keys = [];
        $cursor = $from->copy();
        while ($cursor <= $end) {
            $keys[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $keys;
    }

    /** @return list<string> */
    private function monthKeysInRange(AcademicSession $session, bool $includeNext): array
    {
        if (! $session->start_date || ! $session->end_date) {
            return [];
        }

        $start = $session->start_date->copy()->startOfMonth();
        $end = $session->end_date->copy()->startOfMonth();
        $now = now()->startOfMonth();

        if ($now < $start) {
            return [];
        }
        $till = $now->gt($end) ? $end->copy() : $now->copy();
        if ($includeNext) {
            $next = $till->copy()->addMonth();
            $till = $next->gt($end) ? $end->copy() : $next;
        }

        $keys = [];
        $cursor = $start->copy();
        while ($cursor <= $till) {
            $keys[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $keys;
    }
}
