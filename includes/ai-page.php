<?php
/**
 * Shared AI page bootstrap for all portals
 */
function render_ai_page(string $portal): void
{
    require_role($portal === 'admin' ? ['admin', 'staff'] : [$portal]);
    $pdo = db();
    $user = current_user();
    $company = get_setting($pdo, 'company_name', app_config('name'));

    if (isset($_GET['new'])) {
        $stmt = $pdo->prepare('INSERT INTO ai_chat_sessions (user_id, portal, title) VALUES (?, ?, ?)');
        $stmt->execute([$user['id'], $portal, __('ai.new_chat')]);
        redirect(app_config('url') . "/{$portal}/ai.php?session=" . $pdo->lastInsertId());
    }

    $sessions = $pdo->prepare('SELECT * FROM ai_chat_sessions WHERE user_id = ? AND portal = ? ORDER BY updated_at DESC');
    $sessions->execute([$user['id'], $portal]);
    $sessions = $sessions->fetchAll();

    $sessionId = (int) (get('session') ?: ($sessions[0]['id'] ?? 0));
    if (!$sessionId) {
        $stmt = $pdo->prepare('INSERT INTO ai_chat_sessions (user_id, portal, title) VALUES (?, ?, ?)');
        $stmt->execute([$user['id'], $portal, __('ai.new_chat')]);
        $sessionId = (int) $pdo->lastInsertId();
        $sessions = $pdo->prepare('SELECT * FROM ai_chat_sessions WHERE user_id = ? AND portal = ? ORDER BY updated_at DESC');
        $sessions->execute([$user['id'], $portal]);
        $sessions = $sessions->fetchAll();
    }

    $msgs = $pdo->prepare('SELECT * FROM ai_chat_messages WHERE session_id = ? ORDER BY id ASC');
    $msgs->execute([$sessionId]);
    $messages = $msgs->fetchAll();

    $prompts = match ($portal) {
        'admin' => [
            [__('ai.prompt.client_count'), __('ai.prompt.client_count_body'), 'user'],
            [__('ai.prompt.active_cases'), __('ai.prompt.active_cases_body'), 'briefcase'],
            [__('ai.prompt.total_revenue'), __('ai.prompt.total_revenue_body'), 'money'],
            [__('ai.prompt.appointments'), __('ai.prompt.appointments_body'), 'calendar'],
            [__('ai.prompt.recent_payments'), __('ai.prompt.recent_payments_body'), 'doc'],
            [__('ai.prompt.overdue_invoices'), __('ai.prompt.overdue_invoices_body'), 'alert'],
            [__('ai.prompt.notifications'), __('ai.prompt.notifications_body'), 'bell'],
            [__('ai.prompt.revenue_by_month'), __('ai.prompt.revenue_by_month_body'), 'chart'],
            [__('ai.prompt.dashboard_overview'), __('ai.prompt.dashboard_overview_body'), 'grid'],
            [__('ai.prompt.new_case_draft'), __('ai.prompt.new_case_draft_body'), 'edit'],
            [__('ai.prompt.lawyer_workload'), __('ai.prompt.lawyer_workload_body'), 'users'],
            [__('ai.prompt.court_schedule'), __('ai.prompt.court_schedule_body'), 'court'],
        ],
        'lawyer' => [
            [__('ai.prompt.my_cases'), __('ai.prompt.my_cases_body'), 'briefcase'],
            [__('ai.prompt.todays_appointments'), __('ai.prompt.todays_appointments_body'), 'calendar'],
            [__('ai.prompt.upcoming_hearings'), __('ai.prompt.upcoming_hearings_body'), 'court'],
            [__('ai.prompt.pending_tasks'), __('ai.prompt.pending_tasks_body'), 'tasks'],
            [__('ai.prompt.my_clients'), __('ai.prompt.my_clients_body'), 'user'],
            [__('ai.prompt.notifications'), __('ai.prompt.notifications_body'), 'bell'],
            [__('ai.prompt.draft_letter'), __('ai.prompt.draft_letter_body'), 'edit'],
            [__('ai.prompt.case_timeline'), __('ai.prompt.case_timeline_body'), 'chart'],
            [__('ai.prompt.document_qa'), __('ai.prompt.document_qa_body'), 'doc'],
        ],
        default => [
            [__('ai.prompt.my_cases'), __('ai.prompt.my_cases_client_body'), 'briefcase'],
            [__('ai.prompt.documents'), __('ai.prompt.documents_body'), 'doc'],
            [__('ai.prompt.appointments'), __('ai.prompt.appointments_client_body'), 'calendar'],
            [__('ai.prompt.outstanding_balance'), __('ai.prompt.outstanding_balance_body'), 'money'],
            [__('ai.prompt.notifications'), __('ai.prompt.notifications_body'), 'bell'],
            [__('ai.prompt.invoice_help'), __('ai.prompt.invoice_help_body'), 'doc'],
            [__('ai.prompt.court_dates'), __('ai.prompt.court_dates_body'), 'court'],
            [__('ai.prompt.checklist'), __('ai.prompt.checklist_body'), 'tasks'],
        ],
    };

    $welcome = match ($portal) {
        'admin' => __('ai.welcome_admin', ['company' => $company]),
        'lawyer' => __('ai.welcome_lawyer', ['company' => $company]),
        default => __('ai.welcome_client', ['company' => $company]),
    };

    $subtitle = match ($portal) {
        'admin' => __('ai.subtitle.admin'),
        'lawyer' => __('ai.subtitle.lawyer'),
        default => __('ai.subtitle.client'),
    };

    $placeholder = match ($portal) {
        'admin' => __('ai.placeholder.admin'),
        'lawyer' => __('ai.placeholder.lawyer'),
        default => __('ai.placeholder.client'),
    };

    $pageTitle = __('page.ai');
    $pageSubtitle = $subtitle;
    $activeNav = 'ai';
    require __DIR__ . '/header.php';

    $iconSvg = static function (string $name): string {
        return match ($name) {
            'user' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8" r="3.5"/><path d="M5 19.5c1.5-3.2 4-4.5 7-4.5s5.5 1.3 7 4.5"/></svg>',
            'briefcase' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><rect x="3" y="7" width="18" height="14" rx="2"/><path d="M3 13h18"/></svg>',
            'money' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20M12 10v8"/></svg>',
            'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>',
            'doc' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 3h6l5 5v13a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v5h5"/></svg>',
            'alert' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3l9 16H3L12 3z"/><path d="M12 10v4M12 17h.01"/></svg>',
            'bell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M7 9a5 5 0 0 1 10 0c0 5 2 6 2 6H5s2-1 2-6"/><path d="M10 19a2 2 0 0 0 4 0"/></svg>',
            'chart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 19V5M4 19h16"/><path d="M8 15v-4M12 15V8M16 15v-6"/></svg>',
            'grid' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/></svg>',
            'edit' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 20h4L18 10l-4-4L4 16v4z"/><path d="M12 8l4 4"/></svg>',
            'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="8" r="3"/><circle cx="16" cy="9" r="2.5"/><path d="M3 19c1.2-3 3.5-4.5 6-4.5S13.8 16 15 19"/></svg>',
            'court' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 20h16M6 20V10M10 20V10M14 20V10M18 20V10M3 10h18M12 4l9 6H3l9-6z"/></svg>',
            'tasks' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 11l2 2 4-4"/><rect x="4" y="3" width="16" height="18" rx="2"/></svg>',
            default => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="8"/></svg>',
        };
    };
    ?>
    <div class="ai-workspace" id="ai-workspace" data-prompts='<?= e(json_encode(array_map(static fn($p) => ['label' => $p[0], 'prompt' => $p[1], 'icon' => $p[2]], $prompts), JSON_UNESCAPED_UNICODE)) ?>'>
        <section class="ai-main">
            <div class="ai-toolbar">
                <h2><?= __e('ai.toolbar_title') ?></h2>
                <div class="ai-toolbar-actions">
                    <button class="btn btn-outline-brand btn-sm" type="button" id="ai-library-toggle">
                        <span class="ai-btn-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 5h7v14H4zM13 5h7v14h-7z"/></svg>
                        </span>
                        <?= __e('ai.library') ?>
                    </button>
                    <a class="btn btn-outline-brand btn-sm" href="?new=1"><?= __e('ai.new_chat_btn') ?></a>
                </div>
            </div>

            <div class="ai-library" id="ai-library" hidden>
                <div class="ai-library-head">
                    <strong><?= __e('ai.chat_library') ?></strong>
                    <button type="button" class="ai-library-close" id="ai-library-close" aria-label="<?= __e('ai.close_library') ?>">×</button>
                </div>
                <div class="ai-library-list">
                    <?php foreach ($sessions as $s): ?>
                        <a class="ai-session-link <?= (int) $s['id'] === $sessionId ? 'active' : '' ?>" href="?session=<?= (int) $s['id'] ?>">
                            <span><?= e($s['title']) ?></span>
                            <span class="muted"><?= e(format_datetime($s['updated_at'])) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="ai-chat-panel">
                <div class="ai-messages" id="ai-messages">
                    <?php if (!$messages): ?>
                        <div class="ai-welcome msg msg-assistant">
                            <div class="ai-bot-mark" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                    <rect x="5" y="7" width="14" height="11" rx="3"/>
                                    <circle cx="9.5" cy="12" r="1.2" fill="currentColor" stroke="none"/>
                                    <circle cx="14.5" cy="12" r="1.2" fill="currentColor" stroke="none"/>
                                    <path d="M9 18v2M15 18v2M12 4v3"/>
                                </svg>
                            </div>
                            <div><?= e($welcome) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($messages as $m): ?>
                        <?php if ($m['role'] === 'assistant'): ?>
                            <div class="msg msg-assistant ai-bubble">
                                <div class="ai-bot-mark sm" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                        <rect x="5" y="7" width="14" height="11" rx="3"/>
                                        <circle cx="9.5" cy="12" r="1.2" fill="currentColor" stroke="none"/>
                                        <circle cx="14.5" cy="12" r="1.2" fill="currentColor" stroke="none"/>
                                        <path d="M9 18v2M15 18v2M12 4v3"/>
                                    </svg>
                                </div>
                                <div><?= e($m['content']) ?></div>
                            </div>
                        <?php else: ?>
                            <div class="msg msg-user"><?= e($m['content']) ?></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <form id="ai-compose-form" class="ai-compose-wrap" data-session-id="<?= (int) $sessionId ?>">
                    <div class="ai-compose-bar">
                        <button type="button" class="ai-attach" title="<?= __e('ai.attach_files') ?>" aria-label="<?= __e('ai.attach_files') ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M21 12.5V8a5 5 0 0 0-10 0v9a3 3 0 0 0 6 0V9"/></svg>
                        </button>
                        <input type="text" id="ai-message" placeholder="<?= e($placeholder) ?>" required autocomplete="off">
                        <button class="ai-send" type="submit" aria-label="<?= __e('ai.send') ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12l15-7-4 15-4-5-7-3z"/></svg>
                        </button>
                    </div>
                    <p class="ai-tip muted"><?= __e('ai.attach_tip') ?></p>
                </form>
            </div>
        </section>

        <aside class="ai-prompts">
            <div class="ai-prompts-head">
                <h3><?= __e('ai.quick_prompts') ?></h3>
                <p class="muted"><?= __e('ai.quick_prompts_help') ?></p>
            </div>
            <div class="ai-prompt-list" id="ai-prompt-list">
                <?php foreach (array_slice($prompts, 0, 9) as $p): ?>
                    <button type="button" class="ai-prompt-btn" data-prompt="<?= e($p[1]) ?>">
                        <span class="ai-prompt-icon"><?= $iconSvg($p[2]) ?></span>
                        <span class="ai-prompt-label"><?= e($p[0]) ?></span>
                        <span class="ai-prompt-chevron" aria-hidden="true">›</span>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="ai-prompt-pager" id="ai-prompt-pager">
                <button type="button" class="ai-pager-btn" id="ai-prompt-prev" aria-label="<?= __e('ai.prev') ?>">‹</button>
                <span id="ai-prompt-page-label">1 / 1</span>
                <button type="button" class="ai-pager-btn" id="ai-prompt-next" aria-label="<?= __e('ai.next') ?>">›</button>
            </div>
        </aside>
    </div>
    <?php
    require __DIR__ . '/footer.php';
}
