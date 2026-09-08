<?php

namespace App\Http\Controllers\Erp\People;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\UdiseS03PdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class UdisePlusController extends Controller
{
    public function __construct(private UdiseS03PdfService $pdf) {}

    /** Students (any status, unless filtered) with fields needed for the UDISE+ S03 form list. */
    public function index(Request $request)
    {
        $query = Student::query()
            ->with([
                'schoolClass:id,name',
                'section:id,name',
                'father:id,name',
                'mother:id,name',
                'udiseDetail',
                'branch:id,name',
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('branch_id')) {
            $query->forBranch($request->integer('branch_id'));
        }
        if ($request->filled('school_class_id')) {
            $query->where('school_class_id', $request->integer('school_class_id'));
        }
        if ($request->filled('section_id')) {
            $query->where('section_id', $request->integer('section_id'));
        }
        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('admission_no', 'like', $term)
                    ->orWhere('mobile', 'like', $term)
                    ->orWhere('aadhar_no', 'like', $term)
                    ->orWhereHas('udiseDetail', fn ($u) => $u->where('student_pen', 'like', $term));
            });
        }

        $rows = $query->orderBy('name')->limit(2000)->get()->map(fn (Student $s) => $this->present($s));

        return response()->json($rows);
    }

    public function show(Student $student)
    {
        $student->loadMissing([
            'schoolClass:id,name',
            'section:id,name',
            'father:id,name',
            'mother:id,name',
            'udiseDetail',
            'branch:id,name',
        ]);

        return response()->json($this->present($student));
    }

    public function downloadS03(Request $request, Student $student): StreamedResponse
    {
        $overrides = $this->overridesFromRequest($request);
        $filename = 'udise-s03-'.Str::slug($student->admission_no ?: (string) $student->id).'.pdf';

        return $this->pdf->streamDownload($student, $filename, $overrides);
    }

    public function downloadZip(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'integer|exists:students,id',
        ]);

        $students = Student::with([
            'schoolClass:id,name',
            'section:id,name',
            'father:id,name',
            'mother:id,name',
            'udiseDetail',
        ])->whereIn('id', $data['student_ids'])->get();

        abort_if($students->isEmpty(), 404, 'No students found.');

        $zipPath = tempnam(sys_get_temp_dir(), 'udise-s03-');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        foreach ($students as $student) {
            $binary = $this->pdf->binary($student);
            $zip->addFromString(
                'udise-s03-'.Str::slug($student->admission_no ?: (string) $student->id).'.pdf',
                $binary
            );
        }
        $zip->close();

        return response()->streamDownload(function () use ($zipPath) {
            readfile($zipPath);
            @unlink($zipPath);
        }, 'udise-s03-forms.zip', ['Content-Type' => 'application/zip']);
    }

    private function present(Student $s): array
    {
        $udise = $s->udiseDetail;

        return [
            'id' => $s->id,
            'admission_no' => $s->admission_no,
            'roll_no' => $s->roll_no,
            'name' => $s->name,
            'mobile' => $s->mobile,
            'gender' => $s->gender,
            'dob' => $s->dob?->format('Y-m-d'),
            'aadhar_no' => $s->aadhar_no,
            'status' => $s->status,
            'branch_id' => $s->branch_id,
            'branch_name' => $s->branch?->name,
            'school_class_id' => $s->school_class_id,
            'school_class_name' => $s->schoolClass?->name,
            'section_id' => $s->section_id,
            'section_name' => $s->section?->name,
            'father_name' => $udise?->father_name ?: $s->father?->name,
            'mother_name' => $udise?->mother_name ?: $s->mother?->name,
            'student_pen' => $udise?->student_pen,
            'name_as_per_aadhaar' => $udise?->name_as_per_aadhaar,
        ];
    }

    /** @return array<string, mixed> */
    private function overridesFromRequest(Request $request): array
    {
        return $request->validate([
            'student_pen' => 'nullable|string|max:50',
            'for_name' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:30',
            'existing_name' => 'nullable|string|max:255',
            'existing_dob' => 'nullable|string|max:30',
            'existing_gender' => 'nullable|string|max:30',
            'existing_aadhaar' => 'nullable|string|max:20',
            'existing_name_as_per_aadhaar' => 'nullable|string|max:255',
            'existing_class_section' => 'nullable|string|max:100',
            'existing_mother_name' => 'nullable|string|max:255',
            'existing_father_name' => 'nullable|string|max:255',
            'updated_name' => 'nullable|string|max:255',
            'updated_dob' => 'nullable|string|max:30',
            'updated_gender' => 'nullable|string|max:30',
            'updated_aadhaar' => 'nullable|string|max:20',
            'updated_name_as_per_aadhaar' => 'nullable|string|max:255',
            'updated_class_section' => 'nullable|string|max:100',
            'updated_mother_name' => 'nullable|string|max:255',
            'updated_father_name' => 'nullable|string|max:255',
            // Sent as a query-string param from a JS boolean, which serializes to the literal
            // string "true"/"false" — Laravel's `boolean` rule only accepts 1/0/"1"/"0", not
            // that, so it must stay untyped here; filter_var(FILTER_VALIDATE_BOOLEAN) downstream
            // already normalizes any of true/false/"true"/"false"/1/0/"1"/"0".
            'attach_school_register' => 'nullable',
            'attach_birth_certificate' => 'nullable',
            'undertaking_school' => 'nullable',
            'undertaking_officer' => 'nullable',
            'district' => 'nullable|string|max:100',
            'block' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'udise_code' => 'nullable|string|max:50',
            'school_name' => 'nullable|string|max:255',
            'principal_name' => 'nullable|string|max:255',
            'principal_designation' => 'nullable|string|max:100',
        ]);
    }
}
