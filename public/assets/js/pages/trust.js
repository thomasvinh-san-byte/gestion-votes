/** trust.js — Trust control and audit page for AG-VOTE. Must be loaded AFTER utils.js, shared.js and shell.js. */
(function() {
  'use strict';

  let currentMeetingId = null;
  let currentAnomalies = [];
  let currentAuditEntries = [];
  let currentSeverityFilter = 'all';
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

    try {
      await Promise.all([
        loadMeetingStatus(meetingId),
        loadAnomalies(meetingId),
        loadChecks(meetingId),
        loadMotions(meetingId),
        loadAuditLog(meetingId)
      ]);
    } catch (err) {
      setNotif('error', 'Erreur lors du chargement des données');
    }
  }

  // Load meeting status
  async function loadMeetingStatus(meetingId) {
    try {
      const { body } = await api(`/api/v1/meetings.php?id=${meetingId}`);

      if (body && body.ok && body.data) {
        const m = body.data;
        document.getElementById('meetingTitle').textContent = m.title;

        const statusMap = {
          'draft': { class: 'badge-neutral', text: 'Brouillon', iconName: 'file-text' },
          'scheduled': { class: 'badge-info', text: 'Programmée', iconName: 'calendar' },
          'live': { class: 'badge-danger', text: 'En cours', iconName: 'circle' },
          'closed': { class: 'badge-success', text: 'Terminée', iconName: 'check-circle' },
          'archived': { class: 'badge-neutral', text: 'Archivée', iconName: 'archive' }
        };
        const status = statusMap[m.status] || statusMap['draft'];

        document.getElementById('statusBox').innerHTML = `
          <span class="badge ${status.class}">${icon(status.iconName, 'icon-sm icon-text')}${status.text}</span>
        `;
      }
    } catch (err) {
      setNotif('error', 'Erreur chargement statut');
    }
  }

  // Load anomalies
  async function loadAnomalies(meetingId) {
    try {
      const { body } = await api(`/api/v1/trust_anomalies.php?meeting_id=${meetingId}`);

      const badge = document.getElementById('badgeAnomalies');
      const kpi = document.getElementById('kpiAnomalies');

      if (body && body.ok && body.data && Array.isArray(body.data.anomalies)) {
        currentAnomalies = body.data.anomalies;
        const total = currentAnomalies.length;

        badge.textContent = total;
        kpi.textContent = total;

        // Update severity counts
        const dangerCount = currentAnomalies.filter(a => a.severity === 'danger').length;
        const warningCount = currentAnomalies.filter(a => a.severity === 'warning').length;
        const infoCount = currentAnomalies.filter(a => a.severity === 'info').length;

        document.getElementById('countDanger').textContent = dangerCount;
        document.getElementById('countWarning').textContent = warningCount;
        document.getElementById('countInfo').textContent = infoCount;

        // Update integrity status
        updateIntegrityStatus(total, dangerCount);

        renderAnomalies();
      } else {
        currentAnomalies = [];
        badge.textContent = '0';
        kpi.textContent = '0';
        document.getElementById('countDanger').textContent = '0';
        document.getElementById('countWarning').textContent = '0';
        document.getElementById('countInfo').textContent = '0';
        updateIntegrityStatus(0, 0);
        renderAnomalies();
      }
    } catch (err) {
      setNotif('error', 'Erreur chargement anomalies');
    }
  }

  // Render anomalies with current filter
  function renderAnomalies() {
    const container = document.getElementById('anomaliesList');
    let filtered = currentAnomalies;

    if (currentSeverityFilter !== 'all') {
      filtered = currentAnomalies.filter(a => a.severity === currentSeverityFilter);
    }

    if (filtered.length === 0) {
      container.innerHTML = `
        <div class="empty-state p-6">
          <div class="empty-state-icon">${icon('check-circle', 'icon-xl icon-success')}</div>
          <div class="empty-state-title">${currentSeverityFilter === 'all' ? 'Aucune anomalie' : 'Aucune anomalie de ce type'}</div>
          <div class="empty-state-description">
            ${currentSeverityFilter === 'all' ? 'Tous les contrôles sont passés avec succès' : 'Filtrez par un autre niveau de sévérité'}
          </div>
        </div>
      `;
      return;
    }

    const severityIcons = {
      'danger': icon('alert-circle', 'icon-md icon-danger'),
      'warning': icon('alert-triangle', 'icon-md icon-warning'),
      'info': icon('info', 'icon-md icon-info')
    };

    const severityLabels = {
      'danger': 'Critique',
      'warning': 'Avertissement',
      'info': 'Information'
    };

    container.innerHTML = filtered.map(a => {
      // P6-1: Build drill-down links from anomaly context fields
      let drillLinks = '';
      const links = [];
      if (a.member_id && a.member_name && currentMeetingId) {
        links.push(`<span class="text-xs">${icon('user', 'icon-xs icon-text')} ${escapeHtml(a.member_name)}</span>`);
      }
      if (a.motion_id && a.motion_title && currentMeetingId) {
        links.push(`<a href="/operator.htmx.html?meeting_id=${encodeURIComponent(currentMeetingId)}" class="text-xs text-primary" style="text-decoration:underline">${icon('clipboard-list', 'icon-xs icon-text')} ${escapeHtml(a.motion_title)}</a>`);
      }
      if (links.length) {
        drillLinks = `<div class="flex flex-wrap gap-2 mt-1">${links.join('')}</div>`;
      }

      return `
        <div class="anomaly-card ${a.severity || ''}" style="margin-bottom: 0.75rem;">
          <div class="flex items-start gap-3">
            <span class="text-lg">${severityIcons[a.severity] || severityIcons['warning']}</span>
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span class="font-semibold">${escapeHtml(a.title || a.type)}</span>
                <span class="badge badge-${a.severity === 'danger' ? 'danger' : a.severity === 'warning' ? 'warning' : 'info'}" style="font-size:0.65rem">${severityLabels[a.severity] || ''}</span>
              </div>
              <div class="text-sm opacity-80">${escapeHtml(a.message || a.description)}</div>
              ${a.context ? `<div class="text-xs mt-1 opacity-60">${escapeHtml(a.context)}</div>` : ''}
              ${drillLinks}
            </div>
          </div>
        </div>
      `;
    }).join('');
  }

  // Update integrity status indicator
  function updateIntegrityStatus(totalAnomalies, dangerCount) {
    const statusEl = document.getElementById('integrityStatus');
    if (!statusEl) return;

    let statusClass = 'success';
    let statusIconName = 'check-circle';
    let statusText = 'Conforme';

    if (dangerCount > 0) {
      statusClass = 'danger';
      statusIconName = 'alert-circle';
      statusText = 'Critique';
    } else if (totalAnomalies > 0) {
      statusClass = 'warning';
      statusIconName = 'alert-triangle';
      statusText = 'Attention';
    }

    statusEl.className = `integrity-stat ${statusClass}`;
    statusEl.querySelector('.integrity-stat-value').innerHTML = icon(statusIconName, 'icon-lg icon-' + statusClass);
    statusEl.querySelector('.integrity-stat-label').textContent = statusText;
  }

  // Severity filter click handler
  document.getElementById('severityFilters').addEventListener('click', (e) => {
    const pill = e.target.closest('.severity-pill');
    if (!pill) return;

    document.querySelectorAll('.severity-pill').forEach(p => p.classList.remove('active'));
    pill.classList.add('active');
    currentSeverityFilter = pill.dataset.severity;
    renderAnomalies();
  });

  // Load coherence checks
  async function loadChecks(meetingId) {
    try {
      const { body } = await api(`/api/v1/trust_checks.php?meeting_id=${meetingId}`);

      const container = document.getElementById('checksList');
      const kpi = document.getElementById('kpiChecks');

      if (body && body.ok && body.data && Array.isArray(body.data.checks)) {
        const checks = body.data.checks;
        const passed = checks.filter(c => c.passed).length;
        const total = checks.length;
        const percentage = total > 0 ? Math.round((passed / total) * 100) : 0;

        kpi.textContent = `${passed}/${total}`;

        // Display integrity hash if available
        const hash = body.data.hash || body.data.checksum || null;
        const hashEl = document.getElementById('integrityHash');
        const hashValueEl = document.getElementById('integrityHashValue');
        if (hash && hashEl && hashValueEl) {
          hashValueEl.textContent = hash;
          hashEl.hidden = false;
        } else if (hashEl) {
          hashEl.hidden = true;
        }

        // Update integrity checks class based on pass rate
        const integrityChecksEl = document.getElementById('integrityChecks');
        if (integrityChecksEl) {
          integrityChecksEl.className = 'integrity-stat ' + (percentage === 100 ? 'success' : percentage >= 70 ? 'warning' : 'danger');
        }

        // Update present count from checks data
        const presentCheck = checks.find(c => c.id === 'members_present');
        if (presentCheck && presentCheck.detail) {
          const match = presentCheck.detail.match(/(\d+)/);
          if (match) {
            document.getElementById('kpiPresent').textContent = match[1];
          }
        }

        // Render progress bar and checks
        container.innerHTML = `
          <div class="checks-progress">
            <div class="checks-progress-bar" style="width: ${percentage}%"></div>
          </div>
          <div class="text-sm text-muted mb-3">${passed} sur ${total} contrôles validés (${percentage}%)</div>
          ${checks.map(c => `
            <div class="check-row ${c.passed ? 'pass' : 'fail'}">
              <div class="check-icon">${c.passed ? icon('check', 'icon-sm icon-success') : icon('x', 'icon-sm icon-danger')}</div>
              <div class="flex-1">
                <div class="font-medium">${escapeHtml(c.label)}</div>
                ${c.detail ? `<div class="text-xs opacity-75">${escapeHtml(c.detail)}</div>` : ''}
              </div>
            </div>
          `).join('')}
        `;
      }
    } catch (err) {
      setNotif('error', 'Erreur chargement contrôles');
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
                  ? '<span class="text-success">' + icon('check', 'icon-sm icon-text') + 'OK</span>'
                  : '<span class="text-danger">' + icon('x', 'icon-sm icon-text') + 'Incohérent</span>'}
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
      setNotif('error', 'Erreur chargement résolutions');
    }
  }

  // Load audit log
  async function loadAuditLog(meetingId) {
    try {
      const { body } = await api(`/api/v1/audit_log.php?meeting_id=${meetingId}&limit=50`);

      if (body && body.ok && body.data && Array.isArray(body.data.events)) {
        currentAuditEntries = body.data.events;
      } else {
        currentAuditEntries = [];
      }
      renderAuditLog();
    } catch (err) {
      currentAuditEntries = [];
      renderAuditLog();
      setNotif('error', 'Erreur chargement journal d\'audit');
    }
  }

  // P6-2: Render audit log with filter support
  function renderAuditLog() {
    const container = document.getElementById('auditLog');
    const filterInput = document.getElementById('auditLogFilter');
    const query = (filterInput ? filterInput.value : '').toLowerCase().trim();

    let entries = currentAuditEntries;
    if (query) {
      entries = entries.filter(entry => {
        const text = [
          entry.action_label || entry.action || '',
          entry.message || entry.detail || '',
          entry.actor || ''
        ].join(' ').toLowerCase();
        return text.includes(query);
      });
    }

    if (entries.length === 0) {
      container.innerHTML = `
        <div class="text-center p-6 text-muted">
          ${currentAuditEntries.length === 0
            ? 'Aucune entrée d\'audit pour cette séance'
            : 'Aucun résultat pour « ' + escapeHtml(query) + ' »'}
        </div>
      `;
      return;
    }

    container.innerHTML = entries.map(entry => {
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
  }

  // P6-2: Filter audit log on input
  document.getElementById('auditLogFilter')?.addEventListener('input', renderAuditLog);

  // Export audit log
  document.getElementById('btnExportAudit').addEventListener('click', () => {
    if (!currentMeetingId) {
      setNotif('error', 'Sélectionnez d\'abord une séance');
      return;
    }

    const url = `/api/v1/audit_export.php?meeting_id=${currentMeetingId}&format=csv`;
    window.open(url, '_blank');
    setNotif('success', 'Export CSV du journal d\'audit lancé');
  });

  // P6-6: Export JSON structuré
  document.getElementById('btnExportAuditJson')?.addEventListener('click', () => {
    if (!currentMeetingId) {
      setNotif('error', 'Sélectionnez d\'abord une séance');
      return;
    }

    const url = `/api/v1/audit_export.php?meeting_id=${currentMeetingId}&format=json`;
    window.open(url, '_blank');
    setNotif('success', 'Export JSON structuré lancé (avec chaîne d\'intégrité)');
  });

  // Event listeners
  meetingSelect.addEventListener('change', () => {
    loadMeetingData(meetingSelect.value);
  });

  document.getElementById('btnRefresh').addEventListener('click', async () => {
    if (currentMeetingId) {
      const btn = document.getElementById('btnRefresh');
      Shared.btnLoading(btn, true);
      try { await loadMeetingData(currentMeetingId); } finally { Shared.btnLoading(btn, false); }
    }
  });

  document.getElementById('btnRecheck').addEventListener('click', async () => {
    if (currentMeetingId) {
      const btn = document.getElementById('btnRecheck');
      Shared.btnLoading(btn, true);
      try { await Promise.all([loadChecks(currentMeetingId), loadAnomalies(currentMeetingId)]); } finally { Shared.btnLoading(btn, false); }
    }
  });

  // Copy integrity hash to clipboard
  document.getElementById('btnCopyHash')?.addEventListener('click', () => {
    const hashValue = document.getElementById('integrityHashValue')?.textContent;
    if (hashValue && hashValue !== '—') {
      navigator.clipboard.writeText(hashValue).then(() => {
        setNotif('success', 'Empreinte copiée dans le presse-papiers');
      }).catch(() => {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = hashValue;
        textArea.style.position = 'fixed';
        textArea.style.opacity = '0';
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        setNotif('success', 'Empreinte copiée dans le presse-papiers');
      });
    }
  });

  // Export trust/integrity report
  document.getElementById('btnExportTrust')?.addEventListener('click', () => {
    if (!currentMeetingId) {
      setNotif('error', 'Sélectionnez d\'abord une séance');
      return;
    }

    // Gather current page data into a text report
    const meetingTitle = document.getElementById('meetingTitle')?.textContent || 'Séance';
    const checksKpi = document.getElementById('kpiChecks')?.textContent || '—';
    const anomaliesKpi = document.getElementById('kpiAnomalies')?.textContent || '0';
    const motionsKpi = document.getElementById('kpiMotions')?.textContent || '—';
    const ballotsKpi = document.getElementById('kpiBallots')?.textContent || '—';
    const presentKpi = document.getElementById('kpiPresent')?.textContent || '—';
    const hashValue = document.getElementById('integrityHashValue')?.textContent || '—';

    const anomaliesText = currentAnomalies.map(a =>
      `  [${(a.severity || 'info').toUpperCase()}] ${a.title || a.type}: ${a.message || a.description}`
    ).join('\n') || '  Aucune anomalie détectée';

    const report = [
      '=== RAPPORT D\'INTÉGRITÉ — AG-VOTE ===',
      '',
      `Séance: ${meetingTitle}`,
      `Date du rapport: ${new Date().toLocaleString('fr-FR')}`,
      `Empreinte d'intégrité: ${hashValue}`,
      '',
      '--- Indicateurs ---',
      `Contrôles OK: ${checksKpi}`,
      `Anomalies: ${anomaliesKpi}`,
      `Résolutions: ${motionsKpi}`,
      `Bulletins: ${ballotsKpi}`,
      `Présents: ${presentKpi}`,
      '',
      '--- Anomalies ---',
      anomaliesText,
      '',
      '=== FIN DU RAPPORT ==='
    ].join('\n');

    const blob = new Blob([report], { type: 'text/plain;charset=utf-8' });
    const link = document.createElement('a');
    link.download = `rapport-integrite-${currentMeetingId}.txt`;
    link.href = URL.createObjectURL(blob);
    link.click();
    URL.revokeObjectURL(link.href);
    setNotif('success', 'Rapport d\'intégrité téléchargé');
  });

  // Polling (10s auto-refresh for anomaly detection, disabled when WebSocket connected)
  let pollingInterval = null;
  function startPolling() {
    if (pollingInterval) return;
    pollingInterval = setInterval(() => {
      // Skip polling if WebSocket is connected and authenticated
      if (typeof AgVoteWebSocket !== 'undefined' && window._wsClient?.isRealTime) return;
      if (!document.hidden && currentMeetingId) {
        loadMeetingData(currentMeetingId);
      }
    }, 10000);
  }
  window.addEventListener('beforeunload', () => { if (pollingInterval) clearInterval(pollingInterval); });

  // Initialize
  loadMeetings().then(() => startPolling());
})();
