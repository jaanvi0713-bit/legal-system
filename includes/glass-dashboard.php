<?php
/**
 * Shared "glass" dashboard renderer (mirrors the admin dashboard layout).
 *
 * Expects a single $gd config array:
 * - balance:  ['kicker','value','var'(float|null),'chipLabel','chipUrl']
 * - ai:       ['url','pct','caption']
 * - side:     ['title','viewUrl','rowsHtml','pagerRows'(int),'pagerPages'(int),'notifyHtml'(optional)]
 * - minis:    list of ['label','value','url','bar'(0-100),'barClass'('' | 'is-warn'),'foot']
 * - overview: ['title','svg']
 * - tx:       ['title','chip','columns'(4),'rowsHtml','pagerPages'(int),'hasRows'(bool)]
 * - chartData: array passed to the client-side overview chart
 */
$gdBalance = $gd['balance'];
$gdAi = $gd['ai'];
$gdSide = $gd['side'];
$gdMinis = $gd['minis'] ?? [];
$gdOverview = $gd['overview'];
$gdTx = $gd['tx'];
$gdChartData = $gd['chartData'];
$gdVar = $gdBalance['var'] ?? null;
?>
<div class="glass-dash">
    <div class="glass-dash-top">
        <div class="glass-dash-left">
            <section class="glass-card glass-balance">
                <div class="glass-balance-head">
                    <div>
                        <span class="glass-kicker"><?= e($gdBalance['kicker']) ?></span>
                        <div class="glass-balance-value"><?= e($gdBalance['value']) ?></div>
                        <?php if ($gdVar !== null): ?>
                        <div class="glass-balance-var <?= $gdVar >= 0 ? 'is-up' : 'is-down' ?>">
                            <span><?= ($gdVar >= 0 ? '+' : '') . number_format((float) $gdVar, 1) ?>%</span>
                            <?= __e('dashboard.balance_variation') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($gdBalance['chipUrl'])): ?>
                    <a class="glass-chip" href="<?= e($gdBalance['chipUrl']) ?>"><?= e($gdBalance['chipLabel']) ?></a>
                    <?php endif; ?>
                </div>

                <a class="glass-ai" href="<?= e($gdAi['url']) ?>">
                    <div class="glass-ai-copy">
                        <div class="glass-ai-title">
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.2 6.2L19 9l-5.2 2.2L12 17l-1.8-5.8L5 9l5.8-.8L12 2zm7 11l.7 3.3L23 17l-3.3.7L19 21l-.7-3.3L15 17l3.3-.7L19 13zM5 14l.6 2.7L8 17.2 5.6 18 5 20.5l-.6-2.5L2 17.2l2.4-.5L5 14z"/></svg>
                            <span><?= __e('dashboard.ai_assistant') ?></span>
                        </div>
                        <div class="glass-ai-stat">
                            <span class="glass-ai-chart" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M7 15l3-3 2.5 2.5L17 9" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 9h3v3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <strong><?= e($gdAi['pct']) ?>%</strong>
                        </div>
                        <p><?= e($gdAi['caption']) ?></p>
                    </div>
                    <div class="glass-ai-visual" aria-hidden="true">
                        <img class="glass-ai-bg-orb" src="<?= e(app_config('url')) ?>/assets/img/ai-orb.png" alt="">
                    </div>
                </a>
            </section>

            <section class="glass-card glass-side-list" data-glass-pager-root data-per-page="3"
                     data-page-of="<?= e(__('calendar.page_of', ['page' => ':page', 'pages' => ':pages'])) ?>"
                     data-prev-label="<?= __e('common.previous') ?>"
                     data-next-label="<?= __e('common.next') ?>">
                <div class="glass-panel-head">
                    <h2><?= e($gdSide['title']) ?></h2>
                    <?php if (!empty($gdSide['viewUrl'])): ?>
                    <a class="glass-link" href="<?= e($gdSide['viewUrl']) ?>"><?= __e('common.view') ?></a>
                    <?php endif; ?>
                </div>
                <div class="glass-list">
                    <?= $gdSide['rowsHtml'] ?>
                </div>
                <?php if (!empty($gdSide['notifyHtml'])): ?>
                    <?= $gdSide['notifyHtml'] ?>
                <?php endif; ?>
                <div class="glass-dash-pager-wrap"<?= ((int) $gdSide['pagerRows']) < 1 ? ' hidden' : '' ?>>
                    <div class="glass-dash-pager">
                        <button type="button" class="glass-dash-page-btn" data-glass-page="prev" aria-label="<?= __e('common.previous') ?>" disabled>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
                        </button>
                        <span class="glass-dash-page-label"><?= e(__('calendar.page_of', ['page' => 1, 'pages' => (int) $gdSide['pagerPages']])) ?></span>
                        <button type="button" class="glass-dash-page-btn" data-glass-page="next" aria-label="<?= __e('common.next') ?>"<?= ((int) $gdSide['pagerPages']) <= 1 ? ' disabled' : '' ?>>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <div class="glass-dash-right">
            <div class="glass-mini-row">
                <?php foreach ($gdMinis as $mini): ?>
                <a class="glass-card glass-mini glass-mini-link" href="<?= e($mini['url']) ?>">
                    <div class="glass-mini-top">
                        <span><?= e($mini['label']) ?></span>
                        <strong><?= e($mini['value']) ?></strong>
                    </div>
                    <div class="glass-mini-bar<?= !empty($mini['barClass']) ? ' ' . e($mini['barClass']) : '' ?>"><span style="width:<?= (int) $mini['bar'] ?>%"></span></div>
                    <p><?= e($mini['foot']) ?></p>
                </a>
                <?php endforeach; ?>
            </div>

            <section class="glass-card glass-overview">
                <div class="glass-overview-head">
                    <h2><?= e($gdOverview['title']) ?></h2>
                    <div class="glass-range" id="overviewRange" role="tablist" aria-label="<?= __e('dashboard.chart.aria_range') ?>">
                        <button type="button" class="glass-range-btn" data-range="day"><?= __e('dashboard.chart.day') ?></button>
                        <button type="button" class="glass-range-btn" data-range="week"><?= __e('dashboard.chart.week') ?></button>
                        <button type="button" class="glass-range-btn is-active" data-range="month"><?= __e('dashboard.chart.month') ?></button>
                        <button type="button" class="glass-range-btn" data-range="year"><?= __e('dashboard.chart.year') ?></button>
                    </div>
                </div>
                <div class="glass-chart" id="overviewChartHost">
                    <?= $gdOverview['svg'] ?>
                </div>
            </section>

            <section class="glass-card glass-tx" data-glass-pager-root data-per-page="3"
                     data-page-of="<?= e(__('calendar.page_of', ['page' => ':page', 'pages' => ':pages'])) ?>"
                     data-prev-label="<?= __e('common.previous') ?>"
                     data-next-label="<?= __e('common.next') ?>">
                <div class="glass-panel-head">
                    <h2><?= e($gdTx['title']) ?></h2>
                    <?php if (!empty($gdTx['chip'])): ?>
                    <span class="glass-soft-chip"><?= e($gdTx['chip']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="table-wrap glass-table-wrap">
                    <table class="glass-table">
                        <thead>
                            <tr>
                                <?php foreach ($gdTx['columns'] as $col): ?>
                                <th><?= e($col) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?= $gdTx['rowsHtml'] ?>
                        </tbody>
                    </table>
                </div>
                <div class="glass-dash-pager-wrap"<?= empty($gdTx['hasRows']) ? ' hidden' : '' ?>>
                    <div class="glass-dash-pager">
                        <button type="button" class="glass-dash-page-btn" data-glass-page="prev" aria-label="<?= __e('common.previous') ?>" disabled>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
                        </button>
                        <span class="glass-dash-page-label"><?= e(__('calendar.page_of', ['page' => 1, 'pages' => (int) $gdTx['pagerPages']])) ?></span>
                        <button type="button" class="glass-dash-page-btn" data-glass-page="next" aria-label="<?= __e('common.next') ?>"<?= ((int) $gdTx['pagerPages']) <= 1 ? ' disabled' : '' ?>>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
<script>
window.LEXORA_DASHBOARD = <?= json_encode($gdChartData, JSON_UNESCAPED_UNICODE) ?>;
window.LEXORA_OVERVIEW_SVG = true;
window.initGlassOverview = function () {
  var host = document.getElementById('overviewChartHost');
  var rangeRoot = document.getElementById('overviewRange');
  var data = window.LEXORA_DASHBOARD;
  if (!host || !rangeRoot || !data) return;

  function sliceByRange(range) {
    var len = (data.months || []).length || 1;
    if (range === 'day') return Math.min(2, len);
    if (range === 'week') return Math.min(4, len);
    if (range === 'year') return len;
    return Math.min(7, len);
  }

  function seriesFor(range) {
    var n = sliceByRange(range);
    var labels = (data.months || []).slice(-n);
    var revenue = (data.revenue || []).slice(-n).map(Number);
    var opened = (data.opened || []).slice(-n).map(Number);
    var revenueSum = revenue.reduce(function (a, b) { return a + b; }, 0);
    var values = revenueSum > 0 ? revenue : (opened.some(function (v) { return v > 0; }) ? opened : revenue);
    return {
      labels: labels.length ? labels : ['—'],
      values: values.length ? values : [0]
    };
  }

  function renderSvg(labels, values) {
    var w = 640, h = 220, padL = 16, padR = 16, padT = 20, padB = 36;
    var n = Math.max(values.length, 1);
    var max = Math.max.apply(null, values.map(Number).concat([0]));
    if (max <= 0) max = 1;
    var innerW = w - padL - padR;
    var innerH = h - padT - padB;
    var pts = values.map(function (v, i) {
      var x = padL + (n === 1 ? innerW / 2 : (i / (n - 1)) * innerW);
      var y = padT + innerH - (Number(v) / max) * innerH;
      return [x, y];
    });
    var line = pts.map(function (p, i) {
      return (i ? 'L' : 'M') + p[0].toFixed(1) + ',' + p[1].toFixed(1);
    }).join(' ');
    var last = pts[pts.length - 1];
    var first = pts[0];
    var area = line + ' L' + last[0].toFixed(1) + ',' + (padT + innerH) + ' L' + first[0].toFixed(1) + ',' + (padT + innerH) + ' Z';
    var labelText = labels.map(function (lab, i) {
      var x = padL + (n === 1 ? innerW / 2 : (i / (n - 1)) * innerW);
      return '<text x="' + x.toFixed(1) + '" y="' + (h - 12) + '" text-anchor="middle">' + String(lab).replace(/[<>&]/g, '') + '</text>';
    }).join('');
    var gid = 'ovFill_' + Date.now();
    host.innerHTML = '<svg class="glass-svg-chart" viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none" role="img" aria-label="' + (data.chartAria || '') + '">' +
      '<defs><linearGradient id="' + gid + '" x1="0" y1="0" x2="0" y2="1">' +
      '<stop offset="0%" stop-color="currentColor" stop-opacity="0.32"/>' +
      '<stop offset="100%" stop-color="currentColor" stop-opacity="0"/>' +
      '</linearGradient></defs>' +
      '<path d="' + area + '" fill="url(#' + gid + ')"/>' +
      '<path d="' + line + '" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>' +
      '<circle class="glass-svg-dot" cx="' + last[0].toFixed(1) + '" cy="' + last[1].toFixed(1) + '" r="5.5"/>' +
      '<g class="glass-svg-labels">' + labelText + '</g></svg>';
  }

  if (rangeRoot.dataset.bound !== '1') {
    rangeRoot.dataset.bound = '1';
    rangeRoot.addEventListener('click', function (e) {
      var btn = e.target.closest('.glass-range-btn');
      if (!btn || !rangeRoot.contains(btn)) return;
      rangeRoot.querySelectorAll('.glass-range-btn').forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
      var next = seriesFor(btn.getAttribute('data-range') || 'month');
      renderSvg(next.labels, next.values);
    });
  }

  var active = rangeRoot.querySelector('.glass-range-btn.is-active');
  var range = active ? (active.getAttribute('data-range') || 'month') : 'month';
  var initial = seriesFor(range);
  renderSvg(initial.labels, initial.values);
};
</script>
