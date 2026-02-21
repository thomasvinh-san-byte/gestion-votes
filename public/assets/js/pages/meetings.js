/**
 * meetings.js - Meetings page logic for AG-VOTE.
 *
 * Handles meeting list display, creation, filtering, and calendar view.
 * Uses PageComponents for reusable UI functionality.
 *
 * @module meetings
 * @requires utils.js (api, escapeHtml, setNotif)
 * @requires shared.js (Shared.MEETING_STATUS_MAP, Shared.btnLoading)
 * @requires page-components.js (PageComponents)
 */
(function() {
  'use strict';

  // ==========================================================================
  // STATE
  // ==========================================================================

  let allMeetings = [];
  let currentFilter = 'all';
  let currentSearchText = '';
  let currentSortMode = 'status';
  let currentPage = 1;
  var MEETINGS_PER_PAGE = 12;
  let filterManager = null;
  let viewToggle = null;
  let calendarView = null;
  let dataGrid = null;

  // File attachment state
  let pendingFiles = [];

  // ==========================================================================
  // DOM ELEMENTS
  // ==========================================================================

  const titleInput = document.getElementById('meeting_title');
  const dateInput = document.getElementById('meeting_date');
  const createBtn = document.getElementById('create_meeting_btn');
  const meetingsList = document.getElementById('meetingsList');
  const meetingsCount = document.getElementById('meetingsCount');

  // ==========================================================================
  // STATS
  // ==========================================================================

  /**
   * Update stats bar with meeting counts.
   * @param {Array} meetings - All meetings
   */
  function updateStats(meetings) {
    const live = meetings.filter(m => m.status === 'live' || m.status === 'paused').length;
    const scheduled = meetings.filter(m => ['scheduled', 'frozen'].includes(m.status)).length;
    const draft = meetings.filter(m => m.status === 'draft').length;

    const statLive = document.getElementById('statLive');
    const statScheduled = document.getElementById('statScheduled');
    const statDraft = document.getElementById('statDraft');
    const statTotal = document.getElementById('statTotal');

    if (statLive) statLive.textContent = live;
    if (statScheduled) statScheduled.textContent = scheduled;
    if (statDraft) statDraft.textContent = draft;
    if (statTotal) statTotal.textContent = meetings.length;

    // Total resolutions across all meetings
    const totalResolutions = meetings.reduce(function(sum, m) {
      return sum + (m.motions_count || m.resolution_count || 0);
    }, 0);
    const statResolutions = document.getElementById('statResolutions');
    if (statResolutions) statResolutions.textContent = totalResolutions;

    // Average participation rate
    var participationValues = [];
    meetings.forEach(function(m) {
      if (m.participation_rate != null) {
        participationValues.push(parseFloat(m.participation_rate));
      } else if (m.attendees_count != null && m.total_members != null && m.total_members > 0) {
        participationValues.push((m.attendees_count / m.total_members) * 100);
      }
    });
    var statAvgParticipation = document.getElementById('statAvgParticipation');
    if (statAvgParticipation) {
      if (participationValues.length > 0) {
        var avg = participationValues.reduce(function(s, v) { return s + v; }, 0) / participationValues.length;
        statAvgParticipation.textContent = Math.round(avg) + '%';
      } else {
        statAvgParticipation.textContent = '\u2014';
      }
    }
  }

  // ==========================================================================
  // FILTERING
  // ==========================================================================

  function filterMeetings(meetings, filter) {
    if (filter === 'all') return meetings;
    if (filter === 'live') return meetings.filter(m => m.status === 'live' || m.status === 'paused');
    if (filter === 'scheduled') return meetings.filter(m => ['scheduled', 'frozen'].includes(m.status));
    if (filter === 'draft') return meetings.filter(m => m.status === 'draft');
    if (filter === 'archived') return meetings.filter(m => ['closed', 'validated', 'archived'].includes(m.status));
    return meetings;
  }

  function sortMeetings(a, b) {
    if (currentSortMode === 'date_desc') {
      var dateA = a.scheduled_at || a.created_at || '';
      var dateB = b.scheduled_at || b.created_at || '';
      return new Date(dateB) - new Date(dateA);
    }
    if (currentSortMode === 'date_asc') {
      var dateA = a.scheduled_at || a.created_at || '';
      var dateB = b.scheduled_at || b.created_at || '';
      return new Date(dateA) - new Date(dateB);
    }
    if (currentSortMode === 'title') {
      return (a.title || '').localeCompare(b.title || '', 'fr');
    }
    // Default: sort by status then date
    const statusOrder = { live: 0, paused: 1, frozen: 2, scheduled: 3, draft: 4, closed: 5, validated: 6, archived: 7 };
    const orderA = statusOrder[a.status] ?? 99;
    const orderB = statusOrder[b.status] ?? 99;
    if (orderA !== orderB) return orderA - orderB;
    return new Date(b.created_at) - new Date(a.created_at);
  }

  function searchMeetings(meetings, searchText) {
    if (!searchText) return meetings;
    var lower = searchText.toLowerCase();
    return meetings.filter(function(m) {
      return (m.title || '').toLowerCase().indexOf(lower) !== -1;
    });
  }

  // ==========================================================================
  // RENDERING
  // ==========================================================================

  const MEETING_TYPE_LABELS = {
    ag_ordinaire: 'AG ordinaire',
    ag_extraordinaire: 'AG extraordinaire',
    conseil: 'Conseil',
    bureau: 'Bureau',
    autre: 'Autre'
  };

  function renderMeetingCard(m) {
    const title = escapeHtml(m.title || '(sans titre)');
    const statusInfo = Shared.MEETING_STATUS_MAP[m.status] || Shared.MEETING_STATUS_MAP['draft'];
    const typeLabel = MEETING_TYPE_LABELS[m.meeting_type] || '';
    const isLive = m.status === 'live' || m.status === 'paused';
    const isDraft = m.status === 'draft';
    const isScheduled = m.status === 'scheduled' || m.status === 'frozen';
    const isArchived = ['closed', 'validated', 'archived'].includes(m.status);

    let cardClass = '';
    if (isLive) cardClass = 'is-live';
    else if (isDraft) cardClass = 'is-draft';
    else if (isArchived) cardClass = 'is-archived';

    const date = m.scheduled_at
      ? new Date(m.scheduled_at).toLocaleDateString('fr-FR', {
          weekday: 'short',
          day: 'numeric',
          month: 'short',
          year: 'numeric'
        })
      : '\u2014';

    const badgeClass = isLive
      ? `${statusInfo.badge} badge-sm badge-dot`
      : `${statusInfo.badge} badge-sm`;

    const btnClass = isLive
      ? 'btn btn-sm btn-success'
      : (isDraft ? 'btn btn-sm btn-secondary' : 'btn btn-sm btn-primary');
    const btnText = isLive ? 'Rejoindre' : 'Ouvrir';

    const motionsCount = m.motions_count || 0;
    const attendeesCount = m.attendees_count || 0;
    const motionsLabel = motionsCount === 1 ? 'résolution' : 'résolutions';
    const attendeesLabel = attendeesCount === 1 ? 'présent' : 'présents';

    return `
      <div class="meeting-card ${cardClass}" data-meeting-id="${m.id}" data-search-text="${escapeHtml((m.title || '') + ' ' + (m.status || ''))}">
        <div class="meeting-card-header">
          <h3 class="meeting-card-title">${title}</h3>
          <div class="meeting-card-meta">
            <span class="badge ${badgeClass}">${statusInfo.text}</span>
            ${typeLabel ? `<span class="badge badge-muted badge-sm">${typeLabel}</span>` : ''}
            <span class="meeting-date">
              <svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-calendar"></use></svg>
              ${date}
            </span>
          </div>
        </div>
        <div class="meeting-card-body">
          <div class="meeting-stats">
            <span title="${motionsCount} ${motionsLabel}">
              <svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-clipboard-list"></use></svg>
              <strong>${motionsCount}</strong> ${motionsLabel}
            </span>
            <span title="${attendeesCount} ${attendeesLabel}">
              <svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-users"></use></svg>
              <strong>${attendeesCount}</strong> ${attendeesLabel}
            </span>
          </div>
          <div class="meeting-card-actions">
            <a class="${btnClass}" href="/operator.htmx.html?meeting_id=${m.id}">
              ${isLive ? '<svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-play"></use></svg>' : ''}
              ${btnText}
            </a>
          </div>
        </div>
      </div>
    `;
  }

  function renderMeetings(meetings) {
    const filtered = filterMeetings(meetings, currentFilter);
    const searched = searchMeetings(filtered, currentSearchText);
    const sorted = [...searched].sort(sortMeetings);

    if (meetingsCount) {
      meetingsCount.textContent = `${sorted.length} séance${sorted.length > 1 ? 's' : ''}`;
    }

    if (!meetingsList) return;

    if (sorted.length === 0) {
      meetingsList.innerHTML = Shared.emptyState({
        icon: 'meetings',
        title: 'Aucune séance',
        description: currentSearchText
          ? 'Aucune séance ne correspond à votre recherche.'
          : 'Commencez par préparer une nouvelle séance avec le formulaire ci-dessus. Renseignez un titre, puis cliquez sur \u00ab Préparer la séance \u00bb.',
        actionHtml: '<div style="grid-column:1/-1;"></div>'
      });
      return;
    }

    var visibleCount = currentPage * MEETINGS_PER_PAGE;
    var visible = sorted.slice(0, visibleCount);
    var hasMore = sorted.length > visibleCount;

    var html = visible.map(renderMeetingCard).join('');

    if (hasMore) {
      var remaining = sorted.length - visibleCount;
      html += '<div style="grid-column:1/-1; text-align:center; padding:1rem 0;">' +
        '<button class="btn btn-secondary" id="meetingsShowMore">' +
        'Afficher plus (' + remaining + ' restante' + (remaining > 1 ? 's' : '') + ')' +
        '</button></div>';
    }

    meetingsList.innerHTML = html;

    if (hasMore) {
      var showMoreBtn = document.getElementById('meetingsShowMore');
      if (showMoreBtn) {
        showMoreBtn.addEventListener('click', function() {
          currentPage++;
          renderMeetings(allMeetings);
        });
      }
    }
  }

  // ==========================================================================
  // DATA LOADING
  // ==========================================================================

  async function fetchMeetings() {
    if (meetingsList) {
      meetingsList.innerHTML = '<div class="text-center p-6 text-muted" style="grid-column: 1 / -1;">Chargement...</div>';
    }

    await Shared.withRetry({
      container: meetingsList,
      maxRetries: 1,
      errorMsg: 'Impossible de charger les séances',
      action: async function () {
        const { body } = await api('/api/v1/meetings_index.php');
        allMeetings = body?.data?.items || [];
        updateStats(allMeetings);
        renderMeetings(allMeetings);

        if (calendarView) {
          calendarView.setEvents(filterMeetings(allMeetings, currentFilter));
        }
      }
    });
  }

  // ==========================================================================
  // FILE ATTACHMENTS
  // ==========================================================================

  function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' o';
    if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' Ko';
    return (bytes / (1024 * 1024)).toFixed(1) + ' Mo';
  }

  function renderFileList() {
    const fileList = document.getElementById('fileList');
    const dropContent = document.querySelector('.file-drop-content');
    if (!fileList) return;

    if (pendingFiles.length === 0) {
      fileList.innerHTML = '';
      if (dropContent) dropContent.style.display = '';
      return;
    }

    if (dropContent) dropContent.style.display = 'none';

    fileList.innerHTML = pendingFiles.map(function(f, i) {
      return '<div class="file-item" data-idx="' + i + '">' +
        '<svg class="icon icon-text" aria-hidden="true" style="color:var(--color-danger);flex-shrink:0;"><use href="/assets/icons.svg#icon-clipboard-list"></use></svg>' +
        '<span class="file-item-name">' + escapeHtml(f.name) + '</span>' +
        '<span class="file-item-size">' + formatFileSize(f.size) + '</span>' +
        '<button type="button" class="file-item-remove" data-remove="' + i + '" title="Retirer">&times;</button>' +
        '</div>';
    }).join('');

    // Wire remove buttons
    fileList.querySelectorAll('.file-item-remove').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var idx = parseInt(btn.dataset.remove, 10);
        pendingFiles.splice(idx, 1);
        renderFileList();
      });
    });
  }

  function addFiles(fileListInput) {
    var maxSize = 10 * 1024 * 1024;
    for (var i = 0; i < fileListInput.length; i++) {
      var f = fileListInput[i];
      if (f.type !== 'application/pdf') {
        setNotif('error', 'Seuls les fichiers PDF sont acceptés : ' + f.name);
        continue;
      }
      if (f.size > maxSize) {
        setNotif('error', 'Fichier trop volumineux (max 10 Mo) : ' + f.name);
        continue;
      }
      // Avoid duplicates
      var exists = pendingFiles.some(function(p) { return p.name === f.name && p.size === f.size; });
      if (!exists) {
        pendingFiles.push(f);
      }
    }
    renderFileList();
  }

  function initFileUpload() {
    var dropZone = document.getElementById('fileDropZone');
    var fileInput = document.getElementById('meeting_files');
    var browseBtn = document.getElementById('fileBrowseBtn');

    if (!dropZone || !fileInput) return;

    // Browse button opens file picker
    if (browseBtn) {
      browseBtn.addEventListener('click', function(e) {
        e.preventDefault();
        fileInput.click();
      });
    }

    // File input change
    fileInput.addEventListener('change', function() {
      addFiles(fileInput.files);
      fileInput.value = '';
    });

    // Click on drop zone opens file picker (if not clicking a button)
    dropZone.addEventListener('click', function(e) {
      if (e.target.closest('button') || e.target.closest('.file-item')) return;
      fileInput.click();
    });

    // Drag & drop
    dropZone.addEventListener('dragover', function(e) {
      e.preventDefault();
      dropZone.classList.add('drag-over');
    });

    dropZone.addEventListener('dragleave', function(e) {
      e.preventDefault();
      dropZone.classList.remove('drag-over');
    });

    dropZone.addEventListener('drop', function(e) {
      e.preventDefault();
      dropZone.classList.remove('drag-over');
      if (e.dataTransfer.files.length) {
        addFiles(e.dataTransfer.files);
      }
    });
  }

  async function uploadAttachments(meetingId) {
    for (var i = 0; i < pendingFiles.length; i++) {
      var formData = new FormData();
      formData.append('meeting_id', meetingId);
      formData.append('file', pendingFiles[i]);

      try {
        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
          || (window.CSRF && window.CSRF.token) || '';
        var headers = {};
        if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
        await fetch('/api/v1/meeting_attachments.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
          headers: headers
        });
      } catch (err) {
        setNotif('warning', 'Échec de l\'envoi du fichier : ' + (pendingFiles[i]?.name || 'inconnu'));
      }
    }
  }

  // ==========================================================================
  // MEETING CREATION
  // ==========================================================================

  async function createMeeting() {
    // Inline validation
    var valid = true;
    if (!Shared.validateField(titleInput, [
      { test: function (v) { return v.length > 0; }, msg: 'Le titre de la séance est requis' }
    ])) valid = false;
    if (!Shared.validateField(dateInput, [
      { test: function (v) { return v.length > 0; }, msg: 'La date est requise' }
    ])) valid = false;
    if (!valid) return;

    const title = titleInput.value.trim();
    const scheduled_at = dateInput.value;

    const meetingTypeRadio = document.querySelector('input[name="meetingTypeCreate"]:checked');
    const meeting_type = meetingTypeRadio ? meetingTypeRadio.value : 'ag_ordinaire';

    Shared.btnLoading(createBtn, true);
    try {
      const { body } = await api('/api/v1/meetings.php', { title, scheduled_at, meeting_type });

      if (body && body.ok) {
        const mid = body.data?.meeting_id;

        // Upload pending attachments if any
        if (mid && pendingFiles.length > 0) {
          await uploadAttachments(mid);
        }

        setNotif('success', 'Séance créée — passons à la préparation');
        if (titleInput) { titleInput.value = ''; Shared.fieldClear(titleInput); }
        if (dateInput) { dateInput.value = ''; Shared.fieldClear(dateInput); }
        pendingFiles = [];
        renderFileList();

        if (mid) {
          window.location.href = `/operator.htmx.html?meeting_id=${mid}`;
        } else {
          fetchMeetings();
        }
      } else {
        var errMsg = body?.message || body?.error || 'Erreur lors de la création';
        if (body?.detail) errMsg += ' — ' + body.detail;
        setNotif('error', errMsg);
      }
    } catch (err) {
      setNotif('error', err.message);
    } finally {
      Shared.btnLoading(createBtn, false);
    }
  }

  // ==========================================================================
  // DATE PICKER ENHANCEMENT
  // ==========================================================================

  function initDatePicker() {
    if (!dateInput) return;

    // Set default date to today if empty
    if (!dateInput.value) {
      var today = new Date();
      var yyyy = today.getFullYear();
      var mm = String(today.getMonth() + 1).padStart(2, '0');
      var dd = String(today.getDate()).padStart(2, '0');
      dateInput.value = yyyy + '-' + mm + '-' + dd;
    }

    // Open native date picker on click (for browsers that support showPicker)
    dateInput.addEventListener('click', function() {
      if (typeof dateInput.showPicker === 'function') {
        try { dateInput.showPicker(); } catch (e) { /* ignore */ }
      }
    });
  }

  // ==========================================================================
  // INITIALIZATION
  // ==========================================================================

  function init() {
    // Initialize filter manager
    filterManager = new PageComponents.FilterManager({
      containerSelector: '.filter-tabs',
      defaultFilter: 'all',
      onChange: function(filter) {
        currentFilter = filter;
        renderMeetings(allMeetings);
        if (calendarView) {
          calendarView.setEvents(filterMeetings(allMeetings, currentFilter));
        }
      }
    });

    // Initialize view toggle
    viewToggle = new PageComponents.ViewToggle({
      containerSelector: '.view-toggle',
      defaultView: 'grid',
      views: {
        grid: {
          element: '#meetingsList',
          onActivate: function() {}
        },
        calendar: {
          element: '#calendarContainer',
          onActivate: function() {
            if (calendarView) {
              calendarView.render();
            }
          }
        }
      }
    });

    // Initialize calendar view
    calendarView = new PageComponents.CalendarView({
      container: '#calendarGrid',
      titleElement: '#calendarTitle',
      events: [],
      onEventClick: function(event) {
        window.location.href = '/operator.htmx.html?meeting_id=' + event.id;
      }
    });

    // Calendar navigation buttons
    const calendarPrev = document.getElementById('calendarPrev');
    const calendarNext = document.getElementById('calendarNext');
    const calendarToday = document.getElementById('calendarToday');

    if (calendarPrev) {
      calendarPrev.addEventListener('click', function() {
        calendarView.prevMonth();
      });
    }
    if (calendarNext) {
      calendarNext.addEventListener('click', function() {
        calendarView.nextMonth();
      });
    }
    if (calendarToday) {
      calendarToday.addEventListener('click', function() {
        calendarView.today();
      });
    }

    // Search input
    var meetingsSearchInput = document.getElementById('meetingsSearch');
    if (meetingsSearchInput) {
      var searchTimeout = null;
      meetingsSearchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
          currentSearchText = meetingsSearchInput.value.trim();
          currentPage = 1;
          renderMeetings(allMeetings);
        }, 250);
      });
    }

    // Sort select
    var meetingsSortSelect = document.getElementById('meetingsSort');
    if (meetingsSortSelect) {
      meetingsSortSelect.addEventListener('change', function() {
        currentSortMode = meetingsSortSelect.value;
        currentPage = 1;
        renderMeetings(allMeetings);
      });
    }

    // Create meeting button
    if (createBtn) {
      createBtn.addEventListener('click', createMeeting);
    }

    // Live validation on create form fields
    if (titleInput) {
      Shared.liveValidate(titleInput, [
        { test: function (v) { return v.length > 0; }, msg: 'Le titre est requis' }
      ]);
      titleInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') createMeeting();
      });
    }
    if (dateInput) {
      Shared.liveValidate(dateInput, [
        { test: function (v) { return v.length > 0; }, msg: 'La date est requise' }
      ]);
    }

    // Initialize date picker enhancements
    initDatePicker();

    // Initialize file upload zone
    initFileUpload();

    // Initial data load
    fetchMeetings();
  }

  // ==========================================================================
  // LEGACY EXPORTS (for backward compatibility)
  // ==========================================================================

  function isActiveMeeting(m) {
    return m.status !== 'archived';
  }

  function isHistoryMeeting(m) {
    return m.status === 'closed' || m.status === 'archived';
  }

  window.MeetingsPage = {
    fetchMeetings: fetchMeetings,
    renderMeetings: renderMeetings,
    isActiveMeeting: isActiveMeeting,
    isHistoryMeeting: isHistoryMeeting
  };

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
