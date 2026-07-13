<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $keys = [
        'company_name','company_email','company_phone','company_address',
        'branding_primary','branding_accent','theme','ai_enabled',
        'ai_welcome_admin','ai_welcome_lawyer','ai_welcome_client',
    ];
    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            set_setting($pdo, $key, post($key));
        }
    }
    flash('success', 'Settings saved.');
    redirect('settings.php');
}

$get = fn($k, $d = '') => get_setting($pdo, $k, $d);
$pageTitle = 'System Settings';
$pageSubtitle = 'Company, branding, AI, and general configuration';
$portal = 'admin';
$activeNav = 'settings';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <div class="form-group"><label>Company name</label><input name="company_name" value="<?= e($get('company_name', 'Lexora Legal Partners')) ?>"></div>
        <div class="form-group"><label>Company email</label><input name="company_email" value="<?= e($get('company_email')) ?>"></div>
        <div class="form-group"><label>Company phone</label><input name="company_phone" value="<?= e($get('company_phone')) ?>"></div>
        <div class="form-group"><label>Theme</label><select name="theme"><option value="light" <?= $get('theme')==='light'?'selected':'' ?>>Light</option><option value="dark" <?= $get('theme')==='dark'?'selected':'' ?>>Dark</option></select></div>
        <div class="form-group full"><label>Company address</label><textarea name="company_address"><?= e($get('company_address')) ?></textarea></div>
        <div class="form-group"><label>Primary brand color</label><input name="branding_primary" value="<?= e($get('branding_primary', '#1a3a4a')) ?>"></div>
        <div class="form-group"><label>Accent color</label><input name="branding_accent" value="<?= e($get('branding_accent', '#c4a35a')) ?>"></div>
        <div class="form-group"><label>AI enabled</label><select name="ai_enabled"><option value="1" <?= $get('ai_enabled','1')==='1'?'selected':'' ?>>Yes</option><option value="0" <?= $get('ai_enabled')==='0'?'selected':'' ?>>No</option></select></div>
        <div class="form-group full"><label>Admin AI system prompt</label><textarea name="ai_welcome_admin"><?= e($get('ai_welcome_admin')) ?></textarea></div>
        <div class="form-group full"><label>Lawyer AI system prompt</label><textarea name="ai_welcome_lawyer"><?= e($get('ai_welcome_lawyer')) ?></textarea></div>
        <div class="form-group full"><label>Client AI system prompt</label><textarea name="ai_welcome_client"><?= e($get('ai_welcome_client')) ?></textarea></div>
        <div class="form-actions full"><button class="btn btn-primary" type="submit">Save settings</button></div>
    </form>
    <p class="muted" style="margin-top:1rem;">For live OpenAI responses, set <code>openai_api_key</code> in <code>config/app.php</code>. Security &amp; role permissions are managed under User Management.</p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
