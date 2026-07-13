<?php
/**
 * Shared helper functions
 */

function app_config(?string $key = null, $default = null)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/app.php';
    }
    if ($key === null) {
        return $config;
    }
    return $config[$key] ?? $default;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function full_name(array $user): string
{
    return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
}

function money($amount): string
{
    $symbol = app_config('currency_symbol', '₹');
    $value = (float) $amount;
    if (class_exists('NumberFormatter')) {
        $fmt = new NumberFormatter('en_IN', NumberFormatter::DECIMAL);
        $fmt->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $fmt->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);
        return $symbol . $fmt->format($value);
    }
    return $symbol . number_format($value, 2);
}

function format_date(?string $date, string $format = 'd M Y'): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

function format_datetime(?string $date): string
{
    return format_date($date, 'd M Y, H:i');
}

function status_badge(string $status): string
{
    $map = [
        'open' => 'badge-info',
        'active' => 'badge-success',
        'pending' => 'badge-warning',
        'on_hold' => 'badge-muted',
        'closed' => 'badge-dark',
        'reopened' => 'badge-info',
        'accepted' => 'badge-success',
        'rejected' => 'badge-danger',
        'cancelled' => 'badge-muted',
        'completed' => 'badge-dark',
        'scheduled' => 'badge-info',
        'adjourned' => 'badge-warning',
        'draft' => 'badge-muted',
        'sent' => 'badge-info',
        'paid' => 'badge-success',
        'partial' => 'badge-warning',
        'overdue' => 'badge-danger',
        'available' => 'badge-success',
        'busy' => 'badge-warning',
        'unavailable' => 'badge-danger',
        'low' => 'badge-muted',
        'medium' => 'badge-info',
        'high' => 'badge-warning',
        'urgent' => 'badge-danger',
    ];
    $class = $map[$status] ?? 'badge-muted';
    return '<span class="badge ' . $class . '">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
}

function generate_case_number(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->query("SELECT COUNT(*) FROM cases WHERE YEAR(created_at) = YEAR(CURDATE())");
    $count = (int) $stmt->fetchColumn() + 1;
    return sprintf('LEX-%s-%03d', $year, $count);
}

function generate_invoice_number(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->query("SELECT COUNT(*) FROM invoices WHERE YEAR(created_at) = YEAR(CURDATE())");
    $count = (int) $stmt->fetchColumn() + 1;
    return sprintf('INV-%s-%03d', $year, $count);
}

function generate_receipt_number(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->query("SELECT COUNT(*) FROM payments WHERE YEAR(created_at) = YEAR(CURDATE())");
    $count = (int) $stmt->fetchColumn() + 1;
    return sprintf('RCP-%s-%03d', $year, $count);
}

function log_activity(PDO $pdo, ?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null): void
{
    $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $userId,
        $action,
        $entityType,
        $entityId,
        $description,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function create_notification(PDO $pdo, int $userId, string $title, string $message, string $type = 'info', ?string $link = null, ?int $createdBy = null): void
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, link, created_by) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $title, $message, $type, $link, $createdBy]);
}

function unread_notifications(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function get_setting(PDO $pdo, string $key, $default = null)
{
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : $default;
}

function set_setting(PDO $pdo, string $key, ?string $value): void
{
    $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

function handle_upload(array $file, string $subdir = 'documents'): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (($file['size'] ?? 0) > app_config('upload_max', 10485760)) {
        throw new RuntimeException('File exceeds maximum upload size.');
    }
    $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt', 'xls', 'xlsx'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('File type not allowed.');
    }
    $dir = __DIR__ . '/../uploads/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $stored = uniqid('doc_', true) . '.' . $ext;
    $dest = $dir . '/' . $stored;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Failed to store uploaded file.');
    }
    return [
        'file_name' => $file['name'],
        'file_path' => 'uploads/' . $subdir . '/' . $stored,
        'file_type' => $file['type'] ?? $ext,
        'file_size' => (int) $file['size'],
    ];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

function post(string $key, $default = null)
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

function get(string $key, $default = null)
{
    return isset($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
}
