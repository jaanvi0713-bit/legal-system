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

$pageTitle = __('page.notifications');
$pageSubtitle = __('ai.subtitle.lawyer');
$portal = 'lawyer';
$activeNav = 'notifications';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <div class="panel-header"><h2><?= __e('common.inbox') ?></h2>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="read_all"><button class="btn btn-sm btn-ghost" type="submit"><?= __e('common.mark_all_read') ?></button></form>
    </div>
    <div class="list-stack">
        <?php foreach ($rows as $n): ?>
            <div class="list-item">
                <strong><?= e(t_stored($n['title'])) ?> <?= $n['is_read'] ? '' : status_badge('pending') ?></strong>
                <span class="muted"><?= e(t_stored($n['message'])) ?> · <?= e(format_datetime($n['created_at'])) ?></span>
                <?php if (!$n['is_read']): ?>
                <form method="post" style="margin-top:0.4rem"><?= csrf_field() ?><input type="hidden" name="form_action" value="read"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="chip" type="submit"><?= __e('common.mark_read') ?></button></form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (!$rows): ?><div class="empty-state"><?= __e('common.no_notifications') ?></div><?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
