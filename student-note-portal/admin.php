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
