<?php
/**
 * Router for the PHP built-in dev server (`php -S 127.0.0.1:8080 -t student-note-portal router.php`).
 * Existing files run as-is; anything unknown gets the custom 404 page.
 * (Apache/XAMPP uses .htaccess ErrorDocument instead.)
 */
declare(strict_types=1);

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false; // static asset or an existing .php page — server handles it
}

if ($path === '/' || $path === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}

http_response_code(404);
require __DIR__ . '/404.php';
return true;
