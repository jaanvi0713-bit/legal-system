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
    aiForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const input = document.getElementById('ai-message');
      const messages = document.getElementById('ai-messages');
      const sessionId = aiForm.dataset.sessionId;
      const text = (input.value || '').trim();
      if (!text) return;

      const userMsg = document.createElement('div');
      userMsg.className = 'msg msg-user';
      userMsg.textContent = text;
      messages.appendChild(userMsg);
      input.value = '';
      messages.scrollTop = messages.scrollHeight;

      const thinking = document.createElement('div');
      thinking.className = 'msg msg-assistant';
      thinking.textContent = 'Thinking…';
      messages.appendChild(thinking);

      try {
        const res = await fetch('../api/ai-chat.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ session_id: Number(sessionId), message: text }),
        });
        const data = await res.json();
        thinking.textContent = data.reply || data.error || 'No response.';
      } catch (err) {
        thinking.textContent = 'Unable to reach the AI service. Please try again.';
      }
      messages.scrollTop = messages.scrollHeight;
    });
  }

  initDashboardCharts();
});

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
  const blue = () => cssVar('--blue', '#0f3a6d');
  const purple = () => cssVar('--blue-bright', '#1a5a9c');
  const magenta = () => cssVar('--primary-deep', '#0a2a52');
  const primary = () => cssVar('--primary', '#0f3a6d');

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

  const typeColors = [blue(), purple(), magenta(), primary(), '#123a5c'];
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
