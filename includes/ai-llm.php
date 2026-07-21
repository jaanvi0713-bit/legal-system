<?php
/**
 * Live LLM integration for the AI assistant (OpenAI-compatible APIs).
 * Supports OpenAI, DeepSeek, Groq, and other chat-completions providers.
 */

require_once __DIR__ . '/ai-mauritius-law.php';

/**
 * @return array{enabled:bool,api_key:string,model:string,base_url:string,max_tokens:int,temperature:float}
 */
function ai_llm_config(PDO $pdo): array
{
    $legacyKey = trim((string) app_config('openai_api_key', ''));
    $apiKey = trim((string) get_setting($pdo, 'ai_api_key', app_config('ai_api_key', $legacyKey)));

    return [
        'enabled' => get_setting($pdo, 'ai_enabled', '1') === '1',
        'api_key' => $apiKey,
        'model' => trim((string) get_setting($pdo, 'ai_model', app_config('ai_model', 'gpt-4o-mini'))),
        'base_url' => rtrim(trim((string) get_setting($pdo, 'ai_base_url', app_config('ai_base_url', 'https://api.openai.com/v1'))), '/'),
        'max_tokens' => max(256, (int) get_setting($pdo, 'ai_max_tokens', (string) app_config('ai_max_tokens', 4096))),
        'temperature' => max(0.0, min(1.0, (float) get_setting($pdo, 'ai_temperature', (string) app_config('ai_temperature', 0.3)))),
    ];
}

function ai_llm_is_available(PDO $pdo): bool
{
    $cfg = ai_llm_config($pdo);
    return $cfg['enabled'] && $cfg['api_key'] !== '';
}

/**
 * @return list<array{role:string,content:string}>
 */
function ai_llm_load_messages(PDO $pdo, int $sessionId, int $limit = 24): array
{
    $stmt = $pdo->prepare(
        'SELECT role, content FROM ai_chat_messages
         WHERE session_id = ? AND role IN (\'user\', \'assistant\')
         ORDER BY id ASC'
    );
    $stmt->execute([$sessionId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (count($rows) > $limit) {
        $rows = array_slice($rows, -$limit);
    }

    $messages = [];
    foreach ($rows as $row) {
        $role = (string) ($row['role'] ?? '');
        $content = trim((string) ($row['content'] ?? ''));
        if ($content === '' || !in_array($role, ['user', 'assistant'], true)) {
            continue;
        }
        $messages[] = ['role' => $role, 'content' => $content];
    }

    return $messages;
}

function ai_llm_portal_system_prompt(PDO $pdo, string $portal): string
{
    $settingKey = match ($portal) {
        'admin' => 'ai_prompt_admin',
        'lawyer' => 'ai_prompt_lawyer',
        'client' => 'ai_prompt_client',
        default => 'ai_prompt_client',
    };

    $custom = trim((string) get_setting($pdo, $settingKey, ''));
    if ($custom !== '') {
        return $custom;
    }

    $langKey = match ($portal) {
        'admin' => 'ai.system.admin',
        'lawyer' => 'ai.system.lawyer',
        default => 'ai.system.client',
    };

    return __($langKey);
}

function ai_llm_mauritius_law_context(): string
{
    $parts = [
        ai_mauritius_legal_system_overview(),
        ai_mauritius_sources_of_law(),
        ai_mauritius_courts_overview(),
        ai_mauritius_main_law_areas(),
    ];

    return implode("\n\n", array_filter(array_map('trim', $parts)));
}

function ai_llm_build_system_prompt(PDO $pdo, array $user, string $portal, string $portalContext): string
{
    $company = trim((string) get_setting($pdo, 'company_name', app_config('name', 'LEGAL PRO')));
    $name = function_exists('full_name') ? full_name($user) : trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

    $actionsHelp = "You can guide the user to run workspace actions with clear commands. "
        . "Supported actions (executed by the system when phrased correctly): "
        . "create client, create lawyer, create case, schedule/cancel appointment, "
        . "upload document to a case (with attachment), draft/send professional email, "
        . "assign lawyer, update case status. "
        . "If required details are missing, ask for them. Do not invent successful mutations.";

    $sections = [
        ai_llm_portal_system_prompt($pdo, $portal),
        __('ai.system.mauritius_law'),
        __('ai.system.citations'),
        __('ai.system.behavior'),
        $actionsHelp,
        "Firm workspace: {$company}. User: {$name}. Portal: {$portal}.",
        "Live workspace data:\n{$portalContext}",
        "Mauritius legal system reference:\n" . ai_llm_mauritius_law_context(),
        ai_mauritius_law_disclaimer(),
    ];

    return implode("\n\n", array_filter(array_map('trim', $sections)));
}

/**
 * @param array<int, array<string, mixed>> $attachments
 */
function ai_llm_enrich_message(string $message, array $attachments): string
{
    if (!$attachments) {
        return $message;
    }

    $blocks = [trim($message)];
    foreach ($attachments as $attachment) {
        $fileName = (string) ($attachment['file_name'] ?? 'file');
        $text = trim((string) ($attachment['text'] ?? ''));
        if ($text === '') {
            $blocks[] = "[Attached file: {$fileName} — no extractable text]";
            continue;
        }
        $preview = function_exists('mb_substr') ? mb_substr($text, 0, 12000) : substr($text, 0, 12000);
        $blocks[] = "[Attached file: {$fileName}]\n{$preview}";
    }

    return implode("\n\n", array_filter($blocks));
}

/**
 * @param list<array{role:string,content:string}> $messages
 * @param array<int, array<string, mixed>> $attachments
 * @return list<array{role:string,content:string}>
 */
function ai_llm_prepare_messages(array $messages, string $currentMessage, array $attachments): array
{
    if (!$messages) {
        return [['role' => 'user', 'content' => ai_llm_enrich_message($currentMessage, $attachments)]];
    }

    $lastIndex = count($messages) - 1;
    $last = $messages[$lastIndex];
    if (($last['role'] ?? '') === 'user' && $attachments) {
        $messages[$lastIndex]['content'] = ai_llm_enrich_message((string) $last['content'], $attachments);
    }

    return $messages;
}

/**
 * @param list<array{role:string,content:string}> $messages
 */
function ai_llm_request(array $config, string $systemPrompt, array $messages): ?string
{
    $payload = [
        'model' => $config['model'],
        'messages' => array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages
        ),
        'temperature' => $config['temperature'],
        'max_tokens' => $config['max_tokens'],
    ];

    $url = $config['base_url'] . '/chat/completions';
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        return null;
    }

    if (!function_exists('curl_init')) {
        return ai_llm_request_stream_context($url, $config['api_key'], $body);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['api_key'],
        ],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(5, (int) ($config['timeout'] ?? 25)),
        CURLOPT_CONNECTTIMEOUT => min(8, max(3, (int) ($config['timeout'] ?? 25))),
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        return null;
    }

    return ai_llm_parse_response($response);
}

function ai_llm_request_stream_context(string $url, string $apiKey, string $body): ?string
{
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$apiKey}\r\n",
            'content' => $body,
            'timeout' => 120,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }

    return ai_llm_parse_response($response);
}

function ai_llm_parse_response(string $response): ?string
{
    $data = json_decode($response, true);
    if (!is_array($data)) {
        return null;
    }

    $content = $data['choices'][0]['message']['content'] ?? null;
    if (!is_string($content)) {
        return null;
    }

    $content = trim($content);
    return $content !== '' ? $content : null;
}

/**
 * @param array<int, array<string, mixed>> $attachments
 */
function ai_llm_chat(
    PDO $pdo,
    array $user,
    string $portal,
    int $sessionId,
    string $message,
    string $portalContext,
    array $attachments = []
): ?string {
    if (!ai_llm_is_available($pdo)) {
        return null;
    }

    $config = ai_llm_config($pdo);
    $systemPrompt = ai_llm_build_system_prompt($pdo, $user, $portal, $portalContext);
    $history = ai_llm_load_messages($pdo, $sessionId);
    $messages = ai_llm_prepare_messages($history, $message, $attachments);

    return ai_llm_request($config, $systemPrompt, $messages);
}
