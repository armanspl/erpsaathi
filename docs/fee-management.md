# Fee Management

See [README.md](README.md) for cross-cutting architecture (routing, permission model, generic-page mechanism, audit logging, calculator-service pattern) — not repeated here.

## 1. Module Overview

Fee Management is the ERP's billing/collection ledger: define what each class owes per academic session (Fee Structure, built from Fee Heads), apply per-student discounts, collect payments against that structure (Pay Fee → Fee Receipt), refund payments, and see who still owes money (Fee Due). Nothing about a student's balance is a stored number — every "amount due" figure anywhere in this module (Pay Fee screen, Fee Due list, Fee Reports) is computed on the fly by `app/Services/FeeCalculator.php` from three source tables: `erp_fee_structures` (what's owed), `fee_discounts` (what's waived), and `fee_payments` (what's been collected, net of refunds). There is no `fees_balance` column on `students` for this module (contrast with the deliberate static snapshot field of the same name used only by Student Import — see `student-import-management.md`).

Fine Rules exist as a standalone configuration table (`fine_rules`) but, per the current controller code, are **not** wired into any calculation — the fine amount collected on a receipt is a free-text number an operator types into the Pay Fee form. See Business Rules below.

## 2. Menu Structure

Menu group `Fee Management` (`resources/js/erp/data/menu.js`), 14 children. Route base `/fee-management/*`, resolved via `FLAGSHIP_ROUTES` in `resources/js/erp/router/index.js`.

| Menu Label | Path | Real / Generic | Vue Component | Note |
|---|---|---|---|---|
| Fee Heads | `/fee-management/fee-heads` | Real | `FeeHeads.vue` | |
| Fee Groups | `/fee-management/fee-groups` | Generic | `GenericModule.vue` | Not in `FLAGSHIP_ROUTES` — no backend concept of "fee groups" separate from fee heads exists. |
| Fee Structure | `/fee-management/fee-structure` | Real | `FeeStructure.vue` | |
| Fee Discounts | `/fee-management/fee-discounts` | Real | `FeeDiscounts.vue` | |
| Fine Rules | `/fee-management/fine-rules` | Real | `FineRules.vue` | CRUD only — not applied by any calculation (see Business Rules). |
| Pay Fee | `/fee-management/pay-fee` | Real | `PayFee.vue` | |
| Fee Receipt | `/fee-management/fee-receipt` | Real | `FeeReceipts.vue` | Shares component with Fee Refund. |
| Fee Refund | `/fee-management/fee-refund` | Real | `FeeReceipts.vue` | Same component/route target as Fee Receipt — same list, same refund action; the two menu leaves are cosmetic aliases into one page. |
| Fee Due | `/fee-management/fee-due` | Real | `FeeDue.vue` | |
| Fee Collection Report | `/fee-management/fee-collection-report` | Real | `FeeCollectionReport.vue` | Shares component with Daily Collection and Online Payments; page title and default filters vary by route path (see §12). |
| Daily Collection | `/fee-management/daily-collection` | Real | `FeeCollectionReport.vue` | Same component, defaults `from`/`to` filters to today. |
| Online Payments | `/fee-management/online-payments` | Real | `FeeCollectionReport.vue` | Same component, defaults `mode` filter to `UPI` and excludes `Cash` rows client-side. |
| Fee Settings | `/fee-management/fee-settings` | Generic | `GenericModule.vue` | Not in `FLAGSHIP_ROUTES` — no backend settings entity for this module beyond the tables above. |
| Tally Accounting | `/fee-management/tally-accounting` | Generic | `GenericModule.vue` | Not in `FLAGSHIP_ROUTES` — no Tally/accounting-software integration exists. |

## 3–5. Pages: URL, Controller, Validation, Permissions, Form Layout, Fields

All reads below require only an authenticated `erp` guard user (no extra key). All writes require `erp.permission:fee.manage` (role key `fee.manage`, label "Fee Management" in `resources/js/erp/pages/settings/Roles.vue`).

### 3.1 Fee Heads (`FeeHeads.vue`)

- Frontend: `/fee-management/fee-heads`
- Backend: `GET/POST /erp/api/fee-management/heads`, `PUT/DELETE /erp/api/fee-management/heads/{feeHead}`
- Controller: `App\Http\Controllers\Erp\FeeManagement\FeeHeadController` — `index`, `store`, `update`, `destroy`

Validation (`store`/`update`, verbatim from `FeeHeadController`):
```php
'name' => 'required|string|max:255',
'description' => 'nullable|string|max:255',
```

Form Layout: single-column SlideOver with two fields — Name (text, required), Description (text, optional).

Fields:

| Label | DB Column | Table | Type | Required | Validation | Default | Source | FK |
|---|---|---|---|---|---|---|---|---|
| Name | `name` | `fee_heads` | string | Required | `required\|string\|max:255` | — | free text | — |
| Description | `description` | `fee_heads` | string, nullable | Optional | `nullable\|string\|max:255` | null | free text | — |

### 3.2 Fee Structure (`FeeStructure.vue`)

- Frontend: `/fee-management/fee-structure`
- Backend: `GET/POST /erp/api/fee-management/structures`, `PUT/DELETE /erp/api/fee-management/structures/{feeStructure}`
- Controller: `App\Http\Controllers\Erp\FeeManagement\ErpFeeStructureController` — `index`, `store`, `update`, `destroy`

Validation (verbatim, private `validated()` helper):
```php
'academic_session_id' => 'required|exists:academic_sessions,id',
'school_class_id' => 'required|exists:school_classes,id',
'fee_head_id' => [
    'required', 'exists:fee_heads,id',
    Rule::unique('erp_fee_structures', 'fee_head_id')
        ->where(fn ($q) => $q->where('academic_session_id', $request->academic_session_id)->where('school_class_id', $request->school_class_id))
        ->ignore($structure?->id),
],
'amount' => 'required|numeric|min:0',
'frequency' => ['required', Rule::in(['one_time', 'monthly', 'quarterly', 'annual'])],
```

Form Layout: SlideOver — Academic Session (select), Class (select), Fee Head (select), Amount ₹ (number, required), Frequency (select: One Time / Monthly / Quarterly / Annual).

Fields:

| Label | DB Column | Table | Type | Required | Validation | Default | Source | FK |
|---|---|---|---|---|---|---|---|---|
| Academic Session | `academic_session_id` | `erp_fee_structures` | bigint FK | Required | `required\|exists:academic_sessions,id` | current session (`is_current=1`) preselected on Add | `GET /settings/academic-sessions` | `academic_sessions.id` |
| Class | `school_class_id` | `erp_fee_structures` | bigint FK | Required | `required\|exists:school_classes,id` | none | `GET /academics/classes` | `school_classes.id` |
| Fee Head | `fee_head_id` | `erp_fee_structures` | bigint FK | Required | `required\|exists:fee_heads,id` + unique per (session, class) | none | `GET /fee-management/heads` | `fee_heads.id` |
| Amount | `amount` | `erp_fee_structures` | decimal(10,2) | Required | `required\|numeric\|min:0` | none | free entry | — |
| Frequency | `frequency` | `erp_fee_structures` | enum | Required | `required\|in:one_time,monthly,quarterly,annual` | `annual` | fixed select | — |

**One row per (session, class, fee head)** is enforced both by a DB unique index (`erp_fee_structures_unique` on `academic_session_id, school_class_id, fee_head_id`) and by the `Rule::unique(...)->where(...)->ignore()` validation rule.

### 3.3 Fee Discounts (`FeeDiscounts.vue`)

- Frontend: `/fee-management/fee-discounts`
- Backend: `GET/POST /erp/api/fee-management/discounts`, `PUT/DELETE /erp/api/fee-management/discounts/{feeDiscount}`
- Controller: `App\Http\Controllers\Erp\FeeManagement\FeeDiscountController` — `index`, `store`, `update`, `destroy`

Validation (verbatim):
```php
'student_id' => 'required|exists:students,id',
'fee_head_id' => 'nullable|exists:fee_heads,id',
'type' => ['required', Rule::in(['percentage', 'fixed'])],
'value' => 'required|numeric|min:0',
'reason' => 'nullable|string|max:255',
```

Form Layout: SlideOver — Student (select), Fee Head (select, optional — "leave blank to apply to total fee"), Type (select: Percentage/Fixed) and Value (number) side by side, Reason (text).

Fields:

| Label | DB Column | Table | Type | Required | Validation | Default | Source | FK |
|---|---|---|---|---|---|---|---|---|
| Student | `student_id` | `fee_discounts` | bigint FK | Required | `required\|exists:students,id` | none | `GET /people/students` | `students.id` |
| Fee Head | `fee_head_id` | `fee_discounts` | bigint FK, nullable | Optional | `nullable\|exists:fee_heads,id` | null (= applies to whole fee total, not one head) | `GET /fee-management/heads` | `fee_heads.id` |
| Type | `type` | `fee_discounts` | enum | Required | `required\|in:percentage,fixed` | `percentage` (form default) / DB default `fixed` | fixed select | — |
| Value | `value` | `fee_discounts` | decimal(10,2) | Required | `required\|numeric\|min:0` | none | free entry | — |
| Reason | `reason` | `fee_discounts` | string, nullable | Optional | `nullable\|string\|max:255` | null | free text | — |

Note: a discount with `fee_head_id = null` is treated by `FeeCalculator` as applying against the student's **total** fee (`total_fee`), not a single head — the calculator sums all discounts against the total, it does not subtract per-head.

### 3.4 Fine Rules (`FineRules.vue`)

- Frontend: `/fee-management/fine-rules`
- Backend: `GET/POST /erp/api/fee-management/fine-rules`, `PUT/DELETE /erp/api/fee-management/fine-rules/{fineRule}`
- Controller: `App\Http\Controllers\Erp\FeeManagement\FineRuleController` — `index`, `store`, `update`, `destroy`

Validation (verbatim):
```php
'name' => 'required|string|max:255',
'type' => ['required', Rule::in(['per_day', 'fixed'])],
'amount' => 'required|numeric|min:0',
'grace_days' => 'nullable|integer|min:0',
```

Form Layout: SlideOver — Name (text), Type (select: Per Day/Fixed) + Amount ₹ (number) side by side, Grace Days (number).

Fields:

| Label | DB Column | Table | Type | Required | Validation | Default | Source | FK |
|---|---|---|---|---|---|---|---|---|
| Name | `name` | `fine_rules` | string | Required | `required\|string\|max:255` | — | free text | — |
| Type | `type` | `fine_rules` | enum | Required | `required\|in:per_day,fixed` | `fixed` | fixed select | — |
| Amount | `amount` | `fine_rules` | decimal(10,2) | Required | `required\|numeric\|min:0` | — | free entry | — |
| Grace Days | `grace_days` | `fine_rules` | unsigned int | Optional | `nullable\|integer\|min:0` | `0` | free entry | — |

### 3.5 Pay Fee (`PayFee.vue`)

- Frontend: `/fee-management/pay-fee`
- Backend reads: `GET /erp/api/fee-management/students/{student}/due` (drives the Collect Payment panel), `GET /erp/api/people/students`
- Backend write: `POST /erp/api/fee-management/payments`
- Controller: `App\Http\Controllers\Erp\FeeManagement\FeePaymentController` — `due`, `store`

Validation for `store` (verbatim):
```php
'student_id' => 'required|exists:students,id',
'items' => 'required|array|min:1',
'items.*.fee_head_id' => 'required|exists:fee_heads,id',
'items.*.amount' => 'required|numeric|min:0.01',
'fine_amount' => 'nullable|numeric|min:0',
'discount_amount' => 'nullable|numeric|min:0',
'payment_mode' => ['required', Rule::in(['Cash', 'UPI', 'Card', 'Bank Transfer', 'Cheque'])],
'remarks' => 'nullable|string|max:255',
```
Additionally, the controller throws a `ValidationException` on `student_id` ("No current academic session is configured — set one in Settings first.") if `AcademicSession::where('is_current', true)->first()` returns nothing — this is checked before the payment row is built.

Form Layout: Student search-and-select (typeahead over `/people/students`), then once selected: 4 stat cards (Total Fee / Discount / Paid / Due from the live `due` response), a checklist of the student's fee-structure breakdown (checkbox + editable amount per fee head), Fine Amount (number) and Payment Mode (select) side by side, Remarks (text), and a "Collect & Generate Receipt" button. On success a printable receipt modal opens.

Fields actually **submitted by the user** on this form vs **server-derived** on the resulting `fee_payments` row:

| Label | DB Column | Table | Type | Required | Validation | Default | Source | FK | Who sets it |
|---|---|---|---|---|---|---|---|---|---|
| Student | `student_id` | `fee_payments` | bigint FK | Required | `required\|exists:students,id` | — | typeahead search | `students.id` | User |
| Fee head line items | `items[].fee_head_id`, `items[].amount` | `fee_payments.items` (JSON) | array | Required | `items.*.fee_head_id: required\|exists:fee_heads,id`; `items.*.amount: required\|numeric\|min:0.01` | amounts pre-filled from the structure breakdown, editable | checkbox list from `/fee-management/students/{id}/due` | `fee_heads.id` (per item) | User (checks which heads, can edit amount) |
| Fine Amount | `fine_amount` | `fee_payments` | decimal(10,2) | Optional | `nullable\|numeric\|min:0` | `0` | free entry | — | User (free text — not derived from Fine Rules, see §10) |
| Payment Mode | `payment_mode` | `fee_payments` | enum | Required | `required\|in:Cash,UPI,Card,Bank Transfer,Cheque` | `Cash` | fixed select | — | User |
| Remarks | `remarks` | `fee_payments` | string, nullable | Optional | `nullable\|string\|max:255` | null | free text | — | User |
| Receipt No | `receipt_no` | `fee_payments` | string, unique | — | (not user input) | server-generated | — | — | **Server** — `FeePaymentController::nextReceiptNo()` |
| Academic Session | `academic_session_id` | `fee_payments` | bigint FK | — | (not user input) | current session | — | `academic_sessions.id` | **Server** — `AcademicSession::where('is_current', true)->first()` |
| Amount (total) | `amount` | `fee_payments` | decimal(10,2) | — | (not user input) | — | computed | — | **Server** — `items.sum('amount') + fine_amount` |
| Discount Amount | `discount_amount` | `fee_payments` | decimal(10,2) | — | validated but never sent by `PayFee.vue`'s payload | `0` | — | — | **Server default** — the create form does not include a discount field; discounts come from the separate `fee_discounts` table and only feed the `due` display, not this column |
| Payment Date | `payment_date` | `fee_payments` | date | — | (not user input) | — | `now()->toDateString()` | — | **Server** |
| Collected By | `collected_by_id` | `fee_payments` | bigint FK, nullable | — | (not user input) | — | — | `erp_users.id` | **Server** — `Auth::guard('erp')->id()` |
| Status | `status` | `fee_payments` | enum | — | (not settable at create) | `Paid` (DB default) | — | — | **Server/DB default** |

### 3.6 Fee Receipt / Fee Refund (`FeeReceipts.vue`)

- Frontend: `/fee-management/fee-receipt` and `/fee-management/fee-refund` (same component)
- Backend reads: `GET /erp/api/fee-management/payments`, optional `?student_id=` filter (`FeePaymentController::index()`, added to power People > Students' detail-view Fees tab — see below; this page's own list still fetches unfiltered and filters client-side)
- Backend write: `POST /erp/api/fee-management/payments/{payment}/refund`
- Controller: `App\Http\Controllers\Erp\FeeManagement\FeePaymentController` — `index`, `refund`

Validation for `refund` (verbatim):
```php
'amount' => ['required', 'numeric', 'min:0.01', 'max:' . ($payment->amount - $payment->refunded_amount)],
'reason' => 'required|string|max:255',
```
The `max:` bound is computed per-request from the target payment's remaining refundable balance (`amount - refunded_amount`) — not a static rule.

Form Layout: List page with receipt No/Student/Amount/Mode/Date/Status columns, a receipt viewer modal (print-styled), and a Refund SlideOver — Refund Amount ₹ (number, capped in the UI hint text) and Reason (text, required).

Fields (refund action only — no create form on this page):

| Label | DB Column | Table | Type | Required | Validation | Default | Source | FK |
|---|---|---|---|---|---|---|---|---|
| Refund Amount | `amount` (request field, not a column) | — | decimal | Required | `required\|numeric\|min:0.01\|max:<remaining balance>` | pre-filled to full remaining balance | free entry | — |
| Reason | `refund_reason` | `fee_payments` | string, nullable | Required (in request) | `required\|string\|max:255` | — | free text | — |

Server-derived on refund: `refunded_amount` (accumulates: `payment->refunded_amount + amount`), `refunded_at` (`now()`), `status` (set to `Refunded` if `refunded_amount >= amount`, else `Partially Refunded`).

### 3.7 Fee Due (`FeeDue.vue`)

- Frontend: `/fee-management/fee-due`
- Backend: `GET /erp/api/fee-management/due`
- Controller: `App\Http\Controllers\Erp\FeeManagement\FeeDueController` — `index`

No validation (read-only, computed report). Iterates every `Student` with `status = 'Active'`, running `FeeCalculator::forStudent()` per student and returning `{student_id, admission_no, name, school_class, section, total_fee, total_discount, total_paid, due}` rows. This is a pure computed listing — nothing is written or stored.

Form Layout: none (list + filters only). Filters: Search (name/admission no), Status (Due/Paid, evaluated client-side on `due > 0`). Stat cards: Students with Due, Fully Paid, Total Due, Total Collected. "Pay Now" link on rows with `due > 0` routes to `/fee-management/pay-fee` (does not pre-select the student — Pay Fee's own search must be used).

### 3.8 Fee Collection Report / Daily Collection / Online Payments (`FeeCollectionReport.vue`)

- Frontend: `/fee-management/fee-collection-report`, `/fee-management/daily-collection`, `/fee-management/online-payments` — one shared component, page title and default filters keyed off `route.path`
- Backend: `GET /erp/api/fee-management/payments` (same endpoint as Fee Receipt page; no server-side date/mode filtering — filtering is entirely client-side over the full payments list)
- Controller: `FeePaymentController::index`

No validation (read-only). Client-side filters: Search, From/To date, Payment Mode. Route-specific defaults set in `applyDefaults()`:
- `/fee-management/daily-collection` → `from`/`to` both default to today.
- `/fee-management/online-payments` → `mode` defaults to `UPI`, and rows with `payment_mode === 'Cash'` are excluded regardless of the mode filter.

Stat cards: Transactions (filtered count), Total Collected (`Σ(amount - refunded_amount)`), Average Receipt.

## 6. Database Relationships (Eloquent, as coded)

- `ErpFeeStructure belongsTo AcademicSession` (`academicSession()`), `belongsTo SchoolClass` (`schoolClass()`), `belongsTo FeeHead` (`feeHead()`).
- `FeeDiscount belongsTo Student` (`student()`), `belongsTo FeeHead` (`feeHead()`, nullable).
- `FeePayment belongsTo Student` (`student()`), `belongsTo AcademicSession` (`academicSession()`), `belongsTo ErpUser` (`collectedBy()`, FK `collected_by_id`).
- `FeeHead` and `FineRule` declare no relationships (plain lookup models) — `FeeHead` is referenced *from* `ErpFeeStructure`, `FeeDiscount`, and inside `FeePayment.items` (by ID, not a real FK/relation since `items` is JSON).
- No model in this module has an inverse `hasMany` back onto `Student` or `AcademicSession` — those are defined (or not) on the `Student`/`AcademicSession` models themselves, outside this module's scope.

## 7. Create Flow

**Fee Head / Fee Structure / Fee Discount / Fine Rule**: single-table inserts, no side effects — `Model::create($validated)`, nothing else touches other tables.

**Fee Payment** (`FeePaymentController::store`), in the order the controller executes it:
1. Validate the request (see §3.5).
2. Resolve the current academic session (`AcademicSession::where('is_current', true)->first()`); abort with a validation error if none exists.
3. Look up fee head names for the submitted `items[].fee_head_id` list (`FeeHead::whereIn(...)->pluck('name', 'id')`) and build an enriched `items` array of `{fee_head_id, fee_head_name, amount}` — the name is denormalized into the JSON at write time so a receipt still displays correctly even if the `FeeHead` row is later renamed or deleted.
4. Insert one row into `fee_payments` with: `receipt_no` (server-generated, see §10), `student_id`, `academic_session_id` (current session), `items` (JSON), `amount` (= Σ item amounts + fine_amount), `discount_amount` (from request, defaults 0 — not auto-populated from `fee_discounts`), `fine_amount` (from request, defaults 0), `payment_mode`, `payment_date` (`now()->toDateString()`), `remarks`, `collected_by_id` (authenticated user).
5. Return the payment with `student` and `collectedBy` eager-loaded.

No other table is touched — collecting a payment does not write to `fee_discounts`, does not decrement anything on `erp_fee_structures`, and does not create any ledger/journal/cash-book entry (Finance & Payroll's Cash Book is a separate module with its own tables — out of scope here, but worth knowing there is no automatic linkage).

**Meetings module note (n/a here)** — see meetings.md.

## 8. Edit Flow

- **Fee Head / Fee Structure / Fee Discount / Fine Rule**: `PUT` re-validates the full payload (same rules as create) and calls `$model->update($data)`. For Fee Structure, the uniqueness rule is re-checked with `->ignore($structure->id)` so a row can keep its own (session, class, head) combination.
- **Fee Payment**: there is **no** `PUT /payments/{payment}` route at all — a receipt's core fields (items, amount, mode, date, etc.) can never be edited once created. The only mutating action against an existing payment is **Refund** (`POST /payments/{payment}/refund`), which only ever adds to `refunded_amount` and sets `refund_reason`/`refunded_at`/`status` — it never changes `amount`, `items`, or any other original field.

## 9. Delete Flow

All models in this module use plain Eloquent `Model` (none use the `SoftDeletes` trait) — every delete below is a **hard delete**.

| Entity | Route | Hard/Soft | DB cascade behavior |
|---|---|---|---|
| Fee Head | `DELETE /fee-management/heads/{feeHead}` | Hard | `erp_fee_structures.fee_head_id` and `fee_discounts.fee_head_id` both `cascadeOnDelete` — deleting a fee head **deletes** every fee-structure row and every discount row that references it. |
| Fee Structure | `DELETE /fee-management/structures/{feeStructure}` | Hard | No children reference `erp_fee_structures`; safe standalone delete. |
| Fee Discount | `DELETE /fee-management/discounts/{feeDiscount}` | Hard | No children. |
| Fine Rule | `DELETE /fee-management/fine-rules/{fineRule}` | Hard | No children; also not referenced anywhere else in the codebase (see §10). |
| Fee Payment | **No delete route exists** | — | `routes/erp_api.php`'s `fee-management` group defines no `DELETE payments/{payment}` endpoint and `FeePaymentController` has no `destroy` method. A receipt, once issued, can only be adjusted via **Refund** — it can never be removed. `fee_payments.student_id` and `.academic_session_id` are declared `restrictOnDelete()` in the migration, so even a cascading delete of a `Student` or `AcademicSession` would be blocked by the DB while payment rows for them exist. |

## 10. Business Rules

- **Receipt numbering** (`FeePaymentController::nextReceiptNo()`): `sprintf('RCPT-%s-%04d', $year, $count)` where `$year = now()->format('Y')` and `$count` = (number of existing rows matching `receipt_no LIKE 'RCPT-{year}-%'`) + 1. Example: the 7th receipt of 2026 is `RCPT-2026-0007`. This is a count-based sequence, not a stored counter — concurrent submissions in the same request tick could theoretically race, though Laravel's single-threaded request handling per connection makes this low-risk in practice.
- **Live fee-balance computation** (`app/Services/FeeCalculator.php::forStudent()`), the core rule of the whole module:
  1. Resolve session: caller-supplied, or `AcademicSession::where('is_current', true)->first()`. If none exists, everything is zero.
  2. `total_fee` = sum of `erp_fee_structures.amount` for the student's `school_class_id` + the resolved session.
  3. `total_discount` = sum over the student's `fee_discounts` rows of: `value` if `type = 'fixed'`, else `round(total_fee * value / 100, 2)` if `type = 'percentage'` — then capped with `min(total_discount, total_fee)` so discounts can never exceed the fee itself.
  4. `total_paid` = sum of `amount - refunded_amount` across the student's `fee_payments` rows for that session (i.e., refunds net out of "paid").
  5. `due` = `max(0, round(total_fee - total_discount - total_paid, 2))` — floored at zero, never negative.
- **Fine Rules are not auto-applied.** `fine_rules` is a pure lookup/reference table maintained via its own CRUD screen; grepping the codebase shows `FineRule` is referenced only inside `FineRuleController` and `FineRule.php` itself — no other controller, service, or calculator reads from it. The `fine_amount` collected on a Fee Payment is a manually-typed number on the Pay Fee form; a school must apply its own fine-rule math by hand (or build that wiring later) — this is a known gap, not a bug in this documentation.
- **Discount application at collection time**: `fee_discounts` feed the *displayed* due breakdown (via `FeeCalculator`) so an operator sees the discounted amount to collect, but the `discount_amount` column on the resulting `fee_payments` row is only ever populated if the caller explicitly sends it — the shipped `PayFee.vue` form never does, so in practice every payment's `discount_amount` is `0` even when a discount was factored into the due total the operator saw.
- **One fee-structure row per (session, class, fee head)** — enforced by DB unique index `erp_fee_structures_unique` and by request validation (`Rule::unique(...)->where(...)`).
- **Refund cannot exceed remaining balance** — validated server-side with a dynamic `max:` rule computed from `payment->amount - payment->refunded_amount` at request time.
- **Refund status transition**: `Paid` → `Partially Refunded` (if `refunded_amount < amount` after the refund) → `Refunded` (if `refunded_amount >= amount`). Multiple partial refunds are allowed until the balance is exhausted.
- **`ErpFeeStructure` naming**: named with an `Erp` prefix (table `erp_fee_structures`) specifically because a `FeeStructure` model / `fee_structures` table already existed for the pre-existing public marketing site (`database/migrations/2026_03_11_000002_create_fee_structures_table.php` — columns like `class_name`, `registration_fee`, `admission_fee`, `monthly_tuition_fee`, used to render a public fee-schedule page, unrelated to the ERP ledger). The model file itself documents this: *"Named ErpFeeStructure (table: erp_fee_structures) to avoid colliding with the pre-existing public-website App\Models\FeeStructure / fee_structures table, which is an unrelated feature (marketing site fee display, not the ERP ledger)."*

## 11. Import & Export

Per `docs/README.md`'s Import & Export section: only Student has a real importer today. For Fee Management specifically:
- **Export**: real. `fee` is one of the entities in `ExportController::ENTITIES` (`student, fee, expense, salary, bank, fuel-log, route, marks, global`). `GET /erp/api/import-export/export/fee` streams an `.xlsx` workbook (built with PhpSpreadsheet, `Xlsx` writer) with columns `Receipt No, Student, Admission No, Amount, Discount, Fine, Mode, Date, Status`, sourced from `FeePayment::with('student:id,name,admission_no')->orderByDesc('payment_date')->get()`. It is also included as one sheet in the combined `global` multi-sheet export. Every export call logs a row to `import_export_logs` (`direction: Export, entity: fee, ...`). The output format is XLSX only — there is no separate CSV mode for this entity (the CSV/XLSX-capable reader, `SpreadsheetImportReader`, is used only for import, not export).
- **Import**: none. No import route or controller method exists for fee data; `StudentImportController` is the only real importer in the app.

## 12. Reports

- **Fee Reports** (Reports module, `/reports/fee-reports` → `FeeReports.vue`, gated by `reports.view`): `GET /erp/api/reports/fees` → `FeeReportController::index` → `App\Services\FeeReportCalculator::summary()`. Computed live: for the current session, iterates every `Student` with `status = 'Active'`, running `FeeCalculator::forStudent()` per student and aggregating `total_fee`, `total_discount`, `total_paid`, `total_due` (floored at 0), `collection_rate` (`total_paid / (total_fee - total_discount) * 100`, rounded to 1 decimal — 0 if `total_fee` is 0), `defaulters_count` (students with `due > 0`), `payment_mode_breakdown` (sum of `amount` grouped by `payment_mode` for the session's payments), and `collected_this_month` (sum of `amount` for payments whose `payment_date` matches the current `Y-m`).
- **Fee Collection Report / Daily Collection / Online Payments** (within this module, §3.8): not a separate calculator — reuses the plain `GET /fee-management/payments` list with client-side filtering/aggregation (Transactions count, Total Collected, Average Receipt).
- **Fee Due** (within this module, §3.7): also not a separate report entity — a first-class page in its own right, powered by `FeeDueController` + `FeeCalculator` directly (not `FeeReportCalculator`).

## 13. APIs

All under `/erp/api/fee-management/*` unless noted, `erp.auth` middleware applies to the whole group; write endpoints additionally require `erp.permission:fee.manage`.

| Method | Path | Controller@method | Auth |
|---|---|---|---|
| GET | `/fee-management/heads` | `FeeHeadController@index` | any erp user |
| POST | `/fee-management/heads` | `FeeHeadController@store` | `fee.manage` |
| PUT | `/fee-management/heads/{feeHead}` | `FeeHeadController@update` | `fee.manage` |
| DELETE | `/fee-management/heads/{feeHead}` | `FeeHeadController@destroy` | `fee.manage` |
| GET | `/fee-management/structures` | `ErpFeeStructureController@index` | any erp user |
| POST | `/fee-management/structures` | `ErpFeeStructureController@store` | `fee.manage` |
| PUT | `/fee-management/structures/{feeStructure}` | `ErpFeeStructureController@update` | `fee.manage` |
| DELETE | `/fee-management/structures/{feeStructure}` | `ErpFeeStructureController@destroy` | `fee.manage` |
| GET | `/fee-management/discounts` | `FeeDiscountController@index` | any erp user |
| POST | `/fee-management/discounts` | `FeeDiscountController@store` | `fee.manage` |
| PUT | `/fee-management/discounts/{feeDiscount}` | `FeeDiscountController@update` | `fee.manage` |
| DELETE | `/fee-management/discounts/{feeDiscount}` | `FeeDiscountController@destroy` | `fee.manage` |
| GET | `/fee-management/fine-rules` | `FineRuleController@index` | any erp user |
| POST | `/fee-management/fine-rules` | `FineRuleController@store` | `fee.manage` |
| PUT | `/fee-management/fine-rules/{fineRule}` | `FineRuleController@update` | `fee.manage` |
| DELETE | `/fee-management/fine-rules/{fineRule}` | `FineRuleController@destroy` | `fee.manage` |
| GET | `/fee-management/due` | `FeeDueController@index` | any erp user |
| GET | `/fee-management/payments` | `FeePaymentController@index` | any erp user — optional `?student_id=` filter |
| GET | `/fee-management/students/{student}/due` | `FeePaymentController@due` | any erp user |
| POST | `/fee-management/payments` | `FeePaymentController@store` | `fee.manage` |
| POST | `/fee-management/payments/{payment}/refund` | `FeePaymentController@refund` | `fee.manage` |
| GET | `/reports/fees` | `FeeReportController@index` | `reports.view` |
| GET | `/import-export/export/fee` | `ExportController@download` | any erp user |

Example — `POST /erp/api/fee-management/payments` request:
```json
{
  "student_id": 42,
  "items": [{ "fee_head_id": 3, "amount": 5000 }, { "fee_head_id": 4, "amount": 1200 }],
  "fine_amount": 0,
  "payment_mode": "Cash",
  "remarks": "Q2 tuition"
}
```
Response (201):
```json
{
  "id": 87,
  "receipt_no": "RCPT-2026-0007",
  "student_id": 42,
  "academic_session_id": 5,
  "items": [
    { "fee_head_id": 3, "fee_head_name": "Tuition Fee", "amount": 5000 },
    { "fee_head_id": 4, "fee_head_name": "Library Fee", "amount": 1200 }
  ],
  "amount": "6200.00",
  "discount_amount": "0.00",
  "fine_amount": "0.00",
  "payment_mode": "Cash",
  "payment_date": "2026-07-31",
  "remarks": "Q2 tuition",
  "status": "Paid",
  "refunded_amount": "0.00",
  "collected_by_id": 9,
  "student": { "id": 42, "name": "...", "admission_no": "..." },
  "collected_by": { "id": 9, "name": "..." }
}
```

Example — `GET /erp/api/fee-management/students/42/due` response (shape produced by `FeeCalculator::forStudent`):
```json
{
  "session": { "id": 5, "name": "2026-27" },
  "breakdown": [{ "fee_head_id": 3, "fee_head_name": "Tuition Fee", "amount": 5000, "frequency": "annual" }],
  "total_fee": 5000,
  "total_discount": 500,
  "total_paid": 0,
  "due": 4500
}
```

## 14. Database Tables

| Table | PK | Notable Columns | FKs | Unique / Indexes |
|---|---|---|---|---|
| `fee_heads` | `id` | `name`, `description` | — | — |
| `erp_fee_structures` | `id` | `amount` decimal(10,2), `frequency` enum | `academic_session_id → academic_sessions.id` (cascade), `school_class_id → school_classes.id` (cascade), `fee_head_id → fee_heads.id` (cascade) | `erp_fee_structures_unique` on `(academic_session_id, school_class_id, fee_head_id)` |
| `fee_discounts` | `id` | `type` enum(`percentage`,`fixed`) default `fixed`, `value` decimal(10,2), `reason` | `student_id → students.id` (cascade), `fee_head_id → fee_heads.id` nullable (cascade) | — |
| `fine_rules` | `id` | `type` enum(`per_day`,`fixed`) default `fixed`, `amount` decimal(10,2), `grace_days` unsigned int default 0 | — | — |
| `fee_payments` | `id` | `receipt_no` string, `items` JSON, `amount`/`discount_amount`/`fine_amount`/`refunded_amount` decimal(10,2), `payment_mode` enum, `payment_date` date, `status` enum(`Paid`,`Partially Refunded`,`Refunded`) default `Paid`, `refund_reason`, `refunded_at` datetime | `student_id → students.id` (restrict on delete), `academic_session_id → academic_sessions.id` (restrict on delete), `collected_by_id → erp_users.id` nullable (null on delete) | `receipt_no` unique |
| `fee_structures` (pre-existing public site table — **not** part of this ERP module) | `id` | `class_name`, `registration_fee`, `admission_fee`, `session_fee`, `total_fee_new_students`, `fee_old_students`, `monthly_tuition_fee`, `is_active`, `sort_order` | — | — |

## 15. File Storage

None. Nothing in this module uploads, stores, or serves a file — no receipt PDFs are generated/stored server-side (the "receipt" is a client-side print of the DOM via `window.print()`), and the XLSX export is streamed directly to the browser (`streamDownload`), never written to disk.

## 16. Background Jobs

None. No queued job, listener, or scheduled command exists for this module — `app/Jobs` does not exist in this codebase at all. Receipt numbering, fee calculations, and exports all run synchronously within the HTTP request.

## 17. Notifications

None. Neither `FeePaymentController` nor any other controller in this module calls `Mail::`, `Notification::`, `->notify(`, or dispatches a domain event on payment/refund — collecting a payment or issuing a refund does not email/SMS/notify the student, parent, or anyone else. The only "notification" a user sees is a client-side toast (`pushToast`) confirming their own action succeeded.

## 18. Module Dependencies

- **Depends on**: People/Student (`students` table — every discount, payment, and due row is keyed off a `Student`; the create-payment flow reads a student's `school_class_id` to find the matching fee structure), Academics/SchoolClass (`school_classes` — fee structure rows are per-class), Settings/Academic Sessions (`academic_sessions.is_current` selects the active session for payments and the due calculator).
- **Feeds**: Reports module (Fee Reports, `/reports/fees`, reuses `FeeCalculator` via `FeeReportCalculator`), Dashboard (if it surfaces fee figures, it would go through the same live calculators — no separate fee-specific dashboard code was found in this module's own files), Import & Export (`fee` export entity), People > Students' detail-view Fees tab (`StudentTabFees.vue`, see `people.md`) — fetches `GET /fee-management/students/{id}/due` (pre-existing endpoint, reused as-is) and the new `?student_id=`-filtered `GET /fee-management/payments` to show one student's real due breakdown and payment history.

## 19. Screenshots

Not available — generated from source code.

## 20. Final Deliverable

Fee Management is a small, tightly-scoped ledger module: 6 real backend tables/controllers (Fee Heads, Fee Structure, Fee Discounts, Fine Rules, Fee Payments, plus the read-only Fee Due report) behind 3 generic placeholder leaves (Fee Groups, Fee Settings, Tally Accounting) that have no backend today. Its defining trait is that a student's balance is never stored — `FeeCalculator::forStudent()` recomputes it from `erp_fee_structures`, `fee_discounts`, and `fee_payments` on every request, which is also what powers Fee Due and Fee Reports. `FeePayment.items` is a JSON array of `{fee_head_id, fee_head_name, amount}` line items rather than a normalized child table, matching the shape needed for receipt rendering and the XLSX export without adding a `fee_payment_items` table this app doesn't otherwise need. Receipt numbers (`RCPT-{year}-{0000}`) and the active academic session/collector are always server-derived, never user-supplied. Payments can be refunded (partially or fully, hard-capped at the remaining balance) but never edited or deleted once created; every other entity in the module is a plain hard-deletable CRUD row, with `fee_heads` cascading deletes into both `erp_fee_structures` and `fee_discounts`. Fine Rules is present as a configuration screen but is not yet wired into any fine calculation — fines are still typed by hand at collection time. No file storage, background jobs, or notifications exist anywhere in this module.
