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
    flash('success', __('flash.profile.updated'));
    redirect('profile.php');
}

$u = current_user();
$pageTitle = __('page.profile');
$pageSubtitle = __('settings.profile.help');
$portal = 'lawyer';
$activeNav = 'profile';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <div class="form-group"><label><?= __e('form.first_name') ?></label><input name="first_name" required value="<?= e($u['first_name']) ?>"></div>
        <div class="form-group"><label><?= __e('form.last_name') ?></label><input name="last_name" required value="<?= e($u['last_name']) ?>"></div>
        <div class="form-group"><label><?= __e('common.email') ?></label><input value="<?= e($u['email']) ?>" disabled></div>
        <div class="form-group"><label><?= __e('common.phone') ?></label><input name="phone" value="<?= e($u['phone']) ?>"></div>
        <div class="form-group"><label><?= __e('form.specialization') ?></label><input name="specialization" value="<?= e($u['specialization']) ?>"></div>
        <div class="form-group"><label><?= __e('form.new_password') ?></label><input type="password" name="password" placeholder="<?= __e('form.password_keep') ?>"></div>
        <div class="form-group full"><label><?= __e('common.address') ?></label><textarea name="address"><?= e($u['address']) ?></textarea></div>
        <div class="form-actions full"><button class="btn btn-primary" type="submit"><?= __e('common.save_profile') ?></button></div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
