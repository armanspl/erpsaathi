<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\CoScholasticGrade;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\GradeSystem;
use App\Models\Mark;
use App\Models\ReportCardRemark;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Session-wide annual report: dynamic term/assessment columns + overall average of term totals.
 */
class AnnualReportCalculator
{
    private const PASS_PERCENTAGE = 33.0;

    /**
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    public static function forSession(
        AcademicSession $session,
        ?int $schoolClassId = null,
        ?int $branchId = null,
        ?int $sectionId = null
    ): array {
        $terms = AcademicTerm::query()
            ->where('academic_session_id', $session->id)
            ->with(['exams' => fn ($q) => $q->where('counts_toward_term', true)->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $columns = self::buildColumns($terms);
        if ($terms->isEmpty() || $columns === []) {
            return ['columns' => [], 'rows' => []];
        }

        $examIds = $terms->flatMap(fn (AcademicTerm $t) => $t->exams->pluck('id'))->unique()->values()->all();
        if ($examIds === []) {
            return ['columns' => $columns, 'rows' => []];
        }

        $schedules = ExamSchedule::query()
            ->whereIn('exam_id', $examIds)
            ->when($schoolClassId, fn ($q) => $q->where('school_class_id', $schoolClassId))
            ->with('subject:id,name')
            ->get();

        if ($schedules->isEmpty()) {
            return ['columns' => $columns, 'rows' => []];
        }

        $classIds = $schedules->pluck('school_class_id')->unique()->values();
        $students = Student::query()
            ->whereIn('school_class_id', $classIds)
            ->where('status', 'Active')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
            ->when($schoolClassId, fn ($q) => $q->where('school_class_id', $schoolClassId))
            ->get();

        if ($students->isEmpty()) {
            return ['columns' => $columns, 'rows' => []];
        }

        $marks = Mark::query()
            ->whereIn('exam_schedule_id', $schedules->pluck('id'))
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->groupBy('student_id');

        $scheduleIndex = [];
        foreach ($schedules as $schedule) {
            $scheduleIndex[$schedule->exam_id.'|'.$schedule->school_class_id.'|'.$schedule->subject_id] = $schedule;
        }

        $subjectsByClass = $schedules->groupBy('school_class_id')->map(function (Collection $rows) {
            return $rows->unique('subject_id')->values()->map(fn (ExamSchedule $s) => [
                'subject_id' => $s->subject_id,
                'subject_name' => $s->subject->name ?? 'Subject',
            ]);
        });

        $grades = GradeSystem::orderByDesc('min_percentage')->get();
        $termIds = $terms->pluck('id');
        $coByStudent = CoScholasticGrade::query()
            ->whereIn('academic_term_id', $termIds)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->groupBy('student_id');
        $remarksByStudent = ReportCardRemark::query()
            ->where('academic_session_id', $session->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        $rows = $students->map(function (Student $student) use (
            $marks, $subjectsByClass, $scheduleIndex, $terms, $grades, $columns, $coByStudent, $remarksByStudent, $session
        ) {
            $studentMarks = $marks->get($student->id, collect());
            $subjects = $subjectsByClass->get($student->school_class_id, collect());

            $subjectRows = [];
            $overallObtained = 0.0;
            $overallMax = 0.0;

            foreach ($subjects as $subject) {
                $sid = (int) $subject['subject_id'];
                $termBlocks = [];
                $termTotals = [];

                foreach ($terms as $term) {
                    $block = self::termSubjectBlock(
                        $student,
                        $sid,
                        $term,
                        $scheduleIndex,
                        $studentMarks
                    );
                    $termBlocks[] = $block;
                    if ($block['total'] !== null) {
                        $termTotals[] = (float) $block['total'];
                    }
                }

                $overall = $termTotals !== []
                    ? round(array_sum($termTotals) / count($termTotals), 2)
                    : null;
                $overallMaxSubject = 100.0;

                if ($overall !== null) {
                    $overallObtained += $overall;
                    $overallMax += $overallMaxSubject;
                }

                $pct = ($overall !== null && $overallMaxSubject > 0)
                    ? round(($overall / $overallMaxSubject) * 100, 2)
                    : null;
                $grade = $pct !== null
                    ? ($grades->first(fn ($g) => $pct >= (float) $g->min_percentage && $pct <= (float) $g->max_percentage)?->grade)
                    : null;

                $subjectRows[] = [
                    'subject_id' => $sid,
                    'subject_name' => $subject['subject_name'],
                    'terms' => $termBlocks,
                    'overall' => $overall,
                    'overall_max' => $overallMaxSubject,
                    'grade' => $grade,
                    'marks_obtained' => $overall,
                    'max_marks' => $overallMaxSubject,
                ];
            }

            $percentage = $overallMax > 0 ? round(($overallObtained / $overallMax) * 100, 2) : 0.0;
            $grade = $grades->first(fn ($g) => $percentage >= (float) $g->min_percentage && $percentage <= (float) $g->max_percentage);

            $co = self::presentCoScholastic($coByStudent->get($student->id, collect()), $terms);

            return [
                'format' => 'annual_term',
                'student_id' => $student->id,
                'admission_no' => $student->admission_no,
                'roll_no' => $student->roll_no,
                'name' => $student->name,
                'school_class_id' => $student->school_class_id,
                'section_id' => $student->section_id,
                'subjects' => $subjectRows,
                'columns' => $columns,
                'summary' => self::buildMarksSummary($subjectRows, $columns),
                'obtained' => round($overallObtained, 2),
                'max_total' => round($overallMax, 2),
                'percentage' => $percentage,
                'grade' => $grade?->grade,
                'result' => $percentage >= self::PASS_PERCENTAGE ? 'Pass' : 'Fail',
                'mapping_name' => 'Annual Examination Report Card',
                'session_id' => $session->id,
                'co_scholastic' => $co,
                'remarks' => $remarksByStudent->get($student->id)?->remarks,
            ];
        });

        $ranked = collect();
        foreach ($rows->groupBy('school_class_id') as $classRows) {
            foreach ($classRows->sortByDesc('percentage')->values() as $i => $row) {
                $row['rank'] = $i + 1;
                $ranked->push($row);
            }
        }

        return [
            'columns' => $columns,
            'rows' => $ranked->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, AcademicTerm>  $terms
     * @return list<array<string, mixed>>
     */
    private static function buildColumns(Collection $terms): array
    {
        $columns = [];
        $termIndex = 0;
        foreach ($terms as $term) {
            $termIndex++;
            $exams = $term->exams;
            if ($exams->isEmpty()) {
                continue;
            }

            $internals = $exams->filter(fn (Exam $e) => $e->is_internal_component)->values();
            $boards = $exams->reject(fn (Exam $e) => $e->is_internal_component)->values();
            $childCols = [];

            foreach ($internals as $exam) {
                $max = (int) round($exam->defaultSubjectMaxMarks());
                $childCols[] = [
                    'key' => 'exam_'.$exam->id,
                    'label' => self::shortExamLabel($exam->name, $termIndex, $max, true),
                    'type' => 'exam',
                    'exam_id' => $exam->id,
                    'role' => 'internal',
                ];
            }

            if ($internals->isNotEmpty()) {
                $testMax = (int) round($internals->sum(fn (Exam $e) => $e->defaultSubjectMaxMarks()));
                $childCols[] = [
                    'key' => 'test_'.$term->id,
                    'label' => 'TEST-'.$termIndex.' TOTAL ('.$testMax.')',
                    'type' => 'test_total',
                    'role' => 'test',
                ];
            }

            foreach ($boards as $exam) {
                $max = (int) round($exam->defaultSubjectMaxMarks(80));
                $childCols[] = [
                    'key' => 'exam_'.$exam->id,
                    'label' => self::shortExamLabel($exam->name, $termIndex, $max, false),
                    'type' => 'exam',
                    'exam_id' => $exam->id,
                    'role' => 'board',
                ];
            }

            $childCols[] = [
                'key' => 'term_total_'.$term->id,
                'label' => 'TOTAL (100)',
                'type' => 'term_total',
                'role' => 'term_total',
            ];

            $columns[] = [
                'term_id' => $term->id,
                'term_name' => 'TERM - '.$termIndex,
                'term_index' => $termIndex,
                'max_marks' => (float) ($term->max_marks ?: 100),
                'children' => $childCols,
            ];
        }

        if ($columns !== []) {
            $columns[] = [
                'term_id' => null,
                'term_name' => 'OVERALL',
                'term_index' => null,
                'max_marks' => 100.0,
                'children' => [
                    ['key' => 'overall', 'label' => 'TOTAL (100)', 'type' => 'overall', 'role' => 'overall'],
                    ['key' => 'overall_grade', 'label' => 'Grade', 'type' => 'grade', 'role' => 'grade'],
                ],
            ];
        }

        return $columns;
    }

    private static function shortExamLabel(string $name, int $termIndex, int $max, bool $internal): string
    {
        $upper = mb_strtoupper($name);
        if (preg_match('/\bPT\b/', $upper)) {
            return 'PT-'.$termIndex.' ('.$max.')';
        }
        if (preg_match('/\bNB\b/', $upper)) {
            return 'NB-'.$termIndex.' ('.$max.')';
        }
        if (preg_match('/\bSEA\b/', $upper)) {
            return 'SEA-'.$termIndex.' ('.$max.')';
        }
        if (preg_match('/HALF\s*YEAR|HALF-YEAR|\bHY\b/i', $name)) {
            return 'HALF YEARLY ('.$max.')';
        }
        if (preg_match('/ANNUAL|\bANNU/i', $name)) {
            return 'ANNUAL ('.$max.')';
        }

        $short = mb_strlen($name) > 18 ? mb_substr($name, 0, 16).'…' : $name;

        return $short.' ('.$max.')';
    }

    /**
     * @param  array<string, ExamSchedule>  $scheduleIndex
     * @return array<string, mixed>
     */
    private static function termSubjectBlock(
        Student $student,
        int $subjectId,
        AcademicTerm $term,
        array $scheduleIndex,
        Collection $studentMarks
    ): array {
        $cells = [];
        $totalObt = 0.0;
        $totalMax = 0.0;
        $any = false;
        $testObt = 0.0;
        $testMax = 0.0;
        $testAny = false;

        $boardObt = 0.0;
        $boardAny = false;

        foreach ($term->exams as $exam) {
            $idx = $exam->id.'|'.$student->school_class_id.'|'.$subjectId;
            $schedule = $scheduleIndex[$idx] ?? null;
            $max = $schedule
                ? (float) $schedule->max_marks
                : $exam->defaultSubjectMaxMarks();
            $mark = $schedule
                ? $studentMarks->firstWhere('exam_schedule_id', $schedule->id)
                : null;
            $absent = (bool) ($mark?->is_absent);
            $obt = (! $absent && $mark?->marks_obtained !== null) ? (float) $mark->marks_obtained : null;

            $cells['exam_'.$exam->id] = $absent ? 'Ab' : $obt;
            if ($absent) {
                continue;
            }

            $totalMax += $max;
            if ($obt !== null) {
                $totalObt += $obt;
                $any = true;
            }
            if ($exam->is_internal_component) {
                $testMax += $max;
                if ($obt !== null) {
                    $testObt += $obt;
                    $testAny = true;
                }
            } elseif ($obt !== null) {
                $boardObt += $obt;
                $boardAny = true;
            }
        }

        return [
            'term_id' => $term->id,
            'term_name' => $term->name,
            'cells' => $cells,
            'test_total' => $testAny ? round($testObt, 2) : null,
            'test_max' => $testMax > 0 ? $testMax : null,
            'board_total' => $boardAny ? round($boardObt, 2) : null,
            'total' => $any ? round($totalObt, 2) : null,
            'total_max' => $totalMax > 0 ? $totalMax : (float) $term->max_marks,
        ];
    }

    /**
     * Cross-subject sums for the small footer: Exam | Test-1 | Half Yearly | Total | …
     *
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $columns
     * @return array{headers: list<string>, values: list<string|float>}
     */
    private static function buildMarksSummary(array $subjectRows, array $columns): array
    {
        $headers = ['Exam'];
        $values = ['Marks'];

        $termIndex = 0;
        foreach ($columns as $group) {
            if (($group['term_name'] ?? '') === 'OVERALL' || ($group['term_id'] ?? null) === null) {
                continue;
            }
            $termIndex++;
            $termId = (int) $group['term_id'];
            $boardLabel = 'Board';
            foreach ($group['children'] ?? [] as $child) {
                if (($child['role'] ?? '') === 'board') {
                    $raw = preg_replace('/\s*\(\d+\)\s*$/', '', (string) ($child['label'] ?? 'Board')) ?: 'Board';
                    $boardLabel = ucwords(strtolower(trim($raw)));
                    break;
                }
            }

            $testSum = 0.0;
            $boardSum = 0.0;
            $totalSum = 0.0;
            $hasTest = false;
            $hasBoard = false;
            $hasTotal = false;

            foreach ($subjectRows as $subject) {
                $block = collect($subject['terms'] ?? [])->firstWhere('term_id', $termId);
                if (! $block) {
                    continue;
                }
                if ($block['test_total'] !== null) {
                    $testSum += (float) $block['test_total'];
                    $hasTest = true;
                }
                if (($block['board_total'] ?? null) !== null) {
                    $boardSum += (float) $block['board_total'];
                    $hasBoard = true;
                }
                if ($block['total'] !== null) {
                    $totalSum += (float) $block['total'];
                    $hasTotal = true;
                }
            }

            $headers[] = 'Test-'.$termIndex;
            $values[] = $hasTest ? round($testSum, 2) : '—';
            $headers[] = $boardLabel;
            $values[] = $hasBoard ? round($boardSum, 2) : '—';
            $headers[] = 'Total';
            $values[] = $hasTotal ? round($totalSum, 2) : '—';
        }

        return ['headers' => $headers, 'values' => $values];
    }

    private static function presentCoScholastic(Collection $grades, Collection $terms): array
    {
        $byArea = [];
        foreach (CoScholasticGrade::AREAS as $key => $label) {
            $byArea[$key] = [
                'area' => $key,
                'label' => $label,
                'terms' => [],
            ];
            foreach ($terms as $term) {
                $row = $grades->first(fn ($g) => (int) $g->academic_term_id === (int) $term->id && $g->area === $key);
                $byArea[$key]['terms'][] = [
                    'term_id' => $term->id,
                    'term_name' => $term->name,
                    'grade' => $row?->grade,
                ];
            }
        }

        return array_values($byArea);
    }
}
