# Title: Student Note Sharing Portal — Current State

Status: Draft
Last updated: 2026-10-18 (after the build session)

## Current Phase

**Working implementation complete** in [`student-note-portal/`](../../student-note-portal/):
14 PHP files, 1 MySQL file (schema + seed), sample demo files, docs, report.

## Verified Implementation Status (smoke-tested against Homebrew MySQL 8 + PHP 8.5)

| Flow | Result |
| --- | --- |
| `php -l` on all 19 PHP files | pass (one parse error found and fixed in download.php) |
| `note_portal.sql` import | 3 users, 12 notes, 6 audit rows, transparency view queries |
| Public browse + filters + tags (index.php) | HTTP 200, 7 published cards on page 1 |
| Login redirect by role (admin→admin.php, mod→review.php, student→index) | pass (a per-request cache bug was found and fixed) |
| CSRF: wrong token → 400, fresh token → accepted | pass |
| Student upload via multipart POST → row `pending`, file stored | pass |
| Moderator approves note → `published` + audit + transparency row | pass |
| Moderator dismisses report | pass |
| Admin promotes user; self-demotion blocked | pass |
| Download streams with correct headers, `%PDF` bytes, counter increments | pass |
| 403 for moderator hitting admin.php | pass |

## Completed Work (this session)

- PHP app per [architecture.md](architecture.md); MySQL per [backend-schema.md](backend-schema.md)
- 12 seeded sample files in `uploads/` so downloads work on first demo
- Docs consolidated into this folder; report + screenshot + demo docs added

## Deliberate Technical Debt

- `ponytail:` in-index FULLTEXT misses <3-char tokens; `index.php` falls back to `LIKE` — acceptable at coursework scale.
- `ponytail:` analytics "average approval time" is AVG of hours (labeled median in UI); exact median needs window functions.
- View counter increments per request (except author); no per-IP/session dedupe.

## Open Items

| ID      | Item                                          | Owner |
| ------- | --------------------------------------------- | ----- |
| ISS-101 | Screenshots not yet taken (checklist ready)   | Owner |
| ISS-102 | Report figures need the screenshots pasted in | Owner |
| ISS-103 | D-P09 name / D-P10 design reference           | Owner |
| ISS-104 | Stray root prototype (node_modules, neon.ts, src/) predates the PHP pivot — keep or delete | Owner |

## Next Actions

1. Owner runs the demo per [deployment.md](deployment.md).
2. Owner takes screenshots per [screenshots.md](screenshots.md).
3. Fill report figures in [report-outline.md](report-outline.md) + `report/REPORT.md`.
4. Zip `student-note-portal/` for submission.
