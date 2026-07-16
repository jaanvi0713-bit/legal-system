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
            flash('success', __('flash.client.updated'));
            log_activity($pdo, current_user()['id'], 'update', 'client', $editId, 'Updated client');
        } else {
            $password = password_hash(post('password') ?: 'password123', PASSWORD_DEFAULT);
            $sql = 'INSERT INTO users (role, first_name, last_name, username, email, password, phone, address, company_name, assigned_lawyer_id, notes, is_active) VALUES ("client",?,?,?,?,?,?,?,?,?,?,?)';
            array_splice($data, 4, 0, [$password]);
            $pdo->prepare($sql)->execute($data);
            $newId = (int) $pdo->lastInsertId();
            $clientName = trim(post('first_name') . ' ' . post('last_name'));
            create_notification($pdo, current_user()['id'], 'notify.client_created', notify_payload('notify.msg.new_client', ['name' => $clientName]), 'info', 'clients.php?id=' . $newId, current_user()['id']);
            if (post('assigned_lawyer_id')) {
                create_notification($pdo, (int) post('assigned_lawyer_id'), 'notify.client_assigned', notify_payload('notify.msg.client_assigned', ['name' => $clientName]), 'case', '../lawyer/clients.php', current_user()['id']);
            }
            flash('success', __('flash.client.created'));
            log_activity($pdo, current_user()['id'], 'create', 'client', $newId, 'Created client');
        }
        redirect('clients.php');
    }
    if ($postAction === 'delete') {
        $delId = (int) post('id');
        $pdo->prepare('DELETE FROM users WHERE id=? AND role="client"')->execute([$delId]);
        flash('success', __('flash.client.deleted'));
        redirect('clients.php');
    }
    if ($postAction === 'approve') {
        $pdo->prepare('UPDATE users SET is_active=1 WHERE id=? AND role="client"')->execute([(int) post('id')]);
        flash('success', __('flash.client.approved'));
        redirect('clients.php');
    }
}

$lawyers = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role='lawyer' AND is_active=1 ORDER BY first_name")->fetchAll();
$pageTitle = __('page.clients');
$pageSubtitle = __('page.clients');
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
        <h2><?= $id ? __e('clients.edit') : __e('clients.add') ?></h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="id" value="<?= (int)$client['id'] ?>">
            <div class="form-group"><label><?= __e('form.first_name') ?></label><input name="first_name" required value="<?= e($client['first_name']) ?>"></div>
            <div class="form-group"><label><?= __e('form.last_name') ?></label><input name="last_name" required value="<?= e($client['last_name']) ?>"></div>
            <div class="form-group"><label><?= __e('form.username') ?></label><input name="username" required value="<?= e($client['username']) ?>"></div>
            <div class="form-group"><label><?= __e('common.email') ?></label><input type="email" name="email" required value="<?= e($client['email']) ?>"></div>
            <div class="form-group"><label><?= __e('common.phone') ?></label><input name="phone" value="<?= e($client['phone']) ?>"></div>
            <?php if (!$id): ?><div class="form-group"><label><?= __e('form.temp_password') ?></label><input name="password" placeholder="<?= __e('form.password_default') ?>"></div><?php endif; ?>
            <div class="form-group"><label><?= __e('form.company') ?></label><input name="company_name" value="<?= e($client['company_name']) ?>"></div>
            <div class="form-group"><label><?= __e('form.assigned_lawyer') ?></label>
                <select name="assigned_lawyer_id">
                    <option value=""><?= __e('form.unassigned') ?></option>
                    <?php foreach ($lawyers as $l): ?>
                        <option value="<?= (int)$l['id'] ?>" <?= (int)$client['assigned_lawyer_id'] === (int)$l['id'] ? 'selected' : '' ?>><?= e(full_name($l)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group full"><label><?= __e('common.address') ?></label><textarea name="address"><?= e($client['address']) ?></textarea></div>
            <div class="form-group full"><label><?= __e('clients.history_notes') ?></label><textarea name="notes"><?= e($client['notes']) ?></textarea></div>
            <div class="form-group"><label><?= __e('form.account_status') ?></label>
                <select name="is_active"><option value="1" <?= $client['is_active'] ? 'selected' : '' ?>><?= __e('status.active') ?></option><option value="0" <?= !$client['is_active'] ? 'selected' : '' ?>><?= __e('form.inactive') ?></option></select>
            </div>
            <div class="form-actions full">
                <button class="btn btn-primary" type="submit"><?= __e('clients.save') ?></button>
                <a class="btn btn-ghost" href="clients.php"><?= __e('common.cancel') ?></a>
            </div>
        </form>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

if ($action === 'view' && $id) {
    $stmt = $pdo->prepare('SELECT c.*, CONCAT(l.first_name," ",l.last_name) AS lawyer_name FROM users c LEFT JOIN users l ON l.id = c.assigned_lawyer_id WHERE c.id=? AND c.role="client"');
    $stmt->execute([$id]);
    $client = $stmt->fetch();
    if (!$client) { flash('error', __('clients.not_found')); redirect('clients.php'); }
    $cases = $pdo->prepare('SELECT * FROM cases WHERE client_id=? ORDER BY created_at DESC');
    $cases->execute([$id]);
    $cases = $cases->fetchAll();
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="panel">
        <div class="panel-header">
            <div>
                <h2><?= e(full_name($client)) ?></h2>
                <p class="muted"><?= e($client['company_name'] ?: __('clients.individual')) ?> · <?= __e('cases.lawyer_label') ?> <?= e($client['lawyer_name'] ?: __('common.unassigned')) ?></p>
            </div>
            <div class="quick-links">
                <a class="btn btn-sm btn-primary" href="?action=edit&id=<?= $id ?>"><?= __e('common.edit') ?></a>
                <a class="btn btn-sm btn-ghost" href="clients.php"><?= __e('common.back') ?></a>
            </div>
        </div>
        <div class="grid grid-2">
            <div class="list-item"><strong><?= __e('common.email') ?></strong><?= e($client['email']) ?></div>
            <div class="list-item"><strong><?= __e('common.phone') ?></strong><?= e($client['phone'] ?: __('common.em_dash')) ?></div>
            <div class="list-item span-2"><strong><?= __e('common.address') ?></strong><?= e($client['address'] ?: __('common.em_dash')) ?></div>
            <div class="list-item span-2"><strong><?= __e('clients.history_notes') ?></strong><?= nl2br(e($client['notes'] ?: __('common.em_dash'))) ?></div>
        </div>
    </div>
    <div class="panel">
        <h2><?= __e('clients.cases') ?></h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th><?= __e('common.case') ?> #</th><th><?= __e('common.title') ?></th><th><?= __e('common.status') ?></th><th><?= __e('common.filed') ?></th></tr></thead>
                <tbody>
                <?php foreach ($cases as $c): ?>
                    <tr>
                        <td><a href="cases.php?action=view&id=<?= (int)$c['id'] ?>"><?= e($c['case_number']) ?></a></td>
                        <td><?= e(t_content($c['title'])) ?></td>
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
        <h2><?= __e('clients.all') ?></h2>
        <a class="btn btn-primary btn-sm" href="?action=create"><?= __e('clients.add') ?></a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th><?= __e('common.name') ?></th><th><?= __e('common.company') ?></th><th><?= __e('common.lawyer') ?></th><th><?= __e('common.status') ?></th><th><?= __e('common.actions') ?></th></tr></thead>
            <tbody>
            <?php foreach ($clients as $c): ?>
                <tr>
                    <td><a href="?action=view&id=<?= (int)$c['id'] ?>"><strong><?= e(full_name($c)) ?></strong></a><div class="muted"><?= e($c['email']) ?></div></td>
                    <td><?= e($c['company_name'] ?: __('common.em_dash')) ?></td>
                    <td><?= e($c['lawyer_name'] ?: __('common.unassigned')) ?></td>
                    <td><?= status_badge($c['is_active'] ? 'active' : 'pending') ?></td>
                    <td class="quick-links">
                        <a class="chip" href="?action=edit&id=<?= (int)$c['id'] ?>"><?= __e('common.edit') ?></a>
                        <?php if (!$c['is_active']): ?>
                        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="form_action" value="approve"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="chip" type="submit"><?= __e('common.approve') ?></button></form>
                        <?php endif; ?>
                        <form method="post" style="display:inline" data-confirm="<?= __e('confirm.delete_client') ?>"><?= csrf_field() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="chip" type="submit"><?= __e('common.delete') ?></button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
