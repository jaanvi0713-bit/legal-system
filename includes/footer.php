</main>
        <footer class="app-footer">
            <span>&copy; <?= date('Y') ?> <?= e(get_setting(db(), 'company_name', app_config('name'))) ?></span>
            <span><?= __e('app.tagline') ?></span>
        </footer>
    </div>
</div>
<?php require __DIR__ . '/confirm-modal.php'; ?>
<?php require __DIR__ . '/saving-buffer.php'; ?>
<script>
window.LEXORA_I18N = <?= json_encode([
    'confirm' => __('common.confirm'),
    'confirmTitle' => __('confirm.title'),
    'confirmProceed' => __('confirm.proceed'),
    'cancel' => __('common.cancel'),
    'saving' => __('common.saving'),
    'confirmDeleteClient' => __('confirm.delete_client'),
    'confirmRemoveLawyer' => __('confirm.remove_lawyer'),
    'confirmRemoveStaff' => __('confirm.remove_staff'),
    'confirmResetPassword' => __('confirm.reset_password'),
    'confirmDeleteCase' => __('confirm.delete_case'),
    'confirmDeleteHearing' => __('court.confirm_delete'),
    'confirmCancelAppointment' => __('appointments.confirm_cancel'),
    'confirmRejectAppointment' => __('confirm.reject_appointment'),
    'confirmDeactivateUser' => __('confirm.deactivate_user'),
    'confirmRemoveLine' => __('confirm.remove_line'),
    'availabilityBookingUnavailable' => __('availability.booking.unavailable'),
    'availabilityBookingSunday' => __('availability.booking.sunday'),
    'apptChooseTime' => __('availability.booking.choose_time'),
    'apptNoSlots' => __('availability.booking.no_slots'),
    'apptSelectLawyer' => __('availability.booking.select_lawyer'),
    'apptSelectDate' => __('availability.booking.select_date'),
    'apptLoadingSlots' => __('availability.booking.loading'),
    'availabilitySlotsSelected' => __('availability.slots_selected', ['count' => ':count']),
    'availabilityDayCount' => __('availability.day_count', ['count' => ':count']),
    'thinking' => __('ai.thinking'),
    'no_response' => __('ai.no_response'),
    'service_error' => __('ai.service_error'),
    'attach_disabled' => __('ai.attach_disabled'),
    'chart_cases_opened' => __('chart.cases_opened'),
    'chart_cases_closed' => __('chart.cases_closed'),
    'finance_revenue' => __('finance.revenue'),
    'status_active' => __('status.active'),
    'status_closed' => __('status.closed'),
    'weekdays' => [
        __('calendar.weekday.mon'),
        __('calendar.weekday.tue'),
        __('calendar.weekday.wed'),
        __('calendar.weekday.thu'),
        __('calendar.weekday.fri'),
        __('calendar.weekday.sat'),
        __('calendar.weekday.sun'),
    ],
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php if (!empty($apptCalPayload)): ?>
<?php
$apptCalJsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $apptCalJsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
}
$apptCalJson = json_encode($apptCalPayload, $apptCalJsonFlags);
?>
<?php if ($apptCalJson !== false): ?>
<script type="application/json" id="apptCalData"><?= $apptCalJson ?></script>
<script>
(function () {
  var el = document.getElementById('apptCalData');
  if (!el) return;
  try {
    window.LEXORA_APPT_CAL = JSON.parse(el.textContent);
  } catch (err) {
    console.error('Failed to load appointment calendar data', err);
    window.LEXORA_APPT_CAL = null;
  }
})();
</script>
<?php endif; ?>
<script src="<?= e(app_config('url')) ?>/assets/js/appt-view.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/appt-view.js') ?>"></script>
<?php endif; ?>
<?php if (!empty($includeCharts)): ?>
<script src="<?= e(app_config('url')) ?>/assets/js/chart.umd.min.js"></script>
<?php endif; ?>
<script src="<?= e(app_config('url')) ?>/assets/js/app.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body>
</html>
