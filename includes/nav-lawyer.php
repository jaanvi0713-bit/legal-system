<?php
$items = [
    ['index.php', 'Dashboard', 'dashboard', 'dashboard'],
    ['tasks.php', 'My Tasks', 'tasks', 'tasks'],
    ['cases.php', 'My Cases', 'cases', 'cases'],
    ['clients.php', 'My Clients', 'clients', 'clients'],
    ['appointments.php', 'Appointments', 'appointments', 'appointments'],
    ['court.php', 'Court Tracking', 'court', 'court'],
    ['availability.php', 'Availability', 'availability', 'availability'],
    ['ai.php', 'AI Assistant', 'ai', 'ai'],
];
foreach ($items as [$href, $label, $key, $icon]):
    $active = ($activeNav ?? '') === $key ? 'active' : '';
?>
<a class="nav-link <?= $active ?>" href="<?= e($portalBase . '/' . $href) ?>">
    <span class="nav-icon"><?= nav_icon($icon) ?></span>
    <span class="nav-label"><?= e($label) ?></span>
</a>
<?php endforeach; ?>
