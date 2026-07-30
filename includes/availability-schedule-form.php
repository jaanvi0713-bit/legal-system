<?php
/**
 * Weekly availability as date + time range blocks with calendar sidebar.
 *
 * Expects: $availWeekStart, $availPrevWeek, $availNextWeek, $availWeekLabel,
 *          optional $availIsCurrentWeek, optional $availLawyerId,
 *          optional $availReadOnly, optional $availWeekHref
 */
$availWeekStart = availability_normalize_week_start($availWeekStart ?? null);
$availPrevWeek = $availPrevWeek ?? date('Y-m-d', strtotime($availWeekStart . ' -7 days'));
$availNextWeek = $availNextWeek ?? date('Y-m-d', strtotime($availWeekStart . ' +7 days'));
$availWeekLabel = $availWeekLabel ?? availability_format_week_range($availWeekStart);
$availIsCurrentWeek = $availIsCurrentWeek ?? ($availWeekStart === availability_week_start());
$availReadOnly = !empty($availReadOnly);
$availWeekHref = $availWeekHref ?? static fn(string $week): string => '?week=' . urlencode(availability_normalize_week_start($week));
$u = $u ?? current_user();
$pdoAvail = db();
$lawyerId = (int) ($availLawyerId ?? ($u['id'] ?? 0));
$availWeekdays = availability_weekdays();
$availWeekDates = availability_week_dates($availWeekStart);
$availBlocks = get_lawyer_availability_blocks_for_week($pdoAvail, $lawyerId, $availWeekStart);
$availDateOptions = [];
$dayBlockCounts = [];
foreach ($availWeekdays as $dayNum => $dayLabel) {
    $dayDate = $availWeekDates[$dayNum] ?? '';
    if ($dayDate === '') {
        continue;
    }
    $availDateOptions[] = [
        'value' => $dayDate,
        'day' => (int) $dayNum,
        'label' => availability_format_day_heading($dayDate, $dayLabel),
        'short' => $dayLabel,
    ];
    $dayBlockCounts[$dayNum] = 0;
}
foreach ($availBlocks as $block) {
    $dow = (int) date('N', strtotime($block['block_date']));
    if (isset($dayBlockCounts[$dow])) {
        $dayBlockCounts[$dow]++;
    }
}

$todayDate = date('Y-m-d');
$defaultTabDay = (string) array_key_first($availWeekdays);
foreach ($availWeekdays as $dayNum => $_) {
    if (($availWeekDates[$dayNum] ?? '') === $todayDate) {
        $defaultTabDay = (string) $dayNum;
        break;
    }
}
$defaultDate = $availWeekDates[(int) $defaultTabDay] ?? ($availDateOptions[0]['value'] ?? $todayDate);
$defaultDayLabel = $availWeekdays[(int) $defaultTabDay] ?? '';
$defaultDayHeading = availability_format_day_heading($defaultDate, $defaultDayLabel);
$totalBlocks = count($availBlocks);
?>
<div class="avail-board avail-board-blocks">
    <?php if ($availReadOnly): ?>
    <div class="avail-schedule-form avail-schedule-readonly" id="availBlocksForm" data-avail-blocks-form>
    <?php else: ?>
    <form method="post" class="avail-schedule-form" id="availBlocksForm" data-avail-blocks-form>
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="blocks">
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
                    <?php foreach ($availDateOptions as $opt):
                        $dayNum = $opt['day'];
                        $dayDate = $opt['value'];
                        $isToday = $dayDate === $todayDate;
                        $isDefault = (string) $dayNum === $defaultTabDay;
                    ?>
                    <li>
                        <button
                            type="button"
                            class="appt-cal-month-btn avail-day-tab<?= $isDefault ? ' is-active' : '' ?><?= $isToday ? ' is-today' : '' ?>"
                            role="tab"
                            aria-selected="<?= $isDefault ? 'true' : 'false' ?>"
                            data-avail-tab="<?= (int) $dayNum ?>"
                            data-avail-date="<?= e($dayDate) ?>"
                            data-avail-day-label="<?= e($opt['label']) ?>"
                        >
                            <span class="avail-day-tab-text">
                                <span class="avail-day-tab-name"><?= e($opt['short']) ?></span>
                                <span class="avail-day-tab-date"><?= e(availability_format_day_line($dayDate)) ?></span>
                            </span>
                            <span class="appt-cal-month-count avail-day-tab-count" data-avail-day-count="<?= (int) $dayNum ?>"><?= (int) ($dayBlockCounts[$dayNum] ?? 0) ?></span>
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <div class="avail-rail-meta">
                    <span class="avail-rail-stat" id="availBlocksTotal" data-total-tpl="<?= e(__('availability.blocks.total')) ?>"><?= __e('availability.blocks.total', ['count' => $totalBlocks]) ?></span>
                </div>
            </aside>

            <div class="avail-workspace">
                <header class="avail-panel-head">
                    <div>
                        <h2 class="avail-panel-title" id="availBlocksDayTitle"><?= e($defaultDayHeading) ?></h2>
                        <p class="avail-panel-sub muted" id="availBlocksDayMeta" data-day-tpl="<?= e(__('availability.blocks.day_count')) ?>"><?= __e('availability.blocks.day_count', ['count' => (int) ($dayBlockCounts[(int) $defaultTabDay] ?? 0)]) ?></p>
                    </div>
                    <?php if (!$availReadOnly): ?>
                    <div class="avail-day-actions">
                        <button type="button" class="btn btn-secondary btn-sm" id="availAddBlock" data-avail-add-block><?= __e('availability.blocks.add') ?></button>
                    </div>
                    <?php endif; ?>
                </header>

                <div class="table-wrap avail-blocks-table-wrap">
                    <table class="avail-blocks-table">
                        <thead>
                            <tr>
                                <th class="avail-blocks-date-col"><?= __e('common.date') ?></th>
                                <th><?= __e('lawyer.availability.start_time') ?></th>
                                <th><?= __e('lawyer.availability.end_time') ?></th>
                                <th><?= __e('common.status') ?></th>
                                <?php if (!$availReadOnly): ?><th class="col-actions"><?= __e('common.actions') ?></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="availBlocksBody"
                               data-default-date="<?= e($defaultDate) ?>"
                               data-default-day="<?= e($defaultTabDay) ?>"
                               data-empty-label="<?= __e('availability.blocks.empty_day') ?>">
                            <?php if (!$availBlocks && $availReadOnly): ?>
                            <tr class="avail-blocks-empty-row" data-avail-empty-row>
                                <td colspan="4" class="muted avail-blocks-empty"><?= __e('availability.view.empty_week') ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach ($availBlocks as $i => $block): ?>
                            <tr class="avail-block-row" data-avail-block-row data-block-date="<?= e($block['block_date']) ?>">
                                <?php if ($availReadOnly): ?>
                                <td><?= e(availability_format_day_line($block['block_date'])) ?></td>
                                <td><?= e(availability_format_slot_label($block['start_time'] . ':00')) ?></td>
                                <td><?= e(availability_format_slot_label($block['end_time'] . ':00')) ?></td>
                                <td><?= status_badge($block['is_available'] ? 'available' : 'unavailable') ?></td>
                                <?php else: ?>
                                <td class="avail-blocks-date-col">
                                    <select name="blocks[<?= (int) $i ?>][block_date]" data-avail-block-date-select required>
                                        <?php foreach ($availDateOptions as $opt): ?>
                                        <option value="<?= e($opt['value']) ?>"<?= $opt['value'] === $block['block_date'] ? ' selected' : '' ?>><?= e($opt['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="time" name="blocks[<?= (int) $i ?>][start]" value="<?= e($block['start_time']) ?>" step="300" required></td>
                                <td><input type="time" name="blocks[<?= (int) $i ?>][end]" value="<?= e($block['end_time']) ?>" step="300" required></td>
                                <td>
                                    <select name="blocks[<?= (int) $i ?>][status]" required>
                                        <option value="available"<?= $block['is_available'] ? ' selected' : '' ?>><?= __e('availability.view.available') ?></option>
                                        <option value="unavailable"<?= !$block['is_available'] ? ' selected' : '' ?>><?= __e('availability.view.unavailable') ?></option>
                                    </select>
                                </td>
                                <td class="col-actions">
                                    <button type="button" class="btn btn-row-delete btn-sm" data-avail-remove-block aria-label="<?= __e('common.delete') ?>"><?= __e('common.delete') ?></button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <template id="availBlockRowTemplate">
                    <tr class="avail-block-row" data-avail-block-row data-block-date="__DATE__">
                        <td class="avail-blocks-date-col">
                            <select name="blocks[__INDEX__][block_date]" data-avail-block-date-select required>
                                <?php foreach ($availDateOptions as $opt): ?>
                                <option value="<?= e($opt['value']) ?>"><?= e($opt['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="time" name="blocks[__INDEX__][start]" value="09:00" step="300" required></td>
                        <td><input type="time" name="blocks[__INDEX__][end]" value="17:00" step="300" required></td>
                        <td>
                            <select name="blocks[__INDEX__][status]" required>
                                <option value="available"><?= __e('availability.view.available') ?></option>
                                <option value="unavailable"><?= __e('availability.view.unavailable') ?></option>
                            </select>
                        </td>
                        <td class="col-actions">
                            <button type="button" class="btn btn-row-delete btn-sm" data-avail-remove-block aria-label="<?= __e('common.delete') ?>"><?= __e('common.delete') ?></button>
                        </td>
                    </tr>
                </template>

                <div class="avail-workspace-bottom">
                    <p class="avail-schedule-hint"><?= __e($availReadOnly ? 'availability.blocks.readonly_hint' : 'availability.blocks.hint') ?></p>
                    <?php if (!$availReadOnly): ?>
                    <div class="avail-schedule-actions">
                        <button class="btn btn-primary" type="submit"><?= __e('lawyer.availability.save') ?></button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php if ($availReadOnly): ?>
    </div>
    <?php else: ?>
    </form>
    <?php endif; ?>
</div>
