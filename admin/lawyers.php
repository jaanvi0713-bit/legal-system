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
        if ($lawyerId < 1 || $caseId < 1) {
            flash('error', __('flash.case.invalid_reference'));
            redirect('lawyers.php?action=view&id=' . max(0, $lawyerId));
        }
        sync_case_lawyers($pdo, $caseId, $lawyerId, [], (int) current_user()['id']);
        create_notification($pdo, $lawyerId, 'notify.case_assigned_short', 'A case has been assigned to you.', 'case', '../lawyer/cases.php?id=' . $caseId, current_user()['id']);
        flash('success', __('flash.case.assigned'));
        redirect('lawyers.php?action=view&id=' . $lawyerId);
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
                <a class="btn btn-secondary" href="lawyers.php"><?= __e('common.cancel') ?></a>
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
    $cases = $pdo->prepare('SELECT c.*, CONCAT(u.first_name," ",u.last_name) AS client_name FROM cases c JOIN users u ON u.id=c.client_id WHERE c.lawyer_id=? ORDER BY c.updated_at DESC');
    $cases->execute([$id]);
    $cases = $cases->fetchAll();
    $openCases = count(array_filter($cases, fn($c) => $c['status'] !== 'closed'));
    $closedCases = count($cases) - $openCases;
    $unassigned = $pdo->query("SELECT id, case_number, title FROM cases WHERE lawyer_id IS NULL OR lawyer_id=0 ORDER BY created_at DESC")->fetchAll();

    $pendingApptStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE lawyer_id=? AND status='pending'");
    $pendingApptStmt->execute([$id]);
    $pendingAppts = (int) $pendingApptStmt->fetchColumn();

    $upcomingApptStmt = $pdo->prepare(
        "SELECT a.*, CONCAT(c.first_name,' ',c.last_name) AS client_name
         FROM appointments a
         LEFT JOIN users c ON c.id=a.client_id
         WHERE a.lawyer_id=? AND a.scheduled_at >= NOW()
           AND a.status IN ('scheduled','confirmed','rescheduled','pending')
         ORDER BY a.scheduled_at LIMIT 8"
    );
    $upcomingApptStmt->execute([$id]);
    $upcomingAppts = $upcomingApptStmt->fetchAll();

    $upcomingHearingStmt = $pdo->prepare(
        "SELECT h.*, c.case_number, c.title
         FROM court_hearings h
         JOIN cases c ON c.id=h.case_id
         WHERE c.lawyer_id=? AND h.hearing_date >= NOW() AND h.status='scheduled'
         ORDER BY h.hearing_date LIMIT 8"
    );
    $upcomingHearingStmt->execute([$id]);
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
         WHERE c.lawyer_id=? AND h.hearing_date >= NOW() AND h.status='scheduled'"
    );
    $upcomingHearingCountStmt->execute([$id]);
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
    $scheduleItems = array_slice($scheduleItems, 0, 6);

    $workloadPct = count($cases) > 0 ? (int) round(($openCases / count($cases)) * 100) : 0;
    $initials = strtoupper(mb_substr((string) $lawyer['first_name'], 0, 1) . mb_substr((string) $lawyer['last_name'], 0, 1));
    $pageTitle = __('page.lawyers');
    $pageSubtitle = __('lawyers.profile.greeting_kicker');
    $bodyClass = 'page-glass-dash page-lawyer-profile';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="lawyer-profile-page">
        <header class="panel lawyer-profile-banner">
            <div class="lawyer-profile-banner-inner">
                <div class="lawyer-profile-banner-toolbar">
                    <nav class="lawyer-profile-breadcrumb" aria-label="<?= __e('lawyers.profile.breadcrumb') ?>">
                        <a href="lawyers.php"><?= __e('page.lawyers') ?></a>
                        <span aria-hidden="true">/</span>
                        <span><?= e(full_name($lawyer)) ?></span>
                    </nav>
                    <div class="lawyer-profile-banner-actions row-actions">
                        <a class="btn btn-row-open btn-sm btn-row-fit" href="lawyer-availability.php?lawyer_id=<?= (int) $id ?>"><?= __e('lawyers.view_schedule') ?></a>
                        <a class="btn btn-row-edit btn-sm btn-row-fit" href="?action=edit&id=<?= $id ?>"><?= __e('lawyers.edit_profile') ?></a>
                    </div>
                </div>

                <div class="lawyer-profile-banner-hero">
                    <div class="lawyer-profile-avatar" aria-hidden="true"><?= e($initials) ?></div>
                    <div class="lawyer-profile-identity">
                        <h1 class="lawyer-profile-name"><?= e(full_name($lawyer)) ?></h1>
                        <p class="lawyer-profile-meta"><?= e($lawyer['specialization'] ?: __('lawyers.general_practice')) ?><?php if ($lawyer['bar_number']): ?> · <?= e($lawyer['bar_number']) ?><?php endif; ?></p>
                        <div class="lawyer-profile-chips">
                            <?= status_badge($lawyer['availability']) ?>
                            <?= status_badge($lawyer['is_active'] ? 'active' : 'pending') ?>
                        </div>
                    </div>
                </div>

                <div class="lawyer-profile-metric-strip" role="list">
                    <div class="lawyer-profile-metric is-tone-cases" role="listitem">
                        <div class="lawyer-profile-metric-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
                        </div>
                        <div class="lawyer-profile-metric-body">
                            <span class="lawyer-profile-metric-value"><?= (int) $openCases ?></span>
                            <span class="lawyer-profile-metric-label"><?= __e('lawyers.workload_open') ?></span>
                            <span class="lawyer-profile-metric-foot"><?= (int) $workloadPct ?>% <?= __e('lawyers.profile.of_caseload') ?></span>
                        </div>
                    </div>
                    <div class="lawyer-profile-metric is-tone-clients" role="listitem">
                        <div class="lawyer-profile-metric-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7h18M3 12h18M3 17h18"/></svg>
                        </div>
                        <div class="lawyer-profile-metric-body">
                            <span class="lawyer-profile-metric-value"><?= count($cases) ?></span>
                            <span class="lawyer-profile-metric-label"><?= __e('lawyers.total_cases') ?></span>
                            <span class="lawyer-profile-metric-foot"><?= (int) $closedCases ?> <?= __e('lawyers.profile.closed_short') ?></span>
                        </div>
                    </div>
                    <div class="lawyer-profile-metric is-tone-revenue<?= $pendingAppts > 0 ? ' is-alert' : '' ?>" role="listitem">
                        <div class="lawyer-profile-metric-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 6v6l4 2"/><circle cx="12" cy="12" r="9"/></svg>
                        </div>
                        <div class="lawyer-profile-metric-body">
                            <span class="lawyer-profile-metric-value"><?= (int) $pendingAppts ?></span>
                            <span class="lawyer-profile-metric-label"><?= __e('lawyer.tasks.stat_pending') ?></span>
                            <span class="lawyer-profile-metric-foot"><?= __e('lawyer.kpi.foot_appointments') ?></span>
                        </div>
                    </div>
                    <div class="lawyer-profile-metric is-tone-hearings" role="listitem">
                        <div class="lawyer-profile-metric-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        </div>
                        <div class="lawyer-profile-metric-body">
                            <span class="lawyer-profile-metric-value"><?= (int) $upcomingTotal ?></span>
                            <span class="lawyer-profile-metric-label"><?= __e('lawyer.tasks.stat_upcoming') ?></span>
                            <span class="lawyer-profile-metric-foot"><?= __e('lawyers.profile.schedule_short') ?></span>
                        </div>
                    </div>
                </div>

                <div class="lawyer-profile-workload-block">
                    <div class="lawyer-profile-workload-head">
                        <span><?= __e('lawyers.profile.of_caseload') ?></span>
                        <span><?= (int) $workloadPct ?>%</span>
                    </div>
                    <div class="lawyer-profile-workload" aria-hidden="true">
                        <span style="width: <?= max(0, min(100, $workloadPct)) ?>%;"></span>
                    </div>
                </div>
            </div>
        </header>

        <div class="lawyer-profile-layout">
            <section class="panel lawyer-profile-card lawyer-profile-card--details">
                <div class="lawyer-profile-card-head">
                    <div>
                        <h2><?= __e('lawyers.profile.details') ?></h2>
                        <p class="muted"><?= __e('lawyers.profile.details_help') ?></p>
                    </div>
                    <div class="lawyer-profile-contact-actions">
                        <a class="btn btn-row-open btn-sm btn-row-fit" href="mailto:<?= e($lawyer['email']) ?>"><?= __e('common.email') ?></a>
                        <?php if ($lawyer['phone']): ?>
                        <a class="btn btn-row-open btn-sm btn-row-fit" href="tel:<?= e(preg_replace('/\s+/', '', (string) $lawyer['phone'])) ?>"><?= __e('common.phone') ?></a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="lawyer-profile-spec-groups">
                    <div class="lawyer-profile-spec-group">
                        <h3><?= __e('lawyers.profile.contact_group') ?></h3>
                        <dl class="lawyer-profile-spec-list">
                            <div class="lawyer-profile-spec-item">
                                <dt><?= __e('common.email') ?></dt>
                                <dd><a href="mailto:<?= e($lawyer['email']) ?>"><?= e($lawyer['email']) ?></a></dd>
                            </div>
                            <div class="lawyer-profile-spec-item">
                                <dt><?= __e('common.phone') ?></dt>
                                <dd><?= e($lawyer['phone'] ?: __('common.em_dash')) ?></dd>
                            </div>
                            <div class="lawyer-profile-spec-item">
                                <dt><?= __e('common.address') ?></dt>
                                <dd><?= e($lawyer['address'] ?: __('common.em_dash')) ?></dd>
                            </div>
                        </dl>
                    </div>
                    <div class="lawyer-profile-spec-group">
                        <h3><?= __e('lawyers.profile.credentials_group') ?></h3>
                        <dl class="lawyer-profile-spec-list">
                            <div class="lawyer-profile-spec-item">
                                <dt><?= __e('form.username') ?></dt>
                                <dd><?= e($lawyer['username']) ?></dd>
                            </div>
                            <div class="lawyer-profile-spec-item">
                                <dt><?= __e('form.specialization') ?></dt>
                                <dd><?= e($lawyer['specialization'] ?: __('lawyers.general_practice')) ?></dd>
                            </div>
                            <div class="lawyer-profile-spec-item">
                                <dt><?= __e('form.bar_number') ?></dt>
                                <dd><?= e($lawyer['bar_number'] ?: __('lawyers.no_bar')) ?></dd>
                            </div>
                            <div class="lawyer-profile-spec-item">
                                <dt><?= __e('form.availability') ?></dt>
                                <dd><?= status_badge($lawyer['availability']) ?></dd>
                            </div>
                            <div class="lawyer-profile-spec-item">
                                <dt><?= __e('lawyers.workload_open') ?></dt>
                                <dd><?= (int) $openCases ?> / <?= count($cases) ?> <span class="muted">(<?= (int) $workloadPct ?>%)</span></dd>
                            </div>
                        </dl>
                    </div>
                </div>
                <?php if (trim((string) ($lawyer['notes'] ?? '')) !== ''): ?>
                <div class="lawyer-profile-notes">
                    <h3><?= __e('common.notes') ?></h3>
                    <p><?= nl2br(e($lawyer['notes'])) ?></p>
                </div>
                <?php endif; ?>
            </section>

            <section class="panel lawyer-profile-card lawyer-profile-card--schedule">
                <div class="lawyer-profile-card-head">
                    <div>
                        <h2><?= __e('dashboard.panel.upcoming_schedule') ?></h2>
                        <p class="muted"><?= __e('lawyers.profile.schedule_help') ?></p>
                    </div>
                    <a class="lawyer-profile-link" href="lawyer-availability.php?lawyer_id=<?= (int) $id ?>"><?= __e('common.view_all') ?></a>
                </div>
                <?php if (!$scheduleItems): ?>
                <div class="lawyer-profile-empty lawyer-profile-empty--schedule">
                    <p class="muted"><?= __e('lawyers.profile.no_schedule') ?></p>
                </div>
                <?php else: ?>
                <div class="tasks-feed lawyer-profile-feed">
                    <?php foreach ($scheduleItems as $item): ?>
                        <?php if ($item['kind'] === 'hearing'):
                            $h = $item['row']; ?>
                    <article class="tasks-feed-item">
                        <div class="tasks-feed-mark is-court" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M6 18V8l6-4 6 4v10M9 18v-4h6v4"/></svg>
                        </div>
                        <div class="tasks-feed-body">
                            <strong><?= e($h['case_number']) ?></strong>
                            <span class="muted"><?= e(format_datetime($h['hearing_date'])) ?> · <?= e(t_content($h['court_name'] ?: __('common.court'))) ?></span>
                        </div>
                    </article>
                        <?php else:
                            $a = $item['row']; ?>
                    <article class="tasks-feed-item">
                        <div class="tasks-feed-mark" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        </div>
                        <div class="tasks-feed-body">
                            <strong><?= e(t_content($a['title'])) ?></strong>
                            <span class="muted"><?= e(format_datetime($a['scheduled_at'])) ?> · <?= e($a['client_name'] ?: __('common.client')) ?></span>
                        </div>
                    </article>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
        </div>

        <section class="panel lawyer-profile-card lawyer-profile-card--cases">
            <div class="lawyer-profile-card-head">
                <div>
                    <h2><?= __e('lawyers.case_list') ?></h2>
                    <p class="muted"><?= e(__('lawyers.profile.cases_help', ['count' => count($cases)])) ?></p>
                </div>
                <a class="lawyer-profile-link" href="cases.php"><?= __e('common.view_all') ?></a>
            </div>
            <div class="lawyer-profile-assign">
                <label class="lawyer-profile-assign-label" for="assign_case_id"><?= __e('lawyers.assign_case') ?></label>
                <?php if (!$unassigned): ?>
                <p class="muted lawyer-profile-assign-hint"><?= __e('lawyers.profile.no_unassigned') ?></p>
                <?php else: ?>
                <form method="post" class="lawyer-profile-assign-form inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="assign_case">
                    <input type="hidden" name="lawyer_id" value="<?= $id ?>">
                    <select id="assign_case_id" name="case_id" required>
                        <option value=""><?= __e('form.select_case') ?></option>
                        <?php foreach ($unassigned as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= e($c['case_number'] . ' — ' . $c['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-row-open btn-sm btn-row-fit" type="submit"><?= __e('lawyers.assign') ?></button>
                </form>
                <?php endif; ?>
            </div>
            <?php if (!$cases): ?>
            <div class="lawyer-profile-empty"><p class="muted"><?= __e('cases.empty.no_active') ?></p></div>
            <?php else: ?>
            <div class="table-wrap case-table-wrap lawyer-profile-table-wrap">
                <table class="case-table">
                    <thead>
                        <tr>
                            <th><?= __e('common.case') ?></th>
                            <th><?= __e('common.client') ?></th>
                            <th><?= __e('common.status') ?></th>
                            <th><?= __e('common.priority') ?></th>
                            <th><?= __e('common.last_updated') ?></th>
                            <th class="col-actions"><?= __e('common.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cases as $c): ?>
                        <tr>
                            <td>
                                <a class="lawyer-profile-case-link" href="cases.php?action=view&id=<?= (int) $c['id'] ?>">
                                    <span class="lawyer-profile-case-mark" aria-hidden="true"><?= e(strtoupper(substr((string) $c['case_number'], 0, 1))) ?></span>
                                    <span class="lawyer-profile-case-text">
                                        <strong><?= e($c['case_number']) ?></strong>
                                        <span class="muted"><?= e($c['title']) ?></span>
                                    </span>
                                </a>
                            </td>
                            <td><?= e($c['client_name']) ?></td>
                            <td><?= status_badge($c['status']) ?></td>
                            <td><?= status_badge($c['priority']) ?></td>
                            <td><?= e(format_date($c['updated_at'])) ?></td>
                            <td class="col-actions">
                                <a class="btn btn-row-open btn-sm btn-row-fit" href="cases.php?action=view&id=<?= (int) $c['id'] ?>"><?= __e('common.open') ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
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
    <div class="case-list-foot">
        <p class="case-list-footer muted"><?= e(__($totalLawyers === 1 ? 'lawyers.pager.showing_one' : 'lawyers.pager.showing_many', ['from' => (int)$shownFrom, 'to' => (int)$shownTo, 'total' => (int)$totalLawyers])) ?></p>
        <?php if ($totalPages > 1): ?>
        <nav class="case-list-pager" aria-label="<?= __e('lawyers.pagination.aria') ?>">
            <?php if ($page > 1): ?>
            <a class="case-page-btn" href="?page=<?= $page - 1 ?>" aria-label="<?= __e('cases.pagination.prev') ?>">‹</a>
            <?php else: ?>
            <span class="case-page-btn is-disabled" aria-disabled="true">‹</span>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a class="case-page-btn<?= $p === $page ? ' is-active' : '' ?>" href="?page=<?= $p ?>"<?= $p === $page ? ' aria-current="page"' : '' ?>><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a class="case-page-btn" href="?page=<?= $page + 1 ?>" aria-label="<?= __e('cases.pagination.next') ?>">›</a>
            <?php else: ?>
            <span class="case-page-btn is-disabled" aria-disabled="true">›</span>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
