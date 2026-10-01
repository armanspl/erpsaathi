<?php

namespace App\Http\Controllers\Erp\Academics;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassRoutineEntry;
use App\Models\ClassRoutineSheet;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Services\ClassRoutineExcelExporter;
use App\Services\ClassRoutineImportParser;
use App\Support\TabularExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClassRoutineController extends Controller
{
    private const RELATIONS = ['academicSession:id,name', 'branch:id,name'];

    /** Classes/sections/subjects/branches are already served by the shared /academics/lookups
     *  endpoint — this only adds what that one doesn't carry: teachers, with their routine badge
     *  colour. */
    public function lookups()
    {
        return response()->json([
            'teachers' => Teacher::where('status', 'active')->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    public function index(Request $request)
    {
        $query = ClassRoutineSheet::with(self::RELATIONS)->withCount('entries');

        if ($request->filled('academic_session_id')) {
            $query->where('academic_session_id', $request->integer('academic_session_id'));
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        return response()->json($query->orderByDesc('updated_at')->get());
    }

    public function show(ClassRoutineSheet $classRoutine)
    {
        return response()->json($this->present($classRoutine));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'academic_session_id' => [
                'required', 'exists:academic_sessions,id',
                \Illuminate\Validation\Rule::unique('class_routine_sheets', 'academic_session_id')->where(fn ($q) => $q->where('branch_id', $request->input('branch_id'))),
            ],
            'branch_id' => 'required|exists:branches,id',
            'title' => 'nullable|string|max:255',
            'periods_per_day' => 'nullable|integer|min:1|max:20',
        ], [
            'academic_session_id.unique' => 'A class routine already exists for this session and branch.',
        ]);

        $sheet = ClassRoutineSheet::create([
            'academic_session_id' => $data['academic_session_id'],
            'branch_id' => $data['branch_id'],
            'title' => $data['title'] ?? null,
            'periods_per_day' => $data['periods_per_day'] ?? 7,
        ]);

        return response()->json($this->present($sheet), 201);
    }

    public function update(Request $request, ClassRoutineSheet $classRoutine)
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'periods_per_day' => 'nullable|integer|min:1|max:20',
            'days' => 'array',
            'days.*.day_of_week' => 'required|integer|min:1|max:7',
            'days.*.entries' => 'array',
            'days.*.entries.*.school_class_id' => 'required|exists:school_classes,id',
            'days.*.entries.*.section_id' => 'nullable|exists:sections,id',
            'days.*.entries.*.period_number' => 'required|integer|min:1|max:20',
            'days.*.entries.*.subject_id' => 'nullable|exists:subjects,id',
            'days.*.entries.*.subject_label' => 'nullable|string|max:100',
            'days.*.entries.*.teacher_id' => 'nullable|exists:teachers,id',
            'days.*.entries.*.teacher_label' => 'nullable|string|max:100',
            'days.*.entries.*.color' => 'nullable|string|max:7',
            'days.*.entries.*.remarks' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data, $classRoutine) {
            $classRoutine->update([
                'title' => $data['title'] ?? $classRoutine->title,
                'periods_per_day' => $data['periods_per_day'] ?? $classRoutine->periods_per_day,
            ]);

            $classRoutine->entries()->delete();

            $teacherColorBackfill = []; // teacher_id -> color, applied once below (never clobbers an existing colour)
            foreach ($data['days'] ?? [] as $day) {
                foreach ($day['entries'] ?? [] as $entry) {
                    if (empty($entry['subject_id']) && empty($entry['subject_label'])
                        && empty($entry['teacher_id']) && empty($entry['teacher_label'])) {
                        continue; // a fully blank cell — nothing to persist
                    }

                    $color = $entry['color'] ?? null;
                    if ($color === null && ! empty($entry['teacher_id'])) {
                        $color = Teacher::find($entry['teacher_id'])?->color;
                    }
                    if ($color !== null && ! empty($entry['teacher_id'])) {
                        // A teacher's colour can vary slightly cell-to-cell in a hand-coloured
                        // source workbook (confirmed against the real GAS file) — tally every
                        // colour seen for this teacher in this save and use the most common one
                        // as their persistent badge colour, rather than whichever cell happened
                        // to be processed first.
                        $teacherColorBackfill[$entry['teacher_id']][$color] = ($teacherColorBackfill[$entry['teacher_id']][$color] ?? 0) + 1;
                    }

                    $classRoutine->entries()->create([
                        'school_class_id' => $entry['school_class_id'],
                        'section_id' => $entry['section_id'] ?? null,
                        'day_of_week' => $day['day_of_week'],
                        'period_number' => $entry['period_number'],
                        'subject_id' => $entry['subject_id'] ?? null,
                        'subject_label' => empty($entry['subject_id']) ? ($entry['subject_label'] ?? null) : null,
                        'teacher_id' => $entry['teacher_id'] ?? null,
                        'teacher_label' => empty($entry['teacher_id']) ? ($entry['teacher_label'] ?? null) : null,
                        'color' => $color,
                        'remarks' => $entry['remarks'] ?? null,
                    ]);
                }
            }

            if ($teacherColorBackfill !== []) {
                Teacher::whereIn('id', array_keys($teacherColorBackfill))->whereNull('color')->get(['id'])
                    ->each(function ($t) use ($teacherColorBackfill) {
                        arsort($teacherColorBackfill[$t->id]);
                        $t->update(['color' => array_key_first($teacherColorBackfill[$t->id])]);
                    });
            }
        });

        return response()->json($this->present($classRoutine->fresh()));
    }

    public function destroy(ClassRoutineSheet $classRoutine)
    {
        $classRoutine->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Parses an uploaded Class Routine workbook and returns a {days, warnings, teacher_colors}
     * preview for the frontend to pre-fill — nothing is written to the database here (see
     * ClassRoutineImportParser). Saving afterward goes through the normal store()/update() flow,
     * once the school has reviewed the warnings and fixed any unmatched subject/teacher/class in
     * the grid.
     */
    public function importPreview(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:20480']);

        @set_time_limit(120);

        try {
            $result = app(ClassRoutineImportParser::class)->parse($request->file('file')->getRealPath());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    /**
     * format=xlsx (default) rebuilds the school's own coloured layout via ClassRoutineExcelExporter
     * (the "same to same" export). format=csv/pdf are a plain flattened row-per-period-slot table
     * — no colours/merges, just the data — via the shared TabularExport helper used by every other
     * report/register export in this app.
     */
    public function export(Request $request, ClassRoutineSheet $classRoutine): StreamedResponse
    {
        $format = strtolower((string) $request->query('format', 'xlsx'));
        $classRoutine->loadMissing(['entries.schoolClass', 'entries.subject', 'entries.teacher']);

        if ($format === 'xlsx') {
            return app(ClassRoutineExcelExporter::class)->stream($classRoutine);
        }

        $header = ['Day', 'Class', 'Period', 'Subject', 'Teacher', 'Remarks'];
        $rows = [];
        foreach (ClassRoutineEntry::DAYS as $dow => $dayLabel) {
            $dayEntries = $classRoutine->entries->where('day_of_week', $dow)
                ->sortBy([['school_class_id', 'asc'], ['period_number', 'asc']]);
            foreach ($dayEntries as $entry) {
                $rows[] = [
                    $dayLabel,
                    $entry->schoolClass->name ?? '',
                    $entry->period_number,
                    $entry->subject->name ?? $entry->subject_label ?? '',
                    $entry->teacher->name ?? $entry->teacher_label ?? '',
                    $entry->remarks ?? '',
                ];
            }
        }

        return TabularExport::stream($header, $rows, 'class-routine-'.$classRoutine->id, $classRoutine->title ?: 'Class Routine', $format);
    }

    private function present(ClassRoutineSheet $sheet): array
    {
        $sheet->loadMissing([...self::RELATIONS, 'entries.schoolClass:id,name', 'entries.section:id,name', 'entries.subject:id,name', 'entries.teacher:id,name,color']);
        $currentSession = AcademicSession::fromRequest(request(), true)?->name;

        $days = [];
        foreach (ClassRoutineEntry::DAYS as $dow => $label) {
            $days[] = [
                'day_of_week' => $dow,
                'label' => $label,
                'entries' => $sheet->entries->where('day_of_week', $dow)->values(),
            ];
        }

        return [
            ...$sheet->toArray(),
            'days' => $days,
            'session_name' => $currentSession,
        ];
    }
}
