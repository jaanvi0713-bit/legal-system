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
        $data = [
            post('first_name'), post('last_name'), post('username'), post('email'), post('phone'),
            post('address'), post('company_name'), post('assigned_lawyer_id') ?: null,
            post('notes'), (int) (post('is_active') === '1'),
        ];
        $editId = (int) post('id');
        if ($editId) {
            $sql = 'UPDATE users SET first_name=?, last_name=?, username=?, email=?, phone=?, address=?, company_name=?, assigned_lawyer_id=?, notes=?, is_active=? WHERE id=? AND role="client"';
            $data[] = $editId;
            $pdo->prepare($sql)->execute($data);
            flash('success', 'Client updated.');
            log_activity($pdo, current_user()['id'], 'update', 'client', $editId, 'Updated client');
        } else {
            $password = password_hash(post('password') ?: 'password123', PASSWORD_DEFAULT);
            $sql = 'INSERT INTO users (role, first_name, last_name, username, email, password, phone, address, company_name, assigned_lawyer_id, notes, is_active) VALUES ("client",?,?,?,?,?,?,?,?,?,?,?)';
            array_splice($data, 4, 0, [$password]);
            $pdo->prepare($sql)->execute($data);
            $newId = (int) $pdo->lastInsertId();
            create_notification($pdo, current_user()['id'], 'Client created', post('first_name') . ' ' . post('last_name') . ' added.', 'info', 'clients.php?id=' . $newId, current_user()['id']);
            if (post('assigned_lawyer_id')) {
                create_notification($pdo, (int) post('assigned_lawyer_id'), 'New client assignment', 'Client ' . post('first_name') . ' ' . post('last_name') . ' assigned to you.', 'case', '../lawyer/clients.php', current_user()['id']);
            }
            flash('success', 'Client created.');
            log_activity($pdo, current_user()['id'], 'create', 'client', $newId, 'Created client');
        }
        redirect('clients.php');
    }
    if ($postAction === 'delete') {
        $delId = (int) post('id');
        $pdo->prepare('DELETE FROM users WHERE id=? AND role="client"')->execute([$delId]);
        flash('success', 'Client deleted.');
        redirect('clients.php');
    }
    if ($postAction === 'approve') {
        $pdo->prepare('UPDATE users SET is_active=1 WHERE id=? AND role="client"')->execute([(int) post('id')]);
        flash('success', 'Client account approved.');
        redirect('clients.php');
    }
}

$lawyers = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role='lawyer' AND is_active=1 ORDER BY first_name")->fetchAll();
$pageTitle = 'Client Management';
$pageSubtitle = 'Add, edit, assign, and manage client accounts';
$portal = 'admin';
$activeNav = 'clients';

if ($action === 'create' || ($action === 'edit' && $id)) {
    $client = ['id' => 0, 'first_name' => '', 'last_name' => '', 'username' => '', 'email' => '', 'phone' => '', 'address' => '', 'company_name' => '', 'assigned_lawyer_id' => '', 'notes' => '', 'is_active' => 1];
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="client"');
        $stmt->execute([$id]);
        $client = $stmt->fetch() ?: $client;
    }
    require __DIR__ . '/../includes/header.php';
    $isEdit = (bool) $id;
    ?>
    <div class="entity-form panel">
        <div class="entity-form-hero">
            <div>
                <p class="entity-form-eyebrow"><?= $isEdit ? 'Client profile' : 'New client' ?></p>
                <h2><?= $isEdit ? 'Edit client' : 'Add client' ?></h2>
                <p class="muted"><?= $isEdit ? 'Update contact details, assignment, and account status.' : 'Create a client account, assign a lawyer, and set login credentials.' ?></p>
            </div>
        </div>

        <form method="post">
            <div class="entity-form-body">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="id" value="<?= (int)$client['id'] ?>">

            <section class="entity-section">
                <div class="entity-section-head">
                    <h3>Personal details</h3>
                    <p>Primary contact information for this client.</p>
                </div>
                <div class="form-grid">
                    <div class="form-group"><label for="first_name">First name</label><input id="first_name" name="first_name" required value="<?= e($client['first_name']) ?>" placeholder="e.g. Priya"></div>
                    <div class="form-group"><label for="last_name">Last name</label><input id="last_name" name="last_name" required value="<?= e($client['last_name']) ?>" placeholder="e.g. Sharma"></div>
                    <div class="form-group"><label for="email">Email</label><input id="email" type="email" name="email" required value="<?= e($client['email']) ?>" placeholder="name@company.com"></div>
                    <div class="form-group"><label for="phone">Phone</label><input id="phone" name="phone" value="<?= e($client['phone']) ?>" placeholder="+91 …"></div>
                    <div class="form-group full"><label for="address">Address</label><textarea id="address" name="address" rows="2" placeholder="Street, city, state, PIN"><?= e($client['address']) ?></textarea></div>
                </div>
            </section>

            <section class="entity-section">
                <div class="entity-section-head">
                    <h3>Account & access</h3>
                    <p>Portal login credentials and activation status.</p>
                </div>
                <div class="form-grid">
                    <div class="form-group"><label for="username">Username</label><input id="username" name="username" required value="<?= e($client['username']) ?>" placeholder="Unique login ID" autocomplete="off"></div>
                    <?php if (!$isEdit): ?>
                    <div class="form-group">
                        <label for="password">Temporary password</label>
                        <input id="password" name="password" type="text" placeholder="Leave blank for password123" autocomplete="off">
                        <span class="field-hint">Client can change this after first login.</span>
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="is_active">Account status</label>
                        <select id="is_active" name="is_active">
                            <option value="1" <?= $client['is_active'] ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= !$client['is_active'] ? 'selected' : '' ?>>Inactive / pending approval</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="entity-section">
                <div class="entity-section-head">
                    <h3>Firm assignment</h3>
                    <p>Company affiliation and responsible lawyer.</p>
                </div>
                <div class="form-grid">
                    <div class="form-group"><label for="company_name">Company</label><input id="company_name" name="company_name" value="<?= e($client['company_name']) ?>" placeholder="Optional organization name"></div>
                    <div class="form-group">
                        <label for="assigned_lawyer_id">Assigned lawyer</label>
                        <select id="assigned_lawyer_id" name="assigned_lawyer_id">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($lawyers as $l): ?>
                        <option value="<?= (int)$l['id'] ?>" <?= (int)$client['assigned_lawyer_id'] === (int)$l['id'] ? 'selected' : '' ?>><?= e(full_name($l)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
                    <div class="form-group full">
                        <label for="notes">Notes / history</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Intake notes, preferences, or internal history…"><?= e($client['notes']) ?></textarea>
                    </div>
                </div>
            </section>
            </div>

            <div class="entity-form-footer">
                <a class="btn btn-secondary" href="clients.php">Back to clients</a>
                <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save changes' : 'Save client' ?></button>
            </div>
        </form>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

if ($action === 'view' && $id) {
    $stmt = $pdo->prepare('SELECT c.*, CONCAT(l.first_name," ",l.last_name) AS lawyer_name FROM users c LEFT JOIN users l ON l.id = c.assigned_lawyer_id WHERE c.id=? AND c.role="client"');
    $stmt->execute([$id]);
    $client = $stmt->fetch();
    if (!$client) { flash('error', 'Client not found.'); redirect('clients.php'); }
    $cases = $pdo->prepare('SELECT * FROM cases WHERE client_id=? ORDER BY created_at DESC');
    $cases->execute([$id]);
    $cases = $cases->fetchAll();
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="panel">
        <div class="panel-header">
            <div>
                <h2><?= e(full_name($client)) ?></h2>
                <p class="muted"><?= e($client['company_name'] ?: 'Individual client') ?> · Lawyer: <?= e($client['lawyer_name'] ?: 'Unassigned') ?></p>
            </div>
            <div class="quick-links">
                <a class="btn btn-sm btn-primary" href="?action=edit&id=<?= $id ?>">Edit</a>
                <a class="btn btn-sm btn-secondary" href="clients.php">Back</a>
            </div>
        </div>
        <div class="grid grid-2">
            <div class="list-item"><strong>Email</strong><?= e($client['email']) ?></div>
            <div class="list-item"><strong>Phone</strong><?= e($client['phone'] ?: '—') ?></div>
            <div class="list-item span-2"><strong>Address</strong><?= e($client['address'] ?: '—') ?></div>
            <div class="list-item span-2"><strong>History / notes</strong><?= nl2br(e($client['notes'] ?: '—')) ?></div>
        </div>
    </div>
    <div class="panel">
        <h2>Client cases</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Case #</th><th>Title</th><th>Status</th><th>Filed</th></tr></thead>
                <tbody>
                <?php foreach ($cases as $c): ?>
                    <tr>
                        <td><a href="cases.php?action=view&id=<?= (int)$c['id'] ?>"><?= e($c['case_number']) ?></a></td>
                        <td><?= e($c['title']) ?></td>
                        <td><?= status_badge($c['status']) ?></td>
                        <td><?= e(format_date($c['filing_date'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

$clients = $pdo->query("SELECT c.*, CONCAT(l.first_name,' ',l.last_name) AS lawyer_name FROM users c LEFT JOIN users l ON l.id=c.assigned_lawyer_id WHERE c.role='client' ORDER BY c.created_at DESC")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <div class="panel-header">
        <h2>All clients</h2>
        <a class="btn btn-primary btn-sm" href="?action=create">Add client</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Company</th><th>Lawyer</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($clients as $c): ?>
                <tr>
                    <td><a href="?action=view&id=<?= (int)$c['id'] ?>"><strong><?= e(full_name($c)) ?></strong></a><div class="muted"><?= e($c['email']) ?></div></td>
                    <td><?= e($c['company_name'] ?: '—') ?></td>
                    <td><?= e($c['lawyer_name'] ?: 'Unassigned') ?></td>
                    <td><?= status_badge($c['is_active'] ? 'active' : 'pending') ?></td>
                    <td class="quick-links">
                        <a class="chip chip-edit" href="?action=edit&id=<?= (int)$c['id'] ?>">Edit</a>
                        <?php if (!$c['is_active']): ?>
                        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="form_action" value="approve"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="chip chip-ok" type="submit">Approve</button></form>
                        <?php endif; ?>
                        <form method="post" style="display:inline" onsubmit="return confirm('Delete this client?')"><?= csrf_field() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="chip chip-danger" type="submit">Delete</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
