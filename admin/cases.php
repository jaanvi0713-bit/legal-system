<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'staff']);
$pdo = db();
$action = get('action', 'list');
$id = (int) get('id', 0);
$hasAssignedAdminColumn = false;
try {
    $hasAssignedAdminColumn = (bool) $pdo->query("SHOW COLUMNS FROM cases LIKE 'assigned_admin_id'")->fetch();
} catch (Throwable $e) {
    $hasAssignedAdminColumn = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'save') {
        ensure_case_create_columns($pdo);
        $editId = (int) post('id');
        $clientId = (int) post('client_id');
        $leadLawyerId = post('lead_lawyer_id') !== '' ? (int) post('lead_lawyer_id') : (post('lawyer_id') !== '' ? (int) post('lawyer_id') : null);
        $associateIds = array_values(array_unique(array_filter(array_map('intval', (array) post('associate_lawyer_ids', [])), static fn(int $id): bool => $id > 0)));
        $lawyerId = $leadLawyerId;
        $assignedAdminId = $hasAssignedAdminColumn && post('assigned_admin_id') !== '' ? (int) post('assigned_admin_id') : null;
        if ($hasAssignedAdminColumn && !$assignedAdminId) {
            $soleAdmins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active=1 LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
            if (count($soleAdmins) === 1) {
                $assignedAdminId = (int) $soleAdmins[0];
            }
        }
        $title = trim((string) post('title'));
        $description = trim((string) post('description'));
        $clientInstructions = trim((string) post('client_instructions'));
        $caseType = trim((string) post('case_type')) ?: 'Commercial';
        $status = post('status') ?: 'open';
        $priority = post('priority') ?: 'medium';
        if ($clientId < 1 || $title === '') {
            flash('error', __('flash.case.need_client_title'));
            redirect($editId ? ('cases.php?action=edit&id=' . $editId) : 'cases.php?action=create');
        }
        if ($editId) {
            if ($hasAssignedAdminColumn) {
                $pdo->prepare(
                    'UPDATE cases SET title=?, description=?, client_instructions=?, case_type=?, status=?, priority=?, client_id=?, lawyer_id=?, assigned_admin_id=?, court_name=?, court_location=?, filing_date=?, next_hearing_date=?, closed_at=IF(?="closed", COALESCE(closed_at, NOW()), NULL) WHERE id=?'
                )->execute([
                    $title, $description ?: null, $clientInstructions ?: null, $caseType, $status, $priority,
                    $clientId, $lawyerId, $assignedAdminId, post('court_name'), post('court_location'),
                    post('filing_date') ?: null, post('next_hearing_date') ?: null, $status, $editId,
                ]);
            } else {
                $pdo->prepare(
                    'UPDATE cases SET title=?, description=?, client_instructions=?, case_type=?, status=?, priority=?, client_id=?, lawyer_id=?, court_name=?, court_location=?, filing_date=?, next_hearing_date=?, closed_at=IF(?="closed", COALESCE(closed_at, NOW()), NULL) WHERE id=?'
                )->execute([
                    $title, $description ?: null, $clientInstructions ?: null, $caseType, $status, $priority,
                    $clientId, $lawyerId, post('court_name'), post('court_location'),
                    post('filing_date') ?: null, post('next_hearing_date') ?: null, $status, $editId,
                ]);
            }
            flash('success', __('flash.case.updated'));
            $caseId = $editId;
            sync_case_lawyers($pdo, $caseId, $leadLawyerId, $associateIds, (int) current_user()['id']);
        } else {
            $caseNumber = generate_case_number($pdo);
            if ($hasAssignedAdminColumn) {
                $pdo->prepare(
                    'INSERT INTO cases (case_number, title, description, client_instructions, case_type, status, priority, client_id, lawyer_id, assigned_admin_id, court_name, court_location, filing_date, next_hearing_date, created_by)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $caseNumber, $title, $description ?: null, $clientInstructions ?: null, $caseType, $status, $priority,
                    $clientId, $lawyerId, $assignedAdminId, post('court_name'), post('court_location'),
                    post('filing_date') ?: date('Y-m-d'), post('next_hearing_date') ?: null, current_user()['id'],
                ]);
            } else {
                $pdo->prepare(
                    'INSERT INTO cases (case_number, title, description, client_instructions, case_type, status, priority, client_id, lawyer_id, court_name, court_location, filing_date, next_hearing_date, created_by)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $caseNumber, $title, $description ?: null, $clientInstructions ?: null, $caseType, $status, $priority,
                    $clientId, $lawyerId, post('court_name'), post('court_location'),
                    post('filing_date') ?: date('Y-m-d'), post('next_hearing_date') ?: null, current_user()['id'],
                ]);
            }
            $caseId = (int) $pdo->lastInsertId();
            sync_case_lawyers($pdo, $caseId, $leadLawyerId, $associateIds, (int) current_user()['id']);
            flash('success', __('flash.case.created', ['number' => $caseNumber]));
            notify_case_team(
                $pdo,
                $caseId,
                'New case assigned',
                $caseNumber . ' assigned to you.',
                'case',
                '../lawyer/cases.php?id=' . $caseId,
                (int) current_user()['id']
            );
            create_notification($pdo, $clientId, 'Case opened', 'Your case ' . $caseNumber . ' is now in the system.', 'case', '../client/cases.php', current_user()['id']);
        }

        $feeTotal = save_case_fee_items_from_post($pdo, $caseId);

        $caseNumberLabel = '';
        $numStmt = $pdo->prepare('SELECT case_number FROM cases WHERE id = ?');
        $numStmt->execute([$caseId]);
        $caseNumberLabel = (string) ($numStmt->fetchColumn() ?: ('#' . $caseId));

        // Optional intake file
        if (!empty($_FILES['intake_file']['name'])) {
            try {
                $file = handle_upload($_FILES['intake_file']);
                if ($file) {
                    $pdo->prepare(
                        'INSERT INTO case_documents (case_id, client_id, uploaded_by, title, file_name, file_path, file_type, file_size, category, description)
                         VALUES (?,?,?,?,?,?,?,?,?,?)'
                    )->execute([
                        $caseId, $clientId, current_user()['id'], $file['file_name'],
                        $file['file_name'], $file['file_path'], $file['file_type'], $file['file_size'], 'legal',
                        'Intake document',
                    ]);
                }
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
        }

        // Optional draft quotation from fee lines (new cases only)
        if (!$editId && post('email_quotation') === '1') {
            ensure_invoice_bank_column($pdo);
            ensure_invoice_items_table($pdo);
            $feeItems = case_fee_items($pdo, $caseId);
            if ($feeItems && $feeTotal > 0) {
                $number = generate_invoice_number($pdo);
                $netSum = 0.0;
                $vatSum = 0.0;
                foreach ($feeItems as $fi) {
                    $netSum += (float) $fi['net_amount'];
                    $vatSum += (float) $fi['vat_amount'];
                }
                $pdo->prepare(
                    'INSERT INTO invoices (invoice_number, case_id, client_id, title, description, amount, tax, total, status, due_date, issued_at, created_by, payment_status)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $number, $caseId, $clientId,
                    __('cases.quotation_title_default', ['number' => $caseNumberLabel]),
                    $clientInstructions ?: null,
                    round($netSum, 2), round($vatSum, 2), round($feeTotal, 2), 'draft',
                    date('Y-m-d', strtotime('+30 days')), date('Y-m-d'), current_user()['id'], 'none',
                ]);
                $quoteId = (int) $pdo->lastInsertId();
                $insQ = $pdo->prepare(
                    'INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, vat_amount, line_total, sort_order)
                     VALUES (?,?,?,?,?,?,?)'
                );
                foreach ($feeItems as $ord => $fi) {
                    $insQ->execute([
                        $quoteId, $fi['description'], 1, $fi['net_amount'], $fi['vat_amount'], $fi['line_total'], $ord,
                    ]);
                }
                create_notification(
                    $pdo,
                    $clientId,
                    'notify.invoice_new',
                    notify_payload('notify.msg.invoice_new', ['number' => $number, 'amount' => money($feeTotal)]),
                    'payment',
                    '../client/payments.php',
                    current_user()['id']
                );
            }
        }

        if (!$editId && $clientInstructions !== '') {
            create_notification(
                $pdo,
                $clientId,
                'notify.document_requested',
                $clientInstructions,
                'case',
                '../client/cases.php',
                current_user()['id']
            );
        }

        log_activity($pdo, current_user()['id'], $editId ? 'update' : 'create', 'case', $caseId, 'Case saved');
        redirect('cases.php?action=view&id=' . $caseId);
    }
    if ($fa === 'upload') {
        try {
            $file = handle_upload($_FILES['document'] ?? []);
            if (!$file) {
                throw new RuntimeException(__('error.upload.select_file'));
            }
            $caseId = (int) post('case_id');
            $clientId = post('client_id') ?: null;
            $pdo->prepare('INSERT INTO case_documents (case_id, client_id, uploaded_by, title, file_name, file_path, file_type, file_size, category, description) VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    $caseId, $clientId, current_user()['id'], post('title') ?: $file['file_name'],
                    $file['file_name'], $file['file_path'], $file['file_type'], $file['file_size'], post('category') ?: 'legal', post('description'),
                ]);
            $docRequestId = (int) post('document_request_id');
            if ($docRequestId > 0) {
                ensure_document_requests_table($pdo);
                $docId = (int) $pdo->lastInsertId();
                $pdo->prepare('UPDATE document_requests SET status="fulfilled", fulfilled_document_id=? WHERE id=? AND case_id=?')
                    ->execute([$docId, $docRequestId, $caseId]);
            }
            flash('success', __('flash.document.uploaded'));
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('cases.php?action=view&id=' . (int) post('case_id') . '&tab=documents');
    }
    if ($fa === 'delete_document') {
        $caseId = (int) post('case_id');
        $docId = (int) post('document_id');
        $stmt = $pdo->prepare('SELECT id, file_path FROM case_documents WHERE id=? AND case_id=?');
        $stmt->execute([$docId, $caseId]);
        $doc = $stmt->fetch();
        if ($doc) {
            $pdo->prepare('DELETE FROM case_documents WHERE id=?')->execute([$docId]);
            $abs = __DIR__ . '/../' . ltrim((string) $doc['file_path'], '/');
            if (is_file($abs)) {
                @unlink($abs);
            }
            flash('success', __('flash.document.deleted'));
        } else {
            flash('error', __('flash.document.not_found'));
        }
        redirect('cases.php?action=view&id=' . $caseId . '&tab=documents');
    }
    if ($fa === 'add_doc_request') {
        ensure_document_requests_table($pdo);
        $caseId = (int) post('case_id');
        $clientId = (int) post('client_id');
        $title = trim((string) post('request_title'));
        if ($caseId < 1 || $clientId < 1 || $title === '') {
            flash('error', __('flash.document.request_need_title'));
            redirect('cases.php?action=view&id=' . $caseId . '&tab=documents');
        }
        $pdo->prepare(
            'INSERT INTO document_requests (case_id, client_id, title, instructions, is_required, requested_by) VALUES (?,?,?,?,?,?)'
        )->execute([
            $caseId,
            $clientId,
            $title,
            trim((string) post('request_instructions')) ?: null,
            post('request_required') === '1' ? 1 : 0,
            current_user()['id'],
        ]);
        create_notification(
            $pdo,
            $clientId,
            'notify.document_requested',
            notify_payload('notify.msg.document_requested_named', ['doc' => $title]),
            'document',
            '../client/documents.php',
            current_user()['id']
        );
        flash('success', __('flash.document.request_added'));
        redirect('cases.php?action=view&id=' . $caseId . '&tab=documents');
    }
    if ($fa === 'delete_doc_request') {
        ensure_document_requests_table($pdo);
        $caseId = (int) post('case_id');
        $reqId = (int) post('request_id');
        $pdo->prepare('DELETE FROM document_requests WHERE id=? AND case_id=?')->execute([$reqId, $caseId]);
        flash('success', __('flash.document.request_deleted'));
        redirect('cases.php?action=view&id=' . $caseId . '&tab=documents');
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
        $amountInput = (float) post('amount');
        if ($fa === 'quotation' && post('tax_rate') !== '') {
            // Amount is total including VAT; derive net + tax from rate.
            $taxRate = max(0, (float) post('tax_rate'));
            $total = round($amountInput, 2);
            $net = $taxRate > 0 ? round($total / (1 + ($taxRate / 100)), 2) : $total;
            $tax = round($total - $net, 2);
            $amount = $net;
        } else {
            $amount = $amountInput;
            $tax = (float) post('tax');
            $total = round($amount + $tax, 2);
        }
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
        $clientId = (int) post('client_id');
        $amount = (float) post('amount');
        if (!$invId) {
            flash('error', 'Select an invoice before recording a payment.');
            redirect('cases.php?action=view&id=' . $caseId . '&tab=receipts&compose=payment');
        }
        $receipt = generate_receipt_number($pdo);
        $pdo->prepare('INSERT INTO payments (invoice_id, client_id, amount, payment_method, reference_number, receipt_number, notes, paid_at, recorded_by) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([
                $invId, $clientId, $amount, post('payment_method'),
                post('reference_number'), $receipt, post('notes'), post('paid_at') ?: date('Y-m-d H:i:s'), current_user()['id'],
            ]);
        $paymentId = (int) $pdo->lastInsertId();
        sync_invoice_payment_status($pdo, $invId);
        $invRowStmt = $pdo->prepare('SELECT * FROM invoices WHERE id=?');
        $invRowStmt->execute([$invId]);
        $invRow = $invRowStmt->fetch() ?: ['id' => $invId, 'client_id' => $clientId, 'case_id' => $caseId, 'invoice_number' => '—'];
        notify_payment_events(
            $pdo,
            $invRow,
            $amount,
            $paymentId,
            $receipt,
            (int) current_user()['id']
        );
        $returnTo = 'cases.php?action=view&id=' . $caseId . '&tab=receipts';
        flash('success', __('flash.payment.recorded', ['receipt' => $receipt]));
        redirect('receipt.php?id=' . $paymentId . '&from=' . rawurlencode($returnTo));
    }
    if ($fa === 'delete') {
        $delId = (int) post('id');
        $pdo->prepare('DELETE FROM cases WHERE id=?')->execute([$delId]);
        flash('success', 'Case deleted.');
        redirect('cases.php');
    }
    if ($fa === 'save_task') {
        ensure_case_tasks_table($pdo);
        $caseId = (int) post('case_id');
        $result = save_case_task($pdo, $caseId, [
            'id' => (int) post('task_id'),
            'title' => post('title'),
            'description' => post('description'),
            'assigned_to' => post('assigned_to'),
            'due_date' => post('due_date'),
            'status' => post('status') ?: 'open',
        ], (int) current_user()['id']);
        if (!$result['ok']) {
            flash('error', (string) ($result['error'] ?? __('cases.tasks.error.save_failed')));
            redirect('cases.php?action=view&id=' . $caseId . '&tab=tasks');
        }
        flash('success', __('cases.tasks.flash.saved'));
        redirect('cases.php?action=view&id=' . $caseId . '&tab=tasks');
    }
    if ($fa === 'delete_task') {
        ensure_case_tasks_table($pdo);
        $caseId = (int) post('case_id');
        $taskId = (int) post('task_id');
        delete_case_task($pdo, $caseId, $taskId);
        flash('success', __('cases.tasks.flash.deleted'));
        redirect('cases.php?action=view&id=' . $caseId . '&tab=tasks');
    }
}

$clients = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role='client' ORDER BY first_name")->fetchAll();
$lawyers = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role='lawyer' AND is_active=1 ORDER BY first_name")->fetchAll();
$admins = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role = 'admin' AND is_active=1 ORDER BY first_name")->fetchAll();
$pageTitle = __('page.cases');
$pageSubtitle = __('page.cases.subtitle');
$portal = 'admin';
$activeNav = 'cases';

if ($action === 'create' || ($action === 'edit' && $id)) {
    ensure_case_create_columns($pdo);
    $case = [
        'id' => 0, 'title' => '', 'description' => '', 'client_instructions' => '', 'case_type' => 'Commercial', 'status' => 'open', 'priority' => 'medium',
        'client_id' => '', 'lawyer_id' => '', 'assigned_admin_id' => '', 'court_name' => '', 'court_location' => '', 'filing_date' => date('Y-m-d'), 'next_hearing_date' => '',
        'total_fee' => 0,
    ];
    $feeItems = [];
    $nonvatRate = 0;
    $vatRate = 20;
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM cases WHERE id=?');
        $stmt->execute([$id]);
        $case = $stmt->fetch() ?: $case;
        $feeItems = case_fee_items($pdo, $id);
        foreach ($feeItems as $fi) {
            if ($fi['section'] === 'vat') {
                $vatRate = (float) $fi['vat_rate'];
            } else {
                $nonvatRate = (float) $fi['vat_rate'];
            }
        }
    }
    $nonvatItems = array_values(array_filter($feeItems, static fn($f) => ($f['section'] ?? '') === 'nonvat'));
    $vatItems = array_values(array_filter($feeItems, static fn($f) => ($f['section'] ?? '') === 'vat'));
    if (!$nonvatItems) {
        $nonvatItems = [['description' => '', 'net_amount' => 0]];
    }
    if (!$vatItems) {
        $vatItems = [['description' => '', 'net_amount' => 0]];
    }
    $currencySym = trim(currency_symbol());
    $isEdit = (bool) $id;
    $caseTeam = $id ? case_lawyers_for_case($pdo, $id) : [];
    $leadLawyerId = (int) ($case['lawyer_id'] ?? 0);
    $associateLawyerIds = [];
    foreach ($caseTeam as $teamRow) {
        if (($teamRow['role'] ?? '') === 'lead') {
            $leadLawyerId = (int) $teamRow['lawyer_id'];
        } else {
            $associateLawyerIds[] = (int) $teamRow['lawyer_id'];
        }
    }
    $pageTitle = $isEdit ? __('cases.edit') : __('cases.create_new');
    $bodyClass = 'page-case-create';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="case-create-page">
        <a class="btn btn-primary btn-sm case-create-back" href="cases.php">← <?= __e('cases.back_to_cases') ?></a>
        <div class="case-create-intro">
            <h1><?= $isEdit ? __e('cases.edit') : __e('cases.create_new') ?></h1>
            <p><?= $isEdit ? __e('cases.form.help.edit') : __e('cases.form.help.create_workspace') ?></p>
        </div>

        <form method="post" enctype="multipart/form-data" class="panel case-create-form" id="caseCreateForm">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="id" value="<?= (int) $case['id'] ?>">
            <input type="hidden" name="case_type" value="<?= e($case['case_type'] ?: 'Commercial') ?>">
            <?php if (!$isEdit): ?>
                <input type="hidden" name="status" value="open">
                <input type="hidden" name="priority" value="medium">
                <input type="hidden" name="filing_date" value="<?= e(date('Y-m-d')) ?>">
            <?php endif; ?>

            <section class="case-create-section">
                <div class="case-create-section-head">
                    <span class="case-create-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 6V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v1"/><rect x="4" y="6" width="16" height="14" rx="2"/></svg>
                    </span>
                    <div>
                        <h2><?= __e('cases.form.section.info') ?></h2>
                        <p><?= __e('cases.form.section.info_help') ?></p>
                    </div>
                </div>
                <div class="form-group">
                    <label for="title"><?= __e('cases.form.case_title') ?> <span class="req">*</span></label>
                    <input id="title" name="title" required value="<?= e($case['title']) ?>" placeholder="<?= __e('cases.form.case_title_ph') ?>">
                </div>
                <div class="form-group">
                    <label for="description"><?= __e('common.description') ?></label>
                    <textarea id="description" name="description" rows="4" placeholder="<?= __e('cases.form.description_ph') ?>"><?= e($case['description']) ?></textarea>
                </div>
            </section>

            <section class="case-create-section">
                <div class="case-create-section-head">
                    <span class="case-create-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="8" r="3.2"/><circle cx="16" cy="9" r="2.6"/><path d="M3.5 19c1.2-3.2 3.8-4.8 5.5-4.8S13 15.8 14.2 19"/><path d="M14 14.4c1.5-.4 3.2.2 4.5 2.6"/></svg>
                    </span>
                    <div>
                        <h2><?= __e('cases.form.section.assignment') ?></h2>
                        <p><?= __e('cases.form.section.assignment_help') ?></p>
                    </div>
                </div>
                <div class="case-create-grid-2">
                    <div class="form-group">
                        <label for="client_id"><?= __e('common.client') ?> <span class="req">*</span></label>
                        <select id="client_id" name="client_id" required>
                            <option value=""><?= __e('cases.form.select_client') ?></option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= (int) $case['client_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e(full_name($c)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <a class="case-create-link" href="clients.php?action=create"><?= __e('cases.form.add_client_link') ?></a>
                    </div>
                    <?php if ($hasAssignedAdminColumn && count($admins) > 1): ?>
                    <div class="form-group">
                        <label for="assigned_admin_id"><?= __e('cases.hub.assigned_admin') ?></label>
                        <select id="assigned_admin_id" name="assigned_admin_id">
                            <option value=""><?= __e('form.unassigned_simple') ?></option>
                            <?php foreach ($admins as $a): ?>
                                <option value="<?= (int) $a['id'] ?>" <?= (int) ($case['assigned_admin_id'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>><?= e(full_name($a)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php elseif ($hasAssignedAdminColumn && count($admins) === 1): ?>
                        <?php
                        $soleAdminId = (int) $admins[0]['id'];
                        $lockedAdminId = (int) ($case['assigned_admin_id'] ?? 0) ?: $soleAdminId;
                        ?>
                        <input type="hidden" name="assigned_admin_id" value="<?= $lockedAdminId ?>">
                    <?php endif; ?>
                </div>
                <div class="case-create-grid-2 case-team-lead-row">
                    <div class="form-group">
                        <label for="lead_lawyer_id"><?= __e('cases.team.lead_lawyer') ?></label>
                        <select id="lead_lawyer_id" name="lead_lawyer_id">
                            <option value=""><?= __e('form.unassigned_simple') ?></option>
                            <?php foreach ($lawyers as $l): ?>
                                <option value="<?= (int) $l['id'] ?>" <?= $leadLawyerId === (int) $l['id'] ? 'selected' : '' ?>><?= e(full_name($l)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status"><?= __e('common.status') ?></label>
                        <select id="status" name="status">
                            <?php foreach (['open','active','pending','on_hold','closed','reopened'] as $s): ?>
                                <option value="<?= $s ?>" <?= $case['status'] === $s ? 'selected' : '' ?>><?= e(translate_status($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group case-associates-field">
                    <label id="associateLawyersLabel"><?= __e('cases.team.associates') ?></label>
                    <?php if (!$lawyers): ?>
                    <p class="muted"><?= __e('cases.team.no_lawyers') ?></p>
                    <?php else: ?>
                    <div class="case-assoc-dropdown" id="caseAssocDropdown" data-placeholder="<?= __e('cases.team.associates_placeholder') ?>">
                        <button type="button" class="case-assoc-trigger" id="caseAssocTrigger" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="associateLawyersLabel">
                            <span class="case-assoc-trigger-text" id="caseAssocTriggerText"><?= __e('cases.team.associates_placeholder') ?></span>
                            <span class="case-assoc-caret" aria-hidden="true"></span>
                        </button>
                        <div class="case-assoc-menu" id="caseAssocMenu" hidden role="listbox" aria-multiselectable="true" aria-labelledby="associateLawyersLabel">
                            <?php foreach ($lawyers as $l):
                                $lid = (int) $l['id'];
                                $isLead = $leadLawyerId === $lid;
                                $isAssoc = in_array($lid, $associateLawyerIds, true) && !$isLead;
                            ?>
                            <label class="case-assoc-option<?= $isLead ? ' is-lead' : '' ?>" data-lawyer-id="<?= $lid ?>">
                                <input type="checkbox"
                                       name="associate_lawyer_ids[]"
                                       value="<?= $lid ?>"
                                       <?= $isAssoc ? 'checked' : '' ?>
                                       <?= $isLead ? 'disabled' : '' ?>>
                                <span class="case-assoc-option-name"><?= e(full_name($l)) ?></span>
                                <?php if ($isLead): ?>
                                <span class="case-assoc-option-tag"><?= __e('cases.team.lead') ?></span>
                                <?php endif; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <p class="muted case-associates-help"><?= __e('cases.team.associates_help') ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($isEdit): ?>
                    <input type="hidden" name="court_name" value="<?= e($case['court_name'] ?? '') ?>">
                    <input type="hidden" name="court_location" value="<?= e($case['court_location'] ?? '') ?>">
                    <input type="hidden" name="filing_date" value="<?= e($case['filing_date'] ?? '') ?>">
                    <input type="hidden" name="next_hearing_date" value="<?= e($case['next_hearing_date'] ?? '') ?>">
                    <input type="hidden" name="priority" value="<?= e($case['priority'] ?? 'medium') ?>">
                <?php endif; ?>
            </section>

            <section class="case-create-section">
                <div class="case-create-section-head">
                    <span class="case-create-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M21 12a8.5 8.5 0 0 1-8.5 8.5H7l-3 2.2V15.5A8.5 8.5 0 1 1 21 12z"/></svg>
                    </span>
                    <div>
                        <h2><?= __e('cases.form.section.instructions') ?></h2>
                        <p><?= __e('cases.form.section.instructions_help') ?></p>
                    </div>
                </div>
                <div class="form-group">
                    <label for="client_instructions"><?= __e('cases.form.instructions_label') ?></label>
                    <textarea id="client_instructions" name="client_instructions" rows="4" placeholder="<?= __e('cases.form.instructions_ph') ?>"><?= e($case['client_instructions'] ?? '') ?></textarea>
                </div>
                <div class="case-create-file-row">
                    <div class="form-group">
                        <label for="intake_file"><?= __e('cases.form.upload_optional') ?></label>
                        <input id="intake_file" type="file" name="intake_file">
                    </div>
                    <?php if (!$isEdit): ?>
                    <div class="case-create-checks">
                        <label><input type="checkbox" name="email_quotation" value="1" checked> <?= __e('cases.form.email_quotation') ?></label>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="case-create-section">
                <div class="case-create-section-head">
                    <span class="case-create-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18M12 14h4"/></svg>
                    </span>
                    <div>
                        <h2><?= __e('cases.form.section.billing') ?></h2>
                        <p><?= __e('cases.form.section.billing_help') ?></p>
                    </div>
                </div>

                <div class="case-fee-block" data-section="nonvat">
                    <div class="case-fee-block-head">
                        <strong><?= __e('finance.non_vat_services') ?></strong>
                        <div class="case-fee-block-actions">
                            <label class="case-fee-rate"><?= __e('cases.form.rate_pct') ?> <input type="number" step="0.01" min="0" name="nonvat_rate" id="caseNonvatRate" value="<?= e((string) $nonvatRate) ?>"></label>
                            <button type="button" class="case-fee-add" data-add="nonvat">+ <?= __e('finance.add_service') ?></button>
                        </div>
                    </div>
                    <div class="case-fee-cols"><span><?= __e('common.service') ?></span><span><?= __e('cases.form.net_amount') ?></span><span><?= __e('common.total') ?></span></div>
                    <div class="case-fee-rows" id="caseNonvatRows">
                        <?php foreach ($nonvatItems as $row): ?>
                        <div class="case-fee-row">
                            <input name="nonvat_description[]" placeholder="<?= __e('cases.form.service_ph_nonvat') ?>" value="<?= e($row['description'] ?? '') ?>">
                            <div class="case-fee-amt"><span><?= e($currencySym) ?></span><input type="number" step="0.01" min="0" name="nonvat_amount[]" class="case-fee-net" data-group="nonvat" value="<?= e((string) ($row['net_amount'] ?? 0)) ?>"></div>
                            <strong class="case-fee-line-total"><?= e(money(0)) ?></strong>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="case-fee-block" data-section="vat">
                    <div class="case-fee-block-head">
                        <strong><?= __e('finance.vat_services') ?></strong>
                        <div class="case-fee-block-actions">
                            <label class="case-fee-rate"><?= __e('cases.form.rate_pct') ?> <input type="number" step="0.01" min="0" name="vat_rate" id="caseVatRate" value="<?= e((string) $vatRate) ?>"></label>
                            <button type="button" class="case-fee-add" data-add="vat">+ <?= __e('finance.add_service') ?></button>
                        </div>
                    </div>
                    <div class="case-fee-cols"><span><?= __e('common.service') ?></span><span><?= __e('cases.form.net_amount') ?></span><span><?= __e('common.total') ?></span></div>
                    <div class="case-fee-rows" id="caseVatRows">
                        <?php foreach ($vatItems as $row): ?>
                        <div class="case-fee-row">
                            <input name="vat_description[]" placeholder="<?= __e('cases.form.service_ph_vat') ?>" value="<?= e($row['description'] ?? '') ?>">
                            <div class="case-fee-amt"><span><?= e($currencySym) ?></span><input type="number" step="0.01" min="0" name="vat_amount[]" class="case-fee-net" data-group="vat" value="<?= e((string) ($row['net_amount'] ?? 0)) ?>"></div>
                            <strong class="case-fee-line-total"><?= e(money(0)) ?></strong>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="case-fee-summary">
                    <div>
                        <div class="case-fee-summary-title"><?= __e('finance.non_vat_services') ?></div>
                        <div class="case-fee-summary-row"><span><?= __e('cases.form.summary_net') ?></span><strong id="caseNonvatNet"><?= e(money(0)) ?></strong></div>
                        <div class="case-fee-summary-row"><span><?= __e('cases.form.summary_rate_amt') ?></span><strong id="caseNonvatTax"><?= e(money(0)) ?></strong></div>
                        <div class="case-fee-summary-row is-sub"><span><?= __e('finance.subtotal') ?></span><strong id="caseNonvatSub"><?= e(money(0)) ?></strong></div>
                    </div>
                    <div>
                        <div class="case-fee-summary-title"><?= __e('finance.vat_services') ?></div>
                        <div class="case-fee-summary-row"><span><?= __e('cases.form.summary_net') ?></span><strong id="caseVatNet"><?= e(money(0)) ?></strong></div>
                        <div class="case-fee-summary-row"><span><?= __e('finance.vat') ?></span><strong id="caseVatTax"><?= e(money(0)) ?></strong></div>
                        <div class="case-fee-summary-row is-sub"><span><?= __e('finance.subtotal') ?></span><strong id="caseVatSub"><?= e(money(0)) ?></strong></div>
                    </div>
                </div>
                <div class="case-fee-grand">
                    <span><?= __e('cases.hub.total_fee') ?></span>
                    <strong id="caseFeeGrand"><?= e(money(0)) ?></strong>
                </div>
            </section>

            <div class="case-create-footer">
                <p class="case-create-required"><span class="req">*</span> <?= __e('form.required_fields') ?></p>
                <div class="case-create-actions">
                    <a class="btn btn-secondary" href="cases.php"><?= __e('common.cancel') ?></a>
                    <button class="btn btn-primary" type="submit"><?= $isEdit ? __e('common.save_changes') : '✓ ' . __e('cases.create') ?></button>
                </div>
            </div>
        </form>
    </div>
    <script>
    (function () {
      var sym = <?= json_encode($currencySym) ?>;
      function moneyFmt(n) {
        var v = (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2);
        try { v = Number(v).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}); } catch (e) {}
        return sym + v;
      }
      var nonRows = document.getElementById('caseNonvatRows');
      var vatRows = document.getElementById('caseVatRows');
      var nonRate = document.getElementById('caseNonvatRate');
      var vatRate = document.getElementById('caseVatRate');
      function rowHtml(group) {
        var nameD = group === 'vat' ? 'vat_description[]' : 'nonvat_description[]';
        var nameA = group === 'vat' ? 'vat_amount[]' : 'nonvat_amount[]';
        var ph = group === 'vat' ? <?= json_encode(__('cases.form.service_ph_vat')) ?> : <?= json_encode(__('cases.form.service_ph_nonvat')) ?>;
        return '<div class="case-fee-row"><input name="'+nameD+'" placeholder="'+ph.replace(/"/g,'&quot;')+'"><div class="case-fee-amt"><span>'+sym+'</span><input type="number" step="0.01" min="0" name="'+nameA+'" class="case-fee-net" data-group="'+group+'" value="0"></div><strong class="case-fee-line-total">'+moneyFmt(0)+'</strong></div>';
      }
      function sum(root, rate) {
        var net = 0;
        root.querySelectorAll('.case-fee-row').forEach(function (row) {
          var n = parseFloat(row.querySelector('.case-fee-net').value) || 0;
          var tax = Math.round(n * rate) / 100;
          net += n;
          row.querySelector('.case-fee-line-total').textContent = moneyFmt(n + tax);
        });
        var tax = Math.round((net * rate + Number.EPSILON) * 100) / 100 / 100 * 100;
        tax = Math.round(net * rate) / 100;
        return { net: net, tax: tax, sub: net + tax };
      }
      function recalc() {
        var nr = parseFloat(nonRate.value) || 0;
        var vr = parseFloat(vatRate.value) || 0;
        var a = sum(nonRows, nr);
        var b = sum(vatRows, vr);
        document.getElementById('caseNonvatNet').textContent = moneyFmt(a.net);
        document.getElementById('caseNonvatTax').textContent = moneyFmt(a.tax);
        document.getElementById('caseNonvatSub').textContent = moneyFmt(a.sub);
        document.getElementById('caseVatNet').textContent = moneyFmt(b.net);
        document.getElementById('caseVatTax').textContent = moneyFmt(b.tax);
        document.getElementById('caseVatSub').textContent = moneyFmt(b.sub);
        document.getElementById('caseFeeGrand').textContent = moneyFmt(a.sub + b.sub);
      }
      document.querySelectorAll('[data-add]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var g = btn.getAttribute('data-add');
          (g === 'vat' ? vatRows : nonRows).insertAdjacentHTML('beforeend', rowHtml(g));
          recalc();
        });
      });
      document.getElementById('caseCreateForm').addEventListener('input', function (e) {
        if (e.target.matches('.case-fee-net, #caseNonvatRate, #caseVatRate')) recalc();
      });
      recalc();

      var leadSelect = document.getElementById('lead_lawyer_id');
      var assocDrop = document.getElementById('caseAssocDropdown');
      var assocTrigger = document.getElementById('caseAssocTrigger');
      var assocTriggerText = document.getElementById('caseAssocTriggerText');
      var assocMenu = document.getElementById('caseAssocMenu');
      var leadTagLabel = <?= json_encode(__('cases.team.lead')) ?>;
      var selectedMany = <?= json_encode(__('cases.team.associates_selected')) ?>;

      function assocChecked() {
        if (!assocMenu) return [];
        return Array.prototype.slice.call(assocMenu.querySelectorAll('input[type="checkbox"]:checked:not(:disabled)'));
      }

      function updateAssocLabel() {
        if (!assocDrop || !assocTriggerText) return;
        var checked = assocChecked();
        var placeholder = assocDrop.getAttribute('data-placeholder') || '';
        if (!checked.length) {
          assocTriggerText.textContent = placeholder;
          assocTriggerText.classList.add('is-placeholder');
          return;
        }
        assocTriggerText.classList.remove('is-placeholder');
        if (checked.length === 1) {
          var nameEl = checked[0].closest('.case-assoc-option');
          nameEl = nameEl ? nameEl.querySelector('.case-assoc-option-name') : null;
          assocTriggerText.textContent = nameEl ? nameEl.textContent : placeholder;
          return;
        }
        assocTriggerText.textContent = selectedMany.replace(':count', String(checked.length));
      }

      function setAssocOpen(open) {
        if (!assocDrop || !assocTrigger || !assocMenu) return;
        assocDrop.classList.toggle('is-open', open);
        assocTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        assocMenu.hidden = !open;
      }

      function syncAssociateDropdown() {
        if (!assocMenu) return;
        var leadId = leadSelect ? String(leadSelect.value || '') : '';
        assocMenu.querySelectorAll('.case-assoc-option').forEach(function (row) {
          var input = row.querySelector('input[type="checkbox"]');
          if (!input) return;
          var isLead = leadId !== '' && String(input.value) === leadId;
          row.classList.toggle('is-lead', isLead);
          input.disabled = isLead;
          if (isLead) {
            input.checked = false;
            if (!row.querySelector('.case-assoc-option-tag')) {
              var tag = document.createElement('span');
              tag.className = 'case-assoc-option-tag';
              tag.textContent = leadTagLabel;
              row.appendChild(tag);
            }
          } else {
            var existing = row.querySelector('.case-assoc-option-tag');
            if (existing) existing.remove();
          }
        });
        updateAssocLabel();
      }

      if (assocTrigger && assocMenu) {
        assocTrigger.addEventListener('click', function (e) {
          e.preventDefault();
          setAssocOpen(assocMenu.hidden);
        });
        assocMenu.addEventListener('change', updateAssocLabel);
        document.addEventListener('click', function (e) {
          if (!assocDrop || assocDrop.contains(e.target)) return;
          setAssocOpen(false);
        });
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape') setAssocOpen(false);
        });
      }
      if (leadSelect) leadSelect.addEventListener('change', syncAssociateDropdown);
      syncAssociateDropdown();
    })();
    </script>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

if ($action === 'view' && $id) {
    $viewSql = 'SELECT c.*, cl.first_name AS client_first, cl.last_name AS client_last, cl.email AS client_email, cl.company_name AS client_company, cl.phone AS client_phone, CONCAT(lw.first_name," ",lw.last_name) AS lawyer_name, CONCAT(cb.first_name," ",cb.last_name) AS created_by_name';
    if ($hasAssignedAdminColumn) {
        $viewSql .= ', CONCAT(aa.first_name," ",aa.last_name) AS assigned_admin_name';
    } else {
        $viewSql .= ', NULL AS assigned_admin_name';
    }
    $viewSql .= ' FROM cases c JOIN users cl ON cl.id=c.client_id LEFT JOIN users lw ON lw.id=c.lawyer_id LEFT JOIN users cb ON cb.id=c.created_by';
    if ($hasAssignedAdminColumn) {
        $viewSql .= ' LEFT JOIN users aa ON aa.id=c.assigned_admin_id';
    }
    $viewSql .= ' WHERE c.id=?';
    $stmt = $pdo->prepare($viewSql);
    $stmt->execute([$id]);
    $case = $stmt->fetch();
    if (!$case) { flash('error', __('flash.case.not_found')); redirect('cases.php'); }
    $clientName = trim(($case['client_first'] ?? '') . ' ' . ($case['client_last'] ?? ''));

    $notes = $pdo->prepare('SELECT n.*, CONCAT(u.first_name," ",u.last_name) AS author FROM case_notes n JOIN users u ON u.id=n.user_id WHERE n.case_id=? ORDER BY n.created_at DESC');
    $notes->execute([$id]);
    $notes = $notes->fetchAll();
    $docs = $pdo->prepare(
        'SELECT d.*, CONCAT(u.first_name," ",u.last_name) AS uploader_name, u.role AS uploader_role
         FROM case_documents d
         LEFT JOIN users u ON u.id=d.uploaded_by
         WHERE d.case_id=?
         ORDER BY d.created_at DESC'
    );
    $docs->execute([$id]);
    $docs = $docs->fetchAll();
    ensure_document_requests_table($pdo);
    $docRequests = $pdo->prepare('SELECT * FROM document_requests WHERE case_id=? ORDER BY created_at DESC');
    $docRequests->execute([$id]);
    $docRequests = $docRequests->fetchAll();
    $hearings = $pdo->prepare('SELECT * FROM court_hearings WHERE case_id=? ORDER BY hearing_date DESC');
    $hearings->execute([$id]);
    $hearings = $hearings->fetchAll();
    ensure_case_tasks_table($pdo);
    $caseTasks = case_tasks_for_case($pdo, $id);
    $caseTeamRows = case_lawyers_for_case($pdo, $id);
    $invoicesAll = $pdo->prepare('SELECT i.*, CONCAT(u.first_name," ",u.last_name) AS creator_name FROM invoices i LEFT JOIN users u ON u.id=i.created_by WHERE i.case_id=? ORDER BY i.created_at DESC');
    $invoicesAll->execute([$id]);
    $invoicesAll = $invoicesAll->fetchAll();
    $quotations = array_values(array_filter($invoicesAll, static fn($i) => $i['status'] === 'draft'));
    $invoices = array_values(array_filter($invoicesAll, static fn($i) => $i['status'] !== 'draft'));
    $payments = $pdo->prepare(
        'SELECT p.*, i.invoice_number, i.case_id, CONCAT(u.first_name," ",u.last_name) AS recorder_name
         FROM payments p
         INNER JOIN invoices i ON i.id = p.invoice_id
         LEFT JOIN users u ON u.id = p.recorded_by
         WHERE i.case_id = ?
         ORDER BY p.paid_at DESC, p.id DESC'
    );
    $payments->execute([$id]);
    $payments = $payments->fetchAll();
    $feeTotal = array_sum(array_map(static fn($i) => (float) $i['total'], $invoicesAll));
    $paidTotal = array_sum(array_map(static fn($p) => (float) $p['amount'], $payments));

    $activity = [];
    foreach ($docs as $d) {
        $docLabel = trim((string) ($d['file_name'] ?: $d['title']));
        $activity[] = [
            'type' => 'document',
            'title' => __('cases.activity.document'),
            'ref' => $docLabel !== '' ? $docLabel : ($d['title'] ?: '—'),
            'at' => $d['created_at'],
            'by' => trim((string) ($d['uploader_name'] ?? '')),
        ];
    }
    foreach ($invoicesAll as $i) {
        $isQuote = $i['status'] === 'draft';
        $activity[] = [
            'type' => $isQuote ? 'quote' : 'invoice',
            'title' => __($isQuote ? 'cases.activity.quotation' : 'cases.activity.invoice'),
            'ref' => $i['invoice_number'] . ' · ' . money($i['total']),
            'at' => $i['created_at'],
            'by' => trim((string) ($i['creator_name'] ?? '')),
        ];
    }
    foreach ($payments as $p) {
        $payRef = money($p['amount']);
        if (!empty($p['invoice_number'])) {
            $payRef .= ' · ' . $p['invoice_number'];
        } elseif (!empty($p['receipt_number'])) {
            $payRef .= ' · ' . $p['receipt_number'];
        }
        $activity[] = [
            'type' => 'payment',
            'title' => __('cases.activity.payment'),
            'ref' => $payRef,
            'at' => $p['paid_at'],
            'by' => trim((string) ($p['recorder_name'] ?? '')),
        ];
    }
    foreach ($notes as $n) {
        $notePreview = trim(preg_replace('/\s+/', ' ', (string) $n['note']));
        if (function_exists('mb_strlen') && mb_strlen($notePreview) > 72) {
            $notePreview = mb_substr($notePreview, 0, 69) . '…';
        } elseif (strlen($notePreview) > 72) {
            $notePreview = substr($notePreview, 0, 69) . '…';
        }
        $activity[] = [
            'type' => 'note',
            'title' => __('cases.activity.note'),
            'ref' => $notePreview !== '' ? $notePreview : ($n['author'] ?: 'Note'),
            'at' => $n['created_at'],
            'by' => trim((string) ($n['author'] ?? '')),
        ];
    }
    foreach ($hearings as $h) {
        $activity[] = [
            'type' => 'hearing',
            'title' => __('cases.activity.hearing'),
            'ref' => $h['court_name'] ?: __('common.court'),
            'at' => $h['hearing_date'],
            'by' => '',
        ];
    }
    $activity[] = [
        'type' => 'case',
        'title' => __('cases.activity.created'),
        'ref' => $case['case_number'],
        'at' => $case['created_at'],
        'by' => trim((string) ($case['created_by_name'] ?? '')),
    ];
    usort($activity, static fn($a, $b) => strtotime($b['at']) <=> strtotime($a['at']));
    $activityTotal = count($activity);
    $activityRecent = array_slice($activity, 0, 8);

    $activityIcons = [
        'document' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h6l5 5v11a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M14 3v5h5M12 12v5M9.5 14.5H14.5"/></svg>',
        'invoice' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h8l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M15 3v4h4M9 12h6M9 16h4"/></svg>',
        'quote' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h8l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M15 3v4h4M9 12h6M9 16h4"/></svg>',
        'payment' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="6" width="19" height="12" rx="2"/><path d="M2.5 10h19M7 15h3"/></svg>',
        'note' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h7l4 4v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M15 3v4h4M9 12h6M9 16h4"/></svg>',
        'hearing' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16M6 20V10M10 20V10M14 20V10M18 20V10M3 10h18M12 4l9 6H3l9-6z"/></svg>',
        'case' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><rect x="3" y="7" width="18" height="14" rx="2"/><path d="M3 13h18"/></svg>',
    ];
    $formatActivityWhen = static function (?string $at): string {
        $ts = $at ? strtotime($at) : false;
        return $ts ? date('M d, Y g:i A', $ts) : '—';
    };
    $formatActivityDay = static function (?string $at): string {
        $ts = $at ? strtotime($at) : false;
        return $ts ? strtoupper(date('F j, Y', $ts)) : '';
    };

    $tabs = [
        'overview' => __('cases.tab.overview'),
        'documents' => __('cases.tab.documents'),
        'quotations' => __('cases.tab.quotations'),
        'invoices' => __('cases.tab.invoices'),
        'receipts' => __('cases.tab.receipts'),
        'checklist' => __('cases.tab.checklist'),
        'tasks' => __('cases.tab.tasks'),
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
        [__('cases.checklist.team_assigned'), count($caseTeamRows) > 0 || !empty($case['lawyer_id'])],
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

        <?php if ($tab === 'overview'):
            $serviceLines = [];
            $serviceNet = 0.0;
            $serviceTax = 0.0;
            $serviceTotal = 0.0;
            foreach ($invoices as $invRow) {
                $lines = invoice_line_items($pdo, (int) $invRow['id']);
                if ($lines) {
                    foreach ($lines as $line) {
                        $net = (float) ($line['quantity'] ?? 1) * (float) ($line['unit_price'] ?? 0);
                        $tax = (float) ($line['vat_amount'] ?? 0);
                        $tot = (float) ($line['line_total'] ?? ($net + $tax));
                        $serviceLines[] = [
                            'label' => (string) ($line['description'] ?: $invRow['title']),
                            'net' => $net,
                            'total' => $tot,
                        ];
                        $serviceNet += $net;
                        $serviceTax += $tax;
                        $serviceTotal += $tot;
                    }
                } else {
                    $net = (float) $invRow['amount'];
                    $tax = (float) $invRow['tax'];
                    $tot = (float) $invRow['total'];
                    $serviceLines[] = [
                        'label' => (string) ($invRow['title'] ?: $invRow['invoice_number']),
                        'net' => $net,
                        'total' => $tot,
                    ];
                    $serviceNet += $net;
                    $serviceTax += $tax;
                    $serviceTotal += $tot;
                }
            }
            if (!$serviceLines) {
                $caseFeeRows = case_fee_items($pdo, $id);
                foreach ($caseFeeRows as $feeRow) {
                    $net = (float) $feeRow['net_amount'];
                    $tax = (float) $feeRow['vat_amount'];
                    $tot = (float) $feeRow['line_total'];
                    $serviceLines[] = [
                        'label' => (string) $feeRow['description'],
                        'net' => $net,
                        'total' => $tot,
                    ];
                    $serviceNet += $net;
                    $serviceTax += $tax;
                    $serviceTotal += $tot;
                }
                if ($serviceTotal <= 0 && (float) ($case['total_fee'] ?? 0) > 0) {
                    $serviceTotal = (float) $case['total_fee'];
                    $feeTotal = max($feeTotal, $serviceTotal);
                }
            }
            if (!$serviceLines) {
                $serviceLines[] = [
                    'label' => (string) ($case['case_type'] ?: __('cases.hub.legal_service')),
                    'net' => 0,
                    'total' => 0,
                ];
            }
            $overviewActivityPage = max(1, (int) get('apage', 1));
            $overviewActivityPerPage = 6;
            $overviewActivityTotal = count($activity);
            $overviewActivityPages = max(1, (int) ceil($overviewActivityTotal / $overviewActivityPerPage));
            if ($overviewActivityPage > $overviewActivityPages) {
                $overviewActivityPage = $overviewActivityPages;
            }
            $overviewActivitySlice = array_slice($activity, ($overviewActivityPage - 1) * $overviewActivityPerPage, $overviewActivityPerPage);
            $overviewActivityFrom = $overviewActivityTotal ? (($overviewActivityPage - 1) * $overviewActivityPerPage) + 1 : 0;
            $overviewActivityTo = min($overviewActivityPage * $overviewActivityPerPage, $overviewActivityTotal);
            $summaryIcons = [
                'documents' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 3h6l5 5v13a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>',
                'invoices' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>',
                'payments' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2.5" y="6" width="19" height="12" rx="2"/><path d="M2.5 10h19M7 15h3"/></svg>',
                'quotes' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 3h7l4 4v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M15 3v4h4M9 12h6M9 16h4"/></svg>',
            ];
        ?>
        <section class="panel case-overview-summary">
            <h2><?= __e('cases.hub.summary') ?></h2>
            <div class="case-overview-summary-grid">
                <a class="case-overview-stat" href="<?= e($tabUrl('documents')) ?>">
                    <span class="case-overview-stat-icon" aria-hidden="true"><?= $summaryIcons['documents'] ?></span>
                    <span class="case-overview-stat-copy"><strong><?= count($docs) ?></strong> <?= __e('cases.tab.documents') ?></span>
                </a>
                <a class="case-overview-stat" href="<?= e($tabUrl('invoices')) ?>">
                    <span class="case-overview-stat-icon" aria-hidden="true"><?= $summaryIcons['invoices'] ?></span>
                    <span class="case-overview-stat-copy"><strong><?= count($invoices) ?></strong> <?= __e('cases.tab.invoices') ?></span>
                </a>
                <a class="case-overview-stat" href="<?= e($tabUrl('receipts')) ?>">
                    <span class="case-overview-stat-icon" aria-hidden="true"><?= $summaryIcons['payments'] ?></span>
                    <span class="case-overview-stat-copy"><strong><?= count($payments) ?></strong> <?= __e('cases.hub.payments') ?></span>
                </a>
                <a class="case-overview-stat" href="<?= e($tabUrl('quotations')) ?>">
                    <span class="case-overview-stat-icon" aria-hidden="true"><?= $summaryIcons['quotes'] ?></span>
                    <span class="case-overview-stat-copy"><strong><?= count($quotations) ?></strong> <?= __e('cases.hub.quotes_proposals') ?></span>
                </a>
            </div>
        </section>

        <div class="case-hub-grid">
            <section class="panel case-hub-card case-overview-details">
                <h2><?= __e('cases.hub.case_details') ?></h2>
                <div class="case-hub-meta-grid">
                    <div>
                        <span class="case-hub-label"><?= __e('common.client') ?></span>
                        <strong><?= e($clientName) ?></strong>
                        <span class="muted"><?= e($case['client_company'] ?: '') ?></span>
                    </div>
                    <div>
                        <span class="case-hub-label"><?= __e('common.email') ?></span>
                        <strong><?= e($case['client_email'] ?: __('common.em_dash')) ?></strong>
                    </div>
                    <div>
                        <span class="case-hub-label"><?= __e('cases.team.title') ?></span>
                        <strong><?= e(case_lawyers_label($pdo, $id) ?: ($case['lawyer_name'] ?: __('common.unassigned'))) ?></strong>
                    </div>
                    <div>
                        <span class="case-hub-label"><?= __e('cases.hub.assigned_admin') ?></span>
                        <strong><?= e($case['assigned_admin_name'] ?: ($case['created_by_name'] ?: __('common.unassigned'))) ?></strong>
                    </div>
                </div>

                <div class="case-hub-service">
                    <h3><?= __e('cases.hub.service_fees') ?></h3>
                    <div class="case-fees-box">
                        <div class="case-fees-box-head"><?= $serviceTax > 0 ? __e('cases.hub.services') : __e('cases.hub.non_vat_services') ?></div>
                        <div class="case-fees-table-wrap">
                            <table class="case-fees-table">
                                <thead>
                                    <tr>
                                        <th><?= __e('common.service') ?></th>
                                        <th class="is-right"><?= __e('common.net') ?></th>
                                        <th class="is-right"><?= __e('common.total') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($serviceLines as $line): ?>
                                    <tr>
                                        <td><?= e($line['label']) ?></td>
                                        <td class="is-right"><?= $line['net'] > 0 ? e(money($line['net'])) : e(__('common.em_dash')) ?></td>
                                        <td class="is-right"><strong><?= $line['total'] > 0 ? e(money($line['total'])) : e(__('common.em_dash')) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td><?= __e('finance.subtotal') ?></td>
                                        <td></td>
                                        <td class="is-right"><strong><?= e(money($serviceTotal > 0 ? $serviceTotal : $feeTotal)) ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="case-hub-total">
                        <span><?= __e('cases.hub.total_fee') ?></span>
                        <strong><?= e(money($serviceTotal > 0 ? $serviceTotal : $feeTotal)) ?></strong>
                    </div>
                    <div class="case-hub-foot-meta">
                        <div>
                            <span class="case-hub-label"><?= __e('common.created') ?></span>
                            <strong><?= e(format_datetime($case['created_at'])) ?></strong>
                        </div>
                        <div>
                            <span class="case-hub-label"><?= __e('common.last_updated') ?></span>
                            <strong><?= e(format_datetime($case['updated_at'])) ?></strong>
                        </div>
                    </div>
                    <?php if (trim((string) ($case['description'] ?? '')) !== ''): ?>
                    <div class="case-overview-desc">
                        <span class="case-hub-label"><?= __e('common.description') ?></span>
                        <p><?= nl2br(e(t_content($case['description']))) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <aside class="panel case-hub-card case-overview-activity">
                <div class="case-activity-head">
                    <h2><?= __e('cases.hub.recent_activity') ?></h2>
                </div>
                <div class="case-activity-timeline case-activity-timeline--compact">
                    <?php foreach ($overviewActivitySlice as $a): ?>
                        <div class="case-activity-item type-<?= e($a['type']) ?>">
                            <div class="case-activity-icon" aria-hidden="true"><?= $activityIcons[$a['type']] ?? $activityIcons['case'] ?></div>
                            <div class="case-activity-body">
                                <strong><?= e($a['title']) ?></strong>
                                <span class="case-activity-ref"><?= e($a['ref']) ?></span>
                                <span class="case-activity-meta"><?= e($a['at'] ? date('M d, Y', strtotime($a['at'])) : '—') ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$overviewActivitySlice): ?>
                        <div class="empty-state"><?= __e('cases.activity.empty') ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($overviewActivityTotal > 0): ?>
                <div class="case-overview-activity-foot">
                    <span><?= __e('cases.hub.showing_activity', ['from' => (string) $overviewActivityFrom, 'to' => (string) $overviewActivityTo, 'total' => (string) $overviewActivityTotal]) ?></span>
                    <div class="case-overview-pager">
                        <?php if ($overviewActivityPage > 1): ?>
                            <a class="case-overview-pager-btn" href="<?= e($tabUrl('overview') . '&apage=' . ($overviewActivityPage - 1)) ?>" aria-label="<?= __e('common.previous') ?>">‹</a>
                        <?php else: ?>
                            <span class="case-overview-pager-btn is-disabled" aria-hidden="true">‹</span>
                        <?php endif; ?>
                        <span class="case-overview-pager-num"><?= (int) $overviewActivityPage ?></span>
                        <?php if ($overviewActivityPage < $overviewActivityPages): ?>
                            <a class="case-overview-pager-btn" href="<?= e($tabUrl('overview') . '&apage=' . ($overviewActivityPage + 1)) ?>" aria-label="<?= __e('common.next') ?>">›</a>
                        <?php else: ?>
                            <span class="case-overview-pager-btn is-disabled" aria-hidden="true">›</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </aside>
        </div>

        <?php elseif ($tab === 'documents'):
            $docQ = trim((string) get('dq', ''));
            $docSource = (string) get('dsource', '');
            $showRequestForm = get('add_request') === '1';
            $sourceBucket = static function (?string $role): string {
                $role = (string) $role;
                if ($role === 'client') {
                    return 'client';
                }
                if ($role === 'lawyer') {
                    return 'lawyer';
                }
                return 'admin';
            };
            $sourceLabel = static function (?string $role) use ($sourceBucket): string {
                return __('doc.source.' . $sourceBucket($role));
            };
            $filteredDocs = array_values(array_filter($docs, static function ($d) use ($docQ, $docSource, $sourceBucket) {
                if ($docSource !== '' && $sourceBucket($d['uploader_role'] ?? null) !== $docSource) {
                    return false;
                }
                if ($docQ === '') {
                    return true;
                }
                $hay = strtolower(($d['title'] ?? '') . ' ' . ($d['file_name'] ?? '') . ' ' . ($d['uploader_name'] ?? ''));
                return str_contains($hay, strtolower($docQ));
            }));
            $docFilterUrl = static function (array $extra = []) use ($tabUrl, $docQ, $docSource): string {
                $params = array_filter([
                    'dq' => $docQ !== '' ? $docQ : null,
                    'dsource' => $docSource !== '' ? $docSource : null,
                ], static fn($v) => $v !== null && $v !== '');
                $params = array_merge($params, $extra);
                $qs = $params ? '&' . http_build_query($params) : '';
                return $tabUrl('documents') . $qs;
            };
            $docTotal = count($filteredDocs);
            $docPerPage = 10;
            $docPage = max(1, (int) get('doc_page', 1));
            $docPages = max(1, (int) ceil($docTotal / $docPerPage));
            if ($docPage > $docPages) {
                $docPage = $docPages;
            }
            $pageDocs = array_slice($filteredDocs, ($docPage - 1) * $docPerPage, $docPerPage);
            $docFrom = $docTotal === 0 ? 0 : (($docPage - 1) * $docPerPage) + 1;
            $docTo = min($docPage * $docPerPage, $docTotal);
            $docPageUrl = static function (int $p) use ($tabUrl, $docQ, $docSource): string {
                $params = array_filter([
                    'dq' => $docQ !== '' ? $docQ : null,
                    'dsource' => $docSource !== '' ? $docSource : null,
                    'doc_page' => $p,
                ], static fn($v) => $v !== null && $v !== '');
                return $tabUrl('documents') . ($params ? '&' . http_build_query($params) : '');
            };
        ?>
        <section class="panel case-docs-panel">
            <div class="case-docs-top">
                <div>
                    <h2><?= __e('cases.tab.documents') ?></h2>
                    <p class="case-docs-hint"><?= __e('cases.docs.types_hint') ?></p>
                </div>
                <form method="post" enctype="multipart/form-data" class="case-docs-upload">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="upload">
                    <input type="hidden" name="case_id" value="<?= $id ?>">
                    <input type="hidden" name="client_id" value="<?= (int) $case['client_id'] ?>">
                    <input type="hidden" name="category" value="legal">
                    <input type="file" name="document" id="caseDocFile" required>
                    <button class="btn btn-primary btn-sm" type="submit">
                        <span class="case-docs-upload-ico" aria-hidden="true">↑</span>
                        <?= __e('common.upload') ?>
                    </button>
                </form>
            </div>

            <div class="case-docs-requests">
                <div class="case-docs-requests-head">
                    <strong><?= __e('cases.docs.requested_from_client') ?></strong>
                    <?php if (!$showRequestForm): ?>
                        <a href="<?= e($docFilterUrl(['add_request' => '1'])) ?>"><?= __e('cases.docs.add_request') ?></a>
                    <?php else: ?>
                        <a href="<?= e($docFilterUrl()) ?>"><?= __e('common.cancel') ?></a>
                    <?php endif; ?>
                </div>
                <?php if ($showRequestForm): ?>
                <form method="post" class="case-docs-request-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="add_doc_request">
                    <input type="hidden" name="case_id" value="<?= $id ?>">
                    <input type="hidden" name="client_id" value="<?= (int) $case['client_id'] ?>">
                    <input name="request_title" required placeholder="<?= __e('cases.docs.request_title_ph') ?>">
                    <input name="request_instructions" placeholder="<?= __e('cases.docs.request_instructions_ph') ?>">
                    <label class="case-docs-required">
                        <input type="checkbox" name="request_required" value="1" checked>
                        <?= __e('cases.docs.required') ?>
                    </label>
                    <button class="btn btn-primary btn-sm" type="submit"><?= __e('common.add') ?></button>
                </form>
                <?php endif; ?>
                <?php if ($docRequests): ?>
                <ul class="case-docs-request-list">
                    <?php foreach ($docRequests as $req): ?>
                        <li class="case-docs-request-item">
                            <div>
                                <strong><?= e($req['title']) ?></strong>
                                <?php if (!empty($req['is_required'])): ?><span class="case-docs-pill"><?= __e('cases.docs.required') ?></span><?php endif; ?>
                                <span class="case-docs-status"><?= e(__('cases.docs.request_status.' . ($req['status'] ?: 'pending'))) ?></span>
                                <?php if (!empty($req['instructions'])): ?>
                                    <div class="muted"><?= e($req['instructions']) ?></div>
                                <?php endif; ?>
                            </div>
                            <form method="post" onsubmit="return confirm(<?= json_encode(__('cases.docs.request_delete_confirm')) ?>);">
                                <?= csrf_field() ?>
                                <input type="hidden" name="form_action" value="delete_doc_request">
                                <input type="hidden" name="case_id" value="<?= $id ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                                <button type="submit" class="btn btn-row-delete btn-sm"><?= __e('common.delete') ?></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                    <p class="case-docs-empty muted"><?= __e('cases.docs.no_requests') ?></p>
                <?php endif; ?>
            </div>

            <form method="get" class="case-docs-filters appt-list-toolbar">
                <input type="hidden" name="action" value="view">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="tab" value="documents">
                <label class="appt-list-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" name="dq" value="<?= e($docQ) ?>" placeholder="<?= __e('cases.docs.search_ph') ?>" autocomplete="off">
                </label>
                <select name="dsource" onchange="this.form.submit()">
                    <option value=""><?= __e('cases.docs.all_sources') ?></option>
                    <option value="admin" <?= $docSource === 'admin' ? 'selected' : '' ?>><?= __e('doc.source.admin') ?></option>
                    <option value="lawyer" <?= $docSource === 'lawyer' ? 'selected' : '' ?>><?= __e('doc.source.lawyer') ?></option>
                    <option value="client" <?= $docSource === 'client' ? 'selected' : '' ?>><?= __e('doc.source.client') ?></option>
                </select>
            </form>

            <div class="case-docs-table-wrap">
                <table class="case-docs-table">
                    <thead>
                        <tr>
                            <th><?= __e('cases.docs.col.file') ?></th>
                            <th><?= __e('cases.docs.col.source') ?></th>
                            <th><?= __e('cases.docs.col.uploaded_by') ?></th>
                            <th><?= __e('cases.docs.col.date') ?></th>
                            <th><?= __e('cases.docs.col.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pageDocs as $d):
                        $summary = trim((string) ($d['description'] ?? ''));
                        if ($summary === '') {
                            $summary = __('cases.docs.ai_summary_fallback', [
                                'name' => (string) ($d['file_name'] ?: $d['title']),
                                'type' => (string) ($d['file_type'] ?: __('common.file')),
                            ]);
                        }
                        ?>
                        <tr>
                            <td>
                                <strong><?= e($d['file_name'] ?: $d['title']) ?></strong>
                                <div class="muted"><?= e(format_file_size((int) ($d['file_size'] ?? 0))) ?></div>
                                <details class="case-docs-ai">
                                    <summary><?= __e('cases.docs.ai_summary') ?></summary>
                                    <p><?= e($summary) ?></p>
                                </details>
                            </td>
                            <td><span class="case-docs-source"><?= e($sourceLabel($d['uploader_role'] ?? null)) ?></span></td>
                            <td><?= e($d['uploader_name'] ?: __('common.em_dash')) ?></td>
                            <td><?= e(format_datetime($d['created_at'])) ?></td>
                            <td class="col-actions">
                                <div class="row-actions">
                                    <a class="btn btn-row-open btn-sm" href="../<?= e($d['file_path']) ?>" target="_blank" download><?= __e('common.download') ?></a>
                                    <form method="post" data-confirm="<?= e(__('cases.docs.delete_confirm')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="form_action" value="delete_document">
                                        <input type="hidden" name="case_id" value="<?= $id ?>">
                                        <input type="hidden" name="document_id" value="<?= (int) $d['id'] ?>">
                                        <button type="submit" class="btn btn-row-delete btn-sm"><?= __e('common.delete') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$pageDocs): ?>
                        <tr><td colspan="5" class="muted"><?= __e('cases.no_documents') ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="case-hub-foot">
                <p class="case-list-footer muted"><?= e(__($docTotal === 1 ? 'cases.pager.showing_one' : 'cases.pager.showing_many', ['from' => (int) $docFrom, 'to' => (int) $docTo, 'total' => (int) $docTotal])) ?></p>
                <?php if ($docPages > 1): ?>
                <nav class="case-list-pager" aria-label="<?= __e('cases.pagination.aria') ?>">
                    <?php if ($docPage > 1): ?>
                    <a class="case-page-btn" href="<?= e($docPageUrl($docPage - 1)) ?>" aria-label="<?= __e('cases.pagination.prev') ?>">‹</a>
                    <?php else: ?>
                    <span class="case-page-btn is-disabled" aria-disabled="true">‹</span>
                    <?php endif; ?>
                    <?php for ($dp = 1; $dp <= $docPages; $dp++): ?>
                    <a class="case-page-btn<?= $dp === $docPage ? ' is-active' : '' ?>" href="<?= e($docPageUrl($dp)) ?>"<?= $dp === $docPage ? ' aria-current="page"' : '' ?>><?= $dp ?></a>
                    <?php endfor; ?>
                    <?php if ($docPage < $docPages): ?>
                    <a class="case-page-btn" href="<?= e($docPageUrl($docPage + 1)) ?>" aria-label="<?= __e('cases.pagination.next') ?>">›</a>
                    <?php else: ?>
                    <span class="case-page-btn is-disabled" aria-disabled="true">›</span>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            </div>
        </section>

        <?php elseif ($tab === 'quotations'): 
            $quoteQ = trim((string) get('q', ''));
            $filteredQuotations = array_values(array_filter($quotations, static function ($qRow) use ($quoteQ) {
                if ($quoteQ === '') {
                    return true;
                }
                $hay = strtolower(($qRow['invoice_number'] ?? '') . ' ' . ($qRow['title'] ?? ''));
                return str_contains($hay, strtolower($quoteQ));
            }));
            $quoteTotal = count($filteredQuotations);
            $quotePerPage = 10;
            $quotePage = max(1, (int) get('quote_page', 1));
            $quotePages = max(1, (int) ceil($quoteTotal / $quotePerPage));
            if ($quotePage > $quotePages) {
                $quotePage = $quotePages;
            }
            $pageQuotations = array_slice($filteredQuotations, ($quotePage - 1) * $quotePerPage, $quotePerPage);
            $quoteFrom = $quoteTotal === 0 ? 0 : (($quotePage - 1) * $quotePerPage) + 1;
            $quoteTo = min($quotePage * $quotePerPage, $quoteTotal);
            $quotePageUrl = static function (int $p) use ($tabUrl, $quoteQ): string {
                $params = array_filter([
                    'q' => $quoteQ !== '' ? $quoteQ : null,
                    'quote_page' => $p,
                ], static fn($v) => $v !== null && $v !== '');
                return $tabUrl('quotations') . ($params ? '&' . http_build_query($params) : '');
            };
        ?>
        <section class="panel case-hub-card">
            <div class="panel-header">
                <h2><?= __e('cases.tab.quotations') ?></h2>
                <a class="btn btn-primary btn-sm" href="<?= e($tabUrl('quotations')) ?>&compose=quotation"><?= __e('cases.new_quotation') ?></a>
            </div>
            <p class="muted" style="margin-top:0;"><?= __e('cases.quotations_help') ?></p>
            <form method="get" class="appt-list-toolbar" action="cases.php">
                <input type="hidden" name="action" value="view">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="tab" value="quotations">
                <label class="appt-list-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" name="q" value="<?= e($quoteQ) ?>" placeholder="<?= __e('finance.search_invoices') ?>" autocomplete="off">
                </label>
            </form>
            <?php if ($compose === 'quotation'):
                $quoteTitleDefault = __('cases.quotation_title_named', [
                    'title' => (string) ($case['title'] ?: $case['case_number']),
                    'client' => $clientName !== '' ? $clientName : __('common.client'),
                ]);
            ?>
            <div class="quote-gen-shell">
                <div class="panel quote-gen-panel">
                    <div class="quote-gen-header">
                        <h2><?= __e('cases.generate_quotation') ?></h2>
                        <a class="quote-gen-close" href="<?= e($tabUrl('quotations')) ?>" aria-label="<?= __e('common.close') ?>">×</a>
                    </div>
                    <form method="post" class="quote-gen-form" id="quotationGenerateForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_action" value="quotation">
                        <input type="hidden" name="case_id" value="<?= $id ?>">
                        <input type="hidden" name="client_id" value="<?= (int)$case['client_id'] ?>">
                        <input type="hidden" name="issued_at" value="<?= e(date('Y-m-d')) ?>">

                        <div class="form-group">
                            <label><?= __e('common.title') ?></label>
                            <input name="title" required value="<?= e($quoteTitleDefault) ?>">
                        </div>
                        <div class="form-group">
                            <label><?= __e('cases.amount_incl_vat') ?></label>
                            <input type="number" step="0.01" min="0" name="amount" required value="0">
                        </div>
                        <div class="form-group">
                            <label><?= __e('cases.tax_rate') ?></label>
                            <input type="number" step="0.01" min="0" max="100" name="tax_rate" value="0">
                        </div>
                        <div class="form-group">
                            <label><?= __e('cases.valid_until') ?></label>
                            <input type="date" name="due_date" value="<?= e(date('Y-m-d', strtotime('+30 days'))) ?>">
                        </div>

                        <div class="quote-gen-actions">
                            <a class="btn btn-secondary" href="<?= e($tabUrl('quotations')) ?>"><?= __e('common.cancel') ?></a>
                            <button class="btn btn-primary" type="submit"><?= __e('finance.generate') ?></button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th><?= __e('common.quotation') ?></th><th><?= __e('common.total') ?></th><th><?= __e('common.created') ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($pageQuotations as $q): ?>
                        <tr>
                            <td><strong><?= e($q['invoice_number']) ?></strong><div class="muted"><?= e($q['title']) ?></div></td>
                            <td><?= e(money($q['total'])) ?></td>
                            <td><?= e(format_date($q['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$pageQuotations): ?><tr><td colspan="3" class="muted"><?= __e('cases.no_quotations') ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="case-hub-foot">
                <p class="case-list-footer muted"><?= e(__($quoteTotal === 1 ? 'cases.pager.showing_one' : 'cases.pager.showing_many', ['from' => (int) $quoteFrom, 'to' => (int) $quoteTo, 'total' => (int) $quoteTotal])) ?></p>
                <?php if ($quotePages > 1): ?>
                <nav class="case-list-pager" aria-label="<?= __e('cases.pagination.aria') ?>">
                    <?php if ($quotePage > 1): ?>
                    <a class="case-page-btn" href="<?= e($quotePageUrl($quotePage - 1)) ?>" aria-label="<?= __e('cases.pagination.prev') ?>">‹</a>
                    <?php else: ?>
                    <span class="case-page-btn is-disabled" aria-disabled="true">‹</span>
                    <?php endif; ?>
                    <?php for ($qp = 1; $qp <= $quotePages; $qp++): ?>
                    <a class="case-page-btn<?= $qp === $quotePage ? ' is-active' : '' ?>" href="<?= e($quotePageUrl($qp)) ?>"<?= $qp === $quotePage ? ' aria-current="page"' : '' ?>><?= $qp ?></a>
                    <?php endfor; ?>
                    <?php if ($quotePage < $quotePages): ?>
                    <a class="case-page-btn" href="<?= e($quotePageUrl($quotePage + 1)) ?>" aria-label="<?= __e('cases.pagination.next') ?>">›</a>
                    <?php else: ?>
                    <span class="case-page-btn is-disabled" aria-disabled="true">›</span>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
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
            $invTotal = count($filteredInvoices);
            $invPerPage = 10;
            $invPage = max(1, (int) get('inv_page', 1));
            $invPages = max(1, (int) ceil($invTotal / $invPerPage));
            if ($invPage > $invPages) {
                $invPage = $invPages;
            }
            $pageInvoices = array_slice($filteredInvoices, ($invPage - 1) * $invPerPage, $invPerPage);
            $invFrom = $invTotal === 0 ? 0 : (($invPage - 1) * $invPerPage) + 1;
            $invTo = min($invPage * $invPerPage, $invTotal);
            $invPageUrl = static function (int $p) use ($tabUrl, $invQ, $invStatus): string {
                $params = array_filter([
                    'q' => $invQ !== '' ? $invQ : null,
                    'status' => $invStatus !== '' ? $invStatus : null,
                    'inv_page' => $p,
                ], static fn($v) => $v !== null && $v !== '');
                return $tabUrl('invoices') . ($params ? '&' . http_build_query($params) : '');
            };
        ?>
        <section class="panel case-hub-card inv-list-panel">
            <div class="panel-header">
                <h2><?= __e('finance.invoices') ?></h2>
                <a class="btn btn-primary btn-sm" href="invoice.php?action=generate&case_id=<?= $id ?>&client_id=<?= (int) $case['client_id'] ?>&from=<?= e(urlencode($caseInvReturn)) ?>">+ <?= __e('finance.generate_invoice') ?></a>
            </div>

            <form method="get" class="inv-list-filters appt-list-toolbar" action="cases.php">
                <input type="hidden" name="action" value="view">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="tab" value="invoices">
                <label class="appt-list-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" name="q" value="<?= e($invQ) ?>" placeholder="<?= __e('finance.search_invoices') ?>" autocomplete="off">
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
                            <th><?= __e('finance.payment_status') ?></th>
                            <th class="col-actions"><?= __e('common.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pageInvoices as $i): ?>
                        <tr>
                            <td>
                                <strong><?= e($i['invoice_number']) ?></strong>
                                <div class="muted"><?= e($i['title']) ?></div>
                            </td>
                            <td><?= e(money($i['total'])) ?></td>
                            <td><?= e(format_date($i['due_date'], 'M j, Y')) ?></td>
                            <td><?= status_badge($i['status']) ?></td>
                            <td><?= payment_status_badge(invoice_payment_status($i)) ?></td>
                            <td class="col-actions">
                                <div class="row-actions">
                                    <a class="btn btn-row-open btn-sm" href="invoice.php?id=<?= (int) $i['id'] ?>&from=<?= e(urlencode($caseInvReturn)) ?>"><?= __e('common.view') ?></a>
                                    <form method="post" action="invoice.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="form_action" value="email_client">
                                        <input type="hidden" name="invoice_id" value="<?= (int) $i['id'] ?>">
                                        <input type="hidden" name="return_to" value="<?= e($caseInvReturn) ?>">
                                        <button class="btn btn-row-open btn-sm" type="submit"><?= __e('finance.email_client') ?></button>
                                    </form>
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
                    <?php if (!$pageInvoices): ?>
                        <tr><td colspan="6" class="muted"><?= __e('finance.no_invoices') ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="case-hub-foot">
                <p class="case-list-footer muted"><?= e(__($invTotal === 1 ? 'cases.pager.showing_one' : 'cases.pager.showing_many', ['from' => (int) $invFrom, 'to' => (int) $invTo, 'total' => (int) $invTotal])) ?></p>
                <?php if ($invPages > 1): ?>
                <nav class="case-list-pager" aria-label="<?= __e('cases.pagination.aria') ?>">
                    <?php if ($invPage > 1): ?>
                    <a class="case-page-btn" href="<?= e($invPageUrl($invPage - 1)) ?>" aria-label="<?= __e('cases.pagination.prev') ?>">‹</a>
                    <?php else: ?>
                    <span class="case-page-btn is-disabled" aria-disabled="true">‹</span>
                    <?php endif; ?>
                    <?php for ($ip = 1; $ip <= $invPages; $ip++): ?>
                    <a class="case-page-btn<?= $ip === $invPage ? ' is-active' : '' ?>" href="<?= e($invPageUrl($ip)) ?>"<?= $ip === $invPage ? ' aria-current="page"' : '' ?>><?= $ip ?></a>
                    <?php endfor; ?>
                    <?php if ($invPage < $invPages): ?>
                    <a class="case-page-btn" href="<?= e($invPageUrl($invPage + 1)) ?>" aria-label="<?= __e('cases.pagination.next') ?>">›</a>
                    <?php else: ?>
                    <span class="case-page-btn is-disabled" aria-disabled="true">›</span>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            </div>
        </section>

        <?php elseif ($tab === 'receipts'):
            $preselectPayInvoice = (int) get('invoice_id', 0);
            $rcpQ = trim((string) get('q', ''));
            $caseRcpReturn = 'cases.php?action=view&id=' . $id . '&tab=receipts';
            $filteredPayments = array_values(array_filter($payments, static function ($p) use ($rcpQ) {
                if ($rcpQ === '') {
                    return true;
                }
                $hay = strtolower(($p['receipt_number'] ?? '') . ' ' . ($p['invoice_number'] ?? '') . ' ' . ($p['reference_number'] ?? ''));
                return str_contains($hay, strtolower($rcpQ));
            }));
            $rcpTotal = count($filteredPayments);
            $rcpPerPage = 10;
            $rcpPage = max(1, (int) get('rcp_page', 1));
            $rcpPages = max(1, (int) ceil($rcpTotal / $rcpPerPage));
            if ($rcpPage > $rcpPages) {
                $rcpPage = $rcpPages;
            }
            $pagePayments = array_slice($filteredPayments, ($rcpPage - 1) * $rcpPerPage, $rcpPerPage);
            $rcpFrom = $rcpTotal === 0 ? 0 : (($rcpPage - 1) * $rcpPerPage) + 1;
            $rcpTo = min($rcpPage * $rcpPerPage, $rcpTotal);
            $rcpPageUrl = static function (int $p) use ($tabUrl, $rcpQ): string {
                $params = array_filter([
                    'q' => $rcpQ !== '' ? $rcpQ : null,
                    'rcp_page' => $p,
                ], static fn($v) => $v !== null && $v !== '');
                return $tabUrl('receipts') . ($params ? '&' . http_build_query($params) : '');
            };
        ?>
        <section class="panel case-hub-card inv-list-panel">
            <div class="panel-header">
                <h2><?= __e('finance.receipts') ?></h2>
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

            <form method="get" class="inv-list-filters appt-list-toolbar" action="cases.php">
                <input type="hidden" name="action" value="view">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="tab" value="receipts">
                <label class="appt-list-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" name="q" value="<?= e($rcpQ) ?>" placeholder="<?= __e('finance.search_receipts') ?>" autocomplete="off">
                </label>
                <?php if ($rcpQ !== ''): ?>
                    <a class="btn btn-secondary btn-sm" href="<?= e($tabUrl('receipts')) ?>"><?= __e('common.clear') ?></a>
                <?php endif; ?>
            </form>

            <div class="table-wrap">
                <table class="inv-list-table">
                    <thead>
                        <tr>
                            <th><?= __e('finance.receipt') ?></th>
                            <th><?= __e('common.amount') ?></th>
                            <th><?= __e('finance.method') ?></th>
                            <th><?= __e('finance.invoice_number') ?></th>
                            <th><?= __e('common.date') ?></th>
                            <th class="col-actions"><?= __e('common.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pagePayments as $p): ?>
                        <tr>
                            <td>
                                <a class="inv-list-number" href="receipt.php?id=<?= (int) $p['id'] ?>&from=<?= e(urlencode($caseRcpReturn)) ?>"><strong><?= e($p['receipt_number']) ?></strong></a>
                                <?php if (!empty($p['reference_number'])): ?>
                                    <div class="muted"><?= e($p['reference_number']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e(money($p['amount'])) ?></td>
                            <td><?= e(__('payment.method.' . ($p['payment_method'] ?: 'other'))) ?></td>
                            <td><?= e($p['invoice_number'] ?: __('common.em_dash')) ?></td>
                            <td><?= e(format_datetime($p['paid_at'])) ?></td>
                            <td class="col-actions">
                                <div class="row-actions">
                                    <a class="btn btn-row-open btn-sm" href="receipt.php?id=<?= (int) $p['id'] ?>&from=<?= e(urlencode($caseRcpReturn)) ?>"><?= __e('common.view') ?></a>
                                    <form method="post" action="receipt.php" onsubmit="return confirm(<?= json_encode(__('finance.delete_receipt_confirm', ['number' => $p['receipt_number'] ?: ('#' . $p['id'])]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="form_action" value="delete_payment">
                                        <input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
                                        <input type="hidden" name="return_to" value="<?= e($caseRcpReturn) ?>">
                                        <button class="btn btn-row-delete btn-sm" type="submit"><?= __e('common.delete') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$pagePayments): ?>
                        <tr><td colspan="6" class="muted"><?= __e('finance.no_receipts') ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="case-hub-foot">
                <p class="case-list-footer muted"><?= e(__($rcpTotal === 1 ? 'cases.pager.showing_one' : 'cases.pager.showing_many', ['from' => (int) $rcpFrom, 'to' => (int) $rcpTo, 'total' => (int) $rcpTotal])) ?></p>
                <?php if ($rcpPages > 1): ?>
                <nav class="case-list-pager" aria-label="<?= __e('cases.pagination.aria') ?>">
                    <?php if ($rcpPage > 1): ?>
                    <a class="case-page-btn" href="<?= e($rcpPageUrl($rcpPage - 1)) ?>" aria-label="<?= __e('cases.pagination.prev') ?>">‹</a>
                    <?php else: ?>
                    <span class="case-page-btn is-disabled" aria-disabled="true">‹</span>
                    <?php endif; ?>
                    <?php for ($rp = 1; $rp <= $rcpPages; $rp++): ?>
                    <a class="case-page-btn<?= $rp === $rcpPage ? ' is-active' : '' ?>" href="<?= e($rcpPageUrl($rp)) ?>"<?= $rp === $rcpPage ? ' aria-current="page"' : '' ?>><?= $rp ?></a>
                    <?php endfor; ?>
                    <?php if ($rcpPage < $rcpPages): ?>
                    <a class="case-page-btn" href="<?= e($rcpPageUrl($rcpPage + 1)) ?>" aria-label="<?= __e('cases.pagination.next') ?>">›</a>
                    <?php else: ?>
                    <span class="case-page-btn is-disabled" aria-disabled="true">›</span>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
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

        <?php elseif ($tab === 'tasks'):
            $editTaskId = (int) get('task_id', 0);
            $editingTask = null;
            if ($editTaskId) {
                foreach ($caseTasks as $taskRow) {
                    if ((int) $taskRow['id'] === $editTaskId) {
                        $editingTask = $taskRow;
                        break;
                    }
                }
            }
            $showTaskForm = $compose === 'task' || $editingTask;
            $taskQ = trim((string) get('q', ''));
            $filteredTasks = array_values(array_filter($caseTasks, static function ($taskRow) use ($taskQ) {
                if ($taskQ === '') {
                    return true;
                }
                $hay = strtolower(($taskRow['title'] ?? '') . ' ' . ($taskRow['description'] ?? '') . ' ' . ($taskRow['assignee_name'] ?? ''));
                return str_contains($hay, strtolower($taskQ));
            }));
            $taskTotal = count($filteredTasks);
            $taskPerPage = 10;
            $taskPage = max(1, (int) get('task_page', 1));
            $taskPages = max(1, (int) ceil($taskTotal / $taskPerPage));
            if ($taskPage > $taskPages) {
                $taskPage = $taskPages;
            }
            $pageTasks = array_slice($filteredTasks, ($taskPage - 1) * $taskPerPage, $taskPerPage);
            $taskFrom = $taskTotal === 0 ? 0 : (($taskPage - 1) * $taskPerPage) + 1;
            $taskTo = min($taskPage * $taskPerPage, $taskTotal);
            $taskPageUrl = static function (int $p) use ($tabUrl, $taskQ): string {
                $params = array_filter([
                    'q' => $taskQ !== '' ? $taskQ : null,
                    'task_page' => $p,
                ], static fn($v) => $v !== null && $v !== '');
                return $tabUrl('tasks') . ($params ? '&' . http_build_query($params) : '');
            };
        ?>
        <section class="panel case-hub-card">
            <div class="panel-header">
                <h2><?= __e('cases.tab.tasks') ?></h2>
                <a class="btn btn-primary btn-sm" href="<?= e($tabUrl('tasks')) ?>&compose=task"><?= __e('cases.tasks.add') ?></a>
            </div>
            <form method="get" class="appt-list-toolbar" action="cases.php">
                <input type="hidden" name="action" value="view">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="tab" value="tasks">
                <label class="appt-list-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" name="q" value="<?= e($taskQ) ?>" placeholder="<?= __e('common.search') ?>" autocomplete="off">
                </label>
            </form>
            <?php if ($showTaskForm): ?>
            <form method="post" class="form-grid entity-inline-form" style="margin-bottom:1rem;">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="save_task">
                <input type="hidden" name="case_id" value="<?= $id ?>">
                <input type="hidden" name="task_id" value="<?= (int) ($editingTask['id'] ?? 0) ?>">
                <div class="entity-field-row entity-field-row--2">
                    <div class="form-group"><label><?= __e('common.title') ?> <span class="req">*</span></label><input name="title" required value="<?= e($editingTask['title'] ?? '') ?>"></div>
                    <div class="form-group"><label><?= __e('cases.tasks.assignee') ?></label>
                        <select name="assigned_to">
                            <option value=""><?= __e('cases.tasks.unassigned') ?></option>
                            <?php foreach ($caseTeamRows as $teamLawyer): ?>
                                <option value="<?= (int) $teamLawyer['lawyer_id'] ?>" <?= (int) ($editingTask['assigned_to'] ?? 0) === (int) $teamLawyer['lawyer_id'] ? 'selected' : '' ?>>
                                    <?= e(trim(($teamLawyer['first_name'] ?? '') . ' ' . ($teamLawyer['last_name'] ?? ''))) ?><?= ($teamLawyer['role'] ?? '') === 'lead' ? ' (' . __('cases.team.lead') . ')' : ' (' . __('cases.team.associate') . ')' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="entity-field-row entity-field-row--2">
                    <div class="form-group"><label><?= __e('cases.tasks.due_date') ?></label><input type="date" name="due_date" value="<?= e($editingTask['due_date'] ?? '') ?>"></div>
                    <div class="form-group"><label><?= __e('common.status') ?></label>
                        <select name="status">
                            <?php foreach (case_task_statuses() as $taskStatus): ?>
                                <option value="<?= e($taskStatus) ?>" <?= ($editingTask['status'] ?? 'open') === $taskStatus ? 'selected' : '' ?>><?= e(__('cases.tasks.status.' . $taskStatus)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group full"><label><?= __e('common.description') ?></label><textarea name="description" rows="3"><?= e($editingTask['description'] ?? '') ?></textarea></div>
                <div class="form-actions full">
                    <button class="btn btn-primary btn-sm" type="submit"><?= __e($editingTask ? 'common.save' : 'cases.tasks.add') ?></button>
                    <a class="btn btn-secondary btn-sm" href="<?= e($tabUrl('tasks')) ?>"><?= __e('common.cancel') ?></a>
                </div>
            </form>
            <?php endif; ?>
            <div class="table-wrap">
                <table class="case-table">
                    <thead>
                        <tr>
                            <th><?= __e('common.title') ?></th>
                            <th><?= __e('cases.tasks.assignee') ?></th>
                            <th><?= __e('cases.tasks.due_date') ?></th>
                            <th><?= __e('common.status') ?></th>
                            <th class="col-actions"><?= __e('common.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pageTasks as $taskRow): ?>
                        <tr>
                            <td>
                                <strong><?= e($taskRow['title']) ?></strong>
                                <?php if (!empty($taskRow['description'])): ?><div class="muted"><?= e($taskRow['description']) ?></div><?php endif; ?>
                            </td>
                            <td><?= e($taskRow['assignee_name'] ?: __('cases.tasks.unassigned')) ?></td>
                            <td><?= e(format_date($taskRow['due_date'])) ?></td>
                            <td><?= status_badge($taskRow['status']) ?></td>
                            <td class="col-actions">
                                <div class="row-actions">
                                    <a class="btn btn-row-edit btn-sm" href="<?= e($tabUrl('tasks')) ?>&task_id=<?= (int) $taskRow['id'] ?>"><?= __e('common.edit') ?></a>
                                    <form method="post" class="inline-form" onsubmit="return confirm('<?= __e('cases.tasks.confirm_delete') ?>');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="form_action" value="delete_task">
                                        <input type="hidden" name="case_id" value="<?= $id ?>">
                                        <input type="hidden" name="task_id" value="<?= (int) $taskRow['id'] ?>">
                                        <button class="btn btn-row-delete btn-sm" type="submit"><?= __e('common.delete') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$pageTasks): ?>
                        <tr><td colspan="5" class="muted"><?= __e('cases.tasks.empty') ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="case-hub-foot">
                <p class="case-list-footer muted"><?= e(__($taskTotal === 1 ? 'cases.pager.showing_one' : 'cases.pager.showing_many', ['from' => (int) $taskFrom, 'to' => (int) $taskTo, 'total' => (int) $taskTotal])) ?></p>
                <?php if ($taskPages > 1): ?>
                <nav class="case-list-pager" aria-label="<?= __e('cases.pagination.aria') ?>">
                    <?php if ($taskPage > 1): ?>
                    <a class="case-page-btn" href="<?= e($taskPageUrl($taskPage - 1)) ?>" aria-label="<?= __e('cases.pagination.prev') ?>">‹</a>
                    <?php else: ?>
                    <span class="case-page-btn is-disabled" aria-disabled="true">‹</span>
                    <?php endif; ?>
                    <?php for ($tp = 1; $tp <= $taskPages; $tp++): ?>
                    <a class="case-page-btn<?= $tp === $taskPage ? ' is-active' : '' ?>" href="<?= e($taskPageUrl($tp)) ?>"<?= $tp === $taskPage ? ' aria-current="page"' : '' ?>><?= $tp ?></a>
                    <?php endfor; ?>
                    <?php if ($taskPage < $taskPages): ?>
                    <a class="case-page-btn" href="<?= e($taskPageUrl($taskPage + 1)) ?>" aria-label="<?= __e('cases.pagination.next') ?>">›</a>
                    <?php else: ?>
                    <span class="case-page-btn is-disabled" aria-disabled="true">›</span>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            </div>
        </section>

        <?php elseif ($tab === 'deadlines'):
            $dlQ = trim((string) get('q', ''));
            $filteredHearings = array_values(array_filter($hearings, static function ($h) use ($dlQ) {
                if ($dlQ === '') {
                    return true;
                }
                $hay = strtolower(($h['court_name'] ?? '') . ' ' . ($h['hearing_type'] ?? '') . ' ' . ($h['outcome'] ?? ''));
                return str_contains($hay, strtolower($dlQ));
            }));
            $dlTotal = count($filteredHearings);
            $dlPerPage = 10;
            $dlPage = max(1, (int) get('dl_page', 1));
            $dlPages = max(1, (int) ceil($dlTotal / $dlPerPage));
            if ($dlPage > $dlPages) {
                $dlPage = $dlPages;
            }
            $pageHearings = array_slice($filteredHearings, ($dlPage - 1) * $dlPerPage, $dlPerPage);
            $dlFrom = $dlTotal === 0 ? 0 : (($dlPage - 1) * $dlPerPage) + 1;
            $dlTo = min($dlPage * $dlPerPage, $dlTotal);
            $dlPageUrl = static function (int $p) use ($tabUrl, $dlQ): string {
                $params = array_filter([
                    'q' => $dlQ !== '' ? $dlQ : null,
                    'dl_page' => $p,
                ], static fn($v) => $v !== null && $v !== '');
                return $tabUrl('deadlines') . ($params ? '&' . http_build_query($params) : '');
            };
        ?>
        <section class="panel case-hub-card">
            <div class="panel-header"><h2><?= __e('cases.deadlines_title') ?></h2><a class="btn btn-primary btn-sm" href="court.php?action=create&case_id=<?= $id ?>"><?= __e('cases.add_hearing') ?></a></div>
            <form method="get" class="appt-list-toolbar" action="cases.php">
                <input type="hidden" name="action" value="view">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="tab" value="deadlines">
                <label class="appt-list-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" name="q" value="<?= e($dlQ) ?>" placeholder="<?= __e('common.search') ?>" autocomplete="off">
                </label>
            </form>
            <div class="list-item" style="margin-bottom:1rem;"><strong><?= __e('form.next_hearing') ?></strong><?= e(format_date($case['next_hearing_date'])) ?></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th><?= __e('common.date') ?></th><th><?= __e('common.court') ?></th><th><?= __e('common.type') ?></th><th><?= __e('common.status') ?></th><th><?= __e('common.outcome') ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($pageHearings as $h): ?>
                        <tr>
                            <td><?= e(format_datetime($h['hearing_date'])) ?></td>
                            <td><?= e($h['court_name']) ?><div class="muted"><?= e($h['court_location']) ?></div></td>
                            <td><?= e($h['hearing_type'] ?: __('common.em_dash')) ?></td>
                            <td><?= status_badge($h['status']) ?></td>
                            <td><?= e($h['outcome'] ?: __('common.em_dash')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$pageHearings): ?><tr><td colspan="5" class="muted"><?= __e('cases.no_hearings') ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="case-hub-foot">
                <p class="case-list-footer muted"><?= e(__($dlTotal === 1 ? 'cases.pager.showing_one' : 'cases.pager.showing_many', ['from' => (int) $dlFrom, 'to' => (int) $dlTo, 'total' => (int) $dlTotal])) ?></p>
                <?php if ($dlPages > 1): ?>
                <nav class="case-list-pager" aria-label="<?= __e('cases.pagination.aria') ?>">
                    <?php if ($dlPage > 1): ?>
                    <a class="case-page-btn" href="<?= e($dlPageUrl($dlPage - 1)) ?>" aria-label="<?= __e('cases.pagination.prev') ?>">‹</a>
                    <?php else: ?>
                    <span class="case-page-btn is-disabled" aria-disabled="true">‹</span>
                    <?php endif; ?>
                    <?php for ($dp = 1; $dp <= $dlPages; $dp++): ?>
                    <a class="case-page-btn<?= $dp === $dlPage ? ' is-active' : '' ?>" href="<?= e($dlPageUrl($dp)) ?>"<?= $dp === $dlPage ? ' aria-current="page"' : '' ?>><?= $dp ?></a>
                    <?php endfor; ?>
                    <?php if ($dlPage < $dlPages): ?>
                    <a class="case-page-btn" href="<?= e($dlPageUrl($dlPage + 1)) ?>" aria-label="<?= __e('cases.pagination.next') ?>">›</a>
                    <?php else: ?>
                    <span class="case-page-btn is-disabled" aria-disabled="true">›</span>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            </div>
        </section>

        <?php elseif ($tab === 'notes'):
            $noteQ = trim((string) get('q', ''));
            $filteredNotes = array_values(array_filter($notes, static function ($n) use ($noteQ) {
                if ($noteQ === '') {
                    return true;
                }
                $hay = strtolower(($n['author'] ?? '') . ' ' . ($n['note'] ?? ''));
                return str_contains($hay, strtolower($noteQ));
            }));
            $noteTotal = count($filteredNotes);
            $notePerPage = 10;
            $notePage = max(1, (int) get('note_page', 1));
            $notePages = max(1, (int) ceil($noteTotal / $notePerPage));
            if ($notePage > $notePages) {
                $notePage = $notePages;
            }
            $pageNotes = array_slice($filteredNotes, ($notePage - 1) * $notePerPage, $notePerPage);
            $noteFrom = $noteTotal === 0 ? 0 : (($notePage - 1) * $notePerPage) + 1;
            $noteTo = min($notePage * $notePerPage, $noteTotal);
            $notePageUrl = static function (int $p) use ($tabUrl, $noteQ): string {
                $params = array_filter([
                    'q' => $noteQ !== '' ? $noteQ : null,
                    'note_page' => $p,
                ], static fn($v) => $v !== null && $v !== '');
                return $tabUrl('notes') . ($params ? '&' . http_build_query($params) : '');
            };
        ?>
        <section class="panel case-hub-card">
            <div class="panel-header">
                <h2><?= __e('cases.tab.notes') ?></h2>
            </div>
            <form method="get" class="appt-list-toolbar" action="cases.php">
                <input type="hidden" name="action" value="view">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="tab" value="notes">
                <label class="appt-list-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" name="q" value="<?= e($noteQ) ?>" placeholder="<?= __e('common.search') ?>" autocomplete="off">
                </label>
            </form>
            <form method="post" class="form-grid entity-inline-form case-add-note-form" style="margin-bottom:1rem;">
                <?= csrf_field() ?><input type="hidden" name="form_action" value="note"><input type="hidden" name="case_id" value="<?= $id ?>">
                <div class="case-add-note-head">
                    <label for="case-note-input"><?= __e('cases.add_note') ?></label>
                    <button class="btn btn-primary btn-sm" type="submit"><?= __e('cases.add_note') ?></button>
                </div>
                <div class="form-group full">
                    <textarea id="case-note-input" name="note" required rows="2" placeholder="<?= __e('cases.add_note_ph') ?>"></textarea>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="is_private" value="1"> <?= __e('cases.private_note') ?></label>
                </div>
            </form>
            <div class="list-stack">
                <?php foreach ($pageNotes as $n): ?>
                    <div class="list-item"><strong><?= e($n['author']) ?><?= $n['is_private'] ? ' ' . __('cases.private_suffix') : '' ?></strong><span class="muted"><?= e(format_datetime($n['created_at'])) ?></span><div><?= nl2br(e($n['note'])) ?></div></div>
                <?php endforeach; ?>
                <?php if (!$pageNotes): ?><div class="empty-state"><?= __e('cases.no_notes') ?></div><?php endif; ?>
            </div>
            <div class="case-hub-foot">
                <p class="case-list-footer muted"><?= e(__($noteTotal === 1 ? 'cases.pager.showing_one' : 'cases.pager.showing_many', ['from' => (int) $noteFrom, 'to' => (int) $noteTo, 'total' => (int) $noteTotal])) ?></p>
                <?php if ($notePages > 1): ?>
                <nav class="case-list-pager" aria-label="<?= __e('cases.pagination.aria') ?>">
                    <?php if ($notePage > 1): ?>
                    <a class="case-page-btn" href="<?= e($notePageUrl($notePage - 1)) ?>" aria-label="<?= __e('cases.pagination.prev') ?>">‹</a>
                    <?php else: ?>
                    <span class="case-page-btn is-disabled" aria-disabled="true">‹</span>
                    <?php endif; ?>
                    <?php for ($np = 1; $np <= $notePages; $np++): ?>
                    <a class="case-page-btn<?= $np === $notePage ? ' is-active' : '' ?>" href="<?= e($notePageUrl($np)) ?>"<?= $np === $notePage ? ' aria-current="page"' : '' ?>><?= $np ?></a>
                    <?php endfor; ?>
                    <?php if ($notePage < $notePages): ?>
                    <a class="case-page-btn" href="<?= e($notePageUrl($notePage + 1)) ?>" aria-label="<?= __e('cases.pagination.next') ?>">›</a>
                    <?php else: ?>
                    <span class="case-page-btn is-disabled" aria-disabled="true">›</span>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            </div>
        </section>

        <?php else:
            $activityQ = trim((string) get('q', ''));
            $filteredActivity = array_values(array_filter($activity, static function ($a) use ($activityQ) {
                if ($activityQ === '') {
                    return true;
                }
                $hay = strtolower(($a['title'] ?? '') . ' ' . ($a['ref'] ?? '') . ' ' . ($a['type'] ?? ''));
                return str_contains($hay, strtolower($activityQ));
            }));
            $activityTotal = count($filteredActivity);
            $activityPerPage = 10;
            $activityPage = max(1, (int) get('activity_page', 1));
            $activityPages = max(1, (int) ceil($activityTotal / $activityPerPage));
            if ($activityPage > $activityPages) {
                $activityPage = $activityPages;
            }
            $pageActivity = array_slice($filteredActivity, ($activityPage - 1) * $activityPerPage, $activityPerPage);
            $activityFrom = $activityTotal === 0 ? 0 : (($activityPage - 1) * $activityPerPage) + 1;
            $activityTo = min($activityPage * $activityPerPage, $activityTotal);
            $activityPageUrl = static function (int $p) use ($tabUrl, $activityQ): string {
                $params = array_filter([
                    'q' => $activityQ !== '' ? $activityQ : null,
                    'activity_page' => $p,
                ], static fn($v) => $v !== null && $v !== '');
                return $tabUrl('activity') . ($params ? '&' . http_build_query($params) : '');
            };
            $activityByDate = [];
            foreach ($pageActivity as $a) {
                $dayKey = date('Y-m-d', strtotime($a['at']) ?: time());
                $activityByDate[$dayKey][] = $a;
            }
        ?>
        <section class="panel case-hub-card case-activity-panel">
            <div class="case-activity-head">
                <h2><?= __e('cases.tab.activity') ?></h2>
                <form method="get" class="appt-list-toolbar" action="cases.php">
                    <input type="hidden" name="action" value="view">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="tab" value="activity">
                    <label class="appt-list-search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                        <input type="search" name="q" value="<?= e($activityQ) ?>" placeholder="<?= __e('common.search') ?>" autocomplete="off">
                    </label>
                    <label class="case-activity-filter">
                        <span class="sr-only"><?= __e('notifications.filter_status') ?></span>
                        <select id="caseActivityFilter" aria-label="<?= __e('notifications.filter_status') ?>">
                        <option value="all"><?= __e('notifications.filter.all') ?></option>
                        <option value="document"><?= __e('cases.tab.documents') ?></option>
                        <option value="invoice"><?= __e('cases.tab.invoices') ?></option>
                        <option value="quote"><?= __e('cases.tab.quotations') ?></option>
                        <option value="payment"><?= __e('cases.tab.receipts') ?></option>
                        <option value="note"><?= __e('cases.tab.notes') ?></option>
                        <option value="hearing"><?= __e('cases.tab.deadlines') ?></option>
                        <option value="case"><?= __e('common.case') ?></option>
                    </select>
                </label>
            </div>
            <div class="case-activity-scroll" id="caseActivityList">
                <?php if ($activityTotal > 0): ?>
                    <?php foreach ($activityByDate as $dayItems): ?>
                        <div class="case-activity-day" data-day>
                            <div class="case-activity-day-label"><?= e($formatActivityDay($dayItems[0]['at'])) ?></div>
                            <div class="case-activity-timeline">
                                <?php foreach ($dayItems as $a): ?>
                                    <div class="case-activity-item type-<?= e($a['type']) ?>" data-type="<?= e($a['type']) ?>">
                                        <div class="case-activity-icon" aria-hidden="true"><?= $activityIcons[$a['type']] ?? $activityIcons['case'] ?></div>
                                        <div class="case-activity-body">
                                            <strong><?= e($a['title']) ?></strong>
                                            <span class="case-activity-ref"><?= e($a['ref']) ?></span>
                                            <span class="case-activity-meta"><?= e($formatActivityWhen($a['at'])) ?><?= !empty($a['by']) ? ' · ' . e($a['by']) : '' ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state"><?= __e('cases.activity.empty') ?></div>
                <?php endif; ?>
            </div>
            <div class="case-activity-footer" id="caseActivityFooter">
                <?= e(__($activityTotal === 1 ? 'cases.pager.showing_one' : 'cases.pager.showing_many', ['from' => (int) $activityFrom, 'to' => (int) $activityTo, 'total' => (int) $activityTotal])) ?>
                <?php if ($activityPages > 1): ?>
                <nav class="case-list-pager" aria-label="<?= __e('cases.pagination.aria') ?>">
                    <?php if ($activityPage > 1): ?>
                    <a class="case-page-btn" href="<?= e($activityPageUrl($activityPage - 1)) ?>" aria-label="<?= __e('cases.pagination.prev') ?>">‹</a>
                    <?php else: ?>
                    <span class="case-page-btn is-disabled" aria-disabled="true">‹</span>
                    <?php endif; ?>
                    <?php for ($ap = 1; $ap <= $activityPages; $ap++): ?>
                    <a class="case-page-btn<?= $ap === $activityPage ? ' is-active' : '' ?>" href="<?= e($activityPageUrl($ap)) ?>"<?= $ap === $activityPage ? ' aria-current="page"' : '' ?>><?= $ap ?></a>
                    <?php endfor; ?>
                    <?php if ($activityPage < $activityPages): ?>
                    <a class="case-page-btn" href="<?= e($activityPageUrl($activityPage + 1)) ?>" aria-label="<?= __e('cases.pagination.next') ?>">›</a>
                    <?php else: ?>
                    <span class="case-page-btn is-disabled" aria-disabled="true">›</span>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            </div>
        </section>
        <script>
        (function () {
            var filter = document.getElementById('caseActivityFilter');
            var list = document.getElementById('caseActivityList');
            var footer = document.getElementById('caseActivityFooter');
            if (!filter || !list || !footer) return;
            var total = <?= (int) $activityTotal ?>;
            filter.addEventListener('change', function () {
                var value = filter.value;
                var visible = 0;
                list.querySelectorAll('.case-activity-item').forEach(function (item) {
                    var show = value === 'all' || item.getAttribute('data-type') === value;
                    item.hidden = !show;
                    if (show) visible += 1;
                });
                list.querySelectorAll('[data-day]').forEach(function (day) {
                    var any = day.querySelector('.case-activity-item:not([hidden])');
                    day.hidden = !any;
                });
                footer.textContent = visible > 0
                    ? ('Showing 1–' + visible + ' of ' + total + ' activities')
                    : ('Showing 0 of ' + total + ' activities');
            });
        })();
        </script>
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
