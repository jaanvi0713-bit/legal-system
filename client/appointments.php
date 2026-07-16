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
        $pdo->prepare('UPDATE appointments SET status="cancelled" WHERE id=? AND client_id=? AND status IN ("pending","accepted")')->execute([(int) post('id'), $uid]);
        flash('success', __('flash.appointment.cancelled'));
        redirect('appointments.php');
    }
}

$rows = $pdo->prepare("SELECT a.*, CONCAT(l.first_name,' ',l.last_name) AS lawyer_name FROM appointments a LEFT JOIN users l ON l.id=a.lawyer_id WHERE a.client_id=? ORDER BY a.scheduled_at DESC");
$rows->execute([$uid]);
$rows = $rows->fetchAll();
$cases = $pdo->prepare('SELECT id, case_number FROM cases WHERE client_id=?');
$cases->execute([$uid]);
$cases = $cases->fetchAll();

$pageTitle = __('page.appointments_short');
$pageSubtitle = __('ai.subtitle.client');
$portal = 'client';
$activeNav = 'appointments';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <h2><?= __e('client.appointments.request') ?></h2>
    <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="form_action" value="request">
        <div class="form-group"><label><?= __e('common.title') ?></label><input name="title" required></div>
        <div class="form-group"><label><?= __e('common.type') ?></label><select name="appointment_type"><?php foreach (['consultation','meeting','other'] as $t): ?><option value="<?= $t ?>"><?= e(__('appointment.type.' . $t)) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label><?= __e('form.preferred_time') ?></label><input type="datetime-local" name="scheduled_at" required></div>
        <div class="form-group"><label><?= __e('common.case') ?></label><select name="case_id"><option value=""><?= __e('common.em_dash') ?></option><?php foreach ($cases as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['case_number']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label><?= __e('form.location_preference') ?></label><input name="location" placeholder="<?= __e('common.location') ?>"></div>
        <div class="form-group full"><label><?= __e('form.details') ?></label><textarea name="description"></textarea></div>
        <div class="form-actions full"><button class="btn btn-primary" type="submit"><?= __e('client.appointments.submit') ?></button></div>
    </form>
</div>
<div class="panel">
    <h2><?= __e('client.appointments.history') ?></h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th><?= __e('common.when') ?></th><th><?= __e('common.title') ?></th><th><?= __e('common.lawyer') ?></th><th><?= __e('common.status') ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $a): ?>
                <tr>
                    <td><?= e(format_datetime($a['scheduled_at'])) ?></td>
                    <td><?= e(t_content($a['title'])) ?></td>
                    <td><?= e($a['lawyer_name'] ?: __('common.em_dash')) ?></td>
                    <td><?= status_badge($a['status']) ?></td>
                    <td class="case-row-actions">
                        <?php if (in_array($a['status'], ['pending','accepted'], true)): ?>
                        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="form_action" value="cancel"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-secondary btn-sm" type="submit"><?= __e('common.cancel') ?></button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
