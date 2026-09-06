# Hostel

See [README.md](README.md) for cross-cutting architecture (routing, permission model, generic-page mechanism, audit logging, calculator-service pattern) — not repeated here.

## 1. Module Overview

Hostel manages residential capacity and billing: define **Rooms** (type, capacity, monthly fee), break each room into individual **Beds**, allocate a specific bed to a specific student (`Hostel Students` — see the naming note below), log **Hostel Visitors** coming to see a resident with a check-in/check-out time, and run a bulk, period-based **Hostel Fee** billing cycle against active allocations. **Hostel Reports** is a live, computed occupancy/revenue snapshot — nothing is stored; it is recalculated on every request by `App\Services\HostelReportCalculator` (mirrors `FeeCalculator`/`TransportReportCalculator` elsewhere in the app).

**Naming clarification — "Hostel Students" does not manage the `Student` entity.** The sidebar leaf labeled "Hostel Students" (`/hostel/hostel-students` → `HostelStudents.vue`) is entirely backed by `App\Http\Controllers\Erp\Hostel\HostelAllocationController` and the `hostel_allocations` table. It lets an operator pick an existing student and assign them to an available bed (and later vacate them) — it has no fields for editing a student's name, contact info, class, or any other part of their profile (that lives in People → Students, `students.md`... i.e. out of this module entirely). A new developer expecting "Hostel Students" to be a student-profile editor will find only a bed-assignment list and an Allocate/Vacate workflow.

Every model in this module is a plain Eloquent `Model` — none uses `SoftDeletes` — so every delete discussed below is a hard delete unless a DB foreign-key constraint blocks it first (see §9).

## 2. Menu Structure

Menu group `Hostel` (`resources/js/erp/data/menu.js`), 6 children, all real. Route base `/hostel/*`, resolved via `FLAGSHIP_ROUTES` in `resources/js/erp/router/index.js`.

| Menu Label | Path | Real / Generic | Vue Component | Note |
|---|---|---|---|---|
| Rooms | `/hostel/rooms` | Real | `Rooms.vue` | |
| Beds | `/hostel/beds` | Real | `Beds.vue` | |
| Hostel Students | `/hostel/hostel-students` | Real | `HostelStudents.vue` | Manages `HostelAllocation` (bed assignment) rows, **not** the `Student` entity — see §1. |
| Hostel Visitors | `/hostel/hostel-visitors` | Real | `HostelVisitors.vue` | Update route added (see §8) — no Delete-blocking cascade concerns. |
| Hostel Fee | `/hostel/hostel-fee` | Real | `HostelFee.vue` | No plain `store()` — bulk `generate()` + `markPaid()` only (see §9, §10). |
| Hostel Reports | `/hostel/hostel-reports` | Real | `HostelReports.vue` | **Not** dual-reachable — see §12 (this differs from Transport Reports in `transport.md`; confirmed by checking `menu.js`'s `Reports` group children and `FLAGSHIP_ROUTES`, neither lists a `Hostel Reports`/`/reports/hostel-reports` entry). |

All 6 leaves are real; there is no generic/placeholder leaf anywhere in this module.

## 3–5. Pages: URL, Controller, Validation, Permissions, Form Layout, Fields

All reads below require only an authenticated `erp` guard user. All writes require `erp.permission:hostel.manage` (role key `hostel.manage`, label "Hostel" in `resources/js/erp/pages/settings/Roles.vue`).

### 3.1 Rooms (`Rooms.vue`)

- Frontend: `/hostel/rooms`
- Backend: `GET/POST /erp/api/hostel/rooms`, `PUT/DELETE /erp/api/hostel/rooms/{room}`
- Controller: `App\Http\Controllers\Erp\Hostel\RoomController` — `index`, `store`, `update`, `destroy`

Validation (verbatim):
```php
'room_no' => 'required|string|max:50|unique:rooms,room_no',           // update: Rule::unique(...)->ignore($room->id)
'type' => 'required|string|max:50',
'capacity' => 'required|integer|min:1',
'monthly_fee' => 'required|numeric|min:0',
'status' => ['required', Rule::in(['Active', 'Inactive'])],
```

Form Layout: SlideOver — Room No. (text), Type (select: Single/Double/Dormitory) + Capacity (number), side by side, Monthly Fee (number) + Status (select: Active/Inactive), side by side. List page also has a `FilterBar` (Search, Status).

Fields:

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Room No. | `room_no` | `rooms` | string, unique | Required | `required\|string\|max:50\|unique` | — | free text | — |
| Type | `type` | `rooms` | string | Required | `required\|string\|max:50` | `Double` | fixed select (Single/Double/Dormitory) | — |
| Capacity | `capacity` | `rooms` | unsigned int | Required | `required\|integer\|min:1` | `2` | free entry | — |
| Monthly Fee | `monthly_fee` | `rooms` | decimal(10,2) | Required | `required\|numeric\|min:0` | `0` | free entry | — |
| Status | `status` | `rooms` | enum(`Active`,`Inactive`) | Required | `required\|in:Active,Inactive` | `Active` | fixed select | — |

`index` also returns computed (not stored) counters via `withCount`: `beds_count` and `occupied_beds_count` (beds where `status='Occupied'`), used for the Beds column and the page's stat cards.

### 3.2 Beds (`Beds.vue`)

- Frontend: `/hostel/beds`
- Backend: `GET /erp/api/hostel/beds` (optional `room_id` filter), `POST /erp/api/hostel/beds`, `PUT/DELETE /erp/api/hostel/beds/{bed}`
- Controller: `App\Http\Controllers\Erp\Hostel\BedController` — `index`, `store`, `update`, `destroy`

Validation (private `validated()` helper, verbatim):
```php
'room_id' => $bed ? 'sometimes' : 'required|exists:rooms,id',
'bed_no' => [
    'required', 'string', 'max:50',
    Rule::unique('beds', 'bed_no')->where(fn ($q) => $q->where('room_id', $roomId))->ignore($bed?->id),
],
'status' => ['sometimes', Rule::in(['Available', 'Occupied'])],
```
On update, `room_id` is `sometimes` (not required, not typically sent by `Beds.vue`, which never edits it) and the `bed_no` uniqueness check is scoped to the bed's own (or the submitted) `room_id`. `status` is `sometimes` on both create and update — meaning it is entirely valid to `POST`/`PUT` a bed with no `status` at all, in which case the column just falls back to its DB default (`Available`) on create, or is left untouched on update.

Form Layout: top-level Room selector (filters the list; "All rooms" option), SlideOver "Add Bed" — Room selector (only shown if no room is selected at the top level) and Bed No. (text, placeholder "A, B, 1, 2…"). **No Status field on the form at all** — a bed's `status` (`Available`/`Occupied`) is never set directly by an operator here; it is only ever flipped programmatically by the allocate/vacate flow (§7, §10).

Fields:

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Room (selector) | `room_id` | `beds` | bigint FK | Required on create | `required\|exists:rooms,id` (create); `sometimes` (update) | — | `GET /hostel/rooms` | `rooms.id` |
| Bed No. | `bed_no` | `beds` | string | Required | `required\|string\|max:50` + unique per room | — | free text | — |
| Status (not on form) | `status` | `beds` | enum(`Available`,`Occupied`) | — | `sometimes\|in:Available,Occupied` | `Available` | — (system-managed, see §10) | — |

### 3.3 Hostel Students (`HostelStudents.vue`) — manages `HostelAllocation`

- Frontend: `/hostel/hostel-students`
- Backend: `GET/POST /erp/api/hostel/allocations`, `PUT /erp/api/hostel/allocations/{hostelAllocation}`, `PATCH /erp/api/hostel/allocations/{hostelAllocation}/vacate`. **No `DELETE` route or controller method exists** (unchanged — see §9).
- Controller: `App\Http\Controllers\Erp\Hostel\HostelAllocationController` — `index`, `store`, `update`, `vacate`

Validation (`store`, verbatim):
```php
'student_id' => 'required|exists:students,id',
'bed_id' => 'required|exists:beds,id',
'start_date' => 'required|date',
```
Followed by two hand-written checks (both throw `ValidationException` before any DB write):
1. `HostelAllocation::where('student_id', $data['student_id'])->where('status', 'Active')->exists()` → error on `student_id`: *"This student already has an active hostel allocation."*
2. `Bed::findOrFail($data['bed_id'])->status !== 'Available'` → error on `bed_id`: *"This bed is not available."*

Validation (`update`, verbatim — `student_id` is deliberately **not** re-submitted/re-validated, only bed/start_date can change on an existing allocation):
```php
'bed_id' => 'required|exists:beds,id',
'start_date' => 'required|date',
```
`update()` runs in a `DB::transaction()`: if `bed_id` actually changed, the *old* bed is freed to `Available` and the *new* bed must itself be `Available` (else `ValidationException` on `bed_id`, same message as `store`) before being flipped to `Occupied` — this is a plain field-correction edit (wrong student/bed pairing typo), distinct from the vacate-then-reallocate workflow the Student Import upsert performs when a student switches beds across two import runs (`student-import-management.md` §8).

Validation (`vacate`, verbatim):
```php
'end_date' => 'nullable|date|after_or_equal:' . $hostelAllocation->start_date->toDateString(),
```

Form Layout: SlideOver, title "Allocate Bed"/"Edit Allocation" on an `editing` ref — Student (select, filtered client-side to exclude students with an active allocation; **hidden entirely when editing** and shown instead as read-only text, since `update` doesn't accept a new `student_id`), Bed (select; on Add, filtered to `status === 'Available'` beds; on Edit, the allocation's *current* bed is also included in the option list even though it's `Occupied`, so re-selecting the same bed doesn't appear to vanish), Start Date. Each Active row now has an Edit (✏️) action opening the above, plus the pre-existing "🚪 Vacate" button, which now opens its own small SlideOver dialog with an **End Date** field (defaults to today, backdatable) rather than firing a bodyless `PATCH` — added specifically so a vacate that actually happened days earlier can be recorded accurately instead of always stamping "today."

Fields:

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Student | `student_id` | `hostel_allocations` | bigint FK | Required (create only — immutable on edit) | `required\|exists:students,id` + no existing Active allocation | — | `GET /people/students`, filtered client-side | `students.id` |
| Bed | `bed_id` | `hostel_allocations` | bigint FK | Required | `required\|exists:beds,id` + bed must be `Available` (unless it's the allocation's own current bed, on edit) | — | `GET /hostel/beds`, filtered client-side to Available (+ current bed on edit) | `beds.id` |
| Start Date | `start_date` | `hostel_allocations` | date | Required | `required\|date` | today | date picker | — |
| End Date (vacate only) | `end_date` | `hostel_allocations` | date, nullable | Optional | `nullable\|date\|after_or_equal:start_date` | today | date picker, in the Vacate dialog | — |
| Status (system-managed) | `status` | `hostel_allocations` | enum(`Active`,`Inactive`) | — | not user-settable | `Active` on create; forced `Inactive` on vacate | — | — |

### 3.4 Hostel Visitors (`HostelVisitors.vue`)

- Frontend: `/hostel/hostel-visitors`
- Backend: `GET/POST /erp/api/hostel/visitors`, `PUT /erp/api/hostel/visitors/{hostelVisitor}`, `PATCH /erp/api/hostel/visitors/{hostelVisitor}/checkout`, `DELETE /erp/api/hostel/visitors/{hostelVisitor}`.
- Controller: `App\Http\Controllers\Erp\Hostel\HostelVisitorController` — `index`, `store`, `update`, `checkout`, `destroy`

Validation (`store` and `update`, identical, verbatim):
```php
'student_id' => 'required|exists:students,id',
'visitor_name' => 'required|string|max:255',
'relation' => 'nullable|string|max:100',
'purpose' => 'nullable|string|max:255',
'visit_date' => 'required|date',
'in_time' => 'nullable|date_format:H:i',
```
`update` does **not** accept/touch `out_time` — a visitor's checkout state can only change via the dedicated `checkout` action below, never by editing. `checkout` itself still takes no request body — it unconditionally sets `out_time` to `now()->format('H:i')`.

Form Layout: SlideOver, title "Log Visitor"/"Edit Visitor" on an `editing` ref — Student Being Visited (select), Visitor Name (text), Relation / Visit Date (side by side), In Time / Purpose (side by side). Each row now has an Edit (✏️) action in addition to the pre-existing "Check Out" button (shown only while `out_time` is still null; no fields, single click).

Fields:

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Student Being Visited | `student_id` | `hostel_visitors` | bigint FK | Required | `required\|exists:students,id` | — | `GET /people/students` | `students.id` |
| Visitor Name | `visitor_name` | `hostel_visitors` | string | Required | `required\|string\|max:255` | — | free text | — |
| Relation | `relation` | `hostel_visitors` | string, nullable | Optional | `nullable\|string\|max:100` | null | free text | — |
| Purpose | `purpose` | `hostel_visitors` | string, nullable | Optional | `nullable\|string\|max:255` | null | free text | — |
| Visit Date | `visit_date` | `hostel_visitors` | date | Required | `required\|date` | today | date picker | — |
| In Time | `in_time` | `hostel_visitors` | time, nullable | Optional | `nullable\|date_format:H:i` | null | `<input type="time">` | — |
| Out Time (checkout only) | `out_time` | `hostel_visitors` | time, nullable | — | not user input | `now()->format('H:i')` on checkout | — | — |

### 3.5 Hostel Fee (`HostelFee.vue`)

- Frontend: `/hostel/hostel-fee`
- Backend: `GET /erp/api/hostel/fees?period=`, `POST /erp/api/hostel/fees/generate`, `PATCH /erp/api/hostel/fees/{hostelFee}/pay`. **There is no `POST /hostel/fees` plain create route** — a `HostelFee` row can only ever come into existence through the bulk `generate` action, never a single manual entry, and there is no `PUT`/`DELETE` at all.
- Controller: `App\Http\Controllers\Erp\Hostel\HostelFeeController` — `index`, `generate`, `markPaid` only

Validation (`generate`, verbatim):
```php
'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
```
Validation (`markPaid`, verbatim):
```php
'payment_mode' => ['required', Rule::in(['Cash', 'Bank'])],
'paid_on' => 'nullable|date',
```

Form Layout: a Period picker (`<input type="month">`, drives both the list filter and the "⚙️ Generate Fees for {period}" button — no per-row create form) and, per Pending row, a "💳 Mark Paid" action opening a small SlideOver — Payment Mode (select: Cash/Bank), Paid On (date, defaults today).

Fields:

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Period (generate trigger) | `period` | `hostel_fees` | char(7) `YYYY-MM` | Required | `required\|string\|regex:/^\d{4}-\d{2}$/` | current month | `<input type="month">` | — |
| Payment Mode | `payment_mode` | `hostel_fees` | enum(`Cash`,`Bank`), nullable | Required (on pay) | `required\|in:Cash,Bank` | `Cash` (form default) | fixed select | — |
| Paid On | `paid_on` | `hostel_fees` | date, nullable | Optional | `nullable\|date` | today | date picker | — |
| Amount (system-set) | `amount` | `hostel_fees` | decimal(10,2) | — | not user input | copied from `allocation.bed.room.monthly_fee` at generate time | — | — |
| Status (system-set) | `status` | `hostel_fees` | enum(`Pending`,`Paid`) | — | not user input | `Pending` on generate; `Paid` on `markPaid` | — | — |
| Collected By (system-set) | `collected_by_id` | `hostel_fees` | bigint FK, nullable | — | not user input | `Auth::guard('erp')->id()` on `markPaid` | — | `erp_users.id` |

## 6. Database Relationships (Eloquent, as coded)

- `Room hasMany Bed` (`beds()`).
- `Bed belongsTo Room` (`room()`).
- `HostelAllocation belongsTo Student` (`student()`), `belongsTo Bed` (`bed()`), `hasMany HostelFee` (`fees()`).
- `HostelVisitor belongsTo Student` (`student()`).
- `HostelFee belongsTo HostelAllocation` (`allocation()`, FK `hostel_allocation_id`), `belongsTo ErpUser` (`collectedBy()`, FK `collected_by_id`).
- No model in this module declares an inverse relation back onto `Student` — that lives on `Student.php` itself, outside this module.

## 7. Create Flow

**Room**: single-table insert, no side effects — `Room::create($validated)`.

**Bed**: single-table insert — `Bed::create($validated)`. Does not touch the parent `Room` row (capacity is a separate, independently-set number on `Room` — nothing counts beds against it automatically).

**HostelAllocation** (`HostelAllocationController::store`), in the order the controller executes it:
1. Validate the request (see §3.3).
2. Reject if the student already has an `Active` allocation.
3. Reject if the target bed's `status !== 'Available'`.
4. `DB::transaction()`: (a) `$bed->update(['status' => 'Occupied'])` — **allocating a bed does flip its status**, (b) `HostelAllocation::create([...$data, 'status' => 'Active'])`.
5. Return the allocation with `student`, `bed`, `bed.room` eager-loaded.

**HostelVisitor**: single-table insert — `HostelVisitor::create($validated)`. No linkage to `HostelAllocation`/bed data; a visitor log entry only ever references the `Student`, independent of whether that student currently has an active hostel allocation.

**HostelFee via `generate`** (`HostelFeeController::generate`), the module's one bulk-create action:
1. Validate `period` (regex `YYYY-MM`).
2. Load every `HostelAllocation` with `status = 'Active'`, eager-loading `bed.room`.
3. Load the set of `hostel_allocation_id`s that already have a `HostelFee` row for this exact `period`.
4. For each active allocation **not** already in that set: `HostelFee::create(['hostel_allocation_id' => ..., 'period' => ..., 'amount' => $allocation->bed->room->monthly_fee])` — `status` defaults to `Pending` via the DB/model default.
5. Return `{success: true, generated: <count>}`.

This makes `generate` **idempotent per (period, allocation)** — calling it twice for the same month is safe and simply reports `generated: 0` the second time, thanks to both the pre-check in step 3 and the DB unique index `(hostel_allocation_id, period)` on `hostel_fees` as a hard backstop.

## 8. Edit Flow

- **Room**: `PUT` re-validates the full payload (same rules as create, uniqueness re-scoped with `->ignore($room->id)`) and calls `$room->update($data)`.
- **Bed**: `PUT` re-validates (`room_id` is `sometimes`, so a bed can technically be moved between rooms via a direct API call even though the shipped UI never sends `room_id` on edit — `Beds.vue` has no edit form at all, only Add and Delete) and calls `$bed->update($data)`.
- **HostelAllocation**: now supports a plain edit (`PUT /hostel/allocations/{hostelAllocation}` → `update()`) for correcting `bed_id`/`start_date` on an existing allocation — `student_id` is immutable via this route (see §3.3 form layout). This is distinct from `vacate` (§3.3, §10), which flips `status` to `Inactive`, sets `end_date`, and frees the bed as a lifecycle transition rather than a correction. A wrong student on an allocation still has no direct fix — only vacate-and-recreate — but a wrong bed/date no longer requires that.
- **HostelVisitor**: now also supports a plain edit (`PUT /hostel/visitors/{hostelVisitor}` → `update()`) for `student_id`/`visitor_name`/`relation`/`purpose`/`visit_date`/`in_time` — `out_time` remains only settable via the dedicated `checkout` action, never by editing.
- **HostelFee**: **no edit of any kind exists.** No `PUT`, and no way to change `amount`, `period`, or `hostel_allocation_id` once generated — the only mutating action is `markPaid` (§3.5, §10), which only ever sets `status`, `payment_mode`, `paid_on`, `collected_by_id`. This mirrors `SalarySlip`'s "generate in bulk, then mark paid — never freely edited" lifecycle documented for Finance & Payroll.

## 9. Delete Flow

No model in this module uses the `SoftDeletes` trait — every delete below is a **hard delete**, subject to DB foreign-key constraints.

| Entity | Route | Hard/Soft | DB cascade behavior |
|---|---|---|---|
| Room | `DELETE /hostel/rooms/{room}` | Hard | `beds.room_id` is `cascadeOnDelete` — deleting a room deletes all its beds. **But** `hostel_allocations.bed_id` is `restrictOnDelete`, so if any of those beds is (or ever was) referenced by a `HostelAllocation` row, the cascade fails at that second level and the entire room delete is rejected by the DB (an unhandled `QueryException`, not a friendly validation message — the controller does not pre-check this). A room whose beds were never allocated to anyone deletes cleanly. |
| Bed | `DELETE /hostel/beds/{bed}` | Hard | `hostel_allocations.bed_id` is `restrictOnDelete` — a bed currently (or ever) referenced by an allocation cannot be deleted, raw DB constraint error. |
| HostelAllocation | **No delete route exists.** | — | `HostelAllocationController` has no `destroy` method and `routes/erp_api.php`'s `hostel` group defines no `DELETE allocations/{hostelAllocation}`. An allocation, once created, can never be removed — only vacated (§8). This is also why the `restrictOnDelete` on `hostel_allocations.bed_id`/`.student_id` effectively never gets tested by an application-level delete of the allocation itself — only by attempted deletes of the parent `Bed`/`Student`. |
| HostelVisitor | `DELETE /hostel/visitors/{hostelVisitor}` | Hard | No children reference `hostel_visitors`; safe standalone delete. `hostel_visitors.student_id` is `cascadeOnDelete` — deleting a Student deletes their visitor log automatically. |
| HostelFee | **No delete route exists.** | — | No `DELETE` route or `destroy` method. A generated fee row can never be removed, only marked paid (§8). `hostel_fees.hostel_allocation_id` is `cascadeOnDelete` — deleting a `HostelAllocation` row (not currently possible via the app, see above) would cascade-delete its fee history; in practice this is unreachable since allocations themselves cannot be deleted through the UI/API. |

Additionally: `hostel_allocations.student_id` is `cascadeOnDelete` — deleting a `Student` deletes their `HostelAllocation` row(s) (and, transitively, their `HostelFee` rows via the `hostel_allocation_id` cascade above) automatically; it does **not** free the bed first (the bed's `status` column is left as-is by that cascade, since no application code runs — a hard-deleted student's bed can be left stuck at `Occupied` unless someone notices and manually corrects it via a direct `Beds.vue` edit).

## 10. Business Rules

- **A bed must be `Available` to be allocated.** Enforced in `HostelAllocationController::store` with an explicit status check (`$bed->status !== 'Available'` → `ValidationException`) before any write.
- **Allocating a bed flips its status to `Occupied`; vacating flips it back to `Available`.** Both transitions happen inside a `DB::transaction()` alongside the corresponding `HostelAllocation` write, so the bed's status and the allocation's status can never observably diverge from a completed request.
- **One active allocation per student.** `HostelAllocationController::store` checks `HostelAllocation::where('student_id', ...)->where('status', 'Active')->exists()` before allowing a new allocation — a student who already has an active bed cannot be double-booked into another one. (Unlike Transport's `student_transports.student_id`, this is **not** a DB-level unique constraint — a student can accumulate multiple `Inactive` (vacated) allocation rows over time; only one may be `Active` at once, enforced purely in application code.)
- **`hostel_allocations.bed_id` is `NOT NULL`.** A student cannot be assigned to the hostel without a concrete bed — there is no "hostel, room TBD" or "hostel, bed TBD" partial state. This matters for Student Import's best-effort auto-assignment of hostel placement (see `student-import-management.md`) — a row that can't be matched to a specific available bed is simply left unassigned by that importer.
- **`HostelFee` generation is idempotent per (period, allocation)**, both defensively in `generate()`'s own duplicate-check loop and backstopped by the DB unique index `(hostel_allocation_id, period)` on `hostel_fees`. Only `Active` allocations are considered on each `generate` call — a student who has since vacated is not billed for a period after their allocation went `Inactive` (even retroactively for a past-month period the operator re-runs).
- **`HostelFee` amount is a snapshot, not a live calculation.** `amount` is copied from `allocation.bed.room.monthly_fee` at the moment `generate` runs; if the room's `monthly_fee` changes afterward, already-generated `HostelFee` rows for prior periods are unaffected (they keep their originally-generated amount).
- **`HostelFee` lifecycle mirrors `SalarySlip`**: bulk `generate()` creates `Pending` rows for a period, `markPaid()` is the only way to transition a row to `Paid` (capturing `payment_mode`, `paid_on`, and the collecting `erp_users.id`) — there is no free-form create or edit of an individual fee row at any point.

## 11. Import & Export

Per `docs/README.md`'s Import & Export section: only Student has a real importer today, and Export covers 9 entities (`student, fee, expense, salary, bank, fuel-log, route, marks, global`) — none of which is a Hostel entity. For Hostel specifically: **Rooms, Beds, Hostel Students (allocations), Hostel Visitors, and Hostel Fee all have neither Import nor Export.** None of `room`, `bed`, `hostel-allocation`, `hostel-visitor`, or `hostel-fee` appears in `ExportController::ENTITIES`, and no import route or controller targets any Hostel table. This differs from Transport Management, where Route and Fuel Log both have real Export.

## 12. Reports

- **Hostel Reports** (`/hostel/hostel-reports` → `HostelReports.vue`): `GET /erp/api/hostel/reports` → `HostelReportController::index` → `App\Services\HostelReportCalculator::summary()`. Returns: `total_rooms`, `total_beds`, `occupied_beds`, `available_beds` (= `total_beds - occupied_beds`), `occupancy_rate` (`round(occupied/total*100, 1)`, `0` if no beds), `active_students` (count of `Active` `HostelAllocation` rows), `fee_collected_total` (Σ `hostel_fees.amount` where `status='Paid'`), `fee_pending_total` (Σ `hostel_fees.amount` where `status='Pending'`), `visitors_this_month` (count of `hostel_visitors` with `visit_date` in the current month-to-date), `room_occupancy[]` (per-room: capacity, beds_count, occupied_beds_count).
- **No dual reachability — this differs from Transport Reports.** `menu.js`'s `Reports` menu group children are `Student Reports, Admission Reports, Attendance Reports, Fee Reports, Finance Reports, Salary Reports, Transport Reports, Examination Reports, Library Reports, UDISE Reports, Custom Reports, Dashboard Reports` — **no "Hostel Reports" entry.** Correspondingly, `FLAGSHIP_ROUTES` has no `/reports/hostel-reports` mapping. The only sidebar path to `HostelReports.vue` is `/hostel/hostel-reports` under the Hostel menu group itself. (The "Custom Reports" leaf under Reports is itself a generic placeholder unrelated to Hostel — not covered here, see `reports.md`.)

## 13. APIs

All under `/erp/api/hostel/*`; `erp.auth` middleware applies to the whole group; write endpoints additionally require `erp.permission:hostel.manage`.

| Method | Path | Controller@method | Auth |
|---|---|---|---|
| GET | `/hostel/rooms` | `RoomController@index` | any erp user |
| POST | `/hostel/rooms` | `RoomController@store` | `hostel.manage` |
| PUT | `/hostel/rooms/{room}` | `RoomController@update` | `hostel.manage` |
| DELETE | `/hostel/rooms/{room}` | `RoomController@destroy` | `hostel.manage` |
| GET | `/hostel/beds` | `BedController@index` | any erp user |
| POST | `/hostel/beds` | `BedController@store` | `hostel.manage` |
| PUT | `/hostel/beds/{bed}` | `BedController@update` | `hostel.manage` |
| DELETE | `/hostel/beds/{bed}` | `BedController@destroy` | `hostel.manage` |
| GET | `/hostel/allocations` | `HostelAllocationController@index` | any erp user |
| POST | `/hostel/allocations` | `HostelAllocationController@store` | `hostel.manage` |
| PUT | `/hostel/allocations/{hostelAllocation}` | `HostelAllocationController@update` | `hostel.manage` |
| PATCH | `/hostel/allocations/{hostelAllocation}/vacate` | `HostelAllocationController@vacate` | `hostel.manage` |
| GET | `/hostel/visitors` | `HostelVisitorController@index` | any erp user |
| POST | `/hostel/visitors` | `HostelVisitorController@store` | `hostel.manage` |
| PUT | `/hostel/visitors/{hostelVisitor}` | `HostelVisitorController@update` | `hostel.manage` |
| PATCH | `/hostel/visitors/{hostelVisitor}/checkout` | `HostelVisitorController@checkout` | `hostel.manage` |
| DELETE | `/hostel/visitors/{hostelVisitor}` | `HostelVisitorController@destroy` | `hostel.manage` |
| GET | `/hostel/fees` | `HostelFeeController@index` | any erp user |
| POST | `/hostel/fees/generate` | `HostelFeeController@generate` | `hostel.manage` |
| PATCH | `/hostel/fees/{hostelFee}/pay` | `HostelFeeController@markPaid` | `hostel.manage` |
| GET | `/hostel/reports` | `HostelReportController@index` | any erp user |

Example — `POST /erp/api/hostel/allocations` request:
```json
{
  "student_id": 118,
  "bed_id": 27,
  "start_date": "2026-07-31"
}
```
Response (201):
```json
{
  "id": 44,
  "student_id": 118,
  "bed_id": 27,
  "start_date": "2026-07-31",
  "end_date": null,
  "status": "Active",
  "student": { "id": 118, "name": "...", "admission_no": "..." },
  "bed": { "id": 27, "room_id": 5, "bed_no": "B", "room": { "id": 5, "room_no": "H-101" } }
}
```

Example — `POST /erp/api/hostel/fees/generate` request/response:
```json
{ "period": "2026-08" }
```
```json
{ "success": true, "generated": 36 }
```

Example — `GET /erp/api/hostel/reports` response (shape produced by `HostelReportCalculator::summary()`):
```json
{
  "total_rooms": 20,
  "total_beds": 60,
  "occupied_beds": 47,
  "available_beds": 13,
  "occupancy_rate": 78.3,
  "active_students": 47,
  "fee_collected_total": 235000,
  "fee_pending_total": 18000,
  "visitors_this_month": 9,
  "room_occupancy": [
    { "room_no": "H-101", "capacity": 3, "beds_count": 3, "occupied_beds_count": 2 }
  ]
}
```

## 14. Database Tables

| Table | PK | Notable Columns | FKs | Unique / Indexes |
|---|---|---|---|---|
| `rooms` | `id` | `room_no` unique, `type` default `Double`, `capacity` unsigned int default 2, `monthly_fee` decimal(10,2) default 0, `status` enum default `Active` | — | `room_no` unique |
| `beds` | `id` | `bed_no`, `status` enum(`Available`,`Occupied`) default `Available` | `room_id → rooms.id` (cascade on delete) | unique `(room_id, bed_no)` |
| `hostel_allocations` | `id` | `start_date`, `end_date` nullable, `status` enum(`Active`,`Inactive`) default `Active` | `student_id → students.id` (cascade on delete), `bed_id → beds.id` (restrict on delete) | — |
| `hostel_visitors` | `id` | `visitor_name`, `relation` nullable, `purpose` nullable, `visit_date`, `in_time`/`out_time` time nullable | `student_id → students.id` (cascade on delete) | — |
| `hostel_fees` | `id` | `period` char(7) (`YYYY-MM`), `amount` decimal(10,2), `status` enum(`Pending`,`Paid`) default `Pending`, `paid_on` nullable, `payment_mode` enum(`Cash`,`Bank`) nullable | `hostel_allocation_id → hostel_allocations.id` (cascade on delete), `collected_by_id → erp_users.id` nullable (null on delete) | unique `(hostel_allocation_id, period)` |

## 15. File Storage

None. No page or controller in this module uploads, stores, or serves a file — no ID photo, agreement, or visitor-pass file is handled anywhere in Rooms, Beds, Hostel Students (allocations), Hostel Visitors, or Hostel Fee.

## 16. Background Jobs

None. `app/Jobs` does not exist in this codebase. There is no scheduled/queued job that auto-expires overdue `Pending` `hostel_fees` rows, auto-frees a bed after some inactivity window, or reconciles a bed's `status` against its allocation history — everything in this module is written synchronously within the HTTP request that triggered it.

## 17. Notifications

None. No controller in this module calls `Mail::`, `Notification::`, or `->notify(`. Allocating/vacating a bed, logging/checking out a visitor, or generating/collecting a hostel fee notifies no one — the student, their parent, and the visited party receive no email/SMS/push. The only feedback is the client-side `pushToast` confirming the acting user's own action succeeded.

## 18. Module Dependencies

- **Depends on**: People/Student (`students` table — `hostel_allocations.student_id`, `hostel_visitors.student_id`; every allocation and visitor log is keyed off an existing `Student` row, this module has no concept of a "hostel resident" independent of the `Student` entity, per the naming note in §1).
- **Feeds**: nothing outside itself was found to consume Hostel data — no Reports-module cross-reference (§12), no Import/Export entity (§11), no Dashboard-specific hostel widget found in this module's own files.

## 19. Screenshots

Not available — generated from source code.

## 20. Final Deliverable

Hostel is 6 real controllers over 5 tables (`rooms`, `beds`, `hostel_allocations`, `hostel_visitors`, `hostel_fees`) behind 6 sidebar leaves — all real, no generic placeholder anywhere in the module. "Hostel Students" is the one label most likely to mislead a new developer: it is entirely a `HostelAllocation` (bed-assignment) screen, not a student-profile editor. `HostelAllocation` and `HostelVisitor` both gained a plain `update()` in a later revision — a mistaken bed/date on an allocation, or a typo in a visitor's name/relation/purpose, no longer requires deleting and re-creating the row (allocation's `student_id` and visitor's `out_time` remain deliberately immutable via edit, reserved for `vacate`/`checkout` respectively); `HostelFee` alone still has no plain `store()` at all — only bulk, idempotent `generate()` per (period, allocation) plus `markPaid()`, the same generate-then-mark-paid lifecycle as `SalarySlip` in Finance & Payroll. The allocation Vacate action, previously a bodyless one-click `PATCH` that always stamped `end_date` as today, now opens a small dialog with a backdatable End Date field. The core state machine is: allocating a bed sets it `Occupied` and the allocation `Active` in one DB transaction; vacating reverses both; editing an allocation's bed frees the old one and claims the new one in the same transaction. `hostel_allocations.bed_id` is `NOT NULL`, so hostel placement can never be partial (no "room but no bed yet" state) — relevant to Student Import, which now auto-creates Room/Bed rows when unmatched and performs the same vacate-then-create sequence when a re-imported student switches beds (`student-import-management.md` §8). Deleting a `Room` or `Bed` that has ever been allocated to a student fails at the database level (`restrictOnDelete` on `hostel_allocations.bed_id`) even though `Room → Bed` itself cascades — an unhandled constraint error, not a friendly validation message. Neither Import nor Export exists for any Hostel entity (contrast with Transport's real `route`/`fuel-log` exports), and — unlike Transport Reports — Hostel Reports is reachable from exactly one sidebar location, not dual-reachable via the Reports module. No file storage, background jobs, or notifications exist anywhere in this module.
