<?php
/**
 * Shared appointment create/edit entity form.
 *
 * Expects: $row, $isEdit, $clients, $lawyers, $cases, $formCancelUrl
 * Optional: $apptFormConfig array
 */
$apptFormConfig = array_merge([
    'showStatus' => true,
    'showClient' => true,
    'showLawyer' => true,
    'lockClientId' => null,
    'lockLawyerId' => null,
    'types' => ['meeting', 'consultation', 'hearing', 'other'],
    'createLabel' => __('appointments.create'),
    'editLabel' => __('common.save'),
    'createHelp' => __('appointments.form.help.create'),
    'editHelp' => __('appointments.form.help.edit'),
    'eyebrow' => null,
    'title' => null,
    'formAction' => 'save',
    'pairTitleType' => false,
    'pairCaseWithParty' => false,
], $apptFormConfig ?? []);

$apptFormEyebrow = $apptFormConfig['eyebrow'] ?? ($isEdit ? __('appointments.eyebrow.edit') : __('appointments.eyebrow.create'));
$apptFormTitle = $apptFormConfig['title'] ?? ($isEdit ? __('appointments.edit') : __('appointments.create'));

$showStatus = (bool) $apptFormConfig['showStatus'];
$showClient = (bool) $apptFormConfig['showClient'];
$showLawyer = (bool) $apptFormConfig['showLawyer'];
$lockClientId = $apptFormConfig['lockClientId'];
$lockLawyerId = $apptFormConfig['lockLawyerId'];
$types = (array) $apptFormConfig['types'];

$apptLawyerIdForSlots = (int) ($apptAvailabilityLawyerId ?? $lockLawyerId ?? ($row['lawyer_id'] ?? 0));
$apptEditId = (int) ($row['id'] ?? 0);
$apptScheduleDate = '';
$apptScheduleTime = '';
if (!empty($row['scheduled_at'])) {
    $schedTs = strtotime((string) $row['scheduled_at']);
    if ($schedTs) {
        $apptScheduleDate = date('Y-m-d', $schedTs);
        $apptScheduleTime = date('H:i', $schedTs);
    }
}
$apptHasLawyerSelect = $showLawyer && $lockLawyerId === null;
$apptDateMin = $isEdit ? '' : date('Y-m-d');
?>
<div class="entity-form-wrap">
<div class="entity-form panel">
    <div class="entity-form-hero">
        <div>
            <p class="entity-form-eyebrow"><?= e($apptFormEyebrow) ?></p>
            <h2><?= e($apptFormTitle) ?></h2>
            <p class="muted"><?= $isEdit ? e($apptFormConfig['editHelp']) : e($apptFormConfig['createHelp']) ?></p>
        </div>
        <p class="entity-form-required-note"><span class="req">*</span> <?= __e('form.required_fields') ?></p>
    </div>
    <form method="post">
        <div class="entity-form-body">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="<?= e($apptFormConfig['formAction']) ?>">
            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <?php if ($lockClientId !== null): ?>
                <input type="hidden" name="client_id" value="<?= (int) $lockClientId ?>">
            <?php endif; ?>
            <?php if ($lockLawyerId !== null): ?>
                <input type="hidden" name="lawyer_id" value="<?= (int) $lockLawyerId ?>">
            <?php endif; ?>

            <section class="entity-section">
                <div class="entity-section-head">
                    <h3><?= __e('appointments.section.details') ?></h3>
                    <p><?= __e('appointments.section.details_help') ?></p>
                </div>
                <?php
                $apptTitleField = '<div class="form-group"><label for="title">' . __e('common.title') . ' <span class="req">*</span></label>'
                    . '<input id="title" name="title" required value="' . e($row['title']) . '"></div>';
                ob_start(); ?>
                <div class="form-group">
                    <label for="appointment_type"><?= __e('common.type') ?> <span class="req">*</span></label>
                    <select id="appointment_type" name="appointment_type" required>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= e($t) ?>" <?= ($row['appointment_type'] ?? '') === $t ? 'selected' : '' ?>><?= e(__('appointment.type.' . $t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php $apptTypeField = ob_get_clean(); ?>
                <div class="form-grid">
                    <?php if ($apptFormConfig['pairTitleType'] && !$showStatus): ?>
                    <div class="entity-field-row entity-field-row--2">
                        <?= $apptTitleField ?>
                        <?= $apptTypeField ?>
                    </div>
                    <?php else: ?>
                    <div class="form-group full">
                        <label for="title"><?= __e('common.title') ?> <span class="req">*</span></label>
                        <input id="title" name="title" required value="<?= e($row['title']) ?>">
                    </div>
                    <div class="entity-field-row entity-field-row--2">
                        <?= $apptTypeField ?>
                        <?php if ($showStatus): ?>
                        <div class="form-group">
                            <label for="status"><?= __e('common.status') ?> <span class="req">*</span></label>
                            <select id="status" name="status" required>
                                <?php foreach (appointment_statuses() as $s): ?>
                                    <option value="<?= e($s) ?>" <?= normalize_appointment_status((string) ($row['status'] ?? '')) === $s ? 'selected' : '' ?>><?= e(translate_status($s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="form-group full">
                        <label for="description"><?= __e('common.description') ?></label>
                        <textarea id="description" name="description" rows="2"><?= e($row['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </section>

            <section class="entity-section">
                <div class="entity-section-head">
                    <h3><?= __e('appointments.section.schedule') ?></h3>
                    <p><?= __e('appointments.section.schedule_help') ?></p>
                </div>
                <?php
                $apptClientVisible = $showClient && $lockClientId === null;
                $apptLawyerVisible = $showLawyer && $lockLawyerId === null;
                ob_start(); ?>
                <div class="form-group">
                    <label for="client_id"><?= __e('common.client') ?></label>
                    <select id="client_id" name="client_id">
                        <option value=""><?= __e('common.em_dash') ?></option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (int) ($row['client_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e(full_name($c)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php $apptClientField = ob_get_clean(); ob_start(); ?>
                <div class="form-group">
                    <label for="lawyer_id"><?= __e('common.lawyer') ?></label>
                    <select id="lawyer_id" name="lawyer_id">
                        <option value=""><?= __e('common.em_dash') ?></option>
                        <?php foreach ($lawyers as $l): ?>
                            <option value="<?= (int) $l['id'] ?>" <?= (int) ($row['lawyer_id'] ?? 0) === (int) $l['id'] ? 'selected' : '' ?>><?= e(full_name($l)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php $apptLawyerField = ob_get_clean(); ob_start(); ?>
                <div class="form-group full">
                    <label for="case_id"><?= __e('form.related_case') ?></label>
                    <select id="case_id" name="case_id">
                        <option value=""><?= __e('common.em_dash') ?></option>
                        <?php foreach ($cases as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (int) ($row['case_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['case_number'] . ' — ' . $c['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php $apptCaseField = ob_get_clean(); ?>
                <div class="form-grid">
                    <?php if ($apptFormConfig['pairCaseWithParty'] && $apptLawyerVisible && !$apptClientVisible): ?>
                    <div class="entity-field-row entity-field-row--2">
                        <?= $apptLawyerField ?>
                        <?= str_replace('form-group full', 'form-group', $apptCaseField) ?>
                    </div>
                    <?php else: ?>
                    <?php if ($apptClientVisible || $apptLawyerVisible): ?>
                    <div class="entity-field-row entity-field-row--2">
                        <?php if ($apptClientVisible): ?><?= $apptClientField ?><?php endif; ?>
                        <?php if ($apptLawyerVisible): ?><?= $apptLawyerField ?><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?= $apptCaseField ?>
                    <?php endif; ?>
                    <div class="appt-schedule-grid">
                        <div class="form-group">
                            <label for="appt_schedule_date"><?= __e('common.date') ?> <span class="req">*</span></label>
                            <input id="appt_schedule_date" type="date" value="<?= e($apptScheduleDate) ?>" required<?= $apptDateMin ? ' min="' . e($apptDateMin) . '"' : '' ?>>
                        </div>
                        <div class="form-group">
                            <label for="duration_minutes"><?= __e('form.duration') ?></label>
                            <?php $apptDuration = normalize_appointment_duration((int) ($row['duration_minutes'] ?? 60)); ?>
                            <select id="duration_minutes" name="duration_minutes">
                                <?php foreach (appointment_duration_options() as $mins): ?>
                                    <option value="<?= (int) $mins ?>" <?= $apptDuration === $mins ? 'selected' : '' ?>><?= e(format_appointment_duration($mins)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="appt_schedule_time"><?= __e('availability.booking.time') ?> <span class="req">*</span></label>
                            <select id="appt_schedule_time" required>
                                <option value=""><?= __e('availability.booking.choose_time') ?></option>
                                <?php if ($apptScheduleTime): ?>
                                    <option value="<?= e($apptScheduleTime) ?>" selected><?= e(availability_format_slot_label($apptScheduleTime . ':00')) ?></option>
                                <?php endif; ?>
                            </select>
                            <input type="hidden" id="scheduled_at" name="scheduled_at" value="<?= e($row['scheduled_at'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group full appt-schedule-notes">
                        <p class="field-hint" id="apptAvailHint"><?= __e('availability.booking.hint') ?></p>
                        <p class="field-hint avail-slot-warning" id="apptAvailWarning" hidden></p>
                    </div>
                    <div class="form-group full">
                        <label for="location"><?= __e('common.location') ?></label>
                        <input id="location" name="location" value="<?= e($row['location'] ?? '') ?>">
                    </div>
                </div>
            </section>
        </div>
        <div class="entity-form-footer">
            <a class="btn btn-secondary" href="<?= e($formCancelUrl) ?>"><?= __e('common.cancel') ?></a>
            <button class="btn btn-primary" type="submit"><?= $isEdit ? e($apptFormConfig['editLabel']) : e($apptFormConfig['createLabel']) ?></button>
        </div>
    </form>
</div>
</div>
<script type="application/json" id="apptSlotPickerConfig"
    data-lawyer-id="<?= (int) $apptLawyerIdForSlots ?>"
    data-api-url="<?= e(app_config('url')) ?>/api/lawyer-availability.php"
    data-edit-id="<?= (int) $apptEditId ?>"
    data-initial-time="<?= e($apptScheduleTime) ?>"
    data-has-lawyer-select="<?= $apptHasLawyerSelect ? '1' : '0' ?>"></script>
