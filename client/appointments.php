<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['client']);
$pdo = db();
$uid = (int) current_user()['id'];
$user = current_user();
$action = get('action', 'list');
$id = (int) get('id', 0);

$clientLawyers = contact_fetch_client_lawyers($pdo, $uid);
$clientLawyerIds = array_map(static fn(array $l): int => (int) $l['id'], $clientLawyers);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'cancel') {
        $pdo->prepare('UPDATE appointments SET status="cancelled" WHERE id=? AND client_id=? AND status IN ("pending","scheduled","confirmed","rescheduled")')->execute([(int) post('id'), $uid]);
        flash('success', __('flash.appointment.cancelled'));
        redirect('appointments.php');
    }
    if ($fa === 'request') {
        if (!$clientLawyers) {
            flash('error', __('client.appointments.no_lawyer'));
            redirect('appointments.php');
        }
        $lawyerId = (int) post('lawyer_id');
        if (!in_array($lawyerId, $clientLawyerIds, true)) {
            flash('error', __('flash.message.denied'));
            redirect('appointments.php?action=request');
        }
        $type = post('appointment_type');
        if (!in_array($type, ['meeting', 'consultation'], true)) {
            $type = 'meeting';
        }
        $caseId = post('case_id') ?: null;
        if ($caseId) {
            $check = $pdo->prepare("SELECT id FROM cases WHERE id=? AND client_id=? AND status<>'closed'");
            $check->execute([(int) $caseId, $uid]);
            if (!$check->fetch()) {
                $caseId = null;
            }
        }
        $duration = post_appointment_duration();
        $scheduledAt = post('scheduled_at');
        $pdo->beginTransaction();
        $slotCheck = validate_lawyer_appointment_slot($pdo, $lawyerId, $scheduledAt, $duration, null, true);
        if (!$slotCheck['ok']) {
            $pdo->rollBack();
            flash_lawyer_slot_error($slotCheck, 'appointments.php?action=request');
        }
        $pdo->prepare('INSERT INTO appointments (title, description, appointment_type, case_id, client_id, lawyer_id, scheduled_at, duration_minutes, location, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([post('title'), post('description'), $type, $caseId, $uid, $lawyerId, $scheduledAt, $duration, post('location'), 'pending', $uid]);
        $pdo->commit();
        create_notification($pdo, $lawyerId, __('notify.appointment_request'), post('title'), 'appointment', '../lawyer/appointments.php', $uid);
        flash('success', __('flash.appointment.requested'));
        redirect('appointments.php');
    }
    flash('error', __('flash.message.denied'));
    redirect('appointments.php');
}

$pageTitle = __('page.appointments_short');
$pageSubtitle = __('ai.subtitle.client');
$portal = 'client';
$activeNav = 'appointments';

if ($action === 'create' || $action === 'request') {
    if (!$clientLawyers) {
        flash('error', __('client.appointments.no_lawyer'));
        redirect('appointments.php');
    }
    $prefillDate = get('date', '');
    $scheduledDefault = preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefillDate)
        ? $prefillDate . 'T09:00'
        : date('Y-m-d\TH:i');
    $lockLawyerId = count($clientLawyers) === 1 ? (int) $clientLawyers[0]['id'] : null;
    $row = [
        'id' => 0, 'title' => '', 'description' => '', 'appointment_type' => 'meeting',
        'case_id' => '', 'client_id' => $uid, 'lawyer_id' => $lockLawyerId ?? '',
        'scheduled_at' => $scheduledDefault, 'duration_minutes' => 60, 'location' => '', 'status' => 'pending',
    ];
    $myCases = $pdo->prepare("SELECT id, case_number, title FROM cases WHERE client_id=? AND status<>'closed' ORDER BY created_at DESC");
    $myCases->execute([$uid]);
    require __DIR__ . '/../includes/header.php';
    $isEdit = false;
    $formCancelUrl = 'appointments.php';
    $clients = [];
    $cases = $myCases->fetchAll();
    $apptFormConfig = [
        'showStatus' => false,
        'showClient' => false,
        'lockClientId' => $uid,
        'lockLawyerId' => $lockLawyerId,
        'types' => ['meeting', 'consultation'],
        'eyebrow' => __('client.appointments.request_eyebrow'),
        'title' => __('client.appointments.request'),
        'createLabel' => __('client.appointments.submit'),
        'createHelp' => __('client.appointments.request_help'),
        'formAction' => 'request',
        'pairTitleType' => true,
        'pairCaseWithParty' => true,
    ];
    $lawyers = $clientLawyers;
    $apptAvailabilityLawyerId = $lockLawyerId ?? (int) ($clientLawyers[0]['id'] ?? 0);
    require __DIR__ . '/../includes/appointment-form.php';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$rows = $pdo->prepare("SELECT a.*, CONCAT(l.first_name,' ',l.last_name) AS lawyer_name, cs.case_number, cs.title AS case_title FROM appointments a LEFT JOIN users l ON l.id=a.lawyer_id LEFT JOIN cases cs ON cs.id=a.case_id WHERE a.client_id=? ORDER BY a.scheduled_at DESC");
$rows->execute([$uid]);
$rows = $rows->fetchAll();

$totalCount = count($rows);
$listSubtitle = $totalCount === 1
    ? __('appointments.total_one', ['count' => $totalCount])
    : __('appointments.total_many', ['count' => $totalCount]);
$calendarTones = appointment_statuses();
$calendarItems = array_map(static fn(array $r): array => calendar_item_from_appointment($r, ['editUrl' => '']), $rows);
$canRequest = !empty($clientLawyers);
$calCtx = build_entity_calendar_context($calendarItems, [
    'entity' => 'appointment',
    'showCreate' => $canRequest,
    'createUrl' => $canRequest ? 'appointments.php?action=request' : '',
    'createLabel' => __('client.appointments.request'),
    'scheduleLabel' => __('client.appointments.request'),
]);
extract($calCtx, EXTR_OVERWRITE);

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/calendar-panel.php';
require __DIR__ . '/../includes/calendar-view-modal.php';

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
            <form method="post" data-confirm="<?= __e('appointments.confirm_cancel') ?>"><?= csrf_field() ?><input type="hidden" name="form_action" value="cancel"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-row-delete btn-sm" type="submit"><?= __e('common.cancel') ?></button></form>
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
$listHeroActionHtml = $canRequest
    ? '<a class="btn btn-primary" href="appointments.php?action=request">' . __e('client.appointments.request') . '</a>'
    : '';
require __DIR__ . '/../includes/entity-list-panel.php';
require __DIR__ . '/../includes/footer.php';
