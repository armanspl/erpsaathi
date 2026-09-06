# Reports

See [README.md](README.md) for cross-cutting architecture (routing, permission model, generic-page mechanism, audit logging, calculator-service pattern) — not repeated here.

## 1. Module Overview

The Reports module is a cross-module read-only summary surface: four small live-computed dashboards — Student Reports, Admission Reports, Fee Reports, Finance Reports — each backed by its own `App\Services\*ReportCalculator` service that queries other modules' tables on every request. **Reports owns no database table of its own.** Every genuine page here is 100% read-only: there is no create/edit/delete anywhere in this module, no stored report table, and no export.

The module's sidebar has 12 leaves, but only 4 are actually owned by Reports (`StudentReportController`, `AdmissionReportController`, `FeeReportController`, `FinanceReportController` in `app/Http/Controllers/Erp/Reports/`). The other 8 either route to a report controller/component that belongs to and is documented under a different module, or are unimplemented generic placeholders. See §2 for the full breakdown.

The other defining fact of this module, verified directly from `routes/erp_api.php`: the entire `reports` route prefix is wrapped in `->middleware('erp.permission:reports.view')` **at the route-group level**, so unlike almost every other module in the app (where README's stated convention is "reads are open to any authenticated erp user; writes are gated"), **even GET/read requests to the 4 real Reports endpoints require the `reports.view` permission**. Per README §"Permission model," Reports and System are the only two modules in the entire app where reads are gated at all.

## 2. Menu Structure

Menu group `Reports` (`resources/js/erp/data/menu.js`), 12 children:

| Sidebar Leaf | Real / Generic | Owned by Reports? | Backend | Frontend Component |
|---|---|---|---|---|
| Student Reports | Real | Yes | `StudentReportController` -> `StudentReportCalculator` | `pages/reports/StudentReports.vue` |
| Admission Reports | Real | Yes | `AdmissionReportController` -> `AdmissionReportCalculator` | `pages/reports/AdmissionReports.vue` |
| Attendance Reports | Real | No, reused from Attendance module | `AttendanceReportController` (`Erp\Attendance`) | `pages/attendance/AttendanceReports.vue` |
| Fee Reports | Real | Yes | `FeeReportController` -> `FeeReportCalculator` | `pages/reports/FeeReports.vue` |
| Finance Reports | Real | Yes | `FinanceReportController` -> `FinanceReportCalculator` | `pages/reports/FinanceReports.vue` |
| Salary Reports | Real | No, reused from Finance & Payroll module | `SalaryReportController` (`Erp\FinancePayroll`, presumed namespace) | `pages/finance-payroll/SalaryReports.vue` |
| Transport Reports | Real | No, reused from Transport Management module | `TransportReportController` (`Erp\Transport`) | `pages/transport/TransportReports.vue` |
| Examination Reports | Real | No, reused from Exam Management module | exam reports controller (`Erp\Exam`) | `pages/exams/ExamReports.vue` |
| Library Reports | Real | No, reused from Library module | `LibraryReportController` (`Erp\Library`) | `pages/library/LibraryReports.vue` |
| UDISE Reports | Generic | no controller anywhere | none | `GenericModule.vue` |
| Custom Reports | Generic | no controller anywhere | none | `GenericModule.vue` |
| Dashboard Reports | Generic | no controller anywhere | none | `GenericModule.vue` |

Confirmed directly from `resources/js/erp/router/index.js`'s `FLAGSHIP_ROUTES` map — the `/reports/*` paths point at components imported from **other modules' page directories**, not `pages/reports/`, for 5 of the 9 real leaves:
```js
'/reports/student-reports': StudentReports,       // pages/reports/StudentReports.vue  (owned)
'/reports/admission-reports': AdmissionReports,    // pages/reports/AdmissionReports.vue (owned)
'/reports/fee-reports': FeeReports,                // pages/reports/FeeReports.vue      (owned)
'/reports/finance-reports': FinanceReports,        // pages/reports/FinanceReports.vue  (owned)
'/reports/attendance-reports': AttendanceReports,  // pages/attendance/AttendanceReports.vue (reused)
'/reports/salary-reports': SalaryReports,          // pages/finance-payroll/SalaryReports.vue (reused)
'/reports/transport-reports': TransportReports,    // pages/transport/TransportReports.vue    (reused)
'/reports/examination-reports': ExamReports,       // pages/exams/ExamReports.vue             (reused)
'/reports/library-reports': LibraryReports,        // pages/library/LibraryReports.vue        (reused)
```
Each of the 5 reused components/controllers is the same component/controller reachable from its home module's own "Reports" leaf (e.g. Attendance module's own "Attendance Reports" leaf and `GET /erp/api/attendance/reports` both resolve to the exact same `AttendanceReportController`/`AttendanceReports.vue` — one implementation surfaced at two menu locations, not a duplicate). This file therefore documents in depth **only the 4 owned pages** (Student/Admission/Fee/Finance); the 5 reused pages belong under their home module's own `.md` file, and UDISE/Custom/Dashboard Reports get the one-line generic reason above.

No page in this module — owned or reused — has any Import/Export or create/edit/delete affordance; see §7/§8/§9/§11.

## 3. Every Page (owned pages only)

All four share the same shape: a Laravel route gated by `reports.view`, a single-method controller that returns `SomeCalculator::summary()` verbatim as JSON, and a Vue page that calls it once on mount with no parameters.

### Student Reports
- Frontend URL: `/erp/dashboard/reports/student-reports`
- Backend URL: `GET /erp/api/reports/students`
- Controller: `App\Http\Controllers\Erp\Reports\StudentReportController@index` -> `App\Services\StudentReportCalculator::summary()`
- Validation: N/A, read-only — no request input is accepted or validated (`index()` takes no parameters beyond the framework request).
- Permissions: `erp.permission:reports.view`, applied at the `Route::prefix('reports')` group level in `routes/erp_api.php` — this gates the GET itself, not just a write.

### Admission Reports
- Frontend URL: `/erp/dashboard/reports/admission-reports`
- Backend URL: `GET /erp/api/reports/admissions`
- Controller: `App\Http\Controllers\Erp\Reports\AdmissionReportController@index` -> `App\Services\AdmissionReportCalculator::summary()`
- Validation: N/A, read-only.
- Permissions: `reports.view` (group-level gate).

### Fee Reports
- Frontend URL: `/erp/dashboard/reports/fee-reports`
- Backend URL: `GET /erp/api/reports/fees`
- Controller: `App\Http\Controllers\Erp\Reports\FeeReportController@index` -> `App\Services\FeeReportCalculator::summary()`
- Validation: N/A, read-only.
- Permissions: `reports.view` (group-level gate).

### Finance Reports
- Frontend URL: `/erp/dashboard/reports/finance-reports`
- Backend URL: `GET /erp/api/reports/finance`
- Controller: `App\Http\Controllers\Erp\Reports\FinanceReportController@index` -> `App\Services\FinanceReportCalculator::summary()`
- Validation: N/A, read-only.
- Permissions: `reports.view` (group-level gate).

## 4. Filter / Parameter UI

None of the 4 owned report pages have any filter, date-range, or parameter control. Each Vue page (`StudentReports.vue`, `AdmissionReports.vue`, `FeeReports.vue`, `FinanceReports.vue`) fires a single parameterless `client.get(...)` in its `<script setup>` on component load and renders whatever the calculator returns — there is no `FilterBar`, no date picker, no class/session selector, no query string ever built. The only interaction in these pages is a one-time GET on mount; everything shown is whatever the calculator computes over the entire underlying table(s) (or, for Fee Reports, the current academic session — see below) at request time. This is worth flagging explicitly: unlike the reused Attendance/Salary/Transport/Examination/Library report pages (documented separately, which may have their own filter UI), the 4 pages genuinely owned by this module have none.

The one exception to "whole table" scope is Fee Reports, which is implicitly scoped to `AcademicSession::where('is_current', true)->first()` — not user-selectable, just whichever session Settings currently has flagged current. If no session is flagged current, the page shows an amber "No current academic session is configured" notice instead of any figures (`FeeReports.vue`, checking `!report.session`).

## 5. Every Field (report output fields, in place of create-form fields — there is no create form)

### Student Reports (`StudentReportCalculator::summary()`) — source: `students` table
| Field | Type | Computation |
|---|---|---|
| `total_students` | int | `Student::all()->count()` |
| `active_students` | int | count where `status = 'Active'` |
| `inactive_students` | int | `total_students - active_students` |
| `gender_breakdown` | object | active students grouped by `gender` |
| `class_wise_strength` | array of `{class, count}` | active students grouped by `schoolClass.name` relation, unassigned bucketed as `'Unassigned'`, sorted by class name |
| `new_admissions_this_month` | int | active students whose `admission_date` matches the current `Y-m` |

### Admission Reports (`AdmissionReportCalculator::summary()`) — source: `admission_enquiries` table
| Field | Type | Computation |
|---|---|---|
| `total_enquiries` | int | all rows |
| `by_stage` | object | grouped by `stage` |
| `admitted` | int | count where `stage = 'admitted'` |
| `rejected` | int | count where `stage = 'rejected'` |
| `conversion_rate` | float | `round(admitted / total_enquiries * 100, 1)`, `0` if no enquiries |
| `new_this_month` | int | rows whose `created_at` matches current `Y-m` |
| `by_source` | object | grouped by `source` (blank/null bucketed as `'Unknown'`) |

### Fee Reports (`FeeReportCalculator::summary()`) — source: `academic_sessions`, `students`, `fee_payments`, plus `FeeCalculator::forStudent()` per active student
| Field | Type | Computation |
|---|---|---|
| `session` | object/null | current `AcademicSession` (`is_current = true`); if none, every numeric field below is zeroed/emptied instead of computed |
| `total_fee` | float | sum of `FeeCalculator::forStudent($student, $session)['total_fee']` over every Active student |
| `total_discount` | float | sum of `...['total_discount']` over the same set |
| `total_paid` | float | sum of `...['total_paid']` over the same set |
| `total_due` | float | `max(0, total_fee - total_discount - total_paid)` |
| `collection_rate` | float | `round(total_paid / (total_fee - total_discount) * 100, 1)`, `0` if `total_fee` is 0 |
| `defaulters_count` | int | count of active students whose `FeeCalculator` position has `due > 0` |
| `payment_mode_breakdown` | object | `FeePayment` rows for the current session, summed by `payment_mode` |
| `collected_this_month` | float | same payments filtered to `payment_date` in the current `Y-m`, summed |

### Finance Reports (`FinanceReportCalculator::summary()`) — source: `incomes`, `expenses`, `expense_categories` (via relation), `bank_accounts` (+ its own transactions/income/expense)
| Field | Type | Computation |
|---|---|---|
| `total_income` | float | sum of all `Income.amount` |
| `total_expense` | float | sum of all `Expense.amount` |
| `net_balance` | float | `total_income - total_expense` |
| `income_this_month` | float | incomes whose `date` matches current `Y-m`, summed |
| `expense_this_month` | float | expenses whose `date` matches current `Y-m`, summed |
| `total_bank_balance` | float | sum over all `BankAccount`s of `currentBalance()` (deposits minus withdrawals plus bank-tagged income minus bank-tagged expense; see `BankAccount::currentBalance()`) |
| `top_expense_categories` | object, top 5 | expenses grouped by `expenseCategory.name` (`'Uncategorized'` if null), summed, sorted descending, `take(5)` |

## 6. Database Relationships

Reports owns no models/tables, so there are no relationships to define here. The relations actually traversed by the 4 calculators (all belonging to other modules) are:
- `Student::schoolClass()` — used by Student Reports for class-wise strength
- `Expense::expenseCategory()` — used by Finance Reports for top categories
- `BankAccount::transactions()` — used indirectly via `currentBalance()` for Finance Reports
- `FeeCalculator::forStudent()` internally resolves a student's applicable fee structure/discounts/payments (Fee Management module) — treated as a black box by `FeeReportCalculator`, which only consumes its return array (`total_fee`, `total_discount`, `total_paid`, `due`).

## 7. Create Flow

N/A. Reports has no create action anywhere — no route, no controller method, no form. All 4 owned controllers expose exactly one method (`index`) each.

## 8. Edit Flow

N/A. Same reasoning as Create — nothing in this module is ever written to.

## 9. Delete Flow

N/A. No delete route, no soft- or hard-delete logic, nothing to cascade — Reports reads other modules' tables but never removes rows from them or from anything of its own (it has no tables of its own).

## 10. Business Rules

The calculation logic per calculator, exactly as read from `app/Services/*ReportCalculator.php`:

- StudentReportCalculator loads `Student::with('schoolClass:id,name')->get()` once, then does all aggregation in-memory over the Collection (no separate queries per stat). "This month" = `now()->format('Y-m')` matched against `admission_date`.
- AdmissionReportCalculator loads `AdmissionEnquiry::all()` once, aggregates in-memory. Conversion rate formula: `admitted / total_enquiries * 100`, rounded to 1 decimal, guarded against division by zero.
- FeeReportCalculator short-circuits to an all-zero/null response if no `AcademicSession` has `is_current = true`. Otherwise it loops every Active student individually through `FeeCalculator::forStudent()` (one calculator call per student) to build the aggregate totals, then does one separate `FeePayment` query for the payment-mode breakdown and this-month collections. `collection_rate` divides by `(total_fee - total_discount)`, not raw `total_fee`.
- FinanceReportCalculator loads `Income::all()` and `Expense::with('expenseCategory:id,name')->get()` once each, aggregates in-memory; `total_bank_balance` is the only field that issues additional queries, one set per `BankAccount` via `currentBalance()`.

All four are static, stateless, side-effect-free methods — calling them twice with no data change returns identical output; there is no caching layer, so each request recomputes from scratch.

## 11. Import & Export

Per README.md, only Student has a real importer and Export covers 9 fixed entities (none of which is a report). Reports has no Import and no Export of any kind — none of the 4 owned controllers or Vue pages reference `ExportController`, PhpSpreadsheet, or any download/print action; the pages render read-only stat cards and tables with no export button in their templates.

## 12. Reports Section — what each report shows

### Student Reports
- Shows: total/active/inactive student counts, a "new this month" stat, a class-wise strength table, and a gender breakdown table.
- Filters: none (see §4) — always whole-database scope.
- Tables used: `students` (with `classes`/`sections` via the `schoolClass` relation for labeling).
- Calculations: see §5/§10 — all in-memory aggregation of a single `Student::with('schoolClass')->get()` call; "new this month" compares `admission_date` to the current calendar month at request time.

### Admission Reports
- Shows: total enquiries, admitted count, rejected count, conversion rate, a by-stage breakdown table, a by-source breakdown table, and a "new enquiries this month" line.
- Filters: none.
- Tables used: `admission_enquiries`.
- Calculations: conversion rate = admitted / total x 100 (1 decimal); "this month" compares `created_at` (enquiry creation timestamp) to the current calendar month.

### Fee Reports
- Shows: total fee, total collected, total due, collection rate, discount given, defaulters count, collected-this-month, and a payment-mode breakdown table. Renders an amber warning instead of figures when no academic session is marked current.
- Filters: none — implicitly scoped to whichever `AcademicSession` has `is_current = true` (not user-selectable on this page; that flag is set under Settings/Academic Sessions).
- Tables used: `academic_sessions`, `students` (`status = 'Active'`), `fee_payments`, plus whatever `FeeCalculator::forStudent()` reads internally (fee structures/discounts — Fee Management module tables).
- Calculations: per-student fee position (`total_fee`, `total_discount`, `total_paid`, `due`) is computed by the Fee module's own `FeeCalculator::forStudent()` for every active student and summed; defaulters = count of active students with `due > 0`; collection rate divides paid by (fee minus discount); payment-mode breakdown and "collected this month" are separate `FeePayment` aggregations scoped to the current session.

### Finance Reports
- Shows: total income, total expense, net balance, total bank balance, income/expense this month, and a top-5 expense-categories table.
- Filters: none — whole-history scope for income/expense totals (not session-scoped, unlike Fee Reports).
- Tables used: `incomes`, `expenses` (+ `expense_categories` via relation for labeling), `bank_accounts` (+ each account's own transactions/tagged income/expense for balance).
- Calculations: net balance = income minus expense; bank balance = `BankAccount::currentBalance()` summed across all accounts (deposits minus withdrawals plus bank-tagged income minus bank-tagged expense, per that model's own method); top expense categories = expenses grouped by category name, summed, sorted descending, capped to top 5.

### Reused leaves owned by other modules
Attendance Reports, Salary Reports, Transport Reports, Examination Reports, and Library Reports are surfaced under this menu group but are documented in full under their home modules (`attendance.md`, `finance-payroll.md`, `transport.md`, `exam-management.md`, `library.md` respectively) — including a note there that they are dual-menu-mounted.

## 13. APIs

| Method | Endpoint | Controller@method | Auth |
|---|---|---|---|
| GET | `/erp/api/reports/students` | `StudentReportController@index` | `reports.view` |
| GET | `/erp/api/reports/admissions` | `AdmissionReportController@index` | `reports.view` |
| GET | `/erp/api/reports/fees` | `FeeReportController@index` | `reports.view` |
| GET | `/erp/api/reports/finance` | `FinanceReportController@index` | `reports.view` |

All four are gated by `->middleware('erp.permission:reports.view')` applied once at `Route::prefix('reports')->name('reports.')->middleware('erp.permission:reports.view')->group(...)` in `routes/erp_api.php` — every route inside, all of which are GETs, inherits the gate; there is no separate read/write split within this module because there are no writes.

Example, `GET /erp/api/reports/students`:
```json
{
  "total_students": 842,
  "active_students": 810,
  "inactive_students": 32,
  "gender_breakdown": { "Male": 430, "Female": 380 },
  "class_wise_strength": [
    { "class": "1", "count": 60 },
    { "class": "2", "count": 58 }
  ],
  "new_admissions_this_month": 14
}
```

Example, `GET /erp/api/reports/fees` (no current session):
```json
{
  "session": null,
  "total_fee": 0,
  "total_discount": 0,
  "total_paid": 0,
  "total_due": 0,
  "collection_rate": 0,
  "defaulters_count": 0,
  "payment_mode_breakdown": [],
  "collected_this_month": 0
}
```

Example, `GET /erp/api/reports/finance`:
```json
{
  "total_income": 1250000.0,
  "total_expense": 875000.0,
  "net_balance": 375000.0,
  "income_this_month": 92000.0,
  "expense_this_month": 61000.0,
  "total_bank_balance": 540000.0,
  "top_expense_categories": { "Salaries": 420000.0, "Utilities": 85000.0 }
}
```

A `403 {"message": "You do not have permission to perform this action."}` is returned by `CheckErpPermission` for any authenticated `erp` user whose role lacks `reports.view` (and lacks the `'*'` wildcard, and is not the hardcoded `role === 'admin'` bypass) — for every one of the 4 endpoints above, including the plain GETs.

## 14. Database Tables

Reports owns zero tables. It reads from other modules' tables only:

| Owned page | Tables read |
|---|---|
| Student Reports | `students`, `classes` (via `schoolClass` relation) |
| Admission Reports | `admission_enquiries` |
| Fee Reports | `academic_sessions`, `students`, `fee_payments`, plus Fee Management's structure/discount tables consumed inside `FeeCalculator::forStudent()` |
| Finance Reports | `incomes`, `expenses`, `expense_categories`, `bank_accounts`, plus that model's own transaction/income/expense lookups |

No PKs/FKs/unique constraints/indexes to document for this module itself, since there is no `reports`-owned migration at all.

## 15. File Storage

None. No field in any of the 4 calculators' output or any owned controller touches `Storage::` or file uploads — these are pure JSON aggregation endpoints.

## 16. Background Jobs

None. There is no scheduled command, queued job, or listener anywhere in the codebase that pre-computes, caches, snapshots, or emails any of these 4 reports — confirmed by the calculators being plain static synchronous methods called directly from the controller on each request, with no `app/Jobs/*` or `app/Console/Commands/*` reference to `StudentReportCalculator`, `AdmissionReportCalculator`, `FeeReportCalculator`, or `FinanceReportCalculator`. Every figure shown is computed fresh, in-request, every time the page loads — there is no report-generation step, digest email, or scheduled export.

## 17. Notifications

None. Reports is a passive read surface; no controller or calculator in this module sends an email, SMS, push notification, or writes to any notification-adjacent table. (Contrast with the Communication module, which — despite also not performing real external delivery — at least represents a messaging action; Reports doesn't attempt to notify anyone at all.)

## 18. Module Dependencies

Reports depends on nearly every other module for its underlying data, since it owns no tables of its own:

| Report | Depends on module | Tables/relations consumed |
|---|---|---|
| Student Reports | People, Academics | `students`, `Student::schoolClass()` (Classes & Sections) |
| Admission Reports | Admissions | `admission_enquiries` |
| Fee Reports | Fee Management, People, Academics (Sessions) | `academic_sessions`, `students`, `fee_payments`, `FeeCalculator::forStudent()` (Fee Management's structures/discounts) |
| Finance Reports | Finance & Payroll | `incomes`, `expenses`, `expense_categories`, `bank_accounts` (+ `BankAccount::currentBalance()`'s own transaction lookups) |

The 5 reused sidebar leaves each pull in their own home module's dependency chain (Attendance, Finance & Payroll/Salary, Transport, Exam Management, Library) — not re-documented here since those pages/controllers are owned there.

Reports has no reverse dependents — no other module reads from or references anything Reports produces, since Reports produces nothing persisted.

## 19. Screenshots

Not available; generated from source code.

## 20. Summary

The Reports module is a thin, entirely read-only aggregation layer: 4 genuinely owned pages (Student/Admission/Fee/Finance Reports), each a one-method controller (`GET .../{resource}` -> `SomeCalculator::summary()`) with no request parameters, no stored table, and no create/edit/delete/export capability — every figure is computed live, in-request, from other modules' tables (`students`, `admission_enquiries`, `academic_sessions`+`fee_payments`+`FeeCalculator`, `incomes`+`expenses`+`bank_accounts` respectively), confirmed by reading all four `*ReportCalculator` services in full. Five further sidebar leaves (Attendance/Salary/Transport/Examination/Library Reports) are dual-mounted views of controllers and Vue components that are actually owned by and documented under their home modules, not duplicated here. Three leaves (UDISE Reports, Custom Reports, Dashboard Reports) are unimplemented and fall back to the generic mock-data page. The module's single most important architectural fact: the entire `reports` route prefix is gated by `erp.permission:reports.view` at the group level, so unlike almost every other module, even reading these reports requires an explicit permission grant, one of only two modules in the app (with System) where reads themselves are access-controlled.
