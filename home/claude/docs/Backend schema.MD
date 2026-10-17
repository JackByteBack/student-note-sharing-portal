# Title: Student Note Sharing Portal — Backend Schema (MySQL)

Status: Accepted
Last updated: 2026-10-18

Canonical definition lives in [`student-note-portal/note_portal.sql`](../../student-note-portal/note_portal.sql)
(schema + seed in one importable file). This doc is the human map. Sensitivity classes
measured against the threat model.

## Entities

| Table                | Column highlights (beyond PK/FK)                                                                                  | Sensitivity |
| -------------------- | ------------------------------------------------------------------------------------------------------------------- | ----------- |
| `institutions`       | 1 row (Westfield College) — one college per D-006                                                                   | low         |
| `users`              | `password_hash` (bcrypt only), `role` ENUM(student/moderator/admin)                                                 | high        |
| `courses`, `subjects`| Catalog trees; subject UNIQUE per course                                                                            | low         |
| `notes`              | `status` ENUM(draft/pending/published/rejected/removed), `file_ref` random 32-hex + ext, `original_name`, `mime`, `size_bytes`, `ownership_attested` (CHECKed), `view_count`, `download_count`, `published_at`; `FULLTEXT(title, description)` | medium (file metadata), high if names inside files |
| `tags`, `note_tags`  | join table with composite PK (`note_id`,`tag_id`)                                                                   | low         |
| `reports`            | `reason` CHECK categories, UNIQUE(note_id, reporter_id), status /dismissed/resolved                                | high (reporter identity) |
| `moderation_actions` | `action` ENUM, `reason_category` CHECK, `internal_note` admin-only; **append-only via triggers**; `INDEX(created_at)` | high (actor identity) |
| `bookmarks`          | composite PK (`user_id`,`note_id`) — differentiator module 3                                                        | low         |

## View

`transparency_log` = moderation actions **redacted at the SQL layer**: exposes
`note_id, note_title, action, reason_category, created_at, actor_role` — never
`actor_id`/names, `internal_note`, or reporter columns. D-P01-A is enforced in the
database, not in HTML.

## Seed data (matches sample files in `uploads/`)

- users 1-3: admin / moderator / student (bcrypt of admin123, moderator123, student123)
- 3 courses → 6 subjects; 9 tags
- 12 notes: 6 published, 2 pending (queue demo), 1 rejected, 1 draft, 1 removed (takedown demo), 1 most-recent published (subnetting, top downloads)
- 6 moderation_actions (approve ×3, reject, remove, restore) spanning weeks 1-9 for the analytics chart
- 2 reports (1 open → review inbox demo, 1 resolved), 2 bookmarks

## Conventions

- All timestamps `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` (UTC in engine, shown via app timezone)
- Character set `utf8mb4` / `utf8mb4_unicode_ci` throughout
- Engine InnoDB everywhere (FKs, row locks, FULLTEXT support)
- Sample file_refs must exist in `uploads/` (shipped; `download.php` verifies `is_file` first)
