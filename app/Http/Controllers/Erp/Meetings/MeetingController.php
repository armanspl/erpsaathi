<?php

namespace App\Http\Controllers\Erp\Meetings;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class MeetingController extends Controller
{
    public function index(Request $request)
    {
        $query = Meeting::with(['teacher:id,name', 'schoolClass:id,name', 'createdBy:id,name']);

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }
        if ($request->boolean('has_recording')) {
            $query->whereNotNull('recording_url');
        }

        return response()->json($query->orderByDesc('meeting_date')->orderByDesc('id')->get());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $meeting = Meeting::create([...$data, 'created_by_id' => Auth::guard('erp')->id()]);

        return response()->json($meeting->load(['teacher:id,name', 'schoolClass:id,name', 'createdBy:id,name']), 201);
    }

    public function update(Request $request, Meeting $meeting)
    {
        $data = $this->validated($request);

        $meeting->update($data);

        return response()->json($meeting->load(['teacher:id,name', 'schoolClass:id,name', 'createdBy:id,name']));
    }

    public function destroy(Meeting $meeting)
    {
        $meeting->delete();

        return response()->json(['success' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['Online Meeting', 'Parent Teacher Meeting', 'Broadcast'])],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'meeting_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'meeting_link' => 'nullable|string|max:255',
            'venue' => 'nullable|string|max:255',
            'audience' => ['required', Rule::in(['All', 'Students', 'Teachers', 'Staff', 'Parents'])],
            'teacher_id' => 'nullable|exists:teachers,id',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'status' => ['required', Rule::in(['Scheduled', 'Completed', 'Cancelled'])],
            'recording_url' => 'nullable|string|max:255',
        ]);
    }
}
