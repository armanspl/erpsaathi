<?php

namespace App\Http\Controllers\Erp\Admissions;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\AdmissionEnquiry;
use App\Models\ParentGuardian;
use App\Models\Student;
use App\Support\AdmissionsCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdmissionEnquiryController extends Controller
{
    private const RELATIONS = ['classApplyingFor:id,name', 'branch:id,name'];

    public function index(Request $request)
    {
        $query = AdmissionEnquiry::query()
            ->with(self::RELATIONS)
            ->orderByDesc('created_at');

        // open=1 — Follow-up page (exclude terminal stages).
        if ($request->boolean('open')) {
            $query->whereNotIn('stage', ['admitted', 'rejected']);
        }

        if ($request->filled('stage')) {
            $stages = array_values(array_filter(array_map('trim', explode(',', (string) $request->stage))));
            if ($stages !== []) {
                $query->whereIn('stage', $stages);
            }
        }

        if ($request->filled('lead_status')) {
            $statuses = array_values(array_filter(array_map('trim', explode(',', (string) $request->lead_status))));
            if ($statuses !== []) {
                $query->whereIn('lead_status', $statuses);
            }
        }

        AcademicSession::applyDateWindow($query, $request, 'created_at');

        $limit = min(max($request->integer('limit', 300), 1), 1000);

        return response()->json($query->limit($limit)->get());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['enquiry_no'] = $this->nextEnquiryNo();
        $data['lead_status'] = $data['lead_status'] ?? 'New';
        $data['stage'] = $data['stage'] ?? 'enquiry';

        $enquiry = AdmissionEnquiry::create($data);
        AdmissionsCache::forget();

        return response()->json($enquiry->load(self::RELATIONS), 201);
    }

    public function update(Request $request, AdmissionEnquiry $enquiry)
    {
        $data = $this->validated($request);
        $enquiry->update($data);
        AdmissionsCache::forget();

        return response()->json($enquiry->load(self::RELATIONS));
    }

    public function destroy(AdmissionEnquiry $enquiry)
    {
        $enquiry->delete();
        AdmissionsCache::forget();

        return response()->json(['success' => true]);
    }

    /**
     * Move an enquiry to a new pipeline stage. Moving to "admitted" converts it into a
     * real Student record (People module) — the enquiry becomes a confirmed admission.
     */
    public function updateStage(Request $request, AdmissionEnquiry $enquiry)
    {
        $data = $request->validate([
            'stage' => ['required', Rule::in(['enquiry', 'follow_up', 'registered', 'admitted', 'rejected'])],
        ]);

        if ($data['stage'] === 'admitted' && ! $enquiry->admitted_student_id) {
            DB::transaction(function () use ($enquiry) {
                $guardian = ParentGuardian::firstOrCreate(
                    ['phone' => $enquiry->phone],
                    ['name' => $enquiry->parent_name]
                );

                $student = Student::create([
                    'admission_no' => $this->nextAdmissionNo(),
                    'name' => $enquiry->student_name,
                    'school_class_id' => $enquiry->class_applying_for_id,
                    'guardian_id' => $guardian->id,
                    'gender' => $enquiry->gender,
                    'dob' => $enquiry->dob,
                    'mobile' => $enquiry->phone,
                    'email' => $enquiry->email,
                    'status' => 'Active',
                    'admission_status' => 'Admitted',
                    'admission_date' => now()->toDateString(),
                ]);

                $enquiry->update([
                    'stage' => 'admitted',
                    'lead_status' => 'Converted',
                    'admitted_student_id' => $student->id,
                ]);
            });
        } else {
            $update = ['stage' => $data['stage']];
            if ($data['stage'] === 'registered') {
                $update['lead_status'] = 'Converted';
            }
            $enquiry->update($update);
        }

        AdmissionsCache::forget();

        return response()->json($enquiry->load([...self::RELATIONS, 'admittedStudent:id,admission_no,name']));
    }

    /**
     * `stage` deliberately excludes "admitted" here — reaching that stage runs the special
     * transactional Student/ParentGuardian creation in updateStage() above; allowing it through
     * this plain validated()/update() path would silently skip that and leave the enquiry
     * marked admitted with no real student record behind it.
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'student_name' => 'required|string|max:255',
            'parent_name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'whatsapp_optin' => 'boolean',
            'email' => 'nullable|email|max:255',
            'class_applying_for_id' => 'required|exists:school_classes,id',
            'present_school' => 'nullable|string|max:255',
            'address_line_1' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:20',
            'gender' => 'nullable|in:Male,Female,Other',
            'dob' => 'nullable|date',
            'source' => 'nullable|string|max:100',
            'preferred_contact_time' => 'nullable|string|max:50',
            'preferred_mode' => 'nullable|string|max:50',
            'preferred_contact_date' => 'nullable|date',
            'transport_required' => 'boolean',
            'consent_given' => 'boolean',
            'stage' => ['sometimes', Rule::in(['enquiry', 'follow_up', 'registered', 'rejected'])],
            'lead_status' => ['sometimes', Rule::in(['New', 'Contacted', 'Converted', 'Closed'])],
            'remarks' => 'nullable|string|max:1000',
            'next_follow_up_date' => 'nullable|date',
            'fee_amount' => 'nullable|numeric|min:0',
            'fee_paid' => 'boolean',
            'documents_submitted' => 'array',
            'documents_submitted.*' => 'string',
        ]);
    }

    private function nextEnquiryNo(): string
    {
        $year = now()->format('y');
        $count = AdmissionEnquiry::where('enquiry_no', 'like', "ENQ-{$year}-%")->count() + 1;

        return sprintf('ENQ-%s-%04d', $year, $count);
    }

    private function nextAdmissionNo(): string
    {
        $year = now()->format('Y');
        $count = Student::where('admission_no', 'like', "ADM-{$year}-%")->count() + 1;

        return sprintf('ADM-%s-%04d', $year, $count);
    }
}
