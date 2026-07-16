<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$uid = (int) current_user()['id'];

$cases = $pdo->prepare("SELECT c.*, CONCAT(u.first_name,' ',u.last_name) AS client_name FROM cases c JOIN users u ON u.id=c.client_id WHERE c.lawyer_id=? ORDER BY c.updated_at DESC LIMIT 8");
$cases->execute([$uid]);
$cases = $cases->fetchAll();

$today = $pdo->prepare("SELECT * FROM appointments WHERE lawyer_id=? AND DATE(scheduled_at)=CURDATE() AND status IN ('scheduled','confirmed','rescheduled','pending') ORDER BY scheduled_at");
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

$pageTitle = __('page.dashboard');
$pageSubtitle = __('ai.subtitle.lawyer');
$portal = 'lawyer';
$activeNav = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<section class="kpi-grid">
    <article class="kpi-card">
        <div class="kpi-top">
            <div class="kpi-icon primary">C</div>
            <div class="kpi-meta">
                <div class="kpi-label"><?= __e('lawyer.kpi.assigned_cases') ?></div>
                <div class="kpi-value"><?= count($cases) ?></div>
            </div>
        </div>
        <div class="kpi-foot"><?= __e('lawyer.kpi.foot_cases') ?></div>
    </article>
    <article class="kpi-card">
        <div class="kpi-top">
            <div class="kpi-icon info">A</div>
            <div class="kpi-meta">
                <div class="kpi-label"><?= __e('lawyer.kpi.todays_appointments') ?></div>
                <div class="kpi-value"><?= count($today) ?></div>
            </div>
        </div>
        <div class="kpi-foot"><span class="kpi-delta up"><?= count($pending) ?></span> <?= __e('lawyer.kpi.foot_appointments') ?></div>
    </article>
    <article class="kpi-card">
        <div class="kpi-top">
            <div class="kpi-icon warning">H</div>
            <div class="kpi-meta">
                <div class="kpi-label"><?= __e('lawyer.kpi.upcoming_hearings') ?></div>
                <div class="kpi-value"><?= count($hearings) ?></div>
            </div>
        </div>
        <div class="kpi-foot"><?= __e('lawyer.kpi.foot_hearings') ?></div>
    </article>
    <article class="kpi-card">
        <div class="kpi-top">
            <div class="kpi-icon success">T</div>
            <div class="kpi-meta">
                <div class="kpi-label"><?= __e('lawyer.kpi.pending_tasks') ?></div>
                <div class="kpi-value"><?= count($pending) ?></div>
            </div>
        </div>
        <div class="kpi-foot"><?= __e('lawyer.kpi.foot_tasks') ?></div>
    </article>
</section>
<div class="grid grid-2">
    <div class="panel">
        <div class="panel-header"><h2><?= __e('lawyer.panel.my_cases') ?></h2><a href="cases.php"><?= __e('common.view_all') ?></a></div>
        <div class="list-stack">
            <?php foreach ($cases as $c): ?>
                <div class="list-item"><strong><a href="cases.php?id=<?= (int)$c['id'] ?>"><?= e($c['case_number']) ?></a></strong><span class="muted"><?= e(t_content($c['title'])) ?> · <?= e($c['client_name']) ?></span> <?= status_badge($c['status']) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="panel">
        <div class="panel-header"><h2><?= __e('lawyer.panel.todays_appointments') ?></h2><a href="appointments.php"><?= __e('common.manage') ?></a></div>
        <div class="list-stack">
            <?php foreach ($today as $a): ?>
                <div class="list-item"><strong><?= e(t_content($a['title'])) ?></strong><span class="muted"><?= e(format_datetime($a['scheduled_at'])) ?> · <?= e($a['location'] ? t_content($a['location']) : '') ?></span></div>
            <?php endforeach; ?>
            <?php if (!$today): ?><div class="empty-state"><?= __e('lawyer.empty.no_appointments_today') ?></div><?php endif; ?>
        </div>
    </div>
</div>
<div class="grid grid-2">
    <div class="panel">
        <h2><?= __e('lawyer.panel.upcoming_hearings') ?></h2>
        <div class="list-stack">
            <?php foreach ($hearings as $h): ?>
                <div class="list-item"><strong><?= e($h['case_number']) ?></strong><span class="muted"><?= e(format_datetime($h['hearing_date'])) ?> · <?= e(t_content($h['court_name'])) ?></span></div>
            <?php endforeach; ?>
            <?php if (!$hearings): ?><div class="empty-state"><?= __e('lawyer.empty.no_hearings') ?></div><?php endif; ?>
        </div>
    </div>
    <div class="panel">
        <h2><?= __e('common.notifications') ?></h2>
        <div class="list-stack">
            <?php foreach ($notes as $n): ?>
                <div class="list-item"><strong><?= e(t_stored($n['title'])) ?></strong><span class="muted"><?= e(t_stored($n['message'])) ?></span></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
