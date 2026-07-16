<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'staff']);
$pdo = db();
$action = get('action', 'list');
$id = (int) get('id', 0);
$preCase = (int) get('case_id', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'save') {
        $editId = (int) post('id');
        $vals = [
            (int) post('case_id'), post('hearing_date'), post('court_name'), post('court_location'),
            post('judge_name'), post('hearing_type'), post('outcome'), post('notes'), post('status'),
        ];
        if ($editId) {
            $vals[] = $editId;
            $pdo->prepare('UPDATE court_hearings SET case_id=?, hearing_date=?, court_name=?, court_location=?, judge_name=?, hearing_type=?, outcome=?, notes=?, status=? WHERE id=?')->execute($vals);
            flash('success', 'Hearing updated.');
        } else {
            $vals[] = current_user()['id'];
            $pdo->prepare('INSERT INTO court_hearings (case_id, hearing_date, court_name, court_location, judge_name, hearing_type, outcome, notes, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute($vals);
            $pdo->prepare('UPDATE cases SET next_hearing_date = DATE(?) WHERE id=?')->execute([post('hearing_date'), (int) post('case_id')]);
            flash('success', 'Hearing recorded.');
        }
        redirect('court.php');
    }
    if ($fa === 'delete') {
        $pdo->prepare('DELETE FROM court_hearings WHERE id=?')->execute([(int) post('id')]);
        flash('success', __('flash.hearing.deleted'));
        redirect('court.php');
    }
}

$cases = $pdo->query('SELECT id, case_number, title FROM cases ORDER BY created_at DESC')->fetchAll();
$pageTitle = __('page.court');
$pageSubtitle = 'Hearings, locations, outcomes, and progress';
$portal = 'admin';
$activeNav = 'court';

if ($action === 'create' || ($action === 'edit' && $id)) {
    $row = ['id'=>0,'case_id'=>$preCase,'hearing_date'=>date('Y-m-d\TH:i'),'court_name'=>'','court_location'=>'','judge_name'=>'','hearing_type'=>'','outcome'=>'','notes'=>'','status'=>'scheduled'];
    if ($action === 'create') {
        $prefillDate = get('date', '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefillDate)) {
            $row['hearing_date'] = $prefillDate . 'T09:00';
        }
    }
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM court_hearings WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: $row;
        $row['hearing_date'] = date('Y-m-d\TH:i', strtotime($row['hearing_date']));
    }
    require __DIR__ . '/../includes/header.php';
    $isEdit = (bool) $id;
    ?>
    <div class="entity-form-wrap">
    <div class="entity-form panel">
        <div class="entity-form-hero">
            <div>
                <p class="entity-form-eyebrow"><?= $isEdit ? 'Court record' : 'Court tracking' ?></p>
                <h2><?= $isEdit ? 'Edit hearing' : 'Add hearing' ?></h2>
                <p class="muted"><?= $isEdit ? 'Update hearing schedule, court details, and outcome.' : 'Record an upcoming or completed court hearing.' ?></p>
            </div>
            <p class="entity-form-required-note"><span class="req">*</span> Required fields</p>
        </div>
        <form method="post">
            <div class="entity-form-body">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="save">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3>Hearing details</h3>
                        <p>Case link, date, and status.</p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="case_id">Case <span class="req">*</span></label>
                            <select id="case_id" name="case_id" required>
                                <?php foreach ($cases as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= (int)$row['case_id']===(int)$c['id']?'selected':'' ?>><?= e($c['case_number'].' — '.$c['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="hearing_date">Hearing date <span class="req">*</span></label>
                            <input id="hearing_date" type="datetime-local" name="hearing_date" required value="<?= e($row['hearing_date']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="status">Status <span class="req">*</span></label>
                            <select id="status" name="status" required>
                                <?php foreach (hearing_statuses() as $s): ?>
                                    <option value="<?= $s ?>" <?= $row['status']===$s?'selected':'' ?>><?= e(translate_status($s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="hearing_type">Hearing type</label>
                            <input id="hearing_type" name="hearing_type" value="<?= e($row['hearing_type']) ?>" placeholder="e.g. Preliminary">
                        </div>
                    </div>
                </section>

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3>Court information</h3>
                        <p>Venue, judge, outcome, and notes.</p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="court_name">Court name <span class="req">*</span></label>
                            <input id="court_name" name="court_name" required value="<?= e($row['court_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="court_location">Location</label>
                            <input id="court_location" name="court_location" value="<?= e($row['court_location']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="judge_name">Judge</label>
                            <input id="judge_name" name="judge_name" value="<?= e($row['judge_name']) ?>">
                        </div>
                        <div class="form-group full">
                            <label for="outcome">Outcome</label>
                            <textarea id="outcome" name="outcome" rows="2"><?= e($row['outcome']) ?></textarea>
                        </div>
                        <div class="form-group full">
                            <label for="notes">Court notes</label>
                            <textarea id="notes" name="notes" rows="2"><?= e($row['notes']) ?></textarea>
                        </div>
                    </div>
                </section>
            </div>
            <div class="entity-form-footer">
                <a class="btn btn-secondary" href="court.php">Back to court</a>
                <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save changes' : 'Add hearing' ?></button>
            </div>
        </form>
    </div>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

$courtListSql = "
    SELECT h.*, c.case_number, c.title, c.id AS case_id,
        CONCAT(cl.first_name,' ',cl.last_name) AS client_name
    FROM court_hearings h
    JOIN cases c ON c.id = h.case_id
    JOIN users cl ON cl.id = c.client_id
";

$rows = $pdo->query($courtListSql . ' ORDER BY h.hearing_date DESC')->fetchAll();
$totalCount = count($rows);
$listSubtitle = $totalCount === 1
    ? __('court.total_one', ['count' => $totalCount])
    : __('court.total_many', ['count' => $totalCount]);
$hearingTones = hearing_statuses();
$calendarItems = array_map(static fn(array $r): array => calendar_item_from_hearing($r), $rows);
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
foreach ($rows as $r):
    $status = strtolower((string) $r['status']);
    $caseLabel = trim(($r['case_number'] ? $r['case_number'] . ' — ' : '') . ($r['title'] ?? ''));
    $ts = strtotime((string) $r['hearing_date']);
    $dateLabel = $ts ? date('M j, Y', $ts) : __('common.em_dash');
    $timeLabel = $ts ? date('g:i A', $ts) : '';
    $searchBlob = strtolower(trim(implode(' ', [
        $caseLabel,
        $r['client_name'] ?? '',
        $r['court_name'] ?? '',
        $r['court_location'] ?? '',
        $r['hearing_type'] ?? '',
        $status,
    ])));
?>
<tr data-list-status="<?= e($status) ?>" data-list-search="<?= e($searchBlob) ?>">
    <td><div class="appt-list-when"><strong><?= e($dateLabel) ?></strong><span><?= e($timeLabel) ?></span></div></td>
    <td>
        <strong class="appt-list-title"><?= e(t_content($r['court_name'])) ?></strong>
        <div class="muted"><?= e($r['hearing_type'] ? t_content($r['hearing_type']) : __('common.em_dash')) ?></div>
    </td>
    <td><?= e($r['client_name'] ?: __('common.em_dash')) ?></td>
    <td class="appt-list-case"><a class="case-num-link" href="cases.php?action=view&id=<?= (int)$r['case_id'] ?>"><?= e($r['case_number']) ?></a><div class="muted"><?= e($r['title']) ?></div></td>
    <td><?= hearing_list_status_badge($status) ?></td>
    <td>
        <div class="row-actions">
            <a class="btn btn-row-edit btn-sm" href="?action=edit&id=<?= (int)$r['id'] ?>"><?= __e('common.edit') ?></a>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-row-delete btn-sm" type="submit" data-confirm="<?= __e('court.confirm_delete') ?>"><?= __e('common.delete') ?></button></form>
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
