/** archives.js — Archives listing page for AG-VOTE. Must be loaded AFTER utils.js, shared.js and shell.js. */
(function() {
    'use strict';

    const archivesList = document.getElementById('archivesList');
    const searchInput = document.getElementById('searchInput');
    let allArchives = [];

    // Format date
    function fmtDate(s) {
      if (!s) return '—';
      try {
        return new Date(s).toLocaleString('fr-FR', {
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        });
      } catch (e) {
        return s;
      }
    }

    // Render archives
    function render(items) {
      if (!items || items.length === 0) {
        archivesList.innerHTML = `
          <div class="empty-state p-8">
            <div class="empty-state-icon">📭</div>
            <div class="empty-state-title">Aucune séance archivée</div>
            <div class="empty-state-description">
              Les séances validées par le président apparaîtront ici
            </div>
          </div>
        `;
        return;
      }

      archivesList.innerHTML = items.map(m => {
        const id = m.id;
        const title = escapeHtml(m.title || id);
        const president = escapeHtml(m.president_name || '—');
        const archived = fmtDate(m.archived_at || m.validated_at);
        const hasReport = !!m.has_report;
        const sha = m.report_sha256 ? m.report_sha256.substring(0, 16) + '...' : '—';
        const reportAt = fmtDate(m.report_generated_at);

        const pvUrl = `/api/v1/meeting_report.php?meeting_id=${encodeURIComponent(id)}`;
        const auditUrl = `/api/v1/audit_export.php?meeting_id=${encodeURIComponent(id)}`;

        return `
          <div class="archive-card">
            <div class="archive-header">
              <div>
                <div class="archive-title">${title}</div>
                <div class="archive-meta">
                  <span class="archive-meta-item">
                    <span>🧑‍⚖️</span>
                    <span>${president}</span>
                  </span>
                  <span class="archive-meta-item">
                    <span>📅</span>
                    <span>${archived}</span>
                  </span>
                  ${hasReport ? `
                    <span class="archive-meta-item">
                      <span>✅</span>
                      <span>PV disponible</span>
                    </span>
                  ` : ''}
                </div>
              </div>
              <span class="badge badge-success">Archivée</span>
            </div>

            <div class="archive-footer">
              <div>
                ${hasReport ? `
                  <div class="text-xs text-muted mb-1">SHA-256:</div>
                  <div class="archive-sha">${sha}</div>
                ` : `
                  <span class="text-sm text-muted">PV non généré</span>
                `}
              </div>
              <div class="flex gap-2">
                ${hasReport ? `
                  <a class="btn btn-primary" href="${pvUrl}" target="_blank">
                    📋 Voir le PV
                  </a>
                ` : ''}
                <a class="btn btn-secondary" href="${auditUrl}" target="_blank">
                  📜 Audit CSV
                </a>
              </div>
            </div>
          </div>
        `;
      }).join('');
    }

    // Load archives
    async function loadArchives() {
      archivesList.innerHTML = `
        <div class="text-center p-6">
          <div class="spinner"></div>
          <div class="mt-4 text-muted">Chargement des archives...</div>
        </div>
      `;

      try {
        const { body } = await api('/api/v1/archives_list.php');

        if (body && (body.data || body.items)) {
          allArchives = body.data?.items || body.items || body.data || [];
          render(allArchives);

          // Update KPIs
          const total = allArchives.length;
          const withPV = allArchives.filter(a => a.has_report).length;
          const thisYear = allArchives.filter(a => {
            const date = new Date(a.archived_at || a.validated_at);
            return date.getFullYear() === new Date().getFullYear();
          }).length;

          document.getElementById('kpiTotal').textContent = total;
          document.getElementById('kpiWithPV').textContent = withPV;
          document.getElementById('kpiThisYear').textContent = thisYear;

          // Populate export select
          const exportSelect = document.getElementById('exportMeetingSelect');
          exportSelect.innerHTML = '<option value="">— Sélectionner une séance —</option>';
          allArchives.forEach(a => {
            const opt = document.createElement('option');
            opt.value = a.id;
            opt.textContent = a.title || a.id;
            exportSelect.appendChild(opt);
          });
        } else {
          render([]);
        }
      } catch (err) {
        archivesList.innerHTML = `
          <div class="alert alert-danger">
            <span>❌</span>
            <span>Erreur de chargement: ${escapeHtml(err.message)}</span>
          </div>
        `;
      }
    }

    // Search filter
    searchInput.addEventListener('input', () => {
      const query = searchInput.value.toLowerCase().trim();
      if (!query) {
        render(allArchives);
        return;
      }

      const filtered = allArchives.filter(a =>
        (a.title || '').toLowerCase().includes(query) ||
        (a.president_name || '').toLowerCase().includes(query)
      );
      render(filtered);
    });

    // Refresh
    document.getElementById('btnRefresh').addEventListener('click', loadArchives);

    // Exports modal
    const modal = document.getElementById('exportsModal');
    const backdrop = document.getElementById('exportsBackdrop');

    function openExportsModal() {
      modal.style.display = 'block';
      backdrop.style.display = 'block';
      document.body.style.overflow = 'hidden';
    }

    function closeExportsModal() {
      modal.style.display = 'none';
      backdrop.style.display = 'none';
      document.body.style.overflow = '';
    }

    document.getElementById('btnExportsModal').addEventListener('click', openExportsModal);
    document.getElementById('btnCloseExports').addEventListener('click', closeExportsModal);
    document.getElementById('btnCloseExports2').addEventListener('click', closeExportsModal);
    backdrop.addEventListener('click', closeExportsModal);

    // Export functions
    function getSelectedMeetingId() {
      const select = document.getElementById('exportMeetingSelect');
      return select.value;
    }

    function doExport(endpoint) {
      const meetingId = getSelectedMeetingId();
      if (!meetingId) {
        setNotif('error', 'Sélectionnez d\'abord une séance');
        return;
      }
      window.open(`/api/v1/${endpoint}?meeting_id=${meetingId}`, '_blank');
    }

    document.getElementById('btnExportPV').addEventListener('click', () => doExport('meeting_report.php'));
    document.getElementById('btnExportAttendance').addEventListener('click', () => doExport('attendance_export.php'));
    document.getElementById('btnExportVotes').addEventListener('click', () => doExport('votes_export.php'));
    document.getElementById('btnExportMotions').addEventListener('click', () => doExport('motions_export.php'));
    document.getElementById('btnExportMembers').addEventListener('click', () => doExport('members_export.php'));
    document.getElementById('btnExportAudit').addEventListener('click', () => doExport('audit_export.php'));

    // Initial load
    loadArchives();
  })();
