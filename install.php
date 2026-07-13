<?php
/**
 * One-click installer for Lexora Legal Case Management System
 */
$php = PHP_VERSION;
$config = require __DIR__ . '/config/database.php';
$messages = [];
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['host'], $config['port']);
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $schema = file_get_contents(__DIR__ . '/database/schema.sql');
        $seed = file_get_contents(__DIR__ . '/database/seed.sql');
        $pdo->exec($schema);
        $pdo->exec($seed);

        foreach (['uploads', 'uploads/documents', 'uploads/avatars'] as $dir) {
            $path = __DIR__ . '/' . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0775, true);
            }
        }
        file_put_contents(__DIR__ . '/uploads/.htaccess', "Options -Indexes\n");
        $ok = true;
        $messages[] = 'Database created and seeded successfully.';
    } catch (Throwable $e) {
        $messages[] = 'Install failed: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install · Lexora Legal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page" data-theme="light">
    <div class="login-card">
        <div class="brand-mark">L</div>
        <h1>Install Lexora</h1>
        <p class="muted">Legal Case Management System setup for WAMP.</p>
        <div class="list-stack" style="margin:1rem 0;">
            <div class="list-item"><strong>PHP</strong><?= htmlspecialchars($php) ?></div>
            <div class="list-item"><strong>Database host</strong><?= htmlspecialchars($config['host']) ?></div>
            <div class="list-item"><strong>Database name</strong><?= htmlspecialchars($config['dbname']) ?></div>
            <div class="list-item"><strong>DB user</strong><?= htmlspecialchars($config['username']) ?> (default WAMP root / empty password)</div>
        </div>
        <?php foreach ($messages as $m): ?>
            <div class="alert <?= $ok ? 'alert-success' : 'alert-danger' ?>"><?= htmlspecialchars($m) ?></div>
        <?php endforeach; ?>
        <?php if ($ok): ?>
            <a class="btn btn-accent" href="index.php">Go to login</a>
        <?php else: ?>
            <form method="post">
                <button class="btn btn-primary" type="submit">Create database &amp; seed demo data</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
