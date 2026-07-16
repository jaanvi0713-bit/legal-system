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
        redirect('cases.php?action=view&id=' . (int) post('case_id') . '&tab=documents');
    }
    if ($fa === 'note') {
        $pdo->prepare('INSERT INTO case_notes (case_id, user_id, note, is_private) VALUES (?,?,?,?)')
            ->execute([(int) post('case_id'), current_user()['id'], post('note'), (int) (post('is_private') === '1')]);
        flash('success', 'Note added.');
        redirect('cases.php?action=view&id=' . (int) post('case_id') . '&tab=notes');
    }
    if ($fa === 'reopen') {
        $pdo->prepare('UPDATE cases SET status="reopened", closed_at=NULL WHERE id=?')->execute([(int) post('id')]);
        flash('success', 'Case reopened.');
        redirect('cases.php?action=view&id=' . (int) post('id') . '&tab=overview');
    }
    if ($fa === 'invoice' || $fa === 'quotation') {
        $caseId = (int) post('case_id');
        $clientId = (int) post('client_id');
        $amount = (float) post('amount');
        $tax = (float) post('tax');
        $total = $amount + $tax;
        $number = generate_invoice_number($pdo);
        $status = $fa === 'quotation' ? 'draft' : (post('status') ?: 'sent');
        $pdo->prepare('INSERT INTO invoices (invoice_number, case_id, client_id, title, description, amount, tax, total, status, due_date, issued_at, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                $number, $caseId, $clientId, post('title'), post('description'),
                $amount, $tax, $total, $status, post('due_date') ?: null, post('issued_at') ?: date('Y-m-d'), current_user()['id'],
            ]);
        if ($status !== 'draft') {
            create_notification($pdo, $clientId, 'New invoice', $number . ' issued for ' . money($total), 'payment', '../client/payments.php', current_user()['id']);
        }
        flash('success', ($fa === 'quotation' ? 'Quotation' : 'Invoice') . ' ' . $number . ' created.');
        redirect('cases.php?action=view&id=' . $caseId . '&tab=' . ($fa === 'quotation' ? 'quotations' : 'invoices'));
    }
    if ($fa === 'payment') {
        $caseId = (int) post('case_id');
        $invId = (int) post('invoice_id');
        if (!$invId) {
            flash('error', 'Select an invoice before recording a payment.');
            redirect('cases.php?action=view&id=' . $caseId . '&tab=receipts&compose=payment');
        }
        $receipt = generate_receipt_number($pdo);
        $pdo->prepare('INSERT INTO payments (invoice_id, client_id, amount, payment_method, reference_number, receipt_number, notes, paid_at, recorded_by) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([
                $invId, (int) post('client_id'), (float) post('amount'), post('payment_method'),
                post('reference_number'), $receipt, post('notes'), post('paid_at') ?: date('Y-m-d H:i:s'), current_user()['id'],
            ]);
        $sumStmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=?');
        $sumStmt->execute([$invId]);
        $paid = (float) $sumStmt->fetchColumn();
        $inv = $pdo->prepare('SELECT total FROM invoices WHERE id=?');
        $inv->execute([$invId]);
        $total = (float) $inv->fetchColumn();
        $status = $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'sent');
        $pdo->prepare('UPDATE invoices SET status=? WHERE id=?')->execute([$status, $invId]);
        flash('success', 'Payment recorded. Receipt ' . $receipt);
        redirect('cases.php?action=view&id=' . $caseId . '&tab=receipts');
    }
    if ($fa === 'delete') {
        $delId = (int) post('id');
        $pdo->prepare('DELETE FROM cases WHERE id=?')->execute([$delId]);
        flash('success', 'Case deleted.');
        redirect('cases.php');
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
    $isEdit = (bool) $id;
    ?>
    <div class="entity-form-wrap">
    <div class="entity-form panel">
        <div class="entity-form-hero">
            <div>
                <p class="entity-form-eyebrow"><?= $isEdit ? 'Case record' : 'New matter' ?></p>
                <h2><?= $isEdit ? 'Edit case' : 'Open case' ?></h2>
                <p class="muted"><?= $isEdit ? 'Update parties, court details, and case status.' : 'Open a new matter and assign client and lawyer.' ?></p>
            </div>
            <p class="entity-form-required-note"><span class="req">*</span> Required fields</p>
        </div>
        <form method="post">
            <div class="entity-form-body">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="save">
                <input type="hidden" name="id" value="<?= (int)$case['id'] ?>">

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3>Case details</h3>
                        <p>Title, type, priority, and status for this matter.</p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="title">Title <span class="req">*</span></label>
                            <input id="title" name="title" required value="<?= e($case['title']) ?>" placeholder="Short case title">
                        </div>
                        <div class="form-group">
                            <label for="case_type">Type</label>
                            <input id="case_type" name="case_type" value="<?= e($case['case_type']) ?>" placeholder="e.g. Commercial">
                        </div>
                        <div class="form-group">
                            <label for="priority">Priority <span class="req">*</span></label>
                            <select id="priority" name="priority" required>
                                <?php foreach (['low','medium','high','urgent'] as $p): ?>
                                    <option value="<?= $p ?>" <?= $case['priority']===$p?'selected':'' ?>><?= ucfirst($p) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status">Status <span class="req">*</span></label>
                            <select id="status" name="status" required>
                                <?php foreach (['open','active','pending','on_hold','closed','reopened'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $case['status']===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group full">
                            <label for="description">Description / contract notes</label>
                            <textarea id="description" name="description" rows="3" placeholder="Summary, contract notes, or intake details…"><?= e($case['description']) ?></textarea>
                        </div>
                    </div>
                </section>

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3>Parties</h3>
                        <p>Client and assigned lawyer for this case.</p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="client_id">Client <span class="req">*</span></label>
                            <select id="client_id" name="client_id" required>
                                <option value="">Select…</option>
                                <?php foreach ($clients as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= (int)$case['client_id']===(int)$c['id']?'selected':'' ?>><?= e(full_name($c)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="lawyer_id">Lawyer</label>
                            <select id="lawyer_id" name="lawyer_id">
                                <option value="">Unassigned</option>
                                <?php foreach ($lawyers as $l): ?>
                                    <option value="<?= (int)$l['id'] ?>" <?= (int)$case['lawyer_id']===(int)$l['id']?'selected':'' ?>><?= e(full_name($l)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3>Court &amp; dates</h3>
                        <p>Filing and hearing schedule information.</p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="court_name">Court name</label>
                            <input id="court_name" name="court_name" value="<?= e($case['court_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="court_location">Court location</label>
                            <input id="court_location" name="court_location" value="<?= e($case['court_location']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="filing_date">Filing date</label>
                            <input id="filing_date" type="date" name="filing_date" value="<?= e($case['filing_date']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="next_hearing_date">Next hearing</label>
                            <input id="next_hearing_date" type="date" name="next_hearing_date" value="<?= e($case['next_hearing_date']) ?>">
                        </div>
                    </div>
                </section>
            </div>
            <div class="entity-form-footer">
                <a class="btn btn-secondary" href="cases.php">Back to cases</a>
                <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save changes' : 'Open case' ?></button>
            </div>
        </form>
    </div>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

if ($action === 'view' && $id) {
    $stmt = $pdo->prepare('SELECT c.*, cl.first_name AS client_first, cl.last_name AS client_last, cl.email AS client_email, cl.company_name AS client_company, cl.phone AS client_phone, CONCAT(lw.first_name," ",lw.last_name) AS lawyer_name, CONCAT(cb.first_name," ",cb.last_name) AS created_by_name FROM cases c JOIN users cl ON cl.id=c.client_id LEFT JOIN users lw ON lw.id=c.lawyer_id LEFT JOIN users cb ON cb.id=c.created_by WHERE c.id=?');
    $stmt->execute([$id]);
    $case = $stmt->fetch();
    if (!$case) { flash('error', 'Case not found.'); redirect('cases.php'); }
    $clientName = trim(($case['client_first'] ?? '') . ' ' . ($case['client_last'] ?? ''));

    $notes = $pdo->prepare('SELECT n.*, CONCAT(u.first_name," ",u.last_name) AS author FROM case_notes n JOIN users u ON u.id=n.user_id WHERE n.case_id=? ORDER BY n.created_at DESC');
    $notes->execute([$id]);
    $notes = $notes->fetchAll();
    $docs = $pdo->prepare('SELECT * FROM case_documents WHERE case_id=? ORDER BY created_at DESC');
    $docs->execute([$id]);
    $docs = $docs->fetchAll();
    $hearings = $pdo->prepare('SELECT * FROM court_hearings WHERE case_id=? ORDER BY hearing_date DESC');
    $hearings->execute([$id]);
    $hearings = $hearings->fetchAll();
    $invoicesAll = $pdo->prepare('SELECT * FROM invoices WHERE case_id=? ORDER BY created_at DESC');
    $invoicesAll->execute([$id]);
    $invoicesAll = $invoicesAll->fetchAll();
    $quotations = array_values(array_filter($invoicesAll, static fn($i) => $i['status'] === 'draft'));
    $invoices = array_values(array_filter($invoicesAll, static fn($i) => $i['status'] !== 'draft'));
    $payments = $pdo->prepare('SELECT p.*, i.invoice_number FROM payments p LEFT JOIN invoices i ON i.id=p.invoice_id WHERE i.case_id=? ORDER BY p.paid_at DESC');
    $payments->execute([$id]);
    $payments = $payments->fetchAll();
    $feeTotal = array_sum(array_map(static fn($i) => (float) $i['total'], $invoicesAll));
    $paidTotal = array_sum(array_map(static fn($p) => (float) $p['amount'], $payments));

    $activity = [];
    foreach ($docs as $d) {
        $activity[] = ['type' => 'document', 'title' => 'Document uploaded', 'ref' => $d['title'], 'at' => $d['created_at']];
    }
    foreach ($invoicesAll as $i) {
        $activity[] = ['type' => $i['status'] === 'draft' ? 'quote' : 'invoice', 'title' => $i['status'] === 'draft' ? 'Quotation created' : 'Invoice generated', 'ref' => $i['invoice_number'], 'at' => $i['created_at']];
    }
    foreach ($payments as $p) {
        $activity[] = ['type' => 'payment', 'title' => 'Payment received', 'ref' => $p['receipt_number'], 'at' => $p['paid_at']];
    }
    foreach ($notes as $n) {
        $activity[] = ['type' => 'note', 'title' => 'Note added', 'ref' => $n['author'], 'at' => $n['created_at']];
    }
    foreach ($hearings as $h) {
        $activity[] = ['type' => 'hearing', 'title' => 'Hearing scheduled', 'ref' => $h['court_name'] ?: 'Court', 'at' => $h['hearing_date']];
    }
    $activity[] = ['type' => 'case', 'title' => 'Case created', 'ref' => $case['case_number'], 'at' => $case['created_at']];
    usort($activity, static fn($a, $b) => strtotime($b['at']) <=> strtotime($a['at']));
    $activity = array_slice($activity, 0, 12);

    $tabs = [
        'overview' => 'Overview',
        'documents' => 'Documents',
        'quotations' => 'Quotations',
        'invoices' => 'Invoices',
        'receipts' => 'Receipts',
        'checklist' => 'Checklist',
        'deadlines' => 'Deadlines',
        'notes' => 'Notes',
        'activity' => 'Activity',
    ];
    $tab = get('tab', 'overview');
    if (!isset($tabs[$tab])) {
        $tab = 'overview';
    }
    $compose = get('compose', '');

    $checklist = [
        ['Client details complete', !empty($case['client_email'])],
        ['Lawyer assigned', !empty($case['lawyer_id'])],
        ['Case description added', !empty(trim((string) $case['description']))],
        ['At least one document uploaded', count($docs) > 0],
        ['Quotation or invoice issued', count($invoicesAll) > 0],
        ['Payment / receipt recorded', count($payments) > 0],
        ['Hearing / deadline set', !empty($case['next_hearing_date']) || count($hearings) > 0],
    ];

    $pageTitle = 'Cases';
    $pageSubtitle = 'Case details and billing';
    require __DIR__ . '/../includes/header.php';
    $tabUrl = static fn(string $t) => '?action=view&id=' . $id . '&tab=' . $t;
    ?>
    <div class="case-hub">
        <div class="case-hub-top">
            <a class="case-hub-back" href="cases.php">← Cases</a>
            <div class="case-hub-actions">
                <a class="btn btn-secondary btn-sm" href="?action=edit&id=<?= $id ?>">Edit</a>
                <details class="case-hub-menu">
                    <summary class="btn btn-case-new btn-sm">Quick Actions ▾</summary>
                    <div class="case-hub-menu-panel">
                        <a href="<?= e($tabUrl('quotations')) ?>&compose=quotation">New quotation</a>
                        <a href="<?= e($tabUrl('invoices')) ?>&compose=invoice">Create invoice</a>
                        <a href="<?= e($tabUrl('receipts')) ?>&compose=payment">Record payment / receipt</a>
                        <a href="<?= e($tabUrl('documents')) ?>">Upload document</a>
                        <a href="court.php?action=create&case_id=<?= $id ?>">Add hearing</a>
                        <?php if ($case['status'] === 'closed'): ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="reopen"><input type="hidden" name="id" value="<?= $id ?>"><button type="submit">Reopen case</button></form>
                        <?php endif; ?>
                    </div>
                </details>
            </div>
        </div>

        <div class="case-hub-title">
            <h1><?= e($case['case_number']) ?></h1>
            <p><?= e($case['title']) ?></p>
            <div class="case-hub-badges"><?= status_badge($case['status']) ?> <?= status_badge($case['priority']) ?></div>
        </div>

        <nav class="case-hub-tabs" aria-label="Case sections">
            <?php foreach ($tabs as $key => $label): ?>
                <a class="<?= $tab === $key ? 'active' : '' ?>" href="<?= e($tabUrl($key)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="case-hub-summary">
            <div class="case-hub-stat"><strong><?= count($docs) ?></strong><span>Documents</span></div>
            <div class="case-hub-stat"><strong><?= count($invoices) ?></strong><span>Invoices</span></div>
            <div class="case-hub-stat"><strong><?= count($payments) ?></strong><span>Receipts</span></div>
            <div class="case-hub-stat"><strong><?= count($quotations) ?></strong><span>Quotations</span></div>
        </div>

        <?php if ($tab === 'overview'): ?>
        <div class="case-hub-grid">
            <section class="panel case-hub-card">
                <h2>Case details</h2>
                <div class="case-hub-meta-grid">
                    <div>
                        <span class="case-hub-label">Client</span>
                        <strong><?= e($clientName) ?></strong>
                        <span class="muted"><?= e($case['client_company'] ?: 'Individual client') ?></span>
                    </div>
                    <div>
                        <span class="case-hub-label">Email</span>
                        <strong><?= e($case['client_email'] ?: '—') ?></strong>
                        <span class="muted"><?= e($case['client_phone'] ?: '') ?></span>
                    </div>
                    <div>
                        <span class="case-hub-label">Assigned lawyer</span>
                        <strong><?= e($case['lawyer_name'] ?: 'Unassigned') ?></strong>
                        <span class="muted"><?= e($case['created_by_name'] ? 'Opened by ' . $case['created_by_name'] : '') ?></span>
                    </div>
                </div>

                <div class="case-hub-service">
                    <h3>Service &amp; fees</h3>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Service</th><th>Net</th><th>Tax</th><th>Total</th></tr></thead>
                            <tbody>
                            <?php if ($invoicesAll): ?>
                                <?php foreach ($invoicesAll as $i): ?>
                                    <tr>
                                        <td><strong><?= e($i['title']) ?></strong><div class="muted"><?= e($i['invoice_number']) ?> · <?= e(ucfirst($i['status'])) ?></div></td>
                                        <td><?= e(money($i['amount'])) ?></td>
                                        <td><?= e(money($i['tax'])) ?></td>
                                        <td><?= e(money($i['total'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td><strong><?= e($case['case_type'] ?: 'Legal service') ?></strong><div class="muted">No invoice yet</div></td>
                                    <td>—</td><td>—</td><td>—</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="case-hub-total">
                        <span>Total fee</span>
                        <strong><?= e(money($feeTotal)) ?></strong>
                    </div>
                    <div class="case-hub-foot-meta">
                        <div><span class="case-hub-label">Created</span><?= e(format_datetime($case['created_at'])) ?></div>
                        <div><span class="case-hub-label">Last updated</span><?= e(format_datetime($case['updated_at'])) ?></div>
                        <div><span class="case-hub-label">Paid to date</span><?= e(money($paidTotal)) ?></div>
                    </div>
                </div>

                <?php if ($case['description']): ?>
                <div class="case-hub-desc">
                    <span class="case-hub-label">Description</span>
                    <p><?= nl2br(e($case['description'])) ?></p>
                </div>
                <?php endif; ?>
            </section>

            <aside class="panel case-hub-card">
                <h2>Recent activity</h2>
                <div class="case-hub-timeline">
                    <?php foreach ($activity as $a): ?>
                        <div class="case-hub-timeline-item type-<?= e($a['type']) ?>">
                            <strong><?= e($a['title']) ?></strong>
                            <span><?= e($a['ref']) ?></span>
                            <span class="muted"><?= e(format_datetime($a['at'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </aside>
        </div>

        <?php elseif ($tab === 'documents'): ?>
        <section class="panel case-hub-card">
            <div class="panel-header"><h2>Documents</h2></div>
            <form method="post" enctype="multipart/form-data" class="form-grid entity-inline-form" style="margin-bottom:1rem;">
                <?= csrf_field() ?><input type="hidden" name="form_action" value="upload"><input type="hidden" name="case_id" value="<?= $id ?>"><input type="hidden" name="client_id" value="<?= (int)$case['client_id'] ?>">
                <div class="form-group"><label>Title</label><input name="title" placeholder="Document title"></div>
                <div class="form-group"><label>Category</label>
                    <select name="category"><?php foreach (['legal','contract','evidence','court','other'] as $cat): ?><option value="<?= $cat ?>"><?= ucfirst($cat) ?></option><?php endforeach; ?></select>
                </div>
                <div class="form-group full"><label>File <span class="req">*</span></label><input type="file" name="document" required></div>
                <div class="form-actions full"><button class="btn btn-primary btn-sm" type="submit">Upload</button></div>
            </form>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Title</th><th>Category</th><th>Uploaded</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($docs as $d): ?>
                        <tr>
                            <td><strong><?= e($d['title']) ?></strong></td>
                            <td><?= e(ucfirst($d['category'])) ?></td>
                            <td><?= e(format_datetime($d['created_at'])) ?></td>
                            <td><a class="btn btn-secondary btn-sm" href="../<?= e($d['file_path']) ?>" target="_blank">Download</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$docs): ?><tr><td colspan="4" class="muted">No documents yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php elseif ($tab === 'quotations'): ?>
        <section class="panel case-hub-card">
            <div class="panel-header">
                <h2>Quotations</h2>
                <a class="btn btn-case-new btn-sm" href="<?= e($tabUrl('quotations')) ?>&compose=quotation">+ New quotation</a>
            </div>
            <p class="muted" style="margin-top:0;">Draft fee proposals before issuing a formal invoice.</p>
            <?php if ($compose === 'quotation'): ?>
            <form method="post" class="form-grid entity-inline-form" style="margin:1rem 0;">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="quotation">
                <input type="hidden" name="case_id" value="<?= $id ?>">
                <input type="hidden" name="client_id" value="<?= (int)$case['client_id'] ?>">
                <div class="form-group full"><label>Title <span class="req">*</span></label><input name="title" required value="Quotation — <?= e($case['case_number']) ?>"></div>
                <div class="form-group"><label>Amount (Rs) <span class="req">*</span></label><input type="number" step="0.01" name="amount" required></div>
                <div class="form-group"><label>Tax (Rs)</label><input type="number" step="0.01" name="tax" value="0"></div>
                <div class="form-group full"><label>Description</label><textarea name="description" rows="2" placeholder="Proposed services…"></textarea></div>
                <div class="form-actions full">
                    <button class="btn btn-primary" type="submit">Save quotation</button>
                    <a class="btn btn-secondary" href="<?= e($tabUrl('quotations')) ?>">Cancel</a>
                </div>
            </form>
            <?php endif; ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Quotation</th><th>Total</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php foreach ($quotations as $q): ?>
                        <tr>
                            <td><strong><?= e($q['invoice_number']) ?></strong><div class="muted"><?= e($q['title']) ?></div></td>
                            <td><?= e(money($q['total'])) ?></td>
                            <td><?= e(format_date($q['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$quotations): ?><tr><td colspan="3" class="muted">No quotations yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php elseif ($tab === 'invoices'): ?>
        <section class="panel case-hub-card">
            <div class="panel-header">
                <h2>Invoices</h2>
                <a class="btn btn-case-new btn-sm" href="<?= e($tabUrl('invoices')) ?>&compose=invoice">+ Create invoice</a>
            </div>
            <?php if ($compose === 'invoice'): ?>
            <form method="post" class="form-grid entity-inline-form" style="margin:1rem 0;">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="invoice">
                <input type="hidden" name="case_id" value="<?= $id ?>">
                <input type="hidden" name="client_id" value="<?= (int)$case['client_id'] ?>">
                <div class="form-group full"><label>Title <span class="req">*</span></label><input name="title" required value="Legal fees — <?= e($case['case_number']) ?>"></div>
                <div class="form-group"><label>Amount (Rs) <span class="req">*</span></label><input type="number" step="0.01" name="amount" required></div>
                <div class="form-group"><label>Tax (Rs)</label><input type="number" step="0.01" name="tax" value="0"></div>
                <div class="form-group"><label>Status</label><select name="status"><?php foreach (['sent','partial','paid','overdue'] as $s): ?><option value="<?= $s ?>"><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Due date</label><input type="date" name="due_date"></div>
                <div class="form-group full"><label>Description</label><textarea name="description" rows="2"></textarea></div>
                <div class="form-actions full">
                    <button class="btn btn-primary" type="submit">Save invoice</button>
                    <a class="btn btn-secondary" href="<?= e($tabUrl('invoices')) ?>">Cancel</a>
                </div>
            </form>
            <?php endif; ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Invoice</th><th>Total</th><th>Status</th><th>Due</th></tr></thead>
                    <tbody>
                    <?php foreach ($invoices as $i): ?>
                        <tr>
                            <td><strong><?= e($i['invoice_number']) ?></strong><div class="muted"><?= e($i['title']) ?></div></td>
                            <td><?= e(money($i['total'])) ?></td>
                            <td><?= status_badge($i['status']) ?></td>
                            <td><?= e(format_date($i['due_date'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$invoices): ?><tr><td colspan="4" class="muted">No invoices yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php elseif ($tab === 'receipts'): ?>
        <section class="panel case-hub-card">
            <div class="panel-header">
                <h2>Receipts &amp; payments</h2>
                <a class="btn btn-case-new btn-sm" href="<?= e($tabUrl('receipts')) ?>&compose=payment">+ Record payment</a>
            </div>
            <?php if ($compose === 'payment'): ?>
            <form method="post" class="form-grid entity-inline-form" style="margin:1rem 0;">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="payment">
                <input type="hidden" name="case_id" value="<?= $id ?>">
                <input type="hidden" name="client_id" value="<?= (int)$case['client_id'] ?>">
                <div class="form-group"><label>Invoice <span class="req">*</span></label>
                    <select name="invoice_id" required>
                        <option value="">Select invoice…</option>
                        <?php foreach ($invoices as $i): ?>
                            <option value="<?= (int)$i['id'] ?>"><?= e($i['invoice_number'] . ' · ' . money($i['total'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$invoices): ?><p class="field-hint">Create an invoice first, then record the payment receipt.</p><?php endif; ?>
                </div>
                <div class="form-group"><label>Amount (Rs) <span class="req">*</span></label><input type="number" step="0.01" name="amount" required></div>
                <div class="form-group"><label>Method</label><select name="payment_method"><?php foreach (['bank_transfer','card','cash','cheque','online','other'] as $m): ?><option value="<?= $m ?>"><?= ucwords(str_replace('_',' ',$m)) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Reference</label><input name="reference_number"></div>
                <div class="form-group"><label>Paid at</label><input type="datetime-local" name="paid_at" value="<?= date('Y-m-d\TH:i') ?>"></div>
                <div class="form-group full"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
                <div class="form-actions full">
                    <button class="btn btn-primary" type="submit">Record &amp; generate receipt</button>
                    <a class="btn btn-secondary" href="<?= e($tabUrl('receipts')) ?>">Cancel</a>
                </div>
            </form>
            <?php endif; ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Receipt</th><th>Amount</th><th>Invoice</th><th>Paid</th></tr></thead>
                    <tbody>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><strong><?= e($p['receipt_number']) ?></strong></td>
                            <td><?= e(money($p['amount'])) ?></td>
                            <td><?= e($p['invoice_number'] ?: '—') ?></td>
                            <td><?= e(format_datetime($p['paid_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$payments): ?><tr><td colspan="4" class="muted">No receipts yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php elseif ($tab === 'checklist'): ?>
        <section class="panel case-hub-card">
            <h2>Case checklist</h2>
            <div class="case-hub-checklist">
                <?php foreach ($checklist as [$label, $done]): ?>
                    <div class="case-hub-check <?= $done ? 'is-done' : '' ?>">
                        <span class="case-hub-check-mark"><?= $done ? '✓' : '○' ?></span>
                        <span><?= e($label) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <?php elseif ($tab === 'deadlines'): ?>
        <section class="panel case-hub-card">
            <div class="panel-header"><h2>Deadlines &amp; hearings</h2><a class="btn btn-secondary btn-sm" href="court.php?action=create&case_id=<?= $id ?>">Add hearing</a></div>
            <div class="list-item" style="margin-bottom:1rem;"><strong>Next hearing</strong><?= e(format_date($case['next_hearing_date'])) ?></div>
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
                    <?php if (!$hearings): ?><tr><td colspan="5" class="muted">No hearings recorded.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php elseif ($tab === 'notes'): ?>
        <section class="panel case-hub-card">
            <h2>Notes</h2>
            <form method="post" class="form-grid entity-inline-form" style="margin-bottom:1rem;">
                <?= csrf_field() ?><input type="hidden" name="form_action" value="note"><input type="hidden" name="case_id" value="<?= $id ?>">
                <div class="form-group full"><textarea name="note" required placeholder="Add progress note…"></textarea></div>
                <div class="form-group"><label><input type="checkbox" name="is_private" value="1"> Private note</label></div>
                <div class="form-group"><button class="btn btn-primary btn-sm" type="submit">Add note</button></div>
            </form>
            <div class="list-stack">
                <?php foreach ($notes as $n): ?>
                    <div class="list-item"><strong><?= e($n['author']) ?><?= $n['is_private'] ? ' (private)' : '' ?></strong><span class="muted"><?= e(format_datetime($n['created_at'])) ?></span><div><?= nl2br(e($n['note'])) ?></div></div>
                <?php endforeach; ?>
                <?php if (!$notes): ?><div class="empty-state">No notes yet.</div><?php endif; ?>
            </div>
        </section>

        <?php else: ?>
        <section class="panel case-hub-card">
            <h2>Activity</h2>
            <div class="case-hub-timeline">
                <?php foreach ($activity as $a): ?>
                    <div class="case-hub-timeline-item type-<?= e($a['type']) ?>">
                        <strong><?= e($a['title']) ?></strong>
                        <span><?= e($a['ref']) ?></span>
                        <span class="muted"><?= e(format_datetime($a['at'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

$cases = $pdo->query("
    SELECT c.*,
        CONCAT(cl.first_name,' ',cl.last_name) AS client_name,
        cl.company_name,
        CONCAT(lw.first_name,' ',lw.last_name) AS lawyer_name,
        COALESCE((SELECT SUM(i.total) FROM invoices i WHERE i.case_id = c.id), 0) AS fee_total
    FROM cases c
    JOIN users cl ON cl.id = c.client_id
    LEFT JOIN users lw ON lw.id = c.lawyer_id
    ORDER BY c.updated_at DESC
")->fetchAll();
$pageTitle = 'Cases';
$pageSubtitle = 'View and manage all legal cases';
require __DIR__ . '/../includes/header.php';
$totalCases = count($cases);
?>
<div class="panel case-list-panel">
    <div class="case-list-head">
        <h2>Case Management</h2>
        <a class="btn btn-case-new" href="?action=create">+ Open case</a>
    </div>
    <div class="table-wrap case-table-wrap">
        <table class="case-table">
            <thead>
                <tr>
                    <th>Case #</th>
                    <th>Title</th>
                    <th>Client</th>
                    <th>Service</th>
                    <th>Fee</th>
                    <th class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cases as $c): ?>
                <tr>
                    <td class="case-num-cell"><a class="case-num-link" href="?action=view&id=<?= (int)$c['id'] ?>"><?= e($c['case_number']) ?></a></td>
                    <td class="case-title-cell">
                        <strong><?= e($c['title']) ?></strong>
                        <span class="muted"><?= e($c['company_name'] ?: ucfirst(str_replace('_', ' ', $c['status']))) ?></span>
                    </td>
                    <td><?= e($c['client_name']) ?></td>
                    <td><?= e($c['case_type'] ?: '—') ?></td>
                    <td class="case-fee-cell"><?= (float)$c['fee_total'] > 0 ? e(money($c['fee_total'])) : '—' ?></td>
                    <td class="col-actions">
                        <div class="case-row-actions">
                            <a class="btn btn-row-open btn-sm" href="?action=view&id=<?= (int)$c['id'] ?>">Open</a>
                            <form method="post" onsubmit="return confirm('Delete this case?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="form_action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <button class="btn btn-row-delete btn-sm" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$cases): ?>
                <tr><td colspan="6" class="case-empty">No cases yet. Click <strong>Open case</strong> to create one.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="case-list-footer muted">Showing <?= (int)$totalCases ?> of <?= (int)$totalCases ?> case<?= $totalCases === 1 ? '' : 's' ?></p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
