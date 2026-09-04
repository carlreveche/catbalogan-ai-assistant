<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/google_auth.php';

// Already logged in? No need for Google.
if (is_logged_in()) {
    header('Location: ' . (is_admin() ? '../admin/index.php' : '../chat.php'));
    exit;
}

if (!google_auth_enabled()) {
    $_SESSION['auth_error'] = 'Google login is not configured yet. Fill in the Client ID and Secret in config/google_auth.php, or log in with email and password.';
    header('Location: ../login.php');
    exit;
}

// State = CSRF protection: Google returns it back to us in the callback.
$_SESSION['google_state'] = bin2hex(random_bytes(16));

$params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $_SESSION['google_state'],
    'prompt'        => 'select_account',
    'access_type'   => 'online',
];

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
exit;