<?php

namespace App\Http\Controllers\Erp\People;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\UdiseS02PdfService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UdisePlusS02Controller extends Controller
{
    public function __construct(private UdiseS02PdfService $pdf) {}

    /** Students not marked In UDISE (missing profile / is_in_udise false). */
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
            ])
            ->whereDoesntHave('udiseDetail', fn ($q) => $q->where('is_in_udise', true));

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        } else {
            $query->where('status', 'Active');
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
                    ->orWhere('aadhar_no', 'like', $term);
            });
        }

        $rows = $query->orderBy('name')->limit(2000)->get()->map(fn (Student $s) => $this->present($s));

        return response()->json($rows);
    }

    public function downloadPdf(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'integer|exists:students,id',
            'reasons' => 'nullable|array',
            'academic_year' => 'nullable|string|max:30',
            'state' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'block' => 'nullable|string|max:100',
            'udise_code' => 'nullable|string|max:50',
            'school_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'principal_name' => 'nullable|string|max:255',
            'principal_designation' => 'nullable|string|max:100',
        ]);

        $ids = array_map('intval', $data['student_ids']);
        $students = Student::with([
            'schoolClass:id,name',
            'section:id,name',
            'father:id,name',
            'mother:id,name',
            'udiseDetail',
        ])
            ->whereIn('id', $ids)
            ->whereDoesntHave('udiseDetail', fn ($q) => $q->where('is_in_udise', true))
            ->get()
            ->sortBy(fn (Student $s) => array_search($s->id, $ids, true))
            ->values();

        abort_if($students->isEmpty(), 404, 'No eligible students found (not in UDISE).');
        abort_if(
            $students->count() !== count(array_unique($ids)),
            422,
            'Some selected students are already marked In UDISE or were not found.'
        );

        $options = [
            'reasons' => $data['reasons'] ?? [],
            'academic_year' => $data['academic_year'] ?? null,
            'state' => $data['state'] ?? null,
            'district' => $data['district'] ?? null,
            'block' => $data['block'] ?? null,
            'udise_code' => $data['udise_code'] ?? null,
            'school_name' => $data['school_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'principal_name' => $data['principal_name'] ?? null,
            'principal_designation' => $data['principal_designation'] ?? null,
        ];

        return $this->pdf->streamDownload($students, 'udise-s02-form.pdf', $options);
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
            'admission_date' => $s->admission_date?->format('Y-m-d'),
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
            'guardian_name' => $udise?->guardian_name,
            'alternate_mobile' => $udise?->alternate_mobile,
            'name_as_per_aadhaar' => $udise?->name_as_per_aadhaar,
            'student_pen' => $udise?->student_pen,
            'cwsn' => $udise?->cwsn,
            'is_in_udise' => (bool) ($udise?->is_in_udise),
            'reason' => '',
        ];
    }
}
