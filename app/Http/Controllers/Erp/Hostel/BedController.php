<?php

namespace App\Http\Controllers\Erp\Hostel;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BedController extends Controller
{
    public function index(Request $request)
    {
        $query = Bed::with('room:id,room_no');

        if ($request->filled('room_id')) {
            $query->where('room_id', $request->integer('room_id'));
        }

        return response()->json($query->orderBy('bed_no')->get());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $bed = Bed::create($data);

        return response()->json($bed->load('room:id,room_no'), 201);
    }

    public function update(Request $request, Bed $bed)
    {
        $data = $this->validated($request, $bed);

        $bed->update($data);

        return response()->json($bed->load('room:id,room_no'));
    }

    public function destroy(Bed $bed)
    {
        $bed->delete();

        return response()->json(['success' => true]);
    }

    private function validated(Request $request, ?Bed $bed = null): array
    {
        $roomId = $bed?->room_id ?? $request->input('room_id');

        return $request->validate([
            'room_id' => $bed ? 'sometimes' : 'required|exists:rooms,id',
            'bed_no' => [
                'required', 'string', 'max:50',
                Rule::unique('beds', 'bed_no')->where(fn ($q) => $q->where('room_id', $roomId))->ignore($bed?->id),
            ],
            'status' => ['sometimes', Rule::in(['Available', 'Occupied'])],
        ]);
    }
}
