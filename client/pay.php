<?php
/**
 * Prototype payment page.
 * Opens from invoice Pay Now without requiring client login.
 * Swap payment_gateway_driver later to connect Stripe/PayPal/Juice.
 */
require_once __DIR__ . '/../includes/auth.php';
$pdo = db();
ensure_invoice_bank_column($pdo);

$token = trim((string) (get('token') ?: post('token')));
if ($token === '') {
    http_response_code(404);
    exit(__('flash.invoice.pay_link_invalid'));
}

$stmt = $pdo->prepare(
    'SELECT i.*, u.first_name, u.last_name, u.email, u.phone, u.address, u.company_name AS client_company,
            c.case_number, c.title AS case_title
     FROM invoices i
     JOIN users u ON u.id = i.client_id
     LEFT JOIN cases c ON c.id = i.case_id
     WHERE i.payment_link_token = ?
     LIMIT 1'
);
$stmt->execute([$token]);
$invoice = $stmt->fetch();
if (!$invoice) {
    http_response_code(404);
    exit(__('flash.invoice.pay_link_invalid'));
}

$invoiceId = (int) $invoice['id'];

// Prefer line-item totals when present (keeps Pay Now amount accurate)
$lines = invoice_line_items($pdo, $invoiceId);
$lines = array_values(array_filter($lines, static fn($r) => is_array($r) && trim((string) ($r['description'] ?? '')) !== ''));
if ($lines) {
    $lineSub = 0.0;
    $lineVat = 0.0;
    $lineGrand = 0.0;
    foreach ($lines as $line) {
        $qty = (float) ($line['quantity'] ?? 1);
        $price = (float) ($line['unit_price'] ?? 0);
        $lineSub += round($qty * $price, 2);
        $lineVat += (float) ($line['vat_amount'] ?? 0);
        $lineGrand += (float) ($line['line_total'] ?? (($qty * $price) + (float) ($line['vat_amount'] ?? 0)));
    }
    $lineSub = round($lineSub, 2);
    $lineVat = round($lineVat, 2);
    $lineGrand = round($lineGrand > 0 ? $lineGrand : ($lineSub + $lineVat), 2);
    if (abs($lineGrand - (float) $invoice['total']) > 0.009) {
        $pdo->prepare('UPDATE invoices SET amount = ?, tax = ?, total = ? WHERE id = ?')
            ->execute([$lineSub, $lineVat, $lineGrand, $invoiceId]);
        $invoice['amount'] = $lineSub;
        $invoice['tax'] = $lineVat;
        $invoice['total'] = $lineGrand;
    }
}

$paid = invoice_paid_total($pdo, $invoiceId);
$due = max(0, round((float) $invoice['total'] - $paid, 2));
$payStatus = invoice_payment_status($invoice);
$result = (string) get('result', '');
$viewMode = (string) get('view', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) post('form_action');
    if ($action === 'demo_pay_complete') {
        if ($payStatus !== 'paid' && $due > 0) {
            apply_invoice_payment_result($pdo, $invoiceId, 'paid');
        }
        redirect('pay.php?token=' . rawurlencode($token) . '&result=paid');
    }
    if ($action === 'demo_pay_failed') {
        if ($payStatus !== 'paid') {
            apply_invoice_payment_result(
                $pdo,
                $invoiceId,
                'failed',
                'FAIL-' . strtoupper(substr($token, 0, 8))
            );
        }
        redirect('pay.php?token=' . rawurlencode($token) . '&result=failed');
    }
}

// Refresh after possible redirect
$stmt->execute([$token]);
$invoice = $stmt->fetch() ?: $invoice;
$paid = invoice_paid_total($pdo, $invoiceId);
$due = max(0, round((float) $invoice['total'] - $paid, 2));
$payStatus = invoice_payment_status($invoice);
if ($invoice['status'] === 'paid') {
    $payStatus = 'paid';
}

$latestPayment = null;
$payStmt = $pdo->prepare(
    'SELECT * FROM payments WHERE invoice_id = ? ORDER BY paid_at DESC, id DESC LIMIT 1'
);
$payStmt->execute([$invoiceId]);
$latestPayment = $payStmt->fetch() ?: null;

$firmName = get_setting($pdo, 'company_name', app_config('name', 'LEGAL PRO'));
$base = rtrim((string) app_config('url'), '/');
$css = $base . '/assets/css/style.css?v=' . (int) @filemtime(__DIR__ . '/../assets/css/style.css');
$canDemoPay = $payStatus !== 'paid' && $due > 0;
$isPaid = $result === 'paid' || $payStatus === 'paid' || $due <= 0;
$invoiceViewUrl = 'pay.php?token=' . rawurlencode($token) . '&view=invoice';
$receiptPrintUrl = 'pay.php?token=' . rawurlencode($token) . '&view=receipt';

$accent = get_setting($pdo, 'branding_accent', '#023e8a') ?: '#023e8a';
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? strtolower($accent) : '#023e8a';
$ar = hexdec(substr($accent, 1, 2));
$ag = hexdec(substr($accent, 3, 2));
$ab = hexdec(substr($accent, 5, 2));
$clamp = static fn(int $v): int => max(0, min(255, $v));
$hexOf = static fn(int $r, int $g, int $b): string => sprintf('#%02x%02x%02x', $clamp($r), $clamp($g), $clamp($b));
$accentDeep = $hexOf($ar - 40, $ag - 40, $ab - 40);
$accentBright = $hexOf($ar + 28, $ag + 28, $ab + 28);
$gradBtn = "linear-gradient(135deg, {$accent} 0%, {$accentDeep} 100%)";

// Printable invoice / receipt views
if ($viewMode === 'invoice' || $viewMode === 'receipt') {
    require_once __DIR__ . '/../includes/nav-icons.php';
    $totals = invoice_display_totals($pdo, $invoiceId, $invoice);
    $firmEmail = get_setting($pdo, 'company_email', '');
    $firmPhone = get_setting($pdo, 'company_phone', '');
    $firmAddress = get_setting($pdo, 'company_address', '');
    $firmVat = get_setting($pdo, 'company_vat', get_setting($pdo, 'company_registration', ''));
    $selectedBank = get_bank_account($pdo, isset($invoice['bank_account_id']) ? (int) $invoice['bank_account_id'] : null);

    if ($viewMode === 'receipt') {
        if (!$latestPayment) {
            http_response_code(404);
            exit(__('flash.receipt.not_found'));
        }
        $payment = array_merge($latestPayment, [
            'first_name' => $invoice['first_name'],
            'last_name' => $invoice['last_name'],
            'email' => $invoice['email'],
            'address' => $invoice['address'] ?? '',
            'client_company' => $invoice['client_company'] ?? '',
            'invoice_number' => $invoice['invoice_number'],
            'phone' => $invoice['phone'] ?? '',
        ]);
        $receiptNo = $payment['receipt_number'] ?: ('RCP-' . $payment['id']);
        $methodKey = 'payment.method.' . ($payment['payment_method'] ?: 'other');
        $methodLabel = __($methodKey);
        if ($methodLabel === $methodKey) {
            $methodLabel = ucfirst(str_replace('_', ' ', (string) ($payment['payment_method'] ?: 'other')));
        }
        $invoiceLines = $totals['lines'];
        $subtotal = $totals['subtotal'];
        $vatAmount = $totals['vat'];
        $grand = $totals['grand'];
        $amountPaid = $paid;
        $amountDue = max(0, round($grand - $paid, 2));
        $issueDate = $payment['paid_at'] ? date('d/m/Y', strtotime($payment['paid_at'])) : '—';
        ?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('finance.receipt_document') . ' · ' . $receiptNo) ?></title>
    <link rel="stylesheet" href="<?= e($css) ?>">
    <style>
        :root {
            --primary: <?= e($accent) ?>;
            --primary-deep: <?= e($accentDeep) ?>;
            --primary-rgb: <?= (int) $ar ?>, <?= (int) $ag ?>, <?= (int) $ab ?>;
            --grad-btn: <?= e($gradBtn) ?>;
            --accent: <?= e($accent) ?>;
        }
        body.page-pay-print { background: #eef2f7; margin: 0; padding: 1.25rem; }
        .pay-print-toolbar { max-width: 920px; margin: 0 auto 1rem; display: flex; gap: 0.6rem; flex-wrap: wrap; }
        @media print {
            .pay-print-toolbar, .no-print { display: none !important; }
            body.page-pay-print { background: #fff; padding: 0; }
            .inv-doc { box-shadow: none !important; border: 0 !important; }
            .inv-doc-summary { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body class="page-pay-print">
    <div class="pay-print-toolbar no-print">
        <button type="button" class="btn btn-primary btn-sm inv-doc-print-btn" onclick="window.print()"><?= __e('finance.print_save_pdf') ?></button>
        <a class="btn btn-secondary btn-sm" href="pay.php?token=<?= e(rawurlencode($token)) ?>"><?= __e('common.back') ?></a>
    </div>
    <?php require __DIR__ . '/../includes/receipt_document.php'; ?>
</body>
</html>
        <?php
        exit;
    }

    // Invoice printable view
    $lines = $totals['lines'];
    ?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('finance.invoice_document') . ' · ' . $invoice['invoice_number']) ?></title>
    <link rel="stylesheet" href="<?= e($css) ?>">
    <style>
        :root {
            --primary: <?= e($accent) ?>;
            --primary-deep: <?= e($accentDeep) ?>;
            --primary-rgb: <?= (int) $ar ?>, <?= (int) $ag ?>, <?= (int) $ab ?>;
            --grad-btn: <?= e($gradBtn) ?>;
            --accent: <?= e($accent) ?>;
        }
        body { background: #f4f6fa; margin: 0; padding: 1.25rem; }
        .pay-print-toolbar { max-width: 920px; margin: 0 auto 1rem; display: flex; gap: 0.6rem; flex-wrap: wrap; }
        @media print {
            .pay-print-toolbar { display: none !important; }
            body { background: #fff; padding: 0; }
            .inv-doc { box-shadow: none !important; border: 0 !important; }
            .inv-doc-summary { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="pay-print-toolbar no-print">
        <button type="button" class="btn btn-primary btn-sm inv-doc-print-btn" onclick="window.print()"><?= __e('finance.print_save_pdf') ?></button>
        <a class="btn btn-secondary btn-sm" href="pay.php?token=<?= e(rawurlencode($token)) ?>"><?= __e('common.back') ?></a>
    </div>
    <article class="inv-doc">
        <header class="inv-doc-top">
            <div class="inv-doc-brand">
                <?= brand_mark_html('inv-doc-logo brand-mark') ?>
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
                <?php foreach ($lines as $line): ?>
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
            <div class="inv-doc-summary-row"><span><?= __e('finance.subtotal') ?></span><strong><?= e(money($totals['subtotal'])) ?></strong></div>
            <div class="inv-doc-summary-row"><span><?= __e('finance.vat_amount') ?></span><strong><?= e(money($totals['vat'])) ?></strong></div>
            <div class="inv-doc-summary-row"><span><?= __e('finance.net_ex_vat') ?></span><strong><?= e(money($totals['subtotal'])) ?></strong></div>
            <div class="inv-doc-summary-row"><span><?= __e('finance.net_inc_vat') ?></span><strong><?= e(money($totals['subtotal'] + $totals['vat'])) ?></strong></div>
            <div class="inv-doc-summary-row"><span><?= __e('finance.amount_paid') ?></span><strong><?= e(money($paid)) ?></strong></div>
            <div class="inv-doc-summary-divider"></div>
            <div class="inv-doc-summary-row is-emphasis"><span><?= __e('finance.amount_due') ?></span><strong><?= e(money($due)) ?></strong></div>
            <div class="inv-doc-summary-divider"></div>
            <div class="inv-doc-summary-row is-grand"><span><?= __e('finance.grand_total') ?></span><strong><?= e(money($totals['grand'])) ?></strong></div>
        </section>
        <p class="inv-doc-thanks"><?= __e('finance.thank_you') ?></p>
    </article>
</body>
</html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('finance.pay_invoice') . ' · ' . $invoice['invoice_number']) ?></title>
    <link rel="stylesheet" href="<?= e($css) ?>">
    <style>
        :root {
            --primary: <?= e($accent) ?>;
            --primary-deep: <?= e($accentDeep) ?>;
            --primary-rgb: <?= (int) $ar ?>, <?= (int) $ag ?>, <?= (int) $ab ?>;
            --blue: <?= e($accent) ?>;
            --accent: <?= e($accent) ?>;
            --grad-btn: <?= e($gradBtn) ?>;
            --grad-primary: linear-gradient(135deg, <?= e($accentBright) ?> 0%, <?= e($accent) ?> 100%);
        }
    </style>
</head>
<body class="page-demo-pay-standalone">
<main class="demo-pay-standalone">
    <div class="pay-banner pay-banner-info" role="status">
        <span aria-hidden="true">ℹ</span>
        <?= __e('finance.demo_mode_banner') ?>
    </div>
    <?php if ($isPaid): ?>
    <div class="pay-banner pay-banner-success" role="status">
        <?= __e('finance.payment_completed_banner') ?>
    </div>
    <?php elseif ($result === 'failed' || $payStatus === 'failed'): ?>
    <div class="pay-banner pay-banner-danger" role="status">
        <?= __e('finance.demo_pay_failed') ?>
    </div>
    <?php endif; ?>

    <div class="panel demo-pay-panel pay-invoice-card">
        <div class="panel-header pay-invoice-head">
            <div>
                <p class="pay-firm-label"><?= e(strtoupper($firmName)) ?></p>
                <h2><?= __e('finance.pay_invoice') ?></h2>
            </div>
            <div><?= payment_status_badge($isPaid ? 'paid' : ($payStatus === 'none' ? 'pending' : $payStatus)) ?></div>
        </div>

        <div class="demo-pay-body">
            <div class="pay-invoice-summary">
                <div>
                    <span><?= __e('finance.invoice_number') ?></span>
                    <strong><?= e($invoice['invoice_number']) ?></strong>
                </div>
                <?php if (!empty($invoice['case_number'])): ?>
                <div>
                    <span><?= __e('common.case') ?></span>
                    <strong><?= e($invoice['case_number']) ?></strong>
                    <?php if (!empty($invoice['case_title'])): ?>
                        <em class="pay-case-title"><?= e($invoice['case_title']) ?></em>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div>
                    <span><?= __e('finance.bill_to') ?></span>
                    <strong><?= e(full_name($invoice)) ?></strong>
                </div>
                <div>
                    <span><?= __e('finance.due_date') ?></span>
                    <strong><?= e($invoice['due_date'] ? format_date($invoice['due_date'], 'M j, Y') : __('common.em_dash')) ?></strong>
                </div>
                <div class="pay-amount-due">
                    <span><?= __e('finance.amount_due') ?></span>
                    <strong><?= e(money($isPaid ? 0 : $due)) ?></strong>
                </div>
            </div>

            <?php if ($isPaid): ?>
                <div class="pay-received-box">
                    <div class="pay-received-title">
                        <span class="pay-received-check" aria-hidden="true">✓</span>
                        <strong><?= __e('finance.payment_received') ?></strong>
                    </div>
                    <?php if ($latestPayment): ?>
                        <p><?= __e('finance.paid_on') ?> <?= e(format_datetime($latestPayment['paid_at'])) ?></p>
                        <?php if (!empty($latestPayment['reference_number'])): ?>
                            <p><?= __e('finance.transaction_ref') ?>: <code><?= e($latestPayment['reference_number']) ?></code></p>
                        <?php endif; ?>
                        <?php if (!empty($latestPayment['receipt_number'])): ?>
                            <p><?= __e('finance.receipt_generated', ['number' => $latestPayment['receipt_number']]) ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p><?= __e('finance.demo_pay_success') ?></p>
                    <?php endif; ?>
                    <a class="btn btn-primary pay-download-btn" href="<?= e($receiptPrintUrl) ?>">
                        <?= __e('finance.download_receipt') ?>
                    </a>
                </div>
                <div class="pay-footer-actions">
                    <a class="btn btn-secondary" href="<?= e($invoiceViewUrl) ?>"><?= __e('finance.view_invoice') ?></a>
                </div>
            <?php elseif ($result === 'failed' || $payStatus === 'failed'): ?>
                <div class="demo-pay-actions">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_action" value="demo_pay_complete">
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <button class="btn btn-primary" type="submit"><?= __e('finance.complete_payment') ?> | <?= e(money($due)) ?></button>
                    </form>
                </div>
            <?php else: ?>
                <div class="demo-pay-actions">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_action" value="demo_pay_complete">
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <button class="btn btn-primary" type="submit"><?= __e('finance.complete_payment') ?> | <?= e(money($due)) ?></button>
                    </form>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_action" value="demo_pay_failed">
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <button class="btn btn-secondary" type="submit"><?= __e('finance.payment_failed_btn') ?></button>
                    </form>
                </div>
                <p class="muted demo-pay-note"><?= __e('finance.demo_pay_note') ?></p>
                <div class="pay-footer-actions">
                    <a class="btn btn-secondary btn-sm" href="<?= e($invoiceViewUrl) ?>"><?= __e('finance.view_invoice') ?></a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
