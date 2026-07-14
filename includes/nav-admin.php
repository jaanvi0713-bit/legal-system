<?php
$items = [
    ['index.php', 'Dashboard', 'dashboard', 'dashboard'],
    ['clients.php', 'Clients', 'clients', 'clients'],
    ['lawyers.php', 'Lawyers', 'lawyers', 'lawyers'],
    ['cases.php', 'Cases', 'cases', 'cases'],
    ['appointments.php', 'Appointments', 'appointments', 'appointments'],
    ['court.php', 'Court Tracking', 'court', 'court'],
    ['staff.php', 'Staff', 'staff', 'staff'],
    ['notifications.php', 'Notifications', 'notifications', 'notifications'],
    ['ai.php', 'AI Assistant', 'ai', 'ai'],
    ['settings.php', 'Settings', 'settings', 'settings'],
    ['users.php', 'User Management', 'users', 'users'],
];
foreach ($items as [$href, $label, $key, $icon]):
    $active = ($activeNav ?? '') === $key ? 'active' : '';
?>
<a class="nav-link <?= $active ?>" href="<?= e($portalBase . '/' . $href) ?>">
    <span class="nav-icon"><?= nav_icon($icon) ?></span>
    <span class="nav-label"><?= e($label) ?></span>
</a>
<?php endforeach; ?>
