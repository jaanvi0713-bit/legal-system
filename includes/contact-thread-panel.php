<?php
/**
 * Thread detail + reply panel.
 *
 * Expects: $contactThread, $contactMessages, $contactPortal ('client'|'lawyer'),
 * $contactBackUrl, $contactCanDelete, $contactCanClose, $contactCurrentUserId
 */
$contactPortal = $contactPortal ?? 'client';
$contactBackUrl = $contactBackUrl ?? 'contact.php';
$contactFormUrl = $contactFormUrl ?? 'contact.php?thread=' . (int) ($contactThread['id'] ?? 0);
$contactCanDelete = !empty($contactCanDelete);
$contactCanClose = !empty($contactCanClose);
$contactCurrentUserId = (int) ($contactCurrentUserId ?? (current_user()['id'] ?? 0));
$threadId = (int) ($contactThread['id'] ?? 0);
?>
<section class="panel contact-thread-view" id="contactThreadView">
    <div class="contact-thread-view-head">
        <a class="contact-back-link" href="<?= e($contactBackUrl) ?>">← <?= __e('common.back') ?></a>
        <div class="contact-thread-view-title">
            <h2><?= e(t_stored($contactThread['subject'])) ?></h2>
            <p class="muted">
                <?= e(format_datetime($contactThread['created_at'])) ?> ·
                <?= e(($contactThread['status'] ?? 'open') === 'closed' ? __('contact.status.closed') : __('contact.status.open')) ?>
            </p>
        </div>
        <div class="contact-thread-view-actions">
            <?php if ($contactCanClose && ($contactThread['status'] ?? 'open') === 'open'): ?>
            <form method="post" action="<?= e($contactFormUrl) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="close">
                <input type="hidden" name="thread_id" value="<?= $threadId ?>">
                <button class="btn btn-secondary btn-sm" type="submit"><?= __e('contact.close_thread') ?></button>
            </form>
            <?php endif; ?>
            <?php if ($contactCanDelete): ?>
            <form method="post" action="<?= e($contactFormUrl) ?>" data-confirm="<?= __e('confirm.delete_message') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="delete">
                <input type="hidden" name="thread_id" value="<?= $threadId ?>">
                <button class="btn btn-row-delete btn-sm" type="submit"><?= __e('common.delete') ?></button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="contact-message-stack">
        <?php foreach ($contactMessages as $msg):
            $msgId = (int) $msg['id'];
            $isOwn = (int) ($msg['sender_id'] ?? 0) === $contactCurrentUserId;
            $msgBody = t_stored($msg['body']);
        ?>
        <article class="contact-message<?= ($msg['sender_role'] ?? '') === 'lawyer' ? ' is-lawyer' : ' is-client' ?>" id="contactMsg<?= $msgId ?>">
            <header>
                <div class="contact-message-head">
                    <strong><?= e($msg['sender_name']) ?></strong>
                    <?php if (!empty($msg['edited_at'])): ?>
                    <span class="contact-message-edited"><?= __e('contact.message_edited') ?></span>
                    <?php endif; ?>
                </div>
                <time><?= e(format_datetime($msg['created_at'])) ?></time>
            </header>
            <div class="contact-message-body" data-contact-message-body><?= nl2br(e($msgBody)) ?></div>
            <div class="contact-message-actions row-actions">
                <button type="button" class="btn btn-row-open btn-sm contact-copy-btn" data-copy-text="<?= e($msgBody) ?>"><?= __e('common.copy') ?></button>
                <?php if ($isOwn): ?>
                <button type="button" class="btn btn-row-edit btn-sm contact-edit-toggle" aria-expanded="false"><?= __e('common.edit') ?></button>
                <?php endif; ?>
            </div>
            <?php if ($isOwn): ?>
            <div class="contact-message-edit" hidden>
                <form method="post" action="<?= e($contactFormUrl) ?>" class="contact-message-edit-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="edit_message">
                    <input type="hidden" name="thread_id" value="<?= $threadId ?>">
                    <input type="hidden" name="message_id" value="<?= $msgId ?>">
                    <div class="form-group full">
                        <textarea name="body" required rows="4"><?= e($msgBody) ?></textarea>
                    </div>
                    <div class="contact-message-edit-actions">
                        <button class="btn btn-primary btn-sm" type="submit"><?= __e('common.save') ?></button>
                        <button class="btn btn-secondary btn-sm contact-edit-cancel" type="button"><?= __e('common.cancel') ?></button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    </div>

    <?php if (($contactThread['status'] ?? 'open') === 'open'): ?>
    <form method="post" action="<?= e($contactFormUrl) ?>" class="contact-reply-form entity-inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="reply">
        <input type="hidden" name="thread_id" value="<?= $threadId ?>">
        <div class="form-group full">
            <label><?= __e('contact.reply') ?></label>
            <textarea name="body" required rows="4" placeholder="<?= __e('contact.reply_ph') ?>"></textarea>
        </div>
        <div class="form-actions full">
            <button class="btn btn-primary" type="submit"><?= __e('contact.send_reply') ?></button>
        </div>
    </form>
    <?php endif; ?>
</section>
<script>
(function () {
  const root = document.getElementById('contactThreadView');
  if (!root) return;

  root.querySelectorAll('.contact-copy-btn').forEach((btn) => {
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

  root.querySelectorAll('.contact-edit-toggle').forEach((btn) => {
    btn.addEventListener('click', () => {
      const item = btn.closest('.contact-message');
      const panel = item?.querySelector('.contact-message-edit');
      if (!item || !panel) return;
      const open = panel.hidden;
      root.querySelectorAll('.contact-message.is-editing').forEach((el) => {
        if (el === item) return;
        el.classList.remove('is-editing');
        const p = el.querySelector('.contact-message-edit');
        const t = el.querySelector('.contact-edit-toggle');
        if (p) p.hidden = true;
        if (t) t.setAttribute('aria-expanded', 'false');
      });
      panel.hidden = !open;
      item.classList.toggle('is-editing', open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) panel.querySelector('textarea')?.focus();
    });
  });

  root.querySelectorAll('.contact-edit-cancel').forEach((btn) => {
    btn.addEventListener('click', () => {
      const item = btn.closest('.contact-message');
      const panel = item?.querySelector('.contact-message-edit');
      const toggle = item?.querySelector('.contact-edit-toggle');
      if (panel) panel.hidden = true;
      item?.classList.remove('is-editing');
      toggle?.setAttribute('aria-expanded', 'false');
    });
  });
})();
</script>
