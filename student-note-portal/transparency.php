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
