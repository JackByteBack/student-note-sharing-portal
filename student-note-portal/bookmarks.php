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
