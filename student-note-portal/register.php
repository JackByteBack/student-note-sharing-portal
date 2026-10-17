<?php
/**
 * Registration (FR-03). New users are students; role changes happen in admin.
 * Single-college scope (D-P06): institution_id is fixed to 1.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('index.php');
}

$err  = '';
$mail = '';
$name = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $mail = trim((string) ($_POST['email'] ?? ''));
    $name = trim((string) ($_POST['name'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    $pass2 = (string) ($_POST['password2'] ?? '');
    if ($pass !== $pass2) {
        $err = 'Passwords do not match.';
    } else {
        try {
            register_user($mail, $pass, $name);
            // login_user() regenerates the session id and sets the id — reuse it.
            if (login_user($mail, $pass)) {
                flash('Account created. Welcome to the portal!');
                redirect('index.php');
            }
            flash('Account created — please log in.');
            redirect('login.php');
        } catch (InvalidArgumentException $e) {
            $err = $e->getMessage();
        }
    }
}

$title = 'Register';
require __DIR__ . '/includes/header.php';
?>
<article class="card" style="max-width:440px;margin:0 auto">
  <h1 style="margin-top:0">Create a student account</h1>
  <?php if ($err): ?><div class="flash flash-err"><?= e($err) ?></div><?php endif; ?>
  <form method="post" class="stack">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label>Display name <input type="text" name="name" required maxlength="100" value="<?= e($name) ?>"></label>
    <label>Email <input type="email" name="email" required value="<?= e($mail) ?>"></label>
    <label>Password (min 8 characters) <input type="password" name="password" required minlength="8"></label>
    <label>Repeat password <input type="password" name="password2" required minlength="8"></label>
    <div class="submit-row"><button class="btn">Register</button></div>
  </form>
  <p class="meta" style="margin-top:1rem">Already registered? <a href="login.php">Log in</a>.</p>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
