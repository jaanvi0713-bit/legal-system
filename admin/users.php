<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);
$pdo = db();
$action = get('action', 'list');
$id = (int) get('id', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'save') {
        $editId = (int) post('id');
        if ($editId) {
            $pdo->prepare('UPDATE users SET first_name=?, last_name=?, username=?, email=?, phone=?, role=?, is_active=? WHERE id=?')
                ->execute([post('first_name'), post('last_name'), post('username'), post('email'), post('phone'), post('role'), (int)(post('is_active')==='1'), $editId]);
            if (post('password')) {
                $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash(post('password'), PASSWORD_DEFAULT), $editId]);
            }
            flash('success', 'User updated.');
        } else {
            $pdo->prepare('INSERT INTO users (role, first_name, last_name, username, email, password, phone, is_active) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([post('role'), post('first_name'), post('last_name'), post('username'), post('email'), password_hash(post('password') ?: 'password123', PASSWORD_DEFAULT), post('phone'), (int)(post('is_active')==='1')]);
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
        $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash('password123', PASSWORD_DEFAULT), (int) post('id')]);
        flash('success', 'Password reset to password123.');
        redirect('users.php');
    }
}

$pageTitle = 'User Management';
$pageSubtitle = 'Accounts, roles, activation, and password resets';
$portal = 'admin';
$activeNav = 'users';

if ($action === 'create' || ($action === 'edit' && $id)) {
    $row = ['id'=>0,'first_name'=>'','last_name'=>'','username'=>'','email'=>'','phone'=>'','role'=>'client','is_active'=>1];
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: $row;
    }
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="panel">
        <h2><?= $id ? 'Edit user' : 'Create user' ?></h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="save"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <div class="form-group"><label>First name</label><input name="first_name" required value="<?= e($row['first_name']) ?>"></div>
            <div class="form-group"><label>Last name</label><input name="last_name" required value="<?= e($row['last_name']) ?>"></div>
            <div class="form-group"><label>Username</label><input name="username" required value="<?= e($row['username']) ?>"></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" required value="<?= e($row['email']) ?>"></div>
            <div class="form-group"><label>Phone</label><input name="phone" value="<?= e($row['phone']) ?>"></div>
            <div class="form-group"><label>Role</label>
                <select name="role"><?php foreach (['admin','lawyer','client','staff'] as $r): ?><option value="<?= $r ?>" <?= $row['role']===$r?'selected':'' ?>><?= ucfirst($r) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label>Status</label><select name="is_active"><option value="1" <?= $row['is_active']?'selected':'' ?>>Active</option><option value="0" <?= !$row['is_active']?'selected':'' ?>>Deactivated</option></select></div>
            <div class="form-group full"><label><?= $id ? 'New password (optional)' : 'Password' ?></label><input name="password" placeholder="<?= $id ? 'Leave blank to keep current' : 'Defaults to password123' ?>"></div>
            <div class="form-actions full"><button class="btn btn-primary" type="submit">Save</button><a class="btn btn-ghost" href="users.php">Cancel</a></div>
        </form>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

$users = $pdo->query('SELECT * FROM users ORDER BY role, first_name')->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <div class="panel-header"><h2>All user accounts</h2><a class="btn btn-primary btn-sm" href="?action=create">Create user</a></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>User</th><th>Role</th><th>Last login</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= e(full_name($u)) ?></strong><div class="muted"><?= e($u['username']) ?> · <?= e($u['email']) ?></div></td>
                    <td><?= e(ucfirst($u['role'])) ?></td>
                    <td><?= e(format_datetime($u['last_login'])) ?></td>
                    <td><?= status_badge($u['is_active'] ? 'active' : 'unavailable') ?></td>
                    <td class="quick-links">
                        <a class="chip" href="?action=edit&id=<?= (int)$u['id'] ?>">Edit</a>
                        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="form_action" value="toggle"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="chip" type="submit"><?= $u['is_active']?'Deactivate':'Activate' ?></button></form>
                        <form method="post" style="display:inline" onsubmit="return confirm('Reset password to password123?')"><?= csrf_field() ?><input type="hidden" name="form_action" value="reset"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="chip" type="submit">Reset PW</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
