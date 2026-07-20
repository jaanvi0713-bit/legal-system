<?php
/**
 * Read-only client detail — mirrors the admin client entity form.
 *
 * Expects: $client (users row), $viewBackUrl
 * Optional: $clientCases (array), $clientLawyerName, $viewMailto
 */
$client = $client ?? [];
$viewBackUrl = $viewBackUrl ?? 'clients.php';
$clientCases = $clientCases ?? null;
$clientLawyerName = $clientLawyerName ?? '';
$viewMailto = $viewMailto ?? '';
$dash = __('common.em_dash');

$field = static function (string $label, string $value, bool $full = false) use ($dash): void {
    $isEmpty = trim($value) === '' || $value === $dash;
    echo '<div class="form-group' . ($full ? ' full' : '') . '">';
    echo '<label>' . e($label) . '</label>';
    echo '<input type="text" class="entity-readonly-field' . ($isEmpty ? ' is-empty' : '') . '" value="' . e($isEmpty ? $dash : $value) . '" readonly>';
    echo '</div>';
};

$area = static function (string $label, string $value) use ($dash): void {
    $isEmpty = trim($value) === '' || $value === $dash;
    echo '<div class="form-group full">';
    echo '<label>' . e($label) . '</label>';
    echo '<textarea class="entity-readonly-field' . ($isEmpty ? ' is-empty' : '') . '" rows="3" readonly>' . e($isEmpty ? $dash : $value) . '</textarea>';
    echo '</div>';
};
?>
<div class="entity-form-wrap">
<div class="entity-form panel entity-form--view">
    <div class="entity-form-hero entity-form-hero--view">
        <div class="entity-form-hero-lead">
            <p class="entity-form-eyebrow"><?= __e('clients.eyebrow.view') ?></p>
            <h2><?= e(full_name($client)) ?></h2>
            <p class="muted entity-form-hero-sub">
                <?= e(($client['company_name'] ?? '') !== '' ? $client['company_name'] : __('clients.individual')) ?>
                <?php if ($clientLawyerName !== ''): ?>
                    · <?= __e('clients.lawyer_label') ?> <?= e($clientLawyerName) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="entity-form-hero-side">
            <?= status_badge(!empty($client['is_active']) ? 'active' : 'pending') ?>
            <?php if ($viewMailto !== ''): ?>
            <a class="btn btn-primary btn-sm" href="mailto:<?= e($viewMailto) ?>"><?= __e('lawyer.clients.contact') ?></a>
            <?php endif; ?>
        </div>
    </div>

    <div class="entity-form-body">
        <section class="entity-section">
            <div class="entity-section-head">
                <h3><?= __e('form.section.personal') ?></h3>
                <p><?= __e('clients.section.personal_help') ?></p>
            </div>
            <div class="form-grid">
                <div class="entity-field-row entity-field-row--2">
                    <?php $field(__('form.first_name'), (string) ($client['first_name'] ?? '')); ?>
                    <?php $field(__('form.last_name'), (string) ($client['last_name'] ?? '')); ?>
                </div>
                <div class="entity-field-row entity-field-row--2">
                    <?php $field(__('common.email'), (string) ($client['email'] ?? '')); ?>
                    <?php $field(__('common.phone'), (string) ($client['phone'] ?? '')); ?>
                </div>
                <?php $area(__('common.address'), (string) ($client['address'] ?? '')); ?>
            </div>
        </section>

        <section class="entity-section">
            <div class="entity-section-head">
                <h3><?= __e('form.section.account') ?></h3>
                <p><?= __e('clients.section.account_help') ?></p>
            </div>
            <div class="form-grid">
                <div class="entity-field-row entity-field-row--2">
                    <?php $field(__('form.username'), (string) ($client['username'] ?? '')); ?>
                    <?php $field(__('form.account_status'), !empty($client['is_active']) ? __('status.active') : __('form.inactive_pending')); ?>
                </div>
            </div>
        </section>

        <section class="entity-section">
            <div class="entity-section-head">
                <h3><?= __e('form.section.firm_assignment') ?></h3>
                <p><?= __e('clients.section.firm_help') ?></p>
            </div>
            <div class="form-grid">
                <div class="entity-field-row entity-field-row--2">
                    <?php $field(__('form.company'), (string) ($client['company_name'] ?? '')); ?>
                    <?php $field(__('form.assigned_lawyer'), $clientLawyerName !== '' ? $clientLawyerName : __('form.unassigned')); ?>
                </div>
                <?php $area(__('form.notes_history'), (string) ($client['notes'] ?? '')); ?>
            </div>
        </section>
    </div>

    <div class="entity-form-footer">
        <a class="btn btn-secondary" href="<?= e($viewBackUrl) ?>"><?= __e('common.back') ?></a>
    </div>
</div>
</div>

<?php if (is_array($clientCases)): ?>
<div class="panel" style="margin-top:1rem;">
    <h2><?= __e('clients.cases') ?></h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th><?= __e('common.case_number') ?></th><th><?= __e('common.title') ?></th><th><?= __e('common.status') ?></th><th><?= __e('common.filed') ?></th></tr></thead>
            <tbody>
            <?php foreach ($clientCases as $c): ?>
                <tr>
                    <td><a href="cases.php?action=view&id=<?= (int) $c['id'] ?>"><?= e($c['case_number']) ?></a></td>
                    <td><?= e(t_content($c['title'] ?? '')) ?></td>
                    <td><?= status_badge((string) ($c['status'] ?? '')) ?></td>
                    <td><?= e(format_date($c['filing_date'] ?? null)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$clientCases): ?>
                <tr><td colspan="4" class="muted"><?= __e('common.no_records') ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
