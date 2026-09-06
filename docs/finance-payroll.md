# Finance & Payroll

See [README.md](README.md) for cross-cutting architecture (routing, permission model, generic-page mechanism, audit logging, morph-map, and the "computed, not stored" calculator-service pattern) — only module-specific detail is repeated here.

## 1. Module Overview

Finance & Payroll covers four real, database-backed sub-areas plus one live-computed report:

- **Expense tracking** — categorized office expenses with server-generated vouchers (`ExpenseCategory`, `Expense`).
- **Income tracking** — miscellaneous income entries with server-generated vouchers (`Income`).
- **Bank accounts & transactions** — manually-recorded deposits/withdrawals per account, with a live-computed running balance (`BankAccount`, `BankTransaction`).
- **Payroll** — a per-employee salary structure (`SalaryStructure`) that a bulk, idempotent "Generate" action turns into per-period salary slips (`SalarySlip`), which are then marked paid one at a time.
- **Cash Book** — not a stored table; a live-computed chronological ledger of all *cash*-mode income, expenses, and paid salaries, produced by `App\Services\CashBookCalculator`.

**There is no formal double-entry accounting engine anywhere in this application.** There is no `Ledger`, `Journal`, `Account`/`AccountHead`, `TrialBalance`, or `BalanceSheet` model, table, migration, or controller. This is exactly why the menu leaves **Ledger**, **Journal**, **Trial Balance**, **Profit & Loss**, and **Balance Sheet** all render `GenericModule.vue` (see README "Generic pages") instead of real screens — the underlying accounting engine simply does not exist in the codebase, not a temporarily-hidden feature. **Book Store**, **Book Expenses**, and **Cheque Register** are likewise generic: no controller, route, or table backs any of them.

Payroll only covers `teacher` and `staff` employee types (via the `Relation::enforceMorphMap()` entries for those two types registered in `AppServiceProvider::boot()` — see README "Polymorphic relationships"). There is no payroll support for `student`, `driver`, or any other person type.

## 2. Menu Structure

Menu group `Finance & Payroll` (`resources/js/erp/data/menu.js`), 17 children:

| Menu Label | Path | Status | Real backend / reason if generic |
|---|---|---|---|
| Income | `/finance-and-payroll/income` | **Real** | `IncomeController` |
| Office Expenses | `/finance-and-payroll/office-expenses` | **Real** | `ExpenseController` |
| Expense Categories | `/finance-and-payroll/expense-categories` | **Real** | `ExpenseCategoryController` |
| Salary Slips | `/finance-and-payroll/salary-slips` | **Real** | `SalarySlipController` |
| Salary Generate | `/finance-and-payroll/salary-generate` | **Real** | `SalaryStructureController` + `SalarySlipController::generate` |
| Salary Reports | `/finance-and-payroll/salary-reports` | **Real** | `SalaryReportController` |
| Book Store | — | Generic | No controller/route/table exists for this leaf |
| Book Expenses | — | Generic | No controller/route/table exists for this leaf |
| Bank Accounts | `/finance-and-payroll/bank-accounts` | **Real** | `BankAccountController` |
| Bank Transactions | `/finance-and-payroll/bank-transactions` | **Real** | `BankTransactionController` |
| Cash Book | `/finance-and-payroll/cash-book` | **Real** | `CashBookController` (live-computed, not a stored table) |
| Cheque Register | — | Generic | No controller/route/table exists for this leaf |
| Ledger | — | Generic | No double-entry accounting engine exists in this app |
| Journal | — | Generic | No double-entry accounting engine exists in this app |
| Trial Balance | — | Generic | No double-entry accounting engine exists in this app |
| Profit & Loss | — | Generic | No double-entry accounting engine exists in this app |
| Balance Sheet | — | Generic | No double-entry accounting engine exists in this app |

(Confirmed against `FLAGSHIP_ROUTES` in `resources/js/erp/router/index.js` — only the six paths above, plus `/finance-and-payroll/bank-transactions`, `/finance-and-payroll/bank-accounts`, `/finance-and-payroll/cash-book`, are mapped to real Vue components; every other child in the group falls through to `GenericModule.vue`.)

---

## 3–9. Pages

Backend routes all live under `Route::prefix('finance-payroll')->name('finance-payroll.')` in `routes/erp_api.php` (lines 330–368). Reads are open to any authenticated `erp` user; every write route is wrapped in `Route::middleware('erp.permission:finance.manage')`. The permission key `finance.manage` is registered in `resources/js/erp/pages/settings/Roles.vue`'s `availablePermissions` with label "Finance & Payroll".

### 3.1 Expense Categories

- **Frontend**: `/finance-and-payroll/expense-categories` → `resources/js/erp/pages/finance-payroll/ExpenseCategories.vue`
- **Backend**: `GET|POST /erp/api/finance-payroll/expense-categories`, `PUT|DELETE /erp/api/finance-payroll/expense-categories/{expenseCategory}`
- **Controller**: `App\Http\Controllers\Erp\FinancePayroll\ExpenseCategoryController` (`index`, `store`, `update`, `destroy`)
- **Permissions**: index open to any authenticated user; store/update/destroy require `finance.manage`.

**Validation — store**:
```
name        => required|string|max:255|unique:expense_categories,name
description => nullable|string|max:255
```
**Validation — update**: identical, but `name` uniqueness ignores the current row (`Rule::unique('expense_categories', 'name')->ignore($expenseCategory->id)`).

**Form Layout**: single-column slide-over — Name (text, required), Description (text, optional).

**Fields**:

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Name | `name` | `expense_categories` | `string` | Required | `required\|string\|max:255\|unique` | — | — | — |
| Description | `description` | `expense_categories` | `string`, nullable | Optional | `nullable\|string\|max:255` | `null` | — | — |

**Database Relationships**: none declared on `ExpenseCategory` itself; `Expense::expenseCategory()` is a `belongsTo(ExpenseCategory::class)`, and `expenses.expense_category_id` has `restrictOnDelete()` (see 3.2).

**Create Flow**: single insert into `expense_categories`. No side effects.

**Edit Flow**: single update on the row; name-uniqueness check excludes itself.

**Delete Flow**: hard delete (`$expenseCategory->delete()`, no `SoftDeletes` trait on the model, no `deleted_at` column in the migration). Because `expenses.expense_category_id` is `restrictOnDelete()`, deleting a category that still has expense rows referencing it fails at the database level with a foreign-key-constraint error (surfaces as a generic 500 — the controller does no pre-check).

### 3.2 Office Expenses

- **Frontend**: `/finance-and-payroll/office-expenses` → `resources/js/erp/pages/finance-payroll/Expenses.vue`
- **Backend**: `GET|POST /erp/api/finance-payroll/expenses`, `PUT|DELETE /erp/api/finance-payroll/expenses/{expense}`
- **Controller**: `App\Http\Controllers\Erp\FinancePayroll\ExpenseController`
- **Permissions**: index open; store/update/destroy require `finance.manage`.

**Validation — store & update** (identical rule set):
```
expense_category_id => required|exists:expense_categories,id
title                => required|string|max:255
amount               => required|numeric|min:0.01
date                 => required|date
payment_mode         => required|in:Cash,Bank,UPI,Cheque
bank_account_id      => nullable|exists:bank_accounts,id
remarks              => nullable|string|max:255
```
`voucher_no` and `paid_by_id` are **not** client-suppliable — see Business Rules (3.2.1).

**Form Layout**: slide-over — Category (select), Title (text), two-column row (Amount number / Date), two-column row (Payment Mode select / Bank Account select — Bank Account only shown when mode is Bank or Cheque), Remarks (text).

**Fields**:

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Category | `expense_category_id` | `expenses` | `unsignedBigInteger` | Required | `required\|exists:expense_categories,id` | — | `GET /finance-payroll/expense-categories` | `expense_categories.id`, `restrictOnDelete` |
| Title | `title` | `expenses` | `string` | Required | `required\|string\|max:255` | — | — | — |
| Amount | `amount` | `expenses` | `decimal(10,2)` | Required | `required\|numeric\|min:0.01` | — | — | — |
| Date | `date` | `expenses` | `date` | Required | `required\|date` | — | — | — |
| Payment Mode | `payment_mode` | `expenses` | `enum(Cash,Bank,UPI,Cheque)` | Required | `required\|in:Cash,Bank,UPI,Cheque` | `Cash` | fixed 4-option select | — |
| Bank Account | `bank_account_id` | `expenses` | `unsignedBigInteger`, nullable | Optional | `nullable\|exists:bank_accounts,id` | `null` | `GET /finance-payroll/bank-accounts` | `bank_accounts.id`, `nullOnDelete` |
| Remarks | `remarks` | `expenses` | `string`, nullable | Optional | `nullable\|string\|max:255` | `null` | — | — |
| Voucher No *(server-derived)* | `voucher_no` | `expenses` | `string`, unique | n/a — not user input | — | see 10.1 | — | — |
| Paid By *(server-derived)* | `paid_by_id` | `expenses` | `unsignedBigInteger`, nullable | n/a — not user input | — | `Auth::guard('erp')->id()` | — | `erp_users.id`, `nullOnDelete` |

**Database Relationships** (`app/Models/Expense.php`): `expenseCategory()` → `belongsTo(ExpenseCategory::class)`; `bankAccount()` → `belongsTo(BankAccount::class)`; `paidBy()` → `belongsTo(ErpUser::class, 'paid_by_id')`.

**Create Flow**: `Expense::create()` with the validated fields plus server-derived `voucher_no` (3.2.1) and `paid_by_id` (current `erp` user). Single-table insert, no side effects on other tables (no stock/cash-book row is written — Cash Book is computed live from this table at read time, see §12).

**Edit Flow**: `Expense::update()` with the same 7 validated fields. `voucher_no` and `paid_by_id` are immutable after creation (never re-derived, never accepted from the request body since they aren't in the validated array).

**Delete Flow**: hard delete, no cascade — `expense_category_id` is `restrictOnDelete` (irrelevant on Expense's own delete) and `bank_account_id`/`paid_by_id` are `nullOnDelete` (only relevant if the *parent* — category/bank account/user — is deleted, not when the expense itself is deleted).

### 3.3 Income

- **Frontend**: `/finance-and-payroll/income` → `resources/js/erp/pages/finance-payroll/Income.vue`
- **Backend**: `GET|POST /erp/api/finance-payroll/incomes`, `PUT|DELETE /erp/api/finance-payroll/incomes/{income}`
- **Controller**: `App\Http\Controllers\Erp\FinancePayroll\IncomeController`
- **Permissions**: index open; store/update/destroy require `finance.manage`.

**Validation — store & update**:
```
source          => required|string|max:255
amount          => required|numeric|min:0.01
date            => required|date
payment_mode    => required|in:Cash,Bank,UPI,Cheque
bank_account_id => nullable|exists:bank_accounts,id
remarks         => nullable|string|max:255
```
`voucher_no` and `received_by_id` are server-derived, not client-suppliable.

**Form Layout**: slide-over — Source (text, placeholder "e.g. Donation, Rent, Other Fee"), two-column (Amount / Date), two-column (Payment Mode select / Bank Account select — shown for Bank/Cheque), Remarks.

**Fields**:

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Source | `source` | `incomes` | `string` | Required | `required\|string\|max:255` | — | — | — |
| Amount | `amount` | `incomes` | `decimal(10,2)` | Required | `required\|numeric\|min:0.01` | — | — | — |
| Date | `date` | `incomes` | `date` | Required | `required\|date` | — | — | — |
| Payment Mode | `payment_mode` | `incomes` | `enum(Cash,Bank,UPI,Cheque)` | Required | `required\|in:...` | `Cash` | fixed 4-option select | — |
| Bank Account | `bank_account_id` | `incomes` | `unsignedBigInteger`, nullable | Optional | `nullable\|exists:bank_accounts,id` | `null` | `GET /finance-payroll/bank-accounts` | `bank_accounts.id`, `nullOnDelete` |
| Remarks | `remarks` | `incomes` | `string`, nullable | Optional | `nullable\|string\|max:255` | `null` | — | — |
| Voucher No *(server-derived)* | `voucher_no` | `incomes` | `string`, unique | n/a | — | see 10.1 | — | — |
| Received By *(server-derived)* | `received_by_id` | `incomes` | `unsignedBigInteger`, nullable | n/a | — | `Auth::guard('erp')->id()` | — | `erp_users.id`, `nullOnDelete` |

**Database Relationships** (`app/Models/Income.php`): `bankAccount()` → `belongsTo(BankAccount::class)`; `receivedBy()` → `belongsTo(ErpUser::class, 'received_by_id')`.

**Create/Edit/Delete Flow**: mirror Expenses exactly (single-table insert/update; hard delete, no cascades from `incomes` itself).

### 3.4 Bank Accounts

- **Frontend**: `/finance-and-payroll/bank-accounts` → `resources/js/erp/pages/finance-payroll/BankAccounts.vue`
- **Backend**: `GET|POST /erp/api/finance-payroll/bank-accounts`, `PUT|DELETE /erp/api/finance-payroll/bank-accounts/{bankAccount}`
- **Controller**: `App\Http\Controllers\Erp\FinancePayroll\BankAccountController`
- **Permissions**: index open; store/update/destroy require `finance.manage`.

**Validation — store**:
```
account_name   => required|string|max:255
bank_name      => required|string|max:255
account_number => required|string|max:50|unique:bank_accounts,account_number
ifsc_code      => nullable|string|max:20
branch         => nullable|string|max:255
opening_balance=> nullable|numeric|min:0
```
**Validation — update**: identical, `account_number` uniqueness ignores the current row.

Note: the Vue form disables the "Opening Balance" input when editing an existing account (UI-level convenience only), but the backend `update()` rule set still accepts and persists `opening_balance` if sent — there is no server-side lock on this field.

**Form Layout**: card grid (one card per account) + slide-over form — Account Name, Bank Name, two-column (Account Number / IFSC Code), two-column (Branch / Opening Balance).

**Fields**:

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Account Name | `account_name` | `bank_accounts` | `string` | Required | `required\|string\|max:255` | — | — | — |
| Bank Name | `bank_name` | `bank_accounts` | `string` | Required | `required\|string\|max:255` | — | — | — |
| Account Number | `account_number` | `bank_accounts` | `string`, unique | Required | `required\|string\|max:50\|unique` | — | — | — |
| IFSC Code | `ifsc_code` | `bank_accounts` | `string`, nullable | Optional | `nullable\|string\|max:20` | `null` | — | — |
| Branch | `branch` | `bank_accounts` | `string`, nullable | Optional | `nullable\|string\|max:255` | `null` | — | — |
| Opening Balance | `opening_balance` | `bank_accounts` | `decimal(12,2)` | Optional | `nullable\|numeric\|min:0` | `0` | — | — |
| Current Balance *(not a column — computed)* | n/a | n/a | float | n/a | n/a | n/a | n/a | n/a |

**Database Relationships** (`app/Models/BankAccount.php`): `transactions()` → `hasMany(BankTransaction::class)`. `currentBalance()` is a model method, **not** a stored/cast attribute: `opening_balance + Σdeposits - Σwithdrawals + Σincomes-against-this-account - Σexpenses-against-this-account`, each summed live via query on `bank_transactions`, `incomes`, and `expenses`. `BankAccountController::index()` appends `current_balance` to every row in the JSON response by calling this method per account — it is never persisted.

**Create Flow**: single insert into `bank_accounts`.

**Edit Flow**: single update; see note above re: `opening_balance` being technically editable server-side despite the UI disabling the field.

**Delete Flow**: hard delete. `bank_transactions.bank_account_id` is `cascadeOnDelete()` — deleting a bank account **deletes all of its transactions** at the database level. `expenses.bank_account_id` and `incomes.bank_account_id` are `nullOnDelete()` — any expense/income that referenced the deleted account has its `bank_account_id` set to `NULL` (the expense/income row itself survives).

### 3.5 Bank Transactions

- **Frontend**: `/finance-and-payroll/bank-transactions` → `resources/js/erp/pages/finance-payroll/BankTransactions.vue` (also reachable with `?account={id}` query from the "View Transactions" link on a Bank Accounts card)
- **Backend**: `GET /erp/api/finance-payroll/bank-transactions` (requires `bank_account_id` query param), `POST /erp/api/finance-payroll/bank-transactions`, `PUT /erp/api/finance-payroll/bank-transactions/{bankTransaction}`, `DELETE /erp/api/finance-payroll/bank-transactions/{bankTransaction}`
- **Controller**: `App\Http\Controllers\Erp\FinancePayroll\BankTransactionController`
- **Permissions**: index open; store/update/destroy require `finance.manage`.
- **A previously-known limitation has been fixed**: Bank Transactions used to have create-and-delete only (no `PUT`/update route), meaning a recorded transaction could only be deleted and re-entered. An `update()` method and `PUT` route were added in a later revision — validation is identical to `store` below, and since `BankAccount::currentBalance()` is always computed live from the current `bank_transactions` rows (§3.4), correcting an existing transaction's amount/type here automatically reflects in the account balance on the next read, with no separate balance-adjustment step needed.

**Validation — index**: `bank_account_id => required|exists:bank_accounts,id` (query param, not body).

**Validation — store and update** (identical):
```
bank_account_id => required|exists:bank_accounts,id
type            => required|in:Deposit,Withdrawal
amount          => required|numeric|min:0.01
date            => required|date
reference_no    => nullable|string|max:100
remarks         => nullable|string|max:255
```

**Form Layout**: account picker (top-level select, drives which transactions load), then a slide-over — title "Add Transaction"/"Edit Transaction" on an `editing` ref — Type (select: Deposit/Withdrawal), two-column (Amount / Date), Reference No., Remarks. An Edit (✏️) row action now sits next to Delete.

**Fields**:

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Bank Account | `bank_account_id` | `bank_transactions` | `unsignedBigInteger` | Required | `required\|exists:bank_accounts,id` | — | `GET /finance-payroll/bank-accounts` | `bank_accounts.id`, `cascadeOnDelete` |
| Type | `type` | `bank_transactions` | `enum(Deposit,Withdrawal)` | Required | `required\|in:Deposit,Withdrawal` | `Deposit` | fixed 2-option select | — |
| Amount | `amount` | `bank_transactions` | `decimal(10,2)` | Required | `required\|numeric\|min:0.01` | — | — | — |
| Date | `date` | `bank_transactions` | `date` | Required | `required\|date` | — | — | — |
| Reference No. | `reference_no` | `bank_transactions` | `string`, nullable | Optional | `nullable\|string\|max:100` | `null` | — | — |
| Remarks | `remarks` | `bank_transactions` | `string`, nullable | Optional | `nullable\|string\|max:255` | `null` | — | — |

**Database Relationships** (`app/Models/BankTransaction.php`): `bankAccount()` → `belongsTo(BankAccount::class)`.

**Create Flow**: single insert into `bank_transactions`. No stored balance is updated anywhere — `BankAccount::currentBalance()` recomputes live on the next read (see 3.4).

**Edit Flow**: **not available** — see limitation noted above.

**Delete Flow**: hard delete, no children to cascade.

### 3.6 Salary Generate (Salary Structures)

- **Frontend**: `/finance-and-payroll/salary-generate` → `resources/js/erp/pages/finance-payroll/SalaryGenerate.vue`
- **Backend**: `GET /erp/api/finance-payroll/salary-structures` (list every teacher + staff merged with their structure, if any), `POST /erp/api/finance-payroll/salary-structures` (create-or-update one structure), `POST /erp/api/finance-payroll/salary-slips/generate` (bulk-generate slips for a period)
- **Controller**: `App\Http\Controllers\Erp\FinancePayroll\SalaryStructureController` (`index`, `store`) and `App\Http\Controllers\Erp\FinancePayroll\SalarySlipController::generate`
- **Permissions**: index open; both `store` and `generate` require `finance.manage`.

`SalaryStructureController` uses the `App\Http\Controllers\Erp\FinancePayroll\Concerns\ResolvesPayrollEmployee` trait, which maps `'teacher' => Teacher::class` / `'staff' => Staff::class` and is used to 404 (`NotFoundHttpException`) on any other `employee_type` before the structure is written.

**Validation — `POST salary-structures`**:
```
employee_type => required|in:teacher,staff
employee_id   => required|integer
basic_salary  => required|numeric|min:0
allowances    => nullable|numeric|min:0
deductions    => nullable|numeric|min:0
```
The controller additionally checks the resolved employee model (`Teacher`/`Staff`) exists by id, aborting `404` if not — this is a manual existence check, not a Laravel `exists:` validation rule, because the target table depends on `employee_type`.

**Validation — `POST salary-slips/generate`**:
```
period => required|string|regex:/^\d{4}-\d{2}$/
```

**Form Layout**: `index` shows one row per teacher/staff member (union of both, teachers first — `Teacher::orderBy('name')` then `Staff::orderBy('name')`), each showing basic/allowances/deductions/net (net computed client-side as `basic + allowances - deductions`) and a "Set Structure"/"Edit" button opening a slide-over with 3 numeric fields. A separate "Period" month-picker + "Generate Slips" button triggers the bulk generation.

**Fields (Salary Structure)**:

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Employee Type | `employee_type` | `salary_structures` | `enum(teacher,staff)` | Required | `required\|in:teacher,staff` | — | implicit (page section, not a real dropdown) | polymorphic, morph-mapped |
| Employee ID | `employee_id` | `salary_structures` | `unsignedBigInteger` | Required | `required\|integer` + manual existence check | — | implicit (row context) | `teachers.id` or `staff.id` depending on type |
| Basic Salary | `basic_salary` | `salary_structures` | `decimal(10,2)` | Required | `required\|numeric\|min:0` | — | — | — |
| Allowances | `allowances` | `salary_structures` | `decimal(10,2)` | Optional | `nullable\|numeric\|min:0` | `0` | — | — |
| Deductions | `deductions` | `salary_structures` | `decimal(10,2)` | Optional | `nullable\|numeric\|min:0` | `0` | — | — |

**Fields (Generate action)**:

| Label | Sent as | Required | Validation | Default |
|---|---|---|---|---|
| Period | `period` | Required | `required\|string\|regex:/^\d{4}-\d{2}$/` | current month, `YYYY-MM` |

**Database Relationships** (`app/Models/SalaryStructure.php`): `employee()` → `morphTo()`, resolved through the global morph map (`teacher`/`staff`) registered in `AppServiceProvider::boot()`.

**Create/Edit Flow — Salary Structure**: `SalaryStructure::updateOrCreate(['employee_type' => ..., 'employee_id' => ...], [...])` — there is **no separate update route**; "Set Structure" and "Edit" in the UI both call the same `POST salary-structures` endpoint, which upserts on the unique `(employee_type, employee_id)` key (`salary_structures` migration: `$table->unique(['employee_type', 'employee_id'])`). This is the intended pattern, not a workaround.

**Create Flow — Generate**: see §10.2 (business rule — idempotent bulk insert into `salary_slips`).

**Delete Flow**: **no delete route exists for `SalaryStructure` or the generate action** — a structure can only be overwritten (basic/allowances/deductions set to 0 as a practical "clear", but the row itself is never removed via the UI/API).

### 3.7 Salary Slips

- **Frontend**: `/finance-and-payroll/salary-slips` → `resources/js/erp/pages/finance-payroll/SalarySlips.vue`
- **Backend**: `GET /erp/api/finance-payroll/salary-slips` (optional `period`, `employee_type` filters), `PATCH /erp/api/finance-payroll/salary-slips/{salarySlip}/pay`
- **Controller**: `App\Http\Controllers\Erp\FinancePayroll\SalarySlipController` (`index`, `markPaid`)
- **Permissions**: index open; `markPaid` requires `finance.manage`.
- **IMPORTANT**: `SalarySlip` has **no plain `store()` endpoint**. Slips are never created one at a time through this page or any form — they only come into existence via the bulk `generate()` action on the Salary Generate page (§3.6), and are only ever mutated via `markPaid()`. There is no route to edit a slip's amounts after generation, and no delete route for slips at all.

**Validation — `PATCH salary-slips/{id}/pay`**:
```
payment_mode => required|in:Cash,Bank
paid_on      => nullable|date
```

**Form Layout**: filter bar (Period month-picker, Employee Type select), stat cards (Slips / Paid / Pending / Total Net), table of slips with a "Mark Paid" button shown only when `status === 'Pending'`, opening a 2-field slide-over (Payment Mode, Paid On).

**Fields (read-only list, from `salary_slips` + resolved `employee`)**:

| Label | DB Column | Table | Data Type | Notes |
|---|---|---|---|---|
| Employee | n/a (via `employee` morph relation) | — | — | `employee_name`/`employee_code` appended in the controller from `$slip->employee->name`/`employee_id` |
| Type | `employee_type` | `salary_slips` | `enum(teacher,staff)` | — |
| Period | `period` | `salary_slips` | `char(7)` | `'YYYY-MM'` |
| Basic | `basic_salary` | `salary_slips` | `decimal(10,2)` | snapshotted from the structure at generation time |
| Allowances | `allowances` | `salary_slips` | `decimal(10,2)` | snapshotted |
| Deductions | `deductions` | `salary_slips` | `decimal(10,2)` | snapshotted |
| Net Salary | `net_salary` | `salary_slips` | `decimal(10,2)` | computed once at generation: `basic + allowances - deductions` |
| Status | `status` | `salary_slips` | `enum(Pending,Paid)` | default `Pending` |

**Fields (Mark Paid form)**:

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Payment Mode | `payment_mode` | `salary_slips` | `enum(Cash,Bank)`, nullable | Required (on pay) | `required\|in:Cash,Bank` | `Bank` (UI default) | fixed 2-option select | — |
| Paid On | `paid_on` | `salary_slips` | `date`, nullable | Optional | `nullable\|date` | today's date if omitted | — | — |

**Database Relationships** (`app/Models/SalarySlip.php`): `employee()` → `morphTo()` (morph-mapped `teacher`/`staff`); `generatedBy()` → `belongsTo(ErpUser::class, 'generated_by_id')`.

**Create Flow**: not applicable to this page — see §3.6/§10.2 for `generate()`.

**Edit Flow (`markPaid`)**: `$salarySlip->update(['status' => 'Paid', 'payment_mode' => ..., 'paid_on' => ...])`. This is the *only* mutation path for an existing slip. Once `Paid`, the UI hides the "Mark Paid" button (there's no "un-pay" action, and the backend has no route for it either).

**Delete Flow**: **no delete route exists for `SalarySlip`.** A generated slip is permanent — the only lifecycle state changes are `Pending → Paid`.

### 3.8 Salary Reports

- **Frontend**: `/finance-and-payroll/salary-reports` → `resources/js/erp/pages/finance-payroll/SalaryReports.vue` (the Reports module's own `/reports/salary-reports` leaf is mapped to the **same** `SalaryReports.vue` component in `FLAGSHIP_ROUTES`)
- **Backend**: `GET /erp/api/finance-payroll/salary-reports` (query: `period`, defaults to current month)
- **Controller**: `App\Http\Controllers\Erp\FinancePayroll\SalaryReportController::index`
- **Permissions**: open to any authenticated user (this route is **not** under the `finance.manage` write-gate — it's a `GET` under the open section of the `finance-payroll` prefix).

Read-only, computed page — no form, no create/edit/delete. It aggregates `SalarySlip` rows for the requested `period`: `total_employees`, `total_basic`, `total_allowances`, `total_deductions`, `total_net`, `paid_count`, `pending_count`, a `by_type` breakdown (teacher/staff counts and net totals), and a flat `rows` array (employee name/type/net/status). Nothing here is stored — it's recomputed from `salary_slips` on every request.

### 3.9 Cash Book

- **Frontend**: `/finance-and-payroll/cash-book` → `resources/js/erp/pages/finance-payroll/CashBook.vue`
- **Backend**: `GET /erp/api/finance-payroll/cash-book` (query: `from`, `to`, both optional dates)
- **Controller**: `App\Http\Controllers\Erp\FinancePayroll\CashBookController::index`, delegating entirely to `App\Services\CashBookCalculator::forRange()`
- **Permissions**: open to any authenticated user.

**Validation**: `from => nullable|date`, `to => nullable|date`. Defaults: `from` = start of current month, `to` = today, both computed server-side if omitted.

Read-only computed report — see §12 for the full calculation logic. No form, no create/edit/delete; not a stored table.

---

## 10. Business Rules

### 10.1 Voucher numbering (server-generated, not user-suppliable)

Both `Expense` and `Income` auto-generate a `voucher_no` in their controllers — the field is **not** in the validated request array and cannot be set or overridden by the client:

- **Expense** (`ExpenseController::nextVoucherNo()`): `sprintf('EXP-%s-%04d', $year, $count)` where `$year = now()->format('Y')` and `$count = Expense::where('voucher_no', 'like', "EXP-{$year}-%")->count() + 1`. Example: `EXP-2026-0001`.
- **Income** (`IncomeController::nextVoucherNo()`): identical pattern with prefix `INC-`. Example: `INC-2026-0001`.

Both are simple `COUNT(...) + 1` per calendar year — not a database sequence/lock, so under concurrent requests within the same year there is a theoretical (if narrow) race for the same voucher number; no transaction or `lockForUpdate()` guards it.

Purchases follow the same pattern with prefix `PO-` (see `inventory.md` §10.1 for the identical mechanism in `PurchaseController::nextVoucherNo()`).

### 10.2 Salary Slip generate/markPaid lifecycle

1. **Generate** (`SalarySlipController::generate`, `POST salary-slips/generate`, `finance.manage`): takes a `period` (`YYYY-MM`). Loads **every** `SalaryStructure` row, and every `SalarySlip` already existing for that period. For each structure whose `(employee_type, employee_id)` doesn't already have a slip for that period, it inserts a new `SalarySlip` with `status = 'Pending'`, snapshotting `basic_salary`/`allowances`/`deductions` from the structure at that moment and computing `net_salary = basic + allowances - deductions`. **Idempotent by design**: re-running `generate` for the same period only creates slips for employees who don't have one yet (skips existing via the `$existing->contains(...)` check) — it never duplicates or overwrites a slip. The response reports `generated` (count of newly-created slips), not the total slip count for the period.
2. **Pay** (`SalarySlipController::markPaid`, `PATCH salary-slips/{id}/pay`, `finance.manage`): the only way a `Pending` slip becomes `Paid`. Sets `status = 'Paid'`, `payment_mode` (Cash/Bank), and `paid_on` (defaults to today if not supplied). One-way — there is no unpay/reverse route.
3. Slips are **never edited directly** and **never deleted** — the structure (§3.6) is the only editable input; regenerating doesn't touch existing slips for that period.
4. The `salary_slips` table enforces `unique(['employee_type', 'employee_id', 'period'])` at the database level, which is the actual backstop making step 1 safe even if the idempotency check in application code were ever bypassed.

### 10.3 Cash Book is entirely computed, never stored

`CashBookCalculator::forRange($from, $to)` (`app/Services/CashBookCalculator.php`) builds its ledger from three source tables, filtered to `payment_mode = 'Cash'` (Income, Expense) or `payment_mode = 'Cash'` (SalarySlip, only rows with `status = 'Paid'`):
- Every `Income` with `payment_mode = Cash` in range → an "in" row.
- Every `Expense` with `payment_mode = Cash` in range → an "out" row.
- Every `SalarySlip` with `payment_mode = Cash` and `status = Paid`, filtered by `paid_on` in range → an "out" row (particulars formatted as `"Salary — {Type} #{employee_id} ({period})"`).

All three collections are merged, sorted by date, and a running balance is accumulated top-to-bottom. `total_in`, `total_out`, and `closing_balance` are derived sums — nothing is persisted; every request recomputes from scratch (see README "Computed, not stored" calculator services).

### 10.4 Bank account balance is entirely computed, never stored

`BankAccount::currentBalance()` — no `balance` column exists on `bank_accounts`. It sums `bank_transactions` (Deposit − Withdrawal) plus `incomes`/`expenses` rows that reference that `bank_account_id`, added to `opening_balance`, on every call. `BankAccountController::index()` calls this once per account per request.

---

## 11. Import & Export

Per README §"Import & Export": only Student has a real *importer*; Export covers 9 entities including three from this module.

| Page | Import | Export |
|---|---|---|
| Expense Categories | None | None |
| Office Expenses | None | **Yes** — `GET /erp/api/import-export/export/expense` → `ExportController::buildSection('expense')`. Columns: Voucher No, Category, Title, Amount, Date, Mode. XLSX only (via PhpSpreadsheet `Xlsx` writer), streamed as `expense-export-{Ymd-His}.xlsx`. |
| Income | None | None — Income is **not** one of the 9 exportable entities (`ExportController::ENTITIES` lists `student, fee, expense, salary, bank, fuel-log, route, marks, global` — `income` is absent). |
| Bank Accounts | None | None directly (Bank Accounts themselves aren't exported; see Bank Transactions below) |
| Bank Transactions | None | **Yes** — entity key `bank`. Columns: Account, Type, Amount, Date, Reference No. |
| Salary Generate / Slips | None | **Yes** — entity key `salary`. Columns: Employee Type, Employee, Period, Basic, Allowances, Deductions, Net Salary, Status. |
| Salary Reports | None | None (the export covers raw `salary_slips`, not the aggregated report) |
| Cash Book | None | None — the computed cash book itself has no dedicated export; only the underlying `expense`/`salary`/`bank` exports exist |

All exports are reachable from the Import & Export module's "Expense Export" / "Salary Export" / "Bank Export" menu leaves (which call the same generic `GET export/{entity}` endpoint), and are also included as extra sheets in the "Global Workbook Export" (`entity = global`, which loops every entity except itself). Export reads are open to any authenticated user (not gated by `finance.manage`). Every export writes one row to `import_export_logs` (`direction = Export`).

---

## 12. Reports

- **Salary Reports** (§3.8) — the module's own dedicated report page, and the identical component is reused verbatim at `/reports/salary-reports` in the Reports module.
- **Reports module → Finance Reports** (`/reports/finance-reports`, `FinanceReportController::index`, gated by `reports.view`) reuses this module's raw tables via `App\Services\FinanceReportCalculator::summary()`: `total_income`, `total_expense`, `net_balance` (all-time sums across `incomes`/`expenses`), `income_this_month`, `expense_this_month`, `total_bank_balance` (sum of every `BankAccount::currentBalance()`), and `top_expense_categories` (top 5 categories by summed expense amount, grouped in PHP via `Expense::with('expenseCategory')->groupBy(...)`).
- **Cash Book** (§3.9, §10.3) is itself a lightweight computed report, not a data-entry page — no create/edit/delete exists for it.
- There is no dedicated "Bank Reports" or "Expense Reports" page — bank/expense figures only surface through Finance Reports, Cash Book, and the raw list pages' own stat cards (computed client-side in the Vue components, e.g. `totalAmount`/`thisMonthAmount` in `Expenses.vue`/`Income.vue`).

---

## 13. APIs

Base path for this module: `/erp/api/finance-payroll/*`. All require the `erp.auth` session guard; writes additionally require `erp.permission:finance.manage`.

| Method | Path | Controller@method | Permission |
|---|---|---|---|
| GET | `expense-categories` | `ExpenseCategoryController@index` | any authenticated user |
| POST | `expense-categories` | `ExpenseCategoryController@store` | `finance.manage` |
| PUT | `expense-categories/{expenseCategory}` | `ExpenseCategoryController@update` | `finance.manage` |
| DELETE | `expense-categories/{expenseCategory}` | `ExpenseCategoryController@destroy` | `finance.manage` |
| GET | `expenses` | `ExpenseController@index` | any authenticated user |
| POST | `expenses` | `ExpenseController@store` | `finance.manage` |
| PUT | `expenses/{expense}` | `ExpenseController@update` | `finance.manage` |
| DELETE | `expenses/{expense}` | `ExpenseController@destroy` | `finance.manage` |
| GET | `incomes` | `IncomeController@index` | any authenticated user |
| POST | `incomes` | `IncomeController@store` | `finance.manage` |
| PUT | `incomes/{income}` | `IncomeController@update` | `finance.manage` |
| DELETE | `incomes/{income}` | `IncomeController@destroy` | `finance.manage` |
| GET | `bank-accounts` | `BankAccountController@index` | any authenticated user |
| POST | `bank-accounts` | `BankAccountController@store` | `finance.manage` |
| PUT | `bank-accounts/{bankAccount}` | `BankAccountController@update` | `finance.manage` |
| DELETE | `bank-accounts/{bankAccount}` | `BankAccountController@destroy` | `finance.manage` |
| GET | `bank-transactions?bank_account_id=` | `BankTransactionController@index` | any authenticated user |
| POST | `bank-transactions` | `BankTransactionController@store` | `finance.manage` |
| PUT | `bank-transactions/{bankTransaction}` | `BankTransactionController@update` | `finance.manage` |
| DELETE | `bank-transactions/{bankTransaction}` | `BankTransactionController@destroy` | `finance.manage` |
| GET | `salary-structures` | `SalaryStructureController@index` | any authenticated user |
| POST | `salary-structures` | `SalaryStructureController@store` (upsert) | `finance.manage` |
| GET | `salary-slips?period=&employee_type=` | `SalarySlipController@index` | any authenticated user |
| POST | `salary-slips/generate` | `SalarySlipController@generate` | `finance.manage` |
| PATCH | `salary-slips/{salarySlip}/pay` | `SalarySlipController@markPaid` | `finance.manage` |
| GET | `salary-reports?period=` | `SalaryReportController@index` | any authenticated user |
| GET | `cash-book?from=&to=` | `CashBookController@index` | any authenticated user |

**Example — create an expense**

Request: `POST /erp/api/finance-payroll/expenses`
```json
{
  "expense_category_id": 3,
  "title": "Photocopier toner",
  "amount": 2450.00,
  "date": "2026-07-30",
  "payment_mode": "Cash",
  "bank_account_id": null,
  "remarks": "Office supplies"
}
```
Response `201`:
```json
{
  "id": 41,
  "voucher_no": "EXP-2026-0041",
  "expense_category_id": 3,
  "title": "Photocopier toner",
  "amount": "2450.00",
  "date": "2026-07-30",
  "payment_mode": "Cash",
  "bank_account_id": null,
  "remarks": "Office supplies",
  "paid_by_id": 7,
  "expense_category": { "id": 3, "name": "Office Supplies" },
  "bank_account": null,
  "paid_by": { "id": 7, "name": "Admin User" }
}
```

**Example — generate salary slips**

Request: `POST /erp/api/finance-payroll/salary-slips/generate`
```json
{ "period": "2026-07" }
```
Response `200`:
```json
{ "success": true, "generated": 18 }
```

**Example — mark a slip paid**

Request: `PATCH /erp/api/finance-payroll/salary-slips/12/pay`
```json
{ "payment_mode": "Bank", "paid_on": "2026-07-31" }
```
Response `200`: the updated `SalarySlip` row with `status: "Paid"`.

---

## 14. Database Tables

| Table | PK | FKs | Unique | Notes |
|---|---|---|---|---|
| `expense_categories` | `id` | — | `name` | — |
| `expenses` | `id` | `expense_category_id → expense_categories.id` (restrict), `bank_account_id → bank_accounts.id` (null on delete), `paid_by_id → erp_users.id` (null on delete) | `voucher_no` | — |
| `incomes` | `id` | `bank_account_id → bank_accounts.id` (null on delete), `received_by_id → erp_users.id` (null on delete) | `voucher_no` | — |
| `bank_accounts` | `id` | — | `account_number` | — |
| `bank_transactions` | `id` | `bank_account_id → bank_accounts.id` (cascade on delete) | — | — |
| `salary_structures` | `id` | none formal (polymorphic `employee_type`/`employee_id`, not an FK constraint) | `(employee_type, employee_id)` | — |
| `salary_slips` | `id` | `generated_by_id → erp_users.id` (null on delete); `employee_type`/`employee_id` polymorphic, not FK-constrained | `(employee_type, employee_id, period)` | `period` is `char(7)` |

No table in this module has a `deleted_at` column or the `SoftDeletes` trait — every delete listed above is a hard delete.

---

## 15. File Storage

None. No controller in this module reads or writes to any disk (`Storage::`) — no attachments, receipts, or documents are uploaded for expenses, income, bank transactions, or salary slips.

---

## 16. Background Jobs

None. Salary generation runs synchronously inside the `POST salary-slips/generate` request (a foreach loop over `SalaryStructure::all()`); there is no queued job, no `ShouldQueue`, and nothing in this module is dispatched to a queue.

---

## 17. Notifications

None. Neither `markPaid()` nor `generate()` sends an email, SMS, push notification, or in-app notification to the paid employee or anyone else — both are silent database writes that return a JSON response to the caller only. There is no notification tied to low funds, overdue payroll, or any other finance/payroll event.

---

## 18. Module Dependencies

- **People (Teachers, Staff)** — Finance & Payroll's entire payroll sub-area (`SalaryStructure`, `SalarySlip`) depends directly on the `Teacher` and `Staff` models from the People module: `SalaryStructureController::index()` reads `Teacher::with('schoolClass:id,name')` and `Staff::orderBy('name')` to build the employee list, and both `SalaryStructure`/`SalarySlip`'s `employee()` `morphTo()` resolve into those two models via the global morph map. If a teacher or staff record is deleted, existing `salary_structures`/`salary_slips` rows are **not** cleaned up (no FK constraint on the polymorphic pair) — they become orphaned and `$slip->employee` resolves to `null` (surfaces as `'—'` in the UI, per `SalarySlipController::index()`'s `$slip->employee->name ?? '—'`).
- **Academics** — no direct dependency; nothing in this module references classes, sections, or sessions (the `schoolClass` reference in the payroll row is purely informational display data pulled from `Teacher`, not a Finance & Payroll concern).
- **Reports module** — depends on Finance & Payroll's raw tables (`incomes`, `expenses`, `bank_accounts`, `salary_slips`) via `FinanceReportCalculator` and the shared `SalaryReports.vue`/`SalaryReportController` (§12), not the other way around.
- **Import & Export module** — `ExportController` reads `Expense`, `SalarySlip`, and `BankTransaction` directly for the `expense`/`salary`/`bank` export entities (§11); Finance & Payroll itself doesn't call into Import & Export.
- No dependency on Fee Management, Transport, Library, Hostel, or any other module.

---

## 19. Screenshots

Not available — generated from source code.

---

## 20. Final Deliverable

Finance & Payroll is a working, database-backed module for day-to-day cash/bank bookkeeping and teacher/staff payroll: expense and income entries with server-generated voucher numbers, manually-recorded bank deposits/withdrawals with a live-computed running balance, and a two-stage payroll flow (`SalaryStructure` → idempotent bulk `generate()` → per-slip `markPaid()`) built entirely on polymorphic `teacher`/`staff` employee references. The Cash Book and Finance Reports pages are pure read-time aggregations over these same tables — nothing about a "balance" or "cash position" is ever stored. What the module explicitly does **not** provide is formal accounting: there is no Ledger, Journal, Trial Balance, Profit & Loss, or Balance Sheet engine anywhere in the codebase, so those five menu leaves (plus Book Store, Book Expenses, and Cheque Register) are honestly represented as `GenericModule.vue` placeholders rather than partially-wired features. Known, documented gaps rather than oversights: Salary Structures have no delete route, and Salary Slips can only be generated in bulk and paid — never created or edited individually. Bank Transactions previously had the same edit gap (delete-and-re-enter only) but gained a plain `PUT`/`update()` in a later revision, closing it.
