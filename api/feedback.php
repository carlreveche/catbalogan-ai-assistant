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
$chatId = (int) ($input['chat_id'] ?? 0);
$isHelpful = isset($input['is_helpful']) ? (int) (bool) $input['is_helpful'] : null;

if ($chatId <= 0 || $isHelpful === null) {
    http_response_code(400);
    echo json_encode(['error' => 'chat_id and is_helpful are required.']);
    exit;
}

// Only the owner of the message may rate it
$ownStmt = $pdo->prepare("
    SELECT id FROM chats
    WHERE id = ? AND user_id = ? AND role = 'assistant'
");
$ownStmt->execute([$chatId, current_user_id()]);
if (!$ownStmt->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['error' => 'You can only rate your own assistant replies.']);
    exit;
}

// Upsert: one rating per message (toggle off if the same value is sent again)
$existing = $pdo->prepare('SELECT is_helpful FROM chat_feedback WHERE chat_id = ?');
$existing->execute([$chatId]);
$current = $existing->fetchColumn();

if ($current === false) {
    $pdo->prepare('INSERT INTO chat_feedback (chat_id, is_helpful) VALUES (?, ?)')
        ->execute([$chatId, $isHelpful]);
    $isHelpful = (bool) $isHelpful;
} elseif ((bool) $current === (bool) $isHelpful) {
    // Same rating again -> remove it (toggle off)
    $pdo->prepare('DELETE FROM chat_feedback WHERE chat_id = ?')->execute([$chatId]);
    $isHelpful = null;
} else {
    $pdo->prepare('UPDATE chat_feedback SET is_helpful = ? WHERE chat_id = ?')
        ->execute([$isHelpful, $chatId]);
    $isHelpful = (bool) $isHelpful;
}

echo json_encode(['is_helpful' => $isHelpful]);