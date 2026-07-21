<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$uid = (int) current_user()['id'];
ensure_court_hearing_lawyer_column($pdo);
$action = get('action', 'list');
$id = (int) get('id', 0);
$preCase = (int) get('case_id', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'save') {
        $caseId = (int) post('case_id');
        if (!lawyer_can_access_case($pdo, $uid, $caseId)) {
            flash('error', __('error.case.invalid'));
            redirect('court.php');
        }
        $check = $pdo->prepare('SELECT id, client_id, lawyer_id FROM cases WHERE id=?');
        $check->execute([$caseId]);
        $ownedCase = $check->fetch();
        if (!$ownedCase) {
            flash('error', __('error.case.invalid'));
            redirect('court.php');
        }
        $editId = (int) post('id');
        if ($editId) {
            $owned = $pdo->prepare('SELECT h.id FROM court_hearings h JOIN cases c ON c.id=h.case_id WHERE h.id=? AND ' . lawyer_case_access_sql('c'));
            $owned->execute([$editId, $uid, $uid]);
            if (!$owned->fetch()) {
                flash('error', __('error.case.invalid'));
                redirect('court.php');
            }
            $pdo->prepare('UPDATE court_hearings SET case_id=?, lawyer_id=?, hearing_date=?, court_name=?, court_location=?, judge_name=?, hearing_type=?, outcome=?, notes=?, status=? WHERE id=?')
                ->execute([
                    $caseId, $uid, post('hearing_date'), post('court_name'), post('court_location'),
                    post('judge_name'), post('hearing_type'), post('outcome'), post('notes'), post('status'), $editId,
                ]);
            flash('success', __('flash.hearing.updated'));
        } else {
            $pdo->prepare('INSERT INTO court_hearings (case_id, lawyer_id, hearing_date, court_name, court_location, judge_name, hearing_type, outcome, notes, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    $caseId, $uid, post('hearing_date'), post('court_name'), post('court_location'),
                    post('judge_name'), post('hearing_type'), post('outcome'), post('notes'), post('status'), $uid,
                ]);
            $pdo->prepare('UPDATE cases SET next_hearing_date = DATE(?) WHERE id=?')->execute([post('hearing_date'), $caseId]);
            if (!empty($_FILES['document']['name'])) {
                try {
                    $file = handle_upload($_FILES['document']);
                    if ($file) {
                        $pdo->prepare('INSERT INTO case_documents (case_id, client_id, uploaded_by, title, file_name, file_path, file_type, file_size, category) VALUES (?,?,?,?,?,?,?,?,?)')
                            ->execute([$caseId, $ownedCase['client_id'], $uid, 'Court document - ' . ($file['file_name']), $file['file_name'], $file['file_path'], $file['file_type'], $file['file_size'], 'court']);
                    }
                } catch (Throwable $e) {
                    flash('error', $e->getMessage());
                }
            }
            flash('success', __('flash.hearing.recorded'));
        }
        redirect('court.php');
    }
    if ($fa === 'delete') {
        $hearingId = (int) post('id');
        $owned = $pdo->prepare('SELECT h.id FROM court_hearings h JOIN cases c ON c.id=h.case_id WHERE h.id=? AND ' . lawyer_case_access_sql('c'));
        $owned->execute([$hearingId, $uid, $uid]);
        if ($owned->fetch()) {
            $pdo->prepare('DELETE FROM court_hearings WHERE id=?')->execute([$hearingId]);
            flash('success', __('flash.hearing.deleted'));
        } else {
            flash('error', __('error.case.invalid'));
        }
        redirect('court.php');
    }
}

$cases = $pdo->prepare('SELECT id, case_number, title, lawyer_id FROM cases c WHERE ' . lawyer_case_access_sql('c') . ' ORDER BY created_at DESC');
$cases->execute([$uid, $uid]);
$cases = $cases->fetchAll();

$pageTitle = __('page.court');
$pageSubtitle = __('ai.subtitle.lawyer');
$portal = 'lawyer';
$activeNav = 'court';

if ($action === 'create' || ($action === 'edit' && $id)) {
    $row = ['id' => 0, 'case_id' => $preCase, 'lawyer_id' => $uid, 'hearing_date' => date('Y-m-d\TH:i'), 'court_name' => '', 'court_location' => '', 'judge_name' => '', 'hearing_type' => '', 'outcome' => '', 'notes' => '', 'status' => 'scheduled'];
    if ($action === 'create') {
        $prefillDate = get('date', '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefillDate)) {
            $row['hearing_date'] = $prefillDate . 'T09:00';
        }
    }
    if ($id) {
        $stmt = $pdo->prepare('SELECT h.* FROM court_hearings h JOIN cases c ON c.id=h.case_id WHERE h.id=? AND ' . lawyer_case_access_sql('c'));
        $stmt->execute([$id, $uid, $uid]);
        $row = $stmt->fetch() ?: $row;
        if (!(int) ($row['id'] ?? 0)) {
            flash('error', __('error.case.invalid'));
            redirect('court.php');
        }
        $row['hearing_date'] = date('Y-m-d\TH:i', strtotime($row['hearing_date']));
    }
    require __DIR__ . '/../includes/header.php';
    $isEdit = (bool) $id;
    $formCancelUrl = 'court.php';
    $hearingFormConfig = [
        'showFileUpload' => !$isEdit,
        'showLawyer' => false,
        'lockLawyerId' => $uid,
    ];
    require __DIR__ . '/../includes/hearing-form.php';
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
    WHERE (h.lawyer_id = ? OR " . lawyer_case_access_sql('c') . ")
    ORDER BY h.hearing_date DESC
");
$hearings->execute([$uid, $uid, $uid]);
$hearings = $hearings->fetchAll();

$totalCount = count($hearings);
$listSubtitle = $totalCount === 1
    ? __('court.total_one', ['count' => $totalCount])
    : __('court.total_many', ['count' => $totalCount]);
$hearingTones = hearing_statuses();
$calendarItems = array_map(static fn(array $r): array => calendar_item_from_hearing($r, ['editUrl' => '?action=edit&id=' . (int) $r['id']]), $hearings);
$calCtx = build_entity_calendar_context($calendarItems, [
    'entity' => 'hearing',
    'showCreate' => true,
    'createUrl' => '?action=create',
    'createLabel' => __('court.add'),
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
            <a class="btn btn-row-edit btn-sm" href="?action=edit&id=<?= (int) $h['id'] ?>"><?= __e('common.edit') ?></a>
            <form method="post" data-confirm="<?= __e('court.confirm_delete') ?>"><?= csrf_field() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><button class="btn btn-row-delete btn-sm" type="submit"><?= __e('common.delete') ?></button></form>
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
$listHeroActionHtml = '<a class="btn btn-primary btn-sm" href="?action=create">' . __e('court.add') . '</a>';
require __DIR__ . '/../includes/entity-list-panel.php';
require __DIR__ . '/../includes/footer.php';
