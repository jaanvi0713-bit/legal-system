<?php
/**
 * Single case activity timeline row.
 *
 * Expects: $a (activity row), $activityIcons, optional $compact (bool)
 */
$compact = !empty($compact);
$icon = $activityIcons[$a['type']] ?? $activityIcons['case'];
?>
<div class="case-activity-item type-<?= e($a['type']) ?><?= ($compact && !empty($a['url'])) ? ' has-action' : '' ?>"<?= $compact ? '' : ' data-type="' . e($a['type']) . '"' ?>>
    <div class="case-activity-icon" aria-hidden="true"><?= $icon ?></div>
    <div class="case-activity-body">
        <strong><?= e($a['title']) ?></strong>
        <span class="case-activity-ref"><?= e($a['ref']) ?></span>
        <?php if (!$compact): ?>
        <span class="case-activity-meta"><?= e($formatActivityWhen($a['at'])) ?><?= !empty($a['by']) ? ' · ' . e($a['by']) : '' ?></span>
        <?php else: ?>
        <span class="case-activity-meta"><?= e($a['at'] ? date('M d, Y', strtotime($a['at'])) : '—') ?></span>
        <?php endif; ?>
    </div>
    <?php if ($compact && !empty($a['url'])): ?>
    <div class="case-activity-actions row-actions">
        <a class="btn btn-row-open btn-sm" href="<?= e($a['url']) ?>"><?= __e('common.view') ?></a>
    </div>
    <?php endif; ?>
</div>
