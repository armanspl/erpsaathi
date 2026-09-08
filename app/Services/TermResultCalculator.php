<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\GradeSystem;
use App\Models\Mark;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Combines all assessments in a term that count toward the term total.
 * TEST total = sum of internal components (PT+NB+SEA); display-only, never double-counted.
 * Term total = internals + board (= TEST + Half Yearly / Annual).
 */
class TermResultCalculator
{
    private const PASS_PERCENTAGE = 33.0;

    /**
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, term: AcademicTerm}
     */
    public static function forTerm(
        AcademicTerm $term,
        ?int $schoolClassId = null,
        ?int $branchId = null,
        ?int $sectionId = null
    ): array {
        $exams = $term->exams()
            ->where('counts_toward_term', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $columns = self::buildColumns($term, $exams);
        if ($exams->isEmpty()) {
            return ['columns' => $columns, 'rows' => [], 'term' => $term];
        }

        return [
            'columns' => $columns,
            'rows' => self::buildRows($term, $exams, $columns, $schoolClassId, $branchId, $sectionId),
            'term' => $term,
        ];
    }

    /**
     * @param  Collection<int, Exam>  $exams
     * @param  list<array<string, mixed>>  $columns
     * @return list<array<string, mixed>>
     */
    public static function buildRows(
        AcademicTerm $term,
        Collection $exams,
        array $columns,
        ?int $schoolClassId = null,
        ?int $branchId = null,
        ?int $sectionId = null
    ): array {
        $examIds = $exams->pluck('id')->all();
        $schedules = ExamSchedule::query()
            ->whereIn('exam_id', $examIds)
            ->when($schoolClassId, fn ($q) => $q->where('school_class_id', $schoolClassId))
            ->with('subject:id,name')
            ->get();

        if ($schedules->isEmpty()) {
            return [];
        }

        $classIds = $schedules->pluck('school_class_id')->unique()->values();
        $students = Student::query()
            ->whereIn('school_class_id', $classIds)
            ->where('status', 'Active')
            ->forBranch($branchId)
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
            ->when($schoolClassId, fn ($q) => $q->where('school_class_id', $schoolClassId))
            ->get();

        if ($students->isEmpty()) {
            return [];
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
        $termIndex = self::termIndex($term);

        $rows = $students->map(function (Student $student) use (
            $marks, $subjectsByClass, $scheduleIndex, $exams, $grades, $term, $columns, $termIndex
        ) {
            $studentMarks = $marks->get($student->id, collect());
            $subjects = $subjectsByClass->get($student->school_class_id, collect());

            $subjectRows = [];
            $obtained = 0.0;
            $maxTotal = 0.0;

            foreach ($subjects as $subject) {
                $sid = (int) $subject['subject_id'];
                $cells = [];
                $subjObt = 0.0;
                $subjMax = 0.0;
                $any = false;
                $internalObt = 0.0;
                $internalMax = 0.0;
                $internalAny = false;
                $boardObt = 0.0;
                $boardAny = false;

                foreach ($exams as $exam) {
                    $idx = $exam->id.'|'.$student->school_class_id.'|'.$sid;
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

                    // Term total = PT+NB+SEA+board only (TEST is display of internals, not added again).
                    $subjMax += $max;
                    if ($obt !== null) {
                        $subjObt += $obt;
                        $any = true;
                    }

                    if ($exam->is_internal_component) {
                        $internalMax += $max;
                        if ($obt !== null) {
                            $internalObt += $obt;
                            $internalAny = true;
                        }
                    } elseif ($obt !== null) {
                        $boardObt += $obt;
                        $boardAny = true;
                    }
                }

                if ($any) {
                    $obtained += $subjObt;
                    $maxTotal += $subjMax;
                }

                $pct = ($any && $subjMax > 0) ? round(($subjObt / $subjMax) * 100, 2) : null;
                $grade = $pct !== null
                    ? ($grades->first(fn ($g) => $pct >= (float) $g->min_percentage && $pct <= (float) $g->max_percentage)?->grade)
                    : null;

                $subjectRows[] = [
                    'subject_id' => $sid,
                    'subject_name' => $subject['subject_name'],
                    'cells' => $cells,
                    'test_total' => $internalAny ? round($internalObt, 2) : null,
                    'test_max' => $internalMax > 0 ? $internalMax : null,
                    'board_total' => $boardAny ? round($boardObt, 2) : null,
                    'marks_obtained' => $any ? round($subjObt, 2) : null,
                    'max_marks' => $subjMax > 0 ? $subjMax : (float) ($term->max_marks ?: 100),
                    'grade' => $grade,
                ];
            }

            $percentage = $maxTotal > 0 ? round(($obtained / $maxTotal) * 100, 2) : 0.0;
            $grade = $grades->first(fn ($g) => $percentage >= (float) $g->min_percentage && $percentage <= (float) $g->max_percentage);

            return [
                'format' => 'term',
                'term_id' => $term->id,
                'term_name' => $term->name,
                'term_index' => $termIndex,
                'student_id' => $student->id,
                'admission_no' => $student->admission_no,
                'roll_no' => $student->roll_no,
                'name' => $student->name,
                'school_class_id' => $student->school_class_id,
                'section_id' => $student->section_id,
                'subjects' => $subjectRows,
                'columns' => $columns,
                'obtained' => round($obtained, 2),
                'max_total' => round($maxTotal, 2),
                'percentage' => $percentage,
                'grade' => $grade?->grade,
                'result' => $percentage >= self::PASS_PERCENTAGE ? 'Pass' : 'Fail',
                'mapping_name' => $term->name.' Result',
            ];
        });

        $ranked = collect();
        foreach ($rows->groupBy('school_class_id') as $classRows) {
            foreach ($classRows->sortByDesc('percentage')->values() as $i => $row) {
                $row['rank'] = $i + 1;
                $ranked->push($row);
            }
        }

        return $ranked->values()->all();
    }

    /**
     * @param  Collection<int, Exam>  $exams
     * @return list<array<string, mixed>>
     */
    private static function buildColumns(AcademicTerm $term, Collection $exams): array
    {
        $termIndex = self::termIndex($term);
        $internals = $exams->filter(fn (Exam $e) => $e->is_internal_component)->values();
        $boards = $exams->reject(fn (Exam $e) => $e->is_internal_component)->values();
        $cols = [];

        foreach ($internals as $exam) {
            $max = (int) round($exam->defaultSubjectMaxMarks());
            $cols[] = [
                'key' => 'exam_'.$exam->id,
                'label' => self::shortLabel($exam->name, $termIndex, $max, true),
                'type' => 'exam',
                'exam_id' => $exam->id,
                'role' => 'internal',
            ];
        }

        if ($internals->isNotEmpty()) {
            $testMax = (int) round($internals->sum(fn (Exam $e) => $e->defaultSubjectMaxMarks()));
            $cols[] = [
                'key' => 'test_'.$term->id,
                'label' => 'TEST-'.$termIndex.' ('.$testMax.')',
                'type' => 'test_total',
                'role' => 'test',
            ];
        }

        foreach ($boards as $exam) {
            $max = (int) round($exam->defaultSubjectMaxMarks(80));
            $cols[] = [
                'key' => 'exam_'.$exam->id,
                'label' => self::shortLabel($exam->name, $termIndex, $max, false),
                'type' => 'exam',
                'exam_id' => $exam->id,
                'role' => 'board',
            ];
        }

        $cols[] = [
            'key' => 'term_total_'.$term->id,
            'label' => 'Total ('.(int) ($term->max_marks ?: 100).')',
            'type' => 'term_total',
            'role' => 'term_total',
        ];

        return $cols;
    }

    private static function termIndex(AcademicTerm $term): int
    {
        if (preg_match('/(\d+)/', (string) $term->name, $m)) {
            return max(1, (int) $m[1]);
        }

        return max(1, (int) $term->sort_order);
    }

    private static function shortLabel(string $name, int $termIndex, int $max, bool $internal): string
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
            return 'Half Yearly ('.$max.')';
        }
        if (preg_match('/ANNUAL|\bANNU/i', $name)) {
            return 'Annual ('.$max.')';
        }

        $short = mb_strlen($name) > 18 ? mb_substr($name, 0, 16).'…' : $name;

        return $short.' ('.$max.')';
    }
}
