<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['client']);
$pdo = db();
ensure_invoice_bank_column($pdo);
$uid = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'record') {
    verify_csrf();
    $invoiceId = (int) post('invoice_id');
    $inv = $pdo->prepare('SELECT * FROM invoices WHERE id=? AND client_id=?');
    $inv->execute([$invoiceId, $uid]);
    $invoice = $inv->fetch();
    if (!$invoice) {
        flash('error', __('flash.invoice.not_found'));
        redirect('payments.php');
    }
    $amount = (float) post('amount');
    $receipt = generate_receipt_number($pdo);
    $pdo->prepare('INSERT INTO payments (invoice_id, client_id, amount, payment_method, reference_number, receipt_number, notes, paid_at, recorded_by) VALUES (?,?,?,?,?,?,?,NOW(),?)')
        ->execute([$invoiceId, $uid, $amount, post('payment_method'), post('reference_number'), $receipt, 'Client-recorded payment', $uid]);
    sync_invoice_payment_status($pdo, $invoiceId);
    $payCaseId = (int) ($invoice['case_id'] ?? 0);
    $caseLink = $payCaseId > 0
        ? '../admin/cases.php?action=view&id=' . $payCaseId . '&tab=receipts'
        : '../admin/cases.php';
    $paymentId = (int) $pdo->lastInsertId();
    create_notification($pdo, 1, 'notify.payment_recorded', notify_payload('notify.msg.payment_recorded', ['receipt' => $receipt, 'amount' => money($amount)]), 'payment', $caseLink, $uid);
    flash('success', __('flash.payment.recorded', ['receipt' => $receipt]));
    redirect('receipt.php?id=' . $paymentId);
}

$invoices = $pdo->prepare('SELECT i.*, IFNULL((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id=i.id),0) AS paid_amount FROM invoices i WHERE i.client_id=? ORDER BY i.created_at DESC');
$invoices->execute([$uid]);
$invoices = $invoices->fetchAll();
$payments = $pdo->prepare('SELECT * FROM payments WHERE client_id=? ORDER BY paid_at DESC');
$payments->execute([$uid]);
$payments = $payments->fetchAll();
$invPerPage = 4;
$paymentsPerPage = 10;
$totalInvoices = count($invoices);
$invPage = max(1, (int) get('inv_page', 1));
$invTotalPages = max(1, (int) ceil($totalInvoices / $invPerPage));
if ($invPage > $invTotalPages) {
    $invPage = $invTotalPages;
}
$invOffset = ($invPage - 1) * $invPerPage;
$pageInvoices = array_slice($invoices, $invOffset, $invPerPage);
$invShownFrom = $totalInvoices === 0 ? 0 : $invOffset + 1;
$invShownTo = min($invOffset + count($pageInvoices), $totalInvoices);
$totalPayments = count($payments);
$page = max(1, (int) get('page', 1));
$totalPages = max(1, (int) ceil($totalPayments / $paymentsPerPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $paymentsPerPage;
$pagePayments = array_slice($payments, $offset, $paymentsPerPage);
$shownFrom = $totalPayments === 0 ? 0 : $offset + 1;
$shownTo = min($offset + count($pagePayments), $totalPayments);
$invoicesPagerUrl = static function (int $targetInvPage) use ($page): string {
    $qs = ['inv_page' => max(1, $targetInvPage)];
    if ($page > 1) {
        $qs['page'] = $page;
    }
    return '?' . http_build_query($qs);
};
$paymentsPagerUrl = static function (int $targetPage) use ($invPage): string {
    $qs = ['page' => max(1, $targetPage)];
    if ($invPage > 1) {
        $qs['inv_page'] = $invPage;
    }
    return '?' . http_build_query($qs);
};
$outstanding = 0;
foreach ($invoices as $i) {
    if (in_array($i['status'], ['sent', 'partial', 'overdue', 'draft'], true)) {
        $outstanding += max(0, (float)$i['total'] - (float)$i['paid_amount']);
    }
}

$invoiceBanks = [];
foreach ($invoices as $i) {
    $invoiceBanks[(int) $i['id']] = get_bank_account($pdo, isset($i['bank_account_id']) ? (int) $i['bank_account_id'] : null);
}

$pageTitle = __('page.payments');
$pageSubtitle = __('ai.subtitle.client');
$portal = 'client';
$activeNav = 'payments';
require __DIR__ . '/../includes/header.php';
?>
<div class="stat-card"><div class="stat-label"><?= __e('payments.outstanding_balance') ?></div><div class="stat-value"><?= e(money($outstanding)) ?></div></div>
<div class="grid grid-2">
    <div class="panel case-list-panel">
        <div class="case-list-head">
            <div class="case-list-title">
                <h2><?= __e('payments.my_invoices') ?></h2>
            </div>
        </div>
        <div class="table-wrap case-table-wrap">
            <table class="case-table">
                <thead><tr><th><?= __e('finance.invoice_number') ?></th><th><?= __e('common.total') ?></th><th><?= __e('finance.paid') ?></th><th><?= __e('common.status') ?></th></tr></thead>
                <tbody>
                <?php foreach ($pageInvoices as $i): ?>
                    <tr>
                        <td><strong><?= e($i['invoice_number']) ?></strong><div class="muted"><?= e(t_content($i['title'])) ?></div></td>
                        <td><?= e(money($i['total'])) ?></td>
                        <td><?= e(money($i['paid_amount'])) ?></td>
                        <td><?= status_badge($i['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$pageInvoices): ?>
                    <tr><td colspan="4" class="case-empty muted"><?= __e('finance.no_invoices') ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="case-list-foot payments-invoices-foot">
            <?php if ($invTotalPages > 1): ?>
            <nav class="case-list-pager" aria-label="<?= __e('payments.invoices.pagination.aria') ?>">
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
            <p class="case-list-footer muted"><?= e(__($totalInvoices === 1 ? 'payments.invoices.pager.showing_one' : 'payments.invoices.pager.showing_many', ['from' => (int) $invShownFrom, 'to' => (int) $invShownTo, 'total' => (int) $totalInvoices])) ?></p>
        </div>
    </div>
    <div class="panel">
        <h2><?= __e('payments.record') ?></h2>
        <form method="post" class="form-grid entity-inline-form" id="clientPayForm">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="record">
            <div class="form-group full"><label><?= __e('finance.invoices') ?></label>
                <select name="invoice_id" id="clientPayInvoice" required>
                    <?php foreach ($invoices as $i): if (in_array($i['status'], ['paid','cancelled'], true)) continue; ?>
                        <option value="<?= (int)$i['id'] ?>" data-bank="<?= e(json_encode($invoiceBanks[(int) $i['id']] ?? null, JSON_UNESCAPED_UNICODE)) ?>">
                            <?= e($i['invoice_number'].' · '.__('common.due').' '.money(max(0,$i['total']-$i['paid_amount']))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="entity-field-row entity-field-row--2">
                <div class="form-group"><label><?= __e('common.amount') ?></label><input type="number" step="0.01" name="amount" required></div>
                <div class="form-group"><label><?= __e('finance.method') ?></label>
                    <select name="payment_method" id="clientPayMethod">
                        <?php foreach (['bank_transfer','card','online','cash'] as $m): ?>
                            <option value="<?= $m ?>"><?= e(__('payment.method.' . $m)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group full"><label><?= __e('common.reference') ?></label><input name="reference_number"></div>
            <div class="form-group full" id="clientBankBox" hidden>
                <div class="pay-bank-card">
                    <h3><?= __e('finance.pay_to_bank') ?></h3>
                    <div id="clientBankDetails" class="pay-bank-details"></div>
                </div>
            </div>
            <div class="form-actions full"><button class="btn btn-primary" type="submit"><?= __e('payments.submit') ?></button></div>
        </form>
    </div>
</div>
<div class="panel case-list-panel">
    <div class="case-list-head">
        <div class="case-list-title">
            <h2><?= __e('payments.history') ?></h2>
        </div>
    </div>
    <div class="table-wrap case-table-wrap">
        <table class="case-table">
            <thead><tr><th><?= __e('finance.receipt') ?></th><th><?= __e('common.amount') ?></th><th><?= __e('finance.method') ?></th><th><?= __e('common.date') ?></th><th class="is-right"><?= __e('common.actions') ?></th></tr></thead>
            <tbody>
            <?php foreach ($pagePayments as $p): ?>
                <tr>
                    <td>
                        <a class="inv-list-number" href="receipt.php?id=<?= (int) $p['id'] ?>"><strong><?= e($p['receipt_number']) ?></strong></a>
                        <div class="muted"><?= e($p['reference_number'] ?: '') ?></div>
                    </td>
                    <td><?= e(money($p['amount'])) ?></td>
                    <td><?= e(__('payment.method.' . $p['payment_method'])) ?></td>
                    <td><?= e(format_datetime($p['paid_at'])) ?></td>
                    <td class="is-right">
                        <a class="btn btn-row-open btn-sm" href="receipt.php?id=<?= (int) $p['id'] ?>"><?= __e('common.view') ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pagePayments): ?>
                <tr><td colspan="5" class="muted"><?= __e('finance.no_receipts') ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="case-list-foot">
        <p class="case-list-footer muted"><?= e(__($totalPayments === 1 ? 'payments.pager.showing_one' : 'payments.pager.showing_many', ['from' => (int) $shownFrom, 'to' => (int) $shownTo, 'total' => (int) $totalPayments])) ?></p>
        <?php if ($totalPages > 1): ?>
        <nav class="case-list-pager" aria-label="<?= __e('payments.pagination.aria') ?>">
            <?php if ($page > 1): ?>
            <a class="case-page-btn" href="<?= e($paymentsPagerUrl($page - 1)) ?>" aria-label="<?= __e('cases.pagination.prev') ?>">‹</a>
            <?php else: ?>
            <span class="case-page-btn is-disabled" aria-disabled="true">‹</span>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a class="case-page-btn<?= $p === $page ? ' is-active' : '' ?>" href="<?= e($paymentsPagerUrl($p)) ?>"<?= $p === $page ? ' aria-current="page"' : '' ?>><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a class="case-page-btn" href="<?= e($paymentsPagerUrl($page + 1)) ?>" aria-label="<?= __e('cases.pagination.next') ?>">›</a>
            <?php else: ?>
            <span class="case-page-btn is-disabled" aria-disabled="true">›</span>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
  var inv = document.getElementById('clientPayInvoice');
  var method = document.getElementById('clientPayMethod');
  var box = document.getElementById('clientBankBox');
  var details = document.getElementById('clientBankDetails');
  if (!inv || !method || !box || !details) return;
  function render() {
    var opt = inv.options[inv.selectedIndex];
    var bank = null;
    try { bank = opt && opt.getAttribute('data-bank') ? JSON.parse(opt.getAttribute('data-bank')) : null; } catch (e) { bank = null; }
    var show = method.value === 'bank_transfer' && bank;
    box.hidden = !show;
    if (!show) { details.innerHTML = ''; return; }
    var rows = [];
    if (bank.label) rows.push('<strong>' + bank.label + '</strong>');
    if (bank.bank) rows.push('<p><span><?= e(__('settings.payments.bank_name')) ?></span> ' + bank.bank + '</p>');
    if (bank.account_name) rows.push('<p><span><?= e(__('settings.payments.account_name')) ?></span> ' + bank.account_name + '</p>');
    if (bank.account_number) rows.push('<p><span><?= e(__('settings.payments.account_number')) ?></span> ' + bank.account_number + '</p>');
    if (bank.sort_code) rows.push('<p><span><?= e(__('settings.payments.sort_code')) ?></span> ' + bank.sort_code + '</p>');
    if (bank.iban) rows.push('<p><span><?= e(__('settings.payments.iban')) ?></span> ' + bank.iban + '</p>');
    if (bank.swift) rows.push('<p><span><?= e(__('settings.payments.swift')) ?></span> ' + bank.swift + '</p>');
    if (bank.reference) rows.push('<p><span><?= e(__('settings.payments.reference')) ?></span> ' + bank.reference + '</p>');
    details.innerHTML = rows.join('');
  }
  inv.addEventListener('change', render);
  method.addEventListener('change', render);
  render();
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
