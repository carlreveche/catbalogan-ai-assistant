<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$userId = current_user_id();

// Load this user's past conversations (grouped, with auto-titles)
$convStmt = $pdo->prepare("
    SELECT c.conversation_id,
           COALESCE(
             MAX(CASE WHEN c.title IS NOT NULL THEN c.title END),
             SUBSTRING(MAX(CASE WHEN c.role='user' THEN c.message END), 1, 40),
             'New conversation'
           ) AS title,
           MIN(c.created_at) AS started_at
    FROM chats c
    WHERE c.user_id = ?
    GROUP BY c.conversation_id
    ORDER BY started_at DESC
    LIMIT 100
");
$convStmt->execute([$userId]);
$conversations = $convStmt->fetchAll();

// Load permit list for quick-topic buttons
$permits = $pdo->query("SELECT id, code, name FROM permits ORDER BY id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<title>Catbalogan AI Assistant - Chat</title>
<link rel="icon" type="image/png" href="assets/images/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Public+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="chat-page" data-user-name="<?= sanitize(current_user_name()) ?>">

<div class="app-shell">

  <button id="sidebarToggle" class="sidebar-toggle" aria-label="Open menu">&#9776;</button>
  <div id="sidebarOverlay" class="sidebar-overlay"></div>

  <aside id="sidebar" class="sidebar">
    <div class="brand">
      <img class="brand-badge" src="assets/images/logo.jpg" alt="Catbalogan City logo">
      <div>
        <h1>Catbalogan City</h1>
        <p>AI Virtual Assistant</p>
      </div>
    </div>

    <div class="sidebar-user">
      <?php if (current_user_avatar() !== ''): ?>
        <img class="avatar" src="<?= sanitize(current_user_avatar()) ?>" alt="">
      <?php endif; ?>
      <span>Signed in as</span>
      <strong><?= sanitize(current_user_name()) ?></strong>
      <div class="sidebar-user-links">
        <?php if (is_admin()): ?>
          <a href="admin/index.php" class="admin-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Admin Panel
          </a>
        <?php endif; ?>
        <a href="logout.php" class="logout-btn">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
          Log out
        </a>
      </div>
    </div>

    <button id="newChatBtn" class="btn btn-primary w-100">+ New Conversation</button>

    <div class="quick-topics">
      <h3>Quick Topics</h3>
      <?php foreach ($permits as $p): ?>
        <button class="topic-btn" data-topic="<?= sanitize($p['name']) ?>">
          <?= sanitize($p['name']) ?>
        </button>
      <?php endforeach; ?>
    </div>

    <div class="history-list">
      <h3>Your Conversations</h3>
      <input type="text" id="historySearch" class="history-search" placeholder="Search conversations..." autocomplete="off">
      <div id="historyItems">
        <?php if (empty($conversations)): ?>
          <p class="empty-hint" id="historyEmpty">No conversations yet.</p>
        <?php endif; ?>
        <?php foreach ($conversations as $c): ?>
          <div class="history-item-row" data-title="<?= sanitize($c['title']) ?>">
            <button class="history-item" data-conv="<?= sanitize($c['conversation_id']) ?>" title="<?= sanitize($c['title']) ?>">
              <?= sanitize($c['title']) ?>
            </button>
            <div class="dropdown history-item-menu-wrap">
              <button class="history-item-menu" data-bs-toggle="dropdown" data-conv="<?= sanitize($c['conversation_id']) ?>" aria-expanded="false" aria-label="Conversation options">&#8942;</button>
              <div class="dropdown-menu">
                <h6 class="dropdown-header">Conversation options</h6>
                <button type="button" class="dropdown-item" data-action="rename" data-conv="<?= sanitize($c['conversation_id']) ?>">Rename</button>
                <button type="button" class="dropdown-item dropdown-item-danger" data-action="delete" data-conv="<?= sanitize($c['conversation_id']) ?>">Delete</button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </aside>

  <main class="chat-main">
    <header class="chat-header">
      <h2>Ask about Municipal Permits &amp; Clearances</h2>
      <p>Barangay Clearance &middot; New Business Permit &middot; Business Permit Renewal &middot; Cedula &middot; Police Clearance &middot; Building Permit &middot; Civil Registry</p>
    </header>

    <div id="chatWindow" class="chat-window">
      <div class="msg msg-assistant" data-welcome="1">
        <div class="bubble">
          Hi <?= sanitize(current_user_name()) ?>! I'm the Catbalogan City Virtual Assistant.
          I can help with permits and clearances like the <strong>Barangay Clearance</strong>,
          <strong>New Business Permit</strong>, <strong>Business Permit Renewal</strong>,
          <strong>Cedula</strong>, <strong>Police Clearance</strong>, <strong>Building Permit</strong>,
          and <strong>Civil Registry</strong> requests. Ask me about requirements, steps, fees,
          or processing time &mdash; or tap a quick topic on the left.
          <div class="msg-time"><?= date('g:i A') ?></div>
        </div>
      </div>
    </div>

    <form id="chatForm" class="chat-input-row">
      <input type="text" id="chatInput" class="form-control" placeholder="Type your question here..." autocomplete="off" required>
      <button type="submit" class="btn btn-primary">Send</button>
    </form>
  </main>

</div>

<script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="assets/js/chat.js"></script>

<div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3" aria-live="polite" aria-atomic="true"></div>

<div id="renameModal" class="modal fade" tabindex="-1" aria-labelledby="renameModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="renameModalTitle">Rename Conversation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="modal-hint mb-2">Give this conversation a name you will recognize later.</p>
        <input type="text" id="renameInput" class="form-control" maxlength="120" autocomplete="off">
      </div>
      <div class="modal-footer">
        <button type="button" id="renameCancel" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="renameSave" class="btn btn-primary">Save</button>
      </div>
    </div>
  </div>
</div>

<div id="deleteModal" class="modal fade" tabindex="-1" aria-labelledby="deleteModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteModalTitle">Delete Conversation?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="modal-hint mb-0">This conversation and all its messages will be permanently removed. This cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" id="deleteCancel" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="deleteConfirm" class="btn btn-danger">Delete</button>
      </div>
    </div>
  </div>
</div>
</body>
</html>