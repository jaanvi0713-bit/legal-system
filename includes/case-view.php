<?php
/**
 * Read-only case detail — mirrors the admin case create/edit form sections.
 *
 * Expects: $case (cases row + client_name, lawyer_name optional), $viewBackUrl
 * Optional: $feeItems (from case_fee_items)
 */
$case = $case ?? [];
$viewBackUrl = $viewBackUrl ?? 'cases.php';
$feeItems = $feeItems ?? [];
$dash = __('common.em_dash');

$field = static function (string $label, string $value, bool $full = false) use ($dash): void {
    $isEmpty = trim($value) === '' || $value === $dash;
    echo '<div class="form-group' . ($full ? ' full' : '') . '">';
    echo '<label>' . e($label) . '</label>';
    echo '<input type="text" class="entity-readonly-field' . ($isEmpty ? ' is-empty' : '') . '" value="' . e($isEmpty ? $dash : $value) . '" readonly>';
    echo '</div>';
};

$area = static function (string $label, string $value, int $rows = 4) use ($dash): void {
    $isEmpty = trim($value) === '' || $value === $dash;
    echo '<div class="form-group full">';
    echo '<label>' . e($label) . '</label>';
    echo '<textarea class="entity-readonly-field' . ($isEmpty ? ' is-empty' : '') . '" rows="' . $rows . '" readonly>' . e($isEmpty ? $dash : $value) . '</textarea>';
    echo '</div>';
};

$caseTitle = t_content((string) ($case['title'] ?? ''));
$caseNumber = (string) ($case['case_number'] ?? '');
$clientLabel = trim((string) ($case['client_name'] ?? ''));
if ($clientLabel === '' && !empty($case['client_first'])) {
    $clientLabel = trim(($case['client_first'] ?? '') . ' ' . ($case['client_last'] ?? ''));
}
$lawyerLabel = trim((string) ($case['lawyer_name'] ?? ''));
$nonvatItems = array_values(array_filter($feeItems, static fn($f) => ($f['section'] ?? '') === 'nonvat'));
$vatItems = array_values(array_filter($feeItems, static fn($f) => ($f['section'] ?? '') === 'vat'));
?>
<div class="case-create-page">
    <a class="btn btn-primary btn-sm case-create-back" href="<?= e($viewBackUrl) ?>">← <?= __e('cases.back_to_cases') ?></a>
    <div class="case-create-intro">
        <p class="entity-form-eyebrow" style="margin:0 0 0.35rem;"><?= __e('cases.eyebrow.view') ?></p>
        <h1><?= e($caseNumber !== '' ? $caseNumber : __('page.my_cases')) ?></h1>
        <p><?= e($caseTitle !== '' ? $caseTitle : __('cases.form.help.edit')) ?></p>
        <div class="case-hub-badges" style="margin-top:0.65rem;">
            <?= status_badge((string) ($case['status'] ?? 'open')) ?>
            <?php if (!empty($case['priority'])): ?>
                <?= status_badge((string) $case['priority']) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel case-create-form entity-form--view">
        <section class="case-create-section">
            <div class="case-create-section-head">
                <span class="case-create-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 6V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v1"/><rect x="4" y="6" width="16" height="14" rx="2"/></svg>
                </span>
                <div>
                    <h2><?= __e('cases.form.section.info') ?></h2>
                    <p><?= __e('cases.form.section.info_help') ?></p>
                </div>
            </div>
            <div class="form-grid">
                <?php $field(__('common.case_number'), $caseNumber); ?>
                <?php $field(__('cases.form.case_title'), $caseTitle); ?>
                <?php $field(__('common.type'), (string) ($case['case_type'] ?? $dash)); ?>
                <?php $field(__('common.status'), translate_status((string) ($case['status'] ?? ''))); ?>
                <?php $area(__('common.description'), $case['description'] ? t_content((string) $case['description']) : ''); ?>
            </div>
        </section>

        <section class="case-create-section">
            <div class="case-create-section-head">
                <span class="case-create-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="8" r="3.2"/><circle cx="16" cy="9" r="2.6"/><path d="M3.5 19c1.2-3.2 3.8-4.8 5.5-4.8S13 15.8 14.2 19"/><path d="M14 14.4c1.5-.4 3.2.2 4.5 2.6"/></svg>
                </span>
                <div>
                    <h2><?= __e('cases.form.section.assignment') ?></h2>
                    <p><?= __e('cases.form.section.assignment_help') ?></p>
                </div>
            </div>
            <div class="case-create-grid-2">
                <?php $field(__('common.client'), $clientLabel !== '' ? $clientLabel : $dash); ?>
                <?php $field(__('common.lawyer'), $lawyerLabel !== '' ? $lawyerLabel : __('form.unassigned_simple')); ?>
            </div>
            <?php if (!empty($case['client_email']) || !empty($case['client_phone'])): ?>
            <div class="case-create-grid-2" style="margin-top:0.85rem;">
                <?php $field(__('common.email'), (string) ($case['client_email'] ?? '')); ?>
                <?php $field(__('common.phone'), (string) ($case['client_phone'] ?? '')); ?>
            </div>
            <?php endif; ?>
        </section>

        <section class="case-create-section">
            <div class="case-create-section-head">
                <span class="case-create-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M21 12a8.5 8.5 0 0 1-8.5 8.5H7l-3 2.2V15.5A8.5 8.5 0 1 1 21 12z"/></svg>
                </span>
                <div>
                    <h2><?= __e('cases.form.section.instructions') ?></h2>
                    <p><?= __e('cases.form.section.instructions_help') ?></p>
                </div>
            </div>
            <?php $area(__('cases.form.instructions_label'), !empty($case['client_instructions']) ? t_content((string) $case['client_instructions']) : ''); ?>
        </section>

        <section class="case-create-section">
            <div class="case-create-section-head">
                <span class="case-create-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 21h18M6 21V10M10 21V10M14 21V10M18 21V10M3 10h18M12 4l9 6H3l9-6z"/></svg>
                </span>
                <div>
                    <h2><?= __e('form.section.court_dates') ?></h2>
                    <p><?= __e('form.section.court_dates_help') ?></p>
                </div>
            </div>
            <div class="case-create-grid-2">
                <?php $field(__('form.court_name'), !empty($case['court_name']) ? t_content((string) $case['court_name']) : ''); ?>
                <?php $field(__('common.location'), !empty($case['court_location']) ? t_content((string) $case['court_location']) : ''); ?>
                <?php $field(__('common.filed'), format_date($case['filing_date'] ?? null)); ?>
                <?php $field(__('common.hearing'), format_date($case['next_hearing_date'] ?? null)); ?>
            </div>
        </section>

        <section class="case-create-section">
            <div class="case-create-section-head">
                <span class="case-create-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18M12 14h4"/></svg>
                </span>
                <div>
                    <h2><?= __e('cases.form.section.billing') ?></h2>
                    <p><?= __e('cases.form.section.billing_help') ?></p>
                </div>
            </div>
            <?php $field(__('cases.hub.legal_service') . ' / ' . __('common.total'), money((float) ($case['total_fee'] ?? 0)), true); ?>
            <?php if ($nonvatItems || $vatItems): ?>
            <div class="table-wrap" style="margin-top:0.75rem;">
                <table>
                    <thead><tr><th><?= __e('common.service') ?></th><th><?= __e('cases.form.net_amount') ?></th><th><?= __e('common.total') ?></th></tr></thead>
                    <tbody>
                    <?php foreach (array_merge($nonvatItems, $vatItems) as $fi): ?>
                        <?php
                        $net = (float) ($fi['net_amount'] ?? 0);
                        $rate = (float) ($fi['vat_rate'] ?? 0);
                        $lineTotal = $net + ($net * $rate / 100);
                        ?>
                        <tr>
                            <td><?= e((string) ($fi['description'] ?? $dash)) ?></td>
                            <td><?= e(money($net)) ?></td>
                            <td><?= e(money($lineTotal)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </div>
</div>
