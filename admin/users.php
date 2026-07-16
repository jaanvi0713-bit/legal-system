<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);
$pdo = db();
$action = get('action', 'list');
$id = (int) get('id', 0);
$permissionOptions = ['clients', 'lawyers', 'cases', 'appointments', 'court', 'reports', 'notifications'];

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
            flash('success', 'User updated.');
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
            flash('success', 'User created.');
        }
        redirect('users.php');
    }
    if ($fa === 'toggle') {
        $pdo->prepare('UPDATE users SET is_active = IF(is_active=1,0,1) WHERE id=?')->execute([(int) post('id')]);
        flash('success', 'Account status updated.');
        redirect('users.php');
    }
    if ($fa === 'reset') {
        $pdo->prepare('UPDATE users SET password=? WHERE id=?')
            ->execute([password_hash('password123', PASSWORD_DEFAULT), (int) post('id')]);
        flash('success', 'Password reset to password123.');
        redirect('users.php');
    }
    if ($fa === 'delete') {
        $pdo->prepare('DELETE FROM users WHERE id=? AND role="staff"')->execute([(int) post('id')]);
        flash('success', 'Staff member removed.');
        redirect('users.php');
    }
}

$pageTitle = __('page.users');
$pageSubtitle = 'Accounts, roles, staff permissions, and access control';
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
    $isEdit = (bool) $id;
    ?>
    <div class="entity-form-wrap">
    <div class="entity-form panel">
        <div class="entity-form-hero">
            <div>
                <p class="entity-form-eyebrow"><?= $isEdit ? 'User account' : 'New account' ?></p>
                <h2><?= $isEdit ? 'Edit user' : 'Create user' ?></h2>
                <p class="muted"><?= $isEdit ? 'Update profile, role, and access status.' : 'Create a portal account for admin, staff, lawyer, or client.' ?></p>
            </div>
            <p class="entity-form-required-note"><span class="req">*</span> Required fields</p>
        </div>
        <form method="post" id="user-form">
            <div class="entity-form-body">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="save">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3>Profile</h3>
                        <p>Basic identity and contact details.</p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">First name <span class="req">*</span></label>
                            <input id="first_name" name="first_name" required value="<?= e($row['first_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last name <span class="req">*</span></label>
                            <input id="last_name" name="last_name" required value="<?= e($row['last_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="email">Email <span class="req">*</span></label>
                            <input id="email" type="email" name="email" required value="<?= e($row['email']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input id="phone" name="phone" value="<?= e($row['phone']) ?>">
                        </div>
                    </div>
                </section>

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3>Access</h3>
                        <p>Login credentials, role, and activation.</p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="username">Username <span class="req">*</span></label>
                            <input id="username" name="username" required value="<?= e($row['username']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="password"><?= $isEdit ? 'New password' : 'Password' ?><?= $isEdit ? '' : ' <span class="req">*</span>' ?></label>
                            <input id="password" name="password" placeholder="<?= $isEdit ? 'Leave blank to keep current' : 'Defaults to password123' ?>" <?= $isEdit ? '' : '' ?>>
                            <span class="field-hint"><?= $isEdit ? 'Optional — leave blank to keep the current password.' : 'Optional — defaults to password123 if empty.' ?></span>
                        </div>
                        <div class="form-group">
                            <label for="user-role">Role <span class="req">*</span></label>
                            <select name="role" id="user-role" required>
                                <?php foreach (['admin', 'staff', 'lawyer', 'client'] as $r): ?>
                                    <option value="<?= e($r) ?>" <?= $row['role'] === $r ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="is_active">Status <span class="req">*</span></label>
                            <select id="is_active" name="is_active" required>
                                <option value="1" <?= $row['is_active'] ? 'selected' : '' ?>>Active</option>
                                <option value="0" <?= !$row['is_active'] ? 'selected' : '' ?>>Deactivated</option>
                            </select>
                        </div>
                        <div class="form-group full" id="staff-permissions" hidden>
                            <label>Staff module permissions</label>
                            <div class="quick-links">
                                <?php foreach ($permissionOptions as $p): ?>
                                    <label class="chip">
                                        <input type="checkbox" name="permissions[]" value="<?= e($p) ?>" <?= in_array($p, $selectedPerms, true) ? 'checked' : '' ?>>
                                        <?= e(ucfirst($p)) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <span class="field-hint">Only applies when the role is Staff.</span>
                        </div>
                    </div>
                </section>
            </div>
            <div class="entity-form-footer">
                <a class="btn btn-secondary" href="users.php">Back to users</a>
                <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save changes' : 'Create user' ?></button>
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
        <h2>Users &amp; staff</h2>
        <a class="btn btn-primary btn-sm" href="?action=create">Create user</a>
    </div>
    <div class="quick-links" style="margin-bottom:1rem;">
        <?php
        $filters = ['' => 'All', 'admin' => 'Admin', 'staff' => 'Staff', 'lawyer' => 'Lawyer', 'client' => 'Client'];
        foreach ($filters as $key => $label):
            $href = $key === '' ? 'users.php' : 'users.php?role=' . urlencode($key);
        ?>
            <a class="chip <?= $filter === $key ? 'active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>User</th><th>Role</th><th>Access</th><th>Last login</th><th>Status</th><th>Actions</th></tr>
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
                    <td><?= e(ucfirst($u['role'])) ?></td>
                    <td>
                        <?php if ($u['role'] === 'staff'): ?>
                            <?= e($perms ? implode(', ', $perms) : 'No modules assigned') ?>
                        <?php elseif ($u['role'] === 'admin'): ?>
                            Full access
                        <?php else: ?>
                            Portal role
                        <?php endif; ?>
                    </td>
                    <td><?= e(format_datetime($u['last_login'])) ?></td>
                    <td><?= status_badge($u['is_active'] ? 'active' : 'unavailable') ?></td>
                    <td class="col-actions">
                        <div class="row-actions">
                            <a class="btn btn-row-edit btn-sm" href="?action=edit&id=<?= (int) $u['id'] ?>">Edit</a>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="form_action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button class="btn btn-sm <?= $u['is_active'] ? 'btn-row-delete' : 'btn-row-approve' ?>" type="submit"><?= $u['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                            </form>
                            <form method="post" onsubmit="return confirm('Reset password to password123?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="form_action" value="reset">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button class="btn btn-row-edit btn-sm" type="submit">Reset PW</button>
                            </form>
                            <?php if ($u['role'] === 'staff'): ?>
                                <form method="post" onsubmit="return confirm('Remove this staff member?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="form_action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <button class="btn btn-row-delete btn-sm" type="submit">Remove</button>
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
