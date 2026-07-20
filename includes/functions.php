<?php
/**
 * Shared helper functions
 */

function app_config(?string $key = null, $default = null)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/app.php';
    }
    if ($key === null) {
        return $config;
    }
    return $config[$key] ?? $default;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function full_name(array $user): string
{
    return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
}

function currency_symbol(): string
{
    static $symbol = null;
    if ($symbol !== null) {
        return $symbol;
    }
    $code = 'MUR';
    try {
        $code = strtoupper((string) get_setting(db(), 'payment_currency', app_config('currency', 'MUR')));
    } catch (Throwable $e) {
        $code = strtoupper((string) app_config('currency', 'MUR'));
    }
    $symbols = [
        'MUR' => 'Rs ',
        'INR' => '₹',
        'AED' => 'AED ',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
    ];
    $symbol = $symbols[$code] ?? (app_config('currency_symbol', 'Rs '));
    return $symbol;
}

function money($amount): string
{
    static $symbol = null;
    static $locale = null;
    if ($symbol === null) {
        $symbol = currency_symbol();
        $code = 'MUR';
        try {
            $code = strtoupper((string) get_setting(db(), 'payment_currency', app_config('currency', 'MUR')));
        } catch (Throwable $e) {
            $code = strtoupper((string) app_config('currency', 'MUR'));
        }
        $locales = [
            'MUR' => 'en_MU',
            'INR' => 'en_IN',
            'AED' => 'en_AE',
            'USD' => 'en_US',
            'EUR' => 'fr_FR',
            'GBP' => 'en_GB',
        ];
        $locale = $locales[$code] ?? 'en_MU';
        if (function_exists('current_lang') && current_lang() === 'fr' && $code === 'MUR') {
            $locale = 'fr_MU';
        }
    }
    $value = (float) $amount;
    if (class_exists('NumberFormatter')) {
        $fmt = new NumberFormatter($locale, NumberFormatter::DECIMAL);
        $fmt->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $fmt->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);
        return $symbol . $fmt->format($value);
    }
    return $symbol . number_format($value, 2);
}

function format_date(?string $date, string $format = 'd M Y'): string
{
    $empty = function_exists('__') ? __('common.em_dash') : '—';
    if (!$date) {
        return $empty;
    }
    $ts = strtotime($date);
    if (!$ts) {
        return $empty;
    }
    if (function_exists('locale_tag') && class_exists('IntlDateFormatter') && ($format === 'd M Y' || $format === 'd M Y, H:i')) {
        $withTime = str_contains($format, 'H:i');
        $fmt = new IntlDateFormatter(
            locale_tag(),
            IntlDateFormatter::MEDIUM,
            $withTime ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE
        );
        $out = $fmt->format($ts);
        if ($out !== false) {
            return $out;
        }
    }
    return date($format, $ts);
}

function format_datetime(?string $date): string
{
    return format_date($date, 'd M Y, H:i');
}

function status_badge(string $status): string
{
    $map = [
        'open' => 'badge-info',
        'active' => 'badge-success',
        'pending' => 'badge-warning',
        'on_hold' => 'badge-muted',
        'closed' => 'badge-dark',
        'reopened' => 'badge-info',
        'accepted' => 'badge-success',
        'confirmed' => 'badge-success',
        'rejected' => 'badge-danger',
        'rescheduled' => 'badge-info',
        'cancelled' => 'badge-muted',
        'completed' => 'badge-dark',
        'scheduled' => 'badge-info',
        'adjourned' => 'badge-warning',
        'failed' => 'badge-danger',
        'draft' => 'badge-muted',
        'sent' => 'badge-info',
        'paid' => 'badge-success',
        'partial' => 'badge-warning',
        'overdue' => 'badge-danger',
        'available' => 'badge-success',
        'busy' => 'badge-warning',
        'unavailable' => 'badge-danger',
        'low' => 'badge-muted',
        'medium' => 'badge-info',
        'high' => 'badge-warning',
        'urgent' => 'badge-danger',
    ];
    $statusKey = normalize_appointment_status($status);
    $class = $map[$statusKey] ?? $map[$status] ?? 'badge-muted';
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(isset($map[$statusKey]) ? $statusKey : $status));
    $label = function_exists('translate_status') ? translate_status($status) : ucwords(str_replace('_', ' ', $status));
    return '<span class="badge ' . $class . ' badge-st-' . e($slug) . '">' . e($label) . '</span>';
}

function generate_case_number(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->query("SELECT COUNT(*) FROM cases WHERE YEAR(created_at) = YEAR(CURDATE())");
    $count = (int) $stmt->fetchColumn() + 1;
    return sprintf('CASE-%s-%03d', $year, $count);
}

function ensure_case_create_columns(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $cols = [
        'client_instructions' => 'ALTER TABLE cases ADD COLUMN client_instructions TEXT DEFAULT NULL AFTER description',
        'assigned_admin_id' => 'ALTER TABLE cases ADD COLUMN assigned_admin_id INT UNSIGNED DEFAULT NULL AFTER lawyer_id',
        'total_fee' => 'ALTER TABLE cases ADD COLUMN total_fee DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER priority',
    ];
    foreach ($cols as $name => $sql) {
        $exists = $pdo->query("SHOW COLUMNS FROM cases LIKE " . $pdo->quote($name))->fetch();
        if (!$exists) {
            $pdo->exec($sql);
        }
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS case_fee_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            case_id INT UNSIGNED NOT NULL,
            section ENUM("nonvat","vat") NOT NULL DEFAULT "nonvat",
            description VARCHAR(255) NOT NULL,
            net_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            vat_rate DECIMAL(8,2) NOT NULL DEFAULT 0,
            vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_case_fee_case (case_id),
            CONSTRAINT fk_case_fee_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $ready = true;
}

/** @return array<int, array<string,mixed>> */
function case_fee_items(PDO $pdo, int $caseId): array
{
    ensure_case_create_columns($pdo);
    $stmt = $pdo->prepare('SELECT * FROM case_fee_items WHERE case_id = ? ORDER BY section ASC, sort_order ASC, id ASC');
    $stmt->execute([$caseId]);
    return $stmt->fetchAll();
}

/**
 * Persist fee rows from create/edit case form.
 * @return float total fee
 */
function save_case_fee_items_from_post(PDO $pdo, int $caseId): float
{
    ensure_case_create_columns($pdo);
    $pdo->prepare('DELETE FROM case_fee_items WHERE case_id = ?')->execute([$caseId]);
    $nonvatRate = max(0, (float) post('nonvat_rate', 0));
    $vatRate = max(0, (float) post('vat_rate', 0));
    $total = 0.0;
    $ord = 0;
    $ins = $pdo->prepare(
        'INSERT INTO case_fee_items (case_id, section, description, net_amount, vat_rate, vat_amount, line_total, sort_order)
         VALUES (?,?,?,?,?,?,?,?)'
    );

    $saveSection = static function (string $section, array $descriptions, array $amounts, float $rate) use ($ins, $caseId, &$total, &$ord): void {
        foreach ($descriptions as $i => $desc) {
            $desc = trim((string) $desc);
            if ($desc === '') {
                continue;
            }
            $net = max(0, (float) ($amounts[$i] ?? 0));
            $vat = $rate > 0 ? round($net * ($rate / 100), 2) : 0.0;
            $line = round($net + $vat, 2);
            $ins->execute([$caseId, $section, $desc, $net, $rate, $vat, $line, $ord++]);
            $total += $line;
        }
    };

    $saveSection('nonvat', $_POST['nonvat_description'] ?? [], $_POST['nonvat_amount'] ?? [], $nonvatRate);
    $saveSection('vat', $_POST['vat_description'] ?? [], $_POST['vat_amount'] ?? [], $vatRate);
    $pdo->prepare('UPDATE cases SET total_fee = ? WHERE id = ?')->execute([round($total, 2), $caseId]);
    return round($total, 2);
}

/**
 * Random alphanumeric code guaranteed to contain at least one letter and one digit.
 * Uses an unambiguous alphabet (no O/0/I/1) for easy reading.
 */
function generate_alnum_code(int $length = 6): string
{
    $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $digits = '23456789';
    $alphabet = $letters . $digits;
    $max = strlen($alphabet) - 1;
    if ($length < 2) {
        $length = 2;
    }
    do {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }
    } while (!preg_match('/[A-Z]/', $code) || !preg_match('/[0-9]/', $code));
    return $code;
}

function generate_invoice_number(PDO $pdo): string
{
    $year = date('Y');
    for ($attempt = 0; $attempt < 24; $attempt++) {
        $number = sprintf('INV-%s-%s', $year, generate_alnum_code(6));
        $check = $pdo->prepare('SELECT 1 FROM invoices WHERE invoice_number = ? LIMIT 1');
        $check->execute([$number]);
        if (!$check->fetchColumn()) {
            return $number;
        }
    }
    return sprintf('INV-%s-%s', $year, generate_alnum_code(8));
}

function ensure_invoice_items_table(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS invoice_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT UNSIGNED NOT NULL,
            description VARCHAR(255) NOT NULL,
            quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
            unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_invoice_items_invoice (invoice_id),
            CONSTRAINT fk_invoice_items_invoice
                FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $ready = true;
}

function invoice_paid_total(PDO $pdo, int $invoiceId): float
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = ?');
    $stmt->execute([$invoiceId]);
    return (float) $stmt->fetchColumn();
}

function invoice_line_items(PDO $pdo, int $invoiceId): array
{
    ensure_invoice_items_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$invoiceId]);
    return $stmt->fetchAll();
}

/**
 * Build receipt/invoice display totals from line items; optionally sync invoice header.
 * @return array{lines: array, subtotal: float, vat: float, grand: float}
 */
function invoice_display_totals(PDO $pdo, int $invoiceId, ?array $invoice = null, bool $syncHeader = true): array
{
    ensure_invoice_items_table($pdo);
    $lines = array_values(array_filter(
        invoice_line_items($pdo, $invoiceId),
        static fn($r) => is_array($r) && trim((string) ($r['description'] ?? '')) !== ''
    ));

    if (!$invoice) {
        $stmt = $pdo->prepare('SELECT amount, tax, total, title, description FROM invoices WHERE id = ?');
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch() ?: [];
    }

    if (!$lines) {
        $desc = trim((string) ($invoice['title'] ?? ''));
        if (!empty($invoice['description'])) {
            $desc = $desc !== ''
                ? ($desc . ' — ' . trim((string) $invoice['description']))
                : trim((string) $invoice['description']);
        }
        if ($desc === '') {
            $desc = 'Professional services';
        }
        $lines = [[
            'description' => $desc,
            'quantity' => 1,
            'unit_price' => (float) ($invoice['amount'] ?? 0),
            'vat_amount' => (float) ($invoice['tax'] ?? 0),
            'line_total' => (float) ($invoice['total'] ?? 0),
        ]];
    }

    $subtotal = 0.0;
    $vat = 0.0;
    $grand = 0.0;
    foreach ($lines as $line) {
        $qty = (float) ($line['quantity'] ?? 1);
        $price = (float) ($line['unit_price'] ?? 0);
        $subtotal += round($qty * $price, 2);
        $vat += (float) ($line['vat_amount'] ?? 0);
        $grand += (float) ($line['line_total'] ?? (($qty * $price) + (float) ($line['vat_amount'] ?? 0)));
    }
    $subtotal = round($subtotal, 2);
    $vat = round($vat, 2);
    $grand = round($grand > 0 ? $grand : ($subtotal + $vat), 2);

    if (
        $syncHeader
        && $invoiceId > 0
        && (
            abs($subtotal - (float) ($invoice['amount'] ?? 0)) > 0.009
            || abs($vat - (float) ($invoice['tax'] ?? 0)) > 0.009
            || abs($grand - (float) ($invoice['total'] ?? 0)) > 0.009
        )
    ) {
        $pdo->prepare('UPDATE invoices SET amount = ?, tax = ?, total = ? WHERE id = ?')
            ->execute([$subtotal, $vat, $grand, $invoiceId]);
    }

    return [
        'lines' => $lines,
        'subtotal' => $subtotal,
        'vat' => $vat,
        'grand' => $grand,
    ];
}

function ensure_invoice_bank_column(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $col = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'bank_account_id'")->fetch();
    if (!$col) {
        $pdo->exec('ALTER TABLE invoices ADD COLUMN bank_account_id TINYINT UNSIGNED DEFAULT NULL AFTER created_by');
    }
    $cols = [
        'payment_terms' => 'ALTER TABLE invoices ADD COLUMN payment_terms VARCHAR(255) DEFAULT NULL AFTER bank_account_id',
        'payment_instructions' => 'ALTER TABLE invoices ADD COLUMN payment_instructions TEXT DEFAULT NULL AFTER payment_terms',
        'payment_link_token' => 'ALTER TABLE invoices ADD COLUMN payment_link_token VARCHAR(64) DEFAULT NULL AFTER payment_instructions',
        'payment_link' => 'ALTER TABLE invoices ADD COLUMN payment_link VARCHAR(500) DEFAULT NULL AFTER payment_link_token',
        'payment_status' => "ALTER TABLE invoices ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'none' AFTER payment_link",
        'payment_date' => 'ALTER TABLE invoices ADD COLUMN payment_date DATETIME DEFAULT NULL AFTER payment_status',
        'transaction_reference' => 'ALTER TABLE invoices ADD COLUMN transaction_reference VARCHAR(100) DEFAULT NULL AFTER payment_date',
    ];
    foreach ($cols as $name => $sql) {
        $exists = $pdo->query("SHOW COLUMNS FROM invoices LIKE " . $pdo->quote($name))->fetch();
        if (!$exists) {
            $pdo->exec($sql);
        }
    }
    $ready = true;
}

/** Active payment gateway driver. Swap to stripe/paypal later via settings. */
function payment_gateway_driver(): string
{
    try {
        $driver = strtolower(trim((string) get_setting(db(), 'payment_gateway_driver', 'prototype')));
    } catch (Throwable $e) {
        $driver = 'prototype';
    }
    return $driver !== '' ? $driver : 'prototype';
}

function invoice_payment_public_url(string $token): string
{
    $base = rtrim((string) app_config('url'), '/');
    return $base . '/client/pay.php?token=' . rawurlencode($token);
}

/**
 * Create a checkout/payment link for an invoice.
 * Prototype stores a token URL; real gateways can replace this body later.
 *
 * @return array{token:string,link:string,status:string}
 */
function create_invoice_payment_checkout(PDO $pdo, int $invoiceId): array
{
    ensure_invoice_bank_column($pdo);
    $token = bin2hex(random_bytes(16));
    $link = invoice_payment_public_url($token);
    // Future: if payment_gateway_driver() === 'stripe', create a Checkout Session and use that URL.
    $pdo->prepare(
        'UPDATE invoices
         SET payment_link_token = ?, payment_link = ?, payment_status = ?, payment_date = NULL, transaction_reference = NULL
         WHERE id = ?'
    )->execute([$token, $link, 'pending', $invoiceId]);
    return ['token' => $token, 'link' => $link, 'status' => 'pending'];
}

function invoice_payment_status(array $invoice): string
{
    $status = strtolower(trim((string) ($invoice['payment_status'] ?? 'none')));
    return $status !== '' ? $status : 'none';
}

function invoice_has_pay_now(array $invoice, float $amountDue = -1): bool
{
    $token = trim((string) ($invoice['payment_link_token'] ?? ''));
    $link = trim((string) ($invoice['payment_link'] ?? ''));
    if ($token === '' && $link === '') {
        return false;
    }
    $payStatus = invoice_payment_status($invoice);
    if ($payStatus === 'paid') {
        return false;
    }
    if (in_array((string) ($invoice['status'] ?? ''), ['paid', 'cancelled', 'draft'], true)) {
        return false;
    }
    if ($amountDue >= 0 && $amountDue <= 0) {
        return false;
    }
    return true;
}

function payment_status_badge(string $status): string
{
    $status = strtolower(trim($status));
    if ($status === '' || $status === 'none') {
        return '<span class="badge badge-muted badge-st-none">' . e(__('finance.payment_status.none')) . '</span>';
    }
    $map = [
        'pending' => 'badge-warning',
        'paid' => 'badge-success',
        'failed' => 'badge-danger',
    ];
    $class = $map[$status] ?? 'badge-muted';
    $label = __('finance.payment_status.' . $status);
    if ($label === 'finance.payment_status.' . $status) {
        $label = ucfirst($status);
    }
    return '<span class="badge ' . $class . ' badge-st-' . e($status) . '">' . e($label) . '</span>';
}

/**
 * Apply a prototype (or future gateway webhook) payment result.
 * @param 'paid'|'failed' $result
 */
function apply_invoice_payment_result(PDO $pdo, int $invoiceId, string $result, ?string $transactionRef = null, ?float $amount = null): void
{
    ensure_invoice_bank_column($pdo);
    $result = $result === 'paid' ? 'paid' : 'failed';
    $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ? LIMIT 1');
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();
    if (!$invoice) {
        return;
    }

    if ($result === 'failed') {
        $pdo->prepare(
            'UPDATE invoices SET payment_status = ?, transaction_reference = COALESCE(?, transaction_reference) WHERE id = ?'
        )->execute(['failed', $transactionRef, $invoiceId]);
        return;
    }

    $paidSoFar = invoice_paid_total($pdo, $invoiceId);
    $due = max(0, round((float) $invoice['total'] - $paidSoFar, 2));
    $payAmount = $amount !== null ? max(0, round($amount, 2)) : $due;
    $ref = $transactionRef ?: ('DEMO-' . strtoupper(substr((string) ($invoice['payment_link_token'] ?? generate_alnum_code(8)), 0, 10)));

    if ($payAmount > 0) {
        $receipt = generate_receipt_number($pdo);
        $pdo->prepare(
            'INSERT INTO payments (invoice_id, client_id, amount, payment_method, reference_number, receipt_number, notes, paid_at, recorded_by)
             VALUES (?,?,?,?,?,?,?,NOW(),?)'
        )->execute([
            $invoiceId,
            (int) $invoice['client_id'],
            $payAmount,
            'online',
            $ref,
            $receipt,
            'Prototype payment gateway (' . payment_gateway_driver() . ')',
            is_logged_in() ? (int) current_user()['id'] : null,
        ]);
        $paymentId = (int) $pdo->lastInsertId();
        notify_payment_events(
            $pdo,
            $invoice,
            $payAmount,
            $paymentId,
            $receipt,
            is_logged_in() ? (int) current_user()['id'] : null
        );
    }

    $pdo->prepare(
        'UPDATE invoices SET payment_status = ?, payment_date = NOW(), transaction_reference = ? WHERE id = ?'
    )->execute(['paid', $ref, $invoiceId]);
    sync_invoice_payment_status($pdo, $invoiceId);
}

/**
 * Notify client + admin/staff (+ case lawyer) after a payment/receipt is created.
 */
function notify_payment_events(
    PDO $pdo,
    array $invoice,
    float $amount,
    int $paymentId,
    string $receiptNumber,
    ?int $createdBy = null
): void {
    $clientId = (int) ($invoice['client_id'] ?? 0);
    $caseId = (int) ($invoice['case_id'] ?? 0);
    $invoiceNumber = (string) ($invoice['invoice_number'] ?? '—');

    $fromName = '';
    if ($clientId > 0) {
        $cStmt = $pdo->prepare('SELECT first_name, last_name, company_name FROM users WHERE id = ?');
        $cStmt->execute([$clientId]);
        $client = $cStmt->fetch() ?: [];
        $fromName = trim((string) ($client['company_name'] ?? ''));
        if ($fromName === '') {
            $fromName = trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? ''));
        }
        if ($fromName === '') {
            $fromName = __('common.client');
        }
        create_notification(
            $pdo,
            $clientId,
            'notify.receipt_issued',
            notify_payload('notify.msg.receipt_issued', [
                'number' => $receiptNumber,
                'amount' => money($amount),
                'invoice' => $invoiceNumber,
            ]),
            'payment',
            '../client/receipt.php?id=' . $paymentId,
            $createdBy
        );
    }

    $adminLink = $caseId > 0
        ? 'cases.php?action=view&id=' . $caseId . '&tab=receipts'
        : 'receipt.php?id=' . $paymentId;
    $staffIds = $pdo->query(
        "SELECT id FROM users WHERE role IN ('admin','staff') AND is_active=1"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($staffIds as $staffId) {
        $staffId = (int) $staffId;
        create_notification(
            $pdo,
            $staffId,
            'notify.payment_received',
            notify_payload('notify.msg.payment_received', [
                'amount' => money($amount),
                'from' => $fromName !== '' ? $fromName : __('common.client'),
            ]),
            'payment',
            $adminLink,
            $createdBy
        );
    }

    if ($caseId > 0) {
        $lawStmt = $pdo->prepare('SELECT lawyer_id FROM cases WHERE id = ?');
        $lawStmt->execute([$caseId]);
        $lawyerId = (int) ($lawStmt->fetchColumn() ?: 0);
        if ($lawyerId > 0) {
            create_notification(
                $pdo,
                $lawyerId,
                'notify.payment_received',
                notify_payload('notify.msg.payment_received', [
                    'amount' => money($amount),
                    'from' => $fromName !== '' ? $fromName : __('common.client'),
                ]),
                'payment',
                '../lawyer/cases.php?id=' . $caseId,
                $createdBy
            );
        }
    }
}

/** @return array<int, array{id:int,label:string,bank:string,account_name:string,account_number:string,iban:string,swift:string}> */
function get_bank_accounts(PDO $pdo): array
{
    $raw = (string) get_setting($pdo, 'bank_accounts_json', '');
    $decoded = $raw !== '' ? json_decode($raw, true) : null;
    $accounts = [];
    for ($i = 1; $i <= 3; $i++) {
        $row = is_array($decoded) ? ($decoded[(string) $i] ?? $decoded[$i - 1] ?? $decoded[$i] ?? []) : [];
        if (!is_array($row)) {
            $row = [];
        }
        $accounts[$i] = [
            'id' => $i,
            'label' => trim((string) ($row['label'] ?? ('Bank account ' . $i))),
            'bank' => trim((string) ($row['bank'] ?? '')),
            'account_name' => trim((string) ($row['account_name'] ?? '')),
            'account_number' => trim((string) ($row['account_number'] ?? '')),
            'sort_code' => trim((string) ($row['sort_code'] ?? '')),
            'iban' => trim((string) ($row['iban'] ?? '')),
            'swift' => trim((string) ($row['swift'] ?? '')),
            'reference' => trim((string) ($row['reference'] ?? '')),
        ];
    }
    return $accounts;
}

/** Returns the configured default bank account id (1-3), falling back to the first configured account. */
function get_default_bank_account_id(PDO $pdo): ?int
{
    $configured = get_configured_bank_accounts($pdo);
    if (!$configured) {
        return null;
    }
    $default = (int) get_setting($pdo, 'bank_accounts_default', '0');
    if ($default >= 1 && $default <= 3 && isset($configured[$default])) {
        return $default;
    }
    return (int) array_key_first($configured);
}

function get_configured_bank_accounts(PDO $pdo): array
{
    return array_filter(get_bank_accounts($pdo), static function (array $a): bool {
        return $a['bank'] !== '' || $a['account_number'] !== '' || $a['iban'] !== '';
    });
}

function get_bank_account(PDO $pdo, ?int $id): ?array
{
    if ($id === null || $id < 1 || $id > 3) {
        return null;
    }
    $all = get_bank_accounts($pdo);
    $account = $all[$id] ?? null;
    if (!$account) {
        return null;
    }
    if ($account['bank'] === '' && $account['account_number'] === '' && $account['iban'] === '') {
        return null;
    }
    return $account;
}

function save_bank_accounts(PDO $pdo, array $posted): void
{
    $out = [];
    for ($i = 1; $i <= 3; $i++) {
        $out[(string) $i] = [
            'label' => trim((string) ($posted[$i]['label'] ?? ('Bank account ' . $i))),
            'bank' => trim((string) ($posted[$i]['bank'] ?? '')),
            'account_name' => trim((string) ($posted[$i]['account_name'] ?? '')),
            'account_number' => trim((string) ($posted[$i]['account_number'] ?? '')),
            'sort_code' => trim((string) ($posted[$i]['sort_code'] ?? '')),
            'iban' => trim((string) ($posted[$i]['iban'] ?? '')),
            'swift' => trim((string) ($posted[$i]['swift'] ?? '')),
            'reference' => trim((string) ($posted[$i]['reference'] ?? '')),
        ];
    }
    set_setting($pdo, 'bank_accounts_json', json_encode($out, JSON_UNESCAPED_UNICODE));
}

function generate_receipt_number(PDO $pdo): string
{
    $year = date('Y');
    for ($attempt = 0; $attempt < 24; $attempt++) {
        $number = sprintf('RCP-%s-%s', $year, generate_alnum_code(6));
        $check = $pdo->prepare('SELECT 1 FROM payments WHERE receipt_number = ? LIMIT 1');
        $check->execute([$number]);
        if (!$check->fetchColumn()) {
            return $number;
        }
    }
    return sprintf('RCP-%s-%s', $year, generate_alnum_code(8));
}

function sync_invoice_payment_status(PDO $pdo, int $invoiceId): void
{
    if ($invoiceId < 1) {
        return;
    }
    ensure_invoice_bank_column($pdo);
    $inv = $pdo->prepare('SELECT total, status, payment_status FROM invoices WHERE id = ?');
    $inv->execute([$invoiceId]);
    $row = $inv->fetch();
    if (!$row) {
        return;
    }
    if (in_array($row['status'], ['draft', 'cancelled'], true)) {
        return;
    }
    $paid = invoice_paid_total($pdo, $invoiceId);
    $total = (float) $row['total'];
    $status = $paid >= $total && $total > 0 ? 'paid' : ($paid > 0 ? 'partial' : 'sent');
    $payStatus = (string) ($row['payment_status'] ?? 'none');
    if ($status === 'paid' && $payStatus !== 'paid') {
        $pdo->prepare(
            'UPDATE invoices SET status = ?, payment_status = ?, payment_date = COALESCE(payment_date, NOW()) WHERE id = ?'
        )->execute([$status, 'paid', $invoiceId]);
        return;
    }
    $pdo->prepare('UPDATE invoices SET status = ? WHERE id = ?')->execute([$status, $invoiceId]);
}

function log_activity(PDO $pdo, ?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null): void
{
    $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $userId,
        $action,
        $entityType,
        $entityId,
        $description,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function ensure_notification_edited_column(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $col = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'edited_at'")->fetch();
    if (!$col) {
        $pdo->exec('ALTER TABLE notifications ADD COLUMN edited_at DATETIME DEFAULT NULL AFTER created_at');
    }
    $ready = true;
}

function ensure_contact_message_columns(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $threadCol = $pdo->query("SHOW COLUMNS FROM messages LIKE 'thread_id'")->fetch();
    if (!$threadCol) {
        $pdo->exec('ALTER TABLE messages ADD COLUMN thread_id INT UNSIGNED DEFAULT NULL AFTER created_at');
    }
    $statusCol = $pdo->query("SHOW COLUMNS FROM messages LIKE 'status'")->fetch();
    if (!$statusCol) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN status ENUM('open','closed') NOT NULL DEFAULT 'open' AFTER thread_id");
    }
    $editedCol = $pdo->query("SHOW COLUMNS FROM messages LIKE 'edited_at'")->fetch();
    if (!$editedCol) {
        $pdo->exec('ALTER TABLE messages ADD COLUMN edited_at DATETIME DEFAULT NULL AFTER status');
    }
    $pdo->exec('UPDATE messages SET thread_id = id WHERE thread_id IS NULL');
    $ready = true;
}

function contact_user_in_thread(array $thread, int $userId): bool
{
    return in_array($userId, [(int) ($thread['sender_id'] ?? 0), (int) ($thread['receiver_id'] ?? 0)], true);
}

function contact_lawyer_can_access_thread(PDO $pdo, array $thread, int $lawyerId): bool
{
    $clientId = (int) $thread['sender_id'] === $lawyerId ? (int) $thread['receiver_id'] : (int) $thread['sender_id'];
    return lawyer_can_access_client($pdo, $lawyerId, $clientId);
}

function contact_edit_message(PDO $pdo, int $messageId, int $userId, string $body): bool
{
    ensure_contact_message_columns($pdo);
    $body = trim($body);
    if ($body === '') {
        return false;
    }
    $stmt = $pdo->prepare('UPDATE messages SET body=?, edited_at=NOW() WHERE id=? AND sender_id=?');
    $stmt->execute([$body, $messageId, $userId]);
    return $stmt->rowCount() > 0;
}

function resolve_client_lawyer_id(PDO $pdo, int $clientId): ?int
{
    $lawyers = contact_fetch_client_lawyers($pdo, $clientId);
    if (count($lawyers) === 1) {
        return (int) $lawyers[0]['id'];
    }
    $stmt = $pdo->prepare('SELECT assigned_lawyer_id FROM users WHERE id=? AND role="client"');
    $stmt->execute([$clientId]);
    $lawyerId = $stmt->fetchColumn();
    if ($lawyerId) {
        return (int) $lawyerId;
    }
    if ($lawyers) {
        return (int) $lawyers[0]['id'];
    }
    return null;
}

/** @return list<array{id:int,first_name:string,last_name:string,email:?string,phone:?string,specialization:?string,cases:list<array{id:int,case_number:string,title:?string,status:string}>}> */
/**
 * Lawyers a client is currently allowed to contact.
 * A lawyer is contactable only while the client has at least one open (non-closed)
 * case with them. Once every shared case is closed, the lawyer drops off the list
 * until a new case is opened. Brand-new clients with no cases at all fall back to
 * their assigned lawyer so onboarding contact still works.
 */
function contact_fetch_client_lawyers(PDO $pdo, int $clientId): array
{
    $byId = [];
    $stmt = $pdo->prepare(
        "SELECT c.id AS case_id, c.case_number, c.title, c.status, c.lawyer_id,
                u.first_name, u.last_name, u.email, u.phone, u.specialization
         FROM cases c
         JOIN users u ON u.id = c.lawyer_id AND u.role = 'lawyer'
         WHERE c.client_id = ? AND c.lawyer_id IS NOT NULL AND c.status <> 'closed'
         ORDER BY c.case_number"
    );
    $stmt->execute([$clientId]);
    foreach ($stmt->fetchAll() as $row) {
        $lawyerId = (int) $row['lawyer_id'];
        if (!isset($byId[$lawyerId])) {
            $byId[$lawyerId] = [
                'id' => $lawyerId,
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'specialization' => $row['specialization'],
                'cases' => [],
            ];
        }
        $byId[$lawyerId]['cases'][] = [
            'id' => (int) $row['case_id'],
            'case_number' => $row['case_number'],
            'title' => $row['title'],
            'status' => $row['status'],
        ];
    }

    // Fallback to the assigned lawyer only for clients who have no cases at all yet.
    if (!$byId) {
        $caseCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cases WHERE client_id = ?');
        $caseCountStmt->execute([$clientId]);
        $hasAnyCase = (int) $caseCountStmt->fetchColumn() > 0;
        if (!$hasAnyCase) {
            $assignedStmt = $pdo->prepare('SELECT assigned_lawyer_id FROM users WHERE id=? AND role="client"');
            $assignedStmt->execute([$clientId]);
            $assignedId = (int) ($assignedStmt->fetchColumn() ?: 0);
            if ($assignedId) {
                $lawyerStmt = $pdo->prepare('SELECT id, first_name, last_name, email, phone, specialization FROM users WHERE id=? AND role="lawyer"');
                $lawyerStmt->execute([$assignedId]);
                if ($lawyer = $lawyerStmt->fetch()) {
                    $byId[$assignedId] = [
                        'id' => (int) $lawyer['id'],
                        'first_name' => $lawyer['first_name'],
                        'last_name' => $lawyer['last_name'],
                        'email' => $lawyer['email'],
                        'phone' => $lawyer['phone'],
                        'specialization' => $lawyer['specialization'],
                        'cases' => [],
                    ];
                }
            }
        }
    }

    return array_values($byId);
}

function contact_resolve_send_lawyer(PDO $pdo, int $clientId, ?int $caseId, ?int $lawyerId = null): ?int
{
    $lawyers = contact_fetch_client_lawyers($pdo, $clientId);
    if (!$lawyers) {
        return null;
    }
    if ($caseId) {
        $stmt = $pdo->prepare("SELECT lawyer_id FROM cases WHERE id=? AND client_id=? AND lawyer_id IS NOT NULL AND status <> 'closed'");
        $stmt->execute([$caseId, $clientId]);
        $resolved = $stmt->fetchColumn();
        return $resolved ? (int) $resolved : null;
    }
    if ($lawyerId && client_can_contact_lawyer($pdo, $clientId, $lawyerId)) {
        return $lawyerId;
    }
    if (count($lawyers) === 1) {
        return (int) $lawyers[0]['id'];
    }
    return null;
}

/** @return list<array{key:string,label:string,case_id:?int,lawyer_id:int}> */
function contact_fetch_send_targets(PDO $pdo, int $clientId): array
{
    $targets = [];
    foreach (contact_fetch_client_lawyers($pdo, $clientId) as $lawyer) {
        $lawyerName = full_name($lawyer);
        if (!empty($lawyer['cases'])) {
            foreach ($lawyer['cases'] as $case) {
                $targets[] = [
                    'key' => 'case-' . $case['id'],
                    'label' => __('client.contact.case_option', [
                        'case' => $case['case_number'],
                        'lawyer' => $lawyerName,
                    ]),
                    'case_id' => (int) $case['id'],
                    'lawyer_id' => (int) $lawyer['id'],
                ];
            }
        } else {
            $targets[] = [
                'key' => 'lawyer-' . $lawyer['id'],
                'label' => __('client.contact.general_option', ['lawyer' => $lawyerName]),
                'case_id' => null,
                'lawyer_id' => (int) $lawyer['id'],
            ];
        }
    }
    return $targets;
}

function client_can_contact_lawyer(PDO $pdo, int $clientId, int $lawyerId): bool
{
    foreach (contact_fetch_client_lawyers($pdo, $clientId) as $lawyer) {
        if ((int) $lawyer['id'] === $lawyerId) {
            return true;
        }
    }
    return false;
}

function lawyer_can_access_client(PDO $pdo, int $lawyerId, int $clientId): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM users u WHERE u.id=? AND u.role='client' AND (u.assigned_lawyer_id=? OR u.id IN (SELECT client_id FROM cases WHERE lawyer_id=?)) LIMIT 1");
    $stmt->execute([$clientId, $lawyerId, $lawyerId]);
    return (bool) $stmt->fetchColumn();
}

function contact_create_thread(PDO $pdo, int $clientId, int $lawyerId, ?int $caseId, string $subject, string $body): int
{
    ensure_contact_message_columns($pdo);
    $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, case_id, subject, body, status) VALUES (?,?,?,?,?,?)')
        ->execute([$clientId, $lawyerId, $caseId, $subject, $body, 'open']);
    $threadId = (int) $pdo->lastInsertId();
    $pdo->prepare('UPDATE messages SET thread_id=? WHERE id=?')->execute([$threadId, $threadId]);
    return $threadId;
}

function contact_add_reply(PDO $pdo, int $threadId, int $senderId, int $receiverId, string $body): void
{
    ensure_contact_message_columns($pdo);
    $root = $pdo->prepare('SELECT subject FROM messages WHERE id=? AND thread_id=?');
    $root->execute([$threadId, $threadId]);
    $subject = $root->fetchColumn() ?: __('common.message');
    $replySubject = str_starts_with(strtolower($subject), 're:') ? $subject : 'Re: ' . $subject;
    $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, case_id, subject, body, thread_id, status) VALUES (?,?,?,?,?,?,?)')
        ->execute([$senderId, $receiverId, null, $replySubject, $body, $threadId, 'open']);
    $pdo->prepare("UPDATE messages SET status='open' WHERE id=?")->execute([$threadId]);
}

function contact_fetch_thread(PDO $pdo, int $threadId): ?array
{
    ensure_contact_message_columns($pdo);
    $stmt = $pdo->prepare('SELECT * FROM messages WHERE id=? AND thread_id=?');
    $stmt->execute([$threadId, $threadId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function contact_fetch_thread_messages(PDO $pdo, int $threadId): array
{
    ensure_contact_message_columns($pdo);
    $stmt = $pdo->prepare('SELECT m.*, CONCAT(u.first_name," ",u.last_name) AS sender_name, u.role AS sender_role FROM messages m JOIN users u ON u.id=m.sender_id WHERE m.thread_id=? OR m.id=? ORDER BY m.created_at ASC');
    $stmt->execute([$threadId, $threadId]);
    return $stmt->fetchAll();
}

function contact_mark_thread_read(PDO $pdo, int $threadId, int $userId): void
{
    $pdo->prepare('UPDATE messages SET is_read=1 WHERE thread_id=? AND receiver_id=?')->execute([$threadId, $userId]);
}

function contact_count_threads_for_client(PDO $pdo, int $clientId, int $lawyerId): int
{
    ensure_contact_message_columns($pdo);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE id=thread_id AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))');
    $stmt->execute([$clientId, $lawyerId, $lawyerId, $clientId]);
    return (int) $stmt->fetchColumn();
}

function contact_count_all_threads_for_client(PDO $pdo, int $clientId): int
{
    ensure_contact_message_columns($pdo);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE id=thread_id AND (sender_id=? OR receiver_id=?)');
    $stmt->execute([$clientId, $clientId]);
    return (int) $stmt->fetchColumn();
}

function contact_fetch_threads_for_client(PDO $pdo, int $clientId, int $lawyerId, int $limit, int $offset): array
{
    ensure_contact_message_columns($pdo);
    $sql = 'SELECT root.id AS thread_id, root.subject, root.status, root.created_at,
        latest.body AS preview_body, latest.created_at AS last_at, latest.sender_id AS last_sender_id,
        CONCAT(lu.first_name," ",lu.last_name) AS last_sender_name,
        (SELECT COUNT(*) FROM messages WHERE thread_id = root.id) AS message_count
        FROM messages root
        JOIN messages latest ON latest.id = (
            SELECT id FROM messages WHERE thread_id = root.id ORDER BY created_at DESC LIMIT 1
        )
        JOIN users lu ON lu.id = latest.sender_id
        WHERE root.id = root.thread_id
          AND ((root.sender_id = ? AND root.receiver_id = ?) OR (root.sender_id = ? AND root.receiver_id = ?))
        ORDER BY latest.created_at DESC
        LIMIT ? OFFSET ?';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $clientId, PDO::PARAM_INT);
    $stmt->bindValue(2, $lawyerId, PDO::PARAM_INT);
    $stmt->bindValue(3, $lawyerId, PDO::PARAM_INT);
    $stmt->bindValue(4, $clientId, PDO::PARAM_INT);
    $stmt->bindValue(5, $limit, PDO::PARAM_INT);
    $stmt->bindValue(6, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function contact_fetch_all_threads_for_client(PDO $pdo, int $clientId, int $limit, int $offset): array
{
    ensure_contact_message_columns($pdo);
    $sql = 'SELECT root.id AS thread_id, root.subject, root.status, root.created_at, root.case_id,
        latest.body AS preview_body, latest.created_at AS last_at, latest.sender_id AS last_sender_id,
        CONCAT(lu.first_name," ",lu.last_name) AS last_sender_name,
        IF(root.sender_id = ?, root.receiver_id, root.sender_id) AS lawyer_id,
        CONCAT(law.first_name," ",law.last_name) AS lawyer_name,
        (SELECT COUNT(*) FROM messages WHERE thread_id = root.id) AS message_count
        FROM messages root
        JOIN messages latest ON latest.id = (
            SELECT id FROM messages WHERE thread_id = root.id ORDER BY created_at DESC LIMIT 1
        )
        JOIN users lu ON lu.id = latest.sender_id
        JOIN users law ON law.id = IF(root.sender_id = ?, root.receiver_id, root.sender_id)
        WHERE root.id = root.thread_id
          AND (root.sender_id = ? OR root.receiver_id = ?)
        ORDER BY latest.created_at DESC
        LIMIT ? OFFSET ?';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $clientId, PDO::PARAM_INT);
    $stmt->bindValue(2, $clientId, PDO::PARAM_INT);
    $stmt->bindValue(3, $clientId, PDO::PARAM_INT);
    $stmt->bindValue(4, $clientId, PDO::PARAM_INT);
    $stmt->bindValue(5, $limit, PDO::PARAM_INT);
    $stmt->bindValue(6, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function contact_count_threads_for_lawyer(PDO $pdo, int $lawyerId, ?int $clientId = null): int
{
    ensure_contact_message_columns($pdo);
    $sql = "SELECT COUNT(*) FROM messages root
        WHERE root.id = root.thread_id
          AND (root.sender_id = ? OR root.receiver_id = ?)
          AND EXISTS (
            SELECT 1 FROM users u WHERE u.role='client'
              AND u.id = IF(root.sender_id = ?, root.receiver_id, root.sender_id)
              AND (u.assigned_lawyer_id = ? OR u.id IN (SELECT client_id FROM cases WHERE lawyer_id = ?))
          )";
    $params = [$lawyerId, $lawyerId, $lawyerId, $lawyerId, $lawyerId];
    if ($clientId) {
        $sql .= ' AND (root.sender_id = ? OR root.receiver_id = ?)';
        $params[] = $clientId;
        $params[] = $clientId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function contact_fetch_threads_for_lawyer(PDO $pdo, int $lawyerId, int $limit, int $offset, ?int $clientId = null): array
{
    ensure_contact_message_columns($pdo);
    $sql = "SELECT root.id AS thread_id, root.subject, root.status, root.created_at,
        latest.body AS preview_body, latest.created_at AS last_at, latest.sender_id AS last_sender_id,
        CONCAT(lu.first_name,' ',lu.last_name) AS last_sender_name,
        IF(root.sender_id = ?, root.receiver_id, root.sender_id) AS client_id,
        CONCAT(cu.first_name,' ',cu.last_name) AS client_name,
        (SELECT COUNT(*) FROM messages WHERE thread_id = root.id) AS message_count,
        (SELECT COUNT(*) FROM messages WHERE thread_id = root.id AND receiver_id = ? AND is_read = 0) AS unread_count
        FROM messages root
        JOIN messages latest ON latest.id = (
            SELECT id FROM messages WHERE thread_id = root.id ORDER BY created_at DESC LIMIT 1
        )
        JOIN users lu ON lu.id = latest.sender_id
        JOIN users cu ON cu.id = IF(root.sender_id = ?, root.receiver_id, root.sender_id)
        WHERE root.id = root.thread_id
          AND (root.sender_id = ? OR root.receiver_id = ?)
          AND (cu.assigned_lawyer_id = ? OR cu.id IN (SELECT client_id FROM cases WHERE lawyer_id = ?))";
    $params = [$lawyerId, $lawyerId, $lawyerId, $lawyerId, $lawyerId, $lawyerId, $lawyerId];
    if ($clientId) {
        $sql .= ' AND (root.sender_id = ? OR root.receiver_id = ?)';
        $params[] = $clientId;
        $params[] = $clientId;
    }
    $sql .= ' ORDER BY latest.created_at DESC LIMIT ? OFFSET ?';
    $params[] = $limit;
    $params[] = $offset;
    $stmt = $pdo->prepare($sql);
    foreach ($params as $i => $val) {
        $stmt->bindValue($i + 1, $val, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function contact_delete_thread(PDO $pdo, int $threadId): void
{
    ensure_contact_message_columns($pdo);
    $pdo->prepare('DELETE FROM messages WHERE thread_id=? OR id=?')->execute([$threadId, $threadId]);
}

function create_notification(PDO $pdo, int $userId, string $title, string $message, string $type = 'info', ?string $link = null, ?int $createdBy = null): void
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, link, created_by) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $title, $message, $type, $link, $createdBy]);
}

function unread_notifications(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function contact_fetch_client_for_lawyer(PDO $pdo, int $lawyerId, int $clientId): ?array
{
    if (!lawyer_can_access_client($pdo, $lawyerId, $clientId)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="client"');
    $stmt->execute([$clientId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function contact_fetch_case_summary(PDO $pdo, ?int $caseId): ?array
{
    if (!$caseId) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, case_number, title, status FROM cases WHERE id=?');
    $stmt->execute([$caseId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function contact_info_icon_svg(string $type): string
{
    $icons = [
        'company' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 21V5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v16"/><path d="M14 9h5a1 1 0 0 1 1 1v11"/><path d="M8 9h2M8 13h2M8 17h2"/></svg>',
        'lawyer' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="3.25"/><path d="M6 21v-1a6 6 0 0 1 12 0v1"/></svg>',
        'client' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="3.25"/><path d="M6 21v-1a6 6 0 0 1 12 0v1"/></svg>',
        'case' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><rect x="3" y="7" width="18" height="14" rx="2"/><path d="M3 13h18"/></svg>',
        'email' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
        'phone' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M6.6 3.9h2.4l1.2 3.6-1.6 1.2a11 11 0 0 0 5.3 5.3l1.2-1.6 3.6 1.2v2.4a2 2 0 0 1-2 2A14 14 0 0 1 4.6 5.9a2 2 0 0 1 2-2z"/></svg>',
        'location' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 21s6-5.2 6-10a6 6 0 1 0-12 0c0 4.8 6 10 6 10"/><circle cx="12" cy="11" r="2.5"/></svg>',
        'hours' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 8v4l2.5 2"/></svg>',
    ];
    return $icons[$type] ?? $icons['company'];
}

function notification_type_icon_svg(string $type): string
{
    $icons = [
        'document' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M8 3h6l5 5v13a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v5h5"/></svg>',
        'payment' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
        'appointment' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>',
        'case' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><rect x="3" y="7" width="18" height="14" rx="2"/><path d="M3 13h18"/></svg>',
        'reminder' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M7 9a5 5 0 0 1 10 0c0 5 2 6 2 6H5s2-1 2-6"/><path d="M10 19a2 2 0 0 0 4 0"/></svg>',
        'success' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M9 12.5l2 2 4-4.5"/></svg>',
        'info' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 11v5M12 8h.01"/></svg>',
    ];
    return $icons[$type] ?? $icons['info'];
}

/** @deprecated Use notification_type_icon_svg() */
function notification_type_icons(): array
{
    return [
        'info' => 'i',
        'success' => 'OK',
        'case' => 'C',
        'appointment' => 'A',
        'payment' => 'P',
        'document' => 'D',
        'reminder' => 'R',
    ];
}

function notification_open_url(?string $link, string $portalBase, string $base, string $fallback = ''): string
{
    $fallback = $fallback !== '' ? $fallback : ($portalBase . '/notifications.php');
    $link = trim((string) $link);
    if ($link === '') {
        return $fallback;
    }
    if (preg_match('#^https?://#i', $link) || str_starts_with($link, '/')) {
        return $link;
    }
    if (str_starts_with($link, '../')) {
        return rtrim($base, '/') . '/' . ltrim(preg_replace('#^(\.\./)+#', '', $link), '/');
    }
    return $portalBase . '/' . ltrim($link, './');
}

function format_time_ago(?string $date): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    if (!$ts) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return __('time.just_now');
    }
    if ($diff < 3600) {
        $mins = max(1, (int) floor($diff / 60));
        return $mins === 1 ? __('time.minute_ago') : __('time.minutes_ago', ['count' => $mins]);
    }
    if ($diff < 86400) {
        $hours = max(1, (int) floor($diff / 3600));
        return $hours === 1 ? __('time.hour_ago') : __('time.hours_ago', ['count' => $hours]);
    }
    if ($diff < 604800) {
        $days = max(1, (int) floor($diff / 86400));
        return $days === 1 ? __('time.day_ago') : __('time.days_ago', ['count' => $days]);
    }
    return format_date($date, 'M j, Y');
}

function handle_notification_post(PDO $pdo, array $user, string $redirectUrl, bool $allowDeleteAny = false): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    verify_csrf();
    ensure_notification_edited_column($pdo);
    $fa = post('form_action');
    $uid = (int) $user['id'];
    $returnPage = max(0, (int) post('return_page', 0));
    $returnTo = trim((string) post('return_to', ''));
    if ($returnTo !== '' && !str_contains($returnTo, '://') && !str_starts_with($returnTo, '//')) {
        $redirectTarget = $returnTo;
    } else {
        $redirectTarget = $returnPage > 1 ? $redirectUrl . '?page=' . $returnPage : $redirectUrl;
    }

    if ($fa === 'read') {
        $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([(int) post('id'), $uid]);
        redirect($redirectTarget);
    }
    if ($fa === 'read_all') {
        $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$uid]);
        flash('success', __('flash.notifications.marked_read'));
        redirect($redirectTarget);
    }
    if ($fa === 'edit') {
        $id = (int) post('id');
        $title = trim((string) post('title'));
        $message = trim((string) post('message'));
        if ($title === '' || $message === '') {
            flash('error', __('flash.notification.edit_required'));
            redirect($redirectTarget);
        }
        if ($allowDeleteAny) {
            $stmt = $pdo->prepare('UPDATE notifications SET title=?, message=?, edited_at=NOW() WHERE id=?');
            $stmt->execute([$title, $message, $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE notifications SET title=?, message=?, edited_at=NOW() WHERE id=? AND user_id=?');
            $stmt->execute([$title, $message, $id, $uid]);
        }
        if ($stmt->rowCount()) {
            flash('success', __('flash.notification.updated'));
        }
        redirect($redirectTarget);
    }
    if ($fa === 'delete') {
        $id = (int) post('id');
        if ($allowDeleteAny) {
            $pdo->prepare('DELETE FROM notifications WHERE id=?')->execute([$id]);
        } else {
            $pdo->prepare('DELETE FROM notifications WHERE id=? AND user_id=?')->execute([$id, $uid]);
        }
        flash('success', __('flash.notification.deleted'));
        redirect($redirectTarget);
    }
    if ($fa === 'clear_all') {
        $pdo->prepare('DELETE FROM notifications WHERE user_id=?')->execute([$uid]);
        flash('success', __('flash.notifications.cleared'));
        redirect($redirectTarget);
    }
}

function handle_notification_open(PDO $pdo, array $user, string $portalBase, string $base, string $fallbackUrl): void
{
    if (get('action') !== 'open') {
        return;
    }
    $id = (int) get('id', 0);
    $stmt = $pdo->prepare('SELECT link FROM notifications WHERE id=? AND user_id=?');
    $stmt->execute([$id, (int) $user['id']]);
    $row = $stmt->fetch();
    if (!$row) {
        redirect($fallbackUrl);
    }
    $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([$id, (int) $user['id']]);
    redirect(notification_open_url($row['link'] ?? null, $portalBase, $base, $fallbackUrl));
}

function get_setting(PDO $pdo, string $key, $default = null)
{
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : $default;
}

function set_setting(PDO $pdo, string $key, ?string $value): void
{
    $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

function client_case_instructions(PDO $pdo): string
{
    return trim((string) get_setting($pdo, 'client_case_instructions', __('content.settings.client_case_instructions')));
}

function handle_upload(array $file, string $subdir = 'documents'): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (($file['size'] ?? 0) > app_config('upload_max', 10485760)) {
        throw new RuntimeException(__('error.upload.too_large'));
    }
    $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt', 'xls', 'xlsx', 'zip'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException(__('error.upload.type_not_allowed'));
    }
    $dir = __DIR__ . '/../uploads/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $stored = uniqid('doc_', true) . '.' . $ext;
    $dest = $dir . '/' . $stored;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException(__('error.upload.store_failed'));
    }
    return [
        'file_name' => $file['name'],
        'file_path' => 'uploads/' . $subdir . '/' . $stored,
        'file_type' => $file['type'] ?? $ext,
        'file_size' => (int) $file['size'],
    ];
}

function format_file_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return rtrim(rtrim(number_format($bytes / 1024, 1, '.', ''), '0'), '.') . ' KB';
    }
    return rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.') . ' MB';
}

function ensure_document_requests_table(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS document_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            case_id INT UNSIGNED NOT NULL,
            client_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            instructions TEXT DEFAULT NULL,
            is_required TINYINT(1) NOT NULL DEFAULT 1,
            status ENUM("pending","fulfilled","cancelled") NOT NULL DEFAULT "pending",
            requested_by INT UNSIGNED DEFAULT NULL,
            fulfilled_document_id INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_doc_req_case (case_id),
            INDEX idx_doc_req_client (client_id),
            CONSTRAINT fk_doc_req_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
            CONSTRAINT fk_doc_req_client FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $ready = true;
}

function handle_branding_image_upload(array $file, string $prefix): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (($file['size'] ?? 0) > app_config('upload_max', 10485760)) {
        throw new RuntimeException(__('error.upload.too_large'));
    }
    $allowed = ['png', 'jpg', 'jpeg', 'svg', 'webp', 'gif', 'ico'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException(__('error.upload.type_not_allowed'));
    }
    $dir = __DIR__ . '/../uploads/branding';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $stored = $prefix . '_' . uniqid('', true) . '.' . $ext;
    $dest = $dir . '/' . $stored;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException(__('error.upload.store_failed'));
    }
    return 'uploads/branding/' . $stored;
}

function save_branding_data_url(string $dataUrl, string $prefix): ?string
{
    if (!preg_match('#^data:image/(png|jpeg|jpg|webp);base64,(.+)$#s', $dataUrl, $m)) {
        return null;
    }
    $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
    $bin = base64_decode($m[2], true);
    if ($bin === false || $bin === '' || strlen($bin) > app_config('upload_max', 10485760)) {
        return null;
    }
    $dir = __DIR__ . '/../uploads/branding';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $stored = $prefix . '_' . uniqid('', true) . '.' . $ext;
    if (file_put_contents($dir . '/' . $stored, $bin) === false) {
        return null;
    }
    return 'uploads/branding/' . $stored;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit(function_exists('__') ? __('error.csrf') : 'Invalid CSRF token.');
    }
}

function post(string $key, $default = null)
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

function get(string $key, $default = null)
{
    return isset($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
}

/** @return list<string> */
function appointment_statuses(): array
{
    return ['scheduled', 'confirmed', 'rescheduled', 'pending', 'completed', 'cancelled'];
}

/** Statuses that count as active/upcoming appointments. */
/** @return list<string> */
function appointment_upcoming_statuses(): array
{
    return ['scheduled', 'confirmed', 'rescheduled', 'pending'];
}

function normalize_appointment_status(string $status): string
{
    static $legacy = [
        'accepted' => 'confirmed',
        'rejected' => 'cancelled',
    ];
    return $legacy[$status] ?? $status;
}

/** @return list<int> */
function appointment_duration_options(): array
{
    return [30, 60, 90, 120];
}

function normalize_appointment_duration(int $minutes): int
{
    return in_array($minutes, appointment_duration_options(), true) ? $minutes : 60;
}

function format_appointment_duration(int $minutes): string
{
    $minutes = normalize_appointment_duration($minutes);
    return __('appointment.duration.' . $minutes);
}

function post_appointment_duration(string $field = 'duration_minutes', int $default = 60): int
{
    return normalize_appointment_duration((int) post($field, $default));
}

/** Calendar tone matches stored appointment status. */
function appointment_calendar_tone(array $appt): string
{
    $status = normalize_appointment_status((string) ($appt['status'] ?? 'pending'));
    return in_array($status, appointment_statuses(), true) ? $status : 'pending';
}

function appointment_format_day_time(?string $iso): string
{
    if (!$iso) {
        return '';
    }
    $ts = strtotime($iso);
    if (!$ts) {
        return '';
    }
    if (class_exists('IntlDateFormatter') && function_exists('locale_tag')) {
        $fmt = new IntlDateFormatter(
            locale_tag(),
            IntlDateFormatter::NONE,
            IntlDateFormatter::SHORT
        );
        $out = $fmt->format($ts);
        if ($out !== false) {
            return strtolower(str_replace(' ', '', $out));
        }
    }
    return strtolower(date('g:ia', $ts));
}

function appointment_calendar_tone_priority(string $tone): int
{
    static $map = [
        'cancelled' => 6,
        'completed' => 5,
        'pending' => 4,
        'confirmed' => 3,
        'rescheduled' => 2,
        'scheduled' => 1,
    ];
    return $map[$tone] ?? 0;
}

function appointment_calendar_pick_tone(array $items): string
{
    $best = 'scheduled';
    $score = 0;
    foreach ($items as $item) {
        $tone = (string) ($item['tone'] ?? 'scheduled');
        $next = appointment_calendar_tone_priority($tone);
        if ($next >= $score) {
            $score = $next;
            $best = $tone;
        }
    }
    return $best;
}

/** @return array<string, array<int, array<string, mixed>>> */
function appointment_calendar_group_by_date(array $items): array
{
    $byDate = [];
    foreach ($items as $item) {
        $raw = (string) ($item['scheduledAt'] ?? '');
        // Prefer YYYY-MM-DD prefix to avoid timezone shifts from strtotime
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
            $key = $m[1];
        } else {
            $ts = strtotime($raw);
            if (!$ts) {
                continue;
            }
            $key = date('Y-m-d', $ts);
        }
        $byDate[$key][] = $item;
    }
    foreach ($byDate as &$dayItems) {
        usort($dayItems, static fn(array $a, array $b): int => strtotime((string) $a['scheduledAt']) <=> strtotime((string) $b['scheduledAt']));
    }
    unset($dayItems);
    return $byDate;
}

function render_appointment_calendar_day_events(array $items, int $maxShow = 2): string
{
    if (!$items) {
        return '';
    }
    $shown = array_slice($items, 0, $maxShow);
    $extra = count($items) - count($shown);
    $html = '<div class="appt-cal-day-events">';
    foreach ($shown as $item) {
        $tone = e((string) ($item['tone'] ?? 'scheduled'));
        $title = e((string) ($item['title'] ?? ''));
        $time = e(appointment_format_day_time((string) ($item['scheduledAt'] ?? '')));
        $viewLabel = e(__('calendar.view_appointment'));
        $id = (int) ($item['id'] ?? 0);
        $html .= '<a href="?view=' . $id . '" class="appt-cal-day-event tone-' . $tone . '" data-appt-view="' . $id . '" title="' . $viewLabel . ': ' . $title . '" onclick="return window.lexoraViewAppointment ? window.lexoraViewAppointment(' . $id . ', event) : true;">';
        $html .= '<span class="appt-cal-day-event-dot" aria-hidden="true"></span>';
        $html .= '<span class="appt-cal-day-event-label"><span class="appt-cal-day-event-time">' . $time . '</span> ' . $title . '</span>';
        $html .= '</a>';
    }
    if ($extra > 0) {
        $html .= '<span class="appt-cal-day-more">+' . (int) $extra . ' more</span>';
    }
    $html .= '</div>';
    return $html;
}

function render_appointment_calendar_days(int $year, int $month, int $selectedDay, array $itemsByDate): string
{
    $firstDow = ((int) date('N', mktime(0, 0, 0, $month + 1, 1, $year))) - 1;
    $daysInMonth = (int) date('t', mktime(0, 0, 0, $month + 1, 1, $year));
    $selectedDay = min(max(1, $selectedDay), $daysInMonth);
    $todayY = (int) date('Y');
    $todayM = (int) date('n') - 1;
    $todayD = (int) date('j');
    $colIndex = $firstDow;
    $html = '';

    for ($i = 0; $i < $firstDow; $i++) {
        $html .= '<span class="appt-cal-day is-empty" aria-hidden="true"></span>';
    }

    for ($day = 1; $day <= $daysInMonth; $day++) {
        $key = sprintf('%04d-%02d-%02d', $year, $month + 1, $day);
        $items = $itemsByDate[$key] ?? [];
        $tone = $items ? appointment_calendar_pick_tone($items) : '';
        $isToday = $todayY === $year && $todayM === $month && $todayD === $day;
        $isSelected = $day === $selectedDay;
        $colIndex = ($firstDow + $day - 1) % 7;

        $classes = ['appt-cal-day'];
        if ($items) {
            $classes[] = 'has-appt';
        }
        if ($tone) {
            $classes[] = 'tone-' . $tone;
        }
        if ($isToday) {
            $classes[] = 'is-today';
        }
        if ($isSelected) {
            $classes[] = 'is-selected';
        }

        $label = (string) $day;
        if ($items) {
            $count = count($items);
            $label .= ', ' . $count . ' appointment' . ($count > 1 ? 's' : '');
        }

        $dateIso = sprintf('%04d-%02d-%02d', $year, $month + 1, $day);
        $dayHref = e('?cal_date=' . $dateIso);

        $html .= '<div class="' . implode(' ', $classes) . '" data-day="' . $day . '" data-date="' . e($dateIso) . '" data-col="' . $colIndex . '">';
        $html .= '<a href="' . $dayHref . '" class="appt-cal-day-select" aria-label="' . e($label) . '">';
        $html .= '<span class="appt-cal-day-num">' . $day . '</span>';
        $html .= '</a>';
        $html .= render_appointment_calendar_day_events($items);
        $html .= '</div>';
    }

    $usedCells = $firstDow + $daysInMonth;
    $targetCells = 6 * 7;
    for ($i = $usedCells; $i < $targetCells; $i++) {
        $html .= '<span class="appt-cal-day is-empty" aria-hidden="true"></span>';
    }

    return $html;
}

function appointment_calendar_selected_label(int $year, int $month, int $day): string
{
    $ts = mktime(0, 0, 0, $month + 1, $day, $year);
    if (!$ts) {
        return '';
    }
    if (class_exists('IntlDateFormatter') && function_exists('locale_tag')) {
        $fmt = new IntlDateFormatter(
            locale_tag(),
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            null,
            null,
            'EEEE, d MMMM yyyy'
        );
        $out = $fmt->format($ts);
        if ($out !== false) {
            return $out;
        }
    }
    return date('l, j F Y', $ts);
}

function appointment_calendar_export_urls(array $item): array
{
    $title = (string) ($item['title'] ?? 'Appointment');
    $details = (string) ($item['description'] ?? '');
    $location = (string) ($item['location'] ?? '');
    $startTs = strtotime((string) ($item['scheduledAt'] ?? $item['scheduled_at'] ?? ''));
    $duration = (int) ($item['durationMinutes'] ?? $item['duration_minutes'] ?? 60);
    $duration = normalize_appointment_duration($duration);
    if (!$startTs) {
        return ['google' => '#', 'outlook' => '#', 'ics' => '#'];
    }
    $endTs = $startTs + normalize_appointment_duration($duration) * 60;
    $fmt = static fn(int $ts): string => gmdate('Ymd\THis', $ts);
    // Use local wall time without Z for Google "floating" local times
    $localFmt = static fn(int $ts): string => date('Ymd\THis', $ts);

    $google = 'https://calendar.google.com/calendar/render?' . http_build_query([
        'action' => 'TEMPLATE',
        'text' => $title,
        'dates' => $localFmt($startTs) . '/' . $localFmt($endTs),
        'details' => $details,
        'location' => $location,
    ]);

    $outlook = 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query([
        'path' => '/calendar/action/compose',
        'rru' => 'addevent',
        'subject' => $title,
        'startdt' => date('c', $startTs),
        'enddt' => date('c', $endTs),
        'body' => $details,
        'location' => $location,
    ]);

    $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Legal Pro//Appointments//EN\r\nBEGIN:VEVENT\r\n"
        . 'UID:appt-' . (int) ($item['id'] ?? 0) . "@legal-system\r\n"
        . 'DTSTAMP:' . $fmt(time()) . "\r\n"
        . 'DTSTART:' . $localFmt($startTs) . "\r\n"
        . 'DTEND:' . $localFmt($endTs) . "\r\n"
        . 'SUMMARY:' . str_replace(["\r", "\n"], ' ', $title) . "\r\n"
        . 'DESCRIPTION:' . str_replace(["\r", "\n"], '\\n', $details) . "\r\n"
        . 'LOCATION:' . str_replace(["\r", "\n"], ' ', $location) . "\r\n"
        . "END:VEVENT\r\nEND:VCALENDAR";

    return [
        'google' => $google,
        'outlook' => $outlook,
        'ics' => 'data:text/calendar;charset=utf-8,' . rawurlencode($ics),
    ];
}

function appointment_list_status_badge(string $status): string
{
    $status = normalize_appointment_status($status);
    $label = e(translate_status($status));
    return '<span class="appt-list-badge tone-' . e($status) . '">' . $label . '</span>';
}

/** @return list<string> */
function hearing_statuses(): array
{
    return ['scheduled', 'adjourned', 'completed', 'cancelled'];
}

function hearing_calendar_tone(string $status): string
{
    $status = strtolower(trim($status));
    return match ($status) {
        'adjourned' => 'rescheduled',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
        default => 'scheduled',
    };
}

function hearing_list_status_badge(string $status): string
{
    $status = strtolower(trim($status));
    if (!in_array($status, hearing_statuses(), true)) {
        $status = 'scheduled';
    }
    $tone = hearing_calendar_tone($status);
    return '<span class="appt-list-badge tone-' . e($tone) . '">' . e(translate_status($status)) . '</span>';
}

function ensure_court_hearing_lawyer_column(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $col = $pdo->query("SHOW COLUMNS FROM court_hearings LIKE 'lawyer_id'")->fetch();
    if (!$col) {
        $pdo->exec('ALTER TABLE court_hearings ADD COLUMN lawyer_id INT UNSIGNED DEFAULT NULL AFTER case_id');
        try {
            $pdo->exec('ALTER TABLE court_hearings ADD CONSTRAINT fk_court_hearing_lawyer FOREIGN KEY (lawyer_id) REFERENCES users(id) ON DELETE SET NULL');
        } catch (Throwable $e) {
            // Constraint may already exist.
        }
        $pdo->exec('UPDATE court_hearings h JOIN cases c ON c.id = h.case_id SET h.lawyer_id = c.lawyer_id WHERE h.lawyer_id IS NULL AND c.lawyer_id IS NOT NULL');
    }
    $ready = true;
}

function resolve_hearing_lawyer_id(PDO $pdo, int $caseId, ?int $lawyerId = null, ?int $lockLawyerId = null): ?int
{
    ensure_court_hearing_lawyer_column($pdo);
    if ($lockLawyerId && $lockLawyerId > 0) {
        return $lockLawyerId;
    }
    if ($lawyerId && $lawyerId > 0) {
        $chk = $pdo->prepare('SELECT id FROM users WHERE id=? AND role="lawyer" AND is_active=1');
        $chk->execute([$lawyerId]);
        return $chk->fetch() ? $lawyerId : null;
    }
    if ($caseId > 0) {
        $case = $pdo->prepare('SELECT lawyer_id FROM cases WHERE id=?');
        $case->execute([$caseId]);
        $row = $case->fetch();
        if ($row && !empty($row['lawyer_id'])) {
            return (int) $row['lawyer_id'];
        }
    }
    return null;
}

function calendar_item_from_appointment(array $r, array $opts = []): array
{
    $caseLabel = trim(($r['case_number'] ? $r['case_number'] . ' — ' : '') . ($r['case_title'] ?: ($r['title'] ?? '')));
    $status = normalize_appointment_status((string) ($r['status'] ?? 'pending'));
    return [
        'id' => (int) ($r['id'] ?? 0),
        'title' => t_content($r['title'] ?? ''),
        'caseLabel' => t_content($caseLabel),
        'client' => (string) ($r['client_name'] ?? ''),
        'lawyer' => (string) ($r['lawyer_name'] ?? ''),
        'location' => !empty($r['location']) ? t_content($r['location']) : '',
        'scheduledAt' => (string) ($r['scheduled_at'] ?? ''),
        'status' => $status,
        'tone' => appointment_calendar_tone($r),
        'statusLabel' => translate_status($status),
        'description' => !empty($r['description']) ? t_content($r['description']) : '',
        'durationMinutes' => (int) ($r['duration_minutes'] ?? 60),
        'appointmentType' => (string) ($r['appointment_type'] ?? ''),
        'appointmentTypeLabel' => !empty($r['appointment_type']) ? __('appointment.type.' . $r['appointment_type']) : '',
        'editUrl' => (string) ($opts['editUrl'] ?? ('?action=edit&id=' . (int) ($r['id'] ?? 0))),
    ];
}

function calendar_item_from_hearing(array $r, array $opts = []): array
{
    $caseLabel = trim(($r['case_number'] ? $r['case_number'] . ' — ' : '') . ($r['title'] ?? ''));
    $status = strtolower((string) ($r['status'] ?? 'scheduled'));
    $hearingType = trim((string) ($r['hearing_type'] ?? ''));
    $hearingTitle = trim(($hearingType !== '' ? $hearingType : __('court.hearings')) . ($caseLabel !== '' ? ' — ' . $caseLabel : ''));
    $location = trim((string) ($r['court_name'] ?? '') . (!empty($r['court_location']) ? ', ' . $r['court_location'] : ''));
    $notes = trim((string) ($r['notes'] ?? ''));
    $outcome = trim((string) ($r['outcome'] ?? ''));
    $judge = trim((string) ($r['judge_name'] ?? ''));
    $editUrl = array_key_exists('editUrl', $opts)
        ? (string) $opts['editUrl']
        : ('?action=edit&id=' . (int) ($r['id'] ?? 0));

    return [
        'id' => (int) ($r['id'] ?? 0),
        'entity' => 'hearing',
        'title' => t_content($hearingTitle),
        'caseLabel' => t_content($caseLabel),
        'client' => t_content((string) ($r['court_name'] ?? '')),
        'lawyer' => (string) ($r['lawyer_name'] ?? ''),
        'location' => t_content($location),
        'scheduledAt' => (string) ($r['hearing_date'] ?? ''),
        'status' => $status,
        'tone' => hearing_calendar_tone($status),
        'statusLabel' => translate_status($status),
        'hearingType' => $hearingType !== '' ? t_content($hearingType) : '',
        'judge' => $judge !== '' ? t_content($judge) : '',
        'outcome' => $outcome !== '' ? t_content($outcome) : '',
        'description' => $notes !== '' ? t_content($notes) : '',
        'durationMinutes' => (int) ($opts['durationMinutes'] ?? 60),
        'editUrl' => $editUrl,
    ];
}

/**
 * Build calendar selection state + footer payload for appointments or hearings.
 *
 * @param list<array<string,mixed>> $calendarItems
 * @param array<string,mixed> $config
 * @return array<string,mixed>
 */
function build_entity_calendar_context(array $calendarItems, array $config = []): array
{
    $entity = (string) ($config['entity'] ?? 'appointment');
    $showCreate = (bool) ($config['showCreate'] ?? false);
    $createUrl = (string) ($config['createUrl'] ?? '?action=create');
    $createLabel = (string) ($config['createLabel'] ?? __('appointments.create'));
    $upcomingStatuses = $config['upcomingStatuses'] ?? (
        $entity === 'hearing' ? ['scheduled'] : appointment_upcoming_statuses()
    );

    $calendarMonths = [
        __('calendar.month.jan'), __('calendar.month.feb'), __('calendar.month.mar'),
        __('calendar.month.apr'), __('calendar.month.may'), __('calendar.month.jun'),
        __('calendar.month.jul'), __('calendar.month.aug'), __('calendar.month.sep'),
        __('calendar.month.oct'), __('calendar.month.nov'), __('calendar.month.dec'),
    ];

    if ($entity === 'hearing') {
        $legendItems = [];
        foreach (hearing_statuses() as $st) {
            $legendItems[] = [
                'tone' => hearing_calendar_tone($st),
                'label' => __('court.tone.' . $st),
            ];
        }
        $countOne = (string) ($config['countOne'] ?? __('court.total_one', ['count' => ':count']));
        $countMany = (string) ($config['countMany'] ?? __('court.total_many', ['count' => ':count']));
        $emptyDay = (string) ($config['emptyDay'] ?? __('calendar.empty_day_hearings'));
        $emptyMonth = (string) ($config['emptyMonth'] ?? __('calendar.empty_month_hearings'));
        $viewLabel = (string) ($config['viewLabel'] ?? __('calendar.view_hearing'));
        $viewAllLabel = (string) ($config['viewAllLabel'] ?? __('calendar.view_hearings'));
        $noViewLabel = (string) ($config['noViewLabel'] ?? __('calendar.no_view_hearings'));
        $scheduleLabel = (string) ($config['scheduleLabel'] ?? __('court.add'));
        $fieldPerson = (string) ($config['fieldPerson'] ?? __('common.court'));
        $mainAria = (string) ($config['mainAria'] ?? __('court.schedule'));
    } else {
        $legendItems = [];
        foreach (appointment_statuses() as $st) {
            $legendItems[] = [
                'tone' => $st,
                'label' => __('calendar.tone.' . $st),
            ];
        }
        $countOne = (string) ($config['countOne'] ?? __('appointments.total_one', ['count' => ':count']));
        $countMany = (string) ($config['countMany'] ?? __('appointments.total_many', ['count' => ':count']));
        $emptyDay = (string) ($config['emptyDay'] ?? __('calendar.empty_day'));
        $emptyMonth = (string) ($config['emptyMonth'] ?? __('calendar.empty_month'));
        $viewLabel = (string) ($config['viewLabel'] ?? __('calendar.view_appointment'));
        $viewAllLabel = (string) ($config['viewAllLabel'] ?? __('calendar.view_appointments'));
        $noViewLabel = (string) ($config['noViewLabel'] ?? __('calendar.no_view_available'));
        $scheduleLabel = (string) ($config['scheduleLabel'] ?? __('calendar.schedule_for_day'));
        $fieldPerson = (string) ($config['fieldPerson'] ?? __('common.client'));
        $mainAria = (string) ($config['mainAria'] ?? __('appointments.calendar'));
    }

    $calYear = (int) date('Y');
    $calMonth = (int) date('n') - 1;
    $calDay = (int) date('j');
    $calDateParam = get('cal_date', '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $calDateParam)) {
        $calYear = (int) substr($calDateParam, 0, 4);
        $calMonth = (int) substr($calDateParam, 5, 2) - 1;
        $calDay = (int) substr($calDateParam, 8, 2);
    }

    $calendarItemsByDate = appointment_calendar_group_by_date($calendarItems);
    $selectedKey = sprintf('%04d-%02d-%02d', $calYear, $calMonth + 1, $calDay);
    $selectedDayItems = $calendarItemsByDate[$selectedKey] ?? [];

    if (!$selectedDayItems) {
        $upcoming = null;
        foreach ($calendarItems as $item) {
            if (!in_array((string) ($item['status'] ?? ''), $upcomingStatuses, true)) {
                continue;
            }
            $ts = strtotime((string) ($item['scheduledAt'] ?? ''));
            if (!$ts || $ts < time()) {
                continue;
            }
            if (!$upcoming || $ts < strtotime((string) $upcoming['scheduledAt'])) {
                $upcoming = $item;
            }
        }
        if ($upcoming) {
            $raw = (string) $upcoming['scheduledAt'];
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
                $calYear = (int) $m[1];
                $calMonth = (int) $m[2] - 1;
                $calDay = (int) $m[3];
            } else {
                $calYear = (int) date('Y', strtotime($raw));
                $calMonth = (int) date('n', strtotime($raw)) - 1;
                $calDay = (int) date('j', strtotime($raw));
            }
            $selectedKey = sprintf('%04d-%02d-%02d', $calYear, $calMonth + 1, $calDay);
            $selectedDayItems = $calendarItemsByDate[$selectedKey] ?? [];
        }
    }

    $calendarMonthCounts = array_fill(0, 12, 0);
    foreach ($calendarItems as $item) {
        $raw = (string) ($item['scheduledAt'] ?? '');
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
            $ts = strtotime($raw);
            if (!$ts || (int) date('Y', $ts) !== $calYear) {
                continue;
            }
            $calendarMonthCounts[(int) date('n', $ts) - 1]++;
            continue;
        }
        if ((int) $m[1] !== $calYear) {
            continue;
        }
        $calendarMonthCounts[(int) $m[2] - 1]++;
    }

    $calendarItemsById = [];
    foreach ($calendarItems as $item) {
        $calendarItemsById[(string) $item['id']] = $item;
    }

    $viewId = (int) get('view', 0);
    $viewItem = $viewId && isset($calendarItemsById[(string) $viewId])
        ? $calendarItemsById[(string) $viewId]
        : null;

    $agendaLabels = [
        'emptyDay' => $emptyDay,
        'countOne' => $countOne,
        'countMany' => $countMany,
        'scheduleLabel' => $scheduleLabel,
        'viewLabel' => $viewLabel,
        'viewAllLabel' => $viewAllLabel,
        'noViewLabel' => $noViewLabel,
        'createUrl' => $createUrl,
        'selectedDate' => $selectedKey,
    ];

    $apptCalPayload = [
        'items' => $calendarItems,
        'itemsById' => $calendarItemsById,
        'months' => $calendarMonths,
        'tones' => array_column($legendItems, 'label'),
        'emptyDay' => $emptyDay,
        'emptyMonth' => $emptyMonth,
        'scheduleLabel' => $scheduleLabel,
        'viewLabel' => $viewLabel,
        'viewAllLabel' => $viewAllLabel,
        'noViewLabel' => $noViewLabel,
        'apptCountOne' => $countOne,
        'apptCountMany' => $countMany,
        'pageOf' => __('calendar.page_of', ['page' => ':page', 'pages' => ':pages']),
        'prevLabel' => __('common.previous'),
        'nextLabel' => __('common.next'),
        'createUrl' => $createUrl,
        'locale' => locale_tag(),
        'emDash' => __('common.em_dash'),
        'editLabel' => __('common.edit'),
        'fieldClient' => $fieldPerson,
        'fieldCase' => __('common.case'),
        'fieldWhen' => __('common.when'),
        'fieldLocation' => __('common.location'),
        'fieldStatus' => __('common.status'),
        'fieldNotes' => __('common.notes'),
        'fieldLawyer' => __('common.lawyer'),
        'exportGoogle' => __('calendar.export.google'),
        'exportOutlook' => __('calendar.export.outlook'),
        'exportIcs' => __('calendar.export.ics'),
    ];

    return [
        'calendarItems' => $calendarItems,
        'calendarItemsByDate' => $calendarItemsByDate,
        'calendarItemsById' => $calendarItemsById,
        'calendarMonths' => $calendarMonths,
        'calendarMonthCounts' => $calendarMonthCounts,
        'calendarLegendItems' => $legendItems,
        'calYear' => $calYear,
        'calMonth' => $calMonth,
        'calDay' => $calDay,
        'selectedDayItems' => $selectedDayItems,
        'viewItem' => $viewItem,
        'apptCalPayload' => $apptCalPayload,
        'calShowCreate' => $showCreate,
        'calCreateUrl' => $createUrl,
        'calCreateLabel' => $createLabel,
        'calMainAria' => $mainAria,
        'calViewLabel' => $viewLabel,
        'calViewAllLabel' => $viewAllLabel,
        'calNoViewLabel' => $noViewLabel,
        'calEmptyDay' => $emptyDay,
        'calScheduleLabel' => $scheduleLabel,
        'calCountOne' => $countOne,
        'calCountMany' => $countMany,
        'calAgendaLabels' => $agendaLabels,
        'calFieldPerson' => $fieldPerson,
    ];
}


function hearing_calendar_export_urls(array $item): array
{
    return appointment_calendar_export_urls([
        'id' => (int) ($item['id'] ?? 0),
        'title' => (string) ($item['title'] ?? 'Court hearing'),
        'description' => (string) ($item['description'] ?? $item['notes'] ?? ''),
        'location' => trim(((string) ($item['court_name'] ?? '')) . ((($item['court_location'] ?? '') !== '') ? ', ' . $item['court_location'] : '')),
        'scheduledAt' => (string) ($item['hearing_date'] ?? $item['scheduledAt'] ?? ''),
        'durationMinutes' => (int) ($item['durationMinutes'] ?? 60),
    ]);
}

function calendar_export_buttons_html(array $exports, string $downloadName = 'event'): string
{
    $name = preg_replace('/[^\w\-]+/', '-', strtolower($downloadName)) ?: 'event';
    return '<div class="appt-list-cal-actions">'
        . '<a class="appt-list-cal-btn" href="' . e($exports['google']) . '" target="_blank" rel="noopener" title="' . e(__('calendar.export.google')) . '" aria-label="' . e(__('calendar.export.google')) . '"><span class="appt-list-cal-g" aria-hidden="true">G</span></a>'
        . '<a class="appt-list-cal-btn" href="' . e($exports['outlook']) . '" target="_blank" rel="noopener" title="' . e(__('calendar.export.outlook')) . '" aria-label="' . e(__('calendar.export.outlook')) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 3v3M16 3v3M8 13h3M13 13h3M8 17h3M13 17h3"/></svg></a>'
        . '<a class="appt-list-cal-btn" href="' . e($exports['ics']) . '" download="' . e($name) . '.ics" title="' . e(__('calendar.export.ics')) . '" aria-label="' . e(__('calendar.export.ics')) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v12m0 0l-4-4m4 4l4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg></a>'
        . '</div>';
}


function render_appointment_calendar_agenda_head(int $year, int $month, int $day, array $items, array $labels = []): string
{
    $selectedLabel = e(appointment_calendar_selected_label($year, $month, $day));
    $count = count($items);
    $empty = (string) ($labels['emptyDay'] ?? __('calendar.empty_day'));
    $oneTpl = (string) ($labels['countOne'] ?? __('appointments.total_one', ['count' => ':count']));
    $manyTpl = (string) ($labels['countMany'] ?? __('appointments.total_many', ['count' => ':count']));
    $countLabel = $count === 0
        ? e($empty)
        : e(str_replace(':count', (string) $count, $count === 1 ? $oneTpl : $manyTpl));

    return '<div class="appt-cal-agenda-panel">'
        . '<p class="appt-cal-agenda-date">' . $selectedLabel . '</p>'
        . '<p class="appt-cal-agenda-count">' . $countLabel . '</p>'
        . '</div>';
}

function render_appointment_calendar_agenda_list(array $items, int $page = 1, int $perPage = 2): string
{
    if (!$items) {
        return '';
    }

    $total = count($items);
    $perPage = max(1, $perPage);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $page), $pages);
    $offset = ($page - 1) * $perPage;
    $slice = array_slice($items, $offset, $perPage);

    $html = '';
    foreach ($slice as $index => $item) {
        $tone = e((string) ($item['tone'] ?? 'scheduled'));
        $title = e((string) ($item['caseLabel'] ?? $item['title'] ?? ''));
        $time = e(appointment_format_day_time((string) ($item['scheduledAt'] ?? '')));
        $person = e((string) ($item['client'] ?? $item['lawyer'] ?? ''));
        $statusLabel = e((string) ($item['statusLabel'] ?? ''));
        $viewLabel = e(__('calendar.view_appointment'));
        $delay = (int) $index * 40;

        $html .= '<article class="appt-cal-agenda-card tone-' . $tone . '" style="animation-delay:' . $delay . 'ms">';
        $id = (int) ($item['id'] ?? 0);
        $html .= '<a href="?view=' . $id . '" class="appt-cal-agenda-card-link" data-appt-view="' . $id . '" title="' . $viewLabel . '" onclick="return window.lexoraViewAppointment ? window.lexoraViewAppointment(' . $id . ', event) : true;">';
        $html .= '<span class="appt-cal-agenda-line tone-' . $tone . '">';
        $html .= '<span class="appt-cal-day-event-dot" aria-hidden="true"></span>';
        $html .= '<span class="appt-cal-day-event-label"><span class="appt-cal-day-event-time">' . $time . '</span> ' . $title . '</span>';
        $html .= '</span>';
        $html .= '<span class="appt-cal-agenda-meta">';
        $html .= '<span class="appt-cal-person">' . $person . '</span>';
        $html .= '<span class="appt-cal-badge tone-' . $tone . '">' . $statusLabel . '</span>';
        $html .= '</span>';
        $html .= '</a>';
        $html .= '</article>';
    }

    return $html;
}

function render_appointment_calendar_agenda_pager(int $total, int $page = 1, int $perPage = 2): string
{
    if ($total < 1) {
        return '';
    }
    $perPage = max(1, $perPage);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $page), $pages);
    $label = e(__('calendar.page_of', ['page' => $page, 'pages' => $pages]));
    $prevDisabled = $page <= 1 ? ' disabled' : '';
    $nextDisabled = $page >= $pages ? ' disabled' : '';

    return '<div class="appt-cal-agenda-pager" id="apptCalAgendaPagerInner" data-page="' . $page . '" data-pages="' . $pages . '">'
        . '<button type="button" class="appt-cal-agenda-page-btn" data-agenda-page="prev"' . $prevDisabled . ' aria-label="' . e(__('common.previous')) . '">'
        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>'
        . '</button>'
        . '<span class="appt-cal-agenda-page-label">' . $label . '</span>'
        . '<button type="button" class="appt-cal-agenda-page-btn" data-agenda-page="next"' . $nextDisabled . ' aria-label="' . e(__('common.next')) . '">'
        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>'
        . '</button>'
        . '</div>';
}

function ensure_lawyer_availability_slots_table(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS lawyer_availability_slots (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            lawyer_id INT UNSIGNED NOT NULL,
            week_start DATE NOT NULL COMMENT "Monday of the week",
            day_of_week TINYINT UNSIGNED NOT NULL COMMENT "1=Mon ... 6=Sat",
            slot_time TIME NOT NULL,
            UNIQUE KEY uniq_lawyer_week_day_slot (lawyer_id, week_start, day_of_week, slot_time),
            INDEX idx_lawyer_week (lawyer_id, week_start),
            CONSTRAINT fk_lawyer_availability_lawyer
                FOREIGN KEY (lawyer_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $col = $pdo->query("SHOW COLUMNS FROM lawyer_availability_slots LIKE 'week_start'")->fetch();
    if (!$col) {
        $defaultWeek = availability_week_start();
        $pdo->exec("ALTER TABLE lawyer_availability_slots ADD COLUMN week_start DATE NOT NULL DEFAULT '$defaultWeek' AFTER lawyer_id");
        $pdo->exec("UPDATE lawyer_availability_slots SET week_start='$defaultWeek'");
        try {
            $pdo->exec('ALTER TABLE lawyer_availability_slots DROP INDEX uniq_lawyer_day_slot');
        } catch (Throwable $e) {
            // Legacy index may already be replaced.
        }
        try {
            $pdo->exec('ALTER TABLE lawyer_availability_slots ADD UNIQUE KEY uniq_lawyer_week_day_slot (lawyer_id, week_start, day_of_week, slot_time)');
        } catch (Throwable $e) {
            // Index may already exist on fresh installs.
        }
        try {
            $pdo->exec('ALTER TABLE lawyer_availability_slots ADD INDEX idx_lawyer_week (lawyer_id, week_start)');
        } catch (Throwable $e) {
            // Index may already exist on fresh installs.
        }
    }

    $ready = true;
}

/** Monday (Y-m-d) for the week containing the given date, or today. */
function availability_week_start(?string $date = null): string
{
    $ts = $date ? strtotime($date) : time();
    if (!$ts) {
        $ts = time();
    }
    $dateStr = date('Y-m-d', $ts);
    $dow = (int) date('N', $ts);
    if ($dow === 7) {
        return date('Y-m-d', strtotime($dateStr . ' -6 days'));
    }
    return date('Y-m-d', strtotime($dateStr . ' -' . ($dow - 1) . ' days'));
}

function availability_normalize_week_start(?string $week): string
{
    if ($week && preg_match('/^\d{4}-\d{2}-\d{2}$/', $week)) {
        return availability_week_start($week);
    }
    return availability_week_start();
}

/** @return array<int, string> day 1–6 => Y-m-d */
function availability_week_dates(string $weekStart): array
{
    $weekStart = availability_normalize_week_start($weekStart);
    $dates = [];
    for ($day = 1; $day <= 6; $day++) {
        $dates[$day] = date('Y-m-d', strtotime($weekStart . ' +' . ($day - 1) . ' days'));
    }
    return $dates;
}

function availability_format_short_date(string $date): string
{
    $ts = strtotime($date);
    if (!$ts) {
        return $date;
    }
    if (class_exists('IntlDateFormatter') && function_exists('locale_tag')) {
        $fmt = new IntlDateFormatter(locale_tag(), IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);
        return $fmt->format($ts) ?: date('j M', $ts);
    }
    return date('j M', $ts);
}

function availability_format_week_range(string $weekStart): string
{
    $weekStart = availability_normalize_week_start($weekStart);
    $weekEnd = date('Y-m-d', strtotime($weekStart . ' +5 days'));
    return __('availability.week.range', [
        'start' => availability_format_short_date($weekStart),
        'end' => availability_format_short_date($weekEnd),
    ]);
}

/** @return array<int, string> ISO weekday 1=Mon … 6=Sat */
function availability_weekdays(): array
{
    return [
        1 => __('availability.day.mon'),
        2 => __('availability.day.tue'),
        3 => __('availability.day.wed'),
        4 => __('availability.day.thu'),
        5 => __('availability.day.fri'),
        6 => __('availability.day.sat'),
    ];
}

/** @return list<string> HH:MM:SS */
function availability_slot_times(): array
{
    $slots = [];
    for ($hour = 9; $hour < 17; $hour++) {
        $slots[] = sprintf('%02d:00:00', $hour);
        $slots[] = sprintf('%02d:30:00', $hour);
    }
    return $slots;
}

function availability_format_slot_label(string $time): string
{
    $ts = strtotime('1970-01-01 ' . $time);
    if (!$ts) {
        return $time;
    }
    if (class_exists('IntlDateFormatter') && function_exists('locale_tag')) {
        $fmt = new IntlDateFormatter(locale_tag(), IntlDateFormatter::NONE, IntlDateFormatter::SHORT);
        return $fmt->format($ts) ?: date('g:i A', $ts);
    }
    return date('g:i A', $ts);
}

/** @return array<int, array<string, bool>> */
function get_lawyer_availability_matrix(PDO $pdo, int $lawyerId, ?string $weekStart = null): array
{
    ensure_lawyer_availability_slots_table($pdo);
    $weekStart = availability_normalize_week_start($weekStart);
    $matrix = [];
    foreach (array_keys(availability_weekdays()) as $day) {
        $matrix[$day] = [];
    }
    if ($lawyerId <= 0) {
        return $matrix;
    }
    $stmt = $pdo->prepare('SELECT day_of_week, slot_time FROM lawyer_availability_slots WHERE lawyer_id=? AND week_start=?');
    $stmt->execute([$lawyerId, $weekStart]);
    foreach ($stmt->fetchAll() as $row) {
        $matrix[(int) $row['day_of_week']][substr((string) $row['slot_time'], 0, 5)] = true;
    }
    return $matrix;
}

/** @param list<string> $slotKeys e.g. ["1-09:00", "2-14:30"] */
function save_lawyer_availability_matrix(PDO $pdo, int $lawyerId, string $weekStart, array $slotKeys): void
{
    ensure_lawyer_availability_slots_table($pdo);
    $weekStart = availability_normalize_week_start($weekStart);
    $pdo->prepare('DELETE FROM lawyer_availability_slots WHERE lawyer_id=? AND week_start=?')->execute([$lawyerId, $weekStart]);
    $ins = $pdo->prepare('INSERT INTO lawyer_availability_slots (lawyer_id, week_start, day_of_week, slot_time) VALUES (?,?,?,?)');
    foreach ($slotKeys as $key) {
        if (!preg_match('/^([1-6])-(\d{2}:\d{2})$/', (string) $key, $m)) {
            continue;
        }
        $ins->execute([$lawyerId, $weekStart, (int) $m[1], $m[2] . ':00']);
    }
}

/** @return array{ok:bool, message:string} */
function validate_lawyer_appointment_slot(PDO $pdo, ?int $lawyerId, string $scheduledAt, int $durationMinutes, ?int $excludeApptId = null, bool $forUpdate = false): array
{
    if (!$lawyerId || $lawyerId <= 0) {
        return ['ok' => true, 'message' => ''];
    }

    ensure_lawyer_availability_slots_table($pdo);

    $lawyer = $pdo->prepare('SELECT availability FROM users WHERE id=? AND role="lawyer"');
    $lawyer->execute([$lawyerId]);
    $lawyerRow = $lawyer->fetch();
    if (!$lawyerRow) {
        return ['ok' => false, 'message' => __('error.availability.lawyer_not_found')];
    }
    if (($lawyerRow['availability'] ?? '') === 'unavailable') {
        return ['ok' => false, 'message' => __('error.availability.lawyer_unavailable')];
    }

    $startTs = strtotime($scheduledAt);
    if (!$startTs) {
        return ['ok' => false, 'message' => __('error.availability.invalid_datetime')];
    }

    $durationMinutes = normalize_appointment_duration($durationMinutes);
    $endTs = $startTs + ($durationMinutes * 60);
    $step = 30 * 60;

    for ($t = $startTs; $t < $endTs; $t += $step) {
        $dow = (int) date('N', $t);
        if ($dow === 7) {
            return ['ok' => false, 'message' => __('error.availability.sunday')];
        }
        if ($dow < 1 || $dow > 6) {
            return ['ok' => false, 'message' => __('error.availability.outside_days')];
        }
        $slotTime = date('H:i:00', $t);
        $weekStart = availability_week_start(date('Y-m-d', $t));
        $chk = $pdo->prepare('SELECT 1 FROM lawyer_availability_slots WHERE lawyer_id=? AND week_start=? AND day_of_week=? AND slot_time=?');
        $chk->execute([$lawyerId, $weekStart, $dow, $slotTime]);
        if (!$chk->fetch()) {
            return ['ok' => false, 'message' => __('error.availability.not_available')];
        }
    }

    $conflict = $pdo->prepare(
        'SELECT id FROM appointments
         WHERE lawyer_id=?
           AND status NOT IN ("cancelled","completed")
           AND (? IS NULL OR id <> ?)
           AND scheduled_at < FROM_UNIXTIME(?)
           AND DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > FROM_UNIXTIME(?)'
        . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $conflict->execute([$lawyerId, $excludeApptId, $excludeApptId, $endTs, $startTs]);
    if ($conflict->fetch()) {
        return ['ok' => false, 'message' => __('error.availability.conflict')];
    }

    return ['ok' => true, 'message' => ''];
}

/** @return list<array{value:string, label:string}> */
function get_lawyer_bookable_slots(PDO $pdo, int $lawyerId, string $date, int $durationMinutes, ?int $excludeApptId = null): array
{
    if ($lawyerId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return [];
    }

    $dow = (int) date('N', strtotime($date));
    if ($dow === 7) {
        return [];
    }

    $durationMinutes = normalize_appointment_duration($durationMinutes);
    $bookable = [];
    foreach (availability_slot_times() as $slotTime) {
        $timeKey = substr($slotTime, 0, 5);
        $scheduledAt = $date . ' ' . $timeKey . ':00';
        $check = validate_lawyer_appointment_slot($pdo, $lawyerId, $scheduledAt, $durationMinutes, $excludeApptId);
        if ($check['ok']) {
            $bookable[] = [
                'value' => $timeKey,
                'label' => availability_format_slot_label($slotTime),
            ];
        }
    }

    return $bookable;
}

function flash_lawyer_slot_error(array $check, string $redirect): void
{
    flash('error', $check['message'] ?: __('error.availability.not_available'));
    redirect($redirect);
}

/** Build a no-JS overview area/line SVG for the glass dashboard. */
function build_overview_svg(array $labels, array $values, string $ariaLabel): string
{
    $w = 640;
    $h = 220;
    $padL = 12;
    $padR = 12;
    $padT = 18;
    $padB = 34;
    $n = max(count($values), 1);
    $max = max(array_map('floatval', $values) ?: [0]);
    if ($max <= 0) {
        $max = 1;
    }
    $innerW = $w - $padL - $padR;
    $innerH = $h - $padT - $padB;
    $pts = [];
    foreach ($values as $i => $v) {
        $x = $padL + ($n === 1 ? $innerW / 2 : ($i / ($n - 1)) * $innerW);
        $y = $padT + $innerH - ((float) $v / $max) * $innerH;
        $pts[] = [$x, $y];
    }
    if (!$pts) {
        $pts[] = [$padL + $innerW / 2, $padT + $innerH];
    }
    $line = '';
    foreach ($pts as $i => [$x, $y]) {
        $line .= ($i === 0 ? 'M' : 'L') . round($x, 1) . ',' . round($y, 1) . ' ';
    }
    $area = $line . 'L' . round($pts[count($pts) - 1][0], 1) . ',' . ($padT + $innerH)
        . ' L' . round($pts[0][0], 1) . ',' . ($padT + $innerH) . ' Z';
    $labelsHtml = '';
    foreach ($labels as $i => $lab) {
        $x = $padL + ($n === 1 ? $innerW / 2 : ($i / ($n - 1)) * $innerW);
        $labelsHtml .= '<text x="' . round($x, 1) . '" y="' . ($h - 10) . '" text-anchor="middle">' . htmlspecialchars((string) $lab) . '</text>';
    }
    $last = $pts[count($pts) - 1];
    return '<svg class="glass-svg-chart" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img" aria-label="' . htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') . '">'
        . '<defs><linearGradient id="ovFill" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0%" stop-color="currentColor" stop-opacity="0.28"/>'
        . '<stop offset="100%" stop-color="currentColor" stop-opacity="0"/>'
        . '</linearGradient></defs>'
        . '<path class="glass-svg-area" d="' . trim($area) . '" fill="url(#ovFill)"/>'
        . '<path class="glass-svg-line" d="' . trim($line) . '" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>'
        . '<circle class="glass-svg-dot" cx="' . round($last[0], 1) . '" cy="' . round($last[1], 1) . '" r="5"/>'
        . '<g class="glass-svg-labels">' . $labelsHtml . '</g>'
        . '</svg>';
}
