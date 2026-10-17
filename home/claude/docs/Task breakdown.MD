# Title: Student Note Sharing Portal — Task Breakdown (build plan matches shipped)

Status: Current
Last updated: 2026-10-18

## Phases vs shipped

| Phase | Goal                                                | Status        | Evidence                                             |
| ----- | ---------------------------------------------------- | ------------- | ------------------------------------------------------ |
| P0    | Docs detail + consolidation of the two doc sets     | done          | this folder; root research docs merged in             |
| P1    | SQL + app skeleton + auth                           | done          | note_portal.sql; includes/; auth trio                 |
| P2    | Browse/search → note view/download → upload/drafts → bookmarks | done | index/note/download/upload/my_notes/bookmarks |
| P3    | Moderation + reports + audit → admin + analytics → transparency | done | review.php, admin.php, transparency.php |
| P4    | Seed polish, report, screenshots checklist, demo script, zip | done except screenshots (owner) | README; report/; docs/screenshots.md |

## Per-file acceptance criteria (all verified by smoke tests)

| File              | Acceptance criterion                                                        |
| ----------------- | ------------------------------------------------------------------------------ |
| note_portal.sql   | imports clean on MySQL 8; triggers block audit edits; view queries             |
| includes/db.php   | single PDO; prepared statements enforced                                       |
| includes/auth.php | role redirects after login; cache invalidation after change (bug fixed)        |
| index.php         | filters combine; <3-char searches still work; pagination intact                |
| note.php          | 404 unknown; 403 when not visible; CSRF 400; report/bookmark idempotency paths |
| download.php      | RFC 5987 filename; correct MIME; counter increments; login required            |
| upload.php        | MIME whitelist; 10 MB cap; attestation required server-side; drafts edit       |
| my_notes.php      | delete only own drafts (file unlinked); re-submit rejected                      |
| bookmarks.php     | toggle + unsave list                                                           |
| review.php        | approve/reject/remove writes audit; report inbox dismiss/remove_note            |
| admin.php         | only_full_group_by-safe analytics; delete-guarded taxonomy                      |
| transparency.php  | public; zero identity fields                                                   |

## Remaining owner tasks

Screenshots (screenshots.md), fill report figures, zip deliverables.
