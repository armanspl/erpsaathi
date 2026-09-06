# People

Sidebar group: **People** (`resources/js/erp/data/menu.js`, icon `👥`). Backend routes: `routes/erp_api.php`, `Route::prefix('people')->name('people.')`. Controllers: `app/Http/Controllers/Erp/People/*.php`. See [README.md](README.md) for the cross-cutting architecture (routing, permission model, generic-page mechanism, audit logging, morph maps, calculator services, Import/Export infra) referenced throughout this file rather than re-explained.

## 1. Module Overview

People is the master-data module for every human entity the ERP tracks: **Students**, their **Parents/Guardians**, **Teachers**, **Staff**, **Drivers**, front-desk **Visitors**, and the ERP's own login accounts (**Users**). It is the most upstream module in the system — almost every other module (Attendance, Fee Management, Exam Management, Transport, Hostel, Library, Finance & Payroll) stores a foreign key or polymorphic reference back to a Student, Teacher, Staff or Driver record created here.

Typical workflow: a Student is admitted (via People > Students, or via Admissions, or via the Excel importer — see `student-import-management.md`) and linked to a Class/Section (Academics module) and optionally to up to three Parent/Guardian records. Teachers, Staff and Drivers are added independently as employee records, each with their own `employee_id`. Visitors are logged at the front desk for the duration of a single premises visit. ERP Users are separate from all of the above — they are login credentials for staff who operate the ERP itself (see §"Users vs. other People entities" below).

Related modules: Academics (Classes/Sections that Students belong to), Attendance (marks Students/Teachers/Staff/Drivers present/absent — depends on all four person types existing here first), Fee Management/Exam Management/Transport/Hostel/Library (all reference `student_id`), Finance & Payroll (Salary Structures/Slips reference Teacher/Staff/Driver via the same polymorphic pattern as Attendance), Account (`account.md`, for the logged-in user's own profile/password/theme — distinct from the People > Users admin screen documented here).

## 2. Menu Structure

| Menu label | Path | Status | Component / reason |
|---|---|---|---|
| Users | `/people/users` | Real | `pages/people/Users.vue` → `UserController` |
| Students | `/people/students` | Real | `pages/people/Students.vue` → `StudentController` |
| Parents | `/people/parents` | Real | `pages/people/Parents.vue` → `ParentController` |
| Teachers | `/people/teachers` | Real | `pages/people/Teachers.vue` → `TeacherController` |
| Staff | `/people/staff` | Real | `pages/people/Staff.vue` → `StaffController` |
| Drivers | `/people/drivers` | Real | `pages/people/Drivers.vue` → `DriverController` |
| Visitor Records | `/people/visitor-records` | Real | `pages/people/VisitorRecords.vue` → `VisitorController` |
| Alumni | `/people/alumni` | Generic | No backend route/controller/table exists for alumni; falls back to `GenericModule.vue` (mock data, nothing persists). |
| UDISE+ | `/people/udise-plus` | Generic | No backend route/controller exists for India's UDISE+ government reporting integration; `GenericModule.vue` fallback. |
| User Reports | `/people/user-reports` | Generic | No dedicated report controller/route for this leaf; `GenericModule.vue` fallback (not to be confused with the real Attendance/Fee/Finance reports elsewhere). |

Note on "Users": this is the only People leaf styled without `DataTable`/`SlideOver` — it's a bespoke smaller page (own inline `<table>`) because its write actions are additionally gated by an admin-only check inside the controller (see §Users below).

## 3–17. Pages

### 3.1 Students

**Name**: Students · **Frontend URL**: `/people/students` · **Backend**: `GET/POST /erp/api/people/students`, `PUT/DELETE /erp/api/people/students/{student}` · **Controller**: `App\Http\Controllers\Erp\People\StudentController` (`index`, `store`, `update`, `destroy`) · **Vue**: `resources/js/erp/pages/people/Students.vue` (663 lines — the largest People page, a real list/CRUD screen plus a fully real 9-tab detail view).

**Permissions**: `index` (list) open to any authenticated `erp` user. `store`/`update`/`destroy` require `erp.permission:people.manage` (route group in `routes/erp_api.php` lines 179–205).

**Validation** (`StudentController::validated()`, verbatim):
```
'admission_no' => ['required', 'string', 'max:50', Rule::unique('students', 'admission_no')->ignore($student?->id)],
'roll_no' => 'nullable|integer|min:0',
'name' => 'required|string|max:255',
'school_class_id' => 'required|exists:school_classes,id',
'section_id' => 'nullable|exists:sections,id',
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
'mobile' => 'nullable|string|max:30',
'email' => 'nullable|email|max:255',
'address' => 'nullable|string|max:255',
'city' => 'nullable|string|max:100',
'state' => 'nullable|string|max:100',
'pincode' => 'nullable|string|max:20',
'status' => 'required|in:Active,Inactive,Transferred',
'admission_date' => 'nullable|date',
```
Plus a manual cross-field check, `validateSectionBelongsToClass()`: if `section_id` is filled, it must belong to a `sections` row whose `school_class_id` matches the submitted `school_class_id`, otherwise a `ValidationException` is thrown on the `section_id` key ("The selected section does not belong to the selected class."). The same check runs on both create and update.

**Form Layout** (SlideOver "Add/Edit Student") is now a full ~50-field form spanning 7 visual sections (a `.form-section-heading` divider before each), no longer the 11-field form of earlier revisions. On-screen order:
1. **Identity & Academic** (no heading, top of form): Admission No, Roll No, Student Name, [Class | Section] (Section options recompute reactively from the selected Class via `sectionsForSelectedClass`), [Gender | Status].
2. **Personal & Contact**: Date of Birth, Blood Group, Category, Religion, Nationality, Aadhar No, Mobile, Email, Address, [City | State | Pincode], Admission Date.
3. **Family**: [Father | Mother | Guardian] (three parallel dropdowns of `parents` records, hint "Need to add a new parent first? Go to People > Parents."), House, Family.
4. **Health**: Height (cm), Weight (kg), Vision Left, Vision Right, Dental Hygiene.
5. **Previous School**: Last School Name, Last Exam, Year, Result Status, Marks, Board.
6. **Admission & Documents**: Form No., Scholarship No., Discontinue Date, four checkboxes (Report Card / Character Certificate / Transfer Certificate / DOB Certificate Received), Remarks 1, Remarks 2.
7. **IDs & Financial**: Student ID, Parents' Anniversary Date, Biometric Card No., Child UID, GR No., PEN No., Opening Balance (with an inline hint: "Historical reference only — the ERP computes the student's real fee balance live from Fee Structure and Payments").
8. **Additional Fields (1–10)**: collapsed inside a `<details>` disclosure, off by default.

Height/Weight/Opening Balance use the `.number` v-model modifier (numeric inputs elsewhere in this form are otherwise plain strings). Sections 2–3 (minus the Family dropdowns) map 1:1 onto `students` table columns via `StudentController::validated()`; sections 3 (House/Family)–8 map onto the `student_additional_details` side table via `StudentController::validatedDetail()`/`saveDetail()` (see below) — the form deliberately mirrors the field grouping of the read-only detail tabs so editing and viewing agree.

**Two fields intentionally excluded from this form** despite being valid, validated, mass-assignable columns on `student_additional_details`: **`caste`** (sensitive data, excluded by product decision) and **`fees_balance`** (would immediately go stale — the ERP always computes a student's real fee balance live via `FeeCalculator`, never from a stored snapshot; `opening_balance` above is the only balance-related field surfaced, explicitly labelled as historical-only). Both remain settable only via direct API call; the Excel importer also deliberately never maps any column to either (see `student-import-management.md`).

**Every Field** (`students` table):

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Admission No | `admission_no` | students | string | Yes | unique, max:50 | — | — | — |
| Roll No | `roll_no` | students | unsigned int | No | integer, min:0 | — | — | — |
| Student Name | `name` | students | string | Yes | max:255 | — | — | — |
| Class | `school_class_id` | students | FK bigint | Yes | exists:school_classes,id | — | `GET /academics/classes` | `school_classes.id` (restrictOnDelete) |
| Section | `section_id` | students | FK bigint, nullable | No | exists:sections,id + must belong to selected class | NULL | `GET /academics/sections`, filtered client-side by class | `sections.id` (nullOnDelete) |
| Gender | `gender` | students | enum | No | in:Male,Female,Other | NULL | static options | — |
| Status | `status` | students | enum | Yes | in:Active,Inactive,Transferred | `Active` | static options | — |
| Date of Birth | `dob` | students | date | No | date | NULL | — | — |
| Blood Group | `blood_group` | students | string | No | max:10 | NULL | — | — |
| Category | `category` | students | string | No | max:100 | NULL | — | — |
| Religion | `religion` | students | string | No | max:100 | NULL | — | — |
| Nationality | `nationality` | students | string | No | max:100 | `Indian` (migration default) | — | — |
| Aadhar No | `aadhar_no` | students | string | No | max:20 | NULL | — | — |
| Mobile | `mobile` | students | string | No | max:30 | NULL | — | — |
| Email | `email` | students | string | No | email, max:255 | NULL | — | — |
| Address | `address` | students | string | No | max:255 | NULL | — | — |
| City | `city` | students | string | No | max:100 | NULL | — | — |
| State | `state` | students | string | No | max:100 | NULL | — | — |
| Pincode | `pincode` | students | string | No | max:20 | NULL | — | — |
| Admission Date | `admission_date` | students | date | No | date | NULL | — | — |
| Father | `father_id` | students | FK bigint, nullable | No | exists:parents,id | NULL | `GET /people/parents` | `parents.id` (nullOnDelete) |
| Mother | `mother_id` | students | FK bigint, nullable | No | exists:parents,id | NULL | `GET /people/parents` | `parents.id` (nullOnDelete) |
| Guardian | `guardian_id` | students | FK bigint, nullable | No | exists:parents,id | NULL | `GET /people/parents` | `parents.id` (nullOnDelete) |

**Every Field** (`student_additional_details` table, validated by `StudentController::validatedDetail()` — all optional, a student may have none of this data):

| Label | DB Column | Type | Validation |
|---|---|---|---|
| House | `house` | string | max:255 |
| Family | `family` | string | max:255 |
| Height (cm) | `height` | decimal 6,2 | numeric, min:0 |
| Weight (kg) | `weight` | decimal 6,2 | numeric, min:0 |
| Vision Left | `vision_left` | string | max:255 |
| Vision Right | `vision_right` | string | max:255 |
| Dental Hygiene | `dental_hygiene` | string | max:255 |
| Last School Name | `last_school_name` | string | max:255 |
| Last Exam | `last_exam` | string | max:255 |
| Year | `last_exam_year` | string | max:255 |
| Result Status | `last_exam_status` | string | max:255 |
| Marks | `last_exam_marks` | string | max:255 |
| Board | `last_exam_board` | string | max:255 |
| Form No. | `form_no` | string | max:255 |
| Scholarship No. | `scholarship_no` | string | max:255 |
| Discontinue Date | `discontinue_date` | date | date |
| Report Card Received | `report_card_received` | boolean | boolean, defaults `false` if omitted |
| Character Certificate Received | `cc_received` | boolean | boolean, defaults `false` if omitted |
| Transfer Certificate Received | `tc_received` | boolean | boolean, defaults `false` if omitted |
| DOB Certificate Received | `dob_certificate_received` | boolean | boolean, defaults `false` if omitted |
| Remarks 1 | `remarks_1` | string | max:255 |
| Remarks 2 | `remarks_2` | string | max:255 |
| Student ID | `student_ref_id` | string | max:255 |
| Parents' Anniversary Date | `parents_anniversary_date` | date | date |
| Biometric Card No. | `biometric_card_no` | string | max:255 |
| Child UID | `child_uid` | string | max:255 |
| GR No. | `gr_no` | string | max:255 |
| PEN No. | `pen_no` | string | max:255 |
| Opening Balance | `opening_balance` | decimal 12,2 | numeric, min:0 |
| Additional Field 1–10 | `additional_field_1`…`additional_field_10` | string ×10 | max:255 each |
| (excluded from form) Caste | `caste` | string | max:255 — validated/fillable, deliberately not exposed (sensitive data) |
| (excluded from form) Fees Balance | `fees_balance` | decimal 12,2 | numeric, min:0 — validated/fillable, deliberately not exposed (would go stale; real balance is always computed live) |

**Database Relationships** (`app/Models/Student.php`): `schoolClass()` belongsTo `SchoolClass`; `section()` belongsTo `Section`; `father()`/`mother()`/`guardian()` each belongsTo `ParentGuardian` (table `parents`) via their respective `*_id`; `additionalDetail()` hasOne `StudentAdditionalDetail`. Via the `HasAttendance` trait (`app/Models/Concerns/HasAttendance.php`, shared with Teacher/Staff/Driver): `attendances()` morphMany `Attendance`, `leaveRequests()` morphMany `LeaveRequest` (`attendable`).

**Create Flow**: `StudentController::store()` wraps everything in `DB::transaction()`: inserts into `students`, then calls `saveDetail()` to upsert the matching `student_additional_details` row from the same request payload (see "Upsert" below) — a single Add action now writes to both tables atomically.

**Edit Flow**: `PUT /people/students/{student}` re-runs the identical `students`-table validation (including the section-belongs-to-class check, with the current student's own `id` ignored for the `admission_no` uniqueness check), re-validates the `student_additional_details` payload, and — inside the same `DB::transaction()` — does a full `update($data)` on the student plus `saveDetail()` on the detail row.

**`saveDetail()` — non-destructive upsert, not a naive create-or-replace**: this method fixed a real data-loss bug found during testing. The obvious-looking implementation — check whether *this request's* fields are all blank, and delete the detail row if so — is wrong: a partial `PUT` that only sends a few already-blank fields would wipe out unrelated real data the request never mentioned. The actual implementation instead: loads the existing `additionalDetail` (or a new blank instance), `fill()`s it with this request's data, and only *then* checks the **merged, resulting state** (`$detail->getAttributes()`) for emptiness. If the merged state has at least one non-null/non-empty value or one `true` boolean flag, it saves; if the merged state is now entirely empty, it deletes the (now-stale) row. This means a `PUT` that omits a field leaves that field's existing stored value untouched, exactly like the Student Import upsert's non-destructive merge rule (`student-import-management.md`) — the two features were built with the same data-safety guarantee.

**Delete Flow**: `StudentController::destroy()` calls `$student->delete()` — a **hard delete** (no `SoftDeletes` trait on the model). Cascade behavior comes entirely from the migration's FK clauses: deleting a Student cascades to `student_additional_details` (`cascadeOnDelete`, since that table's `student_id` FK is `cascadeOnDelete`), while `Attendance`/`LeaveRequest` rows are **not** true foreign keys (see §Attendance polymorphism gotcha in `attendance.md`) so they are simply orphaned, not deleted, if a Student is removed. There is no restriction preventing deletion of a Student who has Fee/Exam/Transport/Hostel/Library records elsewhere in the app — those modules' FKs to `students` would need to be checked per-module.

**Business Rules**:
- `admission_no` is globally unique across all students.
- A Section, if chosen, must belong to the chosen Class (enforced server-side, not just in the UI's cascading dropdown).
- Status is a fixed 3-value enum (`Active`/`Inactive`/`Transferred`); there is no explicit "Alumni" or "Graduated" status — the sidebar's separate "Alumni" leaf is a Generic placeholder unrelated to this `status` column.
- The row-level "New" stat card counts students whose `admission_date` is within the last 30 days — purely a client-side computed stat, not a stored flag.

**Detail view — all 9 tabs are now real**: Clicking "view" on a student row opens a detail page with 9 tabs, each `:student="selectedStudent"`. As of this revision all 9 fetch or render genuine per-student data — none remain hardcoded/decorative:
| Tab | Detail |
|---|---|
| Personal | `StudentTabPersonal.vue` renders `dob`, `gender`, `blood_group`, `category`, `religion`, `nationality`, `aadhar_no`, `mobile`, `email`, `address`, `city`/`state`, `pincode` straight from the loaded Student record (no extra fetch — already eager-loaded by the list endpoint). |
| Parents | `StudentTabParents.vue` renders the `father`/`mother`/`guardian` relation objects (name, occupation, phone, email) from the loaded Student record (no extra fetch). |
| Health | `StudentTabHealth.vue` renders every `student_additional_details` field not already shown elsewhere, from `student.additional_detail` (no extra fetch): House/Family, Height/Weight/Vision/Dental Hygiene, Previous School/Exam/Year/Status/Marks/Board, GR No./PEN No./Student Ref ID/Biometric Card No./Child UID/Form No./Scholarship No./Opening Balance/Discontinue Date/Parents' Anniversary Date, the 4 received-flags, Remarks 1–2, and any non-blank Additional Field 1–10. |
| Documents | `StudentTabDocuments.vue` renders the 4 real received-flags (Report Card / CC / TC / DOB Certificate) from `student.additional_detail` as a checklist — no file preview/upload capability exists anywhere in the app (see §File Storage), so there is no "View" action, just Received/Not Received status. |
| Transport | `StudentTabTransport.vue` fetches `GET /transport/student-transport?student_id={id}` and renders the student's real Route, Vehicle, Driver (+ phone), Pickup Point, Monthly Fare, Start Date, Status — or "No transport assignment on record" if none exists. See `transport.md`. |
| Fees | `StudentTabFees.vue` fetches `GET /fee-management/students/{id}/due` (live `FeeCalculator` breakdown: total fee / discount / paid / due, per fee-head structure) plus `GET /fee-management/payments?student_id={id}` (real payment history with receipt no., mode, status). See `fee-management.md`. |
| Attendance | `StudentTabAttendance.vue` fetches `GET /attendance/student/{id}/history` (new endpoint — every marked day for this student, newest first, plus a present/absent/leave/late/half-day summary with a computed percentage). See `attendance.md`. |
| Exam | `StudentTabExam.vue` fetches `GET /exams/students/{id}/results` (new endpoint — `ExamResultCalculator::forStudent()`, one row per exam the student's class has schedules for: obtained/max marks, percentage, grade, class rank, pass/fail). See `exam-management.md`. |
| Library | `StudentTabLibrary.vue` fetches `GET /library/issues?student_id={id}` (real book issue/return history with overdue flag). See `library.md`. |

The Transport/Fees/Attendance/Exam/Library tabs each `watch(() => props.student.id, load, { immediate: true })` — refetching independently when a different student is opened, rather than being pre-loaded by the Students list endpoint (keeping that endpoint's payload from ballooning across five unrelated modules).

**Toolbar buttons — real vs. decorative**: Of the list-page toolbar, only **Add Student** (opens the real create form) is real. **Import**, **Export**, **Print**, **ID Card**, **Certificate**, **Transfer**, **Promote** all call the same `simulate(msg)` helper, which does nothing but `pushToast(`${msg} — demo simulation.`, 'info')` — no HTTP request is made. The per-row `print`/`idcard`/`certificate` actions in the data table fall through to the same pattern (`onRowAction`'s default branch: `pushToast('... — demo action ...')`). Only `view`, `edit`, and `delete` row actions are real. (Real Excel **Import** does exist for Students, but it lives on a separate screen — Import & Export > Student Import — not behind this toolbar button; see `student-import-management.md`.)

**Import & Export**: The toolbar's Import/Export buttons on this page are decorative (see above). A real, extensive Student Excel importer exists at Import & Export > Student Import — fully documented separately in **[student-import-management.md](student-import-management.md)**.

**Reports**: Reports > Student Reports (`reports.md`, `StudentReportController`) is the dedicated reporting view over Student data; Attendance Reports (this file, §Attendance in `attendance.md`) reports on Student attendance history.

**APIs**:
| Method | Path | Purpose |
|---|---|---|
| GET | `/erp/api/people/students` | List, eager-loaded with `schoolClass:id,name`, `section:id,name,school_class_id`, `father:id,name,phone`, `mother:id,name,phone`, `guardian:id,name,phone`; ordered by `admission_date` desc. |
| POST | `/erp/api/people/students` | Create. |
| PUT | `/erp/api/people/students/{student}` | Update. |
| DELETE | `/erp/api/people/students/{student}` | Hard delete. |

Example — create request/response:
```
POST /erp/api/people/students
{
  "admission_no": "ADM-2026-0142",
  "roll_no": 12,
  "name": "Ananya Sharma",
  "school_class_id": 5,
  "section_id": 14,
  "gender": "Female",
  "status": "Active",
  "mobile": "9876543210",
  "father_id": 33,
  "mother_id": null,
  "guardian_id": null
}
→ 201
{
  "id": 481, "admission_no": "ADM-2026-0142", "roll_no": 12, "name": "Ananya Sharma",
  "school_class_id": 5, "section_id": 14, "father_id": 33, "mother_id": null, "guardian_id": null,
  "gender": "Female", "status": "Active", "mobile": "9876543210", "nationality": "Indian",
  "school_class": { "id": 5, "name": "5" }, "section": { "id": 14, "name": "A", "school_class_id": 5 },
  "father": { "id": 33, "name": "Rakesh Sharma", "phone": "9876500000" }, "mother": null, "guardian": null,
  "created_at": "...", "updated_at": "..."
}
```

**Database Tables**:
`students` — PK `id`. FKs: `school_class_id` → `school_classes.id` (restrictOnDelete — a class cannot be deleted while students reference it), `section_id` → `sections.id` (nullOnDelete), `father_id`/`mother_id`/`guardian_id` → `parents.id` (all nullOnDelete). Unique: `admission_no`. No explicit secondary index beyond the FKs' implicit indexes.

`student_additional_details` (see below) — PK `id`. FK `student_id` → `students.id`, **unique** (one-to-one), `cascadeOnDelete`.

**Student's `additionalDetail` (StudentAdditionalDetail) — real data, now editable from both the manual form and the Excel importer**: `Student::additionalDetail()` is a `hasOne(StudentAdditionalDetail::class)`. Table `student_additional_details` (migrations `2026_07_31_105054_create_student_additional_details_table.php` and `2026_07_31_140233_add_caste_and_fees_balance_to_student_additional_details_table.php`) holds every field listed in the "Every Field (student_additional_details table)" table above, including `caste` and `fees_balance` — **both are listed in `StudentAdditionalDetail::$fillable` and validated by `StudentController::validatedDetail()`**, so they are mass-assignable and settable via direct API call; they are simply never exposed in `Students.vue`'s form (see the exclusion note above) and never mapped by the Excel importer (`StudentImportController::EXCLUDED_HEADERS`, see `student-import-management.md`) — a deliberate product decision, not a code gap. This row is populated by both the manual Add/Edit form (`StudentController::saveDetail()`, non-destructive upsert — see Edit Flow above) and the Excel importer (`StudentImportController::upsertAdditionalDetail()`, same non-destructive-merge philosophy — see `student-import-management.md`).

**File Storage**: None. The list column labelled "Photo" (`columns` array, `{ key: 'photo', type: 'photo' }` in `Students.vue`) always renders `photo: null` in `tableRows` — there is no `photo` column on the `students` table, no file input anywhere in the Add/Edit form, and `DataTable.vue`'s `photo` column type only ever renders a generic initials avatar placeholder, never an uploaded image. No student photo upload capability exists anywhere in this module, including the Excel importer.

**Background Jobs**: None.

**Notifications**: None.

---

### 3.2 Parents

**Name**: Parents · **Frontend URL**: `/people/parents` · **Backend**: `GET/POST /erp/api/people/parents`, `PUT/DELETE /erp/api/people/parents/{parent}` · **Controller**: `App\Http\Controllers\Erp\People\ParentController` · **Vue**: `pages/people/Parents.vue`.

**Permissions**: `index` open to any authenticated user; `store`/`update`/`destroy` require `people.manage`.

**Validation** (identical rule set on store and update):
```
'name' => 'required|string|max:255',
'phone' => 'nullable|string|max:30',
'email' => 'nullable|email|max:255',
'occupation' => 'nullable|string|max:255',
'qualification' => 'nullable|string|max:255',
'dob' => 'nullable|date',
'address' => 'nullable|string|max:255',
```
`qualification` and `dob` (added by migration `2026_07_31_105053_add_qualification_and_dob_to_parents_table.php`) are now validated, submitted by the form, and rendered in `Parents.vue` — previously dead columns from this page's perspective, now fully wired.

**Form Layout** (SlideOver "Add/Edit Parent"): Name, Phone, Email, Occupation, [Qualification | DOB], Address.

**Every Field**:

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown | FK |
|---|---|---|---|---|---|---|---|---|
| Name | `name` | parents | string | Yes | max:255 | — | — | — |
| Phone | `phone` | parents | string | No | max:30 | NULL | — | — |
| Email | `email` | parents | string | No | email, max:255 | NULL | — | — |
| Occupation | `occupation` | parents | string | No | max:255 | NULL | — | — |
| Qualification | `qualification` | parents | string | No | max:255 | NULL | — | — |
| DOB | `dob` | parents | date | No | date | NULL | — | — |
| Address | `address` | parents | string | No | max:255 | NULL | — | — |

**Database Relationships** (`app/Models/ParentGuardian.php`, table `parents` — named `ParentGuardian` because `Parent` is a PHP reserved word): `studentsAsFather()`, `studentsAsMother()`, `studentsAsGuardian()` — three separate `hasMany(Student::class, '*_id')` relations, one per role a parent record can play for a given Student.

**Create Flow**: single insert into `parents`.

**Edit Flow**: full update of the same 5 fields.

**Delete Flow**: hard delete (`$parent->delete()`, no `SoftDeletes`). `students.father_id`/`mother_id`/`guardian_id` are `nullOnDelete`, so deleting a Parent silently detaches them from any Students that referenced them (their name simply becomes blank on those Students' records) rather than being blocked or cascading.

**Business Rules**: A single `ParentGuardian` row can be reused as father, mother, and/or guardian across multiple different Student records simultaneously (no uniqueness constraint on name/phone/email). The list page's "Linked to Children" / "Unlinked" stat cards are computed from `children_count`, itself computed server-side in `ParentController::index()` as `studentsAsFather_count + studentsAsMother_count + studentsAsGuardian_count` via `withCount()`.

**Import & Export**: No.

**Reports**: None dedicated; Parents surface only as `father`/`mother`/`guardian` sub-objects inside Student-related reports (Reports > Student Reports).

**APIs**:
| Method | Path | Purpose |
|---|---|---|
| GET | `/erp/api/people/parents` | List with `children_count` computed per row, ordered by name. |
| POST | `/erp/api/people/parents` | Create. |
| PUT | `/erp/api/people/parents/{parent}` | Update. |
| DELETE | `/erp/api/people/parents/{parent}` | Hard delete. |

Example:
```
GET /erp/api/people/parents
→ 200
[
  { "id": 33, "name": "Rakesh Sharma", "phone": "9876500000", "email": null, "occupation": "Engineer",
    "address": null, "students_as_father_count": 2, "students_as_mother_count": 0,
    "students_as_guardian_count": 0, "children_count": 2 }
]
```

**Database Tables**: `parents` — PK `id`. No FKs (parent of the relationship, not a child). No unique constraints beyond `id`.

**File Storage**: None. **Background Jobs**: None. **Notifications**: None.

---

### 3.3 Teachers

**Name**: Teachers · **Frontend URL**: `/people/teachers` · **Backend**: `GET/POST /erp/api/people/teachers`, `PUT/DELETE /erp/api/people/teachers/{teacher}` · **Controller**: `App\Http\Controllers\Erp\People\TeacherController` · **Vue**: `pages/people/Teachers.vue`.

**Permissions**: `index` open to any authenticated user; `store`/`update`/`destroy` require `people.manage`.

**Validation** — store:
```
'employee_id' => 'required|string|max:50|unique:teachers,employee_id',
'name' => 'required|string|max:255',
'phone' => 'nullable|string|max:30',
'email' => 'nullable|email|max:255',
'school_class_id' => 'nullable|exists:school_classes,id',
'status' => 'required|in:active,inactive',
```
update (identical except the unique rule ignores the current row):
```
'employee_id' => ['required', 'string', 'max:50', Rule::unique('teachers', 'employee_id')->ignore($teacher->id)],
'name' => 'required|string|max:255',
'phone' => 'nullable|string|max:30',
'email' => 'nullable|email|max:255',
'school_class_id' => 'nullable|exists:school_classes,id',
'status' => 'required|in:active,inactive',
```

**Form Layout**: Employee ID, Name, Class (optional), Phone, Email, Status.

**Every Field**:

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown | FK |
|---|---|---|---|---|---|---|---|---|
| Employee ID | `employee_id` | teachers | string | Yes | unique, max:50 | — | — | — |
| Name | `name` | teachers | string | Yes | max:255 | — | — | — |
| Class | `school_class_id` | teachers | FK bigint, nullable | No | exists:school_classes,id | NULL | `GET /academics/classes` | `school_classes.id` (nullOnDelete) |
| Phone | `phone` | teachers | string | No | max:30 | NULL | — | — |
| Email | `email` | teachers | string | No | email, max:255 | NULL | — | — |
| Status | `status` | teachers | enum(`active`,`inactive`) | Yes | in:active,inactive | `active` (migration default) | static | — |

**Database Relationships** (`app/Models/Teacher.php`): `schoolClass()` belongsTo `SchoolClass`. Via `HasAttendance`: `attendances()`/`leaveRequests()` morphMany.

**Create/Edit Flow**: single insert/update into `teachers`, no side effects. **Delete Flow**: hard delete; `school_class_id` is `nullOnDelete` on the `school_classes` side, so no restriction from this table when a class is deleted (the Teacher's `school_class_id` is simply nulled).

**Business Rules**: `employee_id` unique across all teachers (a separate namespace from Staff's and Driver's `employee_id`, which live in their own tables). A Teacher's "Class" here is a single optional home-room/class-teacher association, not the class-subject timetable mapping (that's Academics > Class Teachers / Subject Mapping).

**Import & Export**: No. **Reports**: Attendance Reports (type=teacher). **APIs**: GET/POST `/erp/api/people/teachers`, PUT/DELETE `.../{teacher}` — same shape pattern as Students, eager-loading `schoolClass:id,name` on list. **Database Tables**: `teachers` — PK `id`, FK `school_class_id` → `school_classes.id` (nullOnDelete), unique `employee_id`. **File Storage / Background Jobs / Notifications**: None.

---

### 3.4 Staff

**Name**: Staff · **Frontend URL**: `/people/staff` · **Backend**: `GET/POST /erp/api/people/staff`, `PUT/DELETE /erp/api/people/staff/{staffMember}` · **Controller**: `App\Http\Controllers\Erp\People\StaffController` · **Vue**: `pages/people/Staff.vue`.

**Permissions**: `index` open; writes require `people.manage`.

**Validation** — store: `'employee_id' => 'required|string|max:50|unique:staff,employee_id'`, `'name' => 'required|string|max:255'`, `'department' => 'nullable|string|max:255'`, `'phone' => 'nullable|string|max:30'`, `'email' => 'nullable|email|max:255'`, `'status' => 'required|in:active,inactive'`. Update: identical, with `Rule::unique('staff', 'employee_id')->ignore($staffMember->id)`.

**Form Layout**: Employee ID, Name, Department, Phone, Email, Status.

**Every Field**:

| Label | DB Column | Table | Type | Required | Validation | Default | FK |
|---|---|---|---|---|---|---|---|
| Employee ID | `employee_id` | staff | string | Yes | unique, max:50 | — | — |
| Name | `name` | staff | string | Yes | max:255 | — | — |
| Department | `department` | staff | string | No | max:255 | NULL | — |
| Phone | `phone` | staff | string | No | max:30 | NULL | — |
| Email | `email` | staff | string | No | email, max:255 | NULL | — |
| Status | `status` | staff | enum | Yes | in:active,inactive | `active` | — |

**Database Relationships** (`app/Models/Staff.php`, `protected $table = 'staff'`): no belongsTo relations (free-text `department`, not a FK to any department table). Via `HasAttendance`: `attendances()`/`leaveRequests()` morphMany.

**Create/Edit/Delete Flow**: plain single-table insert/update; hard delete, no FKs to worry about on this table.

**Business Rules**: `employee_id` unique within `staff` only (independent namespace from teachers/drivers). `department` is freeform text, not linked to Academics or Finance & Payroll's Expense Categories.

**Import & Export**: No. **Reports**: Attendance Reports (type=staff), Finance & Payroll salary reports reference Staff by the same polymorphic pattern. **APIs**: GET/POST `/erp/api/people/staff`, PUT/DELETE `.../{staffMember}`. **Database Tables**: `staff` — PK `id`, unique `employee_id`, no FKs. **File Storage / Background Jobs / Notifications**: None.

---

### 3.5 Drivers

**Name**: Drivers · **Frontend URL**: `/people/drivers` · **Backend**: `GET/POST /erp/api/people/drivers`, `PUT/DELETE /erp/api/people/drivers/{driver}` · **Controller**: `App\Http\Controllers\Erp\People\DriverController` · **Vue**: `pages/people/Drivers.vue`.

**Permissions**: `index` open; writes require `people.manage`.

**Validation** — store: `'employee_id' => 'required|string|max:50|unique:drivers,employee_id'`, `'name' => 'required|string|max:255'`, `'phone' => 'nullable|string|max:30'`, `'license_no' => 'nullable|string|max:100'`, `'vehicle_no' => 'nullable|string|max:50'`, `'status' => 'required|in:active,inactive'`. Update: identical with `ignore($driver->id)` on the unique rule.

**Form Layout**: Employee ID, Name, Phone, License No, Vehicle No, Status.

**Every Field**:

| Label | DB Column | Table | Type | Required | Validation | Default | FK |
|---|---|---|---|---|---|---|---|
| Employee ID | `employee_id` | drivers | string | Yes | unique, max:50 | — | — |
| Name | `name` | drivers | string | Yes | max:255 | — | — |
| Phone | `phone` | drivers | string | No | max:30 | NULL | — |
| License No | `license_no` | drivers | string | No | max:100 | NULL | — |
| Vehicle No | `vehicle_no` | drivers | string | No | max:50 | NULL | — |
| Status | `status` | drivers | enum | Yes | in:active,inactive | `active` | — |

**Database Relationships** (`app/Models/Driver.php`): no belongsTo relations — `vehicle_no` is a free-text string, **not** a foreign key into Transport Management's `vehicles` table (the two modules track vehicle identity independently). Via `HasAttendance`: `attendances()`/`leaveRequests()` morphMany.

**Create/Edit/Delete Flow**: plain single-table insert/update; hard delete, no FKs.

**Business Rules**: `employee_id` unique within `drivers` only. No validation ties `vehicle_no` to an actual Transport Management vehicle record — it is purely descriptive text on this page.

**Import & Export**: No. **Reports**: Attendance Reports (type=driver). **APIs**: GET/POST `/erp/api/people/drivers`, PUT/DELETE `.../{driver}`. **Database Tables**: `drivers` — PK `id`, unique `employee_id`, no FKs. **File Storage / Background Jobs / Notifications**: None.

---

### 3.6 Users

**Name**: Users · **Frontend URL**: `/people/users` · **Backend**: `GET/POST /erp/api/people/users`, `PUT/DELETE /erp/api/people/users/{user}` · **Controller**: `App\Http\Controllers\Erp\People\UserController` · **Vue**: `pages/people/Users.vue`.

**Users vs. other People entities**: `ErpUser` (table `erp_users`) is the **login-account** entity for the `erp` guard (`app/Models/ErpUser.php extends Authenticatable`) — it is what a person types their email/password into to get into the ERP at all, and it is distinct and unrelated (no FK) to `Student`/`Teacher`/`Staff`/`Driver`/`ParentGuardian`. A school employee who is both a Teacher and needs ERP access would need one row in `teachers` and a separate row in `erp_users`; nothing links them. The currently-logged-in user's own self-service profile, password change, and theme are handled by the **Account** module (see `account.md`) — this People > Users page is the *admin* screen for managing *other* users' accounts, not the self-service one.

**Permissions**: `index` open to any authenticated user (read-only for non-admins — see below). `store`/`update`/`destroy` require `people.manage` **and**, on top of that, `UserController::authorizeAdmin()` aborts with 403 ("Only admins can manage user accounts.") unless `Auth::guard('erp')->user()->role === 'admin'` — a hard-coded second gate baked into the controller itself, not expressible via the `erp.permission:` middleware alone. The Vue page also hides the "+ Add User" button and the Edit/Delete columns entirely for non-admins (`isAdmin = erpStore.user.role === 'admin'`), showing a note "Only admins can create, edit, or remove user accounts. You have read-only access here." instead.

**Validation** — store:
```
'name' => 'required|string|max:255',
'email' => 'required|email|max:255|unique:erp_users,email',
'password' => 'required|string|min:6',
'role' => ['required', Rule::in($this->roleSlugs())],
'is_active' => 'boolean',
```
update:
```
'name' => 'required|string|max:255',
'email' => ['required', 'email', 'max:255', Rule::unique('erp_users', 'email')->ignore($user->id)],
'password' => 'nullable|string|min:6',
'role' => ['required', Rule::in($this->roleSlugs())],
'is_active' => 'boolean',
```
On update, if `password` is empty it is `unset()` from `$data` before `update()`, so the existing hash is preserved. `roleSlugs()` = `ErpRole::pluck('slug')->push('admin', 'staff')->unique()->values()->all()` — i.e. any role slug configured under Settings > Roles & Permissions, plus the two hardcoded built-ins `admin`/`staff` that always exist even if no custom `ErpRole` rows do.

**Delete guard**: `destroy()` refuses (422, "You cannot delete your own account.") if `$user->id === Auth::guard('erp')->id()` — an admin cannot delete themselves through this screen.

**Form Layout**: Name, Email, Password (label switches to "New Password (leave blank to keep current)" when editing), Role (dropdown of `roleSlugs()`), Active (checkbox).

**Every Field**:

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown | FK |
|---|---|---|---|---|---|---|---|---|
| Name | `name` | erp_users | string | Yes | max:255 | — | — | — |
| Email | `email` | erp_users | string | Yes | email, unique, max:255 | — | — | — |
| Password | `password` | erp_users | string (hashed cast) | Create: Yes / Edit: No | min:6 | — | — | — |
| Role | `role` | erp_users | string (slug, not a real FK) | Yes | must be in `roleSlugs()` | `staff` (migration default) | `GET /settings/roles` + hardcoded admin/staff | none — free-text slug, resolved at runtime against `erp_roles.slug` |
| Active | `is_active` | erp_users | boolean | — | boolean | `true` | checkbox | — |
| (unused by this form) Theme Color | `theme_color` | erp_users | string, nullable | No | — | NULL | — | — |

**Database Relationships**: `ErpUser` itself has no Eloquent relations defined; it is referenced *from* other tables instead — `attendances.marked_by_id`, `leave_requests.approved_by_id` (both `nullOnDelete`), and every `audit_logs.performed_by_id` row (see README's audit-logging section).

**Create Flow**: single insert into `erp_users`; password is hashed automatically by the model's `'password' => 'hashed'` cast.

**Edit Flow**: full update; password only changed if a new one was typed (see above).

**Delete Flow**: hard delete (`$user->delete()`), blocked only for self-deletion as noted above. No FK cascade concerns since nothing hard-references `erp_users.id` (all references are `nullOnDelete`).

**Business Rules**: `role` on `erp_users` is a plain string slug, **not a foreign key** — per README's permission-model section, `CheckErpPermission` middleware looks it up against `erp_roles.slug` at request time. The special value `admin` always bypasses all permission checks (`CheckErpPermission::handle()`: `if ($user->role === 'admin') return $next($request);`) regardless of whether an `ErpRole` row named "admin" exists.

**Import & Export**: No. **Reports**: None dedicated (System > Login History / Audit Logs report on `erp_users` activity but are a different module — see `system.md`). **APIs**: GET/POST `/erp/api/people/users`, PUT/DELETE `.../{user}`, all returning `only(['id','name','email','role','is_active'])` (password/timestamps never echoed back). Example:
```
POST /erp/api/people/users
{ "name": "Priya Verma", "email": "priya@school.test", "password": "secret1", "role": "staff", "is_active": true }
→ 201
{ "id": 9, "name": "Priya Verma", "email": "priya@school.test", "role": "staff", "is_active": true }
```
**Database Tables**: `erp_users` — PK `id`, unique `email`, no FKs (a parent table for other modules). **File Storage**: None. **Background Jobs**: None. **Notifications**: None (no welcome email is sent on creation).

---

### 3.7 Visitor Records

**Name**: Visitor Records · **Frontend URL**: `/people/visitor-records` · **Backend**: `GET/POST /erp/api/people/visitors`, `POST /erp/api/people/visitors/{visitor}/check-out`, `DELETE /erp/api/people/visitors/{visitor}` · **Controller**: `App\Http\Controllers\Erp\People\VisitorController` · **Vue**: `pages/people/VisitorRecords.vue`.

**Permissions**: `index`, `store` (check-in) and `checkOut` are **not** gated by `people.manage` — they sit above that middleware group in `routes/erp_api.php` (comment: "Visitor check-in (store) and check-out are both routine front-desk actions, not gated behind people.manage the way edits/deletes of core records are"), so any authenticated `erp` user can check a visitor in or out. Only `destroy` requires `people.manage`.

**Validation** — store: `'name' => 'required|string|max:255'`, `'phone' => 'nullable|string|max:30'`, `'purpose' => 'nullable|string|max:255'`, `'whom_to_meet' => 'nullable|string|max:255'`. `checkOut` takes no body — it 422s with "Visitor has already checked out." if the visitor's `status` is already `checked_out`.

**Form Layout** ("Check In Visitor" SlideOver): Visitor Name, Phone, Purpose, Whom to Meet. There is no create form for check-out — that's a one-click row action.

**Every Field**:

| Label | DB Column | Table | Type | Required | Validation | Default / Source |
|---|---|---|---|---|---|---|
| Visitor Name | `name` | visitors | string | Yes | max:255 | — |
| Phone | `phone` | visitors | string | No | max:30 | NULL |
| Purpose | `purpose` | visitors | string | No | max:255 | NULL |
| Whom to Meet | `whom_to_meet` | visitors | string | No | max:255 | NULL |
| (server-generated) Gate Pass No | `gate_pass_no` | visitors | string, unique | — | — | `GP-{Ymd}-{seq}`, see below |
| (server-generated) Check In At | `check_in_at` | visitors | datetime | — | — | `now()` at check-in |
| (server-generated) Check Out At | `check_out_at` | visitors | datetime, nullable | — | — | `now()` at check-out |
| (server-generated) Status | `status` | visitors | enum(`checked_in`,`checked_out`) | — | — | `checked_in` (migration default), then flipped by `checkOut()` |

**Database Relationships**: None — `Visitor` is a standalone model with no FKs in either direction.

**Create Flow (check-in)**: `VisitorController::store()` computes `gate_pass_no` as `'GP-' . now()->format('ymd') . '-' . str_pad((string) (Visitor::whereDate('created_at', now())->count() + 1), 3, '0', STR_PAD_LEFT)` — i.e. `GP-{yymmdd}-{3-digit sequence within today}` — then sets `check_in_at = now()` and `status = 'checked_in'`, and inserts.

**Edit Flow**: There is no `update` route/method for Visitor — a visitor's only state transition is the dedicated **check-out** action (`POST /people/visitors/{visitor}/check-out`), which sets `status = 'checked_out'` and `check_out_at = now()`. None of the original fields (name, phone, purpose, whom_to_meet) can be edited after check-in.

**Delete Flow**: hard delete, no FK cascade concerns (no child tables reference `visitors`).

**Business Rules**: The gate-pass sequence number resets daily (`whereDate('created_at', now())`), so `GP-260731-001` and `GP-260801-001` can coexist as distinct, valid gate passes — there's a theoretical race condition (two simultaneous check-ins on the same day could compute the same sequence number before either insert commits) since the counting/insert isn't wrapped in a transaction or protected by a unique constraint on the sequence portion, only on the full `gate_pass_no` string. Checking out an already-checked-out visitor is rejected with a 422.

**Import & Export**: No. **Reports**: None dedicated. **APIs**:
| Method | Path | Notes |
|---|---|---|
| GET | `/erp/api/people/visitors` | List, ordered by `check_in_at` desc. |
| POST | `/erp/api/people/visitors` | Check in. |
| POST | `/erp/api/people/visitors/{visitor}/check-out` | Check out. |
| DELETE | `/erp/api/people/visitors/{visitor}` | Hard delete (requires `people.manage`). |

Example:
```
POST /erp/api/people/visitors
{ "name": "Suresh Kumar", "phone": "9900011122", "purpose": "Admission enquiry", "whom_to_meet": "Principal" }
→ 201
{ "id": 58, "gate_pass_no": "GP-260731-004", "name": "Suresh Kumar", "phone": "9900011122",
  "purpose": "Admission enquiry", "whom_to_meet": "Principal", "check_in_at": "2026-07-31T10:15:00.000000Z",
  "check_out_at": null, "status": "checked_in" }
```
**Database Tables**: `visitors` — PK `id`, unique `gate_pass_no`, no FKs. **File Storage**: None (no photo/ID capture). **Background Jobs**: None. **Notifications**: None.

---

### 3.8 Generic (placeholder) leaves

| Leaf | Path | Reason |
|---|---|---|
| Alumni | `/people/alumni` | No controller/model/table; `GenericModule.vue` mock fallback. |
| UDISE+ | `/people/udise-plus` | No controller/model/table for India's UDISE+ government student-data reporting; `GenericModule.vue` mock fallback. |
| User Reports | `/people/user-reports` | No dedicated report controller/route; `GenericModule.vue` mock fallback. |

## 18. Module Dependencies

People is upstream of nearly every other module:
- **Academics**: Students reference `school_classes`/`sections`; Teachers optionally reference `school_classes`.
- **Attendance** (`attendance.md`): depends on all four "attendable" person types (Student/Teacher/Staff/Driver) existing here first; marks them present/absent and processes their leave requests.
- **Fee Management, Exam Management, Transport Management, Hostel, Library**: all store a `student_id` foreign key back to `students`.
- **Finance & Payroll**: Salary Structures/Slips reference Teacher/Staff/Driver via the same 4-type polymorphic pattern documented in `attendance.md` and README's "Polymorphic relationships" section.
- **Admissions**: feeds new Student records into this module once an enquiry converts.
- **Account** (`account.md`): the logged-in ERP user's own profile/password/theme/sessions are self-service and separate from the admin-only People > Users screen documented here.
- **System > Audit Logs**: every create/update/delete across every People model is captured automatically by the global audit listener (README's "Audit logging" section) — no People controller writes audit rows itself.

## 19. Screenshots

Not available — generated from source code.

## 20. Final Deliverable

This document covers all 7 real People pages (Users, Students, Parents, Teachers, Staff, Drivers, Visitor Records) end-to-end — routes, controllers, verbatim validation rules, form layout, every field with its DB column/type/FK, Eloquent relationships, create/edit/delete flows (all hard deletes, no `SoftDeletes` anywhere in this module), business rules, API examples, and full table/FK/unique documentation — plus the 3 Generic placeholder leaves (Alumni, UDISE+, User Reports) with their fallback reason. Special attention was given to the Students page's Add/Edit form (now ~50 fields across 7 sections spanning both `students` and `student_additional_details`, up from an earlier 11-field revision) and its `saveDetail()` non-destructive-merge upsert logic; to all 9 detail tabs now being wired to real per-student data (Personal/Parents/Health/Documents render from the already-loaded Student record, Transport/Fees/Attendance/Exam/Library each fetch their own module's data independently — none remain hardcoded); to the toolbar's real-vs-decorative split (only 1 of 8 toolbar buttons — Add Student — is real, the rest are `pushToast` simulations); to the two deliberately-excluded `student_additional_details` columns (`caste` — sensitive data; `fees_balance` — would go stale against the live `FeeCalculator`); to the fact that no student photo upload exists anywhere despite the "Photo" table column; and to Users' dual permission gate (`people.manage` middleware plus an admin-only check hard-coded in the controller). Full Student Excel import/export field mapping is intentionally deferred to `student-import-management.md` per the module boundary set for this document.
