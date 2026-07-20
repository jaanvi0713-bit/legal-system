<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'staff']);
$pdo = db();
ensure_invoice_items_table($pdo);
require_once __DIR__ . '/../includes/nav-icons.php';

$id = (int) get('id', 0);

$safe_return = static function (string $url, string $fallback = 'cases.php'): string {
    $url = trim($url);
    if ($url === '' || str_contains($url, '://') || str_starts_with($url, '//')) {
        return $fallback;
    }
    return $url;
};
$case_receipts_url = static function (?int $caseId): string {
    if ($caseId && $caseId > 0) {
        return 'cases.php?action=view&id=' . $caseId . '&tab=receipts';
    }
    return 'cases.php';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');

    if ($fa === 'email_client') {
        $paymentId = (int) post('payment_id');
        $stmt = $pdo->prepare(
            "SELECT p.*, u.email, u.first_name, u.last_name, i.invoice_number, i.case_id
             FROM payments p
             JOIN users u ON u.id = p.client_id
             LEFT JOIN invoices i ON i.id = p.invoice_id
             WHERE p.id = ?"
        );
        $stmt->execute([$paymentId]);
        $pay = $stmt->fetch();
        if (!$pay) {
            flash('error', __('flash.receipt.not_found'));
            redirect('cases.php');
        }
        $receiptNo = $pay['receipt_number'] ?: ('#' . $paymentId);
        create_notification(
            $pdo,
            (int) $pay['client_id'],
            'notify.receipt_issued',
            notify_payload('notify.msg.receipt_issued', [
                'number' => $receiptNo,
                'amount' => money($pay['amount']),
                'invoice' => $pay['invoice_number'] ?: '—',
            ]),
            'payment',
            '../client/receipt.php?id=' . $paymentId,
            current_user()['id']
        );
        $from = $safe_return((string) post('return_to', ''), $case_receipts_url((int) ($pay['case_id'] ?? 0)));
        flash('success', __('flash.receipt.emailed', ['email' => $pay['email']]));
        redirect('receipt.php?id=' . $paymentId . '&mailto=1&from=' . rawurlencode($from));
    }

    if ($fa === 'delete_payment') {
        $paymentId = (int) post('payment_id');
        $back = $safe_return((string) post('return_to', 'cases.php'), 'cases.php');
        $stmt = $pdo->prepare(
            "SELECT p.id, p.receipt_number, p.invoice_id, i.case_id
             FROM payments p
             LEFT JOIN invoices i ON i.id = p.invoice_id
             WHERE p.id = ?"
        );
        $stmt->execute([$paymentId]);
        $pay = $stmt->fetch();
        if (!$pay) {
            flash('error', __('flash.receipt.not_found'));
            redirect($back);
        }
        $invoiceId = (int) ($pay['invoice_id'] ?? 0);
        $pdo->prepare('DELETE FROM payments WHERE id = ?')->execute([$paymentId]);
        if ($invoiceId > 0) {
            sync_invoice_payment_status($pdo, $invoiceId);
        }
        flash('success', __('flash.receipt.deleted', ['number' => $pay['receipt_number'] ?: ('#' . $paymentId)]));
        redirect($back !== '' ? $back : $case_receipts_url((int) ($pay['case_id'] ?? 0)));
    }
}

if ($id < 1) {
    flash('error', __('flash.receipt.not_found'));
    redirect('cases.php');
}

$stmt = $pdo->prepare(
    "SELECT p.*,
            u.first_name, u.last_name, u.email, u.phone, u.address, u.company_name AS client_company,
            i.invoice_number, i.total AS invoice_total, i.amount AS invoice_amount, i.tax AS invoice_tax,
            i.title AS invoice_title, i.description AS invoice_description, i.case_id, i.bank_account_id,
            c.case_number, c.title AS case_title,
            CONCAT(rb.first_name, ' ', rb.last_name) AS recorder_name
     FROM payments p
     JOIN users u ON u.id = p.client_id
     LEFT JOIN invoices i ON i.id = p.invoice_id
     LEFT JOIN cases c ON c.id = i.case_id
     LEFT JOIN users rb ON rb.id = p.recorded_by
     WHERE p.id = ?"
);
$stmt->execute([$id]);
$payment = $stmt->fetch();
if (!$payment) {
    flash('error', __('flash.receipt.not_found'));
    redirect('cases.php');
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

$caseId = (int) ($payment['case_id'] ?? 0);
$returnTo = $safe_return((string) get('from', ''), $case_receipts_url($caseId > 0 ? $caseId : null));
$receiptNo = $payment['receipt_number'] ?: ('RCP-' . $payment['id']);
$methodKey = 'payment.method.' . ($payment['payment_method'] ?: 'other');
$methodLabel = __($methodKey);
if ($methodLabel === $methodKey) {
    $methodLabel = ucfirst(str_replace('_', ' ', (string) ($payment['payment_method'] ?: 'other')));
}
$issueDate = $payment['paid_at'] ? date('d/m/Y', strtotime($payment['paid_at'])) : '—';

$pageTitle = $receiptNo;
$pageSubtitle = __('finance.receipt_document');
$portal = 'admin';
$activeNav = 'cases';
$bodyClass = 'page-invoice-doc page-receipt-doc';
$mailtoOnLoad = get('mailto') === '1';
require __DIR__ . '/../includes/header.php';

$mailtoHref = 'mailto:' . rawurlencode((string) $payment['email'])
    . '?subject=' . rawurlencode('Receipt ' . $receiptNo)
    . '&body=' . rawurlencode(
        'Please find your payment receipt ' . $receiptNo
        . ' for ' . money($payment['amount'])
        . (!empty($payment['invoice_number']) ? (' against invoice ' . $payment['invoice_number']) : '')
        . '. View it in your client portal.'
    );
?>
<div class="inv-doc-toolbar no-print">
    <a class="btn btn-secondary btn-sm inv-doc-back" href="<?= e($returnTo) ?>"><?= __e('common.back') ?></a>
    <div class="inv-doc-actions">
        <div class="inv-doc-action-btns">
            <button type="button" class="btn btn-primary btn-sm inv-doc-print-btn" onclick="window.print()"><?= __e('finance.print_save_pdf') ?></button>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="email_client">
                <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                <button class="btn btn-secondary btn-sm" type="submit"><?= __e('finance.email_client') ?></button>
            </form>
            <form method="post" onsubmit="return confirm(<?= json_encode(__('finance.delete_receipt_confirm', ['number' => $receiptNo]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="delete_payment">
                <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                <button class="btn btn-row-delete btn-sm" type="submit"><?= __e('common.delete') ?></button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/receipt_document.php'; ?>
<?php if ($mailtoOnLoad && !empty($payment['email'])): ?>
<script>window.location.href = <?= json_encode($mailtoHref) ?>;</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
