/**
 * session-wizard.js — Wizard de séance AG-VOTE (Diligent Style).
 *
 * Couche intermédiaire entre les pages et l'API :
 *   - État centralisé dans localStorage
 *   - Barre de progression Diligent-style injectée dans la page
 *   - Garde-fous par page (prérequis)
 *   - Polling léger pour notifications inter-pages
 *
 * Chargé APRÈS utils.js, shared.js, shell.js, auth-ui.js.
 */
(function () {
  'use strict';

  // =========================================================================
  // ÉTAPES DU WIZARD (Diligent workflow)
  // =========================================================================

  var STEPS = [
    { id: 'select',     num: 0, label: 'Séance',        shortLabel: 'Séance',     href: '/meetings.htmx.html',    icon: '1', needsMeeting: false },
    { id: 'members',    num: 1, label: 'Membres',       shortLabel: 'Membres',    href: '/members.htmx.html',     icon: '2', needsMeeting: false },
    { id: 'attendance', num: 2, label: 'Présences',     shortLabel: 'Présences',  href: '/operator.htmx.html',    icon: '3', needsMeeting: true },
    { id: 'resolutions',num: 3, label: 'Résolutions',   shortLabel: 'Résolutions',href: '/operator.htmx.html',    icon: '4', needsMeeting: true },
    { id: 'conduct',    num: 4, label: 'Vote',          shortLabel: 'Vote',       href: '/operator.htmx.html',    icon: '5', needsMeeting: true },
    { id: 'validate',   num: 5, label: 'Validation',    shortLabel: 'Clôture',    href: '/operator.htmx.html',    icon: '6', needsMeeting: true }
  ];

  // Map page paths to wizard step ids
  var PAGE_STEP_MAP = {
    '/meetings.htmx.html':   'select',
    '/members.htmx.html':    'members',
    '/operator.htmx.html':   'conduct',
    '/operator_flow.htmx.html': 'conduct',
    '/president.htmx.html':  'conduct',
    '/validate.htmx.html':   'validate',
    '/archives.htmx.html':   'validate'
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
    // Step 0: Séance - Need to select/create a meeting
    if (!checks) return 0;
    if (!checks.meetingId) return 0;

    // Step 1: Membres - Need members in the system
    if (!checks.hasMembers) return 1;

    // Step 2: Présences - Need some attendance (president is optional for demo)
    if (!checks.hasAttendance) return 2;

    // Step 3: Résolutions - Need at least one motion
    if (!checks.hasMotions || !checks.policiesAssigned) return 3;

    var status = checks.meetingStatus || '';

    // Step 4: Vote - Meeting is live, conducting votes
    if (status === 'live') {
      if (!checks.allMotionsClosed) return 4;
      return 5; // All votes done, ready for validation
    }

    // Step 5: Validation/Clôture
    if (status === 'closed' || status === 'validated' || status === 'archived') return 5;

    // draft/scheduled/frozen → still preparing attendance/resolutions
    if (status === 'draft') return 2;
    if (status === 'scheduled' || status === 'frozen') return 3;

    return 0;
  }

  // =========================================================================
  // PROGRESS BAR - Diligent Style Stepper
  // =========================================================================

  // Inject wizard styles once
  function injectWizardStyles() {
    if (document.getElementById('wizard-styles')) return;
    var style = document.createElement('style');
    style.id = 'wizard-styles';
    style.textContent = `
      .wizard-container {
        background: var(--color-surface, #fff);
        border-bottom: 1px solid var(--color-border, #e5e7eb);
        padding: 1rem 1.5rem;
      }
      .wizard-stepper {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        max-width: 900px;
        margin: 0 auto;
      }
      .wizard-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        flex: 1;
        min-width: 60px;
        text-decoration: none;
        color: inherit;
      }
      .wizard-step:not(:last-child)::after {
        content: '';
        position: absolute;
        top: 16px;
        left: calc(50% + 20px);
        width: calc(100% - 40px);
        height: 2px;
        background: var(--color-border, #e5e7eb);
      }
      .wizard-step.done:not(:last-child)::after {
        background: var(--color-success, #22c55e);
      }
      .wizard-step.current:not(:last-child)::after {
        background: linear-gradient(90deg, var(--color-primary, #3b82f6) 50%, var(--color-border, #e5e7eb) 50%);
      }
      .wizard-step-circle {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        font-weight: 600;
        background: var(--color-bg-subtle, #f3f4f6);
        border: 2px solid var(--color-border, #e5e7eb);
        color: var(--color-text-muted, #6b7280);
        position: relative;
        z-index: 1;
        transition: all 0.2s;
      }
      .wizard-step.done .wizard-step-circle {
        background: var(--color-success, #22c55e);
        border-color: var(--color-success, #22c55e);
        color: #fff;
      }
      .wizard-step.current .wizard-step-circle {
        background: var(--color-primary, #3b82f6);
        border-color: var(--color-primary, #3b82f6);
        color: #fff;
        box-shadow: 0 0 0 4px var(--color-primary-subtle, rgba(59,130,246,0.2));
      }
      .wizard-step:hover .wizard-step-circle {
        transform: scale(1.05);
      }
      .wizard-step-label {
        margin-top: 0.5rem;
        font-size: 0.65rem;
        font-weight: 500;
        color: var(--color-text-muted, #6b7280);
        text-align: center;
        white-space: nowrap;
        letter-spacing: 0.02em;
      }
      .wizard-step.done .wizard-step-label,
      .wizard-step.current .wizard-step-label {
        color: var(--color-text, #1f2937);
        font-weight: 600;
      }
      .wizard-meeting-info {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid var(--color-border, #e5e7eb);
        font-size: 0.85rem;
      }
      .wizard-meeting-title {
        font-weight: 600;
        color: var(--color-text, #1f2937);
      }
      .wizard-meeting-status {
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 500;
      }
      .wizard-guard-message {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1rem;
        margin-top: 0.75rem;
        background: var(--color-warning-subtle, #fef3cd);
        border: 1px solid var(--color-warning, #e8a73e);
        border-radius: 6px;
        font-size: 0.85rem;
        color: var(--color-warning-dark, #92400e);
      }
      .wizard-guard-message a {
        color: var(--color-primary, #3b82f6);
        font-weight: 500;
      }
      @media (max-width: 640px) {
        .wizard-step-label { display: none; }
        .wizard-step-circle { width: 28px; height: 28px; font-size: 0.75rem; }
      }
    `;
    document.head.appendChild(style);
  }

  function renderProgressBar() {
    injectWizardStyles();

    var container = document.getElementById('wizard-progress');
    if (!container) {
      // Create and inject before meeting header or at top of main
      var headerBar = document.querySelector('.meeting-header-bar') || document.querySelector('.session-selector');
      var main = document.querySelector('.app-main');
      if (!headerBar && !main) return;

      container = document.createElement('div');
      container.id = 'wizard-progress';
      container.className = 'wizard-container';

      if (headerBar && headerBar.parentNode) {
        headerBar.parentNode.insertBefore(container, headerBar);
      } else if (main) {
        main.insertBefore(container, main.firstChild);
      }
    }

    var state = getState();
    var checks = state.checks || {};
    var currentStep = computeStep(checks);
    var meetingId = state.meetingId || '';
    var meetingTitle = checks.meetingTitle || '';
    var meetingStatus = checks.meetingStatus || '';
    var mid = meetingId ? '?meeting_id=' + encodeURIComponent(meetingId) : '';

    // Determine current page step
    var pagePath = window.location.pathname;
    var pageStepId = PAGE_STEP_MAP[pagePath] || null;

    // Build stepper HTML
    var html = '<div class="wizard-stepper">';

    for (var i = 0; i < STEPS.length; i++) {
      var step = STEPS[i];
      var isDone = step.num < currentStep;
      var isCurrent = step.num === currentStep;
      var stepClass = isDone ? 'done' : (isCurrent ? 'current' : '');
      var href = step.needsMeeting && !meetingId ? '#' : (step.href + (step.needsMeeting ? mid : ''));
      var circleContent = isDone ? '✓' : step.icon;

      html += '<a href="' + href + '" class="wizard-step ' + stepClass + '" title="' + step.label + '">';
      html += '<div class="wizard-step-circle">' + circleContent + '</div>';
      html += '<div class="wizard-step-label">' + step.shortLabel + '</div>';
      html += '</a>';
    }

    html += '</div>';

    // Meeting info (if a meeting is selected)
    if (meetingId && meetingTitle) {
      var statusInfo = (window.Shared && window.Shared.MEETING_STATUS_MAP && window.Shared.MEETING_STATUS_MAP[meetingStatus])
        ? window.Shared.MEETING_STATUS_MAP[meetingStatus]
        : { text: meetingStatus, badge: 'badge-muted' };

      html += '<div class="wizard-meeting-info">';
      html += '<span class="wizard-meeting-title">📋 ' + escapeHtmlWizard(meetingTitle) + '</span>';
      html += '<span class="badge ' + statusInfo.badge + ' wizard-meeting-status">' + statusInfo.text + '</span>';
      html += '</div>';
    }

    // Guard message if prerequisites missing
    var guardMsg = getGuardMessage(pageStepId, checks, state);
    if (guardMsg) {
      html += '<div class="wizard-guard-message">';
      html += '<span>⚠️</span><span>' + guardMsg + '</span>';
      html += '</div>';
    }

    container.innerHTML = html;
  }

  function escapeHtmlWizard(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function getGuardMessage(pageStepId, checks, state) {
    if (!pageStepId) return null;
    var mid = state.meetingId;
    var status = checks.meetingStatus || '';
    var midParam = mid ? '?meeting_id=' + encodeURIComponent(mid) : '';

    switch (pageStepId) {
      case 'select':
        // No guard for meeting selection
        break;
      case 'members':
        if (!mid) return 'Sélectionnez d\'abord une séance depuis <a href="/meetings.htmx.html">Séances</a>.';
        break;
      case 'conduct':
        if (!mid) return 'Sélectionnez une séance pour commencer.';
        if (!checks.hasMembers) return 'Ajoutez des <a href="/members.htmx.html">membres</a> avant de continuer.';
        if (!checks.hasAttendance) return 'Pointez les <strong>présences</strong> dans l\'onglet Présences.';
        if (!checks.hasMotions) return 'Créez au moins une <strong>résolution</strong> dans l\'onglet Résolutions.';
        if (status === 'draft') return 'La séance est en brouillon. Passez-la en statut <strong>Programmée</strong> ou <strong>En cours</strong>.';
        if (status === 'live' && !checks.quorumMet) return 'Attention : le <strong>quorum</strong> n\'est pas atteint.';
        if (status === 'live' && !checks.hasPresident) return 'Info : aucun <strong>président</strong> assigné (optionnel).';
        break;
      case 'validate':
        if (!mid) return 'Sélectionnez une séance.';
        if (status === 'live') return 'Clôturez d\'abord tous les votes avant de valider la séance.';
        if (status !== 'closed' && status !== 'validated' && status !== 'archived') return 'La séance doit être clôturée avant validation.';
        if (!checks.allMotionsClosed) return 'Toutes les résolutions doivent être clôturées avant validation.';
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
