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
                <p class="muted">Welcome back, <?= e(full_name($user)) ?></p>
            </div>
            <div class="topbar-actions">
                <button class="icon-btn theme-toggle" type="button" title="Toggle light / dark mode" aria-label="Toggle theme">
                    <span class="theme-icon theme-icon-dark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4.2"/><path d="M12 3v1.5M12 19.5V21M4.9 4.9l1.1 1.1M18 18l1.1 1.1M3 12h1.5M19.5 12H21M4.9 19.1l1.1-1.1M18 6l1.1-1.1"/></svg>
                    </span>
                    <span class="theme-icon theme-icon-light" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14.5 4A7.5 7.5 0 1 0 20 15.5 7.5 7.5 0 0 1 14.5 4z"/>
                            <path d="M17 3.5v3M15.5 5h3"/>
                            <path d="M20 7.5v2M19 8.5h2"/>
                        </svg>
                    </span>
                </button>
                <a class="icon-btn" href="<?= e($portalBase) ?>/notifications.php" title="Notifications" aria-label="Notifications">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 9a5 5 0 0 1 10 0c0 5 2 6.5 2 6.5H5S7 14 7 9"/><path d="M10.5 19a1.5 1.5 0 0 0 3 0"/></svg>
                    <?php if ($unread > 0): ?><span class="dot"><?= $unread > 9 ? '9+' : (int) $unread ?></span><?php endif; ?>
                </a>
                <div class="topbar-user">
                    <div class="topbar-avatar"><?= e(strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1))) ?></div>
                    <div class="topbar-user-meta">
                        <strong><?= e(full_name($user)) ?></strong>
                        <span><?= e(ucwords(str_replace('_', ' ', $user['role']))) ?></span>
                    </div>
                    <details class="topbar-menu">
                        <summary aria-label="Account menu">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                        </summary>
                        <div class="topbar-menu-panel">
                            <?php if ($portal === 'admin'): ?>
                                <a href="<?= e($portalBase) ?>/settings.php">Settings</a>
                                <a href="<?= e($portalBase) ?>/users.php">Users</a>
                            <?php elseif ($portal === 'lawyer'): ?>
                                <a href="<?= e($portalBase) ?>/profile.php">Profile</a>
                            <?php endif; ?>
                            <a href="<?= e($base) ?>/logout.php">Sign out</a>
                        </div>
                    </details>
                </div>
            </div>
        </header>
        <main class="content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>
