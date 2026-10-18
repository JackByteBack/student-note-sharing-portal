<?php
/**
 * Shared page header. Expects $title (string) and optionally $auth_loaded pages
 * that have already required auth include. Loads session for everyone.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$user    = current_user();
$flashes = take_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? APP_NAME) ?> — <?= APP_NAME ?></title>
<?php
/* SEO: pages may override via $meta_description / $canonical / $json_ld. */
$scheme    = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$canonical = $canonical ?? ($scheme . '://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'));
$desc      = $meta_description ?? 'A free, moderated study-notes library: students share lecture notes as PDF or images, every upload is human-reviewed, and anyone can browse by course and subject.';
?>
<meta name="description" content="<?= e($desc) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:site_name" content="<?= e(APP_NAME) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e(($title ?? APP_NAME) . ' — ' . APP_NAME) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta name="twitter:card" content="summary">
<?php if (! empty($json_ld)): ?>
<script type="application/ld+json"><?= $json_ld ?></script>
<?php endif; ?>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
  <a class="brand" href="index.php"><?= e(APP_NAME) ?></a>
  <nav>
    <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">Browse</a>
    <a href="transparency.php" class="<?= basename($_SERVER['PHP_SELF']) === 'transparency.php' ? 'active' : '' ?>">Transparency</a>
    <?php if ($user): ?>
      <a href="upload.php">Upload</a>
      <a href="my_notes.php">My notes</a>
      <a href="bookmarks.php">Saved</a>
    <?php endif; ?>
    <?php if ($user && in_array($user['role'], ['moderator', 'admin'], true)): ?>
      <a href="review.php" class="<?= basename($_SERVER['PHP_SELF']) === 'review.php' ? 'active' : '' ?>">Review</a>
    <?php endif; ?>
    <?php if ($user && $user['role'] === 'admin'): ?>
      <a href="admin.php">Admin</a>
    <?php endif; ?>
  </nav>
  <div class="account">
    <?php if ($user): ?>
      <span class="who"><?= e($user['display_name']) ?> <em class="badge badge-role"><?= e($user['role']) ?></em></span>
      <a class="btn btn-sec" href="logout.php">Log out</a>
    <?php else: ?>
      <a class="btn btn-sec" href="login.php">Log in</a>
      <a class="btn" href="register.php">Register</a>
    <?php endif; ?>
  </div>
</header>

<main class="wrap">
<?php foreach ($flashes as $f): ?>
  <div class="flash flash-<?= e($f['t']) ?>"><?= e($f['m']) ?></div>
<?php endforeach; ?>
