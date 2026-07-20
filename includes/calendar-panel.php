<?php
/**
 * Shared calendar panel.
 * Expects vars from build_entity_calendar_context() extract.
 */
$calShowCreate = !empty($calShowCreate);
$calCreateUrl = $calCreateUrl ?? '?action=create';
$calCreateLabel = $calCreateLabel ?? __('appointments.create');
$calMainAria = $calMainAria ?? __('appointments.calendar');
$calViewLabel = $calViewLabel ?? __('calendar.view_appointment');
$calViewAllLabel = $calViewAllLabel ?? __('calendar.view_appointments');
$calNoViewLabel = $calNoViewLabel ?? __('calendar.no_view_available');
$calEmptyDay = $calEmptyDay ?? __('calendar.empty_day');
$calScheduleLabel = $calScheduleLabel ?? __('calendar.schedule_for_day');
$calCountOne = $calCountOne ?? __('appointments.total_one', ['count' => ':count']);
$calCountMany = $calCountMany ?? __('appointments.total_many', ['count' => ':count']);
$calAgendaLabels = $calAgendaLabels ?? [];
$calendarLegendItems = $calendarLegendItems ?? [];
?>
<div class="panel appt-calendar-panel">
    <div class="appt-calendar" id="apptCalendar"
         data-year="<?= (int) $calYear ?>"
         data-month="<?= (int) $calMonth ?>"
         data-day="<?= (int) $calDay ?>"
         data-create-url="<?= e($calCreateUrl) ?>"
         data-locale="<?= e(locale_tag()) ?>"
         data-empty-day="<?= e($calEmptyDay) ?>"
         data-schedule-label="<?= e($calScheduleLabel) ?>"
         data-view-label="<?= e($calViewLabel) ?>"
         data-view-all-label="<?= e($calViewAllLabel) ?>"
         data-no-view-label="<?= e($calNoViewLabel) ?>"
         data-appt-count-one="<?= e($calCountOne) ?>"
         data-appt-count-many="<?= e($calCountMany) ?>"
         data-page-of="<?= __e('calendar.page_of', ['page' => ':page', 'pages' => ':pages']) ?>"
         data-prev-label="<?= __e('common.previous') ?>"
         data-next-label="<?= __e('common.next') ?>">
        <aside class="appt-cal-sidebar" aria-label="<?= __e('common.calendar') ?>">
            <div class="appt-cal-year">
                <button type="button" class="appt-cal-nav" data-cal-nav="year" data-dir="-1" aria-label="<?= __e('common.previous') ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 6l-6 6 6 6"/></svg>
                </button>
                <span class="appt-cal-year-label" id="apptCalYear"><?= (int) $calYear ?></span>
                <button type="button" class="appt-cal-nav" data-cal-nav="year" data-dir="1" aria-label="<?= __e('common.next') ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg>
                </button>
            </div>
            <ul class="appt-cal-months" id="apptCalMonths">
                <?php foreach ($calendarMonths as $i => $monthName): ?>
                <li>
                    <button type="button" class="appt-cal-month-btn<?= $i === $calMonth ? ' is-active' : '' ?>" data-month="<?= $i ?>">
                        <span><?= e($monthName) ?></span>
                        <span class="appt-cal-month-count" data-month-count="<?= $i ?>"><?= (int) $calendarMonthCounts[$i] ?></span>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <section class="appt-cal-main" aria-label="<?= e($calMainAria) ?>">
            <div class="appt-cal-main-head">
                <h2 class="appt-cal-month-title" id="apptCalMonthTitle"><?= e(strtoupper($calendarMonths[$calMonth])) ?></h2>
                <?php if ($calShowCreate): ?>
                <a class="appt-cal-add" href="<?= e($calCreateUrl) ?>" title="<?= e($calCreateLabel) ?>" aria-label="<?= e($calCreateLabel) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>
                </a>
                <?php endif; ?>
            </div>
            <div class="appt-cal-weekdays" aria-hidden="true">
                <?php foreach (['mon','tue','wed','thu','fri','sat','sun'] as $wd): ?>
                <span><?= __e('calendar.weekday.' . $wd) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="appt-cal-days" id="apptCalDays" aria-label="<?= e($calMainAria) ?>"><?= render_appointment_calendar_days($calYear, $calMonth, $calDay, $calendarItemsByDate) ?></div>
            <div class="appt-cal-day-menu" id="apptCalDayMenu" hidden role="menu" aria-label="<?= e($calScheduleLabel) ?>">
                <p class="appt-cal-day-menu-date" id="apptCalDayMenuDate"></p>
                <button type="button" class="btn btn-secondary btn-sm appt-cal-view-btn" id="apptCalDayMenuView" role="menuitem"><?= e($calViewLabel) ?></button>
                <?php if (trim((string) $calCreateUrl) !== ''): ?>
                <a class="btn btn-primary btn-sm appt-cal-schedule-btn" id="apptCalDayMenuSchedule" href="#" role="menuitem" data-cal-schedule><?= e($calScheduleLabel) ?></a>
                <?php endif; ?>
            </div>
            <div class="appt-cal-legend" aria-hidden="true">
                <?php foreach ($calendarLegendItems as $legend): ?>
                <span class="appt-cal-legend-item">
                    <span class="appt-cal-dot tone-<?= e($legend['tone']) ?>"></span>
                    <?= e($legend['label']) ?>
                </span>
                <?php endforeach; ?>
            </div>
        </section>

        <aside class="appt-cal-agenda" aria-label="<?= __e('calendar.agenda') ?>">
            <h3 class="appt-cal-agenda-title"><?= __e('calendar.agenda') ?></h3>
            <div class="appt-cal-agenda-head" id="apptCalAgendaHead"><?= render_appointment_calendar_agenda_head($calYear, $calMonth, $calDay, $selectedDayItems, $calAgendaLabels) ?></div>
            <div class="appt-cal-agenda-list" id="apptCalAgenda"><?= render_appointment_calendar_agenda_list($selectedDayItems, 1, 2) ?></div>
            <div class="appt-cal-agenda-pager-wrap" id="apptCalAgendaPager"><?= render_appointment_calendar_agenda_pager(count($selectedDayItems), 1, 2) ?></div>
        </aside>
    </div>
</div>
