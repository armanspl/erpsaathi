<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\EnforcesPortalVisibility;
use App\Models\Event;

class EventController extends Controller
{
    use EnforcesPortalVisibility;

    public function index()
    {
        $this->abortIfModuleDisabled('events_calendar');

        $events = Event::query()
            ->orderBy('event_date')
            ->get(['id', 'title', 'description', 'venue', 'event_date', 'start_time', 'end_time', 'status']);

        return response()->json($events->map(fn (Event $e) => [
            'id' => $e->id,
            'title' => $e->title,
            'description' => $e->description,
            'venue' => $e->venue,
            'event_date' => $e->event_date?->format('Y-m-d'),
            'start_time' => $e->start_time,
            'end_time' => $e->end_time,
            'status' => $e->status,
        ])->values());
    }
}
