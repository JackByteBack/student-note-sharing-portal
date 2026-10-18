<?php
/**
 * Contact us — how to reach the team (no mail() dependency; XAMPP has no SMTP).
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$title            = 'Contact us';
$meta_description = 'Contact the Student Note Sharing Portal team — moderation appeals, bug reports, account help and moderator applications.';
require __DIR__ . '/includes/header.php';
?>

<div class="card prose">
  <h1>Contact us</h1>
  <p>
    Questions, bug reports, moderation appeals and moderator applications all go to the student team
    behind the portal. We aim to reply within two working days during term.
  </p>

  <h2>Email</h2>
  <ul>
    <li>General questions — <a href="mailto:notes@westfield.edu">notes@westfield.edu</a></li>
    <li>Moderation appeals (include the note title) — <a href="mailto:moderation@westfield.edu">moderation@westfield.edu</a></li>
    <li>Account or privacy requests (deletion, data questions) — <a href="mailto:admin@westfield.edu">admin@westfield.edu</a></li>
  </ul>

  <h2>Offline</h2>
  <p>Student Union, Room 2.14, Westfield College — drop in Wednesdays 13:00–15:00 during term.</p>

  <h2>Before you write</h2>
  <p>
    Many questions are answered in the <a href="index.php#faq">FAQ</a>, the
    <a href="about.php">about page</a>, the <a href="privacy.php">privacy policy</a> or the
    <a href="terms.php">terms &amp; conditions</a>. For a removed note, check the
    <a href="transparency.php">transparency log</a> first — the reason is usually recorded there.
  </p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
