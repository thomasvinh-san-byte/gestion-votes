/**
 * motions.js — Resolutions/motions management for AG-VOTE.
 *
 * Must be loaded AFTER utils.js, shared.js and shell.js.
 * Handles: motions list, create, open vote, close vote,
 *          KPI updates, seed attendances (dev).
 */
(function() {
  'use strict';

  let currentMeetingId = null;
  let motionsCache = [];
  const motionsList = document.getElementById('motionsList');

  // Get meeting_id from URL
  function getMeetingIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('meeting_id');
  }

  // Check meeting ID
  currentMeetingId = getMeetingIdFromUrl();
  if (!currentMeetingId) {
    setNotif('error', 'Aucune séance sélectionnée');
    setTimeout(() => window.location.href = '/meetings.htmx.html', 2000);
  }

  // Load meeting info
  async function loadMeetingInfo() {
    try {
      const { body } = await api(`/api/v1/meetings.php?id=${currentMeetingId}`);
      if (body && body.ok && body.data) {
        document.getElementById('meetingTitle').textContent = body.data.title;
        document.getElementById('meetingName').textContent = body.data.title;
        document.getElementById('meetingContext').style.display = 'flex';
      }
    } catch (err) {
      console.error('Meeting info error:', err);
    }
  }

  // Render motions
  function render(motions) {
    if (!motions || motions.length === 0) {
      motionsList.innerHTML = `
        <div class="card">
          <div class="empty-state p-8">
            <div class="empty-state-icon">📋</div>
            <div class="empty-state-title">Aucune résolution</div>
            <div class="empty-state-description">
              Ajoutez des résolutions à l'ordre du jour
            </div>
            <button class="btn btn-primary mt-4" id="btnAddEmpty">
              ➕ Ajouter une résolution
            </button>
          </div>
        </div>
      `;
      document.getElementById('btnAddEmpty')?.addEventListener('click', openModal);
      return;
    }

    // Update KPIs
    const open = motions.filter(m => m.opened_at && !m.closed_at).length;
    const closed = motions.filter(m => m.closed_at).length;
    const pending = motions.filter(m => !m.opened_at).length;

    document.getElementById('kpiTotal').textContent = motions.length;
    document.getElementById('kpiOpen').textContent = open;
    document.getElementById('kpiClosed').textContent = closed;
    document.getElementById('kpiPending').textContent = pending;

    motionsList.innerHTML = motions.map((m, i) => {
      const title = escapeHtml(m.motion_title || m.title || '(sans titre)');
      const desc = escapeHtml(m.motion_description || m.description || '');
      const isOpen = m.opened_at && !m.closed_at;
      const isClosed = !!m.closed_at;
      const isPending = !m.opened_at;

      const statusClass = isOpen ? 'open' : (isClosed ? 'closed' : '');
      const statusBadge = isOpen
        ? '<span class="badge badge-warning badge-dot">Vote ouvert</span>'
        : (isClosed
          ? '<span class="badge badge-success">Terminé</span>'
          : '<span class="badge badge-neutral">En attente</span>');

      const policies = [];
      if (m.secret) policies.push('<span class="badge badge-danger">🔒 Secret</span>');
      if (m.vote_policy_name) policies.push(`<span class="badge">Majorité: ${escapeHtml(m.vote_policy_name)}</span>`);
      if (m.quorum_policy_name) policies.push(`<span class="badge">Quorum: ${escapeHtml(m.quorum_policy_name)}</span>`);

      const results = isClosed ? `
        <div class="motion-results">
          <div class="motion-result-item">
            <div class="motion-result-value text-success">${m.votes_for || 0}</div>
            <div class="motion-result-label">Pour</div>
          </div>
          <div class="motion-result-item">
            <div class="motion-result-value text-danger">${m.votes_against || 0}</div>
            <div class="motion-result-label">Contre</div>
          </div>
          <div class="motion-result-item">
            <div class="motion-result-value">${m.votes_abstain || 0}</div>
            <div class="motion-result-label">Abstention</div>
          </div>
          <div class="motion-result-item">
            ${m.result === 'adopted'
              ? '<span class="badge badge-success badge-lg">✓ Adopté</span>'
              : (m.result === 'rejected'
                ? '<span class="badge badge-danger badge-lg">✗ Rejeté</span>'
                : '<span class="badge badge-neutral">—</span>')}
          </div>
        </div>
      ` : '';

      const actions = isPending ? `
        <button class="btn btn-primary btn-open-vote" data-motion-id="${m.id}">
          ▶️ Ouvrir le vote
        </button>
      ` : (isOpen ? `
        <button class="btn btn-secondary btn-close-vote" data-motion-id="${m.id}">
          ⏹️ Clôturer
        </button>
      ` : '');

      return `
        <div class="motion-card ${statusClass}">
          <div class="motion-header">
            <div class="flex items-start gap-4">
              <div class="motion-number">${i + 1}</div>
              <div>
                <div class="motion-title">${title}</div>
                ${desc ? `<div class="motion-desc">${desc}</div>` : ''}
                ${policies.length ? `<div class="motion-policies">${policies.join('')}</div>` : ''}
              </div>
            </div>
            ${statusBadge}
          </div>
          ${results || actions ? `
            <div class="motion-footer">
              ${results}
              <div class="flex gap-2">
                ${actions}
              </div>
            </div>
          ` : ''}
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
    motionsList.innerHTML = `
      <div class="text-center p-6">
        <div class="spinner"></div>
        <div class="mt-4 text-muted">Chargement des résolutions...</div>
      </div>
    `;

    try {
      const { body } = await api(`/api/v1/motions_for_meeting.php?meeting_id=${currentMeetingId}`);
      motionsCache = body?.items || body?.motions || body?.data || [];
      render(motionsCache);
    } catch (err) {
      motionsList.innerHTML = `
        <div class="alert alert-danger">
          <span>❌</span>
          <span>Erreur de chargement: ${escapeHtml(err.message)}</span>
        </div>
      `;
    }
  }

  // Open vote
  async function openVote(motionId) {
    try {
      const { body } = await api('/api/v1/motions_open.php', {
        meeting_id: currentMeetingId,
        motion_id: motionId
      });

      if (body && body.ok) {
        setNotif('success', '🗳️ Vote ouvert');
        loadMotions();
      } else {
        setNotif('error', body?.error || 'Erreur ouverture vote');
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // Close vote
  async function closeVote(motionId) {
    if (!confirm('Clôturer ce vote ? Cette action calculera le résultat définitif.')) return;

    try {
      const { body } = await api('/api/v1/motions_close.php', {
        meeting_id: currentMeetingId,
        motion_id: motionId
      });

      if (body && body.ok) {
        setNotif('success', '✅ Vote clôturé');
        loadMotions();
      } else {
        setNotif('error', body?.error || 'Erreur clôture vote');
      }
    } catch (err) {
      setNotif('error', err.message);
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

  // Save motion
  document.getElementById('btnSaveMotion').addEventListener('click', async () => {
    const title = document.getElementById('motionTitle').value.trim();
    const description = document.getElementById('motionDesc').value.trim();
    const secret = document.getElementById('motionSecret').checked;

    if (!title) {
      setNotif('error', 'Le titre est requis');
      return;
    }

    try {
      // Create agenda if needed
      await api('/api/v1/agendas.php', { meeting_id: currentMeetingId, title: 'Résolutions' }).catch(() => {});

      const { body } = await api('/api/v1/motions.php', {
        meeting_id: currentMeetingId,
        title,
        description,
        secret
      });

      if (body && body.ok !== false) {
        setNotif('success', '✅ Résolution créée');
        closeModal();
        loadMotions();
      } else {
        setNotif('error', body?.error || 'Erreur création');
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  });

  // Seed attendances
  document.getElementById('btnSeedAttend').addEventListener('click', async () => {
    const limit = prompt('Combien de présences à créer ? (0 = tous)', '0');
    if (limit === null) return;

    try {
      const { body } = await api('/api/v1/dev_seed_attendances.php', {
        meeting_id: currentMeetingId,
        limit: parseInt(limit) || 0
      });

      if (body && body.ok) {
        setNotif('success', '🧪 Présences générées');
      } else {
        setNotif('warning', 'Seed non disponible');
      }
    } catch (err) {
      setNotif('warning', 'Endpoint seed non disponible');
    }
  });

  // Initialize
  if (currentMeetingId) {
    loadMeetingInfo();
    loadMotions();
  }
})();
