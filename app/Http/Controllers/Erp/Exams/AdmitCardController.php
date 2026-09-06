<?php

namespace App\Http\Controllers\Erp\Exams;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\ExamAdmitInstruction;
use App\Models\Student;
use App\Services\DocumentDataBuilder;
use App\Services\DocumentRenderService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class AdmitCardController extends Controller
{
    public function __construct(
        private DocumentRenderService $renderer,
        private DocumentDataBuilder $dataBuilder,
    ) {}

    public function index(Request $request, Exam $exam)
    {
        $students = $this->scopedStudents(
            $request->integer('branch_id') ?: null,
            $request->integer('school_class_id') ?: null,
            $request->integer('section_id') ?: null,
        );

        return response()->json($students->map(fn (Student $s) => $this->presentRow($s))->values());
    }

    public function instructions(Exam $exam)
    {
        $instructions = ExamAdmitInstruction::where('exam_id', $exam->id)->value('instructions');

        return response()->json(['exam_id' => $exam->id, 'instructions' => $instructions ?? '']);
    }

    public function saveInstructions(Request $request, Exam $exam)
    {
        $data = $request->validate(['instructions' => 'nullable|string']);

        $record = ExamAdmitInstruction::updateOrCreate(['exam_id' => $exam->id], ['instructions' => $data['instructions'] ?? '']);

        return response()->json(['exam_id' => $exam->id, 'instructions' => $record->instructions]);
    }

    public function downloadStudentPdf(Request $request, Exam $exam, Student $student): StreamedResponse
    {
        $overrides = $request->only(['name', 'roll_no', 'admission_no', 'father_name', 'mother_name']);
        $data = $this->dataBuilder->admitCard($exam, $student, $overrides);

        // Individual row download is always one full A4 admit card.
        return $this->renderer->streamPdf('admit_card', $data, "admit-card-{$student->admission_no}.pdf");
    }

    public function downloadZip(Request $request, Exam $exam): StreamedResponse
    {
        $students = $this->studentsForRequest($request);
        abort_if($students->isEmpty(), 404, 'No students found for the selected scope.');

        $dataList = [];
        foreach ($students as $student) {
            $dataList[] = $this->dataBuilder->admitCard($exam, $student);
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'admit-cards-zip-');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::OVERWRITE);

        foreach ($students as $index => $student) {
            $binary = $this->renderer->pdfBinary('admit_card', $dataList[$index]);
            $zip->addFromString("admit-card-{$student->admission_no}.pdf", $binary);
        }

        $zip->close();

        return response()->streamDownload(function () use ($zipPath) {
            readfile($zipPath);
            unlink($zipPath);
        }, 'admit-cards-'.$exam->id.'.zip', ['Content-Type' => 'application/zip']);
    }

    /** Combined multi-page PDF of selected (or scoped) admit cards for browser print. */
    public function downloadPrintPdf(Request $request, Exam $exam): StreamedResponse
    {
        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'integer',
        ]);

        $students = $this->studentsForRequest($request);
        abort_if($students->isEmpty(), 404, 'No students found for the selected scope.');

        $dataList = [];
        foreach ($students as $student) {
            $dataList[] = $this->dataBuilder->admitCard($exam, $student);
        }

        return $this->renderer->streamPdfStacked(
            'admit_card',
            $dataList,
            'admit-cards-print-'.$exam->id.'.pdf',
        );
    }

    private function studentsForRequest(Request $request): Collection
    {
        if ($request->filled('student_ids')) {
            $ids = collect($request->input('student_ids'))->map(fn ($id) => (int) $id)->filter()->values();

            return Student::whereIn('id', $ids)->where('status', 'Active')
                ->orderBy('roll_no')->orderBy('name')->get();
        }

        return $this->scopedStudents(
            $request->integer('branch_id') ?: null,
            $request->integer('school_class_id') ?: null,
            $request->integer('section_id') ?: null,
        );
    }

    private function scopedStudents(?int $branchId, ?int $schoolClassId, ?int $sectionId): Collection
    {
        $query = Student::query()
            ->where('status', 'Active')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($schoolClassId, fn ($q) => $q->where('school_class_id', $schoolClassId))
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
            ->with(['schoolClass:id,name', 'section:id,name', 'father:id,name', 'mother:id,name']);

        AcademicSession::applyStudentSessionFilter($query);

        return $query->orderBy('roll_no')->orderBy('name')->get();
    }

    private function presentRow(Student $student): array
    {
        return [
            'student_id' => $student->id,
            'roll_no' => $student->roll_no,
            'admission_no' => $student->admission_no,
            'name' => $student->name,
            'father_name' => $student->father->name ?? null,
            'mother_name' => $student->mother->name ?? null,
            'school_class_name' => $student->schoolClass->name ?? null,
            'section_name' => $student->section->name ?? null,
        ];
    }
}
