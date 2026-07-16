</main>
        <footer class="app-footer">
            <span>&copy; <?= date('Y') ?> <?= e(get_setting(db(), 'company_name', app_config('name'))) ?></span>
            <span><?= __e('app.tagline') ?></span>
        </footer>
    </div>
</div>
<script>
window.LEXORA_I18N = <?= json_encode([
    'confirm' => __('common.confirm'),
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
<?php if (!empty($includeCharts)): ?>
<script src="<?= e(app_config('url')) ?>/assets/js/chart.umd.min.js"></script>
<?php endif; ?>
<script src="<?= e(app_config('url')) ?>/assets/js/app.js"></script>
</body>
</html>
