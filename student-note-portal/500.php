<?php
/**
 * 500 page. Standalone (no auth/header include): the DB is the usual suspect when
 * this renders. Reached via .htaccess ErrorDocument (Apache); the dev server can be
 * shown it directly at /500.php.
 */
declare(strict_types=1);
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Something broke — Student Note Sharing Portal</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="wrap">
  <div class="card prose" style="text-align:center; padding:3rem 1rem">
    <p style="font-size:3rem; margin:0; font-weight:700">500</p>
    <h1 style="margin:.3rem 0 .6rem">Something broke on our side</h1>
    <p class="muted">The error has been noted. Try again in a moment — if it keeps happening,
      tell us on the contact page.</p>
    <p class="submit-row" style="justify-content:center">
      <a class="btn" href="index.php">Back to the homepage</a>
      <a class="btn btn-sec" href="contact.php">Report the problem</a>
    </p>
  </div>
</main>
</body>
</html>
