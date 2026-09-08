<?php

namespace App\Services;

use App\Http\Controllers\Erp\ImportExport\StudentMasterImportController;
use App\Models\AcademicSession;
use App\Models\ImportExportLog;
use App\Models\ParentGuardian;
use App\Models\RouteStop;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAdditionalDetail;
use App\Models\StudentSessionHistory;
use App\Models\StudentTransport;
use App\Models\StudentUdiseDetail;
use App\Models\TransportRoute;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Shared "Student Master Record" import pipeline (UDISE-style workbook, one row per student
 * per session it was ever enrolled in). Used by both the standalone
 * erp/dashboard/import-export?type=student-import screen
 * ({@see StudentMasterImportController}) and any "Student Master*" sheet found inside a
 * Global Workbook import ({@see \App\Http\Controllers\Erp\ImportExport\GlobalWorkbookImportController}),
 * so the header mapping and upsert rules never have to be kept in sync by hand across two places.
 */
class StudentMasterImportService
{
    /**
     * Maps the source file's real headers (lowercased/trimmed) to internal field keys.
     * "Status" and "STATUS" both lowercase to the identical string "status" — the file
     * genuinely has two different columns with that same rendered name (ACTIVE/INACTIVE
     * vs PROMOTED/LEFT/NEW) — so that one is deliberately NOT in this map and is instead
     * disambiguated in buildColumnMap() by which occurrence it is.
     * "Age" and "P Due" are deliberately excluded: Age is never imported (DOB is stored,
     * age is computed on the fly — see Student::age()); P Due is documented as unreliable.
     */
    private const HEADER_MAP = [
        'session' => 'session',
        'adm no.' => 'admission_no',
        'student type' => 'student_type',
        'aadhaar no.' => 'aadhaar_no',
        'name' => 'name',
        'mother name' => 'mother_name',
        'father name' => 'father_name',
        'address' => 'address',
        'mobile' => 'mobile',
        'class' => 'class',
        'section' => 'section',
        'roll' => 'roll_no',
        'dob' => 'dob',
        'gender' => 'gender',
        'bld grp' => 'blood_group',
        'adm date' => 'admission_date',
        'stoppage' => 'stoppage',
        'vehicle' => 'vehicle',
        'adm type' => 'admission_type',
        'transport' => 'uses_transport',
        'social category' => 'category',
        'hostel' => 'hostel',
        'minority group' => 'minority_group',
        'bpl beneficiary' => 'bpl_beneficiary',
        'belongs to ews/disadvantaged group?' => 'ews_disadvantaged',
        'cwsn' => 'cwsn',
        'clsl' => 'clsl',
        'name as per aadhaar' => 'name_as_per_aadhaar',
        'child is indian national?' => 'indian_national',
        'indian nationality' => 'indian_national',
        'indian nationality?' => 'indian_national',
        'nationality' => 'indian_national',
        'indian national' => 'indian_national',
        'guardian name (optional)' => 'guardian_name',
        'alternate mobile number (optional)' => 'alternate_mobile',
        'email id (student/parent/guardian) (optional)' => 'email',
        'mother tongue' => 'mother_tongue',
        'pincode' => 'pincode',
        'student state code' => 'student_state_code',
        'whether antyodaya anna yojana (aay) beneficiary?' => 'aay_beneficiary',
        'type of impairments' => 'type_of_impairments',
        'previous academic year schooling status' => 'previous_year_schooling_status',
        'class studies in previous academic year' => 'previous_year_class',
        'admited/ enrolled under rte/ews? (for private unaided only)' => 'rte_ews_admission',
        'is repeater' => 'is_repeater',
        'appeared for exam in previous class' => 'exam_appeared',
        'result for previous exam' => 'exam_result',
        'marks % of previous exam' => 'exam_marks_percent',
        'class attended days (previous year)' => 'attendance_days',
        // Legacy Master sheet used "C%"; live workbooks use "ATTENDENCE %" (their spelling).
        'c%' => 'attendance_percent',
        'attendance_percent' => 'attendance_percent',
        'attendance %' => 'attendance_percent',
        'attendance%' => 'attendance_percent',
        'attendence %' => 'attendance_percent',
        'attendence%' => 'attendance_percent',
        'attendence_percent' => 'attendance_percent',
        'student pen' => 'student_pen',
        // Sheet column renamed Entry Status → UDISE; both headers write entry_status.
        'udise' => 'entry_status',
        'entry status' => 'entry_status',
        'class admitted' => 'class_admitted',
        'tc number' => 'tc_number',
        'tc date' => 'tc_date',
        'last class studied' => 'last_class_studied',
    ];

    /** The client's file abbreviates Nursery as "Nur" — same alias the old Student Import uses. */
    private const CLASS_ALIASES = ['nur' => 'Nursery'];

    /** Normalizes the ACTIVE/INACTIVE column (both the "current" and the per-session snapshot use of it). */
    private const STATUS_MAP = ['active' => 'Active', 'inactive' => 'Inactive'];

    /** Boolean-ish Yes/No columns. Fields not listed here (e.g. Yes/No values that don't parse) are left untouched rather than guessed. */
    private const YES_NO_FIELDS = [
        'bpl_beneficiary', 'ews_disadvantaged', 'cwsn', 'indian_national',
        'aay_beneficiary', 'hostel', 'uses_transport', 'is_repeater',
    ];

    private static function parseYesNo(string $value): ?bool
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return null;
        }

        return match ($value) {
            'yes', 'y', 'true', '1' => true,
            'no', 'n', 'false', '0' => false,
            // Student Master "INDIAN NATIONALity" column variants
            'indian', 'indian nationality', 'indian national', 'india' => true,
            'other', 'foreign', 'non-indian', 'non indian', 'nri' => false,
            default => null,
        };
    }

    /**
     * The real file mixes casing for Gender ("MALE", "Female", etc.) — normalize known
     * values to the students.gender enum's exact casing. An unrecognized value (not a
     * casing variant of Male/Female/Other) is passed through as-is so validation still
     * catches genuinely bad data rather than silently guessing or dropping it.
     */
    private static function normalizeGender(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return match (strtolower($value)) {
            'male' => 'Male',
            'female' => 'Female',
            'other' => 'Other',
            default => $value,
        };
    }

    /**
     * Excel often stores 85% as 0.85. Normalize to a readable percent string (e.g. "85").
     */
    private static function normalizeAttendancePercent(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $raw = trim((string) $value);
        if ($raw === '' || strcasecmp($raw, 'none') === 0) {
            return '';
        }

        $raw = rtrim($raw, '%');
        if (! is_numeric($raw)) {
            return trim((string) $value);
        }

        $num = (float) $raw;
        // Spreadsheet percent cells are typically 0–1 fractions.
        if ($num > 0 && $num <= 1) {
            $num *= 100;
        }

        $formatted = rtrim(rtrim(number_format($num, 2, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    /** Same casing-normalization idea as normalizeGender() — "NEW"/"new" and "OLD"/"old" → "New"/"Old". */
    private static function normalizeAdmissionType(string $value): string
    {
        $value = trim($value);

        return match (strtolower($value)) {
            'new' => 'New',
            'old' => 'Old',
            default => $value,
        };
    }

    /**
     * The source file's SESSION column uses a short "YYYY-YY" shape (e.g. "2026-27"), but this
     * app's academic_sessions.name convention is the full "YYYY-YYYY" shape (e.g. "2026-2027" —
     * see database/seeders/SettingsSeeder.php). Converting keeps firstOrCreate() below matching
     * the school's real, already-existing session rows instead of creating look-alike duplicates
     * that would never line up with whichever one is flagged is_current.
     */
    private static function normalizeSessionName(string $session): string
    {
        $session = trim($session);
        if (preg_match('/^(\d{4})-(\d{2})$/', $session, $m)) {
            return $m[1] . '-' . substr($m[1], 0, 2) . $m[2];
        }

        return $session;
    }

    /** 0-based column index -> spreadsheet-style letter (0 -> A, 26 -> AA), for readable warnings. */
    private static function columnLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }

    /**
     * Maps this sheet's header row to internal field keys — matched purely by normalized
     * (trimmed, lowercased) header NAME, never by column position/index, so an inserted or
     * reordered column can never silently shift every field after it.
     *
     * A duplicate header (two columns that normalize to the same mapped name — e.g. a
     * second, accidental "Adm Type" column) uses the first occurrence and ignores the rest.
     * "status" is the one deliberate exception: it legitimately appears twice in this file
     * with two different meanings (ACTIVE/INACTIVE vs PROMOTED/LEFT/NEW), disambiguated here
     * by occurrence order into 'status' / 'promotion_status'.
     *
     * A blank/whitespace-only header is never silently skipped — it's recorded as a warning
     * (surfaced via ImportExportLog.ignored_columns) so a shifted or corrupted export gets
     * caught instead of quietly losing whatever data sits in that column.
     *
     * @param  array<int, string>  $header
     * @return array{0: array<string, int>, 1: list<string>} [field => column index, warnings]
     */
    private function buildColumnMap(array $header): array
    {
        $map = [];
        $warnings = [];
        $statusOccurrence = 0;

        foreach ($header as $index => $rawName) {
            $name = strtolower(trim(str_replace("\xc2\xa0", ' ', (string) $rawName)));
            $name = preg_replace('/\s+/', ' ', $name) ?? $name;

            if ($name === '') {
                $warnings[] = 'Blank header at column ' . self::columnLetter($index) . ' — any data in that column was not imported.';
                continue;
            }

            if ($name === 'status') {
                $statusOccurrence++;
                $field = $statusOccurrence === 1 ? 'status' : 'promotion_status';
            } elseif (array_key_exists($name, self::HEADER_MAP)) {
                $field = self::HEADER_MAP[$name];
            } else {
                continue;
            }

            if (isset($map[$field])) {
                $warnings[] = 'Duplicate "' . $name . '" column at ' . self::columnLetter($index)
                    . ' — using the first occurrence (column ' . self::columnLetter($map[$field]) . ') and ignoring this one.';

                continue;
            }

            $map[$field] = $index;
        }

        return [$map, $warnings];
    }

    /**
     * Auto-creates an academic_sessions row for a session found in the file if one doesn't
     * already exist by name — mirroring the Class/Section/Vehicle auto-create pattern. Never
     * sets is_current (that stays a deliberate, manual Settings action — see
     * AcademicSessionController::makeCurrent()); a freshly auto-created session defaults to
     * 'closed' since Master Import data is historical by nature.
     */
    private function resolveAcademicSession(string $rawSession): bool
    {
        $name = self::normalizeSessionName($rawSession);
        $startYear = (int) substr($rawSession, 0, 4);

        $session = AcademicSession::firstOrCreate(['name' => $name], [
            'start_date' => "{$startYear}-04-01",
            'end_date' => ($startYear + 1) . '-03-31',
            'status' => 'closed',
            'is_current' => false,
        ]);

        return $session->wasRecentlyCreated;
    }

    /**
     * Runs the full Student Master pipeline against an already-loaded [header, rows] sheet:
     * parses rows by header NAME (never position), sorts each student's rows oldest-session
     * first (so `students` ends up reflecting the latest session regardless of file order —
     * usort is stable as of PHP 8, so same-session rows keep their original file order),
     * upserts students + udise/additional details + session history for every row.
     * Vehicle numbers are always auto-created as master data (any session). Student
     * transport assignment (and new Stoppage→Route creation) still only runs for rows
     * whose SESSION matches the school's current academic session — older sessions are
     * skipped for assignments so historical stoppages do not overwrite today's roster.
     *
     * @param  array<int, string>  $header
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function import(array $header, array $rows, string $filename): array
    {
        [$columnMap, $headerWarnings] = $this->buildColumnMap($header);

        $parsedRows = [];
        $rowNumber = 1;
        foreach ($rows as $line) {
            $rowNumber++;

            $row = ['row_number' => $rowNumber];
            foreach ($columnMap as $field => $index) {
                $raw = $line[$index] ?? '';
                // Keep numeric Excel cells (including 0 / percentage fractions) instead of losing them.
                if (is_int($raw) || is_float($raw)) {
                    $value = (string) $raw;
                } else {
                    $value = trim((string) $raw);
                }
                $row[$field] = strtolower($value) === 'none' ? '' : $value;
            }

            // Skip silently, don't count — covers the known trailing fully-blank row and
            // any other row with no Adm No.
            if (($row['admission_no'] ?? '') === '') {
                continue;
            }

            $parsedRows[] = $row;
        }

        usort($parsedRows, fn ($a, $b) => strcmp($a['session'] ?? '', $b['session'] ?? ''));

        $counters = [
            'classes_created' => 0,
            'sections_created' => 0,
            'academic_sessions_created' => 0,
            'vehicles_created' => 0,
            'branches_created' => 0,
            'routes_created' => 0,
            'transports_assigned' => 0,
            'transports_cleared' => 0,
            'transports_skipped_other_session' => 0,
        ];

        $defaultBranch = DefaultSchoolBranchService::ensure();
        if ($defaultBranch->wasRecentlyCreated) {
            $counters['branches_created'] = 1;
        }

        // Resolve every unique session ONCE up front (file-level) — rather than once per row —
        // 1,558 rows might share only 5 distinct sessions.
        $uniqueSessions = array_unique(array_filter(array_column($parsedRows, 'session')));
        foreach ($uniqueSessions as $rawSession) {
            if ($this->resolveAcademicSession($rawSession)) {
                $counters['academic_sessions_created']++;
            }
        }

        $currentSession = AcademicSession::where('is_current', true)->first();

        $totalRows = count($parsedRows);
        $successCount = 0;
        $rowOutcomes = [];

        // Warm lookup caches once — Master files are multi-thousand rows with heavy reuse.
        $sectionCache = [];
        foreach (Section::query()->get(['id', 'school_class_id', 'name']) as $section) {
            $sectionCache[$section->school_class_id . '|' . $section->name] = $section->id;
        }
        $cache = [
            'students' => Student::query()->get()->keyBy('admission_no'),
            'classes' => SchoolClass::query()->pluck('id', 'name')->all(),
            'sections' => $sectionCache,
            'parents' => ParentGuardian::query()->pluck('id', 'name')->all(),
            'vehicles' => Vehicle::query()->get(['id', 'vehicle_no'])
                ->mapWithKeys(fn (Vehicle $v) => [mb_strtoupper(trim($v->vehicle_no)) => $v->id])
                ->all(),
            'routes' => TransportRoute::query()->get(['id', 'name', 'vehicle_id'])->keyBy(fn ($r) => mb_strtolower($r->name))->all(),
            'default_branch_id' => $defaultBranch->id,
        ];

        foreach ($parsedRows as $row) {
            $outcome = $this->importRow($row, $cache, $currentSession);
            $rowOutcomes[] = ['row_number' => $row['row_number']] + $outcome;

            if ($outcome['status'] === 'Success') {
                $successCount++;
                if ($outcome['summary']['auto_created']['class']) {
                    $counters['classes_created']++;
                }
                if ($outcome['summary']['auto_created']['section']) {
                    $counters['sections_created']++;
                }
                if ($outcome['summary']['auto_created']['vehicle']) {
                    $counters['vehicles_created']++;
                }
                if ($outcome['summary']['auto_created']['route'] ?? false) {
                    $counters['routes_created']++;
                }
                $transportAction = $outcome['summary']['transport']['action'] ?? null;
                if ($transportAction === 'assigned' || $transportAction === 'updated') {
                    $counters['transports_assigned']++;
                }
                if ($transportAction === 'cleared') {
                    $counters['transports_cleared']++;
                }
                if ($transportAction === 'skipped' && ($outcome['summary']['transport']['reason'] ?? null) === 'other_session') {
                    $counters['transports_skipped_other_session']++;
                }
            }
        }

        $log = ImportExportLog::create([
            'direction' => 'Import',
            'entity' => 'student-master',
            'filename' => $filename,
            'total_rows' => $totalRows,
            'success_count' => $successCount,
            'failed_count' => $totalRows - $successCount,
            'ignored_columns' => $headerWarnings,
            'performed_by_id' => Auth::guard('erp')->id(),
            ...$counters,
        ]);

        $now = now()->toDateTimeString();
        $rowLogRows = [];
        $failedRowRows = [];
        foreach ($rowOutcomes as $outcome) {
            // Persist failures fully; success rows get a compact log (no heavy summary JSON).
            $rowLogRows[] = [
                'import_export_log_id' => $log->id,
                'row_number' => $outcome['row_number'],
                'status' => $outcome['status'],
                'identifier' => $outcome['identifier'],
                'summary' => $outcome['status'] === 'Success'
                    ? json_encode([
                        'student' => $outcome['summary']['student'] ?? null,
                        'auto_created' => $outcome['summary']['auto_created'] ?? null,
                    ], JSON_THROW_ON_ERROR)
                    : null,
                'error_message' => $outcome['error_message'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($outcome['status'] === 'Failed') {
                $failedRowRows[] = [
                    'import_export_log_id' => $log->id,
                    'row_number' => $outcome['row_number'],
                    'row_data' => json_encode($outcome['row_data'] ?? [], JSON_THROW_ON_ERROR),
                    'error_message' => mb_substr((string) $outcome['error_message'], 0, 255),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rowLogRows, 300) as $chunk) {
            DB::table('import_row_logs')->insert($chunk);
        }
        foreach (array_chunk($failedRowRows, 300) as $chunk) {
            DB::table('import_failed_rows')->insert($chunk);
        }

        return [
            'log' => $log->fresh(),
            'total_rows' => $totalRows,
            'success_count' => $successCount,
            'failed_count' => $totalRows - $successCount,
            'counters' => $counters,
            'header_warnings' => $headerWarnings,
            'transport_stats' => [
                'routes_created' => $counters['routes_created'],
                'transports_assigned' => $counters['transports_assigned'],
                'transports_cleared' => $counters['transports_cleared'],
                'transports_skipped_other_session' => $counters['transports_skipped_other_session'],
            ],
            'failed_rows' => $log->failedRows()->limit(200)->get(),
        ];
    }

    /**
     * Upsert one student (+ UDISE details + this session's history) from a parsed row.
     * Matches by admission_no; blank cells never overwrite existing data (same
     * non-destructive-merge philosophy as the old, retired StudentImportController).
     *
     * Stoppage/Vehicle master data and student transport assignment only run when this
     * row's SESSION matches the school's current academic session.
     *
     * @param  array<string, mixed>  $cache
     */
    private function importRow(array $row, array &$cache, ?AcademicSession $currentSession = null): array
    {
        $identifier = $row['admission_no'] ?? null;

        if (($row['admission_no'] ?? '') === '' || ($row['name'] ?? '') === '') {
            return $this->failedRow($row, 'Adm No. and Name are required.');
        }

        $existingStudent = $cache['students'][$row['admission_no']] ?? null;

        $classValue = trim($row['class'] ?? '');
        $classMissingOrZero = $classValue === '' || $classValue === '0';

        $schoolClassId = null;
        $schoolClassCreated = false;
        $sectionId = null;
        $sectionCreated = false;

        if (! $classMissingOrZero) {
            $className = self::CLASS_ALIASES[strtolower($classValue)] ?? $classValue;
            if (! isset($cache['classes'][$className])) {
                $schoolClass = SchoolClass::create(['name' => $className, 'capacity' => 40]);
                $cache['classes'][$className] = $schoolClass->id;
                $schoolClassCreated = true;
            }
            $schoolClassId = $cache['classes'][$className];

            if (! empty($row['section'])) {
                $sectionKey = $schoolClassId . '|' . $row['section'];
                if (! isset($cache['sections'][$sectionKey])) {
                    $section = Section::create([
                        'school_class_id' => $schoolClassId,
                        'name' => $row['section'],
                        'capacity' => 40,
                    ]);
                    $cache['sections'][$sectionKey] = $section->id;
                    $sectionCreated = true;
                }
                $sectionId = $cache['sections'][$sectionKey];
            }
        } elseif (! $existingStudent) {
            // Class = 0/blank only ever appears on INACTIVE (exit) rows in the source data —
            // a brand-new student's very first row being an exit row with no class on record
            // isn't something we can resolve, so this fails rather than guessing a class.
            return $this->failedRow($row, "Cannot create a new student: Class is missing or \"0\" for admission no {$row['admission_no']}.");
        }

        $data = array_filter([
            'admission_no' => $row['admission_no'],
            'name' => $row['name'],
            'roll_no' => ($row['roll_no'] ?? '') !== '' ? (int) $row['roll_no'] : null,
            'school_class_id' => $schoolClassId,
            'section_id' => $sectionId,
            'gender' => self::normalizeGender($row['gender'] ?? ''),
            'dob' => ExcelDateParser::parse($row['dob'] ?? null),
            'blood_group' => ($row['blood_group'] ?? '') ?: null,
            'category' => ($row['category'] ?? '') ?: null,
            'aadhar_no' => ($row['aadhaar_no'] ?? '') ?: null,
            'mobile' => ($row['mobile'] ?? '') ?: null,
            'email' => ($row['email'] ?? '') ?: null,
            'address' => ($row['address'] ?? '') ?: null,
            'pincode' => ($row['pincode'] ?? '') ?: null,
            'status' => ! empty($row['status']) ? (self::STATUS_MAP[strtolower($row['status'])] ?? null) : null,
            'admission_date' => ExcelDateParser::parse($row['admission_date'] ?? null),
            'branch_id' => $cache['default_branch_id'],
        ], fn ($v) => $v !== null);

        // Lightweight validation (avoids exists: DB hits — IDs come from our cache/creates).
        if (($data['gender'] ?? null) !== null && ! in_array($data['gender'], ['Male', 'Female', 'Other'], true)) {
            return $this->failedRow($row, 'The selected gender is invalid.');
        }
        if (($data['status'] ?? null) !== null && ! in_array($data['status'], ['Active', 'Inactive', 'Transferred'], true)) {
            return $this->failedRow($row, 'The selected status is invalid.');
        }
        if (($data['email'] ?? null) !== null && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->failedRow($row, 'The email field must be a valid email address.');
        }

        [$fatherId, $fatherCreated] = $this->resolveParentByNameCached($row['father_name'] ?? '', $cache);
        [$motherId, $motherCreated] = $this->resolveParentByNameCached($row['mother_name'] ?? '', $cache);
        if ($fatherId) {
            $data['father_id'] = $fatherId;
        }
        if ($motherId) {
            $data['mother_id'] = $motherId;
        }

        $vehicleCreated = false;
        // Vehicle master data is created for every session row — not only the current one —
        // so Transport → Vehicles fills in even when Student Master has stacked history years.
        if (($row['vehicle'] ?? '') !== '') {
            [$vehicleId, $vehicleCreated] = $this->ensureVehicle(trim((string) $row['vehicle']), $cache);
            // If TRANSPORT sheet already created the stoppage route, attach this bus number.
            $stoppage = trim((string) ($row['stoppage'] ?? ''));
            if ($vehicleId && $stoppage !== '') {
                $this->attachVehicleToRouteNamed($stoppage, $vehicleId, $cache);
            }
        }

        $applyTransport = $this->rowMatchesCurrentSession($row, $currentSession);
        // Fee Start Month defaults to admission month when not already set.
        if (! empty($data['admission_date'])) {
            $admissionMonth = is_string($data['admission_date'])
                ? substr($data['admission_date'], 0, 7)
                : $data['admission_date']->format('Y-m');
            if (! $existingStudent || empty($existingStudent->fee_start_month)) {
                $data['fee_start_month'] = $admissionMonth;
            }
        }

        if ($existingStudent) {
            $existingStudent->update($data);
            $student = $existingStudent->fresh();
            $cache['students'][$student->admission_no] = $student;
            $studentAction = 'updated';
        } else {
            $student = Student::create($data);
            $cache['students'][$student->admission_no] = $student;
            $studentAction = 'created';
        }

        $udiseOutcome = $this->upsertUdiseDetail($student, $row);
        $additionalDetailOutcome = $this->upsertAdditionalDetail($student, $row);
        $sessionOutcome = $this->upsertSessionHistory($student, $row);
        $transportOutcome = $applyTransport
            ? $this->assignTransportFromStoppage($student, $row, $cache)
            : [
                'action' => 'skipped',
                'reason' => 'other_session',
                'route_created' => false,
                'vehicle_created' => false,
            ];

        $summary = [
            'student' => ['action' => $studentAction, 'id' => $student->id],
            'father' => $fatherId ? ['action' => $fatherCreated ? 'created' : 'matched', 'id' => $fatherId] : null,
            'mother' => $motherId ? ['action' => $motherCreated ? 'created' : 'matched', 'id' => $motherId] : null,
            'udise_detail' => $udiseOutcome,
            'additional_detail' => $additionalDetailOutcome,
            'session_history' => $sessionOutcome,
            'transport' => $transportOutcome,
            'auto_created' => [
                'class' => $schoolClassCreated,
                'section' => $sectionCreated,
                'vehicle' => $vehicleCreated || ($transportOutcome['vehicle_created'] ?? false),
                'route' => $transportOutcome['route_created'] ?? false,
            ],
        ];

        return ['status' => 'Success', 'identifier' => $identifier, 'summary' => $summary, 'error_message' => null, 'row_data' => null];
    }

    /**
     * True when this sheet row's SESSION is the school's current academic session
     * (supports both "YYYY-YYYY" and short "YYYY-YY" shapes).
     */
    private function rowMatchesCurrentSession(array $row, ?AcademicSession $currentSession): bool
    {
        if (! $currentSession) {
            return false;
        }

        $raw = trim((string) ($row['session'] ?? ''));
        if ($raw === '') {
            return false;
        }

        $normalized = self::normalizeSessionName($raw);
        $aliases = AcademicSession::nameAliases($currentSession->name);

        return in_array($normalized, $aliases, true) || in_array($raw, $aliases, true);
    }

    /**
     * Create/match a vehicle by bus number. Keys are uppercased so "DL-01" and "dl-01" share one row.
     *
     * @param  array<string, mixed>  $cache
     * @return array{0: int|null, 1: bool} [vehicle_id, was_created]
     */
    private function ensureVehicle(string $vehicleNo, array &$cache): array
    {
        $vehicleNo = trim($vehicleNo);
        if ($vehicleNo === '') {
            return [null, false];
        }

        $key = mb_strtoupper($vehicleNo);
        if (isset($cache['vehicles'][$key])) {
            return [(int) $cache['vehicles'][$key], false];
        }

        $vehicle = Vehicle::create([
            'vehicle_no' => $vehicleNo,
            'type' => 'Bus',
            'capacity' => 40,
            'status' => 'Active',
        ]);
        $cache['vehicles'][$key] = $vehicle->id;

        return [$vehicle->id, true];
    }

    /**
     * Attach a vehicle to an existing route named after a stoppage (e.g. created by TRANSPORT sheet).
     *
     * @param  array<string, mixed>  $cache
     */
    private function attachVehicleToRouteNamed(string $stoppage, int $vehicleId, array &$cache): void
    {
        $routeKey = mb_strtolower(trim($stoppage));
        if ($routeKey === '' || ! isset($cache['routes'][$routeKey])) {
            return;
        }

        $route = $cache['routes'][$routeKey];
        if (! empty($route->vehicle_id)) {
            return;
        }

        $route->vehicle_id = $vehicleId;
        $route->save();
        $cache['routes'][$routeKey] = $route;
    }

    /**
     * From Excel Stoppage + Vehicle (current session rows only):
     * - Stoppage/Vehicle "None"/blank → student is not on school transport (clear assignment).
     * - Otherwise create/match a Route named after Stoppage, assign Vehicle, ensure a stop
     *   (fare ₹0), and upsert StudentTransport with fee start = admission month.
     *
     * @param  array<string, mixed>  $cache
     * @return array{action: string, route_created?: bool, vehicle_created?: bool, reason?: string|null}
     */
    private function assignTransportFromStoppage(Student $student, array $row, array &$cache): array
    {
        $stoppage = trim((string) ($row['stoppage'] ?? ''));
        $vehicleNo = trim((string) ($row['vehicle'] ?? ''));

        // "None" is already blanked in import(); blank stoppage means not using transport.
        if ($stoppage === '') {
            $deleted = StudentTransport::where('student_id', $student->id)->delete();
            StudentUdiseDetail::where('student_id', $student->id)->update(['uses_transport' => false]);

            return [
                'action' => $deleted > 0 ? 'cleared' : 'skipped',
                'reason' => 'no_stoppage',
                'route_created' => false,
                'vehicle_created' => false,
            ];
        }

        $vehicleCreated = false;
        $vehicleId = null;
        if ($vehicleNo !== '') {
            [$vehicleId, $vehicleCreated] = $this->ensureVehicle($vehicleNo, $cache);
        }

        $routeKey = mb_strtolower($stoppage);
        $routeCreated = false;

        if (! isset($cache['routes'][$routeKey])) {
            $route = TransportRoute::create([
                'branch_id' => $cache['default_branch_id'],
                'name' => $stoppage,
                'start_point' => $stoppage,
                'end_point' => $stoppage,
                'vehicle_id' => $vehicleId,
                'status' => 'Active',
            ]);
            $cache['routes'][$routeKey] = $route;
            $routeCreated = true;
        } else {
            $route = $cache['routes'][$routeKey];
            if ($vehicleId && empty($route->vehicle_id)) {
                $route->vehicle_id = $vehicleId;
                $route->save();
                $cache['routes'][$routeKey] = $route;
            }
        }

        $stop = RouteStop::query()
            ->where('route_id', $route->id)
            ->whereRaw('LOWER(stop_name) = ?', [$routeKey])
            ->first();

        if (! $stop) {
            $nextSeq = (int) RouteStop::query()->where('route_id', $route->id)->max('sequence_no') + 1;
            $stop = RouteStop::create([
                'route_id' => $route->id,
                'stop_name' => $stoppage,
                'sequence_no' => max(1, $nextSeq),
                'fare' => 0,
            ]);
        }

        $startDate = $student->admission_date?->toDateString() ?? now()->toDateString();
        $feeStartMonth = $student->fee_start_month
            ?: ($student->admission_date?->format('Y-m') ?? substr($startDate, 0, 7));

        $assignment = StudentTransport::updateOrCreate(
            ['student_id' => $student->id],
            [
                'route_id' => $route->id,
                'route_stop_id' => $stop->id,
                'start_date' => $startDate,
                'fee_start_month' => $feeStartMonth,
                'status' => 'Active',
            ]
        );

        StudentUdiseDetail::updateOrCreate(
            ['student_id' => $student->id],
            ['uses_transport' => true]
        );

        return [
            'action' => $assignment->wasRecentlyCreated ? 'assigned' : 'updated',
            'route_created' => $routeCreated,
            'vehicle_created' => $vehicleCreated,
            'route_id' => $route->id,
            'route_stop_id' => $stop->id,
        ];
    }

    /** Builds the outcome array for a row that failed validation or matching, before any writes happened. */
    private function failedRow(array $row, string $message): array
    {
        return ['status' => 'Failed', 'identifier' => $row['admission_no'] ?? null, 'summary' => null, 'error_message' => $message, 'row_data' => $row];
    }

    /**
     * Matches/creates a ParentGuardian purely by exact trimmed name — the only key File A
     * gives us for either parent. Unlike phone-based dedup, this can merge two unrelated
     * people who share a name; that's an accepted tradeoff, not a bug to guard against here.
     *
     * @param  array<string, mixed>  $cache
     * @return array{0: int|null, 1: bool}
     */
    private function resolveParentByNameCached(string $name, array &$cache): array
    {
        $name = trim($name);
        if ($name === '') {
            return [null, false];
        }

        if (! isset($cache['parents'][$name])) {
            $parent = ParentGuardian::create(['name' => $name]);
            $cache['parents'][$name] = $parent->id;

            return [$parent->id, true];
        }

        return [$cache['parents'][$name], false];
    }

    /**
     * Upserts the student_udise_details row. Only fields this row actually provides are
     * written — a blank cell (or the literal text "None", already normalized to blank in
     * import()) never overwrites what's already there.
     */
    private function upsertUdiseDetail(Student $student, array $row): array
    {
        $textFields = [
            'student_type', 'minority_group', 'type_of_impairments', 'mother_tongue', 'rte_ews_admission',
            'guardian_name', 'alternate_mobile', 'stoppage', 'vehicle', 'admission_type', 'clsl',
            'name_as_per_aadhaar', 'father_name', 'mother_name', 'student_state_code', 'entry_status', 'student_pen',
        ];

        $data = [];
        foreach ($textFields as $field) {
            if (($row[$field] ?? '') !== '') {
                $data[$field] = $row[$field];
            }
        }

        // Sheet casing varies ("NEW", "New", "new") — normalize to the same "New"/"Old" the
        // People > Add Student form writes, so the two sources agree on one value the app can
        // actually count on (e.g. the Students page's New-admission stat).
        if (isset($data['admission_type'])) {
            $data['admission_type'] = self::normalizeAdmissionType($data['admission_type']);
        }

        // UDISE / Entry Status column: non-blank => In UDISE flag; blank clears it when the column is present.
        if (array_key_exists('entry_status', $row)) {
            $data['is_in_udise'] = trim((string) $row['entry_status']) !== '';
        }

        foreach (self::YES_NO_FIELDS as $field) {
            $parsed = self::parseYesNo($row[$field] ?? '');
            if ($parsed !== null) {
                $data[$field] = $parsed;
            }
        }

        if (empty($data)) {
            return ['action' => 'skipped'];
        }

        $detail = StudentUdiseDetail::firstOrNew(['student_id' => $student->id]);
        $detail->fill($data);
        $detail->save();

        return ['action' => 'saved'];
    }

    /**
     * Upserts the student_additional_details row's TC-history fields (Class Admitted, TC
     * Number, TC Date, Last Class Studied). Same non-destructive merge as upsertUdiseDetail() —
     * a blank cell never overwrites what's already there.
     */
    private function upsertAdditionalDetail(Student $student, array $row): array
    {
        $data = [];
        foreach (['class_admitted', 'tc_number', 'last_class_studied'] as $field) {
            if (($row[$field] ?? '') !== '') {
                $data[$field] = $row[$field];
            }
        }

        $tcDate = ExcelDateParser::parse($row['tc_date'] ?? null);
        if ($tcDate !== null) {
            $data['tc_date'] = $tcDate;
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
     * Upserts this row's session snapshot into student_session_history, keyed by
     * (student_id, session). Class/Section/Roll are stored raw (not FK-linked) — this is a
     * historical record, distinct from the student's current relationship on `students`.
     */
    private function upsertSessionHistory(Student $student, array $row): array
    {
        if (($row['session'] ?? '') === '') {
            return ['action' => 'skipped', 'reason' => 'no_session'];
        }

        $data = [];
        foreach ([
            'previous_year_schooling_status', 'previous_year_class', 'exam_appeared', 'exam_result',
            'exam_marks_percent', 'attendance_days', 'attendance_percent', 'promotion_status',
        ] as $field) {
            if (($row[$field] ?? '') === '') {
                continue;
            }
            $value = $row[$field];
            if ($field === 'attendance_percent') {
                $value = self::normalizeAttendancePercent($value);
                if ($value === '') {
                    continue;
                }
            }
            $data[$field] = $value;
        }

        if (($row['class'] ?? '') !== '') {
            $data['class_name'] = $row['class'];
        }
        if (($row['section'] ?? '') !== '') {
            $data['section_name'] = $row['section'];
        }
        if (($row['roll_no'] ?? '') !== '') {
            $data['roll_no'] = (int) $row['roll_no'];
        }
        if (! empty($row['status'])) {
            $data['status'] = self::STATUS_MAP[strtolower($row['status'])] ?? $row['status'];
        }

        if (empty($data)) {
            return ['action' => 'skipped', 'reason' => 'no_data'];
        }

        $history = StudentSessionHistory::firstOrNew(['student_id' => $student->id, 'session' => $row['session']]);
        $wasNew = ! $history->exists;
        $history->fill($data);
        $history->save();

        return ['action' => $wasNew ? 'created' : 'updated'];
    }
}
