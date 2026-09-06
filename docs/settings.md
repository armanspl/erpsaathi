# Settings Module

See [README.md](README.md) for cross-cutting architecture (routing, permission model, generic-page mechanism, audit logging, morph maps, calculator-service pattern) — not repeated here.

## 1. Module Overview

**Name**: Settings (sidebar group `Settings`, icon ⚙️).

**Purpose**: Central place for school-identity configuration, the shared Academic Sessions resource, and role/permission management that gates every other module's write routes.

**Main workflow**: An admin (or any user holding `settings.manage`) opens School Settings to edit the single school-identity record, defines Academic Sessions (exactly one of which is ever "current"), and defines Roles with a checklist of permission keys that are then assigned to `erp_users.role` (a slug, not a foreign key — see `CheckErpPermission` middleware). Every other module's write routes are gated by the permission keys defined here.

**Related modules**:
- **Academics** — reuses the exact same Academic Sessions resource (`/academics/academic-sessions` routes to the same `AcademicSessions.vue` component and the same `settings/academic-sessions` backend endpoints). Documented once here, cross-referenced from `academics.md`.
- **Every other module** — its write routes are gated by a permission key (`<module>.manage`) whose master list is maintained in `Roles.vue`'s `availablePermissions` array and enforced by `App\Http\Middleware\CheckErpPermission`.
- **Account** — `erp_users.role` (set outside this module, via People > Users) is the slug looked up against `erp_roles.slug` at request time.

## 2. Menu Structure

Sidebar group `Settings`, children (from `resources/js/erp/data/menu.js`):

| Label | Path | Type |
|---|---|---|
| School Settings | `/settings/school-settings` | **Real** |
| Academic Sessions | `/settings/academic-sessions` | **Real** (shared with Academics module) |
| Branch Settings | `/settings/branch-settings` | Generic |
| Roles & Permissions | `/settings/roles-and-permissions` | **Real** |
| User Roles | `/settings/user-roles` | Generic |
| Email Settings | `/settings/email-settings` | Generic |
| SMS Settings | `/settings/sms-settings` | Generic |
| WhatsApp Settings | `/settings/whatsapp-settings` | Generic |
| Payment Gateway | `/settings/payment-gateway` | Generic |
| Biometric Devices | `/settings/biometric-devices` | Generic |
| Receipt Templates | `/settings/receipt-templates` | Generic |
| Certificate Templates | `/settings/certificate-templates` | Generic |
| Theme Settings | `/settings/theme-settings` | Generic |
| Number Series | `/settings/number-series` | Generic |
| Backup | `/settings/backup` | Generic |
| Restore | `/settings/restore` | Generic |
| Database Backup | `/settings/database-backup` | Generic |
| Import Settings | `/settings/import-settings` | Generic |
| Export Settings | `/settings/export-settings` | Generic |
| API Settings | `/settings/api-settings` | Generic |

### Generic pages — reason
None of `Branch Settings, User Roles, Email Settings, SMS Settings, WhatsApp Settings, Payment Gateway, Biometric Devices, Receipt Templates, Certificate Templates, Theme Settings, Number Series, Backup, Restore, Database Backup, Import Settings, Export Settings, API Settings` appear as a key in `FLAGSHIP_ROUTES` (`resources/js/erp/router/index.js`), so each falls through to `GenericModule.vue` — fake seeded data via `mock.js`, nothing persists. (`Branch Settings` and `User Roles` are notable near-duplicates of the real `Branches` page in Academics and the real `Roles & Permissions` page respectively, but neither has its own controller/route.)

## 3. Pages (Real)

### 3.1 School Settings

- **Frontend URL**: `/settings/school-settings` → `resources/js/erp/pages/settings/SchoolSettings.vue`
- **Backend URL**: `GET erp/api/settings/school`, `PUT erp/api/settings/school`
- **Controller**: `App\Http\Controllers\Erp\SchoolSettingController`
  - `show()` — returns `SchoolSetting::current()`
  - `update(Request $request)` — validates and updates the singleton row
- **Validation** (`update`, verbatim from `SchoolSettingController::update`):
```php
$request->validate([
    'school_name' => 'required|string|max:255',
    'address' => 'nullable|string|max:255',
    'phone' => 'nullable|string|max:30',
    'email' => 'nullable|email|max:255',
    'website' => 'nullable|string|max:255',
    'established_year' => 'nullable|integer|min:1800|max:' . (date('Y') + 1),
    'current_branch' => 'nullable|string|max:255',
]);
```
- **Permissions**: `show` is open to any authenticated ERP user; `update` requires `erp.permission:settings.manage` (`routes/erp_api.php`, confirmed current — write routes were previously ungated and are now wrapped in the `Route::middleware('erp.permission:settings.manage')` group).

### 3.2 Academic Sessions

- **Frontend URL**: `/settings/academic-sessions` **and** `/academics/academic-sessions` — both routes resolve to the same `resources/js/erp/pages/settings/AcademicSessions.vue` component (see `FLAGSHIP_ROUTES` — both keys map to the imported `AcademicSessions` component).
- **Backend URL**: `GET erp/api/settings/academic-sessions`, `POST erp/api/settings/academic-sessions`, `PUT erp/api/settings/academic-sessions/{academicSession}`, `DELETE erp/api/settings/academic-sessions/{academicSession}` — there is no separate `academics/academic-sessions` backend route; the Academics sidebar entry calls this exact same `settings/academic-sessions` endpoint (`resources/js/erp/store.js`'s `loadSessions()` helper, used by the shared Vue page, always hits `/settings/academic-sessions`).
- **Controller**: `App\Http\Controllers\Erp\AcademicSessionController`
  - `index()` — `AcademicSession::orderByDesc('start_date')->get()`
  - `store(Request $request)` — creates a session; if `status === 'current'`, calls private `makeCurrent()`
  - `update(Request $request, AcademicSession $academicSession)` — updates; if new status is `current`, calls `makeCurrent()`; else if the session was previously `is_current`, explicitly demotes it
  - `destroy(AcademicSession $academicSession)` — hard delete
- **Validation** (`store` and `update`, identical, verbatim):
```php
$request->validate([
    'name' => 'required|string|max:50',
    'start_date' => 'required|date',
    'end_date' => 'required|date|after:start_date',
    'status' => 'required|in:current,upcoming,closed',
]);
```
- **Permissions**: `index` open to any authenticated ERP user; `store`/`update`/`destroy` require `erp.permission:settings.manage`.
- **Full detail lives here** (Settings); the `academics.md` file cross-references this section rather than repeating it.

### 3.3 Roles & Permissions

- **Frontend URL**: `/settings/roles-and-permissions` → `resources/js/erp/pages/settings/Roles.vue`
- **Backend URL**: `GET erp/api/settings/roles`, `POST erp/api/settings/roles`, `PUT erp/api/settings/roles/{erpRole}`, `DELETE erp/api/settings/roles/{erpRole}`
- **Controller**: `App\Http\Controllers\Erp\ErpRoleController`
  - `index()` — `ErpRole::orderBy('name')->get()`
  - `store(Request $request)` — validates, derives `slug` from `name` via `Str::slug()`, creates
  - `update(Request $request, ErpRole $erpRole)` — same validation, re-derives `slug`, updates
  - `destroy(ErpRole $erpRole)` — blocked with HTTP 422 (`'System roles cannot be deleted.'`) if `is_system` is true, otherwise hard-deletes
- **Validation** (`store` and `update`, identical, verbatim):
```php
$request->validate([
    'name' => 'required|string|max:100',
    'description' => 'nullable|string|max:255',
    'permissions' => 'array',
    'permissions.*' => 'string',
]);
```
- **Permissions**: `index` open to any authenticated ERP user; `store`/`update`/`destroy` require `erp.permission:settings.manage`.
- The **master list of permission keys** (checkbox options in the Add/Edit Role drawer, `availablePermissions` in `Roles.vue`): `academics.manage`, `people.manage`, `admissions.manage`, `attendance.manage`, `fee.manage`, `finance.manage`, `transport.manage`, `exam.manage`, `library.manage`, `inventory.manage`, `hostel.manage`, `documents.manage`, `communication.manage`, `meetings.manage`, `import.manage`, `reports.view`, `settings.manage`, `system.manage`. A role's `permissions` array may instead contain the single wildcard `'*'` (admin-style, grants everything) — this is not offered as a checkbox in the UI, so a `'*'` role can currently only be created by direct DB/seed action, not through this page.
- `App\Http\Middleware\CheckErpPermission::handle()` short-circuits entirely for `erp_users.role === 'admin'` (string literal check, before any `erp_roles` lookup) — the "admin" role slug is effectively always all-powerful regardless of what `erp_roles` contains for it.

## 4. Form Layout

- **School Settings** — single non-drawer form (`<form>` inline on the page, not a `SlideOver`): one 2-column grid section — School Name and Address each span both columns; Phone / Email / Website / Established Year are 2-up; Current Branch spans both columns again. One "Save Changes" button, no cancel (there's nothing to cancel back to — it edits the live singleton in place).
- **Academic Sessions** — `SlideOver` drawer, single flat section, no field grouping: Session Name → Start Date → End Date → Status (select). Footer: Cancel / Save.
- **Roles & Permissions** — `SlideOver` drawer, three sections: Role Name (text) → Description (text) → Permissions (a `grid-cols-2` checkbox list rendered from `availablePermissions`, bound to `form.permissions` array). Footer: Cancel / Save. The role list itself renders as a **card grid** (not a table) — each card shows name, description, system badge, permission count, Edit/Delete buttons (Delete disabled and re-titled for system roles).

## 5. Fields

### 5.1 School Settings (table `school_settings`)

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| School Name | `school_name` | school_settings | `string` (VARCHAR) | Required | `required\|string\|max:255` | none | — | — |
| Address | `address` | school_settings | `string` nullable | Optional | `nullable\|string\|max:255` | none | — | — |
| Phone | `phone` | school_settings | `string` nullable | Optional | `nullable\|string\|max:30` | none | — | — |
| Email | `email` | school_settings | `string` nullable | Optional | `nullable\|email\|max:255` | none | — | — |
| Website | `website` | school_settings | `string` nullable | Optional | `nullable\|string\|max:255` | none | — | — |
| Established Year | `established_year` | school_settings | `unsignedSmallInteger` nullable | Optional | `nullable\|integer\|min:1800\|max:<current_year+1>` | none | — | — |
| Current Branch | `current_branch` | school_settings | `string` | Optional | `nullable\|string\|max:255` | `'Main Campus'` (migration default) | — | none — plain free text, **not** a foreign key into the `branches` table created by the Academics module (two independent "branch" concepts exist in this app) |
| *(not on form)* | `logo_path` | school_settings | `string` nullable | — | not validated/exposed by this controller | none | — | — |

`id` is the primary key; this table is a deliberate **singleton** — `SchoolSetting::current()` is `firstOrCreate(['id' => 1], [...])`, so there is always exactly one row and the UI never shows a list.

### 5.2 Academic Sessions (table `academic_sessions`)

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Session Name | `name` | academic_sessions | `string` | Required | `required\|string\|max:50` | none | — | — |
| Start Date | `start_date` | academic_sessions | `date` | Required | `required\|date` | none | — | — |
| End Date | `end_date` | academic_sessions | `date` | Required | `required\|date\|after:start_date` | none | — | — |
| Status | `status` | academic_sessions | `enum('current','upcoming','closed')` | Required | `required\|in:current,upcoming,closed` | `'upcoming'` (migration default) | fixed 3-option `<select>` | — |
| *(not on form; server-managed)* | `is_current` | academic_sessions | `boolean` | — | not in request validation — set only by controller's `makeCurrent()` logic | `false` | — | — |

### 5.3 Roles & Permissions (table `erp_roles`)

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Role Name | `name` | erp_roles | `string` | Required | `required\|string\|max:100` | none | — | — |
| Description | `description` | erp_roles | `string` nullable | Optional | `nullable\|string\|max:255` | none | — | — |
| Permissions | `permissions` | erp_roles | `json` nullable, cast `array` | Optional (empty array allowed) | `array`, each item `string` | none | checkbox list of `availablePermissions` (hard-coded in `Roles.vue`, not fetched from an API) | — |
| *(not on form; server-derived)* | `slug` | erp_roles | `string`, **unique** | — | not user-supplied — `Str::slug($data['name'])` computed server-side on every store/update | none | — | — |
| *(not on form)* | `is_system` | erp_roles | `boolean` | — | not settable via this controller (presumably seeded) | `false` | — | — |

## 6. Database Relationships

- `SchoolSetting` — no Eloquent relationships defined.
- `AcademicSession` — no Eloquent relationships defined (it is not linked by FK to any other table in this app; sessions are matched to other data only by convention/date, not a `academic_session_id` column anywhere in the schema).
- `ErpRole` — no Eloquent relationships defined. `erp_users.role` is a plain string slug compared at request time in `CheckErpPermission`, **not** a Laravel foreign key/relationship.

## 7. Create Flow

- **School Settings**: never "created" through the UI — the row is auto-created by `SchoolSetting::current()` the first time it's read (`firstOrCreate(['id' => 1], ['school_name' => 'Global Access School', 'current_branch' => 'Main Campus'])`). No side effects.
- **Academic Sessions**: `POST /settings/academic-sessions` inserts one `academic_sessions` row. If `status === 'current'` in the payload, `makeCurrent()` runs inside the same request (not a DB transaction) and updates every other row: sets `status = 'closed'` for any other row still `current`, and sets `is_current = false` for every other row, before setting the new row's `is_current = true`.
- **Roles & Permissions**: `POST /settings/roles` computes `slug` from `name`, then inserts one `erp_roles` row. No side effects on other tables.

## 8. Edit Flow

- **School Settings**: `PUT /settings/school` updates the singleton row in place with any subset of the validated fields (all fields optional except `school_name`).
- **Academic Sessions**: `PUT /settings/academic-sessions/{id}` re-validates the full field set (no partial update) and re-runs the same current-session logic as create: if the new status is `current`, demotes every other session; if the edited session's status changes away from `current` while it was previously `is_current`, it is explicitly demoted (`['is_current' => false]`).
- **Roles & Permissions**: `PUT /settings/roles/{id}` re-validates the full field set, re-derives `slug` from the (possibly changed) `name`, and updates. There is no restriction on editing `is_system` roles' name/permissions — only **delete** is blocked for system roles.

## 9. Delete Flow

- **Hard delete** in all three cases — no `SoftDeletes` trait anywhere in this app (confirmed: no model in `app/Models/` uses `SoftDeletes`).
- **School Settings**: no delete endpoint exists (singleton).
- **Academic Sessions**: `DELETE /settings/academic-sessions/{id}` hard-deletes the row. No FK from any other table references `academic_sessions.id`, so there is no cascade behavior to consider — but deleting the currently-active session leaves the app with **no** `is_current` session until an admin marks another one current (no guard against this in the controller).
- **Roles & Permissions**: `DELETE /settings/roles/{id}` is blocked (HTTP 422) when `erpRole->is_system` is true; otherwise hard-deletes. `erp_users.role` is a plain string, not a DB foreign key to `erp_roles.id`, so deleting a role does **not** cascade or null out any `erp_users` row — it would simply leave users with a role slug that no longer resolves to any `ErpRole` record, and `CheckErpPermission` would treat such a user as having no permissions (empty `$permissions` array).

## 10. Business Rules

- **Single active session**: `AcademicSessionController::makeCurrent()` enforces that at most one `academic_sessions` row has `status = 'current'` / `is_current = true` at any time — verified directly in the controller (see Create/Edit Flow above).
- **System roles cannot be deleted**: enforced server-side (`ErpRoleController::destroy`), independent of the UI's disabled Delete button.
- **Slug derivation**: `erp_roles.slug` is always `Str::slug(name)`, recomputed on every save — there is no user control over the slug, and the migration enforces `slug` as `unique()`, so two roles with names that slugify identically (e.g. "Teacher" and "teacher") will fail to save with a DB unique-constraint error the controller does not catch/translate into a friendly message.
- **Permission wildcard**: `'*'` in a role's `permissions` array grants every `erp.permission:*` gate in the app (checked in `CheckErpPermission`), but the Roles UI never lets an operator add `'*'` via checkbox — it can only exist on roles created outside this page (e.g. a database seeder).
- **`current_branch` vs `branches` table**: `school_settings.current_branch` is a free-text string with no FK relationship to the `branches` table (Academics module) — they are two independently maintained concepts, not synchronized.
- **Permission gate on Settings writes**: `settings.manage` gates `PUT /settings/school`, all three Academic Session write verbs, and all three Role write verbs. This is now correctly enforced in `routes/erp_api.php` (previously these write routes were not wrapped in the permission middleware group; that has been fixed).

## 11. Import & Export

No import or export exists for any page in this module — none of School Settings, Academic Sessions, or Roles & Permissions appear in the README's list of the 9 exportable entities (`student, fee, expense, salary, bank, fuel-log, route, marks, global`), and only Student has a real importer. The sidebar's own `Import Settings` / `Export Settings` leaves are Generic placeholders with no backend.

## 12. Reports

None of this module's three real pages feed a dedicated report view. `AcademicSession::where('is_current', true)->first()` is read by `App\Services\DashboardCalculator` to compute the current-session label shown on the main Dashboard, but that is a dashboard widget, not a Reports-module report.

## 13. APIs

| Method | Path | Controller@method | Permission |
|---|---|---|---|
| GET | `erp/api/settings/school` | `SchoolSettingController@show` | open |
| PUT | `erp/api/settings/school` | `SchoolSettingController@update` | `settings.manage` |
| GET | `erp/api/settings/academic-sessions` | `AcademicSessionController@index` | open |
| POST | `erp/api/settings/academic-sessions` | `AcademicSessionController@store` | `settings.manage` |
| PUT | `erp/api/settings/academic-sessions/{academicSession}` | `AcademicSessionController@update` | `settings.manage` |
| DELETE | `erp/api/settings/academic-sessions/{academicSession}` | `AcademicSessionController@destroy` | `settings.manage` |
| GET | `erp/api/settings/roles` | `ErpRoleController@index` | open |
| POST | `erp/api/settings/roles` | `ErpRoleController@store` | `settings.manage` |
| PUT | `erp/api/settings/roles/{erpRole}` | `ErpRoleController@update` | `settings.manage` |
| DELETE | `erp/api/settings/roles/{erpRole}` | `ErpRoleController@destroy` | `settings.manage` |

### Representative example — Academic Sessions

`POST erp/api/settings/academic-sessions`
```json
{
  "name": "2027-2028",
  "start_date": "2027-04-01",
  "end_date": "2028-03-31",
  "status": "current"
}
```
Response `201`:
```json
{
  "id": 4,
  "name": "2027-2028",
  "start_date": "2027-04-01",
  "end_date": "2028-03-31",
  "status": "current",
  "is_current": true,
  "created_at": "2026-07-31T12:00:00.000000Z",
  "updated_at": "2026-07-31T12:00:00.000000Z"
}
```

### Representative example — Roles & Permissions

`POST erp/api/settings/roles`
```json
{
  "name": "Front Desk",
  "description": "Admissions and enquiry handling only",
  "permissions": ["admissions.manage"]
}
```
Response `201` (shape from `ErpRoleController::store`'s `response()->json($role, 201)`):
```json
{
  "id": 7,
  "name": "Front Desk",
  "slug": "front-desk",
  "description": "Admissions and enquiry handling only",
  "permissions": ["admissions.manage"],
  "is_system": false,
  "created_at": "2026-07-31T12:00:00.000000Z",
  "updated_at": "2026-07-31T12:00:00.000000Z"
}
```

## 14. Database Tables

**`school_settings`**
- PK: `id`
- No foreign keys, no unique constraints beyond PK, no explicit indexes.
- Columns: `school_name`, `address`, `phone`, `email`, `website`, `logo_path`, `established_year`, `current_branch` (default `'Main Campus'`), timestamps.

**`academic_sessions`**
- PK: `id`
- No foreign keys, no unique constraints, no explicit indexes.
- Columns: `name`, `start_date`, `end_date`, `status` (enum, default `'upcoming'`), `is_current` (boolean, default `false`), timestamps.

**`erp_roles`**
- PK: `id`
- Unique: `slug`
- No foreign keys, no explicit secondary indexes.
- Columns: `name`, `slug` (unique), `description` nullable, `permissions` (json, nullable), `is_system` (boolean, default `false`), timestamps.

## 15. File Storage

None. `school_settings.logo_path` exists as a column but no controller action in this module reads, writes, or exposes it — there is no upload endpoint for a school logo anywhere in `SchoolSettingController`.

## 16. Background Jobs

None. No `Job` class in the codebase references `SchoolSetting`, `AcademicSession`, or `ErpRole`, and there is no `php artisan schedule` entry touching this module (confirmed: no scheduled command in the app references Settings-module models).

## 17. Notifications

None. No notification/mailable/broadcast is dispatched from any controller in this module.

## 18. Module Dependencies

- **Depends on**: nothing — these are foundational/reference tables with no inbound dependency on other modules' data.
- **Depended on by**: every module's write routes depend on the `permissions` defined in `erp_roles` via `CheckErpPermission`. `AcademicSession.is_current` is read by `DashboardCalculator` for the Dashboard module. `academic_sessions` itself is also directly exposed under the Academics sidebar group (see `academics.md`) even though it is a Settings-owned resource.

## 19. Screenshots

Not available — this documentation was generated from source code, not a running instance.

## 20. Final Deliverable Summary

Settings is a small, foundational module: a singleton school-identity record (`school_settings`), the shared Academic Sessions list (`academic_sessions`, also surfaced under Academics), and the Roles & Permissions registry (`erp_roles`) that every other module's write routes check via `erp.permission:<key>`. All three real pages use simple flat forms (one plain `<form>` for School Settings, `SlideOver` drawers for the other two), hard-delete their rows (no soft deletes anywhere in the app), and have no import/export, no file storage, no background jobs, and no notifications. The only non-trivial business logic is `AcademicSessionController::makeCurrent()`'s single-active-session enforcement and the system-role delete guard in `ErpRoleController::destroy`. The seventeen remaining sidebar entries in this group are unimplemented `GenericModule.vue` placeholders backed by fake seeded data.
