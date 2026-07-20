<?php
/**
 * AI chat API — built-in (offline) legal assistant only.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai-mauritius-law.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => __('api.error.unauthorized')], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = current_user();
$pdo = db();

$contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
$isMultipart = str_contains(strtolower($contentType), 'multipart/form-data');

if ($isMultipart) {
    $sessionId = (int) ($_POST['session_id'] ?? 0);
    $message = trim((string) ($_POST['message'] ?? ''));
} else {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $sessionId = (int) ($input['session_id'] ?? 0);
    $message = trim((string) ($input['message'] ?? ''));
}

$attachments = [];
if ($isMultipart && !empty($_FILES['files'])) {
    try {
        $attachments = ai_collect_uploaded_files($_FILES['files']);
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($sessionId <= 0 || ($message === '' && !$attachments)) {
    http_response_code(422);
    echo json_encode(['error' => __('api.error.session_message_required')], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($message === '' && $attachments) {
    $message = __('ai.attach_default_prompt');
}

$stmt = $pdo->prepare('SELECT * FROM ai_chat_sessions WHERE id = ? AND user_id = ?');
$stmt->execute([$sessionId, $user['id']]);
$session = $stmt->fetch();
if (!$session) {
    http_response_code(404);
    echo json_encode(['error' => __('api.error.session_not_found')], JSON_UNESCAPED_UNICODE);
    exit;
}

$storedUserMessage = ai_format_user_message_with_attachments($message, $attachments);
$ins = $pdo->prepare('INSERT INTO ai_chat_messages (session_id, role, content) VALUES (?, ?, ?)');
$ins->execute([$sessionId, 'user', $storedUserMessage]);

$portal = $session['portal'];
$reply = generate_ai_reply($pdo, $user, $portal, $message, $attachments);

$ins->execute([$sessionId, 'assistant', $reply]);

$titleSql = "UPDATE ai_chat_sessions SET updated_at = NOW(), title = IF(title IN ('New Chat', ?), ?, title) WHERE id = ?";
$pdo->prepare($titleSql)->execute([__('ai.new_chat'), substr($message, 0, 60), $sessionId]);

echo json_encode([
    'reply' => $reply,
    'attachments' => array_map(static fn(array $a): array => [
        'name' => $a['file_name'],
        'size' => $a['file_size'],
        'type' => $a['file_type'],
    ], $attachments),
], JSON_UNESCAPED_UNICODE);
exit;

/**
 * @param array<int, array<string, mixed>> $attachments
 */
function generate_ai_reply(PDO $pdo, array $user, string $portal, string $message, array $attachments = []): string
{
    // Deterministic math first — more reliable than asking the LLM to arithmetic.
    if (!$attachments) {
        $calc = ai_try_calculate($message, $pdo, $user, $portal);
        if ($calc !== null) {
            return $calc;
        }
    }

    $context = build_portal_context($pdo, $user, $portal);
    return offline_ai_reply($pdo, $user, $portal, $message, $context, full_name($user), $attachments);
}

/**
 * Normalize $_FILES['files'] multi-upload into saved attachment records with optional text.
 *
 * @return list<array{file_name:string,file_path:string,file_type:string,file_size:int,ext:string,text:string}>
 */
function ai_collect_uploaded_files(array $filesField): array
{
    $out = [];
    $names = $filesField['name'] ?? null;
    if (!is_array($names)) {
        if (($filesField['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }
        $saved = handle_upload($filesField, 'ai');
        if (!$saved) {
            return [];
        }
        $ext = strtolower(pathinfo($saved['file_name'], PATHINFO_EXTENSION));
        $abs = __DIR__ . '/../' . $saved['file_path'];
        $out[] = [
            'file_name' => $saved['file_name'],
            'file_path' => $saved['file_path'],
            'file_type' => (string) $saved['file_type'],
            'file_size' => (int) $saved['file_size'],
            'ext' => $ext,
            'text' => ai_extract_file_text($abs, $ext),
        ];
        return $out;
    }

    $count = count($names);
    if ($count > 10) {
        throw new RuntimeException(__('ai.attach_too_many'));
    }

    for ($i = 0; $i < $count; $i++) {
        $err = (int) ($filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $one = [
            'name' => $filesField['name'][$i] ?? '',
            'type' => $filesField['type'][$i] ?? '',
            'tmp_name' => $filesField['tmp_name'][$i] ?? '',
            'error' => $err,
            'size' => (int) ($filesField['size'][$i] ?? 0),
        ];
        $saved = handle_upload($one, 'ai');
        if (!$saved) {
            continue;
        }
        $ext = strtolower(pathinfo($saved['file_name'], PATHINFO_EXTENSION));
        $abs = __DIR__ . '/../' . $saved['file_path'];
        $out[] = [
            'file_name' => $saved['file_name'],
            'file_path' => $saved['file_path'],
            'file_type' => (string) $saved['file_type'],
            'file_size' => (int) $saved['file_size'],
            'ext' => $ext,
            'text' => ai_extract_file_text($abs, $ext),
        ];
    }
    return $out;
}

/**
 * @param list<array{file_name:string,file_size:int}> $attachments
 */
function ai_format_user_message_with_attachments(string $message, array $attachments): string
{
    if (!$attachments) {
        return $message;
    }
    $lines = [$message, '', __('ai.attach_stored_header')];
    foreach ($attachments as $a) {
        $lines[] = '• ' . $a['file_name'] . ' (' . format_file_size((int) $a['file_size']) . ')';
    }
    return implode("\n", $lines);
}

function ai_extract_file_text(string $absolutePath, string $ext): string
{
    if (!is_file($absolutePath)) {
        return '';
    }
    $ext = strtolower($ext);
    try {
        if (in_array($ext, ['txt', 'csv', 'md', 'log'], true)) {
            $raw = (string) file_get_contents($absolutePath);
            return ai_truncate_text(ai_clean_extracted_text($raw));
        }
        if ($ext === 'docx') {
            return ai_truncate_text(ai_extract_docx_text($absolutePath));
        }
        if ($ext === 'pdf') {
            return ai_truncate_text(ai_extract_pdf_text($absolutePath));
        }
    } catch (Throwable $e) {
        return '';
    }
    return '';
}

function ai_clean_extracted_text(string $text): string
{
    $text = str_replace("\0", '', $text);
    $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    return trim($text);
}

function ai_truncate_text(string $text, int $max = 6000): string
{
    $text = ai_clean_extracted_text($text);
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strlen') && mb_strlen($text) > $max) {
        return mb_substr($text, 0, $max) . '…';
    }
    if (strlen($text) > $max) {
        return substr($text, 0, $max) . '…';
    }
    return $text;
}

function ai_extract_docx_text(string $path): string
{
    if (!class_exists('ZipArchive')) {
        return '';
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return '';
    }
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === false || $xml === '') {
        return '';
    }
    $xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
    $text = strip_tags($xml);
    return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function ai_extract_pdf_text(string $path): string
{
    $raw = (string) @file_get_contents($path);
    if ($raw === '') {
        return '';
    }
    $chunks = [];
    if (preg_match_all('/\((\\\\.|[^\\\\)]){2,}\)/s', $raw, $m)) {
        foreach ($m[0] as $token) {
            $inner = substr($token, 1, -1);
            $inner = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'], ["\n", '', ' ', '(', ')', '\\'], $inner);
            $inner = preg_replace('/\\\\[0-9]{3}/', '', $inner) ?? $inner;
            if (preg_match('/[A-Za-z0-9]{3,}/', $inner)) {
                $chunks[] = $inner;
            }
        }
    }
    if (preg_match_all('/[\x20-\x7E\r\n]{40,}/', $raw, $runs)) {
        foreach ($runs[0] as $run) {
            if (!str_starts_with($run, '%PDF') && preg_match('/[A-Za-z]{4,}/', $run)) {
                $chunks[] = $run;
            }
        }
    }
    return implode("\n", array_slice($chunks, 0, 200));
}

/**
 * Live figures the calculator can use (revenue, balances, counts…).
 *
 * @return array<string, array{value:float,label:string,money:bool}>
 */
function ai_calc_metrics(PDO $pdo, array $user, string $portal): array
{
    $uid = (int) ($user['id'] ?? 0);
    $metrics = [];

    $safe = static function (callable $fn, float $fallback = 0.0): float {
        try {
            return (float) $fn();
        } catch (Throwable $e) {
            return $fallback;
        }
    };

    if ($portal === 'admin' || $portal === 'staff') {
        $revenue = $safe(static fn() => (float) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM payments')->fetchColumn());
        $outstanding = $safe(static fn() => (float) $pdo->query(
            "SELECT COALESCE(SUM(total),0) FROM invoices WHERE status IN ('sent','partial','overdue')"
        )->fetchColumn());
        $clients = $safe(static fn() => (float) $pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn());
        $active = $safe(static fn() => (float) $pdo->query(
            "SELECT COUNT(*) FROM cases WHERE status IN ('open','active','pending','reopened','on_hold')"
        )->fetchColumn());
        $lawyers = $safe(static fn() => (float) $pdo->query("SELECT COUNT(*) FROM users WHERE role='lawyer' AND is_active=1")->fetchColumn());
        $metrics['revenue'] = ['value' => $revenue, 'label' => __('ai.offline.calc_metric.revenue'), 'money' => true];
        $metrics['outstanding'] = ['value' => $outstanding, 'label' => __('ai.offline.calc_metric.outstanding'), 'money' => true];
        $metrics['clients'] = ['value' => $clients, 'label' => __('ai.offline.calc_metric.clients'), 'money' => false];
        $metrics['cases'] = ['value' => $active, 'label' => __('ai.offline.calc_metric.active_cases'), 'money' => false];
        $metrics['lawyers'] = ['value' => $lawyers, 'label' => __('ai.offline.calc_metric.lawyers'), 'money' => false];
    } elseif ($portal === 'lawyer') {
        $active = $safe(static function () use ($pdo, $uid) {
            $s = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE lawyer_id=? AND status IN ('open','active','pending','reopened','on_hold')");
            $s->execute([$uid]);
            return (float) $s->fetchColumn();
        });
        $clients = $safe(static function () use ($pdo, $uid) {
            $s = $pdo->prepare(
                "SELECT COUNT(DISTINCT u.id) FROM users u
                 WHERE u.role='client' AND (u.assigned_lawyer_id=? OR u.id IN (SELECT client_id FROM cases WHERE lawyer_id=?))"
            );
            $s->execute([$uid, $uid]);
            return (float) $s->fetchColumn();
        });
        $metrics['cases'] = ['value' => $active, 'label' => __('ai.offline.calc_metric.active_cases'), 'money' => false];
        $metrics['clients'] = ['value' => $clients, 'label' => __('ai.offline.calc_metric.clients'), 'money' => false];
    } else {
        $outstanding = $safe(static function () use ($pdo, $uid) {
            $s = $pdo->prepare(
                "SELECT COALESCE(SUM(total),0) FROM invoices WHERE client_id=? AND status IN ('sent','partial','overdue')"
            );
            $s->execute([$uid]);
            return (float) $s->fetchColumn();
        });
        $paid = $safe(static function () use ($pdo, $uid) {
            $s = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE client_id=?');
            $s->execute([$uid]);
            return (float) $s->fetchColumn();
        });
        $active = $safe(static function () use ($pdo, $uid) {
            $s = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE client_id=? AND status IN ('open','active','pending','reopened','on_hold')");
            $s->execute([$uid]);
            return (float) $s->fetchColumn();
        });
        $metrics['outstanding'] = ['value' => $outstanding, 'label' => __('ai.offline.calc_metric.outstanding'), 'money' => true];
        $metrics['balance'] = ['value' => $outstanding, 'label' => __('ai.offline.calc_metric.balance'), 'money' => true];
        $metrics['paid'] = ['value' => $paid, 'label' => __('ai.offline.calc_metric.paid'), 'money' => true];
        $metrics['cases'] = ['value' => $active, 'label' => __('ai.offline.calc_metric.active_cases'), 'money' => false];
    }

    return $metrics;
}

/**
 * Replace metric words with numeric values so expressions like "10% of revenue" work.
 *
 * @param array<string, array{value:float,label:string,money:bool}> $metrics
 * @return array{text:string,used:list<string>,money:bool}
 */
function ai_calc_bind_metrics(string $text, array $metrics): array
{
    $used = [];
    $money = false;
    $aliases = [
        'revenue' => ['total revenue', 'our revenue', 'firm revenue', 'revenue', 'recettes', 'chiffre d\'affaires', 'chiffre daffaires'],
        'outstanding' => ['outstanding balance', 'outstanding', 'open invoices', 'impayes', 'impayés', 'en souffrance', 'montant du'],
        'balance' => ['my balance', 'solde restant', 'solde', 'balance'],
        'paid' => ['total paid', 'payments received', 'amount paid', 'paye', 'payé', 'regle', 'réglé'],
        'clients' => ['client count', 'number of clients', 'clients', 'nombre de clients'],
        'cases' => ['active cases', 'case count', 'number of cases', 'cases', 'dossiers actifs', 'dossiers', 'matters'],
        'lawyers' => ['lawyer count', 'number of lawyers', 'lawyers', 'avocats', 'counsel'],
    ];

    // Longest aliases first so "total revenue" beats "revenue"
    $pairs = [];
    foreach ($aliases as $key => $names) {
        if (!isset($metrics[$key])) {
            continue;
        }
        foreach ($names as $name) {
            $pairs[] = [$name, $key];
        }
    }
    usort($pairs, static fn($a, $b) => mb_strlen($b[0]) <=> mb_strlen($a[0]));

    $out = $text;
    foreach ($pairs as [$name, $key]) {
        $pattern = '/\b' . preg_quote($name, '/') . '\b/iu';
        if (!preg_match($pattern, $out)) {
            continue;
        }
        $val = $metrics[$key]['value'];
        $num = rtrim(rtrim(number_format($val, 4, '.', ''), '0'), '.');
        if ($num === '' || $num === '-') {
            $num = '0';
        }
        $out = preg_replace($pattern, ' ' . $num . ' ', $out) ?? $out;
        $used[] = $key;
        if (!empty($metrics[$key]['money'])) {
            $money = true;
        }
    }

    $out = preg_replace('/\s+/', ' ', $out) ?? $out;
    return ['text' => trim($out), 'used' => array_values(array_unique($used)), 'money' => $money];
}

/**
 * Try to answer a calculation request. Returns null if the message is not math.
 */
function ai_try_calculate(string $message, ?PDO $pdo = null, array $user = [], string $portal = 'admin'): ?string
{
    $raw = trim($message);
    if ($raw === '') {
        return null;
    }

    $metrics = [];
    $metricNote = '';
    $forceMoney = false;
    if ($pdo instanceof PDO) {
        $metrics = ai_calc_metrics($pdo, $user, $portal);
        $bound = ai_calc_bind_metrics(mb_strtolower($raw), $metrics);
        if ($bound['used']) {
            // Rebuild a workable phrase with numbers inserted into the original casing path via lowercase bind
            $rawForCalc = $bound['text'];
            $forceMoney = $bound['money'];
            $parts = [];
            foreach ($bound['used'] as $key) {
                $m = $metrics[$key];
                $parts[] = $m['label'] . ' = ' . ($m['money'] ? money($m['value']) : (string) (int) $m['value']);
            }
            $metricNote = implode('; ', $parts);
            $normalized = ai_calc_normalize($rawForCalc);
        } else {
            $normalized = ai_calc_normalize($raw);
        }
    } else {
        $normalized = ai_calc_normalize($raw);
    }

    if ($normalized === '') {
        return null;
    }

    // Word fractions / percent wording
    $normalized = preg_replace('/\b(\d+(?:\.\d+)?)\s*(?:percent|per\s*cent|pour\s*cent)\b/iu', '$1%', $normalized) ?? $normalized;
    $normalized = preg_replace('/\bhalf\s+of\b/iu', '0.5 *', $normalized) ?? $normalized;
    $normalized = preg_replace('/\b(?:a\s+)?third\s+of\b/iu', '(1/3) *', $normalized) ?? $normalized;
    $normalized = preg_replace('/\b(?:a\s+)?quarter\s+of\b/iu', '0.25 *', $normalized) ?? $normalized;
    $normalized = preg_replace('/\bdouble\b/iu', '2 *', $normalized) ?? $normalized;
    $normalized = preg_replace('/\btriple\b/iu', '3 *', $normalized) ?? $normalized;
    $normalized = preg_replace('/\bmoitie\s+de\b/iu', '0.5 *', $normalized) ?? $normalized;
    $normalized = preg_replace('/\bmoitié\s+de\b/iu', '0.5 *', $normalized) ?? $normalized;

    $intent = ai_calc_has_intent($raw, $normalized) || $metricNote !== '';
    // "10% of revenue" style after binding still has %
    if (!$intent && preg_match('/\d\s*%/', $normalized)) {
        $intent = true;
    }

    $pct = ai_calc_percent_result($normalized);
    if ($pct !== null) {
        $reply = ai_calc_format_reply($pct['expression'], $pct['result'], ($pct['money'] ?? true) || $forceMoney);
        if ($metricNote !== '') {
            $reply = __('ai.offline.calc_with_metric', ['metric' => $metricNote, 'result' => $reply]);
        }
        return $reply;
    }

    $expr = ai_calc_extract_expression($normalized);
    if ($expr === null) {
        // After metric bind, whole string may be "10 % of 27325.00" already handled; try remaining ops
        if (preg_match('/^[\d.+\-*\/^()%\s]+$/', $normalized)) {
            $expr = trim(str_replace('%', '', $normalized)); // lone % leftover
        }
    }
    if ($expr === null) {
        return null;
    }

    // Require a real expression (at least one operator), not a lone number — unless we used metrics with %
    $opCount = preg_match_all('#[+\-*/^]#', $expr);
    if ($opCount < 1) {
        return null;
    }
    if (!$intent && !preg_match('#\d#', $expr)) {
        return null;
    }

    $result = ai_calc_evaluate($expr);
    if ($result === null || !is_finite($result)) {
        return $intent ? __('ai.offline.calc_error') : null;
    }

    $reply = ai_calc_format_reply($expr, $result, $forceMoney || ai_calc_looks_monetary($raw));
    if ($metricNote !== '') {
        $reply = __('ai.offline.calc_with_metric', ['metric' => $metricNote, 'result' => $reply]);
    }
    return $reply;
}

function ai_calc_normalize(string $message): string
{
    $s = mb_strtolower($message);
    $s = str_replace(["\u{00A0}", '’', '‘'], [' ', "'", "'"], $s);
    // Strip currency labels but keep digits.
    $s = preg_replace('/\b(rs\.?|mur|rupees?|roupies?)\b/iu', ' ', $s) ?? $s;
    $s = preg_replace('/[₹$€£]/u', ' ', $s) ?? $s;

    $phrases = [
        'multiplied by' => '*',
        'multiply by' => '*',
        'divided by' => '/',
        'divide by' => '/',
        'to the power of' => '^',
        'power of' => '^',
        'multiplié par' => '*',
        'multiplie par' => '*',
        'divisé par' => '/',
        'divise par' => '/',
        'ajouté à' => '+',
        'ajoute a' => '+',
    ];
    foreach ($phrases as $from => $to) {
        $s = str_ireplace($from, " $to ", $s);
    }

    $words = [
        'plus' => '+',
        'minus' => '-',
        'times' => '*',
        'moins' => '-',
        'fois' => '*',
        'over' => '/',
    ];
    foreach ($words as $from => $to) {
        $s = preg_replace('/\b' . preg_quote($from, '/') . '\b/iu', " $to ", $s) ?? $s;
    }

    // Multiplication "x" / "×" only between numbers: 12 x 3
    $s = preg_replace('/(\d)\s*[x×]\s*(\d)/u', '$1 * $2', $s) ?? $s;
    $s = str_replace('÷', '/', $s);

    // Decimal commas between digits: 1.500,25 or 1500,25 → 1500.25
    $s = preg_replace_callback('/\d{1,3}(?:\.\d{3})+,\d+/', static function (array $m): string {
        return str_replace(['.', ','], ['', '.'], $m[0]);
    }, $s) ?? $s;
    $s = preg_replace('/(\d),(\d)/', '$1.$2', $s) ?? $s;

    $s = preg_replace('/\s+/', ' ', $s) ?? $s;
    return trim($s);
}

function ai_calc_has_intent(string $raw, string $normalized): bool
{
    $q = mb_strtolower($raw);
    if (preg_match('/\b(calculat|compute|math|arithmetic|vat|tva|percent|percentage|pour\s*cent)\b/iu', $q)) {
        return true;
    }
    if (preg_match('/\b(what\s+is|how\s+much\s+is|combien\s+(font|fait|égale|egale)|calcule[rz]?|calcul)\b/iu', $q)) {
        return true;
    }
    if (preg_match('/\d\s*%/', $normalized) && preg_match('/\b(of|on|to|de|sur|add|ajoute|ajouter|plus|revenue|outstanding|balance|recettes|solde)\b/iu', $q . ' ' . $normalized)) {
        return true;
    }
    if (preg_match('/\b(half|quarter|third|double|triple|moiti[eé])\b/iu', $q)) {
        return true;
    }
    // Pure expression like "12 + 3 * 4"
    if (preg_match('/^\s*[\d.+\-*\/^()%\s]+\s*$/', $normalized) && preg_match('/[+\-*\/^]/', $normalized)) {
        return true;
    }
    return false;
}

/**
 * Handle percent phrases: "15% of 1000", "add 15% to 1000", "20% VAT on 5000".
 *
 * @return array{expression:string,result:float,money?:bool}|null
 */
function ai_calc_percent_result(string $normalized): ?array
{
    // add/plus X% to/on A  → A * (1 + X/100)
    if (preg_match('/(?:add(?:ing)?|plus|ajoute(?:r)?|ajouter)\s+(\d+(?:\.\d+)?)\s*%\s*(?:to|onto|on|a|à|sur)?\s*(\d+(?:\.\d+)?)/iu', $normalized, $m)) {
        $pct = (float) $m[1];
        $amount = (float) $m[2];
        $result = $amount * (1 + $pct / 100);
        return [
            'expression' => $amount . ' + ' . $pct . '%',
            'result' => $result,
            'money' => true,
        ];
    }

    // remove/minus/less X% from/of A → A * (1 - X/100)
    if (preg_match('/(?:remove|minus|less|subtract|retire(?:r)?|enlève(?:r)?|enleve(?:r)?)\s+(\d+(?:\.\d+)?)\s*%\s*(?:from|of|de|sur)?\s*(\d+(?:\.\d+)?)/iu', $normalized, $m)) {
        $pct = (float) $m[1];
        $amount = (float) $m[2];
        $result = $amount * (1 - $pct / 100);
        return [
            'expression' => $amount . ' − ' . $pct . '%',
            'result' => $result,
            'money' => true,
        ];
    }

    // X% of/on/de/sur A  (VAT / share)
    if (preg_match('/(\d+(?:\.\d+)?)\s*%\s*(?:vat|tva|taxe?|tax)?\s*(?:of|on|de|des|du|sur|sur\s+un)\s*(\d+(?:\.\d+)?)/iu', $normalized, $m)) {
        $pct = (float) $m[1];
        $amount = (float) $m[2];
        $vat = $amount * ($pct / 100);
        $total = $amount + $vat;
        // If message mentions total / including / TTC, return gross; otherwise return the percent amount.
        if (preg_match('/\b(total|including|inclusive|ttc|gross|avec)\b/iu', $normalized)) {
            return [
                'expression' => $amount . ' + ' . $pct . '%',
                'result' => $total,
                'money' => true,
            ];
        }
        return [
            'expression' => $pct . '% × ' . $amount,
            'result' => $vat,
            'money' => true,
        ];
    }

    // A plus/with X% → A * (1 + X/100)
    if (preg_match('/(\d+(?:\.\d+)?)\s*(?:\+|plus|with|avec)\s*(\d+(?:\.\d+)?)\s*%/iu', $normalized, $m)) {
        $amount = (float) $m[1];
        $pct = (float) $m[2];
        return [
            'expression' => $amount . ' + ' . $pct . '%',
            'result' => $amount * (1 + $pct / 100),
            'money' => true,
        ];
    }

    return null;
}

function ai_calc_extract_expression(string $normalized): ?string
{
    // Prefer an explicit expression after "calculate:" / "calcule"
    if (preg_match('/(?:calculat(?:e|ion)?|compute|calcule[rz]?|calcul)\s*:?\s*([0-9.+\-*\/^()%\s]+)\s*$/iu', $normalized, $m)) {
        $expr = trim($m[1]);
        return $expr !== '' ? $expr : null;
    }

    // "what is 12+3" / "combien font 12+3"
    if (preg_match('/(?:what\s+is|how\s+much\s+is|combien\s+(?:font|fait|égale|egale)?)\s*([0-9.+\-*\/^()%\s]+)\s*\??$/iu', $normalized, $m)) {
        $expr = trim($m[1]);
        return $expr !== '' ? $expr : null;
    }

    // Whole message is mostly an expression
    if (preg_match('/^[\d.+\-*\/^()%\s]+$/', $normalized)) {
        $expr = trim($normalized);
        return $expr !== '' ? $expr : null;
    }

    // Pull the longest math-looking substring
    if (preg_match('/([0-9]+(?:\.[0-9]+)?(?:\s*[+\-*\/^]\s*[0-9]+(?:\.[0-9]+)?)+)/', $normalized, $m)) {
        return trim($m[1]);
    }

    return null;
}

function ai_calc_looks_monetary(string $raw): bool
{
    return (bool) preg_match('/\b(rs\.?|mur|rupee|roupie|fee|fees|invoice|amount|total|vat|tva|tax|bill|payment|cost|price|montant|facture|honoraire)\b/iu', $raw);
}

function ai_calc_format_reply(string $expression, float $result, bool $asMoney): string
{
    $displayExpr = preg_replace('/\s+/', ' ', trim($expression)) ?? $expression;
    if ($asMoney) {
        $formatted = money($result);
    } else {
        $formatted = (abs($result - round($result)) < 0.0000001)
            ? (string) (int) round($result)
            : rtrim(rtrim(number_format($result, 6, '.', ','), '0'), '.');
    }
    return __('ai.offline.calc_result', [
        'expression' => $displayExpr,
        'result' => $formatted,
    ]);
}

/**
 * Safe evaluator for + - * / ^ and parentheses. No eval().
 */
function ai_calc_evaluate(string $expression): ?float
{
    $expression = str_replace(['−', '–', '—'], '-', $expression);
    $expression = preg_replace('/\s+/', '', $expression) ?? '';
    if ($expression === '' || !preg_match('/^[\d.+\-*\/^()]+$/', $expression)) {
        return null;
    }

    $tokens = [];
    $len = strlen($expression);
    for ($i = 0; $i < $len; $i++) {
        $ch = $expression[$i];
        if (ctype_digit($ch) || $ch === '.') {
            $start = $i;
            $i++;
            while ($i < $len && (ctype_digit($expression[$i]) || $expression[$i] === '.')) {
                $i++;
            }
            $chunk = substr($expression, $start, $i - $start);
            if (!is_numeric($chunk)) {
                return null;
            }
            $tokens[] = ['n', (float) $chunk];
            $i--;
            continue;
        }
        if (str_contains('+-*/^()', $ch)) {
            // Unary minus / plus
            $prev = $tokens[count($tokens) - 1] ?? null;
            if (($ch === '-' || $ch === '+') && ($prev === null || ($prev[0] === 'o' && $prev[1] !== ')'))) {
                $tokens[] = ['o', $ch === '-' ? 'u-' : 'u+'];
            } else {
                $tokens[] = ['o', $ch];
            }
            continue;
        }
        return null;
    }

    $prec = ['u+' => 4, 'u-' => 4, '^' => 3, '*' => 2, '/' => 2, '+' => 1, '-' => 1];
    $rightAssoc = ['^' => true, 'u+' => true, 'u-' => true];
    $output = [];
    $ops = [];

    foreach ($tokens as $token) {
        if ($token[0] === 'n') {
            $output[] = $token[1];
            continue;
        }
        $op = $token[1];
        if ($op === '(') {
            $ops[] = $op;
            continue;
        }
        if ($op === ')') {
            while ($ops && end($ops) !== '(') {
                $output[] = array_pop($ops);
            }
            if (!$ops) {
                return null;
            }
            array_pop($ops);
            continue;
        }
        while ($ops) {
            $top = end($ops);
            if ($top === '(') {
                break;
            }
            $pTop = $prec[$top] ?? 0;
            $pOp = $prec[$op] ?? 0;
            $shouldPop = !empty($rightAssoc[$op]) ? ($pTop > $pOp) : ($pTop >= $pOp);
            if (!$shouldPop) {
                break;
            }
            $output[] = array_pop($ops);
        }
        $ops[] = $op;
    }
    while ($ops) {
        $op = array_pop($ops);
        if ($op === '(' || $op === ')') {
            return null;
        }
        $output[] = $op;
    }

    $stack = [];
    foreach ($output as $item) {
        if (is_float($item) || is_int($item)) {
            $stack[] = (float) $item;
            continue;
        }
        if ($item === 'u-' || $item === 'u+') {
            if (!$stack) {
                return null;
            }
            $a = array_pop($stack);
            $stack[] = $item === 'u-' ? -$a : $a;
            continue;
        }
        if (count($stack) < 2) {
            return null;
        }
        $b = array_pop($stack);
        $a = array_pop($stack);
        $stack[] = match ($item) {
            '+' => $a + $b,
            '-' => $a - $b,
            '*' => $a * $b,
            '/' => abs($b) < 1e-12 ? null : $a / $b,
            '^' => (abs($b) > 12 ? null : $a ** $b),
            default => null,
        };
        if (end($stack) === null) {
            return null;
        }
    }

    if (count($stack) !== 1) {
        return null;
    }
    $value = $stack[0];
    if (!is_finite($value) || abs($value) > 1e15) {
        return null;
    }
    return $value;
}

function build_portal_context(PDO $pdo, array $user, string $portal): string
{
    if ($portal === 'admin') {
        $clients = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn();
        $lawyers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='lawyer'")->fetchColumn();
        $active = (int) $pdo->query("SELECT COUNT(*) FROM cases WHERE status IN ('open','active','pending','reopened')")->fetchColumn();
        $revenue = (float) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM payments')->fetchColumn();
        return __('ai.context.firm_stats', [
            'clients' => $clients,
            'lawyers' => $lawyers,
            'active' => $active,
            'revenue' => money($revenue),
        ]);
    }
    if ($portal === 'lawyer') {
        $stmt = $pdo->prepare("SELECT case_number, title, status FROM cases WHERE lawyer_id = ? ORDER BY updated_at DESC LIMIT 5");
        $stmt->execute([$user['id']]);
        $cases = $stmt->fetchAll();
        $lines = array_map(fn($c) => "{$c['case_number']} (" . translate_status($c['status']) . "): " . t_content($c['title']), $cases);
        return __('ai.context.assigned_cases') . "\n" . (implode("\n", $lines) ?: __('ai.context.none'));
    }
    $stmt = $pdo->prepare("SELECT case_number, title, status FROM cases WHERE client_id = ? ORDER BY updated_at DESC LIMIT 5");
    $stmt->execute([$user['id']]);
    $cases = $stmt->fetchAll();
    $lines = array_map(fn($c) => "{$c['case_number']} (" . translate_status($c['status']) . "): " . t_content($c['title']), $cases);
    $inv = $pdo->prepare("SELECT invoice_number, total, status FROM invoices WHERE client_id = ? ORDER BY created_at DESC LIMIT 5");
    $inv->execute([$user['id']]);
    $invoices = $inv->fetchAll();
    $invLines = array_map(fn($i) => "{$i['invoice_number']}: " . money($i['total']) . " (" . translate_status($i['status']) . ")", $invoices);
    return __('ai.context.your_cases') . "\n" . (implode("\n", $lines) ?: __('ai.context.none'))
        . "\n" . __('ai.context.your_invoices') . "\n" . (implode("\n", $invLines) ?: __('ai.context.none'));
}

function offline_ai_reply(PDO $pdo, array $user, string $portal, string $message, string $context, string $name, array $attachments = []): string
{
    $q = mb_strtolower($message);
    $uid = (int) ($user['id'] ?? 0);
    $base = rtrim((string) app_config('url', ''), '/');
    $portalUrl = static function (string $path) use ($base, $portal): string {
        $path = ltrim($path, '/');
        return $base . '/' . $portal . '/' . $path;
    };
    $md = static function (string $label, string $url): string {
        return '[' . $label . '](' . $url . ')';
    };
    $withLink = static function (string $text, string $label, string $url) use ($md): string {
        return rtrim($text) . "\n\n" . $md($label, $url);
    };

    if ($attachments) {
        $fileLines = [];
        $textBlocks = [];
        $imageCount = 0;
        foreach ($attachments as $a) {
            $fileLines[] = '• ' . $a['file_name'] . ' (' . format_file_size((int) $a['file_size']) . ')';
            $ext = strtolower((string) ($a['ext'] ?? ''));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $imageCount++;
            }
            $excerpt = trim((string) ($a['text'] ?? ''));
            if ($excerpt !== '') {
                $preview = function_exists('mb_substr') ? mb_substr($excerpt, 0, 1200) : substr($excerpt, 0, 1200);
                $textBlocks[] = '— ' . $a['file_name'] . " —\n" . $preview;
            }
        }
        $wantsSummary = (bool) preg_match('/\b(summar|overview|explain|review|analys|what does|read|extract|contenu|résum|resum|explique|analyse)\b/iu', $q);
        if ($textBlocks && ($wantsSummary || $message === __('ai.attach_default_prompt') || count($attachments) > 0)) {
            $body = __('ai.offline.attach_with_text', [
                'files' => implode("\n", $fileLines),
                'excerpt' => implode("\n\n", array_slice($textBlocks, 0, 3)),
            ]);
            return $body;
        }
        return __('ai.offline.attach_received', [
            'files' => implode("\n", $fileLines),
            'hint' => $imageCount > 0
                ? __('ai.offline.attach_image_hint')
                : __('ai.offline.attach_ask_hint'),
        ]);
    }

    $caseUrl = static function (int $caseId) use ($portal, $portalUrl): string {
        if ($caseId < 1) {
            return $portalUrl('cases.php');
        }
        if ($portal === 'admin') {
            return $portalUrl('cases.php?action=view&id=' . $caseId);
        }
        return $portalUrl('cases.php?id=' . $caseId);
    };
    $appointmentUrl = static function (int $appointmentId = 0) use ($portal, $portalUrl): string {
        if ($appointmentId < 1 || $portal === 'client') {
            return $portalUrl('appointments.php');
        }
        return $portalUrl('appointments.php?action=edit&id=' . $appointmentId);
    };
    $hearingUrl = static function (int $hearingId = 0) use ($portal, $portalUrl): string {
        if ($hearingId < 1) {
            return $portalUrl('court.php');
        }
        if ($portal === 'client') {
            return $portalUrl('court.php?action=view&id=' . $hearingId);
        }
        return $portalUrl('court.php?action=edit&id=' . $hearingId);
    };
    $adminPortal = ($portal === 'admin' || $portal === 'staff');
    $invoiceUrl = static function (int $invoiceId = 0) use ($portal, $portalUrl, $base, $adminPortal): string {
        if ($portal === 'client') {
            return $portalUrl('payments.php');
        }
        if ($adminPortal) {
            if ($invoiceId > 0) {
                return $base . '/admin/invoice.php?id=' . $invoiceId;
            }
            return $base . '/admin/cases.php';
        }
        // Lawyers manage billing from assigned cases, not admin invoice pages.
        return $portalUrl('cases.php');
    };
    $receiptUrl = static function (int $paymentId = 0) use ($portal, $portalUrl, $base, $adminPortal, $pdo): string {
        if ($portal === 'client') {
            return $paymentId > 0
                ? $portalUrl('receipt.php?id=' . $paymentId)
                : $portalUrl('payments.php');
        }
        $caseId = 0;
        if ($paymentId > 0) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT COALESCE(i.case_id, 0) FROM payments p
                     LEFT JOIN invoices i ON i.id = p.invoice_id
                     WHERE p.id = ?'
                );
                $stmt->execute([$paymentId]);
                $caseId = (int) $stmt->fetchColumn();
            } catch (Throwable $e) {
                $caseId = 0;
            }
        }
        if ($adminPortal) {
            if ($caseId > 0) {
                return $base . '/admin/cases.php?action=view&id=' . $caseId . '&tab=receipts';
            }
            return $paymentId > 0
                ? $base . '/admin/receipt.php?id=' . $paymentId
                : $base . '/admin/cases.php';
        }
        if ($caseId > 0) {
            return $portalUrl('cases.php?id=' . $caseId);
        }
        return $portalUrl('cases.php');
    };
    $financeUrl = static function () use ($portal, $portalUrl, $base, $adminPortal): string {
        if ($portal === 'client') {
            return $portalUrl('payments.php');
        }
        if ($adminPortal) {
            return $base . '/admin/cases.php';
        }
        return $portalUrl('cases.php');
    };
    $resolveStoredLink = static function (string $link) use ($base, $portal, $portalUrl): string {
        $effectivePortal = ($portal === 'staff') ? 'admin' : $portal;
        $portalBase = $base . '/' . $effectivePortal;
        return notification_open_url($link, $portalBase, $base, $portalUrl('notifications.php'));
    };

    $count = static function (string $sql, array $params = []) use ($pdo): int {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    };
    $sum = static function (string $sql, array $params = []) use ($pdo): float {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (float) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0.0;
        }
    };
    $rows = static function (string $sql, array $params = []) use ($pdo): array {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    };

    // --- Quick-prompt / factual answers (order matters: specific before generic) ---

    // Mauritius law rules + legal-system definitions (before court/hearing keyword matches)
    $lawReply = ai_try_mauritius_law_reply($message);
    if ($lawReply !== null) {
        return $lawReply;
    }

    // Lawyer workload (before generic "summar")
    if (
        str_contains($q, 'lawyer workload') || str_contains($q, 'workload and availability')
        || str_contains($q, 'charge de travail') || str_contains($q, 'disponibilité des avocat')
        || str_contains($q, 'disponibilite des avocat')
    ) {
        if ($portal === 'admin') {
            $list = $rows(
                "SELECT u.id, u.first_name, u.last_name,
                        (SELECT COUNT(*) FROM cases c WHERE c.lawyer_id=u.id AND c.status IN ('open','active','pending','reopened','on_hold')) AS active_cases,
                        (SELECT COUNT(*) FROM appointments a WHERE a.lawyer_id=u.id AND a.scheduled_at>=NOW() AND a.status IN ('scheduled','confirmed','rescheduled','pending')) AS upcoming_appts
                 FROM users u
                 WHERE u.role='lawyer' AND u.is_active=1
                 ORDER BY active_cases DESC, u.last_name ASC
                 LIMIT 12"
            );
            if (!$list) {
                return $withLink(__('ai.offline.answer.workload_none'), __('ai.offline.open_page'), $portalUrl('lawyers.php'));
            }
            $lines = array_map(static function (array $r): string {
                return '• ' . trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))
                    . ' — ' . (int) $r['active_cases'] . ' ' . __('ai.offline.meta.active_cases')
                    . ', ' . (int) $r['upcoming_appts'] . ' ' . __('ai.offline.meta.upcoming_appts');
            }, $list);
            return $withLink(
                __('ai.offline.answer.workload', ['list' => implode("\n", $lines)]),
                __('ai.offline.open_page'),
                $portalUrl('lawyers.php')
            );
        }
    }

    // Case timeline (before generic summar)
    if (
        str_contains($q, 'timeline') || str_contains($q, 'chronologie')
        || str_contains($q, 'build a timeline') || str_contains($q, 'most recent case')
    ) {
        $caseRow = null;
        if ($portal === 'lawyer') {
            $caseRow = $rows(
                "SELECT id, case_number, title, status, opened_at, updated_at FROM cases WHERE lawyer_id=? ORDER BY updated_at DESC LIMIT 1",
                [$uid]
            )[0] ?? null;
        } elseif ($portal === 'client') {
            $caseRow = $rows(
                "SELECT id, case_number, title, status, opened_at, updated_at FROM cases WHERE client_id=? ORDER BY updated_at DESC LIMIT 1",
                [$uid]
            )[0] ?? null;
        } elseif ($portal === 'admin') {
            $caseRow = $rows(
                "SELECT id, case_number, title, status, opened_at, updated_at FROM cases ORDER BY updated_at DESC LIMIT 1"
            )[0] ?? null;
        }
        if (!$caseRow) {
            return $withLink(__('ai.offline.answer.timeline_none'), __('ai.offline.open_page'), $portalUrl('cases.php'));
        }
        $cid = (int) $caseRow['id'];
        $events = [];
        if (!empty($caseRow['opened_at'])) {
            $events[] = format_datetime($caseRow['opened_at']) . ' — ' . __('ai.offline.meta.case_opened');
        }
        foreach ($rows(
            "SELECT scheduled_at AS dt, title AS label FROM appointments WHERE case_id=? ORDER BY scheduled_at ASC LIMIT 6",
            [$cid]
        ) as $e) {
            $events[] = format_datetime($e['dt']) . ' — ' . __('ai.offline.meta.appointment') . ': ' . t_content($e['label']);
        }
        foreach ($rows(
            "SELECT hearing_date AS dt, court_name AS label FROM court_hearings WHERE case_id=? ORDER BY hearing_date ASC LIMIT 6",
            [$cid]
        ) as $e) {
            $events[] = format_datetime($e['dt']) . ' — ' . __('ai.offline.meta.hearing') . ': ' . t_content($e['label']);
        }
        if (!$events) {
            $events[] = __('ai.offline.meta.no_events');
        }
        return $withLink(
            __('ai.offline.answer.timeline', [
                'case' => $caseRow['case_number'] . ' — ' . t_content($caseRow['title']),
                'status' => translate_status($caseRow['status']),
                'list' => implode("\n", array_map(static fn($l) => '• ' . $l, $events)),
            ]),
            __('ai.offline.open_page'),
            $caseUrl($cid)
        );
    }

    // New case draft / draft letter (before generic summar that catches "résumé")
    if (
        str_contains($q, 'draft a new case') || str_contains($q, 'new case summary')
        || str_contains($q, 'ouverture de dossier') || str_contains($q, 'draft a legal letter')
        || str_contains($q, 'correspondance juridique')
        || ((str_contains($q, 'draft') || str_contains($q, 'letter') || str_contains($q, 'contrat') || str_contains($q, 'lettre') || str_contains($q, 'rédig') || str_contains($q, 'redig'))
            && (str_contains($q, 'case') || str_contains($q, 'dossier') || str_contains($q, 'letter') || str_contains($q, 'lettre') || str_contains($q, 'contract')))
    ) {
        return __('ai.offline.draft');
    }

    // Document checklist (client)
    if (
        str_contains($q, 'checklist') || str_contains($q, 'document checklist')
        || str_contains($q, 'liste des pièces') || str_contains($q, 'liste de controle') || str_contains($q, 'liste de contrôle')
        || str_contains($q, 'pièces requises') || str_contains($q, 'pieces requises')
    ) {
        return $withLink(__('ai.offline.answer.checklist'), __('ai.offline.open_page'), $portalUrl($portal === 'client' ? 'documents.php' : 'cases.php'));
    }

    // Document Q&A how-to / recent documents
    if (
        str_contains($q, 'ask questions about an uploaded') || str_contains($q, 'how can i ask questions about')
        || str_contains($q, 'interroger une pièce') || str_contains($q, 'interroger une piece')
        || str_contains($q, 'document qa') || str_contains($q, 'pièce déposée') || str_contains($q, 'piece deposee')
    ) {
        return __('ai.offline.attach_how_to');
    }

    if (
        str_contains($q, 'explain my recent document') || str_contains($q, 'recent documents')
        || str_contains($q, 'mes pièces récentes') || str_contains($q, 'mes pieces recentes')
        || str_contains($q, 'expliquer clairement mes pièces') || str_contains($q, 'expliquer clairement mes pieces')
    ) {
        if ($portal === 'client') {
            $list = $rows(
                "SELECT d.title, d.file_name, d.uploaded_at, c.case_number
                 FROM case_documents d
                 JOIN cases c ON c.id=d.case_id
                 WHERE c.client_id=?
                 ORDER BY d.uploaded_at DESC LIMIT 8",
                [$uid]
            );
        } elseif ($portal === 'lawyer') {
            $list = $rows(
                "SELECT d.title, d.file_name, d.uploaded_at, c.case_number
                 FROM case_documents d
                 JOIN cases c ON c.id=d.case_id
                 WHERE c.lawyer_id=?
                 ORDER BY d.uploaded_at DESC LIMIT 8",
                [$uid]
            );
        } else {
            $list = $rows(
                "SELECT d.title, d.file_name, d.uploaded_at, c.case_number
                 FROM case_documents d
                 JOIN cases c ON c.id=d.case_id
                 ORDER BY d.uploaded_at DESC LIMIT 8"
            );
        }
        if (!$list) {
            return $withLink(__('ai.offline.answer.documents_none'), __('ai.offline.open_page'), $portalUrl($portal === 'admin' ? 'cases.php' : 'documents.php'));
        }
        $lines = array_map(static function (array $d): string {
            return '• ' . ($d['case_number'] ?? '') . ' — ' . t_content($d['title'] ?: $d['file_name'])
                . ' (' . format_datetime($d['uploaded_at']) . ')';
        }, $list);
        return $withLink(
            __('ai.offline.answer.documents', ['list' => implode("\n", $lines)]),
            __('ai.offline.open_page'),
            $portalUrl($portal === 'admin' ? 'cases.php' : 'documents.php')
        );
    }

    // Latest invoice help (client) — before generic invoice/payment
    if (
        str_contains($q, 'latest invoice') || str_contains($q, 'explain my latest invoice')
        || str_contains($q, 'dernière facture') || str_contains($q, 'derniere facture')
        || str_contains($q, 'ma dernière facture') || str_contains($q, 'ma derniere facture')
    ) {
        if ($portal === 'client') {
            $inv = $rows(
                "SELECT invoice_number, total, status, due_date, issued_at FROM invoices WHERE client_id=? ORDER BY created_at DESC LIMIT 1",
                [$uid]
            )[0] ?? null;
            if (!$inv) {
                return $withLink(__('ai.offline.answer.invoice_none'), __('ai.offline.open_page'), $portalUrl('payments.php'));
            }
            return $withLink(
                __('ai.offline.answer.invoice_latest', [
                    'number' => $inv['invoice_number'],
                    'amount' => money($inv['total']),
                    'status' => translate_status($inv['status']),
                    'due' => $inv['due_date'] ? format_date($inv['due_date']) : '—',
                ]),
                __('ai.offline.open_page'),
                $portalUrl('payments.php')
            );
        }
    }

    // Outstanding balance (before generic "outstanding" invoices)
    if (
        str_contains($q, 'outstanding balance') || str_contains($q, 'solde restant')
        || (str_contains($q, 'balance') && (str_contains($q, 'outstanding') || str_contains($q, 'my ')))
        || (str_contains($q, 'solde') && (str_contains($q, 'dû') || str_contains($q, 'du') || str_contains($q, 'impay')))
    ) {
        if ($portal === 'client') {
            $owed = $sum(
                "SELECT COALESCE(SUM(total - IFNULL((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id=invoices.id),0)),0)
                 FROM invoices WHERE client_id=? AND status IN ('sent','partial','overdue')",
                [$uid]
            );
            // Fallback if expression unsupported
            if ($owed <= 0) {
                $owed = $sum(
                    "SELECT COALESCE(SUM(total),0) FROM invoices WHERE client_id=? AND status IN ('sent','partial','overdue')",
                    [$uid]
                );
            }
            return $withLink(
                __('ai.offline.answer.balance_client', ['amount' => money($owed)]),
                __('ai.offline.open_page'),
                $portalUrl('payments.php')
            );
        }
    }

    // Lawyer client list (before "how many clients")
    if (
        ($portal === 'lawyer') && (
            str_contains($q, 'list clients') || str_contains($q, 'clients i am working')
            || str_contains($q, 'my clients') || str_contains($q, 'liste des clients')
            || str_contains($q, 'clients dont j') || str_contains($q, 'établir la liste des clients')
            || str_contains($q, 'etablir la liste des clients')
        )
    ) {
        $list = $rows(
            "SELECT DISTINCT u.id, u.first_name, u.last_name, u.company_name
             FROM users u
             WHERE u.role='client' AND (
                u.assigned_lawyer_id=? OR u.id IN (SELECT client_id FROM cases WHERE lawyer_id=?)
             )
             ORDER BY u.last_name ASC, u.first_name ASC
             LIMIT 15",
            [$uid, $uid]
        );
        if (!$list) {
            return $withLink(__('ai.offline.answer.client_list_none'), __('ai.offline.open_page'), $portalUrl('clients.php'));
        }
        $lines = array_map(static function (array $c): string {
            $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
            if (!empty($c['company_name'])) {
                $name .= ' (' . $c['company_name'] . ')';
            }
            return '• ' . $name;
        }, $list);
        return $withLink(
            __('ai.offline.answer.client_list', ['list' => implode("\n", $lines)]),
            __('ai.offline.open_page'),
            $portalUrl('clients.php')
        );
    }

    // Pending tasks (before appointments — prompt mentions appointments too)
    if (
        str_contains($q, 'pending task') || str_contains($q, 'need my response')
        || str_contains($q, 'nécessitent ma réponse') || str_contains($q, 'necessitent ma reponse')
        || str_contains($q, 'tâches') || str_contains($q, 'taches')
    ) {
        if ($portal === 'lawyer' || $portal === 'admin') {
            $pendingAppts = $rows(
                $portal === 'lawyer'
                    ? "SELECT id, title, scheduled_at, status FROM appointments
                       WHERE lawyer_id=? AND status='pending' ORDER BY scheduled_at ASC LIMIT 10"
                    : "SELECT id, title, scheduled_at, status FROM appointments
                       WHERE status='pending' ORDER BY scheduled_at ASC LIMIT 10",
                $portal === 'lawyer' ? [$uid] : []
            );
            $unread = $count('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$uid]);
            if (!$pendingAppts && $unread < 1) {
                return $withLink(__('ai.offline.answer.tasks_none'), __('ai.offline.open_page'), $portalUrl('appointments.php'));
            }
            $lines = array_map(static function (array $a) use ($appointmentUrl, $md): string {
                return '• ' . format_datetime($a['scheduled_at']) . ' — ' . t_content($a['title']) . ' (' . translate_status($a['status']) . ")\n  "
                    . $md(__('ai.offline.open_page'), $appointmentUrl((int) $a['id']));
            }, $pendingAppts);
            if ($unread > 0) {
                $lines[] = '• ' . __('ai.offline.meta.unread_notifications', ['count' => (string) $unread]);
            }
            return $withLink(
                __('ai.offline.answer.tasks', ['list' => implode("\n", $lines) ?: __('ai.context.none')]),
                __('ai.offline.open_page'),
                $portalUrl('appointments.php')
            );
        }
    }

    // Client count
    if (
        str_contains($q, 'how many client') || str_contains($q, 'client count')
        || str_contains($q, 'nombre de client') || str_contains($q, 'combien de client')
        || (($portal === 'admin') && str_contains($q, 'clients') && (str_contains($q, 'how many') || str_contains($q, 'do we have') || str_contains($q, 'avons') || str_contains($q, 'compte-t')))
    ) {
        if ($portal === 'admin') {
            $n = $count("SELECT COUNT(*) FROM users WHERE role='client'");
            return $withLink(
                __('ai.offline.answer.clients_admin', ['count' => (string) $n]),
                __('ai.offline.open_page'),
                $portalUrl('clients.php')
            );
        }
        if ($portal === 'lawyer') {
            $n = $count(
                "SELECT COUNT(DISTINCT u.id) FROM users u
                 WHERE u.role='client' AND (u.assigned_lawyer_id=? OR u.id IN (SELECT client_id FROM cases WHERE lawyer_id=?))",
                [$uid, $uid]
            );
            return $withLink(
                __('ai.offline.answer.clients_lawyer', ['count' => (string) $n]),
                __('ai.offline.open_page'),
                $portalUrl('clients.php')
            );
        }
    }

    // Active / my cases
    if (
        str_contains($q, 'active case') || str_contains($q, 'how many active')
        || str_contains($q, 'dossiers en cours') || (str_contains($q, 'combien') && str_contains($q, 'dossier'))
        || str_contains($q, 'my cases') || str_contains($q, 'mes dossiers')
        || str_contains($q, 'summarize my assigned') || str_contains($q, 'résumer mes dossiers')
        || str_contains($q, 'resumer mes dossiers') || str_contains($q, 'dossiers désignés')
        || str_contains($q, 'dossiers designes') || str_contains($q, 'cases in plain language')
        || str_contains($q, 'dossiers en termes clairs')
    ) {
        if ($portal === 'admin') {
            $n = $count("SELECT COUNT(*) FROM cases WHERE status IN ('open','active','pending','reopened','on_hold')");
            return $withLink(
                __('ai.offline.answer.active_cases_admin', ['count' => (string) $n, 'context' => $context]),
                __('ai.offline.open_page'),
                $portalUrl('cases.php')
            );
        }
        if ($portal === 'lawyer') {
            $n = $count("SELECT COUNT(*) FROM cases WHERE lawyer_id=? AND status IN ('open','active','pending','reopened','on_hold')", [$uid]);
            return $withLink(
                __('ai.offline.answer.active_cases_lawyer', ['count' => (string) $n, 'context' => $context]),
                __('ai.offline.open_page'),
                $portalUrl('cases.php')
            );
        }
        $n = $count("SELECT COUNT(*) FROM cases WHERE client_id=? AND status IN ('open','active','pending','reopened','on_hold')", [$uid]);
        return $withLink(
            __('ai.offline.answer.active_cases_client', ['count' => (string) $n, 'context' => $context]),
            __('ai.offline.open_page'),
            $portalUrl('cases.php')
        );
    }

    // Revenue by month BEFORE generic revenue
    if (
        ($portal === 'admin') && (
            str_contains($q, 'revenue by month') || str_contains($q, 'break down revenue')
            || str_contains($q, 'recettes par mois') || str_contains($q, 'détailler les recettes')
            || str_contains($q, 'detailler les recettes')
        )
    ) {
        $page = $financeUrl();
        $list = $rows(
            "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS ym, COALESCE(SUM(amount),0) AS total
             FROM payments
             WHERE paid_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
             GROUP BY ym
             ORDER BY ym ASC"
        );
        if (!$list) {
            return $withLink(__('ai.offline.answer.revenue_month_none'), __('ai.offline.open_page'), $page);
        }
        $lines = array_map(static function (array $r): string {
            return '• ' . $r['ym'] . ': ' . money($r['total']);
        }, $list);
        return $withLink(
            __('ai.offline.answer.revenue_month', ['list' => implode("\n", $lines)]),
            __('ai.offline.open_page'),
            $page
        );
    }

    if (
        str_contains($q, 'total revenue') || str_contains($q, 'our total revenue')
        || (str_contains($q, 'revenue') && !str_contains($q, 'month'))
        || str_contains($q, 'recettes') || str_contains($q, 'chiffre')
    ) {
        if ($portal === 'admin') {
            $total = $sum('SELECT COALESCE(SUM(amount),0) FROM payments');
            return $withLink(
                __('ai.offline.answer.revenue', ['amount' => money($total)]),
                __('ai.offline.open_page'),
                $financeUrl()
            );
        }
    }

    // Appointments (with today filter)
    if (
        str_contains($q, 'appointment') || str_contains($q, 'rendez-vous') || str_contains($q, 'rendez vous')
        || str_contains($q, "today's appointment") || str_contains($q, 'appointments do i have')
    ) {
        $apptHub = $appointmentUrl();
        $todayOnly = str_contains($q, 'today') || str_contains($q, "aujourd'hui") || str_contains($q, 'aujourdhui');
        $dateSql = $todayOnly
            ? 'DATE(a.scheduled_at) = CURDATE()'
            : 'a.scheduled_at >= NOW()';
        if ($portal === 'admin') {
            $list = $rows(
                "SELECT a.id, a.title, a.scheduled_at, a.status FROM appointments a
                 WHERE {$dateSql} AND a.status IN ('scheduled','confirmed','rescheduled','pending')
                 ORDER BY a.scheduled_at ASC LIMIT 8"
            );
        } elseif ($portal === 'lawyer') {
            $list = $rows(
                "SELECT a.id, a.title, a.scheduled_at, a.status FROM appointments a
                 WHERE a.lawyer_id=? AND {$dateSql} AND a.status IN ('scheduled','confirmed','rescheduled','pending')
                 ORDER BY a.scheduled_at ASC LIMIT 8",
                [$uid]
            );
        } else {
            $list = $rows(
                "SELECT a.id, a.title, a.scheduled_at, a.status FROM appointments a
                 WHERE a.client_id=? AND {$dateSql} AND a.status IN ('scheduled','confirmed','rescheduled','pending')
                 ORDER BY a.scheduled_at ASC LIMIT 8",
                [$uid]
            );
        }
        if (!$list) {
            return $withLink(
                $todayOnly ? __('ai.offline.answer.appointments_today_none') : __('ai.offline.answer.appointments_none'),
                __('ai.offline.open_page'),
                $apptHub
            );
        }
        $lines = array_map(static function (array $a) use ($appointmentUrl, $md): string {
            return '• ' . format_datetime($a['scheduled_at']) . ' — ' . t_content($a['title']) . ' (' . translate_status($a['status']) . ")\n  "
                . $md(__('ai.offline.open_page'), $appointmentUrl((int) $a['id']));
        }, $list);
        return $withLink(
            __('ai.offline.answer.appointments', ['list' => implode("\n", $lines)]),
            __('ai.offline.open_page'),
            $apptHub
        );
    }

    if (
        str_contains($q, 'recent payment') || str_contains($q, 'summarize recent payment')
        || str_contains($q, 'règlements récents') || str_contains($q, 'reglements recents')
        || str_contains($q, 'synthèse des règlements') || str_contains($q, 'synthese des reglements')
    ) {
        $payHub = $financeUrl();
        if ($portal === 'admin') {
            $list = $rows(
                "SELECT id, amount, paid_at, payment_method FROM payments ORDER BY paid_at DESC, id DESC LIMIT 8"
            );
        } elseif ($portal === 'client') {
            $list = $rows(
                "SELECT id, amount, paid_at, payment_method FROM payments WHERE client_id=? ORDER BY paid_at DESC, id DESC LIMIT 8",
                [$uid]
            );
        } else {
            $list = [];
        }
        if (!$list) {
            return $withLink(__('ai.offline.answer.payments_none'), __('ai.offline.open_page'), $payHub);
        }
        $lines = array_map(static function (array $p) use ($receiptUrl, $md): string {
            $method = ucwords(str_replace('_', ' ', (string) ($p['payment_method'] ?? 'other')));
            return '• ' . money($p['amount']) . ' · ' . format_datetime($p['paid_at']) . ' · ' . $method . "\n  "
                . $md(__('ai.offline.open_page'), $receiptUrl((int) $p['id']));
        }, $list);
        return $withLink(
            __('ai.offline.answer.payments', ['list' => implode("\n", $lines)]),
            __('ai.offline.open_page'),
            $payHub
        );
    }

    if (
        str_contains($q, 'overdue') || str_contains($q, 'outstanding')
        || str_contains($q, 'échue') || str_contains($q, 'echue') || str_contains($q, 'impay')
        || str_contains($q, 'en souffrance')
        || (str_contains($q, 'invoice') && (str_contains($q, 'which') || str_contains($q, 'overdue')))
    ) {
        $invHub = $financeUrl();
        if ($portal === 'client') {
            $list = $rows(
                "SELECT id, invoice_number, total, status, due_date FROM invoices
                 WHERE client_id=? AND status IN ('sent','partial','overdue')
                 ORDER BY due_date ASC, id DESC LIMIT 10",
                [$uid]
            );
        } elseif ($portal === 'admin') {
            $list = $rows(
                "SELECT id, invoice_number, total, status, due_date FROM invoices
                 WHERE status IN ('sent','partial','overdue')
                 ORDER BY due_date ASC, id DESC LIMIT 10"
            );
        } else {
            $list = [];
        }
        if (!$list) {
            return $withLink(__('ai.offline.answer.overdue_none'), __('ai.offline.open_page'), $invHub);
        }
        $lines = array_map(static function (array $i) use ($invoiceUrl, $md): string {
            return '• ' . $i['invoice_number'] . ' — ' . money($i['total']) . ' (' . translate_status($i['status']) . ')'
                . ($i['due_date'] ? ' · due ' . format_date($i['due_date']) : '')
                . "\n  " . $md(__('ai.offline.open_page'), $invoiceUrl((int) $i['id']));
        }, $list);
        return $withLink(
            __('ai.offline.answer.overdue', ['list' => implode("\n", $lines)]),
            __('ai.offline.open_page'),
            $invHub
        );
    }

    if (
        str_contains($q, 'notification') || str_contains($q, 'latest notification')
        || str_contains($q, 'mes dernières notifications') || str_contains($q, 'mes dernieres notifications')
    ) {
        $notifUrl = $portalUrl('notifications.php');
        $list = $rows(
            'SELECT id, title, message, created_at, is_read, link FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 8',
            [$uid]
        );
        if (!$list) {
            return $withLink(__('ai.offline.answer.notifications_none'), __('ai.offline.open_page'), $notifUrl);
        }
        $lines = array_map(static function (array $n) use ($resolveStoredLink, $md): string {
            $mark = ((int) $n['is_read'] === 1) ? '' : '● ';
            $link = $resolveStoredLink((string) ($n['link'] ?? ''));
            return '• ' . $mark . t_stored($n['title']) . ' — ' . format_datetime($n['created_at']) . "\n  "
                . $md(__('ai.offline.open_page'), $link);
        }, $list);
        return $withLink(
            __('ai.offline.answer.notifications', ['list' => implode("\n", $lines)]),
            __('ai.offline.open_page'),
            $notifUrl
        );
    }

    if (
        str_contains($q, 'dashboard overview') || str_contains($q, 'firm dashboard')
        || str_contains($q, 'vue d\'ensemble') || str_contains($q, "vue d'ensemble")
        || str_contains($q, 'vue d’ensemble')
    ) {
        return $withLink(
            __('ai.offline.answer.dashboard', ['context' => $context]),
            __('ai.offline.open_page'),
            $portalUrl('index.php')
        );
    }

    if (
        str_contains($q, 'hearing') || str_contains($q, 'court') || str_contains($q, 'upcoming hearing')
        || str_contains($q, 'audience') || str_contains($q, 'tribunal')
        || str_contains($q, 'court date') || str_contains($q, 'dates d\'audience') || str_contains($q, "dates d'audience")
    ) {
        $courtHub = $hearingUrl();
        if ($portal === 'lawyer') {
            $list = $rows(
                "SELECT h.id, h.hearing_date, h.court_name, c.id AS case_id, c.case_number
                 FROM court_hearings h
                 JOIN cases c ON c.id=h.case_id
                 WHERE c.lawyer_id=? AND h.hearing_date >= NOW() AND h.status='scheduled'
                 ORDER BY h.hearing_date ASC LIMIT 8",
                [$uid]
            );
        } elseif ($portal === 'client') {
            $list = $rows(
                "SELECT h.id, h.hearing_date, h.court_name, c.id AS case_id, c.case_number
                 FROM court_hearings h
                 JOIN cases c ON c.id=h.case_id
                 WHERE c.client_id=? AND h.hearing_date >= NOW() AND h.status='scheduled'
                 ORDER BY h.hearing_date ASC LIMIT 8",
                [$uid]
            );
        } else {
            $list = $rows(
                "SELECT h.id, h.hearing_date, h.court_name, c.id AS case_id, c.case_number
                 FROM court_hearings h
                 JOIN cases c ON c.id=h.case_id
                 WHERE h.hearing_date >= NOW() AND h.status='scheduled'
                 ORDER BY h.hearing_date ASC LIMIT 8"
            );
        }
        if (!$list) {
            return $withLink(__('ai.offline.answer.hearings_none'), __('ai.offline.open_page'), $courtHub);
        }
        $lines = array_map(static function (array $h) use ($caseUrl, $hearingUrl, $md): string {
            return '• ' . format_datetime($h['hearing_date']) . ' — ' . $h['case_number'] . ' · ' . t_content($h['court_name'])
                . "\n  " . $md(__('ai.offline.link_hearing'), $hearingUrl((int) $h['id']))
                . "\n  " . $md(__('ai.offline.link_case'), $caseUrl((int) $h['case_id']));
        }, $list);
        return $withLink(
            __('ai.offline.answer.hearings', ['list' => implode("\n", $lines)]),
            __('ai.offline.open_page'),
            $courtHub
        );
    }

    if (
        str_contains($q, 'document') || str_contains($q, 'attach') || str_contains($q, 'upload')
        || str_contains($q, 'fichier') || str_contains($q, 'pièce') || str_contains($q, 'piece')
    ) {
        return __('ai.offline.attach_how_to');
    }

    if (str_contains($q, 'summar') || str_contains($q, 'overview') || str_contains($q, 'status')
        || str_contains($q, 'résum') || str_contains($q, 'resum') || str_contains($q, 'apercu') || str_contains($q, 'aperçu')) {
        return $withLink(__('ai.offline.overview', ['context' => $context]), __('ai.offline.open_page'), $portalUrl('index.php'));
    }
    if (str_contains($q, 'contract') || str_contains($q, 'draft') || str_contains($q, 'letter')
        || str_contains($q, 'contrat') || str_contains($q, 'lettre') || str_contains($q, 'rédig') || str_contains($q, 'redig')) {
        return __('ai.offline.draft');
    }
    if (str_contains($q, 'invoice') || str_contains($q, 'payment') || str_contains($q, 'bill')
        || str_contains($q, 'facture') || str_contains($q, 'règlement') || str_contains($q, 'reglement') || str_contains($q, 'paiement')) {
        return $withLink(__('ai.offline.billing', ['context' => $context]), __('ai.offline.open_page'), $financeUrl());
    }
    if ($portal === 'client' && (
        str_contains($q, 'other client') || str_contains($q, 'another client')
        || str_contains($q, 'autre client') || str_contains($q, 'autres clients')
    )) {
        return __('ai.offline.privacy');
    }

    return $withLink(__('ai.offline.default', ['context' => $context]), __('ai.offline.open_page'), $portalUrl('index.php'));
}
