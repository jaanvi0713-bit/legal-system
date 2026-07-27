<?php
/**
 * Single-value searchable dropdown (hidden input + filterable list).
 *
 * Expects:
 * - $searchableSelectId (string)
 * - $searchableSelectName (string)
 * - $searchableSelectOptions (list<array{id:int|string,label:string,search?:string}>)
 *
 * Optional:
 * - $searchableSelectValue (int|string)
 * - $searchableSelectPlaceholder (string)
 * - $searchableSelectRequired (bool)
 * - $searchableSelectSearchPlaceholder (string)
 */
$searchableSelectId = $searchableSelectId ?? 'searchableSelect';
$searchableSelectName = $searchableSelectName ?? 'select_id';
$searchableSelectOptions = $searchableSelectOptions ?? [];
$searchableSelectValue = (string) ($searchableSelectValue ?? '');
$searchableSelectPlaceholder = $searchableSelectPlaceholder ?? '';
$searchableSelectRequired = !empty($searchableSelectRequired);
$searchableSelectSearchPlaceholder = $searchableSelectSearchPlaceholder ?? __('notifications.recipient.search_ph');
$searchableSelectInputId = $searchableSelectInputId ?? $searchableSelectName;

$selectedLabel = $searchableSelectPlaceholder;
foreach ($searchableSelectOptions as $opt) {
    if ((string) ($opt['id'] ?? '') === $searchableSelectValue && $searchableSelectValue !== '') {
        $selectedLabel = (string) ($opt['label'] ?? '');
        break;
    }
}
?>
<div class="recipient-picker searchable-select" id="<?= e($searchableSelectId) ?>" data-searchable-select>
    <input type="hidden"
           id="<?= e($searchableSelectInputId) ?>"
           name="<?= e($searchableSelectName) ?>"
           value="<?= e($searchableSelectValue) ?>"
           <?= $searchableSelectRequired ? 'required' : '' ?>
           data-searchable-value>
    <button type="button" class="recipient-picker-trigger" aria-haspopup="listbox" aria-expanded="false" data-searchable-trigger>
        <span class="recipient-picker-label<?= $searchableSelectValue === '' ? ' is-placeholder' : '' ?>" data-searchable-label><?= e($selectedLabel) ?></span>
        <svg class="recipient-picker-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
    </button>
    <div class="recipient-picker-panel" data-searchable-panel aria-hidden="true">
        <label class="appt-list-search recipient-picker-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input type="search" placeholder="<?= e($searchableSelectSearchPlaceholder) ?>" autocomplete="off" data-searchable-search>
        </label>
        <div class="recipient-picker-list" role="listbox" data-searchable-list>
            <?php foreach ($searchableSelectOptions as $opt):
                $optId = (string) ($opt['id'] ?? '');
                $optLabel = (string) ($opt['label'] ?? '');
                $optSearch = (string) ($opt['search'] ?? strtolower($optLabel));
                $isSelected = $searchableSelectValue !== '' && $optId === $searchableSelectValue;
            ?>
            <button type="button"
                    class="recipient-picker-option<?= $isSelected ? ' is-selected' : '' ?>"
                    role="option"
                    aria-selected="<?= $isSelected ? 'true' : 'false' ?>"
                    data-value="<?= e($optId) ?>"
                    data-label="<?= e($optLabel) ?>"
                    data-search="<?= e($optSearch) ?>">
                <?= e($optLabel) ?>
            </button>
            <?php endforeach; ?>
            <p class="recipient-picker-empty muted" data-searchable-empty hidden><?= __e('notifications.recipient.none') ?></p>
        </div>
    </div>
</div>
<script>
(function () {
  const root = document.getElementById(<?= json_encode($searchableSelectId) ?>);
  if (!root || root.dataset.searchableInit === '1') return;
  root.dataset.searchableInit = '1';

  const hidden = root.querySelector('[data-searchable-value]');
  const trigger = root.querySelector('[data-searchable-trigger]');
  const label = root.querySelector('[data-searchable-label]');
  const panel = root.querySelector('[data-searchable-panel]');
  const search = root.querySelector('[data-searchable-search]');
  const placeholder = <?= json_encode($searchableSelectPlaceholder) ?>;
  const options = () => Array.from(root.querySelectorAll('.recipient-picker-option'));
  const empty = root.querySelector('[data-searchable-empty]');
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
    label.textContent = btn.getAttribute('data-label') || placeholder;
    label.classList.toggle('is-placeholder', !hidden.value);
    close();
    hidden.dispatchEvent(new Event('change', { bubbles: true }));
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
