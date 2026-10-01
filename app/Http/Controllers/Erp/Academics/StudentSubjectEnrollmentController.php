<?php

namespace App\Http\Controllers\Erp\Academics;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Services\StudentSubjectEnrollmentService;
use Illuminate\Http\Request;

/**
 * Manages which students take which optional/elective subject for a class + session (e.g. some
 * students take Urdu, others Sanskrit, in the same Class 1-A) — compulsory subjects need none of
 * this, they already apply to everyone via class_subject.
 */
class StudentSubjectEnrollmentController extends Controller
{
    /** Class roster + each student's current optional-subject enrollment, for the assignment screen. */
    public function roster(Request $request)
    {
        $data = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
        ]);

        $schoolClass = SchoolClass::with(['subjects' => fn ($q) => $q->wherePivot('is_optional', true)])
            ->findOrFail($data['school_class_id']);
        $session = AcademicSession::fromRequest($request, true);

        $students = Student::where('status', 'Active')
            ->where('school_class_id', $schoolClass->id)
            ->when(! empty($data['section_id']), fn ($q) => $q->where('section_id', $data['section_id']))
            ->orderBy('roll_no')->orderBy('name')
            ->get(['id', 'admission_no', 'roll_no', 'name', 'religion', 'gender']);

        $enrolled = $session
            ? StudentSubjectEnrollmentService::enrolledSubjectIdsByStudent($students->pluck('id')->all(), $session->id)
            : collect();

        return response()->json([
            'academic_session' => $session ? ['id' => $session->id, 'name' => $session->name] : null,
            'optional_subjects' => $schoolClass->subjects->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'elective_group' => $s->pivot->elective_group,
            ]),
            'religions' => $students->pluck('religion')->filter()->unique()->sort()->values(),
            'students' => $students->map(fn (Student $s) => [
                'id' => $s->id,
                'admission_no' => $s->admission_no,
                'roll_no' => $s->roll_no,
                'name' => $s->name,
                'religion' => $s->religion,
                'gender' => $s->gender,
                'enrolled_subject_ids' => $enrolled->get($s->id, collect())->values(),
            ])->values(),
        ]);
    }

    /** Set (or clear) one student's enrollment in one optional subject — always available as a manual override. */
    public function assign(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'subject_id' => 'required|exists:subjects,id',
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'enrolled' => 'required|boolean',
        ]);

        $student = Student::findOrFail($data['student_id']);

        if ($data['enrolled']) {
            StudentSubjectEnrollment::updateOrCreate([
                'student_id' => $student->id,
                'subject_id' => $data['subject_id'],
                'academic_session_id' => $data['academic_session_id'],
            ], [
                'school_class_id' => $student->school_class_id,
            ]);
        } else {
            StudentSubjectEnrollment::where('student_id', $student->id)
                ->where('subject_id', $data['subject_id'])
                ->where('academic_session_id', $data['academic_session_id'])
                ->delete();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Bulk-enroll every matching active student into one optional subject — religion is only a
     * convenience filter for picking the students, never an automatic assignment rule.
     */
    public function bulkAssign(Request $request)
    {
        $data = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'religion' => 'nullable|string|max:100',
        ]);

        $students = Student::where('status', 'Active')
            ->where('school_class_id', $data['school_class_id'])
            ->when(! empty($data['section_id']), fn ($q) => $q->where('section_id', $data['section_id']))
            ->when(! empty($data['religion']), fn ($q) => $q->where('religion', $data['religion']))
            ->get(['id', 'school_class_id']);

        foreach ($students as $student) {
            StudentSubjectEnrollment::updateOrCreate([
                'student_id' => $student->id,
                'subject_id' => $data['subject_id'],
                'academic_session_id' => $data['academic_session_id'],
            ], [
                'school_class_id' => $student->school_class_id,
            ]);
        }

        return response()->json(['success' => true, 'assigned' => $students->count()]);
    }
}
