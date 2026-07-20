<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['lawyer']);
$pdo = db();
$user = current_user();
$uid = (int) $user['id'];

$cases = $pdo->prepare("SELECT c.*, CONCAT(u.first_name,' ',u.last_name) AS client_name FROM cases c JOIN users u ON u.id=c.client_id WHERE c.lawyer_id=? ORDER BY c.updated_at DESC LIMIT 30");
$cases->execute([$uid]);
$cases = $cases->fetchAll();

$activeCases = (int) ($pdo->query("SELECT COUNT(*) FROM cases WHERE lawyer_id=$uid AND status IN ('open','active','pending','reopened','on_hold')")->fetchColumn());
$totalCases = (int) ($pdo->query("SELECT COUNT(*) FROM cases WHERE lawyer_id=$uid")->fetchColumn());

$today = $pdo->prepare("SELECT * FROM appointments WHERE lawyer_id=? AND DATE(scheduled_at)=CURDATE() AND status IN ('scheduled','confirmed','rescheduled','pending')");
$today->execute([$uid]);
$todayCount = count($today->fetchAll());

$appointments = $pdo->prepare("SELECT a.*, CONCAT(c.first_name,' ',c.last_name) AS client_name FROM appointments a LEFT JOIN users c ON c.id=a.client_id WHERE a.lawyer_id=? AND a.scheduled_at >= NOW() AND a.status IN ('scheduled','confirmed','rescheduled','pending') ORDER BY a.scheduled_at LIMIT 30");
$appointments->execute([$uid]);
$appointments = $appointments->fetchAll();

$hearings = $pdo->prepare("SELECT h.*, c.case_number, c.title FROM court_hearings h JOIN cases c ON c.id=h.case_id WHERE c.lawyer_id=? AND h.hearing_date >= NOW() AND h.status='scheduled' ORDER BY h.hearing_date LIMIT 30");
$hearings->execute([$uid]);
$hearings = $hearings->fetchAll();

$pending = (int) ($pdo->query("SELECT COUNT(*) FROM appointments WHERE lawyer_id=$uid AND status='pending'")->fetchColumn());

$months = [];
for ($i = 11; $i >= 0; $i--) {
    $months[] = date('Y-m', strtotime("-{$i} months"));
}
$openedByMonth = array_fill_keys($months, 0);
$closedByMonth = array_fill_keys($months, 0);
$mo = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c FROM cases WHERE lawyer_id=? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY ym");
$mo->execute([$uid]);
foreach ($mo as $row) {
    if (isset($openedByMonth[$row['ym']])) {
        $openedByMonth[$row['ym']] = (int) $row['c'];
    }
}
$mcl = $pdo->prepare("SELECT DATE_FORMAT(COALESCE(closed_at, updated_at), '%Y-%m') AS ym, COUNT(*) AS c FROM cases WHERE lawyer_id=? AND status='closed' AND COALESCE(closed_at, updated_at) >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY ym");
$mcl->execute([$uid]);
foreach ($mcl as $row) {
    if (isset($closedByMonth[$row['ym']])) {
        $closedByMonth[$row['ym']] = (int) $row['c'];
    }
}

$thisMonthKey = date('Y-m');
$prevMonthKey = date('Y-m', strtotime('-1 month'));
$monthOpened = $openedByMonth[$thisMonthKey] ?? 0;
$prevMonthOpened = $openedByMonth[$prevMonthKey] ?? 0;
$balanceVar = 0.0;
if ($prevMonthOpened > 0) {
    $balanceVar = (($monthOpened - $prevMonthOpened) / $prevMonthOpened) * 100;
} elseif ($monthOpened > 0) {
    $balanceVar = 100.0;
}

$unread = unread_notifications($pdo, $uid);

$casesBar = min(100, (int) round(($activeCases / max($totalCases, 1)) * 100));
$apptBar = min(100, (int) round(($todayCount / max(count($appointments), 1)) * 100));
$hearBar = min(100, (int) round((count($hearings) / max(count($hearings) + $activeCases, 1)) * 100));

$aiPct = abs($balanceVar) >= 0.1 ? number_format(abs($balanceVar), 1) : number_format(max($casesBar * 0.1, 2.4), 1);
$aiCaption = abs($balanceVar) >= 0.1
    ? __($balanceVar >= 0 ? 'dashboard.ai_caption_rise' : 'dashboard.ai_caption_dip')
    : __('dashboard.ai_caption_ready');

$scheduleItems = [];
foreach ($hearings as $h) {
    $scheduleItems[] = ['kind' => 'hearing', 'sort' => strtotime((string) $h['hearing_date']) ?: PHP_INT_MAX, 'row' => $h];
}
foreach ($appointments as $a) {
    $scheduleItems[] = ['kind' => 'appointment', 'sort' => strtotime((string) $a['scheduled_at']) ?: PHP_INT_MAX, 'row' => $a];
}
usort($scheduleItems, static fn(array $a, array $b): int => $a['sort'] <=> $b['sort']);

ob_start();
if ($scheduleItems) {
    foreach ($scheduleItems as $item) {
        $r = $item['row'];
        if ($item['kind'] === 'hearing') { ?>
            <div class="glass-list-item" data-glass-page-row>
                <div class="glass-list-mark">H</div>
                <div class="glass-list-meta">
                    <strong><?= e($r['case_number']) ?></strong>
                    <span><?= e(format_datetime($r['hearing_date'])) ?></span>
                </div>
                <div class="glass-list-right">
                    <strong><?= e(t_content($r['court_name'] ?: __('common.court'))) ?></strong>
                    <span class="is-soft"><?= __e('nav.court') ?></span>
                </div>
            </div>
        <?php } else { ?>
            <div class="glass-list-item" data-glass-page-row>
                <div class="glass-list-mark">A</div>
                <div class="glass-list-meta">
                    <strong><?= e(t_content($r['title'])) ?></strong>
                    <span><?= e(format_datetime($r['scheduled_at'])) ?></span>
                </div>
                <div class="glass-list-right">
                    <strong><?= e($r['client_name'] ?: __('common.em_dash')) ?></strong>
                    <span class="is-soft"><?= e(translate_status((string) ($r['status'] ?? ''))) ?></span>
                </div>
            </div>
        <?php }
    }
} else { ?>
    <div class="empty-state"><?= __e('dashboard.empty.nothing_scheduled') ?></div>
<?php }
$sideRowsHtml = ob_get_clean();

ob_start();
if ($unread > 0) { ?>
    <a class="glass-notify-strip" href="notifications.php">
        <?= e(__($unread === 1 ? 'dashboard.notify.new_one' : 'dashboard.notify.new_many', ['count' => $unread])) ?>
    </a>
<?php }
$notifyHtml = ob_get_clean();

ob_start();
foreach ($cases as $c) {
    $isClosed = $c['status'] === 'closed';
    ?>
    <tr data-glass-page-row>
        <td>
            <div class="glass-tx-act">
                <span class="glass-tx-icon"><?= e(strtoupper(substr((string) $c['case_number'], 0, 1))) ?></span>
                <div>
                    <strong><?= e($c['case_number']) ?></strong>
                    <span><?= e(t_content($c['title'])) ?></span>
                </div>
            </div>
        </td>
        <td><?= e($c['client_name']) ?></td>
        <td><?= e(format_date($c['updated_at'])) ?></td>
        <td>
            <span class="glass-status <?= $isClosed ? 'is-pending' : 'is-ok' ?>"><i></i> <?= e(translate_status((string) $c['status'])) ?></span>
        </td>
    </tr>
<?php }
if (!$cases) { ?>
    <tr><td colspan="4" class="muted"><?= __e('common.no_records') ?></td></tr>
<?php }
$txRowsHtml = ob_get_clean();

$overviewLabels = array_slice(array_map('format_month_short', $months), -7);
$overviewValues = array_slice(array_values($openedByMonth), -7);

$gd = [
    'balance' => [
        'kicker' => __('lawyer.dash.active_caseload'),
        'value' => (string) $activeCases,
        'var' => $balanceVar,
        'chipLabel' => __('lawyer.dash.view_cases'),
        'chipUrl' => 'cases.php',
    ],
    'ai' => ['url' => 'ai.php', 'pct' => $aiPct, 'caption' => $aiCaption],
    'side' => [
        'title' => __('dashboard.panel.upcoming_schedule'),
        'viewUrl' => $hearings ? 'court.php' : 'appointments.php',
        'rowsHtml' => $sideRowsHtml,
        'pagerRows' => count($scheduleItems),
        'pagerPages' => max(1, (int) ceil(max(count($scheduleItems), 1) / 3)),
        'notifyHtml' => $notifyHtml,
    ],
    'minis' => [
        [
            'label' => __('lawyer.kpi.todays_appointments'),
            'value' => (string) $todayCount,
            'url' => 'appointments.php',
            'bar' => max(8, $apptBar),
            'barClass' => '',
            'foot' => __('lawyer.dash.requests', ['count' => $pending]),
        ],
        [
            'label' => __('lawyer.kpi.upcoming_hearings'),
            'value' => (string) count($hearings),
            'url' => 'court.php',
            'bar' => max(8, $hearBar),
            'barClass' => 'is-warn',
            'foot' => __('lawyer.dash.mini_cases', ['count' => $activeCases]),
        ],
    ],
    'overview' => [
        'title' => __('lawyer.dash.caseload_overview'),
        'svg' => build_overview_svg($overviewLabels, $overviewValues, __('dashboard.chart.aria_overview')),
    ],
    'tx' => [
        'title' => __('lawyer.dash.recent_cases'),
        'chip' => __('lawyer.dash.active_chip', ['count' => $activeCases]),
        'columns' => [__('common.case'), __('common.client'), __('common.date'), __('common.status')],
        'rowsHtml' => $txRowsHtml,
        'pagerPages' => max(1, (int) ceil(max(count($cases), 1) / 3)),
        'hasRows' => (bool) $cases,
    ],
    'chartData' => [
        'months' => array_map('format_month_short', $months),
        'monthKeys' => $months,
        'opened' => array_values($openedByMonth),
        'closed' => array_values($closedByMonth),
        'revenue' => array_fill(0, count($months), 0),
        'currency' => trim(app_config('currency_symbol') ?: 'Rs'),
        'chartAria' => __('dashboard.chart.aria_overview'),
        'chartRangeAria' => __('dashboard.chart.aria_range'),
    ],
];

$pageTitle = __('page.dashboard');
$pageSubtitle = __('ai.subtitle.lawyer');
$portal = 'lawyer';
$activeNav = 'dashboard';
$includeCharts = true;
$bodyClass = 'page-glass-dash';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/glass-dashboard.php';
require __DIR__ . '/../includes/footer.php';
?>
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
