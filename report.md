# Student Note Sharing Portal — Complete Source Code Appendix

Full source listing of the submitted application, grouped by layer.
Narrative report: `report/REPORT.md`. Short curated excerpt version: see git history.

**Tree**

```
student-note-portal/
├── includes/          db, auth, helpers, layout
├── uploads/           stored files (web-denied, served via download.php)
├── index.php          public browse + search
├── note.php           detail, report, bookmark, inline moderation
├── download.php       authorized file streaming
├── upload.php         submit / draft flow
├── my_notes.php       owner's notes
├── bookmarks.php      saved notes
├── review.php         moderation queue, report inbox, audit trail
├── admin.php          users/roles, taxonomy, analytics
├── transparency.php   public redacted moderation log
├── login.php / register.php / logout.php
├── about.php / contact.php / privacy.php / terms.php
├── 404.php / 500.php / router.php
├── .htaccess / robots.txt / sitemap.xml
└── note_portal.sql    schema + seed (import once)
```

---

## Core includes (`includes/`)

### `student-note-portal/includes/db.php`

````text
<?php
/**
 * DB connection — PDO only, real prepared statements (no emulation).
 * XAMPP defaults: root with empty password. Change here if yours differs.
 */
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'note_portal';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

/** Shorthand: prepare + execute + return the statement. */
function query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
````

### `student-note-portal/includes/auth.php`

````text
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
````

### `student-note-portal/includes/functions.php`

````text
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

/** Base URL of the install (scheme://host/folder) derived from the request —
 *  works on localhost, XAMPP htdocs and any real domain without config. */
function site_url(string $path = ''): string
{
    $https = ($_SERVER['HTTPS'] ?? '') === 'on';
    $dir   = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
    $dir   = in_array($dir, ['/', '.', ''], true) ? '' : $dir;
    return ($https ? 'https' : 'http') . '://'
         . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir . '/'
         . ltrim($path, '/');
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
````

### `student-note-portal/includes/header.php`

````text
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
````

### `student-note-portal/includes/footer.php`

````text
</main>
<footer class="foot">
  <p><?= e(APP_NAME) ?> — a student-run project of Westfield College.</p>
  <p>Moderation actions are public in the <a href="transparency.php">transparency log</a> with private data redacted.</p>
  <p>
    <a href="about.php">About us</a> ·
    <a href="index.php#faq">FAQ</a> ·
    <a href="contact.php">Contact us</a> ·
    <a href="privacy.php">Privacy policy</a> ·
    <a href="terms.php">Terms &amp; conditions</a>
  </p>
</footer>
</body>
</html>
````

---

## Application pages

### `student-note-portal/index.php`

````text
<?php
/**
 * Public browse page — published notes with search, filters, tags, pagination.
 * FR-02 / FR-05. No login required; downloads gated on note.php.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$where  = ["n.status = 'published'"];
$params = [];

/* Keyword search: FULLTEXT prefix search for normal words, LIKE for short ones.
   ponytail: FULLTEXT misses <3-char tokens by design; LIKE fallback covers those. */
$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    if (mb_strlen($q) >= 3) {
        $where[]   = 'MATCH(n.title, n.description) AGAINST(? IN BOOLEAN MODE)';
        $params[]  = '*' . str_replace(['+', '-', '*', '"'], ' ', $q) . '*';
    } else {
        $where[]  = '(n.title LIKE ? OR n.description LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
}

$subjectId = filter_input(INPUT_GET, 'subject', FILTER_VALIDATE_INT);
if ($subjectId) {
    $where[]   = 'n.subject_id = ?';
    $params[]  = $subjectId;
} else {
    // Course filter narrows subjects; kept separate so subject always wins if both given.
    $courseId = filter_input(INPUT_GET, 'course', FILTER_VALIDATE_INT);
    if ($courseId) {
        $where[]  = 's.course_id = ?';
        $params[] = $courseId;
    }
}

$tag = trim((string) ($_GET['tag'] ?? ''));
if ($tag !== '') {
    $where[]   = 'n.id IN (SELECT nt.note_id FROM note_tags nt JOIN tags t ON t.id = nt.tag_id WHERE t.name = ?)';
    $params[]  = $tag;
}

$baseWhere = implode(' AND ', $where);

/* Pagination (12 per page). */
$perPage  = 12;
$page     = max(1, (int) ($_GET['page'] ?? 1));
$countRow = query(
    "SELECT COUNT(*) c FROM notes n
     JOIN subjects s ON s.id = n.subject_id
     JOIN courses  co ON co.id = s.course_id
     WHERE $baseWhere",
    $params
)->fetch();
$total    = max(1, (int) $countRow['c']);
$pages    = (int) ceil($total / $perPage);
$page     = min($page, $pages);
$offset   = ($page - 1) * $perPage;

$rows = query(
    "SELECT n.id, n.title, n.description, n.created_at, n.view_count, n.download_count,
            n.original_name, n.size_bytes,
            s.name AS subject, co.name AS course, u.display_name AS uploader
     FROM notes n
     JOIN subjects s ON s.id = n.subject_id
     JOIN courses  co ON co.id = s.course_id
     JOIN users    u  ON u.id  = n.uploader_id
     WHERE $baseWhere
     ORDER BY n.published_at DESC, n.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
)->fetchAll();

$courses  = query('SELECT id, name FROM courses ORDER BY name')->fetchAll();
$subjects = query('SELECT s.id, s.name, s.course_id, c.name AS course
                   FROM subjects s JOIN courses c ON c.id = s.course_id
                   ORDER BY c.name, s.name')->fetchAll();
$tagChips = query(
    "SELECT t.name, COUNT(nt.note_id) AS c
     FROM tags t
     JOIN note_tags nt ON nt.tag_id = t.id
     JOIN notes n ON n.id = nt.note_id AND n.status = 'published'
     GROUP BY t.id, t.name
     ORDER BY c DESC, t.name
     LIMIT 12"
)->fetchAll();

function keep(string ...$pairs): array
{
    // Preserve existing filters when building sort/pagination links.
    $out = [];
    foreach (array_chunk($pairs, 2) as [$k, $v]) {
        if ($v !== '' && $v !== null) {
            $out[$k] = $v;
        }
    }
    return $out;
}
$qs = static function (array $base): string
{
    return '?' . http_build_query($base);
};

$title = 'Browse notes';
$meta_description = 'Browse free study notes shared by students — search lecture notes, summaries and revision guides by course, subject and tag. Every note is moderated before publication.';

/* FAQ content: rendered as details/summary and as FAQPage structured data. */
$faq = [
    ['What is the Student Note Sharing Portal?',
     'A free, moderated library where students share study notes. Anyone can browse and read the catalogue; uploaded files are checked by a student moderator before they appear publicly.'],
    ['Do I need an account?',
     'No — searching, filtering and opening note pages works without one. You need a free account to download files, upload your own notes or save favourites.'],
    ['What files can I upload?',
     'PDF, JPG or PNG, up to 10 MB. The server checks the real file type of every upload, so renamings do not get through. Add a title, description and up to eight tags so people can find it.'],
    ['How does moderation work?',
     'Every upload enters a review queue. A moderator approves it (publishing it), rejects it with a reason such as low quality, or removes it later for a policy violation. All actions appear in the public transparency log.'],
    ['How do I report a bad note?',
     'Open the note and use the report button — pick a reason like copyright, low quality or privacy, add details, and it goes straight to the moderation queue. You cannot report your own notes.'],
    ['Is it really free?',
     'Yes. There are no ads, no paid tier and no data selling. The portal is a student-run project that runs on plain PHP and MySQL.'],
    ['My note was rejected or removed — what now?',
     'Check the transparency log for the reason category, fix the issue, and upload a corrected version. You can also appeal by email via the contact page.'],
];
$json_ld = json_encode([
    '@context'        => 'https://schema.org',
    '@type'           => 'FAQPage',
    'mainEntity'      => array_map(static fn (array $qa): array => [
        '@type'          => 'Question',
        'name'           => $qa[0],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]],
    ], $faq),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

require __DIR__ . '/includes/header.php';
?>

<div class="card prose" style="margin-bottom:1.2rem">
  <h1 style="margin:.2rem 0 .4rem">Free study notes, shared by students — reviewed by students</h1>
  <p style="margin:0">
    Search <?= (int) $total ?> published note<?= $total === 1 ? '' : 's' ?> across courses and subjects:
    lecture summaries, revision guides and past-paper walkthroughs uploaded by classmates. Every file is
    human-moderated before it goes public. Read more <a href="about.php">about the portal</a>.
  </p>
</div>

<form class="filters" method="get">
  <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search title or description…" aria-label="Search">
  <select name="course" aria-label="Course">
    <option value="">All courses</option>
    <?php foreach ($courses as $c): ?>
      <option value="<?= (int) $c['id'] ?>" <?= (!$subjectId && (int) ($_GET['course'] ?? 0) == $c['id']) ? 'selected' : '' ?>>
        <?= e($c['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <select name="subject" aria-label="Subject">
    <option value="">All subjects</option>
    <?php foreach ($subjects as $s): ?>
      <option value="<?= (int) $s['id'] ?>" <?= $subjectId === (int) $s['id'] ? 'selected' : '' ?>>
        <?= e($s['course'] . ' — ' . $s['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <input type="hidden" name="tag" value="<?= e($tag) ?>">
  <button class="btn">Filter</button>
  <a class="btn btn-sec" href="index.php">Reset</a>
</form>

<?php if ($tagChips): ?>
<div class="chips">
  <a class="chip <?= $tag === '' ? 'on' : '' ?>" href="<?= e($qs(keep('q', $q, 'course', (string) ($_GET['course'] ?? ''), 'subject', (string) $subjectId))) ?>">all</a>
  <?php foreach ($tagChips as $tc): ?>
    <a class="chip <?= $tag === $tc['name'] ? 'on' : '' ?>"
       href="<?= e($qs(keep('q', $q, 'course', (string) ($_GET['course'] ?? ''), 'subject', (string) $subjectId, 'tag', $tc['name']))) ?>">
      #<?= e($tc['name']) ?> (<?= (int) $tc['c'] ?>)
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<p class="meta"><?= (int) $total ?> published note<?= $total === 1 ? '' : 's' ?></p>

<?php if (! $rows): ?>
  <div class="card">
    <p>No notes match. Try a different search or clear the filters.</p>
    <?php if (is_logged_in()): ?>
      <p><a class="btn" href="upload.php">Upload the first one</a></p>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="grid">
    <?php foreach ($rows as $r): ?>
      <article class="card note-card">
        <h3><a href="note.php?id=<?= (int) $r['id'] ?>"><?= e($r['title']) ?></a></h3>
        <p><?= e(mb_strimwidth((string) ($r['description'] ?? ''), 0, 110, '…')) ?></p>
        <p class="meta">
          <b><?= e($r['subject']) ?></b> · <?= e($r['course']) ?><br>
          by <?= e($r['uploader']) ?> ·
          <?= (int) $r['view_count'] ?> views · <?= (int) $r['download_count'] ?> downloads
        </p>
      </article>
    <?php endforeach; ?>
  </div>

  <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Pages">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
        <?php if ($p === $page): ?>
          <span class="chip on"><?= $p ?></span>
        <?php else: ?>
          <a class="chip" href="<?= e($qs(array_filter(array_merge($_GET, ['page' => $p]), static fn ($v) => $v !== ''))) ?>"><?= $p ?></a>
        <?php endif; ?>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>

<section class="card faq" id="faq" style="margin-top:1.4rem">
  <h2>Frequently asked questions</h2>
  <?php foreach ($faq as $qa): ?>
    <details>
      <summary><?= e($qa[0]) ?></summary>
      <p><?= e($qa[1]) ?></p>
    </details>
  <?php endforeach; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
````

### `student-note-portal/note.php`

````text
<?php
/**
 * Note detail page — visibility by role, view counter, report, bookmark,
 * and inline moderation actions for moderators/admins.
 * FR-06, FR-07, FR-08 (+ D-P08: public view, login to download).
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (! $id) {
    redirect('index.php');
}

$note = query(
    "SELECT n.*, s.name AS subject, s.course_id, c.name AS course,
            u.display_name AS uploader
     FROM notes n
     JOIN subjects s ON s.id = n.subject_id
     JOIN courses  c ON c.id = s.course_id
     JOIN users    u ON u.id  = n.uploader_id
     WHERE n.id = ?",
    [$id]
)->fetch();
if (! $note) {
    http_response_code(404);
    exit('Note not found.');
}

$user        = current_user();
$isOwner     = $user && (int) $user['id'] === (int) $note['uploader_id'];
$isModerator = can_moderate();
$isAdmin     = $user && $user['role'] === 'admin';

/* Visibility rules (server-side, per role). */
$allowed = match ($note['status']) {
    'published'                    => true,
    'draft', 'pending', 'rejected' => $isOwner || $isModerator,
    'removed'                      => $isAdmin || ($isOwner && $isModerator),
};
if (! $allowed) {
    http_response_code($user ? 403 : 401);
    exit('This note is not available.');
}

/* POST: bookmark toggle, report, moderation action. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $act = (string) ($_POST['act'] ?? '');

    if ($act === 'bookmark') {
        require_login();
        $exists = query('SELECT 1 FROM bookmarks WHERE user_id = ? AND note_id = ?',
                        [$user['id'], $id])->fetch();
        if ($exists) {
            query('DELETE FROM bookmarks WHERE user_id = ? AND note_id = ?', [$user['id'], $id]);
            flash('Removed from saved notes.');
        } else {
            query('INSERT INTO bookmarks (user_id, note_id) VALUES (?, ?)', [$user['id'], $id]);
            flash('Saved to your bookmarks.');
        }
        redirect('note.php?id=' . $id);
    }

    if ($act === 'report') {
        require_login();
        $reason  = (string) ($_POST['reason'] ?? 'other');
        $reasons = ['copyright', 'low_quality', 'duplicate', 'off_topic', 'abusive', 'privacy', 'other'];
        if (! in_array($reason, $reasons, true)) {
            flash('Choose a valid reason.', 'err');
            redirect('note.php?id=' . $id);
        }
        if ((int) $note['uploader_id'] === (int) $user['id']) {
            flash('You cannot report your own note.', 'warn');
            redirect('note.php?id=' . $id);
        }
        try {
            query(
                'INSERT INTO reports (note_id, reporter_id, reason, details) VALUES (?, ?, ?, ?)',
                [$id, $user['id'], $reason, mb_substr(trim((string) ($_POST['details'] ?? '')), 0, 1000)]
            );
            flash('Report sent. A moderator will review it.');
        } catch (PDOException) {
            flash('You already reported this note.', 'warn');
        }
        redirect('note.php?id=' . $id);
    }

    if ($act === 'moderate' && $isModerator) {
        require_role('moderator', 'admin');
        $action  = (string) ($_POST['action'] ?? '');
        $actions = ['approve' => 'published', 'reject' => 'rejected', 'remove' => 'removed'];
        if (! isset($actions[$action])) {
            flash('Unknown moderation action.', 'err');
            redirect('note.php?id=' . $id);
        }
        $category = (string) ($_POST['reason_category'] ?? '');
        $cats     = ['copyright', 'low_quality', 'duplicate', 'off_topic', 'abusive', 'privacy', 'other'];
        $internal = mb_substr(trim((string) ($_POST['internal_note'] ?? '')), 0, 1000);

        query('UPDATE notes SET status = ?, published_at = ? WHERE id = ?', [
            $actions[$action],
            $action === 'approve' ? date('Y-m-d H:i:s') : $note['published_at'],
            $id,
        ]);
        query(
            'INSERT INTO moderation_actions (note_id, actor_id, action, reason_category, internal_note)
             VALUES (?, ?, ?, ?, ?)',
            [$id, $user['id'], $action, in_array($category, $cats, true) ? $category : null, $internal]
        );
        flash('Note ' . $action . 'd.');
        redirect('note.php?id=' . $id);
    }

    redirect('note.php?id=' . $id);
}

/* Public counter: published views count, except the author's own visits. */
if ($note['status'] === 'published' && ! $isOwner) {
    query('UPDATE notes SET view_count = view_count + 1 WHERE id = ?', [$id]);
    $note['view_count']++;
}

$bookmarked = $user && query('SELECT 1 FROM bookmarks WHERE user_id = ? AND note_id = ?',
                             [$user['id'], $id])->fetch();
$tags = query(
    'SELECT t.name FROM tags t JOIN note_tags nt ON nt.tag_id = t.id WHERE nt.note_id = ? ORDER BY t.name',
    [$id]
)->fetchAll();
$myReport = query('SELECT status FROM reports WHERE note_id = ? AND reporter_id = ?', [$id, ($user['id'] ?? 0)])->fetch();

$title = $note['title'];
$meta_description = ($note['description'] ?? '') !== ''
    ? mb_substr(trim((string) $note['description']), 0, 155)
    : 'Study note "' . $note['title'] . '" — ' . $note['subject'] . ', ' . $note['course'] . ' — free download on ' . APP_NAME . '.';
require __DIR__ . '/includes/header.php';
?>

<article class="card stack">
  <p class="meta">
    <?= e($note['subject']) ?> · <?= e($note['course']) ?>
    · by <b><?= e($note['uploader']) ?></b>
    · uploaded <?= e(date('j M Y', strtotime($note['created_at']))) ?>
    · <?= status_badge($note['status']) ?>
  </p>
  <h1 style="margin:0"><?= e($note['title']) ?></h1>
  <p style="white-space:pre-line"><?= e($note['description'] ?? '') ?></p>

  <?php if ($tags): ?>
  <div class="chips" style="margin-bottom:0">
    <?php foreach ($tags as $t): ?>
      <a class="chip" href="index.php?tag=<?= e($t['name']) ?>">#<?= e($t['name']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <p class="meta">
    `<?= e($note['original_name']) ?>` ·
    <?= number_format($note['size_bytes'] / 1024, 1) ?> KB ·
    <?= (int) $note['view_count'] ?> views · <?= (int) $note['download_count'] ?> downloads
  </p>

  <div class="submit-row">
    <?php if ($note['status'] === 'published'): ?>
      <?php if ($user): ?>
        <a class="btn" href="download.php?id=<?= (int) $note['id'] ?>">Download</a>
      <?php else: ?>
        <a class="btn" href="login.php">Log in to download</a>
      <?php endif; ?>
    <?php elseif ($isOwner || $isModerator): ?>
      <span class="muted">File stored (<code><?= e($note['file_ref']) ?></code>) — downloads unlock when published.</span>
    <?php endif; ?>

    <?php if ($user && ! $isOwner && $note['status'] === 'published'): ?>
      <form class="inline" method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="act" value="bookmark">
        <button class="btn btn-sec"><?= $bookmarked ? '★ Unsave' : '☆ Save' ?></button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (! $myReport && $user && ! $isOwner && $note['status'] === 'published'): ?>
  <details style="margin-top:1rem">
    <summary class="muted" style="cursor:pointer">⚠ Report this note</summary>
    <form method="post" class="stack" style="margin-top:.6rem">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="act" value="report">
      <label>Reason
        <select name="reason" required>
          <option value="copyright">Copyright issue</option>
          <option value="low_quality">Low quality</option>
          <option value="duplicate">Duplicate</option>
          <option value="off_topic">Off topic</option>
          <option value="abusive">Abusive content</option>
          <option value="privacy">Privacy concern</option>
          <option value="other">Other</option>
        </select>
      </label>
      <label>Details (optional)
        <textarea name="details" maxlength="1000" placeholder="What should the moderator check?"></textarea>
      </label>
      <div class="submit-row"><button class="btn btn-danger">Send report</button></div>
    </form>
  </details>
  <?php elseif ($myReport): ?>
    <p class="meta">Your report on this note is <b><?= e($myReport['status']) ?></b>.</p>
  <?php endif; ?>

  <?php if ($isOwner && $note['status'] === 'draft'): ?>
  <p class="meta">This is your draft — <a href="upload.php?draft=<?= (int) $note['id'] ?>">edit it</a> or submit it from <a href="my_notes.php">My notes</a>.</p>
  <?php endif; ?>
</article>

<?php if ($isModerator && $note['status'] === 'pending'): ?>
<article class="card stack" style="margin-top:1.2rem; border-color:#f2d9ac;">
  <h2 style="margin:0; font-size:1.05rem">Moderate this note (queue item)</h2>
  <form method="post" class="stack">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="act" value="moderate">
    <label>Action
      <select name="action" required>
        <option value="approve">Approve → published</option>
        <option value="reject">Reject — needs improvement</option>
        <option value="remove">Remove — policy violation</option>
      </select>
    </label>
    <label>Reason category
      <select name="reason_category">
        <option value="">— none —</option>
        <option value="copyright">copyright</option>
        <option value="low_quality">low_quality</option>
        <option value="duplicate">duplicate</option>
        <option value="off_topic">off_topic</option>
        <option value="abusive">abusive</option>
        <option value="privacy">privacy</option>
        <option value="other">other</option>
      </select>
    </label>
    <label>Internal note (admin-only)<textarea name="internal_note"></textarea></label>
    <div class="submit-row"><button class="btn">Apply action</button></div>
  </form>
</article>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
````

### `student-note-portal/download.php`

````text
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
````

### `student-note-portal/upload.php`

````text
<?php
/**
 * Upload flow (FR-04): title, subject, description, tags, file, attestation.
 * D-P05 pre-moderation: submitted uploads go to 'pending', drafts stay 'draft'.
 * Editing an existing draft (?draft=id) reuses the row.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login(); // students, moderators and admins may contribute

$editing = null;
if (isset($_GET['draft'])) {
    $draftId = filter_input(INPUT_GET, 'draft', FILTER_VALIDATE_INT);
    $editing = $draftId
        ? query('SELECT * FROM notes WHERE id = ? AND uploader_id = ? AND status = "draft"',
                [$draftId, current_user()['id']])->fetch()
        : null;
    if (! $editing) {
        flash('Draft not found (or not yours).', 'warn');
        redirect('my_notes.php');
    }
}

$courses  = query('SELECT id, name FROM courses ORDER BY name')->fetchAll();
$subjects = query('SELECT id, course_id, name FROM subjects ORDER BY course_id, name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? 'submit'); // 'submit' | 'draft'

    try {
        /* ---------- validate inputs (all server-side) ---------- */
        $title = trim((string) ($_POST['title'] ?? ''));
        if (mb_strlen($title) < 3 || mb_strlen($title) > 200) {
            throw new InvalidArgumentException('Title must be 3–200 characters.');
        }
        $desc = mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 2000);
        $subjectId = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
        if (! $subjectId || ! query('SELECT id FROM subjects WHERE id = ?', [$subjectId])->fetch()) {
            throw new InvalidArgumentException('Choose a subject.');
        }
        $attested = filter_input(INPUT_POST, 'attested', FILTER_VALIDATE_INT) === 1;
        if (! $attested) {
            throw new InvalidArgumentException('You must attest that you created or own these notes.');
        }
        $tags = parse_tags((string) ($_POST['tags'] ?? ''));

        /* ---------- file: required (new uploads and drafts alike) ---------- */
        $hasNewFile = isset($_FILES['file']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($editing && ! $hasNewFile) {
            [$ref, $original, $mime, $size] = [
                $editing['file_ref'], $editing['original_name'], $editing['mime'], (int) $editing['size_bytes'],
            ];
        } else {
            [$ref, $original, $mime, $size] = store_upload($_FILES['file'] ?? []);
        }

        $status = $action === 'draft' ? 'draft' : 'pending';

        if ($editing) {
            query(
                'UPDATE notes SET subject_id = ?, title = ?, description = ?, status = ?,
                        file_ref = ?, original_name = ?, mime = ?, size_bytes = ?,
                        ownership_attested = 1
                 WHERE id = ? AND uploader_id = ?',
                [$subjectId, $title, $desc, $status, $ref, $original, $mime, $size, $editing['id'], current_user()['id']]
            );
            $noteId = (int) $editing['id'];
        } else {
            query(
                'INSERT INTO notes (uploader_id, subject_id, title, description, status,
                                    file_ref, original_name, mime, size_bytes, ownership_attested)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
                [current_user()['id'], $subjectId, $title, $desc, $status,
                 $ref, $original, $mime, $size]
            );
            $noteId = (int) db()->lastInsertId();
        }

        /* ---------- tags: replace set for the note ---------- */
        query('DELETE FROM note_tags WHERE note_id = ?', [$noteId]);
        foreach ($tags as $tagName) {
            $tag = query('SELECT id FROM tags WHERE name = ?', [$tagName])->fetch();
            if ($tag) {
                $tagId = (int) $tag['id'];
            } else {
                query('INSERT INTO tags (name) VALUES (?)', [$tagName]);
                $tagId = (int) db()->lastInsertId();
            }
            query('INSERT IGNORE INTO note_tags (note_id, tag_id) VALUES (?, ?)', [$noteId, $tagId]);
        }

        flash($status === 'draft'
            ? 'Draft saved — submit it when you are ready.'
            : 'Upload sent for moderation. It will appear once a moderator approves it.');
        redirect('my_notes.php');
    } catch (InvalidArgumentException | RuntimeException $e) {
        $error = $e->getMessage();
        // Re-populate form (edited draft keeps its values below when $error set).
    }
}

$selSubject = (int) ($_POST['subject_id'] ?? 0) ?: (int) ($editing['subject_id'] ?? 0);

$title = $editing ? 'Edit draft' : 'Upload notes';
require __DIR__ . '/includes/header.php';
?>
<article class="card form-grid" style="max-width:640px;margin:0 auto">
  <h1 style="margin-top:0"><?= $editing ? 'Edit draft note' : 'Upload your notes' ?></h1>
  <?php if (isset($error)): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="stack">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <label>Title
      <input type="text" name="title" required minlength="3" maxlength="200"
             value="<?= e($_POST['title'] ?? $editing['title'] ?? '') ?>" placeholder="e.g. Discrete Math — Induction proofs">
    </label>

    <label>Subject (grouped by course)
      <select name="subject_id" required>
        <option value="">— choose subject —</option>
        <?php foreach ($courses as $c): ?>
        <optgroup label="<?= e($c['name']) ?>">
          <?php foreach ($subjects as $s): if ((int) $s['course_id'] !== (int) $c['id']) continue; ?>
            <option value="<?= (int) $s['id'] ?>" <?= $selSubject === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
          <?php endforeach; ?>
        </optgroup>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Description (what the notes cover)
      <textarea name="description" maxlength="2000"><?= e($_POST['description'] ?? $editing['description'] ?? '') ?></textarea>
    </label>

    <label>Tags (comma separated, up to 8)
      <input type="text" name="tags"
             value="<?= e($_POST['tags'] ?? implode(', ', array_column(
                 $editing ? query('SELECT t.name FROM tags t JOIN note_tags nt ON nt.tag_id = t.id WHERE nt.note_id = ? ORDER BY t.name', [$editing['id']])->fetchAll() : [],
                 'name'))) ?>">
    </label>

    <label>File — PDF, JPG or PNG, max 10 MB
      <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
             <?= $editing ? '' : 'required' ?>>
      <?php if ($editing): ?><span class="meta">Keeps existing file `<?= e($editing['original_name']) ?>` if you leave this empty.</span><?php endif; ?>
    </label>

    <label class="check">
      <input type="checkbox" name="attested" value="1" required
             <?= ($_POST['attested'] ?? null) || $editing ? 'checked' : '' ?>>
      I attest that I created these notes or have the right to share them, and nothing here breaks coursework rules.
    </label>

    <div class="submit-row">
      <button class="btn" name="action" value="submit">Submit for review</button>
      <button class="btn btn-sec" name="action" value="draft" formnovalidate>Save as draft</button>
    </div>
  </form>
</article>

<?php require __DIR__ . '/includes/footer.php'; ?>
````

### `student-note-portal/my_notes.php`

````text
<?php
/**
 * My notes (student view): all own notes with status, edit/delete for drafts.
 * Deleting a draft also removes its stored file. Published/rejected rows are
 * history — only moderators change their status further.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login();

$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $noteId = filter_input(INPUT_POST, 'note_id', FILTER_VALIDATE_INT);
    $note   = $noteId ? query('SELECT * FROM notes WHERE id = ? AND uploader_id = ?', [$noteId, $me['id']])->fetch() : null;

    if ($note) {
        $act = (string) ($_POST['act'] ?? '');
        if ($act === 'delete' && $note['status'] === 'draft') {
            $ref = basename((string) $note['file_ref']);
            if (is_file(UPLOAD_DIR . '/' . $ref)) {
                unlink(UPLOAD_DIR . '/' . $ref);
            }
            query('DELETE FROM notes WHERE id = ?', [$noteId]);
            flash('Draft deleted (file removed).');
        } elseif ($act === 'delete') {
            flash('Only drafts can be deleted by the author. Ask a moderator for changes.', 'warn');
        } elseif ($act === 'submit' && $note['status'] === 'draft') {
            query('UPDATE notes SET status = "pending" WHERE id = ?', [$noteId]);
            flash('Submitted for moderation.');
        } else {
            flash('Nothing to do for that note.', 'warn');
        }
    } else {
        flash('Note not found (or not yours).', 'err');
    }
    redirect('my_notes.php');
}

$rows = query(
    'SELECT n.id, n.title, n.status, n.created_at, n.view_count, n.download_count,
            s.name AS subject, c.name AS course, n.file_ref
     FROM notes n
     JOIN subjects s ON s.id = n.subject_id
     JOIN courses c ON c.id = s.course_id
     WHERE n.uploader_id = ?
     ORDER BY n.created_at DESC',
    [$me['id']]
)->fetchAll();

$title = 'My notes';
require __DIR__ . '/includes/header.php';
?>
<article class="card">
  <h1 style="margin-top:0">My notes</h1>
  <?php if (! $rows): ?>
    <p class="muted">Nothing uploaded yet.</p>
    <p><a class="btn" href="upload.php">Upload your first note</a></p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr><th>Title</th><th>Subject</th><th>Status</th><th>Views</th><th>Downloads</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="note.php?id=<?= (int) $r['id'] ?>"><?= e($r['title']) ?></a></td>
          <td class="meta"><?= e($r['subject']) ?></td>
          <td><?= status_badge($r['status']) ?></td>
          <td><?= (int) $r['view_count'] ?></td>
          <td><?= (int) $r['download_count'] ?></td>
          <td>
            <?php if ($r['status'] === 'draft'): ?>
              <a class="btn btn-sec" href="upload.php?draft=<?= (int) $r['id'] ?>">Edit</a>
              <form class="inline" method="post" onsubmit="return confirm('Delete this draft?')">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="note_id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="act" value="delete">
                <button class="btn btn-danger">Delete</button>
              </form>
            <?php elseif ($r['status'] === 'rejected'): ?>
              <form class="inline" method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="note_id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="act" value="submit">
                <button class="btn btn-sec" title="Send back to the moderator queue">Re-submit</button>
              </form>
            <?php else: ?>
              <span class="meta">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
````

### `student-note-portal/bookmarks.php`

````text
<?php
/**
 * Bookmarks (unique module): saved notes per student, unsave from here.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login();

$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $noteId = filter_input(INPUT_POST, 'note_id', FILTER_VALIDATE_INT);
    if ($noteId) {
        query('DELETE FROM bookmarks WHERE user_id = ? AND note_id = ?', [$me['id'], $noteId]);
        flash('Removed from saved notes.');
    }
    redirect('bookmarks.php');
}

$rows = query(
    "SELECT n.id, n.title, n.view_count, n.download_count, s.name AS subject
     FROM bookmarks b
     JOIN notes n ON n.id = b.note_id AND n.status = 'published'
     JOIN subjects s ON s.id = n.subject_id
     WHERE b.user_id = ?
     ORDER BY b.created_at DESC",
    [$me['id']]
)->fetchAll();

$title = 'Saved notes';
require __DIR__ . '/includes/header.php';
?>
<article class="card">
  <h1 style="margin-top:0">Saved notes</h1>
  <?php if (! $rows): ?>
    <p class="muted">Nothing saved yet — open a published note and press ☆ Save.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Title</th><th>Subject</th><th>Views</th><th>Downloads</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="note.php?id=<?= (int) $r['id'] ?>"><?= e($r['title']) ?></a></td>
          <td class="meta"><?= e($r['subject']) ?></td>
          <td><?= (int) $r['view_count'] ?></td>
          <td><?= (int) $r['download_count'] ?></td>
          <td>
            <form class="inline" method="post">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="note_id" value="<?= (int) $r['id'] ?>">
              <button class="btn btn-sec">Unsave</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
````

### `student-note-portal/review.php`

````text
<?php
/**
 * Moderation center (FR-08): pending queue, reports inbox, published list,
 * and the append-only audit trail. Moderators + admins only.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_role('moderator', 'admin');

$me  = current_user();
$cats = ['copyright', 'low_quality', 'duplicate', 'off_topic', 'abusive', 'privacy', 'other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $act = (string) ($_POST['act'] ?? '');

    if ($act === 'decide') { // queue decision
        $noteId  = filter_input(INPUT_POST, 'note_id', FILTER_VALIDATE_INT);
        $decision = (string) ($_POST['decision'] ?? '');
        $map      = ['approve' => 'published', 'reject' => 'rejected', 'remove' => 'removed'];
        if (! $noteId || ! isset($map[$decision])) {
            flash('Unknown decision.', 'err');
            redirect('review.php');
        }
        $note = query('SELECT id, status FROM notes WHERE id = ?', [$noteId])->fetch();
        if (! $note) {
            flash('Note not found.', 'err');
            redirect('review.php');
        }
        $category = in_array((string) ($_POST['reason_category'] ?? ''), $cats, true)
            ? (string) $_POST['reason_category'] : null;
        $internal = mb_substr(trim((string) ($_POST['internal_note'] ?? '')), 0, 1000);
        $ok       = $decision === 'approve';

        query('UPDATE notes SET status = ?, published_at = ' . ($ok ? 'CURRENT_TIMESTAMP' : 'published_at') . ' WHERE id = ?',
              [$map[$decision], $noteId]);
        query(
            'INSERT INTO moderation_actions (note_id, actor_id, action, reason_category, internal_note)
             VALUES (?, ?, ?, ?, ?)',
            [$noteId, $me['id'], $decision, $category, $internal]
        );
        flash('Note ' . $decision . 'd.');
    } elseif ($act === 'report_resolve') { // work through report inbox
        $reportId = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);
        $report   = $reportId ? query(
            'SELECT r.*, n.title AS note_title FROM reports r JOIN notes n ON n.id = r.note_id
             WHERE r.id = ? AND r.status = "open"', [$reportId])->fetch() : null;
        if (! $report) {
            flash('Report not found or already handled.', 'err');
            redirect('review.php');
        }
        $choice = (string) ($_POST['choice'] ?? '');
        if ($choice === 'dismiss') {
            query('UPDATE reports SET status = "dismissed" WHERE id = ?', [$reportId]);
            query(
                'INSERT INTO moderation_actions (note_id, actor_id, action, reason_category, internal_note)
                 VALUES (?, ?, "approve", NULL, ?)',
                [$report['note_id'], $me['id'], 'Report dismissed by moderator (note kept).']
            );
            flash('Report dismissed — note stays.');
        } elseif ($choice === 'remove_note') {
            query('UPDATE notes SET status = "removed" WHERE id = ?', [$report['note_id']]);
            query('UPDATE reports SET status = "resolved" WHERE id = ?', [$reportId]);
            $category = in_array((string) ($_POST['reason_category'] ?? ''), $cats, true)
                ? (string) $_POST['reason_category'] : 'other';
            query(
                'INSERT INTO moderation_actions (note_id, actor_id, action, reason_category, internal_note)
                 VALUES (?, ?, "remove", ?, ?)',
                [$report['note_id'], $me['id'], $category,
                 'Removed via report #' . (int) $reportId . '.']
            );
            flash('Note removed and report resolved.');
        } else {
            flash('Unknown report action.', 'err');
        }
    } else {
        flash('Unknown action.', 'err');
    }
    redirect('review.php');
}

$queue = query(
    "SELECT n.id, n.title, n.description, n.created_at, n.original_name, n.size_bytes,
            u.display_name AS uploader, s.name AS subject
     FROM notes n
     JOIN users u    ON u.id = n.uploader_id
     JOIN subjects s ON s.id = n.subject_id
     WHERE n.status = 'pending'
     ORDER BY n.created_at"
)->fetchAll();

$openReports = query(
    "SELECT r.id, r.note_id, r.reason, r.details, r.created_at,
            n.title AS note_title, u.display_name AS reporter
     FROM reports r
     JOIN notes n ON n.id = r.note_id
     JOIN users u ON u.id = r.reporter_id
     WHERE r.status = 'open'
     ORDER BY r.created_at"
)->fetchAll();

$published = query(
    "SELECT n.id, n.title, n.download_count, c.name AS course
     FROM notes n
     JOIN subjects s ON s.id = n.subject_id
     JOIN courses c  ON c.id = s.course_id
     WHERE n.status = 'published'
     ORDER BY n.published_at DESC
     LIMIT 15"
)->fetchAll();

$audit = query(
    "SELECT m.id, m.note_id, m.action, m.reason_category, m.created_at, m.internal_note,
            n.title AS note_title, u.display_name AS actor, u.role AS actor_role
     FROM moderation_actions m
     JOIN notes n    ON n.id = m.note_id
     JOIN users u    ON u.id = m.actor_id
     ORDER BY m.created_at DESC
     LIMIT 30"
)->fetchAll();

$title = 'Moderation';
require __DIR__ . '/includes/header.php';
?>

<article class="card stack" style="margin-bottom:1.2rem">
  <h1 style="margin:0">Moderation queue</h1>
  <p class="meta" style="margin:0"><?= count($queue) ?> note<?= count($queue) === 1 ? '' : 's' ?> waiting (pre-moderation: nothing goes live without approval)</p>

  <?php if (! $queue): ?>
    <p class="muted">Queue is clear. 🎉</p>
  <?php endif; ?>

  <?php foreach ($queue as $q): ?>
    <div class="stack" style="border:1px solid var(--line); border-radius:8px; padding:.8rem 1rem">
      <p style="margin:0">
        <b><a href="note.php?id=<?= (int) $q['id'] ?>"><?= e($q['title']) ?></a></b>
        · <?= e($q['subject']) ?> · by <?= e($q['uploader']) ?> · <?= e(date('j M', strtotime($q['created_at']))) ?>
      </p>
      <p class="meta" style="margin:0">File: `<?= e($q['original_name']) ?>` (<?= number_format($q['size_bytes'] / 1024, 1) ?> KB)</p>
      <?php if ($q['description']): ?><p class="meta" style="margin:.3rem 0 0"><?= e($q['description']) ?></p><?php endif; ?>
      <form method="post" style="display:flex; gap:.6rem; flex-wrap:wrap; align-items:flex-end">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="act" value="decide">
        <input type="hidden" name="note_id" value="<?= (int) $q['id'] ?>">
        <label style="margin:0">Decision
          <select name="decision" required>
            <option value="approve">Approve</option>
            <option value="reject">Reject</option>
            <option value="remove">Remove</option>
          </select>
        </label>
        <label style="margin:0">Reason category
          <select name="reason_category">
            <option value="">—</option>
            <?php foreach ($cats as $cat): ?><option value="<?= e($cat) ?>"><?= e($cat) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label style="margin:0; flex:1">Internal note (never public)
          <input type="text" name="internal_note" maxlength="1000">
        </label>
        <button class="btn">Apply</button>
      </form>
    </div>
  <?php endforeach; ?>
</article>

<article class="card stack" style="margin-bottom:1.2rem">
  <h1 style="margin:0; font-size:1.3rem">Report inbox</h1>
  <?php if (! $openReports): ?>
    <p class="muted">No open reports.</p>
  <?php endif; ?>
  <?php foreach ($openReports as $r): ?>
    <div style="border:1px solid var(--line); border-radius:8px; padding:.8rem 1rem">
      <p style="margin:0 0 .4rem">
        <b><a href="note.php?id=<?= (int) $r['note_id'] ?>"><?= e($r['note_title']) ?></a></b>
        reported for <code><?= e($r['reason']) ?></code> by <?= e($r['reporter']) ?> on <?= e(date('j M', strtotime($r['created_at']))) ?>
      </p>
      <?php if ($r['details']): ?><p class="meta" style="margin:0 0 .5rem">“<?= e($r['details']) ?>”</p><?php endif; ?>
      <form method="post" style="display:flex; gap:.6rem; align-items:flex-end; flex-wrap:wrap">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="act" value="report_resolve">
        <input type="hidden" name="report_id" value="<?= (int) $r['id'] ?>">
        <label style="margin:0">Action
          <select name="choice">
            <option value="dismiss">Dismiss report (note stays)</option>
            <option value="remove_note">Remove note (resolves report)</option>
          </select>
        </label>
        <label style="margin:0">Reason category (for removal)
          <select name="reason_category">
            <?php foreach ($cats as $cat): ?><option value="<?= e($cat) ?>"><?= e($cat) ?></option><?php endforeach; ?>
          </select>
        </label>
        <button class="btn">Handle</button>
      </form>
    </div>
  <?php endforeach; ?>
</article>

<article class="card stack" style="margin-bottom:1.2rem">
  <h1 style="margin:0; font-size:1.3rem">Published notes (spot removal)</h1>
  <table class="table">
    <thead><tr><th>Title</th><th>Course</th><th>Downloads</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($published as $n): ?>
      <tr>
        <td><a href="note.php?id=<?= (int) $n['id'] ?>"><?= e($n['title']) ?></a></td>
        <td class="meta"><?= e($n['course']) ?></td>
        <td><?= (int) $n['download_count'] ?></td>
        <td>
          <form class="inline" method="post" onsubmit="return confirm('Remove this note? Actions are logged.')">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="act" value="decide">
            <input type="hidden" name="note_id" value="<?= (int) $n['id'] ?>">
            <input type="hidden" name="decision" value="remove">
            <input type="hidden" name="reason_category" value="other">
            <button class="btn btn-danger">Remove</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</article>

<article class="card stack">
  <h1 style="margin:0; font-size:1.3rem">Audit trail (last 30, append-only)</h1>
  <table class="table">
    <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Note</th><th>Category</th><th>Internal note</th></tr></thead>
    <tbody>
    <?php foreach ($audit as $a): ?>
      <tr>
        <td class="meta"><?= e(date('j M H:i', strtotime($a['created_at']))) ?></td>
        <td><?= e($a['actor']) ?> <em class="meta">(<?= e($a['actor_role']) ?>)</em></td>
        <td><code><?= e($a['action']) ?></code></td>
        <td><a href="note.php?id=<?= (int) $a['note_id'] ?>"><?= e($a['note_title']) ?></a></td>
        <td class="meta"><?= e($a['reason_category'] ?? '—') ?></td>
        <td class="meta"><?= e($a['internal_note'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
````

### `student-note-portal/admin.php`

````text
<?php
/**
 * Admin console (FR-09): users + roles, courses/subjects, analytics dashboard
 * (unique module — inline CSS charts, no CDN so the demo works offline).
 * Admin only.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_role('admin');

$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $act = (string) ($_POST['act'] ?? '');

    try {
        if ($act === 'user_role') {
            $userId  = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $role    = (string) ($_POST['role'] ?? '');
            if (! $userId || ! in_array($role, ['student', 'moderator', 'admin'], true)) {
                throw new InvalidArgumentException('Invalid user or role.');
            }
            if ($userId === (int) $me['id'] && $role !== 'admin') {
                throw new InvalidArgumentException('You cannot demote yourself.');
            }
            query('UPDATE users SET role = ? WHERE id = ?', [$role, $userId]);
            flash('Role updated.');
        } elseif ($act === 'user_delete') {
            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if (! $userId) {
                throw new InvalidArgumentException('Invalid user.');
            }
            if ($userId === (int) $me['id']) {
                throw new InvalidArgumentException('You cannot delete your own account.');
            }
            try {
                query('DELETE FROM users WHERE id = ?', [$userId]);
                flash('User deleted.');
            } catch (PDOException) { // FK: user authored notes or moderated actions
                flash('User has notes or audit history — change role instead of deleting.', 'warn');
            }
        } elseif ($act === 'course_add') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if (mb_strlen($name) < 2) {
                throw new InvalidArgumentException('Course name too short.');
            }
            try {
                query('INSERT INTO courses (institution_id, name) VALUES (1, ?)', [$name]);
                flash('Course added.');
            } catch (PDOException) {
                flash('That course already exists.', 'warn');
            }
        } elseif ($act === 'subject_add') {
            $courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
            $name     = trim((string) ($_POST['name'] ?? ''));
            if (! $courseId || mb_strlen($name) < 2) {
                throw new InvalidArgumentException('Need a course and a subject name.');
            }
            try {
                query('INSERT INTO subjects (course_id, name) VALUES (?, ?)', [$courseId, $name]);
                flash('Subject added.');
            } catch (PDOException) {
                flash('That subject already exists in the course.', 'warn');
            }
        } elseif ($act === 'subject_delete') {
            $subjectId = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
            if (! $subjectId) {
                throw new InvalidArgumentException('Invalid subject.');
            }
            $used = query('SELECT COUNT(*) c FROM notes WHERE subject_id = ?', [$subjectId])->fetch();
            if ((int) $used['c'] > 0) {
                flash('Subject has notes attached — remove/move them first.', 'warn');
            } else {
                query('DELETE FROM subjects WHERE id = ?', [$subjectId]);
                flash('Subject deleted.');
            }
        }
    } catch (InvalidArgumentException $ex) {
        flash($ex->getMessage(), 'err');
    }
    redirect('admin.php');
}

$users = query('SELECT id, email, display_name, role, created_at FROM users ORDER BY role, display_name')->fetchAll();
$coursesWithSubjects = query(
    'SELECT c.id, c.name, s.id AS sid, s.name AS sname
     FROM courses c LEFT JOIN subjects s ON s.course_id = c.id
     ORDER BY c.name, s.name'
)->fetchAll();
$courses = [];
foreach ($coursesWithSubjects as $row) {
    if (! isset($courses[$row['id']])) {
        $courses[$row['id']] = ['id' => $row['id'], 'name' => $row['name'], 'subjects' => []];
    }
    if ($row['sid']) {
        $courses[$row['id']]['subjects'][] = ['id' => $row['sid'], 'name' => $row['sname']];
    }
}

/* ---------- Analytics ---------- */
// only_full_group_by-safe: grouped on the YEARWEEK expression itself.
$weekRows = query(
    "SELECT YEARWEEK(published_at, 3) AS yw, COUNT(*) AS c
     FROM notes
     WHERE published_at IS NOT NULL AND published_at >= NOW() - INTERVAL 8 WEEK
     GROUP BY yw
     ORDER BY yw"
)->fetchAll();
// yw is YYYYWW — turn it into a readable week label here.
foreach ($weekRows as &$w) {
    $yw = (string) $w['yw'];
    $w['label'] = 'W' . substr($yw, -2);
}
unset($w);
$publishedMax = 0;
foreach ($weekRows as $w) { $publishedMax = max($publishedMax, (int) $w['c']); }

$avgHours = query(
    "SELECT AVG(TIMESTAMPDIFF(HOUR, n.created_at, m.created_at)) AS h
     FROM moderation_actions m
     JOIN notes n ON n.id = m.note_id
     WHERE m.action = 'approve'"
)->fetch();
$avgHours = $avgHours['h'] !== null ? round((float) $avgHours['h'], 1) : null;

$roleCounts  = query("SELECT role, COUNT(*) c FROM users GROUP BY role ORDER BY role")->fetchAll();
$roleCounts  = array_column($roleCounts, 'c', 'role');
$statusCounts = query("SELECT status, COUNT(*) c FROM notes GROUP BY status")->fetchAll();
$statusCounts = array_column($statusCounts, 'c', 'status');
$totalDownloads = (int) query('SELECT COALESCE(SUM(download_count),0) s FROM notes')->fetch()['s'];
$totalViews     = (int) query('SELECT COALESCE(SUM(view_count),0) s FROM notes')->fetch()['s'];
$topNotes = query(
    "SELECT id, title, view_count, download_count FROM notes
     WHERE status = 'published' ORDER BY download_count DESC LIMIT 8"
)->fetchAll();

$title = 'Admin';
require __DIR__ . '/includes/header.php';
?>
<article class="card stack" style="margin-bottom:1.2rem">
  <h1 style="margin:0">Analytics dashboard</h1>
  <div style="display:flex; gap:1.4rem; flex-wrap:wrap">
    <div class="stat"><b><?= (int) ($roleCounts['student'] ?? 0) ?></b><span class="meta">students</span></div>
    <div class="stat"><b><?= (int) ($statusCounts['published'] ?? 0) ?></b><span class="meta">published notes</span></div>
    <div class="stat"><b><?= (int) ($statusCounts['pending'] ?? 0) ?></b><span class="meta">in queue</span></div>
    <div class="stat"><b><?= number_format($totalDownloads) ?></b><span class="meta">downloads</span></div>
    <div class="stat"><b><?= number_format($totalViews) ?></b><span class="meta">views</span></div>
    <div class="stat"><b><?= $avgHours === null ? '—' : $avgHours . ' h' ?></b><span class="meta">avg approval time</span></div>
  </div>

  <h2 style="font-size:1.15rem; margin-bottom:0">Notes published — last 8 weeks</h2>
  <?php if (! $weekRows): ?>
    <p class="muted" style="margin-top:0">No publications in the window yet.</p>
  <?php else: ?>
  <div class="bars">
    <?php foreach ($weekRows as $w): $h = (int) $w['c'] === 0 ? 0 : max(8, (int) round(120 * $w['c'] / max(1, $publishedMax))); ?>
      <div class="bar" style="height:<?= $h ?>px">
        <em><?= (int) $w['c'] ?></em><span><?= e($w['label']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <h2 style="font-size:1.15rem; margin-bottom:0">Top notes by downloads</h2>
  <table class="table" style="max-width:640px">
    <thead><tr><th>Note</th><th>Views</th><th>Downloads</th></tr></thead>
    <tbody>
    <?php foreach ($topNotes as $t): ?>
      <tr>
        <td><a href="note.php?id=<?= (int) $t['id'] ?>"><?= e($t['title']) ?></a></td>
        <td><?= (int) $t['view_count'] ?></td>
        <td><?= (int) $t['download_count'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</article>

<article class="card stack" style="margin-bottom:1.2rem">
  <h1 style="margin:0; font-size:1.3rem">Users</h1>
  <table class="table">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= e($u['display_name']) ?><?= (int) $u['id'] === (int) $me['id'] ? ' <em class="meta">(you)</em>' : '' ?></td>
        <td class="meta"><?= e($u['email']) ?></td>
        <td>
          <form class="inline" method="post" style="display:flex;gap:.4rem;align-items:center">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="act" value="user_role">
            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
            <select name="role">
              <option value="student"  <?= $u['role'] === 'student' ? 'selected' : '' ?>>student</option>
              <option value="moderator" <?= $u['role'] === 'moderator' ? 'selected' : '' ?>>moderator</option>
              <option value="admin"    <?= $u['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
            </select>
            <button class="btn btn-sec" style="padding:.25rem .6rem">Set</button>
          </form>
        </td>
        <td class="meta"><?= e(date('j M Y', strtotime($u['created_at']))) ?></td>
        <td>
          <?php if ((int) $u['id'] !== (int) $me['id']): ?>
          <form class="inline" method="post" onsubmit="return confirm('Delete this user?')">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="act" value="user_delete">
            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
            <button class="btn btn-danger" style="padding:.25rem .6rem">Delete</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</article>

<article class="card stack">
  <h1 style="margin:0; font-size:1.3rem">Courses and subjects (FR-09)</h1>
  <form method="post" style="display:flex; gap:.6rem; flex-wrap:wrap; align-items:flex-end">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="act" value="course_add">
    <label style="margin:0; flex:1">New course<input type="text" name="name" required placeholder="e.g. Mathematics"></label>
    <button class="btn">Add course</button>
  </form>

  <?php foreach ($courses as $c): ?>
    <div style="border:1px solid var(--line); border-radius:8px; padding:.8rem 1rem">
      <b><?= e($c['name']) ?></b>
      <table class="table" style="margin-top:.4rem">
        <tbody>
        <?php foreach ($c['subjects'] as $s): ?>
          <tr>
            <td><?= e($s['name']) ?></td>
            <td style="width:90px">
              <form class="inline" method="post" onsubmit="return confirm('Delete subject?')">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="act" value="subject_delete">
                <input type="hidden" name="subject_id" value="<?= (int) $s['id'] ?>">
                <button class="btn btn-danger" style="padding:.2rem .5rem">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td>
            <form class="inline" method="post" style="display:flex; gap:.4rem; align-items:center">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="act" value="subject_add">
              <input type="hidden" name="course_id" value="<?= (int) $c['id'] ?>">
              <input type="text" name="name" required placeholder="Add subject…" style="max-width:260px">
              <button class="btn" style="padding:.25rem .7rem">Add</button>
            </form>
          </td>
          <td></td>
        </tr>
        </tbody>
      </table>
    </div>
  <?php endforeach; ?>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
````

### `student-note-portal/transparency.php`

````text
<?php
/**
 * Transparency view (FR-10, D-P01 option A): public, read-only feed of every
 * moderation action. Redactions applied at the view level — actor identity,
 * internal notes and reporter identities are structurally excluded, not hidden.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$entries = query(
    'SELECT id, note_id, note_title, action, reason_category, created_at, actor_role
     FROM transparency_log
     ORDER BY created_at DESC
     LIMIT 100'
)->fetchAll();

$title = 'Transparency';
require __DIR__ . '/includes/header.php';
?>
<article class="card stack">
  <h1 style="margin-top:0">Transparency log</h1>
  <p class="meta" style="margin-bottom:0">
    Every moderation action on the portal, open to all users. Redacted by design:
    we show <b>what</b> happened to <b>which note</b> and the actor's <b>role</b> —
    never moderator names, internal notes, reporter identities, emails or private details.
    (This is the redacted-transparency policy, decision D-P01 option A.)
  </p>

  <?php if (! $entries): ?>
    <p class="muted">No moderation actions yet.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>When</th><th>Actor role</th><th>Action</th><th>Note</th><th>Reason</th></tr></thead>
    <tbody>
    <?php foreach ($entries as $t): ?>
      <tr>
        <td class="meta"><?= e(date('j M Y', strtotime($t['created_at']))) ?></td>
        <td class="meta"><?= e($t['actor_role']) ?></td>
        <td><code><?= e($t['action']) ?></code></td>
        <td><a href="note.php?id=<?= (int) $t['note_id'] ?>"><?= e($t['note_title']) ?></a></td>
        <td class="meta"><?= e($t['reason_category'] ?? '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
````

### `student-note-portal/login.php`

````text
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
````

### `student-note-portal/register.php`

````text
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
````

### `student-note-portal/logout.php`

````text
<?php
/** Logout (session destroyed server-side). */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

logout_user();
redirect('login.php');
````

---

## Static / informational pages

### `student-note-portal/about.php`

````text
<?php
/**
 * About us — also the long-form SEO page describing the tool (~1000 words).
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$title            = 'About us';
$meta_description = 'The Student Note Sharing Portal is a free, moderated study-notes library: students share lecture notes as PDF or images, moderators review every upload, and everyone browses by course and subject.';
require __DIR__ . '/includes/header.php';
?>

<div class="card prose">
  <h1>About the Student Note Sharing Portal</h1>

  <p>
    The <b>Student Note Sharing Portal</b> is a free, moderated library where students share their
    study notes with each other. Instead of notes living in private chat groups, lost folders and
    expensive photocopy stacks, they live in one searchable place: browse by course and subject,
    open a note page, and download a clean PDF or image in seconds. Every upload is read by a
    human moderator before it appears publicly, so the library stays useful, legal and safe —
    which is what separates a shared notes library from an unmoderated file dump.
  </p>

  <h2>Why it exists</h2>
  <p>
    Good notes are one of the most effective study aids a student can have. Summarising a lecture
    in your own words cements the material, and reading how a classmate explained the same topic
    often clarifies the parts the textbook glides over. Yet notes are hard to share well. They end
    up in closed messaging groups where nothing is searchable, they get renamed beyond recognition,
    and nobody can tell whether a file is this year's syllabus or an outdated draft. Students who
    miss a lecture — through illness, placement or a timetable clash — lose out the most. This
    portal solves that with three ideas: <b>one shared library</b>, <b>strong browsing tools</b> and
    <b>human moderation</b>.
  </p>

  <h2>How the portal works</h2>
  <p>
    Anyone can visit the portal and read the catalogue — no account is needed to search, filter and
    open note pages. Creating a free account unlocks the rest: downloading files, uploading your own
    notes, and saving favourites. Uploading takes about a minute. You attach a PDF, JPG or PNG up to
    10&nbsp;MB, pick the course and subject it belongs to, add a title, a short description and up to
    eight tags, and confirm the ownership attestation — a checkbox stating that the work is yours or
    that you have the right to share it. From there the note enters the moderation queue as a
    <em>pending</em> note. You can still edit it as a draft, but the public cannot see it until a
    moderator approves it.
  </p>
  <p>
    Moderators are students and staff trusted with the Review queue. For every submission they can
    <b>approve</b> it — which publishes it to the public catalogue — <b>reject</b> it with a reason
    such as low quality or a missing citation, or <b>remove</b> it later if a policy violation
    surfaces. Every action is recorded with a reason category, and those records power the portal's
    signature feature: the <a href="transparency.php">transparency log</a>. It shows what was
    approved, rejected or removed and why, with private data redacted. Moderation you can audit
    builds trust that moderation you cannot see never will.
  </p>

  <h2>Finding the notes you need</h2>
  <p>
    The <a href="index.php">browse page</a> is built for speed. A full-text search matches words in
    titles and descriptions, two dropdowns narrow the catalogue to a course or a single subject, and
    clickable tag chips surface the topics students use most — think <code>#exam-prep</code>,
    <code>#formula-sheet</code> or <code>#summary</code>. Each note page shows who uploaded the note,
    when, its subject and course, the file size, and how many times it has been viewed and
    downloaded, so you can judge its usefulness before opening it. Popular, recent material rises to
    the top because the catalogue sorts by publication date and engagement.
  </p>

  <h2>Quality, safety and copyright</h2>
  <p>
    A shared library only works if people trust it. Three rules keep the portal trustworthy.
    First, <b>moderation is mandatory</b>: nothing a student uploads is public until a moderator
    approves it. Second, <b>reporting is built in</b>: every published note has a report button with
    predefined reasons — copyright, low quality, duplicate, off topic, abusive content or a privacy
    concern — and reports go straight to the moderation queue. Third, <b>files are checked, not
    trusted</b>: the server inspects each upload's real content type and only accepts PDF, JPEG and
    PNG files, stored under unguessable names that are never directly web-accessible. The portal is
    for original study material — your own summaries, your own diagrams, your own past-paper
    walkthroughs — not for redistributing textbooks, exam papers or copyrighted slides.
  </p>

  <h2>Privacy by default</h2>
  <p>
    The portal collects the minimum it needs to run: an email address and display name at
    registration, the notes you upload, and private bookmarks that only you can see. Downloads
    require a logged-in account, which keeps files out of anonymous scrapers. Moderators see internal
    notes; the public log never shows them or any personal data. Full details live in our
    <a href="privacy.php">privacy policy</a> and <a href="terms.php">terms &amp; conditions</a>.
  </p>

  <h2>Who runs it</h2>
  <p>
    The portal began as a student software project at Westfield College, built with plain PHP and
    MySQL so it can run on any campus server with no paid dependencies. It is maintained by student
    volunteers, and the moderator team is open to applications each term. Questions, bug reports,
    moderation appeals and partnership ideas are all welcome on the <a href="contact.php">contact
    page</a>.
  </p>

  <h2>Start here</h2>
  <p>
    Browse the <a href="index.php">catalogue</a>, read the <a href="index.php#faq">frequently asked
    questions</a>, then <a href="register.php">create a free account</a> to download notes or upload
    the set you are proud of. If you only remember one thing about this portal: someone else's
    carefully written notes might be the study session you have been missing — and yours might be
    that resource for someone else.
  </p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
````

### `student-note-portal/contact.php`

````text
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
````

### `student-note-portal/privacy.php`

````text
<?php
/**
 * Privacy policy — what we collect, why, and student-friendly rights.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$title            = 'Privacy policy';
$meta_description = 'How the Student Note Sharing Portal collects, uses and protects your data: account details, uploaded notes, bookmarks, moderation records and your rights.';
require __DIR__ . '/includes/header.php';
?>

<div class="card prose">
  <h1>Privacy policy</h1>
  <p class="meta">Last updated: October 2026 · Applies to the Student Note Sharing Portal operated by Westfield College students.</p>

  <h2>What we collect</h2>
  <ul>
    <li><b>Account data</b> — email address, display name and a password that is stored only as a salted hash.</li>
    <li><b>Content you submit</b> — note titles, descriptions, tags, uploaded files, drafts and any edits.</li>
    <li><b>Activity data</b> — view and download counts per note, your private bookmarks, and reports you file.</li>
    <li><b>Moderation records</b> — actions taken on notes, reason categories and internal moderator notes.</li>
  </ul>

  <h2>Why we use it</h2>
  <ul>
    <li>To run your account, let you upload, download and save notes.</li>
    <li>To moderate the library: moderators see pending notes and reports; admins manage accounts and taxonomy.</li>
    <li>To publish anonymous statistics (view and download counts) and the redacted transparency log.</li>
  </ul>

  <h2>What we never do</h2>
  <ul>
    <li>We do not sell or share your data with advertisers or third parties.</li>
    <li>We do not publish your email address; the public sees only your display name.</li>
    <li>We do not expose uploaded files directly — they are stored under unguessable names and served only through the download page.</li>
  </ul>

  <h2>Moderation transparency</h2>
  <p>
    Every approve/reject/remove action appears in the <a href="transparency.php">public transparency
    log</a> with the reason category. Internal moderator notes and all personal data are redacted from
    that log.
  </p>

  <h2>Retention and your rights</h2>
  <p>
    Data is kept while your account is active. You can ask an administrator to delete your account and
    uploaded notes at any time via the <a href="contact.php">contact page</a>. Removing a note removes
    it from the public catalogue; moderation records of that action are kept in redacted form so the
    log stays honest.
  </p>

  <h2>Cookies and storage</h2>
  <p>
    The portal uses a single session cookie to keep you logged in. There are no tracking cookies, no
    analytics scripts and no third-party embeds — the demo runs fully offline.
  </p>

  <h2>Questions</h2>
  <p>Contact us any time: <a href="contact.php">get in touch</a>.</p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
````

### `student-note-portal/terms.php`

````text
<?php
/**
 * Terms and conditions — acceptable use, uploads, moderation, accounts.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$title            = 'Terms & conditions';
$meta_description = 'Terms of use for the Student Note Sharing Portal: acceptable use, upload rules, moderation, accounts and disclaimers.';
require __DIR__ . '/includes/header.php';
?>

<div class="card prose">
  <h1>Terms &amp; conditions</h1>
  <p class="meta">Last updated: October 2026 · By using the portal you agree to these terms.</p>

  <h2>The service</h2>
  <p>
    The Student Note Sharing Portal is a student-run, moderated library for sharing study notes. It is
    provided free of charge for educational use. Availability is best-effort; the portal may be taken
    offline for maintenance.
  </p>

  <h2>Your account</h2>
  <ul>
    <li>One account per person; keep your password secret and tell us about any misuse.</li>
    <li>Use a real email address so moderators can reach you about your uploads.</li>
    <li>Administrators may suspend accounts that break these terms.</li>
  </ul>

  <h2>Uploading — your promises</h2>
  <ul>
    <li>Only upload work you wrote yourself or have the right to share. Uploading copyrighted material without permission is not allowed.</li>
    <li>No exam papers, answer keys distributed under restriction, personal data of other people, or abusive content.</li>
    <li>Files must be PDF, JPG or PNG and no larger than 10&nbsp;MB.</li>
    <li>You keep ownership of your work; you grant other students a licence to view and download it for their own study.</li>
  </ul>

  <h2>Moderation</h2>
  <p>
    Nothing you upload is public until a moderator approves it. Moderators may reject or remove notes
    that break these rules, and their actions are published in the
    <a href="transparency.php">transparency log</a>. You can appeal a decision via the
    <a href="contact.php">contact page</a>.
  </p>

  <h2>Downloading and reuse</h2>
  <p>
    Downloaded notes are for your personal study. Do not redistribute them publicly, sell them, or
    republish them as your own — that breaches both these terms and your classmate's trust.
  </p>

  <h2>Disclaimer</h2>
  <p>
    Notes are student contributions and may contain mistakes. They are not official college material
    and are not a substitute for lectures, assigned reading or academic advice. Use them at your own
    discretion.
  </p>

  <h2>Changes</h2>
  <p>We may update these terms; the date above reflects the latest revision.</p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
````

### `student-note-portal/404.php`

````text
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
````

### `student-note-portal/500.php`

````text
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
````

---

## Routing and server config

### `student-note-portal/router.php`

````text
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
````

### `student-note-portal/.htaccess`

````text
# Apache/XAMPP — README deploys the app at /htdocs/student-note-portal/.
ErrorDocument 404 /student-note-portal/404.php
ErrorDocument 500 /student-note-portal/500.php
````

### `student-note-portal/uploads/.htaccess`

````text
# Uploads are never served directly — all access goes through download.php
# which checks login, status and role first (T-08).
Require all denied
````

---

## Database

### `student-note-portal/note_portal.sql`

````text
-- ============================================================
-- Student Note Sharing Portal — MySQL database file
-- Schema + seed data in one file (submission deliverable).
--
-- Import: phpMyAdmin (XAMPP/MAMP) > Import, or CLI:
--   mysql -u root < note_portal.sql
--
-- Seeded demo logins (all from institution #1, Westfield College):
--   admin@westfield.edu      admin123       role: admin
--   moderator@westfield.edu  moderator123   role: moderator
--   student@westfield.edu    student123     role: student
--
-- Passwords are stored as password_hash() bcrypt strings — never plaintext.
-- ============================================================

CREATE DATABASE IF NOT EXISTS note_portal
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE note_portal;

-- Drop in reverse dependency order so the file can be re-imported safely.
DROP VIEW   IF EXISTS transparency_log;
DROP TABLE  IF EXISTS bookmarks, moderation_actions, reports, note_tags,
                     tags, notes, subjects, courses, users, institutions;

CREATE TABLE institutions (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(150) NOT NULL UNIQUE,
  email_domain VARCHAR(150),
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- No password-based auth decision is open: email + password (Accepted).
-- Passwords live here as password_hash() output only (T-01).
CREATE TABLE users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id  INT UNSIGNED NOT NULL,
  email           VARCHAR(150) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  display_name    VARCHAR(100) NOT NULL,
  role            ENUM('student','moderator','admin') NOT NULL DEFAULT 'student',
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (institution_id) REFERENCES institutions(id)
) ENGINE=InnoDB;

CREATE TABLE courses (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id INT UNSIGNED NOT NULL,
  name           VARCHAR(150) NOT NULL,
  UNIQUE KEY courses_inst_name_uq (institution_id, name),
  FOREIGN KEY (institution_id) REFERENCES institutions(id)
) ENGINE=InnoDB;

CREATE TABLE subjects (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  name      VARCHAR(150) NOT NULL,
  UNIQUE KEY subjects_course_name_uq (course_id, name),
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- file_ref is a server-generated random name inside uploads/ — never a
-- public URL and never user-controlled input (T-08: no path traversal).
CREATE TABLE notes (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uploader_id        INT UNSIGNED NOT NULL,
  subject_id         INT UNSIGNED NOT NULL,
  title              VARCHAR(200) NOT NULL,
  description        TEXT,
  status             ENUM('draft','pending','published','rejected','removed')
                       NOT NULL DEFAULT 'pending',
  file_ref           VARCHAR(64) NOT NULL,
  original_name      VARCHAR(255) NOT NULL,
  mime               VARCHAR(100) NOT NULL,
  size_bytes         INT UNSIGNED NOT NULL,
  ownership_attested TINYINT(1) NOT NULL,
  view_count         INT UNSIGNED NOT NULL DEFAULT 0,
  download_count     INT UNSIGNED NOT NULL DEFAULT 0,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_at       TIMESTAMP NULL,
  FOREIGN KEY (uploader_id) REFERENCES users(id),
  FOREIGN KEY (subject_id) REFERENCES subjects(id),
  INDEX notes_status_created_idx (status, created_at),
  INDEX notes_subject_idx (subject_id),
  FULLTEXT KEY notes_search_idx (title, description),
  CONSTRAINT notes_title_len CHECK (CHAR_LENGTH(title) BETWEEN 3 AND 200),
  CONSTRAINT notes_attested CHECK (ownership_attested = 1)
) ENGINE=InnoDB;

CREATE TABLE tags (
  id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE note_tags (
  note_id INT UNSIGNED NOT NULL,
  tag_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (note_id, tag_id),
  FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id)  REFERENCES tags(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- Reporter identity is never exposed to anyone except admin debugging
-- (T-10). reason values mirror moderation reason categories.
CREATE TABLE reports (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  note_id     INT UNSIGNED NOT NULL,
  reporter_id INT UNSIGNED NOT NULL,
  reason      VARCHAR(20) NOT NULL
              CHECK (reason IN ('copyright','low_quality','duplicate',
                                'off_topic','abusive','privacy','other')),
  details     VARCHAR(1000),
  status      ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (note_id)     REFERENCES notes(id)  ON DELETE CASCADE,
  FOREIGN KEY (reporter_id) REFERENCES users(id),
  UNIQUE KEY reports_note_reporter_uq (note_id, reporter_id),
  INDEX reports_status_idx (status)
) ENGINE=InnoDB;

-- Append-only audit log (FR-11): UPDATE and DELETE are blocked by triggers.
CREATE TABLE moderation_actions (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  note_id         INT UNSIGNED NOT NULL,
  actor_id        INT UNSIGNED NOT NULL,
  action          ENUM('approve','reject','remove','restore','takedown') NOT NULL,
  reason_category VARCHAR(20)
                  CHECK (reason_category IN ('copyright','low_quality','duplicate',
                        'off_topic','abusive','privacy','other')),
  internal_note   VARCHAR(1000),
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (note_id)  REFERENCES notes(id),
  FOREIGN KEY (actor_id) REFERENCES users(id),
  INDEX moderation_actions_note_idx (note_id),
  INDEX moderation_actions_created_idx (created_at)
) ENGINE=InnoDB;

DELIMITER //
CREATE TRIGGER moderation_actions_no_update BEFORE UPDATE ON moderation_actions
  FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'moderation_actions is append-only'; END //
CREATE TRIGGER moderation_actions_no_delete BEFORE DELETE ON moderation_actions
  FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'moderation_actions is append-only'; END //
DELIMITER ;

-- Transparency view (D-P01, option A — redacted): actor identity,
-- internal notes and reporter identity are intentionally excluded.
CREATE VIEW transparency_log AS
  SELECT m.id, m.note_id, n.title AS note_title, m.action,
         m.reason_category, m.created_at, u.role AS actor_role
  FROM moderation_actions m
  JOIN users u ON u.id = m.actor_id
  JOIN notes n ON n.id = m.note_id;

CREATE TABLE bookmarks (
  user_id    INT UNSIGNED NOT NULL,
  note_id    INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, note_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
  INDEX bookmarks_note_idx (note_id)
) ENGINE=InnoDB;

-- ============================================================
-- Seed data — one college (D-P06: Option "One college").
-- ============================================================

INSERT INTO institutions (id, name, email_domain) VALUES
  (1, 'Westfield College', 'westfield.edu');

-- password_hash('…123', PASSWORD_DEFAULT) values generated by PHP 8.5.
INSERT INTO users (id, institution_id, email, password_hash, display_name, role) VALUES
  (1, 1, 'admin@westfield.edu',
   '$2y$12$LM7xIZzasWMNXepA7zShZerLvIRQZEcdKA/0G1IqhgZMFSPdsH6pq', 'Ada Admin', 'admin'),
  (2, 1, 'moderator@westfield.edu',
   '$2y$12$hesDnNLibL15wgaFUywT/uZ2o0QURHjpPN3DJdr6wvApEG41RQM0u', 'Mia Moderator', 'moderator'),
  (3, 1, 'student@westfield.edu',
   '$2y$12$rfNqO2GCi/omFMvT8.UFUeeusqM5huOCIWoiU6h4U.0eYVqJVfLIe', 'Sam Student', 'student');

INSERT INTO courses (id, institution_id, name) VALUES
  (1, 1, 'Computer Science'),
  (2, 1, 'Information Technology'),
  (3, 1, 'Business Studies');

INSERT INTO subjects (id, course_id, name) VALUES
  (1, 1, 'Programming Fundamentals'),
  (2, 1, 'Data Structures & Algorithms'),
  (3, 1, 'Databases'),
  (4, 2, 'Web Development'),
  (5, 2, 'Computer Networks'),
  (6, 3, 'Marketing Basics');

INSERT INTO tags (id, name) VALUES
  (1, 'java'), (2, 'exam'), (3, 'sql'), (4, 'html'), (5, 'css'),
  (6, 'networks'), (7, 'dsa'), (8, 'python'), (9, 'marketing');

-- file_ref values match sample files shipped in uploads/ so the demo
-- works before any real upload exists.
INSERT INTO notes (id, uploader_id, subject_id, title, description, status,
                   file_ref, original_name, mime, size_bytes, ownership_attested,
                   view_count, download_count, created_at, published_at) VALUES
  (1,  3, 1, 'Java Basics — Sem 1 Lecture Summary',
      'Variables, loops, arrays and OOP intro from Lectures 1–6, own words.', 'published',
      'sample-java-notes.pdf', 'Java Basics Lecture Summary.pdf', 'application/pdf', 18432, 1, 23, 9,
      NOW() - INTERVAL 63 DAY, NOW() - INTERVAL 62 DAY),
  (2,  3, 2, 'Sorting Algorithms Cheat Sheet',
      'Bubble, merge, quick and insertion sort with complexity table.', 'published',
      'sample-sorting.pdf', 'Sorting Cheat Sheet.pdf', 'application/pdf', 15360, 1, 41, 22,
      NOW() - INTERVAL 56 DAY, NOW() - INTERVAL 55 DAY),
  (3,  3, 3, 'SQL Joins Explained with Examples',
      'INNER, LEFT, RIGHT and FULL joins with sample tables and results.', 'published',
      'sample-sql-joins.pdf', 'SQL Joins Explained.pdf', 'application/pdf', 20480, 1, 15, 4,
      NOW() - INTERVAL 42 DAY, NOW() - INTERVAL 41 DAY),
  (4,  3, 4, 'HTML Forms & CSS Grid Practical Notes',
      'Every input type and a grid layout exercise from the lab.', 'published',
      'sample-diagram.png', 'HTML Forms Diagram.png', 'image/png', 1024, 1, 30, 12,
      NOW() - INTERVAL 35 DAY, NOW() - INTERVAL 34 DAY),
  (5,  2, 5, 'OSI Model Diagrams & Port Numbers',
      'Seven layers drawn from memory plus the port numbers that come up in the exam.', 'published',
      'sample-osi.pdf', 'OSI Model Notes.pdf', 'application/pdf', 17408, 1, 7, 3,
      NOW() - INTERVAL 28 DAY, NOW() - INTERVAL 27 DAY),
  (6,  2, 6, 'Marketing 4Ps Class Notes',
      'Product, price, place, promotion with class discussion examples.', 'published',
      'sample-4ps.pdf', 'Marketing 4Ps Notes.pdf', 'application/pdf', 13312, 1, 18, 5,
      NOW() - INTERVAL 14 DAY, NOW() - INTERVAL 13 DAY),
  (7,  3, 2, 'Binary Search Trees — Lecture 5 Notes',
      'Insert, delete and traversals with hand-traced diagrams.', 'pending',
      'sample-bst.pdf', 'BST Lecture 5.pdf', 'application/pdf', 11264, 1, 0, 0,
      NOW() - INTERVAL 2 DAY, NULL),
  (8,  2, 4, 'JavaScript Events Cheat Sheet',
      'addEventListener patterns and bubbling demo code.', 'pending',
      'sample-js-events.pdf', 'JS Events Cheat Sheet.pdf', 'application/pdf', 12800, 1, 0, 0,
      NOW() - INTERVAL 1 DAY, NULL),
  (9,  3, 1, 'Exam Predictions 2026 (Unverified)',
      'Compilation of guesses about what will appear in the exam.', 'rejected',
      'sample-predictions.pdf', 'Exam Predictions.pdf', 'application/pdf', 9216, 1, 2, 1,
      NOW() - INTERVAL 20 DAY, NULL),
  (10, 3, 3, 'ER Diagrams — Draft Work in Progress',
      'First pass of the ER diagram assignment; not finished yet.', 'draft',
      'sample-er.pdf', 'ER Diagrams Draft.pdf', 'application/pdf', 8192, 1, 0, 0,
      NOW() - INTERVAL 5 DAY, NULL),
  (11, 3, 1, 'Copy of Textbook Chapter 4',
      'Scanned chapter from the official book.', 'removed',
      'sample-textbook.pdf', 'Textbook Chapter 4.pdf', 'application/pdf', 40960, 1, 33, 14,
      NOW() - INTERVAL 30 DAY, NULL),
  (12, 2, 5, 'Subnetting Practice Problems',
      'Twenty subnetting exercises with worked answers.', 'published',
      'sample-subnetting.pdf', 'Subnetting Practice.pdf', 'application/pdf', 21504, 1, 51, 27,
      NOW() - INTERVAL 7 DAY, NOW() - INTERVAL 6 DAY);

INSERT INTO note_tags (note_id, tag_id) VALUES
  (1, 1), (1, 2), (2, 7), (2, 2), (3, 3), (4, 4), (4, 5), (5, 6), (6, 9),
  (7, 7), (8, 4), (12, 6), (12, 2);

-- Moderation history feeds the audit log, transparency view (D-P01)
-- and admin dashboard. Append-only: never edited later (FR-11).
INSERT INTO moderation_actions (note_id, actor_id, action, reason_category,
                                internal_note, created_at) VALUES
  (1,  2, 'approve', NULL, NULL, NOW() - INTERVAL 62 DAY),
  (2,  2, 'approve', NULL, NULL, NOW() - INTERVAL 55 DAY),
  (12, 2, 'approve', NULL, NULL, NOW() - INTERVAL 6 DAY),
  (9,  2, 'reject',  'low_quality',
      'Note is speculation; content standards say no unverified exam claims.',
      NOW() - INTERVAL 19 DAY),
  (11, 1, 'remove',  'copyright',
      'Publisher complaint reference #4412. Takedown recorded.', NOW() - INTERVAL 24 DAY),
  (3,  1, 'restore', NULL,
      'Report on note 3 was dismissal pending review; note stays.', NOW() - INTERVAL 10 DAY);

INSERT INTO reports (note_id, reporter_id, reason, details, status, created_at) VALUES
  (3,  2, 'abusive',   'Sample row so the review page has a report to work through.', 'open',  NOW() - INTERVAL 11 DAY),
  (11, 2, 'copyright', 'Uploader does not own the textbook; resolved via takedown.',  'resolved', NOW() - INTERVAL 25 DAY);

INSERT INTO bookmarks (user_id, note_id, created_at) VALUES
  (3, 2, NOW() - INTERVAL 50 DAY),
  (3, 12, NOW() - INTERVAL 6 DAY);
````

---

## Notes on the listing

- `uploads/.htaccess` and the root `.htaccess` are Apache directives, shown as
  plain text for completeness.
- `note_portal.sql` contains schema **and** seed data in one file — importing it
  recreates the whole demo database.
- Screenshots of the running application: `screenshots/`.
