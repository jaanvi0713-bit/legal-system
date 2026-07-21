<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$uid = (int) current_user()['id'];

$clients = $pdo->prepare("SELECT DISTINCT u.*, (SELECT COUNT(*) FROM cases c WHERE c.client_id=u.id AND " . lawyer_case_access_sql('c') . ") AS case_count FROM users u WHERE u.role='client' AND (u.assigned_lawyer_id=? OR u.id IN (SELECT client_id FROM cases c2 WHERE " . lawyer_case_access_sql('c2') . ")) ORDER BY u.first_name");
$clients->execute([$uid, $uid, $uid, $uid]);
$clients = $clients->fetchAll();

$id = (int) get('id', 0);
$action = get('action', $id ? 'view' : 'list');
$pageTitle = __('page.clients_short');
$pageSubtitle = __('lawyer.clients.permission_note');
$portal = 'lawyer';
$activeNav = 'clients';

if ($action === 'view' && $id) {
    $allowed = false;
    foreach ($clients as $c) {
        if ((int) $c['id'] === $id) {
            $allowed = true;
            break;
        }
    }
    if (!$allowed) {
        flash('error', __('flash.client.not_assigned'));
        redirect('clients.php');
    }
    $stmt = $pdo->prepare(
        'SELECT c.*, CONCAT(l.first_name, " ", l.last_name) AS lawyer_name
         FROM users c
         LEFT JOIN users l ON l.id = c.assigned_lawyer_id
         WHERE c.id = ? AND c.role = "client"'
    );
    $stmt->execute([$id]);
    $client = $stmt->fetch();
    if (!$client) {
        flash('error', __('flash.client.not_assigned'));
        redirect('clients.php');
    }
    $cases = $pdo->prepare('SELECT * FROM cases WHERE client_id = ? AND lawyer_id = ? ORDER BY created_at DESC');
    $cases->execute([$id, $uid]);
    $clientCases = $cases->fetchAll();
    $clientLawyerName = trim((string) ($client['lawyer_name'] ?? ''));
    $viewBackUrl = 'clients.php';
    $viewMailto = (string) ($client['email'] ?? '');
    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../includes/client-view.php';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$totalClients = count($clients);
$perPage = 10;
$page = max(1, (int) get('page', 1));
$totalPages = max(1, (int) ceil($totalClients / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$pageClients = array_slice($clients, $offset, $perPage);
$shownFrom = $totalClients === 0 ? 0 : $offset + 1;
$shownTo = min($offset + count($pageClients), $totalClients);

require __DIR__ . '/../includes/header.php';
?>
<div class="panel case-list-panel">
    <div class="case-list-head">
        <div class="case-list-title">
            <h2><?= __e('lawyer.clients.assigned') ?></h2>
        </div>
    </div>
    <div class="table-wrap case-table-wrap">
        <table class="case-table">
            <thead>
                <tr>
                    <th><?= __e('common.client') ?></th>
                    <th><?= __e('common.contact') ?></th>
                    <th><?= __e('common.address') ?></th>
                    <th><?= __e('nav.cases') ?></th>
                    <th class="col-actions"><?= __e('common.actions') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pageClients as $c): ?>
                <tr>
                    <td><strong><?= e(full_name($c)) ?></strong></td>
                    <td>
                        <a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a>
                        <div class="muted"><?= e($c['phone'] ?: __('common.em_dash')) ?></div>
                    </td>
                    <td><?= e(trim((string) ($c['address'] ?? '')) !== '' ? $c['address'] : __('common.em_dash')) ?></td>
                    <td><span class="badge badge-dark"><?= (int) $c['case_count'] ?></span></td>
                    <td class="col-actions">
                        <div class="row-actions">
                            <a class="btn btn-row-edit btn-sm" href="?action=view&id=<?= (int) $c['id'] ?>"><?= __e('common.view') ?></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pageClients): ?>
                <tr><td colspan="5" class="muted"><?= __e('common.no_records') ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="case-list-foot">
        <p class="case-list-footer muted"><?= e(__($totalClients === 1 ? 'clients.pager.showing_one' : 'clients.pager.showing_many', ['from' => (int) $shownFrom, 'to' => (int) $shownTo, 'total' => (int) $totalClients])) ?></p>
        <?php if ($totalPages > 1): ?>
        <nav class="case-list-pager" aria-label="<?= __e('clients.pagination.aria') ?>">
            <?php if ($page > 1): ?>
            <a class="case-page-btn" href="?page=<?= $page - 1 ?>" aria-label="<?= __e('cases.pagination.prev') ?>">‹</a>
            <?php else: ?>
            <span class="case-page-btn is-disabled" aria-disabled="true">‹</span>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a class="case-page-btn<?= $p === $page ? ' is-active' : '' ?>" href="?page=<?= $p ?>"<?= $p === $page ? ' aria-current="page"' : '' ?>><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a class="case-page-btn" href="?page=<?= $page + 1 ?>" aria-label="<?= __e('cases.pagination.next') ?>">›</a>
            <?php else: ?>
            <span class="case-page-btn is-disabled" aria-disabled="true">›</span>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
