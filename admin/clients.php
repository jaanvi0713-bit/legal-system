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
    ?>
    <div class="panel">
        <h2><?= $id ? 'Edit client' : 'Add client' ?></h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="id" value="<?= (int)$client['id'] ?>">
            <div class="form-group"><label>First name</label><input name="first_name" required value="<?= e($client['first_name']) ?>"></div>
            <div class="form-group"><label>Last name</label><input name="last_name" required value="<?= e($client['last_name']) ?>"></div>
            <div class="form-group"><label>Username</label><input name="username" required value="<?= e($client['username']) ?>"></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" required value="<?= e($client['email']) ?>"></div>
            <div class="form-group"><label>Phone</label><input name="phone" value="<?= e($client['phone']) ?>"></div>
            <?php if (!$id): ?><div class="form-group"><label>Temporary password</label><input name="password" placeholder="Defaults to password123"></div><?php endif; ?>
            <div class="form-group"><label>Company</label><input name="company_name" value="<?= e($client['company_name']) ?>"></div>
            <div class="form-group"><label>Assigned lawyer</label>
                <select name="assigned_lawyer_id">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($lawyers as $l): ?>
                        <option value="<?= (int)$l['id'] ?>" <?= (int)$client['assigned_lawyer_id'] === (int)$l['id'] ? 'selected' : '' ?>><?= e(full_name($l)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group full"><label>Address</label><textarea name="address"><?= e($client['address']) ?></textarea></div>
            <div class="form-group full"><label>Notes / history</label><textarea name="notes"><?= e($client['notes']) ?></textarea></div>
            <div class="form-group"><label>Account status</label>
                <select name="is_active"><option value="1" <?= $client['is_active'] ? 'selected' : '' ?>>Active</option><option value="0" <?= !$client['is_active'] ? 'selected' : '' ?>>Inactive / pending</option></select>
            </div>
            <div class="form-actions full">
                <button class="btn btn-primary" type="submit">Save client</button>
                <a class="btn btn-ghost" href="clients.php">Cancel</a>
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
                <a class="btn btn-sm btn-ghost" href="clients.php">Back</a>
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
                        <a class="chip" href="?action=edit&id=<?= (int)$c['id'] ?>">Edit</a>
                        <?php if (!$c['is_active']): ?>
                        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="form_action" value="approve"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="chip" type="submit">Approve</button></form>
                        <?php endif; ?>
                        <form method="post" style="display:inline" onsubmit="return confirm('Delete this client?')"><?= csrf_field() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="chip" type="submit">Delete</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
