<?php

namespace App\Http\Controllers\Erp\FeeManagement;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\ManualFeeDue;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Services\DocumentDataBuilder;
use App\Services\DocumentRenderService;
use App\Services\FeeBalanceService;
use App\Services\FeeCalculator;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeeDueController extends Controller
{
    /**
     * Automatic dues — computed from fee structure − discounts − payments.
     * Filters: branch_id, school_class_id, section_id, search, scope=till_current|current_month.
     */
    public function index(Request $request)
    {
        $rows = $this->automaticRows($request);

        return response()->json([
            'rows' => $rows,
            'summary' => [
                'students' => $rows->count(),
                'charged' => round($rows->sum('total_fee'), 2),
                'paid' => round($rows->sum('total_paid'), 2),
                'due' => round($rows->sum('due'), 2),
            ],
        ]);
    }

    /** Shared row-building for the automatic-dues screen and its export. */
    private function automaticRows(Request $request): \Illuminate\Support\Collection
    {
        $data = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'search' => 'nullable|string|max:100',
            'scope' => 'nullable|in:till_current,current_month,till_next_month',
        ]);

        $scope = $data['scope'] ?? 'till_current';

        $query = Student::with([
            'schoolClass:id,name',
            'section:id,name',
            'branch:id,name',
            'father:id,name',
            'mother:id,name',
            'udiseDetail:student_id,admission_type',
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

        $rows = $students->map(function (Student $student) use ($sessions, $scope, $balance) {
            $totalFee = $totalDiscount = $totalPaid = $due = 0.0;
            $scopeLabel = null;
            foreach ($sessions as $session) {
                $calc = FeeCalculator::forStudent($student, $session);
                $monthKeys = $this->monthKeysInScope($session, $scope);
                $monthKeys = $balance->filterMonthsFromFeeStart($student, $monthKeys);
                $paidInfo = $balance->paidByHead($student, $session);
                $remaining = $balance->remainingForMonths($student, $session, $monthKeys, $calc, $paidInfo);

                $totalFee += $remaining['charge'];
                $totalDiscount += $remaining['discount'];
                $totalPaid += $remaining['paid'];
                $due += $remaining['due'];
                $scopeLabel = $this->scopeLabel($session, $scope);
            }

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
                'due' => round($due, 2),
            ];
        })->values();

        return $rows;
    }

    /** Automatic dues export — same filters/session-scoping as index(), format = pdf|xlsx|csv. */
    public function exportAutomatic(Request $request)
    {
        $rows = $this->automaticRows($request);
        $header = ['Admission No', 'Student', 'Roll No', 'Branch', 'Class', 'Section', 'Father', 'Mother', 'Charge', 'Paid', 'Concession', 'Due'];
        $table = $rows->map(fn (array $r) => [
            $r['admission_no'], $r['name'], $r['roll_no'], $r['branch'], $r['school_class'], $r['section'],
            $r['father'], $r['mother'], $r['total_fee'], $r['total_paid'], $r['total_discount'], $r['due'],
        ])->all();

        return $this->streamTable($request, $header, $table, 'fee-due-automatic', 'Fee Due (Automatic) — '.$this->sessionLabel($request));
    }

    /** Month groups for Create Fee Due modal. */
    public function meta(Request $request)
    {
        $session = AcademicSession::fromRequest($request, true);
        $neighbors = AcademicSession::neighbors($session);

        $pack = function (?AcademicSession $s) {
            if (! $s) {
                return null;
            }

            return [
                'id' => $s->id,
                'name' => $s->name,
                'months' => $s->months(),
            ];
        };

        return response()->json([
            'current_session' => $pack($neighbors['current']),
            'previous_session' => $pack($neighbors['previous']),
            'next_session' => $pack($neighbors['next']),
        ]);
    }

    /** Manual dues grouped by student. */
    public function manualIndex(Request $request)
    {
        $grouped = $this->manualRowsGrouped($request);

        return response()->json([
            'rows' => $grouped,
            'summary' => [
                'students' => $grouped->count(),
                'charged' => round($grouped->sum('total'), 2),
                'paid' => round($grouped->sum('paid'), 2),
                'due' => round($grouped->sum('balance'), 2),
            ],
        ]);
    }

    /** Shared row-building for the manual-dues screen and its export. */
    private function manualRowsGrouped(Request $request): \Illuminate\Support\Collection
    {
        $data = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'search' => 'nullable|string|max:100',
        ]);

        $session = AcademicSession::fromRequest($request, true);
        if (! $session) {
            return collect();
        }

        $query = ManualFeeDue::with([
            'feeHead:id,name',
            'academicSession:id,name',
            'student:id,name,admission_no,roll_no,branch_id,school_class_id,section_id,father_id,mother_id',
            'student.branch:id,name',
            'student.schoolClass:id,name',
            'student.section:id,name',
            'student.father:id,name',
            'student.mother:id,name',
        ])->where('academic_session_id', $session->id);

        $query->whereHas('student', function ($q) use ($data) {
            $q->where('status', 'Active');
            if (! empty($data['branch_id'])) {
                $q->forBranch((int) $data['branch_id']);
            }
            if (! empty($data['school_class_id'])) {
                $q->where('school_class_id', $data['school_class_id']);
            }
            if (! empty($data['section_id'])) {
                $q->where('section_id', $data['section_id']);
            }
            if (! empty($data['search'])) {
                $s = $data['search'];
                $q->where(function ($w) use ($s) {
                    $w->where('name', 'like', "%{$s}%")
                        ->orWhere('admission_no', 'like', "%{$s}%");
                });
            }
        });

        $dues = $query->orderBy('month')->get();

        $grouped = $dues->groupBy('student_id')->map(function ($lines) {
            /** @var \Illuminate\Support\Collection<int, ManualFeeDue> $lines */
            $student = $lines->first()->student;
            $charged = round($lines->sum(fn (ManualFeeDue $d) => (float) $d->amount), 2);
            $paid = round($lines->sum(fn (ManualFeeDue $d) => (float) $d->paid_amount), 2);
            $balance = round($lines->sum(fn (ManualFeeDue $d) => $d->balance()), 2);
            $latestDue = $lines->max(fn (ManualFeeDue $d) => optional($d->due_date)->toDateString());
            $feeNames = $lines->pluck('feeHead.name')->unique()->filter()->values()->all();
            $months = $lines->map(fn (ManualFeeDue $d) => $d->month?->format('M'))->unique()->values()->all();

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
                'fee_types' => implode(', ', $feeNames),
                'months_label' => implode('-', $months),
                'session_name' => $lines->first()->academicSession?->name ?? '',
                'latest_due_date' => $latestDue,
                'total' => $charged,
                'paid' => $paid,
                'balance' => $balance,
                'status' => $balance > 0 ? 'Pending' : 'Paid',
                'lines' => $lines->map(fn (ManualFeeDue $d) => [
                    'id' => $d->id,
                    'fee_head_id' => $d->fee_head_id,
                    'fee_head_name' => $d->feeHead?->name,
                    'month' => $d->month?->format('Y-m'),
                    'month_label' => $d->month?->format('M'),
                    'amount' => (float) $d->amount,
                    'paid_amount' => (float) $d->paid_amount,
                    'balance' => $d->balance(),
                    'due_date' => optional($d->due_date)->toDateString(),
                    'remarks' => $d->remarks,
                ])->values(),
            ];
        })->values();

        return $grouped;
    }

    /** Manual dues export — same filters/session-scoping as manualIndex(), format = pdf|xlsx|csv. */
    public function exportManual(Request $request)
    {
        $grouped = $this->manualRowsGrouped($request);
        $header = ['Admission No', 'Student', 'Class', 'Section', 'Fee Types', 'Total', 'Paid', 'Balance', 'Status'];
        $table = $grouped->map(fn (array $r) => [
            $r['admission_no'], $r['name'], $r['school_class'], $r['section'], $r['fee_types'], $r['total'], $r['paid'], $r['balance'], $r['status'],
        ])->values()->all();

        return $this->streamTable($request, $header, $table, 'fee-due-manual', 'Fee Due (Manual) — '.$this->sessionLabel($request));
    }

    /** Printable due receipt for one student's manual dues. */
    public function manualReceipt(Request $request, Student $student)
    {
        $session = AcademicSession::fromRequest($request, true);
        if (! $session) {
            throw ValidationException::withMessages(['session' => 'No academic session selected.']);
        }

        $student->load([
            'branch:id,name',
            'schoolClass:id,name',
            'section:id,name',
            'father:id,name',
            'mother:id,name',
        ]);

        $dues = ManualFeeDue::with('feeHead:id,name')
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->orderBy('month')
            ->orderBy('id')
            ->get();

        if ($dues->isEmpty()) {
            throw ValidationException::withMessages(['student_id' => 'No manual fee dues found for this student.']);
        }

        $lines = $dues->map(fn (ManualFeeDue $d, int $i) => [
            'sno' => $i + 1,
            'fee_particulars' => strtoupper((string) ($d->feeHead?->name ?? 'Fee')),
            'duration' => $d->month?->format('F Y'),
            'month_key' => $d->month?->format('Y-m'),
            'amount' => (float) $d->amount,
            'paid_amount' => (float) $d->paid_amount,
            'balance' => $d->balance(),
        ])->values();

        $amountDue = round($lines->sum('amount'), 2);
        $totalPaid = round($lines->sum('paid_amount'), 2);
        $remaining = round($lines->sum('balance'), 2);

        $dueMonths = $dues->map(fn (ManualFeeDue $d) => $d->month?->format('F Y'))->unique()->values()->all();
        $paidMonths = $dues
            ->filter(fn (ManualFeeDue $d) => (float) $d->paid_amount > 0)
            ->map(fn (ManualFeeDue $d) => $d->month?->format('F Y'))
            ->unique()
            ->values()
            ->all();

        // The nearest deadline still owed — each ManualFeeDue line carries its own real due_date,
        // so prefer the earliest one still unpaid; only fall back to the earliest overall if every
        // line happens to already be settled (there's nothing left owing, but a date is still useful).
        $earliestDueDate = fn ($rows) => $rows->pluck('due_date')->filter()->sortBy(fn ($d) => $d->timestamp)->first();
        $dueDate = $earliestDueDate($dues->filter(fn (ManualFeeDue $d) => $d->balance() > 0.0001))
            ?? $earliestDueDate($dues);

        $payments = FeePayment::where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (FeePayment $p) => [
                'date' => optional($p->payment_date)->format('d M Y') ?? substr((string) $p->payment_date, 0, 10),
                'payment_mode' => strtoupper((string) $p->payment_mode),
                'amount' => (float) $p->amount - (float) $p->refunded_amount,
                'receipt_no' => $p->receipt_no,
            ])
            ->values();

        $school = SchoolSetting::current();
        $now = now();
        $receiptNo = sprintf(
            'DUE-%s-%s',
            $student->admission_no ?: $student->id,
            $now->format('Ymd')
        );

        return response()->json([
            'type' => 'manual',
            'school' => [
                'name' => $school->school_name ?: 'Global Access School',
                'address' => $school->formatted_address ?: $school->address,
                'phone' => $school->phone,
                'email' => $school->email,
                'logo_path' => $school->logo_url,
                'udise_no' => null,
            ],
            'receipt_no' => $receiptNo,
            'printed_at' => $now->format('j M Y, h:i a'),
            'session' => ['id' => $session->id, 'name' => $session->name],
            'months_bar' => implode(', ', $dueMonths),
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'admission_no' => $student->admission_no,
                'roll_no' => $student->roll_no,
                'father' => $student->father?->name,
                'mother' => $student->mother?->name,
                'branch' => $student->branch?->name,
                'class' => $student->schoolClass?->name,
                'section' => $student->section?->name,
            ],
            'lines' => $lines,
            'amount_due' => $amountDue,
            'total_paid' => $totalPaid,
            'remaining_due' => $remaining,
            'due_date' => $dueDate?->format('d M Y'),
            'due_months' => $dueMonths,
            'paid_months' => $paidMonths,
            'payment_method' => $payments->isNotEmpty() ? $payments->first()['payment_mode'] : 'CASH',
            'description' => 'Outstanding dues as of '.$now->format('j M Y, h:i a'),
            'payment_history' => $payments,
        ]);
    }

    public function downloadManualPdf(Request $request, Student $student, DocumentRenderService $renderer, DocumentDataBuilder $dataBuilder): StreamedResponse
    {
        $json = $this->manualReceipt($request, $student)->getData(true);

        return $renderer->streamPdf('fee_due_receipt', $dataBuilder->feeDueReceipt($json), 'fee-due-'.$student->admission_no.'.pdf');
    }

    /** Printable due receipt from automatic fee structure calculation. */
    public function automaticReceipt(Request $request, Student $student)
    {
        $data = $request->validate([
            'scope' => 'nullable|in:till_current,current_month,till_next_month',
        ]);
        $scope = $data['scope'] ?? 'till_current';

        $session = AcademicSession::fromRequest($request, true);
        if (! $session) {
            throw ValidationException::withMessages(['session' => 'No academic session selected.']);
        }

        $student->load([
            'branch:id,name',
            'schoolClass:id,name',
            'section:id,name',
            'father:id,name',
            'mother:id,name',
        ]);

        $balance = app(FeeBalanceService::class);
        $calc = FeeCalculator::forStudent($student, $session);
        $monthKeys = $balance->filterMonthsFromFeeStart($student, $this->monthKeysInScope($session, $scope));
        $paidInfo = $balance->paidByHead($student, $session);
        $firstBillableMonth = $balance->firstBillableMonth($student, $session);
        [$transportFare, $transportHeadId, $transportFeeStart] = $balance->transportFareAndHead($student);

        $paidByMonth = $paidInfo['by_month'];
        $discountByMonth = $paidInfo['discount_by_month'];
        $paidTotals = $paidInfo['totals'];
        $paidDiscounts = $paidInfo['discounts'];

        $lines = [];
        $sno = 1;
        foreach ($calc['breakdown'] as $item) {
            $freq = strtolower(str_replace(' ', '_', (string) ($item['frequency'] ?? 'one_time')));
            $base = (float) ($item['amount'] ?? 0);
            $name = strtoupper((string) ($item['fee_head_name'] ?? 'Fee'));
            $headKey = (string) (int) ($item['fee_head_id'] ?? 0);
            if ($base <= 0 || $headKey === '0') {
                continue;
            }

            if ($freq === 'monthly') {
                $keys = $scope === 'current_month'
                    ? (count($monthKeys) ? [end($monthKeys)] : [])
                    : $monthKeys;
                foreach ($keys as $key) {
                    $mPaid = (float) ($paidByMonth[$headKey][$key] ?? 0);
                    $mDisc = (float) ($discountByMonth[$headKey][$key] ?? 0);
                    $covered = min($base, $mPaid + $mDisc);
                    $lines[] = [
                        'sno' => $sno++,
                        'fee_particulars' => $name,
                        'duration' => Carbon::createFromFormat('Y-m', $key)->format('F Y'),
                        'month_key' => $key,
                        'amount' => $base,
                        'paid_amount' => round($covered, 2),
                        'balance' => round(max(0, $base - $covered), 2),
                    ];
                }
            } elseif ($freq === 'quarterly') {
                foreach ($monthKeys as $key) {
                    $monthNum = (int) substr($key, 5, 2);
                    if (! in_array($monthNum, [4, 7, 10, 1], true)) {
                        continue;
                    }
                    $mPaid = (float) ($paidByMonth[$headKey][$key] ?? 0);
                    $mDisc = (float) ($discountByMonth[$headKey][$key] ?? 0);
                    $covered = min($base, $mPaid + $mDisc);
                    $lines[] = [
                        'sno' => $sno++,
                        'fee_particulars' => $name.' (QUARTERLY)',
                        'duration' => Carbon::createFromFormat('Y-m', $key)->format('F Y'),
                        'month_key' => $key,
                        'amount' => $base,
                        'paid_amount' => round($covered, 2),
                        'balance' => round(max(0, $base - $covered), 2),
                    ];
                }
            } else {
                if ($monthKeys === [] || ! $firstBillableMonth || ! in_array($firstBillableMonth, $monthKeys, true)) {
                    continue;
                }
                $mPaid = (float) ($paidTotals[$headKey] ?? 0);
                $mDisc = (float) ($paidDiscounts[$headKey] ?? 0);
                $covered = min($base, $mPaid + $mDisc);
                $lines[] = [
                    'sno' => $sno++,
                    'fee_particulars' => $name,
                    'duration' => Carbon::createFromFormat('Y-m', $firstBillableMonth)->format('F Y'),
                    'month_key' => $firstBillableMonth,
                    'amount' => $base,
                    'paid_amount' => round($covered, 2),
                    'balance' => round(max(0, $base - $covered), 2),
                ];
            }
        }

        if ($transportFare > 0 && $transportHeadId) {
            $headKey = (string) $transportHeadId;
            $keys = $scope === 'current_month'
                ? (count($monthKeys) ? [end($monthKeys)] : [])
                : $monthKeys;
            foreach ($keys as $key) {
                if (! $balance->transportAppliesInMonth($student, $key, $transportFeeStart)) {
                    continue;
                }
                $mPaid = (float) ($paidByMonth[$headKey][$key] ?? 0);
                $mDisc = (float) ($discountByMonth[$headKey][$key] ?? 0);
                $covered = min($transportFare, $mPaid + $mDisc);
                $lines[] = [
                    'sno' => $sno++,
                    'fee_particulars' => 'TRANSPORT',
                    'duration' => Carbon::createFromFormat('Y-m', $key)->format('F Y'),
                    'month_key' => $key,
                    'amount' => $transportFare,
                    'paid_amount' => round($covered, 2),
                    'balance' => round(max(0, $transportFare - $covered), 2),
                ];
            }
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['student_id' => 'No automatic fee dues found for this student.']);
        }

        $amountDue = round(array_sum(array_column($lines, 'amount')), 2);
        $totalPaid = round(array_sum(array_column($lines, 'paid_amount')), 2);
        $discount = 0.0;
        $remaining = round(array_sum(array_column($lines, 'balance')), 2);

        $dueMonths = collect($monthKeys)->map(fn ($k) => Carbon::createFromFormat('Y-m', $k)->format('F Y'))->values()->all();
        $paidMonths = collect($lines)
            ->filter(fn ($l) => ($l['paid_amount'] ?? 0) > 0 && ! empty($l['month_key']))
            ->map(fn ($l) => Carbon::createFromFormat('Y-m', $l['month_key'])->format('F Y'))
            ->unique()
            ->values()
            ->all();

        // Automatic dues have no per-line due date of their own (unlike ManualFeeDue) — the
        // nearest real deadline this receipt can state is the end of the last month it actually
        // covers, i.e. "these charges run through <month>, please pay by then".
        $dueDate = $monthKeys !== []
            ? Carbon::createFromFormat('Y-m', end($monthKeys))->endOfMonth()->format('d M Y')
            : null;

        $payments = FeePayment::where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (FeePayment $p) => [
                'date' => optional($p->payment_date)->format('d M Y') ?? substr((string) $p->payment_date, 0, 10),
                'payment_mode' => strtoupper((string) $p->payment_mode),
                'amount' => (float) $p->amount - (float) $p->refunded_amount,
                'receipt_no' => $p->receipt_no,
            ])
            ->values();

        $school = SchoolSetting::current();
        $now = now();
        $receiptNo = sprintf(
            'DUE-%s-%s',
            $student->admission_no ?: $student->id,
            $now->format('Ymd')
        );

        return response()->json([
            'type' => 'automatic',
            'scope' => $scope,
            'school' => [
                'name' => $school->school_name ?: 'Global Access School',
                'address' => $school->formatted_address ?: $school->address,
                'phone' => $school->phone,
                'email' => $school->email,
                'logo_path' => $school->logo_url,
                'udise_no' => null,
            ],
            'receipt_no' => $receiptNo,
            'printed_at' => $now->format('j M Y, h:i a'),
            'session' => ['id' => $session->id, 'name' => $session->name],
            'months_bar' => implode(', ', $dueMonths) ?: $this->scopeLabel($session, $scope),
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'admission_no' => $student->admission_no,
                'roll_no' => $student->roll_no,
                'father' => $student->father?->name,
                'mother' => $student->mother?->name,
                'branch' => $student->branch?->name,
                'class' => $student->schoolClass?->name,
                'section' => $student->section?->name,
            ],
            'lines' => $lines,
            'amount_due' => $amountDue,
            'total_paid' => $totalPaid,
            'remaining_due' => $remaining,
            'due_date' => $dueDate,
            'due_months' => $dueMonths,
            'paid_months' => $paidMonths,
            'payment_method' => $payments->isNotEmpty() ? $payments->first()['payment_mode'] : 'CASH',
            'description' => 'Outstanding dues as of '.$now->format('j M Y, h:i a').' ('.$this->scopeLabel($session, $scope).')',
            'payment_history' => $payments,
        ]);
    }

    public function downloadAutomaticPdf(Request $request, Student $student, DocumentRenderService $renderer, DocumentDataBuilder $dataBuilder): StreamedResponse
    {
        $json = $this->automaticReceipt($request, $student)->getData(true);

        return $renderer->streamPdf('fee_due_receipt', $dataBuilder->feeDueReceipt($json), 'fee-due-'.$student->admission_no.'.pdf');
    }

    /** Month-wise + category-wise automatic due detail for the Details modal. */
    public function automaticDetail(Request $request, Student $student)
    {
        $data = $request->validate([
            'scope' => 'nullable|in:till_current,current_month,till_next_month',
        ]);
        $scope = $data['scope'] ?? 'till_current';

        $session = AcademicSession::fromRequest($request, true);
        if (! $session) {
            throw ValidationException::withMessages(['session' => 'No academic session selected.']);
        }

        $student->load([
            'branch:id,name',
            'schoolClass:id,name',
            'section:id,name',
        ]);

        $calc = FeeCalculator::forStudent($student, $session);
        $balance = app(FeeBalanceService::class);
        $monthKeys = $balance->filterMonthsFromFeeStart($student, $this->monthKeysInScope($session, $scope));
        $paidInfo = $balance->paidByHead($student, $session);
        $monthRows = $balance->monthRows($student, $session, $monthKeys, $calc, $paidInfo);

        $totalCharge = round(array_sum(array_column($monthRows, 'charge')), 2);
        $totalConcession = round(array_sum(array_column($monthRows, 'concession')), 2);
        $totalPaid = round(array_sum(array_column($monthRows, 'paid')), 2);
        $remaining = round(array_sum(array_column($monthRows, 'due')), 2);

        $categories = $balance->categoryRows($student, $session, $monthKeys, $calc, $paidInfo);

        $payments = FeePayment::where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (FeePayment $p) {
                $items = is_array($p->items) ? $p->items : [];
                $labels = collect($items)->pluck('fee_head_name')->filter()->unique()->implode(', ');
                $months = collect($items)->pluck('month')->filter()->unique()->values();
                $monthLabel = $months->map(function ($m) {
                    try {
                        return Carbon::createFromFormat('Y-m', (string) $m)->format('M Y');
                    } catch (\Throwable) {
                        return (string) $m;
                    }
                })->implode(', ');

                return [
                    'date' => optional($p->payment_date)->format('d M Y') ?? substr((string) $p->payment_date, 0, 10),
                    'source' => 'Fee Receipt',
                    'label' => $labels !== '' ? $labels : ($p->remarks ?: 'Payment'),
                    'months' => $monthLabel !== '' ? $monthLabel : '—',
                    'mode' => $p->payment_mode,
                    'amount' => (float) $p->amount - (float) $p->refunded_amount,
                    'receipt_no' => $p->receipt_no,
                ];
            })
            ->values();

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'admission_no' => $student->admission_no,
                'school_class' => $student->schoolClass?->name,
                'section' => $student->section?->name,
                'branch' => $student->branch?->name,
            ],
            'session' => ['id' => $session->id, 'name' => $session->name],
            'scope' => $scope,
            'scope_label' => $this->scopeLabel($session, $scope),
            'banner' => 'Showing totals through '.($monthKeys
                ? Carbon::createFromFormat('Y-m', end($monthKeys))->format('M Y')
                : '—').'.',
            'summary' => [
                'total_charge' => $totalCharge,
                'paid' => $totalPaid,
                'concession' => $totalConcession,
                'remaining_due' => $remaining,
            ],
            'months' => $monthRows,
            'categories' => $categories,
            'payments' => $payments,
        ]);
    }

    /** Create manual fee due lines for one or more students. */
    public function storeManual(Request $request)
    {
        $data = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'required|exists:students,id',
            'due_date' => 'required|date',
            'remarks' => 'nullable|string|max:255',
            'apply_all_months' => 'nullable|boolean',
            'lines' => 'required|array|min:1',
            'lines.*.fee_head_id' => 'nullable|exists:fee_heads,id',
            'lines.*.fee_head_name' => 'nullable|string|max:255',
            'lines.*.month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'lines.*.academic_session_id' => 'required|exists:academic_sessions,id',
            'lines.*.amount' => 'required|numeric|min:0.01',
        ]);

        $userId = Auth::guard('erp')->id();
        $created = 0;

        DB::transaction(function () use ($data, $userId, &$created) {
            foreach ($data['student_ids'] as $studentId) {
                foreach ($data['lines'] as $line) {
                    $headId = $line['fee_head_id'] ?? null;
                    if (! $headId) {
                        $name = trim((string) ($line['fee_head_name'] ?? ''));
                        if ($name === '') {
                            continue;
                        }
                        $headId = FeeHead::firstOrCreate(
                            ['name' => $name],
                            ['description' => 'Created from manual fee due']
                        )->id;
                    }

                    $monthDate = Carbon::createFromFormat('Y-m', $line['month'])->startOfMonth()->toDateString();
                    $due = ManualFeeDue::updateOrCreate(
                        [
                            'student_id' => $studentId,
                            'academic_session_id' => $line['academic_session_id'],
                            'fee_head_id' => $headId,
                            'month' => $monthDate,
                        ],
                        [
                            'amount' => round((float) $line['amount'], 2),
                            'due_date' => $data['due_date'],
                            'remarks' => $data['remarks'] ?? null,
                            'created_by_id' => $userId,
                        ]
                    );
                    if ($due->wasRecentlyCreated || $due->wasChanged()) {
                        $created++;
                    }
                }
            }
        });

        return response()->json(['message' => "Saved {$created} fee due line(s).", 'created' => $created], 201);
    }

    public function updateManual(Request $request, ManualFeeDue $manualFeeDue)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'due_date' => 'nullable|date',
            'remarks' => 'nullable|string|max:255',
        ]);

        if ((float) $data['amount'] < (float) $manualFeeDue->paid_amount) {
            throw ValidationException::withMessages([
                'amount' => 'Amount cannot be less than already paid (₹'.$manualFeeDue->paid_amount.').',
            ]);
        }

        $manualFeeDue->update($data);

        return response()->json($manualFeeDue->fresh()->load('feeHead:id,name'));
    }

    public function destroyManual(ManualFeeDue $manualFeeDue)
    {
        if ((float) $manualFeeDue->paid_amount > 0) {
            throw ValidationException::withMessages([
                'id' => 'Cannot delete a due line that already has payments. Adjust amount instead.',
            ]);
        }
        $manualFeeDue->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    /** Collect payment against open manual dues for a student. */
    public function collectManual(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'nullable|date',
            'payment_mode' => ['required', Rule::in(['Cash', 'UPI', 'Card', 'Bank Transfer', 'Cheque', 'cash', 'upi'])],
            'remarks' => 'nullable|string|max:255',
            'due_ids' => 'nullable|array',
            'due_ids.*' => 'integer|exists:manual_fee_dues,id',
        ]);

        $mode = ucwords(strtolower($data['payment_mode']));
        if ($mode === 'Cash') {
            $mode = 'Cash';
        } elseif (strcasecmp($data['payment_mode'], 'upi') === 0) {
            $mode = 'UPI';
        }

        $session = AcademicSession::fromRequest($request, true);
        if (! $session) {
            throw ValidationException::withMessages(['student_id' => 'No academic session selected.']);
        }

        $duesQuery = ManualFeeDue::where('student_id', $data['student_id'])
            ->where('academic_session_id', $session->id)
            ->orderBy('month')
            ->orderBy('id');

        if (! empty($data['due_ids'])) {
            $duesQuery->whereIn('id', $data['due_ids']);
        }

        $dues = $duesQuery->get()->filter(fn (ManualFeeDue $d) => $d->balance() > 0)->values();
        if ($dues->isEmpty()) {
            throw ValidationException::withMessages(['amount' => 'No outstanding manual dues for this student.']);
        }

        $remaining = round((float) $data['amount'], 2);
        $allocations = [];

        foreach ($dues as $due) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($due->balance(), $remaining);
            $allocations[] = ['due' => $due, 'amount' => $take];
            $remaining = round($remaining - $take, 2);
        }

        if ($allocations === []) {
            throw ValidationException::withMessages(['amount' => 'Nothing to allocate.']);
        }

        $payment = DB::transaction(function () use ($allocations, $data, $session, $mode) {
            $items = [];
            foreach ($allocations as $row) {
                /** @var ManualFeeDue $due */
                $due = $row['due'];
                $due->paid_amount = round((float) $due->paid_amount + $row['amount'], 2);
                $due->save();

                $items[] = [
                    'fee_head_id' => $due->fee_head_id,
                    'fee_head_name' => $due->feeHead?->name ?? FeeHead::find($due->fee_head_id)?->name ?? 'Fee',
                    'amount' => $row['amount'],
                    'month' => $due->month?->format('Y-m'),
                ];
            }

            $total = collect($items)->sum('amount');

            return FeePayment::create([
                'receipt_no' => $this->nextReceiptNo(),
                'student_id' => $data['student_id'],
                'academic_session_id' => $session->id,
                'items' => $items,
                'amount' => $total,
                'discount_amount' => 0,
                'fine_amount' => 0,
                'payment_mode' => in_array($mode, ['Cash', 'UPI', 'Card', 'Bank Transfer', 'Cheque'], true) ? $mode : 'Cash',
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'remarks' => $data['remarks'] ?? 'Manual fee due collection',
                'collected_by_id' => Auth::guard('erp')->id(),
                'status' => 'Paid',
            ]);
        });

        return response()->json($payment->load(['student:id,name,admission_no']), 201);
    }

    /** @param  list<array{fee_head_id:mixed,fee_head_name:string,amount:float,frequency:string}>  $breakdown */
    private function chargedFromBreakdown(array $breakdown, int $months, string $scope): float
    {
        $months = max(0, $months);
        $total = 0.0;
        foreach ($breakdown as $item) {
            $amount = (float) ($item['amount'] ?? 0);
            $freq = strtolower((string) ($item['frequency'] ?? 'one_time'));
            $total += match ($freq) {
                'monthly' => $amount * ($scope === 'current_month' ? min(1, $months) : $months),
                'quarterly' => $amount * max(1, (int) ceil($months / 3)),
                'annual', 'one_time' => $months > 0 ? $amount : 0,
                default => $months > 0 ? $amount : 0,
            };
        }

        return round($total, 2);
    }

    private function monthsInScope(AcademicSession $session, string $scope): int
    {
        return count($this->monthKeysInScope($session, $scope));
    }

    /** @return list<string> Y-m keys */
    private function monthKeysInScope(AcademicSession $session, string $scope): array
    {
        if (! $session->start_date || ! $session->end_date) {
            return [];
        }

        $start = $session->start_date->copy()->startOfMonth();
        $end = $session->end_date->copy()->startOfMonth();
        $now = now()->startOfMonth();

        if ($scope === 'current_month') {
            if ($now < $start || $now > $end) {
                return [];
            }

            return [$now->format('Y-m')];
        }

        if ($now < $start) {
            return [];
        }
        $till = $now->gt($end) ? $end->copy() : $now->copy();
        // "Till next month" pulls in next month's charge alongside every already-elapsed month —
        // capped at the session end so it never reaches past the session's own last month.
        if ($scope === 'till_next_month') {
            $nextMonth = $till->copy()->addMonth();
            $till = $nextMonth->gt($end) ? $end->copy() : $nextMonth;
        }
        $keys = [];
        $cursor = $start->copy();
        while ($cursor <= $till) {
            $keys[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $keys;
    }

    private function scopeLabel(AcademicSession $session, string $scope): string
    {
        if (! $session->start_date || ! $session->end_date) {
            return '';
        }
        if ($scope === 'current_month') {
            return 'Only '.now()->format('M Y');
        }
        $till = now()->startOfMonth();
        $end = $session->end_date->copy()->startOfMonth();
        if ($till->gt($end)) {
            $till = $end;
        }
        if ($scope === 'till_next_month') {
            $nextMonth = $till->copy()->addMonth();
            $till = $nextMonth->gt($end) ? $end : $nextMonth;
        }

        return 'Till '.$till->format('M Y');
    }

    private function nextReceiptNo(): string
    {
        $year = now()->year;
        $prefix = "RCPT-{$year}-";
        $last = FeePayment::where('receipt_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('receipt_no');
        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /** Header session picker label, same convention as ExportController. */
    private function sessionLabel(Request $request): string
    {
        $allSessions = AcademicSession::requestWantsAll($request);
        $session = $allSessions ? null : AcademicSession::fromRequest($request, true);

        return $allSessions ? 'All Sessions' : ($session->name ?? 'All Sessions');
    }

    /**
     * Stream a header+rows table as pdf | xlsx | csv (?format=, defaults to xlsx).
     *
     * @param  array<int, string>  $header
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function streamTable(Request $request, array $header, array $rows, string $basename, string $title): StreamedResponse
    {
        $format = strtolower((string) $request->query('format', 'xlsx'));
        if (! in_array($format, ['xlsx', 'csv', 'pdf'], true)) {
            $format = 'xlsx';
        }
        $filename = $basename.'-'.now()->format('Ymd-His').'.'.$format;

        if ($format === 'csv') {
            return $this->streamCsv($header, $rows, $filename);
        }
        if ($format === 'pdf') {
            return $this->streamPdf($header, $rows, $filename, $title);
        }

        return $this->streamXlsx($header, $rows, $filename, $title);
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function streamCsv(array $header, array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function streamXlsx(array $header, array $rows, string $filename, string $title): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Fee Due');
        $sheet->fromArray($header, null, 'A1');
        if ($rows) {
            $sheet->fromArray($rows, null, 'A2');
        }
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
        $colCount = count($header);
        for ($col = 1; $col <= $colCount; $col++) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function streamPdf(array $header, array $rows, string $filename, string $title): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows, $title) {
            $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $thead = '<tr>'.collect($header)->map(fn ($h) => '<th>'.$escape($h).'</th>')->implode('').'</tr>';
            $tbody = '';
            foreach ($rows as $row) {
                $tbody .= '<tr>';
                foreach ($row as $cell) {
                    $tbody .= '<td>'.$escape($cell).'</td>';
                }
                $tbody .= '</tr>';
            }

            $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
                @page { margin: 10mm; }
                body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111; }
                h1 { font-size: 13pt; margin: 0 0 8pt; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 0.5pt solid #999; padding: 3pt 4pt; vertical-align: top; }
                th { background: #eee; font-weight: bold; }
            </style></head><body>
                <h1>'.$escape($title).'</h1>
                <table><thead>'.$thead.'</thead><tbody>'.$tbody.'</tbody></table>
            </body></html>';

            $options = new Options();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isHtml5ParserEnabled', true);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('a4', 'landscape');
            $dompdf->render();
            echo $dompdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
