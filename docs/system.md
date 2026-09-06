# System

See [README.md](README.md) for cross-cutting architecture (routing, permission model, generic-page mechanism, and — most relevant here — the global audit-log listener) — not repeated here.

## 1. Module Overview

System is the ERP's operations/observability module: audit history, login history, and live server/queue/cache/health introspection. It is one of only two modules in the entire app (the other is Reports) where **reads are permission-gated, not just writes** — every route in `routes/erp_api.php`'s `system` prefix sits behind `erp.permission:system.manage` at the group level. Of its 12 sidebar leaves, 7 are real (backed by 6 controllers — Activity Center and Audit Logs share one controller/endpoint), and 5 are unimplemented generic placeholders.

The four infrastructure pages (Queue Monitor, Scheduled Jobs, Cache Manager, System Health) were specifically checked line-by-line for this document, and **all four are genuinely real** — none synthesize or mock any figure. Precisely what each does and does not reflect is documented in §10.

## 2. Menu Structure

Menu group `System` (`resources/js/erp/data/menu.js`):

| Sidebar Label | Path | Component | Real? |
|---|---|---|---|
| AI Assistance | `/system/ai-assistance` | `GenericModule.vue` | Generic — no backend/controller exists |
| Activity Center | `/system/activity-center` | `ActivityCenter.vue` | **Real** — calls the same `/system/audit-logs` endpoint as Audit Logs, presented as a chronological feed with stat tiles instead of a table |
| Pending Deletions | `/system/pending-deletions` | `GenericModule.vue` | Generic — no backend/controller exists (there is no soft-delete/pending-approval workflow anywhere in the app to back this) |
| Audit Logs | `/system/audit-logs` | `AuditLogs.vue` | **Real** |
| Login History | `/system/login-history` | `LoginHistory.vue` | **Real** |
| Queue Monitor | `/system/queue-monitor` | `QueueMonitor.vue` | **Real** |
| Scheduled Jobs | `/system/scheduled-jobs` | `ScheduledJobs.vue` | **Real** |
| Cache Manager | `/system/cache-manager` | `CacheManager.vue` | **Real** |
| Email Queue | `/system/email-queue` | `GenericModule.vue` | Generic — no backend/controller exists |
| SMS Queue | `/system/sms-queue` | `GenericModule.vue` | Generic — no backend/controller exists |
| Notification Queue | `/system/notification-queue` | `GenericModule.vue` | Generic — no backend/controller exists |
| System Health | `/system/system-health` | `SystemHealth.vue` | **Real** |

## 3. Pages

All 6 controllers live in `app/Http/Controllers/Erp/System/`. None of them accept any request body; the only "validation" present anywhere in this module is optional query-string filtering read via `$request->filled(...)` with no format/type validation applied.

### 3.1 Activity Center / Audit Logs

- **URL (frontend)**: `/system/activity-center` and `/system/audit-logs` (two different pages, one endpoint).
- **URL (backend)**: `GET /erp/api/system/audit-logs`.
- **Controller/method**: `AuditLogController@index`.
- **Filters** (verbatim): `$request->filled('action')` → `where('action', ...)`; `$request->filled('auditable_type')` → `where('auditable_type', ...)`. Neither field name nor its value is validated against a whitelist — an arbitrary string is passed straight into `where()`.
- **Pagination**: none — `orderByDesc('id')->limit(200)->get()`, a hard cap, not a paged cursor. Both `AuditLogs.vue` and `ActivityCenter.vue` apply their own client-side free-text search over the already-fetched 200-row result set (neither page ever sends the server-side `?action=`/`?auditable_type=` filters above as query params — those exist in the controller but are only reachable via direct API call today). The client-side search previously matched only `auditable_type` (e.g. typing "Student" would find it, but typing an action like "deleted," a record ID, or the performer's name would not) — both pages now match against `action` + `auditable_type` + `auditable_id` + performer name together.
- **Permissions**: `system.manage` (group-level).
- **Data source**: this endpoint is the query layer over rows written entirely by the global `Event::listen("eloquent.{$action}: *", ...)` wildcard listener in `AppServiceProvider::boot()` (see README) — `AuditLogController` never writes to `audit_logs` itself.

### 3.2 Login History

- **URL (frontend)**: `/system/login-history`.
- **URL (backend)**: `GET /erp/api/system/login-history`.
- **Controller/method**: `LoginHistoryController@index`.
- **Filters**: `$request->filled('status')` → `where('status', ...)` (expects `Success`/`Failed`, unvalidated).
- **Pagination**: `orderByDesc('id')->limit(200)->get()`.
- **Permissions**: `system.manage`.
- **Data source**: rows are written by `ErpAuthController::login()` (outside `erp_api.php`, part of the pre-authentication login flow) on every login attempt, success or failure — this is the same table `Account > Login Sessions` reads from, scoped to the current user (see `account.md` §3/§18).

### 3.3 Queue Monitor

- **URL (frontend)**: `/system/queue-monitor`.
- **URL (backend)**: `GET /erp/api/system/queue-monitor`.
- **Controller/method**: `QueueMonitorController@index`.
- **Permissions**: `system.manage`.
- **What it genuinely does** (verbatim logic): returns `config('queue.default')` (currently `database`, per `config/queue.php`), `DB::table('jobs')->count()`, `DB::table('failed_jobs')->count()`, and the 20 most recent `failed_jobs` rows (`id`, `queue`, `exception` truncated to 300 chars, `failed_at`). **This is a genuine, real-time query against Laravel's own `jobs`/`failed_jobs` tables and the live queue config — nothing here is mocked or hardcoded.**

### 3.4 Scheduled Jobs

- **URL (frontend)**: `/system/scheduled-jobs`.
- **URL (backend)**: `GET /erp/api/system/scheduled-jobs`.
- **Controller/method**: `ScheduledJobController@index`.
- **Permissions**: `system.manage`.
- **What it genuinely does**: injects Laravel's own `Illuminate\Console\Scheduling\Schedule` instance and calls `$schedule->events()` — the real list of cron entries registered for this application, with each event's command string, cron expression, and computed next-run time (`nextRunDate()`). **This is genuinely real, not synthesized** — but as of this codebase, `routes/console.php` registers no `Schedule::` entries at all (only an `Artisan::command('inspire', ...)` console command, which is not scheduled), so this page will accurately show `count: 0` / an empty list. The page is real, working infrastructure for a feature (scheduled tasks) that the app does not currently use.

### 3.5 Cache Manager

- **URL (frontend)**: `/system/cache-manager`.
- **URL (backend)**: `GET /erp/api/system/cache-manager` (read), `POST /erp/api/system/cache-manager/clear` (action).
- **Controller/method**: `CacheManagerController@index`, `CacheManagerController@clear`.
- **Permissions**: `system.manage` for both.
- **What it genuinely does**: `index()` returns the live `config('cache.default')` (currently `database`, per `config/cache.php`) and that store's full config block. `clear()` genuinely calls `Artisan::call()` four times in sequence — `cache:clear`, `config:clear`, `route:clear`, `view:clear` — which really flushes the application's cache store, the cached config file, the cached route file, and compiled Blade views on disk. **This is a real, destructive action with a real effect on the running application**, not a simulated toast.

### 3.6 System Health

- **URL (frontend)**: `/system/system-health`.
- **URL (backend)**: `GET /erp/api/system/health`.
- **Controller/method**: `SystemHealthController@index`.
- **Permissions**: `system.manage`.
- **What it genuinely does**: `PHP_VERSION`, `app()->version()` (real Laravel version), `app()->environment()`, `config('app.debug')`, a genuine live DB connectivity check (`DB::connection()->getPdo()` inside a try/catch, reporting `Connected`/`Down`), `config('database.default')`, `config('cache.default')`, `config('queue.default')`, `is_writable(storage_path())`, `disk_free_space(storage_path())` converted to GB, and `now()->toDateTimeString()`. **Every field is a genuine, real-time system read — there is no hardcoded or mocked value on this page.**

## 4. Form Layout

Every real page in this module is a read-only table, stat-card grid, or key/value panel fed by a single GET on mount — none has a create/edit form. `ActivityCenter.vue` and `AuditLogs.vue` each offer a client-side search box (`FilterBar`) that filters the already-fetched 200-row result set in the browser; `LoginHistory.vue` similarly. The only actionable control anywhere in the module is Cache Manager's single "Clear Cache" button, which fires the `POST /system/cache-manager/clear` action directly with no confirmation dialog and no parameters.

## 5. Fields

No editable fields exist anywhere in this module. The columns/keys actually displayed per real page:

| Page | Displayed fields | Source |
|---|---|---|
| Activity Center / Audit Logs | Action (created/updated/deleted), Auditable Type, Auditable ID, Performed By, Changes (JSON), Created At | `audit_logs` table |
| Login History | Status (Success/Failed), Email, IP Address, User Agent, Created At, associated ErpUser (if matched) | `login_histories` table |
| Queue Monitor | Connection, Pending Jobs, Failed Jobs, Recent Failed (id/queue/exception/failed_at) | live `jobs`/`failed_jobs` tables + `config('queue.default')` |
| Scheduled Jobs | Command, Cron Expression, Next Due, Description | live `Schedule::events()` |
| Cache Manager | Driver, Store Config (raw config array) | `config('cache.default')` + `config('cache.stores.*')` |
| System Health | PHP Version, Laravel Version, Environment, Debug Mode, DB Connection/Status, Cache Driver, Queue Driver, Storage Writable, Disk Free (GB), Server Time | live PHP/Laravel/filesystem/DB introspection |

## 6. Database Relationships

- `AuditLog::performedBy()` — `belongsTo(ErpUser::class, 'performed_by_id')`.
- `LoginHistory::erpUser()` — `belongsTo(ErpUser::class)`.

Neither `AuditLog` nor `LoginHistory` defines an inverse relation on `ErpUser` itself (`ErpUser.php` declares no relationship methods at all). Queue Monitor's `jobs`/`failed_jobs` reads are raw `DB::table()` calls against Laravel's own framework-owned tables — there is no Eloquent model for either, and no relationship to anything else in the app.

## 7. Create Flow

None initiated by this module directly:
- `audit_logs` rows are created entirely by the global model-event listener described in the README — no controller in System writes to this table.
- `login_histories` rows are created by `ErpAuthController::login()` on every login attempt (success or failure), which lives outside `routes/erp_api.php` entirely (it's the pre-authentication login endpoint).
- `jobs`/`failed_jobs` rows would be created by Laravel's queue system if/when any job is ever dispatched — nothing in this codebase currently dispatches queued jobs (see §16).

## 8. Edit Flow

None. No route in this module updates any row.

## 9. Delete Flow

None. There is no delete endpoint anywhere in System — `audit_logs` and `login_histories` grow unbounded with no purge/retention job, and the two read-only 200-row caps (`limit(200)`) on Audit Logs and Login History are a display limit, not evidence of pruning. `jobs`/`failed_jobs` rows are only ever removed by Laravel's own queue worker commands (`queue:work`, `queue:retry`, `queue:flush`) run from the CLI — Cache Manager's "Clear Cache" button does not touch either table.

## 10. Business Rules

- **`system.manage` gates every route in this module, including reads** — one of only two modules in the app (with Reports) where this is true; every other module leaves reads open to any authenticated user.
- **The `admin` role slug always bypasses the permission check entirely**, before the role's `permissions` array is even consulted (`CheckErpPermission::handle()`: `if ($user->role === 'admin') { return $next($request); }`) — this applies to every gated module, not just System, but is worth restating here since System is the most sensitive one.
- **Queue Monitor, Scheduled Jobs, Cache Manager, and System Health are all genuinely real, with zero synthesized data** — verified by reading each controller in full (see §3.3–3.6). This distinguishes them sharply from `GenericModule.vue`'s fake seeded-random data.
- **Zero jobs are currently scheduled and zero queued jobs currently exist**, so Queue Monitor and Scheduled Jobs will both show empty/zero states in this application today — that is the honest, correct output of real code, not a bug or a placeholder.
- **Cache Manager's "Clear" button has a genuine destructive side effect** on the running application (flushes cache, config cache, route cache, compiled views) — it is not a demo/simulation toast like `GenericModule.vue`'s row actions.
- **Activity Center and Audit Logs are two different UIs over the identical dataset and endpoint** — neither is a superset of the other; they differ only in presentation (feed vs. table) and default framing (stat tiles for Activity Center).

## 11. Import & Export

No. This module has no import or export capability of any kind — no CSV/Excel download, no bulk upload. (See `import-export.md` for the module that owns this capability.)

## 12. Reports

System has no `*ReportCalculator` service of its own (unlike most other modules — see README's "Computed, not stored" section). Its closest equivalents to a report are Audit Logs, Login History, and Queue Monitor's live counts, all of which are direct, unaggregated reads rather than a computed summary — there is no System-specific "Reports" sub-page, and the `Dashboard Reports`/`System` overlap some other modules have does not exist here.

## 13. APIs

| Method | Path | Controller@method | Auth |
|---|---|---|---|
| GET | `/erp/api/system/audit-logs` | `AuditLogController@index` | `system.manage` |
| GET | `/erp/api/system/login-history` | `LoginHistoryController@index` | `system.manage` |
| GET | `/erp/api/system/queue-monitor` | `QueueMonitorController@index` | `system.manage` |
| GET | `/erp/api/system/scheduled-jobs` | `ScheduledJobController@index` | `system.manage` |
| GET | `/erp/api/system/cache-manager` | `CacheManagerController@index` | `system.manage` |
| POST | `/erp/api/system/cache-manager/clear` | `CacheManagerController@clear` | `system.manage` |
| GET | `/erp/api/system/health` | `SystemHealthController@index` | `system.manage` |

Example — Audit Logs:
```
GET /erp/api/system/audit-logs?action=updated
→ 200 OK
[ { "id": 981, "action": "updated", "auditable_type": "student", "auditable_id": 214,
    "changes": { "status": "Inactive" }, "performed_by": { "id": 2, "name": "Admin User" },
    "created_at": "2026-07-30T08:41:09.000000Z" } ]
```

Example — Queue Monitor:
```
GET /erp/api/system/queue-monitor
→ 200 OK
{ "connection": "database", "pending_jobs": 0, "failed_jobs": 0, "recent_failed": [] }
```

Example — Cache Manager clear:
```
POST /erp/api/system/cache-manager/clear
→ 200 OK
{ "success": true, "message": "Application, config, route and view caches cleared." }
```

Example — System Health:
```
GET /erp/api/system/health
→ 200 OK
{ "php_version": "8.2.18", "laravel_version": "12.1.0", "environment": "local", "debug_mode": true,
  "database": { "connection": "mysql", "status": "Connected" },
  "cache_driver": "database", "queue_driver": "database",
  "storage_writable": true, "disk_free_space_gb": 42.7, "server_time": "2026-07-30 14:32:10" }
```

## 14. Database Tables

**`audit_logs`**
| Column | Type | Notes |
|---|---|---|
| id | bigint, PK | |
| action | enum('created','updated','deleted') | required |
| auditable_type | string | morph-mapped short name (e.g. `student`) or FQCN, depending on caller |
| auditable_id | unsigned bigint, nullable | |
| changes | json, nullable | cast to `array` |
| performed_by_id | FK → `erp_users.id`, nullable, `nullOnDelete()` | |
| created_at / updated_at | timestamps | |

**`login_histories`**
| Column | Type | Notes |
|---|---|---|
| id | bigint, PK | |
| erp_user_id | FK → `erp_users.id`, nullable, `nullOnDelete()` | null on failed logins with an unknown email |
| email | string | required (the attempted email, even on failure) |
| status | enum('Success','Failed') | required |
| ip_address | string, nullable | |
| user_agent | string, nullable | |
| created_at / updated_at | timestamps | |

**`jobs`, `job_batches`, `failed_jobs`** — Laravel's own default queue tables (`0001_01_01_000002_create_jobs_table.php`), not owned or modified by this module; System only reads them.

No unique constraints or secondary indexes beyond the PKs/FKs listed.

## 15. File Storage

None.

## 16. Background Jobs

**None currently exist or are scheduled anywhere in this application** — `routes/console.php` registers no `Schedule::` entries, and no code anywhere dispatches a queued `Job` class. However, this is precisely what makes System's Queue Monitor and Scheduled Jobs pages notable: they are **real, working infrastructure genuinely wired to Laravel's actual queue/scheduler subsystems**, ready to display real pending/failed jobs and real cron entries the moment any part of the app starts using them — they are not placeholder UI waiting for a backend to be built. The distinction that matters: the *observability tooling* is real and complete; the *thing it would observe* (actual background jobs) simply doesn't exist yet in this codebase.

## 17. Notifications

None.

## 18. Module Dependencies

System is architecturally downstream of the *entire application* by design, via the global audit-log listener described in the README: every model's `created`/`updated`/`deleted` event across every other module feeds `audit_logs`, which Activity Center and Audit Logs then surface. Beyond that observational relationship:
- **Account**: `ErpUser` (as the performer on every `AuditLog`/`LoginHistory` row; the login flow itself, `ErpAuthController`, lives outside both `system` and `account` route prefixes but populates a table System reads).
- **Settings**: `ErpRole` (consulted by `CheckErpPermission` for every gated route in this module).
- No other module reads from or writes to System's own tables (`audit_logs`, `login_histories`) — the dependency direction is strictly inbound (everything else feeds System; System feeds nothing back except cross-links from the Dashboard's Recent Activities tile, which points to `/system/activity-center`).

## 19. Screenshots

Not available — generated from source code.

## 20. Final Deliverable

System is a 7-real / 5-generic observability module gated behind `system.manage` at the read level (unusual for this app). Audit Logs and Login History are query layers over data other parts of the app write; Queue Monitor, Scheduled Jobs, Cache Manager, and System Health were verified line-by-line and are all genuinely real, live introspection with zero synthesized values — though the underlying subsystems they observe (background jobs, scheduled tasks) are currently empty because nothing in this codebase uses them yet. Cache Manager's clear action has a real, destructive effect on the running app. No create/edit/delete flow, file storage, background job, or notification exists within this module itself.
