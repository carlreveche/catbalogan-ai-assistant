<?php
require_once __DIR__ . '/includes/header.php';

// --- Handle POST actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Invalid security token. Please try again.'];
        header('Location: kb.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        $delRow = $pdo->prepare('SELECT permit_id, intent FROM kb_entries WHERE id = ?');
        $delRow->execute([$id]);
        $delInfo = $delRow->fetch();
        $pdo->prepare('DELETE FROM kb_entries WHERE id = ?')->execute([$id]);
        log_activity($pdo, 'kb_delete', 'KB entry #' . $id . ' (intent: ' . ($delInfo['intent'] ?? '?') . ', permit_id: ' . ($delInfo['permit_id'] ?? 'NULL') . ')');
        $_SESSION['admin_flash'] = ['type' => 'success', 'message' => 'Knowledge base entry deleted.'];
        header('Location: kb.php');
        exit;
    }

    if ($action === 'save') {
        $permitId = (int) ($_POST['permit_id'] ?? 0);
        $intent = trim($_POST['intent'] ?? '');
        $keywords = trim($_POST['keywords'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        $priority = (int) ($_POST['priority'] ?? 1);

        if ($intent === '' || $keywords === '' || $answer === '') {
            $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Intent, keywords, and answer are required.'];
        } else {
            if ($id > 0) {
                $pdo->prepare('UPDATE kb_entries SET permit_id = ?, intent = ?, keywords = ?, answer = ?, priority = ? WHERE id = ?')
                    ->execute([$permitId ?: null, $intent, $keywords, $answer, $priority, $id]);
            } else {
                $pdo->prepare('INSERT INTO kb_entries (permit_id, intent, keywords, answer, priority) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$permitId ?: null, $intent, $keywords, $answer, $priority]);
            }

            // Regression check: matcher eval must still pass after the edit
            require_once __DIR__ . '/../tests/eval_lib.php';
            $report = run_eval($pdo);
            if ($report['failed'] > 0) {
                $_SESSION['admin_flash'] = ['type' => 'error', 'message' => ($id > 0 ? 'Entry updated' : 'Entry created') . ' but matcher regression check FAILED: ' . eval_summary($report) . '. Failing case(s): ' . implode(' | ', array_map(fn ($r) => '"' . $r['case'] . '" -> ' . ($r['reason'] ?? '?'), array_filter($report['results'], fn ($r) => !$r['pass'])))];
                log_activity($pdo, $id > 0 ? 'kb_update' : 'kb_create', 'KB entry #' . $id . ' (intent: ' . $intent . ', permit_id: ' . ($permitId ?: 'NULL') . ') - regression check FAILED ' . eval_summary($report));
            } else {
                $_SESSION['admin_flash'] = ['type' => 'success', 'message' => ($id > 0 ? 'Entry updated' : 'Entry created') . '. Matcher regression check: ' . eval_summary($report) . ' - all good.'];
                log_activity($pdo, $id > 0 ? 'kb_update' : 'kb_create', 'KB entry #' . $id . ' (intent: ' . $intent . ', permit_id: ' . ($permitId ?: 'NULL') . ') - regression check ' . eval_summary($report));
            }
            header('Location: kb.php');
            exit;
        }
    }
}

// --- Determine edit target ---
$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM kb_entries WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
}

$permits = $pdo->query('SELECT id, name FROM permits ORDER BY name')->fetchAll();

$filterPermit = (int) ($_GET['permit'] ?? 0);
$sql = "SELECT k.*, p.name AS permit_name
        FROM kb_entries k
        LEFT JOIN permits p ON p.id = k.permit_id";
$params = [];
if ($filterPermit > 0) {
    $sql .= ' WHERE k.permit_id = ?';
    $params[] = $filterPermit;
}
$sql .= ' ORDER BY k.permit_id, k.priority DESC, k.intent';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();
?>
<h1>Knowledge Base</h1>

<div class="admin-cols">
  <section class="panel">
    <h2><?= $editing ? 'Edit Entry' : 'Add New Entry' ?></h2>
    <div class="alert alert-info">Note: permit answers are generated automatically from the Permit records (single source of truth). KB answers here only fill gaps when a permit has no data for the asked intent.</div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $editing ? (int) $editing['id'] : 0 ?>">

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Permit (blank = general/small-talk)</label>
          <select name="permit_id" class="form-select">
            <option value="0">-- General (no permit) --</option>
            <?php foreach ($permits as $p): ?>
              <option value="<?= (int) $p['id'] ?>" <?= (($editing['permit_id'] ?? 0) == $p['id']) ? 'selected' : '' ?>>
                <?= sanitize($p['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Intent</label>
          <select name="intent" class="form-select">
            <?php foreach (['overview', 'requirements', 'steps', 'fees', 'processing_time', 'deadline', 'where', 'greeting', 'thanks', 'goodbye', 'help_menu', 'office_hours', 'fallback'] as $opt): ?>
              <option value="<?= $opt ?>" <?= (($editing['intent'] ?? '') === $opt) ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Priority (higher wins ties)</label>
          <input type="number" name="priority" class="form-control" min="0" max="10" value="<?= (int) ($editing['priority'] ?? 1) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Keywords (comma-separated phrases, English + Tagalog)</label>
          <textarea name="keywords" class="form-control" rows="3"><?= sanitize($editing['keywords'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label">Answer</label>
          <textarea name="answer" class="form-control" rows="6"><?= sanitize($editing['answer'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $editing ? 'Save Changes' : 'Create Entry' ?></button>
        <?php if ($editing): ?>
          <a href="kb.php<?= $filterPermit ? '?permit=' . $filterPermit : '' ?>" class="btn btn-secondary">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <section class="panel">
    <h2>Entries (<?= count($entries) ?>)</h2>
    <form method="GET" class="inline-filter">
      <label>Filter by permit:
        <select name="permit" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">All</option>
          <option value="-1" <?= $filterPermit === -1 ? 'selected' : '' ?>>General only</option>
          <?php foreach ($permits as $p): ?>
            <option value="<?= (int) $p['id'] ?>" <?= $filterPermit === (int) $p['id'] ? 'selected' : '' ?>><?= sanitize($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <a href="kb.php" class="btn btn-secondary btn-sm">Reset</a>
    </form>
    <table class="table admin-table">
      <thead>
        <tr><th>Permit</th><th>Intent</th><th>Keywords</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($entries as $e): ?>
          <tr>
            <td><?= sanitize($e['permit_name'] ?? 'General') ?></td>
            <td><code><?= sanitize($e['intent']) ?></code></td>
            <td class="td-truncate" title="<?= sanitize($e['keywords']) ?>"><?= sanitize(mb_substr($e['keywords'], 0, 70)) ?>&hellip;</td>
            <td class="table-actions">
              <a class="btn btn-secondary btn-sm" href="?edit=<?= (int) $e['id'] ?>&permit=<?= $filterPermit ?>">Edit</a>
              <form method="POST" onsubmit="return confirm('Delete this entry?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($entries)): ?>
          <tr><td colspan="4" class="empty-hint">No entries match this filter.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </section>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>