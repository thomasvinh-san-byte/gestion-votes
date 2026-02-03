/**
 * attendance.js — Simplified attendance management for AG-VOTE
 * Requires: utils.js, shared.js, shell.js
 */
(function() {
  'use strict';

  // DOM elements
  const noMeetingAlert = document.getElementById('noMeetingAlert');
  const mainContent = document.getElementById('mainContent');
  const memberList = document.getElementById('memberList');
  const meetingTitle = document.getElementById('meetingTitle');
  const btnAllPresent = document.getElementById('btnAllPresent');
  const btnAddMember = document.getElementById('btnAddMember');
  const addMemberForm = document.getElementById('addMemberForm');
  const newMemberName = document.getElementById('newMemberName');
  const newMemberMode = document.getElementById('newMemberMode');
  const btnConfirmAdd = document.getElementById('btnConfirmAdd');
  const btnCancelAdd = document.getElementById('btnCancelAdd');
  const searchInput = document.getElementById('searchInput');
  const lockedAlert = document.getElementById('lockedAlert');
  const actionButtons = document.getElementById('actionButtons');

  // State
  let currentMeetingId = null;
  let currentMeetingStatus = null;
  let members = [];
  let searchTerm = '';
  let isLocked = false;

  // Get meeting_id from URL
  function getMeetingIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('meeting_id');
  }

  // Get initials from name
  function getInitials(name) {
    return (name || '?').split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
  }

  // Update stats display
  function updateStats() {
    const present = members.filter(m => m.mode === 'present').length;
    const remote = members.filter(m => m.mode === 'remote').length;
    const excused = members.filter(m => m.mode === 'excused').length;
    const absent = members.filter(m => !m.mode || m.mode === 'absent').length;

    document.getElementById('countPresent').textContent = present;
    document.getElementById('countRemote').textContent = remote;
    const excusedEl = document.getElementById('countExcused');
    if (excusedEl) excusedEl.textContent = excused;
    document.getElementById('countAbsent').textContent = absent;
    document.getElementById('countTotal').textContent = members.length;
  }

  // Sort members: present/remote first, then by name
  function sortMembers(list) {
    return [...list].sort((a, b) => {
      const orderA = a.mode === 'present' ? 0 : a.mode === 'remote' ? 1 : a.mode === 'excused' ? 2 : 3;
      const orderB = b.mode === 'present' ? 0 : b.mode === 'remote' ? 1 : b.mode === 'excused' ? 2 : 3;
      if (orderA !== orderB) return orderA - orderB;
      return (a.full_name || '').localeCompare(b.full_name || '');
    });
  }

  // Filter members by search term
  function filterMembers(list) {
    if (!searchTerm) return list;
    const term = searchTerm.toLowerCase();
    return list.filter(m => (m.full_name || '').toLowerCase().includes(term));
  }

  // Update UI based on locked state
  function updateLockedState() {
    isLocked = ['validated', 'archived'].includes(currentMeetingStatus);

    if (lockedAlert) {
      lockedAlert.style.display = isLocked ? 'block' : 'none';
    }

    if (actionButtons) {
      actionButtons.style.display = isLocked ? 'none' : 'flex';
    }

    // Disable buttons if locked
    if (btnAllPresent) btnAllPresent.disabled = isLocked;
    if (btnAddMember) btnAddMember.disabled = isLocked;
  }

  // Render member list
  function render() {
    if (members.length === 0) {
      memberList.innerHTML = `
        <div class="text-center p-6 text-muted">
          <p>Aucun membre trouvé.</p>
          <p class="mt-2"><a href="/members.htmx.html" class="btn btn-sm btn-secondary">Ajouter des membres</a></p>
        </div>
      `;
      updateStats();
      return;
    }

    const filtered = filterMembers(members);
    const sorted = sortMembers(filtered);

    if (sorted.length === 0) {
      memberList.innerHTML = `
        <div class="text-center p-6 text-muted">
          <p>Aucun résultat pour "${escapeHtml(searchTerm)}"</p>
        </div>
      `;
      updateStats();
      return;
    }

    memberList.innerHTML = sorted.map(m => {
      const mode = m.mode || 'absent';
      const statusClass = mode === 'present' ? 'is-present' : mode === 'remote' ? 'is-remote' : mode === 'excused' ? 'is-excused' : 'is-absent';
      const disabled = isLocked ? 'disabled' : '';

      return `
        <div class="member-row ${statusClass}" data-member-id="${m.member_id}">
          <div class="member-info">
            <div class="member-avatar">${getInitials(m.full_name)}</div>
            <span class="member-name">${escapeHtml(m.full_name || '—')}</span>
          </div>
          <div class="status-btns">
            <button class="status-btn present ${mode === 'present' ? 'active' : ''}" data-mode="present" ${disabled}>Présent</button>
            <button class="status-btn remote ${mode === 'remote' ? 'active' : ''}" data-mode="remote" ${disabled}>Distant</button>
            <button class="status-btn excused ${mode === 'excused' ? 'active' : ''}" data-mode="excused" ${disabled}>Excusé</button>
            <button class="status-btn absent ${mode === 'absent' ? 'active' : ''}" data-mode="absent" ${disabled}>Absent</button>
          </div>
        </div>
      `;
    }).join('');

    // Bind click handlers (only if not locked)
    if (!isLocked) {
      memberList.querySelectorAll('.status-btn:not([disabled])').forEach(btn => {
        btn.addEventListener('click', (e) => {
          const row = e.target.closest('.member-row');
          const memberId = row.dataset.memberId;
          const mode = btn.dataset.mode;
          updateMemberStatus(memberId, mode);
        });
      });
    }

    updateStats();
  }

  // Update single member status
  async function updateMemberStatus(memberId, mode) {
    if (isLocked) {
      setNotif('error', 'Séance verrouillée');
      return;
    }

    try {
      const { body } = await api('/api/v1/attendances_upsert.php', {
        meeting_id: currentMeetingId,
        member_id: memberId,
        mode: mode
      });

      if (body && body.ok !== false) {
        // Update local state
        const member = members.find(m => String(m.member_id) === String(memberId));
        if (member) member.mode = mode;
        render();
      } else {
        setNotif('error', body?.error || 'Erreur');
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // Show/hide add member form
  function showAddMemberForm() {
    if (isLocked) return;
    addMemberForm.style.display = 'block';
    newMemberName.value = '';
    newMemberName.focus();
  }

  function hideAddMemberForm() {
    addMemberForm.style.display = 'none';
    newMemberName.value = '';
  }

  // Add new member and mark as present
  async function addNewMember() {
    if (isLocked) return;

    const name = newMemberName.value.trim();
    const mode = newMemberMode.value;

    if (!name) {
      setNotif('error', 'Veuillez saisir un nom');
      newMemberName.focus();
      return;
    }

    Shared.btnLoading(btnConfirmAdd, true);
    try {
      // Step 1: Create member
      const { body: createResult } = await api('/api/v1/members.php', {
        full_name: name
      });

      if (!createResult || createResult.ok === false) {
        setNotif('error', createResult?.detail || createResult?.error || 'Erreur de creation');
        return;
      }

      const memberId = createResult.data?.id || createResult.id;
      if (!memberId) {
        setNotif('error', 'ID membre non retourne');
        return;
      }

      // Step 2: Mark as present
      const { body: attendResult } = await api('/api/v1/attendances_upsert.php', {
        meeting_id: currentMeetingId,
        member_id: memberId,
        mode: mode
      });

      if (attendResult && attendResult.ok !== false) {
        setNotif('success', `${name} ajouté et marqué ${mode === 'present' ? 'présent' : mode === 'remote' ? 'distant' : mode}`);
        hideAddMemberForm();
        loadData(); // Reload the list
      } else {
        // Member created but attendance failed - still reload
        setNotif('warning', 'Membre créé mais erreur de pointage');
        loadData();
      }

    } catch (err) {
      setNotif('error', err.message);
    } finally {
      Shared.btnLoading(btnConfirmAdd, false);
    }
  }

  // Mark all as present
  async function markAllPresent() {
    if (isLocked) return;
    if (!confirm('Marquer tous les membres comme présents ?')) return;

    Shared.btnLoading(btnAllPresent, true);
    try {
      const { body } = await api('/api/v1/attendances_bulk.php', {
        meeting_id: currentMeetingId,
        mode: 'present'
      });

      if (body && body.ok) {
        members.forEach(m => m.mode = 'present');
        render();
        setNotif('success', 'Tous marqués présents');
      } else {
        setNotif('error', body?.error || 'Erreur');
      }
    } catch (err) {
      setNotif('error', err.message);
    } finally {
      Shared.btnLoading(btnAllPresent, false);
    }
  }

  // Load meeting info
  async function loadMeetingInfo() {
    try {
      const { body } = await api(`/api/v1/meetings.php?id=${currentMeetingId}`);
      if (body?.ok && body?.data) {
        meetingTitle.textContent = body.data.title || 'Séance';
        currentMeetingStatus = body.data.status;
        updateLockedState();
      }
    } catch (err) {
      console.error('Meeting info error:', err);
    }
  }

  // Load attendance data
  async function loadData() {
    memberList.innerHTML = '<div class="text-center p-6 text-muted">Chargement...</div>';

    try {
      const { body } = await api(`/api/v1/attendances.php?meeting_id=${currentMeetingId}`);
      console.log('[Attendance] API response:', body);

      if (body?.data?.debug) {
        console.warn('[Attendance] Debug:', body.data.debug);
      }

      members = body?.data?.attendances || [];
      if (!Array.isArray(members)) members = [];

      render();
    } catch (err) {
      memberList.innerHTML = `<div class="alert alert-danger">Erreur: ${escapeHtml(err.message)}</div>`;
    }
  }

  // Initialize
  function init() {
    currentMeetingId = getMeetingIdFromUrl();

    if (!currentMeetingId) {
      noMeetingAlert.style.display = 'block';
      mainContent.style.display = 'none';
      return;
    }

    noMeetingAlert.style.display = 'none';
    mainContent.style.display = 'block';

    loadMeetingInfo();
    loadData();

    // Event listeners
    btnAllPresent.addEventListener('click', markAllPresent);

    if (btnAddMember) {
      btnAddMember.addEventListener('click', showAddMemberForm);
    }
    if (btnConfirmAdd) {
      btnConfirmAdd.addEventListener('click', addNewMember);
    }
    if (btnCancelAdd) {
      btnCancelAdd.addEventListener('click', hideAddMemberForm);
    }
    if (newMemberName) {
      newMemberName.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') addNewMember();
      });
    }

    // Search input
    if (searchInput) {
      searchInput.addEventListener('input', (e) => {
        searchTerm = e.target.value.trim();
        render();
      });
    }

    // Auto-refresh every 10s
    setInterval(() => {
      if (!document.hidden && currentMeetingId) {
        loadData();
      }
    }, 10000);
  }

  init();
})();
