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
            flash('success', __('flash.lawyer.updated'));
        } else {
            $password = password_hash(post('password') ?: 'password123', PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (role, first_name, last_name, username, email, password, phone, address, specialization, bar_number, availability, is_active) VALUES ("lawyer",?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    post('first_name'), post('last_name'), post('username'), post('email'), $password, post('phone'),
                    post('address'), post('specialization'), post('bar_number'), post('availability'), (int) (post('is_active') === '1'),
                ]);
            flash('success', __('flash.lawyer.added'));
        }
        redirect('lawyers.php');
    }
    if ($postAction === 'delete') {
        $pdo->prepare('DELETE FROM users WHERE id=? AND role="lawyer"')->execute([(int) post('id')]);
        flash('success', __('flash.lawyer.removed'));
        redirect('lawyers.php');
    }
    if ($postAction === 'assign_case') {
        $pdo->prepare('UPDATE cases SET lawyer_id=? WHERE id=?')->execute([(int) post('lawyer_id'), (int) post('case_id')]);
        create_notification($pdo, (int) post('lawyer_id'), 'notify.case_assigned_short', 'A case has been assigned to you.', 'case', '../lawyer/cases.php', current_user()['id']);
        flash('success', __('flash.case.assigned'));
        redirect('lawyers.php?action=view&id=' . (int) post('lawyer_id'));
    }
}

$pageTitle = __('page.lawyers');
$pageSubtitle = __('page.lawyers.subtitle');
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
                <p class="entity-form-eyebrow"><?= $isEdit ? __e('lawyers.eyebrow.edit') : __e('lawyers.eyebrow.create') ?></p>
                <h2><?= $isEdit ? __e('lawyers.edit') : __e('lawyers.add') ?></h2>
                <p class="muted"><?= $isEdit ? __e('lawyers.form.help.edit') : __e('lawyers.form.help.create') ?></p>
            </div>
            <p class="entity-form-required-note"><span class="req">*</span> <?= __e('form.required_fields') ?></p>
        </div>

        <form method="post">
            <div class="entity-form-body">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="save">
                <input type="hidden" name="id" value="<?= (int)$lawyer['id'] ?>">

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3><?= __e('form.section.personal') ?></h3>
                        <p><?= __e('lawyers.section.personal_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group">
                                <label for="first_name"><?= __e('form.first_name') ?> <span class="req" title="<?= __e('form.required') ?>">*</span></label>
                                <input id="first_name" name="first_name" required value="<?= e($lawyer['first_name']) ?>" placeholder="<?= __e('form.placeholder.first_name') ?>">
                            </div>
                            <div class="form-group">
                                <label for="last_name"><?= __e('form.last_name') ?> <span class="req" title="<?= __e('form.required') ?>">*</span></label>
                                <input id="last_name" name="last_name" required value="<?= e($lawyer['last_name']) ?>" placeholder="<?= __e('form.placeholder.last_name') ?>">
                            </div>
                        </div>
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group">
                                <label for="email"><?= __e('common.email') ?> <span class="req" title="<?= __e('form.required') ?>">*</span></label>
                                <input id="email" type="email" name="email" required value="<?= e($lawyer['email']) ?>" placeholder="<?= __e('form.placeholder.email_firm') ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone"><?= __e('common.phone') ?></label>
                                <input id="phone" name="phone" value="<?= e($lawyer['phone']) ?>" placeholder="<?= __e('form.placeholder.phone') ?>">
                            </div>
                        </div>
                        <div class="form-group full">
                            <label for="address"><?= __e('common.address') ?></label>
                            <textarea id="address" name="address" rows="2" placeholder="<?= __e('form.placeholder.address_office') ?>"><?= e($lawyer['address']) ?></textarea>
                        </div>
                    </div>
                </section>

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3><?= __e('form.section.account') ?></h3>
                        <p><?= __e('lawyers.section.account_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row">
                            <div class="form-group">
                                <label for="username"><?= __e('form.username') ?> <span class="req" title="<?= __e('form.required') ?>">*</span></label>
                                <input id="username" name="username" required value="<?= e($lawyer['username']) ?>" placeholder="<?= __e('form.placeholder.username') ?>" autocomplete="off">
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
                                    <option value="1" <?= $lawyer['is_active'] ? 'selected' : '' ?>><?= __e('status.active') ?></option>
                                    <option value="0" <?= !$lawyer['is_active'] ? 'selected' : '' ?>><?= __e('form.inactive') ?></option>
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
                        <h3><?= __e('form.section.practice') ?></h3>
                        <p><?= __e('lawyers.section.practice_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row">
                            <div class="form-group">
                                <label for="specialization"><?= __e('form.specialization') ?></label>
                                <input id="specialization" name="specialization" value="<?= e($lawyer['specialization']) ?>" placeholder="<?= __e('form.placeholder.specialization') ?>">
                            </div>
                            <div class="form-group">
                                <label for="bar_number"><?= __e('form.bar_number') ?></label>
                                <input id="bar_number" name="bar_number" value="<?= e($lawyer['bar_number']) ?>" placeholder="<?= __e('form.placeholder.bar_number') ?>">
                            </div>
                            <div class="form-group">
                                <label for="availability"><?= __e('form.availability') ?> <span class="req" title="<?= __e('form.required') ?>">*</span></label>
                                <select id="availability" name="availability" required>
                                    <?php foreach (['available', 'busy', 'unavailable'] as $val): ?>
                                        <option value="<?= $val ?>" <?= $lawyer['availability'] === $val ? 'selected' : '' ?>><?= e(translate_status($val)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="entity-form-footer">
                <a class="btn btn-secondary" href="lawyers.php"><?= __e('common.cancel') ?></a>
                <button class="btn btn-primary" type="submit"><?= $isEdit ? __e('common.save_changes') : __e('lawyers.save') ?></button>
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
    if (!$lawyer) { flash('error', __('lawyers.not_found')); redirect('lawyers.php'); }
    $cases = $pdo->prepare('SELECT c.*, CONCAT(u.first_name," ",u.last_name) AS client_name FROM cases c JOIN users u ON u.id=c.client_id WHERE c.lawyer_id=? ORDER BY c.updated_at DESC');
    $cases->execute([$id]);
    $cases = $cases->fetchAll();
    $openCases = count(array_filter($cases, fn($c) => $c['status'] !== 'closed'));
    $unassigned = $pdo->query("SELECT id, case_number, title FROM cases WHERE lawyer_id IS NULL OR lawyer_id=0 ORDER BY created_at DESC")->fetchAll();
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="grid grid-3">
        <div class="stat-card"><div class="stat-label"><?= __e('lawyers.workload_open') ?></div><div class="stat-value"><?= $openCases ?></div></div>
        <div class="stat-card"><div class="stat-label"><?= __e('lawyers.total_cases') ?></div><div class="stat-value"><?= count($cases) ?></div></div>
        <div class="stat-card"><div class="stat-label"><?= __e('form.availability') ?></div><div class="stat-value" style="font-size:1.4rem;"><?= status_badge($lawyer['availability']) ?></div></div>
    </div>
    <div class="panel">
        <div class="panel-header">
            <div>
                <h2><?= e(full_name($lawyer)) ?></h2>
                <p class="muted"><?= e($lawyer['specialization'] ?: __('lawyers.general_practice')) ?> · <?= e($lawyer['bar_number'] ?: __('lawyers.no_bar')) ?></p>
            </div>
            <a class="btn btn-primary btn-sm" href="?action=edit&id=<?= $id ?>"><?= __e('lawyers.edit_profile') ?></a>
        </div>
        <div class="grid grid-2">
            <div class="list-item"><strong><?= __e('common.email') ?></strong><?= e($lawyer['email']) ?></div>
            <div class="list-item"><strong><?= __e('common.phone') ?></strong><?= e($lawyer['phone'] ?: __('common.em_dash')) ?></div>
        </div>
    </div>
    <div class="panel">
        <h2><?= __e('lawyers.assign_case') ?></h2>
        <form method="post" class="form-grid entity-inline-form">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="assign_case"><input type="hidden" name="lawyer_id" value="<?= $id ?>">
            <div class="entity-field-row entity-field-row--2">
                <div class="form-group"><label><?= __e('lawyers.unassigned_case') ?></label>
                    <select name="case_id" required>
                        <option value=""><?= __e('form.select_case') ?></option>
                        <?php foreach ($unassigned as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= e($c['case_number'] . ' — ' . $c['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><button class="btn btn-accent" type="submit"><?= __e('lawyers.assign') ?></button></div>
            </div>
        </form>
    </div>
    <div class="panel">
        <h2><?= __e('lawyers.case_list') ?></h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th><?= __e('common.case') ?></th><th><?= __e('common.client') ?></th><th><?= __e('common.status') ?></th><th><?= __e('common.priority') ?></th></tr></thead>
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
$totalLawyers = count($lawyers);
$perPage = 10;
$page = max(1, (int) get('page', 1));
$totalPages = max(1, (int) ceil($totalLawyers / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$pageLawyers = array_slice($lawyers, $offset, $perPage);
$shownFrom = $totalLawyers === 0 ? 0 : $offset + 1;
$shownTo = min($offset + count($pageLawyers), $totalLawyers);
?>
<div class="panel case-list-panel">
    <div class="case-list-head">
        <div class="case-list-title">
            <h2><?= __e('lawyers.list') ?></h2>
        </div>
        <a class="btn btn-primary btn-sm" href="?action=create"><?= __e('lawyers.add') ?></a>
    </div>
    <div class="table-wrap case-table-wrap">
        <table class="case-table">
            <thead><tr><th><?= __e('common.lawyer') ?></th><th><?= __e('form.specialization') ?></th><th><?= __e('common.workload') ?></th><th><?= __e('form.availability') ?></th><th class="col-actions"><?= __e('common.actions') ?></th></tr></thead>
            <tbody>
            <?php foreach ($pageLawyers as $l): ?>
                <tr>
                    <td><a href="?action=view&id=<?= (int)$l['id'] ?>"><strong><?= e(full_name($l)) ?></strong></a><div class="muted"><?= e($l['email']) ?></div></td>
                    <td><?= e($l['specialization'] ?: __('common.em_dash')) ?></td>
                    <td><?= e(__('lawyers.open_count', ['count' => (int)$l['open_cases']])) ?></td>
                    <td><?= status_badge($l['availability']) ?></td>
                    <td class="col-actions">
                        <div class="row-actions">
                            <a class="btn btn-row-edit btn-sm" href="?action=edit&id=<?= (int)$l['id'] ?>"><?= __e('common.edit') ?></a>
                            <form method="post" data-confirm="<?= __e('confirm.remove_lawyer') ?>"><?= csrf_field() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-row-delete btn-sm" type="submit"><?= __e('common.remove') ?></button></form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$lawyers): ?>
                <tr><td colspan="5" class="case-empty muted"><?= __e('lawyers.empty.none') ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="case-list-foot">
        <p class="case-list-footer muted"><?= e(__($totalLawyers === 1 ? 'lawyers.pager.showing_one' : 'lawyers.pager.showing_many', ['from' => (int)$shownFrom, 'to' => (int)$shownTo, 'total' => (int)$totalLawyers])) ?></p>
        <?php if ($totalPages > 1): ?>
        <nav class="case-list-pager" aria-label="<?= __e('lawyers.pagination.aria') ?>">
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
