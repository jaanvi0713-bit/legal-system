<?php
/**
 * Shared AI page bootstrap for all portals
 */
function render_ai_page(string $portal): void
{
    require_role($portal === 'admin' ? ['admin', 'staff'] : [$portal]);
    $pdo = db();
    $user = current_user();

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

    $capabilities = match ($portal) {
        'admin' => ['Document summarization', 'Contract drafting', 'Legal letter generation', 'Timeline creation', 'Case recommendations', 'Cross-document search'],
        'lawyer' => ['Summarize case files', 'Draft legal letters', 'Draft contracts', 'Hearing summaries', 'Case timelines', 'Q&A on uploaded documents'],
        default => ['Explain documents simply', 'Summarize uploads', 'Answer questions on your cases', 'Explain contracts & invoices', 'Document checklists'],
    };

    $pageTitle = 'AI Assistant';
    $pageSubtitle = $portal === 'client'
        ? 'Limited to your own cases and documents — never other clients.'
        : 'Advanced legal support for your portal.';
    $activeNav = 'ai';
    require __DIR__ . '/header.php';
    ?>
    <div class="panel">
        <div class="panel-header">
            <div>
                <h2>Capabilities</h2>
                <p class="muted">Chat is available now. Richer document AI activates when an API key is configured.</p>
            </div>
            <a class="btn btn-primary btn-sm" href="?new=1">New chat</a>
        </div>
        <div class="quick-links">
            <?php foreach ($capabilities as $cap): ?>
                <span class="chip"><?= e($cap) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="ai-layout">
        <aside class="ai-sessions">
            <h3>Sessions</h3>
            <?php foreach ($sessions as $s): ?>
                <a class="ai-session-link <?= (int)$s['id'] === $sessionId ? 'active' : '' ?>" href="?session=<?= (int)$s['id'] ?>">
                    <?= e($s['title']) ?>
                    <div class="muted" style="font-size:0.75rem;"><?= e(format_datetime($s['updated_at'])) ?></div>
                </a>
            <?php endforeach; ?>
        </aside>
        <section class="ai-chat">
            <div class="ai-messages" id="ai-messages">
                <?php if (!$messages): ?>
                    <div class="msg msg-assistant">Ask me anything related to your <?= e($portal) ?> workspace. Try “Summarize my current workload”.</div>
                <?php endif; ?>
                <?php foreach ($messages as $m): ?>
                    <div class="msg msg-<?= e($m['role'] === 'user' ? 'user' : 'assistant') ?>"><?= e($m['content']) ?></div>
                <?php endforeach; ?>
            </div>
            <form id="ai-compose-form" class="ai-compose" data-session-id="<?= (int)$sessionId ?>">
                <textarea id="ai-message" placeholder="Type your question…" required></textarea>
                <button class="btn btn-accent" type="submit">Send</button>
            </form>
        </section>
    </div>
    <?php
    require __DIR__ . '/footer.php';
}
