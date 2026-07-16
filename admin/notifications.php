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
            flash('success', 'Notification sent to all users.');
        } else {
            create_notification($pdo, (int)$target, $title, $message, $type, null, $user['id']);
            flash('success', 'Notification sent.');
        }
        redirect('notifications.php');
    }
    if ($fa === 'read') {
        $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([(int) post('id'), $user['id']]);
        redirect('notifications.php');
    }
    if ($fa === 'read_all') {
        $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$user['id']]);
        flash('success', 'All notifications marked read.');
        redirect('notifications.php');
    }
}

$users = $pdo->query("SELECT id, first_name, last_name, role FROM users WHERE is_active=1 ORDER BY role, first_name")->fetchAll();
$mine = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50');
$mine->execute([$user['id']]);
$mine = $mine->fetchAll();
$history = $pdo->query('SELECT n.*, CONCAT(u.first_name," ",u.last_name) AS recipient FROM notifications n JOIN users u ON u.id=n.user_id ORDER BY n.created_at DESC LIMIT 40')->fetchAll();

$pageTitle = __('page.notifications');
$pageSubtitle = 'Send, receive, and monitor notification history';
$portal = 'admin';
$activeNav = 'notifications';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-2">
    <div class="panel">
        <h2>Send notification</h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="send">
            <div class="form-group full"><label>Recipient</label>
                <select name="user_id" required>
                    <option value="all">All users</option>
                    <?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e(full_name($u).' ('.$u['role'].')') ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Type</label><select name="type"><?php foreach (['info','success','case','appointment','payment','document','reminder'] as $t): ?><option value="<?= $t ?>"><?= ucfirst($t) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Title</label><input name="title" required></div>
            <div class="form-group full"><label>Message</label><textarea name="message" required></textarea></div>
            <div class="form-actions full"><button class="btn btn-primary" type="submit">Send</button></div>
        </form>
    </div>
    <div class="panel">
        <div class="panel-header"><h2>Inbox</h2>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="read_all"><button class="btn btn-sm btn-ghost" type="submit">Mark all read</button></form>
        </div>
        <div class="list-stack">
            <?php foreach ($mine as $n): ?>
                <div class="list-item">
                    <strong><?= e($n['title']) ?> <?= $n['is_read'] ? '' : status_badge('pending') ?></strong>
                    <span class="muted"><?= e($n['message']) ?> · <?= e(format_datetime($n['created_at'])) ?></span>
                    <?php if (!$n['is_read']): ?>
                    <form method="post" style="margin-top:0.4rem"><?= csrf_field() ?><input type="hidden" name="form_action" value="read"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="chip" type="submit">Mark read</button></form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div class="panel">
    <h2>Notification history</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>When</th><th>Recipient</th><th>Title</th><th>Type</th></tr></thead>
            <tbody>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td><?= e(format_datetime($h['created_at'])) ?></td>
                    <td><?= e($h['recipient']) ?></td>
                    <td><?= e($h['title']) ?><div class="muted"><?= e($h['message']) ?></div></td>
                    <td><?= e($h['type']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
