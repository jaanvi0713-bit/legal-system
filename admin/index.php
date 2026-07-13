<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'staff']);
$pdo = db();

$stats = [
    'clients' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn(),
    'lawyers' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='lawyer'")->fetchColumn(),
    'active_cases' => (int) $pdo->query("SELECT COUNT(*) FROM cases WHERE status IN ('open','active','pending','reopened','on_hold')")->fetchColumn(),
    'closed_cases' => (int) $pdo->query("SELECT COUNT(*) FROM cases WHERE status='closed'")->fetchColumn(),
    'revenue' => (float) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM payments')->fetchColumn(),
];

$payments = $pdo->query('SELECT p.*, u.first_name, u.last_name FROM payments p JOIN users u ON u.id = p.client_id ORDER BY p.paid_at DESC LIMIT 6')->fetchAll();
$appointments = $pdo->query("SELECT a.*, CONCAT(c.first_name,' ',c.last_name) AS client_name FROM appointments a LEFT JOIN users c ON c.id = a.client_id WHERE a.scheduled_at >= NOW() AND a.status NOT IN ('cancelled','rejected') ORDER BY a.scheduled_at ASC LIMIT 6")->fetchAll();
$notifications = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 6');
$notifications->execute([current_user()['id']]);
$notifications = $notifications->fetchAll();
$activities = $pdo->query('SELECT a.*, CONCAT(u.first_name," ",u.last_name) AS actor FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 8')->fetchAll();

$pageTitle = 'Dashboard';
$pageSubtitle = 'Firm-wide overview and quick actions';
$portal = 'admin';
$activeNav = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-4">
    <div class="stat-card"><div class="stat-label">Clients</div><div class="stat-value"><?= $stats['clients'] ?></div></div>
    <div class="stat-card"><div class="stat-label">Lawyers</div><div class="stat-value"><?= $stats['lawyers'] ?></div></div>
    <div class="stat-card"><div class="stat-label">Active cases</div><div class="stat-value"><?= $stats['active_cases'] ?></div></div>
    <div class="stat-card"><div class="stat-label">Closed cases</div><div class="stat-value"><?= $stats['closed_cases'] ?></div></div>
</div>
<div class="grid grid-2">
    <div class="stat-card"><div class="stat-label">Total revenue</div><div class="stat-value"><?= e(money($stats['revenue'])) ?></div></div>
    <div class="panel">
        <h2>Quick access</h2>
        <div class="quick-links">
            <a class="chip" href="clients.php?action=create">Add client</a>
            <a class="chip" href="cases.php?action=create">New case</a>
            <a class="chip" href="appointments.php?action=create">Schedule appointment</a>
            <a class="chip" href="finance.php?action=invoice">Create invoice</a>
            <a class="chip" href="reports.php">Reports</a>
            <a class="chip" href="ai.php">AI Assistant</a>
        </div>
    </div>
</div>
<div class="grid grid-2">
    <div class="panel">
        <div class="panel-header"><h2>Recent payments</h2><a href="finance.php">View all</a></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Client</th><th>Amount</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= e(full_name($p)) ?></td>
                        <td><?= e(money($p['amount'])) ?></td>
                        <td><?= e(format_datetime($p['paid_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="panel">
        <div class="panel-header"><h2>Upcoming appointments</h2><a href="appointments.php">Manage</a></div>
        <div class="list-stack">
            <?php foreach ($appointments as $a): ?>
                <div class="list-item">
                    <strong><?= e($a['title']) ?></strong>
                    <span class="muted"><?= e(format_datetime($a['scheduled_at'])) ?> · <?= e($a['client_name'] ?? '—') ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$appointments): ?><div class="empty-state">No upcoming appointments.</div><?php endif; ?>
        </div>
    </div>
</div>
<div class="grid grid-2">
    <div class="panel">
        <div class="panel-header"><h2>Notifications</h2><a href="notifications.php">Open</a></div>
        <div class="list-stack">
            <?php foreach ($notifications as $n): ?>
                <div class="list-item">
                    <strong><?= e($n['title']) ?></strong>
                    <span class="muted"><?= e($n['message']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="panel">
        <div class="panel-header"><h2>Recent activity</h2></div>
        <div class="list-stack">
            <?php foreach ($activities as $a): ?>
                <div class="list-item">
                    <strong><?= e($a['actor'] ?: 'System') ?> · <?= e($a['action']) ?></strong>
                    <span class="muted"><?= e($a['description'] ?? '') ?> · <?= e(format_datetime($a['created_at'])) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
