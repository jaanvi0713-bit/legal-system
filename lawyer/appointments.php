<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$uid = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
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
            create_notification($pdo, (int)$ap['client_id'], notify_payload('notify.appointment_status', ['status' => post('status')]), $ap['title'], 'appointment', '../client/appointments.php', $uid);
        }
        flash('success', __('flash.appointment.status', ['status' => translate_status(post('status'))]));
        redirect('appointments.php');
    }
    if ($fa === 'schedule') {
        $pdo->prepare('INSERT INTO appointments (title, description, appointment_type, case_id, client_id, lawyer_id, scheduled_at, duration_minutes, location, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,"confirmed",?)')
            ->execute([post('title'), post('description'), post('appointment_type'), post('case_id') ?: null, post('client_id') ?: null, $uid, post('scheduled_at'), (int) post('duration_minutes', 60), post('location'), $uid]);
        if (post('client_id')) {
            create_notification($pdo, (int) post('client_id'), 'notify.meeting_scheduled', post('title'), 'appointment', '../client/appointments.php', $uid);
        }
        flash('success', __('flash.meeting.scheduled'));
        redirect('appointments.php');
    }
    if ($fa === 'availability') {
        $pdo->prepare('UPDATE users SET availability=? WHERE id=?')->execute([post('availability'), $uid]);
        refresh_session_user();
        flash('success', __('flash.availability.updated'));
        redirect('appointments.php');
    }
}

$appointments = $pdo->prepare("SELECT a.*, CONCAT(c.first_name,' ',c.last_name) AS client_name, cs.case_number, cs.title AS case_title FROM appointments a LEFT JOIN users c ON c.id=a.client_id LEFT JOIN cases cs ON cs.id=a.case_id WHERE a.lawyer_id=? ORDER BY a.scheduled_at DESC");
$appointments->execute([$uid]);
$appointments = $appointments->fetchAll();
$myClients = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE role='client' AND (assigned_lawyer_id=? OR id IN (SELECT client_id FROM cases WHERE lawyer_id=?))");
$myClients->execute([$uid, $uid]);
$myClients = $myClients->fetchAll();
$myCases = $pdo->prepare('SELECT id, case_number, title FROM cases WHERE lawyer_id=?');
$myCases->execute([$uid]);
$myCases = $myCases->fetchAll();

$totalCount = count($appointments);
$listSubtitle = $totalCount === 1
    ? __('appointments.total_one', ['count' => $totalCount])
    : __('appointments.total_many', ['count' => $totalCount]);
$calendarTones = appointment_statuses();
$calendarItems = array_map(static fn(array $r): array => calendar_item_from_appointment($r, ['editUrl' => '']), $appointments);
$calCtx = build_entity_calendar_context($calendarItems, [
    'entity' => 'appointment',
    'showCreate' => false,
    'createUrl' => '#apptScheduleForm',
    'scheduleLabel' => __('calendar.schedule_for_day'),
]);
extract($calCtx, EXTR_OVERWRITE);

$prefillScheduleAt = '';
$prefillDate = get('date', '');
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefillDate)) {
    $prefillScheduleAt = $prefillDate . 'T09:00';
}

$pageTitle = __('page.appointments_short');
$pageSubtitle = __('ai.subtitle.lawyer');
$portal = 'lawyer';
$activeNav = 'appointments';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/calendar-panel.php';
require __DIR__ . '/../includes/calendar-view-modal.php';
?>
<div class="grid grid-2">
    <div class="panel">
        <h2><?= __e('lawyer.appointments.my_availability') ?></h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="availability">
            <div class="form-group"><select name="availability"><?php foreach (['available','busy','unavailable'] as $a): ?><option value="<?= $a ?>" <?= current_user()['availability']===$a?'selected':'' ?>><?= e(translate_status($a)) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><button class="btn btn-primary" type="submit"><?= __e('common.update') ?></button></div>
        </form>
    </div>
    <div class="panel" id="apptScheduleForm">
        <h2><?= __e('lawyer.appointments.schedule_meeting') ?></h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="schedule">
            <div class="form-group full"><input name="title" required placeholder="<?= __e('common.title') ?>"></div>
            <div class="form-group"><select name="appointment_type"><?php foreach (['meeting','consultation','hearing'] as $t): ?><option value="<?= $t ?>"><?= e(__('appointment.type.' . $t)) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><input type="datetime-local" name="scheduled_at" required value="<?= e($prefillScheduleAt) ?>"></div>
            <div class="form-group"><select name="client_id"><option value=""><?= __e('common.client') ?>…</option><?php foreach ($myClients as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e(full_name($c)) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><select name="case_id"><option value=""><?= __e('common.case') ?>…</option><?php foreach ($myCases as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['case_number']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><input type="number" name="duration_minutes" value="60"></div>
            <div class="form-group"><input name="location" placeholder="<?= __e('common.location') ?>"></div>
            <div class="form-group full"><textarea name="description" placeholder="<?= __e('common.notes') ?>"></textarea></div>
            <div class="form-actions full"><button class="btn btn-accent" type="submit"><?= __e('lawyer.appointments.schedule') ?></button></div>
        </form>
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
            <?php if ($status === 'pending'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="respond"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="status" value="confirmed"><button class="btn btn-row-approve btn-sm" type="submit"><?= __e('common.accept') ?></button></form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="respond"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="status" value="cancelled"><button class="btn btn-row-delete btn-sm" type="submit"><?= __e('common.reject') ?></button></form>
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
require __DIR__ . '/../includes/entity-list-panel.php';
require __DIR__ . '/../includes/footer.php';

