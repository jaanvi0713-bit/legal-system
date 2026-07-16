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
        $vals = [
            post('title'), post('description'), post('appointment_type'), post('case_id') ?: null,
            post('client_id') ?: null, post('lawyer_id') ?: null, post('scheduled_at'),
            (int) post('duration_minutes', 60), post('location'), $status,
        ];
        if ($editId) {
            $vals[] = $editId;
            $pdo->prepare('UPDATE appointments SET title=?, description=?, appointment_type=?, case_id=?, client_id=?, lawyer_id=?, scheduled_at=?, duration_minutes=?, location=?, status=? WHERE id=?')->execute($vals);
            flash('success', __('flash.appointment.updated'));
        } else {
            $vals[] = current_user()['id'];
            $pdo->prepare('INSERT INTO appointments (title, description, appointment_type, case_id, client_id, lawyer_id, scheduled_at, duration_minutes, location, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute($vals);
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
    ?>
    <div class="entity-form-wrap">
    <div class="entity-form panel">
        <div class="entity-form-hero">
            <div>
                <p class="entity-form-eyebrow"><?= $isEdit ? 'Appointment' : 'Scheduling' ?></p>
                <h2><?= $isEdit ? __e('appointments.edit') : __e('appointments.create') ?></h2>
                <p class="muted"><?= $isEdit ? 'Update time, parties, and status for this booking.' : 'Book a meeting, consultation, or hearing with clients and lawyers.' ?></p>
            </div>
            <p class="entity-form-required-note"><span class="req">*</span> Required fields</p>
        </div>
        <form method="post">
            <div class="entity-form-body">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="save">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3>Appointment details</h3>
                        <p>Title, type, and description.</p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="title"><?= __e('common.title') ?> <span class="req">*</span></label>
                            <input id="title" name="title" required value="<?= e($row['title']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="appointment_type"><?= __e('common.type') ?> <span class="req">*</span></label>
                            <select id="appointment_type" name="appointment_type" required>
                                <?php foreach (['meeting','consultation','hearing','other'] as $t): ?>
                                    <option value="<?= $t ?>" <?= $row['appointment_type']===$t?'selected':'' ?>><?= e(__('appointment.type.' . $t)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status"><?= __e('common.status') ?> <span class="req">*</span></label>
                            <select id="status" name="status" required>
                                <?php foreach (appointment_statuses() as $s): ?>
                                    <option value="<?= $s ?>" <?= normalize_appointment_status((string) $row['status'])===$s?'selected':'' ?>><?= e(translate_status($s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group full">
                            <label for="description"><?= __e('common.description') ?></label>
                            <textarea id="description" name="description" rows="2"><?= e($row['description']) ?></textarea>
                        </div>
                    </div>
                </section>

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3>Participants &amp; schedule</h3>
                        <p>Who is involved and when it happens.</p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="client_id"><?= __e('common.client') ?></label>
                            <select id="client_id" name="client_id"><option value=""><?= __e('common.em_dash') ?></option><?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$row['client_id']===(int)$c['id']?'selected':'' ?>><?= e(full_name($c)) ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="form-group">
                            <label for="lawyer_id"><?= __e('common.lawyer') ?></label>
                            <select id="lawyer_id" name="lawyer_id"><option value=""><?= __e('common.em_dash') ?></option><?php foreach ($lawyers as $l): ?><option value="<?= (int)$l['id'] ?>" <?= (int)$row['lawyer_id']===(int)$l['id']?'selected':'' ?>><?= e(full_name($l)) ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="form-group">
                            <label for="case_id"><?= __e('form.related_case') ?></label>
                            <select id="case_id" name="case_id"><option value=""><?= __e('common.em_dash') ?></option><?php foreach ($cases as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$row['case_id']===(int)$c['id']?'selected':'' ?>><?= e($c['case_number'].' — '.$c['title']) ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="form-group">
                            <label for="scheduled_at"><?= __e('common.when') ?> <span class="req">*</span></label>
                            <input id="scheduled_at" type="datetime-local" name="scheduled_at" required value="<?= e($row['scheduled_at']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="duration_minutes"><?= __e('form.duration_minutes') ?></label>
                            <input id="duration_minutes" type="number" name="duration_minutes" value="<?= (int)$row['duration_minutes'] ?>">
                        </div>
                        <div class="form-group">
                            <label for="location"><?= __e('common.location') ?></label>
                            <input id="location" name="location" value="<?= e($row['location']) ?>">
                        </div>
                    </div>
                </section>
            </div>
            <div class="entity-form-footer">
                <a class="btn btn-secondary" href="appointments.php"><?= __e('common.cancel') ?></a>
                <button class="btn btn-primary" type="submit"><?= $isEdit ? __e('common.save') : __e('appointments.create') ?></button>
            </div>
        </form>
    </div>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
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
            <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="cancel"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-row-delete btn-sm" type="submit" data-confirm="<?= __e('appointments.confirm_cancel') ?>"><?= __e('common.delete') ?></button></form>
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
