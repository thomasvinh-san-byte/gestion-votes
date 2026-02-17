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
  let currentMode = 'setup'; // 'setup' | 'exec'

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

    // Close on backdrop click
    if (closeOnBackdrop) {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.remove();
      });
    }

    // Close on Escape key
    const handleEscape = (e) => {
      if (e.key === 'Escape' && document.body.contains(modal)) {
        modal.remove();
        document.removeEventListener('keydown', handleEscape);
      }
    };
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
   * Remove modal from DOM
   * @param {HTMLElement|string} modal - Modal element or ID
   */
  function closeModal(modal) {
    const el = typeof modal === 'string' ? document.getElementById(modal) : modal;
    if (el) el.remove();
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
    setMode(initialMode);

    // If in setup mode, show the right tab
    if (initialMode === 'setup') {
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
      loadProxies()
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
      console.error('Members error:', err);
    }
  }

  function renderMembersCard() {
    document.getElementById('membersCount').textContent = membersCache.length;

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
    const modal = document.createElement('div');
    modal.className = 'modal-backdrop';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'addMemberModalTitle');
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:var(--z-modal-backdrop, 400);display:flex;align-items:center;justify-content:center;opacity:1;visibility:visible;';

    modal.innerHTML = `
      <div style="background:var(--color-surface);border-radius:12px;padding:1.5rem;max-width:400px;width:90%;" role="document">
        <h3 id="addMemberModalTitle" style="margin:0 0 1rem;">Ajouter un membre</h3>
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
      </div>
    `;

    document.body.appendChild(modal);

    const btnCancel = document.getElementById('btnCancelMember');
    const btnConfirm = document.getElementById('btnConfirmMember');

    btnCancel.onclick = () => modal.remove();
    btnConfirm.onclick = async () => {
      const name = document.getElementById('newMemberName').value.trim();
      const email = document.getElementById('newMemberEmail').value.trim();

      if (!name) {
        setNotif('error', 'Le nom est requis');
        return;
      }

      // Disable buttons during async operation
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
        modal.remove();
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

    document.getElementById('quickPresent').textContent = present;
    document.getElementById('quickRemote').textContent = remote;
    const quickProxyEl = document.getElementById('quickProxy');
    if (quickProxyEl) quickProxyEl.textContent = proxyCount;
    document.getElementById('quickAbsent').textContent = absent;
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

  async function launchSession() {
    if (!confirm('Lancer la séance et ouvrir les votes ?')) return;

    try {
      // Transition through required states: draft → scheduled → frozen → live
      const transitions = [];
      if (currentMeetingStatus === 'draft') transitions.push('scheduled', 'frozen', 'live');
      else if (currentMeetingStatus === 'scheduled') transitions.push('frozen', 'live');
      else if (currentMeetingStatus === 'frozen') transitions.push('live');
      else transitions.push('live');

      for (const status of transitions) {
        const { body } = await api('/api/v1/meeting_transition.php', {
          meeting_id: currentMeetingId,
          to_status: status
        });
        if (!body?.ok) {
          const _sl = { draft: 'brouillon', scheduled: 'planifiée', frozen: 'gelée', live: 'en cours', closed: 'clôturée', validated: 'validée', archived: 'archivée' };
          setNotif('error', body?.error || `Erreur passage vers ${_sl[status] || status}`);
          return;
        }
      }

      setNotif('success', 'Séance lancée !');
      await loadMeetingContext(currentMeetingId);
      setMode('exec');
      announce('Séance lancée — mode exécution activé.');
    } catch (err) {
      setNotif('error', err.message);
    }
  }

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
    const meetingType = currentMeeting.meeting_type || 'ordinary';
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
      console.error('Policies error:', err);
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
      console.error('Roles error:', err);
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
    if (!userId || !confirm('Retirer cet assesseur ?')) return;
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
      document.getElementById('dashPresentCount').textContent = d.attendance?.present_count ?? '-';
      document.getElementById('dashEligibleCount').textContent = d.attendance?.eligible_count ?? '-';
      document.getElementById('dashProxyCount').textContent = d.proxies?.count ?? 0;
      document.getElementById('dashOpenMotions').textContent = d.openable_motions?.length ?? 0;

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
      console.error('Dashboard error:', err);
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
      document.getElementById('devOnline').textContent = counts.online ?? 0;
      document.getElementById('devStale').textContent = counts.stale ?? 0;
      document.getElementById('devOffline').textContent = counts.offline ?? 0;
      document.getElementById('devBlocked').textContent = counts.blocked ?? 0;

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
      console.error('Devices error:', err);
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
              <div class="text-xs text-muted">${dev.role || 'inconnu'} • ${dev.ip || '—'} ${battery}</div>
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

  async function blockDevice(deviceId, modal) {
    const reason = prompt('Raison du blocage (optionnel):') || 'Bloqué par opérateur';
    try {
      await api('/api/v1/device_block.php', { device_id: deviceId, reason });
      setNotif('success', 'Appareil bloqué');
      loadDevicesModal(modal);
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
      console.error('Checklist error:', err);
    }
  }

  // =========================================================================
  // SAVE SETTINGS
  // =========================================================================

  async function saveGeneralSettings() {
    const title = document.getElementById('settingTitle').value.trim();
    const scheduledAt = document.getElementById('settingDate').value;
    const meetingType = document.querySelector('input[name="meetingType"]:checked')?.value || 'ordinary';

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
      console.error('Attendance error:', err);
    }
  }

  function renderAttendance() {
    const present = attendanceCache.filter(a => a.mode === 'present').length;
    const remote = attendanceCache.filter(a => a.mode === 'remote').length;
    const proxyCount = proxiesCache.filter(p => !p.revoked_at).length;
    const absent = attendanceCache.filter(a => !a.mode || a.mode === 'absent').length;

    document.getElementById('presStatPresent').textContent = present;
    document.getElementById('presStatRemote').textContent = remote;
    const proxyStatEl = document.getElementById('presStatProxy');
    if (proxyStatEl) proxyStatEl.textContent = proxyCount;
    document.getElementById('presStatAbsent').textContent = absent;
    document.getElementById('tabCountPresences').textContent = present + remote + proxyCount;

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
    try {
      const { body } = await api('/api/v1/attendances_upsert.php', {
        meeting_id: currentMeetingId,
        member_id: memberId,
        mode: mode
      });
      if (body?.ok === true) {
        const m = attendanceCache.find(a => String(a.member_id) === String(memberId));
        if (m) m.mode = mode;
        renderAttendance();
        loadStatusChecklist();
        updateQuickStats();
        checkLaunchReady();
      } else {
        setNotif('error', getApiError(body, 'Erreur de mise à jour'));
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  async function markAllPresent() {
    if (!confirm('Marquer tous présents ?')) return;
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
          L'email et le poids sont optionnels.
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

        const resp = await fetch('/api/v1/members_import_csv.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
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
      console.error('Proxies error:', err);
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
    if (!confirm('Révoquer cette procuration ?')) return;

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

        const resp = await fetch('/api/v1/proxies_import_csv.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
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
      console.error('Speech queue error:', err);
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
    if (!confirm('Vider l\'historique des prises de parole ?')) return;
    try {
      await api('/api/v1/speech_clear.php', { meeting_id: currentMeetingId });
      setNotif('success', 'Historique vidé');
      loadSpeechQueue();
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  function showAddToQueueModal() {
    // Show modal to select a member to add to the queue
    const presentMembers = attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote');
    const alreadyInQueue = new Set(speechQueueCache.map(s => s.member_id));
    const available = presentMembers.filter(m => !alreadyInQueue.has(m.member_id) && (!currentSpeakerCache || currentSpeakerCache.member_id !== m.member_id));

    const modal = document.createElement('div');
    modal.className = 'modal-backdrop';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:var(--z-modal-backdrop, 400);display:flex;align-items:center;justify-content:center;opacity:1;visibility:visible;';

    modal.innerHTML = `
      <div style="background:var(--color-surface);border-radius:12px;padding:1.5rem;max-width:400px;width:90%;max-height:80vh;overflow:auto;">
        <h3 style="margin:0 0 1rem;">${icon('mic', 'icon-sm icon-text')} Ajouter à la file</h3>
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
      </div>
    `;

    document.body.appendChild(modal);
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });
    document.getElementById('btnCancelAddSpeech').onclick = () => modal.remove();

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
          modal.remove();
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
      console.error('Resolutions error:', err);
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
        voteActions = `<button class="btn btn-sm btn-warning btn-close-vote" data-motion-id="${m.id}">${icon('square', 'icon-sm icon-text')}Clôturer</button>`;
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
        if (!confirm('Supprimer cette résolution ?')) return;
        try {
          await api('/api/v1/motion_delete.php', { motion_id: btn.dataset.motionId, meeting_id: currentMeetingId });
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

  async function loadBallots(motionId) {
    try {
      const { body } = await api(`/api/v1/ballots.php?motion_id=${motionId}`);
      const ballots = body?.data?.ballots || body?.ballots || [];
      ballotsCache = {};
      let forCount = 0, againstCount = 0, abstainCount = 0;
      ballots.forEach(b => {
        ballotsCache[b.member_id] = b.value;
        if (b.value === 'for') forCount++;
        else if (b.value === 'against') againstCount++;
        else if (b.value === 'abstain') abstainCount++;
      });
      document.getElementById('liveVoteFor').textContent = forCount;
      document.getElementById('liveVoteAgainst').textContent = againstCount;
      document.getElementById('liveVoteAbstain').textContent = abstainCount;
    } catch (err) {
      console.error('Ballots error:', err);
    }
  }

  function renderManualVoteList() {
    const voters = attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote');
    const list = document.getElementById('manualVoteList');

    // Allow vote correction - buttons are never disabled, but show current vote
    list.innerHTML = voters.map(v => {
      const vote = ballotsCache[v.member_id];
      const hasVoted = !!vote;
      return `
        <div class="attendance-card ${hasVoted ? 'present' : ''}" data-member-id="${v.member_id}">
          <span class="attendance-name">${escapeHtml(v.full_name || '—')}</span>
          <div class="attendance-mode-btns">
            <button class="mode-btn for ${vote === 'for' ? 'active' : ''}" data-vote="for" title="Pour">${icon('check', 'icon-sm')}</button>
            <button class="mode-btn against ${vote === 'against' ? 'active' : ''}" data-vote="against" title="Contre">${icon('x', 'icon-sm')}</button>
            <button class="mode-btn abstain ${vote === 'abstain' ? 'active' : ''}" data-vote="abstain" title="Abstention">${icon('minus', 'icon-sm')}</button>
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

        // Confirm if correcting existing vote
        const _vl = { for: 'Pour', against: 'Contre', abstain: 'Abstention' };
        if (currentVote && !confirm(`Modifier le vote de "${_vl[currentVote] || currentVote}" vers "${_vl[newVote] || newVote}" ?`)) {
          return;
        }

        await castManualVote(memberId, newVote);
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

    try {
      const { body } = await api('/api/v1/manual_vote.php', {
        meeting_id: currentMeetingId,
        motion_id: currentOpenMotion.id,
        member_id: memberId,
        vote: vote,
        justification: 'Vote opérateur manuel'
      });

      if (body?.ok === true) {
        ballotsCache[memberId] = vote;
        await loadBallots(currentOpenMotion.id);
        renderManualVoteList();
        setNotif('success', 'Vote enregistré');
      } else {
        setNotif('error', getApiError(body, 'Erreur lors du vote'));
      }
    } catch (err) {
      setNotif('error', err.message);
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
    const voters = attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote');

    if (voters.length === 0) {
      setNotif('error', 'Aucun votant présent');
      return;
    }

    if (!confirm(`Enregistrer "${voteLabels[voteType]}" pour ${voters.length} votant(s) ?`)) {
      return;
    }

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

  let _openingVote = false;

  async function openVote(motionId) {
    if (_openingVote) return;
    _openingVote = true;

    // Disable open-vote buttons and show spinner
    const openBtns = document.querySelectorAll(`.btn-open-vote[data-motion-id="${motionId}"]`);
    openBtns.forEach(btn => {
      btn.disabled = true;
      btn.dataset.origHtml = btn.innerHTML;
      btn.innerHTML = '<span class="spinner spinner-sm"></span> Ouverture…';
    });

    try {
      const openResult = await api('/api/v1/motions_open.php', { meeting_id: currentMeetingId, motion_id: motionId });

      if (!openResult.body?.ok) {
        // Prefer detail message over error code for user-friendly display
        const errorMsg = getApiError(openResult.body, 'Erreur ouverture vote');
        setNotif('error', errorMsg);
        return;
      }

      setNotif('success', 'Vote ouvert');

      // Must await loadResolutions so currentOpenMotion is set before switching tabs
      await loadResolutions();

      if (currentMode === 'exec') {
        await loadBallots(currentOpenMotion.id);
        refreshExecView();
      } else {
        switchTab('vote');
        await loadVoteTab();
      }
    } catch (err) {
      setNotif('error', err.message);
    } finally {
      _openingVote = false;
      openBtns.forEach(btn => {
        btn.disabled = false;
        btn.innerHTML = btn.dataset.origHtml || btn.innerHTML;
      });
    }
  }

  let _closingVote = false;

  async function closeVote(motionId) {
    if (_closingVote) return; // Guard against double-click
    if (!confirm('Clôturer ce vote ?')) return;

    _closingVote = true;
    // Disable all close-vote buttons during the operation and show spinner
    const closeBtns = document.querySelectorAll('.btn-close-vote, #btnCloseVote, #execBtnCloseVote');
    closeBtns.forEach(b => {
      b.disabled = true;
      b.dataset.origHtml = b.innerHTML;
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
    } catch (err) {
      setNotif('error', err.message);
    } finally {
      _closingVote = false;
      closeBtns.forEach(b => {
        b.disabled = false;
        b.innerHTML = b.dataset.origHtml || b.innerHTML;
      });
    }
  }

  // =========================================================================
  // TAB: RÉSULTATS
  // =========================================================================

  async function loadResults() {
    const closed = motionsCache.filter(m => m.closed_at);
    const adopted = closed.filter(m => (m.votes_for || 0) > (m.votes_against || 0)).length;
    const rejected = closed.length - adopted;

    document.getElementById('resultAdopted').textContent = adopted;
    document.getElementById('resultRejected').textContent = rejected;
    document.getElementById('resultTotal').textContent = motionsCache.length;

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
    if (pending.length > 0) {
      if (!confirm(`Attention: ${pending.length} résolution(s) n'ont pas été votées. Clôturer quand même ?`)) {
        return;
      }
    } else if (!confirm('Clôturer la séance ? Cette action est irréversible.')) {
      return;
    }

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
    if (!confirm(`Changer l'état vers "${statusLabel}" ?`)) return;
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
        loadMeetingContext(currentMeetingId);
        loadMeetings();
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
    currentMode = mode;

    // Update button states
    if (btnModeSetup) {
      btnModeSetup.classList.toggle('active', mode === 'setup');
      btnModeSetup.setAttribute('aria-pressed', String(mode === 'setup'));
    }
    if (btnModeExec) {
      btnModeExec.classList.toggle('active', mode === 'exec');
      btnModeExec.setAttribute('aria-pressed', String(mode === 'exec'));
    }

    // Toggle views
    if (mode === 'setup') {
      if (viewSetup) viewSetup.hidden = false;
      if (viewExec) viewExec.hidden = true;
      Shared.show(tabsNav, 'flex');
    } else {
      if (viewSetup) viewSetup.hidden = true;
      if (viewExec) viewExec.hidden = false;
      Shared.hide(tabsNav);
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

    // Quorum check (when live)
    if (currentMeetingStatus === 'live' && currentMeeting && currentMeeting.quorum_policy_id) {
      const present = attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote').length;
      const proxyActive = proxiesCache.filter(p => !p.revoked_at).length;
      const total = present + proxyActive;
      if (membersCache.length > 0 && total < Math.ceil(membersCache.length / 2)) {
        alerts.push({
          title: 'Quorum potentiellement non atteint',
          message: total + ' votants / ' + membersCache.length + ' membres.',
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
      const required = Math.ceil(totalMembers / 2); // default quorum
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

    if (currentOpenMotion) {
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
      if (titleEl) titleEl.textContent = 'Aucun vote en cours';
      if (liveBadge) Shared.hide(liveBadge);
      if (forEl) forEl.textContent = '—';
      if (againstEl) againstEl.textContent = '—';
      if (abstainEl) abstainEl.textContent = '—';
      if (btnClose) { btnClose.disabled = true; Shared.hide(btnClose); }
    }
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

  // Quick member add
  document.getElementById('btnAddMember')?.addEventListener('click', addMemberQuick);

  // Quick all present
  document.getElementById('btnQuickAllPresent')?.addEventListener('click', async () => {
    if (!confirm('Marquer tous les membres comme présents ?')) return;
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

  // Exec view: manual vote search
  document.getElementById('execManualSearch')?.addEventListener('input', refreshExecManualVotes);

  // Expose switchTab globally for wizard navigation
  window.switchTab = switchTab;

  initTabs();
  startClock();
  loadMeetings();

  // Auto-refresh - adaptive polling
  const POLL_FAST = 5000;  // 5s when vote is active
  const POLL_SLOW = 15000; // 15s otherwise (reduced from 6s to limit server load)

  async function autoPoll() {
    if (!currentMeetingId || document.hidden) {
      setTimeout(autoPoll, POLL_SLOW);
      return;
    }

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
      // In exec mode, only refresh devices (for counts)
      loadDevices();
    }

    const isVoteActive = !!currentOpenMotion;
    const currentMotionId = currentOpenMotion?.id || null;

    // Detect if a new vote was opened (not by us, e.g. from another tab/device)
    if (isVoteActive && currentMotionId !== previousOpenMotionId) {
      setNotif('info', `Vote ouvert: ${currentOpenMotion.title}`);
      if (currentMode === 'exec') {
        await loadBallots(currentOpenMotion.id);
        refreshExecView();
      } else {
        switchTab('vote');
      }
    }

    previousOpenMotionId = currentMotionId;

    // If vote is active, refresh ballot counts (once — no duplicate)
    if (isVoteActive && currentOpenMotion) {
      await loadBallots(currentOpenMotion.id);
      // Update vote tab display without calling loadBallots again
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
      // No active vote but on vote tab — refresh quick-open list
      loadVoteTab();
    }

    // Refresh bimodal UI
    renderConformityChecklist();
    refreshAlerts();
    if (currentMode === 'exec') refreshExecView();

    // Schedule next poll
    const interval = isVoteActive ? POLL_FAST : POLL_SLOW;
    setTimeout(autoPoll, interval);
  }

  // Refresh immediately when tab becomes visible again
  document.addEventListener('visibilitychange', function() {
    if (!document.hidden && currentMeetingId) {
      autoPoll();
    }
  });

  // Start polling after initial load
  setTimeout(autoPoll, POLL_SLOW);

})();
