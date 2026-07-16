<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['client']);
$pdo = db();
$uid = (int) current_user()['id'];

$hearings = $pdo->prepare("
    SELECT h.*, c.case_number, c.title, c.id AS case_id,
        CONCAT(cl.first_name,' ',cl.last_name) AS client_name
    FROM court_hearings h
    JOIN cases c ON c.id = h.case_id
    JOIN users cl ON cl.id = c.client_id
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
]);
extract($calCtx, EXTR_OVERWRITE);

$pageTitle = __('page.court');
$pageSubtitle = __('ai.subtitle.client');
$portal = 'client';
$activeNav = 'court';
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
    <td><?= e($h['client_name'] ?: __('common.em_dash')) ?></td>
    <td class="appt-list-case"><strong><?= e($h['case_number']) ?></strong><div class="muted"><?= e($h['title']) ?></div></td>
    <td><?= hearing_list_status_badge($status) ?></td>
    <td></td>
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
    __('common.client'),
    __('common.case'),
    __('common.status'),
    __('common.actions'),
];
$listShowingTpl = __('court.showing', ['shown' => $totalCount, 'total' => $totalCount]);
$listTotalOneTpl = __('court.total_one', ['count' => ':count']);
$listTotalManyTpl = __('court.total_many', ['count' => ':count']);
require __DIR__ . '/../includes/entity-list-panel.php';
require __DIR__ . '/../includes/footer.php';
