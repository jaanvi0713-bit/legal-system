<?php
/**
 * Cron / manual runner for deadline alerts.
 *
 * Web:  /tools/run-deadline-alerts.php?key=YOUR_CRON_SECRET
 * CLI:  php tools/run-deadline-alerts.php YOUR_CRON_SECRET
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/deadline-alerts.php';

$pdo = db();
$expected = trim((string) get_setting($pdo, 'cron_secret', ''));
if ($expected === '') {
    $expected = bin2hex(random_bytes(16));
    set_setting($pdo, 'cron_secret', $expected);
}

$key = '';
if (PHP_SAPI === 'cli') {
    $key = $argv[1] ?? '';
} else {
    $key = trim((string) ($_GET['key'] ?? ''));
    header('Content-Type: application/json; charset=utf-8');
}

if (!hash_equals($expected, $key)) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Invalid or missing cron key.\n");
        exit(1);
    }
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$result = run_deadline_alerts($pdo);
$payload = [
    'ok' => true,
    'skipped' => $result['skipped'],
    'hearing_alerts' => $result['hearings'],
    'task_alerts' => $result['tasks'],
    'ran_at' => date('c'),
];

if (PHP_SAPI === 'cli') {
    echo json_encode($payload, JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

echo json_encode($payload);
