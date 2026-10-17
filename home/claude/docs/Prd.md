# Title: Student Note Sharing Portal — Product Requirements

Status: Accepted (course coursework; owner confirmed scope)
Last updated: 2026-10-18

## Problem Statement

Students share notes through scattered channels; nothing is searchable,
vetted, or durable. The portal is one moderated place to share and find study notes.

## Target Users

| Role                      | Capabilities                                                        |
| ------------------------- | -------------------------------------------------------------------- |
| Public visitor (no login) | Browse, search, filter, view published note details and transparency log; no downloads (D-011) |
| Student                   | Register/login; upload (draft or submit); edit/delete drafts; report; bookmark; view own counters |
| Teacher / moderator       | Pending queue approve/reject/remove; report inbox dismiss/remove; audit trail |
| Admin                     | Users + roles, courses/subjects, analytics dashboard; everything a moderator has |

## Functional Requirements — status after build

| ID    | Requirement                                          | Where implemented                     | Acceptance criteria (verified)                                     |
| ----- | ---------------------------------------------------- | ------------------------------------- | -------------------------------------------------------------------- |
| FR-01 | Four roles with different permissions                | auth guards: `require_login`, `require_role`        | Anonymous blocked; mod gets 403 on admin.php; self-demotion blocked |
| FR-02 | Public browse/search of published notes              | index.php, FULLTEXT + LIKE                          | 200 OK; subject/course/tag/keyword filters combine                  |
| FR-03 | Student sign-up / login / logout                     | register.php, login.php, logout.php                 | Password ≥8 chars; errors surfaced; session id rotated at login     |
| FR-04 | Upload with title/subject/description/tags/file      | upload.php + my_notes.php                           | Only PDF/JPG/PNG ≤10 MB; attestation mandatory server-side          |
| FR-05 | Search and filter                                    | index.php                                           | Boolean FULLTEXT for ≥3 chars; LIKE fallback shorter               |
| FR-06 | View + download a note                               | note.php + download.php                             | Public view, login to download; visibility per row                  |
| FR-07 | Report a note                                        | note.php (report form) + review.php inbox           | One report per (note, reporter); duplicate handled gracefully       |
| FR-08 | Moderation: approve / reject / remove with reason    | review.php + inline note.php form                   | Writes row to `moderation_actions`; status transitions enforced     |
| FR-09 | Admin: users, roles, courses, subjects               | admin.php                                           | FK-safe delete with guard messages                                  |
| FR-10 | Redacted transparency view (D-P01-A)                 | transparency.php over `transparency_log` view       | No actor names, reporters, internal notes — in SQL, not CSS         |
| FR-11 | Append-only audit log                                | `moderation_actions` + UPDATE/DELETE triggers       | Triggers raise exceptions; app never edits past rows                |

## Differentiating Modules (D-012)

1. **Audit trail + transparency log** (append-only triggers, SQL view redaction)
2. **View + download counters** on published notes (displayed on cards/detail/admin)
3. **Bookmarks** per student (`☆ Save` / `bookmarks.php`)
4. **Analytics dashboard**: role/status totals, downloads/views, 8-week publish bars, average approval time, top downloads — inline CSS, works offline

## Non-Functional Requirements — status

- Responsive CSS (single file, no CDN; demo works offline)
- WCAG-minded: labels on all inputs, `aria-label` on filter controls, keyboard-submitable forms
- All data marshaled through PDO prepared statements (`ATTR_EMULATE_PREPARES=false`)
- No personal data exposed beyond role needs (transparency redaction; download gating)

## Out of Scope (v1)

Real-time collaboration, payments, native apps, AI features, virus scanning
(unverified tooling; validated types + attachment-only serving stand in — see threat model).
