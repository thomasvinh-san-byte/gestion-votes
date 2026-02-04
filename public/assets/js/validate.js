/** validate.js — Meeting validation page for AG-VOTE. Must be loaded AFTER utils.js, shared.js and shell.js. */
(function() {
    'use strict';

    let currentMeetingId = null;
    let isReady = false;

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

          // Check if already validated
          if (body.data.status === 'archived' || body.data.validated_at) {
            showAlreadyValidated();
          }
        }
      } catch (err) {
        console.error('Meeting info error:', err);
      }
    }

    // Show already validated state
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

    // Load summary
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
        }
      } catch (err) {
        console.error('Summary error:', err);
      }
    }

    // Load readiness checks
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

          checksList.innerHTML = checks.map(check => `
            <div class="check-item ${check.passed ? 'pass' : 'fail'}">
              <div class="check-icon">${check.passed ? '✓' : '✗'}</div>
              <div>
                <div class="font-medium">${escapeHtml(check.label)}</div>
                ${check.detail ? `<div class="text-xs opacity-75">${escapeHtml(check.detail)}</div>` : ''}
              </div>
            </div>
          `).join('');

          btnValidate.disabled = !isReady;
        }
      } catch (err) {
        checksList.innerHTML = `
          <div class="alert alert-danger">
            Erreur de vérification: ${escapeHtml(err.message)}
          </div>
        `;
      }
    }

    // Validate meeting
    document.getElementById('btnValidate').addEventListener('click', async () => {
      const presidentName = document.getElementById('presidentName').value.trim();
      const msgDiv = document.getElementById('validateMsg');

      if (!presidentName) {
        setNotif('error', 'Le nom du président est requis');
        return;
      }

      if (!isReady) {
        setNotif('error', 'La séance n\'est pas prête à être validée');
        return;
      }

      const confirm1 = confirm('ATTENTION: Cette action est IRRÉVERSIBLE.\n\nLa séance sera définitivement archivée et plus aucune modification ne sera possible.\n\nContinuer ?');
      if (!confirm1) return;

      const confirm2 = confirm('Dernière confirmation:\n\nVous êtes sur le point de valider et archiver définitivement cette séance.\n\nConfirmer la validation ?');
      if (!confirm2) return;

      const btn = document.getElementById('btnValidate');
      Shared.btnLoading(btn, true);
      try {
        const { body } = await api('/api/v1/meeting_validate.php', {
          meeting_id: currentMeetingId,
          president_name: presidentName
        });

        if (body && body.ok) {
          msgDiv.style.display = 'block';
          msgDiv.className = 'alert alert-success';
          msgDiv.innerHTML = `${icon('check-circle', 'icon-md icon-success')} Séance validée et archivée avec succès !`;

          setNotif('success', 'Séance validée !');

          showAlreadyValidated();

          // Redirect to archives after 3s
          setTimeout(() => {
            window.location.href = '/archives.htmx.html' + (currentMeetingId ? '?meeting_id=' + encodeURIComponent(currentMeetingId) : '');
          }, 3000);
        } else {
          msgDiv.style.display = 'block';
          msgDiv.className = 'alert alert-danger';
          msgDiv.innerHTML = `${icon('x-circle', 'icon-md icon-danger')} Erreur: ${escapeHtml(body?.error || 'Validation impossible')}`;
          Shared.btnLoading(btn, false);
        }
      } catch (err) {
        setNotif('error', err.message);
        Shared.btnLoading(btn, false);
      }
    });

    // Refresh
    document.getElementById('btnRefresh').addEventListener('click', () => {
      loadSummary();
      loadChecks();
    });

    document.getElementById('btnRecheck').addEventListener('click', loadChecks);

    // Polling (5s auto-refresh for checks and summary)
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

    // Initialize
    if (currentMeetingId) {
      loadMeetingInfo();
      loadSummary();
      loadChecks();
      startPolling();
    }
  })();
