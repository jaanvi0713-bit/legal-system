<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['client']);
$pdo = db();
$uid = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'request') {
        $lawyerId = current_user()['assigned_lawyer_id'] ?: null;
        $pdo->prepare('INSERT INTO appointments (title, description, appointment_type, case_id, client_id, lawyer_id, scheduled_at, duration_minutes, location, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,"pending",?)')
            ->execute([post('title'), post('description'), post('appointment_type'), post('case_id') ?: null, $uid, $lawyerId, post('scheduled_at'), 60, post('location'), $uid]);
        if ($lawyerId) {
            create_notification($pdo, (int)$lawyerId, 'notify.appointment_request', post('title'), 'appointment', '../lawyer/appointments.php', $uid);
        }
        flash('success', __('flash.appointment.requested'));
        redirect('appointments.php');
    }
    if ($fa === 'cancel') {
        $pdo->prepare('UPDATE appointments SET status="cancelled" WHERE id=? AND client_id=? AND status IN ("pending","scheduled","confirmed","rescheduled")')->execute([(int) post('id'), $uid]);
        flash('success', __('flash.appointment.cancelled'));
        redirect('appointments.php');
    }
}

$rows = $pdo->prepare("SELECT a.*, CONCAT(l.first_name,' ',l.last_name) AS lawyer_name, cs.case_number, cs.title AS case_title FROM appointments a LEFT JOIN users l ON l.id=a.lawyer_id LEFT JOIN cases cs ON cs.id=a.case_id WHERE a.client_id=? ORDER BY a.scheduled_at DESC");
$rows->execute([$uid]);
$rows = $rows->fetchAll();
$cases = $pdo->prepare('SELECT id, case_number FROM cases WHERE client_id=?');
$cases->execute([$uid]);
$cases = $cases->fetchAll();

$totalCount = count($rows);
$listSubtitle = $totalCount === 1
    ? __('appointments.total_one', ['count' => $totalCount])
    : __('appointments.total_many', ['count' => $totalCount]);
$calendarTones = appointment_statuses();
$calendarItems = array_map(static fn(array $r): array => calendar_item_from_appointment($r, ['editUrl' => '']), $rows);
$calCtx = build_entity_calendar_context($calendarItems, [
    'entity' => 'appointment',
    'showCreate' => false,
    'createUrl' => '#apptScheduleForm',
    'scheduleLabel' => __('client.appointments.request'),
]);
extract($calCtx, EXTR_OVERWRITE);

$prefillScheduleAt = '';
$prefillDate = get('date', '');
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefillDate)) {
    $prefillScheduleAt = $prefillDate . 'T09:00';
}

$pageTitle = __('page.appointments_short');
$pageSubtitle = __('ai.subtitle.client');
$portal = 'client';
$activeNav = 'appointments';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/calendar-panel.php';
require __DIR__ . '/../includes/calendar-view-modal.php';
?>
<div class="panel" id="apptScheduleForm">
    <h2><?= __e('client.appointments.request') ?></h2>
    <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="form_action" value="request">
        <div class="form-group"><label><?= __e('common.title') ?></label><input name="title" required></div>
        <div class="form-group"><label><?= __e('common.type') ?></label><select name="appointment_type"><?php foreach (['consultation','meeting','other'] as $t): ?><option value="<?= $t ?>"><?= e(__('appointment.type.' . $t)) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label><?= __e('form.preferred_time') ?></label><input type="datetime-local" name="scheduled_at" required value="<?= e($prefillScheduleAt) ?>"></div>
        <div class="form-group"><label><?= __e('common.case') ?></label><select name="case_id"><option value=""><?= __e('common.em_dash') ?></option><?php foreach ($cases as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['case_number']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label><?= __e('form.location_preference') ?></label><input name="location" placeholder="<?= __e('common.location') ?>"></div>
        <div class="form-group full"><label><?= __e('form.details') ?></label><textarea name="description"></textarea></div>
        <div class="form-actions full"><button class="btn btn-primary" type="submit"><?= __e('client.appointments.submit') ?></button></div>
    </form>
</div>
<?php
ob_start();
foreach ($rows as $a):
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
        $a['lawyer_name'] ?? '',
        $caseLabel,
        $a['location'] ?? '',
        $status,
    ])));
?>
<tr data-list-status="<?= e($status) ?>" data-list-search="<?= e($searchBlob) ?>">
    <td><div class="appt-list-when"><strong><?= e($dateLabel) ?></strong><span><?= e($timeLabel) ?></span></div></td>
    <td><strong class="appt-list-title"><?= e(t_content($a['title'])) ?></strong></td>
    <td><?= e($a['lawyer_name'] ?: __('common.em_dash')) ?></td>
    <td class="appt-list-case"><?= e($caseLabel !== '' ? t_content($caseLabel) : __('common.em_dash')) ?></td>
    <td><?= appointment_list_status_badge($status) ?></td>
    <td><?= calendar_export_buttons_html($exports, (string) $a['title']) ?></td>
    <td>
        <div class="row-actions">
            <?php if (in_array($status, appointment_upcoming_statuses(), true)): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="cancel"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-row-delete btn-sm" type="submit" data-confirm="<?= __e('appointments.confirm_cancel') ?>"><?= __e('common.cancel') ?></button></form>
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
    __('common.lawyer'),
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

