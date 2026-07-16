document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.nav-toggle');
  if (toggle) {
    toggle.addEventListener('click', () => {
      document.body.classList.toggle('sidebar-open');
    });
  }

  const collapseBtn = document.querySelector('.sidebar-collapse');
  if (collapseBtn) {
    collapseBtn.addEventListener('click', () => {
      document.documentElement.classList.toggle('sidebar-collapsed');
      try {
        localStorage.setItem(
          'lexora-sidebar',
          document.documentElement.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded'
        );
      } catch (e) {}
    });
  }

  // Expand again when clicking brand mark while collapsed
  const brandMark = document.querySelector('.sidebar-brand .brand-mark');
  if (brandMark) {
    brandMark.style.cursor = 'pointer';
    brandMark.addEventListener('click', () => {
      if (!document.documentElement.classList.contains('sidebar-collapsed')) return;
      document.documentElement.classList.remove('sidebar-collapsed');
      try { localStorage.setItem('lexora-sidebar', 'expanded'); } catch (e) {}
    });
  }

  const themeBtn = document.querySelector('.theme-toggle');
  if (themeBtn) {
    themeBtn.addEventListener('click', () => {
      const root = document.documentElement;
      const next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
      root.setAttribute('data-theme', next);
      try {
        localStorage.setItem('lexora-theme', next);
      } catch (e) {}
      if (typeof Chart !== 'undefined' && window.LEXORA_DASHBOARD) {
        ['chartOverview', 'chartCases', 'chartRevenue', 'chartTypes', 'chartStatus', 'chartWeekdays'].forEach((id) => {
          const el = document.getElementById(id);
          if (!el) return;
          const existing = Chart.getChart(el);
          if (existing) existing.destroy();
        });
        initDashboardCharts();
      }
    });
  }

  const popMenus = document.querySelectorAll('.topbar-account, .topbar-notify-menu');
  if (popMenus.length) {
    document.addEventListener('click', (e) => {
      popMenus.forEach((menu) => {
        if (!menu.open) return;
        if (!menu.contains(e.target)) menu.open = false;
      });
    });
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape') return;
      popMenus.forEach((menu) => {
        if (menu.open) menu.open = false;
      });
    });
    // Only one header popover open at a time
    popMenus.forEach((menu) => {
      menu.addEventListener('toggle', () => {
        if (!menu.open) return;
        popMenus.forEach((other) => {
          if (other !== menu && other.open) other.open = false;
        });
      });
    });
  }

  document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('click', (e) => {
      const i18n = window.LEXORA_I18N || {};
      if (!confirm(el.getAttribute('data-confirm') || i18n.confirm || 'Are you sure?')) {
        e.preventDefault();
      }
    });
  });

  // Shared search + status filter for appointment / court list panels
  window.lexoraInitListFilter = function (opts) {
    const panel = document.getElementById(opts.panelId || '');
    if (!panel) return;
    const search = document.getElementById(opts.searchId || '');
    const status = document.getElementById(opts.statusId || '');
    const table = document.getElementById(opts.tableId || '');
    const footer = document.getElementById(opts.footerId || '');
    const totalMeta = document.getElementById(opts.totalMetaId || '');
    if (!table) return;
    const rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-list-search], tbody tr[data-appt-search]'));
    const total = rows.length;
    const showingTpl = opts.showingTpl || 'Showing :shown of :total.';
    const totalOne = opts.totalOne || ':count total';
    const totalMany = opts.totalMany || ':count total';

    function applyFilter() {
      const q = (search && search.value ? search.value : '').trim().toLowerCase();
      const st = status && status.value ? status.value : '';
      let shown = 0;
      rows.forEach(function (row) {
        const hay = row.getAttribute('data-list-search') || row.getAttribute('data-appt-search') || '';
        const rowStatus = row.getAttribute('data-list-status') || row.getAttribute('data-appt-status') || '';
        const ok = (!q || hay.indexOf(q) !== -1) && (!st || rowStatus === st);
        row.hidden = !ok;
        if (ok) shown += 1;
      });
      if (footer) {
        footer.textContent = String(showingTpl).replace(':shown', String(shown)).replace(':total', String(total));
      }
      if (totalMeta) {
        totalMeta.textContent = String(shown === 1 ? totalOne : totalMany).replace(':count', String(shown));
      }
    }
    if (search) search.addEventListener('input', applyFilter);
    if (status) status.addEventListener('change', applyFilter);
  };

  document.querySelectorAll('[data-list-filter]').forEach((panel) => {
    window.lexoraInitListFilter({
      panelId: panel.id,
      searchId: panel.getAttribute('data-search-id') || 'apptListSearch',
      statusId: panel.getAttribute('data-status-id') || 'apptListStatus',
      tableId: panel.getAttribute('data-table-id') || 'apptListTable',
      footerId: panel.getAttribute('data-footer-id') || 'apptListFooter',
      totalMetaId: panel.getAttribute('data-total-meta-id') || 'apptListTotalMeta',
      showingTpl: panel.getAttribute('data-showing-tpl') || '',
      totalOne: panel.getAttribute('data-total-one') || '',
      totalMany: panel.getAttribute('data-total-many') || '',
    });
  });

  const aiForm = document.getElementById('ai-compose-form');
  if (aiForm) {
    const sendAiMessage = async (text) => {
      const input = document.getElementById('ai-message');
      const messages = document.getElementById('ai-messages');
      const sessionId = aiForm.dataset.sessionId;
      const value = (text || '').trim();
      if (!value) return;

      const welcome = messages.querySelector('.ai-welcome');
      if (welcome) welcome.remove();

      const userMsg = document.createElement('div');
      userMsg.className = 'msg msg-user';
      userMsg.textContent = value;
      messages.appendChild(userMsg);
      if (input) input.value = '';
      messages.scrollTop = messages.scrollHeight;

      const thinking = document.createElement('div');
      thinking.className = 'msg msg-assistant ai-bubble';
      const i18n = window.LEXORA_I18N || {};
      thinking.innerHTML = '<div class="ai-bot-mark sm" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="7" width="14" height="11" rx="3"/><circle cx="9.5" cy="12" r="1.2" fill="currentColor" stroke="none"/><circle cx="14.5" cy="12" r="1.2" fill="currentColor" stroke="none"/><path d="M9 18v2M15 18v2M12 4v3"/></svg></div><div></div>';
      thinking.querySelector('div:last-child').textContent = i18n.thinking || 'Thinking…';
      messages.appendChild(thinking);
      const thinkingText = thinking.querySelector('div:last-child');

      try {
        const res = await fetch('../api/ai-chat.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ session_id: Number(sessionId), message: value }),
        });
        const data = await res.json();
        thinkingText.textContent = data.reply || data.error || i18n.no_response || 'No response.';
      } catch (err) {
        thinkingText.textContent = i18n.service_error || 'Unable to reach the AI service. Please try again.';
      }
      messages.scrollTop = messages.scrollHeight;
    };

    aiForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const input = document.getElementById('ai-message');
      await sendAiMessage(input ? input.value : '');
    });

    window.lexoraSendAiMessage = sendAiMessage;
  }

  initAiAssistantUi();
  initDashboardCharts();
  initAppointmentsCalendar();
});

window.addEventListener('load', () => {
  if (window.LEXORA_DASHBOARD) {
    initDashboardCharts();
    if (typeof window.initGlassOverview === 'function') window.initGlassOverview();
  }
});

/* Dashboard overview is initialized from admin/index.php (initGlassOverview). */

function initAiAssistantUi() {
  const workspace = document.getElementById('ai-workspace');
  if (!workspace) return;

  const library = document.getElementById('ai-library');
  const libraryToggle = document.getElementById('ai-library-toggle');
  const libraryClose = document.getElementById('ai-library-close');
  if (libraryToggle && library) {
    libraryToggle.addEventListener('click', () => {
      library.hidden = !library.hidden;
    });
  }
  if (libraryClose && library) {
    libraryClose.addEventListener('click', () => {
      library.hidden = true;
    });
  }

  const attachBtn = document.querySelector('.ai-attach');
  if (attachBtn) {
    attachBtn.addEventListener('click', () => {
      const i18n = window.LEXORA_I18N || {};
      alert(i18n.attach_disabled || 'File attachments will be available when document AI is enabled. For now, describe the file in your question.');
    });
  }

  const icons = {
    user: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8" r="3.5"/><path d="M5 19.5c1.5-3.2 4-4.5 7-4.5s5.5 1.3 7 4.5"/></svg>',
    briefcase: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><rect x="3" y="7" width="18" height="14" rx="2"/><path d="M3 13h18"/></svg>',
    money: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20M12 10v8"/></svg>',
    calendar: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>',
    doc: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 3h6l5 5v13a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v5h5"/></svg>',
    alert: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3l9 16H3L12 3z"/><path d="M12 10v4M12 17h.01"/></svg>',
    bell: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M7 9a5 5 0 0 1 10 0c0 5 2 6 2 6H5s2-1 2-6"/><path d="M10 19a2 2 0 0 0 4 0"/></svg>',
    chart: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 19V5M4 19h16"/><path d="M8 15v-4M12 15V8M16 15v-6"/></svg>',
    grid: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/></svg>',
    edit: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 20h4L18 10l-4-4L4 16v4z"/><path d="M12 8l4 4"/></svg>',
    users: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="8" r="3"/><circle cx="16" cy="9" r="2.5"/><path d="M3 19c1.2-3 3.5-4.5 6-4.5S13.8 16 15 19"/></svg>',
    court: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 20h16M6 20V10M10 20V10M14 20V10M18 20V10M3 10h18M12 4l9 6H3l9-6z"/></svg>',
    tasks: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 11l2 2 4-4"/><rect x="4" y="3" width="16" height="18" rx="2"/></svg>',
  };

  let prompts = [];
  try {
    prompts = JSON.parse(workspace.getAttribute('data-prompts') || '[]');
  } catch (e) {
    prompts = [];
  }

  const perPage = 9;
  let page = 0;
  const list = document.getElementById('ai-prompt-list');
  const label = document.getElementById('ai-prompt-page-label');
  const prev = document.getElementById('ai-prompt-prev');
  const next = document.getElementById('ai-prompt-next');
  const totalPages = Math.max(1, Math.ceil(prompts.length / perPage));

  const bindPromptButtons = () => {
    list.querySelectorAll('.ai-prompt-btn').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const prompt = btn.getAttribute('data-prompt') || '';
        const input = document.getElementById('ai-message');
        if (input) input.value = prompt;
        if (window.lexoraSendAiMessage) {
          await window.lexoraSendAiMessage(prompt);
        }
      });
    });
  };

  const renderPrompts = () => {
    if (!list) return;
    const start = page * perPage;
    const slice = prompts.slice(start, start + perPage);
    list.innerHTML = slice.map((item) => {
      const icon = icons[item.icon] || icons.grid;
      return `<button type="button" class="ai-prompt-btn" data-prompt="${String(item.prompt).replace(/"/g, '&quot;')}">
        <span class="ai-prompt-icon">${icon}</span>
        <span class="ai-prompt-label">${item.label}</span>
        <span class="ai-prompt-chevron" aria-hidden="true">›</span>
      </button>`;
    }).join('');
    if (label) label.textContent = `${page + 1} / ${totalPages}`;
    bindPromptButtons();
  };

  if (prev) {
    prev.addEventListener('click', () => {
      page = (page - 1 + totalPages) % totalPages;
      renderPrompts();
    });
  }
  if (next) {
    next.addEventListener('click', () => {
      page = (page + 1) % totalPages;
      renderPrompts();
    });
  }

  renderPrompts();
}

function cssVar(name, fallback) {
  const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  return value || fallback;
}

function initDashboardCharts() {
  const data = window.LEXORA_DASHBOARD;
  if (!data) return;

  const hasChart = typeof Chart !== 'undefined';
  if (hasChart) {
    Chart.defaults.font.family = '"Montserrat", "Segoe UI", sans-serif';
    Chart.defaults.font.size = 11;
    Chart.defaults.color = cssVar('--ink-soft', '#718096');
  }

  const gridColor = () => cssVar('--chart-grid', 'rgba(26,32,44,0.06)');
  const inkSoft = () => cssVar('--ink-soft', '#718096');
  const blue = () => cssVar('--blue', '#023e8a');
  const purple = () => cssVar('--purple', '#001845');
  const magenta = () => cssVar('--purple-bright', '#7f9ec4');
  const primary = () => cssVar('--primary', '#023e8a');
  const primaryRgb = () => cssVar('--primary-rgb', '2, 62, 138');
  const rgbaPrimary = (alpha) => `rgba(${primaryRgb()}, ${alpha})`;
  const rgbaPurple = (alpha) => {
    const hex = purple().replace('#', '');
    if (hex.length !== 6) return `rgba(0, 24, 69, ${alpha})`;
    const r = parseInt(hex.slice(0, 2), 16);
    const g = parseInt(hex.slice(2, 4), 16);
    const b = parseInt(hex.slice(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
  };

  const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: primary(),
        titleColor: '#fff',
        bodyColor: '#fff',
        cornerRadius: 10,
        padding: 10,
      },
    },
  };

  if (typeof window.initGlassOverview === 'function') {
    window.initGlassOverview();
  }

  const overviewEl = document.getElementById('chartOverview');
  if (hasChart && overviewEl && !window.LEXORA_OVERVIEW_SVG && !Chart.getChart(overviewEl)) {
    const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';
    const sliceByRange = (range) => {
      const len = (data.months || []).length;
      if (range === '24h') return Math.min(2, len);
      if (range === 'week') return Math.min(3, len);
      if (range === 'year') return Math.max(len, 1);
      return Math.min(7, Math.max(len, 1));
    };
    const buildDataset = (range) => {
      const n = sliceByRange(range);
      const labels = (data.months || []).slice(-n);
      const revenue = (data.revenue || []).slice(-n);
      const opened = (data.opened || []).slice(-n);
      const revenueSum = revenue.reduce((a, b) => a + Number(b || 0), 0);
      const useCases = revenueSum <= 0 && opened.some((v) => Number(v) > 0);
      return {
        labels: labels.length ? labels : ['—'],
        values: (useCases ? opened : revenue).length ? (useCases ? opened : revenue) : [0],
        label: useCases ? 'Cases opened' : 'Collections',
        isMoney: !useCases,
      };
    };
    const themeColors = () => {
      const dark = isDark();
      return {
        line: dark ? '#f4f7fb' : primary(),
        fill: dark ? 'rgba(244, 247, 251, 0.12)' : rgbaPrimary(0.16),
        tick: dark ? 'rgba(232, 238, 248, 0.65)' : inkSoft(),
        grid: dark ? 'rgba(255, 255, 255, 0.08)' : gridColor(),
        pointBorder: dark ? 'rgba(255, 255, 255, 0.95)' : `rgba(${primaryRgb()}, 0.95)`,
      };
    };

    let built = buildDataset('month');
    let colors = themeColors();
    const overviewChart = new Chart(overviewEl, {
      type: 'line',
      data: {
        labels: built.labels,
        datasets: [{
          label: built.label,
          data: built.values,
          borderColor: colors.line,
          backgroundColor: colors.fill,
          fill: true,
          borderWidth: 2.5,
          tension: 0.45,
          pointRadius: 3,
          pointBackgroundColor: colors.line,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { ticks: { color: colors.tick }, grid: { display: false }, border: { display: false } },
          y: { beginAtZero: true, ticks: { color: colors.tick }, grid: { color: colors.grid }, border: { display: false } },
        },
      },
    });

    document.querySelectorAll('.glass-range-btn').forEach((btn) => {
      btn.onclick = () => {
        document.querySelectorAll('.glass-range-btn').forEach((b) => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        built = buildDataset(btn.dataset.range || 'month');
        overviewChart.data.labels = built.labels;
        overviewChart.data.datasets[0].data = built.values;
        overviewChart.update();
      };
    });
  }

  if (!hasChart) return;

  const casesEl = document.getElementById('chartCases');
  if (casesEl) {
    new Chart(casesEl, {
      type: 'line',
      data: {
        labels: data.months,
        datasets: [
          {
            label: (window.LEXORA_I18N && window.LEXORA_I18N.chart_cases_opened) || 'Cases opened',
            data: data.opened,
            borderColor: blue(),
            backgroundColor: rgbaPrimary(0.16),
            fill: true,
            borderWidth: 3,
            tension: 0.4,
            pointRadius: 3,
            pointBackgroundColor: blue(),
          },
          {
            label: (window.LEXORA_I18N && window.LEXORA_I18N.chart_cases_closed) || 'Cases closed',
            data: data.closed,
            borderColor: purple(),
            backgroundColor: rgbaPurple(0.14),
            fill: true,
            borderWidth: 3,
            tension: 0.4,
            pointRadius: 3,
            pointBackgroundColor: purple(),
          },
        ],
      },
      options: {
        ...commonOptions,
        scales: {
          x: { ticks: { color: inkSoft() }, grid: { display: false } },
          y: { beginAtZero: true, ticks: { color: inkSoft() }, grid: { color: gridColor() } },
        },
      },
    });
  }

  const revenueEl = document.getElementById('chartRevenue');
  if (revenueEl) {
    new Chart(revenueEl, {
      type: 'bar',
      data: {
        labels: data.months,
        datasets: [{
          label: (window.LEXORA_I18N && window.LEXORA_I18N.finance_revenue) || 'Revenue',
          data: data.revenue,
          backgroundColor: data.revenue.map((_, i) => (i % 2 === 0 ? blue() : purple())),
          borderRadius: 8,
          barPercentage: 0.55,
        }],
      },
      options: {
        ...commonOptions,
        scales: {
          x: { ticks: { color: inkSoft() }, grid: { display: false } },
          y: { ticks: { color: inkSoft() }, grid: { color: gridColor() } },
        },
      },
    });
  }

  const typeColors = [blue(), purple(), magenta(), primary(), cssVar('--blue-bright', '#1e5fad')];
  const typesEl = document.getElementById('chartTypes');
  if (typesEl) {
    new Chart(typesEl, {
      type: 'doughnut',
      data: {
        labels: data.types.labels,
        datasets: [{
          data: data.types.values,
          backgroundColor: data.types.labels.map((_, i) => typeColors[i % typeColors.length]),
          borderWidth: 0,
          hoverOffset: 4,
        }],
      },
      options: {
        ...commonOptions,
        cutout: '68%',
      },
    });
    const legend = document.getElementById('legendTypes');
    if (legend) {
      legend.innerHTML = data.types.labels.map((label, i) => (
        `<span><i style="background:${typeColors[i % typeColors.length]}"></i>${label}</span>`
      )).join('');
    }
  }

  const statusEl = document.getElementById('chartStatus');
  if (statusEl) {
    new Chart(statusEl, {
      type: 'doughnut',
      data: {
        labels: [
          (window.LEXORA_I18N && window.LEXORA_I18N.status_active) || 'Active',
          (window.LEXORA_I18N && window.LEXORA_I18N.status_closed) || 'Closed',
        ],
        datasets: [{
          data: [data.status.active, data.status.closed],
          backgroundColor: [blue(), purple()],
          borderWidth: 0,
        }],
      },
      options: {
        ...commonOptions,
        cutout: '74%',
      },
    });
  }

  const weekEl = document.getElementById('chartWeekdays');
  if (weekEl) {
    new Chart(weekEl, {
      type: 'bar',
      data: {
        labels: (window.LEXORA_I18N && window.LEXORA_I18N.weekdays) || ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
          data: data.weekdays,
          backgroundColor: data.weekdays.map((_, i) => (i % 2 === 0 ? blue() : purple())),
          borderRadius: 10,
          borderSkipped: false,
          barPercentage: 0.55,
        }],
      },
      options: {
        ...commonOptions,
        scales: {
          x: { ticks: { color: inkSoft() }, grid: { display: false } },
          y: { beginAtZero: true, ticks: { color: inkSoft() }, grid: { color: gridColor() } },
        },
      },
    });
  }
}

function initAppointmentsCalendar() {
  const root = document.getElementById('apptCalendar');
  const daysEl = document.getElementById('apptCalDays');
  if (!root || !daysEl) return;

  const defaults = {
    items: [],
    months: [],
    createUrl: root.dataset.createUrl || '',
    emptyDay: root.dataset.emptyDay || 'No appointments on this day.',
    scheduleLabel: root.dataset.scheduleLabel || 'Schedule appointment',
    viewLabel: root.dataset.viewLabel || 'View appointment',
    viewAllLabel: root.dataset.viewAllLabel || 'View appointments',
    noViewLabel: root.dataset.noViewLabel || 'No appointments to view',
    apptCountOne: root.dataset.apptCountOne || ':count total appointment',
    apptCountMany: root.dataset.apptCountMany || ':count total appointments',
    pageOf: root.dataset.pageOf || 'Page :page of :pages',
    prevLabel: root.dataset.prevLabel || 'Previous',
    nextLabel: root.dataset.nextLabel || 'Next',
    locale: root.dataset.locale || document.documentElement.lang || 'en',
  };

  let data = defaults;
  if (window.LEXORA_APPT_CAL && typeof window.LEXORA_APPT_CAL === 'object') {
    data = { ...defaults, ...window.LEXORA_APPT_CAL };
  } else {
    const dataEl = document.getElementById('apptCalData');
    if (dataEl) {
      try {
        data = { ...defaults, ...JSON.parse(dataEl.textContent) };
      } catch (err) {
        console.error('Appointment calendar data parse failed', err);
      }
    }
  }

  const items = Array.isArray(data.items)
    ? data.items
    : Object.values(data.items || {}).filter((item) => item && typeof item === 'object');
  data.items = items;

  try {
  const agendaEl = document.getElementById('apptCalAgenda');
  const agendaHeadEl = document.getElementById('apptCalAgendaHead');
  const agendaPagerEl = document.getElementById('apptCalAgendaPager');
  const yearLabel = document.getElementById('apptCalYear');
  const monthTitle = document.getElementById('apptCalMonthTitle');
  const monthButtons = root.querySelectorAll('.appt-cal-month-btn');
  const monthCounts = root.querySelectorAll('[data-month-count]');
  const AGENDA_PER_PAGE = 2;
  let agendaPage = 1;

  let year = parseInt(root.dataset.year, 10);
  let month = parseInt(root.dataset.month, 10);
  let selectedDay = parseInt(root.dataset.day, 10);
  if (Number.isNaN(year)) year = new Date().getFullYear();
  if (Number.isNaN(month)) month = new Date().getMonth();
  if (Number.isNaN(selectedDay)) selectedDay = new Date().getDate();

  const urlCalDate = new URLSearchParams(window.location.search).get('cal_date');
  if (urlCalDate && /^\d{4}-\d{2}-\d{2}$/.test(urlCalDate)) {
    const parts = urlCalDate.split('-').map((n) => parseInt(n, 10));
    if (parts.length === 3 && parts[0] && parts[1] && parts[2]) {
      year = parts[0];
      month = parts[1] - 1;
      selectedDay = parts[2];
    }
  }

  if (!Array.isArray(data.months) || !data.months.length) {
    data.months = Array.from(monthButtons, (btn) => btn.querySelector('span')?.textContent?.trim() || '');
  }

  const esc = (value) =>
    String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');

  const tonePriority = {
    cancelled: 6,
    completed: 5,
    pending: 4,
    confirmed: 3,
    rescheduled: 2,
    scheduled: 1,
  };

  const dateKey = (y, m, d) =>
    `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;

  /** Prefer YYYY-MM-DD prefix to avoid timezone shifts from Date parsing. */
  const apptDateKey = (iso) => {
    const match = String(iso || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (match) return `${match[1]}-${match[2]}-${match[3]}`;
    const dt = new Date(String(iso || '').replace(' ', 'T'));
    if (Number.isNaN(dt.getTime())) return '';
    return dateKey(dt.getFullYear(), dt.getMonth(), dt.getDate());
  };

  const byDate = {};
  items.forEach((item) => {
    const key = apptDateKey(item.scheduledAt);
    if (!key) return;
    if (!byDate[key]) byDate[key] = [];
    byDate[key].push(item);
  });

  Object.keys(byDate).forEach((key) => {
    byDate[key].sort((a, b) => String(a.scheduledAt).localeCompare(String(b.scheduledAt)));
  });

  const monthCountForYear = (y) => {
    const counts = Array(12).fill(0);
    items.forEach((item) => {
      const key = apptDateKey(item.scheduledAt);
      if (!key || !key.startsWith(`${y}-`)) return;
      const m = parseInt(key.slice(5, 7), 10) - 1;
      if (m >= 0 && m < 12) counts[m] += 1;
    });
    return counts;
  };

  const pickDayTone = (items) => {
    let best = null;
    let score = 0;
    items.forEach((item) => {
      const s = tonePriority[item.tone] || 0;
      if (s >= score) {
        score = s;
        best = item.tone;
      }
    });
    return best;
  };

  const formatWithLocale = (dt, options, fallback) => {
    if (Number.isNaN(dt.getTime())) return fallback || '';
    try {
      return new Intl.DateTimeFormat(data.locale || undefined, options).format(dt);
    } catch (err) {
      try {
        return new Intl.DateTimeFormat(undefined, options).format(dt);
      } catch (err2) {
        return fallback || '';
      }
    }
  };

  const formatDayTime = (iso) => {
    const dt = new Date(String(iso).replace(' ', 'T'));
    return formatWithLocale(
      dt,
      { hour: 'numeric', minute: '2-digit', hour12: true },
      ''
    )
      .replace(/\s/g, '')
      .toLowerCase();
  };

  const formatSelectedDate = (y, m, d) => {
    const dt = new Date(y, m, d);
    return formatWithLocale(
      dt,
      { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' },
      `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    );
  };

  const buildCreateUrl = (y, m, d) => {
    const isoDate = dateKey(y, m, d);
    const base = (data.createUrl || root.dataset.createUrl || '').trim();
    if (!base) return '';
    const join = base.includes('?') ? '&' : '?';
    return `${base}${join}date=${isoDate}`;
  };

  const buildCalDateUrl = (y, m, d) => `?cal_date=${dateKey(y, m, d)}`;

  const syncCalDateUrl = (y, m, d) => {
    const next = buildCalDateUrl(y, m, d);
    if (window.history?.replaceState && window.location.search !== next) {
      window.history.replaceState(null, '', next);
    }
  };

  const badgeClass = (tone) => `appt-cal-badge tone-${tone || 'scheduled'}`;

  const selectDay = (day) => {
    if (!day) return;
    selectedDay = day;
    agendaPage = 1;
    root.dataset.day = String(selectedDay);
    updateDaySelection();
    renderAgenda();
    syncCalDateUrl(year, month, selectedDay);
    if (agendaEl) {
      agendaEl.scrollTop = 0;
    }
  };

  const updateDaySelection = () => {
    daysEl.querySelectorAll('.appt-cal-day:not(.is-empty)').forEach((cell) => {
      const d = parseInt(cell.dataset.day, 10);
      cell.classList.toggle('is-selected', d === selectedDay);
    });
  };

  const renderDayEvents = (items) => {
    if (!items.length) return '';
    const maxShow = 2;
    const shown = items.slice(0, maxShow);
    const extra = items.length - maxShow;
    const rows = shown
      .map(
        (item) => `
        <a href="?view=${item.id}" class="appt-cal-day-event tone-${esc(item.tone)}" data-appt-view="${item.id}" title="${esc(data.viewLabel || 'View appointment')}: ${esc(item.caseLabel || item.title)}" onclick="return window.lexoraViewAppointment ? window.lexoraViewAppointment(${item.id}, event) : true;">
          <span class="appt-cal-day-event-dot" aria-hidden="true"></span>
          <span class="appt-cal-day-event-label">
            <span class="appt-cal-day-event-time">${esc(formatDayTime(item.scheduledAt))}</span>
            ${esc(item.title)}
          </span>
        </a>`
      )
      .join('');
    const more =
      extra > 0
        ? `<span class="appt-cal-day-more">+${extra} more</span>`
        : '';
    return `<div class="appt-cal-day-events">${rows}${more}</div>`;
  };

  const renderAgendaPager = (total, page, pages) => {
    if (!agendaPagerEl) return;
    if (total < 1) {
      agendaPagerEl.innerHTML = '';
      agendaPagerEl.hidden = true;
      return;
    }
    agendaPagerEl.hidden = false;
    const label = esc(
      String(data.pageOf || 'Page :page of :pages')
        .replace(':page', String(page))
        .replace(':pages', String(pages))
    );
    agendaPagerEl.innerHTML = `
      <div class="appt-cal-agenda-pager" data-page="${page}" data-pages="${pages}">
        <button type="button" class="appt-cal-agenda-page-btn" data-agenda-page="prev"${page <= 1 ? ' disabled' : ''} aria-label="${esc(data.prevLabel || 'Previous')}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
        </button>
        <span class="appt-cal-agenda-page-label">${label}</span>
        <button type="button" class="appt-cal-agenda-page-btn" data-agenda-page="next"${page >= pages ? ' disabled' : ''} aria-label="${esc(data.nextLabel || 'Next')}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
        </button>
      </div>`;
  };

  const dayMenuEl = document.getElementById('apptCalDayMenu');
  const dayMenuDateEl = document.getElementById('apptCalDayMenuDate');
  const dayMenuViewBtn = document.getElementById('apptCalDayMenuView');
  const dayMenuScheduleBtn = document.getElementById('apptCalDayMenuSchedule');

  const buildScheduleHref = (dateStr) => {
    const base = String(data.createUrl || '').trim();
    if (!base) return '';
    if (base.startsWith('#')) return base;
    const sep = base.includes('?') ? '&' : '?';
    return `${base}${sep}date=${encodeURIComponent(dateStr)}`;
  };

  const focusScheduleForm = (href, dateStr) => {
    if (!href || !href.startsWith('#')) return false;
    const form = document.querySelector(href);
    if (!form) return false;
    const input =
      form.querySelector('input[type="datetime-local"][name="scheduled_at"]') ||
      form.querySelector('input[type="datetime-local"][name="hearing_date"]') ||
      form.querySelector('input[type="datetime-local"]');
    if (input && dateStr) {
      input.value = `${dateStr}T09:00`;
    }
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (input) {
      try {
        input.focus({ preventScroll: true });
      } catch (err) {
        input.focus();
      }
    }
    return true;
  };

  let dayMenuPortaled = false;
  const ensureDayMenuPortal = () => {
    if (!dayMenuEl || dayMenuPortaled) return;
    if (dayMenuEl.parentElement !== document.body) {
      document.body.appendChild(dayMenuEl);
    }
    dayMenuEl.style.position = 'fixed';
    dayMenuPortaled = true;
  };

  const hideDayMenu = () => {
    if (!dayMenuEl) return;
    dayMenuEl.hidden = true;
    dayMenuEl.setAttribute('hidden', '');
    dayMenuEl.removeAttribute('data-date');
    dayMenuEl.style.left = '';
    dayMenuEl.style.top = '';
  };

  const showDayMenu = (cell) => {
    if (!dayMenuEl || !cell) return;
    ensureDayMenuPortal();
    const key = cell.dataset.date || dateKey(year, month, parseInt(cell.dataset.day, 10));
    const dayItems = byDate[key] || [];
    const firstId = dayItems.length ? parseInt(dayItems[0].id, 10) || 0 : 0;
    const viewText =
      dayItems.length > 1
        ? data.viewAllLabel || data.viewLabel || 'View'
        : data.viewLabel || 'View';
    const scheduleText = data.scheduleLabel || 'Schedule';
    const noViewText = data.noViewLabel || 'Nothing to view';
    const scheduleHref = buildScheduleHref(key);
    const selectedLabel = formatSelectedDate(year, month, parseInt(cell.dataset.day, 10));

    if (dayMenuDateEl) dayMenuDateEl.textContent = selectedLabel;

    if (dayMenuViewBtn) {
      dayMenuViewBtn.textContent = viewText;
      dayMenuViewBtn.disabled = !firstId;
      dayMenuViewBtn.title = firstId ? viewText : noViewText;
      dayMenuViewBtn.removeAttribute('data-appt-view');
      dayMenuViewBtn.onclick = (ev) => {
        if (!firstId) return;
        ev.preventDefault();
        ev.stopPropagation();
        hideDayMenu();
        if (window.lexoraViewAppointment) {
          window.lexoraViewAppointment(firstId, ev);
        }
      };
    }

    if (dayMenuScheduleBtn) {
      if (scheduleHref) {
        dayMenuScheduleBtn.hidden = false;
        dayMenuScheduleBtn.textContent = scheduleText;
        dayMenuScheduleBtn.href = scheduleHref;
        dayMenuScheduleBtn.dataset.date = key;
        dayMenuScheduleBtn.setAttribute('data-cal-schedule', '');
      } else {
        dayMenuScheduleBtn.hidden = true;
      }
    }

    dayMenuEl.dataset.date = key;
    dayMenuEl.hidden = false;
    dayMenuEl.removeAttribute('hidden');

    const cellRect = cell.getBoundingClientRect();
    const menuWidth = dayMenuEl.offsetWidth || 184;
    const menuHeight = dayMenuEl.offsetHeight || 120;
    let left = cellRect.left + cellRect.width / 2 - menuWidth / 2;
    let top = cellRect.bottom + 6;
    left = Math.max(8, Math.min(left, window.innerWidth - menuWidth - 8));
    if (top + menuHeight > window.innerHeight - 8) {
      top = Math.max(8, cellRect.top - menuHeight - 6);
    }
    dayMenuEl.style.left = `${left}px`;
    dayMenuEl.style.top = `${top}px`;
  };

  const renderAgenda = () => {
    const key = dateKey(year, month, selectedDay);
    const dayItems = byDate[key] || [];
    const selectedLabel = formatSelectedDate(year, month, selectedDay);
    const pages = Math.max(1, Math.ceil(dayItems.length / AGENDA_PER_PAGE));
    agendaPage = Math.min(Math.max(1, agendaPage), pages);
    const slice = dayItems.slice((agendaPage - 1) * AGENDA_PER_PAGE, agendaPage * AGENDA_PER_PAGE);

    const countLabel = dayItems.length
      ? esc(
          String(
            (dayItems.length === 1 ? data.apptCountOne : data.apptCountMany) ||
              `${dayItems.length} appointment${dayItems.length > 1 ? 's' : ''}`
          ).replace(':count', String(dayItems.length))
        )
      : esc(data.emptyDay || 'No appointments on this day.');

    if (agendaHeadEl) {
      agendaHeadEl.innerHTML = `
        <div class="appt-cal-agenda-panel">
          <p class="appt-cal-agenda-date">${esc(selectedLabel)}</p>
          <p class="appt-cal-agenda-count">${countLabel}</p>
        </div>`;
    }

    if (!agendaEl) return;
    if (!dayItems.length) {
      agendaEl.innerHTML = '';
      renderAgendaPager(0, 1, 1);
      return;
    }
    agendaEl.innerHTML = slice
      .map(
        (item, index) => `
      <article class="appt-cal-agenda-card tone-${esc(item.tone)}" style="animation-delay:${index * 40}ms">
        <a href="?view=${item.id}" class="appt-cal-agenda-card-link" data-appt-view="${item.id}" title="${esc(data.viewLabel || 'View appointment')}" onclick="return window.lexoraViewAppointment ? window.lexoraViewAppointment(${item.id}, event) : true;">
          <span class="appt-cal-agenda-line tone-${esc(item.tone)}">
            <span class="appt-cal-day-event-dot" aria-hidden="true"></span>
            <span class="appt-cal-day-event-label">
              <span class="appt-cal-day-event-time">${esc(formatDayTime(item.scheduledAt))}</span>
              ${esc(item.caseLabel || item.title)}
            </span>
          </span>
          <span class="appt-cal-agenda-meta">
            <span class="appt-cal-person">${esc(item.client || item.lawyer || '')}</span>
            <span class="${badgeClass(item.tone)}">${esc(item.statusLabel)}</span>
          </span>
        </a>
      </article>`
      )
      .join('');
    renderAgendaPager(dayItems.length, agendaPage, pages);
  };

  const renderMonthCounts = () => {
    const counts = monthCountForYear(year);
    monthCounts.forEach((el) => {
      const idx = parseInt(el.dataset.monthCount, 10);
      el.textContent = String(counts[idx] || 0);
    });
  };

  daysEl.addEventListener('click', (e) => {
    if (e.target.closest('[data-appt-view]')) return;
    if (e.target.closest('#apptCalDayMenu')) return;

    const cell = e.target.closest('.appt-cal-day:not(.is-empty)');
    if (!cell) return;

    e.preventDefault();
    e.stopPropagation();

    const day = parseInt(cell.dataset.day, 10);
    if (Number.isNaN(day)) return;

    selectDay(day);
    showDayMenu(cell);
  });

  if (agendaPagerEl) {
    agendaPagerEl.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-agenda-page]');
      if (!btn || btn.disabled) return;
      const dir = btn.getAttribute('data-agenda-page');
      if (dir === 'prev') agendaPage -= 1;
      if (dir === 'next') agendaPage += 1;
      renderAgenda();
      if (agendaEl) agendaEl.scrollTop = 0;
    });
  }

  if (dayMenuEl) {
    dayMenuEl.addEventListener('click', (e) => {
      const scheduleLink = e.target.closest('[data-cal-schedule]');
      if (!scheduleLink) return;
      const href = scheduleLink.getAttribute('href') || '';
      const dateStr = scheduleLink.getAttribute('data-date') || dayMenuEl.dataset.date || '';
      if (href.startsWith('#') && focusScheduleForm(href, dateStr)) {
        e.preventDefault();
        hideDayMenu();
      }
    });
  }

  document.addEventListener('click', (e) => {
    if (!dayMenuEl || dayMenuEl.hidden) return;
    if (e.target.closest('#apptCalDayMenu')) return;
    hideDayMenu();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') hideDayMenu();
  });

  const renderCalendar = () => {
    const today = new Date();
    const firstDow = (new Date(year, month, 1).getDay() + 6) % 7;
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    selectedDay = Math.min(selectedDay, daysInMonth);

    if (yearLabel) yearLabel.textContent = String(year);
    if (monthTitle) monthTitle.textContent = (data.months[month] || '').toUpperCase();
    monthButtons.forEach((btn) => {
      btn.classList.toggle('is-active', parseInt(btn.dataset.month, 10) === month);
    });
    renderMonthCounts();

    let html = '';
    for (let i = 0; i < firstDow; i += 1) {
      html += '<span class="appt-cal-day is-empty" aria-hidden="true"></span>';
    }
    for (let d = 1; d <= daysInMonth; d += 1) {
      const key = dateKey(year, month, d);
      const items = byDate[key] || [];
      const tone = items.length ? pickDayTone(items) : '';
      const isToday =
        today.getFullYear() === year && today.getMonth() === month && today.getDate() === d;
      const isSelected = d === selectedDay;
      const colIndex = (firstDow + d - 1) % 7;
      const classes = [
        'appt-cal-day',
        items.length ? 'has-appt' : '',
        tone ? `tone-${tone}` : '',
        isToday ? 'is-today' : '',
        isSelected ? 'is-selected' : '',
      ]
        .filter(Boolean)
        .join(' ');
      const calDateUrl = esc(buildCalDateUrl(year, month, d));
      html += `<div class="${classes}" data-day="${d}" data-date="${dateKey(year, month, d)}" data-col="${colIndex}"><a href="${calDateUrl}" class="appt-cal-day-select" aria-label="${d}${items.length ? `, ${items.length} appointment${items.length > 1 ? 's' : ''}` : ''}"><span class="appt-cal-day-num">${d}</span></a>${renderDayEvents(items)}</div>`;
    }
    daysEl.innerHTML = html;

    updateDaySelection();
    renderAgenda();
    hideDayMenu();
  };

  root.querySelectorAll('[data-cal-nav]').forEach((btn) => {
    btn.addEventListener('click', () => {
      year += parseInt(btn.dataset.dir, 10);
      agendaPage = 1;
      renderCalendar();
      syncCalDateUrl(year, month, selectedDay);
    });
  });

  monthButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      month = parseInt(btn.dataset.month, 10);
      const daysInMonth = new Date(year, month + 1, 0).getDate();
      selectedDay = Math.min(selectedDay, daysInMonth);
      // Prefer first day in this month that has appointments
      const prefix = `${year}-${String(month + 1).padStart(2, '0')}-`;
      const dayWithAppt = Object.keys(byDate)
        .filter((key) => key.startsWith(prefix))
        .sort()[0];
      if (dayWithAppt) {
        selectedDay = parseInt(dayWithAppt.slice(8, 10), 10) || selectedDay;
      }
      agendaPage = 1;
      renderCalendar();
      syncCalDateUrl(year, month, selectedDay);
    });
  });

  const upcomingStatuses = new Set(['scheduled', 'confirmed', 'rescheduled', 'pending', 'accepted']);
  const nowMs = Date.now();
  const upcoming = items
    .filter((item) => {
      if (!upcomingStatuses.has(item.status)) return false;
      const dt = new Date(String(item.scheduledAt).replace(' ', 'T'));
      return !Number.isNaN(dt.getTime()) && dt.getTime() >= nowMs;
    })
    .sort((a, b) => String(a.scheduledAt).localeCompare(String(b.scheduledAt)))[0];

  const urlHasCalDate = !!(urlCalDate && /^\d{4}-\d{2}-\d{2}$/.test(urlCalDate));
  const selectedKey = dateKey(year, month, selectedDay);
  const selectedHasAppts = !!(byDate[selectedKey] && byDate[selectedKey].length);

  // Stale cal_date with no appointments: jump to next upcoming
  if (upcoming && (!urlHasCalDate || !selectedHasAppts)) {
    const key = apptDateKey(upcoming.scheduledAt);
    if (key) {
      const [y, m, d] = key.split('-').map((n) => parseInt(n, 10));
      year = y;
      month = m - 1;
      selectedDay = d;
    }
  }

  root.dataset.year = String(year);
  root.dataset.month = String(month);
  root.dataset.day = String(selectedDay);

  renderCalendar();
  syncCalDateUrl(year, month, selectedDay);
  } catch (err) {
    console.error('Appointment calendar init failed', err);
    // Last resort: keep PHP-rendered markup visible
  } finally {
    root.dataset.calReady = '1';
  }
}
