<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

// --- Handle POST actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Invalid security token. Please try again.'];
        header('Location: users.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $me = current_user_id();

    $targetStmt = $pdo->prepare('SELECT id, name, email, role, status FROM users WHERE id = ?');
    $targetStmt->execute([$id]);
    $target = $targetStmt->fetch();

    if (!$target) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'User not found.'];
        header('Location: users.php');
        exit;
    }
    if ($id === $me) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'You cannot change your own account from this page.'];
        header('Location: users.php');
        exit;
    }

    $admStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id <> ?");
    $admStmt->execute([$id]);
    $otherAdmins = (int) $admStmt->fetchColumn();

    $done = false;
    switch ($action) {
        case 'promote':
            if ($target['role'] === 'admin') {
                $flash = ['type' => 'error', 'message' => $target['name'] . ' is already an administrator.'];
            } else {
                $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$id]);
                log_activity($pdo, 'user_promote', 'User: ' . $target['name'] . ' (' . $target['email'] . ')');
                $flash = ['type' => 'success', 'message' => $target['name'] . ' is now an administrator.'];
                $done = true;
            }
            break;

        case 'demote':
            if ($target['role'] !== 'admin') {
                $flash = ['type' => 'error', 'message' => $target['name'] . ' is not an administrator.'];
            } elseif ($otherAdmins < 1) {
                $flash = ['type' => 'error', 'message' => 'Cannot demote the last remaining administrator.'];
            } else {
                $pdo->prepare("UPDATE users SET role = 'citizen' WHERE id = ?")->execute([$id]);
                log_activity($pdo, 'user_demote', 'User: ' . $target['name'] . ' (' . $target['email'] . ')');
                $flash = ['type' => 'success', 'message' => $target['name'] . ' is no longer an administrator.'];
                $done = true;
            }
            break;

        case 'suspend':
            if ($target['status'] === 'suspended') {
                $flash = ['type' => 'error', 'message' => $target['name'] . ' is already suspended.'];
            } elseif ($target['role'] === 'admin') {
                $flash = ['type' => 'error', 'message' => 'Demote the administrator first before suspending the account.'];
            } else {
                $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?")->execute([$id]);
                log_activity($pdo, 'user_suspend', 'User: ' . $target['name'] . ' (' . $target['email'] . ')');
                $flash = ['type' => 'success', 'message' => $target['name'] . ' has been suspended.'];
                $done = true;
            }
            break;

        case 'unsuspend':
            if ($target['status'] !== 'suspended') {
                $flash = ['type' => 'error', 'message' => $target['name'] . ' is not suspended.'];
            } else {
                $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$id]);
                log_activity($pdo, 'user_unsuspend', 'User: ' . $target['name'] . ' (' . $target['email'] . ')');
                $flash = ['type' => 'success', 'message' => $target['name'] . ' can sign in again.'];
                $done = true;
            }
            break;

        case 'delete':
            if ($target['role'] === 'admin') {
                $flash = ['type' => 'error', 'message' => 'Demote the administrator first before deleting the account.'];
            } else {
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                log_activity($pdo, 'user_delete', 'User: ' . $target['name'] . ' (' . $target['email'] . ')');
                $flash = ['type' => 'success', 'message' => $target['name'] . ' was deleted (their chats and feedback were removed too).'];
                $done = true;
            }
            break;

        default:
            $flash = ['type' => 'error', 'message' => 'Unknown action.'];
    }

    $_SESSION['admin_flash'] = $flash;
    header('Location: users.php' . ($done ? '' : ''));
    exit;
}

require_once __DIR__ . '/includes/header.php';

// --- Filters ---
$search = trim($_GET['q'] ?? '');
$role = trim($_GET['role'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($role === 'admin' || $role === 'citizen') {
    $where[] = 'u.role = ?';
    $params[] = $role;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$cntStmt = $pdo->prepare('SELECT COUNT(*) FROM users u' . $whereSql);
$cntStmt->execute($params);
$total = (int) $cntStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.role, u.status, u.google_id, u.created_at,
           (SELECT MAX(c.created_at) FROM chats c WHERE c.user_id = u.id) AS last_active,
           (SELECT COUNT(DISTINCT c.conversation_id) FROM chats c WHERE c.user_id = u.id) AS conversations
    FROM users u" . $whereSql . "
    ORDER BY u.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Header stat chips
$stats = $pdo->query("
    SELECT COUNT(*) AS total,
           SUM(role = 'admin') AS admins,
           SUM(status = 'suspended') AS suspended
    FROM users
")->fetch();

$badgeFor = [
    'admin'     => 'badge-ok',
    'suspended' => 'badge-bad',
];
?>
<h1>Users</h1>

<div class="stat-grid stat-grid-sm">
  <div class="stat-card"><span class="stat-value"><?= (int) $stats['total'] ?></span><span class="stat-label">Total Accounts</span></div>
  <div class="stat-card"><span class="stat-value"><?= (int) $stats['admins'] ?></span><span class="stat-label">Administrators</span></div>
  <div class="stat-card"><span class="stat-value"><?= (int) $stats['suspended'] ?></span><span class="stat-label">Suspended</span></div>
</div>

<form method="GET" class="inline-filter activity-filter">
  <label>Search (name / email):
    <input type="text" name="q" class="form-control form-control-sm" value="<?= sanitize($search) ?>" placeholder="e.g. juan">
  </label>
  <label>Role:
    <select name="role" class="form-select form-select-sm">
      <option value="">All</option>
      <option value="citizen" <?= $role === 'citizen' ? 'selected' : '' ?>>Citizen</option>
      <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrator</option>
    </select>
  </label>
  <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
  <a href="users.php" class="btn btn-secondary btn-sm">Reset</a>
</form>

<table class="table admin-table">
  <thead>
    <tr><th>User</th><th>Signup</th><th>Role</th><th>Status</th><th>Conversations</th><th>Last Activity</th><th>Actions</th></tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <?php $isMe = (int) $r['id'] === current_user_id(); ?>
      <tr>
        <td>
          <?= sanitize($r['name']) ?><?= $isMe ? ' <small>(you)</small>' : '' ?>
          <br><small><?= sanitize($r['email']) ?></small>
        </td>
        <td><?= $r['google_id'] ? '<span class="badge badge-ok">Gmail</span>' : '<span class="badge">Password</span>' ?></td>
        <td><span class="badge <?= $badgeFor[$r['role']] ?? '' ?>"><?= sanitize($r['role']) ?></span></td>
        <td>
          <?php if ($r['status'] === 'suspended'): ?>
            <span class="badge badge-bad">Suspended</span>
          <?php else: ?>
            <span class="badge badge-ok">Active</span>
          <?php endif; ?>
        </td>
        <td><?= (int) $r['conversations'] ?></td>
        <td><small><?= $r['last_active'] ? date('M j, Y', strtotime($r['last_active'])) : '&ndash;' ?></small></td>
        <td>
          <div class="table-actions">
            <?php if (!$isMe): ?>
              <?php if ($r['role'] === 'admin'): ?>
                <form method="POST" action="users.php" onsubmit="return confirm('Remove administrator access from <?= sanitize($r['name']) ?>?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="demote">
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="btn btn-secondary btn-sm">Demote</button>
                </form>
              <?php else: ?>
                <form method="POST" action="users.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="promote">
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="btn btn-secondary btn-sm">Make Admin</button>
                </form>
              <?php endif; ?>

              <?php if ($r['status'] === 'suspended'): ?>
                <form method="POST" action="users.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="unsuspend">
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="btn btn-secondary btn-sm">Unsuspend</button>
                </form>
              <?php elseif ($r['role'] !== 'admin'): ?>
                <form method="POST" action="users.php" onsubmit="return confirm('Suspend <?= sanitize($r['name']) ?>? They will be logged out and blocked from signing in.')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="suspend">
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="btn btn-secondary btn-sm">Suspend</button>
                </form>
              <?php endif; ?>

              <?php if ($r['role'] !== 'admin'): ?>
                <form method="POST" action="users.php" onsubmit="return confirm('Delete <?= sanitize($r['name']) ?> permanently? Their chats and feedback will also be removed. This cannot be undone.')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
      <tr><td colspan="7" class="empty-hint">No users match this filter.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<?php if ($pages > 1): ?>
  <nav class="pagination-nav">
    <?php
    $qs = http_build_query(array_filter([
        'q' => $search !== '' ? $search : null,
        'role' => $role !== '' ? $role : null,
    ]));
    $qs = $qs ? $qs . '&' : '';
    for ($i = 1; $i <= $pages; $i++): ?>
      <a class="btn btn-secondary btn-sm <?= $i === $page ? 'active' : '' ?>" href="?<?= $qs ?>page=<?= $i ?>"><?= $i ?></a>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>