# Import & Export

See [README.md](README.md) for cross-cutting architecture (routing, permission model, generic-page mechanism, audit logging) — not repeated here.

## 1. Module Overview

The Import & Export module is the ERP's bulk data in/out surface. Architecturally it is unusual among the 19 modules: all **22 sidebar leaves** route to the exact same frontend component (`resources/js/erp/pages/ImportExport.vue`) and the exact same base URL (`/import-export`), differentiated only by a `?type=` query string that the component reads to decide which of four internal "views" to render (Import, Export, Logs, or Failed Records). On the backend, five controllers under `app/Http/Controllers/Erp/ImportExport/` back this one page: `ExportController` (9 downloadable entities), `StudentMasterImportController` (the primary student importer), `StudentPenImportController` (a second, narrower importer that only fills in `student_pen`), `ImportExportLogController` (audit trail of every import/export run), and `FailedRecordController` (row-level failure detail).

As of this writing, **Export is fully implemented for 9 entities**; **Import is fully implemented for Student (via Student Master Import) and for Student PEN** (a PEN-only importer with no export counterpart). Every other "X Import" sidebar leaf is a real route into a real page that itself displays an honest in-app notice ("Import isn't available yet for {entity}") rather than faking success — this is a real page, not a `GenericModule.vue` fallback, but its Import capability for non-Student entities does not exist yet.

**Retired**: `StudentImportController` (the original, ad-hoc-format Student Import) is no longer routed — `StudentMasterImportController` replaced it as the primary way student data enters the system, matching the school's real UDISE-format source files. The old controller's file is kept in the codebase (unrouted) purely because it still owns logic Master Import doesn't replicate: parent (father/mother) resolution by phone, Transport/Hostel auto-assignment, and the health/document-received fields on `student_additional_details`. See `student-import-management.md` for the full picture of both the retired importer and its replacement.

## 2. Menu Structure

Menu group `Import & Export` (`resources/js/erp/data/menu.js`), all 22 children resolve to path `/import-export` with a distinct `query.type`:

| Sidebar Label | `?type=` | View rendered | Real? |
|---|---|---|---|
| Global Workbook Import | `global-workbook-import` | Import tab, entity=`global` | Real page; import unsupported for this entity (shows notice) |
| Global Workbook Export | `global-workbook-export` | Export tab | Real — triggers multi-sheet workbook download |
| Student Import | `student-import` | Import tab, entity=`student` | Real — runs `StudentMasterImportController` (the old `StudentImportController` is retired) |
| Student PEN Import | `student-pen-import` | Import tab, entity=`student-pen` | Real — PEN-only, no export counterpart (kept out of the `ENTITIES` list that drives the Export tab) |
| Student Export | `student-export` | Export tab | Real |
| Fee Import | `fee-import` | Import tab, entity=`fee` | Real page; import unsupported (shows notice) |
| Fee Export | `fee-export` | Export tab | Real |
| Expense Import | `expense-import` | Import tab, entity=`expense` | Real page; import unsupported |
| Expense Export | `expense-export` | Export tab | Real |
| Salary Import | `salary-import` | Import tab, entity=`salary` | Real page; import unsupported |
| Salary Export | `salary-export` | Export tab | Real |
| Bank Import | `bank-import` | Import tab, entity=`bank` | Real page; import unsupported |
| Bank Export | `bank-export` | Export tab | Real |
| Fuel Log Import | `fuel-log-import` | Import tab, entity=`fuel-log` | Real page; import unsupported |
| Fuel Log Export | `fuel-log-export` | Export tab | Real |
| Route Import | `route-import` | Import tab, entity=`route` | Real page; import unsupported |
| Route Export | `route-export` | Export tab | Real |
| Marks Import | `marks-import` | Import tab, entity=`marks` | Real page; import unsupported |
| Marks Export | `marks-export` | Export tab | Real |
| Import Logs | `import-logs` | Logs table (direction=Import) | Real |
| Export Logs | `export-logs` | Logs table (direction=Export) | Real |
| Failed Records | `failed-records` | Failed-records table | Real |

All 21 leaves are wired in `router/index.js`'s `FLAGSHIP_ROUTES` via the special-cased path `'/import-export'` (see `menu.js`: `isImportExport` short-circuits every child in this group to the same path + a `type` query). There are **no generic leaves** in this module — every single sidebar entry hits real Vue/backend code, even where the underlying capability (non-Student import) is honestly stated as unavailable rather than mocked.

**Per-row import outcome logging (new)**: previously, a successful import row left no trace beyond the aggregate `success_count` — only failed rows got any stored detail (`ImportFailedRow`). A new `ImportRowLog` table now records a structured outcome for **every** row of every Student Import run, success or failure, and `import_export_logs` gained 5 auto-create counters plus an `ignored_columns` column. This is surfaced as an expandable detail view on the Import Logs screen (§3.2) — see §7/§13/§14 below for the full mechanism, and `student-import-management.md` for what each outcome field actually means for Student Import specifically.

## 3. Pages

### 3.1 Import / Export (`ImportExport.vue`)

- **URL (frontend)**: `/import-export?type=<slug>` (22 distinct query values, one component instance reused by Vue Router across navigations within the group — the component `watch`es the route query and resyncs local state, per its own in-code comment).
- **URL (backend)**:
  - `GET /erp/api/import-export/export/{entity}` → `ExportController::download` (open read, any authenticated user)
  - `POST /erp/api/import-export/import/student-master` → `StudentMasterImportController::store` (gated `erp.permission:import.manage`)
  - `POST /erp/api/import-export/import/student-pen` → `StudentPenImportController::store` (gated `erp.permission:import.manage`)
- **Validation (`StudentMasterImportController::store`, verbatim)**:
  ```php
  'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
  ```
  Per-row validation inside `importRow()` (verbatim):
  ```php
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
  'pincode' => 'nullable|string|max:20',
  'status' => 'nullable|in:Active,Inactive,Transferred',
  'admission_date' => 'nullable|date',
  ```
  `school_class_id` is `nullable` here (unlike the retired importer's `required`) — a row whose Class is `0`/blank simply skips class resolution instead of failing, *unless* it would be creating a brand-new student, in which case it still fails that row (see `student-import-management.md`).

  `StudentPenImportController::store` validates only the file itself the same way; there is no per-row `Validator` — a row either resolves to exactly one student by Name+Class+Section or fails with a specific message (no match / ambiguous match).
- **Permissions**: reads (`export/{entity}`, `logs`, `failed-records`) are open to any authenticated `erp` user — no permission key required, per the explicit comment in `routes/erp_api.php`. Writes (`import/student-master`, `import/student-pen`) require `import.manage` (`Roles.vue`'s `availablePermissions` confirms the key: `{ key: 'import.manage', label: 'Import & Export' }`).

### 3.2 Import Logs / Export Logs (same component, `view === 'logs'`)

- **Backend**: `GET /erp/api/import-export/logs` → `ImportExportLogController::index`, optional `?direction=Import|Export` filter (`$request->filled('direction')` → `where('direction', ...)`), returns all rows `orderByDesc('id')` (no pagination — full result set). **New**: `GET /erp/api/import-export/logs/{importExportLog}/rows` → `ImportExportLogController::rows`, returns that run's `ImportRowLog` rows ordered by `row_number`, fetched lazily (only on expand, not embedded in the list response — see below).
- **Permissions**: open read (both endpoints).
- **New — expandable per-row detail**: each row in the Logs table is now clickable (a chevron, `toggleExpand()`). Expanding a run shows: (1) auto-created-master-data badges built directly from the log's own `classes_created`/`sections_created`/`vehicles_created`/`rooms_created`/`beds_created` counters (only non-zero ones render, "None" otherwise); (2) an amber warning banner listing `ignored_columns` if the uploaded file had any (e.g. "Columns present in this file but never saved anywhere: Caste, Photo"); (3) a per-row table (Row #, Adm No., Status badge, and one short human-readable outcome each for Student/Father/Mother/Additional/Transport/Hostel, or the error message spanning those columns for a failed row), fetched from the new endpoint above and cached client-side per log id (`rowLogsByLogId`) so re-expanding the same run doesn't refetch.

### 3.3 Failed Records (same component, `view === 'failed-records'`)

- **Backend**: `GET /erp/api/import-export/failed-records` → `FailedRecordController::index`, no filters, `with('log:id,entity,filename,created_at')`, `orderByDesc('id')`, no pagination.
- **Permissions**: open read.

## 4. Form Layout

- **Import tab**: an entity-picker grid of 9 icon buttons from `ENTITIES` (`global`, `student`, `fee`, `expense`, `salary`, `bank`, `fuel-log`, `route`, `marks`) plus one extra button from a separate `EXTRA_IMPORT_ENTITIES` array (`student-pen` — kept out of `ENTITIES` since it has no export counterpart and would otherwise pollute the Export tab's card grid). For `student` or `student-pen`, this is followed by a drag-and-drop file dropzone (`.xlsx`/`.xls`/`.csv`, 10 MB max) with an "Import" button that appears once a file is selected, and a 3-stat result panel (Rows / Imported / Failed) plus a failed-rows table after submit — the two entities share the same dropzone markup, differing only in the descriptive copy above it and which endpoint `submitImport()` posts to (looked up from an `IMPORT_ENDPOINTS` map keyed by `activeEntity`). For every other entity, the dropzone is replaced by a plain amber notice box — there is no file input rendered at all for those entities.
- **Export tab**: a grid of 9 cards (one per entity, `global` visually highlighted as "All modules in one file"), each with an "Export" button that triggers an immediate blob download — no form fields, no confirmation step.
- **Logs view**: a read-only table (Entity, Filename, Rows, Success, Failed, By, When) — no filters exposed in the UI beyond the fixed Import/Export split baked into the sidebar leaf itself — but now expandable per row into the run summary + per-row detail described in §3.2.
- **Failed Records view**: a static read-only table (Import, Row #, Row Data as raw JSON, Error) — no filters.

## 5. Fields

The only genuine input fields in this entire module are on the Student Import and Student PEN Import uploads:

| Label | Type | Required | Notes |
|---|---|---|---|
| File | file input (drag-drop or click-to-browse) | Yes | `.xlsx`, `.xls`, `.csv`; max 10 MB; validated server-side by `mimes:xlsx,xls,csv\|max:10240` (same rule for both importers) |

Everything the Student Master Record file's columns map to (52 recognized headers → `students`, the new `student_udise_details`, and the new `student_session_history`) and everything the Student PEN file's columns map to (4 recognized headers, only `student_pen` is ever written) is **out of scope for this file by design** — see **[student-import-management.md](student-import-management.md)** for the complete, verbatim header maps, field-by-field tables, date-parsing rules, and matching rules for both importers (plus the retired `StudentImportController`'s original rules, kept for reference).

No other page in this module has editable fields: Export is a one-click download per entity, Logs/Failed Records are read-only.

## 6. Database Relationships

Owned by this module:
- `ImportExportLog` — `belongsTo(ErpUser::class, 'performed_by_id')` as `performedBy()`; `hasMany(ImportFailedRow::class)` as `failedRows()`; `hasMany(ImportRowLog::class)` as `rowLogs()` (new).
- `ImportFailedRow` — `belongsTo(ImportExportLog::class, 'import_export_log_id')` as `log()`.
- `ImportRowLog` (new) — `belongsTo(ImportExportLog::class, 'import_export_log_id')` as `log()`. `summary` cast to `array`.

Read (not owned) by `ExportController::buildSection()`, per entity:
- `student` → `Student::with(['schoolClass', 'section'])`
- `fee` → `FeePayment::with('student')`
- `expense` → `Expense::with('expenseCategory')`
- `salary` → `SalarySlip::with('employee')` (`employee` is a polymorphic `MorphTo`, resolved via the app's `Relation::enforceMorphMap()` — see README)
- `bank` → `BankTransaction::with('bankAccount')`
- `fuel-log` → `FuelLog::with('vehicle')`
- `route` → `TransportRoute::with('vehicle')`
- `marks` → `Mark::with(['student', 'examSchedule.exam', 'examSchedule.subject'])`

Read/written by `StudentMasterImportController` in addition to the above: `SchoolClass`, `Section` (match/auto-create), `StudentUdiseDetail` (new — 1:1 with `Student`), `StudentSessionHistory` (new — 1:many with `Student`, unique on `[student_id, session]`). Read/written by `StudentPenImportController`: `SchoolClass`, `Section` (match-only, no auto-create), `StudentUdiseDetail` (writes only `student_pen`). The retired `StudentImportController` additionally reads/writes `ParentGuardian`, `StudentAdditionalDetail`, `TransportRoute`, `Vehicle`, `RouteStop`, `StudentTransport`, `Room`, `Bed`, `HostelAllocation` — all still documented in `student-import-management.md` since that logic isn't replicated elsewhere.

## 7. Create Flow

- **Export**: user clicks "Export" on an entity card → `GET /import-export/export/{entity}` → `ExportController::download()` builds an in-memory `PhpOffice\PhpSpreadsheet\Spreadsheet`, streams it back as `.xlsx` via `streamDownload()`, and — in the same request — creates one `ImportExportLog` row (`direction: 'Export'`, `success_count = total_rows`, `failed_count = 0` always, since a read/export can't "fail" a row the way an import can).
- **Import (Student Master)**: user drags/selects the "Student Master Record" workbook → clicks "Import" → `POST /import-export/import/student-master` (multipart) → `StudentMasterImportController::store()` reads the whole file via `SpreadsheetImportReader::read()`, parses every non-blank row (skipping any row with no Adm No.), **sorts all parsed rows by SESSION ascending** (oldest session first, so the last-processed row per student is always their latest session), and calls `importRow()` for each inside its own `DB::transaction()`. A successful row creates-or-updates a `Student` plus its `StudentUdiseDetail` (1:1) and that session's `StudentSessionHistory` row (1:many, keyed by `[student_id, session]`, never overwritten across sessions — only refreshed in place if the same session is re-imported) — see `student-import-management.md` for the full upsert/non-destructive-merge rules. **There is no staging/preview step; upload immediately writes to the live tables.**
- **Import (Student PEN)**: user drags/selects the UDISE portal's "Students Details" export → clicks "Import" → `POST /import-export/import/student-pen` → `StudentPenImportController::store()` reads the file with the header on row 2 (`SpreadsheetImportReader::read($file, 2)`), and for each row resolves exactly one `Student` by Name+Class+Section, writing only `StudentUdiseDetail::student_pen`. No match or an ambiguous (2+) match fails the row instead of guessing.
- Once the whole file has been processed (either importer), `store()` creates one `ImportExportLog` (`direction: 'Import'`), one `ImportRowLog` per processed row (success or failure), and — for failed rows only — one `ImportFailedRow` each. `StudentMasterImportController` also populates `classes_created`/`sections_created` on the log; `StudentPenImportController` leaves all the auto-create counters at their default of 0 (it never auto-creates anything).

## 8. Edit Flow

None. Nothing in this module is editable after the fact — a `Student` created by import is edited afterward through the ordinary People → Students page, not through Import & Export. `ImportExportLog` and `ImportFailedRow` rows themselves have no update endpoint.

## 9. Delete Flow

None — there is no delete endpoint anywhere in this module (no route to delete a log entry or a failed-row record). Both tables only ever grow. At the database level, `import_failed_rows.import_export_log_id` is `cascadeOnDelete()` against `import_export_logs` (so deleting a log via `php artisan tinker`/direct DB access would cascade-delete its failed rows), but no application code ever exercises this.

## 10. Business Rules

- **Reads are open, writes are gated.** Every GET in this module (`export/{entity}`, `logs`, `failed-records`) is reachable by any authenticated ERP user regardless of role; only `POST import/student-master` and `POST import/student-pen` require `import.manage`. This is the module's own explicit design comment in `routes/erp_api.php`.
- **Student Master Import is an upsert, not create-only.** `StudentMasterImportController::importRow()` checks `Student::where('admission_no', ...)->first()` — if found, the row **updates** that student (non-destructively: a blank cell, or the literal text "None", never overwrites existing data — see `student-import-management.md`); if not found, it creates one (requiring a real Class, since a brand-new student can't be created with a null class — see below). Re-importing the same file refreshes already-imported rows/sessions with any new/changed data instead of duplicating them.
- **Rows are processed oldest-session-first.** The source file stacks every academic session a student was ever enrolled in as its own row; `StudentMasterImportController` sorts all parsed rows by `SESSION` ascending before processing, so the student's "current" row in `students` always ends up reflecting their *latest* session regardless of file order.
- **Class = 0/blank is a known quirk, not a real class.** The source file uses `Class = 0` to mean "not tracked, this student already left" (confirmed: only ever appears on `INACTIVE` rows). When a row's Class is `0` or blank: if the student already exists, class/section resolution is skipped entirely (existing values untouched); if the student is brand new, the row fails outright rather than creating a student with a fabricated "Class 0" or a null class.
- **Master-data resolution auto-creates for Class/Section/Vehicle/Academic Session/Parent in Student Master Import — Student PEN Import never auto-creates anything.** `StudentMasterImportController` auto-creates: Class/Section (flat `capacity` of 40, same pattern the retired importer used); `Vehicle` from the Vehicle column (`firstOrCreate` by `vehicle_no`, the literal text "None" treated as blank so it never creates a fake vehicle); `AcademicSession` for every unique `SESSION` value in the file (never setting `is_current` — that stays a manual Settings action); and `ParentGuardian` from Father/Mother Name, matched by exact trimmed name only (a knowingly-accepted risk of merging two unrelated same-named parents, since the file has no phone number to dedupe by). `StudentPenImportController` only ever matches existing Class/Section/Student rows — a name that doesn't resolve, or resolves to more than one student, fails that row instead of guessing or creating.
- **Per-row atomicity.** Each row's writes run in its own `DB::transaction()`, so one bad row (unparseable date, missing class, ambiguous PEN match) never rolls back rows before or after it in the same file.
- **Only Student (via two importers) has real import capability.** Every other entity's Import view is a real, reachable page that explicitly tells the user the capability doesn't exist yet (`"Import isn't available yet for {entity} — only Student and Student PEN Import are currently supported."`) rather than silently accepting a file and doing nothing.
- **Export never "fails" a row.** Because export is a straight `SELECT` + spreadsheet write, `ExportController` always logs `success_count === total_rows` and `failed_count === 0`.
- **The Global Workbook only exports, never imports.** Selecting the `global` entity on the Import tab always shows the "not available" notice — there is no combined importer, only a combined *exporter* that stitches all 8 non-global entities into one multi-sheet `.xlsx` (`ExportController::download('global')` iterates `array_diff(self::ENTITIES, ['global'])` and adds one sheet per entity).

## 11. Import & Export — module capability matrix

This module *is* the ERP's Import/Export engine, so this section documents its own capability per entity rather than pointing elsewhere:

| Entity | Export | Import | Export format | Sheet name (global workbook) |
|---|---|---|---|---|
| Student | Yes | **Yes** (`StudentMasterImportController`) | `.xlsx` | Student |
| Student PEN *(import-only, not in the export card grid)* | No | **Yes** (`StudentPenImportController`, PEN field only) | — | — |
| Fee | Yes | No | `.xlsx` | Fee |
| Expense | Yes | No | `.xlsx` | Expense |
| Salary | Yes | No | `.xlsx` | Salary |
| Bank | Yes | No | `.xlsx` | Bank |
| Fuel Log | Yes | No | `.xlsx` | Fuel Log |
| Route | Yes | No | `.xlsx` | Route |
| Marks | Yes | No | `.xlsx` | Marks |
| Global Workbook | Yes (all 8 above combined) | No | `.xlsx`, one sheet per entity | — |

Per-entity exported columns (from `ExportController::buildSection()`, verbatim header order):
- **Student**: Admission No, Name, Roll No, Class, Section, Gender, DOB, Status, Admission Date, Mobile
- **Fee**: Receipt No, Student, Admission No, Amount, Discount, Fine, Mode, Date, Status
- **Expense**: Voucher No, Category, Title, Amount, Date, Mode
- **Salary**: Employee Type, Employee, Period, Basic, Allowances, Deductions, Net Salary, Status
- **Bank**: Account, Type, Amount, Date, Reference No
- **Fuel Log**: Vehicle, Date, Liters, Cost, Odometer
- **Route**: Route, Start Point, End Point, Vehicle, Status
- **Marks**: Student, Admission No, Exam, Subject, Marks Obtained, Max Marks

For both student importers' full header maps, upsert/duplicate rules, and auto-create rules — plus the retired `StudentImportController`'s original rules, kept for reference — **see [student-import-management.md](student-import-management.md)**.

## 12. Reports

This module has no dedicated report/calculator service of its own. Its two "report-like" surfaces are the Logs and Failed Records tables described above, which are really just filtered/read views over `import_export_logs` / `import_failed_rows`. Cross-module: `DashboardCalculator::pendingTasks()` surfaces a live `ImportFailedRow::count()` as one of the Dashboard's "Pending Tasks" tiles, and `DashboardCalculator::recentActivities()` surfaces the most recent `ImportExportLog` where `direction = 'Import'` as one Dashboard recent-activity line (see [dashboard.md](dashboard.md)).

## 13. APIs

| Method | Path | Controller@method | Auth |
|---|---|---|---|
| GET | `/erp/api/import-export/export/{entity}` | `ExportController@download` | any authenticated user |
| GET | `/erp/api/import-export/logs` | `ImportExportLogController@index` | any authenticated user |
| GET | `/erp/api/import-export/logs/{importExportLog}/rows` | `ImportExportLogController@rows` | any authenticated user |
| GET | `/erp/api/import-export/failed-records` | `FailedRecordController@index` | any authenticated user |
| POST | `/erp/api/import-export/import/student-master` | `StudentMasterImportController@store` | `import.manage` |
| POST | `/erp/api/import-export/import/student-pen` | `StudentPenImportController@store` | `import.manage` |

Example — export request/response:
```
GET /erp/api/import-export/export/student
→ 200 OK
  Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
  Content-Disposition: attachment; filename="student-export-20260730-143210.xlsx"
  (binary .xlsx stream)
```

Example — Student Master import:
```
POST /erp/api/import-export/import/student-master
Content-Type: multipart/form-data
  file: GAS_STUD_MASTER_REC_2026.xlsx

→ 201 Created
{
  "log": {
    "id": 12, "direction": "Import", "entity": "student-master",
    "filename": "GAS_STUD_MASTER_REC_2026.xlsx", "total_rows": 1558,
    "success_count": 1557, "failed_count": 1,
    "classes_created": 2, "sections_created": 3,
    "performed_by_id": 3, "created_at": "2026-08-01T09:14:02.000000Z"
  },
  "failed_rows": [
    { "id": 900, "row_number": 743, "row_data": { "admission_no": "14780", "class": "0", "...": "..." },
      "error_message": "Cannot create a new student: Class is missing or \"0\" for admission no 14780." }
  ]
}
```

Example — per-row detail for a Student Master run:
```
GET /erp/api/import-export/logs/12/rows
→ 200 OK
[
  { "id": 101, "row_number": 2, "status": "Success", "identifier": "14511",
    "summary": {
      "student": { "action": "updated", "id": 422 },
      "udise_detail": { "action": "saved" },
      "session_history": { "action": "created" },
      "auto_created": { "class": false, "section": false }
    },
    "error_message": null },
  { "id": 102, "row_number": 743, "status": "Failed", "identifier": "14780",
    "summary": null, "error_message": "Cannot create a new student: Class is missing or \"0\" for admission no 14780." }
]
```

Example — Student PEN import:
```
POST /erp/api/import-export/import/student-pen
Content-Type: multipart/form-data
  file: 10161510903_Students_Details_2025-26_ARMAN.xlsx

→ 201 Created
{
  "log": { "id": 13, "direction": "Import", "entity": "student-pen",
    "filename": "10161510903_Students_Details_2025-26_ARMAN.xlsx",
    "total_rows": 240, "success_count": 236, "failed_count": 4,
    "performed_by_id": 3, "created_at": "…" },
  "failed_rows": [
    { "id": 950, "row_number": 57, "row_data": { "name": "AFSHAAN AFTAB", "class": "PP-3", "section": "A", "student_pen": "23063246145" },
      "error_message": "Multiple students match this Name, Class and Section — resolve manually." }
  ]
}
```

Example — logs list:
```
GET /erp/api/import-export/logs?direction=Import
→ 200 OK
[ { "id": 12, "direction": "Import", "entity": "student-master", "filename": "GAS_STUD_MASTER_REC_2026.xlsx",
    "total_rows": 1558, "success_count": 1557, "failed_count": 1,
    "classes_created": 2, "sections_created": 3, "vehicles_created": 0, "rooms_created": 0, "beds_created": 0,
    "ignored_columns": null,
    "performed_by": { "id": 3, "name": "Admin User" }, "created_at": "…" } ]
```

## 14. Database Tables

**`import_export_logs`**
| Column | Type | Notes |
|---|---|---|
| id | bigint, PK | |
| direction | enum('Import','Export') | required |
| entity | string | e.g. `student`, `global` |
| filename | string, nullable | |
| total_rows | unsigned int, default 0 | |
| success_count | unsigned int, default 0 | |
| failed_count | unsigned int, default 0 | |
| classes_created | unsigned int, default 0 | new — count of `SchoolClass` rows auto-created this run |
| sections_created | unsigned int, default 0 | new |
| academic_sessions_created | unsigned int, default 0 | new — count of `AcademicSession` rows auto-created this run (Student Master Import only) |
| vehicles_created | unsigned int, default 0 | new |
| rooms_created | unsigned int, default 0 | new |
| beds_created | unsigned int, default 0 | new |
| ignored_columns | json, nullable | new — cast to `array`; human labels of recognized-but-unmapped headers actually present in this specific file (e.g. `["Caste","Photo"]`) |
| performed_by_id | FK → `erp_users.id`, nullable, `nullOnDelete()` | |
| created_at / updated_at | timestamps | |

**`import_failed_rows`**
| Column | Type | Notes |
|---|---|---|
| id | bigint, PK | |
| import_export_log_id | FK → `import_export_logs.id`, `cascadeOnDelete()` | required |
| row_number | unsigned int | required |
| row_data | json | cast to `array` on the model |
| error_message | string | required |
| created_at / updated_at | timestamps | |

**`import_row_logs`** (new)
| Column | Type | Notes |
|---|---|---|
| id | bigint, PK | |
| import_export_log_id | FK → `import_export_logs.id`, `cascadeOnDelete()` | required |
| row_number | unsigned int | required |
| status | enum('Success','Failed') | required |
| identifier | string, nullable | e.g. the row's `admission_no`, for display without joining to the created/updated record |
| summary | json, nullable | cast to `array`; null for failed rows. Structured per-entity outcome — see `student-import-management.md` for what Student Import specifically writes here |
| error_message | string, nullable | set for failed rows only |
| created_at / updated_at | timestamps | |

No unique constraints or secondary indexes beyond the PKs/FKs on any of the three tables. Unlike `import_failed_rows` (which only ever gets failed rows), `import_row_logs` gets exactly one row per processed row of every import run, success or failure — it is the superset; `import_failed_rows` continues to exist unchanged alongside it purely to keep the standalone Failed Records tab (§3.3) working without a rewrite.

**`student_udise_details`** (new — owned by People/Students, written by both student importers)
| Column | Type | Notes |
|---|---|---|
| id | bigint, PK | |
| student_id | FK → `students.id`, unique, `cascadeOnDelete()` | one row per student |
| student_type, minority_group, type_of_impairments, mother_tongue, rte_ews_admission, guardian_name, alternate_mobile, stoppage, vehicle, admission_type, clsl, name_as_per_aadhaar, father_name, mother_name | string, nullable | free text, written non-destructively by Student Master Import |
| bpl_beneficiary, ews_disadvantaged, cwsn, indian_national, aay_beneficiary, hostel, uses_transport, is_repeater | boolean, nullable | parsed from Yes/No; left `null` (not guessed) if the source cell is neither |
| student_state_code, entry_status | string, nullable | reserved — always blank in the source files today |
| student_pen | string, nullable | reserved by Student Master Import; filled in by Student PEN Import |
| created_at / updated_at | timestamps | |

**`student_session_history`** (new — owned by People/Students, written by Student Master Import only)
| Column | Type | Notes |
|---|---|---|
| id | bigint, PK | |
| student_id | FK → `students.id`, `cascadeOnDelete()` | |
| session | string | e.g. `"2026-27"` |
| class_name, section_name | string, nullable | that session's Class/Section, stored raw (not FK-linked — historical snapshot, not a live relationship) |
| roll_no | unsigned int, nullable | |
| status | string, nullable | that session's ACTIVE/INACTIVE, normalized to Active/Inactive |
| promotion_status | string, nullable | raw PROMOTED/LEFT/NEW |
| previous_year_schooling_status, previous_year_class, exam_appeared, exam_result, exam_marks_percent, attendance_days, attendance_percent | string, nullable | |
| created_at / updated_at | timestamps | |

Unique on `[student_id, session]` — one row per student per session, refreshed (non-destructively) rather than duplicated on re-import.

## 15. File Storage

**None.** Uploaded spreadsheets are read entirely into memory by `SpreadsheetImportReader::read()` (via `IOFactory::load($file->getRealPath())` against Laravel's temp upload path) and discarded once the request completes — the original file is never copied into `storage/app` or anywhere persistent. Exports are generated in memory and streamed straight to the browser via `response()->streamDownload()` — never written to disk server-side either. Only the resulting `ImportExportLog`/`ImportFailedRow` *rows* persist, never the files themselves.

## 16. Background Jobs

None. Both import and export run entirely synchronously within the HTTP request that triggers them — there is no queued job, no chunking across requests, and no progress-polling endpoint. A very large file would simply make the request take longer (or hit PHP's execution-time/memory limits) rather than being handed off to a queue.

## 17. Notifications

None. No email, SMS, or in-app notification is sent to anyone as a result of an import or export — the only feedback is the synchronous JSON response rendered directly in the browser.

## 18. Module Dependencies

Import & Export is one of the most cross-cutting modules in the app precisely because Export reads from, and Student Import writes to, tables owned by several other modules:
- **People**: `Student`, `StudentUdiseDetail`, `StudentSessionHistory`, `ParentGuardian` (read + write by Student Master Import — see student-import-management.md §A.6 for the parent-matching tradeoff); `StudentAdditionalDetail`, `ParentGuardian` (read + write by the retired `StudentImportController` too)
- **Academics**: `SchoolClass`, `Section` (Student Master Import: match-or-auto-create; Student PEN Import: match-only)
- **Settings**: `AcademicSession` (Student Master Import auto-creates missing sessions by name, never touches `is_current` — see student-import-management.md §A.3)
- **Fee Management**: `FeePayment` (export read-only)
- **Finance & Payroll**: `Expense`, `SalarySlip`, `BankTransaction` (export read-only)
- **Transport**: `TransportRoute`, `Vehicle`, `RouteStop`, `StudentTransport` (export read-only; the retired `StudentImportController` also writes `StudentTransport`; Student Master Import auto-creates `Vehicle` rows from the Vehicle column but never touches `TransportRoute`/`RouteStop`/`StudentTransport` — Stoppage stays raw text only, no route/assignment is ever created)
- **Hostel**: `Room`, `Bed`, `HostelAllocation` (the retired `StudentImportController` writes these — current importers only store a `hostel` boolean flag on `student_udise_details`, no real allocation)
- **Exam Management**: `Mark`, `ExamSchedule` (export read-only)
- **Account**: `ErpUser` (as `performed_by_id` on every log row)

## 19. Screenshots

Not available — generated from source code.

## 20. Final Deliverable

Import & Export is a single shared frontend page fronting five real backend controllers: a 9-entity export engine (including a combined multi-sheet global workbook) that is fully implemented and gated open-read, and two working student importers — `StudentMasterImportController` (the primary importer, an **upsert** with **auto-create** for Class/Section only, processing every stacked academic-session row oldest-first) and `StudentPenImportController` (a narrow, match-only importer that fills in one field, `student_pen`, and never auto-creates anything) — both gated behind `import.manage`, both fully synchronous with no staging, background job, or file persistence. The original `StudentImportController` is retired (unrouted, kept in the codebase for its unreplicated parent/transport/hostel/health-field logic). Every processed row — success or failure — leaves a structured, queryable trace via `ImportRowLog`, surfaced as an expandable per-run detail view on the Import Logs screen (entity-aware: the retired importer's detailed 6-column breakdown for historical runs, a compact one-line summary for the two current importers). Every one of the module's 22 sidebar leaves is a real, reachable route — none fall back to `GenericModule.vue` — but every "X Import" leaf other than Student and Student PEN honestly reports that import isn't implemented yet rather than faking success. Full field-level detail for all three student importers (two current, one retired) lives in `student-import-management.md`.
