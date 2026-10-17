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
require __DIR__ . '/includes/header.php';
?>

<form class="filters" method="get">
  <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search title or description…" aria-label="Search">
  <select name="course" aria-label="Course">
    <option value="">All courses</option>
    <?php foreach ($courses as $c): ?>
      <option value="<?= (int) $c['id'] ?>" <?= (!$subjectId && (int) $_GET['course'] == $c['id']) ? 'selected' : '' ?>>
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

<?php require __DIR__ . '/includes/footer.php'; ?>
