# Global School ERP — Developer Documentation

This is the complete functional and technical documentation for the ERP built on top of the existing public marketing website at `c:\xampp\htdocs\global-school`. It is written so a new developer can understand any module's workflow, database structure, business rules, and API surface without reading the source first.

Every fact in these documents is taken directly from the current source code (controllers, models, migrations, routes, Vue pages) — nothing here is guessed or aspirational. Where a documented category (background jobs, file storage, real notification delivery, etc.) genuinely doesn't exist yet for a module, that is stated plainly rather than omitted or padded out.

## Stack

- **Backend**: Laravel 12 (PHP ^8.2), MySQL.
- **Frontend**: Vue 3 SPA (`resources/js/erp/`), mounted at `/erp/dashboard/*`, built with Vite, styled with Tailwind v4.
- **Auth**: Custom `erp` guard (`erp_users` table), session-based — not Sanctum, not the same auth system as the public site's `admins`/`users` tables.
- **This ERP is layered on top of a pre-existing public marketing website** (school's public homepage, admissions info, gallery, etc.) that shares the same Laravel app and database. Several ERP entities are prefixed `Erp` (`ErpNotice`, `ErpFeeStructure`, `ErpRole`, `ErpUser`) specifically because a same-named table/model already existed for the public site — documented per-module where relevant.

## Cross-cutting architecture (applies across every module)

### Routing
- All ERP backend routes live under `routes/erp_api.php`, included from `routes/web.php` inside `Route::prefix('erp/api')`, itself wrapped in the `erp.auth` middleware group (redirects to `/erp/login` if not authenticated as an `erp` guard user).
- All ERP frontend routes are handled client-side by Vue Router (`resources/js/erp/router/index.js`) under the history base `/erp/dashboard`. A `FLAGSHIP_ROUTES` map assigns real Vue components to specific paths; every sidebar leaf not in that map falls back to `GenericModule.vue` (see "Generic pages" below).
- The sidebar itself is a single source of truth: `resources/js/erp/data/menu.js`. Each group becomes a menu section; each child label becomes a path via `slugify()`, and generates a real or generic route entry in the router.

### Permission model
- `app/Http/Middleware/CheckErpPermission.php`, aliased `erp.permission:<key>`. Looks up the authenticated user's role in `erp_roles` (`erp_users.role` is a slug, not a foreign key) and checks the role's `permissions` JSON array for `'*'` (admin-style wildcard) or the specific key.
- **Convention**: reads are open to any authenticated `erp` user; writes are gated by `erp.permission:<module>.manage`. Two deliberate exceptions gate reads too: `reports.view` (Reports module) and `system.manage` (System module), because the underlying data is sensitive.
- The full, current permission key list lives in `resources/js/erp/pages/settings/Roles.vue`'s `availablePermissions` array and is cross-checked against `routes/erp_api.php` per module below.

### Generic (placeholder) pages
- `resources/js/erp/pages/GenericModule.vue` → `components/common/ModulePage.vue` renders any sidebar leaf that has no real backend. It generates fully fake, seeded-random data via `resources/js/erp/utils/mock.js` (`generateRows()`) — nothing persists, nothing is real. Its Add/Delete row actions show a plain success toast (not clearly marked as a simulation, unlike its Import/Export/Print row actions, which do say "demo simulation" — a known, disclosed inconsistency).
- Every module's page below is marked **Real** (own controller/model/table, backed by the database) or **Generic** (this fallback), with a stated reason when generic.

### Audit logging
- A single global Eloquent wildcard listener registered in `app/Providers/AppServiceProvider.php::boot()` (`Event::listen("eloquent.{$action}: *", ...)`) writes to `audit_logs` for every model's `created`/`updated`/`deleted` event across the entire app, guarded by `Auth::guard('erp')->check()` so seeding/console activity isn't captured. This is why almost no individual controller has its own explicit audit-logging code — it's handled once, centrally.
- `created` events use `$model->getAttributes()` (not `getChanges()`, which is empty at that point in Eloquent's lifecycle); `updated` events use `getChanges()`.

### Polymorphic relationships
- `Relation::enforceMorphMap()` in `AppServiceProvider::boot()` maps `'student'/'teacher'/'staff'/'driver'` to their model classes, used by `Attendance`, `LeaveRequest`, `SalaryStructure`, `SalarySlip`, `LibraryMember`.

### "Computed, not stored" calculator services
- `app/Services/*Calculator.php` (e.g. `FeeCalculator`, `ExamResultCalculator`, `CashBookCalculator`, `DashboardCalculator`, and one per Reports-module report) compute figures live from source tables on every request rather than storing/duplicating derived data. Relevant wherever a module's numbers (fee balance, cash book, exam results) are mentioned — they are never a stored column that can drift, except where explicitly noted (e.g. Student Import's `fees_balance` field, a deliberate static snapshot — see `student-import-management.md`).

### Import & Export
- Central infra: `app/Http/Controllers/Erp/ImportExport/{ExportController,StudentImportController,ImportExportLogController,FailedRecordController}.php`, `app/Services/SpreadsheetImportReader.php` (PhpSpreadsheet-backed, reads `.xlsx`/`.xls`/`.csv`), `import_export_logs`/`import_failed_rows` tables.
- Only Student has a real importer today; Export covers 9 entities (`student, fee, expense, salary, bank, fuel-log, route, marks`, plus a combined `global` multi-sheet workbook). Every other module's Import/Export status is noted per-module below.
- Full Student Import field mapping, upsert rules, auto-create rules, and duplicate handling are documented separately in **[student-import-management.md](student-import-management.md)** given how much detail that one feature carries.

## Module Index

| Module | Doc file | Menu group |
|---|---|---|
| Settings | [settings.md](settings.md) | Settings |
| Academics | [academics.md](academics.md) | Academics |
| People | [people.md](people.md) | People |
| Admissions | [admissions.md](admissions.md) | Admissions |
| Fee Management | [fee-management.md](fee-management.md) | Fee Management |
| Attendance | [attendance.md](attendance.md) | Attendance |
| Exam Management | [exam-management.md](exam-management.md) | Exam Management |
| Finance & Payroll | [finance-payroll.md](finance-payroll.md) | Finance & Payroll |
| Transport Management | [transport.md](transport.md) | Transport Management |
| Library | [library.md](library.md) | Library |
| Inventory | [inventory.md](inventory.md) | Inventory |
| Hostel | [hostel.md](hostel.md) | Hostel |
| Documents | [documents.md](documents.md) | Documents |
| Communication | [communication.md](communication.md) | Communication |
| Meetings | [meetings.md](meetings.md) | Meetings |
| Reports | [reports.md](reports.md) | Reports |
| Import & Export | [import-export.md](import-export.md) | Import & Export |
| Dashboard | [dashboard.md](dashboard.md) | Dashboard |
| System | [system.md](system.md) | System |
| Account | [account.md](account.md) | Account |

Each module file follows the same template: Overview → Menu Structure → Pages (URL/Controller/Validation/Permissions) → Form Layout → Field-level tables → Database Relationships → Create/Edit/Delete Flow → Business Rules → Import/Export → Reports → APIs → Database Tables → File Storage → Background Jobs → Notifications → Module Dependencies.
