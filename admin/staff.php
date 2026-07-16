<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'users.php' . ($query !== '' ? '?' . $query : '');
redirect($target);
