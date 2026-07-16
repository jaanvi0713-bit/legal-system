<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['client']);
$pdo = db();
$uid = (int) current_user()['id'];
$id = (int) get('id', 0);

$pageTitle = __('page.my_cases');
$pageSubtitle = __('ai.subtitle.client');
$portal = 'client';
$activeNav = 'cases';

if ($id) {
    $stmt = $pdo->prepare("SELECT c.*, CONCAT(l.first_name,' ',l.last_name) AS lawyer_name, l.email AS lawyer_email, l.phone AS lawyer_phone FROM cases c LEFT JOIN users l ON l.id=c.lawyer_id WHERE c.id=? AND c.client_id=?");
    $stmt->execute([$id, $uid]);
    $case = $stmt->fetch();
    if (!$case) { flash('error', __('flash.case.not_found')); redirect('cases.php'); }
    $notes = $pdo->prepare('SELECT n.*, CONCAT(u.first_name," ",u.last_name) AS author FROM case_notes n JOIN users u ON u.id=n.user_id WHERE n.case_id=? AND n.is_private=0 ORDER BY n.created_at DESC');
    $notes->execute([$id]);
    $notes = $notes->fetchAll();
    $hearings = $pdo->prepare('SELECT * FROM court_hearings WHERE case_id=? ORDER BY hearing_date DESC');
    $hearings->execute([$id]);
    $hearings = $hearings->fetchAll();
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="panel">
        <div class="panel-header">
            <div>
                <h2><?= e($case['case_number']) ?> · <?= e(t_content($case['title'])) ?></h2>
                <p class="muted"><?= __e('client.cases.assigned_lawyer') ?> <?= e($case['lawyer_name'] ?: __('lawyer.to_be_assigned')) ?></p>
            </div>
            <?= status_badge($case['status']) ?>
        </div>
        <p><?= nl2br(e($case['description'] ? t_content($case['description']) : __('common.no_records'))) ?></p>
        <div class="grid grid-3" style="margin-top:1rem;">
            <div class="list-item"><strong><?= __e('client.cases.lawyer_contact') ?></strong><?= e($case['lawyer_email'] ?: __('common.em_dash')) ?><div class="muted"><?= e($case['lawyer_phone'] ?: '') ?></div></div>
            <div class="list-item"><strong><?= __e('common.court') ?></strong><?= e($case['court_name'] ?: __('common.em_dash')) ?></div>
            <div class="list-item"><strong><?= __e('client.cases.next_hearing') ?></strong><?= e(format_date($case['next_hearing_date'])) ?></div>
        </div>
    </div>
    <div class="grid grid-2">
        <div class="panel">
            <h2><?= __e('client.cases.updates') ?></h2>
            <div class="list-stack">
                <?php foreach ($notes as $n): ?>
                    <div class="list-item"><strong><?= e($n['author']) ?></strong><span class="muted"><?= e(format_datetime($n['created_at'])) ?></span><div><?= nl2br(e(t_content($n['note']))) ?></div></div>
                <?php endforeach; ?>
                <?php if (!$notes): ?><div class="empty-state"><?= __e('client.cases.no_updates') ?></div><?php endif; ?>
            </div>
        </div>
        <div class="panel">
            <h2><?= __e('client.cases.court_progress') ?></h2>
            <div class="list-stack">
                <?php foreach ($hearings as $h): ?>
                    <div class="list-item">
                        <strong><?= e(format_datetime($h['hearing_date'])) ?> · <?= status_badge($h['status']) ?></strong>
                        <span class="muted"><?= e(t_content($h['court_name'])) ?> · <?= e($h['hearing_type'] ? t_content($h['hearing_type']) : '') ?></span>
                        <div><?= e($h['outcome'] ? t_content($h['outcome']) : __('court.outcome_pending')) ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$hearings): ?><div class="empty-state"><?= __e('client.cases.no_hearings') ?></div><?php endif; ?>
            </div>
        </div>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

$rows = $pdo->prepare("SELECT c.*, CONCAT(l.first_name,' ',l.last_name) AS lawyer_name FROM cases c LEFT JOIN users l ON l.id=c.lawyer_id WHERE c.client_id=? ORDER BY c.updated_at DESC");
$rows->execute([$uid]);
$rows = $rows->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <h2><?= __e('client.cases.your_cases') ?></h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th><?= __e('common.case') ?></th><th><?= __e('common.lawyer') ?></th><th><?= __e('common.status') ?></th><th><?= __e('common.progress') ?></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $c): ?>
                <tr>
                    <td><a href="?id=<?= (int)$c['id'] ?>"><strong><?= e($c['case_number']) ?></strong></a><div class="muted"><?= e(t_content($c['title'])) ?></div></td>
                    <td><?= e($c['lawyer_name'] ?: __('common.em_dash')) ?></td>
                    <td><?= status_badge($c['status']) ?></td>
                    <td><?= e(format_date($c['next_hearing_date']) !== '—' ? trim(__('court.hearing_prefix')) . ' ' . format_date($c['next_hearing_date']) : translate_status('active')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
