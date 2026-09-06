# Attendance

Sidebar group: **Attendance** (`resources/js/erp/data/menu.js`, icon `📅`). Backend routes: `routes/erp_api.php`, `Route::prefix('attendance')->name('attendance.')`. Controllers: `app/Http/Controllers/Erp/Attendance/*.php`. See [README.md](README.md) for cross-cutting architecture (routing, permission model, generic-page mechanism, audit logging, morph maps, calculator services) referenced throughout rather than re-explained, and [people.md](people.md) for the Student/Teacher/Staff/Driver entities this module marks present/absent.

## 1. Module Overview

Attendance marks daily presence/absence for the four "attendable" person types defined in People — Students, Teachers, Staff, and Drivers — and manages their leave requests, the school's holiday calendar, and its weekly working-day configuration. It depends entirely on People: there is no Attendance record without a Student/Teacher/Staff/Driver row to attach it to.

Workflow: an authenticated user opens one of the four Attendance Marking screens (Student/Teacher/Staff/Driver Attendance), picks a date, sets a status per person, and saves — this **creates or updates** one `attendances` row per person per day (an upsert, not a plain insert; see Business Rules). Separately, any of the four person types can have a Leave Request submitted on their behalf, which an authorized user approves or rejects (Leave Management/Leave Approval), and — once approved — later marks as "rejoined" (Joining After Leave). Holidays and the weekly off-day configuration (Working Days) are reference data consumed by the Working Days Calculator to compute how many working days fall in a given month, but are **not** automatically consulted by the Attendance Marking screens themselves (see Business Rules — marking attendance on a holiday or weekly-off day is not blocked anywhere in the code). Attendance Reports aggregates the raw `attendances` rows into a per-person Present/Absent/Leave/Late/Half-Day count and percentage for a date range.

Related modules: People (`people.md` — the 4 attendable model classes), Finance & Payroll (Salary Structures/Slips use the same polymorphic person-type pattern for Teacher/Staff/Driver payroll), Reports module (its own "Attendance Reports" leaf reuses this module's exact Vue component and backend endpoint rather than duplicating logic).

## 2. Menu Structure

| Menu label | Path | Status | Component / reason |
|---|---|---|---|
| Student Attendance | `/attendance/student-attendance` | Real | `pages/attendance/AttendanceMarking.vue` (type=`student`) → `AttendanceController` |
| Teacher Attendance | `/attendance/teacher-attendance` | Real | `AttendanceMarking.vue` (type=`teacher`) → `AttendanceController` |
| Staff Attendance | `/attendance/staff-attendance` | Real | `AttendanceMarking.vue` (type=`staff`) → `AttendanceController` |
| Driver Attendance | `/attendance/driver-attendance` | Real | `AttendanceMarking.vue` (type=`driver`) → `AttendanceController` |
| Biometric Attendance | `/attendance/biometric-attendance` | Generic | No biometric-device integration/controller/table exists; falls back to `GenericModule.vue` (mock data, nothing persists). Settings > Biometric Devices is likewise a Generic settings leaf — no real hardware integration anywhere in the app. |
| Leave Management | `/attendance/leave-management` | Real | `pages/attendance/LeaveRequests.vue` (unfiltered) → `LeaveRequestController` |
| Leave Approval | `/attendance/leave-approval` | Real | `LeaveRequests.vue`, client-side filtered to `status === 'Pending'` → same `LeaveRequestController` |
| Joining After Leave | `/attendance/joining-after-leave` | Real | `LeaveRequests.vue`, client-side filtered to `status === 'Approved' && !rejoined_at` → same `LeaveRequestController` |
| Holidays | `/attendance/holidays` | Real | `pages/attendance/Holidays.vue` → `HolidayController` |
| Working Days | `/attendance/working-days` | Real | `pages/attendance/WorkingDays.vue` → `WorkingDayConfigController` |
| Attendance Reports | `/attendance/attendance-reports` | Real | `pages/attendance/AttendanceReports.vue` → `AttendanceReportController` |

Note: Leave Management, Leave Approval, and Joining After Leave are **one Vue component and one backend list endpoint** (`GET /attendance/leave-requests`) presented three ways — the backend does no server-side filtering by these three views; `LeaveRequests.vue` fetches the full list every time and filters client-side by `route.path`. Similarly, Reports > Attendance Reports (`/reports/attendance-reports`) is not a separate implementation — `router/index.js` maps it to the exact same `AttendanceReports.vue` component used by this module's own `/attendance/attendance-reports`.

## 3. The polymorphic "attendable" pattern — how it actually works (read this before anything else in this module)

`Attendance` and `LeaveRequest` (`app/Models/Attendance.php`, `app/Models/LeaveRequest.php`) both have `attendable_type`/`attendable_id` columns and an `attendable(): MorphTo` relation. **There is an `Relation::enforceMorphMap()` registered for exactly these four aliases**, in `App\Providers\AppServiceProvider::boot()`:
```php
Relation::enforceMorphMap([
    'student' => Student::class,
    'teacher' => Teacher::class,
    'staff' => Staff::class,
    'driver' => Driver::class,
]);
```
This is the same morph map used by `SalaryStructure`/`SalarySlip`/`LibraryMember` elsewhere in the app (README's "Polymorphic relationships" section) — it is **not** a special or missing case for Attendance/LeaveRequest; `attendable_type` legitimately stores the plain lowercase strings `student`/`teacher`/`staff`/`driver`, and Eloquent's own `MorphTo::attendable()` resolves them to the right model class automatically via this map, exactly as designed.

**The real gotcha is different and more subtle**: the two attendance controllers (`AttendanceController`, `LeaveRequestController`) never actually use that registered morph map to resolve the `{type}` route-wildcard string coming from the URL (e.g. `/attendance/student`) into a queryable model class. Instead, both controllers `use ResolvesAttendableType` (`app/Http/Controllers/Erp/Attendance/Concerns/ResolvesAttendableType.php`), a trait with its **own independent hardcoded array**:
```php
private const TYPES = [
    'student' => Student::class,
    'teacher' => Teacher::class,
    'staff' => Staff::class,
    'driver' => Driver::class,
];
```
This happens to have the exact same 4 entries as the morph map today, but it is a **separate, duplicate source of truth** — nothing keeps the two in sync automatically. If a 5th attendable type were ever added, both `AppServiceProvider::boot()`'s morph map **and** this trait's `TYPES` constant would need to be updated by hand, or `Attendance::with('attendable')`/route-based lookups would silently disagree about which types are valid (the trait throws `NotFoundHttpException` for unknown types via `attendableModelClass()`; the morph map would throw a different `MorphMapException`-style error). `AttendanceReportController` also uses this same trait for the same purpose. `routes/erp_api.php` additionally hard-restricts the `{type}` wildcard itself with `->whereIn('type', ['student', 'teacher', 'staff', 'driver'])` on both the marking `index`/`store` routes — a **third** place enumerating the same 4 values.

## 4. Pages

### 4.1 Student / Teacher / Staff / Driver Attendance (Attendance Marking)

**Name**: Attendance Marking (one screen, 4 menu entries) · **Frontend URLs**: `/attendance/student-attendance`, `/attendance/teacher-attendance`, `/attendance/staff-attendance`, `/attendance/driver-attendance` · **Backend**: `GET /erp/api/attendance/{type}?date=YYYY-MM-DD`, `POST /erp/api/attendance/{type}` where `{type}` ∈ `student|teacher|staff|driver` · **Controller**: `App\Http\Controllers\Erp\Attendance\AttendanceController` (`index`, `store`) · **Vue**: `pages/attendance/AttendanceMarking.vue`, which derives `type`/title/meta-column-label from `route.path` via a `TYPE_BY_PATH` lookup (falls back to `student` if the path is unrecognized).

**Permissions**: `index` (viewing/marking screen load) open to any authenticated user. `store` (saving marked attendance) requires `erp.permission:attendance.manage`.

**Validation** (`AttendanceController::store()`, verbatim):
```php
'date' => 'required|date',
'records' => 'required|array|min:1',
'records.*.attendable_id' => ['required', 'integer', Rule::exists($table, 'id')],
'records.*.status' => ['required', Rule::in(['Present', 'Absent', 'Leave', 'Late', 'Half Day'])],
'records.*.remarks' => 'nullable|string|max:255',
```
`$table` is resolved per-request from `{type}` via `ResolvesAttendableType` (e.g. `students`, `teachers`, `staff`, `drivers`), so `attendable_id` is validated against the correct person table for the type in the URL.

**Form Layout**: a single-page marking grid, not a SlideOver form — a date picker (defaults to today, re-fetches on change), a "Mark All: [Present] [Absent] [Leave] [Late] [Half Day]" quick-action row, then one table row per person (Code, Name, a type-specific meta column, and 5 status toggle-buttons), and a "Save Attendance" button at the bottom.

**Every Field** (request payload, not a DB-row-per-field form):

| Label | Maps to | Type | Required | Validation | Default | Source |
|---|---|---|---|---|---|---|
| Date | `attendances.date` | date | Yes | date | today | date input |
| Person (one row per) | `attendances.attendable_id` | int | Yes | must exist in the type's own table | — | `GET /attendance/{type}` roster |
| Status | `attendances.status` | enum | Yes | in:Present,Absent,Leave,Late,Half Day | none pre-selected — rows with no status chosen are excluded from the save payload | 5-way toggle buttons |
| Remarks | `attendances.remarks` | string, nullable | No | max:255 | NULL | free-text input per row on the marking grid (a 5th column added alongside Name/Meta/Status/—), submitted as `remarks: p.remarks || null` per record |

**Roster composition per type** (`AttendanceController::index()`): `student` → all Students **with `status = 'Active'`** only (Inactive/Transferred students never appear on the marking grid), eager-loaded with `section`; `teacher` → all Teachers, eager-loaded with `schoolClass`; `staff` → all Staff; `driver` → all Drivers. The "meta" column shown per type: Student → `"{ClassName}-{SectionName}"` (trimmed of stray dashes), Teacher → class name, Staff → `department`, Driver → `vehicle_no`.

**Database Relationships**: `Attendance::attendable()` MorphTo (resolves via the morph map, §3); `Attendance::markedBy()` belongsTo `ErpUser` (`marked_by_id`).

**Create/Update Flow (this is an upsert, not a create-then-edit split)**: for each submitted record, `AttendanceController::store()` calls:
```php
Attendance::updateOrCreate(
    ['attendable_type' => $type, 'attendable_id' => $record['attendable_id'], 'date' => $data['date']],
    ['status' => $record['status'], 'remarks' => $record['remarks'] ?? null, 'marked_by_id' => Auth::guard('erp')->id()]
);
```
The 3-column tuple `(attendable_type, attendable_id, date)` is both the upsert key **and** a DB-level unique constraint (see §Database Tables) — so re-marking the same person on the same date always **overwrites** the previous status/remarks/marked_by rather than creating a duplicate row. There is no separate "edit attendance" screen; re-opening the marking grid for a past date pre-populates each person's current status (`marked?.status`) from any existing row for that date, and re-saving overwrites it the same way.

**Delete Flow**: no delete route/UI exists for individual `attendances` rows anywhere in this module — the only way to remove a marked attendance is indirectly, by deleting the Student/Teacher/Staff/Driver it belongs to (which, per §3, does **not** cascade-delete `attendances` rows since `attendable_id`/`attendable_type` are not true foreign keys — the rows are simply orphaned).

**Business Rules**:
- The unique composite `(attendable_type, attendable_id, date)` guarantees at most one attendance status per person per day — enforced by the DB unique index, not just by application logic.
- Marking is **not** blocked on holidays or weekly-off days — `HolidayController`/`WorkingDayConfigController` data is never consulted by `AttendanceController`; an admin can mark attendance for a declared holiday with no warning or error.
- Only `status = 'Active'` Students appear on the Student Attendance roster; Teachers/Staff/Drivers have no such status filter (an `inactive` Teacher/Staff/Driver still appears and can be marked).
- `marked_by_id` is always overwritten to the current user on every save (even a re-save that doesn't change the status), so it reflects who *last* saved the day's attendance, not necessarily who first marked it.

**Import & Export**: No. The page's "Export" toolbar button is decorative — `simulateExport()` only calls `pushToast(`${pageTitle} export — demo simulation.`, 'info')`, no HTTP request.

**Reports**: Attendance Reports (§4.6, this module) and Reports > Attendance Reports (identical, reused component) both read from the same `attendances` table this screen writes to.

**APIs**:
| Method | Path | Purpose |
|---|---|---|
| GET | `/erp/api/attendance/{type}?date=YYYY-MM-DD` | Roster + that date's existing status per person. |
| POST | `/erp/api/attendance/{type}` | Upsert one or more day's records. |
| GET | `/erp/api/attendance/student/{student}/history` | **New** — `AttendanceController::studentHistory()`. Every marked day for one student (`date`/`status`/`remarks`, newest first) plus a computed `summary` (`present`/`absent`/`leave`/`late`/`half_day` counts, `total_marked`, and `percentage` — same weighting as Attendance Reports: `(present + late + half_day*0.5) / total_marked`). Registered as a literal route ahead of the `{type}` wildcard (matching the existing `leave-requests`/`holidays`/`reports` pattern) so `student/{id}/history`'s extra path segments never collide with it. Powers People > Students' detail-view Attendance tab (`StudentTabAttendance.vue`, see `people.md`) — not used anywhere within this module's own pages. |

Example:
```
GET /erp/api/attendance/student?date=2026-07-31
→ 200
{ "date": "2026-07-31", "people": [
  { "id": 481, "name": "Ananya Sharma", "code": "ADM-2026-0142", "meta": "5-A", "status": "Present", "remarks": null },
  { "id": 482, "name": "Rohan Gupta", "code": "ADM-2026-0143", "meta": "5-A", "status": null, "remarks": null }
]}

POST /erp/api/attendance/student
{ "date": "2026-07-31", "records": [
  { "attendable_id": 481, "status": "Present" },
  { "attendable_id": 482, "status": "Absent", "remarks": "Called in sick" }
]}
→ 200
{ "success": true, "count": 2 }
```

**Database Tables**: `attendances` — PK `id`. `attendable_type` (string, not an FK column — see §3), `attendable_id` (unsigned bigint, not a DB-level FK either, since the target table varies by type), composite index on `(attendable_type, attendable_id)`, **unique** on `(attendable_type, attendable_id, date)`. FK `marked_by_id` → `erp_users.id` (`nullOnDelete`).

**File Storage**: None. **Background Jobs**: None. **Notifications**: None (no absence alert is sent to parents/guardians anywhere in this codebase).

---

### 4.2 Leave Management / Leave Approval / Joining After Leave

**Name**: Leave Requests (one screen, 3 menu entries with different client-side filters) · **Frontend URLs**: `/attendance/leave-management` (all requests), `/attendance/leave-approval` (Pending only), `/attendance/joining-after-leave` (Approved + not yet rejoined) · **Backend**: `GET/POST /erp/api/attendance/leave-requests`, `PATCH /erp/api/attendance/leave-requests/{leaveRequest}/approve|reject|rejoin`, `DELETE /erp/api/attendance/leave-requests/{leaveRequest}` · **Controller**: `App\Http\Controllers\Erp\Attendance\LeaveRequestController` · **Vue**: `pages/attendance/LeaveRequests.vue`.

**Permissions**: `index` open to any authenticated user. `store` (Apply Leave), `approve`, `reject`, `rejoin`, `destroy` all require `erp.permission:attendance.manage`.

**Validation** — `store()`:
```php
'attendable_type' => ['required', Rule::in(['student', 'teacher', 'staff', 'driver'])],
'attendable_id' => 'required|integer',
'leave_type' => 'required|string|max:100',
'from_date' => 'required|date',
'to_date' => 'required|date|after_or_equal:from_date',
'reason' => 'nullable|string|max:1000',
```
plus a second validation pass, `$request->validate(['attendable_id' => [Rule::exists($table, 'id')]])`, where `$table` is resolved from `attendable_type` via `ResolvesAttendableType` — i.e. `attendable_id` must exist in the specific person table matching the chosen type. `reject()`: `'rejection_reason' => 'required|string|max:255'`. `rejoin()`: `'rejoined_at' => 'nullable|date'` (defaults to today if omitted). `approve()` and `destroy()` take no body.

**Form Layout** ("Apply Leave" SlideOver): Person Type (select: Student/Teacher/Staff/Driver — changing it re-fetches the matching people list via `onTypeChange()`), Person (select, populated from `/people/{students|teachers|staff|drivers}`), Leave Type (select: Sick/Casual/Earned/Other — free values, not DB-constrained beyond `string|max:100`), From Date, To Date, Reason (textarea). A second, separate SlideOver ("Reject Leave Request") holds just a Rejection Reason text input, opened from the row-level Reject action.

**Every Field**:

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Person Type | `attendable_type` | leave_requests | string | Yes | in:student,teacher,staff,driver | — | static 4 options | resolved via morph map, §3 |
| Person | `attendable_id` | leave_requests | unsigned bigint | Yes | integer, exists in the type's table | — | `GET /people/{students\|teachers\|staff\|drivers}` | not a DB FK (polymorphic) |
| Leave Type | `leave_type` | leave_requests | string | Yes | max:100 | — | static (Sick/Casual/Earned/Other) — free text, not enforced server-side beyond string/length | — |
| From Date | `from_date` | leave_requests | date | Yes | date | — | — | — |
| To Date | `to_date` | leave_requests | date | Yes | date, ≥ From Date | — | — | — |
| Reason | `reason` | leave_requests | text, nullable | No | max:1000 | NULL | — | — |
| (system) Status | `status` | leave_requests | enum(`Pending`,`Approved`,`Rejected`) | — | — | `Pending` | — | — |
| (system) Rejection Reason | `rejection_reason` | leave_requests | string, nullable | On reject: Yes | max:255 | NULL | — | — |
| (system) Approved By | `approved_by_id` | leave_requests | FK bigint, nullable | — | — | NULL | set to current user on approve/reject | `erp_users.id` (nullOnDelete) |
| (system) Approved At | `approved_at` | leave_requests | datetime, nullable | — | — | NULL | `now()` on approve/reject | — |
| (system) Rejoined At | `rejoined_at` | leave_requests | date, nullable | — | — | NULL | today or a caller-supplied date, on rejoin | — |

**Database Relationships** (`app/Models/LeaveRequest.php`): `attendable()` MorphTo (§3); `approvedBy()` belongsTo `ErpUser` (`approved_by_id`).

**Create Flow**: single insert into `leave_requests` with `status = 'Pending'` (migration default). The list response's `attendable_name`/`attendable_code` fields are computed at response time by `LeaveRequestController::present()`, not stored columns: `attendable?->name` and `attendable?->admission_no ?? attendable?->employee_id`.

**Edit Flow**: There is no generic "edit a leave request" endpoint — the only mutations after creation are the four explicit state-transition actions below. None of `attendable_type`, `attendable_id`, `leave_type`, `from_date`, `to_date`, or `reason` can be changed after submission; a mistaken request must be deleted and resubmitted.

**Delete Flow**: hard delete (`$leaveRequest->delete()`), available on a request in any status (not restricted to Pending) — deleting an Approved leave request does not affect any `attendances` rows (there is no automatic linkage between an approved leave and marking that person "Leave" on the marking grid for those dates; the two are entirely independent data).

**Business Rules — the approval workflow**:
- States: `Pending → Approved` or `Pending → Rejected` (both terminal from the state-machine's perspective except for the rejoin step below). There is no `Rejected → Approved` path or any other reversal.
- `approve()`: sets `status = 'Approved'`, `approved_by_id = current user`, `approved_at = now()`. No rejection reason field is touched.
- `reject()`: requires a `rejection_reason`; sets `status = 'Rejected'`, `rejection_reason`, `approved_by_id`, `approved_at` (the same "who/when actioned this" pair is reused for both approvals and rejections).
- `rejoin()`: only meaningful after `Approved`; sets `rejoined_at`. This is a **separate, independent** boolean-ish signal from `status` — an Approved leave with `rejoined_at = null` shows up on "Joining After Leave"; once `rejoined_at` is set it drops off that view but the `status` column itself never changes to anything like "Completed". `rejoin()` has no guard preventing it from being called on a Pending or Rejected leave (the UI simply never shows the "Mark Rejoined" button unless `status === 'Approved' && !rejoined_at`, but the backend route itself does not re-check status).
- The 3 menu views are purely client-side filters over one `GET /attendance/leave-requests` response: Leave Management = no filter, Leave Approval = `status === 'Pending'`, Joining After Leave = `status === 'Approved' && !rejoined_at`.
- Leave dates are **not** cross-checked against the Holidays calendar, Working Day configuration, or that person's existing `attendances` rows — a leave can be requested for a date that's already marked Present, with no conflict warning.

**Import & Export**: No. **Reports**: None dedicated — leave data does not feed into Attendance Reports' Present/Absent/Leave/Late/Half-Day counts unless someone has also separately marked that person's daily status as `Leave` on the Attendance Marking grid (the two datasets, `leave_requests` and `attendances`, are independent as noted above).

**APIs**:
| Method | Path | Purpose |
|---|---|---|
| GET | `/erp/api/attendance/leave-requests` | List (all types/statuses in one call; supports `?type=` and `?status=` query filters, though the Vue page doesn't use them — it filters client-side instead). |
| POST | `/erp/api/attendance/leave-requests` | Apply leave. |
| PATCH | `/erp/api/attendance/leave-requests/{id}/approve` | Approve. |
| PATCH | `/erp/api/attendance/leave-requests/{id}/reject` | Reject (body: `rejection_reason`). |
| PATCH | `/erp/api/attendance/leave-requests/{id}/rejoin` | Mark rejoined (body: optional `rejoined_at`). |
| DELETE | `/erp/api/attendance/leave-requests/{id}` | Hard delete. |

Example:
```
POST /erp/api/attendance/leave-requests
{ "attendable_type": "teacher", "attendable_id": 12, "leave_type": "Sick", "from_date": "2026-08-03", "to_date": "2026-08-05", "reason": "Fever" }
→ 201
{ "id": 77, "attendable_type": "teacher", "attendable_id": 12, "leave_type": "Sick",
  "from_date": "2026-08-03", "to_date": "2026-08-05", "reason": "Fever", "status": "Pending",
  "approved_by_id": null, "approved_at": null, "rejection_reason": null, "rejoined_at": null,
  "attendable_name": "Meena Iyer", "attendable_code": "TCH-0012" }
```

**Database Tables**: `leave_requests` — PK `id`. `attendable_type`/`attendable_id` (not a DB FK, polymorphic — index on the pair). FK `approved_by_id` → `erp_users.id` (nullOnDelete). No unique constraint (a person can have overlapping or duplicate leave requests; nothing prevents it).

**File Storage**: None (no medical-certificate/document attachment for leave). **Background Jobs**: None. **Notifications**: None (no email/SMS to the requester on approval/rejection).

---

### 4.3 Holidays

**Name**: Holidays · **Frontend URL**: `/attendance/holidays` · **Backend**: `GET/POST /erp/api/attendance/holidays`, `PUT/DELETE /erp/api/attendance/holidays/{holiday}` · **Controller**: `App\Http\Controllers\Erp\Attendance\HolidayController` · **Vue**: `pages/attendance/Holidays.vue`.

**Permissions**: `index` open to any authenticated user; `store`/`update`/`destroy` require `attendance.manage`.

**Validation** (identical on store/update except the unique-ignore):
```php
'name' => 'required|string|max:255',
'date' => ['required', 'date', Rule::unique('holidays', 'date')->ignore($holiday?->id)],
'type' => 'nullable|string|max:100',
```

**Form Layout**: Name, Date, Type (free-text input, placeholder "National, Festival, Weekly…" — not a constrained dropdown despite the placeholder implying categories).

**Every Field**:

| Label | DB Column | Table | Type | Required | Validation | Default | FK |
|---|---|---|---|---|---|---|---|
| Name | `name` | holidays | string | Yes | max:255 | — | — |
| Date | `date` | holidays | date | Yes | date, unique | — | — |
| Type | `type` | holidays | string, nullable | No | max:100, freeform | NULL | — |

**Database Relationships**: None — standalone reference table, no FKs in or out.

**Create/Edit Flow**: single insert/update. Only one holiday can exist per calendar date (DB-level unique index on `date`, not just per-name).

**Delete Flow**: hard delete, no cascade concerns.

**Business Rules**: `date` is globally unique — you cannot declare two different holidays (e.g. two different festivals) falling on the same date; the second attempt is rejected with a validation error. As noted in §4.1, declaring a holiday here does **not** automatically block or affect attendance marking for that date on the Attendance Marking screens — its only real consumer is the Working Days Calculator (§4.4).

**Import & Export**: No. **Reports**: consumed by Working Days' calculator, and indirectly informs anyone reading Attendance Reports why a person's `total_marked` days for a month might be less than the calendar-day count (nothing in the report itself references Holidays directly — see §4.4 and §4.6).

**APIs**: GET/POST `/erp/api/attendance/holidays`, PUT/DELETE `.../{holiday}`. Example:
```
POST /erp/api/attendance/holidays
{ "name": "Independence Day", "date": "2026-08-15", "type": "National" }
→ 201
{ "id": 5, "name": "Independence Day", "date": "2026-08-15", "type": "National" }
```
**Database Tables**: `holidays` — PK `id`, unique `date`, no FKs. **File Storage / Background Jobs / Notifications**: None.

---

### 4.4 Working Days

**Name**: Working Days · **Frontend URL**: `/attendance/working-days` · **Backend**: `GET/PUT /erp/api/attendance/working-days?month=YYYY-MM` · **Controller**: `App\Http\Controllers\Erp\Attendance\WorkingDayConfigController` (`show`, `update`) · **Vue**: `pages/attendance/WorkingDays.vue`.

**Permissions**: `show` (GET) open to any authenticated user; `update` (PUT) requires `attendance.manage`.

**Validation** — `update()`:
```php
'weekly_off_days' => 'required|array',
'weekly_off_days.*' => Rule::in(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']),
```

**Form Layout**: two side-by-side cards — "Weekly Off Days" (7 checkboxes, one per weekday, + a "Save Configuration" button) and a read-only "Working Days Calculator" (a month picker that re-fetches `GET .../working-days?month=` and displays Days in Month / Working Days / Off Days as stat cards).

**Every Field**:

| Label | DB Column | Table | Type | Required | Validation | Default |
|---|---|---|---|---|---|---|
| Weekly Off Days | `weekly_off_days` | working_day_configs | JSON array of weekday names | Yes | array of the 7 valid English weekday names | `["Sunday"]` (set by `WorkingDayConfig::current()`'s `firstOrCreate`) |

**Database Relationships**: None.

**Singleton pattern**: `WorkingDayConfig::current()` = `static::firstOrCreate(['id' => 1], ['weekly_off_days' => ['Sunday']])` — there is deliberately only ever one row in `working_day_configs` (hardcoded `id => 1`), representing one global school-wide weekly-off configuration; there is no per-branch or per-class variation.

**Create Flow**: implicit, on first read — the singleton row is auto-created with `['Sunday']` the first time anyone requests it if it doesn't already exist.

**Edit Flow**: `update()` replaces `weekly_off_days` wholesale on the singleton row (`$config->update($data)`); there is no per-day toggle endpoint.

**Delete Flow**: no delete route exists for this resource at all — it cannot be removed via the API, only ever created (once) and updated.

**Business Rules — the calculator (`show()`)**: for the requested `month` (`Y-m`, default current month), it iterates every calendar day and classifies each as an "off day" if either (a) its weekday name appears in `weekly_off_days`, **or** (b) its date appears in `Holiday::whereYear()->whereMonth()` for that month — everything else counts as a "working day". This is the **only** place in the entire Attendance module where Holidays and Working-Day config are combined into a single computation; it is a pure `Days in Month = Working Days + Off Days` client-facing statistic and does not write anything back to `attendances` or any other table (no calendar of "expected working days" is persisted for later comparison against actual attendance).

**Import & Export**: No. **Reports**: feeds only its own on-page calculator; not referenced by Attendance Reports.

**APIs**:
| Method | Path | Purpose |
|---|---|---|
| GET | `/erp/api/attendance/working-days?month=YYYY-MM` | Returns `weekly_off_days`, `month`, `days_in_month`, `working_days`, `off_days`. |
| PUT | `/erp/api/attendance/working-days` | Replace `weekly_off_days`. |

Example:
```
GET /erp/api/attendance/working-days?month=2026-08
→ 200
{ "weekly_off_days": ["Sunday"], "month": "2026-08", "days_in_month": 31, "working_days": 27, "off_days": 4 }
```
**Database Tables**: `working_day_configs` — PK `id` (only row `id=1` is ever used in practice), `weekly_off_days` JSON, nullable. No FKs. **File Storage**: None. **Background Jobs**: None. **Notifications**: None.

---

### 4.5 Attendance Reports

**Name**: Attendance Reports · **Frontend URL**: `/attendance/attendance-reports` (and, identically, Reports > `/reports/attendance-reports` — same component, same endpoint, see §2) · **Backend**: `GET /erp/api/attendance/reports?type=&from=&to=` · **Controller**: `App\Http\Controllers\Erp\Attendance\AttendanceReportController` (`index`) · **Vue**: `pages/attendance/AttendanceReports.vue`.

**Permissions**: open to any authenticated user (read-only page, no write endpoints — not gated by `attendance.manage` or `reports.view`).

**Validation**: none — `type` (default `student`), `from` (default first of current month), `to` (default today) are read from the query string with no server-side validation; an invalid `type` outside `student|teacher|staff|driver` throws `NotFoundHttpException` via `ResolvesAttendableType::attendableModelClass()`.

**Form Layout**: not a CRUD form — a filter bar (Type select, From date, To date, client-side Search-by-name text input) above a read-only results table.

**Business logic (`AttendanceReportController::index()`)**: for every person of the selected `type`, it pulls all their `attendances` rows within `[from, to]`, groups by `attendable_id`, and computes: `present`, `absent`, `leave`, `late`, `half_day` (raw counts per status), `total_marked` (sum of all statuses recorded in the range — **not** the number of calendar/working days in the range, so a person with only 5 marked days out of a 20-working-day month shows `total_marked = 5`, not 20), and `percentage = round(((present + late + half_day * 0.5) / total_marked) * 100, 1)` — i.e. Late counts as a full present day and Half Day counts as 0.5 toward the percentage; Absent and Leave count as 0. If `total_marked === 0` the percentage is `0` rather than `null` or `100`.

**Every Field** (response row, not an input form):

| Label | Source | Computed as |
|---|---|---|
| Code | `admission_no` or `employee_id` | direct from person record |
| Name | `name` | direct |
| Present | count of `status='Present'` rows in range | — |
| Absent | count of `status='Absent'` rows in range | — |
| Leave | count of `status='Leave'` rows in range | — |
| Late | count of `status='Late'` rows in range | — |
| Half Day | count of `status='Half Day'` rows in range | (labelled `half_day`) |
| Attendance % | `(Present + Late + HalfDay×0.5) / total_marked × 100`, rounded to 1dp | 0 if nothing was ever marked in range |

**Database Relationships**: reads `Attendance` rows via `attendable_type`/`attendable_id` grouping — same non-FK polymorphic pattern as §4.1 (`app/Services/*Calculator.php`-style "computed, not stored" pattern per README, though this particular calculation lives directly in the controller rather than a dedicated `*Calculator` service class).

**Create/Edit/Delete Flow**: N/A — this is a pure read/aggregation view; it writes nothing.

**Business Rules**: "Below 75%" and "Perfect Attendance" stat cards on the page are computed client-side from the `percentage`/`total_marked` fields already returned by the API, with `total_marked > 0` as a guard so people with zero marked days don't inflate the "Perfect Attendance" (100%) count. The `type` dropdown defaults to `student` and lists all 4 attendable types every time — it does **not** remember the type selected on the standalone Attendance Marking screens.

**Import & Export**: No (this is itself the reporting view — there is no separate "export this report" button; it's a plain data table).

**Reports**: This page *is* the report. It is reused verbatim at Reports > Attendance Reports (`/reports/attendance-reports`) per the router mapping in §2 — one Vue file, one backend endpoint, two menu entries.

**APIs**:
| Method | Path | Purpose |
|---|---|---|
| GET | `/erp/api/attendance/reports?type=student&from=2026-07-01&to=2026-07-31` | Aggregated per-person attendance summary for the range. |

Example:
```
GET /erp/api/attendance/reports?type=student&from=2026-07-01&to=2026-07-31
→ 200
{ "type": "student", "from": "2026-07-01", "to": "2026-07-31", "rows": [
  { "id": 481, "name": "Ananya Sharma", "code": "ADM-2026-0142", "present": 22, "absent": 1,
    "leave": 0, "late": 2, "half_day": 0, "total_marked": 25, "percentage": 96.0 }
]}
```
**Database Tables**: reads-only, no table of its own — see `attendances` in §4.1. **File Storage / Background Jobs / Notifications**: None.

## 5. Business Rules Summary (cross-page)

- **Upsert-per-day rule**: one `attendances` row per `(attendable_type, attendable_id, date)`, DB-enforced by a unique index; re-marking overwrites.
- **No cross-checking**: Attendance Marking does not consult Holidays or Working Days config; Leave Requests do not consult Attendance Marking or each other for date overlaps; Working Days' calculator is the *only* place Holidays and weekly-off config are combined, and even that is a display-only computation, never persisted or referenced by Attendance Reports.
- **Leave lifecycle**: `Pending → Approved|Rejected`, then optionally `rejoined_at` set on an Approved leave — a fourth, independent axis, not a `status` value.
- **Dual/triple type enumeration**: the valid attendable types (`student`/`teacher`/`staff`/`driver`) are declared in 3 independent places (`AppServiceProvider`'s morph map, `ResolvesAttendableType::TYPES`, and the route `whereIn()` constraints) — see §3.

## 6. Module Dependencies

Attendance depends entirely on **People**'s four person-type tables (`students`, `teachers`, `staff`, `drivers` — `people.md`) for anything to mark or request leave against; it has no meaningful data without them. It supplies data to: **Reports** module (Attendance Reports leaf reuses this module's own component/endpoint outright, §2/§4.5); **People > Students**' detail-view Attendance tab, which fetches the new `GET /attendance/student/{student}/history` endpoint directly (§4.1) to show one student's real marked history and summary, rather than duplicating that computation client-side. It shares its polymorphic person-type pattern (and the same `AppServiceProvider` morph map) with **Finance & Payroll**'s `SalaryStructure`/`SalarySlip` and **Library**'s `LibraryMember`, though none of those write to or read from `attendances`/`leave_requests` directly. **System > Audit Logs** captures every create/update/delete on `Attendance`, `LeaveRequest`, `Holiday`, and `WorkingDayConfig` automatically via the global audit listener (README's audit-logging section) — no controller in this module writes audit rows itself.

## 7. Screenshots

Not available — generated from source code.

## 8. Final Deliverable

This document covers all 6 distinct real screens in the Attendance module (Attendance Marking — shared by 4 menu entries; Leave Requests — shared by 3 menu entries; Holidays; Working Days; Attendance Reports) end-to-end: routes, controllers, verbatim validation rules, form layout, every field with its DB column/type/default, Eloquent relationships, create/upsert/edit/delete flows, the composite-unique upsert rule, the full leave-approval state machine, and API examples derived from the actual controller code. §3 corrects and precisely documents the polymorphic "attendable" pattern: a real `Relation::enforceMorphMap()` for `student/teacher/staff/driver` **is** registered in `AppServiceProvider` (consistent with README's cross-cutting documentation, and used correctly by both models' `MorphTo` relations) — the actual gotcha is that the attendance controllers resolve the `{type}` URL segment through a **separate, independently hardcoded** trait (`ResolvesAttendableType`) rather than through that registered map, giving the app three parallel, manually-synchronized lists of the same 4 valid types. The one Generic placeholder leaf (Biometric Attendance) is noted with its fallback reason.
