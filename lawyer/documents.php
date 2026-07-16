<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$uid = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $caseId = (int) post('case_id');
        $check = $pdo->prepare('SELECT client_id FROM cases WHERE id=? AND lawyer_id=?');
        $check->execute([$caseId, $uid]);
        $clientId = $check->fetchColumn();
        if (!$clientId) throw new RuntimeException('Select one of your assigned cases.');
        $file = handle_upload($_FILES['document'] ?? []);
        if (!$file) throw new RuntimeException('No file uploaded.');
        $pdo->prepare('INSERT INTO case_documents (case_id, client_id, uploaded_by, title, file_name, file_path, file_type, file_size, category, description) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$caseId, $clientId, $uid, post('title') ?: $file['file_name'], $file['file_name'], $file['file_path'], $file['file_type'], $file['file_size'], post('category') ?: 'legal', post('description')]);
        flash('success', 'Document uploaded.');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('documents.php');
}

$docs = $pdo->prepare("SELECT d.*, c.case_number FROM case_documents d LEFT JOIN cases c ON c.id=d.case_id WHERE c.lawyer_id=? OR d.uploaded_by=? ORDER BY d.created_at DESC");
$docs->execute([$uid, $uid]);
$docs = $docs->fetchAll();
$cases = $pdo->prepare('SELECT id, case_number, title FROM cases WHERE lawyer_id=?');
$cases->execute([$uid]);
$cases = $cases->fetchAll();

$pageTitle = __('page.document_management');
$pageSubtitle = 'Upload evidence, legal docs, review contracts, generate work product';
$portal = 'lawyer';
$activeNav = 'documents';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <h2>Upload document</h2>
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <?= csrf_field() ?>
        <div class="form-group"><label>Case</label><select name="case_id" required><?php foreach ($cases as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['case_number'].' — '.$c['title']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Category</label><select name="category"><?php foreach (['evidence','legal','contract','court','other'] as $c): ?><option value="<?= $c ?>"><?= ucfirst($c) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Title</label><input name="title"></div>
        <div class="form-group"><label>File</label><input type="file" name="document" required></div>
        <div class="form-group full"><label>Description / drafting notes</label><textarea name="description" placeholder="Use AI Assistant to generate draft letters, then upload the final PDF here."></textarea></div>
        <div class="form-actions full"><button class="btn btn-primary" type="submit">Upload</button><a class="btn btn-ghost" href="ai.php">Generate with AI</a></div>
    </form>
</div>
<div class="panel">
    <h2>Document library</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Title</th><th>Case</th><th>Category</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($docs as $d): ?>
                <tr>
                    <td><?= e($d['title']) ?></td>
                    <td><?= e($d['case_number'] ?: '—') ?></td>
                    <td><?= e(ucfirst($d['category'])) ?></td>
                    <td><?= e(format_datetime($d['created_at'])) ?></td>
                    <td><a class="chip" href="../<?= e($d['file_path']) ?>" target="_blank">Download</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
