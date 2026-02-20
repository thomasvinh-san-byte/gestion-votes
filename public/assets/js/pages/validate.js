/** validate.js — Page de validation de séance pour AG-VOTE. Doit être chargé APRÈS utils.js, shared.js et shell.js. */
(function() {
    'use strict';

    let currentMeetingId = null;
    let isReady = false;

    // Récupérer le meeting_id depuis l'URL
    function getMeetingIdFromUrl() {
      const params = new URLSearchParams(window.location.search);
      return params.get('meeting_id');
    }

    // Vérifier l'identifiant de séance
    currentMeetingId = getMeetingIdFromUrl();
    if (!currentMeetingId) {
      setNotif('error', 'Aucune séance sélectionnée');
      setTimeout(() => window.location.href = '/meetings.htmx.html', 2000);
    }

    // Charger les informations de la séance
    async function loadMeetingInfo() {
      try {
        const { body } = await api(`/api/v1/meetings.php?id=${currentMeetingId}`);
        if (body && body.ok && body.data) {
          document.getElementById('meetingTitle').textContent = body.data.title;
          document.getElementById('meetingName').textContent = body.data.title;
          // P2-7: Show date in context bar
          const dateCtx = document.getElementById('meetingDateCtx');
          if (dateCtx && body.data.scheduled_at) {
            const d = new Date(body.data.scheduled_at);
            dateCtx.textContent = '— ' + d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
          }
          Shared.show(document.getElementById('meetingContext'), 'flex');

          // Vérifier si déjà validée
          if (body.data.status === 'archived' || body.data.validated_at) {
            showAlreadyValidated();
          }
        }
      } catch (err) {
        setNotif('error', 'Erreur chargement informations séance');
      }
    }

    // Afficher l'état déjà validée
    function showAlreadyValidated() {
      const zone = document.getElementById('validationZone');
      zone.classList.add('locked');
      zone.innerHTML = `
        <div class="text-lg font-bold mb-2">${icon('check-circle', 'icon-md icon-success')} Séance déjà validée</div>
        <div class="text-sm mb-4">
          Cette séance a été validée et archivée.<br>
          Consultez les exports dans les Archives.
        </div>
        <a class="btn btn-primary" href="/archives.htmx.html${currentMeetingId ? '?meeting_id=' + encodeURIComponent(currentMeetingId) : ''}">
          ${icon('archive', 'icon-sm icon-text')}Voir les archives
        </a>
      `;

      document.getElementById('presidentName').disabled = true;
    }

    // Charger le résumé
    async function loadSummary() {
      try {
        const { body } = await api(`/api/v1/meeting_summary.php?meeting_id=${currentMeetingId}`);

        if (body && body.ok && body.data) {
          const s = body.data;
          document.getElementById('sumMembers').textContent = s.total_members ?? '—';
          document.getElementById('sumPresent').textContent = s.present_count ?? '—';
          document.getElementById('sumMotions').textContent = s.motions_count ?? '—';
          document.getElementById('sumAdopted').textContent = s.adopted_count ?? '—';
          document.getElementById('sumRejected').textContent = s.rejected_count ?? '—';
          document.getElementById('sumBallots').textContent = s.ballots_count ?? '—';

          // Quorum status
          const quorumEl = document.getElementById('sumQuorum');
          if (s.quorum_reached != null) {
            quorumEl.textContent = s.quorum_reached ? 'Atteint' : 'Non atteint';
            quorumEl.className = 'summary-value ' + (s.quorum_reached ? 'text-success' : 'text-danger');
          } else {
            quorumEl.textContent = '—';
            quorumEl.className = 'summary-value';
          }

          // Session duration
          const durationEl = document.getElementById('sumDuration');
          if (s.duration_minutes != null) {
            const hours = Math.floor(s.duration_minutes / 60);
            const mins = s.duration_minutes % 60;
            durationEl.textContent = hours > 0 ? hours + 'h ' + String(mins).padStart(2, '0') + 'min' : mins + 'min';
          } else {
            durationEl.textContent = '—';
          }
        }
      } catch (err) {
        setNotif('error', 'Erreur chargement résumé');
      }
    }

    // Charger les vérifications de conformité
    async function loadChecks() {
      const checksList = document.getElementById('checksList');
      const badge = document.getElementById('readyBadge');
      const btnValidate = document.getElementById('btnValidate');

      checksList.innerHTML = `
        <div class="text-center p-4">
          <div class="spinner"></div>
          <div class="mt-2 text-muted">Vérification en cours...</div>
        </div>
      `;

      try {
        const { body } = await api(`/api/v1/meeting_ready_check.php?meeting_id=${currentMeetingId}`);

        if (body && body.ok && body.data) {
          const checks = body.data.checks || [];
          isReady = body.data.ready;

          badge.className = `badge ${isReady ? 'badge-success' : 'badge-danger'}`;
          badge.textContent = isReady ? 'Prêt' : 'Non prêt';

          checksList.innerHTML = checks.map(check => {
            // P5-3: Add remediation link for failed checks
            let remedLink = '';
            if (!check.passed && currentMeetingId) {
              const opUrl = `/operator.htmx.html?meeting_id=${encodeURIComponent(currentMeetingId)}`;
              remedLink = `<a href="${opUrl}" class="text-xs text-primary" style="display:inline-flex;align-items:center;gap:0.25rem;margin-top:0.25rem">${icon('external-link', 'icon-xs icon-text')} Corriger dans l'opérateur</a>`;
            }
            return `
              <div class="check-item ${check.passed ? 'pass' : 'fail'}">
                <div class="check-icon">${check.passed ? icon('check', 'icon-sm icon-success') : icon('x', 'icon-sm icon-danger')}</div>
                <div>
                  <div class="font-medium">${escapeHtml(check.label)}</div>
                  ${check.detail ? `<div class="text-xs opacity-75">${escapeHtml(check.detail)}</div>` : ''}
                  ${remedLink}
                </div>
              </div>
            `;
          }).join('');

          btnValidate.disabled = !isReady;
        }
      } catch (err) {
        checksList.innerHTML = `
          <div class="alert alert-danger">
            Erreur de vérification : ${escapeHtml(err.message)}
          </div>
        `;
      }
    }

    // Validation modal elements
    const validateModal = document.getElementById('validateModal');
    const confirmCheckbox = document.getElementById('confirmIrreversible');
    const btnModalConfirm = document.getElementById('btnModalConfirm');
    const btnModalCancel = document.getElementById('btnModalCancel');

    // P5-1: Enable confirm button only when checkbox is checked AND "VALIDER" typed
    const confirmText = document.getElementById('confirmText');

    function updateModalConfirmState() {
      if (!btnModalConfirm) return;
      const checkOk = confirmCheckbox && confirmCheckbox.checked;
      const textOk = confirmText && confirmText.value.trim().toUpperCase() === 'VALIDER';
      btnModalConfirm.disabled = !(checkOk && textOk);
    }

    if (confirmCheckbox) confirmCheckbox.addEventListener('change', updateModalConfirmState);
    if (confirmText) {
      confirmText.addEventListener('input', updateModalConfirmState);
      confirmText.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !btnModalConfirm.disabled) {
          e.preventDefault();
          btnModalConfirm.click();
        }
      });
    }

    // Close modal helper
    function closeValidateModal() {
      if (validateModal) validateModal.hidden = true;
      if (confirmCheckbox) confirmCheckbox.checked = false;
      if (confirmText) confirmText.value = '';
      if (btnModalConfirm) btnModalConfirm.disabled = true;
    }

    // Cancel button
    if (btnModalCancel) {
      btnModalCancel.addEventListener('click', closeValidateModal);
    }

    // Backdrop click closes modal
    if (validateModal) {
      validateModal.addEventListener('click', (e) => {
        if (e.target === validateModal) closeValidateModal();
      });
      // Escape key closes modal
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !validateModal.hidden) closeValidateModal();
      });
    }

    // Step 1: btnValidate opens the modal (replaces native confirm())
    document.getElementById('btnValidate').addEventListener('click', () => {
      const presidentName = document.getElementById('presidentName').value.trim();

      if (!presidentName) {
        setNotif('error', 'Le nom du président est requis');
        return;
      }

      // Validate pattern: letters, numbers, spaces, hyphens, apostrophes, periods, commas, parentheses
      const namePattern = /^[A-Za-zÀ-ÿ0-9\s\-'.,()]{2,100}$/;
      if (!namePattern.test(presidentName)) {
        setNotif('error', 'Le nom doit contenir entre 2 et 100 caractères (lettres, chiffres, espaces, tirets, apostrophes)');
        return;
      }

      if (!isReady) {
        setNotif('error', 'La séance n\'est pas prête à être validée');
        return;
      }

      // Show modal instead of confirm()
      if (validateModal) {
        validateModal.hidden = false;
        if (confirmCheckbox) confirmCheckbox.focus();
      }
    });

    // Step 2: Modal confirm button performs the actual validation
    if (btnModalConfirm) {
      btnModalConfirm.addEventListener('click', async () => {
        const presidentName = document.getElementById('presidentName').value.trim();
        const msgDiv = document.getElementById('validateMsg');

        closeValidateModal();

        const btn = document.getElementById('btnValidate');
        Shared.btnLoading(btn, true);
        try {
          const { body } = await api('/api/v1/meeting_validate.php', {
            meeting_id: currentMeetingId,
            president_name: presidentName
          });

          if (body && body.ok) {
            Shared.show(msgDiv, 'block');
            msgDiv.className = 'alert alert-success';
            msgDiv.innerHTML = `${icon('check-circle', 'icon-md icon-success')} Séance validée et archivée avec succès !`;

            setNotif('success', 'Séance validée !');

            showAlreadyValidated();

            // Redirection vers les archives après 3s
            setTimeout(() => {
              window.location.href = '/archives.htmx.html' + (currentMeetingId ? '?meeting_id=' + encodeURIComponent(currentMeetingId) : '');
            }, 3000);
          } else {
            Shared.show(msgDiv, 'block');
            msgDiv.className = 'alert alert-danger';
            msgDiv.innerHTML = `${icon('x-circle', 'icon-md icon-danger')} Erreur : ${escapeHtml(body?.error || 'Échec de la validation')}`;
            Shared.btnLoading(btn, false);
          }
        } catch (err) {
          setNotif('error', err.message);
          Shared.btnLoading(btn, false);
        }
      });
    }

    // Actualiser
    document.getElementById('btnRefresh').addEventListener('click', () => {
      loadSummary();
      loadChecks();
    });

    document.getElementById('btnRecheck').addEventListener('click', loadChecks);

    // Interrogation périodique (actualisation auto 5s)
    let pollingInterval = null;
    function startPolling() {
      if (pollingInterval) return;
      pollingInterval = setInterval(() => {
        if (!document.hidden && currentMeetingId) {
          loadSummary();
          loadChecks();
        }
      }, 5000);
    }
    window.addEventListener('beforeunload', () => { if (pollingInterval) clearInterval(pollingInterval); });

    // Initialiser
    if (currentMeetingId) {
      loadMeetingInfo();
      loadSummary();
      loadChecks();
      startPolling();
    }
  })();
