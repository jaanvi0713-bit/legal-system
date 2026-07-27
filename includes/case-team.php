<?php
/**
 * Multi-lawyer case teams and assignable case tasks.
 */

function ensure_case_lawyers_table(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS case_lawyers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            case_id INT UNSIGNED NOT NULL,
            lawyer_id INT UNSIGNED NOT NULL,
            role ENUM("lead","associate") NOT NULL DEFAULT "associate",
            assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            assigned_by INT UNSIGNED DEFAULT NULL,
            UNIQUE KEY uniq_case_lawyer (case_id, lawyer_id),
            INDEX idx_lawyer (lawyer_id),
            CONSTRAINT fk_case_lawyers_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
            CONSTRAINT fk_case_lawyers_lawyer FOREIGN KEY (lawyer_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $pdo->exec(
        'INSERT IGNORE INTO case_lawyers (case_id, lawyer_id, role)
         SELECT id, lawyer_id, "lead" FROM cases WHERE lawyer_id IS NOT NULL'
    );
    $ready = true;
}

function ensure_case_tasks_table(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    ensure_case_lawyers_table($pdo);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS case_tasks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            case_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            assigned_to INT UNSIGNED DEFAULT NULL,
            due_date DATE DEFAULT NULL,
            status ENUM("open","in_progress","done","cancelled") NOT NULL DEFAULT "open",
            created_by INT UNSIGNED DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_case (case_id),
            INDEX idx_assignee (assigned_to, status),
            CONSTRAINT fk_case_tasks_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
            CONSTRAINT fk_case_tasks_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $ready = true;
}

/** @return list<string> */
function case_task_statuses(): array
{
    return ['open', 'in_progress', 'done', 'cancelled'];
}

function lawyer_case_access_sql(string $caseAlias = 'c'): string
{
    return '(' . $caseAlias . '.lawyer_id = ? OR EXISTS (SELECT 1 FROM case_lawyers cl WHERE cl.case_id = ' . $caseAlias . '.id AND cl.lawyer_id = ?))';
}

function lawyer_can_access_case(PDO $pdo, int $lawyerId, int $caseId): bool
{
    if ($lawyerId <= 0 || $caseId <= 0) {
        return false;
    }
    ensure_case_lawyers_table($pdo);
    $stmt = $pdo->prepare('SELECT 1 FROM cases c WHERE c.id = ? AND ' . lawyer_case_access_sql('c') . ' LIMIT 1');
    $stmt->execute([$caseId, $lawyerId, $lawyerId]);

    return (bool) $stmt->fetchColumn();
}

function lawyer_has_case_access(PDO $pdo, int $caseId, int $lawyerId): bool
{
    return lawyer_can_access_case($pdo, $lawyerId, $caseId);
}

/** @return list<int> */
function case_lawyer_ids(PDO $pdo, int $caseId): array
{
    ensure_case_lawyers_table($pdo);
    $stmt = $pdo->prepare('SELECT lawyer_id FROM case_lawyers WHERE case_id = ? ORDER BY role = "lead" DESC, lawyer_id');
    $stmt->execute([$caseId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** @return list<array{id:int,lawyer_id:int,role:string,first_name:string,last_name:string,email:?string}> */
function case_lawyers_for_case(PDO $pdo, int $caseId): array
{
    ensure_case_lawyers_table($pdo);
    $stmt = $pdo->prepare(
        'SELECT cl.id, cl.lawyer_id, cl.role, u.first_name, u.last_name, u.email
         FROM case_lawyers cl
         JOIN users u ON u.id = cl.lawyer_id
         WHERE cl.case_id = ?
         ORDER BY cl.role = "lead" DESC, u.first_name, u.last_name'
    );
    $stmt->execute([$caseId]);

    return $stmt->fetchAll() ?: [];
}

function case_lawyers_label(PDO $pdo, int $caseId): string
{
    $rows = case_lawyers_for_case($pdo, $caseId);
    if (!$rows) {
        return '';
    }
    $parts = [];
    foreach ($rows as $row) {
        $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        if ($row['role'] === 'lead') {
            $name .= ' (' . __('cases.team.lead') . ')';
        }
        $parts[] = $name;
    }

    return implode(', ', $parts);
}

/**
 * @param list<int> $associateIds
 */
function sync_case_lawyers(PDO $pdo, int $caseId, ?int $leadLawyerId, array $associateIds, ?int $assignedBy = null): void
{
    ensure_case_lawyers_table($pdo);
    $leadLawyerId = $leadLawyerId && $leadLawyerId > 0 ? $leadLawyerId : null;
    $associateIds = array_values(array_unique(array_filter(array_map('intval', $associateIds), static fn(int $id): bool => $id > 0)));
    if ($leadLawyerId) {
        $associateIds = array_values(array_filter($associateIds, static fn(int $id): bool => $id !== $leadLawyerId));
    }

    $pdo->prepare('DELETE FROM case_lawyers WHERE case_id = ?')->execute([$caseId]);
    $ins = $pdo->prepare('INSERT INTO case_lawyers (case_id, lawyer_id, role, assigned_by) VALUES (?,?,?,?)');
    if ($leadLawyerId) {
        $ins->execute([$caseId, $leadLawyerId, 'lead', $assignedBy]);
    }
    foreach ($associateIds as $associateId) {
        $ins->execute([$caseId, $associateId, 'associate', $assignedBy]);
    }
    $pdo->prepare('UPDATE cases SET lawyer_id = ? WHERE id = ?')->execute([$leadLawyerId, $caseId]);
}

/** Cases this lawyer is not already on (lead or associate), excluding closed matters. */
function cases_assignable_to_lawyer(PDO $pdo, int $lawyerId): array
{
    ensure_case_lawyers_table($pdo);
    if ($lawyerId <= 0) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT c.id, c.case_number, c.title
         FROM cases c
         WHERE c.status <> "closed"
           AND NOT (' . lawyer_case_access_sql('c') . ')
         ORDER BY c.created_at DESC'
    );
    $stmt->execute([$lawyerId, $lawyerId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return 'lead'|'associate'|null Role on success, null if already on the team or invalid. */
function assign_case_to_lawyer(PDO $pdo, int $caseId, int $lawyerId, ?int $assignedBy = null, string $role = 'associate'): ?string
{
    ensure_case_lawyers_table($pdo);
    if ($caseId <= 0 || $lawyerId <= 0) {
        return null;
    }
    if (lawyer_can_access_case($pdo, $lawyerId, $caseId)) {
        return null;
    }

    $role = $role === 'lead' ? 'lead' : 'associate';

    $stmt = $pdo->prepare('SELECT id, lawyer_id FROM cases WHERE id = ? LIMIT 1');
    $stmt->execute([$caseId]);
    $case = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$case) {
        return null;
    }

    $leadId = null;
    $associates = [];
    foreach (case_lawyers_for_case($pdo, $caseId) as $member) {
        $memberId = (int) $member['lawyer_id'];
        if (($member['role'] ?? '') === 'lead') {
            $leadId = $memberId;
        } else {
            $associates[] = $memberId;
        }
    }

    if (!$leadId) {
        $leadId = (int) ($case['lawyer_id'] ?? 0) ?: null;
    }

    if ($role === 'lead') {
        if ($leadId && $leadId !== $lawyerId && !in_array($leadId, $associates, true)) {
            $associates[] = $leadId;
        }
        $associates = array_values(array_filter($associates, static fn(int $id): bool => $id !== $lawyerId));
        sync_case_lawyers($pdo, $caseId, $lawyerId, $associates, $assignedBy);

        return 'lead';
    }

    if (!$leadId) {
        sync_case_lawyers($pdo, $caseId, $lawyerId, [], $assignedBy);

        return 'lead';
    }

    $associates[] = $lawyerId;
    sync_case_lawyers($pdo, $caseId, $leadId, $associates, $assignedBy);

    return 'associate';
}

function case_team_role_badge(string $role): string
{
    $role = $role === 'lead' ? 'lead' : 'associate';
    $class = $role === 'lead' ? 'badge-success' : 'badge-info';

    return '<span class="badge ' . $class . ' badge-st-' . e($role) . '">' . e(__('cases.team.' . $role)) . '</span>';
}

/** @param list<int>|null $onlyLawyerIds */
function notify_case_team(
    PDO $pdo,
    int $caseId,
    string $title,
    string $message,
    string $type = 'case',
    ?string $link = null,
    ?int $createdBy = null,
    ?array $onlyLawyerIds = null
): void {
    $lawyerIds = $onlyLawyerIds ?? case_lawyer_ids($pdo, $caseId);
    foreach ($lawyerIds as $lawyerId) {
        if ($lawyerId > 0) {
            create_notification($pdo, $lawyerId, $title, $message, $type, $link, $createdBy);
        }
    }
}

/** @return list<array<string,mixed>> */
function case_tasks_for_case(PDO $pdo, int $caseId, ?string $status = null): array
{
    ensure_case_tasks_table($pdo);
    $sql = 'SELECT t.*, CONCAT(u.first_name," ",u.last_name) AS assignee_name,
                   CONCAT(cb.first_name," ",cb.last_name) AS created_by_name
            FROM case_tasks t
            LEFT JOIN users u ON u.id = t.assigned_to
            LEFT JOIN users cb ON cb.id = t.created_by
            WHERE t.case_id = ?';
    $params = [$caseId];
    if ($status !== null && $status !== '') {
        $sql .= ' AND t.status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY FIELD(t.status, "open","in_progress","done","cancelled"), t.due_date IS NULL, t.due_date ASC, t.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll() ?: [];
}

/** @param list<string>|null $statuses */
function case_tasks_for_lawyer(PDO $pdo, int $lawyerId, ?array $statuses = null): array
{
    ensure_case_tasks_table($pdo);
    $statuses = $statuses ?? ['open', 'in_progress'];
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $sql = 'SELECT t.*, c.case_number, c.title AS case_title
            FROM case_tasks t
            JOIN cases c ON c.id = t.case_id
            WHERE t.assigned_to = ?
              AND t.status IN (' . $placeholders . ')
            ORDER BY t.due_date IS NULL, t.due_date ASC, t.created_at DESC';
    $params = array_merge([$lawyerId], $statuses);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll() ?: [];
}

function case_task_open_count_for_lawyer(PDO $pdo, int $lawyerId): int
{
    ensure_case_tasks_table($pdo);
    $statuses = ['open', 'in_progress'];
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $sql = 'SELECT COUNT(*)
            FROM case_tasks t
            WHERE t.assigned_to = ?
              AND t.status IN (' . $placeholders . ')';
    $params = array_merge([$lawyerId], $statuses);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function case_task_by_id(PDO $pdo, int $taskId): ?array
{
    ensure_case_tasks_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM case_tasks WHERE id = ?');
    $stmt->execute([$taskId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function lawyer_can_update_case_task(PDO $pdo, int $taskId, int $lawyerId): bool
{
    $task = case_task_by_id($pdo, $taskId);

    return $task && (int) ($task['assigned_to'] ?? 0) === $lawyerId;
}

/**
 * @return array{ok:bool,task_id?:int,error?:string}
 */
function save_case_task(PDO $pdo, int $caseId, array $data, ?int $actorId = null): array
{
    ensure_case_tasks_table($pdo);
    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '') {
        return ['ok' => false, 'error' => __('cases.tasks.error.title_required')];
    }

    $assignedTo = isset($data['assigned_to']) && $data['assigned_to'] !== '' && $data['assigned_to'] !== null
        ? (int) $data['assigned_to']
        : null;
    $teamIds = case_lawyer_ids($pdo, $caseId);
    if ($assignedTo !== null && !in_array($assignedTo, $teamIds, true)) {
        return ['ok' => false, 'error' => __('cases.tasks.error.assignee_not_on_team')];
    }

    $status = (string) ($data['status'] ?? 'open');
    if (!in_array($status, case_task_statuses(), true)) {
        $status = 'open';
    }
    $description = trim((string) ($data['description'] ?? ''));
    $dueDate = trim((string) ($data['due_date'] ?? ''));
    $dueDate = $dueDate !== '' ? $dueDate : null;
    $taskId = (int) ($data['id'] ?? 0);
    $completedAt = $status === 'done' ? date('Y-m-d H:i:s') : null;
    $prevAssignee = 0;

    if ($taskId > 0) {
        $existing = case_task_by_id($pdo, $taskId);
        if (!$existing || (int) $existing['case_id'] !== $caseId) {
            return ['ok' => false, 'error' => __('cases.tasks.error.not_found')];
        }
        $prevAssignee = (int) ($existing['assigned_to'] ?? 0);
        if ($status !== 'done' && ($existing['status'] ?? '') === 'done') {
            $completedAt = null;
        } elseif ($status === 'done' && !empty($existing['completed_at'])) {
            $completedAt = $existing['completed_at'];
        }
        $pdo->prepare(
            'UPDATE case_tasks SET title=?, description=?, assigned_to=?, due_date=?, status=?, completed_at=? WHERE id=? AND case_id=?'
        )->execute([$title, $description ?: null, $assignedTo, $dueDate, $status, $completedAt, $taskId, $caseId]);
    } else {
        $pdo->prepare(
            'INSERT INTO case_tasks (case_id, title, description, assigned_to, due_date, status, created_by, completed_at)
             VALUES (?,?,?,?,?,?,?,?)'
        )->execute([$caseId, $title, $description ?: null, $assignedTo, $dueDate, $status, $actorId, $completedAt]);
        $taskId = (int) $pdo->lastInsertId();
    }

    if ($assignedTo && $assignedTo !== $prevAssignee) {
        $caseStmt = $pdo->prepare('SELECT case_number, title FROM cases WHERE id = ?');
        $caseStmt->execute([$caseId]);
        $caseRow = $caseStmt->fetch() ?: [];
        $caseLabel = (string) ($caseRow['case_number'] ?? ('#' . $caseId));
        create_notification(
            $pdo,
            $assignedTo,
            __('cases.tasks.notify.assigned_title'),
            __('cases.tasks.notify.assigned_message', ['case' => $caseLabel, 'task' => $title]),
            'case',
            '../lawyer/tasks.php',
            $actorId
        );
    }

    return ['ok' => true, 'task_id' => $taskId];
}

function delete_case_task(PDO $pdo, int $caseId, int $taskId): bool
{
    ensure_case_tasks_table($pdo);
    $stmt = $pdo->prepare('DELETE FROM case_tasks WHERE id = ? AND case_id = ?');

    return $stmt->execute([$taskId, $caseId]);
}

/**
 * @return array{ok:bool,error?:string}
 */
function update_case_task_status_for_lawyer(PDO $pdo, int $taskId, int $lawyerId, string $status): array
{
    if (!in_array($status, ['in_progress', 'done'], true)) {
        return ['ok' => false, 'error' => __('cases.tasks.error.invalid_status')];
    }
    if (!lawyer_can_update_case_task($pdo, $taskId, $lawyerId)) {
        return ['ok' => false, 'error' => __('cases.tasks.error.not_assigned')];
    }
    $completedAt = $status === 'done' ? date('Y-m-d H:i:s') : null;
    $pdo->prepare('UPDATE case_tasks SET status=?, completed_at=? WHERE id=? AND assigned_to=?')
        ->execute([$status, $completedAt, $taskId, $lawyerId]);

    return ['ok' => true];
}

function ensure_case_checklist_table(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS case_checklist_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            case_id INT UNSIGNED NOT NULL,
            item_key VARCHAR(50) DEFAULT NULL,
            label VARCHAR(255) NOT NULL,
            is_done TINYINT(1) NOT NULL DEFAULT 0,
            is_manual TINYINT(1) NOT NULL DEFAULT 0,
            is_dismissed TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_case_checklist_case (case_id),
            CONSTRAINT fk_case_checklist_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $col = $pdo->query("SHOW COLUMNS FROM case_checklist_items LIKE 'is_dismissed'")->fetch();
    if (!$col) {
        $pdo->exec('ALTER TABLE case_checklist_items ADD COLUMN is_dismissed TINYINT(1) NOT NULL DEFAULT 0 AFTER is_manual');
    }
    $ready = true;
}

/**
 * @return array<string, string>
 */
function case_checklist_builtin_labels(): array
{
    return [
        'client_details' => __('cases.checklist.client_details'),
        'team_assigned' => __('cases.checklist.team_assigned'),
        'description' => __('cases.checklist.description'),
        'document' => __('cases.checklist.document'),
        'invoice' => __('cases.checklist.invoice'),
        'payment' => __('cases.checklist.payment'),
        'hearing' => __('cases.checklist.hearing'),
    ];
}

/**
 * @param array<string, mixed> $context
 * @return array<string, bool>
 */
function case_checklist_auto_state(array $context): array
{
    return [
        'client_details' => !empty($context['client_email']),
        'team_assigned' => ((int) ($context['team_count'] ?? 0)) > 0 || !empty($context['lawyer_id']),
        'description' => trim((string) ($context['description'] ?? '')) !== '',
        'document' => ((int) ($context['document_count'] ?? 0)) > 0,
        'invoice' => ((int) ($context['invoice_count'] ?? 0)) > 0,
        'payment' => ((int) ($context['payment_count'] ?? 0)) > 0,
        'hearing' => !empty($context['next_hearing_date']) || ((int) ($context['hearing_count'] ?? 0)) > 0,
    ];
}

/**
 * @return array<string, mixed>
 */
function case_checklist_context_for_case(PDO $pdo, int $caseId): array
{
    ensure_case_lawyers_table($pdo);
    $stmt = $pdo->prepare(
        'SELECT c.description, c.lawyer_id, c.next_hearing_date, cl.email AS client_email
         FROM cases c
         JOIN users cl ON cl.id = c.client_id
         WHERE c.id = ?'
    );
    $stmt->execute([$caseId]);
    $case = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $teamStmt = $pdo->prepare('SELECT COUNT(*) FROM case_lawyers WHERE case_id = ?');
    $teamStmt->execute([$caseId]);
    $teamCount = (int) $teamStmt->fetchColumn();
    if ($teamCount === 0 && !empty($case['lawyer_id'])) {
        $teamCount = 1;
    }

    $docCount = $pdo->prepare('SELECT COUNT(*) FROM case_documents WHERE case_id = ?');
    $docCount->execute([$caseId]);

    $invCount = $pdo->prepare('SELECT COUNT(*) FROM invoices WHERE case_id = ?');
    $invCount->execute([$caseId]);

    $payCount = $pdo->prepare(
        'SELECT COUNT(*) FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE i.case_id = ?'
    );
    $payCount->execute([$caseId]);

    $hearingCount = $pdo->prepare('SELECT COUNT(*) FROM court_hearings WHERE case_id = ?');
    $hearingCount->execute([$caseId]);

    return [
        'client_email' => $case['client_email'] ?? '',
        'lawyer_id' => $case['lawyer_id'] ?? null,
        'team_count' => $teamCount,
        'description' => $case['description'] ?? '',
        'document_count' => (int) $docCount->fetchColumn(),
        'invoice_count' => (int) $invCount->fetchColumn(),
        'payment_count' => (int) $payCount->fetchColumn(),
        'hearing_count' => (int) $hearingCount->fetchColumn(),
        'next_hearing_date' => $case['next_hearing_date'] ?? null,
    ];
}

/**
 * @param array<string, mixed> $context
 */
function sync_case_checklist_auto_items(PDO $pdo, int $caseId, array $context): void
{
    ensure_case_checklist_table($pdo);
    seed_case_checklist_if_empty($pdo, $caseId, $context);

    $auto = case_checklist_auto_state($context);
    $builtin = case_checklist_builtin_labels();

    $heal = $pdo->prepare(
        'UPDATE case_checklist_items SET is_manual = 0
         WHERE case_id = ? AND item_key = ? AND is_manual = 1 AND is_done = ?'
    );
    foreach ($auto as $key => $autoDone) {
        $heal->execute([$caseId, $key, $autoDone ? 1 : 0]);
    }

    $existing = $pdo->prepare('SELECT id, item_key, is_manual, is_dismissed FROM case_checklist_items WHERE case_id = ?');
    $existing->execute([$caseId]);
    $byKey = [];
    foreach ($existing->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!empty($row['item_key'])) {
            $byKey[(string) $row['item_key']] = $row;
        }
    }

    $ins = $pdo->prepare(
        'INSERT INTO case_checklist_items (case_id, item_key, label, is_done, is_manual, is_dismissed, sort_order)
         VALUES (?,?,?,?,0,0,?)'
    );
    $updAuto = $pdo->prepare(
        'UPDATE case_checklist_items SET is_done = ?, label = ? WHERE id = ? AND case_id = ? AND is_manual = 0 AND is_dismissed = 0'
    );

    $order = 0;
    foreach ($builtin as $key => $label) {
        $done = !empty($auto[$key]) ? 1 : 0;
        if (!isset($byKey[$key])) {
            $ins->execute([$caseId, $key, $label, $done, $order]);
            continue;
        }
        $row = $byKey[$key];
        if ((int) ($row['is_dismissed'] ?? 0) === 1) {
            continue;
        }
        if ((int) ($row['is_manual'] ?? 0) === 0) {
            $updAuto->execute([$done, $label, (int) $row['id'], $caseId]);
        }
        $order++;
    }
}

/**
 * @param array<string, mixed> $context
 */
function seed_case_checklist_if_empty(PDO $pdo, int $caseId, array $context): void
{
    ensure_case_checklist_table($pdo);
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM case_checklist_items WHERE case_id = ?');
    $countStmt->execute([$caseId]);
    if ((int) $countStmt->fetchColumn() > 0) {
        return;
    }

    $auto = case_checklist_auto_state($context);
    $ins = $pdo->prepare(
        'INSERT INTO case_checklist_items (case_id, item_key, label, is_done, is_manual, sort_order)
         VALUES (?,?,?,?,0,?)'
    );
    $order = 0;
    foreach (case_checklist_builtin_labels() as $key => $label) {
        $ins->execute([$caseId, $key, $label, !empty($auto[$key]) ? 1 : 0, $order++]);
    }
}

/**
 * @param array<string, mixed> $context
 * @return list<array{id:int,item_key:?string,label:string,is_done:bool,is_manual:bool,sort_order:int}>
 */
function case_checklist_items(PDO $pdo, int $caseId, array $context): array
{
    ensure_case_checklist_table($pdo);
    sync_case_checklist_auto_items($pdo, $caseId, $context);

    $auto = case_checklist_auto_state($context);
    $stmt = $pdo->prepare('SELECT * FROM case_checklist_items WHERE case_id = ? AND is_dismissed = 0 ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$caseId]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = $row['item_key'] !== null && $row['item_key'] !== '' ? (string) $row['item_key'] : null;
        $isManual = (int) ($row['is_manual'] ?? 0) === 1;
        $isDone = (int) ($row['is_done'] ?? 0) === 1;
        if ($key && !$isManual && array_key_exists($key, $auto)) {
            $isDone = (bool) $auto[$key];
        }
        $label = $key ? (case_checklist_builtin_labels()[$key] ?? (string) $row['label']) : (string) $row['label'];
        $rows[] = [
            'id' => (int) $row['id'],
            'item_key' => $key,
            'label' => $label,
            'is_done' => $isDone,
            'is_manual' => $isManual,
            'sort_order' => (int) $row['sort_order'],
        ];
    }
    return $rows;
}

function save_case_checklist_from_post(PDO $pdo, int $caseId): void
{
    ensure_case_checklist_table($pdo);
    $context = case_checklist_context_for_case($pdo, $caseId);
    $auto = case_checklist_auto_state($context);
    $items = isset($_POST['checklist']) && is_array($_POST['checklist']) ? $_POST['checklist'] : [];
    $deleteIds = array_values(array_filter(array_map('intval', (array) post('checklist_delete', [])), static fn(int $id): bool => $id > 0));
    $newLabels = isset($_POST['checklist_new']) && is_array($_POST['checklist_new']) ? $_POST['checklist_new'] : [];
    $pendingLabel = trim((string) post('checklist_pending', ''));
    if ($pendingLabel !== '') {
        $newLabels[] = ['label' => $pendingLabel, 'done' => 0];
    }

    if ($deleteIds) {
        $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
        $params = array_merge($deleteIds, [$caseId]);
        $pdo->prepare("UPDATE case_checklist_items SET is_dismissed = 1 WHERE id IN ($placeholders) AND case_id = ? AND item_key IS NOT NULL")->execute($params);
        $pdo->prepare("DELETE FROM case_checklist_items WHERE id IN ($placeholders) AND case_id = ? AND item_key IS NULL")->execute($params);
    }

    $upd = $pdo->prepare('UPDATE case_checklist_items SET label = ?, is_done = ?, is_manual = ?, sort_order = ? WHERE id = ? AND case_id = ?');
    $fetch = $pdo->prepare('SELECT item_key FROM case_checklist_items WHERE id = ? AND case_id = ?');
    $builtin = case_checklist_builtin_labels();
    $order = 0;
    foreach ($items as $itemId => $row) {
        $itemId = (int) $itemId;
        if ($itemId < 1 || !is_array($row)) {
            continue;
        }
        $fetch->execute([$itemId, $caseId]);
        $itemKey = (string) ($fetch->fetchColumn() ?: '');
        $label = trim((string) ($row['label'] ?? ''));
        if ($itemKey !== '' && isset($builtin[$itemKey])) {
            $label = $builtin[$itemKey];
        }
        if ($label === '') {
            continue;
        }
        $done = !empty($row['done']) ? 1 : 0;
        $isManual = 1;
        if ($itemKey !== '' && isset($auto[$itemKey])) {
            $isManual = ($done === (!empty($auto[$itemKey]) ? 1 : 0)) ? 0 : 1;
        }
        $upd->execute([$label, $done, $isManual, $order++, $itemId, $caseId]);
    }

    $maxOrder = $order;
    $ins = $pdo->prepare(
        'INSERT INTO case_checklist_items (case_id, item_key, label, is_done, is_manual, is_dismissed, sort_order)
         VALUES (?,?,?,?,1,0,?)'
    );
    foreach ($newLabels as $row) {
        if (is_array($row)) {
            $label = trim((string) ($row['label'] ?? ''));
            $done = !empty($row['done']) ? 1 : 0;
        } else {
            $label = trim((string) $row);
            $done = 0;
        }
        if ($label === '') {
            continue;
        }
        $ins->execute([$caseId, null, $label, $done, $maxOrder++]);
    }

    $context = case_checklist_context_for_case($pdo, $caseId);
    sync_case_checklist_auto_items($pdo, $caseId, $context);
}

function delete_case_checklist_item(PDO $pdo, int $caseId, int $itemId): bool
{
    ensure_case_checklist_table($pdo);
    $stmt = $pdo->prepare('SELECT item_key FROM case_checklist_items WHERE id = ? AND case_id = ? AND is_dismissed = 0');
    $stmt->execute([$itemId, $caseId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }
    $key = $row['item_key'] ?? null;
    if ($key !== null && $key !== '') {
        $pdo->prepare('UPDATE case_checklist_items SET is_dismissed = 1 WHERE id = ? AND case_id = ?')->execute([$itemId, $caseId]);
    } else {
        $pdo->prepare('DELETE FROM case_checklist_items WHERE id = ? AND case_id = ?')->execute([$itemId, $caseId]);
    }
    return true;
}
