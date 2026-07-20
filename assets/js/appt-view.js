(function () {
  'use strict';

  function getCalData() {
    if (window.LEXORA_APPT_CAL && typeof window.LEXORA_APPT_CAL === 'object') {
      return window.LEXORA_APPT_CAL;
    }
    var el = document.getElementById('apptCalData');
    if (!el) return { items: [], itemsById: {}, emDash: '—', locale: document.documentElement.lang || 'en' };
    try {
      return JSON.parse(el.textContent);
    } catch (err) {
      console.error('Appointment data parse failed', err);
      return { items: [], itemsById: {}, emDash: '—', locale: document.documentElement.lang || 'en' };
    }
  }

  function findItem(id) {
    id = parseInt(id, 10);
    if (!id) return null;
    var data = getCalData();
    var map = data.itemsById || {};
    if (map[id] || map[String(id)]) return map[id] || map[String(id)];
    var items = data.items || [];
    for (var i = 0; i < items.length; i += 1) {
      if (parseInt(items[i].id, 10) === id) return items[i];
    }
    return null;
  }

  function parseApptDate(iso) {
    return new Date(String(iso || '').replace(' ', 'T'));
  }

  function formatApptRange(iso, durationMinutes, locale, emDash) {
    var start = parseApptDate(iso);
    if (Number.isNaN(start.getTime())) return iso || emDash;
    var end = new Date(start.getTime() + (durationMinutes || 60) * 60000);
    var fmt = new Intl.DateTimeFormat('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
    });
    return fmt.format(start) + ' — ' + fmt.format(end);
  }

  function toIcsDate(dt) {
    if (Number.isNaN(dt.getTime())) return '';
    function pad(n) {
      return String(n).padStart(2, '0');
    }
    return (
      dt.getFullYear() +
      pad(dt.getMonth() + 1) +
      pad(dt.getDate()) +
      'T' +
      pad(dt.getHours()) +
      pad(dt.getMinutes()) +
      '00'
    );
  }

  function buildGoogleUrl(item) {
    var start = parseApptDate(item.scheduledAt);
    if (Number.isNaN(start.getTime())) return '#';
    var end = new Date(start.getTime() + (item.durationMinutes || 60) * 60000);
    var params = new URLSearchParams({
      action: 'TEMPLATE',
      text: item.title || 'Appointment',
      dates: toIcsDate(start) + '/' + toIcsDate(end),
      details: item.description || '',
      location: item.location || '',
    });
    return 'https://calendar.google.com/calendar/render?' + params.toString();
  }

  function buildOutlookUrl(item) {
    var start = parseApptDate(item.scheduledAt);
    if (Number.isNaN(start.getTime())) return '#';
    var end = new Date(start.getTime() + (item.durationMinutes || 60) * 60000);
    var params = new URLSearchParams({
      path: '/calendar/action/compose',
      rru: 'addevent',
      subject: item.title || 'Appointment',
      startdt: start.toISOString(),
      enddt: end.toISOString(),
      body: item.description || '',
      location: item.location || '',
    });
    return 'https://outlook.live.com/calendar/0/deeplink/compose?' + params.toString();
  }

  function buildIcsBlob(item) {
    var start = parseApptDate(item.scheduledAt);
    if (Number.isNaN(start.getTime())) return null;
    var end = new Date(start.getTime() + (item.durationMinutes || 60) * 60000);
    var ics = [
      'BEGIN:VCALENDAR',
      'VERSION:2.0',
      'PRODID:-//Legal Pro//Appointments//EN',
      'BEGIN:VEVENT',
      'UID:appt-' + item.id + '@legal-system',
      'DTSTAMP:' + toIcsDate(new Date()),
      'DTSTART:' + toIcsDate(start),
      'DTEND:' + toIcsDate(end),
      'SUMMARY:' + String(item.title || '').replace(/\n/g, ' '),
      'DESCRIPTION:' + String(item.description || '').replace(/\n/g, '\\n'),
      'LOCATION:' + String(item.location || '').replace(/\n/g, ' '),
      'END:VEVENT',
      'END:VCALENDAR',
    ].join('\r\n');
    return URL.createObjectURL(new Blob([ics], { type: 'text/calendar;charset=utf-8' }));
  }

  var icsObjectUrl = null;
  var bound = false;

  function closeModal() {
    var modal = document.getElementById('apptViewModal');
    if (!modal) return;
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('appt-modal-open');
    if (icsObjectUrl) {
      URL.revokeObjectURL(icsObjectUrl);
      icsObjectUrl = null;
    }
  }

  function openModal(item) {
    if (!item) return false;
    var modal = document.getElementById('apptViewModal');
    if (!modal) return false;

    if (modal.parentElement !== document.body) {
      document.body.appendChild(modal);
    }

    var data = getCalData();
    var emDash = data.emDash || '—';
    var locale = data.locale || document.documentElement.lang || 'en';

    var setText = function (id, value) {
      var el = document.getElementById(id);
      if (el) el.textContent = value;
    };

    setText('apptViewTitle', item.title || '');
    setText('apptViewClient', item.client || emDash);
    setText('apptViewCase', item.caseLabel || emDash);
    setText('apptViewWhen', formatApptRange(item.scheduledAt, item.durationMinutes, locale, emDash));
    setText('apptViewLocation', item.location || emDash);
    setText('apptViewStatus', item.statusLabel || item.status || emDash);
    setText('apptViewNotes', item.description || emDash);

    function setOptionalRow(rowId, valueId, value) {
      var row = document.getElementById(rowId);
      var has = !!(value && String(value).trim());
      if (row) {
        if (has) {
          row.hidden = false;
          row.removeAttribute('hidden');
        } else {
          row.hidden = true;
          row.setAttribute('hidden', '');
        }
      }
      if (has) setText(valueId, value);
    }

    setOptionalRow('apptViewLawyerRow', 'apptViewLawyer', item.lawyer);
    setOptionalRow('apptViewHearingTypeRow', 'apptViewHearingType', item.hearingType);
    setOptionalRow('apptViewJudgeRow', 'apptViewJudge', item.judge);
    setOptionalRow('apptViewOutcomeRow', 'apptViewOutcome', item.outcome);

    var clientLabel = document.getElementById('apptViewClientLabel');
    if (clientLabel && data.fieldClient) {
      clientLabel.textContent = data.fieldClient;
    }

    var google = document.getElementById('apptViewGoogle');
    var outlook = document.getElementById('apptViewOutlook');
    var ics = document.getElementById('apptViewIcs');
    if (google) google.href = buildGoogleUrl(item);
    if (outlook) outlook.href = buildOutlookUrl(item);
    if (ics) {
      if (icsObjectUrl) URL.revokeObjectURL(icsObjectUrl);
      icsObjectUrl = buildIcsBlob(item);
      ics.href = icsObjectUrl || '#';
      ics.download =
        String(item.title || 'appointment')
          .replace(/[^\w\-]+/g, '-')
          .toLowerCase() + '.ics';
    }

    modal.hidden = false;
    modal.removeAttribute('hidden');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('appt-modal-open');
    var dialog = modal.querySelector('.appt-view-dialog');
    if (dialog) dialog.focus();
    return true;
  }

  function viewAppointment(id, evt) {
    if (evt) {
      evt.preventDefault();
      evt.stopPropagation();
    }
    var item = findItem(id);
    if (item) {
      openModal(item);
      return false;
    }
    id = parseInt(id, 10);
    if (id) {
      var href = '?view=' + id;
      var btn = evt && evt.target && evt.target.closest ? evt.target.closest('[data-appt-view], a[href]') : null;
      if (btn && btn.getAttribute('href') && btn.getAttribute('href') !== '#') {
        href = btn.getAttribute('href');
      }
      window.location.href = href;
    }
    return false;
  }

  function bindOnce() {
    if (bound) return;
    bound = true;

    var modal = document.getElementById('apptViewModal');
    if (modal) {
      if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
      }
      modal.querySelectorAll('[data-appt-close]').forEach(function (el) {
        el.addEventListener('click', function (e) {
          e.preventDefault();
          closeModal();
        });
      });
    }

    document.addEventListener(
      'click',
      function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-appt-view]') : null;
        if (!btn) return;
        viewAppointment(btn.getAttribute('data-appt-view'), e);
      },
      true
    );

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeModal();
    });

    var params = new URLSearchParams(window.location.search);
    var viewId = params.get('view');
    if (viewId) {
      viewAppointment(viewId);
    }
  }

  window.lexoraViewAppointment = viewAppointment;
  window.lexoraOpenApptModal = openModal;
  window.lexoraCloseApptModal = closeModal;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindOnce);
  } else {
    bindOnce();
  }
})();
