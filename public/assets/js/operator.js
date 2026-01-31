/**
 * operator.js — Operator console (fiche séance) for AG-VOTE.
 *
 * Must be loaded AFTER utils.js, shared.js, shell.js and meeting-context.js.
 * Handles: meeting context, attendance stats, quorum status,
 *          active motion tracking, auto-refresh polling.
 */
(function() {
  'use strict';

  const meetingSelect = document.getElementById('meetingSelect');
  let currentMeetingId = null;
  let currentMotionId = null;

  // Get meeting_id from URL
  function getMeetingIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('meeting_id');
  }

  // Update all meeting links with current meeting_id
  function updateMeetingLinks(meetingId) {
    document.querySelectorAll('[data-meeting-link]').forEach(link => {
      const baseUrl = link.getAttribute('href').split('?')[0];
      if (meetingId) {
        link.href = `${baseUrl}?meeting_id=${meetingId}`;
      } else {
        link.href = baseUrl;
      }
    });
  }

  // Load meetings list
  async function loadMeetings() {
    try {
      const { status, body } = await api('/api/v1/meetings_index.php');

      if (body && body.ok && body.data && Array.isArray(body.data.meetings)) {
        meetingSelect.innerHTML = '<option value="">— Sélectionner une séance —</option>';

        body.data.meetings.forEach(m => {
          const opt = document.createElement('option');
          opt.value = m.id;
          opt.textContent = `${m.title} (${m.status || 'draft'})`;
          meetingSelect.appendChild(opt);
        });

        // Pre-select if meeting_id in URL
        const urlMeetingId = getMeetingIdFromUrl();
        if (urlMeetingId) {
          meetingSelect.value = urlMeetingId;
          loadMeetingContext(urlMeetingId);
        }
      }
    } catch (err) {
      setNotif('error', 'Erreur chargement séances: ' + err.message);
    }
  }

  // Load meeting context
  async function loadMeetingContext(meetingId) {
    if (!meetingId) {
      resetContext();
      return;
    }

    currentMeetingId = meetingId;
    updateMeetingLinks(meetingId);

    // Update URL
    const url = new URL(window.location);
    url.searchParams.set('meeting_id', meetingId);
    window.history.replaceState({}, '', url);

    try {
      // Load meeting details
      const { body } = await api(`/api/v1/meetings.php?id=${meetingId}`);

      if (body && body.ok && body.data) {
        const m = body.data;

        // Update summary
        document.getElementById('meetingSummary').innerHTML = `
          <div class="font-medium">${escapeHtml(m.title)}</div>
          <div class="text-muted text-sm mt-1">${m.description || 'Pas de description'}</div>
          <div class="text-xs text-secondary mt-2">Créée le ${formatDate(m.created_at)}</div>
        `;

        // Update status badge
        const statusBadge = document.getElementById('meetingStatusBadge');
        const statusInfo = Shared.MEETING_STATUS_MAP[m.status] || Shared.MEETING_STATUS_MAP['draft'];
        statusBadge.className = `badge ${statusInfo.badge}`;
        statusBadge.textContent = statusInfo.text;
      }

      // Load attendance stats
      await loadAttendanceStats(meetingId);

      // Load quorum status
      await loadQuorumStatus(meetingId);

      // Load active motion
      await loadActiveMotion(meetingId);

    } catch (err) {
      setNotif('error', 'Erreur: ' + err.message);
    }
  }

  // Load attendance stats
  async function loadAttendanceStats(meetingId) {
    try {
      const { body } = await api(`/api/v1/attendances.php?meeting_id=${meetingId}`);

      if (body && body.ok && body.data) {
        const stats = body.data.summary || {};
        document.getElementById('statPresent').textContent = stats.present || 0;
        document.getElementById('statRemote').textContent = stats.remote || 0;
        document.getElementById('statProxy').textContent = stats.proxy || 0;
        document.getElementById('statAbsent').textContent = stats.absent || 0;
        document.getElementById('badgeAttendance').textContent = stats.total || 0;
        document.getElementById('kpiPresent').textContent = (stats.present || 0) + (stats.remote || 0);
      }
    } catch (err) {
      console.error('Attendance error:', err);
    }
  }

  // Load quorum status
  async function loadQuorumStatus(meetingId) {
    try {
      const { body } = await api(`/api/v1/quorum_status.php?meeting_id=${meetingId}`);

      if (body && body.ok && body.data) {
        const q = body.data;

        // Update KPI
        document.getElementById('kpiQuorum').textContent = q.met ? '✓' : '✗';
        document.getElementById('kpiQuorum').className = `kpi-value ${q.met ? 'success' : 'danger'}`;

        // Update bar
        const fill = document.getElementById('quorumFill');
        const ratio = Math.min(100, Math.round((q.ratio || 0) * 100));
        fill.style.width = ratio + '%';
        fill.className = `quorum-fill ${q.met ? 'reached' : ratio > 30 ? 'partial' : 'critical'}`;

        // Update threshold marker
        const threshold = document.getElementById('quorumThreshold');
        threshold.style.left = Math.round((q.threshold || 0.5) * 100) + '%';

        // Update text
        document.getElementById('quorumRatio').textContent = `${ratio}% / ${Math.round((q.threshold || 0.5) * 100)}%`;
        document.getElementById('quorumCurrent').textContent = `${q.present || 0} présents`;
        document.getElementById('quorumRequired').textContent = `Seuil: ${Math.round((q.threshold || 0.5) * 100)}%`;

        // Update badge
        const badge = document.getElementById('quorumStatusBadge');
        badge.className = `badge ${q.met ? 'badge-success' : 'badge-warning'}`;
        badge.textContent = q.met ? 'Atteint' : 'Non atteint';

        // Update justification
        document.getElementById('quorumJustification').innerHTML = `
          <div class="text-sm">${q.justification || 'Aucune justification disponible'}</div>
        `;
      }
    } catch (err) {
      console.error('Quorum error:', err);
    }
  }

  // Load active motion
  async function loadActiveMotion(meetingId) {
    try {
      const { body } = await api(`/api/v1/motions.php?meeting_id=${meetingId}&status=open`);

      const btnOpen = document.getElementById('btnOpenMotion');
      const btnClose = document.getElementById('btnCloseMotion');
      const badge = document.getElementById('badgeMotion');
      const detail = document.getElementById('motionDetail');

      if (body && body.ok && body.data && body.data.motions && body.data.motions.length > 0) {
        const m = body.data.motions[0];
        currentMotionId = m.id;

        badge.className = 'badge badge-warning badge-dot';
        badge.textContent = 'Vote ouvert';

        detail.innerHTML = `
          <div class="p-4 bg-warning-subtle rounded-lg border border-warning">
            <div class="font-semibold text-warning-text">${escapeHtml(m.title)}</div>
            <div class="text-sm text-muted mt-1">${m.description || ''}</div>
            <div class="flex items-center gap-4 mt-3 text-sm">
              <span>✅ Pour: <strong id="voteFor">—</strong></span>
              <span>❌ Contre: <strong id="voteAgainst">—</strong></span>
              <span>⚪ Abstention: <strong id="voteAbstain">—</strong></span>
            </div>
          </div>
        `;

        btnOpen.disabled = true;
        btnClose.disabled = false;

        // Update vote counts
        document.getElementById('kpiVoted').textContent = m.votes_count || 0;
        document.getElementById('kpiPending').textContent = m.pending_count || 0;
      } else {
        badge.className = 'badge badge-neutral';
        badge.textContent = 'Aucune';

        detail.innerHTML = `
          <div class="p-4 bg-surface rounded-lg border text-center">
            <div class="text-muted mb-2">Aucune résolution ouverte</div>
            <div class="text-sm text-secondary">
              Ouvrez une résolution depuis la page Résolutions
            </div>
          </div>
        `;

        currentMotionId = null;
        btnOpen.disabled = false;
        btnClose.disabled = true;

        document.getElementById('kpiVoted').textContent = '—';
        document.getElementById('kpiPending').textContent = '—';
      }
    } catch (err) {
      console.error('Motion error:', err);
    }
  }

  // Reset context
  function resetContext() {
    currentMeetingId = null;
    document.getElementById('meetingSummary').innerHTML = 'Sélectionnez une séance';
    document.getElementById('meetingStatusBadge').textContent = '—';
    updateMeetingLinks(null);
  }

  // Event listeners
  meetingSelect.addEventListener('change', () => {
    loadMeetingContext(meetingSelect.value);
  });

  document.getElementById('btnOpenMotion').addEventListener('click', async () => {
    if (!currentMeetingId) return;
    // Redirect to motions page to select which motion to open
    window.location.href = `/motions.htmx.html?meeting_id=${currentMeetingId}`;
  });

  document.getElementById('btnCloseMotion').addEventListener('click', async () => {
    if (!currentMeetingId || !currentMotionId) return;

    if (!confirm('Êtes-vous sûr de vouloir clôturer le vote en cours ?')) return;

    try {
      const { body } = await api('/api/v1/motions_close.php', { motion_id: currentMotionId });

      if (body && body.ok) {
        setNotif('success', 'Vote clôturé');
        loadActiveMotion(currentMeetingId);
      } else {
        setNotif('error', body?.error || 'Erreur');
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  });

  // Register quorum drawer
  if (window.ShellDrawer && window.ShellDrawer.register) {
    window.ShellDrawer.register('quorum', 'Statut du quorum', async function(meetingId, body, esc) {
      if (!meetingId && currentMeetingId) meetingId = currentMeetingId;
      if (!meetingId) {
        body.innerHTML = '<div style="padding:16px;" class="text-muted">Sélectionnez une séance.</div>';
        return;
      }
      body.innerHTML = '<div style="padding:16px;" class="text-muted">Chargement...</div>';
      try {
        var res = await api('/api/v1/quorum_status.php?meeting_id=' + meetingId);
        var b = res.body;
        if (b && b.ok && b.data) {
          var q = b.data;
          var ratio = Math.min(100, Math.round((q.ratio || 0) * 100));
          var threshold = Math.round((q.threshold || 0.5) * 100);
          var barColor = q.met ? 'var(--color-success,#22c55e)' : (ratio > 30 ? 'var(--color-warning,#e8a73e)' : 'var(--color-danger,#c53030)');
          body.innerHTML =
            '<div style="display:flex;flex-direction:column;gap:16px;padding:4px 0;">' +
              '<div style="text-align:center;padding:16px;">' +
                '<div class="badge ' + (q.met ? 'badge-success' : 'badge-warning') + '" style="font-size:16px;padding:8px 16px;">' +
                  (q.met ? 'Quorum atteint' : 'Quorum non atteint') +
                '</div>' +
              '</div>' +
              '<div style="background:var(--color-bg-subtle,#e5e5e5);border-radius:8px;height:20px;position:relative;overflow:hidden;">' +
                '<div style="background:' + barColor + ';height:100%;width:' + ratio + '%;border-radius:8px;transition:width 0.3s;"></div>' +
                '<div style="position:absolute;top:0;bottom:0;left:' + threshold + '%;width:2px;background:#333;"></div>' +
              '</div>' +
              '<div style="display:flex;justify-content:space-between;" class="text-sm">' +
                '<span>' + ratio + '% atteint</span>' +
                '<span>Seuil : ' + threshold + '%</span>' +
              '</div>' +
              '<div style="border-top:1px solid var(--color-border,#ddd);padding-top:12px;display:flex;flex-direction:column;gap:8px;">' +
                '<div style="display:flex;justify-content:space-between;" class="text-sm">' +
                  '<span class="text-muted">Présents</span><span style="font-weight:600;">' + (q.present || 0) + '</span></div>' +
                '<div style="display:flex;justify-content:space-between;" class="text-sm">' +
                  '<span class="text-muted">Requis</span><span style="font-weight:600;">' + (q.required || '—') + '</span></div>' +
                '<div style="display:flex;justify-content:space-between;" class="text-sm">' +
                  '<span class="text-muted">Total éligibles</span><span style="font-weight:600;">' + (q.total_eligible || '—') + '</span></div>' +
                '<div style="display:flex;justify-content:space-between;" class="text-sm">' +
                  '<span class="text-muted">Mode</span><span style="font-weight:600;">' + esc(q.mode || 'simple') + '</span></div>' +
              '</div>' +
            '</div>';
        } else {
          body.innerHTML = '<div style="padding:16px;" class="text-muted">Quorum indisponible.</div>';
        }
      } catch(e) {
        body.innerHTML = '<div style="padding:16px;" class="text-muted">Erreur de chargement.</div>';
      }
    });
  }

  // Initial load
  loadMeetings();

  // Auto-refresh every 5s if meeting selected
  setInterval(() => {
    if (currentMeetingId) {
      loadAttendanceStats(currentMeetingId);
      loadQuorumStatus(currentMeetingId);
      loadActiveMotion(currentMeetingId);
    }
  }, 5000);

})();
