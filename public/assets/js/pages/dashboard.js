/* GO-LIVE-STATUS: ready — Dashboard JS. Données démo en fallback, API /api/v1/dashboard. */
/**
 * Dashboard page — loads KPIs and task data from the API.
 * Degrades gracefully: shows static wireframe data if API unavailable.
 */
(function () {
  'use strict';

  function escapeHtml(s) {
    if (!s) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  /* ── Helpers ──────────────────────────────────────────── */

  function renderSeanceRow(s) {
    var statusColors = {
      'draft': 'var(--color-text-muted)',
      'convocations': 'var(--color-warning)',
      'in_progress': 'var(--color-danger)',
      'closed': 'var(--color-success)',
      'pv_sent': 'var(--color-success)',
      'archived': 'var(--color-text-muted)'
    };
    var statusLabels = {
      'draft': 'Brouillon',
      'convocations': 'Convocations',
      'in_progress': 'En cours',
      'closed': 'Termin\u00e9e',
      'pv_sent': 'PV envoy\u00e9',
      'archived': 'Archiv\u00e9e'
    };
    var color = statusColors[s.status] || 'var(--color-text-muted)';
    var label = statusLabels[s.status] || s.status;
    var title = escapeHtml(s.title || 'S\u00e9ance');
    var date = escapeHtml(s.date_time || '');
    var participants = s.participant_count || 0;
    var motions = s.motion_count || 0;

    return '<div class="session-row" onclick="location.href=\'/operator.htmx.html?meeting_id=' + encodeURIComponent(s.id) + '\'">' +
      '<div class="session-dot" style="background:' + color + ';"></div>' +
      '<div class="session-row-info">' +
        '<div class="session-row-title">' + title + '</div>' +
        '<div class="session-row-meta">' + date + ' &mdash; ' + participants + ' participants &mdash; ' + motions + ' r\u00e9solutions</div>' +
      '</div>' +
      '<span class="tag" style="color:' + color + ';">' + escapeHtml(label) + '</span>' +
    '</div>';
  }

  function renderTaskRow(t) {
    var priorityAttr = t.priority ? ' data-priority="' + escapeHtml(t.priority) + '"' : '';
    return '<div class="task-row"' + priorityAttr + '>' +
      '<div class="task-row-info">' +
        '<div class="task-row-title">' + escapeHtml(t.title) + '</div>' +
        '<div class="task-row-sub">' + escapeHtml(t.sub) + '</div>' +
      '</div>' +
      '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--color-border)" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>' +
    '</div>';
  }

  /* ── Load data ───────────────────────────────────────── */

  function loadDashboard() {
    var api = (typeof Utils !== 'undefined' && Utils.apiGet) ? Utils.apiGet : null;
    if (!api) {
      showFallback();
      return;
    }

    api('/api/v1/dashboard')
      .then(function (data) {
        // KPIs
        var el;
        el = document.getElementById('kpiSeances');
        if (el) el.textContent = data.upcoming_count || 0;
        el = document.getElementById('kpiEnCours');
        if (el) el.textContent = data.live_count || 0;
        el = document.getElementById('kpiConvoc');
        if (el) el.textContent = data.pending_invitations || 0;
        el = document.getElementById('kpiPV');
        if (el) el.textContent = data.pending_pv || 0;

        // Urgent action
        if (data.urgent_action) {
          var ua = data.urgent_action;
          var uTitle = document.getElementById('urgentTitle');
          var uSub = document.getElementById('urgentSub');
          if (uTitle) uTitle.textContent = ua.title || 'Action requise';
          if (uSub) uSub.textContent = ua.subtitle || '';
        } else {
          var urgentCard = document.getElementById('actionUrgente');
          if (urgentCard) urgentCard.hidden = true;
        }

        // Prochaines s\u00e9ances
        var prochaines = document.getElementById('prochaines');
        if (prochaines && data.upcoming_meetings) {
          var html = '';
          data.upcoming_meetings.forEach(function (s, i) {
            html += renderSeanceRow(s);
          });
          prochaines.innerHTML = html || '<div class="session-row empty-state"><span class="session-row-meta">Aucune s\u00e9ance \u00e0 venir</span></div>';
        }

        // T\u00e2ches
        var taches = document.getElementById('taches');
        if (taches && data.tasks) {
          var tHtml = '';
          data.tasks.forEach(function (t, i) {
            tHtml += renderTaskRow(t);
          });
          taches.innerHTML = tHtml || '<div class="task-row empty-state"><span class="task-row-sub">Aucune t\u00e2che</span></div>';
        }
      })
      .catch(function () {
        showFallback();
      });
  }

  /* ── Fallback (wireframe demo data) ─────────────────── */

  function showFallback() {
    var el;
    el = document.getElementById('kpiSeances');
    if (el) el.textContent = '3';
    el = document.getElementById('kpiEnCours');
    if (el) el.textContent = '1';
    el = document.getElementById('kpiConvoc');
    if (el) el.textContent = '12';
    el = document.getElementById('kpiPV');
    if (el) el.textContent = '3';

    var urgentTitle = document.getElementById('urgentTitle');
    var urgentSub = document.getElementById('urgentSub');
    if (urgentTitle) urgentTitle.textContent = 'Envoyer 12 convocations \u2014 AG Ordinaire';
    if (urgentSub) urgentSub.textContent = 'Date limite : 22/02/2026 (art. 9 d\u00e9cret 1967 \u2014 21 jours avant l\u2019AG)';

    var prochaines = document.getElementById('prochaines');
    if (prochaines) {
      var meetings = [
        { title: 'AG Ordinaire', date_time: '18/02/2026', status: 'convocations', participant_count: 45, motion_count: 8, id: 1 },
        { title: 'AG Copropri\u00e9t\u00e9 B', date_time: '15/02/2026', status: 'in_progress', participant_count: 67, motion_count: 5, id: 2 },
        { title: 'Conseil syndical', date_time: '10/02/2026', status: 'pv_sent', participant_count: 12, motion_count: 5, id: 3 }
      ];
      var html = '';
      meetings.forEach(function (s, i) {
        html += renderSeanceRow(s);
      });
      prochaines.innerHTML = html;
    }

    var taches = document.getElementById('taches');
    if (taches) {
      var tasks = [
        { title: 'Envoyer 12 convocations', sub: 'S\u00e9ance A \u2014 J-3' },
        { title: 'V\u00e9rifier procurations re\u00e7ues', sub: 'S\u00e9ance A \u2014 3 re\u00e7ues' },
        { title: 'Compl\u00e9ter ordre du jour', sub: 'S\u00e9ance B \u2014 2 r\u00e9sol. sans majorit\u00e9' },
        { title: 'Envoyer PV derni\u00e8re s\u00e9ance', sub: 'Vote termin\u00e9 le 10/02' }
      ];
      var tHtml = '';
      tasks.forEach(function (t, i) {
        tHtml += renderTaskRow(t);
      });
      taches.innerHTML = tHtml;
    }
  }

  /* ── Init ────────────────────────────────────────────── */

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadDashboard);
  } else {
    loadDashboard();
  }
})();
