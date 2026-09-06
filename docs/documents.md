# Documents

Backend: `app/Http/Controllers/Erp/Documents/*.php` · Models: `Certificate`, `IdCard` · Routes: `routes/erp_api.php` → `Route::prefix('documents')` · Frontend: `resources/js/erp/pages/documents/*.vue` · Menu group: **Documents** (`resources/js/erp/data/menu.js`).

See [README.md](README.md) for cross-cutting architecture (routing, permission model, generic-page mechanism, audit logging, morph maps) — only Documents-specific mechanics are covered here.

## 1. Module Overview

Documents issues and prints two real record-backed document types — student **Certificates** (Bonafide, Transfer, Character, Migration, plus a generic "Certificates" view over all four) and **ID Cards** for students/teachers/staff — and additionally hosts two **print-only veneer pages** (Library Cards, Transport Cards) that format data already owned by other modules for printing, with no entity or table of their own. Three of eleven sidebar leaves (Admit Card Templates, Report Card Templates, Template Builder) are Generic placeholders. All document generation is render-on-demand HTML shown in an on-screen modal and sent to the browser's native print dialog via `window.print()` — there is no server-side PDF generation and no file is ever written to disk.

## 2. Menu Structure

Menu source: `resources/js/erp/data/menu.js` (`Documents` group, lines ~105-110). Router mapping: `resources/js/erp/router/index.js` (`FLAGSHIP_ROUTES`, `/documents/*` entries).

| # | Sidebar Label | URL slug | Real / Generic | Vue Component | Notes |
|---|---|---|---|---|---|
| 1 | ID Cards | `/documents/id-cards` | Real | `documents/IdCards.vue` | `IdCardController`; own table `id_cards` |
| 2 | Certificates | `/documents/certificates` | Real | `documents/Certificates.vue` | `CertificateController`; shows all 4 types |
| 3 | Bonafide | `/documents/bonafide` | Real | `documents/Certificates.vue` (**shared**) | Same component as #2, `type` fixed to `Bonafide` via route-path lookup |
| 4 | Transfer Certificate | `/documents/transfer-certificate` | Real | `documents/Certificates.vue` (**shared**) | `type` fixed to `Transfer Certificate` |
| 5 | Character Certificate | `/documents/character-certificate` | Real | `documents/Certificates.vue` (**shared**) | `type` fixed to `Character Certificate` |
| 6 | Migration Certificate | `/documents/migration-certificate` | Real | `documents/Certificates.vue` (**shared**) | `type` fixed to `Migration Certificate` |
| 7 | Library Cards | `/documents/library-cards` | Real, **print-only veneer** | `documents/LibraryCards.vue` | No own table — reads `GET /library/members` directly; see below |
| 8 | Transport Cards | `/documents/transport-cards` | Real, **print-only veneer** | `documents/TransportCards.vue` | No own table — reads `GET /transport/student-transport` directly; see below |
| 9 | Admit Card Templates | `/documents/admit-card-templates` | **Generic** | `GenericModule.vue` | No controller/model/route exists; falls back to mock `ModulePage.vue` (README.md "Generic pages"). Reason: not implemented — no template-builder backend for admit cards. |
| 10 | Report Card Templates | `/documents/report-card-templates` | **Generic** | `GenericModule.vue` | Same reason — no template-builder backend for report cards. |
| 11 | Template Builder | `/documents/template-builder` | **Generic** | `GenericModule.vue` | Same reason — no generic document-template-builder backend exists anywhere in the app. |

**"5-in-1 Certificates" mechanism, precisely:** `Certificates.vue` is mounted at five different router paths (`/documents/certificates`, `/documents/bonafide`, `/documents/transfer-certificate`, `/documents/character-certificate`, `/documents/migration-certificate`) all pointing to the same imported component (`router/index.js` lines 213-217: `Certificates` imported once, reused as the value for all five map entries). Inside the component, `useRoute()` reads `route.path` and looks it up in a local `routeTypeMap` object to derive a fixed `type` for that leaf; the plain `/documents/certificates` path isn't in the map so `routeType` is `null` there, showing all four types with an extra client-side Type filter. The `type`-specific leaves hard-code `form.type` to their type when opening the "Issue Certificate" drawer and hide that filter. All five leaves call the exact same backend endpoints (`GET/POST/DELETE /erp/api/documents/certificates`) — there is no per-type controller or table; `certificates.type` is a single `enum` column.

**Print-only veneer pages, precisely — no `library_cards` or `transport_cards` table exists:**
- `LibraryCards.vue` calls `GET /erp/api/library/members` (the Library module's own endpoint — `LibraryMemberController@index`), renders a card-shaped preview from the returned `LibraryMember` rows (`library_card_no`, `member_name`, `member_type`, `max_books`, `joined_date`), and prints via `window.print()`. There is no `documents.library-cards.*` route in `erp_api.php` at all — a developer looking for a "Library Cards" backend entity will not find one; it doesn't exist by design.
- `TransportCards.vue` calls `GET /erp/api/transport/student-transport` (the Transport module's endpoint), renders a card from the returned `StudentTransport` row's `student`, `route`, `route_stop` relations, and prints the same way. Again, no `documents.transport-cards.*` route exists.

Both pages are purely client-side reformatting/print views over data that is fully owned, validated, and persisted by other modules (Library, Transport respectively) — Documents contributes zero storage for either.

## 3–5. Pages, Fields, Form Layout

### 3.1 ID Cards — `/documents/id-cards`

- **Backend**: `GET|POST /erp/api/documents/id-cards`, `PUT /erp/api/documents/id-cards/{idCard}`, `PATCH /erp/api/documents/id-cards/{idCard}/reissue`, `DELETE /erp/api/documents/id-cards/{idCard}` → `IdCardController`.
- **Permissions**: read (`index`) open to any authenticated ERP user; `store`/`update`/`reissue`/`destroy` require `documents.manage`.
- **Validation** (`store` and `update`, identical, verbatim):
  ```
  'holder_type' => ['required', Rule::in(['student', 'teacher', 'staff'])],
  'holder_id' => 'required|integer',
  'issued_date' => 'required|date',
  'valid_until' => 'nullable|date|after:issued_date',
  ```
  Plus manual checks (both `store` and `update`): `$modelClass::where('id', $data['holder_id'])->exists()` → 404 if the holder doesn't exist. **`update` does not re-check "one active card per holder"** — only `store` does — and, unlike Certificates below, `update` deliberately does not touch `card_no`/`status`: it exists to correct a mistaken holder/date entry, not to re-issue a fresh card (that's what the dedicated `reissue` action is for). `reissue` and `destroy` take no request body.
- **Form Layout**: SlideOver, title "Issue ID Card"/"Edit ID Card" on an `editing` ref — Holder Type (select, resets Person on change) → Person (select, options scoped by holder type) → Issued Date + Valid Until (2-column row). An Edit (✏️) row action now sits alongside Print/Reissue/Delete.

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Holder Type | `holder_type` | `id_cards` | enum(`student`,`teacher`,`staff`) | Required | `required\|in:student,teacher,staff` | `student` (Vue form default) | hardcoded 3 options | — |
| Person | `holder_id` | `id_cards` | unsigned bigint | Required | `required\|integer` (+ manual existence check) | — | `GET /people/students`\|`/people/teachers`\|`/people/staff` | polymorphic — see §6 |
| Issued Date | `issued_date` | `id_cards` | date | Required | `required\|date` | today (Vue default) | — | — |
| Valid Until | `valid_until` | `id_cards` | date, nullable | Optional | `nullable\|date\|after:issued_date` | null | — | — |
| *(server-set)* | `card_no` | `id_cards` | string, unique | n/a | not user-suppliable | server-generated, see §10 | — | — |
| *(server-set)* | `status` | `id_cards` | enum(`Active`,`Reissued`,`Lost`) | n/a | not user-suppliable on create | `Active` | — | — |

Note: the `Lost` status value exists in the DB enum (migration) but no controller action ever sets it — only `Active` (on create/reissue) and `Reissued` (on the old card, during a reissue) are reachable through current code. There is no "mark as lost" UI action or endpoint.

### 3.2 Certificates / Bonafide / Transfer Certificate / Character Certificate / Migration Certificate — 5 leaves, 1 component, 1 backend

- **Backend**: `GET|POST /erp/api/documents/certificates`, `PUT /erp/api/documents/certificates/{certificate}`, `DELETE /erp/api/documents/certificates/{certificate}` → `CertificateController`.
- **Permissions**: read open; `store`/`update`/`destroy` require `documents.manage`.
- **Validation** (`store` and `update`, identical, verbatim):
  ```
  'student_id' => 'required|exists:students,id',
  'type' => ['required', Rule::in(array_keys(self::TYPE_PREFIXES))],
  'issue_date' => 'required|date',
  'reason' => 'nullable|string|max:255',
  'remarks' => 'nullable|string|max:255',
  ```
  where `TYPE_PREFIXES = ['Bonafide'=>'BON','Transfer Certificate'=>'TC','Character Certificate'=>'CC','Migration Certificate'=>'MC']` — so `type` is effectively restricted to exactly those 4 values (the plain "Certificates" leaf can issue any of the 4, it just isn't type-locked in the UI). **`update` deliberately does not regenerate `certificate_no`, even if `type` changes** — per the controller's own doc comment, this corrects a mistaken entry, it is not a reissue.
- **Form Layout**: SlideOver, title "Issue Certificate"/"Edit Certificate" on an `editing` ref — Student (select), Certificate Type (select — pre-locked to the route's type on the 4 dedicated leaves), Issue Date, Reason, Remarks. Row actions: Print (opens modal preview), Edit (✏️), and Delete.

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Student | `student_id` | `certificates` | unsigned bigint | Required | `required\|exists:students,id` | — | `GET /people/students` | `students.id`, restrict on delete |
| Certificate Type | `type` | `certificates` | enum(4 values) | Required | `required\|in:Bonafide,Transfer Certificate,Character Certificate,Migration Certificate` | route-derived type, or first type constant on the plain leaf | hardcoded 4 options, hidden on the 4 typed leaves | — |
| Issue Date | `issue_date` | `certificates` | date | Required | `required\|date` | today (Vue default) | — | — |
| Reason | `reason` | `certificates` | string, nullable | Optional | `nullable\|string\|max:255` | null | — | — |
| Remarks | `remarks` | `certificates` | string, nullable | Optional | `nullable\|string\|max:255` | null | — | — |
| *(server-set)* | `certificate_no` | `certificates` | string, unique | n/a | not user-suppliable | server-generated, see §10 | — | — |
| *(server-set)* | `issued_by_id` | `certificates` | unsigned bigint, nullable | n/a | not user-suppliable | `Auth::guard('erp')->id()` | — | `erp_users.id`, null on delete |

### 3.3 Library Cards — `/documents/library-cards` (print-only veneer)

- **Backend**: none of its own — reads `GET /erp/api/library/members` (see §2). No `store`/`update`/`delete`.
- **Permissions**: same as Library Members read — open to any authenticated ERP user (no `documents.manage` gate applies since there's no write action).
- **Validation**: n/a — no form submission, only client-side Search + Status filters over the fetched `LibraryMember` list, and a print preview.
- **Form Layout**: no create/edit form. Table + a "🖨 Print" row action opening a read-only card preview (`library_card_no`, member name/code/type, max books, joined date) with a "🖨 Print" button that calls `window.print()`.
- Field-level table: n/a (no writable fields — it displays `LibraryMember` fields documented in library.md §5).

### 3.4 Transport Cards — `/documents/transport-cards` (print-only veneer)

- **Backend**: none of its own — reads `GET /erp/api/transport/student-transport`. No `store`/`update`/`delete`.
- **Permissions**: same as that Transport endpoint's read gate — open to any authenticated ERP user.
- **Validation**: n/a — client-side Search + Status filters, print preview only.
- **Form Layout**: no create/edit form. Table + "🖨 Print" row action opening a preview (student name/admission no., route name, stop name) with `window.print()`.
- Field-level table: n/a (displays `StudentTransport` fields owned by the Transport module).

### 3.5–3.7 Admit Card Templates / Report Card Templates / Template Builder (Generic)

All three: no controller, model, route, or table exists. They fall back to `GenericModule.vue` → `ModulePage.vue`'s seeded-random mock data (README.md "Generic pages"). Reason (all three): no document-template-authoring backend has been implemented in this codebase — the actual Admit Card and Report Card *rendering* live in Exam Management (`Admit Cards`, `Report Cards` sidebar leaves there), but a reusable drag-and-drop/parametrized *template builder* for them does not exist.

## 6. Database Relationships

- `Certificate belongsTo Student` (`student_id`), `belongsTo ErpUser` (`issuedBy()`, FK `issued_by_id`).
- `IdCard morphTo holder()` — polymorphic, no explicit column names given to `morphTo()` (defaults to `holder_type`/`holder_id`, matching the migration).

**`IdCard`'s polymorphic mechanism is identical in kind to `LibraryMember`'s (see library.md §6.) — it is not a separate, bespoke mechanism.** `IdCard::holder()` resolves through the exact same **global** morph map registered once in `app/Providers/AppServiceProvider::boot()`:
```php
Relation::enforceMorphMap([
    'student' => Student::class,
    'teacher' => Teacher::class,
    'staff' => Staff::class,
    'driver' => Driver::class,
]);
```
`holder_type` values (`student`/`teacher`/`staff`) are exactly that map's keys, so `$idCard->holder` resolves to a `Student`, `Teacher`, or `Staff` model without any Documents-specific configuration.

**Separately**, `IdCardController` uses its own trait, `App\Http\Controllers\Erp\Documents\Concerns\ResolvesCardHolderType` (`app/Http/Controllers/Erp/Documents/Concerns/ResolvesCardHolderType.php`), which — like Library's `ResolvesLibraryMemberType` — hard-codes an independent, identical `type => modelClass` array purely so `store()` can validate that `holder_id` exists in the target table before insert. This is not Eloquent's morph-map registry and is not used by the `holder()` relation itself; it is a second, parallel, controller-only copy of the same mapping. As in Library, a new holder type must be added to both the global morph map *and* this local concern.

## 7. Create Flow

- **ID Card**: single insert into `id_cards` after two guard checks (holder exists; holder has no other `Active` card). `card_no` generated server-side, `status` forced `Active`.
- **Certificate**: single insert into `certificates`. `certificate_no` generated server-side, `issued_by_id` set from the authenticated user. No side effects on any other table.
- **Reissue** (`IdCardController@reissue`, `PATCH`): two writes, not wrapped in an explicit `DB::transaction()` in the controller — (1) `$idCard->update(['status' => 'Reissued'])` on the old row, (2) `IdCard::create([...])` for a brand-new row (new `card_no`, same `holder_type`/`holder_id`, `issued_date = now()`, carries over the old `valid_until`, `status = 'Active'`). Both the old and new card rows persist — reissue does not delete or overwrite history.

## 8. Edit Flow

- **ID Card**: `PUT /documents/id-cards/{idCard}` → `update()` corrects `holder_type`/`holder_id`/`issued_date`/`valid_until` on an existing card without touching `card_no`/`status` — distinct from `reissue` (PATCH, still a special-purpose action that mints a whole new card row and history entry, not a generic update).
- **Certificate**: `PUT /documents/certificates/{certificate}` → `update()` corrects `student_id`/`type`/`issue_date`/`reason`/`remarks` without regenerating `certificate_no`, even if `type` changes.
- **Library Cards / Transport Cards**: not applicable — no editable fields, print-only.

## 9. Delete Flow

Both `Certificate` and `IdCard` use **hard deletes** (`->delete()`; neither model has a `SoftDeletes` trait, and neither migration has a `deleted_at` column).

| Entity | Route | FK behavior on delete (from migration) |
|---|---|---|
| Certificate | `DELETE /documents/certificates/{id}` | No inbound FK references it from elsewhere; `certificates.student_id → students.id` is `restrictOnDelete()`, meaning a **student** can't be deleted while they still have certificates on file (this constrains deleting the *parent* Student, not the certificate itself) |
| ID Card | `DELETE /documents/id-cards/{id}` | No FK constraints reference `id_cards` from other tables; deleting an ID card is unconstrained |

Neither `Certificate` nor `IdCard` is referenced as a parent by any other table's foreign key — deleting either is always immediate and unconditional (no restrict/cascade concerns from *their* side).

## 10. Business Rules

- **`card_no` format**: `IdCardController::nextCardNo()` — `sprintf('IDC-%s-%04d', now()->format('Y'), $count)` where `$count = IdCard::where('card_no', 'like', "IDC-{$year}-%")->count() + 1`. Per-year sequential, e.g. `IDC-2026-0001`. Not user-suppliable in any form field.
- **`certificate_no` format**: `CertificateController::nextCertificateNo($type)` — `sprintf('%s-%s-%04d', $prefix, now()->format('Y'), $count)` where `$prefix` is one of `BON`/`TC`/`CC`/`MC` (from `TYPE_PREFIXES`) and `$count` is scoped `where('certificate_no', 'like', "{$prefix}-{$year}-%")->count() + 1`. Per-type-per-year sequential, e.g. `TC-2026-0003`. Not user-suppliable.
- **One active ID card per holder**: enforced in `IdCardController@store` — a new card cannot be issued to a `holder_type`/`holder_id` pair that already has an `IdCard` row with `status='Active'`. (Not a DB-level unique constraint — `id_cards` has no composite unique index on `(holder_type, holder_id)`, unlike `library_members`; the check is purely in application code, and is bypassed once the prior card's status is no longer `Active`, e.g. after `Reissued`.)
- **Reissue always creates a new row**: never mutates the original card's `card_no` — history is fully preserved (old row flips to `Reissued`, new row is `Active`).
- **No fine/payment logic** applies to Documents — certificates and ID cards have no cost/fee field anywhere in the schema.

## 11. Import & Export

Per README.md's central Import/Export inventory: **Documents has no Import and no Export for any page** — ID Cards, Certificates (all 5 leaves), Library Cards, and Transport Cards have no import/export route, button, or backend endpoint. (Import/Export today covers only `student, fee, expense, salary, bank, fuel-log, route, marks, global` per the central `ExportController`.)

## 12. Reports

Documents has no dedicated Reports page of its own and is not one of the entities covered by `app/Services/*Calculator.php` report services. There is no "Documents Reports" leaf in either the Documents menu group or the Reports module — unlike Library, there is no dual-reachable reporting page to document here.

## 13. APIs

All under `Route::prefix('documents')->name('documents.')` in `routes/erp_api.php`. Reads unguarded; writes wrapped in `Route::middleware('erp.permission:documents.manage')`.

| Method | Endpoint | Controller@method | Auth |
|---|---|---|---|
| GET | `/erp/api/documents/certificates` | `CertificateController@index` (optional `?type=`) | read |
| POST | `/erp/api/documents/certificates` | `CertificateController@store` | `documents.manage` |
| PUT | `/erp/api/documents/certificates/{certificate}` | `CertificateController@update` | `documents.manage` |
| DELETE | `/erp/api/documents/certificates/{certificate}` | `CertificateController@destroy` | `documents.manage` |
| GET | `/erp/api/documents/id-cards` | `IdCardController@index` (optional `?holder_type=`) | read |
| POST | `/erp/api/documents/id-cards` | `IdCardController@store` | `documents.manage` |
| PUT | `/erp/api/documents/id-cards/{idCard}` | `IdCardController@update` | `documents.manage` |
| PATCH | `/erp/api/documents/id-cards/{idCard}/reissue` | `IdCardController@reissue` | `documents.manage` |
| DELETE | `/erp/api/documents/id-cards/{idCard}` | `IdCardController@destroy` | `documents.manage` |

Library Cards and Transport Cards issue no requests of their own — they call `GET /erp/api/library/members` and `GET /erp/api/transport/student-transport` respectively (documented in library.md and transport.md).

Note: `CertificateController@index` supports a `?type=` query filter server-side, but the current `Certificates.vue` frontend never sends it — the 4 typed leaves fetch the full unfiltered list and filter client-side by matching `route.path`. The `?type=` param is live and usable directly against the API even though the shipped UI doesn't exercise that code path.

### Example: Issue a Transfer Certificate

```
POST /erp/api/documents/certificates
{
  "student_id": 214,
  "type": "Transfer Certificate",
  "issue_date": "2026-07-30",
  "reason": "Relocation",
  "remarks": ""
}
```
Response `201`:
```json
{
  "id": 41, "certificate_no": "TC-2026-0007", "student_id": 214,
  "type": "Transfer Certificate", "issue_date": "2026-07-30",
  "reason": "Relocation", "remarks": "", "issued_by_id": 3,
  "student": { "id": 214, "name": "Priya Verma", "admission_no": "GS2023-214" },
  "issuedBy": { "id": 3, "name": "Admin User" }
}
```

### Example: Issue an ID card, then reissue it

```
POST /erp/api/documents/id-cards
{ "holder_type": "teacher", "holder_id": 18, "issued_date": "2026-07-30", "valid_until": "2027-07-30" }
```
Response `201`: `{ "id": 9, "card_no": "IDC-2026-0009", "holder_type": "teacher", "holder_id": 18, "status": "Active", ... }`

```
PATCH /erp/api/documents/id-cards/9/reissue
```
Response `201` (a brand-new row; old row `9` is now `Reissued`):
```json
{ "id": 15, "card_no": "IDC-2026-0015", "holder_type": "teacher", "holder_id": 18, "status": "Active", "valid_until": "2027-07-30", ... }
```

## 14. Database Tables

| Table | PK | FKs → target | Unique | Notes |
|---|---|---|---|---|
| `certificates` | `id` | `student_id → students.id` (restrict), `issued_by_id → erp_users.id` (nullable, null on delete) | `certificate_no` | `type` enum, 4 values |
| `id_cards` | `id` | none declared (polymorphic `holder_type`+`holder_id`, no DB-level FK) | `card_no` | `status` enum(`Active`,`Reissued`,`Lost`) default `Active`; **no** composite unique on `(holder_type, holder_id)` — the "one active card" rule is app-code-only, not DB-enforced |

No indexes beyond primary keys, foreign keys, and the listed unique columns are declared in either migration.

## 15. File Storage

**None.** Neither Certificates nor ID Cards write any file to disk, S3, or any Laravel filesystem. Both are **render-on-demand**: the Vue components (`Certificates.vue`, `IdCards.vue`) hold the "print view" as an in-memory HTML fragment (`active` ref) styled with `print:` Tailwind utility classes, shown in a modal, and sent to the OS/browser print dialog via the browser-native `window.print()` call — confirmed by reading the `<script setup>` blocks of both components; there is no PDF library (no dompdf/mpdf/snappy), no `Storage::put`, and no file-returning controller response (`response()->download`, `response()->file`, etc.) anywhere in `CertificateController` or `IdCardController`. Nothing generated is ever retrievable after the browser tab closes; re-printing means re-opening the preview from the live record.

## 16. Background Jobs

None. No queued/scheduled job renders, pre-generates, expires, or cleans up certificates or ID cards. `valid_until` on an ID card is a plain nullable date column with no job that flips `status` to anything when it passes — an "expired" card just keeps showing `status: Active` with a past `valid_until` date; there is no automatic transition.

## 17. Notifications

None. No email/SMS/push notification is sent when a certificate is issued, an ID card is issued or reissued, or a card is nearing its `valid_until` date — confirmed absent from `CertificateController` and `IdCardController` (no `Notification::send`, `Mail::`, or queued dispatch in either file or in `ResolvesCardHolderType`).

## 18. Module Dependencies

- **People**: `Certificate.student_id` depends on `Student` (People module) directly via a real FK; `IdCard.holder_id` depends on `Student`/`Teacher`/`Staff` (People module) via the shared polymorphic morph map. All "Person"/"Student" dropdowns in Documents forms are populated from `GET /people/students`, `/people/teachers`, `/people/staff`.
- **Library**: the Library Cards print page depends entirely on `GET /library/members` (Library module) — Documents contributes no storage of its own for this leaf; see §2.
- **Transport**: the Transport Cards print page depends entirely on `GET /transport/student-transport` (Transport module) — same pattern, no storage of its own.
- **Settings**: write actions are gated by the `documents.manage` permission key (`resources/js/erp/pages/settings/Roles.vue`).
- **Exam Management**: not a runtime dependency, but worth noting for orientation — actual Admit Card / Report Card *rendering* lives in the Exam Management module's own leaves; Documents' same-named "Admit Card Templates"/"Report Card Templates" leaves are unrelated, unimplemented (Generic) template-authoring placeholders, not the rendering pages themselves.

## 19. Screenshots

Not available — generated from source code.

## 20. Final Deliverable

Documents is a small, mostly-real module: two genuinely backed entities (`Certificate`, `IdCard`), each now with Create+Read+**Update**+Delete — a later revision added a plain `PUT` for both, so a mistake in a certificate's reason or an ID card's dates can now be corrected in place rather than requiring delete-and-recreate; `update()` deliberately never regenerates `certificate_no`/`card_no` or touches `status` (Certificates has no reissue concept at all; ID Cards keeps that as the separate, still-untouched `reissue` action). Certificates cleverly reuse one Vue component and one backend across five sidebar leaves, differentiated purely by an enum `type` column and a client-side route-to-type lookup table. `IdCard` is genuinely polymorphic through the exact same global Laravel morph map used by Library/Attendance/Payroll (not a bespoke mechanism), while `IdCardController` additionally carries its own parallel, non-Eloquent concern (`ResolvesCardHolderType`) purely to validate FK existence before insert — structurally identical to Library's `ResolvesLibraryMemberType` pattern. Both `card_no` and `certificate_no` are deterministic, server-generated sequences (per-year, and for certificates also per-type) and are never user-suppliable, on create or edit. Two of the module's leaves — Library Cards and Transport Cards — are pure print veneers with no backend of their own, reading live data from the Library and Transport modules respectively; a developer should not go looking for `library_cards`/`transport_cards` tables because none exist. All document output — certificates and ID cards alike — is render-on-demand HTML printed via the browser's native `window.print()`, with no PDF generation and no file ever written to storage — the same absence of file-upload infrastructure that means People > Students' Documents tab (`people.md`) can only ever show received/not-received status, never an actual document preview. No import/export, background jobs, notifications, or dedicated reports page exist in this module today; three remaining sidebar leaves (Admit Card Templates, Report Card Templates, Template Builder) are unimplemented Generic placeholders.
