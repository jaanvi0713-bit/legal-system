<?php
/**
 * Shared hearing create/edit entity form.
 *
 * Expects: $row, $isEdit, $cases, $formCancelUrl
 * Optional: $hearingFormConfig array
 */
$hearingFormConfig = array_merge([
    'showStatus' => true,
    'showOutcome' => true,
    'showJudge' => true,
    'showFileUpload' => false,
    'showLawyer' => true,
    'lockLawyerId' => null,
    'createLabel' => __('court.add'),
    'editLabel' => __('common.save_changes'),
    'createHelp' => __('court.form.help.create'),
    'editHelp' => __('court.form.help.edit'),
], $hearingFormConfig ?? []);

$showStatus = (bool) $hearingFormConfig['showStatus'];
$showOutcome = (bool) $hearingFormConfig['showOutcome'];
$showJudge = (bool) $hearingFormConfig['showJudge'];
$showFileUpload = (bool) $hearingFormConfig['showFileUpload'];
$showLawyer = (bool) $hearingFormConfig['showLawyer'];
$lockLawyerId = $hearingFormConfig['lockLawyerId'];
$lawyers = $lawyers ?? [];
$formEnctype = $showFileUpload ? 'multipart/form-data' : '';
$hearingLawyerId = (int) ($lockLawyerId ?? ($row['lawyer_id'] ?? 0));
?>
<div class="entity-form-wrap">
<div class="entity-form panel">
    <div class="entity-form-hero">
        <div>
            <p class="entity-form-eyebrow"><?= $isEdit ? __e('court.eyebrow.edit') : __e('court.eyebrow.create') ?></p>
            <h2><?= $isEdit ? __e('court.edit') : __e('court.add') ?></h2>
            <p class="muted"><?= $isEdit ? e($hearingFormConfig['editHelp']) : e($hearingFormConfig['createHelp']) ?></p>
        </div>
        <p class="entity-form-required-note"><span class="req">*</span> <?= __e('form.required_fields') ?></p>
    </div>
    <form method="post"<?= $formEnctype !== '' ? ' enctype="' . e($formEnctype) . '"' : '' ?>>
        <div class="entity-form-body">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">

            <section class="entity-section">
                <div class="entity-section-head">
                    <h3><?= __e('court.section.hearing_details') ?></h3>
                    <p><?= __e('court.section.hearing_details_help') ?></p>
                </div>
                <div class="form-grid">
                    <div class="form-group full">
                        <label for="case_id"><?= __e('common.case') ?> <span class="req">*</span></label>
                        <select id="case_id" name="case_id" required>
                            <?php foreach ($cases as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" data-lawyer-id="<?= (int) ($c['lawyer_id'] ?? 0) ?>" <?= (int) ($row['case_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['case_number'] . ' — ' . $c['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($lockLawyerId): ?>
                        <input type="hidden" name="lawyer_id" value="<?= (int) $lockLawyerId ?>">
                    <?php elseif ($showLawyer && $lawyers): ?>
                    <div class="form-group full">
                        <label for="lawyer_id"><?= __e('common.lawyer') ?> <span class="req">*</span></label>
                        <select id="lawyer_id" name="lawyer_id" required>
                            <option value=""><?= __e('common.em_dash') ?></option>
                            <?php foreach ($lawyers as $l): ?>
                                <option value="<?= (int) $l['id'] ?>" <?= $hearingLawyerId === (int) $l['id'] ? 'selected' : '' ?>><?= e(full_name($l)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="hearing-schedule-grid">
                        <div class="form-group">
                            <label for="hearing_date"><?= __e('form.hearing_date') ?> <span class="req">*</span></label>
                            <input id="hearing_date" type="datetime-local" name="hearing_date" required value="<?= e($row['hearing_date'] ?? '') ?>">
                        </div>
                        <?php if ($showStatus): ?>
                        <div class="form-group">
                            <label for="status"><?= __e('common.status') ?> <span class="req">*</span></label>
                            <select id="status" name="status" required>
                                <?php foreach (hearing_statuses() as $s): ?>
                                    <option value="<?= e($s) ?>" <?= ($row['status'] ?? '') === $s ? 'selected' : '' ?>><?= e(translate_status($s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="form-group">
                            <label for="hearing_type"><?= __e('form.hearing_type') ?></label>
                            <input id="hearing_type" name="hearing_type" value="<?= e($row['hearing_type'] ?? '') ?>" placeholder="<?= __e('form.placeholder.hearing_type') ?>">
                        </div>
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
                        <div class="form-group">
                            <label for="court_name"><?= __e('form.court_name') ?> <span class="req">*</span></label>
                            <input id="court_name" name="court_name" required value="<?= e($row['court_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="court_location"><?= __e('common.location') ?></label>
                            <input id="court_location" name="court_location" value="<?= e($row['court_location'] ?? '') ?>">
                        </div>
                        <?php if ($showJudge): ?>
                        <div class="form-group">
                            <label for="judge_name"><?= __e('form.judge') ?></label>
                            <input id="judge_name" name="judge_name" value="<?= e($row['judge_name'] ?? '') ?>">
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($showOutcome): ?>
                    <div class="form-group full">
                        <label for="outcome"><?= __e('form.outcome') ?></label>
                        <textarea id="outcome" name="outcome" rows="2"><?= e($row['outcome'] ?? '') ?></textarea>
                    </div>
                    <?php endif; ?>
                    <div class="form-group full">
                        <label for="notes"><?= __e('form.court_notes') ?></label>
                        <textarea id="notes" name="notes" rows="2"><?= e($row['notes'] ?? '') ?></textarea>
                    </div>
                    <?php if ($showFileUpload): ?>
                    <div class="form-group full">
                        <label for="document"><?= __e('court.upload_doc') ?></label>
                        <input id="document" type="file" name="document">
                    </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
        <div class="entity-form-footer">
            <a class="btn btn-secondary" href="<?= e($formCancelUrl) ?>"><?= __e('common.cancel') ?></a>
            <button class="btn btn-primary" type="submit"><?= $isEdit ? e($hearingFormConfig['editLabel']) : e($hearingFormConfig['createLabel']) ?></button>
        </div>
    </form>
</div>
</div>
<?php if ($showLawyer && $lawyers && !$lockLawyerId): ?>
<script>
(function () {
  const caseSelect = document.getElementById('case_id');
  const lawyerSelect = document.getElementById('lawyer_id');
  if (!caseSelect || !lawyerSelect) return;
  caseSelect.addEventListener('change', function () {
    const opt = caseSelect.options[caseSelect.selectedIndex];
    const lawyerId = opt ? opt.getAttribute('data-lawyer-id') : '';
    if (lawyerId && lawyerSelect.querySelector('option[value="' + lawyerId + '"]')) {
      lawyerSelect.value = lawyerId;
    }
  });
})();
</script>
<?php endif; ?>

