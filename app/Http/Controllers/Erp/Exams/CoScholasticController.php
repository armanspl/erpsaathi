<?php

namespace App\Http\Controllers\Erp\Exams;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\CoScholasticArea;
use App\Models\CoScholasticStudentGrade;
use App\Models\GradeSystem;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Exam Management → Co-Scholastic Areas: the list of areas printed on the result PDF
 * (Work Education, Drawing & Art, Sports, …) and each student's grade per area for the
 * header-selected academic session.
 */
class CoScholasticController extends Controller
{
    public function areas()
    {
        $session = AcademicSession::fromRequest(request(), true);

        $areas = CoScholasticArea::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->withCount(['studentGrades as grades_count' => fn ($q) => $q->when($session, fn ($q) => $q->where('academic_session_id', $session->id))])
            ->get();

        return response()->json($areas);
    }

    public function storeArea(Request $request)
    {
        $data = $this->validateArea($request);
        $data['sort_order'] ??= ((int) CoScholasticArea::max('sort_order')) + 1;

        return response()->json(CoScholasticArea::create($data), 201);
    }

    public function updateArea(Request $request, CoScholasticArea $area)
    {
        $area->update($this->validateArea($request, $area));

        return response()->json($area);
    }

    public function destroyArea(CoScholasticArea $area)
    {
        $area->delete();

        return response()->json(['success' => true]);
    }

    /** Students of one class (optionally one section) with their grades for the header session. */
    public function grades(Request $request)
    {
        $session = AcademicSession::fromRequest($request, true);
        abort_unless($session, 422, 'Select an academic session first.');

        $data = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $students = Student::query()
            ->with('section:id,name')
            ->where('school_class_id', $data['school_class_id'])
            ->where('status', 'Active')
            ->when($data['section_id'] ?? null, fn ($q, $id) => $q->where('section_id', $id))
            ->when($data['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->get(['id', 'name', 'admission_no', 'roll_no', 'section_id'])
            ->sortBy(fn ($s) => [(int) $s->roll_no ?: PHP_INT_MAX, $s->name])
            ->values();

        $grades = CoScholasticStudentGrade::query()
            ->where('academic_session_id', $session->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->get(['student_id', 'co_scholastic_area_id', 'grade'])
            ->groupBy('student_id');

        return response()->json([
            'session' => ['id' => $session->id, 'name' => $session->name],
            'grade_options' => GradeSystem::orderByDesc('min_percentage')->pluck('grade')->filter()->unique()->values(),
            'students' => $students->map(fn (Student $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'admission_no' => $s->admission_no,
                'roll_no' => $s->roll_no,
                'section' => $s->section?->name,
                'grades' => $grades->get($s->id, collect())
                    ->mapWithKeys(fn ($g) => [$g->co_scholastic_area_id => $g->grade]),
            ]),
        ]);
    }

    /** Upsert grades for the header session; an empty grade clears that student's area. */
    public function saveGrades(Request $request)
    {
        $session = AcademicSession::fromRequest($request, true);
        abort_unless($session, 422, 'Select an academic session first.');

        $data = $request->validate([
            'records' => 'required|array|min:1',
            'records.*.student_id' => 'required|exists:students,id',
            'records.*.area_id' => 'required|exists:co_scholastic_areas,id',
            'records.*.grade' => 'nullable|string|max:16',
        ]);

        $saved = 0;
        $cleared = 0;
        DB::transaction(function () use ($data, $session, &$saved, &$cleared) {
            foreach ($data['records'] as $record) {
                $key = [
                    'student_id' => $record['student_id'],
                    'academic_session_id' => $session->id,
                    'co_scholastic_area_id' => $record['area_id'],
                ];
                $grade = mb_strtoupper(trim((string) ($record['grade'] ?? '')));
                if ($grade === '') {
                    $cleared += CoScholasticStudentGrade::query()->where($key)->delete();

                    continue;
                }
                CoScholasticStudentGrade::query()->updateOrCreate($key, ['grade' => $grade]);
                $saved++;
            }
        });

        return response()->json(['success' => true, 'saved' => $saved, 'cleared' => $cleared]);
    }

    private function validateArea(Request $request, ?CoScholasticArea $area = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('co_scholastic_areas', 'name')->ignore($area?->id),
            ],
            'sort_order' => 'nullable|integer|min:0|max:65535',
            'is_active' => 'sometimes|boolean',
        ]);
    }
}
