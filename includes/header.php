<?php
/**
 * Shared page header / layout start
 * Expected vars: $pageTitle, $portal (admin|lawyer|client), $activeNav
 */
require_once __DIR__ . '/nav-icons.php';
$user = current_user();
$appName = get_setting(db(), 'company_name', app_config('name'));
$unread = unread_notifications(db(), (int) $user['id']);
$flash = get_flash();
$base = app_config('url');
$portalBase = $base . '/' . $portal;
$themeSetting = get_setting(db(), 'theme', 'light');
$theme = in_array($themeSetting, ['light', 'dark'], true) ? $themeSetting : 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> · <?= e($appName) ?></title>
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('lexora-theme');
                if (stored === 'light' || stored === 'dark') {
                    document.documentElement.setAttribute('data-theme', stored);
                }
                if (localStorage.getItem('lexora-sidebar') === 'collapsed') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            } catch (e) {}
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/style.css">
</head>
<body class="portal-<?= e($portal) ?>">
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-left">
                <div class="brand-mark" aria-hidden="true"><?= nav_icon('logo') ?></div>
                <div class="brand-text">
                    <div class="brand-name"><?= e($appName) ?></div>
                    <div class="brand-portal"><?= e(strtoupper($portal)) ?></div>
                </div>
            </div>
            <button class="sidebar-collapse" type="button" aria-label="Collapse sidebar" title="Collapse">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 6l-6 6 6 6"/></svg>
            </button>
        </div>
        <nav class="sidebar-nav">
            <?php require __DIR__ . '/nav-' . $portal . '.php'; ?>
        </nav>
        <div class="sidebar-footer">
            <a class="btn-signout" href="<?= e($base) ?>/logout.php">
                <span class="nav-icon"><?= nav_icon('logout') ?></span>
                <span class="nav-label">Sign out</span>
            </a>
        </div>
    </aside>
    <div class="main">
        <header class="topbar">
            <button class="nav-toggle" type="button" aria-label="Toggle menu">☰</button>
            <div class="topbar-title">
                <h1><?= e($pageTitle ?? 'Dashboard') ?></h1>
                <?php if (!empty($pageSubtitle)): ?>
                    <p class="muted"><?= e($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>
            <div class="topbar-actions">
                <button class="icon-btn theme-toggle" type="button" title="Toggle light / dark mode" aria-label="Toggle theme">
                    <span class="theme-icon theme-icon-dark">☀</span>
                    <span class="theme-icon theme-icon-light">☾</span>
                </button>
                <a class="icon-btn" href="<?= e($portalBase) ?>/notifications.php" title="Notifications" aria-label="Notifications">
                    <span class="bell">🔔</span>
                    <?php if ($unread > 0): ?><span class="dot"><?= (int) $unread ?></span><?php endif; ?>
                </a>
                <a class="btn btn-primary btn-sm" href="<?= e($portalBase) ?>/ai.php">AI Assistant</a>
            </div>
        </header>
        <main class="content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>
