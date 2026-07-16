<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['client']);
$pdo = db();
$uid = (int) current_user()['id'];

$lawyerId = current_user()['assigned_lawyer_id'];
if (!$lawyerId) {
    $stmt = $pdo->prepare('SELECT lawyer_id FROM cases WHERE client_id=? AND lawyer_id IS NOT NULL ORDER BY updated_at DESC LIMIT 1');
    $stmt->execute([$uid]);
    $lawyerId = $stmt->fetchColumn() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$lawyerId) {
        flash('error', 'No lawyer assigned yet.');
        redirect('contact.php');
    }
    $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, case_id, subject, body) VALUES (?,?,?,?,?)')
        ->execute([$uid, $lawyerId, post('case_id') ?: null, post('subject'), post('body')]);
    create_notification($pdo, (int)$lawyerId, 'Client message', post('subject'), 'info', '../lawyer/clients.php', $uid);
    flash('success', 'Message sent to your lawyer.');
    redirect('contact.php');
}

$messages = [];
if ($lawyerId) {
    $stmt = $pdo->prepare('SELECT m.*, CONCAT(s.first_name," ",s.last_name) AS sender_name FROM messages m JOIN users s ON s.id=m.sender_id WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?) ORDER BY m.created_at DESC LIMIT 30');
    $stmt->execute([$uid, $lawyerId, $lawyerId, $uid]);
    $messages = $stmt->fetchAll();
}
$lawyer = null;
if ($lawyerId) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
    $stmt->execute([$lawyerId]);
    $lawyer = $stmt->fetch();
}
$cases = $pdo->prepare('SELECT id, case_number FROM cases WHERE client_id=?');
$cases->execute([$uid]);
$cases = $cases->fetchAll();

$pageTitle = __('page.contact');
$pageSubtitle = 'Send messages, submit requests, and ask case questions';
$portal = 'client';
$activeNav = 'contact';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-2">
    <div class="panel">
        <h2>Your lawyer</h2>
        <?php if ($lawyer): ?>
            <div class="list-item"><strong><?= e(full_name($lawyer)) ?></strong><div class="muted"><?= e($lawyer['specialization'] ?: '') ?></div><div><?= e($lawyer['email']) ?> · <?= e($lawyer['phone'] ?: '') ?></div></div>
        <?php else: ?>
            <div class="empty-state">A lawyer has not been assigned yet.</div>
        <?php endif; ?>
        <h3 style="margin-top:1.2rem;">New message / request</h3>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <div class="form-group full"><label>Subject</label><input name="subject" required></div>
            <div class="form-group full"><label>Related case</label><select name="case_id"><option value="">—</option><?php foreach ($cases as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['case_number']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group full"><label>Message</label><textarea name="body" required placeholder="Ask a question or submit a request…"></textarea></div>
            <div class="form-actions full"><button class="btn btn-primary" type="submit" <?= $lawyer ? '' : 'disabled' ?>>Send</button></div>
        </form>
    </div>
    <div class="panel">
        <h2>Conversation</h2>
        <div class="list-stack">
            <?php foreach ($messages as $m): ?>
                <div class="list-item">
                    <strong><?= e($m['sender_name']) ?> · <?= e($m['subject'] ?: 'Message') ?></strong>
                    <span class="muted"><?= e(format_datetime($m['created_at'])) ?></span>
                    <div><?= nl2br(e($m['body'])) ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$messages): ?><div class="empty-state">No messages yet.</div><?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
