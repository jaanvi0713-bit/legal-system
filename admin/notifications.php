<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'staff']);
$pdo = db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'send') {
        $target = post('user_id');
        $title = post('title');
        $message = post('message');
        $type = post('type') ?: 'info';
        if ($target === 'all') {
            $ids = $pdo->query('SELECT id FROM users WHERE is_active=1')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $uid) {
                create_notification($pdo, (int)$uid, $title, $message, $type, null, $user['id']);
            }
            flash('success', __('flash.notification.sent'));
        } else {
            create_notification($pdo, (int)$target, $title, $message, $type, null, $user['id']);
            flash('success', __('flash.notification.sent'));
        }
        redirect('notifications.php');
    }
    if ($fa === 'read') {
        $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([(int) post('id'), $user['id']]);
        redirect('notifications.php');
    }
    if ($fa === 'read_all') {
        $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$user['id']]);
        flash('success', __('flash.notifications.marked_read'));
        redirect('notifications.php');
    }
}

$users = $pdo->query("SELECT id, first_name, last_name, role FROM users WHERE is_active=1 ORDER BY role, first_name")->fetchAll();
$mine = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50');
$mine->execute([$user['id']]);
$mine = $mine->fetchAll();
$history = $pdo->query('SELECT n.*, CONCAT(u.first_name," ",u.last_name) AS recipient FROM notifications n JOIN users u ON u.id=n.user_id ORDER BY n.created_at DESC LIMIT 40')->fetchAll();

$pageTitle = __('page.notifications');
$pageSubtitle = __('page.notifications');
$portal = 'admin';
$activeNav = 'notifications';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-2">
    <div class="panel">
        <h2><?= __e('notifications.send') ?></h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="send">
            <div class="form-group full"><label><?= __e('common.recipient') ?></label>
                <select name="user_id" required>
                    <option value="all"><?= __e('form.all_users') ?></option>
                    <?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e(full_name($u).' ('.translate_role($u['role']).')') ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label><?= __e('common.type') ?></label><select name="type"><?php foreach (['info','success','case','appointment','payment','document','reminder'] as $t): ?><option value="<?= $t ?>"><?= e(__('notification.type.' . $t)) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label><?= __e('notifications.title_field') ?></label><input name="title" required></div>
            <div class="form-group full"><label><?= __e('common.message') ?></label><textarea name="message" required></textarea></div>
            <div class="form-actions full"><button class="btn btn-primary" type="submit"><?= __e('common.send') ?></button></div>
        </form>
    </div>
    <div class="panel">
        <div class="panel-header"><h2><?= __e('common.inbox') ?></h2>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="read_all"><button class="btn btn-sm btn-ghost" type="submit"><?= __e('common.mark_all_read') ?></button></form>
        </div>
        <div class="list-stack">
            <?php foreach ($mine as $n): ?>
                <div class="list-item">
                    <strong><?= e(t_stored($n['title'])) ?> <?= $n['is_read'] ? '' : status_badge('pending') ?></strong>
                    <span class="muted"><?= e(t_stored($n['message'])) ?> · <?= e(format_datetime($n['created_at'])) ?></span>
                    <?php if (!$n['is_read']): ?>
                    <form method="post" style="margin-top:0.4rem"><?= csrf_field() ?><input type="hidden" name="form_action" value="read"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="chip" type="submit"><?= __e('common.mark_read') ?></button></form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div class="panel">
    <h2><?= __e('notifications.history') ?></h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th><?= __e('common.when') ?></th><th><?= __e('common.recipient') ?></th><th><?= __e('notifications.title_field') ?></th><th><?= __e('common.type') ?></th></tr></thead>
            <tbody>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td><?= e(format_datetime($h['created_at'])) ?></td>
                    <td><?= e($h['recipient']) ?></td>
                    <td><?= e(t_stored($h['title'])) ?><div class="muted"><?= e(t_stored($h['message'])) ?></div></td>
                    <td><?= e(__('notification.type.' . $h['type'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
