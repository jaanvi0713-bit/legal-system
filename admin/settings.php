<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);
$pdo = db();
$user = current_user();

$tabs = [
    'profile' => 'settings.tab.profile',
    'notifications' => 'settings.tab.notifications',
    'branding' => 'settings.tab.branding',
    'email' => 'settings.tab.email',
    'payments' => 'settings.tab.payments',
    'language' => 'settings.tab.language',
    'roles' => 'settings.tab.roles',
    'backup' => 'settings.tab.backup',
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
        flash('success', __('settings.profile.saved'));
    } elseif ($section === 'notifications') {
        foreach (['notify_appointments', 'notify_payments', 'notify_cases', 'notify_email_digest'] as $key) {
            set_setting($pdo, $key, post($key, '0') === '1' ? '1' : '0');
        }
        flash('success', __('settings.notifications.saved'));
    } elseif ($section === 'branding') {
        foreach ([
            'company_name', 'company_email', 'company_phone', 'company_address',
            'company_website', 'company_registration', 'branding_font',
            'branding_accent', 'theme',
            'ai_enabled', 'ai_welcome_admin', 'ai_welcome_lawyer', 'ai_welcome_client',
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
        flash('success', __('settings.branding.saved'));
    } elseif ($section === 'email') {
        foreach (['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from', 'smtp_encryption'] as $key) {
            set_setting($pdo, $key, post($key));
        }
        flash('success', __('settings.email.saved'));
    } elseif ($section === 'payments') {
        foreach (['payment_currency', 'payment_methods', 'payment_instructions'] as $key) {
            set_setting($pdo, $key, post($key));
        }
        flash('success', __('settings.payments.saved'));
    } elseif ($section === 'language') {
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
        flash('success', __('settings.language.saved'));
    } elseif ($section === 'backup') {
        flash('success', __('settings.backup.saved'));
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
                        <div class="form-group"><label><?= __e('form.first_name') ?></label><input name="first_name" required value="<?= e($user['first_name']) ?>"></div>
                        <div class="form-group"><label><?= __e('form.last_name') ?></label><input name="last_name" required value="<?= e($user['last_name']) ?>"></div>
                        <div class="form-group"><label><?= __e('common.email') ?></label><input value="<?= e($user['email']) ?>" disabled></div>
                        <div class="form-group"><label><?= __e('form.username') ?></label><input value="<?= e($user['username']) ?>" disabled></div>
                        <div class="form-group"><label><?= __e('common.phone') ?></label><input name="phone" value="<?= e($user['phone'] ?? '') ?>"></div>
                        <div class="form-group"><label><?= __e('form.new_password') ?></label><input type="password" name="password" placeholder="<?= __e('form.password_keep') ?>"></div>
                        <div class="form-group full"><label><?= __e('common.address') ?></label><textarea name="address"><?= e($user['address'] ?? '') ?></textarea></div>
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
                    <div class="settings-checks">
                        <?php
                        $prefs = [
                            'notify_appointments' => 'settings.notifications.appointments',
                            'notify_payments' => 'settings.notifications.payments',
                            'notify_cases' => 'settings.notifications.cases',
                            'notify_email_digest' => 'settings.notifications.email_digest',
                        ];
                        foreach ($prefs as $key => $labelKey):
                            $on = $get($key, '1') === '1';
                        ?>
                            <label class="settings-check">
                                <input type="checkbox" name="<?= e($key) ?>" value="1" <?= $on ? 'checked' : '' ?>>
                                <span><?= __e($labelKey) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><?= __e('settings.notifications.save') ?></button></div>
            </form>

        <?php elseif ($tab === 'branding'): ?>
            <form method="post" class="settings-form">
                <?= csrf_field() ?>
                <input type="hidden" name="settings_tab" value="branding">

                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.branding.title') ?></h3>
                        <p><?= __e('settings.branding.company_info') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label><?= __e('settings.branding.company_name') ?></label><input name="company_name" value="<?= e($get('company_name', 'LEGAL PRO')) ?>"></div>
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
                            <label><?= __e('settings.branding.ai_assistant') ?></label>
                            <select name="ai_enabled">
                                <option value="1" <?= $get('ai_enabled', '1') === '1' ? 'selected' : '' ?>><?= __e('common.enabled') ?></option>
                                <option value="0" <?= $get('ai_enabled') === '0' ? 'selected' : '' ?>><?= __e('common.disabled') ?></option>
                            </select>
                        </div>
                        <div class="form-group full">
                            <label><?= __e('settings.branding.workspace_url') ?></label>
                            <input value="<?= e($base) ?>" disabled>
                            <span class="field-hint">Portals: <code><?= e($base) ?>/admin</code>, <code><?= e($base) ?>/lawyer</code>, <code><?= e($base) ?>/client</code></span>
                        </div>
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
                        <div class="form-group"><label><?= __e('settings.branding.website') ?></label><input name="company_website" value="<?= e($get('company_website')) ?>" placeholder="https://"></div>
                        <div class="form-group"><label><?= __e('settings.branding.registration') ?></label><input name="company_registration" value="<?= e($get('company_registration')) ?>"></div>
                        <div class="form-group"><label><?= __e('common.email') ?></label><input name="company_email" value="<?= e($get('company_email')) ?>"></div>
                        <div class="form-group"><label><?= __e('common.phone') ?></label><input name="company_phone" value="<?= e($get('company_phone')) ?>"></div>
                        <div class="form-group full"><label><?= __e('common.address') ?></label><textarea name="company_address"><?= e($get('company_address')) ?></textarea></div>
                    </div>
                </div>

                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.branding.ai_prompts') ?></h3>
                    </div>
                    <div class="form-grid">
                        <div class="form-group full"><label><?= __e('settings.branding.ai_prompt_admin') ?></label><textarea name="ai_welcome_admin"><?= e($get('ai_welcome_admin')) ?></textarea></div>
                        <div class="form-group full"><label><?= __e('settings.branding.ai_prompt_lawyer') ?></label><textarea name="ai_welcome_lawyer"><?= e($get('ai_welcome_lawyer')) ?></textarea></div>
                        <div class="form-group full"><label><?= __e('settings.branding.ai_prompt_client') ?></label><textarea name="ai_welcome_client"><?= e($get('ai_welcome_client')) ?></textarea></div>
                    </div>
                </div>

                <div class="form-actions"><button class="btn btn-primary" type="submit"><?= __e('settings.branding.save') ?></button></div>
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
                        <div class="form-group"><label><?= __e('settings.email.host') ?></label><input name="smtp_host" value="<?= e($get('smtp_host')) ?>" placeholder="smtp.example.com"></div>
                        <div class="form-group"><label><?= __e('settings.email.port') ?></label><input name="smtp_port" value="<?= e($get('smtp_port', '587')) ?>"></div>
                        <div class="form-group"><label><?= __e('settings.email.user') ?></label><input name="smtp_user" value="<?= e($get('smtp_user')) ?>"></div>
                        <div class="form-group"><label><?= __e('settings.email.pass') ?></label><input type="password" name="smtp_pass" value="<?= e($get('smtp_pass')) ?>"></div>
                        <div class="form-group"><label><?= __e('settings.email.from') ?></label><input name="smtp_from" value="<?= e($get('smtp_from', $get('company_email'))) ?>"></div>
                        <div class="form-group">
                            <label><?= __e('settings.email.encryption') ?></label>
                            <select name="smtp_encryption">
                                <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => __('content.none')] as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= $get('smtp_encryption', 'tls') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
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
                        <div class="form-group">
                            <label><?= __e('common.currency') ?></label>
                            <select name="payment_currency">
                                <?php foreach (['MUR' => 'content.currency.mur', 'INR' => 'content.currency.inr', 'AED' => 'content.currency.aed', 'USD' => 'content.currency.usd', 'EUR' => 'content.currency.eur', 'GBP' => 'content.currency.gbp'] as $currency => $labelKey): ?>
                                    <option value="<?= e($currency) ?>" <?= $get('payment_currency', 'MUR') === $currency ? 'selected' : '' ?>><?= e(__($labelKey)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label><?= __e('settings.payments.methods') ?></label><input name="payment_methods" value="<?= e($get('payment_methods', __('content.settings.payment_methods'))) ?>"></div>
                        <div class="form-group full"><label><?= __e('settings.payments.instructions') ?></label><textarea name="payment_instructions"><?= e($get('payment_instructions', __('content.settings.payment_instructions'))) ?></textarea></div>
                    </div>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><?= __e('settings.save_payments') ?></button></div>
            </form>

        <?php elseif ($tab === 'language'): ?>
            <form method="post" class="settings-form">
                <?= csrf_field() ?>
                <input type="hidden" name="settings_tab" value="language">
                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.language.title') ?></h3>
                        <p><?= __e('settings.language.help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label><?= __e('settings.language.default') ?></label>
                            <select name="app_language">
                                <?php foreach (supported_langs() as $code => $label): ?>
                                    <option value="<?= e($code) ?>" <?= $get('app_language', 'en') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><?= __e('settings.save_language') ?></button></div>
            </form>

        <?php elseif ($tab === 'roles'): ?>
            <div class="settings-block">
                <div class="settings-block-head">
                    <h3><?= __e('settings.roles.title') ?></h3>
                    <p><?= __e('settings.roles.help') ?></p>
                </div>
                <div class="role-cards">
                    <div class="role-card"><strong><?= __e('role.admin') ?></strong><span><?= __e('settings.roles.admin') ?></span></div>
                    <div class="role-card"><strong><?= __e('role.staff') ?></strong><span><?= __e('settings.roles.staff') ?></span></div>
                    <div class="role-card"><strong><?= __e('role.lawyer') ?></strong><span><?= __e('settings.roles.lawyer') ?></span></div>
                    <div class="role-card"><strong><?= __e('role.client') ?></strong><span><?= __e('settings.roles.client') ?></span></div>
                </div>
                <div class="form-actions" style="margin-top:1rem;">
                    <a class="btn btn-primary" href="users.php"><?= __e('settings.roles.open_users') ?></a>
                </div>
            </div>

        <?php else: ?>
            <form method="post" class="settings-form">
                <?= csrf_field() ?>
                <input type="hidden" name="settings_tab" value="backup">
                <div class="settings-block">
                    <div class="settings-block-head">
                        <h3><?= __e('settings.backup.title') ?></h3>
                        <p><?= __e('settings.backup.help') ?></p>
                    </div>
                    <div class="list-stack">
                        <div class="list-item"><strong><?= __e('settings.backup.database') ?></strong><span class="muted"><?= __e('settings.backup.database_help') ?></span></div>
                        <div class="list-item"><strong><?= __e('settings.backup.schema') ?></strong><span class="muted"><?= __e('settings.backup.schema_help') ?></span></div>
                        <div class="list-item"><strong><?= __e('settings.backup.uploads') ?></strong><span class="muted"><?= __e('settings.backup.uploads_help') ?></span></div>
                    </div>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><?= __e('settings.backup.acknowledge') ?></button></div>
            </form>
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
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
