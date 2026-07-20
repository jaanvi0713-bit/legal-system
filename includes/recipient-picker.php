<?php
/**
 * Searchable grouped recipient picker.
 *
 * Expects:
 * - $recipientPickerId (string)
 * - $recipientPickerLawyers, $recipientPickerClients, $recipientPickerUsers (arrays of user rows)
 * Optional: $recipientPickerName ('user_id'), $recipientPickerDefault ('all')
 */
$recipientPickerId = $recipientPickerId ?? 'recipientPicker';
$recipientPickerName = $recipientPickerName ?? 'user_id';
$recipientPickerDefault = $recipientPickerDefault ?? 'all';
$recipientPickerDefaultLabel = __('notifications.recipient.all');

$recipientGroups = [
    ['key' => 'lawyers', 'label' => __('notifications.recipient.lawyers'), 'items' => $recipientPickerLawyers ?? []],
    ['key' => 'clients', 'label' => __('notifications.recipient.clients'), 'items' => $recipientPickerClients ?? []],
    ['key' => 'users', 'label' => __('notifications.recipient.users'), 'items' => $recipientPickerUsers ?? []],
];
?>
<div class="recipient-picker" id="<?= e($recipientPickerId) ?>" data-recipient-picker>
    <input type="hidden" name="<?= e($recipientPickerName) ?>" value="<?= e($recipientPickerDefault) ?>" required data-recipient-value>
    <button type="button" class="recipient-picker-trigger" aria-haspopup="listbox" aria-expanded="false" data-recipient-trigger>
        <span class="recipient-picker-label" data-recipient-label><?= e($recipientPickerDefaultLabel) ?></span>
        <svg class="recipient-picker-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
    </button>
    <div class="recipient-picker-panel" data-recipient-panel aria-hidden="true">
        <label class="appt-list-search recipient-picker-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input type="search" placeholder="<?= __e('notifications.recipient.search_ph') ?>" autocomplete="off" data-recipient-search>
        </label>
        <div class="recipient-picker-list" role="listbox" data-recipient-list>
            <button type="button" class="recipient-picker-option is-selected" role="option" aria-selected="true"
                data-value="all"
                data-label="<?= e(__('notifications.recipient.all')) ?>"
                data-search="all <?= e(__('notifications.recipient.all')) ?>">
                <?= __e('notifications.recipient.all') ?>
            </button>
            <?php foreach ($recipientGroups as $group): ?>
                <?php if (!$group['items']) continue; ?>
                <div class="recipient-picker-group" data-recipient-group>
                    <div class="recipient-picker-group-label"><?= e($group['label']) ?></div>
                    <?php foreach ($group['items'] as $u):
                        $label = full_name($u);
                        $roleLabel = $group['key'] === 'users' ? translate_role($u['role']) : $group['label'];
                        if ($group['key'] === 'users') {
                            $label = full_name($u) . ' (' . translate_role($u['role']) . ')';
                        }
                        $search = strtolower(trim(implode(' ', [
                            full_name($u),
                            $u['first_name'] ?? '',
                            $u['last_name'] ?? '',
                            $u['role'] ?? '',
                            $roleLabel,
                            $group['label'],
                        ])));
                    ?>
                    <button type="button" class="recipient-picker-option" role="option" aria-selected="false"
                        data-value="<?= (int) $u['id'] ?>"
                        data-label="<?= e($label) ?>"
                        data-search="<?= e($search) ?>">
                        <?= e($label) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <p class="recipient-picker-empty muted" data-recipient-empty hidden><?= __e('notifications.recipient.none') ?></p>
        </div>
    </div>
</div>
<script>
(function () {
  const root = document.getElementById(<?= json_encode($recipientPickerId) ?>);
  if (!root) return;

  const hidden = root.querySelector('[data-recipient-value]');
  const trigger = root.querySelector('[data-recipient-trigger]');
  const label = root.querySelector('[data-recipient-label]');
  const panel = root.querySelector('[data-recipient-panel]');
  const search = root.querySelector('[data-recipient-search]');
  const options = () => Array.from(root.querySelectorAll('.recipient-picker-option'));
  const groups = () => Array.from(root.querySelectorAll('[data-recipient-group]'));
  const empty = root.querySelector('[data-recipient-empty]');
  let repositionHandler = null;

  const positionPanel = () => {
    const rect = trigger.getBoundingClientRect();
    const maxWidth = Math.min(window.innerWidth - 16, Math.max(rect.width, 16));
    let left = rect.left;
    if (left + maxWidth > window.innerWidth - 8) {
      left = Math.max(8, window.innerWidth - maxWidth - 8);
    }
    panel.style.width = maxWidth + 'px';
    panel.style.left = left + 'px';
    panel.style.top = (rect.bottom + 6) + 'px';
  };

  const close = () => {
    panel.classList.remove('is-open');
    panel.setAttribute('aria-hidden', 'true');
    trigger.setAttribute('aria-expanded', 'false');
    root.classList.remove('is-open');
    if (panel.parentElement !== root) {
      root.appendChild(panel);
    }
    if (repositionHandler) {
      window.removeEventListener('resize', repositionHandler);
      window.removeEventListener('scroll', repositionHandler, true);
      repositionHandler = null;
    }
  };

  const open = () => {
    if (panel.parentElement !== document.body) {
      document.body.appendChild(panel);
    }
    positionPanel();
    panel.classList.add('is-open');
    panel.setAttribute('aria-hidden', 'false');
    trigger.setAttribute('aria-expanded', 'true');
    root.classList.add('is-open');
    search.value = '';
    filter('');
    search.focus();
    repositionHandler = () => {
      if (panel.classList.contains('is-open')) positionPanel();
    };
    window.addEventListener('resize', repositionHandler);
    window.addEventListener('scroll', repositionHandler, true);
  };

  const isOpen = () => panel.classList.contains('is-open');

  const selectOption = (btn) => {
    options().forEach((el) => {
      const on = el === btn;
      el.classList.toggle('is-selected', on);
      el.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    hidden.value = btn.getAttribute('data-value') || '';
    label.textContent = btn.getAttribute('data-label') || '';
    close();
  };

  const filter = (q) => {
    const needle = q.trim().toLowerCase();
    let visible = 0;
    options().forEach((el) => {
      const blob = el.getAttribute('data-search') || '';
      const ok = !needle || blob.includes(needle);
      el.hidden = !ok;
      if (ok) visible++;
    });
    groups().forEach((group) => {
      const hasVisible = Array.from(group.querySelectorAll('.recipient-picker-option')).some((el) => !el.hidden);
      group.hidden = !hasVisible;
    });
    if (empty) empty.hidden = visible > 0;
  };

  trigger.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    if (isOpen()) close();
    else open();
  });

  panel.addEventListener('mousedown', (e) => {
    e.stopPropagation();
  });

  options().forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      selectOption(btn);
    });
  });

  search.addEventListener('input', () => filter(search.value));
  search.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      e.preventDefault();
      close();
      trigger.focus();
    }
  });

  document.addEventListener('click', (e) => {
    if (!isOpen()) return;
    if (root.contains(e.target) || panel.contains(e.target)) return;
    close();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && isOpen()) {
      close();
      trigger.focus();
    }
  });
})();
</script>
