<?php

namespace App\Http\Controllers\Erp\Reports;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Student;
use App\Models\StudentAdditionalDetail;
use App\Models\StudentSessionHistory;
use App\Models\StudentUdiseDetail;
use App\Support\TabularExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * UDISE student profile report — header + personal / school / facilities field sets
 * drawn from students, udise details, additional details, and session history.
 */
class UdiseReportController extends Controller
{
    /**
     * Exportable column catalog. Order here is the default export order.
     * key => [group, label]
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const EXPORT_COLUMNS = [
        // Basic / header
        'student_name' => ['basic', 'Student Name'],
        'class_section' => ['basic', 'Class & Section'],
        'academic_year' => ['basic', 'Academic Year'],
        'pen' => ['basic', 'Permanent Education Number (PEN)'],
        'aadhaar_status' => ['basic', 'Aadhaar Status'],
        // Personal
        'name_as_per_record' => ['basic', "Student's Name (as Per Record)"],
        'gender' => ['basic', 'Gender'],
        'dob' => ['basic', 'Date of Birth'],
        'pen_number' => ['basic', 'Permanent Education Number'],
        'mother_name' => ['basic', "Mother's Name"],
        'father_name' => ['basic', "Father's Name"],
        'guardian_name' => ['basic', "Guardian's Name"],
        'aadhaar_no' => ['basic', 'AADHAAR Number of Student'],
        'name_as_per_aadhaar' => ['basic', 'Name as per AADHAAR'],
        'address' => ['basic', 'Address'],
        'pincode' => ['basic', 'Pincode'],
        'mobile' => ['basic', 'Mobile Number'],
        'alternate_mobile' => ['basic', 'Alternate Mobile Number'],
        'email' => ['basic', 'Contact email-id'],
        'mother_tongue' => ['basic', 'Mother Tongue'],
        'social_category' => ['basic', 'Social Category'],
        'minority_group' => ['basic', 'Minority Group'],
        'bpl_beneficiary' => ['basic', 'Whether BPL beneficiary'],
        'aay_beneficiary' => ['basic', 'Whether Antyodaya Anna Yojana (AAY)'],
        'ews_disadvantaged' => ['basic', 'Whether belongs to EWS / Disadvantaged Group'],
        'cwsn' => ['basic', 'Whether CWSN'],
        'type_of_impairments' => ['basic', 'Type of Impairments'],
        'indian_nationality' => ['basic', 'Indian Nationality'],
        'out_of_school_child' => ['basic', 'Is Child Identified as Out of School-Child'],
        'mainstreamed_when' => ['basic', 'When the Child is mainstreamed'],
        'disability_certificate' => ['basic', 'Whether having Disability Certificate?'],
        'disability_percentage' => ['basic', 'Disability Percentage'],
        'blood_group' => ['basic', 'Blood Group'],
        // Admission / academic
        'admission_no' => ['admission', 'Admission Number in Present School'],
        'admission_date' => ['admission', 'Admission Date in Present School'],
        'class_section_roll' => ['admission', 'Class/Section Roll No'],
        'medium_of_instruction' => ['admission', 'Medium of Instruction'],
        'languages_group' => ['admission', 'Languages Group Studied'],
        'academic_stream' => ['admission', 'Academic Stream opted'],
        'subjects_group' => ['admission', 'Subjects Group Studied'],
        'prev_year_status' => ['admission', 'Status in Previous Academic Year'],
        'prev_year_class' => ['admission', 'Grade/Class Studied in Previous Year'],
        'rte_12c' => ['admission', 'Whether Admitted under Section 12C of RTE Act?'],
        'rte_amount_claimed' => ['admission', 'Amount Claimed from Government for RTE'],
        'prev_exam_result' => ['admission', 'Examination Result in Previous Class'],
        'prev_marks_percent' => ['admission', 'Marks Obtained (%)'],
        'prev_attendance_days' => ['admission', 'No. of days child attended school (Prev. Year)'],
        // Facilities
        'facilities_provided' => ['facilities', 'Whether Facilities provided to Student'],
        'cwsn_facilities' => ['facilities', 'Facilities provided in case of CWSN'],
        'olympiads' => ['facilities', 'Appeared in State/National Competitions/Olympiads'],
        'ncc' => ['facilities', 'NCC'],
        'nss' => ['facilities', 'NSS'],
        'scouts_guides' => ['facilities', 'Scouts and Guides'],
        'height_cm' => ['facilities', "Student's Height (in CMs)"],
        'weight_kg' => ['facilities', "Student's Weight (in KGs)"],
        'distance_to_school' => ['facilities', 'Approximate Distance of residence to school'],
        'parents_education' => ['facilities', 'Completed Highest Education Level of Parents'],
    ];

    public const GROUP_LABELS = [
        'basic' => 'Basic Student Information',
        'admission' => 'Admission / Academic Information',
        'facilities' => 'Facilities / Other Information',
    ];

    public function columns(): mixed
    {
        $groups = [];
        foreach (self::GROUP_LABELS as $groupKey => $groupLabel) {
            $fields = [];
            foreach (self::EXPORT_COLUMNS as $key => [$group, $label]) {
                if ($group === $groupKey) {
                    $fields[] = ['key' => $key, 'label' => $label];
                }
            }
            $groups[] = ['key' => $groupKey, 'label' => $groupLabel, 'fields' => $fields];
        }

        return response()->json([
            'groups' => $groups,
            'all_keys' => array_keys(self::EXPORT_COLUMNS),
        ]);
    }

    public function index(Request $request): mixed
    {
        $data = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'search' => 'nullable|string|max:100',
            'student_id' => 'nullable|exists:students,id',
            'format' => 'nullable|in:csv,xlsx,pdf',
            'columns' => 'nullable',
        ]);

        $session = AcademicSession::fromRequest($request, true);
        $sessionName = $session?->name ?? '';

        $query = Student::with([
            'schoolClass:id,name',
            'section:id,name',
            'father:id,name',
            'mother:id,name',
            'guardian:id,name',
            'udiseDetail',
            'additionalDetail',
            'latestSessionHistory',
        ])->where('status', 'Active');

        if (! empty($data['branch_id'])) {
            $query->where('branch_id', $data['branch_id']);
        }
        if (! empty($data['school_class_id'])) {
            $query->where('school_class_id', $data['school_class_id']);
        }
        if (! empty($data['section_id'])) {
            $query->where('section_id', $data['section_id']);
        }
        if (! empty($data['search'])) {
            $q = $data['search'];
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('admission_no', 'like', "%{$q}%")
                    ->orWhereHas('udiseDetail', fn ($u) => $u->where('student_pen', 'like', "%{$q}%"));
            });
        }
        if (! empty($data['student_id'])) {
            $query->where('id', $data['student_id']);
        }

        $students = $query
            ->orderBy('school_class_id')
            ->orderByRaw('CAST(roll_no AS UNSIGNED), roll_no')
            ->orderBy('name')
            ->get();

        $rows = $students->map(fn (Student $s) => $this->mapStudent($s, $sessionName))->values();

        if (! empty($data['format'])) {
            return $this->export($rows->all(), $data['format'], $this->resolveSelectedKeys($request));
        }

        return response()->json([
            'session' => $sessionName,
            'rows' => $rows,
            'export_columns' => [
                'groups' => collect(self::GROUP_LABELS)->map(fn ($label, $key) => [
                    'key' => $key,
                    'label' => $label,
                    'fields' => collect(self::EXPORT_COLUMNS)
                        ->filter(fn ($meta) => $meta[0] === $key)
                        ->map(fn ($meta, $fieldKey) => ['key' => $fieldKey, 'label' => $meta[1]])
                        ->values()
                        ->all(),
                ])->values()->all(),
                'all_keys' => array_keys(self::EXPORT_COLUMNS),
            ],
        ]);
    }

    public function update(Request $request, Student $student): mixed
    {
        $data = $request->validate([
            'values' => 'required|array',
        ]);

        $values = $data['values'];
        $session = AcademicSession::fromRequest($request, true);

        DB::transaction(function () use ($student, $values, $session) {
            $student->loadMissing(['udiseDetail', 'additionalDetail', 'latestSessionHistory', 'father', 'mother', 'guardian']);

            $studentPatch = [];
            if (array_key_exists('name_as_per_record', $values) || array_key_exists('student_name', $values)) {
                $name = trim((string) ($values['name_as_per_record'] ?? $values['student_name'] ?? ''));
                if ($name !== '') {
                    $studentPatch['name'] = $name;
                }
            }
            foreach ([
                'gender' => 'gender',
                'aadhaar_no' => 'aadhar_no',
                'address' => 'address',
                'pincode' => 'pincode',
                'mobile' => 'mobile',
                'email' => 'email',
                'social_category' => 'category',
                'blood_group' => 'blood_group',
                'admission_no' => 'admission_no',
            ] as $from => $to) {
                if (array_key_exists($from, $values)) {
                    $studentPatch[$to] = $this->blankToNull($values[$from]);
                }
            }
            if (array_key_exists('dob', $values)) {
                $studentPatch['dob'] = $this->parseDate($values['dob']);
            }
            if (array_key_exists('admission_date', $values)) {
                $studentPatch['admission_date'] = $this->parseDate($values['admission_date']);
            }
            if (array_key_exists('roll_no', $values)) {
                $studentPatch['roll_no'] = $this->blankToNull($values['roll_no']);
            }
            if (array_key_exists('school_class_id', $values)) {
                $studentPatch['school_class_id'] = $values['school_class_id'] ?: null;
            }
            if (array_key_exists('section_id', $values)) {
                $studentPatch['section_id'] = $values['section_id'] ?: null;
            }
            if ($studentPatch !== []) {
                $student->update($studentPatch);
            }

            $udisePatch = [];
            foreach ([
                'pen_number' => 'student_pen',
                'pen' => 'student_pen',
                'mother_name' => 'mother_name',
                'father_name' => 'father_name',
                'guardian_name' => 'guardian_name',
                'name_as_per_aadhaar' => 'name_as_per_aadhaar',
                'alternate_mobile' => 'alternate_mobile',
                'mother_tongue' => 'mother_tongue',
                'minority_group' => 'minority_group',
                'type_of_impairments' => 'type_of_impairments',
                'out_of_school_child' => 'out_of_school_child',
                'mainstreamed_when' => 'mainstreamed_when',
                'disability_certificate' => 'disability_certificate',
                'disability_percentage' => 'disability_percentage',
                'medium_of_instruction' => 'medium_of_instruction',
                'languages_group' => 'languages_group',
                'academic_stream' => 'academic_stream',
                'subjects_group' => 'subjects_group',
                'rte_12c' => 'rte_ews_admission',
                'rte_amount_claimed' => 'rte_amount_claimed',
                'facilities_provided' => 'facilities_provided',
                'cwsn_facilities' => 'cwsn_facilities',
                'olympiads' => 'olympiads',
                'ncc' => 'ncc',
                'nss' => 'nss',
                'scouts_guides' => 'scouts_guides',
                'distance_to_school' => 'distance_to_school',
                'parents_education' => 'parents_education',
            ] as $from => $to) {
                if (array_key_exists($from, $values)) {
                    $udisePatch[$to] = $this->blankToNull($values[$from]);
                }
            }
            foreach ([
                'bpl_beneficiary' => 'bpl_beneficiary',
                'aay_beneficiary' => 'aay_beneficiary',
                'ews_disadvantaged' => 'ews_disadvantaged',
                'cwsn' => 'cwsn',
                'indian_nationality' => 'indian_national',
            ] as $from => $to) {
                if (array_key_exists($from, $values)) {
                    $udisePatch[$to] = $this->parseYesNo($values[$from]);
                }
            }
            if ($udisePatch !== []) {
                StudentUdiseDetail::firstOrNew(['student_id' => $student->id])->fill($udisePatch)->save();
            }

            // Keep additional_detail.pen_no in sync with UDISE PEN.
            if (array_key_exists('pen_number', $values) || array_key_exists('pen', $values)) {
                $pen = $this->blankToNull($values['pen_number'] ?? $values['pen'] ?? null);
                StudentAdditionalDetail::firstOrNew(['student_id' => $student->id])
                    ->fill(['pen_no' => $pen])
                    ->save();
            }

            $addPatch = [];
            if (array_key_exists('height_cm', $values)) {
                $addPatch['height'] = $this->blankToNull($values['height_cm']);
            }
            if (array_key_exists('weight_kg', $values)) {
                $addPatch['weight'] = $this->blankToNull($values['weight_kg']);
            }
            if ($addPatch !== []) {
                StudentAdditionalDetail::firstOrNew(['student_id' => $student->id])->fill($addPatch)->save();
            }

            if ($session) {
                $histPatch = [];
                foreach ([
                    'prev_year_status' => 'previous_year_schooling_status',
                    'prev_year_class' => 'previous_year_class',
                    'prev_exam_result' => 'exam_result',
                    'prev_marks_percent' => 'exam_marks_percent',
                    'prev_attendance_days' => 'attendance_days',
                ] as $from => $to) {
                    if (array_key_exists($from, $values)) {
                        $histPatch[$to] = $this->blankToNull($values[$from]);
                    }
                }
                if ($histPatch !== []) {
                    $student->loadMissing(['schoolClass:id,name', 'section:id,name']);
                    StudentSessionHistory::firstOrNew([
                        'student_id' => $student->id,
                        'session' => $session->name,
                    ])->fill(array_merge([
                        'class_name' => $student->schoolClass?->name,
                        'section_name' => $student->section?->name,
                        'roll_no' => $student->roll_no,
                    ], $histPatch))->save();
                }
            }

            // Mirror mother/father/guardian name onto linked parent rows when present.
            foreach (['father' => 'father_name', 'mother' => 'mother_name', 'guardian' => 'guardian_name'] as $rel => $key) {
                if (! array_key_exists($key, $values)) {
                    continue;
                }
                $name = trim((string) ($values[$key] ?? ''));
                if ($name === '' || ! $student->{$rel}) {
                    continue;
                }
                $student->{$rel}->update(['name' => $name]);
            }
        });

        $student->refresh()->load([
            'schoolClass:id,name',
            'section:id,name',
            'father:id,name',
            'mother:id,name',
            'guardian:id,name',
            'udiseDetail',
            'additionalDetail',
            'latestSessionHistory',
        ]);

        return response()->json([
            'row' => $this->mapStudent($student, $session?->name ?? ''),
        ]);
    }

    private function blankToNull(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return is_string($value) ? trim($value) : $value;
    }

    private function parseYesNo(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        $v = strtolower(trim((string) $value));

        return match ($v) {
            'yes', '1', 'true' => true,
            'no', '0', 'false' => false,
            default => null,
        };
    }

    private function parseDate(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mapStudent(Student $s, string $sessionName): array
    {
        $udise = $s->udiseDetail;
        $add = $s->additionalDetail;
        $hist = $s->latestSessionHistory;

        $pen = $udise?->student_pen ?: ($add?->pen_no ?: '');
        $aadhaar = (string) ($s->aadhar_no ?? '');
        $classSection = trim(($s->schoolClass?->name ?? '').' / '.($s->section?->name ?? ''), ' /');
        $classSectionRoll = trim($classSection.($s->roll_no ? ' · Roll '.$s->roll_no : ''), ' ·');

        $values = [
            'student_name' => $s->name,
            'class_section' => $classSection !== '' ? $classSection : null,
            'academic_year' => $sessionName !== '' ? $sessionName : null,
            'pen' => $pen !== '' ? $pen : null,
            'aadhaar_status' => $aadhaar !== '' ? 'Available' : 'Not Available',
            'name_as_per_record' => $s->name,
            'gender' => $s->gender,
            'dob' => $s->dob?->format('d M Y'),
            'pen_number' => $pen !== '' ? $pen : null,
            'mother_name' => $udise?->mother_name ?: $s->mother?->name,
            'father_name' => $udise?->father_name ?: $s->father?->name,
            'guardian_name' => $udise?->guardian_name ?: $s->guardian?->name,
            'aadhaar_no' => $aadhaar !== '' ? $aadhaar : null,
            'name_as_per_aadhaar' => $udise?->name_as_per_aadhaar,
            'address' => $s->address,
            'pincode' => $s->pincode,
            'mobile' => $s->mobile,
            'alternate_mobile' => $udise?->alternate_mobile,
            'email' => $s->email,
            'mother_tongue' => $udise?->mother_tongue,
            'social_category' => $s->category,
            'minority_group' => $udise?->minority_group,
            'bpl_beneficiary' => $this->yesNo($udise?->bpl_beneficiary),
            'aay_beneficiary' => $this->yesNo($udise?->aay_beneficiary),
            'ews_disadvantaged' => $this->yesNo($udise?->ews_disadvantaged),
            'cwsn' => $this->yesNo($udise?->cwsn),
            'type_of_impairments' => $udise?->type_of_impairments,
            'indian_nationality' => $this->yesNo($udise?->indian_national) ?: ($s->nationality ?: null),
            'out_of_school_child' => $udise?->out_of_school_child,
            'mainstreamed_when' => $udise?->mainstreamed_when,
            'disability_certificate' => $udise?->disability_certificate,
            'disability_percentage' => $udise?->disability_percentage,
            'blood_group' => $s->blood_group,
            'admission_no' => $s->admission_no,
            'admission_date' => $s->admission_date?->format('d M Y'),
            'class_section_roll' => $classSectionRoll !== '' ? $classSectionRoll : null,
            'medium_of_instruction' => $udise?->medium_of_instruction,
            'languages_group' => $udise?->languages_group,
            'academic_stream' => $udise?->academic_stream,
            'subjects_group' => $udise?->subjects_group,
            'prev_year_status' => $hist?->previous_year_schooling_status,
            'prev_year_class' => $hist?->previous_year_class,
            'rte_12c' => $udise?->rte_ews_admission,
            'rte_amount_claimed' => $udise?->rte_amount_claimed,
            'prev_exam_result' => $hist?->exam_result,
            'prev_marks_percent' => $hist?->exam_marks_percent,
            'prev_attendance_days' => $hist?->attendance_days,
            'facilities_provided' => $udise?->facilities_provided,
            'cwsn_facilities' => $udise?->cwsn_facilities,
            'olympiads' => $udise?->olympiads,
            'ncc' => $udise?->ncc,
            'nss' => $udise?->nss,
            'scouts_guides' => $udise?->scouts_guides,
            'height_cm' => $add?->height,
            'weight_kg' => $add?->weight,
            'distance_to_school' => $udise?->distance_to_school,
            'parents_education' => $udise?->parents_education,
        ];

        $toFields = static function (array $keys) use ($values): array {
            $out = [];
            foreach ($keys as $key) {
                $out[] = [
                    'key' => $key,
                    'label' => self::EXPORT_COLUMNS[$key][1],
                    'value' => $values[$key] ?? null,
                ];
            }

            return $out;
        };

        return [
            'id' => $s->id,
            'header' => [
                'student_name' => $values['student_name'] ?? '—',
                'class_section' => $values['class_section'] ?? '—',
                'academic_year' => $values['academic_year'] ?? '—',
                'pen' => $values['pen'] ?? '—',
                'aadhaar_status' => $values['aadhaar_status'],
            ],
            'values' => $values,
            'personal' => $toFields([
                'name_as_per_record', 'gender', 'dob', 'pen_number', 'mother_name', 'father_name', 'guardian_name',
                'aadhaar_no', 'name_as_per_aadhaar', 'address', 'pincode', 'mobile', 'alternate_mobile', 'email',
                'mother_tongue', 'social_category', 'minority_group', 'bpl_beneficiary', 'aay_beneficiary',
                'ews_disadvantaged', 'cwsn', 'type_of_impairments', 'indian_nationality', 'out_of_school_child',
                'mainstreamed_when', 'disability_certificate', 'disability_percentage', 'blood_group',
            ]),
            'schooling' => $toFields([
                'admission_no', 'admission_date', 'class_section_roll', 'medium_of_instruction', 'languages_group',
                'academic_stream', 'subjects_group', 'prev_year_status', 'prev_year_class', 'rte_12c',
                'rte_amount_claimed', 'prev_exam_result', 'prev_marks_percent', 'prev_attendance_days',
            ]),
            'facilities' => $toFields([
                'facilities_provided', 'cwsn_facilities', 'olympiads', 'ncc', 'nss', 'scouts_guides',
                'height_cm', 'weight_kg', 'distance_to_school', 'parents_education',
            ]),
        ];
    }

    private function yesNo(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value ? 'Yes' : 'No';
    }

    /**
     * @return list<string>
     */
    private function resolveSelectedKeys(Request $request): array
    {
        $raw = $request->input('columns', $request->query('columns'));
        if (is_string($raw)) {
            $raw = array_filter(array_map('trim', explode(',', $raw)));
        }
        if (! is_array($raw) || $raw === [] || in_array('All', $raw, true)) {
            return array_keys(self::EXPORT_COLUMNS);
        }

        $allowed = self::EXPORT_COLUMNS;
        $keys = [];
        foreach ($raw as $key) {
            if (is_string($key) && isset($allowed[$key])) {
                $keys[] = $key;
            }
        }

        // Preserve catalog order for selected keys.
        return array_values(array_filter(array_keys($allowed), fn ($k) => in_array($k, $keys, true)))
            ?: array_keys($allowed);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $keys
     */
    private function export(array $rows, string $format, array $keys): mixed
    {
        $header = array_map(fn ($k) => self::EXPORT_COLUMNS[$k][1], $keys);

        $table = [];
        foreach ($rows as $r) {
            $values = $r['values'] ?? [];
            $table[] = array_map(
                fn ($k) => $values[$k] ?? '',
                $keys
            );
        }

        return TabularExport::stream($header, $table, 'report-udise', 'UDISE Report', $format);
    }
}
