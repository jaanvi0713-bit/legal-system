<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$uid = (int) current_user()['id'];

$cases = $pdo->prepare("SELECT c.*, CONCAT(u.first_name,' ',u.last_name) AS client_name FROM cases c JOIN users u ON u.id=c.client_id WHERE c.lawyer_id=? ORDER BY c.updated_at DESC LIMIT 8");
$cases->execute([$uid]);
$cases = $cases->fetchAll();

$today = $pdo->prepare("SELECT * FROM appointments WHERE lawyer_id=? AND DATE(scheduled_at)=CURDATE() AND status NOT IN ('cancelled','rejected') ORDER BY scheduled_at");
$today->execute([$uid]);
$today = $today->fetchAll();

$hearings = $pdo->prepare("SELECT h.*, c.case_number, c.title FROM court_hearings h JOIN cases c ON c.id=h.case_id WHERE c.lawyer_id=? AND h.hearing_date >= NOW() AND h.status='scheduled' ORDER BY h.hearing_date LIMIT 6");
$hearings->execute([$uid]);
$hearings = $hearings->fetchAll();

$pending = $pdo->prepare("SELECT * FROM appointments WHERE lawyer_id=? AND status='pending' ORDER BY scheduled_at");
$pending->execute([$uid]);
$pending = $pending->fetchAll();

$notes = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 6');
$notes->execute([$uid]);
$notes = $notes->fetchAll();

$pageTitle = 'Dashboard';
$pageSubtitle = 'Your assigned work for today';
$portal = 'lawyer';
$activeNav = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<section class="kpi-grid">
    <article class="kpi-card">
        <div class="kpi-top">
            <div class="kpi-icon primary">C</div>
            <div class="kpi-meta">
                <div class="kpi-label">Assigned cases</div>
                <div class="kpi-value"><?= count($cases) ?></div>
            </div>
        </div>
        <div class="kpi-foot"><span class="kpi-delta up">Active</span> your matter list</div>
    </article>
    <article class="kpi-card">
        <div class="kpi-top">
            <div class="kpi-icon info">A</div>
            <div class="kpi-meta">
                <div class="kpi-label">Today's appointments</div>
                <div class="kpi-value"><?= count($today) ?></div>
            </div>
        </div>
        <div class="kpi-foot"><span class="kpi-delta up"><?= count($pending) ?></span> pending replies</div>
    </article>
    <article class="kpi-card">
        <div class="kpi-top">
            <div class="kpi-icon warning">H</div>
            <div class="kpi-meta">
                <div class="kpi-label">Upcoming hearings</div>
                <div class="kpi-value"><?= count($hearings) ?></div>
            </div>
        </div>
        <div class="kpi-foot"><span class="kpi-delta down">Court</span> scheduled sessions</div>
    </article>
    <article class="kpi-card">
        <div class="kpi-top">
            <div class="kpi-icon success">T</div>
            <div class="kpi-meta">
                <div class="kpi-label">Pending tasks</div>
                <div class="kpi-value"><?= count($pending) ?></div>
            </div>
        </div>
        <div class="kpi-foot"><span class="kpi-delta up">Queue</span> appointments awaiting action</div>
    </article>
</section>
<div class="grid grid-2">
    <div class="panel">
        <div class="panel-header"><h2>My cases</h2><a href="cases.php">View all</a></div>
        <div class="list-stack">
            <?php foreach ($cases as $c): ?>
                <div class="list-item"><strong><a href="cases.php?id=<?= (int)$c['id'] ?>"><?= e($c['case_number']) ?></a></strong><span class="muted"><?= e($c['title']) ?> · <?= e($c['client_name']) ?></span> <?= status_badge($c['status']) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="panel">
        <div class="panel-header"><h2>Today's appointments</h2><a href="appointments.php">Manage</a></div>
        <div class="list-stack">
            <?php foreach ($today as $a): ?>
                <div class="list-item"><strong><?= e($a['title']) ?></strong><span class="muted"><?= e(format_datetime($a['scheduled_at'])) ?> · <?= e($a['location'] ?: '') ?></span></div>
            <?php endforeach; ?>
            <?php if (!$today): ?><div class="empty-state">No appointments today.</div><?php endif; ?>
        </div>
    </div>
</div>
<div class="grid grid-2">
    <div class="panel">
        <h2>Upcoming hearings</h2>
        <div class="list-stack">
            <?php foreach ($hearings as $h): ?>
                <div class="list-item"><strong><?= e($h['case_number']) ?></strong><span class="muted"><?= e(format_datetime($h['hearing_date'])) ?> · <?= e($h['court_name']) ?></span></div>
            <?php endforeach; ?>
            <?php if (!$hearings): ?><div class="empty-state">No upcoming hearings.</div><?php endif; ?>
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
