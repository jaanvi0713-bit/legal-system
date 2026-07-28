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
                <?php foreach ($lawyers as $l):
                    $liveStatus = resolve_lawyer_live_availability($pdo, (int) $l['id']);
                ?>
                    <tr>
                        <td>
                            <strong><?= e(full_name($l)) ?></strong>
                            <div class="muted"><?= e($l['email']) ?></div>
                        </td>
                        <td><?= e($l['specialization'] ?: __('common.em_dash')) ?></td>
                        <td><?= status_badge($liveStatus) ?></td>
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
$availLawyerId = $lawyerId;
$availLawyer = $lawyer;
$availReadOnly = true;
$availWeekHref = static fn(string $week): string => 'lawyer-availability.php?lawyer_id=' . $lawyerId . '&week=' . urlencode(availability_normalize_week_start($week));

$pageTitle = __('availability.view.title', ['name' => full_name($lawyer)]);
$pageSubtitle = __('availability.view.subtitle');
require __DIR__ . '/../includes/header.php';
?>
<div class="avail-view-page">
    <div class="avail-view-top">
        <a class="btn btn-secondary btn-sm" href="lawyers.php?action=view&id=<?= (int) $lawyerId ?>">← <?= __e('lawyers.back_profile') ?></a>
        <div class="avail-view-actions">
            <details class="row-actions-dropdown">
                <summary class="btn btn-primary btn-sm row-actions-toggle" aria-label="<?= __e('common.actions') ?>">
                    <span><?= __e('common.actions') ?></span>
                    <svg class="row-actions-caret" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                </summary>
                <div class="row-actions-menu">
                    <a class="row-actions-item" href="lawyer-availability.php"><?= __e('availability.view.all_lawyers') ?></a>
                    <a class="row-actions-item" href="lawyers.php?action=edit&id=<?= (int) $lawyerId ?>&from=<?= urlencode('lawyers.php?action=view&id=' . (int) $lawyerId) ?>"><?= __e('lawyers.edit_profile') ?></a>
                </div>
            </details>
        </div>
    </div>

    <div class="panel avail-panel">
        <?php require __DIR__ . '/../includes/availability-schedule-form.php'; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
