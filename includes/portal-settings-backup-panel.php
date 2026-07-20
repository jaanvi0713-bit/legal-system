<?php
/**
 * Portal backup settings panel.
 *
 * Expects: $portal (lawyer|client), $backupFrequency, $portalBackupIncluded (array of [titleKey, helpKey])
 */
$portalBackupIncluded = $portalBackupIncluded ?? [];
?>
<div class="settings-form backup-settings-page">
    <div class="settings-block">
        <div class="settings-block-head">
            <h3><?= __e('settings.backup.portal.title') ?></h3>
            <p><?= __e($portal === 'lawyer' ? 'settings.backup.portal.help_lawyer' : 'settings.backup.portal.help_client') ?></p>
        </div>
        <div class="backup-actions-grid">
            <form method="post" class="backup-action-card">
                <?= csrf_field() ?>
                <input type="hidden" name="settings_tab" value="backup">
                <input type="hidden" name="backup_action" value="download">
                <h4><?= __e('settings.backup.download_title') ?></h4>
                <p><?= __e($portal === 'lawyer' ? 'settings.backup.portal.download_help_lawyer' : 'settings.backup.portal.download_help_client') ?></p>
                <button class="btn btn-primary btn-sm" type="submit"><?= __e('settings.backup.download_now') ?></button>
            </form>
            <form method="post" class="backup-action-card">
                <?= csrf_field() ?>
                <input type="hidden" name="settings_tab" value="backup">
                <input type="hidden" name="backup_action" value="email">
                <h4><?= __e('settings.backup.email_title') ?></h4>
                <p><?= __e($portal === 'lawyer' ? 'settings.backup.portal.email_help_lawyer' : 'settings.backup.portal.email_help_client') ?></p>
                <button class="btn btn-secondary btn-sm" type="submit"><?= __e('settings.backup.email_now') ?></button>
            </form>
        </div>
    </div>

    <form method="post" class="settings-block">
        <?= csrf_field() ?>
        <input type="hidden" name="settings_tab" value="backup">
        <input type="hidden" name="backup_action" value="schedule">
        <div class="settings-block-head">
            <h3><?= __e('settings.backup.auto_title') ?></h3>
            <p><?= __e('settings.backup.portal.auto_help') ?></p>
        </div>
        <div class="backup-schedule-row">
            <div class="form-group">
                <label for="backup_frequency"><?= __e('settings.backup.frequency') ?></label>
                <select id="backup_frequency" name="backup_frequency">
                    <option value="never" <?= $backupFrequency === 'never' ? 'selected' : '' ?>><?= __e('settings.backup.frequency.never') ?></option>
                    <option value="weekly" <?= $backupFrequency === 'weekly' ? 'selected' : '' ?>><?= __e('settings.backup.frequency.weekly') ?></option>
                    <option value="monthly" <?= $backupFrequency === 'monthly' ? 'selected' : '' ?>><?= __e('settings.backup.frequency.monthly') ?></option>
                </select>
            </div>
            <div class="backup-schedule-action">
                <button class="btn btn-primary" type="submit"><?= __e('settings.backup.save_schedule') ?></button>
            </div>
        </div>
    </form>

    <div class="settings-block">
        <div class="settings-block-head">
            <h3><?= __e('settings.backup.included_title') ?></h3>
            <p><?= __e('settings.backup.portal.included_help') ?></p>
        </div>
        <div class="list-stack">
            <?php foreach ($portalBackupIncluded as [$titleKey, $helpKey]): ?>
                <div class="list-item">
                    <strong><?= __e($titleKey) ?></strong>
                    <span class="muted"><?= __e($helpKey) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
