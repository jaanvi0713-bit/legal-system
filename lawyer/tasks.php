<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$uid = (int) current_user()['id'];

$pendingAll = $pdo->prepare("SELECT a.*, CONCAT(c.first_name,' ',c.last_name) AS client_name FROM appointments a LEFT JOIN users c ON c.id=a.client_id WHERE a.lawyer_id=? AND a.status='pending' ORDER BY a.scheduled_at");
$pendingAll->execute([$uid]);
$pendingAll = $pendingAll->fetchAll();

$upcomingAll = $pdo->prepare("SELECT a.*, CONCAT(c.first_name,' ',c.last_name) AS client_name FROM appointments a LEFT JOIN users c ON c.id=a.client_id WHERE a.lawyer_id=? AND a.status IN ('scheduled','confirmed','rescheduled','pending') AND a.scheduled_at >= NOW() ORDER BY a.scheduled_at");
$upcomingAll->execute([$uid]);
$upcomingAll = $upcomingAll->fetchAll();

$notesAll = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? AND is_read=0 ORDER BY created_at DESC');
$notesAll->execute([$uid]);
$notesAll = $notesAll->fetchAll();

$perPage = 10;
$slicePage = static function (array $items, string $param) use ($perPage): array {
    $total = count($items);
    $page = max(1, (int) get($param, 1));
    $pages = max(1, (int) ceil($total / $perPage));
    if ($page > $pages) {
        $page = $pages;
    }
    $offset = ($page - 1) * $perPage;
    $slice = array_slice($items, $offset, $perPage);
    return [
        'items' => $slice,
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'from' => $total === 0 ? 0 : $offset + 1,
        'to' => min($offset + count($slice), $total),
        'param' => $param,
    ];
};

$pendingPage = $slicePage($pendingAll, 'pending');
$notesPage = $slicePage($notesAll, 'notes');
$upcomingPage = $slicePage($upcomingAll, 'page');

$pending = $pendingPage['items'];
$notes = $notesPage['items'];
$upcoming = $upcomingPage['items'];

$tasksPagerQs = static function (array $pager, int $targetPage) use ($pendingPage, $notesPage, $upcomingPage): string {
    $q = [
        'pending' => $pendingPage['page'],
        'notes' => $notesPage['page'],
        'page' => $upcomingPage['page'],
    ];
    $q[$pager['param']] = $targetPage;
    $parts = [];
    foreach ($q as $key => $val) {
        if ((int) $val > 1) {
            $parts[] = $key . '=' . (int) $val;
        }
    }
    return $parts ? '?' . implode('&', $parts) : '?';
};

$renderTasksPager = static function (array $pager, string $ariaKey, string $oneKey, string $manyKey) use ($tasksPagerQs): void {
    if ($pager['total'] === 0 && $pager['pages'] <= 1) {
        return;
    }
    ?>
    <div class="case-list-foot" style="margin-top:0.75rem;">
        <p class="case-list-footer muted"><?= e(__($pager['total'] === 1 ? $oneKey : $manyKey, ['from' => (int) $pager['from'], 'to' => (int) $pager['to'], 'total' => (int) $pager['total']])) ?></p>
        <?php if ($pager['pages'] > 1): ?>
        <nav class="case-list-pager" aria-label="<?= __e($ariaKey) ?>">
            <?php if ($pager['page'] > 1): ?>
            <a class="case-page-btn" href="<?= e($tasksPagerQs($pager, $pager['page'] - 1)) ?>" aria-label="<?= __e('cases.pagination.prev') ?>">‹</a>
            <?php else: ?>
            <span class="case-page-btn is-disabled" aria-disabled="true">‹</span>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $pager['pages']; $p++): ?>
            <a class="case-page-btn<?= $p === $pager['page'] ? ' is-active' : '' ?>" href="<?= e($tasksPagerQs($pager, $p)) ?>"<?= $p === $pager['page'] ? ' aria-current="page"' : '' ?>><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($pager['page'] < $pager['pages']): ?>
            <a class="case-page-btn" href="<?= e($tasksPagerQs($pager, $pager['page'] + 1)) ?>" aria-label="<?= __e('cases.pagination.next') ?>">›</a>
            <?php else: ?>
            <span class="case-page-btn is-disabled" aria-disabled="true">›</span>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
    <?php
};

$pageTitle = __('page.tasks');
$pageSubtitle = __('ai.subtitle.lawyer');
$portal = 'lawyer';
$activeNav = 'tasks';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-2">
    <div class="panel">
        <div class="panel-header"><h2><?= __e('lawyer.tasks.pending_responses') ?></h2><a href="appointments.php"><?= __e('common.open') ?></a></div>
        <div class="list-stack">
            <?php foreach ($pending as $a): ?>
                <div class="list-item">
                    <strong><?= e(t_content($a['title'])) ?></strong>
                    <span class="muted"><?= e(format_datetime($a['scheduled_at'])) ?> · <?= e($a['client_name'] ?: __('common.client')) ?></span>
                    <?= status_badge($a['status']) ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$pending): ?><div class="empty-state"><?= __e('lawyer.tasks.empty') ?></div><?php endif; ?>
        </div>
        <?php $renderTasksPager($pendingPage, 'tasks.pagination.aria', 'tasks.pager.showing_one', 'tasks.pager.showing_many'); ?>
    </div>
    <div class="panel">
        <div class="panel-header"><h2><?= __e('lawyer.tasks.unread') ?></h2><a href="notifications.php"><?= __e('common.all') ?></a></div>
        <div class="list-stack">
            <?php foreach ($notes as $n): ?>
                <div class="list-item">
                    <strong><?= e(t_stored($n['title'])) ?></strong>
                    <span class="muted"><?= e(t_stored($n['message'])) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$notes): ?><div class="empty-state"><?= __e('lawyer.tasks.caught_up') ?></div><?php endif; ?>
        </div>
        <?php $renderTasksPager($notesPage, 'notifications.pagination.aria', 'notifications.pager.showing_one', 'notifications.pager.showing_many'); ?>
    </div>
</div>
<div class="panel">
    <div class="panel-header"><h2><?= __e('lawyer.tasks.upcoming') ?></h2></div>
    <div class="list-stack">
        <?php foreach ($upcoming as $a): ?>
            <div class="list-item">
                <strong><?= e(t_content($a['title'])) ?></strong>
                <span class="muted"><?= e(format_datetime($a['scheduled_at'])) ?> · <?= e($a['client_name'] ?: __('common.client')) ?> · <?= e($a['location'] ? t_content($a['location']) : __('common.location')) ?></span>
            </div>
        <?php endforeach; ?>
        <?php if (!$upcoming): ?><div class="empty-state"><?= __e('lawyer.tasks.no_upcoming') ?></div><?php endif; ?>
    </div>
    <?php $renderTasksPager($upcomingPage, 'tasks.pagination.aria', 'tasks.pager.showing_one', 'tasks.pager.showing_many'); ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
