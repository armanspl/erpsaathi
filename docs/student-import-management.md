# Student Import — Excel Field Mapping & Business Rules

This document covers all three student importers this app has had — the two currently live ones (§A, §B) and the original, now-retired one (§1–§13, kept below unchanged) — in full depth, separately from `people.md`, because of how much detail the real column-mapping and business rules carry.

See [README.md](README.md) for the cross-cutting Import & Export architecture (the shared `SpreadsheetImportReader` service, `ImportExportLog`/`ImportRowLog`/`ImportFailedRow` tables).

**Current state**: `StudentMasterImportController` (§A) is the primary, only-routed student importer, matching the school's real UDISE-format "Student Master Record" source file. `StudentPenImportController` (§B) is a second, narrower importer that runs after it and fills in exactly one field (`student_pen`) from a different UDISE-format file. The original `StudentImportController` (§1–§13 below) is **retired** — unrouted, but kept in the codebase because it still owns logic neither new importer replicates: parent (father/mother) resolution by phone, Transport/Hostel auto-assignment, and the health/document-received fields on `student_additional_details`.

---

## A. Student Master Import (`StudentMasterImportController`) — current, primary importer

### A.1 Overview

- **Page**: Import & Export → Student Import (`/import-export?type=student-import`, shared `ImportExport.vue` component, Import tab, `activeEntity === 'student'` — same sidebar entry as before, now backed by different logic).
- **Backend**: `POST /erp/api/import-export/import/student-master` → `StudentMasterImportController::store()`.
- **Source file**: "Student Master Record" (`GAS_STUD_MASTER_REC_2026.xlsx`, sheet `GAS STUD MASTER REC`, header on row 1). Every academic session a student was ever enrolled in is stacked as its own row (same Adm No. repeated once per session) — a real file has ~1,558 rows / ~581 unique students spanning 5 sessions.
- **Permission**: `erp.permission:import.manage`.

### A.2 Header map (`StudentMasterImportController::HEADER_MAP`)

One entry per real column, keyed by the lowercased/trimmed header text (headers with trailing spaces in the source file, e.g. `MOBILE ` and `DOB `, are trimmed the same way `SpreadsheetImportReader` trims everything else):

| Source header | Internal key | Saved to |
|---|---|---|
| SESSION | `session` | `student_session_history.session` (+ drives processing order) |
| Adm No. | `admission_no` | `students.admission_no` (match key) |
| Student Type | `student_type` | `student_udise_details.student_type` |
| AADHAAR No. | `aadhaar_no` | `students.aadhar_no` |
| Name | `name` | `students.name` |
| Mother Name / Father Name | `mother_name` / `father_name` | **Both** `student_udise_details.mother_name`/`father_name` (raw text audit trail) **and** resolved to real `ParentGuardian` records → `students.father_id`/`mother_id` (see §A.6) |
| ADDRESS | `address` | `students.address` |
| MOBILE | `mobile` | `students.mobile` |
| Class | `class` | resolved to `students.school_class_id` (see §A.4) |
| Age | *(excluded — never imported)* | — (DOB is stored; age is computed on the fly via `Student::age()`) |
| Section | `section` | resolved to `students.section_id` |
| ROLL | `roll_no` | `students.roll_no` + `student_session_history.roll_no` |
| DOB | `dob` | `students.dob` (parsed via `ExcelDateParser::parse()`) |
| Gender | `gender` | `students.gender` |
| Bld Grp | `blood_group` | `students.blood_group` |
| ADM DATE | `admission_date` | `students.admission_date` (parsed) |
| Stoppage / Vehicle | `stoppage` / `vehicle` | `student_udise_details.stoppage` (raw text only, never auto-created — see §A.7) / `vehicle` (raw text **and** auto-creates a real `Vehicle` record — see §A.7) |
| Adm Type | `admission_type` | `student_udise_details.admission_type` |
| Transport | `uses_transport` | `student_udise_details.uses_transport` (bool) — a flag only, not a real `StudentTransport` assignment |
| Social Category | `category` | `students.category` (same generic field the retired importer used for its own "Category" column) |
| Hostel | `hostel` | `student_udise_details.hostel` (bool) — a flag only, not a real `HostelAllocation` |
| Minority Group | `minority_group` | `student_udise_details.minority_group` |
| P Due | *(excluded — never imported)* | documented as always 0 / unreliable |
| BPL beneficiary | `bpl_beneficiary` | `student_udise_details.bpl_beneficiary` (bool) |
| BELONGS TO EWS/DISADVANTAGED GROUP? | `ews_disadvantaged` | `student_udise_details.ews_disadvantaged` (bool) |
| CWSN | `cwsn` | `student_udise_details.cwsn` (bool) |
| clsl | `clsl` | `student_udise_details.clsl` — stored raw, not interpreted (looks like an internal UDISE class-level code) |
| Name As per AADHAAR | `name_as_per_aadhaar` | `student_udise_details.name_as_per_aadhaar` |
| CHILD IS INDIAN NATIONAL? | `indian_national` | `student_udise_details.indian_national` (bool) |
| Guardian Name (Optional) | `guardian_name` | `student_udise_details.guardian_name` |
| ALTERNATE MOBILE NUMBER (Optional) | `alternate_mobile` | `student_udise_details.alternate_mobile` |
| EMAIL ID (STUDENT/PARENT/GUARDIAN) (Optional) | `email` | `students.email` |
| MOTHER TONGUE | `mother_tongue` | `student_udise_details.mother_tongue` |
| PINCODE | `pincode` | `students.pincode` |
| Student State Code | `student_state_code` | `student_udise_details.student_state_code` — reserved, always blank today |
| Whether Antyodaya Anna Yojana (AAY) beneficiary? | `aay_beneficiary` | `student_udise_details.aay_beneficiary` (bool) |
| Type of Impairments | `type_of_impairments` | `student_udise_details.type_of_impairments` |
| PREVIOUS ACADEMIC YEAR SCHOOLING STATUS | `previous_year_schooling_status` | `student_session_history.previous_year_schooling_status` |
| CLASS STUDIES IN PREVIOUS ACADEMIC YEAR | `previous_year_class` | `student_session_history.previous_year_class` |
| ADMITED/ ENROLLED UNDER RTE/EWS? (for private unaided only) | `rte_ews_admission` | `student_udise_details.rte_ews_admission` |
| Is Repeater | `is_repeater` | `student_udise_details.is_repeater` (bool) — reserved, always blank today |
| APPEARED FOR EXAM IN PREVIOUS CLASS | `exam_appeared` | `student_session_history.exam_appeared` |
| RESULT FOR PREVIOUS EXAM | `exam_result` | `student_session_history.exam_result` |
| MARKS % OF PREVIOUS EXAM | `exam_marks_percent` | `student_session_history.exam_marks_percent` |
| CLASS ATTENDED DAYS (Previous Year) | `attendance_days` | `student_session_history.attendance_days` |
| C% | `attendance_percent` | `student_session_history.attendance_percent` |
| Status | `status` | `students.status` (normalized ACTIVE/INACTIVE → Active/Inactive) **and** `student_session_history.status` (that session's snapshot) |
| Student PEN | `student_pen` | `student_udise_details.student_pen` — reserved here; Student PEN Import (§B) is what actually fills this in |
| STATUS | `promotion_status` | `student_session_history.promotion_status` (raw PROMOTED/LEFT/NEW) |
| Entry Status | `entry_status` | `student_udise_details.entry_status` — reserved, always blank today |

**The `Status`/`STATUS` collision**: the source file has two genuinely different columns that both render as `"status"` once lowercased and trimmed (`Status` = ACTIVE/INACTIVE, `STATUS` = PROMOTED/LEFT/NEW). Since a header-text-keyed map can't distinguish them, `store()` handles this one pair positionally instead: the *first* column in the header row that lowercases to `"status"` becomes `status`, the *second* becomes `promotion_status`. Every other header in this file is unique after lowercasing.

**Not imported at all**: `Age` (per spec — DOB is stored, age is computed live) and `P Due` (documented as always 0 / not trustworthy). Both are simply absent from `HEADER_MAP`, not mapped-then-discarded.

**Blank/"None" normalization**: the source file uses the literal text `None` (not just an empty cell) to mean "no data" in several columns (Stoppage, Type of Impairments, C%, etc.). `store()` normalizes any cell whose trimmed value lowercases to `none` to an empty string before it ever reaches `HEADER_MAP`/`importRow()` — so `"None"` behaves identically to a genuinely blank cell everywhere, not just in the columns it was observed in.

### A.3 Row selection & session ordering

- A row is skipped **silently and not counted** purely when `Adm No.` is blank after trim — this single check covers the file's one known fully-blank trailing row and any other Adm-No-less row.
- All kept rows are then **sorted by `SESSION` ascending** (`usort` + `strcmp`, safe because every session string shares the `"YYYY-YY"` shape, so lexical order is chronological order) before any row is processed. `usort` is stable on PHP 8+, so same-session rows keep their original file order. This is why the same Adm No. appearing in 2022-23, 2023-24, ..., 2026-27 always ends up with the **2026-27** row's data as the student's current state in `students`, regardless of which order those rows happened to appear in the file.
- **Academic Session auto-create**: before any row is processed, `store()` collects every distinct `SESSION` value across all parsed rows and calls `resolveAcademicSession()` once per unique value (5 calls for a typical file, not 1,558) — `AcademicSession::firstOrCreate(['name' => ...], [...])`, tracked via the run's `academic_sessions_created` counter. The file's short `"YYYY-YY"` shape (e.g. `"2026-27"`) is converted to this app's `"YYYY-YYYY"` naming convention (e.g. `"2026-2027"`, matching `database/seeders/SettingsSeeder.php`) via `normalizeSessionName()` **before** the lookup — without this conversion, the importer would create look-alike duplicate rows that never match the school's real, already-existing sessions (including whichever one is flagged `is_current`). A newly auto-created session always gets `status: 'closed'`, `is_current: false` — **`is_current` is never touched by this importer**, on either a new or already-existing session row; that flag is a deliberate, manual action via Settings → Academic Sessions (`AcademicSessionController::makeCurrent()`).

### A.4 Class = 0/blank — a quirk, not a real class

The source file uses `Class = 0` to mean "class wasn't tracked once this student left" — confirmed in the real file: every single `Class = 0` row has `Status = INACTIVE` (128/128), none are `ACTIVE`. `StudentMasterImportController` treats `Class` trimmed to `''` or `'0'` as "missing":
- **Existing student**: class/section resolution is skipped entirely for that row — `school_class_id`/`section_id` are left out of the update data, so whatever the student's last real class was stays untouched.
- **Brand-new student** (no existing row for this Adm No.): the row **fails** with `"Cannot create a new student: Class is missing or \"0\" for admission no {admission_no}."` — `students.school_class_id` remains a required (`NOT NULL`) column; a null-class student is not created. In practice this case is expected to be rare-to-nonexistent, since a Class-0 row is an exit/inactive row that should logically follow an earlier session where the same student had a real class.
- Otherwise (a real, non-zero Class value): resolved the same way the retired importer resolves Class — `SchoolClass::firstOrCreate(['name' => $className], ['capacity' => 40])` (with the same `nur → Nursery` alias), then `Section::firstOrCreate()` scoped to that class, both auto-creating if unmatched and tracked via the run's `classes_created`/`sections_created` counters.

### A.5 Non-destructive merge (same philosophy as the retired importer)

A blank cell (or the literal text `"None"`, already normalized to blank — see §A.2) on an update row never overwrites existing data. Every field going into `students`, `student_udise_details`, and `student_session_history` is only included in that row's write if the cell actually provided a value; `array_filter(..., fn ($v) => $v !== null)` for `students`, and an explicit non-blank check per field for the two UDISE tables. Boolean UDISE fields (Yes/No columns) are parsed via a small `parseYesNo()` helper that returns `true`/`false`/`null` — **not** the `?: null` idiom, which would incorrectly collapse an explicit `false` ("No") to "not provided."

`admission_no` and `name` are required on every row, same as the retired importer.

### A.6 Father/Mother Name — linked to real `ParentGuardian` records (reversed decision)

**Reversed from this importer's original design.** Father Name/Mother Name are now resolved to real `ParentGuardian` records — `ParentGuardian::firstOrCreate(['name' => trim($name)])`, matched/created independently per parent — and `students.father_id`/`mother_id` are set from the result, inside the same `DB::transaction()` as the student write. This runs whenever the row's Father/Mother Name is non-blank; a blank cell leaves the existing `father_id`/`mother_id` untouched on an update (same non-destructive-merge rule as every other field).

**Known, accepted tradeoff**: File A has no phone number for either parent, so unlike the retired importer's phone-based dedup, matching here is **by exact trimmed name only**. Two unrelated families who happen to share a parent's name (a real possibility at scale) will be merged into a single `ParentGuardian` record. This is a deliberate, knowingly-accepted risk — no further validation or disambiguation is applied.

The raw `student_udise_details.father_name`/`mother_name` text columns (§A.2) are **kept regardless** — they're an independent audit trail of exactly what the source file said, distinct from whatever the linked `ParentGuardian` record ends up being (which could, in principle, later be edited or re-merged by other means).

The per-row `ImportRowLog` summary records each parent's outcome the same way the retired importer does: `{ "action": "created" | "matched", "id": ... }`, or `null` if that row provided no name at all.

### A.7 Vehicle auto-create

The Vehicle column now auto-creates a real `Vehicle` record when non-blank — `Vehicle::firstOrCreate(['vehicle_no' => trim($row['vehicle'])])`, same pattern the retired importer uses for its Bus No. column, tracked via the run's `vehicles_created` counter. Because the literal text `"None"` is already normalized to blank before this point (§A.2's global blank/"None" normalization), the ~128 rows where Vehicle genuinely means "no vehicle" correctly create nothing, rather than a fake `Vehicle` row literally named `"None"`.

This is deliberately narrow in scope, matching only what was asked:
- **Stoppage never auto-creates anything** — it stays raw text only on `student_udise_details.stoppage`, exactly like the retired importer treats Route as match-only, because a stoppage/route needs real geography and stops that a spreadsheet column can't supply.
- **No `TransportRoute` or `StudentTransport` record is created or linked** — this only creates the `Vehicle` master-data row itself. Actually assigning a student to a route/vehicle is out of scope here (the retired importer's `assignTransport()` logic is not replicated).

### A.8 `student_udise_details` and `student_session_history` — why two new tables

- **`student_udise_details`** (1 row per student, `student_id` unique FK, cascade-deletes with the student — same shape as `student_additional_details`) holds every UDISE-style field that has no natural home on `students`: minority group, BPL/EWS/CWSN/AAY flags, guardian/alternate-contact info, the raw Father/Mother Name and Stoppage/Vehicle text (kept alongside the real linked records — §A.6/§A.7), and a handful of reserved columns (`student_state_code`, `is_repeater`, `entry_status`, `student_pen`) that stay null until either the source file starts populating them or Student PEN Import (§B) fills `student_pen`.
- **`student_session_history`** (1 row per student **per session**, unique on `[student_id, session]`) holds the file's per-session history — that session's Class/Section/Roll snapshot (stored raw, not FK-linked, since this is a historical record distinct from the student's *current* relationship on `students`), both status columns, and the previous-year exam/attendance fields. Re-importing the same session refreshes that session's row in place (non-destructively) rather than duplicating it.

### A.9 Field reference table

| Field (internal key) | Table | Column | Required | Notes |
|---|---|---|---|---|
| admission_no | students | admission_no | Yes | Match key |
| name | students | name | Yes | |
| roll_no | students, student_session_history | roll_no | No | |
| class | students | school_class_id (FK) | Yes on create, unless Class=0/blank (then create fails) | Auto-creates (capacity 40) if unmatched |
| section | students | section_id (FK) | No | Auto-creates (capacity 40) if unmatched, scoped to class |
| gender, blood_group, category, aadhar_no (Aadhaar No.), mobile, email, address, pincode, admission_date | students | (same/mapped name) | No | Non-destructive merge |
| dob | students | dob | No | Parsed via `ExcelDateParser::parse()`; DOB only — Age column is never imported |
| status (Status) | students, student_session_history | status | No | ACTIVE/INACTIVE → Active/Inactive; unrecognized/blank never guessed |
| session | student_session_history | session | Yes (for history) | Drives processing order; also auto-creates an `academic_sessions` row per unique value (§A.3) — never sets `is_current` |
| promotion_status (STATUS) | student_session_history | promotion_status | No | Raw PROMOTED/LEFT/NEW |
| student_type, minority_group, type_of_impairments, mother_tongue, rte_ews_admission, guardian_name, alternate_mobile, stoppage, admission_type, clsl, name_as_per_aadhaar | student_udise_details | (same name) | No | Free text, non-destructive merge |
| father_name, mother_name | students (father_id/mother_id, FK) + student_udise_details (raw text) | father_id/mother_id + father_name/mother_name | No | Resolved to `ParentGuardian` by exact trimmed name (`firstOrCreate` — see §A.6); raw text always also kept on `student_udise_details` |
| vehicle | student_udise_details (raw text) + vehicles (auto-created) | vehicle + vehicle_no | No | `Vehicle::firstOrCreate(['vehicle_no' => ...])` when non-blank (see §A.7); no Route/StudentTransport link created |
| bpl_beneficiary, ews_disadvantaged, cwsn, indian_national, aay_beneficiary, hostel, uses_transport, is_repeater | student_udise_details | (same name) | No | Parsed Yes/No → boolean; unrecognized values left untouched, never guessed |
| previous_year_schooling_status, previous_year_class, exam_appeared, exam_result, exam_marks_percent, attendance_days, attendance_percent | student_session_history | (same name) | No | |
| student_pen, student_state_code, entry_status | student_udise_details | (same name) | No | Reserved — always blank in the source file today |

---

## B. Student PEN Import (`StudentPenImportController`) — current, runs after §A

### B.1 Overview

- **Page**: Import & Export → Student PEN Import (`/import-export?type=student-pen-import`, same `ImportExport.vue` component, Import tab, `activeEntity === 'student-pen'`).
- **Backend**: `POST /erp/api/import-export/import/student-pen` → `StudentPenImportController::store()`.
- **Source file**: UDISE portal's "Students Details" export (`<UDISE code>_Students_Details_<session>_<name>.xlsx`, sheet `Students List`). **Row 1 is a title label, the real header is row 2** — `SpreadsheetImportReader::read($file, 2)` skips it. No Admission No. column exists in this file at all.

### B.2 Header map

Only 4 of the file's ~20 columns are recognized — every other column (SL #, Student State Code, Social Category, BPL beneficiary, CWSN, Is Repeater, Suspected Duplicate, Entry Status, AADHAAR No. (masked), Name As per AADHAAR, AADHAAR Validation Status, etc.) is read and silently ignored, matching the spec's "only extract Student PEN" requirement:

| Source header | Internal key | Used for |
|---|---|---|
| Class | `class` | matching |
| Section | `section` | matching |
| Name | `name` | matching |
| Student PEN | `student_pen` | the only field ever written |

**Aadhaar is never read from this file at all** — not even into a variable — because it's masked here (`********7224`) and must never be allowed to overwrite a real, unmasked Aadhaar already stored from Student Master Import.

### B.3 Matching — Name + Class + Section, no auto-create

There's no Admission No. in this file, so matching works differently from every other importer in this app:
1. `class` and `section` are resolved against **existing** `SchoolClass`/`Section` rows only — `SchoolClass::where('name', ...)->first()` / `Section::where('school_class_id', ...)->where('name', ...)->first()`. Neither is ever auto-created; an unmatched Class or Section fails the row.
2. `Student::where('name', ...)->where('school_class_id', ...)->where('section_id', ...)->get()` — **exactly one** match required.
   - **0 matches** → row fails: `"No student found matching this Name, Class and Section."`
   - **2+ matches** (e.g. two students with the same name in the same class/section) → row fails: `"Multiple students match this Name, Class and Section — resolve manually."`
   - **Exactly 1 match** → proceed.

Both failure cases land in Import Logs and the Failed Records tab exactly like any other failed row, so office staff can resolve ambiguous/unmatched rows by hand.

### B.4 What gets written

On exactly one match: `StudentUdiseDetail::firstOrCreate(['student_id' => $student->id])`, then — only if the row's `Student PEN` cell is non-blank — `update(['student_pen' => ...])`. **Nothing else is touched**: not the student, not any other UDISE-details field, not Aadhaar. If the PEN cell itself is blank on an otherwise-successfully-matched row, the row is still logged as a success (the match worked; there was just nothing to write) rather than a failure.

---

## §1–§13: `StudentImportController` — retired, kept for reference

Everything from here to the end of this document describes the **original** Student Import, which is no longer routed (see the top of this file, and `import-export.md`). It's kept verbatim because `StudentMasterImportController` (§A) doesn't replicate all of it — parent (father/mother) resolution by phone, Transport/Hostel auto-assignment, and the health/document-received fields on `student_additional_details` only exist in this retired controller. Nothing below this line reflects current routed behavior for `/import-export?type=student-import`.

### 1. Overview (retired)

- **Page**: previously Import & Export → Student Import; this controller is no longer wired to any route.
- **Backend**: `StudentImportController::store()` — unrouted.
- **Formats accepted**: `.xlsx`, `.xls`, `.csv` (via `PhpOffice\PhpSpreadsheet` through `App\Services\SpreadsheetImportReader::read()`, which auto-detects format and returns a lowercased/trimmed header row plus raw data rows).
- **Historical status**: while it was live, a full 291-row real import completed with 100% row success, using a full **upsert + auto-create** model (§6–9 below).
- **Per-row outcome logging**: every import run recorded what actually happened to *each* row (student created vs. updated, parent created vs. matched, what was auto-created, what was skipped and why) — see §13. `StudentMasterImportController`/`StudentPenImportController` use the same `ImportRowLog` mechanism, with their own summary shapes (§A/§B above).

## 2. Header mapping (`StudentImportController::HEADER_MAP`)

The importer does NOT require the client to rename their spreadsheet's columns. It recognizes the client's actual real-world header text directly (case-insensitive, trimmed), including spelling variants discovered across multiple real-file attempts. Any header not in this map is silently ignored on read (not an error) — currently that means `Caste`, `Father Photo`, `Mother Photo`, `Photo`, and `Fees Balance` are read from the file but never stored (see §7).

| Client header (any of) | Internal key | Saved to |
|---|---|---|
| `Adm No.` | `admission_no` | `students.admission_no` |
| `RollNo` | `roll_no` | `students.roll_no` |
| `Student Name` | `name` | `students.name` |
| `Class` | `class` | resolved to `students.school_class_id` |
| `Section` | `section` | resolved to `students.section_id` |
| `Gender` | `gender` | `students.gender` |
| `Date Of Birth` | `dob` | `students.dob` (parsed, see §4) |
| `Address1` | `address` | `students.address` |
| `City1` | `city` | `students.city` |
| `State` | `state` | `students.state` |
| `Phone` | `mobile` | `students.mobile` |
| `Email` | `email` | `students.email` |
| `Category` | `category` | `students.category` |
| `Blood Group` | `blood_group` | `students.blood_group` |
| `Aadhaar UID` / `Aadhar Card UID ID` | `aadhar_no` | `students.aadhar_no` |
| `Date of Admission` / `Date of Addmission` | `admission_date` | `students.admission_date` (parsed) |
| `School Status` | `status` | `students.status` (normalized, see §5) |
| `Route` | `route` | resolved → `student_transports` (see §8) |
| `Bus no.` | `bus_no` | fallback route lookup (see §8) |
| `House` | `house` | `student_additional_details.house` |
| `Father Name` | `father_name` | `parents.name` (father) |
| `Father Qualification` | `father_qualification` | `parents.qualification` |
| `Father Occupation` | `father_occupation` | `parents.occupation` |
| `Father Mobile` | `father_mobile` | `parents.phone` (also the dedup key) |
| `Father DOB` | `father_dob` | `parents.dob` (parsed) |
| `Mother Name`/`Qualification`/`Occupation`/`Mobile`/`DOB` | `mother_*` | same as father, on the mother `parents` row |
| `Parents Anniversary Date` | `parents_anniversary_date` | `student_additional_details.parents_anniversary_date` (parsed) |
| `Last School Name` | `last_school_name` | `student_additional_details.last_school_name` |
| `Last Exam` / `LastExam` | `last_exam` | `student_additional_details.last_exam` |
| `Year` | `last_exam_year` | `student_additional_details.last_exam_year` |
| `Status` (in the previous-school block, distinct from `School Status`) | `last_exam_status` | `student_additional_details.last_exam_status` |
| `Marks` | `last_exam_marks` | `student_additional_details.last_exam_marks` |
| `Board` | `last_exam_board` | `student_additional_details.last_exam_board` |
| `Form No.` / `FormNo` | `form_no` | `student_additional_details.form_no` |
| `Remarks1` | `remarks_1` | `student_additional_details.remarks_1` |
| `Remarks2` | `remarks_2` | `student_additional_details.remarks_2` |
| `Opening Balance` / `Op Bal.` | `opening_balance` | `student_additional_details.opening_balance` |
| `Hostel Room no` / `Hostel Room No.` | `hostel_room_no` | resolved → `hostel_allocations` (see §8) |
| `Bed no.` | `hostel_bed_no` | resolved → `hostel_allocations` (see §8) |
| `Discontinue Date` | `discontinue_date` | `student_additional_details.discontinue_date` (parsed) |
| `Report Card` / `ReportC` | `report_card_received` | `student_additional_details.report_card_received` (boolean, see §7) |
| `CC` | `cc_received` | `student_additional_details.cc_received` (boolean) |
| `TC` | `tc_received` | `student_additional_details.tc_received` (boolean) |
| `DOB Certificate` / `DobCertificate` | `dob_certificate_received` | `student_additional_details.dob_certificate_received` (boolean) |
| `Scholarship No.` | `scholarship_no` | `student_additional_details.scholarship_no` |
| `Height` | `height` | `student_additional_details.height` (decimal) |
| `Weight` | `weight` | `student_additional_details.weight` (decimal) |
| `Family` | `family` | `student_additional_details.family` |
| `Vision Left` / `Vision Right` | `vision_left`/`vision_right` | `student_additional_details.*` |
| `Dental Hygiene` / `Dental Hygeine` | `dental_hygiene` | `student_additional_details.dental_hygiene` |
| `Additional Field 1`–`10` | `additional_field_1`–`10` | `student_additional_details.additional_field_1`–`10` |
| `Student ID` | `student_ref_id` | `student_additional_details.student_ref_id` |
| `Biometrics Card No.` | `biometric_card_no` | `student_additional_details.biometric_card_no` |
| `Child UID` / `Child UID ID` | `child_uid` | `student_additional_details.child_uid` |
| `GR No.` | `gr_no` | `student_additional_details.gr_no` |
| `PEN No.` | `pen_no` | `student_additional_details.pen_no` |

**Not imported at all today** — and, as of this revision, **surfaced to the user rather than silently dropped**. `StudentImportController::EXCLUDED_HEADERS` is a small alias map (`caste`, `fees balance`, `father photo`, `mother photo`, `photo`, `student photo`) checked against the *actual* header row of each specific uploaded file at `store()` time; whichever of these are genuinely present in that file are recorded on the run's `ImportExportLog.ignored_columns` (JSON array of human labels, e.g. `["Caste","Photo"]`) and shown as a warning banner when the run is expanded in Import Logs — so "this column exists in your file but nothing is done with it" is visible in the UI, not just documented here:
- `Caste` — deliberately excluded (sensitive personal/demographic data; not stored anywhere in the schema today).
- `Father Photo`, `Mother Photo`, `Photo` — these are file paths/embedded images in the source Excel; the importer has no file-handling step, so there is nothing meaningful to extract from a text cell. No file-upload infrastructure exists anywhere in the ERP (confirmed by grepping for `Storage::` across `app/Http/Controllers/Erp` — zero matches).
- `Fees Balance` — deliberately excluded. The ERP computes a student's real fee balance live via `App\Services\FeeCalculator` from `ErpFeeStructure` minus `FeeDiscount` minus `FeePayment` rows; a statically imported number would immediately be stale and could mislead anyone reading it next to the real computed figure. `Opening Balance` (a distinct column, meaning a starting balance before the ERP was in use) IS imported, purely as a historical reference field with no live computation attached to it.

## 3. `student_additional_details` — why a second table

`students` already carries the "core" fields used elsewhere in the app (in joins, filters, reports): `admission_no, roll_no, name, school_class_id, section_id, father_id, mother_id, guardian_id, gender, dob, blood_group, category, religion, nationality, aadhar_no, mobile, email, address, city, state, pincode, status, admission_date`. Everything else the client's spreadsheet carries — health data, document-received flags, previous-school history, hostel-adjacent identifiers, the 10 generic Additional Fields — has no natural home there and is rarely queried elsewhere in the app, so it lives in a companion 1:1 table (`student_additional_details.student_id` unique FK, cascade-deletes with the student) via `Student::additionalDetail(): HasOne`. `upsertAdditionalDetail()` (`StudentImportController.php`) only ever writes a field if this row's cell for it is non-blank — via `StudentAdditionalDetail::firstOrNew(['student_id' => ...])->fill($data)->save()` — so a first-time import with no extended data creates no row at all, and a later re-import that adds a few fields only ever adds to (never blanks) what's already there. Unlike the manual Add/Edit form's `saveDetail()` (`people.md`), the importer never deletes this row — there's no "explicitly clear a field" input in a spreadsheet cell the way there is in a form, so the delete-if-now-empty case that `saveDetail()` handles doesn't apply here.

## 4. Date parsing (`StudentImportController::parseDate()`)

The client's real file mixes date formats across different rows/columns — `04-11-2017` (DD-MM-YYYY), `25/04/1992` (DD/MM/YYYY), `15.08.05` (dotted, 2-digit year) all appear in genuine data. `parseDate()` handles, in order:
1. `D[-/.]M[-/.]Y` or `DD[-/.]MM[-/.]YYYY` — **day-first is always assumed** (this is Indian school data; there is no locale-detection, day-first is a hard assumption). 2-digit years: `>= 70` → `19xx`, otherwise `20xx`. Validated with `checkdate()`.
2. ISO `YYYY-M-D`.
3. A raw numeric value — treated as an Excel date serial number via `PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject()`.
4. Anything else (including the literal text `"N.A."`, garbage strings) → `null`.

This exists specifically because Eloquent's `date` cast throws an uncaught `ErrorException`/`InvalidFormatException` on an unparseable string, which used to crash the entire import request on the first bad date it hit (a real, now-fixed bug — the request-level crash meant even the successfully-processed rows before the bad one only survived because each row runs in its own `DB::transaction()`, not one transaction for the whole file). Every date field in this importer (`dob`, `admission_date`, `father_dob`, `mother_dob`, `parents_anniversary_date`, `discontinue_date`) is normalized through this same function.

## 5. "N.A."/"NONE" and status normalization

- `BLANK_VALUES = ['n.a.', 'na', 'n/a', 'nil', '-', 'none']` (case-insensitive) — normalized to an empty string at the point `$row` is built, so `"N.A."` and `"NONE"` both behave identically to a truly empty cell for every field, not just some. **`'none'` was added in this revision** — a real 291-row import found that the client's file uses both `N.A.` and `NONE` interchangeably to mean "no transport route provided" in the Route column, but only `N.A.` was in the original list; `NONE` fell through as a literal string, got compared against real route names, never matched, and was logged as "Route didn't match — skipped" (a lookup failure) instead of "Not provided" (no data given) — two very different situations that look identical without this fix, making it hard to tell genuinely-missing routes from a real matching bug at a glance. This is a global fix (every field benefits, not just Route).
- `STATUS_MAP` normalizes the `School Status` column's free text into the `students.status` enum (`Active|Inactive|Transferred`): `active/continue/continuing/studying → Active`; `inactive/left/discontinued → Inactive`; `transferred/transfer/"tc issued"/tc → Transferred`. Anything blank or unrecognized defaults to `Active` (matching the field's own pre-existing default before this importer supported free text at all).
- `CLASS_ALIASES = ['nur' => 'Nursery']` — the client's file abbreviates Nursery as `Nur`; this maps it to the real, existing `Nursery` class rather than failing to find a class literally named "Nur" or creating a duplicate.
- The 4 document-received columns (`Report Card`/`CC`/`TC`/`DOB Certificate`) are boolean-ish in the source data (values seen: `0`/`1`, blank). `FALSY_VALUES = ['', 'no', 'n', '0', 'false', 'not received', 'pending']` — any value NOT in that list counts as "received" (`true`).

## 6. Class/Section resolution — auto-create with a flat default capacity

**Reworked in this revision.** `SchoolClass::firstOrCreate(['name' => $className], ['capacity' => 40])` (after the alias check) — if no class with that exact name exists, one is now created automatically with a flat `capacity` of 40, rather than failing the row. This was a deliberate product decision (confirmed with the client) made specifically to let a large real-world file import in one pass without requiring every referenced class to be created by hand first. `SchoolClass::firstOrCreate()`'s `wasRecentlyCreated` flag is checked immediately after the call and fed into that row's per-row outcome log (§13) and the run-level `classes_created` counter — so which classes were auto-created, and how many, is always visible after the fact, not silently absorbed.
Section resolution follows the identical auto-create pattern, scoped to the resolved class: `Section::firstOrCreate(['school_class_id' => ..., 'name' => $row['section']], ['capacity' => 40])`, tracked by `sections_created`.
On an **update** to an already-existing student, a blank Class/Section cell does not touch the student's current class/section — see §9's non-destructive merge rule, which applies here too.

## 7. Parent resolution (father & mother) — update-if-found, not create-only

**Reworked in this revision.** `resolveParent()` runs once per prefix (`father`/`mother`) per row:
- If both name and mobile are blank for that parent → `null` (no parent record, `father_id`/`mother_id` is left untouched on the student — see §9).
- **Dedup key: phone number**, same as before. But the found/not-found branches now behave differently:
  - **Found by phone**: the existing `ParentGuardian` row is `fill()`-ed with only this row's **non-blank** `name`/`occupation`/`qualification`/`dob` and saved — a blank cell in this row never overwrites that parent's existing data (identical non-destructive philosophy to §9). Re-importing now *does* refresh an existing parent's details, which the previous `firstOrCreate`-based implementation explicitly did not do.
  - **Not found by phone**: a new `parents` row is created, same as before.
- If no phone is given at all, a fresh `parents` row is still always created (with `phone => null`) — unchanged; there's no way to dedupe two blank-phone parents against each other.
- The per-row outcome log (§13) records `created` vs. `matched` for each of father/mother, based on Eloquent's `wasRecentlyCreated` flag on the resolved model.

## 8. Transport & Hostel — auto-create for Vehicle/Room/Bed, Route stays match-only

Both remain optional per-row side effects that never fail the row outright if nothing resolves — but unmatched Vehicle/Room/Bed data now gets created rather than silently skipped, closing the gap that used to require creating master data by hand before a real import could fully succeed.

**Transport** (`assignTransport()`), reworked:
1. Try `TransportRoute::where('name', $row['route'])->first()`. **Route remains match-only — it is never auto-created** (a deliberate, disclosed exception; see the investigation note below for why this matters in practice).
2. If a Bus No. was given: `Vehicle::where('vehicle_no', ...)->first()` — if not found, **the vehicle is now auto-created** (`Vehicle::create(['vehicle_no' => ...])`, taking the migration's schema defaults `type='Bus'`, `capacity=0`), tracked via `vehicles_created`. If no route was resolved by name yet, also try `TransportRoute::where('vehicle_id', $vehicle->id)->first()` (works whether the vehicle was just created or already existed).
3. If a route was resolved by name **and** that route currently has no `vehicle_id`, and a vehicle was found/created this row, the vehicle is attached to the route (`$route->update(['vehicle_id' => $vehicle->id])`). Otherwise a newly auto-created vehicle stays unassigned to any route.
4. If a route was resolved (either way), pick its **first stop by `sequence_no`** — unchanged from before; RouteStops are never auto-created, so a route with zero stops still results in a skip.
5. `StudentTransport::updateOrCreate(['student_id' => ...], [...])` — **changed from `firstOrCreate` to `updateOrCreate`**, so re-importing a student whose route/stop changed now updates the existing assignment instead of leaving the original one in place.
6. If nothing resolves — no route by name and no Vehicle/route pairing via Bus No. — the row's transport outcome is recorded as skipped with `reason: 'route_not_found'` (see §13); if a route resolved but has no stops, `reason: 'route_has_no_stops'`.

**Hostel** (`assignHostel()`), reworked:
1. Requires BOTH Room No. and Bed No. to be non-blank; if either is missing, skipped entirely (unchanged).
2. `Room::where('room_no', ...)->first()` — **if not found, now auto-created** (`Room::create(['room_no' => ...])`, schema defaults `type='Double'`, `capacity=2`, `monthly_fee=0`), tracked via `rooms_created`.
3. `Bed::where('room_id', ...)->where('bed_no', ...)->first()` — **if not found, now auto-created** (`Bed::create(['room_id' => ..., 'bed_no' => ..., 'status' => 'Available'])` — `status` must be set explicitly in the create call, not left to the DB column default, because a freshly-created Eloquent model doesn't know about a DB-applied default until it's re-read; this was a real bug caught during testing, where every auto-created bed's in-memory `->status` read back `null` and the allocation logic incorrectly treated it as occupied), tracked via `beds_created`.
4. **New: vacate-then-create for bed switches.** If the student already has an **Active** `HostelAllocation` to a *different* bed than the one resolved this row, the old allocation is vacated first (`status → Inactive`, `end_date → today`, old bed freed to `Available`) — mirroring exactly what `HostelAllocationController::vacate()` does — before the new allocation is created and the new bed marked `Occupied`. If the resolved bed is the student's *current* bed, this is a no-op (outcome `already_in_bed`). If the resolved bed exists but is `Occupied` by a different student, the row is skipped (outcome `bed_occupied`) rather than stealing the bed.

## 9. Duplicate handling — upsert, non-destructive merge

**Reworked in this revision — this was the single biggest behavior change.** `Student::where('admission_no', $row['admission_no'])->first()` is checked before validation. If found, the row now **updates** that student instead of rejecting; if not found, it creates one as before (creation still requires the Class to resolve — see §6).

**The non-destructive merge rule, applied everywhere in the importer (students, additional-details, parents):** a blank Excel cell on an update row never overwrites existing data. Mechanically, every field is built via `array_filter(..., fn ($v) => $v !== null)` before being handed to `Validator::make()` — a blank cell resolves to `null` and is dropped from the array entirely, so it's simply never one of the keys `$existingStudent->update($data)` touches, leaving whatever was already stored untouched. This is *not* the same as "check whether the incoming request is blank" (which would be wrong — see `people.md`'s note on `StudentController::saveDetail()`, which hit and fixed the equivalent bug for the manual Student form); for the importer specifically there's no "delete if now entirely empty" concern, since import rows only ever add or refresh data, never explicitly clear a field.

`admission_no` and `name` remain required on every row, including update rows — a row missing either still fails with the original message, even if it would otherwise resolve to an update. This means a genuine partial re-import (only some columns filled in) must still always carry Admission No and Student Name; only the *other* columns are optional to include or leave blank on a re-import.

There is still no secondary duplicate lookup via `Student ID` (`student_ref_id`) — `admission_no` is the sole match key.

## 10. Failure handling & atomicity

- The file is read entirely in memory first; then each row is processed in its own `DB::transaction()` inside `importRow()` — a failure on row 150 has **no effect** on rows 1–149, which are already committed individually. This is why a single bad date or one missing class, deep into a 291-row file, does not undo any of the successful rows before it.
- Every failure is still recorded as an `ImportFailedRow` (row number, the row's parsed data, and the exact error message) linked to the run's `ImportExportLog`, exactly as before — this is what still feeds the standalone Import & Export → Failed Records tab.
- **New this revision**: every row — success *or* failure — additionally gets an `ImportRowLog` recording a structured outcome (student created/updated, father/mother created/matched/not-provided, additional-details saved/skipped, transport assigned/skipped+reason, hostel assigned/switched/skipped+reason, and which master data was auto-created). The run-level `ImportExportLog` also gains `classes_created`/`sections_created`/`vehicles_created`/`rooms_created`/`beds_created` counters and `ignored_columns`. Full schema and the Import Logs UI are documented in `import-export.md` — kept there rather than duplicated here since the mechanism is intended to generalize to any future importer, not just Student.
- Completely blank rows (every cell empty) are skipped silently and not counted in `total_rows` at all.

## 11. Investigation note — "Route didn't match" is (usually) not a matching bug

A real 291-row import found 172 students with valid-looking route names (`CHANP` ×37, `TETARIA` ×18, etc. — ~29 distinct routes) all logged as "Route didn't match — skipped." Before assuming a matching-logic bug, this was verified directly against the database: **the `routes` table had exactly 1 row** (a placeholder seed route), so there was nothing for any of those 29 real route names to match against — not a bug in the lookup itself. The matching logic was independently checked and confirmed correct: header/cell values are `trim()`-med before comparison (§2), and the `routes.name` column uses `utf8mb4_unicode_ci` collation, which is case-insensitive at the database level, so `WHERE name = 'chanp'` matches a row literally named `CHANP`. **Route deliberately stays match-only, never auto-created** (§8) — unlike Class/Vehicle/Room/Bed, a route needs real `start_point`/`end_point` geography and at least one real `RouteStop` to be useful, none of which can be inferred from an Excel column that only ever contains a route *name*. Missing routes have to be created for real (Transport Management > Routes, with real stop data) before a re-import will assign them — see the `route_not_found` outcome reason in §13/`import-export.md` for how a row like this now shows up per-run rather than only as an aggregate "not assigned" count.

## 12. Field reference table

| Field (internal key) | Table | Column | Type | Required | Notes |
|---|---|---|---|---|---|
| admission_no | students | admission_no | string(50), unique | Yes | Match key — update if found, else create |
| name | students | name | string(255) | Yes | Required on every row, including updates |
| roll_no | students | roll_no | unsigned int, nullable | No | |
| class | students | school_class_id (FK) | — | Yes on create only | Resolved by name; **auto-creates** (capacity 40) if unmatched |
| section | students | section_id (FK) | — | No | Resolved by name within class; **auto-creates** (capacity 40) if unmatched |
| gender | students | gender | enum(Male,Female,Other), nullable | No | |
| dob | students | dob | date, nullable | No | Parsed via `parseDate()` |
| blood_group | students | blood_group | string(10), nullable | No | |
| category | students | category | string(100), nullable | No | Free text, no lookup table |
| aadhar_no | students | aadhar_no | string(20), nullable | No | |
| mobile | students | mobile | string(30), nullable | No | |
| email | students | email | email, nullable | No | |
| address/city/state | students | address/city/state | string, nullable | No | |
| status | students | status | enum(Active,Inactive,Transferred) | No | Normalized via `STATUS_MAP` if provided; blank leaves existing value untouched on update, defaults Active on create |
| admission_date | students | admission_date | date, nullable | No | Parsed via `parseDate()` |
| father/mother name, mobile, occupation, qualification, dob | parents | name, phone, occupation, qualification, dob | mixed | No | Deduped by phone; **now updates non-blank fields if matched** |
| house, family, vision_left, vision_right, dental_hygiene, form_no, remarks_1, remarks_2, last_school_name, last_exam, last_exam_year, last_exam_status, last_exam_marks, last_exam_board, scholarship_no, student_ref_id, biometric_card_no, child_uid, gr_no, pen_no | student_additional_details | (same name) | string, nullable | No | Row upserted; only non-blank fields ever written |
| height, weight, opening_balance | student_additional_details | (same name) | decimal(6,2) / decimal(12,2), nullable | No | |
| discontinue_date, parents_anniversary_date | student_additional_details | (same name) | date, nullable | No | Parsed via `parseDate()` |
| report_card_received, cc_received, tc_received, dob_certificate_received | student_additional_details | (same name) | boolean, default false | No | Only written if this row actually provides a value — blank never flips an existing flag |
| additional_field_1–10 | student_additional_details | (same name) | string, nullable | No | Generic, no defined real-world meaning |
| route / bus_no | student_transports | route_id, route_stop_id (FK) | — | No | Route match-only; Vehicle (via Bus No.) **auto-creates**; assignment `updateOrCreate`s |
| hostel_room_no / hostel_bed_no | hostel_allocations | bed_id (FK) | — | No | Room/Bed **auto-create**; switches vacate the old bed first |

## 13. Known current limitations (accurate as of this document)

- No secondary duplicate check via `student_ref_id` — `admission_no` is the sole match key for upsert.
- Route is still match-only — unlike Class/Section/Vehicle/Room/Bed, it is never auto-created (see §11 for why, and the real-world impact this had).
- A route with zero `RouteStop`s still results in a skip even if the route itself matches — RouteStops are never auto-created.
- Caste and Fees Balance are read from the file but never stored — now at least surfaced per-run via `ignored_columns` (§10) rather than being a purely-documented fact.
- `Category`/`House` are free-text with no master/lookup table — any value is accepted, nothing is ever validated against a fixed list.
- `admission_no` and `name` must be present on every row, including rows meant purely to update other fields on an existing student — a spreadsheet that omits either for an update-only row will fail with "Admission No and Name are required," even though the student already exists.
