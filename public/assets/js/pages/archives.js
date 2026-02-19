/** archives.js — Archives listing page for AG-VOTE. Must be loaded AFTER utils.js, shared.js and shell.js. */
(function() {
    'use strict';

    const archivesList = document.getElementById('archivesList');
    const searchInput = document.getElementById('searchInput');
    const yearFilter = document.getElementById('yearFilter');
    let allArchives = [];
    let currentView = 'cards';
    let currentYear = '';

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
        archivesList.innerHTML = Shared.emptyState({
          icon: 'archives',
          title: 'Aucune séance archivée',
          description: currentYear ? 'Aucune archive pour ' + currentYear : 'Les séances validées par le président apparaîtront ici'
        });
        return;
      }

      if (currentView === 'list') {
        renderListView(items);
      } else {
        renderCardView(items);
      }
    }

    // Card view rendering
    function renderCardView(items) {
      archivesList.innerHTML = items.map(m => {
        const id = m.id;
        const title = escapeHtml(m.title || id);
        const president = escapeHtml(m.president_name || '—');
        const archived = fmtDate(m.archived_at || m.validated_at);
        const hasReport = !!m.has_report;
        const sha = m.report_sha256 ? m.report_sha256.substring(0, 12) + '...' : '—';
        const motionsCount = m.motions_count || m.total_motions || '—';
        const ballotsCount = m.ballots_count || m.total_ballots || '—';

        const pvUrl = `/api/v1/meeting_report.php?meeting_id=${encodeURIComponent(id)}`;
        const auditUrl = `/api/v1/audit_export.php?meeting_id=${encodeURIComponent(id)}`;

        return `
          <div class="archive-card-enhanced">
            <div class="archive-card-header">
              <div>
                <div class="font-semibold text-lg">${title}</div>
                <div class="text-sm text-muted mt-1">
                  <span>${icon('gavel', 'icon-sm icon-muted')} ${president}</span>
                  <span class="mx-2">•</span>
                  <span>${icon('calendar', 'icon-sm icon-muted')} ${archived}</span>
                </div>
              </div>
              <div class="flex items-center gap-2">
                ${hasReport
                  ? `<span class="archive-badge has-pv">${icon('check-circle', 'icon-sm icon-success')} PV</span>`
                  : `<span class="archive-badge no-pv">${icon('clock', 'icon-sm')} PV en attente</span>`}
                <span class="badge badge-success">Archivée</span>
              </div>
            </div>
            <div class="archive-card-body">
              <div class="archive-info-grid">
                <div class="archive-info-item">
                  <div class="archive-info-value">${motionsCount}</div>
                  <div class="archive-info-label">Résolutions</div>
                </div>
                <div class="archive-info-item">
                  <div class="archive-info-value">${ballotsCount}</div>
                  <div class="archive-info-label">Bulletins</div>
                </div>
                <div class="archive-info-item">
                  <div class="archive-info-value">${m.present_count || '—'}</div>
                  <div class="archive-info-label">Présents</div>
                </div>
                <div class="archive-info-item">
                  <div class="archive-info-value">${m.proxy_count || '0'}</div>
                  <div class="archive-info-label">Procurations</div>
                </div>
              </div>
            </div>
            <div class="archive-card-footer">
              <div class="text-xs text-muted">
                ${hasReport ? `SHA: <code>${sha}</code>` : 'Intégrité non vérifiée'}
              </div>
              <div class="flex gap-2">
                ${hasReport ? `<a class="btn btn-primary btn-sm" href="${pvUrl}" target="_blank">${icon('file-text', 'icon-sm icon-text')}PV</a>` : ''}
                <a class="btn btn-secondary btn-sm" href="${auditUrl}" target="_blank">${icon('shield-check', 'icon-sm icon-text')}Audit</a>
                <button class="btn btn-ghost btn-sm btn-view-details" data-id="${id}">Détails</button>
              </div>
            </div>
          </div>
        `;
      }).join('');
    }

    // List view rendering
    function renderListView(items) {
      archivesList.innerHTML = `
        <table class="table" style="width:100%">
          <thead>
            <tr>
              <th>Titre</th>
              <th>Président</th>
              <th>Date d'archive</th>
              <th>Résolutions</th>
              <th>Bulletins</th>
              <th>PV</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            ${items.map(m => {
              const id = m.id;
              const title = escapeHtml(m.title || id);
              const president = escapeHtml(m.president_name || '—');
              const archived = fmtDate(m.archived_at || m.validated_at);
              const hasReport = !!m.has_report;
              const motionsCount = m.motions_count || m.total_motions || '—';
              const ballotsCount = m.ballots_count || m.total_ballots || '—';

              const pvUrl = `/api/v1/meeting_report.php?meeting_id=${encodeURIComponent(id)}`;
              const auditUrl = `/api/v1/audit_export.php?meeting_id=${encodeURIComponent(id)}`;

              return `
                <tr>
                  <td class="font-medium">${title}</td>
                  <td>${president}</td>
                  <td class="text-sm">${archived}</td>
                  <td class="text-center">${motionsCount}</td>
                  <td class="text-center">${ballotsCount}</td>
                  <td class="text-center">
                    ${hasReport
                      ? `<span class="text-success">${icon('check-circle', 'icon-sm icon-success')}</span>`
                      : '<span class="text-muted">—</span>'}
                  </td>
                  <td>
                    <div class="flex gap-1">
                      ${hasReport ? `<a class="btn btn-ghost btn-xs" href="${pvUrl}" target="_blank">PV</a>` : ''}
                      <a class="btn btn-ghost btn-xs" href="${auditUrl}" target="_blank">Audit</a>
                    </div>
                  </td>
                </tr>
              `;
            }).join('')}
          </tbody>
        </table>
      `;
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

          // Average participation rate
          const participationRates = allArchives
            .map(a => parseFloat(a.participation_rate))
            .filter(v => !isNaN(v));
          const avgParticipation = participationRates.length > 0
            ? Math.round(participationRates.reduce((sum, v) => sum + v, 0) / participationRates.length)
            : null;
          document.getElementById('kpiAvgParticipation').textContent =
            avgParticipation != null ? avgParticipation + ' %' : '—';

          // Date range (period)
          const dates = allArchives
            .map(a => new Date(a.archived_at || a.validated_at))
            .filter(d => !isNaN(d.getTime()))
            .sort((a, b) => a - b);
          if (dates.length > 0) {
            const fmt = d => d.toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
            const minDate = fmt(dates[0]);
            const maxDate = fmt(dates[dates.length - 1]);
            document.getElementById('kpiDateRange').textContent =
              minDate === maxDate ? minDate : minDate + ' — ' + maxDate;
          } else {
            document.getElementById('kpiDateRange').textContent = '—';
          }

          // Calculate aggregate stats
          const totalMotions = allArchives.reduce((sum, a) => sum + (parseInt(a.motions_count) || parseInt(a.total_motions) || 0), 0);
          const totalBallots = allArchives.reduce((sum, a) => sum + (parseInt(a.ballots_count) || parseInt(a.total_ballots) || 0), 0);

          document.getElementById('statTotal').textContent = total;
          document.getElementById('statWithPV').textContent = withPV;
          document.getElementById('statMotions').textContent = totalMotions || '—';
          document.getElementById('statBallots').textContent = totalBallots || '—';

          // Populate year filter
          populateYearFilter();

          // Populate export select
          const exportSelect = document.getElementById('exportMeetingSelect');
          exportSelect.innerHTML = '<option value="">— Sélectionner une séance —</option>';
          allArchives.forEach(a => {
            const opt = document.createElement('option');
            opt.value = a.id;
            opt.textContent = a.title || a.id;
            exportSelect.appendChild(opt);
          });

          // Apply filters and render
          applyFilters();
        } else {
          render([]);
        }
      } catch (err) {
        archivesList.innerHTML = `
          <div class="alert alert-danger">
            <span>${icon('x-circle', 'icon-md icon-danger')}</span>
            <span>Erreur de chargement: ${escapeHtml(err.message)}</span>
          </div>
        `;
      }
    }

    // Populate year filter dropdown
    function populateYearFilter() {
      const years = new Set();
      allArchives.forEach(a => {
        const date = new Date(a.archived_at || a.validated_at);
        if (!isNaN(date.getTime())) {
          years.add(date.getFullYear());
        }
      });

      const sortedYears = Array.from(years).sort((a, b) => b - a);
      yearFilter.innerHTML = '<option value="">Toutes années</option>';
      sortedYears.forEach(year => {
        const opt = document.createElement('option');
        opt.value = year;
        opt.textContent = year;
        yearFilter.appendChild(opt);
      });
    }

    // Apply all filters
    function applyFilters() {
      const query = searchInput.value.toLowerCase().trim();
      let filtered = allArchives;

      // Year filter
      if (currentYear) {
        filtered = filtered.filter(a => {
          const date = new Date(a.archived_at || a.validated_at);
          return date.getFullYear() === parseInt(currentYear);
        });
      }

      // Search filter
      if (query) {
        filtered = filtered.filter(a =>
          (a.title || '').toLowerCase().includes(query) ||
          (a.president_name || '').toLowerCase().includes(query)
        );
      }

      render(filtered);
    }

    // Search filter
    searchInput.addEventListener('input', applyFilters);

    // Year filter
    yearFilter.addEventListener('change', () => {
      currentYear = yearFilter.value;
      applyFilters();
    });

    // View toggle
    document.querySelectorAll('.view-toggle-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.view-toggle-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentView = btn.dataset.view;
        applyFilters();
      });
    });

    // Details button click (delegated)
    archivesList.addEventListener('click', async (e) => {
      const btn = e.target.closest('.btn-view-details');
      if (!btn) return;

      const meetingId = btn.dataset.id;
      const archive = allArchives.find(a => a.id === meetingId);
      if (!archive) return;

      // Show details modal
      Shared.openModal({
        title: archive.title || 'Détails de la séance',
        body: `
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div><strong>Président:</strong> ${escapeHtml(archive.president_name || '—')}</div>
            <div><strong>Date d'archivage:</strong> ${fmtDate(archive.archived_at || archive.validated_at)}</div>
            <div><strong>Résolutions:</strong> ${archive.motions_count || archive.total_motions || '—'}</div>
            <div><strong>Bulletins:</strong> ${archive.ballots_count || archive.total_ballots || '—'}</div>
            <div><strong>Présents:</strong> ${archive.present_count || '—'}</div>
            <div><strong>Procurations:</strong> ${archive.proxy_count || '0'}</div>
          </div>
          ${archive.has_report ? `
            <div class="alert alert-success mb-4">
              <span>${icon('check-circle', 'icon-md icon-success')}</span>
              <span>Procès-verbal disponible</span>
            </div>
            <div class="text-xs text-muted">
              <strong>SHA-256:</strong><br>
              <code style="word-break:break-all">${archive.report_sha256 || '—'}</code>
            </div>
          ` : `
            <div class="alert alert-warning">
              <span>${icon('clock', 'icon-md icon-warning')}</span>
              <span>Procès-verbal non encore généré</span>
            </div>
          `}
        `,
        confirmText: 'Fermer',
        hideCancel: true
      });
    });

    // Refresh
    document.getElementById('btnRefresh').addEventListener('click', loadArchives);

    // Exports modal
    const modal = document.getElementById('exportsModal');
    const backdrop = document.getElementById('exportsBackdrop');

    function openExportsModal() {
      Shared.show(modal, 'block');
      Shared.show(backdrop, 'block');
      document.body.style.overflow = 'hidden';
    }

    function closeExportsModal() {
      Shared.hide(modal);
      Shared.hide(backdrop);
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
