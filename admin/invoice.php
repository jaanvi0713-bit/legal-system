<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'staff']);
$pdo = db();
ensure_invoice_items_table($pdo);
ensure_invoice_bank_column($pdo);

$action = get('action', 'view');
$id = (int) get('id', 0);

$safe_return = static function (string $url, string $fallback = 'cases.php'): string {
    $url = trim($url);
    if ($url === '' || str_contains($url, '://') || str_starts_with($url, '//')) {
        return $fallback;
    }
    return $url;
};
$case_billing_url = static function (?int $caseId, string $tab = 'invoices'): string {
    if ($caseId && $caseId > 0) {
        return 'cases.php?action=view&id=' . $caseId . '&tab=' . $tab;
    }
    return 'cases.php';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');

    if ($fa === 'generate') {
        $clientId = (int) post('client_id');
        $caseId = post('case_id') !== '' ? (int) post('case_id') : null;
        $returnAfter = $safe_return((string) post('return_to', ''), $case_billing_url($caseId));
        $status = post('status') ?: 'sent';
        $issuedAt = post('issued_at') ?: date('Y-m-d');
        $dueDate = post('due_date') ?: null;
        $title = trim((string) post('title')) ?: 'Invoice';
        $notes = trim((string) post('description'));
        $bankAccountId = (int) post('bank_account_id');
        if ($bankAccountId < 1 || $bankAccountId > 3 || !get_bank_account($pdo, $bankAccountId)) {
            $configured = get_configured_bank_accounts($pdo);
            $bankAccountId = $configured ? (int) array_key_first($configured) : null;
        }

        $descriptions = $_POST['item_description'] ?? [];
        $quantities = $_POST['item_qty'] ?? [];
        $prices = $_POST['item_price'] ?? [];
        $vats = $_POST['item_vat'] ?? [];

        $lines = [];
        $subtotal = 0.0;
        $vatTotal = 0.0;
        foreach ($descriptions as $i => $desc) {
            $desc = trim((string) $desc);
            if ($desc === '') {
                continue;
            }
            $qty = max(0, (float) ($quantities[$i] ?? 1));
            $price = max(0, (float) ($prices[$i] ?? 0));
            $vat = max(0, (float) ($vats[$i] ?? 0));
            $lineSub = round($qty * $price, 2);
            $lineTotal = round($lineSub + $vat, 2);
            $lines[] = [
                'description' => $desc,
                'quantity' => $qty,
                'unit_price' => $price,
                'vat_amount' => $vat,
                'line_total' => $lineTotal,
            ];
            $subtotal += $lineSub;
            $vatTotal += $vat;
        }

        if (!$clientId || !$lines) {
            flash('error', __('flash.invoice.need_lines'));
            $retry = 'invoice.php?action=generate';
            if ($caseId) {
                $retry .= '&case_id=' . $caseId . '&client_id=' . $clientId;
            }
            redirect($retry);
        }

        $grand = round($subtotal + $vatTotal, 2);
        $number = generate_invoice_number($pdo);

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO invoices (invoice_number, case_id, client_id, title, description, amount, tax, total, status, due_date, issued_at, created_by, bank_account_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $number, $caseId, $clientId, $title, $notes ?: null,
                round($subtotal, 2), round($vatTotal, 2), $grand, $status,
                $dueDate, $issuedAt, current_user()['id'], $bankAccountId,
            ]);
            $invoiceId = (int) $pdo->lastInsertId();
            $ins = $pdo->prepare(
                'INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, vat_amount, line_total, sort_order)
                 VALUES (?,?,?,?,?,?,?)'
            );
            foreach ($lines as $ord => $line) {
                $ins->execute([
                    $invoiceId,
                    $line['description'],
                    $line['quantity'],
                    $line['unit_price'],
                    $line['vat_amount'],
                    $line['line_total'],
                    $ord,
                ]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', __('flash.invoice.create_failed'));
            redirect('invoice.php?action=generate');
        }

        if ($status !== 'draft') {
            create_notification(
                $pdo,
                $clientId,
                'notify.invoice_new',
                notify_payload('notify.msg.invoice_new', ['number' => $number, 'amount' => money($grand)]),
                'payment',
                '../client/payments.php',
                current_user()['id']
            );
        }

        flash('success', __('flash.invoice.created', ['number' => $number]));
        redirect('invoice.php?id=' . $invoiceId . '&from=' . rawurlencode($returnAfter));
    }

    if ($fa === 'email_client') {
        $invoiceId = (int) post('invoice_id');
        $stmt = $pdo->prepare(
            "SELECT i.*, u.email, u.first_name, u.last_name
             FROM invoices i JOIN users u ON u.id = i.client_id WHERE i.id = ?"
        );
        $stmt->execute([$invoiceId]);
        $inv = $stmt->fetch();
        if (!$inv) {
            flash('error', __('flash.invoice.not_found'));
            redirect('cases.php');
        }
        create_notification(
            $pdo,
            (int) $inv['client_id'],
            'notify.invoice_new',
            notify_payload('notify.msg.invoice_new', ['number' => $inv['invoice_number'], 'amount' => money($inv['total'])]),
            'payment',
            '../client/payments.php',
            current_user()['id']
        );
        $from = $safe_return((string) post('return_to', ''), $case_billing_url((int) ($inv['case_id'] ?? 0)));
        flash('success', __('flash.invoice.emailed', ['email' => $inv['email']]));
        redirect('invoice.php?id=' . $invoiceId . '&mailto=1&from=' . rawurlencode($from));
    }

    if ($fa === 'delete_invoice') {
        $invoiceId = (int) post('invoice_id');
        $back = $safe_return((string) post('return_to', 'cases.php'), 'cases.php');
        $stmt = $pdo->prepare('SELECT id, invoice_number FROM invoices WHERE id = ?');
        $stmt->execute([$invoiceId]);
        $inv = $stmt->fetch();
        if (!$inv) {
            flash('error', __('flash.invoice.not_found'));
            redirect($back);
        }
        $pdo->prepare('DELETE FROM payments WHERE invoice_id = ?')->execute([$invoiceId]);
        $pdo->prepare('DELETE FROM invoices WHERE id = ?')->execute([$invoiceId]);
        flash('success', __('flash.invoice.deleted', ['number' => $inv['invoice_number']]));
        redirect($back);
    }

    if ($fa === 'set_bank_account') {
        $invoiceId = (int) post('invoice_id');
        $bankAccountId = (int) post('bank_account_id');
        if ($bankAccountId < 1 || $bankAccountId > 3 || !get_bank_account($pdo, $bankAccountId)) {
            flash('error', __('flash.invoice.bank_required'));
            redirect('invoice.php?id=' . $invoiceId);
        }
        $pdo->prepare('UPDATE invoices SET bank_account_id = ? WHERE id = ?')->execute([$bankAccountId, $invoiceId]);
        flash('success', __('flash.invoice.bank_updated'));
        redirect('invoice.php?id=' . $invoiceId);
    }
}

$clients = $pdo->query("SELECT id, first_name, last_name, email, address FROM users WHERE role='client' ORDER BY first_name")->fetchAll();
$cases = $pdo->query('SELECT id, case_number, title, client_id FROM cases ORDER BY created_at DESC')->fetchAll();

if ($action === 'generate') {
    $bankOptions = get_configured_bank_accounts($pdo);
    $preCaseId = (int) get('case_id', 0);
    $preClientId = (int) get('client_id', 0);
    if ($preCaseId > 0 && $preClientId < 1) {
        foreach ($cases as $c) {
            if ((int) $c['id'] === $preCaseId) {
                $preClientId = (int) $c['client_id'];
                break;
            }
        }
    }
    $genReturn = $safe_return((string) get('from', ''), $case_billing_url($preCaseId > 0 ? $preCaseId : null));
    $pageTitle = __('finance.generate_invoice');
    $pageSubtitle = __('finance.generate_invoice_help');
    $portal = 'admin';
    $activeNav = 'cases';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="panel inv-gen-panel">
        <div class="panel-header">
            <h2><?= __e('finance.generate_invoice') ?></h2>
            <a class="btn btn-secondary btn-sm" href="<?= e($genReturn) ?>"><?= __e('common.back') ?></a>
        </div>
        <form method="post" class="form-grid" id="invoiceGenerateForm">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="generate">
            <input type="hidden" name="return_to" value="<?= e($genReturn) ?>">
            <div class="form-group">
                <label><?= __e('finance.client') ?></label>
                <select name="client_id" id="invClient" required>
                    <option value=""><?= __e('finance.select_client') ?></option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $preClientId === (int) $c['id'] ? 'selected' : '' ?>><?= e(full_name($c)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><?= __e('nav.cases') ?></label>
                <select name="case_id" id="invCase">
                    <option value=""><?= __e('common.em_dash') ?></option>
                    <?php foreach ($cases as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" data-client="<?= (int) $c['client_id'] ?>" <?= $preCaseId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['case_number'] . ' · ' . $c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group full">
                <label><?= __e('finance.invoice_title') ?></label>
                <input name="title" value="Professional services" required>
            </div>
            <div class="form-group">
                <label><?= __e('form.issued') ?></label>
                <input type="date" name="issued_at" value="<?= e(date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label><?= __e('finance.due_date') ?></label>
                <input type="date" name="due_date" value="<?= e(date('Y-m-d', strtotime('+14 days'))) ?>">
            </div>
            <div class="form-group">
                <label><?= __e('common.status') ?></label>
                <select name="status">
                    <?php foreach (['sent', 'draft', 'partial', 'paid', 'overdue'] as $s): ?>
                        <option value="<?= e($s) ?>"><?= e(translate_status($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><?= __e('finance.pay_to_bank') ?></label>
                <?php if ($bankOptions): ?>
                    <select name="bank_account_id" required>
                        <?php foreach ($bankOptions as $ba): ?>
                            <option value="<?= (int) $ba['id'] ?>"><?= e($ba['label'] . ($ba['bank'] ? ' · ' . $ba['bank'] : '') . ($ba['account_number'] ? ' · ' . $ba['account_number'] : '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <p class="muted" style="margin:0.35rem 0 0;"><?= __e('finance.no_bank_accounts') ?> <a href="settings.php?tab=payments"><?= __e('settings.payments.title') ?></a></p>
                    <input type="hidden" name="bank_account_id" value="">
                <?php endif; ?>
            </div>
            <div class="form-group full">
                <label><?= __e('common.notes') ?></label>
                <textarea name="description" rows="2" placeholder="<?= __e('finance.invoice_notes_ph') ?>"></textarea>
            </div>

            <div class="form-group full">
                <div class="inv-lines-head">
                    <h3><?= __e('finance.line_items') ?></h3>
                    <button type="button" class="btn btn-secondary btn-sm" id="invAddLine">+ <?= __e('finance.add_line') ?></button>
                </div>
                <div class="table-wrap">
                    <table class="inv-lines-table" id="invLinesTable">
                        <thead>
                            <tr>
                                <th><?= __e('common.description') ?></th>
                                <th><?= __e('finance.qty') ?></th>
                                <th><?= __e('finance.unit_price') ?></th>
                                <th><?= __e('finance.vat') ?></th>
                                <th><?= __e('common.total') ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="inv-line">
                                <td><input name="item_description[]" required placeholder="<?= __e('finance.line_desc_ph') ?>"></td>
                                <td><input type="number" step="0.01" min="0" name="item_qty[]" value="1" class="inv-qty"></td>
                                <td><input type="number" step="0.01" min="0" name="item_price[]" value="0" class="inv-price"></td>
                                <td><input type="number" step="0.01" min="0" name="item_vat[]" value="0" class="inv-vat"></td>
                                <td class="inv-line-total muted">0.00</td>
                                <td><button type="button" class="chip inv-remove-line" aria-label="<?= __e('common.delete') ?>">×</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="inv-gen-totals">
                    <div><span><?= __e('finance.subtotal') ?></span><strong id="invSubtotal">0.00</strong></div>
                    <div><span><?= __e('finance.vat_amount') ?></span><strong id="invVatTotal">0.00</strong></div>
                    <div class="is-grand"><span><?= __e('finance.grand_total') ?></span><strong id="invGrand">0.00</strong></div>
                </div>
            </div>

            <div class="form-actions full">
                <button class="btn btn-primary" type="submit"><?= __e('finance.generate_invoice') ?></button>
                <a class="btn btn-secondary" href="<?= e($genReturn) ?>"><?= __e('common.cancel') ?></a>
            </div>
        </form>
    </div>
    <script>
    (function () {
      var tbody = document.querySelector('#invLinesTable tbody');
      var addBtn = document.getElementById('invAddLine');
      function rowHtml() {
        return '<tr class="inv-line">' +
          '<td><input name="item_description[]" required placeholder="<?= e(__('finance.line_desc_ph')) ?>"></td>' +
          '<td><input type="number" step="0.01" min="0" name="item_qty[]" value="1" class="inv-qty"></td>' +
          '<td><input type="number" step="0.01" min="0" name="item_price[]" value="0" class="inv-price"></td>' +
          '<td><input type="number" step="0.01" min="0" name="item_vat[]" value="0" class="inv-vat"></td>' +
          '<td class="inv-line-total muted">0.00</td>' +
          '<td><button type="button" class="chip inv-remove-line">×</button></td></tr>';
      }
      function recalc() {
        var sub = 0, vat = 0;
        tbody.querySelectorAll('.inv-line').forEach(function (row) {
          var q = parseFloat(row.querySelector('.inv-qty').value) || 0;
          var p = parseFloat(row.querySelector('.inv-price').value) || 0;
          var v = parseFloat(row.querySelector('.inv-vat').value) || 0;
          var lineSub = q * p;
          sub += lineSub; vat += v;
          row.querySelector('.inv-line-total').textContent = (lineSub + v).toFixed(2);
        });
        document.getElementById('invSubtotal').textContent = sub.toFixed(2);
        document.getElementById('invVatTotal').textContent = vat.toFixed(2);
        document.getElementById('invGrand').textContent = (sub + vat).toFixed(2);
      }
      addBtn.addEventListener('click', function () {
        tbody.insertAdjacentHTML('beforeend', rowHtml());
        recalc();
      });
      tbody.addEventListener('input', function (e) {
        if (e.target.matches('.inv-qty, .inv-price, .inv-vat')) recalc();
      });
      tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.inv-remove-line');
        if (!btn) return;
        var rows = tbody.querySelectorAll('.inv-line');
        if (rows.length <= 1) return;
        btn.closest('tr').remove();
        recalc();
      });
      recalc();
    })();
    </script>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// View / print document
if ($id <= 0) {
    flash('error', __('flash.invoice.not_found'));
    redirect('cases.php');
}

$stmt = $pdo->prepare(
    "SELECT i.*, u.first_name, u.last_name, u.email, u.phone, u.address, u.company_name AS client_company
     FROM invoices i
     JOIN users u ON u.id = i.client_id
     WHERE i.id = ?"
);
$stmt->execute([$id]);
$invoice = $stmt->fetch();
if (!$invoice) {
    flash('error', __('flash.invoice.not_found'));
    redirect('cases.php');
}

$invoiceLines = invoice_line_items($pdo, $id);
$invoiceLines = array_values(array_filter($invoiceLines, static function ($row) {
    return is_array($row) && trim((string) ($row['description'] ?? '')) !== '';
}));
if (!$invoiceLines) {
    $desc = trim((string) ($invoice['title'] ?: ''));
    if ($invoice['description']) {
        $desc = $desc !== '' ? ($desc . ' — ' . trim((string) $invoice['description'])) : trim((string) $invoice['description']);
    }
    if ($desc === '') {
        $desc = 'Professional services';
    }
    $invoiceLines = [[
        'description' => $desc,
        'quantity' => 1,
        'unit_price' => (float) $invoice['amount'],
        'vat_amount' => (float) $invoice['tax'],
        'line_total' => (float) $invoice['total'],
    ]];
}

$paid = invoice_paid_total($pdo, $id);
$subtotal = (float) $invoice['amount'];
$vatAmount = (float) $invoice['tax'];
$grand = (float) $invoice['total'];
$amountDue = max(0, round($grand - $paid, 2));
$bankOptions = get_configured_bank_accounts($pdo);
$selectedBank = get_bank_account($pdo, isset($invoice['bank_account_id']) ? (int) $invoice['bank_account_id'] : null);

$firmName = get_setting($pdo, 'company_name', app_config('name', 'LEGAL PRO'));
$firmEmail = get_setting($pdo, 'company_email', '');
$firmPhone = get_setting($pdo, 'company_phone', '');
$firmAddress = get_setting($pdo, 'company_address', '');
$firmVat = get_setting($pdo, 'company_vat', get_setting($pdo, 'company_registration', ''));
$payInstructions = get_setting($pdo, 'payment_instructions', '');

$pageTitle = $invoice['invoice_number'];
$pageSubtitle = __('finance.invoice_document');
$portal = 'admin';
$activeNav = 'cases';
$bodyClass = 'page-invoice-doc';
$mailtoOnLoad = get('mailto') === '1';
$invoiceCaseId = (int) ($invoice['case_id'] ?? 0);
$returnTo = $safe_return((string) get('from', ''), $case_billing_url($invoiceCaseId > 0 ? $invoiceCaseId : null));
$paymentUrl = $invoiceCaseId > 0
    ? 'cases.php?action=view&id=' . $invoiceCaseId . '&tab=receipts&compose=payment&invoice_id=' . (int) $invoice['id']
    : 'cases.php';
require __DIR__ . '/../includes/header.php';
$mailtoHref = 'mailto:' . rawurlencode((string) $invoice['email'])
    . '?subject=' . rawurlencode('Invoice ' . $invoice['invoice_number'])
    . '&body=' . rawurlencode('Please find your invoice ' . $invoice['invoice_number'] . ' for ' . money($invoice['total']) . '. View it in your client portal.');
?>
<div class="inv-doc-toolbar no-print">
    <div>
        <a class="btn btn-secondary btn-sm" href="<?= e($returnTo) ?>"><?= __e('common.back') ?></a>
    </div>
    <div class="inv-doc-actions">
        <?php if ($bankOptions): ?>
        <form method="post" class="inv-bank-switch inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="set_bank_account">
            <input type="hidden" name="invoice_id" value="<?= (int) $invoice['id'] ?>">
            <label class="sr-only" for="invBankSelect"><?= __e('finance.pay_to_bank') ?></label>
            <select name="bank_account_id" id="invBankSelect" onchange="this.form.submit()">
                <?php foreach ($bankOptions as $ba): ?>
                    <option value="<?= (int) $ba['id'] ?>" <?= (int) ($invoice['bank_account_id'] ?? 0) === (int) $ba['id'] ? 'selected' : '' ?>><?= e($ba['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
        <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()"><?= __e('finance.print') ?></button>
        <form method="post" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="email_client">
            <input type="hidden" name="invoice_id" value="<?= (int) $invoice['id'] ?>">
            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
            <button class="btn btn-primary btn-sm" type="submit"><?= __e('finance.email_client') ?></button>
        </form>
        <?php if ($invoiceCaseId > 0): ?>
        <a class="btn btn-accent btn-sm" href="<?= e($paymentUrl) ?>"><?= __e('finance.record_payment') ?></a>
        <?php endif; ?>
    </div>
</div>

<article class="inv-doc" id="invoiceDocument">
    <header class="inv-doc-top">
        <div class="inv-doc-brand">
            <div class="inv-doc-logo brand-mark" aria-hidden="true"><?= nav_icon('logo') ?></div>
            <div class="inv-doc-brand-text">
                <div class="inv-doc-firm-name"><?= e(strtoupper($firmName)) ?></div>
            </div>
        </div>
        <div class="inv-doc-title-block">
            <h1><?= __e('finance.invoice_word') ?></h1>
            <div class="inv-doc-number">#<?= e($invoice['invoice_number']) ?></div>
        </div>
    </header>

    <section class="inv-doc-parties">
        <div class="inv-doc-from">
            <strong><?= e(strtoupper($firmName)) ?></strong>
            <?php if ($firmAddress): ?><p><?= nl2br(e($firmAddress)) ?></p><?php endif; ?>
            <?php if ($firmEmail): ?><p><?= e($firmEmail) ?></p><?php endif; ?>
            <?php if ($firmPhone): ?><p><?= e($firmPhone) ?></p><?php endif; ?>
            <div class="inv-doc-dates">
                <div><span><?= __e('finance.issue_date') ?></span> <strong><?= e($invoice['issued_at'] ? date('d/m/Y', strtotime($invoice['issued_at'])) : '—') ?></strong></div>
                <div><span><?= __e('finance.due_date') ?></span> <strong><?= e($invoice['due_date'] ? date('d/m/Y', strtotime($invoice['due_date'])) : '—') ?></strong></div>
            </div>
        </div>
        <div class="inv-doc-billto">
            <strong><?= __e('finance.bill_to') ?></strong>
            <p class="inv-doc-client"><?= e(full_name($invoice)) ?></p>
            <?php if (!empty($invoice['client_company'])): ?><p><?= e($invoice['client_company']) ?></p><?php endif; ?>
            <?php if (!empty($invoice['address'])): ?><p><?= nl2br(e($invoice['address'])) ?></p><?php endif; ?>
            <?php if (!empty($invoice['email'])): ?><p><?= e($invoice['email']) ?></p><?php endif; ?>
        </div>
    </section>

    <div class="inv-doc-table-wrap">
        <table class="inv-doc-table">
            <thead>
                <tr>
                    <th><?= __e('common.description') ?></th>
                    <th class="is-center"><?= __e('finance.qty') ?></th>
                    <th class="is-right"><?= __e('finance.unit_price') ?></th>
                    <th class="is-right"><?= __e('finance.vat') ?></th>
                    <th class="is-right"><?= __e('common.total') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($invoiceLines as $line): ?>
                <tr>
                    <td><?= e((string) ($line['description'] ?? '')) ?></td>
                    <td class="is-center"><?= e(rtrim(rtrim(number_format((float) ($line['quantity'] ?? 0), 2, '.', ''), '0'), '.') ?: '0') ?></td>
                    <td class="is-right"><?= e(money($line['unit_price'] ?? 0)) ?></td>
                    <td class="is-right"><?= e(money($line['vat_amount'] ?? 0)) ?></td>
                    <td class="is-right"><?= e(money($line['line_total'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <section class="inv-doc-summary">
        <div class="inv-doc-summary-row"><span><?= __e('finance.subtotal') ?></span><strong><?= e(money($subtotal)) ?></strong></div>
        <div class="inv-doc-summary-row"><span><?= __e('finance.vat_amount') ?></span><strong><?= e(money($vatAmount)) ?></strong></div>
        <div class="inv-doc-summary-row"><span><?= __e('finance.net_ex_vat') ?></span><strong><?= e(money($subtotal)) ?></strong></div>
        <div class="inv-doc-summary-row"><span><?= __e('finance.net_inc_vat') ?></span><strong><?= e(money($grand)) ?></strong></div>
        <div class="inv-doc-summary-row"><span><?= __e('finance.amount_paid') ?></span><strong><?= e(money($paid)) ?></strong></div>
        <div class="inv-doc-summary-divider"></div>
        <div class="inv-doc-summary-row is-emphasis"><span><?= __e('finance.amount_due') ?></span><strong><?= e(money($amountDue)) ?></strong></div>
        <div class="inv-doc-summary-divider"></div>
        <div class="inv-doc-summary-row is-grand"><span><?= __e('finance.grand_total') ?></span><strong><?= e(money($grand)) ?></strong></div>
    </section>

    <footer class="inv-doc-footer">
        <div class="inv-doc-payable">
            <strong><?= __e('finance.payable_to') ?></strong>
            <div class="inv-doc-payable-name"><?= e(strtoupper(($selectedBank['account_name'] ?? '') ?: $firmName)) ?></div>
            <?php if ($firmVat): ?>
                <p><?= __e('finance.vat_number') ?>: <?= e($firmVat) ?></p>
            <?php endif; ?>
            <?php if ($selectedBank): ?>
                <div class="inv-doc-bank">
                    <div class="inv-doc-bank-label"><?= e($selectedBank['label']) ?></div>
                    <?php if ($selectedBank['bank']): ?><p><span><?= __e('settings.payments.bank_name') ?></span> <?= e($selectedBank['bank']) ?></p><?php endif; ?>
                    <?php if ($selectedBank['account_name']): ?><p><span><?= __e('settings.payments.account_name') ?></span> <?= e($selectedBank['account_name']) ?></p><?php endif; ?>
                    <?php if ($selectedBank['account_number']): ?><p><span><?= __e('settings.payments.account_number') ?></span> <?= e($selectedBank['account_number']) ?></p><?php endif; ?>
                    <?php if ($selectedBank['iban']): ?><p><span><?= __e('settings.payments.iban') ?></span> <?= e($selectedBank['iban']) ?></p><?php endif; ?>
                    <?php if ($selectedBank['swift']): ?><p><span><?= __e('settings.payments.swift') ?></span> <?= e($selectedBank['swift']) ?></p><?php endif; ?>
                </div>
            <?php else: ?>
                <p class="muted no-print"><?= __e('finance.no_bank_on_invoice') ?></p>
            <?php endif; ?>
            <?php if ($payInstructions): ?>
                <p class="inv-doc-pay-note"><?= e($payInstructions) ?></p>
            <?php endif; ?>
        </div>
        <p class="inv-doc-thanks"><?= __e('finance.thank_you') ?></p>
    </footer>
</article>
<?php if ($mailtoOnLoad && !empty($invoice['email'])): ?>
<script>window.location.href = <?= json_encode($mailtoHref) ?>;</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
