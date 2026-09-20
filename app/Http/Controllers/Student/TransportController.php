<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\EnforcesPortalVisibility;
use App\Models\Student;
use App\Models\StudentTransport;
use App\Services\FeeBalanceService;
use Illuminate\Support\Facades\Auth;

class TransportController extends Controller
{
    use EnforcesPortalVisibility;

    public function show(FeeBalanceService $balance)
    {
        $this->abortIfModuleDisabled('transport');

        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $assignment = StudentTransport::query()
            ->where('student_id', $student->id)
            ->where('status', 'Active')
            ->with([
                'route:id,name,route_code,start_point,end_point,vehicle_id',
                'route.vehicle:id,vehicle_no,type,capacity,driver_id',
                'route.vehicle.driver:id,name,phone',
                'routeStop:id,route_id,stop_name,sequence_no,fare,km,pickup_time,drop_time',
            ])
            ->first();

        if (! $assignment) {
            return response()->json(['enrolled' => false]);
        }

        [$fare] = $balance->transportFareAndHead($student);

        return response()->json([
            'enrolled' => true,
            'start_date' => $assignment->start_date?->format('Y-m-d'),
            'route' => $assignment->route ? [
                'name' => $assignment->route->name,
                'route_code' => $assignment->route->route_code,
                'start_point' => $assignment->route->start_point,
                'end_point' => $assignment->route->end_point,
            ] : null,
            'vehicle' => $assignment->route?->vehicle ? [
                'vehicle_no' => $assignment->route->vehicle->vehicle_no,
                'type' => $assignment->route->vehicle->type,
                'capacity' => $assignment->route->vehicle->capacity,
                'driver_name' => $assignment->route->vehicle->driver?->name,
                'driver_mobile' => $assignment->route->vehicle->driver?->phone,
            ] : null,
            'pickup_point' => $assignment->routeStop ? [
                'stop_name' => $assignment->routeStop->stop_name,
                'sequence_no' => $assignment->routeStop->sequence_no,
                'km' => $assignment->routeStop->km,
                'pickup_time' => $assignment->routeStop->pickup_time,
                'drop_time' => $assignment->routeStop->drop_time,
            ] : null,
            'fee' => [
                'monthly_fare' => (float) $fare,
            ],
        ]);
    }
}
