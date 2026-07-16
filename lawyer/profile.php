<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$uid = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pdo->prepare('UPDATE users SET first_name=?, last_name=?, phone=?, address=?, specialization=? WHERE id=?')
        ->execute([post('first_name'), post('last_name'), post('phone'), post('address'), post('specialization'), $uid]);
    if (post('password')) {
        $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash(post('password'), PASSWORD_DEFAULT), $uid]);
    }
    refresh_session_user();
    flash('success', 'Profile updated.');
    redirect('profile.php');
}

$u = current_user();
$pageTitle = __('page.profile');
$pageSubtitle = 'Personal information, password, and contact details';
$portal = 'lawyer';
$activeNav = 'profile';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <div class="form-group"><label>First name</label><input name="first_name" required value="<?= e($u['first_name']) ?>"></div>
        <div class="form-group"><label>Last name</label><input name="last_name" required value="<?= e($u['last_name']) ?>"></div>
        <div class="form-group"><label>Email</label><input value="<?= e($u['email']) ?>" disabled></div>
        <div class="form-group"><label>Phone</label><input name="phone" value="<?= e($u['phone']) ?>"></div>
        <div class="form-group"><label>Specialization</label><input name="specialization" value="<?= e($u['specialization']) ?>"></div>
        <div class="form-group"><label>New password</label><input type="password" name="password" placeholder="Leave blank to keep current"></div>
        <div class="form-group full"><label>Address / contact details</label><textarea name="address"><?= e($u['address']) ?></textarea></div>
        <div class="form-actions full"><button class="btn btn-primary" type="submit">Save profile</button></div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
