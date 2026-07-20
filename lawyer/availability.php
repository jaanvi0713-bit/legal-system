<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$uid = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');

    if ($fa === 'status') {
        $availability = post('availability', 'available');
        if (!in_array($availability, ['available', 'busy', 'unavailable'], true)) {
            $availability = 'available';
        }
        $pdo->prepare('UPDATE users SET availability=?, notes=? WHERE id=?')
            ->execute([$availability, post('notes'), $uid]);
        refresh_session_user();
        flash('success', __('flash.availability.updated'));
        redirect('availability.php');
    }

    if ($fa === 'slots') {
        $slots = isset($_POST['slots']) && is_array($_POST['slots']) ? array_values($_POST['slots']) : [];
        $weekStart = post('week_start', availability_week_start());
        save_lawyer_availability_matrix($pdo, $uid, $weekStart, $slots);
        flash('success', __('flash.availability.slots_saved'));
        redirect('availability.php?week=' . urlencode(availability_normalize_week_start($weekStart)));
    }
}

$availWeekStart = availability_normalize_week_start(get('week'));
$availPrevWeek = date('Y-m-d', strtotime($availWeekStart . ' -7 days'));
$availNextWeek = date('Y-m-d', strtotime($availWeekStart . ' +7 days'));
$availWeekLabel = availability_format_week_range($availWeekStart);
$availWeekDates = availability_week_dates($availWeekStart);
$availIsCurrentWeek = $availWeekStart === availability_week_start();

$u = current_user();
$availMatrix = get_lawyer_availability_matrix($pdo, $uid, $availWeekStart);
$pageTitle = __('page.availability');
$pageSubtitle = __('availability.schedule.subtitle');
$portal = 'lawyer';
$activeNav = 'availability';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel avail-panel">
<?php
$availStatusForm = true;
require __DIR__ . '/../includes/availability-schedule-form.php';
?>
</div>
<?php
require __DIR__ . '/../includes/footer.php';
