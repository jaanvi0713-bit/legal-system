<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['client']);
$pdo = db();
ensure_invoice_items_table($pdo);
require_once __DIR__ . '/../includes/nav-icons.php';
$uid = (int) current_user()['id'];
$id = (int) get('id', 0);

if ($id < 1) {
    flash('error', __('flash.receipt.not_found'));
    redirect('payments.php');
}

$stmt = $pdo->prepare(
    "SELECT p.*,
            u.first_name, u.last_name, u.email, u.phone, u.address, u.company_name AS client_company,
            i.invoice_number, i.total AS invoice_total, i.amount AS invoice_amount, i.tax AS invoice_tax,
            i.title AS invoice_title, i.description AS invoice_description, i.case_id, i.bank_account_id,
            c.case_number
     FROM payments p
     JOIN users u ON u.id = p.client_id
     LEFT JOIN invoices i ON i.id = p.invoice_id
     LEFT JOIN cases c ON c.id = i.case_id
     WHERE p.id = ? AND p.client_id = ?"
);
$stmt->execute([$id, $uid]);
$payment = $stmt->fetch();
if (!$payment) {
    flash('error', __('flash.receipt.not_found'));
    redirect('payments.php');
}

$invoiceId = (int) ($payment['invoice_id'] ?? 0);
$invoiceLines = [];
$subtotal = 0.0;
$vatAmount = 0.0;
$grand = (float) ($payment['amount'] ?? 0);
if ($invoiceId > 0) {
    $totals = invoice_display_totals($pdo, $invoiceId, [
        'amount' => $payment['invoice_amount'] ?? 0,
        'tax' => $payment['invoice_tax'] ?? 0,
        'total' => $payment['invoice_total'] ?? 0,
        'title' => $payment['invoice_title'] ?? '',
        'description' => $payment['invoice_description'] ?? '',
    ]);
    $invoiceLines = $totals['lines'];
    $subtotal = $totals['subtotal'];
    $vatAmount = $totals['vat'];
    $grand = $totals['grand'];
} else {
    $invoiceLines = [[
        'description' => __('finance.payment_towards'),
        'quantity' => 1,
        'unit_price' => (float) $payment['amount'],
        'vat_amount' => 0,
        'line_total' => (float) $payment['amount'],
    ]];
    $subtotal = (float) $payment['amount'];
    $grand = $subtotal;
}

$invoicePaid = $invoiceId > 0 ? invoice_paid_total($pdo, $invoiceId) : (float) $payment['amount'];
$amountPaid = $invoicePaid;
$amountDue = max(0, round($grand - $invoicePaid, 2));
$selectedBank = get_bank_account($pdo, isset($payment['bank_account_id']) ? (int) $payment['bank_account_id'] : null);

$firmName = get_setting($pdo, 'company_name', app_config('name', 'LEGAL PRO'));
$firmEmail = get_setting($pdo, 'company_email', '');
$firmPhone = get_setting($pdo, 'company_phone', '');
$firmAddress = get_setting($pdo, 'company_address', '');
$firmVat = get_setting($pdo, 'company_vat', get_setting($pdo, 'company_registration', ''));

$receiptNo = $payment['receipt_number'] ?: ('RCP-' . $payment['id']);
$methodKey = 'payment.method.' . ($payment['payment_method'] ?: 'other');
$methodLabel = __($methodKey);
if ($methodLabel === $methodKey) {
    $methodLabel = ucfirst(str_replace('_', ' ', (string) ($payment['payment_method'] ?: 'other')));
}
$issueDate = $payment['paid_at'] ? date('d/m/Y', strtotime($payment['paid_at'])) : '—';

$pageTitle = $receiptNo;
$pageSubtitle = __('finance.receipt_document');
$portal = 'client';
$activeNav = 'payments';
$bodyClass = 'page-invoice-doc page-receipt-doc';
require __DIR__ . '/../includes/header.php';
?>
<div class="inv-doc-toolbar no-print">
    <a class="btn btn-secondary btn-sm inv-doc-back" href="payments.php"><?= __e('common.back') ?></a>
    <div class="inv-doc-actions">
        <div class="inv-doc-action-btns">
            <button type="button" class="btn btn-primary btn-sm inv-doc-print-btn" onclick="window.print()"><?= __e('finance.print_save_pdf') ?></button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/receipt_document.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
