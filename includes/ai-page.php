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
        $stmt->execute([$user['id'], $portal, 'New Chat']);
        redirect(app_config('url') . "/{$portal}/ai.php?session=" . $pdo->lastInsertId());
    }

    $sessions = $pdo->prepare('SELECT * FROM ai_chat_sessions WHERE user_id = ? AND portal = ? ORDER BY updated_at DESC');
    $sessions->execute([$user['id'], $portal]);
    $sessions = $sessions->fetchAll();

    $sessionId = (int) (get('session') ?: ($sessions[0]['id'] ?? 0));
    if (!$sessionId) {
        $stmt = $pdo->prepare('INSERT INTO ai_chat_sessions (user_id, portal, title) VALUES (?, ?, ?)');
        $stmt->execute([$user['id'], $portal, 'New Chat']);
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
            ['Client count', 'How many clients do we have?', 'user'],
            ['Active cases', 'How many active cases are there?', 'briefcase'],
            ['Total revenue', 'What is our total revenue in rupees?', 'money'],
            ['Appointments', 'Show upcoming appointments', 'calendar'],
            ['Recent payments', 'Summarize recent payments', 'doc'],
            ['Overdue invoices', 'Which invoices are overdue or outstanding?', 'alert'],
            ['Notifications', 'Summarize my latest notifications', 'bell'],
            ['Revenue by month', 'Break down revenue by month', 'chart'],
            ['Dashboard overview', 'Give me a firm dashboard overview', 'grid'],
            ['New case draft', 'Help me draft a new case summary', 'edit'],
            ['Lawyer workload', 'Summarize lawyer workload and availability', 'users'],
            ['Court schedule', 'What hearings are coming up?', 'court'],
        ],
        'lawyer' => [
            ['My cases', 'Summarize my assigned cases', 'briefcase'],
            ["Today's appointments", 'What appointments do I have today?', 'calendar'],
            ['Upcoming hearings', 'List my upcoming court hearings', 'court'],
            ['Pending tasks', 'What tasks or pending appointments need my response?', 'tasks'],
            ['My clients', 'List clients I am working with', 'user'],
            ['Notifications', 'Summarize my latest notifications', 'bell'],
            ['Draft letter', 'Help me draft a legal letter for an assigned case', 'edit'],
            ['Case timeline', 'Build a timeline for my most recent case', 'chart'],
            ['Document Q&A', 'How can I ask questions about an uploaded document?', 'doc'],
        ],
        default => [
            ['My cases', 'Summarize my cases in plain language', 'briefcase'],
            ['Documents', 'Explain my recent documents simply', 'doc'],
            ['Appointments', 'What appointments do I have coming up?', 'calendar'],
            ['Outstanding balance', 'What is my outstanding balance in rupees?', 'money'],
            ['Notifications', 'Summarize my latest notifications', 'bell'],
            ['Invoice help', 'Explain my latest invoice simply', 'doc'],
            ['Court dates', 'What court dates are scheduled for my cases?', 'court'],
            ['Checklist', 'Give me a document checklist for my open case', 'tasks'],
        ],
    };

    $welcome = match ($portal) {
        'admin' => "Welcome to the {$company} AI Assistant! From this unified space, you can instantly view dashboard metrics, search clients or cases, scan documents for quick Q&As, handle client intake, schedule appointments, and draft messages or reminders — all in rupees (₹) where money is involved.",
        'lawyer' => "Welcome to the {$company} AI Assistant! Ask about your assigned cases, hearings, appointments, clients, and documents. I can draft letters, summarize files, and help you prepare for court.",
        default => "Welcome to the {$company} AI Assistant! I can explain your own cases, documents, appointments, and invoices in plain language. I never share other clients' information.",
    };

    $subtitle = match ($portal) {
        'admin' => 'Operations, search, intake & compliance.',
        'lawyer' => 'Casework, drafting & hearing prep.',
        default => 'Your matters explained simply.',
    };

    $placeholder = match ($portal) {
        'admin' => "Ask about clients, cases, fees, or say 'create a case for…'",
        'lawyer' => 'Ask about your cases, hearings, or drafting help…',
        default => 'Ask about your cases, documents, or invoices…',
    };

    $pageTitle = 'AI Assistant';
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
                <h2>AI Assistant</h2>
                <div class="ai-toolbar-actions">
                    <button class="btn btn-outline-brand btn-sm" type="button" id="ai-library-toggle">
                        <span class="ai-btn-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 5h7v14H4zM13 5h7v14h-7z"/></svg>
                        </span>
                        Library
                    </button>
                    <a class="btn btn-outline-brand btn-sm" href="?new=1">+ New chat</a>
                </div>
            </div>

            <div class="ai-library" id="ai-library" hidden>
                <div class="ai-library-head">
                    <strong>Chat library</strong>
                    <button type="button" class="ai-library-close" id="ai-library-close" aria-label="Close library">×</button>
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
                        <button type="button" class="ai-attach" title="Attach files" aria-label="Attach files">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M21 12.5V8a5 5 0 0 0-10 0v9a3 3 0 0 0 6 0V9"/></svg>
                        </button>
                        <input type="text" id="ai-message" placeholder="<?= e($placeholder) ?>" required autocomplete="off">
                        <button class="ai-send" type="submit" aria-label="Send">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12l15-7-4 15-4-5-7-3z"/></svg>
                        </button>
                    </div>
                    <p class="ai-tip muted">Tip: Attach one or more PDFs, screenshots, or photos.</p>
                </form>
            </div>
        </section>

        <aside class="ai-prompts">
            <div class="ai-prompts-head">
                <h3>Quick prompts</h3>
                <p class="muted">One-click starters.</p>
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
                <button type="button" class="ai-pager-btn" id="ai-prompt-prev" aria-label="Previous">‹</button>
                <span id="ai-prompt-page-label">1 / 1</span>
                <button type="button" class="ai-pager-btn" id="ai-prompt-next" aria-label="Next">›</button>
            </div>
        </aside>
    </div>
    <?php
    require __DIR__ . '/footer.php';
}
