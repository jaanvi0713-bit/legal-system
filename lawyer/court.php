<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$uid = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'save') {
        $caseId = (int) post('case_id');
        $check = $pdo->prepare('SELECT id FROM cases WHERE id=? AND lawyer_id=?');
        $check->execute([$caseId, $uid]);
        if (!$check->fetch()) { flash('error', __('error.case.invalid')); redirect('court.php'); }
        $editId = (int) post('id');
        if ($editId) {
            $pdo->prepare('UPDATE court_hearings SET hearing_date=?, court_name=?, court_location=?, outcome=?, notes=?, status=? WHERE id=? AND case_id=?')
                ->execute([post('hearing_date'), post('court_name'), post('court_location'), post('outcome'), post('notes'), post('status'), $editId, $caseId]);
        } else {
            $pdo->prepare('INSERT INTO court_hearings (case_id, hearing_date, court_name, court_location, hearing_type, outcome, notes, status, created_by) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$caseId, post('hearing_date'), post('court_name'), post('court_location'), post('hearing_type'), post('outcome'), post('notes'), post('status'), $uid]);
        }
        if (!empty($_FILES['document']['name'])) {
            try {
                $file = handle_upload($_FILES['document']);
                if ($file) {
                    $client = $pdo->prepare('SELECT client_id FROM cases WHERE id=?');
                    $client->execute([$caseId]);
                    $clientId = $client->fetchColumn();
                    $pdo->prepare('INSERT INTO case_documents (case_id, client_id, uploaded_by, title, file_name, file_path, file_type, file_size, category) VALUES (?,?,?,?,?,?,?,?,?)')
                        ->execute([$caseId, $clientId, $uid, 'Court document - ' . ($file['file_name']), $file['file_name'], $file['file_path'], $file['file_type'], $file['file_size'], 'court']);
                }
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
        }
        flash('success', __('flash.hearing.recorded'));
        redirect('court.php');
    }
    if ($fa === 'delete') {
        $hearingId = (int) post('id');
        $owned = $pdo->prepare('SELECT h.id FROM court_hearings h JOIN cases c ON c.id=h.case_id WHERE h.id=? AND c.lawyer_id=?');
        $owned->execute([$hearingId, $uid]);
        if ($owned->fetch()) {
            $pdo->prepare('DELETE FROM court_hearings WHERE id=?')->execute([$hearingId]);
            flash('success', __('flash.hearing.deleted'));
        } else {
            flash('error', __('error.case.invalid'));
        }
        redirect('court.php');
    }
}

$hearings = $pdo->prepare("
    SELECT h.*, c.case_number, c.title, c.id AS case_id,
        CONCAT(cl.first_name,' ',cl.last_name) AS client_name
    FROM court_hearings h
    JOIN cases c ON c.id = h.case_id
    JOIN users cl ON cl.id = c.client_id
    WHERE c.lawyer_id = ?
    ORDER BY h.hearing_date DESC
");
$hearings->execute([$uid]);
$hearings = $hearings->fetchAll();
$cases = $pdo->prepare('SELECT id, case_number, title FROM cases WHERE lawyer_id=?');
$cases->execute([$uid]);
$cases = $cases->fetchAll();

$totalCount = count($hearings);
$listSubtitle = $totalCount === 1
    ? __('court.total_one', ['count' => $totalCount])
    : __('court.total_many', ['count' => $totalCount]);
$hearingTones = hearing_statuses();
$calendarItems = array_map(static fn(array $r): array => calendar_item_from_hearing($r, ['editUrl' => '']), $hearings);
$calCtx = build_entity_calendar_context($calendarItems, [
    'entity' => 'hearing',
    'showCreate' => false,
    'createUrl' => '#courtScheduleForm',
    'scheduleLabel' => __('court.add'),
]);
extract($calCtx, EXTR_OVERWRITE);

$prefillHearingAt = '';
$prefillDate = get('date', '');
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefillDate)) {
    $prefillHearingAt = $prefillDate . 'T09:00';
}

$pageTitle = __('page.court');
$pageSubtitle = __('ai.subtitle.lawyer');
$portal = 'lawyer';
$activeNav = 'court';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/calendar-panel.php';
require __DIR__ . '/../includes/calendar-view-modal.php';
?>
<div class="panel" id="courtScheduleForm">
    <h2><?= __e('court.record') ?></h2>
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="form_action" value="save">
        <div class="form-group"><label><?= __e('common.case') ?></label><select name="case_id" required><?php foreach ($cases as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['case_number'].' — '.$c['title']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label><?= __e('form.hearing_date') ?></label><input type="datetime-local" name="hearing_date" required value="<?= e($prefillHearingAt) ?>"></div>
        <div class="form-group"><label><?= __e('common.court') ?></label><input name="court_name" required></div>
        <div class="form-group"><label><?= __e('common.location') ?></label><input name="court_location"></div>
        <div class="form-group"><label><?= __e('common.type') ?></label><input name="hearing_type"></div>
        <div class="form-group"><label><?= __e('common.status') ?></label><select name="status"><?php foreach (hearing_statuses() as $s): ?><option value="<?= $s ?>"><?= e(translate_status($s)) ?></option><?php endforeach; ?></select></div>
        <div class="form-group full"><label><?= __e('common.outcome') ?></label><textarea name="outcome"></textarea></div>
        <div class="form-group full"><label><?= __e('form.court_notes') ?></label><textarea name="notes"></textarea></div>
        <div class="form-group full"><label><?= __e('court.upload_doc') ?></label><input type="file" name="document"></div>
        <div class="form-actions full"><button class="btn btn-primary" type="submit"><?= __e('common.save') ?></button></div>
    </form>
</div>
<?php
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
    <td>
        <div class="row-actions">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><button class="btn btn-row-delete btn-sm" type="submit" data-confirm="<?= __e('court.confirm_delete') ?>"><?= __e('common.delete') ?></button></form>
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
