<?php
/**
 * Shared page header / layout start
 * Expected vars: $pageTitle, $portal (admin|lawyer|client), $activeNav
 */
$user = current_user();
$appName = get_setting(db(), 'company_name', app_config('name'));
$unread = unread_notifications(db(), (int) $user['id']);
$flash = get_flash();
$base = app_config('url');
$portalBase = $base . '/' . $portal;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> · <?= e($appName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/style.css">
</head>
<body class="portal-<?= e($portal) ?>">
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-mark">L</div>
            <div>
                <div class="brand-name"><?= e($appName) ?></div>
                <div class="brand-portal"><?= e(ucfirst($portal)) ?> Portal</div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <?php require __DIR__ . '/nav-' . $portal . '.php'; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="user-chip">
                <div class="avatar"><?= e(strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1))) ?></div>
                <div>
                    <strong><?= e(full_name($user)) ?></strong>
                    <span><?= e(ucfirst($user['role'])) ?></span>
                </div>
            </div>
            <a class="btn btn-ghost btn-sm" href="<?= e($base) ?>/logout.php">Sign out</a>
        </div>
    </aside>
    <div class="main">
        <header class="topbar">
            <button class="nav-toggle" type="button" aria-label="Toggle menu">☰</button>
            <div>
                <h1><?= e($pageTitle ?? 'Dashboard') ?></h1>
                <p class="muted"><?= e($pageSubtitle ?? '') ?></p>
            </div>
            <div class="topbar-actions">
                <a class="icon-btn" href="<?= e($portalBase) ?>/notifications.php" title="Notifications">
                    🔔<?php if ($unread > 0): ?><span class="dot"><?= (int) $unread ?></span><?php endif; ?>
                </a>
                <a class="btn btn-accent btn-sm" href="<?= e($portalBase) ?>/ai.php">AI Assistant</a>
            </div>
        </header>
        <main class="content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>
