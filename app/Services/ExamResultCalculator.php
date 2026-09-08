<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\GradeSystem;
use App\Models\Mark;
use App\Models\Student;

class ExamResultCalculator
{
    private const PASS_PERCENTAGE = 33.0;

    /**
     * Compute every student's result for an exam: total obtained, percentage (against
     * their own class's max marks), grade, pass/fail, and rank within their class.
     * Nothing here is stored — it's derived live from exam_schedules + marks each time.
     */
    public static function forExam(Exam $exam, ?int $schoolClassId = null, ?int $branchId = null, ?int $sectionId = null): array
    {
        $schedules = ExamSchedule::where('exam_id', $exam->id)
            ->when($schoolClassId, fn ($q) => $q->where('school_class_id', $schoolClassId))
            ->with('subject:id,name')
            ->get();

        if ($schedules->isEmpty()) {
            return [];
        }

        $schedulesByClass = $schedules->groupBy('school_class_id');

        $students = Student::whereIn('school_class_id', $schedulesByClass->keys())
            ->where('status', 'Active')
            ->forBranch($branchId)
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
            ->get();

        $marks = Mark::whereIn('exam_schedule_id', $schedules->pluck('id'))->get()->groupBy('student_id');
        $grades = GradeSystem::orderByDesc('min_percentage')->get();

        $rows = $students->map(function (Student $student) use ($marks, $schedulesByClass, $grades) {
            $classSchedules = $schedulesByClass->get($student->school_class_id, collect());
            $studentMarks = $marks->get($student->id, collect());

            $obtained = 0.0;
            $maxTotal = 0.0;
            $subjects = $classSchedules->map(function (ExamSchedule $s) use ($studentMarks, &$obtained, &$maxTotal) {
                $mark = $studentMarks->firstWhere('exam_schedule_id', $s->id);
                $absent = (bool) ($mark?->is_absent);
                $obt = $absent ? null : ($mark?->marks_obtained !== null ? (float) $mark->marks_obtained : null);
                // Absent: preserve status, do not count as zero toward obtained or max.
                if (! $absent) {
                    $maxTotal += (float) $s->max_marks;
                    if ($obt !== null) {
                        $obtained += $obt;
                    }
                }

                return [
                    'subject_id' => $s->subject_id,
                    'subject_name' => $s->subject->name,
                    'marks_obtained' => $obt,
                    'max_marks' => (float) $s->max_marks,
                    'is_absent' => $absent,
                ];
            })->values();

            $percentage = $maxTotal > 0 ? round(($obtained / $maxTotal) * 100, 2) : 0;
            $grade = $grades->first(fn (GradeSystem $g) => $percentage >= $g->min_percentage && $percentage <= $g->max_percentage);

            return [
                'student_id' => $student->id,
                'admission_no' => $student->admission_no,
                'roll_no' => $student->roll_no,
                'name' => $student->name,
                'school_class_id' => $student->school_class_id,
                'section_id' => $student->section_id,
                'obtained' => $obtained,
                'max_total' => $maxTotal,
                'percentage' => $percentage,
                'grade' => $grade?->grade,
                'result' => $percentage >= self::PASS_PERCENTAGE ? 'Pass' : 'Fail',
                'subjects' => $subjects,
            ];
        });

        $ranked = collect();
        foreach ($rows->groupBy('school_class_id') as $classRows) {
            foreach ($classRows->sortByDesc('percentage')->values() as $i => $row) {
                $row['rank'] = $i + 1;
                $ranked->push($row);
            }
        }

        return $ranked->sortBy(['school_class_id', 'roll_no', 'rank'])->values()->all();
    }

    /** Every exam this student has a schedule (and therefore a result) for, newest first. */
    public static function forStudent(Student $student): array
    {
        $examIds = ExamSchedule::where('school_class_id', $student->school_class_id)->pluck('exam_id')->unique();
        $exams = Exam::whereIn('id', $examIds)->orderByDesc('start_date')->get();

        return $exams->map(function (Exam $exam) use ($student) {
            $row = collect(self::forExam($exam, $student->school_class_id))->firstWhere('student_id', $student->id);

            return $row ? ['exam_id' => $exam->id, 'exam_name' => $exam->name, ...$row] : null;
        })->filter()->values()->all();
    }
}
