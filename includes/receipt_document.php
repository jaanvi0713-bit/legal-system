<?php
/**
 * Shared receipt document markup (invoice-style).
 * Expected vars:
 *   $payment, $receiptNo, $firmName, $firmEmail, $firmPhone, $firmAddress, $firmVat,
 *   $invoiceLines, $subtotal, $vatAmount, $grand, $amountPaid, $amountDue,
 *   $methodLabel, $selectedBank (nullable), $issueDate (d/m/Y)
 */
if (!isset($invoiceLines) || !is_array($invoiceLines)) {
    $invoiceLines = [];
}
$payNotes = trim((string) ($payment['notes'] ?? ''));
$invoiceRef = trim((string) ($payment['invoice_number'] ?? ''));
?>
<article class="inv-doc" id="receiptDocument">
    <header class="inv-doc-top">
        <div class="inv-doc-brand">
            <?= brand_mark_html('inv-doc-logo brand-mark') ?>
            <div class="inv-doc-brand-text">
                <div class="inv-doc-firm-name"><?= e(strtoupper($firmName)) ?></div>
            </div>
        </div>
        <div class="inv-doc-title-block">
            <h1><?= __e('finance.receipt_word') ?></h1>
            <div class="inv-doc-number">#<?= e($receiptNo) ?></div>
        </div>
    </header>

    <section class="inv-doc-parties">
        <div class="inv-doc-from">
            <strong><?= e(strtoupper($firmName)) ?></strong>
            <?php if ($firmAddress): ?><p><?= nl2br(e($firmAddress)) ?></p><?php endif; ?>
            <?php if ($firmEmail): ?><p><?= e($firmEmail) ?></p><?php endif; ?>
            <?php if ($firmPhone): ?><p><?= e($firmPhone) ?></p><?php endif; ?>
            <div class="inv-doc-dates">
                <div><span><?= __e('finance.issue_date') ?></span> <strong><?= e($issueDate) ?></strong></div>
                <?php if ($invoiceRef !== ''): ?>
                <div><span><?= __e('finance.invoice_reference') ?></span> <strong><?= e($invoiceRef) ?></strong></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="inv-doc-billto">
            <strong><?= __e('finance.bill_to') ?></strong>
            <p class="inv-doc-client"><?= e(full_name($payment)) ?></p>
            <?php if (!empty($payment['client_company'])): ?><p><?= e($payment['client_company']) ?></p><?php endif; ?>
            <?php if (!empty($payment['address'])): ?><p><?= nl2br(e($payment['address'])) ?></p><?php endif; ?>
            <?php if (!empty($payment['email'])): ?><p><?= e($payment['email']) ?></p><?php endif; ?>
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
            <?php if ($invoiceLines): ?>
                <?php foreach ($invoiceLines as $line): ?>
                <tr>
                    <td><?= e((string) ($line['description'] ?? '')) ?></td>
                    <td class="is-center"><?= e(rtrim(rtrim(number_format((float) ($line['quantity'] ?? 0), 2, '.', ''), '0'), '.') ?: '0') ?></td>
                    <td class="is-right"><?= e(money($line['unit_price'] ?? 0)) ?></td>
                    <td class="is-right"><?= e(money($line['vat_amount'] ?? 0)) ?></td>
                    <td class="is-right"><?= e(money($line['line_total'] ?? 0)) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td><?= __e('finance.payment_towards') ?><?= $invoiceRef !== '' ? ' ' . e($invoiceRef) : '' ?></td>
                    <td class="is-center">1</td>
                    <td class="is-right"><?= e(money($payment['amount'])) ?></td>
                    <td class="is-right"><?= e(money(0)) ?></td>
                    <td class="is-right"><?= e(money($payment['amount'])) ?></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <section class="inv-doc-summary">
        <div class="inv-doc-summary-row"><span><?= __e('finance.subtotal') ?></span><strong><?= e(money($subtotal)) ?></strong></div>
        <div class="inv-doc-summary-row"><span><?= __e('finance.vat_amount') ?></span><strong><?= e(money($vatAmount)) ?></strong></div>
        <div class="inv-doc-summary-row"><span><?= __e('finance.net_ex_vat') ?></span><strong><?= e(money($subtotal)) ?></strong></div>
        <div class="inv-doc-summary-row"><span><?= __e('finance.net_inc_vat') ?></span><strong><?= e(money($subtotal + $vatAmount)) ?></strong></div>
        <div class="inv-doc-summary-row"><span><?= __e('finance.amount_paid') ?></span><strong><?= e(money($amountPaid)) ?></strong></div>
        <div class="inv-doc-summary-divider"></div>
        <div class="inv-doc-summary-row is-emphasis"><span><?= __e('finance.amount_due') ?></span><strong><?= e(money($amountDue)) ?></strong></div>
        <div class="inv-doc-summary-divider"></div>
        <div class="inv-doc-summary-row is-grand"><span><?= __e('finance.grand_total') ?></span><strong><?= e(money($grand)) ?></strong></div>
    </section>

    <div class="rcp-info-box">
        <?= __e('finance.payment_received') ?>:
        <strong class="rcp-amount"><?= e(money($payment['amount'])) ?></strong>
        <?= __e('finance.via_method_on', [
            'method' => $methodLabel,
            'date' => $payment['paid_at'] ? format_datetime($payment['paid_at']) : '—',
        ]) ?>
    </div>

    <?php if ($payNotes !== ''): ?>
    <div class="rcp-info-box">
        <strong><?= __e('finance.notes_label') ?></strong> <?= e($payNotes) ?>
    </div>
    <?php endif; ?>

    <footer class="inv-doc-footer">
        <div class="inv-doc-payable">
            <strong><?= __e('finance.payable_to') ?></strong>
            <div class="inv-doc-payable-name"><?= e(strtoupper(($selectedBank['account_name'] ?? '') ?: $firmName)) ?></div>
            <?php if ($firmVat): ?>
                <p><?= __e('finance.vat_number') ?>: <?= e($firmVat) ?></p>
            <?php endif; ?>
            <?php if ($selectedBank && (($selectedBank['bank'] ?? '') !== '' || ($selectedBank['account_number'] ?? '') !== '' || ($selectedBank['iban'] ?? '') !== '')): ?>
                <div class="inv-doc-bank">
                    <div class="inv-doc-bank-label"><?= e($selectedBank['label'] ?? '') ?></div>
                    <?php if (!empty($selectedBank['bank'])): ?><p><span><?= __e('settings.payments.bank_name') ?></span> <?= e($selectedBank['bank']) ?></p><?php endif; ?>
                    <?php if (!empty($selectedBank['account_number'])): ?><p><span><?= __e('settings.payments.account_number') ?></span> <?= e($selectedBank['account_number']) ?></p><?php endif; ?>
                    <?php if (!empty($selectedBank['iban'])): ?><p><span><?= __e('settings.payments.iban') ?></span> <?= e($selectedBank['iban']) ?></p><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <p class="inv-doc-thanks"><?= __e('finance.thank_you') ?></p>
    </footer>
</article>
