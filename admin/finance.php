<?php
/**
 * Finance was moved into Open Case → Invoices / Receipts.
 * Keep this file so old bookmarks and notification links still work.
 */
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'staff']);
$pdo = db();

$invoiceId = (int) get('invoice_id', 0);
if (get('action') === 'payment' && $invoiceId > 0) {
    $stmt = $pdo->prepare('SELECT case_id FROM invoices WHERE id = ?');
    $stmt->execute([$invoiceId]);
    $caseId = (int) $stmt->fetchColumn();
    if ($caseId > 0) {
        redirect('cases.php?action=view&id=' . $caseId . '&tab=receipts&compose=payment&invoice_id=' . $invoiceId);
    }
}

flash('info', 'Billing is managed inside each case (Invoices and Receipts tabs).');
redirect('cases.php');
