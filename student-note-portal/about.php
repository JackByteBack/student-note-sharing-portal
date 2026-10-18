<?php
/**
 * About us — also the long-form SEO page describing the tool (~1000 words).
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$title            = 'About us';
$meta_description = 'The Student Note Sharing Portal is a free, moderated study-notes library: students share lecture notes as PDF or images, moderators review every upload, and everyone browses by course and subject.';
require __DIR__ . '/includes/header.php';
?>

<div class="card prose">
  <h1>About the Student Note Sharing Portal</h1>

  <p>
    The <b>Student Note Sharing Portal</b> is a free, moderated library where students share their
    study notes with each other. Instead of notes living in private chat groups, lost folders and
    expensive photocopy stacks, they live in one searchable place: browse by course and subject,
    open a note page, and download a clean PDF or image in seconds. Every upload is read by a
    human moderator before it appears publicly, so the library stays useful, legal and safe —
    which is what separates a shared notes library from an unmoderated file dump.
  </p>

  <h2>Why it exists</h2>
  <p>
    Good notes are one of the most effective study aids a student can have. Summarising a lecture
    in your own words cements the material, and reading how a classmate explained the same topic
    often clarifies the parts the textbook glides over. Yet notes are hard to share well. They end
    up in closed messaging groups where nothing is searchable, they get renamed beyond recognition,
    and nobody can tell whether a file is this year's syllabus or an outdated draft. Students who
    miss a lecture — through illness, placement or a timetable clash — lose out the most. This
    portal solves that with three ideas: <b>one shared library</b>, <b>strong browsing tools</b> and
    <b>human moderation</b>.
  </p>

  <h2>How the portal works</h2>
  <p>
    Anyone can visit the portal and read the catalogue — no account is needed to search, filter and
    open note pages. Creating a free account unlocks the rest: downloading files, uploading your own
    notes, and saving favourites. Uploading takes about a minute. You attach a PDF, JPG or PNG up to
    10&nbsp;MB, pick the course and subject it belongs to, add a title, a short description and up to
    eight tags, and confirm the ownership attestation — a checkbox stating that the work is yours or
    that you have the right to share it. From there the note enters the moderation queue as a
    <em>pending</em> note. You can still edit it as a draft, but the public cannot see it until a
    moderator approves it.
  </p>
  <p>
    Moderators are students and staff trusted with the Review queue. For every submission they can
    <b>approve</b> it — which publishes it to the public catalogue — <b>reject</b> it with a reason
    such as low quality or a missing citation, or <b>remove</b> it later if a policy violation
    surfaces. Every action is recorded with a reason category, and those records power the portal's
    signature feature: the <a href="transparency.php">transparency log</a>. It shows what was
    approved, rejected or removed and why, with private data redacted. Moderation you can audit
    builds trust that moderation you cannot see never will.
  </p>

  <h2>Finding the notes you need</h2>
  <p>
    The <a href="index.php">browse page</a> is built for speed. A full-text search matches words in
    titles and descriptions, two dropdowns narrow the catalogue to a course or a single subject, and
    clickable tag chips surface the topics students use most — think <code>#exam-prep</code>,
    <code>#formula-sheet</code> or <code>#summary</code>. Each note page shows who uploaded the note,
    when, its subject and course, the file size, and how many times it has been viewed and
    downloaded, so you can judge its usefulness before opening it. Popular, recent material rises to
    the top because the catalogue sorts by publication date and engagement.
  </p>

  <h2>Quality, safety and copyright</h2>
  <p>
    A shared library only works if people trust it. Three rules keep the portal trustworthy.
    First, <b>moderation is mandatory</b>: nothing a student uploads is public until a moderator
    approves it. Second, <b>reporting is built in</b>: every published note has a report button with
    predefined reasons — copyright, low quality, duplicate, off topic, abusive content or a privacy
    concern — and reports go straight to the moderation queue. Third, <b>files are checked, not
    trusted</b>: the server inspects each upload's real content type and only accepts PDF, JPEG and
    PNG files, stored under unguessable names that are never directly web-accessible. The portal is
    for original study material — your own summaries, your own diagrams, your own past-paper
    walkthroughs — not for redistributing textbooks, exam papers or copyrighted slides.
  </p>

  <h2>Privacy by default</h2>
  <p>
    The portal collects the minimum it needs to run: an email address and display name at
    registration, the notes you upload, and private bookmarks that only you can see. Downloads
    require a logged-in account, which keeps files out of anonymous scrapers. Moderators see internal
    notes; the public log never shows them or any personal data. Full details live in our
    <a href="privacy.php">privacy policy</a> and <a href="terms.php">terms &amp; conditions</a>.
  </p>

  <h2>Who runs it</h2>
  <p>
    The portal began as a student software project at Westfield College, built with plain PHP and
    MySQL so it can run on any campus server with no paid dependencies. It is maintained by student
    volunteers, and the moderator team is open to applications each term. Questions, bug reports,
    moderation appeals and partnership ideas are all welcome on the <a href="contact.php">contact
    page</a>.
  </p>

  <h2>Start here</h2>
  <p>
    Browse the <a href="index.php">catalogue</a>, read the <a href="index.php#faq">frequently asked
    questions</a>, then <a href="register.php">create a free account</a> to download notes or upload
    the set you are proud of. If you only remember one thing about this portal: someone else's
    carefully written notes might be the study session you have been missing — and yours might be
    that resource for someone else.
  </p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
