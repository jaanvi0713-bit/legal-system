<?php
/**
 * Lawyer availability: weekly matrix or bookable slots for a specific date.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => __('api.error.unauthorized')], JSON_UNESCAPED_UNICODE);
    exit;
}

$lawyerId = (int) ($_GET['lawyer_id'] ?? 0);
$date = trim((string) ($_GET['date'] ?? ''));
$week = trim((string) ($_GET['week'] ?? ''));

if ($lawyerId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => __('error.availability.lawyer_not_found')], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = current_user();
$pdo = db();
$role = (string) ($user['role'] ?? '');

if ($role === 'lawyer' && (int) $user['id'] !== $lawyerId) {
    http_response_code(403);
    echo json_encode(['error' => __('api.error.unauthorized')], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($role === 'client') {
    $allowed = $pdo->prepare('SELECT 1 FROM users WHERE id=? AND assigned_lawyer_id=? LIMIT 1');
    $allowed->execute([(int) $user['id'], $lawyerId]);
    if (!$allowed->fetch()) {
        $allowed = $pdo->prepare('SELECT 1 FROM cases WHERE client_id=? AND lawyer_id=? LIMIT 1');
        $allowed->execute([(int) $user['id'], $lawyerId]);
        if (!$allowed->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => __('api.error.unauthorized')], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

if ($date !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(422);
        echo json_encode(['error' => __('error.availability.invalid_datetime')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $duration = normalize_appointment_duration((int) ($_GET['duration'] ?? 60));
    $exclude = (int) ($_GET['exclude'] ?? 0) ?: null;
    $slots = get_lawyer_bookable_slots($pdo, $lawyerId, $date, $duration, $exclude);
    echo json_encode([
        'date' => $date,
        'duration' => $duration,
        'slots' => $slots,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$weekStart = availability_normalize_week_start($week !== '' ? $week : null);
$matrix = get_lawyer_availability_matrix($pdo, $lawyerId, $weekStart);

echo json_encode([
    'week_start' => $weekStart,
    'matrix' => $matrix,
], JSON_UNESCAPED_UNICODE);
