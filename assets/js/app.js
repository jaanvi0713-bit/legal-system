document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.nav-toggle');
  if (toggle) {
    toggle.addEventListener('click', () => {
      document.body.classList.toggle('sidebar-open');
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
});
