# Communication

See [README.md](README.md) for cross-cutting architecture (routing, permission model, generic-page mechanism, audit logging, calculator-service pattern) — not repeated here.

## 1. Module Overview

The Communication module covers outbound-facing school messaging: **Notices** (internal announcements), **Circulars** (same concept, formal/administrative framing), **Events** (school calendar entries), and four broadcast **channels** — SMS, Email, WhatsApp, Push Notifications — plus a cross-channel **Communication Logs** view. All of it is backed by exactly three real database tables/models: `erp_notices` (`ErpNotice`), `events` (`Event`), and `messages` (`Message`).

The most important fact about this module, verified directly from `MessageController::store()`: **composing an SMS/Email/WhatsApp/Push item does not deliver anything.** It creates a `messages` row with a computed `recipient_count` and a hardcoded `status = 'Sent'`. There is no Twilio, SMTP/`Mail::`, WhatsApp Business API, or FCM call anywhere in the controller. See §17 (Notifications) for the full honesty statement.

## 2. Menu Structure

Menu group `Communication` (`resources/js/erp/data/menu.js`), 9 children:

| Sidebar Leaf | Real / Generic | Backend | Frontend Component | Notes |
|---|---|---|---|---|
| Notices | Real | `ErpNoticeController` (`type=Notice`) | `pages/communication/Notices.vue` | Shares component + controller with Circulars, filtered by `type` |
| Gallery | **Generic** | none | `GenericModule.vue` | No route entry in `FLAGSHIP_ROUTES` (`resources/js/erp/router/index.js`) — falls back to the generic mock-data page. No `Gallery` controller/model/table exists anywhere in the backend. |
| Events | Real | `EventController` | `pages/communication/Events.vue` | Also reused verbatim as the Dashboard module's "Upcoming Events" leaf (`/dashboard/upcoming-events` → same `Events.vue`) |
| SMS | Real | `MessageController` (`channel=SMS`) | `pages/communication/Messages.vue` | Shares component + controller + table with Email/WhatsApp/Push/Communication Logs, differentiated by the `channel` column |
| Email | Real | `MessageController` (`channel=Email`) | `pages/communication/Messages.vue` | Same as above |
| WhatsApp | Real | `MessageController` (`channel=WhatsApp`) | `pages/communication/Messages.vue` | Same as above |
| Push Notifications | Real | `MessageController` (`channel=Push`) | `pages/communication/Messages.vue` | Same as above |
| Circulars | Real | `ErpNoticeController` (`type=Circular`) | `pages/communication/Notices.vue` | Shares component + controller + table with Notices, filtered by `type` |
| Communication Logs | Real | `MessageController` (no channel filter) | `pages/communication/Messages.vue` | Same `Messages.vue`, but reached via a route (`/communication/communication-logs`) that isn't in the page's internal `routeChannelMap`, so it shows **all** channels together with a visible Channel column |

**Shared-component confirmation (from `resources/js/erp/router/index.js` `FLAGSHIP_ROUTES`):**
```
'/communication/notices': Notices,
'/communication/circulars': Notices,
'/communication/events': Events,
'/communication/sms': Messages,
'/communication/email': Messages,
'/communication/whatsapp': Messages,
'/communication/push-notifications': Messages,
'/communication/communication-logs': Messages,
```
`Notices.vue` distinguishes Notices vs. Circulars via `route.path` (`routeType` computed: `'/communication/circulars'` → `'Circular'`, else `'Notice'`), filtering the shared `notices` array client-side by `type` and defaulting new records' `type` to match the route. `Messages.vue` does the same for the four channel routes via a `routeChannelMap` object; `Communication Logs` deliberately isn't in that map, so `routeChannel` is `null` there and the page shows every channel with an extra "Channel" column and a channel `<select>` in the compose form.

## 3. Every Page

### Notices
- **Name**: Notices
- **Frontend URL**: `/erp/dashboard/communication/notices`
- **Backend routes**: `GET /erp/api/communication/notices`, `POST /erp/api/communication/notices`, `PUT /erp/api/communication/notices/{notice}`, `DELETE /erp/api/communication/notices/{notice}` (`routes/erp_api.php`, `communication.` route group)
- **Controller**: `App\Http\Controllers\Erp\Communication\ErpNoticeController` — `index()`, `store()`, `update()`, `destroy()`
- **Validation** (verbatim, `ErpNoticeController::validated()`):
  ```php
  'title' => 'required|string|max:255',
  'content' => 'required|string',
  'type' => ['required', Rule::in(['Notice', 'Circular'])],
  'audience' => ['required', Rule::in(['All', 'Students', 'Teachers', 'Staff', 'Parents'])],
  'publish_date' => 'required|date',
  'expiry_date' => 'nullable|date|after_or_equal:publish_date',
  'status' => ['required', Rule::in(['Draft', 'Published'])],
  ```
- **Permissions**: read (`index`) open to any authenticated `erp` guard user; write (`store`/`update`/`destroy`) gated by `erp.permission:communication.manage`, applied at the route-group level in `routes/erp_api.php`.

### Circulars
Identical route/controller/validation to Notices above — only the `type` value (`Circular`) and the Vue route path differ. Not documented twice; see Notices.

### Events
- **Name**: Events
- **Frontend URL**: `/erp/dashboard/communication/events` (also surfaced at `/erp/dashboard/dashboard/upcoming-events`, same component/data)
- **Backend routes**: `GET /erp/api/communication/events`, `POST /erp/api/communication/events`, `PUT /erp/api/communication/events/{event}`, `DELETE /erp/api/communication/events/{event}`
- **Controller**: `App\Http\Controllers\Erp\Communication\EventController` — `index()`, `store()`, `update()`, `destroy()`
- **Validation** (verbatim, `EventController::validated()`):
  ```php
  'title' => 'required|string|max:255',
  'description' => 'nullable|string|max:1000',
  'venue' => 'nullable|string|max:255',
  'event_date' => 'required|date',
  'start_time' => 'nullable|date_format:H:i',
  'end_time' => 'nullable|date_format:H:i|after:start_time',
  'status' => ['required', Rule::in(['Scheduled', 'Cancelled'])],
  ```
- **Permissions**: read open to any authenticated `erp` user; write gated by `erp.permission:communication.manage`.

### SMS / Email / WhatsApp / Push Notifications / Communication Logs
- **Name**: one shared page, `Messages.vue`, reached via 5 different sidebar leaves/routes
- **Frontend URLs**: `/erp/dashboard/communication/sms`, `/communication/email`, `/communication/whatsapp`, `/communication/push-notifications`, `/communication/communication-logs`
- **Backend routes**: `GET /erp/api/communication/messages` (list, optional `?channel=` filter server-side), `POST /erp/api/communication/messages` (compose/"send"), `DELETE /erp/api/communication/messages/{message}`. There is **no** `PUT`/update route for messages — confirmed absent from `routes/erp_api.php` and from `MessageController` (only `index`, `store`, `destroy` exist).
- **Controller**: `App\Http\Controllers\Erp\Communication\MessageController`
- **Validation** (verbatim, `MessageController::store()`):
  ```php
  'channel' => ['required', Rule::in(['SMS', 'Email', 'WhatsApp', 'Push'])],
  'audience' => ['required', Rule::in(['All', 'Students', 'Teachers', 'Staff', 'Parents'])],
  'subject' => 'nullable|string|max:255',
  'body' => 'required|string|max:2000',
  ```
- **Permissions**: read (`index`) open to any authenticated `erp` user (this is how Communication Logs works without its own permission key — it's just an unfiltered read of the same list); write (`store`/`destroy`) gated by `erp.permission:communication.manage`.

## 4. Form Layout

- **Notices/Circulars** (`SlideOver` drawer in `Notices.vue`): Title (text), Content (textarea, 4 rows), Type (`<select>`, hidden and pre-set when arriving via the dedicated Notices/Circulars route, shown only if `routeType` is null), Audience + Status (two-column select row), Publish Date + Expiry Date (two-column date row). Footer: Cancel / Save.
- **Events** (`SlideOver` in `Events.vue`): Title (text), Description (textarea, 3 rows), Venue (text), Date/Start/End (three-column row: date + two time inputs), Status (`<select>`: Scheduled/Cancelled). Footer: Cancel / Save.
- **Messages** (`SlideOver` in `Messages.vue`, titled "Compose {channel}"): Channel (`<select>`, hidden and pre-set when arriving via a channel-specific route, shown only on Communication Logs), Audience (`<select>`), Subject (text, shown only when `form.channel === 'Email'`), Message/body (textarea, 4 rows, required). Footer: Cancel / Send (button label is "Sending…" while in flight, not "Saving…").

## 5. Every Field

### `erp_notices` (Notices & Circulars)
| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Title | `title` | erp_notices | string | Required | max:255 | — | — | — |
| Content | `content` | erp_notices | text | Required | string | — | — | — |
| Type | `type` | erp_notices | enum(`Notice`,`Circular`) | Required | in list | `Notice` (DB default) | hardcoded `['Notice','Circular']` | — |
| Audience | `audience` | erp_notices | enum(`All`,`Students`,`Teachers`,`Staff`,`Parents`) | Required | in list | `All` (DB default) | hardcoded list | — |
| Publish Date | `publish_date` | erp_notices | date | Required | valid date | — | — | — |
| Expiry Date | `expiry_date` | erp_notices | date, nullable | Optional | date, `after_or_equal:publish_date` | null | — | — |
| Status | `status` | erp_notices | enum(`Draft`,`Published`) | Required | in list | `Published` (DB default) | hardcoded list | — |
| Created By | `created_by_id` | erp_notices | unsigned BIGINT, nullable | set server-side from `Auth::guard('erp')->id()`, not user-entered | — | null | — | `erp_users.id`, `nullOnDelete()` |

### `events`
| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Title | `title` | events | string | Required | max:255 | — | — | — |
| Description | `description` | events | text, nullable | Optional | max:1000 | null | — | — |
| Venue | `venue` | events | string, nullable | Optional | max:255 | null | — | — |
| Event Date | `event_date` | events | date | Required | valid date | — | — | — |
| Start Time | `start_time` | events | time, nullable | Optional | `H:i` format | null | — | — |
| End Time | `end_time` | events | time, nullable | Optional | `H:i` format, `after:start_time` | null | — | — |
| Status | `status` | events | enum(`Scheduled`,`Cancelled`) | Required | in list | `Scheduled` (DB default) | hardcoded list | — |

### `messages` (SMS/Email/WhatsApp/Push/Communication Logs)
| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Channel | `channel` | messages | enum(`SMS`,`Email`,`WhatsApp`,`Push`) | Required | in list | — (no DB default; always supplied) | hardcoded list, pre-filled from route | — |
| Audience | `audience` | messages | enum(`All`,`Students`,`Teachers`,`Staff`,`Parents`) | Required | in list | `All` (DB default) | hardcoded list | — |
| Subject | `subject` | messages | string, nullable | Optional (shown only for Email in the UI) | max:255 | null | — | — |
| Body | `body` | messages | text | Required | max:2000 | — | — | — |
| Recipient Count | `recipient_count` | messages | unsigned int | **Not user-entered** — computed server-side in `MessageController::audienceCount()` | — | 0 (DB default) | — | — |
| Status | `status` | messages | enum(`Sent`,`Failed`) | **Not user-entered** — hardcoded `'Sent'` in `store()` | — | `Sent` (DB default) | — | — |
| Sent At | `sent_at` | messages | datetime, nullable | **Not user-entered** — set to `now()` in `store()` | — | null | — | — |
| Sent By | `sent_by_id` | messages | unsigned BIGINT, nullable | set server-side from `Auth::guard('erp')->id()` | — | null | — | `erp_users.id`, `nullOnDelete()` |

## 6. Database Relationships

- `ErpNotice::createdBy()` → `belongsTo(ErpUser::class, 'created_by_id')`
- `Message::sentBy()` → `belongsTo(ErpUser::class, 'sent_by_id')`
- `Event` has **no** Eloquent relationships defined — no `belongsTo`/`hasMany` methods on the `Event` model at all; it is a standalone table with only scalar columns.
- Both `index()` methods that expose a relation eager-load it minimally: `ErpNoticeController::index()` → `ErpNotice::with('createdBy:id,name')`; `MessageController::index()` → `Message::with('sentBy:id,name')`.

## 7. Create Flow

- **Notice/Circular**: `POST /erp/api/communication/notices` → `ErpNoticeController::store()` validates, then `ErpNotice::create([...$data, 'created_by_id' => Auth::guard('erp')->id()])`. Single-table insert into `erp_notices`. No side effects on any other table.
- **Event**: `POST /erp/api/communication/events` → `EventController::store()` validates, then `Event::create($data)`. Single-table insert into `events`.
- **Message ("send")**: `POST /erp/api/communication/messages` → `MessageController::store()` validates `channel`/`audience`/`subject`/`body`, computes `recipient_count` via `audienceCount()` (see §10), then `Message::create([...$data, 'recipient_count' => ..., 'status' => 'Sent', 'sent_at' => now(), 'sent_by_id' => Auth::guard('erp')->id()])`. Single-table insert into `messages`. No row is written or updated in `students`/`teachers`/`staff`/`parents` — `audienceCount()` only reads those tables to produce a number.

## 8. Edit Flow

- **Notice/Circular**: Yes, editable. `Notices.vue` has a full `openEdit()`/`PUT /erp/api/communication/notices/{notice}` flow, re-validated with the same rules as create; any field (including `status`, so a Draft can be Published or vice versa) can change after initial save.
- **Event**: Yes, editable. Same pattern via `PUT /erp/api/communication/events/{event}`.
- **Message**: **No.** There is no `update()` method on `MessageController`, no `PUT` route registered for messages, and the `Messages.vue` page has no edit button/drawer-prefill path — only Compose (create) and delete row actions exist. Once "sent," a message's `channel`, `audience`, `subject`, `body`, `recipient_count`, and `status` are permanently fixed; the only way to change the list is to delete the row.

## 9. Delete Flow

All three models delete via **hard delete** — none of `ErpNotice`, `Event`, or `Message` use the `SoftDeletes` trait (confirmed by reading all three model files; no `use Illuminate\Database\Eloquent\SoftDeletes` import and no `deleted_at` column in any of the three migrations). `destroy()` on each controller calls `$model->delete()` directly, which removes the row permanently, and returns `{"success": true}`.

- No FK constraints point *into* these three tables from elsewhere (no other migration references `erp_notices`, `events`, or `messages`), so there is nothing that cascades off a Communication delete.
- The FK these tables themselves hold (`erp_notices.created_by_id`, `messages.sent_by_id` → `erp_users.id`) is `nullOnDelete()`: deleting an `erp_users` row does not delete the notice/message, it just nulls the attribution column.

## 10. Business Rules

- **`recipient_count` computation** (`MessageController::audienceCount()`, exact match logic):
  ```php
  return match ($audience) {
      'Students' => Student::where('status', 'Active')->count(),
      'Teachers' => Teacher::count(),
      'Staff'    => Staff::count(),
      'Parents'  => ParentGuardian::count(),
      'All'      => Student::where('status', 'Active')->count() + Teacher::count() + Staff::count() + ParentGuardian::count(),
  };
  ```
  Only *active* students are counted (`status = 'Active'`); Teachers/Staff/Parents counts are unfiltered totals of those tables (`teachers`, `staff`, `parents` — via the `ParentGuardian` model). This runs at send time, so `recipient_count` is a snapshot, never recomputed afterward even if the underlying People counts change.
- **Event date/time validation**: `end_time` must be `after:start_time` when both are supplied (both optional, so an event with only a start time or neither is valid). `event_date` has no future/past constraint — past dates are accepted (the UI then labels the row "Completed" client-side, see below).
- **Event "Completed" status is UI-only**: `Events.vue`'s `isPast()`/`eventBadge()` computed logic derives a display badge ("Completed" when `event_date` is in the past and `status !== 'Cancelled'"), but this is never written back to the `status` column in the database — the stored `status` enum only ever holds `Scheduled` or `Cancelled`.
- **Notice `type` gating**: the Notices/Circulars pages restrict the visible list client-side by `type` based on which route was used (`routeType`), but the backend `index()` also accepts an optional `?type=` query filter — both the frontend route-based filter and an ad hoc backend filter exist for the same purpose.

## 11. Import & Export

Per `README.md` §"Import & Export," only Student has a real importer, and Export covers 9 fixed entities (`student, fee, expense, salary, bank, fuel-log, route, marks`, plus the combined `global` workbook). **Communication has no Import or Export capability of any kind** — no notice/event/message entity appears in `ExportController`'s exportable list, and there is no import path for any Communication table. Any Import/Export-looking UI a user encounters on a Communication page is the generic mock simulation described in README §"Generic (placeholder) pages," not present on the three real pages here (Notices/Events/Messages have no Import/Export buttons in their templates at all).

## 12. Reports

The module's only reporting surface on itself is **Communication Logs** — the unfiltered, all-channel view of `Messages.vue` (see §2/§3). It is a live list (not an aggregate/summary report): every `messages` row across all four channels, with a "Messages Sent" stat card (`filteredMessages.length`) and a "Recipients Reached" stat card (`sum of recipient_count` over the currently filtered rows, computed client-side in `totalRecipients`). There is no backend aggregation endpoint dedicated to Communication Logs — it reuses `GET /erp/api/communication/messages` with no `channel` filter and does the counting in the Vue component. No Calculator service exists for Communication.

## 13. APIs

| Method | Endpoint | Controller@method | Auth |
|---|---|---|---|
| GET | `/erp/api/communication/notices` | `ErpNoticeController@index` | any `erp` user |
| POST | `/erp/api/communication/notices` | `ErpNoticeController@store` | `communication.manage` |
| PUT | `/erp/api/communication/notices/{notice}` | `ErpNoticeController@update` | `communication.manage` |
| DELETE | `/erp/api/communication/notices/{notice}` | `ErpNoticeController@destroy` | `communication.manage` |
| GET | `/erp/api/communication/events` | `EventController@index` | any `erp` user |
| POST | `/erp/api/communication/events` | `EventController@store` | `communication.manage` |
| PUT | `/erp/api/communication/events/{event}` | `EventController@update` | `communication.manage` |
| DELETE | `/erp/api/communication/events/{event}` | `EventController@destroy` | `communication.manage` |
| GET | `/erp/api/communication/messages` | `MessageController@index` | any `erp` user |
| POST | `/erp/api/communication/messages` | `MessageController@store` | `communication.manage` |
| DELETE | `/erp/api/communication/messages/{message}` | `MessageController@destroy` | `communication.manage` |

Example — `POST /erp/api/communication/messages`:
```json
// Request
{
  "channel": "SMS",
  "audience": "Students",
  "subject": null,
  "body": "School closed tomorrow due to weather."
}

// Response 201
{
  "id": 42,
  "channel": "SMS",
  "audience": "Students",
  "subject": null,
  "body": "School closed tomorrow due to weather.",
  "recipient_count": 612,
  "status": "Sent",
  "sent_at": "2026-07-31T10:15:00.000000Z",
  "sent_by_id": 3,
  "created_at": "...",
  "updated_at": "...",
  "sent_by": { "id": 3, "name": "Admin User" }
}
```

Example — `GET /erp/api/communication/notices`:
```json
[
  {
    "id": 7,
    "title": "PTM Rescheduled",
    "content": "The Parent Teacher Meeting has been moved to Friday.",
    "type": "Notice",
    "audience": "Parents",
    "publish_date": "2026-07-25",
    "expiry_date": "2026-08-05",
    "status": "Published",
    "created_by_id": 3,
    "created_at": "...",
    "updated_at": "...",
    "created_by": { "id": 3, "name": "Admin User" }
  }
]
```

## 14. Database Tables

### `erp_notices`
| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK, auto-increment | |
| `title` | string | |
| `content` | text | |
| `type` | enum(`Notice`,`Circular`) | default `Notice` |
| `audience` | enum(`All`,`Students`,`Teachers`,`Staff`,`Parents`) | default `All` |
| `publish_date` | date | |
| `expiry_date` | date, nullable | |
| `status` | enum(`Draft`,`Published`) | default `Published` |
| `created_by_id` | FK → `erp_users.id`, nullable, `nullOnDelete()` | |
| `created_at`/`updated_at` | timestamps | |

No unique constraints beyond PK; no explicit secondary indexes defined in the migration.

**Naming note**: this table is named `erp_notices` (model `ErpNotice`), not `notices`, specifically because a `notices` table **already existed** for the pre-existing public marketing website before the ERP was built — see `database/migrations/2026_03_12_000001_create_notice_tables.php` (creates `notices` + `notice_tabs`) and the later `2026_03_12_000002_add_event_fields_to_notices_table.php`, which adds `image`, `event_date`, `event_time`, `event_location`, `event_target_audience` to that *public-site* table. The public `Notice` model (`app/Models/Notice.php`) belongs to `notice_tabs` via `notice_tab_id` and exposes static helpers like `getActiveNotices()`/`getEventNotices()` used by the public homepage — it is entirely unrelated to ERP Communication and shares no code or data with `ErpNotice`. The `Erp` prefix on the model/table (matching the same convention used for `ErpFeeStructure`, `ErpRole`, `ErpUser` per README.md) exists purely to avoid this name collision.

### `events`
| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK, auto-increment | |
| `title` | string | |
| `description` | text, nullable | |
| `venue` | string, nullable | |
| `event_date` | date | |
| `start_time` | time, nullable | |
| `end_time` | time, nullable | |
| `status` | enum(`Scheduled`,`Cancelled`) | default `Scheduled` |
| `created_at`/`updated_at` | timestamps | |

No FKs, no unique constraints, no secondary indexes.

### `messages`
| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK, auto-increment | |
| `channel` | enum(`SMS`,`Email`,`WhatsApp`,`Push`) | |
| `audience` | enum(`All`,`Students`,`Teachers`,`Staff`,`Parents`) | default `All` |
| `subject` | string, nullable | |
| `body` | text | |
| `recipient_count` | unsigned int | default 0 |
| `status` | enum(`Sent`,`Failed`) | default `Sent` (note: nothing in the codebase ever sets `Failed` — see §17) |
| `sent_at` | datetime, nullable | |
| `sent_by_id` | FK → `erp_users.id`, nullable, `nullOnDelete()` | |
| `created_at`/`updated_at` | timestamps | |

No unique constraints beyond PK; no secondary indexes defined.

## 15. File Storage

None. No field on any of the three models is a file/upload (no image/attachment column on `erp_notices`, `events`, or `messages`), and none of the three controllers reference `Storage::` or handle `UploadedFile`. Notices/Circulars/Events/Messages are text-only.

## 16. Background Jobs

None. There is no queued job, scheduled command, or listener anywhere in the codebase that sends a digest, retries a "Failed" message, or does anything asynchronous with Communication data. `MessageController::store()` runs entirely synchronously within the HTTP request and returns immediately with `status: 'Sent'`. No `app/Jobs/*` class references `Message`, `Event`, or `ErpNotice`.

## 17. Notifications

**This module's four "channel" leaves (SMS, Email, WhatsApp, Push Notifications) do not deliver anything.** Read in full, `MessageController::store()` is:
```php
public function store(Request $request)
{
    $data = $request->validate([...]);

    $message = Message::create([
        ...$data,
        'recipient_count' => $this->audienceCount($data['audience']),
        'status' => 'Sent',
        'sent_at' => now(),
        'sent_by_id' => Auth::guard('erp')->id(),
    ]);

    return response()->json($message->load('sentBy:id,name'), 201);
}
```
That is the entire method. There is:
- no `Mail::` / `Notification::` / mailable call (no SMTP dispatch for "Email"),
- no HTTP call to Twilio or any SMS gateway (for "SMS"),
- no call to the WhatsApp Business API (for "WhatsApp"),
- no call to FCM or any push service (for "Push"),
- no queued job dispatched at all.

The only thing that happens is: recipient count is computed by counting rows in `students`/`teachers`/`staff`/`parents` (§10), a `messages` row is inserted with `status` hardcoded to the literal string `'Sent'`, and that row is returned to the UI, which shows a "sent" toast. The `status` enum does include a `Failed` value at the database level, but nothing in the codebase ever writes it — there is no failure path, retry, or delivery confirmation of any kind. In short: composing a message here is indistinguishable, from the database's point of view, from actually sending one, but no message ever leaves the server.

## 18. Module Dependencies

- **People**: `MessageController::audienceCount()` reads `Student` (`students`, filtered `status = 'Active'`), `Teacher` (`teachers`), `Staff` (`staff`), `ParentGuardian` (`parents`) purely to produce a recipient count — no FK, no join, no write-back to any People table.
- **Settings**: `communication.manage` permission key is defined in `resources/js/erp/pages/settings/Roles.vue`'s `availablePermissions` and enforced by `erp.permission` middleware, whose lookup goes through `erp_roles` (Settings module).
- **System**: every create/update/delete on `ErpNotice`/`Event`/`Message` is captured by the global audit-log listener (README §"Audit logging") into `audit_logs`, with no explicit code in this module.
- No dependency on Fee/Finance/Academics/Exam/Transport/Library/Hostel/Inventory — Communication is otherwise self-contained.

## 19. Screenshots

Not available — generated from source code.

## 20. Final Deliverable

The Communication module is three genuinely small, independent Eloquent resources — `ErpNotice`, `Event`, `Message` — wrapped by three thin CRUD-style controllers and presented through two shared Vue components (`Notices.vue` doubling as Circulars; `Messages.vue` doubling as SMS/Email/WhatsApp/Push/Communication Logs) plus one purely generic leaf (Gallery). Notices and Events support full create/edit/delete against `erp_notices`/`events`; Messages support create ("compose/send") and delete only, with no edit path. The single fact every developer touching this module must internalize: none of the four messaging channels perform real delivery — `MessageController::store()` only persists a `messages` row with a computed `recipient_count` and a hardcoded `status = 'Sent'`, with zero external gateway integration (no SMTP, Twilio, WhatsApp Business API, or FCM calls), and no background job exists to change that later. `erp_notices` is deliberately `Erp`-prefixed to avoid colliding with the pre-existing public-site `notices` table, which is unrelated and still in active use by the marketing homepage.
