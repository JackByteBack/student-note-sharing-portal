# Student Note Sharing Portal

Course submission project — a moderated study-notes portal: students upload
(PDF/JPG/PNG with ownership attestation), moderators approve/reject/remove,
admins manage users, taxonomy and analytics; the public browses published notes
without an account (downloads need login).

**Status: built and smoke-tested.** Plain PHP 8 + MySQL (no framework, zero
runtime dependencies), designed to run from a folder copy in XAMPP.

## Folder map

```
student-note-portal/    ← THE APP — copy this folder into htdocs/
├─ note_portal.sql      ← submission deliverable: MySQL schema + seed data
├─ index.php, note.php, download.php, upload.php, my_notes.php, bookmarks.php
├─ review.php, admin.php, transparency.php, login/register/logout.php
├─ includes/            db.php (PDO), auth.php (guards), functions.php, layout
├─ assets/style.css     design tokens (no CDN, demo works offline)
└─ uploads/             storage; .htaccess denies direct access; sample files seeded
report/REPORT.md        coursework report draft with figure slots
screenshots/            one PNG per the checklist in docs/screenshots.md
home/claude/docs/       full project documentation set (index: Readme.MD)
```

## Quick start (XAMPP)

1. Copy `student-note-portal/` into `/Applications/XAMPP/htdocs/`.
2. Start **Apache** and **MySQL** in the XAMPP Manager.
3. phpMyAdmin → Import → `student-note-portal/note_portal.sql`.
4. Open <http://localhost/student-note-portal/> and log in:

| email                    | password       | role      | lands on   |
| ------------------------ | -------------- | --------- | ---------- |
| admin@westfield.edu      | admin123       | admin     | admin.php  |
| moderator@westfield.edu  | moderator123   | moderator | review.php |
| student@westfield.edu    | student123     | student   | index.php  |

Dev without XAMPP: `mysql.server start && mysql -u root < student-note-portal/note_portal.sql`
then `php -S 127.0.0.1:8080 -t student-note-portal student-note-portal/router.php` (router adds the custom 404).

## Documentation index

Start with `home/claude/docs/Readme.MD` (reading order, current decisions D-001…D-012,
route matrix, security checklist, test results, report outline, screenshot checklist,
viva demo script). History: research notes, decision debates, and the pre-pivot
Postgres draft are archived in the same folder.

## Submission checklist

- [x] Complete PHP source — `student-note-portal/` (30 files, zero dependencies; incl. SEO meta + JSON-LD, About/Privacy/Terms/Contact, FAQ, 404/500, robots.txt, sitemap.xml)
- [x] MySQL database SQL file — `student-note-portal/note_portal.sql`
- [x] Project report — `report/REPORT.md` (figures embedded; title page left to you)
- [x] Project screenshots — 35 shots in `screenshots/` (real UI runs, incl. post-action flashes, SEO/legal pages, mobile view)
- [x] Working demonstration — viva walkthrough in `home/claude/docs/demo-script.md`
- [x] Packaged — `Student_Note_Sharing_Portal_Submission.zip` (app + report + screenshots)
