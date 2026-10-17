# Title: Student Note Sharing Portal — Auth, Roles, and Route Policy

Status: Accepted (D-005, D-006)
Last updated: 2026-10-18

## Model

- Email + password (bcrypt via `password_hash()`); min 8 chars; no other provider.
- Single college: registration stamps `institution_id = 1`; emails not domain-locked (open choice, institution seeded).
- Roles: `student` (default) / `moderator` / `admin`. Public visitors are modelled by "no session".

## Guards (all server-side; UI only hides links)

| Guard                | Effect                                                    |
| -------------------- | ----------------------------------------------------------- |
| `require_login()`    | redirect anonymous to login.php                             |
| `require_role(...)`  | additionally 403 for wrong role                             |
| `can_moderate()`     | true only for moderator/admin — used for moderation panels  |
| per-row checks       | owner-visibility on note/draft; moderation report-inbox access |

Sessions: PHP native; `httponly` + `SameSite=Lax`; `session_regenerate_id(true)`
on every successful login; `logout_user()` destroys data + cookie. Cookie not
marked `secure` because the accepted demo environment is `http://localhost` (XAMPP).

## CSRF

- One token per session (rotated on login), embedded in **every** form.
- `require_csrf()` at the top of **every** POST branch: constant-time
  `hash_equals`, HTTP 400 + abort on mismatch. Verified in testing: stale token (post-login rotation) 400; bad token 400.

## Permission matrix (see architecture.md route matrix)

The security-relevant decisions: downloads require an account (D-011); draft
files editable only by owner; removed notes visible to admin (and owning
moderator) only; transparency no identities (D-004). Reporters' identity never
leaves the moderator/admin UI.

## Failure behavior

- Unknown role or user: `http_response_code(403)` + plain text exit (no leaks).
- Password errors: generic "Wrong email or password" (no email enumeration).
- Registration errors: specific, validates server-side (client-side hints too).
