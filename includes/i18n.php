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
    if (function_exists('normalize_appointment_status')) {
        $status = normalize_appointment_status($status);
    }
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

/**
 * Resolve a stored i18n string.
 * Supports: "notify.key", "notify.key::{json params}", or legacy English titles.
 */
function t_stored(string $stored, array $extra = []): string
{
    $stored = (string) $stored;
    if ($stored === '') {
        return '';
    }

    if (preg_match('/^(notify\.[a-z0-9_.]+)::(\{.*\})$/s', $stored, $m)) {
        $params = json_decode($m[2], true);
        if (!is_array($params)) {
            $params = [];
        }
        $params = array_merge($params, $extra);
        if ($m[1] === 'notify.appointment_status' && !empty($params['status'])) {
            $params['status'] = translate_status((string) $params['status']);
        }
        foreach ($params as $k => $v) {
            if (is_string($v) && !in_array($k, ['amount', 'number', 'invoice', 'receipt', 'date', 'status'], true)) {
                $params[$k] = t_content($v);
            }
        }
        return __($m[1], $params);
    }

    if (str_starts_with($stored, 'notify.') || str_starts_with($stored, 'ai.')) {
        $translated = __($stored, $extra);
        return $translated !== $stored ? $translated : $stored;
    }

    static $legacy = [
        'Appointment scheduled' => 'notify.appointment_scheduled',
        'Appointment request' => 'notify.appointment_request',
        'Appointment pending' => 'notify.appointment_pending',
        'Meeting scheduled' => 'notify.meeting_scheduled',
        'New client registered' => 'notify.new_client',
        'Client created' => 'notify.client_created',
        'New client assignment' => 'notify.client_assigned',
        'Payment received' => 'notify.payment_received',
        'Payment reminder' => 'notify.payment_reminder',
        'Client payment recorded' => 'notify.payment_recorded',
        'New invoice' => 'notify.invoice_new',
        'New case assigned' => 'notify.case_assigned',
        'Case assigned' => 'notify.case_assigned_short',
        'Case opened' => 'notify.case_opened',
        'Case update' => 'notify.case_update',
        'Hearing reminder' => 'notify.hearing_reminder',
        'Document requested' => 'notify.document_requested',
        'Client document uploaded' => 'notify.document_uploaded',
        'Client message' => 'notify.client_message',
    ];
    if (isset($legacy[$stored])) {
        return __($legacy[$stored], $extra);
    }

    // "Appointment accepted" style
    if (preg_match('/^Appointment\s+(\w+)$/i', $stored, $m)) {
        return __('notify.appointment_status', ['status' => translate_status(strtolower($m[1]))]);
    }

    // Legacy English message bodies (pre-i18n seed / older rows)
    if (preg_match('/^(.+?) has been added as a client\.?$/i', $stored, $m)) {
        return __('notify.msg.new_client', ['name' => $m[1]]);
    }
    if (preg_match('/^(?:Rs\s*|₹|\$|€|\?)\s*([\d,.]+)\s+received from (.+?)\.?$/iu', $stored, $m)) {
        return __('notify.msg.payment_received', ['amount' => 'Rs ' . $m[1], 'from' => $m[2]]);
    }
    if (preg_match('/^You have been assigned to (.+?)\.?$/i', $stored, $m)) {
        return __('notify.msg.case_assigned', ['number' => $m[1]]);
    }
    if (preg_match('/^(.+?) scheduled for (.+?)\.?$/i', $stored, $m)) {
        return __('notify.msg.hearing_reminder', ['title' => t_content($m[1]), 'date' => $m[2]]);
    }
    if (preg_match('/^(.+?) awaits your response\.?$/i', $stored, $m)) {
        return __('notify.msg.appointment_pending', ['title' => t_content($m[1])]);
    }
    if (preg_match('/^Please upload the (.+?)\.?$/i', $stored, $m)) {
        return __('notify.msg.document_requested_named', ['doc' => t_content($m[1])]);
    }
    if (preg_match('/^Outstanding balance of (?:Rs\s*|₹|\$|€|\?)\s*([\d,.]+) on (.+?)\.?$/iu', $stored, $m)) {
        return __('notify.msg.payment_reminder', ['amount' => 'Rs ' . $m[1], 'invoice' => $m[2]]);
    }
    if (preg_match('/^A case has been assigned to you\.?$/i', $stored)) {
        return __('notify.msg.case_assigned_generic');
    }
    if (preg_match('/^The status of your case has been updated\.?$/i', $stored)) {
        return __('notify.msg.case_update_status');
    }

    // Fix mojibake / wrong currency glyphs left in free-text messages
    if (preg_match('/(?:₹|\?)(?=[\d,.])/', $stored)) {
        $stored = preg_replace('/(?:₹|\?)(?=[\d,.])/', 'Rs ', $stored) ?? $stored;
    }

    return t_content($stored);
}

/**
 * Translate known catalog / seed phrases for display. Unknown (user) text passes through.
 */
function t_content(?string $text): string
{
    if ($text === null || $text === '') {
        return $text ?? '';
    }

    static $map = null;
    if ($map === null) {
        $map = [
            // Case types
            'Commercial' => 'content.type.commercial',
            'Employment' => 'content.type.employment',
            'Corporate' => 'content.type.corporate',
            'Real Estate' => 'content.type.real_estate',
            'Other' => 'content.type.other',
            // Specializations
            'Corporate Law' => 'content.spec.corporate',
            'Litigation' => 'content.spec.litigation',
            'Family Law' => 'content.spec.family',
            // Hearing types
            'Preliminary' => 'content.hearing.preliminary',
            'Mediation' => 'content.hearing.mediation',
            'Transfer Hearing' => 'content.hearing.transfer',
            'Commercial hearing' => 'content.appt.commercial_hearing',
            'Commercial Hearing' => 'content.appt.commercial_hearing',
            // Outcomes / hearing notes
            'Title transferred successfully.' => 'content.outcome.title_transferred',
            'Completed without objection.' => 'content.outcome.no_objection',
            'Bring original contracts and witness list.' => 'content.note.bring_contracts',
            'Client to attend with employment file.' => 'content.note.employment_file',
            // Appointments
            'Case Strategy Meeting' => 'content.appt.strategy',
            'Labour Hearing Prep' => 'content.appt.labour_prep',
            'Contract Signing' => 'content.appt.contract_signing',
            'legalisation' => 'content.appt.legalisation',
            'Legalisation' => 'content.appt.legalisation',
            // Appointment / location phrases
            'Review evidence and next steps for commercial dispute.' => 'content.appt.desc.strategy',
            'Prepare client for labour court appearance.' => 'content.appt.desc.labour',
            'Finalize logistics vendor agreement.' => 'content.appt.desc.contract',
            'First hearing - Commercial Circuit.' => 'content.appt.desc.commercial',
            'Lexora Office - Boardroom A' => 'content.loc.boardroom_a',
            'Virtual - Zoom' => 'content.loc.zoom',
            'Lexora Office - Room 2' => 'content.loc.room_2',
            'Dubai Courts' => 'content.loc.dubai_courts',
            // Courts
            'Dubai Courts - Commercial Circuit' => 'content.court.commercial',
            'Labour Court Dubai' => 'content.court.labour',
            'Dubai Land Department' => 'content.court.land',
            'Hon. Judge Al Rashid' => 'content.judge.al_rashid',
            'Hon. Judge Farah' => 'content.judge.farah',
            // Cases
            'Al Maktoum Holdings v. Northwind Corp' => 'content.case.title.1',
            'Vasquez Employment Dispute' => 'content.case.title.2',
            'Sharma Logistics Contract Review' => 'content.case.title.3',
            'Al Maktoum Property Transfer' => 'content.case.title.4',
            'Commercial dispute regarding supply contract breach and damages claim.' => 'content.case.desc.1',
            'Wrongful termination claim and severance negotiation.' => 'content.case.desc.2',
            'Ongoing contract drafting and vendor agreement review.' => 'content.case.desc.3',
            'Real estate title transfer and escrow coordination.' => 'content.case.desc.4',
            // Case notes
            'Initial consultation completed. Client provided supply invoices and correspondence.' => 'content.note.consult_1',
            'Draft statement of claim prepared pending client approval.' => 'content.note.claim_draft',
            'Client requested mediation before formal hearing.' => 'content.note.mediation_request',
            'Vendor agreement template shared with client for review.' => 'content.note.vendor_template',
            // Invoices
            'Retainer - Commercial Dispute' => 'content.inv.title.1',
            'Employment Matter Fees' => 'content.inv.title.2',
            'Contract Drafting Services' => 'content.inv.title.3',
            'Property Transfer Fees' => 'content.inv.title.4',
            'Initial retainer and filing fees' => 'content.inv.desc.1',
            'Consultation and mediation preparation' => 'content.inv.desc.2',
            'Vendor agreement drafting package' => 'content.inv.desc.3',
            'Real estate transfer legal services' => 'content.inv.desc.4',
            // Payments
            'Partial retainer payment' => 'content.pay.partial_retainer',
            'Full payment received' => 'content.pay.full',
            'Final settlement' => 'content.pay.final',
            'Client-recorded payment' => 'content.pay.client_recorded',
            // Messages
            'Evidence package' => 'content.msg.evidence_subject',
            'Re: Evidence package' => 'content.msg.evidence_re',
            'Question about mediation' => 'content.msg.mediation_subject',
            'I have scanned the remaining invoices. When can I upload them?' => 'content.msg.evidence_body',
            'Please upload via the Documents section by Thursday.' => 'content.msg.evidence_reply',
            'Do I need to bring HR witnesses to the mediation session?' => 'content.msg.mediation_body',
            // Misc system / seed
            'Mediation date confirmed for 25 Jul 2026.' => 'content.notify.mediation_confirmed',
            'the original supply contract' => 'content.doc.supply_contract',
            'original supply contract' => 'content.doc.supply_contract',
            'None' => 'content.none',
            'Bank transfer, Card, Cheque' => 'content.settings.payment_methods',
            'Please include the invoice number in the transfer reference.' => 'content.settings.payment_instructions',
        ];
    }

    $trimmed = trim($text);
    if (isset($map[$trimmed])) {
        return __($map[$trimmed]);
    }

    if (preg_match('/^Court document - (.+)$/i', $trimmed, $m)) {
        return __('content.doc.court_prefix', ['name' => $m[1]]);
    }

    return $text;
}

function notify_payload(string $key, array $params = []): string
{
    if ($params === []) {
        return $key;
    }
    return $key . '::' . json_encode($params, JSON_UNESCAPED_UNICODE);
}

function locale_tag(): string
{
    return current_lang() === 'fr' ? 'fr_MU' : 'en_MU';
}

function format_long_date(?int $timestamp = null): string
{
    $timestamp = $timestamp ?? time();
    if (class_exists('IntlDateFormatter')) {
        $fmt = new IntlDateFormatter(locale_tag(), IntlDateFormatter::FULL, IntlDateFormatter::NONE);
        $out = $fmt->format($timestamp);
        if ($out !== false) {
            return $out;
        }
    }
    return date('l, F j, Y', $timestamp);
}

function format_month_short(string $ym): string
{
    $ts = strtotime($ym . '-01');
    if (!$ts) {
        return $ym;
    }
    if (class_exists('IntlDateFormatter')) {
        $fmt = new IntlDateFormatter(locale_tag(), IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'MMM');
        $out = $fmt->format($ts);
        if ($out !== false) {
            return $out;
        }
    }
    return date('M', $ts);
}
