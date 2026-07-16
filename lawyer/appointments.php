<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$uid = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'respond') {
        $pdo->prepare('UPDATE appointments SET status=? WHERE id=? AND lawyer_id=?')->execute([post('status'), (int) post('id'), $uid]);
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
        $pdo->prepare('INSERT INTO appointments (title, description, appointment_type, case_id, client_id, lawyer_id, scheduled_at, duration_minutes, location, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,"accepted",?)')
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

$appointments = $pdo->prepare("SELECT a.*, CONCAT(c.first_name,' ',c.last_name) AS client_name FROM appointments a LEFT JOIN users c ON c.id=a.client_id WHERE a.lawyer_id=? ORDER BY a.scheduled_at DESC");
$appointments->execute([$uid]);
$appointments = $appointments->fetchAll();
$myClients = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE role='client' AND (assigned_lawyer_id=? OR id IN (SELECT client_id FROM cases WHERE lawyer_id=?))");
$myClients->execute([$uid, $uid]);
$myClients = $myClients->fetchAll();
$myCases = $pdo->prepare('SELECT id, case_number, title FROM cases WHERE lawyer_id=?');
$myCases->execute([$uid]);
$myCases = $myCases->fetchAll();

$pageTitle = __('page.appointments_short');
$pageSubtitle = __('ai.subtitle.lawyer');
$portal = 'lawyer';
$activeNav = 'appointments';
require __DIR__ . '/../includes/header.php';
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
    <div class="panel">
        <h2><?= __e('lawyer.appointments.schedule_meeting') ?></h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="schedule">
            <div class="form-group full"><input name="title" required placeholder="<?= __e('common.title') ?>"></div>
            <div class="form-group"><select name="appointment_type"><?php foreach (['meeting','consultation','hearing'] as $t): ?><option value="<?= $t ?>"><?= e(__('appointment.type.' . $t)) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><input type="datetime-local" name="scheduled_at" required></div>
            <div class="form-group"><select name="client_id"><option value=""><?= __e('common.client') ?>…</option><?php foreach ($myClients as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e(full_name($c)) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><select name="case_id"><option value=""><?= __e('common.case') ?>…</option><?php foreach ($myCases as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['case_number']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><input type="number" name="duration_minutes" value="60"></div>
            <div class="form-group"><input name="location" placeholder="<?= __e('common.location') ?>"></div>
            <div class="form-group full"><textarea name="description" placeholder="<?= __e('common.notes') ?>"></textarea></div>
            <div class="form-actions full"><button class="btn btn-accent" type="submit"><?= __e('lawyer.appointments.schedule') ?></button></div>
        </form>
    </div>
</div>
<div class="panel">
    <h2><?= __e('common.calendar') ?></h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th><?= __e('common.when') ?></th><th><?= __e('common.title') ?></th><th><?= __e('common.client') ?></th><th><?= __e('common.status') ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($appointments as $a): ?>
                <tr>
                    <td><?= e(format_datetime($a['scheduled_at'])) ?></td>
                    <td><strong><?= e(t_content($a['title'])) ?></strong><div class="muted"><?= e($a['location'] ? t_content($a['location']) : '') ?></div></td>
                    <td><?= e($a['client_name'] ?: __('common.em_dash')) ?></td>
                    <td><?= status_badge($a['status']) ?></td>
                    <td class="quick-links">
                        <?php if ($a['status'] === 'pending'): ?>
                        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="form_action" value="respond"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="status" value="accepted"><button class="chip" type="submit"><?= __e('common.accept') ?></button></form>
                        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="form_action" value="respond"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="status" value="rejected"><button class="chip" type="submit"><?= __e('common.reject') ?></button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
