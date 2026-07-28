<?php
/**
 * Firm broadcast announcements.
 */

require_once __DIR__ . '/notification-dispatch.php';

function ensure_broadcasts_table(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS broadcasts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(32) NOT NULL DEFAULT "info",
            audience VARCHAR(32) NOT NULL DEFAULT "all",
            target_user_id INT UNSIGNED DEFAULT NULL,
            recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
            email_sent TINYINT(1) NOT NULL DEFAULT 0,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created (created_at),
            INDEX idx_audience (audience)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $ready = true;
}

/** @return list<int> */
function broadcast_recipient_ids(PDO $pdo, string $audience, int $targetUserId = 0): array
{
    $audience = strtolower(trim($audience));
    if ($audience === 'user' && $targetUserId > 0) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$targetUserId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    $roleSql = match ($audience) {
        'lawyers' => "role = 'lawyer'",
        'clients' => "role = 'client'",
        'staff' => "role IN ('admin','staff')",
        default => '1=1',
    };

    return array_map(
        'intval',
        $pdo->query('SELECT id FROM users WHERE is_active = 1 AND ' . $roleSql . ' ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)
    );
}

function broadcast_audience_label(string $audience): string
{
    return match (strtolower($audience)) {
        'lawyers' => __('notifications.recipient.all_lawyers'),
        'clients' => __('notifications.recipient.all_clients'),
        'staff' => __('notifications.recipient.all_staff'),
        'user' => __('notifications.recipient.single'),
        default => __('notifications.recipient.all'),
    };
}

/**
 * Send a broadcast announcement to a role group or single user.
 *
 * @return array{count:int, broadcast_id:int}
 */
function send_broadcast(
    PDO $pdo,
    string $title,
    string $message,
    string $type,
    string $audience,
    int $targetUserId,
    bool $sendEmail,
    int $createdBy
): array {
    ensure_broadcasts_table($pdo);
    $title = trim($title);
    $message = trim($message);
    if ($title === '' || $message === '') {
        return ['count' => 0, 'broadcast_id' => 0];
    }

    $recipientIds = broadcast_recipient_ids($pdo, $audience, $targetUserId);
    $recipientIds = array_values(array_unique(array_filter($recipientIds, static fn(int $id): bool => $id > 0)));

    foreach ($recipientIds as $uid) {
        create_notification($pdo, $uid, $title, $message, $type ?: 'info', null, $createdBy);
        if ($sendEmail) {
            send_user_email($pdo, $uid, $title, $message);
        }
    }

    $normalizedAudience = in_array($audience, ['lawyers', 'clients', 'staff', 'user'], true) ? $audience : 'all';
    $stmt = $pdo->prepare(
        'INSERT INTO broadcasts (title, message, type, audience, target_user_id, recipient_count, email_sent, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $title,
        $message,
        $type ?: 'info',
        $normalizedAudience,
        $targetUserId > 0 ? $targetUserId : null,
        count($recipientIds),
        $sendEmail ? 1 : 0,
        $createdBy > 0 ? $createdBy : null,
    ]);

    return ['count' => count($recipientIds), 'broadcast_id' => (int) $pdo->lastInsertId()];
}

/** @return list<array<string,mixed>> */
function broadcasts_recent(PDO $pdo, int $limit = 15): array
{
    ensure_broadcasts_table($pdo);
    $limit = max(1, min(50, $limit));
    $stmt = $pdo->prepare(
        'SELECT b.*, CONCAT(u.first_name, " ", u.last_name) AS sender_name
         FROM broadcasts b
         LEFT JOIN users u ON u.id = b.created_by
         ORDER BY b.created_at DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll() ?: [];
}
