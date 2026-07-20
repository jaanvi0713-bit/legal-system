<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
redirect('settings.php?tab=profile');
