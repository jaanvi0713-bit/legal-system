<?php
/**
 * Weekly availability slot picker (Mon–Sat), one calendar week at a time.
 *
 * Expects: $availMatrix, $availWeekStart, $availPrevWeek, $availNextWeek,
 *          $availWeekLabel, $availWeekDates, optional $availIsCurrentWeek, optional $availStatusForm
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
$showStatusForm = !empty($availStatusForm);
$u = $u ?? current_user();

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
            <p class="avail-board-lead"><?= __e('availability.schedule.subtitle') ?></p>
        </div>
        <?php if ($showStatusForm): ?>
        <form method="post" class="avail-status-strip">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="status">
            <div class="avail-status-strip-fields">
                <label class="avail-status-field">
                    <span><?= __e('lawyer.availability.current') ?></span>
                    <select id="availability" name="availability">
                        <?php foreach (['available', 'busy', 'unavailable'] as $value): ?>
                            <option value="<?= e($value) ?>" <?= ($u['availability'] ?? '') === $value ? 'selected' : '' ?>><?= e(translate_status($value)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="avail-status-field avail-status-field-grow">
                    <span><?= __e('lawyer.availability.team_notes') ?></span>
                    <input id="notes" type="text" name="notes" value="<?= e($u['notes'] ?? '') ?>" placeholder="<?= __e('common.notes') ?>">
                </label>
                <button class="btn btn-secondary btn-sm" type="submit"><?= __e('lawyer.availability.save_status') ?></button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <form method="post" class="avail-schedule-form" id="availScheduleForm">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="slots">
        <input type="hidden" name="week_start" value="<?= e($availWeekStart) ?>">

        <div class="avail-week-nav">
            <a class="btn btn-secondary btn-sm avail-week-btn" href="?week=<?= e($availPrevWeek) ?>" aria-label="<?= __e('availability.week.prev') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                <?= __e('availability.week.prev') ?>
            </a>
            <div class="avail-week-label">
                <span class="avail-week-range"><?= e($availWeekLabel) ?></span>
                <?php if (!$availIsCurrentWeek): ?>
                <a class="avail-week-today" href="?week=<?= e(availability_week_start()) ?>"><?= __e('availability.week.current') ?></a>
                <?php endif; ?>
            </div>
            <a class="btn btn-secondary btn-sm avail-week-btn" href="?week=<?= e($availNextWeek) ?>" aria-label="<?= __e('availability.week.next') ?>">
                <?= __e('availability.week.next') ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
            </a>
        </div>

        <div class="avail-toolbar">
            <div class="avail-toolbar-meta">
                <span class="avail-stat-pill" id="availSelectedTotal"><?= __e('availability.slots_selected', ['count' => $availSelectedTotal]) ?></span>
                <span class="avail-stat-pill muted"><?= __e('availability.hours_range') ?></span>
            </div>
            <div class="avail-quick-actions">
                <button type="button" class="btn btn-secondary btn-sm" data-avail-preset="weekdays"><?= __e('availability.preset.weekdays') ?></button>
                <button type="button" class="btn btn-secondary btn-sm" data-avail-preset="all"><?= __e('availability.preset.all') ?></button>
                <button type="button" class="btn btn-secondary btn-sm" data-avail-preset="clear"><?= __e('availability.preset.clear') ?></button>
            </div>
        </div>

        <div class="avail-matrix-scroll">
            <table class="avail-matrix" role="grid" aria-label="<?= __e('availability.schedule.title') ?>">
                <thead>
                    <tr>
                        <th class="avail-matrix-time-col" scope="col"></th>
                        <?php foreach ($availWeekdays as $dayNum => $dayLabel):
                            $dayCount = count($availMatrix[$dayNum] ?? []);
                            $dayDate = $availWeekDates[$dayNum] ?? '';
                        ?>
                        <th class="avail-matrix-day-col" scope="col" data-avail-day="<?= (int) $dayNum ?>">
                            <div class="avail-matrix-day">
                                <span class="avail-matrix-day-name"><?= e($dayLabel) ?></span>
                                <?php if ($dayDate): ?>
                                <span class="avail-matrix-day-date"><?= e(availability_format_short_date($dayDate)) ?></span>
                                <?php endif; ?>
                                <span class="avail-day-count" data-avail-day-count="<?= (int) $dayNum ?>"><?= __e('availability.day_count', ['count' => $dayCount]) ?></span>
                                <div class="avail-day-actions">
                                    <button type="button" class="avail-day-btn" data-avail-day-toggle="on"><?= __e('availability.day.all') ?></button>
                                    <button type="button" class="avail-day-btn" data-avail-day-toggle="off"><?= __e('availability.day.none') ?></button>
                                </div>
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
                        if (!$period['slots']) continue;
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
                            $value = $dayNum . '-' . $slotKey;
                            $isOn = !empty($availMatrix[$dayNum][$slotKey]);
                            $inputId = 'avail-' . $dayNum . '-' . str_replace(':', '', $slotKey);
                        ?>
                        <td class="avail-matrix-cell">
                            <label class="avail-cell<?= $isOn ? ' is-on' : '' ?>" for="<?= e($inputId) ?>" title="<?= e($availWeekdays[$dayNum] . ' · ' . availability_format_slot_label($slotTime)) ?>">
                                <input
                                    type="checkbox"
                                    class="avail-slot-input"
                                    name="slots[]"
                                    id="<?= e($inputId) ?>"
                                    value="<?= e($value) ?>"
                                    <?= $isOn ? 'checked' : '' ?>
                                >
                                <span class="avail-cell-dot" aria-hidden="true"></span>
                                <span class="sr-only"><?= e(($availWeekdays[$dayNum] ?? '') . ' ' . availability_format_slot_label($slotTime)) ?></span>
                            </label>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="avail-schedule-footer">
            <p class="avail-schedule-hint"><?= __e('availability.schedule.hint') ?></p>
            <button class="btn btn-primary" type="submit"><?= __e('lawyer.availability.save') ?></button>
        </div>
    </form>
</div>
