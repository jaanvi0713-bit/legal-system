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
        $docId = (int) $pdo->lastInsertId();
        $docRequestId = (int) post('document_request_id');
        if ($docRequestId > 0) {
            ensure_document_requests_table($pdo);
            $pdo->prepare('UPDATE document_requests SET status="fulfilled", fulfilled_document_id=? WHERE id=? AND client_id=? AND status="pending"')
                ->execute([$docId, $docRequestId, $uid]);
        }
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
ensure_document_requests_table($pdo);
$docRequests = $pdo->prepare(
    'SELECT r.*, c.case_number FROM document_requests r
     LEFT JOIN cases c ON c.id = r.case_id
     WHERE r.client_id=? AND r.status="pending"
     ORDER BY r.created_at DESC'
);
$docRequests->execute([$uid]);
$docRequests = $docRequests->fetchAll();
$selectedRequestId = max(0, (int) get('request', 0));
$selectedRequest = null;
foreach ($docRequests as $req) {
    if ((int) $req['id'] === $selectedRequestId) {
        $selectedRequest = $req;
        break;
    }
}
if ($selectedRequest === null && $docRequests) {
    $selectedRequest = $docRequests[0];
    $selectedRequestId = (int) $selectedRequest['id'];
}

$listPerPage = 10;
$totalDocs = count($docs);
$docsPage = max(1, (int) get('docs_page', 1));
$docsTotalPages = max(1, (int) ceil($totalDocs / $listPerPage));
if ($docsPage > $docsTotalPages) {
    $docsPage = $docsTotalPages;
}
$docsOffset = ($docsPage - 1) * $listPerPage;
$pageDocs = array_slice($docs, $docsOffset, $listPerPage);
$docsShownFrom = $totalDocs === 0 ? 0 : $docsOffset + 1;
$docsShownTo = min($docsOffset + count($pageDocs), $totalDocs);

$totalInvoices = count($invoices);
$invPage = max(1, (int) get('inv_page', 1));
$invTotalPages = max(1, (int) ceil($totalInvoices / $listPerPage));
if ($invPage > $invTotalPages) {
    $invPage = $invTotalPages;
}
$invOffset = ($invPage - 1) * $listPerPage;
$pageInvoices = array_slice($invoices, $invOffset, $listPerPage);
$invShownFrom = $totalInvoices === 0 ? 0 : $invOffset + 1;
$invShownTo = min($invOffset + count($pageInvoices), $totalInvoices);

$documentsPagerQuery = static function (array $extra = []) use ($docsPage, $invPage, $selectedRequestId): array {
    $qs = $extra;
    if ($docsPage > 1 && !isset($qs['docs_page'])) {
        $qs['docs_page'] = $docsPage;
    }
    if ($invPage > 1 && !isset($qs['inv_page'])) {
        $qs['inv_page'] = $invPage;
    }
    if ($selectedRequestId > 0 && !isset($qs['request'])) {
        $qs['request'] = $selectedRequestId;
    }
    return $qs;
};
$docsPagerUrl = static function (int $targetPage) use ($documentsPagerQuery): string {
    return '?' . http_build_query($documentsPagerQuery(['docs_page' => max(1, $targetPage)]));
};
$invoicesPagerUrl = static function (int $targetPage) use ($documentsPagerQuery): string {
    return '?' . http_build_query($documentsPagerQuery(['inv_page' => max(1, $targetPage)]));
};

$pageTitle = __('page.documents');
$pageSubtitle = __('ai.subtitle.client');
$portal = 'client';
$activeNav = 'documents';
require __DIR__ . '/../includes/header.php';
$uploadCaseId = $selectedRequest ? (int) ($selectedRequest['case_id'] ?? 0) : 0;
$uploadTitle = $selectedRequest ? (string) ($selectedRequest['title'] ?? '') : '';
$uploadDescription = $selectedRequest && !empty($selectedRequest['instructions']) ? (string) $selectedRequest['instructions'] : '';
?>
<section class="panel client-doc-upload-panel">
    <div class="client-doc-upload-head">
        <div>
            <h2><?= __e('client.documents.upload') ?></h2>
            <p class="client-doc-upload-hint"><?= __e('cases.docs.types_hint') ?></p>
        </div>
    </div>

    <?php if ($docRequests): ?>
    <div class="client-doc-requests" role="group" aria-label="<?= __e('cases.docs.requested_from_client') ?>">
        <div class="client-doc-requests-label"><?= __e('cases.docs.requested_from_client') ?></div>
        <div class="client-doc-request-list">
            <?php foreach ($docRequests as $req): ?>
                <?php
                $isActive = (int) $req['id'] === $selectedRequestId;
                $reqMeta = trim(($req['case_number'] ?: '') . (!empty($req['instructions']) ? ' · ' . $req['instructions'] : ''));
                ?>
                <a class="client-doc-request-chip<?= $isActive ? ' is-active' : '' ?>" href="documents.php?request=<?= (int) $req['id'] ?>#clientDocUploadForm">
                    <span class="client-doc-request-chip-title"><?= e($req['title']) ?></span>
                    <?php if (!empty($req['is_required'])): ?><span class="client-doc-request-chip-badge"><?= __e('cases.docs.required') ?></span><?php endif; ?>
                    <?php if ($reqMeta !== ''): ?><span class="client-doc-request-chip-meta"><?= e($reqMeta) ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form-grid client-doc-upload-form" id="clientDocUploadForm">
        <?= csrf_field() ?>
        <?php if ($selectedRequestId > 0): ?>
            <input type="hidden" name="document_request_id" value="<?= $selectedRequestId ?>">
        <?php endif; ?>
        <div class="entity-field-row entity-field-row--2">
            <div class="form-group">
                <label for="clientDocCase"><?= __e('form.related_case') ?></label>
                <select name="case_id" id="clientDocCase">
                    <option value=""><?= __e('common.em_dash') ?></option>
                    <?php foreach ($cases as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $uploadCaseId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['case_number'] . ' · ' . $c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="clientDocCategory"><?= __e('common.category') ?></label>
                <select name="category" id="clientDocCategory">
                    <?php foreach (['other', 'evidence', 'contract', 'legal'] as $c): ?>
                        <option value="<?= $c ?>"><?= e(__('doc.category.' . $c)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group full">
            <label for="clientDocTitle"><?= __e('common.title') ?></label>
            <input name="title" id="clientDocTitle" value="<?= e($uploadTitle) ?>" placeholder="<?= __e('cases.docs.request_title_ph') ?>">
        </div>
        <div class="form-group full">
            <span class="client-doc-file-label" id="clientDocFileLabel"><?= __e('common.file') ?></span>
            <label class="client-doc-file-drop" for="clientDocFile">
                <span class="client-doc-file-icon" aria-hidden="true">↑</span>
                <span class="client-doc-file-copy">
                    <strong><?= __e('client.documents.choose_file') ?></strong>
                    <span class="client-doc-file-hint"><?= __e('cases.docs.types_hint') ?></span>
                </span>
                <span class="client-doc-file-name" id="clientDocFileName"><?= __e('client.documents.no_file_chosen') ?></span>
            </label>
            <input type="file" name="document" id="clientDocFile" class="client-doc-file-input" required>
        </div>
        <div class="form-group full">
            <label for="clientDocDescription"><?= __e('common.description') ?></label>
            <textarea name="description" id="clientDocDescription" rows="3" placeholder="<?= __e('form.details') ?>"><?= e($uploadDescription) ?></textarea>
        </div>
        <div class="form-actions full client-doc-upload-actions">
            <button class="btn btn-primary" type="submit"><?= __e('common.upload') ?></button>
        </div>
    </form>
</section>
<div class="grid grid-2">
    <div class="panel case-list-panel">
        <div class="case-list-head">
            <div class="case-list-title">
                <h2><?= __e('client.documents.your_files') ?></h2>
            </div>
        </div>
        <div class="list-stack client-doc-list-stack">
            <?php foreach ($pageDocs as $d): ?>
                <div class="list-item">
                    <strong><?= e(t_content($d['title'])) ?></strong>
                    <span class="muted"><?= e(__('doc.category.' . $d['category'])) ?> · <?= e($d['case_number'] ?: __('common.case')) ?> · <?= e(format_datetime($d['created_at'])) ?></span>
                    <div class="row-actions client-doc-row-actions"><a class="btn btn-row-open btn-sm" href="../<?= e($d['file_path']) ?>" target="_blank"><?= __e('common.download') ?></a></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$pageDocs): ?><div class="empty-state"><?= __e('cases.no_documents') ?></div><?php endif; ?>
        </div>
        <div class="case-list-foot client-doc-list-foot">
            <p class="case-list-footer muted"><?= e(__($totalDocs === 1 ? 'client.documents.files.pager.showing_one' : 'client.documents.files.pager.showing_many', ['from' => (int) $docsShownFrom, 'to' => (int) $docsShownTo, 'total' => (int) $totalDocs])) ?></p>
            <?php if ($docsTotalPages > 1): ?>
            <nav class="case-list-pager" aria-label="<?= __e('client.documents.files.pagination.aria') ?>">
                <?php if ($docsPage > 1): ?>
                <a class="case-page-btn" href="<?= e($docsPagerUrl($docsPage - 1)) ?>" aria-label="<?= __e('cases.pagination.prev') ?>">‹</a>
                <?php else: ?>
                <span class="case-page-btn is-disabled" aria-disabled="true">‹</span>
                <?php endif; ?>
                <?php for ($p = 1; $p <= $docsTotalPages; $p++): ?>
                <a class="case-page-btn<?= $p === $docsPage ? ' is-active' : '' ?>" href="<?= e($docsPagerUrl($p)) ?>"<?= $p === $docsPage ? ' aria-current="page"' : '' ?>><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($docsPage < $docsTotalPages): ?>
                <a class="case-page-btn" href="<?= e($docsPagerUrl($docsPage + 1)) ?>" aria-label="<?= __e('cases.pagination.next') ?>">›</a>
                <?php else: ?>
                <span class="case-page-btn is-disabled" aria-disabled="true">›</span>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
        </div>
    </div>
    <div class="panel case-list-panel">
        <div class="case-list-head">
            <div class="case-list-title">
                <h2><?= __e('client.documents.invoices') ?></h2>
            </div>
        </div>
        <div class="list-stack client-doc-list-stack">
            <?php foreach ($pageInvoices as $i): ?>
                <div class="list-item">
                    <strong><?= e($i['invoice_number']) ?> · <?= e(money($i['total'])) ?></strong>
                    <span class="muted"><?= e(t_content($i['title'])) ?> · <?= status_badge($i['status']) ?></span>
                    <div class="muted"><?= __e('form.issued') ?> <?= e(format_date($i['issued_at'])) ?> — <?= __e('client.documents.view_billing') ?>.</div>
                </div>
            <?php endforeach; ?>
            <?php if (!$pageInvoices): ?><div class="empty-state"><?= __e('finance.no_invoices') ?></div><?php endif; ?>
        </div>
        <div class="case-list-foot client-doc-list-foot">
            <p class="case-list-footer muted"><?= e(__($totalInvoices === 1 ? 'client.documents.invoices.pager.showing_one' : 'client.documents.invoices.pager.showing_many', ['from' => (int) $invShownFrom, 'to' => (int) $invShownTo, 'total' => (int) $totalInvoices])) ?></p>
            <?php if ($invTotalPages > 1): ?>
            <nav class="case-list-pager" aria-label="<?= __e('client.documents.invoices.pagination.aria') ?>">
                <?php if ($invPage > 1): ?>
                <a class="case-page-btn" href="<?= e($invoicesPagerUrl($invPage - 1)) ?>" aria-label="<?= __e('cases.pagination.prev') ?>">‹</a>
                <?php else: ?>
                <span class="case-page-btn is-disabled" aria-disabled="true">‹</span>
                <?php endif; ?>
                <?php for ($p = 1; $p <= $invTotalPages; $p++): ?>
                <a class="case-page-btn<?= $p === $invPage ? ' is-active' : '' ?>" href="<?= e($invoicesPagerUrl($p)) ?>"<?= $p === $invPage ? ' aria-current="page"' : '' ?>><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($invPage < $invTotalPages): ?>
                <a class="case-page-btn" href="<?= e($invoicesPagerUrl($invPage + 1)) ?>" aria-label="<?= __e('cases.pagination.next') ?>">›</a>
                <?php else: ?>
                <span class="case-page-btn is-disabled" aria-disabled="true">›</span>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
(function () {
    var input = document.getElementById('clientDocFile');
    var nameEl = document.getElementById('clientDocFileName');
    if (!input || !nameEl) return;
    var emptyLabel = <?= json_encode(__('client.documents.no_file_chosen')) ?>;
    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        nameEl.textContent = file ? file.name : emptyLabel;
    });
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
