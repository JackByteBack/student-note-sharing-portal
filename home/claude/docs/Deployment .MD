# Title: Student Note Sharing Portal — Deployment (XAMPP demo + submission packaging)

Status: Accepted for demo; public hosting D-P11 open
Last updated: 2026-10-18

## Option A — XAMPP (the course-demo baseline)

1. Install XAMPP; open the Manager and start **Apache** and **MySQL** (or MariaDB).
2. Copy the folder `student-note-portal/` into the web root:
   - macOS: `/Applications/XAMPP/htdocs/`
   - Windows: `C:\xampp\htdocs\`
3. Open <http://localhost/phpmyadmin> → **Import** → choose
   `student-note-portal/note_portal.sql` → Run. (Creates DB `note_portal` with seed.)
4. Allow overrides so the uploads `.htaccess` takes effect — with XAMPP's default
   config `<Directory "/Applications/XAMPP/htdocs"> AllowOverride All` is already set;
   if direct file access is ever observed, set `AllowOverride All` for htdocs and restart Apache.
5. Browse: <http://localhost/student-note-portal/> and log in with the seeded accounts

   | email                        | password       | role      | lands on |
   | ---------------------------- | -------------- | --------- | -------- |
   | admin@westfield.edu          | admin123       | admin     | admin.php |
   | moderator@westfield.edu      | moderator123   | moderator | review.php |
   | student@westfield.edu        | student123     | student   | index.php |

Credentials live behind one file (`includes/db.php`) if your stack differs — change
only there.

## Option B — Homebrew (commented for development)

```bash
mysql.server start
mysql -u root < student-note-portal/note_portal.sql
php -S 127.0.0.1:8080 -t student-note-portal
```

(phpMyAdmin optional; the seed covers all demo needs.)

## Submission packaging

```
deliverables/
├─ student-note-portal/   complete PHP source + note_portal.sql + uploads samples
├─ report/REPORT.md       the report (figures filled from screenshots/)
└─ screenshots/           one PNG per docs/screenshots.md row
```

Zip `deliverables/` → `StudentNotePortal_TeamX.zip`. The demo works fully offline;
no CDN dependencies.

## Monitoring (demo scale)

Logs live in XAMPP (`logs/` for Apache; `php_error_log` if enabled). The audit
table + admin dashboard act as the product-level monitoring for the demo.
