<?php

namespace App\Http\Controllers\Erp\Reports;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Student;
use App\Models\StudentTransport;
use App\Models\Vehicle;
use App\Services\FeeBalanceService;
use App\Services\FeeCalculator;
use App\Support\TabularExport;
use Illuminate\Http\Request;

/**
 * "Register" style reports for the sidebar Reports group — flat student lists
 * grouped by class, area (city), father, or vehicle. Each endpoint returns JSON
 * for the screen, or a csv|xlsx|pdf download when ?format= is supplied.
 */
class RegisterReportController extends Controller
{
    /** Class-wise register: pick a class, list its students. */
    public function classWise(Request $request): mixed
    {
        $data = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'search' => 'nullable|string|max:100',
            'format' => 'nullable|in:csv,xlsx,pdf',
        ]);

        $session = AcademicSession::fromRequest($request, true);
        $students = $this->baseStudentQuery($data)
            ->orderBy('school_class_id')
            ->orderByRaw('CAST(roll_no AS UNSIGNED), roll_no')
            ->orderBy('name')
            ->get();

        $fees = $this->feePositionsByStudent($students, $session);

        $rows = $students->map(fn (Student $s) => [
            'admission_no' => $s->admission_no,
            'name' => $s->name,
            'school_class' => $s->schoolClass?->name,
            'section' => $s->section?->name,
            'roll_no' => $s->roll_no,
            'father' => $s->father?->name,
            'mobile' => $s->mobile ?: $s->father?->phone,
            'collected' => $fees[$s->id]['collected'] ?? 0,
            'due' => $fees[$s->id]['due'] ?? 0,
        ])->values();

        if (! empty($data['format'])) {
            $header = ['Admission No', 'Name', 'Class', 'Section', 'Roll No', 'Father Name', 'Mobile', 'Collected', 'Due'];
            $table = $rows->map(fn (array $r) => [
                $r['admission_no'], $r['name'], $r['school_class'], $r['section'], $r['roll_no'], $r['father'], $r['mobile'], $r['collected'], $r['due'],
            ])->all();

            return TabularExport::stream($header, $table, 'report-class-wise', 'Class-wise Register', $data['format']);
        }

        return response()->json([
            'session' => $session?->name,
            'rows' => $rows,
            'total_collected' => round($rows->sum('collected'), 2),
            'total_due' => round($rows->sum('due'), 2),
        ]);
    }

    /** Sentinel for the "no address recorded" bucket in the area-wise picker. */
    private const AREA_BLANK = '__blank__';

    /**
     * Area-wise register. Without ?area= it returns just the list of distinct
     * addresses (deduplicated, with a student count each) for the picker; with
     * ?area= it returns the students living at that address.
     */
    public function areaWise(Request $request): mixed
    {
        $data = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'search' => 'nullable|string|max:100',
            'area' => 'nullable|string|max:255',
            'format' => 'nullable|in:csv,xlsx,pdf',
        ]);

        $session = AcademicSession::fromRequest($request, true);
        $students = $this->baseStudentQuery($data)->orderBy('name')->get();
        $fees = $this->feePositionsByStudent($students, $session);

        $normalize = static fn (Student $s): string => trim(preg_replace('/\s+/', ' ', (string) $s->address));

        $areas = $students
            ->groupBy($normalize)
            ->map(function ($list, $address) use ($fees) {
                $collected = 0.0;
                $due = 0.0;
                foreach ($list as $s) {
                    $collected += $fees[$s->id]['collected'] ?? 0;
                    $due += $fees[$s->id]['due'] ?? 0;
                }

                return [
                    'value' => $address === '' ? self::AREA_BLANK : $address,
                    'label' => $address === '' ? 'No address on file' : $address,
                    'count' => $list->count(),
                    'collected' => round($collected, 2),
                    'due' => round($due, 2),
                ];
            })
            ->sortBy(fn ($a) => $a['label'] === 'No address on file' ? 'zzzz' : strtolower($a['label']))
            ->values();

        $area = $data['area'] ?? null;
        $rows = null;
        if ($area !== null && $area !== '') {
            $filtered = $students->filter(
                fn (Student $s) => $area === self::AREA_BLANK
                    ? $normalize($s) === ''
                    : $normalize($s) === $area
            )->values();

            $vehicleByStudent = $this->vehicleByStudent($filtered->pluck('id')->all());
            $rows = $filtered->map(fn (Student $s) => [
                'admission_no' => $s->admission_no,
                'name' => $s->name,
                'father' => $s->father?->name,
                'mobile' => $s->mobile ?: $s->father?->phone,
                'vehicle' => $vehicleByStudent[$s->id] ?? null,
                'collected' => $fees[$s->id]['collected'] ?? 0,
                'due' => $fees[$s->id]['due'] ?? 0,
            ])->values();
        }

        if (! empty($data['format'])) {
            $areaLabel = $area === self::AREA_BLANK ? 'No address on file' : (string) ($area ?? '');
            $header = ['Address', 'Admission No', 'Name', 'Father Name', 'Mobile', 'Vehicle', 'Collected', 'Due'];
            $table = [];
            foreach (($rows ?? collect()) as $r) {
                $table[] = [$areaLabel, $r['admission_no'], $r['name'], $r['father'], $r['mobile'], $r['vehicle'], $r['collected'], $r['due']];
            }

            return TabularExport::stream(
                $header,
                $table,
                'report-area-wise',
                'Area-wise Register'.($areaLabel !== '' ? " — {$areaLabel}" : ''),
                $data['format']
            );
        }

        return response()->json([
            'session' => $session?->name,
            'areas' => $areas,
            'rows' => $rows,
            'total_collected' => $rows ? round($rows->sum('collected'), 2) : null,
            'total_due' => $rows ? round($rows->sum('due'), 2) : null,
        ]);
    }

    /** Father-wise register: real sibling groups (father + shared mother/address), with collected/due. */
    public function fatherWise(Request $request): mixed
    {
        $data = $request->validate([
            'format' => 'nullable|in:csv,xlsx,pdf',
        ]);

        $session = AcademicSession::fromRequest($request, true);

        $students = Student::with([
            'schoolClass:id,name',
            'section:id,name',
            'father:id,name,phone',
            'mother:id,name',
        ])
            ->where('status', 'Active')
            ->whereNotNull('father_id')
            ->orderBy('name')
            ->get();

        $families = collect();
        foreach ($students->groupBy('father_id') as $list) {
            if ($list->count() < 2) {
                continue;
            }
            foreach ($this->siblingClusters($list) as $cluster) {
                if ($cluster->count() >= 2) {
                    $families->push($cluster);
                }
            }
        }

        $fees = $this->feePositionsByStudent($families->flatten(1), $session);

        $normalizeAddress = static fn (Student $s): string => self::normalizeText($s->address);

        $fathers = $families->map(function ($list) use ($fees, $normalizeAddress) {
            $father = $list->first()->father;
            $addresses = $list->map($normalizeAddress)->filter()->unique()->values();
            $addressMatch = $addresses->count() <= 1;

            $children = $list->map(function (Student $s) use ($fees, $normalizeAddress) {
                $address = $normalizeAddress($s);

                return [
                    'admission_no' => $s->admission_no,
                    'name' => $s->name,
                    'school_class' => trim(($s->schoolClass?->name ?? '').' '.($s->section?->name ?? '')),
                    'mother' => $s->mother?->name,
                    'address' => $address !== '' ? $address : null,
                    'collected' => $fees[$s->id]['collected'] ?? 0,
                    'due' => $fees[$s->id]['due'] ?? 0,
                ];
            })->values();

            // The parent record's own phone is rarely filled in; the student's `mobile`
            // reliably holds the guardian's contact number (same fallback the Class-wise
            // and Area-wise reports use).
            $phone = $father?->phone ?: $list->pluck('mobile')->filter()->first();

            return [
                'father' => $father?->name ?? '—',
                'phone' => $phone,
                'children_count' => $children->count(),
                'address_match' => $addressMatch,
                'total_collected' => round($children->sum('collected'), 2),
                'total_due' => round($children->sum('due'), 2),
                'children' => $children,
            ];
        })
            ->sortByDesc('total_due')
            ->values();

        if (! empty($data['format'])) {
            $header = ['Father Name', 'Phone', 'Children', 'Address Match', 'Admission No', 'Student', 'Class', 'Mother', 'Address', 'Collected', 'Due'];
            $table = [];
            foreach ($fathers as $f) {
                foreach ($f['children'] as $c) {
                    $table[] = [
                        $f['father'], $f['phone'], $f['children_count'], $f['address_match'] ? 'Yes' : 'No',
                        $c['admission_no'], $c['name'], $c['school_class'], $c['mother'], $c['address'], $c['collected'], $c['due'],
                    ];
                }
            }

            return TabularExport::stream($header, $table, 'report-father-wise', 'Father-wise Register', $data['format']);
        }

        return response()->json([
            'session' => $session?->name,
            'fathers' => $fathers,
        ]);
    }

    /** Vehicle-wise register: each route on a vehicle is a "trip"; list its students. */
    public function vehicleWise(Request $request): mixed
    {
        $data = $request->validate([
            'format' => 'nullable|in:csv,xlsx,pdf',
        ]);

        $session = AcademicSession::fromRequest($request, true);

        $vehicles = Vehicle::with([
            'driver:id,name,phone',
            'routes:id,vehicle_id,name,route_code',
        ])->orderBy('vehicle_no')->get();

        $assignments = StudentTransport::with([
            'student:id,name,admission_no,roll_no,school_class_id,section_id,status',
            'student.schoolClass:id,name',
            'student.section:id,name',
            'routeStop:id,stop_name',
        ])
            ->where('status', 'Active')
            ->get()
            ->filter(fn (StudentTransport $t) => $t->student && $t->student->status === 'Active')
            ->groupBy('route_id');

        $studentIds = $assignments->flatten(1)->pluck('student_id')->unique()->filter()->values()->all();
        $feeStudents = $studentIds === []
            ? collect()
            : Student::whereIn('id', $studentIds)->get();
        $fees = $this->feePositionsByStudent($feeStudents, $session);

        $vehicleRows = $vehicles->map(function (Vehicle $v) use ($assignments, $fees) {
            $trips = $v->routes->map(function ($route, $i) use ($assignments, $fees) {
                $students = $assignments->get($route->id, collect())
                    ->sortBy(fn (StudentTransport $t) => $t->student->name)
                    ->map(fn (StudentTransport $t) => [
                        'admission_no' => $t->student->admission_no,
                        'name' => $t->student->name,
                        'school_class' => trim(($t->student->schoolClass?->name ?? '').' '.($t->student->section?->name ?? '')),
                        'stop' => $t->routeStop?->stop_name,
                        'collected' => $fees[$t->student->id]['collected'] ?? 0,
                        'due' => $fees[$t->student->id]['due'] ?? 0,
                    ])->values();

                return [
                    'trip_no' => $i + 1,
                    'route' => $route->name,
                    'route_code' => $route->route_code,
                    'student_count' => $students->count(),
                    'total_collected' => round($students->sum('collected'), 2),
                    'total_due' => round($students->sum('due'), 2),
                    'students' => $students,
                ];
            })->values();

            return [
                'vehicle_no' => $v->vehicle_no,
                'type' => $v->type,
                'driver' => $v->driver?->name,
                'driver_phone' => $v->driver?->phone,
                'trip_count' => $trips->count(),
                'student_count' => $trips->sum('student_count'),
                'total_collected' => round($trips->sum('total_collected'), 2),
                'total_due' => round($trips->sum('total_due'), 2),
                'trips' => $trips,
            ];
        })->values();

        if (! empty($data['format'])) {
            $header = ['Vehicle', 'Driver', 'Trip', 'Route', 'Admission No', 'Student', 'Class', 'Stop', 'Collected', 'Due'];
            $table = [];
            foreach ($vehicleRows as $v) {
                foreach ($v['trips'] as $t) {
                    foreach ($t['students'] as $s) {
                        $table[] = [
                            $v['vehicle_no'], $v['driver'], 'Trip '.$t['trip_no'], $t['route'],
                            $s['admission_no'], $s['name'], $s['school_class'], $s['stop'], $s['collected'], $s['due'],
                        ];
                    }
                }
            }

            return TabularExport::stream($header, $table, 'report-vehicle-wise', 'Vehicle-wise Register', $data['format']);
        }

        return response()->json([
            'session' => $session?->name,
            'vehicles' => $vehicleRows,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function baseStudentQuery(array $data): \Illuminate\Database\Eloquent\Builder
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
                $w->where('name', 'like', "%{$q}%")->orWhere('admission_no', 'like', "%{$q}%");
            });
        }

        return $query;
    }

    /**
     * Split same-father students into sibling clusters that share mother and/or address.
     * Empty mother/address never counts as a match (avoids false merges).
     *
     * @param  \Illuminate\Support\Collection<int, Student>  $siblings
     * @return list<\Illuminate\Support\Collection<int, Student>>
     */
    private function siblingClusters($siblings): array
    {
        $list = $siblings->values();
        $n = $list->count();
        if ($n === 0) {
            return [];
        }

        $hasSignal = false;
        foreach ($list as $s) {
            if ($this->studentFamilySignals($s) !== []) {
                $hasSignal = true;
                break;
            }
        }

        // No mother/address data at all — keep the whole father group (legacy behaviour).
        if (! $hasSignal) {
            return [$list];
        }

        $parent = range(0, $n - 1);
        $find = function (int $i) use (&$parent, &$find): int {
            return $parent[$i] === $i ? $i : ($parent[$i] = $find($parent[$i]));
        };
        $union = function (int $a, int $b) use (&$parent, $find): void {
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[$rb] = $ra;
            }
        };

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($this->studentsShareFamilySignal($list[$i], $list[$j])) {
                    $union($i, $j);
                }
            }
        }

        $groups = [];
        for ($i = 0; $i < $n; $i++) {
            $root = $find($i);
            $groups[$root] ??= collect();
            $groups[$root]->push($list[$i]);
        }

        return array_values($groups);
    }

    /**
     * @return list<string>
     */
    private function studentFamilySignals(Student $s): array
    {
        $signals = [];
        if ($s->mother_id) {
            $signals[] = 'mother_id:'.$s->mother_id;
        }
        $motherName = self::normalizeText($s->mother?->name);
        if ($motherName !== '') {
            $signals[] = 'mother:'.$motherName;
        }
        $address = self::normalizeText($s->address);
        if ($address !== '') {
            $signals[] = 'address:'.$address;
        }

        return $signals;
    }

    private function studentsShareFamilySignal(Student $a, Student $b): bool
    {
        $sa = $this->studentFamilySignals($a);
        $sb = $this->studentFamilySignals($b);
        if ($sa === [] || $sb === []) {
            return false;
        }

        return count(array_intersect($sa, $sb)) > 0;
    }

    private static function normalizeText(?string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $value));
    }

    /**
     * Collected (paid) and due per student for months from session start through current.
     *
     * @param  iterable<int, Student>  $students
     * @return array<int, array{collected: float, due: float}>
     */
    private function feePositionsByStudent(iterable $students, ?AcademicSession $session): array
    {
        $students = collect($students)->filter()->values();
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
            $out[$s->id] = [
                'collected' => round((float) ($pos['paid'] ?? 0), 2),
                'due' => round((float) ($pos['due'] ?? 0), 2),
            ];
        }

        return $out;
    }

    /**
     * Map student_id => vehicle_no for the student's active transport assignment.
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, string|null>
     */
    private function vehicleByStudent(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        return StudentTransport::with('route.vehicle:id,vehicle_no')
            ->whereIn('student_id', $studentIds)
            ->where('status', 'Active')
            ->get()
            ->keyBy('student_id')
            ->map(fn (StudentTransport $t) => $t->route?->vehicle?->vehicle_no)
            ->all();
    }

    /**
     * Session-start month through the current month (capped at session end).
     *
     * @return list<string> Y-m keys
     */
    private function monthKeysTillCurrent(AcademicSession $session): array
    {
        if (! $session->start_date || ! $session->end_date) {
            return [];
        }

        $start = $session->start_date->copy()->startOfMonth();
        $end = $session->end_date->copy()->startOfMonth();
        $now = now()->startOfMonth();

        if ($now->lt($start)) {
            return [];
        }
        $till = $now->gt($end) ? $end->copy() : $now->copy();

        $keys = [];
        $cursor = $start->copy();
        while ($cursor <= $till) {
            $keys[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $keys;
    }
}
