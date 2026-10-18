<?php
/**
 * 404 page. Deliberately standalone (no auth/header include): must render even
 * when the session or DB is broken. Rewired via .htaccess (Apache) and router.php (dev server).
 */
declare(strict_types=1);
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Page not found — Student Note Sharing Portal</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="wrap">
  <div class="card prose" style="text-align:center; padding:3rem 1rem">
    <p style="font-size:3rem; margin:0; font-weight:700">404</p>
    <h1 style="margin:.3rem 0 .6rem">Page not found</h1>
    <p class="muted">That page does not exist — or the note it pointed to was removed.
      Try browsing the catalogue instead.</p>
    <p class="submit-row" style="justify-content:center">
      <a class="btn" href="index.php">Browse notes</a>
      <a class="btn btn-sec" href="index.php#faq">FAQ</a>
      <a class="btn btn-sec" href="contact.php">Contact us</a>
    </p>
  </div>
</main>
</body>
</html>
