<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['client']);
$pdo = db();
$uid = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $file = handle_upload($_FILES['document'] ?? []);
        if (!$file) throw new RuntimeException(__('error.upload.select_file'));
        $caseId = post('case_id') ?: null;
        if ($caseId) {
            $check = $pdo->prepare('SELECT id FROM cases WHERE id=? AND client_id=?');
            $check->execute([(int)$caseId, $uid]);
            if (!$check->fetch()) throw new RuntimeException(__('error.case.invalid'));
        }
        $pdo->prepare('INSERT INTO case_documents (case_id, client_id, uploaded_by, title, file_name, file_path, file_type, file_size, category, description) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$caseId, $uid, $uid, post('title') ?: $file['file_name'], $file['file_name'], $file['file_path'], $file['file_type'], $file['file_size'], post('category') ?: 'other', post('description')]);
        $lawyerId = current_user()['assigned_lawyer_id'];
        if ($lawyerId) {
            create_notification($pdo, (int)$lawyerId, 'notify.document_uploaded', post('title') ?: $file['file_name'], 'document', '../lawyer/documents.php', $uid);
        }
        flash('success', __('flash.document.uploaded'));
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
$pageSubtitle = __('ai.subtitle.client');
$portal = 'client';
$activeNav = 'documents';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <h2><?= __e('client.documents.upload') ?></h2>
    <form method="post" enctype="multipart/form-data" class="form-grid entity-inline-form">
        <?= csrf_field() ?>
        <div class="entity-field-row">
            <div class="form-group"><label><?= __e('form.related_case') ?></label><select name="case_id"><option value=""><?= __e('common.em_dash') ?></option><?php foreach ($cases as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['case_number']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label><?= __e('common.category') ?></label><select name="category"><?php foreach (['other','evidence','contract','legal'] as $c): ?><option value="<?= $c ?>"><?= e(__('doc.category.' . $c)) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label><?= __e('common.title') ?></label><input name="title"></div>
        </div>
        <div class="form-group full"><label><?= __e('common.file') ?></label><input type="file" name="document" required></div>
        <div class="form-group full"><label><?= __e('common.description') ?></label><textarea name="description" placeholder="<?= __e('form.details') ?>"></textarea></div>
        <div class="form-actions full"><button class="btn btn-primary" type="submit"><?= __e('common.upload') ?></button></div>
    </form>
</div>
<div class="grid grid-2">
    <div class="panel">
        <h2><?= __e('client.documents.your_files') ?></h2>
        <div class="list-stack">
            <?php foreach ($docs as $d): ?>
                <div class="list-item">
                    <strong><?= e(t_content($d['title'])) ?></strong>
                    <span class="muted"><?= e(__('doc.category.' . $d['category'])) ?> · <?= e($d['case_number'] ?: __('common.case')) ?> · <?= e(format_datetime($d['created_at'])) ?></span>
                    <div class="row-actions" style="justify-content:flex-start;margin-top:0.35rem"><a class="btn btn-row-open btn-sm" href="../<?= e($d['file_path']) ?>" target="_blank"><?= __e('common.download') ?></a></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$docs): ?><div class="empty-state"><?= __e('cases.no_documents') ?></div><?php endif; ?>
        </div>
    </div>
    <div class="panel">
        <h2><?= __e('client.documents.invoices') ?></h2>
        <div class="list-stack">
            <?php foreach ($invoices as $i): ?>
                <div class="list-item">
                    <strong><?= e($i['invoice_number']) ?> · <?= e(money($i['total'])) ?></strong>
                    <span class="muted"><?= e(t_content($i['title'])) ?> · <?= status_badge($i['status']) ?></span>
                    <div class="muted"><?= __e('form.issued') ?> <?= e(format_date($i['issued_at'])) ?> — <?= __e('client.documents.view_billing') ?>.</div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
