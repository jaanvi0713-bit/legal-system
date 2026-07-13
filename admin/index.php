<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'staff']);
$pdo = db();
$user = current_user();

$stats = [
    'clients' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn(),
    'lawyers' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='lawyer'")->fetchColumn(),
    'active_cases' => (int) $pdo->query("SELECT COUNT(*) FROM cases WHERE status IN ('open','active','pending','reopened','on_hold')")->fetchColumn(),
    'closed_cases' => (int) $pdo->query("SELECT COUNT(*) FROM cases WHERE status='closed'")->fetchColumn(),
    'revenue' => (float) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM payments')->fetchColumn(),
    'messages' => (int) $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn(),
    'appointments' => (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE status NOT IN ('cancelled','rejected')")->fetchColumn(),
    'hearings' => (int) $pdo->query("SELECT COUNT(*) FROM court_hearings WHERE status='scheduled' AND hearing_date >= NOW()")->fetchColumn(),
    'invoices_open' => (int) $pdo->query("SELECT COUNT(*) FROM invoices WHERE status IN ('sent','partial','overdue')")->fetchColumn(),
];

$months = [];
for ($i = 6; $i >= 0; $i--) {
    $months[] = date('Y-m', strtotime("-{$i} months"));
}
$openedByMonth = array_fill_keys($months, 0);
$closedByMonth = array_fill_keys($months, 0);
$revenueByMonth = array_fill_keys($months, 0);

foreach ($pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c FROM cases WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym") as $row) {
    if (isset($openedByMonth[$row['ym']])) {
        $openedByMonth[$row['ym']] = (int) $row['c'];
    }
}
foreach ($pdo->query("SELECT DATE_FORMAT(COALESCE(closed_at, updated_at), '%Y-%m') AS ym, COUNT(*) AS c FROM cases WHERE status='closed' AND COALESCE(closed_at, updated_at) >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym") as $row) {
    if (isset($closedByMonth[$row['ym']])) {
        $closedByMonth[$row['ym']] = (int) $row['c'];
    }
}
foreach ($pdo->query("SELECT DATE_FORMAT(paid_at, '%Y-%m') AS ym, COALESCE(SUM(amount),0) AS total FROM payments WHERE paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym") as $row) {
    if (isset($revenueByMonth[$row['ym']])) {
        $revenueByMonth[$row['ym']] = (float) $row['total'];
    }
}

$typeRows = $pdo->query("SELECT COALESCE(NULLIF(case_type,''), 'Other') AS label, COUNT(*) AS c FROM cases GROUP BY label ORDER BY c DESC")->fetchAll();
$typeLabels = array_column($typeRows, 'label') ?: ['No data'];
$typeCounts = array_map('intval', array_column($typeRows, 'c') ?: [1]);
$typeTotal = max(array_sum($typeCounts), 1);

$weekdayCounts = array_fill(0, 7, 0);
foreach ($pdo->query("SELECT WEEKDAY(scheduled_at) AS wd, COUNT(*) AS c FROM appointments GROUP BY wd") as $row) {
    $weekdayCounts[(int) $row['wd']] = (int) $row['c'];
}

$recentCases = $pdo->query("SELECT c.*, CONCAT(u.first_name,' ',u.last_name) AS client_name, CONCAT(l.first_name,' ',l.last_name) AS lawyer_name FROM cases c JOIN users u ON u.id=c.client_id LEFT JOIN users l ON l.id=c.lawyer_id ORDER BY c.updated_at DESC LIMIT 6")->fetchAll();
$payments = $pdo->query('SELECT p.*, u.first_name, u.last_name FROM payments p JOIN users u ON u.id = p.client_id ORDER BY p.paid_at DESC LIMIT 5')->fetchAll();
$appointments = $pdo->query("SELECT a.*, CONCAT(c.first_name,' ',c.last_name) AS client_name FROM appointments a LEFT JOIN users c ON c.id = a.client_id WHERE a.scheduled_at >= NOW() AND a.status NOT IN ('cancelled','rejected') ORDER BY a.scheduled_at ASC LIMIT 5")->fetchAll();
$hearings = $pdo->query("SELECT h.*, c.case_number, c.title FROM court_hearings h JOIN cases c ON c.id=h.case_id WHERE h.status='scheduled' AND h.hearing_date >= NOW() ORDER BY h.hearing_date ASC LIMIT 5")->fetchAll();

$totalCases = max($stats['active_cases'] + $stats['closed_cases'], 1);
$activePct = (int) round(($stats['active_cases'] / $totalCases) * 100);
$closedPct = (int) round(($stats['closed_cases'] / $totalCases) * 100);

$chartData = [
    'months' => array_map(fn($m) => date('M', strtotime($m . '-01')), $months),
    'opened' => array_values($openedByMonth),
    'closed' => array_values($closedByMonth),
    'revenue' => array_values($revenueByMonth),
    'types' => ['labels' => $typeLabels, 'values' => $typeCounts],
    'status' => ['active' => $stats['active_cases'], 'closed' => $stats['closed_cases']],
    'weekdays' => $weekdayCounts,
];

$pageTitle = 'Dashboard';
$pageSubtitle = 'Overview of cases, clients, and firm performance';
$portal = 'admin';
$activeNav = 'dashboard';
$includeCharts = true;
require __DIR__ . '/../includes/header.php';
?>
<section class="welcome-banner">
    <div>
        <div class="eyebrow"><?= e(date('l, F j, Y')) ?></div>
        <h2>Welcome back, <?= e($user['first_name']) ?></h2>
        <p>Track active matters, hearings, billing, and team workload in one place.</p>
    </div>
    <div class="welcome-actions">
        <a class="btn btn-accent btn-sm" href="cases.php?action=create">New case</a>
        <a class="btn btn-accent btn-sm" href="appointments.php?action=create">Book appointment</a>
        <a class="btn btn-accent btn-sm" href="reports.php">View reports</a>
    </div>
</section>

<section class="kpi-grid">
    <article class="kpi-card">
        <div class="kpi-top">
            <div class="kpi-icon primary">C</div>
            <div class="kpi-meta">
                <div class="kpi-label">Active cases</div>
                <div class="kpi-value"><?= (int) $stats['active_cases'] ?></div>
            </div>
        </div>
        <div class="kpi-foot"><span class="kpi-delta up"><?= $activePct ?>%</span> of all firm matters</div>
    </article>
    <article class="kpi-card">
        <div class="kpi-top">
            <div class="kpi-icon info">U</div>
            <div class="kpi-meta">
                <div class="kpi-label">Clients</div>
                <div class="kpi-value"><?= (int) $stats['clients'] ?></div>
            </div>
        </div>
        <div class="kpi-foot"><span class="kpi-delta up"><?= (int) $stats['lawyers'] ?></span> lawyers available</div>
    </article>
    <article class="kpi-card">
        <div class="kpi-top">
            <div class="kpi-icon success">$</div>
            <div class="kpi-meta">
                <div class="kpi-label">Total revenue</div>
                <div class="kpi-value" style="font-size:1.25rem;"><?= e(money($stats['revenue'])) ?></div>
            </div>
        </div>
        <div class="kpi-foot"><span class="kpi-delta up"><?= (int) $stats['invoices_open'] ?></span> open invoices</div>
    </article>
    <article class="kpi-card">
        <div class="kpi-top">
            <div class="kpi-icon warning">H</div>
            <div class="kpi-meta">
                <div class="kpi-label">Upcoming hearings</div>
                <div class="kpi-value"><?= (int) $stats['hearings'] ?></div>
            </div>
        </div>
        <div class="kpi-foot"><span class="kpi-delta down"><?= (int) $stats['appointments'] ?></span> appointments booked</div>
    </article>
</section>

<section class="dash-main">
    <div class="chart-card">
        <div class="panel-header">
            <div>
                <h2>Case overview</h2>
                <p class="muted" style="margin:0;">Opened vs closed matters · last 7 months</p>
            </div>
            <a href="cases.php">Manage cases</a>
        </div>
        <div class="chart-wrap lg"><canvas id="chartCases"></canvas></div>
    </div>
    <div class="dash-side">
        <div class="chart-card">
            <div class="panel-header">
                <h2>Case status mix</h2>
                <span class="muted"><?= $closedPct ?>% closed</span>
            </div>
            <div class="donut-row" style="position:relative;">
                <div class="chart-wrap sm" style="position:relative;">
                    <canvas id="chartStatus"></canvas>
                    <div class="donut-center-label">
                        <div>
                            <strong><?= (int) $totalCases ?></strong>
                            <span>Total cases</span>
                        </div>
                    </div>
                </div>
                <div class="chart-legend">
                    <span><i style="background:var(--primary)"></i>Active <?= (int) $stats['active_cases'] ?></span>
                    <span><i style="background:var(--info)"></i>Closed <?= (int) $stats['closed_cases'] ?></span>
                </div>
            </div>
        </div>
        <div class="panel">
            <div class="panel-header"><h2>Workload by type</h2></div>
            <div class="progress-list">
                <?php foreach ($typeRows as $i => $type):
                    $pct = (int) round(((int) $type['c'] / $typeTotal) * 100);
                    $barClass = ['', 'info', 'success', 'warning'][$i % 4];
                ?>
                    <div class="progress-item">
                        <div class="row"><strong><?= e($type['label']) ?></strong><span><?= (int) $type['c'] ?> · <?= $pct ?>%</span></div>
                        <div class="progress-bar <?= e($barClass) ?>"><span style="width:<?= $pct ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$typeRows): ?><div class="empty-state">No case types yet.</div><?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="dash-bottom">
    <div class="panel">
        <div class="panel-header">
            <h2>Recent cases</h2>
            <a href="cases.php">See all</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Case</th><th>Client</th><th>Lawyer</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($recentCases as $c): ?>
                    <tr>
                        <td>
                            <strong><a href="cases.php?id=<?= (int) $c['id'] ?>"><?= e($c['case_number']) ?></a></strong>
                            <div class="muted" style="font-size:0.8rem;"><?= e($c['title']) ?></div>
                        </td>
                        <td><?= e($c['client_name']) ?></td>
                        <td><?= e($c['lawyer_name'] ?: 'Unassigned') ?></td>
                        <td><?= status_badge($c['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="chart-card">
        <div class="panel-header">
            <h2>Appointments by weekday</h2>
            <a href="appointments.php">Calendar</a>
        </div>
        <div class="chart-wrap md"><canvas id="chartWeekdays"></canvas></div>
    </div>
</section>

<section class="dash-lists">
    <div class="panel">
        <div class="panel-header"><h2>Revenue collections</h2><a href="finance.php">Finance</a></div>
        <div class="chart-wrap sm" style="margin-bottom:1rem;"><canvas id="chartRevenue"></canvas></div>
        <div class="list-stack">
            <?php foreach ($payments as $p): ?>
                <div class="list-item">
                    <strong><?= e(full_name($p)) ?></strong>
                    <span class="muted"><?= e(money($p['amount'])) ?> · <?= e(format_datetime($p['paid_at'])) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$payments): ?><div class="empty-state">No payments recorded.</div><?php endif; ?>
        </div>
    </div>
    <div class="panel">
        <div class="panel-header"><h2>Upcoming schedule</h2><a href="court.php">Court tracking</a></div>
        <div class="list-stack">
            <?php foreach ($hearings as $h): ?>
                <div class="list-item">
                    <strong>Hearing · <?= e($h['case_number']) ?></strong>
                    <span class="muted"><?= e(format_datetime($h['hearing_date'])) ?> · <?= e($h['court_name']) ?></span>
                </div>
            <?php endforeach; ?>
            <?php foreach ($appointments as $a): ?>
                <div class="list-item">
                    <strong><?= e($a['title']) ?></strong>
                    <span class="muted"><?= e(format_datetime($a['scheduled_at'])) ?> · <?= e($a['client_name'] ?? '—') ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$hearings && !$appointments): ?><div class="empty-state">Nothing scheduled.</div><?php endif; ?>
        </div>
    </div>
</section>

<script>
window.LEXORA_DASHBOARD = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
