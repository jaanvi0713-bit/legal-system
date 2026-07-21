<?php
/**
 * AI workspace actions — create/update entities from natural language (role-scoped).
 */

require_once __DIR__ . '/ai-client-intake.php';
require_once __DIR__ . '/ai-client-case-intake.php';

/**
 * Try to run a workspace mutation from the user message.
 * Returns a reply string on match, or null if this is not an action request.
 *
 * @param array<int, array<string, mixed>> $attachments
 */
function ai_try_actions(PDO $pdo, array $user, string $portal, string $message, array $attachments = [], int $sessionId = 0): ?string
{
    $q = ai_actions_normalize($message);

    try {
        // Drop abandoned client / client+case intakes so they stop swallowing later requests.
        if ($sessionId > 0 && ai_actions_can_admin($portal, $user)) {
            $draft = ai_client_draft_get($sessionId);
            if ($draft && in_array(($draft['type'] ?? ''), ['client', 'client_case'], true)) {
                $age = time() - (int) ($draft['updated_at'] ?? $draft['created_at'] ?? time());
                if ($age > 1800) {
                    ai_client_draft_clear($sessionId);
                    $draft = null;
                }
            }

            if ($draft && in_array(($draft['type'] ?? ''), ['client', 'client_case'], true)) {
                if ($q !== '' && !ai_actions_wants($q, ['cancel appointment', 'cancel meeting', 'annuler rendez-vous', 'annuler rdv'])
                    && (
                        $q === 'cancel' || $q === 'abort' || $q === 'annuler' || $q === 'stop'
                        || ai_actions_wants($q, ['cancel create client', 'cancel client', 'annuler client', 'stop intake', 'cancel case'])
                    )) {
                    ai_client_draft_clear($sessionId);
                    return (($draft['type'] ?? '') === 'client_case')
                        ? 'Client & case setup cancelled. Nothing was created.'
                        : 'Client intake cancelled. Nothing was created.';
                }
            }
        }

        if ($q === '' && !$attachments) {
            return null;
        }

        // Clear workspace intents always win over an open intake wizard.
        $wantsUpload = ai_actions_wants($q, [
            'upload', 'attach', 'add document', 'ajouter document', 'joindre', 'televerser', 'téléverser',
            'upload to case', 'attach to case', 'add to case',
        ]) || ($attachments && (bool) preg_match('/\b(upload|attach|joindre).{0,40}\b(case|dossier)\b/u', $q))
            || ($attachments && (bool) preg_match('/\b(to|onto|into)\s+(case|dossier)\b/u', $q));

        if ($wantsUpload
            && !ai_actions_wants($q, ['create client', 'new client', 'from this document', 'from document', 'extract client'])) {
            return ai_action_upload_document($pdo, $user, $portal, $message, $q, $attachments);
        }

        if (ai_actions_wants_email_draft($q)) {
            return ai_action_draft_email($pdo, $user, $portal, $message, $q);
        }

        if (ai_actions_wants($q, ['send email', 'envoyer email', 'envoyer un email', 'mail to client'])
            && !ai_actions_wants_email_draft($q)) {
            return ai_action_send_email($pdo, $user, $portal, $message, $q);
        }

        if (ai_actions_wants($q, ['cancel appointment', 'cancel the appointment', 'annuler rendez-vous', 'annuler le rendez-vous', 'annuler rdv', 'cancel meeting'])) {
            return ai_action_cancel_appointment($pdo, $user, $portal, $message, $q);
        }

        if (ai_actions_wants($q, ['schedule appointment', 'book appointment', 'create appointment', 'new appointment', 'prendre rendez-vous', 'planifier rendez-vous', 'creer rendez-vous', 'créer rendez-vous', 'fixer un rendez-vous', 'schedule a meeting', 'book a meeting'])) {
            return ai_action_schedule_appointment($pdo, $user, $portal, $message, $q);
        }

        if (ai_actions_wants($q, ['create case', 'new case', 'open case', 'add case', 'creer dossier', 'créer dossier', 'nouveau dossier', 'ouvrir un dossier', 'create a case', 'create a new case', 'create new case', 'create client and case', 'new client and case'])) {
            // Guided wizard when creating a brand-new matter without an existing client named in the message.
            $hasExistingClientHint = (bool) preg_match('/\b(for\s+client|client\s*=|titled\s+.+\s+for\b)/iu', $message);
            $hasTitleAndClient = $hasExistingClientHint && (
                (bool) preg_match('/\btitled\b/iu', $message)
                || (bool) preg_match('/\btitle\s*=/iu', $message)
            );
            if (!$hasTitleAndClient && ai_actions_can_admin($portal, $user)) {
                return ai_action_create_client_case($pdo, $user, $portal, $message, $q, $attachments, $sessionId);
            }
            return ai_action_create_case($pdo, $user, $portal, $message, $q);
        }

        if (ai_actions_wants($q, ['create lawyer', 'new lawyer', 'add lawyer', 'creer avocat', 'créer avocat', 'nouvel avocat', 'ajouter avocat'])) {
            return ai_action_create_lawyer($pdo, $user, $portal, $message, $q);
        }

        if (ai_actions_wants($q, ['assign lawyer', 'assign case', 'assigner avocat', 'assigner le dossier'])) {
            return ai_action_assign_lawyer($pdo, $user, $portal, $message, $q);
        }

        if (ai_actions_wants($q, ['update case status', 'change case status', 'set case status', 'set case', 'close case', 'reopen case', 'mettre a jour statut', 'fermer dossier', 'rouvrir dossier', 'status to'])) {
            return ai_action_update_case_status($pdo, $user, $portal, $message, $q);
        }

        // Resume client / client+case intake only when the message looks like an answer to it.
        if ($sessionId > 0 && ai_actions_can_admin($portal, $user)) {
            $draft = ai_client_draft_get($sessionId);
            if ($draft && ($draft['type'] ?? '') === 'client_case' && ai_cc_message_is_reply($message, $q, $draft)) {
                return ai_action_create_client_case($pdo, $user, $portal, $message, $q, $attachments, $sessionId);
            }
            if ($draft && ($draft['type'] ?? '') === 'client'
                && ai_client_message_is_intake_reply($message, $q, $draft, $attachments)) {
                return ai_action_create_client($pdo, $user, $portal, $message, $q, $attachments, $sessionId);
            }
        }

        if (ai_actions_wants($q, [
            'create client', 'new client', 'add client', 'creer client', 'créer client', 'nouveau client', 'ajouter client',
            'client from document', 'extract client', 'extract details', 'register client', 'onboard client',
        ])) {
            return ai_action_create_client($pdo, $user, $portal, $message, $q, $attachments, $sessionId);
        }

        // Document attached that looks like a client profile / system invoice — start intake.
        if ($attachments && ai_actions_can_admin($portal, $user)) {
            $fileHay = '';
            foreach ($attachments as $att) {
                $fileHay .= ' ' . (string) ($att['file_name'] ?? '');
            }
            $looksUseful = ai_client_doc_looks_like_profile($attachments)
                || (bool) preg_match('/\b(INV|RCP)[-_]?\d/i', $fileHay)
                || ai_actions_wants($q, ['extract', 'from document', 'from this', 'details']);
            if ($looksUseful) {
                return ai_action_create_client(
                    $pdo,
                    $user,
                    $portal,
                    $message !== '' ? $message : 'create client from document',
                    'create client from document',
                    $attachments,
                    $sessionId
                );
            }
        }
    } catch (Throwable $e) {
        return '⚠️ Action failed: ' . $e->getMessage();
    }

    return null;
}

function ai_actions_normalize(string $message): string
{
    $q = function_exists('mb_strtolower') ? mb_strtolower(trim($message), 'UTF-8') : strtolower(trim($message));
    $q = str_replace(["\r\n", "\r"], "\n", $q);
    $map = ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'â' => 'a', 'ù' => 'u', 'û' => 'u', 'ô' => 'o', 'î' => 'i', 'ï' => 'i', 'ç' => 'c'];
    return strtr($q, $map);
}

/**
 * @param list<string> $needles
 */
function ai_actions_wants(string $q, array $needles): bool
{
    foreach ($needles as $n) {
        $n = ai_actions_normalize($n);
        if ($n === '') {
            continue;
        }
        // Word-boundary style match so "open case" does not hit "open cases".
        $pattern = '/(?<![\p{L}\p{N}_])' . preg_quote($n, '/') . '(?![\p{L}\p{N}_])/u';
        if (preg_match($pattern, $q)) {
            return true;
        }
    }
    return false;
}

/** Detect email-draft intents even when words sit between "draft" and "email". */
function ai_actions_wants_email_draft(string $q): bool
{
    if (ai_actions_wants($q, [
        'draft email', 'draft an email', 'draft a professional email', 'write email', 'write an email',
        'compose email', 'compose an email', 'email draft', 'draft letter', 'professional email',
        'redige un email', 'rediger un email', 'ecrire un email', 'lettre professionnelle',
    ])) {
        return true;
    }
    return (bool) preg_match('/\b(draft|write|compose|redige|rediger|ecrire)\b.{0,48}\b(email|mail|lettre)\b/u', $q)
        || (bool) preg_match('/\b(email|mail)\b.{0,48}\b(draft|brouillon)\b/u', $q);
}

function ai_actions_can_admin(string $portal, array $user): bool
{
    return $portal === 'admin' && in_array($user['role'] ?? '', ['admin', 'staff'], true);
}

function ai_actions_portal_url(string $portal, string $path): string
{
    $base = rtrim((string) app_config('url'), '/');
    return $base . '/' . $portal . '/' . ltrim($path, '/');
}

function ai_actions_md_link(string $label, string $url): string
{
    return '[' . $label . '](' . $url . ')';
}

/**
 * Extract "key=value" or "key: value" pairs.
 *
 * @return array<string, string>
 */
function ai_actions_parse_kv(string $message): array
{
    $out = [];
    if (preg_match_all('/\b([a-z_]+)\s*[:=]\s*["\']?([^,"\'\n]+)["\']?/iu', $message, $m, PREG_SET_ORDER)) {
        foreach ($m as $row) {
            $key = strtolower(trim($row[1]));
            $val = trim($row[2]);
            if ($key !== '' && $val !== '') {
                $out[$key] = $val;
            }
        }
    }
    return $out;
}

function ai_actions_extract_email(string $message): ?string
{
    if (preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $message, $m)) {
        return strtolower($m[0]);
    }
    return null;
}

function ai_actions_extract_phone(string $message): ?string
{
    if (preg_match('/(?:phone|tel|mobile|gsm)\s*[:=]?\s*([+\d][\d\s\-()]{6,})/i', $message, $m)) {
        $phone = trim($m[1]);
        return function_exists('ai_client_is_plausible_phone') && !ai_client_is_plausible_phone($phone) ? null : $phone;
    }
    if (preg_match('/\b(\+?\d[\d\s\-()]{7,}\d)\b/', $message, $m)) {
        $phone = trim($m[1]);
        return function_exists('ai_client_is_plausible_phone') && !ai_client_is_plausible_phone($phone) ? null : $phone;
    }
    return null;
}

/**
 * @return array{0:?string,1:?string} first, last
 */
function ai_actions_extract_person_name(string $message, string $afterHint = ''): array
{
    $patterns = [
        '/(?:client|lawyer|avocat|named?|called|pour|for)\s+([A-ZÀ-ÖØ-Ý][\w\'\-]+)\s+([A-ZÀ-ÖØ-Ý][\w\'\-]+)/u',
        '/(?:first(?:\s*name)?|prenom|prénom)\s*[:=]?\s*([A-Za-zÀ-ÖØ-öø-ÿ\'\-]+).*(?:last(?:\s*name)?|nom)\s*[:=]?\s*([A-Za-zÀ-ÖØ-öø-ÿ\'\-]+)/iu',
        '/\b([A-ZÀ-ÖØ-Ý][\w\'\-]+)\s+([A-ZÀ-ÖØ-Ý][\w\'\-]+)\b/u',
    ];
    if ($afterHint !== '') {
        array_unshift($patterns, '/' . preg_quote($afterHint, '/') . '\s+([A-ZÀ-ÖØ-Ý][\w\'\-]+)\s+([A-ZÀ-ÖØ-Ý][\w\'\-]+)/iu');
    }
    foreach ($patterns as $p) {
        if (preg_match($p, $message, $m)) {
            $f = trim($m[1]);
            $l = trim($m[2]);
            if (function_exists('ai_client_is_plausible_person_name') && !ai_client_is_plausible_person_name($f, $l)) {
                continue;
            }
            return [$f, $l];
        }
    }
    $kv = ai_actions_parse_kv($message);
    if (!empty($kv['first']) || !empty($kv['first_name'])) {
        return [
            $kv['first'] ?? $kv['first_name'] ?? null,
            $kv['last'] ?? $kv['last_name'] ?? null,
        ];
    }
    return [null, null];
}

function ai_actions_extract_quoted(string $message, array $labels = ['title', 'subject', 'case']): ?string
{
    if (preg_match('/["“”](.+?)["“”]/u', $message, $m)) {
        return trim($m[1]);
    }
    foreach ($labels as $label) {
        if (preg_match('/\b' . preg_quote($label, '/') . '\s*[:=]\s*["\']?([^"\'\n]+)["\']?/iu', $message, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/\b(?:titled|called|named|intitulé|intitule)\s+["\']?([^"\'\n,]{3,80})["\']?/iu', $message, $m)) {
            return trim($m[1]);
        }
    }
    return null;
}

function ai_actions_extract_datetime(string $message): ?string
{
    $kv = ai_actions_parse_kv($message);
    foreach (['when', 'datetime', 'date', 'scheduled_at', 'at'] as $k) {
        if (!empty($kv[$k])) {
            $ts = strtotime($kv[$k]);
            if ($ts !== false) {
                return date('Y-m-d H:i:s', $ts);
            }
        }
    }

    if (preg_match('/\b(20\d{2}-\d{2}-\d{2})[ T](\d{1,2}:\d{2})(?::\d{2})?\b/', $message, $m)) {
        return $m[1] . ' ' . (strlen($m[2]) === 4 ? '0' . $m[2] : $m[2]) . ':00';
    }

    $rel = null;
    if (preg_match('/\b(tomorrow|demain)\b/iu', $message)) {
        $rel = 'tomorrow';
    } elseif (preg_match('/\btoday|aujourd.?hui\b/iu', $message)) {
        $rel = 'today';
    } elseif (preg_match('/\bnext\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/iu', $message, $m)) {
        $rel = 'next ' . strtolower($m[1]);
    }

    $time = '10:00';
    if (preg_match('/\b(?:at|a|à)\s*(\d{1,2})(?:[:hH](\d{2}))?\s*(am|pm)?\b/iu', $message, $m)) {
        $h = (int) $m[1];
        $min = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
        $ampm = strtolower($m[3] ?? '');
        if ($ampm === 'pm' && $h < 12) {
            $h += 12;
        }
        if ($ampm === 'am' && $h === 12) {
            $h = 0;
        }
        $time = sprintf('%02d:%02d', $h, $min);
    }

    if ($rel !== null) {
        $ts = strtotime($rel . ' ' . $time);
        if ($ts !== false) {
            return date('Y-m-d H:i:s', $ts);
        }
    }

    if (preg_match('/\b(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\b/', $message, $m)) {
        $ts = strtotime($m[1] . ' ' . $time);
        if ($ts !== false) {
            return date('Y-m-d H:i:s', $ts);
        }
    }

    return null;
}

function ai_actions_find_user_by_name(PDO $pdo, string $role, ?string $first, ?string $last, ?string $email = null): ?array
{
    if ($email) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE role=? AND LOWER(email)=? LIMIT 1');
        $stmt->execute([$role, strtolower($email)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
    }
    if ($first && $last) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE role=? AND LOWER(first_name)=? AND LOWER(last_name)=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$role, strtolower($first), strtolower($last)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
        $stmt = $pdo->prepare("SELECT * FROM users WHERE role=? AND (CONCAT(LOWER(first_name),' ',LOWER(last_name)) LIKE ? OR LOWER(company_name) LIKE ?) ORDER BY id DESC LIMIT 1");
        $like = '%' . strtolower($first . ' ' . $last) . '%';
        $stmt->execute([$role, $like, $like]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
    }
    return null;
}

function ai_actions_find_case(PDO $pdo, string $portal, array $user, string $message): ?array
{
    $uid = (int) $user['id'];
    $caseNumber = null;
    $shortNum = null;

    // Full refs: CASE-2026-001, CASE 2026 001, CASE-001
    if (preg_match('/\b(CASE[-\s]?\d{4}[-\s]?\d{1,6})\b/i', $message, $m)) {
        $caseNumber = strtoupper(preg_replace('/\s+/', '', $m[1]));
        $caseNumber = str_replace('CASE', 'CASE-', $caseNumber);
        $caseNumber = preg_replace('/CASE-+/', 'CASE-', $caseNumber);
    } elseif (preg_match('/\bCASE[-\s]?(\d{1,6})\b/i', $message, $m)) {
        $shortNum = (int) $m[1];
    } elseif (preg_match('/\b(?:case|dossier)\s*[#:\-.]?\s*(\d{1,6})\b/i', $message, $m)) {
        // "case 005", "case #5", "dossier 3"
        $shortNum = (int) $m[1];
    } elseif (preg_match('/\b(?:to|onto|into|for)\s+(\d{1,6})\b/i', $message, $m)) {
        // "upload to 005"
        $shortNum = (int) $m[1];
    }

    if ($caseNumber) {
        $compact = str_replace([' ', '-'], '', $caseNumber);
        $stmt = $pdo->prepare(
            'SELECT * FROM cases
             WHERE REPLACE(REPLACE(UPPER(case_number), " ", ""), "-", "") = ?
                OR UPPER(case_number) = ?
             LIMIT 1'
        );
        $stmt->execute([$compact, $caseNumber]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row) {
            return ai_actions_scope_case($pdo, $portal, $user, $row);
        }
    }

    if ($shortNum !== null && $shortNum > 0) {
        $row = ai_actions_find_case_by_short_number($pdo, $portal, $user, $shortNum);
        if ($row) {
            return $row;
        }
    }

    $title = ai_actions_extract_quoted($message, ['case', 'title']);
    if ($title) {
        if ($portal === 'lawyer') {
            $stmt = $pdo->prepare('SELECT * FROM cases WHERE lawyer_id=? AND (title LIKE ? OR case_number LIKE ?) ORDER BY updated_at DESC LIMIT 1');
            $stmt->execute([$uid, '%' . $title . '%', '%' . $title . '%']);
        } elseif ($portal === 'client') {
            $stmt = $pdo->prepare('SELECT * FROM cases WHERE client_id=? AND (title LIKE ? OR case_number LIKE ?) ORDER BY updated_at DESC LIMIT 1');
            $stmt->execute([$uid, '%' . $title . '%', '%' . $title . '%']);
        } else {
            $stmt = $pdo->prepare('SELECT * FROM cases WHERE title LIKE ? OR case_number LIKE ? ORDER BY updated_at DESC LIMIT 1');
            $stmt->execute(['%' . $title . '%', '%' . $title . '%']);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
    }

    return null;
}

/**
 * Resolve "case 5" / "005" to a case by id or case_number suffix (e.g. CASE-2026-005).
 */
function ai_actions_find_case_by_short_number(PDO $pdo, string $portal, array $user, int $num): ?array
{
    if ($num < 1) {
        return null;
    }
    $pad3 = str_pad((string) $num, 3, '0', STR_PAD_LEFT);
    $candidates = [];

    // Prefer exact case_number suffix: CASE-YYYY-005 / CASE-YYYY-5
    $stmt = $pdo->query('SELECT * FROM cases ORDER BY updated_at DESC, id DESC');
    $all = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    foreach ($all as $row) {
        $cn = strtoupper((string) ($row['case_number'] ?? ''));
        if (preg_match('/-0*' . preg_quote((string) $num, '/') . '$/', $cn)
            || preg_match('/-' . preg_quote($pad3, '/') . '$/', $cn)) {
            $scoped = ai_actions_scope_case($pdo, $portal, $user, $row);
            if ($scoped) {
                $candidates[] = $scoped;
            }
        }
    }
    if (count($candidates) === 1) {
        return $candidates[0];
    }
    if (count($candidates) > 1) {
        // Newest match wins
        return $candidates[0];
    }

    // Fallback: internal id
    $stmt = $pdo->prepare('SELECT * FROM cases WHERE id = ? LIMIT 1');
    $stmt->execute([$num]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    return $row ? ai_actions_scope_case($pdo, $portal, $user, $row) : null;
}

/**
 * Short list of cases the user can target (for error messages).
 */
function ai_actions_list_cases_hint(PDO $pdo, string $portal, array $user, int $limit = 8): string
{
    $uid = (int) $user['id'];
    $limit = max(1, min(20, $limit));
    try {
        if ($portal === 'lawyer') {
            $stmt = $pdo->prepare("SELECT case_number, title FROM cases WHERE lawyer_id=? ORDER BY updated_at DESC LIMIT {$limit}");
            $stmt->execute([$uid]);
        } elseif ($portal === 'client') {
            $stmt = $pdo->prepare("SELECT case_number, title FROM cases WHERE client_id=? ORDER BY updated_at DESC LIMIT {$limit}");
            $stmt->execute([$uid]);
        } else {
            $stmt = $pdo->query("SELECT case_number, title FROM cases ORDER BY updated_at DESC LIMIT {$limit}");
        }
        $rows = ($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : null) ?: [];
    } catch (Throwable $e) {
        $rows = [];
    }
    if (!$rows) {
        return 'No cases found in the system yet.';
    }
    $lines = [];
    foreach ($rows as $r) {
        $lines[] = '• **' . ($r['case_number'] ?? '') . '** — ' . ($r['title'] ?? '');
    }
    return "Available cases:\n" . implode("\n", $lines);
}

function ai_actions_scope_case(PDO $pdo, string $portal, array $user, array $case): ?array
{
    $uid = (int) $user['id'];
    if ($portal === 'lawyer' && (int) ($case['lawyer_id'] ?? 0) !== $uid) {
        return null;
    }
    if ($portal === 'client' && (int) ($case['client_id'] ?? 0) !== $uid) {
        return null;
    }
    return $case;
}

function ai_actions_unique_username(PDO $pdo, string $first, string $last): string
{
    $base = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $first . '.' . $last) ?: 'user');
    $base = trim($base, '.') ?: 'user';
    $candidate = $base;
    $i = 1;
    while (true) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
        $stmt->execute([$candidate]);
        if (!$stmt->fetchColumn()) {
            return $candidate;
        }
        $candidate = $base . $i;
        $i++;
        if ($i > 99) {
            return $base . bin2hex(random_bytes(2));
        }
    }
}

function ai_actions_help_create_client(): string
{
    return "Start with `create client` (or attach an intake/ID document).\n"
        . "I will ask for every required field: first name, last name, email, username, password.\n"
        . "You can also paste: `first_name=Jean last_name=Dupont email=jean@example.com`\n"
        . "When ready, reply `confirm`.";
}

function ai_actions_help_create_lawyer(): string
{
    return "To create a lawyer, include name and email. Example:\n"
        . "`Create lawyer Marie Curie email marie@firm.mu specialization Corporate`";
}

function ai_actions_help_create_case(): string
{
    return "To create a case, include a title and client. Example:\n"
        . "`Create case titled \"Lease dispute\" for client Jean Dupont assign lawyer Marie Curie type Commercial priority high`";
}

function ai_actions_help_appointment(): string
{
    return "To schedule an appointment, include when + people. Example:\n"
        . "`Schedule appointment tomorrow at 10:00 with client Jean Dupont and lawyer Marie Curie title Consultation`\n"
        . "Or: `Schedule appointment 2026-07-22 14:30 client=Jean Dupont`";
}

function ai_actions_help_upload(): string
{
    return "Attach a file in chat, then say which case. Examples:\n"
        . "• `Upload this to case CASE-2026-001`\n"
        . "• `Upload to case 001`\n"
        . "• `Attach to case titled Lease dispute category evidence`";
}

function ai_action_create_lawyer(PDO $pdo, array $user, string $portal, string $message, string $q): string
{
    if (!ai_actions_can_admin($portal, $user)) {
        return 'Only admin/staff can create lawyers from the AI assistant.';
    }

    $kv = ai_actions_parse_kv($message);
    [$first, $last] = ai_actions_extract_person_name($message, 'lawyer');
    if ((!$first || !$last) && str_contains($q, 'avocat')) {
        [$first, $last] = ai_actions_extract_person_name($message, 'avocat');
    }
    $first = $kv['first'] ?? $kv['first_name'] ?? $first;
    $last = $kv['last'] ?? $kv['last_name'] ?? $last;
    $email = $kv['email'] ?? ai_actions_extract_email($message);
    $phone = $kv['phone'] ?? ai_actions_extract_phone($message);
    $spec = $kv['specialization'] ?? $kv['speciality'] ?? null;
    if (!$spec && preg_match('/specialization\s*[:=]?\s*([A-Za-z][\w\s\-]{2,40})/i', $message, $m)) {
        $spec = trim($m[1]);
    }

    if (!$first || !$last || !$email) {
        return "I can create the lawyer, but I need more details.\n\n" . ai_actions_help_create_lawyer();
    }

    $existing = ai_actions_find_user_by_name($pdo, 'lawyer', $first, $last, $email);
    if ($existing) {
        $url = ai_actions_portal_url('admin', 'lawyers.php?action=view&id=' . (int) $existing['id']);
        return 'A lawyer with that name/email already exists: **' . full_name($existing) . '**. '
            . ai_actions_md_link('Open lawyer', $url);
    }

    $username = $kv['username'] ?? ai_actions_unique_username($pdo, $first, $last);
    $passwordPlain = $kv['password'] ?? 'password123';
    $password = password_hash($passwordPlain, PASSWORD_DEFAULT);

    $pdo->prepare(
        'INSERT INTO users (role, first_name, last_name, username, email, password, phone, address, specialization, bar_number, availability, is_active)
         VALUES ("lawyer",?,?,?,?,?,?,?,?,?,?,1)'
    )->execute([
        $first, $last, $username, $email, $password, $phone, $kv['address'] ?? null,
        $spec, $kv['bar_number'] ?? $kv['bar'] ?? null, $kv['availability'] ?? 'available',
    ]);
    $newId = (int) $pdo->lastInsertId();
    log_activity($pdo, (int) $user['id'], 'create', 'lawyer', $newId, 'Created lawyer via AI');

    $url = ai_actions_portal_url('admin', 'lawyers.php?action=view&id=' . $newId);
    return "✅ Lawyer created.\n\n"
        . "• Name: **{$first} {$last}**\n"
        . "• Email: {$email}\n"
        . "• Username: `{$username}` (temporary password: `{$passwordPlain}`)\n"
        . ($spec ? "• Specialization: {$spec}\n" : '')
        . "\n" . ai_actions_md_link('Open lawyer profile', $url);
}

function ai_action_create_case(PDO $pdo, array $user, string $portal, string $message, string $q): string
{
    if (!ai_actions_can_admin($portal, $user) && $portal !== 'lawyer') {
        return 'Clients cannot create cases from AI. Please contact your lawyer or the firm.';
    }

    // Lawyers: allow creating only if admin pattern isn't required — in this system only admin creates cases.
    if ($portal === 'lawyer') {
        return 'Lawyers cannot open new firm cases from AI. Ask an admin, or use: `Draft email` / `Schedule appointment` / `Upload document to case`.';
    }

    if (!ai_actions_can_admin($portal, $user)) {
        return 'Only admin/staff can create cases from the AI assistant.';
    }

    ensure_case_create_columns($pdo);
    $kv = ai_actions_parse_kv($message);
    $title = $kv['title'] ?? ai_actions_extract_quoted($message, ['title', 'case']);
    if (!$title && preg_match('/(?:titled|called|named|intitulé|intitule)\s+(.+?)(?:\s+for\s+client|\s+assign|\s+type\b|$)/iu', $message, $m)) {
        $title = trim($m[1], " \t\"'");
    }
    if (!$title) {
        return "I can create the case, but I need a title and client.\n\n" . ai_actions_help_create_case();
    }

    [$cf, $cl] = ai_actions_extract_person_name($message, 'client');
    $clientEmail = null;
    if (preg_match('/client[^@\n]{0,40}([a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,})/i', $message, $m)) {
        $clientEmail = strtolower($m[1]);
    }
    $client = ai_actions_find_user_by_name($pdo, 'client', $cf, $cl, $clientEmail ?: ($kv['client_email'] ?? null));
    if (!$client && !empty($kv['client_id'])) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="client"');
        $stmt->execute([(int) $kv['client_id']]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if (!$client && !empty($kv['client'])) {
        $parts = preg_split('/\s+/', trim($kv['client'])) ?: [];
        $client = ai_actions_find_user_by_name($pdo, 'client', $parts[0] ?? null, $parts[1] ?? null);
    }
    if (!$client) {
        return "I couldn't find that client. Create the client first, or use a full name that exists.\n\n" . ai_actions_help_create_case();
    }

    [$lf, $ll] = ai_actions_extract_person_name($message, 'lawyer');
    $lawyer = null;
    if (!empty($kv['lawyer_id'])) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="lawyer"');
        $stmt->execute([(int) $kv['lawyer_id']]);
        $lawyer = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } elseif (!empty($kv['lawyer'])) {
        $parts = preg_split('/\s+/', trim($kv['lawyer'])) ?: [];
        $lawyer = ai_actions_find_user_by_name($pdo, 'lawyer', $parts[0] ?? null, $parts[1] ?? null);
    } elseif ($lf && $ll) {
        $lawyer = ai_actions_find_user_by_name($pdo, 'lawyer', $lf, $ll);
    } elseif (!empty($client['assigned_lawyer_id'])) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="lawyer"');
        $stmt->execute([(int) $client['assigned_lawyer_id']]);
        $lawyer = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $caseType = $kv['type'] ?? $kv['case_type'] ?? 'Commercial';
    if (preg_match('/\btype\s*[:=]?\s*(Commercial|Civil|Criminal|Family|Employment|Corporate|Real Estate|Other)\b/i', $message, $m)) {
        $caseType = $m[1];
    }
    $priority = $kv['priority'] ?? 'medium';
    if (preg_match('/\bpriority\s*[:=]?\s*(low|medium|high|urgent)\b/i', $message, $m)) {
        $priority = strtolower($m[1]);
    }
    $status = $kv['status'] ?? 'open';
    $description = $kv['description'] ?? null;

    $caseNumber = generate_case_number($pdo);
    $lawyerId = $lawyer ? (int) $lawyer['id'] : null;
    $clientId = (int) $client['id'];

    $hasAssignedAdminColumn = false;
    try {
        $hasAssignedAdminColumn = (bool) $pdo->query("SHOW COLUMNS FROM cases LIKE 'assigned_admin_id'")->fetch();
    } catch (Throwable $e) {
        $hasAssignedAdminColumn = false;
    }

    if ($hasAssignedAdminColumn) {
        $pdo->prepare(
            'INSERT INTO cases (case_number, title, description, client_instructions, case_type, status, priority, client_id, lawyer_id, assigned_admin_id, court_name, court_location, filing_date, next_hearing_date, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $caseNumber, $title, $description, null, $caseType, $status, $priority,
            $clientId, $lawyerId, (int) $user['id'], $kv['court'] ?? null, $kv['court_location'] ?? null,
            date('Y-m-d'), null, (int) $user['id'],
        ]);
    } else {
        $pdo->prepare(
            'INSERT INTO cases (case_number, title, description, client_instructions, case_type, status, priority, client_id, lawyer_id, court_name, court_location, filing_date, next_hearing_date, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $caseNumber, $title, $description, null, $caseType, $status, $priority,
            $clientId, $lawyerId, $kv['court'] ?? null, $kv['court_location'] ?? null,
            date('Y-m-d'), null, (int) $user['id'],
        ]);
    }
    $caseId = (int) $pdo->lastInsertId();
    log_activity($pdo, (int) $user['id'], 'create', 'case', $caseId, 'Created case via AI');
    if ($lawyerId) {
        create_notification($pdo, $lawyerId, 'New case assigned', $caseNumber . ' assigned to you.', 'case', '../lawyer/cases.php?id=' . $caseId, (int) $user['id']);
    }
    create_notification($pdo, $clientId, 'Case opened', 'Your case ' . $caseNumber . ' is now in the system.', 'case', '../client/cases.php', (int) $user['id']);

    $url = ai_actions_portal_url('admin', 'cases.php?action=view&id=' . $caseId);
    return "✅ Case created.\n\n"
        . "• Number: **{$caseNumber}**\n"
        . "• Title: {$title}\n"
        . "• Client: " . full_name($client) . "\n"
        . "• Type: {$caseType} · Priority: {$priority}\n"
        . ($lawyer ? '• Lawyer: ' . full_name($lawyer) . "\n" : "• Lawyer: not assigned yet\n")
        . "\n" . ai_actions_md_link('Open case', $url);
}

function ai_action_schedule_appointment(PDO $pdo, array $user, string $portal, string $message, string $q): string
{
    $kv = ai_actions_parse_kv($message);
    $when = ai_actions_extract_datetime($message);
    if (!$when) {
        return "I need a date/time to schedule.\n\n" . ai_actions_help_appointment();
    }

    $title = $kv['title'] ?? ai_actions_extract_quoted($message, ['title', 'subject']) ?? null;
    if (!$title && preg_match('/\btitle\s*[:=]?\s+(.+?)(?:\s+with\s+|\s+for\s+|\s+client\b|\s+lawyer\b|$)/iu', $message, $m)) {
        $title = trim($m[1], " \t\"'");
    }
    if (!$title) {
        $title = 'Consultation';
    }
    $duration = isset($kv['duration']) ? max(15, (int) $kv['duration']) : 60;
    $type = $kv['type'] ?? $kv['appointment_type'] ?? 'meeting';
    $location = $kv['location'] ?? null;
    $status = 'scheduled';
    if ($portal === 'client') {
        $status = 'pending';
    }

    $uid = (int) $user['id'];
    $client = null;
    $lawyer = null;
    $case = ai_actions_find_case($pdo, $portal, $user, $message);

    if ($portal === 'client') {
        $client = $user;
        if (!empty($user['assigned_lawyer_id'])) {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="lawyer"');
            $stmt->execute([(int) $user['assigned_lawyer_id']]);
            $lawyer = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$lawyer && $case && !empty($case['lawyer_id'])) {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="lawyer"');
            $stmt->execute([(int) $case['lawyer_id']]);
            $lawyer = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$lawyer) {
            return 'I could not determine your assigned lawyer. Open Appointments and pick a lawyer, or mention the lawyer name.';
        }
    } elseif ($portal === 'lawyer') {
        $lawyer = $user;
        [$cf, $cl] = ai_actions_extract_person_name($message, 'client');
        $client = ai_actions_find_user_by_name($pdo, 'client', $cf, $cl, $kv['email'] ?? ai_actions_extract_email($message));
        if (!$client && $case) {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="client"');
            $stmt->execute([(int) $case['client_id']]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if ($client && !lawyer_can_access_client($pdo, $uid, (int) $client['id'])) {
            return 'That client is not assigned to you.';
        }
    } else {
        if (!ai_actions_can_admin($portal, $user)) {
            return 'You do not have permission to schedule appointments.';
        }
        [$cf, $cl] = ai_actions_extract_person_name($message, 'client');
        [$lf, $ll] = ai_actions_extract_person_name($message, 'lawyer');
        $client = ai_actions_find_user_by_name($pdo, 'client', $cf, $cl, $kv['client_email'] ?? null);
        $lawyer = ai_actions_find_user_by_name($pdo, 'lawyer', $lf, $ll, $kv['lawyer_email'] ?? null);
        if (!$client && !empty($kv['client'])) {
            $parts = preg_split('/\s+/', trim($kv['client'])) ?: [];
            $client = ai_actions_find_user_by_name($pdo, 'client', $parts[0] ?? null, $parts[1] ?? null);
        }
        if (!$lawyer && !empty($kv['lawyer'])) {
            $parts = preg_split('/\s+/', trim($kv['lawyer'])) ?: [];
            $lawyer = ai_actions_find_user_by_name($pdo, 'lawyer', $parts[0] ?? null, $parts[1] ?? null);
        }
        if (!$client && $case) {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
            $stmt->execute([(int) $case['client_id']]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$lawyer && $case && !empty($case['lawyer_id'])) {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
            $stmt->execute([(int) $case['lawyer_id']]);
            $lawyer = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }

    if (!$client || !$lawyer) {
        return "I need both a client and a lawyer to schedule.\n\n" . ai_actions_help_appointment();
    }

    $lawyerId = (int) $lawyer['id'];
    $clientId = (int) $client['id'];
    $caseId = $case ? (int) $case['id'] : (!empty($kv['case_id']) ? (int) $kv['case_id'] : null);

    $slotCheck = validate_lawyer_appointment_slot($pdo, $lawyerId, $when, $duration, null, false);
    $slotWarning = '';
    if (empty($slotCheck['ok'])) {
        $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM lawyer_availability_slots WHERE lawyer_id=?');
        $cntStmt->execute([$lawyerId]);
        $hasAnySlots = (int) $cntStmt->fetchColumn();
        if ($portal === 'admin' || $hasAnySlots === 0) {
            $slotWarning = "\n\n⚠️ Note: " . ($slotCheck['message'] ?? 'Slot outside published availability') . ' — booked anyway by AI for your portal.';
        } else {
            $reason = $slotCheck['message'] ?? 'Selected slot is not available.';
            return '⚠️ Could not schedule: ' . $reason . "\nAsk the lawyer to publish availability, or pick another time.";
        }
    }

    $pdo->prepare(
        'INSERT INTO appointments (title, description, appointment_type, case_id, client_id, lawyer_id, scheduled_at, duration_minutes, location, status, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $title, $kv['description'] ?? null, $type, $caseId, $clientId, $lawyerId,
        $when, $duration, $location, $status, $uid,
    ]);
    $apptId = (int) $pdo->lastInsertId();

    create_notification($pdo, $lawyerId, 'notify.appointment_scheduled', $title, 'appointment', '../lawyer/appointments.php', $uid);
    create_notification($pdo, $clientId, 'notify.appointment_scheduled', $title, 'appointment', '../client/appointments.php', $uid);
    log_activity($pdo, $uid, 'create', 'appointment', $apptId, 'Scheduled appointment via AI');

    $url = ai_actions_portal_url($portal, 'appointments.php');
    return "✅ Appointment " . ($status === 'pending' ? 'requested' : 'scheduled') . ".\n\n"
        . "• Title: **{$title}**\n"
        . "• When: " . format_datetime($when) . " ({$duration} min)\n"
        . "• Client: " . full_name($client) . "\n"
        . "• Lawyer: " . full_name($lawyer) . "\n"
        . "• Status: {$status}\n"
        . ($case ? '• Case: ' . ($case['case_number'] ?? ('#' . $caseId)) . "\n" : '')
        . "\n" . ai_actions_md_link('Open appointments', $url)
        . $slotWarning;
}

function ai_action_cancel_appointment(PDO $pdo, array $user, string $portal, string $message, string $q): string
{
    $uid = (int) $user['id'];
    $appt = null;

    if (preg_match('/\b(?:appointment|rdv|rendez-vous)\s*[#:]?\s*(\d+)\b/i', $message, $m)
        || preg_match('/\bid\s*[:=]\s*(\d+)\b/i', $message, $m)) {
        $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id=?');
        $stmt->execute([(int) $m[1]]);
        $appt = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (!$appt) {
        $when = ai_actions_extract_datetime($message);
        if ($portal === 'lawyer') {
            $sql = "SELECT * FROM appointments WHERE lawyer_id=? AND status IN ('pending','scheduled','confirmed','rescheduled')";
            $params = [$uid];
        } elseif ($portal === 'client') {
            $sql = "SELECT * FROM appointments WHERE client_id=? AND status IN ('pending','scheduled','confirmed','rescheduled')";
            $params = [$uid];
        } else {
            $sql = "SELECT * FROM appointments WHERE status IN ('pending','scheduled','confirmed','rescheduled')";
            $params = [];
        }
        if ($when) {
            $sql .= ' AND DATE(scheduled_at)=DATE(?)';
            $params[] = $when;
        }
        $sql .= ' ORDER BY scheduled_at ASC LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $appt = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (!$appt) {
        return 'I could not find an appointment to cancel. Try `Cancel appointment #12` or include the date.';
    }

    if ($portal === 'lawyer' && (int) $appt['lawyer_id'] !== $uid) {
        return 'That appointment is not assigned to you.';
    }
    if ($portal === 'client' && (int) $appt['client_id'] !== $uid) {
        return 'That appointment does not belong to you.';
    }
    if ($portal === 'admin' && !ai_actions_can_admin($portal, $user)) {
        return 'You do not have permission to cancel appointments.';
    }

    if (($appt['status'] ?? '') === 'cancelled') {
        return 'That appointment is already cancelled.';
    }

    $pdo->prepare('UPDATE appointments SET status="cancelled" WHERE id=?')->execute([(int) $appt['id']]);
    log_activity($pdo, $uid, 'update', 'appointment', (int) $appt['id'], 'Cancelled appointment via AI');

    if (!empty($appt['lawyer_id'])) {
        create_notification($pdo, (int) $appt['lawyer_id'], 'Appointment cancelled', (string) $appt['title'], 'appointment', '../lawyer/appointments.php', $uid);
    }
    if (!empty($appt['client_id'])) {
        create_notification($pdo, (int) $appt['client_id'], 'Appointment cancelled', (string) $appt['title'], 'appointment', '../client/appointments.php', $uid);
    }

    $url = ai_actions_portal_url($portal, 'appointments.php');
    return "✅ Appointment cancelled.\n\n"
        . "• #" . (int) $appt['id'] . " — **" . (string) $appt['title'] . "**\n"
        . "• Was scheduled: " . format_datetime($appt['scheduled_at']) . "\n\n"
        . ai_actions_md_link('Open appointments', $url);
}

/**
 * @param array<int, array<string, mixed>> $attachments
 */
function ai_action_upload_document(PDO $pdo, array $user, string $portal, string $message, string $q, array $attachments): string
{
    if (!$attachments) {
        return "Attach one or more files in the chat, then tell me the case.\n\n" . ai_actions_help_upload();
    }

    $case = ai_actions_find_case($pdo, $portal, $user, $message);
    if (!$case) {
        $hint = ai_actions_list_cases_hint($pdo, $portal, $user);
        $asked = '';
        if (preg_match('/\b(?:case|dossier)\s*[#:\-.]?\s*(\d{1,6})\b/i', $message, $m)
            || preg_match('/\b(CASE[-\s]?\d{4}[-\s]?\d{1,6})\b/i', $message, $m)) {
            $asked = ' I could not find **' . trim($m[0]) . '**.';
        }
        return "I need a valid target case.{$asked}\n\n{$hint}\n\n" . ai_actions_help_upload();
    }

    $kv = ai_actions_parse_kv($message);
    $category = $kv['category'] ?? 'legal';
    if (preg_match('/\bcategory\s*[:=]?\s*([a-z_\-]+)/i', $message, $m)) {
        $category = strtolower($m[1]);
    }
    $description = $kv['description'] ?? 'Uploaded via AI assistant';
    $uid = (int) $user['id'];
    $clientId = (int) $case['client_id'];
    $caseId = (int) $case['id'];
    $saved = [];

    $docDir = __DIR__ . '/../uploads/documents';
    if (!is_dir($docDir)) {
        mkdir($docDir, 0775, true);
    }

    foreach ($attachments as $att) {
        $srcRel = (string) ($att['file_path'] ?? '');
        $srcAbs = __DIR__ . '/../' . ltrim($srcRel, '/');
        if ($srcRel === '' || !is_file($srcAbs)) {
            continue;
        }
        $ext = strtolower(pathinfo((string) $att['file_name'], PATHINFO_EXTENSION));
        $stored = uniqid('doc_', true) . ($ext ? '.' . $ext : '');
        $destAbs = $docDir . '/' . $stored;
        if (!@copy($srcAbs, $destAbs)) {
            continue;
        }
        $newRel = 'uploads/documents/' . $stored;
        $title = $kv['title'] ?? (string) $att['file_name'];
        $pdo->prepare(
            'INSERT INTO case_documents (case_id, client_id, uploaded_by, title, file_name, file_path, file_type, file_size, category, description)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $caseId, $clientId, $uid, $title,
            (string) $att['file_name'], $newRel, (string) ($att['file_type'] ?? $ext),
            (int) ($att['file_size'] ?? 0), $category, $description,
        ]);
        $saved[] = $title;
    }

    if (!$saved) {
        return '⚠️ Upload failed — could not store the file(s) on the case.';
    }

    log_activity($pdo, $uid, 'create', 'document', $caseId, 'Uploaded document(s) via AI: ' . implode(', ', $saved));
    if ($portal !== 'client') {
        create_notification($pdo, $clientId, 'New document', 'A document was added to case ' . ($case['case_number'] ?? ''), 'document', '../client/documents.php', $uid);
    }

    $url = ai_actions_portal_url($portal === 'admin' ? 'admin' : $portal, $portal === 'admin' ? ('cases.php?action=view&id=' . $caseId . '&tab=documents') : 'documents.php');
    return "✅ Document(s) uploaded to case **{$case['case_number']}** — {$case['title']}.\n\n"
        . implode("\n", array_map(static fn($n) => '• ' . $n, $saved)) . "\n\n"
        . ai_actions_md_link('Open documents', $url);
}

function ai_action_draft_email(PDO $pdo, array $user, string $portal, string $message, string $q): string
{
    $kv = ai_actions_parse_kv($message);
    $case = ai_actions_find_case($pdo, $portal, $user, $message);
    [$cf, $cl] = ai_actions_extract_person_name($message, 'client');
    $toUser = ai_actions_find_user_by_name($pdo, 'client', $cf, $cl, $kv['email'] ?? ai_actions_extract_email($message));
    if (!$toUser && $case) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
        $stmt->execute([(int) $case['client_id']]);
        $toUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if (!$toUser && $portal === 'client') {
        // Draft to assigned lawyer
        if (!empty($user['assigned_lawyer_id'])) {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
            $stmt->execute([(int) $user['assigned_lawyer_id']]);
            $toUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }

    $topic = $kv['about'] ?? $kv['subject'] ?? null;
    if (!$topic && preg_match('/(?:about|regarding|re|sujet|concerning)\s+(.+)$/iu', $message, $m)) {
        $topic = trim($m[1], " \t\"'");
    }
    if (!$topic) {
        $topic = $case ? ('Update on case ' . ($case['case_number'] ?? '')) : 'Professional correspondence';
    }

    $company = trim((string) get_setting($pdo, 'company_name', app_config('name', 'LEGAL PRO')));
    $sender = full_name($user);
    if ($toUser) {
        $recipient = full_name($toUser);
        $toEmail = (string) ($toUser['email'] ?? ($kv['email'] ?? '[recipient@email.com]'));
    } elseif ($cf && $cl) {
        $recipient = trim($cf . ' ' . $cl);
        $toEmail = (string) ($kv['email'] ?? ai_actions_extract_email($message) ?? '[recipient@email.com]');
    } else {
        $recipient = 'Valued Client';
        $toEmail = (string) ($kv['email'] ?? ai_actions_extract_email($message) ?? '[recipient@email.com]');
    }
    $caseLine = $case ? ("Case: " . ($case['case_number'] ?? '') . " — " . ($case['title'] ?? '')) : '';

    // Prefer live LLM for higher-quality drafts when configured.
    $draftBody = null;
    if (function_exists('ai_llm_is_available') && ai_llm_is_available($pdo) && function_exists('ai_llm_request')) {
        try {
            $prompt = "Draft a concise professional legal email in the user's language.\n"
                . "From: {$sender} ({$company})\nTo: {$recipient} <{$toEmail}>\n"
                . ($caseLine ? $caseLine . "\n" : '')
                . "Topic/instructions: {$message}\n"
                . "Return only Subject + Body. No markdown fences.";
            $cfg = ai_llm_config($pdo);
            $system = 'You are a professional legal assistant drafting emails for a Mauritius law firm. Be clear, courteous, and concise.';
            $draftBody = ai_llm_request($cfg, $system, [['role' => 'user', 'content' => $prompt]]);
        } catch (Throwable $e) {
            $draftBody = null;
        }
    }

    if (!$draftBody) {
        $isHearing = (bool) preg_match('/\b(hearing|audience|court date|prochaine audience)\b/iu', $message . ' ' . (string) $topic);
        $subject = $isHearing
            ? ('Upcoming hearing' . ($case ? ' — ' . ($case['case_number'] ?? '') : ''))
            : ('Re: ' . $topic);
        if ($isHearing) {
            $hearingWhen = '';
            if ($case && !empty($case['next_hearing_date'])) {
                $hearingWhen = date('l, j F Y', strtotime((string) $case['next_hearing_date']));
            }
            $body = "Dear {$recipient},\n\n"
                . "I hope this message finds you well.\n\n"
                . "I am writing to remind you of your upcoming hearing"
                . ($hearingWhen !== '' ? " scheduled for **{$hearingWhen}**" : '')
                . ($caseLine ? " in respect of {$caseLine}" : '')
                . ".\n\n"
                . "Please arrive a little early and bring any documents previously requested. "
                . "If you have questions or cannot attend, contact our office as soon as possible so we can assist you.\n\n"
                . "Kind regards,\n{$sender}\n{$company}";
            // Keep plain text for copy/paste (no markdown bold in stored email)
            $body = str_replace('**', '', $body);
        } else {
            $body = "Dear {$recipient},\n\n"
                . "I hope this message finds you well.\n\n"
                . "I am writing regarding {$topic}."
                . ($caseLine ? "\n\n{$caseLine}." : '')
                . "\n\nPlease let me know a convenient time to discuss the next steps, or if you require any additional information from our side.\n\n"
                . "Kind regards,\n{$sender}\n{$company}";
        }
        $draftBody = "Subject: {$subject}\n\n{$body}";
    }

    // Store as in-app message thread when recipient known (does not require SMTP).
    $storedNote = '';
    if ($toUser && in_array($portal, ['admin', 'lawyer', 'client'], true)) {
        try {
            $subjectLine = 'Professional email draft';
            if (preg_match('/^Subject:\s*(.+)$/mi', $draftBody, $sm)) {
                $subjectLine = trim($sm[1]);
            }
            $bodyOnly = preg_replace('/^Subject:\s*.+\R+/mi', '', $draftBody, 1) ?? $draftBody;
            if ($portal === 'client') {
                $threadId = contact_create_thread($pdo, (int) $user['id'], (int) $toUser['id'], $case ? (int) $case['id'] : null, $subjectLine, trim($bodyOnly));
            } else {
                $receiverId = (int) $toUser['id'];
                $senderId = (int) $user['id'];
                // Admin/lawyer → client as a new contact-style message
                ensure_contact_message_columns($pdo);
                $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, case_id, subject, body, status) VALUES (?,?,?,?,?,?)')
                    ->execute([$senderId, $receiverId, $case ? (int) $case['id'] : null, $subjectLine, trim($bodyOnly), 'open']);
                $threadId = (int) $pdo->lastInsertId();
                $pdo->prepare('UPDATE messages SET thread_id=? WHERE id=?')->execute([$threadId, $threadId]);
            }
            create_notification($pdo, (int) $toUser['id'], 'New message', $subjectLine, 'message', null, (int) $user['id']);
            $storedNote = "\n\n✅ Also saved as an in-app message to **{$recipient}**.";
        } catch (Throwable $e) {
            $storedNote = "\n\n(Note: draft ready, but in-app save skipped: " . $e->getMessage() . ')';
        }
    }

    return "📧 Professional email draft\n\n"
        . "To: {$recipient} <{$toEmail}>\n\n"
        . "```\n" . trim($draftBody) . "\n```"
        . $storedNote
        . "\n\nSay `send email to {$recipient}` if you want me to attempt SMTP delivery (when configured).";
}

function ai_action_send_email(PDO $pdo, array $user, string $portal, string $message, string $q): string
{
    if ($portal === 'client') {
        return 'Clients cannot send SMTP email from AI. Use Contact / Messages, or ask your lawyer.';
    }
    if (!ai_actions_can_admin($portal, $user) && $portal !== 'lawyer') {
        return 'You do not have permission to send email.';
    }

    $draft = ai_action_draft_email($pdo, $user, $portal, $message, $q);
    $to = ai_actions_extract_email($message);
    if (!$to && preg_match('/To:.*? <([^>]+)>/', $draft, $m)) {
        $to = $m[1];
    }
    if (!$to) {
        [$cf, $cl] = ai_actions_extract_person_name($message, 'client');
        $u = ai_actions_find_user_by_name($pdo, 'client', $cf, $cl);
        $to = $u['email'] ?? null;
    }
    if (!$to) {
        return $draft . "\n\n⚠️ Could not determine recipient email for SMTP send.";
    }

    $subject = 'Message from ' . get_setting($pdo, 'company_name', app_config('name'));
    $body = $draft;
    if (preg_match('/```\s*([\s\S]+?)```/', $draft, $m)) {
        $body = trim($m[1]);
    }
    if (preg_match('/^Subject:\s*(.+)$/mi', $body, $sm)) {
        $subject = trim($sm[1]);
        $body = trim(preg_replace('/^Subject:\s*.+\R+/mi', '', $body, 1) ?? $body);
    }

    $from = trim((string) get_setting($pdo, 'smtp_from', ''));
    $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
    if ($from !== '') {
        $headers .= 'From: ' . $from . "\r\n";
    }
    $ok = @mail($to, $subject, $body, $headers);
    if (!$ok) {
        return $draft . "\n\n⚠️ SMTP/mail() delivery failed. The draft above is still ready to copy, and an in-app message may have been saved.";
    }
    return $draft . "\n\n✅ Email sent to {$to}.";
}

function ai_action_assign_lawyer(PDO $pdo, array $user, string $portal, string $message, string $q): string
{
    if (!ai_actions_can_admin($portal, $user)) {
        return 'Only admin/staff can assign lawyers to cases from AI.';
    }
    $case = ai_actions_find_case($pdo, $portal, $user, $message);
    [$lf, $ll] = ai_actions_extract_person_name($message, 'lawyer');
    $kv = ai_actions_parse_kv($message);
    $lawyer = null;
    if (!empty($kv['lawyer_id'])) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="lawyer"');
        $stmt->execute([(int) $kv['lawyer_id']]);
        $lawyer = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } elseif ($lf && $ll) {
        $lawyer = ai_actions_find_user_by_name($pdo, 'lawyer', $lf, $ll);
    } elseif (!empty($kv['lawyer'])) {
        $parts = preg_split('/\s+/', trim($kv['lawyer'])) ?: [];
        $lawyer = ai_actions_find_user_by_name($pdo, 'lawyer', $parts[0] ?? null, $parts[1] ?? null);
    }
    if (!$case || !$lawyer) {
        return 'Need both a case and a lawyer. Example: `Assign lawyer Marie Curie to case CASE-2026-001`';
    }
    $pdo->prepare('UPDATE cases SET lawyer_id=? WHERE id=?')->execute([(int) $lawyer['id'], (int) $case['id']]);
    create_notification($pdo, (int) $lawyer['id'], 'notify.case_assigned_short', 'A case has been assigned to you.', 'case', '../lawyer/cases.php', (int) $user['id']);
    log_activity($pdo, (int) $user['id'], 'update', 'case', (int) $case['id'], 'Assigned lawyer via AI');
    $url = ai_actions_portal_url('admin', 'cases.php?action=view&id=' . (int) $case['id']);
    return '✅ Assigned **' . full_name($lawyer) . '** to case **' . $case['case_number'] . "**.\n\n" . ai_actions_md_link('Open case', $url);
}

function ai_action_update_case_status(PDO $pdo, array $user, string $portal, string $message, string $q): string
{
    if (!ai_actions_can_admin($portal, $user) && $portal !== 'lawyer') {
        return 'You do not have permission to update case status.';
    }
    $case = ai_actions_find_case($pdo, $portal, $user, $message);
    if (!$case) {
        return 'Specify the case. Example: `Set case CASE-2026-001 status to closed`';
    }
    if ($portal === 'lawyer' && (int) $case['lawyer_id'] !== (int) $user['id']) {
        return 'That case is not assigned to you.';
    }

    $status = null;
    if (preg_match('/\b(open|active|pending|on_hold|reopened|closed)\b/i', $message, $m)) {
        $status = strtolower($m[1]);
    }
    if (str_contains($q, 'close case') || str_contains($q, 'fermer dossier')) {
        $status = 'closed';
    }
    if (str_contains($q, 'reopen')) {
        $status = 'reopened';
    }
    if (!$status) {
        return 'Specify a status: open, active, pending, on_hold, reopened, or closed.';
    }

    $pdo->prepare('UPDATE cases SET status=?, closed_at=IF(?="closed", COALESCE(closed_at, NOW()), NULL), updated_at=NOW() WHERE id=?')
        ->execute([$status, $status, (int) $case['id']]);
    log_activity($pdo, (int) $user['id'], 'update', 'case', (int) $case['id'], 'Updated case status via AI to ' . $status);
    create_notification($pdo, (int) $case['client_id'], 'notify.case_update', 'Case ' . $case['case_number'] . ' status: ' . $status, 'case', '../client/cases.php', (int) $user['id']);

    $url = ai_actions_portal_url($portal, $portal === 'admin' ? ('cases.php?action=view&id=' . (int) $case['id']) : 'cases.php');
    return "✅ Case **{$case['case_number']}** status set to **{$status}**.\n\n" . ai_actions_md_link('Open case', $url);
}
