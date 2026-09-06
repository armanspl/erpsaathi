<?php

namespace App\Http\Controllers\Erp\Exams;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Student;
use App\Services\DocumentDataBuilder;
use App\Services\DocumentRenderService;
use App\Services\ExamResultCalculator;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ExamResultController extends Controller
{
    public function __construct(
        private DocumentDataBuilder $dataBuilder,
        private DocumentRenderService $renderer,
    ) {}

    public function index(Request $request, Exam $exam)
    {
        return response()->json($this->rowsFor($request, $exam));
    }

    public function show(Exam $exam, int $student)
    {
        $rows = ExamResultCalculator::forExam($exam);
        $row = collect($rows)->firstWhere('student_id', $student);

        abort_if(! $row, 404, 'No result found for this student in this exam.');

        return response()->json($row);
    }

    /** Every exam result for one student across all exams — powers the Student Detail Exam tab. */
    public function forStudent(Student $student)
    {
        return response()->json(ExamResultCalculator::forStudent($student));
    }

    public function downloadStudentPdf(Exam $exam, int $student): StreamedResponse
    {
        $rows = ExamResultCalculator::forExam($exam);
        $row = collect($rows)->firstWhere('student_id', $student);

        abort_if(! $row, 404, 'No result found for this student in this exam.');

        $filename = 'report-card-'.($row['admission_no'] ?? $student).'.pdf';

        return $this->renderer->streamPdf('report_card', $this->dataBuilder->reportCard($exam, $row), $filename);
    }

    public function downloadSheetPdf(Request $request, Exam $exam): StreamedResponse
    {
        // Class result sheet stays a tabular export (not a single-student template).
        $rows = $this->rowsFor($request, $exam);
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $school = $this->dataBuilder->schoolContext();

        $subjectColumns = collect($rows[0]['subjects'] ?? [])->map(fn ($s) => $s['subject_name']);

        $headerRow = '<th>Roll No</th><th>Admission ID</th><th>Student Name</th>'
            .$subjectColumns->map(fn ($name) => '<th>'.$escape($name).'</th>')->implode('')
            .'<th>Total</th><th>%</th><th>Grade</th><th>Result</th>';

        $bodyRows = collect($rows)->map(function ($row) use ($subjectColumns, $escape) {
            $subjectCells = $subjectColumns->map(function ($name) use ($row) {
                $subject = collect($row['subjects'])->firstWhere('subject_name', $name);

                return '<td>'.($subject['marks_obtained'] ?? '—').'</td>';
            })->implode('');

            return '<tr><td>'.$escape($row['roll_no'] ?? '—').'</td><td>'.$escape($row['admission_no']).'</td><td>'.$escape($row['name']).'</td>'
                .$subjectCells
                .'<td>'.$row['obtained'].' / '.$row['max_total'].'</td><td>'.$row['percentage'].'%</td><td>'.$escape($row['grade'] ?? '—').'</td><td>'.$row['result'].'</td></tr>';
        })->implode('');

        $logo = ! empty($school['school_logo'])
            ? '<img src="'.$school['school_logo'].'" style="height:36pt;vertical-align:middle;margin-right:8pt" />'
            : '';

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            @page { margin: 10mm; }
            body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1e293b; }
            h1 { font-size: 14pt; margin: 0 0 2pt; }
            .sub { font-size: 8pt; color: #64748b; margin: 0 0 8pt; }
            table { width: 100%; border-collapse: collapse; margin-top: 6pt; }
            th, td { border: 0.5pt solid #94a3b8; padding: 4pt 5pt; text-align: left; }
            th { background: #1e3a5f; color: #fff; }
        </style></head><body>
            <div>'.$logo.'<span style="font-size:13pt;font-weight:bold">'.$escape($school['school_name'] ?? 'School').'</span></div>
            <h1>Exam Results — '.$escape($exam->name).'</h1>
            <p class="sub">'.$escape($school['school_address'] ?? '').'</p>
            <table><thead><tr>'.$headerRow.'</tr></thead><tbody>'.($bodyRows ?: '<tr><td colspan="99">No results found.</td></tr>').'</tbody></table>
        </body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);

        return response()->streamDownload(function () use ($html, $options) {
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('a4', 'landscape');
            $dompdf->render();
            echo $dompdf->output();
        }, 'exam-results-'.$exam->id.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function downloadZip(Request $request, Exam $exam): StreamedResponse
    {
        $rows = $this->rowsFor($request, $exam);
        abort_if(empty($rows), 404, 'No results found for the selected filters.');

        $zipPath = tempnam(sys_get_temp_dir(), 'results-zip-');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        foreach ($rows as $row) {
            $binary = $this->renderer->pdfBinary('report_card', $this->dataBuilder->reportCard($exam, $row));
            $zip->addFromString('report-card-'.($row['admission_no'] ?? $row['student_id']).'.pdf', $binary);
        }
        $zip->close();

        return response()->streamDownload(function () use ($zipPath) {
            readfile($zipPath);
            unlink($zipPath);
        }, 'exam-results-'.$exam->id.'.zip', ['Content-Type' => 'application/zip']);
    }

    private function rowsFor(Request $request, Exam $exam): array
    {
        return ExamResultCalculator::forExam(
            $exam,
            $request->filled('school_class_id') ? $request->integer('school_class_id') : null,
            $request->filled('branch_id') ? $request->integer('branch_id') : null,
            $request->filled('section_id') ? $request->integer('section_id') : null,
        );
    }
}
