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
        'label' => 'nav.section.my_matter',
        'items' => [
            ['cases.php', 'nav.my_cases', 'cases', 'cases'],
            ['appointments.php', 'nav.appointments', 'appointments', 'appointments'],
            ['court.php', 'nav.court', 'court', 'court'],
            ['documents.php', 'nav.documents', 'documents', 'documents'],
            ['payments.php', 'nav.payments', 'payments', 'payments'],
        ],
    ],
    [
        'label' => 'nav.section.tools',
        'items' => [
            ['notifications.php', 'nav.notifications', 'notifications', 'notifications'],
            ['contact.php', 'nav.contact', 'contact', 'contact'],
            ['ai.php', 'nav.ai', 'ai', 'ai'],
        ],
    ],
];

render_sidebar_nav($sections, null, $activeNav ?? '', $portalBase);
