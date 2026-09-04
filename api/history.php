<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in.']);
    exit;
}
require_active();

$conversationId = trim($_GET['conversation_id'] ?? '');
if ($conversationId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'conversation_id is required.']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT c.id, c.role, c.message, c.matched_topic, c.created_at,
           p.name AS topic_name,
           f.is_helpful
    FROM chats c
    LEFT JOIN permits p ON p.code = c.matched_topic
    LEFT JOIN chat_feedback f ON f.chat_id = c.id
    WHERE c.user_id = ? AND c.conversation_id = ?
    ORDER BY c.id ASC
");
$stmt->execute([current_user_id(), $conversationId]);

$messages = array_map(static function ($m) {
    $m['is_helpful'] = $m['is_helpful'] === null ? null : (bool) $m['is_helpful'];
    return $m;
}, $stmt->fetchAll());

echo json_encode(['messages' => $messages]);