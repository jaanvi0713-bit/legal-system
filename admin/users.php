<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/role-access.php';
require_role(['admin']);
$pdo = db();
$action = get('action', 'list');
$id = (int) get('id', 0);
$permissionOptions = ['clients', 'lawyers', 'cases', 'appointments', 'court', 'reports', 'notifications'];
$permissionLabel = static function (string $p): string {
    $map = [
        'clients' => 'nav.clients',
        'lawyers' => 'nav.lawyers',
        'cases' => 'nav.cases',
        'appointments' => 'nav.appointments',
        'court' => 'nav.court',
        'reports' => 'page.reports',
        'notifications' => 'nav.notifications',
    ];
    return __($map[$p] ?? $p);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'save') {
        $editId = (int) post('id');
        $role = post('role', 'client');
        $perms = null;
        if ($role === 'staff') {
            $accessRole = post('staff_access_role', 'staff');
            $config = role_access_load($pdo);
            $perm = $config['permissions'][$accessRole] ?? role_access_default_permissions()['staff'];
            $perms = json_encode([
                'access_role' => $accessRole,
                'modules' => array_values($perm['modules'] ?? []),
                'assigned_cases' => !empty($perm['assigned_cases']),
                'read_only' => !empty($perm['read_only']),
            ], JSON_UNESCAPED_UNICODE);
        }

        if ($editId) {
            $pdo->prepare('UPDATE users SET first_name=?, last_name=?, username=?, email=?, phone=?, role=?, permissions=?, is_active=? WHERE id=?')
                ->execute([
                    post('first_name'),
                    post('last_name'),
                    post('username'),
                    post('email'),
                    post('phone'),
                    $role,
                    $perms,
                    (int) (post('is_active') === '1'),
                    $editId,
                ]);
            if (post('password')) {
                $pdo->prepare('UPDATE users SET password=? WHERE id=?')
                    ->execute([password_hash(post('password'), PASSWORD_DEFAULT), $editId]);
            }
            flash('success', __('flash.user.updated'));
        } else {
            $pdo->prepare('INSERT INTO users (role, first_name, last_name, username, email, password, phone, permissions, is_active) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([
                    $role,
                    post('first_name'),
                    post('last_name'),
                    post('username'),
                    post('email'),
                    password_hash(post('password') ?: 'password123', PASSWORD_DEFAULT),
                    post('phone'),
                    $perms,
                    (int) (post('is_active') === '1'),
                ]);
            flash('success', __('flash.user.created'));
        }
        redirect('users.php');
    }
    if ($fa === 'toggle') {
        $pdo->prepare('UPDATE users SET is_active = IF(is_active=1,0,1) WHERE id=?')->execute([(int) post('id')]);
        flash('success', __('flash.user.status'));
        redirect('users.php');
    }
    if ($fa === 'reset') {
        $pdo->prepare('UPDATE users SET password=? WHERE id=?')
            ->execute([password_hash('password123', PASSWORD_DEFAULT), (int) post('id')]);
        flash('success', __('flash.user.password_reset'));
        redirect('users.php');
    }
    if ($fa === 'delete') {
        $deleteId = (int) post('id');
        $currentId = (int) (current_user()['id'] ?? 0);
        if ($deleteId && $deleteId !== $currentId) {
            $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$deleteId]);
            flash('success', __('flash.user.removed'));
        } else {
            flash('error', __('flash.user.cannot_remove_self'));
        }
        redirect('users.php');
    }
}

$pageTitle = __('page.users');
$pageSubtitle = __('page.users.subtitle');
$portal = 'admin';
$activeNav = 'users';

if ($action === 'create' || ($action === 'edit' && $id)) {
    $row = [
        'id' => 0,
        'first_name' => '',
        'last_name' => '',
        'username' => '',
        'email' => '',
        'phone' => '',
        'role' => 'client',
        'is_active' => 1,
        'permissions' => '[]',
    ];
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: $row;
    }
    $permData = json_decode($row['permissions'] ?: '{}', true);
    if (!is_array($permData)) {
        $permData = [];
    }
    $selectedAccessRole = (string) ($permData['access_role'] ?? 'staff');
    $roleAccessConfig = role_access_load($pdo);
    require __DIR__ . '/../includes/header.php';
    $isEdit = (bool) $id;
    ?>
    <div class="entity-form-wrap">
    <div class="entity-form panel">
        <div class="entity-form-hero">
            <div>
                <p class="entity-form-eyebrow"><?= $isEdit ? __e('users.eyebrow.edit') : __e('users.eyebrow.create') ?></p>
                <h2><?= $isEdit ? __e('users.edit') : __e('users.create') ?></h2>
                <p class="muted"><?= $isEdit ? __e('users.form.help.edit') : __e('users.form.help.create') ?></p>
            </div>
            <p class="entity-form-required-note"><span class="req">*</span> <?= __e('form.required_fields') ?></p>
        </div>
        <form method="post" id="user-form">
            <div class="entity-form-body">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="save">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3><?= __e('form.section.profile') ?></h3>
                        <p><?= __e('users.section.profile_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group">
                                <label for="first_name"><?= __e('form.first_name') ?> <span class="req">*</span></label>
                                <input id="first_name" name="first_name" required value="<?= e($row['first_name']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="last_name"><?= __e('form.last_name') ?> <span class="req">*</span></label>
                                <input id="last_name" name="last_name" required value="<?= e($row['last_name']) ?>">
                            </div>
                        </div>
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group">
                                <label for="email"><?= __e('common.email') ?> <span class="req">*</span></label>
                                <input id="email" type="email" name="email" required value="<?= e($row['email']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone"><?= __e('common.phone') ?></label>
                                <input id="phone" name="phone" value="<?= e($row['phone']) ?>">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3><?= __e('form.section.access') ?></h3>
                        <p><?= __e('users.section.access_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row">
                            <div class="form-group">
                                <label for="username"><?= __e('form.username') ?> <span class="req">*</span></label>
                                <input id="username" name="username" required value="<?= e($row['username']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="password"><?= $isEdit ? __e('form.new_password') : __e('form.password') ?><?= $isEdit ? '' : ' <span class="req">*</span>' ?></label>
                                <input id="password" name="password" placeholder="<?= $isEdit ? __e('form.password_keep') : __e('form.password_default') ?>">
                            </div>
                            <div class="form-group">
                                <label for="user-role"><?= __e('common.role') ?> <span class="req">*</span></label>
                                <select name="role" id="user-role" required>
                                    <?php foreach (['admin', 'staff', 'lawyer', 'client'] as $r): ?>
                                        <option value="<?= e($r) ?>" <?= $row['role'] === $r ? 'selected' : '' ?>><?= e(translate_role($r)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group">
                                <label for="is_active"><?= __e('common.status') ?> <span class="req">*</span></label>
                                <select id="is_active" name="is_active" required>
                                    <option value="1" <?= $row['is_active'] ? 'selected' : '' ?>><?= __e('status.active') ?></option>
                                    <option value="0" <?= !$row['is_active'] ? 'selected' : '' ?>><?= __e('users.status.deactivated') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group full entity-field-notes">
                            <span class="field-hint"><?= $isEdit ? __e('form.hint.password_keep') : __e('form.hint.password_default') ?></span>
                        </div>
                        <div class="form-group full" id="staff-permissions" hidden>
                            <label for="staff_access_role"><?= __e('users.staff_access_role') ?></label>
                            <select id="staff_access_role" name="staff_access_role">
                                <?php foreach ($roleAccessConfig['roles'] as $accessRole): ?>
                                    <option value="<?= e($accessRole['id']) ?>" <?= $selectedAccessRole === $accessRole['id'] ? 'selected' : '' ?>><?= e($accessRole['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field-hint"><?= __e('users.staff_access_role_help') ?> <a href="settings.php?tab=roles"><?= __e('settings.tab.roles') ?></a></span>
                        </div>
                    </div>
                </section>
            </div>
            <div class="entity-form-footer">
                <a class="btn btn-secondary" href="users.php"><?= __e('common.cancel') ?></a>
                <button class="btn btn-primary" type="submit"><?= $isEdit ? __e('common.save_changes') : __e('users.create') ?></button>
            </div>
        </form>
    </div>
    </div>
    <script>
    (function () {
      const role = document.getElementById('user-role');
      const perms = document.getElementById('staff-permissions');
      if (!role || !perms) return;
      function sync() { perms.hidden = role.value !== 'staff'; }
      role.addEventListener('change', sync);
      sync();
    })();
    </script>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$filterRaw = strtolower(trim((string) get('role', 'all')));
$filter = in_array($filterRaw, ['admin', 'staff', 'lawyer', 'client'], true) ? $filterRaw : '';
$search = trim((string) get('q', ''));
$perPage = 10;
$page = max(1, (int) get('page', 1));

$usersListUrl = static function (string $roleKey, int $pageNum = 1) use ($search): string {
    $qs = ['role' => $roleKey];
    if ($search !== '') {
        $qs['q'] = $search;
    }
    if ($pageNum > 1) {
        $qs['page'] = $pageNum;
    }
    return 'users.php?' . http_build_query($qs);
};

$where = [];
$params = [];
if ($filter !== '') {
    $where[] = 'role = ?';
    $params[] = $filter;
}
if ($search !== '') {
    $where[] = '(first_name LIKE ? OR last_name LIKE ? OR username LIKE ? OR email LIKE ? OR phone LIKE ? OR CONCAT(first_name, " ", last_name) LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}
$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM users' . $whereSql);
$countStmt->execute($params);
$totalUsers = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($totalUsers / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$sql = 'SELECT * FROM users' . $whereSql . ' ORDER BY FIELD(role, "admin", "staff", "lawyer", "client"), first_name LIMIT ' . $perPage . ' OFFSET ' . $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
$shownFrom = $totalUsers === 0 ? 0 : $offset + 1;
$shownTo = min($offset + count($users), $totalUsers);
$listRoleKey = $filter !== '' ? $filter : 'all';

$currentUserId = (int) (current_user()['id'] ?? 0);
$roleConfig = role_access_load($pdo);
$roleList = $roleConfig['roles'];
$rolePerms = $roleConfig['permissions'];
$roleModules = role_access_modules();
$roleUserCounts = role_access_user_counts($pdo);
$companyName = get_setting($pdo, 'company_name', app_config('name', 'LEGAL PRO'));

require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <div class="panel-header">
        <h2><?= __e('users.list') ?></h2>
        <a class="btn btn-primary btn-sm" href="?action=create"><?= __e('users.create') ?></a>
    </div>
    <div class="users-list-toolbar">
        <form method="get" class="users-list-search-form" role="search">
            <input type="hidden" name="role" value="<?= e($filter !== '' ? $filter : 'all') ?>">
            <label class="appt-list-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                <input type="search" name="q" value="<?= e($search) ?>" placeholder="<?= __e('users.search_placeholder') ?>" autocomplete="off">
            </label>
        </form>
        <div class="users-list-filters">
        <?php
        $filters = [
            'all' => __('users.filter.all'),
            'admin' => __('users.filter.admin'),
            'staff' => __('users.filter.staff'),
            'lawyer' => __('users.filter.lawyer'),
            'client' => __('users.filter.client'),
        ];
        foreach ($filters as $key => $label):
            $href = $usersListUrl($key, 1);
            $isActive = $key === 'all' ? $filter === '' : $filter === $key;
        ?>
            <a class="chip <?= $isActive ? 'active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th><?= __e('users.col.user') ?></th><th><?= __e('common.role') ?></th><th><?= __e('common.access') ?></th><th><?= __e('common.last_login') ?></th><th><?= __e('common.status') ?></th><th><?= __e('common.actions') ?></th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u):
                $perms = $u['role'] === 'staff' ? (json_decode($u['permissions'] ?: '[]', true) ?: []) : [];
            ?>
                <tr>
                    <td>
                        <strong><?= e(full_name($u)) ?></strong>
                        <div class="muted"><?= e($u['username']) ?> · <?= e($u['email']) ?></div>
                    </td>
                    <td><?= e(translate_role($u['role'])) ?></td>
                    <td>
                        <?php if ($u['role'] === 'staff'): ?>
                            <?= e($perms ? implode(', ', array_map($permissionLabel, $perms)) : __('users.access.no_modules')) ?>
                        <?php elseif ($u['role'] === 'admin'): ?>
                            <?= __e('users.access.full') ?>
                        <?php else: ?>
                            <?= __e('users.access.portal') ?>
                        <?php endif; ?>
                    </td>
                    <td><?= e(format_datetime($u['last_login'])) ?></td>
                    <td><?= status_badge($u['is_active'] ? 'active' : 'unavailable') ?></td>
                    <td class="col-actions">
                        <details class="row-actions-dropdown">
                            <summary class="row-actions-toggle" aria-label="<?= __e('common.actions') ?>">
                                <span><?= __e('common.actions') ?></span>
                                <svg class="row-actions-caret" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                            </summary>
                            <div class="row-actions-menu">
                                <a class="row-actions-item" href="?action=edit&id=<?= (int) $u['id'] ?>"><?= __e('common.edit') ?></a>
                                <form method="post"<?= $u['is_active'] ? ' data-confirm="' . __e('confirm.deactivate_user') . '"' : '' ?>>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="form_action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <button class="row-actions-item<?= $u['is_active'] ? ' row-actions-item--danger' : ' row-actions-item--success' ?>" type="submit"><?= $u['is_active'] ? __e('common.deactivate') : __e('common.activate') ?></button>
                                </form>
                                <form method="post" data-confirm="<?= __e('confirm.reset_password') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="form_action" value="reset">
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <button class="row-actions-item" type="submit"><?= __e('common.reset_pw') ?></button>
                                </form>
                                <?php if ((int) $u['id'] !== $currentUserId): ?>
                                    <form method="post" data-confirm="<?= __e('confirm.remove_user') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="form_action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                        <button class="row-actions-item row-actions-item--danger" type="submit"><?= __e('common.remove') ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?>
                <tr><td colspan="6" class="case-empty muted"><?= __e($search !== '' ? 'users.empty.search' : 'users.empty.none') ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="case-list-foot">
        <p class="case-list-footer muted"><?= e(__($totalUsers === 1 ? 'users.pager.showing_one' : 'users.pager.showing_many', ['from' => (int) $shownFrom, 'to' => (int) $shownTo, 'total' => (int) $totalUsers])) ?></p>
        <?php if ($totalPages > 1): ?>
        <nav class="case-list-pager" aria-label="<?= __e('users.pagination.aria') ?>">
            <?php if ($page > 1): ?>
            <a class="case-page-btn" href="<?= e($usersListUrl($listRoleKey, $page - 1)) ?>" aria-label="<?= __e('cases.pagination.prev') ?>">‹</a>
            <?php else: ?>
            <span class="case-page-btn is-disabled" aria-disabled="true">‹</span>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a class="case-page-btn<?= $p === $page ? ' is-active' : '' ?>" href="<?= e($usersListUrl($listRoleKey, $p)) ?>"<?= $p === $page ? ' aria-current="page"' : '' ?>><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a class="case-page-btn" href="<?= e($usersListUrl($listRoleKey, $page + 1)) ?>" aria-label="<?= __e('cases.pagination.next') ?>">›</a>
            <?php else: ?>
            <span class="case-page-btn is-disabled" aria-disabled="true">›</span>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
</div>

<div class="panel users-role-access-panel">
    <?php
    $roleAccessSummariesCustomize = true;
    $roleAccessSummariesShowUsers = false;
    require __DIR__ . '/../includes/role-access-summaries-panel.php';
    ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
