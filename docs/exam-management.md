# Exam Management

See [README.md](README.md) for cross-cutting architecture (routing, permission model, generic-page mechanism, audit logging, calculator-service pattern, Import & Export infra) — not repeated here.

## 1. Module Overview

Exam Management covers the full exam lifecycle: defining exam types, scheduling exam papers per class/subject, auto-assigning exam-hall seats, entering marks, defining grade bands, and viewing computed results/reports/report cards/admit cards. It is backed by 7 real tables (`exam_types`, `exams`, `exam_schedules`, `grade_systems`, `marks`, `seat_plans`, `questions`) and 8 controllers under `app/Http/Controllers/Erp/Exams/`. Results, ranks and grades are never stored — they are computed live on every request by `app/Services/ExamResultCalculator.php` from `exam_schedules` + `marks` + `grade_systems`, per the README's "computed, not stored" pattern.

Of the 16 sidebar leaves under "Exam Management", 12 are real and 4 (Result Processing, Rank Generation, Exam Results, Report Cards) are cosmetic variants of one real page (`ExamResults.vue`), backed by one real endpoint. 3 leaves (Online Tests, Test Settings, Online Classes) are entirely generic/fake — there is no online-testing or online-class backend anywhere in this codebase.

## 2. Menu Structure

Source: `resources/js/erp/data/menu.js` (Exam Management group, 16 children) cross-checked against `FLAGSHIP_ROUTES` in `resources/js/erp/router/index.js`.

| # | Sidebar Label | Route path | Real / Generic | Component | Notes |
|---|---|---|---|---|---|
| 1 | Exams | `/exam-management/exams` | Real | `Exams.vue` | |
| 2 | Exam Types | `/exam-management/exam-types` | Real | `ExamTypes.vue` | |
| 3 | Exam Schedule | `/exam-management/exam-schedule` | Real | `ExamSchedule.vue` | |
| 4 | Seat Planning | `/exam-management/seat-planning` | Real | `SeatPlanning.vue` | generate-only, no plain store |
| 5 | Marks Management | `/exam-management/marks-management` | Real | `MarksEntry.vue` | bulk upsert |
| 6 | Grade System | `/exam-management/grade-system` | Real | `GradeSystem.vue` | |
| 7 | Result Processing | `/exam-management/result-processing` | Real (shared) | `ExamResults.vue` | same component/endpoint as #8–10, title only differs |
| 8 | Report Cards | `/exam-management/report-cards` | Real (shared) | `ExamResults.vue` | same component/endpoint as #7,9,10 |
| 9 | Rank Generation | `/exam-management/rank-generation` | Real (shared) | `ExamResults.vue` | same component/endpoint as #7,8,10 |
| 10 | Exam Results | `/exam-management/exam-results` | Real (shared) | `ExamResults.vue` | same component/endpoint as #7–9 |
| 11 | Admit Cards | `/exam-management/admit-cards` | Real | `AdmitCards.vue` | no PDF, `window.print()` only |
| 12 | Online Tests | *(none)* | **Generic** | `GenericModule.vue` | no online-test backend exists anywhere in the app |
| 13 | Question Bank | `/exam-management/question-bank` | Real | `QuestionBank.vue` | |
| 14 | Test Settings | *(none)* | **Generic** | `GenericModule.vue` | no test-settings backend exists |
| 15 | Online Classes | *(none)* | **Generic** | `GenericModule.vue` | no online-class/video backend exists |
| 16 | Exam Reports | `/exam-management/exam-reports` | Real | `ExamReports.vue` | |

**The 4-in-1 detail**: `FLAGSHIP_ROUTES` maps all four of `/exam-management/result-processing`, `/exam-management/rank-generation`, `/exam-management/exam-results`, and `/exam-management/report-cards` to the exact same imported component, `ExamResults.vue` (`resources/js/erp/router/index.js` lines ~167–170). Inside that component, a computed `pageTitle` looks up `route.path` in a small dictionary to change only the `<h1>` text and breadcrumb; every other part of the page — the exam/class filters, the `GET /erp/api/exams/{exam}/results` call, the results table, the "Publish Results" button, and the "Report Card" print modal — is identical regardless of which of the 4 menu leaves was clicked. A new developer should treat these 4 sidebar items as one feature with four names, not four separate concepts, and should never go looking for a `RankController` or a `ReportCardController` — they don't exist.

Additionally, the Reports module's own **Examination Reports** leaf (`/reports/examination-reports`, see `reports.md`) also maps to the same `ExamReports.vue` component used by Exam Management's "Exam Reports" leaf (line 240 of the router vs. line 171) — see section 12.

## 3. Every Page

### 3.1 Exams (`Exams.vue`)
- **Frontend URL**: `/erp/dashboard/exam-management/exams`
- **Backend**: `GET/POST /erp/api/exams`, `PUT/DELETE /erp/api/exams/{exam}`, `PATCH /erp/api/exams/{exam}/publish`
- **Controller**: `ExamController@index/store/update/destroy/publish`
- **Validation** (`ExamController::validated()`, verbatim):
```php
'name' => 'required|string|max:255',
'exam_type_id' => 'required|exists:exam_types,id',
'academic_session_id' => 'required|exists:academic_sessions,id',
'start_date' => 'required|date',
'end_date' => 'required|date|after_or_equal:start_date',
'status' => ['required', Rule::in(['Scheduled', 'Ongoing', 'Completed'])],
```
- **Permissions**: index open to any authenticated ERP user; store/update/destroy/publish require `exam.manage`.

### 3.2 Exam Types (`ExamTypes.vue`)
- **Frontend URL**: `/erp/dashboard/exam-management/exam-types`
- **Backend**: `GET/POST /erp/api/exams/types`, `PUT/DELETE /erp/api/exams/types/{examType}`
- **Controller**: `ExamTypeController@index/store/update/destroy`
- **Validation** (verbatim, identical for store and update):
```php
'name' => 'required|string|max:255',
'description' => 'nullable|string|max:255',
```
- **Permissions**: index open; store/update/destroy require `exam.manage`.

### 3.3 Exam Schedule (`ExamSchedule.vue`)
- **Frontend URL**: `/erp/dashboard/exam-management/exam-schedule`
- **Backend**: `GET/POST /erp/api/exams/schedules`, `PUT/DELETE /erp/api/exams/schedules/{examSchedule}` (index accepts optional `?exam_id=`)
- **Controller**: `ExamScheduleController@index/store/update/destroy`
- **Validation** (`ExamScheduleController::validated()`, verbatim):
```php
'exam_id' => 'required|exists:exams,id',
'school_class_id' => 'required|exists:school_classes,id',
'subject_id' => [
    'required', 'exists:subjects,id',
    Rule::unique('exam_schedules', 'subject_id')
        ->where(fn ($q) => $q->where('exam_id', $request->exam_id)->where('school_class_id', $request->school_class_id))
        ->ignore($schedule?->id),
],
'date' => 'required|date',
'start_time' => 'required|date_format:H:i',
'end_time' => 'required|date_format:H:i|after:start_time',
'room' => 'nullable|string|max:100',
'max_marks' => 'required|numeric|min:1',
```
  Note the composite-uniqueness rule enforced at the *validation* layer (in addition to the DB-level unique index): a subject cannot appear twice for the same exam + class.
- **Permissions**: index open; store/update/destroy require `exam.manage`.

### 3.4 Seat Planning (`SeatPlanning.vue`)
- **Frontend URL**: `/erp/dashboard/exam-management/seat-planning`
- **Backend**: `GET /erp/api/exams/seat-plans?exam_id=` (required), `POST /erp/api/exams/seat-plans/{exam}/generate`, `PUT/DELETE /erp/api/exams/seat-plans/{seatPlan}`
- **Controller**: `SeatPlanController@index/generate/update/destroy` — **there is no `store()` method and no route for one.**
- **Validation**:
  - `index`: `'exam_id' => 'required|exists:exams,id'`
  - `generate`: none (route-model-bound `Exam $exam`, no request body)
  - `update` (verbatim): `'room' => 'required|string|max:100', 'seat_no' => 'required|string|max:20'`
- **Permissions**: index open; generate/update/destroy require `exam.manage`.
- The Vue page itself only exposes "Auto-Assign Seats" and a per-row delete button — there is no add/edit form in the UI even though `update()` exists on the backend.

### 3.5 Marks Management (`MarksEntry.vue`)
- **Frontend URL**: `/erp/dashboard/exam-management/marks-management`
- **Backend**: `GET /erp/api/exams/marks?exam_schedule_id=` (required), `POST /erp/api/exams/marks`
- **Controller**: `MarkController@index/store` — **there is no per-record store/update/delete; `store()` is a bulk upsert.**
- **Validation** (`store`, verbatim):
```php
'exam_schedule_id' => 'required|exists:exam_schedules,id',
'records' => 'required|array|min:1',
'records.*.student_id' => 'required|exists:students,id',
'records.*.marks_obtained' => 'required|numeric|min:0',
'records.*.remarks' => 'nullable|string|max:255',
```
  Additionally, the controller manually rejects (HTTP 422) any record whose `marks_obtained` exceeds the schedule's `max_marks` — this is not a Laravel validation rule but an explicit `if` check in `store()`.
- **Permissions**: index open; store requires `exam.manage`.

### 3.6 Grade System (`GradeSystem.vue`)
- **Frontend URL**: `/erp/dashboard/exam-management/grade-system`
- **Backend**: `GET/POST /erp/api/exams/grade-system`, `PUT/DELETE /erp/api/exams/grade-system/{gradeSystem}`
- **Controller**: `GradeSystemController@index/store/update/destroy`
- **Validation** (verbatim):
```php
'grade' => 'required|string|max:10',
'min_percentage' => 'required|numeric|min:0|max:100',
'max_percentage' => 'required|numeric|min:0|max:100|gte:min_percentage',
'remarks' => 'nullable|string|max:255',
```
- **Permissions**: index open; store/update/destroy require `exam.manage`.

### 3.7–3.10 Result Processing / Report Cards / Rank Generation / Exam Results (`ExamResults.vue`, shared)
- **Frontend URLs**: `/erp/dashboard/exam-management/result-processing`, `/report-cards`, `/rank-generation`, `/exam-results`
- **Backend**: `GET /erp/api/exams/{exam}/results?school_class_id=`, `GET /erp/api/exams/{exam}/results/{student}`, plus `PATCH /erp/api/exams/{exam}/publish` for the "Publish Results" button shown on this page
- **Controller**: `ExamResultController@index/show`, backed entirely by `ExamResultCalculator::forExam()` (see section 10) — no validation rules of its own (`school_class_id` is just cast with `$request->filled()`/`integer()`)
- **New — `ExamResultController@forStudent`**, `GET /erp/api/exams/students/{student}/results`: returns every exam result for one student across *all* exams, not scoped to a single exam like `show` above. Backed by a new `ExamResultCalculator::forStudent()` method — resolves every distinct `exam_id` with an `ExamSchedule` for the student's `school_class_id`, then re-runs `forExam($exam, $student->school_class_id)` per exam and extracts just that student's row (reusing the exact same computation as `index`/`show`, not a parallel implementation). Registered as a literal `students/{student}/results` route ahead of the `{exam}/results` wildcard group so it can never be mistaken for an exam ID. Not used anywhere within this module's own pages — it exists specifically to power People > Students' detail-view Exam tab (`StudentTabExam.vue`, see `people.md`).
- **Permissions**: `index`/`show` open to any authenticated user; `publish` (called from this page) requires `exam.manage`.
- The in-page "Report Card" button opens a print-style modal built entirely from the same result row already in memory (no extra API call).

### 3.11 Admit Cards (`AdmitCards.vue`)
- **Frontend URL**: `/erp/dashboard/exam-management/admit-cards`
- **Backend**: composed client-side from three existing read endpoints — `GET /erp/api/exams/schedules?exam_id=`, `GET /erp/api/exams/seat-plans?exam_id=`, `GET /erp/api/people/students` — **there is no dedicated admit-card controller, model, or endpoint.**
- **Controller**: none specific to admit cards.
- **Validation**: none (read-only page).
- **Permissions**: all three underlying endpoints are open to any authenticated ERP user.
- Confirmed by reading the component: `preview(student)` just sets a local `active` ref, and the "🖨 Print" button calls `window.print()` directly (`resources/js/erp/pages/exams/AdmitCards.vue` line 159). No PDF is generated server-side or client-side; nothing is written to storage.

### 3.12 Question Bank (`QuestionBank.vue`)
- **Frontend URL**: `/erp/dashboard/exam-management/question-bank`
- **Backend**: `GET/POST /erp/api/exams/questions`, `PUT/DELETE /erp/api/exams/questions/{question}`
- **Controller**: `QuestionController@index/store/update/destroy`
- **Validation** (verbatim):
```php
'subject_id' => 'required|exists:subjects,id',
'question_text' => 'required|string|max:2000',
'question_type' => ['required', Rule::in(['MCQ', 'Short Answer', 'Long Answer'])],
'options' => 'nullable|array',
'options.*' => 'string|max:255',
'correct_answer' => 'nullable|string|max:255',
'marks' => 'required|numeric|min:0.5',
'difficulty' => ['required', Rule::in(['Easy', 'Medium', 'Hard'])],
```
- **Permissions**: index open; store/update/destroy require `exam.manage`.
- Not wired to Online Tests (generic) in any way — it is a standalone question repository only.

### 3.13 Exam Reports (`ExamReports.vue`)
- **Frontend URL**: `/erp/dashboard/exam-management/exam-reports` (also reused at `/erp/dashboard/reports/examination-reports`, see section 12)
- **Backend**: `GET /erp/api/exams/{exam}/report?school_class_id=`
- **Controller**: `ExamReportController@index`, built on top of `ExamResultCalculator::forExam()` — adds pass/fail counts, pass/fail percentage, topper and class average on top of the raw per-student rows.
- **Validation**: none (read-only; `school_class_id` optional filter).
- **Permissions**: open to any authenticated ERP user (this route lives under the `exams` prefix, **not** under the `reports.view`-gated `reports` prefix — see section 12 for why that matters).

### Generic leaves (one-line reasons)
- **Online Tests**: no controller, model, table, or route anywhere in the codebase for online/computer-based testing — falls back to `GenericModule.vue` with fake seeded data.
- **Test Settings**: no settings entity or route for exam/test configuration beyond what already exists on Exam/ExamSchedule — falls back to `GenericModule.vue`.
- **Online Classes**: no video/online-class backend exists in this app at all — falls back to `GenericModule.vue`.

## 4. Form Layout

All real forms use the shared `SlideOver.vue` right-hand drawer pattern (per README's generic form conventions), except Marks Management and Seat Planning, which are page-embedded (bulk grid and generate-only respectively, not a create/edit drawer):
- **Exams**: Name → Exam Type (select) → Academic Session (select) → Start/End Date (2-col date grid) → Status (select).
- **Exam Types**: Name → Description.
- **Exam Schedule**: Exam (select) → Class (select) → Subject (select) → Date → Start/End Time (2-col) → Room/Max Marks (2-col).
- **Grade System**: Grade → Min %/Max % (2-col) → Remarks.
- **Question Bank**: Subject (select) → Question Text (textarea) → Type/Difficulty (2-col select) → Options (textarea, MCQ only) + Correct Answer (MCQ only) → Marks.
- **Marks Management**: not a drawer — a filter bar (Exam → Class & Subject cascading selects) reveals a full-width editable grid, one row per active student in the selected class, with inline `marks_obtained`/`remarks` inputs and a single "Save Marks" bulk-submit button.
- **Seat Planning**: not a drawer — a single Exam selector plus one "Auto-Assign Seats" action button; the resulting table only supports row deletion, no inline edit UI (despite the backend `update()` existing).

## 5. Every Field

### `exam_types`
| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Name | `name` | exam_types | string | Required | `required|string|max:255` | — | — | — |
| Description | `description` | exam_types | string, nullable | Optional | `nullable|string|max:255` | null | — | — |

### `exams`
| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Exam Name | `name` | exams | string | Required | `required|string|max:255` | — | — | — |
| Exam Type | `exam_type_id` | exams | unsignedBigInteger | Required | `required|exists:exam_types,id` | — | `GET /exams/types` | `exam_types.id`, `restrictOnDelete` |
| Academic Session | `academic_session_id` | exams | unsignedBigInteger | Required | `required|exists:academic_sessions,id` | — | `GET /settings/academic-sessions` | `academic_sessions.id`, `restrictOnDelete` |
| Start Date | `start_date` | exams | date | Required | `required|date` | — | — | — |
| End Date | `end_date` | exams | date | Required | `required|date|after_or_equal:start_date` | — | — | — |
| Status | `status` | exams | enum | Required | `required|in:Scheduled,Ongoing,Completed` | `Scheduled` (DB default) | fixed 3-option select | — |
| Published At | `published_at` | exams | datetime, nullable | n/a — set only by `publish()`, not user-entered | — | null | — | — |

### `exam_schedules`
| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Exam | `exam_id` | exam_schedules | unsignedBigInteger | Required | `required|exists:exams,id` | — | `GET /exams` | `exams.id`, `cascadeOnDelete` |
| Class | `school_class_id` | exam_schedules | unsignedBigInteger | Required | `required|exists:school_classes,id` | — | `GET /academics/classes` | `school_classes.id`, `cascadeOnDelete` |
| Subject | `subject_id` | exam_schedules | unsignedBigInteger | Required | `required|exists:subjects,id` + unique per (exam_id, school_class_id) | — | `GET /academics/subjects` | `subjects.id`, `cascadeOnDelete` |
| Date | `date` | exam_schedules | date | Required | `required|date` | — | — | — |
| Start Time | `start_time` | exam_schedules | time | Required | `required|date_format:H:i` | — | — | — |
| End Time | `end_time` | exam_schedules | time | Required | `required|date_format:H:i|after:start_time` | — | — | — |
| Room | `room` | exam_schedules | string, nullable | Optional | `nullable|string|max:100` | null | — | — |
| Max Marks | `max_marks` | exam_schedules | decimal(6,2) | Required | `required|numeric|min:1` | `100` (DB default) | — | — |

### `grade_systems`
| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Grade | `grade` | grade_systems | string | Required | `required|string|max:10` | — | — | — |
| Min % | `min_percentage` | grade_systems | decimal(5,2) | Required | `required|numeric|min:0|max:100` | — | — | — |
| Max % | `max_percentage` | grade_systems | decimal(5,2) | Required | `required|numeric|min:0|max:100|gte:min_percentage` | — | — | — |
| Remarks | `remarks` | grade_systems | string, nullable | Optional | `nullable|string|max:255` | null | — | — |

### `marks`
| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Exam Schedule | `exam_schedule_id` | marks | unsignedBigInteger | Required | `required|exists:exam_schedules,id` (top level of bulk payload) | — | implicit (selected via Exam→Class&Subject cascade in UI) | `exam_schedules.id`, `cascadeOnDelete` |
| Student | `student_id` | marks | unsignedBigInteger | Required (per record) | `required|exists:students,id` | — | list of active students in the schedule's class, from `GET /exams/marks` | `students.id`, `cascadeOnDelete` |
| Marks Obtained | `marks_obtained` | marks | decimal(6,2) | Required (per record) | `required|numeric|min:0` + controller-level `<= schedule.max_marks` check | — | — | — |
| Remarks | `remarks` | marks | string, nullable | Optional (per record) | `nullable|string|max:255` | null | — | — |

### `seat_plans`
| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Exam | `exam_id` | seat_plans | unsignedBigInteger | set by `generate()`, not user input | — | — | — | `exams.id`, `cascadeOnDelete` |
| Student | `student_id` | seat_plans | unsignedBigInteger | set by `generate()`, not user input | — | — | — | `students.id`, `cascadeOnDelete` |
| Room | `room` | seat_plans | string | Required (on `update()`) | `required|string|max:100` | computed by `generate()` as `"Room N"` | — | — |
| Seat No | `seat_no` | seat_plans | string | Required (on `update()`) | `required|string|max:20` | computed by `generate()` as `1..30` | — | — |

### `questions`
| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Subject | `subject_id` | questions | unsignedBigInteger | Required | `required|exists:subjects,id` | — | `GET /academics/subjects` | `subjects.id`, `cascadeOnDelete` |
| Question Text | `question_text` | questions | text | Required | `required|string|max:2000` | — | — | — |
| Question Type | `question_type` | questions | enum | Required | `required|in:MCQ,Short Answer,Long Answer` | `MCQ` (DB default) | fixed 3-option select | — |
| Options | `options` | questions | json, nullable | Optional (MCQ only, UI textarea "one per line") | `nullable|array`, `options.*` string max 255 | null | — | — |
| Correct Answer | `correct_answer` | questions | string, nullable | Optional | `nullable|string|max:255` | null | — | — |
| Marks | `marks` | questions | decimal(5,2) | Required | `required|numeric|min:0.5` | `1` (DB default) | — | — |
| Difficulty | `difficulty` | questions | enum | Required | `required|in:Easy,Medium,Hard` | `Medium` (DB default) | fixed 3-option select | — |

## 6. Database Relationships

From the model files (`app/Models/Exam.php`, `ExamSchedule.php`, `Mark.php`, `SeatPlan.php`, `Question.php`, `GradeSystem.php`, `ExamType.php`):

- `Exam` **belongsTo** `ExamType` (`examType()`), **belongsTo** `AcademicSession` (`academicSession()`), **hasMany** `ExamSchedule` (`schedules()`), **hasMany** `SeatPlan` (`seatPlans()`).
- `ExamSchedule` **belongsTo** `Exam` (`exam()`), **belongsTo** `SchoolClass` (`schoolClass()`), **belongsTo** `Subject` (`subject()`), **hasMany** `Mark` (`marks()`).
- `Mark` **belongsTo** `ExamSchedule` (`examSchedule()`), **belongsTo** `Student` (`student()`).
- `SeatPlan` **belongsTo** `Exam` (`exam()`), **belongsTo** `Student` (`student()`).
- `Question` **belongsTo** `Subject` (`subject()`).
- `GradeSystem` and `ExamType` have no relations declared (`GradeSystem` has a static helper `forPercentage()` used nowhere in the current controllers — `ExamResultCalculator` reimplements the same lookup inline instead of calling it).
- `ExamType`, `ExamSchedule`, `GradeSystem`, `Mark`, `Question`, `SeatPlan` have no `SoftDeletes` trait — none of the Exam Management models do (verified: no `use Illuminate\Database\Eloquent\SoftDeletes` import in any of the 7 model files).

## 7. Create Flow

- **Exam Type**: single insert into `exam_types`. No side effects.
- **Exam**: single insert into `exams` (`published_at` left null, `status` defaults to whatever the form sends, typically `Scheduled`). No cascading writes to other tables at creation time — schedules, seat plans and marks are created independently afterward.
- **Exam Schedule**: single insert into `exam_schedules`, scoped to one exam + one class + one subject. No automatic mark or seat-plan rows are created. The DB-level composite unique index `exam_schedules_unique` (`exam_id`, `school_class_id`, `subject_id`) backs the validation-layer `Rule::unique` check.
- **Grade Band**: single insert into `grade_systems`. Takes effect immediately for every future call to `ExamResultCalculator::forExam()` — no exam-specific binding, one grade table serves the whole school.
- **Marks**: `MarkController::store()` loops over the `records` array and calls `Mark::updateOrCreate(['exam_schedule_id' => ..., 'student_id' => ...], [...])` per student — see section 10 for the upsert semantics. If any single record's `marks_obtained` exceeds the schedule's `max_marks`, the entire request is rejected with a 422 before any row is written (the loop returns early on the first offending record, so earlier records in the same batch that were already `updateOrCreate`'d **do** persist — there is no wrapping DB transaction).
- **Seat Plan**: only ever created via `SeatPlanController::generate()` — see section 10 for the exact algorithm. There is no manual "add seat" flow in the UI or API.
- **Question**: single insert into `questions`; `options` (MCQ only) is submitted as a plain-text textarea in the UI, split on newlines client-side into a JSON array before POST.

## 8. Edit Flow

All real entities except `Mark` and `SeatPlan`-via-generate follow the standard pattern: `PUT` re-runs the exact same validation rules as `store()` (see section 3) and calls `$model->update($data)`. Notably:
- `Exam::update()` accepts the same fields as create, **including `status`** — a user can manually flip status back from `Completed` to `Scheduled`/`Ongoing` via the Edit drawer; `publish()` is a separate, distinct action (section 10) that also sets `status = Completed` as a side effect.
- `ExamSchedule::update()` re-validates the composite-unique subject rule with `->ignore($schedule?->id)` so a schedule can be re-saved without tripping over its own existing row.
- `Mark`: there is no distinct "edit" endpoint — re-submitting the same `exam_schedule_id`/`student_id` pair through `POST /exams/marks` just re-triggers `updateOrCreate`, overwriting the existing row's `marks_obtained`/`remarks`. The Marks Management UI pre-fills already-entered marks into the same grid, so editing and creating are the same user action.
- `SeatPlan::update()` only lets an admin correct `room`/`seat_no` for one already-generated row; it cannot create a new row.

## 9. Delete Flow

All deletes across this module are **hard deletes** (`$model->delete()`, no `SoftDeletes` trait on any of the 7 models — confirmed in section 6). Cascade behavior comes entirely from migration-level FK constraints, not application code:

| Table | On parent delete | Behavior |
|---|---|---|
| `exams` (delete an exam) | `exam_schedules.exam_id`, `seat_plans.exam_id` | `cascadeOnDelete` — deleting an exam deletes all its schedules and seat plans |
| `exam_types` (delete a type) | `exams.exam_type_id` | `restrictOnDelete` — deleting a type in use by any exam is blocked at the DB level (the controller has no pre-check; a DB constraint violation would surface as a 500) |
| `academic_sessions` (delete a session) | `exams.academic_session_id` | `restrictOnDelete` — same as above |
| `exam_schedules` (delete a schedule) | `marks.exam_schedule_id` | `cascadeOnDelete` — deleting a schedule silently deletes every mark entered against it |
| `school_classes` / `subjects` (delete a class/subject) | `exam_schedules.school_class_id` / `.subject_id` | `cascadeOnDelete` — deleting a class or subject used in scheduling deletes the schedule rows (and transitively their marks) |
| `students` (delete a student) | `marks.student_id`, `seat_plans.student_id` | `cascadeOnDelete` |
| `subjects` (delete a subject) | `questions.subject_id` | `cascadeOnDelete` |
| `grade_systems` (delete a grade band) | none — grades are read live by percentage range, not FK'd from anywhere | no cascade; simply removes that band from future calculations |

None of the delete endpoints in this module have any extra application-level guard beyond what the DB constraint enforces (e.g. no "can't delete a published exam" check in `ExamController::destroy()`).

## 10. Business Rules

- **Grade-boundary logic**: a grade applies when `percentage >= min_percentage AND percentage <= max_percentage` (inclusive on both ends), evaluated with `$grades->first(...)` after ordering by `min_percentage` descending (`ExamResultCalculator::forExam()`). If two grade bands overlap, the band with the *highest* `min_percentage` wins because it's checked first. If no band covers a percentage, `grade` is returned as `null` (rendered as "—" in the UI). There is no server-side validation preventing overlapping or gapped grade bands at create/edit time in `GradeSystemController`.
- **Pass/Fail threshold**: hardcoded constant `ExamResultCalculator::PASS_PERCENTAGE = 33.0` — a student passes if their overall percentage (obtained ÷ sum of all `max_marks` for their class's schedules in that exam) is `>= 33.0`. This is not configurable from any UI.
- **Rank**: computed per school class (not school-wide), by sorting each class's rows by `percentage` descending and assigning `rank = index + 1`; ties are not specially handled — whichever row sorts first (stable sort by original iteration order) gets the lower rank number.
- **Marks composite-unique upsert rule**: the `marks` table has a DB-level unique constraint on (`exam_schedule_id`, `student_id`) (migration `2026_07_29_121857_create_marks_table.php`). `MarkController::store()` relies on this by calling `Mark::updateOrCreate(['exam_schedule_id' => ..., 'student_id' => ...], [...])` per record — meaning a student can only ever have one mark row per exam schedule; resubmitting simply overwrites it. There is no marks-history/versioning.
- **Seat Plan composite-unique + generate-only rule**: `seat_plans` has a unique constraint on (`exam_id`, `student_id`). `SeatPlanController::generate()` reads every schedule's `school_class_id` for the given exam, collects every `Active` student across those classes, subtracts students who already have a seat plan row for that exam (`$existing`), and only creates rows for the remainder — so calling "Auto-Assign Seats" repeatedly is idempotent/incremental (it only assigns newly-eligible students, never reassigns or duplicates existing ones). Room capacity is a hardcoded constant `SeatPlanController::SEATS_PER_ROOM = 30`; rooms are auto-named `"Room " . (intdiv($seatNumber, 30) + 1)` and seat numbers cycle `1..30` per room, assigned in a global running counter across the whole exam (not per class) ordered by `school_class_id` then `name`.
- **Exam publish workflow**: `ExamController::publish()` (`PATCH /exams/{exam}/publish`, requires `exam.manage`) sets `published_at = now()` and forces `status = 'Completed'` — it does **not** lock/freeze any other table. Marks can still be edited via `POST /exams/marks`, schedules can still be edited/deleted, and `ExamResultCalculator` will simply reflect whatever the current mark data is on every subsequent read; there is no `is_locked` flag anywhere in this module. The only UI-visible effect of publishing is: the "Publish Results" button disappears (`v-if="!exam.published_at"` in both `Exams.vue` and `ExamResults.vue`), a "Published" badge appears in the Exams list, and the exam's status badge changes to Completed.
- **Marks-exceed-max-marks guard**: enforced only in `MarkController::store()` as a manual `if` check against `$schedule->max_marks`, not a Laravel validation rule — a value like `-5` would actually pass the `numeric|min:0` rule check and be silently accepted as long as it's `>= 0`, since only the upper bound is manually guarded (the lower bound of `0` *is* enforced by `min:0`).

## 11. Import & Export

Per the README's Import & Export section: only 9 entities have real Export, and Exam Management contributes exactly one of them.

- **Export**: `marks` is a real, working export entity (`app/Http/Controllers/Erp/ImportExport/ExportController.php`, `ENTITIES` array). `GET /erp/api/import-export/export/marks` streams every `Mark` row (with `student`, `examSchedule.exam`, `examSchedule.subject` eager-loaded) as columns `Student, Admission No, Exam, Subject, Marks Obtained, Max Marks`. It is reachable from the Import & Export module's UI, not from within any Exam Management page directly.
- **No other entity in this module has Export** — `exams`, `exam_types`, `exam_schedules`, `grade_systems`, `seat_plans`, `questions` are not in the `ENTITIES` list.
- **No Import exists for anything in this module.** The only real importer in the whole app is Student Import (see `student-import-management.md`); there is no marks-import, no bulk-seat-plan-import, nothing.

## 12. Reports

- **Exam Reports** (`/exam-management/exam-reports`, `ExamReports.vue` / `ExamReportController`) is the module's own reporting page: exam + optional class filter → pass %, fail %, topper, class average, and the full ranked student list, all derived from `ExamResultCalculator::forExam()`.
- **Reports module's "Examination Reports"** (`/reports/examination-reports`) maps, in `FLAGSHIP_ROUTES`, to the **exact same `ExamReports.vue` component** (`resources/js/erp/router/index.js`: `'/exam-management/exam-reports': ExamReports` and `'/reports/examination-reports': ExamReports` both point at the identical import) — confirmed by reading the router file directly. There is no separate `ExaminationReportController` in the Reports namespace.
- **Important permission nuance**: the Reports module's other real report pages (Students, Admissions, Fees, Finance) live under `Route::prefix('reports')->middleware('erp.permission:reports.view')` in `routes/erp_api.php`, meaning reads are gated. Examination Reports/Exam Reports, however, hits `GET /erp/api/exams/{exam}/report`, which is declared under the **`exams` prefix**, not the `reports` prefix, and is therefore **open to any authenticated ERP user regardless of `reports.view`** — an inconsistency worth knowing if auditing permission gates across the Reports module.
- The Exam Results page (section 3.7–3.10) also functions as a lighter-weight, per-exam results report with the same underlying computed data, just without the pass/fail/topper aggregate stats that `ExamReportController` adds.

## 13. APIs

All under `/erp/api/exams/...` (`routes/erp_api.php`, `Route::prefix('exams')->name('exams.')`), `erp.auth` middleware applied globally, `erp.permission:exam.manage` applied to the write group only.

| Method | Path | Controller@method | Auth |
|---|---|---|---|
| GET | `/exams` | `ExamController@index` | any authenticated |
| POST | `/exams` | `ExamController@store` | `exam.manage` |
| PUT | `/exams/{exam}` | `ExamController@update` | `exam.manage` |
| DELETE | `/exams/{exam}` | `ExamController@destroy` | `exam.manage` |
| PATCH | `/exams/{exam}/publish` | `ExamController@publish` | `exam.manage` |
| GET | `/exams/types` | `ExamTypeController@index` | any authenticated |
| POST | `/exams/types` | `ExamTypeController@store` | `exam.manage` |
| PUT | `/exams/types/{examType}` | `ExamTypeController@update` | `exam.manage` |
| DELETE | `/exams/types/{examType}` | `ExamTypeController@destroy` | `exam.manage` |
| GET | `/exams/schedules` | `ExamScheduleController@index` | any authenticated |
| POST | `/exams/schedules` | `ExamScheduleController@store` | `exam.manage` |
| PUT | `/exams/schedules/{examSchedule}` | `ExamScheduleController@update` | `exam.manage` |
| DELETE | `/exams/schedules/{examSchedule}` | `ExamScheduleController@destroy` | `exam.manage` |
| GET | `/exams/grade-system` | `GradeSystemController@index` | any authenticated |
| POST | `/exams/grade-system` | `GradeSystemController@store` | `exam.manage` |
| PUT | `/exams/grade-system/{gradeSystem}` | `GradeSystemController@update` | `exam.manage` |
| DELETE | `/exams/grade-system/{gradeSystem}` | `GradeSystemController@destroy` | `exam.manage` |
| GET | `/exams/marks` | `MarkController@index` | any authenticated |
| POST | `/exams/marks` | `MarkController@store` | `exam.manage` |
| GET | `/exams/seat-plans` | `SeatPlanController@index` | any authenticated |
| POST | `/exams/seat-plans/{exam}/generate` | `SeatPlanController@generate` | `exam.manage` |
| PUT | `/exams/seat-plans/{seatPlan}` | `SeatPlanController@update` | `exam.manage` |
| DELETE | `/exams/seat-plans/{seatPlan}` | `SeatPlanController@destroy` | `exam.manage` |
| GET | `/exams/questions` | `QuestionController@index` | any authenticated |
| POST | `/exams/questions` | `QuestionController@store` | `exam.manage` |
| PUT | `/exams/questions/{question}` | `QuestionController@update` | `exam.manage` |
| DELETE | `/exams/questions/{question}` | `QuestionController@destroy` | `exam.manage` |
| GET | `/exams/{exam}/results` | `ExamResultController@index` | any authenticated |
| GET | `/exams/{exam}/results/{student}` | `ExamResultController@show` | any authenticated |
| GET | `/exams/students/{student}/results` | `ExamResultController@forStudent` | any authenticated |
| GET | `/exams/{exam}/report` | `ExamReportController@index` | any authenticated |

### Example: Mark bulk-upsert (`POST /erp/api/exams/marks`)
Request:
```json
{
  "exam_schedule_id": 14,
  "records": [
    { "student_id": 201, "marks_obtained": 87, "remarks": "Good" },
    { "student_id": 202, "marks_obtained": 45.5, "remarks": null },
    { "student_id": 203, "marks_obtained": 63 }
  ]
}
```
Response (200):
```json
{ "success": true, "count": 3 }
```
Rejected response if any record exceeds the schedule's `max_marks` (e.g. schedule `max_marks = 100`, one record submits `120`):
```json
{ "message": "Marks for student #203 exceed the maximum of 100.00." }
```
(HTTP 422; note only the offending record's message is returned, and any records processed earlier in the loop before the offending one are already persisted — see section 7.)

### Example: Seat plan generate (`POST /erp/api/exams/seat-plans/5/generate`)
Request body: none.
Response (200):
```json
{ "success": true, "assigned": 42 }
```

### Example: Exam results (`GET /erp/api/exams/5/results?school_class_id=3`)
Response (200), one row per student:
```json
[
  {
    "student_id": 201,
    "admission_no": "S-2026-0041",
    "name": "Aarav Sharma",
    "school_class_id": 3,
    "obtained": 412.5,
    "max_total": 500,
    "percentage": 82.5,
    "grade": "A",
    "result": "Pass",
    "subjects": [
      { "subject_id": 9, "subject_name": "Mathematics", "marks_obtained": 87, "max_marks": 100 },
      { "subject_id": 10, "subject_name": "Science", "marks_obtained": 78.5, "max_marks": 100 }
    ],
    "rank": 1
  }
]
```

## 14. Database Tables

| Table | PK | FKs (→ target, ON DELETE) | Unique constraints | Indexes |
|---|---|---|---|---|
| `exam_types` | `id` | none | none | none beyond PK |
| `exams` | `id` | `exam_type_id` → `exam_types.id` (restrict); `academic_session_id` → `academic_sessions.id` (restrict) | none | none beyond PK/FK |
| `exam_schedules` | `id` | `exam_id` → `exams.id` (cascade); `school_class_id` → `school_classes.id` (cascade); `subject_id` → `subjects.id` (cascade) | `exam_schedules_unique` on (`exam_id`, `school_class_id`, `subject_id`) | none beyond PK/FK/unique |
| `grade_systems` | `id` | none | none | none beyond PK |
| `marks` | `id` | `exam_schedule_id` → `exam_schedules.id` (cascade); `student_id` → `students.id` (cascade) | unnamed unique on (`exam_schedule_id`, `student_id`) | none beyond PK/FK/unique |
| `seat_plans` | `id` | `exam_id` → `exams.id` (cascade); `student_id` → `students.id` (cascade) | unnamed unique on (`exam_id`, `student_id`) | none beyond PK/FK/unique |
| `questions` | `id` | `subject_id` → `subjects.id` (cascade) | none | none beyond PK/FK |

All tables use the default Laravel `id`/`timestamps()`; none have `deleted_at` (no soft deletes, matching section 9).

## 15. File Storage

None. Confirmed by reading both `AdmitCards.vue` and `ExamResults.vue` (report cards) end to end: both render an on-screen modal from data already in memory and call `window.print()` when the user clicks Print — there is no PDF library (no DomPDF/Snappy/Browsershot usage), no `Storage::` call, and no `storage/app` write anywhere in `ExamController`, `ExamResultController`, `ExamReportController`, or any Exams-namespace controller. Nothing generated in this module is ever persisted to disk; every "document" is regenerated from live data on each view/print.

## 16. Background Jobs

None. There is no `app/Jobs` directory in this codebase at all (confirmed — the path does not exist), so nothing in Exam Management (or any other module) runs as a queued/background job. Publishing results, generating seat plans, and computing reports all execute synchronously within the HTTP request.

## 17. Notifications

None. There is no `app/Notifications` directory in this codebase at all (confirmed — the path does not exist). Publishing an exam's results (`ExamController::publish()`) does not notify students, parents, or teachers in any way — it only flips `published_at`/`status` on the `exams` row. Any apparent "notification" behavior in the ERP (toasts) is purely local UI feedback (`pushToast()`), not a delivered message.

## 18. Module Dependencies

- **Depends on People/Student**: `MarkController`, `SeatPlanController`, `ExamResultCalculator`, and `AdmitCards.vue` all query `students` directly (filtered to `status = 'Active'`, and to the classes covered by an exam's schedules) — this module cannot function without Student data.
- **Depends on Academics**: `ExamSchedule` requires a `school_class_id` and `subject_id` from the Academics module (`school_classes`, `subjects` tables); `Exam` requires an `academic_session_id`.
- **Depends on Settings**: `Exams.vue` fetches `GET /settings/academic-sessions` to populate the session dropdown and to default-select the "current" session.
- **Feeds Reports**: the Reports module's "Examination Reports" leaf reuses this module's `ExamReportController`/`ExamResultCalculator` output wholesale (section 12) — Reports has no independent examination-report logic.
- **Feeds People > Students**: the detail-view Exam tab (`StudentTabExam.vue`) fetches the new `GET /exams/students/{student}/results` endpoint to show one student's real results across every exam (`people.md`) — the only consumer of `ExamResultCalculator::forStudent()`.
- **Feeds Import & Export**: the `marks` export entity (section 11) is the only cross-module surface this module exposes for the Import & Export module to consume.
- **Documents module does NOT reuse Exam data.** Checked both Documents controllers (`app/Http/Controllers/Erp/Documents/CertificateController.php` and `IdCardController.php`) — Certificates cover Bonafide/Transfer/Character/Migration certificate types only (no exam-related type), and ID Cards are keyed to `student`/`teacher`/`staff` holders with no exam linkage. The Documents menu's "Admit Card Templates", "Report Card Templates", and "Template Builder" leaves are **not** in `FLAGSHIP_ROUTES` and therefore fall back to `GenericModule.vue` — they are unconnected, fake placeholder pages, not a real integration point with this module's Admit Cards/Report Cards features.

## 19. Screenshots

Not available — generated from source code.

## 20. Final Deliverable

Exam Management is a compact but complete exam lifecycle module: 7 real database tables and 8 controllers drive exam definition, scheduling, seat assignment, marks capture, and grading, while results, ranks, and grades are never persisted — they are recomputed on every request by `ExamResultCalculator`. Two deliberate simplifications define its edges: Marks are entered through a single bulk-upsert endpoint keyed on the composite-unique `(exam_schedule_id, student_id)` pair rather than per-record CRUD, and Seat Plans are populated exclusively by an idempotent auto-assignment action (`generate()`) with no manual "add seat" path. Four sidebar leaves (Result Processing, Report Cards, Rank Generation, Exam Results) are a single component and endpoint wearing four different page titles, and three more (Online Tests, Test Settings, Online Classes) are unimplemented generic placeholders — of the 16 menu entries, 12 represent real, working functionality. Admit Cards and Report Cards are both purely `window.print()`-based with no server-side PDF generation and no file storage; there are no background jobs and no real notification delivery anywhere in this module (or the app). The module's only external surfaces are: it depends on People/Student and Academics/SchoolClass+Subject for its core data, it feeds the Reports module's Examination Reports (a direct reuse of the same component/controller, not a separate implementation), and it contributes one export entity (`marks`) to the Import & Export module — it has no import path and no integration with the Documents module despite the naming overlap around "admit cards" and "report cards."
