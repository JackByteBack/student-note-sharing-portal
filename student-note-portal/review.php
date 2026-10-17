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
