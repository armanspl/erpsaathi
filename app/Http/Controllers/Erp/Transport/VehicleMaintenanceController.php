<?php

namespace App\Http\Controllers\Erp\Transport;

use App\Http\Controllers\Controller;
use App\Models\VehicleMaintenance;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleMaintenanceController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['vehicle_id' => 'required|exists:vehicles,id']);

        return response()->json(
            VehicleMaintenance::where('vehicle_id', $request->integer('vehicle_id'))->orderByDesc('date')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'type' => ['required', Rule::in(['Service', 'Repair', 'Inspection', 'Other'])],
            'description' => 'nullable|string|max:255',
            'cost' => 'required|numeric|min:0',
            'date' => 'required|date',
            'next_due_date' => 'nullable|date|after_or_equal:date',
        ]);

        $record = VehicleMaintenance::create($data);

        return response()->json($record, 201);
    }

    public function update(Request $request, VehicleMaintenance $vehicleMaintenance)
    {
        $data = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'type' => ['required', Rule::in(['Service', 'Repair', 'Inspection', 'Other'])],
            'description' => 'nullable|string|max:255',
            'cost' => 'required|numeric|min:0',
            'date' => 'required|date',
            'next_due_date' => 'nullable|date|after_or_equal:date',
        ]);

        $vehicleMaintenance->update($data);

        return response()->json($vehicleMaintenance);
    }

    public function destroy(VehicleMaintenance $vehicleMaintenance)
    {
        $vehicleMaintenance->delete();

        return response()->json(['success' => true]);
    }
}
