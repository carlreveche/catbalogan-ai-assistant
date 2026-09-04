<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/llm_functions.php';

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

// Keyword-matcher answer: topic tagging + offline backup if the LLM fails
$matcher = get_ai_response($pdo, $message, $lastTopicCode);

// Save the citizen's message (first message becomes the conversation title)
$title = $isNewConversation ? mb_substr($message, 0, 60) : null;
$save = $pdo->prepare("
    INSERT INTO chats (user_id, conversation_id, role, message, matched_topic, title)
    VALUES (?, ?, 'user', ?, NULL, ?)
");
$save->execute([$userId, $conversationId, $message, $title]);
$userChatId = (int) $pdo->lastInsertId();

// SSE response
ini_set('zlib.output_compression', 'Off');
ini_set('output_buffering', 'Off');
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
while (ob_get_level() > 0) {
    ob_end_clean();
}

$send = function (array $data): void {
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
};

// Stream the LLM answer token by token
$answer = null;
$streamed = false;
try {
    $answer = llm_stream_answer($pdo, $message, $conversationId, function (string $chunk) use ($send, &$streamed): void {
        $streamed = true;
        $send(['type' => 'token', 'content' => $chunk]);
    }, $userChatId);
} catch (Throwable $e) {
    error_log('LLM stream error: ' . $e->getMessage());
    $answer = null;
}

if ($answer === null || trim($answer) === '') {
    // Offline backup: the keyword-matcher answer
    $answer = $matcher['answer'];
    $topic = $matcher['topic'];
    $topicCode = $matcher['topic_code'];
    $intent = $matcher['intent'];
    $confidence = $matcher['confidence'];
    $suggestions = $matcher['suggestions'];
} else {
    [$topic, $topicCode] = llm_detect_topic($pdo, $message, $lastTopicCode);
    if ($topicCode === null) {
        $topic = $matcher['topic'];
        $topicCode = $matcher['topic_code'];
    }
    $intent = 'ai';
    $confidence = 85;
    $suggestions = $topicCode !== null
        ? ['What are the requirements?', 'How do I apply?', 'How much does it cost?', 'How long does it take?']
        : $matcher['suggestions'];
}

$followUp = $lastTopicCode !== null && $topicCode !== null && $topicCode === $lastTopicCode;

// Only send the full text if it was not already streamed
if (!$streamed) {
    $send(['type' => 'token', 'content' => $answer]);
}

// Save the assistant's reply (matched_topic stores the permit code)
$save2 = $pdo->prepare("
    INSERT INTO chats (user_id, conversation_id, role, message, matched_topic)
    VALUES (?, ?, 'assistant', ?, ?)
");
$save2->execute([$userId, $conversationId, $answer, $topicCode]);
$assistantChatId = (int) $pdo->lastInsertId();

$send([
    'type'     => 'done',
    'metadata' => [
        'conversation_id'   => $conversationId,
        'conversation_title' => $title,
        'user_chat_id'      => $userChatId,
        'assistant_chat_id' => $assistantChatId,
        'answer'            => $answer,
        'topic'             => $topic,
        'topic_code'        => $topicCode,
        'intent'            => $intent,
        'confidence'        => $confidence,
        'follow_up'         => $followUp,
        'suggestions'       => $suggestions,
    ],
]);