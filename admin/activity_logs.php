<?php
require_once __DIR__ . '/includes/header.php';

// --- Filters ---
$action = trim($_GET['action'] ?? '');
$search = trim($_GET['q'] ?? '');
$days = (int) ($_GET['days'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;

$where = [];
$params = [];
if ($action !== '' && $action !== 'all') {
    $where[] = 'a.action = ?';
    $params[] = $action;
}
if ($search !== '') {
    $where[] = '(a.user_name LIKE ? OR a.details LIKE ? OR a.ip_address LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($days > 0) {
    $where[] = 'a.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
    $params[] = $days;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs a" . $whereSql);
$cntStmt->execute($params);
$total = (int) $cntStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT a.* FROM activity_logs a" . $whereSql . "
    ORDER BY a.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Distinct actions for the filter dropdown
$actions = $pdo->query('SELECT DISTINCT action FROM activity_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);

// Stats chips
$stats = $pdo->query("
    SELECT COUNT(*) AS total,
           SUM(action IN ('permit_create','permit_update','permit_delete','kb_create','kb_update','kb_delete')) AS edits,
           SUM(action = 'login_success') AS logins,
           SUM(action = 'login_failed') AS failed_logins
    FROM activity_logs
")->fetch();

$badgeFor = [
    'permit_create'  => 'badge-ok',
    'permit_update'  => 'badge-ok',
    'permit_delete'  => 'badge-bad',
    'kb_create'      => 'badge-ok',
    'kb_update'      => 'badge-ok',
    'kb_delete'      => 'badge-bad',
    'login_success'  => 'badge-ok',
    'login_failed'   => 'badge-bad',
    'logout'         => '',
];
?>
<h1>Activity Logs</h1>

<div class="stat-grid stat-grid-sm">
  <div class="stat-card"><span class="stat-value"><?= (int) $stats['total'] ?></span><span class="stat-label">Total Events</span></div>
  <div class="stat-card"><span class="stat-value"><?= (int) $stats['edits'] ?></span><span class="stat-label">Content Edits</span></div>
  <div class="stat-card"><span class="stat-value"><?= (int) $stats['logins'] ?></span><span class="stat-label">Logins</span></div>
  <div class="stat-card"><span class="stat-value"><?= (int) $stats['failed_logins'] ?></span><span class="stat-label">Failed Logins</span></div>
</div>

<form method="GET" class="inline-filter activity-filter">
  <label>Action:
    <select name="action" class="form-select form-select-sm">
      <option value="all">All</option>
      <?php foreach ($actions as $a): ?>
        <option value="<?= sanitize($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= sanitize($a) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Search (user / detail / IP):
    <input type="text" name="q" class="form-control form-control-sm" value="<?= sanitize($search) ?>" placeholder="e.g. cedula">
  </label>
  <label>Period:
    <select name="days" class="form-select form-select-sm">
      <option value="0">All time</option>
      <option value="1" <?= $days === 1 ? 'selected' : '' ?>>Last 24 hours</option>
      <option value="7" <?= $days === 7 ? 'selected' : '' ?>>Last 7 days</option>
      <option value="30" <?= $days === 30 ? 'selected' : '' ?>>Last 30 days</option>
    </select>
  </label>
  <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
  <a href="activity_logs.php" class="btn btn-secondary btn-sm">Reset</a>
</form>

<table class="table admin-table">
  <thead>
    <tr><th>When</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><small><?= date('M j, Y g:i A', strtotime($r['created_at'])) ?></small></td>
        <td><?= sanitize($r['user_name'] ?: '(guest)') ?><br><small>#<?= (int) $r['user_id'] ?></small></td>
        <td><span class="badge <?= $badgeFor[$r['action']] ?? '' ?>"><?= sanitize($r['action']) ?></span></td>
        <td class="td-truncate" title="<?= sanitize($r['details']) ?>"><?= sanitize($r['details'] ?: '') ?></td>
        <td><small><?= sanitize($r['ip_address'] ?: '') ?></small></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
      <tr><td colspan="5" class="empty-hint">No activity matches this filter.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<?php if ($pages > 1): ?>
  <nav class="pagination-nav">
    <?php
    $qs = http_build_query(array_filter([
        'action' => $action !== '' && $action !== 'all' ? $action : null,
        'q' => $search !== '' ? $search : null,
        'days' => $days > 0 ? $days : null,
    ]));
    $qs = $qs ? $qs . '&' : '';
    for ($i = 1; $i <= $pages; $i++): ?>
      <a class="btn btn-secondary btn-sm <?= $i === $page ? 'active' : '' ?>" href="?<?= $qs ?>page=<?= $i ?>"><?= $i ?></a>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>