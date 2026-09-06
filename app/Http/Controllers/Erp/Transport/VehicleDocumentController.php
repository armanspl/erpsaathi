<?php

namespace App\Http\Controllers\Erp\Transport;

use App\Http\Controllers\Controller;
use App\Models\VehicleDocument;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleDocumentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['vehicle_id' => 'required|exists:vehicles,id']);

        return response()->json(
            VehicleDocument::where('vehicle_id', $request->integer('vehicle_id'))->orderBy('expiry_date')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'document_type' => ['required', Rule::in(['RC', 'Insurance', 'Permit', 'Fitness', 'PUC', 'Other'])],
            'document_no' => 'nullable|string|max:100',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'required|date',
            'remarks' => 'nullable|string|max:255',
        ]);

        $document = VehicleDocument::create($data);

        return response()->json($document, 201);
    }

    public function update(Request $request, VehicleDocument $vehicleDocument)
    {
        $data = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'document_type' => ['required', Rule::in(['RC', 'Insurance', 'Permit', 'Fitness', 'PUC', 'Other'])],
            'document_no' => 'nullable|string|max:100',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'required|date',
            'remarks' => 'nullable|string|max:255',
        ]);

        $vehicleDocument->update($data);

        return response()->json($vehicleDocument);
    }

    public function destroy(VehicleDocument $vehicleDocument)
    {
        $vehicleDocument->delete();

        return response()->json(['success' => true]);
    }
}
