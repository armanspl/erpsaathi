<?php

namespace App\Http\Controllers\Erp\Exams;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Exam;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function index(Request $request)
    {
        $query = Exam::withCount('schedules')->with(['academicSession:id,name', 'academicTerm:id,name']);
        AcademicSession::applySessionIdFilter($query, $request);

        return response()->json($query->orderByDesc('id')->get());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['academic_session_id'] = AcademicSession::fromRequest($request, true)?->id;

        $exam = Exam::create($data);

        return response()->json($exam->fresh()->loadCount('schedules')->load(['academicSession:id,name', 'academicTerm:id,name']), 201);
    }

    public function update(Request $request, Exam $exam)
    {
        $data = $this->validated($request);

        $exam->update($data);

        return response()->json($exam->fresh()->loadCount('schedules')->load(['academicSession:id,name', 'academicTerm:id,name']));
    }

    public function destroy(Exam $exam)
    {
        $exam->delete();

        return response()->json(['success' => true]);
    }

    public function publish(Exam $exam)
    {
        $exam->update(['published_at' => now(), 'status' => 'Completed']);

        return response()->json($exam);
    }

    /**
     * Sets the accent colour used on this exam's Exam Schedule, Report Card, and Admit Card
     * PDFs. `pdf_accent_color` is either a hex colour, the literal string "none" (renders in
     * greyscale — see DocumentDataBuilder::accentPalette()), or null (reset to the default blue).
     */
    public function updatePdfColor(Request $request, Exam $exam)
    {
        $data = $request->validate([
            'pdf_accent_color' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== 'none' && ! preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                    $fail('The PDF colour must be a hex colour or "none".');
                }
            }],
        ]);

        $exam->update(['pdf_accent_color' => $data['pdf_accent_color'] ?? null]);

        return response()->json(['id' => $exam->id, 'pdf_accent_color' => $exam->pdf_accent_color]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:100',
            'total_marks' => 'required|numeric|min:0',
            'passing_marks' => 'required|numeric|min:0|lte:total_marks',
            'min_marks' => 'nullable|numeric|min:0',
            'max_marks' => 'nullable|numeric|gte:min_marks',
            'description' => 'nullable|string|max:2000',
            'academic_term_id' => 'nullable|exists:academic_terms,id',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'counts_toward_term' => 'nullable|boolean',
            'is_internal_component' => 'nullable|boolean',
        ]);

        if (array_key_exists('academic_term_id', $data) && ($data['academic_term_id'] === '' || $data['academic_term_id'] === null)) {
            $data['academic_term_id'] = null;
        }
        if (array_key_exists('counts_toward_term', $data)) {
            $data['counts_toward_term'] = (bool) $data['counts_toward_term'];
        }
        if (array_key_exists('is_internal_component', $data)) {
            $data['is_internal_component'] = (bool) $data['is_internal_component'];
        }

        return $data;
    }
}
