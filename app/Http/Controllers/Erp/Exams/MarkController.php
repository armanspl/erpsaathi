<?php

namespace App\Http\Controllers\Erp\Exams;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\GradeSystem;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\SpreadsheetImportReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarkController extends Controller
{
    /** The full grid: sheet subjects (shared across every section of this exam+class) + this section's student roster + marks. */
    public function sheet(Request $request)
    {
        $data = $this->contextValidation($request);
        $context = $this->resolveContext($data);

        $subjects = $this->sheetSubjects($data['exam_id'], $data['school_class_id']);
        $students = $this->rosterStudents($data)->get();
        $marks = Mark::whereIn('exam_schedule_id', $subjects->pluck('id'))
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->groupBy('student_id');

        return response()->json([
            ...$context,
            'subjects' => $subjects->map(fn (ExamSchedule $s) => [
                'id' => $s->id,
                'subject_id' => $s->subject_id,
                'name' => $s->subject->name,
                'max_marks' => (float) $s->max_marks,
            ]),
            'students' => $students->map(fn (Student $student) => $this->presentStudentRow($student, $subjects, $marks->get($student->id, collect())))->values(),
        ]);
    }

    public function addSubject(Request $request)
    {
        $data = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'max_marks' => 'required|numeric|min:1|max:9999',
        ]);

        if (ExamSchedule::where('exam_id', $data['exam_id'])->where('school_class_id', $data['school_class_id'])->where('subject_id', $data['subject_id'])->exists()) {
            throw ValidationException::withMessages(['subject_id' => 'That subject is already on this sheet.']);
        }

        $schedule = ExamSchedule::create($data);

        return response()->json([
            'id' => $schedule->id,
            'subject_id' => $schedule->subject_id,
            'name' => $schedule->subject->name,
            'max_marks' => (float) $schedule->max_marks,
        ], 201);
    }

    public function removeSubject(ExamSchedule $examSchedule)
    {
        $examSchedule->delete();

        return response()->json(['success' => true]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'records' => 'required|array',
            'records.*.student_id' => 'required|exists:students,id',
            'records.*.marks' => 'array',
            'records.*.marks.*' => 'nullable|numeric|min:0',
        ]);

        $subjects = $this->sheetSubjects($data['exam_id'], $data['school_class_id'])->keyBy('subject_id');

        DB::transaction(function () use ($data, $subjects) {
            foreach ($data['records'] as $record) {
                foreach ($record['marks'] ?? [] as $subjectId => $value) {
                    $schedule = $subjects->get((int) $subjectId);
                    if (! $schedule) {
                        continue;
                    }
                    if ($value === null || $value === '') {
                        Mark::where('exam_schedule_id', $schedule->id)->where('student_id', $record['student_id'])->delete();
                        continue;
                    }
                    if ($value > $schedule->max_marks) {
                        throw ValidationException::withMessages(['marks' => "Marks for subject \"{$schedule->subject->name}\" exceed the maximum of {$schedule->max_marks}."]);
                    }
                    Mark::updateOrCreate(
                        ['exam_schedule_id' => $schedule->id, 'student_id' => $record['student_id']],
                        ['marks_obtained' => $value]
                    );
                }
            }
        });

        return response()->json(['success' => true]);
    }

    public function deleteSheet(Request $request)
    {
        $data = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'school_class_id' => 'required|exists:school_classes,id',
        ]);

        ExamSchedule::where('exam_id', $data['exam_id'])->where('school_class_id', $data['school_class_id'])->delete();

        return response()->json(['success' => true]);
    }

    public function downloadTemplate(Request $request): StreamedResponse
    {
        $data = $this->contextValidation($request, requireFormat: true);
        $context = $this->resolveContext($data);
        $subjectNames = $this->templateSubjectNames($data['school_class_id']);
        $students = $this->rosterStudents($data)->get();

        $header = ['Admission No', 'Roll No', 'Student Name', ...$subjectNames];
        $meta = [
            ['Session', $context['session_name']],
            ['Branch', $context['branch']['name'] ?? ''],
            ['Class', $context['school_class']['name'] ?? ''],
            ['Section', $context['section']['name'] ?? ''],
            ['Exam', $context['exam']['name'] ?? ''],
        ];
        $rows = $students->map(fn (Student $s) => [$s->admission_no, $s->roll_no, $s->name, ...array_fill(0, count($subjectNames), '')]);

        if ($data['format'] === 'xlsx') {
            return $this->streamXlsxTemplate($meta, $header, $rows);
        }

        return response()->streamDownload(function () use ($meta, $header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($meta as $row) {
                fputcsv($out, $row);
            }
            fputcsv($out, []);
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'marks-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $this->contextValidation($request);
        $subjects = $this->sheetSubjects($data['exam_id'], $data['school_class_id']);
        $students = $this->rosterStudents($data)->get();
        $marks = Mark::whereIn('exam_schedule_id', $subjects->pluck('id'))->whereIn('student_id', $students->pluck('id'))->get()->groupBy('student_id');

        $header = ['Admission No', 'Roll No', 'Student Name', ...$subjects->map(fn ($s) => $s->subject->name)->all(), 'Total', '%', 'Grade'];

        return response()->streamDownload(function () use ($students, $subjects, $marks, $header) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($students as $student) {
                $row = $this->presentStudentRow($student, $subjects, $marks->get($student->id, collect()));
                $line = [$student->admission_no, $student->roll_no, $student->name];
                foreach ($subjects as $s) {
                    $line[] = $row['marks'][$s->subject_id] ?? '';
                }
                $line[] = $row['total'];
                $line[] = $row['percentage'];
                $line[] = $row['grade'] ?? '-';
                fputcsv($out, $line);
            }
            fclose($out);
        }, 'marks-export.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'branch_id' => 'required|exists:branches,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ]);

        $parsed = SpreadsheetImportReader::read($request->file('file'));
        $headerRowIndex = null;
        $allRows = array_merge([$parsed['header']], $parsed['rows']);
        foreach ($allRows as $i => $row) {
            $first = strtolower(trim((string) ($row[0] ?? '')));
            if (str_contains($first, 'admission')) {
                $headerRowIndex = $i;
                break;
            }
        }
        if ($headerRowIndex === null) {
            throw ValidationException::withMessages(['file' => 'Could not find the "Admission No" header row in this file.']);
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $allRows[$headerRowIndex]);
        $dataRows = array_slice($allRows, $headerRowIndex + 1);

        $admissionCol = array_search('admission no', $header, true);
        if ($admissionCol === false) {
            $admissionCol = 0;
        }

        $classSubjects = SchoolClass::findOrFail($data['school_class_id'])->subjects()->get()->keyBy(fn ($s) => strtolower($s->name));
        $subjectColumns = [];
        foreach ($header as $col => $label) {
            $key = strtolower(trim($label));
            if (in_array($key, ['admission no', 'roll no', 'student name'], true) || $key === '') {
                continue;
            }
            if ($classSubjects->has($key)) {
                $subjectColumns[$col] = $classSubjects->get($key);
            }
        }

        if (! $subjectColumns) {
            throw ValidationException::withMessages(['file' => 'No subject columns in this file matched a subject assigned to this class.']);
        }

        $studentsByAdmission = Student::forBranch((int) $data['branch_id'])
            ->where('school_class_id', $data['school_class_id'])
            ->when(! empty($data['section_id']), fn ($q) => $q->where('section_id', $data['section_id']))
            ->get()
            ->keyBy(fn ($s) => strtolower(trim((string) $s->admission_no)));

        $exam = Exam::findOrFail($data['exam_id']);
        $defaultMax = $exam->defaultSubjectMaxMarks();
        $scheduleIds = [];
        foreach ($subjectColumns as $subject) {
            $schedule = ExamSchedule::firstOrCreate(
                ['exam_id' => $data['exam_id'], 'school_class_id' => $data['school_class_id'], 'subject_id' => $subject->id],
                ['max_marks' => $defaultMax]
            );
            $scheduleIds[$subject->id] = $schedule->id;
        }

        $imported = 0;
        $skipped = 0;
        DB::transaction(function () use ($dataRows, $admissionCol, $subjectColumns, $studentsByAdmission, $scheduleIds, &$imported, &$skipped) {
            foreach ($dataRows as $row) {
                $admissionNo = strtolower(trim((string) ($row[$admissionCol] ?? '')));
                $student = $admissionNo !== '' ? $studentsByAdmission->get($admissionNo) : null;
                if (! $student) {
                    $skipped++;
                    continue;
                }
                foreach ($subjectColumns as $col => $subject) {
                    $raw = trim((string) ($row[$col] ?? ''));
                    if ($raw === '') {
                        continue;
                    }
                    if (! is_numeric($raw)) {
                        continue;
                    }
                    Mark::updateOrCreate(
                        ['exam_schedule_id' => $scheduleIds[$subject->id], 'student_id' => $student->id],
                        ['marks_obtained' => (float) $raw]
                    );
                }
                $imported++;
            }
        });

        return response()->json(['success' => true, 'imported' => $imported, 'skipped' => $skipped]);
    }

    private function contextValidation(Request $request, bool $requireFormat = false): array
    {
        $rules = [
            'exam_id' => 'required|exists:exams,id',
            'branch_id' => 'required|exists:branches,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
        ];
        if ($requireFormat) {
            $rules['format'] = 'required|in:csv,xlsx';
        }

        return $request->validate($rules);
    }

    private function resolveContext(array $data): array
    {
        $exam = Exam::findOrFail($data['exam_id']);
        $class = SchoolClass::findOrFail($data['school_class_id']);
        $branch = \App\Models\Branch::findOrFail($data['branch_id']);
        $section = ! empty($data['section_id']) ? \App\Models\Section::find($data['section_id']) : null;
        $currentSession = AcademicSession::fromRequest(request(), true)?->name;

        return [
            'exam' => ['id' => $exam->id, 'name' => $exam->name],
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'school_class' => ['id' => $class->id, 'name' => $class->name],
            'section' => $section ? ['id' => $section->id, 'name' => $section->name] : null,
            'session_name' => $currentSession,
            'label' => trim(($exam->name ?? '') . ' · ' . ($branch->name ?? '') . ' · ' . ($class->name ?? '') . ($section ? " ({$section->name})" : '')),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, ExamSchedule> */
    private function sheetSubjects(int $examId, int $schoolClassId)
    {
        return ExamSchedule::with('subject:id,name')
            ->where('exam_id', $examId)
            ->where('school_class_id', $schoolClassId)
            ->orderBy('id')
            ->get();
    }

    private function rosterStudents(array $data)
    {
        $query = Student::where('status', 'Active')
            ->forBranch((int) $data['branch_id'])
            ->where('school_class_id', $data['school_class_id'])
            ->when(! empty($data['section_id']), fn ($q) => $q->where('section_id', $data['section_id']));

        AcademicSession::applyStudentSessionFilter($query);

        return $query->orderBy('roll_no')->orderBy('name');
    }

    private function templateSubjectNames(int $schoolClassId): array
    {
        return SchoolClass::findOrFail($schoolClassId)->subjects()->orderBy('name')->pluck('name')->all();
    }

    private function presentStudentRow(Student $student, $subjects, $studentMarks): array
    {
        $marksBySubject = $studentMarks->keyBy('exam_schedule_id');
        $marksBySubjectId = [];
        $total = 0.0;
        $maxTotal = 0.0;
        $anyEntered = false;

        foreach ($subjects as $schedule) {
            $mark = $marksBySubject->get($schedule->id);
            $maxTotal += (float) $schedule->max_marks;
            if ($mark) {
                $marksBySubjectId[$schedule->subject_id] = (float) $mark->marks_obtained;
                $total += (float) $mark->marks_obtained;
                $anyEntered = true;
            } else {
                $marksBySubjectId[$schedule->subject_id] = null;
            }
        }

        $percentage = $maxTotal > 0 ? round(($total / $maxTotal) * 100, 2) : 0;
        $grade = $anyEntered ? GradeSystem::forPercentage($percentage)?->grade : null;

        return [
            'id' => $student->id,
            'admission_no' => $student->admission_no,
            'roll_no' => $student->roll_no,
            'name' => $student->name,
            'marks' => $marksBySubjectId,
            'total' => $anyEntered ? round($total, 2) : 0,
            'percentage' => $anyEntered ? $percentage : 0,
            'grade' => $grade,
        ];
    }

    private function streamXlsxTemplate(array $meta, array $header, $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $r = 1;
        foreach ($meta as $row) {
            $sheet->fromArray($row, null, "A{$r}");
            $r++;
        }
        $r++;
        $sheet->fromArray($header, null, "A{$r}");
        $sheet->getStyle("A{$r}:" . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($header)) . $r)->getFont()->setBold(true);
        $r++;
        foreach ($rows as $row) {
            $sheet->fromArray($row, null, "A{$r}");
            $r++;
        }
        foreach (range('A', \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($header))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'marks-template.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
