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

$pageTitle = __('page.lawyers');
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
    $isEdit = (bool) $id;
    ?>
    <div class="entity-form-wrap">
    <div class="entity-form panel">
        <div class="entity-form-hero">
            <div>
                <p class="entity-form-eyebrow"><?= $isEdit ? 'Lawyer profile' : 'New lawyer' ?></p>
                <h2><?= $isEdit ? 'Edit lawyer' : 'Add lawyer' ?></h2>
                <p class="muted"><?= $isEdit ? 'Update practice details, availability, and portal access.' : 'Create a lawyer profile with credentials, specialization, and availability.' ?></p>
            </div>
            <p class="entity-form-required-note"><span class="req">*</span> Required fields</p>
        </div>

        <form method="post">
            <div class="entity-form-body">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="save">
                <input type="hidden" name="id" value="<?= (int)$lawyer['id'] ?>">

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3>Personal details</h3>
                        <p>Contact information shown on the lawyer profile.</p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">First name <span class="req" title="Required">*</span></label>
                            <input id="first_name" name="first_name" required value="<?= e($lawyer['first_name']) ?>" placeholder="e.g. Arjun">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last name <span class="req" title="Required">*</span></label>
                            <input id="last_name" name="last_name" required value="<?= e($lawyer['last_name']) ?>" placeholder="e.g. Mehta">
                        </div>
                        <div class="form-group">
                            <label for="email">Email <span class="req" title="Required">*</span></label>
                            <input id="email" type="email" name="email" required value="<?= e($lawyer['email']) ?>" placeholder="name@firm.com">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input id="phone" name="phone" value="<?= e($lawyer['phone']) ?>" placeholder="+230 …">
                        </div>
                        <div class="form-group full">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="2" placeholder="Office / chamber address"><?= e($lawyer['address']) ?></textarea>
                        </div>
                    </div>
                </section>

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3>Account &amp; access</h3>
                        <p>Portal login credentials and account status.</p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="username">Username <span class="req" title="Required">*</span></label>
                            <input id="username" name="username" required value="<?= e($lawyer['username']) ?>" placeholder="Unique login ID" autocomplete="off">
                        </div>
                        <?php if (!$isEdit): ?>
                        <div class="form-group">
                            <label for="password">Temporary password</label>
                            <input id="password" name="password" type="text" placeholder="Leave blank for password123" autocomplete="off">
                            <span class="field-hint">Optional — defaults to password123 if empty.</span>
                        </div>
                        <?php endif; ?>
                        <div class="form-group">
                            <label for="is_active">Account status <span class="req" title="Required">*</span></label>
                            <select id="is_active" name="is_active" required>
                                <option value="1" <?= $lawyer['is_active'] ? 'selected' : '' ?>>Active</option>
                                <option value="0" <?= !$lawyer['is_active'] ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3>Practice &amp; availability</h3>
                        <p>Bar credentials, specialization, and current workload status.</p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="specialization">Specialization</label>
                            <input id="specialization" name="specialization" value="<?= e($lawyer['specialization']) ?>" placeholder="e.g. Corporate, Criminal, Family">
                        </div>
                        <div class="form-group">
                            <label for="bar_number">Bar number</label>
                            <input id="bar_number" name="bar_number" value="<?= e($lawyer['bar_number']) ?>" placeholder="Enrollment / bar ID">
                        </div>
                        <div class="form-group">
                            <label for="availability">Availability <span class="req" title="Required">*</span></label>
                            <select id="availability" name="availability" required>
                                <?php foreach (['available' => 'Available', 'busy' => 'Busy', 'unavailable' => 'Unavailable'] as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $lawyer['availability'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </section>
            </div>

            <div class="entity-form-footer">
                <a class="btn btn-secondary" href="lawyers.php">Back to lawyers</a>
                <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save changes' : 'Save lawyer' ?></button>
            </div>
        </form>
    </div>
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
            <a class="btn btn-primary btn-sm" href="?action=edit&id=<?= $id ?>">Edit profile</a>
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
                    <td class="col-actions">
                        <div class="row-actions">
                            <a class="btn btn-row-edit btn-sm" href="?action=edit&id=<?= (int)$l['id'] ?>">Edit</a>
                            <form method="post" onsubmit="return confirm('Remove this lawyer?')"><?= csrf_field() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-row-delete btn-sm" type="submit">Remove</button></form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
