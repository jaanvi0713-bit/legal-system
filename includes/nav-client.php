<?php
$items = [
    ['index.php', 'Dashboard', 'dashboard'],
    ['cases.php', 'My Cases', 'cases'],
    ['documents.php', 'Documents', 'documents'],
    ['appointments.php', 'Appointments', 'appointments'],
    ['payments.php', 'Payments', 'payments'],
    ['contact.php', 'Contact Lawyer', 'contact'],
    ['notifications.php', 'Notifications', 'notifications'],
    ['ai.php', 'AI Assistant', 'ai'],
];
foreach ($items as [$href, $label, $key]):
    $active = ($activeNav ?? '') === $key ? 'active' : '';
?>
<a class="nav-link <?= $active ?>" href="<?= e($portalBase . '/' . $href) ?>"><?= e($label) ?></a>
<?php endforeach; ?>
