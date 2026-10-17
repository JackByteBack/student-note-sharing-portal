# Title: Student Note Sharing Portal — Screenshot Checklist

**Status: DONE** — 28 shots captured by a headless-Chrome driver performing the
real app flows (2026-10-18). Adjustments made while shooting:

- **#20** shows the *download counter incremented on note 2* after a real
  download (`20-download-counter.png`) instead of dev tools (headers verified in
  code review; browser dev tools can't be screenshotted headless).
- **#21** shows the submission SQL file itself (`21-sql-file.png`) — the phpMyAdmin
  import screen is owner-side; the file view is the honest equivalent.
- **#22/#23** are element-clipped evidence of the audit trail and the redacted view.
- Extra evidence shots kept: `05b-login-flash.png`, `06b-registered-flash.png`,
  `17b-role-updated.png`, `25-report-sent.png`.

Original checklist retained below for reference.

| # | File name (shot)                | Page + action                                                  | Visible content                                        |
| - | ------------------------------- | --------------------------------------------------------------- | ------------------------------------------------------ |
| 01 | 01-browse-anonymous.png ✓      | index.php logged out                                             | nav w/o account links; cards with counters              |
| 02 | 02-search-dsa.png ✓            | index.php `q=joins`                                              | narrowed results                                        |
| 03 | 03-filter-subject.png ✓        | index.php subject=3                                              | filter bar + selection                                  |
| 04 | 04-tag-chip.png ✓              | index.php tag=dsa                                                | active chip                                             |
| 05 | 05-login-student.png ✓ (05b)   | login.php student + flash                                        | form; then flash on landing                             |
| 06 | 06-register.png ✓ (06b)        | register filled → created flash                                  | password rule; auto-login                               |
| 07 | 07-note-detail.png ✓           | note.php?id=2 student                                            | subject line, tags, counters, Save + Report             |
| 08 | 08-bookmark-saved.png ✓        | note.php?id=5 after ☆ Save                                       | flash "Saved to your bookmarks"                          |
| 09 | 09-bookmarks-list.png ✓        | bookmarks.php                                                    | saved rows + Unsave                                     |
| 10 | 10-upload-form.png ✓           | upload.php filled + file chosen                                  | grouped subjects, attestation checked                   |
| 11 | 11-upload-moderation-flash.png ✓ | after Submit for review                                        | flash "Upload sent for moderation"                      |
| 12 | 12-my-notes.png ✓              | my_notes.php                                                     | status badges + actions                                 |
| 13 | 13-review-queue.png ✓          | review.php moderator                                             | queue cards, report inbox, audit                        |
| 14 | 14-review-approve-done.png ✓   | approve applied                                                  | flash "Note approved"                                   |
| 15 | 15-transparency-public.png ✓   | transparency.php anonymous                                       | redaction note + log without names                      |
| 16 | 16-admin-dashboard.png ✓       | admin.php                                                        | stat chips + weekly bars + top downloads                |
| 17 | 17-admin-users.png ✓ (17b)     | users table (+ role-updated flash)                               | role dropdowns, delete except self                      |
| 18 | 18-admin-taxonomy.png ✓        | courses/subjects with add forms                                  | added subject                                           |
| 19 | 19-403-mod-admin.png ✓         | moderator → admin.php                                            | Forbidden body (role isolation)                         |
| 20 | 20-download-counter.png ✓      | note 2 after a real download                                     | download_count incremented                              |
| 21 | 21-sql-file.png ✓              | note_portal.sql view                                             | schema + seed file as delivered                         |
| 22 | 22-audit-rows.png ✓            | review.php audit clip                                            | append-only rows                                        |
| 23 | 23-transparency-view.png ✓     | transparency table clip                                          | redacted columns only                                   |
| 24 | 24-er-diagram.png ✓            | render of the schema                                             | entities, FKs, view, trigger box                        |
| 25 | 25-report-sent.png ✓           | note.php?id=6 after report                                       | flash + report status                                   |
