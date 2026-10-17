# Title: Student Note Sharing Portal — Decisions

Records accepted, rejected, superseded, and pending decisions.

Status: Draft
Last updated: 2026-10-18

## Accepted

| ID    | Date       | Decision                                                                      | Reason                                                          |
| ----- | ---------- | ----------------------------------------------------------------------------- | --------------------------------------------------------------- |
| D-001 | 2026-10-18 | Project is a coursework submission (originally doc-only idea stage)           | Course brief provided by owner                                  |
| D-002 | 2026-10-18 | Four audiences: students, teachers/moderators, admin, public/no-login         | Owner selected all four                                         |
| D-003 | 2026-10-18 | Documentation delivered as Markdown following the Project Doc Blueprint       | Owner selected it                                               |
| D-004 | 2026-10-18 | Transparent behavior = redacted transparency view (was D-P01 option A)        | Owner confirmed; excludes reporters/emails/internal notes        |
| D-005 | 2026-10-18 | Authentication = email + password (was D-P03)                                 | Owner confirmed; works with plain PHP sessions                   |
| D-006 | 2026-10-18 | Audience scope = one college (was D-P06)                                      | Owner confirmed; single institution row seeded                   |
| D-007 | 2026-10-18 | **Stack flip for submission: plain PHP 8 + MySQL, XAMPP demo**                 | Submission brief demands PHP source + MySQL SQL file             |
| D-008 | 2026-10-18 | No framework; hand-written pages/ + includes/ with PDO                        | Coursework-typical, defensible in viva, zero install step         |
| D-009 | 2026-10-18 | Pre-moderation at launch (was D-P05 recommendation)                           | Owner ratified the recommendation; visible queue is demo material |
| D-010 | 2026-10-18 | Uploads: PDF/JPG/PNG, 10 MB cap, ownership attestation mandatory              | Owner ratified D-P04 recommendation; enforced server-side        |
| D-011 | 2026-10-18 | Public browsing without login; downloads require login (D-P08 recommendation) | Owner ratified; always enforced in download.php                  |
| D-012 | 2026-10-18 | Four differentiating modules: audit+transparency, counters, bookmarks, analytics | Owner picked all to differ from other teams                    |

## Superseded

| ID    | Decision (was)                     | Superseded by | Reason                                     |
| ----- | ---------------------------------- | ------------- | ------------------------------------------ |
| D-P02 | Backend = Neon Postgres            | D-007         | Submission requires MySQL                  |
| D-002 | Frontend stack Astro + Tailwind v4 | D-007         | Submission requires PHP                    |
| D-P04 | Provider storage bucket            | D-010         | Files live in app-local `uploads/`, gated by .htaccess |

## Pending (non-blocking)

| ID    | Decision Needed                     | Options                          | Default until decided                       |
| ----- | ----------------------------------- | -------------------------------- | ------------------------------------------- |
| D-P09 | Final product name                  | Working title / rename           | "Student Note Sharing Portal"               |
| D-P10 | Design reference                    | Notion / Mintlify / Linear       | Neutral clean CSS tokens, no reference      |
| D-P11 | Public hosting beyond the demo      | XAMPP only / deploy E2 vector    | Local XAMPP demo (course requires local run) |
| D-P07 | Copyright/takedown wording refinement | Attestation + takedown (v1)    | Attestation checkbox + remove workflow (shipped) |

## Rule Reminder

No extra dependency is adopted unless needed: app has **zero runtime
dependencies** (PDO, sessions, GD/finfo from PHP core; CSS is hand-written).
