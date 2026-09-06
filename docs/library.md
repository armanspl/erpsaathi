# Library

Backend: `app/Http/Controllers/Erp/Library/*.php` · Models: `BookCategory`, `Author`, `Publisher`, `Book`, `LibraryMember`, `BookIssue` · Routes: `routes/erp_api.php` → `Route::prefix('library')` · Frontend: `resources/js/erp/pages/library/*.vue` · Menu group: **Library** (`resources/js/erp/data/menu.js`).

See [README.md](README.md) for cross-cutting architecture (routing, permission model, generic-page mechanism, audit logging, morph maps) — only Library-specific mechanics are covered here.

## 1. Module Overview

Library manages the school's book catalogue (categories, authors, publishers, books with copy counts) and lending workflow: enrolling students/teachers/staff as library members, issuing books, returning them (with automatic overdue-fine calculation), and collecting those fines. Nine sidebar leaves; eight are real, one (Barcode) is generic. All nine reads are open to any authenticated ERP user; all writes require the `library.manage` permission key.

There is no separate fines table and no separate "fine" entity — `Book Return` and `Fine Collection` are both state-transition actions (`PATCH`) on the single `book_issues` table, alongside `Book Issue`'s plain `POST`. Fine amounts are computed by `App\Services\LibraryFineCalculator` at return time, not entered manually.

## 2. Menu Structure

Menu source: `resources/js/erp/data/menu.js` (`Library` group, lines ~90-92). Router mapping: `resources/js/erp/router/index.js` (`FLAGSHIP_ROUTES`, `/library/*` entries).

| # | Sidebar Label | URL slug | Real / Generic | Vue Component | Notes |
|---|---|---|---|---|---|
| 1 | Categories | `/library/categories` | Real | `library/Categories.vue` | `BookCategoryController` |
| 2 | Authors | `/library/authors` | Real | `library/Authors.vue` | `AuthorController` |
| 3 | Publishers | `/library/publishers` | Real | `library/Publishers.vue` | `PublisherController` |
| 4 | Books | `/library/books` | Real | `library/Books.vue` | `BookController` |
| 5 | Library Members | `/library/library-members` | Real | `library/LibraryMembers.vue` | `LibraryMemberController`; polymorphic member |
| 6 | Book Issue | `/library/book-issue` | Real | `library/BookIssue.vue` | `BookIssueController@index/store`; Create+Read only |
| 7 | Book Return | `/library/book-return` | Real | `library/BookReturn.vue` | `BookIssueController@returnBook` — **same `book_issues` table as Book Issue**, no own controller class |
| 8 | Fine Collection | `/library/fine-collection` | Real | `library/FineCollection.vue` | `FineCollectionController` — **also the same `book_issues` table**, filtered to `fine_status = Pending` |
| 9 | Barcode | `/library/barcode` | **Generic** | `GenericModule.vue` | No controller, no route, no model — falls back to `ModulePage.vue`'s seeded-random mock data (see README.md "Generic pages"). Reason: not implemented; no barcode entity or scanning integration exists in this codebase. |
| 10 | Library Reports | `/library/library-reports` | Real | `library/LibraryReports.vue` | `LibraryReportController` → `LibraryReportCalculator::summary()` |

**Reused component note (3 pages, 1 table):** Book Issue, Book Return, and Fine Collection are three distinct sidebar leaves with three distinct Vue components and (for the Return/Collect actions) route actions on the same `BookIssueController`/`FineCollectionController` pair, but they all read and write the single `book_issues` table. There is no `book_returns` or `fines`/`fine_collections` table anywhere in the schema.

**Dual reachability:** `LibraryReports.vue` is also mounted at `/reports/library-reports` (Reports module, `FLAGSHIP_ROUTES` line 241) — same component, same `GET /erp/api/library/reports` call, just reached from two different sidebar entries. See §12.

## 3–5. Pages, Fields, Form Layout

### 3.1 Categories — `/library/categories`

- **Backend**: `GET|POST /erp/api/library/categories`, `PUT|DELETE /erp/api/library/categories/{bookCategory}` → `BookCategoryController@index/store/update/destroy`.
- **Permissions**: read — any authenticated ERP user; write (store/update/destroy) — `library.manage`.
- **Validation** (`store`, verbatim):
  ```
  'name' => 'required|string|max:255|unique:book_categories,name',
  'description' => 'nullable|string|max:255',
  ```
  `update` is identical except uniqueness is scoped with `Rule::unique('book_categories','name')->ignore($bookCategory->id)`.
- **Form Layout**: single-column `SlideOver` drawer, 2 fields (Name, Description), Cancel/Save footer.

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Name | `name` | `book_categories` | string | Required | `required\|string\|max:255\|unique` | — | — | — |
| Description | `description` | `book_categories` | string, nullable | Optional | `nullable\|string\|max:255` | null | — | — |

### 3.2 Authors — `/library/authors`

- **Backend**: `GET|POST /erp/api/library/authors`, `PUT|DELETE /erp/api/library/authors/{author}` → `AuthorController`.
- **Permissions**: read open; write `library.manage`.
- **Validation** (store & update identical):
  ```
  'name' => 'required|string|max:255',
  'bio' => 'nullable|string|max:500',
  ```
- **Form Layout**: SlideOver, 2 fields (Name, Bio).

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Name | `name` | `authors` | string | Required | `required\|string\|max:255` | — | — | — |
| Bio | `bio` | `authors` | string, nullable | Optional | `nullable\|string\|max:500` | null | — | — |

### 3.3 Publishers — `/library/publishers`

- **Backend**: `GET|POST /erp/api/library/publishers`, `PUT|DELETE /erp/api/library/publishers/{publisher}` → `PublisherController`.
- **Permissions**: read open; write `library.manage`.
- **Validation** (store & update identical):
  ```
  'name' => 'required|string|max:255',
  'address' => 'nullable|string|max:255',
  'contact' => 'nullable|string|max:100',
  ```
- **Form Layout**: SlideOver, 3 fields (Name, Address, Contact).

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Name | `name` | `publishers` | string | Required | `required\|string\|max:255` | — | — | — |
| Address | `address` | `publishers` | string, nullable | Optional | `nullable\|string\|max:255` | null | — | — |
| Contact | `contact` | `publishers` | string, nullable | Optional | `nullable\|string\|max:100` | null | — | — |

### 3.4 Books — `/library/books`

- **Backend**: `GET|POST /erp/api/library/books`, `PUT|DELETE /erp/api/library/books/{book}` → `BookController`.
- **Permissions**: read open; write `library.manage`.
- **Validation** (`store`, verbatim):
  ```
  'title' => 'required|string|max:255',
  'isbn' => 'nullable|string|max:32|unique:books,isbn',
  'book_category_id' => 'required|exists:book_categories,id',
  'author_id' => 'required|exists:authors,id',
  'publisher_id' => 'required|exists:publishers,id',
  'total_copies' => 'required|integer|min:1',
  'rack_no' => 'nullable|string|max:50',
  ```
  `update` is identical except `isbn` uniqueness is scoped with `->ignore($book->id)`, and `update` additionally computes `$issuedCount = BookIssue::where('book_id', $book->id)->where('status', 'Issued')->count()` and throws a `ValidationException` on `total_copies` (message: *"Cannot reduce total copies below the {N} currently issued."*) if the new `total_copies` is less than that count.
- **Form Layout**: SlideOver, fields Title / ISBN / Category (select) / Author (select) / Publisher (select) / Total Copies + Rack No. (2-column row).

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Title | `title` | `books` | string | Required | `required\|string\|max:255` | — | — | — |
| ISBN | `isbn` | `books` | string, nullable, unique | Optional | `nullable\|string\|max:32\|unique:books,isbn` | null | — | — |
| Category | `book_category_id` | `books` | unsigned bigint | Required | `required\|exists:book_categories,id` | — | `GET /library/categories` | `book_categories.id`, restrict on delete |
| Author | `author_id` | `books` | unsigned bigint | Required | `required\|exists:authors,id` | — | `GET /library/authors` | `authors.id`, restrict on delete |
| Publisher | `publisher_id` | `books` | unsigned bigint | Required | `required\|exists:publishers,id` | — | `GET /library/publishers` | `publishers.id`, restrict on delete |
| Total Copies | `total_copies` | `books` | unsigned int | Required | `required\|integer\|min:1` | 1 (migration default) | — | — |
| Rack No. | `rack_no` | `books` | string, nullable | Optional | `nullable\|string\|max:50` | null | — | — |
| *(not user-editable)* | `available_copies` | `books` | unsigned int | n/a | not in validation rules at all | — | — | — |

`available_copies` is deliberately absent from both the create and edit form and from the validated payload — see §7/§10.

### 3.5 Library Members — `/library/library-members`

- **Backend**: `GET|POST /erp/api/library/members`, `PUT|DELETE /erp/api/library/members/{libraryMember}` → `LibraryMemberController`.
- **Permissions**: read open; write `library.manage`.
- **Validation** (`store`, verbatim):
  ```
  'member_type' => ['required', Rule::in(['student', 'teacher', 'staff'])],
  'member_id' => 'required|integer',
  'max_books' => 'nullable|integer|min:1',
  'joined_date' => 'required|date',
  ```
  Plus two manual checks in the controller body (not Laravel validation rules, but enforced the same way — a thrown `ValidationException`/404):
  - `$modelClass::where('id', $data['member_id'])->exists()` — 404 `abort(404, 'Member not found.')` if the target `student`/`teacher`/`staff` row doesn't exist.
  - `LibraryMember::where('member_type', ...)->where('member_id', ...)->exists()` — `ValidationException` on `member_id` (*"This person is already a library member."*) if that person already has a `LibraryMember` row.
  - **`validate` (`update`, verbatim)**:
  ```
  'status' => ['required', Rule::in(['Active', 'Blocked'])],
  'max_books' => 'required|integer|min:1',
  ```
  Note `update` cannot change `member_type`/`member_id`/`joined_date`/`library_card_no` — only `status` and `max_books` are accepted.
- **Form Layout**: SlideOver with two distinct template branches — Add mode (Member Type select → Person select scoped to that type → Max Books + Joined Date) vs. Edit mode (Max Books + Status select only).

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Member Type | `member_type` | `library_members` | enum(`student`,`teacher`,`staff`) | Required | `required\|in:student,teacher,staff` | — | hardcoded 3 options in Vue | — |
| Person | `member_id` | `library_members` | unsigned bigint | Required | `required\|integer` (+ manual existence check) | — | `GET /people/students`\|`/people/teachers`\|`/people/staff` depending on type, filtered client-side to exclude people already members | polymorphic — see §6 |
| Max Books | `max_books` | `library_members` | unsigned int | Optional on create, Required on edit | create: `nullable\|integer\|min:1`; edit: `required\|integer\|min:1` | `3` (both DB migration default and controller `$data['max_books'] ?? 3`) | — | — |
| Joined Date | `joined_date` | `library_members` | date | Required (create only) | `required\|date` | — | — | — |
| Status | `status` | `library_members` | enum(`Active`,`Blocked`) | Required (edit only) | `required\|in:Active,Blocked` | `Active` (set server-side on create) | — | — |
| *(not user-suppliable)* | `library_card_no` | `library_members` | string, unique | n/a | not in any validation rule | server-generated, see §10 | — | — |

### 3.6 Book Issue — `/library/book-issue`

- **Backend**: `GET|POST /erp/api/library/issues` → `BookIssueController@index/store`. **No `PUT`/`DELETE` route exists for issues at all.** `index` also accepts an optional `?student_id=` filter (`whereHas('member', fn ($q) => $q->where('member_type', 'student')->where('member_id', ...))`) — added to power People > Students' detail-view Library tab (see `people.md`); this page's own list still fetches unfiltered.
- **Permissions**: read open; write (`store`) `library.manage`.
- **Validation** (`store`, verbatim):
  ```
  'book_id' => 'required|exists:books,id',
  'library_member_id' => 'required|exists:library_members,id',
  'issue_date' => 'required|date',
  'due_date' => 'required|date|after_or_equal:issue_date',
  ```
  Plus business-rule checks that throw `ValidationException` (not Laravel `validate()` rules, but enforced identically): `$book->available_copies < 1` → error on `book_id`; `$member->status !== 'Active'` → error on `library_member_id`; open-issue count `>= $member->max_books` → error on `library_member_id`. See §10.
- **Form Layout**: SlideOver — Book (select, pre-filtered client-side to `available_copies > 0`), Member (select, pre-filtered client-side to `status === 'Active'`), Issue Date + Due Date (2-column row, due date defaulted in the Vue component to issue date + 14 days).

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Book | `book_id` | `book_issues` | unsigned bigint | Required | `required\|exists:books,id` | — | `GET /library/books` (client-filtered to available) | `books.id`, restrict on delete |
| Member | `library_member_id` | `book_issues` | unsigned bigint | Required | `required\|exists:library_members,id` | — | `GET /library/members` (client-filtered to Active) | `library_members.id`, restrict on delete |
| Issue Date | `issue_date` | `book_issues` | date | Required | `required\|date` | today (Vue default) | — | — |
| Due Date | `due_date` | `book_issues` | date | Required | `required\|date\|after_or_equal:issue_date` | issue date + 14 days (Vue default only, not a server default) | — | — |
| *(server-set)* | `status` | `book_issues` | enum(`Issued`,`Returned`) | n/a | not user-settable | `Issued` | — | — |

### 3.7 Book Return — `/library/book-return`

Same table as Book Issue. This page lists `BookIssue` rows with `status=Issued` (`GET /erp/api/library/issues?status=Issued`) and lets the user return one via a distinct action route.

- **Backend**: `PATCH /erp/api/library/issues/{bookIssue}/return` → `BookIssueController@returnBook`.
- **Permissions**: `library.manage` (inside the same protected group as `issues.store`).
- **Validation** (verbatim):
  ```
  'return_date' => 'nullable|date|after_or_equal:' . $bookIssue->issue_date->toDateString(),
  ```
  Guard clause before validation: if `$bookIssue->status !== 'Issued'`, throws `ValidationException` on `status` (*"This book has already been returned."*).
- **Form Layout**: SlideOver showing the selected issue's book/member/due-date (read-only) plus a single Return Date input.

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Return Date | `return_date` | `book_issues` | date, nullable | Optional | `nullable\|date\|after_or_equal:<issue_date>` | `now()->toDateString()` server-side if omitted | — | — |
| *(server-computed)* | `fine_amount` | `book_issues` | decimal(8,2) | n/a | — | `LibraryFineCalculator::forReturn()` result | — | — |
| *(server-computed)* | `fine_status` | `book_issues` | enum(`None`,`Pending`,`Paid`) | n/a | — | `Pending` if fine > 0 else `None` | — | — |
| *(server-set)* | `status` | `book_issues` | enum | n/a | — | `Returned` | — | — |

### 3.8 Fine Collection — `/library/fine-collection`

Same table again. Lists `BookIssue` rows with `fine_status='Pending'`.

- **Backend**: `GET /erp/api/library/fines` → `FineCollectionController@index`; `PATCH /erp/api/library/fines/{bookIssue}/collect` → `FineCollectionController@collect`.
- **Permissions**: read open; `collect` requires `library.manage`.
- **Validation**: `collect` takes no request body/validation rules — it is a pure state transition. Guard clause: if `$bookIssue->fine_status !== 'Pending'`, throws `ValidationException` (*"There is no pending fine for this issue."*).
- **Form Layout**: no drawer/form — a single "💳 Collect" row action per pending fine.

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| *(server-set on collect)* | `fine_status` | `book_issues` | enum | n/a | — | `Paid` | — | — |
| *(server-set on collect)* | `fine_paid_at` | `book_issues` | datetime, nullable | n/a | — | `now()` | — | — |
| *(server-set on collect)* | `collected_by_id` | `book_issues` | unsigned bigint, nullable | n/a | — | `Auth::guard('erp')->id()` | — | `erp_users.id`, null on delete |

### 3.9 Barcode — `/library/barcode` (Generic)

No controller, model, route, or table. Falls back to `GenericModule.vue`/`ModulePage.vue` with fully fake seeded mock data (see README.md). Not counted further in this document beyond this note.

### 3.10 Library Reports — `/library/library-reports`

- **Backend**: `GET /erp/api/library/reports` → `LibraryReportController@index` → `LibraryReportCalculator::summary()`.
- **Permissions**: read open (no write actions exist on this page).
- **Form Layout**: read-only dashboard — 4 stat cards (Titles, Members, Currently Issued, Overdue), 2 stat cards (Fine Collected, Fine Pending), 2 list panels (Overdue Books, Most Borrowed).
- No fields/inputs — pure computed output, see §12.

## 6. Database Relationships

From the actual model files:

- `Book belongsTo BookCategory` (`book_category_id`), `belongsTo Author` (`author_id`), `belongsTo Publisher` (`publisher_id`), `hasMany BookIssue`.
- `LibraryMember morphTo member()` — polymorphic. `hasMany BookIssue` (`library_member_id`).
- `BookIssue belongsTo Book`, `belongsTo LibraryMember` (`member()`, FK `library_member_id`), `belongsTo ErpUser` (`collectedBy()`, FK `collected_by_id`).
- `BookCategory`, `Author`, `Publisher` have no relation methods defined (only inverse `Book belongsTo` exists).

**The `LibraryMember` polymorphic mechanism, precisely:**

`LibraryMember::member()` is declared as a plain Eloquent `morphTo()` with no explicit type/id column names (defaults to `member_type`/`member_id`, matching the migration). This relation is resolved through Laravel's **standard global morph map**, registered once in `app/Providers/AppServiceProvider::boot()`:
```php
Relation::enforceMorphMap([
    'student' => Student::class,
    'teacher' => Teacher::class,
    'staff' => Staff::class,
    'driver' => Driver::class,
]);
```
This is the *same* app-wide map documented in README.md and shared with `Attendance`, `LeaveRequest`, `SalaryStructure`, `SalarySlip` — Library does not define its own morph map; it reuses the global one, and `member_type` values (`student`/`teacher`/`staff`) are exactly the map's keys.

**Separately**, `LibraryMemberController` uses its own trait, `App\Http\Controllers\Erp\Library\Concerns\ResolvesLibraryMemberType` (`app/Http/Controllers/Erp/Library/Concerns/ResolvesLibraryMemberType.php`), which hard-codes an **identical but independent** `type => modelClass` array (`student => Student::class, teacher => Teacher::class, staff => Staff::class`). This trait is **not** Eloquent's morph-map registry and is **not** consulted by the `member()` relation — it exists purely so `LibraryMemberController@store` can run `$modelClass::where('id', ...)->exists()` to validate the incoming `member_id` before insert, since the global morph map isn't directly usable for a plain existence check without extra Eloquent boilerplate. In other words: two parallel mechanisms, same data, different jobs — the Eloquent relation resolves via the global morph map; controller-side FK-existence validation resolves via this local concern. A developer changing the set of allowed member types must update **both**.

## 7. Create Flow

- **Category/Author/Publisher**: single-table insert, no side effects.
- **Book**: single insert into `books`; `available_copies` is force-set equal to `total_copies` server-side — the field is never taken from the request (see §10).
- **Library Member**: single insert into `library_members` after two guard checks (holder exists, not already a member); `library_card_no` generated server-side (`LIB-%04d`, see §10), `status` forced to `Active`, `max_books` defaults to 3 if omitted.
- **Book Issue** (`BookIssueController@store`): wrapped in `DB::transaction()` — (1) `$book->decrement('available_copies')`, (2) `BookIssue::create([...])` with `status = 'Issued'`. Both statements happen atomically; if the insert fails the decrement rolls back.
- **Certificates/ID Cards**: not applicable here (Documents module).

## 8. Edit Flow

- **Category/Author/Publisher**: straight `update()` of validated fields.
- **Book**: `update()` recomputes `available_copies = total_copies - $issuedCount` (where `$issuedCount` is the live count of `Issued` `BookIssue` rows for that book) rather than leaving `available_copies` untouched — so editing `total_copies` up or down proportionally adjusts `available_copies` too, after the guard that blocks reducing `total_copies` below the currently-issued count.
- **Library Member**: `update()` only accepts `status` and `max_books` — `member_type`/`member_id`/`joined_date`/`library_card_no` are immutable after creation via this endpoint (no route/validation path lets them change).
- **Book Issue**: **there is no `PUT`/update route** for `book_issues` at all — see §9. The only two ways an existing issue row changes state are the `return` and `collect` `PATCH` actions described in §3.7/§3.8.

## 9. Delete Flow

All Library deletes are **hard deletes** (`->delete()`, no `SoftDeletes` trait on any of `BookCategory`, `Author`, `Publisher`, `Book`, `LibraryMember`, `BookIssue` — none of the migrations include a `deleted_at` column).

| Entity | Route | FK behavior on delete (from migrations) |
|---|---|---|
| Category | `DELETE /library/categories/{id}` | `books.book_category_id` is `restrictOnDelete()` — DB will reject deleting a category still referenced by a book (no app-level pre-check; relies on the FK constraint, so this surfaces as a raw SQL error to the client if triggered) |
| Author | `DELETE /library/authors/{id}` | `books.author_id` `restrictOnDelete()` — same as above |
| Publisher | `DELETE /library/publishers/{id}` | `books.publisher_id` `restrictOnDelete()` — same as above |
| Book | `DELETE /library/books/{id}` | `book_issues.book_id` `restrictOnDelete()` — a book with any issue history (even fully returned) cannot be deleted |
| Library Member | `DELETE /library/members/{id}` | `book_issues.library_member_id` `restrictOnDelete()` — a member with any issue history cannot be deleted |
| **Book Issue** | **no delete route exists** | N/A |

**Book Issue explicitly has no Update or Delete.** `routes/erp_api.php` only registers `GET issues`, `POST issues`, and `PATCH issues/{bookIssue}/return` for the `book_issues` table (plus `PATCH fines/{bookIssue}/collect` from the separate `FineCollectionController`, same table). Once a book is issued, the only lifecycle transitions available are Return and (after a fine accrues) Collect — the row itself can never be edited arbitrarily or removed.

## 10. Business Rules

- **`available_copies` is a derived field, never directly editable.** Confirmed in `BookController`: on `store`, forced to `total_copies`; on `update`, recomputed as `total_copies - issuedCount`; the validated payload for both never includes `available_copies`. It only otherwise changes via `BookIssueController@store` (`decrement`) and `BookIssueController@returnBook` (`increment`).
- **Cannot issue a book with zero available copies**: `BookIssueController@store` throws if `$book->available_copies < 1`.
- **Member must be `Active` to borrow**: throws if `$member->status !== 'Active'` (a `Blocked` member cannot be issued a new book).
- **Per-member borrow limit**: throws if the member's count of currently-`Issued` `BookIssue` rows `>= $member->max_books`.
- **`library_card_no` format**: server-generated in `LibraryMemberController::nextCardNo()` — `sprintf('LIB-%04d', LibraryMember::count() + 1)`, e.g. `LIB-0001`, `LIB-0002`. This is a running count of all rows, not a per-year counter, and is **not sequence-gap-safe** (deleting a member and adding a new one can produce a duplicate-looking sequence position, though the column is `unique` at the DB level so an actual collision would raise a DB error — this is a simple counter, not a guaranteed-collision-free generator under concurrent writes).
- **Duplicate membership blocked**: a `(member_type, member_id)` pair can only have one `LibraryMember` row (checked in code and enforced by the composite `unique(['member_type','member_id'])` index in the migration).
- **Fine calculation**: `App\Services\LibraryFineCalculator::forReturn(BookIssue $issue, string $returnDate)` — `FINE_PER_DAY = 2.0`; `$daysLate = max(0, due->diffInDays(returned, false))`; fine `= round($daysLate * 2.0, 2)`. Returning on/before the due date yields a fine of `0` and `fine_status = 'None'`; returning late yields a positive fine and `fine_status = 'Pending'` until collected.
- **Return date validation**: must be `>= issue_date`; an issue already `Returned` cannot be returned again (guard clause, not a validation rule).
- **Collect guard**: a fine can only be collected while `fine_status === 'Pending'`.

## 11. Import & Export

Per README.md's central Import/Export inventory (`app/Http/Controllers/Erp/ImportExport/*`, Export covers only `student, fee, expense, salary, bank, fuel-log, route, marks, global`): **Library has no Import and no Export for any page** — Categories, Authors, Publishers, Books, Library Members, Book Issue, Book Return, Fine Collection, and Library Reports all lack any import/export route, button, or backend endpoint. This is unrelated to the Generic-page Barcode leaf's simulated Import/Export buttons (see README.md "Generic pages" — those are mock-data demo actions on `ModulePage.vue`, not real functionality either).

## 12. Reports

- **Library Reports** (`/library/library-reports`) is a real page backed by `LibraryReportController@index` → `App\Services\LibraryReportCalculator::summary()`, which computes everything live from `books`, `book_issues`, `library_members` (no stored/cached report table):
  - `total_books` = `SUM(books.total_copies)`
  - `available_books` = `SUM(books.available_copies)`
  - `total_titles` = `COUNT(books)`
  - `total_members` = `COUNT(library_members)`
  - `currently_issued` = count of `book_issues` where `status='Issued'`
  - `overdue_count` = of those, count where `due_date` is past
  - `fine_collected_total` = `SUM(fine_amount)` where `fine_status='Paid'`
  - `fine_pending_total` = `SUM(fine_amount)` where `fine_status='Pending'`
  - `overdue_list` = per-issue detail (book, due date, days late)
  - `most_borrowed` = top 5 books by issue count (`GROUP BY book_id`)
- **Dual reachability confirmed**: the exact same `LibraryReports.vue` component is registered twice in `router/index.js`'s `FLAGSHIP_ROUTES` — once at `/library/library-reports` (Library group) and once at `/reports/library-reports` (Reports group, line 241) — both hitting the identical `GET /erp/api/library/reports` endpoint. There is no separate "Reports module" backend for library data; it is the same controller/service reused under a second sidebar entry.

## 13. APIs

All under `Route::prefix('library')->name('library.')` in `routes/erp_api.php`. Reads unguarded (any authenticated `erp` user); writes wrapped in `Route::middleware('erp.permission:library.manage')`.

| Method | Endpoint | Controller@method | Auth |
|---|---|---|---|
| GET | `/erp/api/library/categories` | `BookCategoryController@index` | read |
| POST | `/erp/api/library/categories` | `BookCategoryController@store` | `library.manage` |
| PUT | `/erp/api/library/categories/{bookCategory}` | `BookCategoryController@update` | `library.manage` |
| DELETE | `/erp/api/library/categories/{bookCategory}` | `BookCategoryController@destroy` | `library.manage` |
| GET | `/erp/api/library/authors` | `AuthorController@index` | read |
| POST | `/erp/api/library/authors` | `AuthorController@store` | `library.manage` |
| PUT | `/erp/api/library/authors/{author}` | `AuthorController@update` | `library.manage` |
| DELETE | `/erp/api/library/authors/{author}` | `AuthorController@destroy` | `library.manage` |
| GET | `/erp/api/library/publishers` | `PublisherController@index` | read |
| POST | `/erp/api/library/publishers` | `PublisherController@store` | `library.manage` |
| PUT | `/erp/api/library/publishers/{publisher}` | `PublisherController@update` | `library.manage` |
| DELETE | `/erp/api/library/publishers/{publisher}` | `PublisherController@destroy` | `library.manage` |
| GET | `/erp/api/library/books` | `BookController@index` | read |
| POST | `/erp/api/library/books` | `BookController@store` | `library.manage` |
| PUT | `/erp/api/library/books/{book}` | `BookController@update` | `library.manage` |
| DELETE | `/erp/api/library/books/{book}` | `BookController@destroy` | `library.manage` |
| GET | `/erp/api/library/members` | `LibraryMemberController@index` | read |
| POST | `/erp/api/library/members` | `LibraryMemberController@store` | `library.manage` |
| PUT | `/erp/api/library/members/{libraryMember}` | `LibraryMemberController@update` | `library.manage` |
| DELETE | `/erp/api/library/members/{libraryMember}` | `LibraryMemberController@destroy` | `library.manage` |
| GET | `/erp/api/library/issues` | `BookIssueController@index` (optional `?status=Issued\|Returned`, `?student_id=`) | read |
| POST | `/erp/api/library/issues` | `BookIssueController@store` | `library.manage` |
| PATCH | `/erp/api/library/issues/{bookIssue}/return` | `BookIssueController@returnBook` | `library.manage` |
| GET | `/erp/api/library/fines` | `FineCollectionController@index` | read |
| PATCH | `/erp/api/library/fines/{bookIssue}/collect` | `FineCollectionController@collect` | `library.manage` |
| GET | `/erp/api/library/reports` | `LibraryReportController@index` | read |

### Example: Issue a book

```
POST /erp/api/library/issues
{
  "book_id": 12,
  "library_member_id": 4,
  "issue_date": "2026-07-30",
  "due_date": "2026-08-13"
}
```
Response `201`:
```json
{
  "id": 57, "book_id": 12, "library_member_id": 4,
  "issue_date": "2026-07-30", "due_date": "2026-08-13",
  "return_date": null, "status": "Issued",
  "fine_amount": "0.00", "fine_status": "None",
  "book": { "id": 12, "title": "Wings of Fire" },
  "member": { "id": 4, "library_card_no": "LIB-0004", "member": { "name": "Aarav Sharma" } }
}
```

### Example: Return a book (with fine)

```
PATCH /erp/api/library/issues/57/return
{ "return_date": "2026-08-20" }
```
Response `200` (7 days late × ₹2 = ₹14):
```json
{
  "id": 57, "status": "Returned", "return_date": "2026-08-20",
  "fine_amount": "14.00", "fine_status": "Pending", ...
}
```

### Example: Collect a fine

```
PATCH /erp/api/library/fines/57/collect
```
Response `200`:
```json
{
  "id": 57, "fine_status": "Paid",
  "fine_paid_at": "2026-08-21T10:15:00.000000Z",
  "collected_by_id": 3, ...
}
```

## 14. Database Tables

| Table | PK | FKs → target | Unique | Notes |
|---|---|---|---|---|
| `book_categories` | `id` | — | `name` | — |
| `authors` | `id` | — | — | — |
| `publishers` | `id` | — | — | — |
| `books` | `id` | `book_category_id → book_categories.id` (restrict), `author_id → authors.id` (restrict), `publisher_id → publishers.id` (restrict) | `isbn` (nullable-unique) | `total_copies`/`available_copies` default `1` |
| `library_members` | `id` | none declared (polymorphic `member_type`+`member_id`, no DB-level FK — resolved only in app code via morph map) | `library_card_no`; composite `(member_type, member_id)` | `status` enum default `Active`; `max_books` default `3` |
| `book_issues` | `id` | `book_id → books.id` (restrict), `library_member_id → library_members.id` (restrict), `collected_by_id → erp_users.id` (nullable, null on delete) | — | `status` enum default `Issued`; `fine_amount` decimal(8,2) default `0`; `fine_status` enum default `None` |

No indexes beyond the primary keys, foreign keys, and the unique constraints listed above are declared in the migrations.

## 15. File Storage

None. Library has no file-upload fields anywhere (no book cover image, no scanned document, no attachment column on any of the six tables) and no barcode image generation/storage — the Barcode leaf is entirely a Generic mock page (§2). Nothing in this module writes to `storage/app` or any disk.

## 16. Background Jobs

None. There is no scheduled/queued job that auto-accrues overdue fines, auto-flags overdue issues, or auto-blocks members who exceed their limit — `fine_amount`/`fine_status` are computed only synchronously, at the moment `BookIssueController@returnBook` runs. An issue that is overdue but not yet returned shows `is_overdue: true` in the `index()` response (computed inline: `status === 'Issued' && due_date->isPast()`) but this is a read-time computation, not a stored/job-driven flag, and no fine accrues until an actual return happens.

## 17. Notifications

None. No email/SMS/push notification is sent for due-date reminders, overdue books, fine amounts, or new membership — confirmed absent from all Library controllers (no `Notification::send`, `Mail::`, or queued job dispatch anywhere in `app/Http/Controllers/Erp/Library/*` or `app/Services/Library*.php`).

## 18. Module Dependencies

- **People**: `LibraryMember.member_id` and the `holder`/`member` resolution depend on `Student`, `Teacher`, `Staff` models (People module) via the shared global morph map (`student`/`teacher`/`staff`). The Library Members, Book Issue, ID Cards forms all pull their person dropdowns from `GET /people/students`, `/people/teachers`, `/people/staff`.
- **Settings**: permission gating depends on the `library.manage` key defined in `resources/js/erp/pages/settings/Roles.vue`'s `availablePermissions` and checked by `erp.permission` middleware against `erp_roles.permissions`.
- **Documents**: the Documents module's "Library Cards" print page (`/documents/library-cards`) reads `GET /library/members` directly — Library is a data dependency *of* Documents, not the reverse (see documents.md §2).
- **Reports**: the Reports module's "Library Reports" leaf reuses this module's own `LibraryReports.vue`/`LibraryReportController` rather than defining its own (see §12).
- **Feeds People > Students**: the detail-view Library tab (`StudentTabLibrary.vue`) fetches `GET /library/issues?student_id={id}` (new filter, see §3.6) to show one student's real book issue/return history (`people.md`).

## 19. Screenshots

Not available — generated from source code.

## 20. Final Deliverable

Library is a fully real, database-backed module across 8 of its 9 sidebar leaves (Barcode being the sole Generic placeholder). Its catalogue side (Categories/Authors/Publishers/Books) is straightforward CRUD, but its lending side is a deliberately tight three-page, one-table design: `BookIssueController` owns Create+Read plus a `return` state transition, and a separate `FineCollectionController` owns a `collect` state transition on the exact same `book_issues` rows — there is no independent fines table and no Update/Delete on issues at all. `Book.available_copies` is a derived counter, never client-supplied, kept in sync by increment/decrement on issue/return and by recomputation on `total_copies` edits. `LibraryMember` is genuinely polymorphic through Laravel's app-wide morph map (shared with Attendance/Payroll), while a separate, parallel controller-side concern (`ResolvesLibraryMemberType`) independently duplicates the same type-to-class mapping purely to validate FK existence before insert — two mechanisms, same data, different purposes, and both must be updated together if a new member type is ever added. Fines are a live, formula-driven computation (`₹2/day late`, `App\Services\LibraryFineCalculator`) rather than a stored, editable amount. No import/export, file storage, background jobs, or notifications exist anywhere in this module today.
