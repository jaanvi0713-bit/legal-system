<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$uid = (int) current_user()['id'];

$pending = $pdo->prepare("SELECT a.*, CONCAT(c.first_name,' ',c.last_name) AS client_name FROM appointments a LEFT JOIN users c ON c.id=a.client_id WHERE a.lawyer_id=? AND a.status='pending' ORDER BY a.scheduled_at");
$pending->execute([$uid]);
$pending = $pending->fetchAll();

$upcoming = $pdo->prepare("SELECT a.*, CONCAT(c.first_name,' ',c.last_name) AS client_name FROM appointments a LEFT JOIN users c ON c.id=a.client_id WHERE a.lawyer_id=? AND a.status IN ('accepted','pending') AND a.scheduled_at >= NOW() ORDER BY a.scheduled_at LIMIT 12");
$upcoming->execute([$uid]);
$upcoming = $upcoming->fetchAll();

$notes = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? AND is_read=0 ORDER BY created_at DESC LIMIT 10');
$notes->execute([$uid]);
$notes = $notes->fetchAll();

$pageTitle = __('page.tasks');
$pageSubtitle = 'Pending appointments, reminders, and follow-ups';
$portal = 'lawyer';
$activeNav = 'tasks';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-2">
    <div class="panel">
        <div class="panel-header"><h2>Pending appointment responses</h2><a href="appointments.php">Open</a></div>
        <div class="list-stack">
            <?php foreach ($pending as $a): ?>
                <div class="list-item">
                    <strong><?= e($a['title']) ?></strong>
                    <span class="muted"><?= e(format_datetime($a['scheduled_at'])) ?> · <?= e($a['client_name'] ?: 'Client') ?></span>
                    <?= status_badge($a['status']) ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$pending): ?><div class="empty-state">No pending tasks right now.</div><?php endif; ?>
        </div>
    </div>
    <div class="panel">
        <div class="panel-header"><h2>Unread notifications</h2><a href="notifications.php">All</a></div>
        <div class="list-stack">
            <?php foreach ($notes as $n): ?>
                <div class="list-item">
                    <strong><?= e($n['title']) ?></strong>
                    <span class="muted"><?= e($n['message']) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$notes): ?><div class="empty-state">You're all caught up.</div><?php endif; ?>
        </div>
    </div>
</div>
<div class="panel">
    <div class="panel-header"><h2>Upcoming schedule</h2></div>
    <div class="list-stack">
        <?php foreach ($upcoming as $a): ?>
            <div class="list-item">
                <strong><?= e($a['title']) ?></strong>
                <span class="muted"><?= e(format_datetime($a['scheduled_at'])) ?> · <?= e($a['client_name'] ?: 'Client') ?> · <?= e($a['location'] ?: 'No location') ?></span>
            </div>
        <?php endforeach; ?>
        <?php if (!$upcoming): ?><div class="empty-state">No upcoming appointments.</div><?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
