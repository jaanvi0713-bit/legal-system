<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['client']);
$pdo = db();
$user = current_user();
$uid = (int) $user['id'];

$cases = $pdo->prepare("SELECT c.*, CONCAT(l.first_name,' ',l.last_name) AS lawyer_name FROM cases c LEFT JOIN users l ON l.id=c.lawyer_id WHERE c.client_id=? ORDER BY c.updated_at DESC");
$cases->execute([$uid]);
$cases = $cases->fetchAll();
$activeCases = array_values(array_filter($cases, static fn($c) => $c['status'] !== 'closed'));
$closedCases = count($cases) - count($activeCases);

$lawyer = null;
if ($user['assigned_lawyer_id']) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
    $stmt->execute([$user['assigned_lawyer_id']]);
    $lawyer = $stmt->fetch();
}

$docCount = (int) $pdo->query('SELECT COUNT(*) FROM case_documents WHERE client_id=' . $uid)->fetchColumn();

$appointments = $pdo->prepare("SELECT a.*, CONCAT(l.first_name,' ',l.last_name) AS lawyer_name FROM appointments a LEFT JOIN users l ON l.id=a.lawyer_id WHERE a.client_id=? AND a.scheduled_at >= NOW() AND a.status IN ('scheduled','confirmed','rescheduled','pending') ORDER BY a.scheduled_at LIMIT 30");
$appointments->execute([$uid]);
$appointments = $appointments->fetchAll();

$hearings = $pdo->prepare("SELECT h.*, c.case_number, c.title FROM court_hearings h JOIN cases c ON c.id=h.case_id WHERE c.client_id=? AND h.hearing_date >= NOW() AND h.status='scheduled' ORDER BY h.hearing_date LIMIT 30");
$hearings->execute([$uid]);
$hearings = $hearings->fetchAll();

$outstandingStmt = $pdo->prepare("SELECT COALESCE(SUM(i.total - IFNULL((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id=i.id),0)),0) FROM invoices i WHERE i.client_id=? AND i.status IN ('sent','partial','overdue','draft')");
$outstandingStmt->execute([$uid]);
$outstanding = (float) $outstandingStmt->fetchColumn();

$invoicesOpen = (int) ($pdo->query("SELECT COUNT(*) FROM invoices WHERE client_id=$uid AND status IN ('sent','partial','overdue')")->fetchColumn());
$totalPaidStmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE client_id=?');
$totalPaidStmt->execute([$uid]);
$totalPaid = (float) $totalPaidStmt->fetchColumn();

$monthPaidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE client_id=? AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$monthPaidStmt->execute([$uid]);
$monthPaid = (float) $monthPaidStmt->fetchColumn();
$prevMonthPaidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE client_id=? AND paid_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND paid_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$prevMonthPaidStmt->execute([$uid]);
$prevMonthPaid = (float) $prevMonthPaidStmt->fetchColumn();

$payments = $pdo->prepare('SELECT p.*, i.invoice_number, i.status AS invoice_status FROM payments p LEFT JOIN invoices i ON i.id=p.invoice_id WHERE p.client_id=? ORDER BY p.paid_at DESC LIMIT 30');
$payments->execute([$uid]);
$payments = $payments->fetchAll();

$months = [];
for ($i = 11; $i >= 0; $i--) {
    $months[] = date('Y-m', strtotime("-{$i} months"));
}
$paidByMonth = array_fill_keys($months, 0.0);
$openedByMonth = array_fill_keys($months, 0);
$mp = $pdo->prepare("SELECT DATE_FORMAT(paid_at, '%Y-%m') AS ym, COALESCE(SUM(amount),0) AS total FROM payments WHERE client_id=? AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY ym");
$mp->execute([$uid]);
foreach ($mp as $row) {
    if (isset($paidByMonth[$row['ym']])) {
        $paidByMonth[$row['ym']] = (float) $row['total'];
    }
}
$mc = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c FROM cases WHERE client_id=? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY ym");
$mc->execute([$uid]);
foreach ($mc as $row) {
    if (isset($openedByMonth[$row['ym']])) {
        $openedByMonth[$row['ym']] = (int) $row['c'];
    }
}

$unread = unread_notifications($pdo, $uid);

$balanceVar = 0.0;
if ($prevMonthPaid > 0) {
    $balanceVar = (($monthPaid - $prevMonthPaid) / $prevMonthPaid) * 100;
} elseif ($monthPaid > 0) {
    $balanceVar = 100.0;
}
$caseShare = max(count($cases), 1);
$casesBar = min(100, (int) round((count($activeCases) / $caseShare) * 100));
$collectTarget = max($totalPaid + $outstanding, 1);
$collectBar = min(100, (int) round(($outstanding / $collectTarget) * 100));

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
                    <strong><?= e($r['lawyer_name'] ?: __('common.em_dash')) ?></strong>
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
foreach ($payments as $p) {
    $isPending = ($p['invoice_status'] ?? '') === 'partial';
    $status = $isPending ? __('common.pending') : __('dashboard.status.success');
    $label = $p['invoice_number'] ?: ($p['receipt_number'] ?: __('finance.receipt'));
    ?>
    <tr data-glass-page-row>
        <td>
            <div class="glass-tx-act">
                <span class="glass-tx-icon"><?= e(strtoupper(substr((string) $label, 0, 1))) ?></span>
                <div>
                    <strong><?= e($label) ?></strong>
                    <span><?= e(__('payment.method.' . $p['payment_method'])) ?></span>
                </div>
            </div>
        </td>
        <td><?= e(format_date($p['paid_at'])) ?></td>
        <td><?= e(money($p['amount'])) ?></td>
        <td>
            <span class="glass-status <?= $isPending ? 'is-pending' : 'is-ok' ?>"><i></i> <?= e($status) ?></span>
        </td>
    </tr>
<?php }
if (!$payments) { ?>
    <tr><td colspan="4" class="muted"><?= __e('dashboard.empty.no_payments') ?></td></tr>
<?php }
$txRowsHtml = ob_get_clean();

$overviewValues = array_slice(array_values($paidByMonth), -7);
$overviewLabels = array_slice(array_map('format_month_short', $months), -7);
if (array_sum($overviewValues) <= 0) {
    $overviewValues = array_slice(array_values($openedByMonth), -7);
}

$gd = [
    'balance' => [
        'kicker' => __('client.dash.outstanding_balance'),
        'value' => money($outstanding),
        'var' => $balanceVar,
        'chipLabel' => __('client.dash.view_payments'),
        'chipUrl' => 'payments.php',
    ],
    'ai' => ['url' => 'payments.php', 'pct' => $aiPct, 'caption' => $aiCaption],
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
            'label' => __('client.kpi.active_cases'),
            'value' => '+' . count($activeCases),
            'url' => 'cases.php',
            'bar' => $casesBar,
            'barClass' => '',
            'foot' => __('client.dash.mini_cases', ['docs' => $docCount, 'lawyer' => $lawyer ? full_name($lawyer) : __('lawyer.tba')]),
        ],
        [
            'label' => __('finance.outstanding'),
            'value' => money($outstanding),
            'url' => 'payments.php',
            'bar' => max(8, $collectBar),
            'barClass' => 'is-warn',
            'foot' => $invoicesOpen . ' ' . __('dashboard.kpi.open_invoices'),
        ],
    ],
    'overview' => [
        'title' => __('client.dash.spending_overview'),
        'svg' => build_overview_svg($overviewLabels, $overviewValues, __('dashboard.chart.aria_overview')),
    ],
    'tx' => [
        'title' => __('client.dash.payment_history'),
        'chip' => __('dashboard.month_chip', ['amount' => money($monthPaid)]),
        'columns' => [__('dashboard.col.activity'), __('common.date'), __('common.amount'), __('common.status')],
        'rowsHtml' => $txRowsHtml,
        'pagerPages' => max(1, (int) ceil(max(count($payments), 1) / 3)),
        'hasRows' => (bool) $payments,
    ],
    'chartData' => [
        'months' => array_map('format_month_short', $months),
        'monthKeys' => $months,
        'opened' => array_values($openedByMonth),
        'closed' => array_fill(0, count($months), 0),
        'revenue' => array_values($paidByMonth),
        'currency' => trim(app_config('currency_symbol') ?: 'Rs'),
        'chartAria' => __('dashboard.chart.aria_overview'),
        'chartRangeAria' => __('dashboard.chart.aria_range'),
    ],
];

$pageTitle = __('page.dashboard');
$pageSubtitle = __('ai.subtitle.client');
$portal = 'client';
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
