<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$uid = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $availability = post('availability', 'available');
    if (!in_array($availability, ['available', 'busy', 'unavailable'], true)) {
        $availability = 'available';
    }
    $pdo->prepare('UPDATE users SET availability=?, notes=? WHERE id=?')
        ->execute([$availability, post('notes'), $uid]);
    refresh_session_user();
    flash('success', __('flash.availability.updated'));
    redirect('availability.php');
}

$u = current_user();
$pageTitle = __('page.availability');
$pageSubtitle = __('ai.subtitle.lawyer');
$portal = 'lawyer';
$activeNav = 'availability';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <div class="form-group">
            <label><?= __e('lawyer.availability.current') ?></label>
            <select name="availability">
                <?php foreach (['available', 'busy', 'unavailable'] as $value): ?>
                    <option value="<?= e($value) ?>" <?= ($u['availability'] ?? '') === $value ? 'selected' : '' ?>><?= e(translate_status($value)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label><?= __e('lawyer.availability.shown_as') ?></label>
            <input value="<?= e(translate_status($u['availability'] ?? 'available')) ?>" disabled>
        </div>
        <div class="form-group full">
            <label><?= __e('lawyer.availability.team_notes') ?></label>
            <textarea name="notes" placeholder="<?= __e('common.notes') ?>"><?= e($u['notes'] ?? '') ?></textarea>
        </div>
        <div class="form-actions full">
            <button class="btn btn-primary" type="submit"><?= __e('lawyer.availability.save') ?></button>
            <a class="btn btn-ghost" href="profile.php"><?= __e('lawyer.availability.edit_profile') ?></a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
