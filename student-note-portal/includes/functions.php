<?php
/**
 * Shared helpers: escaping, CSRF, flash messages, upload validation.
 * Kept small on purpose — permission logic lives in auth.php,
 * data access in the pages that need it.
 */
declare(strict_types=1);

const APP_ROOT     = __DIR__ . '/..';
const UPLOAD_DIR   = __DIR__ . '/../uploads';
const UPLOAD_MAX   = 10 * 1024 * 1024; // 10 MB (D-P04 placeholder limit)
const APP_NAME     = 'Student Note Sharing Portal';

/** Escape every user/dashboard value passed to HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Current base URL path for links in emails/views (unused in v1). */

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

/** Call at the top of every POST handler. Aborts on missing/invalid token. */
function require_csrf(): void
{
    $sent = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? null;
    if (! is_string($sent) || ! hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(400);
        exit('Bad request: CSRF token missing or invalid.');
    }
}

/* ---------- Flash messages (set once, render next render) ---------- */

function flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'][] = ['m' => $message, 't' => $type];
}

function take_flashes(): array
{
    $list = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $list;
}

/* ---------- Redirect helper ---------- */

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/* ---------- Upload validation (D-P04: PDF/JPEG/PNG, 10 MB) ---------- */

/**
 * Validate an uploaded file and move it into uploads/ under a random name.
 * Returns [file_ref, original_name, mime, size_bytes].
 * Never trusts the client-provided filename or content type.
 */
function store_upload(array $filesEntry): array
{
    if (($filesEntry['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed — please attach a file.');
    }
    $tmp = $filesEntry['tmp_name'] ?? '';
    if (! is_uploaded_file($tmp)) {
        throw new RuntimeException('Upload failed — no file received.');
    }
    if (($filesEntry['size'] ?? 0) > UPLOAD_MAX) {
        throw new RuntimeException('File is over the 10 MB limit.');
    }

    // Detect the real content type server-side, not the client-claimed one (T-08).
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($tmp);
    if (! in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
        throw new RuntimeException('Only PDF, JPG and PNG files are allowed.');
    }

    $ext   = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$mime];
    $ref   = bin2hex(random_bytes(16)) . '.' . $ext; // unguessable, no traversal
    $dest  = UPLOAD_DIR . '/' . $ref;

    if (! is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    if (! move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Could not save the file.');
    }

    $original = mb_substr((string) ($filesEntry['name'] ?? 'note'), 0, 255);
    return [$ref, $original, $mime, (int) $filesEntry['size']];
}

/** Parse a comma-separated tag string into clean names (max 8 tags). */
function parse_tags(string $raw): array
{
    $names = preg_split('/[,;\n]+/', $raw) ?: [];
    $names = array_filter(array_map(
        static fn (string $t): string => mb_substr(trim($t), 0, 100),
        $names
    ));
    $names = array_values(array_unique(array_slice($names, 0, 8)));
    return $names;
}

/** Human-readable status for badges. */
function status_badge(string $status): string
{
    $map = [
        'draft'     => ['draft', 'badge-draft'],
        'pending'   => ['pending review', 'badge-pending'],
        'published' => ['published', 'badge-ok'],
        'rejected'  => ['rejected', 'badge-bad'],
        'removed'   => ['removed', 'badge-bad'],
    ];
    [$label, $class] = $map[$status] ?? [$status, ''];
    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
}
