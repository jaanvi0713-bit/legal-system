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
    flash('success', 'Availability updated.');
    redirect('availability.php');
}

$u = current_user();
$pageTitle = 'Availability';
$pageSubtitle = 'Set your current availability for clients and the firm';
$portal = 'lawyer';
$activeNav = 'availability';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Current status</label>
            <select name="availability">
                <?php foreach (['available' => 'Available', 'busy' => 'Busy', 'unavailable' => 'Unavailable'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($u['availability'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Shown as</label>
            <input value="<?= e(ucfirst($u['availability'] ?? 'available')) ?>" disabled>
        </div>
        <div class="form-group full">
            <label>Notes for the team</label>
            <textarea name="notes" placeholder="Court mornings, consultation windows, leave dates…"><?= e($u['notes'] ?? '') ?></textarea>
        </div>
        <div class="form-actions full">
            <button class="btn btn-primary" type="submit">Save availability</button>
            <a class="btn btn-ghost" href="profile.php">Edit profile</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
