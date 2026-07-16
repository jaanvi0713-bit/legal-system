<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'staff']);
$pdo = db();
$action = get('action', 'list');
$id = (int) get('id', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'save') {
        $editId = (int) post('id');
        $clientId = (int) post('client_id');
        $lawyerId = post('lawyer_id') ?: null;
        if ($editId) {
            $pdo->prepare('UPDATE cases SET title=?, description=?, case_type=?, status=?, priority=?, client_id=?, lawyer_id=?, court_name=?, court_location=?, filing_date=?, next_hearing_date=?, closed_at=IF(?="closed", COALESCE(closed_at, NOW()), NULL) WHERE id=?')
                ->execute([
                    post('title'), post('description'), post('case_type'), post('status'), post('priority'),
                    $clientId, $lawyerId, post('court_name'), post('court_location'),
                    post('filing_date') ?: null, post('next_hearing_date') ?: null, post('status'), $editId,
                ]);
            flash('success', 'Case updated.');
            $caseId = $editId;
        } else {
            $caseNumber = generate_case_number($pdo);
            $pdo->prepare('INSERT INTO cases (case_number, title, description, case_type, status, priority, client_id, lawyer_id, court_name, court_location, filing_date, next_hearing_date, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    $caseNumber, post('title'), post('description'), post('case_type'), post('status'), post('priority'),
                    $clientId, $lawyerId, post('court_name'), post('court_location'),
                    post('filing_date') ?: date('Y-m-d'), post('next_hearing_date') ?: null, current_user()['id'],
                ]);
            $caseId = (int) $pdo->lastInsertId();
            flash('success', 'Case created: ' . $caseNumber);
            if ($lawyerId) {
                create_notification($pdo, (int)$lawyerId, 'New case assigned', $caseNumber . ' assigned to you.', 'case', '../lawyer/cases.php?id=' . $caseId, current_user()['id']);
            }
            create_notification($pdo, $clientId, 'Case opened', 'Your case ' . $caseNumber . ' is now in the system.', 'case', '../client/cases.php', current_user()['id']);
        }
        log_activity($pdo, current_user()['id'], $editId ? 'update' : 'create', 'case', $caseId, 'Case saved');
        redirect('cases.php?action=view&id=' . $caseId);
    }
    if ($fa === 'upload') {
        try {
            $file = handle_upload($_FILES['document'] ?? []);
            if (!$file) throw new RuntimeException('No file uploaded.');
            $pdo->prepare('INSERT INTO case_documents (case_id, client_id, uploaded_by, title, file_name, file_path, file_type, file_size, category, description) VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    (int) post('case_id'), post('client_id') ?: null, current_user()['id'], post('title') ?: $file['file_name'],
                    $file['file_name'], $file['file_path'], $file['file_type'], $file['file_size'], post('category') ?: 'legal', post('description'),
                ]);
            flash('success', 'Document uploaded.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('cases.php?action=view&id=' . (int) post('case_id'));
    }
    if ($fa === 'note') {
        $pdo->prepare('INSERT INTO case_notes (case_id, user_id, note, is_private) VALUES (?,?,?,?)')
            ->execute([(int) post('case_id'), current_user()['id'], post('note'), (int) (post('is_private') === '1')]);
        flash('success', 'Note added.');
        redirect('cases.php?action=view&id=' . (int) post('case_id'));
    }
    if ($fa === 'reopen') {
        $pdo->prepare('UPDATE cases SET status="reopened", closed_at=NULL WHERE id=?')->execute([(int) post('id')]);
        flash('success', 'Case reopened.');
        redirect('cases.php?action=view&id=' . (int) post('id'));
    }
}

$clients = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role='client' ORDER BY first_name")->fetchAll();
$lawyers = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role='lawyer' AND is_active=1 ORDER BY first_name")->fetchAll();
$pageTitle = __('page.cases');
$pageSubtitle = 'Full control over every matter in the firm';
$portal = 'admin';
$activeNav = 'cases';

if ($action === 'create' || ($action === 'edit' && $id)) {
    $case = [
        'id' => 0, 'title' => '', 'description' => '', 'case_type' => 'Commercial', 'status' => 'open', 'priority' => 'medium',
        'client_id' => '', 'lawyer_id' => '', 'court_name' => '', 'court_location' => '', 'filing_date' => date('Y-m-d'), 'next_hearing_date' => '',
    ];
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM cases WHERE id=?');
        $stmt->execute([$id]);
        $case = $stmt->fetch() ?: $case;
    }
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="panel">
        <h2><?= $id ? 'Edit case' : 'Create case' ?></h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="save"><input type="hidden" name="id" value="<?= (int)$case['id'] ?>">
            <div class="form-group full"><label>Title</label><input name="title" required value="<?= e($case['title']) ?>"></div>
            <div class="form-group"><label>Type</label><input name="case_type" value="<?= e($case['case_type']) ?>"></div>
            <div class="form-group"><label>Status</label>
                <select name="status"><?php foreach (['open','active','pending','on_hold','closed','reopened'] as $s): ?><option value="<?= $s ?>" <?= $case['status']===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label>Priority</label>
                <select name="priority"><?php foreach (['low','medium','high','urgent'] as $p): ?><option value="<?= $p ?>" <?= $case['priority']===$p?'selected':'' ?>><?= ucfirst($p) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label>Client</label>
                <select name="client_id" required><option value="">Select…</option><?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$case['client_id']===(int)$c['id']?'selected':'' ?>><?= e(full_name($c)) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label>Lawyer</label>
                <select name="lawyer_id"><option value="">Unassigned</option><?php foreach ($lawyers as $l): ?><option value="<?= (int)$l['id'] ?>" <?= (int)$case['lawyer_id']===(int)$l['id']?'selected':'' ?>><?= e(full_name($l)) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label>Court name</label><input name="court_name" value="<?= e($case['court_name']) ?>"></div>
            <div class="form-group"><label>Court location</label><input name="court_location" value="<?= e($case['court_location']) ?>"></div>
            <div class="form-group"><label>Filing date</label><input type="date" name="filing_date" value="<?= e($case['filing_date']) ?>"></div>
            <div class="form-group"><label>Next hearing</label><input type="date" name="next_hearing_date" value="<?= e($case['next_hearing_date']) ?>"></div>
            <div class="form-group full"><label>Description / contract notes</label><textarea name="description"><?= e($case['description']) ?></textarea></div>
            <div class="form-actions full"><button class="btn btn-primary" type="submit">Save case</button><a class="btn btn-ghost" href="cases.php">Cancel</a></div>
        </form>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

if ($action === 'view' && $id) {
    $stmt = $pdo->prepare('SELECT c.*, CONCAT(cl.first_name," ",cl.last_name) AS client_name, CONCAT(lw.first_name," ",lw.last_name) AS lawyer_name FROM cases c JOIN users cl ON cl.id=c.client_id LEFT JOIN users lw ON lw.id=c.lawyer_id WHERE c.id=?');
    $stmt->execute([$id]);
    $case = $stmt->fetch();
    if (!$case) { flash('error', 'Case not found.'); redirect('cases.php'); }
    $notes = $pdo->prepare('SELECT n.*, CONCAT(u.first_name," ",u.last_name) AS author FROM case_notes n JOIN users u ON u.id=n.user_id WHERE n.case_id=? ORDER BY n.created_at DESC');
    $notes->execute([$id]);
    $notes = $notes->fetchAll();
    $docs = $pdo->prepare('SELECT * FROM case_documents WHERE case_id=? ORDER BY created_at DESC');
    $docs->execute([$id]);
    $docs = $docs->fetchAll();
    $hearings = $pdo->prepare('SELECT * FROM court_hearings WHERE case_id=? ORDER BY hearing_date DESC');
    $hearings->execute([$id]);
    $hearings = $hearings->fetchAll();
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="panel">
        <div class="panel-header">
            <div>
                <h2><?= e($case['case_number']) ?> · <?= e($case['title']) ?></h2>
                <p class="muted">Client: <?= e($case['client_name']) ?> · Lawyer: <?= e($case['lawyer_name'] ?: 'Unassigned') ?></p>
            </div>
            <div class="quick-links">
                <?= status_badge($case['status']) ?> <?= status_badge($case['priority']) ?>
                <a class="btn btn-sm btn-primary" href="?action=edit&id=<?= $id ?>">Edit</a>
                <?php if ($case['status'] === 'closed'): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="reopen"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-sm btn-accent" type="submit">Reopen</button></form>
                <?php endif; ?>
            </div>
        </div>
        <p><?= nl2br(e($case['description'] ?: 'No description.')) ?></p>
        <div class="grid grid-3" style="margin-top:1rem;">
            <div class="list-item"><strong>Court</strong><?= e($case['court_name'] ?: '—') ?></div>
            <div class="list-item"><strong>Location</strong><?= e($case['court_location'] ?: '—') ?></div>
            <div class="list-item"><strong>Next hearing</strong><?= e(format_date($case['next_hearing_date'])) ?></div>
        </div>
    </div>
    <div class="grid grid-2">
        <div class="panel">
            <h2>Case history / notes</h2>
            <form method="post" class="form-grid" style="margin-bottom:1rem;">
                <?= csrf_field() ?><input type="hidden" name="form_action" value="note"><input type="hidden" name="case_id" value="<?= $id ?>">
                <div class="form-group full"><textarea name="note" required placeholder="Add progress note…"></textarea></div>
                <div class="form-group"><label><input type="checkbox" name="is_private" value="1"> Private note</label></div>
                <div class="form-group"><button class="btn btn-sm btn-primary" type="submit">Add note</button></div>
            </form>
            <div class="list-stack">
                <?php foreach ($notes as $n): ?>
                    <div class="list-item"><strong><?= e($n['author']) ?><?= $n['is_private'] ? ' (private)' : '' ?></strong><span class="muted"><?= e(format_datetime($n['created_at'])) ?></span><div><?= nl2br(e($n['note'])) ?></div></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="panel">
            <h2>Documents &amp; contracts</h2>
            <form method="post" enctype="multipart/form-data" class="form-grid" style="margin-bottom:1rem;">
                <?= csrf_field() ?><input type="hidden" name="form_action" value="upload"><input type="hidden" name="case_id" value="<?= $id ?>"><input type="hidden" name="client_id" value="<?= (int)$case['client_id'] ?>">
                <div class="form-group"><label>Title</label><input name="title"></div>
                <div class="form-group"><label>Category</label>
                    <select name="category"><?php foreach (['legal','contract','evidence','court','other'] as $cat): ?><option value="<?= $cat ?>"><?= ucfirst($cat) ?></option><?php endforeach; ?></select>
                </div>
                <div class="form-group full"><label>File</label><input type="file" name="document" required></div>
                <div class="form-group full"><button class="btn btn-sm btn-accent" type="submit">Upload</button></div>
            </form>
            <div class="list-stack">
                <?php foreach ($docs as $d): ?>
                    <div class="list-item"><strong><?= e($d['title']) ?></strong><span class="muted"><?= e($d['category']) ?> · <?= e(format_datetime($d['created_at'])) ?></span><div><a href="../<?= e($d['file_path']) ?>" target="_blank">Download</a></div></div>
                <?php endforeach; ?>
                <?php if (!$docs): ?><div class="empty-state">No documents yet.</div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="panel">
        <div class="panel-header"><h2>Court progress</h2><a href="court.php?action=create&case_id=<?= $id ?>">Add hearing</a></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Date</th><th>Court</th><th>Type</th><th>Status</th><th>Outcome</th></tr></thead>
                <tbody>
                <?php foreach ($hearings as $h): ?>
                    <tr>
                        <td><?= e(format_datetime($h['hearing_date'])) ?></td>
                        <td><?= e($h['court_name']) ?><div class="muted"><?= e($h['court_location']) ?></div></td>
                        <td><?= e($h['hearing_type'] ?: '—') ?></td>
                        <td><?= status_badge($h['status']) ?></td>
                        <td><?= e($h['outcome'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

$cases = $pdo->query("SELECT c.*, CONCAT(cl.first_name,' ',cl.last_name) AS client_name, CONCAT(lw.first_name,' ',lw.last_name) AS lawyer_name FROM cases c JOIN users cl ON cl.id=c.client_id LEFT JOIN users lw ON lw.id=c.lawyer_id ORDER BY c.updated_at DESC")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <div class="panel-header"><h2>All cases</h2><a class="btn btn-primary btn-sm" href="?action=create">Create case</a></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Case</th><th>Client</th><th>Lawyer</th><th>Status</th><th>Hearing</th></tr></thead>
            <tbody>
            <?php foreach ($cases as $c): ?>
                <tr>
                    <td><a href="?action=view&id=<?= (int)$c['id'] ?>"><strong><?= e($c['case_number']) ?></strong></a><div class="muted"><?= e($c['title']) ?></div></td>
                    <td><?= e($c['client_name']) ?></td>
                    <td><?= e($c['lawyer_name'] ?: 'Unassigned') ?></td>
                    <td><?= status_badge($c['status']) ?></td>
                    <td><?= e(format_date($c['next_hearing_date'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
