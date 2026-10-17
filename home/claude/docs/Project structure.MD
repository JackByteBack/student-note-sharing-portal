# Title: Student Note Sharing Portal — Project Structure

Status: Accepted
Last updated: 2026-10-18

Layout as shipped. The app folder is self-contained: copy `student-note-portal/`
into XAMPP's `htdocs/` and it runs.

```
student-note-portal/
├─ note_portal.sql         schema + seed data (submission deliverable)
├─ includes/
│  ├─ db.php               PDO single connection (XAMPP root/no-pass defaults)
│  ├─ functions.php        e(), csrf, flash, redirect, store_upload, parse_tags, badges
│  ├─ auth.php             session start, current_user, guards, login/register/logout
│  ├─ header.php           topbar + nav (role-conditional) + flash rendering
│  └─ footer.php           closes layout, links transparency log
├─ assets/style.css        design tokens + all components (no CDN, no JS)
├─ uploads/                file storage; .htaccess denies direct web access
├─ index.php               browse/search/filter — public                      (FR-05)
├─ note.php                detail, view counter, report, bookmark, inline moderation (FR-06/07/08)
├─ download.php            the only exit for files; login + status checked    (FR-06)
├─ login.php / register.php / logout.php
├─ upload.php              new upload + edit-draft (?draft=id)                (FR-04)
├─ my_notes.php            own notes; delete/submit drafts; re-submit rejected
├─ bookmarks.php           saved list + unsave                                (module 3)
├─ review.php              moderation center: queue, reports, spot removal, audit
├─ admin.php               users/roles, taxonomy, analytics dashboard         (FR-09, module 4)
└─ transparency.php        redacted public log                                (FR-10)
```

Around it (at the project root, outside the submission zip):

```
report/REPORT.md            coursework report (paste screenshots at figures)
screenshots/                owner fills per docs/screenshots.md checklist
home/claude/docs/           this documentation set
deliverables.zip built from student-note-portal/ + report/ + screenshots/
```

## Conventions (enforced in code)

- Permission logic only in `includes/auth.php` (`require_role()`, `can_moderate()`) — pages never branch on role inline except to *hide UI*, never to *permit*.
- Data access via `query()` in `db.php`; every statement is prepared.
- All user input validated in the handling page before touching the DB; uploads only via `store_upload()`.
- All output passes `e()` (htmlspecialchars ENT_QUOTES); raw values never echo users' input.
- Every POST starts with `require_csrf()`.
- DB name `note_portal`; credentials only in `includes/db.php` (root/empty default for XAMPP).
