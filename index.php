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
$base = app_config('url');
$cssVer = filemtime(__DIR__ . '/assets/css/login.css');
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in · <?= e($appName) ?></title>
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('lexora-theme');
                if (stored === 'light' || stored === 'dark') {
                    document.documentElement.setAttribute('data-theme', stored);
                }
            } catch (e) {}
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/login.css?v=<?= (int) $cssVer ?>">
</head>
<body class="auth-landing">
    <div class="glass-scene" aria-hidden="true">
        <div class="glass-orb glass-orb-a"></div>
        <div class="glass-orb glass-orb-b"></div>
        <div class="glass-orb glass-orb-c"></div>
        <div class="glass-orb glass-orb-d"></div>
        <div class="glass-stone glass-stone-a"></div>
        <div class="glass-stone glass-stone-b"></div>
    </div>

    <main class="glass-stage">
        <section class="glass-login">
            <header class="glass-login-top">
                <div class="glass-brand">
                    <span class="brand-mark">L</span>
                    <span><?= e($appName) ?></span>
                </div>
                <span class="glass-portal-tag">Secure portal</span>
            </header>

            <h1 class="glass-title">Log in</h1>
            <p class="glass-lead">Access your admin, lawyer, or client workspace.</p>

            <?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>

            <form method="post" class="glass-form">
                <?= csrf_field() ?>
                <label class="glass-field">
                    <span class="glass-ico" aria-hidden="true">@</span>
                    <input type="text" name="login" required autocomplete="username" placeholder="Username or email" value="<?= e(post('login', '')) ?>">
                </label>
                <label class="glass-field">
                    <span class="glass-ico" aria-hidden="true">•</span>
                    <input type="password" name="password" required autocomplete="current-password" placeholder="Password">
                </label>
                <button class="glass-go" type="submit" aria-label="Sign in">→</button>
            </form>

            <div class="glass-demo">
                <span><b>admin</b> / admin123</span>
                <span><b>lawyer01</b> / lawyer01</span>
                <span><b>yeshna</b> / yeshna</span>
            </div>
        </section>

        <aside class="glass-side">
            <div class="glass-side-orb"></div>
            <p class="glass-side-kicker">Lexora Legal</p>
            <h2>Clarity for every case.</h2>
            <p>Cases, courts, billing, and AI support — unified for your firm.</p>
            <div class="glass-side-cta">
                <span>Enter workspace</span>
                <span class="glass-mini-go">→</span>
            </div>
        </aside>
    </main>
</body>
</html>
