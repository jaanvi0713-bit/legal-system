</main>
        <footer class="app-footer">
            <span>&copy; <?= date('Y') ?> <?= e(get_setting(db(), 'company_name', app_config('name'))) ?></span>
            <span><?= __e('app.tagline') ?></span>
        </footer>
    </div>
</div>
<?php if (!empty($includeCharts)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<?php endif; ?>
<script src="<?= e(app_config('url')) ?>/assets/js/app.js"></script>
</body>
</html>
