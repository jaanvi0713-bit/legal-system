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
            create_notification($pdo, (int)$lawyerId, 'Appointment request', post('title'), 'appointment', '../lawyer/appointments.php', $uid);
        }
        flash('success', 'Appointment requested. Your lawyer will confirm.');
        redirect('appointments.php');
    }
    if ($fa === 'cancel') {
        $pdo->prepare('UPDATE appointments SET status="cancelled" WHERE id=? AND client_id=? AND status IN ("pending","accepted")')->execute([(int) post('id'), $uid]);
        flash('success', 'Appointment cancelled.');
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
$pageSubtitle = 'Request meetings, view history, and cancel when needed';
$portal = 'client';
$activeNav = 'appointments';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <h2>Request appointment</h2>
    <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="form_action" value="request">
        <div class="form-group"><label>Title</label><input name="title" required></div>
        <div class="form-group"><label>Type</label><select name="appointment_type"><option value="consultation">Consultation</option><option value="meeting">Meeting</option><option value="other">Other</option></select></div>
        <div class="form-group"><label>Preferred time</label><input type="datetime-local" name="scheduled_at" required></div>
        <div class="form-group"><label>Case</label><select name="case_id"><option value="">—</option><?php foreach ($cases as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['case_number']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Location preference</label><input name="location" placeholder="Office / Virtual"></div>
        <div class="form-group full"><label>Details</label><textarea name="description"></textarea></div>
        <div class="form-actions full"><button class="btn btn-primary" type="submit">Submit request</button></div>
    </form>
</div>
<div class="panel">
    <h2>Appointment history</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>When</th><th>Title</th><th>Lawyer</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $a): ?>
                <tr>
                    <td><?= e(format_datetime($a['scheduled_at'])) ?></td>
                    <td><?= e($a['title']) ?></td>
                    <td><?= e($a['lawyer_name'] ?: '—') ?></td>
                    <td><?= status_badge($a['status']) ?></td>
                    <td>
                        <?php if (in_array($a['status'], ['pending','accepted'], true)): ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="cancel"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="chip" type="submit">Cancel</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
