<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['client']);
redirect(app_config('url') . '/client/index.php');
