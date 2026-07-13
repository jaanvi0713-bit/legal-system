</main>
        <footer class="app-footer">
            <span>&copy; <?= date('Y') ?> <?= e(get_setting(db(), 'company_name', app_config('name'))) ?></span>
            <span>Legal Case Management System</span>
        </footer>
    </div>
</div>
<script src="<?= e(app_config('url')) ?>/assets/js/app.js"></script>
</body>
</html>
