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
    if ($fa === 'invoice') {
        $caseId = (int) post('case_id');
        $clientId = (int) post('client_id');
        $amount = (float) post('amount');
        $tax = (float) post('tax');
        $total = $amount + $tax;
        $number = generate_invoice_number($pdo);
        $pdo->prepare('INSERT INTO invoices (invoice_number, case_id, client_id, title, description, amount, tax, total, status, due_date, issued_at, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                $number, $caseId, $clientId, post('title'), post('description'),
                $amount, $tax, $total, post('status') ?: 'sent', post('due_date') ?: null, post('issued_at') ?: date('Y-m-d'), current_user()['id'],
            ]);
        create_notification($pdo, $clientId, 'New invoice', $number . ' issued for ' . money($total), 'payment', '../client/payments.php', current_user()['id']);
        flash('success', 'Invoice ' . $number . ' created for this case.');
        redirect('cases.php?action=view&id=' . $caseId . '#billing');
    }
    if ($fa === 'payment') {
        $caseId = (int) post('case_id');
        $receipt = generate_receipt_number($pdo);
        $invId = post('invoice_id') ? (int) post('invoice_id') : null;
        $pdo->prepare('INSERT INTO payments (invoice_id, client_id, amount, payment_method, reference_number, receipt_number, notes, paid_at, recorded_by) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([
                $invId, (int) post('client_id'), (float) post('amount'), post('payment_method'),
                post('reference_number'), $receipt, post('notes'), post('paid_at') ?: date('Y-m-d H:i:s'), current_user()['id'],
            ]);
        if ($invId) {
            $sumStmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=?');
            $sumStmt->execute([$invId]);
            $paid = (float) $sumStmt->fetchColumn();
            $inv = $pdo->prepare('SELECT total FROM invoices WHERE id=?');
            $inv->execute([$invId]);
            $total = (float) $inv->fetchColumn();
            $status = $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'sent');
            $pdo->prepare('UPDATE invoices SET status=? WHERE id=?')->execute([$status, $invId]);
        }
        flash('success', 'Payment recorded. Receipt ' . $receipt);
        redirect('cases.php?action=view&id=' . $caseId . '#billing');
    }
    if ($fa === 'delete') {
        $delId = (int) post('id');
        $pdo->prepare('DELETE FROM cases WHERE id=?')->execute([$delId]);
        flash('success', 'Case deleted.');
        log_activity($pdo, current_user()['id'], 'delete', 'case', $delId, 'Case deleted');
        redirect('cases.php');
    }
}

$clients = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role='client' ORDER BY first_name")->fetchAll();
$lawyers = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role='lawyer' AND is_active=1 ORDER BY first_name")->fetchAll();
$pageTitle = 'Cases';
$pageSubtitle = 'View and manage all legal cases';
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
    $invoices = $pdo->prepare('SELECT * FROM invoices WHERE case_id=? ORDER BY created_at DESC');
    $invoices->execute([$id]);
    $invoices = $invoices->fetchAll();
    $payments = $pdo->prepare('SELECT p.*, i.invoice_number FROM payments p JOIN invoices i ON i.id=p.invoice_id WHERE i.case_id=? ORDER BY p.paid_at DESC');
    $payments->execute([$id]);
    $payments = $payments->fetchAll();
    $billingTab = get('billing', '');
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
                <a class="btn btn-sm btn-accent" href="#billing">Invoices &amp; receipts</a>
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

    <div class="panel" id="billing">
        <div class="panel-header">
            <div>
                <h2>Invoices &amp; receipts</h2>
                <p class="muted" style="margin:0.25rem 0 0;">Billing for this case · Client <?= e($case['client_name']) ?></p>
            </div>
            <div class="quick-links">
                <a class="btn btn-sm btn-primary" href="?action=view&id=<?= $id ?>&billing=invoice#billing">Create invoice</a>
                <a class="btn btn-sm btn-accent" href="?action=view&id=<?= $id ?>&billing=payment#billing">Record payment</a>
            </div>
        </div>

        <?php if ($billingTab === 'invoice'): ?>
        <form method="post" class="form-grid entity-inline-form" style="margin-bottom:1.25rem;">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="invoice">
            <input type="hidden" name="case_id" value="<?= $id ?>">
            <input type="hidden" name="client_id" value="<?= (int)$case['client_id'] ?>">
            <div class="form-group full"><label>Title</label><input name="title" required placeholder="e.g. Legal fees — <?= e($case['case_number']) ?>" value="Legal fees — <?= e($case['case_number']) ?>"></div>
            <div class="form-group"><label>Amount (₹)</label><input type="number" step="0.01" name="amount" required></div>
            <div class="form-group"><label>Tax (₹)</label><input type="number" step="0.01" name="tax" value="0"></div>
            <div class="form-group"><label>Status</label><select name="status"><?php foreach (['draft','sent','partial','paid','overdue'] as $s): ?><option value="<?= $s ?>" <?= $s === 'sent' ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Due date</label><input type="date" name="due_date"></div>
            <div class="form-group full"><label>Description</label><textarea name="description" rows="2" placeholder="Line items or notes…"></textarea></div>
            <div class="form-actions full">
                <button class="btn btn-primary" type="submit">Save invoice</button>
                <a class="btn btn-secondary" href="?action=view&id=<?= $id ?>#billing">Cancel</a>
            </div>
        </form>
        <?php elseif ($billingTab === 'payment'): ?>
        <form method="post" class="form-grid entity-inline-form" style="margin-bottom:1.25rem;">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="payment">
            <input type="hidden" name="case_id" value="<?= $id ?>">
            <input type="hidden" name="client_id" value="<?= (int)$case['client_id'] ?>">
            <div class="form-group"><label>Invoice</label>
                <select name="invoice_id">
                    <option value="">— No invoice —</option>
                    <?php foreach ($invoices as $i): ?>
                        <option value="<?= (int)$i['id'] ?>"><?= e($i['invoice_number'] . ' · ' . money($i['total']) . ' · ' . $i['status']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Amount (₹)</label><input type="number" step="0.01" name="amount" required></div>
            <div class="form-group"><label>Method</label><select name="payment_method"><?php foreach (['bank_transfer','card','cash','cheque','online','other'] as $m): ?><option value="<?= $m ?>"><?= ucwords(str_replace('_',' ',$m)) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Reference</label><input name="reference_number" placeholder="UTR / cheque no."></div>
            <div class="form-group"><label>Paid at</label><input type="datetime-local" name="paid_at" value="<?= date('Y-m-d\TH:i') ?>"></div>
            <div class="form-group full"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
            <div class="form-actions full">
                <button class="btn btn-accent" type="submit">Record &amp; generate receipt</button>
                <a class="btn btn-secondary" href="?action=view&id=<?= $id ?>#billing">Cancel</a>
            </div>
        </form>
        <?php endif; ?>

        <div class="grid grid-2">
            <div>
                <h3 class="billing-subhead">Invoices</h3>
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
                        <?php if (!$invoices): ?><tr><td colspan="4" class="muted">No invoices for this case yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div>
                <h3 class="billing-subhead">Receipts / payments</h3>
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
                        <?php if (!$payments): ?><tr><td colspan="4" class="muted">No receipts yet. Record a payment to generate one.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
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

$search = trim(get('q', ''));
$allCases = $cases;
if ($search !== '') {
    $needle = strtolower($search);
    $cases = array_values(array_filter($cases, static fn($c) =>
        str_contains(strtolower($c['case_number']), $needle)
        || str_contains(strtolower($c['title']), $needle)
        || str_contains(strtolower($c['client_name']), $needle)
        || str_contains(strtolower($c['company_name'] ?? ''), $needle)
        || str_contains(strtolower($c['case_type'] ?? ''), $needle)
    ));
}
$shownCases = count($cases);
$totalCases = count($allCases);

require __DIR__ . '/../includes/header.php';
?>
<div class="panel case-list-panel">
    <div class="case-list-head">
        <h2>Case Management</h2>
        <a class="btn btn-case-new" href="?action=create">+ New Case</a>
    </div>

    <form class="case-list-search" method="get" action="cases.php">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search by case #, title, client…" aria-label="Search cases">
    </form>

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
                <?php
                $subtitle = $c['company_name'] ?: ($c['description'] ? mb_strimwidth($c['description'], 0, 48, '…') : ucfirst(str_replace('_', ' ', $c['status'])));
                ?>
                <tr>
                    <td class="case-num-cell">
                        <a class="case-num-link" href="?action=view&id=<?= (int)$c['id'] ?>"><?= e($c['case_number']) ?></a>
                    </td>
                    <td class="case-title-cell">
                        <strong><?= e($c['title']) ?></strong>
                        <span class="muted"><?= e($subtitle) ?></span>
                    </td>
                    <td><?= e($c['client_name']) ?></td>
                    <td><?= e($c['case_type'] ?: '—') ?></td>
                    <td class="case-fee-cell"><?= (float)$c['fee_total'] > 0 ? e(money($c['fee_total'])) : '—' ?></td>
                    <td class="col-actions">
                        <div class="case-row-actions">
                            <a class="btn btn-row-open btn-sm" href="?action=view&id=<?= (int)$c['id'] ?>">Open</a>
                            <form method="post" onsubmit="return confirm('Delete this case and all related records?')">
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
                <tr>
                    <td colspan="6" class="case-empty">No cases found<?= $search !== '' ? ' for “' . e($search) . '”' : '' ?>.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p class="case-list-footer muted">Showing <?= (int)$shownCases ?> of <?= (int)$totalCases ?> case<?= $totalCases === 1 ? '' : 's' ?></p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
