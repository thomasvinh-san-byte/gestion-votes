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
    const live = meetings.filter(m => m.status === 'live').length;
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

  /**
   * Filter meetings based on current filter.
   * @param {Array} meetings - All meetings
   * @param {string} filter - Filter value
   * @returns {Array} Filtered meetings
   */
  function filterMeetings(meetings, filter) {
    if (filter === 'all') return meetings;
    if (filter === 'live') return meetings.filter(m => m.status === 'live');
    if (filter === 'scheduled') return meetings.filter(m => ['scheduled', 'frozen'].includes(m.status));
    if (filter === 'draft') return meetings.filter(m => m.status === 'draft');
    if (filter === 'archived') return meetings.filter(m => ['closed', 'validated', 'archived'].includes(m.status));
    return meetings;
  }

  /**
   * Sort meetings based on the current sort mode.
   * @param {Object} a - First meeting
   * @param {Object} b - Second meeting
   * @returns {number} Sort order
   */
  function sortMeetings(a, b) {
    if (currentSortMode === 'date_desc') {
      var dateA = a.scheduled_at || a.created_at || '';
      var dateB = b.scheduled_at || b.created_at || '';
      return new Date(dateB) - new Date(dateA);
    }
    if (currentSortMode === 'date_asc') {
      var dateA2 = a.scheduled_at || a.created_at || '';
      var dateB2 = b.scheduled_at || b.created_at || '';
      return new Date(dateA2) - new Date(dateB2);
    }
    if (currentSortMode === 'title') {
      return (a.title || '').localeCompare(b.title || '', 'fr');
    }
    // Default: sort by status then date
    const statusOrder = { live: 0, frozen: 1, scheduled: 2, draft: 3, closed: 4, validated: 5, archived: 6 };
    const orderA = statusOrder[a.status] ?? 99;
    const orderB = statusOrder[b.status] ?? 99;
    if (orderA !== orderB) return orderA - orderB;
    return new Date(b.created_at) - new Date(a.created_at);
  }

  /**
   * Filter meetings by search text.
   * @param {Array} meetings - Meetings to filter
   * @param {string} searchText - Search query
   * @returns {Array} Filtered meetings
   */
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

  /**
   * Render a single meeting card.
   * @param {Object} m - Meeting data
   * @returns {string} HTML string
   */
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
    const isLive = m.status === 'live';
    const isDraft = m.status === 'draft';
    const isScheduled = m.status === 'scheduled' || m.status === 'frozen';
    const isArchived = ['closed', 'validated', 'archived'].includes(m.status);

    let cardClass = '';
    if (isLive) cardClass = 'is-live';
    else if (isDraft) cardClass = 'is-draft';
    else if (isArchived) cardClass = 'is-archived';

    // Format date nicely
    const date = m.scheduled_at
      ? new Date(m.scheduled_at).toLocaleDateString('fr-FR', {
          weekday: 'short',
          day: 'numeric',
          month: 'short',
          year: 'numeric'
        })
      : '—';

    // Badge with dot indicator for live status
    const badgeClass = isLive
      ? `${statusInfo.badge} badge-sm badge-dot`
      : `${statusInfo.badge} badge-sm`;

    // Button variant based on status
    const btnClass = isLive
      ? 'btn btn-sm btn-success'
      : (isDraft ? 'btn btn-sm btn-secondary' : 'btn btn-sm btn-primary');
    const btnText = isLive ? 'Rejoindre' : 'Ouvrir';

    // Motions and attendees with labels
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

  /**
   * Render meetings grid with search, sort, and pagination.
   * @param {Array} meetings - Meetings to render
   */
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
          : 'Commencez par préparer une nouvelle séance avec le formulaire ci-dessus. Renseignez un titre, puis cliquez sur « Préparer la séance ».',
        actionHtml: '<div style="grid-column:1/-1;"></div>'
      });
      return;
    }

    // Pagination: show first N * currentPage items
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

    // Wire up "Show more" button
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

  /**
   * Fetch meetings from API.
   */
  async function fetchMeetings() {
    if (meetingsList) {
      meetingsList.innerHTML = '<div class="text-center p-6 text-muted" style="grid-column: 1 / -1;">Chargement...</div>';
    }

    try {
      const { body } = await api('/api/v1/meetings_index.php');
      allMeetings = body?.data?.meetings || [];
      updateStats(allMeetings);
      renderMeetings(allMeetings);

      // Update calendar if active
      if (calendarView) {
        calendarView.setEvents(filterMeetings(allMeetings, currentFilter));
      }
    } catch (err) {
      if (meetingsList) {
        meetingsList.innerHTML = `
          <div class="alert alert-danger" style="grid-column: 1 / -1;">
            Erreur: ${escapeHtml(err.message)}
          </div>
        `;
      }
    }
  }

  // ==========================================================================
  // MEETING CREATION
  // ==========================================================================

  /**
   * Create a new meeting.
   */
  async function createMeeting() {
    const title = titleInput?.value.trim();
    if (!title) {
      setNotif('error', 'Le titre est requis');
      titleInput?.focus();
      return;
    }

    const scheduled_at = dateInput?.value || null;
    if (!scheduled_at) {
      setNotif('error', 'La date de la séance est requise');
      dateInput?.focus();
      return;
    }

    const meetingTypeRadio = document.querySelector('input[name="meetingTypeCreate"]:checked');
    const meeting_type = meetingTypeRadio ? meetingTypeRadio.value : 'ag_ordinaire';

    Shared.btnLoading(createBtn, true);
    try {
      const { body } = await api('/api/v1/meetings.php', { title, scheduled_at, meeting_type });

      if (body && body.ok) {
        setNotif('success', 'Séance créée — passons à la préparation');
        if (titleInput) titleInput.value = '';
        if (dateInput) dateInput.value = '';

        // Redirect to operator page
        const mid = body.data?.meeting_id;
        if (mid) {
          window.location.href = `/operator.htmx.html?meeting_id=${mid}`;
        } else {
          fetchMeetings();
        }
      } else {
        setNotif('error', body?.error || 'Erreur');
      }
    } catch (err) {
      setNotif('error', err.message);
    } finally {
      Shared.btnLoading(createBtn, false);
    }
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

    // Enter key in title input
    if (titleInput) {
      titleInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') createMeeting();
      });
    }

    // Initial data load
    fetchMeetings();
  }

  // ==========================================================================
  // LEGACY EXPORTS (for backward compatibility)
  // ==========================================================================

  /**
   * A meeting is "active" if it is not archived.
   */
  function isActiveMeeting(m) {
    return m.status !== 'archived';
  }

  /**
   * A meeting is in history if it is closed or archived.
   */
  function isHistoryMeeting(m) {
    return m.status === 'closed' || m.status === 'archived';
  }

  // Export for legacy usage
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
