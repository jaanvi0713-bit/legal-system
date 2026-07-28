<?php
/**
 * Notification delivery — in-app alerts and optional email.
 */

require_once __DIR__ . '/case-team.php';

function notification_category_map(): array
{
    return [
        'case' => 'cases',
        'appointment' => 'appointments',
        'payment' => 'payments',
        'document' => 'documents',
        'reminder' => 'appointments',
        'info' => 'system',
        'success' => 'system',
        'broadcast' => 'system',
        'deadline' => 'appointments',
    ];
}

function notification_firm_allows(PDO $pdo, string $category, string $channel): bool
{
    $key = 'notify_' . $category . '_' . $channel;
    $default = $channel === 'inapp' ? '1' : '0';
    if (!in_array($category, ['cases', 'invoices', 'payments', 'appointments', 'documents', 'account', 'system'], true)) {
        $category = 'system';
        $key = 'notify_' . $category . '_' . $channel;
    }
    return get_setting($pdo, $key, $default) === '1';
}

function send_user_email(PDO $pdo, int $userId, string $subject, string $body): bool
{
    $stmt = $pdo->prepare('SELECT email, first_name, is_active FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row || !(int) ($row['is_active'] ?? 0)) {
        return false;
    }
    $to = trim((string) ($row['email'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $from = trim((string) get_setting($pdo, 'smtp_from', ''));
    if ($from === '') {
        $from = trim((string) get_setting($pdo, 'company_email', ''));
    }
    $firm = company_name($pdo);
    $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
    if ($from !== '') {
        $headers .= 'From: ' . $firm . ' <' . $from . ">\r\n";
    }
    $greeting = trim((string) ($row['first_name'] ?? ''));
    $fullBody = ($greeting !== '' ? __('email.greeting', ['name' => $greeting]) . "\n\n" : '')
        . $body
        . "\n\n— " . $firm;

    return @mail($to, $subject, $fullBody, $headers);
}

/**
 * Create an in-app notification and optionally email the user.
 */
function dispatch_notification(
    PDO $pdo,
    int $userId,
    string $title,
    string $message,
    string $type = 'info',
    ?string $link = null,
    ?int $createdBy = null,
    ?string $category = null,
    bool $forceEmail = false
): void {
    if ($userId < 1) {
        return;
    }
    $category = $category ?? (notification_category_map()[$type] ?? 'system');
    $inapp = notification_firm_allows($pdo, $category, 'inapp');
    $email = $forceEmail || notification_firm_allows($pdo, $category, 'email');

    if ($inapp) {
        create_notification($pdo, $userId, $title, $message, $type, $link, $createdBy);
    }

    if ($email) {
        $subject = t_stored($title);
        if ($subject === $title && str_starts_with($title, 'notify.')) {
            $subject = __($title);
        }
        $body = t_stored($message);
        if ($body === $message && str_contains($message, 'notify.')) {
            $body = __($message);
        }
        if ($link) {
            $base = rtrim((string) app_config('url'), '/');
            $body .= "\n\n" . __('email.open_link') . ': ' . notification_absolute_url($link, $base);
        }
        send_user_email($pdo, $userId, $subject, $body);
    }
}

function notification_absolute_url(string $link, string $base): string
{
    $link = trim($link);
    if ($link === '') {
        return $base;
    }
    if (preg_match('#^https?://#i', $link)) {
        return $link;
    }
    if (str_starts_with($link, '../client/')) {
        return $base . '/client/' . substr($link, 10);
    }
    if (str_starts_with($link, '../lawyer/')) {
        return $base . '/lawyer/' . substr($link, 10);
    }
    if (str_starts_with($link, '../admin/')) {
        return $base . '/admin/' . substr($link, 9);
    }
    if (str_starts_with($link, '/')) {
        return $base . $link;
    }
    return $base . '/admin/' . ltrim($link, './');
}
