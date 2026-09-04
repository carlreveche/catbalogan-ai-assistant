<?php
require_once __DIR__ . '/includes/header.php';

// Dashboard metrics use a consistent 30-day comparison window.
$selPeriod = 30;
$days      = 30;
$from     = date('Y-m-d H:i:s', strtotime("-$days days"));
$prevFrom = date('Y-m-d H:i:s', strtotime('-' . ($days * 2) . ' days'));
$periodTxt = 'vs prev ' . $selPeriod . 'd';

// --- Tiny scalar/row helpers (all prepared statements) ---
$dashInt = function (string $sql, array $params = []) use ($pdo): int {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return (int) $st->fetchColumn();
};
$dashRows = function (string $sql, array $params = []) use ($pdo): array {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
};

// --- Stat cards: current period vs previous period of equal length ---
$totalUsers      = $dashInt('SELECT COUNT(*) FROM users');
$totalConversations = $dashInt('SELECT COUNT(DISTINCT conversation_id) FROM chats');
$totalMessages   = $dashInt('SELECT COUNT(*) FROM chats');

$curUsers    = $dashInt('SELECT COUNT(*) FROM users WHERE created_at >= ?', [$from]);
$curConvos   = $dashInt('SELECT COUNT(DISTINCT conversation_id) FROM chats WHERE created_at >= ?', [$from]);
$curMessages = $dashInt('SELECT COUNT(*) FROM chats WHERE created_at >= ?', [$from]);

$fbCur = $dashRows('SELECT COUNT(*) AS total, SUM(is_helpful = 1) AS helpful FROM chat_feedback WHERE created_at >= ?', [$from]);
$fbPrev = $prevFrom !== null
    ? $dashRows('SELECT COUNT(*) AS total, SUM(is_helpful = 1) AS helpful FROM chat_feedback WHERE created_at >= ? AND created_at < ?', [$prevFrom, $from])
    : null;
$ratedCur   = (int) $fbCur[0]['total'];
$helpfulCur = (int) $fbCur[0]['helpful'];
$ratedPrev   = $fbPrev !== null ? (int) $fbPrev[0]['total'] : 0;
$helpfulPrev = $fbPrev !== null ? (int) $fbPrev[0]['helpful'] : 0;
$helpfulRate = $ratedCur > 0 ? round($helpfulCur / $ratedCur * 100) : 0;
$helpfulPrevRate = $ratedPrev > 0 ? round($helpfulPrev / $ratedPrev * 100) : 0;

$prevUsers    = $prevFrom !== null ? $dashInt('SELECT COUNT(*) FROM users WHERE created_at >= ? AND created_at < ?', [$prevFrom, $from]) : null;
$prevConvos   = $prevFrom !== null ? $dashInt('SELECT COUNT(DISTINCT conversation_id) FROM chats WHERE created_at >= ? AND created_at < ?', [$prevFrom, $from]) : null;
$prevMessages = $prevFrom !== null ? $dashInt('SELECT COUNT(*) FROM chats WHERE created_at >= ? AND created_at < ?', [$prevFrom, $from]) : null;

// --- Delta badge helper: returns [class, text] ---
$delta = function ($cur, $prev, $suffix = '%') use ($periodTxt): array {
    if ($prev === null || $prev <= 0) return ['flat', 'no prior data'];
    $diff = $cur - $prev;
    $pct  = round(abs($diff) / $prev * 100);
    $cls  = $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'flat');
    $txt  = $diff > 0 ? '+' : ($diff < 0 ? '-' : '');
    $txt .= $pct . $suffix . ($periodTxt !== '' ? ' ' . $periodTxt : '');
    return [$cls, $txt];
};
$deltaPts = function ($cur, $prev) use ($periodTxt): array {
    if ($prev === null) return ['flat', 'no prior data'];
    $diff = round($cur - $prev);
    $cls  = $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'flat');
    $txt  = ($diff > 0 ? '+' : ($diff < 0 ? '-' : '')) . $diff . ' pts' . ($periodTxt !== '' ? ' ' . $periodTxt : '');
    return [$cls, $txt];
};
$uDelta = $delta($curUsers, $prevUsers);
$cDelta = $delta($curConvos, $prevConvos);
$mDelta = $delta($curMessages, $prevMessages);
$hDelta = $deltaPts($helpfulRate, $helpfulPrevRate);

// --- Most asked topics (with share %) ---
$topTopics = $dashRows("
    SELECT COALESCE(p.name, c.matched_topic, '(general / no match)') AS topic, COUNT(*) AS cnt
    FROM chats c
    LEFT JOIN permits p ON p.code = c.matched_topic
    WHERE c.role = 'assistant'
    GROUP BY topic
    ORDER BY cnt DESC
    LIMIT 8
");
$maxTopic = 0;
$topicTotal = 0;
foreach ($topTopics as $t) {
    $maxTopic = max($maxTopic, (int) $t['cnt']);
    $topicTotal += (int) $t['cnt'];
}

// --- Citizen engagement: new vs returning ---
$engagedReturning = $dashInt('SELECT COUNT(DISTINCT c.user_id) FROM chats c JOIN users u ON u.id = c.user_id WHERE c.created_at >= ? AND u.created_at < ?', [$from, $from]);
$engagedTotal = $curUsers + $engagedReturning;
$returningPct = $engagedTotal > 0 ? round($engagedReturning / $engagedTotal * 100) : 0;

// --- Recent activity ---
$recentActivity = $dashRows("
    SELECT c.message, c.matched_topic, c.created_at, u.name AS user_name
    FROM chats c
    JOIN users u ON u.id = c.user_id
    ORDER BY c.id DESC
    LIMIT 6
");

$chartData = [
    'engagement' => ['returning' => $engagedReturning, 'new' => $curUsers],
];
?>
<div class="page-head">
  <div>
    <h1>Dashboard</h1>
    <p class="admin-sub">Overview of citizen engagement &amp; assistant performance</p>
  </div>
  <div class="quick-actions">
    <a href="kb.php" class="qa-btn qa-btn-primary">
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
      New KB Entry
    </a>
    <a href="permits.php" class="qa-btn">
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
      Add Permit
    </a>
    <a href="feedback.php" class="qa-btn">
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
      Review Feedback
    </a>
  </div>
</div>

<div class="stat-grid dashboard-stats">
  <div class="stat-card stat-teal">
    <div class="stat-icon"><svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
    <span class="stat-value"><?= number_format($totalUsers) ?></span>
    <span class="stat-label">Registered Citizens</span>
    <span class="delta delta-<?= $uDelta[0] ?>"><?= $uDelta[1] ?></span>
  </div>
  <div class="stat-card stat-orange">
    <div class="stat-icon"><svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
    <span class="stat-value"><?= number_format($totalConversations) ?></span>
    <span class="stat-label">Conversations</span>
    <span class="delta delta-<?= $cDelta[0] ?>"><?= $cDelta[1] ?></span>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg></div>
    <span class="stat-value"><?= number_format($totalMessages) ?></span>
    <span class="stat-label">Messages Exchanged</span>
    <span class="delta delta-<?= $mDelta[0] ?>"><?= $mDelta[1] ?></span>
  </div>
  <div class="stat-card stat-red">
    <div class="stat-icon"><svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg></div>
    <span class="stat-value"><?= $helpfulRate ?>%</span>
    <span class="stat-label">Helpful Rating</span>
    <span class="delta delta-<?= $hDelta[0] ?>"><?= $hDelta[1] ?></span>
  </div>
</div>

<div class="admin-cols dashboard-panel-row dashboard-top-row">
  <section class="panel">
    <h2>Most Asked Topics</h2>
    <?php if (empty($topTopics)): ?>
      <p class="empty-hint">No assistant answers yet.</p>
    <?php else: ?>
      <?php foreach ($topTopics as $t): ?>
        <div class="topic-row">
          <span class="topic-name"><?= sanitize($t['topic']) ?></span>
          <span class="topic-count"><?= (int) $t['cnt'] ?></span>
          <div class="topic-bar"><span style="width:<?= $maxTopic > 0 ? (int) round((int) $t['cnt'] / $maxTopic * 100) : 0 ?>%"></span></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <section class="panel">
    <h2>Citizen Engagement</h2>
    <p class="panel-note">New vs returning citizens, <?= $selPeriod > 0 ? 'last ' . $selPeriod . ' days' : 'all time' ?></p>
    <div class="donut-wrap">
      <div class="donut-box">
        <canvas id="engageChart" aria-label="Citizen engagement chart"></canvas>
      </div>
      <div class="donut-legend">
        <div class="donut-row">
          <span class="donut-swatch" style="background: var(--teal)"></span>
          <span class="donut-label">Returning citizens</span>
          <b><?= number_format($engagedReturning) ?> <small><?= $engagedTotal > 0 ? round($engagedReturning / $engagedTotal * 100) : 0 ?>%</small></b>
        </div>
        <div class="donut-row">
          <span class="donut-swatch" style="background: var(--accent-border)"></span>
          <span class="donut-label">New this period</span>
          <b><?= number_format($curUsers) ?> <small><?= $engagedTotal > 0 ? round($curUsers / $engagedTotal * 100) : 0 ?>%</small></b>
        </div>
      </div>
    </div>
  </section>
</div>

<div class="admin-cols dashboard-panel-row">
  <section class="panel">
    <h2>Recent Activity</h2>
    <?php if (empty($recentActivity)): ?>
      <p class="empty-hint">No activity yet.</p>
    <?php else: ?>
      <ul class="activity-list">
        <?php foreach ($recentActivity as $a): ?>
          <li>
            <strong><?= sanitize($a['user_name']) ?></strong>
            <span><?= sanitize(mb_substr($a['message'], 0, 60)) ?></span>
            <?php if (!empty($a['matched_topic'])): ?><span class="activity-tag"><?= sanitize($a['matched_topic']) ?></span><?php endif; ?>
            <small><?= date('M j, g:i A', strtotime($a['created_at'])) ?></small>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

</div>

<script src="../assets/vendor/chartjs/chart.umd.min.js"></script>
<script>window.DASHBOARD_DATA = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;</script>
<script src="../assets/js/dashboard.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>