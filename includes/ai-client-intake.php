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
        return true;
    }
    $trim = trim($message);
    if ($trim === '') {
        return false;
    }

    if (ai_actions_wants($q, [
        'confirm', 'yes create', 'create now', 'save client', 'oui', 'confirmer', 'go ahead',
        'create client', 'new client', 'add client', 'extract client', 'extract details', 'register client',
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
 * @return array{fields: array<string,string>, note: string, existing_client: ?array}
 */
function ai_client_extract_from_attachments(PDO $pdo, array $attachments): array
{
    $fields = [];
    $notes = [];
    $existing = null;

    $haystack = '';
    foreach ($attachments as $att) {
        $haystack .= ' ' . (string) ($att['file_name'] ?? '') . "\n" . (string) ($att['text'] ?? '');
    }

    $system = ai_client_lookup_system_document($pdo, $haystack);
    if ($system) {
        $existing = $system['user'];
        $fields = array_merge($fields, $system['fields']);
        $notes[] = $system['note'];
        // System invoice/receipt match is enough — skip slow LLM extraction.
        return [
            'fields' => ai_client_sanitize_extracted_fields($fields),
            'note' => implode(' ', $notes),
            'existing_client' => $existing,
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
    ];
}

/**
 * Resolve client from Lexora invoice/receipt numbers in filename or text.
 *
 * @return array{user:array<string,mixed>,fields:array<string,string>,note:string}|null
 */
function ai_client_lookup_system_document(PDO $pdo, string $haystack): ?array
{
    $invoiceNo = null;
    if (preg_match('/\b(INV[- ]?\d{4}[- ]?[A-Z0-9]+)\b/i', $haystack, $m)) {
        $invoiceNo = strtoupper(preg_replace('/\s+/', '', $m[1]) ?? $m[1]);
        $invoiceNo = preg_replace('/^INV-?/', 'INV-', $invoiceNo) ?? $invoiceNo;
    }
    $receiptNo = null;
    if (preg_match('/\b(RCP[- ]?\d{4}[- ]?[A-Z0-9]+)\b/i', $haystack, $m)) {
        $receiptNo = strtoupper(preg_replace('/\s+/', '', $m[1]) ?? $m[1]);
        $receiptNo = preg_replace('/^RCP-?/', 'RCP-', $receiptNo) ?? $receiptNo;
    }

    $clientId = 0;
    $ref = '';
    try {
        if ($invoiceNo) {
            $stmt = $pdo->prepare('SELECT client_id, invoice_number FROM invoices WHERE UPPER(invoice_number) = ? LIMIT 1');
            $stmt->execute([$invoiceNo]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $stmt = $pdo->prepare("SELECT client_id, invoice_number FROM invoices WHERE REPLACE(UPPER(invoice_number), '-', '') = ? LIMIT 1");
                $stmt->execute([str_replace('-', '', $invoiceNo)]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            if ($row) {
                $clientId = (int) $row['client_id'];
                $ref = 'invoice ' . $row['invoice_number'];
            }
        }
        if ($clientId <= 0 && $receiptNo) {
            $stmt = $pdo->prepare('SELECT client_id, receipt_number FROM payments WHERE UPPER(receipt_number) = ? LIMIT 1');
            $stmt->execute([$receiptNo]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $clientId = (int) $row['client_id'];
                $ref = 'receipt ' . ($row['receipt_number'] ?? '');
            }
        }
        if ($clientId <= 0) {
            return null;
        }
        $uStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'client' LIMIT 1");
        $uStmt->execute([$clientId]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return null;
        }
    } catch (Throwable $e) {
        return null;
    }

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

    return [
        'user' => $user,
        'fields' => ai_client_sanitize_extracted_fields($fields),
        'note' => '📄 Matched system ' . $ref . ' → client **' . trim($fields['first_name'] . ' ' . $fields['last_name']) . '** (already in the system).',
    ];
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
    if (!empty($fields['first_name']) || !empty($fields['last_name'])) {
        $f = (string) ($fields['first_name'] ?? '');
        $l = (string) ($fields['last_name'] ?? '');
        if (!ai_client_is_plausible_person_name($f, $l)) {
            unset($fields['first_name'], $fields['last_name']);
            // Bad auto-username from bogus names
            if (!empty($fields['username']) && preg_match('/invoice|inv\.|receipt|rcp\./i', (string) $fields['username'])) {
                unset($fields['username']);
            }
        }
    }
    if (!empty($fields['phone']) && !ai_client_is_plausible_phone((string) $fields['phone'])) {
        unset($fields['phone']);
    }
    if (!empty($fields['email']) && !filter_var((string) $fields['email'], FILTER_VALIDATE_EMAIL)) {
        unset($fields['email']);
    }
    foreach (['company_name', 'address', 'notes', 'assigned_lawyer'] as $k) {
        if (!empty($fields[$k]) && ai_client_is_blocked_name_token((string) $fields[$k])) {
            unset($fields[$k]);
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
    $kv = ai_actions_parse_kv($message);
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
    ];
    foreach ($kv as $k => $v) {
        $key = $map[strtolower($k)] ?? null;
        if ($key) {
            $incoming[$key] = $v;
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

    [$f, $l] = ai_actions_extract_person_name($message, 'client');
    if ($f && $l) {
        $incoming['first_name'] = $incoming['first_name'] ?? $f;
        $incoming['last_name'] = $incoming['last_name'] ?? $l;
    }

    // Single-field answer when wizard asked for one thing
    $trim = trim($message);
    if ($awaitingField !== '' && $trim !== '' && !str_contains($trim, "\n") && count($incoming) === 0) {
        if ($awaitingField === 'email' && filter_var($trim, FILTER_VALIDATE_EMAIL)) {
            $incoming['email'] = $trim;
        } elseif ($awaitingField === 'password' || $awaitingField === 'username' || $awaitingField === 'phone'
            || $awaitingField === 'address' || $awaitingField === 'company_name' || $awaitingField === 'notes'
            || $awaitingField === 'assigned_lawyer' || $awaitingField === 'first_name' || $awaitingField === 'last_name') {
            $incoming[$awaitingField] = $trim;
        }
    }

    // "Jean Dupont" alone when both names missing
    if (empty($fields['first_name']) && empty($incoming['first_name']) && preg_match('/^([A-Za-zÀ-ÖØ-öø-ÿ\'\-]+)\s+([A-Za-zÀ-ÖØ-öø-ÿ\'\-]+)$/u', $trim, $m)) {
        $incoming['first_name'] = $m[1];
        $incoming['last_name'] = $m[2];
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
 * @param array<string, mixed> $draft
 */
function ai_client_intake_prompt(array $draft, string $extraNote = ''): string
{
    $fields = $draft['fields'] ?? [];
    $missing = ai_client_missing_required($fields);
    $lines = ["👤 **New client intake**"];
    if ($extraNote !== '') {
        $lines[] = $extraNote;
    }
    if (!empty($draft['doc_names'])) {
        $lines[] = 'Documents used: ' . implode(', ', array_map('strval', $draft['doc_names']));
    }
    $lines[] = '';
    $lines[] = '**Collected so far**';
    foreach (array_merge(ai_client_required_fields(), ai_client_optional_fields()) as $key) {
        $val = trim((string) ($fields[$key] ?? ''));
        $mark = $val !== '' ? '✅' : (in_array($key, ai_client_required_fields(), true) ? '❌' : '○');
        $lines[] = "{$mark} " . ai_client_field_label($key) . ($val !== '' ? ": {$val}" : ' — missing');
    }

    if ($missing) {
        $next = $missing[0];
        $lines[] = '';
        $lines[] = '**Required next:** please send **' . ai_client_field_label($next) . '**.';
        $lines[] = 'You can reply with just the value, or several fields like:';
        $lines[] = '`first_name=Jean last_name=Dupont email=jean@example.com phone=51234567 username=jean.dupont password=Temp123!`';
        $lines[] = 'Attach any ID / intake form / letter and I will extract details automatically.';
        $lines[] = 'Say **cancel** to abort.';
        $draft['awaiting'] = 'details';
        $draft['awaiting_field'] = $next;
    } else {
        $lines[] = '';
        $lines[] = 'All required fields are ready.';
        $lines[] = 'Reply **confirm** to create this client, or send corrections (e.g. `phone=5…`).';
        $lines[] = 'Say **cancel** to abort.';
        $draft['awaiting'] = 'confirm';
        $draft['awaiting_field'] = '';
    }

    return implode("\n", $lines);
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
    if (!$isConfirm || $startedFresh) {
        $awaitingField = (string) ($draft['awaiting_field'] ?? '');
        $msgForParse = $message;
        // Avoid treating command words as a person name.
        $msgForParse = preg_replace('/\b(create|new|add|register|onboard|client|from|this|document|documents|extract|intake|please|a|the)\b/iu', ' ', $msgForParse) ?? $msgForParse;
        $fromMsg = ai_client_parse_user_reply($msgForParse, $fields, $awaitingField);
        $fromText = ai_client_extract_fields_from_text($msgForParse, $pdo);
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

        if ($existingClient) {
            ai_client_draft_clear($sessionId);
            $cid = (int) $existingClient['id'];
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
    if (!empty($fields['username']) && (empty($fields['first_name']) || empty($fields['last_name']))) {
        unset($fields['username']);
    }
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
        $extraNote = 'Let\'s register a new client. I need every required field. You can type them or attach a document (ID, intake form, letter) and I will extract what I can.';
    }

    $reply = ai_client_intake_prompt($draft, $extraNote);
    // Persist awaiting_field from prompt helper
    $missing2 = ai_client_missing_required($draft['fields']);
    $draft['awaiting'] = $missing2 ? 'details' : 'confirm';
    $draft['awaiting_field'] = $missing2[0] ?? '';
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
