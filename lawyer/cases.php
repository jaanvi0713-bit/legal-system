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
    $own = $pdo->prepare('SELECT id, client_id FROM cases WHERE id=? AND lawyer_id=?');
    $own->execute([$caseId, $uid]);
    $case = $own->fetch();
    if (!$case) { flash('error', 'Case not found or not assigned to you.'); redirect('cases.php'); }

    if ($fa === 'status') {
        $pdo->prepare('UPDATE cases SET status=? WHERE id=? AND lawyer_id=?')->execute([post('status'), $caseId, $uid]);
        create_notification($pdo, (int)$case['client_id'], 'Case update', 'Status changed on your case.', 'case', '../client/cases.php', $uid);
        flash('success', 'Case status updated.');
    }
    if ($fa === 'note') {
        $pdo->prepare('INSERT INTO case_notes (case_id, user_id, note, is_private) VALUES (?,?,?,?)')
            ->execute([$caseId, $uid, post('note'), (int)(post('is_private')==='1')]);
        flash('success', 'Note added.');
    }
    if ($fa === 'upload') {
        try {
            $file = handle_upload($_FILES['document'] ?? []);
            if (!$file) throw new RuntimeException('No file uploaded.');
            $pdo->prepare('INSERT INTO case_documents (case_id, client_id, uploaded_by, title, file_name, file_path, file_type, file_size, category, description) VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([$caseId, $case['client_id'], $uid, post('title') ?: $file['file_name'], $file['file_name'], $file['file_path'], $file['file_type'], $file['file_size'], post('category') ?: 'legal', post('description')]);
            flash('success', 'Document uploaded.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
    }
    redirect('cases.php?id=' . $caseId);
}

$pageTitle = __('page.my_cases');
$pageSubtitle = 'Update notes, status, and documents for assigned matters';
$portal = 'lawyer';
$activeNav = 'cases';

if ($id) {
    $stmt = $pdo->prepare("SELECT c.*, CONCAT(u.first_name,' ',u.last_name) AS client_name, u.email AS client_email, u.phone AS client_phone FROM cases c JOIN users u ON u.id=c.client_id WHERE c.id=? AND c.lawyer_id=?");
    $stmt->execute([$id, $uid]);
    $case = $stmt->fetch();
    if (!$case) { flash('error', 'Case not found.'); redirect('cases.php'); }
    $notes = $pdo->prepare('SELECT n.*, CONCAT(u.first_name," ",u.last_name) AS author FROM case_notes n JOIN users u ON u.id=n.user_id WHERE n.case_id=? ORDER BY n.created_at DESC');
    $notes->execute([$id]);
    $notes = $notes->fetchAll();
    $docs = $pdo->prepare('SELECT * FROM case_documents WHERE case_id=? ORDER BY created_at DESC');
    $docs->execute([$id]);
    $docs = $docs->fetchAll();
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="panel">
        <div class="panel-header">
            <div>
                <h2><?= e($case['case_number']) ?> · <?= e($case['title']) ?></h2>
                <p class="muted">Client: <?= e($case['client_name']) ?> · <?= e($case['client_email']) ?> · <?= e($case['client_phone'] ?: '') ?></p>
            </div>
            <?= status_badge($case['status']) ?>
        </div>
        <p><?= nl2br(e($case['description'] ?: '')) ?></p>
        <form method="post" class="form-grid" style="margin-top:1rem;">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="status"><input type="hidden" name="case_id" value="<?= $id ?>">
            <div class="form-group"><label>Change status</label>
                <select name="status"><?php foreach (['open','active','pending','on_hold','closed','reopened'] as $s): ?><option value="<?= $s ?>" <?= $case['status']===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group" style="align-self:end;"><button class="btn btn-primary" type="submit">Update status</button></div>
        </form>
    </div>
    <div class="grid grid-2">
        <div class="panel">
            <h2>Case notes</h2>
            <form method="post" class="form-grid" style="margin-bottom:1rem;">
                <?= csrf_field() ?><input type="hidden" name="form_action" value="note"><input type="hidden" name="case_id" value="<?= $id ?>">
                <div class="form-group full"><textarea name="note" required></textarea></div>
                <div class="form-group"><label><input type="checkbox" name="is_private" value="1"> Private</label></div>
                <div class="form-group"><button class="btn btn-sm btn-primary" type="submit">Add note</button></div>
            </form>
            <div class="list-stack"><?php foreach ($notes as $n): ?><div class="list-item"><strong><?= e($n['author']) ?></strong><span class="muted"><?= e(format_datetime($n['created_at'])) ?></span><div><?= nl2br(e($n['note'])) ?></div></div><?php endforeach; ?></div>
        </div>
        <div class="panel">
            <h2>Documents &amp; contracts</h2>
            <form method="post" enctype="multipart/form-data" class="form-grid" style="margin-bottom:1rem;">
                <?= csrf_field() ?><input type="hidden" name="form_action" value="upload"><input type="hidden" name="case_id" value="<?= $id ?>">
                <div class="form-group"><input name="title" placeholder="Title"></div>
                <div class="form-group"><select name="category"><?php foreach (['legal','contract','evidence','court','other'] as $c): ?><option value="<?= $c ?>"><?= ucfirst($c) ?></option><?php endforeach; ?></select></div>
                <div class="form-group full"><input type="file" name="document" required></div>
                <div class="form-group full"><button class="btn btn-sm btn-accent" type="submit">Upload</button></div>
            </form>
            <div class="list-stack"><?php foreach ($docs as $d): ?><div class="list-item"><strong><?= e($d['title']) ?></strong><a href="../<?= e($d['file_path']) ?>" target="_blank">Download / review</a></div><?php endforeach; ?></div>
        </div>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

$rows = $pdo->prepare("SELECT c.*, CONCAT(u.first_name,' ',u.last_name) AS client_name FROM cases c JOIN users u ON u.id=c.client_id WHERE c.lawyer_id=? ORDER BY c.updated_at DESC");
$rows->execute([$uid]);
$rows = $rows->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <h2>Assigned cases</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Case</th><th>Client</th><th>Status</th><th>Hearing</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $c): ?>
                <tr>
                    <td><a href="?id=<?= (int)$c['id'] ?>"><strong><?= e($c['case_number']) ?></strong></a><div class="muted"><?= e($c['title']) ?></div></td>
                    <td><?= e($c['client_name']) ?></td>
                    <td><?= status_badge($c['status']) ?></td>
                    <td><?= e(format_date($c['next_hearing_date'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
