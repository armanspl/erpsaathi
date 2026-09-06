<?php

namespace App\Http\Controllers\Erp\Transport;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\FuelLog;
use Illuminate\Http\Request;

class FuelLogController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['vehicle_id' => 'required|exists:vehicles,id']);

        $query = FuelLog::where('vehicle_id', $request->integer('vehicle_id'));
        AcademicSession::applyDateWindow($query, $request, 'date');

        return response()->json(
            $query->orderByDesc('date')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'date' => 'required|date',
            'liters' => 'required|numeric|min:0.01',
            'cost' => 'required|numeric|min:0.01',
            'odometer_reading' => 'nullable|integer|min:0',
            'remarks' => 'nullable|string|max:255',
        ]);

        $log = FuelLog::create($data);

        return response()->json($log, 201);
    }

    public function update(Request $request, FuelLog $fuelLog)
    {
        $data = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'date' => 'required|date',
            'liters' => 'required|numeric|min:0.01',
            'cost' => 'required|numeric|min:0.01',
            'odometer_reading' => 'nullable|integer|min:0',
            'remarks' => 'nullable|string|max:255',
        ]);

        $fuelLog->update($data);

        return response()->json($fuelLog);
    }

    public function destroy(FuelLog $fuelLog)
    {
        $fuelLog->delete();

        return response()->json(['success' => true]);
    }
}
