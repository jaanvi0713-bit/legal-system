<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(portal_home(current_user()['role']));
}

$error = '';
$selectedRole = post('login_role', get('portal', 'admin'));
if (!in_array($selectedRole, ['admin', 'lawyer', 'client'], true)) {
    $selectedRole = 'admin';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $login = post('login', '');
    $password = post('password', '');
    $role = post('login_role', 'admin');
    if (!in_array($role, ['admin', 'lawyer', 'client'], true)) {
        $error = __('login.error_role');
    } elseif ($login === '' || $password === '') {
        $error = __('login.error_required');
    } elseif (!attempt_login($login, $password)) {
        $error = __('login.error_invalid');
    } else {
        $user = current_user();
        $ok = match ($role) {
            'admin' => in_array($user['role'], ['admin', 'staff'], true),
            'lawyer' => $user['role'] === 'lawyer',
            'client' => $user['role'] === 'client',
            default => false,
        };
        if (!$ok) {
            logout_user();
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            bootstrap_locale();
            $error = __('login.error_portal', ['role' => translate_role($role)]);
            $selectedRole = $role;
        } else {
            redirect(portal_home($user['role']));
        }
    }
    $selectedRole = $role;
}

$appName = app_config('name', 'LEGAL PRO');
$brandName = app_config('brand', 'LEGAL PRO');
try {
    $appName = get_setting(db(), 'company_name', app_config('name'));
} catch (Throwable $e) {
    $appName = app_config('name', 'LEGAL PRO');
}
$accent = '#023e8a';
try {
    $accent = get_setting(db(), 'branding_accent', '#023e8a') ?: '#023e8a';
} catch (Throwable $e) {
}
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? strtolower($accent) : '#023e8a';
$r = hexdec(substr($accent, 1, 2));
$g = hexdec(substr($accent, 3, 2));
$b = hexdec(substr($accent, 5, 2));
$accentDark = sprintf('#%02x%02x%02x', max(0, $r - 30), max(0, $g - 30), max(0, $b - 30));

$base = app_config('url');
$cssVer = @filemtime(__DIR__ . '/assets/css/login.css') ?: time();
$uiLang = current_lang();
?>
<!DOCTYPE html>
<html lang="<?= e($uiLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($appName) ?> · <?= __e('login.title') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/login.css?v=<?= (int) $cssVer ?>">
    <style>
        body.login-page {
            --lp-accent: <?= e($accent) ?>;
            --lp-accent-dark: <?= e($accentDark) ?>;
            --lp-accent-rgb: <?= (int)$r ?>, <?= (int)$g ?>, <?= (int)$b ?>;
            --lp-bg-image: url("<?= e($base) ?>/assets/img/login-waves.png?v=<?= (int) (@filemtime(__DIR__ . '/assets/img/login-waves.png') ?: time()) ?>");
        }
    </style>
</head>
<body class="login-page">
    <div class="login-scene">
        <div class="login-bg" aria-hidden="true">
            <div class="login-bg-waves"></div>
            <div class="login-bg-glow"></div>
            <div class="login-bg-vignette"></div>
        </div>
        <div class="login-lang" role="group" aria-label="<?= __e('common.language') ?>">
            <a class="login-lang-btn <?= $uiLang === 'en' ? 'is-active' : '' ?>" href="<?= e(lang_switch_url('en')) ?>" hreflang="en">EN</a>
            <a class="login-lang-btn <?= $uiLang === 'fr' ? 'is-active' : '' ?>" href="<?= e(lang_switch_url('fr')) ?>" hreflang="fr">FR</a>
        </div>
        <div class="login-stage">
            <div class="login-plate">
                <div class="glass-card">
                    <div class="card-head">
                        <div class="brand-badge" aria-hidden="true">L</div>
                        <div class="company-name"><?= e(strtoupper($brandName)) ?></div>
                    </div>

                    <h2><?= __e('login.welcome') ?></h2>
                    <p class="subtitle"><?= __e('login.subtitle') ?></p>

                    <?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?>

                    <form method="post" class="login-form" id="login-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="login_role" id="login_role" value="<?= e($selectedRole) ?>">

                        <div class="field">
                            <label for="login"><?= __e('login.username') ?></label>
                            <div class="field-input">
                                <input type="text" id="login" name="login" required autocomplete="username" value="<?= e(post('login', '')) ?>" placeholder="<?= __e('login.username_ph') ?>">
                            </div>
                        </div>

                        <div class="field">
                            <label for="password"><?= __e('login.password') ?></label>
                            <div class="field-input">
                                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="<?= __e('login.password_ph') ?>">
                                <button type="button" class="password-toggle" id="password-toggle" aria-label="<?= __e('login.show_password') ?>">
                                    <svg class="eye-icon eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="eye-icon eye-closed is-hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3l18 18M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-1.2M9.9 5.1A11 11 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-4.2 4.8M6.1 6.1A18 18 0 0 0 2 12s3.5 7 10 7a10 10 0 0 0 3.1-.5"/></svg>
                                </button>
                            </div>
                        </div>

                        <div class="login-meta">
                            <label class="remember"><input type="checkbox" name="remember" value="1"> <?= __e('login.remember') ?></label>
                            <a class="forgot" href="#" tabindex="-1" onclick="return false;"><?= __e('login.forgot') ?></a>
                        </div>

                        <button class="login-btn" type="submit"><?= __e('login.submit') ?></button>
                    </form>

                    <div class="role-divider"><span><?= __e('login.as') ?></span></div>
                    <div class="role-picker" role="group" aria-label="<?= __e('login.sign_in_as') ?>">
                        <?php
                        $roles = [
                            'admin' => ['role.admin', 'M12 3l7 4v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V7l7-4z'],
                            'lawyer' => ['role.lawyer', 'M12 3v18M5 8h14M7 8l-2 10h14l-2-10'],
                            'client' => ['role.client', 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 20a8 8 0 0 1 16 0'],
                        ];
                        foreach ($roles as $key => [$labelKey, $path]):
                        ?>
                            <button type="button" class="role-btn <?= $selectedRole === $key ? 'is-active' : '' ?>" data-role="<?= e($key) ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="<?= e($path) ?>"/></svg>
                                <span><?= __e($labelKey) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function () {
      const roleInput = document.getElementById('login_role');
      document.querySelectorAll('.role-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
          document.querySelectorAll('.role-btn').forEach((b) => b.classList.remove('is-active'));
          btn.classList.add('is-active');
          roleInput.value = btn.dataset.role;
        });
      });
      const toggle = document.getElementById('password-toggle');
      const password = document.getElementById('password');
      if (toggle && password) {
        toggle.addEventListener('click', () => {
          const show = password.type === 'password';
          password.type = show ? 'text' : 'password';
          toggle.querySelector('.eye-open').classList.toggle('is-hidden', show);
          toggle.querySelector('.eye-closed').classList.toggle('is-hidden', !show);
        });
      }
    })();
    </script>
</body>
</html>
