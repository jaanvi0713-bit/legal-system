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
        $perms = json_encode(array_values($_POST['permissions'] ?? []));
        if ($editId) {
            $pdo->prepare('UPDATE users SET first_name=?, last_name=?, username=?, email=?, phone=?, permissions=?, is_active=? WHERE id=? AND role="staff"')
                ->execute([post('first_name'), post('last_name'), post('username'), post('email'), post('phone'), $perms, (int)(post('is_active')==='1'), $editId]);
            flash('success', 'Staff updated.');
        } else {
            $pdo->prepare('INSERT INTO users (role, first_name, last_name, username, email, password, phone, permissions, is_active) VALUES ("staff",?,?,?,?,?,?,?,?)')
                ->execute([post('first_name'), post('last_name'), post('username'), post('email'), password_hash(post('password') ?: 'password123', PASSWORD_DEFAULT), post('phone'), $perms, (int)(post('is_active')==='1')]);
            flash('success', 'Staff member added.');
        }
        redirect('staff.php');
    }
    if ($fa === 'delete') {
        $pdo->prepare('DELETE FROM users WHERE id=? AND role="staff"')->execute([(int) post('id')]);
        flash('success', 'Staff removed.');
        redirect('staff.php');
    }
}

$permissionOptions = ['clients','lawyers','cases','appointments','court','finance','reports','notifications'];
$pageTitle = 'Staff Management';
$pageSubtitle = 'Add staff and assign module permissions';
$portal = 'admin';
$activeNav = 'staff';

if ($action === 'create' || ($action === 'edit' && $id)) {
    $row = ['id'=>0,'first_name'=>'','last_name'=>'','username'=>'','email'=>'','phone'=>'','is_active'=>1,'permissions'=>'[]'];
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="staff"');
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: $row;
    }
    $selected = json_decode($row['permissions'] ?: '[]', true) ?: [];
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="panel">
        <h2><?= $id ? 'Edit staff' : 'Add staff' ?></h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="save"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <div class="form-group"><label>First name</label><input name="first_name" required value="<?= e($row['first_name']) ?>"></div>
            <div class="form-group"><label>Last name</label><input name="last_name" required value="<?= e($row['last_name']) ?>"></div>
            <div class="form-group"><label>Username</label><input name="username" required value="<?= e($row['username']) ?>"></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" required value="<?= e($row['email']) ?>"></div>
            <div class="form-group"><label>Phone</label><input name="phone" value="<?= e($row['phone']) ?>"></div>
            <?php if (!$id): ?><div class="form-group"><label>Password</label><input name="password" placeholder="Defaults to password123"></div><?php endif; ?>
            <div class="form-group"><label>Status</label><select name="is_active"><option value="1" <?= $row['is_active']?'selected':'' ?>>Active</option><option value="0" <?= !$row['is_active']?'selected':'' ?>>Inactive</option></select></div>
            <div class="form-group full"><label>Permissions</label>
                <div class="quick-links">
                    <?php foreach ($permissionOptions as $p): ?>
                        <label class="chip"><input type="checkbox" name="permissions[]" value="<?= $p ?>" <?= in_array($p, $selected, true) ? 'checked' : '' ?>> <?= e(ucfirst($p)) ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-actions full"><button class="btn btn-primary" type="submit">Save</button><a class="btn btn-ghost" href="staff.php">Cancel</a></div>
        </form>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

$staff = $pdo->query("SELECT * FROM users WHERE role='staff' ORDER BY first_name")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <div class="panel-header"><h2>Staff members</h2><a class="btn btn-primary btn-sm" href="?action=create">Add staff</a></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Permissions</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($staff as $s): $perms = json_decode($s['permissions'] ?: '[]', true) ?: []; ?>
                <tr>
                    <td><strong><?= e(full_name($s)) ?></strong><div class="muted"><?= e($s['email']) ?></div></td>
                    <td><?= e($perms ? implode(', ', $perms) : 'None') ?></td>
                    <td><?= status_badge($s['is_active'] ? 'active' : 'pending') ?></td>
                    <td class="quick-links">
                        <a class="chip" href="?action=edit&id=<?= (int)$s['id'] ?>">Edit</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('Remove staff member?')"><?= csrf_field() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="chip" type="submit">Remove</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
