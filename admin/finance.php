<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'staff']);
$pdo = db();
$action = get('action', 'list');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'invoice') {
        $amount = (float) post('amount');
        $tax = (float) post('tax');
        $total = $amount + $tax;
        $number = generate_invoice_number($pdo);
        $pdo->prepare('INSERT INTO invoices (invoice_number, case_id, client_id, title, description, amount, tax, total, status, due_date, issued_at, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                $number, post('case_id') ?: null, (int) post('client_id'), post('title'), post('description'),
                $amount, $tax, $total, post('status'), post('due_date') ?: null, post('issued_at') ?: date('Y-m-d'), current_user()['id'],
            ]);
        create_notification($pdo, (int) post('client_id'), 'New invoice', $number . ' issued for ' . money($total), 'payment', '../client/payments.php', current_user()['id']);
        flash('success', 'Invoice ' . $number . ' created.');
        redirect('finance.php');
    }
    if ($fa === 'payment') {
        $receipt = generate_receipt_number($pdo);
        $pdo->prepare('INSERT INTO payments (invoice_id, client_id, amount, payment_method, reference_number, receipt_number, notes, paid_at, recorded_by) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([
                post('invoice_id') ?: null, (int) post('client_id'), (float) post('amount'), post('payment_method'),
                post('reference_number'), $receipt, post('notes'), post('paid_at') ?: date('Y-m-d H:i:s'), current_user()['id'],
            ]);
        if (post('invoice_id')) {
            $invId = (int) post('invoice_id');
            $paid = (float) $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=?')->execute([$invId]) ?: 0;
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
        redirect('finance.php');
    }
}

$clients = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role='client' ORDER BY first_name")->fetchAll();
$cases = $pdo->query('SELECT id, case_number, title, client_id FROM cases ORDER BY created_at DESC')->fetchAll();
$invoices = $pdo->query("SELECT i.*, CONCAT(u.first_name,' ',u.last_name) AS client_name FROM invoices i JOIN users u ON u.id=i.client_id ORDER BY i.created_at DESC")->fetchAll();
$payments = $pdo->query("SELECT p.*, CONCAT(u.first_name,' ',u.last_name) AS client_name, i.invoice_number FROM payments p JOIN users u ON u.id=p.client_id LEFT JOIN invoices i ON i.id=p.invoice_id ORDER BY p.paid_at DESC LIMIT 20")->fetchAll();
$revenue = (float) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM payments')->fetchColumn();
$outstanding = (float) $pdo->query("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status IN ('sent','partial','overdue')")->fetchColumn();
$paidSum = (float) $pdo->query("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN invoices i ON i.id=p.invoice_id WHERE i.status IN ('sent','partial','overdue','paid')")->fetchColumn();
$outstandingBal = max(0, (float)$pdo->query("SELECT COALESCE(SUM(i.total - IFNULL((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id=i.id),0)),0) FROM invoices i WHERE i.status IN ('sent','partial','overdue','draft')")->fetchColumn());

$pageTitle = 'Financial Management';
$pageSubtitle = 'Billing, invoices, payments, and revenue';
$portal = 'admin';
$activeNav = 'finance';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-3">
    <div class="stat-card"><div class="stat-label">Total revenue</div><div class="stat-value"><?= e(money($revenue)) ?></div></div>
    <div class="stat-card"><div class="stat-label">Outstanding balance</div><div class="stat-value"><?= e(money($outstandingBal)) ?></div></div>
    <div class="stat-card"><div class="stat-label">Invoices</div><div class="stat-value"><?= count($invoices) ?></div></div>
</div>

<?php if ($action === 'invoice'): ?>
<div class="panel">
    <h2>Create invoice</h2>
    <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="form_action" value="invoice">
        <div class="form-group"><label>Client</label><select name="client_id" required><?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e(full_name($c)) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Case</label><select name="case_id"><option value="">—</option><?php foreach ($cases as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['case_number']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group full"><label>Title</label><input name="title" required></div>
        <div class="form-group"><label>Amount</label><input type="number" step="0.01" name="amount" required></div>
        <div class="form-group"><label>Tax</label><input type="number" step="0.01" name="tax" value="0"></div>
        <div class="form-group"><label>Status</label><select name="status"><?php foreach (['draft','sent','partial','paid','overdue'] as $s): ?><option value="<?= $s ?>"><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Due date</label><input type="date" name="due_date"></div>
        <div class="form-group"><label>Issued</label><input type="date" name="issued_at" value="<?= date('Y-m-d') ?>"></div>
        <div class="form-group full"><label>Description</label><textarea name="description"></textarea></div>
        <div class="form-actions full"><button class="btn btn-primary" type="submit">Save invoice</button><a class="btn btn-ghost" href="finance.php">Cancel</a></div>
    </form>
</div>
<?php elseif ($action === 'payment'): ?>
<div class="panel">
    <h2>Record payment</h2>
    <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="form_action" value="payment">
        <div class="form-group"><label>Client</label><select name="client_id" required><?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e(full_name($c)) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Invoice</label><select name="invoice_id"><option value="">—</option><?php foreach ($invoices as $i): ?><option value="<?= (int)$i['id'] ?>"><?= e($i['invoice_number'].' · '.money($i['total'])) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Amount</label><input type="number" step="0.01" name="amount" required></div>
        <div class="form-group"><label>Method</label><select name="payment_method"><?php foreach (['bank_transfer','card','cash','cheque','online','other'] as $m): ?><option value="<?= $m ?>"><?= ucwords(str_replace('_',' ',$m)) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Reference</label><input name="reference_number"></div>
        <div class="form-group"><label>Paid at</label><input type="datetime-local" name="paid_at" value="<?= date('Y-m-d\TH:i') ?>"></div>
        <div class="form-group full"><label>Notes</label><textarea name="notes"></textarea></div>
        <div class="form-actions full"><button class="btn btn-accent" type="submit">Record &amp; generate receipt</button><a class="btn btn-ghost" href="finance.php">Cancel</a></div>
    </form>
</div>
<?php else: ?>
<div class="panel">
    <div class="panel-header">
        <h2>Billing overview</h2>
        <div class="quick-links">
            <a class="btn btn-sm btn-primary" href="?action=invoice">Create invoice</a>
            <a class="btn btn-sm btn-accent" href="?action=payment">Record payment</a>
        </div>
    </div>
</div>
<div class="grid grid-2">
    <div class="panel">
        <h2>Invoices</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Invoice</th><th>Client</th><th>Total</th><th>Status</th><th>Due</th></tr></thead>
                <tbody>
                <?php foreach ($invoices as $i): ?>
                    <tr>
                        <td><strong><?= e($i['invoice_number']) ?></strong><div class="muted"><?= e($i['title']) ?></div></td>
                        <td><?= e($i['client_name']) ?></td>
                        <td><?= e(money($i['total'])) ?></td>
                        <td><?= status_badge($i['status']) ?></td>
                        <td><?= e(format_date($i['due_date'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="panel">
        <h2>Recent payments / receipts</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Receipt</th><th>Client</th><th>Amount</th><th>Invoice</th></tr></thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><strong><?= e($p['receipt_number']) ?></strong><div class="muted"><?= e(format_datetime($p['paid_at'])) ?></div></td>
                        <td><?= e($p['client_name']) ?></td>
                        <td><?= e(money($p['amount'])) ?></td>
                        <td><?= e($p['invoice_number'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
