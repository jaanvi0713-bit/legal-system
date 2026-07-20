<?php
/**
 * Alerts-style notification list (WHARF-inspired, system primary colors).
 *
 * Expects:
 * - $notifyBoardItems (array of notification rows)
 * - $notifyBoardTitle (string)
 * - $notifyBoardTotal (int)
 * - $notifyBoardUnread (int)
 * - $notifyBoardPostUrl (string)
 * - Optional: $notifyBoardMode ('inbox'|'history'), $notifyBoardPreferencesUrl,
 *   $notifyBoardShowMarkAll, $notifyBoardAllowDeleteAny, $notifyBoardId,
 *   $notifyBoardPagerPage, $notifyBoardPagerTotalPages, $notifyBoardPagerShownFrom,
 *   $notifyBoardPagerShownTo, $notifyBoardAllowEdit, $notifyBoardReturnPage
 */
$notifyBoardItems = $notifyBoardItems ?? [];
$notifyBoardTitle = $notifyBoardTitle ?? __('notifications.tab.history');
$notifyBoardTotal = (int) ($notifyBoardTotal ?? count($notifyBoardItems));
$notifyBoardUnread = (int) ($notifyBoardUnread ?? 0);
$notifyBoardPostUrl = $notifyBoardPostUrl ?? 'notifications.php';
$notifyBoardMode = $notifyBoardMode ?? 'inbox';
$notifyBoardPreferencesUrl = $notifyBoardPreferencesUrl ?? null;
$notifyBoardShowMarkAll = !isset($notifyBoardShowMarkAll) || $notifyBoardShowMarkAll;
$notifyBoardAllowDeleteAny = !empty($notifyBoardAllowDeleteAny);
$notifyBoardAllowEdit = !empty($notifyBoardAllowEdit ?? $notifyBoardAllowDeleteAny);
$notifyBoardReturnPage = (int) ($notifyBoardReturnPage ?? 0);
$notifyBoardId = $notifyBoardId ?? 'notifyBoard';
$notifyBoardActionUnread = (int) ($notifyBoardActionUnread ?? $notifyBoardUnread);
$notifyBoardUserId = (int) ($notifyBoardUserId ?? (current_user()['id'] ?? 0));
$notifyBoardPagerPage = isset($notifyBoardPagerPage) ? (int) $notifyBoardPagerPage : 0;
$notifyBoardPagerTotalPages = (int) ($notifyBoardPagerTotalPages ?? 1);
$notifyBoardPagerShownFrom = (int) ($notifyBoardPagerShownFrom ?? 0);
$notifyBoardPagerShownTo = (int) ($notifyBoardPagerShownTo ?? 0);
$notifyBoardPagerEnabled = $notifyBoardPagerPage > 0;
$base = app_config('url');
$portalBase = $base . '/' . ($portal ?? 'admin');
$summaryKey = $notifyBoardUnread === 1
    ? 'notifications.board.summary_one'
    : 'notifications.board.summary_many';
?>
<section class="notify-board panel" id="<?= e($notifyBoardId) ?>">
    <div class="notify-board-banner">
        <div class="notify-board-banner-copy">
            <h2><?= e($notifyBoardTitle) ?></h2>
            <p><?= e(__($summaryKey, ['total' => $notifyBoardTotal, 'unread' => $notifyBoardUnread])) ?></p>
        </div>
        <div class="notify-board-banner-actions">
            <?php if ($notifyBoardPreferencesUrl): ?>
            <a class="notify-board-action" href="<?= e($notifyBoardPreferencesUrl) ?>">
                <span aria-hidden="true">⚙</span>
                <?= __e('notifications.preferences') ?>
            </a>
            <?php endif; ?>
            <?php if ($notifyBoardShowMarkAll && $notifyBoardActionUnread > 0): ?>
            <form method="post" action="<?= e($notifyBoardPostUrl) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="read_all">
                <button class="notify-board-action" type="submit">
                    <span aria-hidden="true">✓</span>
                    <?= __e('common.mark_all_read') ?>
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="appt-list-toolbar notify-board-toolbar">
        <label class="appt-list-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input type="search" id="<?= e($notifyBoardId) ?>Search" placeholder="<?= __e('notifications.search_ph') ?>" autocomplete="off">
        </label>
        <select id="<?= e($notifyBoardId) ?>Filter" aria-label="<?= __e('notifications.filter_status') ?>">
            <option value=""><?= __e('notifications.filter.all') ?></option>
            <option value="unread"><?= __e('notifications.filter.unread') ?></option>
            <option value="read"><?= __e('notifications.filter.read') ?></option>
        </select>
    </div>

    <div class="notify-board-list" id="<?= e($notifyBoardId) ?>List">
        <?php foreach ($notifyBoardItems as $n):
            $type = $n['type'] ?: 'info';
            $isUnread = !(int) ($n['is_read'] ?? 0);
            $openUrl = notification_open_url($n['link'] ?? null, $portalBase, $base, $portalBase . '/notifications.php');
            if ($notifyBoardMode === 'inbox') {
                $openUrl = $portalBase . '/notifications.php?action=open&id=' . (int) $n['id'];
            } elseif ($notifyBoardMode === 'history' && (int) ($n['user_id'] ?? 0) === $notifyBoardUserId) {
                $openUrl = $portalBase . '/notifications.php?action=open&id=' . (int) $n['id'];
            } elseif (!empty($n['link'])) {
                $openUrl = notification_open_url($n['link'], $portalBase, $base, $portalBase . '/notifications.php');
            } else {
                $openUrl = '';
            }
            $searchBlob = strtolower(trim(implode(' ', [
                t_stored($n['title'] ?? ''),
                t_stored($n['message'] ?? ''),
                $n['recipient'] ?? '',
                $type,
            ])));
            $filterTokens = $isUnread ? 'unread' : 'read';
            $notifyCopyText = trim(t_stored($n['title'] ?? '') . "\n\n" . t_stored($n['message'] ?? ''));
        ?>
        <article
            class="notify-board-item<?= $isUnread ? ' is-unread' : ' is-read' ?>"
            data-notify-filter="<?= e($filterTokens) ?>"
            data-notify-search="<?= e($searchBlob) ?>"
        >
            <div class="notify-board-item-leading">
                <div class="notify-type-icon-wrap">
                    <?php if ($isUnread): ?><span class="notify-type-icon-badge" aria-hidden="true"></span><?php endif; ?>
                    <div class="notify-type-icon" aria-hidden="true"><?= notification_type_icon_svg($type) ?></div>
                </div>
            </div>
            <div class="notify-board-body">
                <strong><?= e(t_stored($n['title'])) ?></strong>
                <p class="notify-board-message"><?= e(t_stored($n['message'])) ?></p>
                <?php if (!empty($n['edited_at'])): ?>
                    <span class="notify-board-edited"><?= __e('notifications.message_edited') ?></span>
                <?php endif; ?>
                <?php if ($notifyBoardMode === 'history' && !empty($n['recipient'])): ?>
                    <span class="notify-board-recipient"><?= __e('notifications.sent_to') ?> <?= e($n['recipient']) ?></span>
                <?php endif; ?>
                <time class="notify-board-time"><?= e(format_time_ago($n['created_at'] ?? null)) ?></time>
            </div>
            <div class="notify-board-actions row-actions<?= $notifyBoardAllowEdit ? ' notify-board-actions--with-edit' : '' ?>">
                <?php if ($openUrl !== ''): ?>
                <a class="btn btn-row-open btn-sm" href="<?= e($openUrl) ?>"><?= __e('common.open') ?></a>
                <?php endif; ?>
                <?php if ($notifyBoardAllowEdit): ?>
                <button type="button" class="btn btn-row-open btn-sm notify-board-copy-btn" data-copy-text="<?= e($notifyCopyText) ?>"><?= __e('common.copy') ?></button>
                <button type="button" class="btn btn-row-edit btn-sm notify-board-edit-toggle" aria-expanded="false"><?= __e('common.edit') ?></button>
                <?php endif; ?>
                <form method="post" action="<?= e($notifyBoardPostUrl) ?>" data-confirm="<?= __e('confirm.delete_notification') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                    <?php if ($notifyBoardReturnPage > 1): ?>
                    <input type="hidden" name="return_page" value="<?= (int) $notifyBoardReturnPage ?>">
                    <?php endif; ?>
                    <button class="btn btn-row-delete btn-sm" type="submit"><?= __e('common.delete') ?></button>
                </form>
            </div>
            <?php if ($notifyBoardAllowEdit): ?>
            <div class="notify-board-edit" hidden>
                <form method="post" action="<?= e($notifyBoardPostUrl) ?>" class="notify-board-edit-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="edit">
                    <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                    <?php if ($notifyBoardReturnPage > 1): ?>
                    <input type="hidden" name="return_page" value="<?= (int) $notifyBoardReturnPage ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label><?= __e('notifications.title_field') ?></label>
                        <input name="title" required value="<?= e(t_stored($n['title'])) ?>">
                    </div>
                    <div class="form-group">
                        <label><?= __e('common.message') ?></label>
                        <textarea name="message" required rows="4"><?= e(t_stored($n['message'])) ?></textarea>
                    </div>
                    <div class="notify-board-edit-actions">
                        <button class="btn btn-primary btn-sm" type="submit"><?= __e('common.save') ?></button>
                        <button class="btn btn-secondary btn-sm notify-board-edit-cancel" type="button"><?= __e('common.cancel') ?></button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
        <?php if (!$notifyBoardItems): ?>
            <div class="notify-board-empty"><?= __e('common.no_notifications') ?></div>
        <?php endif; ?>
    </div>
    <?php if ($notifyBoardPagerEnabled): ?>
    <div class="case-list-foot notify-board-pager-foot">
        <p class="case-list-footer muted"><?= e(__($notifyBoardTotal === 1 ? 'notifications.pager.showing_one' : 'notifications.pager.showing_many', ['from' => $notifyBoardPagerShownFrom, 'to' => $notifyBoardPagerShownTo, 'total' => $notifyBoardTotal])) ?></p>
        <?php if ($notifyBoardPagerTotalPages > 1): ?>
        <nav class="case-list-pager" aria-label="<?= __e('notifications.pagination.aria') ?>">
            <?php if ($notifyBoardPagerPage > 1): ?>
            <a class="case-page-btn" href="?page=<?= $notifyBoardPagerPage - 1 ?>" aria-label="<?= __e('cases.pagination.prev') ?>">‹</a>
            <?php else: ?>
            <span class="case-page-btn is-disabled" aria-disabled="true">‹</span>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $notifyBoardPagerTotalPages; $p++): ?>
            <a class="case-page-btn<?= $p === $notifyBoardPagerPage ? ' is-active' : '' ?>" href="?page=<?= $p ?>"<?= $p === $notifyBoardPagerPage ? ' aria-current="page"' : '' ?>><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($notifyBoardPagerPage < $notifyBoardPagerTotalPages): ?>
            <a class="case-page-btn" href="?page=<?= $notifyBoardPagerPage + 1 ?>" aria-label="<?= __e('cases.pagination.next') ?>">›</a>
            <?php else: ?>
            <span class="case-page-btn is-disabled" aria-disabled="true">›</span>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <p class="notify-board-foot muted" id="<?= e($notifyBoardId) ?>Foot" hidden></p>
</section>
<script>
(function () {
  const board = document.getElementById(<?= json_encode($notifyBoardId) ?>);
  if (!board) return;
  const search = document.getElementById(<?= json_encode($notifyBoardId . 'Search') ?>);
  const filter = document.getElementById(<?= json_encode($notifyBoardId . 'Filter') ?>);
  const list = document.getElementById(<?= json_encode($notifyBoardId . 'List') ?>);
  const foot = document.getElementById(<?= json_encode($notifyBoardId . 'Foot') ?>);
  const items = () => Array.from(list.querySelectorAll('.notify-board-item'));
  const apply = () => {
    const q = (search?.value || '').trim().toLowerCase();
    const f = filter?.value || '';
    let shown = 0;
    items().forEach((el) => {
      const blob = el.getAttribute('data-notify-search') || '';
      const tokens = el.getAttribute('data-notify-filter') || '';
      const matchQ = !q || blob.includes(q);
      const matchF = !f || tokens === f;
      const ok = matchQ && matchF;
      el.hidden = !ok;
      if (ok) shown++;
    });
    if (foot) {
      foot.hidden = shown === items().length;
      foot.textContent = shown === 1
        ? <?= json_encode(__('notifications.board.filtered_one')) ?>
        : <?= json_encode(__('notifications.board.filtered_many')) ?>.replace(':count', String(shown));
    }
  };
  search?.addEventListener('input', apply);
  filter?.addEventListener('change', apply);

  board.querySelectorAll('.notify-board-copy-btn').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const text = btn.getAttribute('data-copy-text') || '';
      try {
        await navigator.clipboard.writeText(text);
        const prev = btn.textContent;
        btn.textContent = <?= json_encode(__('contact.copied')) ?>;
        setTimeout(() => { btn.textContent = prev; }, 1400);
      } catch (e) {
        window.prompt(<?= json_encode(__('contact.copy_prompt')) ?>, text);
      }
    });
  });

  board.querySelectorAll('.notify-board-edit-toggle').forEach((btn) => {
    btn.addEventListener('click', () => {
      const item = btn.closest('.notify-board-item');
      const panel = item?.querySelector('.notify-board-edit');
      if (!item || !panel) return;
      const open = panel.hidden;
      board.querySelectorAll('.notify-board-item.is-editing').forEach((el) => {
        if (el === item) return;
        el.classList.remove('is-editing');
        const p = el.querySelector('.notify-board-edit');
        const t = el.querySelector('.notify-board-edit-toggle');
        if (p) p.hidden = true;
        if (t) t.setAttribute('aria-expanded', 'false');
      });
      panel.hidden = !open;
      item.classList.toggle('is-editing', open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        panel.querySelector('textarea')?.focus();
      }
    });
  });
  board.querySelectorAll('.notify-board-edit-cancel').forEach((btn) => {
    btn.addEventListener('click', () => {
      const item = btn.closest('.notify-board-item');
      const panel = item?.querySelector('.notify-board-edit');
      const toggle = item?.querySelector('.notify-board-edit-toggle');
      if (panel) panel.hidden = true;
      item?.classList.remove('is-editing');
      toggle?.setAttribute('aria-expanded', 'false');
    });
  });
})();
</script>
