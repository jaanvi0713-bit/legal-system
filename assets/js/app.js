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

  const accountMenu = document.querySelector('.topbar-account');
  if (accountMenu) {
    document.addEventListener('click', (e) => {
      if (!accountMenu.open) return;
      if (!accountMenu.contains(e.target)) accountMenu.open = false;
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && accountMenu.open) accountMenu.open = false;
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
  const data = window.LEXORA_APPT_CAL;
  if (!root || !data) return;

  const daysEl = document.getElementById('apptCalDays');
  const agendaEl = document.getElementById('apptCalAgenda');
  const yearLabel = document.getElementById('apptCalYear');
  const monthTitle = document.getElementById('apptCalMonthTitle');
  const monthButtons = root.querySelectorAll('.appt-cal-month-btn');
  const monthCounts = root.querySelectorAll('[data-month-count]');

  let year = parseInt(root.dataset.year, 10) || new Date().getFullYear();
  let month = parseInt(root.dataset.month, 10) || new Date().getMonth();
  let selectedDay = parseInt(root.dataset.day, 10) || new Date().getDate();

  const esc = (value) =>
    String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');

  const tonePriority = {
    cancelled: 6,
    completed: 5,
    past: 4,
    confirmed: 3,
    rescheduled: 2,
    scheduled: 1,
  };

  const dateKey = (y, m, d) =>
    `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;

  const apptDateKey = (iso) => {
    const dt = new Date(iso.replace(' ', 'T'));
    if (Number.isNaN(dt.getTime())) return '';
    return dateKey(dt.getFullYear(), dt.getMonth(), dt.getDate());
  };

  const byDate = {};
  (data.items || []).forEach((item) => {
    const key = apptDateKey(item.scheduledAt);
    if (!key) return;
    if (!byDate[key]) byDate[key] = [];
    byDate[key].push(item);
  });

  Object.keys(byDate).forEach((key) => {
    byDate[key].sort((a, b) => new Date(a.scheduledAt) - new Date(b.scheduledAt));
  });

  const monthCountForYear = (y) => {
    const counts = Array(12).fill(0);
    (data.items || []).forEach((item) => {
      const dt = new Date(item.scheduledAt.replace(' ', 'T'));
      if (Number.isNaN(dt.getTime()) || dt.getFullYear() !== y) return;
      counts[dt.getMonth()] += 1;
    });
    return counts;
  };

  const formatAgendaWhen = (iso) => {
    const dt = new Date(iso.replace(' ', 'T'));
    if (Number.isNaN(dt.getTime())) return iso;
    return new Intl.DateTimeFormat(data.locale || undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    }).format(dt);
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

  const badgeClass = (tone) => {
    return `appt-cal-status tone-${tone || 'scheduled'}`;
  };

  const monthItems = () => {
    const prefix = `${year}-${String(month + 1).padStart(2, '0')}-`;
    return (data.items || [])
      .filter((item) => apptDateKey(item.scheduledAt).startsWith(prefix))
      .sort((a, b) => new Date(a.scheduledAt) - new Date(b.scheduledAt));
  };

  const renderAgenda = () => {
    if (!agendaEl) return;
    // Match mock: agenda lists the month's appointments (not only the selected day)
    const items = monthItems();
    if (!items.length) {
      agendaEl.innerHTML = `<div class="appt-cal-empty">${esc(data.emptyMonth || data.emptyDay || 'No appointments this month.')}</div>`;
      return;
    }
    agendaEl.innerHTML = items
      .map(
        (item) => `
      <article class="appt-cal-agenda-card tone-${esc(item.tone)}${apptDateKey(item.scheduledAt) === dateKey(year, month, selectedDay) ? ' is-active-day' : ''}">
        <a href="${esc(item.editUrl)}">
          <div class="appt-cal-agenda-when">
            <span class="appt-cal-agenda-when-text">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
              ${esc(formatAgendaWhen(item.scheduledAt))}
            </span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></svg>
          </div>
          <strong>${esc(item.caseLabel || item.title)}</strong>
          <div class="appt-cal-person">${esc(item.client || item.lawyer || '')}</div>
          <span class="${badgeClass(item.tone)}">${esc(item.statusLabel)}</span>
        </a>
      </article>`
      )
      .join('');
  };

  const renderMonthCounts = () => {
    const counts = monthCountForYear(year);
    monthCounts.forEach((el) => {
      const idx = parseInt(el.dataset.monthCount, 10);
      el.textContent = String(counts[idx] || 0);
    });
  };

  const renderCalendar = () => {
    if (!daysEl) return;
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
      html += `<button type="button" class="${classes}" data-day="${d}" data-col="${colIndex}" aria-label="${d}"><span class="appt-cal-day-num">${d}</span></button>`;
    }
    daysEl.innerHTML = html;

    // Column highlight follows today when viewing current month, else selected day
    let highlightDay = selectedDay;
    if (today.getFullYear() === year && today.getMonth() === month) {
      highlightDay = today.getDate();
    }
    const highlightBtn = daysEl.querySelector(`[data-day="${highlightDay}"]`);
    const colIndex = highlightBtn ? parseInt(highlightBtn.dataset.col, 10) : 0;
    daysEl.classList.toggle('has-col-highlight', !!highlightBtn);
    daysEl.style.setProperty('--col-index', String(colIndex));

    daysEl.querySelectorAll('.appt-cal-day:not(.is-empty)').forEach((btn) => {
      btn.addEventListener('click', () => {
        selectedDay = parseInt(btn.dataset.day, 10);
        renderCalendar();
        renderAgenda();
      });
    });

    renderAgenda();
  };

  root.querySelectorAll('[data-cal-nav]').forEach((btn) => {
    btn.addEventListener('click', () => {
      year += parseInt(btn.dataset.dir, 10);
      renderCalendar();
    });
  });

  monthButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      month = parseInt(btn.dataset.month, 10);
      renderCalendar();
    });
  });

  const upcoming = (data.items || [])
    .filter((item) => {
      const dt = new Date(item.scheduledAt.replace(' ', 'T'));
      return dt >= new Date() && ['pending', 'accepted'].includes(item.status);
    })
    .sort((a, b) => new Date(a.scheduledAt) - new Date(b.scheduledAt))[0];

  // Open the month that has upcoming work, but keep "today" selected when possible
  // (matches mock: today highlighted, agenda still lists month appointments)
  if (upcoming) {
    const dt = new Date(upcoming.scheduledAt.replace(' ', 'T'));
    const now = new Date();
    year = dt.getFullYear();
    month = dt.getMonth();
    if (now.getFullYear() === year && now.getMonth() === month) {
      selectedDay = now.getDate();
    } else {
      selectedDay = dt.getDate();
    }
  }

  renderCalendar();
}
