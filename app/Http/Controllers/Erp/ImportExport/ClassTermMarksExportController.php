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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Exports marks in the same "wide" marksheet shape that ClassTermMarksImportController's
 * new-format import reads: one MARKSHEET sheet covering every class, with Adm. No./Name/
 * Class/Roll No + a per-subject block of PT1/NB1/SEA1/TOT(20)/HY-or-ANNU(80)/TOT(100)
 * columns, plus a GRADE sheet for co-scholastic grades.
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
            : SchoolClass::query()->orderBy('sort_order')->orderBy('id')->get();

        if ($classes->isEmpty()) {
            abort(422, 'No classes found to export.');
        }

        $terms = isset($data['term']) ? [(int) $data['term']] : [1, 2];
        $sessionLabel = $this->sessionShortLabel($session);
        $classToken = isset($data['school_class_id']) ? $this->classToken($classes->first()->name) : 'ALL-CLASSES';

        $files = [];
        foreach ($terms as $termNo) {
            $spreadsheet = $this->buildWideMarksheetWorkbook($session, $classes, $termNo, $classToken);
            if ($spreadsheet === null) {
                continue;
            }
            $filename = sprintf('MARKSHEET_%s_TERM-%d_%s.xlsx', $classToken, $termNo, $sessionLabel);
            $tmp = tempnam(sys_get_temp_dir(), 'marks_exp_');
            (new Xlsx($spreadsheet))->save($tmp);
            $spreadsheet->disconnectWorksheets();
            $files[$filename] = $tmp;
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

    /**
     * @param  \Illuminate\Support\Collection<int, SchoolClass>  $classes
     */
    private function buildWideMarksheetWorkbook(AcademicSession $session, $classes, int $termNo, string $classToken): ?Spreadsheet
    {
        $boardKind = $termNo === 2 ? 'annual' : 'hy';
        $boardLabel = $termNo === 2 ? 'ANNU' : 'HY';

        $pt = $this->findExam($session, 'pt', $termNo, 'PT-'.$termNo);
        $nb = $this->findExam($session, 'nb', $termNo, 'NB-'.$termNo);
        $sea = $this->findExam($session, 'sea', $termNo, 'SEA-'.$termNo);
        $board = $this->findExam($session, $boardKind, $termNo, $boardLabel);

        $examIds = array_values(array_filter([$pt?->id, $nb?->id, $sea?->id, $board?->id]));
        $classIds = $classes->pluck('id')->all();

        $subjects = collect();
        $scheduleIndex = [];
        if ($examIds !== []) {
            $schedules = ExamSchedule::query()
                ->with('subject:id,name')
                ->whereIn('exam_id', $examIds)
                ->whereIn('school_class_id', $classIds)
                ->get();

            $subjects = $schedules->pluck('subject')->filter()->unique('id')->sortBy('id')->values();
            foreach ($schedules as $sc) {
                $scheduleIndex[$sc->exam_id][$sc->school_class_id][$sc->subject_id] = $sc->id;
            }
        }

        $students = Student::query()
            ->whereIn('school_class_id', $classIds)
            ->with('schoolClass:id,name,sort_order')
            ->get(['id', 'admission_no', 'roll_no', 'name', 'school_class_id']);

        if ($students->isEmpty()) {
            return null;
        }

        // Collection::sortBy is stable, so chaining from least- to most-significant key
        // (rather than passing an array of key-extractor closures, which sortBy() does not
        // support the way one might expect) yields a correct class → roll no → name ordering.
        $students = $students
            ->sortBy(fn ($s) => $s->name)
            ->sortBy(fn ($s) => is_numeric($s->roll_no) ? (int) $s->roll_no : PHP_INT_MAX)
            ->sortBy(fn ($s) => $s->schoolClass?->sort_order ?? PHP_INT_MAX)
            ->values();

        $marksByStudent = [];
        if ($subjects->isNotEmpty()) {
            $scheduleIds = [];
            foreach ($scheduleIndex as $byClass) {
                foreach ($byClass as $bySubject) {
                    $scheduleIds = array_merge($scheduleIds, array_values($bySubject));
                }
            }
            if ($scheduleIds !== []) {
                $rows = Mark::query()
                    ->whereIn('exam_schedule_id', array_unique($scheduleIds))
                    ->whereIn('student_id', $students->pluck('id'))
                    ->get(['exam_schedule_id', 'student_id', 'marks_obtained', 'is_absent']);
                foreach ($rows as $mark) {
                    $marksByStudent[$mark->student_id][$mark->exam_schedule_id] = $mark;
                }
            }
        }

        $sessionLabel = $this->sessionShortLabel($session);
        $banner = sprintf('MARKSHEET_%s_TERM-%d_%s', $classToken, $termNo, $sessionLabel);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('MARKSHEET');

        $ptLabel = $termNo === 2 ? 'PT2' : 'PT1';
        $nbLabel = $termNo === 2 ? 'NB2' : 'NB1';
        $seaLabel = $termNo === 2 ? 'SEA2' : 'SEA1';
        $components = ["{$ptLabel} (10)", "{$nbLabel} (05)", "{$seaLabel} (05)", 'TOT (20)', "{$boardLabel} (80)", 'TOT (100)'];

        $sheet->setCellValue('A1', 'Adm. No.');
        $sheet->setCellValue('B1', 'Name');
        $sheet->setCellValue('C1', 'Class');
        $sheet->setCellValue('D1', 'Roll No');
        $sheet->mergeCells('A1:A2');
        $sheet->mergeCells('B1:B2');
        $sheet->mergeCells('C1:C2');
        $sheet->mergeCells('D1:D2');

        $col = 5;
        foreach ($subjects as $subject) {
            $startLetter = Coordinate::stringFromColumnIndex($col);
            $endLetter = Coordinate::stringFromColumnIndex($col + count($components) - 1);
            $sheet->setCellValue($startLetter.'1', $subject->name);
            $sheet->mergeCells("{$startLetter}1:{$endLetter}1");
            $c = $col;
            foreach ($components as $label) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c).'2', $label);
                $c++;
            }
            $col += count($components);
        }
        $lastCol = Coordinate::stringFromColumnIndex(max($col - 1, 4));
        $sheet->getStyle("A1:{$lastCol}2")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}2")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');

        $row = 3;
        foreach ($students as $student) {
            $values = [$student->admission_no, $student->name, $student->schoolClass?->name, $student->roll_no];

            foreach ($subjects as $subject) {
                $ptVal = $this->markCellValue($marksByStudent, $student->id, $scheduleIndex[$pt?->id][$student->school_class_id][$subject->id] ?? null);
                $nbVal = $this->markCellValue($marksByStudent, $student->id, $scheduleIndex[$nb?->id][$student->school_class_id][$subject->id] ?? null);
                $seaVal = $this->markCellValue($marksByStudent, $student->id, $scheduleIndex[$sea?->id][$student->school_class_id][$subject->id] ?? null);
                $boardVal = $this->markCellValue($marksByStudent, $student->id, $scheduleIndex[$board?->id][$student->school_class_id][$subject->id] ?? null);

                $tot20 = $this->sumNumeric([$ptVal, $nbVal, $seaVal]);
                $tot100 = $this->sumNumeric([$tot20, $boardVal]);

                array_push($values, $ptVal, $nbVal, $seaVal, $tot20, $boardVal, $tot100);
            }

            $sheet->fromArray($values, null, "A{$row}");
            $row++;
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

        $gradeSheet = $spreadsheet->createSheet();
        $gradeSheet->setTitle('GRADE');
        $this->writeGradeSheet($gradeSheet, $banner, $sessionLabel, $students, $term);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /** @return float|string|null */
    private function markCellValue(array $marksByStudent, int $studentId, ?int $scheduleId)
    {
        if (! $scheduleId) {
            return null;
        }
        $mark = $marksByStudent[$studentId][$scheduleId] ?? null;
        if (! $mark) {
            return null;
        }
        if ($mark->is_absent) {
            return 'Ab';
        }

        return $mark->marks_obtained !== null ? (float) $mark->marks_obtained : null;
    }

    /** @param  list<float|string|null>  $values */
    private function sumNumeric(array $values): ?float
    {
        $sum = 0.0;
        $any = false;
        foreach ($values as $v) {
            if (is_numeric($v)) {
                $sum += (float) $v;
                $any = true;
            }
        }

        return $any ? round($sum, 2) : null;
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
