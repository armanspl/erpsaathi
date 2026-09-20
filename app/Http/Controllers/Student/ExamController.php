<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\EnforcesPortalVisibility;
use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\Student;
use App\Services\DocumentDataBuilder;
use App\Services\DocumentRenderService;
use App\Services\ExamResultCalculator;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamController extends Controller
{
    use EnforcesPortalVisibility;

    public function __construct(
        private DocumentDataBuilder $dataBuilder,
        private DocumentRenderService $renderer,
    ) {}

    /** Upcoming/past schedule — visible regardless of whether results have been published. */
    public function schedule()
    {
        $this->abortIfModuleDisabled('exam_schedule');
        $student = $this->student();

        $rows = ExamSchedule::query()
            ->where('school_class_id', $student->school_class_id)
            ->with(['exam:id,name,type,academic_session_id,start_date', 'subject:id,name'])
            ->orderBy('date')
            ->get();

        return response()->json($rows->map(fn (ExamSchedule $s) => [
            'id' => $s->id,
            'exam_id' => $s->exam_id,
            'exam_name' => $s->exam?->name,
            'exam_type' => $s->exam?->type,
            'subject' => $s->subject?->name,
            'date' => $s->date?->format('Y-m-d'),
            'start_time' => $s->start_time,
            'end_time' => $s->end_time,
            'room' => $s->room,
            'max_marks' => (float) $s->max_marks,
        ])->values());
    }

    public function admitCard(Exam $exam): StreamedResponse
    {
        $this->abortIfModuleDisabled('admit_card');
        $student = $this->student();
        $this->assertScheduledForStudent($exam, $student);

        $data = $this->dataBuilder->admitCard($exam, $student);

        return $this->renderer->streamPdf('admit_card', $data, "admit-card-{$student->admission_no}.pdf");
    }

    /** Every published exam's result for the student's current session. */
    public function marks()
    {
        $this->abortIfModuleDisabled('marks');
        $student = $this->student();
        $session = AcademicSession::where('is_current', true)->first();

        return response()->json($this->publishedResultRows($student, $session?->id));
    }

    public function reportCard(Exam $exam): StreamedResponse
    {
        $this->abortIfModuleDisabled('report_card');
        $student = $this->student();
        abort_unless($exam->published_at, 404, 'Results for this exam have not been published yet.');

        $rows = ExamResultCalculator::forExam($exam, $student->school_class_id);
        $row = collect($rows)->firstWhere('student_id', $student->id);
        abort_if(! $row, 404, 'No result found for this exam.');

        $filename = 'report-card-'.$student->admission_no.'-'.$exam->id.'.pdf';

        return $this->renderer->streamPdf('report_card', $this->dataBuilder->reportCard($exam, $row), $filename);
    }

    /** Marks grouped by subject across every published exam of the current session — for a trend view. */
    public function subjectPerformance()
    {
        $this->abortIfModuleDisabled('subject_performance');
        $student = $this->student();
        $session = AcademicSession::where('is_current', true)->first();
        $rows = $this->publishedResultRows($student, $session?->id);

        $bySubject = [];
        foreach ($rows as $row) {
            foreach ($row['subjects'] as $subject) {
                $key = $subject['subject_name'];
                $bySubject[$key] ??= ['subject_name' => $key, 'entries' => []];
                $bySubject[$key]['entries'][] = [
                    'exam_id' => $row['exam_id'],
                    'exam_name' => $row['exam_name'],
                    'marks_obtained' => $subject['marks_obtained'],
                    'max_marks' => $subject['max_marks'],
                    'is_absent' => $subject['is_absent'],
                ];
            }
        }

        foreach ($bySubject as &$subject) {
            $scored = collect($subject['entries'])->filter(fn ($e) => ! $e['is_absent'] && $e['marks_obtained'] !== null);
            $obtained = $scored->sum('marks_obtained');
            $max = $scored->sum('max_marks');
            $subject['average_percentage'] = $max > 0 ? round(($obtained / $max) * 100, 2) : null;
        }
        unset($subject);

        return response()->json(array_values($bySubject));
    }

    /** Published exam results from every academic session other than the current one. */
    public function previousResults()
    {
        $this->abortIfModuleDisabled('previous_results');
        $student = $this->student();
        $currentSessionId = AcademicSession::where('is_current', true)->value('id');

        $rows = collect($this->publishedResultRows($student, null))
            ->when($currentSessionId, fn ($c) => $c->where('academic_session_id', '!=', $currentSessionId))
            ->values();

        return response()->json($rows);
    }

    private function student(): Student
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        return $student;
    }

    private function assertScheduledForStudent(Exam $exam, Student $student): void
    {
        $scheduled = ExamSchedule::where('exam_id', $exam->id)
            ->where('school_class_id', $student->school_class_id)
            ->exists();
        abort_unless($scheduled, 404, 'This exam is not scheduled for your class.');
    }

    /**
     * ExamResultCalculator returns every exam the student's class has schedules for,
     * regardless of publish state — filter to published only before this ever reaches
     * a student, and attach academic_session_id for session-scoping callers.
     *
     * @return list<array<string, mixed>>
     */
    private function publishedResultRows(Student $student, ?int $sessionId): array
    {
        $rows = ExamResultCalculator::forStudent($student);
        if ($rows === []) {
            return [];
        }

        $examIds = collect($rows)->pluck('exam_id')->unique()->values();
        $exams = Exam::whereIn('id', $examIds)
            ->whereNotNull('published_at')
            ->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId))
            ->pluck('academic_session_id', 'id');

        return collect($rows)
            ->filter(fn ($row) => $exams->has($row['exam_id']))
            ->map(fn ($row) => [...$row, 'academic_session_id' => $exams[$row['exam_id']]])
            ->values()
            ->all();
    }
}
