<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);
$pdo = db();
$user = current_user();

$tabs = [
    'profile' => 'My Profile',
    'notifications' => 'Notification Preferences',
    'branding' => 'Branding',
    'email' => 'Email / SMTP',
    'payments' => 'Payments',
    'roles' => 'Role Access',
    'backup' => 'Backup',
];
$tab = get('tab', 'branding');
if (!isset($tabs[$tab])) {
    $tab = 'branding';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $section = post('settings_tab', $tab);

    if ($section === 'profile') {
        $pdo->prepare('UPDATE users SET first_name=?, last_name=?, phone=?, address=? WHERE id=?')
            ->execute([post('first_name'), post('last_name'), post('phone'), post('address'), (int) $user['id']]);
        if (post('password')) {
            $pdo->prepare('UPDATE users SET password=? WHERE id=?')
                ->execute([password_hash(post('password'), PASSWORD_DEFAULT), (int) $user['id']]);
        }
        refresh_session_user();
        flash('success', 'Profile updated.');
    } elseif ($section === 'notifications') {
        foreach (['notify_appointments', 'notify_payments', 'notify_cases', 'notify_email_digest'] as $key) {
            set_setting($pdo, $key, post($key, '0') === '1' ? '1' : '0');
        }
        flash('success', 'Notification preferences saved.');
    } elseif ($section === 'branding') {
        foreach ([
            'company_name', 'company_email', 'company_phone', 'company_address',
            'company_website', 'company_registration', 'branding_font',
            'branding_primary', 'branding_secondary', 'branding_accent', 'theme',
            'ai_enabled', 'ai_welcome_admin', 'ai_welcome_lawyer', 'ai_welcome_client',
        ] as $key) {
            if (isset($_POST[$key])) {
                set_setting($pdo, $key, post($key));
            }
        }
        flash('success', 'Branding settings saved.');
    } elseif ($section === 'email') {
        foreach (['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from', 'smtp_encryption'] as $key) {
            set_setting($pdo, $key, post($key));
        }
        flash('success', 'Email / SMTP settings saved.');
    } elseif ($section === 'payments') {
        foreach (['payment_currency', 'payment_methods', 'payment_instructions'] as $key) {
            set_setting($pdo, $key, post($key));
        }
        flash('success', 'Payment settings saved.');
    } elseif ($section === 'backup') {
        flash('success', 'Backup preferences noted. Export schema from phpMyAdmin or run database/schema.sql + seed.sql.');
    }

    redirect('settings.php?tab=' . urlencode($section));
}

$get = fn($k, $d = '') => get_setting($pdo, $k, $d);
$user = current_user();
$base = app_config('url');

$pageTitle = 'Settings';
$pageSubtitle = 'Branding, email delivery, payments, and role access.';
$portal = 'admin';
$activeNav = 'settings';
require __DIR__ . '/../includes/header.php';
?>
<section class="settings-shell">
    <header class="settings-hero">
        <div>
            <h2>Company Settings</h2>
            <p>Configure branding, delivery, payments, and access for your legal workspace.</p>
        </div>
    </header>

    <nav class="settings-tabs" aria-label="Settings sections">
        <?php foreach ($tabs as $key => $label): ?>
            <a class="settings-tab <?= $tab === $key ? 'active' : '' ?>" href="?tab=<?= e($key) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="settings-body">
        <?php if ($tab === 'profile'): ?>
            <form method="post" class="settings-form">
                <?= csrf_field() ?>
                <input type="hidden" name="settings_tab" value="profile">
                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3>My profile</h3>
                        <p>Personal details for your admin account.</p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>First name</label><input name="first_name" required value="<?= e($user['first_name']) ?>"></div>
                        <div class="form-group"><label>Last name</label><input name="last_name" required value="<?= e($user['last_name']) ?>"></div>
                        <div class="form-group"><label>Email</label><input value="<?= e($user['email']) ?>" disabled></div>
                        <div class="form-group"><label>Username</label><input value="<?= e($user['username']) ?>" disabled></div>
                        <div class="form-group"><label>Phone</label><input name="phone" value="<?= e($user['phone'] ?? '') ?>"></div>
                        <div class="form-group"><label>New password</label><input type="password" name="password" placeholder="Leave blank to keep current"></div>
                        <div class="form-group full"><label>Address</label><textarea name="address"><?= e($user['address'] ?? '') ?></textarea></div>
                    </div>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit">Save profile</button></div>
            </form>

        <?php elseif ($tab === 'notifications'): ?>
            <form method="post" class="settings-form">
                <?= csrf_field() ?>
                <input type="hidden" name="settings_tab" value="notifications">
                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3>Notification preferences</h3>
                        <p>Choose which firm events generate alerts for administrators.</p>
                    </div>
                    <div class="settings-checks">
                        <?php
                        $prefs = [
                            'notify_appointments' => 'Appointment requests and schedule changes',
                            'notify_payments' => 'Payment received and overdue invoices',
                            'notify_cases' => 'New cases and status updates',
                            'notify_email_digest' => 'Daily email digest summary',
                        ];
                        foreach ($prefs as $key => $label):
                            $on = $get($key, '1') === '1';
                        ?>
                            <label class="settings-check">
                                <input type="checkbox" name="<?= e($key) ?>" value="1" <?= $on ? 'checked' : '' ?>>
                                <span><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit">Save preferences</button></div>
            </form>

        <?php elseif ($tab === 'branding'): ?>
            <form method="post" class="settings-form">
                <?= csrf_field() ?>
                <input type="hidden" name="settings_tab" value="branding">

                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3>Brand &amp; appearance</h3>
                        <p>Name, typography, and colors used across the admin, lawyer, and client portals.</p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>Company name</label><input name="company_name" value="<?= e($get('company_name', 'Lexora Legal Partners')) ?>"></div>
                        <div class="form-group">
                            <label>Font family</label>
                            <select name="branding_font">
                                <?php
                                $fonts = [
                                    'Montserrat' => 'Montserrat — modern geometric',
                                    'Plus Jakarta Sans' => 'Plus Jakarta Sans — soft UI',
                                    'Inter' => 'Inter — neutral UI',
                                ];
                                $font = $get('branding_font', 'Montserrat');
                                foreach ($fonts as $value => $label):
                                ?>
                                    <option value="<?= e($value) ?>" <?= $font === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Default theme</label>
                            <select name="theme">
                                <option value="light" <?= $get('theme', 'light') === 'light' ? 'selected' : '' ?>>Light</option>
                                <option value="dark" <?= $get('theme') === 'dark' ? 'selected' : '' ?>>Dark</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>AI assistant</label>
                            <select name="ai_enabled">
                                <option value="1" <?= $get('ai_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled</option>
                                <option value="0" <?= $get('ai_enabled') === '0' ? 'selected' : '' ?>>Disabled</option>
                            </select>
                        </div>
                        <div class="form-group full">
                            <label>Workspace URL</label>
                            <input value="<?= e($base) ?>" disabled>
                            <span class="field-hint">Portals: <code><?= e($base) ?>/admin</code>, <code><?= e($base) ?>/lawyer</code>, <code><?= e($base) ?>/client</code></span>
                        </div>
                    </div>

                    <div class="color-fields">
                        <?php
                        $colors = [
                            'branding_primary' => ['Primary Color', '#1e3a6e'],
                            'branding_secondary' => ['Secondary Color', '#002b5b'],
                            'branding_accent' => ['Accent Color', '#5b4b8a'],
                        ];
                        foreach ($colors as $key => [$label, $default]):
                            $val = $get($key, $default);
                        ?>
                            <label class="color-field">
                                <span><?= e($label) ?></span>
                                <span class="color-bar">
                                    <input type="color" name="<?= e($key) ?>" value="<?= e($val) ?>">
                                    <input type="text" value="<?= e($val) ?>" readonly>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3>Company information</h3>
                        <p>Legal identifiers and primary contact details for your firm.</p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>Company website</label><input name="company_website" value="<?= e($get('company_website')) ?>" placeholder="https://"></div>
                        <div class="form-group"><label>Company registration number</label><input name="company_registration" value="<?= e($get('company_registration')) ?>"></div>
                        <div class="form-group"><label>Company email</label><input name="company_email" value="<?= e($get('company_email')) ?>"></div>
                        <div class="form-group"><label>Company phone</label><input name="company_phone" value="<?= e($get('company_phone')) ?>"></div>
                        <div class="form-group full"><label>Company address</label><textarea name="company_address"><?= e($get('company_address')) ?></textarea></div>
                    </div>
                </div>

                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3>AI prompts</h3>
                        <p>System prompts used by the admin, lawyer, and client assistants.</p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group full"><label>Admin AI system prompt</label><textarea name="ai_welcome_admin"><?= e($get('ai_welcome_admin')) ?></textarea></div>
                        <div class="form-group full"><label>Lawyer AI system prompt</label><textarea name="ai_welcome_lawyer"><?= e($get('ai_welcome_lawyer')) ?></textarea></div>
                        <div class="form-group full"><label>Client AI system prompt</label><textarea name="ai_welcome_client"><?= e($get('ai_welcome_client')) ?></textarea></div>
                    </div>
                </div>

                <div class="form-actions"><button class="btn btn-primary" type="submit">Save branding</button></div>
            </form>

        <?php elseif ($tab === 'email'): ?>
            <form method="post" class="settings-form">
                <?= csrf_field() ?>
                <input type="hidden" name="settings_tab" value="email">
                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3>Email / SMTP</h3>
                        <p>Outgoing mail server used for notifications and digests.</p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>SMTP host</label><input name="smtp_host" value="<?= e($get('smtp_host')) ?>" placeholder="smtp.example.com"></div>
                        <div class="form-group"><label>SMTP port</label><input name="smtp_port" value="<?= e($get('smtp_port', '587')) ?>"></div>
                        <div class="form-group"><label>SMTP username</label><input name="smtp_user" value="<?= e($get('smtp_user')) ?>"></div>
                        <div class="form-group"><label>SMTP password</label><input type="password" name="smtp_pass" value="<?= e($get('smtp_pass')) ?>"></div>
                        <div class="form-group"><label>From address</label><input name="smtp_from" value="<?= e($get('smtp_from', $get('company_email'))) ?>"></div>
                        <div class="form-group">
                            <label>Encryption</label>
                            <select name="smtp_encryption">
                                <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= $get('smtp_encryption', 'tls') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit">Save SMTP</button></div>
            </form>

        <?php elseif ($tab === 'payments'): ?>
            <form method="post" class="settings-form">
                <?= csrf_field() ?>
                <input type="hidden" name="settings_tab" value="payments">
                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3>Payments</h3>
                        <p>Default currency and client-facing payment instructions.</p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Currency</label>
                            <select name="payment_currency">
                                <?php foreach (['INR', 'AED', 'USD', 'EUR', 'GBP', 'MUR'] as $currency): ?>
                                    <option value="<?= e($currency) ?>" <?= $get('payment_currency', 'INR') === $currency ? 'selected' : '' ?>><?= e($currency) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Accepted methods</label><input name="payment_methods" value="<?= e($get('payment_methods', 'Bank transfer, Card, Cheque')) ?>"></div>
                        <div class="form-group full"><label>Payment instructions</label><textarea name="payment_instructions"><?= e($get('payment_instructions', 'Please include the invoice number in the transfer reference.')) ?></textarea></div>
                    </div>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit">Save payments</button></div>
            </form>

        <?php elseif ($tab === 'roles'): ?>
            <div class="settings-block">
                <div class="settings-block-head">
                    <h3>Role access</h3>
                    <p>Portal permissions are role-based. Manage accounts under User Management.</p>
                </div>
                <div class="role-cards">
                    <div class="role-card"><strong>Admin</strong><span>Full firm settings, users, finance, and cases.</span></div>
                    <div class="role-card"><strong>Staff</strong><span>Operational access to admin tools without settings ownership.</span></div>
                    <div class="role-card"><strong>Lawyer</strong><span>Assigned cases, clients, court tracking, and availability.</span></div>
                    <div class="role-card"><strong>Client</strong><span>Own cases, documents, appointments, and payments.</span></div>
                </div>
                <div class="form-actions" style="margin-top:1rem;">
                    <a class="btn btn-primary" href="users.php">Open user management</a>
                </div>
            </div>

        <?php else: ?>
            <form method="post" class="settings-form">
                <?= csrf_field() ?>
                <input type="hidden" name="settings_tab" value="backup">
                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3>Backup</h3>
                        <p>Protect firm data by exporting the MySQL database regularly.</p>
                    </div>
                    <div class="list-stack">
                        <div class="list-item"><strong>Database</strong><span class="muted">Export <code>legal-system</code> from phpMyAdmin or mysqldump.</span></div>
                        <div class="list-item"><strong>Schema &amp; seed</strong><span class="muted">Source files live in <code>database/schema.sql</code> and <code>database/seed.sql</code>.</span></div>
                        <div class="list-item"><strong>Uploads</strong><span class="muted">Back up the <code>uploads/</code> folder for documents and avatars.</span></div>
                    </div>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit">Acknowledge backup checklist</button></div>
            </form>
        <?php endif; ?>
    </div>
</section>
<script>
document.querySelectorAll('.color-field input[type="color"]').forEach((picker) => {
  const text = picker.parentElement.querySelector('input[type="text"]');
  picker.addEventListener('input', () => { text.value = picker.value; });
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
