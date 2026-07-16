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
        if (!$check->fetch()) { flash('error', 'Invalid case.'); redirect('court.php'); }
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
        flash('success', 'Court record saved.');
        redirect('court.php');
    }
}

$hearings = $pdo->prepare("SELECT h.*, c.case_number, c.title FROM court_hearings h JOIN cases c ON c.id=h.case_id WHERE c.lawyer_id=? ORDER BY h.hearing_date DESC");
$hearings->execute([$uid]);
$hearings = $hearings->fetchAll();
$cases = $pdo->prepare('SELECT id, case_number, title FROM cases WHERE lawyer_id=?');
$cases->execute([$uid]);
$cases = $cases->fetchAll();

$pageTitle = __('page.court');
$pageSubtitle = 'Hearing schedules, outcomes, notes, and court documents';
$portal = 'lawyer';
$activeNav = 'court';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <h2>Record / update hearing</h2>
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="form_action" value="save">
        <div class="form-group"><label>Case</label><select name="case_id" required><?php foreach ($cases as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['case_number'].' — '.$c['title']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Hearing date</label><input type="datetime-local" name="hearing_date" required></div>
        <div class="form-group"><label>Court</label><input name="court_name" required></div>
        <div class="form-group"><label>Location</label><input name="court_location"></div>
        <div class="form-group"><label>Type</label><input name="hearing_type"></div>
        <div class="form-group"><label>Status</label><select name="status"><?php foreach (['scheduled','completed','adjourned','cancelled'] as $s): ?><option value="<?= $s ?>"><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
        <div class="form-group full"><label>Outcome</label><textarea name="outcome"></textarea></div>
        <div class="form-group full"><label>Court notes</label><textarea name="notes"></textarea></div>
        <div class="form-group full"><label>Upload court document</label><input type="file" name="document"></div>
        <div class="form-actions full"><button class="btn btn-primary" type="submit">Save</button></div>
    </form>
</div>
<div class="panel">
    <h2>Hearing schedule</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Case</th><th>Court</th><th>Status</th><th>Outcome / notes</th></tr></thead>
            <tbody>
            <?php foreach ($hearings as $h): ?>
                <tr>
                    <td><?= e(format_datetime($h['hearing_date'])) ?></td>
                    <td><?= e($h['case_number']) ?></td>
                    <td><?= e($h['court_name']) ?><div class="muted"><?= e($h['court_location']) ?></div></td>
                    <td><?= status_badge($h['status']) ?></td>
                    <td><?= e($h['outcome'] ?: $h['notes'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
