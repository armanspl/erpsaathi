<?php

namespace App\Http\Controllers\Erp\Exams;

use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExamScheduleController extends Controller
{
    public function index(Request $request)
    {
        $query = ExamSchedule::with(['exam:id,name', 'schoolClass:id,name', 'subject:id,name,code'])->orderBy('date');

        if ($request->filled('exam_id')) {
            $query->where('exam_id', $request->integer('exam_id'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $schedule = ExamSchedule::create($data);

        return response()->json($schedule->load(['exam:id,name', 'schoolClass:id,name', 'subject:id,name,code']), 201);
    }

    public function update(Request $request, ExamSchedule $examSchedule)
    {
        $data = $this->validated($request, $examSchedule);

        $examSchedule->update($data);

        return response()->json($examSchedule->load(['exam:id,name', 'schoolClass:id,name', 'subject:id,name,code']));
    }

    public function destroy(ExamSchedule $examSchedule)
    {
        $examSchedule->delete();

        return response()->json(['success' => true]);
    }

    private function validated(Request $request, ?ExamSchedule $schedule = null): array
    {
        return $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'subject_id' => [
                'required', 'exists:subjects,id',
                Rule::unique('exam_schedules', 'subject_id')
                    ->where(fn ($q) => $q->where('exam_id', $request->exam_id)->where('school_class_id', $request->school_class_id))
                    ->ignore($schedule?->id),
            ],
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'room' => 'nullable|string|max:100',
            'max_marks' => 'required|numeric|min:1',
        ]);
    }
}
