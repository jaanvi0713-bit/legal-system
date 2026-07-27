<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'staff']);
$pdo = db();

$lawyerId = (int) get('lawyer_id', 0);
$pageTitle = __('page.lawyer_availability');
$pageSubtitle = __('availability.view.pick_lawyer');
$portal = 'admin';
$activeNav = 'lawyers';

$lawyers = $pdo->query(
    "SELECT id, first_name, last_name, email, specialization, availability, notes, is_active
     FROM users WHERE role='lawyer' ORDER BY first_name, last_name"
)->fetchAll();

if ($lawyerId <= 0) {
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="panel case-list-panel">
        <div class="case-list-head">
            <div class="case-list-title">
                <h2><?= __e('availability.view.select_lawyer') ?></h2>
                <p class="muted"><?= __e('availability.view.pick_lawyer') ?></p>
            </div>
            <a class="btn btn-secondary btn-sm" href="lawyers.php"><?= __e('lawyers.back') ?></a>
        </div>
        <div class="table-wrap case-table-wrap">
            <table class="case-table">
                <thead>
                    <tr>
                        <th><?= __e('common.lawyer') ?></th>
                        <th><?= __e('form.specialization') ?></th>
                        <th><?= __e('form.availability') ?></th>
                        <th class="col-actions"><?= __e('common.actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($lawyers as $l): ?>
                    <tr>
                        <td>
                            <strong><?= e(full_name($l)) ?></strong>
                            <div class="muted"><?= e($l['email']) ?></div>
                        </td>
                        <td><?= e($l['specialization'] ?: __('common.em_dash')) ?></td>
                        <td><?= status_badge($l['availability']) ?></td>
                        <td class="col-actions">
                            <a class="btn btn-row-open btn-sm btn-row-fit" href="lawyer-availability.php?lawyer_id=<?= (int) $l['id'] ?>"><?= __e('lawyers.view_schedule') ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$lawyers): ?>
                    <tr><td colspan="4" class="case-empty muted"><?= __e('lawyers.empty.none') ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="lawyer"');
$stmt->execute([$lawyerId]);
$lawyer = $stmt->fetch();
if (!$lawyer) {
    flash('error', __('lawyers.not_found'));
    redirect('lawyer-availability.php');
}

$availWeekStart = availability_normalize_week_start(get('week'));
$availPrevWeek = date('Y-m-d', strtotime($availWeekStart . ' -7 days'));
$availNextWeek = date('Y-m-d', strtotime($availWeekStart . ' +7 days'));
$availWeekLabel = availability_format_week_range($availWeekStart);
$availWeekDates = availability_week_dates($availWeekStart);
$availIsCurrentWeek = $availWeekStart === availability_week_start();
$availMatrix = get_lawyer_availability_matrix($pdo, $lawyerId, $availWeekStart);
$availWeekHref = static fn(string $week): string => 'lawyer-availability.php?lawyer_id=' . $lawyerId . '&week=' . urlencode(availability_normalize_week_start($week));

$slotCountStmt = $pdo->prepare('SELECT COUNT(*) FROM lawyer_availability_slots WHERE lawyer_id=?');
$slotCountStmt->execute([$lawyerId]);
$availTotalPublishedSlots = (int) $slotCountStmt->fetchColumn();
$availLawyer = $lawyer;

$pageTitle = __('availability.view.title', ['name' => full_name($lawyer)]);
$pageSubtitle = __('availability.view.subtitle');
require __DIR__ . '/../includes/header.php';
?>
<div class="avail-view-page">
    <div class="avail-view-top">
        <a class="btn btn-secondary btn-sm" href="lawyers.php?action=view&id=<?= (int) $lawyerId ?>">← <?= __e('lawyers.back_profile') ?></a>
        <div class="row-actions">
            <a class="btn btn-secondary btn-sm" href="lawyer-availability.php"><?= __e('availability.view.all_lawyers') ?></a>
            <a class="btn btn-primary btn-sm" href="lawyers.php?action=edit&id=<?= (int) $lawyerId ?>"><?= __e('lawyers.edit_profile') ?></a>
        </div>
    </div>

    <div class="panel avail-panel">
        <div class="avail-view-lawyer-head">
            <div>
                <h2><?= e(full_name($lawyer)) ?></h2>
                <p class="muted">
                    <?= e($lawyer['specialization'] ?: __('lawyers.general_practice')) ?>
                    · <?= e($lawyer['email']) ?>
                    <?php if (!empty($lawyer['phone'])): ?>
                    · <?= e($lawyer['phone']) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <?php require __DIR__ . '/../includes/availability-schedule-view.php'; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
