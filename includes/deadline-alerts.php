<?php
/**
 * Automated deadline reminders for hearings and case tasks.
 */

require_once __DIR__ . '/notification-dispatch.php';
require_once __DIR__ . '/case-team.php';

function ensure_deadline_alert_log_table(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS deadline_alert_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_type ENUM("hearing","task") NOT NULL,
            entity_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            days_before SMALLINT NOT NULL,
            channel ENUM("inapp","email") NOT NULL DEFAULT "inapp",
            sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_deadline_alert (entity_type, entity_id, user_id, days_before, channel),
            INDEX idx_entity (entity_type, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $ready = true;
}

function deadline_alerts_enabled(PDO $pdo): bool
{
    return get_setting($pdo, 'deadline_alerts_enabled', '1') === '1';
}

/** @return list<int> */
function deadline_alert_days(PDO $pdo): array
{
    $raw = (string) get_setting($pdo, 'deadline_alert_days', '7,3,1,0');
    $days = [];
    foreach (explode(',', $raw) as $part) {
        $part = trim($part);
        if ($part === '' || !is_numeric($part)) {
            continue;
        }
        $days[] = max(0, min(30, (int) $part));
    }
    $days = array_values(array_unique($days));
    sort($days);

    return $days ?: [7, 3, 1, 0];
}

function deadline_alerts_email_enabled(PDO $pdo): bool
{
    return get_setting($pdo, 'deadline_alerts_email', '1') === '1';
}

function deadline_alert_link(string $role, int $caseId, string $entityType): string
{
    $caseUrl = 'cases.php?action=view&id=' . $caseId;
    if ($entityType === 'hearing') {
        return match ($role) {
            'client' => 'court.php',
            default => $caseUrl . '&tab=deadlines',
        };
    }

    return match ($role) {
        'client' => $caseUrl,
        default => $caseUrl . '&tab=tasks',
    };
}

function deadline_alert_already_sent(PDO $pdo, string $entityType, int $entityId, int $userId, int $daysBefore, string $channel): bool
{
    ensure_deadline_alert_log_table($pdo);
    $stmt = $pdo->prepare(
        'SELECT 1 FROM deadline_alert_log
         WHERE entity_type = ? AND entity_id = ? AND user_id = ? AND days_before = ? AND channel = ?
         LIMIT 1'
    );
    $stmt->execute([$entityType, $entityId, $userId, $daysBefore, $channel]);

    return (bool) $stmt->fetchColumn();
}

function deadline_alert_mark_sent(PDO $pdo, string $entityType, int $entityId, int $userId, int $daysBefore, string $channel): void
{
    ensure_deadline_alert_log_table($pdo);
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO deadline_alert_log (entity_type, entity_id, user_id, days_before, channel)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$entityType, $entityId, $userId, $daysBefore, $channel]);
}

/** @return list<int> */
function deadline_alert_case_recipients(PDO $pdo, array $caseRow, ?int $assignedUserId = null): array
{
    $ids = [];
    $clientId = (int) ($caseRow['client_id'] ?? 0);
    $lawyerId = (int) ($caseRow['lawyer_id'] ?? 0);
    if ($clientId > 0) {
        $ids[] = $clientId;
    }
    if ($lawyerId > 0) {
        $ids[] = $lawyerId;
    }
    foreach (case_lawyer_ids($pdo, (int) ($caseRow['id'] ?? $caseRow['case_id'] ?? 0)) as $teamId) {
        if ($teamId > 0) {
            $ids[] = $teamId;
        }
    }
    if ($assignedUserId !== null && $assignedUserId > 0) {
        $ids[] = $assignedUserId;
    }

    $assignedAdminId = (int) ($caseRow['assigned_admin_id'] ?? 0);
    if ($assignedAdminId > 0) {
        $ids[] = $assignedAdminId;
    } else {
        foreach ($pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN) as $adminId) {
            $ids[] = (int) $adminId;
        }
    }

    return array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
}

function deadline_alert_days_label(int $daysBefore): string
{
    if ($daysBefore === 0) {
        return __('deadline.alert.today');
    }
    if ($daysBefore === 1) {
        return __('deadline.alert.tomorrow');
    }
    return __('deadline.alert.in_days', ['count' => $daysBefore]);
}

function deadline_alert_notify_user(
    PDO $pdo,
    int $userId,
    string $entityType,
    int $entityId,
    int $caseId,
    string $caseNumber,
    string $title,
    string $whenLabel,
    int $daysBefore,
    bool $sendEmail
): bool {
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$userId]);
    $role = (string) ($stmt->fetchColumn() ?: '');
    if ($role === '') {
        return false;
    }

    $channels = ['inapp'];
    if ($sendEmail) {
        $channels[] = 'email';
    }

    $sentAny = false;
    $link = deadline_alert_link($role, $caseId, $entityType);
    $alertTitle = $entityType === 'hearing' ? 'deadline.alert.hearing_title' : 'deadline.alert.task_title';
    $alertMessage = notify_payload('notify.msg.deadline_alert', [
        'when' => $whenLabel,
        'case' => $caseNumber,
        'title' => $title,
    ]);

    foreach ($channels as $channel) {
        if (deadline_alert_already_sent($pdo, $entityType, $entityId, $userId, $daysBefore, $channel)) {
            continue;
        }

        if ($channel === 'inapp') {
            dispatch_notification($pdo, $userId, $alertTitle, $alertMessage, 'reminder', $link, null, 'appointments', false);
        } else {
            $subject = __($alertTitle);
            $body = str_replace(
                [':when', ':case', ':title'],
                [$whenLabel, $caseNumber, $title],
                __('deadline.alert.email_body')
            );
            if (send_user_email($pdo, $userId, $subject, $body . "\n\n" . __('email.open_link') . ': ' . notification_absolute_url($link, rtrim((string) app_config('url'), '/')))) {
                deadline_alert_mark_sent($pdo, $entityType, $entityId, $userId, $daysBefore, $channel);
                $sentAny = true;
            }
            continue;
        }

        deadline_alert_mark_sent($pdo, $entityType, $entityId, $userId, $daysBefore, $channel);
        $sentAny = true;
    }

    return $sentAny;
}

/**
 * Scan upcoming hearings and tasks; send reminders.
 *
 * @return array{hearings:int,tasks:int,skipped:bool}
 */
function run_deadline_alerts(PDO $pdo): array
{
    if (!deadline_alerts_enabled($pdo)) {
        return ['hearings' => 0, 'tasks' => 0, 'skipped' => true];
    }

    ensure_deadline_alert_log_table($pdo);
    ensure_case_tasks_table($pdo);

    $daysList = deadline_alert_days($pdo);
    $sendEmail = deadline_alerts_email_enabled($pdo);
    $hearingCount = 0;
    $taskCount = 0;
    $tz = app_config('timezone', 'UTC');

    foreach ($daysList as $daysBefore) {
        $targetDate = (new DateTimeImmutable('now', new DateTimeZone($tz)))
            ->modify('+' . $daysBefore . ' days')
            ->format('Y-m-d');

        $hearingStmt = $pdo->prepare(
            'SELECT h.*, c.case_number, c.title AS case_title, c.client_id, c.lawyer_id, c.assigned_admin_id
             FROM court_hearings h
             JOIN cases c ON c.id = h.case_id
             WHERE h.status = "scheduled"
               AND DATE(h.hearing_date) = ?
               AND c.status != "closed"'
        );
        $hearingStmt->execute([$targetDate]);
        foreach ($hearingStmt->fetchAll() as $hearing) {
            $whenLabel = deadline_alert_days_label($daysBefore);
            $hearingTitle = trim((string) (($hearing['hearing_type'] ?? '') ?: ($hearing['court_name'] ?? __('cases.tab.deadlines'))));
            $recipients = deadline_alert_case_recipients($pdo, $hearing);
            foreach ($recipients as $userId) {
                if (deadline_alert_notify_user(
                    $pdo,
                    $userId,
                    'hearing',
                    (int) $hearing['id'],
                    (int) $hearing['case_id'],
                    (string) $hearing['case_number'],
                    $hearingTitle,
                    $whenLabel,
                    $daysBefore,
                    $sendEmail
                )) {
                    $hearingCount++;
                }
            }
        }

        $taskStmt = $pdo->prepare(
            'SELECT t.*, c.case_number, c.title AS case_title, c.client_id, c.lawyer_id, c.assigned_admin_id
             FROM case_tasks t
             JOIN cases c ON c.id = t.case_id
             WHERE t.status IN ("open","in_progress")
               AND t.due_date IS NOT NULL
               AND t.due_date = ?
               AND c.status != "closed"'
        );
        $taskStmt->execute([$targetDate]);
        foreach ($taskStmt->fetchAll() as $task) {
            $whenLabel = deadline_alert_days_label($daysBefore);
            $recipients = deadline_alert_case_recipients($pdo, $task, (int) ($task['assigned_to'] ?? 0));
            foreach ($recipients as $userId) {
                if (deadline_alert_notify_user(
                    $pdo,
                    $userId,
                    'task',
                    (int) $task['id'],
                    (int) $task['case_id'],
                    (string) $task['case_number'],
                    (string) ($task['title'] ?? ''),
                    $whenLabel,
                    $daysBefore,
                    $sendEmail
                )) {
                    $taskCount++;
                }
            }
        }
    }

    set_setting($pdo, 'deadline_alerts_last_run', date('Y-m-d H:i:s'));

    return ['hearings' => $hearingCount, 'tasks' => $taskCount, 'skipped' => false];
}

/**
 * Run at most once per hour (for environments without cron).
 */
function deadline_alerts_maybe_run(PDO $pdo): void
{
    if (!deadline_alerts_enabled($pdo)) {
        return;
    }
    $last = (string) get_setting($pdo, 'deadline_alerts_last_run', '');
    if ($last !== '') {
        $lastTs = strtotime($last);
        if ($lastTs && (time() - $lastTs) < 3600) {
            return;
        }
    }
    run_deadline_alerts($pdo);
}
