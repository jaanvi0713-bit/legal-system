<?php
require_once __DIR__ . '/role-access.php';
$user = current_user();
$canSeeAll = ($user['role'] ?? '') === 'admin';
$allowedModules = $canSeeAll ? null : role_access_staff_modules(db(), $user);

$items = [
    ['index.php', 'nav.dashboard', 'dashboard', 'dashboard'],
    ['insights.php', 'nav.insights', 'insights', 'insights'],
    ['clients.php', 'nav.clients', 'clients', 'clients'],
    ['lawyers.php', 'nav.lawyers', 'lawyers', 'lawyers'],
    ['cases.php', 'nav.cases', 'cases', 'cases'],
    ['appointments.php', 'nav.appointments', 'appointments', 'appointments'],
    ['court.php', 'nav.court', 'court', 'court'],
    ['notifications.php', 'nav.notifications', 'notifications', 'notifications'],
    ['ai.php', 'nav.ai', 'ai', 'ai'],
    ['users.php', 'nav.users', 'users', 'users'],
    ['settings.php', 'nav.settings', 'settings', 'settings'],
];
foreach ($items as [$href, $labelKey, $key, $icon]):
    if ($allowedModules !== null && !in_array($key, $allowedModules, true)) {
        continue;
    }
    $active = ($activeNav ?? '') === $key ? 'active' : '';
?>
<a class="nav-link <?= $active ?>" href="<?= e($portalBase . '/' . $href) ?>">
    <span class="nav-icon"><?= nav_icon($icon) ?></span>
    <span class="nav-label"><?= __e($labelKey) ?></span>
</a>
<?php endforeach; ?>
