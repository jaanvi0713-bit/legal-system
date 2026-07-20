<?php
$items = [
    ['index.php', 'nav.dashboard', 'dashboard', 'dashboard'],
    ['insights.php', 'nav.insights', 'insights', 'insights'],
    ['tasks.php', 'nav.tasks', 'tasks', 'tasks'],
    ['cases.php', 'nav.my_cases', 'cases', 'cases'],
    ['clients.php', 'nav.my_clients', 'clients', 'clients'],
    ['contact.php', 'nav.client_messages', 'contact', 'contact'],
    ['appointments.php', 'nav.appointments', 'appointments', 'appointments'],
    ['court.php', 'nav.court', 'court', 'court'],
    ['availability.php', 'nav.availability', 'availability', 'availability'],
    ['ai.php', 'nav.ai', 'ai', 'ai'],
];
foreach ($items as [$href, $labelKey, $key, $icon]):
    $active = ($activeNav ?? '') === $key ? 'active' : '';
?>
<a class="nav-link <?= $active ?>" href="<?= e($portalBase . '/' . $href) ?>">
    <span class="nav-icon"><?= nav_icon($icon) ?></span>
    <span class="nav-label"><?= __e($labelKey) ?></span>
</a>
<?php endforeach; ?>
