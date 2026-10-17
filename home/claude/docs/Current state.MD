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
- 28 screenshots captured by a headless-Chrome driver performing the real
  flows (register → login → upload → moderate → promote → download)
- Figures embedded in `report/REPORT.md`; deliverables zipped
- Stray TS/Neon prototype at the project root removed (owner-approved)

## Deliberate Technical Debt

- `ponytail:` in-index FULLTEXT misses <3-char tokens; `index.php` falls back to `LIKE` — acceptable at coursework scale.
- `ponytail:` analytics approval-time stat is a plain AVG (coursework scale); exact median would need window functions.
- View counter increments per request (except author); no per-IP/session dedupe.

## Open Items

| ID      | Item                                          | Owner |
| ------- | --------------------------------------------- | ----- |
| ISS-103 | D-P09 name / D-P10 design reference           | Owner |
| ISS-105 | Report title page names / course code         | Owner |

## Next Actions

1. Fill the report title page and zip: `Student_Note_Sharing_Portal_Submission.zip` is already built.
2. Run the viva demo per [demo-script.md](demo-script.md).
