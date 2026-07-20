<?php
$items = [
    ['index.php', 'nav.dashboard', 'dashboard', 'dashboard'],
    ['cases.php', 'nav.my_cases', 'cases', 'cases'],
    ['documents.php', 'nav.documents', 'documents', 'documents'],
    ['appointments.php', 'nav.appointments', 'appointments', 'appointments'],
    ['court.php', 'nav.court', 'court', 'court'],
    ['payments.php', 'nav.payments', 'payments', 'payments'],
    ['contact.php', 'nav.contact', 'contact', 'contact'],
    ['notifications.php', 'nav.notifications', 'notifications', 'notifications'],
];
foreach ($items as [$href, $labelKey, $key, $icon]):
    $active = ($activeNav ?? '') === $key ? 'active' : '';
?>
<a class="nav-link <?= $active ?>" href="<?= e($portalBase . '/' . $href) ?>">
    <span class="nav-icon"><?= nav_icon($icon) ?></span>
    <span class="nav-label"><?= __e($labelKey) ?></span>
</a>
<?php endforeach; ?>
