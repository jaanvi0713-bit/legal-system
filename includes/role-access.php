<?php
/**
 * Admin portal role access (permission templates for staff sub-roles).
 */

function role_access_modules(): array
{
    return [
        'dashboard' => 'nav.dashboard',
        'clients' => 'nav.clients',
        'lawyers' => 'nav.lawyers',
        'cases' => 'nav.cases',
        'appointments' => 'nav.appointments',
        'court' => 'nav.court',
        'notifications' => 'nav.notifications',
        'ai' => 'nav.ai',
        'users' => 'page.users',
        'settings' => 'page.settings',
        'profile' => 'settings.tab.profile',
    ];
}

function role_access_scope_keys(): array
{
    return [
        'assigned_cases' => 'settings.roles.scope.assigned_cases',
        'read_only' => 'settings.roles.scope.read_only',
    ];
}

function role_access_builtin_roles(): array
{
    return [
        [
            'id' => 'administrator',
            'name' => 'Administrator',
            'description' => 'settings.roles.builtin.administrator',
            'builtin' => true,
            'sort' => 0,
        ],
        [
            'id' => 'manager',
            'name' => 'Manager',
            'description' => 'settings.roles.builtin.manager',
            'builtin' => true,
            'sort' => 1,
        ],
        [
            'id' => 'staff',
            'name' => 'Staff',
            'description' => 'settings.roles.builtin.staff',
            'builtin' => true,
            'sort' => 2,
        ],
        [
            'id' => 'viewer',
            'name' => 'Viewer',
            'description' => 'settings.roles.builtin.viewer',
            'builtin' => true,
            'sort' => 3,
        ],
    ];
}

function role_access_default_permissions(): array
{
    $all = array_keys(role_access_modules());
    return [
        'administrator' => [
            'modules' => $all,
            'assigned_cases' => false,
            'read_only' => false,
        ],
        'manager' => [
            'modules' => ['dashboard', 'clients', 'lawyers', 'cases', 'appointments', 'court', 'notifications', 'ai', 'profile'],
            'assigned_cases' => false,
            'read_only' => false,
        ],
        'staff' => [
            'modules' => ['dashboard', 'clients', 'cases', 'appointments', 'court', 'notifications', 'profile'],
            'assigned_cases' => true,
            'read_only' => false,
        ],
        'viewer' => [
            'modules' => ['dashboard', 'clients', 'cases', 'appointments', 'court', 'notifications', 'profile'],
            'assigned_cases' => true,
            'read_only' => true,
        ],
    ];
}

function role_access_load(PDO $pdo): array
{
    $raw = get_setting($pdo, 'role_access_config', '');
    $data = $raw !== '' ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        $data = [];
    }

    $roles = $data['roles'] ?? role_access_builtin_roles();
    $permissions = $data['permissions'] ?? role_access_default_permissions();

    $builtinIds = array_column(role_access_builtin_roles(), 'id');
    $existingIds = array_column($roles, 'id');
    foreach (role_access_builtin_roles() as $builtin) {
        if (!in_array($builtin['id'], $existingIds, true)) {
            $roles[] = $builtin;
        }
    }
    foreach (role_access_default_permissions() as $roleId => $perm) {
        if (!isset($permissions[$roleId])) {
            $permissions[$roleId] = $perm;
        }
    }

    usort($roles, static fn(array $a, array $b): int => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));

    return ['roles' => $roles, 'permissions' => $permissions];
}

function role_access_save(PDO $pdo, array $roles, array $permissions): void
{
    set_setting($pdo, 'role_access_config', json_encode([
        'roles' => array_values($roles),
        'permissions' => $permissions,
    ], JSON_UNESCAPED_UNICODE));
}

function role_access_role_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 1));
}

function role_access_role_theme(string $roleId, bool $builtin = true): array
{
    $themes = [
        'administrator' => ['color' => '#5b21b6', 'icon' => 'shield'],
        'manager' => ['color' => '#0369a1', 'icon' => 'hierarchy'],
        'staff' => ['color' => '#0f766e', 'icon' => 'desktop'],
        'viewer' => ['color' => '#64748b', 'icon' => 'eye'],
    ];
    if (isset($themes[$roleId])) {
        return $themes[$roleId];
    }
    return ['color' => $builtin ? '#023e8a' : '#0f766e', 'icon' => 'tag'];
}

function role_access_role_icon_svg(string $icon): string
{
    return match ($icon) {
        'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 4v6c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V7l8-4z"/></svg>',
        'hierarchy' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="5" rx="1"/><rect x="3" y="16" width="6" height="5" rx="1"/><rect x="15" y="16" width="6" height="5" rx="1"/><path d="M12 8v3M6 16v-2h12v2"/></svg>',
        'desktop' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>',
        'eye' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>',
        default => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><circle cx="7" cy="7" r="1.5"/></svg>',
    };
}

function role_access_find_role_index(array $roles, string $roleId): int
{
    foreach ($roles as $i => $role) {
        if (($role['id'] ?? '') === $roleId) {
            return $i;
        }
    }
    return -1;
}

function role_access_delete_role(array $config, string $roleId): array
{
    $idx = role_access_find_role_index($config['roles'], $roleId);
    if ($idx < 0 || !empty($config['roles'][$idx]['builtin'])) {
        return $config;
    }
    array_splice($config['roles'], $idx, 1);
    unset($config['permissions'][$roleId]);
    return $config;
}

function role_access_duplicate_role(array $config, string $roleId): array
{
    $idx = role_access_find_role_index($config['roles'], $roleId);
    if ($idx < 0) {
        return $config;
    }
    $source = $config['roles'][$idx];
    $name = trim($source['name'] ?? 'Role') . ' copy';
    $description = (string) ($source['description'] ?? '');
    return role_access_add_custom_role($config, $name, $description, $roleId);
}

function role_access_move_role(array $config, string $roleId, string $direction): array
{
    $idx = role_access_find_role_index($config['roles'], $roleId);
    if ($idx < 0) {
        return $config;
    }
    $swap = $direction === 'left' ? $idx - 1 : $idx + 1;
    if ($swap < 0 || $swap >= count($config['roles'])) {
        return $config;
    }
    $roles = $config['roles'];
    [$roles[$idx], $roles[$swap]] = [$roles[$swap], $roles[$idx]];
    foreach ($roles as $i => &$role) {
        $role['sort'] = $i;
    }
    unset($role);
    $config['roles'] = $roles;
    return $config;
}

function role_access_rename_role(array $config, string $roleId, string $name, string $description = ''): array
{
    $idx = role_access_find_role_index($config['roles'], $roleId);
    if ($idx < 0 || $name === '') {
        return $config;
    }
    $config['roles'][$idx]['name'] = $name;
    if ($description !== '' || empty($config['roles'][$idx]['builtin'])) {
        $config['roles'][$idx]['description'] = $description;
    }
    return $config;
}

function role_access_user_label(int $count, bool $short = true): string
{
    if ($count === 0) {
        return __($short ? 'settings.roles.no_users_short' : 'settings.roles.no_users');
    }
    if ($count === 1) {
        return __('settings.roles.one_user_short');
    }
    return __('settings.roles.users_count_short', ['n' => (string) $count]);
}

function role_access_user_counts(PDO $pdo): array
{
    $counts = [];
    $stmt = $pdo->query("SELECT permissions FROM users WHERE role = 'staff' AND is_active = 1");
    while ($row = $stmt->fetch()) {
        $perms = json_decode($row['permissions'] ?: '{}', true);
        if (!is_array($perms)) {
            $accessRole = 'staff';
        } elseif ($perms !== [] && array_keys($perms) === range(0, count($perms) - 1)) {
            $accessRole = 'staff';
        } else {
            $accessRole = (string) ($perms['access_role'] ?? 'staff');
        }
        if ($accessRole === '') {
            $accessRole = 'staff';
        }
        $counts[$accessRole] = ($counts[$accessRole] ?? 0) + 1;
    }
    return $counts;
}

function role_access_add_custom_role(array $config, string $name, string $description, string $copyFrom): array
{
    $id = 'custom_' . bin2hex(random_bytes(4));
    $maxSort = 0;
    foreach ($config['roles'] as $role) {
        $maxSort = max($maxSort, (int) ($role['sort'] ?? 0));
    }
    $config['roles'][] = [
        'id' => $id,
        'name' => $name,
        'description' => $description,
        'builtin' => false,
        'sort' => $maxSort + 1,
    ];
    $source = $config['permissions'][$copyFrom] ?? role_access_default_permissions()['staff'];
    $config['permissions'][$id] = [
        'modules' => array_values($source['modules'] ?? []),
        'assigned_cases' => !empty($source['assigned_cases']),
        'read_only' => !empty($source['read_only']),
    ];
    return $config;
}

function role_access_parse_post(array $post, array $config): array
{
    $roles = $config['roles'];
    $permissions = [];
    $moduleKeys = array_keys(role_access_modules());
    $scopeKeys = array_keys(role_access_scope_keys());

    foreach ($roles as $role) {
        $roleId = $role['id'];
        $modules = [];
        foreach ($moduleKeys as $module) {
            if (($post['perm'][$roleId][$module] ?? '0') === '1') {
                $modules[] = $module;
            }
        }
        $permissions[$roleId] = [
            'modules' => $modules,
            'assigned_cases' => ($post['scope'][$roleId]['assigned_cases'] ?? '0') === '1',
            'read_only' => ($post['scope'][$roleId]['read_only'] ?? '0') === '1',
        ];
    }

    return ['roles' => $roles, 'permissions' => $permissions];
}

function role_access_effective_modules(array $permissions, string $roleId): array
{
    return array_values($permissions[$roleId]['modules'] ?? []);
}

function role_access_staff_modules(PDO $pdo, array $user): array
{
    if (($user['role'] ?? '') === 'admin') {
        return array_keys(role_access_modules());
    }
    if (($user['role'] ?? '') !== 'staff') {
        return [];
    }
    $perms = json_decode($user['permissions'] ?: '{}', true);
    if (!is_array($perms)) {
        $perms = [];
    }
    if (!empty($perms['modules']) && is_array($perms['modules'])) {
        return array_values($perms['modules']);
    }
    $accessRole = (string) ($perms['access_role'] ?? 'staff');
    $config = role_access_load($pdo);
    return role_access_effective_modules($config['permissions'], $accessRole);
}

function role_access_staff_read_only(PDO $pdo, array $user): bool
{
    if (($user['role'] ?? '') !== 'staff') {
        return false;
    }
    $perms = json_decode($user['permissions'] ?: '{}', true);
    if (!is_array($perms)) {
        $perms = [];
    }
    if (isset($perms['read_only'])) {
        return (bool) $perms['read_only'];
    }
    $accessRole = (string) ($perms['access_role'] ?? 'staff');
    $config = role_access_load($pdo);
    return !empty($config['permissions'][$accessRole]['read_only']);
}

function role_access_staff_assigned_cases_only(PDO $pdo, array $user): bool
{
    if (($user['role'] ?? '') !== 'staff') {
        return false;
    }
    $perms = json_decode($user['permissions'] ?: '{}', true);
    if (!is_array($perms)) {
        $perms = [];
    }
    if (isset($perms['assigned_cases'])) {
        return (bool) $perms['assigned_cases'];
    }
    $accessRole = (string) ($perms['access_role'] ?? 'staff');
    $config = role_access_load($pdo);
    return !empty($config['permissions'][$accessRole]['assigned_cases']);
}
