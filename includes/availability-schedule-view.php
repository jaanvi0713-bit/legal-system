<?php
/**
 * Read-only weekly availability grid (Mon–Sat) — matches availability-schedule-form layout.
 *
 * Expects: $availMatrix, $availWeekStart, $availPrevWeek, $availNextWeek,
 *          $availWeekLabel, $availWeekDates, optional $availIsCurrentWeek,
 *          optional $availWeekHref (callable string week => url),
 *          optional $availLawyer (user row for read-only status strip),
 *          optional $availTotalPublishedSlots (int)
 */
$availMatrix = $availMatrix ?? [];
$availWeekStart = availability_normalize_week_start($availWeekStart ?? null);
$availPrevWeek = $availPrevWeek ?? date('Y-m-d', strtotime($availWeekStart . ' -7 days'));
$availNextWeek = $availNextWeek ?? date('Y-m-d', strtotime($availWeekStart . ' +7 days'));
$availWeekLabel = $availWeekLabel ?? availability_format_week_range($availWeekStart);
$availWeekDates = $availWeekDates ?? availability_week_dates($availWeekStart);
$availIsCurrentWeek = $availIsCurrentWeek ?? ($availWeekStart === availability_week_start());
$availSlotTimes = availability_slot_times();
$availWeekdays = availability_weekdays();
$availWeekHref = $availWeekHref ?? static fn(string $week): string => '?week=' . urlencode($week);
$availLawyer = $availLawyer ?? null;
$availTotalPublishedSlots = isset($availTotalPublishedSlots) ? (int) $availTotalPublishedSlots : null;

$availSelectedTotal = 0;
foreach ($availWeekdays as $dayNum => $_) {
    $availSelectedTotal += count($availMatrix[$dayNum] ?? []);
}

$availMorningSlots = [];
$availAfternoonSlots = [];
foreach ($availSlotTimes as $slotTime) {
    $hour = (int) substr($slotTime, 0, 2);
    if ($hour < 12) {
        $availMorningSlots[] = $slotTime;
    } else {
        $availAfternoonSlots[] = $slotTime;
    }
}
?>
<div class="avail-board">
    <div class="avail-board-top">
        <div class="avail-board-intro">
            <p class="avail-board-eyebrow"><?= __e('availability.schedule.title') ?></p>
            <p class="avail-board-lead"><?= __e($availLawyer ? 'availability.view.subtitle' : 'availability.schedule.subtitle') ?></p>
        </div>
        <?php if ($availLawyer): ?>
        <div class="avail-status-strip avail-status-strip-readonly">
            <div class="avail-status-strip-fields">
                <div class="avail-status-field">
                    <span><?= __e('lawyer.availability.current') ?></span>
                    <div class="avail-status-readonly-value"><?= status_badge($availLawyer['availability'] ?? 'available') ?></div>
                </div>
                <?php if (trim((string) ($availLawyer['notes'] ?? '')) !== ''): ?>
                <div class="avail-status-field avail-status-field-grow">
                    <span><?= __e('lawyer.availability.team_notes') ?></span>
                    <div class="avail-status-readonly-value"><?= e($availLawyer['notes']) ?></div>
                </div>
                <?php endif; ?>
                <div class="avail-status-field">
                    <span><?= __e('availability.view.week_slots') ?></span>
                    <div class="avail-status-readonly-value"><strong><?= $availSelectedTotal ?></strong></div>
                </div>
                <?php if ($availTotalPublishedSlots !== null): ?>
                <div class="avail-status-field">
                    <span><?= __e('availability.view.total_slots') ?></span>
                    <div class="avail-status-readonly-value"><strong><?= $availTotalPublishedSlots ?></strong></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="avail-week-nav">
        <a class="btn btn-secondary btn-sm avail-week-btn" href="<?= e($availWeekHref($availPrevWeek)) ?>" aria-label="<?= __e('availability.week.prev') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            <?= __e('availability.week.prev') ?>
        </a>
        <div class="avail-week-label">
            <span class="avail-week-range"><?= e($availWeekLabel) ?></span>
            <?php if (!$availIsCurrentWeek): ?>
            <a class="avail-week-today" href="<?= e($availWeekHref(availability_week_start())) ?>"><?= __e('availability.week.current') ?></a>
            <?php endif; ?>
        </div>
        <a class="btn btn-secondary btn-sm avail-week-btn" href="<?= e($availWeekHref($availNextWeek)) ?>" aria-label="<?= __e('availability.week.next') ?>">
            <?= __e('availability.week.next') ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
        </a>
    </div>

    <div class="avail-toolbar">
        <div class="avail-toolbar-meta">
            <span class="avail-stat-pill"><?= __e('availability.slots_selected', ['count' => $availSelectedTotal]) ?></span>
            <span class="avail-stat-pill muted"><?= __e('availability.hours_range') ?></span>
        </div>
        <div class="avail-view-legend" aria-hidden="true">
            <span class="avail-legend-item"><span class="avail-cell avail-cell-readonly is-on"><span class="avail-cell-dot"></span></span><?= __e('availability.view.available') ?></span>
            <span class="avail-legend-item"><span class="avail-cell avail-cell-readonly"><span class="avail-cell-dot"></span></span><?= __e('availability.view.unavailable') ?></span>
        </div>
    </div>

    <?php if ($availTotalPublishedSlots === 0): ?>
        <div class="flash flash-warning avail-view-flash"><?= __e('availability.view.no_slots_total') ?></div>
    <?php elseif ($availSelectedTotal === 0): ?>
        <div class="empty-state avail-view-empty"><?= __e('availability.view.empty_week') ?></div>
    <?php endif; ?>

    <div class="avail-matrix-scroll">
        <table class="avail-matrix avail-matrix-readonly" role="grid" aria-label="<?= __e('availability.schedule.title') ?>">
            <thead>
                <tr>
                    <th class="avail-matrix-time-col" scope="col"></th>
                    <?php foreach ($availWeekdays as $dayNum => $dayLabel):
                        $dayCount = count($availMatrix[$dayNum] ?? []);
                        $dayDate = $availWeekDates[$dayNum] ?? '';
                    ?>
                    <th class="avail-matrix-day-col" scope="col">
                        <div class="avail-matrix-day">
                            <span class="avail-matrix-day-name"><?= e($dayLabel) ?></span>
                            <?php if ($dayDate): ?>
                            <span class="avail-matrix-day-date"><?= e(availability_format_short_date($dayDate)) ?></span>
                            <?php endif; ?>
                            <span class="avail-day-count"><?= __e('availability.day_count', ['count' => $dayCount]) ?></span>
                        </div>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $periods = [
                    ['label' => __('availability.period.morning'), 'slots' => $availMorningSlots],
                    ['label' => __('availability.period.afternoon'), 'slots' => $availAfternoonSlots],
                ];
                foreach ($periods as $period):
                    if (!$period['slots']) {
                        continue;
                    }
                ?>
                <tr class="avail-period-row">
                    <th colspan="<?= count($availWeekdays) + 1 ?>" scope="rowgroup"><?= e($period['label']) ?></th>
                </tr>
                <?php foreach ($period['slots'] as $slotTime):
                    $slotKey = substr($slotTime, 0, 5);
                ?>
                <tr class="avail-matrix-row">
                    <th class="avail-matrix-time" scope="row"><?= e(availability_format_slot_label($slotTime)) ?></th>
                    <?php foreach ($availWeekdays as $dayNum => $_):
                        $isOn = !empty($availMatrix[$dayNum][$slotKey]);
                    ?>
                    <td class="avail-matrix-cell">
                        <span
                            class="avail-cell avail-cell-readonly<?= $isOn ? ' is-on' : '' ?>"
                            title="<?= e(($availWeekdays[$dayNum] ?? '') . ' · ' . availability_format_slot_label($slotTime)) ?>"
                            aria-label="<?= e(($availWeekdays[$dayNum] ?? '') . ' ' . availability_format_slot_label($slotTime) . ($isOn ? ' — ' . __('availability.view.available') : ' — ' . __('availability.view.unavailable'))) ?>"
                        >
                            <span class="avail-cell-dot" aria-hidden="true"></span>
                        </span>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="avail-schedule-footer avail-schedule-footer-readonly">
        <p class="avail-schedule-hint"><?= __e('availability.view.readonly_hint') ?></p>
    </div>
</div>
