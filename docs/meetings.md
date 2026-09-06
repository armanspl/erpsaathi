# Meetings

See [README.md](README.md) for cross-cutting architecture (routing, permission model, generic-page mechanism, audit logging, calculator-service pattern) — not repeated here.

## 1. Module Overview

Meetings is a scheduling and record-keeping module for three kinds of events — Online Meeting, Parent Teacher Meeting, Broadcast — all stored in a single `meetings` table distinguished only by a `type` enum column. There is no real video-conferencing, calendar, or broadcast-delivery backend anywhere in this module: "Online Meeting" and "Broadcast" are schedule entries with a free-text `meeting_link` string field (e.g. a Zoom/Meet URL an operator pastes in) and a `venue` string; "Meeting Recordings" is likewise just a `recording_url` string field on the same table, not a file upload or media-storage feature. A new developer should not expect any WebRTC/streaming/video code, file storage, or automated invites to exist — this is confirmed by reading `Meeting`'s fillable list, its migration, and every controller in the module (no `Mail::`, `Notification::`, `->notify(`, or job dispatch anywhere).

## 2. Menu Structure

Menu group `Meetings` (`resources/js/erp/data/menu.js`), 5 children. Route base `/meetings/*`, resolved via `FLAGSHIP_ROUTES` in `resources/js/erp/router/index.js`.

| Menu Label | Path | Real / Generic | Vue Component | Note |
|---|---|---|---|---|
| Online Meetings | `/meetings/online-meetings` | Real | `Meetings.vue` | Shares component with Parent Teacher Meeting and Broadcast — one shared list/CRUD page, filtered/pre-typed by route path. |
| Parent Teacher Meeting | `/meetings/parent-teacher-meeting` | Real | `Meetings.vue` | Same component; adds Teacher/Class fields to the form (see §3.1). |
| Broadcast | `/meetings/broadcast` | Real | `Meetings.vue` | Same component. |
| Meeting Reports | `/meetings/meeting-reports` | Real | `MeetingReports.vue` | |
| Meeting Recordings | `/meetings/meeting-recordings` | Real | `MeetingRecordings.vue` | Read-only filtered view of `meetings` where `recording_url` is not null — see honesty note in §1/§15. |

## 3. Pages: URL, Controller, Validation, Permissions

Reads require only an authenticated `erp` guard user. Writes require `erp.permission:meetings.manage` (role key `meetings.manage`, label "Meetings" in `resources/js/erp/pages/settings/Roles.vue`).

### 3.1 Online Meetings / Parent Teacher Meeting / Broadcast (`Meetings.vue`)

- Frontend: `/meetings/online-meetings`, `/meetings/parent-teacher-meeting`, `/meetings/broadcast` — one shared component; `routeTypeMap` in the script maps each path to a fixed `type` value and hides the Type selector on the form when a route type applies (Online Meetings/PTM/Broadcast each lock `type` to their own value; there is no unscoped "all types" route using this component).
- Backend: `GET /erp/api/meetings`, `POST /erp/api/meetings`, `PUT /erp/api/meetings/{meeting}`, `DELETE /erp/api/meetings/{meeting}`
- Controller: `App\Http\Controllers\Erp\Meetings\MeetingController` — `index`, `store`, `update`, `destroy`

`index` supports two optional query filters (both used elsewhere by other pages, not by `Meetings.vue` itself which filters client-side): `type` (exact match) and `has_recording=1` (adds `whereNotNull('recording_url')`, used by Meeting Recordings — see §3.3).

Validation (verbatim, private `validated()` helper, shared by `store` and `update`):
```php
'type' => ['required', Rule::in(['Online Meeting', 'Parent Teacher Meeting', 'Broadcast'])],
'title' => 'required|string|max:255',
'description' => 'nullable|string|max:1000',
'meeting_date' => 'required|date',
'start_time' => 'required|date_format:H:i',
'end_time' => 'nullable|date_format:H:i|after:start_time',
'meeting_link' => 'nullable|string|max:255',
'venue' => 'nullable|string|max:255',
'audience' => ['required', Rule::in(['All', 'Students', 'Teachers', 'Staff', 'Parents'])],
'teacher_id' => 'nullable|exists:teachers,id',
'school_class_id' => 'nullable|exists:school_classes,id',
'status' => ['required', Rule::in(['Scheduled', 'Completed', 'Cancelled'])],
'recording_url' => 'nullable|string|max:255',
```

Form Layout (SlideOver): Type (select, hidden if route locks it), Title (text), Description (textarea), a 3-column row of Date / Start Time / End Time, Meeting Link (text), Venue (text), then conditionally — only rendered when the effective type is `Parent Teacher Meeting` — a Teacher/Class select pair, then Audience + Status side by side, and finally a Recording URL field that only appears once `status === 'Completed'`.

Fields:

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Type | `type` | `meetings` | enum | Required | `required\|in:Online Meeting,Parent Teacher Meeting,Broadcast` | route-locked value, or `Online Meeting` on the unscoped form | fixed select | — |
| Title | `title` | `meetings` | string | Required | `required\|string\|max:255` | — | free text | — |
| Description | `description` | `meetings` | text, nullable | Optional | `nullable\|string\|max:1000` | null | free text | — |
| Date | `meeting_date` | `meetings` | date | Required | `required\|date` | today's date (form default) | date picker | — |
| Start Time | `start_time` | `meetings` | time | Required | `required\|date_format:H:i` | — | time picker | — |
| End Time | `end_time` | `meetings` | time, nullable | Optional | `nullable\|date_format:H:i\|after:start_time` | null | time picker | — |
| Meeting Link | `meeting_link` | `meetings` | string, nullable | Optional | `nullable\|string\|max:255` | null | free text (URL pasted by operator — no validation that it is a real/reachable URL) | — |
| Venue | `venue` | `meetings` | string, nullable | Optional | `nullable\|string\|max:255` | null | free text | — |
| Teacher | `teacher_id` | `meetings` | bigint FK, nullable | Optional (only shown for Parent Teacher Meeting) | `nullable\|exists:teachers,id` | null | `GET /people/teachers` | `teachers.id` |
| Class | `school_class_id` | `meetings` | bigint FK, nullable | Optional (only shown for Parent Teacher Meeting) | `nullable\|exists:school_classes,id` | null | `GET /academics/classes` | `school_classes.id` |
| Audience | `audience` | `meetings` | enum | Required | `required\|in:All,Students,Teachers,Staff,Parents` | `All` | fixed select | — |
| Status | `status` | `meetings` | enum | Required | `required\|in:Scheduled,Completed,Cancelled` | `Scheduled` | fixed select | — |
| Recording URL | `recording_url` | `meetings` | string, nullable | Optional (field only shown when Status = Completed) | `nullable\|string\|max:255` | null | free text (URL pasted by operator — no upload, no validation of reachability) | — |
| Created By | `created_by_id` | `meetings` | bigint FK, nullable | — | not user-settable | authenticated user | — | `erp_users.id` (server-derived) |

### 3.2 Meeting Reports (`MeetingReports.vue`)

- Frontend: `/meetings/meeting-reports`
- Backend: `GET /erp/api/meetings/reports`
- Controller: `App\Http\Controllers\Erp\Meetings\MeetingReportController::index` → `App\Services\MeetingReportCalculator::summary()`

No validation (read-only, computed report). No form.

### 3.3 Meeting Recordings (`MeetingRecordings.vue`)

- Frontend: `/meetings/meeting-recordings`
- Backend: `GET /erp/api/meetings?has_recording=1`
- Controller: `MeetingController::index` (same endpoint as the list page, filtered by the `has_recording` query flag which adds `whereNotNull('recording_url')`)

No validation (read-only). No create/edit form — this page only lists meetings that already have a `recording_url` set (via the Meetings form's Recording URL field once a meeting is marked Completed) and renders a "▶ Watch" link (`<a :href="m.recording_url" target="_blank">`) that opens whatever URL was typed in. **There is no video player, no file upload, and no storage of an actual recording file** — the "recording" is only ever a link an operator manually enters.

## 4–5. Form Layout & Fields

Covered inline per-page in §3 above (single shared form component for the three scheduling leaves; no forms on Reports or Recordings).

## 6. Database Relationships (Eloquent, as coded)

- `Meeting belongsTo Teacher` (`teacher()`, FK `teacher_id`, nullable).
- `Meeting belongsTo SchoolClass` (`schoolClass()`, FK `school_class_id`, nullable).
- `Meeting belongsTo ErpUser` (`createdBy()`, FK `created_by_id`, nullable).
- No inverse `hasMany` relations are declared on `Teacher`/`SchoolClass`/`ErpUser` pointing back to `Meeting` in this module's own files (any such inverse, if present, is defined outside this module's scope).

## 7. Create Flow

`MeetingController::store`:
1. Validate the request (see §3.1 rules).
2. Insert one row into `meetings`: all validated fields plus `created_by_id` (server-derived from `Auth::guard('erp')->id()`), written in a single `Meeting::create([...$data, 'created_by_id' => ...])` call.
3. Return the new meeting with `teacher`, `schoolClass`, `createdBy` eager-loaded.

Single-table insert; no other table is touched, no notification is sent to the invited teacher/class/audience (see §17), and no calendar/ICS file or reminder job is created (see §16).

## 8. Edit Flow

`MeetingController::update`: re-validates the full payload with the same rules as create, then `$meeting->update($data)`. Every field — including `type`, `status`, and `recording_url` — is freely editable after creation; there is no restriction preventing, e.g., changing a Completed meeting's type or re-opening a Cancelled one.

## 9. Delete Flow

`Meeting` does not use the `SoftDeletes` trait — deletion is a **hard delete**. `DELETE /erp/api/meetings/{meeting}` → `MeetingController::destroy` → `$meeting->delete()`, unconditional, no restore path. The migration sets `teacher_id`, `school_class_id`, and `created_by_id` all to `nullOnDelete()` (in the other direction — deleting a `Teacher`, `SchoolClass`, or `ErpUser` nulls out those columns on any meeting rows that reference them, rather than blocking or cascading).

## 10. Business Rules

- **No one-active-meeting-per-X constraint exists.** There is no unique index or validation rule in `meetings` preventing two meetings of the same type, same teacher, same class, or same date/time from being scheduled simultaneously — an operator can double-book a teacher or create overlapping Online Meetings freely. This was checked in both the migration (no unique index beyond the primary key) and the controller (no overlap-checking logic).
- **Type-specific form behavior, not type-specific storage**: all three meeting kinds (Online Meeting, Parent Teacher Meeting, Broadcast) live in the same `meetings` table and go through the same validation rules; the only behavioral difference is that the shared Vue form reveals a Teacher/Class picker exclusively for `type = 'Parent Teacher Meeting'`, and locks/hides the Type selector when reached via one of the three type-specific routes.
- **Recording URL is gated by Status in the UI only, not the backend**: the form only shows the Recording URL input once `status` is set to `Completed`, but the `recording_url` validation rule itself (`nullable|string|max:255`) does not require `status = Completed` — a `Scheduled` meeting could technically be given a recording URL via a direct API call, though the shipped UI never does this.
- **"Online Meeting" has no real video-conferencing backend.** Confirmed by `Meeting`'s fillable list (`meeting_link` is a plain string, no provider/room-id/token fields) and its migration (`$table->string('meeting_link')->nullable()`) — it is a manually-entered URL to an external service (Zoom/Meet/Teams/etc.), not an integration.
- **"Meeting Recordings" has no real file storage.** Confirmed the same way: `recording_url` is a plain nullable string column, and no controller in this module (or `app/Http/Controllers` generally, per grep) writes to Laravel's filesystem/Storage facade for meetings. It is a link, not a hosted file.
- **`upcoming` count in Meeting Reports** (`MeetingReportCalculator::summary()`) = meetings with `status = 'Scheduled'` whose `meeting_date` is not in the past (`! $m->meeting_date->isPast()`), i.e. today or later.

## 11. Import & Export

Per `docs/README.md`'s Import & Export section (Export covers `student, fee, expense, salary, bank, fuel-log, route, marks, global`): **Meetings has neither Import nor Export.** `meetings` does not appear in `ExportController::ENTITIES`, and there is no meetings-specific import route or controller anywhere in `routes/erp_api.php` or `app/Http/Controllers/Erp/ImportExport/`.

## 12. Reports

**Meeting Reports** (`/meetings/meeting-reports` → `MeetingReports.vue`): `GET /erp/api/meetings/reports` → `MeetingReportController::index` → `App\Services\MeetingReportCalculator::summary()`. Computed live from `Meeting::all()` (no filtering, no session-scoping — every meeting ever created, regardless of date):

```php
[
    'total_meetings'  => $meetings->count(),
    'scheduled'       => $meetings->where('status', 'Scheduled')->count(),
    'completed'       => $meetings->where('status', 'Completed')->count(),
    'cancelled'       => $meetings->where('status', 'Cancelled')->count(),
    'with_recording'  => $meetings->whereNotNull('recording_url')->count(),
    'upcoming'        => $meetings->where('status', 'Scheduled')->filter(fn ($m) => ! $m->meeting_date->isPast())->count(),
    'by_type'         => ['Online Meeting' => ..., 'Parent Teacher Meeting' => ..., 'Broadcast' => ...],
]
```
This is not gated by `reports.view` (unlike the cross-module Reports section) — it lives under the `meetings` route group and only requires an authenticated erp user to read.

## 13. APIs

All under `/erp/api/meetings/*`, `erp.auth` middleware applies to the whole group; write endpoints additionally require `erp.permission:meetings.manage`.

| Method | Path | Controller@method | Auth |
|---|---|---|---|
| GET | `/meetings` | `MeetingController@index` | any erp user (optional `?type=` and `?has_recording=1` query filters) |
| GET | `/meetings/reports` | `MeetingReportController@index` | any erp user |
| POST | `/meetings` | `MeetingController@store` | `meetings.manage` |
| PUT | `/meetings/{meeting}` | `MeetingController@update` | `meetings.manage` |
| DELETE | `/meetings/{meeting}` | `MeetingController@destroy` | `meetings.manage` |

Example — `POST /erp/api/meetings` request (Parent Teacher Meeting):
```json
{
  "type": "Parent Teacher Meeting",
  "title": "Term 1 PTM — Grade 5",
  "description": "Discuss mid-term progress",
  "meeting_date": "2026-08-15",
  "start_time": "10:00",
  "end_time": "12:00",
  "meeting_link": "",
  "venue": "Main Hall",
  "teacher_id": 12,
  "school_class_id": 4,
  "audience": "Parents",
  "status": "Scheduled",
  "recording_url": null
}
```
Response (201):
```json
{
  "id": 55,
  "type": "Parent Teacher Meeting",
  "title": "Term 1 PTM — Grade 5",
  "description": "Discuss mid-term progress",
  "meeting_date": "2026-08-15",
  "start_time": "10:00",
  "end_time": "12:00",
  "meeting_link": "",
  "venue": "Main Hall",
  "audience": "Parents",
  "teacher_id": 12,
  "school_class_id": 4,
  "status": "Scheduled",
  "recording_url": null,
  "created_by_id": 9,
  "teacher": { "id": 12, "name": "..." },
  "school_class": { "id": 4, "name": "..." },
  "created_by": { "id": 9, "name": "..." }
}
```

Example — `GET /erp/api/meetings/reports` response:
```json
{
  "total_meetings": 24,
  "scheduled": 10,
  "completed": 12,
  "cancelled": 2,
  "with_recording": 9,
  "upcoming": 6,
  "by_type": { "Online Meeting": 8, "Parent Teacher Meeting": 11, "Broadcast": 5 }
}
```

## 14. Database Tables

| Table | PK | Notable Columns | FKs | Unique / Indexes |
|---|---|---|---|---|
| `meetings` | `id` | `type` enum(`Online Meeting`,`Parent Teacher Meeting`,`Broadcast`), `title`, `description` text, `meeting_date` date, `start_time`/`end_time` time, `meeting_link` string nullable, `venue` string nullable, `audience` enum default `All`, `status` enum default `Scheduled`, `recording_url` string nullable | `teacher_id → teachers.id` nullable (null on delete), `school_class_id → school_classes.id` nullable (null on delete), `created_by_id → erp_users.id` nullable (null on delete) | none beyond PK — no unique constraints |

## 15. File Storage

None. `recording_url` and `meeting_link` are both plain nullable string columns holding operator-entered URLs — no file is ever uploaded, stored on disk, or served through Laravel's filesystem/Storage layer for this module. "Meeting Recordings" is a filtered read of the same `meetings` table, not a media library.

## 16. Background Jobs

None. No queued job, scheduled command, or listener exists for Meetings — `app/Jobs` does not exist in this codebase. Nothing sends reminders, expires stale "Scheduled" meetings, or auto-generates recording links.

## 17. Notifications

None. Confirmed by reading `MeetingController` in full: `store`, `update`, and `destroy` contain no calls to `Mail::`, `Notification::`, `->notify(`, or any event dispatch. Scheduling a meeting — of any type, to any audience — does not email, SMS, or in-app-notify the teacher, class, parents, or anyone else; the `audience` field is stored purely as descriptive metadata with no delivery mechanism behind it. The only user-facing confirmation is a client-side toast (`pushToast`) shown to the operator who performed the action.

## 18. Module Dependencies

- **Depends on**: People/Teacher (`teachers` table, via `teacher_id` — only relevant/shown for Parent Teacher Meeting), Academics/SchoolClass (`school_classes` table, via `school_class_id`, same scope), Account (`erp_users` via `created_by_id`).
- **Feeds**: nothing outside itself — Meeting Reports (`/meetings/reports`) is this module's own report, not part of the cross-module Reports index (`routes/erp_api.php`'s `reports` prefix only covers `students, admissions, fees, finance`); there is no Dashboard widget or other module reading from `meetings` found in this codebase.

## 19. Screenshots

Not available — generated from source code.

## 20. Final Deliverable

Meetings is a single-table module (`meetings`) covering three event kinds — Online Meeting, Parent Teacher Meeting, Broadcast — that share one controller (`MeetingController`), one Vue component (`Meetings.vue`), and one set of validation rules, differentiated only by the `type` enum column and by which optional fields the shared form reveals (Teacher/Class only for Parent Teacher Meeting). Two more sidebar leaves — Meeting Reports and Meeting Recordings — are real, backed pages that read the same table: Reports via a live-computed `MeetingReportCalculator::summary()`, Recordings via a simple `?has_recording=1` filter on the same list endpoint. Critically, there is no real video-conferencing or file-storage capability behind either "Online Meeting" or "Meeting Recordings" — `meeting_link` and `recording_url` are both plain nullable string columns that hold whatever URL an operator manually types in; nothing is hosted, streamed, or uploaded by this application. Deletes are hard deletes with no restore path; there are no soft-deletes, no uniqueness/overlap constraints preventing double-booking, no background jobs, no file storage, and — confirmed by reading every method on `MeetingController` — no notifications of any kind sent to invitees when a meeting is created, edited, or cancelled. Meetings has neither Import nor Export support today.
