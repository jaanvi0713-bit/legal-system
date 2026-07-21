<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$uid = (int) current_user()['id'];
$id = (int) get('id', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    $caseId = (int) post('case_id');
    if (!lawyer_can_access_case($pdo, $uid, $caseId)) { flash('error', __('error.case.not_assigned')); redirect('cases.php'); }
    $own = $pdo->prepare('SELECT id, client_id FROM cases WHERE id=?');
    $own->execute([$caseId]);
    $case = $own->fetch();
    if (!$case) { flash('error', __('error.case.not_assigned')); redirect('cases.php'); }

    if ($fa === 'status') {
        $pdo->prepare('UPDATE cases SET status=? WHERE id=?')->execute([post('status'), $caseId]);
        create_notification($pdo, (int)$case['client_id'], 'notify.case_update', 'notify.msg.case_update_status', 'case', '../client/cases.php', $uid);
        flash('success', __('flash.case.updated'));
    }
    if ($fa === 'note') {
        $pdo->prepare('INSERT INTO case_notes (case_id, user_id, note, is_private) VALUES (?,?,?,?)')
            ->execute([$caseId, $uid, post('note'), (int)(post('is_private')==='1')]);
        flash('success', __('flash.note.added'));
    }
    if ($fa === 'upload') {
        try {
            $file = handle_upload($_FILES['document'] ?? []);
            if (!$file) throw new RuntimeException(__('error.upload.none'));
            $pdo->prepare('INSERT INTO case_documents (case_id, client_id, uploaded_by, title, file_name, file_path, file_type, file_size, category, description) VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([$caseId, $case['client_id'], $uid, post('title') ?: $file['file_name'], $file['file_name'], $file['file_path'], $file['file_type'], $file['file_size'], post('category') ?: 'legal', post('description')]);
            flash('success', __('flash.document.uploaded'));
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
    }
    redirect('cases.php?action=view&id=' . $caseId);
}

$pageTitle = __('page.my_cases');
$pageSubtitle = __('ai.subtitle.lawyer');
$portal = 'lawyer';
$activeNav = 'cases';
$action = get('action', $id ? 'view' : 'list');

if (($action === 'view' || $id > 0) && $id) {
    $stmt = $pdo->prepare(
        "SELECT c.*,
                CONCAT(u.first_name,' ',u.last_name) AS client_name,
                u.email AS client_email,
                u.phone AS client_phone,
                CONCAT(lw.first_name,' ',lw.last_name) AS lawyer_name
         FROM cases c
         JOIN users u ON u.id = c.client_id
         LEFT JOIN users lw ON lw.id = c.lawyer_id
         WHERE c.id = ? AND " . lawyer_case_access_sql('c')
    );
    $stmt->execute([$id, $uid, $uid]);
    $case = $stmt->fetch();
    if (!$case) {
        flash('error', __('flash.case.not_found'));
        redirect('cases.php');
    }
    if (function_exists('ensure_case_create_columns')) {
        ensure_case_create_columns($pdo);
    }
    $feeItems = case_fee_items($pdo, $id);
    $viewBackUrl = 'cases.php';
    $caseTeamLabel = case_lawyers_label($pdo, $id);
    $myCaseTasks = array_values(array_filter(
        case_tasks_for_lawyer($pdo, $uid),
        static fn(array $task): bool => (int) ($task['case_id'] ?? 0) === $id
    ));
    $notes = $pdo->prepare('SELECT n.*, CONCAT(u.first_name," ",u.last_name) AS author FROM case_notes n JOIN users u ON u.id=n.user_id WHERE n.case_id=? ORDER BY n.created_at DESC');
    $notes->execute([$id]);
    $notes = $notes->fetchAll();
    $docs = $pdo->prepare('SELECT * FROM case_documents WHERE case_id=? ORDER BY created_at DESC');
    $docs->execute([$id]);
    $docs = $docs->fetchAll();
    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../includes/case-view.php';
    ?>
    <div class="grid grid-2" style="margin-top:1rem;">
        <div class="panel">
            <h2><?= __e('lawyer.cases.case_notes') ?></h2>
            <form method="post" class="form-grid entity-inline-form case-add-note-form" style="margin-bottom:1rem;">
                <?= csrf_field() ?><input type="hidden" name="form_action" value="note"><input type="hidden" name="case_id" value="<?= $id ?>">
                <div class="case-add-note-head">
                    <label for="lawyer-case-note-input"><?= __e('cases.add_note') ?></label>
                    <button class="btn btn-primary btn-sm" type="submit"><?= __e('cases.add_note') ?></button>
                </div>
                <div class="form-group full">
                    <textarea id="lawyer-case-note-input" name="note" required rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="is_private" value="1"> <?= __e('lawyer.cases.private') ?></label>
                </div>
            </form>
            <div class="list-stack"><?php foreach ($notes as $n): ?><div class="list-item"><strong><?= e($n['author']) ?></strong><span class="muted"><?= e(format_datetime($n['created_at'])) ?></span><div><?= nl2br(e(t_content($n['note']))) ?></div></div><?php endforeach; ?></div>
        </div>
        <div class="panel">
            <h2><?= __e('cases.documents') ?></h2>
            <form method="post" enctype="multipart/form-data" class="form-grid entity-inline-form" style="margin-bottom:1rem;">
                <?= csrf_field() ?><input type="hidden" name="form_action" value="upload"><input type="hidden" name="case_id" value="<?= $id ?>">
                <div class="entity-field-row entity-field-row--2">
                    <div class="form-group"><label><?= __e('common.title') ?></label><input name="title" placeholder="<?= __e('common.title') ?>"></div>
                    <div class="form-group"><label><?= __e('common.category') ?></label><select name="category"><?php foreach (['legal','contract','evidence','court','other'] as $c): ?><option value="<?= $c ?>"><?= e(__('doc.category.' . $c)) ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="form-group full"><label><?= __e('common.file') ?></label><input type="file" name="document" required></div>
                <div class="form-group full"><button class="btn btn-accent btn-sm" type="submit"><?= __e('common.upload') ?></button></div>
            </form>
            <div class="list-stack"><?php foreach ($docs as $d): ?><div class="list-item"><strong><?= e(t_content($d['title'])) ?></strong><a href="../<?= e($d['file_path']) ?>" target="_blank"><?= __e('common.download') ?> / <?= __e('common.review') ?></a></div><?php endforeach; ?></div>
        </div>
    </div>
    <div class="panel" style="margin-top:1rem;">
        <h2><?= __e('lawyer.cases.change_status') ?></h2>
        <form method="post" class="form-grid entity-inline-form">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="status"><input type="hidden" name="case_id" value="<?= $id ?>">
            <div class="entity-field-row entity-field-row--2">
                <div class="form-group"><label><?= __e('common.status') ?></label>
                    <select name="status"><?php foreach (['open','active','pending','on_hold','closed','reopened'] as $s): ?><option value="<?= $s ?>" <?= $case['status']===$s?'selected':'' ?>><?= e(translate_status($s)) ?></option><?php endforeach; ?></select>
                </div>
                <div class="form-group"><button class="btn btn-primary" type="submit"><?= __e('lawyer.cases.update_status') ?></button></div>
            </div>
        </form>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$rows = $pdo->prepare("SELECT c.*, CONCAT(u.first_name,' ',u.last_name) AS client_name FROM cases c JOIN users u ON u.id=c.client_id WHERE " . lawyer_case_access_sql('c') . " ORDER BY c.updated_at DESC");
$rows->execute([$uid, $uid]);
$rows = $rows->fetchAll();
$totalCases = count($rows);
$perPage = 10;
$page = max(1, (int) get('page', 1));
$totalPages = max(1, (int) ceil($totalCases / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$pageRows = array_slice($rows, $offset, $perPage);
$shownFrom = $totalCases === 0 ? 0 : $offset + 1;
$shownTo = min($offset + count($pageRows), $totalCases);
require __DIR__ . '/../includes/header.php';
?>
<div class="panel case-list-panel">
    <div class="case-list-head">
        <div class="case-list-title">
            <h2><?= __e('lawyer.cases.assigned') ?></h2>
        </div>
    </div>
    <div class="table-wrap case-table-wrap">
        <table class="case-table">
            <thead>
                <tr>
                    <th><?= __e('common.case') ?></th>
                    <th><?= __e('common.client') ?></th>
                    <th><?= __e('common.hearing') ?></th>
                    <th><?= __e('common.status') ?></th>
                    <th class="col-actions"><?= __e('common.actions') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pageRows as $c): ?>
                <tr>
                    <td>
                        <strong><?= e($c['case_number']) ?></strong>
                        <div class="muted"><?= e(t_content($c['title'])) ?></div>
                    </td>
                    <td><?= e($c['client_name']) ?></td>
                    <td><?= e(format_date($c['next_hearing_date'])) ?></td>
                    <td><?= status_badge($c['status']) ?></td>
                    <td class="col-actions">
                        <div class="row-actions">
                            <a class="btn btn-row-edit btn-sm" href="?action=view&id=<?= (int) $c['id'] ?>"><?= __e('common.view') ?></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pageRows): ?>
                <tr><td colspan="5" class="muted"><?= __e('common.no_records') ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="case-list-foot">
        <p class="case-list-footer muted"><?= e(__($totalCases === 1 ? 'cases.pager.showing_one' : 'cases.pager.showing_many', ['from' => (int) $shownFrom, 'to' => (int) $shownTo, 'total' => (int) $totalCases])) ?></p>
        <?php if ($totalPages > 1): ?>
        <nav class="case-list-pager" aria-label="<?= __e('cases.pagination.aria') ?>">
            <?php if ($page > 1): ?>
            <a class="case-page-btn" href="?page=<?= $page - 1 ?>" aria-label="<?= __e('cases.pagination.prev') ?>">‹</a>
            <?php else: ?>
            <span class="case-page-btn is-disabled" aria-disabled="true">‹</span>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a class="case-page-btn<?= $p === $page ? ' is-active' : '' ?>" href="?page=<?= $p ?>"<?= $p === $page ? ' aria-current="page"' : '' ?>><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a class="case-page-btn" href="?page=<?= $page + 1 ?>" aria-label="<?= __e('cases.pagination.next') ?>">›</a>
            <?php else: ?>
            <span class="case-page-btn is-disabled" aria-disabled="true">›</span>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
