<?php
$items = [
    ['index.php', 'Dashboard', 'dashboard', 'dashboard'],
    ['cases.php', 'My Cases', 'cases', 'cases'],
    ['documents.php', 'Documents', 'documents', 'documents'],
    ['appointments.php', 'Appointments', 'appointments', 'appointments'],
    ['payments.php', 'Payments', 'payments', 'payments'],
    ['contact.php', 'Contact Lawyer', 'contact', 'contact'],
    ['notifications.php', 'Notifications', 'notifications', 'notifications'],
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
