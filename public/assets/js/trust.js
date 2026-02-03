/** trust.js — Trust control and audit page for AG-VOTE. Must be loaded AFTER utils.js, shared.js and shell.js. */
(function() {
  'use strict';

  let currentMeetingId = null;
  const meetingSelect = document.getElementById('meetingSelect');

  function getMeetingIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('meeting_id');
  }

  // Load meetings list and auto-select active one
  async function loadMeetings() {
    try {
      const { body } = await api('/api/v1/meetings.php');

      const meetings = (body && body.ok && body.data) ? (body.data.meetings || []) : [];
      meetingSelect.innerHTML = '<option value="">— Sélectionner une séance —</option>';

      meetings.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.id;
        const statusLabels = { draft: 'Brouillon', scheduled: 'Programmée', live: 'En cours', closed: 'Terminée', archived: 'Archivée' };
        opt.textContent = `${m.title} (${statusLabels[m.status] || m.status})`;
        meetingSelect.appendChild(opt);
      });

      // Priority: URL param > first live meeting > first meeting
      const urlMeetingId = getMeetingIdFromUrl();
      if (urlMeetingId) {
        meetingSelect.value = urlMeetingId;
        loadMeetingData(urlMeetingId);
      } else {
        const live = meetings.find(m => m.status === 'live');
        const first = live || meetings[0];
        if (first) {
          meetingSelect.value = first.id;
          loadMeetingData(first.id);
        }
      }
    } catch (err) {
      setNotif('error', 'Erreur chargement séances: ' + err.message);
    }
  }

  // Load meeting data
  async function loadMeetingData(meetingId) {
    if (!meetingId) return;

    currentMeetingId = meetingId;

    // Update URL
    const url = new URL(window.location);
    url.searchParams.set('meeting_id', meetingId);
    window.history.replaceState({}, '', url);

    await Promise.all([
      loadMeetingStatus(meetingId),
      loadAnomalies(meetingId),
      loadChecks(meetingId),
      loadMotions(meetingId),
      loadAuditLog(meetingId)
    ]);
  }

  // Load meeting status
  async function loadMeetingStatus(meetingId) {
    try {
      const { body } = await api(`/api/v1/meetings.php?id=${meetingId}`);

      if (body && body.ok && body.data) {
        const m = body.data;
        document.getElementById('meetingTitle').textContent = m.title;

        const statusMap = {
          'draft': { class: 'badge-neutral', text: 'Brouillon', icon: '📝' },
          'scheduled': { class: 'badge-info', text: 'Programmée', icon: '📅' },
          'live': { class: 'badge-danger', text: 'En cours', icon: '🔴' },
          'closed': { class: 'badge-success', text: 'Terminée', icon: '✅' },
          'archived': { class: 'badge-neutral', text: 'Archivée', icon: '📦' }
        };
        const status = statusMap[m.status] || statusMap['draft'];

        document.getElementById('statusBox').innerHTML = `
          <span class="badge ${status.class}">${status.icon} ${status.text}</span>
        `;
      }
    } catch (err) {
      console.error('Status error:', err);
    }
  }

  // Load anomalies
  async function loadAnomalies(meetingId) {
    try {
      const { body } = await api(`/api/v1/trust_anomalies.php?meeting_id=${meetingId}`);

      const container = document.getElementById('anomaliesList');
      const badge = document.getElementById('badgeAnomalies');
      const kpi = document.getElementById('kpiAnomalies');

      if (body && body.ok && body.data && Array.isArray(body.data.anomalies) && body.data.anomalies.length > 0) {
        const anomalies = body.data.anomalies;
        badge.textContent = anomalies.length;
        kpi.textContent = anomalies.length;
        kpi.className = 'kpi-value danger';

        container.innerHTML = anomalies.map(a => `
          <div class="anomaly-card ${a.severity || ''}">
            <div class="flex items-start gap-3">
              <span class="text-lg">${a.severity === 'warning' ? '⚠️' : '🚨'}</span>
              <div>
                <div class="font-semibold">${escapeHtml(a.type)}</div>
                <div class="text-sm opacity-80">${escapeHtml(a.message)}</div>
                ${a.context ? `<div class="text-xs mt-1 opacity-60">${escapeHtml(a.context)}</div>` : ''}
              </div>
            </div>
          </div>
        `).join('');
      } else {
        badge.textContent = '0';
        kpi.textContent = '0';
        kpi.className = 'kpi-value success';

        container.innerHTML = `
          <div class="empty-state p-6">
            <div class="empty-state-icon text-success">✅</div>
            <div class="empty-state-title">Aucune anomalie</div>
            <div class="empty-state-description">
              Tous les contrôles sont passés avec succès
            </div>
          </div>
        `;
      }
    } catch (err) {
      console.error('Anomalies error:', err);
    }
  }

  // Load coherence checks
  async function loadChecks(meetingId) {
    try {
      const { body } = await api(`/api/v1/trust_checks.php?meeting_id=${meetingId}`);

      const container = document.getElementById('checksList');
      const kpi = document.getElementById('kpiChecks');

      if (body && body.ok && body.data && Array.isArray(body.data.checks)) {
        const checks = body.data.checks;
        const passed = checks.filter(c => c.passed).length;
        kpi.textContent = `${passed}/${checks.length}`;

        container.innerHTML = checks.map(c => `
          <div class="check-row ${c.passed ? 'pass' : 'fail'}">
            <div class="check-icon">${c.passed ? '✓' : '✗'}</div>
            <div class="flex-1">
              <div class="font-medium">${escapeHtml(c.label)}</div>
              ${c.detail ? `<div class="text-xs opacity-75">${escapeHtml(c.detail)}</div>` : ''}
            </div>
          </div>
        `).join('');
      }
    } catch (err) {
      console.error('Checks error:', err);
    }
  }

  // Load motions
  async function loadMotions(meetingId) {
    try {
      const { body } = await api(`/api/v1/motions_for_meeting.php?meeting_id=${meetingId}`);

      const tbody = document.getElementById('motionsTbody');
      const kpi = document.getElementById('kpiMotions');

      if (body && body.ok && body.data && Array.isArray(body.data.motions)) {
        const motions = body.data.motions;
        kpi.textContent = motions.length;

        if (motions.length === 0) {
          tbody.innerHTML = `
            <tr>
              <td colspan="8" class="text-center text-muted p-6">
                Aucune résolution dans cette séance
              </td>
            </tr>
          `;
          return;
        }

        tbody.innerHTML = motions.map(m => {
          // Compute status from opened_at/closed_at timestamps
          let status = 'draft';
          if (m.closed_at) {
            status = 'closed';
          } else if (m.opened_at) {
            status = 'open';
          }

          const statusBadge = {
            'draft': '<span class="badge badge-neutral">En attente</span>',
            'open': '<span class="badge badge-warning badge-dot">Vote ouvert</span>',
            'closed': '<span class="badge badge-success">Clos</span>'
          }[status] || '<span class="badge">—</span>';

          // Compute decision from vote counts
          const votesFor = m.votes_for || 0;
          const votesAgainst = m.votes_against || 0;
          let decision = m.result || m.decision;
          if (!decision && status === 'closed') {
            decision = votesFor > votesAgainst ? 'adopted' : 'rejected';
          } else if (!decision) {
            decision = 'pending';
          }

          const decisionBadge = {
            'adopted': '<span class="badge badge-success">Adopté</span>',
            'rejected': '<span class="badge badge-danger">Rejeté</span>',
            'pending': '<span class="badge badge-neutral">En attente</span>'
          }[decision] || '<span class="badge badge-neutral">—</span>';

          const total = (m.votes_for || 0) + (m.votes_against || 0) + (m.votes_abstain || 0);
          const isCoherent = m.coherent !== false;

          return `
            <tr>
              <td class="font-medium">${escapeHtml(m.title)}</td>
              <td>${statusBadge}</td>
              <td class="text-success font-medium">${m.votes_for || 0}</td>
              <td class="text-danger font-medium">${m.votes_against || 0}</td>
              <td class="text-muted">${m.votes_abstain || 0}</td>
              <td>${total}</td>
              <td>${decisionBadge}</td>
              <td>
                ${isCoherent
                  ? '<span class="text-success">✓ OK</span>'
                  : '<span class="text-danger">✗ Incohérent</span>'}
              </td>
            </tr>
          `;
        }).join('');

        // Update ballots KPI
        const totalBallots = motions.reduce((sum, m) =>
          sum + (m.votes_for || 0) + (m.votes_against || 0) + (m.votes_abstain || 0), 0);
        document.getElementById('kpiBallots').textContent = totalBallots;
      }
    } catch (err) {
      console.error('Motions error:', err);
    }
  }

  // Load audit log
  async function loadAuditLog(meetingId) {
    try {
      const { body } = await api(`/api/v1/audit_log.php?meeting_id=${meetingId}&limit=50`);

      const container = document.getElementById('auditLog');

      if (body && body.ok && body.data && Array.isArray(body.data.events) && body.data.events.length > 0) {
        container.innerHTML = body.data.events.map(entry => {
          const time = entry.timestamp || entry.created_at;
          const detail = entry.message || entry.detail || '';
          const actionLabel = entry.action_label || entry.action;
          return `
            <div class="audit-entry">
              <div class="audit-time">${formatDate(time)}</div>
              <div class="audit-content">
                <div class="audit-action">${escapeHtml(actionLabel)}</div>
                ${detail ? `<div class="audit-detail">${escapeHtml(detail)}</div>` : ''}
              </div>
              <div>
                <span class="badge badge-neutral">${escapeHtml(entry.actor || 'système')}</span>
              </div>
            </div>
          `;
        }).join('');
      } else {
        container.innerHTML = `
          <div class="text-center p-6 text-muted">
            Aucune entrée d'audit pour cette séance
          </div>
        `;
      }
    } catch (err) {
      console.error('Audit error:', err);
    }
  }

  // Export audit log
  document.getElementById('btnExportAudit').addEventListener('click', () => {
    if (!currentMeetingId) {
      setNotif('error', 'Sélectionnez d\'abord une séance');
      return;
    }

    const url = `/api/v1/audit_export.php?meeting_id=${currentMeetingId}&format=csv`;
    window.open(url, '_blank');
  });

  // Event listeners
  meetingSelect.addEventListener('change', () => {
    loadMeetingData(meetingSelect.value);
  });

  document.getElementById('btnRefresh').addEventListener('click', () => {
    if (currentMeetingId) {
      loadMeetingData(currentMeetingId);
    }
  });

  document.getElementById('btnRecheck').addEventListener('click', () => {
    if (currentMeetingId) {
      loadChecks(currentMeetingId);
      loadAnomalies(currentMeetingId);
    }
  });

  // Polling (10s auto-refresh for anomaly detection)
  let pollingInterval = null;
  function startPolling() {
    if (pollingInterval) return;
    pollingInterval = setInterval(() => {
      if (!document.hidden && currentMeetingId) {
        loadMeetingData(currentMeetingId);
      }
    }, 10000);
  }
  window.addEventListener('beforeunload', () => { if (pollingInterval) clearInterval(pollingInterval); });

  // Initialize
  loadMeetings().then(() => startPolling());
})();
