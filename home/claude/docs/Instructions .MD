# Title: Student Note Sharing Portal — Instructions for AI and collaborators

Status: Draft (updated for the PHP implementation)
Last updated: 2026-10-18

## Stack rules (Accepted, D-007/D-008)

- Plain PHP 8, no framework, no Composer/npm runtime deps.
- MySQL 8/MariaDB via PDO. Never auto-switch languages/stacks; the submission mandates PHP.
- Styling is the single hand-written `assets/style.css` using tokens — no CDN/JS.

## Code rules

- Every file opens with `declare(strict_types=1);` and requires `includes/auth.php` for routing pages.
- Permission checks in `includes/auth.php` only; data via `query()` prepared statements.
- Output: `e()` everywhere; `require_csrf()` at top of every POST branch.
- Docs edits: keep `home/claude/docs/` authoritative; do not rewrite history files
  (research-notes, decision-debates, red-team-review) except status lines.

## Definition of Done (unchanged)

1. `php -l` clean on changed files; smoke test affected flow via `php -S`.
2. Acceptance criteria in [prd.md](prd.md) satisfied.
3. Security items touched re-checked in [security.md](security.md).
4. Docs (`current-state`) updated after meaningful changes.

## Uncertainty

If a requirement is Open, use the documented default and mark it with
`ponytail:`-style comment naming the ceiling — do not stall.
