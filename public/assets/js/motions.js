/**
 * motions.js — Simplified motions management for AG-VOTE.
 * Must be loaded AFTER utils.js, shared.js and shell.js.
 */
(function() {
  'use strict';

  let currentMeetingId = null;
  const motionsList = document.getElementById('motionsList');
  const noMeetingAlert = document.getElementById('noMeetingAlert');
  const mainContent = document.getElementById('mainContent');
  const meetingTitle = document.getElementById('meetingTitle');
  const statTotal = document.getElementById('statTotal');
  const statOpen = document.getElementById('statOpen');
  const statClosed = document.getElementById('statClosed');
  const statPending = document.getElementById('statPending');

  // Get meeting_id from URL
  function getMeetingIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('meeting_id');
  }

  // Check meeting ID
  currentMeetingId = getMeetingIdFromUrl();
  if (!currentMeetingId) {
    noMeetingAlert.style.display = 'block';
    mainContent.style.display = 'none';
  } else {
    noMeetingAlert.style.display = 'none';
    mainContent.style.display = 'block';
  }

  // Load meeting info
  async function loadMeetingInfo() {
    try {
      const { body } = await api(`/api/v1/meetings.php?id=${currentMeetingId}`);
      if (body && body.ok && body.data) {
        meetingTitle.textContent = body.data.title;
      }
    } catch (err) {
      console.error('Meeting info error:', err);
    }
  }

  // Render motions
  function render(motions) {
    // Update stats
    const open = motions.filter(m => m.opened_at && !m.closed_at).length;
    const closed = motions.filter(m => m.closed_at).length;
    const pending = motions.filter(m => !m.opened_at).length;

    statTotal.textContent = motions.length;
    statOpen.textContent = open;
    statClosed.textContent = closed;
    statPending.textContent = pending;

    if (!motions || motions.length === 0) {
      motionsList.innerHTML = `
        <div class="empty-state-inline">
          <p>Aucune résolution</p>
          <button class="btn btn-primary btn-sm mt-4" id="btnAddEmpty">${icon('plus', 'icon-sm icon-text')}Ajouter une résolution</button>
        </div>
      `;
      document.getElementById('btnAddEmpty')?.addEventListener('click', openModal);
      return;
    }

    motionsList.innerHTML = motions.map((m, i) => {
      const title = escapeHtml(m.motion_title || m.title || '(sans titre)');
      const isOpen = m.opened_at && !m.closed_at;
      const isClosed = !!m.closed_at;
      const isPending = !m.opened_at;

      const statusClass = isOpen ? 'is-open' : (isClosed ? 'is-closed' : '');
      const statusText = isOpen ? 'Vote en cours' : (isClosed ? 'Terminé' : 'En attente');

      let resultHtml = '';
      if (isClosed) {
        const resultClass = m.result === 'adopted' ? 'result-adopted' : (m.result === 'rejected' ? 'result-rejected' : '');
        const resultLabel = m.result === 'adopted' ? `${icon('check', 'icon-sm')} Adopté` : (m.result === 'rejected' ? `${icon('x', 'icon-sm')} Rejeté` : '—');
        resultHtml = `
          <div class="results-inline">
            <span style="color:var(--color-success)">${icon('thumbs-up', 'icon-sm')} ${m.votes_for || 0}</span>
            <span style="color:var(--color-danger)">${icon('thumbs-down', 'icon-sm')} ${m.votes_against || 0}</span>
            <span>${icon('minus', 'icon-sm icon-muted')} ${m.votes_abstain || 0}</span>
            <span class="result-badge ${resultClass}">${resultLabel}</span>
          </div>
        `;
      }

      let actionBtn = '';
      if (isPending) {
        actionBtn = `<button class="btn btn-primary btn-sm btn-open-vote" data-motion-id="${m.id}">${icon('play', 'icon-sm icon-text')}Ouvrir</button>`;
      } else if (isOpen) {
        actionBtn = `<button class="btn btn-secondary btn-sm btn-close-vote" data-motion-id="${m.id}">${icon('square', 'icon-sm icon-text')}Clôturer</button>`;
      }

      const secretBadge = m.secret ? `<span class="badge badge-sm">${icon('lock', 'icon-xs')}</span>` : '';

      return `
        <div class="motion-row ${statusClass}">
          <div class="motion-number">${i + 1}</div>
          <div class="motion-info">
            <div class="motion-title">${title} ${secretBadge}</div>
            <div class="motion-meta">${statusText}</div>
          </div>
          ${resultHtml}
          <div class="motion-actions">${actionBtn}</div>
        </div>
      `;
    }).join('');

    // Bind action buttons
    motionsList.querySelectorAll('.btn-open-vote').forEach(btn => {
      btn.addEventListener('click', () => openVote(btn.dataset.motionId));
    });

    motionsList.querySelectorAll('.btn-close-vote').forEach(btn => {
      btn.addEventListener('click', () => closeVote(btn.dataset.motionId));
    });
  }

  // Load motions
  async function loadMotions() {
    motionsList.innerHTML = '<div class="text-center p-6 text-muted">Chargement...</div>';

    try {
      const { body } = await api(`/api/v1/motions_for_meeting.php?meeting_id=${currentMeetingId}`);
      const motions = body?.data?.motions || body?.items || body?.motions || [];
      render(motions);
    } catch (err) {
      motionsList.innerHTML = `
        <div class="alert alert-danger">Erreur: ${escapeHtml(err.message)}</div>
      `;
    }
  }

  // Open vote
  async function openVote(motionId) {
    const btn = motionsList.querySelector(`.btn-open-vote[data-motion-id="${motionId}"]`);
    Shared.btnLoading(btn, true);
    try {
      const { body } = await api('/api/v1/motions_open.php', {
        meeting_id: currentMeetingId,
        motion_id: motionId
      });

      if (body && body.ok) {
        setNotif('success', 'Vote ouvert');
        loadMotions();
      } else {
        setNotif('error', body?.error || 'Erreur');
        Shared.btnLoading(btn, false);
      }
    } catch (err) {
      setNotif('error', err.message);
      Shared.btnLoading(btn, false);
    }
  }

  // Close vote
  async function closeVote(motionId) {
    if (!confirm('Clôturer ce vote ?')) return;

    const btn = motionsList.querySelector(`.btn-close-vote[data-motion-id="${motionId}"]`);
    Shared.btnLoading(btn, true);
    try {
      const { body } = await api('/api/v1/motions_close.php', {
        meeting_id: currentMeetingId,
        motion_id: motionId
      });

      if (body && body.ok) {
        setNotif('success', 'Vote clôturé');
        loadMotions();
      } else {
        setNotif('error', body?.error || 'Erreur');
        Shared.btnLoading(btn, false);
      }
    } catch (err) {
      setNotif('error', err.message);
      Shared.btnLoading(btn, false);
    }
  }

  // Modal
  const modal = document.getElementById('addMotionModal');
  const backdrop = document.getElementById('modalBackdrop');

  function openModal() {
    modal.style.display = 'block';
    backdrop.style.display = 'block';
    document.body.style.overflow = 'hidden';
    document.getElementById('motionTitle').focus();
  }

  function closeModal() {
    modal.style.display = 'none';
    backdrop.style.display = 'none';
    document.body.style.overflow = '';
    document.getElementById('motionTitle').value = '';
    document.getElementById('motionDesc').value = '';
    document.getElementById('motionSecret').checked = false;
  }

  document.getElementById('btnAddMotion').addEventListener('click', openModal);
  document.getElementById('btnCloseModal').addEventListener('click', closeModal);
  document.getElementById('btnCancelModal').addEventListener('click', closeModal);
  backdrop.addEventListener('click', closeModal);

  // Get or create a default agenda
  async function getOrCreateAgenda() {
    const { body: listBody } = await api(`/api/v1/agendas.php?meeting_id=${currentMeetingId}`);
    const existing = listBody?.data?.agendas || [];
    if (existing.length > 0) {
      return existing[0].id;
    }
    const { body: createBody } = await api('/api/v1/agendas.php', {
      meeting_id: currentMeetingId,
      title: 'Résolutions'
    });
    if (createBody?.data?.agenda_id) {
      return createBody.data.agenda_id;
    }
    throw new Error('Impossible de créer le point d\'ordre du jour');
  }

  // Save motion
  document.getElementById('btnSaveMotion').addEventListener('click', async () => {
    const title = document.getElementById('motionTitle').value.trim();
    const description = document.getElementById('motionDesc').value.trim();
    const secret = document.getElementById('motionSecret').checked;

    if (!title) {
      setNotif('error', 'Le titre est requis');
      return;
    }

    const btn = document.getElementById('btnSaveMotion');
    Shared.btnLoading(btn, true);
    try {
      const agendaId = await getOrCreateAgenda();

      const { body } = await api('/api/v1/motions.php', {
        agenda_id: agendaId,
        title,
        description,
        secret
      });

      if (body && body.ok !== false) {
        setNotif('success', 'Résolution créée');
        closeModal();
        loadMotions();
      } else {
        setNotif('error', body?.error || 'Erreur');
      }
    } catch (err) {
      setNotif('error', err.message);
    } finally {
      Shared.btnLoading(btn, false);
    }
  });

  // Auto-refresh
  let pollingInterval = null;
  function startPolling() {
    if (pollingInterval) return;
    pollingInterval = setInterval(() => {
      if (!document.hidden && currentMeetingId) {
        loadMotions();
      }
    }, 5000);
  }
  window.addEventListener('beforeunload', () => { if (pollingInterval) clearInterval(pollingInterval); });

  // Initialize
  if (currentMeetingId) {
    loadMeetingInfo();
    loadMotions();
    startPolling();
  }
})();
