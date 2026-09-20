<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\EnforcesPortalVisibility;
use App\Models\AcademicCalendarEntry;
use App\Models\AcademicSession;

class CalendarController extends Controller
{
    use EnforcesPortalVisibility;

    public function index()
    {
        $this->abortIfModuleDisabled('events_calendar');

        $session = AcademicSession::where('is_current', true)->first();

        $entries = AcademicCalendarEntry::query()
            ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
            ->orderBy('sort_order')
            ->orderBy('start_date')
            ->get(['id', 'category', 'title', 'month_label', 'date_label', 'start_date', 'end_date', 'notes']);

        return response()->json($entries->map(fn (AcademicCalendarEntry $e) => [
            'id' => $e->id,
            'category' => $e->category,
            'title' => $e->title,
            'month_label' => $e->month_label,
            'date_label' => $e->date_label,
            'start_date' => $e->start_date?->format('Y-m-d'),
            'end_date' => $e->end_date?->format('Y-m-d'),
            'notes' => $e->notes,
        ])->values());
    }
}
