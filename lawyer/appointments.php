<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$uid = (int) current_user()['id'];
$action = get('action', 'list');
$id = (int) get('id', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'save') {
        $editId = (int) post('id');
        $status = post('status');
        if (!in_array($status, appointment_statuses(), true)) {
            $status = 'confirmed';
        }
        $caseId = post('case_id') ?: null;
        if ($caseId) {
            $check = $pdo->prepare('SELECT id FROM cases WHERE id=? AND lawyer_id=?');
            $check->execute([(int) $caseId, $uid]);
            if (!$check->fetch()) {
                $caseId = null;
            }
        }
        $clientId = post('client_id') ?: null;
        if ($clientId) {
            $check = $pdo->prepare('SELECT id FROM users WHERE id=? AND role="client" AND (assigned_lawyer_id=? OR id IN (SELECT client_id FROM cases WHERE lawyer_id=?))');
            $check->execute([(int) $clientId, $uid, $uid]);
            if (!$check->fetch()) {
                $clientId = null;
            }
        }
        $duration = post_appointment_duration();
        $vals = [
            post('title'), post('description'), post('appointment_type'), $caseId,
            $clientId, $uid, post('scheduled_at'),
            $duration, post('location'), $status,
        ];
        $pdo->beginTransaction();
        $slotCheck = validate_lawyer_appointment_slot($pdo, $uid, post('scheduled_at'), $duration, $editId ?: null, true);
        if (!$slotCheck['ok']) {
            $pdo->rollBack();
            flash_lawyer_slot_error($slotCheck, $editId ? 'appointments.php?action=edit&id=' . $editId : 'appointments.php?action=create');
        }
        if ($editId) {
            $owned = $pdo->prepare('SELECT id FROM appointments WHERE id=? AND lawyer_id=?');
            $owned->execute([$editId, $uid]);
            if (!$owned->fetch()) {
                $pdo->rollBack();
                flash('error', __('error.case.invalid'));
                redirect('appointments.php');
            }
            $vals[] = $editId;
            $pdo->prepare('UPDATE appointments SET title=?, description=?, appointment_type=?, case_id=?, client_id=?, lawyer_id=?, scheduled_at=?, duration_minutes=?, location=?, status=? WHERE id=?')->execute($vals);
            $pdo->commit();
            flash('success', __('flash.appointment.updated'));
        } else {
            $vals[] = $uid;
            $pdo->prepare('INSERT INTO appointments (title, description, appointment_type, case_id, client_id, lawyer_id, scheduled_at, duration_minutes, location, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute($vals);
            $pdo->commit();
            if ($clientId) {
                create_notification($pdo, (int) $clientId, 'notify.meeting_scheduled', post('title'), 'appointment', '../client/appointments.php', $uid);
            }
            flash('success', __('flash.meeting.scheduled'));
        }
        redirect('appointments.php');
    }
    if ($fa === 'respond') {
        $status = post('status');
        if (!in_array($status, appointment_statuses(), true)) {
            $status = 'pending';
        }
        $pdo->prepare('UPDATE appointments SET status=? WHERE id=? AND lawyer_id=?')->execute([$status, (int) post('id'), $uid]);
        $ap = $pdo->prepare('SELECT * FROM appointments WHERE id=?');
        $ap->execute([(int) post('id')]);
        $ap = $ap->fetch();
        if ($ap && $ap['client_id']) {
            create_notification($pdo, (int) $ap['client_id'], notify_payload('notify.appointment_status', ['status' => post('status')]), $ap['title'], 'appointment', '../client/appointments.php', $uid);
        }
        flash('success', __('flash.appointment.status', ['status' => translate_status(post('status'))]));
        redirect('appointments.php');
    }
}

$myClients = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE role='client' AND (assigned_lawyer_id=? OR id IN (SELECT client_id FROM cases WHERE lawyer_id=?)) ORDER BY first_name");
$myClients->execute([$uid, $uid]);
$myClients = $myClients->fetchAll();
$myCases = $pdo->prepare('SELECT id, case_number, title FROM cases WHERE lawyer_id=? ORDER BY created_at DESC');
$myCases->execute([$uid]);
$myCases = $myCases->fetchAll();

$pageTitle = __('page.appointments_short');
$pageSubtitle = __('ai.subtitle.lawyer');
$portal = 'lawyer';
$activeNav = 'appointments';

if ($action === 'create' || ($action === 'edit' && $id)) {
    $row = ['id' => 0, 'title' => '', 'description' => '', 'appointment_type' => 'meeting', 'case_id' => '', 'client_id' => '', 'lawyer_id' => $uid, 'scheduled_at' => date('Y-m-d\TH:i'), 'duration_minutes' => 60, 'location' => '', 'status' => 'confirmed'];
    if ($action === 'create') {
        $prefillDate = get('date', '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefillDate)) {
            $row['scheduled_at'] = $prefillDate . 'T09:00';
        }
    }
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id=? AND lawyer_id=?');
        $stmt->execute([$id, $uid]);
        $row = $stmt->fetch() ?: $row;
        if (!empty($row['scheduled_at'])) {
            $row['scheduled_at'] = date('Y-m-d\TH:i', strtotime($row['scheduled_at']));
        }
        if (!$row || !(int) ($row['id'] ?? 0)) {
            flash('error', __('error.case.invalid'));
            redirect('appointments.php');
        }
    }
    require __DIR__ . '/../includes/header.php';
    $isEdit = (bool) $id;
    $formCancelUrl = 'appointments.php';
    $clients = $myClients;
    $cases = $myCases;
    $apptFormConfig = [
        'showLawyer' => false,
        'lockLawyerId' => $uid,
        'createLabel' => __('lawyer.appointments.schedule'),
        'editLabel' => __('common.save'),
        'createHelp' => __('lawyer.appointments.schedule_meeting'),
        'types' => ['meeting', 'consultation', 'hearing'],
    ];
    $apptAvailabilityLawyerId = $uid;
    require __DIR__ . '/../includes/appointment-form.php';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$appointments = $pdo->prepare("SELECT a.*, CONCAT(c.first_name,' ',c.last_name) AS client_name, cs.case_number, cs.title AS case_title FROM appointments a LEFT JOIN users c ON c.id=a.client_id LEFT JOIN cases cs ON cs.id=a.case_id WHERE a.lawyer_id=? ORDER BY a.scheduled_at DESC");
$appointments->execute([$uid]);
$appointments = $appointments->fetchAll();

$totalCount = count($appointments);
$listSubtitle = $totalCount === 1
    ? __('appointments.total_one', ['count' => $totalCount])
    : __('appointments.total_many', ['count' => $totalCount]);
$calendarTones = appointment_statuses();
$calendarItems = array_map(static fn(array $r): array => calendar_item_from_appointment($r, ['editUrl' => '?action=edit&id=' . (int) $r['id']]), $appointments);
$calCtx = build_entity_calendar_context($calendarItems, [
    'entity' => 'appointment',
    'showCreate' => true,
    'createUrl' => '?action=create',
    'createLabel' => __('lawyer.appointments.schedule'),
]);
extract($calCtx, EXTR_OVERWRITE);

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/calendar-panel.php';
require __DIR__ . '/../includes/calendar-view-modal.php';
?>
<div class="panel avail-schedule-link-panel">
    <div class="avail-schedule-link-inner">
        <div>
            <h2><?= __e('lawyer.appointments.my_availability') ?></h2>
            <p class="muted"><?= __e('availability.schedule.manage_hint') ?></p>
        </div>
        <a class="btn btn-primary btn-sm" href="availability.php"><?= __e('availability.schedule.manage') ?></a>
    </div>
</div>
<?php
ob_start();
foreach ($appointments as $a):
    $status = normalize_appointment_status((string) $a['status']);
    $caseLabel = trim(($a['case_number'] ? $a['case_number'] . ' — ' : '') . ($a['case_title'] ?: ''));
    $ts = strtotime((string) $a['scheduled_at']);
    $dateLabel = $ts ? date('M j, Y', $ts) : __('common.em_dash');
    $timeLabel = $ts ? date('g:i A', $ts) : '';
    $exports = appointment_calendar_export_urls([
        'id' => (int) $a['id'],
        'title' => t_content($a['title']),
        'description' => $a['description'] ? t_content($a['description']) : '',
        'location' => $a['location'] ? t_content($a['location']) : '',
        'scheduledAt' => $a['scheduled_at'],
        'durationMinutes' => (int) ($a['duration_minutes'] ?? 60),
    ]);
    $searchBlob = strtolower(trim(implode(' ', [
        $a['title'] ?? '',
        $a['client_name'] ?? '',
        $caseLabel,
        $a['location'] ?? '',
        $status,
    ])));
?>
<tr data-list-status="<?= e($status) ?>" data-list-search="<?= e($searchBlob) ?>">
    <td><div class="appt-list-when"><strong><?= e($dateLabel) ?></strong><span><?= e($timeLabel) ?></span></div></td>
    <td><strong class="appt-list-title"><?= e(t_content($a['title'])) ?></strong></td>
    <td><?= e($a['client_name'] ?: __('common.em_dash')) ?></td>
    <td class="appt-list-case"><?= e($caseLabel !== '' ? t_content($caseLabel) : __('common.em_dash')) ?></td>
    <td><?= appointment_list_status_badge($status) ?></td>
    <td><?= calendar_export_buttons_html($exports, (string) $a['title']) ?></td>
    <td>
        <div class="row-actions">
            <a class="btn btn-row-edit btn-sm" href="?action=edit&id=<?= (int) $a['id'] ?>"><?= __e('common.edit') ?></a>
            <?php if ($status === 'pending'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="respond"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="status" value="confirmed"><button class="btn btn-row-approve btn-sm" type="submit"><?= __e('common.accept') ?></button></form>
            <form method="post" data-confirm="<?= __e('confirm.reject_appointment') ?>"><?= csrf_field() ?><input type="hidden" name="form_action" value="respond"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="status" value="cancelled"><button class="btn btn-row-delete btn-sm" type="submit"><?= __e('common.reject') ?></button></form>
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
$listSubtitle = $listSubtitle;
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
$listHeroActionHtml = '<a class="btn btn-primary btn-sm" href="?action=create">' . __e('lawyer.appointments.schedule') . '</a>';
require __DIR__ . '/../includes/entity-list-panel.php';
require __DIR__ . '/../includes/footer.php';
