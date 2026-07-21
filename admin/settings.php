<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/role-access.php';
require_role(['admin']);
$pdo = db();
$user = current_user();

$tabs = [
    'profile' => 'settings.tab.profile',
    'notifications' => 'settings.tab.notifications',
    'branding' => 'settings.tab.branding',
    'ai' => 'settings.tab.ai',
    'email' => 'settings.tab.email',
    'payments' => 'settings.tab.payments',
    'roles' => 'settings.tab.roles',
    'backup' => 'settings.tab.backup',
];
$tab = get('tab', 'branding');
if (!isset($tabs[$tab])) {
    $tab = 'branding';
}

// Notification preferences matrix: category => [label key, default email on?]
$notifyCategories = [
    'cases' => ['label' => 'settings.notif.cat.cases', 'email' => false],
    'invoices' => ['label' => 'settings.notif.cat.invoices', 'email' => true],
    'payments' => ['label' => 'settings.notif.cat.payments', 'email' => true],
    'appointments' => ['label' => 'settings.notif.cat.appointments', 'email' => true],
    'documents' => ['label' => 'settings.notif.cat.documents', 'email' => false],
    'account' => ['label' => 'settings.notif.cat.account', 'email' => true],
    'system' => ['label' => 'settings.notif.cat.system', 'email' => false],
];

function backup_payload_build(PDO $pdo): array
{
    $settings = [];
    $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings ORDER BY setting_key');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
    }

    $count = static function (string $sql) use ($pdo): int {
        try {
            return (int) $pdo->query($sql)->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    };

    $usersByRole = [
        'admin' => 0,
        'staff' => 0,
        'lawyer' => 0,
        'client' => 0,
    ];
    try {
        $roleStmt = $pdo->query('SELECT role, COUNT(*) AS c FROM users GROUP BY role');
        while ($r = $roleStmt->fetch(PDO::FETCH_ASSOC)) {
            $role = (string) ($r['role'] ?? '');
            if ($role !== '') {
                $usersByRole[$role] = (int) ($r['c'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        // Ignore if table doesn't exist yet.
    }

    return [
        'meta' => [
            'generated_at' => gmdate('c'),
            'company' => (string) get_setting($pdo, 'company_name', app_config('name', 'LEGAL PRO')),
            'workspace_url' => (string) app_config('url', ''),
            'version' => 1,
        ],
        'counts' => [
            'users_total' => array_sum($usersByRole),
            'users_by_role' => $usersByRole,
            'cases' => $count('SELECT COUNT(*) FROM cases'),
            'invoices' => $count('SELECT COUNT(*) FROM invoices'),
            'payments' => $count('SELECT COUNT(*) FROM payments'),
            'appointments' => $count('SELECT COUNT(*) FROM appointments'),
            'notifications' => $count('SELECT COUNT(*) FROM notifications'),
        ],
        'settings' => $settings,
    ];
}

function backup_payload_json(PDO $pdo): string
{
    return json_encode(
        backup_payload_build($pdo),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?: '{}';
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
        flash('success', __('settings.profile.saved'));
    } elseif ($section === 'notifications') {
        foreach (array_keys($notifyCategories) as $cat) {
            set_setting($pdo, 'notify_' . $cat . '_inapp', post('notify_' . $cat . '_inapp', '0') === '1' ? '1' : '0');
            set_setting($pdo, 'notify_' . $cat . '_email', post('notify_' . $cat . '_email', '0') === '1' ? '1' : '0');
        }
        flash('success', __('settings.notifications.saved'));
    } elseif ($section === 'branding') {
        foreach ([
            'company_name', 'company_email', 'company_phone', 'company_address',
            'company_website', 'company_registration', 'company_vat', 'branding_font',
            'branding_accent', 'theme',
            'social_facebook', 'social_instagram', 'social_linkedin',
            'business_hours', 'company_description',
        ] as $key) {
            if (isset($_POST[$key])) {
                $value = post($key);
                if ($key === 'branding_accent') {
                    $value = strtolower(trim($value));
                    if (!preg_match('/^#[0-9a-f]{6}$/', $value)) {
                        $value = '#023e8a';
                    }
                }
                set_setting($pdo, $key, $value);
            }
        }
        try {
            if (post('remove_company_logo') === '1') {
                set_setting($pdo, 'company_logo', '');
            } else {
                $logoData = (string) ($_POST['company_logo_data'] ?? '');
                if ($logoData !== '') {
                    $logoPath = save_branding_data_url($logoData, 'logo');
                    if ($logoPath !== null) {
                        set_setting($pdo, 'company_logo', $logoPath);
                    }
                }
            }
            if (post('remove_company_favicon') === '1') {
                set_setting($pdo, 'company_favicon', '');
            } else {
                $faviconData = (string) ($_POST['company_favicon_data'] ?? '');
                if ($faviconData !== '') {
                    $faviconPath = save_branding_data_url($faviconData, 'favicon');
                    if ($faviconPath !== null) {
                        set_setting($pdo, 'company_favicon', $faviconPath);
                    }
                } elseif (!empty($_FILES['company_favicon']['name'])) {
                    $faviconPath = handle_branding_image_upload($_FILES['company_favicon'], 'favicon');
                    if ($faviconPath !== null) {
                        set_setting($pdo, 'company_favicon', $faviconPath);
                    }
                }
            }
        } catch (RuntimeException $ex) {
            flash('error', $ex->getMessage());
            redirect('settings.php?tab=branding');
        }
        if (isset($_POST['app_language'])) {
            $lang = strtolower((string) post('app_language', 'en'));
            if (!isset(supported_langs()[$lang])) {
                $lang = 'en';
            }
            set_setting($pdo, 'app_language', $lang);
            $_SESSION['lang'] = $lang;
            setcookie('lexora_lang', $lang, [
                'expires' => time() + 60 * 60 * 24 * 365,
                'path' => '/',
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
        flash('success', __('settings.branding.saved'));
    } elseif ($section === 'ai') {
        set_setting($pdo, 'ai_enabled', post('ai_enabled', '0') === '1' ? '1' : '0');

        $apiKey = trim((string) post('ai_api_key', ''));
        if ($apiKey !== '') {
            set_setting($pdo, 'ai_api_key', $apiKey);
        }

        foreach (['ai_model', 'ai_base_url'] as $key) {
            set_setting($pdo, $key, trim((string) post($key, '')));
        }

        $maxTokens = max(256, (int) post('ai_max_tokens', '4096'));
        set_setting($pdo, 'ai_max_tokens', (string) $maxTokens);

        $temperature = max(0.0, min(1.0, (float) post('ai_temperature', '0.3')));
        set_setting($pdo, 'ai_temperature', (string) $temperature);

        foreach (['ai_prompt_admin', 'ai_prompt_lawyer', 'ai_prompt_client'] as $key) {
            if (isset($_POST[$key])) {
                set_setting($pdo, $key, trim((string) post($key, '')));
            }
        }

        flash('success', __('settings.ai.saved'));
    } elseif ($section === 'email') {
        foreach (['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from', 'smtp_encryption'] as $key) {
            set_setting($pdo, $key, post($key));
        }
        flash('success', __('settings.email.saved'));
    } elseif ($section === 'payments') {
        foreach (['payment_currency', 'payment_instructions'] as $key) {
            set_setting($pdo, $key, post($key));
        }
        $methods = $_POST['payment_methods'] ?? [];
        if (!is_array($methods)) {
            $methods = [$methods];
        }
        $methods = array_values(array_filter(array_map(static fn($m): string => trim((string) $m), $methods), static fn(string $m): bool => $m !== ''));
        set_setting($pdo, 'payment_methods', implode(', ', $methods));
        $banks = [];
        for ($i = 1; $i <= 3; $i++) {
            $banks[$i] = [
                'label' => post('bank_' . $i . '_label'),
                'bank' => post('bank_' . $i . '_bank'),
                'account_name' => post('bank_' . $i . '_account_name'),
                'account_number' => post('bank_' . $i . '_account_number'),
                'sort_code' => post('bank_' . $i . '_sort_code'),
                'iban' => post('bank_' . $i . '_iban'),
                'swift' => post('bank_' . $i . '_swift'),
                'reference' => post('bank_' . $i . '_reference'),
            ];
        }
        save_bank_accounts($pdo, $banks);
        $defaultBank = (int) post('bank_accounts_default', '0');
        set_setting($pdo, 'bank_accounts_default', (string) ($defaultBank >= 1 && $defaultBank <= 3 ? $defaultBank : 0));
        flash('success', __('settings.payments.saved'));
    } elseif ($section === 'roles') {
        $roleAction = post('role_action', 'save');
        $config = role_access_load($pdo);
        $roleId = post('role_id', '');
        if ($roleAction === 'add') {
            $name = trim(post('role_name'));
            $description = trim(post('role_description'));
            $copyFrom = post('role_copy_from', 'staff');
            if ($name === '') {
                flash('error', __('settings.roles.error_name'));
            } else {
                $config = role_access_add_custom_role($config, $name, $description, $copyFrom);
                role_access_save($pdo, $config['roles'], $config['permissions']);
                flash('success', __('settings.roles.added'));
            }
        } elseif ($roleAction === 'delete' && $roleId !== '') {
            $config = role_access_delete_role($config, $roleId);
            role_access_save($pdo, $config['roles'], $config['permissions']);
            flash('success', __('settings.roles.deleted'));
        } elseif ($roleAction === 'copy' && $roleId !== '') {
            $config = role_access_duplicate_role($config, $roleId);
            role_access_save($pdo, $config['roles'], $config['permissions']);
            flash('success', __('settings.roles.copied'));
        } elseif (($roleAction === 'move_left' || $roleAction === 'move_right') && $roleId !== '') {
            $config = role_access_move_role($config, $roleId, $roleAction === 'move_left' ? 'left' : 'right');
            role_access_save($pdo, $config['roles'], $config['permissions']);
            flash('success', __('settings.roles.reordered'));
        } elseif ($roleAction === 'rename' && $roleId !== '') {
            $name = trim(post('role_rename', ''));
            $description = trim(post('role_rename_description', ''));
            if ($name === '') {
                flash('error', __('settings.roles.error_name'));
            } else {
                $config = role_access_rename_role($config, $roleId, $name, $description);
                role_access_save($pdo, $config['roles'], $config['permissions']);
                flash('success', __('settings.roles.renamed'));
            }
        } else {
            $config = role_access_parse_post($_POST, $config);
            role_access_save($pdo, $config['roles'], $config['permissions']);
            flash('success', __('settings.roles.saved'));
        }
    } elseif ($section === 'backup') {
        $action = post('backup_action', '');
        if ($action === 'download') {
            $json = backup_payload_json($pdo);
            $filename = 'website-backup-' . date('Y-m-d-His') . '.json';
            header('Content-Type: application/json; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($json));
            echo $json;
            exit;
        }
        if ($action === 'email') {
            $to = trim((string) get_setting($pdo, 'company_email', ''));
            if ($to === '') {
                flash('error', __('settings.backup.email_missing'));
            } else {
                $subject = __('settings.backup.email_subject') . ' · ' . date('Y-m-d H:i');
                $body = backup_payload_json($pdo);
                $ok = @mail($to, $subject, $body, "Content-Type: text/plain; charset=UTF-8\r\n");
                if ($ok) {
                    flash('success', __('settings.backup.emailed', ['email' => $to]));
                } else {
                    flash('error', __('settings.backup.email_failed'));
                }
            }
        } elseif ($action === 'schedule') {
            $frequency = strtolower(post('backup_frequency', 'never'));
            if (!in_array($frequency, ['never', 'weekly', 'monthly'], true)) {
                $frequency = 'never';
            }
            set_setting($pdo, 'backup_frequency', $frequency);
            flash('success', __('settings.backup.schedule_saved'));
        } elseif ($action === 'restore') {
            try {
                if (!isset($_FILES['backup_file']) || (int) $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException(__('settings.backup.restore_file_required'));
                }
                $raw = (string) file_get_contents((string) $_FILES['backup_file']['tmp_name']);
                $data = json_decode($raw, true);
                if (!is_array($data) || !is_array($data['settings'] ?? null)) {
                    throw new RuntimeException(__('settings.backup.restore_invalid'));
                }
                $applied = 0;
                foreach ($data['settings'] as $key => $value) {
                    $k = trim((string) $key);
                    if ($k === '') {
                        continue;
                    }
                    set_setting($pdo, $k, is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE));
                    $applied++;
                }
                flash('success', __('settings.backup.restore_done', ['count' => (string) $applied]));
            } catch (RuntimeException $ex) {
                flash('error', $ex->getMessage());
            }
        } else {
            flash('success', __('settings.backup.saved'));
        }
    }

    redirect('settings.php?tab=' . urlencode($section));
}

$get = fn($k, $d = '') => get_setting($pdo, $k, $d);
$user = current_user();
$base = app_config('url');

$pageTitle = __('page.settings');
$pageSubtitle = __('settings.subtitle');
$portal = 'admin';
$activeNav = 'settings';
require __DIR__ . '/../includes/header.php';
?>
<section class="settings-shell">
    <header class="settings-hero">
        <div>
            <h2><?= __e('settings.hero_title') ?></h2>
            <p><?= __e('settings.hero_sub') ?></p>
        </div>
    </header>

    <nav class="settings-tabs" aria-label="<?= __e('page.settings') ?>">
        <?php foreach ($tabs as $key => $labelKey): ?>
            <a class="settings-tab <?= $tab === $key ? 'active' : '' ?>" href="?tab=<?= e($key) ?>"><?= __e($labelKey) ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="settings-body">
        <?php if ($tab === 'profile'): ?>
            <form method="post" class="settings-form">
                <?= csrf_field() ?>
                <input type="hidden" name="settings_tab" value="profile">
                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.profile.title') ?></h3>
                        <p><?= __e('settings.profile.help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group"><label><?= __e('form.first_name') ?></label><input name="first_name" required value="<?= e($user['first_name']) ?>"></div>
                            <div class="form-group"><label><?= __e('form.last_name') ?></label><input name="last_name" required value="<?= e($user['last_name']) ?>"></div>
                        </div>
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group"><label><?= __e('common.email') ?></label><input value="<?= e($user['email']) ?>" disabled></div>
                            <div class="form-group"><label><?= __e('form.username') ?></label><input value="<?= e($user['username']) ?>" disabled></div>
                        </div>
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group"><label><?= __e('common.phone') ?></label><input name="phone" value="<?= e($user['phone'] ?? '') ?>"></div>
                            <div class="form-group"><label><?= __e('form.new_password') ?></label><input type="password" name="password" placeholder="<?= __e('form.password_keep') ?>"><span class="field-hint"><?= __e('form.hint.password_keep') ?></span></div>
                        </div>
                        <div class="form-group full"><label><?= __e('common.address') ?></label><textarea name="address" rows="2"><?= e($user['address'] ?? '') ?></textarea></div>
                    </div>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><?= __e('common.save_profile') ?></button></div>
            </form>

        <?php elseif ($tab === 'notifications'): ?>
            <form method="post" class="settings-form">
                <?= csrf_field() ?>
                <input type="hidden" name="settings_tab" value="notifications">
                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.notifications.title') ?></h3>
                        <p><?= __e('settings.notifications.help') ?></p>
                    </div>
                    <?php
                    $notifIcons = [
                        'cases' => '<path d="M4 7h16v12H4z"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
                        'invoices' => '<path d="M6 3h9l3 3v15l-2-1-2 1-2-1-2 1-2-1-2 1V3z"/><path d="M9 8h6M9 12h6M9 16h4"/>',
                        'payments' => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/>',
                        'appointments' => '<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/>',
                        'documents' => '<path d="M7 3h7l4 4v14H7z"/><path d="M14 3v4h4"/>',
                        'account' => '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/><path d="M5 20a7 7 0 0 1 14 0"/>',
                        'system' => '<path d="M12 4a5 5 0 0 0-5 5v3l-2 3h14l-2-3V9a5 5 0 0 0-5-5z"/><path d="M10 19a2 2 0 0 0 4 0"/>',
                    ];
                    ?>
                    <div class="table-wrap">
                        <table class="settings-matrix">
                            <thead>
                                <tr>
                                    <th><?= __e('settings.notif.category') ?></th>
                                    <th class="settings-matrix-col"><?= __e('settings.notif.inapp') ?></th>
                                    <th class="settings-matrix-col"><?= __e('settings.notif.email') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifyCategories as $cat => $meta):
                                    $catLabel = __($meta['label']);
                                    $inapp = $get('notify_' . $cat . '_inapp', '1') === '1';
                                    $email = $get('notify_' . $cat . '_email', $meta['email'] ? '1' : '0') === '1';
                                ?>
                                <tr>
                                    <td>
                                        <div class="settings-matrix-cat">
                                            <span class="settings-matrix-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?= $notifIcons[$cat] ?></svg></span>
                                            <span><?= e($catLabel) ?></span>
                                        </div>
                                    </td>
                                    <td class="settings-matrix-check">
                                        <input type="checkbox" name="notify_<?= e($cat) ?>_inapp" value="1" <?= $inapp ? 'checked' : '' ?> aria-label="<?= e($catLabel . ' · ' . __('settings.notif.inapp')) ?>">
                                    </td>
                                    <td class="settings-matrix-check">
                                        <input type="checkbox" name="notify_<?= e($cat) ?>_email" value="1" <?= $email ? 'checked' : '' ?> aria-label="<?= e($catLabel . ' · ' . __('settings.notif.email')) ?>">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="field-hint settings-matrix-note"><?= __e('settings.notif.smtp_note') ?></p>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><?= __e('settings.notifications.save') ?></button></div>
            </form>

        <?php elseif ($tab === 'branding'): ?>
            <form method="post" class="settings-form" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="settings_tab" value="branding">

                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.branding.title') ?></h3>
                        <p><?= __e('settings.branding.company_info') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row">
                            <div class="form-group"><label><?= __e('settings.branding.company_name') ?></label><input name="company_name" value="<?= e($get('company_name', 'LEGAL PRO')) ?>"></div>
                            <div class="form-group"><label><?= __e('settings.branding.workspace_url') ?></label><input value="<?= e($base) ?>" disabled></div>
                            <div class="form-group">
                                <label><?= __e('settings.branding.created_by') ?></label>
                                <input value="Company Automator" disabled readonly>
                                <span class="field-hint"><?= __e('settings.branding.created_by_help') ?></span>
                            </div>
                        </div>
                        <div class="entity-field-row">
                            <div class="form-group">
                                <label><?= __e('settings.branding.font') ?></label>
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
                                <label><?= __e('settings.branding.theme') ?></label>
                                <select name="theme">
                                    <option value="light" <?= $get('theme', 'light') === 'light' ? 'selected' : '' ?>><?= __e('theme.light') ?></option>
                                    <option value="dark" <?= $get('theme') === 'dark' ? 'selected' : '' ?>><?= __e('theme.dark') ?></option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><?= __e('settings.language.default') ?></label>
                                <select name="app_language">
                                    <?php foreach (supported_langs() as $code => $label): ?>
                                        <option value="<?= e($code) ?>" <?= $get('app_language', 'en') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group full entity-field-notes"><span class="field-hint">Portals: <code><?= e($base) ?>/admin</code>, <code><?= e($base) ?>/lawyer</code>, <code><?= e($base) ?>/client</code></span></div>
                    </div>

                    <div class="accent-palette-block">
                        <div class="settings-block-head" style="margin-bottom:0.85rem;">
                            <h3><?= __e('settings.branding.accent') ?></h3>
                            <p><?= __e('settings.branding.pick_colour') ?></p>
                        </div>
                        <?php
                        $accentPalettes = [
                            '#023e8a' => ['Classic Navy', 'Trusted legal standard'],
                            '#2d3748' => ['Executive Charcoal', 'Refined neutral tone'],
                            '#0d5c63' => ['Professional Teal', 'Calm and contemporary'],
                            '#1e4d3b' => ['Heritage Forest', 'Established and trustworthy'],
                            '#475569' => ['Corporate Slate', 'Modern business gray'],
                            '#5c2e37' => ['Deep Burgundy', 'Distinguished executive accent'],
                        ];
                        $currentAccent = strtolower($get('branding_accent', '#023e8a'));
                        if (!preg_match('/^#[0-9a-f]{6}$/', $currentAccent)) {
                            $currentAccent = '#023e8a';
                        }
                        $isCustom = !isset($accentPalettes[$currentAccent]);
                        $customHex = $isCustom ? $currentAccent : '#2563eb';
                        $darken = static function (string $hex, int $amt = 40): string {
                            $r = max(0, hexdec(substr($hex, 1, 2)) - $amt);
                            $g = max(0, hexdec(substr($hex, 3, 2)) - $amt);
                            $b = max(0, hexdec(substr($hex, 5, 2)) - $amt);
                            return sprintf('#%02x%02x%02x', $r, $g, $b);
                        };
                        ?>
                        <div class="accent-palette" id="accent-palette">
                            <?php foreach ($accentPalettes as $hex => [$label, $desc]):
                                $grad = 'linear-gradient(135deg, ' . $hex . ' 0%, ' . $darken($hex) . ' 100%)';
                            ?>
                                <label class="accent-swatch">
                                    <input type="radio" name="branding_accent_choice" value="<?= e($hex) ?>" <?= $currentAccent === $hex ? 'checked' : '' ?>>
                                    <span class="accent-swatch-tone" style="background:<?= e($grad) ?>"></span>
                                    <span class="accent-swatch-meta">
                                        <strong><?= e($label) ?></strong>
                                        <small><?= e($desc) ?></small>
                                    </span>
                                    <span class="accent-swatch-check" aria-hidden="true">✓</span>
                                </label>
                            <?php endforeach; ?>
                            <label class="accent-swatch accent-swatch-custom">
                                <input type="radio" name="branding_accent_choice" value="custom" <?= $isCustom ? 'checked' : '' ?>>
                                <span class="accent-swatch-tone accent-swatch-tone-custom" id="accent-custom-stripe" style="background:linear-gradient(135deg, <?= e($customHex) ?> 0%, <?= e($darken($customHex)) ?> 100%)"></span>
                                <span class="accent-swatch-meta">
                                    <strong><?= __e('settings.branding.your_colour') ?></strong>
                                    <small><?= __e('settings.branding.custom_colour') ?></small>
                                </span>
                                <span class="accent-swatch-check" aria-hidden="true">✓</span>
                            </label>
                        </div>
                        <div class="accent-custom-panel" id="accent-custom-panel" <?= $isCustom ? '' : 'hidden' ?>>
                            <label for="branding_accent_custom"><?= __e('settings.branding.custom_colour') ?></label>
                            <div class="accent-custom-controls">
                                <input type="color" id="branding_accent_custom" value="<?= e($customHex) ?>" title="<?= __e('settings.branding.pick_colour') ?>">
                                <input type="text" id="branding_accent_hex" value="<?= e(strtoupper($customHex)) ?>" maxlength="7" spellcheck="false" autocomplete="off" placeholder="#023E8A">
                                <span class="field-hint">Use any hex colour for buttons, dashboards, and login.</span>
                            </div>
                        </div>
                        <input type="hidden" name="branding_accent" id="branding_accent" value="<?= e($currentAccent) ?>">
                    </div>
                </div>

                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.branding.company_info') ?></h3>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row">
                            <div class="form-group"><label><?= __e('settings.branding.website') ?></label><input name="company_website" value="<?= e($get('company_website')) ?>" placeholder="https://"></div>
                            <div class="form-group"><label><?= __e('settings.branding.registration') ?></label><input name="company_registration" value="<?= e($get('company_registration')) ?>"></div>
                            <div class="form-group"><label><?= __e('settings.branding.vat_number') ?></label><input name="company_vat" value="<?= e($get('company_vat')) ?>" placeholder="e.g. 290 4002 07"></div>
                        </div>
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group"><label><?= __e('common.email') ?></label><input name="company_email" value="<?= e($get('company_email')) ?>"></div>
                            <div class="form-group"><label><?= __e('common.phone') ?></label><input name="company_phone" value="<?= e($get('company_phone')) ?>"></div>
                        </div>
                        <div class="form-group full"><label><?= __e('common.address') ?></label><textarea name="company_address"><?= e($get('company_address')) ?></textarea></div>
                    </div>
                </div>

                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.branding.social_media') ?></h3>
                        <p><?= __e('settings.branding.social_media_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row">
                            <div class="form-group"><label><?= __e('settings.branding.facebook') ?></label><input name="social_facebook" value="<?= e($get('social_facebook')) ?>" placeholder="https://facebook.com/..."></div>
                            <div class="form-group"><label><?= __e('settings.branding.instagram') ?></label><input name="social_instagram" value="<?= e($get('social_instagram')) ?>" placeholder="https://instagram.com/..."></div>
                            <div class="form-group"><label><?= __e('settings.branding.linkedin') ?></label><input name="social_linkedin" value="<?= e($get('social_linkedin')) ?>" placeholder="https://linkedin.com/company/..."></div>
                        </div>
                    </div>
                </div>

                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.branding.office_desc') ?></h3>
                        <p><?= __e('settings.branding.office_desc_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group full"><label><?= __e('settings.branding.business_hours') ?></label><textarea name="business_hours" rows="2" placeholder="Monday – Friday: 9:00 AM – 5:00 PM"><?= e($get('business_hours')) ?></textarea></div>
                        <div class="form-group full"><label><?= __e('settings.branding.description') ?></label><textarea name="company_description" rows="3"><?= e($get('company_description')) ?></textarea></div>
                    </div>
                </div>

                <?php
                $companyLogo = $get('company_logo');
                $companyFavicon = $get('company_favicon');
                $logoUrl = $companyLogo ? $base . '/' . ltrim($companyLogo, '/') : '';
                $faviconUrl = $companyFavicon ? $base . '/' . ltrim($companyFavicon, '/') : '';
                $brandPreviewName = $get('company_name', 'LEGAL PRO');
                ?>
                <div class="settings-block" id="logoFaviconBlock" data-logo-url="<?= e($logoUrl) ?>" data-favicon-url="<?= e($faviconUrl) ?>">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.branding.logo_favicon') ?></h3>
                        <p><?= __e('settings.branding.logo_favicon_help') ?></p>
                    </div>

                    <div class="form-group full">
                        <label><?= __e('settings.branding.company_logo') ?></label>
                        <div class="logo-upload-row">
                            <label class="logo-file-btn">
                                <input type="file" id="logoFileInput" accept="image/png,image/jpeg,image/svg+xml,image/webp,image/gif">
                                <span class="logo-file-btn-label"><?= __e('settings.branding.choose_file') ?></span>
                            </label>
                            <span class="logo-file-name" id="logoFileName" data-empty="<?= __e('settings.branding.no_file_chosen') ?>"><?= __e('settings.branding.no_file_chosen') ?></span>
                            <button type="button" class="btn-row-edit btn-sm" id="logoEditBtn" <?= $companyLogo ? '' : 'hidden' ?>><?= __e('settings.branding.edit_logo') ?></button>
                            <button type="button" class="btn-row-delete btn-sm" id="logoRemoveBtn" <?= $companyLogo ? '' : 'hidden' ?>><?= __e('settings.branding.remove_logo') ?></button>
                        </div>
                    </div>

                    <div class="logo-appears" id="logoAppears" <?= $companyLogo ? '' : 'hidden' ?>>
                        <div class="logo-appears-title"><?= __e('settings.branding.where_logo_appears') ?></div>
                        <div class="logo-appears-grid">
                            <div class="logo-appears-card">
                                <div class="logo-appears-label"><?= __e('settings.branding.preview_sidebar') ?></div>
                                <div class="logo-appears-sidebar">
                                    <span class="lap-mark"><img id="prevSidebar" src="<?= e($logoUrl) ?>" alt=""></span>
                                    <div class="lap-text"><strong><?= e(strtoupper($brandPreviewName)) ?></strong><span><?= __e('role.admin') ?></span></div>
                                </div>
                                <div class="logo-appears-dim">39 × 38 px</div>
                            </div>
                            <div class="logo-appears-card">
                                <div class="logo-appears-label"><?= __e('settings.branding.preview_login') ?></div>
                                <div class="logo-appears-login">
                                    <span class="lap-login-mark"><img id="prevLogin" src="<?= e($logoUrl) ?>" alt=""></span>
                                </div>
                                <div class="logo-appears-dim">64 × 64 px</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group full" style="margin-top:1rem;">
                        <label><?= __e('settings.branding.favicon') ?></label>
                        <div class="logo-upload-row">
                            <label class="logo-file-btn">
                                <input type="file" id="faviconFileInput" accept="image/png,image/jpeg,image/svg+xml,image/webp">
                                <span class="logo-file-btn-label"><?= __e('settings.branding.choose_file') ?></span>
                            </label>
                            <span class="logo-file-name" id="faviconFileName" data-empty="<?= __e('settings.branding.no_file_chosen') ?>"><?= __e('settings.branding.no_file_chosen') ?></span>
                            <button type="button" class="btn-row-edit btn-sm" id="faviconEditBtn" <?= $companyFavicon ? '' : 'hidden' ?>><?= __e('settings.branding.edit_logo') ?></button>
                            <button type="button" class="btn-row-delete btn-sm" id="faviconRemoveBtn" <?= $companyFavicon ? '' : 'hidden' ?>><?= __e('settings.branding.remove_logo') ?></button>
                        </div>
                        <div class="favicon-note" id="faviconNote">
                            <?php if ($companyFavicon): ?>
                                <img src="<?= e($faviconUrl) ?>" alt="" class="favicon-note-img">
                            <?php else: ?>
                                <span class="muted"><?= __e('settings.branding.no_favicon') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <input type="hidden" name="company_logo_data" id="companyLogoData">
                    <input type="hidden" name="company_favicon_data" id="companyFaviconData">
                    <input type="hidden" name="remove_company_logo" id="removeCompanyLogo" value="0">
                    <input type="hidden" name="remove_company_favicon" id="removeCompanyFavicon" value="0">
                </div>

                <div class="form-actions"><button class="btn btn-primary" type="submit"><?= __e('settings.branding.save') ?></button></div>
            </form>

            <div class="logo-crop-modal" id="logoCropModal" hidden>
                <div class="logo-crop-backdrop" data-close></div>
                <div class="logo-crop-dialog" role="dialog" aria-modal="true" aria-label="<?= __e('settings.branding.adjust_logo') ?>">
                    <div class="logo-crop-head">
                        <h3 id="logoCropTitle" data-logo="<?= __e('settings.branding.adjust_logo') ?>" data-favicon="<?= __e('settings.branding.adjust_favicon') ?>"><?= __e('settings.branding.adjust_logo') ?></h3>
                        <button type="button" class="logo-crop-close" data-close aria-label="<?= __e('common.close') ?>">×</button>
                    </div>
                    <div class="logo-crop-tabs">
                        <button type="button" class="logo-crop-tab is-active" data-mode="square"><?= __e('settings.branding.crop_square') ?></button>
                        <button type="button" class="logo-crop-tab" data-mode="free"><?= __e('settings.branding.crop_free') ?></button>
                    </div>
                    <div class="logo-crop-body">
                        <div class="logo-crop-stage" id="logoCropStage">
                            <img id="logoCropImg" alt="" draggable="false">
                            <div class="logo-crop-frame" id="logoCropFrame">
                                <span class="lcf-handle lcf-nw" data-handle="nw"></span>
                                <span class="lcf-handle lcf-ne" data-handle="ne"></span>
                                <span class="lcf-handle lcf-sw" data-handle="sw"></span>
                                <span class="lcf-handle lcf-se" data-handle="se"></span>
                            </div>
                        </div>
                        <div class="logo-crop-side">
                            <div class="logo-crop-preview-title"><?= __e('settings.branding.live_preview') ?></div>
                            <div class="lcp-sidebar">
                                <span class="lcp-mark"><img id="lcpSidebar" alt=""></span>
                                <div class="lcp-text"><strong><?= e(strtoupper($brandPreviewName)) ?></strong><span><?= __e('settings.branding.preview_sidebar') ?></span></div>
                            </div>
                            <div class="lcp-login">
                                <span class="lcp-login-mark"><img id="lcpLogin" alt=""></span>
                                <div class="lcp-login-text"><strong><?= e(strtoupper($brandPreviewName)) ?></strong></div>
                            </div>
                        </div>
                    </div>
                    <p class="logo-crop-hint"><?= __e('settings.branding.crop_hint') ?></p>
                    <div class="logo-crop-foot">
                        <button type="button" class="btn btn-secondary" data-close><?= __e('common.cancel') ?></button>
                        <button type="button" class="btn btn-primary" id="logoCropApply"><?= __e('settings.branding.apply_logo') ?></button>
                    </div>
                </div>
            </div>
            <script src="<?= e($base) ?>/assets/js/logo-cropper.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/logo-cropper.js') ?>"></script>

        <?php elseif ($tab === 'ai'): ?>
            <?php
            $aiKeyStored = trim((string) $get('ai_api_key', app_config('ai_api_key', '')));
            $aiKeyPreview = $aiKeyStored !== '' ? str_repeat('•', min(20, max(8, strlen($aiKeyStored)))) : '';
            ?>
            <form method="post" class="settings-form">
                <?= csrf_field() ?>
                <input type="hidden" name="settings_tab" value="ai">
                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.ai.title') ?></h3>
                        <p><?= __e('settings.ai.help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group full">
                            <label class="checkbox-label">
                                <input type="checkbox" name="ai_enabled" value="1" <?= $get('ai_enabled', '1') === '1' ? 'checked' : '' ?>>
                                <?= __e('settings.ai.enabled') ?>
                            </label>
                            <span class="field-hint"><?= __e('settings.ai.enabled_help') ?></span>
                        </div>
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group">
                                <label for="ai_api_key"><?= __e('settings.ai.api_key') ?></label>
                                <input type="password" id="ai_api_key" name="ai_api_key" value="" placeholder="<?= $aiKeyPreview !== '' ? e($aiKeyPreview) : 'sk-...' ?>" autocomplete="new-password">
                                <span class="field-hint"><?= __e('settings.ai.api_key_help') ?></span>
                            </div>
                            <div class="form-group">
                                <label for="ai_model"><?= __e('settings.ai.model') ?></label>
                                <input id="ai_model" name="ai_model" value="<?= e($get('ai_model', app_config('ai_model', 'gpt-4o-mini'))) ?>" placeholder="gpt-4o-mini">
                                <span class="field-hint"><?= __e('settings.ai.model_help') ?></span>
                            </div>
                        </div>
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group">
                                <label for="ai_base_url"><?= __e('settings.ai.base_url') ?></label>
                                <input id="ai_base_url" name="ai_base_url" value="<?= e($get('ai_base_url', app_config('ai_base_url', 'https://api.openai.com/v1'))) ?>" placeholder="https://api.openai.com/v1">
                                <span class="field-hint"><?= __e('settings.ai.base_url_help') ?></span>
                            </div>
                            <div class="form-group">
                                <label for="ai_max_tokens"><?= __e('settings.ai.max_tokens') ?></label>
                                <input id="ai_max_tokens" name="ai_max_tokens" type="number" min="256" max="16384" value="<?= e($get('ai_max_tokens', (string) app_config('ai_max_tokens', 4096))) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="ai_temperature"><?= __e('settings.ai.temperature') ?></label>
                            <input id="ai_temperature" name="ai_temperature" type="number" min="0" max="1" step="0.1" value="<?= e($get('ai_temperature', (string) app_config('ai_temperature', 0.3))) ?>">
                            <span class="field-hint"><?= __e('settings.ai.temperature_help') ?></span>
                        </div>
                    </div>
                </div>

                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.branding.ai_prompts') ?></h3>
                        <p><?= __e('settings.ai.prompts_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="ai_prompt_admin"><?= __e('settings.branding.ai_prompt_admin') ?></label>
                            <textarea id="ai_prompt_admin" name="ai_prompt_admin" rows="3" placeholder="<?= e(__('ai.system.admin')) ?>"><?= e($get('ai_prompt_admin')) ?></textarea>
                        </div>
                        <div class="form-group full">
                            <label for="ai_prompt_lawyer"><?= __e('settings.branding.ai_prompt_lawyer') ?></label>
                            <textarea id="ai_prompt_lawyer" name="ai_prompt_lawyer" rows="3" placeholder="<?= e(__('ai.system.lawyer')) ?>"><?= e($get('ai_prompt_lawyer')) ?></textarea>
                        </div>
                        <div class="form-group full">
                            <label for="ai_prompt_client"><?= __e('settings.branding.ai_prompt_client') ?></label>
                            <textarea id="ai_prompt_client" name="ai_prompt_client" rows="3" placeholder="<?= e(__('ai.system.client')) ?>"><?= e($get('ai_prompt_client')) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-actions"><button class="btn btn-primary" type="submit"><?= __e('settings.ai.save') ?></button></div>
            </form>

        <?php elseif ($tab === 'email'): ?>
            <form method="post" class="settings-form">
                <?= csrf_field() ?>
                <input type="hidden" name="settings_tab" value="email">
                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.email.title') ?></h3>
                        <p><?= __e('settings.email.help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row">
                            <div class="form-group"><label><?= __e('settings.email.host') ?></label><input name="smtp_host" value="<?= e($get('smtp_host')) ?>" placeholder="smtp.example.com"></div>
                            <div class="form-group"><label><?= __e('settings.email.port') ?></label><input name="smtp_port" value="<?= e($get('smtp_port', '587')) ?>"></div>
                            <div class="form-group">
                                <label><?= __e('settings.email.encryption') ?></label>
                                <select name="smtp_encryption">
                                    <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => __('content.none')] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= $get('smtp_encryption', 'tls') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group"><label><?= __e('settings.email.user') ?></label><input name="smtp_user" value="<?= e($get('smtp_user')) ?>"></div>
                            <div class="form-group"><label><?= __e('settings.email.pass') ?></label><input type="password" name="smtp_pass" value="<?= e($get('smtp_pass')) ?>"></div>
                        </div>
                        <div class="form-group full"><label><?= __e('settings.email.from') ?></label><input name="smtp_from" value="<?= e($get('smtp_from', $get('company_email'))) ?>"></div>
                    </div>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><?= __e('settings.email.save') ?></button></div>
            </form>

        <?php elseif ($tab === 'payments'): ?>
            <form method="post" class="settings-form">
                <?= csrf_field() ?>
                <input type="hidden" name="settings_tab" value="payments">
                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.payments.title') ?></h3>
                        <p><?= __e('settings.payments.help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group">
                                <label><?= __e('common.currency') ?></label>
                                <select name="payment_currency">
                                    <?php foreach (['MUR' => 'content.currency.mur', 'INR' => 'content.currency.inr', 'AED' => 'content.currency.aed', 'USD' => 'content.currency.usd', 'EUR' => 'content.currency.eur', 'GBP' => 'content.currency.gbp'] as $currency => $labelKey): ?>
                                        <option value="<?= e($currency) ?>" <?= $get('payment_currency', 'MUR') === $currency ? 'selected' : '' ?>><?= e(__($labelKey)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><?= __e('settings.payments.methods') ?></label>
                                <?php
                                $methodOptions = [
                                    'Bank transfer' => __('settings.payments.method.bank_transfer'),
                                    'Card' => __('settings.payments.method.card'),
                                    'Cheque' => __('settings.payments.method.cheque'),
                                    'Online payment' => __('settings.payments.method.online'),
                                    'Cash' => __('settings.payments.method.cash'),
                                ];
                                $selectedMethods = array_filter(array_map('trim', explode(',', (string) $get('payment_methods', __('content.settings.payment_methods')))));
                                $selectedMethodsLc = array_map('strtolower', $selectedMethods);
                                $selectedLabels = [];
                                foreach ($methodOptions as $mv => $ml) {
                                    if (in_array(strtolower($mv), $selectedMethodsLc, true)) {
                                        $selectedLabels[] = $ml;
                                    }
                                }
                                ?>
                                <details class="ms-dropdown" id="paymentMethodsDropdown">
                                    <summary class="ms-dropdown-toggle">
                                        <span class="ms-dropdown-value" data-placeholder="<?= __e('settings.payments.methods_placeholder') ?>"><?= $selectedLabels ? e(implode(', ', $selectedLabels)) : '<span class="ms-dropdown-placeholder">' . __e('settings.payments.methods_placeholder') . '</span>' ?></span>
                                        <svg class="ms-dropdown-caret" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                                    </summary>
                                    <div class="ms-dropdown-panel">
                                        <?php foreach ($methodOptions as $mv => $ml): ?>
                                            <label class="ms-dropdown-option">
                                                <input type="checkbox" name="payment_methods[]" value="<?= e($mv) ?>" <?= in_array(strtolower($mv), $selectedMethodsLc, true) ? 'checked' : '' ?>>
                                                <span><?= e($ml) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            </div>
                        </div>
                        <div class="form-group full"><label><?= __e('settings.payments.instructions') ?></label><textarea name="payment_instructions"><?= e($get('payment_instructions', __('content.settings.payment_instructions'))) ?></textarea></div>
                    </div>
                </div>
                <?php
                $bankAccounts = get_bank_accounts($pdo);
                $defaultBankId = (int) $get('bank_accounts_default', '0');
                $fieldIcon = static function (string $name): string {
                    $paths = [
                        'label' => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0l-7-7A2 2 0 0 1 3 12.2V4a1 1 0 0 1 1-1h8.2a2 2 0 0 1 1.4.6l7 7a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.2"/>',
                        'bank' => '<path d="M3 10l9-6 9 6"/><path d="M5 10v8M19 10v8M9 10v8M15 10v8M3 21h18"/>',
                        'person' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c1-3.5 4-5 7-5s6 1.5 7 5"/>',
                        'card' => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/>',
                        'hash' => '<path d="M10 4L8 20M16 4l-2 16M4 9h16M4 15h16"/>',
                        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.6 2.6 2.6 15.4 0 18M12 3c-2.6 2.6-2.6 15.4 0 18"/>',
                        'shield' => '<path d="M12 3l7 3v5c0 4.6-3 8.1-7 10-4-1.9-7-5.4-7-10V6l7-3z"/>',
                        'bookmark' => '<path d="M6 3h12a1 1 0 0 1 1 1v17l-7-4-7 4V4a1 1 0 0 1 1-1z"/>',
                        'star' => '<path d="M12 3l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 17.8 6.8 19.2l1-5.8-4.3-4.1 5.9-.9L12 3z"/>',
                    ];
                    $p = $paths[$name] ?? $paths['label'];
                    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
                };
                ?>
                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.payments.bank_accounts') ?></h3>
                        <p><?= __e('settings.payments.bank_accounts_help') ?></p>
                    </div>
                    <div class="bank-accounts-grid">
                        <?php foreach ($bankAccounts as $n => $ba): ?>
                            <div class="bank-account-card">
                                <h4><?= __e('settings.payments.bank_account_n', ['n' => (string) $n]) ?></h4>
                                <div class="form-grid">
                                    <div class="entity-field-row entity-field-row--2">
                                        <div class="form-group"><label><?= __e('settings.payments.bank_label') ?></label><div class="field-icon"><span class="field-icon-symbol"><?= $fieldIcon('label') ?></span><input name="bank_<?= (int) $n ?>_label" value="<?= e($ba['label']) ?>" placeholder="e.g. Main AED account"></div></div>
                                        <div class="form-group"><label><?= __e('settings.payments.bank_name') ?></label><div class="field-icon"><span class="field-icon-symbol"><?= $fieldIcon('bank') ?></span><input name="bank_<?= (int) $n ?>_bank" value="<?= e($ba['bank']) ?>" placeholder="e.g. Emirates NBD"></div></div>
                                    </div>
                                    <div class="entity-field-row entity-field-row--2">
                                        <div class="form-group"><label><?= __e('settings.payments.account_name') ?></label><div class="field-icon"><span class="field-icon-symbol"><?= $fieldIcon('person') ?></span><input name="bank_<?= (int) $n ?>_account_name" value="<?= e($ba['account_name']) ?>"></div></div>
                                        <div class="form-group"><label><?= __e('settings.payments.account_number') ?></label><div class="field-icon"><span class="field-icon-symbol"><?= $fieldIcon('card') ?></span><input name="bank_<?= (int) $n ?>_account_number" value="<?= e($ba['account_number']) ?>"></div></div>
                                    </div>
                                    <div class="entity-field-row entity-field-row--2">
                                        <div class="form-group"><label><?= __e('settings.payments.sort_code') ?></label><div class="field-icon"><span class="field-icon-symbol"><?= $fieldIcon('hash') ?></span><input name="bank_<?= (int) $n ?>_sort_code" value="<?= e($ba['sort_code']) ?>" placeholder="e.g. 20-00-00"></div></div>
                                        <div class="form-group"><label><?= __e('settings.payments.iban') ?></label><div class="field-icon"><span class="field-icon-symbol"><?= $fieldIcon('globe') ?></span><input name="bank_<?= (int) $n ?>_iban" value="<?= e($ba['iban']) ?>"></div></div>
                                    </div>
                                    <div class="entity-field-row entity-field-row--2">
                                        <div class="form-group"><label><?= __e('settings.payments.swift') ?></label><div class="field-icon"><span class="field-icon-symbol"><?= $fieldIcon('shield') ?></span><input name="bank_<?= (int) $n ?>_swift" value="<?= e($ba['swift']) ?>"></div></div>
                                        <div class="form-group"><label><?= __e('settings.payments.reference') ?></label><div class="field-icon"><span class="field-icon-symbol"><?= $fieldIcon('bookmark') ?></span><input name="bank_<?= (int) $n ?>_reference" value="<?= e($ba['reference']) ?>" placeholder="<?= __e('settings.payments.reference_placeholder') ?>"></div></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="bank-default-card">
                        <div class="bank-default-head">
                            <span class="bank-default-icon" aria-hidden="true"><?= $fieldIcon('star') ?></span>
                            <div class="bank-default-body">
                                <label for="bank_accounts_default"><?= __e('settings.payments.default_account') ?></label>
                                <p class="muted"><?= __e('settings.payments.default_account_help') ?></p>
                            </div>
                        </div>
                        <select name="bank_accounts_default" id="bank_accounts_default">
                            <?php foreach ($bankAccounts as $n => $ba): ?>
                                <option value="<?= (int) $n ?>" <?= $defaultBankId === (int) $n ? 'selected' : '' ?>><?= e($ba['label'] !== '' ? $ba['label'] : __('settings.payments.bank_account_n', ['n' => (string) $n])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><?= __e('settings.save_payments') ?></button></div>
            </form>

        <?php elseif ($tab === 'roles'):
            $roleConfig = role_access_load($pdo);
            $roleList = $roleConfig['roles'];
            $rolePerms = $roleConfig['permissions'];
            $roleModules = role_access_modules();
            $roleScopes = role_access_scope_keys();
            $roleUserCounts = role_access_user_counts($pdo);
            $companyName = $get('company_name', app_config('name', 'LEGAL PRO'));
            ?>
            <div class="role-access-page">
                <div class="role-access-add panel">
                    <div class="role-access-add-head">
                        <h3><?= __e('settings.roles.add_title') ?></h3>
                        <p><?= __e('settings.roles.add_help') ?></p>
                    </div>
                    <form method="post" class="role-access-add-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="settings_tab" value="roles">
                        <input type="hidden" name="role_action" value="add">
                        <div class="form-grid role-access-add-grid">
                            <div class="form-group">
                                <label for="role_name"><?= __e('settings.roles.role_name') ?></label>
                                <input type="text" id="role_name" name="role_name" placeholder="<?= __e('settings.roles.role_name_ph') ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="role_copy_from"><?= __e('settings.roles.copy_from') ?></label>
                                <select id="role_copy_from" name="role_copy_from">
                                    <?php foreach ($roleList as $r): ?>
                                        <option value="<?= e($r['id']) ?>" <?= $r['id'] === 'staff' ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="role_description"><?= __e('settings.roles.description') ?></label>
                                <input type="text" id="role_description" name="role_description" placeholder="<?= __e('settings.roles.description_ph') ?>">
                            </div>
                        </div>
                        <div class="form-actions role-access-add-actions">
                            <button class="btn btn-primary" type="submit"><?= __e('settings.roles.add_btn') ?></button>
                        </div>
                    </form>
                </div>

                <form method="post" class="settings-form role-access-form" id="roleAccessForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="settings_tab" value="roles">
                    <input type="hidden" name="role_action" value="save">

                    <div class="settings-block role-access-matrix-wrap" style="--role-cols: <?= count($roleList) ?>;">
                        <div class="role-access-matrix-head">
                            <p class="role-access-note"><?= __e('settings.roles.matrix_note', ['company' => $companyName]) ?></p>
                            <span class="role-access-toggle-hint"><?= __e('settings.roles.toggle_hint') ?></span>
                        </div>

                        <div class="role-access-scroll">
                            <table class="role-access-matrix">
                                <thead>
                                    <tr class="role-access-head-row">
                                        <th class="role-access-perm-col role-access-perm-col-head"><?= __e('settings.roles.permission') ?></th>
                                        <?php foreach ($roleList as $i => $role):
                                            $rid = $role['id'];
                                            $count = (int) ($roleUserCounts[$rid] ?? 0);
                                            $theme = role_access_role_theme($rid, !empty($role['builtin']));
                                            $isFirst = $i === 0;
                                            $isLast = $i === count($roleList) - 1;
                                            ?>
                                            <th class="role-access-role-col">
                                                <div class="role-access-header-card" style="--rah-color: <?= e($theme['color']) ?>">
                                                    <div class="rah-top">
                                                        <form method="post" class="rah-mini-form">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="settings_tab" value="roles">
                                                            <input type="hidden" name="role_action" value="rename">
                                                            <input type="hidden" name="role_id" value="<?= e($rid) ?>">
                                                            <input type="hidden" name="role_rename" value="<?= e($role['name']) ?>" class="rah-rename-input">
                                                            <input type="hidden" name="role_rename_description" value="<?= e((string) ($role['description'] ?? '')) ?>">
                                                            <button type="button" class="rah-icon-btn rah-edit" title="<?= __e('common.edit') ?>" aria-label="<?= __e('common.edit') ?>" data-role-name="<?= e($role['name']) ?>" data-role-description="<?= e((string) ($role['description'] ?? '')) ?>">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                                            </button>
                                                        </form>
                                                        <?php if (empty($role['builtin'])): ?>
                                                        <form method="post" class="rah-mini-form" data-confirm="<?= __e('settings.roles.confirm_delete') ?>">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="settings_tab" value="roles">
                                                            <input type="hidden" name="role_action" value="delete">
                                                            <input type="hidden" name="role_id" value="<?= e($rid) ?>">
                                                            <button type="submit" class="rah-icon-btn rah-delete" title="<?= __e('common.delete') ?>" aria-label="<?= __e('common.delete') ?>">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                                                            </button>
                                                        </form>
                                                        <?php else: ?>
                                                        <span class="rah-top-spacer" aria-hidden="true"></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="rah-icon">
                                                        <?= role_access_role_icon_svg($theme['icon']) ?>
                                                    </div>
                                                    <div class="rah-name"><?= e($role['name']) ?></div>
                                                    <?php if (!empty($role['builtin'])): ?>
                                                        <span class="role-access-tag role-access-tag--builtin"><?= __e('settings.roles.builtin') ?></span>
                                                    <?php else: ?>
                                                        <span class="role-access-tag role-access-tag--custom"><?= __e('settings.roles.custom') ?></span>
                                                    <?php endif; ?>
                                                    <div class="rah-users"><?= e(role_access_user_label($count)) ?></div>
                                                    <div class="rah-foot">
                                                        <form method="post" class="rah-mini-form">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="settings_tab" value="roles">
                                                            <input type="hidden" name="role_action" value="copy">
                                                            <input type="hidden" name="role_id" value="<?= e($rid) ?>">
                                                            <button type="submit" class="rah-icon-btn rah-copy" title="<?= __e('common.copy') ?>" aria-label="<?= __e('common.copy') ?>">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                                            </button>
                                                        </form>
                                                        <div class="rah-reorder">
                                                            <form method="post" class="rah-mini-form">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="settings_tab" value="roles">
                                                                <input type="hidden" name="role_action" value="move_left">
                                                                <input type="hidden" name="role_id" value="<?= e($rid) ?>">
                                                                <button type="submit" class="rah-icon-btn rah-move" <?= $isFirst ? 'disabled' : '' ?> aria-label="<?= __e('settings.roles.move_left') ?>">‹</button>
                                                            </form>
                                                            <form method="post" class="rah-mini-form">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="settings_tab" value="roles">
                                                                <input type="hidden" name="role_action" value="move_right">
                                                                <input type="hidden" name="role_id" value="<?= e($rid) ?>">
                                                                <button type="submit" class="rah-icon-btn rah-move" <?= $isLast ? 'disabled' : '' ?> aria-label="<?= __e('settings.roles.move_right') ?>">›</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roleModules as $moduleKey => $moduleLabel): ?>
                                        <tr>
                                            <td class="role-access-perm-label"><?= __e($moduleLabel) ?></td>
                                            <?php foreach ($roleList as $role):
                                                $rid = $role['id'];
                                                $checked = in_array($moduleKey, $rolePerms[$rid]['modules'] ?? [], true);
                                                ?>
                                                <td class="role-access-cell">
                                                    <label class="role-access-check">
                                                        <input type="hidden" name="perm[<?= e($rid) ?>][<?= e($moduleKey) ?>]" value="0">
                                                        <input type="checkbox" name="perm[<?= e($rid) ?>][<?= e($moduleKey) ?>]" value="1" <?= $checked ? 'checked' : '' ?>>
                                                        <span class="role-access-box" aria-hidden="true"></span>
                                                    </label>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="role-access-section-row">
                                        <td colspan="<?= count($roleList) + 1 ?>"><?= __e('settings.roles.scope_section') ?></td>
                                    </tr>
                                    <?php foreach ($roleScopes as $scopeKey => $scopeLabel): ?>
                                        <tr>
                                            <td class="role-access-perm-label"><?= __e($scopeLabel) ?></td>
                                            <?php foreach ($roleList as $role):
                                                $rid = $role['id'];
                                                $checked = !empty($rolePerms[$rid][$scopeKey]);
                                                ?>
                                                <td class="role-access-cell">
                                                    <label class="role-access-check">
                                                        <input type="hidden" name="scope[<?= e($rid) ?>][<?= e($scopeKey) ?>]" value="0">
                                                        <input type="checkbox" name="scope[<?= e($rid) ?>][<?= e($scopeKey) ?>]" value="1" <?= $checked ? 'checked' : '' ?>>
                                                        <span class="role-access-box" aria-hidden="true"></span>
                                                    </label>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="settings-block">
                        <?php
                        $roleAccessSummariesCustomize = false;
                        $roleAccessSummariesShowUsers = true;
                        require __DIR__ . '/../includes/role-access-summaries-panel.php';
                        ?>
                    </div>

                    <div class="form-actions role-access-actions">
                        <button class="btn btn-primary" type="submit"><?= __e('settings.roles.save') ?></button>
                        <a class="btn btn-secondary" href="users.php"><?= __e('settings.roles.back_users') ?></a>
                    </div>
                </form>
            </div>
            <script>
            (function () {
              document.querySelectorAll('.rah-edit').forEach(function (btn) {
                btn.addEventListener('click', function () {
                  var form = btn.closest('form');
                  if (!form) return;
                  var nameInput = form.querySelector('.rah-rename-input');
                  var descInput = form.querySelector('[name="role_rename_description"]');
                  var current = btn.getAttribute('data-role-name') || '';
                  var next = window.prompt(<?= json_encode(__('settings.roles.rename_prompt')) ?>, current);
                  if (next === null) return;
                  next = next.trim();
                  if (!next) return;
                  if (nameInput) nameInput.value = next;
                  if (descInput && btn.getAttribute('data-role-description')) {
                    descInput.value = btn.getAttribute('data-role-description');
                  }
                  form.submit();
                });
              });
            })();
            </script>

        <?php else:
            $backupFrequency = strtolower((string) $get('backup_frequency', 'never'));
            if (!in_array($backupFrequency, ['never', 'weekly', 'monthly'], true)) {
                $backupFrequency = 'never';
            }
            ?>
            <div class="settings-form backup-settings-page">
                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.backup.title') ?></h3>
                        <p><?= __e('settings.backup.help') ?></p>
                    </div>
                    <div class="backup-actions-grid">
                        <form method="post" class="backup-action-card">
                            <?= csrf_field() ?>
                            <input type="hidden" name="settings_tab" value="backup">
                            <input type="hidden" name="backup_action" value="download">
                            <h4><?= __e('settings.backup.download_title') ?></h4>
                            <p><?= __e('settings.backup.download_help') ?></p>
                            <button class="btn btn-primary btn-sm" type="submit"><?= __e('settings.backup.download_now') ?></button>
                        </form>
                        <form method="post" class="backup-action-card">
                            <?= csrf_field() ?>
                            <input type="hidden" name="settings_tab" value="backup">
                            <input type="hidden" name="backup_action" value="email">
                            <h4><?= __e('settings.backup.email_title') ?></h4>
                            <p><?= __e('settings.backup.email_help') ?></p>
                            <button class="btn btn-secondary btn-sm" type="submit"><?= __e('settings.backup.email_now') ?></button>
                        </form>
                    </div>
                </div>

                <form method="post" class="settings-block">
                    <?= csrf_field() ?>
                    <input type="hidden" name="settings_tab" value="backup">
                    <input type="hidden" name="backup_action" value="schedule">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.backup.auto_title') ?></h3>
                        <p><?= __e('settings.backup.auto_help') ?></p>
                    </div>
                    <div class="backup-schedule-row">
                        <div class="form-group">
                            <label for="backup_frequency"><?= __e('settings.backup.frequency') ?></label>
                            <select id="backup_frequency" name="backup_frequency">
                                <option value="never" <?= $backupFrequency === 'never' ? 'selected' : '' ?>><?= __e('settings.backup.frequency.never') ?></option>
                                <option value="weekly" <?= $backupFrequency === 'weekly' ? 'selected' : '' ?>><?= __e('settings.backup.frequency.weekly') ?></option>
                                <option value="monthly" <?= $backupFrequency === 'monthly' ? 'selected' : '' ?>><?= __e('settings.backup.frequency.monthly') ?></option>
                            </select>
                        </div>
                        <div class="backup-schedule-action">
                            <button class="btn btn-primary" type="submit"><?= __e('settings.backup.save_schedule') ?></button>
                        </div>
                    </div>
                </form>

                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.backup.included_title') ?></h3>
                        <p><?= __e('settings.backup.included_help') ?></p>
                    </div>
                    <div class="list-stack">
                        <div class="list-item"><strong><?= __e('settings.backup.database') ?></strong><span class="muted"><?= __e('settings.backup.database_help') ?></span></div>
                        <div class="list-item"><strong><?= __e('settings.backup.schema') ?></strong><span class="muted"><?= __e('settings.backup.schema_help') ?></span></div>
                        <div class="list-item"><strong><?= __e('settings.backup.uploads') ?></strong><span class="muted"><?= __e('settings.backup.uploads_help') ?></span></div>
                    </div>
                </div>

                <form method="post" class="settings-block" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="settings_tab" value="backup">
                    <input type="hidden" name="backup_action" value="restore">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.backup.restore_title') ?></h3>
                        <p><?= __e('settings.backup.restore_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="backup_file"><?= __e('settings.backup.restore_file') ?></label>
                            <input type="file" id="backup_file" name="backup_file" accept="application/json,.json">
                            <span class="field-hint"><?= __e('settings.backup.restore_note') ?></span>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-secondary" type="submit"><?= __e('settings.backup.restore_btn') ?></button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</section>
<script>
(function () {
  const hidden = document.getElementById('branding_accent');
  const custom = document.getElementById('branding_accent_custom');
  const hexInput = document.getElementById('branding_accent_hex');
  const panel = document.getElementById('accent-custom-panel');
  const stripe = document.getElementById('accent-custom-stripe');
  const palette = document.getElementById('accent-palette');
  if (!hidden || !palette) return;

  function normHex(v) {
    v = (v || '').trim();
    if (!v) return '';
    if (v.charAt(0) !== '#') v = '#' + v;
    return /^#[0-9a-fA-F]{6}$/.test(v) ? v.toLowerCase() : '';
  }

  function darken(hex, amt) {
    amt = amt || 40;
    const n = parseInt(hex.slice(1), 16);
    const r = Math.max(0, ((n >> 16) & 255) - amt);
    const g = Math.max(0, ((n >> 8) & 255) - amt);
    const b = Math.max(0, (n & 255) - amt);
    return '#' + [r, g, b].map((x) => x.toString(16).padStart(2, '0')).join('');
  }

  function applyThemePreview(hex) {
    const c = normHex(hex);
    if (!c || typeof window.applyLexoraAccent !== 'function') return;
    window.applyLexoraAccent(c);
  }

  function setAccent(hex, fromCustom) {
    const c = normHex(hex);
    if (!c) return;
    hidden.value = c;
    if (custom) custom.value = c;
    if (hexInput) hexInput.value = c.toUpperCase();
    if (stripe) stripe.style.background = 'linear-gradient(135deg, ' + c + ' 0%, ' + darken(c) + ' 100%)';
    if (panel) panel.hidden = !fromCustom;
    applyThemePreview(c);
  }

  palette.querySelectorAll('input[name="branding_accent_choice"]').forEach((radio) => {
    radio.addEventListener('change', () => {
      if (radio.value === 'custom') {
        setAccent(custom ? custom.value : hidden.value, true);
      } else {
        setAccent(radio.value, false);
      }
    });
  });

  if (custom) {
    custom.addEventListener('input', () => {
      const customRadio = palette.querySelector('input[value="custom"]');
      if (customRadio) customRadio.checked = true;
      setAccent(custom.value, true);
    });
  }

  if (hexInput) {
    hexInput.addEventListener('input', () => {
      const n = normHex(hexInput.value);
      if (!n) return;
      const customRadio = palette.querySelector('input[value="custom"]');
      if (customRadio) customRadio.checked = true;
      setAccent(n, true);
    });
    hexInput.addEventListener('blur', () => {
      const n = normHex(hexInput.value);
      if (n) hexInput.value = n.toUpperCase();
    });
  }
})();
(function () {
  const dd = document.getElementById('paymentMethodsDropdown');
  if (!dd) return;
  const value = dd.querySelector('.ms-dropdown-value');
  const boxes = dd.querySelectorAll('input[type="checkbox"]');
  const placeholder = value ? value.getAttribute('data-placeholder') : '';
  function sync() {
    const labels = [];
    boxes.forEach((b) => { if (b.checked) labels.push(b.parentNode.querySelector('span').textContent.trim()); });
    if (!value) return;
    if (labels.length) {
      value.textContent = labels.join(', ');
    } else {
      value.innerHTML = '<span class="ms-dropdown-placeholder">' + placeholder + '</span>';
    }
  }
  boxes.forEach((b) => b.addEventListener('change', sync));
  document.addEventListener('click', (e) => {
    if (dd.open && !dd.contains(e.target)) dd.open = false;
  });
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
