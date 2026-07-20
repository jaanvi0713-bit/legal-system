<?php
/**
 * Read-only hearing detail — mirrors the hearing edit form (entity-form) as a
 * professional, non-editable form.
 *
 * Expects: $row (hearing with case_number, title, lawyer_name), $viewBackUrl
 */
$row = $row ?? [];
$viewBackUrl = $viewBackUrl ?? 'court.php';
$hearingTs = strtotime((string) ($row['hearing_date'] ?? ''));
$hearingWhen = $hearingTs ? date('l, j F Y · g:i A', $hearingTs) : __('common.em_dash');
$caseLabel = trim(($row['case_number'] ?? '') . ($row['title'] ? ' — ' . $row['title'] : ''));
$statusRaw = strtolower((string) ($row['status'] ?? 'scheduled'));
$hearingType = trim((string) ($row['hearing_type'] ?? ''));
$heroTitle = $hearingType !== '' ? t_content($hearingType) : __('court.hearings');
$dash = __('common.em_dash');
$viewRequestApptUrl = $viewRequestApptUrl ?? '';

/** Render a read-only field; marks empties for lighter styling. */
$field = static function (string $label, string $value, bool $full = false) use ($dash): void {
    $isEmpty = trim($value) === '' || $value === $dash;
    echo '<div class="form-group' . ($full ? ' full' : '') . '">';
    echo '<label>' . e($label) . '</label>';
    echo '<input type="text" class="entity-readonly-field' . ($isEmpty ? ' is-empty' : '') . '" value="' . e($isEmpty ? $dash : $value) . '" readonly>';
    echo '</div>';
};
?>
<div class="entity-form-wrap">
<div class="entity-form panel entity-form--view">
    <div class="entity-form-hero entity-form-hero--view">
        <div class="entity-form-hero-lead">
            <p class="entity-form-eyebrow"><?= __e('court.eyebrow.view') ?></p>
            <h2><?= e($heroTitle) ?></h2>
            <?php if ($caseLabel !== ''): ?>
            <p class="muted entity-form-hero-sub"><?= e($caseLabel) ?></p>
            <?php endif; ?>
        </div>
        <div class="entity-form-hero-side">
            <?= hearing_list_status_badge($statusRaw) ?>
            <?php if ($viewRequestApptUrl !== ''): ?>
            <a class="btn btn-primary btn-sm" href="<?= e($viewRequestApptUrl) ?>"><?= __e('client.appointments.request') ?></a>
            <?php endif; ?>
        </div>
    </div>
    <div class="entity-form-body">
        <section class="entity-section">
            <div class="entity-section-head">
                <h3><?= __e('court.section.hearing_details') ?></h3>
                <p><?= __e('court.section.hearing_details_help') ?></p>
            </div>
            <div class="form-grid">
                <?php $field(__('common.case'), $caseLabel !== '' ? $caseLabel : $dash, true); ?>
                <?php $field(__('common.lawyer'), (string) ($row['lawyer_name'] ?? $dash), true); ?>
                <div class="hearing-schedule-grid">
                    <?php $field(__('form.hearing_date'), $hearingWhen); ?>
                    <div class="form-group">
                        <label><?= __e('common.status') ?></label>
                        <div class="entity-readonly-status">
                            <?= hearing_list_status_badge($statusRaw) ?>
                        </div>
                    </div>
                    <?php $field(__('form.hearing_type'), $hearingType !== '' ? t_content($hearingType) : $dash); ?>
                </div>
            </div>
        </section>

        <section class="entity-section">
            <div class="entity-section-head">
                <h3><?= __e('court.section.court_info') ?></h3>
                <p><?= __e('court.section.court_info_help') ?></p>
            </div>
            <div class="form-grid">
                <div class="hearing-court-grid">
                    <?php $field(__('form.court_name'), t_content((string) ($row['court_name'] ?? '')) ?: $dash); ?>
                    <?php $field(__('common.location'), t_content((string) ($row['court_location'] ?? '')) ?: $dash); ?>
                    <?php $field(__('form.judge'), t_content((string) ($row['judge_name'] ?? '')) ?: $dash); ?>
                </div>
                <div class="form-group full">
                    <label><?= __e('form.outcome') ?></label>
                    <?php $outcomeEmpty = trim((string) ($row['outcome'] ?? '')) === ''; ?>
                    <textarea rows="2" class="entity-readonly-field<?= $outcomeEmpty ? ' is-empty' : '' ?>" readonly><?= e($outcomeEmpty ? __('court.outcome_pending') : t_content($row['outcome'])) ?></textarea>
                </div>
                <div class="form-group full">
                    <label><?= __e('form.court_notes') ?></label>
                    <?php $notesEmpty = trim((string) ($row['notes'] ?? '')) === ''; ?>
                    <textarea rows="3" class="entity-readonly-field<?= $notesEmpty ? ' is-empty' : '' ?>" readonly><?= e($notesEmpty ? __('common.no_records') : t_content($row['notes'])) ?></textarea>
                </div>
            </div>
        </section>
    </div>
    <div class="entity-form-footer">
        <a class="btn btn-secondary" href="<?= e($viewBackUrl) ?>"><?= __e('common.back') ?></a>
    </div>
</div>
</div>
