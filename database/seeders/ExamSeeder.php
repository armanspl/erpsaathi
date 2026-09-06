<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\ExamType;
use App\Models\GradeSystem;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class ExamSeeder extends Seeder
{
    public function run(): void
    {
        $types = ['Unit Test', 'Half Yearly', 'Annual Exam'];
        foreach ($types as $name) {
            ExamType::firstOrCreate(['name' => $name]);
        }

        $grades = [
            ['grade' => 'A+', 'min_percentage' => 90, 'max_percentage' => 100, 'remarks' => 'Outstanding'],
            ['grade' => 'A', 'min_percentage' => 80, 'max_percentage' => 89.99, 'remarks' => 'Excellent'],
            ['grade' => 'B', 'min_percentage' => 70, 'max_percentage' => 79.99, 'remarks' => 'Very Good'],
            ['grade' => 'C', 'min_percentage' => 60, 'max_percentage' => 69.99, 'remarks' => 'Good'],
            ['grade' => 'D', 'min_percentage' => 33, 'max_percentage' => 59.99, 'remarks' => 'Satisfactory'],
            ['grade' => 'F', 'min_percentage' => 0, 'max_percentage' => 32.99, 'remarks' => 'Needs Improvement'],
        ];
        foreach ($grades as $grade) {
            GradeSystem::firstOrCreate(['grade' => $grade['grade']], $grade);
        }

        $session = AcademicSession::where('is_current', true)->first();
        $halfYearly = ExamType::where('name', 'Half Yearly')->first();
        $class1 = SchoolClass::where('name', '1')->first();
        $english = Subject::where('code', 'ENG')->first();
        $math = Subject::where('code', 'MATH')->first();

        if (! $session || ! $halfYearly || ! $class1 || ! $english || ! $math) {
            return;
        }

        $exam = Exam::firstOrCreate(
            ['name' => 'Half Yearly Examination 2026'],
            ['exam_type_id' => $halfYearly->id, 'academic_session_id' => $session->id, 'start_date' => '2026-09-15', 'end_date' => '2026-09-25', 'status' => 'Scheduled']
        );

        $englishSchedule = ExamSchedule::firstOrCreate(
            ['exam_id' => $exam->id, 'school_class_id' => $class1->id, 'subject_id' => $english->id],
            ['date' => '2026-09-15', 'start_time' => '09:00', 'end_time' => '11:00', 'room' => 'Room 1', 'max_marks' => 100]
        );

        ExamSchedule::firstOrCreate(
            ['exam_id' => $exam->id, 'school_class_id' => $class1->id, 'subject_id' => $math->id],
            ['date' => '2026-09-17', 'start_time' => '09:00', 'end_time' => '11:00', 'room' => 'Room 1', 'max_marks' => 100]
        );

        $student = Student::where('admission_no', 'ADM-1001')->first();
        if ($student) {
            $englishSchedule->marks()->firstOrCreate(['student_id' => $student->id], ['marks_obtained' => 88]);
        }
    }
}
