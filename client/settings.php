<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/portal-backup.php';
require_role(['client']);
$pdo = db();
$user = current_user();
$uid = (int) $user['id'];

$tabs = [
    'profile' => 'settings.tab.profile',
    'backup' => 'settings.tab.backup',
];
$tab = get('tab', 'profile');
if (!isset($tabs[$tab])) {
    $tab = 'profile';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = post('settings_tab', $tab);

    if ($section === 'profile') {
        verify_csrf();
        $pdo->prepare('UPDATE users SET first_name=?, last_name=?, phone=?, address=?, company_name=? WHERE id=?')
            ->execute([post('first_name'), post('last_name'), post('phone'), post('address'), post('company_name'), $uid]);
        if (post('password')) {
            $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash(post('password'), PASSWORD_DEFAULT), $uid]);
        }
        refresh_session_user();
        $user = current_user();
        flash('success', __('settings.profile.saved'));
        redirect('settings.php?tab=profile');
    }

    if ($section === 'backup') {
        portal_backup_handle_post($pdo, $user, 'client', 'settings.php?tab=backup');
    }
}

$backupFrequency = portal_backup_frequency($pdo, $uid);
$portalBackupIncluded = [
    ['settings.backup.portal.client.cases', 'settings.backup.portal.client.cases_help'],
    ['settings.backup.portal.client.documents', 'settings.backup.portal.client.documents_help'],
    ['settings.backup.portal.client.appointments', 'settings.backup.portal.client.appointments_help'],
    ['settings.backup.portal.client.payments', 'settings.backup.portal.client.payments_help'],
    ['settings.backup.portal.client.hearings', 'settings.backup.portal.client.hearings_help'],
    ['settings.backup.portal.client.messages', 'settings.backup.portal.client.messages_help'],
];

$pageTitle = __('page.settings');
$pageSubtitle = __('settings.portal.client.subtitle');
$portal = 'client';
$activeNav = 'settings';
require __DIR__ . '/../includes/header.php';
?>
<section class="settings-shell">
    <header class="settings-hero">
        <div>
            <h2><?= __e('settings.portal.client.title') ?></h2>
            <p><?= __e('settings.portal.client.hero') ?></p>
        </div>
    </header>

    <nav class="settings-tabs" aria-label="<?= __e('page.settings') ?>">
        <?php foreach ($tabs as $key => $labelKey): ?>
            <a class="settings-tab <?= $tab === $key ? 'active' : '' ?>" href="?tab=<?= e($key) ?>"><?= __e($labelKey) ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="settings-body">
        <?php if ($tab === 'profile'): ?>
            <form method="post" class="settings-form">
                <?= csrf_field() ?>
                <input type="hidden" name="settings_tab" value="profile">
                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.profile.title') ?></h3>
                        <p><?= __e('settings.portal.client.profile_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group"><label for="first_name"><?= __e('form.first_name') ?></label><input id="first_name" name="first_name" required value="<?= e($user['first_name']) ?>"></div>
                            <div class="form-group"><label for="last_name"><?= __e('form.last_name') ?></label><input id="last_name" name="last_name" required value="<?= e($user['last_name']) ?>"></div>
                        </div>
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group"><label for="email"><?= __e('common.email') ?></label><input id="email" value="<?= e($user['email']) ?>" disabled></div>
                            <div class="form-group"><label for="phone"><?= __e('common.phone') ?></label><input id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>"></div>
                        </div>
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group"><label for="company_name"><?= __e('form.company') ?></label><input id="company_name" name="company_name" value="<?= e($user['company_name'] ?? '') ?>" placeholder="<?= __e('form.placeholder.company') ?>"></div>
                            <div class="form-group"><label for="password"><?= __e('form.new_password') ?></label><input id="password" type="password" name="password" placeholder="<?= __e('form.password_keep') ?>"><span class="field-hint"><?= __e('form.hint.password_keep') ?></span></div>
                        </div>
                        <div class="form-group full"><label for="address"><?= __e('common.address') ?></label><textarea id="address" name="address" rows="2"><?= e($user['address'] ?? '') ?></textarea></div>
                    </div>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><?= __e('common.save_profile') ?></button></div>
            </form>
        <?php else: ?>
            <?php require __DIR__ . '/../includes/portal-settings-backup-panel.php'; ?>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
