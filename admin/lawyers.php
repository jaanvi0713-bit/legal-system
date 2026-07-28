<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'staff']);
$pdo = db();
$action = get('action', 'list');
$id = (int) get('id', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postAction = post('form_action');
    if ($postAction === 'save') {
        $editId = (int) post('id');
        $fields = [
            post('first_name'), post('last_name'), post('username'), post('email'), post('phone'),
            post('address'), post('specialization'), post('bar_number'),
            post('availability'), (int) (post('is_active') === '1'),
        ];
        if ($editId) {
            $fields[] = $editId;
            $pdo->prepare('UPDATE users SET first_name=?, last_name=?, username=?, email=?, phone=?, address=?, specialization=?, bar_number=?, availability=?, is_active=? WHERE id=? AND role="lawyer"')->execute($fields);
            flash('success', __('flash.lawyer.updated'));
        } else {
            $password = password_hash(post('password') ?: 'password123', PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (role, first_name, last_name, username, email, password, phone, address, specialization, bar_number, availability, is_active) VALUES ("lawyer",?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    post('first_name'), post('last_name'), post('username'), post('email'), $password, post('phone'),
                    post('address'), post('specialization'), post('bar_number'), post('availability'), (int) (post('is_active') === '1'),
                ]);
            flash('success', __('flash.lawyer.added'));
        }
        redirect('lawyers.php');
    }
    if ($postAction === 'delete') {
        $pdo->prepare('DELETE FROM users WHERE id=? AND role="lawyer"')->execute([(int) post('id')]);
        flash('success', __('flash.lawyer.removed'));
        redirect('lawyers.php');
    }
    if ($postAction === 'assign_case') {
        $lawyerId = (int) post('lawyer_id');
        $caseId = (int) post('case_id');
        $profileCasesUrl = 'lawyers.php?action=view&id=' . max(0, $lawyerId) . '&tab=cases';
        if ($lawyerId < 1 || $caseId < 1) {
            flash('error', __('flash.case.invalid_reference'));
            redirect($profileCasesUrl);
        }
        $assignRole = post('assign_role') === 'lead' ? 'lead' : 'associate';
        $assignedRole = assign_case_to_lawyer($pdo, $caseId, $lawyerId, (int) current_user()['id'], $assignRole);
        if ($assignedRole === null) {
            flash('error', __('flash.case.already_assigned_lawyer'));
            redirect($profileCasesUrl);
        }
        create_notification($pdo, $lawyerId, 'notify.case_assigned_short', 'A case has been assigned to you.', 'case', '../lawyer/cases.php?id=' . $caseId, current_user()['id']);
        flash('success', __('flash.case.assigned_role', ['role' => __('cases.team.' . $assignedRole)]));
        redirect($profileCasesUrl);
    }
}

$pageTitle = __('page.lawyers');
$pageSubtitle = __('page.lawyers.subtitle');
$portal = 'admin';
$activeNav = 'lawyers';

if ($action === 'create' || ($action === 'edit' && $id)) {
    $lawyer = ['id' => 0, 'first_name' => '', 'last_name' => '', 'username' => '', 'email' => '', 'phone' => '', 'address' => '', 'specialization' => '', 'bar_number' => '', 'availability' => 'available', 'is_active' => 1];
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="lawyer"');
        $stmt->execute([$id]);
        $lawyer = $stmt->fetch() ?: $lawyer;
    }
    require __DIR__ . '/../includes/header.php';
    $isEdit = (bool) $id;
    $editCancelUrl = 'lawyers.php';
    if ($isEdit) {
        $from = trim((string) get('from', ''));
        if ($from !== '' && !str_contains($from, '://') && !str_starts_with($from, '//')) {
            $editCancelUrl = $from;
        }
    }
    ?>
    <div class="entity-form-wrap">
    <div class="entity-form panel">
        <div class="entity-form-hero">
            <div>
                <p class="entity-form-eyebrow"><?= $isEdit ? __e('lawyers.eyebrow.edit') : __e('lawyers.eyebrow.create') ?></p>
                <h2><?= $isEdit ? __e('lawyers.edit') : __e('lawyers.add') ?></h2>
                <p class="muted"><?= $isEdit ? __e('lawyers.form.help.edit') : __e('lawyers.form.help.create') ?></p>
            </div>
            <p class="entity-form-required-note"><span class="req">*</span> <?= __e('form.required_fields') ?></p>
        </div>

        <form method="post">
            <div class="entity-form-body">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="save">
                <input type="hidden" name="id" value="<?= (int)$lawyer['id'] ?>">

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3><?= __e('form.section.personal') ?></h3>
                        <p><?= __e('lawyers.section.personal_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group">
                                <label for="first_name"><?= __e('form.first_name') ?> <span class="req" title="<?= __e('form.required') ?>">*</span></label>
                                <input id="first_name" name="first_name" required value="<?= e($lawyer['first_name']) ?>" placeholder="<?= __e('form.placeholder.first_name') ?>">
                            </div>
                            <div class="form-group">
                                <label for="last_name"><?= __e('form.last_name') ?> <span class="req" title="<?= __e('form.required') ?>">*</span></label>
                                <input id="last_name" name="last_name" required value="<?= e($lawyer['last_name']) ?>" placeholder="<?= __e('form.placeholder.last_name') ?>">
                            </div>
                        </div>
                        <div class="entity-field-row entity-field-row--2">
                            <div class="form-group">
                                <label for="email"><?= __e('common.email') ?> <span class="req" title="<?= __e('form.required') ?>">*</span></label>
                                <input id="email" type="email" name="email" required value="<?= e($lawyer['email']) ?>" placeholder="<?= __e('form.placeholder.email_firm') ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone"><?= __e('common.phone') ?></label>
                                <input id="phone" name="phone" value="<?= e($lawyer['phone']) ?>" placeholder="<?= __e('form.placeholder.phone') ?>">
                            </div>
                        </div>
                        <div class="form-group full">
                            <label for="address"><?= __e('common.address') ?></label>
                            <textarea id="address" name="address" rows="2" placeholder="<?= __e('form.placeholder.address_office') ?>"><?= e($lawyer['address']) ?></textarea>
                        </div>
                    </div>
                </section>

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3><?= __e('form.section.account') ?></h3>
                        <p><?= __e('lawyers.section.account_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row">
                            <div class="form-group">
                                <label for="username"><?= __e('form.username') ?> <span class="req" title="<?= __e('form.required') ?>">*</span></label>
                                <input id="username" name="username" required value="<?= e($lawyer['username']) ?>" placeholder="<?= __e('form.placeholder.username') ?>" autocomplete="off">
                            </div>
                            <?php if (!$isEdit): ?>
                            <div class="form-group">
                                <label for="password"><?= __e('form.temp_password') ?></label>
                                <input id="password" name="password" type="text" placeholder="<?= __e('form.password_keep') ?>" autocomplete="off">
                            </div>
                            <?php endif; ?>
                            <div class="form-group">
                                <label for="is_active"><?= __e('form.account_status') ?> <span class="req" title="<?= __e('form.required') ?>">*</span></label>
                                <select id="is_active" name="is_active" required>
                                    <option value="1" <?= $lawyer['is_active'] ? 'selected' : '' ?>><?= __e('status.active') ?></option>
                                    <option value="0" <?= !$lawyer['is_active'] ? 'selected' : '' ?>><?= __e('form.inactive') ?></option>
                                </select>
                            </div>
                        </div>
                        <?php if (!$isEdit): ?>
                        <div class="form-group full entity-field-notes">
                            <span class="field-hint"><?= __e('form.hint.password_default') ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="entity-section">
                    <div class="entity-section-head">
                        <h3><?= __e('form.section.practice') ?></h3>
                        <p><?= __e('lawyers.section.practice_help') ?></p>
                    </div>
                    <div class="form-grid">
                        <div class="entity-field-row">
                            <div class="form-group">
                                <label for="specialization"><?= __e('form.specialization') ?></label>
                                <input id="specialization" name="specialization" value="<?= e($lawyer['specialization']) ?>" placeholder="<?= __e('form.placeholder.specialization') ?>">
                            </div>
                            <div class="form-group">
                                <label for="bar_number"><?= __e('form.bar_number') ?></label>
                                <input id="bar_number" name="bar_number" value="<?= e($lawyer['bar_number']) ?>" placeholder="<?= __e('form.placeholder.bar_number') ?>">
                            </div>
                            <div class="form-group">
                                <label for="availability"><?= __e('form.availability') ?> <span class="req" title="<?= __e('form.required') ?>">*</span></label>
                                <select id="availability" name="availability" required>
                                    <?php foreach (['available', 'busy', 'unavailable'] as $val): ?>
                                        <option value="<?= $val ?>" <?= $lawyer['availability'] === $val ? 'selected' : '' ?>><?= e(translate_status($val)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="entity-form-footer">
                <a class="btn btn-secondary" href="<?= e($editCancelUrl) ?>"><?= __e('common.cancel') ?></a>
                <button class="btn btn-primary" type="submit"><?= $isEdit ? __e('common.save_changes') : __e('lawyers.save') ?></button>
            </div>
        </form>
    </div>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

if ($action === 'view' && $id) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="lawyer"');
    $stmt->execute([$id]);
    $lawyer = $stmt->fetch();
    if (!$lawyer) { flash('error', __('lawyers.not_found')); redirect('lawyers.php'); }
    $cases = $pdo->prepare(
        'SELECT c.*, CONCAT(u.first_name," ",u.last_name) AS client_name,
                COALESCE(
                    (SELECT cl.role FROM case_lawyers cl WHERE cl.case_id = c.id AND cl.lawyer_id = ? LIMIT 1),
                    IF(c.lawyer_id = ?, "lead", "associate")
                ) AS team_role
         FROM cases c
         JOIN users u ON u.id=c.client_id
         WHERE ' . lawyer_case_access_sql('c') . '
         ORDER BY c.updated_at DESC'
    );
    $cases->execute([$id, $id, $id, $id]);
    $cases = $cases->fetchAll();
    $openCases = count(array_filter($cases, fn($c) => $c['status'] !== 'closed'));
    $closedCases = count($cases) - $openCases;
    $assignableCases = cases_assignable_to_lawyer($pdo, $id);

    $pendingApptStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE lawyer_id=? AND status='pending'");
    $pendingApptStmt->execute([$id]);
    $pendingAppts = (int) $pendingApptStmt->fetchColumn();

    $upcomingApptStmt = $pdo->prepare(
        "SELECT a.*, CONCAT(c.first_name,' ',c.last_name) AS client_name
         FROM appointments a
         LEFT JOIN users c ON c.id=a.client_id
         WHERE a.lawyer_id=? AND a.scheduled_at >= NOW()
           AND a.status IN ('scheduled','confirmed','rescheduled','pending')
         ORDER BY a.scheduled_at"
    );
    $upcomingApptStmt->execute([$id]);
    $upcomingAppts = $upcomingApptStmt->fetchAll();

    $hearingLawyerSql = '(h.lawyer_id = ? OR c.lawyer_id = ? OR EXISTS (SELECT 1 FROM case_lawyers cl WHERE cl.case_id = c.id AND cl.lawyer_id = ?))';
    $upcomingHearingStmt = $pdo->prepare(
        "SELECT h.*, c.case_number, c.title
         FROM court_hearings h
         JOIN cases c ON c.id=h.case_id
         WHERE {$hearingLawyerSql} AND h.hearing_date >= NOW() AND h.status='scheduled'
         ORDER BY h.hearing_date"
    );
    $upcomingHearingStmt->execute([$id, $id, $id]);
    $upcomingHearings = $upcomingHearingStmt->fetchAll();

    $upcomingApptCountStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM appointments
         WHERE lawyer_id=? AND scheduled_at >= NOW()
           AND status IN ('scheduled','confirmed','rescheduled','pending')"
    );
    $upcomingApptCountStmt->execute([$id]);
    $upcomingApptCount = (int) $upcomingApptCountStmt->fetchColumn();

    $upcomingHearingCountStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM court_hearings h
         JOIN cases c ON c.id=h.case_id
         WHERE {$hearingLawyerSql} AND h.hearing_date >= NOW() AND h.status='scheduled'"
    );
    $upcomingHearingCountStmt->execute([$id, $id, $id]);
    $upcomingHearingCount = (int) $upcomingHearingCountStmt->fetchColumn();
    $upcomingTotal = $upcomingApptCount + $upcomingHearingCount;

    $scheduleItems = [];
    foreach ($upcomingHearings as $h) {
        $scheduleItems[] = ['kind' => 'hearing', 'sort' => strtotime((string) $h['hearing_date']) ?: PHP_INT_MAX, 'row' => $h];
    }
    foreach ($upcomingAppts as $a) {
        $scheduleItems[] = ['kind' => 'appointment', 'sort' => strtotime((string) $a['scheduled_at']) ?: PHP_INT_MAX, 'row' => $a];
    }
    usort($scheduleItems, static fn(array $a, array $b): int => $a['sort'] <=> $b['sort']);

    $lawyerProfileUrl = 'lawyers.php?action=view&id=' . $id;
    $lawyerScheduleUrl = 'lawyer-availability.php?lawyer_id=' . $id;
    $lawyerApptCreateUrl = 'appointments.php?action=create&lawyer_id=' . $id;
    $lawyerHearingCreateUrl = 'court.php?action=create&lawyer_id=' . $id;

    $workloadPct = count($cases) > 0 ? (int) round(($openCases / count($cases)) * 100) : 0;
    $initials = strtoupper(mb_substr((string) $lawyer['first_name'], 0, 1) . mb_substr((string) $lawyer['last_name'], 0, 1));
    $profileTab = in_array((string) get('tab', ''), ['schedule', 'cases'], true) ? (string) get('tab') : 'overview';
    $pageTitle = __('page.lawyers');
    $pageSubtitle = __('lawyers.profile.greeting_kicker');
    $bodyClass = 'page-glass-dash page-lawyer-profile';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="lawyer-profile-page">
        <div class="lawyer-profile-workspace panel">
            <aside class="lawyer-profile-sidebar" aria-label="<?= __e('lawyers.profile.breadcrumb') ?>">
                <div class="lawyer-profile-sidebar-user">
                    <div class="lawyer-profile-sidebar-identity">
                        <div class="lawyer-profile-avatar" aria-hidden="true"><?= e($initials) ?></div>
                        <div class="lawyer-profile-sidebar-meta">
                            <strong><?= e(full_name($lawyer)) ?></strong>
                            <span><?= e($lawyer['email']) ?></span>
                        </div>
                    </div>
                    <div class="lawyer-profile-chips">
                        <?= status_badge($lawyer['availability']) ?>
                        <?= status_badge($lawyer['is_active'] ? 'active' : 'pending') ?>
                    </div>
                </div>
                <nav class="lawyer-profile-sidebar-nav" role="tablist" aria-label="<?= __e('lawyers.profile.breadcrumb') ?>">
                    <a class="<?= $profileTab === 'overview' ? 'is-active' : '' ?>" href="?action=view&id=<?= $id ?>&tab=overview" role="tab" data-lawyer-tab="overview" aria-selected="<?= $profileTab === 'overview' ? 'true' : 'false' ?>"><?= __e('lawyers.profile.nav_overview') ?></a>
                    <a class="<?= $profileTab === 'schedule' ? 'is-active' : '' ?>" href="?action=view&id=<?= $id ?>&tab=schedule" role="tab" data-lawyer-tab="schedule" aria-selected="<?= $profileTab === 'schedule' ? 'true' : 'false' ?>"><?= __e('lawyers.profile.nav_schedule') ?></a>
                    <a class="<?= $profileTab === 'cases' ? 'is-active' : '' ?>" href="?action=view&id=<?= $id ?>&tab=cases" role="tab" data-lawyer-tab="cases" aria-selected="<?= $profileTab === 'cases' ? 'true' : 'false' ?>"><?= __e('lawyers.profile.nav_cases') ?></a>
                    <a href="?action=edit&id=<?= $id ?>&from=<?= urlencode($lawyerProfileUrl . '&tab=' . $profileTab) ?>"><?= __e('lawyers.profile.nav_edit') ?></a>
                </nav>
                <a class="lawyer-profile-sidebar-back" href="lawyers.php">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                    <?= __e('lawyers.profile.back_lawyers') ?>
                </a>
            </aside>

            <div class="lawyer-profile-canvas">
                <div class="lawyer-profile-canvas-top">
                    <nav class="lawyer-profile-breadcrumb" aria-label="<?= __e('lawyers.profile.breadcrumb') ?>">
                        <a href="lawyers.php"><?= __e('page.lawyers') ?></a>
                        <span aria-hidden="true">/</span>
                        <span><?= e(full_name($lawyer)) ?></span>
                    </nav>
                    <p class="lawyer-profile-canvas-meta"><?= e($lawyer['specialization'] ?: __('lawyers.general_practice')) ?><?php if ($lawyer['bar_number']): ?> · <?= e($lawyer['bar_number']) ?><?php endif; ?></p>
                </div>

            <div class="lawyer-profile-body is-tab-<?= e($profileTab) ?>" data-lawyer-profile-tabs>
                <div class="lawyer-profile-main">
                    <section class="lawyer-profile-tab-panel<?= $profileTab === 'overview' ? ' is-active' : '' ?>" data-lawyer-tab-panel="overview" role="tabpanel"<?= $profileTab !== 'overview' ? ' hidden' : '' ?>>
                        <div class="lawyer-profile-section-head">
                            <div>
                                <h2><?= __e('lawyers.profile.details') ?></h2>
                                <p class="muted"><?= __e('lawyers.profile.details_help') ?></p>
                            </div>
                        </div>
                        <div class="lawyer-profile-content-panel lawyer-profile-content-panel--overview">
                        <div class="lawyer-profile-overview lawyer-profile-overview--split">
                        <div class="lawyer-profile-sheet">
                            <div class="lawyer-profile-sheet-rows">
                                <div class="lawyer-profile-sheet-row">
                                    <span class="lawyer-profile-sheet-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
                                    </span>
                                    <div class="lawyer-profile-sheet-copy">
                                        <span class="lawyer-profile-sheet-label"><?= __e('common.email') ?></span>
                                        <strong><?= e($lawyer['email']) ?></strong>
                                    </div>
                                    <a class="lawyer-profile-sheet-action" href="mailto:<?= e($lawyer['email']) ?>">
                                        <span class="lawyer-profile-sheet-action-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2 11 13"/><path d="M22 2 15 22 11 13 2 9z"/></svg>
                                        </span>
                                        <span><?= __e('lawyers.profile.action_email') ?></span>
                                    </a>
                                </div>
                                <div class="lawyer-profile-sheet-row">
                                    <span class="lawyer-profile-sheet-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h4l2 5-2 1a11 11 0 0 0 5 5l1-2 5 2v4a2 2 0 0 1-2 2A15 15 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>
                                    </span>
                                    <div class="lawyer-profile-sheet-copy">
                                        <span class="lawyer-profile-sheet-label"><?= __e('common.phone') ?></span>
                                        <strong><?= e($lawyer['phone'] ?: __('common.em_dash')) ?></strong>
                                    </div>
                                    <?php if ($lawyer['phone']): ?>
                                    <a class="lawyer-profile-sheet-action" href="tel:<?= e(preg_replace('/\s+/', '', (string) $lawyer['phone'])) ?>">
                                        <span class="lawyer-profile-sheet-action-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 4h4l2 5-2 1a11 11 0 0 0 5 5l1-2 5 2v4a2 2 0 0 1-2 2A15 15 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>
                                        </span>
                                        <span><?= __e('lawyers.profile.action_call') ?></span>
                                    </a>
                                    <?php endif; ?>
                                </div>
                                <div class="lawyer-profile-sheet-row">
                                    <span class="lawyer-profile-sheet-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-4.5 7-11a7 7 0 0 1 14 0c0 6.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                    </span>
                                    <div class="lawyer-profile-sheet-copy">
                                        <span class="lawyer-profile-sheet-label"><?= __e('common.address') ?></span>
                                        <strong><?= e($lawyer['address'] ?: __('common.em_dash')) ?></strong>
                                    </div>
                                </div>
                                <div class="lawyer-profile-sheet-row is-muted">
                                    <span class="lawyer-profile-sheet-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0"/></svg>
                                    </span>
                                    <div class="lawyer-profile-sheet-copy">
                                        <span class="lawyer-profile-sheet-label"><?= __e('form.username') ?></span>
                                        <strong><?= e($lawyer['username']) ?></strong>
                                    </div>
                                </div>
                            </div>
                            <?php if (trim((string) ($lawyer['notes'] ?? '')) !== ''): ?>
                            <div class="lawyer-profile-notes">
                                <h3><?= __e('common.notes') ?></h3>
                                <p><?= nl2br(e($lawyer['notes'])) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <aside class="lawyer-profile-metric-rail" aria-label="<?= __e('lawyers.profile.at_a_glance') ?>">
                            <div class="lawyer-profile-metric-rail-head">
                                <h3 class="lawyer-profile-metric-rail-title"><?= __e('lawyers.profile.at_a_glance') ?></h3>
                                <p class="muted"><?= __e('lawyers.profile.at_a_glance_help') ?></p>
                            </div>
                            <div class="lawyer-profile-metric-rail-list" role="list">
                                <a class="lawyer-profile-metric-row" href="?action=view&id=<?= $id ?>&tab=cases" role="listitem" data-lawyer-tab="cases">
                                    <span class="lawyer-profile-metric-row-value"><?= (int) $openCases ?></span>
                                    <span class="lawyer-profile-metric-row-copy">
                                        <strong><?= __e('lawyers.workload_open') ?></strong>
                                        <span><?= (int) $workloadPct ?>% <?= __e('lawyers.profile.of_caseload') ?></span>
                                    </span>
                                    <span class="lawyer-profile-metric-row-bar" aria-hidden="true"><span style="width: <?= max(0, min(100, $workloadPct)) ?>%;"></span></span>
                                </a>
                                <a class="lawyer-profile-metric-row" href="?action=view&id=<?= $id ?>&tab=cases" role="listitem" data-lawyer-tab="cases">
                                    <span class="lawyer-profile-metric-row-value"><?= count($cases) ?></span>
                                    <span class="lawyer-profile-metric-row-copy">
                                        <strong><?= __e('lawyers.total_cases') ?></strong>
                                        <span><?= (int) $closedCases ?> <?= __e('lawyers.profile.closed_short') ?></span>
                                    </span>
                                </a>
                                <a class="lawyer-profile-metric-row" href="?action=view&id=<?= $id ?>&tab=schedule" role="listitem" data-lawyer-tab="schedule">
                                    <span class="lawyer-profile-metric-row-value"><?= (int) $pendingAppts ?></span>
                                    <span class="lawyer-profile-metric-row-copy">
                                        <strong><?= __e('lawyer.tasks.stat_pending') ?></strong>
                                        <span><?= __e('lawyers.profile.pending_foot') ?></span>
                                    </span>
                                </a>
                                <a class="lawyer-profile-metric-row" href="?action=view&id=<?= $id ?>&tab=schedule" role="listitem" data-lawyer-tab="schedule">
                                    <span class="lawyer-profile-metric-row-value"><?= (int) $upcomingTotal ?></span>
                                    <span class="lawyer-profile-metric-row-copy">
                                        <strong><?= __e('lawyer.tasks.stat_upcoming') ?></strong>
                                        <span><?= __e('lawyers.profile.schedule_short') ?></span>
                                    </span>
                                </a>
                            </div>
                        </aside>
                        </div>
                        </div>
                    </section>

                    <section class="lawyer-profile-tab-panel<?= $profileTab === 'schedule' ? ' is-active' : '' ?>" data-lawyer-tab-panel="schedule" role="tabpanel"<?= $profileTab !== 'schedule' ? ' hidden' : '' ?>>
                        <div class="lawyer-profile-section-head lawyer-profile-section-head--actions">
                            <div>
                                <h2><?= __e('dashboard.panel.upcoming_schedule') ?></h2>
                                <p class="muted" id="lawyerScheduleFilterMeta"><?= e(__('lawyers.profile.schedule_meta', ['count' => count($scheduleItems)])) ?></p>
                            </div>
                            <div class="lawyer-profile-schedule-actions">
                                <details class="row-actions-dropdown">
                                    <summary class="btn btn-primary btn-sm row-actions-toggle" aria-label="<?= __e('common.actions') ?>">
                                        <span><?= __e('common.actions') ?></span>
                                        <svg class="row-actions-caret" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                                    </summary>
                                    <div class="row-actions-menu">
                                        <a class="row-actions-item" href="<?= e($lawyerScheduleUrl) ?>"><?= __e('lawyers.view_schedule') ?></a>
                                        <a class="row-actions-item" href="<?= e($lawyerApptCreateUrl) ?>"><?= __e('lawyers.profile.schedule_appointment') ?></a>
                                        <a class="row-actions-item" href="<?= e($lawyerHearingCreateUrl) ?>"><?= __e('lawyers.profile.schedule_hearing') ?></a>
                                    </div>
                                </details>
                            </div>
                        </div>
                        <div class="lawyer-profile-content-panel">
                        <?php if (!$scheduleItems): ?>
                        <div class="lawyer-profile-empty lawyer-profile-empty--schedule">
                            <span class="lawyer-profile-empty-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                            </span>
                            <p><?= __e('lawyers.profile.no_schedule') ?></p>
                        </div>
                        <?php else: ?>
                        <div class="lawyer-profile-case-filters appt-list-toolbar"
                             id="lawyerScheduleFilterPanel"
                             data-list-filter
                             data-search-id="lawyerScheduleSearch"
                             data-table-id="lawyerScheduleList"
                             data-item-selector=".lawyer-profile-schedule-item[data-list-search]"
                             data-total-meta-id="lawyerScheduleFilterMeta"
                             data-page-size="3"
                             data-pager-id="lawyerSchedulePager"
                             data-pager-label-id="lawyerSchedulePagerLabel"
                             data-pager-showing-one="<?= __e('lawyers.profile.schedule_pager.showing_one') ?>"
                             data-pager-showing-many="<?= __e('lawyers.profile.schedule_pager.showing_many') ?>"
                             data-total-one="<?= __e('lawyers.profile.schedule_meta', ['count' => ':count']) ?>"
                             data-total-many="<?= __e('lawyers.profile.schedule_meta', ['count' => ':count']) ?>">
                            <label class="appt-list-search lawyer-profile-case-search">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                                <input type="search" id="lawyerScheduleSearch" placeholder="<?= __e('lawyers.profile.schedule_search_placeholder') ?>" autocomplete="off" aria-label="<?= __e('lawyers.profile.schedule_search_placeholder') ?>">
                            </label>
                        </div>
                        <div class="lawyer-profile-row-list lawyer-profile-schedule-list" id="lawyerScheduleList">
                            <?php foreach ($scheduleItems as $item):
                                if ($item['kind'] === 'hearing'):
                                    $h = $item['row'];
                                    $scheduleSearch = strtolower(trim(implode(' ', [
                                        $h['case_number'] ?? '',
                                        $h['title'] ?? '',
                                        $h['court_name'] ?? '',
                                        $h['court_location'] ?? '',
                                        $h['hearing_type'] ?? '',
                                        __('nav.court'),
                                        format_datetime($h['hearing_date']),
                                    ])));
                                    $scheduleHref = 'court.php?action=edit&id=' . (int) $h['id'];
                                else:
                                    $a = $item['row'];
                                    $scheduleSearch = strtolower(trim(implode(' ', [
                                        $a['title'] ?? '',
                                        $a['client_name'] ?? '',
                                        $a['appointment_type'] ?? '',
                                        $a['location'] ?? '',
                                        __('nav.appointments'),
                                        format_datetime($a['scheduled_at']),
                                    ])));
                                    $scheduleHref = 'appointments.php?action=edit&id=' . (int) $a['id'];
                                endif;
                            ?>
                            <a class="lawyer-profile-row-card lawyer-profile-row-card--schedule lawyer-profile-schedule-item" href="<?= e($scheduleHref) ?>" data-list-search="<?= e($scheduleSearch) ?>">
                                <?php if ($item['kind'] === 'hearing'):
                                    $h = $item['row']; ?>
                                <div class="lawyer-profile-row-mark is-court" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M6 18V8l6-4 6 4v10M9 18v-4h6v4"/></svg>
                                </div>
                                <div class="lawyer-profile-row-body">
                                    <span class="lawyer-profile-row-label"><?= __e('nav.court') ?></span>
                                    <strong><?= e($h['case_number']) ?></strong>
                                    <span class="muted"><?= e(t_content($h['court_name'] ?: __('common.court'))) ?></span>
                                </div>
                                <time class="lawyer-profile-row-time"><?= e(format_datetime($h['hearing_date'])) ?></time>
                                <?php else:
                                    $a = $item['row']; ?>
                                <div class="lawyer-profile-row-mark" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                </div>
                                <div class="lawyer-profile-row-body">
                                    <span class="lawyer-profile-row-label"><?= __e('nav.appointments') ?></span>
                                    <strong><?= e(t_content($a['title'])) ?></strong>
                                    <span class="muted"><?= e($a['client_name'] ?: __('common.client')) ?></span>
                                </div>
                                <time class="lawyer-profile-row-time"><?= e(format_datetime($a['scheduled_at'])) ?></time>
                                <?php endif; ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <div class="case-list-foot lawyer-profile-case-foot">
                            <p class="case-list-footer muted" id="lawyerSchedulePagerLabel"></p>
                            <nav class="case-list-pager" id="lawyerSchedulePager" aria-label="<?= __e('lawyers.profile.schedule_pagination.aria') ?>" hidden></nav>
                        </div>
                        <?php endif; ?>
                        </div>
                    </section>

                    <section class="lawyer-profile-tab-panel<?= $profileTab === 'cases' ? ' is-active' : '' ?>" data-lawyer-tab-panel="cases" role="tabpanel"<?= $profileTab !== 'cases' ? ' hidden' : '' ?>>
                        <div class="lawyer-profile-section-head">
                            <div>
                                <h2><?= __e('lawyers.case_list') ?></h2>
                                <p class="muted" id="lawyerCaseFilterMeta"><?= e(__('lawyers.profile.cases_help', ['count' => count($cases)])) ?></p>
                            </div>
                        </div>
                        <div class="lawyer-profile-content-panel">
                        <div class="lawyer-profile-assign">
                            <div class="lawyer-profile-assign-copy">
                                <span class="lawyer-profile-assign-label"><?= __e('lawyers.assign_case') ?></span>
                                <p class="muted lawyer-profile-assign-hint"><?= __e('lawyers.profile.assign_help') ?></p>
                            </div>
                            <?php if (!$assignableCases): ?>
                            <p class="muted lawyer-profile-assign-empty"><?= __e('lawyers.profile.no_assignable') ?></p>
                            <?php else: ?>
                            <form method="post" class="lawyer-profile-assign-form inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="form_action" value="assign_case">
                                <input type="hidden" name="lawyer_id" value="<?= $id ?>">
                                <select id="assign_case_id" name="case_id" required aria-label="<?= __e('form.select_case') ?>">
                                    <option value=""><?= __e('form.select_case') ?></option>
                                    <?php foreach ($assignableCases as $c): ?>
                                        <option value="<?= (int) $c['id'] ?>"><?= e($c['case_number'] . ' — ' . $c['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select id="assign_case_role" name="assign_role" required aria-label="<?= __e('lawyers.assign_role') ?>">
                                    <option value="associate"><?= __e('cases.team.associate') ?></option>
                                    <option value="lead"><?= __e('cases.team.lead') ?></option>
                                </select>
                                <button class="btn btn-primary btn-sm" type="submit"><?= __e('lawyers.assign') ?></button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php if (!$cases): ?>
                        <div class="lawyer-profile-empty lawyer-profile-empty--cases"><p class="muted"><?= __e('cases.empty.no_active') ?></p></div>
                        <?php else: ?>
                        <div class="lawyer-profile-case-filters appt-list-toolbar"
                             id="lawyerCaseFilterPanel"
                             data-list-filter
                             data-search-id="lawyerCaseSearch"
                             data-table-id="lawyerCaseTable"
                             data-total-meta-id="lawyerCaseFilterMeta"
                             data-page-size="3"
                             data-pager-id="lawyerCasePager"
                             data-pager-label-id="lawyerCasePagerLabel"
                             data-pager-showing-one="<?= __e('lawyers.profile.cases_pager.showing_one') ?>"
                             data-pager-showing-many="<?= __e('lawyers.profile.cases_pager.showing_many') ?>"
                             data-total-one="<?= __e('lawyers.profile.cases_help', ['count' => ':count']) ?>"
                             data-total-many="<?= __e('lawyers.profile.cases_help', ['count' => ':count']) ?>">
                            <label class="appt-list-search lawyer-profile-case-search">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                                <input type="search" id="lawyerCaseSearch" placeholder="<?= __e('lawyers.profile.cases_search_placeholder') ?>" autocomplete="off" aria-label="<?= __e('lawyers.profile.cases_search_placeholder') ?>">
                            </label>
                        </div>
                        <div class="table-wrap case-table-wrap lawyer-profile-table-wrap">
                            <table class="case-table" id="lawyerCaseTable">
                                <thead>
                                    <tr>
                                        <th><?= __e('common.case') ?></th>
                                        <th><?= __e('common.client') ?></th>
                                        <th><?= __e('lawyers.case_role') ?></th>
                                        <th><?= __e('common.status') ?></th>
                                        <th><?= __e('common.priority') ?></th>
                                        <th><?= __e('common.last_updated') ?></th>
                                        <th class="col-actions"><?= __e('common.actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($cases as $c):
                                    $teamRole = (string) ($c['team_role'] ?? 'associate');
                                    $caseStatus = (string) ($c['status'] ?? '');
                                    $casePriority = (string) ($c['priority'] ?? '');
                                    $caseSearchBlob = strtolower(trim(implode(' ', [
                                        $c['case_number'] ?? '',
                                        $c['title'] ?? '',
                                        $c['client_name'] ?? '',
                                        $teamRole,
                                        __('cases.team.' . $teamRole),
                                        $caseStatus,
                                        translate_status($caseStatus),
                                        $casePriority,
                                        translate_status($casePriority),
                                    ])));
                                ?>
                                    <tr data-list-role="<?= e($teamRole) ?>" data-list-status="<?= e($caseStatus) ?>" data-list-priority="<?= e($casePriority) ?>" data-list-search="<?= e($caseSearchBlob) ?>">
                                        <td>
                                            <a class="lawyer-profile-case-link" href="cases.php?action=view&id=<?= (int) $c['id'] ?>">
                                                <span class="lawyer-profile-case-text">
                                                    <strong><?= e($c['case_number']) ?></strong>
                                                    <span class="muted"><?= e($c['title']) ?></span>
                                                </span>
                                            </a>
                                        </td>
                                        <td><?= e($c['client_name']) ?></td>
                                        <td><?= case_team_role_badge($teamRole) ?></td>
                                        <td><?= status_badge($caseStatus) ?></td>
                                        <td><?= status_badge($casePriority) ?></td>
                                        <td><?= e(format_date($c['updated_at'])) ?></td>
                                        <td class="col-actions">
                                            <a class="btn btn-row-open btn-sm btn-row-fit" href="cases.php?action=view&id=<?= (int) $c['id'] ?>"><?= __e('common.open') ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="case-list-foot lawyer-profile-case-foot">
                            <p class="case-list-footer muted" id="lawyerCasePagerLabel"></p>
                            <nav class="case-list-pager" id="lawyerCasePager" aria-label="<?= __e('lawyers.profile.cases_pagination.aria') ?>" hidden></nav>
                        </div>
                        <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>
            </div>
        </div>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; exit;
}

$lawyers = $pdo->query("SELECT l.*, (SELECT COUNT(*) FROM cases c WHERE c.lawyer_id=l.id AND c.status!='closed') AS open_cases FROM users l WHERE l.role='lawyer' ORDER BY l.first_name")->fetchAll();
require __DIR__ . '/../includes/header.php';
$totalLawyers = count($lawyers);
$perPage = 10;
$page = max(1, (int) get('page', 1));
$totalPages = max(1, (int) ceil($totalLawyers / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$pageLawyers = array_slice($lawyers, $offset, $perPage);
$shownFrom = $totalLawyers === 0 ? 0 : $offset + 1;
$shownTo = min($offset + count($pageLawyers), $totalLawyers);
?>
<div class="panel case-list-panel">
    <div class="case-list-head">
        <div class="case-list-title">
            <h2><?= __e('lawyers.list') ?></h2>
        </div>
        <a class="btn btn-primary btn-sm" href="?action=create"><?= __e('lawyers.add') ?></a>
    </div>
    <div class="table-wrap case-table-wrap">
        <table class="case-table">
            <thead><tr><th><?= __e('common.lawyer') ?></th><th><?= __e('form.specialization') ?></th><th><?= __e('common.workload') ?></th><th><?= __e('form.availability') ?></th><th class="col-actions"><?= __e('common.actions') ?></th></tr></thead>
            <tbody>
            <?php foreach ($pageLawyers as $l): ?>
                <tr>
                    <td><a href="?action=view&id=<?= (int)$l['id'] ?>"><strong><?= e(full_name($l)) ?></strong></a><div class="muted"><?= e($l['email']) ?></div></td>
                    <td><?= e($l['specialization'] ?: __('common.em_dash')) ?></td>
                    <td><?= e(__('lawyers.open_count', ['count' => (int)$l['open_cases']])) ?></td>
                    <td><?= status_badge($l['availability']) ?></td>
                    <td class="col-actions">
                        <div class="row-actions">
                            <a class="btn btn-row-open btn-sm btn-row-fit" href="lawyer-availability.php?lawyer_id=<?= (int)$l['id'] ?>"><?= __e('lawyers.view_schedule') ?></a>
                            <a class="btn btn-row-edit btn-sm" href="?action=edit&id=<?= (int)$l['id'] ?>"><?= __e('common.edit') ?></a>
                            <form method="post" data-confirm="<?= __e('confirm.remove_lawyer') ?>"><?= csrf_field() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-row-delete btn-sm" type="submit"><?= __e('common.remove') ?></button></form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$lawyers): ?>
                <tr><td colspan="5" class="case-empty muted"><?= __e('lawyers.empty.none') ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php render_case_list_pager(
        $page,
        $totalPages,
        (int) $shownFrom,
        (int) $shownTo,
        (int) $totalLawyers,
        'lawyers.pager.showing_one',
        'lawyers.pager.showing_many',
        'lawyers.pagination.aria',
        static fn(int $p): string => '?page=' . $p
    ); ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
