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
$accent = get_setting(db(), 'branding_accent', '#023e8a') ?: '#023e8a';
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? strtolower($accent) : '#023e8a';
$ar = hexdec(substr($accent, 1, 2));
$ag = hexdec(substr($accent, 3, 2));
$ab = hexdec(substr($accent, 5, 2));
$clamp = static fn(int $v): int => max(0, min(255, $v));
$hexOf = static fn(int $r, int $g, int $b): string => sprintf('#%02x%02x%02x', $clamp($r), $clamp($g), $clamp($b));
$accentDeep = $hexOf($ar - 40, $ag - 40, $ab - 40);
$accentBright = $hexOf($ar + 28, $ag + 28, $ab + 28);
$accentMid = $hexOf((int) round(($ar + 255) / 2), (int) round(($ag + 255) / 2), (int) round(($ab + 255) / 2));
$accentSoft = sprintf('rgba(%d,%d,%d,0.12)', $ar, $ag, $ab);
$accentLine = sprintf('rgba(%d,%d,%d,0.14)', $ar, $ag, $ab);
$accentShadow = sprintf('0 10px 28px rgba(%d,%d,%d,0.18)', $ar, $ag, $ab);
$gradPrimary = "linear-gradient(135deg, {$accentBright} 0%, {$accent} 100%)";
$gradBtn = "linear-gradient(135deg, {$accent} 0%, {$accentDeep} 100%)";
$gradBanner = "linear-gradient(125deg, {$accentDeep} 0%, {$accent} 48%, {$accentBright} 100%)";
$gradBlue = "linear-gradient(135deg, {$accentBright} 0%, {$accentDeep} 100%)";
$gradInfo = "linear-gradient(135deg, {$accent} 0%, {$accentDeep} 100%)";
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
        window.applyLexoraAccent = function (hex) {
            if (!/^#[0-9a-fA-F]{6}$/.test(hex || '')) return;
            hex = hex.toLowerCase();
            var n = parseInt(hex.slice(1), 16);
            var r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255;
            function clamp(v) { return Math.max(0, Math.min(255, v | 0)); }
            function toHex(rr, gg, bb) {
                return '#' + [rr, gg, bb].map(function (x) {
                    return clamp(x).toString(16).padStart(2, '0');
                }).join('');
            }
            var deep = toHex(r - 40, g - 40, b - 40);
            var bright = toHex(r + 28, g + 28, b + 28);
            var mid = toHex(Math.round((r + 255) / 2), Math.round((g + 255) / 2), Math.round((b + 255) / 2));
            var root = document.documentElement;
            var map = {
                '--primary': hex,
                '--primary-deep': deep,
                '--primary-rgb': r + ', ' + g + ', ' + b,
                '--blue': hex,
                '--blue-bright': bright,
                '--info': hex,
                '--cyan': bright,
                '--purple': deep,
                '--purple-bright': mid,
                '--accent': hex,
                '--nav-active': deep,
                '--nav-active-bg': 'rgba(' + r + ',' + g + ',' + b + ',0.12)',
                '--line': 'rgba(' + r + ',' + g + ',' + b + ',0.14)',
                '--shadow': '0 10px 28px rgba(' + r + ',' + g + ',' + b + ',0.18)',
                '--chart-grid': 'rgba(' + r + ',' + g + ',' + b + ',0.1)',
                '--grad-primary': 'linear-gradient(135deg, ' + bright + ' 0%, ' + hex + ' 100%)',
                '--grad-btn': 'linear-gradient(135deg, ' + hex + ' 0%, ' + deep + ' 100%)',
                '--grad-banner': 'linear-gradient(125deg, ' + deep + ' 0%, ' + hex + ' 48%, ' + bright + ' 100%)',
                '--grad-blue': 'linear-gradient(135deg, ' + bright + ' 0%, ' + deep + ' 100%)',
                '--grad-info': 'linear-gradient(135deg, ' + hex + ' 0%, ' + deep + ' 100%)',
                '--grad-purple': 'linear-gradient(135deg, ' + mid + ' 0%, ' + hex + ' 100%)'
            };
            Object.keys(map).forEach(function (k) { root.style.setProperty(k, map[k]); });
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/style.css">
    <style>
        :root, [data-theme="light"], html[data-theme="dark"] {
            --primary: <?= e($accent) ?>;
            --primary-deep: <?= e($accentDeep) ?>;
            --primary-rgb: <?= (int)$ar ?>, <?= (int)$ag ?>, <?= (int)$ab ?>;
            --blue: <?= e($accent) ?>;
            --blue-bright: <?= e($accentBright) ?>;
            --info: <?= e($accent) ?>;
            --cyan: <?= e($accentBright) ?>;
            --purple: <?= e($accentDeep) ?>;
            --purple-bright: <?= e($accentMid) ?>;
            --accent: <?= e($accent) ?>;
            --nav-active: <?= e($accentDeep) ?>;
            --nav-active-bg: <?= e($accentSoft) ?>;
            --line: <?= e($accentLine) ?>;
            --shadow: <?= e($accentShadow) ?>;
            --chart-grid: rgba(<?= (int)$ar ?>, <?= (int)$ag ?>, <?= (int)$ab ?>, 0.1);
            --grad-primary: <?= e($gradPrimary) ?>;
            --grad-btn: <?= e($gradBtn) ?>;
            --grad-banner: <?= e($gradBanner) ?>;
            --grad-blue: <?= e($gradBlue) ?>;
            --grad-info: <?= e($gradInfo) ?>;
            --grad-purple: linear-gradient(135deg, <?= e($accentMid) ?> 0%, <?= e($accent) ?> 100%);
        }
        html[data-theme="dark"] {
            --nav-active-bg: rgba(<?= (int)$ar ?>, <?= (int)$ag ?>, <?= (int)$ab ?>, 0.22);
            --line: rgba(<?= (int)$ar ?>, <?= (int)$ag ?>, <?= (int)$ab ?>, 0.22);
        }
    </style>
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
