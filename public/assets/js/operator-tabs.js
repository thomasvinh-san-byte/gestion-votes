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
  let ballotsCache = {};
  let usersCache = [];
  let policiesCache = { quorum: [], vote: [] };

  // Transitions
  const TRANSITIONS = {
    draft: [{ to: 'scheduled', label: 'Planifier', icon: '📅' }],
    scheduled: [
      { to: 'frozen', label: 'Geler', icon: '🧊' },
      { to: 'draft', label: 'Retour brouillon', icon: '↩️' }
    ],
    frozen: [
      { to: 'live', label: 'Ouvrir la séance', icon: '▶️' },
      { to: 'scheduled', label: 'Dégeler', icon: '↩️' }
    ],
    live: [{ to: 'closed', label: 'Clôturer', icon: '⏹️' }],
    closed: [{ to: 'validated', label: 'Valider', icon: '✅' }],
    validated: [{ to: 'archived', label: 'Archiver', icon: '📦' }],
    archived: []
  };

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

  function switchTab(tabId) {
    tabButtons.forEach(btn => {
      btn.classList.toggle('active', btn.dataset.tab === tabId);
    });
    tabContents.forEach(content => {
      content.classList.toggle('active', content.id === `tab-${tabId}`);
    });

    // Reload data when switching to certain tabs
    if (currentMeetingId) {
      if (tabId === 'presences') loadAttendance();
      if (tabId === 'resolutions') loadResolutions();
      if (tabId === 'vote') loadVoteTab();
      if (tabId === 'resultats') loadResults();
    }
  }

  // =========================================================================
  // MEETING SELECTION
  // =========================================================================

  async function loadMeetings() {
    try {
      const { body } = await api('/api/v1/meetings_index.php');
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
    noMeetingState.style.display = 'flex';
    tabsNav.style.display = 'none';
    tabContents.forEach(c => c.classList.remove('active'));
    currentMeetingId = null;
    currentMeeting = null;
  }

  function showMeetingContent() {
    noMeetingState.style.display = 'none';
    tabsNav.style.display = 'flex';
    switchTab('parametres');
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
      loadDevices()
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
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100;display:flex;align-items:center;justify-content:center;';

    modal.innerHTML = `
      <div style="background:var(--color-surface);border-radius:12px;padding:1.5rem;max-width:400px;width:90%;">
        <h3 style="margin:0 0 1rem;">Ajouter un membre</h3>
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

    document.getElementById('btnCancelMember').onclick = () => modal.remove();
    document.getElementById('btnConfirmMember').onclick = async () => {
      const name = document.getElementById('newMemberName').value.trim();
      const email = document.getElementById('newMemberEmail').value.trim();

      if (!name) {
        setNotif('error', 'Le nom est requis');
        return;
      }

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
      }
    };
  }

  // =========================================================================
  // QUICK STATS & LAUNCH BANNER
  // =========================================================================

  function updateQuickStats() {
    const present = attendanceCache.filter(a => a.mode === 'present').length;
    const remote = attendanceCache.filter(a => a.mode === 'remote').length;
    const excused = attendanceCache.filter(a => a.mode === 'excused').length;
    const absent = attendanceCache.filter(a => !a.mode || a.mode === 'absent').length;

    document.getElementById('quickPresent').textContent = present;
    document.getElementById('quickRemote').textContent = remote;
    document.getElementById('quickExcused').textContent = excused;
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
      banner.style.display = 'block';
    } else {
      banner.style.display = 'none';
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
          setNotif('error', body?.error || `Erreur passage vers ${status}`);
          return;
        }
      }

      setNotif('success', 'Séance lancée !');
      switchTab('vote');
      loadMeetingContext(currentMeetingId);
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

      // President select
      const presSelect = document.getElementById('settingPresident');
      presSelect.innerHTML = '<option value="">— Non assigné —</option>';
      usersCache.forEach(u => {
        const selected = president?.user_id === u.id ? 'selected' : '';
        presSelect.innerHTML += `<option value="${u.id}" ${selected}>${escapeHtml(u.name || u.email)}</option>`;
      });

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
          <button class="btn btn-sm btn-ghost text-danger" onclick="removeAssessor('${a.user_id}')" title="Retirer">✕</button>
        </div>
      `;
    }).join('');
  }

  window.removeAssessor = async function(userId) {
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
    }
  };

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
      if (card) card.style.display = 'block';

      // Attendance
      document.getElementById('dashPresentCount').textContent = d.attendance?.present_count ?? '-';
      document.getElementById('dashEligibleCount').textContent = d.attendance?.eligible_count ?? '-';
      document.getElementById('dashProxyCount').textContent = d.proxies?.count ?? 0;
      document.getElementById('dashOpenMotions').textContent = d.openable_motions?.length ?? 0;

      // Current motion
      const motionDiv = document.getElementById('dashCurrentMotion');
      if (d.current_motion) {
        motionDiv.style.display = 'block';
        document.getElementById('dashMotionTitle').textContent = d.current_motion.title || '—';
        const votes = d.current_motion_votes || {};
        document.getElementById('dashVoteFor').textContent = votes.weight_for ?? 0;
        document.getElementById('dashVoteAgainst').textContent = votes.weight_against ?? 0;
        document.getElementById('dashVoteAbstain').textContent = votes.weight_abstain ?? 0;
      } else {
        motionDiv.style.display = 'none';
      }

      // Ready to sign
      const ready = d.ready_to_sign || {};
      document.getElementById('dashReadySign').style.display = ready.can ? 'block' : 'none';
      document.getElementById('dashNotReadySign').style.display = ready.can ? 'none' : 'block';
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
      if (card) card.style.display = 'block';

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
          const statusIcon = dev.status === 'online' ? '🟢' : dev.status === 'stale' ? '🟡' : '⚫';
          const blocked = dev.is_blocked ? ' 🚫' : '';
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
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100;display:flex;align-items:center;justify-content:center;';

    modal.innerHTML = `
      <div style="background:var(--color-surface);border-radius:12px;padding:1.5rem;max-width:600px;width:90%;max-height:80vh;overflow:auto;">
        <div class="flex items-center justify-between mb-4">
          <h3 style="margin:0;">📱 Gestion des appareils</h3>
          <button class="btn btn-sm btn-ghost" id="btnCloseDevices">✕</button>
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
        const statusIcon = dev.status === 'online' ? '🟢' : dev.status === 'stale' ? '🟡' : '⚫';
        const blocked = dev.is_blocked;
        const battery = dev.battery_pct !== null ? `🔋 ${dev.battery_pct}%${dev.is_charging ? '⚡' : ''}` : '';

        return `
          <div class="flex items-center justify-between p-3 border-b" style="border-color:var(--color-border);">
            <div>
              <div class="font-medium">${statusIcon} ${escapeHtml(dev.device_id.slice(0, 12))}...</div>
              <div class="text-xs text-muted">${dev.role || 'inconnu'} • ${dev.ip || '—'} ${battery}</div>
              ${blocked ? `<div class="text-xs text-danger">🚫 Bloqué: ${escapeHtml(dev.block_reason || '')}</div>` : ''}
            </div>
            <div class="flex gap-1">
              ${blocked
                ? `<button class="btn btn-xs btn-success btn-unblock" data-device="${dev.device_id}">Débloquer</button>`
                : `<button class="btn btn-xs btn-warning btn-block" data-device="${dev.device_id}">Bloquer</button>`
              }
              <button class="btn btn-xs btn-secondary btn-kick" data-device="${dev.device_id}">Kick</button>
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
        const icon = c.done ? '✓' : '○';
        const cls = c.done ? 'color: var(--color-success)' : 'color: var(--color-text-muted)';
        const style = c.optional ? 'opacity:0.7;font-style:italic;' : '';
        return `<div class="flex items-center gap-2" style="${cls};${style}"><span>${icon}</span> ${c.text}</div>`;
      }).join('');

      // Transition buttons
      const transitions = TRANSITIONS[currentMeetingStatus] || [];
      const actions = document.getElementById('statusActions');
      actions.innerHTML = transitions.map(t => {
        const btnClass = t.to === 'live' ? 'btn-primary' : 'btn-secondary';
        return `<button class="btn ${btnClass}" data-transition="${t.to}">${t.icon} ${t.label}</button>`;
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
    const presidentId = document.getElementById('settingPresident').value;
    const presSelect = document.getElementById('settingPresident');
    const presidentName = presSelect.options[presSelect.selectedIndex]?.text || '';

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
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100;display:flex;align-items:center;justify-content:center;';

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
    const excused = attendanceCache.filter(a => a.mode === 'excused').length;
    const absent = attendanceCache.filter(a => !a.mode || a.mode === 'absent').length;

    document.getElementById('presStatPresent').textContent = present;
    document.getElementById('presStatRemote').textContent = remote;
    document.getElementById('presStatExcused').textContent = excused;
    document.getElementById('presStatAbsent').textContent = absent;
    document.getElementById('tabCountPresences').textContent = present + remote;

    const searchTerm = (document.getElementById('presenceSearch')?.value || '').toLowerCase();
    let filtered = attendanceCache;
    if (searchTerm) {
      filtered = attendanceCache.filter(a => (a.full_name || '').toLowerCase().includes(searchTerm));
    }

    // Sort
    filtered = [...filtered].sort((a, b) => {
      const orderA = a.mode === 'present' ? 0 : a.mode === 'remote' ? 1 : a.mode === 'excused' ? 2 : 3;
      const orderB = b.mode === 'present' ? 0 : b.mode === 'remote' ? 1 : b.mode === 'excused' ? 2 : 3;
      if (orderA !== orderB) return orderA - orderB;
      return (a.full_name || '').localeCompare(b.full_name || '');
    });

    const grid = document.getElementById('attendanceGrid');
    const isLocked = ['validated', 'archived'].includes(currentMeetingStatus);

    grid.innerHTML = filtered.map(m => {
      const mode = m.mode || 'absent';
      const disabled = isLocked ? 'disabled' : '';
      return `
        <div class="attendance-card ${mode}" data-member-id="${m.member_id}">
          <span class="attendance-name">${escapeHtml(m.full_name || '—')}</span>
          <div class="attendance-mode-btns">
            <button class="mode-btn present ${mode === 'present' ? 'active' : ''}" data-mode="present" ${disabled}>P</button>
            <button class="mode-btn remote ${mode === 'remote' ? 'active' : ''}" data-mode="remote" ${disabled}>D</button>
            <button class="mode-btn excused ${mode === 'excused' ? 'active' : ''}" data-mode="excused" ${disabled}>E</button>
            <button class="mode-btn absent ${mode === 'absent' ? 'active' : ''}" data-mode="absent" ${disabled}>A</button>
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
        setNotif('error', body?.error || 'Erreur de mise à jour');
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
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100;display:flex;align-items:center;justify-content:center;';

    modal.innerHTML = `
      <div style="background:var(--color-surface);border-radius:12px;padding:1.5rem;max-width:500px;width:90%;">
        <h3 style="margin:0 0 1rem;">📥 Importer des membres (CSV)</h3>
        <p class="text-muted text-sm mb-3">
          Format attendu: <code>nom,email,poids</code> (une ligne par membre).<br>
          L'email et le poids sont optionnels.
        </p>
        <div class="form-group mb-3">
          <label class="form-label">Fichier CSV</label>
          <input type="file" class="form-input" id="csvFileInput" accept=".csv,.txt">
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Ou coller le contenu</label>
          <textarea class="form-input" id="csvTextInput" rows="5" placeholder="Jean Dupont,jean@exemple.com,1\nMarie Martin,,2"></textarea>
        </div>
        <div class="flex gap-2 justify-end">
          <button class="btn btn-secondary" id="btnCancelImport">Annuler</button>
          <button class="btn btn-primary" id="btnConfirmImport">Importer</button>
        </div>
      </div>
    `;

    document.body.appendChild(modal);

    document.getElementById('btnCancelImport').onclick = () => modal.remove();
    document.getElementById('btnConfirmImport').onclick = async () => {
      const fileInput = document.getElementById('csvFileInput');
      const textInput = document.getElementById('csvTextInput');
      let csvContent = textInput.value.trim();

      // Read file if selected
      if (fileInput.files.length > 0) {
        csvContent = await fileInput.files[0].text();
      }

      if (!csvContent) {
        setNotif('error', 'Aucun contenu à importer');
        return;
      }

      try {
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
        }
      } catch (err) {
        setNotif('error', err.message);
      }
    };
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

    list.innerHTML = filtered.map((m, i) => {
      const isOpen = !!(m.opened_at && !m.closed_at);
      const isClosed = !!m.closed_at;
      const statusClass = isOpen ? 'open' : (isClosed ? 'closed' : 'pending');
      const statusText = isOpen ? 'Vote en cours' : (isClosed ? 'Terminé' : 'En attente');

      // Vote actions
      let voteActions = '';
      if (isLive && !isOpen && !isClosed) {
        voteActions = `<button class="btn btn-sm btn-primary btn-open-vote" data-motion-id="${m.id}">▶️ Ouvrir</button>`;
      } else if (isLive && isOpen) {
        voteActions = `<button class="btn btn-sm btn-warning btn-close-vote" data-motion-id="${m.id}">⏹️ Clôturer</button>`;
      }

      // Edit actions (only for pending resolutions)
      let editActions = '';
      if (canEdit && !isOpen && !isClosed) {
        editActions = `
          <button class="btn btn-sm btn-ghost btn-edit-motion" data-motion-id="${m.id}" title="Modifier">✏️</button>
          <button class="btn btn-sm btn-ghost btn-delete-motion" data-motion-id="${m.id}" title="Supprimer">🗑️</button>
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
          <span style="color:var(--color-success)">✓ ${m.votes_for || 0}</span>
          <span style="color:var(--color-danger)">✗ ${m.votes_against || 0}</span>
          <span style="color:var(--color-text-muted)">○ ${m.votes_abstain || 0}</span>
        </div>
      ` : '';

      return `
        <div class="resolution-section" data-motion-id="${m.id}">
          <div class="resolution-header">
            <div class="resolution-reorder">${reorderBtns}</div>
            <span class="resolution-chevron">▶</span>
            <span style="font-weight:700;margin-right:0.5rem;">${i + 1}.</span>
            <span class="resolution-title">${escapeHtml(m.title)}</span>
            <span class="resolution-status ${statusClass}">${statusText}</span>
            <div class="resolution-edit-btns" style="margin-left:auto;">${editActions}</div>
          </div>
          <div class="resolution-body">
            <div class="resolution-content">
              ${m.description ? escapeHtml(m.description) : '<em class="text-muted">Aucune description</em>'}
            </div>
            ${results}
            <div class="resolution-actions">
              ${voteActions}
            </div>
          </div>
        </div>
      `;
    }).join('') || '<div class="text-center p-4 text-muted">Aucune résolution</div>';

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
          loadResolutions();
          loadStatusChecklist();
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
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100;display:flex;align-items:center;justify-content:center;';

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
          loadResolutions();
        } else {
          setNotif('error', body?.error || body?.detail || 'Erreur lors de la mise à jour');
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
        setNotif('error', body?.error || 'Erreur lors du réordonnancement');
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
        document.getElementById('addResolutionForm').style.display = 'none';
        document.getElementById('newResolutionTitle').value = '';
        document.getElementById('newResolutionDesc').value = '';
        loadResolutions();
        loadStatusChecklist();
        checkLaunchReady();
      } else {
        setNotif('error', body?.error || body?.detail || 'Erreur lors de la création');
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
      document.getElementById('noActiveVote').style.display = 'block';
      document.getElementById('activeVotePanel').style.display = 'none';
      return;
    }

    document.getElementById('noActiveVote').style.display = 'none';
    document.getElementById('activeVotePanel').style.display = 'block';
    document.getElementById('activeVoteTitle').textContent = currentOpenMotion.title;

    await loadBallots(currentOpenMotion.id);
    renderManualVoteList();
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
            <button class="mode-btn present ${vote === 'for' ? 'active' : ''}" data-vote="for" title="Pour">✓</button>
            <button class="mode-btn absent ${vote === 'against' ? 'active' : ''}" data-vote="against" title="Contre">✗</button>
            <button class="mode-btn excused ${vote === 'abstain' ? 'active' : ''}" data-vote="abstain" title="Abstention">○</button>
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
        if (currentVote && !confirm(`Modifier le vote de "${currentVote}" vers "${newVote}" ?`)) {
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
        setNotif('error', body?.error || 'Erreur lors du vote');
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  async function openVote(motionId) {
    try {
      await api('/api/v1/motions_open.php', { meeting_id: currentMeetingId, motion_id: motionId });
      setNotif('success', 'Vote ouvert');
      loadResolutions();
      switchTab('vote');
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  async function closeVote(motionId) {
    if (!confirm('Clôturer ce vote ?')) return;
    try {
      await api('/api/v1/motions_close.php', { meeting_id: currentMeetingId, motion_id: motionId });
      setNotif('success', 'Vote clôturé');
      currentOpenMotion = null;
      loadResolutions();
      loadVoteTab();
    } catch (err) {
      setNotif('error', err.message);
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
              <span style="color:var(--color-success)">✓ ${vFor}</span>
              <span style="color:var(--color-danger)">✗ ${vAgainst}</span>
              <span style="color:var(--color-text-muted)">○ ${vAbstain}</span>
              <span style="margin-left:auto;">${pct}% pour</span>
            </div>
          ` : ''}
        </div>
      `;
    }).join('') || '<div class="text-center p-4 text-muted">Aucune résolution</div>';

    // Export links (preview=1 permet de générer un brouillon si séance non validée)
    document.getElementById('exportPV').href = `/api/v1/meeting_generate_report_pdf.php?meeting_id=${currentMeetingId}&preview=1`;
    document.getElementById('exportAttendance').href = `/api/v1/export_attendance_csv.php?meeting_id=${currentMeetingId}`;
    document.getElementById('exportVotes').href = `/api/v1/export_votes_csv.php?meeting_id=${currentMeetingId}`;
  }

  // =========================================================================
  // TRANSITIONS
  // =========================================================================

  async function doTransition(toStatus) {
    if (!confirm(`Changer l'état vers "${toStatus}" ?`)) return;
    try {
      const { body } = await api('/api/v1/meeting_transition.php', {
        meeting_id: currentMeetingId,
        to_status: toStatus
      });
      if (body?.ok) {
        if (body.warnings?.length) {
          body.warnings.forEach(w => setNotif('warning', w.msg));
        }
        setNotif('success', `Séance passée en "${toStatus}"`);
        loadMeetingContext(currentMeetingId);
        loadMeetings();
      } else {
        setNotif('error', body?.detail || body?.error || 'Erreur');
      }
    } catch (err) {
      setNotif('error', err.message);
    }
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

  // Resolution search
  document.getElementById('resolutionSearch')?.addEventListener('input', renderResolutions);
  document.getElementById('btnAddResolution')?.addEventListener('click', () => {
    document.getElementById('addResolutionForm').style.display = 'block';
  });
  document.getElementById('btnCancelResolution')?.addEventListener('click', () => {
    document.getElementById('addResolutionForm').style.display = 'none';
  });
  document.getElementById('btnConfirmResolution')?.addEventListener('click', createResolution);

  // Vote tab
  document.getElementById('btnCloseVote')?.addEventListener('click', () => {
    if (currentOpenMotion) closeVote(currentOpenMotion.id);
  });

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

  initTabs();
  loadMeetings();

  // Auto-refresh
  setInterval(() => {
    if (currentMeetingId && !document.hidden) {
      loadStatusChecklist();
      loadDashboard();
      loadDevices();
      if (currentOpenMotion) loadBallots(currentOpenMotion.id);
    }
  }, 5000);

})();
