<?php

namespace Database\Seeders;

use App\Models\Bed;
use App\Models\HostelAllocation;
use App\Models\HostelFee;
use App\Models\HostelVisitor;
use App\Models\Room;
use App\Models\Student;
use Illuminate\Database\Seeder;

class HostelSeeder extends Seeder
{
    public function run(): void
    {
        $room = Room::firstOrCreate(
            ['room_no' => 'R-101'],
            ['type' => 'Double', 'capacity' => 2, 'monthly_fee' => 3000, 'status' => 'Active']
        );

        $bedA = Bed::firstOrCreate(['room_id' => $room->id, 'bed_no' => 'A'], ['status' => 'Available']);
        Bed::firstOrCreate(['room_id' => $room->id, 'bed_no' => 'B'], ['status' => 'Available']);

        $student = Student::where('admission_no', 'ADM-1001')->first();
        if ($student) {
            $allocation = HostelAllocation::firstOrCreate(
                ['student_id' => $student->id, 'status' => 'Active'],
                ['bed_id' => $bedA->id, 'start_date' => '2026-04-01']
            );
            if ($allocation->wasRecentlyCreated) {
                $bedA->update(['status' => 'Occupied']);
            }

            HostelFee::firstOrCreate(
                ['hostel_allocation_id' => $allocation->id, 'period' => '2026-06'],
                ['amount' => 3000, 'status' => 'Paid', 'paid_on' => '2026-06-05', 'payment_mode' => 'Cash']
            );

            HostelVisitor::firstOrCreate(
                ['student_id' => $student->id, 'visitor_name' => 'Ramesh Sharma', 'visit_date' => '2026-07-20'],
                ['relation' => 'Father', 'purpose' => 'Weekly visit', 'in_time' => '16:00', 'out_time' => '17:30']
            );
        }
    }
}
