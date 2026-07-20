<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['client']);
$pdo = db();
$user = current_user();
$uid = (int) $user['id'];
ensure_contact_message_columns($pdo);

$clientLawyers = contact_fetch_client_lawyers($pdo, $uid);
$sendTargets = contact_fetch_send_targets($pdo, $uid);
$requiresCaseForSend = count($clientLawyers) > 1;

// Distinguish "never had a lawyer" from "all cases closed" for the empty state.
$clientCaseCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cases WHERE client_id = ?');
$clientCaseCountStmt->execute([$uid]);
$clientHasAnyCase = (int) $clientCaseCountStmt->fetchColumn() > 0;
$noContactMessage = (!$clientLawyers && $clientHasAnyCase)
    ? __('client.contact.no_open_case')
    : __('client.contact.no_lawyer');

$threadId = (int) get('thread', 0);
$perPage = 10;
$listPage = max(1, (int) get('page', 1));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('form_action');
    if ($action === 'send') {
        if (!$clientLawyers) {
            flash('error', __('flash.lawyer.not_assigned'));
            redirect('contact.php');
        }
        $subject = trim((string) post('subject'));
        $body = trim((string) post('body'));
        if ($subject === '' || $body === '') {
            flash('error', __('flash.message.required'));
            redirect('contact.php');
        }
        $caseId = post('case_id') ? (int) post('case_id') : null;
        $sendLawyerId = post('lawyer_id') ? (int) post('lawyer_id') : null;
        $sendTarget = trim((string) post('send_target'));
        if ($sendTarget !== '') {
            if (preg_match('/^case-(\d+)$/', $sendTarget, $m)) {
                $caseId = (int) $m[1];
                $sendLawyerId = null;
            } elseif (preg_match('/^lawyer-(\d+)$/', $sendTarget, $m)) {
                $caseId = null;
                $sendLawyerId = (int) $m[1];
            }
        }
        if ($requiresCaseForSend && $caseId === null && !$sendLawyerId) {
            flash('error', __('flash.contact.select_case'));
            redirect('contact.php');
        }
        $resolvedLawyerId = contact_resolve_send_lawyer($pdo, $uid, $caseId, $sendLawyerId);
        if (!$resolvedLawyerId) {
            flash('error', $caseId ? __('flash.contact.case_no_lawyer') : __('flash.contact.select_case'));
            redirect('contact.php');
        }
        $newThread = contact_create_thread($pdo, $uid, $resolvedLawyerId, $caseId, $subject, $body);
        create_notification(
            $pdo,
            $resolvedLawyerId,
            __('notify.client_message'),
            $subject,
            'info',
            '../lawyer/contact.php?thread=' . $newThread,
            $uid
        );
        flash('success', __('flash.message.sent'));
        redirect('contact.php?thread=' . $newThread);
    }
    if ($action === 'reply') {
        $postThread = (int) post('thread_id', $threadId);
        if (!$postThread) {
            redirect('contact.php');
        }
        $body = trim((string) post('body'));
        if ($body === '') {
            flash('error', __('flash.message.required'));
            redirect('contact.php?thread=' . $postThread);
        }
        $root = contact_fetch_thread($pdo, $postThread);
        if (!$root || !in_array($uid, [(int) $root['sender_id'], (int) $root['receiver_id']], true)) {
            flash('error', __('flash.message.denied'));
            redirect('contact.php');
        }
        $receiverId = (int) $root['sender_id'] === $uid ? (int) $root['receiver_id'] : (int) $root['sender_id'];
        contact_add_reply($pdo, $postThread, $uid, $receiverId, $body);
        $roleStmt = $pdo->prepare('SELECT role FROM users WHERE id=?');
        $roleStmt->execute([$receiverId]);
        $receiverRole = $roleStmt->fetchColumn();
        $notifyTitle = $receiverRole === 'lawyer' ? __('notify.client_message') : __('notify.lawyer_message');
        create_notification(
            $pdo,
            $receiverId,
            $notifyTitle,
            t_stored($root['subject']),
            'info',
            ($receiverRole === 'lawyer' ? '../lawyer/contact.php?thread=' : 'contact.php?thread=') . $postThread,
            $uid
        );
        flash('success', __('flash.message.sent'));
        redirect('contact.php?thread=' . $postThread);
    }
    if ($action === 'delete') {
        $postThread = (int) post('thread_id', $threadId);
        if ($postThread) {
            $root = contact_fetch_thread($pdo, $postThread);
            if ($root && contact_user_in_thread($root, $uid)) {
                contact_delete_thread($pdo, $postThread);
                flash('success', __('flash.message.deleted'));
            }
        }
        redirect('contact.php');
    }
    if ($action === 'edit_message') {
        $postThread = (int) post('thread_id', $threadId);
        $messageId = (int) post('message_id');
        $body = trim((string) post('body'));
        if ($body === '') {
            flash('error', __('flash.message.required'));
            redirect('contact.php?thread=' . $postThread);
        }
        $root = contact_fetch_thread($pdo, $postThread);
        if (!$root || !contact_user_in_thread($root, $uid)) {
            flash('error', __('flash.message.denied'));
            redirect('contact.php');
        }
        if (contact_edit_message($pdo, $messageId, $uid, $body)) {
            flash('success', __('flash.message.updated'));
        }
        redirect('contact.php?thread=' . $postThread);
    }
}

$caseOptions = array_values(array_filter($sendTargets, fn($t) => $t['case_id'] !== null));

$companyName = get_setting($pdo, 'company_name', app_config('name'));
$companyAddress = get_setting($pdo, 'company_address', '');
$businessHours = get_setting($pdo, 'company_hours', __('contact.default_hours'));

$activeThread = null;
$threadMessages = [];
if ($threadId) {
    $activeThread = contact_fetch_thread($pdo, $threadId);
    if ($activeThread && contact_user_in_thread($activeThread, $uid)) {
        $threadMessages = contact_fetch_thread_messages($pdo, $threadId);
        contact_mark_thread_read($pdo, $threadId, $uid);
    } else {
        $activeThread = null;
        $threadId = 0;
    }
}

$threads = [];
$totalThreads = 0;
$totalPages = 1;
$shownFrom = 0;
$shownTo = 0;
if (!$threadId) {
    $totalThreads = contact_count_all_threads_for_client($pdo, $uid);
    $totalPages = max(1, (int) ceil($totalThreads / $perPage));
    if ($listPage > $totalPages) {
        $listPage = $totalPages;
    }
    $offset = ($listPage - 1) * $perPage;
    $threads = contact_fetch_all_threads_for_client($pdo, $uid, $perPage, $offset);
    $shownFrom = $totalThreads === 0 ? 0 : $offset + 1;
    $shownTo = min($offset + count($threads), $totalThreads);
}

$pageTitle = __('page.contact');
$pageSubtitle = __('client.contact.subtitle');
$portal = 'client';
$activeNav = 'contact';
$bodyClass = 'page-contact';
require __DIR__ . '/../includes/header.php';
?>
<div class="contact-page">
    <?php if ($threadId && $activeThread): ?>
        <?php
        $contactThread = $activeThread;
        $contactMessages = $threadMessages;
        $contactPortal = 'client';
        $contactBackUrl = 'contact.php';
        $contactCanDelete = contact_user_in_thread($activeThread, $uid);
        $contactCanClose = false;
        $contactCurrentUserId = $uid;
        require __DIR__ . '/../includes/contact-thread-panel.php';
        ?>
    <?php else: ?>
    <div class="contact-grid">
        <section class="panel contact-info-card">
            <div class="notify-board-banner">
                <div class="notify-board-banner-copy">
                    <h2><?= __e('client.contact.office_info') ?></h2>
                    <p><?= __e('client.contact.office_info_help') ?></p>
                </div>
            </div>
            <div class="contact-info-body">
            <dl class="contact-info-list">
                <?php if ($clientLawyers): ?>
                <div class="contact-info-item contact-info-item--lawyers">
                    <span class="contact-info-icon"><?= contact_info_icon_svg('lawyer') ?></span>
                    <div class="contact-info-copy">
                        <dt><?= count($clientLawyers) > 1 ? __e('client.contact.your_lawyers') : __e('client.contact.your_lawyer') ?></dt>
                        <dd class="contact-lawyer-picker">
                            <?php if (count($clientLawyers) > 1): ?>
                            <div class="select-wrap contact-lawyer-selectwrap">
                                <select id="contactLawyerSelect" class="contact-lawyer-select" aria-label="<?= __e('client.contact.your_lawyers') ?>">
                                    <?php foreach ($clientLawyers as $entry): ?>
                                    <option value="<?= (int) $entry['id'] ?>"><?= e(full_name($entry)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <?php foreach ($clientLawyers as $i => $entry): ?>
                            <div class="contact-lawyer-detail contact-info-lawyer<?= $i === 0 ? ' is-active' : '' ?>" data-lawyer-id="<?= (int) $entry['id'] ?>">
                                <?php if (count($clientLawyers) === 1): ?><span class="contact-info-value"><?= e(full_name($entry)) ?></span><?php endif; ?>
                                <?php if (!empty($entry['specialization'])): ?><span class="contact-info-meta"><?= e($entry['specialization']) ?></span><?php endif; ?>
                                <?php if (!empty($entry['cases'])): ?>
                                <span class="contact-info-meta"><?= e(implode(', ', array_column($entry['cases'], 'case_number'))) ?></span>
                                <?php elseif (count($clientLawyers) === 1 && empty($entry['cases'])): ?>
                                <span class="contact-info-meta"><?= __e('client.contact.assigned_lawyer') ?></span>
                                <?php endif; ?>
                                <?php if (!empty($entry['email'])): ?><a class="contact-info-meta contact-info-link" href="mailto:<?= e($entry['email']) ?>"><?= e($entry['email']) ?></a><?php endif; ?>
                                <?php if (!empty($entry['phone'])): ?><a class="contact-info-meta contact-info-link" href="tel:<?= e(preg_replace('/\s+/', '', $entry['phone'])) ?>"><?= e($entry['phone']) ?></a><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </dd>
                    </div>
                </div>
                <?php endif; ?>
                <div class="contact-info-item">
                    <span class="contact-info-icon"><?= contact_info_icon_svg('company') ?></span>
                    <div class="contact-info-copy">
                        <dt><?= __e('client.contact.company') ?></dt>
                        <dd><?= e($companyName) ?></dd>
                    </div>
                </div>
                <?php if ($companyAddress): ?>
                <div class="contact-info-item">
                    <span class="contact-info-icon"><?= contact_info_icon_svg('location') ?></span>
                    <div class="contact-info-copy">
                        <dt><?= __e('common.address') ?></dt>
                        <dd><?= nl2br(e($companyAddress)) ?></dd>
                    </div>
                </div>
                <?php endif; ?>
                <div class="contact-info-item">
                    <span class="contact-info-icon"><?= contact_info_icon_svg('hours') ?></span>
                    <div class="contact-info-copy">
                        <dt><?= __e('client.contact.business_hours') ?></dt>
                        <dd><?= nl2br(e($businessHours)) ?></dd>
                    </div>
                </div>
            </dl>
            </div>
        </section>

        <section class="panel contact-compose-card">
            <div class="notify-board-banner">
                <div class="notify-board-banner-copy">
                    <h2><?= __e('client.contact.send_message') ?></h2>
                    <p><?= __e('client.contact.response_time') ?></p>
                </div>
            </div>
            <?php if (!$clientLawyers): ?>
                <div class="contact-compose-body contact-empty"><?= e($noContactMessage) ?></div>
            <?php else: ?>
            <form method="post" class="form-grid entity-inline-form contact-compose-body">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="send">
                <?php if ($requiresCaseForSend): ?>
                <div class="form-group full">
                    <label><?= __e('client.contact.message_to') ?> *</label>
                    <select name="send_target" required>
                        <option value=""><?= __e('client.contact.select_case_ph') ?></option>
                        <?php foreach ($sendTargets as $target): ?>
                        <option value="<?= e($target['key']) ?>"><?= e($target['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field-hint muted"><?= __e('client.contact.select_case_help') ?></p>
                </div>
                <?php elseif ($caseOptions): ?>
                <div class="form-group full">
                    <label><?= __e('form.related_case') ?></label>
                    <select name="case_id">
                        <option value=""><?= __e('common.em_dash') ?></option>
                        <?php foreach ($caseOptions as $c): ?>
                        <option value="<?= (int) $c['case_id'] ?>"><?= e($c['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="form-group full">
                    <label><?= __e('common.subject') ?></label>
                    <input name="subject" required placeholder="<?= __e('client.contact.subject_ph') ?>">
                </div>
                <div class="form-group full">
                    <label><?= __e('common.message') ?></label>
                    <textarea name="body" required rows="6" placeholder="<?= __e('client.contact.message_ph') ?>"></textarea>
                </div>
                <div class="form-actions full">
                    <button class="btn btn-primary" type="submit"><?= __e('client.contact.send_message_btn') ?></button>
                </div>
            </form>
            <?php endif; ?>
        </section>
    </div>

    <section class="panel contact-library">
        <div class="contact-library-head">
            <div>
                <h2><?= __e('client.contact.chat_library') ?></h2>
                <p class="muted"><?= e(__($totalThreads === 1 ? 'client.contact.saved_one' : 'client.contact.saved_many', ['count' => $totalThreads])) ?></p>
            </div>
        </div>
        <?php if (!$clientLawyers): ?>
            <div class="contact-empty"><?= e($noContactMessage) ?></div>
        <?php elseif (!$threads): ?>
            <div class="contact-empty"><?= __e('client.contact.no_messages') ?></div>
        <?php else: ?>
        <div class="contact-thread-list">
            <?php foreach ($threads as $t): ?>
            <article class="contact-thread-item">
                <a class="contact-thread-main" href="contact.php?thread=<?= (int) $t['thread_id'] ?>">
                    <strong><?= e(t_stored($t['subject'])) ?></strong>
                    <p><?= e(t_stored($t['preview_body'])) ?></p>
                    <span class="contact-thread-meta">
                        <?= e($t['lawyer_name']) ?> · <?= e(format_date($t['last_at'], 'M j, Y')) ?> ·
                        <?= e($t['status'] === 'closed' ? __('contact.status.closed') : __('contact.status.open')) ?>
                    </span>
                </a>
                <form method="post" action="contact.php" data-confirm="<?= __e('confirm.delete_message') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="delete">
                    <input type="hidden" name="thread_id" value="<?= (int) $t['thread_id'] ?>">
                    <button class="btn btn-row-delete btn-sm" type="submit"><?= __e('common.delete') ?></button>
                </form>
            </article>
            <?php endforeach; ?>
        </div>
        <?php if ($totalPages > 1 || $totalThreads > 0): ?>
        <div class="case-list-foot contact-library-foot">
            <p class="case-list-footer muted"><?= e(__($totalThreads === 1 ? 'contact.pager.showing_one' : 'contact.pager.showing_many', ['from' => $shownFrom, 'to' => $shownTo, 'total' => $totalThreads])) ?></p>
            <?php if ($totalPages > 1): ?>
            <nav class="case-list-pager" aria-label="<?= __e('contact.pagination.aria') ?>">
                <?php if ($listPage > 1): ?><a class="case-page-btn" href="?page=<?= $listPage - 1 ?>">‹</a><?php else: ?><span class="case-page-btn is-disabled">‹</span><?php endif; ?>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a class="case-page-btn<?= $p === $listPage ? ' is-active' : '' ?>" href="?page=<?= $p ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($listPage < $totalPages): ?><a class="case-page-btn" href="?page=<?= $listPage + 1 ?>">›</a><?php else: ?><span class="case-page-btn is-disabled">›</span><?php endif; ?>
            </nav>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
<?php if (!$threadId && count($clientLawyers) > 1): ?>
<script>
(function () {
    var select = document.getElementById('contactLawyerSelect');
    if (!select) return;
    var details = document.querySelectorAll('.contact-lawyer-detail');
    select.addEventListener('change', function () {
        details.forEach(function (el) {
            el.classList.toggle('is-active', el.getAttribute('data-lawyer-id') === select.value);
        });
    });
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
