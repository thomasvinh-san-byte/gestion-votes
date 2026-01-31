/**
 * president.js — President cockpit for AG-VOTE.
 *
 * Must be loaded AFTER utils.js, shared.js, shell.js and meeting-context.js.
 * Handles: speech queue management, active motion tracking,
 *          readiness checks, meeting validation, auto-refresh polling.
 */
(function() {
  'use strict';

  let currentMeetingId = null;
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

  // Tab switching
  const tabSpeech = document.getElementById('tabSpeech');
  const tabValidation = document.getElementById('tabValidation');
  const panelSpeech = document.getElementById('panelSpeech');
  const panelValidation = document.getElementById('panelValidation');

  tabSpeech.addEventListener('click', () => {
    tabSpeech.classList.add('active');
    tabValidation.classList.remove('active');
    panelSpeech.style.display = 'block';
    panelValidation.style.display = 'none';
  });

  tabValidation.addEventListener('click', () => {
    tabValidation.classList.add('active');
    tabSpeech.classList.remove('active');
    panelValidation.style.display = 'block';
    panelSpeech.style.display = 'none';
    loadReadinessCheck();
  });

  // Load meeting info
  async function loadMeetingInfo(meetingId) {
    try {
      const { body } = await api(`/api/v1/meetings.php?id=${meetingId}`);
      if (body && body.ok && body.data) {
        document.getElementById('meetingTitle').textContent = body.data.title;
      }
    } catch (err) {
      console.error('Meeting error:', err);
    }
  }

  // Load speech data
  async function loadSpeechData() {
    if (!currentMeetingId) return;

    try {
      // Load current speaker
      const { body: currentBody } = await api(`/api/v1/speech_current.php?meeting_id=${currentMeetingId}`);
      const currentDiv = document.getElementById('currentSpeaker');
      const speakingBadge = document.getElementById('speakingBadge');
      const btnEnd = document.getElementById('btnEndSpeech');

      if (currentBody && currentBody.ok && currentBody.data) {
        const speaker = currentBody.data;
        currentDiv.innerHTML = `
          <div class="speech-item speaking">
            <div>
              <div class="font-semibold">${escapeHtml(speaker.member_name)}</div>
              <div class="text-sm text-muted">Depuis ${formatDate(speaker.started_at)}</div>
            </div>
          </div>
        `;
        speakingBadge.style.display = 'inline-flex';
        btnEnd.disabled = false;
      } else {
        currentDiv.innerHTML = `
          <div class="text-center p-6 text-muted">
            <div class="text-3xl mb-2">🔇</div>
            <div>Personne n'a la parole</div>
          </div>
        `;
        speakingBadge.style.display = 'none';
        btnEnd.disabled = true;
      }

      // Load queue
      const { body: queueBody } = await api(`/api/v1/speech_queue.php?meeting_id=${currentMeetingId}`);
      const queueDiv = document.getElementById('speechQueue');
      const queueCount = document.getElementById('queueCount');
      const btnNext = document.getElementById('btnNextSpeaker');

      if (queueBody && queueBody.ok && Array.isArray(queueBody.data) && queueBody.data.length > 0) {
        queueCount.textContent = queueBody.data.length;
        queueDiv.innerHTML = queueBody.data.map((req, i) => `
          <div class="speech-item">
            <div>
              <div class="font-medium">${i + 1}. ${escapeHtml(req.member_name)}</div>
              <div class="text-xs text-muted">${formatDate(req.requested_at)}</div>
            </div>
            <button class="btn btn-sm btn-ghost btn-grant" data-request-id="${req.id}">
              Donner
            </button>
          </div>
        `).join('');
        btnNext.disabled = false;

        // Bind grant buttons
        queueDiv.querySelectorAll('.btn-grant').forEach(btn => {
          btn.addEventListener('click', () => grantSpeech(btn.dataset.requestId));
        });
      } else {
        queueCount.textContent = '0';
        queueDiv.innerHTML = `
          <div class="text-center p-4 text-muted">
            Aucune demande de parole
          </div>
        `;
        btnNext.disabled = true;
      }
    } catch (err) {
      console.error('Speech error:', err);
    }
  }

  // Load active motion
  async function loadActiveMotion() {
    if (!currentMeetingId) return;

    try {
      const { body } = await api(`/api/v1/motions.php?meeting_id=${currentMeetingId}&status=open`);
      const infoDiv = document.getElementById('activeMotionInfo');
      const badge = document.getElementById('motionBadge');
      const btnClose = document.getElementById('btnCloseVote');

      if (body && body.ok && body.data && (body.data.motion || body.data.motions?.length > 0)) {
        const motion = body.data.motion || body.data.motions[0];
        badge.className = 'badge badge-warning badge-dot';
        badge.textContent = 'Vote ouvert';

        infoDiv.innerHTML = `
          <div class="p-4 bg-warning-subtle rounded-lg">
            <div class="font-semibold">${escapeHtml(motion.title)}</div>
            <div class="text-sm text-muted mt-1">${motion.description || ''}</div>
            <div class="flex items-center gap-6 mt-4">
              <div class="text-center">
                <div class="text-2xl font-bold text-success">${motion.votes_for || 0}</div>
                <div class="text-xs text-muted">Pour</div>
              </div>
              <div class="text-center">
                <div class="text-2xl font-bold text-danger">${motion.votes_against || 0}</div>
                <div class="text-xs text-muted">Contre</div>
              </div>
              <div class="text-center">
                <div class="text-2xl font-bold">${motion.votes_abstain || 0}</div>
                <div class="text-xs text-muted">Abstention</div>
              </div>
              <div class="text-center">
                <div class="text-2xl font-bold text-warning">${motion.votes_pending || 0}</div>
                <div class="text-xs text-muted">En attente</div>
              </div>
            </div>
          </div>
        `;

        btnClose.disabled = false;
        btnClose.dataset.motionId = motion.id;
      } else {
        badge.className = 'badge badge-neutral';
        badge.textContent = 'Aucun vote';

        infoDiv.innerHTML = `
          <div class="text-center p-4 text-muted">
            Aucune résolution ouverte
          </div>
        `;

        btnClose.disabled = true;
      }
    } catch (err) {
      console.error('Motion error:', err);
    }
  }

  // Load readiness check
  async function loadReadinessCheck() {
    if (!currentMeetingId) return;

    try {
      const { body } = await api(`/api/v1/meeting_ready_check.php?meeting_id=${currentMeetingId}`);
      const checklist = document.getElementById('readinessChecklist');
      const badge = document.getElementById('readyBadge');
      const btnValidate = document.getElementById('btnValidate');

      if (body && body.ok && body.data) {
        const checks = body.data.checks || [];
        const ready = body.data.ready;

        badge.className = `badge ${ready ? 'badge-success' : 'badge-danger'}`;
        badge.textContent = ready ? 'Prêt' : 'Non prêt';

        checklist.innerHTML = checks.map(check => `
          <div class="check-item ${check.passed ? 'pass' : 'fail'}">
            <div class="check-icon">${check.passed ? '✓' : '✗'}</div>
            <div>
              <div class="font-medium">${escapeHtml(check.label)}</div>
              ${check.detail ? `<div class="text-xs opacity-75">${escapeHtml(check.detail)}</div>` : ''}
            </div>
          </div>
        `).join('');

        btnValidate.disabled = !ready;
      }
    } catch (err) {
      console.error('Ready check error:', err);
    }
  }

  // Grant speech
  async function grantSpeech(requestId) {
    try {
      const { body } = await api('/api/v1/speech_grant.php', {
        meeting_id: currentMeetingId,
        request_id: requestId
      });

      if (body && body.ok) {
        setNotif('success', '🎤 Parole accordée');
        loadSpeechData();
      } else {
        setNotif('error', body?.error || 'Erreur');
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // End speech
  document.getElementById('btnEndSpeech').addEventListener('click', async () => {
    try {
      const { body } = await api('/api/v1/speech_end.php', {
        meeting_id: currentMeetingId
      });

      if (body && body.ok) {
        setNotif('success', 'Parole terminée');
        loadSpeechData();
      } else {
        setNotif('error', body?.error || 'Erreur');
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  });

  // Next speaker
  document.getElementById('btnNextSpeaker').addEventListener('click', async () => {
    try {
      const { body } = await api('/api/v1/speech_next.php', {
        meeting_id: currentMeetingId
      });

      if (body && body.ok) {
        setNotif('success', '🎤 Parole au suivant');
        loadSpeechData();
      } else {
        setNotif('error', body?.error || 'Erreur');
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  });

  // Clear queue
  document.getElementById('btnClearQueue').addEventListener('click', async () => {
    if (!confirm('Vider toute la file d\'attente ?')) return;

    try {
      const { body } = await api('/api/v1/speech_clear.php', {
        meeting_id: currentMeetingId
      });

      if (body && body.ok) {
        setNotif('success', 'File vidée');
        loadSpeechData();
      } else {
        setNotif('error', body?.error || 'Erreur');
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  });

  // Close vote
  document.getElementById('btnCloseVote').addEventListener('click', async () => {
    const btn = document.getElementById('btnCloseVote');
    const motionId = btn.dataset.motionId;

    if (!motionId) return;
    if (!confirm('Clôturer ce vote ? Cette action calculera le résultat définitif.')) return;

    try {
      const { body } = await api('/api/v1/motions_close.php', {
        meeting_id: currentMeetingId,
        motion_id: motionId
      });

      if (body && body.ok) {
        setNotif('success', '✅ Vote clôturé');
        loadActiveMotion();
      } else {
        setNotif('error', body?.error || 'Erreur');
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  });

  // Recheck readiness
  document.getElementById('btnRecheck').addEventListener('click', loadReadinessCheck);

  // Validate meeting
  document.getElementById('btnValidate').addEventListener('click', async () => {
    const presidentName = document.getElementById('presidentName').value.trim();
    const msgDiv = document.getElementById('validateMsg');

    if (!presidentName) {
      setNotif('error', 'Le nom du président est requis');
      return;
    }

    if (!confirm('ATTENTION: Cette action est IRRÉVERSIBLE.\n\nLa séance sera définitivement archivée et plus aucune modification ne sera possible.\n\nConfirmer la validation ?')) {
      return;
    }

    try {
      const { body } = await api('/api/v1/meeting_validate.php', {
        meeting_id: currentMeetingId,
        president_name: presidentName
      });

      if (body && body.ok) {
        msgDiv.style.display = 'block';
        msgDiv.className = 'alert alert-success';
        msgDiv.innerHTML = '✅ Séance validée et archivée avec succès !';

        setNotif('success', '✅ Séance validée !');

        // Disable validation
        document.getElementById('btnValidate').disabled = true;
        document.getElementById('presidentName').disabled = true;
      } else {
        msgDiv.style.display = 'block';
        msgDiv.className = 'alert alert-danger';
        msgDiv.innerHTML = `❌ Erreur: ${body?.error || 'Validation impossible'}`;
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  });

  // Refresh button
  document.getElementById('btnRefresh').addEventListener('click', () => {
    loadSpeechData();
    loadActiveMotion();
    if (panelValidation.style.display !== 'none') {
      loadReadinessCheck();
    }
  });

  // Initialize
  function init() {
    currentMeetingId = getMeetingIdFromUrl();

    if (!currentMeetingId) {
      setNotif('error', 'Aucune séance sélectionnée');
      setTimeout(() => window.location.href = '/meetings.htmx.html', 2000);
      return;
    }

    updateMeetingLinks(currentMeetingId);
    loadMeetingInfo(currentMeetingId);
    loadSpeechData();
    loadActiveMotion();

    // Polling every 3s
    pollingInterval = setInterval(() => {
      loadSpeechData();
      loadActiveMotion();
    }, 3000);
  }

  // Cleanup
  window.addEventListener('beforeunload', () => {
    if (pollingInterval) clearInterval(pollingInterval);
  });

  init();
})();
