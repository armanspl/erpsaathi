<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\FuelLog;
use App\Models\RouteStop;
use App\Models\Student;
use App\Models\StudentTransport;
use App\Models\TransportRoute;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleMaintenance;
use Illuminate\Database\Seeder;

class TransportSeeder extends Seeder
{
    public function run(): void
    {
        $driver = Driver::where('employee_id', 'DRV-001')->first();

        $vehicle = Vehicle::firstOrCreate(
            ['vehicle_no' => 'RJ-14-GA-3321'],
            ['type' => 'Bus', 'capacity' => 40, 'driver_id' => $driver?->id, 'status' => 'Active']
        );

        $route = TransportRoute::firstOrCreate(
            ['name' => 'Route 1 — City Center'],
            ['start_point' => 'School Campus', 'end_point' => 'Sector 15 Circle', 'vehicle_id' => $vehicle->id, 'status' => 'Active']
        );

        $stop1 = RouteStop::firstOrCreate(
            ['route_id' => $route->id, 'sequence_no' => 1],
            ['stop_name' => 'Sector 12', 'fare' => 500, 'pickup_time' => '07:00', 'drop_time' => '14:30']
        );
        RouteStop::firstOrCreate(
            ['route_id' => $route->id, 'sequence_no' => 2],
            ['stop_name' => 'Sector 15', 'fare' => 600, 'pickup_time' => '07:15', 'drop_time' => '14:15']
        );

        VehicleMaintenance::firstOrCreate(
            ['vehicle_id' => $vehicle->id, 'type' => 'Service', 'date' => '2026-07-05'],
            ['description' => 'Routine engine service', 'cost' => 3500, 'next_due_date' => '2026-10-05']
        );

        FuelLog::firstOrCreate(
            ['vehicle_id' => $vehicle->id, 'date' => '2026-07-10'],
            ['liters' => 40, 'cost' => 4200, 'odometer_reading' => 15200]
        );

        VehicleDocument::firstOrCreate(
            ['vehicle_id' => $vehicle->id, 'document_type' => 'Insurance'],
            ['document_no' => 'INS-2026-8891', 'issue_date' => '2026-01-01', 'expiry_date' => '2027-01-01']
        );

        $student = Student::where('admission_no', 'ADM-1001')->first();
        if ($student) {
            StudentTransport::firstOrCreate(
                ['student_id' => $student->id],
                ['route_id' => $route->id, 'route_stop_id' => $stop1->id, 'start_date' => '2026-04-01', 'status' => 'Active']
            );
        }
    }
}
