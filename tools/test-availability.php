<?php
/**
 * CLI smoke tests for lawyer availability (blocks, live status, normalization).
 * Run: c:\wamp64\bin\php\php8.2.29\php.exe tools/test-availability.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();
$failures = 0;

function assert_true(bool $cond, string $label): void
{
    global $failures;
    if ($cond) {
        echo "PASS: {$label}\n";
        return;
    }
    $failures++;
    echo "FAIL: {$label}\n";
}

function assert_eq(mixed $a, mixed $b, string $label): void
{
    assert_true($a === $b, $label . " (expected " . var_export($b, true) . ", got " . var_export($a, true) . ")");
}

echo "=== Availability smoke tests ===\n\n";

assert_eq(lawyer_availability_statuses(), ['available', 'unavailable'], 'lawyer_availability_statuses');
assert_eq(normalize_lawyer_availability('busy'), 'unavailable', 'normalize busy');
assert_eq(normalize_lawyer_availability('available'), 'available', 'normalize available');
assert_eq(normalize_lawyer_availability(''), 'unavailable', 'normalize empty');

ensure_lawyer_availability_blocks_table($pdo);

$lawyer = $pdo->query("SELECT id FROM users WHERE role='lawyer' ORDER BY id LIMIT 1")->fetch();
if (!$lawyer) {
    echo "SKIP: no lawyer in database\n";
    exit($failures > 0 ? 1 : 0);
}
$lawyerId = (int) $lawyer['id'];
$weekStart = availability_week_start();
$dates = availability_week_dates($weekStart);
$today = date('Y-m-d');
$testDate = in_array($today, $dates, true) ? $today : ($dates[1] ?? $weekStart);

// Clear test week blocks then insert known schedule.
$pdo->prepare('DELETE FROM lawyer_availability_blocks WHERE lawyer_id=? AND block_date BETWEEN ? AND ?')
    ->execute([$lawyerId, $dates[1] ?? $weekStart, $dates[6] ?? $weekStart]);

$blocks = [
    ['block_date' => $testDate, 'start' => '09:00', 'end' => '12:00', 'status' => 'available'],
    ['block_date' => $testDate, 'start' => '12:00', 'end' => '17:00', 'status' => 'unavailable'],
];
save_lawyer_availability_blocks($pdo, $lawyerId, $weekStart, $blocks);

$saved = get_lawyer_availability_blocks_for_date($pdo, $lawyerId, $testDate);
assert_true(count($saved) === 2, 'saved two blocks for test day');

$atTen = strtotime($testDate . ' 10:00:00');
$atThirteen = strtotime($testDate . ' 13:00:00');
$atEvening = strtotime($testDate . ' 18:00:00');

assert_eq(resolve_lawyer_live_availability($pdo, $lawyerId, $atTen), 'available', 'live status inside available block');
assert_eq(resolve_lawyer_live_availability($pdo, $lawyerId, $atThirteen), 'unavailable', 'live status inside unavailable block');
assert_eq(resolve_lawyer_live_availability($pdo, $lawyerId, $atEvening), 'unavailable', 'live status outside blocks (no busy)');

$stored = $pdo->prepare('SELECT availability FROM users WHERE id=?');
$stored->execute([$lawyerId]);
$dbStatus = (string) $stored->fetchColumn();
assert_true(in_array($dbStatus, ['available', 'unavailable'], true), 'synced DB status is available or unavailable only');

// Legacy busy in DB should normalize on read.
$pdo->prepare("UPDATE users SET availability='busy' WHERE id=?")->execute([$lawyerId]);
assert_eq(normalize_lawyer_availability('busy'), 'unavailable', 'normalize after manual busy set');
sync_lawyer_availability_status($pdo, $lawyerId);
$stored->execute([$lawyerId]);
$dbStatusAfter = (string) $stored->fetchColumn();
assert_true($dbStatusAfter !== 'busy', 'sync clears busy from DB');

// Appointment slot validation against blocks.
$slotOk = validate_lawyer_appointment_slot($pdo, $lawyerId, date('Y-m-d H:i:s', strtotime($testDate . ' 10:00:00')), 30);
assert_true($slotOk['ok'], 'appointment slot allowed in available block');

$slotBad = validate_lawyer_appointment_slot($pdo, $lawyerId, date('Y-m-d H:i:s', strtotime($testDate . ' 13:00:00')), 30);
assert_true(!$slotBad['ok'], 'appointment slot rejected in unavailable block');

echo "\n";
if ($failures === 0) {
    echo "All tests passed.\n";
    exit(0);
}
echo "{$failures} test(s) failed.\n";
exit(1);
