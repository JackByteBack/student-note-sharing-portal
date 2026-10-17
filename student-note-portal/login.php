<?php
/**
 * Login (FR-03). Email + password via password_verify; CSRF protected.
 * Redirects mod/admin to their panels afterwards for demo convenience.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('index.php');
}

$err  = '';
$mail = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $mail = trim((string) ($_POST['email'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    if ($mail === '' || $pass === '') {
        $err = 'Enter your email and password.';
    } elseif (login_user($mail, $pass)) {
        flash('Welcome back!');
        $role = current_user()['role'];
        redirect($role === 'admin' ? 'admin.php' : ($role === 'moderator' ? 'review.php' : 'index.php'));
    } else {
        $err = 'Wrong email or password.';
    }
}

$title = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<article class="card" style="max-width:440px;margin:0 auto">
  <h1 style="margin-top:0">Log in</h1>
  <?php if ($err): ?><div class="flash flash-err"><?= e($err) ?></div><?php endif; ?>
  <form method="post" class="stack">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label>Email <input type="email" name="email" required autofocus value="<?= e($mail) ?>"></label>
    <label>Password <input type="password" name="password" required></label>
    <div class="submit-row"><button class="btn">Log in</button></div>
  </form>
  <p class="meta" style="margin-top:1rem">No account? <a href="register.php">Register as a student</a>.</p>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
