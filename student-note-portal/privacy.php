<?php
/**
 * Privacy policy — what we collect, why, and student-friendly rights.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$title            = 'Privacy policy';
$meta_description = 'How the Student Note Sharing Portal collects, uses and protects your data: account details, uploaded notes, bookmarks, moderation records and your rights.';
require __DIR__ . '/includes/header.php';
?>

<div class="card prose">
  <h1>Privacy policy</h1>
  <p class="meta">Last updated: October 2026 · Applies to the Student Note Sharing Portal operated by Westfield College students.</p>

  <h2>What we collect</h2>
  <ul>
    <li><b>Account data</b> — email address, display name and a password that is stored only as a salted hash.</li>
    <li><b>Content you submit</b> — note titles, descriptions, tags, uploaded files, drafts and any edits.</li>
    <li><b>Activity data</b> — view and download counts per note, your private bookmarks, and reports you file.</li>
    <li><b>Moderation records</b> — actions taken on notes, reason categories and internal moderator notes.</li>
  </ul>

  <h2>Why we use it</h2>
  <ul>
    <li>To run your account, let you upload, download and save notes.</li>
    <li>To moderate the library: moderators see pending notes and reports; admins manage accounts and taxonomy.</li>
    <li>To publish anonymous statistics (view and download counts) and the redacted transparency log.</li>
  </ul>

  <h2>What we never do</h2>
  <ul>
    <li>We do not sell or share your data with advertisers or third parties.</li>
    <li>We do not publish your email address; the public sees only your display name.</li>
    <li>We do not expose uploaded files directly — they are stored under unguessable names and served only through the download page.</li>
  </ul>

  <h2>Moderation transparency</h2>
  <p>
    Every approve/reject/remove action appears in the <a href="transparency.php">public transparency
    log</a> with the reason category. Internal moderator notes and all personal data are redacted from
    that log.
  </p>

  <h2>Retention and your rights</h2>
  <p>
    Data is kept while your account is active. You can ask an administrator to delete your account and
    uploaded notes at any time via the <a href="contact.php">contact page</a>. Removing a note removes
    it from the public catalogue; moderation records of that action are kept in redacted form so the
    log stays honest.
  </p>

  <h2>Cookies and storage</h2>
  <p>
    The portal uses a single session cookie to keep you logged in. There are no tracking cookies, no
    analytics scripts and no third-party embeds — the demo runs fully offline.
  </p>

  <h2>Questions</h2>
  <p>Contact us any time: <a href="contact.php">get in touch</a>.</p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
