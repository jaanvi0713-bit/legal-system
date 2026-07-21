<?php
/**
 * Business Insights — Intelligence hub (admin / lawyer / client).
 */

function insights_health_ring(int $score): string
{
    $pct = max(0, min(100, $score));
    $r = 15.9155;
    return '<svg class="ih-health-ring" viewBox="0 0 36 36" aria-hidden="true">'
        . '<path class="ih-health-ring-bg" d="M18 2.0845 a ' . $r . ' ' . $r . ' 0 0 1 0 31.831 a ' . $r . ' ' . $r . ' 0 0 1 0 -31.831"/>'
        . '<path class="ih-health-ring-fg" stroke-dasharray="' . e($pct . ', 100') . '" d="M18 2.0845 a ' . $r . ' ' . $r . ' 0 0 1 0 31.831 a ' . $r . ' ' . $r . ' 0 0 1 0 -31.831"/>'
        . '</svg>';
}

function insights_svg(string $name): string
{
    $icons = [
        'chart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V5M4 19h16"/><path d="M8 16v-5M12 16V8M16 16v-3"/><path d="M8 16l3-4 2.5 2 3.5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'alert' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4M12 17h.01"/><path d="M10.3 4.3 2.8 17a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0z"/></svg>',
        'flame' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3c2 3 1 5 1 5s3-1 4 2a5 5 0 1 1-9.5 2C6 8 9 6 12 3z"/></svg>',
        'coin' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path d="M12 7v10M9.5 9.5c.6-1 1.5-1.5 2.5-1.5s2 .7 2 1.8-1 1.7-2.5 1.7S9.5 12.5 9.5 13.7 10.6 15.5 12 15.5c1 0 1.9-.5 2.5-1.3"/></svg>',
        'trend' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 16l5-5 4 3 7-8"/><path d="M14 6h6v6"/></svg>',
        'bolt' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 2 4 14h7l-1 8 9-12h-7l1-8z"/></svg>',
        'cases' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><rect x="3" y="7" width="18" height="14" rx="2"/></svg>',
        'invoice' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 3h6l5 5v13H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>',
        'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.4"/><path d="M3.5 19c1.2-2.8 3.4-4.2 5.5-4.2S13.3 16.2 14.5 19M14 15.2c1.5-.5 3.1-.2 4.5 1.3"/></svg>',
        'ads' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12h4l3-7 3 14 3-7h3"/></svg>',
        'erp' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg>',
        'court' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v3M5 9h14M7 9v10M17 9v10M4 19h16M9 13h6"/></svg>',
        'doc' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 3h6l5 5v13H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v5h5"/></svg>',
    ];
    return $icons[$name] ?? $icons['chart'];
}

function render_insights_page(string $portal): void
{
    $pdo = db();
    $user = current_user();
    $uid = (int) $user['id'];
    $months6 = [];
    for ($i = 5; $i >= 0; $i--) {
        $months6[] = date('Y-m', strtotime("-{$i} months"));
    }

    if ($portal === 'admin') {
        require_role(['admin', 'staff']);
        $data = insights_hub_admin($pdo, $months6);
    } elseif ($portal === 'lawyer') {
        require_role(['lawyer']);
        $data = insights_hub_lawyer($pdo, $uid, $months6);
    } else {
        require_role(['client']);
        $data = insights_hub_client($pdo, $uid, $months6);
    }

    $activeNav = 'insights';
    $pageTitle = __('page.insights');
    $pageSubtitle = __('page.insights.subtitle');
    $bodyClass = 'page-insights';
    $includeCharts = true;
    require __DIR__ . '/header.php';

    $health = $data['health'];
    $forecast = $data['forecast'];
    $alerts = $data['alerts'];
    $trend = $data['trend'];
    $tabs = $data['tabs'];
    $panels = $data['panels'];
    $lists = $data['lists'] ?? [];
    $tables = $data['tables'] ?? ['invoices' => [], 'receipts' => []];
    $links = $data['links'] ?? [];
    $updated = date('H:i:s');
    $monthLabel = date('F Y');
    $topAlerts = array_slice($alerts, 0, 2);

    $renderMetrics = static function (array $items): void {
        echo '<div class="ih-metric-grid">';
        foreach ($items as $item) {
            $tone = e($item['tone'] ?? 'teal');
            $inner = '<span>' . e($item['label']) . '</span>'
                . '<strong>' . e($item['value']) . '</strong>'
                . (!empty($item['hint']) ? '<em>' . e($item['hint']) . '</em>' : '');
            if (!empty($item['url'])) {
                echo '<a class="ih-metric ih-metric--' . $tone . ' is-link" href="' . e($item['url']) . '">' . $inner . '</a>';
            } else {
                echo '<article class="ih-metric ih-metric--' . $tone . '">' . $inner . '</article>';
            }
        }
        echo '</div>';
    };

    $renderAlerts = static function (array $items): void {
        if (!$items) {
            echo '<p class="muted ih-empty">' . __e('insights.alerts.empty') . '</p>';
            return;
        }
        echo '<div class="ih-alerts ih-alerts--panel">';
        foreach ($items as $alert) {
            echo '<article class="ih-alert ih-alert--' . e($alert['tone']) . '">';
            echo '<span class="ih-alert-ico" aria-hidden="true">' . insights_svg($alert['icon']) . '</span>';
            echo '<div class="ih-alert-body"><h3>' . e($alert['title']) . '</h3><p>' . e($alert['text']) . '</p>';
            if (!empty($alert['url'])) {
                echo '<a href="' . e($alert['url']) . '">' . __e('insights.alerts.view') . '</a>';
            }
            echo '</div></article>';
        }
        echo '</div>';
    };

    $renderList = static function (array $rows, string $emptyKey = 'common.no_records'): void {
        if (!$rows) {
            echo '<p class="muted ih-empty">' . __e($emptyKey) . '</p>';
            return;
        }
        echo '<ul class="ih-detail-list">';
        foreach ($rows as $row) {
            $pct = (int) ($row['pct'] ?? 0);
            echo '<li>';
            echo '<div class="ih-detail-meta"><strong>' . e($row['label']) . '</strong><span>' . e($row['value']) . '</span></div>';
            if (isset($row['pct'])) {
                echo '<div class="ih-detail-track"><span style="width:' . max(4, $pct) . '%"></span></div>';
            }
            echo '</li>';
        }
        echo '</ul>';
    };

    $renderBillingRows = static function (array $rows): void {
        if (!$rows) {
            echo '<p class="muted ih-empty">' . __e('insights.tables.empty') . '</p>';
            return;
        }
        echo '<ul class="ih-billing-list">';
        foreach ($rows as $row) {
            $inner = '<div class="ih-billing-main"><strong>' . e($row['label']) . '</strong><span>' . e($row['meta'] ?? '') . '</span></div>'
                . '<div class="ih-billing-side"><em>' . e($row['value']) . '</em><small>' . e($row['status'] ?? '') . '</small></div>';
            if (!empty($row['url'])) {
                echo '<li><a class="ih-billing-row" href="' . e($row['url']) . '">' . $inner . '</a></li>';
            } else {
                echo '<li><div class="ih-billing-row">' . $inner . '</div></li>';
            }
        }
        echo '</ul>';
    };

    $shellOpen = static function (string $eyebrow, string $title, string $note = ''): void {
        echo '<div class="ih-shell">';
        echo '<div class="ih-shell-head"><div><p class="ih-eyebrow">' . e($eyebrow) . '</p>';
        echo '<h2 class="ih-panel-title">' . e($title) . '</h2></div>';
        if ($note !== '') {
            echo '<p class="ih-shell-note">' . e($note) . '</p>';
        }
        echo '</div><div class="ih-shell-body">';
    };
    $shellClose = static function (string $foot = ''): void {
        echo '</div>';
        if ($foot !== '') {
            echo '<div class="ih-shell-foot"><p>' . e($foot) . '</p></div>';
        }
        echo '</div>';
    };
    ?>
    <div class="ih-hub" data-ih-root>
        <header class="ih-banner">
            <div class="ih-banner-brand">
                <span class="ih-banner-icon" aria-hidden="true"><?= insights_svg('chart') ?></span>
                <div>
                    <h1><?= __e('insights.hub.title') ?></h1>
                    <p><?= e(__('insights.hub.subtitle', ['month' => $monthLabel])) ?></p>
                </div>
            </div>
            <nav class="ih-banner-nav" role="tablist" aria-label="<?= __e('insights.hub.sections') ?>">
                <?php foreach ($tabs as $i => $tab): ?>
                <button type="button"
                        class="ih-nav-link<?= $i === 0 ? ' is-active' : '' ?>"
                        role="tab"
                        id="ih-tab-<?= e($tab['id']) ?>"
                        data-ih-tab="<?= e($tab['id']) ?>"
                        aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                        aria-controls="ih-panel-<?= e($tab['id']) ?>">
                    <?= e($tab['label']) ?>
                </button>
                <?php endforeach; ?>
            </nav>
        </header>

        <div class="ih-panels">
            <section class="ih-panel is-active" id="ih-panel-overview" data-ih-panel="overview" role="tabpanel" aria-labelledby="ih-tab-overview">
                <div class="ih-overview">
                    <div class="ih-hero">
                        <div class="ih-card ih-health">
                            <div class="ih-health-gauge">
                                <?= insights_health_ring((int) $health['score']) ?>
                                <div class="ih-health-center"><strong><?= (int) $health['score'] ?></strong></div>
                            </div>
                            <span class="ih-pill ih-pill--<?= e($health['tone']) ?>"><?= e($health['label']) ?></span>
                            <h2><?= __e('insights.health.title') ?></h2>
                            <p><?= e($health['caption']) ?></p>
                            <div class="ih-health-foot">
                                <span><?= __e('insights.health.updated') ?> <?= e($updated) ?></span>
                            </div>
                        </div>
                        <div class="ih-card ih-overview-side">
                            <div class="ih-overview-block">
                                <p class="ih-eyebrow"><?= __e('insights.overview.snapshot') ?></p>
                                <?php $renderMetrics($panels['overview']); ?>
                            </div>
                            <div class="ih-overview-block ih-overview-alerts">
                                <p class="ih-eyebrow"><?= __e('insights.alerts.title') ?></p>
                                <?php $renderAlerts($topAlerts); ?>
                            </div>
                            <?php if (!empty($lists['overview'])): ?>
                            <div class="ih-overview-block ih-overview-mix">
                                <p class="ih-eyebrow"><?= __e('insights.overview.mix') ?></p>
                                <?php $renderList(array_slice($lists['overview'], 0, 3)); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($links): ?>
                    <div class="ih-card ih-jump-bar">
                        <p class="ih-eyebrow"><?= __e('insights.links.title') ?></p>
                        <nav class="ih-jump-links" aria-label="<?= __e('insights.links.title') ?>">
                            <?php foreach ($links as $link): ?>
                            <a href="<?= e($link['url']) ?>"><?= e($link['label']) ?></a>
                            <?php endforeach; ?>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="ih-panel" id="ih-panel-financial" data-ih-panel="financial" role="tabpanel" aria-labelledby="ih-tab-financial">
                <div class="ih-card ih-forecast ih-shell">
                    <div class="ih-shell-head ih-forecast-head">
                        <div>
                            <p class="ih-eyebrow ih-eyebrow--live"><?= __e('insights.forecast.live') ?></p>
                            <h2 class="ih-panel-title"><?= e($forecast['title']) ?></h2>
                        </div>
                        <div class="ih-forecast-meta">
                            <span class="ih-pill ih-pill--<?= e($forecast['risk_tone']) ?>"><?= e($forecast['risk']) ?></span>
                            <span class="ih-updated"><?= __e('insights.forecast.updated') ?> <?= e($updated) ?></span>
                        </div>
                    </div>
                    <div class="ih-shell-body ih-forecast-body">
                        <div class="ih-forecast-chart">
                            <canvas id="ihForecastChart" aria-label="<?= e($forecast['title']) ?>"></canvas>
                        </div>
                        <div class="ih-kpi-row">
                            <?php foreach ($forecast['kpis'] as $kpi): ?>
                            <?php if (!empty($kpi['url'])): ?>
                            <a class="ih-kpi ih-kpi--<?= e($kpi['tone']) ?> is-link" href="<?= e($kpi['url']) ?>">
                                <span><?= e($kpi['label']) ?></span>
                                <strong><?= e($kpi['value']) ?></strong>
                            </a>
                            <?php else: ?>
                            <article class="ih-kpi ih-kpi--<?= e($kpi['tone']) ?>">
                                <span><?= e($kpi['label']) ?></span>
                                <strong><?= e($kpi['value']) ?></strong>
                            </article>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($tables['invoices']) || !empty($tables['receipts'])): ?>
                        <div class="ih-billing-grid">
                            <div>
                                <p class="ih-eyebrow"><?= __e('insights.tables.invoices') ?></p>
                                <?php $renderBillingRows($tables['invoices'] ?? []); ?>
                            </div>
                            <div>
                                <p class="ih-eyebrow"><?= __e('insights.tables.receipts') ?></p>
                                <?php $renderBillingRows($tables['receipts'] ?? []); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="ih-forecast-foot ih-shell-foot">
                        <p><?= e($forecast['sync']) ?></p>
                        <?php if (!empty($forecast['badge'])): ?>
                        <span class="ih-foot-badge ih-foot-badge--<?= e($forecast['badge_tone'] ?? 'ok') ?>"><?= e($forecast['badge']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="ih-panel" id="ih-panel-cases" data-ih-panel="cases" role="tabpanel" aria-labelledby="ih-tab-cases">
                <div class="ih-card ih-panel-card">
                    <?php
                    $shellOpen(__('insights.tab.cases'), __('insights.panel.cases.title'), __('insights.panel.cases.note'));
                    $renderMetrics($panels['cases']);
                    echo '<div class="ih-split">';
                    echo '<div><p class="ih-eyebrow">' . __e('insights.overview.mix') . '</p>';
                    $renderList($lists['cases'] ?? []);
                    echo '</div><div><p class="ih-eyebrow">' . __e('insights.panel.status') . '</p>';
                    $renderList($lists['case_status'] ?? []);
                    echo '</div></div>';
                    $shellClose(__('insights.panel.cases.foot'));
                    ?>
                </div>
            </section>

            <?php if (!empty($panels['clients'])): ?>
            <section class="ih-panel" id="ih-panel-clients" data-ih-panel="clients" role="tabpanel" aria-labelledby="ih-tab-clients">
                <div class="ih-card ih-panel-card">
                    <?php
                    $shellOpen(__('insights.tab.clients'), __('insights.panel.clients.title'), __('insights.panel.clients.note'));
                    $renderMetrics($panels['clients']);
                    echo '<div class="ih-split"><div><p class="ih-eyebrow">' . __e('insights.panel.top_clients') . '</p>';
                    $renderList($lists['clients'] ?? []);
                    echo '</div><div><p class="ih-eyebrow">' . __e('insights.panel.billing') . '</p>';
                    $renderList($lists['billing'] ?? []);
                    echo '</div></div>';
                    $shellClose(__('insights.panel.clients.foot'));
                    ?>
                </div>
            </section>
            <?php endif; ?>

            <section class="ih-panel" id="ih-panel-appointments" data-ih-panel="appointments" role="tabpanel" aria-labelledby="ih-tab-appointments">
                <div class="ih-card ih-panel-card">
                    <?php
                    $shellOpen(__('insights.tab.appointments'), __('insights.panel.appointments.title'), __('insights.panel.appointments.note'));
                    $renderMetrics($panels['appointments']);
                    echo '<div class="ih-split"><div><p class="ih-eyebrow">' . __e('insights.panel.upcoming_list') . '</p>';
                    $renderList($lists['appointments'] ?? []);
                    echo '</div><div><p class="ih-eyebrow">' . __e('insights.panel.court_list') . '</p>';
                    $renderList($lists['hearings'] ?? []);
                    echo '</div></div>';
                    $shellClose(__('insights.panel.appointments.foot'));
                    ?>
                </div>
            </section>

            <section class="ih-panel" id="ih-panel-intelligence" data-ih-panel="intelligence" role="tabpanel" aria-labelledby="ih-tab-intelligence">
                <div class="ih-card ih-panel-card">
                    <?php
                    $shellOpen(__('insights.alerts.title'), __('insights.panel.alerts.title'), __('insights.panel.alerts.note'));
                    $renderAlerts($alerts);
                    $shellClose(__('insights.panel.alerts.foot', ['count' => count($alerts)]));
                    ?>
                </div>
            </section>

            <section class="ih-panel" id="ih-panel-reports" data-ih-panel="reports" role="tabpanel" aria-labelledby="ih-tab-reports">
                <div class="ih-card ih-trend ih-shell">
                    <div class="ih-shell-head">
                        <div>
                            <p class="ih-eyebrow"><?= __e('insights.trend.title') ?></p>
                            <h2 class="ih-panel-title"><?= e($trend['series_label']) ?></h2>
                        </div>
                        <p class="ih-shell-note"><?= __e('insights.panel.reports.note') ?></p>
                    </div>
                    <div class="ih-shell-body">
                        <div class="ih-trend-chart">
                            <canvas id="ihTrendChart" aria-label="<?= __e('insights.trend.title') ?>"></canvas>
                        </div>
                    </div>
                    <div class="ih-shell-foot">
                        <p><?= __e('insights.panel.reports.foot') ?></p>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script>
    window.LEXORA_INSIGHTS = <?= json_encode([
        'forecast' => [
            'labels' => $forecast['labels'],
            'base' => $forecast['series']['base'],
            'best' => $forecast['series']['best'],
            'worst' => $forecast['series']['worst'],
            'labelsSeries' => [
                'base' => __('insights.forecast.base'),
                'best' => __('insights.forecast.best'),
                'worst' => __('insights.forecast.worst'),
            ],
        ],
        'trend' => [
            'labels' => $trend['labels'],
            'values' => $trend['values'],
            'label' => $trend['series_label'],
        ],
    ], JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <?php
    require __DIR__ . '/footer.php';
}

function insights_health_band(int $score): array
{
    if ($score >= 75) {
        return ['label' => __('insights.health.good'), 'tone' => 'good'];
    }
    if ($score >= 45) {
        return ['label' => __('insights.health.fair'), 'tone' => 'fair'];
    }
    return ['label' => __('insights.health.poor'), 'tone' => 'poor'];
}

function insights_build_forecast_series(float $mtd, float $prev, int $days = 30): array
{
    $labels = [];
    $base = [];
    $best = [];
    $worst = [];
    $daily = $mtd > 0 ? ($mtd / max((int) date('j'), 1)) : max($prev / 30, 1);
    $growth = $prev > 0 ? max(0.85, min(1.35, $mtd / max($prev, 1))) : 1.05;
    $running = max($mtd, $daily);
    for ($i = 0; $i < $days; $i++) {
        $labels[] = date('M j', strtotime("+{$i} days"));
        $wave = 1 + sin($i / 4.2) * 0.04;
        $b = $running * (1 + (($growth - 1) * ($i / max($days - 1, 1)))) * $wave;
        $base[] = round($b, 2);
        $best[] = round($b * 1.12, 2);
        $worst[] = round($b * 0.84, 2);
    }
    return [
        'labels' => $labels,
        'base' => $base,
        'best' => $best,
        'worst' => $worst,
        'base_end' => end($base) ?: 0,
        'best_end' => end($best) ?: 0,
        'worst_end' => end($worst) ?: 0,
    ];
}

function insights_billing_tables_admin(PDO $pdo): array
{
    $invoices = $pdo->query("
        SELECT i.id, i.invoice_number, i.total, i.status, i.due_date, i.case_id,
               COALESCE(NULLIF(TRIM(u.company_name),''), CONCAT(u.first_name,' ',u.last_name)) AS client_name
        FROM invoices i
        JOIN users u ON u.id = i.client_id
        WHERE i.status IN ('sent','partial','overdue','paid')
        ORDER BY FIELD(i.status,'overdue','partial','sent','paid'), i.due_date IS NULL, i.due_date ASC, i.id DESC
        LIMIT 3
    ")->fetchAll();

    $receipts = $pdo->query("
        SELECT p.id, p.receipt_number, p.amount, p.paid_at, p.payment_method, p.invoice_id, i.case_id,
               COALESCE(i.invoice_number, CONCAT('#', p.invoice_id)) AS invoice_number
        FROM payments p
        LEFT JOIN invoices i ON i.id = p.invoice_id
        ORDER BY p.paid_at DESC, p.id DESC
        LIMIT 3
    ")->fetchAll();

    return [
        'invoices' => array_map(static function ($r) {
            $caseId = (int) ($r['case_id'] ?? 0);
            return [
                'label' => (string) $r['invoice_number'],
                'meta' => (string) $r['client_name'],
                'value' => money((float) $r['total']),
                'status' => translate_status((string) $r['status']),
                'url' => $caseId > 0
                    ? 'cases.php?action=view&id=' . $caseId . '&tab=invoices'
                    : 'invoice.php?id=' . (int) $r['id'],
            ];
        }, $invoices),
        'receipts' => array_map(static function ($r) {
            $caseId = (int) ($r['case_id'] ?? 0);
            return [
                'label' => (string) ($r['receipt_number'] ?: ('#' . $r['id'])),
                'meta' => (string) $r['invoice_number'] . ' · ' . format_date($r['paid_at'], 'M j'),
                'value' => money((float) $r['amount']),
                'status' => (string) ($r['payment_method'] ?? ''),
                'url' => $caseId > 0
                    ? 'cases.php?action=view&id=' . $caseId . '&tab=receipts'
                    : 'receipt.php?id=' . (int) $r['id'],
            ];
        }, $receipts),
    ];
}

function insights_billing_tables_client(PDO $pdo, int $uid): array
{
    $invStmt = $pdo->prepare("
        SELECT i.id, i.invoice_number, i.total, i.status, i.due_date, i.case_id
        FROM invoices i
        WHERE i.client_id = ? AND i.status IN ('sent','partial','overdue','paid')
        ORDER BY FIELD(i.status,'overdue','partial','sent','paid'), i.due_date IS NULL, i.due_date ASC, i.id DESC
        LIMIT 3
    ");
    $invStmt->execute([$uid]);
    $invoices = $invStmt->fetchAll();

    $rcpStmt = $pdo->prepare("
        SELECT p.id, p.receipt_number, p.amount, p.paid_at, p.payment_method,
               COALESCE(i.invoice_number, CONCAT('#', p.invoice_id)) AS invoice_number
        FROM payments p
        LEFT JOIN invoices i ON i.id = p.invoice_id
        WHERE p.client_id = ?
        ORDER BY p.paid_at DESC, p.id DESC
        LIMIT 3
    ");
    $rcpStmt->execute([$uid]);
    $receipts = $rcpStmt->fetchAll();

    return [
        'invoices' => array_map(static function ($r) {
            return [
                'label' => (string) $r['invoice_number'],
                'meta' => translate_status((string) $r['status']),
                'value' => money((float) $r['total']),
                'status' => !empty($r['due_date']) ? format_date($r['due_date'], 'M j') : '—',
                'url' => 'payments.php',
            ];
        }, $invoices),
        'receipts' => array_map(static function ($r) {
            return [
                'label' => (string) ($r['receipt_number'] ?: ('#' . $r['id'])),
                'meta' => (string) $r['invoice_number'] . ' · ' . format_date($r['paid_at'], 'M j'),
                'value' => money((float) $r['amount']),
                'status' => (string) ($r['payment_method'] ?? ''),
                'url' => 'receipt.php?id=' . (int) $r['id'],
            ];
        }, $receipts),
    ];
}

function insights_billing_tables_lawyer(PDO $pdo, int $uid): array
{
    $accessSql = lawyer_case_access_sql('c');
    $invStmt = $pdo->prepare("
        SELECT i.id, i.invoice_number, i.total, i.status, i.case_id,
               COALESCE(NULLIF(TRIM(u.company_name),''), CONCAT(u.first_name,' ',u.last_name)) AS client_name
        FROM invoices i
        JOIN cases c ON c.id = i.case_id
        JOIN users u ON u.id = i.client_id
        WHERE $accessSql AND i.status IN ('sent','partial','overdue','paid')
        ORDER BY FIELD(i.status,'overdue','partial','sent','paid'), i.id DESC
        LIMIT 3
    ");
    $invStmt->execute([$uid, $uid]);
    $invoices = $invStmt->fetchAll();

    $rcpStmt = $pdo->prepare("
        SELECT p.id, p.receipt_number, p.amount, p.paid_at, p.payment_method, i.case_id,
               COALESCE(i.invoice_number, CONCAT('#', p.invoice_id)) AS invoice_number
        FROM payments p
        JOIN invoices i ON i.id = p.invoice_id
        JOIN cases c ON c.id = i.case_id
        WHERE $accessSql
        ORDER BY p.paid_at DESC, p.id DESC
        LIMIT 3
    ");
    $rcpStmt->execute([$uid, $uid]);
    $receipts = $rcpStmt->fetchAll();

    return [
        'invoices' => array_map(static function ($r) {
            return [
                'label' => (string) $r['invoice_number'],
                'meta' => (string) $r['client_name'],
                'value' => money((float) $r['total']),
                'status' => translate_status((string) $r['status']),
                'url' => 'cases.php?action=view&id=' . (int) $r['case_id'],
            ];
        }, $invoices),
        'receipts' => array_map(static function ($r) {
            return [
                'label' => (string) ($r['receipt_number'] ?: ('#' . $r['id'])),
                'meta' => (string) $r['invoice_number'] . ' · ' . format_date($r['paid_at'], 'M j'),
                'value' => money((float) $r['amount']),
                'status' => (string) ($r['payment_method'] ?? ''),
                'url' => 'cases.php?action=view&id=' . (int) $r['case_id'],
            ];
        }, $receipts),
    ];
}

function insights_hub_tabs(string $portal): array
{
    if ($portal === 'client') {
        return [
            ['id' => 'overview', 'label' => __('insights.tab.overview')],
            ['id' => 'financial', 'label' => __('insights.tab.financial')],
            ['id' => 'cases', 'label' => __('insights.tab.cases')],
            ['id' => 'appointments', 'label' => __('insights.tab.appointments')],
            ['id' => 'intelligence', 'label' => __('insights.tab.alerts')],
            ['id' => 'reports', 'label' => __('insights.tab.reports')],
        ];
    }
    if ($portal === 'lawyer') {
        return [
            ['id' => 'overview', 'label' => __('insights.tab.overview')],
            ['id' => 'financial', 'label' => __('insights.tab.workload')],
            ['id' => 'cases', 'label' => __('insights.tab.cases')],
            ['id' => 'appointments', 'label' => __('insights.tab.appointments')],
            ['id' => 'intelligence', 'label' => __('insights.tab.alerts')],
            ['id' => 'reports', 'label' => __('insights.tab.reports')],
        ];
    }
    return [
        ['id' => 'overview', 'label' => __('insights.tab.overview')],
        ['id' => 'financial', 'label' => __('insights.tab.financial')],
        ['id' => 'cases', 'label' => __('insights.tab.cases')],
        ['id' => 'clients', 'label' => __('insights.tab.clients')],
        ['id' => 'appointments', 'label' => __('insights.tab.appointments')],
        ['id' => 'intelligence', 'label' => __('insights.tab.alerts')],
        ['id' => 'reports', 'label' => __('insights.tab.reports')],
    ];
}

function insights_hub_admin(PDO $pdo, array $months6): array
{
    $mtd = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
    $prev = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND paid_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
    $active = (int) $pdo->query("SELECT COUNT(*) FROM cases WHERE status IN ('open','active','pending','reopened','on_hold')")->fetchColumn();
    $paidInv = (int) $pdo->query("SELECT COUNT(*) FROM invoices WHERE status='paid'")->fetchColumn();
    $issuedInv = max(1, (int) $pdo->query("SELECT COUNT(*) FROM invoices WHERE status IN ('sent','partial','overdue','paid')")->fetchColumn());
    $collection = round(($paidInv / $issuedInv) * 100, 1);
    $overdue = (int) $pdo->query("SELECT COUNT(*) FROM invoices WHERE status='overdue' OR (status IN ('sent','partial') AND due_date < CURDATE())")->fetchColumn();
    $priority = (int) $pdo->query("SELECT COUNT(*) FROM cases WHERE status IN ('open','active','pending','reopened','on_hold') AND priority IN ('high','urgent')")->fetchColumn();
    $clients = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='client' AND is_active=1")->fetchColumn();
    $appts = (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE status IN ('scheduled','confirmed','rescheduled','pending') AND scheduled_at >= NOW()")->fetchColumn();
    $hearings = (int) $pdo->query("SELECT COUNT(*) FROM court_hearings WHERE status='scheduled' AND hearing_date >= NOW()")->fetchColumn();

    $peakDay = $pdo->query("
        SELECT DATE(paid_at) d, COALESCE(SUM(amount),0) t
        FROM payments
        WHERE paid_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
        GROUP BY d
        ORDER BY t DESC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC) ?: ['d' => null, 't' => 0];

    $delta = $prev > 0 ? (($mtd - $prev) / $prev) * 100 : ($mtd > 0 ? 100.0 : 0.0);
    $series = insights_build_forecast_series($mtd, $prev);

    $healthScore = (int) round(
        min(100, $collection) * 0.4
        + min(100, ($active > 0 ? 70 : 35)) * 0.25
        + min(100, max(0, 100 - $overdue * 12)) * 0.2
        + min(100, ($mtd > 0 ? 75 : 30)) * 0.15
    );
    $band = insights_health_band($healthScore);
    $riskTone = $healthScore >= 70 ? 'good' : ($healthScore >= 45 ? 'medium' : 'poor');
    $riskLabel = $healthScore >= 70 ? __('insights.risk.low') : ($healthScore >= 45 ? __('insights.risk.medium') : __('insights.risk.high'));

    $revByMonth = array_fill_keys($months6, 0.0);
    foreach ($pdo->query("SELECT DATE_FORMAT(paid_at,'%Y-%m') ym, COALESCE(SUM(amount),0) t FROM payments WHERE paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym") as $row) {
        if (isset($revByMonth[$row['ym']])) {
            $revByMonth[$row['ym']] = (float) $row['t'];
        }
    }

    $alerts = [];
    if ($overdue > 0) {
        $alerts[] = [
            'tone' => 'danger',
            'icon' => 'alert',
            'title' => __('insights.alert.overdue.title'),
            'text' => __('insights.alert.overdue.text', ['count' => $overdue]),
            'url' => 'cases.php?filter=outstanding',
        ];
    }
    if ($priority > 0) {
        $alerts[] = [
            'tone' => 'warn',
            'icon' => 'flame',
            'title' => __('insights.alert.priority.title'),
            'text' => __('insights.alert.priority.text', ['count' => $priority]),
            'url' => 'cases.php',
        ];
    }
    if ($collection < 65) {
        $alerts[] = [
            'tone' => 'amber',
            'icon' => 'coin',
            'title' => __('insights.alert.collection.title'),
            'text' => __('insights.alert.collection.text', ['pct' => number_format($collection, 0)]),
            'url' => 'cases.php?filter=outstanding',
        ];
    }
    if ($delta >= 20) {
        $alerts[] = [
            'tone' => 'ok',
            'icon' => 'trend',
            'title' => __('insights.alert.spike.title'),
            'text' => __('insights.alert.spike.text', ['pct' => number_format($delta, 0)]),
            'url' => null,
        ];
    }
    if ((float) $peakDay['t'] > 0 && $peakDay['d']) {
        $alerts[] = [
            'tone' => 'info',
            'icon' => 'bolt',
            'title' => __('insights.alert.payment_day.title'),
            'text' => __('insights.alert.payment_day.text', [
                'day' => date('D j', strtotime((string) $peakDay['d'])),
                'amount' => money((float) $peakDay['t']),
            ]),
            'url' => null,
            'wide' => true,
        ];
    }
    if ($hearings > 0) {
        $alerts[] = [
            'tone' => 'info',
            'icon' => 'court',
            'title' => __('insights.alert.hearings.title'),
            'text' => __('insights.alert.hearings.text', ['count' => $hearings]),
            'url' => 'court.php',
        ];
    }

    $typeRows = $pdo->query("SELECT COALESCE(NULLIF(TRIM(case_type),''),'Other') label, COUNT(*) c FROM cases GROUP BY label ORDER BY c DESC LIMIT 3")->fetchAll();
    $statusRows = $pdo->query('SELECT status label, COUNT(*) c FROM cases GROUP BY status ORDER BY c DESC')->fetchAll();
    $typeTotal = max(1, (int) array_sum(array_map(static fn($r) => (int) $r['c'], $typeRows)));
    $statusTotal = max(1, (int) array_sum(array_map(static fn($r) => (int) $r['c'], $statusRows)));

    $topClients = $pdo->query("
        SELECT COALESCE(NULLIF(TRIM(u.company_name),''), CONCAT(u.first_name,' ',u.last_name)) label,
               COALESCE(SUM(p.amount),0) t
        FROM users u
        LEFT JOIN payments p ON p.client_id=u.id
        WHERE u.role='client'
        GROUP BY u.id
        ORDER BY t DESC
        LIMIT 5
    ")->fetchAll();
    $topClientMax = max(1.0, ...array_map(static fn($r) => (float) $r['t'], $topClients ?: [['t' => 0]]));

    $upcomingAppts = $pdo->query("
        SELECT title label, DATE_FORMAT(scheduled_at, '%d %b %H:%i') value
        FROM appointments
        WHERE status IN ('scheduled','confirmed','rescheduled','pending') AND scheduled_at >= NOW()
        ORDER BY scheduled_at ASC
        LIMIT 5
    ")->fetchAll();
    $upcomingHearings = $pdo->query("
        SELECT CONCAT(COALESCE(c.case_number, CONCAT('#', c.id)), ' — ', DATE_FORMAT(h.hearing_date, '%d %b')) label,
               COALESCE(h.court_name, h.hearing_type, 'Hearing') value
        FROM court_hearings h
        JOIN cases c ON c.id=h.case_id
        WHERE h.status='scheduled' AND h.hearing_date >= NOW()
        ORDER BY h.hearing_date ASC
        LIMIT 5
    ")->fetchAll();

    $closed = (int) $pdo->query("SELECT COUNT(*) FROM cases WHERE status='closed'")->fetchColumn();
    $openInv = (int) $pdo->query("SELECT COUNT(*) FROM invoices WHERE status IN ('sent','partial','overdue')")->fetchColumn();
    $outstandingAmt = (float) $pdo->query("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status IN ('sent','partial','overdue')")->fetchColumn();

    $badgeTone = $delta >= 0 ? 'ok' : 'warn';
    $badge = $delta >= 0
        ? __('insights.forecast.up', ['pct' => number_format(abs($delta), 0)])
        : __('insights.forecast.down', ['pct' => number_format(abs($delta), 0)]);

    return [
        'tabs' => insights_hub_tabs('admin'),
        'health' => [
            'score' => $healthScore,
            'label' => $band['label'],
            'tone' => $band['tone'],
            'caption' => __('insights.health.caption.admin'),
        ],
        'forecast' => [
            'title' => __('insights.forecast.title'),
            'risk' => $riskLabel,
            'risk_tone' => $riskTone,
            'labels' => $series['labels'],
            'series' => [
                'base' => $series['base'],
                'best' => $series['best'],
                'worst' => $series['worst'],
            ],
            'kpis' => [
                ['label' => __('insights.kpi.mtd'), 'value' => money($mtd), 'tone' => 'teal', 'url' => 'cases.php?filter=outstanding'],
                ['label' => __('insights.kpi.active'), 'value' => (string) $active, 'tone' => 'blue', 'url' => 'cases.php'],
                ['label' => __('insights.kpi.collection'), 'value' => number_format($collection, 1) . '%', 'tone' => 'cyan', 'url' => 'cases.php?filter=outstanding'],
            ],
            'sync' => __('insights.forecast.sync.admin', ['cases' => $active, 'amount' => money($mtd)]),
            'badge' => $badge,
            'badge_tone' => $badgeTone,
        ],
        'alerts' => array_slice($alerts, 0, 5),
        'panels' => [
            'overview' => [
                ['label' => __('insights.kpi.mtd'), 'value' => money($mtd), 'tone' => 'teal', 'hint' => __('insights.hint.this_month'), 'url' => 'cases.php?filter=outstanding'],
                ['label' => __('insights.kpi.active'), 'value' => (string) $active, 'tone' => 'blue', 'hint' => __('insights.hint.open_matters'), 'url' => 'cases.php'],
                ['label' => __('insights.kpi.collection'), 'value' => number_format($collection, 1) . '%', 'tone' => 'cyan', 'hint' => __('insights.hint.paid_share'), 'url' => 'cases.php?filter=outstanding'],
            ],
            'cases' => [
                ['label' => __('insights.kpi.active'), 'value' => (string) $active, 'tone' => 'teal', 'url' => 'cases.php'],
                ['label' => __('insights.kpi.priority'), 'value' => (string) $priority, 'tone' => 'orange', 'url' => 'cases.php'],
                ['label' => __('insights.kpi.closed'), 'value' => (string) $closed, 'tone' => 'green', 'url' => 'cases.php'],
            ],
            'clients' => [
                ['label' => __('insights.kpi.clients'), 'value' => (string) $clients, 'tone' => 'teal', 'url' => 'clients.php'],
                ['label' => __('insights.kpi.open_invoices'), 'value' => (string) $openInv, 'tone' => 'orange', 'url' => 'cases.php?filter=outstanding'],
                ['label' => __('insights.kpi.due'), 'value' => money($outstandingAmt), 'tone' => 'purple', 'url' => 'cases.php?filter=outstanding'],
            ],
            'appointments' => [
                ['label' => __('insights.kpi.meetings'), 'value' => (string) $appts, 'tone' => 'teal', 'url' => 'appointments.php'],
                ['label' => __('insights.kpi.hearings'), 'value' => (string) $hearings, 'tone' => 'blue', 'url' => 'court.php'],
                ['label' => __('insights.kpi.upcoming'), 'value' => (string) ($appts + $hearings), 'tone' => 'cyan', 'url' => 'appointments.php'],
            ],
        ],
        'tables' => insights_billing_tables_admin($pdo),
        'links' => [
            ['label' => __('nav.cases'), 'url' => 'cases.php'],
            ['label' => __('nav.clients'), 'url' => 'clients.php'],
            ['label' => __('nav.appointments'), 'url' => 'appointments.php'],
            ['label' => __('nav.court'), 'url' => 'court.php'],
        ],
        'lists' => [
            'overview' => array_map(static fn($r) => [
                'label' => $r['label'],
                'value' => (string) (int) $r['c'],
                'pct' => (int) round(((int) $r['c'] / $typeTotal) * 100),
            ], $typeRows),
            'cases' => array_map(static fn($r) => [
                'label' => $r['label'],
                'value' => (string) (int) $r['c'],
                'pct' => (int) round(((int) $r['c'] / $typeTotal) * 100),
            ], $typeRows),
            'case_status' => array_map(static fn($r) => [
                'label' => translate_status((string) $r['label']),
                'value' => (string) (int) $r['c'],
                'pct' => (int) round(((int) $r['c'] / $statusTotal) * 100),
            ], $statusRows),
            'clients' => array_map(static fn($r) => [
                'label' => $r['label'],
                'value' => money((float) $r['t']),
                'pct' => (int) round(((float) $r['t'] / $topClientMax) * 100),
            ], $topClients),
            'billing' => [
                ['label' => __('insights.kpi.open_invoices'), 'value' => (string) $openInv, 'pct' => min(100, $openInv * 15)],
                ['label' => __('insights.kpi.collection'), 'value' => number_format($collection, 1) . '%', 'pct' => (int) $collection],
                ['label' => __('insights.kpi.due'), 'value' => money($outstandingAmt), 'pct' => min(100, (int) round(($outstandingAmt / max($mtd + $outstandingAmt, 1)) * 100))],
            ],
            'appointments' => array_map(static fn($r) => [
                'label' => $r['label'],
                'value' => $r['value'],
            ], $upcomingAppts),
            'hearings' => array_map(static fn($r) => [
                'label' => $r['label'],
                'value' => $r['value'],
            ], $upcomingHearings),
        ],
        'trend' => [
            'labels' => array_map('format_month_short', $months6),
            'values' => array_values($revByMonth),
            'series_label' => __('insights.trend.revenue'),
        ],
    ];
}

function insights_hub_lawyer(PDO $pdo, int $uid, array $months6): array
{
    $activeStmt = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE lawyer_id=? AND status IN ('open','active','pending','reopened','on_hold')");
    $activeStmt->execute([$uid]);
    $active = (int) $activeStmt->fetchColumn();
    $priorityStmt = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE lawyer_id=? AND status IN ('open','active','pending','reopened','on_hold') AND priority IN ('high','urgent')");
    $priorityStmt->execute([$uid]);
    $priority = (int) $priorityStmt->fetchColumn();
    $apptsStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE lawyer_id=? AND status IN ('scheduled','confirmed','rescheduled','pending') AND scheduled_at >= NOW()");
    $apptsStmt->execute([$uid]);
    $appts = (int) $apptsStmt->fetchColumn();
    $todayStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE lawyer_id=? AND DATE(scheduled_at)=CURDATE() AND status IN ('scheduled','confirmed','rescheduled','pending')");
    $todayStmt->execute([$uid]);
    $today = (int) $todayStmt->fetchColumn();
    $hearStmt = $pdo->prepare("SELECT COUNT(*) FROM court_hearings h JOIN cases c ON c.id=h.case_id WHERE " . lawyer_case_access_sql('c') . " AND h.status='scheduled' AND h.hearing_date >= NOW()");
    $hearStmt->execute([$uid, $uid]);
    $hearings = (int) $hearStmt->fetchColumn();
    $clientsStmt = $pdo->prepare('SELECT COUNT(DISTINCT client_id) FROM cases WHERE lawyer_id=?');
    $clientsStmt->execute([$uid]);
    $clients = (int) $clientsStmt->fetchColumn();
    $closedStmt = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE lawyer_id=? AND status='closed'");
    $closedStmt->execute([$uid]);
    $closed = (int) $closedStmt->fetchColumn();
    $all = max(1, $active + $closed);

    $opened = array_fill_keys($months6, 0);
    $oStmt = $pdo->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(*) c FROM cases WHERE lawyer_id=? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym");
    $oStmt->execute([$uid]);
    foreach ($oStmt->fetchAll() as $row) {
        if (isset($opened[$row['ym']])) {
            $opened[$row['ym']] = (int) $row['c'];
        }
    }
    $monthOpen = (int) ($opened[date('Y-m')] ?? 0);
    $prevOpen = (int) ($opened[date('Y-m', strtotime('first day of last month'))] ?? 0);
    $delta = $prevOpen > 0 ? (($monthOpen - $prevOpen) / $prevOpen) * 100 : ($monthOpen > 0 ? 100.0 : 0.0);

    // Forecast caseload pressure using appointments + hearings as proxy units
    $load = max($appts + $hearings, 1);
    $series = insights_build_forecast_series((float) ($active * 10 + $load), (float) max($active * 8, 1));

    $healthScore = (int) round(
        min(100, ($active / $all) * 100) * 0.35
        + min(100, max(0, 100 - $priority * 18)) * 0.3
        + min(100, ($today > 0 ? 55 : 75)) * 0.15
        + min(100, ($appts > 0 ? 70 : 40)) * 0.2
    );
    $band = insights_health_band($healthScore);
    $riskTone = $priority >= 3 ? 'poor' : ($priority >= 1 ? 'medium' : 'good');
    $riskLabel = $priority >= 3 ? __('insights.risk.high') : ($priority >= 1 ? __('insights.risk.medium') : __('insights.risk.low'));

    $alerts = [];
    if ($priority > 0) {
        $alerts[] = [
            'tone' => 'warn',
            'icon' => 'flame',
            'title' => __('insights.alert.priority.title'),
            'text' => __('insights.alert.priority.text', ['count' => $priority]),
            'url' => 'cases.php',
        ];
    }
    if ($today > 0) {
        $alerts[] = [
            'tone' => 'info',
            'icon' => 'calendar',
            'title' => __('insights.alert.today.title'),
            'text' => __('insights.alert.today.text', ['count' => $today]),
            'url' => 'appointments.php',
        ];
    }
    if ($hearings > 0) {
        $alerts[] = [
            'tone' => 'info',
            'icon' => 'court',
            'title' => __('insights.alert.hearings.title'),
            'text' => __('insights.alert.hearings.text', ['count' => $hearings]),
            'url' => 'court.php',
        ];
    }
    if ($delta >= 20) {
        $alerts[] = [
            'tone' => 'ok',
            'icon' => 'trend',
            'title' => __('insights.alert.matters_up.title'),
            'text' => __('insights.alert.matters_up.text', ['pct' => number_format($delta, 0)]),
            'url' => null,
        ];
    }
    if ($active === 0) {
        $alerts[] = [
            'tone' => 'amber',
            'icon' => 'cases',
            'title' => __('insights.alert.no_active.title'),
            'text' => __('insights.alert.no_active.text'),
            'url' => 'cases.php',
        ];
    }

    return [
        'tabs' => insights_hub_tabs('lawyer'),
        'health' => [
            'score' => $healthScore,
            'label' => $band['label'],
            'tone' => $band['tone'],
            'caption' => __('insights.health.caption.lawyer'),
        ],
        'forecast' => [
            'title' => __('insights.forecast.title.lawyer'),
            'risk' => $riskLabel,
            'risk_tone' => $riskTone,
            'labels' => $series['labels'],
            'series' => [
                'base' => $series['base'],
                'best' => $series['best'],
                'worst' => $series['worst'],
            ],
            'kpis' => [
                ['label' => __('insights.kpi.active'), 'value' => (string) $active, 'tone' => 'teal', 'url' => 'cases.php'],
                ['label' => __('insights.kpi.clients'), 'value' => (string) $clients, 'tone' => 'blue', 'url' => 'clients.php'],
                ['label' => __('insights.kpi.upcoming'), 'value' => (string) ($appts + $hearings), 'tone' => 'cyan', 'url' => 'appointments.php'],
            ],
            'sync' => __('insights.forecast.sync.lawyer', ['cases' => $active, 'appts' => $appts]),
            'badge' => $delta >= 0
                ? __('insights.forecast.matters_up', ['pct' => number_format(abs($delta), 0)])
                : __('insights.forecast.matters_down', ['pct' => number_format(abs($delta), 0)]),
            'badge_tone' => $delta >= 0 ? 'ok' : 'warn',
        ],
        'alerts' => array_slice($alerts, 0, 5),
        'panels' => [
            'overview' => [
                ['label' => __('insights.kpi.active'), 'value' => (string) $active, 'tone' => 'teal', 'hint' => __('insights.hint.open_matters'), 'url' => 'cases.php'],
                ['label' => __('insights.kpi.clients'), 'value' => (string) $clients, 'tone' => 'blue', 'hint' => __('insights.hint.assigned'), 'url' => 'clients.php'],
                ['label' => __('insights.kpi.upcoming'), 'value' => (string) ($appts + $hearings), 'tone' => 'cyan', 'hint' => __('insights.hint.diary'), 'url' => 'appointments.php'],
            ],
            'cases' => [
                ['label' => __('insights.kpi.active'), 'value' => (string) $active, 'tone' => 'teal', 'url' => 'cases.php'],
                ['label' => __('insights.kpi.priority'), 'value' => (string) $priority, 'tone' => 'orange', 'url' => 'cases.php'],
                ['label' => __('insights.kpi.closed'), 'value' => (string) $closed, 'tone' => 'green', 'url' => 'cases.php'],
            ],
            'clients' => [],
            'appointments' => [
                ['label' => __('insights.kpi.today'), 'value' => (string) $today, 'tone' => 'teal', 'url' => 'appointments.php'],
                ['label' => __('insights.kpi.meetings'), 'value' => (string) $appts, 'tone' => 'blue', 'url' => 'appointments.php'],
                ['label' => __('insights.kpi.hearings'), 'value' => (string) $hearings, 'tone' => 'cyan', 'url' => 'court.php'],
            ],
        ],
        'tables' => insights_billing_tables_lawyer($pdo, $uid),
        'links' => [
            ['label' => __('nav.cases'), 'url' => 'cases.php'],
            ['label' => __('nav.clients'), 'url' => 'clients.php'],
            ['label' => __('nav.appointments'), 'url' => 'appointments.php'],
            ['label' => __('nav.court'), 'url' => 'court.php'],
        ],
        'lists' => (static function () use ($pdo, $uid, $active, $closed, $priority): array {
            $typeStmt = $pdo->prepare("SELECT COALESCE(NULLIF(TRIM(case_type),''),'Other') label, COUNT(*) c FROM cases WHERE lawyer_id=? GROUP BY label ORDER BY c DESC LIMIT 3");
            $typeStmt->execute([$uid]);
            $types = $typeStmt->fetchAll();
            $typeTotal = max(1, (int) array_sum(array_map(static fn($r) => (int) $r['c'], $types)));
            $statusStmt = $pdo->prepare('SELECT status label, COUNT(*) c FROM cases WHERE lawyer_id=? GROUP BY status ORDER BY c DESC');
            $statusStmt->execute([$uid]);
            $statuses = $statusStmt->fetchAll();
            $statusTotal = max(1, (int) array_sum(array_map(static fn($r) => (int) $r['c'], $statuses)));
            $apptStmt = $pdo->prepare("SELECT title label, DATE_FORMAT(scheduled_at, '%d %b %H:%i') value FROM appointments WHERE lawyer_id=? AND status IN ('scheduled','confirmed','rescheduled','pending') AND scheduled_at >= NOW() ORDER BY scheduled_at ASC LIMIT 5");
            $apptStmt->execute([$uid]);
            $apptsList = $apptStmt->fetchAll();
            $hearStmt = $pdo->prepare("SELECT CONCAT(COALESCE(c.case_number, CONCAT('#', c.id)), ' — ', DATE_FORMAT(h.hearing_date, '%d %b')) label, COALESCE(h.court_name, h.hearing_type, 'Hearing') value FROM court_hearings h JOIN cases c ON c.id=h.case_id WHERE " . lawyer_case_access_sql('c') . " AND h.status='scheduled' AND h.hearing_date >= NOW() ORDER BY h.hearing_date ASC LIMIT 5");
            $hearStmt->execute([$uid, $uid]);
            $hears = $hearStmt->fetchAll();
            return [
                'overview' => array_map(static fn($r) => [
                    'label' => $r['label'],
                    'value' => (string) (int) $r['c'],
                    'pct' => (int) round(((int) $r['c'] / $typeTotal) * 100),
                ], $types),
                'cases' => array_map(static fn($r) => [
                    'label' => $r['label'],
                    'value' => (string) (int) $r['c'],
                    'pct' => (int) round(((int) $r['c'] / $typeTotal) * 100),
                ], $types),
                'case_status' => array_map(static fn($r) => [
                    'label' => translate_status((string) $r['label']),
                    'value' => (string) (int) $r['c'],
                    'pct' => (int) round(((int) $r['c'] / $statusTotal) * 100),
                ], $statuses),
                'appointments' => array_map(static fn($r) => ['label' => $r['label'], 'value' => $r['value']], $apptsList),
                'hearings' => array_map(static fn($r) => ['label' => $r['label'], 'value' => $r['value']], $hears),
            ];
        })(),
        'trend' => [
            'labels' => array_map('format_month_short', $months6),
            'values' => array_values($opened),
            'series_label' => __('insights.trend.opened'),
        ],
    ];
}

function insights_hub_client(PDO $pdo, int $uid, array $months6): array
{
    $invStmt = $pdo->prepare("SELECT i.total, i.status, i.due_date, IFNULL((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id=i.id),0) paid FROM invoices i WHERE i.client_id=?");
    $invStmt->execute([$uid]);
    $outstanding = 0.0;
    $overdue = 0;
    $paidCount = 0;
    $issued = 0;
    foreach ($invStmt->fetchAll() as $inv) {
        if (!in_array($inv['status'], ['sent', 'partial', 'overdue', 'paid'], true)) {
            continue;
        }
        $issued++;
        if ($inv['status'] === 'paid') {
            $paidCount++;
        }
        $due = max(0, (float) $inv['total'] - (float) $inv['paid']);
        if ($due > 0) {
            $outstanding += $due;
            if ($inv['status'] === 'overdue' || (!empty($inv['due_date']) && $inv['due_date'] < date('Y-m-d'))) {
                $overdue++;
            }
        }
    }
    $collection = $issued > 0 ? round(($paidCount / $issued) * 100, 1) : 100.0;

    $mtdStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE client_id=? AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $mtdStmt->execute([$uid]);
    $mtd = (float) $mtdStmt->fetchColumn();
    $prevStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE client_id=? AND paid_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND paid_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $prevStmt->execute([$uid]);
    $prev = (float) $prevStmt->fetchColumn();
    $delta = $prev > 0 ? (($mtd - $prev) / $prev) * 100 : ($mtd > 0 ? 100.0 : 0.0);

    $activeStmt = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE client_id=? AND status IN ('open','active','pending','reopened','on_hold')");
    $activeStmt->execute([$uid]);
    $active = (int) $activeStmt->fetchColumn();
    $docsStmt = $pdo->prepare('SELECT COUNT(*) FROM case_documents d JOIN cases c ON c.id=d.case_id WHERE c.client_id=?');
    $docsStmt->execute([$uid]);
    $docs = (int) $docsStmt->fetchColumn();
    $apptsStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE client_id=? AND status IN ('scheduled','confirmed','rescheduled','pending') AND scheduled_at >= NOW()");
    $apptsStmt->execute([$uid]);
    $appts = (int) $apptsStmt->fetchColumn();

    $paidByMonth = array_fill_keys($months6, 0.0);
    $pStmt = $pdo->prepare("SELECT DATE_FORMAT(paid_at,'%Y-%m') ym, COALESCE(SUM(amount),0) t FROM payments WHERE client_id=? AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym");
    $pStmt->execute([$uid]);
    foreach ($pStmt->fetchAll() as $row) {
        if (isset($paidByMonth[$row['ym']])) {
            $paidByMonth[$row['ym']] = (float) $row['t'];
        }
    }

    $series = insights_build_forecast_series(max($mtd, $outstanding * 0.2), max($prev, 1));
    $healthScore = (int) round(
        min(100, $collection) * 0.45
        + min(100, max(0, 100 - $overdue * 20)) * 0.3
        + min(100, ($active > 0 ? 65 : 40)) * 0.25
    );
    $band = insights_health_band($healthScore);
    $riskTone = $outstanding > 0 ? ($overdue > 0 ? 'poor' : 'medium') : 'good';
    $riskLabel = $overdue > 0 ? __('insights.risk.high') : ($outstanding > 0 ? __('insights.risk.medium') : __('insights.risk.low'));

    $alerts = [];
    if ($overdue > 0) {
        $alerts[] = [
            'tone' => 'danger',
            'icon' => 'alert',
            'title' => __('insights.alert.overdue.title'),
            'text' => __('insights.alert.overdue.text', ['count' => $overdue]),
            'url' => 'payments.php',
        ];
    }
    if ($outstanding > 0) {
        $alerts[] = [
            'tone' => 'amber',
            'icon' => 'coin',
            'title' => __('insights.alert.balance.title'),
            'text' => __('insights.alert.balance.text', ['amount' => money($outstanding)]),
            'url' => 'payments.php',
        ];
    }
    if ($appts > 0) {
        $alerts[] = [
            'tone' => 'info',
            'icon' => 'calendar',
            'title' => __('insights.alert.upcoming_meet.title'),
            'text' => __('insights.alert.upcoming_meet.text', ['count' => $appts]),
            'url' => 'appointments.php',
        ];
    }
    if ($docs > 0) {
        $alerts[] = [
            'tone' => 'ok',
            'icon' => 'doc',
            'title' => __('insights.alert.docs.title'),
            'text' => __('insights.alert.docs.text', ['count' => $docs]),
            'url' => 'documents.php',
        ];
    }

    return [
        'tabs' => insights_hub_tabs('client'),
        'health' => [
            'score' => $healthScore,
            'label' => $band['label'],
            'tone' => $band['tone'],
            'caption' => __('insights.health.caption.client'),
        ],
        'forecast' => [
            'title' => __('insights.forecast.title.client'),
            'risk' => $riskLabel,
            'risk_tone' => $riskTone,
            'labels' => $series['labels'],
            'series' => [
                'base' => $series['base'],
                'best' => $series['best'],
                'worst' => $series['worst'],
            ],
            'kpis' => [
                ['label' => __('insights.kpi.mtd'), 'value' => money($mtd), 'tone' => 'teal', 'url' => 'payments.php'],
                ['label' => __('insights.kpi.active'), 'value' => (string) $active, 'tone' => 'blue', 'url' => 'cases.php'],
                ['label' => __('insights.kpi.due'), 'value' => money($outstanding), 'tone' => 'orange', 'url' => 'payments.php'],
            ],
            'sync' => __('insights.forecast.sync.client', ['cases' => $active, 'amount' => money($mtd)]),
            'badge' => $delta >= 0
                ? __('insights.forecast.paid_up', ['pct' => number_format(abs($delta), 0)])
                : __('insights.forecast.paid_down', ['pct' => number_format(abs($delta), 0)]),
            'badge_tone' => $delta >= 0 ? 'ok' : 'warn',
        ],
        'alerts' => array_slice($alerts, 0, 5),
        'panels' => [
            'overview' => [
                ['label' => __('insights.kpi.due'), 'value' => money($outstanding), 'tone' => 'orange', 'hint' => __('insights.hint.outstanding'), 'url' => 'payments.php'],
                ['label' => __('insights.kpi.active'), 'value' => (string) $active, 'tone' => 'blue', 'hint' => __('insights.hint.open_matters'), 'url' => 'cases.php'],
                ['label' => __('insights.kpi.mtd'), 'value' => money($mtd), 'tone' => 'teal', 'hint' => __('insights.hint.this_month'), 'url' => 'payments.php'],
            ],
            'cases' => [
                ['label' => __('insights.kpi.active'), 'value' => (string) $active, 'tone' => 'teal', 'url' => 'cases.php'],
                ['label' => __('insights.kpi.documents'), 'value' => (string) $docs, 'tone' => 'blue', 'url' => 'documents.php'],
                ['label' => __('insights.kpi.overdue'), 'value' => (string) $overdue, 'tone' => 'orange', 'url' => 'payments.php'],
            ],
            'clients' => [],
            'appointments' => [
                ['label' => __('insights.kpi.meetings'), 'value' => (string) $appts, 'tone' => 'teal', 'url' => 'appointments.php'],
                ['label' => __('insights.kpi.documents'), 'value' => (string) $docs, 'tone' => 'blue', 'url' => 'documents.php'],
                ['label' => __('insights.kpi.due'), 'value' => money($outstanding), 'tone' => 'orange', 'url' => 'payments.php'],
            ],
        ],
        'tables' => insights_billing_tables_client($pdo, $uid),
        'links' => [
            ['label' => __('nav.cases'), 'url' => 'cases.php'],
            ['label' => __('nav.payments'), 'url' => 'payments.php'],
            ['label' => __('nav.appointments'), 'url' => 'appointments.php'],
            ['label' => __('nav.documents'), 'url' => 'documents.php'],
        ],
        'lists' => (static function () use ($pdo, $uid, $docs, $outstanding, $overdue, $active): array {
            $statusStmt = $pdo->prepare('SELECT status label, COUNT(*) c FROM cases WHERE client_id=? GROUP BY status ORDER BY c DESC');
            $statusStmt->execute([$uid]);
            $statuses = $statusStmt->fetchAll();
            $statusTotal = max(1, (int) array_sum(array_map(static fn($r) => (int) $r['c'], $statuses)));
            $caseStmt = $pdo->prepare("SELECT CONCAT(COALESCE(case_number,''),' — ',title) label, status FROM cases WHERE client_id=? ORDER BY updated_at DESC LIMIT 5");
            $caseStmt->execute([$uid]);
            $cases = $caseStmt->fetchAll();
            $apptStmt = $pdo->prepare("SELECT title label, DATE_FORMAT(scheduled_at, '%d %b %H:%i') value FROM appointments WHERE client_id=? AND status IN ('scheduled','confirmed','rescheduled','pending') AND scheduled_at >= NOW() ORDER BY scheduled_at ASC LIMIT 5");
            $apptStmt->execute([$uid]);
            $apptsList = $apptStmt->fetchAll();
            return [
                'overview' => array_map(static fn($r) => [
                    'label' => translate_status((string) $r['label']),
                    'value' => (string) (int) $r['c'],
                    'pct' => (int) round(((int) $r['c'] / $statusTotal) * 100),
                ], $statuses),
                'cases' => array_map(static fn($r) => [
                    'label' => $r['label'],
                    'value' => translate_status((string) $r['status']),
                ], $cases),
                'case_status' => array_map(static fn($r) => [
                    'label' => translate_status((string) $r['label']),
                    'value' => (string) (int) $r['c'],
                    'pct' => (int) round(((int) $r['c'] / $statusTotal) * 100),
                ], $statuses),
                'appointments' => array_map(static fn($r) => ['label' => $r['label'], 'value' => $r['value']], $apptsList),
                'hearings' => [
                    ['label' => __('insights.kpi.documents'), 'value' => (string) $docs],
                    ['label' => __('insights.kpi.overdue'), 'value' => (string) $overdue],
                    ['label' => __('insights.kpi.active'), 'value' => (string) $active],
                ],
            ];
        })(),
        'trend' => [
            'labels' => array_map('format_month_short', $months6),
            'values' => array_values($paidByMonth),
            'series_label' => __('insights.trend.payments'),
        ],
    ];
}
