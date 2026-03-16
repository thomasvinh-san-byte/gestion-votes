/** audit.js — Journal d'audit page for AG-VOTE. Must be loaded AFTER utils.js and shared.js. */
(function() {
  'use strict';

  var currentPage = 1;
  var currentFilter = 'all';
  var currentView = 'table';
  var currentSort = 'desc';
  var searchTerm = '';
  var PAGE_SIZE = 20;
  var totalPages = 1;

  document.addEventListener('DOMContentLoaded', init);

  function init() {
    // Filter pills
    var pills = document.querySelectorAll('.filter-pill');
    pills.forEach(function(pill) {
      pill.addEventListener('click', function() {
        pills.forEach(function(p) { p.classList.remove('filter-pill--active'); });
        pill.classList.add('filter-pill--active');
        currentFilter = pill.dataset.filter || 'all';
        currentPage = 1;
        loadAuditLog();
      });
    });

    // Search
    var searchInput = document.getElementById('auditSearch');
    if (searchInput) {
      searchInput.addEventListener('input', Shared.debounce(function() {
        searchTerm = searchInput.value.trim();
        currentPage = 1;
        loadAuditLog();
      }, 300));
    }

    // Sort
    var sortSelect = document.getElementById('auditSort');
    if (sortSelect) {
      sortSelect.addEventListener('change', function() {
        currentSort = sortSelect.value;
        currentPage = 1;
        loadAuditLog();
      });
    }

    // View toggle
    var viewBtns = document.querySelectorAll('.view-toggle-btn');
    viewBtns.forEach(function(btn) {
      btn.addEventListener('click', function() {
        viewBtns.forEach(function(b) {
          b.classList.remove('view-toggle-btn--active');
          b.setAttribute('aria-pressed', 'false');
        });
        btn.classList.add('view-toggle-btn--active');
        btn.setAttribute('aria-pressed', 'true');
        currentView = btn.dataset.view || 'table';
        toggleView(currentView);
        loadAuditLog();
      });
    });

    // Pagination
    var pagination = document.getElementById('auditPagination');
    if (pagination) {
      pagination.addEventListener('page-change', function(e) {
        currentPage = e.detail && e.detail.page ? e.detail.page : 1;
        loadAuditLog();
      });
    }

    // Select all checkboxes
    var selectAll = document.getElementById('auditSelectAll');
    if (selectAll) {
      selectAll.addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('#auditTableBody .audit-row-check');
        checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
      });
    }

    // Details modal close
    var btnClose = document.getElementById('btnCloseDetail');
    if (btnClose) {
      btnClose.addEventListener('click', function() {
        var modal = document.getElementById('auditDetailModal');
        if (modal && modal.close) { modal.close(); }
        else if (modal) { modal.removeAttribute('open'); }
      });
    }

    // Copy hash button
    var btnCopy = document.getElementById('btnCopyHash');
    if (btnCopy) {
      btnCopy.addEventListener('click', function() {
        var hashEl = document.getElementById('detailHash');
        if (!hashEl) return;
        var hash = hashEl.textContent.trim();
        if (hash && hash !== '—' && navigator.clipboard) {
          navigator.clipboard.writeText(hash).then(function() {
            AgToast.show('Empreinte copiee', 'success');
          }).catch(function() {
            AgToast.show('Impossible de copier', 'warn');
          });
        }
      });
    }

    // Verify hash button
    var btnVerify = document.getElementById('btnVerifyHash');
    if (btnVerify) {
      btnVerify.addEventListener('click', function() {
        var hashEl = document.getElementById('detailHash');
        var eventId = btnVerify.dataset.eventId;
        if (!eventId) return;
        verifyEventIntegrity(eventId);
      });
    }

    // Export CSV
    var btnExport = document.getElementById('btnExportCsv');
    if (btnExport) {
      btnExport.addEventListener('click', exportAuditLog);
    }

    // Refresh
    var btnRefresh = document.getElementById('btnRefresh');
    if (btnRefresh) {
      btnRefresh.addEventListener('click', function() { loadAuditLog(); });
    }

    // Delegated click: details button
    var tableBody = document.getElementById('auditTableBody');
    if (tableBody) {
      tableBody.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-audit-id]');
        if (btn) { showEventDetail(btn.dataset.auditId); }
      });
    }
    var timelineBody = document.getElementById('auditTimelineBody');
    if (timelineBody) {
      timelineBody.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-audit-id]');
        if (btn) { showEventDetail(btn.dataset.auditId); }
      });
    }

    // Initial load
    loadAuditLog();
  }

  // ===================================================
  // LOAD AUDIT LOG
  // ===================================================

  function loadAuditLog() {
    showTableSkeleton();

    var url = '/api/v1/audit_log.php?page=' + currentPage +
      '&page_size=' + PAGE_SIZE +
      '&sort=' + encodeURIComponent(currentSort);

    if (currentFilter && currentFilter !== 'all') {
      url += '&filter=' + encodeURIComponent(currentFilter);
    }
    if (searchTerm) {
      url += '&q=' + encodeURIComponent(searchTerm);
    }

    api(url).then(function(r) {
      var body = r && r.body ? r.body : r;
      if (body && body.ok !== false && (body.data || body.items)) {
        var data = body.data || {};
        var items = data.items || body.items || [];
        var total = data.total || items.length;
        totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));

        // Update pagination
        var pagination = document.getElementById('auditPagination');
        if (pagination) {
          pagination.setAttribute('total', total);
          pagination.setAttribute('page', currentPage);
          pagination.setAttribute('page-size', PAGE_SIZE);
        }

        if (currentView === 'table') {
          renderTable(items);
        } else {
          renderTimeline(items);
        }

        if (!items || items.length === 0) {
          showEmptyState();
        }
      } else {
        showEmptyState();
      }
    }).catch(function(err) {
      console.error('Audit log load error:', err);
      var tableBody = document.getElementById('auditTableBody');
      if (tableBody) {
        tableBody.innerHTML = '<tr><td colspan="7" class="text-center p-6 text-muted">Erreur de chargement du journal</td></tr>';
      }
      if (typeof AgToast !== 'undefined') {
        AgToast.show('Impossible de charger le journal d\'audit', 'danger');
      }
    });
  }

  // ===================================================
  // RENDER TABLE (AUD-02)
  // ===================================================

  function renderTable(events) {
    var tableBody = document.getElementById('auditTableBody');
    if (!tableBody) return;

    if (!events || events.length === 0) {
      showEmptyState();
      return;
    }

    tableBody.innerHTML = events.map(function(e) {
      var statusVariant = statusToVariant(e.status || e.result || '');
      var statusLabel = e.status_label || e.status || '—';
      return '<tr>' +
        '<td class="audit-checkbox-col"><input type="checkbox" class="audit-row-check" aria-label="Selectionner cet evenement"></td>' +
        '<td class="audit-date">' + escapeHtml(Shared.formatDate ? Shared.formatDate(e.timestamp || e.created_at) : fmtDateTime(e.timestamp || e.created_at)) + '</td>' +
        '<td class="audit-user">' + escapeHtml(e.user_name || e.actor || '—') + '</td>' +
        '<td class="audit-action">' + escapeHtml(e.action_label || e.action || '—') + '</td>' +
        '<td class="audit-resource col-resource"><code class="audit-resource">' + escapeHtml(e.resource || '—') + '</code></td>' +
        '<td><ag-badge variant="' + statusVariant + '">' + escapeHtml(statusLabel) + '</ag-badge></td>' +
        '<td><button class="btn btn-ghost btn-xs" data-audit-id="' + escapeHtml(String(e.id || '')) + '">Details</button></td>' +
        '</tr>';
    }).join('');
  }

  // ===================================================
  // RENDER TIMELINE (AUD-02)
  // ===================================================

  function renderTimeline(events) {
    var timelineBody = document.getElementById('auditTimelineBody');
    if (!timelineBody) return;

    if (!events || events.length === 0) {
      timelineBody.innerHTML = Shared.emptyState ? Shared.emptyState(timelineBody, {
        icon: 'search',
        title: 'Aucun evenement',
        subtitle: 'Aucun evenement ne correspond aux criteres selectionnes'
      }) : '<div class="text-center p-6 text-muted">Aucun evenement</div>';
      return;
    }

    // Group events by date
    var groups = {};
    var groupOrder = [];
    events.forEach(function(e) {
      var ts = e.timestamp || e.created_at || '';
      var dateKey = ts ? ts.substring(0, 10) : 'Inconnu';
      if (!groups[dateKey]) {
        groups[dateKey] = [];
        groupOrder.push(dateKey);
      }
      groups[dateKey].push(e);
    });

    var html = groupOrder.map(function(dateKey) {
      var label = dateKey !== 'Inconnu' ? formatDateLabel(dateKey) : 'Date inconnue';
      var itemsHtml = groups[dateKey].map(function(e) {
        var iconClass = filterToIconClass(e.event_type || e.filter_type || '');
        var iconSvg = filterToIconSvg(e.event_type || e.filter_type || '');
        var timeStr = fmtTime(e.timestamp || e.created_at || '');
        var statusVariant = statusToVariant(e.status || e.result || '');
        var statusLabel = e.status_label || e.status || '—';
        return '<div class="audit-timeline-item">' +
          '<div class="audit-timeline-marker">' +
            '<span class="audit-timeline-time">' + escapeHtml(timeStr) + '</span>' +
            '<span class="audit-timeline-icon ' + iconClass + '" aria-hidden="true">' + iconSvg + '</span>' +
          '</div>' +
          '<div class="audit-timeline-card">' +
            '<div class="audit-timeline-card-header">' +
              '<span class="audit-timeline-card-title">' + escapeHtml(e.action_label || e.action || '—') + '</span>' +
              '<ag-badge variant="' + statusVariant + '">' + escapeHtml(statusLabel) + '</ag-badge>' +
            '</div>' +
            '<div class="audit-timeline-card-meta">' +
              '<span>' + escapeHtml(e.user_name || e.actor || '—') + '</span>' +
              (e.resource ? '<span><code>' + escapeHtml(e.resource) + '</code></span>' : '') +
            '</div>' +
            '<div class="mt-2">' +
              '<button class="btn btn-ghost btn-xs" data-audit-id="' + escapeHtml(String(e.id || '')) + '">Details</button>' +
            '</div>' +
          '</div>' +
        '</div>';
      }).join('');

      return '<div class="audit-timeline-date-group">' +
        '<div class="audit-timeline-date-label">' + escapeHtml(label) + '</div>' +
        itemsHtml +
      '</div>';
    }).join('');

    timelineBody.innerHTML = html;
  }

  // ===================================================
  // SHOW EVENT DETAIL (AUD-03)
  // ===================================================

  function showEventDetail(eventId) {
    if (!eventId || eventId === 'undefined') return;

    // Reset modal fields
    setDetailField('detailDate', '...');
    setDetailField('detailUser', '...');
    setDetailField('detailAction', '...');
    setDetailField('detailResource', '...');
    setDetailField('detailStatus', '...');
    setDetailField('detailIp', '...');
    var hashEl = document.getElementById('detailHash');
    if (hashEl) { hashEl.textContent = 'Chargement...'; }

    // Open modal
    var modal = document.getElementById('auditDetailModal');
    if (modal && modal.open) { modal.open(); }
    else if (modal) { modal.setAttribute('open', ''); }

    // Fetch event details
    api('/api/v1/audit_log.php?id=' + encodeURIComponent(eventId)).then(function(r) {
      var body = r && r.body ? r.body : r;
      var event = body && body.data ? (body.data.item || body.data) : null;
      if (event) {
        setDetailField('detailDate', Shared.formatDate ? Shared.formatDate(event.timestamp || event.created_at) : fmtDateTime(event.timestamp || event.created_at));
        setDetailField('detailUser', event.user_name || event.actor || '—');
        setDetailField('detailAction', event.action_label || event.action || '—');
        setDetailField('detailResource', event.resource || '—');
        setDetailField('detailStatus', event.status_label || event.status || '—');
        setDetailField('detailIp', event.ip_address || '—');
      }
    }).catch(function() {
      setDetailField('detailDate', '—');
    });

    // Fetch SHA-256 fingerprint
    var btnVerify = document.getElementById('btnVerifyHash');
    if (btnVerify) { btnVerify.dataset.eventId = eventId; }

    api('/api/v1/audit_verify.php?id=' + encodeURIComponent(eventId)).then(function(r) {
      var body = r && r.body ? r.body : r;
      var hash = body && body.data ? (body.data.sha256 || body.data.hash || body.sha256 || '') : '';
      if (hashEl) {
        hashEl.textContent = hash || '—';
      }
    }).catch(function() {
      if (hashEl) { hashEl.textContent = '—'; }
    });
  }

  // ===================================================
  // VERIFY INTEGRITY
  // ===================================================

  function verifyEventIntegrity(eventId) {
    if (!eventId) return;
    api('/api/v1/audit_verify.php?id=' + encodeURIComponent(eventId) + '&verify=1').then(function(r) {
      var body = r && r.body ? r.body : r;
      if (body && body.ok) {
        AgToast.show('Integrite verifiee — empreinte valide', 'success');
      } else {
        AgToast.show('Echec de verification — empreinte invalide', 'danger');
      }
    }).catch(function() {
      AgToast.show('Erreur lors de la verification', 'danger');
    });
  }

  // ===================================================
  // EXPORT
  // ===================================================

  function exportAuditLog() {
    var selectedIds = [];
    document.querySelectorAll('#auditTableBody .audit-row-check:checked').forEach(function(cb) {
      var row = cb.closest('tr');
      var btn = row ? row.querySelector('[data-audit-id]') : null;
      if (btn && btn.dataset.auditId) { selectedIds.push(btn.dataset.auditId); }
    });

    var url = '/api/v1/audit_export.php?sort=' + encodeURIComponent(currentSort);
    if (currentFilter && currentFilter !== 'all') {
      url += '&filter=' + encodeURIComponent(currentFilter);
    }
    if (searchTerm) {
      url += '&q=' + encodeURIComponent(searchTerm);
    }
    if (selectedIds.length > 0) {
      url += '&ids=' + encodeURIComponent(selectedIds.join(','));
    }
    window.open(url, '_blank');
  }

  // ===================================================
  // HELPERS
  // ===================================================

  function toggleView(view) {
    var tableView = document.getElementById('auditTableView');
    var timelineView = document.getElementById('auditTimelineView');
    if (!tableView || !timelineView) return;
    if (view === 'table') {
      tableView.hidden = false;
      timelineView.hidden = true;
    } else {
      tableView.hidden = true;
      timelineView.hidden = false;
    }
  }

  function showTableSkeleton() {
    if (currentView === 'table') {
      var tableBody = document.getElementById('auditTableBody');
      if (tableBody) {
        tableBody.innerHTML = '<tr><td colspan="7" class="text-center p-6"><div class="spinner"></div><div class="mt-2 text-muted">Chargement...</div></td></tr>';
      }
    } else {
      var timelineBody = document.getElementById('auditTimelineBody');
      if (timelineBody) {
        timelineBody.innerHTML = '<div class="text-center p-6"><div class="spinner"></div><div class="mt-2 text-muted">Chargement...</div></div>';
      }
    }
  }

  function showEmptyState() {
    if (currentView === 'table') {
      var tableBody = document.getElementById('auditTableBody');
      if (tableBody) {
        tableBody.innerHTML = '<tr><td colspan="7" class="text-center p-6 text-muted">Aucun evenement trouve</td></tr>';
      }
    } else {
      var timelineBody = document.getElementById('auditTimelineBody');
      if (timelineBody) {
        timelineBody.innerHTML = '<div class="text-center p-6 text-muted">Aucun evenement trouve</div>';
      }
    }
  }

  function setDetailField(id, value) {
    var el = document.getElementById(id);
    if (el) { el.textContent = value || '—'; }
  }

  function statusToVariant(status) {
    if (!status) return '';
    var s = status.toLowerCase();
    if (s === 'ok' || s === 'success' || s === 'succes') return 'success';
    if (s === 'erreur' || s === 'error' || s === 'fail' || s === 'failed') return 'danger';
    if (s === 'avertissement' || s === 'warning' || s === 'warn') return 'warn';
    return '';
  }

  function filterToIconClass(type) {
    if (!type) return '';
    var t = type.toLowerCase();
    if (t === 'votes' || t === 'vote') return 'icon-votes';
    if (t === 'presences' || t === 'presence') return 'icon-presences';
    if (t === 'security' || t === 'securite') return 'icon-security';
    if (t === 'system' || t === 'systeme') return 'icon-system';
    return '';
  }

  function filterToIconSvg(type) {
    if (!type) return '<svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#icon-activity"></use></svg>';
    var t = type.toLowerCase();
    if (t === 'votes' || t === 'vote') return '<svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#icon-vote"></use></svg>';
    if (t === 'presences' || t === 'presence') return '<svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#icon-users"></use></svg>';
    if (t === 'security' || t === 'securite') return '<svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#icon-shield-check"></use></svg>';
    if (t === 'system' || t === 'systeme') return '<svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#icon-settings"></use></svg>';
    return '<svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#icon-activity"></use></svg>';
  }

  function fmtDateTime(s) {
    if (!s) return '—';
    try {
      return new Date(s).toLocaleString('fr-FR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit'
      });
    } catch (e) { return s; }
  }

  function fmtTime(s) {
    if (!s) return '—';
    try {
      return new Date(s).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    } catch (e) { return s; }
  }

  function formatDateLabel(dateKey) {
    if (!dateKey || dateKey === 'Inconnu') return 'Date inconnue';
    try {
      return new Date(dateKey).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    } catch (e) { return dateKey; }
  }

})();
