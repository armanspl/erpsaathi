<?php

namespace App\Http\Controllers\Erp\Communication;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $query = Event::query();
        AcademicSession::applyDateWindow($query, $request, 'event_date');

        return response()->json($query->orderBy('event_date')->get());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $event = Event::create($data);

        return response()->json($event, 201);
    }

    public function update(Request $request, Event $event)
    {
        $data = $this->validated($request);

        $event->update($data);

        return response()->json($event);
    }

    public function destroy(Event $event)
    {
        $event->delete();

        return response()->json(['success' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'venue' => 'nullable|string|max:255',
            'event_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'status' => ['required', Rule::in(['Scheduled', 'Cancelled'])],
        ]);
    }
}
