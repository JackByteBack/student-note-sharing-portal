# Title: Student Note Sharing Portal — Technology

Status: Accepted (D-007, D-008)
Last updated: 2026-10-18

| Technology                       | Purpose              | Notes                                                                  |
| -------------------------------- | -------------------- | ---------------------------------------------------------------------- |
| PHP 8 (8.1+ targeted; 8.5 tested)| Runtime              | `declare(strict_types=1)` in every file                                 |
| PDO (ext-mysqli not used)        | Data access          | Real prepared statements, no emulation                                  |
| MySQL 8 / MariaDB + InnoDB       | Database             | Provided by XAMPP or Homebrew (both covered in deployment.md)           |
| Sessions (native PHP)            | Auth state           | `httponly`, `samesite=Lax`; cookie not `secure` since demo is http://localhost |
| `password_hash()` bcrypt         | Password storage     | Hashes regenerated with PHP 8.5 for the seed users                      |
| finfo (libmagic, PHP core)       | Upload type sniffing | Detects real MIME — client-declared types never trusted                 |
| Hand-written CSS (1 file)        | Styling              | Zero CDN/JS deps so the demo works offline                              |
| XAMPP (Apache + PHP + MySQL)     | Demo runtime         | htdocs folder copy; .htaccess flock on uploads/                         |

## Existing Dependencies

The application itself ships **zero** Composer/npm dependencies. `php -l`
and `php -S` plus a MySQL server are the only requirements for a demo run.

## Compatibility Constraints

- Astro needed a server-capable host; plain PHP + PDO needs any host that runs PHP 8 —
  the same compatibility problem, solved trivially (D-011 remaining open only for public hosting).
- MySQL `FULLTEXT(title, description)` on InnoDB needed by FR-05.
- `only_full_group_by` enforced by default in MySQL 8 — see admin.php analytics notes.
- `CHECK`-style constraints are used where they add value (title length, attestation),
  but the app also validates inputs server-side (belt and suspenders).
- Append-only audit relies on BEFORE UPDATE/DELETE triggers that `SIGNAL SQLSTATE '45000'`.
