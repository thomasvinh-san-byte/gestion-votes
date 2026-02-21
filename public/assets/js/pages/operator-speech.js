/**
 * operator-speech.js — Speech queue sub-module for the operator console.
 * Requires: utils.js, shared.js, operator-tabs.js (OpS bridge)
 */
(function() {
  'use strict';

  const O = window.OpS;

  // =========================================================================
  // TAB: PAROLE - Speech Queue
  // =========================================================================

  async function loadSpeechQueue() {
    if (!O.currentMeetingId) return;

    try {
      const { body } = await api(`/api/v1/speech_queue.php?meeting_id=${O.currentMeetingId}`);
      const data = body?.data || {};
      O.currentSpeakerCache = data.speaker || null;
      const newQueue = data.queue || [];

      // Detect new hand-raise requests
      const newQueueIds = new Set(newQueue.map(r => r.id));
      for (const req of newQueue) {
        if (!O.previousQueueIds.has(req.id)) {
          const name = req.member_name || req.full_name || 'Un membre';
          setNotif('info', name + ' demande la parole');
          break;
        }
      }
      O.previousQueueIds = newQueueIds;

      O.speechQueueCache = newQueue;

      renderSpeechQueue();
      renderCurrentSpeaker();

      // Update tab count
      const countEl = document.getElementById('tabCountSpeech');
      if (countEl) countEl.textContent = O.speechQueueCache.length;
    } catch (err) {
      setNotif('error', 'Erreur chargement file de parole');
    }
  }

  function renderCurrentSpeaker() {
    const noSpeaker = document.getElementById('noSpeakerState');
    const activeSpeaker = document.getElementById('activeSpeakerState');
    const btnNext = document.getElementById('btnNextSpeaker');

    if (!noSpeaker || !activeSpeaker) return;

    // Clear any existing timer
    if (O.speechTimerInterval) {
      clearInterval(O.speechTimerInterval);
      O.speechTimerInterval = null;
    }

    if (!O.currentSpeakerCache) {
      Shared.show(noSpeaker, 'block');
      Shared.hide(activeSpeaker);
      if (btnNext) btnNext.disabled = O.speechQueueCache.length === 0;
      return;
    }

    Shared.hide(noSpeaker);
    Shared.show(activeSpeaker, 'block');

    document.getElementById('currentSpeakerName').textContent = O.currentSpeakerCache.full_name || '—';

    // Start timer
    const startTime = O.currentSpeakerCache.updated_at ? new Date(O.currentSpeakerCache.updated_at).getTime() : Date.now();
    updateSpeechTimer(startTime);
    O.speechTimerInterval = setInterval(() => updateSpeechTimer(startTime), 1000);
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

    if (O.speechQueueCache.length === 0) {
      list.innerHTML = '<div class="text-center p-4 text-muted">Aucune demande de parole</div>';
      return;
    }

    list.innerHTML = O.speechQueueCache.map((s, i) => `
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
    if (!Utils.isValidUUID(memberId)) { setNotif('error', 'ID membre invalide'); return; }
    try {
      await api('/api/v1/speech_grant.php', {
        meeting_id: O.currentMeetingId,
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
      await api('/api/v1/speech_next.php', { meeting_id: O.currentMeetingId });
      setNotif('success', 'Orateur suivant');
      loadSpeechQueue();
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  async function endCurrentSpeech() {
    try {
      await api('/api/v1/speech_end.php', { meeting_id: O.currentMeetingId });
      setNotif('success', 'Parole terminée');
      loadSpeechQueue();
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  async function cancelSpeechRequest(requestId) {
    if (!Utils.isValidUUID(requestId)) { setNotif('error', 'ID demande invalide'); return; }
    try {
      await api('/api/v1/speech_cancel.php', {
        meeting_id: O.currentMeetingId,
        request_id: requestId
      });
      setNotif('success', 'Demande retirée');
      loadSpeechQueue();
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  async function clearSpeechHistory() {
    const confirmed = await O.confirmModal({
      title: 'Vider l\'historique',
      body: '<p>Vider l\'historique des prises de parole ?</p>',
      confirmText: 'Vider',
      confirmClass: 'btn-danger'
    });
    if (!confirmed) return;
    try {
      await api('/api/v1/speech_clear.php', { meeting_id: O.currentMeetingId });
      setNotif('success', 'Historique vidé');
      loadSpeechQueue();
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  function showAddToQueueModal() {
    const presentMembers = O.attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote');
    const alreadyInQueue = new Set(O.speechQueueCache.map(s => s.member_id));
    const available = presentMembers.filter(m => !alreadyInQueue.has(m.member_id) && (!O.currentSpeakerCache || O.currentSpeakerCache.member_id !== m.member_id));

    const modal = O.createModal({
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

    document.getElementById('btnCancelAddSpeech').addEventListener('click', () => O.closeModal(modal));

    const btnConfirm = document.getElementById('btnConfirmAddSpeech');
    if (btnConfirm) {
      btnConfirm.addEventListener('click', async () => {
        const memberId = document.getElementById('addSpeechSelect').value;
        if (!memberId) {
          setNotif('error', 'Sélectionnez un membre');
          return;
        }
        if (!Utils.isValidUUID(memberId)) { setNotif('error', 'ID membre invalide'); return; }
        Shared.btnLoading(btnConfirm, true);
        try {
          await api('/api/v1/speech_request.php', {
            meeting_id: O.currentMeetingId,
            member_id: memberId
          });
          setNotif('success', 'Membre ajouté à la file');
          O.closeModal(modal);
          loadSpeechQueue();
        } catch (err) {
          setNotif('error', err.message);
        } finally {
          Shared.btnLoading(btnConfirm, false);
        }
      });
    }
  }

  // Register on OpS — overwrites the stubs from operator-tabs.js
  O.fn.loadSpeechQueue      = loadSpeechQueue;
  O.fn.renderCurrentSpeaker = renderCurrentSpeaker;
  O.fn.renderSpeechQueue    = renderSpeechQueue;
  O.fn.grantSpeech          = grantSpeech;
  O.fn.nextSpeaker          = nextSpeaker;
  O.fn.endCurrentSpeech     = endCurrentSpeech;
  O.fn.cancelSpeechRequest  = cancelSpeechRequest;
  O.fn.clearSpeechHistory   = clearSpeechHistory;
  O.fn.showAddToQueueModal  = showAddToQueueModal;

})();
