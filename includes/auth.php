<?php
/**
 * Authentication & authorization
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set(app_config('timezone', 'UTC'));

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect(app_config('url') . '/index.php');
    }
}

function require_role(array $roles): void
{
    require_login();
    $user = current_user();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Access denied for this portal.');
    }
}

function attempt_login(string $login, string $password): bool
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE (email = ? OR username = ?) AND is_active = 1 LIMIT 1');
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }
    unset($user['password']);
    $_SESSION['user'] = $user;
    $upd = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
    $upd->execute([$user['id']]);
    log_activity($pdo, (int) $user['id'], 'login', 'user', (int) $user['id'], 'User logged in');
    return true;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function portal_home(string $role): string
{
    $base = app_config('url');
    return match ($role) {
        'admin', 'staff' => $base . '/admin/index.php',
        'lawyer' => $base . '/lawyer/index.php',
        'client' => $base . '/client/index.php',
        default => $base . '/index.php',
    };
}

function refresh_session_user(): void
{
    if (!is_logged_in()) {
        return;
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([current_user()['id']]);
    $user = $stmt->fetch();
    if ($user) {
        unset($user['password']);
        $_SESSION['user'] = $user;
    }
}
