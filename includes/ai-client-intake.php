<?php
/**
 * Guided AI client intake — multi-turn wizard + document extraction.
 * Included from ai-actions.php
 */

/** @return list<string> */
function ai_client_required_fields(): array
{
    return ['first_name', 'last_name', 'email', 'username', 'password'];
}

/** @return list<string> */
function ai_client_optional_fields(): array
{
    return ['phone', 'address', 'company_name', 'assigned_lawyer', 'notes'];
}

/** @return array<string, mixed>|null */
function ai_client_draft_get(int $sessionId): ?array
{
    if ($sessionId <= 0) {
        return null;
    }
    $draft = $_SESSION['ai_client_draft'][$sessionId] ?? null;
    return is_array($draft) ? $draft : null;
}

/** @param array<string, mixed> $draft */
function ai_client_draft_set(int $sessionId, array $draft): void
{
    if ($sessionId <= 0) {
        return;
    }
    if (!isset($_SESSION['ai_client_draft']) || !is_array($_SESSION['ai_client_draft'])) {
        $_SESSION['ai_client_draft'] = [];
    }
    $draft['updated_at'] = time();
    $_SESSION['ai_client_draft'][$sessionId] = $draft;
}

function ai_client_draft_clear(int $sessionId): void
{
    if ($sessionId <= 0) {
        return;
    }
    unset($_SESSION['ai_client_draft'][$sessionId]);
}

/** @return array<string, string> empty template */
function ai_client_draft_blank(): array
{
    return [
        'type' => 'client',
        'fields' => [
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'username' => '',
            'password' => '',
            'phone' => '',
            'address' => '',
            'company_name' => '',
            'assigned_lawyer' => '',
            'assigned_lawyer_id' => '',
            'notes' => '',
            'is_active' => '1',
        ],
        'sources' => [],
        'awaiting' => 'details', // details | confirm
        'awaiting_field' => '',
        'doc_names' => [],
        'created_at' => time(),
        'updated_at' => time(),
    ];
}

/**
 * Only treat a message as an intake answer when it clearly is one.
 * Prevents open intakes from hijacking unrelated chat (email, Q&A, etc.).
 *
 * @param array<string, mixed> $draft
 * @param array<int, array<string, mixed>> $attachments
 */
function ai_client_message_is_intake_reply(string $message, string $q, array $draft, array $attachments = []): bool
{
    if ($attachments) {
        $hay = $message;
        foreach ($attachments as $att) {
            $hay .= ' ' . (string) ($att['file_name'] ?? '');
        }
        // Invoice/receipt extracts are not intake answers.
        if (preg_match('/\b(INV|RCP)[-_]?\d/i', $hay)
            && (ai_actions_wants($q, ['extract details', 'extract invoice', 'extract receipt', 'invoice details', 'receipt details'])
                || in_array(trim($q), ['extract', 'details', ''], true))) {
            return false;
        }
        return true;
    }
    $trim = trim($message);
    if ($trim === '') {
        return false;
    }

    if (ai_actions_wants($q, [
        'confirm', 'yes create', 'create now', 'save client', 'oui', 'confirmer', 'go ahead',
        'create client', 'new client', 'add client', 'extract client', 'register client',
    ])) {
        return true;
    }

    // Explicit field assignments
    if (preg_match('/\b(first_name|last_name|email|username|password|phone|address|company_name|notes|assigned_lawyer)\s*=/i', $trim)) {
        return true;
    }

    // Pure email / short scalar answer while a field is awaited
    $awaiting = (string) ($draft['awaiting_field'] ?? '');
    if ($awaiting !== '') {
        if (filter_var($trim, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        // Short answers only (avoid "Draft a professional email…")
        $wordCount = preg_match_all('/\S+/u', $trim) ?: 0;
        if ($wordCount <= 6 && !preg_match('/\b(draft|write|compose|schedule|create|how|what|when|where|why|who|list|show|summar|define|email|hearing|appointment|case|dossier)\b/iu', $q)) {
            return true;
        }
        if ($awaiting === 'password' && $wordCount <= 3 && !str_contains($trim, ' ')) {
            return true;
        }
        if (in_array($awaiting, ['phone', 'username'], true) && $wordCount <= 3) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<int, array<string, mixed>> $attachments
 */
function ai_client_doc_looks_like_profile(array $attachments): bool
{
    $text = ai_client_attachments_text($attachments);
    if (strlen($text) < 20) {
        return false;
    }
    $hits = 0;
    if (ai_actions_extract_email($text)) {
        $hits++;
    }
    if (ai_actions_extract_phone($text)) {
        $hits++;
    }
    if (preg_match('/\b(name|nom|client|email|phone|tel|address|adresse|company|societe|société)\b/iu', $text)) {
        $hits++;
    }
    if (preg_match('/\b[A-ZÀ-ÖØ-Ý][\w\'\-]+\s+[A-ZÀ-ÖØ-Ý][\w\'\-]+\b/u', $text)) {
        $hits++;
    }
    return $hits >= 2;
}

/**
 * @param array<int, array<string, mixed>> $attachments
 */
function ai_client_attachments_text(array $attachments): string
{
    $parts = [];
    foreach ($attachments as $att) {
        $t = trim((string) ($att['text'] ?? ''));
        $name = (string) ($att['file_name'] ?? 'file');
        if ($t !== '') {
            $parts[] = "=== FILE: {$name} ===\n{$t}";
        }
    }
    return implode("\n\n", $parts);
}

/**
 * @param array<int, array<string, mixed>> $attachments
 * @return array{fields: array<string,string>, note: string, existing_client: ?array, document: ?array}
 */
function ai_client_extract_from_attachments(PDO $pdo, array $attachments): array
{
    $fields = [];
    $notes = [];
    $existing = null;
    $document = null;

    $haystack = '';
    foreach ($attachments as $att) {
        $haystack .= ' ' . (string) ($att['file_name'] ?? '') . "\n" . (string) ($att['text'] ?? '');
    }

    $system = ai_client_lookup_system_document($pdo, $haystack);
    if ($system) {
        $existing = $system['user'];
        $fields = array_merge($fields, $system['fields']);
        $notes[] = $system['note'];
        $document = $system['document'] ?? null;
        // System invoice/receipt match is enough — skip slow LLM extraction.
        return [
            'fields' => ai_client_sanitize_extracted_fields($fields),
            'note' => implode(' ', $notes),
            'existing_client' => $existing,
            'document' => is_array($document) ? $document : null,
        ];
    }

    $docText = ai_client_attachments_text($attachments);
    $parsed = ai_client_extract_fields_from_text($docText, $pdo, false);
    foreach ($parsed as $k => $v) {
        if ($v !== '' && empty($fields[$k])) {
            $fields[$k] = $v;
        }
    }

    $fields = ai_client_sanitize_extracted_fields($fields);

    if ($existing) {
        // keep note from system lookup
    } elseif ($fields) {
        $labels = [];
        foreach ($fields as $k => $v) {
            if (trim((string) $v) !== '') {
                $labels[] = ai_client_field_label($k);
            }
        }
        if ($labels) {
            $notes[] = '📄 Extracted from document(s): ' . implode(', ', array_unique($labels)) . '.';
        }
    } elseif ($docText === '') {
        $notes[] = '📄 File attached but little readable text was found (common with some PDFs). Type the client details, or upload a text/Word intake form.';
    } else {
        $notes[] = '📄 Document read, but no reliable client name/email/phone was found. Please type the details.';
    }

    return [
        'fields' => $fields,
        'note' => implode(' ', $notes),
        'existing_client' => $existing,
        'document' => null,
    ];
}

/**
 * Resolve client from Lexora invoice/receipt numbers in filename or text.
 *
 * @return array{user:?array<string,mixed>,fields:array<string,string>,note:string,document:?array,ref:string}|null
 */
function ai_client_lookup_system_document(PDO $pdo, string $haystack): ?array
{
    $invoiceNo = ai_client_match_doc_number($haystack, 'INV');
    $receiptNo = ai_client_match_doc_number($haystack, 'RCP');

    $clientId = 0;
    $ref = '';
    $document = null;
    try {
        if ($invoiceNo) {
            $row = ai_client_find_invoice_row($pdo, $invoiceNo);
            if ($row) {
                $clientId = (int) $row['client_id'];
                $ref = 'invoice ' . $row['invoice_number'];
                $document = ai_invoice_build_extract_payload($pdo, $row);
            }
        }
        if (!$document && $receiptNo) {
            $stmt = $pdo->prepare(
                'SELECT p.*, i.invoice_number, i.case_id AS invoice_case_id, c.case_number, c.title AS case_title
                 FROM payments p
                 LEFT JOIN invoices i ON i.id = p.invoice_id
                 LEFT JOIN cases c ON c.id = i.case_id
                 WHERE UPPER(p.receipt_number) = ?
                    OR REPLACE(UPPER(p.receipt_number), "-", "") = ?
                 LIMIT 1'
            );
            $stmt->execute([$receiptNo, str_replace('-', '', $receiptNo)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $clientId = (int) $row['client_id'];
                $ref = 'receipt ' . ($row['receipt_number'] ?? '');
                $document = ai_receipt_build_extract_payload($pdo, $row);
            }
        }
        if (!$document) {
            return null;
        }

        $user = null;
        $fields = [];
        if ($clientId > 0) {
            $uStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'client' LIMIT 1");
            $uStmt->execute([$clientId]);
            $user = $uStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    } catch (Throwable $e) {
        return null;
    }

    if ($user) {
        $fields = [
            'first_name' => (string) ($user['first_name'] ?? ''),
            'last_name' => (string) ($user['last_name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'phone' => (string) ($user['phone'] ?? ''),
            'address' => (string) ($user['address'] ?? ''),
            'company_name' => (string) ($user['company_name'] ?? ''),
            'username' => (string) ($user['username'] ?? ''),
            'notes' => 'Linked from ' . $ref,
        ];
        if (!empty($user['assigned_lawyer_id'])) {
            $fields['assigned_lawyer_id'] = (string) (int) $user['assigned_lawyer_id'];
            try {
                $l = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id=? AND role='lawyer'");
                $l->execute([(int) $user['assigned_lawyer_id']]);
                $lr = $l->fetch(PDO::FETCH_ASSOC);
                if ($lr) {
                    $fields['assigned_lawyer'] = trim($lr['first_name'] . ' ' . $lr['last_name']);
                }
            } catch (Throwable $e) {
                // ignore
            }
        }

        if (is_array($document)) {
            $document['client'] = [
                'id' => (int) $user['id'],
                'name' => trim($fields['first_name'] . ' ' . $fields['last_name']),
                'email' => $fields['email'],
                'phone' => $fields['phone'],
                'company' => $fields['company_name'],
                'address' => $fields['address'],
            ];
        }

        return [
            'user' => $user,
            'fields' => ai_client_sanitize_extracted_fields($fields),
            'note' => '📄 Matched system ' . $ref . ' → client **' . trim($fields['first_name'] . ' ' . $fields['last_name']) . '** (already in the system).',
            'document' => $document,
            'ref' => $ref,
        ];
    }

    return [
        'user' => null,
        'fields' => [],
        'note' => '📄 Matched system ' . $ref . '.',
        'document' => $document,
        'ref' => $ref,
    ];
}

/**
 * @return non-empty-string|null
 */
function ai_client_match_doc_number(string $haystack, string $prefix): ?string
{
    $prefix = strtoupper($prefix);
    if (!preg_match('/\b(' . preg_quote($prefix, '/') . '[- _]?\d{4}[- _]?[A-Z0-9]+)\b/i', $haystack, $m)) {
        return null;
    }
    $raw = strtoupper(preg_replace('/[\s_]+/', '', $m[1]) ?? $m[1]);
    $raw = preg_replace('/^' . preg_quote($prefix, '/') . '-?/', $prefix . '-', $raw) ?? $raw;
    return $raw !== '' ? $raw : null;
}

/**
 * @return array<string, mixed>|null
 */
function ai_client_find_invoice_row(PDO $pdo, string $invoiceNo): ?array
{
    $variants = array_values(array_unique(array_filter([
        $invoiceNo,
        str_replace('-', '', $invoiceNo),
        preg_replace('/^INV-/i', 'INV', $invoiceNo),
    ])));

    foreach ($variants as $variant) {
        $stmt = $pdo->prepare(
            'SELECT i.*, c.case_number, c.title AS case_title
             FROM invoices i
             LEFT JOIN cases c ON c.id = i.case_id
             WHERE UPPER(i.invoice_number) = ?
                OR REPLACE(UPPER(i.invoice_number), "-", "") = ?
             LIMIT 1'
        );
        $compact = str_replace('-', '', strtoupper($variant));
        $stmt->execute([strtoupper($variant), $compact]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
    }

    // Filename sometimes truncates or adds a suffix — try prefix match on compact form.
    $compact = str_replace('-', '', strtoupper($invoiceNo));
    if (strlen($compact) >= 10) {
        $stmt = $pdo->prepare(
            'SELECT i.*, c.case_number, c.title AS case_title
             FROM invoices i
             LEFT JOIN cases c ON c.id = i.case_id
             WHERE REPLACE(UPPER(i.invoice_number), "-", "") LIKE ?
             ORDER BY i.id DESC
             LIMIT 1'
        );
        $stmt->execute([$compact . '%']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
    }

    return null;
}

/**
 * Extract invoice/receipt from attachments or message — never opens client intake.
 *
 * @param array<int, array<string, mixed>> $attachments
 */
function ai_action_extract_system_document(
    PDO $pdo,
    array $user,
    string $portal,
    string $message,
    array $attachments = []
): ?string {
    $haystack = $message;
    foreach ($attachments as $att) {
        $haystack .= ' ' . (string) ($att['file_name'] ?? '') . "\n" . (string) ($att['text'] ?? '');
    }

    $system = ai_client_lookup_system_document($pdo, $haystack);
    if ($system && is_array($system['document'] ?? null) && !empty($system['document']['number'])) {
        if (!empty($user['id']) && function_exists('ai_client_draft_clear')) {
            // Avoid leaving a stale intake draft after an extract request.
            // Session clearing is handled by caller when session id is known.
        }
        $existing = is_array($system['user'] ?? null) ? $system['user'] : [];
        return ai_document_format_extract_reply($system['document'], $existing, $portal);
    }

    $invoiceNo = ai_client_match_doc_number($haystack, 'INV');
    $receiptNo = ai_client_match_doc_number($haystack, 'RCP');
    if ($invoiceNo || $receiptNo) {
        $label = $invoiceNo ? ('invoice **' . $invoiceNo . '**') : ('receipt **' . $receiptNo . '**');
        return "📄 I could not find {$label} in the system database.\n\n"
            . "No new client was started. Check the number, or open **Billing → Invoices**.\n"
            . "To register someone new, say **create client** (do not use an existing invoice PDF for that).";
    }

    if ($attachments) {
        $names = [];
        foreach ($attachments as $att) {
            $n = trim((string) ($att['file_name'] ?? ''));
            if ($n !== '') {
                $names[] = $n;
            }
        }
        $fileLabel = $names ? ('**' . implode('**, **', $names) . '**') : 'this file';
        return "📄 Attached {$fileLabel}, but I could not match an invoice or receipt in the system.\n\n"
            . "No new client was started. Attach a Lexora invoice/receipt PDF (e.g. INV-2026-…), or say **create client** to register someone new.";
    }

    return null;
}

/**
 * @param array<string, mixed> $invoice
 * @return array<string, mixed>
 */
function ai_invoice_build_extract_payload(PDO $pdo, array $invoice): array
{
    $invoiceId = (int) ($invoice['id'] ?? 0);
    $totals = $invoiceId > 0
        ? invoice_display_totals($pdo, $invoiceId, $invoice, false)
        : ['lines' => [], 'subtotal' => (float) ($invoice['amount'] ?? 0), 'vat' => (float) ($invoice['tax'] ?? 0), 'grand' => (float) ($invoice['total'] ?? 0)];

    $lines = [];
    foreach (($totals['lines'] ?? []) as $line) {
        $qty = (float) ($line['quantity'] ?? 1);
        $unit = (float) ($line['unit_price'] ?? 0);
        $vat = (float) ($line['vat_amount'] ?? 0);
        $total = (float) ($line['line_total'] ?? (($qty * $unit) + $vat));
        $lines[] = [
            'description' => trim((string) ($line['description'] ?? 'Service')),
            'quantity' => $qty,
            'unit_price' => $unit,
            'vat' => $vat,
            'total' => $total,
            'unit_price_fmt' => money($unit),
            'vat_fmt' => money($vat),
            'total_fmt' => money($total),
        ];
    }

    $paid = $invoiceId > 0 ? invoice_paid_total($pdo, $invoiceId) : 0.0;
    $grand = (float) ($totals['grand'] ?? ($invoice['total'] ?? 0));
    $balance = max(0, round($grand - $paid, 2));
    $payStatus = function_exists('invoice_payment_status') ? invoice_payment_status($invoice) : (string) ($invoice['payment_status'] ?? $invoice['status'] ?? '');

    return [
        'kind' => 'invoice',
        'id' => $invoiceId,
        'number' => (string) ($invoice['invoice_number'] ?? ''),
        'title' => trim((string) ($invoice['title'] ?? '')),
        'description' => trim((string) ($invoice['description'] ?? '')),
        'status' => (string) ($invoice['status'] ?? ''),
        'payment_status' => $payStatus,
        'issued_at' => (string) ($invoice['issued_at'] ?? ''),
        'due_date' => (string) ($invoice['due_date'] ?? ''),
        'case_id' => (int) ($invoice['case_id'] ?? 0),
        'case_number' => (string) ($invoice['case_number'] ?? ''),
        'case_title' => trim((string) ($invoice['case_title'] ?? '')),
        'lines' => $lines,
        'subtotal' => (float) ($totals['subtotal'] ?? 0),
        'vat' => (float) ($totals['vat'] ?? 0),
        'grand' => $grand,
        'paid' => $paid,
        'balance' => $balance,
        'subtotal_fmt' => money((float) ($totals['subtotal'] ?? 0)),
        'vat_fmt' => money((float) ($totals['vat'] ?? 0)),
        'grand_fmt' => money($grand),
        'paid_fmt' => money($paid),
        'balance_fmt' => money($balance),
        'issued_fmt' => !empty($invoice['issued_at']) ? format_date($invoice['issued_at']) : '',
        'due_fmt' => !empty($invoice['due_date']) ? format_date($invoice['due_date']) : '',
        'status_label' => function_exists('translate_status') ? translate_status((string) ($invoice['status'] ?? '')) : (string) ($invoice['status'] ?? ''),
        'payment_label' => $payStatus !== ''
            ? (function_exists('__') && __('finance.payment_status.' . $payStatus) !== ('finance.payment_status.' . $payStatus)
                ? __('finance.payment_status.' . $payStatus)
                : ucfirst(str_replace('_', ' ', $payStatus)))
            : '',
    ];
}

/**
 * @param array<string, mixed> $payment
 * @return array<string, mixed>
 */
function ai_receipt_build_extract_payload(PDO $pdo, array $payment): array
{
    $amount = (float) ($payment['amount'] ?? 0);
    return [
        'kind' => 'receipt',
        'id' => (int) ($payment['id'] ?? 0),
        'number' => (string) ($payment['receipt_number'] ?? ''),
        'title' => 'Payment receipt',
        'description' => trim((string) ($payment['notes'] ?? $payment['method'] ?? '')),
        'status' => (string) ($payment['status'] ?? 'paid'),
        'payment_status' => 'paid',
        'issued_at' => (string) ($payment['paid_at'] ?? $payment['created_at'] ?? ''),
        'due_date' => '',
        'case_id' => (int) ($payment['invoice_case_id'] ?? 0),
        'case_number' => (string) ($payment['case_number'] ?? ''),
        'case_title' => trim((string) ($payment['case_title'] ?? '')),
        'invoice_number' => (string) ($payment['invoice_number'] ?? ''),
        'method' => (string) ($payment['payment_method'] ?? $payment['method'] ?? ''),
        'lines' => [[
            'description' => trim((string) (($payment['invoice_number'] ?? '') !== '' ? ('Payment for ' . $payment['invoice_number']) : 'Payment received')),
            'quantity' => 1,
            'unit_price' => $amount,
            'vat' => 0,
            'total' => $amount,
            'unit_price_fmt' => money($amount),
            'vat_fmt' => money(0),
            'total_fmt' => money($amount),
        ]],
        'subtotal' => $amount,
        'vat' => 0.0,
        'grand' => $amount,
        'paid' => $amount,
        'balance' => 0.0,
        'subtotal_fmt' => money($amount),
        'vat_fmt' => money(0),
        'grand_fmt' => money($amount),
        'paid_fmt' => money($amount),
        'balance_fmt' => money(0),
        'issued_fmt' => !empty($payment['paid_at']) ? format_date($payment['paid_at']) : (!empty($payment['created_at']) ? format_date($payment['created_at']) : ''),
        'due_fmt' => '',
        'status_label' => 'Paid',
        'payment_label' => 'Paid',
    ];
}

/**
 * @param array<string, mixed> $document
 * @param array<string, mixed> $user
 */
function ai_document_format_extract_reply(array $document, array $user, string $portal = 'admin'): string
{
    $kind = (string) ($document['kind'] ?? 'invoice');
    $number = (string) ($document['number'] ?? '');
    $clientId = (int) ($user['id'] ?? 0);
    $clientName = $clientId > 0 ? trim(full_name($user)) : '';

    $clientUrl = $clientId > 0 ? ai_actions_portal_url($portal, 'clients.php?action=view&id=' . $clientId) : '';
    $docUrl = '';
    if ($kind === 'invoice' && !empty($document['id'])) {
        $docUrl = ai_actions_portal_url($portal, 'invoice.php?id=' . (int) $document['id']);
    } elseif ($kind === 'receipt' && !empty($document['id'])) {
        $docUrl = ai_actions_portal_url($portal, 'receipt.php?id=' . (int) $document['id']);
    }
    $caseUrl = '';
    if (!empty($document['case_id'])) {
        $caseUrl = ai_actions_portal_url($portal, 'cases.php?action=view&id=' . (int) $document['case_id']);
    }

    $card = $document;
    $card['links'] = [
        'client' => $clientUrl,
        'document' => $docUrl,
        'case' => $caseUrl,
    ];
    $json = json_encode($card, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        $json = '{}';
    }

    $headline = $kind === 'receipt'
        ? "📄 Extracted receipt **{$number}**"
        : "📄 Extracted invoice **{$number}**";

    $lines = [
        $headline,
        '',
        'All available details from this document are shown below.',
        '',
        '[[AI_INVOICE_CARD]]',
        $json,
        '[[/AI_INVOICE_CARD]]',
        '',
    ];

    if ($clientName !== '') {
        $lines[] = 'This client is **already registered** — a new client was not created.';
        $lines[] = '• Name: **' . $clientName . '**';
        $lines[] = '• Email: ' . (($user['email'] ?? '') !== '' ? $user['email'] : '—');
        $lines[] = '• Phone: ' . (($user['phone'] ?? '') !== '' ? $user['phone'] : '—');
        if (!empty($user['company_name'])) {
            $lines[] = '• Company: ' . $user['company_name'];
        }
        $lines[] = '';
    } else {
        $lines[] = 'Invoice details extracted from the system. No new client was created.';
        $lines[] = '';
    }

    $links = [];
    if ($clientUrl !== '') {
        $links[] = ai_actions_md_link('Open client profile', $clientUrl);
    }
    if ($docUrl !== '') {
        $links[] = ai_actions_md_link($kind === 'receipt' ? 'Open receipt' : 'Open invoice', $docUrl);
    }
    if ($caseUrl !== '') {
        $links[] = ai_actions_md_link('Open case', $caseUrl);
    }
    if ($links) {
        $lines[] = implode(' · ', $links);
    }
    $lines[] = '';
    $lines[] = 'To register someone new, say **create client** and type their details (or attach an intake form, not an existing invoice).';

    return implode("\n", $lines);
}

function ai_client_is_blocked_name_token(string $token): bool
{
    $t = strtoupper(trim($token));
    $t = preg_replace('/[^A-Z0-9\-]/', '', $t) ?? $t;
    if ($t === '') {
        return true;
    }
    static $blocked = [
        'INVOICE', 'INVOICES', 'RECEIPT', 'RECEIPTS', 'BILL', 'BILLTO', 'BILLING',
        'CLIENT', 'DETAILS', 'PERSONAL', 'INFORMATION', 'CONTACT', 'ADDRESS', 'COMPANY',
        'DOCUMENT', 'STATEMENT', 'QUOTE', 'QUOTATION', 'CONTRACT', 'AGREEMENT',
        'CASE', 'DOSSIER', 'LEGAL', 'PRO', 'LEXORA', 'PAGE', 'TOTAL', 'AMOUNT',
        'DUE', 'PAID', 'SUBTOTAL', 'VAT', 'TAX', 'DATE', 'NUMBER', 'NO', 'REF',
        'FROM', 'THIS', 'EXTRACT', 'CREATE', 'NEW', 'ADD', 'REGISTER',
        'MR', 'MRS', 'MS', 'MISS', 'DR',
    ];
    if (in_array($t, $blocked, true)) {
        return true;
    }
    if (preg_match('/^(INV|RCP|CASE|DOC)[-_]?\d/i', $t)) {
        return true;
    }
    if (preg_match('/^\d+$/', $t)) {
        return true;
    }
    return false;
}

function ai_client_is_plausible_person_name(string $first, string $last): bool
{
    $first = trim($first);
    $last = trim($last);
    if ($first === '' || $last === '') {
        return false;
    }
    if (ai_client_is_blocked_name_token($first) || ai_client_is_blocked_name_token($last)) {
        return false;
    }
    if (!preg_match('/^[\p{L}][\p{L}\'\-]{0,40}$/u', $first)) {
        return false;
    }
    if (!preg_match('/^[\p{L}][\p{L}\'\-]{0,60}$/u', $last)) {
        return false;
    }
    // Reject ALL CAPS document headers of unusual length tokens with digits
    if (preg_match('/\d/', $first . $last)) {
        return false;
    }
    return true;
}

function ai_client_is_plausible_phone(?string $phone): bool
{
    if ($phone === null) {
        return false;
    }
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) < 7 || strlen($digits) > 15) {
        return false;
    }
    // Reject timestamps like 20260708084504
    if (preg_match('/^(19|20)\d{12}$/', $digits)) {
        return false;
    }
    if (preg_match('/^(19|20)\d{6}$/', $digits) && strlen($digits) >= 8) {
        // YYYYMMDD alone is not a phone
        if (strlen($digits) === 8) {
            return false;
        }
    }
    return true;
}

/**
 * @param array<string, string> $fields
 * @return array<string, string>
 */
function ai_client_sanitize_extracted_fields(array $fields): array
{
    foreach (['first_name', 'last_name'] as $nk) {
        if (empty($fields[$nk])) {
            continue;
        }
        $val = trim((string) $fields[$nk]);
        // Drop greedy/corrupt captures from single-line pastes
        if ($val === '' || preg_match('/\b(?:first_name|last_name|email|username|password|phone)\s*[:=]/i', $val)) {
            unset($fields[$nk]);
            continue;
        }
        if (ai_client_is_blocked_name_token($val)) {
            unset($fields[$nk]);
            continue;
        }
        $max = $nk === 'first_name' ? 40 : 60;
        if (!preg_match('/^[\p{L}][\p{L}\'\-\s]{0,' . $max . '}$/u', $val)) {
            unset($fields[$nk]);
        }
    }
    if (!empty($fields['phone']) && !ai_client_is_plausible_phone((string) $fields['phone'])) {
        unset($fields['phone']);
    }
    if (!empty($fields['email']) && !filter_var((string) $fields['email'], FILTER_VALIDATE_EMAIL)) {
        unset($fields['email']);
    }
    if (!empty($fields['username']) && preg_match('/\b(?:first_name|last_name|email|password|phone)\s*[:=]/i', (string) $fields['username'])) {
        unset($fields['username']);
    }
    foreach (['company_name', 'address', 'notes', 'assigned_lawyer'] as $k) {
        if (empty($fields[$k])) {
            continue;
        }
        $val = trim((string) $fields[$k]);
        if (preg_match('/\b(?:lawyer|notes|note|company|address|email|phone|username|password|first_name|last_name)\s*[:=]/i', $val, $m, PREG_OFFSET_CAPTURE)) {
            $val = trim(substr($val, 0, (int) $m[0][1]));
        }
        if ($val === '' || ai_client_is_blocked_name_token($val)) {
            unset($fields[$k]);
        } else {
            $fields[$k] = $val;
        }
    }
    return $fields;
}

/**
 * Extract client-like fields from free text / documents.
 *
 * @return array<string, string>
 */
function ai_client_extract_fields_from_text(string $text, PDO $pdo, bool $useLlm = false): array
{
    $out = [];
    $text = trim($text);
    if ($text === '') {
        return $out;
    }
    // Avoid regex fatals / warnings on binary PDF scrapings.
    if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
        $text = function_exists('mb_convert_encoding')
            ? (string) mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252')
            : utf8_encode($text);
    }
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', $text) ?? $text;

    // Prefer Bill To / client blocks on invoices & letters
    $billBlock = '';
    if (preg_match('/(?:bill\s*to|facture\s*[àa]|client\s*:|billed\s*to)\s*[:\-]?\s*(.{10,400})/isu', $text, $m)) {
        $billBlock = $m[1];
    }

    $searchSpaces = array_filter([$billBlock, $text]);

    foreach ($searchSpaces as $chunk) {
        if (empty($out['email'])) {
            $email = ai_actions_extract_email($chunk);
            if ($email) {
                $out['email'] = $email;
            }
        }

        if (empty($out['phone'])) {
            $phone = null;
            if (preg_match('/(?:phone|tel(?:ephone)?|mobile|gsm|cell)\s*[:#\-]?\s*([+\d][\d\s\-().]{6,20})/iu', $chunk, $m)) {
                $phone = trim($m[1]);
            }
            if ($phone && ai_client_is_plausible_phone($phone)) {
                $out['phone'] = preg_replace('/\s+/', ' ', $phone) ?? $phone;
            }
        }

        $patterns = [
            'first_name' => '/(?:first\s*name|prenom|prénom|given\s*name)\s*[:\-]\s*([^\n,;]+)/iu',
            'last_name' => '/(?:last\s*name|surname|family\s*name|nom(?:\s+de\s+famille)?)\s*[:\-]\s*([^\n,;]+)/iu',
            'company_name' => '/(?:company(?:\s*name)?|organisation|organization|societe|société|firm)\s*[:\-]\s*([^\n]+)/iu',
            'address' => '/(?:address|adresse|residential\s*address|domicile)\s*[:\-]\s*([^\n]+(?:\n(?![A-Za-z ]{0,20}:)[^\n]+){0,2})/iu',
            'username' => '/(?:username|user\s*name|login|identifiant)\s*[:\-]\s*([A-Za-z0-9._\-]+)/iu',
        ];
        foreach ($patterns as $key => $re) {
            if (!empty($out[$key])) {
                continue;
            }
            if (preg_match($re, $chunk, $m)) {
                $val = trim(preg_replace('/\s+/', ' ', $m[1]) ?? $m[1]);
                if ($val !== '') {
                    $out[$key] = $val;
                }
            }
        }

        if (empty($out['first_name']) || empty($out['last_name'])) {
            $namePatterns = [
                '/(?:full\s*name|client\s*name|nom\s*complet|nom\s*du\s*client)\s*[:\-]\s*([A-ZÀ-ÖØ-Ý][\p{L}\'\-]+)\s+([A-ZÀ-ÖØ-Ý][\p{L}\'\-]+(?:\s+[A-ZÀ-ÖØ-Ý][\p{L}\'\-]+)?)/u',
                '/(?:bill\s*to|facture\s*[àa]|billed\s*to)\s*[:\-]?\s*([A-ZÀ-ÖØ-Ý][\p{L}\'\-]+)\s+([A-ZÀ-ÖØ-Ý][\p{L}\'\-]+)/iu',
                '/(?:mr|mrs|ms|miss|mme|mlle)\.?\s+([A-ZÀ-ÖØ-Ý][\p{L}\'\-]+)\s+([A-ZÀ-ÖØ-Ý][\p{L}\'\-]+)/iu',
            ];
            foreach ($namePatterns as $re) {
                if (preg_match($re, $chunk, $m)) {
                    $f = trim($m[1]);
                    $parts = preg_split('/\s+/', trim($m[2])) ?: [];
                    $l = (string) end($parts);
                    if (ai_client_is_plausible_person_name($f, $l)) {
                        $out['first_name'] = $f;
                        $out['last_name'] = $l;
                        break;
                    }
                }
            }
        }

        // In a Bill To block, first plausible two-word name line
        if ($billBlock !== '' && $chunk === $billBlock && (empty($out['first_name']) || empty($out['last_name']))) {
            if (preg_match('/^\s*([A-ZÀ-ÖØ-Ý][\p{L}\'\-]+)\s+([A-ZÀ-ÖØ-Ý][\p{L}\'\-]+)\s*$/mu', $billBlock, $m)) {
                if (ai_client_is_plausible_person_name($m[1], $m[2])) {
                    $out['first_name'] = $m[1];
                    $out['last_name'] = $m[2];
                }
            }
        }
    }

    // Never use generic "first two Title Case words" on full invoice bodies — too noisy.

    if (preg_match('/(?:assigned\s*lawyer|lawyer|avocat)\s*[:\-]\s*([A-ZÀ-ÖØ-Ý][\p{L}\'\-]+\s+[A-ZÀ-ÖØ-Ý][\p{L}\'\-]+)/u', $text, $m)) {
        $parts = preg_split('/\s+/', trim($m[1])) ?: [];
        if (count($parts) >= 2 && ai_client_is_plausible_person_name($parts[0], (string) end($parts))) {
            $out['assigned_lawyer'] = trim($m[1]);
        }
    }

    // Optional LLM — off by default for uploads (remote timeouts break the chat UI).
    if ($useLlm
        && empty($out['first_name'])
        && empty($out['email'])
        && function_exists('ai_llm_is_available')
        && ai_llm_is_available($pdo)
        && function_exists('ai_llm_request')
    ) {
        try {
            $snippet = function_exists('mb_substr') ? mb_substr($text, 0, 4000) : substr($text, 0, 4000);
            $prompt = "Extract the CLIENT (Bill To) person/company for CRM registration from this document. "
                . "Ignore document titles like INVOICE/RECEIPT and ignore invoice numbers. "
                . "Return ONLY compact JSON keys: first_name, last_name, email, phone, address, company_name, username, notes, assigned_lawyer. "
                . "Empty string when unknown. No markdown.\n\nDOCUMENT:\n{$snippet}";
            $cfg = ai_llm_config($pdo);
            $cfg['timeout'] = min(20, (int) ($cfg['timeout'] ?? 20));
            $raw = ai_llm_request($cfg, 'Extract client (bill-to) fields only. Never use invoice titles as names. JSON only.', [
                ['role' => 'user', 'content' => $prompt],
            ]);
            if (is_string($raw) && preg_match('/\{[\s\S]*\}/', $raw, $jm)) {
                $json = json_decode($jm[0], true);
                if (is_array($json)) {
                    foreach (['first_name', 'last_name', 'email', 'phone', 'address', 'company_name', 'username', 'notes', 'assigned_lawyer'] as $k) {
                        $v = trim((string) ($json[$k] ?? ''));
                        if ($v !== '' && empty($out[$k])) {
                            $out[$k] = $v;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    return ai_client_sanitize_extracted_fields($out);
}

function ai_client_merge_fields(array $fields, array $incoming, bool $overwrite = true): array
{
    foreach ($incoming as $k => $v) {
        $v = trim((string) $v);
        if ($v === '') {
            continue;
        }
        $current = trim((string) ($fields[$k] ?? ''));
        if ($current === '') {
            $fields[$k] = $v;
            continue;
        }
        if (!$overwrite) {
            continue;
        }
        if ($k === 'notes' && !str_contains($current, $v)) {
            $fields[$k] = trim($current . "\n" . $v);
            continue;
        }
        $fields[$k] = $v;
    }
    return $fields;
}

/**
 * Parse user reply during intake (kv pairs, plain answers for awaiting field, free text).
 *
 * @param array<string, string> $fields
 * @return array<string, string>
 */
function ai_client_parse_user_reply(string $message, array $fields, string $awaitingField = ''): array
{
    $incoming = [];
    $map = [
        'first' => 'first_name', 'firstname' => 'first_name', 'first_name' => 'first_name', 'prenom' => 'first_name',
        'last' => 'last_name', 'lastname' => 'last_name', 'last_name' => 'last_name', 'nom' => 'last_name', 'surname' => 'last_name',
        'email' => 'email', 'mail' => 'email',
        'username' => 'username', 'user' => 'username', 'login' => 'username',
        'password' => 'password', 'pass' => 'password',
        'phone' => 'phone', 'tel' => 'phone', 'mobile' => 'phone',
        'address' => 'address', 'adresse' => 'address',
        'company' => 'company_name', 'company_name' => 'company_name',
        'lawyer' => 'assigned_lawyer', 'assigned_lawyer' => 'assigned_lawyer',
        'notes' => 'notes', 'note' => 'notes',
        'temporary_password' => 'password', 'temp_password' => 'password',
    ];
    $resolveKey = static function (string $raw) use ($map): ?string {
        $norm = strtolower(trim($raw));
        $norm = preg_replace('/[\s\-]+/', '_', $norm) ?? $norm;
        $norm = preg_replace('/_+/', '_', $norm) ?? $norm;
        $norm = trim($norm, '_');
        return $map[$norm] ?? ($map[str_replace('_', '', $norm)] ?? null);
    };

    // Known keys used to stop values that contain spaces (company, address, lawyer, notes…)
    $stopKeys = 'first_name|last_name|first\s*name|last\s*name|email|username|password|phone|address|company|company_name|lawyer|assigned_lawyer|notes|note|prenom|nom|user|login|pass|tel|mobile|mail';
    $embeddedKeyRe = '/\b(?:' . $stopKeys . ')\s*[:=]/i';

    $cleanValue = static function (string $val) use ($embeddedKeyRe): string {
        $val = trim($val, " \t\"'");
        // Cut off any accidental trailing "lawyer=…" / "notes=…" glued into the value
        if (preg_match($embeddedKeyRe, $val, $m, PREG_OFFSET_CAPTURE)) {
            $val = trim(substr($val, 0, (int) $m[0][1]));
        }
        return trim($val, " \t\"',;");
    };

    $setField = static function (string $key, string $val) use (&$incoming, $cleanValue, $embeddedKeyRe): void {
        $val = $cleanValue($val);
        if ($key === '' || $val === '') {
            return;
        }
        $prev = (string) ($incoming[$key] ?? '');
        if ($prev !== '' && !preg_match($embeddedKeyRe, $prev) && preg_match($embeddedKeyRe, $val)) {
            return;
        }
        // Prefer cleaner / longer real values
        if ($prev !== '' && preg_match($embeddedKeyRe, $prev) && !preg_match($embeddedKeyRe, $val)) {
            $incoming[$key] = $val;
            return;
        }
        $incoming[$key] = $val;
    };

    // 1) key=value pairs — values may include spaces, but stop before the next known key=
    if (preg_match_all(
        '/\b(' . $stopKeys . ')\s*[:=]\s*(?:"([^"]*)"|\'([^\']*)\'|(.*?)(?=\s+(?:' . $stopKeys . ')\s*[:=]|$))/ius',
        $message,
        $rows,
        PREG_SET_ORDER
    )) {
        foreach ($rows as $row) {
            $key = $resolveKey($row[1]);
            $val = $row[2] !== '' ? $row[2] : ($row[3] !== '' ? $row[3] : ($row[4] ?? ''));
            if ($key) {
                $setField($key, (string) $val);
            }
        }
    }

    // 2) Whole-line key=value when the line has a single pair (address/company with commas)
    foreach (preg_split('/\R/u', $message) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $pairCount = preg_match_all('/\b(?:' . $stopKeys . ')\s*[:=]/iu', $line);
        if ($pairCount !== 1) {
            // Still split multi-pair lines explicitly
            if ($pairCount > 1 && preg_match_all(
                '/\b(' . $stopKeys . ')\s*[:=]\s*(?:"([^"]*)"|\'([^\']*)\'|(.*?)(?=\s+(?:' . $stopKeys . ')\s*[:=]|$))/ius',
                $line,
                $lineRows,
                PREG_SET_ORDER
            )) {
                foreach ($lineRows as $row) {
                    $key = $resolveKey($row[1]);
                    $val = $row[2] !== '' ? $row[2] : ($row[3] !== '' ? $row[3] : ($row[4] ?? ''));
                    if ($key) {
                        $setField($key, (string) $val);
                    }
                }
            }
            continue;
        }
        if (preg_match('/^(' . $stopKeys . ')\s*[:=]\s*(.*)$/ius', $line, $m)) {
            $key = $resolveKey($m[1]);
            if (!$key) {
                continue;
            }
            $whole = $cleanValue($m[2]);
            $prev = trim((string) ($incoming[$key] ?? ''));
            if ($prev === '' || (strlen($whole) > strlen($prev) && str_starts_with($whole, $prev)) || preg_match($embeddedKeyRe, $prev)) {
                $setField($key, $whole);
            }
        }
    }

    // 3) Legacy kv helper only for missing keys, never accept embedded key junk
    $kv = ai_actions_parse_kv($message);
    foreach ($kv as $k => $v) {
        $key = $resolveKey((string) $k);
        if (!$key || !empty($incoming[$key])) {
            continue;
        }
        $val = $cleanValue((string) $v);
        if ($val !== '' && !preg_match($embeddedKeyRe, $val)) {
            $setField($key, $val);
        }
    }

    if (!isset($incoming['email'])) {
        $email = ai_actions_extract_email($message);
        if ($email) {
            $incoming['email'] = $email;
        }
    }
    if (!isset($incoming['phone'])) {
        $phone = ai_actions_extract_phone($message);
        if ($phone) {
            $incoming['phone'] = $phone;
        }
    }

    $trim = trim($message);
    $looksLikeFormat = $incoming !== [] || (bool) preg_match('/[a-z][a-z0-9_\s]*\s*[:=]/iu', $trim);

    if (!$looksLikeFormat) {
        [$f, $l] = ai_actions_extract_person_name($message, 'client');
        if ($f && $l) {
            $incoming['first_name'] = $incoming['first_name'] ?? $f;
            $incoming['last_name'] = $incoming['last_name'] ?? $l;
        }
    }

    // Only use single-field fallback when the message is NOT a format paste
    if (!$looksLikeFormat && $awaitingField !== '' && $trim !== '' && empty($incoming[$awaitingField])) {
        $onlyEmail = (bool) filter_var($trim, FILTER_VALIDATE_EMAIL);
        $onlyPhone = (bool) preg_match('/^\+?[\d\s\-()]{7,}$/', $trim);
        if ($awaitingField === 'email' && $onlyEmail) {
            $incoming['email'] = $trim;
        } elseif (in_array($awaitingField, ['first_name', 'last_name'], true) && !$onlyEmail && !$onlyPhone) {
            if (preg_match('/^([A-Za-zÀ-ÖØ-öø-ÿ\'\-]+)\s+([A-Za-zÀ-ÖØ-öø-ÿ\'\-]+(?:\s+[A-Za-zÀ-ÖØ-öø-ÿ\'\-]+)?)$/u', $trim, $m)
                && $awaitingField === 'first_name'
                && empty($fields['last_name'])
            ) {
                $incoming['first_name'] = $m[1];
                $incoming['last_name'] = $m[2];
            } else {
                $incoming[$awaitingField] = $trim;
            }
        } elseif (in_array($awaitingField, [
            'password', 'username', 'phone', 'address', 'company_name', 'notes', 'assigned_lawyer',
        ], true)) {
            $incoming[$awaitingField] = $trim;
        }
    }

    if (!$looksLikeFormat && empty($fields['first_name']) && empty($incoming['first_name'])
        && preg_match('/^([A-Za-zÀ-ÖØ-öø-ÿ\'\-]+)\s+([A-Za-zÀ-ÖØ-öø-ÿ\'\-]+)$/u', $trim, $m)
    ) {
        $incoming['first_name'] = $m[1];
        $incoming['last_name'] = $m[2];
    }

    // Final cleanup of any still-greedy values
    foreach (array_keys($incoming) as $nk) {
        $cleaned = $cleanValue((string) $incoming[$nk]);
        if ($cleaned === '') {
            unset($incoming[$nk]);
        } else {
            $incoming[$nk] = $cleaned;
        }
    }

    return $incoming;
}

/**
 * @param array<string, string> $fields
 * @return list<string>
 */
function ai_client_missing_required(array $fields): array
{
    $missing = [];
    foreach (ai_client_required_fields() as $key) {
        if (trim((string) ($fields[$key] ?? '')) === '') {
            $missing[] = $key;
        }
    }
    return $missing;
}

function ai_client_field_label(string $key): string
{
    return match ($key) {
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'email' => 'Email',
        'username' => 'Username',
        'password' => 'Temporary password',
        'phone' => 'Phone',
        'address' => 'Address',
        'company_name' => 'Company',
        'assigned_lawyer' => 'Assigned lawyer',
        'notes' => 'Notes',
        default => $key,
    };
}

/**
 * Short how-to for the field currently being asked.
 *
 * @return array{example:string,how:string,also:string}
 */
function ai_client_field_guide(string $key): array
{
    return match ($key) {
        'first_name' => [
            'example' => 'Jean',
            'how' => 'Reply with the first name only — example: Jean',
            'also' => 'You can also send first and last together: Jean Dupont',
        ],
        'last_name' => [
            'example' => 'Dupont',
            'how' => 'Reply with the last name only — example: Dupont',
            'also' => 'Or use: last_name=Dupont',
        ],
        'email' => [
            'example' => 'jean@example.com',
            'how' => 'Reply with a full email — example: jean@example.com',
            'also' => 'Or use: email=jean@example.com',
        ],
        'username' => [
            'example' => 'jean.dupont',
            'how' => 'Reply with a login username — example: jean.dupont',
            'also' => 'Letters, numbers, dots or underscores work.',
        ],
        'password' => [
            'example' => 'Temp123!',
            'how' => 'Reply with a temporary password — example: Temp123!',
            'also' => 'The client can change it after first login.',
        ],
        'phone' => [
            'example' => '51234567',
            'how' => 'Reply with a phone number — example: 51234567',
            'also' => 'Or use: phone=51234567',
        ],
        'address' => [
            'example' => '12 Royal Road, Port Louis',
            'how' => 'Reply with the full address in one message',
            'also' => 'Or use: address=12 Royal Road, Port Louis',
        ],
        'company_name' => [
            'example' => 'Acme Ltd',
            'how' => 'Reply with the company name, or type skip',
            'also' => 'Or use: company=Acme Ltd',
        ],
        'assigned_lawyer' => [
            'example' => 'Marie Laurent',
            'how' => 'Reply with the lawyer full name, or type skip',
            'also' => 'Or use: lawyer=Marie Laurent',
        ],
        'notes' => [
            'example' => 'Preferred contact: WhatsApp',
            'how' => 'Reply with any notes, or type skip',
            'also' => 'Or use: notes=…',
        ],
        default => [
            'example' => '',
            'how' => 'Reply with the value for this field',
            'also' => '',
        ],
    };
}

/**
 * Blank / prefilled paste template for admin client intake.
 *
 * @param array<string, string> $fields
 */
function ai_client_intake_format_template(array $fields = []): string
{
    $val = static function (string $key) use ($fields): string {
        if ($key === 'password' && trim((string) ($fields[$key] ?? '')) !== '') {
            return (string) $fields[$key];
        }
        return trim((string) ($fields[$key] ?? ''));
    };

    $lines = [
        'first_name=' . $val('first_name'),
        'last_name=' . $val('last_name'),
        'email=' . $val('email'),
        'username=' . $val('username'),
        'password=' . $val('password'),
        'phone=' . $val('phone'),
        'address=' . $val('address'),
        'company=' . $val('company_name'),
        'lawyer=' . $val('assigned_lawyer'),
        'notes=' . $val('notes'),
    ];
    return implode("\n", $lines);
}

/**
 * Filled example admins can copy and edit.
 */
function ai_client_intake_format_example(): string
{
    return implode("\n", [
        'first_name=Jean',
        'last_name=Dupont',
        'email=jean@example.com',
        'username=jean.dupont',
        'password=Temp123!',
        'phone=51234567',
        'address=12 Royal Road, Port Louis',
        'company=Acme Ltd',
        'lawyer=',
        'notes=',
    ]);
}

/**
 * @param array<string, mixed> $draft
 */
function ai_client_intake_prompt(array &$draft, string $extraNote = ''): string
{
    $fields = $draft['fields'] ?? [];
    $missing = ai_client_missing_required($fields);
    $required = ai_client_required_fields();
    $items = [];
    foreach (array_merge($required, ai_client_optional_fields()) as $key) {
        $val = trim((string) ($fields[$key] ?? ''));
        $isReq = in_array($key, $required, true);
        $display = $val;
        if ($key === 'password' && $val !== '') {
            $display = 'Set';
        }
        $items[] = [
            'key' => $key,
            'label' => ai_client_field_label($key),
            'value' => $display,
            'required' => $isReq,
            'status' => $val !== '' ? 'done' : 'missing',
        ];
    }
    $doneCount = count(array_filter($items, static fn(array $item): bool => $item['status'] === 'done'));

    if ($missing) {
        $draft['awaiting'] = 'details';
        $draft['awaiting_field'] = '';
    } else {
        $draft['awaiting'] = 'confirm';
        $draft['awaiting_field'] = '';
    }

    $payload = [
        'type' => 'client_intake',
        'title' => 'New client intake',
        'note' => $extraNote,
        'docs' => array_values(array_map('strval', $draft['doc_names'] ?? [])),
        'items' => $items,
        'progress' => [
            'done' => $doneCount,
            'total' => count($items),
            'required_left' => count($missing),
        ],
        'ready' => $missing === [],
        'format' => $doneCount > 0
            ? ai_client_intake_format_template($fields)
            : ai_client_intake_format_example(),
        'example' => ai_client_intake_format_example(),
        'missing_labels' => array_map('ai_client_field_label', $missing),
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($extraNote !== '') {
        $lead = $extraNote;
    } elseif ($missing) {
        $lead = 'Copy the format below, fill in the details, and paste it back in one message.';
    } else {
        $lead = 'All required fields are ready. Review the details, then reply **confirm** to create the client.';
    }

    return $lead . "\n\n[[AI_INTAKE_CARD]]\n" . $json . "\n[[/AI_INTAKE_CARD]]";
}

/**
 * @param array<int, array<string, mixed>> $attachments
 */
function ai_action_create_client(
    PDO $pdo,
    array $user,
    string $portal,
    string $message,
    string $q,
    array $attachments = [],
    int $sessionId = 0
): string {
    if (!ai_actions_can_admin($portal, $user)) {
        return 'Only admin/staff can create clients from the AI assistant.';
    }
    if ($sessionId <= 0) {
        return 'Open an AI chat session first, then say **create client**.';
    }

    $draft = ai_client_draft_get($sessionId);
    $startedFresh = false;
    if (!$draft || ($draft['type'] ?? '') !== 'client') {
        $draft = ai_client_draft_blank();
        $startedFresh = true;
    }

    $fields = $draft['fields'];
    $extraNote = '';
    $fromMsg = [];

    $isConfirm = ai_actions_wants($q, ['confirm', 'yes create', 'create now', 'save client', 'oui', 'confirmer', 'go ahead']);
    $isCancel = ai_actions_wants($q, ['cancel', 'abort', 'annuler', 'stop']);
    if ($isCancel && !ai_actions_wants($q, ['cancel appointment', 'cancel meeting'])) {
        ai_client_draft_clear($sessionId);
        return 'Client intake cancelled. Nothing was created.';
    }

    // Parse typed answers first (except pure confirm).
    $awaitingField = (string) ($draft['awaiting_field'] ?? '');
    if (!$isConfirm || $startedFresh) {
        $msgForParse = $message;
        $isFormatPaste = (bool) preg_match('/[a-z][a-z0-9_\s]*\s*[:=]/iu', $message);
        // Avoid treating command words as a person name — but never rewrite a format paste.
        if (!$isFormatPaste) {
            $msgForParse = preg_replace('/\b(create|new|add|register|onboard|client|from|this|document|documents|extract|intake|please|a|the)\b/iu', ' ', $msgForParse) ?? $msgForParse;
        }
        $fromMsg = ai_client_parse_user_reply($msgForParse, $fields, $awaitingField);
        $fromText = $isFormatPaste ? [] : ai_client_extract_fields_from_text($msgForParse, $pdo);
        $fields = ai_client_merge_fields($fields, $fromText, true);
        $fields = ai_client_merge_fields($fields, $fromMsg, true);
    }

    // Documents: resolve system invoice/receipt OR parse text carefully.
    if ($attachments) {
        $pack = ai_client_extract_from_attachments($pdo, $attachments);
        $extracted = $pack['fields'];
        $existingClient = $pack['existing_client'];
        $names = [];
        foreach ($attachments as $att) {
            $names[] = (string) ($att['file_name'] ?? 'file');
        }
        $draft['doc_names'] = array_values(array_unique(array_merge($draft['doc_names'] ?? [], $names)));

        if ($existingClient || (is_array($pack['document'] ?? null) && !empty($pack['document']['number']))) {
            ai_client_draft_clear($sessionId);
            $doc = $pack['document'] ?? null;
            if (is_array($doc) && !empty($doc['number'])) {
                return ai_document_format_extract_reply($doc, is_array($existingClient) ? $existingClient : [], $portal);
            }
            $cid = (int) ($existingClient['id'] ?? 0);
            $url = ai_actions_portal_url('admin', 'clients.php?action=view&id=' . $cid);
            return $pack['note'] . "\n\n"
                . "This person is **already registered** — a new client was not created.\n"
                . "• Name: **" . full_name($existingClient) . "**\n"
                . "• Email: " . ($existingClient['email'] ?? '—') . "\n"
                . "• Phone: " . ($existingClient['phone'] ?: '—') . "\n\n"
                . ai_actions_md_link('Open client profile', $url) . "\n\n"
                . "To register someone new, say **create client** and type their details (or attach an intake form, not an existing invoice).";
        }

        $before = $fields;
        $fields = ai_client_merge_fields($fields, $extracted, true);
        foreach (['first_name', 'last_name', 'username', 'phone'] as $nk) {
            $badPrev = in_array(strtolower((string) ($before[$nk] ?? '')), ['from', 'this', 'document', 'client', 'new', 'create', 'invoice', 'extract', 'details'], true)
                || (($nk === 'phone') && !ai_client_is_plausible_phone((string) ($before[$nk] ?? '')))
                || (($nk === 'first_name' || $nk === 'last_name') && ai_client_is_blocked_name_token((string) ($before[$nk] ?? '')));
            if (!empty($extracted[$nk]) && $badPrev) {
                $fields[$nk] = $extracted[$nk];
            }
        }
        $fields = ai_client_sanitize_extracted_fields($fields);
        if ($pack['note'] !== '') {
            $extraNote = $pack['note'];
        }
    }

    // Clear any garbage already sitting in the draft from earlier bad extracts
    $fields = ai_client_sanitize_extracted_fields($fields);
    if (!empty($fields['username']) && preg_match('/invoice|inv\.|receipt|rcp\./i', (string) $fields['username'])) {
        unset($fields['username']);
    }

    if (!$isConfirm || $startedFresh) {
        // Auto username/password only for plausible real names
        if (ai_client_is_plausible_person_name((string) $fields['first_name'], (string) $fields['last_name']) && trim($fields['username']) === '') {
            $fields['username'] = ai_actions_unique_username($pdo, $fields['first_name'], $fields['last_name']);
            if ($extraNote === '') {
                $extraNote = 'Suggested username generated from the name (you can change it).';
            }
        }
        if (trim($fields['password']) === '' && ai_client_is_plausible_person_name((string) ($fields['first_name'] ?? ''), (string) ($fields['last_name'] ?? '')) && trim((string) ($fields['email'] ?? '')) !== '') {
            $fields['password'] = 'Temp' . random_int(1000, 9999) . '!';
        }
    }

    $draft['fields'] = $fields;
    $missing = ai_client_missing_required($fields);

    if ($isConfirm && !$missing) {
        return ai_client_finalize_create($pdo, $user, $sessionId, $draft);
    }
    if ($isConfirm && $missing) {
        $extraNote = 'Cannot confirm yet — still missing: ' . implode(', ', array_map('ai_client_field_label', $missing)) . '.';
    }

    if ($startedFresh && !$attachments && trim($message) !== '' && ai_actions_wants($q, ['create client', 'new client', 'add client', 'nouveau client']) && !$fromMsg && empty(ai_client_extract_fields_from_text($message, $pdo))) {
        $extraNote = 'Paste the format below with the client details (required lines first). You can also attach an ID or intake form.';
    } elseif (
        $extraNote === ''
        && !$startedFresh
        && $missing
        && $fromMsg
    ) {
        $still = array_map('ai_client_field_label', $missing);
        $extraNote = 'Updated. Still missing: **' . implode(', ', $still) . '**. Fill those lines in the format and paste again.';
    }

    $reply = ai_client_intake_prompt($draft, $extraNote);
    ai_client_draft_set($sessionId, $draft);

    return $reply;
}

/**
 * @param array<string, mixed> $draft
 */
function ai_client_finalize_create(PDO $pdo, array $user, int $sessionId, array $draft): string
{
    $fields = $draft['fields'];
    $first = trim((string) $fields['first_name']);
    $last = trim((string) $fields['last_name']);
    $email = trim((string) $fields['email']);
    $username = trim((string) $fields['username']);
    $passwordPlain = trim((string) $fields['password']);
    $phone = trim((string) ($fields['phone'] ?? '')) ?: null;
    $address = trim((string) ($fields['address'] ?? '')) ?: null;
    $company = trim((string) ($fields['company_name'] ?? '')) ?: null;
    $notes = trim((string) ($fields['notes'] ?? '')) ?: null;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $draft['awaiting'] = 'details';
        $draft['awaiting_field'] = 'email';
        ai_client_draft_set($sessionId, $draft);
        return "⚠️ `{$email}` is not a valid email. Please send a correct **Email**.";
    }

    $existing = ai_actions_find_user_by_name($pdo, 'client', $first, $last, $email);
    if ($existing) {
        ai_client_draft_clear($sessionId);
        $url = ai_actions_portal_url('admin', 'clients.php?action=view&id=' . (int) $existing['id']);
        return 'A client with that name/email already exists: **' . full_name($existing) . '**. '
            . ai_actions_md_link('Open client', $url);
    }

    $userCheck = $pdo->prepare('SELECT id FROM users WHERE username=? OR email=? LIMIT 1');
    $userCheck->execute([$username, $email]);
    if ($userCheck->fetchColumn()) {
        $draft['fields']['username'] = ai_actions_unique_username($pdo, $first, $last);
        $draft['awaiting'] = 'confirm';
        ai_client_draft_set($sessionId, $draft);
        return '⚠️ That username or email is already taken. I suggested username `'
            . $draft['fields']['username'] . '`. Reply **confirm** to create, or send a new `username=` / `email=`.';
    }

    $lawyerId = null;
    if (!empty($fields['assigned_lawyer_id'])) {
        $lawyerId = (int) $fields['assigned_lawyer_id'];
    } elseif (!empty($fields['assigned_lawyer'])) {
        $parts = preg_split('/\s+/', trim((string) $fields['assigned_lawyer'])) ?: [];
        $law = ai_actions_find_user_by_name($pdo, 'lawyer', $parts[0] ?? null, $parts[1] ?? null);
        $lawyerId = $law ? (int) $law['id'] : null;
    }

    $password = password_hash($passwordPlain, PASSWORD_DEFAULT);
    $pdo->prepare(
        'INSERT INTO users (role, first_name, last_name, username, email, password, phone, address, company_name, assigned_lawyer_id, notes, is_active)
         VALUES ("client",?,?,?,?,?,?,?,?,?,?,1)'
    )->execute([
        $first, $last, $username, $email, $password, $phone, $address, $company, $lawyerId, $notes,
    ]);
    $newId = (int) $pdo->lastInsertId();
    log_activity($pdo, (int) $user['id'], 'create', 'client', $newId, 'Created client via AI intake');
    create_notification($pdo, (int) $user['id'], 'notify.client_created', $first . ' ' . $last . ' added.', 'info', 'clients.php?action=view&id=' . $newId, (int) $user['id']);
    if ($lawyerId) {
        create_notification($pdo, $lawyerId, 'notify.client_assigned', 'Client ' . $first . ' ' . $last . ' assigned to you.', 'case', '../lawyer/clients.php', (int) $user['id']);
    }

    ai_client_draft_clear($sessionId);
    $url = ai_actions_portal_url('admin', 'clients.php?action=view&id=' . $newId);
    return "✅ Client created successfully.\n\n"
        . "• Name: **{$first} {$last}**\n"
        . "• Email: {$email}\n"
        . "• Username: `{$username}`\n"
        . "• Temporary password: `{$passwordPlain}`\n"
        . ($phone ? "• Phone: {$phone}\n" : '')
        . ($company ? "• Company: {$company}\n" : '')
        . ($address ? "• Address: {$address}\n" : '')
        . ($lawyerId ? "• Assigned lawyer ID: {$lawyerId}\n" : '')
        . "\n" . ai_actions_md_link('Open client profile', $url);
}
