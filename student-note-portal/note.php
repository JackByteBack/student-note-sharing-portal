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
