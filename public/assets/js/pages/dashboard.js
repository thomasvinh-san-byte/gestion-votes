/* GO-LIVE-STATUS: ready — Dashboard JS. zero demo fallback — real API only. */
/**
 * Dashboard page — loads KPIs and session data from the API.
 * Renders status-aware session cards with lifecycle-specific CTAs.
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

  /* ── Status maps ──────────────────────────────────────── */

  var STATUS_CTA = {
    'draft':     { label: 'Compl\u00e9ter \u2192',                   href: '/wizard.htmx.html',       live: false },
    'scheduled': { label: 'Enregistrer les pr\u00e9sences \u2192',   href: '/hub.htmx.html',          live: false },
    'frozen':    { label: 'Ouvrir la console \u2192',                href: '/operator.htmx.html',     live: false },
    'live':      { label: '\u25cf En cours \u2014 Rejoindre \u2192', href: '/operator.htmx.html',     live: true  },
    'paused':    { label: '\u25cf En cours \u2014 Rejoindre \u2192', href: '/operator.htmx.html',     live: true  },
    'closed':    { label: 'G\u00e9n\u00e9rer le PV \u2192',         href: '/postsession.htmx.html',  live: false },
    'validated': { label: 'Archiver \u2192',                         href: '/postsession.htmx.html',  live: false },
    'archived':  { label: null,                                       href: null,                      live: false }
  };

  var STATUS_COLORS = {
    'draft':     'var(--color-text-muted)',
    'scheduled': 'var(--color-warning)',
    'frozen':    'var(--color-primary)',
    'live':      'var(--color-success)',
    'paused':    'var(--color-success)',
    'closed':    'var(--color-text-muted)',
    'validated': 'var(--color-primary)',
    'archived':  'var(--color-text-muted)'
  };

  var STATUS_PRIORITY = ['live', 'paused', 'frozen', 'scheduled', 'draft', 'closed', 'validated', 'archived'];

  /* ── Card renderer ────────────────────────────────────── */

  function renderSessionCard(s) {
    var cta = STATUS_CTA[s.status] || STATUS_CTA['draft'];
    var href = cta.href ? cta.href + '?meeting_id=' + encodeURIComponent(s.id) : null;
    var isLive = cta.live;
    var isMuted = s.status === 'archived';
    var color = STATUS_COLORS[s.status] || 'var(--color-text-muted)';
    var dateStr = s.date_time || s.scheduled_at || '';
    if (dateStr) {
      try { dateStr = new Date(dateStr).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' }); }
      catch (e) { /* keep raw */ }
    }

    var h = '<div class="session-card' + (isLive ? ' session-card--live' : '') + (isMuted ? ' session-card--muted' : '') + '"';
    if (href && !isMuted) h += ' onclick="location.href=\'' + escapeHtml(href) + '\'"';
    h += '>';
    h += '<div class="session-card-status-dot" style="background:' + color + '"></div>';
    h += '<div class="session-card-info">';
    h += '<div class="session-card-title">' + escapeHtml(s.title || 'S\u00e9ance') + '</div>';
    h += '<div class="session-card-meta">' + escapeHtml(dateStr) + ' \u2014 ' + (s.participant_count || 0) + ' membres \u2014 ' + (s.motion_count || 0) + ' r\u00e9solutions</div>';
    h += '</div>';
    if (cta.label && href) {
      h += '<a class="btn btn-sm btn-secondary session-card-cta' + (isLive ? ' btn-success' : '') + '" href="' + escapeHtml(href) + '" onclick="event.stopPropagation()">';
      if (isLive) h += '<span class="pulse-dot" aria-hidden="true"></span>';
      h += escapeHtml(cta.label);
      h += '</a>';
    }
    h += '</div>';
    return h;
  }

  /* ── Load data ───────────────────────────────────────── */

  function loadDashboard() {
    var api = (typeof Utils !== 'undefined' && Utils.apiGet) ? Utils.apiGet : null;
    if (!api) {
      showDashboardError();
      return;
    }

    function tryLoad(attempt) {
      api('/api/v1/dashboard')
        .then(function (data) {
          if (!data || !data.ok) { showDashboardError(); return; }
          var d = data.data; // unwrap envelope
          var meetings = Array.isArray(d.meetings) ? d.meetings : [];

          // KPIs — computed from meetings array
          var live = meetings.filter(function (m) { return m.status === 'live' || m.status === 'paused'; });
          var upcoming = meetings.filter(function (m) {
            return m.status === 'draft' || m.status === 'scheduled' || m.status === 'frozen' ||
              (m.status !== 'live' && m.status !== 'paused' && m.status !== 'closed' &&
               m.status !== 'validated' && m.status !== 'archived' &&
               m.scheduled_at && new Date(m.scheduled_at) > new Date());
          });

          var el;
          el = document.getElementById('kpiSeances');
          if (el) el.textContent = upcoming.length;
          el = document.getElementById('kpiEnCours');
          if (el) el.textContent = live.length;
          el = document.getElementById('kpiConvoc');
          if (el) el.textContent = 0; // convocation data not in dashboard API — show 0
          el = document.getElementById('kpiPV');
          if (el) el.textContent = meetings.filter(function (m) { return m.status === 'closed' || m.status === 'validated'; }).length;

          // Urgent action — show if there's a live meeting
          var liveMeeting = live.length > 0 ? live[0] : null;
          if (liveMeeting) {
            var uTitle = document.getElementById('urgentTitle');
            var uSub = document.getElementById('urgentSub');
            if (uTitle) uTitle.textContent = 'S\u00e9ance en cours';
            if (uSub) uSub.textContent = liveMeeting.title || '';
          } else {
            var urgentCard = document.getElementById('actionUrgente');
            if (urgentCard) urgentCard.hidden = true;
          }

          // S\u00e9ances — all meetings sorted by lifecycle priority
          var prochaines = document.getElementById('prochaines');
          if (prochaines) {
            if (meetings.length === 0) {
              prochaines.innerHTML = '<ag-empty-state icon="meetings" title="Aucune s\u00e9ance" description="Cr\u00e9ez votre premi\u00e8re s\u00e9ance pour g\u00e9rer vos assembl\u00e9es g\u00e9n\u00e9rales." action-label="Nouvelle s\u00e9ance" action-href="/wizard.htmx.html"></ag-empty-state>';
            } else {
              var sorted = meetings.slice().sort(function (a, b) {
                var ai = STATUS_PRIORITY.indexOf(a.status);
                var bi = STATUS_PRIORITY.indexOf(b.status);
                return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi);
              });
              var html = '';
              sorted.slice(0, 8).forEach(function (s) { html += renderSessionCard(s); });
              prochaines.innerHTML = html;
            }
          }

          // T\u00e2ches — not in dashboard API, show empty state
          var taches = document.getElementById('taches');
          if (taches) {
            taches.innerHTML = '<ag-empty-state icon="generic" title="Aucune t\u00e2che en attente" description="Les t\u00e2ches automatiques appara\u00eetront ici."></ag-empty-state>';
          }
        })
        .catch(function () {
          if (attempt === 1) {
            setTimeout(function () { tryLoad(2); }, 2000);
          } else {
            showDashboardError();
          }
        });
    }

    tryLoad(1);
  }

  /* ── Error state ─────────────────────────────────────── */

  function showDashboardError() {
    if (window.Shared && Shared.showToast) {
      Shared.showToast('Impossible de charger le tableau de bord.', 'error');
    }
    var content = document.getElementById('main-content');
    if (content) {
      // Remove any existing banner to prevent duplicates on retry
      var existing = content.querySelector('.dashboard-error');
      if (existing) { existing.remove(); }

      var banner = document.createElement('div');
      banner.className = 'hub-error dashboard-error';
      banner.innerHTML =
        '<p style="margin:0 0 12px;">Impossible de charger les donn\u00e9es du tableau de bord.</p>' +
        '<button class="btn btn-primary" id="dashboardRetryBtn">R\u00e9essayer</button>';
      content.prepend(banner);
      var retryBtn = document.getElementById('dashboardRetryBtn');
      if (retryBtn) {
        retryBtn.addEventListener('click', function () {
          banner.remove();
          loadDashboard();
        });
      }
    }
  }

  /* ── Init ────────────────────────────────────────────── */

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadDashboard);
  } else {
    loadDashboard();
  }
})();
