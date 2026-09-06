<?php

namespace App\Http\Controllers\Erp\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Erp\Attendance\Concerns\ResolvesAttendableType;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\Driver;
use App\Models\Holiday;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentTransport;
use App\Models\Teacher;
use App\Models\TransportRoute;
use App\Models\WorkingDayConfig;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    use ResolvesAttendableType;

    /** Every marked day for one student, newest first, plus a present/absent/leave/late/half-day summary. */
    public function studentHistory(Request $request, Student $student)
    {
        $base = Attendance::query()
            ->where('attendable_type', 'student')
            ->where('attendable_id', $student->id);
        AcademicSession::applyDateWindow($base, $request, 'date');

        $summaryRow = (clone $base)
            ->selectRaw("SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent")
            ->selectRaw("SUM(CASE WHEN status = 'Leave' THEN 1 ELSE 0 END) as leave_count")
            ->selectRaw("SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late")
            ->selectRaw("SUM(CASE WHEN status = 'Half Day' THEN 1 ELSE 0 END) as half_day")
            ->selectRaw('COUNT(*) as total_marked')
            ->first();

        $present = (int) ($summaryRow->present ?? 0);
        $absent = (int) ($summaryRow->absent ?? 0);
        $leave = (int) ($summaryRow->leave_count ?? 0);
        $late = (int) ($summaryRow->late ?? 0);
        $halfDay = (int) ($summaryRow->half_day ?? 0);
        $total = (int) ($summaryRow->total_marked ?? 0);
        $percentage = $total > 0 ? round((($present + $late + $halfDay * 0.5) / $total) * 100, 1) : 0;

        $records = (clone $base)
            ->orderByDesc('date')
            ->limit(400)
            ->get(['date', 'status', 'remarks']);

        return response()->json([
            'records' => $this->withRunningPresentCount($records),
            'summary' => [
                'present' => $present,
                'absent' => $absent,
                'leave' => $leave,
                'late' => $late,
                'half_day' => $halfDay,
                'total_marked' => $total,
                'percentage' => $percentage,
            ],
        ]);
    }

    /** Dates that have at least one student attendance row in the range (History tab). */
    public function studentSessions(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'branch_id' => 'nullable|exists:branches,id',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
        ]);

        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;

        if ((! $from || ! $to) && ! AcademicSession::requestWantsAll($request)) {
            $session = AcademicSession::fromRequest($request);
            if ($session?->start_date && $session?->end_date) {
                $from ??= $session->start_date->toDateString();
                $to ??= $session->end_date->toDateString();
            }
        }

        $from ??= now()->startOfMonth()->toDateString();
        $to ??= now()->toDateString();

        $studentIds = $this->filteredStudentIds($data);
        if ($studentIds === []) {
            return response()->json(['from' => $from, 'to' => $to, 'sessions' => []]);
        }

        $sessions = Attendance::query()
            ->where('attendable_type', 'student')
            ->whereIn('attendable_id', $studentIds)
            ->whereBetween('date', [$from, $to])
            ->selectRaw('date, COUNT(*) as marked_count')
            ->selectRaw("SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count")
            ->selectRaw("SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count")
            ->groupBy('date')
            ->orderByDesc('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date instanceof \DateTimeInterface ? $row->date->format('Y-m-d') : (string) $row->date,
                'marked_count' => (int) $row->marked_count,
                'present_count' => (int) $row->present_count,
                'absent_count' => (int) $row->absent_count,
            ]);

        return response()->json(['from' => $from, 'to' => $to, 'sessions' => $sessions]);
    }

    /** Month grid for Excel view: students × days with status letters. */
    public function studentSheet(Request $request)
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
            'branch_id' => 'nullable|exists:branches,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
        ]);

        $start = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $daysInMonth = $start->daysInMonth;

        $students = Student::query()
            ->where('status', 'Active')
            ->when($data['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->where('school_class_id', $data['school_class_id'])
            ->when($data['section_id'] ?? null, fn ($q, $id) => $q->where('section_id', $id))
            ->orderBy('roll_no')
            ->orderBy('name')
            ->get(['id', 'admission_no', 'name', 'roll_no']);

        $marks = Attendance::query()
            ->where('attendable_type', 'student')
            ->whereIn('attendable_id', $students->pluck('id'))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['attendable_id', 'date', 'status']);

        $byStudent = [];
        foreach ($marks as $mark) {
            $day = (int) Carbon::parse($mark->date)->format('j');
            $byStudent[$mark->attendable_id][$day] = $this->statusLetter($mark->status);
        }

        $days = range(1, $daysInMonth);
        $rows = $students->map(function (Student $student) use ($byStudent, $days) {
            $cells = [];
            foreach ($days as $day) {
                $cells[(string) $day] = $byStudent[$student->id][$day] ?? '';
            }

            return [
                'id' => $student->id,
                'admission_no' => $student->admission_no,
                'name' => $student->name,
                'roll_no' => $student->roll_no,
                'days' => $cells,
            ];
        });

        return response()->json([
            'month' => $data['month'],
            'days' => $days,
            'rows' => $rows->values(),
        ]);
    }

    /** CSV download of the month sheet. */
    public function exportStudentSheet(Request $request): StreamedResponse
    {
        $payload = $this->studentSheet($request)->getData(true);
        $filename = 'attendance-sheet-' . ($payload['month'] ?? 'export') . '.csv';

        return response()->streamDownload(function () use ($payload) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $header = array_merge(['Roll', 'Admission No', 'Name'], array_map('strval', $payload['days'] ?? []));
            fputcsv($out, $header);
            foreach ($payload['rows'] ?? [] as $row) {
                $line = [$row['roll_no'] ?? '', $row['admission_no'] ?? '', $row['name'] ?? ''];
                foreach ($payload['days'] as $day) {
                    $line[] = $row['days'][(string) $day] ?? '';
                }
                fputcsv($out, $line);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Logged-in ERP user's own attendance (Teacher matched by email). */
    public function mine(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);
        $from = $data['from'] ?? now()->startOfYear()->toDateString();
        $to = $data['to'] ?? now()->toDateString();

        $user = Auth::guard('erp')->user();
        $teacher = $user?->email
            ? Teacher::where('email', $user->email)->first()
            : null;

        if (! $teacher) {
            return response()->json([
                'from' => $from,
                'to' => $to,
                'person' => null,
                'records' => [],
                'message' => 'No attendance profile linked to your login.',
            ]);
        }

        $records = Attendance::where('attendable_type', 'teacher')
            ->where('attendable_id', $teacher->id)
            ->whereBetween('date', [$from, $to])
            ->orderByDesc('date')
            ->get(['date', 'status', 'remarks']);

        return response()->json([
            'from' => $from,
            'to' => $to,
            'person' => ['id' => $teacher->id, 'name' => $teacher->name, 'type' => 'teacher'],
            'records' => $records,
        ]);
    }

    /** Resolve the logged-in ERP user's own Staff profile by email (same convention as Teacher's `mine()`). */
    private function resolveStaffForCurrentUser(): ?Staff
    {
        $user = Auth::guard('erp')->user();

        return $user?->email ? Staff::where('email', $user->email)->first() : null;
    }

    /** Logged-in staff member's own check-in status for a date (Mark tab). */
    public function staffMine(Request $request)
    {
        $data = $request->validate(['date' => 'nullable|date']);
        $date = $data['date'] ?? now()->toDateString();

        $staff = $this->resolveStaffForCurrentUser();
        if (! $staff) {
            return response()->json([
                'person' => null,
                'today' => null,
                'message' => 'No attendance profile linked to your login.',
            ]);
        }

        $today = Attendance::where('attendable_type', 'staff')
            ->where('attendable_id', $staff->id)
            ->where('date', $date)
            ->first();

        return response()->json([
            'person' => ['id' => $staff->id, 'name' => $staff->name],
            'today' => $today ? [
                'status' => $today->status,
                'remarks' => $today->remarks,
                'has_photo' => (bool) $today->photo_path,
            ] : null,
        ]);
    }

    /** Self check-in for the logged-in staff member (Present + optional note/selfie). Cannot mark anyone else — the target Staff record is resolved server-side from the login, never from the request. */
    public function staffCheckIn(Request $request)
    {
        $staff = $this->resolveStaffForCurrentUser();
        if (! $staff) {
            return response()->json(['message' => 'No attendance profile linked to your login.'], 422);
        }

        $data = $request->validate([
            'date' => 'required|date',
            'remarks' => 'nullable|string|max:255',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $existing = Attendance::where('attendable_type', 'staff')
            ->where('attendable_id', $staff->id)
            ->where('date', $data['date'])
            ->first();

        $photoPath = $existing?->photo_path;
        if ($request->hasFile('photo')) {
            if ($photoPath && Storage::disk('local')->exists($photoPath)) {
                Storage::disk('local')->delete($photoPath);
            }
            $photoPath = $request->file('photo')->store('attendance/staff', 'local');
        }

        $attendance = Attendance::updateOrCreate(
            ['attendable_type' => 'staff', 'attendable_id' => $staff->id, 'date' => $data['date']],
            [
                'status' => 'Present',
                'remarks' => $data['remarks'] ?? null,
                'photo_path' => $photoPath,
                'marked_by_id' => Auth::guard('erp')->id(),
            ]
        );

        return response()->json([
            'success' => true,
            'record' => [
                'id' => $attendance->id,
                'date' => $data['date'],
                'status' => $attendance->status,
                'remarks' => $attendance->remarks,
                'has_photo' => (bool) $attendance->photo_path,
            ],
        ]);
    }

    /** Attendance history across all staff, searchable by name/phone/employee id (History tab). */
    public function staffHistory(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'search' => 'nullable|string|max:100',
        ]);

        $from = $data['from'] ?? now()->startOfYear()->toDateString();
        $to = $data['to'] ?? now()->toDateString();

        $query = Attendance::query()
            ->where('attendable_type', 'staff')
            ->whereBetween('date', [$from, $to])
            ->orderByDesc('date')
            ->orderBy('attendable_id');

        if (! empty($data['search'])) {
            $term = '%' . trim($data['search']) . '%';
            $staffIds = Staff::query()
                ->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('employee_id', 'like', $term);
                })
                ->pluck('id');
            $query->whereIn('attendable_id', $staffIds);
        }

        $limit = min(max($request->integer('limit', 400), 1), 1000);
        $rows = $query->limit($limit)->get();
        $staffMembers = Staff::query()
            ->whereIn('id', $rows->pluck('attendable_id')->unique()->filter()->values())
            ->get(['id', 'name', 'phone', 'employee_id', 'department'])
            ->keyBy('id');

        $records = $rows->map(function (Attendance $row) use ($staffMembers) {
            $staff = $staffMembers->get($row->attendable_id);

            return [
                'id' => $row->id,
                'date' => $row->date instanceof \DateTimeInterface ? $row->date->format('Y-m-d') : (string) $row->date,
                'status' => $row->status,
                'remarks' => $row->remarks,
                'has_photo' => (bool) $row->photo_path,
                'staff' => $staff ? [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'phone' => $staff->phone,
                    'employee_id' => $staff->employee_id,
                    'department' => $staff->department,
                ] : null,
            ];
        });

        return response()->json(['from' => $from, 'to' => $to, 'records' => $records]);
    }

    /** Driver check-in for a date (Present + optional selfie/notes). */
    public function driverCheckIn(Request $request)
    {
        $data = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'date' => 'required|date',
            'remarks' => 'nullable|string|max:255',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $existing = Attendance::where('attendable_type', 'driver')
            ->where('attendable_id', $data['driver_id'])
            ->where('date', $data['date'])
            ->first();

        $photoPath = $existing?->photo_path;
        if ($request->hasFile('photo')) {
            if ($photoPath && Storage::disk('local')->exists($photoPath)) {
                Storage::disk('local')->delete($photoPath);
            }
            $photoPath = $request->file('photo')->store('attendance/drivers', 'local');
        }

        $attendance = Attendance::updateOrCreate(
            [
                'attendable_type' => 'driver',
                'attendable_id' => $data['driver_id'],
                'date' => $data['date'],
            ],
            [
                'status' => 'Present',
                'remarks' => $data['remarks'] ?? null,
                'photo_path' => $photoPath,
                'marked_by_id' => Auth::guard('erp')->id(),
            ]
        );

        return response()->json([
            'success' => true,
            'record' => [
                'id' => $attendance->id,
                'date' => $data['date'],
                'status' => $attendance->status,
                'remarks' => $attendance->remarks,
                'has_photo' => (bool) $attendance->photo_path,
            ],
        ]);
    }

    /** Attendance history across drivers (History tab). */
    public function driverHistory(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'search' => 'nullable|string|max:100',
        ]);

        $from = $data['from'] ?? now()->startOfYear()->toDateString();
        $to = $data['to'] ?? now()->toDateString();

        $query = Attendance::query()
            ->where('attendable_type', 'driver')
            ->whereBetween('date', [$from, $to])
            ->orderByDesc('date')
            ->orderBy('attendable_id');

        if (! empty($data['search'])) {
            $term = '%' . trim($data['search']) . '%';
            $driverIds = Driver::query()
                ->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('employee_id', 'like', $term);
                })
                ->pluck('id');
            $query->whereIn('attendable_id', $driverIds);
        }

        $limit = min(max($request->integer('limit', 400), 1), 1000);
        $rows = $query->limit($limit)->get();
        $drivers = Driver::query()
            ->whereIn('id', $rows->pluck('attendable_id')->unique()->filter()->values())
            ->get(['id', 'name', 'phone', 'employee_id', 'vehicle_no'])
            ->keyBy('id');

        $records = $rows->map(function (Attendance $row) use ($drivers) {
            $driver = $drivers->get($row->attendable_id);

            return [
                'id' => $row->id,
                'date' => $row->date instanceof \DateTimeInterface ? $row->date->format('Y-m-d') : (string) $row->date,
                'status' => $row->status,
                'remarks' => $row->remarks,
                'has_photo' => (bool) $row->photo_path,
                'driver' => $driver ? [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'phone' => $driver->phone,
                    'employee_id' => $driver->employee_id,
                    'vehicle_no' => $driver->vehicle_no,
                ] : null,
            ];
        });

        return response()->json(['from' => $from, 'to' => $to, 'records' => $records]);
    }

    /** Students assigned to routes driven by this driver. */
    public function driverRouteStudents(Request $request)
    {
        $data = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'date' => 'required|date',
        ]);

        $routeIds = TransportRoute::query()
            ->where('status', 'Active')
            ->whereHas('vehicle', fn ($q) => $q->where('driver_id', $data['driver_id']))
            ->pluck('id');

        if ($routeIds->isEmpty()) {
            return response()->json([
                'date' => $data['date'],
                'driver_id' => (int) $data['driver_id'],
                'notes' => null,
                'students' => [],
                'message' => 'No active routes assigned to this driver.',
            ]);
        }

        $transports = StudentTransport::query()
            ->with([
                'student:id,name,admission_no,school_class_id,section_id,status',
                'student.schoolClass:id,name',
                'student.section:id,name',
                'route:id,name',
                'routeStop:id,stop_name',
            ])
            ->whereIn('route_id', $routeIds)
            ->where('status', 'Active')
            ->get();

        $studentIds = $transports->pluck('student_id')->filter()->unique()->values();
        $marked = Attendance::where('attendable_type', 'student')
            ->where('date', $data['date'])
            ->whereIn('attendable_id', $studentIds)
            ->get()
            ->keyBy('attendable_id');

        $driverAttendance = Attendance::where('attendable_type', 'driver')
            ->where('attendable_id', $data['driver_id'])
            ->where('date', $data['date'])
            ->first();

        $students = $transports->map(function (StudentTransport $transport) use ($marked) {
            $student = $transport->student;
            if (! $student || $student->status !== 'Active') {
                return null;
            }
            $attendance = $marked->get($student->id);
            $meta = trim(($student->schoolClass->name ?? '') . '-' . ($student->section->name ?? ''), '-');

            return [
                'id' => $student->id,
                'name' => $student->name,
                'code' => $student->admission_no,
                'meta' => $meta !== '' ? $meta : null,
                'route' => $transport->route->name ?? null,
                'stop' => $transport->routeStop->stop_name ?? null,
                'status' => $attendance?->status,
                'remarks' => $attendance?->remarks,
            ];
        })->filter()->values();

        return response()->json([
            'date' => $data['date'],
            'driver_id' => (int) $data['driver_id'],
            'notes' => $driverAttendance?->remarks,
            'students' => $students,
        ]);
    }

    /** Save boarding attendance for a driver's route students. */
    public function storeDriverRouteStudents(Request $request)
    {
        $data = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'date' => 'required|date',
            'notes' => 'nullable|string|max:255',
            'records' => 'required|array|min:1',
            'records.*.attendable_id' => ['required', 'integer', Rule::exists('students', 'id')],
            'records.*.status' => ['required', Rule::in(['Present', 'Absent', 'Leave', 'Late', 'Half Day'])],
            'records.*.remarks' => 'nullable|string|max:255',
        ]);

        $now = now()->toDateTimeString();
        $userId = Auth::guard('erp')->id();
        $rows = [];
        foreach ($data['records'] as $record) {
            $rows[] = [
                'attendable_type' => 'student',
                'attendable_id' => $record['attendable_id'],
                'date' => $data['date'],
                'status' => $record['status'],
                'remarks' => $record['remarks'] ?? null,
                'marked_by_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            Attendance::upsert(
                $chunk,
                ['attendable_type', 'attendable_id', 'date'],
                ['status', 'remarks', 'marked_by_id', 'updated_at']
            );
        }

        if (array_key_exists('notes', $data)) {
            Attendance::upsert([[
                'attendable_type' => 'driver',
                'attendable_id' => $data['driver_id'],
                'date' => $data['date'],
                'status' => 'Present',
                'remarks' => $data['notes'],
                'marked_by_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['attendable_type', 'attendable_id', 'date'], ['status', 'remarks', 'marked_by_id', 'updated_at']);
        }

        return response()->json(['success' => true, 'count' => count($data['records'])]);
    }

    public function index(Request $request, string $type)
    {
        $modelClass = $this->attendableModelClass($type);
        $date = $request->query('date', now()->toDateString());

        $query = $modelClass::query();
        if (in_array($type, ['student', 'teacher'], true)) {
            $query->with('schoolClass:id,name');
        }
        if ($type === 'student') {
            // Require class (or teacher→class) so we never load the whole school roster.
            if (! $request->filled('school_class_id') && ! $request->filled('teacher_id')) {
                return response()->json(['date' => $date, 'people' => []]);
            }

            $query->with(['section:id,name'])->where('status', 'Active');
            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->query('branch_id'));
            }
            if ($request->filled('school_class_id')) {
                $query->where('school_class_id', $request->query('school_class_id'));
            }
            if ($request->filled('section_id')) {
                $query->where('section_id', $request->query('section_id'));
            }
            if ($request->filled('teacher_id')) {
                $teacher = Teacher::query()->find($request->query('teacher_id'), ['id', 'school_class_id']);
                if ($teacher?->school_class_id) {
                    $query->where('school_class_id', $teacher->school_class_id);
                } elseif (! $request->filled('school_class_id')) {
                    return response()->json(['date' => $date, 'people' => []]);
                }
            }
            $query->orderBy('roll_no')->orderBy('name');
        } else {
            $query->orderBy('name');
        }

        $people = $query->get();
        $ids = $people->pluck('id');

        $marked = $ids->isEmpty()
            ? collect()
            : Attendance::where('attendable_type', $type)
                ->where('date', $date)
                ->whereIn('attendable_id', $ids)
                ->get(['attendable_id', 'status', 'remarks'])
                ->keyBy('attendable_id');

        $rows = $people->map(function ($person) use ($marked, $type) {
            $attendance = $marked->get($person->id);

            return [
                'id' => $person->id,
                'name' => $person->name,
                'code' => $person->admission_no ?? $person->employee_id ?? null,
                'meta' => match ($type) {
                    'student' => trim(($person->schoolClass->name ?? '') . '-' . ($person->section->name ?? ''), '-') ?: null,
                    'teacher' => $person->schoolClass->name ?? null,
                    'staff' => $person->department,
                    'driver' => $person->vehicle_no,
                    default => null,
                },
                'status' => $attendance?->status,
                'remarks' => $attendance?->remarks,
            ];
        });

        return response()->json(['date' => $date, 'people' => $rows->values()]);
    }

    public function store(Request $request, string $type)
    {
        $modelClass = $this->attendableModelClass($type);
        $table = (new $modelClass())->getTable();

        $data = $request->validate([
            'date' => 'required|date',
            'records' => 'required|array|min:1',
            'records.*.attendable_id' => ['required', 'integer', Rule::exists($table, 'id')],
            'records.*.status' => ['required', Rule::in(['Present', 'Absent', 'Leave', 'Late', 'Half Day'])],
            'records.*.remarks' => 'nullable|string|max:255',
        ]);

        $now = now()->toDateTimeString();
        $userId = Auth::guard('erp')->id();
        $rows = [];
        foreach ($data['records'] as $record) {
            $rows[] = [
                'attendable_type' => $type,
                'attendable_id' => $record['attendable_id'],
                'date' => $data['date'],
                'status' => $record['status'],
                'remarks' => $record['remarks'] ?? null,
                'marked_by_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            Attendance::upsert(
                $chunk,
                ['attendable_type', 'attendable_id', 'date'],
                ['status', 'remarks', 'marked_by_id', 'updated_at']
            );
        }

        return response()->json(['success' => true, 'count' => count($data['records'])]);
    }

    /**
     * Month-wise marking grid (Mark tab's month mode): one row per student, one column per
     * calendar day of the chosen month, holidays flagged (never editable, never stored), and
     * a `present_to_date` running count per cell — same cumulative logic as studentHistory(),
     * but scoped to just this one month, mirroring how the Excel import treats each month's
     * "days present" figure as independent of every other month.
     */
    public function studentMonthGrid(Request $request)
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
            'branch_id' => 'nullable|exists:branches,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
        ]);

        $start = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $holidaySet = $this->holidaySetForRange($start, $end);

        $days = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $days[] = [
                'date' => $cursor->toDateString(),
                'day' => (int) $cursor->format('j'),
                'dow' => $cursor->format('D'),
                'is_holiday' => isset($holidaySet[$cursor->toDateString()]),
            ];
        }

        $students = Student::query()
            ->where('status', 'Active')
            ->when($data['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->where('school_class_id', $data['school_class_id'])
            ->when($data['section_id'] ?? null, fn ($q, $id) => $q->where('section_id', $id))
            ->orderBy('roll_no')->orderBy('name')
            ->get(['id', 'admission_no', 'name', 'roll_no']);

        $marksByStudent = Attendance::query()
            ->where('attendable_type', 'student')
            ->whereIn('attendable_id', $students->pluck('id'))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['attendable_id', 'date', 'status'])
            ->groupBy('attendable_id');

        $rows = $students->map(function (Student $student) use ($days, $marksByStudent) {
            $byDate = ($marksByStudent->get($student->id) ?? collect())
                ->keyBy(fn (Attendance $m) => $m->date->toDateString());

            $presentToDate = 0;
            $cells = [];
            foreach ($days as $day) {
                if ($day['is_holiday']) {
                    $cells[] = ['date' => $day['date'], 'status' => 'Holiday', 'present_to_date' => $presentToDate];

                    continue;
                }

                $status = $byDate->get($day['date'])?->status;
                if ($status === 'Present') {
                    $presentToDate++;
                }
                $cells[] = ['date' => $day['date'], 'status' => $status, 'present_to_date' => $presentToDate];
            }

            return [
                'id' => $student->id,
                'admission_no' => $student->admission_no,
                'name' => $student->name,
                'roll_no' => $student->roll_no,
                'cells' => $cells,
            ];
        });

        return response()->json([
            'month' => $data['month'],
            'days' => $days,
            'students' => $rows->values(),
        ]);
    }

    /** Bulk save for the month-wise marking grid. Holiday dates are silently dropped — never stored, same as the Excel import. */
    public function storeStudentMonth(Request $request)
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
            'records' => 'required|array|min:1',
            'records.*.attendable_id' => ['required', 'integer', Rule::exists('students', 'id')],
            'records.*.date' => 'required|date_format:Y-m-d',
            'records.*.status' => ['required', Rule::in(['Present', 'Absent'])],
        ]);

        $start = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $holidaySet = $this->holidaySetForRange($start, $end);

        $now = now()->toDateTimeString();
        $userId = Auth::guard('erp')->id();
        $rows = [];
        $skippedHolidays = 0;
        foreach ($data['records'] as $record) {
            $date = Carbon::createFromFormat('Y-m-d', $record['date']);
            if ($date->lt($start) || $date->gt($end)) {
                continue;
            }
            if (isset($holidaySet[$record['date']])) {
                $skippedHolidays++;

                continue;
            }
            $rows[] = [
                'attendable_type' => 'student',
                'attendable_id' => $record['attendable_id'],
                'date' => $record['date'],
                'status' => $record['status'],
                'remarks' => null,
                'marked_by_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            Attendance::upsert(
                $chunk,
                ['attendable_type', 'attendable_id', 'date'],
                ['status', 'remarks', 'marked_by_id', 'updated_at']
            );
        }

        return response()->json(['success' => true, 'saved' => count($rows), 'skipped_holidays' => $skippedHolidays]);
    }

    /** @param  array{branch_id?: int|null, school_class_id?: int|null, section_id?: int|null}  $filters */
    private function filteredStudentIds(array $filters): array
    {
        $query = Student::query()->where('status', 'Active');
        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }
        if (! empty($filters['school_class_id'])) {
            $query->where('school_class_id', $filters['school_class_id']);
        }
        if (! empty($filters['section_id'])) {
            $query->where('section_id', $filters['section_id']);
        }

        return $query->pluck('id')->all();
    }

    /**
     * Walks the fetched records in chronological order and attaches a `present_to_date`
     * running total (Present = +1, Absent/Leave/Late/Half Day = unchanged), interleaving
     * real Holiday dates (weekly-off + Holiday table, same source as the Excel import) that
     * fall within the span of the fetched records but have no attendance row of their own
     * — those never get written by import or the Mark tab, so without this they'd silently
     * vanish from the day-by-day picture instead of showing as a skipped, non-counted day.
     * Days with no record and no holiday (never marked) are left out entirely rather than
     * fabricated as Absent. Returned newest-first, matching the caller's prior ordering.
     *
     * @param  \Illuminate\Support\Collection<int, Attendance>  $records
     * @return list<array{date: string, status: string, remarks: ?string, present_to_date: int}>
     */
    /** @return array<string, true> Y-m-d => true, for every weekly-off / Holiday-table date in [start, end]. */
    private function holidaySetForRange(Carbon $start, Carbon $end): array
    {
        $offDays = is_array(WorkingDayConfig::current()->weekly_off_days ?? null)
            ? WorkingDayConfig::current()->weekly_off_days
            : [];
        $holidayDates = Holiday::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')->map(fn ($d) => $d->toDateString())->all();

        $set = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            if (in_array($cursor->format('l'), $offDays, true) || in_array($cursor->toDateString(), $holidayDates, true)) {
                $set[$cursor->toDateString()] = true;
            }
        }

        return $set;
    }

    private function withRunningPresentCount($records): array
    {
        if ($records->isEmpty()) {
            return [];
        }

        $chronological = $records->sortBy('date')->values();
        $spanStart = $chronological->first()->date->copy();
        $spanEnd = $chronological->last()->date->copy();
        $byDate = $chronological->keyBy(fn (Attendance $r) => $r->date->toDateString());
        $holidaySet = $this->holidaySetForRange($spanStart, $spanEnd);

        $timeline = [];
        $presentToDate = 0;
        for ($cursor = $spanStart->copy(); $cursor->lte($spanEnd); $cursor->addDay()) {
            $ymd = $cursor->toDateString();

            if (isset($holidaySet[$ymd])) {
                $timeline[] = ['date' => $ymd, 'status' => 'Holiday', 'remarks' => null, 'present_to_date' => $presentToDate];

                continue;
            }

            $row = $byDate->get($ymd);
            if (! $row) {
                continue;
            }

            if ($row->status === 'Present') {
                $presentToDate++;
            }
            $timeline[] = ['date' => $ymd, 'status' => $row->status, 'remarks' => $row->remarks, 'present_to_date' => $presentToDate];
        }

        return array_reverse($timeline);
    }

    private function statusLetter(string $status): string
    {
        return match ($status) {
            'Present' => 'P',
            'Absent' => 'A',
            'Leave' => 'L',
            'Late' => 'T',
            'Half Day' => 'H',
            default => '',
        };
    }
}
