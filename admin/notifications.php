<?php
require_once __DIR__ . '/../includes/auth.php';
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
        $target = post('user_id');
        $title = post('title');
        $message = post('message');
        $type = post('type') ?: 'info';
        if ($target === 'all') {
            $ids = $pdo->query('SELECT id FROM users WHERE is_active=1')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $uid) {
                create_notification($pdo, (int)$uid, $title, $message, $type, null, $user['id']);
            }
            flash('success', __('flash.notification.sent'));
        } else {
            create_notification($pdo, (int)$target, $title, $message, $type, null, $user['id']);
            flash('success', __('flash.notification.sent'));
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
                <h2><?= __e('notifications.send') ?></h2>
                <p><?= __e('notifications.send_help') ?></p>
            </div>
        </div>
        <form method="post" class="form-grid notify-form entity-inline-form notify-compose-body" id="notifyComposeForm">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="send">
            <div class="form-group full">
                <label><?= __e('common.recipient') ?></label>
                <?php
                $recipientPickerId = 'notifyRecipientPicker';
                $recipientPickerLawyers = $recipientLawyers;
                $recipientPickerClients = $recipientClients;
                $recipientPickerUsers = $recipientUsers;
                require __DIR__ . '/../includes/recipient-picker.php';
                ?>
            </div>
            <div class="entity-field-row entity-field-row--2">
                <div class="form-group">
                    <label><?= __e('common.type') ?></label>
                    <select name="type" id="notifyComposeType">
                        <?php foreach (['info','success','case','appointment','payment','document','reminder'] as $t): ?>
                            <option value="<?= $t ?>"><?= e(__('notification.type.' . $t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <div class="notify-field-label-row">
                        <label for="notifyComposeTitle"><?= __e('notifications.title_field') ?></label>
                        <div class="notify-field-tools">
                            <button type="button" class="notify-field-icon-btn" data-copy-for="notifyComposeTitle" title="<?= __e('common.copy') ?>" aria-label="<?= __e('common.copy') ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                            </button>
                            <button type="button" class="notify-field-icon-btn" data-edit-for="notifyComposeTitle" title="<?= __e('common.edit') ?>" aria-label="<?= __e('common.edit') ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                            </button>
                        </div>
                    </div>
                    <input name="title" id="notifyComposeTitle" required placeholder="<?= __e('notifications.title_ph') ?>">
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
                <button class="btn btn-primary" type="submit"><?= __e('common.send') ?></button>
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
