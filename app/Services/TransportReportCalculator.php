<?php

namespace App\Services;

use App\Models\FuelLog;
use App\Models\RouteStop;
use App\Models\StudentTransport;
use App\Models\TransportRoute;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleMaintenance;

class TransportReportCalculator
{
    /**
     * Fleet/route utilization snapshot, computed live from the operational tables rather
     * than stored — mirrors FeeCalculator / ExamResultCalculator.
     */
    public static function summary(): array
    {
        $routes = TransportRoute::with('vehicle:id,vehicle_no')->withCount('stops')->get();
        $assignments = StudentTransport::where('status', 'Active')->get();
        $assignmentsByRoute = $assignments->groupBy('route_id');

        $routeRows = $routes->map(function (TransportRoute $route) use ($assignmentsByRoute) {
            $students = $assignmentsByRoute->get($route->id, collect());
            $fareTotal = $students->sum(fn (StudentTransport $st) => (float) (RouteStop::find($st->route_stop_id)?->fare ?? 0));

            return [
                'route_id' => $route->id,
                'name' => $route->name,
                'vehicle' => $route->vehicle->vehicle_no ?? null,
                'stops_count' => $route->stops_count,
                'students_count' => $students->count(),
                'monthly_fare_total' => round($fareTotal, 2),
            ];
        });

        $upcomingDocs = VehicleDocument::with('vehicle:id,vehicle_no')
            ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->orderBy('expiry_date')
            ->get()
            ->map(fn (VehicleDocument $d) => [
                'vehicle' => $d->vehicle->vehicle_no,
                'document_type' => $d->document_type,
                'expiry_date' => $d->expiry_date->toDateString(),
            ]);

        $upcomingMaintenance = VehicleMaintenance::with('vehicle:id,vehicle_no')
            ->whereNotNull('next_due_date')
            ->whereBetween('next_due_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->orderBy('next_due_date')
            ->get()
            ->map(fn (VehicleMaintenance $m) => [
                'vehicle' => $m->vehicle->vehicle_no,
                'type' => $m->type,
                'next_due_date' => $m->next_due_date->toDateString(),
            ]);

        return [
            'total_vehicles' => Vehicle::count(),
            'active_vehicles' => Vehicle::where('status', 'Active')->count(),
            'total_routes' => $routes->count(),
            'students_using_transport' => $assignments->count(),
            'total_monthly_fare' => round($routeRows->sum('monthly_fare_total'), 2),
            'fuel_cost_this_month' => round((float) FuelLog::whereBetween('date', [now()->startOfMonth()->toDateString(), now()->toDateString()])->sum('cost'), 2),
            'maintenance_cost_this_month' => round((float) VehicleMaintenance::whereBetween('date', [now()->startOfMonth()->toDateString(), now()->toDateString()])->sum('cost'), 2),
            'routes' => $routeRows->values(),
            'upcoming_document_expiries' => $upcomingDocs,
            'upcoming_maintenance' => $upcomingMaintenance,
        ];
    }
}
