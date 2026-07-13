<?php
$items = [
    ['index.php', 'Dashboard', 'dashboard'],
    ['clients.php', 'Clients', 'clients'],
    ['lawyers.php', 'Lawyers', 'lawyers'],
    ['cases.php', 'Cases', 'cases'],
    ['appointments.php', 'Appointments', 'appointments'],
    ['court.php', 'Court Tracking', 'court'],
    ['finance.php', 'Finance', 'finance'],
    ['staff.php', 'Staff', 'staff'],
    ['reports.php', 'Reports', 'reports'],
    ['notifications.php', 'Notifications', 'notifications'],
    ['ai.php', 'AI Assistant', 'ai'],
    ['settings.php', 'Settings', 'settings'],
    ['users.php', 'User Management', 'users'],
];
foreach ($items as [$href, $label, $key]):
    $active = ($activeNav ?? '') === $key ? 'active' : '';
?>
<a class="nav-link <?= $active ?>" href="<?= e($portalBase . '/' . $href) ?>"><?= e($label) ?></a>
<?php endforeach; ?>
