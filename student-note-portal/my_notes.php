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
