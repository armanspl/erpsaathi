<?php

namespace App\Http\Controllers\Erp\People;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Support\PeopleCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class DriverController extends Controller
{
    public function index()
    {
        $payload = Cache::remember(PeopleCache::DRIVERS, PeopleCache::TTL, function () {
            return Driver::orderBy('name')->get();
        });

        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|string|max:50|unique:drivers,employee_id',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'license_no' => 'nullable|string|max:100',
            'vehicle_no' => 'nullable|string|max:50',
            'status' => 'required|in:active,inactive',
        ]);

        $driver = Driver::create($data);
        PeopleCache::forget();

        return response()->json($driver, 201);
    }

    public function update(Request $request, Driver $driver)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'string', 'max:50', Rule::unique('drivers', 'employee_id')->ignore($driver->id)],
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'license_no' => 'nullable|string|max:100',
            'vehicle_no' => 'nullable|string|max:50',
            'status' => 'required|in:active,inactive',
        ]);

        $driver->update($data);
        PeopleCache::forget();

        return response()->json($driver);
    }

    public function destroy(Driver $driver)
    {
        $driver->delete();
        PeopleCache::forget();

        return response()->json(['success' => true]);
    }
}
