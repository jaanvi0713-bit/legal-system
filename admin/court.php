<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'staff']);
$pdo = db();
$action = get('action', 'list');
$id = (int) get('id', 0);
$preCase = (int) get('case_id', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'save') {
        $editId = (int) post('id');
        $vals = [
            (int) post('case_id'), post('hearing_date'), post('court_name'), post('court_location'),
            post('judge_name'), post('hearing_type'), post('outcome'), post('notes'), post('status'),
        ];
        if ($editId) {
            $vals[] = $editId;
            $pdo->prepare('UPDATE court_hearings SET case_id=?, hearing_date=?, court_name=?, court_location=?, judge_name=?, hearing_type=?, outcome=?, notes=?, status=? WHERE id=?')->execute($vals);
            flash('success', 'Hearing updated.');
        } else {
            $vals[] = current_user()['id'];
            $pdo->prepare('INSERT INTO court_hearings (case_id, hearing_date, court_name, court_location, judge_name, hearing_type, outcome, notes, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute($vals);
            $pdo->prepare('UPDATE cases SET next_hearing_date = DATE(?) WHERE id=?')->execute([post('hearing_date'), (int) post('case_id')]);
            flash('success', 'Hearing recorded.');
        }
        redirect('court.php');
    }
}

$cases = $pdo->query('SELECT id, case_number, title FROM cases ORDER BY created_at DESC')->fetchAll();
$pageTitle = __('page.court');
$pageSubtitle = 'Hearings, locations, outcomes, and progress';
$portal = 'admin';
$activeNav = 'court';

if ($action === 'create' || ($action === 'edit' && $id)) {
    $row = ['id'=>0,'case_id'=>$preCase,'hearing_date'=>date('Y-m-d\TH:i'),'court_name'=>'','court_location'=>'','judge_name'=>'','hearing_type'=>'','outcome'=>'','notes'=>'','status'=>'scheduled'];
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM court_hearings WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: $row;
        $row['hearing_date'] = date('Y-m-d\TH:i', strtotime($row['hearing_date']));
    }
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="panel">
        <h2><?= $id ? 'Edit hearing' : 'Add hearing' ?></h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="save"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <div class="form-group full"><label>Case</label>
                <select name="case_id" required><?php foreach ($cases as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$row['case_id']===(int)$c['id']?'selected':'' ?>><?= e($c['case_number'].' — '.$c['title']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label>Hearing date</label><input type="datetime-local" name="hearing_date" required value="<?= e($row['hearing_date']) ?>"></div>
            <div class="form-group"><label>Status</label>
                <select name="status"><?php foreach (['scheduled','completed','adjourned','cancelled'] as $s): ?><option value="<?= $s ?>" <?= $row['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label>Court name</label><input name="court_name" required value="<?= e($row['court_name']) ?>"></div>
            <div class="form-group"><label>Location</label><input name="court_location" value="<?= e($row['court_location']) ?>"></div>
            <div class="form-group"><label>Judge</label><input name="judge_name" value="<?= e($row['judge_name']) ?>"></div>
            <div class="form-group"><label>Hearing type</label><input name="hearing_type" value="<?= e($row['hearing_type']) ?>"></div>
            <div class="form-group full"><label>Outcome</label><textarea name="outcome"><?= e($row['outcome']) ?></textarea></div>
            <div class="form-group full"><label>Court notes</label><textarea name="notes"><?= e($row['notes']) ?></textarea></div>
            <div class="form-actions full"><button class="btn btn-primary" type="submit">Save</button><a class="btn btn-ghost" href="court.php">Cancel</a></div>
        </form>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

$rows = $pdo->query("SELECT h.*, c.case_number, c.title FROM court_hearings h JOIN cases c ON c.id=h.case_id ORDER BY h.hearing_date DESC")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <div class="panel-header"><h2>Court hearings</h2><a class="btn btn-primary btn-sm" href="?action=create">Add hearing</a></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Case</th><th>Court / location</th><th>Type</th><th>Status</th><th>Outcome</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e(format_datetime($r['hearing_date'])) ?></td>
                    <td><a href="cases.php?action=view&id=<?= (int)$r['case_id'] ?>"><?= e($r['case_number']) ?></a><div class="muted"><?= e($r['title']) ?></div></td>
                    <td><?= e($r['court_name']) ?><div class="muted"><?= e($r['court_location']) ?></div></td>
                    <td><?= e($r['hearing_type'] ?: '—') ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td><?= e($r['outcome'] ?: '—') ?></td>
                    <td><a class="chip" href="?action=edit&id=<?= (int)$r['id'] ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
