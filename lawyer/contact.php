<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$user = current_user();
$uid = (int) $user['id'];
ensure_contact_message_columns($pdo);

$threadId = (int) get('thread', 0);
$clientFilter = (int) get('client', 0) ?: null;
$perPage = 10;
$listPage = max(1, (int) get('page', 1));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('form_action');
    $postThread = (int) post('thread_id', $threadId ?: (int) get('thread', 0));

    if ($action === 'reply') {
        $postThread = (int) post('thread_id', $threadId ?: (int) get('thread', 0));
        if (!$postThread) {
            redirect('contact.php');
        }
        $body = trim((string) post('body'));
        if ($body === '') {
            flash('error', __('flash.message.required'));
            redirect('contact.php?thread=' . $postThread);
        }
        $root = contact_fetch_thread($pdo, $postThread);
        if (!$root) {
            flash('error', __('flash.message.denied'));
            redirect('contact.php');
        }
        $clientId = (int) $root['sender_id'] === $uid ? (int) $root['receiver_id'] : (int) $root['sender_id'];
        if (!lawyer_can_access_client($pdo, $uid, $clientId)) {
            flash('error', __('flash.message.denied'));
            redirect('contact.php');
        }
        contact_add_reply($pdo, $postThread, $uid, $clientId, $body);
        create_notification(
            $pdo,
            $clientId,
            __('notify.lawyer_message'),
            t_stored($root['subject']),
            'info',
            '../client/contact.php?thread=' . $postThread,
            $uid
        );
        flash('success', __('flash.message.sent'));
        redirect('contact.php?thread=' . $postThread);
    }
    if ($action === 'close') {
        $postThread = (int) post('thread_id', $threadId ?: (int) get('thread', 0));
        if ($postThread) {
            $root = contact_fetch_thread($pdo, $postThread);
            if ($root) {
                $clientId = (int) $root['sender_id'] === $uid ? (int) $root['receiver_id'] : (int) $root['sender_id'];
                if (lawyer_can_access_client($pdo, $uid, $clientId)) {
                    $pdo->prepare("UPDATE messages SET status='closed' WHERE thread_id=?")->execute([$postThread]);
                    flash('success', __('flash.message.closed'));
                }
            }
            redirect('contact.php?thread=' . $postThread);
        }
    }
    if ($action === 'delete') {
        $postThread = (int) post('thread_id', $threadId ?: (int) get('thread', 0));
        if ($postThread) {
            $root = contact_fetch_thread($pdo, $postThread);
            if ($root && contact_lawyer_can_access_thread($pdo, $root, $uid)) {
                contact_delete_thread($pdo, $postThread);
                flash('success', __('flash.message.deleted'));
            }
        }
        redirect('contact.php' . ($clientFilter ? '?client=' . $clientFilter : ''));
    }
    if ($action === 'edit_message') {
        $postThread = (int) post('thread_id', $threadId ?: (int) get('thread', 0));
        $messageId = (int) post('message_id');
        $body = trim((string) post('body'));
        if ($body === '') {
            flash('error', __('flash.message.required'));
            redirect('contact.php?thread=' . $postThread);
        }
        $root = contact_fetch_thread($pdo, $postThread);
        if (!$root || !contact_lawyer_can_access_thread($pdo, $root, $uid)) {
            flash('error', __('flash.message.denied'));
            redirect('contact.php');
        }
        if (contact_edit_message($pdo, $messageId, $uid, $body)) {
            flash('success', __('flash.message.updated'));
        }
        redirect('contact.php?thread=' . $postThread . ($clientFilter ? '&client=' . $clientFilter : ''));
    }
}

$clients = $pdo->prepare("SELECT DISTINCT u.id, u.first_name, u.last_name FROM users u WHERE u.role='client' AND (u.assigned_lawyer_id=? OR u.id IN (SELECT client_id FROM cases WHERE lawyer_id=?)) ORDER BY u.first_name");
$clients->execute([$uid, $uid]);
$clients = $clients->fetchAll();

$activeThread = null;
$threadMessages = [];
$contactClient = null;
$contactClientCase = null;
if ($threadId) {
    $activeThread = contact_fetch_thread($pdo, $threadId);
    if ($activeThread) {
        $clientId = (int) $activeThread['sender_id'] === $uid ? (int) $activeThread['receiver_id'] : (int) $activeThread['sender_id'];
        if (lawyer_can_access_client($pdo, $uid, $clientId)) {
            $threadMessages = contact_fetch_thread_messages($pdo, $threadId);
            contact_mark_thread_read($pdo, $threadId, $uid);
            $contactClient = contact_fetch_client_for_lawyer($pdo, $uid, $clientId);
            $contactClientCase = contact_fetch_case_summary($pdo, !empty($activeThread['case_id']) ? (int) $activeThread['case_id'] : null);
        } else {
            $activeThread = null;
            $threadId = 0;
        }
    }
} elseif ($clientFilter) {
    $contactClient = contact_fetch_client_for_lawyer($pdo, $uid, $clientFilter);
}

$threads = [];
$totalThreads = 0;
$totalPages = 1;
$shownFrom = 0;
$shownTo = 0;
if (!$threadId) {
    $totalThreads = contact_count_threads_for_lawyer($pdo, $uid, $clientFilter);
    $totalPages = max(1, (int) ceil($totalThreads / $perPage));
    if ($listPage > $totalPages) {
        $listPage = $totalPages;
    }
    $offset = ($listPage - 1) * $perPage;
    $threads = contact_fetch_threads_for_lawyer($pdo, $uid, $perPage, $offset, $clientFilter);
    $shownFrom = $totalThreads === 0 ? 0 : $offset + 1;
    $shownTo = min($offset + count($threads), $totalThreads);
}

$unreadStmt = $pdo->prepare('SELECT COUNT(DISTINCT thread_id) FROM messages WHERE receiver_id=? AND is_read=0');
$unreadStmt->execute([$uid]);
$unreadThreads = (int) $unreadStmt->fetchColumn();

$pageTitle = __('page.contact_lawyer');
$pageSubtitle = $unreadThreads
    ? __('lawyer.contact.subtitle_unread', ['count' => $unreadThreads])
    : __('lawyer.contact.subtitle');
$portal = 'lawyer';
$activeNav = 'contact';
$bodyClass = 'page-contact';
require __DIR__ . '/../includes/header.php';
?>
<div class="contact-page<?= ($threadId && $activeThread) ? ' contact-page--thread' : '' ?>">
    <?php if ($threadId && $activeThread): ?>
        <div class="contact-thread-layout">
        <?php if ($contactClient): ?>
        <?php require __DIR__ . '/../includes/contact-client-info-card.php'; ?>
        <?php endif; ?>
        <?php
        $contactThread = $activeThread;
        $contactMessages = $threadMessages;
        $contactPortal = 'lawyer';
        $contactBackUrl = 'contact.php' . ($clientFilter ? '?client=' . $clientFilter : '');
        $contactCanDelete = true;
        $contactCanClose = true;
        $contactCurrentUserId = $uid;
        require __DIR__ . '/../includes/contact-thread-panel.php';
        ?>
        </div>
    <?php else: ?>
    <?php if ($contactClient): ?>
    <?php require __DIR__ . '/../includes/contact-client-info-card.php'; ?>
    <?php endif; ?>
    <section class="panel contact-library contact-library--lawyer">
        <div class="contact-library-head">
            <div>
                <h2><?= __e('lawyer.contact.inbox') ?></h2>
                <p class="muted"><?= e(__($totalThreads === 1 ? 'lawyer.contact.thread_one' : 'lawyer.contact.thread_many', ['count' => $totalThreads])) ?></p>
            </div>
            <?php if ($clients): ?>
            <form method="get" class="contact-client-filter">
                <select name="client" onchange="this.form.submit()" aria-label="<?= __e('lawyer.contact.filter_client') ?>">
                    <option value=""><?= __e('lawyer.contact.all_clients') ?></option>
                    <?php foreach ($clients as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $clientFilter === (int) $c['id'] ? 'selected' : '' ?>><?= e(full_name($c)) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
        </div>

        <?php if (!$threads): ?>
            <div class="contact-empty"><?= __e('lawyer.contact.no_messages') ?></div>
        <?php else: ?>
        <div class="contact-thread-list">
            <?php foreach ($threads as $t): ?>
            <article class="contact-thread-item<?= (int) $t['unread_count'] > 0 ? ' is-unread' : '' ?>">
                <a class="contact-thread-main" href="contact.php?thread=<?= (int) $t['thread_id'] ?><?= $clientFilter ? '&client=' . $clientFilter : '' ?>">
                    <strong><?= e(t_stored($t['subject'])) ?></strong>
                    <p><?= e(t_stored($t['preview_body'])) ?></p>
                    <span class="contact-thread-meta">
                        <?= e($t['client_name']) ?> · <?= e(format_date($t['last_at'], 'M j, Y')) ?> ·
                        <?= e($t['status'] === 'closed' ? __('contact.status.closed') : __('contact.status.open')) ?>
                        <?php if ((int) $t['unread_count'] > 0): ?> · <?= __e('notifications.unread') ?><?php endif; ?>
                    </span>
                </a>
                <form method="post" action="contact.php<?= $clientFilter ? '?client=' . (int) $clientFilter : '' ?>" data-confirm="<?= __e('confirm.delete_message') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="delete">
                    <input type="hidden" name="thread_id" value="<?= (int) $t['thread_id'] ?>">
                    <button class="btn btn-row-delete btn-sm" type="submit"><?= __e('common.delete') ?></button>
                </form>
            </article>
            <?php endforeach; ?>
        </div>
        <div class="case-list-foot contact-library-foot">
            <p class="case-list-footer muted"><?= e(__($totalThreads === 1 ? 'contact.pager.showing_one' : 'contact.pager.showing_many', ['from' => $shownFrom, 'to' => $shownTo, 'total' => $totalThreads])) ?></p>
            <?php if ($totalPages > 1): ?>
            <nav class="case-list-pager" aria-label="<?= __e('contact.pagination.aria') ?>">
                <?php
                $pagerQs = $clientFilter ? '&client=' . $clientFilter : '';
                ?>
                <?php if ($listPage > 1): ?><a class="case-page-btn" href="?page=<?= $listPage - 1 ?><?= e($pagerQs) ?>">‹</a><?php else: ?><span class="case-page-btn is-disabled">‹</span><?php endif; ?>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a class="case-page-btn<?= $p === $listPage ? ' is-active' : '' ?>" href="?page=<?= $p ?><?= e($pagerQs) ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($listPage < $totalPages): ?><a class="case-page-btn" href="?page=<?= $listPage + 1 ?><?= e($pagerQs) ?>">›</a><?php else: ?><span class="case-page-btn is-disabled">›</span><?php endif; ?>
            </nav>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
