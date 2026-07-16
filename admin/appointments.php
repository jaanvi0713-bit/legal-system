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
        $vals = [
            post('title'), post('description'), post('appointment_type'), post('case_id') ?: null,
            post('client_id') ?: null, post('lawyer_id') ?: null, post('scheduled_at'),
            (int) post('duration_minutes', 60), post('location'), post('status'),
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
}

$clients = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role='client' ORDER BY first_name")->fetchAll();
$lawyers = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role='lawyer' AND is_active=1 ORDER BY first_name")->fetchAll();
$cases = $pdo->query('SELECT id, case_number, title FROM cases ORDER BY created_at DESC')->fetchAll();
$pageTitle = __('page.appointments_short');
$upcomingCount = (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE status IN ('pending','accepted') AND scheduled_at >= NOW()")->fetchColumn();
$pageSubtitle = __('appointments.upcoming_count', ['count' => $upcomingCount]);
$portal = 'admin';
$activeNav = 'appointments';

if ($action === 'create' || ($action === 'edit' && $id)) {
    $row = ['id'=>0,'title'=>'','description'=>'','appointment_type'=>'meeting','case_id'=>'','client_id'=>'','lawyer_id'=>'','scheduled_at'=>date('Y-m-d\TH:i'),'duration_minutes'=>60,'location'=>'','status'=>'pending'];
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: $row;
        if (!empty($row['scheduled_at'])) $row['scheduled_at'] = date('Y-m-d\TH:i', strtotime($row['scheduled_at']));
    }
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="panel">
        <h2><?= $id ? __e('appointments.edit') : __e('appointments.create') ?></h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="save"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <div class="form-group full"><label><?= __e('common.title') ?></label><input name="title" required value="<?= e($row['title']) ?>"></div>
            <div class="form-group"><label><?= __e('common.type') ?></label>
                <select name="appointment_type"><?php foreach (['meeting','consultation','hearing','other'] as $t): ?><option value="<?= $t ?>" <?= $row['appointment_type']===$t?'selected':'' ?>><?= e(__('appointment.type.' . $t)) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label><?= __e('common.status') ?></label>
                <select name="status"><?php foreach (['pending','accepted','rejected','cancelled','completed'] as $s): ?><option value="<?= $s ?>" <?= $row['status']===$s?'selected':'' ?>><?= e(translate_status($s)) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label><?= __e('common.client') ?></label>
                <select name="client_id"><option value=""><?= __e('common.em_dash') ?></option><?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$row['client_id']===(int)$c['id']?'selected':'' ?>><?= e(full_name($c)) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label><?= __e('common.lawyer') ?></label>
                <select name="lawyer_id"><option value=""><?= __e('common.em_dash') ?></option><?php foreach ($lawyers as $l): ?><option value="<?= (int)$l['id'] ?>" <?= (int)$row['lawyer_id']===(int)$l['id']?'selected':'' ?>><?= e(full_name($l)) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label><?= __e('form.related_case') ?></label>
                <select name="case_id"><option value=""><?= __e('common.em_dash') ?></option><?php foreach ($cases as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$row['case_id']===(int)$c['id']?'selected':'' ?>><?= e($c['case_number'].' — '.$c['title']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label><?= __e('common.when') ?></label><input type="datetime-local" name="scheduled_at" required value="<?= e($row['scheduled_at']) ?>"></div>
            <div class="form-group"><label><?= __e('form.duration_minutes') ?></label><input type="number" name="duration_minutes" value="<?= (int)$row['duration_minutes'] ?>"></div>
            <div class="form-group"><label><?= __e('common.location') ?></label><input name="location" value="<?= e($row['location']) ?>"></div>
            <div class="form-group full"><label><?= __e('common.description') ?></label><textarea name="description"><?= e($row['description']) ?></textarea></div>
            <div class="form-actions full"><button class="btn btn-primary" type="submit"><?= __e('common.save') ?></button><a class="btn btn-ghost" href="appointments.php"><?= __e('common.cancel') ?></a></div>
        </form>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

$rows = $pdo->query("SELECT a.*, CONCAT(c.first_name,' ',c.last_name) AS client_name, CONCAT(l.first_name,' ',l.last_name) AS lawyer_name, cs.case_number, cs.title AS case_title FROM appointments a LEFT JOIN users c ON c.id=a.client_id LEFT JOIN users l ON l.id=a.lawyer_id LEFT JOIN cases cs ON cs.id=a.case_id ORDER BY a.scheduled_at DESC")->fetchAll();

$calendarItems = [];
foreach ($rows as $r) {
    $tone = appointment_calendar_tone($r);
    $titlePart = t_content($r['case_title'] ?: $r['title']);
    $locPart = $r['location'] ? t_content($r['location']) : '';
    if (!empty($r['case_number'])) {
        $caseLabel = $r['case_number'] . ' - ' . $titlePart . ($locPart !== '' ? ' ' . $locPart : '');
    } else {
        $caseLabel = $titlePart . ($locPart !== '' ? ' - ' . $locPart : '');
    }
    $calendarItems[] = [
        'id' => (int) $r['id'],
        'title' => t_content($r['title']),
        'caseLabel' => $caseLabel,
        'client' => $r['client_name'] ?: '',
        'lawyer' => $r['lawyer_name'] ?: '',
        'location' => $locPart,
        'scheduledAt' => $r['scheduled_at'],
        'status' => $r['status'],
        'tone' => $tone,
        'statusLabel' => strtoupper(__('calendar.tone.' . $tone)),
        'editUrl' => '?action=edit&id=' . (int) $r['id'],
    ];
}

$calendarMonths = [
    __('calendar.month.jan'),
    __('calendar.month.feb'),
    __('calendar.month.mar'),
    __('calendar.month.apr'),
    __('calendar.month.may'),
    __('calendar.month.jun'),
    __('calendar.month.jul'),
    __('calendar.month.aug'),
    __('calendar.month.sep'),
    __('calendar.month.oct'),
    __('calendar.month.nov'),
    __('calendar.month.dec'),
];

$calendarTones = ['scheduled', 'confirmed', 'rescheduled', 'past', 'completed', 'cancelled'];
$totalCount = count($rows);
$listSubtitle = $totalCount === 1
    ? __('appointments.total_one', ['count' => $totalCount])
    : __('appointments.total_many', ['count' => $totalCount]);

require __DIR__ . '/../includes/header.php';
?>
<div class="panel appt-calendar-panel">
    <div class="appt-calendar" id="apptCalendar"
         data-year="<?= (int) date('Y') ?>"
         data-month="<?= (int) date('n') - 1 ?>"
         data-day="<?= (int) date('j') ?>">
        <aside class="appt-cal-sidebar" aria-label="<?= __e('common.calendar') ?>">
            <div class="appt-cal-year">
                <button type="button" class="appt-cal-nav" data-cal-nav="year" data-dir="-1" aria-label="<?= __e('common.previous') ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 6l-6 6 6 6"/></svg>
                </button>
                <span class="appt-cal-year-label" id="apptCalYear"><?= (int) date('Y') ?></span>
                <button type="button" class="appt-cal-nav" data-cal-nav="year" data-dir="1" aria-label="<?= __e('common.next') ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg>
                </button>
            </div>
            <ul class="appt-cal-months" id="apptCalMonths">
                <?php foreach ($calendarMonths as $i => $monthName): ?>
                <li>
                    <button type="button" class="appt-cal-month-btn" data-month="<?= $i ?>">
                        <span><?= e($monthName) ?></span>
                        <span class="appt-cal-month-count" data-month-count="<?= $i ?>">0</span>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <section class="appt-cal-main" aria-label="<?= __e('appointments.calendar') ?>">
            <div class="appt-cal-main-head">
                <h2 class="appt-cal-month-title" id="apptCalMonthTitle"><?= e(strtoupper($calendarMonths[(int) date('n') - 1])) ?></h2>
                <a class="appt-cal-add" href="?action=create" title="<?= __e('appointments.create') ?>" aria-label="<?= __e('appointments.create') ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>
                </a>
            </div>
            <div class="appt-cal-weekdays" aria-hidden="true">
                <?php foreach (['mon','tue','wed','thu','fri','sat','sun'] as $wd): ?>
                <span><?= __e('calendar.weekday.' . $wd) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="appt-cal-days" id="apptCalDays" role="grid" aria-label="<?= __e('appointments.calendar') ?>"></div>
            <div class="appt-cal-legend" aria-hidden="true">
                <?php foreach ($calendarTones as $tone): ?>
                <span class="appt-cal-legend-item">
                    <span class="appt-cal-dot tone-<?= e($tone) ?>"></span>
                    <?= e(__('calendar.tone.' . $tone)) ?>
                </span>
                <?php endforeach; ?>
            </div>
        </section>

        <aside class="appt-cal-agenda" aria-label="<?= __e('calendar.agenda') ?>">
            <h3 class="appt-cal-agenda-title"><?= __e('calendar.agenda') ?></h3>
            <div class="appt-cal-agenda-list" id="apptCalAgenda"></div>
        </aside>
    </div>
</div>

<script>
window.LEXORA_APPT_CAL = <?= json_encode([
    'items' => $calendarItems,
    'months' => $calendarMonths,
    'tones' => array_map(static fn(string $t) => __('calendar.tone.' . $t), $calendarTones),
    'emptyDay' => __('calendar.empty_day'),
    'emptyMonth' => __('calendar.empty_month'),
    'createUrl' => '?action=create',
    'locale' => locale_tag(),
], JSON_UNESCAPED_UNICODE) ?>;
</script>

<div class="panel appt-list-panel">
    <div class="appt-list-header">
        <div>
            <h2><?= __e('appointments.list') ?></h2>
            <p class="appt-list-meta"><?= e($listSubtitle) ?></p>
        </div>
        <a class="btn btn-accent btn-sm" href="?action=create"><?= __e('appointments.create') ?></a>
    </div>
    <div class="table-wrap appt-list-body">
        <table>
            <thead><tr><th><?= __e('common.when') ?></th><th><?= __e('common.title') ?></th><th><?= __e('common.type') ?></th><th><?= __e('common.lawyer') ?></th><th><?= __e('common.client') ?></th><th><?= __e('common.status') ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e(format_datetime($r['scheduled_at'])) ?></td>
                    <td><strong><?= e(t_content($r['title'])) ?></strong><div class="muted"><?= e($r['location'] ? t_content($r['location']) : '') ?></div></td>
                    <td><?= e(__('appointment.type.' . $r['appointment_type'])) ?></td>
                    <td><?= e($r['lawyer_name'] ?: __('common.em_dash')) ?></td>
                    <td><?= e($r['client_name'] ?: __('common.em_dash')) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td class="quick-links">
                        <a class="chip" href="?action=edit&id=<?= (int)$r['id'] ?>"><?= __e('common.edit') ?></a>
                        <?php if ($r['status'] !== 'cancelled'): ?>
                        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="form_action" value="cancel"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="chip" type="submit"><?= __e('common.cancel') ?></button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
