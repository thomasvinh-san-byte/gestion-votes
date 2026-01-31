/** attendance.js — Attendance tracking page for AG-VOTE. Must be loaded AFTER utils.js, shared.js and shell.js. */
(function() {
  'use strict';

  let currentMeetingId = null;
  let attendanceCache = [];
  let currentFilter = 'all';
  const attendanceList = document.getElementById('attendanceList');
  const searchInput = document.getElementById('searchInput');

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

  // Get initials
  function getInitials(name) {
    return (name || '?')
      .split(' ')
      .map(w => w[0])
      .join('')
      .substring(0, 2)
      .toUpperCase();
  }

  // Load meeting info
  async function loadMeetingInfo() {
    try {
      const { body } = await api(`/api/v1/meetings.php?id=${currentMeetingId}`);
      if (body && body.ok && body.data) {
        document.getElementById('meetingTitle').textContent = body.data.title;
        document.getElementById('meetingName').textContent = body.data.title;
        document.getElementById('meetingContext').style.display = 'flex';
      }
    } catch (err) {
      console.error('Meeting info error:', err);
    }
  }

  // Render attendance
  function render(items) {
    const query = searchInput.value.toLowerCase().trim();

    let filtered = items;

    // Apply status filter
    if (currentFilter !== 'all') {
      filtered = filtered.filter(a => a.status === currentFilter);
    }

    // Apply search filter
    if (query) {
      filtered = filtered.filter(a => {
        const name = (a.member_name || a.name || '').toLowerCase();
        const email = (a.email || '').toLowerCase();
        return name.includes(query) || email.includes(query);
      });
    }

    document.getElementById('filteredCount').textContent = `${filtered.length} membre${filtered.length > 1 ? 's' : ''}`;

    if (filtered.length === 0) {
      attendanceList.innerHTML = `
        <div class="empty-state p-6">
          <div class="empty-state-icon">👥</div>
          <div class="empty-state-title">Aucun résultat</div>
          <div class="empty-state-description">
            ${query || currentFilter !== 'all' ? 'Modifiez vos filtres' : 'Aucun participant dans cette séance'}
          </div>
        </div>
      `;
      return;
    }

    attendanceList.innerHTML = filtered.map(a => {
      const name = escapeHtml(a.member_name || a.name || '—');
      const email = escapeHtml(a.email || '');
      const power = a.voting_power ?? 1;
      const status = a.status || 'absent';
      const initials = getInitials(a.member_name || a.name);

      return `
        <div class="attendance-card ${status}" data-member-id="${a.member_id}">
          <div class="attendance-info">
            <div class="attendance-avatar">${initials}</div>
            <div>
              <div class="attendance-name">${name}</div>
              <div class="attendance-meta">${email} · ${power} voix</div>
            </div>
          </div>
          <div class="status-buttons">
            <button class="status-btn present ${status === 'present' ? 'active' : ''}"
                    data-member-id="${a.member_id}" data-status="present">
              Présent
            </button>
            <button class="status-btn remote ${status === 'remote' ? 'active' : ''}"
                    data-member-id="${a.member_id}" data-status="remote">
              Distant
            </button>
            <button class="status-btn absent ${status === 'absent' ? 'active' : ''}"
                    data-member-id="${a.member_id}" data-status="absent">
              Absent
            </button>
          </div>
        </div>
      `;
    }).join('');

    // Bind status buttons
    attendanceList.querySelectorAll('.status-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        updateStatus(btn.dataset.memberId, btn.dataset.status);
      });
    });
  }

  // Update KPIs
  function updateKPIs(items) {
    const total = items.length;
    const present = items.filter(a => a.status === 'present').length;
    const remote = items.filter(a => a.status === 'remote').length;
    const proxy = items.filter(a => a.status === 'proxy').length;
    const absent = items.filter(a => !a.status || a.status === 'absent').length;

    document.getElementById('kpiTotal').textContent = total;
    document.getElementById('kpiPresent').textContent = present;
    document.getElementById('kpiRemote').textContent = remote;
    document.getElementById('kpiProxy').textContent = proxy;
    document.getElementById('kpiAbsent').textContent = absent;
  }

  // Load quorum
  async function loadQuorum() {
    try {
      const { body } = await api(`/api/v1/quorum_status.php?meeting_id=${currentMeetingId}`);

      if (body && body.ok && body.data) {
        const q = body.data;
        const ratio = Math.round((q.ratio || 0) * 100);
        const threshold = Math.round((q.threshold || 0.5) * 100);

        document.getElementById('quorumRatio').textContent = `${ratio}% / ${threshold}%`;

        const fill = document.getElementById('quorumFill');
        fill.style.width = Math.min(100, ratio) + '%';
        fill.className = `quorum-fill ${q.met ? 'reached' : ratio > 30 ? 'partial' : 'critical'}`;

        document.getElementById('quorumThreshold').style.left = threshold + '%';
        document.getElementById('quorumCurrent').textContent = `${q.eligible || 0} éligibles`;

        const status = document.getElementById('quorumStatus');
        status.className = `badge ${q.met ? 'badge-success' : 'badge-warning'}`;
        status.textContent = q.met ? 'Atteint' : 'Non atteint';
      }
    } catch (err) {
      console.error('Quorum error:', err);
    }
  }

  // Load attendance
  async function loadAttendance() {
    attendanceList.innerHTML = `
      <div class="text-center p-6">
        <div class="spinner"></div>
        <div class="mt-4 text-muted">Chargement des présences...</div>
      </div>
    `;

    try {
      const { body } = await api(`/api/v1/attendances.php?meeting_id=${currentMeetingId}`);
      attendanceCache = body?.data || body?.items || [];
      render(attendanceCache);
      updateKPIs(attendanceCache);
      loadQuorum();
    } catch (err) {
      attendanceList.innerHTML = `
        <div class="alert alert-danger">
          <span>❌</span>
          <span>Erreur de chargement: ${escapeHtml(err.message)}</span>
        </div>
      `;
    }
  }

  // Update status
  async function updateStatus(memberId, status) {
    try {
      const { body } = await api('/api/v1/attendances.php', {
        meeting_id: currentMeetingId,
        member_id: memberId,
        status
      });

      if (body && body.ok !== false) {
        // Update cache
        const item = attendanceCache.find(a => a.member_id == memberId);
        if (item) item.status = status;

        render(attendanceCache);
        updateKPIs(attendanceCache);
        loadQuorum();
      } else {
        setNotif('error', body?.error || 'Erreur mise à jour');
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // Mark all present
  document.getElementById('btnMarkAllPresent').addEventListener('click', async () => {
    if (!confirm('Marquer tous les membres comme présents ?')) return;

    try {
      const { body } = await api('/api/v1/attendances_bulk.php', {
        meeting_id: currentMeetingId,
        status: 'present'
      });

      if (body && body.ok) {
        setNotif('success', '✅ Tous marqués présents');
        loadAttendance();
      } else {
        setNotif('error', body?.error || 'Erreur');
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  });

  // Filter tabs
  document.querySelectorAll('.attendance-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.attendance-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      currentFilter = tab.dataset.filter;
      render(attendanceCache);
    });
  });

  // Search
  searchInput.addEventListener('input', () => render(attendanceCache));

  // Initialize
  if (currentMeetingId) {
    loadMeetingInfo();
    loadAttendance();
  }
})();
