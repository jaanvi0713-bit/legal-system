<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$user = current_user();
$uid = (int) $user['id'];
$base = app_config('url');
$portalBase = $base . '/lawyer';

handle_notification_open($pdo, $user, $portalBase, $base, $portalBase . '/notifications.php');
handle_notification_post($pdo, $user, 'notifications.php');

$perPage = 10;
$listPage = max(1, (int) get('page', 1));
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=?');
$countStmt->execute([$uid]);
$totalCount = (int) $countStmt->fetchColumn();
$unreadCount = unread_notifications($pdo, $uid);
$totalPages = max(1, (int) ceil($totalCount / $perPage));
if ($listPage > $totalPages) {
    $listPage = $totalPages;
}
$offset = ($listPage - 1) * $perPage;
$listStmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT ? OFFSET ?');
$listStmt->bindValue(1, $uid, PDO::PARAM_INT);
$listStmt->bindValue(2, $perPage, PDO::PARAM_INT);
$listStmt->bindValue(3, $offset, PDO::PARAM_INT);
$listStmt->execute();
$rows = $listStmt->fetchAll();
$shownFrom = $totalCount === 0 ? 0 : $offset + 1;
$shownTo = min($offset + count($rows), $totalCount);

$pageTitle = __('page.notifications');
$pageSubtitle = $unreadCount
    ? __('notifications.subtitle_unread', ['count' => $unreadCount])
    : __('notifications.history_help');
$portal = 'lawyer';
$activeNav = 'notifications';
$bodyClass = 'page-notifications';
require __DIR__ . '/../includes/header.php';
?>
<div class="notify-page">
    <?php
    $notifyBoardItems = $rows;
    $notifyBoardTitle = __('notifications.tab.history');
    $notifyBoardTotal = $totalCount;
    $notifyBoardUnread = $unreadCount;
    $notifyBoardActionUnread = $unreadCount;
    $notifyBoardPostUrl = 'notifications.php';
    $notifyBoardMode = 'inbox';
    $notifyBoardPreferencesUrl = null;
    $notifyBoardId = 'notifyHistoryBoard';
    $notifyBoardPagerPage = $listPage;
    $notifyBoardPagerTotalPages = $totalPages;
    $notifyBoardPagerShownFrom = $shownFrom;
    $notifyBoardPagerShownTo = $shownTo;
    require __DIR__ . '/../includes/notifications-alerts-board.php';
    ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
