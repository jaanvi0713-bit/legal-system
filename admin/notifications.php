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
$unreadCount = 0;
foreach ($mine as $n) {
    if (!(int) $n['is_read']) {
        $unreadCount++;
    }
}
$history = $pdo->query('SELECT n.*, CONCAT(u.first_name," ",u.last_name) AS recipient FROM notifications n JOIN users u ON u.id=n.user_id ORDER BY n.created_at DESC LIMIT 40')->fetchAll();

$typeIcons = [
    'info' => 'i',
    'success' => 'OK',
    'case' => 'C',
    'appointment' => 'A',
    'payment' => 'P',
    'document' => 'D',
    'reminder' => 'R',
];

$pageTitle = __('page.notifications');
$pageSubtitle = __('notifications.subtitle');
$portal = 'admin';
$activeNav = 'notifications';
$bodyClass = 'page-notifications';
require __DIR__ . '/../includes/header.php';
?>
<div class="notify-page">
    <section class="notify-hero panel">
        <div class="notify-hero-copy">
            <span class="notify-kicker"><?= __e('common.inbox') ?></span>
            <h2><?= __e('page.notifications') ?></h2>
            <p><?= __e('notifications.hero_help') ?></p>
        </div>
        <div class="notify-hero-stats">
            <div class="notify-stat">
                <strong><?= (int) $unreadCount ?></strong>
                <span><?= __e('notifications.unread') ?></span>
            </div>
            <div class="notify-stat">
                <strong><?= count($mine) ?></strong>
                <span><?= __e('notifications.in_inbox') ?></span>
            </div>
            <div class="notify-stat">
                <strong><?= count($history) ?></strong>
                <span><?= __e('notifications.recent_sent') ?></span>
            </div>
        </div>
    </section>

    <div class="notify-grid">
        <section class="panel notify-compose">
            <div class="notify-card-head">
                <div>
                    <h2><?= __e('notifications.send') ?></h2>
                    <p class="muted"><?= __e('notifications.send_help') ?></p>
                </div>
            </div>
            <form method="post" class="form-grid notify-form">
                <?= csrf_field() ?><input type="hidden" name="form_action" value="send">
                <div class="form-group full">
                    <label><?= __e('common.recipient') ?></label>
                    <select name="user_id" required>
                        <option value="all"><?= __e('form.all_users') ?></option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int)$u['id'] ?>"><?= e(full_name($u).' ('.translate_role($u['role']).')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= __e('common.type') ?></label>
                    <select name="type">
                        <?php foreach (['info','success','case','appointment','payment','document','reminder'] as $t): ?>
                            <option value="<?= $t ?>"><?= e(__('notification.type.' . $t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= __e('notifications.title_field') ?></label>
                    <input name="title" required placeholder="<?= __e('notifications.title_ph') ?>">
                </div>
                <div class="form-group full">
                    <label><?= __e('common.message') ?></label>
                    <textarea name="message" required rows="5" placeholder="<?= __e('notifications.message_ph') ?>"></textarea>
                </div>
                <div class="form-actions full">
                    <button class="btn btn-primary" type="submit"><?= __e('common.send') ?></button>
                </div>
            </form>
        </section>

        <section class="panel notify-inbox">
            <div class="panel-header notify-card-head">
                <div>
                    <h2><?= __e('common.inbox') ?></h2>
                    <p class="muted"><?= __e('notifications.inbox_help') ?></p>
                </div>
                <?php if ($unreadCount > 0): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="read_all">
                    <button class="btn btn-secondary btn-sm" type="submit"><?= __e('common.mark_all_read') ?></button>
                </form>
                <?php endif; ?>
            </div>
            <div class="notify-list">
                <?php foreach ($mine as $n):
                    $type = $n['type'] ?: 'info';
                    $icon = $typeIcons[$type] ?? '•';
                    $isUnread = !(int) $n['is_read'];
                ?>
                    <article class="notify-item <?= $isUnread ? 'is-unread' : 'is-read' ?>">
                        <div class="notify-item-icon tone-<?= e($type) ?>" aria-hidden="true"><?= e($icon) ?></div>
                        <div class="notify-item-body">
                            <div class="notify-item-top">
                                <strong><?= e(t_stored($n['title'])) ?></strong>
                                <?php if ($isUnread): ?>
                                    <span class="notify-pill"><?= __e('notifications.new') ?></span>
                                <?php endif; ?>
                            </div>
                            <p><?= e(t_stored($n['message'])) ?></p>
                            <div class="notify-item-meta">
                                <span class="notify-type"><?= e(__('notification.type.' . $type)) ?></span>
                                <span><?= e(format_datetime($n['created_at'])) ?></span>
                            </div>
                        </div>
                        <?php if ($isUnread): ?>
                        <form method="post" class="notify-item-actions">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form_action" value="read">
                            <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                            <button class="btn btn-row-edit btn-sm" type="submit"><?= __e('common.mark_read') ?></button>
                        </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
                <?php if (!$mine): ?>
                    <div class="notify-empty"><?= __e('common.no_notifications') ?></div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <section class="panel notify-history">
        <div class="notify-card-head">
            <div>
                <h2><?= __e('notifications.history') ?></h2>
                <p class="muted"><?= __e('notifications.history_help') ?></p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="notify-table">
                <thead>
                    <tr>
                        <th><?= __e('common.when') ?></th>
                        <th><?= __e('common.recipient') ?></th>
                        <th><?= __e('notifications.title_field') ?></th>
                        <th><?= __e('common.type') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($history as $h):
                    $type = $h['type'] ?: 'info';
                ?>
                    <tr>
                        <td class="notify-when"><?= e(format_datetime($h['created_at'])) ?></td>
                        <td><?= e($h['recipient']) ?></td>
                        <td>
                            <strong><?= e(t_stored($h['title'])) ?></strong>
                            <div class="muted"><?= e(t_stored($h['message'])) ?></div>
                        </td>
                        <td><span class="notify-type-chip tone-<?= e($type) ?>"><?= e(__('notification.type.' . $type)) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$history): ?>
                    <tr><td colspan="4" class="muted"><?= __e('common.no_notifications') ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
