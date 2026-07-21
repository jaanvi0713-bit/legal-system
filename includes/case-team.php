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
