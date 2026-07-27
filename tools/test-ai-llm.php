<?php
/** CLI smoke test for external AI settings — run: php tools/test-ai-llm.php */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai-llm.php';

$pdo = db();
$cfg = ai_llm_config($pdo);

echo "External AI enabled: " . ($cfg['enabled'] ? 'yes' : 'no') . PHP_EOL;
echo "API key stored: " . ($cfg['api_key'] !== '' ? 'yes (' . strlen($cfg['api_key']) . ' chars)' : 'NO — add key in Settings → AI Assistant') . PHP_EOL;
echo "Model: {$cfg['model']}" . PHP_EOL;
echo "Base URL: {$cfg['base_url']}" . PHP_EOL;
echo "Max tokens: {$cfg['max_tokens']}, temperature: {$cfg['temperature']}" . PHP_EOL;
echo "cURL available: " . (function_exists('curl_init') ? 'yes' : 'no (will use stream wrapper)') . PHP_EOL;

if (!ai_llm_is_available($pdo)) {
    echo PHP_EOL . "RESULT: External AI is NOT available (enable checkbox + save API key)." . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "Sending test request..." . PHP_EOL;
$result = ai_llm_test_connection($pdo);
if ($result['ok']) {
    echo "RESULT: OK — " . $result['message'] . PHP_EOL;
    exit(0);
}

echo "RESULT: FAILED — " . $result['message'] . PHP_EOL;
exit(1);
