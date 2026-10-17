# Title: Student Note Sharing Portal — Decision Debates

Each pending decision argued from opposing sides, with a verdict for the owner to accept or reject.

Status: Proposed
Last updated: 2026-10-18

Verdicts are **Proposed**. Nothing is approved until you move it to Accepted in [decisions.md](decisions.md). Facts come from [research-notes.md](research-notes.md).

---

## 1. D-P01 — "Users can see everything the admin sees"

**Side A, radical transparency:** Open moderation builds trust. Students see why a note was removed, and bans can't look arbitrary.

**Side B, privacy first:** Admin data holds emails, reporter identities, and internal notes. Exposing them invites retaliation against reporters and harassment of moderators.

**Cross-examination:** A concedes identities must stay hidden. B concedes that decisions and reasons can be public.

**Verdict (Proposed):** Tiered view. Logged-in users see status, action type, reason category, timestamp, and actor _role_ (not name). Public visitors see aggregate counts only. Emails, reporter IDs, IPs, and internal notes stay admin-only.

**What would flip it:** You confirm in writing that you meant literally all admin data. Then do a privacy review first.

---

## 2. D-P02 — Backend and hosting

|         | Supabase                                                                                 | Cloudflare (Pages + D1 + R2)                                            | Firebase                                                                                                                            |
| ------- | ---------------------------------------------------------------------------------------- | ----------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| For     | Login, database, files, and per-role access rules in one service; documented Astro guide | Most generous free storage (R2 10 GB, free egress); no inactivity pause | Mature auth; no-cost quotas                                                                                                         |
| Against | Free: 1 GB files, pauses after 1 week idle, no backups                                   | No bundled login found; you build auth and access rules yourself        | Quota overrun shuts the product off; storage quota unverified; NoSQL fits relational data (courses, subjects, notes) less naturally |

**Pro-Supabase argument:** The role model (student, moderator, admin) maps to row-level rules, and an auth bug is the most dangerous bug here.

**Pro-Cloudflare argument:** At about 3 MB per note, 1 GB holds roughly 330 notes (assumption), so Supabase Free fills fast, while R2 holds about 10x more at no egress cost.

**Cross-examination:** Supabase's reply is that Pro ($25/mo) gives 100 GB and daily backups, so upgrading is a money problem, not a rewrite. Cloudflare's reply is that building auth yourself is the costly part, so the savings disappear.

**Verdict (Proposed):** Supabase for the build phase, since auth correctness matters most. Set a trigger: revisit when storage passes 70%, or before real students onboard (backups and the idle pause matter then). Moving files to R2 later is a second data flow and needs its own decision. Treat hosting as a separate choice, because Astro auth needs a server-capable host.

**What would flip it:** Budget is strictly zero past 330 notes, or you prefer building auth.

---

## 3. D-P03 and D-P06 — Who can sign up

**Side A, open sign-up:** Any email, maximum reach, alumni and other colleges included.

**Side B, college-email only:** Less spam, more accountability, easier moderation.

**Cross-examination:** B concedes that a domain check is worthless without email confirmation. The Supabase guide supports confirmation links. A concedes that open sign-up multiplies moderation load and copyright risk.

**Verdict (Proposed):** Public can browse. Uploading needs a _verified_ institution email. Add an `institution_id` field from day one so adding colleges later is cheap.

**What would flip it:** You want a public community across colleges.

---

## 4. D-P05 — Moderate before or after publishing

**Pre-moderation:** Blocks bad or infringing files from ever going live. Cost: review delay discourages uploaders.

**Post-moderation:** Instant publishing. Cost: harmful files are public until someone reports them.

**Verdict (Proposed):** Pre-moderation at launch, plus a trusted fast lane after a set number of approved notes with no reports. The number (suggest 5) and a review target (suggest 48 hours) are placeholders for you to set.

**What would flip it:** Median review time stays above your target, or you have too few moderators.

---

## 5. D-P07 and D-P08 — Copyright and downloads

**Permissive side:** Students share their own notes. Heavy rules kill adoption.

**Cautious side:** Notes often include scanned textbook pages and slides. The platform hosts third-party content and may carry takedown duties.

**Fact check:** One secondary source describes about 36-hour takedown expectations and a 21-day access-withholding window in India. It is **unverified**, and I'm not a lawyer.

**Verdict (Proposed):** Upload requires an ownership checkbox. Every note has a Report button. Publish a takedown contact and process, aiming at the 36-hour figure until a lawyer says otherwise. Public visitors get preview only, and download needs login, which also slows scraping.

**What would flip it:** Legal advice, or a college policy that sets its own rules.
