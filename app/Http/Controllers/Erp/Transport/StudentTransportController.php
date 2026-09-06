<?php

namespace App\Http\Controllers\Erp\Transport;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\RouteStop;
use App\Models\StudentTransport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentTransportController extends Controller
{
    public function index(Request $request)
    {
        // active_ids=1 — People Students table only needs who has Active transport.
        if ($request->boolean('active_ids')) {
            return response()->json(
                StudentTransport::query()
                    ->where('status', 'Active')
                    ->pluck('student_id')
            );
        }

        // Single-student lookup for Fee Receipt — skip session filter + heavy graphs.
        if ($request->filled('student_id')) {
            $rows = StudentTransport::query()
                ->where('student_id', $request->integer('student_id'))
                ->with([
                    'route:id,name',
                    'routeStop:id,stop_name,fare',
                ])
                ->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'student_id', 'route_id', 'route_stop_id', 'status', 'start_date', 'fee_start_month']);

            return response()->json($rows);
        }

        $query = StudentTransport::with([
            'student:id,name,admission_no,school_class_id',
            'student.schoolClass:id,name',
            'route:id,name,vehicle_id',
            'route.vehicle:id,vehicle_no,driver_id',
            'route.vehicle.driver:id,name,phone',
            'routeStop:id,stop_name,fare',
        ]);

        if (! AcademicSession::requestWantsAll($request)) {
            $query->whereHas('student', function ($q) use ($request) {
                AcademicSession::applyStudentSessionFilter($q, $request);
            });
        }

        return response()->json($query->orderByDesc('id')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id', Rule::unique('student_transports', 'student_id')],
            'route_id' => 'required|exists:routes,id',
            'route_stop_id' => 'required|exists:route_stops,id',
            'start_date' => 'required|date',
            'fee_start_month' => 'nullable|date_format:Y-m',
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $this->assertStopBelongsToRoute($data['route_id'], $data['route_stop_id']);
        $data['fee_start_month'] = $data['fee_start_month']
            ?? substr((string) $data['start_date'], 0, 7);

        $assignment = StudentTransport::create($data);

        return response()->json($assignment->load(['student:id,name,admission_no', 'route:id,name', 'routeStop:id,stop_name,fare']), 201);
    }

    public function update(Request $request, StudentTransport $studentTransport)
    {
        $data = $request->validate([
            'route_id' => 'required|exists:routes,id',
            'route_stop_id' => 'required|exists:route_stops,id',
            'start_date' => 'required|date',
            'fee_start_month' => 'nullable|date_format:Y-m',
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $this->assertStopBelongsToRoute($data['route_id'], $data['route_stop_id']);
        if (empty($data['fee_start_month'])) {
            $data['fee_start_month'] = substr((string) $data['start_date'], 0, 7);
        }

        $studentTransport->update($data);

        return response()->json($studentTransport->load(['student:id,name,admission_no', 'route:id,name', 'routeStop:id,stop_name,fare']));
    }

    public function destroy(StudentTransport $studentTransport)
    {
        $studentTransport->delete();

        return response()->json(['success' => true]);
    }

    private function assertStopBelongsToRoute(int $routeId, int $routeStopId): void
    {
        if (! RouteStop::where('id', $routeStopId)->where('route_id', $routeId)->exists()) {
            throw ValidationException::withMessages(['route_stop_id' => 'The selected stop does not belong to the selected route.']);
        }
    }
}
