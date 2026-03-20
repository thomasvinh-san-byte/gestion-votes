/** archives.js — Archives listing page for AG-VOTE. Must be loaded AFTER utils.js, shared.js and shell.js. */
(function() {
  'use strict';

  const archivesList = document.getElementById('archivesList');
  const searchInput = document.getElementById('searchInput');
  const yearFilter = document.getElementById('yearFilter');
  var allArchives = [];
  var filteredArchives = [];
  var currentView = 'cards';
  var currentYear = '';
  var currentPage = 1;
  var perPage = 5;
  var currentStatusFilter = '';

  // Meeting type labels
  var TYPE_LABELS = {
    ag_ordinaire: 'AG Ordinaire',
    ag_extraordinaire: 'AG Extraordinaire',
    conseil: 'Conseil d\'administration'
  };

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

  // Reset all filters
  function resetFilters() {
    searchInput.value = '';
    yearFilter.value = '';
    currentYear = '';
    // Reset type filter
    document.querySelectorAll('#archiveTypeFilter .filter-tab').forEach(function(t) { t.classList.remove('active'); });
    var allTypeTab = document.querySelector('#archiveTypeFilter .filter-tab[data-type=""]');
    if (allTypeTab) allTypeTab.classList.add('active');
    currentType = '';
    // Reset status filter
    document.querySelectorAll('#archiveStatusFilter .filter-tab').forEach(function(t) { t.classList.remove('active'); });
    var allStatusTab = document.querySelector('#archiveStatusFilter .filter-tab[data-status=""]');
    if (allStatusTab) allStatusTab.classList.add('active');
    currentStatusFilter = '';
    currentPage = 1;
    applyFilters();
  }

  // Expose resetFilters globally for inline onclick
  window.resetFilters = resetFilters;

  // Render archives (paginated)
  function render(items) {
    var paginationEl = document.getElementById('archivesPagination');
    if (!items || items.length === 0) {
      var query = searchInput.value.trim();
      var hasActiveFilters = query || currentYear || currentType || currentStatusFilter;
      if (hasActiveFilters) {
        archivesList.innerHTML = '<ag-empty-state icon="archive" title="Aucune s\u00e9ance trouv\u00e9e" description="Aucun r\u00e9sultat ne correspond aux filtres s\u00e9lectionn\u00e9s."><button class="btn btn-secondary btn-sm" onclick="resetFilters()">Effacer les filtres</button></ag-empty-state>';
      } else {
        archivesList.innerHTML = '<ag-empty-state icon="archive" title="Aucune s\u00e9ance archiv\u00e9e" description="' + (currentYear ? 'Aucune archive pour ' + currentYear : 'Les s\u00e9ances valid\u00e9es et archiv\u00e9es apparaissent ici.') + '"></ag-empty-state>';
      }
      if (paginationEl) paginationEl.innerHTML = '';
      return;
    }

    // Slice for current page
    var totalPages = Math.max(1, Math.ceil(items.length / perPage));
    if (currentPage > totalPages) currentPage = totalPages;
    var start = (currentPage - 1) * perPage;
    var pageItems = items.slice(start, start + perPage);

    if (currentView === 'list') {
      renderListView(pageItems);
    } else {
      renderCardView(pageItems);
    }

    // Render pagination
    renderPaginationControls(items.length, totalPages, paginationEl);
  }

  // Render pagination controls
  function renderPaginationControls(total, totalPages, paginationEl) {
    if (!paginationEl) return;
    if (totalPages <= 1) {
      paginationEl.innerHTML = '';
      return;
    }

    var html = '';
    html += '<button class="btn btn-sm btn-ghost" data-page="' + (currentPage - 1) + '"' +
            (currentPage <= 1 ? ' disabled' : '') + '>&#8249; Préc.</button>';
    for (var i = 1; i <= totalPages; i++) {
      var active = i === currentPage ? ' btn-primary' : ' btn-ghost';
      html += '<button class="btn btn-sm' + active + '" data-page="' + i + '">' + i + '</button>';
    }
    html += '<button class="btn btn-sm btn-ghost" data-page="' + (currentPage + 1) + '"' +
            (currentPage >= totalPages ? ' disabled' : '') + '>Suiv. &#8250;</button>';

    paginationEl.innerHTML = html;

    var btns = paginationEl.querySelectorAll('button[data-page]');
    btns.forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        if (e.target.disabled) return;
        var page = parseInt(e.target.dataset.page);
        if (!isNaN(page) && page >= 1 && page <= totalPages) {
          currentPage = page;
          render(filteredArchives);
        }
      });
    });
  }

  // Card view rendering
  function renderCardView(items) {
    archivesList.innerHTML = items.map(function(m) {
      var id = m.id;
      var title = escapeHtml(m.title || id);
      var president = escapeHtml(m.president_name || '—');
      var archived = fmtDate(m.archived_at || m.validated_at);
      var hasReport = !!m.has_report;
      var sha = m.report_sha256 ? m.report_sha256.substring(0, 12) + '...' : '—';
      var motionsCount = m.motions_count || m.total_motions || '—';
      var ballotsCount = m.ballots_count || m.total_ballots || '—';
      var meetingType = escapeHtml(TYPE_LABELS[m.meeting_type] || m.meeting_type || '—');
      var resolutionSummary = escapeHtml(m.resolution_summary || (motionsCount !== '—' ? motionsCount + ' résolution(s)' : '—'));
      var status = escapeHtml(m.status || 'archived');

      var pvUrl = '/api/v1/meeting_report.php?meeting_id=' + encodeURIComponent(id);
      var auditUrl = '/api/v1/audit_export.php?meeting_id=' + encodeURIComponent(id);

      return '<div class="archive-card-enhanced" data-status="' + status + '">' +
          '<div class="archive-card-header">' +
            '<div>' +
              '<div class="font-semibold text-lg">' + title + '</div>' +
              '<div class="text-sm text-muted mt-1">' +
                '<span>' + icon('gavel', 'icon-sm icon-muted') + ' ' + president + '</span>' +
                '<span class="mx-2">•</span>' +
                '<span>' + icon('calendar', 'icon-sm icon-muted') + ' <span class="archive-date">' + archived + '</span></span>' +
                '<span class="mx-2">•</span>' +
                '<span class="tag tag-ghost">' + meetingType + '</span>' +
              '</div>' +
            '</div>' +
            '<div class="flex items-center gap-2">' +
              (hasReport
                ? '<span class="archive-badge has-pv">' + icon('check-circle', 'icon-sm icon-success') + ' PV</span>'
                : '<span class="archive-badge no-pv">' + icon('clock', 'icon-sm') + ' PV en attente</span>') +
              '<span class="badge badge-success">Archiv\u00e9e</span>' +
            '</div>' +
          '</div>' +
          '<div class="archive-card-body">' +
            '<div class="archive-info-grid">' +
              '<div class="archive-info-item">' +
                '<div class="archive-info-value">' + escapeHtml(String(motionsCount)) + '</div>' +
                '<div class="archive-info-label">R\u00e9solutions</div>' +
              '</div>' +
              '<div class="archive-info-item">' +
                '<div class="archive-info-value">' + escapeHtml(String(ballotsCount)) + '</div>' +
                '<div class="archive-info-label">Bulletins</div>' +
              '</div>' +
              '<div class="archive-info-item">' +
                '<div class="archive-info-value">' + escapeHtml(String(m.present_count || '—')) + '</div>' +
                '<div class="archive-info-label">Pr\u00e9sents</div>' +
              '</div>' +
              '<div class="archive-info-item">' +
                '<div class="archive-info-value">' + escapeHtml(String(m.proxy_count || '0')) + '</div>' +
                '<div class="archive-info-label">Procurations</div>' +
              '</div>' +
            '</div>' +
            '<div class="text-sm text-muted mt-2">' +
              icon('clipboard-list', 'icon-sm icon-muted') + ' ' + resolutionSummary +
            '</div>' +
          '</div>' +
          '<div class="archive-card-footer">' +
            '<div class="text-xs text-muted">' +
              (hasReport ? 'SHA: <span class="archive-sha">' + escapeHtml(sha) + '</span>' : 'Int\u00e9grit\u00e9 non v\u00e9rifi\u00e9e') +
            '</div>' +
            '<div class="archive-card-actions flex gap-2">' +
              (hasReport ? '<a class="btn btn-primary btn-sm" href="' + pvUrl + '" target="_blank">' + icon('file-text', 'icon-sm icon-text') + 'PV</a>' : '') +
              '<a class="btn btn-secondary btn-sm" href="' + auditUrl + '" target="_blank">' + icon('shield-check', 'icon-sm icon-text') + 'Audit</a>' +
              '<button class="btn btn-ghost btn-sm btn-view-details" data-id="' + escapeHtml(id) + '">D\u00e9tails</button>' +
            '</div>' +
          '</div>' +
        '</div>';
    }).join('');
  }

  // List view rendering
  function renderListView(items) {
    archivesList.innerHTML = `
        <table class="table archive-list-table">
          <thead>
            <tr>
              <th><ag-tooltip text="Titre de la seance archivee" position="bottom">Titre</ag-tooltip></th>
              <th><ag-tooltip text="Nom du president de seance" position="bottom">Pr\u00e9sident</ag-tooltip></th>
              <th><ag-tooltip text="Date d'archivage ou de validation de la seance" position="bottom">Date d'archive</ag-tooltip></th>
              <th><ag-tooltip text="Nombre de r\u00e9solutions vot\u00e9es" position="bottom">R\u00e9solutions</ag-tooltip></th>
              <th><ag-tooltip text="Nombre de bulletins de vote enregistr\u00e9s" position="bottom">Bulletins</ag-tooltip></th>
              <th><ag-tooltip text="Proc\u00e8s-verbal disponible" position="bottom">PV</ag-tooltip></th>
              <th><ag-tooltip text="Actions disponibles pour cette s\u00e9ance" position="bottom">Actions</ag-tooltip></th>
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
                  <td><span class="archive-date">${archived}</span></td>
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

  // Reset KPIs to placeholder
  function resetKPIs() {
    ['kpiTotal', 'kpiWithPV', 'kpiThisYear', 'kpiAvgParticipation', 'kpiDateRange'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.textContent = '—';
    });
  }

  // Update type filter count badges
  function updateTypeFilterCounts(archives) {
    var counts = { '': archives.length };
    archives.forEach(function(a) {
      var t = (a.meeting_type || '').toLowerCase();
      if (counts[t] === undefined) counts[t] = 0;
      counts[t]++;
    });
    document.querySelectorAll('#archiveTypeFilter .filter-tab').forEach(function(btn) {
      var type = btn.dataset.type;
      var span = btn.querySelector('.count');
      if (!span) { span = document.createElement('span'); span.className = 'count'; btn.appendChild(span); }
      span.textContent = counts[type !== undefined ? type : ''] || 0;
    });
  }

  // Load archives with retry
  async function loadArchives() {
    archivesList.innerHTML = `
        <div class="text-center p-6">
          <div class="spinner"></div>
          <div class="mt-4 text-muted">Chargement des archives...</div>
        </div>
      `;

    await Shared.withRetry({
      container: archivesList,
      maxRetries: 1,
      errorMsg: 'Impossible de charger les archives',
      action: async function () {
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

          // Populate year filter
          populateYearFilter();

          // Update type filter count badges
          updateTypeFilterCounts(allArchives);

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
          allArchives = [];
          resetKPIs();
          render([]);
        }
      }
    });
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
    var query = searchInput.value.toLowerCase().trim();
    var filtered = allArchives;

    // Type filter
    if (currentType) {
      filtered = filtered.filter(function(a) {
        return (a.meeting_type || '').toLowerCase() === currentType.toLowerCase();
      });
    }

    // Status filter
    if (currentStatusFilter) {
      filtered = filtered.filter(function(a) {
        return (a.status || '') === currentStatusFilter;
      });
    }

    // Year filter
    if (currentYear) {
      filtered = filtered.filter(function(a) {
        var date = new Date(a.archived_at || a.validated_at);
        return date.getFullYear() === parseInt(currentYear);
      });
    }

    // Search filter
    if (query) {
      filtered = filtered.filter(function(a) {
        return (a.title || '').toLowerCase().indexOf(query) !== -1 ||
               (a.president_name || '').toLowerCase().indexOf(query) !== -1;
      });
    }

    filteredArchives = filtered;
    currentPage = 1;
    render(filteredArchives);
  }

  // Type filter tabs
  let currentType = '';
  document.querySelectorAll('#archiveTypeFilter .filter-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('#archiveTypeFilter .filter-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      currentType = tab.dataset.type || '';
      currentPage = 1;
      applyFilters();
    });
  });

  // Status filter tabs
  document.querySelectorAll('#archiveStatusFilter .filter-tab').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelector('#archiveStatusFilter .filter-tab.active').classList.remove('active');
      btn.classList.add('active');
      currentStatusFilter = btn.dataset.status || '';
      currentPage = 1;
      applyFilters();
    });
  });

  // Search filter
  searchInput.addEventListener('input', function() {
    currentPage = 1;
    applyFilters();
  });

  // Year filter
  yearFilter.addEventListener('change', () => {
    currentYear = yearFilter.value;
    currentPage = 1;
    applyFilters();
  });

  // Pagination
  var archivesPager = document.getElementById('archivesPager');
  if (archivesPager) {
    archivesPager.addEventListener('ag-page-change', function(e) {
      currentPage = e.detail.page;
      applyFilters();
    });
  }

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

  var previousFocusExport = null;

  function openExportsModal() {
    previousFocusExport = document.activeElement;
    Shared.show(modal, 'block');
    Shared.show(backdrop, 'block');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    // Focus first interactive element
    var firstFocusable = modal.querySelector('select, button, input');
    if (firstFocusable) setTimeout(function () { firstFocusable.focus(); }, 50);
  }

  function closeExportsModal() {
    Shared.hide(modal);
    Shared.hide(backdrop);
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    // Restore focus
    if (previousFocusExport && previousFocusExport.focus) {
      try { previousFocusExport.focus(); } catch (e) {}
    }
  }

  document.getElementById('btnExportsModal').addEventListener('click', openExportsModal);
  document.getElementById('btnCloseExports').addEventListener('click', closeExportsModal);
  document.getElementById('btnCloseExports2').addEventListener('click', closeExportsModal);
  backdrop.addEventListener('click', closeExportsModal);

  // Close exports modal with Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.style.display !== 'none') {
      closeExportsModal();
    }
  });

  // Export functions
  function getSelectedMeetingId() {
    const select = document.getElementById('exportMeetingSelect');
    return select.value;
  }

  function doExport(endpoint) {
    const meetingId = getSelectedMeetingId();
    if (!meetingId) {
      var select = document.getElementById('exportMeetingSelect');
      Shared.fieldError(select, 'Sélectionnez une séance avant d\u2019exporter');
      return;
    }
    Shared.fieldClear(document.getElementById('exportMeetingSelect'));
    window.open(`/api/v1/${endpoint}?meeting_id=${encodeURIComponent(meetingId)}`, '_blank');
  }

  document.getElementById('btnExportPV').addEventListener('click', () => doExport('meeting_report.php'));
  document.getElementById('btnExportAttendance').addEventListener('click', () => doExport('export_attendance_csv.php'));
  document.getElementById('btnExportVotes').addEventListener('click', () => doExport('export_votes_csv.php'));
  document.getElementById('btnExportMotions').addEventListener('click', () => doExport('export_motions_results_csv.php'));
  document.getElementById('btnExportMembers').addEventListener('click', () => doExport('export_members_csv.php'));
  document.getElementById('btnExportAudit').addEventListener('click', () => doExport('audit_export.php'));
  document.getElementById('btnExportZip')?.addEventListener('click', () => doExport('export_full_xlsx.php'));

  // Initial load
  loadArchives();
})();
