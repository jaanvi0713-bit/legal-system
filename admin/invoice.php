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
        $paymentTerms = trim((string) post('payment_terms'));
        $paymentInstructions = trim((string) post('payment_instructions'));
        $generatePayLink = post('generate_payment_link') === '1';
        $bankAccountId = (int) post('bank_account_id');
        $allBanks = get_bank_accounts($pdo);
        if ($bankAccountId < 1 || $bankAccountId > 3 || !isset($allBanks[$bankAccountId])) {
            $bankAccountId = get_default_bank_account_id($pdo) ?: 1;
        }

        $vatRate = max(0, (float) post('vat_rate', 0));
        $lines = [];
        $subtotal = 0.0;
        $vatTotal = 0.0;

        $appendLines = static function (array $descriptions, array $amounts, bool $isVat) use (&$lines, &$subtotal, &$vatTotal, $vatRate): void {
            foreach ($descriptions as $i => $desc) {
                $desc = trim((string) $desc);
                if ($desc === '') {
                    continue;
                }
                $net = max(0, (float) ($amounts[$i] ?? 0));
                $vat = $isVat ? round($net * ($vatRate / 100), 2) : 0.0;
                $lineTotal = round($net + $vat, 2);
                $lines[] = [
                    'description' => $desc,
                    'quantity' => 1,
                    'unit_price' => $net,
                    'vat_amount' => $vat,
                    'line_total' => $lineTotal,
                ];
                $subtotal += $net;
                $vatTotal += $vat;
            }
        };

        $appendLines($_POST['nonvat_description'] ?? [], $_POST['nonvat_amount'] ?? [], false);
        $appendLines($_POST['vat_description'] ?? [], $_POST['vat_amount'] ?? [], true);

        // Legacy fallback if old field names are posted
        if (!$lines && !empty($_POST['item_description'])) {
            $descriptions = $_POST['item_description'] ?? [];
            $quantities = $_POST['item_qty'] ?? [];
            $prices = $_POST['item_price'] ?? [];
            $vats = $_POST['item_vat'] ?? [];
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
        $payToken = null;
        $payLink = null;
        $payStatus = 'none';
        if ($generatePayLink) {
            $payToken = bin2hex(random_bytes(16));
            $payLink = invoice_payment_public_url($payToken);
            $payStatus = 'pending';
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO invoices (invoice_number, case_id, client_id, title, description, amount, tax, total, status, due_date, issued_at, created_by, bank_account_id, payment_terms, payment_instructions, payment_link_token, payment_link, payment_status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $number, $caseId, $clientId, $title, $notes ?: null,
                round($subtotal, 2), round($vatTotal, 2), $grand, $status,
                $dueDate, $issuedAt, current_user()['id'], $bankAccountId,
                $paymentTerms !== '' ? $paymentTerms : null,
                $paymentInstructions !== '' ? $paymentInstructions : null,
                $payToken,
                $payLink,
                $payStatus,
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
        $allBanks = get_bank_accounts($pdo);
        if ($bankAccountId < 1 || $bankAccountId > 3 || !isset($allBanks[$bankAccountId])) {
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
    $bankAccounts = get_bank_accounts($pdo);
    $configuredBanks = get_configured_bank_accounts($pdo);
    $defaultBankId = get_default_bank_account_id($pdo) ?: 1;
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
    $currencySym = trim(currency_symbol());
    $defaultTerms = __('finance.payment_terms_default');
    $pageTitle = __('finance.generate_invoice');
    $pageSubtitle = __('finance.generate_invoice_help');
    $portal = 'admin';
    $activeNav = 'cases';
    $bodyClass = 'page-invoice-generate';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="inv-gen-shell">
        <div class="panel inv-gen-panel">
            <div class="inv-gen-header">
                <div>
                    <h2><?= __e('finance.generate_invoice') ?></h2>
                    <p class="inv-gen-help"><?= __e('finance.generate_invoice_line_help') ?></p>
                </div>
                <a class="inv-gen-close" href="<?= e($genReturn) ?>" aria-label="<?= __e('common.close') ?>">×</a>
            </div>

            <form method="post" class="inv-gen-form" id="invoiceGenerateForm">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="generate">
                <input type="hidden" name="return_to" value="<?= e($genReturn) ?>">
                <input type="hidden" name="title" value="Professional services">
                <input type="hidden" name="issued_at" value="<?= e(date('Y-m-d')) ?>">
                <input type="hidden" name="status" value="sent">

                <?php if ($preClientId > 0 && $preCaseId > 0): ?>
                    <input type="hidden" name="client_id" value="<?= (int) $preClientId ?>">
                    <input type="hidden" name="case_id" value="<?= (int) $preCaseId ?>">
                <?php else: ?>
                <div class="inv-gen-parties">
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
                </div>
                <?php endif; ?>

                <section class="inv-svc-block" data-section="nonvat">
                    <div class="inv-svc-head">
                        <h3><?= __e('finance.non_vat_services') ?></h3>
                        <button type="button" class="inv-svc-add" data-add="nonvat">+ <?= __e('finance.add_service') ?></button>
                    </div>
                    <div class="inv-svc-rows" id="invNonVatRows">
                        <div class="inv-svc-row">
                            <input name="nonvat_description[]" placeholder="<?= __e('finance.service_desc_ph') ?>">
                            <div class="inv-svc-amount">
                                <span class="inv-svc-currency"><?= e($currencySym) ?></span>
                                <input type="number" step="0.01" min="0" name="nonvat_amount[]" value="0" class="inv-amt" data-group="nonvat">
                            </div>
                            <button type="button" class="inv-svc-remove" aria-label="<?= __e('common.delete') ?>">×</button>
                        </div>
                    </div>
                </section>

                <section class="inv-svc-block" data-section="vat">
                    <div class="inv-svc-head">
                        <h3><?= __e('finance.vat_services') ?></h3>
                        <div class="inv-svc-head-actions">
                            <label class="inv-vat-rate">
                                <span><?= __e('finance.vat_rate') ?></span>
                                <input type="number" step="0.01" min="0" max="100" name="vat_rate" id="invVatRate" value="0">
                            </label>
                            <button type="button" class="inv-svc-add" data-add="vat">+ <?= __e('finance.add_service') ?></button>
                        </div>
                    </div>
                    <div class="inv-svc-rows" id="invVatRows">
                        <div class="inv-svc-row">
                            <input name="vat_description[]" placeholder="<?= __e('finance.service_desc_ph') ?>">
                            <div class="inv-svc-amount">
                                <span class="inv-svc-currency"><?= e($currencySym) ?></span>
                                <input type="number" step="0.01" min="0" name="vat_amount[]" value="0" class="inv-amt" data-group="vat">
                            </div>
                            <button type="button" class="inv-svc-remove" aria-label="<?= __e('common.delete') ?>">×</button>
                        </div>
                    </div>
                </section>

                <section class="inv-gen-summary" aria-live="polite">
                    <div class="inv-gen-summary-col">
                        <div class="inv-gen-summary-row"><span><?= __e('finance.non_vat_net') ?></span><strong id="invNonVatNet"><?= e(money(0)) ?></strong></div>
                        <div class="inv-gen-summary-row"><span><?= __e('finance.vat_services_net') ?></span><strong id="invVatNet"><?= e(money(0)) ?></strong></div>
                        <div class="inv-gen-summary-row is-emphasis"><span><?= __e('finance.net_subtotal') ?></span><strong id="invNetSubtotal"><?= e(money(0)) ?></strong></div>
                    </div>
                    <div class="inv-gen-summary-col">
                        <div class="inv-gen-summary-row"><span><?= __e('finance.vat_amount') ?></span><strong id="invVatTotal"><?= e(money(0)) ?></strong></div>
                        <div class="inv-gen-summary-divider"></div>
                        <div class="inv-gen-summary-row is-grand"><span><?= __e('finance.invoice_total') ?></span><strong id="invGrand"><?= e(money(0)) ?></strong></div>
                    </div>
                </section>

                <div class="form-group inv-gen-bank">
                    <label class="inv-gen-bank-label">
                        <span class="inv-gen-bank-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 10l9-6 9 6"/><path d="M5 10v8M19 10v8M9 10v8M15 10v8M3 21h18"/></svg>
                        </span>
                        <?= __e('finance.bank_on_invoice') ?>
                    </label>
                    <select name="bank_account_id" required>
                        <?php foreach ($bankAccounts as $ba):
                            $isConfigured = isset($configuredBanks[(int) $ba['id']]);
                            $label = $ba['label'] ?: ('Bank account ' . $ba['id']);
                            if (!$isConfigured) {
                                $label .= ' (' . __('finance.bank_not_configured') . ')';
                            } elseif ($ba['bank'] || $ba['account_number']) {
                                $label .= ($ba['bank'] ? ' · ' . $ba['bank'] : '') . ($ba['account_number'] ? ' · ' . $ba['account_number'] : '');
                            }
                            ?>
                            <option value="<?= (int) $ba['id'] ?>" <?= (int) $ba['id'] === (int) $defaultBankId ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="inv-gen-hint"><?= __e('finance.bank_configure_hint') ?> <a href="settings.php?tab=payments"><?= __e('settings.payments.title') ?></a>.</p>
                </div>

                <div class="form-group">
                    <label><?= __e('finance.due_date') ?></label>
                    <input type="date" name="due_date" value="<?= e(date('Y-m-d', strtotime('+14 days'))) ?>">
                </div>
                <div class="form-group">
                    <label><?= __e('finance.payment_terms') ?></label>
                    <input type="text" name="payment_terms" value="<?= e($defaultTerms) ?>">
                </div>
                <div class="form-group">
                    <label><?= __e('finance.payment_instructions') ?></label>
                    <textarea name="payment_instructions" rows="3" placeholder="<?= __e('finance.payment_instructions_ph') ?>"></textarea>
                </div>
                <div class="form-group">
                    <label><?= __e('common.notes') ?></label>
                    <textarea name="description" rows="3" placeholder="<?= __e('finance.invoice_notes_ph') ?>"></textarea>
                </div>

                <label class="inv-paylink">
                    <input type="checkbox" name="generate_payment_link" value="1" checked>
                    <span><?= __e('finance.generate_payment_link') ?></span>
                </label>

                <div class="inv-gen-actions">
                    <a class="btn btn-secondary" href="<?= e($genReturn) ?>"><?= __e('common.cancel') ?></a>
                    <button class="btn btn-primary" type="submit"><?= __e('finance.generate') ?></button>
                </div>
            </form>
        </div>
    </div>
    <script>
    (function () {
      var currencySym = <?= json_encode($currencySym) ?>;
      var moneyFmt = function (n) {
        var v = (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2);
        try {
          v = Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } catch (e) {}
        return currencySym + v;
      };
      var nonVatRows = document.getElementById('invNonVatRows');
      var vatRows = document.getElementById('invVatRows');
      var vatRateInput = document.getElementById('invVatRate');
      var ph = <?= json_encode(__('finance.service_desc_ph')) ?>;
      var delLabel = <?= json_encode(__('common.delete')) ?>;

      function rowHtml(group) {
        var nameDesc = group === 'vat' ? 'vat_description[]' : 'nonvat_description[]';
        var nameAmt = group === 'vat' ? 'vat_amount[]' : 'nonvat_amount[]';
        return '<div class="inv-svc-row">' +
          '<input name="' + nameDesc + '" placeholder="' + ph.replace(/"/g, '&quot;') + '">' +
          '<div class="inv-svc-amount"><span class="inv-svc-currency">' + currencySym + '</span>' +
          '<input type="number" step="0.01" min="0" name="' + nameAmt + '" value="0" class="inv-amt" data-group="' + group + '"></div>' +
          '<button type="button" class="inv-svc-remove" aria-label="' + delLabel.replace(/"/g, '&quot;') + '">×</button></div>';
      }

      function sumGroup(root) {
        var total = 0;
        root.querySelectorAll('.inv-amt').forEach(function (input) {
          total += parseFloat(input.value) || 0;
        });
        return total;
      }

      function recalc() {
        var nonVat = sumGroup(nonVatRows);
        var vatNet = sumGroup(vatRows);
        var rate = parseFloat(vatRateInput.value) || 0;
        var vatAmt = Math.round(vatNet * rate) / 100;
        vatAmt = Math.round((vatAmt + Number.EPSILON) * 100) / 100;
        var netSub = nonVat + vatNet;
        var grand = netSub + vatAmt;
        document.getElementById('invNonVatNet').textContent = moneyFmt(nonVat);
        document.getElementById('invVatNet').textContent = moneyFmt(vatNet);
        document.getElementById('invNetSubtotal').textContent = moneyFmt(netSub);
        document.getElementById('invVatTotal').textContent = moneyFmt(vatAmt);
        document.getElementById('invGrand').textContent = moneyFmt(grand);
      }

      document.querySelectorAll('[data-add]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var group = btn.getAttribute('data-add');
          var root = group === 'vat' ? vatRows : nonVatRows;
          root.insertAdjacentHTML('beforeend', rowHtml(group));
          recalc();
        });
      });

      document.getElementById('invoiceGenerateForm').addEventListener('click', function (e) {
        var rem = e.target.closest('.inv-svc-remove');
        if (!rem) return;
        var row = rem.closest('.inv-svc-row');
        var root = row.parentElement;
        if (root.querySelectorAll('.inv-svc-row').length <= 1) {
          var desc = row.querySelector('input[name$="_description[]"]');
          if (desc) desc.value = '';
          var amt = row.querySelector('.inv-amt');
          if (amt) amt.value = '0';
        } else {
          row.remove();
        }
        recalc();
      });

      document.getElementById('invoiceGenerateForm').addEventListener('input', function (e) {
        if (e.target.matches('.inv-amt, #invVatRate')) recalc();
      });

      var caseSelect = document.getElementById('invCase');
      var clientSelect = document.getElementById('invClient');
      if (caseSelect && clientSelect) {
        caseSelect.addEventListener('change', function () {
          var opt = caseSelect.options[caseSelect.selectedIndex];
          var cid = opt && opt.getAttribute('data-client');
          if (cid) clientSelect.value = cid;
        });
      }

      document.getElementById('invoiceGenerateForm').addEventListener('submit', function (e) {
        var hasLine = false;
        document.querySelectorAll('input[name="nonvat_description[]"], input[name="vat_description[]"]').forEach(function (input) {
          if ((input.value || '').trim() !== '') hasLine = true;
        });
        if (!hasLine) {
          e.preventDefault();
          alert(<?= json_encode(__('flash.invoice.need_lines')) ?>);
        }
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
$subtotal = 0.0;
$vatAmount = 0.0;
$grand = 0.0;
foreach ($invoiceLines as $line) {
    $qty = (float) ($line['quantity'] ?? 1);
    $price = (float) ($line['unit_price'] ?? 0);
    $subtotal += round($qty * $price, 2);
    $vatAmount += (float) ($line['vat_amount'] ?? 0);
    $grand += (float) ($line['line_total'] ?? (($qty * $price) + (float) ($line['vat_amount'] ?? 0)));
}
$subtotal = round($subtotal, 2);
$vatAmount = round($vatAmount, 2);
$grand = round($grand > 0 ? $grand : ($subtotal + $vatAmount), 2);
// Keep stored header totals in sync with line items
if (
    abs($subtotal - (float) $invoice['amount']) > 0.009
    || abs($vatAmount - (float) $invoice['tax']) > 0.009
    || abs($grand - (float) $invoice['total']) > 0.009
) {
    $pdo->prepare('UPDATE invoices SET amount = ?, tax = ?, total = ? WHERE id = ?')
        ->execute([$subtotal, $vatAmount, $grand, $id]);
    $invoice['amount'] = $subtotal;
    $invoice['tax'] = $vatAmount;
    $invoice['total'] = $grand;
}
$amountDue = max(0, round($grand - $paid, 2));
$bankOptions = get_bank_accounts($pdo);
$configuredBanks = get_configured_bank_accounts($pdo);
$selectedBank = get_bank_account($pdo, isset($invoice['bank_account_id']) ? (int) $invoice['bank_account_id'] : null);
if (!$selectedBank && !empty($invoice['bank_account_id'])) {
    $selectedBank = $bankOptions[(int) $invoice['bank_account_id']] ?? null;
}

$firmName = get_setting($pdo, 'company_name', app_config('name', 'LEGAL PRO'));
$firmEmail = get_setting($pdo, 'company_email', '');
$firmPhone = get_setting($pdo, 'company_phone', '');
$firmAddress = get_setting($pdo, 'company_address', '');
$firmVat = get_setting($pdo, 'company_vat', get_setting($pdo, 'company_registration', ''));
$payInstructions = trim((string) ($invoice['payment_instructions'] ?? ''));
if ($payInstructions === '') {
    $payInstructions = get_setting($pdo, 'payment_instructions', '');
}
$paymentTerms = trim((string) ($invoice['payment_terms'] ?? ''));
$paymentLinkToken = trim((string) ($invoice['payment_link_token'] ?? ''));
$paymentLink = trim((string) ($invoice['payment_link'] ?? ''));
if ($paymentLink === '' && $paymentLinkToken !== '') {
    $paymentLink = invoice_payment_public_url($paymentLinkToken);
}
$payStatus = invoice_payment_status($invoice);
$showPayNow = invoice_has_pay_now($invoice, $amountDue);
$clientPayUrl = $showPayNow
    ? ($paymentLink !== '' ? $paymentLink : ($paymentLinkToken !== '' ? '../client/pay.php?token=' . rawurlencode($paymentLinkToken) : ''))
    : '';

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
    <a class="btn btn-secondary btn-sm inv-doc-back" href="<?= e($returnTo) ?>"><?= __e('common.back') ?></a>
    <div class="inv-doc-actions">
        <?php if ($bankOptions): ?>
        <form method="post" class="inv-bank-switch">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="set_bank_account">
            <input type="hidden" name="invoice_id" value="<?= (int) $invoice['id'] ?>">
            <label for="invBankSelect"><?= __e('finance.pay_to_bank') ?></label>
            <select name="bank_account_id" id="invBankSelect" onchange="this.form.submit()">
                <?php foreach ($bankOptions as $ba):
                    $isConfigured = isset($configuredBanks[(int) $ba['id']]);
                    $optLabel = $ba['label'] ?: ('Bank account ' . $ba['id']);
                    if (!$isConfigured) {
                        $optLabel .= ' (' . __('finance.bank_not_configured') . ')';
                    }
                    ?>
                    <option value="<?= (int) $ba['id'] ?>" <?= (int) ($invoice['bank_account_id'] ?? 0) === (int) $ba['id'] ? 'selected' : '' ?>><?= e($optLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <span class="inv-doc-actions-sep" aria-hidden="true"></span>
        <?php endif; ?>
        <div class="inv-doc-action-btns">
            <button type="button" class="btn btn-primary btn-sm inv-doc-print-btn" onclick="window.print()"><?= __e('finance.print_save_pdf') ?></button>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="email_client">
                <input type="hidden" name="invoice_id" value="<?= (int) $invoice['id'] ?>">
                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                <button class="btn btn-secondary btn-sm" type="submit"><?= __e('finance.email_client') ?></button>
            </form>
            <?php if ($showPayNow && $clientPayUrl): ?>
            <a class="btn btn-primary btn-sm inv-pay-now-btn" href="<?= e($clientPayUrl) ?>" target="_blank" rel="noopener"><?= __e('finance.pay_now') ?> | <?= e(money($amountDue)) ?></a>
            <?php elseif ($payStatus === 'paid' || $invoice['status'] === 'paid'): ?>
            <span class="inv-pay-status-inline"><?= payment_status_badge('paid') ?></span>
            <?php elseif ($payStatus === 'failed'): ?>
            <span class="inv-pay-status-inline"><?= payment_status_badge('failed') ?></span>
            <?php elseif ($payStatus === 'pending'): ?>
            <span class="inv-pay-status-inline"><?= payment_status_badge('pending') ?></span>
            <?php endif; ?>
            <?php if ($invoiceCaseId > 0): ?>
            <a class="btn btn-accent btn-sm" href="<?= e($paymentUrl) ?>"><?= __e('finance.record_payment') ?></a>
            <?php endif; ?>
        </div>
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
        <div class="inv-doc-summary-row"><span><?= __e('finance.net_inc_vat') ?></span><strong><?= e(money($subtotal + $vatAmount)) ?></strong></div>
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
            <?php if ($selectedBank && (($selectedBank['bank'] ?? '') !== '' || ($selectedBank['account_number'] ?? '') !== '' || ($selectedBank['iban'] ?? '') !== '')): ?>
                <div class="inv-doc-bank">
                    <div class="inv-doc-bank-label"><?= e($selectedBank['label']) ?></div>
                    <?php if ($selectedBank['bank']): ?><p><span><?= __e('settings.payments.bank_name') ?></span> <?= e($selectedBank['bank']) ?></p><?php endif; ?>
                    <?php if ($selectedBank['account_name']): ?><p><span><?= __e('settings.payments.account_name') ?></span> <?= e($selectedBank['account_name']) ?></p><?php endif; ?>
                    <?php if ($selectedBank['account_number']): ?><p><span><?= __e('settings.payments.account_number') ?></span> <?= e($selectedBank['account_number']) ?></p><?php endif; ?>
                    <?php if (!empty($selectedBank['sort_code'])): ?><p><span><?= __e('settings.payments.sort_code') ?></span> <?= e($selectedBank['sort_code']) ?></p><?php endif; ?>
                    <?php if ($selectedBank['iban']): ?><p><span><?= __e('settings.payments.iban') ?></span> <?= e($selectedBank['iban']) ?></p><?php endif; ?>
                    <?php if ($selectedBank['swift']): ?><p><span><?= __e('settings.payments.swift') ?></span> <?= e($selectedBank['swift']) ?></p><?php endif; ?>
                    <?php if (!empty($selectedBank['reference'])): ?><p><span><?= __e('settings.payments.reference') ?></span> <?= e($selectedBank['reference']) ?></p><?php endif; ?>
                </div>
            <?php else: ?>
                <p class="muted no-print"><?= __e('finance.no_bank_on_invoice') ?></p>
            <?php endif; ?>
            <?php if ($paymentTerms): ?>
                <p class="inv-doc-pay-note"><strong><?= __e('finance.payment_terms') ?>:</strong> <?= e($paymentTerms) ?></p>
            <?php endif; ?>
            <?php if ($payInstructions): ?>
                <p class="inv-doc-pay-note"><?= e($payInstructions) ?></p>
            <?php endif; ?>
            <div class="inv-doc-pay-cta no-print">
                <?php if ($showPayNow && $clientPayUrl): ?>
                    <a class="btn btn-primary inv-pay-now-btn" href="<?= e($clientPayUrl) ?>" target="_blank" rel="noopener"><?= __e('finance.pay_now') ?> | <?= e(money($amountDue)) ?></a>
                    <span class="muted"><?= __e('finance.pay_now_help') ?></span>
                <?php elseif ($payStatus === 'paid' || $invoice['status'] === 'paid'): ?>
                    <?= payment_status_badge('paid') ?>
                    <?php if (!empty($invoice['payment_date'])): ?>
                        <span class="muted"><?= e(format_datetime($invoice['payment_date'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($invoice['transaction_reference'])): ?>
                        <span class="muted"><?= __e('finance.transaction_ref') ?>: <?= e($invoice['transaction_reference']) ?></span>
                    <?php endif; ?>
                <?php elseif ($payStatus === 'failed'): ?>
                    <?= payment_status_badge('failed') ?>
                    <?php if ($paymentLinkToken !== ''): ?>
                        <a class="btn btn-secondary btn-sm" href="<?= e(invoice_payment_public_url($paymentLinkToken)) ?>" target="_blank" rel="noopener"><?= __e('finance.retry_payment') ?></a>
                    <?php endif; ?>
                <?php elseif ($payStatus === 'pending'): ?>
                    <?= payment_status_badge('pending') ?>
                <?php endif; ?>
            </div>
        </div>
        <p class="inv-doc-thanks"><?= __e('finance.thank_you') ?></p>
    </footer>
</article>
<?php if ($mailtoOnLoad && !empty($invoice['email'])): ?>
<script>window.location.href = <?= json_encode($mailtoHref) ?>;</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
