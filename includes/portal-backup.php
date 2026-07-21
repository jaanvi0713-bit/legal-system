<?php

function portal_backup_frequency_key(int $userId): string
{
    return 'user_backup_frequency_' . $userId;
}

function portal_backup_frequency(PDO $pdo, int $userId): string
{
    $frequency = strtolower((string) get_setting($pdo, portal_backup_frequency_key($userId), 'never'));
    return in_array($frequency, ['never', 'weekly', 'monthly'], true) ? $frequency : 'never';
}

function portal_backup_user_profile(array $user): array
{
    $fields = [
        'id', 'username', 'email', 'role', 'first_name', 'last_name', 'phone', 'address',
        'specialization', 'company_name', 'availability', 'notes', 'created_at', 'updated_at',
    ];
    $profile = [];
    foreach ($fields as $field) {
        if (array_key_exists($field, $user)) {
            $profile[$field] = $user[$field];
        }
    }

    return $profile;
}

function portal_backup_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function portal_backup_count(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function portal_backup_payload_build(PDO $pdo, array $user, string $portal): array
{
    $uid = (int) ($user['id'] ?? 0);
    $company = (string) get_setting($pdo, 'company_name', app_config('name', 'LEGAL PRO'));

    if ($portal === 'lawyer') {
        $accessSql = lawyer_case_access_sql('c');
        $caseIds = array_map(
            static fn(array $row): int => (int) ($row['id'] ?? 0),
            portal_backup_rows($pdo, "SELECT c.id FROM cases c WHERE $accessSql ORDER BY c.id", [$uid, $uid])
        );
        $caseIdClause = $caseIds ? implode(',', array_map('intval', $caseIds)) : '0';

        $cases = portal_backup_rows(
            $pdo,
            'SELECT c.*, CONCAT(cl.first_name, " ", cl.last_name) AS client_name, cl.email AS client_email
             FROM cases c
             JOIN users cl ON cl.id = c.client_id
             WHERE ' . $accessSql . '
             ORDER BY c.updated_at DESC',
            [$uid, $uid]
        );
        $notes = $caseIds
            ? portal_backup_rows($pdo, 'SELECT * FROM case_notes WHERE case_id IN (' . $caseIdClause . ') ORDER BY created_at DESC')
            : [];
        $documents = $caseIds
            ? portal_backup_rows(
                $pdo,
                'SELECT id, case_id, client_id, uploaded_by, title, file_name, file_path, file_type, file_size, category, description, created_at
                 FROM case_documents WHERE case_id IN (' . $caseIdClause . ') ORDER BY created_at DESC'
            )
            : [];
        $appointments = portal_backup_rows(
            $pdo,
            'SELECT a.*, CONCAT(c.first_name, " ", c.last_name) AS client_name
             FROM appointments a
             LEFT JOIN users c ON c.id = a.client_id
             WHERE a.lawyer_id = ?
             ORDER BY a.scheduled_at DESC',
            [$uid]
        );
        $hearings = $caseIds
            ? portal_backup_rows(
                $pdo,
                'SELECT h.*, c.case_number
                 FROM court_hearings h
                 JOIN cases c ON c.id = h.case_id
                 WHERE h.case_id IN (' . $caseIdClause . ')
                 ORDER BY h.hearing_date DESC'
            )
            : [];
        $clients = portal_backup_rows(
            $pdo,
            'SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.phone, u.company_name, u.address
             FROM users u
             WHERE u.role = "client"
               AND (u.assigned_lawyer_id = ? OR u.id IN (SELECT client_id FROM cases c2 WHERE ' . lawyer_case_access_sql('c2') . '))
             ORDER BY u.first_name, u.last_name',
            [$uid, $uid, $uid]
        );
        $messages = portal_backup_rows(
            $pdo,
            'SELECT m.*, CONCAT(u.first_name, " ", u.last_name) AS sender_name
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.sender_id = ? OR m.receiver_id = ?
             ORDER BY m.created_at DESC',
            [$uid, $uid]
        );
        $notifications = portal_backup_rows(
            $pdo,
            'SELECT id, title, message, type, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC',
            [$uid]
        );
        $availability = portal_backup_rows(
            $pdo,
            'SELECT * FROM lawyer_availability_slots WHERE lawyer_id = ? ORDER BY week_start, day_of_week, slot_time',
            [$uid]
        );

        return [
            'meta' => [
                'generated_at' => gmdate('c'),
                'portal' => 'lawyer',
                'company' => $company,
                'workspace_url' => (string) app_config('url', ''),
                'version' => 1,
            ],
            'profile' => portal_backup_user_profile($user),
            'counts' => [
                'cases' => count($cases),
                'clients' => count($clients),
                'appointments' => count($appointments),
                'documents' => count($documents),
                'hearings' => count($hearings),
                'messages' => count($messages),
                'notifications' => count($notifications),
                'availability_slots' => count($availability),
            ],
            'data' => [
                'cases' => $cases,
                'case_notes' => $notes,
                'documents' => $documents,
                'appointments' => $appointments,
                'court_hearings' => $hearings,
                'clients' => $clients,
                'messages' => $messages,
                'notifications' => $notifications,
                'availability_slots' => $availability,
            ],
        ];
    }

    $caseIds = array_map(
        static fn(array $row): int => (int) ($row['id'] ?? 0),
        portal_backup_rows($pdo, 'SELECT id FROM cases WHERE client_id = ? ORDER BY id', [$uid])
    );
    $caseIdClause = $caseIds ? implode(',', array_map('intval', $caseIds)) : '0';

    $cases = portal_backup_rows(
        $pdo,
        'SELECT c.*, CONCAT(l.first_name, " ", l.last_name) AS lawyer_name, l.email AS lawyer_email
         FROM cases c
         LEFT JOIN users l ON l.id = c.lawyer_id
         WHERE c.client_id = ?
         ORDER BY c.updated_at DESC',
        [$uid]
    );
    $notes = $caseIds
        ? portal_backup_rows(
            $pdo,
            'SELECT n.*, CONCAT(u.first_name, " ", u.last_name) AS author
             FROM case_notes n
             JOIN users u ON u.id = n.user_id
             WHERE n.case_id IN (' . $caseIdClause . ') AND n.is_private = 0
             ORDER BY n.created_at DESC'
        )
        : [];
    $documents = portal_backup_rows(
        $pdo,
        'SELECT id, case_id, client_id, uploaded_by, title, file_name, file_path, file_type, file_size, category, description, created_at
         FROM case_documents WHERE client_id = ? ORDER BY created_at DESC',
        [$uid]
    );
    $appointments = portal_backup_rows(
        $pdo,
        'SELECT a.*, CONCAT(l.first_name, " ", l.last_name) AS lawyer_name
         FROM appointments a
         LEFT JOIN users l ON l.id = a.lawyer_id
         WHERE a.client_id = ?
         ORDER BY a.scheduled_at DESC',
        [$uid]
    );
    $hearings = $caseIds
        ? portal_backup_rows(
            $pdo,
            'SELECT h.*, c.case_number
             FROM court_hearings h
             JOIN cases c ON c.id = h.case_id
             WHERE h.case_id IN (' . $caseIdClause . ')
             ORDER BY h.hearing_date DESC'
        )
        : [];
    $invoices = portal_backup_rows(
        $pdo,
        'SELECT * FROM invoices WHERE client_id = ? ORDER BY created_at DESC',
        [$uid]
    );
    $payments = portal_backup_rows(
        $pdo,
        'SELECT * FROM payments WHERE client_id = ? ORDER BY paid_at DESC, created_at DESC',
        [$uid]
    );
    $messages = portal_backup_rows(
        $pdo,
        'SELECT m.*, CONCAT(u.first_name, " ", u.last_name) AS sender_name
         FROM messages m
         JOIN users u ON u.id = m.sender_id
         WHERE m.sender_id = ? OR m.receiver_id = ?
         ORDER BY m.created_at DESC',
        [$uid, $uid]
    );
    $notifications = portal_backup_rows(
        $pdo,
        'SELECT id, title, message, type, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC',
        [$uid]
    );

    return [
        'meta' => [
            'generated_at' => gmdate('c'),
            'portal' => 'client',
            'company' => $company,
            'workspace_url' => (string) app_config('url', ''),
            'version' => 1,
        ],
        'profile' => portal_backup_user_profile($user),
        'counts' => [
            'cases' => count($cases),
            'documents' => count($documents),
            'appointments' => count($appointments),
            'invoices' => count($invoices),
            'payments' => count($payments),
            'hearings' => count($hearings),
            'messages' => count($messages),
            'notifications' => count($notifications),
        ],
        'data' => [
            'cases' => $cases,
            'case_notes' => $notes,
            'documents' => $documents,
            'appointments' => $appointments,
            'court_hearings' => $hearings,
            'invoices' => $invoices,
            'payments' => $payments,
            'messages' => $messages,
            'notifications' => $notifications,
        ],
    ];
}

function portal_backup_payload_json(PDO $pdo, array $user, string $portal): string
{
    return json_encode(
        portal_backup_payload_build($pdo, $user, $portal),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?: '{}';
}

function portal_backup_handle_post(PDO $pdo, array $user, string $portal, string $redirectUrl): void
{
    verify_csrf();
    $action = post('backup_action', '');

    if ($action === 'download') {
        $json = portal_backup_payload_json($pdo, $user, $portal);
        $filename = $portal . '-backup-' . date('Y-m-d-His') . '.json';
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }

    if ($action === 'email') {
        $to = trim((string) ($user['email'] ?? ''));
        if ($to === '') {
            flash('error', __('settings.backup.portal.email_missing'));
        } else {
            $subjectKey = $portal === 'lawyer'
                ? 'settings.backup.portal.email_subject_lawyer'
                : 'settings.backup.portal.email_subject_client';
            $subject = __($subjectKey) . ' · ' . date('Y-m-d H:i');
            $body = portal_backup_payload_json($pdo, $user, $portal);
            $ok = @mail($to, $subject, $body, "Content-Type: text/plain; charset=UTF-8\r\n");
            if ($ok) {
                flash('success', __('settings.backup.emailed', ['email' => $to]));
            } else {
                flash('error', __('settings.backup.email_failed'));
            }
        }
    } elseif ($action === 'schedule') {
        $frequency = strtolower(post('backup_frequency', 'never'));
        if (!in_array($frequency, ['never', 'weekly', 'monthly'], true)) {
            $frequency = 'never';
        }
        set_setting($pdo, portal_backup_frequency_key((int) $user['id']), $frequency);
        flash('success', __('settings.backup.schedule_saved'));
    }

    redirect($redirectUrl);
}
