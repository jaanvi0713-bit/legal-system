<?php
/**
 * Compatibility redirect: old /finance.php bookmarks → admin finance hub.
 */
require_once __DIR__ . '/includes/auth.php';
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? '?' . $_SERVER['QUERY_STRING']
    : '';
redirect(rtrim((string) app_config('url'), '/') . '/admin/finance.php' . $qs);
