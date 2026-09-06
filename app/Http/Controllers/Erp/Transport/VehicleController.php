<?php

namespace App\Http\Controllers\Erp\Transport;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index()
    {
        return response()->json(Vehicle::with('driver:id,name,employee_id')->orderBy('vehicle_no')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vehicle_no' => 'required|string|max:50|unique:vehicles,vehicle_no',
            'type' => 'required|string|max:50',
            'capacity' => 'required|integer|min:1',
            'driver_id' => 'nullable|exists:drivers,id',
            'status' => ['required', Rule::in(['Active', 'Under Maintenance', 'Inactive'])],
        ]);

        $vehicle = Vehicle::create($data);

        return response()->json($vehicle->load('driver:id,name,employee_id'), 201);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate([
            'vehicle_no' => ['required', 'string', 'max:50', Rule::unique('vehicles', 'vehicle_no')->ignore($vehicle->id)],
            'type' => 'required|string|max:50',
            'capacity' => 'required|integer|min:1',
            'driver_id' => 'nullable|exists:drivers,id',
            'status' => ['required', Rule::in(['Active', 'Under Maintenance', 'Inactive'])],
        ]);

        $vehicle->update($data);

        return response()->json($vehicle->load('driver:id,name,employee_id'));
    }

    public function destroy(Vehicle $vehicle)
    {
        $vehicle->delete();

        return response()->json(['success' => true]);
    }
}
