<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'staff']);
$pdo = db();
$type = get('type', 'overview');

$clientReport = $pdo->query("SELECT c.id, c.first_name, c.last_name, c.company_name, COUNT(cs.id) AS case_count, COALESCE(SUM(i.total),0) AS billed FROM users c LEFT JOIN cases cs ON cs.client_id=c.id LEFT JOIN invoices i ON i.client_id=c.id WHERE c.role='client' GROUP BY c.id ORDER BY billed DESC")->fetchAll();
$lawyerReport = $pdo->query("SELECT l.id, l.first_name, l.last_name, l.specialization, SUM(CASE WHEN c.status!='closed' THEN 1 ELSE 0 END) AS open_cases, COUNT(c.id) AS total_cases FROM users l LEFT JOIN cases c ON c.lawyer_id=l.id WHERE l.role='lawyer' GROUP BY l.id")->fetchAll();
$caseReport = $pdo->query("SELECT status, COUNT(*) AS total FROM cases GROUP BY status")->fetchAll();
$revenueReport = $pdo->query("SELECT DATE_FORMAT(paid_at,'%Y-%m') AS month, SUM(amount) AS total FROM payments GROUP BY DATE_FORMAT(paid_at,'%Y-%m') ORDER BY month DESC")->fetchAll();
$appointmentReport = $pdo->query("SELECT status, COUNT(*) AS total FROM appointments GROUP BY status")->fetchAll();
$paymentReport = $pdo->query("SELECT payment_method, COUNT(*) AS cnt, SUM(amount) AS total FROM payments GROUP BY payment_method")->fetchAll();

$pageTitle = 'Reports';
$pageSubtitle = 'Client, lawyer, case, revenue, appointment, and payment reports';
$portal = 'admin';
$activeNav = 'reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <div class="quick-links">
        <?php foreach (['overview'=>'Overview','clients'=>'Clients','lawyers'=>'Lawyers','cases'=>'Cases','revenue'=>'Revenue','appointments'=>'Appointments','payments'=>'Payments'] as $k=>$label): ?>
            <a class="chip" href="?type=<?= $k ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
</div>
<?php if ($type === 'clients'): ?>
<div class="panel"><h2>Client report</h2>
<table><thead><tr><th>Client</th><th>Company</th><th>Cases</th><th>Billed</th></tr></thead><tbody>
<?php foreach ($clientReport as $r): ?><tr><td><?= e(full_name($r)) ?></td><td><?= e($r['company_name']?:'—') ?></td><td><?= (int)$r['case_count'] ?></td><td><?= e(money($r['billed'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php elseif ($type === 'lawyers'): ?>
<div class="panel"><h2>Lawyer report</h2>
<table><thead><tr><th>Lawyer</th><th>Specialization</th><th>Open</th><th>Total</th></tr></thead><tbody>
<?php foreach ($lawyerReport as $r): ?><tr><td><?= e(full_name($r)) ?></td><td><?= e($r['specialization']?:'—') ?></td><td><?= (int)$r['open_cases'] ?></td><td><?= (int)$r['total_cases'] ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php elseif ($type === 'cases'): ?>
<div class="panel"><h2>Case report</h2>
<div class="grid grid-3"><?php foreach ($caseReport as $r): ?><div class="stat-card"><div class="stat-label"><?= e(ucwords(str_replace('_',' ',$r['status']))) ?></div><div class="stat-value"><?= (int)$r['total'] ?></div></div><?php endforeach; ?></div></div>
<?php elseif ($type === 'revenue'): ?>
<div class="panel"><h2>Revenue report</h2>
<table><thead><tr><th>Month</th><th>Revenue</th></tr></thead><tbody>
<?php foreach ($revenueReport as $r): ?><tr><td><?= e($r['month']) ?></td><td><?= e(money($r['total'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php elseif ($type === 'appointments'): ?>
<div class="panel"><h2>Appointment report</h2>
<div class="grid grid-3"><?php foreach ($appointmentReport as $r): ?><div class="stat-card"><div class="stat-label"><?= e(ucfirst($r['status'])) ?></div><div class="stat-value"><?= (int)$r['total'] ?></div></div><?php endforeach; ?></div></div>
<?php elseif ($type === 'payments'): ?>
<div class="panel"><h2>Payment report</h2>
<table><thead><tr><th>Method</th><th>Count</th><th>Total</th></tr></thead><tbody>
<?php foreach ($paymentReport as $r): ?><tr><td><?= e(ucwords(str_replace('_',' ',$r['payment_method']))) ?></td><td><?= (int)$r['cnt'] ?></td><td><?= e(money($r['total'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php else: ?>
<div class="grid grid-2">
    <div class="panel"><h2>Cases by status</h2><div class="list-stack"><?php foreach ($caseReport as $r): ?><div class="list-item"><strong><?= e(ucwords(str_replace('_',' ',$r['status']))) ?></strong><?= (int)$r['total'] ?></div><?php endforeach; ?></div></div>
    <div class="panel"><h2>Revenue by month</h2><div class="list-stack"><?php foreach ($revenueReport as $r): ?><div class="list-item"><strong><?= e($r['month']) ?></strong><?= e(money($r['total'])) ?></div><?php endforeach; ?></div></div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
