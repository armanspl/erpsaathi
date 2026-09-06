<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\HostelAllocation;
use App\Models\ImportExportLog;
use App\Models\ImportFailedRow;
use App\Models\ImportRowLog;
use App\Models\ParentGuardian;
use App\Models\Room;
use App\Models\RouteStop;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAdditionalDetail;
use App\Models\StudentTransport;
use App\Models\TransportRoute;
use App\Models\Vehicle;
use App\Services\ExcelDateParser;
use App\Services\SpreadsheetImportReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Retired from the Import & Export UI — no longer routed (see routes/erp_api.php) since
 * StudentMasterImportController is now the primary student importer. Kept in the codebase
 * because it still owns logic Feature 1 doesn't replicate: parent (father/mother) resolution,
 * Transport/Hostel auto-assignment, and the health/document-received fields on
 * student_additional_details. See docs/student-import-management.md for the full picture.
 */
class StudentImportController extends Controller
{
    /**
     * Maps the client's real Excel headers (lowercased/trimmed) to internal field keys.
     * Any header not listed here is simply ignored (Photo columns, Caste, Fees Balance —
     * see the Student Import roadmap for why those are intentionally not imported).
     */
    private const HEADER_MAP = [
        'adm no.' => 'admission_no',
        'rollno' => 'roll_no',
        'student name' => 'name',
        'class' => 'class',
        'route' => 'route',
        'bus no.' => 'bus_no',
        'category' => 'category',
        'house' => 'house',
        'section' => 'section',
        'gender' => 'gender',
        'date of birth' => 'dob',
        'address1' => 'address',
        'city1' => 'city',
        'state' => 'state',
        'phone' => 'mobile',
        'email' => 'email',
        'father name' => 'father_name',
        'father qualification' => 'father_qualification',
        'father occupation' => 'father_occupation',
        'father mobile' => 'father_mobile',
        'father dob' => 'father_dob',
        'mother name' => 'mother_name',
        'mother qualification' => 'mother_qualification',
        'mother occupation' => 'mother_occupation',
        'mother mobile' => 'mother_mobile',
        'mother dob' => 'mother_dob',
        'parents anniversary date' => 'parents_anniversary_date',
        'last school name' => 'last_school_name',
        'last exam' => 'last_exam',
        'lastexam' => 'last_exam',
        'year' => 'last_exam_year',
        'status' => 'last_exam_status',
        'marks' => 'last_exam_marks',
        'board' => 'last_exam_board',
        'form no.' => 'form_no',
        'formno' => 'form_no',
        'remarks1' => 'remarks_1',
        'opening balance' => 'opening_balance',
        'op bal.' => 'opening_balance',
        'hostel room no.' => 'hostel_room_no',
        'hostel room no' => 'hostel_room_no',
        'bed no.' => 'hostel_bed_no',
        'school status' => 'status',
        'discontinue date' => 'discontinue_date',
        'report card' => 'report_card_received',
        'reportc' => 'report_card_received',
        'cc' => 'cc_received',
        'tc' => 'tc_received',
        'dob certificate' => 'dob_certificate_received',
        'dobcertificate' => 'dob_certificate_received',
        'date of admission' => 'admission_date',
        'date of addmission' => 'admission_date',
        'remarks2' => 'remarks_2',
        'scholarship no.' => 'scholarship_no',
        'blood group' => 'blood_group',
        'height' => 'height',
        'weight' => 'weight',
        'family' => 'family',
        'vision left' => 'vision_left',
        'vision right' => 'vision_right',
        'dental hygiene' => 'dental_hygiene',
        'dental hygeine' => 'dental_hygiene',
        'additional field 1' => 'additional_field_1',
        'additional field 2' => 'additional_field_2',
        'additional field 3' => 'additional_field_3',
        'additional field 4' => 'additional_field_4',
        'additional field 5' => 'additional_field_5',
        'additional field 6' => 'additional_field_6',
        'additional field 7' => 'additional_field_7',
        'additional field 8' => 'additional_field_8',
        'additional field 9' => 'additional_field_9',
        'additional field 10' => 'additional_field_10',
        'student id' => 'student_ref_id',
        'aadhaar uid' => 'aadhar_no',
        'aadhar card uid id' => 'aadhar_no',
        'biometrics card no.' => 'biometric_card_no',
        'child uid' => 'child_uid',
        'child uid id' => 'child_uid',
        'gr no.' => 'gr_no',
        'pen no.' => 'pen_no',
    ];

    /**
     * Headers that ARE recognized but whose data is deliberately never stored anywhere, so their
     * presence in an uploaded file is surfaced to the user (via ImportExportLog::ignored_columns)
     * instead of silently vanishing. Caste is excluded for sensitivity; Fees Balance is computed
     * live by FeeCalculator and would go stale; the photo columns have no file-upload path yet.
     */
    private const EXCLUDED_HEADERS = [
        'caste' => 'Caste',
        'fees balance' => 'Fees Balance',
        'father photo' => 'Father Photo',
        'mother photo' => 'Mother Photo',
        'photo' => 'Photo',
        'student photo' => 'Photo',
    ];

    /** Values that mean "no data" in the client's spreadsheets, beyond a plain empty string. */
    private const BLANK_VALUES = ['n.a.', 'na', 'n/a', 'nil', '-', 'none'];

    /** Alternate spellings for existing class names, so an abbreviation resolves to the real class rather than failing. */
    private const CLASS_ALIASES = [
        'nur' => 'Nursery',
    ];

    /** Normalizes free-text "School Status" values to the students.status enum. Unrecognized/blank → Active. */
    private const STATUS_MAP = [
        'active' => 'Active',
        'continue' => 'Active',
        'continuing' => 'Active',
        'studying' => 'Active',
        'inactive' => 'Inactive',
        'left' => 'Inactive',
        'discontinued' => 'Inactive',
        'transferred' => 'Transferred',
        'transfer' => 'Transferred',
        'tc issued' => 'Transferred',
        'tc' => 'Transferred',
    ];

    /** Boolean-ish document-received columns: any non-blank value other than these "falsy" strings counts as received. */
    private const FALSY_VALUES = ['', 'no', 'n', '0', 'false', 'not received', 'pending'];

    /** @see ExcelDateParser::parse() — extracted so StudentMasterImportController can reuse it. */
    private static function parseDate(?string $value): ?string
    {
        return ExcelDateParser::parse($value);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        ['header' => $header, 'rows' => $lines] = SpreadsheetImportReader::read($request->file('file'));

        $ignoredColumns = array_values(array_unique(array_filter(
            array_map(fn ($column) => self::EXCLUDED_HEADERS[$column] ?? null, $header)
        )));

        $totalRows = 0;
        $successCount = 0;
        $rowOutcomes = [];
        $counters = ['classes_created' => 0, 'sections_created' => 0, 'vehicles_created' => 0, 'rooms_created' => 0, 'beds_created' => 0];

        $rowNumber = 1;
        foreach ($lines as $line) {
            $rowNumber++;
            if (count(array_filter($line, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }
            $totalRows++;

            $row = [];
            foreach ($header as $index => $column) {
                if (array_key_exists($column, self::HEADER_MAP)) {
                    $value = trim((string) ($line[$index] ?? ''));
                    $row[self::HEADER_MAP[$column]] = in_array(strtolower($value), self::BLANK_VALUES, true) ? '' : $value;
                }
            }

            $outcome = $this->importRow($row);
            $rowOutcomes[] = ['row_number' => $rowNumber] + $outcome;

            if ($outcome['status'] === 'Success') {
                $successCount++;
                foreach (['class' => 'classes_created', 'section' => 'sections_created', 'vehicle' => 'vehicles_created', 'room' => 'rooms_created', 'bed' => 'beds_created'] as $flag => $counterKey) {
                    if ($outcome['summary']['auto_created'][$flag]) {
                        $counters[$counterKey]++;
                    }
                }
            }
        }

        $log = ImportExportLog::create([
            'direction' => 'Import',
            'entity' => 'student',
            'filename' => $request->file('file')->getClientOriginalName(),
            'total_rows' => $totalRows,
            'success_count' => $successCount,
            'failed_count' => $totalRows - $successCount,
            'ignored_columns' => $ignoredColumns ?: null,
            'performed_by_id' => Auth::guard('erp')->id(),
            ...$counters,
        ]);

        foreach ($rowOutcomes as $outcome) {
            ImportRowLog::create([
                'import_export_log_id' => $log->id,
                'row_number' => $outcome['row_number'],
                'status' => $outcome['status'],
                'identifier' => $outcome['identifier'],
                'summary' => $outcome['summary'],
                'error_message' => $outcome['error_message'],
            ]);

            if ($outcome['status'] === 'Failed') {
                ImportFailedRow::create([
                    'import_export_log_id' => $log->id,
                    'row_number' => $outcome['row_number'],
                    'row_data' => $outcome['row_data'],
                    'error_message' => $outcome['error_message'],
                ]);
            }
        }

        return response()->json([
            'log' => $log,
            'failed_rows' => $log->failedRows,
        ], 201);
    }

    /**
     * Upsert one student (+ related parent/additional-detail/transport/hostel records) from a parsed
     * spreadsheet row. Matches existing students by admission_no; if found, only the columns this row
     * actually provides are written — blank Excel cells never overwrite existing data. If not found,
     * creates a new student (which requires the class to resolve, since school_class_id is required).
     *
     * Returns an outcome array — {status, identifier, summary, error_message, row_data} — used both to
     * decide success/failure and to persist a per-row ImportRowLog (and, on failure, ImportFailedRow)
     * so a re-import's actual effects (created vs updated, what was auto-created, what was skipped and
     * why) are visible afterward instead of only a pass/fail count.
     */
    private function importRow(array $row): array
    {
        $identifier = $row['admission_no'] ?? null;

        if (empty($row['admission_no']) || empty($row['name'])) {
            return $this->failedRow($row, 'Admission No and Name are required.');
        }

        $existingStudent = Student::where('admission_no', $row['admission_no'])->first();

        $schoolClassId = null;
        $schoolClassCreated = false;
        if (! empty($row['class'])) {
            $className = self::CLASS_ALIASES[strtolower($row['class'])] ?? $row['class'];
            $schoolClass = SchoolClass::firstOrCreate(['name' => $className], ['capacity' => 40]);
            $schoolClassId = $schoolClass->id;
            $schoolClassCreated = $schoolClass->wasRecentlyCreated;
        } elseif (! $existingStudent) {
            return $this->failedRow($row, "Class \"" . ($row['class'] ?? '') . "\" was not found.");
        }

        $sectionId = null;
        $sectionCreated = false;
        if (! empty($row['section'])) {
            $classIdForSection = $schoolClassId ?? $existingStudent?->school_class_id;
            $section = Section::firstOrCreate(
                ['school_class_id' => $classIdForSection, 'name' => $row['section']],
                ['capacity' => 40]
            );
            $sectionId = $section->id;
            $sectionCreated = $section->wasRecentlyCreated;
        }

        $data = array_filter([
            'admission_no' => $row['admission_no'],
            'name' => $row['name'],
            'roll_no' => ($row['roll_no'] ?? '') !== '' ? (int) $row['roll_no'] : null,
            'school_class_id' => $schoolClassId,
            'section_id' => $sectionId,
            'gender' => ($row['gender'] ?? '') ?: null,
            'dob' => self::parseDate($row['dob'] ?? null),
            'blood_group' => ($row['blood_group'] ?? '') ?: null,
            'category' => ($row['category'] ?? '') ?: null,
            'aadhar_no' => ($row['aadhar_no'] ?? '') ?: null,
            'mobile' => ($row['mobile'] ?? '') ?: null,
            'email' => ($row['email'] ?? '') ?: null,
            'address' => ($row['address'] ?? '') ?: null,
            'city' => ($row['city'] ?? '') ?: null,
            'state' => ($row['state'] ?? '') ?: null,
            'status' => ! empty($row['status']) ? (self::STATUS_MAP[strtolower($row['status'])] ?? 'Active') : null,
            'admission_date' => self::parseDate($row['admission_date'] ?? null),
        ], fn ($v) => $v !== null);

        $validator = Validator::make($data, [
            'admission_no' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'roll_no' => 'nullable|integer|min:0',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'gender' => 'nullable|in:Male,Female,Other',
            'dob' => 'nullable|date',
            'blood_group' => 'nullable|string|max:10',
            'category' => 'nullable|string|max:100',
            'aadhar_no' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'status' => 'nullable|in:Active,Inactive,Transferred',
            'admission_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->failedRow($row, implode(' ', $validator->errors()->all()));
        }

        $summary = DB::transaction(function () use ($validator, $row, $existingStudent, $schoolClassCreated, $sectionCreated) {
            $data = $validator->validated();

            $father = $this->resolveParent($row, 'father');
            $mother = $this->resolveParent($row, 'mother');
            if ($father) {
                $data['father_id'] = $father->id;
            }
            if ($mother) {
                $data['mother_id'] = $mother->id;
            }

            if ($existingStudent) {
                $existingStudent->update($data);
                $student = $existingStudent;
                $studentAction = 'updated';
            } else {
                $student = Student::create($data);
                $studentAction = 'created';
            }

            $detailOutcome = $this->upsertAdditionalDetail($student, $row);
            $transportOutcome = $this->assignTransport($student, $row);
            $hostelOutcome = $this->assignHostel($student, $row);

            return [
                'student' => ['action' => $studentAction, 'id' => $student->id],
                'father' => $father ? ['action' => $father->wasRecentlyCreated ? 'created' : 'matched', 'id' => $father->id] : null,
                'mother' => $mother ? ['action' => $mother->wasRecentlyCreated ? 'created' : 'matched', 'id' => $mother->id] : null,
                'additional_details' => $detailOutcome,
                'transport' => $transportOutcome,
                'hostel' => $hostelOutcome,
                'auto_created' => [
                    'class' => $schoolClassCreated,
                    'section' => $sectionCreated,
                    'vehicle' => $transportOutcome['vehicle_created'],
                    'room' => $hostelOutcome['room_created'],
                    'bed' => $hostelOutcome['bed_created'],
                ],
            ];
        });

        return ['status' => 'Success', 'identifier' => $identifier, 'summary' => $summary, 'error_message' => null, 'row_data' => null];
    }

    /** Builds the outcome array for a row that failed validation or matching, before any writes happened. */
    private function failedRow(array $row, string $message): array
    {
        return ['status' => 'Failed', 'identifier' => $row['admission_no'] ?? null, 'summary' => null, 'error_message' => $message, 'row_data' => $row];
    }

    /**
     * Finds an existing parent by phone (same dedup key used elsewhere, e.g. AdmissionEnquiryController).
     * If found, only the non-blank fields this row provides are updated — an existing parent's data is
     * never blanked out by a re-import. If not found, creates a new parent.
     */
    private function resolveParent(array $row, string $prefix): ?ParentGuardian
    {
        $name = $row["{$prefix}_name"] ?? '';
        $phone = $row["{$prefix}_mobile"] ?? '';
        $occupation = $row["{$prefix}_occupation"] ?? '';
        $qualification = $row["{$prefix}_qualification"] ?? '';
        $dob = $row["{$prefix}_dob"] ?? '';

        if ($name === '' && $phone === '') {
            return null;
        }

        $attributes = array_filter([
            'name' => $name ?: null,
            'occupation' => $occupation ?: null,
            'qualification' => $qualification ?: null,
            'dob' => self::parseDate($dob),
        ], fn ($v) => $v !== null);

        if ($phone !== '') {
            $parent = ParentGuardian::where('phone', $phone)->first();
            if ($parent) {
                $parent->fill($attributes)->save();

                return $parent;
            }

            return ParentGuardian::create($attributes + ['phone' => $phone, 'name' => $name ?: "Unknown ({$prefix})"]);
        }

        return ParentGuardian::create($attributes + ['phone' => null, 'name' => $name ?: "Unknown ({$prefix})"]);
    }

    /**
     * Upserts the student_additional_details row. Only fields this row actually provides are written —
     * on an existing row, blank Excel cells leave the current value untouched rather than clearing it.
     */
    private function upsertAdditionalDetail(Student $student, array $row): array
    {
        $fields = [
            'house', 'height', 'weight', 'vision_left', 'vision_right', 'dental_hygiene', 'family',
            'scholarship_no', 'form_no', 'remarks_1', 'remarks_2',
            'last_school_name', 'last_exam', 'last_exam_year', 'last_exam_status', 'last_exam_marks', 'last_exam_board',
            'student_ref_id', 'biometric_card_no', 'child_uid', 'gr_no', 'pen_no', 'opening_balance',
            'additional_field_1', 'additional_field_2', 'additional_field_3', 'additional_field_4', 'additional_field_5',
            'additional_field_6', 'additional_field_7', 'additional_field_8', 'additional_field_9', 'additional_field_10',
        ];

        $data = [];
        foreach ($fields as $field) {
            if (($row[$field] ?? '') !== '') {
                $data[$field] = $row[$field];
            }
        }
        foreach (['discontinue_date', 'parents_anniversary_date'] as $field) {
            $parsed = self::parseDate($row[$field] ?? null);
            if ($parsed !== null) {
                $data[$field] = $parsed;
            }
        }
        foreach (['report_card_received', 'cc_received', 'tc_received', 'dob_certificate_received'] as $field) {
            if (($row[$field] ?? '') !== '') {
                $data[$field] = ! in_array(strtolower(trim($row[$field])), self::FALSY_VALUES, true);
            }
        }

        if (empty($data)) {
            return ['action' => 'skipped'];
        }

        $detail = StudentAdditionalDetail::firstOrNew(['student_id' => $student->id]);
        $detail->fill($data);
        $detail->save();

        return ['action' => 'saved'];
    }

    /**
     * Route stays match-only (by name). Vehicle (Bus No.) auto-creates if unmatched — schema defaults
     * apply (type='Bus', capacity=0) — and is assigned to a route that was resolved by name but doesn't
     * already have a vehicle; otherwise it's created unassigned. Silently skipped if nothing resolves —
     * a row whose Route name simply didn't match anything is flagged with reason 'route_not_found' so
     * that silent skip is visible in the per-row log instead of just disappearing.
     */
    private function assignTransport(Student $student, array $row): array
    {
        if (empty($row['route']) && empty($row['bus_no'])) {
            return ['action' => 'skipped', 'reason' => null, 'vehicle_created' => false];
        }

        $route = ! empty($row['route']) ? TransportRoute::where('name', $row['route'])->first() : null;

        $vehicle = null;
        $vehicleCreated = false;
        if (! empty($row['bus_no'])) {
            $vehicle = Vehicle::where('vehicle_no', $row['bus_no'])->first();
            if (! $vehicle) {
                $vehicle = Vehicle::create(['vehicle_no' => $row['bus_no']]);
                $vehicleCreated = true;
            }
            if (! $route) {
                $route = TransportRoute::where('vehicle_id', $vehicle->id)->first();
            }
        }

        if ($route && ! $route->vehicle_id && $vehicle) {
            $route->update(['vehicle_id' => $vehicle->id]);
        }

        if (! $route) {
            return ['action' => 'skipped', 'reason' => 'route_not_found', 'vehicle_created' => $vehicleCreated];
        }

        $stop = RouteStop::where('route_id', $route->id)->orderBy('sequence_no')->first();
        if (! $stop) {
            return ['action' => 'skipped', 'reason' => 'route_has_no_stops', 'vehicle_created' => $vehicleCreated];
        }

        StudentTransport::updateOrCreate(['student_id' => $student->id], [
            'route_id' => $route->id,
            'route_stop_id' => $stop->id,
            'start_date' => $student->admission_date ?? now()->toDateString(),
            'status' => 'Active',
        ]);

        return ['action' => 'assigned', 'reason' => null, 'vehicle_created' => $vehicleCreated];
    }

    /**
     * Room/Bed auto-create if unmatched (schema defaults apply). If the student already has an active
     * allocation to a different bed than the one resolved this row, the old allocation is vacated first
     * (mirroring HostelAllocationController::vacate()) before the new one is created. No-op if the
     * resolved bed is the student's current bed; silently skipped if the resolved bed is occupied by
     * someone else.
     */
    private function assignHostel(Student $student, array $row): array
    {
        if (empty($row['hostel_room_no']) || empty($row['hostel_bed_no'])) {
            return ['action' => 'skipped', 'reason' => null, 'room_created' => false, 'bed_created' => false];
        }

        $room = Room::where('room_no', $row['hostel_room_no'])->first();
        $roomCreated = false;
        if (! $room) {
            $room = Room::create(['room_no' => $row['hostel_room_no']]);
            $roomCreated = true;
        }

        $bed = Bed::where('room_id', $room->id)->where('bed_no', $row['hostel_bed_no'])->first();
        $bedCreated = false;
        if (! $bed) {
            $bed = Bed::create(['room_id' => $room->id, 'bed_no' => $row['hostel_bed_no'], 'status' => 'Available']);
            $bedCreated = true;
        }

        $currentAllocation = HostelAllocation::where('student_id', $student->id)->where('status', 'Active')->first();

        if ($currentAllocation && $currentAllocation->bed_id == $bed->id) {
            return ['action' => 'skipped', 'reason' => 'already_in_bed', 'room_created' => $roomCreated, 'bed_created' => $bedCreated];
        }

        if ($bed->status !== 'Available') {
            return ['action' => 'skipped', 'reason' => 'bed_occupied', 'room_created' => $roomCreated, 'bed_created' => $bedCreated];
        }

        $switched = (bool) $currentAllocation;
        if ($currentAllocation) {
            $currentAllocation->update(['status' => 'Inactive', 'end_date' => now()->toDateString()]);
            $currentAllocation->bed()->update(['status' => 'Available']);
        }

        HostelAllocation::create([
            'student_id' => $student->id,
            'bed_id' => $bed->id,
            'start_date' => $student->admission_date ?? now()->toDateString(),
            'status' => 'Active',
        ]);
        $bed->update(['status' => 'Occupied']);

        return ['action' => $switched ? 'switched' : 'assigned', 'reason' => null, 'room_created' => $roomCreated, 'bed_created' => $bedCreated];
    }
}
