<?php

namespace App\Http\Controllers\Erp\Exams;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\CoScholasticGrade;
use App\Models\Exam;
use App\Models\ReportCardRemark;
use App\Models\Student;
use App\Services\AnnualReportCalculator;
use App\Services\DocumentDataBuilder;
use App\Services\DocumentRenderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class AnnualReportController extends Controller
{
    public function __construct(
        private DocumentDataBuilder $dataBuilder,
        private DocumentRenderService $renderer,
    ) {}

    public function results(Request $request)
    {
        $session = AcademicSession::fromRequest($request, true);
        abort_unless($session, 422, 'Select an academic session first.');

        $data = $request->validate([
            'school_class_id' => 'nullable|exists:school_classes,id',
            'branch_id' => 'nullable|exists:branches,id',
            'section_id' => 'nullable|exists:sections,id',
        ]);

        $payload = AnnualReportCalculator::forSession(
            $session,
            $data['school_class_id'] ?? null,
            $data['branch_id'] ?? null,
            $data['section_id'] ?? null
        );

        return response()->json($payload);
    }

    public function downloadStudentPdf(Request $request, int $student): StreamedResponse
    {
        $session = AcademicSession::fromRequest($request, true);
        abort_unless($session, 422, 'Select an academic session first.');

        $studentModel = Student::findOrFail($student);
        $payload = AnnualReportCalculator::forSession(
            $session,
            $studentModel->school_class_id,
            $studentModel->branch_id,
            $studentModel->section_id
        );
        $row = collect($payload['rows'])->firstWhere('student_id', $student);
        abort_unless($row, 404, 'No annual result found for this student.');

        $exam = $this->anchorExam($session);

        return $this->renderer->streamPdf(
            'report_card',
            $this->dataBuilder->reportCard($exam, $row),
            'annual-report-card-'.($row['admission_no'] ?? $student).'.pdf'
        );
    }

    /**
     * The exam whose pdf_accent_color actually controls the Annual Result PDFs (see
     * anchorExam() below) — exposed so the Exam Results page can offer a PDF colour picker for
     * the Annual Result tab, not just Individual Exam.
     */
    public function anchorExamInfo(Request $request)
    {
        $session = AcademicSession::fromRequest($request, true);
        abort_unless($session, 422, 'Select an academic session first.');

        $exam = $this->anchorExam($session);

        return response()->json(['id' => $exam->id, 'name' => $exam->name, 'pdf_accent_color' => $exam->pdf_accent_color]);
    }

    public function downloadZip(Request $request): StreamedResponse
    {
        $session = AcademicSession::fromRequest($request, true);
        abort_unless($session, 422, 'Select an academic session first.');

        $data = $request->validate([
            'school_class_id' => 'nullable|exists:school_classes,id',
            'branch_id' => 'nullable|exists:branches,id',
            'section_id' => 'nullable|exists:sections,id',
        ]);

        $payload = AnnualReportCalculator::forSession(
            $session,
            $data['school_class_id'] ?? null,
            $data['branch_id'] ?? null,
            $data['section_id'] ?? null
        );
        $rows = $payload['rows'];
        abort_if($rows === [], 404, 'No annual results to download.');

        $exam = $this->anchorExam($session);
        $zipPath = tempnam(sys_get_temp_dir(), 'annual-rc-');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        foreach ($rows as $row) {
            $binary = $this->renderer->pdfBinary('report_card', $this->dataBuilder->reportCard($exam, $row));
            $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($row['admission_no'] ?? $row['student_id'])) ?: 'student';
            $zip->addFromString("annual-report-card-{$safe}.pdf", $binary);
        }
        $zip->close();

        return response()->streamDownload(function () use ($zipPath) {
            readfile($zipPath);
            @unlink($zipPath);
        }, 'annual-report-cards.zip', ['Content-Type' => 'application/zip']);
    }

    public function saveCoScholastic(Request $request)
    {
        $data = $request->validate([
            'records' => 'required|array|min:1',
            'records.*.student_id' => 'required|exists:students,id',
            'records.*.academic_term_id' => 'required|exists:academic_terms,id',
            'records.*.area' => 'required|string|in:work_education,drawing_art,sports',
            'records.*.grade' => 'nullable|string|max:16',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['records'] as $record) {
                $grade = trim((string) ($record['grade'] ?? ''));
                if ($grade === '') {
                    CoScholasticGrade::query()
                        ->where('student_id', $record['student_id'])
                        ->where('academic_term_id', $record['academic_term_id'])
                        ->where('area', $record['area'])
                        ->delete();
                    continue;
                }
                CoScholasticGrade::query()->updateOrCreate(
                    [
                        'student_id' => $record['student_id'],
                        'academic_term_id' => $record['academic_term_id'],
                        'area' => $record['area'],
                    ],
                    ['grade' => $grade]
                );
            }
        });

        return response()->json(['success' => true]);
    }

    public function saveRemarks(Request $request)
    {
        $session = AcademicSession::fromRequest($request, true);
        abort_unless($session, 422, 'Select an academic session first.');

        $data = $request->validate([
            'records' => 'required|array|min:1',
            'records.*.student_id' => 'required|exists:students,id',
            'records.*.remarks' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($data, $session) {
            foreach ($data['records'] as $record) {
                $remarks = trim((string) ($record['remarks'] ?? ''));
                if ($remarks === '') {
                    ReportCardRemark::query()
                        ->where('student_id', $record['student_id'])
                        ->where('academic_session_id', $session->id)
                        ->delete();
                    continue;
                }
                ReportCardRemark::query()->updateOrCreate(
                    [
                        'student_id' => $record['student_id'],
                        'academic_session_id' => $session->id,
                    ],
                    ['remarks' => $remarks]
                );
            }
        });

        return response()->json(['success' => true]);
    }

    private function anchorExam(AcademicSession $session): Exam
    {
        $exam = Exam::query()
            ->where('academic_session_id', $session->id)
            ->where(function ($q) {
                $q->where('name', 'like', '%Annual%')
                    ->orWhere('name', 'like', '%Half Year%');
            })
            ->orderByDesc('id')
            ->first();

        if ($exam) {
            return $exam;
        }

        return Exam::query()->firstOrCreate(
            ['name' => 'Annual Examination Report Card', 'academic_session_id' => $session->id],
            ['status' => 'Scheduled', 'type' => 'Annual Report', 'total_marks' => 100, 'passing_marks' => 33]
        );
    }
}
