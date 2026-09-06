<?php

namespace App\Http\Controllers\Erp\Transport;

use App\Http\Controllers\Controller;
use App\Models\TransportRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RouteController extends Controller
{
    public function index(Request $request)
    {
        $query = TransportRoute::query()
            ->with($request->boolean('with_stops') ? ['branch:id,name', 'stops', 'vehicle:id,vehicle_no,driver_id'] : ['vehicle:id,vehicle_no,driver_id'])
            ->withCount('stops')
            ->orderBy('name');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        return response()->json($query->get());
    }

    /** Update stop fares on existing transport routes (Fee Structure → Transport). */
    public function updateFees(Request $request)
    {
        $data = $request->validate([
            'routes' => 'required|array|min:1',
            'routes.*.id' => 'required|integer|exists:routes,id',
            'routes.*.stops' => 'required|array|min:1',
            'routes.*.stops.*.id' => 'nullable|integer|exists:route_stops,id',
            'routes.*.stops.*.stop_name' => 'required|string|max:255',
            'routes.*.stops.*.fare' => 'nullable|numeric|min:0',
            'routes.*.stops.*.km' => 'nullable|numeric|min:0',
        ]);

        $routeIds = collect($data['routes'])->pluck('id')->unique()->values();

        DB::transaction(function () use ($data) {
            foreach ($data['routes'] as $routeData) {
                $route = TransportRoute::findOrFail($routeData['id']);
                $keptIds = [];

                foreach (array_values($routeData['stops']) as $index => $stop) {
                    $payload = [
                        'stop_name' => $stop['stop_name'],
                        'sequence_no' => $index + 1,
                        'fare' => $stop['fare'] ?? 0,
                        'km' => $stop['km'] ?? null,
                    ];

                    if (! empty($stop['id'])) {
                        $existing = $route->stops()->whereKey($stop['id'])->firstOrFail();
                        $existing->update($payload);
                        $keptIds[] = $existing->id;
                    } else {
                        $keptIds[] = $route->stops()->create($payload)->id;
                    }
                }

                $route->stops()->whereNotIn('id', $keptIds)->delete();
            }
        });

        return response()->json(
            TransportRoute::with(['branch:id,name', 'stops', 'vehicle:id,vehicle_no'])
                ->whereIn('id', $routeIds)
                ->orderBy('name')
                ->get()
        );
    }

    /** Remove all stop fares for a route (route itself stays in Transport Management). */
    public function clearFees(TransportRoute $route)
    {
        $route->stops()->update(['fare' => 0, 'km' => null]);

        return response()->json(['success' => true]);
    }

    /** @deprecated Use updateFees — full replace sync kept for legacy callers. */
    public function sync(Request $request)
    {
        $data = $request->validate([
            'replace_all' => 'nullable|boolean',
            'routes' => 'present|array',
            'routes.*.id' => 'nullable|integer|exists:routes,id',
            'routes.*.branch_id' => 'nullable|exists:branches,id',
            'routes.*.name' => 'required|string|max:255',
            'routes.*.route_code' => 'nullable|string|max:50',
            'routes.*.stops' => 'array',
            'routes.*.stops.*.stop_name' => 'required|string|max:255',
            'routes.*.stops.*.fare' => 'nullable|numeric|min:0',
            'routes.*.stops.*.km' => 'nullable|numeric|min:0',
        ]);

        $routes = DB::transaction(function () use ($data, $request) {
            $keptIds = [];

            foreach ($data['routes'] as $routeData) {
                $stops = $routeData['stops'] ?? [];
                $start = $stops[0]['stop_name'] ?? '';
                $end = $stops !== [] ? ($stops[count($stops) - 1]['stop_name'] ?? '') : '';

                $payload = [
                    'branch_id' => $routeData['branch_id'] ?? null,
                    'name' => $routeData['name'],
                    'route_code' => $routeData['route_code'] ?? null,
                    'start_point' => $start,
                    'end_point' => $end,
                    'status' => 'Active',
                ];

                if (! empty($routeData['id'])) {
                    $route = TransportRoute::findOrFail($routeData['id']);
                    $route->update($payload);
                } else {
                    $route = TransportRoute::create($payload);
                }

                $keptIds[] = $route->id;
                $route->stops()->delete();

                foreach (array_values($stops) as $index => $stop) {
                    $route->stops()->create([
                        'stop_name' => $stop['stop_name'],
                        'sequence_no' => $index + 1,
                        'fare' => $stop['fare'] ?? 0,
                        'km' => $stop['km'] ?? null,
                    ]);
                }
            }

            if ($request->boolean('replace_all', true)) {
                if ($keptIds === []) {
                    TransportRoute::query()->delete();
                } else {
                    TransportRoute::whereNotIn('id', $keptIds)->delete();
                }
            }

            return TransportRoute::with(['branch:id,name', 'stops'])
                ->whereIn('id', $keptIds)
                ->orderBy('name')
                ->get();
        });

        return response()->json($routes);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'name' => 'required|string|max:255',
            'route_code' => 'nullable|string|max:50',
            'start_point' => 'nullable|string|max:255',
            'end_point' => 'nullable|string|max:255',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $route = TransportRoute::create($data);

        return response()->json($route->load(['branch:id,name', 'vehicle:id,vehicle_no,driver_id']), 201);
    }

    public function update(Request $request, TransportRoute $route)
    {
        $data = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'name' => 'required|string|max:255',
            'route_code' => 'nullable|string|max:50',
            'start_point' => 'nullable|string|max:255',
            'end_point' => 'nullable|string|max:255',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $route->update($data);

        return response()->json($route->load(['branch:id,name', 'vehicle:id,vehicle_no,driver_id']));
    }

    public function destroy(TransportRoute $route)
    {
        $route->delete();

        return response()->json(['success' => true]);
    }
}
