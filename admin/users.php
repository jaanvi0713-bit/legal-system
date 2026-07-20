<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/role-access.php';
require_role(['admin']);
$pdo = db();
$action = get('action', 'list');
$id = (int) get('id', 0);
$permissionOptions = ['clients', 'lawyers', 'cases', 'appointments', 'court', 'reports', 'notifications'];
$permissionLabel = static function (string $p): string {
    $map = [
        'clients' => 'nav.clients',
        'lawyers' => 'nav.lawyers',
        'cases' => 'nav.cases',
        'appointments' => 'nav.appointments',
        'court' => 'nav.court',
        'reports' => 'page.reports',
        'notifications' => 'nav.notifications',
    ];
    return __($map[$p] ?? $p);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'save') {
        $editId = (int) post('id');
        $role = post('role', 'client');
        $perms = null;
        if ($role === 'staff') {
            $accessRole = post('staff_access_role', 'staff');
            $config = role_access_load($pdo);
            $perm = $config['permissions'][$accessRole] ?? role_access_default_permissions()['staff'];
            $perms = json_encode([
                'access_role' => $accessRole,
                'modules' => array_values($perm['modules'] ?? []),
                'assigned_cases' => !empty($perm['assigned_cases']),
                'read_only' => !empty($perm['read_only']),
            ], JSON_UNESCAPED_UNICODE);
        }

        if ($editId) {
            $pdo->prepare('UPDATE users SET first_name=?, last_name=?, username=?, email=?, phone=?, role=?, permissions=?, is_active=? WHERE id=?')
                ->execute([
                    post('first_name'),
                    post('last_name'),
                    post('username'),
                    post('email'),
                    post('phone'),
                    $role,
                    $perms,
                    (int) (post('is_active') === '1'),
                    $editId,
                ]);
            if (post('password')) {
                $pdo->prepare('UPDATE users SET password=? WHERE id=?')
                    ->execute([password_hash(post('password'), PASSWORD_DEFAULT), $editId]);
            }
            flash('success', __('flash.user.updated'));
        } else {
            $pdo->prepare('INSERT INTO users (role, first_name, last_name, username, email, password, phone, permissions, is_active) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([
                    $role,
                    post('first_name'),
                    post('last_name'),
                    post('username'),
                    post('email'),
                    password_hash(post('password') ?: 'password123', PASSWORD_DEFAULT),
                    post('phone'),
                    $perms,
                    (int) (post('is_active') === '1'),
                ]);
            flash('success', __('flash.user.created'));
        }
        redirect('users.php');
    }
    if ($fa === 'toggle') {
        $pdo->prepare('UPDATE users SET is_active = IF(is_active=1,0,1) WHERE id=?')->execute([(int) post('id')]);
        flash('success', __('flash.user.status'));
        redirect('users.php');
    }
    if ($fa === 'reset') {
        $pdo->prepare('UPDATE users SET password=? WHERE id=?')
            ->execute([password_hash('password123', PASSWORD_DEFAULT), (int) post('id')]);
        flash('success', __('flash.user.password_reset'));
        redirect('users.php');
    }
    if ($fa === 'delete') {
        $pdo->prepare('DELETE FROM users WHERE id=? AND role="staff"')->execute([(int) post('id')]);
        flash('success', __('flash.staff.removed'));
        redirect('users.php');
    }
}

$pageTitle = __('page.users');
$pageSubtitle = __('page.users.subtitle');
$portal = 'admin';
$activeNav = 'users';

if ($action === 'create' || ($action === 'edit' && $id)) {
    $row = [
        'id' => 0,
        'first_name' => '',
        'last_name' => '',
        'username' => '',
        'email' => '',
        'phone' => '',
        'role' => 'client',
        'is_active' => 1,
        'permissions' => '[]',
    ];
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: $row;
    }
    $permData = json_decode($row['permissions'] ?: '{}', true);
    if (!is_array($permData)) {
        $permData = [];
    }
    $selectedAccessRole = (string) ($permData['access_role'] ?? 'staff');
    $roleAccessConfig = role_access_load($pdo);
    require __DIR__ . '/../includes/header.php';
    $isEdit = (bool) $id;
    ?>
    <div class="entity-form-wrap">
    <div class="entity-form panel">
        <div class="entity-form-hero">
            <div>
                <p class="entity-form-eyebrow"><?= $isEdit ? __e('users.eyebrow.edit') : __e('users.eyebrow.create') ?></p>
                <h2><?= $isEdit ? __e('users.edit') : __e('users.create') ?></h2>
                <p class="muted"><?= $isEdit ? __e('users.form.help.edit') : __e('users.form.help.create') ?></p>
            </div>
            <p class="entity-form-required-note"><span class="req">*</span> <?= __e('form.required_fields') ?></p>
        </div>
        <form method="post" id="user-form">
            <div class="entity-form-body">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="save">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3><?= __e('form.section.profile') ?></h3>
                        <p><?= __e('users.section.profile_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group">
                                <label for="first_name"><?= __e('form.first_name') ?> <span class="req">*</span></label>
                                <input id="first_name" name="first_name" required value="<?= e($row['first_name']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="last_name"><?= __e('form.last_name') ?> <span class="req">*</span></label>
                                <input id="last_name" name="last_name" required value="<?= e($row['last_name']) ?>">
                            </div>
                        </div>
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group">
                                <label for="email"><?= __e('common.email') ?> <span class="req">*</span></label>
                                <input id="email" type="email" name="email" required value="<?= e($row['email']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone"><?= __e('common.phone') ?></label>
                                <input id="phone" name="phone" value="<?= e($row['phone']) ?>">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3><?= __e('form.section.access') ?></h3>
                        <p><?= __e('users.section.access_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row">
                            <div class="form-group">
                                <label for="username"><?= __e('form.username') ?> <span class="req">*</span></label>
                                <input id="username" name="username" required value="<?= e($row['username']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="password"><?= $isEdit ? __e('form.new_password') : __e('form.password') ?><?= $isEdit ? '' : ' <span class="req">*</span>' ?></label>
                                <input id="password" name="password" placeholder="<?= $isEdit ? __e('form.password_keep') : __e('form.password_default') ?>">
                            </div>
                            <div class="form-group">
                                <label for="user-role"><?= __e('common.role') ?> <span class="req">*</span></label>
                                <select name="role" id="user-role" required>
                                    <?php foreach (['admin', 'staff', 'lawyer', 'client'] as $r): ?>
                                        <option value="<?= e($r) ?>" <?= $row['role'] === $r ? 'selected' : '' ?>><?= e(translate_role($r)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group">
                                <label for="is_active"><?= __e('common.status') ?> <span class="req">*</span></label>
                                <select id="is_active" name="is_active" required>
                                    <option value="1" <?= $row['is_active'] ? 'selected' : '' ?>><?= __e('status.active') ?></option>
                                    <option value="0" <?= !$row['is_active'] ? 'selected' : '' ?>><?= __e('users.status.deactivated') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group full entity-field-notes">
                            <span class="field-hint"><?= $isEdit ? __e('form.hint.password_keep') : __e('form.hint.password_default') ?></span>
                        </div>
                        <div class="form-group full" id="staff-permissions" hidden>
                            <label for="staff_access_role"><?= __e('users.staff_access_role') ?></label>
                            <select id="staff_access_role" name="staff_access_role">
                                <?php foreach ($roleAccessConfig['roles'] as $accessRole): ?>
                                    <option value="<?= e($accessRole['id']) ?>" <?= $selectedAccessRole === $accessRole['id'] ? 'selected' : '' ?>><?= e($accessRole['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field-hint"><?= __e('users.staff_access_role_help') ?> <a href="settings.php?tab=roles"><?= __e('settings.tab.roles') ?></a></span>
                        </div>
                    </div>
                </section>
            </div>
            <div class="entity-form-footer">
                <a class="btn btn-secondary" href="users.php"><?= __e('common.cancel') ?></a>
                <button class="btn btn-primary" type="submit"><?= $isEdit ? __e('common.save_changes') : __e('users.create') ?></button>
            </div>
        </form>
    </div>
    </div>
    <script>
    (function () {
      const role = document.getElementById('user-role');
      const perms = document.getElementById('staff-permissions');
      if (!role || !perms) return;
      function sync() { perms.hidden = role.value !== 'staff'; }
      role.addEventListener('change', sync);
      sync();
    })();
    </script>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$filter = get('role', '');
$allowedFilters = ['', 'admin', 'staff', 'lawyer', 'client'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = '';
}

$sql = 'SELECT * FROM users';
$params = [];
if ($filter !== '') {
    $sql .= ' WHERE role = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY FIELD(role, "admin", "staff", "lawyer", "client"), first_name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <div class="panel-header">
        <h2><?= __e('users.list') ?></h2>
        <a class="btn btn-primary btn-sm" href="?action=create"><?= __e('users.create') ?></a>
    </div>
    <div class="quick-links" style="margin-bottom:1rem;">
        <?php
        $filters = [
            '' => __('users.filter.all'),
            'admin' => __('users.filter.admin'),
            'staff' => __('users.filter.staff'),
            'lawyer' => __('users.filter.lawyer'),
            'client' => __('users.filter.client'),
        ];
        foreach ($filters as $key => $label):
            $href = $key === '' ? 'users.php' : 'users.php?role=' . urlencode($key);
        ?>
            <a class="chip <?= $filter === $key ? 'active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th><?= __e('users.col.user') ?></th><th><?= __e('common.role') ?></th><th><?= __e('common.access') ?></th><th><?= __e('common.last_login') ?></th><th><?= __e('common.status') ?></th><th><?= __e('common.actions') ?></th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u):
                $perms = $u['role'] === 'staff' ? (json_decode($u['permissions'] ?: '[]', true) ?: []) : [];
            ?>
                <tr>
                    <td>
                        <strong><?= e(full_name($u)) ?></strong>
                        <div class="muted"><?= e($u['username']) ?> · <?= e($u['email']) ?></div>
                    </td>
                    <td><?= e(translate_role($u['role'])) ?></td>
                    <td>
                        <?php if ($u['role'] === 'staff'): ?>
                            <?= e($perms ? implode(', ', array_map($permissionLabel, $perms)) : __('users.access.no_modules')) ?>
                        <?php elseif ($u['role'] === 'admin'): ?>
                            <?= __e('users.access.full') ?>
                        <?php else: ?>
                            <?= __e('users.access.portal') ?>
                        <?php endif; ?>
                    </td>
                    <td><?= e(format_datetime($u['last_login'])) ?></td>
                    <td><?= status_badge($u['is_active'] ? 'active' : 'unavailable') ?></td>
                    <td class="col-actions">
                        <div class="row-actions">
                            <a class="btn btn-row-edit btn-sm" href="?action=edit&id=<?= (int) $u['id'] ?>"><?= __e('common.edit') ?></a>
                            <form method="post"<?= $u['is_active'] ? ' data-confirm="' . __e('confirm.deactivate_user') . '"' : '' ?>>
                                <?= csrf_field() ?>
                                <input type="hidden" name="form_action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button class="btn btn-sm <?= $u['is_active'] ? 'btn-row-delete' : 'btn-row-approve' ?>" type="submit"><?= $u['is_active'] ? __e('common.deactivate') : __e('common.activate') ?></button>
                            </form>
                            <form method="post" data-confirm="<?= __e('confirm.reset_password') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="form_action" value="reset">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button class="btn btn-row-edit btn-sm" type="submit"><?= __e('common.reset_pw') ?></button>
                            </form>
                            <?php if ($u['role'] === 'staff'): ?>
                                <form method="post" data-confirm="<?= __e('confirm.remove_staff') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="form_action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <button class="btn btn-row-delete btn-sm" type="submit"><?= __e('common.remove') ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
