<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'staff']);
$pdo = db();
$user = current_user();

$stats = [
    'clients' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn(),
    'lawyers' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='lawyer' AND is_active=1")->fetchColumn(),
    'active_cases' => (int) $pdo->query("SELECT COUNT(*) FROM cases WHERE status IN ('open','active','pending','reopened','on_hold')")->fetchColumn(),
    'closed_cases' => (int) $pdo->query("SELECT COUNT(*) FROM cases WHERE status='closed'")->fetchColumn(),
    'revenue' => (float) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM payments')->fetchColumn(),
    'messages' => (int) $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn(),
    'appointments' => (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE status IN ('scheduled','confirmed','rescheduled','pending')")->fetchColumn(),
    'hearings' => (int) $pdo->query("SELECT COUNT(*) FROM court_hearings WHERE status='scheduled' AND hearing_date >= NOW()")->fetchColumn(),
    'invoices_open' => (int) $pdo->query("SELECT COUNT(*) FROM invoices WHERE status IN ('sent','partial','overdue')")->fetchColumn(),
    'outstanding' => (float) $pdo->query("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status IN ('sent','partial','overdue')")->fetchColumn(),
    'month_revenue' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn(),
    'prev_month_revenue' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND paid_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn(),
];

$months = [];
for ($i = 11; $i >= 0; $i--) {
    $months[] = date('Y-m', strtotime("-{$i} months"));
}
$openedByMonth = array_fill_keys($months, 0);
$closedByMonth = array_fill_keys($months, 0);
$revenueByMonth = array_fill_keys($months, 0);

foreach ($pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c FROM cases WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY ym") as $row) {
    if (isset($openedByMonth[$row['ym']])) {
        $openedByMonth[$row['ym']] = (int) $row['c'];
    }
}
foreach ($pdo->query("SELECT DATE_FORMAT(COALESCE(closed_at, updated_at), '%Y-%m') AS ym, COUNT(*) AS c FROM cases WHERE status='closed' AND COALESCE(closed_at, updated_at) >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY ym") as $row) {
    if (isset($closedByMonth[$row['ym']])) {
        $closedByMonth[$row['ym']] = (int) $row['c'];
    }
}
foreach ($pdo->query("SELECT DATE_FORMAT(paid_at, '%Y-%m') AS ym, COALESCE(SUM(amount),0) AS total FROM payments WHERE paid_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY ym") as $row) {
    if (isset($revenueByMonth[$row['ym']])) {
        $revenueByMonth[$row['ym']] = (float) $row['total'];
    }
}

$typeRows = $pdo->query("SELECT COALESCE(NULLIF(case_type,''), 'Other') AS label, COUNT(*) AS c FROM cases GROUP BY label ORDER BY c DESC LIMIT 5")->fetchAll();
$typeTotal = max((int) array_sum(array_map(static fn($r) => (int) $r['c'], $typeRows)), 1);

$payments = $pdo->query('
    SELECT p.*, u.first_name, u.last_name, i.invoice_number, i.status AS invoice_status
    FROM payments p
    JOIN users u ON u.id = p.client_id
    LEFT JOIN invoices i ON i.id = p.invoice_id
    ORDER BY p.paid_at DESC
    LIMIT 30
')->fetchAll();

$hearings = $pdo->query("
    SELECT h.*, c.case_number, c.title
    FROM court_hearings h
    JOIN cases c ON c.id = h.case_id
    WHERE h.status = 'scheduled' AND h.hearing_date >= NOW()
    ORDER BY h.hearing_date ASC
    LIMIT 30
")->fetchAll();

$appointments = $pdo->query("
    SELECT a.*, CONCAT(c.first_name,' ',c.last_name) AS client_name
    FROM appointments a
    LEFT JOIN users c ON c.id = a.client_id
    WHERE a.scheduled_at >= NOW()
      AND a.status IN ('scheduled','confirmed','rescheduled','pending')
    ORDER BY a.scheduled_at ASC
    LIMIT 30
")->fetchAll();

$scheduleItems = [];
foreach ($hearings as $h) {
    $scheduleItems[] = [
        'kind' => 'hearing',
        'sort' => strtotime((string) $h['hearing_date']) ?: PHP_INT_MAX,
        'hearing' => $h,
    ];
}
foreach ($appointments as $a) {
    $scheduleItems[] = [
        'kind' => 'appointment',
        'sort' => strtotime((string) $a['scheduled_at']) ?: PHP_INT_MAX,
        'appointment' => $a,
    ];
}
usort($scheduleItems, static fn(array $a, array $b): int => $a['sort'] <=> $b['sort']);

$unread = unread_notifications($pdo, (int) $user['id']);

$balanceVar = 0.0;
if ($stats['prev_month_revenue'] > 0) {
    $balanceVar = (($stats['month_revenue'] - $stats['prev_month_revenue']) / $stats['prev_month_revenue']) * 100;
} elseif ($stats['month_revenue'] > 0) {
    $balanceVar = 100.0;
}
$activeShare = max($stats['active_cases'] + $stats['closed_cases'], 1);
$casesBar = min(100, (int) round(($stats['active_cases'] / $activeShare) * 100));
$collectTarget = max($stats['month_revenue'] + $stats['outstanding'], 1);
$collectBar = min(100, (int) round(($stats['month_revenue'] / $collectTarget) * 100));

$aiPct = abs($balanceVar) >= 0.1 ? number_format(abs($balanceVar), 1) : number_format(max($casesBar * 0.1, 2.4), 1);
$aiCaption = abs($balanceVar) >= 0.1
    ? __($balanceVar >= 0 ? 'dashboard.ai_caption_rise' : 'dashboard.ai_caption_dip')
    : __('dashboard.ai_caption_ready');

$hasSchedule = (bool) $scheduleItems;
$schedulePagerRows = $hasSchedule ? count($scheduleItems) : count($typeRows);
$schedulePagerPages = max(1, (int) ceil(max($schedulePagerRows, 1) / 3));
$paymentsPagerPages = max(1, (int) ceil(max(count($payments), 1) / 3));

$chartData = [
    'months' => array_map('format_month_short', $months),
    'monthKeys' => $months,
    'opened' => array_values($openedByMonth),
    'closed' => array_values($closedByMonth),
    'revenue' => array_values($revenueByMonth),
    'currency' => trim(app_config('currency_symbol') ?: 'Rs'),
    'chartAria' => __('dashboard.chart.aria_overview'),
    'chartRangeAria' => __('dashboard.chart.aria_range'),
];

/** Build overview SVG path from a numeric series (no JS required). */
$buildOverviewSvg = static function (array $labels, array $values, string $ariaLabel): string {
    $w = 640;
    $h = 220;
    $padL = 12;
    $padR = 12;
    $padT = 18;
    $padB = 34;
    $n = max(count($values), 1);
    $max = max(array_map('floatval', $values) ?: [0]);
    if ($max <= 0) {
        $max = 1;
    }
    $innerW = $w - $padL - $padR;
    $innerH = $h - $padT - $padB;
    $pts = [];
    foreach ($values as $i => $v) {
        $x = $padL + ($n === 1 ? $innerW / 2 : ($i / ($n - 1)) * $innerW);
        $y = $padT + $innerH - ((float) $v / $max) * $innerH;
        $pts[] = [$x, $y];
    }
    $line = '';
    foreach ($pts as $i => [$x, $y]) {
        $line .= ($i === 0 ? 'M' : 'L') . round($x, 1) . ',' . round($y, 1) . ' ';
    }
    $area = $line . 'L' . round($pts[count($pts) - 1][0], 1) . ',' . ($padT + $innerH)
        . ' L' . round($pts[0][0], 1) . ',' . ($padT + $innerH) . ' Z';
    $labelsHtml = '';
    foreach ($labels as $i => $lab) {
        $x = $padL + ($n === 1 ? $innerW / 2 : ($i / ($n - 1)) * $innerW);
        $labelsHtml .= '<text x="' . round($x, 1) . '" y="' . ($h - 10) . '" text-anchor="middle">' . htmlspecialchars((string) $lab) . '</text>';
    }
    $last = $pts[count($pts) - 1];
    return '<svg class="glass-svg-chart" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img" aria-label="' . htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') . '">'
        . '<defs><linearGradient id="ovFill" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0%" stop-color="currentColor" stop-opacity="0.28"/>'
        . '<stop offset="100%" stop-color="currentColor" stop-opacity="0"/>'
        . '</linearGradient></defs>'
        . '<path class="glass-svg-area" d="' . trim($area) . '" fill="url(#ovFill)"/>'
        . '<path class="glass-svg-line" d="' . trim($line) . '" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>'
        . '<circle class="glass-svg-dot" cx="' . round($last[0], 1) . '" cy="' . round($last[1], 1) . '" r="5"/>'
        . '<g class="glass-svg-labels">' . $labelsHtml . '</g>'
        . '</svg>';
};

$overviewLabels = array_slice($chartData['months'], -7);
$overviewValues = array_slice($chartData['revenue'], -7);
$overviewOpened = array_slice($chartData['opened'], -7);
if (array_sum($overviewValues) <= 0) {
    $overviewValues = $overviewOpened;
}
$overviewSvg = $buildOverviewSvg($overviewLabels, $overviewValues, __('dashboard.chart.aria_overview'));

$pageTitle = __('page.dashboard');
$pageSubtitle = __('dashboard.welcome_body');
$portal = 'admin';
$activeNav = 'dashboard';
$includeCharts = true;
$bodyClass = 'page-glass-dash';
require __DIR__ . '/../includes/header.php';
?>
<div class="glass-dash">
    <div class="glass-dash-top">
        <div class="glass-dash-left">
            <section class="glass-card glass-balance">
                <div class="glass-balance-head">
                    <div>
                        <span class="glass-kicker"><?= __e('dashboard.kpi.total_revenue') ?></span>
                        <div class="glass-balance-value"><?= e(money($stats['revenue'])) ?></div>
                        <div class="glass-balance-var <?= $balanceVar >= 0 ? 'is-up' : 'is-down' ?>">
                            <span><?= ($balanceVar >= 0 ? '+' : '') . number_format($balanceVar, 1) ?>%</span>
                            <?= __e('dashboard.balance_variation') ?>
                        </div>
                    </div>
                    <a class="glass-chip" href="reports.php"><?= __e('dashboard.action.view_reports') ?></a>
                </div>

                <a class="glass-ai" href="insights.php">
                    <div class="glass-ai-copy">
                        <div class="glass-ai-title">
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.2 6.2L19 9l-5.2 2.2L12 17l-1.8-5.8L5 9l5.8-.8L12 2zm7 11l.7 3.3L23 17l-3.3.7L19 21l-.7-3.3L15 17l3.3-.7L19 13zM5 14l.6 2.7L8 17.2 5.6 18 5 20.5l-.6-2.5L2 17.2l2.4-.5L5 14z"/></svg>
                            <span><?= __e('dashboard.insights') ?></span>
                        </div>
                        <div class="glass-ai-stat">
                            <span class="glass-ai-chart" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M7 15l3-3 2.5 2.5L17 9" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 9h3v3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <strong><?= e($aiPct) ?>%</strong>
                        </div>
                        <p><?= e($aiCaption) ?></p>
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
                    <h2><?= $hasSchedule ? __e('dashboard.panel.upcoming_schedule') : __e('dashboard.panel.workload_by_type') ?></h2>
                    <a class="glass-link" href="<?= $hasSchedule ? ($hearings ? 'court.php' : 'appointments.php') : 'cases.php' ?>"><?= __e('common.view') ?></a>
                </div>
                <div class="glass-list">
                    <?php if ($hasSchedule): ?>
                        <?php foreach ($scheduleItems as $item): ?>
                            <?php if ($item['kind'] === 'hearing'):
                                $h = $item['hearing'];
                            ?>
                            <div class="glass-list-item" data-glass-page-row>
                                <div class="glass-list-mark">H</div>
                                <div class="glass-list-meta">
                                    <strong><?= e($h['case_number']) ?></strong>
                                    <span><?= e(format_datetime($h['hearing_date'])) ?></span>
                                </div>
                                <div class="glass-list-right">
                                    <strong><?= e(t_content($h['court_name'] ?: __('common.court'))) ?></strong>
                                    <span class="is-soft"><?= e(__('dashboard.schedule.appts', ['count' => (int) $stats['appointments']])) ?></span>
                                </div>
                            </div>
                            <?php else:
                                $a = $item['appointment'];
                            ?>
                            <div class="glass-list-item" data-glass-page-row>
                                <div class="glass-list-mark">A</div>
                                <div class="glass-list-meta">
                                    <strong><?= e(t_content($a['title'])) ?></strong>
                                    <span><?= e(format_datetime($a['scheduled_at'])) ?></span>
                                </div>
                                <div class="glass-list-right">
                                    <strong><?= e($a['client_name'] ?? __('common.em_dash')) ?></strong>
                                    <span class="is-soft"><?= e(translate_status((string) ($a['status'] ?? ''))) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach ($typeRows as $type):
                            $pct = (int) round(((int) $type['c'] / $typeTotal) * 100);
                        ?>
                            <div class="glass-list-item" data-glass-page-row>
                                <div class="glass-list-mark"><?= e(strtoupper(substr($type['label'], 0, 1))) ?></div>
                                <div class="glass-list-meta">
                                    <strong><?= e(t_content($type['label'])) ?></strong>
                                    <span><?= e(__('dashboard.workload.matters', ['count' => (int) $type['c']])) ?></span>
                                </div>
                                <div class="glass-list-right">
                                    <strong><?= $pct ?>%</strong>
                                    <span class="is-soft"><?= __e('dashboard.workload.share') ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$typeRows): ?>
                            <div class="empty-state"><?= __e('dashboard.empty.no_case_types') ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if ($unread > 0 || $stats['messages'] > 0): ?>
                    <a class="glass-notify-strip" href="notifications.php">
                        <?php if ($unread > 0): ?>
                            <?= e(__($unread === 1 ? 'dashboard.notify.new_one' : 'dashboard.notify.new_many', ['count' => (int) $unread])) ?>
                        <?php endif; ?>
                        <?php if ($unread > 0 && $stats['messages'] > 0): ?> · <?php endif; ?>
                        <?php if ($stats['messages'] > 0): ?>
                            <?= e(__($stats['messages'] === 1 ? 'dashboard.message_one' : 'dashboard.message_many', ['count' => (int) $stats['messages']])) ?>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
                <div class="glass-dash-pager-wrap"<?= $schedulePagerRows < 1 ? ' hidden' : '' ?>>
                    <div class="glass-dash-pager">
                        <button type="button" class="glass-dash-page-btn" data-glass-page="prev" aria-label="<?= __e('common.previous') ?>" disabled>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
                        </button>
                        <span class="glass-dash-page-label"><?= e(__('calendar.page_of', ['page' => 1, 'pages' => $schedulePagerPages])) ?></span>
                        <button type="button" class="glass-dash-page-btn" data-glass-page="next" aria-label="<?= __e('common.next') ?>"<?= $schedulePagerPages <= 1 ? ' disabled' : '' ?>>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <div class="glass-dash-right">
            <div class="glass-mini-row">
                <a class="glass-card glass-mini glass-mini-link" href="cases.php?filter=active">
                    <div class="glass-mini-top">
                        <span><?= __e('dashboard.kpi.active_cases') ?></span>
                        <strong>+<?= (int) $stats['active_cases'] ?></strong>
                    </div>
                    <div class="glass-mini-bar"><span style="width:<?= $casesBar ?>%"></span></div>
                    <p><?= (int) $stats['clients'] ?> <?= __e('dashboard.kpi.clients') ?> · <?= (int) $stats['lawyers'] ?> <?= __e('dashboard.kpi.lawyers_available') ?></p>
                </a>
                <a class="glass-card glass-mini glass-mini-link" href="cases.php?filter=outstanding">
                    <div class="glass-mini-top">
                        <span><?= __e('finance.outstanding') ?></span>
                        <strong><?= e(money($stats['outstanding'])) ?></strong>
                    </div>
                    <div class="glass-mini-bar is-warn"><span style="width:<?= max(8, 100 - $collectBar) ?>%"></span></div>
                    <p><?= (int) $stats['invoices_open'] ?> <?= __e('dashboard.kpi.open_invoices') ?></p>
                </a>
            </div>

            <section class="glass-card glass-overview">
                <div class="glass-overview-head">
                    <h2><?= __e('dashboard.panel.case_overview') ?></h2>
                    <div class="glass-range" id="overviewRange" role="tablist" aria-label="<?= __e('dashboard.chart.aria_range') ?>">
                        <button type="button" class="glass-range-btn" data-range="day"><?= __e('dashboard.chart.day') ?></button>
                        <button type="button" class="glass-range-btn" data-range="week"><?= __e('dashboard.chart.week') ?></button>
                        <button type="button" class="glass-range-btn is-active" data-range="month"><?= __e('dashboard.chart.month') ?></button>
                        <button type="button" class="glass-range-btn" data-range="year"><?= __e('dashboard.chart.year') ?></button>
                    </div>
                </div>
                <div class="glass-chart" id="overviewChartHost">
                    <?= $overviewSvg ?>
                </div>
            </section>

            <section class="glass-card glass-tx" data-glass-pager-root data-per-page="3"
                     data-page-of="<?= e(__('calendar.page_of', ['page' => ':page', 'pages' => ':pages'])) ?>"
                     data-prev-label="<?= __e('common.previous') ?>"
                     data-next-label="<?= __e('common.next') ?>">
                <div class="glass-panel-head">
                    <h2><?= __e('dashboard.panel.revenue_collections') ?></h2>
                    <span class="glass-soft-chip"><?= e(__('dashboard.month_chip', ['amount' => money($stats['month_revenue'])])) ?></span>
                </div>
                <div class="table-wrap glass-table-wrap">
                    <table class="glass-table">
                        <thead>
                            <tr>
                                <th><?= __e('dashboard.col.activity') ?></th>
                                <th><?= __e('common.date') ?></th>
                                <th><?= __e('common.amount') ?></th>
                                <th><?= __e('common.status') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($payments as $p):
                            $status = $p['invoice_status'] === 'partial' ? __('common.pending') : __('dashboard.status.success');
                            $isPending = $p['invoice_status'] === 'partial';
                        ?>
                            <tr data-glass-page-row>
                                <td>
                                    <div class="glass-tx-act">
                                        <span class="glass-tx-icon"><?= e(strtoupper(substr($p['first_name'], 0, 1))) ?></span>
                                        <div>
                                            <strong><?= e(full_name($p)) ?></strong>
                                            <span><?= e($p['invoice_number'] ?: ($p['receipt_number'] ?: __('finance.receipt'))) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e(format_date($p['paid_at'])) ?></td>
                                <td><?= e(money($p['amount'])) ?></td>
                                <td>
                                    <span class="glass-status <?= $isPending ? 'is-pending' : 'is-ok' ?>">
                                        <i></i> <?= e($status) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$payments): ?>
                            <tr><td colspan="4" class="muted"><?= __e('dashboard.empty.no_payments') ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="glass-dash-pager-wrap"<?= !$payments ? ' hidden' : '' ?>>
                    <div class="glass-dash-pager">
                        <button type="button" class="glass-dash-page-btn" data-glass-page="prev" aria-label="<?= __e('common.previous') ?>" disabled>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
                        </button>
                        <span class="glass-dash-page-label"><?= e(__('calendar.page_of', ['page' => 1, 'pages' => $paymentsPagerPages])) ?></span>
                        <button type="button" class="glass-dash-page-btn" data-glass-page="next" aria-label="<?= __e('common.next') ?>"<?= $paymentsPagerPages <= 1 ? ' disabled' : '' ?>>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
<script>
window.LEXORA_DASHBOARD = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE) ?>;
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
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
(function () {
  function boot() {
    if (typeof window.initGlassOverview === 'function') window.initGlassOverview();
    if (typeof window.initGlassDashPagination === 'function') window.initGlassDashPagination();
  }
  boot();
  window.addEventListener('load', boot);
})();
</script>
