<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['client']);
$pdo = db();
$uid = (int) current_user()['id'];

$cases = $pdo->prepare("SELECT c.*, CONCAT(l.first_name,' ',l.last_name) AS lawyer_name FROM cases c LEFT JOIN users l ON l.id=c.lawyer_id WHERE c.client_id=? ORDER BY c.updated_at DESC");
$cases->execute([$uid]);
$cases = $cases->fetchAll();
$active = array_values(array_filter($cases, fn($c) => $c['status'] !== 'closed'));

$lawyer = null;
if (current_user()['assigned_lawyer_id']) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
    $stmt->execute([current_user()['assigned_lawyer_id']]);
    $lawyer = $stmt->fetch();
}

$docs = $pdo->prepare('SELECT * FROM case_documents WHERE client_id=? ORDER BY created_at DESC LIMIT 5');
$docs->execute([$uid]);
$docs = $docs->fetchAll();

$appointments = $pdo->prepare("SELECT * FROM appointments WHERE client_id=? AND scheduled_at >= NOW() AND status NOT IN ('cancelled','rejected') ORDER BY scheduled_at LIMIT 5");
$appointments->execute([$uid]);
$appointments = $appointments->fetchAll();

$outstanding = $pdo->prepare("SELECT COALESCE(SUM(i.total - IFNULL((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id=i.id),0)),0) FROM invoices i WHERE i.client_id=? AND i.status IN ('sent','partial','overdue','draft')");
$outstanding->execute([$uid]);
$outstanding = (float) $outstanding->fetchColumn();

$notes = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 6');
$notes->execute([$uid]);
$notes = $notes->fetchAll();

$pageTitle = 'Dashboard';
$pageSubtitle = 'Your matters at a glance';
$portal = 'client';
$activeNav = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-4">
    <div class="stat-card"><div class="stat-label">Active cases</div><div class="stat-value"><?= count($active) ?></div></div>
    <div class="stat-card"><div class="stat-label">Assigned lawyer</div><div class="stat-value" style="font-size:1.25rem;"><?= e($lawyer ? full_name($lawyer) : 'Pending') ?></div></div>
    <div class="stat-card"><div class="stat-label">Upcoming appointments</div><div class="stat-value"><?= count($appointments) ?></div></div>
    <div class="stat-card"><div class="stat-label">Outstanding</div><div class="stat-value" style="font-size:1.5rem;"><?= e(money($outstanding)) ?></div></div>
</div>
<div class="grid grid-2">
    <div class="panel">
        <div class="panel-header"><h2>My cases</h2><a href="cases.php">Open</a></div>
        <div class="list-stack">
            <?php foreach ($active as $c): ?>
                <div class="list-item"><strong><?= e($c['case_number']) ?></strong><span class="muted"><?= e($c['title']) ?> · Lawyer: <?= e($c['lawyer_name'] ?: 'TBA') ?></span> <?= status_badge($c['status']) ?></div>
            <?php endforeach; ?>
            <?php if (!$active): ?><div class="empty-state">No active cases.</div><?php endif; ?>
        </div>
    </div>
    <div class="panel">
        <div class="panel-header"><h2>Recent documents</h2><a href="documents.php">All files</a></div>
        <div class="list-stack">
            <?php foreach ($docs as $d): ?>
                <div class="list-item"><strong><?= e($d['title']) ?></strong><a href="../<?= e($d['file_path']) ?>" target="_blank">Download</a></div>
            <?php endforeach; ?>
            <?php if (!$docs): ?><div class="empty-state">No documents yet.</div><?php endif; ?>
        </div>
    </div>
</div>
<div class="grid grid-2">
    <div class="panel">
        <h2>Upcoming appointments</h2>
        <div class="list-stack">
            <?php foreach ($appointments as $a): ?>
                <div class="list-item"><strong><?= e($a['title']) ?></strong><span class="muted"><?= e(format_datetime($a['scheduled_at'])) ?></span></div>
            <?php endforeach; ?>
            <?php if (!$appointments): ?><div class="empty-state">None scheduled.</div><?php endif; ?>
        </div>
    </div>
    <div class="panel">
        <h2>Notifications</h2>
        <div class="list-stack">
            <?php foreach ($notes as $n): ?>
                <div class="list-item"><strong><?= e($n['title']) ?></strong><span class="muted"><?= e($n['message']) ?></span></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
