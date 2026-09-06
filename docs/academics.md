# Academics Module

See [README.md](README.md) for cross-cutting architecture (routing, permission model, generic-page mechanism, audit logging, morph maps, calculator-service pattern) — not repeated here.

## 1. Module Overview

**Name**: Academics (sidebar group `Academics`, icon 🎓).

**Purpose**: Defines the school's structural taxonomy — physical/administrative branches, academic years, the class→section hierarchy, the subject catalog, and which subjects belong to which class.

**Main workflow**: An operator (with `academics.manage`) first creates `Branches`, then `Classes` (e.g. "1", "10", "Nursery"), adds `Sections` under each class (e.g. "A", "B"), creates the `Subjects` catalog, and finally assigns subjects to classes via the class–subject pivot. Everything downstream — Admissions' "Class Applying For", Students' `school_class_id`/`section_id`, Exam Schedules, Fee Structures, Teachers' class-teacher assignment, Meetings — references the `school_classes`/`sections` rows created here.

**Related modules**:
- **Settings** — Academic Sessions is a Settings-owned resource, also surfaced in this module's sidebar (see §3.2 below — full detail lives in [settings.md](settings.md)).
- **Admissions** — every `AdmissionEnquiry.class_applying_for_id` is a foreign key into `school_classes`; admitting an enquiry copies that class onto the newly created `Student`.
- **People** — `Student.school_class_id` / `Student.section_id` are foreign keys into this module's tables.
- **Exam Management, Fee Management, Meetings, Teachers** — all reference `school_classes`/`sections`/`subjects` by foreign key in their own migrations (`exam_schedules`, `erp_fee_structures`, `meetings`, `teachers`, `questions` all carry a `school_class_id`/`section_id`/`subject_id` column).

## 2. Menu Structure

Sidebar group `Academics`, children (from `resources/js/erp/data/menu.js`):

| Label | Path | Type |
|---|---|---|
| Branches | `/academics/branches` | **Real** |
| Academic Sessions | `/academics/academic-sessions` | **Real** (shared with Settings — see §3.2) |
| Classes & Sections | `/academics/classes-and-sections` | **Real** |
| Subjects | `/academics/subjects` | **Real** |
| Class Teachers | `/academics/class-teachers` | Generic |
| Subject Mapping | `/academics/subject-mapping` | Generic |
| Curriculum | `/academics/curriculum` | Generic |
| Lesson Plans | `/academics/lesson-plans` | Generic |
| Homework | `/academics/homework` | Generic |
| Timetable | `/academics/timetable` | Generic |
| Period Settings | `/academics/period-settings` | Generic |
| Academic Calendar | `/academics/academic-calendar` | Generic |

### Generic pages — reason
`Class Teachers, Subject Mapping, Curriculum, Lesson Plans, Homework, Timetable, Period Settings, Academic Calendar` have no key in `FLAGSHIP_ROUTES`, so each falls through to `GenericModule.vue` with fake seeded data. Note `Class Teachers` and `Subject Mapping` are near-duplicates of functionality already covered by the real `Classes & Sections` page (which has an inline "class teacher" free-text field per section, and a subject-assignment drawer per class) — but neither has its own dedicated controller/route.

## 3. Pages (Real)

### 3.1 Branches

- **Frontend URL**: `/academics/branches` → `resources/js/erp/pages/academics/Branches.vue`
- **Backend URL**: `GET erp/api/academics/branches`, `POST erp/api/academics/branches`, `PUT erp/api/academics/branches/{branch}`, `DELETE erp/api/academics/branches/{branch}`
- **Controller**: `App\Http\Controllers\Erp\Academics\BranchController`
  - `index()` — `Branch::orderBy('name')->get()`
  - `store(Request $request)` — validate + create
  - `update(Request $request, Branch $branch)` — validate + update
  - `destroy(Branch $branch)` — hard delete
- **Validation** (`store`/`update`, identical, verbatim):
```php
$request->validate([
    'name' => 'required|string|max:255',
    'principal' => 'nullable|string|max:255',
    'phone' => 'nullable|string|max:30',
    'address' => 'nullable|string|max:255',
    'status' => 'required|in:active,inactive',
]);
```
- **Permissions**: `index` open; `store`/`update`/`destroy` require `erp.permission:academics.manage`.

### 3.2 Academic Sessions

- **Frontend URL**: `/academics/academic-sessions` — resolves to the **same** `resources/js/erp/pages/settings/AcademicSessions.vue` component as `/settings/academic-sessions` (both keys point to the same imported component in `FLAGSHIP_ROUTES`).
- **Backend URL**: identical calls to `erp/api/settings/academic-sessions*` — there is no `academics/academic-sessions` backend route at all; `AcademicSessionController` lives entirely under the `settings` route prefix.
- Full controller, validation, fields, business rules (single-current-session enforcement) documented once in **[settings.md §3.2 / §5.2 / §10](settings.md)** — not duplicated here.

### 3.3 Classes & Sections

This single page manages three things: the `school_classes` list, each class's `sections` (nested drawer), and each class's `subjects` pivot assignment (nested drawer) — three separate backend resources behind one Vue component.

- **Frontend URL**: `/academics/classes-and-sections` → `resources/js/erp/pages/academics/ClassesSections.vue`
- **Backend URLs**:
  - Classes: `GET erp/api/academics/classes`, `POST erp/api/academics/classes`, `PUT erp/api/academics/classes/{schoolClass}`, `DELETE erp/api/academics/classes/{schoolClass}`
  - Sections: `GET erp/api/academics/sections`, `POST erp/api/academics/sections`, `PUT erp/api/academics/sections/{section}`, `DELETE erp/api/academics/sections/{section}`
  - Class–Subject sync: `PUT erp/api/academics/classes/{schoolClass}/subjects`
- **Controllers**:
  - `App\Http\Controllers\Erp\Academics\SchoolClassController`
    - `index()` — `SchoolClass::withCount('sections')->with(['sections', 'subjects:id,name,code'])->orderBy('sort_order')->orderBy('name')->get()`
    - `store(Request $request)` — validate + create, returns the class reloaded with `sections` and `subjects`
    - `update(Request $request, SchoolClass $schoolClass)` — validate + update, same reload
    - `destroy(SchoolClass $schoolClass)` — hard delete
  - `App\Http\Controllers\Erp\Academics\SectionController`
    - `index(Request $request)` — `Section::with('schoolClass:id,name')`, optionally filtered by `?school_class_id=`
    - `store(Request $request)` — validate + create
    - `update(Request $request, Section $section)` — validate + update
    - `destroy(Section $section)` — hard delete
  - `App\Http\Controllers\Erp\Academics\ClassSubjectController`
    - `sync(Request $request, SchoolClass $schoolClass)` — validates `subject_ids`, calls `$schoolClass->subjects()->sync($data['subject_ids'] ?? [])` — a full replace, not an append
- **Validation — Classes** (`store`/`update`, verbatim; `update` swaps the plain `unique` rule for a `Rule::unique(...)->ignore($schoolClass->id)`):
```php
// store
$request->validate([
    'name' => 'required|string|max:100|unique:school_classes,name',
    'capacity' => 'nullable|integer|min:1',
    'sort_order' => 'nullable|integer|min:0',
]);
// update
$request->validate([
    'name' => ['required', 'string', 'max:100', Rule::unique('school_classes', 'name')->ignore($schoolClass->id)],
    'capacity' => 'nullable|integer|min:1',
    'sort_order' => 'nullable|integer|min:0',
]);
```
- **Validation — Sections** (`store`/`update`, verbatim; note the composite-uniqueness scoping by `school_class_id`):
```php
// store
$request->validate([
    'school_class_id' => 'required|exists:school_classes,id',
    'name' => [
        'required', 'string', 'max:50',
        Rule::unique('sections', 'name')->where(fn ($q) => $q->where('school_class_id', $request->school_class_id)),
    ],
    'capacity' => 'nullable|integer|min:1',
    'class_teacher' => 'nullable|string|max:255',
]);
// update adds ->ignore($section->id) to the same Rule::unique chain
```
- **Validation — Class–Subject sync** (verbatim):
```php
$request->validate([
    'subject_ids' => 'array',
    'subject_ids.*' => 'integer|exists:subjects,id',
]);
```
- **Permissions**: all three `index` reads are open; all writes (`classes.store/update/destroy`, `classes.subjects.sync`, `sections.store/update/destroy`) require `erp.permission:academics.manage`.

### 3.4 Subjects

- **Frontend URL**: `/academics/subjects` → `resources/js/erp/pages/academics/Subjects.vue`
- **Backend URL**: `GET erp/api/academics/subjects`, `POST erp/api/academics/subjects`, `PUT erp/api/academics/subjects/{subject}`, `DELETE erp/api/academics/subjects/{subject}`
- **Controller**: `App\Http\Controllers\Erp\Academics\SubjectController`
  - `index()` — `Subject::withCount('classes')->orderBy('name')->get()`
  - `store(Request $request)` — validate + create
  - `update(Request $request, Subject $subject)` — validate + update
  - `destroy(Subject $subject)` — hard delete
- **Validation** (`store`; `update` swaps to `Rule::unique(...)->ignore($subject->id)`):
```php
// store
$request->validate([
    'name' => 'required|string|max:255',
    'code' => 'required|string|max:20|unique:subjects,code',
]);
// update
$request->validate([
    'name' => 'required|string|max:255',
    'code' => ['required', 'string', 'max:20', Rule::unique('subjects', 'code')->ignore($subject->id)],
]);
```
- **Permissions**: `index` open; `store`/`update`/`destroy` require `erp.permission:academics.manage`.

## 4. Form Layout

- **Branches** — `SlideOver` drawer, flat single-section form: Branch Name → Principal → Phone → Address → Status (select: active/inactive). Footer: Cancel / Save.
- **Classes & Sections** — three separate drawers on one page:
  1. **Add/Edit Class** drawer: Class Name → Capacity → Sort Order. Footer: Cancel / Save.
  2. **Manage Sections** drawer (opened per class via the 🧩 row action): top half lists existing sections (name, capacity, class teacher, inline Edit/Delete); bottom half is a compact add/edit mini-form (Name, Capacity, Class Teacher) that toggles between "+ Add Section" and "Update Section" depending on whether `editingSection` is set. Footer: Close only (no Save — every row action saves immediately).
  3. **Manage Subjects** drawer (opened per class via the 📖 row action): a `grid-cols-2` checkbox list of every subject in the catalog, pre-checked for subjects already on the class. Footer: Cancel / Save Assignment (calls the `sync` endpoint, replacing the full set).
- **Subjects** — `SlideOver` drawer, flat single-section form: Subject Name → Code. Footer: Cancel / Save.

## 5. Fields

### 5.1 Branches (table `branches`)

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Branch Name | `name` | branches | `string` | Required | `required\|string\|max:255` | none | — | — |
| Principal | `principal` | branches | `string` nullable | Optional | `nullable\|string\|max:255` | none | — | — |
| Phone | `phone` | branches | `string` nullable | Optional | `nullable\|string\|max:30` | none | — | — |
| Address | `address` | branches | `string` nullable | Optional | `nullable\|string\|max:255` | none | — | — |
| Status | `status` | branches | `enum('active','inactive')` | Required | `required\|in:active,inactive` | `'active'` (migration default) | fixed 2-option `<select>` | — |

### 5.2 Academic Sessions (table `academic_sessions`)

See [settings.md §5.2](settings.md) — identical fields, identical endpoint, not duplicated here.

### 5.3 Classes (table `school_classes`)

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Class Name | `name` | school_classes | `string`, **unique** | Required | `required\|string\|max:100\|unique:school_classes,name` (ignored-self on update) | none | — | — |
| Capacity | `capacity` | school_classes | `unsignedInteger` nullable | Optional | `nullable\|integer\|min:1` | none | — | — |
| Sort Order | `sort_order` | school_classes | `unsignedInteger` | Optional | `nullable\|integer\|min:0` | `0` (migration default) | — | — |

### 5.4 Sections (table `sections`)

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| *(implicit — set from the parent class context, not a visible field on the mini-form)* | `school_class_id` | sections | `foreignId` | Required | `required\|exists:school_classes,id` | none | parent class (page context, not a `<select>`) | `school_classes.id`, `cascadeOnDelete` |
| Section Name | `name` | sections | `string` | Required | `required\|string\|max:50`, unique **per** `school_class_id` (composite via `Rule::unique(...)->where(...)`) | none | — | — |
| Capacity | `capacity` | sections | `unsignedInteger` nullable | Optional | `nullable\|integer\|min:1` | none | — | — |
| Class Teacher | `class_teacher` | sections | `string` nullable | Optional | `nullable\|string\|max:255` | none | free text — **not** a foreign key to the Teachers table despite the name | — |

### 5.5 Subjects (table `subjects`)

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Subject Name | `name` | subjects | `string` | Required | `required\|string\|max:255` | none | — | — |
| Code | `code` | subjects | `string`, **unique** | Required | `required\|string\|max:20\|unique:subjects,code` (ignored-self on update) | none | — | — |

### 5.6 Class–Subject pivot (table `class_subject`)

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Subjects (multi-select checkboxes) | `subject_id` (one row per checked subject) | class_subject | `foreignId` | Optional (empty array clears all) | `subject_ids` array, each `integer\|exists:subjects,id` | none | full `Subject::all()` catalog, fetched via `/academics/subjects` | `subjects.id`, `cascadeOnDelete` |
| *(implicit)* | `school_class_id` | class_subject | `foreignId` | — | route-bound `{schoolClass}` | none | — | `school_classes.id`, `cascadeOnDelete` |

## 6. Database Relationships

- **`Branch`** (`app/Models/Branch.php`) — no Eloquent relationships defined (standalone directory list; no other table currently carries a `branch_id` foreign key).
- **`SchoolClass`** (`app/Models/SchoolClass.php`):
  - `sections(): HasMany` → `Section`
  - `subjects(): BelongsToMany` → `Subject` via pivot table `class_subject`
- **`Section`** (`app/Models/Section.php`):
  - `schoolClass(): BelongsTo` → `SchoolClass`
- **`Subject`** (`app/Models/Subject.php`):
  - `classes(): BelongsToMany` → `SchoolClass` via pivot table `class_subject`
- **`AcademicSession`** — no relationships (see [settings.md](settings.md)).

## 7. Create Flow

- **Branch**: single insert into `branches`. No side effects.
- **Class**: single insert into `school_classes`. No sections or subjects are created alongside it — those are separate, subsequent operations from the same page.
- **Section**: single insert into `sections`, with `school_class_id` taken from whichever class row the "Manage Sections" drawer was opened for (not user-selectable via a dropdown).
- **Subject**: single insert into `subjects`. No side effects.
- **Class–Subject assignment**: not a create in the traditional sense — `sync()` is a full-replace operation on the `class_subject` pivot: any subject IDs not in the submitted `subject_ids` array are deleted from the pivot for that class, and any new ones are inserted, in one call.

## 8. Edit Flow

- **Branch / Class / Section / Subject**: each `update()` re-validates the full field set (no partial-update semantics) and writes all fields back, including re-checking uniqueness (`Rule::unique(...)->ignore($id)`) for `Class.name`, `Section.name` (scoped per class), and `Subject.code`.
- **Class–Subject assignment**: editing = re-opening the "Manage Subjects" drawer, re-checking/unchecking boxes, and re-submitting the full desired set — there is no incremental add/remove API, only the full-replace `sync`.
- No recalculation of any derived/computed value occurs anywhere in this module (no calculator service is involved).

## 9. Delete Flow

- **Hard delete** throughout — no `SoftDeletes` trait used anywhere in the app.
- **Branch**: `DELETE /academics/branches/{id}` — no FK references `branches.id` from any other table, so no cascade behavior applies.
- **Class**: `DELETE /academics/classes/{id}` — the migration puts **no** `ON DELETE` behavior override on `sections.school_class_id` beyond `cascadeOnDelete()` (deleting a class cascades to delete all its `sections` and, via `class_subject.school_class_id`'s own `cascadeOnDelete()`, all its subject-assignment pivot rows). However, `students.school_class_id` uses `restrictOnDelete()` and `admission_enquiries.class_applying_for_id` uses `restrictOnDelete()` — **a class with any enrolled student or any admission enquiry referencing it cannot be deleted**; the DB will raise a foreign-key constraint violation (the controller does not pre-check or translate this into a friendly message).
- **Section**: `DELETE /academics/sections/{id}` — `students.section_id` uses `nullOnDelete()`, so deleting a section that has students assigned simply sets those students' `section_id` to `NULL` rather than blocking the delete.
- **Subject**: `DELETE /academics/subjects/{id}` — `class_subject.subject_id` is `cascadeOnDelete()`, so deleting a subject silently removes it from every class's assignment. `questions.subject_id` and `exam_schedules.subject_id` reference subjects too (see their own migrations) — this module's controller does not check those tables before deleting, so deleting a subject already used by an exam schedule or question relies entirely on that table's own migration-level FK behavior.
- **Class–Subject pivot**: no standalone delete endpoint — removal only happens via `sync()` omitting an ID, or via the cascades above.

## 10. Business Rules

- **Class name uniqueness**: `school_classes.name` has a DB `unique()` constraint, doubly enforced by the `required|...|unique:school_classes,name` validation rule.
- **Section name uniqueness is per-class, not global**: enforced by the composite DB unique index `['school_class_id', 'name']` and mirrored by the scoped `Rule::unique(...)->where(...)` in the controller — the same section name ("A") can exist under many different classes.
- **Subject code uniqueness**: `subjects.code` has a DB `unique()` constraint, mirrored by validation.
- **Class–subject sync is destructive-by-omission**: submitting an empty `subject_ids` array clears all subject assignments for that class in one call — there is no confirmation step in the UI beyond the drawer's own Save button.
- **Restrict-vs-null delete asymmetry**: deleting a `SchoolClass` is blocked if any `Student` or `AdmissionEnquiry` references it (`restrictOnDelete`), but deleting a `Section` merely orphans (nulls) any `Student.section_id` pointing at it (`nullOnDelete`) — a deliberate asymmetry visible directly in the migrations.
- **Academic Sessions single-current rule** — see [settings.md §10](settings.md).

## 11. Import & Export

No import or export exists for Branches, Classes & Sections, or Subjects — none of the three are among the README's 9 exportable entities (`student, fee, expense, salary, bank, fuel-log, route, marks, global`), and Student is the only entity with a real importer.

## 12. Reports

No dedicated report view consumes Academics data directly (no controller under `app/Http/Controllers/Erp/Reports` references `SchoolClass`, `Section`, `Branch`, or `Subject`). `school_classes`/`sections` are, however, used as **filter/foreign-key context** by other modules' own reports (e.g. Attendance, Exam, Fee reports filter by class/section) — that filtering logic lives in those modules' own calculators, not here.

## 13. APIs

| Method | Path | Controller@method | Permission |
|---|---|---|---|
| GET | `erp/api/academics/branches` | `BranchController@index` | open |
| POST | `erp/api/academics/branches` | `BranchController@store` | `academics.manage` |
| PUT | `erp/api/academics/branches/{branch}` | `BranchController@update` | `academics.manage` |
| DELETE | `erp/api/academics/branches/{branch}` | `BranchController@destroy` | `academics.manage` |
| GET | `erp/api/academics/classes` | `SchoolClassController@index` | open |
| POST | `erp/api/academics/classes` | `SchoolClassController@store` | `academics.manage` |
| PUT | `erp/api/academics/classes/{schoolClass}` | `SchoolClassController@update` | `academics.manage` |
| DELETE | `erp/api/academics/classes/{schoolClass}` | `SchoolClassController@destroy` | `academics.manage` |
| PUT | `erp/api/academics/classes/{schoolClass}/subjects` | `ClassSubjectController@sync` | `academics.manage` |
| GET | `erp/api/academics/sections` | `SectionController@index` | open |
| POST | `erp/api/academics/sections` | `SectionController@store` | `academics.manage` |
| PUT | `erp/api/academics/sections/{section}` | `SectionController@update` | `academics.manage` |
| DELETE | `erp/api/academics/sections/{section}` | `SectionController@destroy` | `academics.manage` |
| GET | `erp/api/academics/subjects` | `SubjectController@index` | open |
| POST | `erp/api/academics/subjects` | `SubjectController@store` | `academics.manage` |
| PUT | `erp/api/academics/subjects/{subject}` | `SubjectController@update` | `academics.manage` |
| DELETE | `erp/api/academics/subjects/{subject}` | `SubjectController@destroy` | `academics.manage` |
| (shared) | `erp/api/settings/academic-sessions*` | `AcademicSessionController` | see [settings.md](settings.md) |

### Representative example — Classes

`POST erp/api/academics/classes`
```json
{ "name": "10", "capacity": 40, "sort_order": 10 }
```
Response `201` (from `SchoolClassController::store`'s `$schoolClass->loadCount('sections')->load('sections', 'subjects:id,name,code')`):
```json
{
  "id": 3,
  "name": "10",
  "capacity": 40,
  "sort_order": 10,
  "created_at": "2026-07-31T12:00:00.000000Z",
  "updated_at": "2026-07-31T12:00:00.000000Z",
  "sections_count": 0,
  "sections": [],
  "subjects": []
}
```

### Representative example — Class–Subject sync

`PUT erp/api/academics/classes/3/subjects`
```json
{ "subject_ids": [1, 2, 5] }
```
Response `200`:
```json
{
  "id": 3,
  "name": "10",
  "capacity": 40,
  "sort_order": 10,
  "subjects": [
    { "id": 1, "name": "Mathematics", "code": "MATH" },
    { "id": 2, "name": "English", "code": "ENG" },
    { "id": 5, "name": "Science", "code": "SCI" }
  ]
}
```

## 14. Database Tables

**`branches`**
- PK: `id`. No foreign keys, no unique constraints, no explicit indexes.
- Columns: `name`, `principal` nullable, `phone` nullable, `address` nullable, `status` enum (`active`/`inactive`, default `active`), timestamps.

**`school_classes`**
- PK: `id`. Unique: `name`. No foreign keys.
- Columns: `name` (unique), `capacity` unsignedInteger nullable, `sort_order` unsignedInteger default `0`, timestamps.

**`sections`**
- PK: `id`. FK: `school_class_id` → `school_classes.id`, `cascadeOnDelete()`. Unique (composite): `[school_class_id, name]`.
- Columns: `school_class_id`, `name`, `capacity` unsignedInteger nullable, `class_teacher` string nullable, timestamps.

**`subjects`**
- PK: `id`. Unique: `code`. No foreign keys.
- Columns: `name`, `code` (unique), timestamps.

**`class_subject`**
- PK: `id`. FK: `school_class_id` → `school_classes.id` `cascadeOnDelete()`; `subject_id` → `subjects.id` `cascadeOnDelete()`. Unique (composite): `[school_class_id, subject_id]`.
- Columns: `school_class_id`, `subject_id`, timestamps.

**`academic_sessions`** — see [settings.md §14](settings.md).

## 15. File Storage

None. No page in this module uploads or stores files.

## 16. Background Jobs

None. No `Job` class references `Branch`, `SchoolClass`, `Section`, `Subject`, or `ClassSubject`, and no scheduled command touches this module.

## 17. Notifications

None. No notification/mailable/broadcast is dispatched from any controller in this module.

## 18. Module Dependencies

- **Depends on**: nothing — Branches, Classes, Sections, and Subjects are foundational reference data created early in setup.
- **Depended on by**: Admissions (`admission_enquiries.class_applying_for_id`), People/Students (`students.school_class_id`, `students.section_id`), Teachers (`teachers` migration carries a class/section reference), Exam Management (`exam_schedules.school_class_id`/`section_id`, `questions.subject_id`), Fee Management (`erp_fee_structures` carries a class reference), Meetings (`meetings` migration carries a class/section reference). Academic Sessions is itself owned by Settings but exposed here — see [settings.md](settings.md).

## 19. Screenshots

Not available — this documentation was generated from source code, not a running instance.

## 20. Final Deliverable Summary

Academics defines the structural backbone every other module builds on: Branches (standalone directory, not yet wired to anything else), the shared Academic Sessions resource (fully documented in `settings.md`), the Class→Section hierarchy with a class-scoped section-name uniqueness rule, the Subject catalog, and a many-to-many class↔subject pivot managed by a dedicated full-replace `sync()` endpoint. All writes are gated by `erp.permission:academics.manage`; all reads are open to any authenticated ERP user. Deletes are hard deletes, with a deliberate asymmetry in the migrations — deleting a `SchoolClass` is blocked (`restrictOnDelete`) if students or enquiries reference it, while deleting a `Section` merely nulls out the referencing students' `section_id`. No import/export, file storage, background jobs, or notifications exist anywhere in this module. Eight of the twelve sidebar entries (Class Teachers, Subject Mapping, Curriculum, Lesson Plans, Homework, Timetable, Period Settings, Academic Calendar) remain unimplemented `GenericModule.vue` placeholders.
