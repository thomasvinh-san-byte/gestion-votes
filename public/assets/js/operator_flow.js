/** operator_flow.js — Live meeting operator flow for AG-VOTE. Must be loaded AFTER utils.js, shared.js and shell.js. */
(function() {
  'use strict';

  let currentMeetingId = null;
  let currentMotionId = null;
  let selectedMotionId = null;
  let pollingInterval = null;

  // Get meeting_id from URL
  function getMeetingIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('meeting_id');
  }

  // Update meeting links
  function updateMeetingLinks(meetingId) {
    document.querySelectorAll('[data-meeting-link]').forEach(link => {
      const baseUrl = link.getAttribute('href').split('?')[0];
      link.href = meetingId ? `${baseUrl}?meeting_id=${meetingId}` : baseUrl;
    });
  }

  // Load meeting info
  async function loadMeetingInfo(meetingId) {
    try {
      const { body } = await api(`/api/v1/meetings.php?id=${meetingId}`);
      if (body && body.ok && body.data) {
        document.getElementById('meetingTitle').textContent = body.data.title;
      }
    } catch (err) {
      console.error('Meeting info error:', err);
    }
  }

  // Load motions list
  async function loadMotions(meetingId) {
    try {
      const { body } = await api(`/api/v1/motions.php?meeting_id=${meetingId}`);
      const container = document.getElementById('motionsList');

      if (body && body.ok && body.data) {
        const motions = body.data.motions || [];
        document.getElementById('motionsCount').textContent = motions.length;

        if (motions.length === 0) {
          container.innerHTML = `
            <div class="empty-state p-6">
              <div class="empty-state-icon">${icon('clipboard-list', 'icon-xl')}</div>
              <div class="empty-state-title">Aucune résolution</div>
              <div class="empty-state-description">
                Créez des résolutions depuis la fiche séance
              </div>
              <a class="btn btn-primary mt-4" data-meeting-link href="/motions.htmx.html">
                Créer des résolutions
              </a>
            </div>
          `;
          updateMeetingLinks(meetingId);
          return;
        }

        container.innerHTML = motions.map((m, i) => {
          const isOpen = m.status === 'open';
          const isClosed = m.status === 'closed';
          const statusClass = isOpen ? 'active' : (isClosed ? 'done' : '');
          const statusIcon = isOpen ? icon('circle', 'icon-xs icon-danger') : (isClosed ? icon('check-circle', 'icon-xs icon-success') : icon('circle', 'icon-xs icon-muted'));

          if (isOpen) {
            currentMotionId = m.id;
            document.getElementById('currentMotionId').value = m.id;
          }

          return `
            <div class="motion-item ${statusClass}" data-motion-id="${m.id}" data-status="${m.status}">
              <div class="motion-number">${i + 1}</div>
              <div class="motion-info">
                <div class="motion-title">${escapeHtml(m.title)}</div>
                <div class="motion-meta">
                  ${statusIcon} ${isOpen ? 'Vote en cours' : (isClosed ? 'Terminé' : 'En attente')}
                  ${m.result ? ` — ${m.result}` : ''}
                </div>
              </div>
              <div class="flex gap-2">
                ${!isOpen && !isClosed ? `
                  <button class="btn btn-sm btn-primary btn-open-motion" data-motion-id="${m.id}">
                    Ouvrir
                  </button>
                ` : ''}
                ${isOpen ? `
                  <button class="btn btn-sm btn-secondary btn-close-motion" data-motion-id="${m.id}">
                    Clôturer
                  </button>
                ` : ''}
              </div>
            </div>
          `;
        }).join('');

        // Bind motion buttons
        container.querySelectorAll('.btn-open-motion').forEach(btn => {
          btn.addEventListener('click', (e) => {
            e.stopPropagation();
            openVote(btn.dataset.motionId);
          });
        });

        container.querySelectorAll('.btn-close-motion').forEach(btn => {
          btn.addEventListener('click', (e) => {
            e.stopPropagation();
            closeVote(btn.dataset.motionId);
          });
        });

        // Update active vote display
        updateActiveVote(motions.find(m => m.status === 'open'));
      }
    } catch (err) {
      console.error('Motions error:', err);
    }
  }

  // Load quorum status
  async function loadQuorum(meetingId) {
    try {
      const { body } = await api(`/api/v1/quorum_status.php?meeting_id=${meetingId}`);

      if (body && body.ok && body.data) {
        const q = body.data;
        const ratio = Math.round((q.ratio || 0) * 100);
        const threshold = Math.round((q.threshold || 0.5) * 100);

        const bar = document.getElementById('quorumBar');
        bar.style.width = Math.min(100, ratio) + '%';
        bar.className = `quorum-fill ${q.met ? 'reached' : ratio > 30 ? 'partial' : 'critical'}`;

        document.getElementById('quorumMarker').style.left = threshold + '%';
        document.getElementById('quorumPresent').textContent = `${q.present || 0} présents (${ratio}%)`;
        document.getElementById('quorumThresholdText').textContent = `Seuil: ${threshold}%`;

        const badge = document.getElementById('quorumBadge');
        badge.className = `badge ${q.met ? 'badge-success' : 'badge-warning'}`;
        badge.textContent = q.met ? 'Atteint' : 'Non atteint';
      }
    } catch (err) {
      console.error('Quorum error:', err);
    }
  }

  // Update active vote display
  function updateActiveVote(motion) {
    const card = document.getElementById('voteProgressCard');
    const info = document.getElementById('activeVoteInfo');
    const progress = document.getElementById('voteProgress');
    const badge = document.getElementById('voteStatusBadge');
    const btnOpen = document.getElementById('btnOpenVote');
    const btnClose = document.getElementById('btnCloseVote');
    const hint = document.getElementById('actionHint');

    if (motion && motion.status === 'open') {
      info.innerHTML = `
        <div class="font-semibold">${escapeHtml(motion.title)}</div>
        <div class="text-sm text-muted mt-1">${motion.description || ''}</div>
      `;

      progress.style.display = 'flex';
      badge.style.display = 'inline-flex';

      document.getElementById('countFor').textContent = motion.votes_for || 0;
      document.getElementById('countAgainst').textContent = motion.votes_against || 0;
      document.getElementById('countAbstain').textContent = motion.votes_abstain || 0;
      document.getElementById('countPending').textContent = motion.votes_pending || 0;

      btnOpen.disabled = true;
      btnClose.disabled = false;
      hint.textContent = 'Vote en cours - Clôturez quand tous ont voté';

      currentMotionId = motion.id;
    } else {
      info.innerHTML = `
        <div class="text-center p-4 text-muted">
          Aucun vote ouvert — Sélectionnez une résolution
        </div>
      `;

      progress.style.display = 'none';
      badge.style.display = 'none';

      btnOpen.disabled = true;
      btnClose.disabled = true;
      hint.textContent = 'Cliquez sur "Ouvrir" sur une résolution pour démarrer le vote';

      currentMotionId = null;
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
        setNotif('success', 'Vote ouvert');
        loadMotions(currentMeetingId);
      } else {
        setNotif('error', body?.error || 'Erreur ouverture vote');
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // Close vote
  async function closeVote(motionId) {
    if (!confirm('Êtes-vous sûr de vouloir clôturer ce vote ?')) return;

    try {
      const { body } = await api('/api/v1/motions_close.php', {
        meeting_id: currentMeetingId,
        motion_id: motionId || currentMotionId
      });

      if (body && body.ok) {
        setNotif('success', 'Vote clôturé');
        loadMotions(currentMeetingId);
      } else {
        setNotif('error', body?.error || 'Erreur clôture vote');
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // Initialize
  function init() {
    currentMeetingId = getMeetingIdFromUrl();

    if (!currentMeetingId) {
      setNotif('error', 'Aucune séance sélectionnée');
      setTimeout(() => {
        window.location.href = '/meetings.htmx.html';
      }, 2000);
      return;
    }

    document.getElementById('currentMeetingId').value = currentMeetingId;
    updateMeetingLinks(currentMeetingId);

    loadMeetingInfo(currentMeetingId);
    loadMotions(currentMeetingId);
    loadQuorum(currentMeetingId);

    // Polling every 3s
    pollingInterval = setInterval(() => {
      loadMotions(currentMeetingId);
      loadQuorum(currentMeetingId);
    }, 3000);
  }

  // Button handlers
  document.getElementById('btnCloseVote').addEventListener('click', () => {
    if (currentMotionId) {
      closeVote(currentMotionId);
    }
  });

  // Cleanup on leave
  window.addEventListener('beforeunload', () => {
    if (pollingInterval) clearInterval(pollingInterval);
  });

  // Start
  init();

})();
