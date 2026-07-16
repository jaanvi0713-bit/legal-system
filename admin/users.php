<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);
$pdo = db();
$action = get('action', 'list');
$id = (int) get('id', 0);
$permissionOptions = ['clients', 'lawyers', 'cases', 'appointments', 'court', 'finance', 'reports', 'notifications'];
$permissionNavKeys = [
    'clients' => 'nav.clients',
    'lawyers' => 'nav.lawyers',
    'cases' => 'nav.cases',
    'appointments' => 'nav.appointments',
    'court' => 'nav.court',
    'finance' => 'nav.finance',
    'reports' => 'page.reports',
    'notifications' => 'nav.notifications',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'save') {
        $editId = (int) post('id');
        $role = post('role', 'client');
        $perms = $role === 'staff'
            ? json_encode(array_values($_POST['permissions'] ?? []))
            : null;

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
$pageSubtitle = __('page.users');
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
    $selectedPerms = json_decode($row['permissions'] ?: '[]', true) ?: [];
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="panel">
        <h2><?= $id ? __e('users.edit') : __e('users.create') ?></h2>
        <form method="post" class="form-grid" id="user-form">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <div class="form-group"><label><?= __e('form.first_name') ?></label><input name="first_name" required value="<?= e($row['first_name']) ?>"></div>
            <div class="form-group"><label><?= __e('form.last_name') ?></label><input name="last_name" required value="<?= e($row['last_name']) ?>"></div>
            <div class="form-group"><label><?= __e('form.username') ?></label><input name="username" required value="<?= e($row['username']) ?>"></div>
            <div class="form-group"><label><?= __e('common.email') ?></label><input type="email" name="email" required value="<?= e($row['email']) ?>"></div>
            <div class="form-group"><label><?= __e('common.phone') ?></label><input name="phone" value="<?= e($row['phone']) ?>"></div>
            <div class="form-group"><label><?= __e('users.access.portal') ?></label>
                <select name="role" id="user-role">
                    <?php foreach (['admin', 'staff', 'lawyer', 'client'] as $r): ?>
                        <option value="<?= e($r) ?>" <?= $row['role'] === $r ? 'selected' : '' ?>><?= e(translate_role($r)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label><?= __e('common.status') ?></label>
                <select name="is_active">
                    <option value="1" <?= $row['is_active'] ? 'selected' : '' ?>><?= __e('status.active') ?></option>
                    <option value="0" <?= !$row['is_active'] ? 'selected' : '' ?>><?= __e('form.deactivated') ?></option>
                </select>
            </div>
            <div class="form-group full"><label><?= $id ? __e('form.new_password_optional') : __e('form.password') ?></label><input name="password" placeholder="<?= $id ? __e('form.password_keep') : __e('form.password_default') ?>"></div>
            <div class="form-group full" id="staff-permissions" hidden>
                <label><?= __e('users.staff_perms') ?></label>
                <div class="quick-links">
                    <?php foreach ($permissionOptions as $p): ?>
                        <label class="chip">
                            <input type="checkbox" name="permissions[]" value="<?= e($p) ?>" <?= in_array($p, $selectedPerms, true) ? 'checked' : '' ?>>
                            <?= e(__($permissionNavKeys[$p])) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <span class="field-hint"><?= __e('users.staff_perms_help') ?></span>
            </div>
            <div class="form-actions full">
                <button class="btn btn-primary" type="submit"><?= __e('common.save') ?></button>
                <a class="btn btn-ghost" href="users.php"><?= __e('common.cancel') ?></a>
            </div>
        </form>
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
        $filters = ['' => __('common.all'), 'admin' => __('role.admin'), 'staff' => __('role.staff'), 'lawyer' => __('role.lawyer'), 'client' => __('role.client')];
        foreach ($filters as $key => $label):
            $href = $key === '' ? 'users.php' : 'users.php?role=' . urlencode($key);
        ?>
            <a class="chip <?= $filter === $key ? 'active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th><?= __e('common.name') ?></th><th><?= __e('users.access.portal') ?></th><th><?= __e('common.access') ?></th><th><?= __e('common.last_login') ?></th><th><?= __e('common.status') ?></th><th><?= __e('common.actions') ?></th></tr>
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
                            <?= e($perms ? implode(', ', array_map(fn($p) => __($permissionNavKeys[$p] ?? $p), $perms)) : __('users.access.no_modules')) ?>
                        <?php elseif ($u['role'] === 'admin'): ?>
                            <?= __e('users.access.full') ?>
                        <?php else: ?>
                            <?= __e('users.access.portal') ?>
                        <?php endif; ?>
                    </td>
                    <td><?= e(format_datetime($u['last_login'])) ?></td>
                    <td><?= status_badge($u['is_active'] ? 'active' : 'unavailable') ?></td>
                    <td class="quick-links">
                        <a class="chip" href="?action=edit&id=<?= (int) $u['id'] ?>"><?= __e('common.edit') ?></a>
                        <form method="post" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form_action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button class="chip" type="submit"><?= $u['is_active'] ? __e('common.deactivate') : __e('common.activate') ?></button>
                        </form>
                        <form method="post" style="display:inline" data-confirm="<?= __e('confirm.reset_password') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form_action" value="reset">
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button class="chip" type="submit"><?= __e('common.reset_pw') ?></button>
                        </form>
                        <?php if ($u['role'] === 'staff'): ?>
                            <form method="post" style="display:inline" data-confirm="<?= __e('confirm.remove_staff') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="form_action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button class="chip" type="submit"><?= __e('common.remove') ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
