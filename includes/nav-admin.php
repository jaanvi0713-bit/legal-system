<?php
require_once __DIR__ . '/role-access.php';
require_once __DIR__ . '/nav-render.php';

$user = current_user();
$canSeeAll = ($user['role'] ?? '') === 'admin';
$allowedModules = $canSeeAll ? null : role_access_staff_modules(db(), $user);

$sections = [
    [
        'label' => 'nav.section.overview',
        'items' => [
            ['index.php', 'nav.dashboard', 'dashboard', 'dashboard'],
            ['insights.php', 'nav.insights', 'insights', 'insights'],
        ],
    ],
    [
        'label' => 'nav.section.practice',
        'items' => [
            ['clients.php', 'nav.clients', 'clients', 'clients'],
            ['lawyers.php', 'nav.lawyers', 'lawyers', 'lawyers'],
            ['cases.php', 'nav.cases', 'cases', 'cases'],
            ['appointments.php', 'nav.appointments', 'appointments', 'appointments'],
            ['court.php', 'nav.court', 'court', 'court'],
        ],
    ],
    [
        'label' => 'nav.section.tools',
        'items' => [
            ['notifications.php', 'nav.notifications', 'notifications', 'notifications'],
            ['ai.php', 'nav.ai', 'ai', 'ai'],
        ],
    ],
    [
        'label' => 'nav.section.admin',
        'items' => [
            ['users.php', 'nav.users', 'users', 'users'],
            ['settings.php', 'nav.settings', 'settings', 'settings'],
        ],
    ],
];

render_sidebar_nav($sections, $allowedModules, $activeNav ?? '', $portalBase);
