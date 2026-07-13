<?php
$items = [
    ['index.php', 'Dashboard', 'dashboard'],
    ['cases.php', 'My Cases', 'cases'],
    ['clients.php', 'Clients', 'clients'],
    ['appointments.php', 'Appointments', 'appointments'],
    ['court.php', 'Court Tracking', 'court'],
    ['documents.php', 'Documents', 'documents'],
    ['notifications.php', 'Notifications', 'notifications'],
    ['ai.php', 'AI Assistant', 'ai'],
    ['profile.php', 'Profile', 'profile'],
];
foreach ($items as [$href, $label, $key]):
    $active = ($activeNav ?? '') === $key ? 'active' : '';
?>
<a class="nav-link <?= $active ?>" href="<?= e($portalBase . '/' . $href) ?>"><?= e($label) ?></a>
<?php endforeach; ?>
