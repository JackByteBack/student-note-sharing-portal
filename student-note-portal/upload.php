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
