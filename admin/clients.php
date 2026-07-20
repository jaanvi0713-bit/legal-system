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
            create_notification($pdo, current_user()['id'], 'notify.client_created', post('first_name') . ' ' . post('last_name') . ' added.', 'info', 'clients.php?action=view&id=' . $newId, current_user()['id']);
            if (post('assigned_lawyer_id')) {
                create_notification($pdo, (int) post('assigned_lawyer_id'), 'notify.client_assigned', 'Client ' . post('first_name') . ' ' . post('last_name') . ' assigned to you.', 'case', '../lawyer/clients.php', current_user()['id']);
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
$pageSubtitle = __('page.clients.subtitle');
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
    <div class="entity-form-wrap">
    <div class="entity-form panel">
        <div class="entity-form-hero">
            <div>
                <p class="entity-form-eyebrow"><?= $isEdit ? __e('clients.eyebrow.edit') : __e('clients.eyebrow.create') ?></p>
                <h2><?= $isEdit ? __e('clients.edit') : __e('clients.add') ?></h2>
                <p class="muted"><?= $isEdit ? __e('clients.form.help.edit') : __e('clients.form.help.create') ?></p>
            </div>
            <p class="entity-form-required-note"><span class="req">*</span> <?= __e('form.required_fields') ?></p>
        </div>

        <form method="post">
            <div class="entity-form-body">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="save">
                <input type="hidden" name="id" value="<?= (int)$client['id'] ?>">

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3><?= __e('form.section.personal') ?></h3>
                        <p><?= __e('clients.section.personal_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group">
                                <label for="first_name"><?= __e('form.first_name') ?> <span class="req" title="<?= __e('form.required') ?>">*</span></label>
                                <input id="first_name" name="first_name" required value="<?= e($client['first_name']) ?>" placeholder="<?= __e('form.placeholder.first_name') ?>">
                            </div>
                            <div class="form-group">
                                <label for="last_name"><?= __e('form.last_name') ?> <span class="req" title="<?= __e('form.required') ?>">*</span></label>
                                <input id="last_name" name="last_name" required value="<?= e($client['last_name']) ?>" placeholder="<?= __e('form.placeholder.last_name') ?>">
                            </div>
                        </div>
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group">
                                <label for="email"><?= __e('common.email') ?> <span class="req" title="<?= __e('form.required') ?>">*</span></label>
                                <input id="email" type="email" name="email" required value="<?= e($client['email']) ?>" placeholder="<?= __e('form.placeholder.email') ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone"><?= __e('common.phone') ?></label>
                                <input id="phone" name="phone" value="<?= e($client['phone']) ?>" placeholder="<?= __e('form.placeholder.phone') ?>">
                            </div>
                        </div>
                        <div class="form-group full">
                            <label for="address"><?= __e('common.address') ?></label>
                            <textarea id="address" name="address" rows="2" placeholder="<?= __e('form.placeholder.address') ?>"><?= e($client['address']) ?></textarea>
                        </div>
                    </div>
                </section>

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3><?= __e('form.section.account') ?></h3>
                        <p><?= __e('clients.section.account_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row">
                            <div class="form-group">
                                <label for="username"><?= __e('form.username') ?> <span class="req" title="<?= __e('form.required') ?>">*</span></label>
                                <input id="username" name="username" required value="<?= e($client['username']) ?>" placeholder="<?= __e('form.placeholder.username') ?>" autocomplete="off">
                            </div>
                            <?php if (!$isEdit): ?>
                            <div class="form-group">
                                <label for="password"><?= __e('form.temp_password') ?></label>
                                <input id="password" name="password" type="text" placeholder="<?= __e('form.password_keep') ?>" autocomplete="off">
                            </div>
                            <?php endif; ?>
                            <div class="form-group">
                                <label for="is_active"><?= __e('form.account_status') ?> <span class="req" title="<?= __e('form.required') ?>">*</span></label>
                                <select id="is_active" name="is_active" required>
                                    <option value="1" <?= $client['is_active'] ? 'selected' : '' ?>><?= __e('status.active') ?></option>
                                    <option value="0" <?= !$client['is_active'] ? 'selected' : '' ?>><?= __e('form.inactive_pending') ?></option>
                                </select>
                            </div>
                        </div>
                        <?php if (!$isEdit): ?>
                        <div class="form-group full entity-field-notes">
                            <span class="field-hint"><?= __e('form.hint.password_default') ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3><?= __e('form.section.firm_assignment') ?></h3>
                        <p><?= __e('clients.section.firm_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group">
                                <label for="company_name"><?= __e('form.company') ?></label>
                                <input id="company_name" name="company_name" value="<?= e($client['company_name']) ?>" placeholder="<?= __e('form.placeholder.company') ?>">
                            </div>
                            <div class="form-group">
                                <label for="assigned_lawyer_id"><?= __e('form.assigned_lawyer') ?></label>
                                <select id="assigned_lawyer_id" name="assigned_lawyer_id">
                                    <option value=""><?= __e('form.unassigned') ?></option>
                                    <?php foreach ($lawyers as $l): ?>
                                        <option value="<?= (int)$l['id'] ?>" <?= (int)$client['assigned_lawyer_id'] === (int)$l['id'] ? 'selected' : '' ?>><?= e(full_name($l)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group full">
                            <label for="notes"><?= __e('form.notes_history') ?></label>
                            <textarea id="notes" name="notes" rows="3" placeholder="<?= __e('form.placeholder.notes_client') ?>"><?= e($client['notes']) ?></textarea>
                        </div>
                    </div>
                </section>
            </div>

            <div class="entity-form-footer">
                <a class="btn btn-secondary" href="clients.php"><?= __e('common.cancel') ?></a>
                <button class="btn btn-primary" type="submit"><?= $isEdit ? __e('common.save_changes') : __e('clients.save') ?></button>
            </div>
        </form>
    </div>
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
                <p class="muted"><?= e($client['company_name'] ?: __('clients.individual')) ?> · <?= __e('clients.lawyer_label') ?> <?= e($client['lawyer_name'] ?: __('common.unassigned')) ?></p>
            </div>
            <div class="quick-links">
                <a class="btn btn-primary btn-sm" href="?action=edit&id=<?= $id ?>"><?= __e('common.edit') ?></a>
                <a class="btn btn-secondary btn-sm" href="clients.php"><?= __e('common.back') ?></a>
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
                <thead><tr><th><?= __e('common.case_number') ?></th><th><?= __e('common.title') ?></th><th><?= __e('common.status') ?></th><th><?= __e('common.filed') ?></th></tr></thead>
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
$totalClients = count($clients);
$perPage = 10;
$page = max(1, (int) get('page', 1));
$totalPages = max(1, (int) ceil($totalClients / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$pageClients = array_slice($clients, $offset, $perPage);
$shownFrom = $totalClients === 0 ? 0 : $offset + 1;
$shownTo = min($offset + count($pageClients), $totalClients);
?>
<div class="panel case-list-panel">
    <div class="case-list-head">
        <div class="case-list-title">
            <h2><?= __e('clients.all') ?></h2>
        </div>
        <a class="btn btn-primary btn-sm" href="?action=create"><?= __e('clients.add') ?></a>
    </div>
    <div class="table-wrap case-table-wrap">
        <table class="case-table">
            <thead><tr><th><?= __e('common.name_col') ?></th><th><?= __e('common.company_col') ?></th><th><?= __e('common.lawyer_col') ?></th><th><?= __e('common.status') ?></th><th class="col-actions"><?= __e('common.actions') ?></th></tr></thead>
            <tbody>
            <?php foreach ($pageClients as $c): ?>
                <tr>
                    <td><a href="?action=view&id=<?= (int)$c['id'] ?>"><strong><?= e(full_name($c)) ?></strong></a><div class="muted"><?= e($c['email']) ?></div></td>
                    <td><?= e($c['company_name'] ?: __('common.em_dash')) ?></td>
                    <td><?= e($c['lawyer_name'] ?: __('common.unassigned')) ?></td>
                    <td><?= status_badge($c['is_active'] ? 'active' : 'pending') ?></td>
                    <td class="col-actions">
                        <div class="row-actions">
                            <a class="btn btn-row-edit btn-sm" href="?action=edit&id=<?= (int)$c['id'] ?>"><?= __e('common.edit') ?></a>
                            <?php if (!$c['is_active']): ?>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="approve"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-row-approve btn-sm" type="submit"><?= __e('common.approve') ?></button></form>
                            <?php endif; ?>
                            <form method="post" data-confirm="<?= __e('confirm.delete_client') ?>"><?= csrf_field() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-row-delete btn-sm" type="submit"><?= __e('common.delete') ?></button></form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$clients): ?>
                <tr><td colspan="5" class="case-empty muted"><?= __e('clients.empty.none') ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="case-list-foot">
        <p class="case-list-footer muted"><?= e(__($totalClients === 1 ? 'clients.pager.showing_one' : 'clients.pager.showing_many', ['from' => (int)$shownFrom, 'to' => (int)$shownTo, 'total' => (int)$totalClients])) ?></p>
        <?php if ($totalPages > 1): ?>
        <nav class="case-list-pager" aria-label="<?= __e('clients.pagination.aria') ?>">
            <?php if ($page > 1): ?>
            <a class="case-page-btn" href="?page=<?= $page - 1 ?>" aria-label="<?= __e('cases.pagination.prev') ?>">‹</a>
            <?php else: ?>
            <span class="case-page-btn is-disabled" aria-disabled="true">‹</span>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a class="case-page-btn<?= $p === $page ? ' is-active' : '' ?>" href="?page=<?= $p ?>"<?= $p === $page ? ' aria-current="page"' : '' ?>><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a class="case-page-btn" href="?page=<?= $page + 1 ?>" aria-label="<?= __e('cases.pagination.next') ?>">›</a>
            <?php else: ?>
            <span class="case-page-btn is-disabled" aria-disabled="true">›</span>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
