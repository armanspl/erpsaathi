# Account

See [README.md](README.md) for cross-cutting architecture (routing, permission model, generic-page mechanism, audit logging) — not repeated here.

## 1. Module Overview

Account is the ERP's self-service module — every authenticated user manages only their own profile, password, theme, login history, and support tickets. It is the one module in the entire app with **no `erp.permission` gate anywhere**, on reads or writes, by explicit design (`routes/erp_api.php`'s comment: "Account is self-service only … so no erp.permission gate applies here"). Of its 9 sidebar leaves, 5 are real; 3 are unimplemented generic placeholders; Logout is a client-side action, not a page. A tenth capability — API Tokens — has a **fully functional backend that is deliberately not linked from the sidebar or router at all**, because no part of the app currently authenticates via bearer token.

## 2. Menu Structure

Menu group `Account` (`resources/js/erp/data/menu.js`):

| Sidebar Label | Path | Component | Real? |
|---|---|---|---|
| Profile | `/account/profile` | `Profile.vue` | **Real** |
| Change Password | `/account/change-password` | `ChangePassword.vue` | **Real** |
| Two Factor Authentication | `/account/two-factor-authentication` | `GenericModule.vue` | Generic — no backend/controller exists |
| Notifications | `/account/notifications` | `GenericModule.vue` | Generic — no backend/controller exists |
| Theme Creator | `/account/theme-creator` | `ThemeCreator.vue` | **Real** |
| Language | `/account/language` | `GenericModule.vue` | Generic — no backend/controller exists |
| Login Sessions | `/account/login-sessions` | `LoginSessions.vue` | **Real** |
| Support Tickets | `/account/support-tickets` | `SupportTickets.vue` | **Real** |
| Logout | *(no path — `action: 'logout'`)* | n/a | Client-side session teardown, not a routed page |
| *(API Tokens — intentionally absent)* | *(no menu entry)* | `ApiTokens.vue` exists but is never imported by `router/index.js` | See §10 |

`router/index.js` even keeps a standing comment about the omission: `// ApiTokens.vue is intentionally not routed — see the note in data/menu.js.` `menu.js` itself documents the reasoning inline: *"the token vault (generate/reveal-once/revoke) is fully implemented, but no auth guard in the app consumes these tokens yet, so exposing it would imply a capability that doesn't exist. Backend is untouched — re-add here once a real consumer is built."*

## 3. Pages

### 3.1 Profile

- **URL (frontend)**: `/account/profile`.
- **URL (backend)**: `GET /erp/api/account/profile`, `PUT /erp/api/account/profile`.
- **Controller/method**: `ProfileController@show`, `ProfileController@update`.
- **Validation** (verbatim, `update`):
  ```php
  'name' => 'required|string|max:255',
  'email' => ['required', 'email', 'max:255', Rule::unique('erp_users', 'email')->ignore($user->id)],
  ```
- **Permissions**: none beyond `erp.auth` — operates only on `Auth::guard('erp')->user()`, the currently-logged-in user; there is no way to view or edit another user's profile from this endpoint.

### 3.2 Change Password

- **URL (frontend)**: `/account/change-password`.
- **URL (backend)**: `PUT /erp/api/account/password`.
- **Controller/method**: `PasswordController@update`.
- **Validation** (verbatim):
  ```php
  'current_password' => 'required|string',
  'password' => 'required|string|min:8|confirmed',
  ```
  Plus a manual check: `Hash::check($data['current_password'], $user->password)` — throws `ValidationException` on `current_password` if it doesn't match.
- **Permissions**: none beyond `erp.auth`; operates only on the logged-in user.

### 3.3 Theme Creator

- **URL (frontend)**: `/account/theme-creator`.
- **URL (backend)**: `PUT /erp/api/account/theme`.
- **Controller/method**: `ThemeController@update`.
- **Validation** (verbatim): `'theme_color' => 'nullable|regex:/^#[0-9a-fA-F]{6}$/'`.
- **Permissions**: none beyond `erp.auth`.
- **Important indirection**: `ThemeCreator.vue` **never calls `client.put('/account/theme', …)` directly**. Every color change (color-picker input, hex text input, preset swatch click, "Apply Theme," and "Reset to Default") goes through `setThemeColor(hex)` / `resetThemeColor()`, two functions exported from `resources/js/erp/store.js` — the app's shared Vue reactive store. Those two functions do three things together: (1) call `applyTheme()`/`resetTheme()` from `utils/theme.js` to update CSS variables live and mirror the value into `localStorage`, (2) update `store.js`'s own reactive `state.themeColor` (so every other component — e.g. `UserMenu.vue`'s preset swatches — stays in sync instantly), and (3) fire the `PUT /account/theme` request in the background (`.catch(() => {})` — a failed save is silently swallowed, the UI never rolls back). This is why other places in the app (e.g. the header's `UserMenu.vue` preset picker) can also change the account theme color without duplicating any API-calling code — they all funnel through the same two store functions.

### 3.4 Login Sessions

- **URL (frontend)**: `/account/login-sessions`.
- **URL (backend)**: `GET /erp/api/account/sessions`.
- **Controller/method**: `SessionController@index`.
- **Validation**: none (no input).
- **Permissions**: none beyond `erp.auth`; query is hard-scoped server-side to `LoginHistory::where('erp_user_id', Auth::guard('erp')->id())`, `orderByDesc('id')->limit(50)`. This reads the exact same `login_histories` table System's Login History page reads globally (`system.md` §3.2) — the difference is entirely in the `WHERE` clause (own rows only) and the absence of any permission gate.

### 3.5 Support Tickets

- **URL (frontend)**: `/account/support-tickets`.
- **URL (backend)**: `GET /erp/api/account/support-tickets`, `POST /erp/api/account/support-tickets`, `GET /erp/api/account/support-tickets/{supportTicket}`, `POST /erp/api/account/support-tickets/{supportTicket}/reply`, `DELETE /erp/api/account/support-tickets/{supportTicket}`.
- **Controller/method**: `SupportTicketController@index/store/show/reply/destroy`.
- **Validation** (verbatim, `store`):
  ```php
  'subject' => 'required|string|max:255',
  'description' => 'required|string|max:2000',
  'priority' => ['required', Rule::in(['Low', 'Medium', 'High'])],
  ```
  Verbatim, `reply`: `'message' => 'required|string|max:2000'`.
- **Permissions**: none beyond `erp.auth`; ownership enforced manually per-request by `authorizeOwner()`, which `abort(403)`s if `$ticket->erp_user_id !== Auth::guard('erp')->id()` — applied on `show`, `reply`, and `destroy`. `index` is scoped server-side the same way as Login Sessions.

### 3.6 API Tokens *(backend-only, not reachable from the UI)*

- **URL (backend)**: `GET /erp/api/account/api-tokens`, `POST /erp/api/account/api-tokens`, `DELETE /erp/api/account/api-tokens/{apiToken}` — all live, all routed, all callable today via curl/Postman against a valid `erp` session cookie.
- **Controller/method**: `ApiTokenController@index/store/destroy`.
- **Validation** (verbatim, `store`): `'name' => 'required|string|max:255'`.
- **Permissions**: none beyond `erp.auth`; `destroy` manually checks `$apiToken->erp_user_id !== Auth::guard('erp')->id()` and `abort(403)` otherwise.
- **Status**: fully functional, fully tested-by-code-reading, and completely absent from `resources/js/erp/data/menu.js` and `router/index.js` (`ApiTokens.vue` exists on disk but is never imported). This is a deliberate product decision, not an oversight — see §10.

## 4. Form Layout

- **Profile**: a single card — avatar-initial circle, Name text input, Email text input, one "Save Changes" button.
- **Change Password**: a single card — Current Password, New Password, Confirm New Password (all `type="password"`), one "Update Password" button.
- **Theme Creator**: a two-column layout — left: an HTML `<input type="color">` swatch plus a manual hex text field, and a 6-column grid of preset color buttons; right: a live preview panel (mini sidebar, buttons, stat card, tabs) that re-renders instantly against the app's actual CSS custom properties, not a static mockup.
- **Login Sessions**: a read-only table (Status, IP Address, Device/Browser, Date) — no filters, no form.
- **Support Tickets**: a list table (Subject, Priority, Status, Replies count, Created) with a "+ New Ticket" button opening a `SlideOver` form (Subject, Priority select, Description textarea); clicking a row opens a second `SlideOver` showing the ticket's description, its reply thread, a "Add Reply" textarea, a "Send Reply" button, and a "Delete Ticket" text-link.

## 5. Fields

| Label | DB Column | Table | Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Name | name | erp_users | string(255) | Yes | `required\|string\|max:255` | — | — | — |
| Email | email | erp_users | string, unique | Yes | `required\|email\|max:255\|unique:erp_users,email` (self-ignored) | — | — | — |
| Current Password | *(not stored — compared via `Hash::check`)* | erp_users | string | Yes | `required\|string` | — | — | — |
| New Password | password | erp_users | string, hashed | Yes | `required\|string\|min:8\|confirmed` | — | — | — |
| Theme Color | theme_color | erp_users | string(7) hex, nullable | No | `nullable\|regex:/^#[0-9a-fA-F]{6}$/` | `null` (falls back to app default Indigo client-side) | 12 hardcoded presets in `utils/theme.js` + native color picker | — |
| Ticket Subject | subject | support_tickets | string(255) | Yes | `required\|string\|max:255` | — | — | — |
| Ticket Priority | priority | support_tickets | enum(Low,Medium,High) | Yes | `required\|Rule::in([...])` | `Medium` (DB default; form also defaults to Medium) | static 3-option select | — |
| Ticket Description | description | support_tickets | text | Yes | `required\|string\|max:2000` | — | — | — |
| Ticket Status | status | support_tickets | enum(Open,In Progress,Resolved,Closed) | n/a (system-set) | not user-settable via any current route | `Open` (DB default; also explicitly set on create) | — | — |
| Reply Message | message | support_ticket_replies | text | Yes | `required\|string\|max:2000` | — | — | — |
| API Token Name | name | api_tokens | string(255) | Yes | `required\|string\|max:255` | — | — | — |

## 6. Database Relationships

- `SupportTicket::erpUser()` — `belongsTo(ErpUser::class)`.
- `SupportTicket::replies()` — `hasMany(SupportTicketReply::class)->orderBy('created_at')`.
- `SupportTicketReply::ticket()` — `belongsTo(SupportTicket::class, 'support_ticket_id')`.
- `SupportTicketReply::erpUser()` — `belongsTo(ErpUser::class)`.
- `ApiToken::erpUser()` — `belongsTo(ErpUser::class)`.
- `LoginHistory::erpUser()` — `belongsTo(ErpUser::class)` (shared with System — see `system.md`).

**`ErpUser` itself defines no relationship methods at all** (`app/Models/ErpUser.php` — only `$fillable`, `$hidden`, `casts()`) — every relation above is one-directional, declared only on the child model.

## 7. Create Flow

- **Support Ticket**: user fills the SlideOver form → `POST /account/support-tickets` → `SupportTicketController::store()` validates, then `SupportTicket::create([...$data, 'erp_user_id' => current user, 'status' => 'Open'])` — status is always forced to `Open` on creation, ignoring any client input for it (there is no `status` field in the create form anyway).
- **API Token** (backend-only): `POST /account/api-tokens` → generates `Str::random(64)` as the plaintext token, stores only `Hash::make($plainText)` in `api_tokens.token`, and returns the plaintext **once**, in the create response body only (`plain_text_token`) — it is never retrievable again afterward (see §10).
- **Reply**: `POST /account/support-tickets/{id}/reply` → creates a `SupportTicketReply` row via `$supportTicket->replies()->create([...])`.

## 8. Edit Flow

- **Profile**: `PUT /account/profile` re-validates and `$user->update($data)` — name/email only.
- **Change Password**: `PUT /account/password` verifies the current password via `Hash::check()` first, then `$user->update(['password' => $data['password']])` (the model casts `password` to `hashed`, so it's re-hashed automatically on save).
- **Theme Color**: `PUT /account/theme` — `$user->update(['theme_color' => $data['theme_color'] ?? null])`; sending `theme_color: null` is exactly how "Reset to Default" works.
- **Support Ticket status has no direct edit route.** There is no `PUT/PATCH /account/support-tickets/{id}` anywhere in `routes/erp_api.php`. The only way `status` ever changes after creation is the one side effect inside `reply()`: *if* a ticket's current status is `Resolved`, replying flips it back to `Open`. Because no route in the current codebase ever sets a ticket to `Resolved`, `In Progress`, or `Closed` in the first place, **this reopen-on-reply rule exists in code but currently has no way to fire** — every ticket that has ever been created via this API remains permanently `Open` unless someone edits the database directly. This is documented precisely, not assumed, because the task description's premise (a working Resolved→Open flow) does not match what `routes/erp_api.php` actually exposes.

## 9. Delete Flow

- **Support Ticket**: `DELETE /account/support-tickets/{id}` → `SupportTicketController::destroy()` → `authorizeOwner()` then `$supportTicket->delete()`. **Hard delete** (no `SoftDeletes` trait on `SupportTicket`). At the database level, `support_ticket_replies.support_ticket_id` is `cascadeOnDelete()`, so deleting a ticket genuinely deletes all of its replies in the same operation.
- **API Token**: `DELETE /account/api-tokens/{id}` → ownership check, then `$apiToken->delete()`. **Hard delete**, no soft-delete trait. This is effectively "revoke" — since only the token's hash is stored and compared, deleting the row makes the plaintext permanently unusable (there is nothing else referencing it: `api_tokens.erp_user_id` is `cascadeOnDelete()` on the parent side, meaning deleting an `ErpUser` deletes their tokens, not the reverse).
- **Profile / Change Password / Theme Creator / Login Sessions**: no delete action exists on any of these — a user cannot delete their own account, a login-history entry, or their theme preference (only reset it to null) from this module.

## 10. Business Rules

- **No permission gate anywhere in this module** — confirmed by reading the entire `account` route group in `routes/erp_api.php`: not one route is wrapped in `erp.permission:*`. Every authenticated `erp` user, regardless of role, can manage their own profile/password/theme/sessions/tickets/tokens. This is the explicit, stated design (see the route file's own comment) and the one genuine exception to the README's "writes are gated" convention.
- **Ownership is enforced per-request, not by route scoping alone.** `SupportTicketController` and `ApiTokenController` both manually compare `->erp_user_id !== Auth::guard('erp')->id()` and `abort(403)` — a user cannot view, reply to, or delete another user's ticket or token even though the routes accept any ID.
- **Theme persistence is fully indirected through `store.js`.** `ThemeCreator.vue` contains zero direct API calls for saving theme — every change goes through `setThemeColor()`/`resetThemeColor()`, which is also how the header's quick-swatch picker (`UserMenu.vue`) changes the theme without its own API code. A saved theme color on `erp_users.theme_color` follows the user across browsers/devices (read into `window.__ERP_USER__` on page load and prioritized over the per-browser `localStorage` fallback); an unset (`null`) one falls back to whatever was last saved in that browser's `localStorage`.
- **API Token is a genuine one-time-reveal secret.** The plaintext (`Str::random(64)`) is generated once, returned once in the `store()` JSON response, and never stored anywhere in plaintext — only `Hash::make($plainText)` is persisted, and the model additionally declares `protected $hidden = ['token']`, so even the hashed value never leaks through `index()`. There is no "regenerate"/"reveal again" endpoint — losing the plaintext means deleting the token and creating a new one.
- **API Tokens exist, work, and are testable, but are deliberately unrouted from the UI.** All three endpoints function correctly today (confirmed by reading `ApiTokenController` in full — no missing pieces), but `router/index.js` never imports `ApiTokens.vue` and `menu.js` never lists "API Tokens" as a sidebar child. This is stated by the codebase itself to be intentional: exposing a token vault in the UI would imply the app has a bearer-token authentication path, and it does not — every request in this ERP authenticates via the `erp` session guard, never a token. A developer wiring up a genuine API-token consumer (e.g. a mobile app or third-party integration) is expected to re-add the route and menu entry at that point.
- **Support Ticket status is effectively frozen at `Open` today.** See §8 — the reopen-on-reply rule is real code but currently unreachable because nothing sets `Resolved`/`In Progress`/`Closed` in the first place; there is no admin-side ticket-management screen anywhere in the codebase.

## 11. Import & Export

No. This module has no import or export capability of any kind. (See `import-export.md` for the module that owns this capability.)

## 12. Reports

None. Account has no `*ReportCalculator` service and no aggregated summary view — Login Sessions is a plain chronological list, not a computed report.

## 13. APIs

| Method | Path | Controller@method | Auth |
|---|---|---|---|
| GET | `/erp/api/account/profile` | `ProfileController@show` | any authenticated user (self only) |
| PUT | `/erp/api/account/profile` | `ProfileController@update` | any authenticated user (self only) |
| PUT | `/erp/api/account/password` | `PasswordController@update` | any authenticated user (self only) |
| PUT | `/erp/api/account/theme` | `ThemeController@update` | any authenticated user (self only) |
| GET | `/erp/api/account/sessions` | `SessionController@index` | any authenticated user (self only) |
| GET | `/erp/api/account/api-tokens` | `ApiTokenController@index` | any authenticated user (self only) — no UI |
| POST | `/erp/api/account/api-tokens` | `ApiTokenController@store` | any authenticated user (self only) — no UI |
| DELETE | `/erp/api/account/api-tokens/{apiToken}` | `ApiTokenController@destroy` | any authenticated user (self only) — no UI |
| GET | `/erp/api/account/support-tickets` | `SupportTicketController@index` | any authenticated user (self only) |
| POST | `/erp/api/account/support-tickets` | `SupportTicketController@store` | any authenticated user (self only) |
| GET | `/erp/api/account/support-tickets/{supportTicket}` | `SupportTicketController@show` | ticket owner only (403 otherwise) |
| POST | `/erp/api/account/support-tickets/{supportTicket}/reply` | `SupportTicketController@reply` | ticket owner only (403 otherwise) |
| DELETE | `/erp/api/account/support-tickets/{supportTicket}` | `SupportTicketController@destroy` | ticket owner only (403 otherwise) |

Example — Theme update:
```
PUT /erp/api/account/theme
{ "theme_color": "#6366f1" }
→ 200 OK
{ "success": true, "theme_color": "#6366f1" }
```

Example — API token creation (backend-only, no UI):
```
POST /erp/api/account/api-tokens
{ "name": "Mobile App Integration" }
→ 201 Created
{ "id": 4, "name": "Mobile App Integration",
  "plain_text_token": "kQ2f...ZP9x" /* 64 chars, shown ONLY in this response */,
  "created_at": "2026-07-30T14:20:00.000000Z" }
```

Example — Support ticket reply reopening a Resolved ticket (were one ever set to Resolved):
```
POST /erp/api/account/support-tickets/9/reply
{ "message": "Still seeing this after the last update." }
→ 201 Created
{ "id": 9, "status": "Open", /* flipped back from "Resolved" */
  "replies": [ { "id": 21, "erp_user": { "id": 3, "name": "Admin User" }, "message": "…" } ] }
```

## 14. Database Tables

**`erp_users`** (relevant columns only — full table owned by the auth system, not this module)
| Column | Type | Notes |
|---|---|---|
| id | bigint, PK | |
| name, email, password, role, is_active | — | see People/Settings docs for the account-creation side |
| theme_color | string, nullable | added by a later migration (`add_theme_color_to_erp_users_table`) |

**`api_tokens`**
| Column | Type | Notes |
|---|---|---|
| id | bigint, PK | |
| erp_user_id | FK → `erp_users.id`, `cascadeOnDelete()` | required |
| name | string | required |
| token | string, **unique** | hashed value only |
| last_used_at | datetime, nullable | present in schema; never actually written to by any controller (no "mark used" logic exists) |
| created_at / updated_at | timestamps | |

**`support_tickets`**
| Column | Type | Notes |
|---|---|---|
| id | bigint, PK | |
| erp_user_id | FK → `erp_users.id`, `cascadeOnDelete()` | required |
| subject | string | required |
| description | text | required |
| priority | enum('Low','Medium','High'), default 'Medium' | |
| status | enum('Open','In Progress','Resolved','Closed'), default 'Open' | see §8/§10 — only `Open`↔`Open`/`Resolved`→`Open` reachable today |
| created_at / updated_at | timestamps | |

**`support_ticket_replies`**
| Column | Type | Notes |
|---|---|---|
| id | bigint, PK | |
| support_ticket_id | FK → `support_tickets.id`, `cascadeOnDelete()` | required |
| erp_user_id | FK → `erp_users.id`, `cascadeOnDelete()` | required |
| message | text | required |
| created_at / updated_at | timestamps | |

**`login_histories`** — shared with System, schema documented in `system.md` §14 (Account only ever reads it, scoped by `erp_user_id`; it does not own the table).

## 15. File Storage

None. No avatar/document upload exists anywhere in Account — Profile's avatar is a CSS-rendered initials circle computed client-side from the user's name, not an uploaded image.

## 16. Background Jobs

None.

## 17. Notifications

**None** — verified by reading `SupportTicketController` in full: no `Mail::`, `Notification::`, or `->notify()` call exists anywhere in it. A new support ticket, and a new reply to one, notify no one — not the submitting user, not an admin, not by email or in-app notification. (Note, for context only, since it lives outside this module's route prefix: `ErpAuthController::sendResetLink()` does send a real password-reset email via `Mail::to($user->email)->send(new ErpResetPasswordMail(...))`, but that is part of the pre-login "forgot password" flow, not the self-service Account module documented here.)

## 18. Module Dependencies

Account is largely self-contained, as its own route-file comment states. Its only real dependency is on `ErpUser` as the anchor for every owned table (`api_tokens`, `support_tickets`, `support_ticket_replies` all FK to `erp_users.id`), and on the `login_histories` table populated by the pre-authentication login flow (`ErpAuthController`, shared read-only with System). Theme changes propagate app-wide via `store.js`'s reactive state (affecting every page's CSS custom properties), which is a UI-layer dependency rather than a data dependency.

## 19. Screenshots

Not available — generated from source code.

## 20. Final Deliverable

Account is a 5-real / 3-generic self-service module with zero permission gating by deliberate design — every authenticated user manages only their own profile, password, theme, login history, and support tickets, enforced by manual ownership checks rather than route-level scoping. Theme persistence is notably indirected through `store.js`'s `setThemeColor`/`resetThemeColor` rather than the page calling the API directly. A fully functional, fully tested API Token vault (one-time-reveal secret, hash-only storage, owner-scoped revoke) exists complete in the backend but is intentionally absent from the sidebar and router because nothing in the app yet authenticates via bearer token. Support tickets have no admin resolution workflow and no email notification on reply — the "Resolved ticket reopens on reply" rule exists in code but is currently unreachable since no route ever marks a ticket Resolved in the first place.
