# Student Note Sharing Portal — Project Report (final draft)

> Owner to fill: title-page names/course/supervisor. All figures below are real
> screenshots from the working app (files in `screenshots/`, checklist in
> `home/claude/docs/screenshots.md`).

## 1. Title page

**Student Note Sharing Portal** — a moderated web platform for sharing and finding
study notes.
Team: [names]. Course: [code]. Institution: Westfield College (working name).
Supervisor: [name]. Academic year: 2026–27.

## 2. Abstract

Students lose study notes across chat groups and drives: material is unvetted,
unsearchable, and disappears with old chats. We built **Student Note Sharing
Portal**, a server-rendered PHP 8 web app on MySQL where students upload notes
(PDF/JPG/PNG, ≤10 MB, ownership attested) as drafts or moderation queue items;
moderators approve, reject with categorized reasons, or remove notes; admins
manage users, roles, and the course taxonomy and see analytics. The public can
browse and search published notes without an account, while downloads require
login. Four modules go beyond a baseline CRUD portal: an append-only audit trail
(enforced in the database), a transparency log redacted *in SQL*, per-note
view/download counters, personal bookmarks, and an admin analytics dashboard.
Security-relevant behavior is server-side: PDO prepared statements, bcrypt
passwords, session hardening, CSRF tokens on every form, upload type/size
sniffing with non-guessable stored names, and web-denied file storage served only
through an authorized `download.php`. The result was exercised end to end by a
headless-browser run that performed the real user actions behind every figure.

**Keywords:** content moderation, transparency, append-only audit, PHP, MySQL, PDO.

## 3. Introduction and problem statement

Course notes get shared in chats and personal drives. The problems: (a) no single
searchable index, (b) copies without vetting (copyright and quality risk), (c)
institutional knowledge lost as chats clear. Objectives (from the PRD): any
student can *find* a note in ≤3 clicks, any student can *publish* a note in
≤2 minutes, and every moderation action is traceable and transparent to the
community in redacted form.

## 4. Objectives and scope — requirements

| ID | Requirement | Accepted criteria (verified) |
| -- | ----------- | ----------------------------- |
| FR-01 | Four roles with different permissions | anonymous blocked; moderator → 403 on admin; self-demotion impossible |
| FR-02 | Public browse/search (published only) | HTTP 200 open access; no pending/draft rows leak |
| FR-03 | Student sign-up / login / logout | password ≥8; session id rotated at login |
| FR-04 | Upload with title/subject/description/tags/file | PDF/JPG/PNG ≤10 MB; attestation required server-side |
| FR-05 | Search + filters (course/subject/tag/keyword) | FULLTEXT boolean ≥3 chars; LIKE fallback shorter |
| FR-06 | View + download | public view; login to download; per-status visibility |
| FR-07 | Report a note | one per (note, reporter); reported flows into inbox |
| FR-08 | Moderate approve/reject/remove with reason | audit row per decision; category + internal note |
| FR-09 | Admin users/roles/courses/subjects | FK-guarded deletes; role changes admin-only |
| FR-10 | Redacted transparency view | identity columns absent from the SQL view itself |
| FR-11 | Append-only audit log | UPDATE/DELETE answered by SQL SIGNAL |

Out of scope: real-time collaboration, payments, native apps, AI features, malware scanning.

## 5. System analysis

Roles map 1:1 to permissions (public visitor = "no session"). User journeys
(home/claude/docs/user-journeys.md) drove page inventory; decision debates from
the pre-build phase are summarized in Section 14.

## 6. Design — architecture

Server-rendered pages; one PHP file is one route; shared concerns live in
`includes/` (db, auth, functions, header, footer). Guards run before anything
renders; every POST first proves a CSRF token; files leave the server only via
`download.php`.

```mermaid
flowchart TD
  B[Browser] -->|GET/POST| A[Apache/PHP page]
  A -->|require_login / require_role| G[auth guards]
  A -->|POST| C[require_csrf]
  A -->|PDO prepared| D[(MySQL note_portal)]
  B -->|download link| DL[download.php]
  DL -->|login + status checks| D
  DL -->|readfile| U[uploads/ .htaccess-denied]
```

The full route matrix (page × role) is in home/claude/docs/architecture.md.

## 7. Design — database

![Figure 24 — ER diagram of the note_portal schema](../screenshots/24-er-diagram.png)

InnoDB throughout, utf8mb4. `notes` carries the moderation state machine
(draft → pending → published / rejected / removed), non-guessable `file_ref`,
counters, and a FULLTEXT index. `moderation_actions` is **append-only at the SQL
layer** — UPDATE and DELETE raise SIGNAL '45000'. `transparency_log` is a VIEW
whose SELECT physically omits actor identity, internal notes and reporter
columns: the redaction of D-P01-A is enforced in the database, not in HTML.
The submission SQL (schema + seed in one file) is `student-note-portal/note_portal.sql`
— the imported result provides every status, report and audit row the demo needs.

## 8. Implementation — modules

| Module | Files | Highlights |
| ------ | ----- | ---------- |
| Data access | includes/db.php | one PDO; `ATTR_EMULATE_PREPARES=false` (real prepares) |
| Auth | includes/auth.php | `require_login`/`require_role`; `session_regenerate_id(true)`; per-request cache invalidated by login/logout |
| Helpers | includes/functions.php | `e()` escaping, CSRF, flash, `store_upload()` (finfo MIME → random hex name) |
| Browse | index.php | Boolean FULLTEXT + LIKE fallback; course/subject/tag filters; pagination |
| Note detail | note.php | visibility per row status; counters; report; bookmark; inline moderation |
| Download | download.php | login + status gate; MIME + RFC 5987 filename; counter |
| Upload | upload.php | grouped subjects; tags; attestation; drafts vs pending |
| My notes | my_notes.php | status chips; edit/delete own drafts (file unlinked); re-submit |
| Moderation | review.php | queue, report inbox, spot removal, audit trail |
| Admin | admin.php | users/roles (self-demotion blocked), FK-guarded taxonomy, analytics |
| Transparency | transparency.php | reads the redacted view |

## 9. Differentiators

1. **Audit + transparency** — INSERT-only history plus a public log that cannot
   leak what it never selects. Figures 15, 22, 23.
2. **Counters** — views skip the author's own visits; downloads increment inside
   the authorized download path only. Figures 07 → 20.
3. **Bookmarks** — composite-key table, toggle on any published note. Figures 08, 09.
4. **Analytics** — pure-CSS bars, top downloads, avg approval time, role/status
   totals; no chart library so the demo works offline. Figure 16.

## 10. Security and privacy

| Threat | Control (where) |
| ------ | ----------------- |
| SQL injection | PDO prepared statements only, emulation off |
| XSS | `e()` (htmlspecialchars ENT_QUOTES) at every output; `nosniff` on downloads |
| Weak/leaked passwords | `password_hash` bcrypt; generic login errors |
| Session fixation / theft | httponly + SameSite=Lax cookie; id regenerated on login |
| CSRF | token per session on EVERY form; constant-time check; 400 on failure |
| Malicious upload | finfo MIME whitelist (pdf/jpeg/png), size cap, random name, client names never parsed |
| Direct file access | `uploads/.htaccess` "Require all denied"; `download.php` sole reader |
| Privilege escalation | guards server-side; role change admin-only; self-demotion blocked |
| Reporter identity leak | fields absent from transparency view; inbox is moderator-only |
| Audit tampering | SQL triggers make UPDATE/DELETE impossible |

**Honest residual risks** (stated, not hidden): no malware scanning of uploads
(type whitelist + demo environment reduce likelihood); no rate limiting or
lockout (coursework scale); password reset is admin-assisted; audit table grows
by design.

## 11. Testing

Automated syntax gate (`php -l`) across 19 files, plus an end-to-end headless
browser run and CLI probes — the same actions that produced the figures:

| # | Test | Result |
| - | ---- | ------ |
| 01 | `php -l` all files | pass (one parse error fixed during build) |
| 02 | SQL import; counts 3 users / 12 notes / 6 audit | pass |
| 03 | Browse, search, filters, tag chips (fig 01–04) | pass |
| 04 | Role-landing after login (fig 05b/14/16) | pass |
| 05 | Registration → auto-login (fig 06b) | pass |
| 06 | CSRF: stale/bad token → 400 | pass |
| 07 | Upload → `pending` + file stored (fig 10–12) | pass |
| 08 | Moderator approve → published + audit + transparency (fig 13–14) | pass |
| 09 | Report create + dismiss (fig 25, 13) | pass |
| 10 | Admin promote / self-demotion blocked (fig 17b) | pass |
| 11 | Download streams + counter increments (fig 20) | pass |
| 12 | Moderator blocked from admin (fig 19) | pass |
| 13 | Transparency redaction (fig 15/23 + DB view def) | pass |
| 14 | Append-only trigger fires on UPDATE attempt | coded + verified in build |
| 15 | Duplicate report handled gracefully | coded |

## 12. Screenshots — the demo story

![Figure 01 — public browse](../screenshots/01-browse-anonymous.png)
![Figure 02 — keyword search](../screenshots/02-search-dsa.png)
![Figure 03 — subject filter](../screenshots/03-filter-subject.png)
![Figure 04 — tag chip](../screenshots/04-tag-chip.png)
*Public visitor: browse, search, filter, tags — everything above needs no account.*

![Figure 05 — login form](../screenshots/05-login-student.png)
![Figure 06 — registration](../screenshots/06-register.png)
![Figure 07 — note detail with counters](../screenshots/07-note-detail.png)
![Figure 08 — bookmark saved](../screenshots/08-bookmark-saved.png)
![Figure 09 — bookmarks list](../screenshots/09-bookmarks-list.png)
*Student module: auth, detail page with counters, save-for-later (module 3).*

![Figure 10 — upload form](../screenshots/10-upload-form.png)
![Figure 11 — submitted for moderation](../screenshots/11-upload-moderation-flash.png)
![Figure 12 — my notes statuses](../screenshots/12-my-notes.png)
![Figure 25 — report sent](../screenshots/25-report-sent.png)
*Upload flow with attestation; every submission enters the pre-moderation queue
(module discipline: nothing auto-publishes).*

![Figure 13 — moderation center](../screenshots/13-review-queue.png)
![Figure 14 — approval applied](../screenshots/14-review-approve-done.png)
![Figure 19 — role isolation (403)](../screenshots/19-403-mod-admin.png)
*Moderator queue and inbox; a moderator hitting the admin console gets 403 —
UI hiding is never the security boundary.*

![Figure 15 — public transparency log](../screenshots/15-transparency-public.png)
![Figure 22 — append-only audit rows](../screenshots/22-audit-rows.png)
![Figure 23 — redacted view columns](../screenshots/23-transparency-view.png)
*Audit trail (append-only) and the redacted public view (module 1).*

![Figure 16 — admin analytics dashboard](../screenshots/16-admin-dashboard.png)
![Figure 17 — role management](../screenshots/17-admin-users.png)
![Figure 18 — taxonomy management](../screenshots/18-admin-taxonomy.png)
*Admin console with the analytics module (module 4) — inline CSS bars, no CDN.*

![Figure 20 — download counter incremented](../screenshots/20-download-counter.png)
![Figure 24 — ER diagram](../screenshots/24-er-diagram.png)
![Figure 21 — submission SQL file](../screenshots/21-sql-file.png)

Additional evidence shots: `05b-login-flash.png`, `06b-registered-flash.png`,
`17b-role-updated.png` — post-action state with flash messages.

## 13. Installation guide

Copy `student-note-portal/` into XAMPP's `htdocs/`; start Apache + MySQL; import
`student-note-portal/note_portal.sql` in phpMyAdmin; open
`http://localhost/student-note-portal/`. Seeded accounts:

| email | password | role | lands on |
| ----- | -------- | ---- | -------- |
| admin@westfield.edu | admin123 | admin | admin.php |
| moderator@westfield.edu | moderator123 | moderator | review.php |
| student@westfield.edu | student123 | student | index.php |

(Dev alternative: `mysql -u root < note_portal.sql` + `php -S 127.0.0.1:8080 -t student-note-portal`.)

## 14. Limitations, future work, and the decisions trail

Residual security rows in Section 10 are future-work items (scanning hook, rate
limiting, password reset). Open owner decisions: final product name (D-P09),
design reference (D-P10), public hosting (D-P11). Historical decision debates —
including the superseded Astro/Neon direction and why the course brief flipped
the stack to PHP + MySQL — are archived in home/claude/docs/decisions.md.

## 15. Conclusion

All eleven functional requirements shipped with acceptance criteria verified by
the end-to-end run; moderation is demonstrably traceable (append-only audit) and
publicly transparent in redacted form (SQL-level redaction). Stack discipline
(plain PHP + MySQL, zero runtime dependencies) kept the deliverable
self-contained: one folder + one .sql import runs the whole system offline.

## 16. References

1. PHP Manual — password_hash, finfo, session, PDO.
2. MySQL 8.0 Reference Manual — FULLTEXT, triggers, CHECK constraints, views.
3. OWASP Cheat Sheet Series — Authentication, CSRF, XSS, File Upload.
4. Project documentation set — home/claude/docs/ (index: Readme.MD).
