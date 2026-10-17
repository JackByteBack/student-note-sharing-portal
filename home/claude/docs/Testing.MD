# Title: Student Note Sharing Portal — Testing

Status: Draft (smoke tests executed; table doubles as report material)
Last updated: 2026-10-18

## Executed this session (actual results)

Run against Homebrew MySQL 8 (`mysql.server start` → import `note_portal.sql`) and `php -S 127.0.0.1:8080`.

| # | Test | Result |
| - | ---- | ------ |
| 01 | `php -l` across all 19 PHP files | pass after fixing download.php parse error |
| 02 | Import SQL; counts: 3 users / 12 notes / 6 audit | pass |
| 03 | index.php 200, 7 published cards visible | pass |
| 04 | transparency.php 200 with seeded rows | pass |
| 05 | Login each seeded account; redirects admin→admin.php, mod→review.php, student→index | pass (after fixing per-request cache bug) |
| 06 | CSRF: bad token → 400; stale token → 400; fresh token accepted | pass |
| 07 | Register new account → logged-in immediately | pass |
| 08 | Upload via multipart: row `pending`, original filename stored, tags parsed | pass |
| 09 | Moderator approves note → `published`, audit + transparency updated | pass |
| 10 | Report created; duplicate-report path shows warning not error | coded |
| 11 | Moderator dismisses open report → `dismissed` + audit note | pass |
| 12 | Admin promotes new user to moderator; self-demotion attempt ignored with message | pass |
| 13 | Bookmark toggle writes/reads; `bookmarks.php` lists | pass |
| 14 | Download: `application/pdf` + RFC 5987 filename + `%PDF` bytes + counter +1 | pass |
| 15 | Role isolation: moderator on admin.php → 403 | pass |

## Test table for the report (module → case → expected)

| Module | Case | Expected |
| ------ | ---- | -------- |
| Auth | register duplicate email | error message, no row |
| Auth | login wrong password | generic error, no session |
| Upload | >10 MB file | rejected before storage |
| Upload | .svg renamed to .pdf | rejected by finfo MIME check |
| Note | draft viewed by stranger | 403 |
| Note | report own note | warning, no row |
| moderation | remove published without reason | audit row persists (append-only) |
| DB | UPDATE moderation_actions | SQL exception 'append-only' |
| Transparency | view rows | no actor/reporter columns exist |
| Admin | delete subject with notes | guard message, no deletion |

## How to run the automated part yourself

```bash
php -l student-note-portal/*.php        # syntax gate  (run per file on macOS zsh)
mysql.server start && mysql -u root < student-note-portal/note_portal.sql
php -S 127.0.0.1:8080 -t student-note-portal   # then per README flows
```

XAMPP users: instead import via phpMyAdmin and open `http://localhost/student-note-portal/`.
