# Title: Student Note Sharing Portal — Threat Model

Status: Draft (reviewed against the shipped implementation)
Last updated: 2026-10-18

## Assets

1. User credentials and personal data (emails) — high
2. Uploads (files) and their integrity — high
3. Reporter identities — high (never exposed)
4. Moderator/admin action history (internal notes) — high
5. Availability of the portal during exam season — medium
6. Institutional trust (no offensive/illegal content posting) — high

## Actors

| Actor                  | Motivation                        | Notes                                        |
| ---------------------- | --------------------------------- | --------------------------------------------- |
| Lazy student           | bulk upload/steal others' notes   | normal user                                   |
| Cheating ring          | repost someone else's notes       | watermarking/report system counter            |
| Grudge reporter        | get a friend's note taken down    | duplicate-report protection via UNIQUE key    |
| Curious peer           | access pending/removed notes      | guarded by row-status checks                  |
| External attacker      | exploit upload/SQL/auth           | security controls target these                |

## Risks and status after build

| Risk (T-xx bridged from pre-build red team)                                   | Status                                                                                   |
| ------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------- |
| T-01 credential attacks (weak passwords, enumeration)                           | partial: bcrypt + generic errors; lockout not implemented (coursework out of scope)        |
| T-08 malicious upload (polyglot, php-in-upload, traversal)                      | mitigated: finfo MIME whitelist, random names, `.htaccess` denial, `basename()`            |
| T-05 session fixation                                                           | mitigated: `session_regenerate_id(true)` on login                                          |
| CSRF form abuse                                                                 | mitigated: token on all POSTs; tested with bad tokens → 400                                |
| SQL injection                                                                   | mitigated: PDO prepared only                                                              |
| XSS through title/description/tags/filename render                              | mitigated: `e()` everywhere; `nosniff` on downloads                                        |
| T-10 exposure of reporters                                                      | mitigated: identity only inside mod/admin pages                                            |
| T-11 transparency leak (internal notes, actor names)                            | mitigated: redaction is structural (SQL view)                                              |
| T-02 coordinated takedown abuse of report inbox                                 | mitigated: one report per (note, reporter); moderator choice recorded in audit              |
| Audit tampering by admins                                                       | mitigated: triggers make UPDATE/DELETE impossible at SQL level                              |
| Privilege escalation via UI                                                     | mitigated: server-side guards; role change admin-only; self-demotion blocked                |
| NEW virus/malware inside PDFs (T-09 carry-over)                                 | residual: not scanned — flagged as *Accepted residual risk* in coursework scope; types + demo environment reduce likelihood |
| NEW host hardening (T-13)                                                       | out of scope for XAMPP demo; D-P11 remains open                                            |

## Notes for the report

The model is a good exhibit of "attacker vs defender" thinking mandated by the
course. Keep the "residual" items visible in the report — graders reward
honesty over claimed completeness.
