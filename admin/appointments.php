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
            flash('success', 'Appointment updated.');
        } else {
            $vals[] = current_user()['id'];
            $pdo->prepare('INSERT INTO appointments (title, description, appointment_type, case_id, client_id, lawyer_id, scheduled_at, duration_minutes, location, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute($vals);
            if (post('lawyer_id')) {
                create_notification($pdo, (int) post('lawyer_id'), 'Appointment scheduled', post('title'), 'appointment', '../lawyer/appointments.php', current_user()['id']);
            }
            if (post('client_id')) {
                create_notification($pdo, (int) post('client_id'), 'Appointment scheduled', post('title'), 'appointment', '../client/appointments.php', current_user()['id']);
            }
            flash('success', 'Appointment created.');
        }
        redirect('appointments.php');
    }
    if ($fa === 'cancel') {
        $pdo->prepare('UPDATE appointments SET status="cancelled" WHERE id=?')->execute([(int) post('id')]);
        flash('success', 'Appointment cancelled.');
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
$pageTitle = __('page.appointments');
$pageSubtitle = 'Meetings, consultations, and hearing schedules';
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
    $isEdit = (bool) $id;
    ?>
    <div class="entity-form-wrap">
    <div class="entity-form panel">
        <div class="entity-form-hero">
            <div>
                <p class="entity-form-eyebrow"><?= $isEdit ? 'Appointment' : 'Scheduling' ?></p>
                <h2><?= $isEdit ? 'Edit appointment' : 'Create appointment' ?></h2>
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
                            <label for="title">Title <span class="req">*</span></label>
                            <input id="title" name="title" required value="<?= e($row['title']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="appointment_type">Type <span class="req">*</span></label>
                            <select id="appointment_type" name="appointment_type" required>
                                <?php foreach (['meeting','consultation','hearing','other'] as $t): ?>
                                    <option value="<?= $t ?>" <?= $row['appointment_type']===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status">Status <span class="req">*</span></label>
                            <select id="status" name="status" required>
                                <?php foreach (['pending','accepted','rejected','cancelled','completed'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $row['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group full">
                            <label for="description">Description</label>
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
                            <label for="client_id">Client</label>
                            <select id="client_id" name="client_id"><option value="">—</option><?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$row['client_id']===(int)$c['id']?'selected':'' ?>><?= e(full_name($c)) ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="form-group">
                            <label for="lawyer_id">Lawyer</label>
                            <select id="lawyer_id" name="lawyer_id"><option value="">—</option><?php foreach ($lawyers as $l): ?><option value="<?= (int)$l['id'] ?>" <?= (int)$row['lawyer_id']===(int)$l['id']?'selected':'' ?>><?= e(full_name($l)) ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="form-group">
                            <label for="case_id">Related case</label>
                            <select id="case_id" name="case_id"><option value="">—</option><?php foreach ($cases as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$row['case_id']===(int)$c['id']?'selected':'' ?>><?= e($c['case_number'].' — '.$c['title']) ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="form-group">
                            <label for="scheduled_at">When <span class="req">*</span></label>
                            <input id="scheduled_at" type="datetime-local" name="scheduled_at" required value="<?= e($row['scheduled_at']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="duration_minutes">Duration (minutes)</label>
                            <input id="duration_minutes" type="number" name="duration_minutes" value="<?= (int)$row['duration_minutes'] ?>">
                        </div>
                        <div class="form-group">
                            <label for="location">Location</label>
                            <input id="location" name="location" value="<?= e($row['location']) ?>">
                        </div>
                    </div>
                </section>
            </div>
            <div class="entity-form-footer">
                <a class="btn btn-secondary" href="appointments.php">Back to appointments</a>
                <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save changes' : 'Create appointment' ?></button>
            </div>
        </form>
    </div>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

$rows = $pdo->query("SELECT a.*, CONCAT(c.first_name,' ',c.last_name) AS client_name, CONCAT(l.first_name,' ',l.last_name) AS lawyer_name FROM appointments a LEFT JOIN users c ON c.id=a.client_id LEFT JOIN users l ON l.id=a.lawyer_id ORDER BY a.scheduled_at DESC")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <div class="panel-header"><h2>Appointment calendar</h2><a class="btn btn-primary btn-sm" href="?action=create">Create appointment</a></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>When</th><th>Title</th><th>Type</th><th>Lawyer</th><th>Client</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e(format_datetime($r['scheduled_at'])) ?></td>
                    <td><strong><?= e($r['title']) ?></strong><div class="muted"><?= e($r['location'] ?: '') ?></div></td>
                    <td><?= e(ucfirst($r['appointment_type'])) ?></td>
                    <td><?= e($r['lawyer_name'] ?: '—') ?></td>
                    <td><?= e($r['client_name'] ?: '—') ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td class="case-row-actions">
                        <a class="btn btn-row-open btn-sm" href="?action=edit&id=<?= (int)$r['id'] ?>">Edit</a>
                        <?php if ($r['status'] !== 'cancelled'): ?>
                        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="form_action" value="cancel"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-secondary btn-sm" type="submit">Cancel</button></form>
                        <?php endif; ?>
                        <form method="post" class="inline-form" onsubmit="return confirm('Delete this appointment?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-row-delete btn-sm" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
