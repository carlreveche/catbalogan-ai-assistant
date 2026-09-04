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
$message = trim($input['message'] ?? '');
$conversationId = trim($input['conversation_id'] ?? '');

if ($message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Message cannot be empty.']);
    exit;
}

if (mb_strlen($message) > 1000) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is too long (max 1000 characters).']);
    exit;
}

if ($conversationId === '') {
    $conversationId = bin2hex(random_bytes(8));
}

$userId = current_user_id();

// Does this conversation already exist? (for auto-titling + follow-up context)
$existsStmt = $pdo->prepare('SELECT 1 FROM chats WHERE user_id = ? AND conversation_id = ? LIMIT 1');
$existsStmt->execute([$userId, $conversationId]);
$isNewConversation = !$existsStmt->fetchColumn();

// Last assistant reply's topic code, used for follow-up questions
$lastTopicCode = null;
if (!$isNewConversation) {
    $lastStmt = $pdo->prepare("
        SELECT matched_topic FROM chats
        WHERE user_id = ? AND conversation_id = ? AND role = 'assistant'
        ORDER BY id DESC LIMIT 1
    ");
    $lastStmt->execute([$userId, $conversationId]);
    $lastTopicCode = $lastStmt->fetchColumn() ?: null;
}

// 1. Get the AI (keyword-matcher) response
$result = get_ai_response($pdo, $message, $lastTopicCode);

// 1b. The LLM answers everything; the matcher is the offline backup.
require_once __DIR__ . '/../includes/llm_functions.php';
$llmAnswer = ask_llm($pdo, $message, $conversationId, $lastTopicCode);
if ($llmAnswer !== null) {
    $result = $llmAnswer;
}

// 2. Save the citizen's message (first message becomes the conversation title)
$title = $isNewConversation ? mb_substr($message, 0, 60) : null;
$save = $pdo->prepare("
    INSERT INTO chats (user_id, conversation_id, role, message, matched_topic, title)
    VALUES (?, ?, 'user', ?, NULL, ?)
");
$save->execute([$userId, $conversationId, $message, $title]);
$userChatId = (int) $pdo->lastInsertId();

// 3. Save the assistant's reply (matched_topic stores the permit code)
$save2 = $pdo->prepare("
    INSERT INTO chats (user_id, conversation_id, role, message, matched_topic)
    VALUES (?, ?, 'assistant', ?, ?)
");
$save2->execute([$userId, $conversationId, $result['answer'], $result['topic_code']]);
$assistantChatId = (int) $pdo->lastInsertId();

echo json_encode([
    'conversation_id'  => $conversationId,
    'conversation_title' => $title,
    'user_chat_id'     => $userChatId,
    'assistant_chat_id' => $assistantChatId,
    'answer'           => $result['answer'],
    'topic'            => $result['topic'],
    'topic_code'       => $result['topic_code'],
    'intent'           => $result['intent'],
    'confidence'       => $result['confidence'],
    'follow_up'        => $result['follow_up'],
    'suggestions'      => $result['suggestions'],
]);