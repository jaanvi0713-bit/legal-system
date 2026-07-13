<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(portal_home(current_user()['role']));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $login = post('login', '');
    $password = post('password', '');
    if ($login === '' || $password === '') {
        $error = 'Username/email and password are required.';
    } elseif (attempt_login($login, $password)) {
        redirect(portal_home(current_user()['role']));
    } else {
        $error = 'Invalid credentials or inactive account.';
    }
}
$appName = app_config('name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in · <?= e($appName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="login-card">
        <div class="brand-mark">L</div>
        <h1><?= e($appName) ?></h1>
        <p class="muted">Secure access to Admin, Lawyer, and Client portals.</p>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="form-grid" style="grid-template-columns:1fr; margin-top:1rem;">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="login">Username / Email</label>
                <input type="text" id="login" name="login" required autocomplete="username" placeholder="username or email" value="<?= e(post('login', '')) ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
            </div>
            <button class="btn btn-accent" type="submit">Sign in</button>
        </form>
        <div class="demo-box">
            <strong>Demo accounts</strong><br>
            Admin: <code>admin</code> or <code>admin@admin.mu</code> / <code>admin123</code><br>
            Lawyer: <code>lawyer01</code> / <code>lawyer01</code><br>
            Client: <code>yeshna</code> / <code>yeshna</code>
        </div>
        <p class="muted" style="margin-top:1rem;font-size:0.85rem;">First time? Run <a href="install.php"><strong>install.php</strong></a></p>
    </div>
</body>
</html>
