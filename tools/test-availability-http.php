<?php
/**
 * HTTP smoke tests for availability pages (run against local WAMP).
 * Run: c:\wamp64\bin\php\php8.2.29\php.exe tools/test-availability-http.php
 */
declare(strict_types=1);

$base = rtrim(getenv('LEXORA_BASE_URL') ?: 'http://localhost/legal-system', '/');
$failures = 0;

function http_fail(string $label, string $detail = ''): void
{
    global $failures;
    $failures++;
    echo "FAIL: {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
}

function http_pass(string $label): void
{
    echo "PASS: {$label}\n";
}

function cookie_jar(): string
{
    static $jar = '';
    if ($jar === '') {
        $jar = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lexora-avail-test-' . getmypid() . '.txt';
    }
    return $jar;
}

/** @return array{code:int, body:string, headers:array<string,string>} */
function http_request(string $method, string $url, ?string $body = null, array $extraHeaders = []): array
{
    $ch = curl_init($url);
    $headers = $extraHeaders;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_COOKIEJAR => cookie_jar(),
        CURLOPT_COOKIEFILE => cookie_jar(),
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    if ($body !== null) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    if ($headers) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    if ($raw === false) {
        return ['code' => 0, 'body' => '', 'headers' => []];
    }
    $rawHeaders = substr($raw, 0, $headerSize);
    $bodyOut = substr($raw, $headerSize);
    $parsed = [];
    foreach (preg_split('/\r\n|\n|\r/', $rawHeaders) as $line) {
        if (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $parsed[strtolower(trim($k))] = trim($v);
        }
    }
    return ['code' => $code, 'body' => $bodyOut, 'headers' => $parsed];
}

function extract_csrf(string $html): string
{
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/name=\'csrf_token\'\s+value=\'([^\']+)\'/', $html, $m)) {
        return $m[1];
    }
    return '';
}

echo "=== HTTP availability smoke tests ({$base}) ===\n\n";

$loginPage = http_request('GET', $base . '/');
if ($loginPage['code'] !== 200) {
    http_fail('login page loads', 'HTTP ' . $loginPage['code']);
    exit(1);
}
http_pass('login page loads');

$csrf = extract_csrf($loginPage['body']);
if ($csrf === '') {
    http_fail('csrf token on login page');
} else {
    http_pass('csrf token on login page');
}

$loginBody = http_build_query([
    'csrf_token' => $csrf,
    'login_role' => 'admin',
    'login' => 'admin',
    'password' => 'admin123',
]);
$loginResp = http_request('POST', $base . '/', $loginBody);
if ($loginResp['code'] !== 200 || (!str_contains($loginResp['body'], 'Dashboard') && !str_contains($loginResp['body'], 'dashboard') && !str_contains($loginResp['body'], 'admin/index'))) {
    if ($loginResp['code'] < 200 || $loginResp['code'] >= 400) {
        http_fail('admin login', 'HTTP ' . $loginResp['code']);
    } elseif (str_contains($loginResp['body'], 'login.error') || str_contains($loginResp['body'], 'Invalid') || str_contains($loginResp['body'], 'error_invalid')) {
        http_fail('admin login', 'credentials rejected');
    } elseif (str_contains($loginResp['body'], 'name="password"')) {
        http_fail('admin login', 'still on login page');
    } else {
        http_pass('admin login');
    }
} else {
    http_pass('admin login');
}

$availPage = http_request('GET', $base . '/admin/lawyer-availability.php?lawyer_id=2');
if ($availPage['code'] !== 200) {
    http_fail('lawyer availability page', 'HTTP ' . $availPage['code']);
} else {
    http_pass('lawyer availability page');
}

$html = $availPage['body'];
$checks = [
    'data-avail-blocks-form' => 'availability blocks form marker',
    'avail-rail appt-cal-sidebar' => 'full-height sidebar rail',
    'availBlocksBody' => 'blocks table body',
    'availBlocksPager' => null,
    'case-list-pager' => null,
    'Showing' => null,
    'badge-st-busy' => null,
    'status.busy' => null,
];
foreach ($checks as $needle => $label) {
    $found = str_contains($html, $needle);
    if ($label === null) {
        if ($found) {
            http_fail("should not contain {$needle}");
        } else {
            http_pass("no {$needle} in page");
        }
    } elseif ($found) {
        http_pass($label);
    } else {
        http_fail($label, "missing {$needle}");
    }
}

if (str_contains($html, 'lawyer_availability_statuses')) {
    http_fail('raw PHP leaked');
}

$lawyersPage = http_request('GET', $base . '/admin/lawyers.php');
if ($lawyersPage['code'] === 200) {
    http_pass('lawyers list page');
    if (str_contains($lawyersPage['body'], 'badge-st-busy')) {
        http_fail('lawyers list shows busy badge');
    } else {
        http_pass('lawyers list has no busy badge');
    }
    if (preg_match('/<option value="busy"/', $lawyersPage['body'])) {
        http_fail('lawyers edit form still has busy option');
    } else {
        http_pass('lawyers form has no busy option');
    }
} else {
    http_fail('lawyers list page', 'HTTP ' . $lawyersPage['code']);
}

// API bookable slots for James Carter with blocks on current week
$weekStart = date('Y-m-d', strtotime('monday this week'));
$apiUrl = $base . '/api/lawyer-availability.php?lawyer_id=2&date=' . urlencode(date('Y-m-d')) . '&duration=30';
$apiResp = http_request('GET', $apiUrl);
if ($apiResp['code'] === 200) {
    http_pass('availability API responds');
    $json = json_decode($apiResp['body'], true);
    if (!is_array($json) || !isset($json['slots'])) {
        http_fail('availability API JSON shape');
    } else {
        http_pass('availability API JSON shape');
        if (($json['source'] ?? '') === 'blocks') {
            http_pass('API uses blocks source when schedule exists');
        }
    }
} else {
    http_fail('availability API', 'HTTP ' . $apiResp['code']);
}

// Lawyer portal availability (editable form + save markup)
$logout = http_request('GET', $base . '/logout.php');
$lawyerLoginPage = http_request('GET', $base . '/');
$lawyerCsrf = extract_csrf($lawyerLoginPage['body']);
$lawyerLoginBody = http_build_query([
    'csrf_token' => $lawyerCsrf,
    'login_role' => 'lawyer',
    'login' => 'lawyer01',
    'password' => 'lawyer01',
]);
$lawyerLogin = http_request('POST', $base . '/', $lawyerLoginBody);
if (str_contains($lawyerLogin['body'], 'name="password"')) {
    http_fail('lawyer login', 'still on login page');
} else {
    http_pass('lawyer login');
}

$lawyerAvail = http_request('GET', $base . '/lawyer/availability.php');
if ($lawyerAvail['code'] === 200 && str_contains($lawyerAvail['body'], 'data-avail-blocks-form')) {
    http_pass('lawyer availability editor');
    if (str_contains($lawyerAvail['body'], 'availBlocksPager')) {
        http_fail('lawyer page still has pager');
    } else {
        http_pass('lawyer page has no pager');
    }
    if (str_contains($lawyerAvail['body'], 'lawyer.availability.save')) {
        http_pass('lawyer save button present');
    }
} else {
    http_fail('lawyer availability editor', 'HTTP ' . $lawyerAvail['code']);
}

@unlink(cookie_jar());

echo "\n";
if ($failures === 0) {
    echo "All HTTP tests passed.\n";
    exit(0);
}
echo "{$failures} HTTP test(s) failed.\n";
exit(1);
