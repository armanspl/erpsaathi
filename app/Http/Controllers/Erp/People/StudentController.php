<?php

namespace App\Http\Controllers\Erp\People;

use App\Http\Controllers\Controller;
use App\Support\AdmissionsCache;
use App\Support\PeopleCache;
use App\Models\AcademicSession;
use App\Models\BookExpense;
use App\Models\BookIssue;
use App\Models\Certificate;
use App\Models\FeePayment;
use App\Models\IdCard;
use App\Models\LibraryMember;
use App\Models\ParentGuardian;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAdditionalDetail;
use App\Models\StudentDocument;
use App\Models\StudentSessionHistory;
use App\Models\StudentUdiseDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    /**
     * Upload field key (request field is "{key}_file") → stored column + validation rule.
     * Files are stored on the private `local` disk (storage/app/private/student-documents/{id}/)
     * — never the public disk — and only ever served back through downloadDocument(), which
     * requires the same auth as every other student-data read in this app.
     */
    private const DOCUMENT_TYPES = [
        'photo' => ['column' => 'photo_path', 'label' => 'Photo', 'rules' => 'nullable|file|mimes:jpg,jpeg,png|max:2048'],
        'aadhaar' => ['column' => 'aadhaar_path', 'label' => 'Aadhaar', 'rules' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120'],
        'pan' => ['column' => 'pan_path', 'label' => 'PAN', 'rules' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120'],
        'birth_certificate' => ['column' => 'birth_certificate_path', 'label' => 'Birth Certificate', 'rules' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120'],
        'transfer_certificate' => ['column' => 'transfer_certificate_path', 'label' => 'Transfer Certificate', 'rules' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120'],
        'marksheet' => ['column' => 'marksheet_path', 'label' => 'Marksheet', 'rules' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120'],
        'father_aadhaar' => ['column' => 'father_aadhaar_path', 'label' => "Father's Aadhaar", 'rules' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120'],
        'father_pan' => ['column' => 'father_pan_path', 'label' => "Father's PAN", 'rules' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120'],
        'mother_aadhaar' => ['column' => 'mother_aadhaar_path', 'label' => "Mother's Aadhaar", 'rules' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120'],
        'mother_pan' => ['column' => 'mother_pan_path', 'label' => "Mother's PAN", 'rules' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120'],
        'father_photo' => ['column' => 'father_photo_path', 'label' => "Father's Photo", 'rules' => 'nullable|file|mimes:jpg,jpeg,png|max:2048'],
        'mother_photo' => ['column' => 'mother_photo_path', 'label' => "Mother's Photo", 'rules' => 'nullable|file|mimes:jpg,jpeg,png|max:2048'],
    ];

    public function index(Request $request)
    {
        // lite=1 — list tables (People / Admissions); skip heavy parent/UDISE/document graphs.
        // for=fee — Fee Receipt / Pay Fee picker: Active students only, minimal columns (no session history).
        $lite = $request->boolean('lite');
        $forFee = $request->string('for')->toString() === 'fee';

        if ($forFee) {
            $query = Student::query()
                ->select(['id', 'name', 'admission_no', 'branch_id', 'school_class_id', 'section_id', 'status'])
                ->with([
                    'schoolClass:id,name',
                    'section:id,name,school_class_id',
                    'branch:id,name',
                ])
                ->where('status', 'Active');

            if (! AcademicSession::requestWantsAll($request)) {
                $session = AcademicSession::fromRequest($request);
                if ($session) {
                    AcademicSession::applyStudentSessionFilter($query, $request);
                }
            }

            if ($request->filled('search')) {
                $term = '%'.trim((string) $request->input('search')).'%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)->orWhere('admission_no', 'like', $term);
                });
            }
            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->integer('branch_id'));
            }
            if ($request->filled('school_class_id')) {
                $query->where('school_class_id', $request->integer('school_class_id'));
            }
            if ($request->filled('section_id')) {
                $query->where('section_id', $request->integer('section_id'));
            }

            $limit = $request->integer('limit');
            if ($limit > 0) {
                $query->limit(min($limit, 2000));
            } else {
                $query->limit(2000);
            }

            return response()->json($query->orderBy('name')->orderBy('admission_no')->get());
        }

        $query = Student::query()->with(
            $lite
                ? [
                    'schoolClass:id,name',
                    'section:id,name,school_class_id',
                    'branch:id,name',
                    'father:id,name,phone',
                    // Just admission_type — the People list's "New" stat card needs this;
                    // the rest of udiseDetail's ~20 fields stay in the heavy (non-lite) load.
                    'udiseDetail:student_id,admission_type',
                ]
                : [
                    'schoolClass:id,name',
                    'section:id,name,school_class_id',
                    'branch:id,name',
                    'father:id,name,phone,email,occupation,qualification,annual_income,aadhaar_no,pan_no',
                    'mother:id,name,phone,email,occupation,qualification,annual_income,aadhaar_no,pan_no',
                    'guardian:id,name,phone,email,occupation,relationship',
                    'additionalDetail',
                    'udiseDetail',
                    'documents',
                ]
        );

        if ($request->filled('admission_status')) {
            $statuses = array_values(array_filter(array_map('trim', explode(',', (string) $request->admission_status))));
            if ($statuses !== []) {
                $query->whereIn('admission_status', $statuses);
            }
        }

        if ($request->filled('status')) {
            $statuses = array_values(array_filter(array_map('trim', explode(',', (string) $request->status))));
            if ($statuses !== []) {
                $query->whereIn('status', $statuses);
            }
        }

        if (! AcademicSession::requestWantsAll($request)) {
            $session = AcademicSession::fromRequest($request);
            if ($session) {
                $aliases = AcademicSession::nameAliases($session->name);

                $query->where(function ($q) use ($aliases, $session) {
                    $q->whereHas('sessionHistories', fn ($h) => $h->whereIn('session', $aliases));
                    // Manually-added students (no history yet) still belong to the current session.
                    if ($session->is_current) {
                        $q->orWhereDoesntHave('sessionHistories');
                    }
                });
                // Session snapshot is needed for People table class/section columns even in lite mode.
                $query->with([
                    'sessionHistories' => fn ($h) => $h->whereIn('session', $aliases)
                        ->select(['id', 'student_id', 'session', 'class_name', 'section_name', 'roll_no', 'status']),
                ]);
            } elseif (! $lite) {
                $query->with('latestSessionHistory');
            }
        } elseif (! $lite) {
            $query->with('latestSessionHistory');
        }

        $limit = $request->integer('limit');
        if ($limit > 0) {
            $query->limit(min($limit, 2000));
        }

        return response()->json($query->orderByDesc('admission_date')->orderByDesc('id')->get());
    }

    public function show(Student $student)
    {
        return response()->json($this->loadRelations($student));
    }

    public function store(Request $request)
    {
        $this->decodeCustomFieldValues($request);
        $data = $this->validated($request);
        $detailData = $this->validatedDetail($request);
        $udiseData = $this->validatedUdise($request);
        $this->validatedDocuments($request);
        $this->validatedGuardians($request);

        // Default Active students from People stay Admitted; Registration/Admission forms set explicitly.
        $data['admission_status'] = $data['admission_status'] ?? 'Admitted';

        // A student created here (not via Student Master Import) is, by definition, a fresh
        // admission into this system — default to "New" unless the form explicitly said "Old"
        // (e.g. staff manually re-entering an already-enrolled student).
        $udiseData['admission_type'] = $udiseData['admission_type'] ?? 'New';

        $student = DB::transaction(function () use ($data, $detailData, $udiseData, $request) {
            $this->applyResolvedGuardians($data, $request);

            $student = Student::create($data);
            $this->saveDetail($student, $detailData);
            $this->saveUdiseDetail($student, $udiseData);
            $this->saveDocuments($student, $request);
            $this->saveSessionHistory($student, $request);

            return $student;
        });

        AdmissionsCache::forget();
        PeopleCache::forget();

        return response()->json($this->loadRelations($student), 201);
    }

    public function update(Request $request, Student $student)
    {
        $this->decodeCustomFieldValues($request);
        $data = $this->validated($request, $student);
        $detailData = $this->validatedDetail($request);
        $udiseData = $this->validatedUdise($request);
        $this->validatedDocuments($request);
        $this->validatedGuardians($request);

        DB::transaction(function () use ($student, $data, $detailData, $udiseData, $request) {
            $this->applyResolvedGuardians($data, $request);

            $student->update($data);
            $this->saveDetail($student, $detailData);
            $this->saveUdiseDetail($student, $udiseData);
            $this->saveDocuments($student, $request);
            $this->saveSessionHistory($student, $request);
        });

        AdmissionsCache::forget();
        PeopleCache::forget();

        return response()->json($this->loadRelations($student));
    }

    public function destroy(Student $student)
    {
        DB::transaction(function () use ($student) {
            $student->loadMissing('documents');

            // Tables that historically used restrictOnDelete — remove before the student row.
            Certificate::where('student_id', $student->id)->delete();
            FeePayment::where('student_id', $student->id)->delete();
            BookExpense::where('student_id', $student->id)->delete(); // items cascade at DB

            // Morph / non-FK links that would otherwise leave orphans.
            $student->attendances()->delete();
            $student->leaveRequests()->delete();
            IdCard::where('holder_type', 'student')->where('holder_id', $student->id)->delete();

            $libraryMemberIds = LibraryMember::query()
                ->where('member_type', 'student')
                ->where('member_id', $student->id)
                ->pluck('id');
            if ($libraryMemberIds->isNotEmpty()) {
                BookIssue::whereIn('library_member_id', $libraryMemberIds)->delete();
                LibraryMember::whereIn('id', $libraryMemberIds)->delete();
            }

            // Remove uploaded document files from disk (DB row cascades with student).
            if ($student->documents) {
                foreach (self::DOCUMENT_TYPES as $meta) {
                    $path = $student->documents->{$meta['column']} ?? null;
                    if ($path) {
                        Storage::disk('local')->delete($path);
                    }
                }
            }

            $student->delete();
        });

        AdmissionsCache::forget();
        PeopleCache::forget();

        return response()->json(['success' => true]);
    }

    /** Promote Registered → Admitted (and similar) without a full student form rewrite. */
    public function updateAdmissionStatus(Request $request, Student $student)
    {
        $data = $request->validate([
            'admission_status' => 'required|in:New,Registered,Admitted',
        ]);
        $student->update($data);
        AdmissionsCache::forget();

        return response()->json($this->loadRelations($student));
    }

    /**
     * Streams a private document back to the browser. `$type` must be one of the known
     * DOCUMENT_TYPES keys — never trust the URL segment as a raw path/column name.
     */
    public function downloadDocument(Student $student, string $type)
    {
        abort_unless(array_key_exists($type, self::DOCUMENT_TYPES), 404);

        $column = self::DOCUMENT_TYPES[$type]['column'];
        $path = $student->documents?->{$column};
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $friendlyName = trim("{$student->admission_no} {$student->name} " . self::DOCUMENT_TYPES[$type]['label']);
        $friendlyName = $extension ? "{$friendlyName}.{$extension}" : $friendlyName;

        return Storage::disk('local')->download($path, $friendlyName);
    }

    /** Removes one already-uploaded document immediately — independent of the Save button, same as the Download action. */
    public function deleteDocument(Student $student, string $type)
    {
        abort_unless(array_key_exists($type, self::DOCUMENT_TYPES), 404);

        $column = self::DOCUMENT_TYPES[$type]['column'];
        $document = $student->documents;
        $path = $document?->{$column};

        if ($path) {
            Storage::disk('local')->delete($path);
            $document->update([$column => null]);
        }

        return response()->json(['success' => true]);
    }

    private function loadRelations(Student $student): Student
    {
        return $student->load([
            'schoolClass:id,name', 'section:id,name,school_class_id', 'branch:id,name',
            'father:id,name,phone,email,occupation,qualification,annual_income,aadhaar_no,pan_no',
            'mother:id,name,phone,email,occupation,qualification,annual_income,aadhaar_no,pan_no',
            'guardian:id,name,phone,email,occupation,relationship',
            'additionalDetail', 'udiseDetail', 'latestSessionHistory', 'documents',
        ]);
    }

    private function decodeCustomFieldValues(Request $request): void
    {
        $raw = $request->input('custom_field_values');
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $request->merge(['custom_field_values' => is_array($decoded) ? $decoded : []]);
        }
    }

    private function validated(Request $request, ?Student $student = null): array
    {
        $data = $request->validate([
            'admission_no' => ['required', 'string', 'max:50', Rule::unique('students', 'admission_no')->ignore($student?->id)],
            'roll_no' => 'nullable|integer|min:0',
            // Registration sends first/middle/last separately (see name composition below);
            // the simpler People > Students form sends `name` directly — either is accepted.
            'name' => 'required_without_all:first_name,last_name|nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'branch_id' => 'nullable|exists:branches,id',
            'father_id' => 'nullable|exists:parents,id',
            'mother_id' => 'nullable|exists:parents,id',
            'guardian_id' => 'nullable|exists:parents,id',
            'gender' => 'nullable|in:Male,Female,Other',
            'dob' => 'nullable|date',
            'blood_group' => 'nullable|string|max:10',
            'category' => 'nullable|string|max:100',
            'religion' => 'nullable|string|max:100',
            'nationality' => 'nullable|string|max:100',
            'aadhar_no' => 'nullable|string|max:20',
            'pan_no' => 'nullable|string|max:20',
            're_admission' => 'boolean',
            'mobile' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:20',
            'permanent_same_as_current' => 'boolean',
            'permanent_address_line_1' => 'nullable|string|max:255',
            'permanent_address_line_2' => 'nullable|string|max:255',
            'permanent_city' => 'nullable|string|max:100',
            'permanent_state' => 'nullable|string|max:100',
            'permanent_pincode' => 'nullable|string|max:20',
            'status' => 'required|in:Active,Inactive,Transferred',
            'admission_status' => 'nullable|in:New,Registered,Admitted',
            'custom_field_values' => 'nullable|array',
            'admission_date' => 'nullable|date',
            'fee_start_month' => 'nullable|date_format:Y-m',
        ]);

        // First/Middle/Last are the source of truth for `name` whenever Registration's form
        // sends them — stored as their own columns (not just concatenated) so Edit can
        // re-populate the 3 separate inputs later instead of re-splitting one string.
        if ($request->filled('first_name') || $request->filled('last_name')) {
            $data['name'] = trim(collect([
                $data['first_name'] ?? null,
                $data['middle_name'] ?? null,
                $data['last_name'] ?? null,
            ])->filter()->implode(' '));
        }

        $this->validateSectionBelongsToClass($request);

        return $data;
    }

    /** Validates the student_additional_details payload. All fields optional — a student may have none of this data. */
    private function validatedDetail(Request $request): array
    {
        return $request->validate([
            'house' => 'nullable|string|max:255',
            'caste' => 'nullable|string|max:255',
            'family' => 'nullable|string|max:255',
            'medical_conditions' => 'nullable|string|max:2000',
            'allergies' => 'nullable|string|max:2000',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:30',
            'height' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'vision_left' => 'nullable|string|max:255',
            'vision_right' => 'nullable|string|max:255',
            'dental_hygiene' => 'nullable|string|max:255',
            'discontinue_date' => 'nullable|date',
            'scholarship_no' => 'nullable|string|max:255',
            'report_card_received' => 'boolean',
            'cc_received' => 'boolean',
            'tc_received' => 'boolean',
            'dob_certificate_received' => 'boolean',
            'form_no' => 'nullable|string|max:255',
            'remarks_1' => 'nullable|string|max:255',
            'remarks_2' => 'nullable|string|max:255',
            'last_school_name' => 'nullable|string|max:255',
            'previous_class' => 'nullable|string|max:100',
            'last_exam' => 'nullable|string|max:255',
            'last_exam_year' => 'nullable|string|max:255',
            'last_exam_status' => 'nullable|string|max:255',
            'last_exam_marks' => 'nullable|string|max:255',
            'last_exam_board' => 'nullable|string|max:255',
            'parents_anniversary_date' => 'nullable|date',
            'student_ref_id' => 'nullable|string|max:255',
            'biometric_card_no' => 'nullable|string|max:255',
            'child_uid' => 'nullable|string|max:255',
            'gr_no' => 'nullable|string|max:255',
            'pen_no' => 'nullable|string|max:255',
            'opening_balance' => 'nullable|numeric|min:0',
            'fees_balance' => 'nullable|numeric|min:0',
            'additional_field_1' => 'nullable|string|max:255',
            'additional_field_2' => 'nullable|string|max:255',
            'additional_field_3' => 'nullable|string|max:255',
            'additional_field_4' => 'nullable|string|max:255',
            'additional_field_5' => 'nullable|string|max:255',
            'additional_field_6' => 'nullable|string|max:255',
            'additional_field_7' => 'nullable|string|max:255',
            'additional_field_8' => 'nullable|string|max:255',
            'additional_field_9' => 'nullable|string|max:255',
            'additional_field_10' => 'nullable|string|max:255',
        ]);
    }

    /** Validates the 10 optional document uploads. Field name is "{type}_file", e.g. "photo_file". */
    private function validatedDocuments(Request $request): void
    {
        $rules = [];
        foreach (self::DOCUMENT_TYPES as $type => $meta) {
            $rules["{$type}_file"] = $meta['rules'];
        }

        $request->validate($rules);
    }

    /**
     * Stores whichever of the 10 document uploads were actually included in this request —
     * a field simply absent from the request leaves that document untouched (same
     * non-destructive-merge philosophy as saveDetail()). Replacing an existing document
     * deletes the old file from disk first, so re-uploads never leave orphaned files behind.
     */
    private function saveDocuments(Student $student, Request $request): void
    {
        $existing = $student->documents;
        $data = [];

        foreach (self::DOCUMENT_TYPES as $type => $meta) {
            $fieldKey = "{$type}_file";
            if (! $request->hasFile($fieldKey)) {
                continue;
            }

            $column = $meta['column'];
            if ($existing && $existing->{$column}) {
                Storage::disk('local')->delete($existing->{$column});
            }

            $data[$column] = $request->file($fieldKey)->store("student-documents/{$student->id}", 'local');
        }

        if (empty($data)) {
            return;
        }

        StudentDocument::firstOrNew(['student_id' => $student->id])->fill($data)->save();
    }

    /**
     * Only these 5 UDISE fields are writable through this controller (Registration's form) —
     * Route/Hostel Room No./Bed No. stay raw text only, never auto-linked to real Transport/
     * Hostel records (same precedent as Stoppage/Vehicle from Student Master Import).
     */
    private function validatedUdise(Request $request): array
    {
        return $request->validate([
            'mother_tongue' => 'nullable|string|max:100',
            'uses_transport' => 'boolean',
            'route' => 'nullable|string|max:255',
            'hostel_room_no' => 'nullable|string|max:255',
            'hostel_bed_no' => 'nullable|string|max:255',
            'student_pen' => 'nullable|string|max:50',
            'name_as_per_aadhaar' => 'nullable|string|max:255',
            'admission_type' => 'nullable|in:New,Old',
            'is_in_udise' => 'boolean',
        ]);
    }

    /**
     * Upserts only the fields validatedUdise() returns onto student_udise_details — this
     * controller never touches the ~20 other UDISE fields that only Student Master Import
     * populates, and must never delete the row just because these fields are blank (Master
     * Import may have already populated other real data on that same row).
     */
    private function saveUdiseDetail(Student $student, array $udiseData): void
    {
        // Allow clearing PEN / Aadhaar name when the form sends an empty string.
        $clearable = ['student_pen', 'name_as_per_aadhaar'];
        $data = [];
        foreach ($udiseData as $key => $value) {
            if ($value === null) {
                continue;
            }
            if ($value === '' && ! in_array($key, $clearable, true)) {
                continue;
            }
            $data[$key] = $value === '' ? null : $value;
        }
        if ($data === []) {
            return;
        }

        StudentUdiseDetail::firstOrNew(['student_id' => $student->id])->fill($data)->save();
    }

    /** Validates the optional Father/Mother/Guardian intake fields — see resolveParent(). */
    private function validatedGuardians(Request $request): void
    {
        $request->validate([
            'father_name' => 'nullable|string|max:255',
            'father_email' => 'nullable|email|max:255',
            'father_phone' => 'nullable|string|max:30',
            'father_occupation' => 'nullable|string|max:255',
            'father_qualification' => 'nullable|string|max:255',
            'father_annual_income' => 'nullable|numeric|min:0',
            'father_aadhaar_no' => 'nullable|string|max:20',
            'father_pan_no' => 'nullable|string|max:20',
            'mother_name' => 'nullable|string|max:255',
            'mother_email' => 'nullable|email|max:255',
            'mother_phone' => 'nullable|string|max:30',
            'mother_occupation' => 'nullable|string|max:255',
            'mother_qualification' => 'nullable|string|max:255',
            'mother_annual_income' => 'nullable|numeric|min:0',
            'mother_aadhaar_no' => 'nullable|string|max:20',
            'mother_pan_no' => 'nullable|string|max:20',
            'guardian_name' => 'nullable|string|max:255',
            'guardian_email' => 'nullable|email|max:255',
            'guardian_phone' => 'nullable|string|max:30',
            'guardian_occupation' => 'nullable|string|max:255',
            'guardian_relationship' => 'nullable|string|max:100',
        ]);
    }

    /**
     * Resolves Father/Mother/Guardian by phone (a real dedup key, unlike Student Master Import
     * which had no phone data and accepted a name-only merge risk) and sets the corresponding
     * *_id onto $data. Only runs when this row actually provides name/phone fields for that
     * role — the simpler People > Students form instead sends father_id/mother_id/guardian_id
     * directly (an existing parent picked from a dropdown), which is left untouched here.
     */
    private function applyResolvedGuardians(array &$data, Request $request): void
    {
        foreach (['father', 'mother', 'guardian'] as $prefix) {
            $parent = $this->resolveParent($request, $prefix);
            if ($parent) {
                $data["{$prefix}_id"] = $parent->id;
            }
        }
    }

    private function resolveParent(Request $request, string $prefix): ?ParentGuardian
    {
        $name = trim((string) $request->input("{$prefix}_name", ''));
        $phone = trim((string) $request->input("{$prefix}_phone", ''));

        if ($name === '' && $phone === '') {
            return null;
        }

        $attributes = array_filter([
            'name' => $name ?: null,
            'email' => $request->input("{$prefix}_email") ?: null,
            'occupation' => $request->input("{$prefix}_occupation") ?: null,
            'qualification' => $request->input("{$prefix}_qualification") ?: null,
            'annual_income' => $request->filled("{$prefix}_annual_income") ? $request->input("{$prefix}_annual_income") : null,
            'aadhaar_no' => $request->input("{$prefix}_aadhaar_no") ?: null,
            'pan_no' => $request->input("{$prefix}_pan_no") ?: null,
            'relationship' => $prefix === 'guardian' ? ($request->input('guardian_relationship') ?: null) : null,
        ], fn ($v) => $v !== null);

        if ($phone === '') {
            return ParentGuardian::create($attributes + ['phone' => null, 'name' => $name ?: "Unknown ({$prefix})"]);
        }

        $parent = ParentGuardian::where('phone', $phone)->first();
        if ($parent) {
            $parent->fill($attributes)->save();

            return $parent;
        }

        return ParentGuardian::create($attributes + ['phone' => $phone, 'name' => $name ?: "Unknown ({$prefix})"]);
    }

    /**
     * Seeds/refreshes this student's history row for one academic session (defaults to the
     * ERP header's current session — see AcademicSession::fromRequest()) with a Class/Section/
     * Roll snapshot, reusing the exact table Student Master Import writes — a historical
     * record distinct from the student's live relationship on `students` itself.
     */
    private function saveSessionHistory(Student $student, Request $request): void
    {
        $session = $request->filled('academic_session_id')
            ? AcademicSession::find($request->input('academic_session_id'))
            : AcademicSession::fromRequest($request, true);

        if (! $session) {
            return;
        }

        $data = array_filter([
            'class_name' => $student->schoolClass?->name,
            'section_name' => $student->section?->name,
            'roll_no' => $student->roll_no,
        ], fn ($v) => $v !== null);

        if (empty($data)) {
            return;
        }

        StudentSessionHistory::firstOrNew(['student_id' => $student->id, 'session' => $session->name])
            ->fill($data)
            ->save();
    }

    /**
     * Upserts the 1:1 student_additional_details row. Only keeps a row at all if the RESULTING
     * record (existing data merged with this update) has at least one field/flag actually set —
     * checked against the merged state, not just the fields present in this particular request,
     * so a partial update (e.g. an API call that only sends a few fields) can never wipe out
     * other real data that this request simply didn't mention.
     */
    private function saveDetail(Student $student, array $detailData): void
    {
        foreach (['report_card_received', 'cc_received', 'tc_received', 'dob_certificate_received'] as $flag) {
            $detailData[$flag] ??= false;
        }

        $detail = $student->additionalDetail ?: new StudentAdditionalDetail(['student_id' => $student->id]);
        $detail->fill($detailData);

        $flagFields = ['report_card_received', 'cc_received', 'tc_received', 'dob_certificate_received'];
        $mergedAttributes = collect($detail->getAttributes())->except(['id', 'student_id', 'created_at', 'updated_at']);
        $hasAnyValue = $mergedAttributes->except($flagFields)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
        $hasAnyFlag = $mergedAttributes->only($flagFields)->contains(fn ($v) => (bool) $v);

        if ($hasAnyValue || $hasAnyFlag) {
            $detail->save();
        } elseif ($detail->exists) {
            // The merged result is now entirely empty — remove the stale row rather than leaving an all-null one.
            $detail->delete();
        }
    }

    private function validateSectionBelongsToClass(Request $request): void
    {
        if (! $request->filled('section_id')) {
            return;
        }

        $belongs = Section::where('id', $request->input('section_id'))
            ->where('school_class_id', $request->input('school_class_id'))
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'section_id' => 'The selected section does not belong to the selected class.',
            ]);
        }
    }
}
