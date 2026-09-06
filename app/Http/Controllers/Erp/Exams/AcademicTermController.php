<?php

namespace App\Http\Controllers\Erp\Exams;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\Exam;
use App\Services\DocumentDataBuilder;
use App\Services\DocumentRenderService;
use App\Services\TermResultCalculator;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class AcademicTermController extends Controller
{
    public function __construct(
        private DocumentDataBuilder $dataBuilder,
        private DocumentRenderService $renderer,
    ) {}

    public function index(Request $request)
    {
        $session = AcademicSession::fromRequest($request, true);
        abort_unless($session, 422, 'Select an academic session first.');

        $terms = AcademicTerm::query()
            ->where('academic_session_id', $session->id)
            ->with(['exams:id,name,academic_term_id,sort_order,counts_toward_term,is_internal_component,max_marks,total_marks'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json($terms);
    }

    public function store(Request $request)
    {
        $session = AcademicSession::fromRequest($request, true);
        abort_unless($session, 422, 'Select an academic session first.');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'max_marks' => 'nullable|numeric|min:0|max:9999',
        ]);

        $term = AcademicTerm::create([
            'academic_session_id' => $session->id,
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? ((int) AcademicTerm::where('academic_session_id', $session->id)->max('sort_order') + 1),
            'max_marks' => $data['max_marks'] ?? 100,
        ]);

        return response()->json($term->load('exams'), 201);
    }

    public function update(Request $request, AcademicTerm $term)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'max_marks' => 'nullable|numeric|min:0|max:9999',
        ]);

        $term->update($data);

        return response()->json($term->fresh()->load('exams'));
    }

    public function destroy(AcademicTerm $term)
    {
        Exam::where('academic_term_id', $term->id)->update(['academic_term_id' => null]);
        $term->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Create Term-1 / Term-2 and assign existing session exams by name patterns.
     * Unit tests are left unassigned.
     */
    public function setupSession(Request $request)
    {
        $session = AcademicSession::fromRequest($request, true);
        abort_unless($session, 422, 'Select an academic session first.');

        $created = DB::transaction(function () use ($session) {
            $term1 = AcademicTerm::firstOrCreate(
                ['academic_session_id' => $session->id, 'name' => 'Term-1'],
                ['sort_order' => 1, 'max_marks' => 100]
            );
            $term2 = AcademicTerm::firstOrCreate(
                ['academic_session_id' => $session->id, 'name' => 'Term-2'],
                ['sort_order' => 2, 'max_marks' => 100]
            );

            $exams = Exam::query()->where('academic_session_id', $session->id)->get();
            $assigned = 0;

            foreach ($exams as $exam) {
                if (preg_match('/unit\s*test/i', $exam->name)) {
                    continue;
                }

                $termId = null;
                $internal = false;
                $sort = 50;
                $name = $exam->name;

                if (preg_match('/\bPT[-\s]?1\b/i', $name) || preg_match('/periodic\s*test\s*1/i', $name)) {
                    $termId = $term1->id;
                    $internal = true;
                    $sort = 10;
                } elseif (preg_match('/\bNB[-\s]?1\b/i', $name) || preg_match('/notebook\s*1/i', $name)) {
                    $termId = $term1->id;
                    $internal = true;
                    $sort = 20;
                } elseif (preg_match('/\bSEA[-\s]?1\b/i', $name)) {
                    $termId = $term1->id;
                    $internal = true;
                    $sort = 30;
                } elseif (preg_match('/half\s*year/i', $name) || preg_match('/mid\s*term/i', $name)) {
                    $termId = $term1->id;
                    $sort = 40;
                } elseif (preg_match('/\bPT[-\s]?2\b/i', $name) || preg_match('/periodic\s*test\s*2/i', $name)) {
                    $termId = $term2->id;
                    $internal = true;
                    $sort = 10;
                } elseif (preg_match('/\bNB[-\s]?2\b/i', $name) || preg_match('/notebook\s*2/i', $name)) {
                    $termId = $term2->id;
                    $internal = true;
                    $sort = 20;
                } elseif (preg_match('/\bSEA[-\s]?2\b/i', $name)) {
                    $termId = $term2->id;
                    $internal = true;
                    $sort = 30;
                } elseif (preg_match('/\bannual\b/i', $name) || preg_match('/final\s*exam/i', $name)) {
                    $termId = $term2->id;
                    $sort = 40;
                }

                if ($termId) {
                    $patch = [
                        'academic_term_id' => $termId,
                        'is_internal_component' => $internal,
                        'counts_toward_term' => true,
                        'sort_order' => $sort,
                    ];
                    if ($internal && (! $exam->max_marks || (float) $exam->max_marks >= 100)) {
                        if (preg_match('/\bPT\b/i', $name)) {
                            $patch['max_marks'] = 10;
                            $patch['total_marks'] = $exam->total_marks ?: 10;
                        } elseif (preg_match('/\bNB\b/i', $name)) {
                            $patch['max_marks'] = 5;
                            $patch['total_marks'] = $exam->total_marks ?: 5;
                        } elseif (preg_match('/\bSEA\b/i', $name)) {
                            $patch['max_marks'] = 5;
                            $patch['total_marks'] = $exam->total_marks ?: 5;
                        }
                    }
                    $exam->update($patch);

                    if (! empty($patch['max_marks'])) {
                        \App\Models\ExamSchedule::query()
                            ->where('exam_id', $exam->id)
                            ->where('max_marks', 100)
                            ->update(['max_marks' => $patch['max_marks']]);
                    }
                    $assigned++;
                }
            }

            return compact('term1', 'term2', 'assigned');
        });

        $terms = AcademicTerm::query()
            ->where('academic_session_id', $session->id)
            ->with(['exams:id,name,academic_term_id,sort_order,counts_toward_term,is_internal_component,max_marks,total_marks'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'terms' => $terms,
            'assigned' => $created['assigned'],
            'message' => "Created/updated Term-1 and Term-2; assigned {$created['assigned']} exam(s). Unit tests left standalone.",
        ]);
    }

    public function results(Request $request, AcademicTerm $term)
    {
        $data = $request->validate([
            'school_class_id' => 'nullable|exists:school_classes,id',
            'branch_id' => 'nullable|exists:branches,id',
            'section_id' => 'nullable|exists:sections,id',
        ]);

        $payload = TermResultCalculator::forTerm(
            $term,
            $data['school_class_id'] ?? null,
            $data['branch_id'] ?? null,
            $data['section_id'] ?? null
        );

        return response()->json([
            'term' => $term->load('exams:id,name,academic_term_id,sort_order,counts_toward_term,is_internal_component,max_marks'),
            'columns' => $payload['columns'],
            'rows' => $payload['rows'],
        ]);
    }

    /**
     * The exam whose pdf_accent_color actually controls this term's Report Card PDFs (see
     * anchorExam() below) — exposed so the Exam Results page can offer a PDF colour picker for
     * the Term Result / Half Yearly Result tabs, not just Individual Exam.
     */
    public function anchorExamInfo(AcademicTerm $term)
    {
        $exam = $this->anchorExam($term);

        return response()->json(['id' => $exam->id, 'name' => $exam->name, 'pdf_accent_color' => $exam->pdf_accent_color]);
    }

    public function downloadStudentPdf(Request $request, AcademicTerm $term, int $student): StreamedResponse
    {
        $payload = TermResultCalculator::forTerm(
            $term,
            $request->filled('school_class_id') ? $request->integer('school_class_id') : null,
            $request->filled('branch_id') ? $request->integer('branch_id') : null,
            $request->filled('section_id') ? $request->integer('section_id') : null
        );
        $row = collect($payload['rows'])->firstWhere('student_id', $student);
        abort_unless($row, 404, 'No term result found for this student.');

        $exam = $this->anchorExam($term);
        $pdfRow = $this->toPdfRow($row, $payload['columns'], $term);

        return $this->renderer->streamPdf(
            'report_card',
            $this->dataBuilder->reportCard($exam, $pdfRow),
            'term-report-'.($row['admission_no'] ?? $student).'.pdf'
        );
    }

    public function downloadSheetPdf(Request $request, AcademicTerm $term): StreamedResponse
    {
        $data = $request->validate([
            'school_class_id' => 'nullable|exists:school_classes,id',
            'branch_id' => 'nullable|exists:branches,id',
            'section_id' => 'nullable|exists:sections,id',
        ]);

        $payload = TermResultCalculator::forTerm(
            $term,
            $data['school_class_id'] ?? null,
            $data['branch_id'] ?? null,
            $data['section_id'] ?? null
        );
        $rows = $payload['rows'];
        $columns = $payload['columns'];
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $school = $this->dataBuilder->schoolContext();

        $headerRow = '<th>Roll No</th><th>Admission ID</th><th>Student Name</th><th>Total</th><th>%</th><th>Grade</th><th>Result</th>';
        $bodyRows = collect($rows)->map(function ($row) use ($escape) {
            return '<tr><td>'.$escape($row['roll_no'] ?? '—').'</td><td>'.$escape($row['admission_no']).'</td><td>'.$escape($row['name']).'</td>'
                .'<td>'.$row['obtained'].' / '.$row['max_total'].'</td><td>'.$row['percentage'].'%</td><td>'.$escape($row['grade'] ?? '—').'</td><td>'.$escape($row['result'] ?? '').'</td></tr>';
        })->implode('');

        $logo = ! empty($school['school_logo'])
            ? '<img src="'.$school['school_logo'].'" style="height:36pt;vertical-align:middle;margin-right:8pt" />'
            : '';

        $colHint = collect($columns)->pluck('label')->implode(', ');
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
            <h1>Term Result — '.$escape($term->name).'</h1>
            <p class="sub">'.$escape($colHint).'</p>
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
        }, 'term-results-'.$term->id.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function downloadZip(Request $request, AcademicTerm $term): StreamedResponse
    {
        $data = $request->validate([
            'school_class_id' => 'nullable|exists:school_classes,id',
            'branch_id' => 'nullable|exists:branches,id',
            'section_id' => 'nullable|exists:sections,id',
        ]);

        $payload = TermResultCalculator::forTerm(
            $term,
            $data['school_class_id'] ?? null,
            $data['branch_id'] ?? null,
            $data['section_id'] ?? null
        );
        $rows = $payload['rows'];
        abort_if($rows === [], 404, 'No term results to download.');

        $exam = $this->anchorExam($term);
        $zipPath = tempnam(sys_get_temp_dir(), 'term-rc-');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        foreach ($rows as $row) {
            $pdfRow = $this->toPdfRow($row, $payload['columns'], $term);
            $binary = $this->renderer->pdfBinary('report_card', $this->dataBuilder->reportCard($exam, $pdfRow));
            $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($row['admission_no'] ?? $row['student_id'])) ?: 'student';
            $zip->addFromString("term-report-{$safe}.pdf", $binary);
        }
        $zip->close();

        return response()->streamDownload(function () use ($zipPath) {
            readfile($zipPath);
            @unlink($zipPath);
        }, 'term-report-cards-'.$term->id.'.zip', ['Content-Type' => 'application/zip']);
    }

    /**
     * Reshape term row into annual_term PDF layout with a single term block (no Overall).
     *
     * @param  array<string, mixed>  $row
     * @param  list<array<string, mixed>>  $columns
     * @return array<string, mixed>
     */
    private function toPdfRow(array $row, array $columns, AcademicTerm $term): array
    {
        $termIndex = (int) ($row['term_index'] ?? 1);
        $row['format'] = 'annual_term';
        $row['mapping_name'] = ($term->name ?: 'Term').' Result';
        $row['columns'] = [[
            'term_id' => $term->id,
            'term_name' => 'TERM - '.$termIndex,
            'term_index' => $termIndex,
            'max_marks' => (float) ($term->max_marks ?: 100),
            'children' => array_merge($columns, [
                ['key' => 'grade', 'label' => 'Grade', 'type' => 'grade', 'role' => 'grade'],
            ]),
        ]];
        $row['subjects'] = array_map(function (array $subject) use ($term) {
            return [
                'subject_id' => $subject['subject_id'],
                'subject_name' => $subject['subject_name'],
                'terms' => [[
                    'term_id' => $term->id,
                    'term_name' => $term->name,
                    'cells' => $subject['cells'] ?? [],
                    'test_total' => $subject['test_total'] ?? null,
                    'board_total' => $subject['board_total'] ?? null,
                    'total' => $subject['marks_obtained'] ?? null,
                ]],
                'overall' => $subject['marks_obtained'] ?? null,
                'overall_max' => $subject['max_marks'] ?? 100,
                'grade' => $subject['grade'] ?? null,
                'marks_obtained' => $subject['marks_obtained'] ?? null,
                'max_marks' => $subject['max_marks'] ?? 100,
            ];
        }, $row['subjects'] ?? []);

        return $row;
    }

    private function anchorExam(AcademicTerm $term): Exam
    {
        $exam = $term->exams()
            ->where('counts_toward_term', true)
            ->orderByDesc('is_internal_component')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($exam) {
            return $exam;
        }

        return Exam::query()->firstOrCreate(
            ['name' => $term->name.' Report Card', 'academic_session_id' => $term->academic_session_id],
            ['status' => 'Scheduled', 'type' => 'Term Report', 'total_marks' => 100, 'passing_marks' => 33]
        );
    }
}
