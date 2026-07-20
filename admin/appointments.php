<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'staff']);
$pdo = db();
$action = get('action', 'list');
$id = (int) get('id', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'save') {
        $editId = (int) post('id');
        $status = post('status');
        if (!in_array($status, appointment_statuses(), true)) {
            $status = 'pending';
        }
        $duration = post_appointment_duration();
        $vals = [
            post('title'), post('description'), post('appointment_type'), post('case_id') ?: null,
            post('client_id') ?: null, post('lawyer_id') ?: null, post('scheduled_at'),
            $duration, post('location'), $status,
        ];
        $lawyerIdForSlot = post('lawyer_id') ? (int) post('lawyer_id') : null;
        $pdo->beginTransaction();
        $slotCheck = validate_lawyer_appointment_slot($pdo, $lawyerIdForSlot, post('scheduled_at'), $duration, $editId ?: null, true);
        if (!$slotCheck['ok']) {
            $pdo->rollBack();
            flash_lawyer_slot_error($slotCheck, $editId ? 'appointments.php?action=edit&id=' . $editId : 'appointments.php?action=create');
        }
        if ($editId) {
            $vals[] = $editId;
            $pdo->prepare('UPDATE appointments SET title=?, description=?, appointment_type=?, case_id=?, client_id=?, lawyer_id=?, scheduled_at=?, duration_minutes=?, location=?, status=? WHERE id=?')->execute($vals);
            $pdo->commit();
            flash('success', __('flash.appointment.updated'));
        } else {
            $vals[] = current_user()['id'];
            $pdo->prepare('INSERT INTO appointments (title, description, appointment_type, case_id, client_id, lawyer_id, scheduled_at, duration_minutes, location, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute($vals);
            $pdo->commit();
            if (post('lawyer_id')) {
                create_notification($pdo, (int) post('lawyer_id'), 'notify.appointment_scheduled', post('title'), 'appointment', '../lawyer/appointments.php', current_user()['id']);
            }
            if (post('client_id')) {
                create_notification($pdo, (int) post('client_id'), 'notify.appointment_scheduled', post('title'), 'appointment', '../client/appointments.php', current_user()['id']);
            }
            flash('success', __('flash.appointment.created'));
        }
        redirect('appointments.php');
    }
    if ($fa === 'cancel') {
        $pdo->prepare('UPDATE appointments SET status="cancelled" WHERE id=?')->execute([(int) post('id')]);
        flash('success', __('flash.appointment.cancelled'));
        redirect('appointments.php');
    }
    if ($fa === 'delete') {
        $delId = (int) post('id');
        $pdo->prepare('DELETE FROM appointments WHERE id=?')->execute([$delId]);
        flash('success', 'Appointment deleted.');
        redirect('appointments.php');
    }
}

$clients = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role='client' ORDER BY first_name")->fetchAll();
$lawyers = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role='lawyer' AND is_active=1 ORDER BY first_name")->fetchAll();
$cases = $pdo->query('SELECT id, case_number, title FROM cases ORDER BY created_at DESC')->fetchAll();
$pageTitle = __('page.appointments_short');
$upcomingCount = (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE status IN ('" . implode("','", appointment_upcoming_statuses()) . "') AND scheduled_at >= NOW()")->fetchColumn();
$pageSubtitle = __('appointments.upcoming_count', ['count' => $upcomingCount]);
$portal = 'admin';
$activeNav = 'appointments';

if ($action === 'create' || ($action === 'edit' && $id)) {
    $row = ['id'=>0,'title'=>'','description'=>'','appointment_type'=>'meeting','case_id'=>'','client_id'=>'','lawyer_id'=>'','scheduled_at'=>date('Y-m-d\TH:i'),'duration_minutes'=>60,'location'=>'','status'=>'pending'];
    if ($action === 'create') {
        $prefillDate = get('date', '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefillDate)) {
            $row['scheduled_at'] = $prefillDate . 'T09:00';
        }
    }
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: $row;
        if (!empty($row['scheduled_at'])) $row['scheduled_at'] = date('Y-m-d\TH:i', strtotime($row['scheduled_at']));
    }
    require __DIR__ . '/../includes/header.php';
    $isEdit = (bool) $id;
    $formCancelUrl = 'appointments.php';
    $apptAvailabilityLawyerId = (int) ($row['lawyer_id'] ?? 0) ?: null;
    require __DIR__ . '/../includes/appointment-form.php';
    require __DIR__ . '/../includes/footer.php'; exit;
}

$rows = $pdo->query("SELECT a.*, CONCAT(c.first_name,' ',c.last_name) AS client_name, CONCAT(l.first_name,' ',l.last_name) AS lawyer_name, cs.case_number, cs.title AS case_title FROM appointments a LEFT JOIN users c ON c.id=a.client_id LEFT JOIN users l ON l.id=a.lawyer_id LEFT JOIN cases cs ON cs.id=a.case_id ORDER BY a.scheduled_at DESC")->fetchAll();

$calendarItems = array_map(static fn(array $r): array => calendar_item_from_appointment($r), $rows);
$calCtx = build_entity_calendar_context($calendarItems, [
    'entity' => 'appointment',
    'showCreate' => true,
    'createUrl' => '?action=create',
    'createLabel' => __('appointments.create'),
]);
extract($calCtx, EXTR_OVERWRITE);

$calendarTones = appointment_statuses();
$totalCount = count($rows);
$listSubtitle = $totalCount === 1
    ? __('appointments.total_one', ['count' => $totalCount])
    : __('appointments.total_many', ['count' => $totalCount]);

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/calendar-panel.php';
require __DIR__ . '/../includes/calendar-view-modal.php';

ob_start();
foreach ($rows as $r):
    $status = normalize_appointment_status((string) $r['status']);
    $caseLabel = trim(($r['case_number'] ? $r['case_number'] . ' — ' : '') . ($r['case_title'] ?: ($r['title'] ?? '')));
    $ts = strtotime((string) $r['scheduled_at']);
    $dateLabel = $ts ? date('M j, Y', $ts) : __('common.em_dash');
    $timeLabel = $ts ? date('g:i A', $ts) : '';
    $exports = appointment_calendar_export_urls([
        'id' => (int) $r['id'],
        'title' => t_content($r['title']),
        'description' => $r['description'] ? t_content($r['description']) : '',
        'location' => $r['location'] ? t_content($r['location']) : '',
        'scheduledAt' => $r['scheduled_at'],
        'durationMinutes' => (int) $r['duration_minutes'],
    ]);
    $searchBlob = strtolower(trim(implode(' ', [
        $r['title'] ?? '',
        $r['client_name'] ?? '',
        $caseLabel,
        $r['location'] ?? '',
        $r['appointment_type'] ?? '',
        $status,
    ])));
?>
<tr data-list-status="<?= e($status) ?>" data-list-search="<?= e($searchBlob) ?>">
    <td>
        <div class="appt-list-when">
            <strong><?= e($dateLabel) ?></strong>
            <span><?= e($timeLabel) ?></span>
        </div>
    </td>
    <td><strong class="appt-list-title"><?= e(t_content($r['title'])) ?></strong></td>
    <td><?= e($r['client_name'] ?: __('common.em_dash')) ?></td>
    <td class="appt-list-case"><?= e($caseLabel !== '' ? t_content($caseLabel) : __('common.em_dash')) ?></td>
    <td><?= appointment_list_status_badge($status) ?></td>
    <td><?= calendar_export_buttons_html($exports, (string) $r['title']) ?></td>
    <td>
        <div class="row-actions">
            <a class="btn btn-row-edit btn-sm" href="?action=edit&id=<?= (int)$r['id'] ?>"><?= __e('common.edit') ?></a>
            <?php if ($status !== 'cancelled'): ?>
            <form method="post" data-confirm="<?= __e('appointments.confirm_cancel') ?>"><?= csrf_field() ?><input type="hidden" name="form_action" value="cancel"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-row-delete btn-sm" type="submit"><?= __e('common.delete') ?></button></form>
            <?php endif; ?>
        </div>
    </td>
</tr>
<?php endforeach;
$listRowsHtml = ob_get_clean();
$listPanelId = 'apptListPanel';
$listSearchId = 'apptListSearch';
$listStatusId = 'apptListStatus';
$listTableId = 'apptListTable';
$listFooterId = 'apptListFooter';
$listTotalMetaId = 'apptListTotalMeta';
$listPanelClass = 'appt-list-panel';
$listTitle = __('appointments.list');
$listSearchPlaceholder = __('appointments.search_placeholder');
$listAllStatuses = __('appointments.all_statuses');
$listStatuses = $calendarTones;
$listStatusI18nPrefix = 'calendar.tone.';
$listColumns = [
    __('appointments.col.datetime'),
    __('common.title'),
    __('common.client'),
    __('common.case'),
    __('common.status'),
    __('common.calendar'),
    __('common.actions'),
];
$listShowingTpl = __('appointments.showing', ['shown' => $totalCount, 'total' => $totalCount]);
$listTotalOneTpl = __('appointments.total_one', ['count' => ':count']);
$listTotalManyTpl = __('appointments.total_many', ['count' => ':count']);
require __DIR__ . '/../includes/entity-list-panel.php';
require __DIR__ . '/../includes/footer.php';
