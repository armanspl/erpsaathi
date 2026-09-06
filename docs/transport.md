# Transport Management

See [README.md](README.md) for cross-cutting architecture (routing, permission model, generic-page mechanism, audit logging, calculator-service pattern) — not repeated here.

## 1. Module Overview

Transport Management runs the school's bus/van fleet: define **Routes** with an ordered list of **Route Stops** (each with its own fare and pickup/drop time), assign a **Vehicle** to a route and a **Driver** to a vehicle, log **Vehicle Maintenance** and **Fuel Logs** per vehicle, track **Vehicle Documents** (RC/Insurance/Permit/Fitness/PUC) with expiry dates, and assign **Students** to a specific route + stop (`Student Transport`). **Transport Reports** is a live, computed fleet/route utilization snapshot — nothing about vehicle counts, fare totals, or students-transported is stored; it is recalculated from the operational tables on every request by `App\Services\TransportReportCalculator` (mirrors `FeeCalculator`/`ExamResultCalculator` elsewhere in the app).

On disk the Vue pages live under `resources/js/erp/pages/transport/` (not `transport-management`) — a purely cosmetic folder-naming difference from the sidebar's `/transport-management/*` URL prefix; it has no functional effect.

The Eloquent model for a route is `App\Models\TransportRoute`, not `Route` — its own migration/table is `routes`, but naming the *class* `Route` would collide with Laravel's own `Illuminate\Routing\Route` / `Illuminate\Support\Facades\Route`, which is used pervasively across the app's own routing files (`routes/erp_api.php`, `routes/web.php`) and would create an ambiguous/unusable symbol if a model of the same bare name existed. `App\Models\TransportRoute.php` does not carry an explicit in-file comment stating this (unlike `ErpFeeStructure`, which does), but the reasoning is the same class of problem documented in `fee-management.md` for that model.

**Dead code fix**: `App\Models\Vehicle::routes()` was previously declared as `return $this->hasMany(Route::class);` — with no `use` import for either `App\Models\TransportRoute` or `Illuminate\Routing\Route`, the unqualified `Route::class` resolved to the non-existent `App\Models\Route`, so calling `$vehicle->routes()` would have thrown a `Class "App\Models\Route" not found` error. No controller or Vue page ever actually called this relation (vehicle→route lookups go the other way, via `TransportRoute::with('vehicle')`), so the bug was inert in practice — but it has since been **fixed** to `return $this->hasMany(TransportRoute::class);`, which is what it was always intended to be.

**Investigation note — a real-world "route matching is broken" report was actually missing master data, not a code bug.** During a 291-row Student Import, 172 rows referencing real route names (`CHANP`, `TETARIA`, etc. — ~29 distinct routes) all failed to match. Verified directly against the database: the `routes` table had exactly 1 row (a placeholder) — none of those routes existed yet, so there was nothing to match. The matching logic itself (`TransportRoute::where('name', $row['route'])->first()`) was independently confirmed correct — values are trimmed before comparison and `routes.name` uses the case-insensitive `utf8mb4_unicode_ci` collation. See `student-import-management.md` §11 for the full writeup; the practical implication for this module is that **Route is the one master-data type the Student Import importer deliberately never auto-creates** (Class/Section/Vehicle/Room/Bed all do) — a route needs real `start_point`/`end_point` and at least one real `RouteStop`, neither of which an Excel column can supply, so missing routes have to be created for real, here, before a re-import will assign them.

## 2. Menu Structure

Menu group `Transport Management` (`resources/js/erp/data/menu.js`), 11 children. Route base `/transport-management/*`, resolved via `FLAGSHIP_ROUTES` in `resources/js/erp/router/index.js`.

| Menu Label | Path | Real / Generic | Vue Component | Note |
|---|---|---|---|---|
| Routes | `/transport-management/routes` | Real | `Routes.vue` | Shares component with Driver Assignment (see §3.1). |
| Route Stops | `/transport-management/route-stops` | Real | `RouteStops.vue` | Shares component with Route Fare (see §3.2). |
| Route Fare | `/transport-management/route-fare` | Real | `RouteStops.vue` | Same component/route target as Route Stops — fare is just one field (`fare`) on the same `route_stops` row; the two menu leaves are cosmetic aliases into one page/table. |
| Vehicles | `/transport-management/vehicles` | Real | `Vehicles.vue` | |
| Vehicle Maintenance | `/transport-management/vehicle-maintenance` | Real | `VehicleMaintenance.vue` | No Update route (see §9). |
| Fuel Logs | `/transport-management/fuel-logs` | Real | `FuelLogs.vue` | No Update route (see §9). Real Export entity (`fuel-log`, see §11). |
| Driver Assignment | `/transport-management/driver-assignment` | Real | `Routes.vue` | Same component/route target as Routes — "assigning a driver" here actually means picking the **Vehicle** (whose own `driver_id` already carries the driver) on the route form; there is no separate driver field on `TransportRoute`. |
| Vehicle Documents | `/transport-management/vehicle-documents` | Real | `VehicleDocuments.vue` | No Update route (see §9). Tracks metadata only — no file upload (see §15). |
| Student Transport | `/transport-management/student-transport` | Real | `StudentTransport.vue` | |
| Transport Cards | `/transport-management/transport-cards` | **Generic** | `GenericModule.vue` | Not in `FLAGSHIP_ROUTES` — no backend ID-card entity for transport passes exists. (The same "Transport Cards" label also appears under the **Documents** menu group at `/documents/transport-cards`, which resolves to the real `TransportCards.vue`/`IdCardController`-backed page documented in `documents.md` — this Transport Management leaf is a distinct, unrelated, generic placeholder.) |
| Transport Reports | `/transport-management/transport-reports` | Real | `TransportReports.vue` | Also reachable from the **Reports** menu group (see §12 — confirmed dual reachability). |

10 of 11 leaves are real; only **Transport Cards** falls back to the generic mock-data page.

## 3–5. Pages: URL, Controller, Validation, Permissions, Form Layout, Fields

All reads below require only an authenticated `erp` guard user. All writes require `erp.permission:transport.manage` (role key `transport.manage`, label "Transport" in `resources/js/erp/pages/settings/Roles.vue`).

### 3.1 Routes / Driver Assignment (`Routes.vue`)

- Frontend: `/transport-management/routes` and `/transport-management/driver-assignment` (same component; page title switches on `route.path`, everything else identical)
- Backend: `GET/POST /erp/api/transport/routes`, `PUT/DELETE /erp/api/transport/routes/{route}`
- Controller: `App\Http\Controllers\Erp\Transport\RouteController` — `index`, `store`, `update`, `destroy`

Validation (`store`/`update`, verbatim):
```php
'name' => 'required|string|max:255',
'start_point' => 'required|string|max:255',
'end_point' => 'required|string|max:255',
'vehicle_id' => 'nullable|exists:vehicles,id',
'status' => ['required', Rule::in(['Active', 'Inactive'])],
```

Form Layout: single-column SlideOver — Route Name (text), Start Point / End Point (text, side by side), Assigned Vehicle (select, shows driver name next to each vehicle, "Unassigned" option), Status (select: Active/Inactive).

Fields:

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Route Name | `name` | `routes` | string | Required | `required\|string\|max:255` | — | free text | — |
| Start Point | `start_point` | `routes` | string | Required (`html5 required` only; server rule is `required`) | `required\|string\|max:255` | — | free text | — |
| End Point | `end_point` | `routes` | string | Required | `required\|string\|max:255` | — | free text | — |
| Assigned Vehicle | `vehicle_id` | `routes` | bigint FK, nullable | Optional | `nullable\|exists:vehicles,id` | `null` (Unassigned) | `GET /transport/vehicles` | `vehicles.id` |
| Status | `status` | `routes` | enum(`Active`,`Inactive`) | Required | `required\|in:Active,Inactive` | `Active` | fixed select | — |

### 3.2 Route Stops / Route Fare (`RouteStops.vue`)

- Frontend: `/transport-management/route-stops` and `/transport-management/route-fare` (same component; page title switches on `route.path`)
- Backend: `GET /erp/api/transport/stops?route_id=` (route required to list), `POST /erp/api/transport/stops`, `PUT/DELETE /erp/api/transport/stops/{routeStop}`
- Controller: `App\Http\Controllers\Erp\Transport\RouteStopController` — `index`, `store`, `update`, `destroy`

Validation (private `validated()` helper, verbatim):
```php
'route_id' => $stop ? 'sometimes' : 'required|exists:routes,id',
'stop_name' => 'required|string|max:255',
'sequence_no' => [
    'required', 'integer', 'min:1',
    Rule::unique('route_stops', 'sequence_no')->where(fn ($q) => $q->where('route_id', $routeId))->ignore($stop?->id),
],
'fare' => 'nullable|numeric|min:0',
'pickup_time' => 'nullable|date_format:H:i',
'drop_time' => 'nullable|date_format:H:i',
```
`$routeId` is `$stop?->route_id ?? $request->input('route_id')` — on update, `route_id` is not re-submitted/re-validated (a stop can never be moved to a different route via this form), and the sequence-number uniqueness check is always scoped to that stop's own route.

Form Layout: a top-level Route selector (select, drives the list below and gates the "+ Add Stop" button) plus a SlideOver — Stop Name (text), Sequence No. / Fare (number, side by side), Pickup Time / Drop Time (`<input type="time">`, side by side).

Fields:

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Route (selector, not a form field) | `route_id` | `route_stops` | bigint FK | Required to list/create | `required\|exists:routes,id` (create only) | — | `GET /transport/routes` | `routes.id` |
| Stop Name | `stop_name` | `route_stops` | string | Required | `required\|string\|max:255` | — | free text | — |
| Sequence No. | `sequence_no` | `route_stops` | unsigned int | Required | `required\|integer\|min:1` + unique per route | next available (`stops.length + 1` client-side default) | free entry | — |
| Fare | `fare` | `route_stops` | decimal(8,2) | Optional | `nullable\|numeric\|min:0` | `0` | free entry | — |
| Pickup Time | `pickup_time` | `route_stops` | time, nullable | Optional | `nullable\|date_format:H:i` | null | `<input type="time">` | — |
| Drop Time | `drop_time` | `route_stops` | time, nullable | Optional | `nullable\|date_format:H:i` | null | `<input type="time">` | — |

### 3.3 Vehicles (`Vehicles.vue`)

- Frontend: `/transport-management/vehicles`
- Backend: `GET/POST /erp/api/transport/vehicles`, `PUT/DELETE /erp/api/transport/vehicles/{vehicle}`
- Controller: `App\Http\Controllers\Erp\Transport\VehicleController` — `index`, `store`, `update`, `destroy`

Validation (verbatim):
```php
'vehicle_no' => 'required|string|max:50|unique:vehicles,vehicle_no',                 // update: Rule::unique(...)->ignore($vehicle->id)
'type' => 'required|string|max:50',
'capacity' => 'required|integer|min:1',
'driver_id' => 'nullable|exists:drivers,id',
'status' => ['required', Rule::in(['Active', 'Under Maintenance', 'Inactive'])],
```

Form Layout: SlideOver — Vehicle No. (text), Type (select: Bus/Van/Car/Other) + Capacity (number), side by side, Driver (select, shows employee ID; "Unassigned" option), Status (select: Active/Under Maintenance/Inactive). List page also has a `FilterBar` (Search, Status).

Fields:

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Vehicle No. | `vehicle_no` | `vehicles` | string, unique | Required | `required\|string\|max:50\|unique` | — | free text | — |
| Type | `type` | `vehicles` | string | Required | `required\|string\|max:50` | `Bus` (DB default; form default is also `Bus`) | fixed select (Bus/Van/Car/Other) | — |
| Capacity | `capacity` | `vehicles` | unsigned int | Required | `required\|integer\|min:1` | `20` (form default; DB default is `0`) | free entry | — |
| Driver | `driver_id` | `vehicles` | bigint FK, nullable | Optional | `nullable\|exists:drivers,id` | `null` (Unassigned) | `GET /people/drivers` | `drivers.id` |
| Status | `status` | `vehicles` | enum | Required | `required\|in:Active,Under Maintenance,Inactive` | `Active` | fixed select | — |

### 3.4 Vehicle Maintenance (`VehicleMaintenance.vue`)

- Frontend: `/transport-management/vehicle-maintenance`
- Backend: `GET /erp/api/transport/maintenance?vehicle_id=` (required), `POST /erp/api/transport/maintenance`, `PUT /erp/api/transport/maintenance/{vehicleMaintenance}`, `DELETE /erp/api/transport/maintenance/{vehicleMaintenance}`.
- Controller: `App\Http\Controllers\Erp\Transport\VehicleMaintenanceController` — `index`, `store`, `update`, `destroy`

Validation (`store` and `update`, identical, verbatim):
```php
'vehicle_id' => 'required|exists:vehicles,id',
'type' => ['required', Rule::in(['Service', 'Repair', 'Inspection', 'Other'])],
'description' => 'nullable|string|max:255',
'cost' => 'required|numeric|min:0',
'date' => 'required|date',
'next_due_date' => 'nullable|date|after_or_equal:date',
```

Form Layout: top-level Vehicle selector (gates "+ Add Record" and the list), SlideOver — title switches "Add Maintenance Record"/"Edit Maintenance Record" on an `editing` ref — Type (select), Description (text), Cost / Date (side by side), Next Due Date. An Edit (✏️) row action now sits next to Delete.

Fields:

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Vehicle (selector) | `vehicle_id` | `vehicle_maintenances` | bigint FK | Required | `required\|exists:vehicles,id` | — | `GET /transport/vehicles` | `vehicles.id` |
| Type | `type` | `vehicle_maintenances` | enum | Required | `required\|in:Service,Repair,Inspection,Other` | `Service` | fixed select | — |
| Description | `description` | `vehicle_maintenances` | string, nullable | Optional | `nullable\|string\|max:255` | null | free text | — |
| Cost | `cost` | `vehicle_maintenances` | decimal(10,2) | Required | `required\|numeric\|min:0` | `0` | free entry | — |
| Date | `date` | `vehicle_maintenances` | date | Required | `required\|date` | today | date picker | — |
| Next Due Date | `next_due_date` | `vehicle_maintenances` | date, nullable | Optional | `nullable\|date\|after_or_equal:date` | null | date picker | — |

### 3.5 Fuel Logs (`FuelLogs.vue`)

- Frontend: `/transport-management/fuel-logs`
- Backend: `GET /erp/api/transport/fuel-logs?vehicle_id=` (required), `POST /erp/api/transport/fuel-logs`, `PUT /erp/api/transport/fuel-logs/{fuelLog}`, `DELETE /erp/api/transport/fuel-logs/{fuelLog}`.
- Controller: `App\Http\Controllers\Erp\Transport\FuelLogController` — `index`, `store`, `update`, `destroy`

Validation (`store` and `update`, identical, verbatim):
```php
'vehicle_id' => 'required|exists:vehicles,id',
'date' => 'required|date',
'liters' => 'required|numeric|min:0.01',
'cost' => 'required|numeric|min:0.01',
'odometer_reading' => 'nullable|integer|min:0',
'remarks' => 'nullable|string|max:255',
```

Form Layout: top-level Vehicle selector, SlideOver — title switches "Add Fuel Log"/"Edit Fuel Log" — Liters / Cost (side by side), Date / Odometer (side by side), Remarks. An Edit (✏️) row action now sits next to Delete.

Fields:

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Vehicle (selector) | `vehicle_id` | `fuel_logs` | bigint FK | Required | `required\|exists:vehicles,id` | — | `GET /transport/vehicles` | `vehicles.id` |
| Date | `date` | `fuel_logs` | date | Required | `required\|date` | today | date picker | — |
| Liters | `liters` | `fuel_logs` | decimal(8,2) | Required | `required\|numeric\|min:0.01` | `0` | free entry | — |
| Cost | `cost` | `fuel_logs` | decimal(10,2) | Required | `required\|numeric\|min:0.01` | `0` | free entry | — |
| Odometer | `odometer_reading` | `fuel_logs` | unsigned int, nullable | Optional | `nullable\|integer\|min:0` | null | free entry | — |
| Remarks | `remarks` | `fuel_logs` | string, nullable | Optional | `nullable\|string\|max:255` | null | free text | — |

### 3.6 Vehicle Documents (`VehicleDocuments.vue`)

- Frontend: `/transport-management/vehicle-documents`
- Backend: `GET /erp/api/transport/documents?vehicle_id=` (required), `POST /erp/api/transport/documents`, `PUT /erp/api/transport/documents/{vehicleDocument}`, `DELETE /erp/api/transport/documents/{vehicleDocument}`.
- Controller: `App\Http\Controllers\Erp\Transport\VehicleDocumentController` — `index`, `store`, `update`, `destroy`

Validation (`store` and `update`, identical, verbatim):
```php
'vehicle_id' => 'required|exists:vehicles,id',
'document_type' => ['required', Rule::in(['RC', 'Insurance', 'Permit', 'Fitness', 'PUC', 'Other'])],
'document_no' => 'nullable|string|max:100',
'issue_date' => 'nullable|date',
'expiry_date' => 'required|date',
'remarks' => 'nullable|string|max:255',
```

Form Layout: top-level Vehicle selector, SlideOver — title switches "Add Document"/"Edit Document" — Document Type (select), Document No. (text), Issue Date / Expiry Date (side by side), Remarks. An Edit (✏️) row action now sits next to Delete. **No file/attachment input anywhere on this form.**

Fields:

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Vehicle (selector) | `vehicle_id` | `vehicle_documents` | bigint FK | Required | `required\|exists:vehicles,id` | — | `GET /transport/vehicles` | `vehicles.id` |
| Document Type | `document_type` | `vehicle_documents` | enum | Required | `required\|in:RC,Insurance,Permit,Fitness,PUC,Other` | `Insurance` | fixed select | — |
| Document No. | `document_no` | `vehicle_documents` | string, nullable | Optional | `nullable\|string\|max:100` | null | free text | — |
| Issue Date | `issue_date` | `vehicle_documents` | date, nullable | Optional | `nullable\|date` | null | date picker | — |
| Expiry Date | `expiry_date` | `vehicle_documents` | date | Required | `required\|date` | — | date picker | — |
| Remarks | `remarks` | `vehicle_documents` | string, nullable | Optional | `nullable\|string\|max:255` | null | free text | — |

The list computes a client-side (not stored, not server-computed) "Valid / Expiring Soon / Expired" badge from `expiry_date` vs. `now()` (≤30 days = Expiring Soon) purely for display — see §16 for why this never triggers any real alert on its own.

### 3.7 Student Transport (`StudentTransport.vue`)

- Frontend: `/transport-management/student-transport`
- Backend: `GET/POST /erp/api/transport/student-transport`, `PUT/DELETE /erp/api/transport/student-transport/{studentTransport}`
- Controller: `App\Http\Controllers\Erp\Transport\StudentTransportController` — `index`, `store`, `update`, `destroy`

Validation (verbatim):
```php
// store
'student_id' => ['required', 'exists:students,id', Rule::unique('student_transports', 'student_id')],
'route_id' => 'required|exists:routes,id',
'route_stop_id' => 'required|exists:route_stops,id',
'start_date' => 'required|date',
'status' => ['required', Rule::in(['Active', 'Inactive'])],

// update — student_id is NOT re-submitted/re-validated; a student can never be reassigned
// to a different assignment row via edit, only route/stop/date/status change
'route_id' => 'required|exists:routes,id',
'route_stop_id' => 'required|exists:route_stops,id',
'start_date' => 'required|date',
'status' => ['required', Rule::in(['Active', 'Inactive'])],
```
Both `store` and `update` additionally call a private `assertStopBelongsToRoute($routeId, $routeStopId)` which throws a `ValidationException` on `route_stop_id` ("The selected stop does not belong to the selected route.") if no `route_stops` row exists with that `id` **and** `route_id` together.

Form Layout: SlideOver — Student (select, only shown on Add — hidden entirely once editing since the student can't change), Route (select, resets Stop on change), Stop (select, disabled until a route is chosen, shows fare), Start Date / Status (side by side).

Fields:

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Student | `student_id` | `student_transports` | bigint FK, unique | Required (create only) | `required\|exists:students,id\|unique:student_transports,student_id` | — | `GET /people/students`, filtered client-side to exclude already-assigned students | `students.id` |
| Route | `route_id` | `student_transports` | bigint FK | Required | `required\|exists:routes,id` | — | `GET /transport/routes` | `routes.id` |
| Stop | `route_stop_id` | `student_transports` | bigint FK | Required | `required\|exists:route_stops,id` + must belong to the chosen route | — | `GET /transport/stops?route_id=` per route, aggregated client-side | `route_stops.id` |
| Start Date | `start_date` | `student_transports` | date | Required | `required\|date` | today | date picker | — |
| Status | `status` | `student_transports` | enum | Required | `required\|in:Active,Inactive` | `Active` | fixed select | — |

### 3.8 Transport Reports (`TransportReports.vue`)

- Frontend: `/transport-management/transport-reports` (and `/reports/transport-reports`, see §12)
- Backend: `GET /erp/api/transport/reports`
- Controller: `App\Http\Controllers\Erp\Transport\TransportReportController` — `index` only, delegates to `App\Services\TransportReportCalculator::summary()`

No validation (read-only, fully computed — see §12 for the exact fields returned).

## 6. Database Relationships (Eloquent, as coded)

- `TransportRoute belongsTo Vehicle` (`vehicle()`), `TransportRoute hasMany RouteStop` (`stops()`, `orderBy('sequence_no')`, FK `route_id`).
- `RouteStop belongsTo TransportRoute` (`route()`, FK `route_id`).
- `Vehicle belongsTo Driver` (`driver()`), `Vehicle hasMany VehicleMaintenance` (`maintenances()`), `Vehicle hasMany FuelLog` (`fuelLogs()`), `Vehicle hasMany VehicleDocument` (`documents()`), `Vehicle hasMany TransportRoute` (`routes()`, fixed — see §1).
- `VehicleMaintenance belongsTo Vehicle` (`vehicle()`).
- `FuelLog belongsTo Vehicle` (`vehicle()`).
- `VehicleDocument belongsTo Vehicle` (`vehicle()`).
- `StudentTransport belongsTo Student` (`student()`), `belongsTo TransportRoute` (`route()`, FK `route_id`), `belongsTo RouteStop` (`routeStop()`).
- No model in this module declares an inverse relation back onto `Student` or `Driver` — those live on `Student.php`/`Driver.php` themselves, outside this module.

## 7. Create Flow

**Route / Route Stop / Vehicle / Vehicle Maintenance / Fuel Log / Vehicle Document**: single-table inserts, no side effects — `Model::create($validated)`. No other table is touched (e.g. adding a Fuel Log does not update the vehicle's odometer anywhere else; `odometer_reading` is stored only on that log row).

**Student Transport** (`StudentTransportController::store`):
1. Validate (see §3.7), including the unique-per-student rule.
2. `assertStopBelongsToRoute()` — reject if the stop/route pairing is invalid.
3. Insert one row into `student_transports`.
4. Return with `student`, `route`, `routeStop` eager-loaded.

No fare/billing row is created anywhere else — Student Transport only records the assignment; there is no linked invoice/fee entry generated in Fee Management or elsewhere.

## 8. Edit Flow

- **Route / Route Stop / Vehicle / Student Transport / Vehicle Maintenance / Fuel Logs / Vehicle Documents**: `PUT` re-validates the full payload (same rules as create, with uniqueness re-scoped via `->ignore($id)` where applicable) and calls `$model->update($data)`. All 7 entity types in this module now support Edit — Vehicle Maintenance, Fuel Logs, and Vehicle Documents gained `PUT` routes and an `editing`-ref-driven Add/Edit SlideOver in a later revision (previously create-and-delete only, meaning a mistaken cost/date/expiry could only be fixed by deleting and re-adding).

## 9. Delete Flow

No model in this module uses the `SoftDeletes` trait — every delete below is a **hard delete**.

| Entity | Route | Hard/Soft | DB cascade behavior |
|---|---|---|---|
| Route | `DELETE /transport/routes/{route}` | Hard | `route_stops.route_id` is `cascadeOnDelete` — deleting a route deletes all its stops too. **But** if any of those stops is referenced by a `student_transports.route_stop_id` row (`restrictOnDelete`), the cascade chain fails at that second level and the entire delete is rejected by the DB (surfaces as an unhandled `QueryException`/500 — no friendly validation message in the controller). A route with no student assignments on any of its stops deletes cleanly. `student_transports.route_id` is itself also `restrictOnDelete`, an independent block on the same delete. |
| Route Stop | `DELETE /transport/stops/{routeStop}` | Hard | `student_transports.route_stop_id` is `restrictOnDelete` — a stop currently (or ever) referenced by a student assignment cannot be deleted; fails as a raw DB constraint error. |
| Vehicle | `DELETE /transport/vehicles/{vehicle}` | Hard | `routes.vehicle_id` is `nullOnDelete` (routes keep existing, just become unassigned). `vehicle_maintenances.vehicle_id`, `fuel_logs.vehicle_id`, `vehicle_documents.vehicle_id` are all `cascadeOnDelete` — deleting a vehicle deletes its entire maintenance log, fuel log, and document history. |
| Vehicle Maintenance | `DELETE /transport/maintenance/{vehicleMaintenance}` | Hard | No children reference this table; safe standalone delete. |
| Fuel Log | `DELETE /transport/fuel-logs/{fuelLog}` | Hard | No children; safe standalone delete. |
| Vehicle Document | `DELETE /transport/documents/{vehicleDocument}` | Hard | No children; safe standalone delete. |
| Student Transport | `DELETE /transport/student-transport/{studentTransport}` | Hard | No children reference `student_transports`; safe standalone delete. `student_transports.student_id` is `cascadeOnDelete` from the `students` side — deleting a Student deletes their transport assignment automatically. |

## 10. Business Rules

- **A stop must belong to its route.** Both `StudentTransportController::store` and `::update` call `assertStopBelongsToRoute()`, rejecting any `route_stop_id` that isn't a `route_stops` row with matching `route_id`.
- **Route Stop sequence ordering.** `sequence_no` is `required|integer|min:1` and unique **per route** (`Rule::unique(...)->where('route_id', ...)`), and `TransportRoute::stops()` always eager-loads in `orderBy('sequence_no')` — stop order on a route is driven entirely by this column, not by insertion order or an ID.
- **One active transport assignment per student.** Enforced by a DB unique index on `student_transports.student_id` (the column itself is `unique()` in the migration, not just per-status) and the matching `Rule::unique('student_transports', 'student_id')` validation on create — a student can have **at most one** `student_transports` row ever, active or inactive; there is no way to "re-assign" — the existing row must be edited (§8) instead of a new one created.
- **`student_transports.route_stop_id` (and `route_id`) are `NOT NULL`.** A student cannot be assigned to transport without picking a concrete stop on a concrete route — there is no "transport, stop TBD" state. This matters for Student Import's best-effort auto-assignment of transport (see `student-import-management.md`) — a row that can't be matched to both a route and a stop is simply left unassigned by that importer, it cannot partially create a `student_transports` row.
- **Vehicle Documents expiry status is purely a client-side label.** `VehicleDocuments.vue::expiryStatus()` computes "Valid / Expiring Soon (≤30 days) / Expired" from `new Date(expiry_date) - new Date()` on every render — nothing is stored, and nothing server-side ever flags a document as expired (see §16, no background job checks this either; `TransportReportCalculator` independently recomputes the same 30-day window for its own "Documents Expiring" widget).
- **Maintenance "Next Due" is informational only.** `next_due_date` on `vehicle_maintenances` is never compared against "today" anywhere except inside `TransportReportCalculator::summary()`'s `upcoming_maintenance` list (next 30 days) — there is no automatic status flip on the vehicle or a blocking rule that prevents further use of an overdue vehicle.

## 11. Import & Export

Per `docs/README.md`'s Import & Export section: only Student has a real importer today. For Transport Management specifically:

- **Route** — real Export. `route` is one of `ExportController::ENTITIES`. `GET /erp/api/import-export/export/route` streams an `.xlsx` with columns `Route, Start Point, End Point, Vehicle, Status`, sourced from `TransportRoute::with('vehicle:id,vehicle_no')->orderBy('name')->get()`. Also included as one sheet of the combined `global` export. No Import.
- **Fuel Log** — real Export. `fuel-log` is one of `ExportController::ENTITIES`. `GET /erp/api/import-export/export/fuel-log` streams an `.xlsx` with columns `Vehicle, Date, Liters, Cost, Odometer`, sourced from `FuelLog::with('vehicle:id,vehicle_no')->orderByDesc('date')->get()`. Also included in the `global` export. No Import.
- **Vehicles, Route Stops, Vehicle Maintenance, Vehicle Documents, Student Transport** — neither Import nor Export exists for any of these; they are absent from `ExportController::ENTITIES` and there is no import route for any of them.

Both real exports are XLSX only (PhpSpreadsheet `Xlsx` writer, `response()->streamDownload()` — never written to disk), open to any authenticated user (no `transport.manage` needed to export), and each call logs one row to `import_export_logs`.

## 12. Reports

- **Transport Reports** (`/transport-management/transport-reports` → `TransportReports.vue`): `GET /erp/api/transport/reports` → `TransportReportController::index` → `App\Services\TransportReportCalculator::summary()`. Returns: `total_vehicles`, `active_vehicles`, `total_routes`, `students_using_transport` (count of `status='Active'` `student_transports`), `total_monthly_fare` (Σ of each active route's fare total), `fuel_cost_this_month` (Σ `fuel_logs.cost` for the current month-to-date), `maintenance_cost_this_month` (Σ `vehicle_maintenances.cost` for the current month-to-date), `routes[]` (per-route: vehicle, stops_count, students_count, monthly_fare_total), `upcoming_document_expiries[]` (documents expiring in the next 30 days), `upcoming_maintenance[]` (maintenance due in the next 30 days).
- **Dual reachability — confirmed.** `resources/js/erp/router/index.js`'s `FLAGSHIP_ROUTES` maps **both** `/transport-management/transport-reports` and `/reports/transport-reports` to the same `TransportReports.vue` component, and `menu.js`'s `Reports` group does include a `Transport Reports` child. Both sidebar entries land on the identical page, which always calls the same endpoint, `GET /erp/api/transport/reports` — **not** a `reports.view`-gated endpoint under the `reports` route-group prefix (that group's backend, `routes/erp_api.php` lines ~559–564, only defines `students`, `admissions`, `fees`, `finance` — there is no `reports/transport` route at all). So reaching this page via the Reports menu is purely a second sidebar shortcut; it does not add or require the `reports.view` permission — any authenticated `erp` user can view it either way, same as reaching it via Transport Management.

## 13. APIs

All under `/erp/api/transport/*` unless noted; `erp.auth` middleware applies to the whole group; write endpoints additionally require `erp.permission:transport.manage`.

| Method | Path | Controller@method | Auth |
|---|---|---|---|
| GET | `/transport/routes` | `RouteController@index` | any erp user |
| POST | `/transport/routes` | `RouteController@store` | `transport.manage` |
| PUT | `/transport/routes/{route}` | `RouteController@update` | `transport.manage` |
| DELETE | `/transport/routes/{route}` | `RouteController@destroy` | `transport.manage` |
| GET | `/transport/stops` | `RouteStopController@index` | any erp user |
| POST | `/transport/stops` | `RouteStopController@store` | `transport.manage` |
| PUT | `/transport/stops/{routeStop}` | `RouteStopController@update` | `transport.manage` |
| DELETE | `/transport/stops/{routeStop}` | `RouteStopController@destroy` | `transport.manage` |
| GET | `/transport/vehicles` | `VehicleController@index` | any erp user |
| POST | `/transport/vehicles` | `VehicleController@store` | `transport.manage` |
| PUT | `/transport/vehicles/{vehicle}` | `VehicleController@update` | `transport.manage` |
| DELETE | `/transport/vehicles/{vehicle}` | `VehicleController@destroy` | `transport.manage` |
| GET | `/transport/maintenance` | `VehicleMaintenanceController@index` | any erp user |
| POST | `/transport/maintenance` | `VehicleMaintenanceController@store` | `transport.manage` |
| PUT | `/transport/maintenance/{vehicleMaintenance}` | `VehicleMaintenanceController@update` | `transport.manage` |
| DELETE | `/transport/maintenance/{vehicleMaintenance}` | `VehicleMaintenanceController@destroy` | `transport.manage` |
| GET | `/transport/fuel-logs` | `FuelLogController@index` | any erp user |
| POST | `/transport/fuel-logs` | `FuelLogController@store` | `transport.manage` |
| PUT | `/transport/fuel-logs/{fuelLog}` | `FuelLogController@update` | `transport.manage` |
| DELETE | `/transport/fuel-logs/{fuelLog}` | `FuelLogController@destroy` | `transport.manage` |
| GET | `/transport/documents` | `VehicleDocumentController@index` | any erp user |
| POST | `/transport/documents` | `VehicleDocumentController@store` | `transport.manage` |
| PUT | `/transport/documents/{vehicleDocument}` | `VehicleDocumentController@update` | `transport.manage` |
| DELETE | `/transport/documents/{vehicleDocument}` | `VehicleDocumentController@destroy` | `transport.manage` |
| GET | `/transport/student-transport` | `StudentTransportController@index` | any erp user |
| POST | `/transport/student-transport` | `StudentTransportController@store` | `transport.manage` |
| PUT | `/transport/student-transport/{studentTransport}` | `StudentTransportController@update` | `transport.manage` |
| DELETE | `/transport/student-transport/{studentTransport}` | `StudentTransportController@destroy` | `transport.manage` |
| GET | `/transport/reports` | `TransportReportController@index` | any erp user |
| GET | `/import-export/export/route` | `ExportController@download` | any erp user |
| GET | `/import-export/export/fuel-log` | `ExportController@download` | any erp user |

Example — `POST /erp/api/transport/student-transport` request:
```json
{
  "student_id": 118,
  "route_id": 3,
  "route_stop_id": 14,
  "start_date": "2026-07-31",
  "status": "Active"
}
```
Response (201):
```json
{
  "id": 52,
  "student_id": 118,
  "route_id": 3,
  "route_stop_id": 14,
  "start_date": "2026-07-31",
  "status": "Active",
  "student": { "id": 118, "name": "...", "admission_no": "..." },
  "route": { "id": 3, "name": "Route C - North" },
  "route_stop": { "id": 14, "stop_name": "Market Square", "fare": "350.00" }
}
```

Example — `GET /erp/api/transport/reports` response (shape produced by `TransportReportCalculator::summary()`):
```json
{
  "total_vehicles": 8,
  "active_vehicles": 6,
  "total_routes": 5,
  "students_using_transport": 142,
  "total_monthly_fare": 49700,
  "fuel_cost_this_month": 18500,
  "maintenance_cost_this_month": 6200,
  "routes": [
    { "route_id": 3, "name": "Route C - North", "vehicle": "KA-05-AB-1234", "stops_count": 6, "students_count": 28, "monthly_fare_total": 9800 }
  ],
  "upcoming_document_expiries": [
    { "vehicle": "KA-05-AB-1234", "document_type": "Insurance", "expiry_date": "2026-08-15" }
  ],
  "upcoming_maintenance": [
    { "vehicle": "KA-05-AB-1234", "type": "Service", "next_due_date": "2026-08-10" }
  ]
}
```

## 14. Database Tables

| Table | PK | Notable Columns | FKs | Unique / Indexes |
|---|---|---|---|---|
| `routes` | `id` | `name`, `start_point`, `end_point`, `status` enum default `Active` | `vehicle_id → vehicles.id` nullable (null on delete) | — |
| `route_stops` | `id` | `stop_name`, `sequence_no` unsigned int, `fare` decimal(8,2) default 0, `pickup_time`/`drop_time` time nullable | `route_id → routes.id` (cascade on delete) | unique `(route_id, sequence_no)` |
| `vehicles` | `id` | `vehicle_no` string unique, `type` default `Bus`, `capacity` unsigned int default 0, `status` enum default `Active` | `driver_id → drivers.id` nullable (null on delete) | `vehicle_no` unique |
| `vehicle_maintenances` | `id` | `type` enum(`Service`,`Repair`,`Inspection`,`Other`) default `Service`, `description`, `cost` decimal(10,2) default 0, `date`, `next_due_date` nullable | `vehicle_id → vehicles.id` (cascade on delete) | — |
| `fuel_logs` | `id` | `date`, `liters` decimal(8,2), `cost` decimal(10,2), `odometer_reading` unsigned int nullable, `remarks` | `vehicle_id → vehicles.id` (cascade on delete) | — |
| `vehicle_documents` | `id` | `document_type` enum(`RC`,`Insurance`,`Permit`,`Fitness`,`PUC`,`Other`) default `Insurance`, `document_no`, `issue_date` nullable, `expiry_date`, `remarks` | `vehicle_id → vehicles.id` (cascade on delete) | — |
| `student_transports` | `id` | `start_date`, `status` enum default `Active` | `student_id → students.id` (cascade, **unique**), `route_id → routes.id` (restrict), `route_stop_id → route_stops.id` (restrict) | `student_id` unique |

## 15. File Storage

None. Vehicle Documents (`vehicle_documents`) tracks document **metadata only** — `document_type`, `document_no`, `issue_date`, `expiry_date`, `remarks` — there is no file upload field on the `VehicleDocuments.vue` form, no `Storage::`/file-upload handling in `VehicleDocumentController`, and no file-path column on the table. A school cannot attach a scanned RC/insurance PDF anywhere in this module; the feature records that a document exists and when it expires, not the document itself.

## 16. Background Jobs

None. `app/Jobs` does not exist in this codebase. In particular: there is **no** scheduled/queued job that scans `vehicle_documents.expiry_date` or `vehicle_maintenances.next_due_date` and raises an alert — the only place "expiring soon" is ever computed is synchronously, on-request, inside `VehicleDocuments.vue`'s client-side `expiryStatus()` helper (display badge only) and `TransportReportCalculator::summary()`'s `upcoming_document_expiries`/`upcoming_maintenance` lists (only visible if someone opens Transport Reports). Nothing runs in the background and nothing pushes a proactive warning.

## 17. Notifications

None. No controller in this module calls `Mail::`, `Notification::`, or `->notify(`. Assigning/removing a student from transport, logging fuel, recording maintenance, or adding an expiring document notifies no one — parents, students, and drivers receive no email/SMS/push about any of it. The only feedback is the client-side `pushToast` confirming the acting user's own action succeeded.

## 18. Module Dependencies

- **Depends on**: People/Student (`students` table — `student_transports.student_id`), People/Driver (`drivers` table — `vehicles.driver_id`; Transport has no student-transport equivalent for drivers, drivers are only ever attached to a `Vehicle`, never directly to a `Route`).
- **Feeds**: Reports module (dual-reachable `Transport Reports` leaf, §12), Import & Export (`route` and `fuel-log` export entities, §11; Student Import also writes `StudentTransport` and, since a later revision, can auto-create `Vehicle` rows — see `student-import-management.md`). People → Students' detail-view Transport tab (`StudentTabTransport.vue`) fetches `GET /transport/student-transport?student_id={id}` to render one student's real route/vehicle/driver/stop/fare (see `people.md`). Documents module has its own, unrelated "Transport Cards" real page (`/documents/transport-cards`) that is not part of this module and does not read from any of these tables per the code inspected here.

## 19. Screenshots

Not available — generated from source code.

## 20. Final Deliverable

Transport Management is 8 real controllers over 7 tables (`routes`, `route_stops`, `vehicles`, `vehicle_maintenances`, `fuel_logs`, `vehicle_documents`, `student_transports`) behind 11 sidebar leaves, 10 of which are real — only **Transport Cards** is a generic placeholder (a same-named but functionally unrelated real page exists under Documents). Two sidebar pairs share one Vue component each: Routes/Driver Assignment (`Routes.vue`) and Route Stops/Route Fare (`RouteStops.vue`). **All 7 entity types now support Edit** — Vehicle Maintenance, Fuel Logs, and Vehicle Documents previously had no `update` route/method (create-and-delete only) but have since gained one, closing what used to be the module's main create/edit-completeness gap. Vehicle Documents tracks expiry metadata but stores no actual files, and nothing in the app (no background job, no notification) proactively warns anyone as a document or maintenance due-date approaches — the only surfacing is a same-request "expiring in 30 days" computation on the Transport Reports page and a client-side badge on the Vehicle Documents list itself. `student_transports.route_stop_id` (and `route_id`) are `NOT NULL`, so a student can never be transport-assigned without a concrete stop — relevant to Student Import, which now auto-creates Vehicles (via Bus No.) but deliberately still never auto-creates Routes themselves, since a route needs real geography and stop data no Excel column can supply (a real-world investigation confirmed 172 "route didn't match" rows in one import were missing master data, not a matching bug — see §1 and `student-import-management.md` §11). `TransportRoute` (table `routes`) is deliberately not named `Route` to avoid colliding with Laravel's own routing classes; a previously broken `Vehicle::routes()` relation (referencing a non-existent `App\Models\Route`) has been fixed to reference `TransportRoute::class`. Export is real for exactly two entities — `route` and `fuel-log` — both open to any authenticated user and delivered as XLSX only; everything else in the module has neither Import nor Export. Transport Reports is dual-reachable from both the Transport Management and Reports sidebar groups via the identical component and the identical (ungated) API endpoint. People → Students' detail-view Transport tab now reads real per-student assignment data from this module (`people.md`). No file storage, background jobs, or notifications exist anywhere in this module.
