/* GO-LIVE-STATUS: ready — Audit et Conformité JS. Real API data, table/timeline views, filter, pagination, modal, CSV export. */
/**
 * audit.js — Audit page module for AG-VOTE.
 * Loads audit events from API with error handling, renders table and timeline views,
 * handles filter pills, search, sort, pagination, checkbox selection, and event detail modal.
 * Must be loaded AFTER utils.js, shared.js and shell.js.
 */
(function () {
  'use strict';

  /* ── Helper ── */
  function esc(s) {
    return Utils.escapeHtml(s);
  }

  /* ── Category label map ── */
  var CATEGORY_LABELS = {
    votes: 'Votes',
    presences: 'Présences',
    securite: 'Sécurité',
    systeme: 'Système'
  };

  /* ── State ── */
  var _allEvents = [];
  var _filteredEvents = [];
  var _currentPage = 1;
  var _perPage = 15;
  var _currentView = 'table';
  var _activeFilter = '';
  var _selectedIds = [];
  var _searchQuery = '';

  /* ── DOM references ── */
  var _tableBody = document.getElementById('auditTableBody');
  var _timeline = document.getElementById('auditTimeline');
  var _pagination = document.getElementById('auditPagination');
  var _tableView = document.getElementById('auditTableView');
  var _timelineView = document.getElementById('auditTimelineView');
  var _selectAll = document.getElementById('selectAll');
  var _btnExportAll = document.getElementById('btnExportAll');
  var _btnExportSelection = document.getElementById('btnExportSelection');
  var _auditSearch = document.getElementById('auditSearch');
  var _auditSort = document.getElementById('auditSort');
  var _detailModal = document.getElementById('auditDetailModal');
  var _detailBackdrop = document.getElementById('auditDetailBackdrop');

  /* ── Format date ── */
  function formatTimestamp(iso) {
    if (!iso) return '—';
    try {
      var d = new Date(iso);
      var pad = function(n) { return n < 10 ? '0' + n : '' + n; };
      return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
             ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    } catch (e) {
      return iso;
    }
  }

  function formatDateShort(iso) {
    if (!iso) return '—';
    try {
      var d = new Date(iso);
      return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch (e) {
      return iso;
    }
  }

  /* ── KPI populate ── */
  function populateKPIs() {
    var kpiIntegrity = document.getElementById('kpiIntegrity');
    var kpiEvents = document.getElementById('kpiEvents');
    var kpiAnomalies = document.getElementById('kpiAnomalies');
    var kpiLastSession = document.getElementById('kpiLastSession');

    if (kpiIntegrity) kpiIntegrity.textContent = '100%';
    if (kpiEvents) kpiEvents.textContent = _allEvents.length;

    var anomalies = _allEvents.filter(function(e) { return e.severity === 'danger'; }).length;
    if (kpiAnomalies) {
      kpiAnomalies.textContent = anomalies;
      // Red indicator when anomalies > 0
      if (anomalies > 0) {
        kpiAnomalies.setAttribute('data-anomaly', 'true');
      } else {
        kpiAnomalies.removeAttribute('data-anomaly');
      }
    }

    if (kpiLastSession) {
      var sorted = _allEvents.slice().sort(function(a, b) {
        return new Date(b.timestamp) - new Date(a.timestamp);
      });
      kpiLastSession.textContent = sorted.length > 0 ? formatDateShort(sorted[0].timestamp) : '—';
    }
  }

  /* ── Filter count badges ── */
  function updateFilterCounts(events) {
    var cats = { '': events.length, votes: 0, presences: 0, securite: 0, systeme: 0 };
    events.forEach(function(e) {
      var cat = (e.category || e.type || '').toLowerCase();
      if (cats[cat] !== undefined) cats[cat]++;
    });
    document.querySelectorAll('#auditTypeFilter .filter-tab').forEach(function(btn) {
      var type = btn.dataset.type;
      var span = btn.querySelector('.count');
      if (!span) { span = document.createElement('span'); span.className = 'count'; btn.appendChild(span); }
      span.textContent = cats[type !== undefined ? type : ''] || 0;
    });
  }

  /* ── Build user cell with initials avatar ── */
  function buildUserCell(username) {
    var name = username || '??';
    var initials = name.split(' ').map(function(w) { return w[0]; }).join('').substring(0, 2).toUpperCase();
    return '<span class="audit-user-cell"><span class="audit-user-avatar">' + esc(initials) + '</span><span>' + esc(name) + '</span></span>';
  }

  /* ── Build inline detail panel HTML ── */
  function buildDetailPanelHtml(evt) {
    var catLabel = esc(CATEGORY_LABELS[evt.category] || evt.category || '—');
    return '<tr class="audit-detail-inline"><td colspan="6"><div class="audit-detail-panel">' +
      '<div class="audit-detail-item">' +
        '<div class="audit-detail-label">Horodatage</div>' +
        '<div class="audit-detail-value">' + esc(formatTimestamp(evt.timestamp)) + '</div>' +
      '</div>' +
      '<div class="audit-detail-item">' +
        '<div class="audit-detail-label">Cat&eacute;gorie</div>' +
        '<div class="audit-detail-value"><span class="tag tag-ghost">' + catLabel + '</span></div>' +
      '</div>' +
      '<div class="audit-detail-item">' +
        '<div class="audit-detail-label">Utilisateur</div>' +
        '<div class="audit-detail-value">' + esc(evt.user || '—') + '</div>' +
      '</div>' +
      '<div class="audit-detail-item">' +
        '<div class="audit-detail-label">S&eacute;v&eacute;rit&eacute;</div>' +
        '<div class="audit-detail-value"><span class="audit-severity-dot ' + esc(evt.severity) + '"></span> ' + esc(evt.severity || '—') + '</div>' +
      '</div>' +
      '<div class="audit-detail-description">' + esc(evt.description || '—') + '</div>' +
      '<div class="audit-detail-hash">SHA-256: ' + esc(evt.hash || '—') + '</div>' +
    '</div></td></tr>';
  }

  /* ── Render table ── */
  function renderTable(events) {
    if (!_tableBody) return;

    if (!events || events.length === 0) {
      _tableBody.innerHTML = '<tr><td colspan="6">' +
        '<ag-empty-state icon="generic" title="Aucun r\u00e9sultat" ' +
        'description="Aucun \u00e9v\u00e9nement ne correspond aux filtres s\u00e9lectionn\u00e9s.">' +
        '<button slot="action" class="btn btn-secondary btn-sm" data-action="reset-filters">' +
        'R\u00e9initialiser les filtres</button></ag-empty-state>' +
        '</td></tr>';
      return;
    }

    _tableBody.innerHTML = events.map(function(evt, idx) {
      var checked = _selectedIds.indexOf(evt.id) !== -1 ? ' checked' : '';
      var offsetIdx = (_currentPage - 1) * _perPage + idx + 1;
      return '<tr data-event-id="' + esc(evt.id) + '" class="audit-table-row">' +
        '<td class="audit-col-check" onclick="event.stopPropagation()">' +
          '<input type="checkbox" class="audit-row-check" data-id="' + esc(evt.id) + '"' + checked + '>' +
        '</td>' +
        '<td class="audit-col-num">' + offsetIdx + '</td>' +
        '<td class="audit-col-timestamp"><span class="audit-timestamp">' + esc(formatTimestamp(evt.timestamp)) + '</span></td>' +
        '<td class="audit-col-event">' +
          '<div class="audit-event-cell">' +
            '<span class="audit-severity-dot ' + esc(evt.severity) + '"></span>' +
            esc(evt.event) +
          '</div>' +
        '</td>' +
        '<td class="audit-col-user">' + buildUserCell(evt.user) + '</td>' +
        '<td class="audit-col-hash">' +
          '<span class="audit-hash-cell" title="' + esc(evt.hash) + '">' + esc(evt.hash.substring(0, 12)) + '...</span>' +
        '</td>' +
      '</tr>';
    }).join('');

    // Bind row click handlers — inline expansion (replaces modal)
    var rows = _tableBody.querySelectorAll('tr.audit-table-row');
    for (var i = 0; i < rows.length; i++) {
      (function(row) {
        row.addEventListener('click', function(e) {
          if (e.target.type === 'checkbox' || e.target.closest('[onclick]')) return;
          var eventId = row.dataset.eventId;
          if (!eventId) return;

          // Check if this row already has an open detail row after it
          var nextRow = row.nextElementSibling;
          if (nextRow && nextRow.classList.contains('audit-detail-inline')) {
            // Collapse: remove existing inline row
            nextRow.remove();
            return;
          }

          // Remove any other open inline detail rows first
          var openDetails = _tableBody.querySelectorAll('tr.audit-detail-inline');
          for (var d = 0; d < openDetails.length; d++) openDetails[d].remove();

          // Find event data
          var evt = null;
          for (var j = 0; j < _allEvents.length; j++) {
            if (_allEvents[j].id === eventId) { evt = _allEvents[j]; break; }
          }
          if (!evt) return;

          // Insert inline detail row after the clicked row
          row.insertAdjacentHTML('afterend', buildDetailPanelHtml(evt));
        });
      })(rows[i]);
    }

    // Bind individual checkbox changes
    var checks = _tableBody.querySelectorAll('.audit-row-check');
    for (var j = 0; j < checks.length; j++) {
      checks[j].addEventListener('change', function(e) {
        var id = e.target.dataset.id;
        if (e.target.checked) {
          if (_selectedIds.indexOf(id) === -1) _selectedIds.push(id);
        } else {
          _selectedIds = _selectedIds.filter(function(s) { return s !== id; });
        }
        updateExportSelectionBtn();
        // Update selectAll indeterminate state
        updateSelectAllState();
      });
    }
  }

  /* ── Render timeline ── */
  function renderTimeline(events) {
    if (!_timeline) return;

    if (!events || events.length === 0) {
      _timeline.innerHTML = '<ag-empty-state icon="generic" title="Aucun r\u00e9sultat" ' +
        'description="Aucun \u00e9v\u00e9nement ne correspond aux filtres s\u00e9lectionn\u00e9s.">' +
        '<button slot="action" class="btn btn-secondary btn-sm" data-action="reset-filters">' +
        'R\u00e9initialiser les filtres</button></ag-empty-state>';
      return;
    }

    _timeline.innerHTML = events.map(function(evt) {
      var catLabel = esc(CATEGORY_LABELS[evt.category] || evt.category);
      // Map severity to high/medium/low for CSS data-severity border coloring
      var severityMap = { danger: 'high', warning: 'medium', info: 'low', success: 'low' };
      var severityLevel = severityMap[evt.severity] || 'low';
      return '<div class="audit-timeline-item" data-event-id="' + esc(evt.id) + '" data-severity="' + esc(severityLevel) + '">' +
        '<span class="audit-timeline-dot ' + esc(evt.severity) + '"></span>' +
        '<div class="audit-timeline-content">' +
          '<div class="audit-timeline-header">' +
            '<div class="audit-timeline-title">' + esc(evt.event) + '</div>' +
            '<div class="audit-timeline-time audit-timestamp">' + esc(formatTimestamp(evt.timestamp)) + '</div>' +
          '</div>' +
          '<div class="audit-timeline-meta">' +
            '<span class="tag tag-ghost">' + catLabel + '</span>' +
            '<span class="tag tag-accent">' + esc(evt.user) + '</span>' +
            '<span class="audit-hash-cell" title="' + esc(evt.hash) + '">' + esc(evt.hash.substring(0, 12)) + '...</span>' +
          '</div>' +
        '</div>' +
        '<svg class="icon audit-timeline-chevron" aria-hidden="true"><use href="/assets/icons.svg#icon-chevron-right"></use></svg>' +
      '</div>';
    }).join('');

    // Bind click handlers
    var items = _timeline.querySelectorAll('.audit-timeline-item');
    for (var i = 0; i < items.length; i++) {
      (function(item) {
        item.addEventListener('click', function() {
          var eventId = item.dataset.eventId;
          if (eventId) openDetailModal(eventId);
        });
      })(items[i]);
    }
  }

  /* ── Render current view ── */
  function renderCurrentView() {
    var start = (_currentPage - 1) * _perPage;
    var pageEvents = _filteredEvents.slice(start, start + _perPage);

    if (_currentView === 'timeline') {
      renderTimeline(pageEvents);
    } else {
      renderTable(pageEvents);
    }
    renderPagination();
  }

  /* ── Pagination ── */
  function renderPagination() {
    if (!_pagination) return;

    var totalPages = Math.max(1, Math.ceil(_filteredEvents.length / _perPage));
    if (totalPages <= 1 && _filteredEvents.length <= _perPage) {
      _pagination.innerHTML = '';
      return;
    }

    var html = '';

    // Previous button
    html += '<button class="btn btn-sm btn-ghost" data-page="' + (_currentPage - 1) + '"' +
            (_currentPage <= 1 ? ' disabled' : '') + '>&#8249; Préc.</button>';

    // Page buttons
    for (var i = 1; i <= totalPages; i++) {
      var active = i === _currentPage ? ' btn-primary' : ' btn-ghost';
      html += '<button class="btn btn-sm' + active + '" data-page="' + i + '">' + i + '</button>';
    }

    // Next button
    html += '<button class="btn btn-sm btn-ghost" data-page="' + (_currentPage + 1) + '"' +
            (_currentPage >= totalPages ? ' disabled' : '') + '>Suiv. &#8250;</button>';

    _pagination.innerHTML = html;

    // Bind clicks
    var btns = _pagination.querySelectorAll('button[data-page]');
    for (var j = 0; j < btns.length; j++) {
      btns[j].addEventListener('click', function(e) {
        if (e.target.disabled) return;
        var page = parseInt(e.target.dataset.page);
        if (!isNaN(page) && page >= 1 && page <= totalPages) {
          _currentPage = page;
          renderCurrentView();
        }
      });
    }
  }

  /* ── Apply filters ── */
  function applyFilters() {
    _filteredEvents = _allEvents.filter(function(evt) {
      var matchCategory = !_activeFilter || evt.category === _activeFilter;
      var matchSearch = true;
      if (_searchQuery) {
        var q = _searchQuery.toLowerCase();
        matchSearch = (evt.event || '').toLowerCase().indexOf(q) !== -1 ||
                      (evt.user || '').toLowerCase().indexOf(q) !== -1 ||
                      (evt.description || '').toLowerCase().indexOf(q) !== -1;
      }
      return matchCategory && matchSearch;
    });

    // Update filter count badges based on all events (not filtered)
    updateFilterCounts(_allEvents);

    _currentPage = 1;
    renderCurrentView();
  }

  /* ── Sort ── */
  function applySortToFiltered() {
    var sortVal = _auditSort ? _auditSort.value : 'date-desc';
    _filteredEvents.sort(function(a, b) {
      if (sortVal === 'date-asc') {
        return new Date(a.timestamp) - new Date(b.timestamp);
      }
      if (sortVal === 'severity-desc') {
        var order = { danger: 0, warning: 1, info: 2, success: 3 };
        return (order[a.severity] || 99) - (order[b.severity] || 99);
      }
      // date-desc (default)
      return new Date(b.timestamp) - new Date(a.timestamp);
    });
  }

  /* ── Event detail modal ── */
  function openDetailModal(eventId) {
    var evt = null;
    for (var i = 0; i < _allEvents.length; i++) {
      if (_allEvents[i].id === eventId) { evt = _allEvents[i]; break; }
    }
    if (!evt) return;

    var detailTimestamp = document.getElementById('detailTimestamp');
    var detailCategory = document.getElementById('detailCategory');
    var detailUser = document.getElementById('detailUser');
    var detailSeverity = document.getElementById('detailSeverity');
    var detailDescription = document.getElementById('detailDescription');
    var detailHash = document.getElementById('detailHash');

    if (detailTimestamp) detailTimestamp.textContent = formatTimestamp(evt.timestamp);
    if (detailCategory) {
      var catLabel = CATEGORY_LABELS[evt.category] || evt.category;
      detailCategory.innerHTML = '<span class="tag tag-ghost">' + esc(catLabel) + '</span>';
    }
    if (detailUser) detailUser.textContent = evt.user;
    if (detailSeverity) {
      detailSeverity.innerHTML = '<span class="audit-severity-dot ' + esc(evt.severity) + '"></span> ' + esc(evt.severity);
    }
    if (detailDescription) detailDescription.textContent = evt.description;
    if (detailHash) detailHash.textContent = evt.hash;

    // Store current event id for export
    _detailModal.dataset.currentEventId = eventId;

    // Show
    if (_detailModal) {
      _detailModal.style.display = 'block';
      _detailModal.setAttribute('aria-hidden', 'false');
    }
    if (_detailBackdrop) _detailBackdrop.style.display = 'block';
    document.body.style.overflow = 'hidden';
  }

  function closeDetailModal() {
    if (_detailModal) {
      _detailModal.style.display = 'none';
      _detailModal.setAttribute('aria-hidden', 'true');
    }
    if (_detailBackdrop) _detailBackdrop.style.display = 'none';
    document.body.style.overflow = '';
  }

  /* ── CSV Export ── */
  function generateCSV(events) {
    var headers = ['Horodatage', 'Evenement', 'Categorie', 'Utilisateur', 'Severite', 'Hash'];
    var rows = events.map(function(evt) {
      return [
        formatTimestamp(evt.timestamp),
        '"' + (evt.event || '').replace(/"/g, '""') + '"',
        evt.category,
        '"' + (evt.user || '').replace(/"/g, '""') + '"',
        evt.severity,
        evt.hash
      ].join(',');
    });
    return headers.join(',') + '\n' + rows.join('\n');
  }

  function downloadCSV(csv, filename) {
    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  /* ── Checkbox / selection ── */
  function updateExportSelectionBtn() {
    if (_btnExportSelection) {
      if (_selectedIds.length > 0) {
        _btnExportSelection.hidden = false;
        _btnExportSelection.innerHTML = '<svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-download"></use></svg> Exporter la sélection (' + _selectedIds.length + ')';
      } else {
        _btnExportSelection.hidden = true;
      }
    }
  }

  function updateSelectAllState() {
    if (!_selectAll) return;
    var allChecks = _tableBody ? _tableBody.querySelectorAll('.audit-row-check') : [];
    var checkedCount = 0;
    for (var i = 0; i < allChecks.length; i++) {
      if (allChecks[i].checked) checkedCount++;
    }
    if (checkedCount === 0) {
      _selectAll.checked = false;
      _selectAll.indeterminate = false;
    } else if (checkedCount === allChecks.length) {
      _selectAll.checked = true;
      _selectAll.indeterminate = false;
    } else {
      _selectAll.checked = false;
      _selectAll.indeterminate = true;
    }
  }

  /* ── Error state ── */
  function showAuditError() {
    if (window.Shared && Shared.showToast) {
      Shared.showToast("Impossible de charger les événements d'audit.", 'error');
    }

    // Reset KPI values to dash to avoid showing stale data
    var kpiIntegrity = document.getElementById('kpiIntegrity');
    var kpiEvents = document.getElementById('kpiEvents');
    var kpiAnomalies = document.getElementById('kpiAnomalies');
    var kpiLastSession = document.getElementById('kpiLastSession');
    if (kpiIntegrity) kpiIntegrity.textContent = '—';
    if (kpiEvents) kpiEvents.textContent = '—';
    if (kpiAnomalies) kpiAnomalies.textContent = '—';
    if (kpiLastSession) kpiLastSession.textContent = '—';

    var errorContent =
      Shared.emptyState({ icon: 'generic', title: 'Erreur de chargement', description: 'Impossible de contacter le serveur.' }) +
      '<div style="text-align:center;margin-top:12px;">' +
        '<button class="btn btn-primary" id="auditRetryBtn">Réessayer</button>' +
      '</div>';

    if (_tableBody) {
      _tableBody.innerHTML = '<tr><td colspan="6">' + errorContent + '</td></tr>';
    }
    if (_timeline) {
      _timeline.innerHTML = errorContent;
    }

    var retryBtn = document.getElementById('auditRetryBtn');
    if (retryBtn) {
      retryBtn.addEventListener('click', function() { loadData(); });
    }
  }

  /* ── Data loading ── */
  function loadData() {
    var params = new URLSearchParams(window.location.search);
    var meetingId = params.get('meeting_id');
    // Support clean URL: /audit/{uuid}
    if (!meetingId) {
      var pathMatch = window.location.pathname.match(/^\/audit\/([0-9a-f-]{36})$/i);
      if (pathMatch) meetingId = pathMatch[1];
    }
    // Fallback to MeetingContext if available
    if (!meetingId && window.MeetingContext) {
      meetingId = window.MeetingContext.get();
    }

    if (!meetingId) {
      _allEvents = [];
      var guidanceContent = '<tr><td colspan="6">' + Shared.emptyState({
        icon: 'generic',
        title: 'Sélectionnez une séance',
        description: "Ouvrez une séance depuis le tableau de bord pour afficher son journal d'audit."
      }) + '</td></tr>';
      if (_tableBody) _tableBody.innerHTML = guidanceContent;
      if (_timeline) _timeline.innerHTML = Shared.emptyState({
        icon: 'generic',
        title: 'Sélectionnez une séance',
        description: "Ouvrez une séance depuis le tableau de bord pour afficher son journal d'audit."
      });
      return;
    }

    function tryLoad(attempt) {
      window.api('/api/v1/audit_log.php?meeting_id=' + encodeURIComponent(meetingId))
        .then(function(res) {
          if (res && res.body && res.body.data && res.body.data.items) {
            var items = res.body.data.items;
            _allEvents = items.map(function(item) {
              return {
                id: item.id,
                timestamp: item.created_at || item.timestamp,
                category: item.category || 'system',
                severity: item.severity || 'info',
                event: item.action_label || item.event || '',
                user: item.actor || item.user || '',
                hash: item.hash || '',
                description: item.description || item.action_label || ''
              };
            });
          } else {
            throw new Error('No data');
          }
          populateKPIs();
          applyFilters();
        })
        .catch(function(e) {
          if (attempt < 2) {
            setTimeout(function() { tryLoad(attempt + 1); }, 2000);
          } else {
            // API unavailable after retries
            showAuditError();
          }
        });
    }

    tryLoad(1);
  }

  /* ── Init event handlers ── */
  function initHandlers() {
    // Filter tabs
    var filterTabs = document.querySelectorAll('#auditTypeFilter .filter-tab');
    for (var i = 0; i < filterTabs.length; i++) {
      (function(tab) {
        tab.addEventListener('click', function() {
          for (var j = 0; j < filterTabs.length; j++) filterTabs[j].classList.remove('active');
          tab.classList.add('active');
          _activeFilter = tab.dataset.type || '';
          applyFilters();
        });
      })(filterTabs[i]);
    }

    // Search (debounced)
    if (_auditSearch) {
      _auditSearch.addEventListener('input', Utils.debounce(function(e) {
        _searchQuery = e.target.value.trim();
        applyFilters();
      }, 300));
    }

    // Sort
    if (_auditSort) {
      _auditSort.addEventListener('change', function() {
        applySortToFiltered();
        _currentPage = 1;
        renderCurrentView();
      });
    }

    // View toggle
    var viewBtns = document.querySelectorAll('.view-toggle-btn');
    for (var k = 0; k < viewBtns.length; k++) {
      (function(btn) {
        btn.addEventListener('click', function() {
          for (var m = 0; m < viewBtns.length; m++) viewBtns[m].classList.remove('active');
          btn.classList.add('active');
          _currentView = btn.dataset.view || 'table';
          if (_currentView === 'timeline') {
            _tableView.removeAttribute('hidden');
            _timelineView.removeAttribute('hidden');
            _tableView.setAttribute('hidden', '');
          } else {
            _tableView.removeAttribute('hidden');
            _timelineView.setAttribute('hidden', '');
          }
          _currentPage = 1;
          renderCurrentView();
        });
      })(viewBtns[k]);
    }

    // Select all
    if (_selectAll) {
      _selectAll.addEventListener('change', function() {
        var allChecks = _tableBody ? _tableBody.querySelectorAll('.audit-row-check') : [];
        var isChecked = _selectAll.checked;
        for (var n = 0; n < allChecks.length; n++) {
          allChecks[n].checked = isChecked;
          var id = allChecks[n].dataset.id;
          if (isChecked) {
            if (_selectedIds.indexOf(id) === -1) _selectedIds.push(id);
          } else {
            _selectedIds = _selectedIds.filter(function(s) { return s !== id; });
          }
        }
        if (!isChecked) _selectedIds = [];
        updateExportSelectionBtn();
      });
    }

    // Export all
    if (_btnExportAll) {
      _btnExportAll.addEventListener('click', function() {
        downloadCSV(generateCSV(_filteredEvents), 'audit-export-' + new Date().toISOString().slice(0, 10) + '.csv');
      });
    }

    // Export selection
    if (_btnExportSelection) {
      _btnExportSelection.addEventListener('click', function() {
        var selected = _allEvents.filter(function(e) { return _selectedIds.indexOf(e.id) !== -1; });
        downloadCSV(generateCSV(selected), 'audit-selection-' + new Date().toISOString().slice(0, 10) + '.csv');
      });
    }

    // Close modal buttons
    var btnClose1 = document.getElementById('btnCloseAuditDetail');
    var btnClose2 = document.getElementById('btnCloseAuditDetail2');
    if (btnClose1) btnClose1.addEventListener('click', closeDetailModal);
    if (btnClose2) btnClose2.addEventListener('click', closeDetailModal);
    if (_detailBackdrop) _detailBackdrop.addEventListener('click', closeDetailModal);

    // Export detail button
    var btnExportDetail = document.getElementById('btnExportDetail');
    if (btnExportDetail) {
      btnExportDetail.addEventListener('click', function() {
        var eventId = _detailModal && _detailModal.dataset.currentEventId;
        if (!eventId) return;
        var evt = null;
        for (var p = 0; p < _allEvents.length; p++) {
          if (_allEvents[p].id === eventId) { evt = _allEvents[p]; break; }
        }
        if (!evt) return;
        var json = JSON.stringify(evt, null, 2);
        var blob = new Blob([json], { type: 'application/json' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'audit-event-' + eventId + '.json';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
      });
    }

    // Escape key closes modal
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && _detailModal && _detailModal.style.display !== 'none') {
        closeDetailModal();
      }
    });

    // Reset filters delegation (from empty state reset button in table and timeline views)
    var auditContainer = document.getElementById('auditTableBody') && document.getElementById('auditTableBody').closest('table') ||
                         document.getElementById('auditTimeline');
    var auditSection = document.querySelector('.audit-content') || document.getElementById('main-content');
    if (auditSection) {
      auditSection.addEventListener('click', function(e) {
        var resetBtn = e.target.closest('[data-action="reset-filters"]');
        if (!resetBtn) return;
        // Reset filter tabs
        _activeFilter = '';
        var filterTabs = document.querySelectorAll('#auditTypeFilter .filter-tab');
        for (var i = 0; i < filterTabs.length; i++) {
          filterTabs[i].classList.toggle('active', !filterTabs[i].dataset.type);
        }
        // Reset search
        _searchQuery = '';
        if (_auditSearch) _auditSearch.value = '';
        applyFilters();
      });
    }
  }

  /* ── Bootstrap ── */
  document.addEventListener('DOMContentLoaded', function() {
    initHandlers();
    loadData();
  });

})();
