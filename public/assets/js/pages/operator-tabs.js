/**
 * operator-tabs.js — Tab-based operator console for AG-VOTE (Diligent-style)
 * Requires: utils.js, shared.js, shell.js
 *
 * Sub-modules (loaded after this file):
 *   operator-speech.js, operator-attendance.js, operator-motions.js
 * Communication via window.OpS bridge (state + function registry).
 */
window.OpS = { fn: {} };

(function() {
  'use strict';

  // DOM elements
  const meetingSelect = document.getElementById('meetingSelect');
  const meetingStatusBadge = document.getElementById('meetingStatusBadge');
  const tabsNav = document.getElementById('tabsNav');
  const noMeetingState = document.getElementById('noMeetingState');

  // Meeting bar elements
  const healthChip = document.getElementById('healthChip');
  const healthScore = document.getElementById('healthScore');
  const healthHint = document.getElementById('healthHint');
  const barClock = document.getElementById('barClock');
  const contextHint = document.getElementById('contextHint');
  const btnModeSetup = document.getElementById('btnModeSetup');
  const btnModeExec = document.getElementById('btnModeExec');
  const btnPrimary = document.getElementById('btnPrimary');
  const meetingBarActions = document.getElementById('meetingBarActions');
  const viewSetup = document.getElementById('viewSetup');
  const viewExec = document.getElementById('viewExec');
  const srAnnounce = document.getElementById('srAnnounce');

  // Tab elements
  const tabButtons = document.querySelectorAll('.tab-btn');
  const tabContents = document.querySelectorAll('.tab-content');

  // State
  let currentMeetingId = null;
  let currentMeetingStatus = null;
  let currentMeeting = null;
  let attendanceCache = [];
  let motionsCache = [];
  let currentOpenMotion = null;
  let previousOpenMotionId = null;  // Tracks previous motion ID for detecting new votes
  let ballotsCache = {};
  let usersCache = [];
  let policiesCache = { quorum: [], vote: [] };
  let proxiesCache = [];  // Cache for proxies
  let currentMode = 'setup'; // 'setup' | 'exec'
  let lastSetupTab = 'seance'; // Remember last active prep tab for seamless mode switching
  let _settingsSnapshot = null;  // Captured after populateSettingsForm to track dirty state

  // Safe DOM text setter — avoids null reference errors if element is missing
  function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  }

  // Transitions (icons are added dynamically via icon() function)
  const TRANSITIONS = {
    draft: [{ to: 'scheduled', label: 'Planifier', iconName: 'calendar' }],
    scheduled: [
      { to: 'frozen', label: 'Geler', iconName: 'lock' },
      { to: 'draft', label: 'Retour brouillon', iconName: 'arrow-left' }
    ],
    frozen: [
      { to: 'live', label: 'Ouvrir la séance', iconName: 'play' },
      { to: 'scheduled', label: 'Dégeler', iconName: 'unlock' }
    ],
    live: [
      { to: 'paused', label: 'Pause', iconName: 'pause' },
      { to: 'closed', label: 'Clôturer', iconName: 'square' }
    ],
    paused: [
      { to: 'live', label: 'Reprendre', iconName: 'play' },
      { to: 'closed', label: 'Clôturer', iconName: 'square' }
    ],
    closed: [{ to: 'validated', label: 'Valider', iconName: 'check-circle' }],
    validated: [{ to: 'archived', label: 'Archiver', iconName: 'archive' }],
    archived: []
  };

  // =========================================================================
  // MODAL UTILITIES
  // =========================================================================

  /**
   * Create a standardized modal with proper ARIA attributes and styling.
   * @param {Object} options - Modal configuration
   * @param {string} options.id - Unique ID for the modal
   * @param {string} options.title - Modal title (for ARIA)
   * @param {string} options.content - HTML content for modal body
   * @param {string} [options.maxWidth='500px'] - Max width of modal
   * @param {boolean} [options.closeOnBackdrop=true] - Close when clicking backdrop
   * @returns {HTMLElement} The modal element
   */
  function createModal({ id, title, content, maxWidth = '500px', closeOnBackdrop = true, onDismiss = null }) {
    const modalId = id || 'modal-' + Date.now();
    const titleId = modalId + '-title';

    const modal = document.createElement('div');
    modal.id = modalId;
    modal.className = 'modal-backdrop';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', titleId);
    modal.style.cssText = 'position:fixed;inset:0;background:var(--color-backdrop);z-index:var(--z-modal-backdrop, 400);display:flex;align-items:center;justify-content:center;opacity:1;visibility:visible;';

    modal.innerHTML = `
      <div class="modal-content" style="background:var(--color-surface);border-radius:12px;padding:1.5rem;max-width:${maxWidth};width:90%;max-height:90vh;overflow:auto;" role="document">
        ${content}
      </div>
    `;

    document.body.appendChild(modal);

    const handleEscape = (e) => {
      if (e.key === 'Escape' && document.body.contains(modal)) {
        destroyModal(true);
      }
    };

    function destroyModal(isDismiss) {
      document.removeEventListener('keydown', handleEscape);
      if (document.body.contains(modal)) modal.remove();
      if (isDismiss && onDismiss) onDismiss();
    }
    modal._destroy = () => destroyModal(false);

    if (closeOnBackdrop) {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) destroyModal(true);
      });
    }

    document.addEventListener('keydown', handleEscape);

    const focusableElements = modal.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    if (focusableElements.length > 0) {
      focusableElements[0].focus();
    }

    return modal;
  }

  /**
   * Remove modal from DOM and clean up event listeners
   * @param {HTMLElement|string} modal - Modal element or ID
   */
  function closeModal(modal) {
    const el = typeof modal === 'string' ? document.getElementById(modal) : modal;
    if (el && el._destroy) {
      el._destroy();
    } else if (el) {
      el.remove();
    }
  }

  // =========================================================================
  // CONFIRM MODAL HELPER (uses Shared.openModal for standardized modals)
  // =========================================================================

  /**
   * Show a confirm/cancel modal and return a Promise<boolean>.
   * @param {Object} opts
   * @param {string} opts.title - Modal title
   * @param {string} opts.body - Body HTML
   * @param {string} [opts.confirmText='Confirmer'] - Confirm button text
   * @param {string} [opts.confirmClass='btn-primary'] - Confirm button class
   * @returns {Promise<boolean>}
   */
  function confirmModal({ title, body, confirmText = 'Confirmer', confirmClass = 'btn-primary' }) {
    return new Promise(resolve => {
      let resolved = false;
      const m = Shared.openModal({
        title,
        body,
        confirmText,
        confirmClass,
        onConfirm() {
          resolved = true;
          resolve(true);
        }
      });
      // When modal is closed without confirming (Escape, backdrop, Cancel)
      const origClose = m._close;
      m._close = function() {
        origClose.call(this);
        if (!resolved) resolve(false);
      };
    });
  }

  // =========================================================================
  // TAB NAVIGATION
  // =========================================================================

  function initTabs() {
    tabButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        const tabId = btn.dataset.tab;
        switchTab(tabId);
      });
    });
  }

  // Legacy tab ID aliases for backward compat
  const TAB_ALIASES = {
    'parametres': 'seance',
    'presences': 'participants',
    'procurations': 'participants',
    'resolutions': 'ordre-du-jour'
  };

  async function switchTab(tabId) {
    // Resolve legacy aliases
    tabId = TAB_ALIASES[tabId] || tabId;

    // Warn if leaving Séance tab with unsaved changes
    var currentTab = document.querySelector('.tab-btn.active')?.dataset?.tab;
    if (currentTab === 'seance' && tabId !== 'seance' && _isSettingsDirty()) {
      if (!confirm('Vous avez des modifications non enregistrées dans l\'onglet Séance. Quitter sans sauvegarder ?')) {
        return;
      }
    }

    tabButtons.forEach(btn => {
      btn.classList.toggle('active', btn.dataset.tab === tabId);
    });
    tabContents.forEach(content => {
      content.classList.toggle('active', content.id === `tab-${tabId}`);
    });

    // Reload data when switching to certain tabs
    if (currentMeetingId) {
      if (tabId === 'participants') {
        await loadAttendance();
        await loadProxies();
      }
      if (tabId === 'ordre-du-jour') await loadResolutions();
      if (tabId === 'controle') {
        renderConformityChecklist();
        refreshAlerts();
        loadStatusChecklist();
        loadInvitationStats();
      }
      if (tabId === 'parole') await loadSpeechQueue();
      if (tabId === 'vote') { await loadVoteTab(); loadQuorumStatus(); }
      if (tabId === 'resultats') await loadResults();
    }
  }

  /**
   * Show/hide execution tabs based on meeting status
   */
  function updateLiveTabs() {
    const isLive = ['live', 'paused', 'closed', 'validated'].includes(currentMeetingStatus);
    const sep = document.getElementById('tabSeparator');
    if (sep) sep.hidden = !isLive;
    document.querySelectorAll('.tab-btn-live').forEach(btn => {
      btn.hidden = !isLive;
    });
    // Update alert count badge
    const alertCountEl = document.getElementById('tabCountAlerts');
    if (alertCountEl) {
      const count = parseInt(document.getElementById('setupAlertCount')?.textContent || '0');
      alertCountEl.textContent = count;
      alertCountEl.hidden = count === 0;
    }
  }

  // =========================================================================
  // MEETING SELECTION
  // =========================================================================

  async function loadMeetings() {
    try {
      const { body } = await api('/api/v1/meetings_index.php?active_only=1');
      if (body?.ok && body?.data?.items) {
        meetingSelect.innerHTML = '<option value="">— Sélectionner une séance —</option>';
        body.data.items.forEach(m => {
          const opt = document.createElement('option');
          opt.value = m.id;
          opt.textContent = `${m.title} (${m.status || 'draft'})`;
          meetingSelect.appendChild(opt);
        });

        // Pre-select from URL
        const urlMeetingId = new URLSearchParams(window.location.search).get('meeting_id');
        if (urlMeetingId) {
          meetingSelect.value = urlMeetingId;
          loadMeetingContext(urlMeetingId);
        }
      }
    } catch (err) {
      setNotif('error', 'Erreur chargement: ' + err.message);
    }
  }

  async function loadMeetingContext(meetingId) {
    if (!meetingId) {
      showNoMeeting();
      return;
    }

    currentMeetingId = meetingId;
    _hasAutoNavigated = false; // Reset for new meeting
    updateURLParam('meeting_id', meetingId);

    try {
      const { body } = await api(`/api/v1/meetings.php?id=${meetingId}`);
      if (body?.ok && body?.data) {
        currentMeeting = body.data;
        currentMeetingStatus = body.data.status;
        showMeetingContent();
        updateHeader(body.data);
        updateLiveTabs();
        await loadAllData();
      }
    } catch (err) {
      setNotif('error', 'Impossible de charger la séance. Vérifiez votre connexion et réessayez.');
    }
  }

  function showNoMeeting() {
    Shared.show(noMeetingState, 'flex');
    Shared.hide(tabsNav);
    tabContents.forEach(c => c.classList.remove('active'));
    if (meetingBarActions) meetingBarActions.hidden = true;
    if (viewSetup) viewSetup.hidden = true;
    if (viewExec) viewExec.hidden = true;
    if (healthChip) healthChip.hidden = true;
    currentMeetingId = null;
    currentMeeting = null;
    currentMode = 'setup';
  }

  function showMeetingContent() {
    Shared.hide(noMeetingState);
    if (meetingBarActions) meetingBarActions.hidden = false;

    // Always use advanced mode (assistant mode removed)
    setPrepMode();

    // Auto-select mode based on meeting status
    const initialMode = (currentMeetingStatus === 'live') ? 'exec' : 'setup';

    if (initialMode === 'setup') {
      // Check URL for a specific tab request
      const urlParams = new URLSearchParams(window.location.search);
      const requestedTab = urlParams.get('tab');
      const validTabs = ['seance', 'participants', 'ordre-du-jour', 'controle', 'parole', 'vote', 'resultats',
        'parametres', 'resolutions', 'presences', 'procurations']; // legacy aliases accepted
      // Default tab depends on meeting status: post-session → résultats, prep → séance
      const isPostSession = ['closed', 'validated', 'archived'].includes(currentMeetingStatus);
      const defaultTab = isPostSession ? 'resultats' : 'seance';
      const tabToShow = (requestedTab && validTabs.includes(requestedTab)) ? requestedTab : defaultTab;
      setMode('setup', { tab: tabToShow });
    } else {
      setMode('exec');
    }
  }

  function updateHeader(meeting) {
    const statusInfo = Shared.MEETING_STATUS_MAP[meeting.status] || Shared.MEETING_STATUS_MAP['draft'];
    meetingStatusBadge.className = `badge ${statusInfo.badge}`;
    meetingStatusBadge.textContent = statusInfo.text;

    // Update meeting links
    document.querySelectorAll('[data-meeting-link]').forEach(link => {
      const base = link.getAttribute('href').split('?')[0];
      link.href = `${base}?meeting_id=${currentMeetingId}`;
    });

    // Update lifecycle bar visual indicator
    updateLifecycleBar(meeting.status);
  }

  /**
   * Update the lifecycle bar to show which phase the meeting is in.
   * States flow: draft → scheduled → frozen → live → closed → validated → archived
   */
  function updateLifecycleBar(status) {
    const bar = document.getElementById('lifecycleBar');
    if (!bar) return;
    bar.hidden = false;

    const STATES = ['draft', 'scheduled', 'frozen', 'live', 'closed', 'validated', 'archived'];
    const currentIdx = STATES.indexOf(status);

    bar.querySelectorAll('.lifecycle-step').forEach(step => {
      const state = step.getAttribute('data-state');
      const idx = STATES.indexOf(state);
      step.classList.remove('done', 'current');
      if (idx < currentIdx) step.classList.add('done');
      else if (idx === currentIdx) step.classList.add('current');
    });
  }

  /**
   * Detect president mode from URL param or meeting role.
   */
  function detectPresidentMode() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('mode') === 'president') {
      document.querySelector('.app-shell')?.setAttribute('data-mode', 'president');
    }
  }
  detectPresidentMode();

  function updateURLParam(key, value) {
    const url = new URL(window.location);
    url.searchParams.set(key, value);
    window.history.replaceState({}, '', url);
  }

  // =========================================================================
  // LOAD ALL DATA
  // =========================================================================

  async function loadAllData() {
    // Use Promise.allSettled to handle partial failures gracefully
    const results = await Promise.allSettled([
      loadMembers(),
      loadAttendance(),
      loadResolutions(),
      loadPolicies(),
      loadRoles(),
      loadStatusChecklist(),
      loadAgenda(),
      loadDashboard(),
      loadDevices(),
      loadEmergencyProcedures(),
      loadSpeechQueue(),
      loadProxies(),
      loadInvitationStats()
    ]);

    // Log any failures but continue
    results.forEach((result, idx) => {
      if (result.status === 'rejected') {
        console.warn(`loadAllData: Task ${idx} failed:`, result.reason);
      }
    });

    populateSettingsForm();
    updateQuickStats();
    checkLaunchReady();

    // Update bimodal UI
    renderConformityChecklist();
    refreshAlerts();
    if (currentMode === 'exec') refreshExecView();

    // Initialize motion tracking state to avoid false notifications on page load
    initializePreviousMotionState();

    // If a vote is already open and meeting is live, auto-switch to exec
    if (currentOpenMotion && currentMeetingStatus === 'live' && currentMode === 'setup') {
      setMode('exec');
    }

    // Smart default tab: guide user to the first incomplete step
    autoNavigateToNextStep();

    // P2-1/P2-2/P2-6: Adapt UI based on user's role
    applyRoleAwareUI();
  }

  // =========================================================================
  // ROLE-AWARE UI (P2-1, P2-2, P2-6)
  // =========================================================================

  /**
   * Detect the user's effective role for the current meeting and adapt the UI:
   * - Show role badge in the meeting bar
   * - Disable/tooltip buttons the user cannot use
   * - Hide irrelevant tabs for non-operators
   */
  function applyRoleAwareUI() {
    const roleBadge = document.getElementById('userRoleBadge');
    if (!roleBadge) return;

    const auth = window.Auth || {};
    const systemRole = auth.role;
    const meetingRoles = auth.meetingRoles || [];
    const LABELS = auth.ROLE_LABELS || { admin: 'Administrateur', operator: 'Opérateur', president: 'Président', assessor: 'Assesseur' };

    // Determine the effective role for this meeting
    const meetingRole = meetingRoles.find(mr => String(mr.meeting_id) === String(currentMeetingId));
    const effectiveMeetingRole = meetingRole ? meetingRole.role : null;

    // Priority: meeting role > system role
    let displayRole = '';
    let roleColor = '';
    const isAdmin = systemRole === 'admin';
    const isOperator = systemRole === 'operator' || isAdmin;
    const isPresident = effectiveMeetingRole === 'president';
    const isAssessor = effectiveMeetingRole === 'assessor';

    if (isAdmin) {
      displayRole = LABELS.admin || 'Administrateur';
      roleColor = 'var(--color-danger, #dc2626)';
    } else if (isOperator && isPresident) {
      displayRole = (LABELS.operator || 'Opérateur') + ' + ' + (LABELS.president || 'Président');
      roleColor = 'var(--color-primary, #0066cc)';
    } else if (isOperator) {
      displayRole = LABELS.operator || 'Opérateur';
      roleColor = 'var(--color-primary, #0066cc)';
    } else if (isPresident) {
      displayRole = LABELS.president || 'Président';
      roleColor = 'var(--color-warning, #f59e0b)';
    } else if (isAssessor) {
      displayRole = LABELS.assessor || 'Assesseur';
      roleColor = 'var(--color-text-muted)';
    } else if (systemRole) {
      displayRole = LABELS[systemRole] || systemRole;
      roleColor = 'var(--color-text-muted)';
    }

    // P2-1: Show role badge
    if (displayRole) {
      roleBadge.innerHTML = icon('user', 'icon-sm icon-text') + ' ' + escapeHtml(displayRole);
      roleBadge.style.color = roleColor;
      roleBadge.hidden = false;
    } else {
      roleBadge.hidden = true;
    }

    // P2: If user is president (not operator/admin), 100% lecture seule
    const isRestrictedPresident = isPresident && !isOperator;

    if (isRestrictedPresident) {
      // Hide prep mode switches (president doesn't prepare)
      const prepModeSwitch = document.getElementById('prepModeSwitch');
      if (prepModeSwitch) prepModeSwitch.hidden = true;
      const modeSwitch = document.getElementById('modeSwitch');
      if (modeSwitch) modeSwitch.hidden = true;

      // Hide "Paramètres" tab
      const paramTab = document.querySelector('[data-tab="parametres"]');
      if (paramTab) paramTab.style.display = 'none';

      // Hide primary action button (Ouvrir la séance, etc.)
      if (btnPrimary) btnPrimary.style.display = 'none';

      // Disable ALL buttons and inputs inside tab contents (full read-only)
      document.querySelectorAll('.tab-content button, .tab-content input, .tab-content select, .tab-content textarea').forEach(el => {
        el.disabled = true;
        el.title = el.title || 'Consultation uniquement — Président';
      });

      // Also disable exec view buttons
      document.querySelectorAll('#viewExec button, #viewExec input, #viewExec select').forEach(el => {
        el.disabled = true;
        el.title = el.title || 'Consultation uniquement — Président';
      });

      // Update context hint to show supervision mode
      const hint = document.getElementById('contextHint');
      if (hint) {
        hint.innerHTML = icon('eye', 'icon-sm icon-text') + ' <strong>Mode supervision</strong> — Consultation uniquement';
      }

      // Allow the Actualiser and Projection buttons (read-only actions)
      const allowedBtns = ['btnBarRefresh', 'btnProjector', 'btnRecheck'];
      allowedBtns.forEach(id => {
        const btn = document.getElementById(id);
        if (btn) { btn.disabled = false; btn.title = ''; }
      });
    }
  }

  // =========================================================================
  // MEMBERS CARD
  // =========================================================================

  let membersCache = [];

  async function loadMembers() {
    try {
      const { body } = await api('/api/v1/members.php');
      membersCache = body?.data?.items || [];
      renderMembersCard();
    } catch (err) {
      setNotif('error', 'Erreur chargement membres');
    }
  }

  function renderMembersCard() {
    setText('membersCount', membersCache.length);

    const list = document.getElementById('membersList');
    if (!list) return;

    if (membersCache.length === 0) {
      list.innerHTML = '<span class="text-muted text-sm">Aucun membre inscrit. Ajoutez des membres pour commencer.</span>';
      return;
    }

    // Show first 5 members
    const display = membersCache.slice(0, 5);
    list.innerHTML = display.map(m => `
      <div class="flex items-center gap-2 text-sm py-1">
        <span class="text-muted">•</span>
        <span>${escapeHtml(m.full_name || m.email || '—')}</span>
      </div>
    `).join('');

    if (membersCache.length > 5) {
      list.innerHTML += `<div class="text-sm text-muted">+ ${membersCache.length - 5} autres...</div>`;
    }
  }

  async function addMemberQuick() {
    const modal = createModal({
      id: 'addMemberModal',
      title: 'Ajouter un membre',
      maxWidth: '400px',
      content: `
        <h3 id="addMemberModal-title" style="margin:0 0 1rem;">Ajouter un membre</h3>
        <div class="form-group mb-3">
          <label class="form-label">Nom complet</label>
          <input type="text" class="form-input" id="newMemberName" placeholder="Nom Prénom">
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Email (optionnel)</label>
          <input type="email" class="form-input" id="newMemberEmail" placeholder="email@exemple.com">
        </div>
        <div class="flex gap-2 justify-end">
          <button class="btn btn-secondary" id="btnCancelMember">Annuler</button>
          <button class="btn btn-primary" id="btnConfirmMember">Ajouter</button>
        </div>
      `
    });

    const btnCancel = document.getElementById('btnCancelMember');
    const btnConfirm = document.getElementById('btnConfirmMember');

    btnCancel.addEventListener('click', () => closeModal(modal));
    btnConfirm.addEventListener('click', async () => {
      const name = document.getElementById('newMemberName').value.trim();
      const email = document.getElementById('newMemberEmail').value.trim();

      if (!name) {
        setNotif('error', 'Le nom est requis');
        return;
      }

      btnConfirm.disabled = true;
      btnCancel.disabled = true;
      const originalText = btnConfirm.textContent;
      btnConfirm.textContent = 'Ajout...';

      try {
        await api('/api/v1/members.php', {
          action: 'create',
          full_name: name,
          email: email || null
        });
        setNotif('success', 'Membre ajouté');
        closeModal(modal);
        loadMembers();
        loadAttendance();
        loadStatusChecklist();
      } catch (err) {
        setNotif('error', err.message);
        btnConfirm.disabled = false;
        btnCancel.disabled = false;
        btnConfirm.textContent = originalText;
      }
    });
  }

  // =========================================================================
  // QUICK STATS & LAUNCH BANNER
  // =========================================================================

  function updateQuickStats() {
    const present = attendanceCache.filter(a => a.mode === 'present').length;
    const remote = attendanceCache.filter(a => a.mode === 'remote').length;
    const proxyCount = proxiesCache.filter(p => !p.revoked_at).length;
    const absent = attendanceCache.filter(a => !a.mode || a.mode === 'absent').length;

    setText('quickPresent', present);
    setText('quickRemote', remote);
    setText('quickProxy', proxyCount);
    setText('quickAbsent', absent);
  }

  function checkLaunchReady() {
    const banner = document.getElementById('launchBanner');
    if (!banner) return;

    // Show banner if: has members, has attendance, has motions, and not already live/closed
    const hasMembers = membersCache.length > 0;
    const hasAttendance = attendanceCache.some(a => a.mode === 'present' || a.mode === 'remote');
    const hasMotions = motionsCache.length > 0;
    const canLaunch = ['draft', 'scheduled', 'frozen'].includes(currentMeetingStatus);

    if (hasMembers && hasAttendance && hasMotions && canLaunch) {
      Shared.show(banner, 'block');
      // Clear any blockers message
      var blockersEl = document.getElementById('launchBlockers');
      if (blockersEl) blockersEl.hidden = true;
    } else if (canLaunch) {
      // Show the banner but with blockers explanation
      Shared.show(banner, 'block');
      var blockers = [];
      if (!hasMembers) blockers.push('Aucun membre inscrit');
      if (!hasAttendance) blockers.push('Aucun membre point\u00e9 pr\u00e9sent');
      if (!hasMotions) blockers.push('Aucune r\u00e9solution cr\u00e9\u00e9e');

      var blockersEl = document.getElementById('launchBlockers');
      if (!blockersEl) {
        blockersEl = document.createElement('div');
        blockersEl.id = 'launchBlockers';
        blockersEl.className = 'launch-blockers';
        banner.appendChild(blockersEl);
      }
      blockersEl.hidden = false;
      blockersEl.innerHTML =
        '<div class="text-sm" style="color:var(--color-warning-text);margin-top:var(--space-2);">' +
          '<strong>Pr\u00e9requis manquants :</strong> ' +
          blockers.map(function(b) {
            return '<span class="launch-blocker-item">' +
              '<svg class="icon icon-xs" aria-hidden="true"><use href="/assets/icons.svg#icon-alert-triangle"></use></svg> ' +
              Utils.escapeHtml(b) +
            '</span>';
          }).join(' \u00b7 ') +
        '</div>';

      // Disable the launch button
      var launchBtn = document.getElementById('btnLaunchSession');
      if (launchBtn) launchBtn.disabled = true;
    } else {
      Shared.hide(banner);
    }
  }

  function showLaunchModal() {
    const modal = document.getElementById('launchModal');
    if (!modal) { launchSessionConfirmed(); return; }

    // Fill meeting title
    const titleEl = document.getElementById('launchModalMeetingTitle');
    if (titleEl) titleEl.textContent = currentMeeting?.title || 'Séance';

    // Build summary
    const presentCount = attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote').length;
    const remoteCount = attendanceCache.filter(a => a.mode === 'remote').length;
    const proxyCount = proxiesCache.length;
    const motionCount = motionsCache.length;
    const hasPresident = currentMeeting?.president_name || '';

    let dateText = '—';
    if (currentMeeting?.scheduled_at) {
      try {
        dateText = new Date(currentMeeting.scheduled_at).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
      } catch (e) { dateText = escapeHtml(currentMeeting.scheduled_at); }
    }

    const summary = document.getElementById('launchModalSummary');
    if (summary) {
      summary.innerHTML = `
        <div class="summary-line"><span class="summary-label">Date</span><span class="summary-value">${escapeHtml(dateText)}</span></div>
        <div class="summary-line"><span class="summary-label">Membres</span><span class="summary-value">${membersCache.length} inscrits</span></div>
        <div class="summary-line"><span class="summary-label">Présents</span><span class="summary-value">${presentCount}${remoteCount > 0 ? ' (dont ' + remoteCount + ' à distance)' : ''}${proxyCount > 0 ? ' + ' + proxyCount + ' procuration' + (proxyCount > 1 ? 's' : '') : ''}</span></div>
        <div class="summary-line"><span class="summary-label">Résolutions</span><span class="summary-value">${motionCount} à voter</span></div>
        ${hasPresident ? '<div class="summary-line"><span class="summary-label">Président</span><span class="summary-value">' + escapeHtml(hasPresident) + '</span></div>' : ''}
      `;
    }

    modal.style.display = 'flex';
    document.getElementById('launchModalConfirm')?.focus();
  }

  function hideLaunchModal() {
    const modal = document.getElementById('launchModal');
    if (modal) modal.style.display = 'none';
  }

  async function launchSession() {
    showLaunchModal();
  }

  async function launchSessionConfirmed() {
    hideLaunchModal();

    // Show loading on launch/primary buttons during API call
    const launchBtn = document.getElementById('btnLaunchSession');
    if (launchBtn) Shared.btnLoading(launchBtn, true);
    if (btnPrimary) Shared.btnLoading(btnPrimary, true);

    try {
      // Atomic launch: single API call handles all transitions (draft→scheduled→frozen→live)
      const { body } = await api('/api/v1/meeting_launch.php', {
        meeting_id: currentMeetingId
      });

      if (!body?.ok) {
        setNotif('error', getApiError(body, 'Erreur lors du lancement'));
        // Reload to reflect any partial state change
        await loadMeetingContext(currentMeetingId);
        return;
      }

      if (body.data?.warnings?.length) {
        body.data.warnings.forEach(w => setNotif('warning', w.msg || w));
      }

      setNotif('success', 'Séance lancée !');
      await loadMeetingContext(currentMeetingId);
      setMode('exec');
      announce('Séance lancée — mode exécution activé.');
    } catch (err) {
      setNotif('error', err.message);
      // Reload to see current state after potential partial failure
      await loadMeetingContext(currentMeetingId);
    } finally {
      if (launchBtn) Shared.btnLoading(launchBtn, false);
      if (btnPrimary) Shared.btnLoading(btnPrimary, false);
    }
  }

  // Launch modal event listeners
  document.getElementById('launchModalCancel')?.addEventListener('click', hideLaunchModal);
  document.getElementById('launchModalConfirm')?.addEventListener('click', launchSessionConfirmed);
  document.getElementById('launchModal')?.addEventListener('click', function(e) {
    if (e.target === this) hideLaunchModal();
  });

  // =========================================================================
  // TAB: PARAMÈTRES - Settings
  // =========================================================================

  function populateSettingsForm() {
    if (!currentMeeting) return;

    // Titre
    const titleInput = document.getElementById('settingTitle');
    if (titleInput) titleInput.value = currentMeeting.title || '';

    // Date (format: YYYY-MM-DD)
    const dateInput = document.getElementById('settingDate');
    if (dateInput && currentMeeting.scheduled_at) {
      dateInput.value = currentMeeting.scheduled_at.slice(0, 10);
    }

    // Type de consultation
    const meetingType = currentMeeting.meeting_type || 'ag_ordinaire';
    document.querySelectorAll('input[name="meetingType"]').forEach(radio => {
      radio.checked = radio.value === meetingType;
    });

    // Policies
    const qSelect = document.getElementById('settingQuorumPolicy');
    const vSelect = document.getElementById('settingVotePolicy');
    if (qSelect) qSelect.value = currentMeeting.quorum_policy_id || '';
    if (vSelect) vSelect.value = currentMeeting.vote_policy_id || '';

    // Convocation
    const convSelect = document.getElementById('settingConvocation');
    if (convSelect) convSelect.value = currentMeeting.convocation_no || 1;

    // Capture snapshot for dirty-state tracking
    _captureSettingsSnapshot();
  }

  function _captureSettingsSnapshot() {
    _settingsSnapshot = {
      title: (document.getElementById('settingTitle')?.value || '').trim(),
      date: document.getElementById('settingDate')?.value || '',
      type: document.querySelector('input[name="meetingType"]:checked')?.value || '',
      quorum: document.getElementById('settingQuorumPolicy')?.value || '',
      vote: document.getElementById('settingVotePolicy')?.value || '',
      convocation: document.getElementById('settingConvocation')?.value || ''
    };
  }

  function _isSettingsDirty() {
    if (!_settingsSnapshot) return false;
    var current = {
      title: (document.getElementById('settingTitle')?.value || '').trim(),
      date: document.getElementById('settingDate')?.value || '',
      type: document.querySelector('input[name="meetingType"]:checked')?.value || '',
      quorum: document.getElementById('settingQuorumPolicy')?.value || '',
      vote: document.getElementById('settingVotePolicy')?.value || '',
      convocation: document.getElementById('settingConvocation')?.value || ''
    };
    return Object.keys(_settingsSnapshot).some(function(k) {
      return _settingsSnapshot[k] !== current[k];
    });
  }

  async function loadPolicies() {
    try {
      const [qpRes, vpRes] = await Promise.all([
        api('/api/v1/quorum_policies.php'),
        api('/api/v1/vote_policies.php')
      ]);

      policiesCache.quorum = qpRes.body?.data?.items || [];
      policiesCache.vote = vpRes.body?.data?.items || [];

      const qSelect = document.getElementById('settingQuorumPolicy');
      const vSelect = document.getElementById('settingVotePolicy');

      qSelect.innerHTML = '<option value="">— Aucune —</option>';
      policiesCache.quorum.forEach(p => {
        qSelect.innerHTML += `<option value="${p.id}">${escapeHtml(p.label || p.name)}</option>`;
      });

      vSelect.innerHTML = '<option value="">— Aucune —</option>';
      policiesCache.vote.forEach(p => {
        vSelect.innerHTML += `<option value="${p.id}">${escapeHtml(p.label || p.name)}</option>`;
      });

      // Set values if meeting is loaded
      if (currentMeeting) {
        qSelect.value = currentMeeting.quorum_policy_id || '';
        vSelect.value = currentMeeting.vote_policy_id || '';
        document.getElementById('settingConvocation').value = currentMeeting.convocation_no || 1;
      }
    } catch (err) {
      setNotif('error', 'Erreur chargement politiques');
    }
  }

  async function loadRoles() {
    try {
      const [usersRes, rolesRes] = await Promise.all([
        api('/api/v1/admin_users.php'),
        api(`/api/v1/admin_meeting_roles.php?meeting_id=${currentMeetingId}`)
      ]);

      usersCache = usersRes.body?.data?.items || [];
      const roles = rolesRes.body?.data?.items || [];
      const president = roles.find(r => r.role === 'president');
      const assessors = roles.filter(r => r.role === 'assessor');

      // President select - support both native select and ag-searchable-select
      const presSelect = document.getElementById('settingPresident');
      const isSearchable = presSelect && presSelect.tagName.toLowerCase() === 'ag-searchable-select';

      if (isSearchable) {
        // Use ag-searchable-select API
        const options = usersCache.map(u => ({
          value: u.id,
          label: u.name || u.email || 'Utilisateur',
          sublabel: u.email || ''
        }));
        presSelect.setOptions(options);
        if (president?.user_id) {
          presSelect.value = president.user_id;
        }
      } else {
        // Fallback to native select
        presSelect.innerHTML = '<option value="">— Non assigné —</option>';
        usersCache.forEach(u => {
          const selected = president?.user_id === u.id ? 'selected' : '';
          presSelect.innerHTML += `<option value="${u.id}" ${selected}>${escapeHtml(u.name || u.email)}</option>`;
        });
      }

      // Assessors list
      renderAssessors(assessors);
    } catch (err) {
      setNotif('error', 'Erreur chargement rôles');
    }
  }

  function renderAssessors(assessors) {
    const container = document.getElementById('assessorsList');
    if (!assessors || assessors.length === 0) {
      container.innerHTML = '<span class="text-muted text-sm">Aucun assesseur</span>';
      return;
    }

    container.innerHTML = assessors.map(a => {
      const user = usersCache.find(u => u.id === a.user_id);
      const name = user ? (user.name || user.email) : a.user_id;
      return `
        <div class="flex items-center justify-between gap-2 p-2 bg-subtle rounded">
          <span>${escapeHtml(name)}</span>
          <button class="btn btn-sm btn-ghost text-danger" data-remove-assessor="${escapeHtml(a.user_id)}" title="Retirer" aria-label="Retirer ${escapeHtml(name)}">&#10005;</button>
        </div>
      `;
    }).join('');
  }

  // Delegated handler for assessor removal
  document.addEventListener('click', async function(e) {
    const btn = e.target.closest('[data-remove-assessor]');
    if (!btn) return;
    const userId = btn.getAttribute('data-remove-assessor');
    if (!userId) return;
    const confirmed = await confirmModal({
      title: 'Retirer l\'assesseur',
      body: '<p>Retirer cet assesseur de la séance ?</p>',
      confirmText: 'Retirer',
      confirmClass: 'btn-danger'
    });
    if (!confirmed) return;
    btn.disabled = true;
    try {
      await api('/api/v1/admin_meeting_roles.php', {
        action: 'revoke',
        meeting_id: currentMeetingId,
        user_id: userId,
        role: 'assessor'
      });
      setNotif('success', 'Assesseur retiré');
      loadRoles();
    } catch (err) {
      setNotif('error', err.message);
    } finally {
      btn.disabled = false;
    }
  });

  // =========================================================================
  // DASHBOARD & DEVICES WIDGETS
  // =========================================================================

  async function loadDashboard() {
    if (!currentMeetingId) return;

    try {
      const { body } = await api(`/api/v1/dashboard.php?meeting_id=${currentMeetingId}`);
      const d = body?.data || body || {};

      // Show card
      const card = document.getElementById('dashboardCard');
      if (card) Shared.show(card, 'block');

      // Attendance
      setText('dashPresentCount', d.attendance?.present_count ?? '-');
      setText('dashEligibleCount', d.attendance?.eligible_count ?? '-');
      setText('dashProxyCount', d.proxies?.count ?? 0);
      setText('dashOpenMotions', d.openable_motions?.length ?? 0);

      // Current motion
      const motionDiv = document.getElementById('dashCurrentMotion');
      if (d.current_motion) {
        Shared.show(motionDiv, 'block');
        document.getElementById('dashMotionTitle').textContent = d.current_motion.title || '—';
        const votes = d.current_motion_votes || {};
        document.getElementById('dashVoteFor').textContent = Shared.formatWeight(votes.weight_for ?? 0);
        document.getElementById('dashVoteAgainst').textContent = Shared.formatWeight(votes.weight_against ?? 0);
        document.getElementById('dashVoteAbstain').textContent = Shared.formatWeight(votes.weight_abstain ?? 0);
      } else {
        Shared.hide(motionDiv);
      }

      // Ready to sign
      const ready = d.ready_to_sign || {};
      if (ready.can) { Shared.show(document.getElementById('dashReadySign'), 'block'); } else { Shared.hide(document.getElementById('dashReadySign')); }
      if (ready.can) { Shared.hide(document.getElementById('dashNotReadySign')); } else { Shared.show(document.getElementById('dashNotReadySign'), 'block'); }
      if (!ready.can && ready.reasons?.length) {
        document.getElementById('dashReadyReasons').innerHTML = ready.reasons.map(r => `<li>${escapeHtml(r)}</li>`).join('');
      }
    } catch (err) {
      setNotif('error', 'Erreur chargement tableau de bord');
    }
  }

  async function loadDevices() {
    if (!currentMeetingId) return;

    try {
      const { body } = await api(`/api/v1/devices_list.php?meeting_id=${currentMeetingId}`);

      if (!body?.ok) return;

      // Show card
      const card = document.getElementById('devicesCard');
      if (card) Shared.show(card, 'block');

      const counts = body.data?.counts || {};
      setText('devOnline', counts.online ?? 0);
      setText('devStale', counts.stale ?? 0);
      setText('devOffline', counts.offline ?? 0);
      setText('devBlocked', counts.blocked ?? 0);

      // Device list (show first 5)
      const items = body.data?.items || [];
      const list = document.getElementById('devicesList');

      if (items.length === 0) {
        list.innerHTML = '<span class="text-muted text-sm">Aucun appareil connecté</span>';
      } else {
        const display = items.slice(0, 5);
        list.innerHTML = display.map(dev => {
          const statusIcon = dev.status === 'online' ? icon('circle', 'icon-xs icon-success') : dev.status === 'stale' ? icon('circle', 'icon-xs icon-warning') : icon('circle', 'icon-xs icon-muted');
          const blocked = dev.is_blocked ? ' ' + icon('ban', 'icon-xs icon-danger') : '';
          const battery = dev.battery_pct !== null ? ` ${dev.battery_pct}%` : '';
          const role = dev.role ? ` (${dev.role})` : '';
          return `<div class="text-sm">${statusIcon}${blocked} ${escapeHtml(dev.device_id.slice(0, 8))}...${role}${battery}</div>`;
        }).join('');

        if (items.length > 5) {
          list.innerHTML += `<div class="text-sm text-muted">+ ${items.length - 5} autres...</div>`;
        }
      }
    } catch (err) {
      setNotif('error', 'Erreur chargement appareils');
    }
  }

  // =========================================================================
  // AGENDA MANAGEMENT
  // =========================================================================

  let agendaCache = [];

  async function loadAgenda() {
    if (!currentMeetingId) return;
    var container = document.getElementById('agendaList');
    if (!container) return;

    await Shared.withRetry({
      container: container,
      errorMsg: 'Impossible de charger l\u2019ordre du jour',
      action: async function () {
        var res = await api('/api/v1/agendas.php?meeting_id=' + currentMeetingId);
        var d = res.body;
        if (!d || !d.ok) throw new Error((d && d.error) || 'Erreur');
        agendaCache = (d.data && d.data.items) || [];
        container.setAttribute('aria-busy', 'false');
        renderAgenda();
      }
    });
  }

  function renderAgenda() {
    var container = document.getElementById('agendaList');
    if (!container) return;

    if (agendaCache.length === 0) {
      container.innerHTML = Shared.emptyState({
        icon: 'generic',
        title: 'Aucun point \u00e0 l\u2019ordre du jour',
        description: 'Ajoutez les points qui structureront votre s\u00e9ance.'
      });
      return;
    }

    container.innerHTML = '<ol class="agenda-items">' +
      agendaCache.map(function (item, i) {
        return '<li class="agenda-item" data-id="' + escapeHtml(item.id || '') + '">' +
          '<span class="agenda-item-number">' + (i + 1) + '</span>' +
          '<span class="agenda-item-title">' + escapeHtml(item.title || '') + '</span>' +
        '</li>';
      }).join('') +
    '</ol>';
  }

  // Add agenda item
  document.getElementById('btnAddAgendaItem')?.addEventListener('click', function () {
    if (!currentMeetingId) { setNotif('error', 'Aucune s\u00e9ance s\u00e9lectionn\u00e9e'); return; }

    Shared.openModal({
      title: 'Ajouter un point \u00e0 l\u2019ordre du jour',
      body:
        '<div class="form-group">' +
          '<label class="form-label">Intitul\u00e9 du point</label>' +
          '<input class="form-input" type="text" id="agendaItemTitle" placeholder="Ex: Approbation du proc\u00e8s-verbal" maxlength="500" autofocus>' +
        '</div>',
      confirmText: 'Ajouter',
      onConfirm: async function (modal) {
        var titleInput = modal.querySelector('#agendaItemTitle');
        if (!Shared.validateField(titleInput, [{ test: function (v) { return v.length > 0; }, msg: 'L\u2019intitul\u00e9 est requis' }])) return false;

        var confirmBtn = modal.querySelector('.modal-confirm-btn');
        Shared.btnLoading(confirmBtn, true);
        try {
          var res = await api('/api/v1/agendas.php', {
            meeting_id: currentMeetingId,
            title: titleInput.value.trim()
          });
          if (res.body && res.body.ok) {
            setNotif('success', 'Point ajout\u00e9');
            loadAgenda();
          } else {
            setNotif('error', (res.body && res.body.error) || 'Erreur');
            Shared.btnLoading(confirmBtn, false);
            return false;
          }
        } catch (e) {
          setNotif('error', 'Erreur r\u00e9seau');
          Shared.btnLoading(confirmBtn, false);
          return false;
        }
      }
    });
  });

  // =========================================================================
  // EMERGENCY PROCEDURES
  // =========================================================================

  async function loadEmergencyProcedures() {
    if (!currentMeetingId) return;
    var container = document.getElementById('emergencyChecklist');
    if (!container) return;

    await Shared.withRetry({
      container: container,
      errorMsg: 'Impossible de charger les proc\u00e9dures d\u2019urgence',
      action: async function () {
        var url = '/api/v1/emergency_procedures.php?audience=operator&meeting_id=' + currentMeetingId;
        var res = await api(url);
        var d = res.body;
        if (!d || !d.ok) throw new Error(d && d.error || 'Erreur');

        var items = (d.data && d.data.items) || [];
        var checks = (d.data && d.data.checks) || [];

        if (items.length === 0) {
          container.innerHTML = '<p class="text-muted text-sm">Aucune proc\u00e9dure configur\u00e9e.</p>';
          container.setAttribute('aria-busy', 'false');
          return;
        }

        // Build a lookup for checked items
        var checkedMap = {};
        checks.forEach(function (c) {
          checkedMap[c.procedure_code + ':' + c.item_index] = c.checked;
        });

        container.setAttribute('aria-busy', 'false');
        container.innerHTML = items.map(function (proc, idx) {
          var code = proc.code || proc.id;
          var checked = checkedMap[code + ':' + idx] ? ' checked' : '';
          return '<label class="emergency-item">' +
            '<input type="checkbox" class="emergency-cb" data-code="' + escapeHtml(code) + '" data-index="' + idx + '"' + checked + '>' +
            '<span>' + escapeHtml(proc.field || proc.code || '') + '</span>' +
            '</label>';
        }).join('');

        // Bind toggles
        container.querySelectorAll('.emergency-cb').forEach(function (cb) {
          cb.addEventListener('change', function () {
            var code = cb.dataset.code;
            var index = parseInt(cb.dataset.index, 10);
            api('/api/v1/emergency_check_toggle.php', {
              meeting_id: currentMeetingId,
              procedure_code: code,
              item_index: index,
              checked: cb.checked ? 1 : 0
            }).catch(function () {
              setNotif('error', 'Erreur de mise \u00e0 jour');
              cb.checked = !cb.checked;
            });
          });
        });
      }
    });
  }

  // =========================================================================
  // QUORUM STATUS (vote tab card)
  // =========================================================================

  async function loadQuorumStatus() {
    if (!currentMeetingId) return;
    var card = document.getElementById('quorumStatusCard');
    if (!card) return;

    // Only show when a quorum policy is set
    if (!currentMeeting || !currentMeeting.quorum_policy_id) {
      card.hidden = true;
      return;
    }

    try {
      var res = await api('/api/v1/quorum_status.php?meeting_id=' + currentMeetingId);
      var d = res.body;
      if (!d || !d.ok) { card.hidden = true; return; }

      var data = d.data || d;
      var applied = data.applied;
      var met = data.met;
      var justification = data.justification || '';
      var present = data.present || 0;
      var required = data.required || 0;
      var totalEligible = data.total_eligible || 0;

      var label = document.getElementById('quorumStatusLabel');
      var detail = document.getElementById('quorumStatusDetail');
      var badge = document.getElementById('quorumStatusBadge');

      if (!applied) {
        label.textContent = 'Quorum';
        detail.textContent = 'Aucune politique appliquée';
        badge.textContent = '—';
        badge.className = 'badge ml-auto';
        card.hidden = false;
        card.className = 'quorum-status-card mb-4';
        return;
      }

      label.textContent = present + ' / ' + required + ' requis';
      detail.textContent = justification || (present + ' présents sur ' + totalEligible + ' éligibles');

      if (met === true) {
        badge.textContent = 'Atteint';
        badge.className = 'badge badge-success ml-auto';
        card.className = 'quorum-status-card mb-4 quorum-met';
      } else if (met === false) {
        badge.textContent = 'Non atteint';
        badge.className = 'badge badge-danger ml-auto';
        card.className = 'quorum-status-card mb-4 quorum-unmet';
      } else {
        badge.textContent = '—';
        badge.className = 'badge ml-auto';
        card.className = 'quorum-status-card mb-4';
      }

      card.hidden = false;
    } catch (e) {
      console.warn('loadQuorumStatus error:', e);
      card.hidden = true;
    }
  }

  function showDeviceManagementModal() {
    const modal = document.createElement('div');
    modal.className = 'modal-backdrop';
    modal.style.cssText = 'position:fixed;inset:0;background:var(--color-backdrop);z-index:var(--z-modal-backdrop, 400);display:flex;align-items:center;justify-content:center;opacity:1;visibility:visible;';

    modal.innerHTML = `
      <div style="background:var(--color-surface);border-radius:12px;padding:1.5rem;max-width:600px;width:90%;max-height:80vh;overflow:auto;">
        <div class="flex items-center justify-between mb-4">
          <h3 style="margin:0;"><svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-activity"></use></svg> Gestion des appareils</h3>
          <button class="btn btn-sm btn-ghost" id="btnCloseDevices">×</button>
        </div>
        <div id="devicesModalList">
          <div class="text-center p-4"><div class="spinner"></div></div>
        </div>
      </div>
    `;

    document.body.appendChild(modal);
    document.getElementById('btnCloseDevices').addEventListener('click', () => modal.remove());
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });

    loadDevicesModal(modal);
  }

  async function loadDevicesModal(modal) {
    const list = modal.querySelector('#devicesModalList');

    try {
      const { body } = await api(`/api/v1/devices_list.php?meeting_id=${currentMeetingId}`);

      if (!body?.ok || !body.data?.items?.length) {
        list.innerHTML = '<div class="text-center p-4 text-muted">Aucun appareil connecté</div>';
        return;
      }

      list.innerHTML = body.data.items.map(dev => {
        const statusIcon = dev.status === 'online' ? icon('circle', 'icon-xs icon-success') : dev.status === 'stale' ? icon('circle', 'icon-xs icon-warning') : icon('circle', 'icon-xs icon-muted');
        const blocked = dev.is_blocked;
        const battery = dev.battery_pct !== null ? `${icon('battery', 'icon-xs')} ${dev.battery_pct}%${dev.is_charging ? icon('zap', 'icon-xs') : ''}` : '';

        return `
          <div class="flex items-center justify-between p-3 border-b" style="border-color:var(--color-border);">
            <div>
              <div class="font-medium">${statusIcon} ${escapeHtml(dev.device_id.slice(0, 12))}...</div>
              <div class="text-xs text-muted">${escapeHtml(dev.role || 'inconnu')} • ${escapeHtml(dev.ip || '—')} ${battery}</div>
              ${blocked ? `<div class="text-xs text-danger">${icon('ban', 'icon-xs icon-text')}Bloqué: ${escapeHtml(dev.block_reason || '')}</div>` : ''}
            </div>
            <div class="flex gap-1">
              ${blocked
                ? `<button class="btn btn-xs btn-success btn-unblock" data-device="${dev.device_id}">Débloquer</button>`
                : `<button class="btn btn-xs btn-warning btn-block" data-device="${dev.device_id}">Bloquer</button>`
              }
              <button class="btn btn-xs btn-secondary btn-kick" data-device="${dev.device_id}">Reconnecter</button>
            </div>
          </div>
        `;
      }).join('');

      // Bind actions
      list.querySelectorAll('.btn-block').forEach(btn => {
        btn.addEventListener('click', () => blockDevice(btn.dataset.device, modal));
      });
      list.querySelectorAll('.btn-unblock').forEach(btn => {
        btn.addEventListener('click', () => unblockDevice(btn.dataset.device, modal));
      });
      list.querySelectorAll('.btn-kick').forEach(btn => {
        btn.addEventListener('click', () => kickDevice(btn.dataset.device, modal));
      });
    } catch (err) {
      list.innerHTML = `<div class="text-center p-4 text-danger">Erreur: ${escapeHtml(err.message)}</div>`;
    }
  }

  async function blockDevice(deviceId, parentModal) {
    const reason = await new Promise(resolve => {
      const m = createModal({
        id: 'blockDeviceReasonModal',
        title: 'Bloquer l\'appareil',
        onDismiss: () => resolve(null),
        content: `
          <div class="form-group mb-4">
            <label class="form-label">Raison du blocage (optionnel)</label>
            <input class="form-input" type="text" id="blockDeviceReason" placeholder="Ex : comportement suspect" maxlength="200">
          </div>
          <div class="flex gap-3 justify-end">
            <button class="btn btn-secondary" data-action="cancel">Annuler</button>
            <button class="btn btn-danger" data-action="confirm">Bloquer</button>
          </div>`
      });
      m.querySelector('[data-action="cancel"]').addEventListener('click', () => { closeModal(m); resolve(null); });
      m.querySelector('[data-action="confirm"]').addEventListener('click', () => {
        const val = m.querySelector('#blockDeviceReason').value.trim();
        closeModal(m);
        resolve(val || 'Bloqué par opérateur');
      });
    });
    if (reason === null) return;
    try {
      await api('/api/v1/device_block.php', { device_id: deviceId, reason });
      setNotif('success', 'Appareil bloqué');
      loadDevicesModal(parentModal);
      loadDevices();
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  async function unblockDevice(deviceId, modal) {
    var ok = await confirmModal({
      title: 'Débloquer l\u2019appareil',
      body: '<p>L\u2019appareil pourra à nouveau voter. Confirmer ?</p>'
    });
    if (!ok) return;
    try {
      await api('/api/v1/device_unblock.php', { device_id: deviceId });
      setNotif('success', 'Appareil débloqué');
      loadDevicesModal(modal);
      loadDevices();
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  async function kickDevice(deviceId, modal) {
    var ok = await confirmModal({
      title: 'Forcer la reconnexion',
      body: '<p>L\u2019appareil sera déconnecté et devra se reconnecter. Confirmer ?</p>'
    });
    if (!ok) return;
    try {
      await api('/api/v1/device_kick.php', { device_id: deviceId, message: 'Reconnexion demandée par opérateur' });
      setNotif('success', 'Demande de reconnexion envoyée');
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  async function loadStatusChecklist() {
    try {
      const { body } = await api(`/api/v1/wizard_status.php?meeting_id=${currentMeetingId}`);
      const d = body?.data || {};

      const checks = [
        { done: d.members_count > 0, text: 'Membres ajoutés', link: '/members.htmx.html' },
        { done: d.present_count > 0, text: 'Présences pointées', link: `#tab-presences` },
        { done: d.motions_total > 0, text: 'Résolutions créées', link: `#tab-resolutions` },
        { done: d.has_president, text: 'Président assigné', optional: true },
        { done: d.policies_assigned, text: 'Politiques configurées', optional: true }
      ];

      const checklist = document.getElementById('statusChecklist');
      checklist.innerHTML = checks.map(c => {
        const iconHtml = c.done ? icon('check', 'icon-sm icon-success') : icon('circle', 'icon-sm icon-muted');
        const cls = c.done ? 'color: var(--color-success)' : 'color: var(--color-text-muted)';
        const style = c.optional ? 'opacity:0.7;font-style:italic;' : '';
        return `<div class="flex items-center gap-2" style="${cls};${style}"><span>${iconHtml}</span> ${c.text}</div>`;
      }).join('');

      // Transition buttons
      const transitions = TRANSITIONS[currentMeetingStatus] || [];
      const actions = document.getElementById('statusActions');
      actions.innerHTML = transitions.map(t => {
        const btnClass = t.to === 'live' ? 'btn-primary' : 'btn-secondary';
        const iconHtml = t.iconName ? icon(t.iconName, 'icon-sm icon-text') : '';
        return `<button class="btn ${btnClass}" data-transition="${t.to}">${iconHtml}${t.label}</button>`;
      }).join('');

      actions.querySelectorAll('[data-transition]').forEach(btn => {
        btn.addEventListener('click', () => doTransition(btn.dataset.transition));
      });

      // Update tab counts
      document.getElementById('tabCountResolutions').textContent = d.motions_total || 0;
      document.getElementById('tabCountPresences').textContent = d.present_count || 0;

      // Update readiness strips on tabs 1-3 — clickable progress steps
      var required = checks.filter(function(c) { return !c.optional; });
      var doneCount = required.filter(function(c) { return c.done; }).length;
      var totalRequired = required.length;

      // Map each check to the tab that resolves it
      var checkTabMap = {
        'Membres ajoutés': 'participants',
        'Présences pointées': 'participants',
        'Résolutions créées': 'ordre-du-jour'
      };

      var stripHtml = '';
      if (doneCount < totalRequired) {
        stripHtml = '<div class="readiness-steps">' +
          required.map(function(c) {
            var cls = c.done ? 'readiness-step done' : 'readiness-step pending';
            var iconName = c.done ? 'check-circle' : 'circle';
            var iconCls = c.done ? 'icon-xs icon-success' : 'icon-xs';
            var targetTab = checkTabMap[c.text] || '';
            var clickAttr = (!c.done && targetTab) ? ' data-goto-tab="' + targetTab + '" role="button" tabindex="0"' : '';
            return '<span class="' + cls + '"' + clickAttr + '>' +
              icon(iconName, iconCls) + ' ' + c.text + '</span>';
          }).join('<span class="readiness-arrow" aria-hidden="true">\u203A</span>') +
          '</div>';
      } else {
        stripHtml = '<div class="readiness-steps all-done">' +
          icon('check-circle', 'icon-sm icon-success') +
          ' <strong>Prêt pour le lancement</strong> — cliquez sur « Ouvrir la séance »' +
          '</div>';
      }
      document.querySelectorAll('[data-readiness-strip]').forEach(function(el) {
        el.innerHTML = stripHtml;
        // Wire up clickable steps
        el.querySelectorAll('[data-goto-tab]').forEach(function(step) {
          step.addEventListener('click', function() { switchTab(step.dataset.gotoTab); });
          step.addEventListener('keydown', function(e) { if (e.key === 'Enter') switchTab(step.dataset.gotoTab); });
        });
      });

      // Update contextual footer CTAs
      updateFooterCTAs(required);

      // Update policies warning
      updatePoliciesWarning();
    } catch (err) {
      setNotif('error', 'Erreur chargement checklist');
    }
  }

  // =========================================================================
  // GUIDED FLOW — Smart navigation + contextual CTAs
  // =========================================================================

  /**
   * After initial data load, auto-navigate to the first incomplete step
   * for draft meetings. Only fires on the first load (not polling refreshes).
   */
  var _hasAutoNavigated = false;
  function autoNavigateToNextStep() {
    // Only auto-navigate on initial load, not on polling
    if (_hasAutoNavigated) return;
    _hasAutoNavigated = true;

    // Skip if not in setup mode, or if URL had an explicit tab request
    if (currentMode !== 'setup') return;
    var urlTab = new URLSearchParams(window.location.search).get('tab');
    if (urlTab) return;

    // Only for draft/scheduled meetings (the "preparation" phase)
    if (!['draft', 'scheduled'].includes(currentMeetingStatus)) return;

    // Determine the first incomplete step
    var hasMembers = membersCache.length > 0;
    var hasAttendance = attendanceCache.some(function(a) { return a.mode === 'present' || a.mode === 'remote'; });
    var hasMotions = motionsCache.length > 0;

    // Show onboarding banner for fresh drafts (no data yet)
    var isFreshDraft = currentMeetingStatus === 'draft' && !hasMembers && !hasMotions;
    var banner = document.getElementById('onboardingBanner');
    if (banner && isFreshDraft) {
      banner.hidden = false;
    }

    var targetTab = null;
    if (!hasMembers || !hasAttendance) {
      targetTab = 'participants';
    } else if (!hasMotions) {
      targetTab = 'ordre-du-jour';
    }

    if (targetTab) {
      switchTab(targetTab);
    }
  }

  /**
   * Update the "next step" footer buttons dynamically based on what's missing.
   */
  function updateFooterCTAs(requiredChecks) {
    if (!requiredChecks) return;

    // Determine what's the logical next step after each tab
    var hasMembers = requiredChecks[0]?.done;
    var hasAttendance = requiredChecks[1]?.done;
    var hasMotions = requiredChecks[2]?.done;
    var allDone = requiredChecks.every(function(c) { return c.done; });

    // Tab Séance footer: what to do next?
    var seanceFooter = document.querySelector('#tab-seance [data-tab-switch="participants"]');
    if (seanceFooter) {
      if (!hasMembers) {
        seanceFooter.innerHTML = icon('arrow-right', 'icon-text') + ' Ajouter des participants';
      } else if (!hasAttendance) {
        seanceFooter.innerHTML = icon('arrow-right', 'icon-text') + ' Pointer les présences';
      } else {
        seanceFooter.innerHTML = 'Participants ' + icon('arrow-right', 'icon-text');
      }
    }

    // Tab Participants footer: what to do next?
    var partFooter = document.querySelector('#tab-participants [data-tab-switch="ordre-du-jour"]');
    if (partFooter) {
      if (!hasMotions) {
        partFooter.innerHTML = icon('arrow-right', 'icon-text') + ' Créer des résolutions';
      } else {
        partFooter.innerHTML = 'Ordre du jour ' + icon('arrow-right', 'icon-text');
      }
    }

    // Tab Ordre du jour footer: contextual guidance
    var odjFooter = document.querySelector('#tab-ordre-du-jour [data-tab-switch="controle"]');
    if (odjFooter) {
      if (allDone) {
        odjFooter.innerHTML = icon('check-circle', 'icon-text icon-success') + ' Prêt — vérifier et lancer';
        odjFooter.classList.remove('btn-secondary');
        odjFooter.classList.add('btn-primary');
      } else {
        odjFooter.innerHTML = 'Contrôle ' + icon('arrow-right', 'icon-text');
        odjFooter.classList.remove('btn-primary');
        odjFooter.classList.add('btn-secondary');
      }
    }
  }

  function updatePoliciesWarning() {
    var qSelect = document.getElementById('settingQuorumPolicy');
    var vSelect = document.getElementById('settingVotePolicy');
    var warning = document.getElementById('noPoliciesWarning');
    if (!warning || !qSelect || !vSelect) return;
    var bothEmpty = !qSelect.value && !vSelect.value;
    warning.hidden = !bothEmpty;
  }

  // =========================================================================
  // SAVE SETTINGS
  // =========================================================================

  async function saveGeneralSettings() {
    var titleInput = document.getElementById('settingTitle');
    var dateInput = document.getElementById('settingDate');

    // Inline validation
    if (!Shared.validateField(titleInput, [
      { test: function (v) { return v.length > 0; }, msg: 'Le titre de la séance est obligatoire' }
    ])) return;

    const title = titleInput.value.trim();
    const scheduledAt = dateInput.value;
    const meetingType = document.querySelector('input[name="meetingType"]:checked')?.value || 'ag_ordinaire';

    const btn = document.getElementById('btnSaveSettings');
    Shared.btnLoading(btn, true);

    try {
      // Gather all values before firing requests
      const quorumPolicyId = document.getElementById('settingQuorumPolicy').value || null;
      const votePolicyId = document.getElementById('settingVotePolicy').value || null;
      const convocationNo = parseInt(document.getElementById('settingConvocation').value) || 1;

      // Fire all 3 saves in parallel — avoids partial save on sequential failure
      var results = await Promise.allSettled([
        api('/api/v1/meetings_update.php', {
          meeting_id: currentMeetingId,
          title: title,
          scheduled_at: scheduledAt || null,
          meeting_type: meetingType
        }),
        api('/api/v1/meeting_quorum_settings.php', {
          meeting_id: currentMeetingId,
          quorum_policy_id: quorumPolicyId,
          convocation_no: convocationNo
        }),
        api('/api/v1/meeting_vote_settings.php', {
          meeting_id: currentMeetingId,
          vote_policy_id: votePolicyId
        })
      ]);

      // Check for partial failures
      var failures = results.filter(function(r) { return r.status === 'rejected'; });
      if (failures.length > 0) {
        var errMsg = failures.map(function(r) { return r.reason?.message || 'Erreur inconnue'; }).join(', ');
        if (failures.length === results.length) {
          throw new Error(errMsg);
        }
        // Partial success — warn but continue
        setNotif('warning', 'Sauvegarde partielle : ' + errMsg);
      }

      // Update local state and header
      currentMeeting.title = title;
      currentMeeting.scheduled_at = scheduledAt;
      currentMeeting.quorum_policy_id = quorumPolicyId;
      currentMeeting.vote_policy_id = votePolicyId;
      currentMeeting.convocation_no = convocationNo;

      updateHeader(currentMeeting);
      loadStatusChecklist();

      if (failures.length === 0) {
        setNotif('success', 'Paramètres enregistrés');
      }

      // Reset dirty-state snapshot
      _captureSettingsSnapshot();

      // Brief visual confirmation on button
      Shared.btnLoading(btn, false);
      btn.classList.add('btn-success');
      btn.classList.remove('btn-primary');
      var origHTML = btn.innerHTML;
      btn.innerHTML = '<svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-check"></use></svg> Enregistré';
      setTimeout(function () {
        btn.classList.remove('btn-success');
        btn.classList.add('btn-primary');
        btn.innerHTML = origHTML;
      }, 2000);
      return; // skip the finally block's btnLoading
    } catch (err) {
      setNotif('error', err.message);
    } finally {
      Shared.btnLoading(btn, false);
    }
  }

  async function savePresident() {
    const presSelect = document.getElementById('settingPresident');
    const presidentId = presSelect.value;
    const isSearchable = presSelect && presSelect.tagName.toLowerCase() === 'ag-searchable-select';

    // Get president name - support both native select and ag-searchable-select
    let presidentName = '';
    if (isSearchable) {
      const selectedOpt = presSelect.selectedOption;
      presidentName = selectedOpt?.label || '';
    } else {
      presidentName = presSelect.options[presSelect.selectedIndex]?.text || '';
    }

    try {
      if (presidentId) {
        // Assign role
        await api('/api/v1/admin_meeting_roles.php', {
          action: 'assign',
          meeting_id: currentMeetingId,
          user_id: presidentId,
          role: 'president'
        });
        // Also save president_name to meeting for PV/validation
        await api('/api/v1/meetings_update.php', {
          meeting_id: currentMeetingId,
          president_name: presidentName
        });
        setNotif('success', 'Président assigné');
      } else {
        // Remove current president
        const { body } = await api(`/api/v1/admin_meeting_roles.php?meeting_id=${currentMeetingId}`);
        const roles = body?.data?.items || [];
        const president = roles.find(r => r.role === 'president');
        if (president) {
          await api('/api/v1/admin_meeting_roles.php', {
            action: 'revoke',
            meeting_id: currentMeetingId,
            user_id: president.user_id,
            role: 'president'
          });
        }
        // Clear president_name
        await api('/api/v1/meetings_update.php', {
          meeting_id: currentMeetingId,
          president_name: ''
        });
        setNotif('success', 'Président retiré');
      }
      loadStatusChecklist();
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  async function addAssessor() {
    // Show modal or prompt for user selection
    const modal = document.createElement('div');
    modal.className = 'modal-backdrop';
    modal.style.cssText = 'position:fixed;inset:0;background:var(--color-backdrop);z-index:var(--z-modal-backdrop, 400);display:flex;align-items:center;justify-content:center;opacity:1;visibility:visible;';

    // Support both wizard and advanced contexts
    const wizPres = document.getElementById('wizPresident');
    const settPres = document.getElementById('settingPresident');
    const presId = (wizPres && !wizPres.closest('[hidden]')) ? wizPres.value : settPres?.value;
    const availableUsers = usersCache.filter(u => u.id !== presId);

    modal.innerHTML = `
      <div style="background:var(--color-surface);border-radius:12px;padding:1.5rem;max-width:400px;width:90%;">
        <h3 style="margin:0 0 1rem;">Ajouter un assesseur</h3>
        <select class="form-input" id="assessorSelect" style="margin-bottom:1rem;">
          <option value="">— Sélectionner —</option>
          ${availableUsers.map(u => `<option value="${u.id}">${escapeHtml(u.name || u.email)}</option>`).join('')}
        </select>
        <div class="flex gap-2 justify-end">
          <button class="btn btn-secondary" id="btnCancelAssessor">Annuler</button>
          <button class="btn btn-primary" id="btnConfirmAssessor">Ajouter</button>
        </div>
      </div>
    `;

    document.body.appendChild(modal);

    document.getElementById('btnCancelAssessor').addEventListener('click', () => modal.remove());
    document.getElementById('btnConfirmAssessor').addEventListener('click', async () => {
      const userId = document.getElementById('assessorSelect').value;
      if (!userId) {
        setNotif('error', 'Sélectionnez un utilisateur');
        return;
      }

      const btn = document.getElementById('btnConfirmAssessor');
      Shared.btnLoading(btn, true);
      try {
        await api('/api/v1/admin_meeting_roles.php', {
          action: 'assign',
          meeting_id: currentMeetingId,
          user_id: userId,
          role: 'assessor'
        });
        setNotif('success', 'Assesseur ajouté');
        loadRoles();
        modal.remove();
      } catch (err) {
        setNotif('error', err.message);
      } finally {
        Shared.btnLoading(btn, false);
      }
    });
  }

  // =========================================================================
  // TAB: PRÉSENCES — delegated to operator-attendance.js
  // =========================================================================

  function loadAttendance()                    { return OpS.fn.loadAttendance(); }
  function renderAttendance()                  { return OpS.fn.renderAttendance(); }
  function updateAttendance(memberId, mode)    { return OpS.fn.updateAttendance(memberId, mode); }
  function markAllPresent()                    { return OpS.fn.markAllPresent(); }
  function showImportCSVModal()                { return OpS.fn.showImportCSVModal(); }

  // =========================================================================
  // TAB: PRÉSENCES - Proxies — delegated to operator-attendance.js
  // =========================================================================

  function loadProxies()                       { return OpS.fn.loadProxies(); }
  function renderProxies()                     { return OpS.fn.renderProxies(); }
  function revokeProxy(giverId)                { return OpS.fn.revokeProxy(giverId); }
  function showAddProxyModal()                 { return OpS.fn.showAddProxyModal(); }
  function showImportProxiesCSVModal()         { return OpS.fn.showImportProxiesCSVModal(); }

  // =========================================================================
  // TAB: PAROLE — delegated to operator-speech.js
  // State kept here for OpS bridge defineProperty closures.
  // =========================================================================

  let speechQueueCache = [];
  let currentSpeakerCache = null;
  let speechTimerInterval = null;
  let previousQueueIds = new Set();

  // Delegating stubs — call through OpS.fn so the sub-module implementation runs
  function loadSpeechQueue()               { return OpS.fn.loadSpeechQueue(); }
  function renderCurrentSpeaker()          { return OpS.fn.renderCurrentSpeaker(); }
  function renderSpeechQueue()             { return OpS.fn.renderSpeechQueue(); }
  function grantSpeech(memberId)           { return OpS.fn.grantSpeech(memberId); }
  function nextSpeaker()                   { return OpS.fn.nextSpeaker(); }
  function endCurrentSpeech()              { return OpS.fn.endCurrentSpeech(); }
  function cancelSpeechRequest(requestId)  { return OpS.fn.cancelSpeechRequest(requestId); }
  function clearSpeechHistory()            { return OpS.fn.clearSpeechHistory(); }
  function showAddToQueueModal()           { return OpS.fn.showAddToQueueModal(); }

  // =========================================================================
  // TAB: RÉSOLUTIONS, VOTE, RÉSULTATS, TRANSITIONS
  // — delegated to operator-motions.js
  // State kept here for OpS bridge defineProperty closures.
  // =========================================================================

  let ballotSourceCache = {};

  function loadResolutions()                   { return OpS.fn.loadResolutions(); }
  function initializePreviousMotionState()     { return OpS.fn.initializePreviousMotionState(); }
  function renderResolutions()                 { return OpS.fn.renderResolutions(); }
  function showEditResolutionModal(motionId)   { return OpS.fn.showEditResolutionModal(motionId); }
  function moveResolution(motionId, direction) { return OpS.fn.moveResolution(motionId, direction); }
  function createResolution()                  { return OpS.fn.createResolution(); }
  function loadVoteTab()                       { return OpS.fn.loadVoteTab(); }
  function renderQuickOpenList()               { return OpS.fn.renderQuickOpenList(); }
  function loadBallots(motionId)               { return OpS.fn.loadBallots(motionId); }
  function renderManualVoteList()              { return OpS.fn.renderManualVoteList(); }
  function castManualVote(memberId, vote)      { return OpS.fn.castManualVote(memberId, vote); }
  function applyUnanimity(voteType)            { return OpS.fn.applyUnanimity(voteType); }
  function openVote(motionId)                  { return OpS.fn.openVote(motionId); }
  function closeVote(motionId)                 { return OpS.fn.closeVote(motionId); }
  function loadResults()                       { return OpS.fn.loadResults(); }
  function getCloseSessionState()              { return OpS.fn.getCloseSessionState(); }
  function updateCloseSessionStatus()          { return OpS.fn.updateCloseSessionStatus(); }
  function updateExecCloseSession()            { return OpS.fn.updateExecCloseSession(); }
  function closeSession()                      { return OpS.fn.closeSession(); }
  function doTransition(toStatus)              { return OpS.fn.doTransition(toStatus); }


  // =========================================================================
  // MODE SWITCH (Préparation / Exécution)
  // =========================================================================

  /**
   * Switch between Préparation (setup) and Exécution (exec) modes.
   * @param {string} mode - 'setup' or 'exec'
   * @param {Object} [opts] - Options
   * @param {string} [opts.tab] - Force a specific tab when entering setup mode
   * @param {boolean} [opts.restoreTab] - Restore last active prep tab (default true for manual switches)
   */
  function setMode(mode, opts = {}) {
    // Prevent entering exec mode if meeting is not live
    if (mode === 'exec' && currentMeetingStatus !== 'live') {
      mode = 'setup';
    }

    // No-op if already in the requested mode and view is visible (unless forcing a tab)
    const alreadyVisible = mode === 'setup' ? (viewSetup && !viewSetup.hidden) : (viewExec && !viewExec.hidden);
    if (mode === currentMode && !opts.tab && alreadyVisible) return;

    // Save last active prep tab when leaving setup
    if (currentMode === 'setup' && mode === 'exec') {
      const activeTab = document.querySelector('.tab-btn.active');
      if (activeTab) lastSetupTab = activeTab.dataset.tab;
    }

    currentMode = mode;

    // Update mode switch button states
    if (btnModeSetup) {
      btnModeSetup.classList.toggle('active', mode === 'setup');
      btnModeSetup.setAttribute('aria-pressed', String(mode === 'setup'));
    }
    if (btnModeExec) {
      btnModeExec.classList.toggle('active', mode === 'exec');
      btnModeExec.setAttribute('aria-pressed', String(mode === 'exec'));
      btnModeExec.disabled = currentMeetingStatus !== 'live';
    }

    // Show/hide mode switch: only useful when meeting is live or recently closed
    const modeSwitch = document.getElementById('modeSwitch');
    if (modeSwitch) {
      modeSwitch.hidden = !['live', 'closed'].includes(currentMeetingStatus);
    }

    // Cross-fade views
    const incoming = mode === 'setup' ? viewSetup : viewExec;
    const outgoing = mode === 'setup' ? viewExec : viewSetup;

    if (outgoing) outgoing.hidden = true;
    if (incoming) {
      incoming.hidden = false;
      incoming.classList.remove('view-entering');
      void incoming.offsetWidth; // force reflow for re-trigger
      incoming.classList.add('view-entering');
    }

    if (mode === 'setup') {
      Shared.show(tabsNav, 'flex');
      // Restore last active prep tab or apply forced tab
      const targetTab = opts.tab || (opts.restoreTab !== false ? lastSetupTab : null);
      if (targetTab) switchTab(targetTab);
    } else {
      Shared.hide(tabsNav);
      refreshExecView();
      startSessionTimer();
    }

    updatePrimaryButton();
    updateContextHint();
    announce(mode === 'setup' ? 'Mode préparation activé' : 'Mode exécution activé');
  }

  /** @type {string|null} Current primary button action name */
  let primaryAction = null;

  function updatePrimaryButton() {
    if (!btnPrimary) return;

    if (!currentMeetingId) {
      btnPrimary.disabled = true;
      btnPrimary.textContent = 'Ouvrir la séance';
      primaryAction = null;
      return;
    }

    if (currentMode === 'setup') {
      if (['draft', 'scheduled', 'frozen'].includes(currentMeetingStatus)) {
        const score = getConformityScore();
        // All 3 mandatory prerequisites must be met (members, attendance, rules)
        btnPrimary.disabled = score < 3;
        btnPrimary.textContent = 'Ouvrir la séance';
        primaryAction = 'launch';
        // Explain why button is disabled
        if (score < 3) {
          var missing = [];
          if (membersCache.length === 0) missing.push('membres');
          if (!attendanceCache.some(a => a.mode === 'present' || a.mode === 'remote')) missing.push('présences');
          var hasQ = !!(currentMeeting && currentMeeting.quorum_policy_id);
          var presEl = document.getElementById('settingPresident');
          var hasP = !!(presEl && presEl.value);
          if (!hasQ && !hasP) missing.push('politique de vote ou président');
          btnPrimary.title = 'Manque : ' + missing.join(', ');
        } else {
          btnPrimary.title = '';
        }
      } else if (currentMeetingStatus === 'live') {
        btnPrimary.disabled = false;
        btnPrimary.textContent = 'Passer en exécution';
        primaryAction = 'exec';
      } else if (currentMeetingStatus === 'paused') {
        btnPrimary.disabled = false;
        btnPrimary.textContent = 'Reprendre la séance';
        primaryAction = 'resume';
      } else {
        btnPrimary.disabled = true;
        btnPrimary.textContent = 'Séance terminée';
        primaryAction = null;
      }
    } else {
      // Exec mode
      if (currentOpenMotion) {
        btnPrimary.disabled = false;
        btnPrimary.textContent = 'Voir le vote';
        primaryAction = 'scroll-vote';
      } else {
        // When no vote is open, check if we can close the session
        const allDone = motionsCache.length > 0 && motionsCache.every(m => m.closed_at);
        if (allDone) {
          btnPrimary.disabled = false;
          btnPrimary.textContent = 'Clôturer la séance';
          primaryAction = 'close-session';
        } else {
          btnPrimary.disabled = false;
          btnPrimary.textContent = 'Préparation';
          primaryAction = 'setup';
        }
      }
    }
  }

  if (btnPrimary) {
    btnPrimary.addEventListener('click', () => {
      if (primaryAction === 'launch') launchSession();
      else if (primaryAction === 'exec') setMode('exec');
      else if (primaryAction === 'setup') setMode('setup');
      else if (primaryAction === 'resume') doTransition('live');
      else if (primaryAction === 'close-session') closeSession();
      else if (primaryAction === 'scroll-vote') {
        const el = document.getElementById('execVoteCard');
        if (el) el.scrollIntoView({ behavior: 'smooth' });
      }
    });
  }

  function updateContextHint() {
    if (!contextHint) return;

    if (!currentMeetingId) {
      contextHint.textContent = 'Sélectionnez une séance…';
      return;
    }

    if (currentMode === 'setup') {
      if (['closed', 'validated'].includes(currentMeetingStatus)) {
        contextHint.textContent = 'Séance clôturée — consultez les résultats.';
      } else if (currentMeetingStatus === 'archived') {
        contextHint.textContent = 'Séance archivée.';
      } else if (currentMeetingStatus === 'paused') {
        contextHint.textContent = 'Séance en pause — reprenez ou clôturez.';
      } else if (currentMeetingStatus === 'live') {
        contextHint.textContent = 'Séance en cours — basculez en exécution.';
      } else {
        const score = getConformityScore();
        if (score >= 3) {
          contextHint.textContent = 'Séance prête — vous pouvez lancer.';
        } else {
          contextHint.textContent = 'Préparez la séance (' + score + '/3 pré-requis validés).';
        }
      }
    } else {
      if (currentOpenMotion) {
        contextHint.textContent = 'Vote en cours : ' + (currentOpenMotion.title || '');
      } else if (currentMeetingStatus === 'live') {
        const allDone = motionsCache.length > 0 && motionsCache.every(m => m.closed_at);
        if (allDone) {
          contextHint.textContent = 'Tous les votes sont terminés — prêt à clôturer.';
        } else {
          contextHint.textContent = 'Séance en cours — aucun vote ouvert.';
        }
      } else {
        contextHint.textContent = 'Mode exécution.';
      }
    }
  }

  function announce(msg) {
    if (srAnnounce) srAnnounce.textContent = msg;
  }

  // =========================================================================
  // CLOCK
  // =========================================================================

  let _clockInterval = null;
  function startClock() {
    if (_clockInterval) clearInterval(_clockInterval);
    function tick() {
      const now = new Date();
      if (barClock) {
        barClock.textContent = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
      }
    }
    tick();
    _clockInterval = setInterval(tick, 30000);
  }

  // Session elapsed timer
  let sessionTimerInterval = null;

  function startSessionTimer() {
    if (sessionTimerInterval) clearInterval(sessionTimerInterval);
    const el = document.getElementById('execSessionTimer');
    if (!el) return;

    // Use the meeting's opened_at if live, otherwise show --:--
    if (currentMeetingStatus !== 'live' || !currentMeeting) {
      el.textContent = '--:--';
      return;
    }

    const startedAt = currentMeeting.opened_at || currentMeeting.started_at;
    if (!startedAt) {
      el.textContent = '00:00';
      return;
    }

    const startTime = new Date(startedAt).getTime();

    function updateTimer() {
      const elapsed = Math.floor((Date.now() - startTime) / 1000);
      const hours = Math.floor(elapsed / 3600);
      const minutes = Math.floor((elapsed % 3600) / 60);
      const seconds = elapsed % 60;
      if (hours > 0) {
        el.textContent = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
      } else {
        el.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
      }
    }

    updateTimer();
    sessionTimerInterval = setInterval(updateTimer, 1000);
  }

  // =========================================================================
  // CONFORMITY CHECKLIST
  // =========================================================================

  function getConformityScore() {
    let score = 0;
    // 1. Members
    if (membersCache.length > 0) score++;
    // 2. Attendance
    if (attendanceCache.some(a => a.mode === 'present' || a.mode === 'remote')) score++;
    // 3. Rules & presidency
    const hasQuorum = !!(currentMeeting && currentMeeting.quorum_policy_id);
    const presEl = document.getElementById('settingPresident');
    const hasPresident = !!(presEl && presEl.value);
    if (hasQuorum || hasPresident) score++;
    // Note: Convocations is optional and not counted in the mandatory score
    return score;
  }

  function renderConformityChecklist() {
    const checklist = document.getElementById('conformityChecklist');
    if (!checklist) return;

    const hasMembers = membersCache.length > 0;
    const presentCount = attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote').length;
    const hasPresent = presentCount > 0;
    const activeProxies = proxiesCache.filter(p => !p.revoked_at).length;
    const hasQuorum = !!(currentMeeting && currentMeeting.quorum_policy_id);
    const presEl = document.getElementById('settingPresident');
    const hasPresident = !!(presEl && presEl.value);
    const hasRules = hasQuorum || hasPresident;

    const steps = [
      {
        key: 'members',
        label: 'Membres inscrits',
        done: hasMembers,
        status: hasMembers ? membersCache.length + ' membre' + (membersCache.length > 1 ? 's' : '') : 'à faire',
        hint: 'Au moins un membre actif'
      },
      {
        key: 'attendance',
        label: 'Présences enregistrées',
        done: hasPresent,
        status: hasPresent
          ? presentCount + ' présent' + (presentCount > 1 ? 's' : '') + (activeProxies ? ', ' + activeProxies + ' proc.' : '')
          : 'à faire',
        hint: 'Marquer au moins un membre comme présent'
      },
      {
        key: 'rules',
        label: 'Politique de vote ou président',
        done: hasRules,
        status: hasRules
          ? (hasPresident ? 'président assigné' : 'politique configurée')
          : 'à faire',
        hint: 'Définir une politique de vote ou nommer un président'
      },
      {
        key: 'convocations',
        label: 'Invitations envoyées',
        done: true,
        optional: true,
        status: 'optionnel'
      }
    ];

    // Show/hide guided empty states in tabs
    var noMembersGuide = document.getElementById('noMembersGuide');
    var noResolutionsGuide = document.getElementById('noResolutionsGuide');
    var presenceToolbar = document.getElementById('presenceToolbar');
    if (noMembersGuide) {
      noMembersGuide.hidden = hasMembers;
      // Hide presence toolbar when no members
      if (presenceToolbar) presenceToolbar.style.display = hasMembers ? '' : 'none';
    }
    // Show hint on Séance tab when no members registered
    var noMembersHint = document.getElementById('noMembersHint');
    if (noMembersHint) noMembersHint.hidden = hasMembers;
    if (noResolutionsGuide) {
      noResolutionsGuide.hidden = motionsCache.length > 0;
    }

    // Score only counts non-optional items
    const mandatorySteps = steps.filter(s => !s.optional);
    const score = mandatorySteps.filter(s => s.done).length;
    const maxScore = mandatorySteps.length;

    // Map checklist steps to tabs for navigation
    const stepTabMap = { members: 'participants', attendance: 'participants', convocations: 'controle', rules: 'seance' };

    checklist.innerHTML = steps.map(s => {
      const doneClass = s.done ? 'done' : '';
      const optClass = s.optional ? 'optional' : '';
      const iconClass = s.done ? 'done' : 'pending';
      const tab = stepTabMap[s.key] || '';
      const hintHtml = s.hint && !s.done ? '<span class="conformity-hint">' + escapeHtml(s.hint) + '</span>' : '';
      return '<div class="conformity-item ' + doneClass + ' ' + optClass + '" data-step="' + s.key + '"'
        + (tab && !s.done ? ' data-goto-tab="' + tab + '" role="button" tabindex="0" title="Aller à l\'onglet"' : '')
        + '>'
        + '<span class="conformity-icon ' + iconClass + '"></span>'
        + '<span class="conformity-label">' + s.label + (s.optional ? ' <span class="text-xs text-muted">(optionnel)</span>' : '') + '</span>'
        + hintHtml
        + '<span class="conformity-status">' + s.status + '</span>'
        + '</div>';
    }).join('');

    // Clickable incomplete items navigate to relevant tab
    checklist.querySelectorAll('[data-goto-tab]').forEach(item => {
      item.addEventListener('click', () => switchTab(item.dataset.gotoTab));
    });

    // Update score display
    const setupScoreEl = document.getElementById('setupScore');
    if (setupScoreEl) setupScoreEl.textContent = score + '/' + maxScore;

    // Update health chip
    updateHealthChip(score, maxScore);

    // Update primary button and context
    updatePrimaryButton();
    updateContextHint();
  }

  function updateHealthChip(score, maxScore) {
    if (!healthChip) return;
    var max = maxScore || 3;

    if (!currentMeetingId) {
      healthChip.hidden = true;
      return;
    }

    healthChip.hidden = false;
    if (healthScore) healthScore.textContent = score + '/' + max;
    if (healthHint) healthHint.textContent = 'pré-requis';

    const dot = healthChip.querySelector('.health-dot');
    if (dot) {
      dot.classList.remove('ok', 'warn', 'danger');
      if (score >= max) dot.classList.add('ok');
      else if (score >= Math.ceil(max / 2)) dot.classList.add('warn');
      else dot.classList.add('danger');
    }
  }

  // =========================================================================
  // ALERTS
  // =========================================================================

  function collectAlerts() {
    const alerts = [];

    if (!currentMeetingId) return alerts;

    // Specific missing prerequisites
    const hasMembers = membersCache.length > 0;
    const hasPresent = attendanceCache.some(a => a.mode === 'present' || a.mode === 'remote');
    const hasMotions = motionsCache.length > 0;
    const hasQuorum = !!(currentMeeting && currentMeeting.quorum_policy_id);
    const presEl = document.getElementById('settingPresident');
    const hasPresident = !!(presEl && presEl.value);
    const hasRules = hasQuorum || hasPresident;

    if (!hasMembers) {
      alerts.push({
        title: 'Aucun membre',
        message: 'Ajoutez des membres dans l\u2019onglet Participants.',
        severity: 'critical',
        tab: 'participants'
      });
    }
    if (hasMembers && !hasPresent) {
      alerts.push({
        title: 'Aucun présent enregistré',
        message: 'Pointez les présences dans l\u2019onglet Participants.',
        severity: 'critical',
        tab: 'participants'
      });
    }
    if (!hasMotions) {
      alerts.push({
        title: 'Aucune résolution',
        message: 'Créez au moins une résolution dans l\u2019Ordre du jour.',
        severity: 'warning',
        tab: 'ordre-du-jour'
      });
    }
    if (!hasRules) {
      alerts.push({
        title: 'Ni quorum ni président',
        message: 'Configurez le quorum ou désignez un président dans Séance.',
        severity: 'info',
        tab: 'seance'
      });
    }

    // Quorum check (when live) — use configured threshold from policy
    if (['live', 'paused'].includes(currentMeetingStatus) && currentMeeting && currentMeeting.quorum_policy_id) {
      const present = attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote').length;
      const proxyActive = proxiesCache.filter(p => !p.revoked_at).length;
      const total = present + proxyActive;
      const policy = policiesCache.quorum.find(p => p.id === currentMeeting.quorum_policy_id);
      const threshold = policy?.threshold ? parseFloat(policy.threshold) : 0.5;
      const required = Math.ceil(membersCache.length * threshold);
      if (membersCache.length > 0 && total < required) {
        alerts.push({
          title: 'Quorum potentiellement non atteint',
          message: total + ' votants / ' + required + ' requis (' + Math.round(threshold * 100) + '%).',
          severity: 'warning'
        });
      }
    }

    // Open vote with no votes
    if (currentOpenMotion) {
      const totalBallots = Object.keys(ballotsCache).length;
      if (totalBallots === 0) {
        alerts.push({
          title: 'Aucun vote enregistré',
          message: 'Le vote « ' + (currentOpenMotion.title || '') + ' » est ouvert mais aucun bulletin reçu.',
          severity: 'info'
        });
      }
    }

    return alerts;
  }

  function renderAlertsPanel(targetId, countId) {
    const target = document.getElementById(targetId);
    const countEl = document.getElementById(countId);
    if (!target) return;

    const alerts = collectAlerts();
    if (countEl) countEl.textContent = alerts.length;

    if (alerts.length === 0) {
      target.innerHTML = '<div class="alert-empty">Aucune alerte.</div>';
      return;
    }

    target.innerHTML = alerts.map(a => {
      const tabLink = a.tab
        ? ' <button class="btn btn-sm btn-ghost" style="margin-top:0.25rem" data-alert-go="' + escapeHtml(a.tab) + '">Aller \u00e0 l\u2019onglet &rarr;</button>'
        : '';
      return '<div class="alert-item ' + escapeHtml(a.severity) + '">'
        + '<div><div class="alert-item-title">' + escapeHtml(a.title) + '</div>'
        + '<div class="alert-item-message">' + escapeHtml(a.message) + '</div>'
        + tabLink + '</div>'
        + '</div>';
    }).join('');

    // Bind "go to tab" buttons
    target.querySelectorAll('[data-alert-go]').forEach(btn => {
      btn.addEventListener('click', () => switchTab(btn.dataset.alertGo));
    });
  }

  function refreshAlerts() {
    renderAlertsPanel('setupAlertsList', 'setupAlertCount');
    renderAlertsPanel('execAlertsList', 'execAlertCount');
    // Update Contrôle tab badge
    const count = parseInt(document.getElementById('setupAlertCount')?.textContent || '0');
    const badge = document.getElementById('tabCountAlerts');
    if (badge) { badge.textContent = count; badge.hidden = count === 0; }
  }

  // =========================================================================
  // EXECUTION VIEW
  // =========================================================================

  function refreshExecKPIs() {
    // Quorum bar
    const qBar = document.getElementById('execQuorumBar');
    if (qBar) {
      const present = attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote').length;
      const proxyActive = proxiesCache.filter(p => !p.revoked_at).length;
      const currentVoters = present + proxyActive;
      const totalMembers = membersCache.length;
      const policy = policiesCache.quorum.find(p => p.id === (currentMeeting?.quorum_policy_id));
      const threshold = policy?.threshold ? parseFloat(policy.threshold) : 0.5;
      const required = Math.ceil(totalMembers * threshold);
      qBar.setAttribute('current', currentVoters);
      qBar.setAttribute('required', required);
      qBar.setAttribute('total', totalMembers);
    }

    // Participation %
    const partEl = document.getElementById('execParticipation');
    if (partEl && currentOpenMotion) {
      const totalBallots = Object.keys(ballotsCache).length;
      const eligible = attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote').length +
                       proxiesCache.filter(p => !p.revoked_at).length;
      const pct = eligible > 0 ? Math.round((totalBallots / eligible) * 100) : 0;
      partEl.textContent = pct + '%';
      partEl.style.color = pct >= 75 ? 'var(--color-success)' : pct >= 50 ? 'var(--color-warning)' : 'var(--color-text-muted)';
    } else if (partEl) {
      partEl.textContent = '—';
      partEl.style.color = '';
    }

    // Motions progress
    const doneEl = document.getElementById('execMotionsDone');
    const totalEl = document.getElementById('execMotionsTotal');
    if (doneEl && totalEl) {
      const closed = motionsCache.filter(m => m.closed_at).length;
      doneEl.textContent = closed;
      totalEl.textContent = motionsCache.length;
    }

    // Vote participation bar in exec
    const barFill = document.getElementById('execVoteParticipationBar');
    const barPct = document.getElementById('execVoteParticipationPct');
    if (barFill && barPct) {
      if (currentOpenMotion) {
        const totalBallots = Object.keys(ballotsCache).length;
        const eligible = attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote').length +
                         proxiesCache.filter(p => !p.revoked_at).length;
        const pct = eligible > 0 ? Math.round((totalBallots / eligible) * 100) : 0;
        barFill.style.width = pct + '%';
        barPct.textContent = pct + '%';
      } else {
        barFill.style.width = '0%';
        barPct.textContent = '—';
      }
    }
  }

  function refreshExecView() {
    refreshExecKPIs();
    refreshExecVote();
    refreshExecSpeech();
    refreshExecDevices();
    refreshExecManualVotes();
    refreshAlerts();
    updateExecCloseSession();
  }

  function refreshExecVote() {
    const titleEl = document.getElementById('execVoteTitle');
    const forEl = document.getElementById('execVoteFor');
    const againstEl = document.getElementById('execVoteAgainst');
    const abstainEl = document.getElementById('execVoteAbstain');
    const liveBadge = document.getElementById('execLiveBadge');
    const btnClose = document.getElementById('execBtnCloseVote');
    const noVotePanel = document.getElementById('execNoVote');
    const activeVotePanel = document.getElementById('execActiveVote');

    if (currentOpenMotion) {
      // Show active vote, hide no-vote placeholder
      if (noVotePanel) Shared.hide(noVotePanel);
      if (activeVotePanel) activeVotePanel.hidden = false;

      if (titleEl) titleEl.textContent = currentOpenMotion.title;
      if (liveBadge) Shared.show(liveBadge);
      if (btnClose) { btnClose.disabled = false; Shared.show(btnClose); }

      let fc = 0, ac = 0, ab = 0;
      Object.values(ballotsCache).forEach(v => {
        if (v === 'for') fc++;
        else if (v === 'against') ac++;
        else if (v === 'abstain') ab++;
      });

      if (forEl) forEl.textContent = fc;
      if (againstEl) againstEl.textContent = ac;
      if (abstainEl) abstainEl.textContent = ab;
    } else {
      // Show no-vote placeholder with quick-open buttons, hide active vote
      if (noVotePanel) Shared.show(noVotePanel, 'block');
      if (activeVotePanel) activeVotePanel.hidden = true;

      if (liveBadge) Shared.hide(liveBadge);
      if (btnClose) { btnClose.disabled = true; Shared.hide(btnClose); }

      renderExecQuickOpenList();
    }
  }

  function renderExecQuickOpenList() {
    const list = document.getElementById('execQuickOpenList');
    if (!list) return;

    const isLive = currentMeetingStatus === 'live';
    const openableMotions = motionsCache.filter(m => !m.opened_at && !m.closed_at);

    if (!isLive || openableMotions.length === 0) {
      list.innerHTML = isLive
        ? '<p class="text-muted text-sm">Aucune résolution en attente</p>'
        : '<p class="text-muted text-sm">La séance doit être en cours pour ouvrir un vote</p>';
      return;
    }

    list.innerHTML = openableMotions.slice(0, 5).map((m, i) => `
      <button class="btn btn-primary btn-quick-open" data-motion-id="${m.id}">
        ${icon('play', 'icon-sm icon-text')}${i + 1}. ${escapeHtml(m.title.length > 30 ? m.title.substring(0, 30) + '...' : m.title)}
      </button>
    `).join('');

    if (openableMotions.length > 5) {
      list.innerHTML += `<span class="text-muted text-sm">+ ${openableMotions.length - 5} autres</span>`;
    }

    list.querySelectorAll('.btn-quick-open').forEach(btn => {
      btn.addEventListener('click', () => openVote(btn.dataset.motionId));
    });
  }

  let execSpeechTimerInterval = null;

  function refreshExecSpeech() {
    const speakerInfo = document.getElementById('execSpeakerInfo');
    const actionsEl = document.getElementById('execSpeechActions');
    const queueList = document.getElementById('execSpeechQueue');

    // Clear exec timer
    if (execSpeechTimerInterval) {
      clearInterval(execSpeechTimerInterval);
      execSpeechTimerInterval = null;
    }

    if (speakerInfo) {
      if (currentSpeakerCache) {
        const name = escapeHtml(currentSpeakerCache.full_name || '—');
        const startTime = currentSpeakerCache.updated_at ? new Date(currentSpeakerCache.updated_at).getTime() : Date.now();
        speakerInfo.innerHTML =
          '<div class="exec-speaker-active">' +
            '<svg class="icon icon-text exec-speaker-mic" aria-hidden="true"><use href="/assets/icons.svg#icon-mic"></use></svg>' +
            '<strong>' + name + '</strong>' +
            '<span class="exec-speaker-timer" id="execSpeakerTimer">00:00</span>' +
          '</div>';
        // Start live timer
        function updateExecTimer() {
          const el = document.getElementById('execSpeakerTimer');
          if (!el) return;
          const elapsed = Math.floor((Date.now() - startTime) / 1000);
          const mm = String(Math.floor(elapsed / 60)).padStart(2, '0');
          const ss = String(elapsed % 60).padStart(2, '0');
          el.textContent = mm + ':' + ss;
        }
        updateExecTimer();
        execSpeechTimerInterval = setInterval(updateExecTimer, 1000);
      } else {
        speakerInfo.innerHTML = '<span class="text-sm text-muted">Aucun orateur</span>';
      }
    }

    // Show/hide action buttons
    if (actionsEl) {
      actionsEl.style.display = currentSpeakerCache ? '' : 'none';
    }

    if (queueList) {
      if (speechQueueCache.length === 0) {
        queueList.innerHTML = '<span class="text-muted text-sm">File vide</span>';
      } else {
        queueList.innerHTML = '<div class="text-sm text-muted mb-1">File (' + speechQueueCache.length + ') :</div>' +
          speechQueueCache.slice(0, 5).map(function(s, i) {
            return '<div class="text-sm">' + (i + 1) + '. ' + escapeHtml(s.full_name || '—') + '</div>';
          }).join('');
        if (speechQueueCache.length > 5) {
          queueList.innerHTML += '<div class="text-sm text-muted">+ ' + (speechQueueCache.length - 5) + ' autres</div>';
        }
      }
    }
  }

  function refreshExecDevices() {
    const devOnlineEl = document.getElementById('devOnline');
    const devStaleEl = document.getElementById('devStale');
    const execOnline = document.getElementById('execDevOnline');
    const execStale = document.getElementById('execDevStale');

    if (execOnline && devOnlineEl) execOnline.textContent = devOnlineEl.textContent;
    if (execStale && devStaleEl) execStale.textContent = devStaleEl.textContent;
  }

  function refreshExecManualVotes() {
    const list = document.getElementById('execManualVoteList');
    if (!list) return;

    if (!currentOpenMotion) {
      list.innerHTML = '<span class="text-muted text-sm">Aucun vote actif</span>';
      return;
    }

    const searchInput = document.getElementById('execManualSearch');
    const searchTerm = (searchInput ? searchInput.value : '').toLowerCase();
    let voters = attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote');

    if (searchTerm) {
      voters = voters.filter(v => (v.full_name || '').toLowerCase().includes(searchTerm));
    }

    var shown = voters.slice(0, 20);
    var remaining = voters.length - shown.length;
    list.innerHTML = shown.map(function(v) {
      var vote = ballotsCache[v.member_id];
      return '<div class="exec-manual-vote-row" data-member-id="' + v.member_id + '">'
        + '<span class="text-sm">' + escapeHtml(v.full_name || '\u2014') + '</span>'
        + '<div class="flex gap-1">'
        + '<button class="btn btn-xs ' + (vote === 'for' ? 'btn-success' : 'btn-ghost') + '" data-vote="for" aria-label="Pour \u2014 ' + escapeHtml(v.full_name || '') + '">Pour</button>'
        + '<button class="btn btn-xs ' + (vote === 'against' ? 'btn-danger' : 'btn-ghost') + '" data-vote="against" aria-label="Contre \u2014 ' + escapeHtml(v.full_name || '') + '">Contre</button>'
        + '<button class="btn btn-xs ' + (vote === 'abstain' ? 'btn-warning' : 'btn-ghost') + '" data-vote="abstain" aria-label="Abstention \u2014 ' + escapeHtml(v.full_name || '') + '">Abst.</button>'
        + '</div></div>';
    }).join('') + (remaining > 0 ? '<div class="text-xs text-muted text-center mt-2">+ ' + remaining + ' votants non affichés</div>' : '')
    || '<span class="text-muted text-sm">Aucun votant</span>';

    // Bind vote buttons
    list.querySelectorAll('[data-vote]').forEach(function(btn) {
      btn.addEventListener('click', async function() {
        const row = btn.closest('[data-member-id]');
        const memberId = row.dataset.memberId;
        const voteType = btn.dataset.vote;
        if (ballotsCache[memberId] === voteType) return;
        await castManualVote(memberId, voteType);
        refreshExecManualVotes();
      });
    });
  }

  // =========================================================================
  // INIT
  // =========================================================================

  meetingSelect.addEventListener('change', () => loadMeetingContext(meetingSelect.value));

  // Debounce helper for search inputs (avoid re-render on every keystroke)
  function debounce(fn, ms = 250) {
    let t;
    return function (...args) { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), ms); };
  }

  // Presence search
  document.getElementById('presenceSearch')?.addEventListener('input', debounce(renderAttendance));
  document.getElementById('btnMarkAllPresent')?.addEventListener('click', markAllPresent);

  // Import CSV button (members)
  document.getElementById('btnImportCSV')?.addEventListener('click', showImportCSVModal);

  // Import attendance CSV button
  document.getElementById('btnImportAttendanceCSV')?.addEventListener('click', function () {
    if (!currentMeetingId) { setNotif('error', 'Aucune s\u00e9ance s\u00e9lectionn\u00e9e'); return; }

    Shared.openModal({
      title: 'Importer les pr\u00e9sences (CSV)',
      body:
        '<p class="text-muted text-sm mb-3">' +
          'Format attendu : <code>name,email,mode,notes</code> (en-t\u00eate requis).<br>' +
          'Colonnes requises : <code>name</code> ou <code>email</code>. Mode : present, absent, excused, proxy.' +
        '</p>' +
        '<div class="form-group mb-3">' +
          '<label class="form-label">Fichier CSV</label>' +
          '<input type="file" class="form-input" id="attendanceCsvFile" accept=".csv,.txt">' +
        '</div>' +
        '<div id="attendanceImportResult" hidden></div>',
      confirmText: 'Importer',
      onConfirm: async function (modal) {
        var fileInput = modal.querySelector('#attendanceCsvFile');
        if (!fileInput.files.length) {
          Shared.fieldError(fileInput, 'S\u00e9lectionnez un fichier CSV');
          return false;
        }

        var confirmBtn = modal.querySelector('.modal-confirm-btn');
        Shared.btnLoading(confirmBtn, true);

        var formData = new FormData();
        formData.append('file', fileInput.files[0]);
        formData.append('meeting_id', currentMeetingId);

        try {
          var { body: d } = await apiUpload('/api/v1/attendances_import_csv.php', formData);

          var resultDiv = modal.querySelector('#attendanceImportResult');
          resultDiv.hidden = false;

          if (d.ok) {
            var imported = d.data ? d.data.imported : (d.imported || 0);
            var skipped = d.data ? d.data.skipped : (d.skipped || 0);
            var errors = d.data ? d.data.errors : (d.errors || []);

            resultDiv.innerHTML =
              '<div class="alert alert-success mb-2"><strong>' + imported + ' pr\u00e9sence' + (imported > 1 ? 's' : '') + ' import\u00e9e' + (imported > 1 ? 's' : '') + '</strong>' +
              (skipped > 0 ? ', ' + skipped + ' ignor\u00e9e' + (skipped > 1 ? 's' : '') : '') +
              '</div>' +
              (errors.length > 0 ? '<div class="text-sm text-danger">' + errors.slice(0, 5).map(function (e) { return 'Ligne ' + e.line + ' : ' + escapeHtml(e.error); }).join('<br>') + '</div>' : '');

            setNotif('success', imported + ' pr\u00e9sences import\u00e9es');
            loadAttendance();
          } else {
            resultDiv.innerHTML = '<div class="alert alert-danger">' + escapeHtml(d.error || 'Erreur d\u2019import') + '</div>';
          }
        } catch (e) {
          setNotif('error', 'Erreur r\u00e9seau');
        } finally {
          Shared.btnLoading(confirmBtn, false);
        }
        return false; // Keep modal open to show results
      }
    });
  });

  // Add proxy button
  document.getElementById('btnAddProxy')?.addEventListener('click', showAddProxyModal);

  // Proxy search
  document.getElementById('proxySearch')?.addEventListener('input', debounce(renderProxies));

  // Import proxies CSV button
  document.getElementById('btnImportProxiesCSV')?.addEventListener('click', showImportProxiesCSVModal);

  // Resolution search
  document.getElementById('resolutionSearch')?.addEventListener('input', debounce(renderResolutions));
  document.getElementById('btnAddResolution')?.addEventListener('click', () => {
    Shared.show(document.getElementById('addResolutionForm'), 'block');
  });
  // Guide button for empty state also triggers the add form
  document.getElementById('btnGuideAddResolution')?.addEventListener('click', () => {
    Shared.show(document.getElementById('addResolutionForm'), 'block');
    document.getElementById('noResolutionsGuide')?.setAttribute('hidden', '');
    var titleInput = document.getElementById('resolutionTitle');
    if (titleInput) titleInput.focus();
  });
  document.getElementById('btnCancelResolution')?.addEventListener('click', () => {
    Shared.hide(document.getElementById('addResolutionForm'));
  });
  document.getElementById('btnConfirmResolution')?.addEventListener('click', createResolution);

  // Vote tab
  document.getElementById('btnCloseVote')?.addEventListener('click', () => {
    if (currentOpenMotion) closeVote(currentOpenMotion.id);
  });

  // Unanimity buttons
  document.getElementById('btnUnanimityFor')?.addEventListener('click', () => applyUnanimity('for'));
  document.getElementById('btnUnanimityAgainst')?.addEventListener('click', () => applyUnanimity('against'));
  document.getElementById('btnUnanimityAbstain')?.addEventListener('click', () => applyUnanimity('abstain'));

  // Generate vote tokens
  document.getElementById('btnGenerateTokens')?.addEventListener('click', function () {
    if (!currentMeetingId) { setNotif('error', 'Aucune s\u00e9ance s\u00e9lectionn\u00e9e'); return; }
    // Build motion select from cached motions
    var openMotions = motionsCache.filter(function (m) { return !m.closed_at; });
    if (!openMotions.length) { setNotif('error', 'Aucune r\u00e9solution ouverte pour g\u00e9n\u00e9rer des jetons'); return; }

    var motionOpts = openMotions.map(function (m) {
      return '<option value="' + escapeHtml(m.id) + '">' + escapeHtml(m.title || 'R\u00e9solution') + '</option>';
    }).join('');

    Shared.openModal({
      title: 'G\u00e9n\u00e9rer les jetons de vote',
      body:
        '<div class="form-group mb-3">' +
          '<label class="form-label">R\u00e9solution</label>' +
          '<select class="form-input" id="tokenMotionId">' + motionOpts + '</select>' +
        '</div>' +
        '<div class="form-group mb-3">' +
          '<label class="form-label">Dur\u00e9e de validit\u00e9 (minutes)</label>' +
          '<input class="form-input" type="number" id="tokenTtl" value="180" min="1" max="1440">' +
        '</div>' +
        '<div id="tokenResult" hidden></div>',
      confirmText: 'G\u00e9n\u00e9rer',
      onConfirm: async function (modal) {
        var motionId = modal.querySelector('#tokenMotionId').value;
        var ttl = parseInt(modal.querySelector('#tokenTtl').value, 10) || 180;
        var resultDiv = modal.querySelector('#tokenResult');
        var confirmBtn = modal.querySelector('.modal-confirm-btn');

        Shared.btnLoading(confirmBtn, true);
        try {
          var res = await api('/api/v1/vote_tokens_generate.php', {
            meeting_id: currentMeetingId,
            motion_id: motionId,
            ttl_minutes: ttl
          });
          var d = res.body;
          if (d && d.ok && d.data) {
            var tokens = d.data.tokens || [];
            resultDiv.hidden = false;
            resultDiv.innerHTML =
              '<div class="alert alert-success mb-2">' +
                '<strong>' + tokens.length + ' jeton' + (tokens.length > 1 ? 's' : '') + ' g\u00e9n\u00e9r\u00e9' + (tokens.length > 1 ? 's' : '') + '</strong>' +
                ' (expire dans ' + ttl + ' min)' +
              '</div>' +
              '<div style="max-height:200px;overflow-y:auto;font-size:0.85rem;">' +
                tokens.map(function (t) {
                  return '<div class="flex items-center justify-between py-1 border-b" style="border-color:var(--color-border-subtle)">' +
                    '<span>' + escapeHtml(t.member_name || '') + '</span>' +
                    '<code class="text-xs" style="user-select:all">' + escapeHtml(t.token.slice(0, 12)) + '\u2026</code>' +
                  '</div>';
                }).join('') +
              '</div>';
            setNotif('success', tokens.length + ' jetons g\u00e9n\u00e9r\u00e9s');
          } else {
            setNotif('error', (d && d.error) || 'Erreur de g\u00e9n\u00e9ration');
          }
        } catch (e) {
          setNotif('error', 'Erreur r\u00e9seau');
        } finally {
          Shared.btnLoading(confirmBtn, false);
        }
        return false; // Keep modal open to show results
      }
    });
  });

  // Settings: live validation on title
  var _settingTitle = document.getElementById('settingTitle');
  if (_settingTitle) {
    Shared.liveValidate(_settingTitle, [
      { test: function (v) { return v.length > 0; }, msg: 'Le titre est obligatoire' }
    ]);
  }

  // Settings save
  document.getElementById('btnSaveSettings')?.addEventListener('click', saveGeneralSettings);

  // President change handler
  document.getElementById('settingPresident')?.addEventListener('change', savePresident);

  // Policy change handlers — update warning inline
  document.getElementById('settingQuorumPolicy')?.addEventListener('change', updatePoliciesWarning);
  document.getElementById('settingVotePolicy')?.addEventListener('change', updatePoliciesWarning);

  // Add assessor button
  document.getElementById('btnAddAssessor')?.addEventListener('click', addAssessor);

  // =========================================================================
  // INVITATIONS
  // =========================================================================

  async function loadInvitationStats() {
    if (!currentMeetingId) return;
    try {
      const { body } = await api(`/api/v1/invitations_stats.php?meeting_id=${currentMeetingId}`);
      if (body?.ok && body?.data) {
        const inv = body.data.items || {};
        const eng = body.data.engagement || {};
        setText('invTotal', inv.total || 0);
        setText('invSent', inv.sent || 0);
        setText('invOpened', inv.opened || 0);
        setText('invBounced', inv.bounced || 0);

        const openRateEl = document.getElementById('invOpenRate');
        const engEl = document.getElementById('invEngagement');
        if (openRateEl) openRateEl.textContent = Shared.formatPct(eng.open_rate || 0) + '%';
        if (engEl && (inv.sent || inv.opened)) Shared.show(engEl, 'block');

      }
    } catch (err) {
      // Stats endpoint may not exist yet — show placeholders
      setText('invTotal', '—');
      setText('invSent', '—');
      setText('invOpened', '—');
      setText('invBounced', '—');
      var openRateEl = document.getElementById('invOpenRate');
      if (openRateEl) openRateEl.textContent = '—';
    }
  }

  async function sendInvitations() {
    if (!currentMeetingId) {
      setNotif('error', 'Aucune séance sélectionnée');
      return;
    }

    const membersWithEmail = membersCache.filter(m => m.email).length;
    if (membersWithEmail === 0) {
      setNotif('error', 'Aucun membre n\'a d\'adresse email. Ajoutez des emails aux membres avant d\'envoyer.');
      return;
    }

    const confirmed = await new Promise(resolve => {
      const modal = createModal({
        id: 'sendInvitationsModal',
        title: 'Envoyer les invitations',
        onDismiss: () => resolve(false),
        content: `
          <h3 id="sendInvitationsModal-title" style="margin:0 0 0.75rem;font-size:1.125rem;">${icon('mail', 'icon-sm icon-text')} Envoyer les invitations ?</h3>
          <p style="margin:0 0 0.75rem;">${membersWithEmail} membre${membersWithEmail > 1 ? 's' : ''} avec email recevront une invitation de vote.</p>
          <p style="margin:0 0 0.5rem;color:var(--color-text-muted);font-size:0.875rem;">Les invitations déjà envoyées ne seront pas renvoyées (sauf si vous cochez ci-dessous).</p>
          <label class="flex items-center gap-2 mt-3 mb-4" style="font-size:0.875rem;cursor:pointer;">
            <input type="checkbox" id="invResendAll">
            Renvoyer à tous (y compris ceux déjà invités)
          </label>
          <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
            <button class="btn btn-secondary" data-action="cancel">Annuler</button>
            <button class="btn btn-primary" data-action="confirm">${icon('send', 'icon-sm icon-text')} Envoyer</button>
          </div>
        `
      });
      modal.querySelector('[data-action="cancel"]').addEventListener('click', () => { closeModal(modal); resolve(false); });
      modal.querySelector('[data-action="confirm"]').addEventListener('click', () => { closeModal(modal); resolve(true); });
    });
    if (!confirmed) return;

    const resendAll = document.getElementById('invResendAll')?.checked || false;
    const btn = document.getElementById('btnSendInvitations');
    Shared.btnLoading(btn, true);

    try {
      const { body } = await api('/api/v1/invitations_send_bulk.php', {
        meeting_id: currentMeetingId,
        only_unsent: !resendAll
      });

      if (body?.ok) {
        const sent = body.data?.sent || body.sent || 0;
        const skipped = body.data?.skipped || body.skipped || 0;
        const errors = body.data?.errors || body.errors || [];
        const noEmail = body.data?.skipped_no_email || [];

        if (errors.length > 0) {
          setNotif('warning', `${sent} invitation${sent > 1 ? 's' : ''} envoyée${sent > 1 ? 's' : ''}, ${errors.length} erreur${errors.length > 1 ? 's' : ''}`);
        } else {
          let msg = `${sent} invitation${sent > 1 ? 's' : ''} envoyée${sent > 1 ? 's' : ''}`;
          if (skipped > 0) msg += ` (${skipped} ignorée${skipped > 1 ? 's' : ''})`;
          setNotif('success', msg);
        }

        // Warn about members without email addresses
        if (noEmail.length > 0) {
          const names = noEmail.slice(0, 5).map(n => escapeHtml(n)).join(', ');
          const more = noEmail.length > 5 ? ` et ${noEmail.length - 5} autre${noEmail.length - 5 > 1 ? 's' : ''}` : '';
          setNotif('warning', `${noEmail.length} membre${noEmail.length > 1 ? 's' : ''} sans email : ${names}${more}`);
        }

        await loadInvitationStats();
        loadStatusChecklist();
      } else {
        const errMsg = body?.error || getApiError(body, 'Erreur envoi invitations');
        if (errMsg === 'smtp_not_configured' || (errMsg && errMsg.includes('smtp'))) {
          setNotif('error', 'Le serveur SMTP n\'est pas configuré. Vérifiez la configuration email dans l\'administration.');
        } else {
          setNotif('error', errMsg);
        }
      }
    } catch (err) {
      setNotif('error', err.message);
    } finally {
      Shared.btnLoading(btn, false);
    }
  }

  document.getElementById('btnSendInvitations')?.addEventListener('click', sendInvitations);

  // Invitation scheduling buttons
  const invitationsOptions = document.getElementById('invitationsOptions');
  const scheduleGroup = document.getElementById('scheduleGroup');

  document.getElementById('btnScheduleInvitations')?.addEventListener('click', () => {
    if (!invitationsOptions) return;
    const isVisible = invitationsOptions.style.display !== 'none';
    invitationsOptions.style.display = isVisible ? 'none' : '';
    if (!isVisible && scheduleGroup) scheduleGroup.style.display = '';
  });

  document.getElementById('btnCancelSend')?.addEventListener('click', () => {
    if (invitationsOptions) invitationsOptions.style.display = 'none';
  });

  document.getElementById('btnConfirmSend')?.addEventListener('click', async () => {
    if (!currentMeetingId) { setNotif('error', 'Aucune séance sélectionnée'); return; }

    const scheduledAt = document.getElementById('invScheduleAt')?.value || '';
    const templateId = document.getElementById('invTemplateSelect')?.value || '';
    const recipientsRadio = document.querySelector('input[name="invRecipients"]:checked');
    const onlyUnsent = !recipientsRadio || recipientsRadio.value === 'unsent';

    const btn = document.getElementById('btnConfirmSend');
    Shared.btnLoading(btn, true);

    try {
      const payload = { meeting_id: currentMeetingId, only_unsent: onlyUnsent };
      if (templateId) payload.template_id = templateId;
      if (scheduledAt) payload.scheduled_at = scheduledAt;

      const { body } = await api('/api/v1/invitations_schedule.php', payload);
      if (body?.ok) {
        const count = body.data?.scheduled || 0;
        const label = scheduledAt ? 'programmée' + (count > 1 ? 's' : '') : 'envoyée' + (count > 1 ? 's' : '');
        setNotif('success', count + ' invitation' + (count > 1 ? 's' : '') + ' ' + label);
        if (invitationsOptions) invitationsOptions.style.display = 'none';
        await loadInvitationStats();
        loadStatusChecklist();
      } else {
        setNotif('error', getApiError(body, 'Erreur programmation invitations'));
      }
    } catch (err) {
      setNotif('error', err.message);
    } finally {
      Shared.btnLoading(btn, false);
    }
  });

  // Quick member add
  document.getElementById('btnAddMember')?.addEventListener('click', addMemberQuick);

  // Launch session button
  document.getElementById('btnLaunchSession')?.addEventListener('click', launchSession);

  // Device management button
  document.getElementById('btnManageDevices')?.addEventListener('click', showDeviceManagementModal);

  // Tab switch buttons
  document.querySelectorAll('[data-tab-switch]').forEach(btn => {
    btn.addEventListener('click', () => switchTab(btn.dataset.tabSwitch));
  });

  // Onboarding banner dismiss
  document.getElementById('onboardingDismiss')?.addEventListener('click', function() {
    var banner = document.getElementById('onboardingBanner');
    if (banner) banner.hidden = true;
  });

  // Close session buttons (Résultats tab + Exec view)
  document.getElementById('btnCloseSession')?.addEventListener('click', closeSession);
  document.getElementById('execBtnCloseSession')?.addEventListener('click', closeSession);
  document.getElementById('execBtnSwitchResults')?.addEventListener('click', () => {
    setMode('setup', { tab: 'resultats' });
  });

  // Quick nav buttons in exec mode — switch to setup with specific tab
  document.querySelectorAll('[data-exec-goto]').forEach(btn => {
    btn.addEventListener('click', () => {
      setMode('setup', { tab: btn.dataset.execGoto });
    });
  });

  // Speech queue buttons
  document.getElementById('btnNextSpeaker')?.addEventListener('click', nextSpeaker);
  document.getElementById('btnNextSpeakerActive')?.addEventListener('click', nextSpeaker);
  document.getElementById('btnEndSpeech')?.addEventListener('click', endCurrentSpeech);
  document.getElementById('btnAddToQueue')?.addEventListener('click', showAddToQueueModal);
  document.getElementById('btnClearSpeechHistory')?.addEventListener('click', clearSpeechHistory);

  // Mode switch buttons
  btnModeSetup?.addEventListener('click', () => setMode('setup'));
  btnModeExec?.addEventListener('click', () => setMode('exec'));

  // Meeting bar refresh
  document.getElementById('btnBarRefresh')?.addEventListener('click', () => {
    if (currentMeetingId) loadAllData();
  });

  // Exec view: close vote button
  document.getElementById('execBtnCloseVote')?.addEventListener('click', async function () {
    var btn = this;
    if (!currentOpenMotion) return;
    Shared.btnLoading(btn, true);
    try {
      await closeVote(currentOpenMotion.id);
    } finally {
      Shared.btnLoading(btn, false);
    }
  });

  // Exec view: speech action buttons
  document.getElementById('execBtnEndSpeech')?.addEventListener('click', async function () {
    Shared.btnLoading(this, true);
    try { await endCurrentSpeech(); } finally { Shared.btnLoading(this, false); }
  });
  document.getElementById('execBtnNextSpeaker')?.addEventListener('click', async function () {
    Shared.btnLoading(this, true);
    try { await nextSpeaker(); } finally { Shared.btnLoading(this, false); }
  });

  // Setup view: manual vote search (P3-5)
  document.getElementById('manualVoteSearch')?.addEventListener('input', renderManualVoteList);

  // Exec view: manual vote search
  document.getElementById('execManualSearch')?.addEventListener('input', refreshExecManualVotes);

  // =========================================================================
  // SETUP MODE INIT
  // =========================================================================

  const advancedMode = document.getElementById('advancedMode');

  function setPrepMode() {
    if (advancedMode) advancedMode.hidden = false;
    if (currentMode === 'setup') {
      Shared.show(tabsNav, 'flex');
    }
  }

  // Expose switchTab globally
  window.switchTab = switchTab;

  // =========================================================================
  // OPS BRIDGE — shared state & function registry for sub-modules
  // =========================================================================

  const _ops = window.OpS;

  // State proxy: each property delegates to the local IIFE variable.
  // Sub-modules read/write via OpS.xxx; main file uses bare variables as before.
  function _defState(name, getter, setter) {
    Object.defineProperty(_ops, name, {
      get: getter, set: setter, enumerable: true, configurable: true
    });
  }

  _defState('currentMeetingId',     () => currentMeetingId,     v => { currentMeetingId = v; });
  _defState('currentMeetingStatus', () => currentMeetingStatus, v => { currentMeetingStatus = v; });
  _defState('currentMeeting',       () => currentMeeting,       v => { currentMeeting = v; });
  _defState('currentMode',          () => currentMode,          v => { currentMode = v; });
  _defState('attendanceCache',      () => attendanceCache,      v => { attendanceCache = v; });
  _defState('motionsCache',         () => motionsCache,         v => { motionsCache = v; });
  _defState('currentOpenMotion',    () => currentOpenMotion,    v => { currentOpenMotion = v; });
  _defState('previousOpenMotionId', () => previousOpenMotionId, v => { previousOpenMotionId = v; });
  _defState('ballotsCache',         () => ballotsCache,         v => { ballotsCache = v; });
  _defState('ballotSourceCache',    () => ballotSourceCache,    v => { ballotSourceCache = v; });
  _defState('usersCache',           () => usersCache,           v => { usersCache = v; });
  _defState('policiesCache',        () => policiesCache,        v => { policiesCache = v; });
  _defState('proxiesCache',         () => proxiesCache,         v => { proxiesCache = v; });
  _defState('membersCache',         () => membersCache,         v => { membersCache = v; });
  _defState('speechQueueCache',     () => speechQueueCache,     v => { speechQueueCache = v; });
  _defState('currentSpeakerCache',  () => currentSpeakerCache,  v => { currentSpeakerCache = v; });
  _defState('speechTimerInterval',  () => speechTimerInterval,  v => { speechTimerInterval = v; });
  _defState('previousQueueIds',     () => previousQueueIds,     v => { previousQueueIds = v; });
  _defState('execSpeechTimerInterval', () => execSpeechTimerInterval, v => { execSpeechTimerInterval = v; });
  _defState('lastSetupTab',         () => lastSetupTab,         v => { lastSetupTab = v; });

  // Core utilities — sub-modules use these directly
  _ops.createModal   = createModal;
  _ops.closeModal    = closeModal;
  _ops.confirmModal  = confirmModal;
  _ops.setText       = setText;
  _ops.announce      = announce;
  _ops.TRANSITIONS   = TRANSITIONS;

  // Function registry — sub-modules call main-file functions via OpS.fn.xxx()
  // When a sub-module replaces a function, it overwrites the entry here.
  _ops.fn.loadStatusChecklist      = loadStatusChecklist;
  _ops.fn.checkLaunchReady         = checkLaunchReady;
  _ops.fn.updateQuickStats         = updateQuickStats;
  _ops.fn.loadMembers              = loadMembers;
  _ops.fn.loadAttendance           = loadAttendance;
  _ops.fn.renderAttendance         = renderAttendance;
  _ops.fn.loadProxies              = loadProxies;
  _ops.fn.renderProxies            = renderProxies;
  _ops.fn.loadResolutions          = loadResolutions;
  _ops.fn.renderResolutions        = renderResolutions;
  _ops.fn.loadSpeechQueue          = loadSpeechQueue;
  _ops.fn.renderCurrentSpeaker     = renderCurrentSpeaker;
  _ops.fn.renderSpeechQueue        = renderSpeechQueue;
  _ops.fn.grantSpeech              = grantSpeech;
  _ops.fn.nextSpeaker              = nextSpeaker;
  _ops.fn.endCurrentSpeech         = endCurrentSpeech;
  _ops.fn.cancelSpeechRequest      = cancelSpeechRequest;
  _ops.fn.clearSpeechHistory       = clearSpeechHistory;
  _ops.fn.showAddToQueueModal      = showAddToQueueModal;
  _ops.fn.loadBallots              = loadBallots;
  _ops.fn.loadVoteTab              = loadVoteTab;
  _ops.fn.renderManualVoteList     = renderManualVoteList;
  _ops.fn.refreshExecView          = refreshExecView;
  _ops.fn.refreshExecManualVotes   = refreshExecManualVotes;
  _ops.fn.setMode                  = setMode;
  _ops.fn.switchTab                = switchTab;
  _ops.fn.loadMeetingContext       = loadMeetingContext;
  _ops.fn.loadMeetings             = loadMeetings;
  _ops.fn.renderConformityChecklist = renderConformityChecklist;
  _ops.fn.refreshAlerts            = refreshAlerts;
  _ops.fn.updateAttendance         = updateAttendance;
  _ops.fn.markAllPresent           = markAllPresent;
  _ops.fn.showImportCSVModal       = showImportCSVModal;
  _ops.fn.showAddProxyModal        = showAddProxyModal;
  _ops.fn.showImportProxiesCSVModal = showImportProxiesCSVModal;
  _ops.fn.revokeProxy              = revokeProxy;
  _ops.fn.createResolution         = createResolution;
  _ops.fn.openVote                 = openVote;
  _ops.fn.closeVote                = closeVote;
  _ops.fn.castManualVote           = castManualVote;
  _ops.fn.applyUnanimity           = applyUnanimity;
  _ops.fn.loadResults              = loadResults;
  _ops.fn.closeSession             = closeSession;
  _ops.fn.doTransition             = doTransition;
  _ops.fn.initializePreviousMotionState = initializePreviousMotionState;

  initTabs();
  startClock();
  loadMeetings();

  // Auto-refresh - adaptive polling with cancellable timer
  const POLL_FAST = 5000;  // 5s when vote is active
  const POLL_SLOW = 15000; // 15s otherwise
  let pollTimer = null;
  let pollRunning = false;
  let newVoteDebounceTimer = null;

  function schedulePoll(ms) {
    if (pollTimer) clearTimeout(pollTimer);
    pollTimer = setTimeout(autoPoll, ms);
  }

  async function autoPoll() {
    pollTimer = null;

    if (!currentMeetingId || document.hidden) {
      schedulePoll(POLL_SLOW);
      return;
    }

    // Prevent overlapping poll cycles
    if (pollRunning) return;
    pollRunning = true;

    try {
      const activeTab = document.querySelector('.tab-btn.active')?.dataset?.tab;
      const onVoteTab = activeTab === 'vote';

      // Always refresh resolutions to detect motion state changes
      loadSpeechQueue();
      await loadResolutions();

      // In setup mode, also refresh dashboard/checklist/devices (not needed in exec)
      if (currentMode === 'setup') {
        loadStatusChecklist();
        loadDashboard();
        loadDevices();
      } else {
        loadDevices();
      }

      const isVoteActive = !!currentOpenMotion;
      const currentMotionId = currentOpenMotion?.id || null;

      // Detect if a new vote was opened (not by us) — debounced
      if (isVoteActive && currentMotionId !== previousOpenMotionId) {
        if (newVoteDebounceTimer) clearTimeout(newVoteDebounceTimer);
        const motionTitle = currentOpenMotion.title;
        newVoteDebounceTimer = setTimeout(() => {
          setNotif('info', `Vote ouvert: ${motionTitle}`);
          announce(`Vote ouvert : ${motionTitle}`);
          if (currentMode === 'exec') {
            loadBallots(currentOpenMotion.id).then(() => refreshExecView());
          } else if (currentMeetingStatus === 'live') {
            // Auto-switch to exec for real-time vote monitoring
            loadBallots(currentOpenMotion.id).then(() => setMode('exec'));
          } else {
            switchTab('vote');
          }
        }, 500);
      }

      previousOpenMotionId = currentMotionId;

      // If vote is active, refresh ballot counts
      if (isVoteActive && currentOpenMotion) {
        await loadBallots(currentOpenMotion.id);
        if (currentMode === 'setup' && onVoteTab) {
          const noVote = document.getElementById('noActiveVote');
          const panel = document.getElementById('activeVotePanel');
          const title = document.getElementById('activeVoteTitle');
          if (noVote) Shared.hide(noVote);
          if (panel) Shared.show(panel, 'block');
          if (title) title.textContent = currentOpenMotion.title;
          renderManualVoteList();
        }
      } else if (onVoteTab) {
        loadVoteTab();
      }

      // Refresh quorum when on vote tab
      if (onVoteTab) loadQuorumStatus();

      // Refresh bimodal UI
      renderConformityChecklist();
      refreshAlerts();
      if (currentMode === 'exec') refreshExecView();

      // Schedule next poll
      const interval = isVoteActive ? POLL_FAST : POLL_SLOW;
      schedulePoll(interval);
    } catch (err) {
      console.warn('autoPoll error:', err);
      schedulePoll(POLL_SLOW);
    } finally {
      pollRunning = false;
    }
  }

  // Refresh immediately when tab becomes visible — using schedulePoll to avoid stacking
  document.addEventListener('visibilitychange', function() {
    if (!document.hidden && currentMeetingId) {
      schedulePoll(100); // near-immediate but cancels any existing timer
    }
  });

  // Cleanup on page unload
  window.addEventListener('beforeunload', function() {
    if (pollTimer) clearTimeout(pollTimer);
    if (newVoteDebounceTimer) clearTimeout(newVoteDebounceTimer);
    if (speechTimerInterval) clearInterval(speechTimerInterval);
    if (execSpeechTimerInterval) clearInterval(execSpeechTimerInterval);
    if (sessionTimerInterval) clearInterval(sessionTimerInterval);
    if (_clockInterval) clearInterval(_clockInterval);
  });

  // Start polling after initial load
  schedulePoll(POLL_SLOW);

})();
