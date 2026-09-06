<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\HostelAllocation;
use App\Models\HostelFee;
use App\Models\HostelVisitor;
use App\Models\Room;

class HostelReportCalculator
{
    /** Hostel occupancy/revenue snapshot, computed live from rooms/beds/allocations/fees. */
    public static function summary(): array
    {
        $totalBeds = Bed::count();
        $occupiedBeds = Bed::where('status', 'Occupied')->count();

        $roomOccupancy = Room::withCount(['beds', 'beds as occupied_beds_count' => fn ($q) => $q->where('status', 'Occupied')])
            ->orderBy('room_no')
            ->get()
            ->map(fn (Room $room) => [
                'room_no' => $room->room_no,
                'capacity' => $room->capacity,
                'beds_count' => $room->beds_count,
                'occupied_beds_count' => $room->occupied_beds_count,
            ]);

        $thisMonthVisitors = HostelVisitor::whereBetween('visit_date', [now()->startOfMonth()->toDateString(), now()->toDateString()])->count();

        return [
            'total_rooms' => Room::count(),
            'total_beds' => $totalBeds,
            'occupied_beds' => $occupiedBeds,
            'available_beds' => $totalBeds - $occupiedBeds,
            'occupancy_rate' => $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0,
            'active_students' => HostelAllocation::where('status', 'Active')->count(),
            'fee_collected_total' => round((float) HostelFee::where('status', 'Paid')->sum('amount'), 2),
            'fee_pending_total' => round((float) HostelFee::where('status', 'Pending')->sum('amount'), 2),
            'visitors_this_month' => $thisMonthVisitors,
            'room_occupancy' => $roomOccupancy,
        ];
    }
}
