<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['client']);
$pdo = db();
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
    $sumStmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=?');
    $sumStmt->execute([$invoiceId]);
    $paid = (float) $sumStmt->fetchColumn();
    $status = $paid >= (float)$invoice['total'] ? 'paid' : 'partial';
    $pdo->prepare('UPDATE invoices SET status=? WHERE id=?')->execute([$status, $invoiceId]);
    create_notification($pdo, 1, 'notify.payment_recorded', notify_payload('notify.msg.payment_recorded', ['receipt' => $receipt, 'amount' => money($amount)]), 'payment', '../admin/finance.php', $uid);
    flash('success', __('flash.payment.recorded', ['receipt' => $receipt]));
    redirect('payments.php');
}

$invoices = $pdo->prepare('SELECT i.*, IFNULL((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id=i.id),0) AS paid_amount FROM invoices i WHERE i.client_id=? ORDER BY i.created_at DESC');
$invoices->execute([$uid]);
$invoices = $invoices->fetchAll();
$payments = $pdo->prepare('SELECT * FROM payments WHERE client_id=? ORDER BY paid_at DESC');
$payments->execute([$uid]);
$payments = $payments->fetchAll();
$outstanding = 0;
foreach ($invoices as $i) {
    if (in_array($i['status'], ['sent', 'partial', 'overdue', 'draft'], true)) {
        $outstanding += max(0, (float)$i['total'] - (float)$i['paid_amount']);
    }
}

$pageTitle = __('page.payments');
$pageSubtitle = __('ai.subtitle.client');
$portal = 'client';
$activeNav = 'payments';
require __DIR__ . '/../includes/header.php';
?>
<div class="stat-card"><div class="stat-label"><?= __e('payments.outstanding_balance') ?></div><div class="stat-value"><?= e(money($outstanding)) ?></div></div>
<div class="grid grid-2">
    <div class="panel">
        <h2><?= __e('payments.my_invoices') ?></h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th><?= __e('finance.invoice_number') ?></th><th><?= __e('common.total') ?></th><th><?= __e('finance.paid') ?></th><th><?= __e('common.status') ?></th></tr></thead>
                <tbody>
                <?php foreach ($invoices as $i): ?>
                    <tr>
                        <td><strong><?= e($i['invoice_number']) ?></strong><div class="muted"><?= e(t_content($i['title'])) ?></div></td>
                        <td><?= e(money($i['total'])) ?></td>
                        <td><?= e(money($i['paid_amount'])) ?></td>
                        <td><?= status_badge($i['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="panel">
        <h2><?= __e('payments.record') ?></h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="record">
            <div class="form-group full"><label><?= __e('finance.invoices') ?></label>
                <select name="invoice_id" required>
                    <?php foreach ($invoices as $i): if (in_array($i['status'], ['paid','cancelled'], true)) continue; ?>
                        <option value="<?= (int)$i['id'] ?>"><?= e($i['invoice_number'].' · '.__('common.due').' '.money(max(0,$i['total']-$i['paid_amount']))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label><?= __e('common.amount') ?></label><input type="number" step="0.01" name="amount" required></div>
            <div class="form-group"><label><?= __e('finance.method') ?></label><select name="payment_method"><?php foreach (['bank_transfer','card','online','cash'] as $m): ?><option value="<?= $m ?>"><?= e(__('payment.method.' . $m)) ?></option><?php endforeach; ?></select></div>
            <div class="form-group full"><label><?= __e('common.reference') ?></label><input name="reference_number"></div>
            <div class="form-actions full"><button class="btn btn-accent" type="submit"><?= __e('payments.submit') ?></button></div>
        </form>
    </div>
</div>
<div class="panel">
    <h2><?= __e('payments.history') ?></h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th><?= __e('finance.receipt') ?></th><th><?= __e('common.amount') ?></th><th><?= __e('finance.method') ?></th><th><?= __e('common.date') ?></th></tr></thead>
            <tbody>
            <?php foreach ($payments as $p): ?>
                <tr>
                    <td><strong><?= e($p['receipt_number']) ?></strong><div class="muted"><?= e($p['reference_number'] ?: '') ?></div></td>
                    <td><?= e(money($p['amount'])) ?></td>
                    <td><?= e(__('payment.method.' . $p['payment_method'])) ?></td>
                    <td><?= e(format_datetime($p['paid_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
