<?php
$items = [
    ['index.php', 'nav.dashboard', 'dashboard', 'dashboard'],
    ['clients.php', 'nav.clients', 'clients', 'clients'],
    ['lawyers.php', 'nav.lawyers', 'lawyers', 'lawyers'],
    ['cases.php', 'nav.cases', 'cases', 'cases'],
    ['appointments.php', 'nav.appointments', 'appointments', 'appointments'],
    ['court.php', 'nav.court', 'court', 'court'],
    ['notifications.php', 'nav.notifications', 'notifications', 'notifications'],
    ['ai.php', 'nav.ai', 'ai', 'ai'],
    ['settings.php', 'nav.settings', 'settings', 'settings'],
    ['users.php', 'nav.users', 'users', 'users'],
];
foreach ($items as [$href, $labelKey, $key, $icon]):
    $active = ($activeNav ?? '') === $key ? 'active' : '';
?>
<a class="nav-link <?= $active ?>" href="<?= e($portalBase . '/' . $href) ?>">
    <span class="nav-icon"><?= nav_icon($icon) ?></span>
    <span class="nav-label"><?= __e($labelKey) ?></span>
</a>
<?php endforeach; ?>
