<?php
/**
 * Shared AI page bootstrap for all portals
 */
function ai_welcome_text(string $portal, string $company): string
{
    return match ($portal) {
        'admin' => __('ai.welcome_admin', ['company' => $company]),
        'lawyer' => __('ai.welcome_lawyer', ['company' => $company]),
        default => __('ai.welcome_client', ['company' => $company]),
    };
}

function ai_create_session_with_welcome(PDO $pdo, int $userId, string $portal, string $company): int
{
    $stmt = $pdo->prepare('INSERT INTO ai_chat_sessions (user_id, portal, title) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $portal, __('ai.new_chat')]);
    $sessionId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO ai_chat_messages (session_id, role, content) VALUES (?, ?, ?)')
        ->execute([$sessionId, 'assistant', ai_welcome_text($portal, $company)]);
    return $sessionId;
}

function render_ai_page(string $portal): void
{
    require_role($portal === 'admin' ? ['admin', 'staff'] : [$portal]);
    $pdo = db();
    $user = current_user();
    $company = get_setting($pdo, 'company_name', app_config('name'));
    $aiBase = app_config('url') . "/{$portal}/ai.php";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $fa = post('form_action');
        $sid = (int) post('session_id');
        $own = $pdo->prepare('SELECT id FROM ai_chat_sessions WHERE id = ? AND user_id = ? AND portal = ?');
        $own->execute([$sid, $user['id'], $portal]);
        if (!$own->fetch()) {
            flash('error', __('ai.session_not_found'));
            redirect($aiBase);
        }

        if ($fa === 'rename_session') {
            $title = trim((string) post('title'));
            if ($title === '') {
                $title = __('ai.new_chat');
            }
            $pdo->prepare('UPDATE ai_chat_sessions SET title = ?, updated_at = NOW() WHERE id = ? AND user_id = ?')
                ->execute([function_exists('mb_substr') ? mb_substr($title, 0, 120) : substr($title, 0, 120), $sid, $user['id']]);
            flash('success', __('ai.session_renamed'));
            redirect($aiBase . '?session=' . $sid . '&library=1');
        }

        if ($fa === 'delete_session') {
            $pdo->prepare('DELETE FROM ai_chat_sessions WHERE id = ? AND user_id = ?')->execute([$sid, $user['id']]);
            $next = $pdo->prepare('SELECT id FROM ai_chat_sessions WHERE user_id = ? AND portal = ? ORDER BY updated_at DESC LIMIT 1');
            $next->execute([$user['id'], $portal]);
            $nextId = (int) ($next->fetchColumn() ?: 0);
            flash('success', __('ai.session_deleted'));
            if ($nextId > 0) {
                redirect($aiBase . '?session=' . $nextId . '&library=1');
            }
            $newId = ai_create_session_with_welcome($pdo, (int) $user['id'], $portal, $company);
            redirect($aiBase . '?session=' . $newId . '&library=1');
        }

        if ($fa === 'clear_chat') {
            $pdo->prepare('DELETE FROM ai_chat_messages WHERE session_id = ?')->execute([$sid]);
            $pdo->prepare('INSERT INTO ai_chat_messages (session_id, role, content) VALUES (?, ?, ?)')
                ->execute([$sid, 'assistant', ai_welcome_text($portal, $company)]);
            $pdo->prepare('UPDATE ai_chat_sessions SET title = ?, updated_at = NOW() WHERE id = ? AND user_id = ?')
                ->execute([__('ai.new_chat'), $sid, $user['id']]);
            if (isset($_SESSION['ai_client_draft'][$sid])) {
                unset($_SESSION['ai_client_draft'][$sid]);
            }
            flash('success', __('ai.chat_cleared'));
            $qs = ['session' => $sid];
            if (isset($_GET['library']) || post('library') === '1') {
                $qs['library'] = '1';
            }
            redirect($aiBase . '?' . http_build_query($qs));
        }
    }

    if (isset($_GET['new'])) {
        $newId = ai_create_session_with_welcome($pdo, (int) $user['id'], $portal, $company);
        $qs = ['session' => $newId];
        if (isset($_GET['library'])) {
            $qs['library'] = '1';
        }
        redirect($aiBase . '?' . http_build_query($qs));
    }

    $sessions = $pdo->prepare(
        'SELECT s.*,
            (
                SELECT m.content
                FROM ai_chat_messages m
                WHERE m.session_id = s.id
                ORDER BY m.id DESC
                LIMIT 1
            ) AS preview
         FROM ai_chat_sessions s
         WHERE s.user_id = ? AND s.portal = ?
         ORDER BY s.updated_at DESC'
    );
    $sessions->execute([$user['id'], $portal]);
    $sessions = $sessions->fetchAll();

    $sessionId = (int) (get('session') ?: ($sessions[0]['id'] ?? 0));
    if (!$sessionId) {
        $sessionId = ai_create_session_with_welcome($pdo, (int) $user['id'], $portal, $company);
        $sessions = $pdo->prepare(
            'SELECT s.*,
                (
                    SELECT m.content
                    FROM ai_chat_messages m
                    WHERE m.session_id = s.id
                    ORDER BY m.id DESC
                    LIMIT 1
                ) AS preview
             FROM ai_chat_sessions s
             WHERE s.user_id = ? AND s.portal = ?
             ORDER BY s.updated_at DESC'
        );
        $sessions->execute([$user['id'], $portal]);
        $sessions = $sessions->fetchAll();
    }

    $libraryOpen = get('library', '') === '1';

    $msgs = $pdo->prepare('SELECT * FROM ai_chat_messages WHERE session_id = ? ORDER BY id ASC');
    $msgs->execute([$sessionId]);
    $messages = $msgs->fetchAll();

    $prompts = match ($portal) {
        'admin' => [
            [__('ai.prompt.client_count'), __('ai.prompt.client_count_body'), 'user'],
            [__('ai.prompt.active_cases'), __('ai.prompt.active_cases_body'), 'briefcase'],
            [__('ai.prompt.create_client'), __('ai.prompt.create_client_body'), 'user'],
            [__('ai.prompt.create_case'), __('ai.prompt.create_case_body'), 'briefcase'],
            [__('ai.prompt.schedule_appt'), __('ai.prompt.schedule_appt_body'), 'calendar'],
            [__('ai.prompt.delete_appt'), __('ai.prompt.delete_appt_body'), 'alert'],
            [__('ai.prompt.draft_email'), __('ai.prompt.draft_email_body'), 'edit'],
            [__('ai.prompt.upload_doc'), __('ai.prompt.upload_doc_body'), 'doc'],
            [__('ai.prompt.total_revenue'), __('ai.prompt.total_revenue_body'), 'money'],
            [__('ai.prompt.calculate'), __('ai.prompt.calculate_body'), 'calc'],
            [__('ai.prompt.appointments'), __('ai.prompt.appointments_body'), 'calendar'],
            [__('ai.prompt.recent_payments'), __('ai.prompt.recent_payments_body'), 'doc'],
            [__('ai.prompt.overdue_invoices'), __('ai.prompt.overdue_invoices_body'), 'alert'],
            [__('ai.prompt.notifications'), __('ai.prompt.notifications_body'), 'bell'],
            [__('ai.prompt.dashboard_overview'), __('ai.prompt.dashboard_overview_body'), 'grid'],
            [__('ai.prompt.mauritius_laws'), __('ai.prompt.mauritius_laws_body'), 'doc'],
        ],
        'lawyer' => [
            [__('ai.prompt.my_cases'), __('ai.prompt.my_cases_body'), 'briefcase'],
            [__('ai.prompt.schedule_appt'), __('ai.prompt.schedule_appt_lawyer_body'), 'calendar'],
            [__('ai.prompt.delete_appt'), __('ai.prompt.delete_appt_body'), 'alert'],
            [__('ai.prompt.cancel_appt'), __('ai.prompt.cancel_appt_body'), 'alert'],
            [__('ai.prompt.draft_email'), __('ai.prompt.draft_email_body'), 'edit'],
            [__('ai.prompt.upload_doc'), __('ai.prompt.upload_doc_body'), 'doc'],
            [__('ai.prompt.todays_appointments'), __('ai.prompt.todays_appointments_body'), 'calendar'],
            [__('ai.prompt.upcoming_hearings'), __('ai.prompt.upcoming_hearings_body'), 'court'],
            [__('ai.prompt.calculate'), __('ai.prompt.calculate_body'), 'calc'],
            [__('ai.prompt.my_clients'), __('ai.prompt.my_clients_body'), 'user'],
            [__('ai.prompt.notifications'), __('ai.prompt.notifications_body'), 'bell'],
            [__('ai.prompt.case_timeline'), __('ai.prompt.case_timeline_body'), 'chart'],
            [__('ai.prompt.mauritius_laws'), __('ai.prompt.mauritius_laws_body'), 'court'],
        ],
        default => [
            [__('ai.prompt.my_cases'), __('ai.prompt.my_cases_client_body'), 'briefcase'],
            [__('ai.prompt.schedule_appt'), __('ai.prompt.schedule_appt_client_body'), 'calendar'],
            [__('ai.prompt.cancel_appt'), __('ai.prompt.cancel_appt_body'), 'alert'],
            [__('ai.prompt.delete_appt'), __('ai.prompt.delete_appt_body'), 'alert'],
            [__('ai.prompt.upload_doc'), __('ai.prompt.upload_doc_client_body'), 'doc'],
            [__('ai.prompt.draft_email'), __('ai.prompt.draft_email_client_body'), 'edit'],
            [__('ai.prompt.documents'), __('ai.prompt.documents_body'), 'doc'],
            [__('ai.prompt.appointments'), __('ai.prompt.appointments_client_body'), 'calendar'],
            [__('ai.prompt.outstanding_balance'), __('ai.prompt.outstanding_balance_body'), 'money'],
            [__('ai.prompt.notifications'), __('ai.prompt.notifications_body'), 'bell'],
            [__('ai.prompt.mauritius_laws'), __('ai.prompt.mauritius_laws_body'), 'court'],
        ],
    };

    $welcome = ai_welcome_text($portal, $company);

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
            'calc' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 11h2M12 11h2M16 11h2M8 15h2M12 15h2M16 15h2M8 19h2M12 19h2M16 19h2"/></svg>',
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
    <div class="ai-workspace<?= $libraryOpen ? ' is-library-open' : '' ?>" id="ai-workspace">
        <script type="application/json" id="ai-prompts-data"><?= json_encode(array_map(static fn($p) => ['label' => $p[0], 'prompt' => $p[1], 'icon' => $p[2]], $prompts), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
        <aside class="ai-library" id="ai-library"<?= $libraryOpen ? '' : ' hidden' ?>>
            <div class="ai-library-head">
                <strong><?= __e('ai.library') ?></strong>
                <button type="button" class="ai-library-close" id="ai-library-close" aria-label="<?= __e('ai.close_library') ?>">×</button>
            </div>
            <div class="ai-library-list" id="ai-library-list">
                <?php if (!$sessions): ?>
                    <div class="ai-library-empty"><?= __e('ai.library_empty') ?></div>
                <?php else: ?>
                    <?php foreach ($sessions as $index => $s):
                        $preview = trim((string) ($s['preview'] ?? ''));
                        if ($preview === '') {
                            $preview = __('ai.library_no_messages');
                        } elseif (function_exists('mb_strlen') && mb_strlen($preview) > 72) {
                            $preview = mb_substr($preview, 0, 72) . '…';
                        } elseif (strlen($preview) > 72) {
                            $preview = substr($preview, 0, 72) . '…';
                        }
                        $dateLabel = format_date($s['updated_at'] ?? null, 'j M');
                        ?>
                        <article class="ai-library-item<?= (int) $s['id'] === $sessionId ? ' is-active' : '' ?>" data-library-page-row data-session-id="<?= (int) $s['id'] ?>"<?= $index >= 8 ? ' hidden' : '' ?>>
                            <a class="ai-library-item-main" href="?session=<?= (int) $s['id'] ?>&library=1">
                                <strong><?= e($s['title'] ?: __('ai.new_chat')) ?></strong>
                                <p><?= e($preview) ?></p>
                                <span class="ai-library-date"><?= e($dateLabel) ?></span>
                            </a>
                            <div class="ai-library-item-actions">
                                <button type="button" class="ai-library-icon-btn" data-rename-session="<?= (int) $s['id'] ?>" data-current-title="<?= e($s['title'] ?: __('ai.new_chat')) ?>" title="<?= __e('common.edit') ?>" aria-label="<?= __e('common.edit') ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                </button>
                                <form method="post" class="ai-library-delete-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="form_action" value="delete_session">
                                    <input type="hidden" name="session_id" value="<?= (int) $s['id'] ?>">
                                    <button type="submit" class="ai-library-icon-btn is-danger" data-confirm="<?= __e('ai.confirm_delete_session') ?>" title="<?= __e('common.delete') ?>" aria-label="<?= __e('common.delete') ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 14h10l1-14"/></svg>
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <form method="post" id="ai-rename-form" class="ai-rename-form" hidden>
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="rename_session">
                <input type="hidden" name="session_id" id="ai-rename-session-id" value="">
                <input type="hidden" name="title" id="ai-rename-title" value="">
            </form>
            <div class="ai-library-pager" id="ai-library-pager"<?= count($sessions) <= 8 ? ' hidden' : '' ?>>
                <button type="button" class="ai-pager-btn" id="ai-library-prev" aria-label="<?= __e('ai.prev') ?>">‹</button>
                <span id="ai-library-page-label">1 / <?= max(1, (int) ceil(count($sessions) / 8)) ?></span>
                <button type="button" class="ai-pager-btn" id="ai-library-next" aria-label="<?= __e('ai.next') ?>">›</button>
            </div>
        </aside>

        <section class="ai-main">
            <div class="ai-toolbar">
                <h2><?= __e('ai.toolbar_title') ?></h2>
                <div class="ai-toolbar-actions">
                    <button class="btn btn-outline-brand btn-sm" type="button" id="ai-library-toggle" aria-expanded="<?= $libraryOpen ? 'true' : 'false' ?>">
                        <span class="ai-btn-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 5h7v14H4zM13 5h7v14h-7z"/></svg>
                        </span>
                        <?= __e('ai.library') ?>
                    </button>
                    <form method="post" class="ai-toolbar-form" data-confirm="<?= __e('ai.confirm_clear_chat') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_action" value="clear_chat">
                        <input type="hidden" name="session_id" value="<?= (int) $sessionId ?>">
                        <?php if ($libraryOpen): ?>
                        <input type="hidden" name="library" value="1">
                        <?php endif; ?>
                        <button class="btn btn-outline-brand btn-sm" type="submit"><?= __e('ai.clear_chat') ?></button>
                    </form>
                    <a class="btn btn-outline-brand btn-sm" href="?new=1<?= $libraryOpen ? '&library=1' : '' ?>"><?= __e('ai.new_chat_btn') ?></a>
                </div>
            </div>

            <div class="ai-chat-panel">
                <div class="ai-messages" id="ai-messages">
                    <?php if (!$messages): ?>
                        <div class="ai-msg-row ai-msg-row--assistant">
                            <div class="ai-bot-mark" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                    <rect x="5" y="7" width="14" height="11" rx="3"/>
                                    <circle cx="9.5" cy="12" r="1.2" fill="currentColor" stroke="none"/>
                                    <circle cx="14.5" cy="12" r="1.2" fill="currentColor" stroke="none"/>
                                    <path d="M9 18v2M15 18v2M12 4v3"/>
                                </svg>
                            </div>
                            <div class="ai-msg-stack">
                                <div class="msg msg-assistant ai-bubble ai-welcome">
                                    <div class="ai-msg-body" data-ai-raw="<?= e($welcome) ?>"><?= e($welcome) ?></div>
                                </div>
                                <div class="ai-msg-actions">
                                    <button type="button" class="ai-msg-action" data-ai-copy title="<?= __e('ai.copy') ?>" aria-label="<?= __e('ai.copy') ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($messages as $m): ?>
                        <?php if ($m['role'] === 'assistant'): ?>
                            <div class="ai-msg-row ai-msg-row--assistant">
                                <div class="ai-bot-mark sm" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                        <rect x="5" y="7" width="14" height="11" rx="3"/>
                                        <circle cx="9.5" cy="12" r="1.2" fill="currentColor" stroke="none"/>
                                        <circle cx="14.5" cy="12" r="1.2" fill="currentColor" stroke="none"/>
                                        <path d="M9 18v2M15 18v2M12 4v3"/>
                                    </svg>
                                </div>
                                <div class="ai-msg-stack">
                                    <div class="msg msg-assistant ai-bubble">
                                        <div class="ai-msg-body" data-ai-raw="<?= e($m['content']) ?>"><?= ai_format_message($m['content']) ?></div>
                                    </div>
                                    <div class="ai-msg-actions">
                                        <button type="button" class="ai-msg-action" data-ai-copy title="<?= __e('ai.copy') ?>" aria-label="<?= __e('ai.copy') ?>">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="ai-msg-row ai-msg-row--user">
                                <div class="ai-msg-stack">
                                    <div class="msg msg-user">
                                        <div class="ai-msg-body" data-ai-raw="<?= e($m['content']) ?>"><?= nl2br(e($m['content'])) ?></div>
                                    </div>
                                    <div class="ai-msg-actions ai-msg-actions--user">
                                        <button type="button" class="ai-msg-action" data-ai-edit title="<?= __e('ai.edit') ?>" aria-label="<?= __e('ai.edit') ?>">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 20h4L18 10l-4-4L4 16v4z"/><path d="M12 8l4 4"/></svg>
                                        </button>
                                        <button type="button" class="ai-msg-action" data-ai-copy title="<?= __e('ai.copy') ?>" aria-label="<?= __e('ai.copy') ?>">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <form id="ai-compose-form" class="ai-compose-wrap" data-no-saving="1" data-session-id="<?= (int) $sessionId ?>" data-ai-endpoint="../api/ai-chat.php" data-app-url="<?= e(rtrim((string) app_config('url'), '/')) ?>">
                    <div id="ai-attach-list" class="ai-attach-list" hidden></div>
                    <div class="ai-compose-bar">
                        <button type="button" class="ai-attach" id="ai-attach-btn" title="<?= __e('ai.attach_files') ?>" aria-label="<?= __e('ai.attach_files') ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M21 12.5V8a5 5 0 0 0-10 0v9a3 3 0 0 0 6 0V9"/></svg>
                        </button>
                        <input type="file" id="ai-file-input" class="ai-file-input" multiple accept=".pdf,.doc,.docx,.txt,.csv,.jpg,.jpeg,.png,.webp,.xls,.xlsx" hidden>
                        <input type="text" id="ai-message" placeholder="<?= e($placeholder) ?>" autocomplete="off">
                        <button class="ai-send" type="button" id="ai-send-btn" aria-label="<?= __e('ai.send') ?>">
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
                <?php foreach (array_slice($prompts, 0, 8) as $p): ?>
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
