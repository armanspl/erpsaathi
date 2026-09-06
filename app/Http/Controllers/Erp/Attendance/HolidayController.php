<?php

namespace App\Http\Controllers\Erp\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Holiday;
use App\Support\AttendanceCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $query = Holiday::query()->orderBy('date');
        AcademicSession::applyDateWindow($query, $request, 'date');

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $holiday = Holiday::create($data);
        AttendanceCache::forget();

        return response()->json($holiday, 201);
    }

    public function update(Request $request, Holiday $holiday)
    {
        $data = $this->validated($request, $holiday);

        $holiday->update($data);
        AttendanceCache::forget();

        return response()->json($holiday);
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();
        AttendanceCache::forget();

        return response()->json(['success' => true]);
    }

    private function validated(Request $request, ?Holiday $holiday = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'date' => ['required', 'date', Rule::unique('holidays', 'date')->ignore($holiday?->id)],
            'type' => 'nullable|string|max:100',
        ]);
    }
}
