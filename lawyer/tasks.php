<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0, post-check=0, pre-check=0');
header('Pragma: no-cache');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() - 3600) . ' GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$uid = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (post('form_action') === 'task_status') {
        $taskId = (int) post('task_id');
        $status = (string) post('status');
        $result = update_case_task_status_for_lawyer($pdo, $taskId, $uid, $status);
        if (!$result['ok']) {
            flash('error', (string) ($result['error'] ?? __('cases.tasks.error.save_failed')));
        } else {
            flash('success', __('cases.tasks.flash.updated'));
        }
        redirect('tasks.php');
    }
}

$caseTasksAll = case_tasks_for_lawyer($pdo, $uid);

$pendingAll = $pdo->prepare("SELECT a.*, CONCAT(c.first_name,' ',c.last_name) AS client_name FROM appointments a LEFT JOIN users c ON c.id=a.client_id WHERE a.lawyer_id=? AND a.status='pending' ORDER BY a.scheduled_at");
$pendingAll->execute([$uid]);
$pendingAll = $pendingAll->fetchAll();

$upcomingAll = $pdo->prepare("SELECT a.*, CONCAT(c.first_name,' ',c.last_name) AS client_name FROM appointments a LEFT JOIN users c ON c.id=a.client_id WHERE a.lawyer_id=? AND a.status IN ('scheduled','confirmed','rescheduled','pending') AND a.scheduled_at >= NOW() ORDER BY a.scheduled_at");
$upcomingAll->execute([$uid]);
$upcomingAll = $upcomingAll->fetchAll();

$notesAll = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? AND is_read=0 ORDER BY created_at DESC');
$notesAll->execute([$uid]);
$notesAll = $notesAll->fetchAll();

$perPage = 3;
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
$caseTasksPage = $slicePage($caseTasksAll, 'ctasks');

$pending = $pendingPage['items'];
$notes = $notesPage['items'];
$upcoming = $upcomingPage['items'];
$caseTasks = $caseTasksPage['items'];

$tasksPagerQs = static function (array $pager, int $targetPage) use ($pendingPage, $notesPage, $upcomingPage, $caseTasksPage): string {
    $q = [
        'pending' => $pendingPage['page'],
        'notes' => $notesPage['page'],
        'page' => $upcomingPage['page'],
        'ctasks' => $caseTasksPage['page'],
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
    <div class="case-list-foot tasks-section-foot">
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
$pageSubtitle = __('lawyer.tasks.subtitle');
$portal = 'lawyer';
$activeNav = 'tasks';
$bodyClass = 'page-tasks';
require __DIR__ . '/../includes/header.php';

$iconPlay = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>';
$iconCheck = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
$iconView = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
$iconAppt = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/></svg>';
$iconBell = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M18 16v-5a6 6 0 1 0-12 0v5"/><path d="M5 16h14"/><path d="M10 19a2 2 0 0 0 4 0"/></svg>';
$iconCal = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>';
?>
<!-- CACHE CLEAR: <?= time() ?> -->
<div class="tasks-page">
    <div class="tasks-summary">
        <div class="tasks-stat">
            <span class="tasks-stat-value"><?= (int) $caseTasksPage['total'] ?></span>
            <span class="tasks-stat-label"><?= __e('lawyer.tasks.case_tasks') ?></span>
        </div>
        <div class="tasks-stat">
            <span class="tasks-stat-value"><?= (int) $pendingPage['total'] ?></span>
            <span class="tasks-stat-label"><?= __e('lawyer.tasks.stat_pending') ?></span>
        </div>
        <div class="tasks-stat<?= $notesPage['total'] > 0 ? ' is-accent' : '' ?>">
            <span class="tasks-stat-value"><?= (int) $notesPage['total'] ?></span>
            <span class="tasks-stat-label"><?= __e('lawyer.tasks.stat_unread') ?></span>
        </div>
        <div class="tasks-stat">
            <span class="tasks-stat-value"><?= (int) $upcomingPage['total'] ?></span>
            <span class="tasks-stat-label"><?= __e('lawyer.tasks.stat_upcoming') ?></span>
        </div>
    </div>

    <div class="tasks-split">
        <section class="panel tasks-section">
            <div class="tasks-section-head">
                <div>
                    <h2><?= __e('lawyer.tasks.pending_responses') ?></h2>
                </div>
                <a class="tasks-section-link" href="appointments.php"><?= __e('common.open') ?></a>
            </div>
            <div class="tasks-feed">
                <?php foreach ($pending as $a): ?>
                <article class="tasks-feed-item">
                    <div class="tasks-feed-mark" aria-hidden="true"><?= $iconAppt ?></div>
                    <div class="tasks-feed-body">
                        <strong><?= e(t_content($a['title'])) ?></strong>
                        <span class="muted"><?= e(format_datetime($a['scheduled_at'])) ?> · <?= e($a['client_name'] ?: __('common.client')) ?></span>
                    </div>
                    <div class="tasks-feed-side"><?= status_badge($a['status']) ?></div>
                </article>
                <?php endforeach; ?>
                <?php if (!$pending): ?>
                <div class="tasks-empty tasks-empty--compact"><?= __e('lawyer.tasks.empty') ?></div>
                <?php endif; ?>
            </div>
            <?php $renderTasksPager($pendingPage, 'tasks.pagination.aria', 'tasks.pager.showing_one', 'tasks.pager.showing_many'); ?>
        </section>

        <section class="panel tasks-section">
            <div class="tasks-section-head">
                <div>
                    <h2><?= __e('lawyer.tasks.unread') ?></h2>
                </div>
                <a class="tasks-section-link" href="notifications.php"><?= __e('common.all') ?></a>
            </div>
            <div class="tasks-feed">
                <?php foreach ($notes as $n): ?>
                <article class="tasks-feed-item">
                    <div class="tasks-feed-mark is-alert" aria-hidden="true"><?= $iconBell ?></div>
                    <div class="tasks-feed-body">
                        <strong><?= e(t_stored($n['title'])) ?></strong>
                        <span class="muted"><?= e(t_stored($n['message'])) ?></span>
                    </div>
                </article>
                <?php endforeach; ?>
                <?php if (!$notes): ?>
                <div class="tasks-empty tasks-empty--compact"><?= __e('lawyer.tasks.caught_up') ?></div>
                <?php endif; ?>
            </div>
            <?php $renderTasksPager($notesPage, 'notifications.pagination.aria', 'notifications.pager.showing_one', 'notifications.pager.showing_many'); ?>
        </section>
    </div>

    <section class="panel tasks-section">
        <div class="tasks-section-head">
            <div>
                <h2><?= __e('lawyer.tasks.case_tasks') ?></h2>
                <p class="muted"><?= __e('lawyer.tasks.case_tasks_help') ?></p>
            </div>
        </div>
        <?php if (!$caseTasks): ?>
        <div class="tasks-empty"><?= __e('lawyer.tasks.case_tasks_empty') ?></div>
        <?php else: ?>
        <div class="table-wrap case-table-wrap">
            <table class="case-table tasks-case-table">
                <thead>
                    <tr>
                        <th><?= __e('common.case') ?></th>
                        <th><?= __e('common.title') ?></th>
                        <th><?= __e('cases.tasks.due_date') ?></th>
                        <th><?= __e('common.status') ?></th>
                        <th class="col-actions"><?= __e('common.actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($caseTasks as $task): ?>
                    <tr>
                        <td>
                            <strong><?= e($task['case_number']) ?></strong>
                            <div class="muted"><?= e(t_content((string) ($task['case_title'] ?? ''))) ?></div>
                        </td>
                        <td><?= e($task['title']) ?></td>
                        <td><?= e(format_date($task['due_date'])) ?></td>
                        <td><?= status_badge($task['status']) ?></td>
                        <td class="col-actions">
                            <div class="tasks-row-actions">
                                <?php if ($task['status'] === 'open'): ?>
                                <form method="post" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="form_action" value="task_status">
                                    <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                                    <input type="hidden" name="status" value="in_progress">
                                    <button class="tasks-icon-btn" type="submit" title="<?= __e('cases.tasks.start') ?>" aria-label="<?= __e('cases.tasks.start') ?>"><?= $iconPlay ?></button>
                                </form>
                                <?php endif; ?>
                                <?php if (in_array($task['status'], ['open', 'in_progress'], true)): ?>
                                <form method="post" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="form_action" value="task_status">
                                    <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                                    <input type="hidden" name="status" value="done">
                                    <button class="tasks-icon-btn is-success" type="submit" title="<?= __e('cases.tasks.complete') ?>" aria-label="<?= __e('cases.tasks.complete') ?>"><?= $iconCheck ?></button>
                                </form>
                                <?php endif; ?>
                                <a class="tasks-icon-btn" href="cases.php?action=view&id=<?= (int) $task['case_id'] ?>" title="<?= __e('common.view') ?>" aria-label="<?= __e('common.view') ?>"><?= $iconView ?></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php $renderTasksPager($caseTasksPage, 'cases.tasks.pagination.aria', 'cases.tasks.pager.showing_one', 'cases.tasks.pager.showing_many'); ?>
        <?php endif; ?>
    </section>

    <section class="panel tasks-section">
        <div class="tasks-section-head">
            <div>
                <h2><?= __e('lawyer.tasks.upcoming') ?></h2>
            </div>
            <a class="tasks-section-link" href="appointments.php"><?= __e('common.open') ?></a>
        </div>
        <div class="tasks-feed">
            <?php foreach ($upcoming as $a): ?>
            <article class="tasks-feed-item">
                <div class="tasks-feed-mark" aria-hidden="true"><?= $iconCal ?></div>
                <div class="tasks-feed-body">
                    <strong><?= e(t_content($a['title'])) ?></strong>
                    <span class="muted"><?= e(format_datetime($a['scheduled_at'])) ?> · <?= e($a['client_name'] ?: __('common.client')) ?> · <?= e($a['location'] ? t_content($a['location']) : __('common.location')) ?></span>
                </div>
            </article>
            <?php endforeach; ?>
            <?php if (!$upcoming): ?>
            <div class="tasks-empty tasks-empty--compact"><?= __e('lawyer.tasks.no_upcoming') ?></div>
            <?php endif; ?>
        </div>
        <?php $renderTasksPager($upcomingPage, 'tasks.pagination.aria', 'tasks.pager.showing_one', 'tasks.pager.showing_many'); ?>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
