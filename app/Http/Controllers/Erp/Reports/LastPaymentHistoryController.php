<?php

namespace App\Http\Controllers\Erp\Reports;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\FeePayment;
use App\Models\Student;
use App\Services\FeeBalanceService;
use App\Services\FeeCalculator;
use App\Support\TabularExport;
use Illuminate\Http\Request;

/**
 * Reports → Last Payment History: each active student's most recent fee receipt
 * (date, amount, what was paid for) plus current due. Supports column-picked
 * csv|xlsx|pdf export via ?format= and ?columns[].
 */
class LastPaymentHistoryController extends Controller
{
    /** @var list<array{key: string, label: string}> */
    public const COLUMNS = [
        ['key' => 'admission_no', 'label' => 'Adm No'],
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'class', 'label' => 'Class'],
        ['key' => 'section', 'label' => 'Section'],
        ['key' => 'father', 'label' => 'Father'],
        ['key' => 'mobile', 'label' => 'Mobile'],
        ['key' => 'last_paid_month', 'label' => 'Month'],
        ['key' => 'last_paid_date', 'label' => 'Last Paid Date'],
        ['key' => 'last_paid_amount', 'label' => 'Last Paid Amount'],
        ['key' => 'receipt_no', 'label' => 'Receipt No'],
        ['key' => 'payment_mode', 'label' => 'Mode'],
        ['key' => 'paid_for', 'label' => 'Paid For'],
        ['key' => 'due', 'label' => 'Due'],
    ];

    public function index(Request $request): mixed
    {
        $data = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'search' => 'nullable|string|max:100',
            'has_payment' => 'nullable|in:all,yes,no',
            'dues' => 'nullable|in:all,due,clear',
            'format' => 'nullable|in:csv,xlsx,pdf',
            'columns' => 'nullable|array',
            'columns.*' => 'string|max:40',
        ]);

        $session = AcademicSession::fromRequest($request, true);
        $students = $this->students($data);
        $lastByStudent = $this->lastPaymentsByStudent($students->pluck('id')->all());
        $dues = $this->duesByStudent($students, $session);

        $rows = $students->map(function (Student $s) use ($lastByStudent, $dues) {
            $pay = $lastByStudent[$s->id] ?? null;

            return [
                'student_id' => $s->id,
                'admission_no' => $s->admission_no,
                'name' => $s->name,
                'class' => $s->schoolClass?->name,
                'section' => $s->section?->name,
                'father' => $s->father?->name,
                'mobile' => $s->mobile ?: $s->father?->phone,
                'last_paid_month' => $this->monthLabel($pay['date'] ?? null),
                'last_paid_date' => $pay['date'] ?? null,
                'last_paid_amount' => $pay['amount'] ?? null,
                'receipt_no' => $pay['receipt_no'] ?? null,
                'payment_mode' => $pay['payment_mode'] ?? null,
                'paid_for' => $pay['paid_for'] ?? null,
                'due' => $dues[$s->id] ?? 0.0,
            ];
        });

        $hasPayment = $data['has_payment'] ?? 'all';
        if ($hasPayment === 'yes') {
            $rows = $rows->filter(fn (array $r) => $r['last_paid_date'] !== null);
        } elseif ($hasPayment === 'no') {
            $rows = $rows->filter(fn (array $r) => $r['last_paid_date'] === null);
        }

        $duesFilter = $data['dues'] ?? 'all';
        if ($duesFilter === 'due') {
            $rows = $rows->filter(fn (array $r) => (float) $r['due'] > 0.0001);
        } elseif ($duesFilter === 'clear') {
            $rows = $rows->filter(fn (array $r) => (float) $r['due'] <= 0.0001);
        }

        $rows = $rows->values();

        if (! empty($data['format'])) {
            $wanted = $this->resolveColumns($data['columns'] ?? null);
            $header = array_column($wanted, 'label');
            $keys = array_column($wanted, 'key');
            $table = $rows->map(function (array $r) use ($keys) {
                return array_map(function (string $key) use ($r) {
                    $v = $r[$key] ?? '';
                    if ($key === 'last_paid_date' && is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                        return \Carbon\Carbon::createFromFormat('Y-m-d', $v)->format('d/m/Y');
                    }
                    if (in_array($key, ['last_paid_amount', 'due'], true) && $v !== null && $v !== '') {
                        return round((float) $v, 2);
                    }

                    return $v ?? '';
                }, $keys);
            })->all();

            return TabularExport::stream(
                $header,
                $table,
                'last-payment-history',
                'Last Payment History'.($session?->name ? ' — '.$session->name : ''),
                $data['format']
            );
        }

        return response()->json([
            'session' => $session ? ['id' => $session->id, 'name' => $session->name] : null,
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'totals' => [
                'students' => $rows->count(),
                'with_payment' => $rows->filter(fn (array $r) => $r['last_paid_date'] !== null)->count(),
                'last_paid_amount' => round((float) $rows->sum(fn (array $r) => (float) ($r['last_paid_amount'] ?? 0)), 2),
                'due' => round((float) $rows->sum('due'), 2),
            ],
        ]);
    }

    /** e.g. 2026-11-10 → Nov-2026 */
    private function monthLabel(?string $date): ?string
    {
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        return \Carbon\Carbon::createFromFormat('Y-m-d', $date)->format('M-Y');
    }

    /**
     * @param  list<string>|null  $requested
     * @return list<array{key: string, label: string}>
     */
    private function resolveColumns(?array $requested): array
    {
        $byKey = collect(self::COLUMNS)->keyBy('key');
        if ($requested) {
            $picked = [];
            foreach ($requested as $key) {
                if ($byKey->has($key)) {
                    $picked[] = $byKey->get($key);
                }
            }
            if ($picked !== []) {
                return $picked;
            }
        }

        return self::COLUMNS;
    }

    /** @param  array<string, mixed>  $data */
    private function students(array $data)
    {
        $query = Student::with([
            'schoolClass:id,name',
            'section:id,name',
            'father:id,name,phone',
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
                    ->orWhere('admission_no', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%")
                    ->orWhereHas('father', fn ($f) => $f->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"));
            });
        }

        return $query->orderBy('school_class_id')->orderBy('name')->get();
    }

    /**
     * Latest non-rolled-back payment per student.
     *
     * @param  list<int>  $studentIds
     * @return array<int, array{date: string, amount: float, receipt_no: ?string, payment_mode: ?string, paid_for: string}>
     */
    private function lastPaymentsByStudent(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $payments = FeePayment::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', '!=', 'Rolled Back')
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get([
                'id', 'student_id', 'receipt_no', 'payment_date', 'amount', 'refunded_amount',
                'payment_mode', 'items', 'fine_amount',
            ]);

        $out = [];
        foreach ($payments as $p) {
            $sid = (int) $p->student_id;
            if (isset($out[$sid])) {
                continue;
            }
            $net = round((float) $p->amount - (float) $p->refunded_amount, 2);
            $out[$sid] = [
                'date' => $p->payment_date?->format('Y-m-d'),
                'amount' => $net,
                'receipt_no' => $p->receipt_no,
                'payment_mode' => $p->payment_mode,
                'paid_for' => $this->describePaidFor($p),
            ];
        }

        return $out;
    }

    private function describePaidFor(FeePayment $payment): string
    {
        $bits = [];
        foreach ($payment->items ?? [] as $item) {
            $name = trim((string) ($item['fee_head_name'] ?? 'Fee'));
            $months = [];
            if (! empty($item['months']) && is_array($item['months'])) {
                $months = $item['months'];
            } elseif (! empty($item['month'])) {
                $months = [$item['month']];
            }
            $monthLabels = [];
            foreach ($months as $m) {
                $key = substr((string) $m, 0, 7);
                if (preg_match('/^\d{4}-\d{2}$/', $key)) {
                    try {
                        $monthLabels[] = \Carbon\Carbon::createFromFormat('Y-m', $key)->format('M Y');
                    } catch (\Throwable) {
                        $monthLabels[] = $key;
                    }
                }
            }
            $bits[] = $monthLabels !== [] ? $name.' ('.implode(', ', $monthLabels).')' : $name;
        }
        if ((float) $payment->fine_amount > 0.0001) {
            $bits[] = 'Fine';
        }

        return $bits !== [] ? implode('; ', $bits) : '—';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Student>  $students
     * @return array<int, float>
     */
    private function duesByStudent($students, ?AcademicSession $session): array
    {
        if (! $session || $students->isEmpty()) {
            return [];
        }

        FeeCalculator::flushRuntimeCache();
        FeeCalculator::warmForStudents($students, collect([$session]));
        $balance = app(FeeBalanceService::class);
        $monthKeys = $this->monthKeysTillCurrent($session);
        $out = [];

        foreach ($students as $s) {
            $calc = FeeCalculator::forStudent($s, $session);
            $keys = $balance->filterMonthsFromFeeStart($s, $monthKeys);
            $paidInfo = $balance->paidByHead($s, $session);
            $pos = $balance->remainingForMonths($s, $session, $keys, $calc, $paidInfo);
            $out[$s->id] = round((float) ($pos['due'] ?? 0), 2);
        }

        return $out;
    }

    /** @return list<string> */
    private function monthKeysTillCurrent(AcademicSession $session): array
    {
        $keys = array_column($session->months(), 'key');
        $cap = now()->format('Y-m');
        $end = $session->end_date?->format('Y-m') ?: $cap;
        if ($cap > $end) {
            $cap = $end;
        }

        return array_values(array_filter($keys, fn (string $k) => $k <= $cap));
    }
}
