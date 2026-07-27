<?php
/**
 * Weekly availability schedule (Mon–Sat) — editable or read-only.
 *
 * Expects: $availMatrix, $availWeekStart, $availPrevWeek, $availNextWeek,
 *          $availWeekLabel, $availWeekDates, optional $availIsCurrentWeek,
 *          optional $availDayHours, optional $availLawyerId,
 *          optional $availReadOnly, optional $availWeekHref (callable week => url)
 */
$availMatrix = $availMatrix ?? [];
$availWeekStart = availability_normalize_week_start($availWeekStart ?? null);
$availPrevWeek = $availPrevWeek ?? date('Y-m-d', strtotime($availWeekStart . ' -7 days'));
$availNextWeek = $availNextWeek ?? date('Y-m-d', strtotime($availWeekStart . ' +7 days'));
$availWeekLabel = $availWeekLabel ?? availability_format_week_range($availWeekStart);
$availWeekDates = $availWeekDates ?? availability_week_dates($availWeekStart);
$availIsCurrentWeek = $availIsCurrentWeek ?? ($availWeekStart === availability_week_start());
$availReadOnly = !empty($availReadOnly);
$availWeekHref = $availWeekHref ?? static fn(string $week): string => '?week=' . urlencode(availability_normalize_week_start($week));
$u = $u ?? current_user();
$pdoAvail = db();
$lawyerId = (int) ($availLawyerId ?? ($u['id'] ?? 0));
$availDayHours = $availDayHours ?? get_lawyer_week_day_hours($pdoAvail, $lawyerId, $availWeekStart);
$availWeekdays = availability_weekdays();

$availSelectedTotal = 0;
foreach ($availWeekdays as $dayNum => $_) {
    $availSelectedTotal += count($availMatrix[$dayNum] ?? []);
}

$todayDate = date('Y-m-d');
$defaultTabDay = (string) array_key_first($availWeekdays);
foreach ($availWeekdays as $dayNum => $_) {
    if (($availWeekDates[$dayNum] ?? '') === $todayDate) {
        $defaultTabDay = (string) $dayNum;
        break;
    }
}
?>
<div class="avail-board">
    <?php if ($availReadOnly): ?>
    <div class="avail-schedule-form avail-schedule-readonly" id="availScheduleForm">
    <?php else: ?>
    <form method="post" class="avail-schedule-form" id="availScheduleForm">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="slots">
        <input type="hidden" name="week_start" value="<?= e($availWeekStart) ?>">
    <?php endif; ?>

        <div class="avail-layout">
            <aside class="avail-rail appt-cal-sidebar" aria-label="<?= __e('availability.schedule.title') ?>">
                <div class="appt-cal-year avail-rail-week">
                    <a class="appt-cal-nav avail-rail-arrow" href="<?= e($availWeekHref($availPrevWeek)) ?>" aria-label="<?= __e('availability.week.prev') ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                    </a>
                    <div class="avail-rail-week-center">
                        <span class="appt-cal-year-label avail-rail-week-range"><?= e($availWeekLabel) ?></span>
                        <?php if (!$availIsCurrentWeek): ?>
                        <a class="avail-rail-week-jump" href="<?= e($availWeekHref(availability_week_start())) ?>"><?= __e('availability.week.current') ?></a>
                        <?php endif; ?>
                    </div>
                    <a class="appt-cal-nav avail-rail-arrow" href="<?= e($availWeekHref($availNextWeek)) ?>" aria-label="<?= __e('availability.week.next') ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg>
                    </a>
                </div>

                <ul class="appt-cal-months avail-day-nav" role="tablist">
                    <?php foreach ($availWeekdays as $dayNum => $dayLabel):
                        $dayCount = count($availMatrix[$dayNum] ?? []);
                        $dayDate = $availWeekDates[$dayNum] ?? '';
                        $isToday = $dayDate === $todayDate;
                        $isDefault = (string) $dayNum === $defaultTabDay;
                        $dayDateLabel = $dayDate ? availability_format_day_line($dayDate) : '';
                    ?>
                    <li>
                        <button
                            type="button"
                            class="appt-cal-month-btn avail-day-tab<?= $isDefault ? ' is-active' : '' ?><?= $isToday ? ' is-today' : '' ?>"
                            role="tab"
                            aria-selected="<?= $isDefault ? 'true' : 'false' ?>"
                            data-avail-tab="<?= (int) $dayNum ?>"
                        >
                            <span class="avail-day-tab-text">
                                <span class="avail-day-tab-name"><?= e($dayLabel) ?></span>
                                <?php if ($dayDateLabel): ?><span class="avail-day-tab-date"><?= e($dayDateLabel) ?></span><?php endif; ?>
                            </span>
                            <span class="appt-cal-month-count avail-day-tab-count" data-avail-day-count="<?= (int) $dayNum ?>"><?= (int) $dayCount ?></span>
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <div class="avail-rail-meta">
                    <span class="avail-rail-stat" id="availSelectedTotal"><?= __e('availability.slots_selected', ['count' => $availSelectedTotal]) ?></span>
                </div>

                <?php if (!$availReadOnly): ?>
                <div class="avail-rail-presets">
                    <span class="avail-rail-presets-label"><?= __e('availability.preset.label') ?></span>
                    <div class="avail-rail-preset-group" role="group" aria-label="<?= __e('availability.preset.label') ?>">
                        <button type="button" class="avail-rail-preset" data-avail-preset="weekdays"><?= __e('availability.preset.weekdays') ?></button>
                        <button type="button" class="avail-rail-preset" data-avail-preset="weekend"><?= __e('availability.preset.weekend') ?></button>
                    </div>
                </div>
                <?php endif; ?>
            </aside>

            <div class="avail-workspace">
                <?php foreach ($availWeekdays as $dayNum => $dayLabel):
                    $dayDate = $availWeekDates[$dayNum] ?? '';
                    $isDefault = (string) $dayNum === $defaultTabDay;
                    $dayHeading = availability_format_day_heading($dayDate, $dayLabel);
                    $dayHours = $availDayHours[$dayNum] ?? availability_hours_defaults();
                    $dayIntervalInput = availability_interval_input_parts((int) $dayHours['interval']);
                    $daySlotTimes = availability_slot_times_for_hours($dayHours);
                    $dayLastSlot = availability_last_slot_time($dayHours);
                    $dayHoursRange = availability_format_hours_range_for_hours($dayHours);
                    $intervalUnitLabel = $dayIntervalInput['unit'] === 'hours'
                        ? __('lawyer.availability.interval_hours')
                        : __('lawyer.availability.interval_minutes');
                ?>
                <section
                    class="avail-day-panel<?= $isDefault ? ' is-active' : '' ?>"
                    data-avail-panel="<?= (int) $dayNum ?>"
                    data-avail-day="<?= (int) $dayNum ?>"
                    data-avail-start="<?= e(substr($dayHours['start'], 0, 5)) ?>"
                    data-avail-end="<?= e(substr($dayHours['end'], 0, 5)) ?>"
                    data-avail-last-slot="<?= e($dayLastSlot) ?>"
                    role="tabpanel"
                    <?= $isDefault ? '' : 'hidden' ?>
                >
                    <header class="avail-panel-head">
                        <div>
                            <h2 class="avail-panel-title"><?= e($dayHeading) ?></h2>
                            <p class="avail-panel-sub" data-avail-hours-range><?= e($dayHoursRange) ?></p>
                        </div>
                        <?php if (!$availReadOnly): ?>
                        <div class="avail-day-actions">
                            <button type="button" class="avail-panel-action" data-avail-day-toggle="on"><?= __e('availability.day.all') ?></button>
                            <button type="button" class="avail-panel-action" data-avail-day-toggle="off"><?= __e('availability.day.none') ?></button>
                        </div>
                        <?php endif; ?>
                    </header>

                    <div class="avail-day-hours"<?= $availReadOnly ? '' : ' data-avail-day-hours' ?>>
                        <div class="avail-day-hours-fields">
                            <?php if ($availReadOnly): ?>
                            <div class="avail-hours-field">
                                <span><?= __e('lawyer.availability.start_time') ?></span>
                                <div class="avail-hours-readonly-value"><?= e(availability_format_slot_label($dayHours['start'])) ?></div>
                            </div>
                            <div class="avail-hours-field">
                                <span><?= __e('lawyer.availability.end_time') ?></span>
                                <div class="avail-hours-readonly-value"><?= e(availability_format_slot_label($dayHours['end'])) ?></div>
                            </div>
                            <div class="avail-hours-field avail-hours-field-interval">
                                <span><?= __e('lawyer.availability.interval') ?></span>
                                <div class="avail-hours-readonly-value"><?= e((string) $dayIntervalInput['value'] . ' ' . $intervalUnitLabel) ?></div>
                            </div>
                            <?php else: ?>
                            <label class="avail-hours-field">
                                <span><?= __e('lawyer.availability.start_time') ?></span>
                                <input type="time" name="day_hours[<?= (int) $dayNum ?>][start]" value="<?= e(substr($dayHours['start'], 0, 5)) ?>" step="900" data-avail-day-start required>
                            </label>
                            <label class="avail-hours-field">
                                <span><?= __e('lawyer.availability.end_time') ?></span>
                                <input type="time" name="day_hours[<?= (int) $dayNum ?>][end]" value="<?= e(substr($dayHours['end'], 0, 5)) ?>" step="900" data-avail-day-end required>
                            </label>
                            <label class="avail-hours-field avail-hours-field-interval">
                                <span><?= __e('lawyer.availability.interval') ?></span>
                                <div class="avail-interval-control">
                                    <input
                                        type="number"
                                        class="avail-interval-value"
                                        name="day_hours[<?= (int) $dayNum ?>][interval_value]"
                                        value="<?= (int) $dayIntervalInput['value'] ?>"
                                        min="5"
                                        step="1"
                                        inputmode="numeric"
                                        data-avail-day-interval-value
                                        required
                                    >
                                    <div class="avail-interval-units" role="radiogroup" aria-label="<?= __e('lawyer.availability.interval_unit') ?>">
                                        <label class="avail-interval-unit">
                                            <input type="radio" name="day_hours[<?= (int) $dayNum ?>][interval_unit]" value="minutes" data-avail-day-interval-unit <?= $dayIntervalInput['unit'] === 'minutes' ? 'checked' : '' ?>>
                                            <span><?= __e('lawyer.availability.interval_minutes') ?></span>
                                        </label>
                                        <label class="avail-interval-unit">
                                            <input type="radio" name="day_hours[<?= (int) $dayNum ?>][interval_unit]" value="hours" data-avail-day-interval-unit <?= $dayIntervalInput['unit'] === 'hours' ? 'checked' : '' ?>>
                                            <span><?= __e('lawyer.availability.interval_hours') ?></span>
                                        </label>
                                    </div>
                                </div>
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($availReadOnly): ?>
                    <?php
                    $availableSlots = [];
                    foreach ($daySlotTimes as $slotTime) {
                        $slotKey = substr($slotTime, 0, 5);
                        if (!empty($availMatrix[$dayNum][$slotKey])) {
                            $availableSlots[] = $slotTime;
                        }
                    }
                    $showcaseMorning = [];
                    $showcaseAfternoon = [];
                    foreach ($availableSlots as $slotTime) {
                        if ((int) substr($slotTime, 0, 2) < 12) {
                            $showcaseMorning[] = $slotTime;
                        } else {
                            $showcaseAfternoon[] = $slotTime;
                        }
                    }
                    $showcasePeriods = [
                        ['key' => 'morning', 'label' => __('availability.period.morning'), 'slots' => $showcaseMorning],
                        ['key' => 'afternoon', 'label' => __('availability.period.afternoon'), 'slots' => $showcaseAfternoon],
                    ];
                    ?>
                    <div class="avail-schedule-showcase">
                        <div class="avail-showcase-head">
                            <div class="avail-showcase-intro">
                                <span class="avail-showcase-label"><?= __e('availability.view.available_slots') ?></span>
                                <p class="avail-showcase-count"><?= __e('availability.view.slots_open', ['count' => count($availableSlots)]) ?></p>
                            </div>
                            <?php if ($availableSlots): ?>
                            <span class="avail-showcase-badge"><?= __e('availability.view.bookable') ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($availableSlots): ?>
                        <div class="avail-showcase-timeline">
                            <?php foreach ($showcasePeriods as $period):
                                if (!$period['slots']) {
                                    continue;
                                }
                            ?>
                            <section class="avail-showcase-period">
                                <h3 class="avail-showcase-period-label"><?= e($period['label']) ?></h3>
                                <ul class="avail-showcase-grid">
                                    <?php foreach ($period['slots'] as $slotTime):
                                        $chip = availability_slot_chip_parts($slotTime);
                                    ?>
                                    <li class="avail-showcase-slot">
                                        <span class="avail-showcase-slot-time"><?= e($chip['clock']) ?></span>
                                        <?php if ($chip['meridiem'] !== ''): ?>
                                        <span class="avail-showcase-slot-meridiem"><?= e($chip['meridiem']) ?></span>
                                        <?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="avail-showcase-empty">
                            <span class="avail-showcase-empty-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            </span>
                            <p><?= __e('availability.view.empty_day') ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="avail-slot-list">
                        <?php foreach ($daySlotTimes as $slotTime):
                            $slotKey = substr($slotTime, 0, 5);
                            $value = $dayNum . '-' . $slotKey;
                            $isOn = !empty($availMatrix[$dayNum][$slotKey]);
                            $inputId = 'avail-' . $dayNum . '-' . str_replace(':', '', $slotKey);
                            $slotLabel = availability_format_slot_label($slotTime);
                        ?>
                        <label class="avail-cell avail-slot<?= $isOn ? ' is-on' : '' ?>" for="<?= e($inputId) ?>">
                            <input
                                type="checkbox"
                                class="avail-slot-input"
                                name="slots[]"
                                id="<?= e($inputId) ?>"
                                value="<?= e($value) ?>"
                                <?= $isOn ? 'checked' : '' ?>
                            >
                            <span class="avail-slot-time"><?= e($slotLabel) ?></span>
                            <span class="avail-slot-toggle" aria-hidden="true"></span>
                            <span class="sr-only"><?= e($dayLabel . ' ' . $slotLabel) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>
                <?php endforeach; ?>

                <footer class="avail-schedule-footer<?= $availReadOnly ? ' avail-schedule-footer-readonly' : '' ?>">
                    <p class="avail-schedule-hint"><?= __e($availReadOnly ? 'availability.view.readonly_hint' : 'availability.schedule.hint') ?></p>
                    <?php if (!$availReadOnly): ?>
                    <button class="btn btn-primary" type="submit"><?= __e('lawyer.availability.save') ?></button>
                    <?php endif; ?>
                </footer>
            </div>
        </div>
    <?php if ($availReadOnly): ?>
    </div>
    <?php else: ?>
    </form>
    <?php endif; ?>
</div>
