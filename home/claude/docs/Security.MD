# Title: Student Note Sharing Portal — Security

Status: Accepted — checklist verified by smoke tests
Last updated: 2026-10-18

## Principles applied

1. Validate at the trust boundary (server-side, every POST and upload).
2. Authorize server-side, never in the UI (guards + per-row checks).
3. Least exposure of personal data (SQL-level redaction).
4. One gate for dangerous operations (files leave only via download.php).
5. Favor boring, auditable code; zero runtime dependencies.

## Controls shipped

| Control                | Implementation                                                            |
| ---------------------- | --------------------------------------------------------------------------- |
| Injection              | PDO prepared statements only (`ATTR_EMULATE_PREPARES=false`) — no string SQL |
| XSS                    | `e()` on every dynamic value; `Content-Type: text/html` implicitly           |
| Passwords              | `password_hash` bcrypt; `password_verify`; no plaintext anywhere; seeded with real hashes |
| Sessions               | httponly, SameSite=Lax, `session_regenerate_id(true)` at login, destroy on logout |
| CSRF                   | per-session token checked by every POST; 400 on failure                     |
| Upload type/size       | finfo MIME whitelist (pdf/jpeg/png), ext derived from MIME, 10 MB cap, `UPLOAD_ERR` probe |
| Upload tampering       | Stored under random 32-hex name; `original_name` only used for download headers |
| Direct file access     | `uploads/.htaccess` = `Require all denied`; `download.php` is the sole reader |
| Path traversal         | `basename()` on file_ref everywhere; random names have no user input        |
| Privilege escalation   | Role changes only via admin POST; self-demotion blocked                     |
| Authorization (rows)   | per-row owner checks before view/edit/delete of notes, drafts, bookmarks    |
| Report privacy         | reporter identity never rendered outside mod/admin inbox                    |
| Transparency redaction | enforced in SQL view — reporters/actors/internal notes absent structurally  |
| Audit integrity        | `moderation_actions` UPDATE/DELETE answered with `SIGNAL SQLSTATE '45000'`   |
| Click-guard            | `nosniff` header on downloads; explicit `Content-Length`                     |

## Verified in testing

- `php -l` clean on 19 files; bad/stale CSRF tokens → 400; upload → pending + file stored;
  moderator approval logged; admin role changes up but blocking self-demotion; download
  headers + counter increment; 403 role isolation.

## Secret names (for hosting beyond the demo; D-P11 open)

- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` in `includes/db.php` — first place to move to env/config on real hosting.

## Incident basics (student scale)

If abuse is suspected: (1) remove the content via moderation panel, (2) note it
in an internal note of an append-only audit row, (3) escalate the account role
or delete it if warranted, (4) report to the supervising teacher with the audit rows.
