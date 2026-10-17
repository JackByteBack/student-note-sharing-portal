<?php
/**
 * Download endpoint — the only way files leave uploads/.
 * Enforces login (D-P08), note visibility and status before streaming,
 * counts the download and serves the stored mime type.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login();

$id   = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (! $id) {
    redirect('index.php');
}

$note = query('SELECT id, uploader_id, status, file_ref, original_name, mime, download_count
               FROM notes WHERE id = ?', [$id])->fetch();
if (! $note) {
    http_response_code(404);
    exit('Note not found.');
}

$user = current_user();
$canDownload =
    $note['status'] === 'published' ||
    ((int) $user['id'] === (int) $note['uploader_id']) ||
    can_moderate();
if (! $canDownload) {
    http_response_code(403);
    exit('This note is not downloadable.');
}

/* Never trust the stored name for path building — file_ref is app-generated,
   and this is the last guard against anything weird in the DB. */
$ref  = basename((string) $note['file_ref']);
$path = UPLOAD_DIR . '/' . $ref;
if (! is_file($path)) {
    http_response_code(404);
    exit('File missing on server — ask the moderator.');
}

query('UPDATE notes SET download_count = download_count + 1 WHERE id = ?', [$id]);

header('Content-Type: ' . ($note['mime'] ?: 'application/octet-stream'));
header('Content-Length: ' . (string) filesize($path));
header('X-Content-Type-Options: nosniff');
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($note['original_name']));
readfile($path);
exit;
