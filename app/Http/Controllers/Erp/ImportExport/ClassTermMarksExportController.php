<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\CoScholasticGrade;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\ImportExportLog;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Exports marks in the same CLASS_*_TERM-* workbook shape that ClassTermMarksImportController imports:
 * sheets PT-n / NB-n / SEA-n / HY|ANNU / GRADE with Adm. No. + subject columns.
 */
class ClassTermMarksExportController extends Controller
{
    /** School class name → filename/sheet token. */
    private const CLASS_TOKEN_MAP = [
        'nursery' => 'NUR',
        'nur' => 'NUR',
        'lkg' => 'LKG',
        'ukg' => 'UKG',
        '1' => '1st',
        '2' => '2nd',
        '3' => '3rd',
        '4' => '4th',
        '5' => '5th',
        '6' => '6th',
        '7' => '7th',
        '8' => '8th',
    ];

    public function download(Request $request): StreamedResponse
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $data = $request->validate([
            'academic_session_id' => 'nullable|integer|exists:academic_sessions,id',
            'school_class_id' => 'nullable|integer|exists:school_classes,id',
            'term' => 'nullable|integer|in:1,2',
        ]);

        $session = isset($data['academic_session_id'])
            ? AcademicSession::query()->findOrFail($data['academic_session_id'])
            : AcademicSession::query()->where('is_current', true)->first()
                ?? AcademicSession::query()->orderByDesc('id')->first();

        if (! $session) {
            abort(422, 'No academic session found.');
        }

        $classes = isset($data['school_class_id'])
            ? SchoolClass::query()->where('id', $data['school_class_id'])->get()
            : SchoolClass::query()->orderBy('id')->get();

        $terms = isset($data['term']) ? [(int) $data['term']] : [1, 2];

        $files = [];
        foreach ($classes as $class) {
            foreach ($terms as $termNo) {
                $spreadsheet = $this->buildClassTermWorkbook($session, $class, $termNo);
                if ($spreadsheet === null) {
                    continue;
                }
                $token = $this->classToken($class->name);
                $sessionLabel = $this->sessionShortLabel($session);
                $filename = sprintf('CLASS_%s_TERM-%d_%s.xlsx', $token, $termNo, $sessionLabel);
                $tmp = tempnam(sys_get_temp_dir(), 'marks_exp_');
                (new Xlsx($spreadsheet))->save($tmp);
                $spreadsheet->disconnectWorksheets();
                $files[$filename] = $tmp;
            }
        }

        if ($files === []) {
            abort(422, 'No exam marks found to export for the selected session/class/term.');
        }

        $userId = Auth::guard('erp')->id();

        if (count($files) === 1) {
            $filename = array_key_first($files);
            $path = $files[$filename];
            ImportExportLog::query()->create([
                'direction' => 'Export',
                'entity' => 'exam-marks',
                'filename' => $filename,
                'total_rows' => 1,
                'success_count' => 1,
                'failed_count' => 0,
                'performed_by_id' => $userId,
            ]);

            return response()->streamDownload(function () use ($path) {
                echo file_get_contents($path);
                @unlink($path);
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        $zipName = 'exam-marks-export-'.$this->sessionShortLabel($session).'-'.now()->format('Ymd-His').'.zip';
        $zipPath = tempnam(sys_get_temp_dir(), 'marks_zip_');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        foreach ($files as $name => $path) {
            $zip->addFile($path, $name);
        }
        $zip->close();
        foreach ($files as $path) {
            @unlink($path);
        }

        ImportExportLog::query()->create([
            'direction' => 'Export',
            'entity' => 'exam-marks',
            'filename' => $zipName,
            'total_rows' => count($files),
            'success_count' => count($files),
            'failed_count' => 0,
            'performed_by_id' => $userId,
        ]);

        return response()->streamDownload(function () use ($zipPath) {
            echo file_get_contents($zipPath);
            @unlink($zipPath);
        }, $zipName, [
            'Content-Type' => 'application/zip',
        ]);
    }

    private function buildClassTermWorkbook(AcademicSession $session, SchoolClass $class, int $termNo): ?Spreadsheet
    {
        $sheetSpecs = $termNo === 2
            ? [
                ['kind' => 'pt', 'title' => 'PT-2', 'max' => 10.0],
                ['kind' => 'nb', 'title' => 'NB-2', 'max' => 5.0],
                ['kind' => 'sea', 'title' => 'SEA-2', 'max' => 5.0],
                ['kind' => 'annual', 'title' => 'ANNU', 'max' => 80.0],
                ['kind' => 'grade', 'title' => 'GRADE', 'max' => null],
            ]
            : [
                ['kind' => 'pt', 'title' => 'PT-1', 'max' => 10.0],
                ['kind' => 'nb', 'title' => 'NB-1', 'max' => 5.0],
                ['kind' => 'sea', 'title' => 'SEA-1', 'max' => 5.0],
                ['kind' => 'hy', 'title' => 'HY', 'max' => 80.0],
                ['kind' => 'grade', 'title' => 'GRADE', 'max' => null],
            ];

        $students = Student::query()
            ->where('school_class_id', $class->id)
            ->orderByRaw('CASE WHEN roll_no IS NULL OR roll_no = "" THEN 1 ELSE 0 END')
            ->orderBy('roll_no')
            ->orderBy('name')
            ->get(['id', 'admission_no', 'roll_no', 'name']);

        if ($students->isEmpty()) {
            return null;
        }

        $term = AcademicTerm::query()
            ->where('academic_session_id', $session->id)
            ->where(function ($q) use ($termNo) {
                $q->where('name', 'like', '%Term-'.$termNo.'%')
                    ->orWhere('name', 'like', '%Term '.$termNo.'%')
                    ->orWhere('name', 'like', 'T'.$termNo.'%');
            })
            ->orderBy('id')
            ->first();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        $wroteAny = false;
        $token = $this->classToken($class->name);
        $sessionLabel = $this->sessionShortLabel($session);
        $banner = sprintf('CLASS_%s_TERM-%d_%s', $token, $termNo, $sessionLabel);

        foreach ($sheetSpecs as $spec) {
            if ($spec['kind'] === 'grade') {
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle($spec['title']);
                $this->writeGradeSheet($sheet, $banner, $sessionLabel, $students, $term);
                $wroteAny = true;

                continue;
            }

            $exam = $this->findExam($session, $spec['kind'], $termNo, $spec['title']);
            if (! $exam) {
                // Still emit an empty template sheet so the workbook stays import-compatible.
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle($spec['title']);
                $this->writeMarksSheet($sheet, $banner, $sessionLabel, $spec, $students, [], []);
                $wroteAny = true;

                continue;
            }

            $schedules = ExamSchedule::query()
                ->with('subject:id,name,code')
                ->where('exam_id', $exam->id)
                ->where('school_class_id', $class->id)
                ->orderBy('id')
                ->get();

            $subjects = [];
            foreach ($schedules as $schedule) {
                $label = $schedule->subject?->code ?: $schedule->subject?->name;
                if ($label) {
                    $subjects[$schedule->id] = $label;
                }
            }

            $marksByStudent = [];
            if ($schedules->isNotEmpty()) {
                $rows = Mark::query()
                    ->whereIn('exam_schedule_id', $schedules->pluck('id'))
                    ->whereIn('student_id', $students->pluck('id'))
                    ->get(['exam_schedule_id', 'student_id', 'marks_obtained', 'is_absent']);
                foreach ($rows as $mark) {
                    $marksByStudent[$mark->student_id][$mark->exam_schedule_id] = $mark;
                }
            }

            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($spec['title']);
            $this->writeMarksSheet($sheet, $banner, $sessionLabel, $spec, $students, $subjects, $marksByStudent);
            $wroteAny = true;
        }

        if (! $wroteAny) {
            return null;
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * @param  list<array{kind:string,title:string,max:?float}>  $spec
     * @param  \Illuminate\Support\Collection<int, Student>  $students
     * @param  array<int, string>  $subjects schedule_id => column label
     * @param  array<int, array<int, Mark>>  $marksByStudent
     */
    private function writeMarksSheet(
        $sheet,
        string $banner,
        string $sessionLabel,
        array $spec,
        $students,
        array $subjects,
        array $marksByStudent
    ): void {
        $sheet->setCellValue('A1', $banner);
        $sheet->setCellValue('A2', $spec['max'] !== null ? 'Max marks: '.$spec['max'] : $spec['title']);
        $sheet->setCellValue('A3', 'Session: '.$sessionLabel);

        $headers = array_merge(['Adm. No.', 'Roll', 'Name'], array_values($subjects));
        $sheet->fromArray($headers, null, 'A4');
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A4:{$lastCol}4")->getFont()->setBold(true);
        $sheet->getStyle("A4:{$lastCol}4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');

        $scheduleIds = array_keys($subjects);
        $row = 5;
        foreach ($students as $student) {
            $values = [
                $student->admission_no,
                $student->roll_no,
                $student->name,
            ];
            foreach ($scheduleIds as $scheduleId) {
                $mark = $marksByStudent[$student->id][$scheduleId] ?? null;
                if (! $mark) {
                    $values[] = null;
                } elseif ($mark->is_absent) {
                    $values[] = 'Ab';
                } else {
                    $values[] = $mark->marks_obtained !== null ? (float) $mark->marks_obtained : null;
                }
            }
            $sheet->fromArray($values, null, "A{$row}");
            $row++;
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Student>  $students
     */
    private function writeGradeSheet($sheet, string $banner, string $sessionLabel, $students, ?AcademicTerm $term): void
    {
        $sheet->setCellValue('A1', $banner);
        $sheet->setCellValue('A2', 'Co-scholastic grades (A/B/C)');
        $sheet->setCellValue('A3', 'Session: '.$sessionLabel);

        $areas = CoScholasticGrade::AREAS;
        $headers = array_merge(['Adm. No.', 'Roll', 'Name'], array_values($areas));
        $sheet->fromArray($headers, null, 'A4');
        $sheet->getStyle('A4:F4')->getFont()->setBold(true);
        $sheet->getStyle('A4:F4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');

        $gradesByStudent = [];
        if ($term) {
            $rows = CoScholasticGrade::query()
                ->where('academic_term_id', $term->id)
                ->whereIn('student_id', $students->pluck('id'))
                ->get(['student_id', 'area', 'grade']);
            foreach ($rows as $g) {
                $gradesByStudent[$g->student_id][$g->area] = $g->grade;
            }
        }

        $row = 5;
        foreach ($students as $student) {
            $values = [$student->admission_no, $student->roll_no, $student->name];
            foreach (array_keys($areas) as $area) {
                $values[] = $gradesByStudent[$student->id][$area] ?? null;
            }
            $sheet->fromArray($values, null, "A{$row}");
            $row++;
        }
    }

    private function findExam(AcademicSession $session, string $kind, int $termNo, string $sheetTitle): ?Exam
    {
        $query = Exam::query()->where('academic_session_id', $session->id);

        if ($kind === 'hy') {
            return (clone $query)->where(function ($q) {
                $q->where('name', 'like', '%Half Year%')
                    ->orWhere('name', 'like', '%Half-Year%')
                    ->orWhere('name', 'like', '%Mid Term%')
                    ->orWhere('name', 'like', '%HY%');
            })->orderBy('id')->first();
        }

        if ($kind === 'annual') {
            return (clone $query)->where(function ($q) {
                $q->where('name', 'like', '%Annual%')
                    ->orWhere('name', 'like', '%ANNU%');
            })->orderBy('id')->first();
        }

        $canonical = match ($kind) {
            'pt' => 'PT-'.$termNo,
            'nb' => 'NB-'.$termNo,
            'sea' => 'SEA-'.$termNo,
            default => $sheetTitle,
        };

        return (clone $query)->where('name', $canonical)->orderBy('id')->first()
            ?? (clone $query)->where('name', 'like', $canonical.'%')->orderBy('id')->first();
    }

    private function classToken(string $className): string
    {
        $key = strtolower(trim($className));
        $key = preg_replace('/\s+/', '', $key) ?? $key;

        return self::CLASS_TOKEN_MAP[$key] ?? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $className) ?: 'CLASS');
    }

    private function sessionShortLabel(AcademicSession $session): string
    {
        $name = (string) $session->name;
        if (preg_match('/(20\d{2})\s*[-–\/]\s*(\d{2}|\d{4})/', $name, $m)) {
            $end = strlen($m[2]) === 2 ? $m[2] : substr($m[2], -2);

            return $m[1].'-'.$end;
        }

        return preg_replace('/\s+/', '', $name) ?: (string) $session->id;
    }
}
