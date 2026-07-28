<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'staff']);
$pdo = db();
$type = get('type', 'overview');

$clientReport = $pdo->query("SELECT c.id, c.first_name, c.last_name, c.company_name, COUNT(cs.id) AS case_count, COALESCE(SUM(i.total),0) AS billed FROM users c LEFT JOIN cases cs ON cs.client_id=c.id LEFT JOIN invoices i ON i.client_id=c.id WHERE c.role='client' GROUP BY c.id ORDER BY billed DESC")->fetchAll();
$lawyerReport = $pdo->query("SELECT l.id, l.first_name, l.last_name, l.specialization, SUM(CASE WHEN c.status!='closed' THEN 1 ELSE 0 END) AS open_cases, COUNT(c.id) AS total_cases FROM users l LEFT JOIN cases c ON c.lawyer_id=l.id WHERE l.role='lawyer' GROUP BY l.id ORDER BY open_cases DESC, total_cases DESC")->fetchAll();
$caseReport = $pdo->query("SELECT status, COUNT(*) AS total FROM cases GROUP BY status ORDER BY total DESC")->fetchAll();
$revenueReport = $pdo->query("SELECT DATE_FORMAT(paid_at,'%Y-%m') AS month, SUM(amount) AS total FROM payments GROUP BY DATE_FORMAT(paid_at,'%Y-%m') ORDER BY month DESC")->fetchAll();
$appointmentReport = $pdo->query("SELECT status, COUNT(*) AS total FROM appointments GROUP BY status ORDER BY total DESC")->fetchAll();
$paymentReport = $pdo->query("SELECT payment_method, COUNT(*) AS cnt, SUM(amount) AS total FROM payments GROUP BY payment_method ORDER BY total DESC")->fetchAll();
$caseDetailReport = $pdo->query("SELECT c.id, c.case_number, c.title, c.status, c.priority, c.filing_date, CONCAT(cl.first_name,' ',cl.last_name) AS client_name, CONCAT(l.first_name,' ',l.last_name) AS lawyer_name FROM cases c LEFT JOIN users cl ON cl.id=c.client_id LEFT JOIN users l ON l.id=c.lawyer_id ORDER BY c.filing_date DESC, c.id DESC")->fetchAll();
$appointmentDetailReport = $pdo->query("SELECT a.id, a.title, a.appointment_type, a.scheduled_at, a.status, a.location, CONCAT(c.first_name,' ',c.last_name) AS client_name, CONCAT(l.first_name,' ',l.last_name) AS lawyer_name, cs.case_number FROM appointments a LEFT JOIN users c ON c.id=a.client_id LEFT JOIN users l ON l.id=a.lawyer_id LEFT JOIN cases cs ON cs.id=a.case_id ORDER BY a.scheduled_at DESC, a.id DESC")->fetchAll();

$reportTabs = [
    'overview' => 'reports.tab.overview',
    'clients' => 'reports.tab.clients',
    'lawyers' => 'reports.tab.lawyers',
    'cases' => 'reports.tab.cases',
    'revenue' => 'reports.tab.revenue',
    'appointments' => 'reports.tab.appointments',
    'payments' => 'reports.tab.payments',
];

$totalCases = (int) array_sum(array_map(static fn(array $r): int => (int) $r['total'], $caseReport));
$totalRevenue = (float) array_sum(array_map(static fn(array $r): float => (float) $r['total'], $revenueReport));
$totalClients = count($clientReport);
$totalAppointments = (int) array_sum(array_map(static fn(array $r): int => (int) $r['total'], $appointmentReport));
$openCases = (int) array_sum(array_map(
    static fn(array $r): int => ($r['status'] ?? '') === 'closed' ? 0 : (int) $r['total'],
    $caseReport
));
$revenueChartRows = array_slice(array_reverse($revenueReport), -6);
$maxRevenue = max(1.0, ...array_map(static fn(array $r): float => (float) $r['total'], $revenueChartRows ?: [['total' => 1.0]]));
$topPageSize = 3;
$topClientsPage = max(1, (int) ($_GET['top_clients_page'] ?? 1));
$topLawyersPage = max(1, (int) ($_GET['top_lawyers_page'] ?? 1));

$reportPageSize = 10;
$paginateReportRows = static function (array $rows, int $page, int $pageSize): array {
    $total = count($rows);
    $totalPages = max(1, (int) ceil($total / $pageSize));
    $page = max(1, min($totalPages, $page));
    $from = $total > 0 ? (($page - 1) * $pageSize) + 1 : 0;
    $to = min($total, $page * $pageSize);

    return [
        'rows' => array_slice($rows, ($page - 1) * $pageSize, $pageSize),
        'page' => $page,
        'totalPages' => $totalPages,
        'total' => $total,
        'from' => $from,
        'to' => $to,
    ];
};

$topClientsPaged = $paginateReportRows($clientReport, $topClientsPage, $topPageSize);
$topLawyersPaged = $paginateReportRows($lawyerReport, $topLawyersPage, $topPageSize);
$topClientsPage = (int) $topClientsPaged['page'];
$topLawyersPage = (int) $topLawyersPaged['page'];
$topClients = $topClientsPaged['rows'];
$topLawyers = $topLawyersPaged['rows'];

$reportPage = max(1, (int) ($_GET['page'] ?? 1));
$clientsReportPaged = $paginateReportRows($clientReport, $reportPage, $reportPageSize);
$lawyersReportPaged = $paginateReportRows($lawyerReport, $reportPage, $reportPageSize);
$revenueReportPaged = $paginateReportRows($revenueReport, $reportPage, $reportPageSize);
$paymentsReportPaged = $paginateReportRows($paymentReport, $reportPage, $reportPageSize);
$casesReportPaged = $paginateReportRows($caseDetailReport, $reportPage, $reportPageSize);
$appointmentsReportPaged = $paginateReportRows($appointmentDetailReport, $reportPage, $reportPageSize);

$accent = get_setting($pdo, 'branding_accent', '#023e8a') ?: '#023e8a';
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? strtolower($accent) : '#023e8a';
$ar = hexdec(substr($accent, 1, 2));
$ag = hexdec(substr($accent, 3, 2));
$ab = hexdec(substr($accent, 5, 2));
$clampHex = static fn(int $v): int => max(0, min(255, $v));
$hexOf = static fn(int $r, int $g, int $b): string => sprintf('#%02x%02x%02x', $clampHex($r), $clampHex($g), $clampHex($b));
$accentBright = $hexOf($ar + 28, $ag + 28, $ab + 28);
$accentMid = $hexOf((int) round(($ar + 255) / 2), (int) round(($ag + 255) / 2), (int) round(($ab + 255) / 2));

$reportStatusTone = static function (string $status): string {
    return match ($status) {
        'open' => 'is-primary',
        'active' => 'is-success',
        'pending', 'rescheduled' => 'is-warning',
        'closed', 'cancelled' => 'is-muted',
        'on_hold' => 'is-hold',
        'reopened', 'confirmed', 'scheduled' => 'is-info',
        'completed' => 'is-success',
        default => 'is-primary',
    };
};

$reportStatusTileClass = static function (string $status): string {
    return match ($status) {
        'open' => 'reports-status-tile--open',
        'active', 'completed' => 'reports-status-tile--active',
        'pending', 'rescheduled' => 'reports-status-tile--pending',
        'closed', 'cancelled' => 'reports-status-tile--closed',
        'on_hold' => 'reports-status-tile--hold',
        'reopened', 'confirmed', 'scheduled' => 'reports-status-tile--scheduled',
        default => 'reports-status-tile--open',
    };
};

$reportStatusColor = static function (string $status): string {
    return match ($status) {
        'open' => 'var(--primary)',
        'active' => 'var(--success)',
        'pending', 'rescheduled' => 'var(--warning)',
        'closed', 'cancelled' => 'var(--ink-soft)',
        'on_hold' => 'var(--purple-bright)',
        'reopened', 'confirmed', 'scheduled' => 'var(--blue-bright)',
        default => 'var(--primary)',
    };
};

$personInitials = static function (array $row): string {
    return strtoupper(substr((string) ($row['first_name'] ?? ''), 0, 1) . substr((string) ($row['last_name'] ?? ''), 0, 1));
};

$donutStops = [];
$donutOffset = 0.0;
if ($totalCases > 0) {
    foreach ($caseReport as $row) {
        $pct = ((int) $row['total'] / $totalCases) * 100;
        if ($pct <= 0) {
            continue;
        }
        $color = $reportStatusColor((string) $row['status']);
        $donutStops[] = sprintf('%s %.2f%% %.2f%%', $color, $donutOffset, $donutOffset + $pct);
        $donutOffset += $pct;
    }
}
$donutStyle = $donutStops
    ? 'conic-gradient(' . implode(', ', $donutStops) . ')'
    : sprintf('conic-gradient(rgba(%d,%d,%d,0.14) 0 100%%)', $ar, $ag, $ab);

$monthLabel = date('F Y');

$pageTitle = __('page.reports');
$pageSubtitle = __('page.reports.subtitle');
$portal = 'admin';
$activeNav = 'reports';
$bodyClass = 'page-reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="reports-page">
    <header class="ih-banner">
        <div class="ih-banner-brand">
            <span class="ih-banner-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V5M4 19h16"/><path d="M8 15v-4M12 15V8M16 15v-6"/></svg>
            </span>
            <div>
                <h1><?= __e('reports.hub.title') ?></h1>
                <p><?= e(__('reports.hub.subtitle', ['month' => $monthLabel])) ?></p>
            </div>
        </div>
        <nav class="ih-banner-nav" aria-label="<?= __e('reports.hub.sections') ?>">
            <?php foreach ($reportTabs as $tabKey => $labelKey): ?>
                <a class="ih-nav-link<?= $type === $tabKey ? ' is-active' : '' ?>"
                   href="?type=<?= e($tabKey) ?>"
                   <?= $type === $tabKey ? 'aria-current="page"' : '' ?>>
                    <?= __e($labelKey) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>

    <?php if ($type === 'overview'): ?>
        <div class="reports-kpi-row">
            <article class="reports-kpi-tile reports-kpi-tile--cases">
                <span class="reports-kpi-tile-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><rect x="3" y="7" width="18" height="14" rx="2"/></svg>
                </span>
                <div class="reports-kpi-tile-body">
                    <span class="reports-kpi-tile-label"><?= __e('reports.kpi.total_cases') ?></span>
                    <strong class="reports-kpi-tile-value"><?= (int) $totalCases ?></strong>
                    <span class="reports-kpi-tile-foot"><?= __e('reports.kpi.open_cases') ?>: <?= (int) $openCases ?></span>
                </div>
            </article>

            <article class="reports-kpi-tile reports-kpi-tile--revenue">
                <span class="reports-kpi-tile-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="8"/><path d="M12 7v10M9.5 9.5c.6-1 1.5-1.5 2.5-1.5 1.4 0 2.5.9 2.5 2s-1.1 2-2.5 2-2.5.9-2.5 2 1.1 2 2.5 2c1 0 1.9-.5 2.5-1.5"/></svg>
                </span>
                <div class="reports-kpi-tile-body">
                    <span class="reports-kpi-tile-label"><?= __e('reports.kpi.total_revenue') ?></span>
                    <strong class="reports-kpi-tile-value is-money"><?= e(money($totalRevenue)) ?></strong>
                    <span class="reports-kpi-tile-foot"><?= __e('reports.kpi.trend') ?></span>
                </div>
            </article>

            <article class="reports-kpi-tile reports-kpi-tile--clients">
                <span class="reports-kpi-tile-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8" r="3.5"/><path d="M5 19.5c1.5-3.2 4-4.5 7-4.5s5.5 1.3 7 4.5"/></svg>
                </span>
                <div class="reports-kpi-tile-body">
                    <span class="reports-kpi-tile-label"><?= __e('reports.kpi.clients') ?></span>
                    <strong class="reports-kpi-tile-value"><?= (int) $totalClients ?></strong>
                </div>
            </article>

            <article class="reports-kpi-tile reports-kpi-tile--appointments">
                <span class="reports-kpi-tile-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                </span>
                <div class="reports-kpi-tile-body">
                    <span class="reports-kpi-tile-label"><?= __e('reports.kpi.appointments') ?></span>
                    <strong class="reports-kpi-tile-value"><?= (int) $totalAppointments ?></strong>
                </div>
            </article>
        </div>

        <div class="grid grid-2">
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2><?= __e('reports.cases_by_status') ?></h2>
                        <p class="muted"><?= __e('reports.cases_by_status_help') ?></p>
                    </div>
                </div>
                <?php if ($caseReport): ?>
                <div class="reports-split-chart">
                    <div class="reports-donut" style="background:<?= e($donutStyle) ?>">
                        <div class="reports-donut-hole">
                            <strong><?= (int) $totalCases ?></strong>
                            <span><?= __e('nav.cases') ?></span>
                        </div>
                    </div>
                    <ul class="reports-legend">
                        <?php foreach ($caseReport as $r):
                            $pct = $totalCases > 0 ? round(((int) $r['total'] / $totalCases) * 100) : 0;
                        ?>
                        <li>
                            <span class="reports-legend-dot <?= e($reportStatusTone((string) $r['status'])) ?>"></span>
                            <span><?= e(translate_status($r['status'])) ?></span>
                            <strong><?= (int) $r['total'] ?> <span class="muted">(<?= $pct ?>%)</span></strong>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php else: ?>
                <p class="muted reports-empty"><?= __e('reports.empty') ?></p>
                <?php endif; ?>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2><?= __e('reports.revenue_by_month') ?></h2>
                        <p class="muted"><?= __e('reports.revenue_by_month_help') ?></p>
                    </div>
                </div>
                <?php if ($revenueChartRows): ?>
                <div class="reports-column-chart">
                    <?php foreach ($revenueChartRows as $r):
                        $amount = (float) $r['total'];
                        $pct = max(10, (int) round(($amount / $maxRevenue) * 100));
                    ?>
                    <div class="reports-column">
                        <div class="reports-column-value"><?= e(money($amount)) ?></div>
                        <div class="reports-column-track">
                            <span class="reports-column-bar" style="height:<?= $pct ?>%"></span>
                        </div>
                        <div class="reports-column-label"><?= e(format_month_short((string) $r['month'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="muted reports-empty"><?= __e('reports.empty') ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-2">
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2><?= __e('reports.top_clients') ?></h2>
                        <p class="muted"><?= __e('reports.top_clients_help') ?></p>
                    </div>
                    <a class="btn btn-secondary btn-sm" href="?type=clients"><?= __e('reports.view_all') ?></a>
                </div>
                <?php if ($topClients): ?>
                <div class="list-stack">
                    <?php foreach ($topClients as $i => $r): ?>
                    <div class="list-item reports-list-item">
                        <div class="reports-person">
                            <span class="reports-avatar"><?= e($personInitials($r)) ?></span>
                            <div>
                                <strong><?= e(full_name($r)) ?></strong>
                                <span class="muted"><?= e($r['company_name'] ?: __('reports.no_company')) ?></span>
                            </div>
                        </div>
                        <strong><?= e(money($r['billed'])) ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php render_case_list_pager_slice(
                    $topClientsPaged,
                    'clients.pager.showing_one',
                    'clients.pager.showing_many',
                    'reports.pagination.top_clients',
                    static fn(int $p): string => '?top_clients_page=' . $p . '&top_lawyers_page=' . $topLawyersPage,
                    'reports-panel-foot',
                    true
                ); ?>
                <?php else: ?>
                <p class="muted reports-empty"><?= __e('reports.empty') ?></p>
                <?php endif; ?>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2><?= __e('reports.top_lawyers') ?></h2>
                        <p class="muted"><?= __e('reports.top_lawyers_help') ?></p>
                    </div>
                    <a class="btn btn-secondary btn-sm" href="?type=lawyers"><?= __e('reports.view_all') ?></a>
                </div>
                <?php if ($topLawyers): ?>
                <div class="list-stack">
                    <?php foreach ($topLawyers as $r): ?>
                    <div class="list-item reports-list-item">
                        <div class="reports-person">
                            <span class="reports-avatar is-lawyer"><?= e($personInitials($r)) ?></span>
                            <div>
                                <strong><?= e(full_name($r)) ?></strong>
                                <span class="muted"><?= e($r['specialization'] ? t_content($r['specialization']) : __('common.em_dash')) ?></span>
                            </div>
                        </div>
                        <span class="badge badge-warning"><?= (int) $r['open_cases'] ?> <?= __e('common.open_count') ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php render_case_list_pager_slice(
                    $topLawyersPaged,
                    'lawyers.pager.showing_one',
                    'lawyers.pager.showing_many',
                    'reports.pagination.top_lawyers',
                    static fn(int $p): string => '?top_clients_page=' . $topClientsPage . '&top_lawyers_page=' . $p,
                    'reports-panel-foot',
                    true
                ); ?>
                <?php else: ?>
                <p class="muted reports-empty"><?= __e('reports.empty') ?></p>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($type === 'clients'): ?>
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2><?= __e('reports.heading.clients') ?></h2>
                    <p class="muted"><?= __e('reports.heading.clients_help') ?></p>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th><?= __e('common.client') ?></th><th><?= __e('common.company') ?></th><th><?= __e('nav.cases') ?></th><th><?= __e('common.billed') ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($clientsReportPaged['rows'] as $r): ?>
                        <tr>
                            <td>
                                <div class="reports-person">
                                    <span class="reports-avatar"><?= e($personInitials($r)) ?></span>
                                    <strong><?= e(full_name($r)) ?></strong>
                                </div>
                            </td>
                            <td><?= e($r['company_name'] ?: __('common.em_dash')) ?></td>
                            <td><?= (int) $r['case_count'] ?></td>
                            <td><strong><?= e(money($r['billed'])) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$clientsReportPaged['total']): ?><tr><td colspan="4" class="muted reports-empty-cell"><?= __e('reports.empty') ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php render_case_list_pager_slice(
                $clientsReportPaged,
                'clients.pager.showing_one',
                'clients.pager.showing_many',
                'reports.pagination.clients',
                static fn(int $p): string => '?type=clients&page=' . $p,
                'reports-panel-foot',
                true
            ); ?>
        </div>

    <?php elseif ($type === 'lawyers'): ?>
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2><?= __e('reports.heading.lawyers') ?></h2>
                    <p class="muted"><?= __e('reports.heading.lawyers_help') ?></p>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th><?= __e('common.lawyer') ?></th><th><?= __e('common.specialization') ?></th><th><?= __e('common.open_count') ?></th><th><?= __e('common.total') ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($lawyersReportPaged['rows'] as $r): ?>
                        <tr>
                            <td>
                                <div class="reports-person">
                                    <span class="reports-avatar is-lawyer"><?= e($personInitials($r)) ?></span>
                                    <strong><?= e(full_name($r)) ?></strong>
                                </div>
                            </td>
                            <td><?= e($r['specialization'] ? t_content($r['specialization']) : __('common.em_dash')) ?></td>
                            <td><?= (int) $r['open_cases'] ?></td>
                            <td><?= (int) $r['total_cases'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$lawyersReportPaged['total']): ?><tr><td colspan="4" class="muted reports-empty-cell"><?= __e('reports.empty') ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php render_case_list_pager_slice(
                $lawyersReportPaged,
                'lawyers.pager.showing_one',
                'lawyers.pager.showing_many',
                'reports.pagination.lawyers',
                static fn(int $p): string => '?type=lawyers&page=' . $p,
                'reports-panel-foot',
                true
            ); ?>
        </div>

    <?php elseif ($type === 'cases'): ?>
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2><?= __e('reports.heading.cases') ?></h2>
                    <p class="muted"><?= __e('reports.heading.cases_help') ?></p>
                </div>
            </div>
            <?php if ($caseReport): ?>
            <div class="reports-kpi-row reports-status-row">
                <?php foreach ($caseReport as $r): ?>
                <article class="reports-kpi-tile reports-kpi-tile--compact <?= e($reportStatusTileClass((string) $r['status'])) ?>">
                    <div class="reports-kpi-tile-body">
                        <span class="reports-kpi-tile-label"><?= e(translate_status($r['status'])) ?></span>
                        <strong class="reports-kpi-tile-value"><?= (int) $r['total'] ?></strong>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="reports-detail-section">
                <h3 class="reports-detail-heading"><?= __e('reports.cases.all_matters') ?></h3>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th><?= __e('common.case') ?></th><th><?= __e('common.client') ?></th><th><?= __e('common.lawyer') ?></th><th><?= __e('common.status') ?></th><th><?= __e('common.priority') ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($casesReportPaged['rows'] as $r): ?>
                            <tr>
                                <td>
                                    <strong><?= e($r['case_number']) ?></strong>
                                    <span class="muted reports-subtle"><?= e($r['title']) ?></span>
                                </td>
                                <td><?= e($r['client_name'] ?: __('common.em_dash')) ?></td>
                                <td><?= e($r['lawyer_name'] ?: __('common.unassigned')) ?></td>
                                <td><?= status_badge($r['status']) ?></td>
                                <td><?= status_badge($r['priority']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$casesReportPaged['total']): ?><tr><td colspan="5" class="muted reports-empty-cell"><?= __e('reports.empty') ?></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php render_case_list_pager_slice(
                    $casesReportPaged,
                    'reports.pager.cases_one',
                    'reports.pager.cases_many',
                    'reports.pagination.cases',
                    static fn(int $p): string => '?type=cases&page=' . $p,
                    'reports-panel-foot',
                    true
                ); ?>
            </div>
        </div>

    <?php elseif ($type === 'revenue'): ?>
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2><?= __e('reports.heading.revenue') ?></h2>
                    <p class="muted"><?= __e('reports.heading.revenue_help') ?></p>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th><?= __e('common.month') ?></th><th><?= __e('finance.revenue') ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($revenueReportPaged['rows'] as $r): ?>
                        <tr>
                            <td><strong><?= e(format_month_short((string) $r['month'])) ?></strong><span class="muted reports-subtle"><?= e($r['month']) ?></span></td>
                            <td><strong><?= e(money($r['total'])) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$revenueReportPaged['total']): ?><tr><td colspan="2" class="muted reports-empty-cell"><?= __e('reports.empty') ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php render_case_list_pager_slice(
                $revenueReportPaged,
                'reports.pager.revenue_one',
                'reports.pager.revenue_many',
                'reports.pagination.revenue',
                static fn(int $p): string => '?type=revenue&page=' . $p,
                'reports-panel-foot',
                true
            ); ?>
        </div>

    <?php elseif ($type === 'appointments'): ?>
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2><?= __e('reports.heading.appointments') ?></h2>
                    <p class="muted"><?= __e('reports.heading.appointments_help') ?></p>
                </div>
            </div>
            <?php if ($appointmentReport): ?>
            <div class="reports-kpi-row reports-status-row">
                <?php foreach ($appointmentReport as $r): ?>
                <article class="reports-kpi-tile reports-kpi-tile--compact <?= e($reportStatusTileClass((string) $r['status'])) ?>">
                    <div class="reports-kpi-tile-body">
                        <span class="reports-kpi-tile-label"><?= e(translate_status($r['status'])) ?></span>
                        <strong class="reports-kpi-tile-value"><?= (int) $r['total'] ?></strong>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="reports-detail-section">
                <h3 class="reports-detail-heading"><?= __e('reports.appointments.all_bookings') ?></h3>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th><?= __e('common.title') ?></th><th><?= __e('common.client') ?></th><th><?= __e('common.lawyer') ?></th><th><?= __e('appointments.col.datetime') ?></th><th><?= __e('common.status') ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($appointmentsReportPaged['rows'] as $r): ?>
                            <tr>
                                <td>
                                    <strong><?= e($r['title']) ?></strong>
                                    <span class="muted reports-subtle"><?= e(__('appointment.type.' . $r['appointment_type'])) ?><?= $r['case_number'] ? ' · ' . e($r['case_number']) : '' ?></span>
                                </td>
                                <td><?= e($r['client_name'] ?: __('common.em_dash')) ?></td>
                                <td><?= e($r['lawyer_name'] ?: __('common.unassigned')) ?></td>
                                <td><?= e(format_datetime($r['scheduled_at'])) ?></td>
                                <td><?= status_badge($r['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$appointmentsReportPaged['total']): ?><tr><td colspan="5" class="muted reports-empty-cell"><?= __e('reports.empty') ?></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php render_case_list_pager_slice(
                    $appointmentsReportPaged,
                    'reports.pager.appointments_one',
                    'reports.pager.appointments_many',
                    'reports.pagination.appointments',
                    static fn(int $p): string => '?type=appointments&page=' . $p,
                    'reports-panel-foot',
                    true
                ); ?>
            </div>
        </div>

    <?php elseif ($type === 'payments'): ?>
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2><?= __e('reports.heading.payments') ?></h2>
                    <p class="muted"><?= __e('reports.heading.payments_help') ?></p>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th><?= __e('finance.method') ?></th><th><?= __e('common.count') ?></th><th><?= __e('common.total') ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($paymentsReportPaged['rows'] as $r): ?>
                        <tr>
                            <td><strong><?= e(__('payment.method.' . $r['payment_method'])) ?></strong></td>
                            <td><?= (int) $r['cnt'] ?></td>
                            <td><strong><?= e(money($r['total'])) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$paymentsReportPaged['total']): ?><tr><td colspan="3" class="muted reports-empty-cell"><?= __e('reports.empty') ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php render_case_list_pager_slice(
                $paymentsReportPaged,
                'reports.pager.payments_one',
                'reports.pager.payments_many',
                'reports.pagination.payments',
                static fn(int $p): string => '?type=payments&page=' . $p,
                'reports-panel-foot',
                true
            ); ?>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
