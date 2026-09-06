<?php

namespace App\Http\Controllers\Erp\Exams;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\SeatPlanRoom;
use App\Models\SeatPlanSheet;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SeatPlanController extends Controller
{
    private const SHEET_RELATIONS = ['exam:id,name', 'branch:id,name', 'schoolClass:id,name', 'section:id,name'];

    public function index(Request $request)
    {
        $query = SeatPlanSheet::with(self::SHEET_RELATIONS)->withCount('rooms');

        if ($request->filled('exam_id')) {
            $query->where('exam_id', $request->integer('exam_id'));
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        $currentSession = AcademicSession::fromRequest(request(), true)?->name;

        return response()->json(
            $query->orderByDesc('created_at')->get()->map(function (SeatPlanSheet $sheet) use ($currentSession) {
                $seatsPerRoom = $sheet->rows * $sheet->columns * $sheet->students_per_bench;
                $assigned = SeatPlanRoom::where('seat_plan_sheet_id', $sheet->id)
                    ->join('seat_plan_seats', 'seat_plan_seats.seat_plan_room_id', '=', 'seat_plan_rooms.id')
                    ->whereNotNull('seat_plan_seats.student_id')
                    ->count();

                return [
                    ...$sheet->toArray(),
                    'session_name' => $currentSession,
                    'capacity' => $seatsPerRoom * $sheet->rooms_count,
                    'assigned' => $assigned,
                    'label' => trim(($sheet->exam->name ?? '') . ' — ' . ($sheet->branch->name ?? '')),
                ];
            })
        );
    }

    public function show(SeatPlanSheet $seatPlanSheet)
    {
        return response()->json($this->present($seatPlanSheet));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'branch_id' => 'required|exists:branches,id',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'rows' => 'required|integer|min:1|max:50',
            'columns' => 'required|integer|min:1|max:50',
            'students_per_bench' => 'required|integer|min:1|max:6',
            'room_count' => 'nullable|integer|min:1|max:200',
            'fill_order' => ['required', Rule::in(['roll_number', 'admission_no', 'name'])],
            'separate_by_gender' => 'boolean',
            'room_prefix' => 'nullable|string|max:50',
            'room_suffix' => 'nullable|string|max:50',
        ]);

        $sheet = DB::transaction(function () use ($data) {
            $sheetData = $data;
            unset($sheetData['room_count']);

            $seatsPerRoom = $data['rows'] * $data['columns'] * $data['students_per_bench'];
            $studentsQuery = $this->scopedStudents($data);

            if (! empty($data['separate_by_gender'])) {
                $boys = $this->sortByFillOrder((clone $studentsQuery)->where('gender', 'Male'), $data['fill_order'])->get();
                $girls = $this->sortByFillOrder((clone $studentsQuery)->where('gender', 'Female'), $data['fill_order'])->get();
                $others = $this->sortByFillOrder((clone $studentsQuery)->where(function ($q) {
                    $q->whereNotIn('gender', ['Male', 'Female'])->orWhereNull('gender');
                }), $data['fill_order'])->get();
                // Students without a recognized gender are folded into whichever group is currently smaller.
                if ($boys->count() <= $girls->count()) {
                    $boys = $boys->merge($others);
                } else {
                    $girls = $girls->merge($others);
                }

                $boysRooms = $boys->isEmpty() ? 0 : max(1, (int) ceil($boys->count() / $seatsPerRoom));
                $girlsRooms = $girls->isEmpty() ? 0 : max(1, (int) ceil($girls->count() / $seatsPerRoom));
                $roomCount = max(1, $boysRooms + $girlsRooms);

                $sheet = SeatPlanSheet::create([...$sheetData, 'room_count' => $roomCount]);

                $roomNumber = 1;
                $roomNumber = $this->createRoomsAndSeats($sheet, $boys, $boysRooms, $roomNumber, 'Boys');
                $this->createRoomsAndSeats($sheet, $girls, $girlsRooms, $roomNumber, 'Girls');
            } else {
                $students = $this->sortByFillOrder($studentsQuery, $data['fill_order'])->get();
                $roomCount = $data['room_count'] ?? max(1, (int) ceil(max($students->count(), 1) / $seatsPerRoom));

                $sheet = SeatPlanSheet::create([...$sheetData, 'room_count' => $roomCount]);
                $this->createRoomsAndSeats($sheet, $students, $roomCount, 1, null);
            }

            return $sheet;
        });

        return response()->json($this->present($sheet->fresh()), 201);
    }

    public function destroy(SeatPlanSheet $seatPlanSheet)
    {
        $seatPlanSheet->delete();

        return response()->json(['success' => true]);
    }

    public function exportRoomCsv(SeatPlanRoom $seatPlanRoom): StreamedResponse
    {
        $seatPlanRoom->load(['seats.student:id,name,admission_no', 'sheet']);
        $filename = str_replace(' ', '-', strtolower($seatPlanRoom->name)) . '.csv';

        return response()->streamDownload(function () use ($seatPlanRoom) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Seat No', 'Row', 'Column', 'Student', 'Admission No', 'Status']);
            foreach ($seatPlanRoom->seats as $seat) {
                fputcsv($out, [
                    'S' . $seat->seat_no,
                    $seat->row_no,
                    $seat->col_no,
                    $seat->student->name ?? '',
                    $seat->student->admission_no ?? '',
                    $seat->student_id ? 'Assigned' : 'Vacant',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return \Illuminate\Database\Eloquent\Builder */
    private function scopedStudents(array $data)
    {
        $query = Student::query()
            ->where('status', 'Active')
            ->where('branch_id', $data['branch_id'])
            ->when(! empty($data['school_class_id']), fn ($q) => $q->where('school_class_id', $data['school_class_id']))
            ->when(! empty($data['section_id']), fn ($q) => $q->where('section_id', $data['section_id']));

        AcademicSession::applyStudentSessionFilter($query);

        return $query;
    }

    private function sortByFillOrder($query, string $fillOrder)
    {
        return match ($fillOrder) {
            'admission_no' => $query->orderBy('admission_no'),
            'name' => $query->orderBy('name'),
            default => $query->orderBy('roll_no')->orderBy('name'),
        };
    }

    /** Creates $roomCount rooms (numbered starting at $startingRoomNumber) and fills their seats from $students in order. Returns the next free room number. */
    private function createRoomsAndSeats(SeatPlanSheet $sheet, $students, int $roomCount, int $startingRoomNumber, ?string $gender): int
    {
        $seatsPerRoom = $sheet->rows * $sheet->columns * $sheet->students_per_bench;
        $studentQueue = $students instanceof \Illuminate\Support\Collection ? $students->values() : collect($students)->values();
        $cursor = 0;
        $globalSeatNo = SeatPlanRoom::where('seat_plan_sheet_id', $sheet->id)
            ->join('seat_plan_seats', 'seat_plan_seats.seat_plan_room_id', '=', 'seat_plan_rooms.id')
            ->max('seat_no') ?? 0;

        for ($i = 0; $i < $roomCount; $i++) {
            $roomNumber = $startingRoomNumber + $i;
            $room = SeatPlanRoom::create([
                'seat_plan_sheet_id' => $sheet->id,
                'name' => trim(($sheet->room_prefix ?: 'Room') . ' ' . $roomNumber . ($sheet->room_suffix ?: '')),
                'gender' => $gender,
                'sort_order' => $roomNumber,
            ]);

            for ($r = 1; $r <= $sheet->rows; $r++) {
                for ($c = 1; $c <= $sheet->columns; $c++) {
                    for ($slot = 1; $slot <= $sheet->students_per_bench; $slot++) {
                        $globalSeatNo++;
                        $studentId = null;
                        if ($cursor < $studentQueue->count()) {
                            $studentId = $studentQueue[$cursor]->id;
                            $cursor++;
                        }
                        $room->seats()->create([
                            'seat_no' => $globalSeatNo,
                            'row_no' => $r,
                            'col_no' => $c,
                            'bench_slot' => $slot,
                            'student_id' => $studentId,
                        ]);
                    }
                }
            }
        }

        return $startingRoomNumber + $roomCount;
    }

    private function present(SeatPlanSheet $sheet): array
    {
        $sheet->loadMissing([...self::SHEET_RELATIONS, 'rooms.seats.student:id,name,admission_no']);
        $currentSession = AcademicSession::fromRequest(request(), true)?->name;
        $seatsPerRoom = $sheet->rows * $sheet->columns * $sheet->students_per_bench;

        $assigned = 0;
        $rooms = $sheet->rooms->map(function (SeatPlanRoom $room) use (&$assigned) {
            $roomAssigned = $room->seats->filter(fn ($s) => $s->student_id)->count();
            $assigned += $roomAssigned;

            return [
                'id' => $room->id,
                'name' => $room->name,
                'gender' => $room->gender,
                'assigned' => $roomAssigned,
                'seats' => $room->seats->map(fn ($s) => [
                    'id' => $s->id,
                    'seat_no' => $s->seat_no,
                    'row_no' => $s->row_no,
                    'col_no' => $s->col_no,
                    'bench_slot' => $s->bench_slot,
                    'student' => $s->student ? ['id' => $s->student->id, 'name' => $s->student->name, 'admission_no' => $s->student->admission_no] : null,
                ])->values(),
            ];
        })->values();

        return [
            ...collect($sheet->toArray())->except('rooms')->all(),
            'session_name' => $currentSession,
            'label' => trim(($sheet->exam->name ?? '') . ' — ' . ($sheet->branch->name ?? '')),
            'seats_per_room' => $seatsPerRoom,
            'capacity' => $seatsPerRoom * $rooms->count(),
            'assigned' => $assigned,
            'rooms' => $rooms,
        ];
    }
}
