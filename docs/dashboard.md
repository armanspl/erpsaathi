# Dashboard

See [README.md](README.md) for cross-cutting architecture (routing, permission model, generic-page mechanism, audit logging) — not repeated here.

## 1. Module Overview

The Dashboard module is the ERP's landing summary — a single read-only aggregation view computed live, on every request, from data owned by nearly every other module. It has no tables, no forms, and no CRUD of its own. Of its 8 sidebar leaves, **4 (Dashboard, Today's Summary, Quick Actions, Recent Activities) are literally the same Vue component making the same single backend call**; 1 (Upcoming Events) deliberately reuses Communication's real `Events.vue` page rather than duplicating it; the remaining 3 (Analytics, Notifications, Dashboard Reports) are unimplemented and fall back to `GenericModule.vue`.

## 2. Menu Structure

Menu group `Dashboard` (`resources/js/erp/data/menu.js`):

| Sidebar Label | Path | Component | Real? |
|---|---|---|---|
| Dashboard | `/` (root — special-cased as the first child of this group) | `Dashboard.vue` | **Real** |
| Analytics | `/dashboard/analytics` | `GenericModule.vue` | Generic — no backend/controller exists |
| Today's Summary | `/dashboard/today-s-summary` | `Dashboard.vue` | **Real** — same component & endpoint as root Dashboard |
| Quick Actions | `/dashboard/quick-actions` | `Dashboard.vue` | **Real** — same component & endpoint; "Quick Actions" is one static section within the full dashboard, not a separate view |
| Recent Activities | `/dashboard/recent-activities` | `Dashboard.vue` | **Real** — same component & endpoint; "Recent Activities" is one static section within the full dashboard, not a separate view |
| Upcoming Events | `/dashboard/upcoming-events` | `Events.vue` (Communication module's page, imported directly into `FLAGSHIP_ROUTES`) | **Real** — cross-module reuse, see §3.2 |
| Notifications | `/dashboard/notifications` | `GenericModule.vue` | Generic — no backend/controller exists |
| Dashboard Reports | `/dashboard/dashboard-reports` | `GenericModule.vue` | Generic — no backend/controller exists (the identically-named `Reports > Dashboard Reports` leaf is also generic, and is a separate, unrelated route) |

**Important nuance**: navigating to any of Dashboard / Today's Summary / Quick Actions / Recent Activities renders the *entire* same page top to bottom (welcome banner, stat cards, today's summary, class strength, fee collection status, recent admissions, pending tasks, upcoming events, recent activities, and the quick-actions grid) — the sidebar label does not filter or scroll to a sub-section. They are four different entry points into one identical screen.

## 3. Pages

### 3.1 Dashboard (`Dashboard.vue`)

- **URL (frontend)**: `/`, `/dashboard/today-s-summary`, `/dashboard/quick-actions`, `/dashboard/recent-activities` (all four render `Dashboard.vue`).
- **URL (backend)**: `GET /erp/api/dashboard`.
- **Controller/method**: `App\Http\Controllers\Erp\DashboardController@index` — a one-line pass-through: `return response()->json(DashboardCalculator::summary());`.
- **Validation**: none — the endpoint takes no request input.
- **Permissions**: none beyond `erp.auth` (session authentication). The route is declared standalone at the very top of `routes/erp_api.php`, outside any `erp.permission` middleware group, with the explicit comment "open to every authenticated ERP user regardless of role."

### 3.2 Upcoming Events (reused `Events.vue`)

- **URL (frontend)**: `/dashboard/upcoming-events`.
- **URL (backend)**: `GET /erp/api/communication/events` (Communication module's own endpoint — Dashboard's router entry points at the *same* component instance/import as Communication's `/communication/events` route; there is no Dashboard-specific events endpoint).
- **Controller/method**: `App\Http\Controllers\Erp\Communication\EventController@index` — returns all `Event` rows ordered by `event_date`, no filtering by date (the *page*, when reached from this sidebar leaf, shows every event; only the DashboardCalculator's own summary tile, described in §12, filters to future events).
- **Validation**: `store`/`update` validation (not reachable from the Dashboard sidebar leaf, but documented for completeness since `Events.vue` is the same page used for editing): `title` required max:255, `description` nullable max:1000, `venue` nullable max:255, `event_date` required date, `start_time` nullable `H:i`, `end_time` nullable `H:i` `after:start_time`, `status` required `in:Scheduled,Cancelled`.
- **Permissions**: reads open to all; writes gated by `communication.manage` (documented fully in `communication.md` — not re-derived here).

This is a deliberate, explicit cross-module reuse: the Dashboard menu group does not own an Events page or table — it points straight at Communication's.

## 4. Form Layout

None. The Dashboard is 100% read-only display: stat cards, tables, and progress bars fed entirely by the `GET /dashboard` response. The one interactive element is the "Quick Actions" grid — 7 hardcoded `RouterLink` shortcuts (Add Student, Collect Fee, Import Excel, Export Data, Add Notice, Generate Report, Theme Creator) defined as a static array directly in `Dashboard.vue`'s `<script setup>` — **these links are not derived from the backend response in any way**, they are compile-time constants that simply navigate elsewhere in the app.

## 5. Fields

There are no input fields. The table below documents the shape of the `GET /dashboard` JSON response instead, since that is what actually populates the page:

| Response key | Populated from | Computed by |
|---|---|---|
| `people_stats.students/teachers/staff/drivers` | `Student` (status=Active), `Teacher`, `Staff`, `Driver` counts | `DashboardCalculator::summary()` |
| `money_stats.fee_collected/fee_pending` | `FeeReportCalculator::summary()`'s `total_paid`/`total_due` | `FeeReportCalculator` (Fee Management) |
| `money_stats.income/expense` | `FinanceReportCalculator::summary()`'s `total_income`/`total_expense` | `FinanceReportCalculator` (Finance & Payroll) |
| `today_summary.students/staff.present/absent` | `Attendance` rows for today, keyed by `attendable_type` | `DashboardCalculator::todaySummary()` |
| `today_summary.fee.received/receipts` | `FeePayment` rows `whereDate('payment_date', today)` | `DashboardCalculator::todaySummary()` |
| `class_strength` | Active `Student` rows grouped by `schoolClass.name`, split boys/girls | `DashboardCalculator::classStrength()` |
| `fee_collection_status` | Active `Student` rows grouped by class, each run through `FeeCalculator::forStudent()` for the current `AcademicSession` | `DashboardCalculator::feeCollectionStatus()` |
| `recent_admissions` | Latest 5 `Student` rows by `admission_date`/`id` | `DashboardCalculator::recentAdmissions()` |
| `pending_tasks` | See §12 | `DashboardCalculator::pendingTasks()` |
| `upcoming_events` | Next 5 non-cancelled `Event` rows with `event_date >= today` | `DashboardCalculator::summary()` inline |
| `recent_activities` | Latest row from 5 different models, merged and time-sorted | `DashboardCalculator::recentActivities()` |

## 6. Database Relationships

`DashboardCalculator` and `DashboardController` own no models or tables of their own. Relations it traverses (all `belongsTo`, all read-only): `Student::schoolClass()`, `FeePayment::student()`, `SalarySlip::employee()` (polymorphic `MorphTo`), `Certificate::student()`. No relation is ever written to by this module.

## 7. Create Flow

None. The Dashboard module creates nothing.

## 8. Edit Flow

None.

## 9. Delete Flow

None — no delete affordance anywhere in this module. (The one exception, Support Ticket deletion reachable via the "Support Tickets" quick action, belongs to and is documented in `account.md`.)

## 10. Business Rules

- **Four sidebar leaves, one component, one endpoint.** Dashboard, Today's Summary, Quick Actions, and Recent Activities all resolve to `Dashboard.vue` and all trigger the identical `GET /dashboard` call — there is no server-side or client-side branching based on which of the four paths was used to arrive at the page.
- **Quick Actions are static, not data-driven.** The 7 shortcuts are a hardcoded array in the component; none of them come from the `/dashboard` API response, a user's role/permissions, or any database table. All 7 are shown identically to every user, including one (Export Data) that deep-links into Import & Export with a preset `type=global-workbook-export` query.
- **`fee_collection_status` and `class_strength` both silently return an empty array if no `AcademicSession` has `is_current = true`.**
- **`recent_activities` is a merge-then-truncate, not a true activity log.** It fetches at most one *latest* row from each of `Student`, `FeePayment`, `SalarySlip`, `Certificate`, and `ImportExportLog` (5 candidate rows total), sorts those 5 by timestamp, and keeps the top 5 — meaning it can never show two "New admission" lines even if two students were added seconds apart; it is not a query of `audit_logs` (that's the System module's Activity Center/Audit Logs — see `system.md`).
- **Upcoming Events cross-reuse is real, not a redirect.** `/dashboard/upcoming-events` genuinely mounts Communication's own `Events.vue` component (same JS import), so it has full read/write event capability if the user's role permits it — it is not a stripped-down or read-only preview.

## 11. Import & Export

No. The Dashboard module has no import or export capability of its own. (`DashboardCalculator::pendingTasks()` links out to Import & Export's Failed Records view as one of its tiles, but that is a navigation shortcut, not a Dashboard-owned capability — see `import-export.md`.)

## 12. Reports

The Dashboard *is*, in effect, the ERP's flagship live summary/report. `DashboardCalculator::summary()` (`app/Services/DashboardCalculator.php`, read in full) aggregates, on every single request with no caching or stored snapshot:

- **People stats** — live counts: `Student::where('status','Active')->count()`, `Teacher::count()`, `Staff::count()`, `Driver::count()`.
- **Money stats** — delegates to two other modules' calculators rather than recomputing: `FeeReportCalculator::summary()` for `total_paid`/`total_due`, `FinanceReportCalculator::summary()` for `total_income`/`total_expense`.
- **Today's Summary** — same-day `Attendance` rows split by `attendable_type` (`student` vs `teacher`/`staff`, using the app's polymorphic morph map) and same-day `FeePayment` rows.
- **Class Strength** — active students grouped by class name, boys/girls counted by `gender`.
- **Fee Collection Status** — per class, runs every active student in that class through `FeeCalculator::forStudent()` (the shared "computed, not stored" fee engine — see README) against the current `AcademicSession`, and reports a rounded collection percentage plus a count of students still owing money.
- **Recent Admissions** — latest 5 students by admission date.
- **Pending Tasks** — five live counts, each a `RouterLink` deep-link elsewhere in the app:
  | Label | Source | Links to |
  |---|---|---|
  | Fee Payments Pending | `FeeReportCalculator::summary()`'s `defaulters_count` | `/fee-management/fee-due` |
  | Leave Requests Pending | `LeaveRequest::where('status','Pending')->count()` | `/attendance/leave-approval` |
  | Admission Enquiries | `AdmissionEnquiry::whereIn('stage', ['enquiry','follow_up','registered'])->count()` | `/admissions/enquiry` |
  | Salary Slips Pending | `SalarySlip::where('status','Pending')->count()` | `/finance-and-payroll/salary-slips` |
  | Import Failed Records | `ImportFailedRow::count()` | `/import-export?type=failed-records` |
- **Upcoming Events** — next 5 `Event` rows, `status != 'Cancelled'` and `event_date >= today`, ordered ascending.
- **Recent Activities** — see §10.

## 13. APIs

| Method | Path | Controller@method | Auth |
|---|---|---|---|
| GET | `/erp/api/dashboard` | `DashboardController@index` | any authenticated user, no permission key |
| GET | `/erp/api/communication/events` | `EventController@index` (Communication module, reused for Upcoming Events) | any authenticated user |

Example response (`GET /erp/api/dashboard`, abbreviated):
```json
{
  "people_stats": { "students": 482, "teachers": 31, "staff": 18, "drivers": 6 },
  "money_stats": { "fee_collected": 1245000, "fee_pending": 318500, "income": 1560000, "expense": 742300 },
  "today_summary": {
    "students": { "present": 401, "absent": 22 },
    "staff": { "present": 44, "absent": 3 },
    "fee": { "received": 18500, "receipts": 6 }
  },
  "class_strength": [ { "class": "Nursery", "boys": 12, "girls": 14 } ],
  "fee_collection_status": [ { "class": "Nursery", "collected": 78, "pending": 4 } ],
  "recent_admissions": [ { "adm": "A-1045", "name": "…", "class": "5", "date": "30-07-26" } ],
  "pending_tasks": [ { "label": "Fee Payments Pending", "count": 37, "to": "/fee-management/fee-due" } ],
  "upcoming_events": [ { "title": "Annual Day", "date": "15 Aug" } ],
  "recent_activities": [ "New admission created — Aarav Sharma", "Fee receipt RC-2201 generated for Aarav Sharma" ]
}
```

## 14. Database Tables

None owned by this module. It only *reads* tables owned elsewhere: `students`, `teachers`, `staff`, `drivers`, `attendances`, `fee_payments`, `leave_requests`, `admission_enquiries`, `salary_slips`, `import_failed_rows`, `import_export_logs`, `events`, `certificates`, `academic_sessions`.

## 15. File Storage

None.

## 16. Background Jobs

None. Every figure is computed synchronously within the request — there is no cron-refreshed cache or precomputed snapshot table behind the Dashboard.

## 17. Notifications

None.

## 18. Module Dependencies

The Dashboard is the most cross-cutting module in the ERP by design — it reads from nearly every other module rather than owning data:
- **People**: `Student`, `Teacher`, `Staff`, `Driver`
- **Attendance**: `Attendance`
- **Fee Management**: `FeePayment`, `FeeCalculator`, `FeeReportCalculator`
- **Finance & Payroll**: `FinanceReportCalculator`, `SalarySlip`
- **Attendance/HR**: `LeaveRequest`
- **Admissions**: `AdmissionEnquiry`
- **Import & Export**: `ImportFailedRow`, `ImportExportLog`
- **Communication**: `Event` (both a summary tile and, via Upcoming Events, the full reused page)
- **Documents**: `Certificate`
- **Settings**: `AcademicSession` (for the "current session" used in fee-collection math)

## 19. Screenshots

Not available — generated from source code.

## 20. Final Deliverable

The Dashboard module ships one real page (`Dashboard.vue`) reached via four different sidebar labels and one backend endpoint (`GET /dashboard`, itself a thin wrapper around `DashboardCalculator::summary()`), plus one deliberate cross-module reuse of Communication's `Events.vue` for "Upcoming Events." It has no tables, no CRUD, no background jobs, and no notifications of its own — it is purely a live, uncached aggregation surface over People, Attendance, Fee Management, Finance & Payroll, Admissions, Import & Export, Communication, and Documents. 3 of its 8 sidebar leaves (Analytics, Notifications, Dashboard Reports) remain unimplemented generic placeholders.
