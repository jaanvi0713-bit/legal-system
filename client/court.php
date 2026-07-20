<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['client']);
$pdo = db();
ensure_court_hearing_lawyer_column($pdo);
$uid = (int) current_user()['id'];
$action = get('action', 'list');
$id = (int) get('id', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    flash('error', __('flash.message.denied'));
    redirect('court.php');
}

$pageTitle = __('page.court');
$pageSubtitle = __('ai.subtitle.client');
$portal = 'client';
$activeNav = 'court';

if ($action === 'create') {
    flash('error', __('flash.message.denied'));
    redirect('court.php');
}

if ($action === 'view' && $id) {
    $stmt = $pdo->prepare("
        SELECT h.*, c.case_number, c.title, c.id AS case_id,
            CONCAT(lw.first_name,' ',lw.last_name) AS lawyer_name
        FROM court_hearings h
        JOIN cases c ON c.id = h.case_id
        LEFT JOIN users lw ON lw.id = COALESCE(h.lawyer_id, c.lawyer_id)
        WHERE h.id = ? AND c.client_id = ?
    ");
    $stmt->execute([$id, $uid]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('error', __('flash.message.denied'));
        redirect('court.php');
    }
    require __DIR__ . '/../includes/header.php';
    $viewBackUrl = 'court.php';
    $viewRequestApptUrl = 'appointments.php';
    require __DIR__ . '/../includes/hearing-view.php';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$hearings = $pdo->prepare("
    SELECT h.*, c.case_number, c.title, c.id AS case_id,
        CONCAT(cl.first_name,' ',cl.last_name) AS client_name,
        CONCAT(lw.first_name,' ',lw.last_name) AS lawyer_name
    FROM court_hearings h
    JOIN cases c ON c.id = h.case_id
    JOIN users cl ON cl.id = c.client_id
    LEFT JOIN users lw ON lw.id = COALESCE(h.lawyer_id, c.lawyer_id)
    WHERE c.client_id = ?
    ORDER BY h.hearing_date DESC
");
$hearings->execute([$uid]);
$hearings = $hearings->fetchAll();

$totalCount = count($hearings);
$listSubtitle = $totalCount === 1
    ? __('court.total_one', ['count' => $totalCount])
    : __('court.total_many', ['count' => $totalCount]);
$hearingTones = hearing_statuses();
$calendarItems = array_map(static fn(array $r): array => calendar_item_from_hearing($r, ['editUrl' => '']), $hearings);
$calCtx = build_entity_calendar_context($calendarItems, [
    'entity' => 'hearing',
    'showCreate' => false,
    'createUrl' => '',
    'createLabel' => __('client.court.add'),
    'scheduleLabel' => __('client.court.add'),
]);
extract($calCtx, EXTR_OVERWRITE);

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/calendar-panel.php';
require __DIR__ . '/../includes/calendar-view-modal.php';

ob_start();
foreach ($hearings as $h):
    $status = strtolower((string) $h['status']);
    $caseLabel = trim(($h['case_number'] ? $h['case_number'] . ' — ' : '') . ($h['title'] ?? ''));
    $ts = strtotime((string) $h['hearing_date']);
    $dateLabel = $ts ? date('M j, Y', $ts) : __('common.em_dash');
    $timeLabel = $ts ? date('g:i A', $ts) : '';
    $searchBlob = strtolower(trim(implode(' ', [
        $caseLabel,
        $h['client_name'] ?? '',
        $h['lawyer_name'] ?? '',
        $h['court_name'] ?? '',
        $h['court_location'] ?? '',
        $h['hearing_type'] ?? '',
        $status,
    ])));
?>
<tr data-list-status="<?= e($status) ?>" data-list-search="<?= e($searchBlob) ?>">
    <td><div class="appt-list-when"><strong><?= e($dateLabel) ?></strong><span><?= e($timeLabel) ?></span></div></td>
    <td>
        <strong class="appt-list-title"><?= e(t_content($h['court_name'])) ?></strong>
        <div class="muted"><?= e($h['hearing_type'] ? t_content($h['hearing_type']) : __('common.em_dash')) ?></div>
    </td>
    <td><?= e($h['lawyer_name'] ?: __('common.em_dash')) ?></td>
    <td><?= e($h['client_name'] ?: __('common.em_dash')) ?></td>
    <td class="appt-list-case"><strong><?= e($h['case_number']) ?></strong><div class="muted"><?= e($h['title']) ?></div></td>
    <td><?= hearing_list_status_badge($status) ?></td>
    <td>
        <div class="row-actions">
            <a class="btn btn-row-open btn-sm" href="?action=view&id=<?= (int) $h['id'] ?>"><?= __e('common.view') ?></a>
        </div>
    </td>
</tr>
<?php endforeach;
$listRowsHtml = ob_get_clean();
$listPanelId = 'courtListPanel';
$listSearchId = 'courtListSearch';
$listStatusId = 'courtListStatus';
$listTableId = 'courtListTable';
$listFooterId = 'courtListFooter';
$listTotalMetaId = 'courtListTotalMeta';
$listPanelClass = 'court-list-panel';
$listTitle = __('court.list');
$listSearchPlaceholder = __('court.search_placeholder');
$listAllStatuses = __('court.all_statuses');
$listStatuses = $hearingTones;
$listStatusI18nPrefix = 'court.tone.';
$listColumns = [
    __('court.col.datetime'),
    __('court.col.court_type'),
    __('common.lawyer'),
    __('common.client'),
    __('common.case'),
    __('common.status'),
    __('common.actions'),
];
$listShowingTpl = __('court.showing', ['shown' => $totalCount, 'total' => $totalCount]);
$listTotalOneTpl = __('court.total_one', ['count' => ':count']);
$listTotalManyTpl = __('court.total_many', ['count' => ':count']);
$listHeroActionHtml = '';
require __DIR__ . '/../includes/entity-list-panel.php';
require __DIR__ . '/../includes/footer.php';
