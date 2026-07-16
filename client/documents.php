<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['client']);
$pdo = db();
$uid = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $file = handle_upload($_FILES['document'] ?? []);
        if (!$file) throw new RuntimeException('Select a file to upload.');
        $caseId = post('case_id') ?: null;
        if ($caseId) {
            $check = $pdo->prepare('SELECT id FROM cases WHERE id=? AND client_id=?');
            $check->execute([(int)$caseId, $uid]);
            if (!$check->fetch()) throw new RuntimeException('Invalid case.');
        }
        $pdo->prepare('INSERT INTO case_documents (case_id, client_id, uploaded_by, title, file_name, file_path, file_type, file_size, category, description) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$caseId, $uid, $uid, post('title') ?: $file['file_name'], $file['file_name'], $file['file_path'], $file['file_type'], $file['file_size'], post('category') ?: 'other', post('description')]);
        $lawyerId = current_user()['assigned_lawyer_id'];
        if ($lawyerId) {
            create_notification($pdo, (int)$lawyerId, 'Client document uploaded', post('title') ?: $file['file_name'], 'document', '../lawyer/documents.php', $uid);
        }
        flash('success', 'Document uploaded.');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('documents.php');
}

$docs = $pdo->prepare('SELECT d.*, c.case_number FROM case_documents d LEFT JOIN cases c ON c.id=d.case_id WHERE d.client_id=? ORDER BY d.created_at DESC');
$docs->execute([$uid]);
$docs = $docs->fetchAll();
$cases = $pdo->prepare('SELECT id, case_number, title FROM cases WHERE client_id=?');
$cases->execute([$uid]);
$cases = $cases->fetchAll();
$invoices = $pdo->prepare('SELECT * FROM invoices WHERE client_id=? ORDER BY created_at DESC');
$invoices->execute([$uid]);
$invoices = $invoices->fetchAll();

$pageTitle = __('page.documents');
$pageSubtitle = 'Upload requested files, download legal docs, contracts, and invoices';
$portal = 'client';
$activeNav = 'documents';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <h2>Upload requested document</h2>
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <?= csrf_field() ?>
        <div class="form-group"><label>Related case</label><select name="case_id"><option value="">—</option><?php foreach ($cases as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['case_number']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Category</label><select name="category"><option value="other">Other</option><option value="evidence">Evidence</option><option value="contract">Contract</option><option value="legal">Legal</option></select></div>
        <div class="form-group"><label>Title</label><input name="title"></div>
        <div class="form-group"><label>File</label><input type="file" name="document" required></div>
        <div class="form-group full"><textarea name="description" placeholder="Optional note for your lawyer"></textarea></div>
        <div class="form-actions full"><button class="btn btn-primary" type="submit">Upload</button></div>
    </form>
</div>
<div class="grid grid-2">
    <div class="panel">
        <h2>Your files &amp; contracts</h2>
        <div class="list-stack">
            <?php foreach ($docs as $d): ?>
                <div class="list-item">
                    <strong><?= e($d['title']) ?></strong>
                    <span class="muted"><?= e(ucfirst($d['category'])) ?> · <?= e($d['case_number'] ?: 'General') ?> · <?= e(format_datetime($d['created_at'])) ?></span>
                    <div><a href="../<?= e($d['file_path']) ?>" target="_blank">Download</a></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$docs): ?><div class="empty-state">No documents yet.</div><?php endif; ?>
        </div>
    </div>
    <div class="panel">
        <h2>Invoices (download)</h2>
        <div class="list-stack">
            <?php foreach ($invoices as $i): ?>
                <div class="list-item">
                    <strong><?= e($i['invoice_number']) ?> · <?= e(money($i['total'])) ?></strong>
                    <span class="muted"><?= e($i['title']) ?> · <?= status_badge($i['status']) ?></span>
                    <div class="muted">Issued <?= e(format_date($i['issued_at'])) ?> — view full billing under Payments.</div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
