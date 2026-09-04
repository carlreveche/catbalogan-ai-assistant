<?php
require_once __DIR__ . '/includes/header.php';

$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT f.id, f.is_helpful, f.created_at,
               c.message, c.matched_topic, c.user_id,
               u.name AS user_name, u.email
        FROM chat_feedback f
        JOIN chats c ON c.id = f.chat_id
        JOIN users u ON u.id = c.user_id";
$params = [];

if ($filter === 'helpful') {
    $sql .= ' WHERE f.is_helpful = 1';
} elseif ($filter === 'unhelpful') {
    $sql .= ' WHERE f.is_helpful = 0';
}
$sql .= ' ORDER BY f.id DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$counts = $pdo->query("
    SELECT COUNT(*) AS total, SUM(is_helpful = 1) AS helpful, SUM(is_helpful = 0) AS unhelpful
    FROM chat_feedback
")->fetch();
?>
<h1>Answer Feedback</h1>

<div class="stat-grid stat-grid-sm">
  <div class="stat-card"><span class="stat-value"><?= (int) $counts['total'] ?></span><span class="stat-label">Total Ratings</span></div>
  <div class="stat-card"><span class="stat-value"><?= (int) $counts['helpful'] ?></span><span class="stat-label">Helpful</span></div>
  <div class="stat-card"><span class="stat-value"><?= (int) $counts['unhelpful'] ?></span><span class="stat-label">Unhelpful</span></div>
</div>

<div class="inline-filter">
  <a href="?filter=all" class="btn btn-secondary btn-sm <?= $filter === 'all' ? 'active' : '' ?>">All</a>
  <a href="?filter=helpful" class="btn btn-secondary btn-sm <?= $filter === 'helpful' ? 'active' : '' ?>">Helpful</a>
  <a href="?filter=unhelpful" class="btn btn-secondary btn-sm <?= $filter === 'unhelpful' ? 'active' : '' ?>">Unhelpful</a>
</div>

<table class="table admin-table">
  <thead>
    <tr><th>Citizen</th><th>Question (user message)</th><th>Assistant Answer</th><th>Rating</th><th>When</th></tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= sanitize($r['user_name']) ?><br><small><?= sanitize($r['email']) ?></small></td>
        <td class="td-truncate" title="<?= sanitize($r['message']) ?>"><?= sanitize(mb_substr($r['message'], 0, 80)) ?>&hellip;</td>
        <td class="td-truncate" title="<?= sanitize($r['matched_topic'] ?: '') ?>"><?= sanitize($r['matched_topic'] ?: '(no topic)') ?></td>
        <td><?= (int) $r['is_helpful'] ? '<span class="badge badge-ok">Helpful</span>' : '<span class="badge badge-bad">Unhelpful</span>' ?></td>
        <td><small><?= date('M j, Y g:i A', strtotime($r['created_at'])) ?></small></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
      <tr><td colspan="5" class="empty-hint">No feedback yet.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
<?php require_once __DIR__ . '/includes/footer.php'; ?>