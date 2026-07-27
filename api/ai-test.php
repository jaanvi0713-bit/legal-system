<?php
/**
 * Test external AI settings (admin only).
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai-llm.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => __('api.error.unauthorized')], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = current_user();
if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => __('common.access_denied')], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST required.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$pdo = db();
$overrides = [];

if (array_key_exists('ai_enabled', $input)) {
    $overrides['enabled'] = ($input['ai_enabled'] === '1' || $input['ai_enabled'] === true || $input['ai_enabled'] === 1);
}
if (!empty($input['ai_api_key']) && is_string($input['ai_api_key'])) {
    $overrides['api_key'] = trim($input['ai_api_key']);
}
if (!empty($input['ai_model']) && is_string($input['ai_model'])) {
    $overrides['model'] = trim($input['ai_model']);
}
if (!empty($input['ai_base_url']) && is_string($input['ai_base_url'])) {
    $overrides['base_url'] = trim($input['ai_base_url']);
}

$result = ai_llm_test_connection($pdo, $overrides);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
