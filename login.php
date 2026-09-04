<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/google_auth.php';

if (is_logged_in()) {
    header('Location: ' . (is_admin() ? 'admin/index.php' : 'chat.php'));
    exit;
}

$errors = [];
$email = '';
$authError = $_SESSION['auth_error'] ?? null;
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['auth_error'], $_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $errors[] = 'Please enter both email and password.';
        } else {
            $stmt = $pdo->prepare('SELECT id, name, password_hash, role, status FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                log_activity($pdo, 'login_failed', 'Email: ' . $email);
                $errors[] = 'Invalid email or password.';
            } elseif ($user['status'] === 'suspended') {
                log_activity($pdo, 'login_suspended', 'Email: ' . $email);
                $errors[] = 'This account has been suspended. Please contact the administrator.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_avatar'] = '';
                log_activity($pdo, 'login_success', 'Role: ' . $user['role'], (string) $user['id'], $user['name']);
                header('Location: ' . ($user['role'] === 'admin' ? 'admin/index.php' : 'chat.php'));
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Catbalogan AI Assistant</title>
<link rel="icon" type="image/png" href="assets/images/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Public+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
  <div class="auth-card">
    <div class="brand">
      <img class="brand-badge" src="assets/images/logo.jpg" alt="Catbalogan City logo">
      <div>
        <h1>Catbalogan City</h1>
        <p>AI-Based Virtual Assistant</p>
      </div>
    </div>

    <h2>Welcome back</h2>

    <?php if ($authError): ?>
      <div class="alert alert-danger"><?= sanitize($authError) ?></div>
    <?php endif; ?>
    <?php if ($flash): ?>
      <div class="alert alert-success"><?= sanitize($flash) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?= sanitize($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (google_auth_enabled()): ?>
      <a href="google_auth/login.php" class="google-btn">
        <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
          <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
          <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
          <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
          <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        </svg>
        Continue with Google
      </a>
      <div class="google-divider"><span>or continue with email</span></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <?= csrf_field() ?>
      <label>Email Address
        <input type="email" name="email" class="form-control" value="<?= sanitize($email) ?>" required>
      </label>
      <label>Password
        <input type="password" name="password" class="form-control" required>
      </label>
      <button type="submit" class="btn btn-primary">Log In</button>
    </form>

    <p class="auth-switch">Don't have an account? <a href="register.php">Register</a></p>
  </div>
</body>
</html>
