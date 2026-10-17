# Title: Student Note Sharing Portal — Demo Script (viva)

Nine steps, ~6–8 minutes, using seeded accounts (deployment.md). Tell the story
along the way; each step ends with the line to remember.

## Setup before the demo

Start Apache + MySQL in XAMPP; note_portal.sql imported
via phpMyAdmin (keep that tab around — step 3 shows it).

## The walk

1. **Anonymously.** Browse → search "joins" → open a tag chip → note card
   counters visible. _Line: public browsing is open; downloads are not._
2. **Try to download without an account** on note.php → "Log in to download".
   _Line: policy D-P08 enforced server-side._
3. **Database peek (optional but strong).** phpMyAdmin: show `transparency_log`
   view next to `moderation_actions` — columns missing = redaction is real.
4. **Student.** Register a new account → login redirect → open navbar:
   Upload / My notes / Saved appear.
5. **Upload.** Pick any subject; add content; check the attestation; click "Submit for review".
   _Line: every upload is moderated — nothing auto-publishes._
6. **Moderator.** Review → pending note seen with decision form → Approve.
   Then Reports inbox → dismiss the open report.
7. **Transparency.** transparency.php → your approve is already public, but no
   moderator name is shown. _Line: users see what admins see, redacted._
8. **Admin.** admin.php → stats + bars + top notes; users table: promote
   the new account; courses section: add a subject → refresh upload page and the
   optgroup reflects it.
9. **Audit trail.** review.php bottom or phpMyAdmin `moderation_actions`:
   rows from approve/attempted demotion → _Line: append-only — even admins
   cannot rewrite history._

## Q&A quick answers

- **Why PHP/MySQL?** Submission requirement; portable XAMPP pipeline.
- **Why sessions not tokens?** v1 browser app with server-rendered pages; cookies httponly.
- **Why not framework?** Viva-friendly; security controls are hand-applied and explicit.
- **What's different from other teams?** Four modules: audit+transparency,
  counters, bookmarks, analytics.
- **Where's the SQL file?** `student-note-portal/note_portal.sql` (schema + seed in one file).
