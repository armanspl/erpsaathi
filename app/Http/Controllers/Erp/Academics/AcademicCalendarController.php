<?php

namespace App\Http\Controllers\Erp\Academics;

use App\Http\Controllers\Controller;
use App\Models\AcademicCalendarEntry;
use App\Models\AcademicSession;
use App\Support\DashboardCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicCalendarController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'academic_session_id' => 'nullable|integer|exists:academic_sessions,id',
            'category' => ['nullable', 'string', Rule::in(AcademicCalendarEntry::CATEGORIES)],
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'month' => 'nullable|date_format:Y-m',
        ]);

        $sessionId = $data['academic_session_id']
            ?? AcademicSession::query()->where('is_current', true)->value('id');

        $query = AcademicCalendarEntry::query()
            ->with('academicSession:id,name,is_current')
            ->orderByRaw('start_date is null')
            ->orderBy('start_date')
            ->orderBy('sort_order')
            ->orderBy('title');

        if ($sessionId) {
            $query->where(function ($q) use ($sessionId) {
                $q->where('academic_session_id', $sessionId)->orWhereNull('academic_session_id');
            });
        }
        if (! empty($data['category'])) {
            $query->where('category', $data['category']);
        }
        if (! empty($data['from'])) {
            $query->whereDate('start_date', '>=', $data['from']);
        }
        if (! empty($data['to'])) {
            $query->whereDate('start_date', '<=', $data['to']);
        }
        if (! empty($data['month'])) {
            [$y, $m] = array_map('intval', explode('-', $data['month']));
            $query->whereYear('start_date', $y)->whereMonth('start_date', $m);
        }

        $entries = $query->get();

        return response()->json([
            'entries' => $entries,
            'sessions' => AcademicSession::query()->orderByDesc('is_current')->orderByDesc('id')->get(['id', 'name', 'is_current', 'start_date', 'end_date']),
            'categories' => AcademicCalendarEntry::CATEGORIES,
            'stats' => [
                'total' => $entries->count(),
                'holiday' => $entries->where('category', 'holiday')->count(),
                'examination' => $entries->where('category', 'examination')->count(),
                'programme' => $entries->where('category', 'programme')->count(),
                'deadline' => $entries->where('category', 'deadline')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort_order'] = $data['sort_order']
            ?? ((int) AcademicCalendarEntry::query()->where('category', $data['category'])->max('sort_order') + 1);

        $entry = AcademicCalendarEntry::query()->create($data);

        DashboardCache::forget();

        return response()->json(['entry' => $entry->load('academicSession:id,name,is_current')], 201);
    }

    public function update(Request $request, AcademicCalendarEntry $academicCalendar)
    {
        $academicCalendar->update($this->validated($request, $academicCalendar));

        DashboardCache::forget();

        return response()->json(['entry' => $academicCalendar->fresh()->load('academicSession:id,name,is_current')]);
    }

    public function destroy(AcademicCalendarEntry $academicCalendar)
    {
        $academicCalendar->delete();

        DashboardCache::forget();

        return response()->json(['message' => 'Deleted.']);
    }

    private function validated(Request $request, ?AcademicCalendarEntry $entry = null): array
    {
        return $request->validate([
            'academic_session_id' => 'nullable|integer|exists:academic_sessions,id',
            'category' => ['required', 'string', Rule::in(AcademicCalendarEntry::CATEGORIES)],
            'title' => 'required|string|max:255',
            'month_label' => 'nullable|string|max:40',
            'date_label' => 'nullable|string|max:80',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'sort_order' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);
    }
}
