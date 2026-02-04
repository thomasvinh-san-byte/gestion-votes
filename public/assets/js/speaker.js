/**
 * speaker.js — Speaker/speech management for AG-VOTE operators.
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
            <svg class="icon" style="width:2rem;height:2rem;margin-bottom:0.5rem;" aria-hidden="true"><use href="/assets/icons.svg#icon-mic-off"></use></svg>
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
      const { body } = await api(`/api/v1/motions_for_meeting.php?meeting_id=${currentMeetingId}`);
      const infoDiv = document.getElementById('activeMotionInfo');
      const badge = document.getElementById('motionBadge');
      const btnClose = document.getElementById('btnCloseVote');

      const allMotions = body?.data?.motions || [];
      const openMotions = allMotions.filter(m => m.opened_at && !m.closed_at);

      if (openMotions.length > 0) {
        const motion = openMotions[0];
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

  // Grant speech
  async function grantSpeech(requestId) {
    try {
      const { body } = await api('/api/v1/speech_grant.php', {
        meeting_id: currentMeetingId,
        request_id: requestId
      });

      if (body && body.ok) {
        setNotif('success', 'Parole accordée');
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
    const btn = document.getElementById('btnEndSpeech');
    Shared.btnLoading(btn, true);
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
    } finally {
      Shared.btnLoading(btn, false);
    }
  });

  // Next speaker
  document.getElementById('btnNextSpeaker').addEventListener('click', async () => {
    const btn = document.getElementById('btnNextSpeaker');
    Shared.btnLoading(btn, true);
    try {
      const { body } = await api('/api/v1/speech_next.php', {
        meeting_id: currentMeetingId
      });

      if (body && body.ok) {
        setNotif('success', 'Parole au suivant');
        loadSpeechData();
      } else {
        setNotif('error', body?.error || 'Erreur');
      }
    } catch (err) {
      setNotif('error', err.message);
    } finally {
      Shared.btnLoading(btn, false);
    }
  });

  // Clear queue
  document.getElementById('btnClearQueue').addEventListener('click', async () => {
    if (!confirm('Vider toute la file d\'attente ?')) return;

    const btn = document.getElementById('btnClearQueue');
    Shared.btnLoading(btn, true);
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
    } finally {
      Shared.btnLoading(btn, false);
    }
  });

  // Close vote
  document.getElementById('btnCloseVote').addEventListener('click', async () => {
    const btn = document.getElementById('btnCloseVote');
    const motionId = btn.dataset.motionId;

    if (!motionId) return;
    if (!confirm('Clôturer ce vote ? Cette action calculera le résultat définitif.')) return;

    Shared.btnLoading(btn, true);
    try {
      const { body } = await api('/api/v1/motions_close.php', {
        meeting_id: currentMeetingId,
        motion_id: motionId
      });

      if (body && body.ok) {
        setNotif('success', 'Vote clôturé');
        loadActiveMotion();
      } else {
        setNotif('error', body?.error || 'Erreur');
        Shared.btnLoading(btn, false);
      }
    } catch (err) {
      setNotif('error', err.message);
      Shared.btnLoading(btn, false);
    }
  });

  // Refresh button
  document.getElementById('btnRefresh').addEventListener('click', () => {
    loadSpeechData();
    loadActiveMotion();
  });

  // Show waiting state: meeting selector for president
  function showMeetingSelector() {
    const container = document.querySelector('.container');
    if (!container) return;

    // Hide panels
    const panelSpeech = document.getElementById('panelSpeech');
    if (panelSpeech) panelSpeech.style.display = 'none';

    // Insert selector card
    const selectorDiv = document.createElement('div');
    selectorDiv.id = 'presidentWaiting';
    selectorDiv.innerHTML = `
      <div class="card mb-6">
        <div class="card-header">
          <h3 class="card-title">Bienvenue, Monsieur le Président</h3>
          <p class="card-description">Sélectionnez une séance ou attendez qu'elle soit créée par l'opérateur</p>
        </div>
        <div class="card-body">
          <div class="form-group mb-4">
            <label class="form-label" for="presidentMeetingSelect">Séance disponible</label>
            <select class="form-input" id="presidentMeetingSelect">
              <option value="">— En attente d'une séance —</option>
            </select>
          </div>
          <button class="btn btn-primary w-full" id="btnSelectMeeting" disabled>
            Rejoindre la séance
          </button>
          <div class="text-sm text-muted text-center mt-3" id="waitingHint">
            La page s'actualisera automatiquement lorsqu'une séance sera disponible.
          </div>
        </div>
      </div>
    `;

    const notifBox = document.getElementById('notif_box');
    if (notifBox && notifBox.nextSibling) {
      container.insertBefore(selectorDiv, notifBox.nextSibling);
    } else {
      container.appendChild(selectorDiv);
    }

    const sel = document.getElementById('presidentMeetingSelect');
    const btn = document.getElementById('btnSelectMeeting');

    sel.addEventListener('change', () => {
      btn.disabled = !sel.value;
    });

    btn.addEventListener('click', () => {
      if (sel.value) {
        window.location.href = '/speaker.htmx.html?meeting_id=' + encodeURIComponent(sel.value);
      }
    });

    // Poll for meetings
    loadAvailableMeetings(sel, btn);
    meetingPollTimer = setInterval(() => loadAvailableMeetings(sel, btn), 5000);
  }

  let meetingPollTimer = null;

  async function loadAvailableMeetings(sel, btn) {
    try {
      const { body } = await api('/api/v1/meetings_index.php');
      if (!body || !body.ok || !Array.isArray(body.data?.meetings)) return;

      const meetings = body.data.meetings;
      const current = sel.value;
      sel.innerHTML = '<option value="">— Sélectionner une séance —</option>';

      let hasLive = null;
      meetings.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = `${m.title} (${m.status || 'draft'})`;
        sel.appendChild(opt);
        if (m.status === 'live' && !hasLive) hasLive = m;
      });

      // Auto-select live meeting
      if (hasLive) {
        sel.value = hasLive.id;
        btn.disabled = false;
        if (window.Wizard && window.Wizard.showToast) {
          window.Wizard.showToast('Séance en cours détectée : ' + hasLive.title);
        }
        document.getElementById('waitingHint').innerHTML =
          '<strong>Séance en cours détectée !</strong> Cliquez sur "Rejoindre" pour commencer.';
      } else if (meetings.length > 0) {
        if (current) sel.value = current;
        btn.disabled = !sel.value;
        document.getElementById('waitingHint').textContent =
          meetings.length + ' séance(s) disponible(s). Sélectionnez-en une pour commencer.';
      } else {
        btn.disabled = true;
        document.getElementById('waitingHint').textContent =
          'En attente... La page se rafraîchit automatiquement.';
      }
    } catch (err) {
      console.error('Meeting poll error:', err);
    }
  }

  // Initialize
  function init() {
    currentMeetingId = getMeetingIdFromUrl();

    if (!currentMeetingId) {
      // Instead of redirecting, show meeting selector and wait
      showMeetingSelector();
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
    if (meetingPollTimer) clearInterval(meetingPollTimer);
  });

  init();
})();
