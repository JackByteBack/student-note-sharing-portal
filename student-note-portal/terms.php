<?php
/**
 * Terms and conditions — acceptable use, uploads, moderation, accounts.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$title            = 'Terms & conditions';
$meta_description = 'Terms of use for the Student Note Sharing Portal: acceptable use, upload rules, moderation, accounts and disclaimers.';
require __DIR__ . '/includes/header.php';
?>

<div class="card prose">
  <h1>Terms &amp; conditions</h1>
  <p class="meta">Last updated: October 2026 · By using the portal you agree to these terms.</p>

  <h2>The service</h2>
  <p>
    The Student Note Sharing Portal is a student-run, moderated library for sharing study notes. It is
    provided free of charge for educational use. Availability is best-effort; the portal may be taken
    offline for maintenance.
  </p>

  <h2>Your account</h2>
  <ul>
    <li>One account per person; keep your password secret and tell us about any misuse.</li>
    <li>Use a real email address so moderators can reach you about your uploads.</li>
    <li>Administrators may suspend accounts that break these terms.</li>
  </ul>

  <h2>Uploading — your promises</h2>
  <ul>
    <li>Only upload work you wrote yourself or have the right to share. Uploading copyrighted material without permission is not allowed.</li>
    <li>No exam papers, answer keys distributed under restriction, personal data of other people, or abusive content.</li>
    <li>Files must be PDF, JPG or PNG and no larger than 10&nbsp;MB.</li>
    <li>You keep ownership of your work; you grant other students a licence to view and download it for their own study.</li>
  </ul>

  <h2>Moderation</h2>
  <p>
    Nothing you upload is public until a moderator approves it. Moderators may reject or remove notes
    that break these rules, and their actions are published in the
    <a href="transparency.php">transparency log</a>. You can appeal a decision via the
    <a href="contact.php">contact page</a>.
  </p>

  <h2>Downloading and reuse</h2>
  <p>
    Downloaded notes are for your personal study. Do not redistribute them publicly, sell them, or
    republish them as your own — that breaches both these terms and your classmate's trust.
  </p>

  <h2>Disclaimer</h2>
  <p>
    Notes are student contributions and may contain mistakes. They are not official college material
    and are not a substitute for lectures, assigned reading or academic advice. Use them at your own
    discretion.
  </p>

  <h2>Changes</h2>
  <p>We may update these terms; the date above reflects the latest revision.</p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
