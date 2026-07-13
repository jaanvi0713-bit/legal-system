<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'staff']);
$pdo = db();
$action = get('action', 'list');
$id = (int) get('id', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postAction = post('form_action');
    if ($postAction === 'save') {
        $editId = (int) post('id');
        $fields = [
            post('first_name'), post('last_name'), post('username'), post('email'), post('phone'),
            post('address'), post('specialization'), post('bar_number'),
            post('availability'), (int) (post('is_active') === '1'),
        ];
        if ($editId) {
            $fields[] = $editId;
            $pdo->prepare('UPDATE users SET first_name=?, last_name=?, username=?, email=?, phone=?, address=?, specialization=?, bar_number=?, availability=?, is_active=? WHERE id=? AND role="lawyer"')->execute($fields);
            flash('success', 'Lawyer updated.');
        } else {
            $password = password_hash(post('password') ?: 'password123', PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (role, first_name, last_name, username, email, password, phone, address, specialization, bar_number, availability, is_active) VALUES ("lawyer",?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    post('first_name'), post('last_name'), post('username'), post('email'), $password, post('phone'),
                    post('address'), post('specialization'), post('bar_number'), post('availability'), (int) (post('is_active') === '1'),
                ]);
            flash('success', 'Lawyer added.');
        }
        redirect('lawyers.php');
    }
    if ($postAction === 'delete') {
        $pdo->prepare('DELETE FROM users WHERE id=? AND role="lawyer"')->execute([(int) post('id')]);
        flash('success', 'Lawyer removed.');
        redirect('lawyers.php');
    }
    if ($postAction === 'assign_case') {
        $pdo->prepare('UPDATE cases SET lawyer_id=? WHERE id=?')->execute([(int) post('lawyer_id'), (int) post('case_id')]);
        create_notification($pdo, (int) post('lawyer_id'), 'Case assigned', 'A case has been assigned to you.', 'case', '../lawyer/cases.php', current_user()['id']);
        flash('success', 'Case assigned to lawyer.');
        redirect('lawyers.php?action=view&id=' . (int) post('lawyer_id'));
    }
}

$pageTitle = 'Lawyer Management';
$pageSubtitle = 'Profiles, workload, availability, and case assignment';
$portal = 'admin';
$activeNav = 'lawyers';

if ($action === 'create' || ($action === 'edit' && $id)) {
    $lawyer = ['id' => 0, 'first_name' => '', 'last_name' => '', 'username' => '', 'email' => '', 'phone' => '', 'address' => '', 'specialization' => '', 'bar_number' => '', 'availability' => 'available', 'is_active' => 1];
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="lawyer"');
        $stmt->execute([$id]);
        $lawyer = $stmt->fetch() ?: $lawyer;
    }
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="panel">
        <h2><?= $id ? 'Edit lawyer' : 'Add lawyer' ?></h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="save"><input type="hidden" name="id" value="<?= (int)$lawyer['id'] ?>">
            <div class="form-group"><label>First name</label><input name="first_name" required value="<?= e($lawyer['first_name']) ?>"></div>
            <div class="form-group"><label>Last name</label><input name="last_name" required value="<?= e($lawyer['last_name']) ?>"></div>
            <div class="form-group"><label>Username</label><input name="username" required value="<?= e($lawyer['username']) ?>"></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" required value="<?= e($lawyer['email']) ?>"></div>
            <div class="form-group"><label>Phone</label><input name="phone" value="<?= e($lawyer['phone']) ?>"></div>
            <?php if (!$id): ?><div class="form-group"><label>Password</label><input name="password" placeholder="Defaults to password123"></div><?php endif; ?>
            <div class="form-group"><label>Specialization</label><input name="specialization" value="<?= e($lawyer['specialization']) ?>"></div>
            <div class="form-group"><label>Bar number</label><input name="bar_number" value="<?= e($lawyer['bar_number']) ?>"></div>
            <div class="form-group"><label>Availability</label>
                <select name="availability">
                    <?php foreach (['available','busy','unavailable'] as $a): ?>
                        <option value="<?= $a ?>" <?= $lawyer['availability'] === $a ? 'selected' : '' ?>><?= ucfirst($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Status</label>
                <select name="is_active"><option value="1" <?= $lawyer['is_active'] ? 'selected' : '' ?>>Active</option><option value="0" <?= !$lawyer['is_active'] ? 'selected' : '' ?>>Inactive</option></select>
            </div>
            <div class="form-group full"><label>Address</label><textarea name="address"><?= e($lawyer['address']) ?></textarea></div>
            <div class="form-actions full"><button class="btn btn-primary" type="submit">Save</button><a class="btn btn-ghost" href="lawyers.php">Cancel</a></div>
        </form>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

if ($action === 'view' && $id) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="lawyer"');
    $stmt->execute([$id]);
    $lawyer = $stmt->fetch();
    if (!$lawyer) { flash('error', 'Lawyer not found.'); redirect('lawyers.php'); }
    $cases = $pdo->prepare('SELECT c.*, CONCAT(u.first_name," ",u.last_name) AS client_name FROM cases c JOIN users u ON u.id=c.client_id WHERE c.lawyer_id=? ORDER BY c.updated_at DESC');
    $cases->execute([$id]);
    $cases = $cases->fetchAll();
    $openCases = count(array_filter($cases, fn($c) => $c['status'] !== 'closed'));
    $unassigned = $pdo->query("SELECT id, case_number, title FROM cases WHERE lawyer_id IS NULL OR lawyer_id=0 ORDER BY created_at DESC")->fetchAll();
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="grid grid-3">
        <div class="stat-card"><div class="stat-label">Workload (open)</div><div class="stat-value"><?= $openCases ?></div></div>
        <div class="stat-card"><div class="stat-label">Total cases</div><div class="stat-value"><?= count($cases) ?></div></div>
        <div class="stat-card"><div class="stat-label">Availability</div><div class="stat-value" style="font-size:1.4rem;"><?= status_badge($lawyer['availability']) ?></div></div>
    </div>
    <div class="panel">
        <div class="panel-header">
            <div>
                <h2><?= e(full_name($lawyer)) ?></h2>
                <p class="muted"><?= e($lawyer['specialization'] ?: 'General practice') ?> · <?= e($lawyer['bar_number'] ?: 'No bar #') ?></p>
            </div>
            <a class="btn btn-sm btn-primary" href="?action=edit&id=<?= $id ?>">Edit profile</a>
        </div>
        <div class="grid grid-2">
            <div class="list-item"><strong>Email</strong><?= e($lawyer['email']) ?></div>
            <div class="list-item"><strong>Phone</strong><?= e($lawyer['phone'] ?: '—') ?></div>
        </div>
    </div>
    <div class="panel">
        <h2>Assign case</h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="assign_case"><input type="hidden" name="lawyer_id" value="<?= $id ?>">
            <div class="form-group"><label>Unassigned case</label>
                <select name="case_id" required>
                    <option value="">Select case…</option>
                    <?php foreach ($unassigned as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= e($c['case_number'] . ' — ' . $c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="align-self:end;"><button class="btn btn-accent" type="submit">Assign</button></div>
        </form>
    </div>
    <div class="panel">
        <h2>Case list &amp; performance</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Case</th><th>Client</th><th>Status</th><th>Priority</th></tr></thead>
                <tbody>
                <?php foreach ($cases as $c): ?>
                    <tr>
                        <td><a href="cases.php?action=view&id=<?= (int)$c['id'] ?>"><?= e($c['case_number']) ?></a><div class="muted"><?= e($c['title']) ?></div></td>
                        <td><?= e($c['client_name']) ?></td>
                        <td><?= status_badge($c['status']) ?></td>
                        <td><?= status_badge($c['priority']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

$lawyers = $pdo->query("SELECT l.*, (SELECT COUNT(*) FROM cases c WHERE c.lawyer_id=l.id AND c.status!='closed') AS open_cases FROM users l WHERE l.role='lawyer' ORDER BY l.first_name")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <div class="panel-header"><h2>Lawyers</h2><a class="btn btn-primary btn-sm" href="?action=create">Add lawyer</a></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Lawyer</th><th>Specialization</th><th>Workload</th><th>Availability</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($lawyers as $l): ?>
                <tr>
                    <td><a href="?action=view&id=<?= (int)$l['id'] ?>"><strong><?= e(full_name($l)) ?></strong></a><div class="muted"><?= e($l['email']) ?></div></td>
                    <td><?= e($l['specialization'] ?: '—') ?></td>
                    <td><?= (int)$l['open_cases'] ?> open</td>
                    <td><?= status_badge($l['availability']) ?></td>
                    <td class="quick-links">
                        <a class="chip" href="?action=edit&id=<?= (int)$l['id'] ?>">Edit</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('Remove this lawyer?')"><?= csrf_field() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="chip" type="submit">Remove</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
