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
        ['chartCases', 'chartRevenue', 'chartTypes', 'chartStatus', 'chartWeekdays'].forEach((id) => {
          const el = document.getElementById(id);
          if (!el) return;
          const existing = Chart.getChart(el);
          if (existing) existing.destroy();
        });
        initDashboardCharts();
      }
    });
  }

  document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('click', (e) => {
      if (!confirm(el.getAttribute('data-confirm') || 'Are you sure?')) {
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
      thinking.innerHTML = '<div class="ai-bot-mark sm" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="7" width="14" height="11" rx="3"/><circle cx="9.5" cy="12" r="1.2" fill="currentColor" stroke="none"/><circle cx="14.5" cy="12" r="1.2" fill="currentColor" stroke="none"/><path d="M9 18v2M15 18v2M12 4v3"/></svg></div><div>Thinking…</div>';
      messages.appendChild(thinking);
      const thinkingText = thinking.querySelector('div:last-child');

      try {
        const res = await fetch('../api/ai-chat.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ session_id: Number(sessionId), message: value }),
        });
        const data = await res.json();
        thinkingText.textContent = data.reply || data.error || 'No response.';
      } catch (err) {
        thinkingText.textContent = 'Unable to reach the AI service. Please try again.';
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
});

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
      alert('File attachments will be available when document AI is enabled. For now, describe the file in your question.');
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
  if (!data || typeof Chart === 'undefined') return;

  Chart.defaults.font.family = '"Montserrat", "Segoe UI", sans-serif';
  Chart.defaults.font.size = 11;
  Chart.defaults.color = cssVar('--ink-soft', '#718096');

  const gridColor = () => cssVar('--chart-grid', 'rgba(26,32,44,0.06)');
  const inkSoft = () => cssVar('--ink-soft', '#718096');
  const blue = () => cssVar('--blue', '#1e3a6e');
  const purple = () => cssVar('--purple', '#5b4b8a');
  const magenta = () => cssVar('--purple-bright', '#6d5a9e');
  const primary = () => cssVar('--primary', '#1e3a6e');

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

  const casesEl = document.getElementById('chartCases');
  if (casesEl) {
    new Chart(casesEl, {
      type: 'line',
      data: {
        labels: data.months,
        datasets: [
          {
            label: 'Cases opened',
            data: data.opened,
            borderColor: blue(),
            backgroundColor: 'rgba(37, 99, 235, 0.16)',
            fill: true,
            borderWidth: 3,
            tension: 0.4,
            pointRadius: 3,
            pointBackgroundColor: blue(),
          },
          {
            label: 'Cases closed',
            data: data.closed,
            borderColor: purple(),
            backgroundColor: 'rgba(124, 58, 237, 0.14)',
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
          label: 'Revenue',
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

  const typeColors = [blue(), purple(), magenta(), primary(), '#3a4d7a'];
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
        labels: ['Active', 'Closed'],
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
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
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
