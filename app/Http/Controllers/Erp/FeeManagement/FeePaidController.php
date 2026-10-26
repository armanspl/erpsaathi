<?php

namespace App\Http\Controllers\Erp\FeeManagement;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\FeePayment;
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
    /** Vehicles of the students in scope — set by buildRows() for the Vehicle filter. */
    private array $vehicles = [];

    public function index(Request $request)
    {
        $rows = $this->buildRows($request);

        return response()->json([
            'rows' => $rows,
            'vehicles' => $this->vehicles,
            'summary' => [
                'students' => $rows->count(),
                'charged' => round($rows->sum('total_fee'), 2),
                'paid' => round($rows->sum('total_paid'), 2),
                'concession' => round($rows->sum('total_discount'), 2),
                'advance_paid' => round($rows->sum('advance_paid'), 2),
                'collected' => round($rows->sum('session_paid'), 2),
                'due' => round($rows->sum('due'), 2),
                'cleared' => $rows->where('payment_status', 'cleared')->count(),
                'partial' => $rows->where('payment_status', 'partial')->count(),
                // Received between paid_from and paid_to (only when that filter is on).
                'range_collected' => round($rows->sum('range_paid'), 2),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $data = $request->validate([
            'format' => 'nullable|in:csv,xlsx,pdf',
            'view' => 'nullable|in:paid_any,till_current,advance',
        ]);
        $format = $data['format'] ?? 'xlsx';
        $view = $data['view'] ?? 'till_current';

        $rows = $this->buildRows($request);
        $range = $this->paidRange($request);

        if ($view === 'paid_any') {
            $header = [
                'Admission No', 'Student', 'Class', 'Section', 'Receipts', 'Last Receipt', 'Last Paid Date',
                'Total Paid', 'Charged (till now)', 'Concession', 'Outstanding', 'Status', 'Paid Through',
            ];
            $table = $rows->map(fn (array $r) => [
                $r['admission_no'], $r['name'], $r['school_class'], $r['section'], $r['receipts_count'], $r['last_receipt_no'],
                $r['last_paid_date'], $r['session_paid'], $r['total_fee'], $r['total_discount'], $r['due'],
                ['cleared' => 'Fully paid', 'partial' => 'Partly paid', 'left' => 'Left school'][$r['payment_status']],
                $r['paid_through_label'],
            ])->all();
            $title = 'Fee Paid (All Students With Payments)';
        } elseif ($view === 'advance') {
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

        // Paid-on filter: what each student paid in that period, and the period in the title.
        if ($range) {
            $header[] = 'Paid in Period';
            $table = array_map(fn (array $line, array $r) => [...$line, $r['range_paid']], $table, $rows->all());
            $title .= ' — Paid '.$this->rangeLabel($range);
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
            'view' => 'nullable|in:paid_any,till_current,advance',
            'vehicle' => 'nullable|string|max:100',
            'dues' => 'nullable|in:all,due,clear',
        ]);

        $view = $data['view'] ?? 'till_current';
        $range = $this->paidRange($request);

        $all = AcademicSession::requestWantsAll($request);
        $sessions = $all
            ? AcademicSession::orderByDesc('start_date')->get()
            : collect([AcademicSession::fromRequest($request, true)])->filter();

        $query = Student::with([
            'schoolClass:id,name',
            'section:id,name',
            'branch:id,name',
            'father:id,name',
            'mother:id,name',
            'udiseDetail:id,student_id,vehicle',
        ]);
        if ($view === 'paid_any') {
            // Everyone who paid anything in the selected session(s) — including students who
            // have since left — whether or not they still owe fees.
            $query->whereIn('id', FeePayment::query()
                ->where('status', '!=', 'Rolled Back')
                ->when(! $all, fn ($q) => $q->touchingSessions($sessions->pluck('id')->all()))
                ->select('student_id'));
        } else {
            $query->where('status', 'Active');
        }

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
        // Paid on a date / in a month / between dates: only students with a receipt in that period.
        if ($range) {
            $query->whereIn('id', FeePayment::query()
                ->where('status', '!=', 'Rolled Back')
                ->when(! $all, fn ($q) => $q->touchingSessions($sessions->pluck('id')->all()))
                ->whereDate('payment_date', '>=', $range[0])
                ->whereDate('payment_date', '<=', $range[1])
                ->select('student_id'));
        }

        $students = $query->orderBy('name')->get();

        // Vehicle: the one on the student's record, else their active transport route's bus
        // (same rule as the Demand Slip). The list feeds the Vehicle filter.
        $routeVehicles = \App\Models\StudentTransport::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->where('status', 'Active')
            ->with('route:id,vehicle_id', 'route.vehicle:id,vehicle_no')
            ->orderByDesc('id')
            ->get()
            ->unique('student_id')
            ->mapWithKeys(fn ($t) => [$t->student_id => trim((string) ($t->route?->vehicle?->vehicle_no ?? ''))]);
        $vehicleOf = fn (Student $s) => (trim((string) ($s->udiseDetail?->vehicle ?? '')) ?: ($routeVehicles[$s->id] ?? '')) ?: 'None';
        $this->vehicles = $students->map($vehicleOf)->reject(fn ($v) => $v === 'None')->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
        $vehicle = trim((string) ($data['vehicle'] ?? ''));
        if ($vehicle !== '') {
            $students = $students->filter(fn (Student $s) => match ($vehicle) {
                '__any' => $vehicleOf($s) !== 'None',
                default => $vehicleOf($s) === $vehicle,
            })->values();
        }

        FeeCalculator::flushRuntimeCache();
        FeeCalculator::warmForStudents($students, $sessions);

        $balance = app(FeeBalanceService::class);
        $nowKey = now()->format('Y-m');

        $rows = $students->map(function (Student $student) use ($sessions, $view, $balance, $nowKey, $vehicleOf) {
            $totalFee = $totalDiscount = $totalPaid = $advancePaid = $dueTillNow = $countedPaid = $outsidePaid = 0.0;
            $outsideMonths = [];
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

                $dueTillNow += $till['due'];
                $countedPaid += $till['paid'] + $adv['paid'];

                // Paid for months outside this session (e.g. "MAR TO APR" saved under 2026-27 —
                // March 2026 is the last month of 2025-26): no charge of this session exists for
                // it, so Fee Due doesn't count it. Taken from the same month allocation Fee Due
                // uses; annual / one-time heads are matched by total, whatever the month.
                if ($session->start_date && $session->end_date) {
                    [$from, $to] = [$session->start_date->format('Y-m'), $session->end_date->format('Y-m')];
                    $freqByHead = collect($calc['breakdown'] ?? [])
                        ->mapWithKeys(fn ($b) => [(string) ($b['fee_head_id'] ?? '') => strtolower(str_replace(' ', '_', (string) ($b['frequency'] ?? 'monthly')))]);
                    foreach ($paidInfo['by_month'] ?? [] as $headId => $months) {
                        if (in_array($freqByHead[(string) $headId] ?? 'monthly', ['annual', 'one_time'], true)) {
                            continue;
                        }
                        foreach ($months as $monthKey => $amount) {
                            if ($amount > 0 && ($monthKey < $from || $monthKey > $to)) {
                                $outsidePaid += (float) $amount;
                                $outsideMonths[$monthKey] = true;
                            }
                        }
                    }
                }

                if ($view === 'paid_any') {
                    // Payment status, not a filter: charged / paid / concession through the
                    // current month, with what is still due shown alongside.
                    $include = true;
                    $totalFee += $till['charge'];
                    $totalPaid += $till['paid'];
                    $totalDiscount += $till['discount'];
                    $advancePaid += $adv['paid'];
                    $scopeLabel = 'Till '.Carbon::createFromFormat('Y-m', $nowKey)->format('M Y');
                } elseif ($view === 'advance') {
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
                'vehicle' => $vehicleOf($student),
                'scope_label' => $scopeLabel,
                'total_fee' => round($totalFee, 2),
                'total_discount' => round($totalDiscount, 2),
                'total_paid' => round($totalPaid, 2),
                'advance_paid' => round($advancePaid, 2),
                // A student who has left keeps their payments listed, but monthly fees after they
                // left are not a real due — so no outstanding amount is shown for them.
                'due' => $student->status === 'Active' ? round($dueTillNow, 2) : 0.0,
                // Paid toward this session's charges (up to now + advance months) — what Fee Due counts.
                'counted_paid' => round($countedPaid, 2),
                'outside_session_paid' => round($outsidePaid, 2),
                'outside_session_months' => collect(array_keys($outsideMonths))->sort()
                    ->map(fn ($k) => Carbon::createFromFormat('Y-m', $k)->format('M Y'))->implode(', '),
                'payment_status' => $student->status !== 'Active' ? 'left' : ($dueTillNow > 0.0001 ? 'partial' : 'cleared'),
                'student_status' => $student->status,
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

        // Dues: students still owing up to now vs fully paid (left students owe nothing new).
        $dues = $data['dues'] ?? 'all';
        if ($dues !== 'all') {
            $rows = $rows->filter(fn (array $r) => $dues === 'due' ? $r['due'] > 0.0001 : $r['due'] <= 0.0001)->values();
        }

        return $this->withLatestReceipts($rows, $all ? null : $sessions->pluck('id')->all(), $range);
    }

    /**
     * The Paid-on filter as [from, to] dates (Y-m-d), or null when it's off. A single date or
     * a month arrives as a from/to pair from the page.
     *
     * @return array{0: string, 1: string}|null
     */
    private function paidRange(Request $request): ?array
    {
        $data = $request->validate([
            'paid_from' => 'nullable|date',
            'paid_to' => 'nullable|date',
        ]);
        $from = ! empty($data['paid_from']) ? Carbon::parse($data['paid_from'])->toDateString() : null;
        $to = ! empty($data['paid_to']) ? Carbon::parse($data['paid_to'])->toDateString() : null;
        if (! $from && ! $to) {
            return null;
        }
        $range = [$from ?? '1900-01-01', $to ?? '9999-12-31'];
        sort($range);

        return $range;
    }

    /** "on 05 Oct 2026", "in Oct 2026", "05 Oct 2026 to 20 Oct 2026" — for export titles. */
    private function rangeLabel(array $range): string
    {
        [$from, $to] = [Carbon::parse($range[0]), Carbon::parse($range[1])];
        if ($from->isSameDay($to)) {
            return 'on '.$from->format('d M Y');
        }
        if ($from->isSameDay($from->copy()->startOfMonth()) && $to->isSameDay($from->copy()->endOfMonth())) {
            return 'in '.$from->format('M Y');
        }
        if ($range[0] === '1900-01-01') {
            return 'till '.$to->format('d M Y');
        }
        if ($range[1] === '9999-12-31') {
            return 'from '.$from->format('d M Y');
        }

        return $from->format('d M Y').' to '.$to->format('d M Y');
    }

    /**
     * Fee lines paid by one student in the selected session(s), grouped by receipt —
     * the expandable panel under each Fee Paid row.
     */
    public function details(Request $request, Student $student)
    {
        $all = AcademicSession::requestWantsAll($request);
        $session = $all ? null : AcademicSession::fromRequest($request, true);
        $sessionsById = AcademicSession::query()->get()->keyBy('id');

        $payments = FeePayment::query()
            ->where('student_id', $student->id)
            ->where('status', '!=', 'Rolled Back')
            ->when($session, fn ($q) => $q->touchingSessions([$session->id]))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();
        if ($session) {
            // A receipt that also pays another session's fees shows only this session's lines.
            $payments = FeePayment::slicesFor($payments, [$session->id]);
        }

        $frequencies = [];
        $frequencyFor = function (int $sessionId, int $feeHeadId) use (&$frequencies, $student, $sessionsById): ?string {
            if (! isset($frequencies[$sessionId])) {
                $s = $sessionsById->get($sessionId);
                $frequencies[$sessionId] = $s
                    ? collect(FeeCalculator::forStudent($student, $s)['breakdown'] ?? [])->pluck('frequency', 'fee_head_id')->all()
                    : [];
            }

            return $frequencies[$sessionId][$feeHeadId] ?? null;
        };

        $receipts = $payments->map(function (FeePayment $p) use ($sessionsById, $frequencyFor) {
            $lines = [];
            foreach (is_array($p->items) ? $p->items : [] as $item) {
                $sessionId = (int) ($p->itemSessionId($item) ?: $p->academic_session_id);
                $months = array_values(array_filter((array) ($item['months'] ?? ($item['month'] ?? null) ?? [])));
                $paid = (float) ($item['amount'] ?? 0);
                $discount = (float) ($item['discount'] ?? 0);
                $charge = max((float) ($item['charge'] ?? 0), $paid + $discount);
                // Transport isn't part of the class fee plan; it's billed monthly.
                $frequency = $frequencyFor($sessionId, (int) ($item['fee_head_id'] ?? 0))
                    ?? (! empty($item['transport_route_id']) || stripos((string) ($item['fee_head_name'] ?? ''), 'transport') !== false ? 'monthly' : null);
                $sessionName = $sessionsById->get($sessionId)?->name ?? ($item['session'] ?? null);

                // One line per month, splitting a multi-month item evenly (as entered on the receipt).
                $parts = $months === [] ? [null] : $months;
                $n = count($parts);
                foreach ($parts as $i => $monthKey) {
                    $share = fn (float $v) => $i === $n - 1 ? round($v - round($v / $n, 2) * ($n - 1), 2) : round($v / $n, 2);
                    $month = $monthKey ? Carbon::createFromFormat('Y-m', substr((string) $monthKey, 0, 7)) : null;
                    $lineSession = $sessionsById->get($sessionId);
                    $lines[] = [
                        // A month outside its own session (March 2026 on a 2026-27 line) — no charge
                        // of that session exists for it, so Fee Due doesn't count it.
                        'outside_session' => $month && ! in_array($frequency, ['annual', 'one_time'], true)
                            && $lineSession?->start_date && $lineSession->end_date
                            && ($month->format('Y-m') < $lineSession->start_date->format('Y-m') || $month->format('Y-m') > $lineSession->end_date->format('Y-m')),
                        'fee_name' => $item['fee_head_name'] ?? 'Fee',
                        'fee_type' => $frequency ? ucwords(str_replace('_', ' ', $frequency)) : null,
                        'session' => $sessionName,
                        'month' => $month?->format('F'),
                        'year' => $month?->format('Y'),
                        'class' => $item['class'] ?? null,
                        'remarks' => $item['remarks'] ?? null,
                        'amount' => $share($charge),
                        'discount' => $share($discount),
                        'final' => $share($paid),
                    ];
                }
            }

            $totalFee = round(array_sum(array_column($lines, 'amount')), 2);
            $totalDiscount = round(array_sum(array_column($lines, 'discount')), 2);
            $itemsPaid = round(array_sum(array_column($lines, 'final')), 2);
            $fine = (float) $p->fine_amount;

            return [
                'id' => $p->id,
                'receipt_no' => $p->receipt_no,
                'payment_date' => $p->payment_date?->format('Y-m-d'),
                'payment_mode' => $p->payment_mode,
                'reference_no' => $p->reference_no,
                'status' => $p->status,
                'remarks' => $p->remarks,
                'multi_session' => (bool) $p->multi_session,
                'lines' => $lines,
                'totals' => [
                    'total_fee' => $totalFee,
                    'total_discount' => $totalDiscount,
                    'grand_total' => round($totalFee - $totalDiscount, 2),
                    'fine' => round($fine, 2),
                    'paid' => round((float) $p->amount, 2),
                    'refunded' => round((float) $p->refunded_amount, 2),
                    'balance' => round(max(0, $totalFee - $totalDiscount - $itemsPaid), 2),
                ],
            ];
        })->values();

        return response()->json([
            'session' => $session?->name,
            'receipts' => $receipts,
            'summary' => [
                'receipts' => $receipts->count(),
                'total_fee' => round($receipts->sum('totals.total_fee'), 2),
                'total_discount' => round($receipts->sum('totals.total_discount'), 2),
                'fine' => round($receipts->sum('totals.fine'), 2),
                'paid' => round($receipts->sum('totals.paid'), 2),
                'refunded' => round($receipts->sum('totals.refunded'), 2),
            ],
        ]);
    }

    /**
     * Adds each student's latest receipt (in the selected sessions) for the collapsed row.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * With the Paid-on filter, the "latest receipt" is the latest one in that period, and
     * range_receipts / range_paid count only that period's receipts.
     *
     * @param  list<int>|null  $sessionIds  null = all sessions
     * @param  array{0: string, 1: string}|null  $range  Paid-on filter (Y-m-d from / to)
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function withLatestReceipts(\Illuminate\Support\Collection $rows, ?array $sessionIds, ?array $range = null): \Illuminate\Support\Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $payments = FeePayment::query()
            ->whereIn('student_id', $rows->pluck('student_id'))
            ->where('status', '!=', 'Rolled Back')
            ->when($sessionIds !== null, fn ($q) => $q->touchingSessions($sessionIds))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get(['id', 'student_id', 'receipt_no', 'payment_date', 'academic_session_id', 'multi_session', 'items', 'amount', 'discount_amount', 'fine_amount', 'session_fines', 'refunded_amount']);
        // Session totals count only the selected session's share of a multi-session receipt.
        $byStudent = ($sessionIds !== null ? FeePayment::slicesFor($payments, $sessionIds) : $payments)->groupBy('student_id');
        return $rows->map(function (array $r) use ($byStudent, $range) {
            $list = $byStudent->get($r['student_id'], collect());
            $inRange = $range
                ? $list->filter(function (FeePayment $p) use ($range) {
                    $date = $p->payment_date?->format('Y-m-d');

                    return $date !== null && $date >= $range[0] && $date <= $range[1];
                })->values()
                : $list;
            $last = $inRange->first();
            $received = round((float) $list->sum(fn (FeePayment $p) => (float) $p->amount - (float) $p->refunded_amount), 2);
            // Received = counted toward dues + months outside the session + fines + the rest.
            $fine = (float) $list->sum(fn (FeePayment $p) => (float) $p->fine_amount);
            $rest = $received - (float) ($r['counted_paid'] ?? 0) - (float) ($r['outside_session_paid'] ?? 0) - $fine;

            return $r + [
                'receipts_count' => $list->count(),
                'session_paid' => $received,
                'fine_paid' => round($fine, 2),
                // Paid toward a head with no charge set up, or more than a month's charge.
                'unmatched_paid' => round(max(0, $rest), 2),
                'last_payment_id' => $last?->id,
                'last_receipt_no' => $last?->receipt_no,
                'last_paid_date' => $last?->payment_date?->format('Y-m-d'),
                'last_paid_amount' => $last ? round((float) $last->amount, 2) : null,
                'range_receipts' => $inRange->count(),
                'range_paid' => round((float) $inRange->sum(fn (FeePayment $p) => (float) $p->amount - (float) $p->refunded_amount), 2),
            ];
        });
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
