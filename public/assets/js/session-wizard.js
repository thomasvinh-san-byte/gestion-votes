/**
 * session-wizard.js — Wizard de séance AG-VOTE.
 *
 * Couche intermédiaire entre les pages et l'API :
 *   - État centralisé dans localStorage
 *   - Barre de progression injectée dans le sidebar
 *   - Garde-fous par page (prérequis)
 *   - Polling léger pour notifications inter-pages
 *
 * Chargé APRÈS utils.js, shared.js, shell.js, auth-ui.js.
 */
(function () {
  'use strict';

  // =========================================================================
  // ÉTAPES DU WIZARD
  // =========================================================================

  var STEPS = [
    { id: 'conduct',    num: 0, label: 'Conduite',      href: '/operator.htmx.html',    icon: '▶️', needsMeeting: false },
    { id: 'select',     num: 1, label: 'Séance',        href: '/meetings.htmx.html',    icon: '📋', needsMeeting: false },
    { id: 'members',    num: 2, label: 'Membres',       href: '/members.htmx.html',     icon: '👤', needsMeeting: false },
    { id: 'motions',    num: 3, label: 'Résolutions',   href: '/motions.htmx.html',     icon: '📝', needsMeeting: true },
    { id: 'attendance', num: 4, label: 'Présences',     href: '/attendance.htmx.html',   icon: '👥', needsMeeting: true },
    { id: 'validate',   num: 5, label: 'Validation',    href: '/validate.htmx.html',     icon: '✅', needsMeeting: true },
    { id: 'archive',    num: 6, label: 'Archive',       href: '/archives.htmx.html',     icon: '📦', needsMeeting: false }
  ];

  // Map page paths to wizard step ids
  var PAGE_STEP_MAP = {
    '/meetings.htmx.html':   'select',
    '/members.htmx.html':    'members',
    '/motions.htmx.html':    'motions',
    '/attendance.htmx.html': 'attendance',
    '/operator.htmx.html':   'conduct',
    '/operator_flow.htmx.html': 'conduct',
    '/president.htmx.html':  'conduct',
    '/validate.htmx.html':   'validate',
    '/archives.htmx.html':   'archive'
  };

  var STORAGE_KEY = 'ag_vote_wizard';
  var POLL_INTERVAL = 5000; // 5 seconds
  var pollTimer = null;
  var lastPollData = null;

  // =========================================================================
  // STATE MANAGEMENT
  // =========================================================================

  function getState() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : {};
    } catch (e) { return {}; }
  }

  function setState(patch) {
    var s = getState();
    for (var k in patch) {
      if (patch.hasOwnProperty(k)) s[k] = patch[k];
    }
    s.updatedAt = Date.now();
    localStorage.setItem(STORAGE_KEY, JSON.stringify(s));
    return s;
  }

  function getMeetingId() {
    // Priority: URL > sessionStorage
    var params = new URLSearchParams(window.location.search);
    var urlId = params.get('meeting_id');
    if (urlId) {
      setState({ meetingId: urlId });
      return urlId;
    }
    return getState().meetingId || null;
  }

  // =========================================================================
  // CHECKS — détermine l'état de complétude
  // =========================================================================

  function computeStep(checks) {
    if (!checks) return 0;
    if (!checks.meetingId) return 0;
    if (!checks.hasMembers) return 1;
    if (!checks.hasMotions || !checks.policiesAssigned) return 2;
    if (!checks.hasAttendance || !checks.hasPresident) return 3;
    var status = checks.meetingStatus || '';
    if (status === 'live') {
      if (!checks.allMotionsClosed) return 4;
      return 5;
    }
    if (status === 'closed') return 5;
    if (status === 'validated' || status === 'archived') return 6;
    // draft/scheduled/frozen → still in prep
    if (status === 'draft') return 1;
    if (status === 'scheduled' || status === 'frozen') return 3;
    return 0;
  }

  // =========================================================================
  // PROGRESS BAR (injected into page)
  // =========================================================================

  function renderProgressBar() {
    var container = document.getElementById('wizard-progress');
    if (!container) {
      // Create and inject into .app-main before the .container, or top of main
      var main = document.querySelector('.app-main .container') || document.querySelector('.app-main') || document.querySelector('main');
      if (!main) return;
      container = document.createElement('div');
      container.id = 'wizard-progress';
      container.style.cssText = 'padding:12px 16px 0;';
      main.insertBefore(container, main.firstChild);
    }

    var state = getState();
    var checks = state.checks || {};
    var currentStep = computeStep(checks);
    var meetingId = state.meetingId || '';
    var mid = meetingId ? '?meeting_id=' + encodeURIComponent(meetingId) : '';

    // Determine current page step
    var pagePath = window.location.pathname;
    var pageStepId = PAGE_STEP_MAP[pagePath] || null;

    var html = '<div class="wizard-bar" style="display:flex;gap:2px;margin-bottom:12px;border-radius:8px;overflow:hidden;background:var(--color-bg-subtle,#f0f0f0);height:6px;">';
    for (var i = 0; i < STEPS.length; i++) {
      var step = STEPS[i];
      var isDone = step.num < currentStep;
      var isCurrent = step.num === currentStep;
      var isPage = step.id === pageStepId;
      var color = isDone ? 'var(--color-success,#22c55e)' :
                  (isCurrent ? 'var(--color-primary,#3b82f6)' :
                  'transparent');
      html += '<div style="flex:1;background:' + color + ';' +
              (isPage ? 'box-shadow:inset 0 -2px 0 var(--color-text,#333);' : '') +
              '"></div>';
    }
    html += '</div>';

    // Step labels row
    html += '<div class="wizard-steps" style="display:flex;gap:4px;font-size:11px;margin-bottom:8px;">';
    for (var j = 0; j < STEPS.length; j++) {
      var s = STEPS[j];
      var done = s.num < currentStep;
      var curr = s.num === currentStep;
      var active = s.id === pageStepId;
      var href = s.needsMeeting && !meetingId ? '#' : (s.href + (s.needsMeeting ? mid : ''));
      var weight = (active || curr) ? 'font-weight:700;' : '';
      var opacity = (done || curr || active) ? '' : 'opacity:0.4;';
      var icon = done ? '&#10003;' : s.icon;
      html += '<a href="' + href + '" style="flex:1;text-align:center;text-decoration:none;color:var(--color-text,#333);' + weight + opacity + '" title="' + s.label + '">' +
              '<span style="font-size:14px;">' + icon + '</span><br>' + s.label +
              '</a>';
    }
    html += '</div>';

    // Guard message if prerequisites missing
    var guardMsg = getGuardMessage(pageStepId, checks, state);
    if (guardMsg) {
      html += '<div style="padding:8px 12px;background:var(--color-warning-subtle,#fef3cd);border:1px solid var(--color-warning,#e8a73e);border-radius:6px;font-size:13px;margin-bottom:8px;">' +
              guardMsg + '</div>';
    }

    container.innerHTML = html;
  }

  function getGuardMessage(pageStepId, checks, state) {
    if (!pageStepId) return null;
    var mid = state.meetingId;
    var status = checks.meetingStatus || '';

    switch (pageStepId) {
      case 'motions':
        if (!mid) return '&#9888; Sélectionnez d\'abord une séance depuis <a href="/meetings.htmx.html">Séances</a>.';
        if (!checks.hasMembers) return '&#9888; Ajoutez des membres avant de créer des résolutions.';
        break;
      case 'attendance':
        if (!mid) return '&#9888; Sélectionnez d\'abord une séance.';
        if (!checks.hasPresident) return '&#9888; Assignez un président depuis <a href="/operator.htmx.html' + (mid ? '?meeting_id=' + mid : '') + '">Opérateur</a> avant de gérer les présences.';
        break;
      case 'conduct':
        if (!mid) return '&#9888; Sélectionnez une séance pour conduire les votes.';
        if (!checks.hasMotions) return '&#9888; Créez au moins une résolution avant de conduire la séance.';
        if (!checks.hasAttendance) return '&#9888; Pointez les présences avant d\'ouvrir la séance.';
        if (status === 'draft') return '&#9888; La séance est en brouillon. Planifiez-la pour avancer.';
        break;
      case 'validate':
        if (!mid) return '&#9888; Sélectionnez une séance.';
        if (status !== 'closed' && status !== 'validated') return '&#9888; La séance doit être clôturée avant validation.';
        if (!checks.allMotionsClosed) return '&#9888; Toutes les résolutions doivent être clôturées.';
        break;
    }
    return null;
  }

  // =========================================================================
  // POLLING — léger, met à jour l'état wizard
  // =========================================================================

  async function poll() {
    var meetingId = getMeetingId();
    if (!meetingId) return;

    try {
      var url = '/api/v1/wizard_status.php?meeting_id=' + encodeURIComponent(meetingId);
      var resp = await fetch(url, { credentials: 'same-origin' });
      if (!resp.ok) return;
      var data = await resp.json();
      if (!data.ok) return;

      var d = data.data;
      var checks = {
        meetingId: meetingId,
        meetingStatus: d.meeting_status || '',
        meetingTitle: d.meeting_title || '',
        hasMembers: (d.members_count || 0) > 0,
        hasMotions: (d.motions_total || 0) > 0,
        hasAttendance: (d.present_count || 0) > 0,
        hasPresident: !!d.has_president,
        quorumMet: !!d.quorum_met,
        policiesAssigned: !!d.policies_assigned,
        allMotionsClosed: d.motions_total > 0 && d.motions_closed === d.motions_total,
        currentMotionId: d.current_motion_id || null,
        presentCount: d.present_count || 0,
        motionsTotal: d.motions_total || 0,
        motionsClosed: d.motions_closed || 0
      };

      setState({ checks: checks, meetingId: meetingId });

      // Detect changes and notify
      if (lastPollData) {
        notifyChanges(lastPollData, checks);
      }
      lastPollData = checks;

      renderProgressBar();
    } catch (e) {
      // Silent fail — polling is best-effort
    }
  }

  // =========================================================================
  // NOTIFICATIONS — toast on state changes
  // =========================================================================

  function notifyChanges(prev, curr) {
    // Meeting status changed
    if (prev.meetingStatus && curr.meetingStatus && prev.meetingStatus !== curr.meetingStatus) {
      showToast('Séance passée en : ' + curr.meetingStatus);
    }
    // Motion opened
    if (!prev.currentMotionId && curr.currentMotionId) {
      showToast('Vote ouvert sur une résolution');
    }
    // Motion closed
    if (prev.currentMotionId && !curr.currentMotionId) {
      showToast('Vote clôturé');
    }
    // Quorum reached
    if (!prev.quorumMet && curr.quorumMet) {
      showToast('Quorum atteint !');
    }
  }

  function showToast(message) {
    var toast = document.createElement('div');
    toast.style.cssText =
      'position:fixed;bottom:20px;right:20px;z-index:9999;' +
      'background:var(--color-primary,#3b82f6);color:#fff;' +
      'padding:10px 20px;border-radius:8px;font-size:14px;' +
      'box-shadow:0 4px 12px rgba(0,0,0,0.15);' +
      'animation:slideIn 0.3s ease;max-width:350px;';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(function () {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity 0.3s';
      setTimeout(function () { toast.remove(); }, 300);
    }, 4000);
  }

  // =========================================================================
  // PUBLIC API
  // =========================================================================

  window.Wizard = {
    getState: getState,
    setState: setState,
    getMeetingId: getMeetingId,
    poll: poll,
    renderProgressBar: renderProgressBar,
    showToast: showToast,
    STEPS: STEPS,

    /** Called by meetings.js when a meeting is selected */
    selectMeeting: function (id, title) {
      setState({ meetingId: id, checks: { meetingId: id, meetingTitle: title || '' } });
      poll(); // immediate refresh
    },

    /** Called by meetings.js when a meeting is created */
    onMeetingCreated: function (id, title) {
      setState({ meetingId: id, checks: { meetingId: id, meetingTitle: title || '', meetingStatus: 'draft' } });
      poll();
    }
  };

  // =========================================================================
  // INIT
  // =========================================================================

  function init() {
    // Skip wizard on non-app pages
    var path = window.location.pathname;
    if (path === '/login.html' || path === '/index.html' || path === '/' ||
        path === '/public.htmx.html' || path === '/paper_redeem.htmx.html') {
      return;
    }

    // Wait for auth to be ready
    if (window.Auth && window.Auth.ready) {
      window.Auth.ready.then(function () {
        // Initial render with cached state
        renderProgressBar();
        // First poll
        poll();
        // Start polling
        pollTimer = setInterval(poll, POLL_INTERVAL);
      });
    } else {
      renderProgressBar();
      poll();
      pollTimer = setInterval(poll, POLL_INTERVAL);
    }
  }

  // Cleanup
  window.addEventListener('beforeunload', function () {
    if (pollTimer) clearInterval(pollTimer);
  });

  init();
})();
