# Title: Student Note Sharing Portal — Coding Agent Rules

Rules for coding agents and collaborators who write code in this project.

Status: Accepted (implementation live)
Last updated: 2026-10-18

## Read First

[instructions.md](instructions.md), [project-structure.md](project-structure.md),
[architecture.md](architecture.md), [security.md](security.md).

## Constraints

- Stack is **plain PHP 8 + MySQL via PDO** (D-007/D-008, submission-mandated).
  Do not introduce Astro/Tailwind/Node; nothing JS-based without an accepted decision.
- No runtime dependencies at all (no Composer/npm packages).
- Layout per [project-structure.md](project-structure.md); app code lives in `student-note-portal/`.
- Minimal, reviewable changes. No unrelated refactors.
- Validate all input server-side (`require_csrf()`, guards, `store_upload()` are the harness — use them, don't re-roll).
- Authorization checks in `includes/auth.php`; per-row checks in the handling page.
- No secrets in code: DB constants only in `includes/db.php` (swap-ready for env vars, D-P11).

## Testing

`php -l` every touched file; run `php -S 127.0.0.1:8080 -t student-note-portal`
against an imported `note_portal.sql` and click the affected flow. Report actual
output; never claim a test passed without running it. Full table in [testing.md](testing.md).

## Definition of Done

1. Code reviewed against acceptance criteria in [prd.md](prd.md).
2. Tests and lint run, results reported.
3. Security checklist items re-verified ([security.md](security.md)).
4. Docs and [current-state.md](current-state.md) updated.

## Uncertainty

If a requirement is Open, use its documented default ([decisions.md](decisions.md)
Pending column), implement, and name the ceiling with a `ponytail:` comment.
