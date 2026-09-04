<?php
require_once __DIR__ . '/includes/header.php';

// --- Date range (shared with export.php via report_range()) ---
[$from, $to] = report_range();
$fromSql = $from !== null ? $from . ' 00:00:00' : null;
$toSql   = $to !== null ? $to . ' 23:59:59' : null;
$params  = $fromSql !== null ? [$fromSql, $toSql] : [];

$wherePlain  = $fromSql !== null ? " WHERE created_at BETWEEN ? AND ?" : '';
$whereTopics = $fromSql !== null ? " WHERE c.created_at BETWEEN ? AND ?" : '';
$whereUnans  = $fromSql !== null ? " WHERE f.created_at BETWEEN ? AND ?" : '';
$whereFb     = $fromSql !== null ? " WHERE c.created_at BETWEEN ? AND ?" : '';
$andPlain    = $fromSql !== null ? " AND created_at BETWEEN ? AND ?" : '';
$andTopics   = $fromSql !== null ? " AND c.created_at BETWEEN ? AND ?" : '';
$andUnans    = $fromSql !== null ? " AND f.created_at BETWEEN ? AND ?" : '';

// --- Overview cards ---
$st = $pdo->prepare("SELECT COUNT(*) FROM users" . $wherePlain);
$st->execute($params);
$statUsers = (int) $st->fetchColumn();

$st = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM chats WHERE role = 'user'" . $andPlain);
$st->execute($params);
$statActive = (int) $st->fetchColumn();

$st = $pdo->prepare("SELECT COUNT(DISTINCT conversation_id) FROM chats" . $wherePlain);
$st->execute($params);
$statConvos = (int) $st->fetchColumn();

$st = $pdo->prepare("SELECT COUNT(*) FROM chats" . $wherePlain);
$st->execute($params);
$statMessages = (int) $st->fetchColumn();

$st = $pdo->prepare("SELECT COUNT(*) FROM chats WHERE role = 'assistant' AND (matched_topic IS NULL OR matched_topic = '')" . $andPlain);
$st->execute($params);
$statUnanswered = (int) $st->fetchColumn();

$st = $pdo->prepare("
    SELECT COUNT(*) AS total, SUM(f.is_helpful = 1) AS helpful
    FROM chat_feedback f JOIN chats c ON c.id = f.chat_id" . $whereFb
);
$st->execute($params);
$fb = $st->fetch();
$statRated = (int) $fb['total'];
$statHelpful = (int) $fb['helpful'];
$helpfulRate = $statRated > 0 ? round($statHelpful / $statRated * 100) : 0;
$unansweredRate = $statMessages > 0 ? round($statUnanswered / $statMessages * 100) : 0;

// --- Most asked topics + answer quality (also feeds the chart) ---
$st = $pdo->prepare("
    SELECT COALESCE(p.name, c.matched_topic, '(no topic)') AS topic,
           COUNT(*) AS questions,
           SUM(f.is_helpful = 1) AS helpful,
           SUM(f.is_helpful = 0) AS unhelpful
    FROM chats c
    LEFT JOIN permits p ON p.code = c.matched_topic
    LEFT JOIN chat_feedback f ON f.chat_id = c.id
    WHERE c.role = 'assistant'" . $andTopics . "
    GROUP BY topic
    ORDER BY questions DESC
    LIMIT 15
");
$st->execute($params);
$topTopics = $st->fetchAll();
$questionsTotal = 0;
foreach ($topTopics as $t) {
    $questionsTotal += (int) $t['questions'];
}

$chartData = [
    'labels' => array_slice(array_map(fn($t) => $t['topic'], $topTopics), 0, 10),
    'values' => array_slice(array_map(fn($t) => (int) $t['questions'], $topTopics), 0, 10),
];

// --- Unanswered / no-topic questions (knowledge base gaps) ---
$st = $pdo->prepare("
    SELECT f.id, f.created_at, u.name AS user_name,
           (SELECT c2.message FROM chats c2
             WHERE c2.user_id = f.user_id AND c2.conversation_id = f.conversation_id
               AND c2.id < f.id AND c2.role = 'user'
             ORDER BY c2.id DESC LIMIT 1) AS user_question
    FROM chats f
    JOIN users u ON u.id = f.user_id
    WHERE f.role = 'assistant' AND (f.matched_topic IS NULL OR f.matched_topic = '')" . $andUnans . "
    ORDER BY f.id DESC LIMIT 25
");
$st->execute($params);
$unansweredRows = $st->fetchAll();

// --- Content freshness (current state, no period) ---
$stalePermits = $pdo->query("
    SELECT id, name, office, verified_at
    FROM permits
    WHERE verified_at IS NULL OR verified_at < DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    ORDER BY verified_at ASC
")->fetchAll();

$periodLabel = $from !== null ? date('M j, Y', strtotime($from)) . ' &ndash; ' . date('M j, Y', strtotime($to)) : 'all time';
$selPeriod = (int) ($_GET['period'] ?? 0);
$customSelected = !in_array($selPeriod, [7, 30, 90, 365], true);
?>
<h1>Reports</h1>

<div class="panel report-filter">
  <form method="GET" class="inline-filter" id="reportForm">
    <label>Period:
      <select name="period" id="reportPeriod" class="form-select form-select-sm">
        <option value="7" <?= $selPeriod === 7 ? 'selected' : '' ?>>Last 7 days</option>
        <option value="30" <?= $selPeriod === 30 ? 'selected' : '' ?>>Last 30 days</option>
        <option value="90" <?= $selPeriod === 90 ? 'selected' : '' ?>>Last 90 days</option>
        <option value="365" <?= $selPeriod === 365 ? 'selected' : '' ?>>Last 12 months</option>
        <option value="0" <?= $customSelected ? 'selected' : '' ?>>Custom range</option>
      </select>
    </label>
    <label>From:
      <input type="date" name="from" id="reportFrom" class="form-control form-control-sm" value="<?= sanitize($from ?? '') ?>">
    </label>
    <label>To:
      <input type="date" name="to" id="reportTo" class="form-control form-control-sm" value="<?= sanitize($to ?? '') ?>">
    </label>
    <button type="submit" class="btn btn-secondary btn-sm">Apply</button>
    <a href="reports.php" class="btn btn-secondary btn-sm">Reset</a>
  </form>
  <p class="report-range-note">Showing <?= $periodLabel ?></p>
</div>

<div class="stat-grid">
  <div class="stat-card"><span class="stat-value"><?= $statUsers ?></span><span class="stat-label">New Registrations</span></div>
  <div class="stat-card"><span class="stat-value"><?= $statActive ?></span><span class="stat-label">Active Citizens</span></div>
  <div class="stat-card"><span class="stat-value"><?= $statConvos ?></span><span class="stat-label">Conversations</span></div>
  <div class="stat-card"><span class="stat-value"><?= $statMessages ?></span><span class="stat-label">Messages Exchanged</span></div>
  <div class="stat-card"><span class="stat-value"><?= $unansweredRate ?>%</span><span class="stat-label">Unanswered Rate</span></div>
  <div class="stat-card"><span class="stat-value"><?= $helpfulRate ?>%</span><span class="stat-label">Helpful Rating (<?= $statRated ?> rated)</span></div>
</div>

<section class="panel report-panel">
  <h2>Most Asked Topics</h2>
  <p class="report-note">Which permits citizens asked about in this period.</p>
  <div class="chart-box"><canvas id="topicChart"></canvas></div>
</section>

<section class="panel report-panel">
  <h2>Most Asked Topics &amp; Answer Quality</h2>
  <p class="report-note">Questions per permit, and how often the answers were rated helpful.</p>
  <table class="table admin-table">
    <thead>
      <tr><th>Topic</th><th>Questions</th><th>% of Questions</th><th>Helpful</th><th>Unhelpful</th><th>Helpful Rate</th></tr>
    </thead>
    <tbody>
      <?php foreach ($topTopics as $t): ?>
        <?php
        $q = (int) $t['questions'];
        $h = (int) $t['helpful'];
        $u = (int) $t['unhelpful'];
        $rate = ($h + $u) > 0 ? round($h / ($h + $u) * 100) : '&ndash;';
        ?>
        <tr>
          <td><?= sanitize($t['topic']) ?></td>
          <td><?= $q ?></td>
          <td><?= $questionsTotal > 0 ? round($q / $questionsTotal * 100) : 0 ?>%</td>
          <td><span class="badge badge-ok"><?= $h ?></span></td>
          <td><span class="badge badge-bad"><?= $u ?></span></td>
          <td><?= $rate ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($topTopics)): ?>
        <tr><td colspan="6" class="empty-hint">No assistant answers in this period.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

<section class="panel report-panel">
  <h2>Unanswered Questions (Knowledge Base Gaps)</h2>
  <p class="report-note">Questions the assistant could not match to any permit &mdash; add knowledge base entries for these.</p>
  <table class="table admin-table">
    <thead>
      <tr><th>When</th><th>Citizen</th><th>Question Asked</th></tr>
    </thead>
    <tbody>
      <?php foreach ($unansweredRows as $r): ?>
        <tr>
          <td><small><?= date('M j, Y g:i A', strtotime($r['created_at'])) ?></small></td>
          <td><?= sanitize($r['user_name'] ?: '(unknown)') ?></td>
          <td class="td-truncate" title="<?= sanitize($r['user_question'] ?? '') ?>"><?= sanitize(mb_substr($r['user_question'] ?? '', 0, 120)) ?>&hellip;</td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($unansweredRows)): ?>
        <tr><td colspan="3" class="empty-hint">No unanswered questions in this period.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

<section class="panel report-panel">
  <h2>Permits Needing Re-Verification</h2>
  <p class="report-note">Permits not verified with the issuing office in the last 6 months. Update in <a href="permits.php">Permits</a>.</p>
  <table class="table admin-table">
    <thead>
      <tr><th>Permit</th><th>Office</th><th>Last Verified</th><th>Days Since Verification</th></tr>
    </thead>
    <tbody>
      <?php foreach ($stalePermits as $p): ?>
        <tr>
          <td><?= sanitize($p['name']) ?></td>
          <td><?= sanitize($p['office']) ?></td>
          <td><?= $p['verified_at'] ? date('M j, Y', strtotime($p['verified_at'])) : '<span class="badge badge-bad">Never</span>' ?></td>
          <td><?= $p['verified_at'] ? (int) floor((time() - strtotime($p['verified_at'])) / 86400) : '&ndash;' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($stalePermits)): ?>
        <tr><td colspan="4" class="empty-hint">All permits are up to date.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

<script src="../assets/vendor/chartjs/chart.umd.min.js"></script>
<script>window.REPORTS_DATA = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;</script>
<script src="../assets/js/reports.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>