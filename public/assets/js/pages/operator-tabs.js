/**
 * operator-tabs.js — Tab-based operator console for AG-VOTE (Diligent-style)
 * Requires: utils.js, shared.js, shell.js
 */
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
  let invitationsSentCount = 0;  // Track sent invitations for wizard checklist
  let currentMode = 'setup'; // 'setup' | 'exec'

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
    live: [{ to: 'closed', label: 'Clôturer', iconName: 'square' }],
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
  function createModal({ id, title, content, maxWidth = '500px', closeOnBackdrop = true }) {
    const modalId = id || 'modal-' + Date.now();
    const titleId = modalId + '-title';

    const modal = document.createElement('div');
    modal.id = modalId;
    modal.className = 'modal-backdrop';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', titleId);
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:var(--z-modal-backdrop, 400);display:flex;align-items:center;justify-content:center;opacity:1;visibility:visible;';

    modal.innerHTML = `
      <div class="modal-content" style="background:var(--color-surface);border-radius:12px;padding:1.5rem;max-width:${maxWidth};width:90%;max-height:90vh;overflow:auto;" role="document">
        ${content}
      </div>
    `;

    document.body.appendChild(modal);

    // Centralized destroy function — removes element AND cleans up listener
    const handleEscape = (e) => {
      if (e.key === 'Escape' && document.body.contains(modal)) {
        destroyModal();
      }
    };

    function destroyModal() {
      document.removeEventListener('keydown', handleEscape);
      if (document.body.contains(modal)) modal.remove();
    }
    modal._destroy = destroyModal;

    // Close on backdrop click
    if (closeOnBackdrop) {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) destroyModal();
      });
    }

    // Close on Escape key
    document.addEventListener('keydown', handleEscape);

    // Focus trap - keep focus inside modal
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

  async function switchTab(tabId) {
    tabButtons.forEach(btn => {
      btn.classList.toggle('active', btn.dataset.tab === tabId);
    });
    tabContents.forEach(content => {
      content.classList.toggle('active', content.id === `tab-${tabId}`);
    });

    // Reload data when switching to certain tabs
    if (currentMeetingId) {
      if (tabId === 'presences') {
        await loadAttendance();
      }
      if (tabId === 'procurations') {
        await loadProxies();
        await loadAttendance(); // Need attendance for proxy modal
      }
      if (tabId === 'resolutions') await loadResolutions();
      if (tabId === 'parole') await loadSpeechQueue();
      if (tabId === 'vote') await loadVoteTab();
      if (tabId === 'resultats') await loadResults();
    }
  }

  // =========================================================================
  // MEETING SELECTION
  // =========================================================================

  async function loadMeetings() {
    try {
      const { body } = await api('/api/v1/meetings_index.php?active_only=1');
      if (body?.ok && body?.data?.meetings) {
        meetingSelect.innerHTML = '<option value="">— Sélectionner une séance —</option>';
        body.data.meetings.forEach(m => {
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
    updateURLParam('meeting_id', meetingId);

    try {
      const { body } = await api(`/api/v1/meetings.php?id=${meetingId}`);
      if (body?.ok && body?.data) {
        currentMeeting = body.data;
        currentMeetingStatus = body.data.status;
        showMeetingContent();
        updateHeader(body.data);
        await loadAllData();
      }
    } catch (err) {
      setNotif('error', 'Erreur: ' + err.message);
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

    // Auto-select mode based on meeting status
    const initialMode = (currentMeetingStatus === 'live') ? 'exec' : 'setup';

    // For live/closed/validated/archived meetings, force advanced mode
    if (['live', 'closed', 'validated', 'archived'].includes(currentMeetingStatus)) {
      setPrepMode('advanced');
    } else {
      setPrepMode('assistant');
    }

    setMode(initialMode);

    // If in setup mode + advanced, show the right tab
    if (initialMode === 'setup' && prepMode === 'advanced') {
      const urlParams = new URLSearchParams(window.location.search);
      const requestedTab = urlParams.get('tab');
      const validTabs = ['parametres', 'resolutions', 'presences', 'procurations', 'parole', 'vote', 'resultats'];
      const tabToShow = (requestedTab && validTabs.includes(requestedTab)) ? requestedTab : 'parametres';
      switchTab(tabToShow);
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
  }

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
      loadDashboard(),
      loadDevices(),
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

    // If a vote is already open and in setup, switch to vote tab
    if (currentOpenMotion && currentMeetingStatus === 'live' && currentMode === 'setup') {
      switchTab('vote');
    }

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
      membersCache = body?.data?.members || [];
      renderMembersCard();
    } catch (err) {
      console.warn('loadMembers:', err.message);
    }
  }

  function renderMembersCard() {
    setText('membersCount', membersCache.length);

    const list = document.getElementById('membersList');
    if (membersCache.length === 0) {
      list.innerHTML = '<span class="text-muted text-sm">Aucun membre</span>';
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

    btnCancel.onclick = () => closeModal(modal);
    btnConfirm.onclick = async () => {
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
    };
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
      } catch (e) { dateText = currentMeeting.scheduled_at; }
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

    // Type de consultation (stored in description or metadata)
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
      console.warn('loadPolicies:', err.message);
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
      console.warn('loadRoles:', err.message);
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

  // Delegated handler for assessor removal (replaces inline onclick)
  document.addEventListener('click', async function(e) {
    const btn = e.target.closest('[data-remove-assessor]');
    if (!btn) return;
    const userId = btn.getAttribute('data-remove-assessor');
    if (!userId) return;
    const confirmed = await new Promise(resolve => {
      const m = createModal({
        id: 'removeAssessorModal',
        title: 'Retirer l\'assesseur',
        content: `<p>Retirer cet assesseur de la séance ?</p>
          <div class="flex gap-3 justify-end mt-4">
            <button class="btn btn-secondary" data-action="cancel">Annuler</button>
            <button class="btn btn-danger" data-action="confirm">Retirer</button>
          </div>`
      });
      m.querySelector('[data-action="cancel"]').addEventListener('click', () => { closeModal(m); resolve(false); });
      m.querySelector('[data-action="confirm"]').addEventListener('click', () => { closeModal(m); resolve(true); });
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
        document.getElementById('dashVoteFor').textContent = votes.weight_for ?? 0;
        document.getElementById('dashVoteAgainst').textContent = votes.weight_against ?? 0;
        document.getElementById('dashVoteAbstain').textContent = votes.weight_abstain ?? 0;
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
      console.warn('loadDashboard:', err.message);
    }
  }

  async function loadDevices() {
    if (!currentMeetingId) return;

    try {
      const resp = await fetch(`/api/v1/devices_list.php?meeting_id=${currentMeetingId}`, { credentials: 'same-origin' });
      const data = await resp.json();

      if (!data.ok) return;

      // Show card
      const card = document.getElementById('devicesCard');
      if (card) Shared.show(card, 'block');

      const counts = data.counts || {};
      setText('devOnline', counts.online ?? 0);
      setText('devStale', counts.stale ?? 0);
      setText('devOffline', counts.offline ?? 0);
      setText('devBlocked', counts.blocked ?? 0);

      // Device list (show first 5)
      const items = data.items || [];
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
      console.warn('loadDevices:', err.message);
    }
  }

  function showDeviceManagementModal() {
    const modal = document.createElement('div');
    modal.className = 'modal-backdrop';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:var(--z-modal-backdrop, 400);display:flex;align-items:center;justify-content:center;opacity:1;visibility:visible;';

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
    document.getElementById('btnCloseDevices').onclick = () => modal.remove();
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });

    loadDevicesModal(modal);
  }

  async function loadDevicesModal(modal) {
    const list = modal.querySelector('#devicesModalList');

    try {
      const resp = await fetch(`/api/v1/devices_list.php?meeting_id=${currentMeetingId}`, { credentials: 'same-origin' });
      const data = await resp.json();

      if (!data.ok || !data.items?.length) {
        list.innerHTML = '<div class="text-center p-4 text-muted">Aucun appareil connecté</div>';
        return;
      }

      list.innerHTML = data.items.map(dev => {
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
    } catch (err) {
      console.warn('loadStatusChecklist:', err.message);
    }
  }

  // =========================================================================
  // SAVE SETTINGS
  // =========================================================================

  async function saveGeneralSettings() {
    const title = document.getElementById('settingTitle').value.trim();
    const scheduledAt = document.getElementById('settingDate').value;
    const meetingType = document.querySelector('input[name="meetingType"]:checked')?.value || 'ag_ordinaire';

    if (!title) {
      setNotif('error', 'Le titre est obligatoire');
      return;
    }

    const btn = document.getElementById('btnSaveSettings');
    Shared.btnLoading(btn, true);

    try {
      // Save title and date
      await api('/api/v1/meetings_update.php', {
        meeting_id: currentMeetingId,
        title: title,
        scheduled_at: scheduledAt || null
      });

      // Save policies
      const quorumPolicyId = document.getElementById('settingQuorumPolicy').value || null;
      const votePolicyId = document.getElementById('settingVotePolicy').value || null;
      const convocationNo = parseInt(document.getElementById('settingConvocation').value) || 1;

      await api('/api/v1/meeting_quorum_settings.php', {
        meeting_id: currentMeetingId,
        quorum_policy_id: quorumPolicyId,
        convocation_no: convocationNo
      });

      await api('/api/v1/meeting_vote_settings.php', {
        meeting_id: currentMeetingId,
        vote_policy_id: votePolicyId
      });

      // Update local state and header
      currentMeeting.title = title;
      currentMeeting.scheduled_at = scheduledAt;
      currentMeeting.quorum_policy_id = quorumPolicyId;
      currentMeeting.vote_policy_id = votePolicyId;
      currentMeeting.convocation_no = convocationNo;

      updateHeader(currentMeeting);
      loadStatusChecklist();

      setNotif('success', 'Paramètres enregistrés');
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
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:var(--z-modal-backdrop, 400);display:flex;align-items:center;justify-content:center;opacity:1;visibility:visible;';

    const availableUsers = usersCache.filter(u => {
      // Exclude current president
      const presId = document.getElementById('settingPresident').value;
      return u.id !== presId;
    });

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

    document.getElementById('btnCancelAssessor').onclick = () => modal.remove();
    document.getElementById('btnConfirmAssessor').onclick = async () => {
      const userId = document.getElementById('assessorSelect').value;
      if (!userId) {
        setNotif('error', 'Sélectionnez un utilisateur');
        return;
      }

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
      }
    };
  }

  // =========================================================================
  // TAB: PRÉSENCES - Attendance
  // =========================================================================

  async function loadAttendance() {
    try {
      const { body } = await api(`/api/v1/attendances.php?meeting_id=${currentMeetingId}`);
      attendanceCache = body?.data?.attendances || [];
      renderAttendance();
    } catch (err) {
      console.warn('loadAttendance:', err.message);
    }
  }

  function renderAttendance() {
    const present = attendanceCache.filter(a => a.mode === 'present').length;
    const remote = attendanceCache.filter(a => a.mode === 'remote').length;
    const proxyCount = proxiesCache.filter(p => !p.revoked_at).length;
    const absent = attendanceCache.filter(a => !a.mode || a.mode === 'absent').length;

    setText('presStatPresent', present);
    setText('presStatRemote', remote);
    setText('presStatProxy', proxyCount);
    setText('presStatAbsent', absent);
    setText('tabCountPresences', present + remote + proxyCount);

    const searchTerm = (document.getElementById('presenceSearch')?.value || '').toLowerCase();
    let filtered = attendanceCache;
    if (searchTerm) {
      filtered = attendanceCache.filter(a => (a.full_name || '').toLowerCase().includes(searchTerm));
    }

    // Build a map of proxies for quick lookup
    const proxyByGiver = {};
    proxiesCache.filter(p => !p.revoked_at).forEach(p => {
      proxyByGiver[p.giver_member_id] = p;
    });

    // Sort: present first, remote second, proxy third, absent last
    filtered = [...filtered].sort((a, b) => {
      const hasProxyA = !!proxyByGiver[a.member_id];
      const hasProxyB = !!proxyByGiver[b.member_id];
      const orderA = a.mode === 'present' ? 0 : a.mode === 'remote' ? 1 : hasProxyA ? 2 : 3;
      const orderB = b.mode === 'present' ? 0 : b.mode === 'remote' ? 1 : hasProxyB ? 2 : 3;
      if (orderA !== orderB) return orderA - orderB;
      return (a.full_name || '').localeCompare(b.full_name || '');
    });

    const grid = document.getElementById('attendanceGrid');
    const isLocked = ['validated', 'archived'].includes(currentMeetingStatus);

    grid.innerHTML = filtered.map(m => {
      const mode = m.mode || 'absent';
      const disabled = isLocked ? 'disabled' : '';
      const proxy = proxyByGiver[m.member_id];
      const hasProxy = !!proxy;
      const cardClass = hasProxy ? 'proxy' : mode;
      const proxyTitle = hasProxy ? `Procuration: ${proxy.receiver_name || 'mandataire'}` : 'Ajouter procuration';

      return `
        <div class="attendance-card ${cardClass}" data-member-id="${m.member_id}">
          <span class="attendance-name">${escapeHtml(m.full_name || '—')}</span>
          ${hasProxy ? `<span class="proxy-indicator" title="${escapeHtml(proxyTitle)}">${icon('user-check', 'icon-xs')}</span>` : ''}
          <div class="attendance-mode-btns">
            <button class="mode-btn present ${mode === 'present' ? 'active' : ''}" data-mode="present" ${disabled} title="Présent">P</button>
            <button class="mode-btn remote ${mode === 'remote' ? 'active' : ''}" data-mode="remote" ${disabled} title="Distant">D</button>
            <button class="mode-btn absent ${mode === 'absent' ? 'active' : ''}" data-mode="absent" ${disabled} title="Absent">A</button>
          </div>
        </div>
      `;
    }).join('') || '<div class="text-center p-4 text-muted">Aucun membre</div>';

    if (!isLocked) {
      grid.querySelectorAll('.mode-btn:not([disabled])').forEach(btn => {
        btn.addEventListener('click', async (e) => {
          const card = e.target.closest('.attendance-card');
          const memberId = card.dataset.memberId;
          const mode = btn.dataset.mode;
          await updateAttendance(memberId, mode);
        });
      });
    }
  }

  async function updateAttendance(memberId, mode) {
    const m = attendanceCache.find(a => String(a.member_id) === String(memberId));
    const prevMode = m ? m.mode : undefined;

    // Optimistic update for instant feedback
    if (m) m.mode = mode;
    renderAttendance();
    updateQuickStats();

    try {
      const { body } = await api('/api/v1/attendances_upsert.php', {
        meeting_id: currentMeetingId,
        member_id: memberId,
        mode: mode
      });
      if (body?.ok === true) {
        loadStatusChecklist();
        checkLaunchReady();
      } else {
        // Rollback on API error
        if (m) m.mode = prevMode;
        renderAttendance();
        updateQuickStats();
        setNotif('error', getApiError(body, 'Erreur de mise à jour'));
      }
    } catch (err) {
      // Rollback on network error
      if (m) m.mode = prevMode;
      renderAttendance();
      updateQuickStats();
      setNotif('error', err.message);
    }
  }

  async function markAllPresent() {
    const confirmed = await new Promise(resolve => {
      const m = createModal({
        id: 'markAllPresentModal',
        title: 'Marquer tous présents',
        content: `<p>Marquer tous les membres comme <strong>présents</strong> ?</p>
          <div class="flex gap-3 justify-end mt-4">
            <button class="btn btn-secondary" data-action="cancel">Annuler</button>
            <button class="btn btn-primary" data-action="confirm">Confirmer</button>
          </div>`
      });
      m.querySelector('[data-action="cancel"]').addEventListener('click', () => { closeModal(m); resolve(false); });
      m.querySelector('[data-action="confirm"]').addEventListener('click', () => { closeModal(m); resolve(true); });
    });
    if (!confirmed) return;
    try {
      await api('/api/v1/attendances_bulk.php', { meeting_id: currentMeetingId, mode: 'present' });
      attendanceCache.forEach(m => m.mode = 'present');
      renderAttendance();
      setNotif('success', 'Tous marqués présents');
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  function showImportCSVModal() {
    const modal = document.createElement('div');
    modal.className = 'modal-backdrop';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:var(--z-modal-backdrop, 400);display:flex;align-items:center;justify-content:center;opacity:1;visibility:visible;';

    modal.innerHTML = `
      <div style="background:var(--color-surface);border-radius:12px;padding:1.5rem;max-width:600px;width:90%;max-height:90vh;overflow:auto;">
        <h3 style="margin:0 0 1rem;"><svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-download"></use></svg> Importer des membres (CSV)</h3>
        <p class="text-muted text-sm mb-3">
          Format attendu: <code>name,email,voting_power</code> (en-tête requis).<br>
          L'email et le nombre de voix sont optionnels.
        </p>
        <div class="form-group mb-3">
          <label class="form-label">Fichier CSV</label>
          <input type="file" class="form-input" id="csvFileInput" accept=".csv,.txt">
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Ou coller le contenu</label>
          <textarea class="form-input" id="csvTextInput" rows="4" placeholder="name,email,voting_power\nJean Dupont,jean@exemple.com,1\nMarie Martin,,2"></textarea>
        </div>
        <div id="csvPreviewContainer" style="display:none;" class="mb-4"></div>
        <div class="flex gap-2 justify-end">
          <button class="btn btn-secondary" id="btnCancelImport">Annuler</button>
          <button class="btn btn-outline" id="btnPreviewCSV">Aperçu</button>
          <button class="btn btn-primary" id="btnConfirmImport" disabled>Importer</button>
        </div>
      </div>
    `;

    document.body.appendChild(modal);

    let parsedData = null;

    const previewContainer = document.getElementById('csvPreviewContainer');
    const btnPreview = document.getElementById('btnPreviewCSV');
    const btnConfirm = document.getElementById('btnConfirmImport');

    // Get CSV content from file or textarea
    async function getCSVContent() {
      const fileInput = document.getElementById('csvFileInput');
      const textInput = document.getElementById('csvTextInput');
      let csvContent = textInput.value.trim();

      if (fileInput.files.length > 0) {
        csvContent = await fileInput.files[0].text();
      }
      return csvContent;
    }

    // Preview button handler
    btnPreview.onclick = async () => {
      const csvContent = await getCSVContent();

      if (!csvContent) {
        setNotif('error', 'Aucun contenu à prévisualiser');
        return;
      }

      // Parse and generate preview
      parsedData = Utils.parseCSV(csvContent);
      const previewHtml = Utils.generateCSVPreview(parsedData);

      previewContainer.innerHTML = previewHtml;
      Shared.show(previewContainer, 'block');

      // Enable import button if we have valid rows
      const hasValidRows = parsedData.rows.some(r => r.name);
      btnConfirm.disabled = !hasValidRows;

      if (!hasValidRows) {
        setNotif('warning', 'Aucune donnée valide trouvée');
      }
    };

    // Auto-preview when file is selected
    document.getElementById('csvFileInput').onchange = () => {
      btnPreview.click();
    };

    document.getElementById('btnCancelImport').onclick = () => modal.remove();

    btnConfirm.onclick = async () => {
      const csvContent = await getCSVContent();

      if (!csvContent) {
        setNotif('error', 'Aucun contenu à importer');
        return;
      }

      try {
        btnConfirm.disabled = true;
        btnConfirm.textContent = 'Import en cours...';

        const formData = new FormData();
        formData.append('csv_content', csvContent);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || (window.CSRF && window.CSRF.token) || '';
        const resp = await fetch('/api/v1/members_import_csv.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
          headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : {}
        });
        const data = await resp.json();

        if (data.ok) {
          const count = data.data?.imported || 0;
          setNotif('success', `${count} membre(s) importé(s)`);
          modal.remove();
          loadMembers();
          loadAttendance();
          loadStatusChecklist();
        } else {
          setNotif('error', data.error || 'Erreur import');
          btnConfirm.disabled = false;
          btnConfirm.textContent = 'Importer';
        }
      } catch (err) {
        setNotif('error', err.message);
        btnConfirm.disabled = false;
        btnConfirm.textContent = 'Importer';
      }
    };
  }

  // =========================================================================
  // TAB: PRÉSENCES - Proxies Management
  // =========================================================================

  async function loadProxies() {
    if (!currentMeetingId) return;

    try {
      const { body } = await api(`/api/v1/proxies.php?meeting_id=${currentMeetingId}`);
      proxiesCache = body?.data?.proxies || body?.proxies || [];
      renderProxies();
    } catch (err) {
      console.warn('loadProxies:', err.message);
    }
  }

  function renderProxies() {
    const list = document.getElementById('proxyList');
    if (!list) return;

    const searchTerm = (document.getElementById('proxySearch')?.value || '').toLowerCase();
    const activeProxies = proxiesCache.filter(p => !p.revoked_at);

    // Filter by search term
    let filtered = activeProxies;
    if (searchTerm) {
      filtered = activeProxies.filter(p =>
        (p.giver_name || '').toLowerCase().includes(searchTerm) ||
        (p.receiver_name || '').toLowerCase().includes(searchTerm)
      );
    }

    // Update stats
    const uniqueReceivers = new Set(activeProxies.map(p => p.receiver_member_id));
    const proxyStatActiveEl = document.getElementById('proxyStatActive');
    const proxyStatGiversEl = document.getElementById('proxyStatGivers');
    const proxyStatReceiversEl = document.getElementById('proxyStatReceivers');
    if (proxyStatActiveEl) proxyStatActiveEl.textContent = activeProxies.length;
    if (proxyStatGiversEl) proxyStatGiversEl.textContent = activeProxies.length;
    if (proxyStatReceiversEl) proxyStatReceiversEl.textContent = uniqueReceivers.size;

    // Update tab count
    const tabCount = document.getElementById('tabCountProxies');
    if (tabCount) tabCount.textContent = activeProxies.length;

    if (filtered.length === 0) {
      list.innerHTML = searchTerm
        ? '<div class="text-center p-4 text-muted">Aucune procuration ne correspond à la recherche</div>'
        : '<div class="text-center p-4 text-muted">Aucune procuration</div>';
      return;
    }

    const isLocked = ['validated', 'archived'].includes(currentMeetingStatus);

    list.innerHTML = filtered.map(p => `
      <div class="proxy-item" data-proxy-id="${p.id}" data-giver-id="${p.giver_member_id}">
        <div class="proxy-item-info">
          <span class="proxy-giver">${escapeHtml(p.giver_name || '—')}</span>
          <span class="proxy-arrow">${icon('arrow-right', 'icon-sm')}</span>
          <span class="proxy-receiver">${escapeHtml(p.receiver_name || '—')}</span>
        </div>
        ${!isLocked ? `
          <button class="btn btn-sm btn-ghost text-danger btn-revoke-proxy" data-giver-id="${p.giver_member_id}" title="Révoquer">
            ${icon('x', 'icon-sm')}
          </button>
        ` : ''}
      </div>
    `).join('');

    // Bind revoke buttons
    if (!isLocked) {
      list.querySelectorAll('.btn-revoke-proxy').forEach(btn => {
        btn.addEventListener('click', () => revokeProxy(btn.dataset.giverId));
      });
    }
  }

  async function revokeProxy(giverId) {
    const confirmed = await new Promise(resolve => {
      const m = createModal({
        id: 'revokeProxyModal',
        title: 'Révoquer la procuration',
        content: `<p>Révoquer cette procuration ?</p>
          <div class="flex gap-3 justify-end mt-4">
            <button class="btn btn-secondary" data-action="cancel">Annuler</button>
            <button class="btn btn-danger" data-action="confirm">Révoquer</button>
          </div>`
      });
      m.querySelector('[data-action="cancel"]').addEventListener('click', () => { closeModal(m); resolve(false); });
      m.querySelector('[data-action="confirm"]').addEventListener('click', () => { closeModal(m); resolve(true); });
    });
    if (!confirmed) return;

    try {
      const { body } = await api('/api/v1/proxies_upsert.php', {
        meeting_id: currentMeetingId,
        giver_member_id: giverId,
        receiver_member_id: ''  // Empty to revoke
      });

      if (body?.ok || body?.revoked) {
        setNotif('success', 'Procuration révoquée');
        await loadProxies();
        renderAttendance();
        updateQuickStats();
      } else {
        setNotif('error', getApiError(body, 'Erreur lors de la révocation'));
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  function showAddProxyModal() {
    try {
      // Check if data is loaded
      if (!attendanceCache || attendanceCache.length === 0) {
        setNotif('warning', 'Données de présence non chargées. Veuillez sélectionner une séance.');
        return;
      }

      // Get list of members who can give proxy (not already present and not already giving)
      const giverIds = new Set(proxiesCache.filter(p => !p.revoked_at).map(p => p.giver_member_id));
      const presentIds = new Set(attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote').map(a => a.member_id));

      // Givers: members who are absent and don't already have a proxy
      const potentialGivers = attendanceCache.filter(a =>
        !presentIds.has(a.member_id) && !giverIds.has(a.member_id)
      );

      // Receivers: members who are present or remote
      const potentialReceivers = attendanceCache.filter(a =>
        a.mode === 'present' || a.mode === 'remote'
      );

      const modal = document.createElement('div');
      modal.className = 'modal-backdrop';
      modal.setAttribute('role', 'dialog');
      modal.setAttribute('aria-modal', 'true');
      modal.setAttribute('aria-labelledby', 'addProxyModalTitle');
      modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:var(--z-modal-backdrop, 400);display:flex;align-items:center;justify-content:center;opacity:1;visibility:visible;';

      modal.innerHTML = `
        <div style="background:var(--color-surface);border-radius:12px;padding:1.5rem 1.75rem;max-width:500px;width:90%;" role="document">
          <h3 id="addProxyModalTitle" style="margin:0 0 1rem;">${icon('user-check', 'icon-sm icon-text')} Nouvelle procuration</h3>
          <p class="text-muted text-sm mb-4">Le mandant (absent) donne procuration au mandataire (présent) pour voter à sa place.</p>

          ${potentialGivers.length === 0 ? `
            <div class="alert alert-warning mb-4">${icon('info', 'icon-sm icon-text')} Tous les membres absents ont déjà une procuration ou sont présents.</div>
          ` : ''}

          ${potentialReceivers.length === 0 ? `
            <div class="alert alert-warning mb-4">${icon('info', 'icon-sm icon-text')} Aucun membre présent pour recevoir une procuration.</div>
          ` : ''}

          <div class="form-group mb-3">
            <label class="form-label">Mandant (qui donne procuration)</label>
            <ag-searchable-select
              id="proxyGiverSelect"
              placeholder="Rechercher un membre absent..."
              empty-text="Aucun membre trouvé"
              ${potentialGivers.length === 0 ? 'disabled' : ''}
            ></ag-searchable-select>
          </div>

          <div class="form-group mb-3">
            <label class="form-label">Mandataire (qui vote à sa place)</label>
            <ag-searchable-select
              id="proxyReceiverSelect"
              placeholder="Rechercher un membre présent..."
              empty-text="Aucun membre trouvé"
              ${potentialReceivers.length === 0 ? 'disabled' : ''}
            ></ag-searchable-select>
          </div>

          <div class="flex gap-2 justify-end">
            <button class="btn btn-secondary" id="btnCancelProxy">Annuler</button>
            <button class="btn btn-primary" id="btnConfirmProxy" ${potentialGivers.length === 0 || potentialReceivers.length === 0 ? 'disabled' : ''}>Créer la procuration</button>
          </div>
        </div>
      `;

      document.body.appendChild(modal);
      modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });

      // Initialize searchable selects with options
      const giverSelect = document.getElementById('proxyGiverSelect');
      const receiverSelect = document.getElementById('proxyReceiverSelect');

      if (giverSelect && giverSelect.setOptions) {
        const giverOptions = potentialGivers.map(m => ({
          value: m.member_id,
          label: m.full_name || '—',
          sublabel: m.email || ''
        }));
        giverSelect.setOptions(giverOptions);
      }

      if (receiverSelect && receiverSelect.setOptions) {
        const receiverOptions = potentialReceivers.map(m => ({
          value: m.member_id,
          label: m.full_name || '—',
          sublabel: (m.email || '') + (m.mode === 'present' ? ' — Présent' : ' — Distant')
        }));
        receiverSelect.setOptions(receiverOptions);
      }

      const btnCancel = document.getElementById('btnCancelProxy');
      const btnConfirm = document.getElementById('btnConfirmProxy');

      if (btnCancel) btnCancel.onclick = () => modal.remove();
      if (btnConfirm) btnConfirm.onclick = async () => {
        const giverId = document.getElementById('proxyGiverSelect').value;
        const receiverId = document.getElementById('proxyReceiverSelect').value;

        if (!giverId || !receiverId) {
          setNotif('error', 'Veuillez sélectionner le mandant et le mandataire');
          return;
        }

        if (giverId === receiverId) {
          setNotif('error', 'Le mandant et le mandataire doivent être différents');
          return;
        }

        // Disable buttons during async operation
        btnConfirm.disabled = true;
        btnCancel.disabled = true;
        const originalText = btnConfirm.textContent;
        btnConfirm.textContent = 'Création...';

        try {
          const { body } = await api('/api/v1/proxies_upsert.php', {
            meeting_id: currentMeetingId,
            giver_member_id: giverId,
            receiver_member_id: receiverId
          });

          if (body?.ok) {
            setNotif('success', 'Procuration créée');
            modal.remove();
            await loadProxies();
            renderAttendance();
            updateQuickStats();
          } else {
            setNotif('error', getApiError(body, 'Erreur lors de la création'));
            btnConfirm.disabled = false;
            btnCancel.disabled = false;
            btnConfirm.textContent = originalText;
          }
        } catch (err) {
          setNotif('error', err.message);
          btnConfirm.disabled = false;
          btnCancel.disabled = false;
          btnConfirm.textContent = originalText;
        }
      };
    } catch (err) {
      console.error('Error in showAddProxyModal:', err);
      setNotif('error', 'Erreur lors de l\'ouverture du formulaire: ' + err.message);
    }
  }

  // Import proxies from CSV modal
  function showImportProxiesCSVModal() {
    // Validate that meeting is selected and data is loaded
    if (!currentMeetingId) {
      setNotif('warning', 'Veuillez sélectionner une séance.');
      return;
    }
    if (!attendanceCache || attendanceCache.length === 0) {
      setNotif('warning', 'Données de présence non chargées. Veuillez patienter ou recharger la page.');
      return;
    }

    const modal = document.createElement('div');
    modal.className = 'modal-backdrop';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'importProxiesModalTitle');
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:var(--z-modal-backdrop, 400);display:flex;align-items:center;justify-content:center;opacity:1;visibility:visible;';

    modal.innerHTML = `
      <div style="background:var(--color-surface);border-radius:12px;padding:1.5rem;max-width:600px;width:90%;max-height:90vh;overflow:auto;" role="document">
        <h3 id="importProxiesModalTitle" style="margin:0 0 1rem;">${icon('download', 'icon-sm icon-text')} Importer des procurations (CSV)</h3>
        <p class="text-muted text-sm mb-3">
          Format attendu: <code>giver_email,receiver_email</code> (en-tête requis).<br>
          Les emails doivent correspondre aux membres existants.
        </p>
        <div class="form-group mb-3">
          <label class="form-label">Fichier CSV</label>
          <input type="file" class="form-input" id="csvProxyFileInput" accept=".csv,.txt">
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Ou coller le contenu</label>
          <textarea class="form-input" id="csvProxyTextInput" rows="4" placeholder="giver_email,receiver_email\nabsent@exemple.com,present@exemple.com"></textarea>
        </div>
        <div id="csvProxyPreviewContainer" style="display:none;" class="mb-4"></div>
        <div class="flex gap-2 justify-end">
          <button class="btn btn-secondary" id="btnCancelProxyImport">Annuler</button>
          <button class="btn btn-outline" id="btnPreviewProxyCSV">Aperçu</button>
          <button class="btn btn-primary" id="btnConfirmProxyImport" disabled>Importer</button>
        </div>
      </div>
    `;

    document.body.appendChild(modal);

    const previewContainer = document.getElementById('csvProxyPreviewContainer');
    const btnPreview = document.getElementById('btnPreviewProxyCSV');
    const btnConfirm = document.getElementById('btnConfirmProxyImport');

    async function getCSVContent() {
      const fileInput = document.getElementById('csvProxyFileInput');
      const textInput = document.getElementById('csvProxyTextInput');
      let csvContent = textInput.value.trim();

      if (fileInput.files.length > 0) {
        csvContent = await fileInput.files[0].text();
      }
      return csvContent;
    }

    btnPreview.onclick = async () => {
      const csvContent = await getCSVContent();
      if (!csvContent) {
        setNotif('error', 'Aucun contenu à prévisualiser');
        return;
      }

      const parsed = Utils.parseCSV(csvContent);
      let html = '<div style="max-height:200px;overflow:auto;"><table class="table table-sm"><thead><tr><th>Mandant</th><th>Mandataire</th></tr></thead><tbody>';
      let validCount = 0;

      for (const row of parsed.rows) {
        const giver = row.giver_email || row[Object.keys(row)[0]] || '';
        const receiver = row.receiver_email || row[Object.keys(row)[1]] || '';
        if (giver && receiver) {
          html += '<tr><td>' + escapeHtml(giver) + '</td><td>' + escapeHtml(receiver) + '</td></tr>';
          validCount++;
        }
      }

      html += '</tbody></table></div>';
      html += '<p class="text-sm text-muted mt-2">' + validCount + ' procuration(s) trouvée(s)</p>';

      previewContainer.innerHTML = html;
      Shared.show(previewContainer, 'block');
      btnConfirm.disabled = validCount === 0;
    };

    document.getElementById('csvProxyFileInput').onchange = () => btnPreview.click();
    document.getElementById('btnCancelProxyImport').onclick = () => modal.remove();

    btnConfirm.onclick = async () => {
      const csvContent = await getCSVContent();
      if (!csvContent) {
        setNotif('error', 'Aucun contenu à importer');
        return;
      }

      try {
        btnConfirm.disabled = true;
        btnConfirm.textContent = 'Import en cours...';

        const formData = new FormData();
        formData.append('meeting_id', currentMeetingId);
        formData.append('csv_content', csvContent);

        const csrfTok = document.querySelector('meta[name="csrf-token"]')?.content || (window.CSRF && window.CSRF.token) || '';
        const resp = await fetch('/api/v1/proxies_import_csv.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
          headers: csrfTok ? { 'X-CSRF-Token': csrfTok } : {}
        });
        const data = await resp.json();

        if (data.ok) {
          const count = data.data?.imported || data.imported || 0;
          setNotif('success', count + ' procuration(s) importée(s)');
          modal.remove();
          await loadProxies();
          renderAttendance();
          updateQuickStats();
        } else {
          setNotif('error', data.error || 'Erreur import');
          btnConfirm.disabled = false;
          btnConfirm.textContent = 'Importer';
        }
      } catch (err) {
        setNotif('error', err.message);
        btnConfirm.disabled = false;
        btnConfirm.textContent = 'Importer';
      }
    };
  }

  // =========================================================================
  // TAB: PAROLE - Speech Queue
  // =========================================================================

  let speechQueueCache = [];
  let currentSpeakerCache = null;
  let speechTimerInterval = null;
  let previousQueueIds = new Set();

  async function loadSpeechQueue() {
    if (!currentMeetingId) return;

    try {
      const { body } = await api(`/api/v1/speech_queue.php?meeting_id=${currentMeetingId}`);
      const data = body?.data || {};
      currentSpeakerCache = data.speaker || null;
      const newQueue = data.queue || [];

      // Detect new hand-raise requests
      const newQueueIds = new Set(newQueue.map(r => r.id));
      for (const req of newQueue) {
        if (!previousQueueIds.has(req.id)) {
          // New request detected - show notification
          const name = req.member_name || req.full_name || 'Un membre';
          setNotif('info', `🖐️ ${name} demande la parole`);
          break; // Only one notification per poll
        }
      }
      previousQueueIds = newQueueIds;

      speechQueueCache = newQueue;

      renderSpeechQueue();
      renderCurrentSpeaker();

      // Update tab count
      const countEl = document.getElementById('tabCountSpeech');
      if (countEl) countEl.textContent = speechQueueCache.length;
    } catch (err) {
      console.warn('loadSpeechQueue:', err.message);
    }
  }

  function renderCurrentSpeaker() {
    const noSpeaker = document.getElementById('noSpeakerState');
    const activeSpeaker = document.getElementById('activeSpeakerState');
    const btnNext = document.getElementById('btnNextSpeaker');

    if (!noSpeaker || !activeSpeaker) return;

    // Clear any existing timer
    if (speechTimerInterval) {
      clearInterval(speechTimerInterval);
      speechTimerInterval = null;
    }

    if (!currentSpeakerCache) {
      Shared.show(noSpeaker, 'block');
      Shared.hide(activeSpeaker);
      if (btnNext) btnNext.disabled = speechQueueCache.length === 0;
      return;
    }

    Shared.hide(noSpeaker);
    Shared.show(activeSpeaker, 'block');

    document.getElementById('currentSpeakerName').textContent = currentSpeakerCache.full_name || '—';

    // Start timer
    const startTime = currentSpeakerCache.updated_at ? new Date(currentSpeakerCache.updated_at).getTime() : Date.now();
    updateSpeechTimer(startTime);
    speechTimerInterval = setInterval(() => updateSpeechTimer(startTime), 1000);
  }

  function updateSpeechTimer(startTime) {
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    const minutes = Math.floor(elapsed / 60);
    const seconds = elapsed % 60;
    const formatted = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
    const el = document.getElementById('currentSpeakerTime');
    if (el) el.textContent = formatted;
  }

  function renderSpeechQueue() {
    const list = document.getElementById('speechQueueList');
    if (!list) return;

    if (speechQueueCache.length === 0) {
      list.innerHTML = '<div class="text-center p-4 text-muted">Aucune demande de parole</div>';
      return;
    }

    list.innerHTML = speechQueueCache.map((s, i) => `
      <div class="speech-queue-item" data-request-id="${s.id}" data-member-id="${s.member_id}">
        <span class="speech-queue-position">${i + 1}</span>
        <span class="speech-queue-name">${escapeHtml(s.full_name || '—')}</span>
        <div class="speech-queue-actions">
          <button class="btn btn-xs btn-primary btn-grant-speech" data-member-id="${s.member_id}" title="Donner la parole">
            ${icon('mic', 'icon-xs')}
          </button>
          <button class="btn btn-xs btn-ghost btn-remove-speech" data-request-id="${s.id}" title="Retirer">
            ${icon('x', 'icon-xs')}
          </button>
        </div>
      </div>
    `).join('');

    // Bind grant speech buttons
    list.querySelectorAll('.btn-grant-speech').forEach(btn => {
      btn.addEventListener('click', () => grantSpeech(btn.dataset.memberId));
    });

    // Bind remove buttons
    list.querySelectorAll('.btn-remove-speech').forEach(btn => {
      btn.addEventListener('click', () => cancelSpeechRequest(btn.dataset.requestId));
    });
  }

  async function grantSpeech(memberId) {
    try {
      await api('/api/v1/speech_grant.php', {
        meeting_id: currentMeetingId,
        member_id: memberId
      });
      setNotif('success', 'Parole accordée');
      loadSpeechQueue();
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  async function nextSpeaker() {
    try {
      await api('/api/v1/speech_next.php', { meeting_id: currentMeetingId });
      setNotif('success', 'Orateur suivant');
      loadSpeechQueue();
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  async function endCurrentSpeech() {
    try {
      await api('/api/v1/speech_end.php', { meeting_id: currentMeetingId });
      setNotif('success', 'Parole terminée');
      loadSpeechQueue();
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  async function cancelSpeechRequest(requestId) {
    try {
      await api('/api/v1/speech_cancel.php', {
        meeting_id: currentMeetingId,
        request_id: requestId
      });
      setNotif('success', 'Demande retirée');
      loadSpeechQueue();
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  async function clearSpeechHistory() {
    const confirmed = await new Promise(resolve => {
      const m = createModal({
        id: 'clearSpeechModal',
        title: 'Vider l\'historique',
        content: `<p>Vider l'historique des prises de parole ?</p>
          <div class="flex gap-3 justify-end mt-4">
            <button class="btn btn-secondary" data-action="cancel">Annuler</button>
            <button class="btn btn-danger" data-action="confirm">Vider</button>
          </div>`
      });
      m.querySelector('[data-action="cancel"]').addEventListener('click', () => { closeModal(m); resolve(false); });
      m.querySelector('[data-action="confirm"]').addEventListener('click', () => { closeModal(m); resolve(true); });
    });
    if (!confirmed) return;
    try {
      await api('/api/v1/speech_clear.php', { meeting_id: currentMeetingId });
      setNotif('success', 'Historique vidé');
      loadSpeechQueue();
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  function showAddToQueueModal() {
    const presentMembers = attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote');
    const alreadyInQueue = new Set(speechQueueCache.map(s => s.member_id));
    const available = presentMembers.filter(m => !alreadyInQueue.has(m.member_id) && (!currentSpeakerCache || currentSpeakerCache.member_id !== m.member_id));

    const modal = createModal({
      id: 'addToQueueModal',
      title: 'Ajouter à la file',
      maxWidth: '400px',
      content: `
        <h3 id="addToQueueModal-title" style="margin:0 0 1rem;">${icon('mic', 'icon-sm icon-text')} Ajouter à la file</h3>
        ${available.length === 0
          ? '<p class="text-muted">Tous les membres présents sont déjà dans la file.</p>'
          : `
            <div class="form-group mb-3">
              <label class="form-label">Membre</label>
              <select class="form-input" id="addSpeechSelect">
                <option value="">— Sélectionner —</option>
                ${available.map(m => `<option value="${m.member_id}">${escapeHtml(m.full_name || '—')}</option>`).join('')}
              </select>
            </div>
          `
        }
        <div class="flex gap-2 justify-end">
          <button class="btn btn-secondary" id="btnCancelAddSpeech">Annuler</button>
          ${available.length > 0 ? '<button class="btn btn-primary" id="btnConfirmAddSpeech">Ajouter</button>' : ''}
        </div>
      `
    });

    document.getElementById('btnCancelAddSpeech').onclick = () => closeModal(modal);

    const btnConfirm = document.getElementById('btnConfirmAddSpeech');
    if (btnConfirm) {
      btnConfirm.onclick = async () => {
        const memberId = document.getElementById('addSpeechSelect').value;
        if (!memberId) {
          setNotif('error', 'Sélectionnez un membre');
          return;
        }
        try {
          await api('/api/v1/speech_request.php', {
            meeting_id: currentMeetingId,
            member_id: memberId
          });
          setNotif('success', 'Membre ajouté à la file');
          closeModal(modal);
          loadSpeechQueue();
        } catch (err) {
          setNotif('error', err.message);
        }
      };
    }
  }

  // =========================================================================
  // TAB: RÉSOLUTIONS - Motions
  // =========================================================================

  async function loadResolutions() {
    try {
      const { body } = await api(`/api/v1/motions_for_meeting.php?meeting_id=${currentMeetingId}`);
      motionsCache = body?.data?.motions || [];
      currentOpenMotion = motionsCache.find(m => m.opened_at && !m.closed_at) || null;
      renderResolutions();
      document.getElementById('tabCountResolutions').textContent = motionsCache.length;
    } catch (err) {
      console.warn('loadResolutions:', err.message);
    }
  }

  // Initialize previousOpenMotionId when loading meeting to avoid false "vote opened" notifications
  function initializePreviousMotionState() {
    previousOpenMotionId = currentOpenMotion?.id || null;
  }

  function renderResolutions() {
    const list = document.getElementById('resolutionsList');
    const searchTerm = (document.getElementById('resolutionSearch')?.value || '').toLowerCase();
    let filtered = motionsCache;
    if (searchTerm) {
      filtered = motionsCache.filter(m => (m.title || '').toLowerCase().includes(searchTerm));
    }

    const canEdit = !['validated', 'archived'].includes(currentMeetingStatus);
    const isLive = currentMeetingStatus === 'live';
    const totalCount = motionsCache.length;

    // Build header hint
    const headerHint = filtered.length > 0 ? `
      <div class="resolutions-list-header">
        <div class="hint">
          ${icon('mouse-pointer', 'icon-sm')}
          <span>Cliquez sur une résolution pour voir les détails</span>
        </div>
        <span>${filtered.length} résolution${filtered.length > 1 ? 's' : ''}</span>
      </div>
    ` : '';

    list.innerHTML = headerHint + (filtered.map((m, i) => {
      const isOpen = !!(m.opened_at && !m.closed_at);
      const isClosed = !!m.closed_at;
      const statusClass = isOpen ? 'open' : (isClosed ? 'closed' : 'pending');
      const statusText = isOpen ? 'Vote en cours' : (isClosed ? 'Terminé' : 'En attente');

      // Vote actions
      let voteActions = '';
      if (isLive && !isOpen && !isClosed) {
        voteActions = `<button class="btn btn-sm btn-primary btn-open-vote" data-motion-id="${m.id}">${icon('play', 'icon-sm icon-text')}Ouvrir</button>`;
      } else if (isLive && isOpen) {
        voteActions = `<button class="btn btn-sm btn-warning btn-close-vote" data-motion-id="${m.id}">${icon('square', 'icon-sm icon-text')}Terminer</button>`;
      }

      // Edit actions (only for pending resolutions)
      let editActions = '';
      if (canEdit && !isOpen && !isClosed) {
        editActions = `
          <button class="btn btn-sm btn-ghost btn-edit-motion" data-motion-id="${m.id}" title="Modifier">${icon('edit', 'icon-sm')}</button>
          <button class="btn btn-sm btn-ghost btn-delete-motion" data-motion-id="${m.id}" title="Supprimer">${icon('trash', 'icon-sm')}</button>
        `;
      }

      // Reorder buttons (only when not searching and can edit)
      let reorderBtns = '';
      if (canEdit && !searchTerm && !isOpen && !isClosed) {
        const globalIdx = motionsCache.findIndex(x => x.id === m.id);
        const canMoveUp = globalIdx > 0;
        const canMoveDown = globalIdx < totalCount - 1;
        reorderBtns = `
          <button class="btn btn-xs btn-ghost btn-move-up" data-motion-id="${m.id}" ${canMoveUp ? '' : 'disabled'} title="Monter">▲</button>
          <button class="btn btn-xs btn-ghost btn-move-down" data-motion-id="${m.id}" ${canMoveDown ? '' : 'disabled'} title="Descendre">▼</button>
        `;
      }

      const results = isClosed ? `
        <div style="display:flex;gap:1rem;font-size:0.85rem;margin-top:0.5rem;">
          <span style="color:var(--color-success)">${icon('check', 'icon-xs')} ${m.votes_for || 0}</span>
          <span style="color:var(--color-danger)">${icon('x', 'icon-xs')} ${m.votes_against || 0}</span>
          <span style="color:var(--color-text-muted)">${icon('minus', 'icon-xs')} ${m.votes_abstain || 0}</span>
        </div>
      ` : '';

      return `
        <div class="resolution-section" data-motion-id="${m.id}">
          <div class="resolution-header">
            <div class="resolution-reorder">${reorderBtns}</div>
            <span class="resolution-chevron"><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#icon-chevron-right"></use></svg></span>
            <span style="font-weight:700;margin-right:0.5rem;">${i + 1}.</span>
            <span class="resolution-title">${escapeHtml(m.title)}</span>
            <span class="resolution-status ${statusClass}">${statusText}</span>
            <div class="resolution-header-actions" style="margin-left:auto;display:flex;gap:0.5rem;align-items:center;">
              ${voteActions}
              ${editActions}
            </div>
          </div>
          <div class="resolution-body">
            <div class="resolution-content">
              ${m.description ? escapeHtml(m.description) : '<em class="text-muted">Aucune description</em>'}
            </div>
            ${results}
          </div>
        </div>
      `;
    }).join('') || '<div class="text-center p-4 text-muted">Aucune résolution</div>');

    // Bind collapsible (only on chevron/title, not buttons)
    list.querySelectorAll('.resolution-header').forEach(header => {
      header.addEventListener('click', (e) => {
        if (e.target.closest('button')) return; // Don't toggle on button clicks
        header.closest('.resolution-section').classList.toggle('expanded');
      });
    });

    // Bind vote actions
    list.querySelectorAll('.btn-open-vote').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        openVote(btn.dataset.motionId);
      });
    });

    list.querySelectorAll('.btn-close-vote').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        closeVote(btn.dataset.motionId);
      });
    });

    // Bind edit button
    list.querySelectorAll('.btn-edit-motion').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        showEditResolutionModal(btn.dataset.motionId);
      });
    });

    // Bind delete button
    list.querySelectorAll('.btn-delete-motion').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const motionId = btn.dataset.motionId;
        const motion = motionsCache.find(m => String(m.id) === String(motionId));
        const title = motion ? escapeHtml(motion.title || '—') : 'cette résolution';
        const ok = await new Promise(resolve => {
          const modal = createModal({
            id: 'deleteMotionModal',
            title: 'Supprimer la résolution',
            content: `
              <h3 id="deleteMotionModal-title" style="margin:0 0 0.75rem;font-size:1.125rem;">${icon('trash-2', 'icon-sm icon-text')} Supprimer ?</h3>
              <p style="margin:0 0 1.5rem;">Résolution : <strong>${title}</strong></p>
              <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
                <button class="btn btn-secondary" data-action="cancel">Annuler</button>
                <button class="btn btn-danger" data-action="confirm">${icon('trash-2', 'icon-sm icon-text')} Supprimer</button>
              </div>
            `
          });
          modal.querySelector('[data-action="cancel"]').addEventListener('click', () => { closeModal(modal); resolve(false); });
          modal.querySelector('[data-action="confirm"]').addEventListener('click', () => { closeModal(modal); resolve(true); });
        });
        if (!ok) return;
        try {
          await api('/api/v1/motion_delete.php', { motion_id: motionId, meeting_id: currentMeetingId });
          setNotif('success', 'Résolution supprimée');
          await loadResolutions();
          await loadStatusChecklist();
        } catch (err) {
          setNotif('error', err.message);
        }
      });
    });

    // Bind reorder buttons
    list.querySelectorAll('.btn-move-up').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        moveResolution(btn.dataset.motionId, -1);
      });
    });

    list.querySelectorAll('.btn-move-down').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        moveResolution(btn.dataset.motionId, 1);
      });
    });
  }

  // Edit resolution modal
  function showEditResolutionModal(motionId) {
    const motion = motionsCache.find(m => m.id === motionId);
    if (!motion) return;

    const modal = document.createElement('div');
    modal.className = 'modal-backdrop';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:var(--z-modal-backdrop, 400);display:flex;align-items:center;justify-content:center;opacity:1;visibility:visible;';

    modal.innerHTML = `
      <div style="background:var(--color-surface);border-radius:12px;padding:1.5rem;max-width:600px;width:90%;max-height:80vh;overflow:auto;">
        <h3 style="margin:0 0 1rem;">Modifier la résolution</h3>
        <div class="form-group mb-3">
          <label class="form-label">Titre</label>
          <input type="text" class="form-input" id="editResolutionTitle" value="${escapeHtml(motion.title || '')}" maxlength="200">
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Description / Texte complet</label>
          <textarea class="form-input" id="editResolutionDesc" rows="6">${escapeHtml(motion.description || '')}</textarea>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">
            <input type="checkbox" id="editResolutionSecret" ${motion.secret ? 'checked' : ''}>
            Vote secret
          </label>
        </div>
        <div class="flex gap-2 justify-end">
          <button class="btn btn-secondary" id="btnCancelEdit">Annuler</button>
          <button class="btn btn-primary" id="btnSaveEdit">Enregistrer</button>
        </div>
      </div>
    `;

    document.body.appendChild(modal);
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });
    document.getElementById('btnCancelEdit').onclick = () => modal.remove();

    document.getElementById('btnSaveEdit').onclick = async () => {
      const title = document.getElementById('editResolutionTitle').value.trim();
      const description = document.getElementById('editResolutionDesc').value.trim();
      const secret = document.getElementById('editResolutionSecret').checked;

      if (!title) {
        setNotif('error', 'Titre requis');
        return;
      }

      try {
        const { body } = await api('/api/v1/motions.php', {
          motion_id: motionId,
          agenda_id: motion.agenda_id,
          title,
          description,
          secret
        });

        if (body?.ok === true) {
          setNotif('success', 'Résolution mise à jour');
          modal.remove();
          await loadResolutions();
        } else {
          setNotif('error', getApiError(body, 'Erreur lors de la mise à jour'));
        }
      } catch (err) {
        setNotif('error', err.message);
      }
    };

    // Focus on title
    document.getElementById('editResolutionTitle').focus();
  }

  // Move resolution up or down
  async function moveResolution(motionId, direction) {
    const idx = motionsCache.findIndex(m => m.id === motionId);
    if (idx < 0) return;

    const newIdx = idx + direction;
    if (newIdx < 0 || newIdx >= motionsCache.length) return;

    // Swap in local cache for immediate feedback
    const ids = motionsCache.map(m => m.id);
    [ids[idx], ids[newIdx]] = [ids[newIdx], ids[idx]];

    // Optimistic update
    [motionsCache[idx], motionsCache[newIdx]] = [motionsCache[newIdx], motionsCache[idx]];
    renderResolutions();

    // Save to server
    try {
      const { body } = await api('/api/v1/motion_reorder.php', {
        meeting_id: currentMeetingId,
        motion_ids: ids
      });

      if (body?.ok !== true) {
        // Revert on error
        loadResolutions();
        setNotif('error', getApiError(body, 'Erreur lors du réordonnancement'));
      }
    } catch (err) {
      loadResolutions();
      setNotif('error', err.message);
    }
  }

  async function createResolution() {
    const title = document.getElementById('newResolutionTitle').value.trim();
    const desc = document.getElementById('newResolutionDesc').value.trim();
    if (!title) {
      setNotif('error', 'Titre requis');
      return;
    }

    try {
      const { body } = await api('/api/v1/motion_create_simple.php', {
        meeting_id: currentMeetingId,
        title: title,
        description: desc || ''
      });

      if (body?.ok === true) {
        setNotif('success', 'Résolution créée');
        Shared.hide(document.getElementById('addResolutionForm'));
        document.getElementById('newResolutionTitle').value = '';
        document.getElementById('newResolutionDesc').value = '';
        await loadResolutions();
        await loadStatusChecklist();
        checkLaunchReady();
      } else {
        setNotif('error', getApiError(body, 'Erreur lors de la création'));
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // =========================================================================
  // TAB: VOTE EN DIRECT
  // =========================================================================

  async function loadVoteTab() {
    if (!currentOpenMotion) {
      Shared.show(document.getElementById('noActiveVote'), 'block');
      Shared.hide(document.getElementById('activeVotePanel'));
      renderQuickOpenList();
      return;
    }

    Shared.hide(document.getElementById('noActiveVote'));
    Shared.show(document.getElementById('activeVotePanel'), 'block');
    document.getElementById('activeVoteTitle').textContent = currentOpenMotion.title;

    await loadBallots(currentOpenMotion.id);
    renderManualVoteList();
  }

  // Render quick open buttons in the Vote tab when no vote is active
  function renderQuickOpenList() {
    const list = document.getElementById('quickOpenMotionList');
    if (!list) return;

    const isLive = currentMeetingStatus === 'live';
    const openableMotions = motionsCache.filter(m => !m.opened_at && !m.closed_at);

    if (!isLive || openableMotions.length === 0) {
      list.innerHTML = isLive
        ? '<p class="text-muted text-sm">Aucune résolution en attente</p>'
        : '<p class="text-muted text-sm">La séance doit être en mode "live" pour ouvrir un vote</p>';
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

    // Bind quick open buttons
    list.querySelectorAll('.btn-quick-open').forEach(btn => {
      btn.addEventListener('click', () => openVote(btn.dataset.motionId));
    });
  }

  let ballotSourceCache = {}; // memberId → 'manual'|'tablet'|...

  async function loadBallots(motionId) {
    try {
      const { body } = await api(`/api/v1/ballots.php?motion_id=${motionId}`);
      const ballots = body?.data?.ballots || body?.ballots || [];
      ballotsCache = {};
      ballotSourceCache = {};
      let forCount = 0, againstCount = 0, abstainCount = 0;
      ballots.forEach(b => {
        ballotsCache[b.member_id] = b.value;
        ballotSourceCache[b.member_id] = b.source || 'tablet';
        if (b.value === 'for') forCount++;
        else if (b.value === 'against') againstCount++;
        else if (b.value === 'abstain') abstainCount++;
      });
      setText('liveVoteFor', forCount);
      setText('liveVoteAgainst', againstCount);
      setText('liveVoteAbstain', abstainCount);
    } catch (err) {
      console.warn('loadBallots:', err.message);
    }
  }

  function renderManualVoteList() {
    // P3-5: Filtrage par recherche
    const searchInput = document.getElementById('manualVoteSearch');
    const searchTerm = (searchInput ? searchInput.value : '').toLowerCase();
    let voters = attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote');
    if (searchTerm) {
      voters = voters.filter(v => (v.full_name || '').toLowerCase().includes(searchTerm));
    }
    const list = document.getElementById('manualVoteList');

    // Allow vote correction - buttons are never disabled, but show current vote
    list.innerHTML = voters.map(v => {
      const vote = ballotsCache[v.member_id];
      const hasVoted = !!vote;
      const isManual = ballotSourceCache[v.member_id] === 'manual';
      const cancelBtn = (hasVoted && isManual)
        ? `<button class="mode-btn btn-cancel-ballot" data-member-id="${v.member_id}" title="Annuler ce vote manuel" style="color:var(--color-danger);margin-left:0.25rem;">${icon('trash-2', 'icon-sm')}</button>`
        : '';
      return `
        <div class="attendance-card ${hasVoted ? 'present' : ''}" data-member-id="${v.member_id}">
          <span class="attendance-name">${escapeHtml(v.full_name || '—')}</span>
          <div class="attendance-mode-btns">
            <button class="mode-btn for ${vote === 'for' ? 'active' : ''}" data-vote="for" title="Pour">${icon('check', 'icon-sm')}</button>
            <button class="mode-btn against ${vote === 'against' ? 'active' : ''}" data-vote="against" title="Contre">${icon('x', 'icon-sm')}</button>
            <button class="mode-btn abstain ${vote === 'abstain' ? 'active' : ''}" data-vote="abstain" title="Abstention">${icon('minus', 'icon-sm')}</button>
            ${cancelBtn}
          </div>
        </div>
      `;
    }).join('') || '<div class="text-center p-4 text-muted">Aucun votant</div>';

    // Bind all buttons (allow vote correction)
    list.querySelectorAll('.mode-btn').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        const card = e.target.closest('.attendance-card');
        const memberId = card.dataset.memberId;
        const newVote = btn.dataset.vote;
        const currentVote = ballotsCache[memberId];

        // Skip if clicking same vote
        if (currentVote === newVote) return;

        // Confirm if correcting existing vote via modal
        const _vl = { for: 'Pour', against: 'Contre', abstain: 'Abstention' };
        if (currentVote) {
          const ok = await new Promise(resolve => {
            const memberName = card.querySelector('.attendance-name')?.textContent || '—';
            const modal = createModal({
              id: 'correctVoteModal',
              title: 'Modifier le vote',
              content: `
                <h3 id="correctVoteModal-title" style="margin:0 0 0.75rem;font-size:1.125rem;">Modifier le vote ?</h3>
                <p style="margin:0 0 0.5rem;">Membre : <strong>${escapeHtml(memberName)}</strong></p>
                <p style="margin:0 0 1.5rem;">De <strong>${_vl[currentVote] || currentVote}</strong> vers <strong>${_vl[newVote] || newVote}</strong></p>
                <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
                  <button class="btn btn-secondary" data-action="cancel">Annuler</button>
                  <button class="btn btn-primary" data-action="confirm">Modifier</button>
                </div>
              `
            });
            modal.querySelector('[data-action="cancel"]').addEventListener('click', () => { closeModal(modal); resolve(false); });
            modal.querySelector('[data-action="confirm"]').addEventListener('click', () => { closeModal(modal); resolve(true); });
          });
          if (!ok) return;
        }

        await castManualVote(memberId, newVote);
      });
    });

    // P3-4: Cancel manual vote buttons
    list.querySelectorAll('.btn-cancel-ballot').forEach(btn => {
      btn.addEventListener('click', async () => {
        const memberId = btn.dataset.memberId;
        const card = btn.closest('.attendance-card');
        const memberName = card?.querySelector('.attendance-name')?.textContent || '—';
        const voteLabels = { for: 'Pour', against: 'Contre', abstain: 'Abstention' };
        const currentVote = ballotsCache[memberId];

        const confirmed = await new Promise(resolve => {
          const modal = createModal({
            id: 'cancelBallotModal',
            title: 'Annuler le vote manuel',
            content: `
              <h3 id="cancelBallotModal-title" style="margin:0 0 0.75rem;font-size:1.125rem;">Annuler ce vote ?</h3>
              <p>Membre : <strong>${escapeHtml(memberName)}</strong></p>
              <p>Vote actuel : <strong>${voteLabels[currentVote] || currentVote}</strong></p>
              <div class="form-group" style="margin-top:1rem;">
                <label class="form-label">Justification <span class="text-danger">*</span></label>
                <input class="form-input" type="text" id="cancelBallotReason" placeholder="Raison de l'annulation" required>
              </div>
              <div style="display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1rem;">
                <button class="btn btn-secondary" data-action="cancel">Annuler</button>
                <button class="btn btn-danger" data-action="confirm" id="btnConfirmCancel" disabled>Supprimer le vote</button>
              </div>
            `
          });
          const reasonInput = modal.querySelector('#cancelBallotReason');
          const confirmBtn = modal.querySelector('#btnConfirmCancel');
          reasonInput.addEventListener('input', () => { confirmBtn.disabled = reasonInput.value.trim().length < 3; });
          modal.querySelector('[data-action="cancel"]').addEventListener('click', () => { closeModal(modal); resolve(null); });
          confirmBtn.addEventListener('click', () => { closeModal(modal); resolve(reasonInput.value.trim()); });
          setTimeout(() => reasonInput.focus(), 60);
        });

        if (!confirmed) return;

        btn.disabled = true;
        try {
          const { body } = await api('/api/v1/ballots_cancel.php', {
            motion_id: currentOpenMotion.id,
            member_id: memberId,
            reason: confirmed
          });
          if (body?.ok) {
            delete ballotsCache[memberId];
            delete ballotSourceCache[memberId];
            await loadBallots(currentOpenMotion.id);
            renderManualVoteList();
            setNotif('success', 'Vote annulé');
          } else {
            setNotif('error', body?.error_label || body?.error || 'Erreur lors de l\'annulation');
          }
        } catch (err) {
          setNotif('error', err.message);
        }
        btn.disabled = false;
      });
    });
  }

  const VALID_VOTE_TYPES = ['for', 'against', 'abstain'];

  async function castManualVote(memberId, vote) {
    if (!currentOpenMotion) return;

    // Validate vote type
    if (!VALID_VOTE_TYPES.includes(vote)) {
      setNotif('error', `Type de vote invalide: ${vote}`);
      return;
    }

    // P3-3: Lire la justification depuis le champ éditable
    const justifInput = document.getElementById('manualVoteJustification');
    const justification = (justifInput ? justifInput.value.trim() : '') || 'Vote opérateur manuel';

    try {
      const { body } = await api('/api/v1/manual_vote.php', {
        meeting_id: currentMeetingId,
        motion_id: currentOpenMotion.id,
        member_id: memberId,
        vote: vote,
        justification: justification
      });

      if (body?.ok === true) {
        ballotsCache[memberId] = vote;
        await loadBallots(currentOpenMotion.id);
        renderManualVoteList();
        setNotif('success', 'Vote enregistré');
      } else {
        setNotif('error', getApiError(body, 'Erreur lors du vote'));
        // P3-6: Refresh auto pour resynchroniser l'état après erreur
        if (currentOpenMotion) await loadBallots(currentOpenMotion.id);
        renderManualVoteList();
      }
    } catch (err) {
      setNotif('error', err.message);
      // P3-6: Refresh auto pour resynchroniser après erreur réseau
      if (currentOpenMotion) await loadBallots(currentOpenMotion.id);
      renderManualVoteList();
    }
  }

  /**
   * Apply unanimity vote - set all present/remote voters to the same vote
   * @param {'for'|'against'|'abstain'} voteType - The vote type to apply to all voters
   */
  async function applyUnanimity(voteType) {
    if (!currentOpenMotion) {
      setNotif('error', 'Aucun vote en cours');
      return;
    }

    const voteLabels = { for: 'Pour', against: 'Contre', abstain: 'Abstention' };
    const voteColors = { for: 'var(--color-success)', against: 'var(--color-danger)', abstain: 'var(--color-text-muted)' };
    const voters = attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote');

    if (voters.length === 0) {
      setNotif('error', 'Aucun votant présent');
      return;
    }

    // P3-2: Modale de confirmation au lieu de confirm()
    const alreadyVoted = voters.filter(v => ballotsCache[v.member_id]).length;
    const motionTitle = currentOpenMotion ? escapeHtml(currentOpenMotion.title || '—') : '—';

    const confirmed = await new Promise(resolve => {
      const modal = createModal({
        id: 'unanimityConfirmModal',
        title: 'Confirmer le vote unanime',
        content: `
          <h3 id="unanimityConfirmModal-title" style="margin:0 0 0.75rem;font-size:1.125rem;">${icon('alert-triangle', 'icon-sm icon-text')} Vote unanime</h3>
          <p style="margin:0 0 0.5rem;">Résolution : <strong>${motionTitle}</strong></p>
          <p style="margin:0 0 0.5rem;">Vote : <strong style="color:${voteColors[voteType]}">${voteLabels[voteType]}</strong> pour <strong>${voters.length}</strong> votant(s)</p>
          ${alreadyVoted > 0 ? `<p style="margin:0 0 0.5rem;color:var(--color-warning);font-size:0.875rem;">${icon('alert-triangle', 'icon-sm icon-text')} ${alreadyVoted} votant(s) ont déjà voté — leur vote existant sera conservé.</p>` : ''}
          <p style="margin:0 0 1.5rem;color:var(--color-text-muted);font-size:0.875rem;">Cette action enregistrera un vote manuel pour chaque votant présent ou à distance.</p>
          <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
            <button class="btn btn-secondary" data-action="cancel">Annuler</button>
            <button class="btn btn-primary" data-action="confirm">Confirmer (${voters.length} votes)</button>
          </div>
        `
      });
      modal.querySelector('[data-action="cancel"]').addEventListener('click', () => { closeModal(modal); resolve(false); });
      modal.querySelector('[data-action="confirm"]').addEventListener('click', () => { closeModal(modal); resolve(true); });
    });
    if (!confirmed) return;

    let successCount = 0;
    let errorCount = 0;

    // Show loading state with spinner
    const btns = ['btnUnanimityFor', 'btnUnanimityAgainst', 'btnUnanimityAbstain']
      .map(id => document.getElementById(id))
      .filter(Boolean);
    btns.forEach(btn => {
      btn.disabled = true;
      btn.dataset.origHtml = btn.innerHTML;
      btn.innerHTML = '<span class="spinner spinner-sm"></span> Traitement…';
    });

    try {
      // Process votes in parallel batches for speed
      const batchSize = 5;
      for (let i = 0; i < voters.length; i += batchSize) {
        const batch = voters.slice(i, i + batchSize);
        const results = await Promise.allSettled(
          batch.map(voter =>
            api('/api/v1/manual_vote.php', {
              meeting_id: currentMeetingId,
              motion_id: currentOpenMotion.id,
              member_id: voter.member_id,
              vote: voteType,
              justification: `Unanimité opérateur: ${voteLabels[voteType]}`
            })
          )
        );

        results.forEach((result, idx) => {
          if (result.status === 'fulfilled' && result.value.body?.ok) {
            successCount++;
            ballotsCache[batch[idx].member_id] = voteType;
          } else {
            errorCount++;
          }
        });
      }

      // Refresh display
      await loadBallots(currentOpenMotion.id);
      renderManualVoteList();

      if (errorCount === 0) {
        setNotif('success', `Unanimité "${voteLabels[voteType]}" appliquée (${successCount} votes)`);
      } else {
        setNotif('warning', `${successCount} votes enregistrés, ${errorCount} erreur(s)`);
      }
    } catch (err) {
      setNotif('error', 'Erreur: ' + err.message);
    } finally {
      btns.forEach(btn => {
        btn.disabled = false;
        btn.innerHTML = btn.dataset.origHtml || btn.innerHTML;
      });
    }
  }

  async function openVote(motionId) {
    // P3-1: Confirmation modale avant ouverture
    const motion = motionsCache.find(m => String(m.id) === String(motionId));
    const motionTitle = motion ? escapeHtml(motion.title || '—') : 'cette résolution';

    const confirmed = await new Promise(resolve => {
      const modal = createModal({
        id: 'openVoteConfirmModal',
        title: 'Confirmer l\'ouverture du vote',
        content: `
          <h3 id="openVoteConfirmModal-title" style="margin:0 0 0.75rem;font-size:1.125rem;">${icon('alert-triangle', 'icon-sm icon-text')} Ouvrir le vote ?</h3>
          <p style="margin:0 0 0.5rem;">Résolution : <strong>${motionTitle}</strong></p>
          <p style="margin:0 0 1.5rem;color:var(--color-text-muted);font-size:0.875rem;">Le vote sera immédiatement accessible à tous les votants. Un seul vote peut être ouvert à la fois.</p>
          <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
            <button class="btn btn-secondary" data-action="cancel">Annuler</button>
            <button class="btn btn-primary" data-action="confirm">${icon('play', 'icon-sm icon-text')} Ouvrir le vote</button>
          </div>
        `
      });
      modal.querySelector('[data-action="cancel"]').addEventListener('click', () => { closeModal(modal); resolve(false); });
      modal.querySelector('[data-action="confirm"]').addEventListener('click', () => { closeModal(modal); resolve(true); });
    });
    if (!confirmed) return;

    // Disable ALL open-vote buttons and quick-open buttons to prevent any double-click
    const openBtns = document.querySelectorAll('.btn-open-vote, .btn-quick-open');
    openBtns.forEach(btn => {
      btn.disabled = true;
      btn.dataset.origHtml = btn.dataset.origHtml || btn.innerHTML;
      btn.innerHTML = '<span class="spinner spinner-sm"></span> Ouverture…';
    });

    try {
      const openResult = await api('/api/v1/motions_open.php', { meeting_id: currentMeetingId, motion_id: motionId });

      if (!openResult.body?.ok) {
        const errorMsg = getApiError(openResult.body, 'Erreur ouverture vote');
        setNotif('error', errorMsg);
        await loadResolutions();
        return;
      }

      setNotif('success', 'Vote ouvert');
      announce('Vote ouvert.');

      await loadResolutions();

      if (currentMode === 'exec') {
        if (currentOpenMotion) await loadBallots(currentOpenMotion.id);
        refreshExecView();
      } else {
        switchTab('vote');
        await loadVoteTab();
      }
    } catch (err) {
      setNotif('error', err.message);
      await loadResolutions();
    }
  }

  async function closeVote(motionId) {
    // P2-4 / P3: Modale de confirmation avec récapitulatif au lieu de confirm()
    const motion = motionsCache.find(m => String(m.id) === String(motionId));
    const motionTitle = motion ? escapeHtml(motion.title || '—') : 'ce vote';
    const vFor = parseInt(document.getElementById('liveVoteFor')?.textContent || '0', 10);
    const vAgainst = parseInt(document.getElementById('liveVoteAgainst')?.textContent || '0', 10);
    const vAbstain = parseInt(document.getElementById('liveVoteAbstain')?.textContent || '0', 10);
    const vTotal = vFor + vAgainst + vAbstain;

    const confirmed = await new Promise(resolve => {
      const modal = createModal({
        id: 'closeVoteConfirmModal',
        title: 'Terminer le scrutin',
        content: `
          <h3 id="closeVoteConfirmModal-title" style="margin:0 0 0.75rem;font-size:1.125rem;">${icon('square', 'icon-sm icon-text')} Terminer le scrutin ?</h3>
          <p style="margin:0 0 0.75rem;">Résolution : <strong>${motionTitle}</strong></p>
          <div style="display:flex;gap:1rem;margin:0 0 1rem;font-size:0.9375rem;">
            <span style="color:var(--color-success);">${icon('check', 'icon-sm icon-text')} ${vFor}</span>
            <span style="color:var(--color-danger);">${icon('x', 'icon-sm icon-text')} ${vAgainst}</span>
            <span style="color:var(--color-text-muted);">&#9675; ${vAbstain}</span>
            <span class="text-muted">&mdash; ${vTotal} vote(s)</span>
          </div>
          <p style="margin:0 0 1.5rem;color:var(--color-warning);font-size:0.875rem;">${icon('alert-triangle', 'icon-sm icon-text')} Les résultats seront figés définitivement. Plus aucun vote ne sera accepté.</p>
          <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
            <button class="btn btn-secondary" data-action="cancel">Annuler</button>
            <button class="btn btn-warning" data-action="confirm">${icon('square', 'icon-sm icon-text')} Terminer le scrutin</button>
          </div>
        `
      });
      modal.querySelector('[data-action="cancel"]').addEventListener('click', () => { closeModal(modal); resolve(false); });
      modal.querySelector('[data-action="confirm"]').addEventListener('click', () => { closeModal(modal); resolve(true); });
    });
    if (!confirmed) return;

    // Disable all close-vote buttons during the operation and show spinner
    const closeBtns = document.querySelectorAll('.btn-close-vote, #btnCloseVote, #execBtnCloseVote');
    closeBtns.forEach(b => {
      b.disabled = true;
      b.dataset.origHtml = b.dataset.origHtml || b.innerHTML;
      b.innerHTML = '<span class="spinner spinner-sm"></span> Clôture…';
    });

    try {
      await api('/api/v1/motions_close.php', { meeting_id: currentMeetingId, motion_id: motionId });
      setNotif('success', 'Vote clôturé');
      currentOpenMotion = null;
      ballotsCache = {};
      await loadResolutions();
      await loadVoteTab();
      if (currentMode === 'exec') refreshExecView();
      announce('Vote clôturé.');

      // P2-5: Proclamation explicite des résultats
      const closedMotion = motionsCache.find(m => String(m.id) === String(motionId));
      if (closedMotion) {
        const cFor = closedMotion.votes_for || 0;
        const cAgainst = closedMotion.votes_against || 0;
        const cAbstain = closedMotion.votes_abstain || 0;
        const cBlanc = closedMotion.votes_blank || 0;
        const cTotal = cFor + cAgainst + cAbstain + cBlanc;
        const adopted = cFor > cAgainst;
        const resultText = adopted ? 'ADOPTÉE' : 'REJETÉE';
        const resultColor = adopted ? 'var(--color-success)' : 'var(--color-danger)';
        const resultIcon = adopted ? 'check-circle' : 'x-circle';

        const proclamModal = createModal({
          id: 'proclamationModal',
          title: 'Résultat du vote',
          maxWidth: '520px',
          content: `
            <div style="text-align:center;padding:1rem 0;">
              <i data-lucide="${resultIcon}" style="width:64px;height:64px;color:${resultColor};margin-bottom:1rem;"></i>
              <h2 id="proclamationModal-title" style="font-size:1.5rem;margin:0 0 0.5rem;">${escapeHtml(closedMotion.title)}</h2>
              <p style="font-size:2rem;font-weight:700;color:${resultColor};margin:0.5rem 0;" aria-live="assertive">
                ${resultText}
              </p>
              <div style="display:flex;justify-content:center;gap:2rem;margin:1.5rem 0;font-size:1.1rem;">
                <span><strong style="color:var(--color-success)">${cFor}</strong> Pour</span>
                <span><strong style="color:var(--color-danger)">${cAgainst}</strong> Contre</span>
                <span><strong style="color:var(--color-text-muted)">${cAbstain}</strong> Abstention</span>
                ${cBlanc > 0 ? `<span><strong style="color:var(--color-text-muted)">${cBlanc}</strong> Blanc</span>` : ''}
              </div>
              <p style="color:var(--color-text-muted);font-size:0.9rem;">${cTotal} vote${cTotal !== 1 ? 's' : ''} exprimé${cTotal !== 1 ? 's' : ''}</p>
              <button class="btn btn-primary" data-action="close-proclamation" style="margin-top:1rem;">Fermer</button>
            </div>
          `
        });

        if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [proclamModal] });
        proclamModal.querySelector('[data-action="close-proclamation"]').addEventListener('click', () => closeModal(proclamModal));
      }
    } catch (err) {
      setNotif('error', err.message);
      await loadResolutions();
    }
  }

  // =========================================================================
  // TAB: RÉSULTATS
  // =========================================================================

  async function loadResults() {
    const closed = motionsCache.filter(m => m.closed_at);
    const adopted = closed.filter(m => (m.votes_for || 0) > (m.votes_against || 0)).length;
    const rejected = closed.length - adopted;

    setText('resultAdopted', adopted);
    setText('resultRejected', rejected);
    setText('resultTotal', motionsCache.length);

    const list = document.getElementById('resultsDetailList');
    list.innerHTML = motionsCache.map((m, i) => {
      const isClosed = !!m.closed_at;
      const vFor = m.votes_for || 0;
      const vAgainst = m.votes_against || 0;
      const vAbstain = m.votes_abstain || 0;
      const total = vFor + vAgainst + vAbstain;
      const pct = total > 0 ? Math.round((vFor / total) * 100) : 0;
      const status = !isClosed ? 'En attente' : (vFor > vAgainst ? 'Adoptée' : 'Rejetée');
      const statusColor = !isClosed ? 'var(--color-text-muted)' : (vFor > vAgainst ? 'var(--color-success)' : 'var(--color-danger)');

      return `
        <div class="settings-section" style="margin-bottom:1rem;">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <strong>${i + 1}. ${escapeHtml(m.title)}</strong>
            <span style="color:${statusColor};font-weight:600;">${status}</span>
          </div>
          ${isClosed ? `
            <div style="display:flex;gap:2rem;margin-top:1rem;font-size:1.1rem;">
              <span style="color:var(--color-success)">${icon('check', 'icon-sm')} ${vFor}</span>
              <span style="color:var(--color-danger)">${icon('x', 'icon-sm')} ${vAgainst}</span>
              <span style="color:var(--color-text-muted)">${icon('minus', 'icon-sm')} ${vAbstain}</span>
              <span style="margin-left:auto;">${pct}% pour</span>
            </div>
          ` : ''}
        </div>
      `;
    }).join('') || '<div class="text-center p-4 text-muted">Aucune résolution</div>';

    // Export links (preview=1 generates a draft if meeting not validated)
    document.getElementById('exportPV').href = `/api/v1/meeting_generate_report_pdf.php?meeting_id=${currentMeetingId}&preview=1`;
    document.getElementById('exportAttendance').href = `/api/v1/export_attendance_csv.php?meeting_id=${currentMeetingId}`;
    document.getElementById('exportVotes').href = `/api/v1/export_votes_csv.php?meeting_id=${currentMeetingId}`;

    // Update close session section
    updateCloseSessionStatus();
  }

  function updateCloseSessionStatus() {
    const section = document.getElementById('closeSessionSection');
    const statusDiv = document.getElementById('closeSessionStatus');
    const btnClose = document.getElementById('btnCloseSession');
    if (!section || !statusDiv || !btnClose) return;

    // Only show for live sessions
    if (currentMeetingStatus !== 'live') {
      Shared.hide(section);
      return;
    }
    Shared.show(section, 'block');

    // Check readiness
    const total = motionsCache.length;
    const closed = motionsCache.filter(m => m.closed_at).length;
    const open = motionsCache.filter(m => m.opened_at && !m.closed_at).length;
    const pending = total - closed - open;
    const allClosed = total > 0 && closed === total;
    const hasOpenVote = open > 0;

    let statusHtml = '';
    let canClose = true;

    if (hasOpenVote) {
      statusHtml += `<div class="alert alert-warning mb-2">${icon('alert-triangle', 'icon-sm icon-text')}Un vote est en cours — clôturez-le avant de fermer la séance.</div>`;
      canClose = false;
    }

    if (pending > 0) {
      statusHtml += `<div class="alert alert-info mb-2">${icon('info', 'icon-sm icon-text')}${pending} résolution(s) n'ont pas encore été votées.</div>`;
    }

    if (allClosed) {
      statusHtml += `<div class="alert alert-success mb-2">${icon('check-circle', 'icon-sm icon-text')}Tous les votes sont terminés. Vous pouvez clôturer la séance.</div>`;
    }

    statusDiv.innerHTML = statusHtml || '<div class="text-muted">Prêt à clôturer.</div>';
    btnClose.disabled = !canClose;
  }

  async function closeSession() {
    const open = motionsCache.filter(m => m.opened_at && !m.closed_at);
    if (open.length > 0) {
      setNotif('error', 'Impossible de clôturer : un vote est encore ouvert');
      return;
    }

    const pending = motionsCache.filter(m => !m.opened_at && !m.closed_at);
    const closed = motionsCache.filter(m => m.closed_at).length;
    const total = motionsCache.length;
    const meetingTitle = currentMeeting ? escapeHtml(currentMeeting.title || '') : '';
    const presentCount = attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote').length;

    let warningHtml = '';
    if (pending.length > 0) {
      warningHtml = `<p style="margin:0 0 0.75rem;color:var(--color-warning);">${icon('alert-triangle', 'icon-sm icon-text')} <strong>${pending.length} résolution(s)</strong> n'ont pas encore été votées.</p>`;
    }

    const confirmed = await new Promise(resolve => {
      const modal = createModal({
        id: 'closeSessionConfirmModal',
        title: 'Clôturer la séance',
        content: `
          <h3 id="closeSessionConfirmModal-title" style="margin:0 0 0.75rem;font-size:1.125rem;">${icon('square', 'icon-sm icon-text')} Clôturer la séance ?</h3>
          ${meetingTitle ? `<p style="margin:0 0 0.5rem;"><strong>${meetingTitle}</strong></p>` : ''}
          <div style="margin:0 0 0.75rem;font-size:0.9375rem;color:var(--color-text-muted);">
            ${icon('users', 'icon-sm icon-text')} ${presentCount} présent(s) &mdash; ${icon('check-circle', 'icon-sm icon-text')} ${closed}/${total} résolution(s) votée(s)
          </div>
          ${warningHtml}
          <p style="margin:0 0 1.5rem;color:var(--color-danger);font-size:0.875rem;">${icon('alert-triangle', 'icon-sm icon-text')} Cette action est irréversible. La séance passera en statut « clôturée ».</p>
          <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
            <button class="btn btn-secondary" data-action="cancel">Annuler</button>
            <button class="btn btn-danger" data-action="confirm">${icon('square', 'icon-sm icon-text')} Clôturer la séance</button>
          </div>
        `
      });
      modal.querySelector('[data-action="cancel"]').addEventListener('click', () => { closeModal(modal); resolve(false); });
      modal.querySelector('[data-action="confirm"]').addEventListener('click', () => { closeModal(modal); resolve(true); });
    });
    if (!confirmed) return;

    try {
      const { body } = await api('/api/v1/meeting_transition.php', {
        meeting_id: currentMeetingId,
        to_status: 'closed'
      });
      if (body?.ok) {
        setNotif('success', 'Séance clôturée');
        await loadMeetingContext(currentMeetingId);
        loadMeetings();
        setMode('setup');
        switchTab('resultats');
        announce('Séance clôturée.');
      } else {
        setNotif('error', getApiError(body, 'Erreur lors de la clôture'));
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // =========================================================================
  // TRANSITIONS
  // =========================================================================

  async function doTransition(toStatus) {
    const statusLabels = { draft: 'brouillon', scheduled: 'planifiée', frozen: 'gelée', live: 'en cours', closed: 'clôturée', validated: 'validée', archived: 'archivée' };
    const statusLabel = statusLabels[toStatus] || toStatus;
    const confirmed = await new Promise(resolve => {
      const modal = createModal({
        id: 'transitionConfirmModal',
        title: 'Confirmer le changement d\'état',
        content: `
          <h3 id="transitionConfirmModal-title" style="margin:0 0 0.75rem;font-size:1.125rem;">Changer l'état ?</h3>
          <p style="margin:0 0 1.5rem;">La séance passera en statut <strong>« ${statusLabel} »</strong>.</p>
          <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
            <button class="btn btn-secondary" data-action="cancel">Annuler</button>
            <button class="btn btn-primary" data-action="confirm">Confirmer</button>
          </div>
        `
      });
      modal.querySelector('[data-action="cancel"]').addEventListener('click', () => { closeModal(modal); resolve(false); });
      modal.querySelector('[data-action="confirm"]').addEventListener('click', () => { closeModal(modal); resolve(true); });
    });
    if (!confirmed) return;
    try {
      const { body } = await api('/api/v1/meeting_transition.php', {
        meeting_id: currentMeetingId,
        to_status: toStatus
      });
      if (body?.ok) {
        if (body.warnings?.length) {
          body.warnings.forEach(w => setNotif('warning', w.msg));
        }
        setNotif('success', `Séance passée en "${statusLabel}"`);
        await loadMeetingContext(currentMeetingId);
        loadMeetings();

        // Auto-switch mode based on new status
        if (toStatus === 'live') {
          setMode('exec');
          announce('Séance en cours — mode exécution activé.');
        } else if (['closed', 'validated', 'archived'].includes(toStatus)) {
          setMode('setup');
          switchTab('resultats');
          announce(`Séance ${statusLabel}.`);
        }
      } else {
        setNotif('error', getApiError(body));
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // =========================================================================
  // MODE SWITCH (Préparation / Exécution)
  // =========================================================================

  function setMode(mode) {
    // Prevent entering exec mode if meeting is not live
    if (mode === 'exec' && currentMeetingStatus !== 'live') {
      mode = 'setup';
    }
    currentMode = mode;

    // Update button states
    if (btnModeSetup) {
      btnModeSetup.classList.toggle('active', mode === 'setup');
      btnModeSetup.setAttribute('aria-pressed', String(mode === 'setup'));
    }
    if (btnModeExec) {
      btnModeExec.classList.toggle('active', mode === 'exec');
      btnModeExec.setAttribute('aria-pressed', String(mode === 'exec'));
      // Disable exec button when meeting is not live
      btnModeExec.disabled = currentMeetingStatus !== 'live';
    }

    // Toggle views
    if (mode === 'setup') {
      if (viewSetup) viewSetup.hidden = false;
      if (viewExec) viewExec.hidden = true;
      // Show/hide prep mode switch and tabs based on prep mode
      if (prepModeSwitch) prepModeSwitch.hidden = false;
      if (prepMode === 'advanced') {
        Shared.show(tabsNav, 'flex');
      } else {
        Shared.hide(tabsNav);
      }
      refreshWizardStep();
    } else {
      if (viewSetup) viewSetup.hidden = true;
      if (viewExec) viewExec.hidden = false;
      Shared.hide(tabsNav);
      if (prepModeSwitch) prepModeSwitch.hidden = true;
      refreshExecView();
      startSessionTimer();
    }

    updatePrimaryButton();
    updateContextHint();
    announce(mode === 'setup' ? 'Mode préparation activé' : 'Mode exécution activé');
  }

  function updatePrimaryButton() {
    if (!btnPrimary) return;

    if (!currentMeetingId) {
      btnPrimary.disabled = true;
      btnPrimary.textContent = 'Ouvrir la séance';
      btnPrimary.onclick = null;
      return;
    }

    if (currentMode === 'setup') {
      if (['draft', 'scheduled', 'frozen'].includes(currentMeetingStatus)) {
        const score = getConformityScore();
        btnPrimary.disabled = score < 3;
        btnPrimary.textContent = 'Ouvrir la séance';
        btnPrimary.onclick = launchSession;
      } else if (currentMeetingStatus === 'live') {
        btnPrimary.disabled = false;
        btnPrimary.textContent = 'Passer en exécution';
        btnPrimary.onclick = () => setMode('exec');
      } else {
        btnPrimary.disabled = true;
        btnPrimary.textContent = 'Séance terminée';
        btnPrimary.onclick = null;
      }
    } else {
      // Exec mode
      if (currentOpenMotion) {
        btnPrimary.disabled = false;
        btnPrimary.textContent = 'Voir le vote';
        btnPrimary.onclick = () => {
          const el = document.getElementById('execVoteCard');
          if (el) el.scrollIntoView({ behavior: 'smooth' });
        };
      } else {
        btnPrimary.disabled = false;
        btnPrimary.textContent = 'Préparation';
        btnPrimary.onclick = () => setMode('setup');
      }
    }
  }

  function updateContextHint() {
    if (!contextHint) return;

    if (!currentMeetingId) {
      contextHint.textContent = 'Sélectionnez une séance…';
      return;
    }

    if (currentMode === 'setup') {
      const score = getConformityScore();
      if (currentMeetingStatus === 'live') {
        contextHint.textContent = 'Séance en cours — basculez en exécution.';
      } else if (score >= 4) {
        contextHint.textContent = 'Séance prête — vous pouvez lancer.';
      } else {
        contextHint.textContent = 'Préparez la séance (' + score + '/4 pré-requis validés).';
      }
    } else {
      if (currentOpenMotion) {
        contextHint.textContent = 'Vote en cours : ' + (currentOpenMotion.title || '');
      } else if (currentMeetingStatus === 'live') {
        contextHint.textContent = 'Séance en cours — aucun vote ouvert.';
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

  function startClock() {
    function tick() {
      const now = new Date();
      if (barClock) {
        barClock.textContent = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
      }
    }
    tick();
    setInterval(tick, 30000);
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
    // 3. Convocations (optional — always counts)
    score++;
    // 4. Rules & presidency
    const hasQuorum = !!(currentMeeting && currentMeeting.quorum_policy_id);
    const presEl = document.getElementById('settingPresident');
    const hasPresident = !!(presEl && presEl.value);
    if (hasQuorum || hasPresident) score++;
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
        label: 'Registre des membres',
        done: hasMembers,
        status: hasMembers ? membersCache.length + ' membre(s)' : 'à faire'
      },
      {
        key: 'attendance',
        label: 'Présences & procurations',
        done: hasPresent,
        status: hasPresent
          ? presentCount + ' présent(s)' + (activeProxies ? ', ' + activeProxies + ' proc.' : '')
          : 'à faire'
      },
      {
        key: 'convocations',
        label: 'Convocations',
        done: true,
        optional: true,
        status: 'optionnel'
      },
      {
        key: 'rules',
        label: 'Règlement & présidence',
        done: hasRules,
        status: hasRules
          ? (hasPresident ? 'président assigné' : 'politiques configurées')
          : 'à faire'
      }
    ];

    const score = steps.filter(s => s.done).length;

    checklist.innerHTML = steps.map(s => {
      const doneClass = s.done ? 'done' : '';
      const optClass = s.optional ? 'optional' : '';
      const iconClass = s.done ? 'done' : 'pending';
      return '<div class="conformity-item ' + doneClass + ' ' + optClass + '" data-step="' + s.key + '">'
        + '<span class="conformity-icon ' + iconClass + '"></span>'
        + '<span class="conformity-label">' + s.label + '</span>'
        + '<span class="conformity-status">' + s.status + '</span>'
        + '</div>';
    }).join('');

    // Update score display
    const setupScoreEl = document.getElementById('setupScore');
    if (setupScoreEl) setupScoreEl.textContent = score + '/4';

    // Update health chip
    updateHealthChip(score);

    // Update primary button and context
    updatePrimaryButton();
    updateContextHint();
  }

  function updateHealthChip(score) {
    if (!healthChip) return;

    if (!currentMeetingId) {
      healthChip.hidden = true;
      return;
    }

    healthChip.hidden = false;
    if (healthScore) healthScore.textContent = score + '/4';
    if (healthHint) healthHint.textContent = 'pré-requis';

    const dot = healthChip.querySelector('.health-dot');
    if (dot) {
      dot.classList.remove('ok', 'warn', 'danger');
      if (score >= 4) dot.classList.add('ok');
      else if (score >= 2) dot.classList.add('warn');
      else dot.classList.add('danger');
    }
  }

  // =========================================================================
  // ALERTS
  // =========================================================================

  function collectAlerts() {
    const alerts = [];

    if (!currentMeetingId) return alerts;

    // Conformity check
    const score = getConformityScore();
    if (score < 4) {
      alerts.push({
        title: 'Préparation incomplète',
        message: score + '/4 pré-requis validés.',
        severity: score < 2 ? 'critical' : 'warning'
      });
    }

    // Quorum check (when live) — use configured threshold from policy
    if (currentMeetingStatus === 'live' && currentMeeting && currentMeeting.quorum_policy_id) {
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

    target.innerHTML = alerts.map(a =>
      '<div class="alert-item ' + a.severity + '">'
      + '<div class="alert-item-title">' + escapeHtml(a.title) + '</div>'
      + '<div class="alert-item-message">' + escapeHtml(a.message) + '</div>'
      + '</div>'
    ).join('');
  }

  function refreshAlerts() {
    renderAlertsPanel('setupAlertsList', 'setupAlertCount');
    renderAlertsPanel('execAlertsList', 'execAlertCount');
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

  function refreshExecSpeech() {
    const speakerInfo = document.getElementById('execSpeakerInfo');
    const queueList = document.getElementById('execSpeechQueue');

    if (speakerInfo) {
      speakerInfo.innerHTML = currentSpeakerCache
        ? '<strong>' + escapeHtml(currentSpeakerCache.full_name || '—') + '</strong> a la parole'
        : '<span class="text-muted">Aucun orateur</span>';
    }

    if (queueList) {
      if (speechQueueCache.length === 0) {
        queueList.innerHTML = '<span class="text-muted text-sm">File vide</span>';
      } else {
        queueList.innerHTML = speechQueueCache.slice(0, 5).map(function(s, i) {
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

    list.innerHTML = voters.slice(0, 20).map(function(v) {
      const vote = ballotsCache[v.member_id];
      return '<div class="exec-manual-vote-row" data-member-id="' + v.member_id + '">'
        + '<span class="text-sm">' + escapeHtml(v.full_name || '—') + '</span>'
        + '<div class="flex gap-1">'
        + '<button class="btn btn-xs ' + (vote === 'for' ? 'btn-success' : 'btn-ghost') + '" data-vote="for" title="Pour">P</button>'
        + '<button class="btn btn-xs ' + (vote === 'against' ? 'btn-danger' : 'btn-ghost') + '" data-vote="against" title="Contre">C</button>'
        + '<button class="btn btn-xs ' + (vote === 'abstain' ? 'btn-warning' : 'btn-ghost') + '" data-vote="abstain" title="Abstention">A</button>'
        + '</div></div>';
    }).join('') || '<span class="text-muted text-sm">Aucun votant</span>';

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

  // Presence search
  document.getElementById('presenceSearch')?.addEventListener('input', renderAttendance);
  document.getElementById('btnMarkAllPresent')?.addEventListener('click', markAllPresent);

  // Import CSV button
  document.getElementById('btnImportCSV')?.addEventListener('click', showImportCSVModal);

  // Add proxy button
  document.getElementById('btnAddProxy')?.addEventListener('click', showAddProxyModal);

  // Proxy search
  document.getElementById('proxySearch')?.addEventListener('input', renderProxies);

  // Import proxies CSV button
  document.getElementById('btnImportProxiesCSV')?.addEventListener('click', showImportProxiesCSVModal);

  // Resolution search
  document.getElementById('resolutionSearch')?.addEventListener('input', renderResolutions);
  document.getElementById('btnAddResolution')?.addEventListener('click', () => {
    Shared.show(document.getElementById('addResolutionForm'), 'block');
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

  // Settings save
  document.getElementById('btnSaveSettings')?.addEventListener('click', saveGeneralSettings);

  // President change handler
  document.getElementById('settingPresident')?.addEventListener('change', savePresident);

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
        const inv = body.data.invitations || {};
        const eng = body.data.engagement || {};
        setText('invTotal', inv.total || 0);
        setText('invSent', inv.sent || 0);
        setText('invOpened', inv.opened || 0);
        setText('invBounced', inv.bounced || 0);

        const openRateEl = document.getElementById('invOpenRate');
        const engEl = document.getElementById('invEngagement');
        if (openRateEl) openRateEl.textContent = (eng.open_rate || 0) + '%';
        if (engEl && (inv.sent || inv.opened)) Shared.show(engEl, 'block');

        invitationsSentCount = (inv.sent || 0) + (inv.opened || 0) + (inv.accepted || 0);
      }
    } catch (err) {
      // Stats may not exist yet — ignore
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

        if (errors.length > 0) {
          setNotif('warning', `${sent} invitation${sent > 1 ? 's' : ''} envoyée${sent > 1 ? 's' : ''}, ${errors.length} erreur${errors.length > 1 ? 's' : ''}`);
        } else {
          setNotif('success', `${sent} invitation${sent > 1 ? 's' : ''} envoyée${sent > 1 ? 's' : ''}${skipped > 0 ? ` (${skipped} déjà envoyée${skipped > 1 ? 's' : ''})` : ''}`);
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

  // Quick member add
  document.getElementById('btnAddMember')?.addEventListener('click', addMemberQuick);

  // Quick all present
  document.getElementById('btnQuickAllPresent')?.addEventListener('click', async () => {
    const confirmed = await new Promise(resolve => {
      const m = createModal({
        id: 'quickAllPresentModal',
        title: 'Marquer tous présents',
        content: `<p>Marquer <strong>tous les membres</strong> comme présents ?</p>
          <div class="flex gap-3 justify-end mt-4">
            <button class="btn btn-secondary" data-action="cancel">Annuler</button>
            <button class="btn btn-primary" data-action="confirm">Confirmer</button>
          </div>`
      });
      m.querySelector('[data-action="cancel"]').addEventListener('click', () => { closeModal(m); resolve(false); });
      m.querySelector('[data-action="confirm"]').addEventListener('click', () => { closeModal(m); resolve(true); });
    });
    if (!confirmed) return;
    try {
      await api('/api/v1/attendances_bulk.php', { meeting_id: currentMeetingId, mode: 'present' });
      attendanceCache.forEach(m => m.mode = 'present');
      renderAttendance();
      updateQuickStats();
      loadStatusChecklist();
      checkLaunchReady();
      setNotif('success', 'Tous marqués présents');
    } catch (err) {
      setNotif('error', err.message);
    }
  });

  // Launch session button
  document.getElementById('btnLaunchSession')?.addEventListener('click', launchSession);

  // Device management button
  document.getElementById('btnManageDevices')?.addEventListener('click', showDeviceManagementModal);

  // Tab switch buttons
  document.querySelectorAll('[data-tab-switch]').forEach(btn => {
    btn.addEventListener('click', () => switchTab(btn.dataset.tabSwitch));
  });

  // Close session button
  document.getElementById('btnCloseSession')?.addEventListener('click', closeSession);

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
  document.getElementById('execBtnCloseVote')?.addEventListener('click', () => {
    if (currentOpenMotion) closeVote(currentOpenMotion.id);
  });

  // Setup view: manual vote search (P3-5)
  document.getElementById('manualVoteSearch')?.addEventListener('input', renderManualVoteList);

  // Exec view: manual vote search
  document.getElementById('execManualSearch')?.addEventListener('input', refreshExecManualVotes);

  // =========================================================================
  // ASSISTANT MODE — Step-by-step wizard for meeting preparation
  // =========================================================================

  let currentWizardStep = 1;
  let prepMode = 'assistant'; // 'assistant' | 'advanced'

  const assistantMode = document.getElementById('assistantMode');
  const advancedMode = document.getElementById('advancedMode');
  const prepModeSwitch = document.getElementById('prepModeSwitch');
  const btnModeAssistant = document.getElementById('btnModeAssistant');
  const btnModeAdvanced = document.getElementById('btnModeAdvanced');

  function setPrepMode(mode) {
    prepMode = mode;
    if (btnModeAssistant) {
      btnModeAssistant.classList.toggle('active', mode === 'assistant');
      btnModeAssistant.setAttribute('aria-pressed', String(mode === 'assistant'));
    }
    if (btnModeAdvanced) {
      btnModeAdvanced.classList.toggle('active', mode === 'advanced');
      btnModeAdvanced.setAttribute('aria-pressed', String(mode === 'advanced'));
    }
    if (assistantMode) assistantMode.hidden = mode !== 'assistant';
    if (advancedMode) advancedMode.hidden = mode !== 'advanced';

    // Show/hide tabs nav only in advanced mode
    if (mode === 'advanced' && currentMode === 'setup') {
      Shared.show(tabsNav, 'flex');
    } else if (mode === 'assistant') {
      Shared.hide(tabsNav);
    }

    // Refresh wizard content when switching to assistant
    if (mode === 'assistant') refreshWizardStep();
  }

  function goToWizardStep(step) {
    if (step < 1 || step > 5) return;
    currentWizardStep = step;

    // Update step buttons
    document.querySelectorAll('[data-wizard-step]').forEach(btn => {
      const s = parseInt(btn.dataset.wizardStep);
      btn.classList.toggle('active', s === step);
      btn.classList.toggle('done', s < step);
    });

    // Update connectors
    const connectors = document.querySelectorAll('.assistant-step-connector');
    connectors.forEach((c, i) => {
      c.classList.toggle('done', i < step - 1);
    });

    // Show/hide panels
    for (let i = 1; i <= 5; i++) {
      const panel = document.getElementById('wizardStep' + i);
      if (panel) panel.hidden = i !== step;
    }

    refreshWizardStep();
  }

  function refreshWizardStep() {
    if (prepMode !== 'assistant') return;

    switch (currentWizardStep) {
      case 1: renderWizMembers(); break;
      case 2: renderWizAttendance(); break;
      case 3: renderWizResolutions(); break;
      case 4: renderWizRules(); break;
      case 5: renderWizChecklist(); break;
    }
  }

  // --- Step 1: Members ---
  function renderWizMembers() {
    const title = document.getElementById('wizMembersTitle');
    if (title) title.textContent = membersCache.length + ' membre' + (membersCache.length > 1 ? 's' : '') + ' ajouté' + (membersCache.length > 1 ? 's' : '');

    const list = document.getElementById('wizMembersList');
    if (!list) return;

    if (membersCache.length === 0) {
      list.innerHTML = '<div class="text-center p-4 text-muted">Aucun membre pour le moment.</div>';
      return;
    }

    list.innerHTML = membersCache.map(m => `
      <div class="assistant-member-row" data-member-id="${m.id}">
        <span class="assistant-member-name">${escapeHtml(m.full_name || '—')}</span>
        <span class="text-sm text-muted assistant-member-email">${escapeHtml(m.email || '')}</span>
        <span class="assistant-member-power">${m.voting_power || 1} voix</span>
        <div class="assistant-member-actions">
          <button class="btn-icon-sm" title="Modifier" data-wiz-edit="${m.id}">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#icon-edit"></use></svg>
          </button>
          <button class="btn-icon-sm danger" title="Retirer" data-wiz-delete="${m.id}">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#icon-trash"></use></svg>
          </button>
        </div>
      </div>
    `).join('');

    // Bind edit/delete handlers
    list.querySelectorAll('[data-wiz-edit]').forEach(btn => {
      btn.addEventListener('click', () => wizEditMember(btn.dataset.wizEdit));
    });
    list.querySelectorAll('[data-wiz-delete]').forEach(btn => {
      btn.addEventListener('click', () => wizDeleteMember(btn.dataset.wizDelete));
    });
  }

  function wizEditMember(memberId) {
    const m = membersCache.find(x => x.id === memberId);
    if (!m) return;

    Shared.openModal({
      title: 'Modifier le membre',
      body: `
        <div class="form-group mb-3">
          <label class="form-label">Nom complet *</label>
          <input class="form-input" type="text" id="wizEditName" value="${escapeHtml(m.full_name || m.name || '')}" placeholder="Nom Prénom">
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Email <span class="text-muted text-sm">(facultatif)</span></label>
          <input class="form-input" type="email" id="wizEditEmail" value="${escapeHtml(m.email || '')}" placeholder="email@exemple.com">
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Nombre de voix</label>
          <input class="form-input" type="number" id="wizEditPower" value="${m.voting_power || 1}" min="1" step="1">
        </div>
      `,
      confirmText: 'Enregistrer',
      onConfirm: async function(modal) {
        const newName = modal.querySelector('#wizEditName').value.trim();
        const newEmail = modal.querySelector('#wizEditEmail').value.trim();
        const newPower = parseInt(modal.querySelector('#wizEditPower').value) || 1;

        if (!newName) { setNotif('error', 'Le nom est requis'); return false; }

        try {
          const { body } = await api('/api/v1/members.php', {
            member_id: memberId,
            full_name: newName,
            email: newEmail || null,
            voting_power: newPower,
            is_active: true
          }, 'PATCH');

          if (body?.ok) {
            setNotif('success', 'Membre modifié');
            await loadMembers();
            renderWizMembers();
            return true;
          } else {
            setNotif('error', getApiError(body, 'Erreur'));
            return false;
          }
        } catch (err) {
          setNotif('error', err.message);
          return false;
        }
      }
    });
  }

  async function wizDeleteMember(memberId) {
    const m = membersCache.find(x => x.id === memberId);
    if (!m) return;
    const name = m.full_name || m.name || 'ce membre';

    Shared.openModal({
      title: 'Retirer un membre',
      body: `<p>Retirer <strong>${escapeHtml(name)}</strong> de la liste des membres ?</p>
             <p class="text-sm text-muted">Cette action supprime le membre du registre.</p>`,
      confirmText: 'Retirer',
      danger: true,
      onConfirm: async function() {
        try {
          const { body } = await api('/api/v1/members.php', { member_id: memberId }, 'DELETE');
          if (body?.ok) {
            setNotif('success', name + ' retiré');
            await loadMembers();
            await loadAttendance();
            renderWizMembers();
            return true;
          } else {
            setNotif('error', getApiError(body, 'Erreur'));
            return false;
          }
        } catch (err) {
          setNotif('error', err.message);
          return false;
        }
      }
    });
  }

  async function wizAddMember() {
    const nameInput = document.getElementById('wizAddMemberName');
    const emailInput = document.getElementById('wizAddMemberEmail');
    const powerInput = document.getElementById('wizAddMemberPower');
    const name = nameInput?.value?.trim();
    if (!name) { setNotif('error', 'Le nom est requis'); nameInput?.focus(); return; }

    const voting_power = parseInt(powerInput?.value) || 1;

    try {
      await api('/api/v1/members.php', {
        action: 'create',
        full_name: name,
        email: emailInput?.value?.trim() || null,
        voting_power: voting_power
      });
      if (nameInput) nameInput.value = '';
      if (emailInput) emailInput.value = '';
      if (powerInput) powerInput.value = '1';
      nameInput?.focus();
      await loadMembers();
      renderWizMembers();
      setNotif('success', name + ' ajouté');
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  function wizHandleCsvDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    const zone = document.getElementById('wizImportZone');
    if (zone) zone.classList.remove('drag-over');
    const file = e.dataTransfer?.files?.[0];
    if (file) wizImportCsvFile(file);
  }

  async function wizImportCsvFile(file) {
    const text = await file.text();
    try {
      const { body } = await api('/api/v1/members_import_csv.php', {
        csv_content: text,
        meeting_id: currentMeetingId
      });
      if (body?.ok) {
        setNotif('success', 'Import réussi : ' + (body.data?.imported_count || 0) + ' membres');
        await loadMembers();
        await loadAttendance();
        renderWizMembers();
      } else {
        setNotif('error', body?.error || "Erreur d'import");
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // --- Step 2: Attendance ---
  function renderWizAttendance() {
    const grid = document.getElementById('wizAttendanceGrid');
    if (!grid) return;

    if (attendanceCache.length === 0) {
      grid.innerHTML = '<div class="text-center p-4 text-muted">Ajoutez des membres à l\'étape 1 d\'abord.</div>';
      return;
    }

    grid.innerHTML = attendanceCache.map(a => {
      const name = escapeHtml(a.full_name || '—');
      const isPresent = a.mode === 'present';
      const isRemote = a.mode === 'remote';
      const isAbsent = !a.mode || a.mode === 'absent';
      return `
        <div class="attendance-card" data-member-id="${a.member_id}">
          <span class="attendance-name">${name}</span>
          <div class="mode-btns">
            <button class="mode-btn present ${isPresent ? 'active' : ''}" data-mode="present" title="Présent">P</button>
            <button class="mode-btn remote ${isRemote ? 'active' : ''}" data-mode="remote" title="À distance">D</button>
            <button class="mode-btn absent ${isAbsent ? 'active' : ''}" data-mode="absent" title="Absent">A</button>
          </div>
        </div>
      `;
    }).join('');

    // Bind click handlers
    grid.querySelectorAll('.mode-btn').forEach(btn => {
      btn.addEventListener('click', async function() {
        const card = btn.closest('[data-member-id]');
        const memberId = card.dataset.memberId;
        const mode = btn.dataset.mode;

        // Optimistic update
        card.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        try {
          await api('/api/v1/attendances_upsert.php', {
            meeting_id: currentMeetingId,
            member_id: memberId,
            mode: mode
          });
          const entry = attendanceCache.find(a => a.member_id == memberId);
          if (entry) entry.mode = mode;
          updateWizAttendanceStats();
        } catch (err) {
          setNotif('error', err.message);
        }
      });
    });

    updateWizAttendanceStats();
  }

  function updateWizAttendanceStats() {
    const present = attendanceCache.filter(a => a.mode === 'present').length;
    const remote = attendanceCache.filter(a => a.mode === 'remote').length;
    const proxy = proxiesCache.length;
    const absent = attendanceCache.filter(a => !a.mode || a.mode === 'absent').length;
    setText('wizPresent', present);
    setText('wizRemote', remote);
    setText('wizProxy', proxy);
    setText('wizAbsent', absent);
  }

  async function wizAllPresent() {
    try {
      await api('/api/v1/attendances_bulk.php', { meeting_id: currentMeetingId, mode: 'present' });
      attendanceCache.forEach(m => m.mode = 'present');
      renderWizAttendance();
      setNotif('success', 'Tous marqués présents');
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  async function wizAllRemote() {
    try {
      await api('/api/v1/attendances_bulk.php', { meeting_id: currentMeetingId, mode: 'remote' });
      attendanceCache.forEach(m => m.mode = 'remote');
      renderWizAttendance();
      setNotif('success', 'Tous marqués à distance');
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // --- Step 3: Resolutions ---
  function renderWizResolutions() {
    const list = document.getElementById('wizResolutionsList');
    if (!list) return;

    if (motionsCache.length === 0) {
      list.innerHTML = '<div class="text-center p-4 text-muted">Aucune résolution pour le moment.</div>';
      return;
    }

    list.innerHTML = motionsCache.map((m, i) => `
      <div class="resolution-item">
        <div class="resolution-header" style="cursor:default;">
          <span class="resolution-number">#${i + 1}</span>
          <span class="resolution-title">${escapeHtml(m.title || '—')}</span>
          <span class="resolution-status pending">En attente</span>
        </div>
      </div>
    `).join('');
  }

  async function wizAddResolution() {
    const titleInput = document.getElementById('wizResolutionTitle');
    const descInput = document.getElementById('wizResolutionDesc');
    const title = titleInput?.value?.trim();
    if (!title) { setNotif('error', 'Le titre est requis'); titleInput?.focus(); return; }

    try {
      await api('/api/v1/motions.php', {
        meeting_id: currentMeetingId,
        title: title,
        description: descInput?.value?.trim() || null
      });
      if (titleInput) titleInput.value = '';
      if (descInput) descInput.value = '';
      titleInput?.focus();
      await loadResolutions();
      renderWizResolutions();
      setNotif('success', 'Résolution ajoutée');
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // --- Step 4: Rules ---
  function renderWizRules() {
    // Populate policy dropdowns from cache
    const qSelect = document.getElementById('wizQuorumPolicy');
    const vSelect = document.getElementById('wizVotePolicy');
    const qHint = document.getElementById('wizQuorumHint');
    const vHint = document.getElementById('wizVoteHint');

    if (qSelect) {
      if (policiesCache.quorum.length > 0) {
        const currentVal = qSelect.value;
        qSelect.innerHTML = '<option value="">— Aucun quorum —</option>' +
          policiesCache.quorum.map(p => `<option value="${p.id}">${escapeHtml(p.name || p.label || p.id)}</option>`).join('');
        if (currentMeeting?.quorum_policy_id) qSelect.value = currentMeeting.quorum_policy_id;
        else if (currentVal) qSelect.value = currentVal;
        qSelect.disabled = false;
        if (qHint) qHint.innerHTML = 'Si le quorum n\'est pas atteint, le vote ne peut pas avoir lieu.';
      } else {
        qSelect.innerHTML = '<option value="">— Aucun quorum —</option>';
        qSelect.disabled = false;
        if (qHint) qHint.innerHTML = 'Aucune politique de quorum créée. <a href="/admin.htmx.html#policies" target="_blank" class="text-primary">Créer une politique</a> (optionnel — vous pouvez continuer sans).';
      }
    }

    if (vSelect) {
      if (policiesCache.vote.length > 0) {
        const currentVal = vSelect.value;
        vSelect.innerHTML = '<option value="">— Majorité simple (par défaut) —</option>' +
          policiesCache.vote.map(p => `<option value="${p.id}">${escapeHtml(p.name || p.label || p.id)}</option>`).join('');
        if (currentMeeting?.vote_policy_id) vSelect.value = currentMeeting.vote_policy_id;
        else if (currentVal) vSelect.value = currentVal;
        vSelect.disabled = false;
        if (vHint) vHint.innerHTML = '';
      } else {
        vSelect.innerHTML = '<option value="">— Majorité simple (par défaut) —</option>';
        vSelect.disabled = false;
        if (vHint) vHint.innerHTML = 'Aucune politique de vote personnalisée. La majorité simple (50%+1) sera appliquée.';
      }
    }

    // Set convocation radio
    const conv = currentMeeting?.convocation_no || '1';
    const convRadio = document.querySelector('input[name="wizConvocation"][value="' + conv + '"]');
    if (convRadio) convRadio.checked = true;

    // Populate president from cache
    const wizPresident = document.getElementById('wizPresident');
    if (wizPresident && usersCache.length > 0) {
      wizPresident.items = JSON.stringify(usersCache.map(u => ({
        value: u.id,
        label: u.name || u.email || u.id
      })));
    }
  }

  // --- Step 4: Save rules on leaving step ---
  async function wizSaveRules() {
    if (!currentMeetingId) return;

    const quorumPolicyId = document.getElementById('wizQuorumPolicy')?.value || null;
    const votePolicyId = document.getElementById('wizVotePolicy')?.value || null;
    const convocationNo = parseInt(document.querySelector('input[name="wizConvocation"]:checked')?.value || '1') || 1;

    try {
      await Promise.all([
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

      // Update local state
      if (currentMeeting) {
        currentMeeting.quorum_policy_id = quorumPolicyId;
        currentMeeting.vote_policy_id = votePolicyId;
        currentMeeting.convocation_no = convocationNo;
      }

      // Also sync with settings tab dropdowns
      const settingQuorum = document.getElementById('settingQuorumPolicy');
      const settingVote = document.getElementById('settingVotePolicy');
      const settingConv = document.getElementById('settingConvocation');
      if (settingQuorum) settingQuorum.value = quorumPolicyId || '';
      if (settingVote) settingVote.value = votePolicyId || '';
      if (settingConv) settingConv.value = convocationNo;
    } catch (err) {
      console.warn('wizSaveRules:', err.message);
    }
  }

  // --- Step 5: Checklist ---
  function renderWizChecklist() {
    const container = document.getElementById('wizChecklist');
    if (!container) return;

    const hasMembers = membersCache.length > 0;
    const hasAttendance = attendanceCache.some(a => a.mode === 'present' || a.mode === 'remote');
    const hasMotions = motionsCache.length > 0;
    const presentCount = attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote').length;
    const remoteCount = attendanceCache.filter(a => a.mode === 'remote').length;
    const proxyCount = proxiesCache.length;
    const hasDate = !!(currentMeeting?.scheduled_at);
    const hasPresident = !!(currentMeeting?.president_user_id || currentMeeting?.president_name);
    const hasQuorumPolicy = !!(currentMeeting?.quorum_policy_id);
    const presidentName = currentMeeting?.president_name || '';

    // Format date for display
    let dateText = 'Date non renseignée';
    if (hasDate) {
      try {
        dateText = new Date(currentMeeting.scheduled_at).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
      } catch (e) { dateText = currentMeeting.scheduled_at; }
    }

    const checks = [
      { ok: hasDate, text: dateText, warn: !hasDate },
      { ok: hasMembers, text: membersCache.length + ' membre' + (membersCache.length > 1 ? 's' : '') + ' inscrit' + (membersCache.length > 1 ? 's' : '') },
      { ok: hasAttendance, text: presentCount + ' présent' + (presentCount > 1 ? 's' : '') + (remoteCount > 0 ? ' dont ' + remoteCount + ' à distance' : '') + (proxyCount > 0 ? ' · ' + proxyCount + ' procuration' + (proxyCount > 1 ? 's' : '') : '') },
      { ok: hasMotions, text: motionsCache.length + ' résolution' + (motionsCache.length > 1 ? 's' : '') + ' à voter' },
      { ok: hasQuorumPolicy, text: hasQuorumPolicy ? 'Quorum configuré' : 'Aucun quorum configuré', warn: !hasQuorumPolicy, optional: true },
      { ok: hasPresident, text: hasPresident ? 'Président : ' + escapeHtml(presidentName || 'assigné') : 'Aucun président désigné', warn: !hasPresident, optional: true },
      { ok: invitationsSentCount > 0, text: invitationsSentCount > 0 ? invitationsSentCount + ' invitation' + (invitationsSentCount > 1 ? 's' : '') + ' envoyée' + (invitationsSentCount > 1 ? 's' : '') : 'Invitations non envoyées', warn: invitationsSentCount === 0, optional: true }
    ];

    container.innerHTML = checks.map(c => {
      const icon = c.ok && !c.warn ? '&#10003;' : (c.warn ? '&#9888;' : '&#10007;');
      const cls = c.ok && !c.warn ? 'check-ok' : (c.warn ? 'check-warn' : 'check-fail');
      return `<div class="assistant-check-item ${cls}">
        <span class="assistant-check-icon">${icon}</span>
        <span>${escapeHtml(c.text)}${c.optional ? ' <span class="text-muted text-sm">(facultatif)</span>' : ''}</span>
      </div>`;
    }).join('');

    // Enable/disable launch button
    const launchBtn = document.getElementById('wizBtnLaunch');
    const canLaunch = hasMembers && hasAttendance && hasMotions && ['draft', 'scheduled', 'frozen'].includes(currentMeetingStatus);
    if (launchBtn) launchBtn.disabled = !canLaunch;
  }

  // --- Wizard navigation bindings ---
  document.getElementById('wizBtnAddMember')?.addEventListener('click', wizAddMember);
  document.getElementById('wizAddMemberName')?.addEventListener('keypress', e => { if (e.key === 'Enter') wizAddMember(); });
  document.getElementById('wizBtnAllPresent')?.addEventListener('click', wizAllPresent);
  document.getElementById('wizBtnAllRemote')?.addEventListener('click', wizAllRemote);
  document.getElementById('wizBtnAddResolution')?.addEventListener('click', wizAddResolution);
  document.getElementById('wizResolutionTitle')?.addEventListener('keypress', e => { if (e.key === 'Enter') wizAddResolution(); });

  // CSV import
  const wizCsvFile = document.getElementById('wizCsvFile');
  if (wizCsvFile) {
    wizCsvFile.addEventListener('change', e => {
      if (e.target.files[0]) wizImportCsvFile(e.target.files[0]);
    });
  }
  const wizImportZone = document.getElementById('wizImportZone');
  if (wizImportZone) {
    wizImportZone.addEventListener('dragover', e => { e.preventDefault(); wizImportZone.classList.add('drag-over'); });
    wizImportZone.addEventListener('dragleave', () => wizImportZone.classList.remove('drag-over'));
    wizImportZone.addEventListener('drop', wizHandleCsvDrop);
  }

  // Step navigation
  document.getElementById('wizBtnNext1')?.addEventListener('click', () => goToWizardStep(2));
  document.getElementById('wizBtnPrev2')?.addEventListener('click', () => goToWizardStep(1));
  document.getElementById('wizBtnNext2')?.addEventListener('click', () => goToWizardStep(3));
  document.getElementById('wizBtnPrev3')?.addEventListener('click', () => goToWizardStep(2));
  document.getElementById('wizBtnNext3')?.addEventListener('click', () => goToWizardStep(4));
  document.getElementById('wizBtnPrev4')?.addEventListener('click', () => goToWizardStep(3));
  document.getElementById('wizBtnNext4')?.addEventListener('click', async () => { await wizSaveRules(); goToWizardStep(5); });
  document.getElementById('wizBtnPrev5')?.addEventListener('click', () => goToWizardStep(4));

  // Step progress clicks
  document.querySelectorAll('[data-wizard-step]').forEach(btn => {
    btn.addEventListener('click', () => goToWizardStep(parseInt(btn.dataset.wizardStep)));
  });

  // Launch button
  document.getElementById('wizBtnLaunch')?.addEventListener('click', launchSession);

  // Send invitations from wizard
  document.getElementById('wizBtnSendInvitations')?.addEventListener('click', () => {
    // Switch to advanced mode and show invitations
    setPrepMode('advanced');
    switchTab('parametres');
    const sendBtn = document.getElementById('btnSendInvitations');
    if (sendBtn) sendBtn.click();
  });

  // Prep mode toggle
  btnModeAssistant?.addEventListener('click', () => setPrepMode('assistant'));
  btnModeAdvanced?.addEventListener('click', () => setPrepMode('advanced'));

  // Expose switchTab globally for wizard navigation
  window.switchTab = switchTab;

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
    if (sessionTimerInterval) clearInterval(sessionTimerInterval);
  });

  // Start polling after initial load
  schedulePoll(POLL_SLOW);

})();
