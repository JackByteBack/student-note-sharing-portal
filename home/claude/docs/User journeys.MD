# Title: Student Note Sharing Portal — User Journeys

Status: Draft (as implemented)
Last updated: 2026-10-18

## Public visitor (no login)

Find class notes fast → index.php: search + filters + tag chips → note.php
(read-only; Save/Report/download hidden or gated) → transparency.php to see how
moderation works. Blocked gracefully: downloads (login prompt), moderated notes (403/404).

## Student

1. Register (role: student) → auto-login → index.
2. Upload.php → title, grouped subject, description, tags, file (PDF/JPG/PNG ≤10 MB),
   attestation → **Submit for review** (or Save as draft → My notes later).
3. Notification flash shows queue expectations; my_notes.php shows status chips: draft/pending.
4. Browse published notes others find → note.php: ★ Save → bookmarks.php; ⚠ Report if a note violates rules.
5. Rejected notes appear in my_notes with reason visible on note.php (rejected status)
   → Re-submit after fixing (goes back to pending).

## Moderator

1. login → review.php: pending queue with uploader, subject, file info.
2. Decide approve/reject/remove per item with reason category + internal note
   (internal never public) → audit row appended.
3. Report inbox: dismiss (keep note) or remove note (resolves report) → row written.
4. Spot-removal table for published notes; audit trail at bottom last 30 actions.

## Admin

1. login → admin.php directly: stat chips, weekly publish bars, top downloads,
   average approval time.
2. Users page: change roles (guarded against self-demotion), delete users
   (delete guarded when they have notes or audit rows).
3. Courses/Subjects maintenance: add/delete (subject delete guarded by notes-FK).
4. Still uses review.php like moderators + keeps the *full* audit history visible.
