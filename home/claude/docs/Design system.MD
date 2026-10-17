# Title: Student Note Sharing Portal — Design System

Status: Accepted (working tokens; design reference D-P10 still open)
Last updated: 2026-10-18

Single source: [`student-note-portal/assets/style.css`](../../student-note-portal/assets/style.css).

## Tokens

| Token        | Value      | Use                            |
| ------------ | ---------- | ------------------------------ |
| `--bg`       | #f6f7f9    | page background                |
| `--card`     | #ffffff    | panels                         |
| `--ink`      | #1f2430    | primary text                   |
| `--muted-text` | #5c6478  | secondary text                 |
| `--brand`    | #4654e0    | links, buttons, active states  |
| `--brand-dark` | #3a45c4  | button hover                   |
| `--ok`/`--warn`/`--bad` | green/amber/red | status badges      |
| `--line`     | #e3e6ee    | hairline borders               |

Type: system-ui stack (works on all grading machines), base 16px/1.55.

## Components

- `.topbar` sticky nav with role-conditional links (server-rendered, no JS)
- `.card` general panel; `.grid` auto-fill note cards; `.note-card` h3 + preview + meta
- `.badge` + variants map notes statuses (draft/pending/ok/bad) and role chip
- `.btn`, `.btn-sec`, `.btn-danger` — destructive actions flagged (moderation remove)
- `.filters` bar, `.chips` tag filter pills, `.pager` chips
- `.table` for my-notes/review/admin listings
- `.stat` + `.bars` inline SVG-free analytics bars (admin dashboard)
- `.flash` + colors for one-shot action feedback

## Accessibility

- Every input has a `<label>`; decorative icon buttons include text too.
- `aria-label` on search/filter selects; status color never the only signal (text inside).
- Forms submit natively (enter key works) — no JS required anywhere.
- Contrast: tokens tested by eye against AA for body text sizes used.

## Status wording

Status wording: draft / pending review / published / rejected / removed — shown through
`status_badge()`; the same wording appears in my_notes, review queue, and note detail.
