<?php
/**
 * Shared appointment/hearing view modal.
 * Expects $viewItem and optional $calFieldPerson.
 */
$viewItem = $viewItem ?? null;
$calFieldPerson = $calFieldPerson ?? __('common.client');
$showLawyer = $viewItem && trim((string) ($viewItem['lawyer'] ?? '')) !== '';
$showHearingType = $viewItem && trim((string) ($viewItem['hearingType'] ?? '')) !== '';
$showJudge = $viewItem && trim((string) ($viewItem['judge'] ?? '')) !== '';
$showOutcome = $viewItem && trim((string) ($viewItem['outcome'] ?? '')) !== '';
?>
<div class="appt-view-modal" id="apptViewModal"<?= $viewItem ? '' : ' hidden' ?> aria-hidden="<?= $viewItem ? 'false' : 'true' ?>">
    <div class="appt-view-backdrop" data-appt-close tabindex="-1"></div>
    <div class="appt-view-dialog" role="dialog" aria-modal="true" aria-labelledby="apptViewTitle" tabindex="-1">
        <button type="button" class="appt-view-close" data-appt-close aria-label="<?= __e('common.close') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
        <h2 class="appt-view-title" id="apptViewTitle"><?= $viewItem ? e((string) $viewItem['title']) : '' ?></h2>
        <dl class="appt-view-fields">
            <div class="appt-view-field"><dt id="apptViewClientLabel"><?= e($calFieldPerson) ?></dt><dd id="apptViewClient"><?= $viewItem ? e((string) ($viewItem['client'] ?: __('common.em_dash'))) : '' ?></dd></div>
            <div class="appt-view-field" id="apptViewLawyerRow"<?= $showLawyer ? '' : ' hidden' ?>><dt><?= __e('common.lawyer') ?></dt><dd id="apptViewLawyer"><?= $showLawyer ? e((string) $viewItem['lawyer']) : '' ?></dd></div>
            <div class="appt-view-field"><dt><?= __e('common.case') ?></dt><dd id="apptViewCase"><?= $viewItem ? e((string) ($viewItem['caseLabel'] ?: __('common.em_dash'))) : '' ?></dd></div>
            <div class="appt-view-field" id="apptViewHearingTypeRow"<?= $showHearingType ? '' : ' hidden' ?>><dt><?= __e('form.hearing_type') ?></dt><dd id="apptViewHearingType"><?= $showHearingType ? e((string) $viewItem['hearingType']) : '' ?></dd></div>
            <div class="appt-view-field"><dt><?= __e('common.when') ?></dt><dd id="apptViewWhen"><?php
                if ($viewItem) {
                    $startTs = strtotime((string) $viewItem['scheduledAt']);
                    $endTs = $startTs ? $startTs + ((int) ($viewItem['durationMinutes'] ?? 60) * 60) : false;
                    echo e($startTs && $endTs
                        ? date('M j, Y g:i A', $startTs) . ' — ' . date('M j, Y g:i A', $endTs)
                        : __('common.em_dash'));
                }
            ?></dd></div>
            <div class="appt-view-field"><dt><?= __e('common.location') ?></dt><dd id="apptViewLocation"><?= $viewItem ? e((string) ($viewItem['location'] ?: __('common.em_dash'))) : '' ?></dd></div>
            <div class="appt-view-field" id="apptViewJudgeRow"<?= $showJudge ? '' : ' hidden' ?>><dt><?= __e('form.judge') ?></dt><dd id="apptViewJudge"><?= $showJudge ? e((string) $viewItem['judge']) : '' ?></dd></div>
            <div class="appt-view-field"><dt><?= __e('common.status') ?></dt><dd id="apptViewStatus"><?= $viewItem ? e((string) ($viewItem['statusLabel'] ?? $viewItem['status'] ?: __('common.em_dash'))) : '' ?></dd></div>
            <div class="appt-view-field" id="apptViewOutcomeRow"<?= $showOutcome ? '' : ' hidden' ?>><dt><?= __e('form.outcome') ?></dt><dd id="apptViewOutcome"><?= $showOutcome ? e((string) $viewItem['outcome']) : '' ?></dd></div>
            <div class="appt-view-field"><dt><?= __e('common.notes') ?></dt><dd id="apptViewNotes"><?= $viewItem ? e((string) ($viewItem['description'] ?: __('common.em_dash'))) : '' ?></dd></div>
        </dl>
        <div class="appt-view-export">
            <a href="#" class="appt-view-export-btn" id="apptViewGoogle" target="_blank" rel="noopener">
                <svg class="appt-view-export-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                <?= __e('calendar.export.google') ?>
            </a>
            <a href="#" class="appt-view-export-btn" id="apptViewOutlook" target="_blank" rel="noopener">
                <svg class="appt-view-export-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M21 4.75A1.25 1.25 0 0 0 19.75 3.5H4.25A1.25 1.25 0 0 0 3 4.75v14.5A1.25 1.25 0 0 0 4.25 20.5h15.5A1.25 1.25 0 0 0 21 19.25V4.75zm-9.5 12.35a3.6 3.6 0 1 1 0-7.2 3.6 3.6 0 0 1 0 7.2z"/></svg>
                <?= __e('calendar.export.outlook') ?>
            </a>
            <a href="#" class="appt-view-export-btn" id="apptViewIcs" download="appointment.ics">
                <svg class="appt-view-export-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v12m0 0l-4-4m4 4l4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                <?= __e('calendar.export.ics') ?>
            </a>
        </div>
    </div>
</div>
<?php if ($viewItem): ?><script>document.body.classList.add('appt-modal-open');</script><?php endif; ?>
