<?php
require_once __DIR__ . '/nav-render.php';

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
            ['clients.php', 'nav.my_clients', 'clients', 'clients'],
            ['cases.php', 'nav.my_cases', 'cases', 'cases'],
            ['appointments.php', 'nav.appointments', 'appointments', 'appointments'],
            ['court.php', 'nav.court', 'court', 'court'],
            ['tasks.php', 'nav.tasks', 'tasks', 'tasks'],
        ],
    ],
    [
        'label' => 'nav.section.schedule',
        'items' => [
            ['availability.php', 'nav.availability', 'availability', 'availability'],
            ['contact.php', 'nav.client_messages', 'contact', 'contact'],
        ],
    ],
    [
        'label' => 'nav.section.tools',
        'items' => [
            ['notifications.php', 'nav.notifications', 'notifications', 'notifications'],
            ['ai.php', 'nav.ai', 'ai', 'ai'],
        ],
    ],
];

render_sidebar_nav($sections, null, $activeNav ?? '', $portalBase);
