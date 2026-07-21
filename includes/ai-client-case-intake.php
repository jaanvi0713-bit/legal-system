<?php
/**
 * Guided AI wizard: create new client, then case (one question at a time).
 * Included from ai-actions.php / ai-client-intake.php
 */

/** @return list<string> */
function ai_cc_steps(): array
{
    return ['full_name', 'email', 'phone', 'address', 'case_title', 'service', 'description'];
}

function ai_cc_draft_blank(): array
{
    return [
        'type' => 'client_case',
        'fields' => [
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            'address' => '',
            'street' => '',
            'city' => '',
            'region' => '',
            'postal' => '',
            'country' => '',
            'case_title' => '',
            'service' => '',
            'description' => '',
            'username' => '',
            'password' => '',
        ],
        'awaiting' => 'details',
        'awaiting_field' => 'full_name',
        'step_index' => 0,
        'created_at' => time(),
        'updated_at' => time(),
    ];
}

function ai_cc_intro(): string
{
    return "Let's set up the new client first, then the case. All fields are required.";
}

function ai_cc_question(string $step): string
{
    return match ($step) {
        'full_name' => "What is the client's full legal name? (first and last name required)",
        'email' => "What is the client's email address?",
        'phone' => "What is the client's phone number? (include country code if international)",
        'address' => "What is the client's full postal address? Enter as: street, city, state/region, postal code, country",
        'case_title' => "What case title should I use? (e.g. Smith Property Transfer)",
        'service' => "What type of service is this? (e.g. deed, jurat, POA, notarization)",
        'description' => "Add case notes or description (what the client needs done). Type **none** if not applicable.",
        default => 'Please continue.',
    };
}

/**
 * @param array<string, mixed> $draft
 */
function ai_cc_next_missing(array $draft): ?string
{
    $f = $draft['fields'] ?? [];
    foreach (ai_cc_steps() as $step) {
        if ($step === 'full_name') {
            if (trim((string) ($f['first_name'] ?? '')) === '' || trim((string) ($f['last_name'] ?? '')) === '') {
                return 'full_name';
            }
            continue;
        }
        if ($step === 'description') {
            if (empty($draft['_description_set'])) {
                return 'description';
            }
            continue;
        }
        $key = $step === 'case_title' ? 'case_title' : ($step === 'service' ? 'service' : $step);
        if (trim((string) ($f[$key] ?? '')) === '') {
            return $step;
        }
    }
    return null;
}

/**
 * @param array<string, mixed> $draft
 */
function ai_cc_prompt(array $draft, string $bridge = ''): string
{
    $next = ai_cc_next_missing($draft);
    if ($next === null) {
        return ai_cc_review_message($draft);
    }

    $lines = [];
    if ($bridge !== '') {
        $lines[] = $bridge;
        $lines[] = '';
    }
    // After address complete, switch to case section
    if (in_array($next, ['case_title', 'service', 'description'], true)
        && trim((string) ($draft['fields']['phone'] ?? '')) !== ''
        && trim((string) ($draft['fields']['address'] ?? '')) !== '') {
        if ($next === 'case_title' || $bridge === '') {
            // Show case bridge once when entering case steps
            if ($next === 'case_title') {
                $lines = ["Client details complete. Now for the case:", '', ai_cc_question($next)];
                $draft['awaiting_field'] = $next;
                $draft['awaiting'] = 'details';
                return implode("\n", $lines);
            }
        }
    }

    $lines[] = ai_cc_intro();
    $lines[] = '';
    $lines[] = ai_cc_question($next);
    $draft['awaiting_field'] = $next;
    $draft['awaiting'] = 'details';
    return implode("\n", $lines);
}

/**
 * @param array<string, mixed> $draft
 */
function ai_cc_review_message(array $draft): string
{
    $f = $draft['fields'];
    $name = trim(($f['first_name'] ?? '') . ' ' . ($f['last_name'] ?? ''));
    $payload = [
        'type' => 'client_case',
        'title' => 'Create Client And Case',
        'fields' => [
            'name' => $name,
            'email' => (string) ($f['email'] ?? ''),
            'phone' => (string) ($f['phone'] ?? ''),
            'address' => (string) ($f['address'] ?? ''),
            'postal' => (string) ($f['postal'] ?? ''),
            'country' => (string) ($f['country'] ?? ''),
            'case_title' => (string) ($f['case_title'] ?? ''),
            'service' => (string) ($f['service'] ?? ''),
            'description' => (string) (($f['description'] ?? '') !== '' ? $f['description'] : '-'),
        ],
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $draft['awaiting'] = 'confirm';
    $draft['awaiting_field'] = '';

    return "Review the new client and case below. Edit any field in the draft or say `change country to UK` before you click **Confirm**.\n\n"
        . "[[AI_DRAFT_CARD]]\n" . $json . "\n[[/AI_DRAFT_CARD]]";
}

function ai_cc_parse_address(string $raw): array
{
    $raw = trim($raw);
    $parts = preg_split('/\s*,\s*/', $raw) ?: [];
    $parts = array_values(array_filter(array_map('trim', $parts), static fn($p) => $p !== ''));
    $out = [
        'street' => '',
        'city' => '',
        'region' => '',
        'postal' => '',
        'country' => '',
        'address' => $raw,
    ];
    if (count($parts) >= 5) {
        $out['street'] = $parts[0];
        $out['city'] = $parts[1];
        $out['region'] = $parts[2];
        $out['postal'] = $parts[3];
        $out['country'] = $parts[4];
        $out['address'] = implode(', ', $parts);
    } elseif (count($parts) === 4) {
        $out['street'] = $parts[0];
        $out['city'] = $parts[1];
        $out['postal'] = $parts[2];
        $out['country'] = $parts[3];
        $out['address'] = implode(', ', $parts);
    } elseif (count($parts) >= 2) {
        $out['street'] = $parts[0];
        $out['city'] = $parts[1] ?? '';
        $out['country'] = $parts[count($parts) - 1] ?? '';
        $out['address'] = implode(', ', $parts);
    }
    return $out;
}

function ai_cc_parse_full_name(string $raw): array
{
    $raw = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);
    $parts = preg_split('/\s+/', $raw) ?: [];
    if (count($parts) < 2) {
        return ['first_name' => '', 'last_name' => ''];
    }
    $first = array_shift($parts);
    $last = implode(' ', $parts);
    return ['first_name' => (string) $first, 'last_name' => $last];
}

/**
 * Apply a free-text answer for the current step.
 *
 * @param array<string, mixed> $draft
 * @return array{ok:bool,error:?string,draft:array}
 */
function ai_cc_apply_answer(array $draft, string $message, string $q): array
{
    $step = (string) ($draft['awaiting_field'] ?? ai_cc_next_missing($draft) ?? '');
    $trim = trim($message);
    $f = $draft['fields'];

    // Inline corrections: "change country to UK", "email=x@y.com"
    if (preg_match('/\bchange\s+(name|email|phone|address|postal|country|case\s*title|title|service|description)\s+to\s+(.+)$/iu', $trim, $m)) {
        $key = strtolower(str_replace(' ', '_', trim($m[1])));
        $val = trim($m[2], " \t\"'");
        if ($key === 'title') {
            $key = 'case_title';
        }
        if ($key === 'name') {
            $names = ai_cc_parse_full_name($val);
            if ($names['first_name'] === '') {
                return ['ok' => false, 'error' => 'Please provide first and last name.', 'draft' => $draft];
            }
            $f['first_name'] = $names['first_name'];
            $f['last_name'] = $names['last_name'];
        } elseif ($key === 'address') {
            $f = array_merge($f, ai_cc_parse_address($val));
        } else {
            $f[$key] = $val;
            if ($key === 'description') {
                $draft['_description_set'] = true;
            }
        }
        $draft['fields'] = $f;
        return ['ok' => true, 'error' => null, 'draft' => $draft];
    }

    if (preg_match('/\b(email|phone|postal|country|case_title|title|service|description|address)\s*=\s*(.+)$/iu', $trim, $m)) {
        $key = strtolower($m[1]);
        $val = trim($m[2], " \t\"'");
        if ($key === 'title') {
            $key = 'case_title';
        }
        if ($key === 'address') {
            $f = array_merge($f, ai_cc_parse_address($val));
        } else {
            $f[$key] = $val;
            if ($key === 'description') {
                $draft['_description_set'] = true;
            }
        }
        $draft['fields'] = $f;
        return ['ok' => true, 'error' => null, 'draft' => $draft];
    }

    if ($step === '' || ($draft['awaiting'] ?? '') === 'confirm') {
        // Allow field patches while on review
        $draft['fields'] = $f;
        return ['ok' => true, 'error' => null, 'draft' => $draft];
    }

    switch ($step) {
        case 'full_name':
            $names = ai_cc_parse_full_name($trim);
            if ($names['first_name'] === '' || $names['last_name'] === '') {
                return ['ok' => false, 'error' => 'Please send the full legal name with **first and last** name (e.g. Tom Winston).', 'draft' => $draft];
            }
            $f['first_name'] = $names['first_name'];
            $f['last_name'] = $names['last_name'];
            break;
        case 'email':
            if (!filter_var($trim, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'error' => 'That does not look like a valid email. Please try again.', 'draft' => $draft];
            }
            $f['email'] = strtolower($trim);
            break;
        case 'phone':
            $phone = preg_replace('/[^\d+()\-\s]/', '', $trim) ?? $trim;
            if (strlen(preg_replace('/\D/', '', $phone) ?? '') < 6) {
                return ['ok' => false, 'error' => 'Please send a valid phone number.', 'draft' => $draft];
            }
            $f['phone'] = trim($phone);
            break;
        case 'address':
            if (strlen($trim) < 5 || substr_count($trim, ',') < 1) {
                return ['ok' => false, 'error' => 'Please enter address as: street, city, state/region, postal code, country', 'draft' => $draft];
            }
            $f = array_merge($f, ai_cc_parse_address($trim));
            break;
        case 'case_title':
            if (strlen($trim) < 2) {
                return ['ok' => false, 'error' => 'Please provide a case title.', 'draft' => $draft];
            }
            $f['case_title'] = $trim;
            break;
        case 'service':
            if (strlen($trim) < 2) {
                return ['ok' => false, 'error' => 'Please provide the service type (e.g. deed, jurat, POA).', 'draft' => $draft];
            }
            $f['service'] = $trim;
            break;
        case 'description':
            if (preg_match('/^(none|n\/a|na|-)$/i', $trim)) {
                $f['description'] = '';
            } else {
                $f['description'] = $trim;
            }
            $draft['_description_set'] = true;
            break;
        default:
            break;
    }

    $draft['fields'] = $f;
    return ['ok' => true, 'error' => null, 'draft' => $draft];
}

/**
 * Apply draft-card save payload (from Confirm / Save changes).
 *
 * @param array<string, mixed> $draft
 * @param array<string, mixed> $incoming
 */
function ai_cc_apply_card_fields(array $draft, array $incoming): array
{
    $f = $draft['fields'];
    if (!empty($incoming['name'])) {
        $names = ai_cc_parse_full_name((string) $incoming['name']);
        if ($names['first_name'] !== '') {
            $f['first_name'] = $names['first_name'];
            $f['last_name'] = $names['last_name'];
        }
    }
    foreach (['email', 'phone', 'postal', 'country', 'case_title', 'service', 'description'] as $k) {
        if (array_key_exists($k, $incoming)) {
            $f[$k] = trim((string) $incoming[$k]);
        }
    }
    if (array_key_exists('address', $incoming)) {
        $addr = trim((string) $incoming['address']);
        $f['street'] = $addr;
        // Rebuild full address line
        $bits = array_filter([
            $addr,
            $f['city'] ?? '',
            $f['region'] ?? '',
            $f['postal'] ?? '',
            $f['country'] ?? '',
        ], static fn($v) => trim((string) $v) !== '');
        $f['address'] = implode(', ', $bits);
    }
    if (array_key_exists('description', $incoming)) {
        $draft['_description_set'] = true;
        if (preg_match('/^(none|-)$/i', (string) $f['description'])) {
            $f['description'] = '';
        }
    }
    $draft['fields'] = $f;
    return $draft;
}

/**
 * @param array<int, array<string, mixed>> $attachments
 */
function ai_action_create_client_case(
    PDO $pdo,
    array $user,
    string $portal,
    string $message,
    string $q,
    array $attachments = [],
    int $sessionId = 0
): string {
    if (!ai_actions_can_admin($portal, $user)) {
        return 'Only admin/staff can create clients and cases from the AI assistant.';
    }
    if ($sessionId <= 0) {
        return 'Open an AI chat session first, then say **create a new case**.';
    }

    $draft = ai_client_draft_get($sessionId);
    if (!$draft || ($draft['type'] ?? '') !== 'client_case') {
        $draft = ai_cc_draft_blank();
        // Starting phrase only — ask first question
        if (ai_actions_wants($q, [
            'create a new case', 'create new case', 'new case', 'create client and case',
            'create a client and case', 'new client and case', 'setup client and case',
        ]) && !preg_match('/\b(titled|for client|client=)/i', $message)) {
            ai_client_draft_set($sessionId, $draft);
            return ai_cc_prompt($draft);
        }
        // Continuing somehow without draft — start fresh
        ai_client_draft_set($sessionId, $draft);
        return ai_cc_prompt($draft);
    }

    if ($q === 'cancel' || $q === 'abort' || $q === 'annuler' || $q === 'stop'
        || ai_actions_wants($q, ['cancel create', 'cancel case', 'stop intake'])) {
        ai_client_draft_clear($sessionId);
        return 'Client & case setup cancelled. Nothing was created.';
    }

    // Re-asking "create a new case" while a wizard is open → show current question again.
    if (ai_actions_wants($q, [
        'create a new case', 'create new case', 'create client and case', 'new client and case',
    ]) && !preg_match('/\b(titled|for client|tom |change |confirm|@)/i', $message)
        && strlen(trim($message)) < 48) {
        $missing = ai_cc_next_missing($draft);
        if ($missing === 'case_title') {
            return "Client details complete. Now for the case:\n\n" . ai_cc_question('case_title');
        }
        if ($missing) {
            return ai_cc_intro() . "\n\n" . ai_cc_question($missing);
        }
        return ai_cc_review_message($draft);
    }

    $isConfirm = ai_actions_wants($q, ['confirm', 'yes create', 'create now', 'save client', 'oui', 'confirmer', 'go ahead']);
    $isSaveOnly = ai_actions_wants($q, ['save changes', 'save draft', 'update draft']);

    // Card JSON payload: confirm {"name":"..."} 
    $cardData = null;
    if (preg_match('/^(confirm|save changes|save draft)\s+(\{[\s\S]*\})$/iu', trim($message), $m)) {
        $isConfirm = str_starts_with(strtolower($m[1]), 'confirm');
        $isSaveOnly = !$isConfirm;
        $cardData = json_decode($m[2], true);
    }

    if (is_array($cardData)) {
        $draft = ai_cc_apply_card_fields($draft, $cardData);
    } elseif (!$isConfirm) {
        $applied = ai_cc_apply_answer($draft, $message, $q);
        $draft = $applied['draft'];
        if (!$applied['ok']) {
            ai_client_draft_set($sessionId, $draft);
            return '⚠️ ' . $applied['error'] . "\n\n" . ai_cc_intro() . "\n\n" . ai_cc_question((string) ($draft['awaiting_field'] ?: 'full_name'));
        }
    }

    // Auto username/password when client fields ready
    $f = $draft['fields'];
    if ($f['first_name'] && $f['last_name'] && empty($f['username'])) {
        $f['username'] = ai_actions_unique_username($pdo, $f['first_name'], $f['last_name']);
    }
    if ($f['first_name'] && empty($f['password'])) {
        $f['password'] = 'Temp' . random_int(1000, 9999) . '!';
    }
    $draft['fields'] = $f;

    $missing = ai_cc_next_missing($draft);
    if ($isSaveOnly) {
        $draft['awaiting'] = $missing ? 'details' : 'confirm';
        $draft['awaiting_field'] = $missing ?? '';
        ai_client_draft_set($sessionId, $draft);
        return $missing
            ? ("Draft updated.\n\n" . ai_cc_prompt($draft))
            : ("Draft updated.\n\n" . ai_cc_review_message($draft));
    }

    if ($missing) {
        $draft['awaiting'] = 'details';
        $draft['awaiting_field'] = $missing;
        ai_client_draft_set($sessionId, $draft);
        if ($missing === 'case_title') {
            return "Client details complete. Now for the case:\n\n" . ai_cc_question('case_title');
        }
        if (in_array($missing, ['service', 'description'], true)) {
            return ai_cc_question($missing);
        }
        return ai_cc_intro() . "\n\n" . ai_cc_question($missing);
    }

    if (!$isConfirm) {
        $draft['awaiting'] = 'confirm';
        $draft['awaiting_field'] = '';
        ai_client_draft_set($sessionId, $draft);
        return ai_cc_review_message($draft);
    }

    return ai_cc_finalize($pdo, $user, $sessionId, $draft);
}

/**
 * @param array<string, mixed> $draft
 */
function ai_cc_finalize(PDO $pdo, array $user, int $sessionId, array $draft): string
{
    $f = $draft['fields'];
    $first = trim((string) $f['first_name']);
    $last = trim((string) $f['last_name']);
    $email = trim((string) $f['email']);
    $phone = trim((string) ($f['phone'] ?? '')) ?: null;
    $address = trim((string) ($f['address'] ?? '')) ?: null;
    $title = trim((string) $f['case_title']);
    $service = trim((string) ($f['service'] ?? 'Other')) ?: 'Other';
    $description = trim((string) ($f['description'] ?? ''));
    $username = trim((string) ($f['username'] ?? ''));
    $passwordPlain = trim((string) ($f['password'] ?? ''));

    if ($first === '' || $last === '' || $email === '' || $title === '') {
        return '⚠️ Missing required fields. ' . ai_cc_prompt($draft);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $draft['awaiting_field'] = 'email';
        ai_client_draft_set($sessionId, $draft);
        return '⚠️ Invalid email. Please send a correct email address.';
    }

    $existing = ai_actions_find_user_by_name($pdo, 'client', $first, $last, $email);
    if ($existing) {
        $clientId = (int) $existing['id'];
    } else {
        if ($username === '') {
            $username = ai_actions_unique_username($pdo, $first, $last);
        }
        if ($passwordPlain === '') {
            $passwordPlain = 'Temp' . random_int(1000, 9999) . '!';
        }
        $check = $pdo->prepare('SELECT id FROM users WHERE username=? OR email=? LIMIT 1');
        $check->execute([$username, $email]);
        if ($check->fetchColumn()) {
            $username = ai_actions_unique_username($pdo, $first, $last);
        }
        $pdo->prepare(
            'INSERT INTO users (role, first_name, last_name, username, email, password, phone, address, company_name, assigned_lawyer_id, notes, is_active)
             VALUES ("client",?,?,?,?,?,?,?,?,NULL,NULL,1)'
        )->execute([
            $first, $last, $username, $email, password_hash($passwordPlain, PASSWORD_DEFAULT), $phone, $address,
        ]);
        $clientId = (int) $pdo->lastInsertId();
        log_activity($pdo, (int) $user['id'], 'create', 'client', $clientId, 'Created client via AI client+case wizard');
    }

    ensure_case_create_columns($pdo);
    $caseNumber = generate_case_number($pdo);
    $caseType = $service;
    // Map common services into known types when obvious
    $known = ['Commercial', 'Civil', 'Criminal', 'Family', 'Employment', 'Corporate', 'Real Estate', 'Other'];
    foreach ($known as $k) {
        if (strcasecmp($k, $service) === 0) {
            $caseType = $k;
            break;
        }
    }

    $hasAssignedAdminColumn = false;
    try {
        $hasAssignedAdminColumn = (bool) $pdo->query("SHOW COLUMNS FROM cases LIKE 'assigned_admin_id'")->fetch();
    } catch (Throwable $e) {
        $hasAssignedAdminColumn = false;
    }

    $desc = $description !== '' ? $description : null;
    if ($hasAssignedAdminColumn) {
        $pdo->prepare(
            'INSERT INTO cases (case_number, title, description, client_instructions, case_type, status, priority, client_id, lawyer_id, assigned_admin_id, court_name, court_location, filing_date, next_hearing_date, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $caseNumber, $title, $desc, null, $caseType, 'open', 'medium',
            $clientId, null, (int) $user['id'], null, null, date('Y-m-d'), null, (int) $user['id'],
        ]);
    } else {
        $pdo->prepare(
            'INSERT INTO cases (case_number, title, description, client_instructions, case_type, status, priority, client_id, lawyer_id, court_name, court_location, filing_date, next_hearing_date, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $caseNumber, $title, $desc, null, $caseType, 'open', 'medium',
            $clientId, null, null, null, date('Y-m-d'), null, (int) $user['id'],
        ]);
    }
    $caseId = (int) $pdo->lastInsertId();
    log_activity($pdo, (int) $user['id'], 'create', 'case', $caseId, 'Created case via AI client+case wizard');

    ai_client_draft_clear($sessionId);
    $clientUrl = ai_actions_portal_url('admin', 'clients.php?action=view&id=' . $clientId);
    $caseUrl = ai_actions_portal_url('admin', 'cases.php?action=view&id=' . $caseId);

    $pwdNote = $existing ? '' : ("\n• Temporary password: `{$passwordPlain}` (username `{$username}`)");

    return "✅ Client and case created.\n\n"
        . "• Client: **{$first} {$last}** ({$email})\n"
        . "• Case: **{$caseNumber}** — {$title}\n"
        . "• Service: {$service}"
        . $pwdNote . "\n\n"
        . ai_actions_md_link('Open client', $clientUrl) . ' · '
        . ai_actions_md_link('Open case', $caseUrl);
}

function ai_cc_message_is_reply(string $message, string $q, array $draft): bool
{
    if (($draft['type'] ?? '') !== 'client_case') {
        return false;
    }
    if (ai_actions_wants($q, ['confirm', 'save changes', 'save draft', 'cancel', 'abort', 'annuler', 'stop'])) {
        return true;
    }
    if (preg_match('/^(confirm|save changes)\s+\{/iu', trim($message))) {
        return true;
    }
    if (preg_match('/\bchange\s+\w+\s+to\b/iu', $q)) {
        return true;
    }
    $awaiting = (string) ($draft['awaiting_field'] ?? '');
    if ($awaiting !== '' || ($draft['awaiting'] ?? '') === 'confirm') {
        $trim = trim($message);
        if ($trim === '') {
            return false;
        }
        // Reject clear unrelated intents
        if (ai_actions_wants_email_draft($q) || ai_actions_wants($q, ['schedule appointment', 'upload', 'how many', 'what is', 'define '])) {
            return false;
        }
        return true;
    }
    return false;
}
