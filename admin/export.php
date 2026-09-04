<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$section = $_GET['section'] ?? 'topics';
$allowed = ['usage', 'topics', 'unanswered', 'freshness'];
if (!in_array($section, $allowed, true)) {
    http_response_code(400);
    exit('Unknown report section.');
}

[$from, $to] = report_range();
$fromSql = $from !== null ? $from . ' 00:00:00' : null;
$toSql   = $to !== null ? $to . ' 23:59:59' : null;
$params  = $fromSql !== null ? [$fromSql, $toSql] : [];

$wherePlain  = $fromSql !== null ? " WHERE created_at BETWEEN ? AND ?" : '';
$whereTopics = $fromSql !== null ? " WHERE c.created_at BETWEEN ? AND ?" : '';
$whereUnans  = $fromSql !== null ? " WHERE f.created_at BETWEEN ? AND ?" : '';
$andTopics   = $fromSql !== null ? " AND c.created_at BETWEEN ? AND ?" : '';
$andUnans    = $fromSql !== null ? " AND f.created_at BETWEEN ? AND ?" : '';
$periodTag = $from !== null ? $from . '_to_' . $to : 'all';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="catbalogan_' . $section . '_report_' . $periodTag . '.csv"');

$out = fopen('php://output', 'w');

switch ($section) {
    case 'usage':
        fputcsv($out, ['Date', 'New Registrations', 'Messages Exchanged', 'Conversations']);
        $st = $pdo->prepare("
            SELECT DATE(c.created_at) AS d,
                   COUNT(*) AS messages,
                   COUNT(DISTINCT c.conversation_id) AS convs
            FROM chats c" . $wherePlain . "
            GROUP BY d ORDER BY d
        ");
        $st->execute($params);
        $rows = $st->fetchAll();
        $st2 = $pdo->prepare("SELECT DATE(created_at) AS d, COUNT(*) AS cnt FROM users" . $wherePlain . " GROUP BY d");
        $st2->execute($params);
        $userMap = [];
        foreach ($st2->fetchAll() as $r) {
            $userMap[$r['d']] = (int) $r['cnt'];
        }
        foreach ($rows as $r) {
            fputcsv($out, [$r['d'], $userMap[$r['d']] ?? 0, (int) $r['messages'], (int) $r['convs']]);
        }
        break;

    case 'topics':
        fputcsv($out, ['Topic', 'Questions', '% of Questions', 'Helpful', 'Unhelpful', 'Helpful Rate']);
        $st = $pdo->prepare("
            SELECT COALESCE(p.name, c.matched_topic, '(no topic)') AS topic,
                   COUNT(*) AS questions,
                   SUM(f.is_helpful = 1) AS helpful,
                   SUM(f.is_helpful = 0) AS unhelpful
            FROM chats c
            LEFT JOIN permits p ON p.code = c.matched_topic
            LEFT JOIN chat_feedback f ON f.chat_id = c.id
            WHERE c.role = 'assistant'" . $andTopics . "
            GROUP BY topic ORDER BY questions DESC
        ");
        $st->execute($params);
        $rows = $st->fetchAll();
        $total = 0;
        foreach ($rows as $r) {
            $total += (int) $r['questions'];
        }
        foreach ($rows as $r) {
            $h = (int) $r['helpful'];
            $u = (int) $r['unhelpful'];
            $rate = ($h + $u) > 0 ? round($h / ($h + $u) * 100) : '';
            fputcsv($out, [$r['topic'], (int) $r['questions'], $total > 0 ? round((int) $r['questions'] / $total * 100) : 0, $h, $u, $rate]);
        }
        break;

    case 'unanswered':
        fputcsv($out, ['Date', 'Citizen', 'Question Asked']);
        $st = $pdo->prepare("
            SELECT f.created_at, u.name AS user_name,
                   (SELECT c2.message FROM chats c2
                     WHERE c2.user_id = f.user_id AND c2.conversation_id = f.conversation_id
                       AND c2.id < f.id AND c2.role = 'user'
                     ORDER BY c2.id DESC LIMIT 1) AS user_question
            FROM chats f
            JOIN users u ON u.id = f.user_id
            WHERE f.role = 'assistant' AND (f.matched_topic IS NULL OR f.matched_topic = '')" . $andUnans . "
            ORDER BY f.id DESC
        ");
        $st->execute($params);
        foreach ($st->fetchAll() as $r) {
            fputcsv($out, [$r['created_at'], $r['user_name'] ?: '(unknown)', $r['user_question'] ?? '']);
        }
        break;

    case 'freshness':
        fputcsv($out, ['Permit', 'Office', 'Last Verified', 'Days Since Verification']);
        $rows = $pdo->query("
            SELECT name, office, verified_at FROM permits
            WHERE verified_at IS NULL OR verified_at < DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            ORDER BY verified_at ASC
        ")->fetchAll();
        foreach ($rows as $r) {
            fputcsv($out, [$r['name'], $r['office'], $r['verified_at'] ?: 'never', $r['verified_at'] ? (int) floor((time() - strtotime($r['verified_at'])) / 86400) : '']);
        }
        break;
}

fclose($out);