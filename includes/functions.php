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
        'confirmed' => 'badge-success',
        'rejected' => 'badge-danger',
        'rescheduled' => 'badge-info',
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
    $class = $map[normalize_appointment_status($status)] ?? $map[$status] ?? 'badge-muted';
    $label = function_exists('translate_status') ? translate_status($status) : ucwords(str_replace('_', ' ', $status));
    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
}

function generate_case_number(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->query("SELECT COUNT(*) FROM cases WHERE YEAR(created_at) = YEAR(CURDATE())");
    $count = (int) $stmt->fetchColumn() + 1;
    return sprintf('CASE-%s-%03d', $year, $count);
}

function generate_invoice_number(PDO $pdo): string
{
    $year = date('Y');
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    for ($attempt = 0; $attempt < 24; $attempt++) {
        $code = '';
        for ($i = 0; $i < 5; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }
        $number = sprintf('INV-%s-%s', $year, $code);
        $check = $pdo->prepare('SELECT 1 FROM invoices WHERE invoice_number = ? LIMIT 1');
        $check->execute([$number]);
        if (!$check->fetchColumn()) {
            return $number;
        }
    }
    return sprintf('INV-%s-%05d', $year, random_int(10000, 99999));
}

function ensure_invoice_items_table(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS invoice_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT UNSIGNED NOT NULL,
            description VARCHAR(255) NOT NULL,
            quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
            unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_invoice_items_invoice (invoice_id),
            CONSTRAINT fk_invoice_items_invoice
                FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $ready = true;
}

function invoice_paid_total(PDO $pdo, int $invoiceId): float
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = ?');
    $stmt->execute([$invoiceId]);
    return (float) $stmt->fetchColumn();
}

function invoice_line_items(PDO $pdo, int $invoiceId): array
{
    ensure_invoice_items_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$invoiceId]);
    return $stmt->fetchAll();
}

function ensure_invoice_bank_column(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $col = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'bank_account_id'")->fetch();
    if (!$col) {
        $pdo->exec('ALTER TABLE invoices ADD COLUMN bank_account_id TINYINT UNSIGNED DEFAULT NULL AFTER created_by');
    }
    $ready = true;
}

/** @return array<int, array{id:int,label:string,bank:string,account_name:string,account_number:string,iban:string,swift:string}> */
function get_bank_accounts(PDO $pdo): array
{
    $raw = (string) get_setting($pdo, 'bank_accounts_json', '');
    $decoded = $raw !== '' ? json_decode($raw, true) : null;
    $accounts = [];
    for ($i = 1; $i <= 3; $i++) {
        $row = is_array($decoded) ? ($decoded[(string) $i] ?? $decoded[$i - 1] ?? $decoded[$i] ?? []) : [];
        if (!is_array($row)) {
            $row = [];
        }
        $accounts[$i] = [
            'id' => $i,
            'label' => trim((string) ($row['label'] ?? ('Bank account ' . $i))),
            'bank' => trim((string) ($row['bank'] ?? '')),
            'account_name' => trim((string) ($row['account_name'] ?? '')),
            'account_number' => trim((string) ($row['account_number'] ?? '')),
            'iban' => trim((string) ($row['iban'] ?? '')),
            'swift' => trim((string) ($row['swift'] ?? '')),
        ];
    }
    return $accounts;
}

function get_configured_bank_accounts(PDO $pdo): array
{
    return array_filter(get_bank_accounts($pdo), static function (array $a): bool {
        return $a['bank'] !== '' || $a['account_number'] !== '' || $a['iban'] !== '';
    });
}

function get_bank_account(PDO $pdo, ?int $id): ?array
{
    if ($id === null || $id < 1 || $id > 3) {
        return null;
    }
    $all = get_bank_accounts($pdo);
    $account = $all[$id] ?? null;
    if (!$account) {
        return null;
    }
    if ($account['bank'] === '' && $account['account_number'] === '' && $account['iban'] === '') {
        return null;
    }
    return $account;
}

function save_bank_accounts(PDO $pdo, array $posted): void
{
    $out = [];
    for ($i = 1; $i <= 3; $i++) {
        $out[(string) $i] = [
            'label' => trim((string) ($posted[$i]['label'] ?? ('Bank account ' . $i))),
            'bank' => trim((string) ($posted[$i]['bank'] ?? '')),
            'account_name' => trim((string) ($posted[$i]['account_name'] ?? '')),
            'account_number' => trim((string) ($posted[$i]['account_number'] ?? '')),
            'iban' => trim((string) ($posted[$i]['iban'] ?? '')),
            'swift' => trim((string) ($posted[$i]['swift'] ?? '')),
        ];
    }
    set_setting($pdo, 'bank_accounts_json', json_encode($out, JSON_UNESCAPED_UNICODE));
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

/** @return list<string> */
function appointment_statuses(): array
{
    return ['scheduled', 'confirmed', 'rescheduled', 'pending', 'completed', 'cancelled'];
}

/** Statuses that count as active/upcoming appointments. */
/** @return list<string> */
function appointment_upcoming_statuses(): array
{
    return ['scheduled', 'confirmed', 'rescheduled', 'pending'];
}

function normalize_appointment_status(string $status): string
{
    static $legacy = [
        'accepted' => 'confirmed',
        'rejected' => 'cancelled',
    ];
    return $legacy[$status] ?? $status;
}

/** Calendar tone matches stored appointment status. */
function appointment_calendar_tone(array $appt): string
{
    $status = normalize_appointment_status((string) ($appt['status'] ?? 'pending'));
    return in_array($status, appointment_statuses(), true) ? $status : 'pending';
}

function appointment_format_day_time(?string $iso): string
{
    if (!$iso) {
        return '';
    }
    $ts = strtotime($iso);
    if (!$ts) {
        return '';
    }
    if (class_exists('IntlDateFormatter') && function_exists('locale_tag')) {
        $fmt = new IntlDateFormatter(
            locale_tag(),
            IntlDateFormatter::NONE,
            IntlDateFormatter::SHORT
        );
        $out = $fmt->format($ts);
        if ($out !== false) {
            return strtolower(str_replace(' ', '', $out));
        }
    }
    return strtolower(date('g:ia', $ts));
}

function appointment_calendar_tone_priority(string $tone): int
{
    static $map = [
        'cancelled' => 6,
        'completed' => 5,
        'pending' => 4,
        'confirmed' => 3,
        'rescheduled' => 2,
        'scheduled' => 1,
    ];
    return $map[$tone] ?? 0;
}

function appointment_calendar_pick_tone(array $items): string
{
    $best = 'scheduled';
    $score = 0;
    foreach ($items as $item) {
        $tone = (string) ($item['tone'] ?? 'scheduled');
        $next = appointment_calendar_tone_priority($tone);
        if ($next >= $score) {
            $score = $next;
            $best = $tone;
        }
    }
    return $best;
}

/** @return array<string, array<int, array<string, mixed>>> */
function appointment_calendar_group_by_date(array $items): array
{
    $byDate = [];
    foreach ($items as $item) {
        $raw = (string) ($item['scheduledAt'] ?? '');
        // Prefer YYYY-MM-DD prefix to avoid timezone shifts from strtotime
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
            $key = $m[1];
        } else {
            $ts = strtotime($raw);
            if (!$ts) {
                continue;
            }
            $key = date('Y-m-d', $ts);
        }
        $byDate[$key][] = $item;
    }
    foreach ($byDate as &$dayItems) {
        usort($dayItems, static fn(array $a, array $b): int => strtotime((string) $a['scheduledAt']) <=> strtotime((string) $b['scheduledAt']));
    }
    unset($dayItems);
    return $byDate;
}

function render_appointment_calendar_day_events(array $items, int $maxShow = 2): string
{
    if (!$items) {
        return '';
    }
    $shown = array_slice($items, 0, $maxShow);
    $extra = count($items) - count($shown);
    $html = '<div class="appt-cal-day-events">';
    foreach ($shown as $item) {
        $tone = e((string) ($item['tone'] ?? 'scheduled'));
        $title = e((string) ($item['title'] ?? ''));
        $time = e(appointment_format_day_time((string) ($item['scheduledAt'] ?? '')));
        $viewLabel = e(__('calendar.view_appointment'));
        $id = (int) ($item['id'] ?? 0);
        $html .= '<a href="?view=' . $id . '" class="appt-cal-day-event tone-' . $tone . '" data-appt-view="' . $id . '" title="' . $viewLabel . ': ' . $title . '" onclick="return window.lexoraViewAppointment ? window.lexoraViewAppointment(' . $id . ', event) : true;">';
        $html .= '<span class="appt-cal-day-event-dot" aria-hidden="true"></span>';
        $html .= '<span class="appt-cal-day-event-label"><span class="appt-cal-day-event-time">' . $time . '</span> ' . $title . '</span>';
        $html .= '</a>';
    }
    if ($extra > 0) {
        $html .= '<span class="appt-cal-day-more">+' . (int) $extra . ' more</span>';
    }
    $html .= '</div>';
    return $html;
}

function render_appointment_calendar_days(int $year, int $month, int $selectedDay, array $itemsByDate): string
{
    $firstDow = ((int) date('N', mktime(0, 0, 0, $month + 1, 1, $year))) - 1;
    $daysInMonth = (int) date('t', mktime(0, 0, 0, $month + 1, 1, $year));
    $selectedDay = min(max(1, $selectedDay), $daysInMonth);
    $todayY = (int) date('Y');
    $todayM = (int) date('n') - 1;
    $todayD = (int) date('j');
    $colIndex = $firstDow;
    $html = '';

    for ($i = 0; $i < $firstDow; $i++) {
        $html .= '<span class="appt-cal-day is-empty" aria-hidden="true"></span>';
    }

    for ($day = 1; $day <= $daysInMonth; $day++) {
        $key = sprintf('%04d-%02d-%02d', $year, $month + 1, $day);
        $items = $itemsByDate[$key] ?? [];
        $tone = $items ? appointment_calendar_pick_tone($items) : '';
        $isToday = $todayY === $year && $todayM === $month && $todayD === $day;
        $isSelected = $day === $selectedDay;
        $colIndex = ($firstDow + $day - 1) % 7;

        $classes = ['appt-cal-day'];
        if ($items) {
            $classes[] = 'has-appt';
        }
        if ($tone) {
            $classes[] = 'tone-' . $tone;
        }
        if ($isToday) {
            $classes[] = 'is-today';
        }
        if ($isSelected) {
            $classes[] = 'is-selected';
        }

        $label = (string) $day;
        if ($items) {
            $count = count($items);
            $label .= ', ' . $count . ' appointment' . ($count > 1 ? 's' : '');
        }

        $dateIso = sprintf('%04d-%02d-%02d', $year, $month + 1, $day);
        $dayHref = e('?cal_date=' . $dateIso);

        $html .= '<div class="' . implode(' ', $classes) . '" data-day="' . $day . '" data-date="' . e($dateIso) . '" data-col="' . $colIndex . '">';
        $html .= '<a href="' . $dayHref . '" class="appt-cal-day-select" aria-label="' . e($label) . '">';
        $html .= '<span class="appt-cal-day-num">' . $day . '</span>';
        $html .= '</a>';
        $html .= render_appointment_calendar_day_events($items);
        $html .= '</div>';
    }

    return $html;
}

function appointment_calendar_selected_label(int $year, int $month, int $day): string
{
    $ts = mktime(0, 0, 0, $month + 1, $day, $year);
    if (!$ts) {
        return '';
    }
    if (class_exists('IntlDateFormatter') && function_exists('locale_tag')) {
        $fmt = new IntlDateFormatter(
            locale_tag(),
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            null,
            null,
            'EEEE, d MMMM yyyy'
        );
        $out = $fmt->format($ts);
        if ($out !== false) {
            return $out;
        }
    }
    return date('l, j F Y', $ts);
}

function appointment_calendar_export_urls(array $item): array
{
    $title = (string) ($item['title'] ?? 'Appointment');
    $details = (string) ($item['description'] ?? '');
    $location = (string) ($item['location'] ?? '');
    $startTs = strtotime((string) ($item['scheduledAt'] ?? $item['scheduled_at'] ?? ''));
    $duration = (int) ($item['durationMinutes'] ?? $item['duration_minutes'] ?? 60);
    if (!$startTs) {
        return ['google' => '#', 'outlook' => '#', 'ics' => '#'];
    }
    $endTs = $startTs + max(15, $duration) * 60;
    $fmt = static fn(int $ts): string => gmdate('Ymd\THis', $ts);
    // Use local wall time without Z for Google "floating" local times
    $localFmt = static fn(int $ts): string => date('Ymd\THis', $ts);

    $google = 'https://calendar.google.com/calendar/render?' . http_build_query([
        'action' => 'TEMPLATE',
        'text' => $title,
        'dates' => $localFmt($startTs) . '/' . $localFmt($endTs),
        'details' => $details,
        'location' => $location,
    ]);

    $outlook = 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query([
        'path' => '/calendar/action/compose',
        'rru' => 'addevent',
        'subject' => $title,
        'startdt' => date('c', $startTs),
        'enddt' => date('c', $endTs),
        'body' => $details,
        'location' => $location,
    ]);

    $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Legal Pro//Appointments//EN\r\nBEGIN:VEVENT\r\n"
        . 'UID:appt-' . (int) ($item['id'] ?? 0) . "@legal-system\r\n"
        . 'DTSTAMP:' . $fmt(time()) . "\r\n"
        . 'DTSTART:' . $localFmt($startTs) . "\r\n"
        . 'DTEND:' . $localFmt($endTs) . "\r\n"
        . 'SUMMARY:' . str_replace(["\r", "\n"], ' ', $title) . "\r\n"
        . 'DESCRIPTION:' . str_replace(["\r", "\n"], '\\n', $details) . "\r\n"
        . 'LOCATION:' . str_replace(["\r", "\n"], ' ', $location) . "\r\n"
        . "END:VEVENT\r\nEND:VCALENDAR";

    return [
        'google' => $google,
        'outlook' => $outlook,
        'ics' => 'data:text/calendar;charset=utf-8,' . rawurlencode($ics),
    ];
}

function appointment_list_status_badge(string $status): string
{
    $status = normalize_appointment_status($status);
    $label = e(translate_status($status));
    return '<span class="appt-list-badge tone-' . e($status) . '">' . $label . '</span>';
}

/** @return list<string> */
function hearing_statuses(): array
{
    return ['scheduled', 'adjourned', 'completed', 'cancelled'];
}

function hearing_calendar_tone(string $status): string
{
    $status = strtolower(trim($status));
    return match ($status) {
        'adjourned' => 'rescheduled',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
        default => 'scheduled',
    };
}

function hearing_list_status_badge(string $status): string
{
    $status = strtolower(trim($status));
    if (!in_array($status, hearing_statuses(), true)) {
        $status = 'scheduled';
    }
    $tone = hearing_calendar_tone($status);
    return '<span class="appt-list-badge tone-' . e($tone) . '">' . e(translate_status($status)) . '</span>';
}

function calendar_item_from_appointment(array $r, array $opts = []): array
{
    $caseLabel = trim(($r['case_number'] ? $r['case_number'] . ' — ' : '') . ($r['case_title'] ?: ($r['title'] ?? '')));
    $status = normalize_appointment_status((string) ($r['status'] ?? 'pending'));
    return [
        'id' => (int) ($r['id'] ?? 0),
        'title' => t_content($r['title'] ?? ''),
        'caseLabel' => t_content($caseLabel),
        'client' => (string) ($r['client_name'] ?? ''),
        'lawyer' => (string) ($r['lawyer_name'] ?? ''),
        'location' => !empty($r['location']) ? t_content($r['location']) : '',
        'scheduledAt' => (string) ($r['scheduled_at'] ?? ''),
        'status' => $status,
        'tone' => appointment_calendar_tone($r),
        'statusLabel' => translate_status($status),
        'description' => !empty($r['description']) ? t_content($r['description']) : '',
        'durationMinutes' => (int) ($r['duration_minutes'] ?? 60),
        'appointmentType' => (string) ($r['appointment_type'] ?? ''),
        'appointmentTypeLabel' => !empty($r['appointment_type']) ? __('appointment.type.' . $r['appointment_type']) : '',
        'editUrl' => (string) ($opts['editUrl'] ?? ('?action=edit&id=' . (int) ($r['id'] ?? 0))),
    ];
}

function calendar_item_from_hearing(array $r, array $opts = []): array
{
    $caseLabel = trim(($r['case_number'] ? $r['case_number'] . ' — ' : '') . ($r['title'] ?? ''));
    $status = strtolower((string) ($r['status'] ?? 'scheduled'));
    $hearingTitle = trim(($r['hearing_type'] ?: __('court.hearings')) . ($caseLabel !== '' ? ' — ' . $caseLabel : ''));
    $location = trim((string) ($r['court_name'] ?? '') . (!empty($r['court_location']) ? ', ' . $r['court_location'] : ''));
    $notes = trim((string) ($r['notes'] ?? ''));
    $outcome = trim((string) ($r['outcome'] ?? ''));
    $description = trim($notes . (($notes && $outcome) ? "\n" : '') . $outcome);
    $editUrl = array_key_exists('editUrl', $opts)
        ? (string) $opts['editUrl']
        : ('?action=edit&id=' . (int) ($r['id'] ?? 0));

    return [
        'id' => (int) ($r['id'] ?? 0),
        'title' => t_content($hearingTitle),
        'caseLabel' => t_content($caseLabel),
        'client' => t_content((string) ($r['court_name'] ?? '')),
        'lawyer' => (string) ($r['lawyer_name'] ?? ''),
        'location' => t_content($location),
        'scheduledAt' => (string) ($r['hearing_date'] ?? ''),
        'status' => $status,
        'tone' => hearing_calendar_tone($status),
        'statusLabel' => translate_status($status),
        'description' => $description !== '' ? t_content($description) : '',
        'durationMinutes' => (int) ($opts['durationMinutes'] ?? 60),
        'editUrl' => $editUrl,
    ];
}

/**
 * Build calendar selection state + footer payload for appointments or hearings.
 *
 * @param list<array<string,mixed>> $calendarItems
 * @param array<string,mixed> $config
 * @return array<string,mixed>
 */
function build_entity_calendar_context(array $calendarItems, array $config = []): array
{
    $entity = (string) ($config['entity'] ?? 'appointment');
    $showCreate = (bool) ($config['showCreate'] ?? false);
    $createUrl = (string) ($config['createUrl'] ?? '?action=create');
    $createLabel = (string) ($config['createLabel'] ?? __('appointments.create'));
    $upcomingStatuses = $config['upcomingStatuses'] ?? (
        $entity === 'hearing' ? ['scheduled'] : appointment_upcoming_statuses()
    );

    $calendarMonths = [
        __('calendar.month.jan'), __('calendar.month.feb'), __('calendar.month.mar'),
        __('calendar.month.apr'), __('calendar.month.may'), __('calendar.month.jun'),
        __('calendar.month.jul'), __('calendar.month.aug'), __('calendar.month.sep'),
        __('calendar.month.oct'), __('calendar.month.nov'), __('calendar.month.dec'),
    ];

    if ($entity === 'hearing') {
        $legendItems = [];
        foreach (hearing_statuses() as $st) {
            $legendItems[] = [
                'tone' => hearing_calendar_tone($st),
                'label' => __('court.tone.' . $st),
            ];
        }
        $countOne = (string) ($config['countOne'] ?? __('court.total_one', ['count' => ':count']));
        $countMany = (string) ($config['countMany'] ?? __('court.total_many', ['count' => ':count']));
        $emptyDay = (string) ($config['emptyDay'] ?? __('calendar.empty_day_hearings'));
        $emptyMonth = (string) ($config['emptyMonth'] ?? __('calendar.empty_month_hearings'));
        $viewLabel = (string) ($config['viewLabel'] ?? __('calendar.view_hearing'));
        $viewAllLabel = (string) ($config['viewAllLabel'] ?? __('calendar.view_hearings'));
        $noViewLabel = (string) ($config['noViewLabel'] ?? __('calendar.no_view_hearings'));
        $scheduleLabel = (string) ($config['scheduleLabel'] ?? __('court.add'));
        $fieldPerson = (string) ($config['fieldPerson'] ?? __('common.court'));
        $mainAria = (string) ($config['mainAria'] ?? __('court.schedule'));
    } else {
        $legendItems = [];
        foreach (appointment_statuses() as $st) {
            $legendItems[] = [
                'tone' => $st,
                'label' => __('calendar.tone.' . $st),
            ];
        }
        $countOne = (string) ($config['countOne'] ?? __('appointments.total_one', ['count' => ':count']));
        $countMany = (string) ($config['countMany'] ?? __('appointments.total_many', ['count' => ':count']));
        $emptyDay = (string) ($config['emptyDay'] ?? __('calendar.empty_day'));
        $emptyMonth = (string) ($config['emptyMonth'] ?? __('calendar.empty_month'));
        $viewLabel = (string) ($config['viewLabel'] ?? __('calendar.view_appointment'));
        $viewAllLabel = (string) ($config['viewAllLabel'] ?? __('calendar.view_appointments'));
        $noViewLabel = (string) ($config['noViewLabel'] ?? __('calendar.no_view_available'));
        $scheduleLabel = (string) ($config['scheduleLabel'] ?? __('calendar.schedule_for_day'));
        $fieldPerson = (string) ($config['fieldPerson'] ?? __('common.client'));
        $mainAria = (string) ($config['mainAria'] ?? __('appointments.calendar'));
    }

    $calYear = (int) date('Y');
    $calMonth = (int) date('n') - 1;
    $calDay = (int) date('j');
    $calDateParam = get('cal_date', '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $calDateParam)) {
        $calYear = (int) substr($calDateParam, 0, 4);
        $calMonth = (int) substr($calDateParam, 5, 2) - 1;
        $calDay = (int) substr($calDateParam, 8, 2);
    }

    $calendarItemsByDate = appointment_calendar_group_by_date($calendarItems);
    $selectedKey = sprintf('%04d-%02d-%02d', $calYear, $calMonth + 1, $calDay);
    $selectedDayItems = $calendarItemsByDate[$selectedKey] ?? [];

    if (!$selectedDayItems) {
        $upcoming = null;
        foreach ($calendarItems as $item) {
            if (!in_array((string) ($item['status'] ?? ''), $upcomingStatuses, true)) {
                continue;
            }
            $ts = strtotime((string) ($item['scheduledAt'] ?? ''));
            if (!$ts || $ts < time()) {
                continue;
            }
            if (!$upcoming || $ts < strtotime((string) $upcoming['scheduledAt'])) {
                $upcoming = $item;
            }
        }
        if ($upcoming) {
            $raw = (string) $upcoming['scheduledAt'];
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
                $calYear = (int) $m[1];
                $calMonth = (int) $m[2] - 1;
                $calDay = (int) $m[3];
            } else {
                $calYear = (int) date('Y', strtotime($raw));
                $calMonth = (int) date('n', strtotime($raw)) - 1;
                $calDay = (int) date('j', strtotime($raw));
            }
            $selectedKey = sprintf('%04d-%02d-%02d', $calYear, $calMonth + 1, $calDay);
            $selectedDayItems = $calendarItemsByDate[$selectedKey] ?? [];
        }
    }

    $calendarMonthCounts = array_fill(0, 12, 0);
    foreach ($calendarItems as $item) {
        $raw = (string) ($item['scheduledAt'] ?? '');
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
            $ts = strtotime($raw);
            if (!$ts || (int) date('Y', $ts) !== $calYear) {
                continue;
            }
            $calendarMonthCounts[(int) date('n', $ts) - 1]++;
            continue;
        }
        if ((int) $m[1] !== $calYear) {
            continue;
        }
        $calendarMonthCounts[(int) $m[2] - 1]++;
    }

    $calendarItemsById = [];
    foreach ($calendarItems as $item) {
        $calendarItemsById[(string) $item['id']] = $item;
    }

    $viewId = (int) get('view', 0);
    $viewItem = $viewId && isset($calendarItemsById[(string) $viewId])
        ? $calendarItemsById[(string) $viewId]
        : null;

    $agendaLabels = [
        'emptyDay' => $emptyDay,
        'countOne' => $countOne,
        'countMany' => $countMany,
        'scheduleLabel' => $scheduleLabel,
        'viewLabel' => $viewLabel,
        'viewAllLabel' => $viewAllLabel,
        'noViewLabel' => $noViewLabel,
        'createUrl' => $createUrl,
        'selectedDate' => $selectedKey,
    ];

    $apptCalPayload = [
        'items' => $calendarItems,
        'itemsById' => $calendarItemsById,
        'months' => $calendarMonths,
        'tones' => array_column($legendItems, 'label'),
        'emptyDay' => $emptyDay,
        'emptyMonth' => $emptyMonth,
        'scheduleLabel' => $scheduleLabel,
        'viewLabel' => $viewLabel,
        'viewAllLabel' => $viewAllLabel,
        'noViewLabel' => $noViewLabel,
        'apptCountOne' => $countOne,
        'apptCountMany' => $countMany,
        'pageOf' => __('calendar.page_of', ['page' => ':page', 'pages' => ':pages']),
        'prevLabel' => __('common.previous'),
        'nextLabel' => __('common.next'),
        'createUrl' => $createUrl,
        'locale' => locale_tag(),
        'emDash' => __('common.em_dash'),
        'editLabel' => __('common.edit'),
        'fieldClient' => $fieldPerson,
        'fieldCase' => __('common.case'),
        'fieldWhen' => __('common.when'),
        'fieldLocation' => __('common.location'),
        'fieldStatus' => __('common.status'),
        'fieldNotes' => __('common.notes'),
        'fieldLawyer' => __('common.lawyer'),
        'exportGoogle' => __('calendar.export.google'),
        'exportOutlook' => __('calendar.export.outlook'),
        'exportIcs' => __('calendar.export.ics'),
    ];

    return [
        'calendarItems' => $calendarItems,
        'calendarItemsByDate' => $calendarItemsByDate,
        'calendarItemsById' => $calendarItemsById,
        'calendarMonths' => $calendarMonths,
        'calendarMonthCounts' => $calendarMonthCounts,
        'calendarLegendItems' => $legendItems,
        'calYear' => $calYear,
        'calMonth' => $calMonth,
        'calDay' => $calDay,
        'selectedDayItems' => $selectedDayItems,
        'viewItem' => $viewItem,
        'apptCalPayload' => $apptCalPayload,
        'calShowCreate' => $showCreate,
        'calCreateUrl' => $createUrl,
        'calCreateLabel' => $createLabel,
        'calMainAria' => $mainAria,
        'calViewLabel' => $viewLabel,
        'calViewAllLabel' => $viewAllLabel,
        'calNoViewLabel' => $noViewLabel,
        'calEmptyDay' => $emptyDay,
        'calScheduleLabel' => $scheduleLabel,
        'calCountOne' => $countOne,
        'calCountMany' => $countMany,
        'calAgendaLabels' => $agendaLabels,
        'calFieldPerson' => $fieldPerson,
    ];
}


function hearing_calendar_export_urls(array $item): array
{
    return appointment_calendar_export_urls([
        'id' => (int) ($item['id'] ?? 0),
        'title' => (string) ($item['title'] ?? 'Court hearing'),
        'description' => (string) ($item['description'] ?? $item['notes'] ?? ''),
        'location' => trim(((string) ($item['court_name'] ?? '')) . ((($item['court_location'] ?? '') !== '') ? ', ' . $item['court_location'] : '')),
        'scheduledAt' => (string) ($item['hearing_date'] ?? $item['scheduledAt'] ?? ''),
        'durationMinutes' => (int) ($item['durationMinutes'] ?? 60),
    ]);
}

function calendar_export_buttons_html(array $exports, string $downloadName = 'event'): string
{
    $name = preg_replace('/[^\w\-]+/', '-', strtolower($downloadName)) ?: 'event';
    return '<div class="appt-list-cal-actions">'
        . '<a class="appt-list-cal-btn" href="' . e($exports['google']) . '" target="_blank" rel="noopener" title="' . e(__('calendar.export.google')) . '" aria-label="' . e(__('calendar.export.google')) . '"><span class="appt-list-cal-g" aria-hidden="true">G</span></a>'
        . '<a class="appt-list-cal-btn" href="' . e($exports['outlook']) . '" target="_blank" rel="noopener" title="' . e(__('calendar.export.outlook')) . '" aria-label="' . e(__('calendar.export.outlook')) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 3v3M16 3v3M8 13h3M13 13h3M8 17h3M13 17h3"/></svg></a>'
        . '<a class="appt-list-cal-btn" href="' . e($exports['ics']) . '" download="' . e($name) . '.ics" title="' . e(__('calendar.export.ics')) . '" aria-label="' . e(__('calendar.export.ics')) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v12m0 0l-4-4m4 4l4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg></a>'
        . '</div>';
}


function render_appointment_calendar_agenda_head(int $year, int $month, int $day, array $items, array $labels = []): string
{
    $selectedLabel = e(appointment_calendar_selected_label($year, $month, $day));
    $count = count($items);
    $empty = (string) ($labels['emptyDay'] ?? __('calendar.empty_day'));
    $oneTpl = (string) ($labels['countOne'] ?? __('appointments.total_one', ['count' => ':count']));
    $manyTpl = (string) ($labels['countMany'] ?? __('appointments.total_many', ['count' => ':count']));
    $countLabel = $count === 0
        ? e($empty)
        : e(str_replace(':count', (string) $count, $count === 1 ? $oneTpl : $manyTpl));

    return '<div class="appt-cal-agenda-panel">'
        . '<p class="appt-cal-agenda-date">' . $selectedLabel . '</p>'
        . '<p class="appt-cal-agenda-count">' . $countLabel . '</p>'
        . '</div>';
}

function render_appointment_calendar_agenda_list(array $items, int $page = 1, int $perPage = 2): string
{
    if (!$items) {
        return '';
    }

    $total = count($items);
    $perPage = max(1, $perPage);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $page), $pages);
    $offset = ($page - 1) * $perPage;
    $slice = array_slice($items, $offset, $perPage);

    $html = '';
    foreach ($slice as $index => $item) {
        $tone = e((string) ($item['tone'] ?? 'scheduled'));
        $title = e((string) ($item['caseLabel'] ?? $item['title'] ?? ''));
        $time = e(appointment_format_day_time((string) ($item['scheduledAt'] ?? '')));
        $person = e((string) ($item['client'] ?? $item['lawyer'] ?? ''));
        $statusLabel = e((string) ($item['statusLabel'] ?? ''));
        $viewLabel = e(__('calendar.view_appointment'));
        $delay = (int) $index * 40;

        $html .= '<article class="appt-cal-agenda-card tone-' . $tone . '" style="animation-delay:' . $delay . 'ms">';
        $id = (int) ($item['id'] ?? 0);
        $html .= '<a href="?view=' . $id . '" class="appt-cal-agenda-card-link" data-appt-view="' . $id . '" title="' . $viewLabel . '" onclick="return window.lexoraViewAppointment ? window.lexoraViewAppointment(' . $id . ', event) : true;">';
        $html .= '<span class="appt-cal-agenda-line tone-' . $tone . '">';
        $html .= '<span class="appt-cal-day-event-dot" aria-hidden="true"></span>';
        $html .= '<span class="appt-cal-day-event-label"><span class="appt-cal-day-event-time">' . $time . '</span> ' . $title . '</span>';
        $html .= '</span>';
        $html .= '<span class="appt-cal-agenda-meta">';
        $html .= '<span class="appt-cal-person">' . $person . '</span>';
        $html .= '<span class="appt-cal-badge tone-' . $tone . '">' . $statusLabel . '</span>';
        $html .= '</span>';
        $html .= '</a>';
        $html .= '</article>';
    }

    return $html;
}

function render_appointment_calendar_agenda_pager(int $total, int $page = 1, int $perPage = 2): string
{
    if ($total < 1) {
        return '';
    }
    $perPage = max(1, $perPage);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $page), $pages);
    $label = e(__('calendar.page_of', ['page' => $page, 'pages' => $pages]));
    $prevDisabled = $page <= 1 ? ' disabled' : '';
    $nextDisabled = $page >= $pages ? ' disabled' : '';

    return '<div class="appt-cal-agenda-pager" id="apptCalAgendaPagerInner" data-page="' . $page . '" data-pages="' . $pages . '">'
        . '<button type="button" class="appt-cal-agenda-page-btn" data-agenda-page="prev"' . $prevDisabled . ' aria-label="' . e(__('common.previous')) . '">'
        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>'
        . '</button>'
        . '<span class="appt-cal-agenda-page-label">' . $label . '</span>'
        . '<button type="button" class="appt-cal-agenda-page-btn" data-agenda-page="next"' . $nextDisabled . ' aria-label="' . e(__('common.next')) . '">'
        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>'
        . '</button>'
        . '</div>';
}
