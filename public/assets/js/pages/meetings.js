/**
 * meetings.js — Sessions page logic for AG-VOTE.
 *
 * Clean rewrite: filter pills, search/sort, session list rendering,
 * calendar view, popover menus, empty states, onboarding banner.
 *
 * @module meetings
 * @requires utils.js (api, escapeHtml, formatDate, debounce, setNotif)
 * @requires shared.js (Shared.emptyState, Shared.btnLoading, Shared.validateField)
 */
(function() {
  'use strict';

  // === STATE ===
  var allMeetings = [];
  var currentFilter = 'all';
  var searchText = '';
  var sortMode = 'date_desc';
  var currentView = 'list';
  var calendarDate = new Date();
  var currentPage = 1;
  var PER_PAGE = 12;

  // === CONSTANTS ===
  var FILTER_MAP = {
    all: null,
    upcoming: ['scheduled', 'frozen', 'draft', 'convocations'],
    live: ['live', 'paused'],
    completed: ['closed', 'validated', 'archived', 'pv_sent']
  };

  var DOT_CLASS_MAP = {
    draft: 'draft',
    scheduled: 'upcoming',
    frozen: 'upcoming',
    convocations: 'upcoming',
    live: 'live',
    paused: 'live',
    closed: 'completed',
    validated: 'completed',
    archived: 'completed',
    pv_sent: 'completed'
  };

  var TAG_VARIANT_MAP = {
    draft: 'muted',
    scheduled: 'accent',
    frozen: 'accent',
    convocations: 'accent',
    live: 'danger',
    paused: 'danger',
    closed: 'success',
    validated: 'success',
    archived: 'muted',
    pv_sent: 'muted'
  };

  var TYPE_LABELS = {
    ag_ordinaire: 'AG ordinaire',
    ag_extraordinaire: 'AG extra.',
    conseil: 'Conseil',
    bureau: 'Bureau',
    autre: 'Autre'
  };

  var STATUS_LABELS = {
    draft: 'Brouillon',
    scheduled: 'Planifi&eacute;e',
    frozen: 'Verrouill&eacute;e',
    convocations: 'Convocations',
    live: 'En cours',
    paused: 'En pause',
    closed: 'Termin&eacute;e',
    validated: 'Valid&eacute;e',
    archived: 'Archiv&eacute;e',
    pv_sent: 'PV envoy&eacute;'
  };

  var MONTH_NAMES = [
    'Janvier', 'F\u00e9vrier', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Ao\u00fbt', 'Septembre', 'Octobre', 'Novembre', 'D\u00e9cembre'
  ];

  var DAY_NAMES = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

  // === DOM REFS ===
  var meetingsList = document.getElementById('meetingsList');
  var meetingsCount = document.getElementById('meetingsCount');
  var meetingsPagination = document.getElementById('meetingsPagination');
  var calendarContainer = document.getElementById('calendarContainer');
  var calendarGrid = document.getElementById('calendarGrid');
  var calendarTitle = document.getElementById('calendarTitle');

  // === DATA LOADING ===

  function loadMeetings() {
    Shared.withRetry({
      container: meetingsList,
      maxRetries: 1,
      errorMsg: 'Impossible de charger les s\u00e9ances',
      action: async function() {
        var result = await api('/api/v1/meetings_index.php');
        if (!result.body || !result.body.ok) throw new Error(result.body?.error || 'Erreur serveur');
        allMeetings = result.body.data?.items || [];
        updateFilterCounts();
        renderCurrentView();
        updateOnboardingBanner();
      }
    });
  }

  // === FILTER PILLS ===

  function initFilterPills() {
    var pills = document.querySelectorAll('.filter-pill');
    pills.forEach(function(pill) {
      pill.addEventListener('click', function() {
        currentFilter = pill.getAttribute('data-filter') || 'all';
        pills.forEach(function(p) {
          p.classList.remove('active');
          p.setAttribute('aria-selected', 'false');
        });
        pill.classList.add('active');
        pill.setAttribute('aria-selected', 'true');
        currentPage = 1;
        renderCurrentView();
      });
    });
  }

  function updateFilterCounts() {
    var countAll = document.getElementById('countAll');
    var countUpcoming = document.getElementById('countUpcoming');
    var countLive = document.getElementById('countLive');
    var countCompleted = document.getElementById('countCompleted');

    if (countAll) countAll.textContent = allMeetings.length;
    if (countUpcoming) countUpcoming.textContent = allMeetings.filter(function(m) {
      return FILTER_MAP.upcoming.indexOf(m.status) !== -1;
    }).length;
    if (countLive) countLive.textContent = allMeetings.filter(function(m) {
      return FILTER_MAP.live.indexOf(m.status) !== -1;
    }).length;
    if (countCompleted) countCompleted.textContent = allMeetings.filter(function(m) {
      return FILTER_MAP.completed.indexOf(m.status) !== -1;
    }).length;
  }

  // === SEARCH & SORT ===

  function initSearch() {
    var searchInput = document.getElementById('meetingsSearch');
    if (!searchInput) return;
    var handler = Utils.debounce(function() {
      searchText = searchInput.value.trim();
      currentPage = 1;
      renderCurrentView();
    }, 250);
    searchInput.addEventListener('input', handler);
  }

  function initSort() {
    var sortSelect = document.getElementById('meetingsSort');
    if (!sortSelect) return;
    sortSelect.addEventListener('change', function() {
      sortMode = sortSelect.value;
      renderCurrentView();
    });
  }

  // === FILTERING PIPELINE ===

  function getFilteredMeetings() {
    var filtered = allMeetings;

    // Apply filter pill
    if (currentFilter !== 'all' && FILTER_MAP[currentFilter]) {
      var statuses = FILTER_MAP[currentFilter];
      filtered = filtered.filter(function(m) {
        return statuses.indexOf(m.status) !== -1;
      });
    }

    // Apply search
    if (searchText) {
      var q = searchText.toLowerCase();
      filtered = filtered.filter(function(m) {
        return (m.title || '').toLowerCase().indexOf(q) !== -1;
      });
    }

    // Apply sort
    filtered = filtered.slice().sort(function(a, b) {
      switch (sortMode) {
        case 'date_asc':
          return new Date(a.scheduled_at || a.created_at || 0) - new Date(b.scheduled_at || b.created_at || 0);
        case 'title_asc':
          return (a.title || '').localeCompare(b.title || '', 'fr');
        case 'title_desc':
          return (b.title || '').localeCompare(a.title || '', 'fr');
        case 'date_desc':
        default:
          return new Date(b.scheduled_at || b.created_at || 0) - new Date(a.scheduled_at || a.created_at || 0);
      }
    });

    return filtered;
  }

  // === SESSION LIST RENDERING ===

  function renderSessionList() {
    if (!meetingsList) return;

    var filtered = getFilteredMeetings();
    var total = filtered.length;

    // Paginate
    var totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
    if (currentPage > totalPages) currentPage = totalPages;
    var start = (currentPage - 1) * PER_PAGE;
    var page = filtered.slice(start, start + PER_PAGE);

    // Update count
    if (meetingsCount) {
      meetingsCount.textContent = total + ' s\u00e9ance' + (total !== 1 ? 's' : '');
    }

    // Empty state
    if (total === 0) {
      meetingsList.innerHTML = renderEmptyState(currentFilter);
      if (meetingsPagination) meetingsPagination.innerHTML = '';
      return;
    }

    // Render items
    var html = '';
    for (var i = 0; i < page.length; i++) {
      html += renderSessionItem(page[i]);
    }
    meetingsList.innerHTML = html;

    // Render pagination
    renderPagination(totalPages);
  }

  function renderSessionItem(m) {
    var id = m.id || m.meeting_id;
    var status = m.status || 'draft';
    var statusLabel = STATUS_LABELS[status] || status;
    var typeLabel = TYPE_LABELS[m.meeting_type] || 'S\u00e9ance';
    var ctaLabel = getCtaLabel(status);
    var ctaHref = getCtaHref(status, id);

    return '<div class="session-card" data-id="' + id + '" data-status="' + status + '">' +
      '<div class="session-card-info">' +
        '<div class="session-card-title">' + Utils.escapeHtml(m.title || '') + '</div>' +
        '<div class="session-card-meta">' +
          '<span class="session-card-date">' + Utils.formatDate(m.scheduled_at) + '</span>' +
          '<span class="session-card-meta-sep">\u00b7</span>' +
          '<span class="session-meta-item participants">' + (m.participant_count || 0) + ' participants</span>' +
          '<span class="session-card-meta-sep session-meta-item resolutions">\u00b7</span>' +
          '<span class="session-meta-item resolutions">' + (m.motions_count || 0) + ' r\u00e9solutions</span>' +
        '</div>' +
      '</div>' +
      '<span class="meeting-type-badge">' + Utils.escapeHtml(typeLabel) + '</span>' +
      '<span class="meeting-card-status ' + status + '">' +
        '<span class="status-dot"></span>' +
        statusLabel +
      '</span>' +
      '<a class="btn btn-sm btn-primary session-card-cta" href="' + ctaHref + '" onclick="event.stopPropagation()">' + ctaLabel + '</a>' +
      '<button class="btn btn-ghost btn-icon btn-sm session-menu-btn" data-meeting-id="' + id + '" aria-label="Actions" onclick="event.stopPropagation()">' +
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>' +
      '</button>' +
    '</div>';
  }

  function getCtaLabel(status) {
    if (status === 'live' || status === 'paused') return 'Reprendre';
    if (status === 'closed' || status === 'validated' || status === 'archived' || status === 'pv_sent') return 'Voir r\u00e9sultats';
    return 'Ouvrir';
  }

  function getCtaHref(status, id) {
    if (status === 'closed' || status === 'validated' || status === 'archived' || status === 'pv_sent') {
      return '/postsession/' + id;
    }
    return '/hub/' + id;
  }

  function formatQuorum(m) {
    if (!m.quorum_rule) return 'Standard';
    var r = m.quorum_rule;
    if (r === 'majority') return 'Majorit\u00e9';
    if (r === 'two_thirds') return '2/3';
    if (r === 'unanimity') return 'Unanimit\u00e9';
    return r;
  }

  // === EMPTY STATES ===

  function renderEmptyState(filter) {
    if (filter === 'all' && allMeetings.length === 0) {
      return '<ag-empty-state icon="meetings" title="Aucune s\u00e9ance" description="Cr\u00e9ez votre premi\u00e8re s\u00e9ance pour commencer." action-label="Nouvelle s\u00e9ance" action-href="/wizard"></ag-empty-state>';
    }
    if (filter === 'upcoming') {
      return '<ag-empty-state icon="meetings" title="Aucune s\u00e9ance \u00e0 venir" description="Toutes vos s\u00e9ances sont termin\u00e9es ou en cours."></ag-empty-state>';
    }
    if (filter === 'live') {
      return '<ag-empty-state icon="meetings" title="Aucune s\u00e9ance en cours" description="Lancez une s\u00e9ance depuis la console op\u00e9rateur."></ag-empty-state>';
    }
    if (filter === 'completed') {
      return '<ag-empty-state icon="meetings" title="Aucune s\u00e9ance termin\u00e9e" description="Les s\u00e9ances termin\u00e9es appara\u00eetront ici."></ag-empty-state>';
    }
    // Filtered 'all' with search but no results
    return '<ag-empty-state icon="generic" title="Aucun r\u00e9sultat" description="Essayez un autre terme de recherche."></ag-empty-state>';
  }

  // === POPOVER MENUS ===

  function initPopoverMenus() {
    if (!meetingsList) return;

    meetingsList.addEventListener('click', function(e) {
      var btn = e.target.closest('.session-menu-btn');
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();

      var meetingId = btn.getAttribute('data-meeting-id');
      var m = findMeetingById(meetingId);
      if (!m) return;

      // Close any existing popover
      var existing = document.querySelector('ag-popover.session-popover');
      if (existing) existing.remove();

      var items = buildPopoverItems(m);
      var popover = document.createElement('ag-popover');
      popover.className = 'session-popover';
      popover.setAttribute('items', JSON.stringify(items));
      popover.setAttribute('open', '');

      // Position near the button
      var rect = btn.getBoundingClientRect();
      popover.style.position = 'fixed';
      popover.style.top = (rect.bottom + 4) + 'px';
      popover.style.left = (rect.left - 120) + 'px';
      popover.style.zIndex = '100';

      document.body.appendChild(popover);

      // Handle item selection
      popover.addEventListener('select', function(ev) {
        var action = ev.detail?.value || ev.detail;
        handlePopoverAction(action, m);
        popover.remove();
      });

      // Close on click outside (next tick)
      setTimeout(function() {
        var closeHandler = function(ev) {
          if (!popover.contains(ev.target) && ev.target !== btn) {
            popover.remove();
            document.removeEventListener('click', closeHandler);
          }
        };
        document.addEventListener('click', closeHandler);
      }, 0);
    });
  }

  function buildPopoverItems(m) {
    var status = m.status || 'draft';
    var id = m.id || m.meeting_id;

    if (status === 'draft') {
      return [
        { label: 'Modifier', value: 'edit', icon: 'edit' },
        { label: 'Supprimer', value: 'delete', icon: 'trash', variant: 'danger' }
      ];
    }
    if (status === 'scheduled' || status === 'frozen' || status === 'convocations') {
      return [
        { label: 'Voir', value: 'view', icon: 'eye' },
        { label: 'Annuler', value: 'cancel', icon: 'x-circle' }
      ];
    }
    // completed / archived / live / paused
    return [
      { label: 'Voir', value: 'view', icon: 'eye' },
      { label: 'Archiver', value: 'archive', icon: 'archive' }
    ];
  }

  function handlePopoverAction(action, m) {
    var id = m.id || m.meeting_id;
    switch (action) {
      case 'view':
        location.href = '/hub/' + id;
        break;
      case 'edit':
        openEditModal(id);
        break;
      case 'delete':
        openDeleteModal(id);
        break;
      case 'cancel':
      case 'archive':
        if (typeof setNotif === 'function') setNotif('info', 'Non impl\u00e9ment\u00e9');
        break;
    }
  }

  // === EDIT/DELETE MODALS ===

  function findMeetingById(id) {
    return allMeetings.find(function(m) {
      return String(m.id) === String(id) || String(m.meeting_id) === String(id);
    });
  }

  function openEditModal(meetingId) {
    var m = findMeetingById(meetingId);
    if (!m) return;
    var modal = document.getElementById('editMeetingModal');
    if (!modal) return;

    document.getElementById('editMeetingId').value = m.id || m.meeting_id;
    document.getElementById('editMeetingTitle').value = m.title || '';
    document.getElementById('editMeetingDate').value = m.scheduled_at ? m.scheduled_at.slice(0, 10) : '';

    var typeVal = m.meeting_type || 'ag_ordinaire';
    var typeRadio = modal.querySelector('input[name="editMeetingType"][value="' + typeVal + '"]');
    if (typeRadio) typeRadio.checked = true;

    modal.hidden = false;
    document.getElementById('editMeetingTitle').focus();
  }

  function closeEditModal() {
    var modal = document.getElementById('editMeetingModal');
    if (modal) modal.hidden = true;
  }

  async function saveEditMeeting() {
    var meetingId = document.getElementById('editMeetingId').value;
    var titleEl = document.getElementById('editMeetingTitle');
    var dateEl = document.getElementById('editMeetingDate');
    var modal = document.getElementById('editMeetingModal');

    if (!Shared.validateField(titleEl, [
      { test: function(v) { return v.length > 0; }, msg: 'Le titre est requis' }
    ])) return;

    var title = titleEl.value.trim();
    var scheduledAt = dateEl.value;
    var typeRadio = modal.querySelector('input[name="editMeetingType"]:checked');
    var meetingType = typeRadio ? typeRadio.value : 'ag_ordinaire';

    var saveBtn = document.getElementById('editMeetingSaveBtn');
    Shared.btnLoading(saveBtn, true);

    try {
      var resp = await api('/api/v1/meetings_update.php', {
        meeting_id: meetingId,
        title: title,
        scheduled_at: scheduledAt || null,
        meeting_type: meetingType
      });

      if (resp.body && resp.body.ok) {
        setNotif('success', 'S\u00e9ance modifi\u00e9e');
        closeEditModal();
        loadMeetings();
      } else {
        setNotif('error', resp.body?.message || resp.body?.error || 'Erreur lors de la modification');
      }
    } catch (err) {
      setNotif('error', err.message);
    } finally {
      Shared.btnLoading(saveBtn, false);
    }
  }

  function openDeleteModal(meetingId) {
    var m = findMeetingById(meetingId);
    if (!m) return;
    var modal = document.getElementById('deleteMeetingModal');
    if (!modal) return;

    document.getElementById('deleteMeetingId').value = m.id || m.meeting_id;
    document.getElementById('deleteMeetingName').textContent = m.title || '(sans titre)';
    modal.hidden = false;
  }

  function closeDeleteModal() {
    var modal = document.getElementById('deleteMeetingModal');
    if (modal) modal.hidden = true;
  }

  async function confirmDeleteMeeting() {
    var meetingId = document.getElementById('deleteMeetingId').value;
    var btn = document.getElementById('deleteMeetingConfirmBtn');

    Shared.btnLoading(btn, true);
    try {
      var resp = await api('/api/v1/meetings_delete.php', { meeting_id: meetingId });

      if (resp.body && resp.body.ok) {
        setNotif('success', 'S\u00e9ance supprim\u00e9e');
        closeDeleteModal();
        loadMeetings();
      } else {
        setNotif('error', resp.body?.message || resp.body?.error || 'Erreur lors de la suppression');
      }
    } catch (err) {
      setNotif('error', err.message);
    } finally {
      Shared.btnLoading(btn, false);
    }
  }

  function initModals() {
    var editModal = document.getElementById('editMeetingModal');
    if (editModal) {
      editModal.querySelectorAll('[data-close-modal]').forEach(function(btn) {
        btn.addEventListener('click', closeEditModal);
      });
      editModal.addEventListener('click', function(e) {
        if (e.target === editModal) closeEditModal();
      });
      var saveBtn = document.getElementById('editMeetingSaveBtn');
      if (saveBtn) saveBtn.addEventListener('click', saveEditMeeting);
    }

    var deleteModal = document.getElementById('deleteMeetingModal');
    if (deleteModal) {
      deleteModal.querySelectorAll('[data-close-modal]').forEach(function(btn) {
        btn.addEventListener('click', closeDeleteModal);
      });
      deleteModal.addEventListener('click', function(e) {
        if (e.target === deleteModal) closeDeleteModal();
      });
      var confirmBtn = document.getElementById('deleteMeetingConfirmBtn');
      if (confirmBtn) confirmBtn.addEventListener('click', confirmDeleteMeeting);
    }

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        if (editModal && !editModal.hidden) closeEditModal();
        if (deleteModal && !deleteModal.hidden) closeDeleteModal();
      }
    });
  }

  // === CALENDAR VIEW ===

  function renderCalendar() {
    if (!calendarGrid || !calendarTitle) return;

    var year = calendarDate.getFullYear();
    var month = calendarDate.getMonth();

    // Title
    calendarTitle.textContent = MONTH_NAMES[month] + ' ' + year;

    // Group filtered meetings by date
    var filtered = getFilteredMeetings();
    var meetingsByDate = {};
    filtered.forEach(function(m) {
      if (!m.scheduled_at) return;
      var dateKey = m.scheduled_at.slice(0, 10);
      if (!meetingsByDate[dateKey]) meetingsByDate[dateKey] = [];
      meetingsByDate[dateKey].push(m);
    });

    // Build grid
    var html = '';

    // Day headers
    for (var d = 0; d < DAY_NAMES.length; d++) {
      html += '<div class="calendar-day-header">' + DAY_NAMES[d] + '</div>';
    }

    // Calculate first day offset (Monday = 0)
    var firstDay = new Date(year, month, 1).getDay();
    var offset = (firstDay === 0) ? 6 : firstDay - 1; // Convert Sunday=0 to Monday-first

    var daysInMonth = new Date(year, month + 1, 0).getDate();
    var today = new Date();
    var todayKey = today.getFullYear() + '-' +
      String(today.getMonth() + 1).padStart(2, '0') + '-' +
      String(today.getDate()).padStart(2, '0');

    // Previous month padding
    var prevMonthDays = new Date(year, month, 0).getDate();
    for (var p = offset - 1; p >= 0; p--) {
      var prevDay = prevMonthDays - p;
      html += '<div class="calendar-day other-month"><div class="calendar-day-num">' + prevDay + '</div></div>';
    }

    // Current month days
    for (var day = 1; day <= daysInMonth; day++) {
      var dateKey = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
      var isToday = dateKey === todayKey;
      var dayMeetings = meetingsByDate[dateKey] || [];

      var classes = 'calendar-day';
      if (isToday) classes += ' today';

      html += '<div class="' + classes + '" data-date="' + dateKey + '">';
      html += '<div class="calendar-day-num">' + day + '</div>';

      // Render meeting events
      if (dayMeetings.length > 0) {
        var shown = Math.min(dayMeetings.length, 3);
        for (var e = 0; e < shown; e++) {
          var evtStatus = dayMeetings[e].status || 'draft';
          var evtClass = DOT_CLASS_MAP[evtStatus] || 'draft';
          // Map dot class to calendar-event class
          var calClass = 'draft';
          if (evtClass === 'upcoming') calClass = 'scheduled';
          else if (evtClass === 'live') calClass = 'live';
          else if (evtClass === 'completed') calClass = 'draft'; // re-use neutral for completed
          html += '<a class="calendar-event ' + calClass + '" href="/hub/' + (dayMeetings[e].id || dayMeetings[e].meeting_id) + '">' +
            Utils.escapeHtml(dayMeetings[e].title || '') + '</a>';
        }
        if (dayMeetings.length > 3) {
          html += '<span class="calendar-day-count">+' + (dayMeetings.length - 3) + '</span>';
        }
      }

      html += '</div>';
    }

    // Next month padding
    var totalCells = offset + daysInMonth;
    var remainder = totalCells % 7;
    if (remainder > 0) {
      for (var n = 1; n <= 7 - remainder; n++) {
        html += '<div class="calendar-day other-month"><div class="calendar-day-num">' + n + '</div></div>';
      }
    }

    calendarGrid.innerHTML = html;

    // Day click popover
    initCalendarDayPopovers(meetingsByDate);
  }

  function initCalendarDayPopovers(meetingsByDate) {
    if (!calendarGrid) return;

    calendarGrid.addEventListener('click', function(e) {
      var dayCell = e.target.closest('.calendar-day:not(.other-month)');
      if (!dayCell) return;
      // Don't intercept clicks on event links
      if (e.target.closest('.calendar-event')) return;

      var dateKey = dayCell.getAttribute('data-date');
      if (!dateKey || !meetingsByDate[dateKey] || meetingsByDate[dateKey].length === 0) return;

      e.stopPropagation();

      // Remove existing popover
      var existing = document.querySelector('.calendar-day-popover');
      if (existing) existing.remove();

      var meetings = meetingsByDate[dateKey];
      var popover = document.createElement('div');
      popover.className = 'calendar-day-popover';

      var popHtml = '';
      meetings.forEach(function(m) {
        var dotClass = DOT_CLASS_MAP[m.status || 'draft'] || 'draft';
        var id = m.id || m.meeting_id;
        var time = m.scheduled_at ? Utils.formatDate(m.scheduled_at) : '';
        popHtml += '<a class="calendar-popover-item" href="/hub/' + id + '">' +
          '<span class="session-dot ' + dotClass + '" style="width:8px;height:8px;"></span>' +
          '<span>' + Utils.escapeHtml(m.title || '') + '</span>' +
          '<span style="color:var(--color-text-muted);font-size:11px;margin-left:auto;">' + Utils.escapeHtml(time) + '</span>' +
        '</a>';
      });

      popover.innerHTML = popHtml;

      // Position relative to the day cell
      dayCell.style.position = 'relative';
      popover.style.position = 'absolute';
      popover.style.top = '100%';
      popover.style.left = '0';
      dayCell.appendChild(popover);

      // Close on click outside
      setTimeout(function() {
        var closeHandler = function(ev) {
          if (!popover.contains(ev.target)) {
            popover.remove();
            document.removeEventListener('click', closeHandler);
          }
        };
        document.addEventListener('click', closeHandler);
      }, 0);
    });
  }

  function initCalendarNav() {
    var prevBtn = document.getElementById('calendarPrev');
    var nextBtn = document.getElementById('calendarNext');
    var todayBtn = document.getElementById('calendarToday');

    if (prevBtn) {
      prevBtn.addEventListener('click', function() {
        calendarDate.setMonth(calendarDate.getMonth() - 1);
        renderCalendar();
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function() {
        calendarDate.setMonth(calendarDate.getMonth() + 1);
        renderCalendar();
      });
    }
    if (todayBtn) {
      todayBtn.addEventListener('click', function() {
        calendarDate = new Date();
        renderCalendar();
      });
    }
  }

  // === VIEW TOGGLE ===

  function initViewToggle() {
    var toggleBtns = document.querySelectorAll('.view-toggle-btn');
    toggleBtns.forEach(function(btn) {
      btn.addEventListener('click', function() {
        currentView = btn.getAttribute('data-view') || 'list';
        toggleBtns.forEach(function(b) { b.classList.remove('active'); });
        btn.classList.add('active');

        if (meetingsList) meetingsList.style.display = (currentView === 'list') ? '' : 'none';
        if (meetingsPagination) meetingsPagination.style.display = (currentView === 'list') ? '' : 'none';
        if (calendarContainer) {
          if (currentView === 'calendar') {
            calendarContainer.classList.add('active');
          } else {
            calendarContainer.classList.remove('active');
          }
        }

        renderCurrentView();
      });
    });
  }

  function renderCurrentView() {
    if (currentView === 'list') {
      renderSessionList();
    } else {
      renderCalendar();
    }
  }

  // === PAGINATION ===

  function renderPagination(totalPages) {
    if (!meetingsPagination) return;
    if (totalPages <= 1) {
      meetingsPagination.innerHTML = '';
      return;
    }

    var html = '';

    // Previous button
    html += '<button class="btn btn-ghost btn-sm' + (currentPage <= 1 ? ' disabled' : '') + '" data-page="' + (currentPage - 1) + '"' + (currentPage <= 1 ? ' disabled' : '') + '>&laquo;</button>';

    for (var p = 1; p <= totalPages; p++) {
      var active = (p === currentPage) ? ' btn-primary' : ' btn-ghost';
      html += '<button class="btn btn-sm' + active + '" data-page="' + p + '">' + p + '</button>';
    }

    // Next button
    html += '<button class="btn btn-ghost btn-sm' + (currentPage >= totalPages ? ' disabled' : '') + '" data-page="' + (currentPage + 1) + '"' + (currentPage >= totalPages ? ' disabled' : '') + '>&raquo;</button>';

    meetingsPagination.innerHTML = html;

    meetingsPagination.addEventListener('click', function(e) {
      var btn = e.target.closest('[data-page]');
      if (!btn || btn.disabled) return;
      var page = parseInt(btn.getAttribute('data-page'), 10);
      if (page >= 1 && page <= totalPages) {
        currentPage = page;
        renderSessionList();
        // Scroll to top of list
        if (meetingsList) meetingsList.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  }

  // === ONBOARDING BANNER ===

  function updateOnboardingBanner() {
    var banner = document.getElementById('onboardingBanner');
    if (!banner) return;
    if (!banner.hasAttribute('data-session-driven')) return;

    var KEY = 'ag_meetings_ob_dismissed';
    if (localStorage.getItem(KEY) === '1') {
      banner.hidden = true;
      return;
    }

    if (allMeetings.length === 0) {
      banner.hidden = false;
    } else {
      banner.hidden = true;
      localStorage.setItem(KEY, '1');
    }
  }

  function initOnboardingBanner() {
    var banner = document.getElementById('onboardingBanner');
    if (!banner) return;

    var KEY = 'ag_meetings_ob_dismissed';
    if (localStorage.getItem(KEY) === '1') {
      banner.hidden = true;
      return;
    }

    var dismiss = function() {
      banner.hidden = true;
      localStorage.setItem(KEY, '1');
    };

    var btnClose = document.getElementById('btnCloseOnboarding');
    var btnDismiss = document.getElementById('btnDismissOnboarding');
    if (btnClose) btnClose.addEventListener('click', dismiss);
    if (btnDismiss) btnDismiss.addEventListener('click', dismiss);
  }

  // === INIT ===

  document.addEventListener('DOMContentLoaded', function() {
    initFilterPills();
    initSearch();
    initSort();
    initViewToggle();
    initCalendarNav();
    initPopoverMenus();
    initModals();
    initOnboardingBanner();
    loadMeetings();
  });

})();
