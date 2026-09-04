<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/google_auth.php';

if (!google_auth_enabled()) {
    $_SESSION['auth_error'] = 'Google login is not configured. Please set up config/google_auth.php.';
    header('Location: ../login.php');
    exit;
}

// --- CSRF: verify the state token ---
$state = $_GET['state'] ?? '';
if ($state === '' || !hash_equals($_SESSION['google_state'] ?? '', $state)) {
    $_SESSION['auth_error'] = 'Login state mismatch. Please try again.';
    header('Location: ../login.php');
    exit;
}
unset($_SESSION['google_state']);

// --- User cancelled / Google returned an error ---
if (isset($_GET['error'])) {
    $_SESSION['auth_error'] = 'Google sign-in was cancelled or failed. Please try again.';
    header('Location: ../login.php');
    exit;
}

// --- Exchange the authorization code ---
$code = $_GET['code'] ?? '';
if ($code === '') {
    $_SESSION['auth_error'] = 'No authorization code received from Google.';
    header('Location: ../login.php');
    exit;
}

$token = google_exchange_code_for_token($code);
if (!$token || empty($token['id_token'])) {
    $_SESSION['auth_error'] = 'Could not complete Google sign-in. Please try again.';
    header('Location: ../login.php');
    exit;
}

// --- Verify the id_token (audience, expiry, issuer) ---
$profile = google_validate_id_token($token['id_token']);
if (!$profile) {
    $_SESSION['auth_error'] = 'Google sign-in could not be verified. Please try again.';
    header('Location: ../login.php');
    exit;
}

if (!$profile['email_verified']) {
    $_SESSION['auth_error'] = 'Please use a Google account with a verified email address.';
    header('Location: ../login.php');
    exit;
}

// --- Find, link, or create the account, then log in ---
$result = login_or_register_with_google($pdo, $profile);
$user = $result['user'];

// Suspended accounts cannot sign in
$st = $pdo->prepare('SELECT status FROM users WHERE id = ?');
$st->execute([$user['id']]);
if ($st->fetchColumn() === 'suspended') {
    $_SESSION['auth_error'] = 'This account has been suspended. Please contact the administrator.';
    header('Location: ../login.php');
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id']     = (int) $user['id'];
$_SESSION['user_name']   = $user['name'];
$_SESSION['user_role']   = $user['role'];
$_SESSION['user_avatar'] = $user['avatar'] ?? '';

$_SESSION['flash'] = $result['new']
    ? 'Welcome, ' . $user['name'] . '! Your account was created with your Google account.'
    : 'Logged in with Google. Welcome back, ' . $user['name'] . '!';

header('Location: ' . ($user['role'] === 'admin' ? '../admin/index.php' : '../chat.php'));
exit;