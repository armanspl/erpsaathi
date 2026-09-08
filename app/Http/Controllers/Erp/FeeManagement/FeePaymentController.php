<?php

namespace App\Http\Controllers\Erp\FeeManagement;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\BankTransaction;
use App\Models\ErpFeeSetting;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\FeePaymentAudit;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentTransport;
use App\Services\DocumentDataBuilder;
use App\Services\DocumentRenderService;
use App\Services\FeeBalanceService;
use App\Services\FeeCalculator;
use App\Services\FeeMonthAllocator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeePaymentController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'nullable|exists:students,id',
            'search' => 'nullable|string|max:100',
            'branch_id' => 'nullable|exists:branches,id',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'period' => 'nullable|in:day,month,year',
            'date' => 'nullable|date',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'order_by' => 'nullable|in:submitted,payment_date,amount,receipt_no',
            'order' => 'nullable|in:asc,desc',
            'with_due' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:2000',
        ]);

        [$from, $to] = $this->resolveDateRange($data);

        $query = FeePayment::with([
            'student:id,name,admission_no,branch_id,school_class_id,section_id',
            'student.branch:id,name',
            'student.schoolClass:id,name',
            'student.section:id,name',
            'collectedBy:id,name',
            'editedBy:id,name',
            'rolledBackBy:id,name',
        ]);

        if (! empty($data['student_id'])) {
            $query->where('student_id', $data['student_id']);
        }

        if (! AcademicSession::requestWantsAll($request)) {
            $session = AcademicSession::fromRequest($request);
            if ($session) {
                $query->where('academic_session_id', $session->id);
            }
        }

        if ($from) {
            $query->whereDate('payment_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('payment_date', '<=', $to);
        }

        if (! empty($data['min_amount'])) {
            $query->where('amount', '>=', $data['min_amount']);
        }
        if (isset($data['max_amount']) && $data['max_amount'] !== null && $data['max_amount'] !== '') {
            $query->where('amount', '<=', $data['max_amount']);
        }

        if (! empty($data['branch_id']) || ! empty($data['school_class_id']) || ! empty($data['section_id']) || ! empty($data['search'])) {
            $query->whereHas('student', function ($q) use ($data) {
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
                    $term = '%' . trim($data['search']) . '%';
                    $q->where(function ($q) use ($term) {
                        $q->where('name', 'like', $term)->orWhere('admission_no', 'like', $term);
                    });
                }
            });
        }

        $orderBy = match ($data['order_by'] ?? 'payment_date') {
            'submitted' => 'id',
            'amount' => 'amount',
            'receipt_no' => 'receipt_no',
            default => 'payment_date',
        };
        $order = ($data['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($orderBy, $order)->orderByDesc('id');

        $limit = (int) ($data['limit'] ?? 400);
        $payments = $query->limit($limit)->get();
        $dues = [];
        if ($request->boolean('with_due')) {
            $session = AcademicSession::fromRequest($request, true);
            $uniqueStudents = $payments->pluck('student')->filter()->unique('id')->values();
            FeeCalculator::flushRuntimeCache();
            FeeCalculator::warmForStudents($uniqueStudents, $session ? collect([$session]) : collect());
            foreach ($uniqueStudents as $student) {
                $dues[$student->id] = FeeCalculator::forStudent($student, $session)['due'] ?? 0;
            }
        }

        return response()->json(
            $payments->map(fn (FeePayment $p) => $this->presentPayment($p, $dues[$p->student_id] ?? null))
        );
    }

    public function report(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'nullable|exists:students,id',
            'search' => 'nullable|string|max:100',
            'branch_id' => 'nullable|exists:branches,id',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'period' => 'nullable|in:day,month,year',
            'date' => 'nullable|date',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'order_by' => 'nullable|in:submitted,payment_date,amount,receipt_no',
            'order' => 'nullable|in:asc,desc',
        ]);

        $request->merge(['with_due' => false]);
        $payments = collect($this->index($request)->getData(true));

        $groupKey = ! empty($data['section_id'])
            ? 'section'
            : (! empty($data['school_class_id']) ? 'class' : (! empty($data['branch_id']) ? 'branch' : 'branch_default'));

        $groups = $payments->groupBy(function (array $p) use ($groupKey) {
            return match ($groupKey) {
                'section' => ($p['student']['branch']['name'] ?? '—') . ' / ' . ($p['student']['school_class']['name'] ?? '—') . ' / ' . ($p['student']['section']['name'] ?? '—'),
                'class' => ($p['student']['branch']['name'] ?? '—') . ' / ' . ($p['student']['school_class']['name'] ?? '—'),
                'branch' => $p['student']['branch']['name'] ?? 'All branches',
                default => $p['student']['branch']['name'] ?? 'Unassigned',
            };
        })->map(function ($rows, $group) {
            $paid = (float) $rows->sum(fn ($r) => (float) $r['amount'] - (float) $r['refunded_amount']);
            $discount = (float) $rows->sum('discount_amount');
            $extra = (float) $rows->sum('fine_amount');
            $rollback = (float) $rows->sum('refunded_amount');

            return [
                'group' => $group,
                'receipts' => $rows->count(),
                'payable' => round($paid + $discount - $extra, 2),
                'discount' => round($discount, 2),
                'extra' => round($extra, 2),
                'paid' => round($paid, 2),
                'due' => 0,
                'rollback' => round($rollback, 2),
            ];
        })->values();

        $totalCollected = (float) $payments->sum(fn ($r) => (float) $r['amount'] - (float) $r['refunded_amount']);

        return response()->json([
            'total_collected' => round($totalCollected, 2),
            'groups' => $groups,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $payments = collect($this->index($request)->getData(true));
        $filename = 'fee-receipts-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($payments) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Receipt', 'Payment Date', 'Student', 'Admission No', 'Months', 'Fee Types', 'Paid', 'Refunded', 'Status', 'Mode']);
            foreach ($payments as $p) {
                $types = collect($p['items'] ?? [])->pluck('fee_head_name')->implode(', ');
                fputcsv($out, [
                    $p['receipt_no'] ?? '',
                    isset($p['payment_date']) ? substr((string) $p['payment_date'], 0, 10) : '',
                    $p['student']['name'] ?? '',
                    $p['student']['admission_no'] ?? '',
                    $p['months'] ?? '',
                    $types,
                    $p['amount'] ?? 0,
                    $p['refunded_amount'] ?? 0,
                    $p['status'] ?? '',
                    $p['payment_mode'] ?? '',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Due breakdown for one student — powers the Pay Fee / Fee Receipt screens. */
    public function due(Request $request, Student $student)
    {
        $session = AcademicSession::fromRequest($request, true);
        $calc = FeeCalculator::forStudent($student, $session);
        $balance = app(FeeBalanceService::class);
        $paidByHead = $balance->paidByHead($student, $session);
        $paidMonths = $balance->paidMonths(
            $student,
            $session,
            $calc['breakdown'] ?? [],
            $paidByHead
        );

        $transportHead = FeeHead::query()->firstOrCreate(
            ['name' => 'Transport'],
            ['description' => 'School transport fee']
        );

        return response()->json([
            ...$calc,
            'paid_months' => $paidMonths,
            'paid_by_head' => $paidByHead['totals'],
            'paid_by_head_discount' => $paidByHead['discounts'],
            'paid_by_head_month' => $paidByHead['by_month'],
            'paid_by_head_month_discount' => $paidByHead['discount_by_month'],
            'transport_head_id' => $transportHead->id,
            'fee_start_month' => $balance->feeStartKey($student),
            'transport_fee_start_month' => $balance->transportFareAndHead($student)[2] ?? null,
        ]);
    }

    private function paidByHeadForStudent(Student $student, ?AcademicSession $session = null): array
    {
        return app(FeeBalanceService::class)->paidByHead($student, $session);
    }

    private function paidMonthsForStudent(
        Student $student,
        ?AcademicSession $session = null,
        ?array $breakdown = null,
        ?array $paidInfo = null
    ): array {
        return app(FeeBalanceService::class)->paidMonths($student, $session, $breakdown, $paidInfo);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'items' => 'nullable|array',
            'items.*.fee_head_id' => 'required_with:items|exists:fee_heads,id',
            'items.*.amount' => 'required_with:items|numeric|min:0.01',
            'items.*.charge' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.months' => 'nullable|array',
            'items.*.months.*' => 'string|max:7',
            'months' => 'nullable|array',
            'months.*' => 'string|max:7',
            'fine_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'payment_mode' => ['required', Rule::in(['Cash', 'UPI', 'Card', 'Bank Transfer', 'Cheque', 'cash', 'upi', 'card', 'bank transfer', 'cheque'])],
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'payment_date' => 'nullable|date',
            'remarks' => 'nullable|string|max:255',
            'reference_no' => 'nullable|string|max:100',
            'transport_fee' => 'nullable|numeric|min:0',
            'transport_charge' => 'nullable|numeric|min:0',
            'transport_route_id' => 'nullable|integer',
            'transport_stop_id' => 'nullable|integer',
        ]);

        if (empty($data['items']) && (float) ($data['transport_fee'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['items' => 'Add at least one fee item or a transport fee.']);
        }

        $session = AcademicSession::fromRequest($request, true);
        if (! $session) {
            throw ValidationException::withMessages(['student_id' => 'No academic session is selected — pick one in the header or set a current session in Settings.']);
        }

        // Normalize payment mode casing to stored enum values.
        $modeMap = [
            'cash' => 'Cash',
            'upi' => 'UPI',
            'card' => 'Card',
            'bank transfer' => 'Bank Transfer',
            'cheque' => 'Cheque',
        ];
        $paymentMode = $modeMap[strtolower($data['payment_mode'])] ?? $data['payment_mode'];

        $feeHeadNames = FeeHead::whereIn('id', collect($data['items'] ?? [])->pluck('fee_head_id'))->pluck('name', 'id');
        $globalMonths = array_values(array_unique($data['months'] ?? []));

        $student = Student::findOrFail($data['student_id']);

        // Unit charges so multi-month lines can be split FIFO at save time.
        $unitByHead = [];
        $freqByHead = [];
        $calc = FeeCalculator::forStudent($student, $session);
        foreach ($calc['breakdown'] ?? [] as $row) {
            $hid = (int) ($row['fee_head_id'] ?? 0);
            if (! $hid) {
                continue;
            }
            $unitByHead[$hid] = (float) ($row['amount'] ?? 0);
            $freqByHead[$hid] = strtolower(str_replace(' ', '_', (string) ($row['frequency'] ?? 'monthly')));
        }

        $transportAssignment = StudentTransport::query()
            ->where('student_id', $student->id)
            ->where('status', 'Active')
            ->with('routeStop:id,fare')
            ->first();
        $transportFare = (float) ($transportAssignment?->routeStop?->fare ?? 0);
        $transportFeeStart = $transportAssignment?->feeStartMonthKey();
        $transportHead = null;
        if ($transportFare > 0) {
            $transportHead = FeeHead::query()->firstOrCreate(
                ['name' => 'Transport'],
                ['description' => 'School transport fee']
            );
            $unitByHead[$transportHead->id] = $transportFare;
            $freqByHead[$transportHead->id] = 'monthly';
        }

        $rawItems = collect($data['items'] ?? [])->map(function ($item) use ($feeHeadNames, $globalMonths) {
            $months = array_values(array_unique($item['months'] ?? $globalMonths));
            $amount = (float) $item['amount'];
            $charge = isset($item['charge']) ? (float) $item['charge'] : $amount;

            return array_filter([
                'fee_head_id' => (int) $item['fee_head_id'],
                'fee_head_name' => $feeHeadNames[$item['fee_head_id']] ?? 'Fee',
                'amount' => $amount,
                'charge' => max($charge, $amount),
                'discount' => isset($item['discount']) ? (float) $item['discount'] : 0.0,
                'months' => $months ?: null,
                'month' => $months[0] ?? null,
            ], fn ($v) => $v !== null);
        })->values();

        $transportFee = (float) ($data['transport_fee'] ?? 0);
        if ($transportFee > 0) {
            $transportHead ??= FeeHead::query()->firstOrCreate(
                ['name' => 'Transport'],
                ['description' => 'School transport fee']
            );
            $transportCharge = (float) ($data['transport_charge'] ?? $transportFee);
            $transportMonths = $globalMonths;
            if ($transportFeeStart) {
                $transportMonths = array_values(array_filter(
                    $globalMonths,
                    fn ($m) => (string) $m >= $transportFeeStart
                ));
            }
            $rawItems->push(array_filter([
                'fee_head_id' => $transportHead->id,
                'fee_head_name' => 'Transport',
                'amount' => $transportFee,
                'charge' => max($transportCharge, $transportFee),
                'discount' => 0.0,
                'months' => $transportMonths ?: null,
                'month' => $transportMonths[0] ?? null,
                'transport_route_id' => $data['transport_route_id'] ?? null,
                'transport_stop_id' => $data['transport_stop_id'] ?? null,
            ], fn ($v) => $v !== null));
        }

        // Expand multi-month lines into one line per month (FIFO), so attribution never equal-splits.
        $items = collect();
        $runningCovered = []; // headId => month => paid+discount already allocated in this receipt
        $priorPaid = $this->paidByHeadForStudent($student, $session);
        foreach ($rawItems as $item) {
            $months = $item['months'] ?? [];
            $feeHeadId = (int) $item['fee_head_id'];
            $freq = $freqByHead[$feeHeadId] ?? 'monthly';
            $unit = (float) ($unitByHead[$feeHeadId] ?? 0);

            if (count($months) <= 1 || $unit <= 0) {
                $items->push(array_filter([
                    ...$item,
                    'discount' => ((float) ($item['discount'] ?? 0)) ?: null,
                ], fn ($v) => $v !== null));
                continue;
            }

            $already = $runningCovered[$feeHeadId] ?? [];
            $priorByMonth = $priorPaid['by_month'][(string) $feeHeadId] ?? [];
            $priorDisc = $priorPaid['discount_by_month'][(string) $feeHeadId] ?? [];
            foreach (array_unique(array_merge(array_keys($priorByMonth), array_keys($priorDisc), array_keys($already))) as $mk) {
                $already[$mk] = (float) ($already[$mk] ?? 0)
                    + (float) ($priorByMonth[$mk] ?? 0)
                    + (float) ($priorDisc[$mk] ?? 0);
            }

            $shares = FeeMonthAllocator::distribute(
                (float) $item['amount'],
                (float) ($item['discount'] ?? 0),
                $months,
                $unit,
                $already,
                $freq
            );

            foreach ($shares as $monthKey => $share) {
                $paidTake = (float) $share['paid'];
                $discTake = (float) $share['discount'];
                if ($paidTake <= 0 && $discTake <= 0) {
                    continue;
                }
                $priorOnMonth = (float) ($already[$monthKey] ?? 0);
                $monthCharge = max(0.0, $unit - $priorOnMonth);
                if ($monthCharge <= 0) {
                    $monthCharge = $paidTake + $discTake;
                }

                $line = array_filter([
                    'fee_head_id' => $feeHeadId,
                    'fee_head_name' => $item['fee_head_name'] ?? 'Fee',
                    'amount' => round($paidTake, 2),
                    'charge' => round(max($monthCharge, $paidTake), 2),
                    'discount' => $discTake > 0 ? round($discTake, 2) : null,
                    'months' => [$monthKey],
                    'month' => $monthKey,
                    'transport_route_id' => $item['transport_route_id'] ?? null,
                    'transport_stop_id' => $item['transport_stop_id'] ?? null,
                ], fn ($v) => $v !== null);

                if (($line['amount'] ?? 0) > 0) {
                    $items->push($line);
                    $runningCovered[$feeHeadId][$monthKey] = ($runningCovered[$feeHeadId][$monthKey] ?? 0)
                        + $paidTake + $discTake;
                    $already[$monthKey] = ($already[$monthKey] ?? 0) + $paidTake + $discTake;
                }
            }
        }

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Add at least one fee item or a transport fee.']);
        }

        $alreadyPaid = $this->paidMonthsForStudent($student, $session);
        $requestedMonths = collect($globalMonths)
            ->merge($items->flatMap(fn ($i) => $i['months'] ?? []))
            ->filter()
            ->unique()
            ->values();
        $overlap = $requestedMonths->intersect($alreadyPaid)->values()->all();
        if ($overlap !== []) {
            throw ValidationException::withMessages([
                'months' => 'These months are already paid: '.implode(', ', $overlap).'. Uncheck them and try again.',
            ]);
        }

        $feeStart = app(FeeBalanceService::class)->feeStartKey($student);
        if ($feeStart) {
            $beforeStart = $requestedMonths->filter(fn ($m) => (string) $m < $feeStart)->values()->all();
            if ($beforeStart !== []) {
                throw ValidationException::withMessages([
                    'months' => 'These months are before the student fee start ('.$feeStart.'): '.implode(', ', $beforeStart).'.',
                ]);
            }
        }

        $discountAmount = (float) ($data['discount_amount'] ?? 0);
        if ($discountAmount <= 0) {
            $discountAmount = (float) $items->sum(fn ($i) => (float) ($i['discount'] ?? 0));
        }

        $payment = FeePayment::create([
            'receipt_no' => $this->nextReceiptNo($session),
            'student_id' => $data['student_id'],
            'academic_session_id' => $session->id,
            'items' => $items->values()->all(),
            'amount' => $items->sum('amount') + ($data['fine_amount'] ?? 0),
            'discount_amount' => $discountAmount,
            'fine_amount' => $data['fine_amount'] ?? 0,
            'payment_mode' => $paymentMode,
            'payment_date' => $data['payment_date'] ?? now()->toDateString(),
            'remarks' => $data['remarks'] ?? null,
            'reference_no' => $data['reference_no'] ?? null,
            'collected_by_id' => Auth::guard('erp')->id(),
        ]);

        // fee_payments has no bank_account_id column, so the link to the chosen account is a
        // BankTransaction (Deposit) instead — BankAccount::currentBalance() already sums these,
        // the same mechanism a manually-entered deposit or an imported fee payment uses.
        if (! empty($data['bank_account_id'])) {
            BankTransaction::create([
                'bank_account_id' => $data['bank_account_id'],
                'type' => 'Deposit',
                'amount' => $payment->amount,
                'date' => $payment->payment_date,
                'reference_no' => $payment->receipt_no,
                'remarks' => 'Fee payment — '.$student->name,
            ]);
        }

        return response()->json($payment->load(['student:id,name,admission_no', 'collectedBy:id,name']), 201);
    }

    /** Printable deposit receipt for a collected fee payment. */
    public function receipt(FeePayment $payment)
    {
        $payment->load([
            'student:id,name,admission_no,roll_no,branch_id,school_class_id,section_id,father_id,mother_id',
            'student.branch:id,name',
            'student.schoolClass:id,name',
            'student.section:id,name',
            'student.father:id,name',
            'student.mother:id,name',
            'academicSession:id,name',
            'collectedBy:id,name',
        ]);

        $student = $payment->student;
        $session = $payment->academicSession;
        $school = SchoolSetting::current();
        $paidNet = round((float) $payment->amount - (float) $payment->refunded_amount, 2);
        $discount = (float) $payment->discount_amount;
        $payable = round($paidNet + $discount, 2);

        $monthKeys = collect($payment->items ?? [])
            ->flatMap(function ($item) {
                if (! empty($item['months']) && is_array($item['months'])) {
                    return $item['months'];
                }
                if (! empty($item['month'])) {
                    return [$item['month']];
                }

                return [];
            })
            ->filter()
            ->unique()
            ->values();

        $monthsLabel = $monthKeys->map(function ($m) {
            try {
                return Carbon::createFromFormat('Y-m', (string) $m)->format('F Y');
            } catch (\Throwable) {
                return (string) $m;
            }
        })->implode(', ');

        if ($monthsLabel === '') {
            $monthsLabel = $payment->payment_date
                ? Carbon::parse($payment->payment_date)->format('F Y')
                : '—';
        }

        $lines = [];
        $sno = 1;
        foreach ($payment->items ?? [] as $item) {
            $itemMonths = [];
            if (! empty($item['months']) && is_array($item['months'])) {
                $itemMonths = $item['months'];
            } elseif (! empty($item['month'])) {
                $itemMonths = [$item['month']];
            }
            $duration = collect($itemMonths)->map(function ($m) {
                try {
                    return Carbon::createFromFormat('Y-m', (string) $m)->format('F Y');
                } catch (\Throwable) {
                    return (string) $m;
                }
            })->implode(', ');
            if ($duration === '') {
                $duration = $monthsLabel;
            }

            $paid = (float) ($item['amount'] ?? 0);
            $itemDiscount = (float) ($item['discount'] ?? 0);
            $itemPayable = round($paid + $itemDiscount, 2);

            $lines[] = [
                'sno' => $sno++,
                'fee_particulars' => (string) ($item['fee_head_name'] ?? 'Fee'),
                'duration' => $duration,
                'payable' => $itemPayable,
                'paid' => $paid,
                'dues' => 0,
            ];
        }

        if ($lines === [] && $paidNet > 0) {
            $lines[] = [
                'sno' => 1,
                'fee_particulars' => 'Fee payment',
                'duration' => $monthsLabel,
                'payable' => $payable,
                'paid' => $paidNet,
                'dues' => 0,
            ];
        }

        $printedAt = $payment->created_at
            ? Carbon::parse($payment->created_at)->format('j M Y, h:i a')
            : ($payment->payment_date
                ? Carbon::parse($payment->payment_date)->format('j M Y')
                : now()->format('j M Y, h:i a'));

        return response()->json([
            'type' => 'deposit',
            'school' => [
                'name' => $school->school_name ?: 'Global Access School',
                'address' => $school->formatted_address,
                'phone' => $school->phone,
                'email' => $school->email,
                'logo_path' => $school->logo_url,
                'udise_no' => $school->udise_code,
            ],
            'receipt_no' => $payment->receipt_no,
            'printed_at' => $printedAt,
            'session' => [
                'id' => $session?->id,
                'name' => $session?->name ?? '—',
            ],
            'months_bar' => $monthsLabel,
            'student' => [
                'id' => $student?->id,
                'name' => $student?->name,
                'admission_no' => $student?->admission_no,
                'roll_no' => $student?->roll_no,
                'father' => $student?->father?->name,
                'mother' => $student?->mother?->name,
                'branch' => $student?->branch?->name,
                'class' => $student?->schoolClass?->name,
                'section' => $student?->section?->name,
                'photo_url' => null,
            ],
            'lines' => $lines,
            'amount_payable' => $payable,
            'counter_discount' => $discount,
            'amount_paid' => $paidNet,
            'payment_mode' => strtoupper((string) $payment->payment_mode),
            'paid_at' => ErpFeeSetting::current()->receipt_paid_at ?: 'SCHOOL',
            'remarks' => $payment->remarks,
        ]);
    }

    public function downloadPdf(FeePayment $payment, DocumentRenderService $renderer, DocumentDataBuilder $dataBuilder): StreamedResponse
    {
        return $renderer->streamPdf('fee_receipt', $dataBuilder->feeReceipt($payment), ($payment->receipt_no ?: 'fee-receipt').'.pdf');
    }

    public function refund(Request $request, FeePayment $payment)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . ($payment->amount - $payment->refunded_amount)],
            'reason' => 'required|string|max:255',
        ]);

        $newRefundedTotal = $payment->refunded_amount + $data['amount'];

        $payment->update([
            'refunded_amount' => $newRefundedTotal,
            'refund_reason' => $data['reason'],
            'refunded_at' => now(),
            'status' => $newRefundedTotal >= $payment->amount ? 'Refunded' : 'Partially Refunded',
        ]);

        return response()->json($payment->load(['student:id,name,admission_no', 'collectedBy:id,name']));
    }

    /**
     * Correct a payment that was entered wrong (amount, date, fee head, months, mode, reference).
     * Nothing is cached elsewhere — every due/balance figure is derived live from this row's
     * items/amount, so updating it here is the recalculation.
     */
    public function update(Request $request, FeePayment $payment)
    {
        if ($payment->status === 'Rolled Back') {
            throw ValidationException::withMessages(['status' => 'This receipt has been rolled back and can no longer be edited.']);
        }

        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.fee_head_id' => 'required|exists:fee_heads,id',
            'items.*.amount' => 'required|numeric|min:0.01',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.months' => 'nullable|array',
            'items.*.months.*' => 'string|max:7',
            'fine_amount' => 'nullable|numeric|min:0',
            'payment_mode' => ['required', Rule::in(['Cash', 'UPI', 'Card', 'Bank Transfer', 'Cheque'])],
            'payment_date' => 'required|date',
            'reference_no' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:255',
            'reason' => 'required|string|max:255',
        ]);

        $feeHeadNames = FeeHead::whereIn('id', collect($data['items'])->pluck('fee_head_id'))->pluck('name', 'id');

        $items = collect($data['items'])->map(function ($item) use ($feeHeadNames) {
            $months = array_values(array_unique($item['months'] ?? []));
            $amount = round((float) $item['amount'], 2);
            $discount = round((float) ($item['discount'] ?? 0), 2);

            return array_filter([
                'fee_head_id' => (int) $item['fee_head_id'],
                'fee_head_name' => $feeHeadNames[$item['fee_head_id']] ?? 'Fee',
                'amount' => $amount,
                'charge' => $amount + $discount,
                'discount' => $discount ?: null,
                'months' => $months ?: null,
                'month' => $months[0] ?? null,
            ], fn ($v) => $v !== null);
        })->values();

        // Only NEWLY added (fee head, month) pairs need to be checked against other receipts —
        // months this payment already covered before the edit are status quo, not a new
        // collision, even if legacy/imported data happens to overlap them elsewhere.
        $originalHeadMonths = collect($payment->items ?? [])->flatMap(
            fn ($i) => collect($i['months'] ?? array_filter([$i['month'] ?? null]))
                ->map(fn ($m) => ($i['fee_head_id'] ?? 0) . ':' . $m)
        )->unique();
        $newHeadMonths = $items->flatMap(fn ($i) => collect($i['months'] ?? [])->map(
            fn ($m) => $i['fee_head_id'] . ':' . $m
        ))->unique()->diff($originalHeadMonths)->values();
        if ($newHeadMonths->isNotEmpty()) {
            $overlap = FeePayment::query()
                ->where('student_id', $payment->student_id)
                ->where('academic_session_id', $payment->academic_session_id)
                ->where('id', '!=', $payment->id)
                ->where('status', '!=', 'Rolled Back')
                ->get(['items'])
                ->flatMap(fn (FeePayment $p) => collect($p->items ?? [])->flatMap(
                    fn ($i) => collect($i['months'] ?? array_filter([$i['month'] ?? null]))
                        ->map(fn ($m) => ($i['fee_head_id'] ?? 0) . ':' . $m)
                ))
                ->unique()
                ->intersect($newHeadMonths)
                ->values()
                ->map(fn ($key) => explode(':', $key, 2)[1] ?? $key)
                ->all();
            if ($overlap !== []) {
                throw ValidationException::withMessages([
                    'months' => 'These months are already paid on another receipt for the same fee head: ' . implode(', ', $overlap) . '.',
                ]);
            }
        }

        $amount = round((float) $items->sum('amount') + (float) ($data['fine_amount'] ?? 0), 2);
        if ($amount < (float) $payment->refunded_amount) {
            throw ValidationException::withMessages([
                'items' => 'The new amount (₹' . number_format($amount, 2) . ') is less than the ₹' . number_format((float) $payment->refunded_amount, 2) . ' already refunded on this receipt.',
            ]);
        }
        $discountAmount = (float) $items->sum(fn ($i) => (float) ($i['discount'] ?? 0));

        $before = $payment->only(['items', 'amount', 'discount_amount', 'fine_amount', 'payment_mode', 'payment_date', 'reference_no', 'remarks']);

        DB::transaction(function () use ($payment, $items, $amount, $discountAmount, $data, $before) {
            $payment->update([
                'items' => $items->values()->all(),
                'amount' => $amount,
                'discount_amount' => $discountAmount,
                'fine_amount' => $data['fine_amount'] ?? 0,
                'payment_mode' => $data['payment_mode'],
                'payment_date' => $data['payment_date'],
                'reference_no' => $data['reference_no'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'edited_at' => now(),
                'edited_by_id' => Auth::guard('erp')->id(),
            ]);

            FeePaymentAudit::create([
                'fee_payment_id' => $payment->id,
                'action' => 'Edit',
                'before' => $before,
                'after' => $payment->only(['items', 'amount', 'discount_amount', 'fine_amount', 'payment_mode', 'payment_date', 'reference_no', 'remarks']),
                'reason' => $data['reason'],
                'performed_by_id' => Auth::guard('erp')->id(),
            ]);
        });

        return response()->json($this->presentPayment(
            $payment->fresh(['student', 'collectedBy', 'editedBy', 'rolledBackBy'])
        ));
    }

    /**
     * Void an entire receipt. The row is never deleted — status flips to "Rolled Back" and
     * refunded_amount is set to the full amount so every due/paid calculation (which already
     * nets amount - refunded_amount) treats it as contributing nothing, restoring the student's
     * balance without any special-casing elsewhere.
     */
    public function rollback(Request $request, FeePayment $payment)
    {
        if ($payment->status === 'Rolled Back') {
            throw ValidationException::withMessages(['status' => 'This receipt has already been rolled back.']);
        }

        $remaining = max(0.01, (float) $payment->amount - (float) $payment->refunded_amount);
        $data = $request->validate([
            'reason' => 'required|string|max:255',
            'refund_amount' => 'nullable|numeric|min:0.01|max:' . $remaining,
            'refund_reason' => 'required_with:refund_amount|nullable|string|max:255',
        ]);

        $before = $payment->only(['status', 'refunded_amount', 'refund_reason']);

        DB::transaction(function () use ($payment, $data, $before) {
            $payment->update([
                'refunded_amount' => $payment->amount,
                'refund_reason' => $data['refund_reason'] ?? $data['reason'],
                'refunded_at' => now(),
                'status' => 'Rolled Back',
                'rollback_reason' => $data['reason'],
                'rolled_back_at' => now(),
                'rolled_back_by_id' => Auth::guard('erp')->id(),
            ]);

            // Reverse the matching bank deposit (if this payment was routed through one) so the
            // account balance stays accurate — without touching or deleting the original entry.
            $deposit = BankTransaction::query()
                ->where('reference_no', $payment->receipt_no)
                ->where('type', 'Deposit')
                ->first();
            if ($deposit) {
                BankTransaction::create([
                    'bank_account_id' => $deposit->bank_account_id,
                    'type' => 'Withdrawal',
                    'amount' => $deposit->amount,
                    'date' => now()->toDateString(),
                    'reference_no' => $payment->receipt_no,
                    'remarks' => 'Rollback reversal — receipt ' . $payment->receipt_no,
                ]);
            }

            FeePaymentAudit::create([
                'fee_payment_id' => $payment->id,
                'action' => 'Rollback',
                'before' => $before,
                'after' => $payment->only(['status', 'refunded_amount', 'refund_reason']),
                'reason' => $data['reason'],
                'refund_amount' => $data['refund_amount'] ?? null,
                'refund_reason' => $data['refund_reason'] ?? null,
                'performed_by_id' => Auth::guard('erp')->id(),
            ]);
        });

        return response()->json($this->presentPayment(
            $payment->fresh(['student', 'collectedBy', 'editedBy', 'rolledBackBy'])
        ));
    }

    /** Full edit + rollback audit trail for one receipt. */
    public function audits(FeePayment $payment)
    {
        return response()->json(
            $payment->audits()->with('performedBy:id,name')->get()
        );
    }

    /** @param  array<string, mixed>  $data */
    private function resolveDateRange(array $data): array
    {
        if (! empty($data['from']) || ! empty($data['to'])) {
            return [$data['from'] ?? null, $data['to'] ?? null];
        }

        if (empty($data['period']) || empty($data['date'])) {
            return [null, null];
        }

        $date = Carbon::parse($data['date']);

        return match ($data['period']) {
            'day' => [$date->toDateString(), $date->toDateString()],
            'year' => [$date->copy()->startOfYear()->toDateString(), $date->copy()->endOfYear()->toDateString()],
            default => [$date->copy()->startOfMonth()->toDateString(), $date->copy()->endOfMonth()->toDateString()],
        };
    }

    private function presentPayment(FeePayment $p, mixed $due = null): array
    {
        $items = collect($p->items ?? []);
        $monthsFromItems = $items->flatMap(function ($item) {
            if (! empty($item['months']) && is_array($item['months'])) {
                return $item['months'];
            }
            if (! empty($item['month'])) {
                return [$item['month']];
            }

            return [];
        })->filter()->unique()->values();

        $monthLabel = $monthsFromItems->isNotEmpty()
            ? $monthsFromItems->map(function ($m) {
                try {
                    return Carbon::createFromFormat('Y-m', (string) $m)->format('M Y');
                } catch (\Throwable) {
                    return (string) $m;
                }
            })->implode(', ')
            : ($p->payment_date ? Carbon::parse($p->payment_date)->format('M Y') : '—');

        $feeTypes = $items->pluck('fee_head_name')->filter()->implode(', ');

        return [
            ...$p->toArray(),
            'months' => $monthLabel,
            'fee_types' => $feeTypes ?: '—',
            'advance' => 0,
            'paid_net' => round((float) $p->amount - (float) $p->refunded_amount, 2),
            'due_amount' => $due !== null ? (float) $due : null,
            'edited_by' => $p->relationLoaded('editedBy') && $p->editedBy ? ['id' => $p->editedBy->id, 'name' => $p->editedBy->name] : null,
            'rolled_back_by' => $p->relationLoaded('rolledBackBy') && $p->rolledBackBy ? ['id' => $p->rolledBackBy->id, 'name' => $p->rolledBackBy->name] : null,
            'student' => $p->student ? [
                'id' => $p->student->id,
                'name' => $p->student->name,
                'admission_no' => $p->student->admission_no,
                'branch' => $p->student->branch ? ['id' => $p->student->branch->id, 'name' => $p->student->branch->name] : null,
                'school_class' => $p->student->schoolClass ? ['id' => $p->student->schoolClass->id, 'name' => $p->student->schoolClass->name] : null,
                'section' => $p->student->section ? ['id' => $p->student->section->id, 'name' => $p->student->section->name] : null,
            ] : null,
        ];
    }

    private function nextReceiptNo(?AcademicSession $session = null): string
    {
        $sessionDigits = $session ? preg_replace('/\D+/', '', (string) $session->name) : '';
        $token = $sessionDigits !== '' ? $sessionDigits : now()->format('Y');
        $prefix = "RCP-{$token}-";
        $count = FeePayment::where('receipt_no', 'like', $prefix.'%')->count() + 1;

        return sprintf('%s%05d', $prefix, $count);
    }
}
