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
<div class="entity-form-wrap">
<div class="entity-form panel">
    <div class="entity-form-hero">
        <div>
            <p class="entity-form-eyebrow"><?= __e('page.profile') ?></p>
            <h2><?= __e('common.save_profile') ?></h2>
            <p class="muted"><?= __e('settings.profile.help') ?></p>
        </div>
    </div>
    <form method="post">
        <div class="entity-form-body">
            <section class="entity-section">
                <div class="entity-section-head">
                    <h3><?= __e('form.section.personal') ?></h3>
                </div>
                <div class="form-grid">
                    <div class="entity-field-row entity-field-row--2">
                        <div class="form-group">
                            <label for="first_name"><?= __e('form.first_name') ?></label>
                            <input id="first_name" name="first_name" required value="<?= e($u['first_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="last_name"><?= __e('form.last_name') ?></label>
                            <input id="last_name" name="last_name" required value="<?= e($u['last_name']) ?>">
                        </div>
                    </div>
                    <div class="entity-field-row entity-field-row--2">
                        <div class="form-group">
                            <label for="email"><?= __e('common.email') ?></label>
                            <input id="email" value="<?= e($u['email']) ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="phone"><?= __e('common.phone') ?></label>
                            <input id="phone" name="phone" value="<?= e($u['phone']) ?>">
                        </div>
                    </div>
                    <div class="entity-field-row entity-field-row--2">
                        <div class="form-group">
                            <label for="specialization"><?= __e('form.specialization') ?></label>
                            <input id="specialization" name="specialization" value="<?= e($u['specialization']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="password"><?= __e('form.new_password') ?></label>
                            <input id="password" type="password" name="password" placeholder="<?= __e('form.password_keep') ?>">
                        </div>
                    </div>
                    <div class="form-group full entity-field-notes">
                        <span class="field-hint"><?= __e('form.hint.password_keep') ?></span>
                    </div>
                    <div class="form-group full">
                        <label for="address"><?= __e('common.address') ?></label>
                        <textarea id="address" name="address" rows="2"><?= e($u['address']) ?></textarea>
                    </div>
                </div>
            </section>
        </div>
        <div class="entity-form-footer">
            <button class="btn btn-primary" type="submit"><?= __e('common.save_profile') ?></button>
        </div>
    </form>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
