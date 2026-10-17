<?php
/**
 * Session bootstrap, authentication and authorization guards.
 * All permission checks route through here — pages never branch on role inline.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // 'secure' => true left off: demo runs on http://localhost in XAMPP.
    ]);
    session_start();
}

/** Who is logged in (array or null). Cached per request (in $GLOBALS so
 *  login_user()/logout_user() can invalidate it mid-request). */
function current_user(): ?array
{
    if (array_key_exists('op_user', $GLOBALS)) {
        return $GLOBALS['op_user'] ?: null;
    }
    $id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (! $id) {
        return $GLOBALS['op_user'] = null;
    }
    $row = query('SELECT id, email, display_name, role, institution_id FROM users WHERE id = ?', [$id])->fetch();
    return $GLOBALS['op_user'] = $row ?: null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/** Stop anonymous users here. */
function require_login(): void
{
    if (current_user() === null) {
        flash('Please log in first.', 'warn');
        redirect('login.php');
    }
}

/** Allow these roles only. Server-side by definition — UI hints are not security. */
function require_role(string ...$roles): void
{
    require_login();
    if (! in_array(current_user()['role'], $roles, true)) {
        http_response_code(403);
        exit('Forbidden: your role cannot access this page.');
    }
}

function can_moderate(): bool
{
    $u = current_user();
    return $u !== null && in_array($u['role'], ['moderator', 'admin'], true);
}

/* ---------- Login / register / logout ---------- */

function login_user(string $email, string $password): bool
{
    $user = query('SELECT id, password_hash FROM users WHERE email = ?', [trim($email)])->fetch();
    if (! $user || ! password_verify($password, $user['password_hash'])) {
        return false;
    }
    session_regenerate_id(true); // stops session fixation (T-05)
    $_SESSION['user_id'] = (int) $user['id'];
    unset($GLOBALS['op_user'], $_SESSION['csrf']);
    return true;
}

function register_user(string $email, string $password, string $displayName): int
{
    $email = trim(mb_strtolower($email));
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter a valid email address.');
    }
    if (strlen($password) < 8) {
        throw new InvalidArgumentException('Password must be at least 8 characters.');
    }
    $displayName = trim($displayName);
    if ($displayName === '' || mb_strlen($displayName) > 100) {
        throw new InvalidArgumentException('Enter a display name (1–100 characters).');
    }
    if (query('SELECT id FROM users WHERE email = ?', [$email])->fetch()) {
        throw new InvalidArgumentException('That email is already registered.');
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    query(
        'INSERT INTO users (institution_id, email, password_hash, display_name)
         VALUES (1, ?, ?, ?)',
        [$email, $hash, $displayName]
    );
    return (int) db()->lastInsertId();
}

function logout_user(): void
{
    $_SESSION = [];
    $GLOBALS['op_user'] = null;
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
