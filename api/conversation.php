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

require_csrf();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$conversationId = trim($input['conversation_id'] ?? '');
$userId = current_user_id();

if ($conversationId === '' || !in_array($action, ['rename', 'delete'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'action and conversation_id are required.']);
    exit;
}

// Verify ownership
$own = $pdo->prepare('SELECT 1 FROM chats WHERE user_id = ? AND conversation_id = ? LIMIT 1');
$own->execute([$userId, $conversationId]);
if (!$own->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Conversation not found.']);
    exit;
}

if ($action === 'delete') {
    $pdo->prepare('DELETE FROM chats WHERE user_id = ? AND conversation_id = ?')
        ->execute([$userId, $conversationId]);
    echo json_encode(['ok' => true, 'conversation_id' => $conversationId]);
    exit;
}

// rename
$title = trim($input['title'] ?? '');
if ($title === '' || mb_strlen($title) > 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Title must be between 1 and 100 characters.']);
    exit;
}

$pdo->prepare('UPDATE chats SET title = ? WHERE user_id = ? AND conversation_id = ?')
    ->execute([$title, $userId, $conversationId]);

echo json_encode(['ok' => true, 'title' => $title]);