<?php

namespace App\Http\Controllers\Erp\Exams;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ExamScheduleSheet;
use App\Services\DocumentDataBuilder;
use App\Services\DocumentRenderService;
use App\Services\ExamScheduleImportParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamScheduleSheetController extends Controller
{
    private const RELATIONS = ['exam:id,name', 'branch:id,name'];
    private const FULL_RELATIONS = ['exam:id,name', 'branch:id,name', 'sittings', 'dates.cells.schoolClass:id,name', 'dates.cells.section:id,name', 'dates.cells.subject:id,name'];

    public function __construct(
        private DocumentDataBuilder $dataBuilder,
        private DocumentRenderService $renderer,
    ) {}

    public function index(Request $request)
    {
        $query = ExamScheduleSheet::with(self::RELATIONS)->withCount(['sittings', 'dates']);

        if ($request->filled('exam_id')) {
            $query->where('exam_id', $request->integer('exam_id'));
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        $currentSession = AcademicSession::fromRequest(request(), true)?->name;

        return response()->json(
            $query->orderByDesc('updated_at')->get()->map(fn (ExamScheduleSheet $s) => [
                ...$s->toArray(),
                'session_name' => $currentSession,
            ])
        );
    }

    public function show(ExamScheduleSheet $examScheduleSheet)
    {
        return response()->json($this->present($examScheduleSheet));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'exam_id' => [
                'required', 'exists:exams,id',
                Rule::unique('exam_schedule_sheets', 'exam_id')->where(fn ($q) => $q->where('branch_id', $request->input('branch_id'))),
            ],
            'branch_id' => 'required|exists:branches,id',
        ], [
            'exam_id.unique' => 'A schedule already exists for this exam and branch.',
        ]);

        $sheet = ExamScheduleSheet::create($data);

        return response()->json($this->present($sheet), 201);
    }

    public function update(Request $request, ExamScheduleSheet $examScheduleSheet)
    {
        $data = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'branch_id' => 'required|exists:branches,id',
            'sittings' => 'array',
            'sittings.*.label' => 'required|string|max:100',
            'sittings.*.start_time' => 'required|date_format:H:i',
            'sittings.*.end_time' => 'required|date_format:H:i|after:sittings.*.start_time',
            'dates' => 'array',
            'dates.*.date' => 'required|date',
            'dates.*.is_holiday' => 'boolean',
            'dates.*.cells' => 'array',
            'dates.*.cells.*.school_class_id' => 'required|exists:school_classes,id',
            'dates.*.cells.*.section_id' => 'nullable|exists:sections,id',
            'dates.*.cells.*.subject_id' => 'nullable|exists:subjects,id',
            'dates.*.cells.*.start_time' => 'nullable|date_format:H:i',
            'dates.*.cells.*.end_time' => 'nullable|date_format:H:i',
        ]);

        DB::transaction(function () use ($data, $examScheduleSheet) {
            $examScheduleSheet->update(['exam_id' => $data['exam_id'], 'branch_id' => $data['branch_id']]);

            $examScheduleSheet->sittings()->delete();
            foreach ($data['sittings'] ?? [] as $i => $row) {
                $examScheduleSheet->sittings()->create([
                    'label' => $row['label'],
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time'],
                    'sort_order' => $i,
                ]);
            }

            $examScheduleSheet->dates()->delete();
            foreach ($data['dates'] ?? [] as $row) {
                $date = $examScheduleSheet->dates()->create([
                    'date' => $row['date'],
                    'is_holiday' => $row['is_holiday'] ?? false,
                ]);
                if (empty($row['is_holiday'])) {
                    foreach ($row['cells'] ?? [] as $cell) {
                        if (empty($cell['subject_id'])) {
                            continue;
                        }
                        $date->cells()->create([
                            'school_class_id' => $cell['school_class_id'],
                            'section_id' => $cell['section_id'] ?? null,
                            'subject_id' => $cell['subject_id'],
                            'start_time' => $cell['start_time'] ?? null,
                            'end_time' => $cell['end_time'] ?? null,
                        ]);
                    }
                }
            }
        });

        return response()->json($this->present($examScheduleSheet->fresh()));
    }

    /**
     * Parses an uploaded exam-routine workbook into the same {date, is_holiday, cells[]} shape
     * the grid UI already works with and returns it for the frontend to pre-fill — nothing is
     * written to the database here. Saving afterward goes through the normal store()/update()
     * flow above, unchanged, once the school has reviewed the warnings (unmatched subjects,
     * possible holidays, etc.) and fixed anything in the grid.
     */
    public function importPreview(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:20480']);

        @set_time_limit(120);

        try {
            $result = app(ExamScheduleImportParser::class)->parse($request->file('file')->getRealPath());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function destroy(ExamScheduleSheet $examScheduleSheet)
    {
        $examScheduleSheet->delete();

        return response()->json(['success' => true]);
    }

    public function downloadPdf(ExamScheduleSheet $examScheduleSheet): StreamedResponse
    {
        $filename = 'exam-schedule-'.Str::slug($examScheduleSheet->exam->name ?? 'sheet').'-'.$examScheduleSheet->id.'.pdf';

        return $this->renderer->streamPdf(
            'exam_schedule',
            $this->dataBuilder->examSchedule($examScheduleSheet),
            $filename
        );
    }

    private function present(ExamScheduleSheet $sheet): array
    {
        $sheet->loadMissing(self::FULL_RELATIONS);
        $currentSession = AcademicSession::fromRequest(request(), true)?->name;

        return [
            ...$sheet->toArray(),
            'dates' => $sheet->dates->map(fn ($d) => [...$d->toArray(), 'date' => $d->date->format('Y-m-d')])->values(),
            'session_name' => $currentSession,
        ];
    }
}
