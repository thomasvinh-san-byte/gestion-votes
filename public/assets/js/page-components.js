/**
 * page-components.js - Reusable UI components for AG-VOTE pages.
 *
 * Provides:
 * - TabManager: Tab navigation with ARIA support
 * - FilterManager: Filter tabs with callbacks
 * - ViewToggle: Grid/Calendar/List view switching
 * - CalendarView: Monthly calendar with events
 * - CollapsibleSection: Expandable sections
 * - SearchFilter: Real-time search filtering
 *
 * @module page-components
 * @requires utils.js (escapeHtml)
 */
(function() {
  'use strict';

  // ==========================================================================
  // TAB MANAGER
  // ==========================================================================

  /**
   * TabManager - Handles tab navigation with ARIA support.
   *
   * @example
   * const tabs = new TabManager({
   *   navSelector: '.tabs-nav',
   *   contentSelector: '.tab-content',
   *   onChange: (tabId) => console.log('Tab changed:', tabId)
   * });
   */
  class TabManager {
    /**
     * @param {Object} options
     * @param {string} options.navSelector - Selector for tab navigation container
     * @param {string} options.contentSelector - Selector for tab content panels
     * @param {Function} [options.onChange] - Callback when tab changes
     */
    constructor(options) {
      this.navContainer = document.querySelector(options.navSelector);
      this.contentSelector = options.contentSelector;
      this.onChange = options.onChange || function() {};
      this.activeTab = null;

      if (this.navContainer) {
        this.init();
      }
    }

    init() {
      const buttons = this.navContainer.querySelectorAll('.tab-btn, [data-tab]');
      buttons.forEach(btn => {
        btn.addEventListener('click', () => this.switchTo(btn.dataset.tab));
      });

      // Keyboard navigation
      this.navContainer.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
          const btns = Array.from(buttons);
          const current = btns.findIndex(b => b.classList.contains('active'));
          let next = e.key === 'ArrowRight' ? current + 1 : current - 1;
          if (next < 0) next = btns.length - 1;
          if (next >= btns.length) next = 0;
          btns[next].focus();
          this.switchTo(btns[next].dataset.tab);
        }
      });

      // Initialize active tab
      const activeBtn = this.navContainer.querySelector('.tab-btn.active, [data-tab].active');
      if (activeBtn) {
        this.activeTab = activeBtn.dataset.tab;
      }
    }

    /**
     * Switch to a specific tab.
     * @param {string} tabId - Tab ID to activate
     */
    switchTo(tabId) {
      if (!tabId) return;

      // Update buttons
      const buttons = this.navContainer.querySelectorAll('.tab-btn, [data-tab]');
      buttons.forEach(btn => {
        const isActive = btn.dataset.tab === tabId;
        btn.classList.toggle('active', isActive);
        btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });

      // Update content panels
      const panels = document.querySelectorAll(this.contentSelector);
      panels.forEach(panel => {
        const panelId = panel.id.replace('tab-', '');
        panel.classList.toggle('active', panelId === tabId);
      });

      this.activeTab = tabId;
      this.onChange(tabId);
    }

    /**
     * Update tab count badge.
     * @param {string} tabId - Tab ID
     * @param {number} count - Count to display
     */
    updateCount(tabId, count) {
      const countEl = document.getElementById('tabCount' + tabId.charAt(0).toUpperCase() + tabId.slice(1));
      if (countEl) {
        countEl.textContent = count;
      }
    }

    /**
     * Get current active tab ID.
     * @returns {string|null}
     */
    getActive() {
      return this.activeTab;
    }
  }

  // ==========================================================================
  // FILTER MANAGER
  // ==========================================================================

  /**
   * FilterManager - Handles filter tab buttons.
   *
   * @example
   * const filters = new FilterManager({
   *   containerSelector: '.filter-tabs',
   *   onChange: (filter) => loadData(filter)
   * });
   */
  class FilterManager {
    /**
     * @param {Object} options
     * @param {string} options.containerSelector - Selector for filter container
     * @param {Function} [options.onChange] - Callback when filter changes
     * @param {string} [options.defaultFilter='all'] - Default filter value
     */
    constructor(options) {
      this.container = document.querySelector(options.containerSelector);
      this.onChange = options.onChange || function() {};
      this.currentFilter = options.defaultFilter || 'all';

      if (this.container) {
        this.init();
      }
    }

    init() {
      const tabs = this.container.querySelectorAll('.filter-tab, [data-filter]');
      tabs.forEach(tab => {
        tab.addEventListener('click', () => {
          const filter = tab.dataset.filter;
          this.setFilter(filter);
        });
      });
    }

    /**
     * Set the active filter.
     * @param {string} filter - Filter value
     */
    setFilter(filter) {
      const tabs = this.container.querySelectorAll('.filter-tab, [data-filter]');
      tabs.forEach(tab => {
        tab.classList.toggle('active', tab.dataset.filter === filter);
      });

      this.currentFilter = filter;
      this.onChange(filter);
    }

    /**
     * Get current filter value.
     * @returns {string}
     */
    getFilter() {
      return this.currentFilter;
    }
  }

  // ==========================================================================
  // VIEW TOGGLE
  // ==========================================================================

  /**
   * ViewToggle - Handles grid/calendar/list view switching.
   *
   * @example
   * const toggle = new ViewToggle({
   *   containerSelector: '.view-toggle',
   *   views: {
   *     grid: { element: '#gridContainer', onActivate: () => {} },
   *     calendar: { element: '#calendarContainer', onActivate: () => {} }
   *   }
   * });
   */
  class ViewToggle {
    /**
     * @param {Object} options
     * @param {string} options.containerSelector - Selector for toggle buttons container
     * @param {Object} options.views - View configuration { viewId: { element, onActivate } }
     * @param {string} [options.defaultView] - Default view ID
     */
    constructor(options) {
      this.container = document.querySelector(options.containerSelector);
      this.views = options.views || {};
      this.currentView = options.defaultView || Object.keys(this.views)[0];

      if (this.container) {
        this.init();
      }
    }

    init() {
      const buttons = this.container.querySelectorAll('.view-toggle-btn, [data-view]');
      buttons.forEach(btn => {
        btn.addEventListener('click', () => {
          this.switchTo(btn.dataset.view);
        });
      });

      // Set initial view
      this.switchTo(this.currentView);
    }

    /**
     * Switch to a specific view.
     * @param {string} viewId - View ID to activate
     */
    switchTo(viewId) {
      if (!viewId || !this.views[viewId]) return;

      // Update buttons
      const buttons = this.container.querySelectorAll('.view-toggle-btn, [data-view]');
      buttons.forEach(btn => {
        btn.classList.toggle('active', btn.dataset.view === viewId);
      });

      // Update view visibility
      Object.keys(this.views).forEach(id => {
        const view = this.views[id];
        const el = typeof view.element === 'string'
          ? document.querySelector(view.element)
          : view.element;

        if (el) {
          if (id === viewId) {
            el.style.display = '';
            el.classList.add('active');
          } else {
            el.style.display = 'none';
            el.classList.remove('active');
          }
        }
      });

      // Call activation callback
      const view = this.views[viewId];
      if (view && typeof view.onActivate === 'function') {
        view.onActivate();
      }

      this.currentView = viewId;
    }

    /**
     * Get current view ID.
     * @returns {string}
     */
    getView() {
      return this.currentView;
    }
  }

  // ==========================================================================
  // CALENDAR VIEW
  // ==========================================================================

  /**
   * CalendarView - Monthly calendar with events.
   *
   * @example
   * const calendar = new CalendarView({
   *   container: '#calendarGrid',
   *   titleElement: '#calendarTitle',
   *   events: meetings,
   *   onEventClick: (event) => openMeeting(event.id)
   * });
   */
  class CalendarView {
    static DAYS_FR = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
    static MONTHS_FR = [
      'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
      'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
    ];

    /**
     * @param {Object} options
     * @param {string|HTMLElement} options.container - Grid container
     * @param {string|HTMLElement} [options.titleElement] - Title element
     * @param {Array} [options.events] - Events array
     * @param {Function} [options.onEventClick] - Event click callback
     * @param {Function} [options.getEventDate] - Function to extract date from event
     * @param {Function} [options.getEventStatus] - Function to extract status from event
     * @param {Function} [options.getEventTitle] - Function to extract title from event
     */
    constructor(options) {
      this.container = typeof options.container === 'string'
        ? document.querySelector(options.container)
        : options.container;

      this.titleElement = typeof options.titleElement === 'string'
        ? document.querySelector(options.titleElement)
        : options.titleElement;

      this.events = options.events || [];
      this.onEventClick = options.onEventClick || function() {};
      this.getEventDate = options.getEventDate || (e => e.scheduled_at || e.created_at);
      this.getEventStatus = options.getEventStatus || (e => e.status);
      this.getEventTitle = options.getEventTitle || (e => e.title || '(sans titre)');

      this.currentDate = new Date();
    }

    /**
     * Set events and re-render.
     * @param {Array} events
     */
    setEvents(events) {
      this.events = events;
      this.render();
    }

    /**
     * Navigate to previous month.
     */
    prevMonth() {
      this.currentDate.setMonth(this.currentDate.getMonth() - 1);
      this.render();
    }

    /**
     * Navigate to next month.
     */
    nextMonth() {
      this.currentDate.setMonth(this.currentDate.getMonth() + 1);
      this.render();
    }

    /**
     * Navigate to today.
     */
    today() {
      this.currentDate = new Date();
      this.render();
    }

    /**
     * Render the calendar.
     */
    render() {
      if (!this.container) return;

      const year = this.currentDate.getFullYear();
      const month = this.currentDate.getMonth();

      // Update title
      if (this.titleElement) {
        this.titleElement.textContent = `${CalendarView.MONTHS_FR[month]} ${year}`;
      }

      // Calculate calendar grid
      const firstDay = new Date(year, month, 1);
      const lastDay = new Date(year, month + 1, 0);
      const daysInMonth = lastDay.getDate();

      let startDay = firstDay.getDay() - 1;
      if (startDay < 0) startDay = 6;

      // Group events by date
      const eventsByDate = {};
      this.events.forEach(event => {
        const date = this.getEventDate(event);
        if (date) {
          const key = date.slice(0, 10);
          if (!eventsByDate[key]) eventsByDate[key] = [];
          eventsByDate[key].push(event);
        }
      });

      // Build HTML
      const today = new Date();
      const todayKey = today.toISOString().slice(0, 10);
      const esc = window.escapeHtml || (s => s);

      let html = CalendarView.DAYS_FR.map(d =>
        `<div class="calendar-day-header">${d}</div>`
      ).join('');

      // Previous month days
      const prevMonth = new Date(year, month, 0);
      const prevMonthDays = prevMonth.getDate();
      for (let i = startDay - 1; i >= 0; i--) {
        const dayNum = prevMonthDays - i;
        html += `<div class="calendar-day other-month"><div class="calendar-day-number">${dayNum}</div></div>`;
      }

      // Current month days
      for (let day = 1; day <= daysInMonth; day++) {
        const dateKey = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const isToday = dateKey === todayKey;
        const dayEvents = eventsByDate[dateKey] || [];

        let eventsHtml = '';
        dayEvents.slice(0, 3).forEach(event => {
          const title = esc(this.getEventTitle(event)).slice(0, 20);
          const status = this.getEventStatus(event);
          eventsHtml += `<div class="calendar-event status-${status}" data-event-id="${event.id}" title="${esc(this.getEventTitle(event))}">${title}</div>`;
        });
        if (dayEvents.length > 3) {
          eventsHtml += `<div class="text-muted" style="font-size:0.65rem;">+${dayEvents.length - 3} autres</div>`;
        }

        html += `
          <div class="calendar-day${isToday ? ' today' : ''}">
            <div class="calendar-day-number">${day}</div>
            <div class="calendar-events">${eventsHtml}</div>
          </div>`;
      }

      // Next month days to fill grid
      const totalCells = startDay + daysInMonth;
      const remaining = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
      for (let i = 1; i <= remaining; i++) {
        html += `<div class="calendar-day other-month"><div class="calendar-day-number">${i}</div></div>`;
      }

      this.container.innerHTML = html;

      // Bind event clicks
      this.container.querySelectorAll('.calendar-event[data-event-id]').forEach(el => {
        el.addEventListener('click', () => {
          const eventId = el.dataset.eventId;
          const event = this.events.find(e => String(e.id) === eventId);
          if (event) this.onEventClick(event);
        });
      });
    }
  }

  // ==========================================================================
  // COLLAPSIBLE SECTION
  // ==========================================================================

  /**
   * CollapsibleSection - Expandable/collapsible sections.
   *
   * @example
   * CollapsibleSection.initAll('.resolution-section');
   */
  class CollapsibleSection {
    /**
     * Initialize all collapsible sections.
     * @param {string} selector - Selector for section containers
     */
    static initAll(selector) {
      document.querySelectorAll(selector).forEach(section => {
        new CollapsibleSection(section);
      });
    }

    /**
     * @param {HTMLElement} element - Section element
     */
    constructor(element) {
      this.element = element;
      this.header = element.querySelector('.resolution-header, [data-collapse-toggle]');
      this.body = element.querySelector('.resolution-body, [data-collapse-content]');

      if (this.header) {
        this.header.addEventListener('click', () => this.toggle());
        this.header.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            this.toggle();
          }
        });
      }
    }

    /**
     * Toggle expanded state.
     */
    toggle() {
      this.element.classList.toggle('expanded');
    }

    /**
     * Expand the section.
     */
    expand() {
      this.element.classList.add('expanded');
    }

    /**
     * Collapse the section.
     */
    collapse() {
      this.element.classList.remove('expanded');
    }

    /**
     * Check if section is expanded.
     * @returns {boolean}
     */
    isExpanded() {
      return this.element.classList.contains('expanded');
    }
  }

  // ==========================================================================
  // SEARCH FILTER
  // ==========================================================================

  /**
   * SearchFilter - Real-time search filtering.
   *
   * @example
   * const search = new SearchFilter({
   *   inputSelector: '#searchInput',
   *   itemsSelector: '.item',
   *   searchAttribute: 'data-search-text',
   *   onFilter: (visibleCount, totalCount) => updateCount(visibleCount)
   * });
   */
  class SearchFilter {
    /**
     * @param {Object} options
     * @param {string} options.inputSelector - Search input selector
     * @param {string} options.itemsSelector - Items to filter selector
     * @param {string} [options.searchAttribute='data-search-text'] - Attribute containing searchable text
     * @param {Function} [options.onFilter] - Callback after filtering (visibleCount, totalCount)
     * @param {number} [options.debounce=150] - Debounce delay in ms
     */
    constructor(options) {
      this.input = document.querySelector(options.inputSelector);
      this.itemsSelector = options.itemsSelector;
      this.searchAttribute = options.searchAttribute || 'data-search-text';
      this.onFilter = options.onFilter || function() {};
      this.debounceDelay = options.debounce || 150;
      this.debounceTimer = null;

      if (this.input) {
        this.input.addEventListener('input', () => this.handleInput());
      }
    }

    handleInput() {
      clearTimeout(this.debounceTimer);
      this.debounceTimer = setTimeout(() => this.filter(), this.debounceDelay);
    }

    /**
     * Filter items based on search input.
     */
    filter() {
      const query = this.input.value.toLowerCase().trim();
      const items = document.querySelectorAll(this.itemsSelector);
      let visibleCount = 0;

      items.forEach(item => {
        const text = (item.getAttribute(this.searchAttribute) || item.textContent || '').toLowerCase();
        const matches = !query || text.includes(query);
        item.style.display = matches ? '' : 'none';
        if (matches) visibleCount++;
      });

      this.onFilter(visibleCount, items.length);
    }

    /**
     * Clear the search input and show all items.
     */
    clear() {
      if (this.input) {
        this.input.value = '';
        this.filter();
      }
    }
  }

  // ==========================================================================
  // DATA GRID RENDERER
  // ==========================================================================

  /**
   * DataGrid - Renders a grid of cards from data.
   *
   * @example
   * const grid = new DataGrid({
   *   container: '#meetingsList',
   *   renderItem: (meeting) => `<div class="meeting-card">...</div>`,
   *   emptyState: { icon: 'clipboard-list', title: 'Aucune séance', message: '...' }
   * });
   * grid.setData(meetings);
   */
  class DataGrid {
    /**
     * @param {Object} options
     * @param {string|HTMLElement} options.container - Grid container
     * @param {Function} options.renderItem - Function to render each item (item, index) => HTML
     * @param {Object} [options.emptyState] - Empty state config { icon, title, message, actionHtml }
     * @param {Function} [options.sortFn] - Sort function for items
     * @param {Function} [options.filterFn] - Filter function for items
     */
    constructor(options) {
      this.container = typeof options.container === 'string'
        ? document.querySelector(options.container)
        : options.container;

      this.renderItem = options.renderItem;
      this.emptyState = options.emptyState || null;
      this.sortFn = options.sortFn || null;
      this.filterFn = options.filterFn || null;
      this.data = [];
    }

    /**
     * Set data and render.
     * @param {Array} data
     */
    setData(data) {
      this.data = data || [];
      this.render();
    }

    /**
     * Set filter function and re-render.
     * @param {Function} filterFn
     */
    setFilter(filterFn) {
      this.filterFn = filterFn;
      this.render();
    }

    /**
     * Render the grid.
     */
    render() {
      if (!this.container) return;

      let items = [...this.data];

      // Apply filter
      if (this.filterFn) {
        items = items.filter(this.filterFn);
      }

      // Apply sort
      if (this.sortFn) {
        items.sort(this.sortFn);
      }

      // Render
      if (items.length === 0 && this.emptyState) {
        const esc = window.escapeHtml || (s => s);
        this.container.innerHTML = `
          <div class="empty-state" style="grid-column: 1 / -1;">
            <div class="empty-state-icon">
              <svg class="icon" style="width:3rem;height:3rem;" aria-hidden="true">
                <use href="/assets/icons.svg#icon-${this.emptyState.icon}"></use>
              </svg>
            </div>
            <h3>${esc(this.emptyState.title)}</h3>
            <p>${esc(this.emptyState.message)}</p>
            ${this.emptyState.actionHtml || ''}
          </div>`;
      } else {
        this.container.innerHTML = items.map((item, i) => this.renderItem(item, i)).join('');
      }

      return items.length;
    }

    /**
     * Get current filtered/sorted items count.
     * @returns {number}
     */
    getCount() {
      let items = [...this.data];
      if (this.filterFn) {
        items = items.filter(this.filterFn);
      }
      return items.length;
    }
  }

  // ==========================================================================
  // EXPORTS
  // ==========================================================================

  window.PageComponents = {
    TabManager: TabManager,
    FilterManager: FilterManager,
    ViewToggle: ViewToggle,
    CalendarView: CalendarView,
    CollapsibleSection: CollapsibleSection,
    SearchFilter: SearchFilter,
    DataGrid: DataGrid
  };

})();
