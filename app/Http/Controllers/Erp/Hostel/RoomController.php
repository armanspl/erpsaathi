<?php

namespace App\Http\Controllers\Erp\Hostel;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function index()
    {
        return response()->json(Room::withCount(['beds', 'beds as occupied_beds_count' => fn ($q) => $q->where('status', 'Occupied')])->orderBy('room_no')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'room_no' => 'required|string|max:50|unique:rooms,room_no',
            'type' => 'required|string|max:50',
            'capacity' => 'required|integer|min:1',
            'monthly_fee' => 'required|numeric|min:0',
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $room = Room::create($data);

        return response()->json($room, 201);
    }

    public function update(Request $request, Room $room)
    {
        $data = $request->validate([
            'room_no' => ['required', 'string', 'max:50', Rule::unique('rooms', 'room_no')->ignore($room->id)],
            'type' => 'required|string|max:50',
            'capacity' => 'required|integer|min:1',
            'monthly_fee' => 'required|numeric|min:0',
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $room->update($data);

        return response()->json($room);
    }

    public function destroy(Room $room)
    {
        $room->delete();

        return response()->json(['success' => true]);
    }
}
