<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$uid = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (post('form_action') === 'read') {
        $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([(int) post('id'), $uid]);
    }
    if (post('form_action') === 'read_all') {
        $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$uid]);
    }
    redirect('notifications.php');
}

$rows = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC');
$rows->execute([$uid]);
$rows = $rows->fetchAll();
$unreadCount = 0;
foreach ($rows as $n) {
    if (!(int) $n['is_read']) {
        $unreadCount++;
    }
}
$typeIcons = [
    'info' => 'i', 'success' => 'OK', 'case' => 'C', 'appointment' => 'A',
    'payment' => 'P', 'document' => 'D', 'reminder' => 'R',
];

$pageTitle = __('page.notifications');
$pageSubtitle = __('notifications.subtitle');
$portal = 'lawyer';
$activeNav = 'notifications';
$bodyClass = 'page-notifications';
require __DIR__ . '/../includes/header.php';
?>
<div class="notify-page">
    <section class="panel notify-inbox notify-inbox-solo">
        <div class="panel-header notify-card-head">
            <div>
                <h2><?= __e('common.inbox') ?></h2>
                <p class="muted"><?= $unreadCount ? __e('notifications.unread_count', ['n' => (string) $unreadCount]) : __e('notifications.inbox_help') ?></p>
            </div>
            <?php if ($unreadCount > 0): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="read_all"><button class="btn btn-secondary btn-sm" type="submit"><?= __e('common.mark_all_read') ?></button></form>
            <?php endif; ?>
        </div>
        <div class="notify-list">
            <?php foreach ($rows as $n):
                $type = $n['type'] ?: 'info';
                $icon = $typeIcons[$type] ?? '•';
                $isUnread = !(int) $n['is_read'];
            ?>
                <article class="notify-item <?= $isUnread ? 'is-unread' : 'is-read' ?>">
                    <div class="notify-item-icon tone-<?= e($type) ?>" aria-hidden="true"><?= e($icon) ?></div>
                    <div class="notify-item-body">
                        <div class="notify-item-top">
                            <strong><?= e(t_stored($n['title'])) ?></strong>
                            <?php if ($isUnread): ?><span class="notify-pill"><?= __e('notifications.new') ?></span><?php endif; ?>
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
            <?php if (!$rows): ?><div class="notify-empty"><?= __e('common.no_notifications') ?></div><?php endif; ?>
        </div>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
