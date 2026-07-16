<?php
/**
 * Lightweight English / French localisation
 */

function supported_langs(): array
{
    return ['en' => 'English', 'fr' => 'Français'];
}

function bootstrap_locale(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $supported = array_keys(supported_langs());

    if (isset($_GET['lang'])) {
        $requested = strtolower((string) $_GET['lang']);
        if (in_array($requested, $supported, true)) {
            $_SESSION['lang'] = $requested;
            setcookie('lexora_lang', $requested, [
                'expires' => time() + 60 * 60 * 24 * 365,
                'path' => '/',
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
            // Drop lang from query so refreshes / forms stay clean
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $parts = parse_url($uri);
            $path = $parts['path'] ?? '';
            $query = [];
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $query);
                unset($query['lang']);
            }
            $target = $path . ($query ? '?' . http_build_query($query) : '');
            if ($target !== '') {
                header('Location: ' . $target);
                exit;
            }
        }
    }

    if (empty($_SESSION['lang'])) {
        $cookie = $_COOKIE['lexora_lang'] ?? '';
        if (in_array($cookie, $supported, true)) {
            $_SESSION['lang'] = $cookie;
        } else {
            $default = 'en';
            try {
                $default = (string) get_setting(db(), 'app_language', app_config('language', 'en'));
            } catch (Throwable $e) {
                $default = (string) app_config('language', 'en');
            }
            $_SESSION['lang'] = in_array($default, $supported, true) ? $default : 'en';
        }
    }
}

function current_lang(): string
{
    bootstrap_locale();
    $lang = $_SESSION['lang'] ?? 'en';
    return isset(supported_langs()[$lang]) ? $lang : 'en';
}

function lang_strings(?string $lang = null): array
{
    static $cache = [];
    $lang = $lang ?? current_lang();
    if (!isset($cache[$lang])) {
        $file = __DIR__ . '/../lang/' . $lang . '.php';
        $cache[$lang] = is_file($file) ? (require $file) : [];
        if ($lang !== 'en') {
            $enFile = __DIR__ . '/../lang/en.php';
            $en = is_file($enFile) ? (require $enFile) : [];
            $cache[$lang] = array_merge($en, $cache[$lang]);
        }
    }
    return $cache[$lang];
}

/**
 * Translate a key. Supports :name placeholders via $replace.
 */
function __(string $key, array $replace = []): string
{
    $strings = lang_strings();
    $text = $strings[$key] ?? $key;
    foreach ($replace as $name => $value) {
        $text = str_replace(':' . $name, (string) $value, $text);
    }
    return $text;
}

function __e(string $key, array $replace = []): string
{
    return e(__($key, $replace));
}

function lang_switch_url(string $lang): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($uri);
    $path = $parts['path'] ?? '/';
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $query['lang'] = $lang;
    return $path . '?' . http_build_query($query);
}

function translate_status(string $status): string
{
    $key = 'status.' . $status;
    $translated = __($key);
    if ($translated !== $key) {
        return $translated;
    }
    return ucwords(str_replace('_', ' ', $status));
}

function translate_role(string $role): string
{
    $key = 'role.' . $role;
    $translated = __($key);
    if ($translated !== $key) {
        return $translated;
    }
    return ucwords(str_replace('_', ' ', $role));
}
