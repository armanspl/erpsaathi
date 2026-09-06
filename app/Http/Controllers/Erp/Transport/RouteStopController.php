<?php

namespace App\Http\Controllers\Erp\Transport;

use App\Http\Controllers\Controller;
use App\Models\RouteStop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RouteStopController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['route_id' => 'required|exists:routes,id']);

        return response()->json(
            RouteStop::where('route_id', $request->integer('route_id'))->orderBy('sequence_no')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $stop = RouteStop::create($data);

        return response()->json($stop, 201);
    }

    public function update(Request $request, RouteStop $routeStop)
    {
        $data = $this->validated($request, $routeStop);

        $routeStop->update($data);

        return response()->json($routeStop);
    }

    public function destroy(RouteStop $routeStop)
    {
        $routeStop->delete();

        return response()->json(['success' => true]);
    }

    private function validated(Request $request, ?RouteStop $stop = null): array
    {
        $routeId = $stop?->route_id ?? $request->input('route_id');

        return $request->validate([
            'route_id' => $stop ? 'sometimes' : 'required|exists:routes,id',
            'stop_name' => 'required|string|max:255',
            'sequence_no' => [
                'required', 'integer', 'min:1',
                Rule::unique('route_stops', 'sequence_no')->where(fn ($q) => $q->where('route_id', $routeId))->ignore($stop?->id),
            ],
            'fare' => 'nullable|numeric|min:0',
            'pickup_time' => 'nullable|date_format:H:i',
            'drop_time' => 'nullable|date_format:H:i',
        ]);
    }
}
