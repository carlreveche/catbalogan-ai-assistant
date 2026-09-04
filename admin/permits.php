<?php
require_once __DIR__ . '/includes/header.php';

// --- Handle POST actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Invalid security token. Please try again.'];
        header('Location: permits.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        $delRow = $pdo->prepare('SELECT name FROM permits WHERE id = ?');
        $delRow->execute([$id]);
        $delName = $delRow->fetchColumn() ?: "#$id";
        $pdo->prepare('DELETE FROM permits WHERE id = ?')->execute([$id]);
        log_activity($pdo, 'permit_delete', 'Permit: ' . $delName);
        $_SESSION['admin_flash'] = ['type' => 'success', 'message' => 'Permit deleted (its knowledge base entries were removed too).'];
        header('Location: permits.php');
        exit;
    }

    if ($action === 'save') {
        $code = strtolower(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $office = trim($_POST['office'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $requirements = trim($_POST['requirements'] ?? '');
        $steps = trim($_POST['steps'] ?? '');
        $fees = trim($_POST['fees'] ?? '');
        $processing_time = trim($_POST['processing_time'] ?? '');
        $validity = trim($_POST['validity'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $verified_at = trim($_POST['verified_at'] ?? '');
        if ($verified_at !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $verified_at)) {
            $verified_at = '';
        }
        if ($verified_at === '') {
            $verified_at = null;
        }

        $code = preg_replace('/[^a-z0-9_]/', '_', $code);

        if ($code === '' || $name === '' || $office === '') {
            $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Code, name, and office are required.'];
        } else {
            // code must be unique (except when editing the same row)
            $dup = $pdo->prepare('SELECT id FROM permits WHERE code = ? AND id <> ?');
            $dup->execute([$code, $id]);
            if ($dup->fetchColumn()) {
                $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'That code is already used by another permit.'];
            } else {
                if ($id > 0) {
                    $pdo->prepare("
                        UPDATE permits SET code = ?, name = ?, office = ?, description = ?,
                            requirements = ?, steps = ?, fees = ?, processing_time = ?, validity = ?,
                            address = ?, contact = ?, verified_at = ?
                        WHERE id = ?
                    ")->execute([$code, $name, $office, $description, $requirements, $steps, $fees, $processing_time, $validity, $address, $contact, $verified_at, $id]);
                } else {
                    $pdo->prepare("
                        INSERT INTO permits (code, name, office, description, requirements, steps, fees, processing_time, validity, address, contact, verified_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ")->execute([$code, $name, $office, $description, $requirements, $steps, $fees, $processing_time, $validity, $address, $contact, $verified_at]);
                    $newId = (int) $pdo->lastInsertId();
                    // Auto-create an overview KB entry so the matcher can detect the new permit
                    $pdo->prepare("
                        INSERT INTO kb_entries (permit_id, intent, keywords, answer, priority)
                        VALUES (?, 'overview', ?, ?, 2)
                    ")->execute([$newId, $name . ', about ' . $name, $name . ' - ' . ($description ?: 'Ask about its requirements, steps, fees, or processing time.')]);
                }

                // Regression check: matcher eval must still pass after the edit
                require_once __DIR__ . '/../tests/eval_lib.php';
                $report = run_eval($pdo);
                if ($report['failed'] > 0) {
                    $_SESSION['admin_flash'] = ['type' => 'error', 'message' => ($id > 0 ? 'Permit updated' : 'Permit created') . ' but matcher regression check FAILED: ' . eval_summary($report) . '. Failing case(s): ' . implode(' | ', array_map(fn ($r) => '"' . $r['case'] . '" -> ' . ($r['reason'] ?? '?'), array_filter($report['results'], fn ($r) => !$r['pass'])))];
                    log_activity($pdo, $id > 0 ? 'permit_update' : 'permit_create', ($id > 0 ? 'Permit: ' : 'New permit: ') . $name . ' (code: ' . $code . ') - regression check FAILED ' . eval_summary($report));
                } else {
                    $_SESSION['admin_flash'] = ['type' => 'success', 'message' => ($id > 0 ? 'Permit updated' : 'Permit created') . '. Matcher regression check: ' . eval_summary($report) . ' - all good.'];
                    log_activity($pdo, $id > 0 ? 'permit_update' : 'permit_create', ($id > 0 ? 'Permit: ' : 'New permit: ') . $name . ' (code: ' . $code . ') - regression check ' . eval_summary($report));
                }
                header('Location: permits.php');
                exit;
            }
        }
    }
}

// --- Determine edit target ---
$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM permits WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
}

$permits = $pdo->query("
    SELECT p.*, (SELECT COUNT(*) FROM kb_entries k WHERE k.permit_id = p.id) AS kb_count
    FROM permits p
    ORDER BY p.id
")->fetchAll();
?>
<h1>Permits</h1>

<div class="admin-cols">
  <section class="panel">
    <h2><?= $editing ? 'Edit Permit' : 'Add New Permit' ?></h2>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $editing ? (int) $editing['id'] : 0 ?>">

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Code (unique, lowercase, e.g. cedula)</label>
          <input type="text" name="code" class="form-control" value="<?= sanitize($editing['code'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Name</label>
          <input type="text" name="name" class="form-control" value="<?= sanitize($editing['name'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Issuing Office</label>
          <input type="text" name="office" class="form-control" value="<?= sanitize($editing['office'] ?? '') ?>" required>
        </div>
        <div class="col-12">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="3"><?= sanitize($editing['description'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label">Requirements (one per line)</label>
          <textarea name="requirements" class="form-control" rows="5"><?= sanitize($editing['requirements'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label">Steps (one per line)</label>
          <textarea name="steps" class="form-control" rows="5"><?= sanitize($editing['steps'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">Fees</label>
          <input type="text" name="fees" class="form-control" value="<?= sanitize($editing['fees'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Processing Time</label>
          <input type="text" name="processing_time" class="form-control" value="<?= sanitize($editing['processing_time'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Validity</label>
          <input type="text" name="validity" class="form-control" value="<?= sanitize($editing['validity'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Office Address</label>
          <input type="text" name="address" class="form-control" value="<?= sanitize($editing['address'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Contact Number / Email</label>
          <input type="text" name="contact" class="form-control" value="<?= sanitize($editing['contact'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Verified On (YYYY-MM-DD)</label>
          <input type="text" name="verified_at" class="form-control" placeholder="2025-06-30" value="<?= sanitize($editing['verified_at'] ?? '') ?>">
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $editing ? 'Save Changes' : 'Create Permit' ?></button>
        <?php if ($editing): ?>
          <a href="permits.php" class="btn btn-secondary">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <section class="panel">
    <h2>Permit List</h2>
    <table class="table admin-table">
      <thead>
        <tr><th>Name</th><th>Office</th><th>KB Entries</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($permits as $p): ?>
          <tr>
            <td><strong><?= sanitize($p['name']) ?></strong><br><small><?= sanitize($p['code']) ?></small></td>
            <td><?= sanitize($p['office']) ?></td>
            <td><?= (int) $p['kb_count'] ?></td>
            <td class="table-actions">
              <a class="btn btn-secondary btn-sm" href="?edit=<?= (int) $p['id'] ?>">Edit</a>
              <form method="POST" onsubmit="return confirm('Delete this permit and all its KB entries?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($permits)): ?>
          <tr><td colspan="4" class="empty-hint">No permits yet. Add your first one.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </section>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>