# Title: Student Note Sharing Portal — Documentation Index

Entry point, reading order, and index for the portal's project documentation.

Status: Draft
Last updated: 2026-10-18

## Current Stage

**Built.** A complete PHP 8 + MySQL coursework implementation now lives in
[`student-note-portal/`](../../student-note-portal/). The earlier Astro + Neon
direction was **superseded** by the course submission requirements (see
[decisions.md](decisions.md) D-007). The Postgres draft
[schema-postgres-draft.sql](schema-postgres-draft.sql) is kept as history.

## Reading Order

1. [prd.md](prd.md) — problem, roles, requirements
2. [decisions.md](decisions.md) — what was decided and why
3. [current-state.md](current-state.md) — what exists now
4. [backend-schema.md](backend-schema.md) — the MySQL entities
5. [architecture.md](architecture.md) — page → role → table map
6. [security.md](security.md) — rules baked into the code
then as needed: [task-breakdown.md](task-breakdown.md), [testing.md](testing.md),
[deployment.md](deployment.md), [report-outline.md](report-outline.md),
[screenshots.md](screenshots.md), [demo-script.md](demo-script.md).

## Index

| File                                         | Description                                                             | Status     |
| -------------------------------------------- | ----------------------------------------------------------------------- | ---------- |
| [prd.md](prd.md)                             | Problem, users, goals, functional requirements with acceptance criteria | Accepted  |
| [decisions.md](decisions.md)                 | Decision log incl. the PHP+MySQL pivot                                  | Draft      |
| [current-state.md](current-state.md)         | Implementation status after the build session                           | Draft      |
| [instructions.md](instructions.md)           | Rules for AI and collaborators                                          | Draft      |
| [agents.md](agents.md)                       | Coding-agent rules                                                      | Accepted   |
| [glossary.md](glossary.md)                   | Domain terms                                                            | Draft      |
| [architecture.md](architecture.md)           | Route matrix, lifecycles, trust boundaries                              | Accepted   |
| [technology.md](technology.md)               | PHP 8 / MySQL / XAMPP toolchain                                         | Accepted   |
| [backend-schema.md](backend-schema.md)       | MySQL tables, enums, indexes, seed data spec                            | Accepted   |
| [auth.md](auth.md)                           | Sessions, guards, CSRF, route policy                                    | Accepted   |
| [security.md](security.md)                   | Controls, upload rules, checklist (verified)                            | Accepted   |
| [threat-model.md](threat-model.md)           | Assets, actors, risks incl. PHP-specific attacks                        | Draft      |
| [design-system.md](design-system.md)         | CSS tokens, components                                                  | Accepted   |
| [user-journeys.md](user-journeys.md)         | Role-based journeys                                                     | Draft      |
| [content-guidelines.md](content-guidelines.md)| Upload and moderation content rules                                    | Proposed   |
| [testing.md](testing.md)                     | Smoke-test results + test table for the report                          | Draft      |
| [deployment.md](deployment.md)               | XAMPP demo + submission packaging                                       | Accepted   |
| [research-notes.md](research-notes.md)       | Dated backend limits and takedown facts (historical)                    | Archived   |
| [decision-debates.md](decision-debates.md)   | Both sides argued per pending decision (historical)                     | Archived   |
| [red-team-review.md](red-team-review.md)     | Attacker vs defender pass (historical)                                  | Archived   |
| [schema-postgres-draft.sql](schema-postgres-draft.sql) | Pre-pivot Neon/Postgres draft v0                              | Archived   |
| [task-breakdown.md](task-breakdown.md)       | Phases with per-file tasks and acceptance criteria                      | Current    |
| [report-outline.md](report-outline.md)       | Section map for the coursework report                                   | Accepted   |
| [screenshots.md](screenshots.md)             | Shot checklist (page × role)                                            | Current    |
| [demo-script.md](demo-script.md)             | 9-step viva walkthrough with seeded logins                              | Accepted   |

## Consolidation Map

| Subject                       | Where it lives                       | Why                                   |
| ----------------------------- | ------------------------------------ | ------------------------------------- |
| API endpoints                 | architecture.md route matrix         | Plain-PHP pages, no REST layer        |
| Secrets, checklist, incidents | security.md                          | Small enough at coursework scale      |
| Monitoring                    | deployment.md                        | Demo-scale, logs live in XAMPP        |
| Local setup                   | deployment.md + project-structure.md | XAMPP steps live with the SQL file    |
| Known issues                  | current-state.md                     | Runner of the build                   |

## Open Questions

Non-blocking: D-P09 (final product name — working title retained),
D-P10 (design reference), D-P11 (real hosting beyond the XAMPP demo).
