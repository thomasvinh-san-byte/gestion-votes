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

        // Show control card and update transitions
        document.getElementById('meetingControlCard').style.display = 'block';
        updateTransitionButtons(m.status);

        // Load meeting roles (president assignment)
        loadMeetingRoles(meetingId);
      }

      // Load users for president dropdown (once)
      loadUsersForPresident();

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
        const attendances = body.data.attendances || [];
        const present = attendances.filter(a => a.mode === 'present').length;
        const remote = attendances.filter(a => a.mode === 'remote').length;
        const proxy = attendances.filter(a => a.mode === 'proxy').length;
        const total = attendances.length;
        const absent = total - present - remote - proxy;

        document.getElementById('statPresent').textContent = present;
        document.getElementById('statRemote').textContent = remote;
        document.getElementById('statProxy').textContent = proxy;
        document.getElementById('statAbsent').textContent = absent;
        document.getElementById('badgeAttendance').textContent = total;
        document.getElementById('kpiPresent').textContent = present + remote;
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
      const { body } = await api(`/api/v1/motions_for_meeting.php?meeting_id=${meetingId}`);

      const btnOpen = document.getElementById('btnOpenMotion');
      const btnClose = document.getElementById('btnCloseMotion');
      const badge = document.getElementById('badgeMotion');
      const detail = document.getElementById('motionDetail');

      const allMotions = body?.data?.motions || [];
      const openMotions = allMotions.filter(m => m.opened_at && !m.closed_at);

      if (openMotions.length > 0) {
        const m = openMotions[0];
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

  // ==========================================================================
  // MEETING TRANSITIONS
  // ==========================================================================

  const TRANSITIONS = {
    draft: [{ to: 'scheduled', label: 'Planifier', icon: '📅', cls: 'btn-secondary' }],
    scheduled: [
      { to: 'frozen', label: 'Geler (verrouiller)', icon: '🧊', cls: 'btn-warning' },
      { to: 'draft', label: 'Retour brouillon', icon: '↩️', cls: 'btn-ghost' }
    ],
    frozen: [
      { to: 'live', label: 'Ouvrir la séance', icon: '▶️', cls: 'btn-primary' },
      { to: 'scheduled', label: 'Dégeler', icon: '↩️', cls: 'btn-ghost' }
    ],
    live: [{ to: 'closed', label: 'Clôturer la séance', icon: '⏹️', cls: 'btn-danger' }],
    closed: [{ to: 'validated', label: 'Valider la séance', icon: '✅', cls: 'btn-primary' }],
    validated: [{ to: 'archived', label: 'Archiver', icon: '📦', cls: 'btn-secondary' }],
    archived: []
  };

  let currentMeetingStatus = null;

  function updateTransitionButtons(status) {
    currentMeetingStatus = status;
    const container = document.getElementById('transitionButtons');
    const transitions = TRANSITIONS[status] || [];

    if (transitions.length === 0) {
      container.innerHTML = '<div class="text-sm text-muted">Aucune transition disponible</div>';
      return;
    }

    container.innerHTML = transitions.map(t => `
      <button class="btn ${t.cls}" data-transition="${t.to}">
        ${t.icon} ${t.label}
      </button>
    `).join('');

    container.querySelectorAll('[data-transition]').forEach(btn => {
      btn.addEventListener('click', () => doTransition(btn.dataset.transition));
    });
  }

  async function doTransition(toStatus) {
    if (!currentMeetingId) return;
    if (!confirm(`Changer l'état de la séance vers "${toStatus}" ?`)) return;

    try {
      const { body } = await api('/api/v1/meeting_transition.php', {
        meeting_id: currentMeetingId,
        to_status: toStatus
      });

      if (body && body.ok) {
        setNotif('success', `Séance passée en "${toStatus}"`);
        loadMeetings();
        loadMeetingContext(currentMeetingId);
      } else {
        const debug = body?.debug || {};
        let msg = body?.error || 'Erreur';
        if (debug.required_role) {
          msg += ` — Rôle requis: ${debug.required_role} (votre rôle: ${debug.user_role || '?'})`;
        }
        setNotif('error', msg);
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // ==========================================================================
  // PRESIDENT ASSIGNMENT
  // ==========================================================================

  async function loadUsersForPresident() {
    try {
      const { body } = await api('/api/v1/admin_users.php');
      const sel = document.getElementById('presidentSelect');
      sel.innerHTML = '<option value="">— Choisir un utilisateur —</option>';

      const users = body?.data?.users || body?.data?.items || [];
      users.forEach(u => {
        const opt = document.createElement('option');
        opt.value = u.id;
        opt.textContent = `${u.name || u.email} (${u.role})`;
        sel.appendChild(opt);
      });
    } catch (err) {
      console.error('Load users error:', err);
    }
  }

  async function loadMeetingRoles(meetingId) {
    try {
      const { body } = await api(`/api/v1/admin_meeting_roles.php?meeting_id=${meetingId}`);
      const items = body?.data?.items || [];
      const president = items.find(r => r.role === 'president');
      const infoDiv = document.getElementById('presidentInfo');

      if (president) {
        infoDiv.innerHTML = `<span class="badge badge-success">Président: ${escapeHtml(president.user_name || president.name || president.email || '?')}</span>`;
        const sel = document.getElementById('presidentSelect');
        if (sel) sel.value = president.user_id || '';
      } else {
        infoDiv.innerHTML = '<span class="badge badge-warning">Aucun président assigné</span>';
      }
    } catch (err) {
      console.error('Meeting roles error:', err);
    }
  }

  document.getElementById('btnAssignPresident').addEventListener('click', async () => {
    const userId = document.getElementById('presidentSelect').value;
    if (!userId || !currentMeetingId) {
      setNotif('error', 'Sélectionnez un utilisateur');
      return;
    }

    try {
      const { body } = await api('/api/v1/admin_meeting_roles.php', {
        action: 'assign',
        meeting_id: currentMeetingId,
        user_id: userId,
        role: 'president'
      });

      if (body && body.ok) {
        setNotif('success', 'Président assigné');
        loadMeetingRoles(currentMeetingId);
      } else {
        setNotif('error', body?.error || 'Erreur');
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  });

  // ==========================================================================
  // CONTEXT MANAGEMENT
  // ==========================================================================

  function resetContext() {
    currentMeetingId = null;
    currentMeetingStatus = null;
    document.getElementById('meetingSummary').innerHTML = 'Sélectionnez une séance';
    document.getElementById('meetingStatusBadge').textContent = '—';
    document.getElementById('meetingControlCard').style.display = 'none';
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
        const res = await api('/api/v1/quorum_status.php?meeting_id=' + meetingId);
        const b = res.body;
        if (b && b.ok && b.data) {
          const q = b.data;
          const ratio = Math.min(100, Math.round((q.ratio || 0) * 100));
          const threshold = Math.round((q.threshold || 0.5) * 100);
          const barColor = q.met ? 'var(--color-success,#22c55e)' : (ratio > 30 ? 'var(--color-warning,#e8a73e)' : 'var(--color-danger,#c53030)');
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
