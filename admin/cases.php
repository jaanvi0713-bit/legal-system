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
        ensure_invoice_bank_column($pdo);
        $caseId = (int) post('case_id');
        $clientId = (int) post('client_id');
        $amount = (float) post('amount');
        $tax = (float) post('tax');
        $total = $amount + $tax;
        $number = generate_invoice_number($pdo);
        $status = $fa === 'quotation' ? 'draft' : (post('status') ?: 'sent');
        $bankAccountId = (int) post('bank_account_id');
        if ($bankAccountId < 1 || $bankAccountId > 3 || !get_bank_account($pdo, $bankAccountId)) {
            $configured = get_configured_bank_accounts($pdo);
            $bankAccountId = $configured ? (int) array_key_first($configured) : null;
        }
        $pdo->prepare('INSERT INTO invoices (invoice_number, case_id, client_id, title, description, amount, tax, total, status, due_date, issued_at, created_by, bank_account_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                $number, $caseId, $clientId, post('title'), post('description'),
                $amount, $tax, $total, $status, post('due_date') ?: null, post('issued_at') ?: date('Y-m-d'), current_user()['id'], $bankAccountId,
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
$pageSubtitle = __('page.cases.subtitle');
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
                <p class="entity-form-eyebrow"><?= $isEdit ? __e('cases.eyebrow.edit') : __e('cases.eyebrow.create') ?></p>
                <h2><?= $isEdit ? __e('cases.edit') : __e('cases.save_open') ?></h2>
                <p class="muted"><?= $isEdit ? __e('cases.form.help.edit') : __e('cases.form.help.create') ?></p>
            </div>
            <p class="entity-form-required-note"><span class="req">*</span> <?= __e('form.required_fields') ?></p>
        </div>
        <form method="post">
            <div class="entity-form-body">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="save">
                <input type="hidden" name="id" value="<?= (int)$case['id'] ?>">

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3><?= __e('form.section.case_details') ?></h3>
                        <p><?= __e('form.section.case_details_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="title"><?= __e('common.title') ?> <span class="req">*</span></label>
                            <input id="title" name="title" required value="<?= e($case['title']) ?>" placeholder="<?= __e('form.placeholder.case_title') ?>">
                        </div>
                        <div class="entity-field-row">
                            <div class="form-group">
                                <label for="case_type"><?= __e('common.type') ?></label>
                                <input id="case_type" name="case_type" value="<?= e($case['case_type']) ?>" placeholder="<?= __e('form.placeholder.case_type') ?>">
                            </div>
                            <div class="form-group">
                                <label for="priority"><?= __e('common.priority') ?> <span class="req">*</span></label>
                                <select id="priority" name="priority" required>
                                    <?php foreach (['low','medium','high','urgent'] as $p): ?>
                                        <option value="<?= $p ?>" <?= $case['priority']===$p?'selected':'' ?>><?= e(translate_status($p)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status"><?= __e('common.status') ?> <span class="req">*</span></label>
                                <select id="status" name="status" required>
                                    <?php foreach (['open','active','pending','on_hold','closed','reopened'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $case['status']===$s?'selected':'' ?>><?= e(translate_status($s)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group full<?= !$isEdit ? ' instruction-field' : '' ?>">
                            <label for="description"><?= __e('form.description_contract') ?></label>
                            <textarea id="description" name="description" rows="3" placeholder="<?= __e('form.placeholder.case_description') ?>"><?= e($case['description']) ?></textarea>
                        </div>
                    </div>
                </section>

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3><?= __e('form.section.parties') ?></h3>
                        <p><?= __e('form.section.parties_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group">
                                <label for="client_id"><?= __e('common.client') ?> <span class="req">*</span></label>
                                <select id="client_id" name="client_id" required>
                                    <option value=""><?= __e('form.select') ?></option>
                                    <?php foreach ($clients as $c): ?>
                                        <option value="<?= (int)$c['id'] ?>" <?= (int)$case['client_id']===(int)$c['id']?'selected':'' ?>><?= e(full_name($c)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="lawyer_id"><?= __e('common.lawyer') ?></label>
                                <select id="lawyer_id" name="lawyer_id">
                                    <option value=""><?= __e('form.unassigned_simple') ?></option>
                                    <?php foreach ($lawyers as $l): ?>
                                        <option value="<?= (int)$l['id'] ?>" <?= (int)$case['lawyer_id']===(int)$l['id']?'selected':'' ?>><?= e(full_name($l)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3><?= __e('form.section.court_dates') ?></h3>
                        <p><?= __e('form.section.court_dates_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group">
                                <label for="court_name"><?= __e('form.court_name') ?></label>
                                <input id="court_name" name="court_name" value="<?= e($case['court_name']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="court_location"><?= __e('form.court_location') ?></label>
                                <input id="court_location" name="court_location" value="<?= e($case['court_location']) ?>">
                            </div>
                        </div>
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group">
                                <label for="filing_date"><?= __e('form.filing_date') ?></label>
                                <input id="filing_date" type="date" name="filing_date" value="<?= e($case['filing_date']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="next_hearing_date"><?= __e('form.next_hearing') ?></label>
                                <input id="next_hearing_date" type="date" name="next_hearing_date" value="<?= e($case['next_hearing_date']) ?>">
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            <div class="entity-form-footer">
                <a class="btn btn-secondary" href="cases.php"><?= __e('common.cancel') ?></a>
                <button class="btn btn-primary" type="submit"><?= $isEdit ? __e('common.save_changes') : __e('cases.save_open') ?></button>
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
    if (!$case) { flash('error', __('flash.case.not_found')); redirect('cases.php'); }
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
        $activity[] = ['type' => 'document', 'title' => __('cases.activity.document'), 'ref' => $d['title'], 'at' => $d['created_at']];
    }
    foreach ($invoicesAll as $i) {
        $activity[] = ['type' => $i['status'] === 'draft' ? 'quote' : 'invoice', 'title' => __($i['status'] === 'draft' ? 'cases.activity.quotation' : 'cases.activity.invoice'), 'ref' => $i['invoice_number'], 'at' => $i['created_at']];
    }
    foreach ($payments as $p) {
        $activity[] = ['type' => 'payment', 'title' => __('cases.activity.payment'), 'ref' => $p['receipt_number'], 'at' => $p['paid_at']];
    }
    foreach ($notes as $n) {
        $activity[] = ['type' => 'note', 'title' => __('cases.activity.note'), 'ref' => $n['author'], 'at' => $n['created_at']];
    }
    foreach ($hearings as $h) {
        $activity[] = ['type' => 'hearing', 'title' => __('cases.activity.hearing'), 'ref' => $h['court_name'] ?: __('common.court'), 'at' => $h['hearing_date']];
    }
    $activity[] = ['type' => 'case', 'title' => __('cases.activity.created'), 'ref' => $case['case_number'], 'at' => $case['created_at']];
    usort($activity, static fn($a, $b) => strtotime($b['at']) <=> strtotime($a['at']));
    $activity = array_slice($activity, 0, 12);

    $tabs = [
        'overview' => __('cases.tab.overview'),
        'documents' => __('cases.tab.documents'),
        'quotations' => __('cases.tab.quotations'),
        'invoices' => __('cases.tab.invoices'),
        'receipts' => __('cases.tab.receipts'),
        'checklist' => __('cases.tab.checklist'),
        'deadlines' => __('cases.tab.deadlines'),
        'notes' => __('cases.tab.notes'),
        'activity' => __('cases.tab.activity'),
    ];
    $tab = get('tab', 'overview');
    if (!isset($tabs[$tab])) {
        $tab = 'overview';
    }
    $compose = get('compose', '');

    $checklist = [
        [__('cases.checklist.client_details'), !empty($case['client_email'])],
        [__('cases.checklist.lawyer_assigned'), !empty($case['lawyer_id'])],
        [__('cases.checklist.description'), !empty(trim((string) $case['description']))],
        [__('cases.checklist.document'), count($docs) > 0],
        [__('cases.checklist.invoice'), count($invoicesAll) > 0],
        [__('cases.checklist.payment'), count($payments) > 0],
        [__('cases.checklist.hearing'), !empty($case['next_hearing_date']) || count($hearings) > 0],
    ];

    $pageTitle = __('page.cases');
    $pageSubtitle = __('cases.hub.subtitle');
    require __DIR__ . '/../includes/header.php';
    $tabUrl = static fn(string $t) => '?action=view&id=' . $id . '&tab=' . $t;
    ?>
    <div class="case-hub">
        <div class="case-hub-top">
            <a class="case-hub-back" href="cases.php"><?= __e('cases.back') ?></a>
            <div class="case-hub-actions">
                <a class="btn btn-secondary btn-sm" href="?action=edit&id=<?= $id ?>"><?= __e('common.edit') ?></a>
                <details class="case-hub-menu">
                    <summary class="btn btn-primary btn-sm"><?= __e('cases.quick_actions') ?></summary>
                    <div class="case-hub-menu-panel">
                        <a href="<?= e($tabUrl('quotations')) ?>&compose=quotation"><?= __e('cases.action.new_quotation') ?></a>
                        <a href="invoice.php?action=generate&case_id=<?= $id ?>&client_id=<?= (int) $case['client_id'] ?>&from=<?= e(urlencode('cases.php?action=view&id=' . $id . '&tab=invoices')) ?>"><?= __e('cases.action.generate_invoice') ?></a>
                        <a href="<?= e($tabUrl('receipts')) ?>&compose=payment"><?= __e('cases.action.record_payment') ?></a>
                        <a href="<?= e($tabUrl('documents')) ?>"><?= __e('cases.action.upload_document') ?></a>
                        <a href="court.php?action=create&case_id=<?= $id ?>"><?= __e('cases.action.add_hearing') ?></a>
                        <?php if ($case['status'] === 'closed'): ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="reopen"><input type="hidden" name="id" value="<?= $id ?>"><button type="submit"><?= __e('cases.action.reopen_case') ?></button></form>
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

        <nav class="case-hub-tabs" aria-label="<?= __e('cases.hub.sections_aria') ?>">
            <?php foreach ($tabs as $key => $label): ?>
                <a class="<?= $tab === $key ? 'active' : '' ?>" href="<?= e($tabUrl($key)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="case-hub-summary">
            <div class="case-hub-stat"><strong><?= count($docs) ?></strong><span><?= __e('cases.tab.documents') ?></span></div>
            <div class="case-hub-stat"><strong><?= count($invoices) ?></strong><span><?= __e('cases.tab.invoices') ?></span></div>
            <div class="case-hub-stat"><strong><?= count($payments) ?></strong><span><?= __e('cases.tab.receipts') ?></span></div>
            <div class="case-hub-stat"><strong><?= count($quotations) ?></strong><span><?= __e('cases.tab.quotations') ?></span></div>
        </div>

        <?php if ($tab === 'overview'): ?>
        <div class="case-hub-grid">
            <section class="panel case-hub-card">
                <h2><?= __e('cases.hub.case_details') ?></h2>
                <div class="case-hub-meta-grid">
                    <div>
                        <span class="case-hub-label"><?= __e('common.client') ?></span>
                        <strong><?= e($clientName) ?></strong>
                        <span class="muted"><?= e($case['client_company'] ?: __('clients.individual')) ?></span>
                    </div>
                    <div>
                        <span class="case-hub-label"><?= __e('common.email') ?></span>
                        <strong><?= e($case['client_email'] ?: __('common.em_dash')) ?></strong>
                        <span class="muted"><?= e($case['client_phone'] ?: '') ?></span>
                    </div>
                    <div>
                        <span class="case-hub-label"><?= __e('cases.hub.assigned_lawyer') ?></span>
                        <strong><?= e($case['lawyer_name'] ?: __('common.unassigned')) ?></strong>
                        <span class="muted"><?= e($case['created_by_name'] ? __('cases.hub.opened_by', ['name' => $case['created_by_name']]) : '') ?></span>
                    </div>
                </div>

                <div class="case-hub-service">
                    <h3><?= __e('cases.hub.service_fees') ?></h3>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th><?= __e('common.service') ?></th><th><?= __e('common.net') ?></th><th><?= __e('common.tax') ?></th><th><?= __e('common.total') ?></th></tr></thead>
                            <tbody>
                            <?php if ($invoicesAll): ?>
                                <?php foreach ($invoicesAll as $i): ?>
                                    <tr>
                                        <td><strong><?= e($i['title']) ?></strong><div class="muted"><?= e($i['invoice_number']) ?> · <?= e(translate_status($i['status'])) ?></div></td>
                                        <td><?= e(money($i['amount'])) ?></td>
                                        <td><?= e(money($i['tax'])) ?></td>
                                        <td><?= e(money($i['total'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td><strong><?= e($case['case_type'] ?: __('cases.hub.legal_service')) ?></strong><div class="muted"><?= __e('cases.hub.no_invoice') ?></div></td>
                                    <td><?= __e('common.em_dash') ?></td><td><?= __e('common.em_dash') ?></td><td><?= __e('common.em_dash') ?></td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="case-hub-total">
                        <span><?= __e('cases.hub.total_fee') ?></span>
                        <strong><?= e(money($feeTotal)) ?></strong>
                    </div>
                    <div class="case-hub-foot-meta">
                        <div><span class="case-hub-label"><?= __e('common.created') ?></span><?= e(format_datetime($case['created_at'])) ?></div>
                        <div><span class="case-hub-label"><?= __e('common.last_updated') ?></span><?= e(format_datetime($case['updated_at'])) ?></div>
                        <div><span class="case-hub-label"><?= __e('cases.hub.paid_to_date') ?></span><?= e(money($paidTotal)) ?></div>
                    </div>
                </div>

                <div class="instruction-field instruction-field--display">
                    <span class="instruction-field-label"><?= __e('form.description_contract') ?></span>
                    <div class="instruction-field-body<?= trim((string) ($case['description'] ?? '')) === '' ? ' is-empty' : '' ?>">
                        <?php if (trim((string) ($case['description'] ?? '')) !== ''): ?>
                            <p><?= nl2br(e(t_content($case['description']))) ?></p>
                        <?php else: ?>
                            <p><?= __e('form.placeholder.case_description') ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <aside class="panel case-hub-card">
                <h2><?= __e('cases.hub.recent_activity') ?></h2>
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
            <div class="panel-header"><h2><?= __e('cases.tab.documents') ?></h2></div>
            <form method="post" enctype="multipart/form-data" class="form-grid entity-inline-form" style="margin-bottom:1rem;">
                <?= csrf_field() ?><input type="hidden" name="form_action" value="upload"><input type="hidden" name="case_id" value="<?= $id ?>"><input type="hidden" name="client_id" value="<?= (int)$case['client_id'] ?>">
                <div class="entity-field-row entity-field-row--2">
                    <div class="form-group"><label><?= __e('common.title') ?></label><input name="title" placeholder="<?= __e('form.placeholder.document_title') ?>"></div>
                    <div class="form-group"><label><?= __e('common.category') ?></label>
                        <select name="category"><?php foreach (['legal','contract','evidence','court','other'] as $cat): ?><option value="<?= $cat ?>"><?= e(__('doc.category.' . $cat)) ?></option><?php endforeach; ?></select>
                    </div>
                </div>
                <div class="form-group full"><label><?= __e('common.file') ?> <span class="req">*</span></label><input type="file" name="document" required></div>
                <div class="form-actions full"><button class="btn btn-primary btn-sm" type="submit"><?= __e('common.upload') ?></button></div>
            </form>
            <div class="table-wrap">
                <table>
                    <thead><tr><th><?= __e('common.title') ?></th><th><?= __e('common.category') ?></th><th><?= __e('cases.doc.uploaded') ?></th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($docs as $d): ?>
                        <tr>
                            <td><strong><?= e($d['title']) ?></strong></td>
                            <td><?= e(__('doc.category.' . ($d['category'] ?: 'other'))) ?></td>
                            <td><?= e(format_datetime($d['created_at'])) ?></td>
                            <td><a class="btn btn-secondary btn-sm" href="../<?= e($d['file_path']) ?>" target="_blank"><?= __e('common.download') ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$docs): ?><tr><td colspan="4" class="muted"><?= __e('cases.no_documents') ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php elseif ($tab === 'quotations'): ?>
        <section class="panel case-hub-card">
            <div class="panel-header">
                <h2><?= __e('cases.tab.quotations') ?></h2>
                <a class="btn btn-primary btn-sm" href="<?= e($tabUrl('quotations')) ?>&compose=quotation"><?= __e('cases.new_quotation') ?></a>
            </div>
            <p class="muted" style="margin-top:0;"><?= __e('cases.quotations_help') ?></p>
            <?php if ($compose === 'quotation'): ?>
            <form method="post" class="form-grid entity-inline-form" style="margin:1rem 0;">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="quotation">
                <input type="hidden" name="case_id" value="<?= $id ?>">
                <input type="hidden" name="client_id" value="<?= (int)$case['client_id'] ?>">
                <div class="form-group full"><label><?= __e('common.title') ?> <span class="req">*</span></label><input name="title" required value="<?= e(__('cases.quotation_title_default', ['number' => $case['case_number']])) ?>"></div>
                <div class="entity-field-row entity-field-row--2">
                    <div class="form-group"><label><?= __e('cases.amount_rs') ?> <span class="req">*</span></label><input type="number" step="0.01" name="amount" required></div>
                    <div class="form-group"><label><?= __e('cases.tax_rs') ?></label><input type="number" step="0.01" name="tax" value="0"></div>
                </div>
                <div class="form-group full"><label><?= __e('common.description') ?></label><textarea name="description" rows="2" placeholder="<?= __e('form.placeholder.proposed_services') ?>"></textarea></div>
                <div class="form-actions full">
                    <button class="btn btn-primary" type="submit"><?= __e('cases.save_quotation') ?></button>
                    <a class="btn btn-secondary" href="<?= e($tabUrl('quotations')) ?>"><?= __e('common.cancel') ?></a>
                </div>
            </form>
            <?php endif; ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th><?= __e('common.quotation') ?></th><th><?= __e('common.total') ?></th><th><?= __e('common.created') ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($quotations as $q): ?>
                        <tr>
                            <td><strong><?= e($q['invoice_number']) ?></strong><div class="muted"><?= e($q['title']) ?></div></td>
                            <td><?= e(money($q['total'])) ?></td>
                            <td><?= e(format_date($q['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$quotations): ?><tr><td colspan="3" class="muted"><?= __e('cases.no_quotations') ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php elseif ($tab === 'invoices'):
            $invQ = trim((string) get('q', ''));
            $invStatus = (string) get('status', '');
            $caseInvReturn = 'cases.php?action=view&id=' . $id . '&tab=invoices';
            $filteredInvoices = array_values(array_filter($invoices, static function ($i) use ($invQ, $invStatus) {
                if ($invStatus !== '' && $i['status'] !== $invStatus) {
                    return false;
                }
                if ($invQ === '') {
                    return true;
                }
                $hay = strtolower(($i['invoice_number'] ?? '') . ' ' . ($i['title'] ?? ''));
                return str_contains($hay, strtolower($invQ));
            }));
        ?>
        <section class="panel case-hub-card inv-list-panel">
            <div class="panel-header">
                <h2><?= __e('finance.invoices') ?></h2>
                <a class="btn btn-primary btn-sm" href="invoice.php?action=generate&case_id=<?= $id ?>&client_id=<?= (int) $case['client_id'] ?>&from=<?= e(urlencode($caseInvReturn)) ?>">+ <?= __e('finance.generate_invoice') ?></a>
            </div>

            <form method="get" class="inv-list-filters" action="cases.php">
                <input type="hidden" name="action" value="view">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="tab" value="invoices">
                <label class="inv-search">
                    <span class="inv-search-icon" aria-hidden="true">⌕</span>
                    <input type="search" name="q" value="<?= e($invQ) ?>" placeholder="<?= __e('finance.search_invoices') ?>">
                </label>
                <select name="status" onchange="this.form.submit()">
                    <option value=""><?= __e('finance.all_statuses') ?></option>
                    <?php foreach (['sent', 'partial', 'paid', 'overdue', 'cancelled'] as $s): ?>
                        <option value="<?= e($s) ?>" <?= $invStatus === $s ? 'selected' : '' ?>><?= e(translate_status($s)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($invQ !== '' || $invStatus !== ''): ?>
                    <a class="btn btn-secondary btn-sm" href="<?= e($tabUrl('invoices')) ?>"><?= __e('common.clear') ?></a>
                <?php endif; ?>
            </form>

            <div class="table-wrap">
                <table class="inv-list-table">
                    <thead>
                        <tr>
                            <th><?= __e('finance.invoice_number') ?></th>
                            <th><?= __e('common.amount') ?></th>
                            <th><?= __e('finance.due_date') ?></th>
                            <th><?= __e('common.status') ?></th>
                            <th class="col-actions"><?= __e('common.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($filteredInvoices as $i): ?>
                        <tr>
                            <td>
                                <strong><?= e($i['invoice_number']) ?></strong>
                                <div class="muted"><?= e($i['title']) ?></div>
                            </td>
                            <td><?= e(money($i['total'])) ?></td>
                            <td><?= e(format_date($i['due_date'], 'M j, Y')) ?></td>
                            <td><?= status_badge($i['status']) ?></td>
                            <td class="col-actions">
                                <div class="row-actions">
                                    <a class="btn btn-row-open btn-sm" href="invoice.php?id=<?= (int) $i['id'] ?>&from=<?= e(urlencode($caseInvReturn)) ?>"><?= __e('common.view') ?></a>
                                    <form method="post" action="invoice.php" data-confirm="<?= __e('finance.delete_confirm', ['number' => $i['invoice_number']]) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="form_action" value="delete_invoice">
                                        <input type="hidden" name="invoice_id" value="<?= (int) $i['id'] ?>">
                                        <input type="hidden" name="return_to" value="<?= e($caseInvReturn) ?>">
                                        <button class="btn btn-row-delete btn-sm" type="submit"><?= __e('common.delete') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$filteredInvoices): ?>
                        <tr><td colspan="5" class="muted"><?= __e('finance.no_invoices') ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php elseif ($tab === 'receipts'):
            $preselectPayInvoice = (int) get('invoice_id', 0);
        ?>
        <section class="panel case-hub-card">
            <div class="panel-header">
                <h2>Receipts</h2>
                <a class="btn btn-primary btn-sm" href="<?= e($tabUrl('receipts')) ?>&compose=payment">+ <?= __e('finance.record_payment') ?></a>
            </div>
            <?php if ($compose === 'payment'): ?>
            <form method="post" class="form-grid entity-inline-form" style="margin:1rem 0;">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="payment">
                <input type="hidden" name="case_id" value="<?= $id ?>">
                <input type="hidden" name="client_id" value="<?= (int)$case['client_id'] ?>">
                <div class="form-group full"><label><?= __e('finance.invoice_number') ?> <span class="req">*</span></label>
                    <select name="invoice_id" required>
                        <option value=""><?= __e('common.em_dash') ?></option>
                        <?php foreach ($invoices as $i): ?>
                            <option value="<?= (int)$i['id'] ?>" <?= $preselectPayInvoice === (int) $i['id'] ? 'selected' : '' ?>><?= e($i['invoice_number'] . ' · ' . money($i['total'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!$invoices): ?>
                <div class="form-group full entity-field-notes"><span class="field-hint">Generate an invoice first, then record the payment receipt.</span></div>
                <?php endif; ?>
                <div class="entity-field-row">
                    <div class="form-group"><label><?= __e('common.amount') ?> <span class="req">*</span></label><input type="number" step="0.01" name="amount" required></div>
                    <div class="form-group"><label><?= __e('finance.method') ?></label><select name="payment_method"><?php foreach (['bank_transfer','card','cash','cheque','online','other'] as $m): ?><option value="<?= $m ?>"><?= e(__('payment.method.' . $m)) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label><?= __e('form.paid_at') ?></label><input type="datetime-local" name="paid_at" value="<?= date('Y-m-d\TH:i') ?>"></div>
                </div>
                <div class="form-group full"><label><?= __e('common.reference') ?></label><input name="reference_number"></div>
                <div class="form-group full"><label><?= __e('common.notes') ?></label><textarea name="notes" rows="2"></textarea></div>
                <div class="form-actions full">
                    <button class="btn btn-primary" type="submit"><?= __e('finance.record_receipt_btn') ?></button>
                    <a class="btn btn-secondary" href="<?= e($tabUrl('receipts')) ?>"><?= __e('common.cancel') ?></a>
                </div>
            </form>
            <?php endif; ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?= __e('finance.receipt') ?></th>
                            <th><?= __e('common.amount') ?></th>
                            <th><?= __e('finance.method') ?></th>
                            <th><?= __e('finance.invoice_number') ?></th>
                            <th><?= __e('common.date') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td>
                                <strong><?= e($p['receipt_number']) ?></strong>
                                <?php if (!empty($p['reference_number'])): ?>
                                    <div class="muted"><?= e($p['reference_number']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e(money($p['amount'])) ?></td>
                            <td><?= e(__('payment.method.' . ($p['payment_method'] ?: 'other'))) ?></td>
                            <td><?= e($p['invoice_number'] ?: __('common.em_dash')) ?></td>
                            <td><?= e(format_datetime($p['paid_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$payments): ?>
                        <tr><td colspan="5" class="muted"><?= __e('dashboard.empty.no_payments') ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php elseif ($tab === 'checklist'): ?>
        <section class="panel case-hub-card">
            <h2><?= __e('cases.checklist_title') ?></h2>
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
            <div class="panel-header"><h2><?= __e('cases.deadlines_title') ?></h2><a class="btn btn-primary btn-sm" href="court.php?action=create&case_id=<?= $id ?>"><?= __e('cases.add_hearing') ?></a></div>
            <div class="list-item" style="margin-bottom:1rem;"><strong><?= __e('form.next_hearing') ?></strong><?= e(format_date($case['next_hearing_date'])) ?></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th><?= __e('common.date') ?></th><th><?= __e('common.court') ?></th><th><?= __e('common.type') ?></th><th><?= __e('common.status') ?></th><th><?= __e('common.outcome') ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($hearings as $h): ?>
                        <tr>
                            <td><?= e(format_datetime($h['hearing_date'])) ?></td>
                            <td><?= e($h['court_name']) ?><div class="muted"><?= e($h['court_location']) ?></div></td>
                            <td><?= e($h['hearing_type'] ?: __('common.em_dash')) ?></td>
                            <td><?= status_badge($h['status']) ?></td>
                            <td><?= e($h['outcome'] ?: __('common.em_dash')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$hearings): ?><tr><td colspan="5" class="muted"><?= __e('cases.no_hearings') ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php elseif ($tab === 'notes'): ?>
        <section class="panel case-hub-card">
            <h2><?= __e('cases.tab.notes') ?></h2>
            <form method="post" class="form-grid entity-inline-form" style="margin-bottom:1rem;">
                <?= csrf_field() ?><input type="hidden" name="form_action" value="note"><input type="hidden" name="case_id" value="<?= $id ?>">
                <div class="form-group full"><label><?= __e('cases.add_note') ?></label><textarea name="note" required rows="2" placeholder="<?= __e('cases.add_note_ph') ?>"></textarea></div>
                <div class="entity-field-row entity-field-row--2">
                    <div class="form-group"><label><input type="checkbox" name="is_private" value="1"> <?= __e('cases.private_note') ?></label></div>
                    <div class="form-group"><button class="btn btn-primary btn-sm" type="submit"><?= __e('cases.add_note') ?></button></div>
                </div>
            </form>
            <div class="list-stack">
                <?php foreach ($notes as $n): ?>
                    <div class="list-item"><strong><?= e($n['author']) ?><?= $n['is_private'] ? ' ' . __('cases.private_suffix') : '' ?></strong><span class="muted"><?= e(format_datetime($n['created_at'])) ?></span><div><?= nl2br(e($n['note'])) ?></div></div>
                <?php endforeach; ?>
                <?php if (!$notes): ?><div class="empty-state"><?= __e('cases.no_notes') ?></div><?php endif; ?>
            </div>
        </section>

        <?php else: ?>
        <section class="panel case-hub-card">
            <h2><?= __e('cases.tab.activity') ?></h2>
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

$pdo->exec("UPDATE cases SET case_number = REPLACE(case_number, 'LEX-', 'CASE-') WHERE case_number LIKE 'LEX-%'");

$filter = (string) get('filter', '');
$activeStatuses = ['open', 'active', 'pending', 'reopened', 'on_hold'];
$sql = "
    SELECT c.*,
        CONCAT(cl.first_name,' ',cl.last_name) AS client_name,
        cl.company_name,
        CONCAT(lw.first_name,' ',lw.last_name) AS lawyer_name,
        COALESCE((SELECT SUM(i.total) FROM invoices i WHERE i.case_id = c.id), 0) AS fee_total,
        COALESCE((SELECT SUM(i.total) FROM invoices i WHERE i.case_id = c.id AND i.status IN ('sent','partial','overdue')), 0) AS outstanding_total
    FROM cases c
    JOIN users cl ON cl.id = c.client_id
    LEFT JOIN users lw ON lw.id = c.lawyer_id
";
if ($filter === 'active') {
    $placeholders = implode(',', array_fill(0, count($activeStatuses), '?'));
    $sql .= " WHERE c.status IN ($placeholders)";
    $stmt = $pdo->prepare($sql . ' ORDER BY c.updated_at DESC');
    $stmt->execute($activeStatuses);
    $cases = $stmt->fetchAll();
} elseif ($filter === 'outstanding') {
    $sql .= " WHERE EXISTS (
        SELECT 1 FROM invoices i
        WHERE i.case_id = c.id AND i.status IN ('sent','partial','overdue')
    )";
    $cases = $pdo->query($sql . ' ORDER BY outstanding_total DESC, c.updated_at DESC')->fetchAll();
} else {
    $cases = $pdo->query($sql . ' ORDER BY c.updated_at DESC')->fetchAll();
}
$listTitle = $filter === 'active' ? __('cases.list.active') : ($filter === 'outstanding' ? __('cases.list.outstanding') : __('cases.list.all'));
$pageTitle = __('page.cases');
$pageSubtitle = $filter === 'active'
    ? __('cases.list.subtitle_active')
    : ($filter === 'outstanding' ? __('cases.list.subtitle_outstanding') : __('cases.list.subtitle_all'));
require __DIR__ . '/../includes/header.php';
$totalCases = count($cases);
$perPage = 10;
$page = max(1, (int) get('page', 1));
$totalPages = max(1, (int) ceil($totalCases / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$pageCases = array_slice($cases, $offset, $perPage);
$shownFrom = $totalCases === 0 ? 0 : $offset + 1;
$shownTo = min($offset + count($pageCases), $totalCases);
$amountLabel = $filter === 'outstanding' ? __('cases.col.outstanding') : __('cases.col.fee');
$pagerQs = $filter !== '' ? '&filter=' . urlencode($filter) : '';
?>
<div class="panel case-list-panel">
    <div class="case-list-head">
        <div class="case-list-title">
            <h2><?= e($listTitle) ?></h2>
            <?php if ($filter === 'active' || $filter === 'outstanding'): ?>
                <a class="case-filter-chip" href="cases.php" title="<?= __e('common.show_all_cases') ?>">
                    <?= $filter === 'active' ? __e('cases.filter.active') : __e('cases.filter.unpaid') ?>
                    <span aria-hidden="true">×</span>
                </a>
            <?php endif; ?>
        </div>
        <a class="btn btn-primary btn-sm" href="?action=create"><?= __e('cases.open_case') ?></a>
    </div>
    <div class="table-wrap case-table-wrap">
        <table class="case-table">
            <thead>
                <tr>
                    <th><?= __e('common.case_number') ?></th>
                    <th><?= __e('common.title') ?></th>
                    <th><?= __e('common.client') ?></th>
                    <th><?= __e('common.status') ?></th>
                    <th><?= e($amountLabel) ?></th>
                    <th class="col-actions"><?= __e('common.actions') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pageCases as $c):
                $amount = $filter === 'outstanding' ? (float) $c['outstanding_total'] : (float) $c['fee_total'];
            ?>
                <tr>
                    <td class="case-num-cell"><a class="case-num-link" href="?action=view&id=<?= (int)$c['id'] ?>"><?= e($c['case_number']) ?></a></td>
                    <td class="case-title-cell">
                        <strong><?= e($c['title']) ?></strong>
                        <span class="muted"><?= e($c['case_type'] ?: ($c['company_name'] ?: __('common.em_dash'))) ?></span>
                    </td>
                    <td><?= e($c['client_name']) ?></td>
                    <td><?= status_badge($c['status']) ?></td>
                    <td class="case-fee-cell"><?= $amount > 0 ? e(money($amount)) : __('common.em_dash') ?></td>
                    <td class="col-actions">
                        <div class="row-actions">
                            <a class="btn btn-row-open btn-sm" href="?action=view&id=<?= (int)$c['id'] ?><?= $filter === 'outstanding' ? '&tab=invoices' : '' ?>"><?= __e('cases.list.open') ?></a>
                            <a class="btn btn-row-edit btn-sm" href="?action=edit&id=<?= (int)$c['id'] ?>"><?= __e('common.edit') ?></a>
                            <form method="post" data-confirm="<?= __e('confirm.delete_case') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="form_action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <button class="btn btn-row-delete btn-sm" type="submit"><?= __e('common.delete') ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$cases): ?>
                <tr>
                    <td colspan="6" class="case-empty">
                        <?php if ($filter === 'active'): ?>
                            <?= __e('cases.empty.no_active') ?>
                        <?php elseif ($filter === 'outstanding'): ?>
                            <?= __e('cases.empty.no_outstanding') ?>
                        <?php else: ?>
                            <?= __('cases.empty.none') ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="case-list-foot">
        <p class="case-list-footer muted"><?= e(__($totalCases === 1 ? 'cases.pager.showing_one' : 'cases.pager.showing_many', ['from' => (int)$shownFrom, 'to' => (int)$shownTo, 'total' => (int)$totalCases])) ?></p>
        <?php if ($totalPages > 1): ?>
        <nav class="case-list-pager" aria-label="<?= __e('cases.pagination.aria') ?>">
            <?php if ($page > 1): ?>
            <a class="case-page-btn" href="?page=<?= $page - 1 ?><?= e($pagerQs) ?>" aria-label="<?= __e('cases.pagination.prev') ?>">‹</a>
            <?php else: ?>
            <span class="case-page-btn is-disabled" aria-disabled="true">‹</span>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a class="case-page-btn<?= $p === $page ? ' is-active' : '' ?>" href="?page=<?= $p ?><?= e($pagerQs) ?>"<?= $p === $page ? ' aria-current="page"' : '' ?>><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a class="case-page-btn" href="?page=<?= $page + 1 ?><?= e($pagerQs) ?>" aria-label="<?= __e('cases.pagination.next') ?>">›</a>
            <?php else: ?>
            <span class="case-page-btn is-disabled" aria-disabled="true">›</span>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
