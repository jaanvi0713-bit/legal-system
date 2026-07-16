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
    static $symbol = null;
    static $locale = null;
    if ($symbol === null) {
        $code = 'MUR';
        try {
            $code = strtoupper((string) get_setting(db(), 'payment_currency', app_config('currency', 'MUR')));
        } catch (Throwable $e) {
            $code = strtoupper((string) app_config('currency', 'MUR'));
        }
        $symbols = [
            'MUR' => 'Rs ',
            'INR' => '₹',
            'AED' => 'AED ',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
        ];
        $symbol = $symbols[$code] ?? (app_config('currency_symbol', 'Rs '));
        $locales = [
            'MUR' => 'en_MU',
            'INR' => 'en_IN',
            'AED' => 'en_AE',
            'USD' => 'en_US',
            'EUR' => 'fr_FR',
            'GBP' => 'en_GB',
        ];
        $locale = $locales[$code] ?? 'en_MU';
        if (function_exists('current_lang') && current_lang() === 'fr' && $code === 'MUR') {
            $locale = 'fr_MU';
        }
    }
    $value = (float) $amount;
    if (class_exists('NumberFormatter')) {
        $fmt = new NumberFormatter($locale, NumberFormatter::DECIMAL);
        $fmt->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $fmt->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);
        return $symbol . $fmt->format($value);
    }
    return $symbol . number_format($value, 2);
}

function format_date(?string $date, string $format = 'd M Y'): string
{
    $empty = function_exists('__') ? __('common.em_dash') : '—';
    if (!$date) {
        return $empty;
    }
    $ts = strtotime($date);
    if (!$ts) {
        return $empty;
    }
    if (function_exists('locale_tag') && class_exists('IntlDateFormatter') && ($format === 'd M Y' || $format === 'd M Y, H:i')) {
        $withTime = str_contains($format, 'H:i');
        $fmt = new IntlDateFormatter(
            locale_tag(),
            IntlDateFormatter::MEDIUM,
            $withTime ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE
        );
        $out = $fmt->format($ts);
        if ($out !== false) {
            return $out;
        }
    }
    return date($format, $ts);
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
    $label = function_exists('translate_status') ? translate_status($status) : ucwords(str_replace('_', ' ', $status));
    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
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
        throw new RuntimeException(__('error.upload.too_large'));
    }
    $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt', 'xls', 'xlsx'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException(__('error.upload.type_not_allowed'));
    }
    $dir = __DIR__ . '/../uploads/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $stored = uniqid('doc_', true) . '.' . $ext;
    $dest = $dir . '/' . $stored;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException(__('error.upload.store_failed'));
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
        exit(function_exists('__') ? __('error.csrf') : 'Invalid CSRF token.');
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

/** Calendar tone for appointment status (scheduled, confirmed, rescheduled, past, completed, cancelled). */
function appointment_calendar_tone(array $appt): string
{
    $status = $appt['status'] ?? 'pending';
    $scheduledAt = strtotime((string) ($appt['scheduled_at'] ?? ''));
    $now = time();

    if (in_array($status, ['cancelled', 'rejected'], true)) {
        return 'cancelled';
    }
    if ($status === 'completed') {
        return 'completed';
    }
    if ($scheduledAt && $scheduledAt < $now && in_array($status, ['pending', 'accepted'], true)) {
        return 'past';
    }
    if ($status === 'accepted') {
        return 'confirmed';
    }
    $created = strtotime((string) ($appt['created_at'] ?? ''));
    $updated = strtotime((string) ($appt['updated_at'] ?? ''));
    if ($status === 'pending' && $updated && $created && $updated > $created + 60) {
        return 'rescheduled';
    }
    return 'scheduled';
}
