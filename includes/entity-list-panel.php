<?php
/**
 * Shared appointment/court list panel markup.
 *
 * Expected vars:
 * - $listPanelId, $listSearchId, $listStatusId, $listTableId, $listFooterId, $listTotalMetaId
 * - $listTitle, $listSubtitle, $listSearchPlaceholder, $listAllStatuses
 * - $listShowingTpl, $listTotalOneTpl, $listTotalManyTpl
 * - $listStatuses (array of status keys), $listStatusLabelFn (callable|null) or use __('court.tone.'.$s) / calendar.tone
 * - $listStatusI18nPrefix ('calendar.tone.' or 'court.tone.')
 * - $listColumns: array of th labels
 * - $listRowsHtml: pre-rendered tbody HTML
 * - $listHeroActionHtml: optional HTML for hero right side
 * - $listPanelClass: 'appt-list-panel' or 'court-list-panel'
 */
$listPanelId = $listPanelId ?? 'entityListPanel';
$listSearchId = $listSearchId ?? 'entityListSearch';
$listStatusId = $listStatusId ?? 'entityListStatus';
$listTableId = $listTableId ?? 'entityListTable';
$listFooterId = $listFooterId ?? 'entityListFooter';
$listTotalMetaId = $listTotalMetaId ?? 'entityListTotalMeta';
$listPanelClass = $listPanelClass ?? 'appt-list-panel';
$listStatuses = $listStatuses ?? [];
$listStatusI18nPrefix = $listStatusI18nPrefix ?? 'calendar.tone.';
$listHeroActionHtml = $listHeroActionHtml ?? '';
$listColumns = $listColumns ?? [];
$listRowsHtml = $listRowsHtml ?? '';
$listTitle = $listTitle ?? '';
$listSubtitle = $listSubtitle ?? '';
$listSearchPlaceholder = $listSearchPlaceholder ?? '';
$listAllStatuses = $listAllStatuses ?? '';
$listShowingTpl = $listShowingTpl ?? '';
$listTotalOneTpl = $listTotalOneTpl ?? '';
$listTotalManyTpl = $listTotalManyTpl ?? '';
?>
<div class="panel <?= e($listPanelClass) ?>"
     id="<?= e($listPanelId) ?>"
     data-list-filter
     data-search-id="<?= e($listSearchId) ?>"
     data-status-id="<?= e($listStatusId) ?>"
     data-table-id="<?= e($listTableId) ?>"
     data-footer-id="<?= e($listFooterId) ?>"
     data-total-meta-id="<?= e($listTotalMetaId) ?>"
     data-showing-tpl="<?= e($listShowingTpl) ?>"
     data-total-one="<?= e($listTotalOneTpl) ?>"
     data-total-many="<?= e($listTotalManyTpl) ?>">
    <div class="appt-list-hero">
        <div>
            <h2><?= e($listTitle) ?></h2>
            <p class="appt-list-meta" id="<?= e($listTotalMetaId) ?>"><?= e($listSubtitle) ?></p>
        </div>
        <?php if ($listHeroActionHtml !== ''): ?>
            <?= $listHeroActionHtml ?>
        <?php endif; ?>
    </div>

    <div class="appt-list-toolbar">
        <label class="appt-list-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input type="search" id="<?= e($listSearchId) ?>" placeholder="<?= e($listSearchPlaceholder) ?>" autocomplete="off">
        </label>
        <select id="<?= e($listStatusId) ?>" aria-label="<?= __e('common.status') ?>">
            <option value=""><?= e($listAllStatuses) ?></option>
            <?php foreach ($listStatuses as $tone): ?>
            <option value="<?= e($tone) ?>"><?= e(__($listStatusI18nPrefix . $tone)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="table-wrap appt-list-table-wrap">
        <table class="appt-list-table" id="<?= e($listTableId) ?>">
            <thead>
                <tr>
                    <?php foreach ($listColumns as $col): ?>
                    <th><?= e($col) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?= $listRowsHtml ?>
            </tbody>
        </table>
    </div>
    <p class="appt-list-footer" id="<?= e($listFooterId) ?>"><?= e($listShowingTpl) ?></p>
</div>
