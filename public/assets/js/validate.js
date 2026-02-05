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
      setNotif('error', 'No meeting selected');
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
        <div class="text-lg font-bold mb-2">${icon('check-circle', 'icon-md icon-success')} Meeting already validated</div>
        <div class="text-sm mb-4">
          This meeting has been validated and archived.<br>
          View exports in the Archives.
        </div>
        <a class="btn btn-primary" href="/archives.htmx.html${currentMeetingId ? '?meeting_id=' + encodeURIComponent(currentMeetingId) : ''}">
          ${icon('archive', 'icon-sm icon-text')}View archives
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
          <div class="mt-2 text-muted">Verification in progress...</div>
        </div>
      `;

      try {
        const { body } = await api(`/api/v1/meeting_ready_check.php?meeting_id=${currentMeetingId}`);

        if (body && body.ok && body.data) {
          const checks = body.data.checks || [];
          isReady = body.data.ready;

          badge.className = `badge ${isReady ? 'badge-success' : 'badge-danger'}`;
          badge.textContent = isReady ? 'Ready' : 'Not ready';

          checksList.innerHTML = checks.map(check => `
            <div class="check-item ${check.passed ? 'pass' : 'fail'}">
              <div class="check-icon">${check.passed ? icon('check', 'icon-sm icon-success') : icon('x', 'icon-sm icon-danger')}</div>
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
            Verification error: ${escapeHtml(err.message)}
          </div>
        `;
      }
    }

    // Validate meeting
    document.getElementById('btnValidate').addEventListener('click', async () => {
      const presidentName = document.getElementById('presidentName').value.trim();
      const msgDiv = document.getElementById('validateMsg');

      if (!presidentName) {
        setNotif('error', 'President name is required');
        return;
      }

      if (!isReady) {
        setNotif('error', 'The meeting is not ready to be validated');
        return;
      }

      const confirm1 = confirm('WARNING: This action is IRREVERSIBLE.\n\nThe meeting will be permanently archived and no further modifications will be possible.\n\nContinue?');
      if (!confirm1) return;

      const confirm2 = confirm('Final confirmation:\n\nYou are about to validate and permanently archive this meeting.\n\nConfirm validation?');
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
          msgDiv.innerHTML = `${icon('check-circle', 'icon-md icon-success')} Meeting validated and archived successfully!`;

          setNotif('success', 'Meeting validated!');

          showAlreadyValidated();

          // Redirect to archives after 3s
          setTimeout(() => {
            window.location.href = '/archives.htmx.html' + (currentMeetingId ? '?meeting_id=' + encodeURIComponent(currentMeetingId) : '');
          }, 3000);
        } else {
          msgDiv.style.display = 'block';
          msgDiv.className = 'alert alert-danger';
          msgDiv.innerHTML = `${icon('x-circle', 'icon-md icon-danger')} Error: ${escapeHtml(body?.error || 'Validation failed')}`;
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

    // Polling (5s auto-refresh for checks and summary, disabled when WebSocket connected)
    let pollingInterval = null;
    function startPolling() {
      if (pollingInterval) return;
      pollingInterval = setInterval(() => {
        // Skip polling if WebSocket is connected and authenticated
        if (typeof AgVoteWebSocket !== 'undefined' && window._wsClient?.isRealTime) return;
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
