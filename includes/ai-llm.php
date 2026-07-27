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

    return __($langKey, ['company' => company_name($pdo)]);
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
    $company = company_name($pdo);
    $name = function_exists('full_name') ? full_name($user) : trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

    $actionsHelp = "You can guide the user to run workspace actions with clear commands. "
        . "Supported actions (executed by the system when phrased correctly): "
        . "create client, create lawyer, create case, schedule/cancel/delete appointment (guided), "
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

    $result = ai_llm_http_post($config, $payload);
    return $result['ok'] ? $result['content'] : null;
}

function ai_llm_is_local_dev(): bool
{
    $url = (string) app_config('url', '');
    return (bool) preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?(/|$)#i', $url);
}

/**
 * @return array{response:string|false,http_code:int,curl_error:string}
 */
function ai_llm_curl_post(string $url, string $apiKey, string $body, int $timeout, bool $verifySsl): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(8, $timeout),
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'response' => $response,
        'http_code' => $httpCode,
        'curl_error' => $curlError,
    ];
}

/**
 * @return array{ok:bool,content:?string,error:string,http_code:int}
 */
function ai_llm_http_post(array $config, array $payload): array
{
    $fail = static function (string $error, int $httpCode = 0): array {
        return ['ok' => false, 'content' => null, 'error' => $error, 'http_code' => $httpCode];
    };

    $baseUrl = rtrim(trim((string) ($config['base_url'] ?? '')), '/');
    $apiKey = trim((string) ($config['api_key'] ?? ''));
    $model = trim((string) ($config['model'] ?? ''));

    if ($apiKey === '') {
        return $fail('No API key configured.');
    }
    if ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
        return $fail('Invalid API base URL.');
    }
    if ($model === '') {
        return $fail('No model configured.');
    }

    $payload['model'] = $model;
    $url = $baseUrl . '/chat/completions';
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        return $fail('Could not encode the API request.');
    }

    $timeout = max(5, (int) ($config['timeout'] ?? 25));

    if (function_exists('curl_init')) {
        $attempt = ai_llm_curl_post($url, $apiKey, $body, $timeout, true);
        $response = $attempt['response'];
        $httpCode = $attempt['http_code'];
        $curlError = $attempt['curl_error'];

        if ($response === false && stripos($curlError, 'ssl') !== false && ai_llm_is_local_dev()) {
            $attempt = ai_llm_curl_post($url, $apiKey, $body, $timeout, false);
            $response = $attempt['response'];
            $httpCode = $attempt['http_code'];
            $curlError = $attempt['curl_error'];
        }

        if ($response === false) {
            $hint = stripos($curlError, 'ssl') !== false
                ? ' SSL certificate problem — on local WAMP, set curl.cainfo in php.ini to a CA bundle (cacert.pem), or run the app on localhost where SSL verify is relaxed automatically.'
                : '';
            return $fail(trim($curlError !== '' ? $curlError . '.' : 'Network request failed.') . $hint);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return $fail(ai_llm_error_from_body($response, $httpCode), $httpCode);
        }

        $content = ai_llm_parse_response($response);
        if ($content === null) {
            return $fail('The API returned an empty or unexpected response.', $httpCode);
        }

        return ['ok' => true, 'content' => $content, 'error' => '', 'http_code' => $httpCode];
    }

    $verifySsl = !ai_llm_is_local_dev();
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$apiKey}\r\n",
            'content' => $body,
            'timeout' => max(30, $timeout),
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => $verifySsl,
            'verify_peer_name' => $verifySsl,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return $fail('Network request failed (PHP streams). Enable the curl extension for better compatibility.');
    }

    $httpCode = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $m)) {
        $httpCode = (int) $m[1];
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        return $fail(ai_llm_error_from_body($response, $httpCode), $httpCode);
    }

    $content = ai_llm_parse_response($response);
    if ($content === null) {
        return $fail('The API returned an empty or unexpected response.', $httpCode);
    }

    return ['ok' => true, 'content' => $content, 'error' => '', 'http_code' => $httpCode];
}

function ai_llm_error_from_body(string $response, int $httpCode): string
{
    $data = json_decode($response, true);
    if (is_array($data)) {
        $msg = $data['error']['message'] ?? $data['message'] ?? null;
        if (is_string($msg) && trim($msg) !== '') {
            return trim($msg) . ($httpCode > 0 ? " (HTTP {$httpCode})" : '');
        }
    }

    $preview = trim(preg_replace('/\s+/', ' ', $response));
    if ($preview !== '') {
        $preview = function_exists('mb_substr') ? mb_substr($preview, 0, 180) : substr($preview, 0, 180);
        return $preview . ($httpCode > 0 ? " (HTTP {$httpCode})" : '');
    }

    return $httpCode > 0 ? "API request failed (HTTP {$httpCode})." : 'API request failed.';
}

function ai_llm_error_hint(int $httpCode, string $message): string
{
    $lower = strtolower($message);
    if ($httpCode === 429 || str_contains($lower, 'quota') || str_contains($lower, 'billing')) {
        return __('settings.ai.error_quota');
    }
    if ($httpCode === 401 || str_contains($lower, 'incorrect api key') || str_contains($lower, 'invalid api key')) {
        return __('settings.ai.error_key');
    }
    if ($httpCode === 404 || str_contains($lower, 'model') && str_contains($lower, 'not found')) {
        return __('settings.ai.error_model');
    }
    return '';
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
 * Test external AI connectivity using stored settings or optional overrides.
 *
 * @param array<string, mixed> $overrides api_key, model, base_url, enabled
 * @return array{ok:bool,message:string,model:string,reply_preview:string}
 */
function ai_llm_test_connection(PDO $pdo, array $overrides = []): array
{
    $cfg = ai_llm_config($pdo);

    if (array_key_exists('enabled', $overrides)) {
        $cfg['enabled'] = (bool) $overrides['enabled'];
    }
    if (!empty($overrides['api_key']) && is_string($overrides['api_key'])) {
        $cfg['api_key'] = trim($overrides['api_key']);
    }
    if (!empty($overrides['model']) && is_string($overrides['model'])) {
        $cfg['model'] = trim($overrides['model']);
    }
    if (!empty($overrides['base_url']) && is_string($overrides['base_url'])) {
        $cfg['base_url'] = rtrim(trim($overrides['base_url']), '/');
    }

    if ($cfg['api_key'] === '') {
        return [
            'ok' => false,
            'message' => 'Add an API key and save, or enter one in the field before testing.',
            'model' => $cfg['model'],
            'reply_preview' => '',
        ];
    }

    $result = ai_llm_http_post($cfg, [
        'model' => $cfg['model'],
        'messages' => [
            ['role' => 'system', 'content' => 'Reply with exactly: OK'],
            ['role' => 'user', 'content' => 'Say OK'],
        ],
        'temperature' => 0,
        'max_tokens' => 16,
    ]);

    if (!$result['ok']) {
        $hint = ai_llm_error_hint($result['http_code'], $result['error']);
        $connectionOk = $result['http_code'] === 429
            || str_contains(strtolower($result['error']), 'quota')
            || str_contains(strtolower($result['error']), 'billing');
        return [
            'ok' => false,
            'connection_ok' => $connectionOk,
            'message' => $result['error'],
            'hint' => $hint,
            'model' => $cfg['model'],
            'reply_preview' => '',
        ];
    }

    $preview = trim((string) $result['content']);
    if (function_exists('mb_substr')) {
        $preview = mb_substr($preview, 0, 120);
    } else {
        $preview = substr($preview, 0, 120);
    }

    return [
        'ok' => true,
        'message' => 'Connected successfully using model ' . $cfg['model'] . '.',
        'model' => $cfg['model'],
        'reply_preview' => $preview,
    ];
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
