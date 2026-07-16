<?php
/**
 * AI chat API — uses OpenAI when configured, otherwise rule-based legal assistant replies.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => __('api.error.unauthorized')], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$sessionId = (int) ($input['session_id'] ?? 0);
$message = trim((string) ($input['message'] ?? ''));
$user = current_user();
$pdo = db();

if ($sessionId <= 0 || $message === '') {
    http_response_code(422);
    echo json_encode(['error' => __('api.error.session_message_required')], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM ai_chat_sessions WHERE id = ? AND user_id = ?');
$stmt->execute([$sessionId, $user['id']]);
$session = $stmt->fetch();
if (!$session) {
    http_response_code(404);
    echo json_encode(['error' => __('api.error.session_not_found')], JSON_UNESCAPED_UNICODE);
    exit;
}

$ins = $pdo->prepare('INSERT INTO ai_chat_messages (session_id, role, content) VALUES (?, ?, ?)');
$ins->execute([$sessionId, 'user', $message]);

$portal = $session['portal'];
$reply = generate_ai_reply($pdo, $user, $portal, $message);

$ins->execute([$sessionId, 'assistant', $reply]);

$newChatTitles = ['New Chat', __('ai.new_chat')];
$titleSql = "UPDATE ai_chat_sessions SET updated_at = NOW(), title = IF(title IN ('New Chat', ?), ?, title) WHERE id = ?";
$pdo->prepare($titleSql)->execute([__('ai.new_chat'), substr($message, 0, 60), $sessionId]);

echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);

function generate_ai_reply(PDO $pdo, array $user, string $portal, string $message): string
{
    $apiKey = app_config('openai_api_key', '');
    $system = match ($portal) {
        'admin' => get_setting($pdo, 'ai_welcome_admin', __('ai.system.admin')),
        'lawyer' => get_setting($pdo, 'ai_welcome_lawyer', __('ai.system.lawyer')),
        default => get_setting($pdo, 'ai_welcome_client', __('ai.system.client')),
    };

    // Encourage model to answer in the active UI language
    $lang = current_lang() === 'fr' ? 'French' : 'English';
    $system .= "\n\nRespond in {$lang}. Amounts are in Mauritian rupees (MUR / Rs).";

    $context = build_portal_context($pdo, $user, $portal);

    if ($apiKey) {
        $live = call_openai($apiKey, $system . "\n\nContext:\n" . $context, $message);
        if ($live !== null) {
            return $live;
        }
    }

    return offline_ai_reply($portal, $message, $context, full_name($user));
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

function call_openai(string $apiKey, string $system, string $message): ?string
{
    $payload = [
        'model' => app_config('openai_model', 'gpt-4o-mini'),
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $message],
        ],
        'temperature' => 0.4,
    ];
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 45,
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300 || !$raw) {
        return null;
    }
    $data = json_decode($raw, true);
    return $data['choices'][0]['message']['content'] ?? null;
}

function offline_ai_reply(string $portal, string $message, string $context, string $name): string
{
    $q = strtolower($message);
    $intro = __('ai.offline.intro', [
        'name' => $name,
        'portal' => __('portal.label.' . $portal),
    ]);

    if (str_contains($q, 'summar') || str_contains($q, 'overview') || str_contains($q, 'status')
        || str_contains($q, 'résum') || str_contains($q, 'apercu') || str_contains($q, 'aperçu')) {
        return $intro . "\n\n" . __('ai.offline.overview', ['context' => $context]);
    }
    if (str_contains($q, 'contract') || str_contains($q, 'draft') || str_contains($q, 'letter')
        || str_contains($q, 'contrat') || str_contains($q, 'lettre') || str_contains($q, 'rédig')) {
        return $intro . "\n\n" . __('ai.offline.draft');
    }
    if (str_contains($q, 'invoice') || str_contains($q, 'payment') || str_contains($q, 'bill')
        || str_contains($q, 'facture') || str_contains($q, 'règlement') || str_contains($q, 'reglement') || str_contains($q, 'paiement')) {
        return $intro . "\n\n" . __('ai.offline.billing', ['context' => $context]);
    }
    if (str_contains($q, 'hearing') || str_contains($q, 'court') || str_contains($q, 'timeline')
        || str_contains($q, 'audience') || str_contains($q, 'tribunal') || str_contains($q, 'chronolog')) {
        return $intro . "\n\n" . __('ai.offline.hearing', ['context' => $context]);
    }
    if ($portal === 'client' && (
        str_contains($q, 'other client') || str_contains($q, 'another client')
        || str_contains($q, 'autre client') || str_contains($q, 'autres clients')
    )) {
        return __('ai.offline.privacy');
    }

    return $intro . "\n\n" . __('ai.offline.default', ['context' => $context]);
}
