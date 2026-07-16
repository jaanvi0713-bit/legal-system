<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$uid = (int) current_user()['id'];

$clients = $pdo->prepare("SELECT DISTINCT u.*, (SELECT COUNT(*) FROM cases c WHERE c.client_id=u.id AND c.lawyer_id=?) AS case_count FROM users u WHERE u.role='client' AND (u.assigned_lawyer_id=? OR u.id IN (SELECT client_id FROM cases WHERE lawyer_id=?)) ORDER BY u.first_name");
$clients->execute([$uid, $uid, $uid]);
$clients = $clients->fetchAll();

$id = (int) get('id', 0);
$pageTitle = __('page.clients_short');
$pageSubtitle = __('lawyer.clients.permission_note');
$portal = 'lawyer';
$activeNav = 'clients';

if ($id) {
    $allowed = false;
    foreach ($clients as $c) if ((int)$c['id'] === $id) $allowed = true;
    if (!$allowed) { flash('error', __('flash.client.not_assigned')); redirect('clients.php'); }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="client"');
    $stmt->execute([$id]);
    $client = $stmt->fetch();
    $cases = $pdo->prepare('SELECT * FROM cases WHERE client_id=? AND lawyer_id=?');
    $cases->execute([$id, $uid]);
    $cases = $cases->fetchAll();
    $docs = $pdo->prepare('SELECT * FROM case_documents WHERE client_id=? ORDER BY created_at DESC LIMIT 20');
    $docs->execute([$id]);
    $docs = $docs->fetchAll();
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="panel">
        <div class="panel-header">
            <div><h2><?= e(full_name($client)) ?></h2><p class="muted"><?= e($client['company_name'] ?: '') ?></p></div>
            <a class="btn btn-primary btn-sm" href="mailto:<?= e($client['email']) ?>"><?= __e('lawyer.clients.contact') ?></a>
        </div>
        <div class="grid grid-2">
            <div class="list-item"><strong><?= __e('common.email') ?></strong><?= e($client['email']) ?></div>
            <div class="list-item"><strong><?= __e('common.phone') ?></strong><?= e($client['phone'] ?: __('common.em_dash')) ?></div>
            <div class="list-item span-2"><strong><?= __e('common.history') ?></strong><?= nl2br(e($client['notes'] ?: __('lawyer.clients.no_notes'))) ?></div>
        </div>
    </div>
    <div class="grid grid-2">
        <div class="panel"><h2><?= __e('nav.cases') ?></h2><div class="list-stack"><?php foreach ($cases as $c): ?><div class="list-item"><a href="cases.php?id=<?= (int)$c['id'] ?>"><strong><?= e($c['case_number']) ?></strong></a> <?= status_badge($c['status']) ?></div><?php endforeach; ?></div></div>
        <div class="panel"><h2><?= __e('nav.documents') ?></h2><div class="list-stack"><?php foreach ($docs as $d): ?><div class="list-item"><strong><?= e(t_content($d['title'])) ?></strong><a href="../<?= e($d['file_path']) ?>" target="_blank"><?= __e('common.open') ?></a></div><?php endforeach; ?><?php if (!$docs): ?><div class="empty-state"><?= __e('lawyer.clients.no_documents') ?></div><?php endif; ?></div></div>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <h2><?= __e('lawyer.clients.assigned') ?></h2>
    <p class="muted"><?= __e('lawyer.clients.permission_note') ?></p>
    <div class="table-wrap">
        <table>
            <thead><tr><th><?= __e('common.client') ?></th><th><?= __e('common.company') ?></th><th><?= __e('nav.cases') ?></th><th><?= __e('common.contact') ?></th></tr></thead>
            <tbody>
            <?php foreach ($clients as $c): ?>
                <tr>
                    <td><a href="?id=<?= (int)$c['id'] ?>"><strong><?= e(full_name($c)) ?></strong></a></td>
                    <td><?= e($c['company_name'] ?: __('common.em_dash')) ?></td>
                    <td><?= (int)$c['case_count'] ?></td>
                    <td><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
