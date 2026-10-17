# Title: Student Note Sharing Portal — Screenshot Checklist

One PNG per row. Save into `screenshots/` numbered as below; REPORT.md figures
reference these numbers. Take at 1440×900 or similar; seeded accounts in
[deployment.md](deployment.md).

| # | File name (suggested)          | Page + action                                                  | Must be visible                                      |
| - | ------------------------------ | --------------------------------------------------------------- | ------------------------------------------------------ |
| 01 | 01-browse-anonymous.png       | index.php logged out                                             | nav without Account links; note cards with views/downloads |
| 02 | 02-search-dsa.png             | index.php `q=joins`                                              | search box filled; result narrowed                      |
| 03 | 03-filter-subject.png         | index.php course/subject filter applied                          | filter bar + narrowed list                              |
| 04 | 04-tag-chip.png               | index.php tag chip clicked (#dsa)                                | chip active state                                       |
| 05 | 05-login-student.png          | login.php filled as student                                      | form + flash after login                                |
| 06 | 06-register.png               | register.php                                                     | password rule + note role is student                    |
| 07 | 07-note-detail.png            | note.php?id=2 as student                                         | subject line, tags, views/downloads, Save + Report      |
| 08 | 08-bookmark-saved.png         | note.php after ★ Save                                            | flash "Saved to your bookmarks"                         |
| 09 | 09-bookmarks-list.png         | bookmarks.php                                                    | saved table rows + Unsave                               |
| 10 | 10-upload-form.png            | upload.php                                                       | grouped subject select, tags, attestation checked        |
| 11 | 11-upload-moderation-flash.png| after Submit for review                                          | flash "Upload sent for moderation"                      |
| 12 | 12-my-notes.png               | my_notes.php                                                     | status badges (draft/pending/published) + actions       |
| 13 | 13-review-queue.png           | review.php as moderator                                          | queue cards with decision forms + reports inbox         |
| 14 | 14-review-approve-done.png    | note.php post-approval as moderator                              | flash "Note approved"; status badge published           |
| 15 | 15-transparency-public.png    | transparency.php logged out                                      | redaction note + actions table without names            |
| 16 | 16-admin-dashboard.png        | admin.php                                                        | stat chips + weekly bars + top downloads                |
| 17 | 17-admin-users.png            | admin.php users table                                            | roles dropdowns + delete except self                    |
| 18 | 18-admin-taxonomy.png         | admin.php courses/subjects                                       | add forms inline                                        |
| 19 | 19-403-mod-admin.png          | review user opens admin.php                                      | Forbidden page (role isolation)                         |
| 20 | 20-download-headers.png       | browser dev tools on POST download.php                           | Content-Type + counters increment                       |
| 21 | 21-sql-import.png             | phpMyAdmin after importing note_portal.sql                       | table list visible (schema + seed)                      |
| 22 | 22-audit-rows.png             | phpMyAdmin `SELECT * FROM moderation_actions`                    | append-only rows; trigger demo allowed                  |
| 23 | 23-transparency-view.png      | phpMyAdmin `SELECT * FROM transparency_log`                      | redacted columns only                                   |
| 24 | 24-er-diagram.png             | from MySQL Workbench/dbForge/phpMyAdmin designer                 | entities + FKs (report figure)                           |
