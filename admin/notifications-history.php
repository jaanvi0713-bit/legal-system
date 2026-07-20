<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'staff']);
redirect('notifications.php');
