<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/broadcasts.php';
require_role(['admin', 'staff']);
$pdo = db();
$user = current_user();
$base = app_config('url');
$portalBase = $base . '/admin';

handle_notification_open($pdo, $user, $portalBase, $base, $portalBase . '/notifications.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'send') {
        $target = trim((string) post('user_id', ''));
        $title = trim((string) post('title'));
        $message = trim((string) post('message'));
        $type = post('type') ?: 'info';
        $sendEmail = post('send_email') === '1';

        if ($title === '' || $message === '') {
            flash('error', __('flash.notification.required'));
            redirect('notifications.php#send');
        }

        if (in_array($target, ['all', 'lawyers', 'clients', 'staff'], true)) {
            $result = send_broadcast($pdo, $title, $message, $type, $target, 0, $sendEmail, (int) $user['id']);
            flash('success', __('flash.broadcast.sent', ['count' => $result['count']]));
        } elseif (ctype_digit($target)) {
            $result = send_broadcast($pdo, $title, $message, $type, 'user', (int) $target, $sendEmail, (int) $user['id']);
            flash('success', __('flash.notification.sent'));
        } else {
            flash('error', __('flash.notification.recipient_required'));
            redirect('notifications.php#send');
        }
        redirect('notifications.php');
    }
}

handle_notification_post($pdo, $user, 'notifications.php', true);

$users = $pdo->query("SELECT id, first_name, last_name, role FROM users WHERE is_active=1 ORDER BY first_name, last_name")->fetchAll();
$recipientLawyers = [];
$recipientClients = [];
$recipientUsers = [];
foreach ($users as $u) {
    if ($u['role'] === 'lawyer') {
        $recipientLawyers[] = $u;
    } elseif ($u['role'] === 'client') {
        $recipientClients[] = $u;
    } else {
        $recipientUsers[] = $u;
    }
}

$broadcasts = broadcasts_recent($pdo, 12);

$perPage = 10;
$listPage = max(1, (int) get('page', 1));
$totalCount = (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn();
$listUnread = (int) $pdo->query('SELECT COUNT(*) FROM notifications WHERE is_read=0')->fetchColumn();
$totalPages = max(1, (int) ceil($totalCount / $perPage));
if ($listPage > $totalPages) {
    $listPage = $totalPages;
}
$offset = ($listPage - 1) * $perPage;
$listStmt = $pdo->prepare('SELECT n.*, CONCAT(u.first_name," ",u.last_name) AS recipient FROM notifications n JOIN users u ON u.id=n.user_id ORDER BY n.created_at DESC LIMIT ? OFFSET ?');
$listStmt->bindValue(1, $perPage, PDO::PARAM_INT);
$listStmt->bindValue(2, $offset, PDO::PARAM_INT);
$listStmt->execute();
$rows = $listStmt->fetchAll();
$shownFrom = $totalCount === 0 ? 0 : $offset + 1;
$shownTo = min($offset + count($rows), $totalCount);
$userUnread = unread_notifications($pdo, (int) $user['id']);

$pageTitle = __('page.notifications');
$pageSubtitle = $userUnread
    ? __('notifications.subtitle_unread', ['count' => $userUnread])
    : __('notifications.history_help');
$portal = 'admin';
$activeNav = 'notifications';
$bodyClass = 'page-notifications';
require __DIR__ . '/../includes/header.php';
?>
<div class="notify-page">
    <section class="panel notify-compose" id="send">
        <div class="notify-board-banner">
            <div class="notify-board-banner-copy">
                <h2><?= __e('notifications.broadcast_title') ?></h2>
                <p><?= __e('notifications.broadcast_help') ?></p>
            </div>
        </div>
        <form method="post" class="form-grid notify-form entity-inline-form notify-compose-body" id="notifyComposeForm">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="send">
            <div class="entity-field-row entity-field-row--2">
                <div class="form-group">
                    <label><?= __e('common.recipient') ?></label>
                    <?php
                    $recipientPickerId = 'notifyRecipientPicker';
                    $recipientPickerLawyers = $recipientLawyers;
                    $recipientPickerClients = $recipientClients;
                    $recipientPickerUsers = $recipientUsers;
                    require __DIR__ . '/../includes/recipient-picker.php';
                    ?>
                </div>
                <div class="form-group">
                    <label for="notifyComposeTitle"><?= __e('notifications.title_field') ?></label>
                    <input name="title" id="notifyComposeTitle" required placeholder="<?= __e('notifications.title_ph') ?>">
                </div>
            </div>
            <div class="entity-field-row entity-field-row--2">
                <div class="form-group">
                    <label for="notifyComposeType"><?= __e('common.type') ?></label>
                    <select name="type" id="notifyComposeType">
                        <?php foreach (['info','success','case','appointment','payment','document','reminder'] as $t): ?>
                            <option value="<?= $t ?>"><?= e(__('notification.type.' . $t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group notify-compose-checkbox">
                    <span class="notify-compose-checkbox-label" aria-hidden="true">&nbsp;</span>
                    <label class="notify-inline-check" for="notifyComposeSendEmail">
                        <input type="checkbox" name="send_email" id="notifyComposeSendEmail" value="1">
                        <span><?= __e('notifications.send_email_also') ?></span>
                    </label>
                </div>
            </div>
            <div class="form-group full">
                <div class="notify-field-label-row">
                    <label for="notifyComposeMessage"><?= __e('common.message') ?></label>
                    <div class="notify-field-tools">
                        <button type="button" class="notify-field-icon-btn" data-copy-for="notifyComposeMessage" title="<?= __e('common.copy') ?>" aria-label="<?= __e('common.copy') ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </button>
                        <button type="button" class="notify-field-icon-btn" data-edit-for="notifyComposeMessage" title="<?= __e('common.edit') ?>" aria-label="<?= __e('common.edit') ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                        </button>
                    </div>
                </div>
                <textarea name="message" id="notifyComposeMessage" required rows="5" placeholder="<?= __e('notifications.message_ph') ?>"></textarea>
            </div>
            <div class="form-actions full">
                <button class="btn btn-primary" type="submit"><?= __e('notifications.broadcast_send') ?></button>
            </div>
        </form>
    </section>
    <script>
    (function () {
      var copiedLabel = <?= json_encode(__('contact.copied')) ?>;
      var copyPromptLabel = <?= json_encode(__('contact.copy_prompt')) ?>;

      function focusField(field) {
        if (!field) return;
        field.focus();
        var len = field.value.length;
        if (typeof field.setSelectionRange === 'function') {
          field.setSelectionRange(len, len);
        }
      }

      document.querySelectorAll('[data-copy-for]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
          var field = document.getElementById(btn.getAttribute('data-copy-for'));
          if (!field) return;
          var text = (field.value || '').trim();
          if (!text) return;
          try {
            await navigator.clipboard.writeText(text);
            btn.classList.add('is-copied');
            var prevTitle = btn.getAttribute('title');
            btn.setAttribute('title', copiedLabel);
            setTimeout(function () {
              btn.classList.remove('is-copied');
              btn.setAttribute('title', prevTitle || '');
            }, 1400);
          } catch (e) {
            window.prompt(copyPromptLabel, text);
          }
        });
      });

      document.querySelectorAll('[data-edit-for]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          focusField(document.getElementById(btn.getAttribute('data-edit-for')));
        });
      });
    })();
    </script>

    <?php if ($broadcasts): ?>
    <section class="panel notify-broadcast-history">
        <div class="panel-header">
            <h2><?= __e('notifications.broadcast_history') ?></h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th><?= __e('common.date') ?></th>
                        <th><?= __e('notifications.title_field') ?></th>
                        <th><?= __e('common.recipient') ?></th>
                        <th><?= __e('notifications.broadcast_recipients') ?></th>
                        <th><?= __e('common.email') ?></th>
                        <th><?= __e('common.sent_by') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($broadcasts as $bc): ?>
                    <tr>
                        <td><?= e(format_date($bc['created_at'], 'M j, Y g:i A')) ?></td>
                        <td>
                            <strong><?= e($bc['title']) ?></strong>
                            <div class="muted"><?= e(mb_strimwidth(strip_tags((string) $bc['message']), 0, 90, '…')) ?></div>
                        </td>
                        <td><?= e(broadcast_audience_label((string) $bc['audience'])) ?></td>
                        <td><?= (int) $bc['recipient_count'] ?></td>
                        <td><?= (int) $bc['email_sent'] ? __e('common.yes') : __e('common.no') ?></td>
                        <td><?= e($bc['sender_name'] ?: __('common.system')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <?php
    $notifyBoardItems = $rows;
    $notifyBoardTitle = __('notifications.tab.history');
    $notifyBoardTotal = $totalCount;
    $notifyBoardUnread = $listUnread;
    $notifyBoardActionUnread = $userUnread;
    $notifyBoardPostUrl = 'notifications.php';
    $notifyBoardMode = 'history';
    $notifyBoardPreferencesUrl = 'settings.php?tab=notifications';
    $notifyBoardShowMarkAll = true;
    $notifyBoardAllowDeleteAny = true;
    $notifyBoardId = 'notifyHistoryBoard';
    $notifyBoardPagerPage = $listPage;
    $notifyBoardPagerTotalPages = $totalPages;
    $notifyBoardPagerShownFrom = $shownFrom;
    $notifyBoardPagerShownTo = $shownTo;
    $notifyBoardAllowEdit = false;
    $notifyBoardReturnPage = $listPage;
    require __DIR__ . '/../includes/notifications-alerts-board.php';
    ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
