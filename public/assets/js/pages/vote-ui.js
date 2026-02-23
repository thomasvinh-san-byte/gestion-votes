// Invitation-mode detection: if URL has token=, hide the context bar
// and show the identity banner instead (grandmother-test optimization)
(function() {
  var params = new URLSearchParams(window.location.search);
  if (params.get('token') || params.get('invitation')) {
    var app = document.getElementById('voteApp');
    if (app) app.classList.add('invitation-mode');
    var banner = document.getElementById('identityBanner');
    if (banner) banner.hidden = false;
    var subtitle = document.getElementById('voteSubtitle');
    if (subtitle) subtitle.textContent = 'Votez en toute s\u00e9curit\u00e9';
  }
})();

(function() {
    'use strict';

    const voteButtons = document.querySelectorAll('.vote-btn');
    const confirmOverlay = document.getElementById('confirmationOverlay');
    const confirmChoice = document.getElementById('confirmChoice');
    const btnCancel = document.getElementById('btnCancel');
    const btnConfirm = document.getElementById('btnConfirm');
    let pendingVote = null;
    let hasVoted = false;
    let _isSubmitting = false; // Guard against double-submission

    // Choice labels
    const choiceInfo = {
      'for': { label: 'POUR', color: 'var(--color-success)' },
      'against': { label: 'CONTRE', color: 'var(--color-danger)' },
      'abstain': { label: 'ABSTENTION', color: 'var(--color-text-muted)' },
      'blanc': { label: 'BLANC', color: 'var(--color-neutral)' }
    };

    // Populate confirmation context
    function fillConfirmContext(choice) {
      const info = choiceInfo[choice];
      confirmChoice.textContent = info.label;
      confirmChoice.style.color = info.color;

      // Meeting name
      const meetSel = document.getElementById('meetingSelect');
      const cMeeting = document.getElementById('cMeeting');
      if (cMeeting) {
        cMeeting.textContent = meetSel?.selectedOption?.label || meetSel?.options?.[meetSel.selectedIndex]?.textContent || '\u2014';
      }

      // Member name
      const memSel = document.getElementById('memberSelect');
      const cMember = document.getElementById('cMember');
      if (cMember) {
        cMember.textContent = memSel?.selectedOption?.label || memSel?.options?.[memSel.selectedIndex]?.textContent || '\u2014';
      }

      // Motion title
      const cMotion = document.getElementById('cMotion');
      if (cMotion) {
        cMotion.textContent = document.getElementById('motionTitle')?.textContent || '\u2014';
      }

      // Always show resolution context in confirmation
      const resoText = document.getElementById('resoText');
      const confirmResoDetails = document.getElementById('confirmResoDetails');
      const confirmResoText = document.getElementById('confirmResoText');
      if (confirmResoDetails && confirmResoText) {
        const bodyText = (resoText?.textContent || '').trim();
        const motionTitleText = (document.getElementById('motionTitle')?.textContent || '').trim();
        if (bodyText) {
          confirmResoText.textContent = bodyText;
          confirmResoDetails.hidden = false;
        } else if (motionTitleText && motionTitleText !== 'En attente d\u2019une r\u00e9solution') {
          confirmResoText.textContent = motionTitleText;
          confirmResoDetails.hidden = false;
        } else {
          confirmResoDetails.hidden = true;
        }
      }
    }

    let _triggerBtn = null;
    let _overlayMotionId = null;

    // Vote button click -> show confirmation
    voteButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        if (btn.disabled) return;
        _triggerBtn = btn;
        pendingVote = btn.dataset.choice;
        _overlayMotionId = document.getElementById('motionTitle')?.dataset?.motionId || null;
        fillConfirmContext(pendingVote);
        confirmOverlay.classList.add('show');
        confirmOverlay.setAttribute('aria-hidden', 'false');
        btnConfirm.focus();
      });
    });

    // Watch for motion changes while confirmation overlay is open
    const motionTitleEl = document.getElementById('motionTitle');
    const motionTitleObserver = new MutationObserver(() => {
      if (!confirmOverlay.classList.contains('show') || !_overlayMotionId) return;
      const currentId = motionTitleEl?.dataset?.motionId || null;
      if (currentId !== _overlayMotionId) {
        closeConfirm();
        setNotif('error', 'La r\u00e9solution a chang\u00e9 pendant votre vote. Veuillez revoter.');
        _overlayMotionId = null;
      }
    });
    if (motionTitleEl) {
      motionTitleObserver.observe(motionTitleEl, { attributes: true, attributeFilter: ['data-motion-id'] });
    }

    function closeConfirm() {
      confirmOverlay.classList.remove('show');
      confirmOverlay.setAttribute('aria-hidden', 'true');
      pendingVote = null;
      _isSubmitting = false;
      btnConfirm.disabled = false;
      btnCancel.disabled = false;
      btnConfirm.innerHTML = 'Confirmer';
      if (_triggerBtn && !_triggerBtn.disabled) {
        _triggerBtn.focus();
      }
      _triggerBtn = null;
    }

    // Focus trap inside confirmation overlay
    confirmOverlay.addEventListener('keydown', (e) => {
      if (e.key !== 'Tab') return;
      const focusable = confirmOverlay.querySelectorAll('button:not([disabled]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
      if (focusable.length === 0) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    });

    btnCancel.addEventListener('click', closeConfirm);
    confirmOverlay.addEventListener('click', (e) => {
      if (e.target === confirmOverlay) closeConfirm();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && confirmOverlay.classList.contains('show')) closeConfirm();
    });

    // Confirm vote
    btnConfirm.addEventListener('click', async () => {
      if (!pendingVote || _isSubmitting) return;

      const currentTitle = document.getElementById('motionTitle')?.textContent || null;
      if (_overlayMotionId && currentTitle && currentTitle !== _overlayMotionId && currentTitle !== 'En attente d\u2019une r\u00e9solution') {
        closeConfirm();
        setNotif('error', 'La r\u00e9solution a chang\u00e9. Veuillez revoter.');
        return;
      }

      _isSubmitting = true;
      btnConfirm.disabled = true;
      btnCancel.disabled = true;
      btnConfirm.innerHTML = '<span class="spinner spinner-sm"></span> Envoi...';

      const votingChoice = pendingVote;

      try {
        if (typeof submitVote === 'function') {
          await submitVote(votingChoice);
        }

        hasVoted = true;

        voteButtons.forEach(b => {
          b.classList.remove('selected');
          b.setAttribute('aria-pressed', 'false');
        });
        const selectedBtn = document.querySelector(`[data-choice="${votingChoice}"]`);
        if (selectedBtn) {
          selectedBtn.classList.add('selected');
          selectedBtn.setAttribute('aria-pressed', 'true');
        }

        const statusEl = document.getElementById('voteStatus');
        if (statusEl) {
          statusEl.textContent = 'Vot\u00e9';
          statusEl.classList.add('voted');
        }

        const receipt = document.getElementById('voteReceipt');
        if (receipt) {
          const now = new Date();
          const hh = String(now.getHours()).padStart(2, '0');
          const mm = String(now.getMinutes()).padStart(2, '0');
          const ss = String(now.getSeconds()).padStart(2, '0');
          const receiptText = `${hh}:${mm}:${ss} \u2022 ${choiceInfo[votingChoice].label}`;
          receipt.textContent = receiptText;
          receipt.hidden = false;
          try {
            const memberId = document.getElementById('memberSelect')?.value || 'anonymous';
            const motionTitle = document.getElementById('motionTitle')?.textContent || '';
            const key = `ag_vote_receipt_${motionTitle}`;
            sessionStorage.setItem(key, JSON.stringify({ text: receiptText, choice: votingChoice, ts: now.toISOString() }));
            sessionStorage.setItem('ag_vote_last_receipt_' + memberId, receiptText);
          } catch(e) { /* sessionStorage unavailable */ }
        }

        closeConfirm();
        setNotif('success', `Vote ${choiceInfo[votingChoice].label} enregistr\u00e9`);

        voteButtons.forEach(b => b.disabled = true);
        pendingVote = null;
      } catch (err) {
        setNotif('error', 'Erreur: ' + (err.message || '\u00c9chec de l\'envoi'));
        btnConfirm.disabled = false;
        btnCancel.disabled = false;
        btnConfirm.innerHTML = 'R\u00e9essayer';
        return;
      } finally {
        _isSubmitting = false;
        if (hasVoted) {
          btnConfirm.disabled = false;
          btnCancel.disabled = false;
          btnConfirm.innerHTML = 'Confirmer';
        }
      }
    });

    // Connection status
    function updateConnectionStatus(online) {
      const dot = document.getElementById('connectionDot');
      const status = document.getElementById('connectionStatus');
      const banner = document.getElementById('offlineBanner');

      if (online) {
        dot.classList.remove('offline');
        status.textContent = 'Connect\u00e9';
        if (banner) banner.hidden = true;
        if (!hasVoted) {
          voteButtons.forEach(b => b.disabled = false);
        }
      } else {
        dot.classList.add('offline');
        status.textContent = 'Hors ligne';
        if (banner) banner.hidden = false;
        voteButtons.forEach(b => b.disabled = true);
      }
    }

    window.addEventListener('online', () => updateConnectionStatus(true));
    window.addEventListener('offline', () => updateConnectionStatus(false));
    updateConnectionStatus(navigator.onLine);

    // Update member info display
    window.updateMemberDisplay = function(member) {
      if (!member) {
        document.getElementById('memberName').textContent = 'Non connect\u00e9';
        document.getElementById('memberWeight').textContent = '\u2014';
        document.getElementById('memberAvatar').textContent = '?';
        return;
      }

      document.getElementById('memberName').textContent = member.name || 'Membre';

      const weightParts = [];
      if (member.weight && Number(member.weight) > 1) {
        weightParts.push(`${Shared.formatWeight(member.weight)} voix`);
      }
      if (member.mode === 'proxy') {
        weightParts.push(member.proxyFor
          ? `Procuration pour ${member.proxyFor}`
          : 'Vote par procuration');
      } else if (member.mode === 'remote') {
        weightParts.push('\u00c0 distance');
      }
      document.getElementById('memberWeight').textContent = weightParts.length ? weightParts.join(' \u00b7 ') : '\u2014';

      document.getElementById('memberAvatar').textContent =
        (member.name || '?').charAt(0).toUpperCase();
    };

    // Hand raise / Request to speak
    const btnHand = document.getElementById('btnHand');
    const speechLabel = document.getElementById('speechLabel');
    const speechQueuePos = document.getElementById('speechQueuePos');
    const speechPosNumber = document.getElementById('speechPosNumber');
    const speechHintEl = document.getElementById('speechHint');
    const speechHandIcon = document.getElementById('speechHandIcon');
    let handRaised = false;
    let queuePosition = null;
    let queueSize = 0;

    btnHand?.addEventListener('click', async () => {
      if (speakingNow) return;

      const meetingId = document.getElementById('meetingSelect')?.value;
      const memberId = document.getElementById('memberSelect')?.value;

      if (!meetingId || !memberId) {
        setNotif('error', 'Veuillez d\u2019abord choisir une s\u00e9ance et un votant');
        return;
      }

      btnHand.disabled = true;
      try {
        const resp = await fetch('/api/v1/speech_request.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ meeting_id: meetingId, member_id: memberId })
        });
        const data = await resp.json();

        if (data.ok) {
          const status = data.data?.status || 'none';
          handRaised = status === 'waiting';
          speakingNow = status === 'speaking';
          queuePosition = data.data?.position || null;
          queueSize = data.data?.queue_size || 0;
          updateHandUI();
          setNotif('success', handRaised ? 'Main lev\u00e9e \u2014 demande envoy\u00e9e' : 'Demande retir\u00e9e');
        } else {
          setNotif('error', data.error || 'Erreur');
        }
      } catch (err) {
        setNotif('error', 'Erreur r\u00e9seau');
      } finally {
        btnHand.disabled = false;
      }
    });

    function updateHandUI() {
      const speechBox = document.getElementById('speechBox');

      if (speakingNow) {
        btnHand.setAttribute('aria-pressed', 'true');
        btnHand.disabled = true;
        speechHandIcon.innerHTML = '<svg class="icon icon-xl"><use href="/assets/icons.svg#icon-mic"></use></svg>';
        speechLabel.textContent = 'Vous avez la parole';
        speechBox?.classList.remove('raised');
        speechBox?.classList.add('speaking');
        if (speechHintEl) speechHintEl.textContent = 'Le pr\u00e9sident vous a accord\u00e9 la parole';
        if (speechQueuePos) speechQueuePos.hidden = true;

      } else if (handRaised) {
        btnHand.setAttribute('aria-pressed', 'true');
        btnHand.disabled = false;
        speechHandIcon.innerHTML = '<svg class="icon icon-xl"><use href="/assets/icons.svg#icon-hand"></use></svg>';

        if (queuePosition && queueSize) {
          speechLabel.textContent = `Main lev\u00e9e \u2014 ${queuePosition}\u1d49 sur ${queueSize}`;
          speechPosNumber.textContent = queuePosition;
          speechQueuePos.hidden = false;
        } else {
          speechLabel.textContent = 'Main lev\u00e9e';
          speechQueuePos.hidden = true;
        }

        speechBox?.classList.add('raised');
        speechBox?.classList.remove('speaking');
        if (speechHintEl) speechHintEl.textContent = 'Appuyez \u00e0 nouveau pour retirer votre demande';

      } else {
        btnHand.setAttribute('aria-pressed', 'false');
        btnHand.disabled = false;
        speechHandIcon.innerHTML = '<svg class="icon icon-xl"><use href="/assets/icons.svg#icon-hand"></use></svg>';
        speechLabel.textContent = 'Lever la main';
        speechBox?.classList.remove('raised', 'speaking');
        if (speechHintEl) speechHintEl.textContent = 'Demander la parole';
        if (speechQueuePos) speechQueuePos.hidden = true;
      }
    }

    // Poll speech status every 3 seconds
    let speakingNow = false;
    let _prevPosition = null;

    async function pollSpeechStatus() {
      const meetingId = document.getElementById('meetingSelect')?.value;
      const memberId = document.getElementById('memberSelect')?.value;

      if (!meetingId || !memberId) return;

      try {
        const resp = await fetch(`/api/v1/speech_my_status.php?meeting_id=${encodeURIComponent(meetingId)}&member_id=${encodeURIComponent(memberId)}`, {
          credentials: 'same-origin'
        });
        const data = await resp.json();

        if (data.ok && data.data) {
          const wasRaised = handRaised;
          const wasSpeaking = speakingNow;
          const status = data.data.status || 'none';
          const newPos = data.data.position || null;

          handRaised = status === 'waiting';
          speakingNow = status === 'speaking';
          queuePosition = newPos;
          queueSize = data.data.queue_size || 0;

          const stateChanged = wasRaised !== handRaised || wasSpeaking !== speakingNow;
          const posChanged = handRaised && _prevPosition !== newPos;

          if (stateChanged || posChanged) {
            updateHandUI();
            if (!wasSpeaking && speakingNow) {
              setNotif('success', 'Vous avez la parole !');
            }
          }
          _prevPosition = newPos;
        }
      } catch (err) {
        // Silent fail for polling
      }
    }

    const _speechPollTimer = setInterval(() => {
      if (!document.hidden) pollSpeechStatus();
    }, 3000);
    setTimeout(pollSpeechStatus, 1000);

    // -----------------------------------------------------------------------
    // CURRENT SPEAKER BANNER
    // -----------------------------------------------------------------------
    const speakerBanner = document.getElementById('currentSpeakerBanner');
    const speakerNameEl = document.getElementById('currentSpeakerName');
    const speakerTimerEl = document.getElementById('currentSpeakerTimer');
    const speakerQueueEl = document.getElementById('currentSpeakerQueue');
    const speakerQueueCountEl = document.getElementById('currentSpeakerQueueCount');
    let _speakerStartTime = null;
    let _speakerTimerInterval = null;
    let _lastSpeakerId = null;

    function updateSpeakerTimer() {
      if (!_speakerStartTime || !speakerTimerEl) return;
      const elapsed = Math.floor((Date.now() - _speakerStartTime) / 1000);
      const mm = String(Math.floor(elapsed / 60)).padStart(2, '0');
      const ss = String(elapsed % 60).padStart(2, '0');
      speakerTimerEl.textContent = mm + ':' + ss;
    }

    async function pollCurrentSpeaker() {
      const meetingId = document.getElementById('meetingSelect')?.value;
      if (!meetingId || !speakerBanner) return;

      try {
        const resp = await fetch('/api/v1/speech_current.php?meeting_id=' + encodeURIComponent(meetingId), {
          credentials: 'same-origin'
        });
        const data = await resp.json();

        if (data.ok && data.data && data.data.member_name) {
          const d = data.data;
          if (_lastSpeakerId !== d.request_id) {
            _lastSpeakerId = d.request_id;
            _speakerStartTime = d.started_at ? new Date(d.started_at).getTime() : (Date.now() - (d.elapsed_seconds || 0) * 1000);
            if (_speakerTimerInterval) clearInterval(_speakerTimerInterval);
            _speakerTimerInterval = setInterval(updateSpeakerTimer, 1000);
          }
          speakerNameEl.textContent = d.member_name;
          updateSpeakerTimer();

          if (d.queue_count > 0 && speakerQueueEl) {
            speakerQueueCountEl.textContent = d.queue_count;
            speakerQueueEl.hidden = false;
          } else if (speakerQueueEl) {
            speakerQueueEl.hidden = true;
          }

          speakerBanner.hidden = false;
        } else {
          speakerBanner.hidden = true;
          _lastSpeakerId = null;
          _speakerStartTime = null;
          if (_speakerTimerInterval) {
            clearInterval(_speakerTimerInterval);
            _speakerTimerInterval = null;
          }
        }
      } catch (err) {
        // Silent fail for polling
      }
    }

    const _speakerPollTimer = setInterval(() => {
      if (!document.hidden) pollCurrentSpeaker();
    }, 3000);
    setTimeout(pollCurrentSpeaker, 1500);

    // P4-8: Restore last receipt from sessionStorage on load
    try {
      const memberId = document.getElementById('memberSelect')?.value || 'anonymous';
      const lastReceipt = sessionStorage.getItem('ag_vote_last_receipt_' + memberId);
      if (lastReceipt) {
        const receipt = document.getElementById('voteReceipt');
        if (receipt) {
          receipt.textContent = lastReceipt;
          receipt.hidden = false;
        }
      }
    } catch(e) { /* sessionStorage unavailable */ }

    // P4-2: Keyboard shortcuts 1/2/3/4 -> vote buttons
    document.addEventListener('keydown', (e) => {
      if (confirmOverlay.classList.contains('show')) return;
      const tag = (e.target.tagName || '').toLowerCase();
      if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
      const keyMap = { '1': 'btnFor', '2': 'btnAgainst', '3': 'btnAbstain', '4': 'btnBlanc' };
      const btnId = keyMap[e.key];
      if (!btnId) return;
      const btn = document.getElementById(btnId);
      if (btn && !btn.disabled) {
        e.preventDefault();
        btn.click();
      }
    });

    // Reset state when meeting changes
    document.addEventListener('vote:meeting-changed', () => {
      if (confirmOverlay.classList.contains('show')) closeConfirm();
      hasVoted = false;
      voteButtons.forEach(b => b.disabled = false);
      handRaised = false;
      speakingNow = false;
      queuePosition = null;
      queueSize = 0;
      _prevPosition = null;
      updateHandUI();
      _lastSpeakerId = null;
      _speakerStartTime = null;
      if (_speakerTimerInterval) { clearInterval(_speakerTimerInterval); _speakerTimerInterval = null; }
      if (speakerBanner) speakerBanner.hidden = true;
      const receipt = document.getElementById('voteReceipt');
      if (receipt) { receipt.textContent = ''; receipt.hidden = true; }
    });

    // Cleanup on page unload
    window.addEventListener('pagehide', () => {
      clearInterval(_speechPollTimer);
      clearInterval(_speakerPollTimer);
      if (_speakerTimerInterval) clearInterval(_speakerTimerInterval);
      if (motionTitleObserver) motionTitleObserver.disconnect();
    });
  })();
