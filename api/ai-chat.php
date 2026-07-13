<?php
/**
 * AI chat API — uses OpenAI when configured, otherwise rule-based legal assistant replies.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$sessionId = (int) ($input['session_id'] ?? 0);
$message = trim((string) ($input['message'] ?? ''));
$user = current_user();
$pdo = db();

if ($sessionId <= 0 || $message === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Session and message are required.']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM ai_chat_sessions WHERE id = ? AND user_id = ?');
$stmt->execute([$sessionId, $user['id']]);
$session = $stmt->fetch();
if (!$session) {
    http_response_code(404);
    echo json_encode(['error' => 'Session not found.']);
    exit;
}

$ins = $pdo->prepare('INSERT INTO ai_chat_messages (session_id, role, content) VALUES (?, ?, ?)');
$ins->execute([$sessionId, 'user', $message]);

$portal = $session['portal'];
$reply = generate_ai_reply($pdo, $user, $portal, $message);

$ins->execute([$sessionId, 'assistant', $reply]);
$pdo->prepare('UPDATE ai_chat_sessions SET updated_at = NOW(), title = IF(title = \'New Chat\', ?, title) WHERE id = ?')
    ->execute([substr($message, 0, 60), $sessionId]);

echo json_encode(['reply' => $reply]);

function generate_ai_reply(PDO $pdo, array $user, string $portal, string $message): string
{
    $apiKey = app_config('openai_api_key', '');
    $system = match ($portal) {
        'admin' => get_setting($pdo, 'ai_welcome_admin', 'You are the Lexora admin AI assistant.'),
        'lawyer' => get_setting($pdo, 'ai_welcome_lawyer', 'You are the Lexora lawyer AI assistant.'),
        default => get_setting($pdo, 'ai_welcome_client', 'You are the Lexora client AI assistant. Only discuss this client\'s own matters.'),
    };

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
        return "Firm stats: {$clients} clients, {$lawyers} lawyers, {$active} active cases, revenue " . money($revenue) . ".";
    }
    if ($portal === 'lawyer') {
        $stmt = $pdo->prepare("SELECT case_number, title, status FROM cases WHERE lawyer_id = ? ORDER BY updated_at DESC LIMIT 5");
        $stmt->execute([$user['id']]);
        $cases = $stmt->fetchAll();
        $lines = array_map(fn($c) => "{$c['case_number']} ({$c['status']}): {$c['title']}", $cases);
        return "Assigned cases:\n" . (implode("\n", $lines) ?: 'None');
    }
    // client — scoped ONLY to this client
    $stmt = $pdo->prepare("SELECT case_number, title, status FROM cases WHERE client_id = ? ORDER BY updated_at DESC LIMIT 5");
    $stmt->execute([$user['id']]);
    $cases = $stmt->fetchAll();
    $lines = array_map(fn($c) => "{$c['case_number']} ({$c['status']}): {$c['title']}", $cases);
    $inv = $pdo->prepare("SELECT invoice_number, total, status FROM invoices WHERE client_id = ? ORDER BY created_at DESC LIMIT 5");
    $inv->execute([$user['id']]);
    $invoices = $inv->fetchAll();
    $invLines = array_map(fn($i) => "{$i['invoice_number']}: " . money($i['total']) . " ({$i['status']})", $invoices);
    return "Your cases:\n" . (implode("\n", $lines) ?: 'None') . "\nYour invoices:\n" . (implode("\n", $invLines) ?: 'None');
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
    $intro = "Hello {$name}. I'm your Lexora {$portal} AI assistant.";

    if (str_contains($q, 'summar') || str_contains($q, 'overview') || str_contains($q, 'status')) {
        return "{$intro}\n\nHere is a quick overview based on your portal data:\n{$context}\n\nI can also help draft letters, explain documents, or outline next steps. (Connect an OpenAI API key in config/app.php for richer live answers.)";
    }
    if (str_contains($q, 'contract') || str_contains($q, 'draft') || str_contains($q, 'letter')) {
        return "{$intro}\n\nDraft outline:\n1. Parties and date\n2. Purpose / subject matter\n3. Key obligations\n4. Payment or consideration\n5. Confidentiality\n6. Dispute resolution\n7. Signatures\n\nShare the specific facts and I will expand this into a fuller draft. Live drafting improves when OpenAI is configured.";
    }
    if (str_contains($q, 'invoice') || str_contains($q, 'payment') || str_contains($q, 'bill')) {
        return "{$intro}\n\nBilling context available to you:\n{$context}\n\nFor clients, I only discuss your own invoices. Ask me to explain a specific invoice number for a plain-language summary.";
    }
    if (str_contains($q, 'hearing') || str_contains($q, 'court') || str_contains($q, 'timeline')) {
        return "{$intro}\n\nSuggested timeline approach:\n• Collect filing date and pleadings\n• List upcoming hearings and deadlines\n• Note outstanding documents\n• Flag risks / next actions\n\nContext:\n{$context}";
    }
    if ($portal === 'client' && (str_contains($q, 'other client') || str_contains($q, 'another client'))) {
        return "I can only discuss your own cases, documents, and invoices. I cannot access or reveal information belonging to other clients.";
    }

    return "{$intro}\n\nI can help with:\n• Document / case summaries\n• Plain-language explanations\n• Letter and contract outlines\n• Checklists and next steps\n\nYour current context:\n{$context}\n\nAsk a more specific question (e.g. “Summarize my active cases” or “Draft a demand letter outline”).";
}
