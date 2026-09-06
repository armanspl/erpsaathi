<?php

namespace App\Services;

use App\Models\Meeting;

class MeetingReportCalculator
{
    /** Meeting activity snapshot, computed live from the meetings table. */
    public static function summary(): array
    {
        $meetings = Meeting::all();

        $byType = $meetings->groupBy('type')->map->count();

        return [
            'total_meetings' => $meetings->count(),
            'scheduled' => $meetings->where('status', 'Scheduled')->count(),
            'completed' => $meetings->where('status', 'Completed')->count(),
            'cancelled' => $meetings->where('status', 'Cancelled')->count(),
            'with_recording' => $meetings->whereNotNull('recording_url')->count(),
            'upcoming' => $meetings->where('status', 'Scheduled')->filter(fn (Meeting $m) => ! $m->meeting_date->isPast())->count(),
            'by_type' => [
                'Online Meeting' => (int) ($byType['Online Meeting'] ?? 0),
                'Parent Teacher Meeting' => (int) ($byType['Parent Teacher Meeting'] ?? 0),
                'Broadcast' => (int) ($byType['Broadcast'] ?? 0),
            ],
        ];
    }
}
