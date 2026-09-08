<?php

namespace App\Http\Controllers\Erp\FeeManagement;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\FeeHead;
use App\Models\Student;
use App\Services\FeeBalanceService;
use App\Services\FeeCalculator;
use App\Support\TabularExport;
use Illuminate\Http\Request;

/**
 * Fee History — student-wise totals for selected fee heads + months.
 * Columns: Total (charged), Received (paid), Ledger Balance (due), Net (charge − concession).
 */
class FeeHistoryController extends Controller
{
    public function meta(Request $request)
    {
        $session = AcademicSession::fromRequest($request, true);
        $months = $session
            ? collect($session->months())->map(fn ($m) => [
                'key' => $m['key'] ?? null,
                'label' => $m['label'] ?? ($m['key'] ?? ''),
            ])->filter(fn ($m) => ! empty($m['key']))->values()
            : collect();

        $heads = FeeHead::query()->orderBy('name')->get(['id', 'name']);

        return response()->json([
            'session' => $session?->name,
            'months' => $months,
            'fee_heads' => $heads,
            'sort_options' => [
                ['key' => 'class_section_roll_adm', 'label' => 'Class > Section > Roll No > Adm No'],
                ['key' => 'class_section_name', 'label' => 'Class > Section > Name'],
                ['key' => 'roll_adm_name', 'label' => 'Roll No > Admission No > Name'],
                ['key' => 'admission_no', 'label' => 'Admission No'],
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'address', 'label' => 'Address'],
                ['key' => 'mobile', 'label' => 'Mobile'],
            ],
        ]);
    }

    public function index(Request $request)
    {
        $rows = $this->buildRows($request);

        return response()->json([
            'rows' => $rows,
            'summary' => [
                'students' => $rows->count(),
                'total' => round($rows->sum('total'), 2),
                'received' => round($rows->sum('received'), 2),
                'ledger_balance' => round($rows->sum('ledger_balance'), 2),
                'net' => round($rows->sum('net'), 2),
                'zero_balance' => $rows->where('ledger_balance', 0)->count(),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $data = $request->validate([
            'format' => 'nullable|in:csv,xlsx,pdf',
            'sort' => 'nullable|string|max:50',
        ]);
        $format = $data['format'] ?? 'xlsx';
        $sort = $data['sort'] ?? 'class_section_roll_adm';

        $rows = $this->sortRows($this->buildRows($request), $sort);

        $header = [
            'Admission No', 'Roll No', 'Student', 'Class', 'Section', 'Address', 'Mobile',
            'Total', 'Received', 'Ledger Balance', 'Net',
        ];
        $table = $rows->map(fn (array $r) => [
            $r['admission_no'], $r['roll_no'], $r['name'], $r['school_class'], $r['section'],
            $r['address'], $r['mobile'], $r['total'], $r['received'], $r['ledger_balance'], $r['net'],
        ])->all();

        return TabularExport::stream($header, $table, 'fee-history', 'Fee History', $format);
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
            'fee_head_ids' => 'nullable|array',
            'fee_head_ids.*' => 'integer|exists:fee_heads,id',
            'months' => 'nullable|array',
            'months.*' => 'string|max:7',
            'zero_balance' => 'nullable|in:all,only_zero,exclude_zero',
            'sort' => 'nullable|string|max:50',
        ]);

        $zeroMode = $data['zero_balance'] ?? 'all';
        $feeHeadIds = ! empty($data['fee_head_ids']) ? array_map('intval', $data['fee_head_ids']) : null;

        $query = Student::with([
            'schoolClass:id,name',
            'section:id,name',
            'branch:id,name',
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
                    ->orWhere('mobile', 'like', "%{$q}%");
            });
        }

        $students = $query->orderBy('name')->get();

        $session = AcademicSession::fromRequest($request, true);
        if (! $session) {
            return collect();
        }

        $monthKeys = ! empty($data['months'])
            ? array_values($data['months'])
            : collect($session->months())->map(fn ($m) => $m['key'] ?? null)->filter()->values()->all();

        FeeCalculator::flushRuntimeCache();
        FeeCalculator::warmForStudents($students, collect([$session]));
        $balance = app(FeeBalanceService::class);

        $rows = $students->map(function (Student $student) use ($session, $monthKeys, $balance, $feeHeadIds) {
            $calc = FeeCalculator::forStudent($student, $session);
            $paidInfo = $balance->paidByHead($student, $session);
            $keys = $balance->filterMonthsFromFeeStart($student, $monthKeys);
            $remaining = $balance->remainingForMonths($student, $session, $keys, $calc, $paidInfo, $feeHeadIds);

            return [
                'student_id' => $student->id,
                'admission_no' => $student->admission_no,
                'name' => $student->name,
                'roll_no' => $student->roll_no,
                'school_class' => $student->schoolClass?->name,
                'section' => $student->section?->name,
                'address' => trim(implode(', ', array_filter([
                    $student->address,
                    $student->address_line_2,
                ]))) ?: null,
                'mobile' => $student->mobile,
                'total' => round($remaining['charge'], 2),
                'received' => round($remaining['paid'], 2),
                'concession' => round($remaining['discount'], 2),
                'ledger_balance' => round($remaining['due'], 2),
                'net' => round($remaining['net'], 2),
            ];
        });

        if ($zeroMode === 'only_zero') {
            $rows = $rows->filter(fn (array $r) => (float) $r['ledger_balance'] <= 0.0001);
        } elseif ($zeroMode === 'exclude_zero') {
            $rows = $rows->filter(fn (array $r) => (float) $r['ledger_balance'] > 0.0001);
        }

        // Drop students with no charge/paid under selected heads/months (unless only_zero).
        if ($zeroMode !== 'only_zero') {
            $rows = $rows->filter(fn (array $r) => $r['total'] > 0 || $r['received'] > 0 || $r['ledger_balance'] > 0);
        }

        return $this->sortRows($rows->values(), $data['sort'] ?? 'class_section_roll_adm');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function sortRows(\Illuminate\Support\Collection $rows, string $sort): \Illuminate\Support\Collection
    {
        $cmp = static function ($a, $b) {
            return strnatcasecmp((string) $a, (string) $b);
        };

        return $rows->sort(function (array $a, array $b) use ($sort, $cmp) {
            return match ($sort) {
                'class_section_name' => $cmp($a['school_class'], $b['school_class'])
                    ?: $cmp($a['section'], $b['section'])
                    ?: $cmp($a['name'], $b['name']),
                'roll_adm_name' => $cmp($a['roll_no'], $b['roll_no'])
                    ?: $cmp($a['admission_no'], $b['admission_no'])
                    ?: $cmp($a['name'], $b['name']),
                'admission_no' => $cmp($a['admission_no'], $b['admission_no']),
                'name' => $cmp($a['name'], $b['name']),
                'address' => $cmp($a['address'], $b['address']) ?: $cmp($a['name'], $b['name']),
                'mobile' => $cmp($a['mobile'], $b['mobile']) ?: $cmp($a['name'], $b['name']),
                default => $cmp($a['school_class'], $b['school_class'])
                    ?: $cmp($a['section'], $b['section'])
                    ?: $cmp($a['roll_no'], $b['roll_no'])
                    ?: $cmp($a['admission_no'], $b['admission_no']),
            };
        })->values();
    }
}
