<?php
/**
 * Role access summary cards.
 *
 * Expects: $roleList, $rolePerms, $roleModules, $roleUserCounts, $companyName
 * Optional: $roleAccessSummariesCustomize (bool), $roleAccessSummariesShowUsers (bool)
 */
$roleAccessSummariesCustomize = $roleAccessSummariesCustomize ?? false;
$roleAccessSummariesShowUsers = $roleAccessSummariesShowUsers ?? true;
?>
<div class="role-access-summaries-panel">
    <?php if ($roleAccessSummariesCustomize): ?>
        <div class="panel-header role-access-summaries-head">
            <h2><?= __e('users.role_access.title', ['company' => $companyName]) ?></h2>
            <a class="btn btn-primary btn-sm" href="settings.php?tab=roles"><?= __e('users.role_access.customize') ?></a>
        </div>
    <?php else: ?>
        <div class="settings-block-head">
            <h3><?= __e('settings.roles.summaries') ?></h3>
        </div>
    <?php endif; ?>
    <div class="role-access-summaries">
        <?php foreach ($roleList as $role):
            $rid = $role['id'];
            $perm = $rolePerms[$rid] ?? [];
            $count = (int) ($roleUserCounts[$rid] ?? 0);
            $descKey = $role['description'] ?? '';
            if ($descKey !== '' && str_starts_with($descKey, 'settings.')) {
                $desc = __($descKey);
            } elseif ($descKey !== '') {
                $desc = (string) $descKey;
            } elseif (empty($role['builtin'])) {
                $desc = __('settings.roles.custom_description');
            } else {
                $desc = '';
            }
            $theme = role_access_role_theme($rid, !empty($role['builtin']));
            $moduleLabels = [];
            foreach ($roleModules as $moduleKey => $moduleLabelKey) {
                if (in_array($moduleKey, $perm['modules'] ?? [], true)) {
                    $moduleLabels[] = __($moduleLabelKey);
                }
            }
            if ($roleAccessSummariesShowUsers) {
                if ($count === 0) {
                    $userLabel = __e('settings.roles.no_users');
                } elseif ($count === 1) {
                    $userLabel = __e('settings.roles.one_user');
                } else {
                    $userLabel = __e('settings.roles.users_count', ['n' => (string) $count]);
                }
            }
            ?>
            <div class="role-access-summary-card">
                <div class="role-access-summary-head">
                    <span class="ras-icon" style="--rah-color: <?= e($theme['color']) ?>"><?= e(role_access_role_initials($role['name'])) ?></span>
                    <strong><?= e($role['name']) ?></strong>
                </div>
                <?php if ($desc !== ''): ?>
                    <p class="ras-desc"><?= e($desc) ?></p>
                <?php endif; ?>
                <?php if ($moduleLabels !== []): ?>
                    <div class="ras-access">
                        <span class="ras-access-label"><?= __e('users.role_access.access_label') ?></span>
                        <p class="ras-access-list"><?= e(implode(', ', $moduleLabels)) ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($roleAccessSummariesShowUsers): ?>
                    <div class="ras-users"><?= e($userLabel) ?></div>
                <?php endif; ?>
                <div class="role-access-badges">
                    <?php if (!empty($perm['assigned_cases'])): ?>
                        <span class="role-access-badge role-access-badge--assigned"><?= __e('settings.roles.badge.assigned') ?></span>
                    <?php endif; ?>
                    <?php if (!empty($perm['read_only'])): ?>
                        <span class="role-access-badge role-access-badge--muted"><?= __e('settings.roles.badge.readonly') ?></span>
                    <?php else: ?>
                        <span class="role-access-badge role-access-badge--edit"><?= __e('settings.roles.badge.edit') ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
