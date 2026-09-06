# Admissions Module

See [README.md](README.md) for cross-cutting architecture (routing, permission model, generic-page mechanism, audit logging, morph maps, calculator-service pattern) — not repeated here.

## 1. Module Overview

**Name**: Admissions (sidebar group `Admissions`, icon 📝).

**Purpose**: Tracks the prospective-student pipeline from first enquiry through follow-up, registration, and final admission — at which point the enquiry is converted into a real `Student` record consumed by every other module.

**Main workflow**: A front-desk user logs an `AdmissionEnquiry` (student/parent details, class applying for, source). They log `AdmissionFollowUp` notes over time and move the enquiry through a five-stage pipeline (`enquiry → follow_up → registered → admitted` or `rejected`) via the stage buttons in the enquiry detail drawer. Moving an enquiry to `admitted` triggers `AdmissionEnquiryController::updateStage()`'s auto-conversion: a `ParentGuardian` is found-or-created and a real `Student` row is inserted, permanently linked back to the enquiry via `admission_enquiries.admitted_student_id`.

**Related modules**:
- **Academics** — every enquiry's `class_applying_for_id` is a required foreign key into `school_classes` (Academics module); the class survives the admit conversion onto the new `Student.school_class_id`.
- **People** — the auto-created `Student` and `ParentGuardian` (`parents` table) rows are the exact same records the People module's Students/Parents pages read and manage.
- **Reports** — `App\Services\AdmissionReportCalculator::summary()` computes the admission funnel (by stage, by source, conversion rate) live from `admission_enquiries`, surfaced at `/reports/admission-reports` in the Reports module.
- **Dashboard** — `App\Services\DashboardCalculator` counts open-pipeline enquiries (`enquiry`, `follow_up`, `registered` stages) for a Dashboard quick-stat card linking back to `/admissions/enquiry`.

## 2. Menu Structure

Sidebar group `Admissions`, children (from `resources/js/erp/data/menu.js`):

| Label | Path | Type |
|---|---|---|
| Enquiry | `/admissions/enquiry` | **Real** |
| Follow-up | `/admissions/follow-up` | **Real** |
| Registration | `/admissions/registration` | **Real** |
| Admission | `/admissions/admission` | **Real** |
| Admission Documents | `/admissions/admission-documents` | Generic |
| Admission Fee | `/admissions/admission-fee` | Generic |
| Admission Settings | `/admissions/admission-settings` | Generic |
| Admission Reports | `/admissions/admission-reports` | Generic |

### Generic pages — reason
`Admission Documents`, `Admission Fee`, `Admission Settings`, `Admission Reports` have no key in `FLAGSHIP_ROUTES`, so each falls through to `GenericModule.vue` with fake seeded data. Note that document tracking and fee tracking for an enquiry **do** exist as real functionality — but as fields/sub-panels inside the real Enquiry detail drawer (§4), not as these dedicated pages. Likewise a real admission report **does** exist, but under the Reports module at `/reports/admission-reports` (`AdmissionReports.vue`, backed by `AdmissionReportCalculator`), not at this module's own `/admissions/admission-reports` path.

### Important nuance: Enquiry / Registration / Admission are one component
`/admissions/enquiry`, `/admissions/registration`, and `/admissions/admission` all map to the exact same `resources/js/erp/pages/admissions/Admissions.vue` component in `FLAGSHIP_ROUTES`. The component only changes its page title/breadcrumb based on `route.path` (a local `pageTitle` computed: `{'/admissions/enquiry': 'Enquiry', '/admissions/registration': 'Registration', '/admissions/admission': 'Admission'}[route.path]`) — the underlying data grid is **not** filtered by stage on any of the three URLs; all three show every enquiry in the `admission_enquiries` table regardless of its current `stage`. There is no separate "Registration" or "Admission" table or endpoint.

## 3. Pages (Real)

### 3.1 Enquiry / Registration / Admission (shared component)

- **Frontend URL**: `/admissions/enquiry`, `/admissions/registration`, `/admissions/admission` → `resources/js/erp/pages/admissions/Admissions.vue` (see nuance above)
- **Backend URL**: `GET erp/api/admissions/enquiries`, `POST erp/api/admissions/enquiries`, `PUT erp/api/admissions/enquiries/{enquiry}`, `DELETE erp/api/admissions/enquiries/{enquiry}`, `PATCH erp/api/admissions/enquiries/{enquiry}/stage`. Also calls `GET erp/api/academics/classes` (Academics module) to populate the "Class Applying For" dropdown, and `GET erp/api/admissions/enquiries/{enquiry}/follow-ups` for the detail drawer's follow-up history.
- **Controller**: `App\Http\Controllers\Erp\Admissions\AdmissionEnquiryController`
  - `index()` — `AdmissionEnquiry::with('classApplyingFor:id,name')->orderByDesc('created_at')->get()`
  - `store(Request $request)` — validates via private `validated()`, generates `enquiry_no`, creates
  - `update(Request $request, AdmissionEnquiry $enquiry)` — same `validated()`, updates (used both by the Add-form's edit path and by the detail drawer's inline document/fee-toggle saves, which resend the full field set via a `pickBase()` helper on the frontend)
  - `destroy(AdmissionEnquiry $enquiry)` — hard delete
  - `updateStage(Request $request, AdmissionEnquiry $enquiry)` — moves the pipeline stage; auto-converts to `Student` when the target stage is `admitted` (see §7)
- **Validation** — `store`/`update` (private `validated()`, verbatim):
```php
$request->validate([
    'student_name' => 'required|string|max:255',
    'parent_name' => 'required|string|max:255',
    'phone' => 'required|string|max:30',
    'email' => 'nullable|email|max:255',
    'class_applying_for_id' => 'required|exists:school_classes,id',
    'gender' => 'nullable|in:Male,Female,Other',
    'dob' => 'nullable|date',
    'source' => 'nullable|string|max:100',
    'remarks' => 'nullable|string|max:1000',
    'fee_amount' => 'nullable|numeric|min:0',
    'fee_paid' => 'boolean',
    'documents_submitted' => 'array',
    'documents_submitted.*' => 'string',
]);
```
- **Validation** — `updateStage` (verbatim):
```php
$request->validate([
    'stage' => ['required', Rule::in(['enquiry', 'follow_up', 'registered', 'admitted', 'rejected'])],
]);
```
- **Permissions**: `index` (and the follow-ups `index`) open to any authenticated ERP user; `store`/`update`/`destroy`/`updateStage` require `erp.permission:admissions.manage`.

### 3.2 Follow-up

- **Frontend URL**: `/admissions/follow-up` → `resources/js/erp/pages/admissions/FollowUp.vue`
- **Backend URL**: `GET erp/api/admissions/enquiries` (client-side filtered — see below), `GET erp/api/admissions/enquiries/{enquiry}/follow-ups`, `POST erp/api/admissions/enquiries/{enquiry}/follow-ups`
- **Controller**: `App\Http\Controllers\Erp\Admissions\AdmissionFollowUpController`
  - `index(AdmissionEnquiry $enquiry)` — `$enquiry->followUps()->get()` (ordered newest-first via the model relationship's `orderByDesc('follow_up_date')`)
  - `store(Request $request, AdmissionEnquiry $enquiry)` — validates, defaults `follow_up_date` to today if omitted, creates the follow-up, and as a side effect updates the parent enquiry's `next_follow_up_date` and auto-advances `stage` from `enquiry` to `follow_up` (only if it was still at the initial `enquiry` stage — later stages are left untouched)
- **Validation** (verbatim):
```php
$request->validate([
    'note' => 'required|string|max:1000',
    'follow_up_date' => 'nullable|date',
    'next_follow_up_date' => 'nullable|date',
]);
```
- **Frontend list filtering** (not a backend query param): `FollowUp.vue`'s `load()` fetches **all** enquiries via `/admissions/enquiries` and then filters client-side: `data.filter((e) => !['admitted', 'rejected'].includes(e.stage))` — only open-pipeline enquiries are shown, with Overdue / Due Today / Upcoming stat cards computed from `next_follow_up_date` against the current date on the client.
- **Permissions**: `index` open; `store` requires `erp.permission:admissions.manage` (same gate as the parent Enquiry resource — there is no separate follow-up permission key).

## 4. Form Layout

- **Enquiry / Registration / Admission page** — two separate `SlideOver` drawers:
  1. **Add Enquiry** drawer: Student Name → Parent Name → (Phone, Email side-by-side) → Class Applying For (select, sourced from `/academics/classes`) → (Gender, Source side-by-side) → Remarks. Footer: Cancel / Save. (`stage`, `fee_amount`, `fee_paid`, `documents_submitted` are not present on this create form — they default server-side / are only editable after creation.)
  2. **Detail / Manage** drawer (opened via the 👁 row action) — several stacked sections on one enquiry: (a) read-only header (student name, class, parent, phone); (b) **Pipeline Stage** — a row of stage buttons (Enquiry/Follow-up/Registered/Admitted/Rejected) that immediately `PATCH .../stage` on click, plus a confirmation line once `admitted_student` is set; (c) **Documents Submitted** — a checkbox grid of four fixed document types (`Birth Certificate`, `Aadhar Card`, `Previous School TC`, `Passport Photo`) that auto-saves via `PUT` on every checkbox change; (d) **Admission Fee** — a numeric fee amount input plus a "Fee Paid" checkbox, both auto-saving via `PUT` on change; (e) **Follow-up History** — a scrollable list of past follow-ups plus an inline note/date form and a "+ Log Follow-up" button that posts to the follow-ups endpoint.
- **Follow-up page** — a single `SlideOver` drawer opened per enquiry via "Log Follow-up": scrollable follow-up history list, then a Note textarea and a Next Follow-up Date input. Footer: Cancel / Save Follow-up.

## 5. Fields

### 5.1 Admission Enquiry (table `admission_enquiries`)

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| *(not on form; server-generated)* | `enquiry_no` | admission_enquiries | `string`, **unique** | — | not user-supplied — `ENQ-{yy}-{0000}` generated server-side (see §10) | none | — | — |
| Student Name | `student_name` | admission_enquiries | `string` | Required | `required\|string\|max:255` | none | — | — |
| Parent Name | `parent_name` | admission_enquiries | `string` | Required | `required\|string\|max:255` | none | — | — |
| Phone | `phone` | admission_enquiries | `string` | Required | `required\|string\|max:30` | none | — | — |
| Email | `email` | admission_enquiries | `string` nullable | Optional | `nullable\|email\|max:255` | none | — | — |
| Class Applying For | `class_applying_for_id` | admission_enquiries | `foreignId` | Required | `required\|exists:school_classes,id` | none | `GET /academics/classes` (Academics module) | `school_classes.id`, `restrictOnDelete()` |
| Gender | `gender` | admission_enquiries | `enum('Male','Female','Other')` nullable | Optional | `nullable\|in:Male,Female,Other` | none | fixed 3-option `<select>` | — |
| DOB | `dob` | admission_enquiries | `date` nullable | Optional | `nullable\|date` | none | — | — |
| Source | `source` | admission_enquiries | `string` nullable | Optional | `nullable\|string\|max:100` | none | free text (placeholder suggests "Walk-in, Website…") | — |
| Stage | `stage` | admission_enquiries | `enum('enquiry','follow_up','registered','admitted','rejected')` | — (not on create form) | changed only via `updateStage`'s `required\|in:...` rule (route: `PATCH .../stage`) | `'enquiry'` (migration default) | fixed 5-button pipeline control | — |
| Remarks | `remarks` | admission_enquiries | `text` nullable | Optional | `nullable\|string\|max:1000` | none | — | — |
| Next Follow-up Date | `next_follow_up_date` | admission_enquiries | `date` nullable | — (not on create form) | not directly validated on the enquiry endpoints — set as a side effect of `AdmissionFollowUpController::store` | none | — | — |
| Admission Fee (₹) | `fee_amount` | admission_enquiries | `decimal(10,2)` nullable | Optional | `nullable\|numeric\|min:0` | none | — | — |
| Fee Paid | `fee_paid` | admission_enquiries | `boolean` | Optional | `boolean` | `false` (migration default) | checkbox | — |
| Documents Submitted | `documents_submitted` | admission_enquiries | `json` nullable, cast `array` | Optional | `array`, each item `string` | none | fixed 4-option checkbox list (hard-coded in the Vue component, not fetched from an API) | — |
| *(not on form; server-managed)* | `admitted_student_id` | admission_enquiries | `foreignId` nullable | — | not user-settable — set only inside `updateStage()`'s admit branch | `null` | — | `students.id`, `nullOnDelete()` |
| *(computed, not a column)* | `status` (`Pending`/`Confirmed`/`Rejected`) | — | model `$appends` accessor `getStatusAttribute()` | — | derived from `stage`: `admitted` → `Confirmed`, `rejected` → `Rejected`, anything else → `Pending` | — | — | — |

### 5.2 Admission Follow-up (table `admission_follow_ups`)

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| *(implicit — route-bound)* | `admission_enquiry_id` | admission_follow_ups | `foreignId` | Required | route parameter `{enquiry}`, not in the request body | none | — | `admission_enquiries.id`, `cascadeOnDelete()` |
| Note | `note` | admission_follow_ups | `text` | Required | `required\|string\|max:1000` | none | — | — |
| Follow-up Date | `follow_up_date` | admission_follow_ups | `date` | Optional in request | `nullable\|date` — defaults to `now()->toDateString()` server-side if omitted (`$data['follow_up_date'] ??= now()->toDateString();`) | none (migration column itself is non-nullable, but the controller always fills it) | — | — |
| Next Follow-up Date | `next_follow_up_date` | admission_follow_ups | `date` nullable | Optional | `nullable\|date` | none | — | — |

## 6. Database Relationships

- **`AdmissionEnquiry`** (`app/Models/AdmissionEnquiry.php`):
  - `classApplyingFor(): BelongsTo` → `SchoolClass` (FK `class_applying_for_id`)
  - `admittedStudent(): BelongsTo` → `Student` (FK `admitted_student_id`)
  - `followUps(): HasMany` → `AdmissionFollowUp`, ordered `->orderByDesc('follow_up_date')`
  - `$appends = ['status']` — the computed Pending/Confirmed/Rejected accessor is always included in JSON output
- **`AdmissionFollowUp`** (`app/Models/AdmissionFollowUp.php`):
  - `enquiry(): BelongsTo` → `AdmissionEnquiry` (FK `admission_enquiry_id`)
- **`ParentGuardian`** (`app/Models/ParentGuardian.php`, table `parents`) — relevant here only via `firstOrCreate` during the admit conversion; its own `studentsAsFather/Mother/Guardian` relationships belong to the People module.
- **`Student`** (`app/Models/Student.php`) — relevant here only as the auto-created target of the admit conversion; its own `schoolClass`, `section`, `father`, `mother`, `guardian`, `additionalDetail` relationships belong to the People module.

## 7. Create Flow

**Creating an enquiry** (`POST /admissions/enquiries`):
1. Validate the payload (§5.1).
2. Generate `enquiry_no` via `nextEnquiryNo()`: format `ENQ-{2-digit year}-{4-digit zero-padded sequence}`, sequence = count of existing rows whose `enquiry_no LIKE 'ENQ-{yy}-%'` + 1 (see §10 for the exact format and its edge cases).
3. Insert one `admission_enquiries` row (`stage` defaults to `'enquiry'` at the DB level).

**Logging a follow-up** (`POST /admissions/enquiries/{enquiry}/follow-ups`):
1. Validate (§5.2); default `follow_up_date` to today if not supplied.
2. Insert one `admission_follow_ups` row via `$enquiry->followUps()->create($data)`.
3. Side effect on the parent enquiry: `next_follow_up_date` is updated to the submitted `next_follow_up_date` (or left unchanged if none given); `stage` is advanced from `enquiry` to `follow_up` **only if** it was still `enquiry` (later stages are never regressed or altered by logging a follow-up).

**Admitting an enquiry — the auto-conversion side effect** (`PATCH /admissions/enquiries/{enquiry}/stage` with `stage: "admitted"`):
Executed inside a `DB::transaction()` in `AdmissionEnquiryController::updateStage()`, guarded by `! $enquiry->admitted_student_id` (idempotent — re-sending `admitted` on an already-admitted enquiry just falls through to the plain stage update, since the `if` condition is false):
1. `ParentGuardian::firstOrCreate(['phone' => $enquiry->phone], ['name' => $enquiry->parent_name])` — looked up **by phone number only**; if a parent with that phone already exists, it is reused as-is (its `name` is not overwritten from the enquiry even if different).
2. `Student::create([...])` — a new `students` row is inserted with:
   - `admission_no` — generated via `nextAdmissionNo()`: format `ADM-{4-digit year}-{4-digit zero-padded sequence}` (note: **4-digit** year, unlike the enquiry number's 2-digit year — see §10)
   - `name` ← `enquiry.student_name`
   - `school_class_id` ← `enquiry.class_applying_for_id`
   - `guardian_id` ← the `ParentGuardian` from step 1 (the student is linked only as `guardian_id`; `father_id`/`mother_id` are left `NULL` — the enquiry form has no separate father/mother capture)
   - `gender` ← `enquiry.gender`
   - `dob` ← `enquiry.dob`
   - `mobile` ← `enquiry.phone`
   - `email` ← `enquiry.email`
   - `status` ← `'Active'` (hard-coded)
   - `admission_date` ← `now()->toDateString()` (today, not the enquiry's creation date)
3. `$enquiry->update(['stage' => 'admitted', 'admitted_student_id' => $student->id])`.

If the target stage is anything other than `admitted` (or the enquiry was already admitted), the controller just does `$enquiry->update(['stage' => $data['stage']])` — a plain stage change with no other side effects.

## 8. Edit Flow

- `PUT /admissions/enquiries/{id}` re-validates and updates the same field set as create (§5.1) — this single endpoint is reused by three different frontend interactions: the (unused-in-this-component) generic edit path, the Documents-Submitted checkbox auto-save, and the Admission Fee auto-save — the frontend's `pickBase()` helper re-sends all the base fields alongside whichever one changed, since the backend does a full-field validation on every `update()` call (no partial-update support).
- `stage` and `admitted_student_id` are **never** editable through the plain `update()` endpoint — only through `PATCH .../stage`.
- No recalculation occurs on edit (no calculator service involved in this module).

## 9. Delete Flow

- **Hard delete** — no `SoftDeletes` trait anywhere in the app.
- **Enquiry**: `DELETE /admissions/enquiries/{id}` — `admission_follow_ups.admission_enquiry_id` is `cascadeOnDelete()`, so deleting an enquiry also deletes all of its logged follow-ups. If the enquiry had already been admitted (`admitted_student_id` set), the linked `Student` row is **not** deleted or affected — `admission_enquiries.admitted_student_id` uses `nullOnDelete()` on the *students* side (i.e., deleting the *student* would null this column on the enquiry), not the reverse; deleting the *enquiry* itself has no cascading effect on the student.
- **Follow-up**: there is no delete endpoint for an individual `AdmissionFollowUp` — the only way one disappears is via its parent enquiry's cascade delete.
- No backend restriction based on pipeline stage — the delete endpoint itself will remove an enquiry regardless of what stage it's in. The frontend now guards the row-delete button with a native `confirm()` dialog (`if (!confirm(...)) return;` in `Admissions.vue`, matching the same pattern already used by `SupportTickets.vue`/`ApiTokens.vue` in the Account module) before calling `DELETE` — previously this page deleted immediately on click with no confirmation step at all.

## 10. Business Rules

- **`enquiry_no` format**: `ENQ-{yy}-{0000}` — 2-digit year (`now()->format('y')`), 4-digit zero-padded sequential count of existing rows matching `ENQ-{yy}-%`, plus 1. This is a simple count-based sequence, **not** guarded against race conditions (two simultaneous `store()` calls could compute the same next number before either insert completes) and **not** guarded against gaps from deleted enquiries (deleting enquiry `ENQ-26-0003` does not free that number for reuse, but also does not prevent the count-based logic from re-issuing a duplicate-looking sequence if deletions shift the count — worth flagging to a new developer as a known race/ID-reuse risk, not a documented-safe pattern).
- **`admission_no` format**: `ADM-{YYYY}-{0000}` — note the **year format differs from `enquiry_no`** (4-digit year here vs. 2-digit for enquiries) — an inconsistency visible directly in `nextEnquiryNo()` vs `nextAdmissionNo()`.
- **Auto-conversion to Student is idempotent per enquiry**: guarded by `! $enquiry->admitted_student_id`, so re-triggering "admitted" on an already-converted enquiry will not create a second `Student`.
- **Guardian matching is phone-based, not name-based**: `ParentGuardian::firstOrCreate(['phone' => ...], ['name' => ...])` means two enquiries sharing the same parent phone number will resolve to the same `ParentGuardian` row (correct for siblings), but a typo'd phone number will silently create a duplicate guardian.
- **Follow-up auto-advances stage only from the initial stage**: logging a follow-up only flips `stage` from `enquiry` to `follow_up`; it never regresses a later stage (`registered`, `admitted`, `rejected`) back to `follow_up`.
- **Uniqueness**: `admission_enquiries.enquiry_no` has a DB `unique()` constraint (belt-and-braces alongside the sequence generator).
- **Class reference is `restrictOnDelete`**: an `AdmissionEnquiry`'s `class_applying_for_id` blocks deletion of the referenced `SchoolClass` while any enquiry (of any stage, including old rejected ones) still points at it — see [academics.md §9](academics.md).

## 11. Import & Export

No import or export exists for Enquiry, Follow-up, Registration, or Admission — none of these are among the README's 9 exportable entities (`student, fee, expense, salary, bank, fuel-log, route, marks, global`), and Student (the entity this module eventually creates) is the only entity with a real importer, which is unrelated to this module's own enquiry data.

## 12. Reports

- **`/reports/admission-reports`** (Reports module, `AdmissionReports.vue`, backed by `App\Http\Controllers\Erp\Reports\AdmissionReportController` → `App\Services\AdmissionReportCalculator::summary()`) computes, live from `admission_enquiries` on every request: `total_enquiries`, `by_stage` (grouped counts), `admitted`, `rejected`, `conversion_rate` (admitted ÷ total × 100, rounded to 1 decimal), `new_this_month` (created this calendar month), `by_source` (grouped by `source`, with blank source bucketed as `'Unknown'`).
- **Dashboard** — `App\Services\DashboardCalculator` includes a quick-stat entry: `{'label' => 'Admission Enquiries', 'count' => AdmissionEnquiry::whereIn('stage', ['enquiry', 'follow_up', 'registered'])->count(), 'to' => '/admissions/enquiry'}` on the main Dashboard page.

## 13. APIs

| Method | Path | Controller@method | Permission |
|---|---|---|---|
| GET | `erp/api/admissions/enquiries` | `AdmissionEnquiryController@index` | open |
| POST | `erp/api/admissions/enquiries` | `AdmissionEnquiryController@store` | `admissions.manage` |
| PUT | `erp/api/admissions/enquiries/{enquiry}` | `AdmissionEnquiryController@update` | `admissions.manage` |
| DELETE | `erp/api/admissions/enquiries/{enquiry}` | `AdmissionEnquiryController@destroy` | `admissions.manage` |
| PATCH | `erp/api/admissions/enquiries/{enquiry}/stage` | `AdmissionEnquiryController@updateStage` | `admissions.manage` |
| GET | `erp/api/admissions/enquiries/{enquiry}/follow-ups` | `AdmissionFollowUpController@index` | open |
| POST | `erp/api/admissions/enquiries/{enquiry}/follow-ups` | `AdmissionFollowUpController@store` | `admissions.manage` |

### Representative example — create an enquiry

`POST erp/api/admissions/enquiries`
```json
{
  "student_name": "Aarav Sharma",
  "parent_name": "Rohit Sharma",
  "phone": "9876543210",
  "email": "rohit.sharma@example.com",
  "class_applying_for_id": 3,
  "gender": "Male",
  "source": "Website",
  "remarks": "Interested in the science stream."
}
```
Response `201` (shape from `$enquiry->load('classApplyingFor:id,name')`):
```json
{
  "id": 42,
  "enquiry_no": "ENQ-26-0018",
  "student_name": "Aarav Sharma",
  "parent_name": "Rohit Sharma",
  "phone": "9876543210",
  "email": "rohit.sharma@example.com",
  "class_applying_for_id": 3,
  "gender": "Male",
  "dob": null,
  "source": "Website",
  "stage": "enquiry",
  "remarks": "Interested in the science stream.",
  "next_follow_up_date": null,
  "fee_amount": null,
  "fee_paid": false,
  "documents_submitted": null,
  "admitted_student_id": null,
  "status": "Pending",
  "class_applying_for": { "id": 3, "name": "10" },
  "created_at": "2026-07-31T12:00:00.000000Z",
  "updated_at": "2026-07-31T12:00:00.000000Z"
}
```

### Representative example — admit conversion

`PATCH erp/api/admissions/enquiries/42/stage`
```json
{ "stage": "admitted" }
```
Response `200` (shape from `$enquiry->load(['classApplyingFor:id,name', 'admittedStudent:id,admission_no,name'])`):
```json
{
  "id": 42,
  "enquiry_no": "ENQ-26-0018",
  "stage": "admitted",
  "status": "Confirmed",
  "admitted_student_id": 118,
  "class_applying_for": { "id": 3, "name": "10" },
  "admitted_student": { "id": 118, "admission_no": "ADM-2026-0037", "name": "Aarav Sharma" }
}
```

## 14. Database Tables

**`admission_enquiries`**
- PK: `id`. Unique: `enquiry_no`.
- FK: `class_applying_for_id` → `school_classes.id`, `restrictOnDelete()`. `admitted_student_id` → `students.id`, nullable, `nullOnDelete()`.
- No explicit secondary indexes beyond the PK/FK/unique.
- Columns: `enquiry_no` (unique), `student_name`, `parent_name`, `phone`, `email` nullable, `class_applying_for_id`, `gender` enum nullable, `dob` nullable, `source` nullable, `stage` enum (default `enquiry`), `remarks` text nullable, `next_follow_up_date` nullable, `fee_amount` decimal(10,2) nullable, `fee_paid` boolean (default `false`), `documents_submitted` json nullable, `admitted_student_id` nullable, timestamps.

**`admission_follow_ups`**
- PK: `id`. FK: `admission_enquiry_id` → `admission_enquiries.id`, `cascadeOnDelete()`.
- No unique constraints, no explicit secondary indexes.
- Columns: `admission_enquiry_id`, `note` text, `follow_up_date` date, `next_follow_up_date` date nullable, timestamps.

## 15. File Storage

None. The "Documents Submitted" field only stores a JSON array of fixed document-type *labels* (booleans of which were checked) — no actual file upload, storage path, or attachment exists anywhere in this module.

## 16. Background Jobs

None. No `Job` class references `AdmissionEnquiry` or `AdmissionFollowUp`, and no scheduled command (e.g. an automated overdue-follow-up reminder) exists — the Overdue/Due Today/Upcoming stat cards on the Follow-up page are computed client-side on every page load, not by any background process.

## 17. Notifications

None. No notification/mailable/SMS/WhatsApp is dispatched when an enquiry is created, a follow-up is logged, or an enquiry is admitted — despite Communication-module channels (SMS/Email/WhatsApp) existing elsewhere in the app, none are wired into this module's controllers.

## 18. Module Dependencies

- **Depends on**: Academics — `class_applying_for_id` requires an existing `SchoolClass` row (Admissions cannot function until at least one class exists).
- **Depended on by**: People — the auto-created `Student` and (`firstOrCreate`d) `ParentGuardian` rows are real records the People module's Students/Parents pages manage from that point forward; every downstream module that operates on students (Attendance, Fee Management, Exams, Transport, Hostel, Library, Documents) transitively depends on students that originated here. Reports (`/reports/admission-reports`) and Dashboard both read `admission_enquiries` directly for live metrics.

## 19. Screenshots

Not available — this documentation was generated from source code, not a running instance.

## 20. Final Deliverable Summary

Admissions runs a five-stage enquiry pipeline (`enquiry → follow_up → registered → admitted`/`rejected`) on a single `admission_enquiries` table plus a `hasMany` `admission_follow_ups` history log. Three sidebar entries (Enquiry, Registration, Admission) are the same `Admissions.vue` component and the same unfiltered backend list — only the page title differs by route. The pipeline's one significant piece of business logic is `AdmissionEnquiryController::updateStage()`'s admit-time auto-conversion: inside a DB transaction, it `firstOrCreate`s a `ParentGuardian` by phone and inserts a new `Student` row (class, guardian, gender, dob, contact info carried over; `status` hard-coded `Active`; `admission_date` set to today), then permanently links the enquiry to that student via `admitted_student_id`, guarded to be idempotent. Enquiry numbers (`ENQ-yy-0000`) and admission numbers (`ADM-YYYY-0000`) are both simple count-based sequences generated per-request with no race-condition guard, and notably use inconsistent year-digit formats. All writes require `erp.permission:admissions.manage`; deletes are hard deletes that cascade to follow-ups but never touch an already-created student. No import/export, file storage, background jobs, or notifications exist in this module; four of the eight sidebar entries remain unimplemented `GenericModule.vue` placeholders.
