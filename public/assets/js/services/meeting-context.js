/**
 * MeetingContext - Centralized meeting state management singleton
 *
 * Single source of truth for meeting_id across the application.
 * Provides: get/set methods, sessionStorage persistence, URL sync, event emission.
 *
 * Usage:
 *   MeetingContext.init();                    // Call once on page load
 *   const id = MeetingContext.get();          // Get current meeting_id
 *   MeetingContext.set('uuid-here');          // Set and persist
 *   MeetingContext.onChange(callback);        // Listen to changes
 *   MeetingContext.clear();                   // Clear selection
 */
const MeetingContext = (function() {
  'use strict';

  const STORAGE_KEY = 'meeting_id';
  const EVENT_NAME = 'meetingcontext:change';

  let _meetingId = null;
  let _initialized = false;
  const _listeners = [];

  /**
   * Initialize the context from URL or sessionStorage
   * @returns {string|null} The current meeting_id
   */
  function init() {
    if (_initialized) return _meetingId;

    // Priority: URL param > sessionStorage > hidden input
    const urlParams = new URLSearchParams(window.location.search);
    const urlId = urlParams.get('meeting_id');
    const storedId = sessionStorage.getItem(STORAGE_KEY);
    const inputEl = document.querySelector('input[name="meeting_id"]');
    const inputId = inputEl?.value || null;

    _meetingId = urlId || storedId || inputId || null;

    // Persist to sessionStorage if we got it from URL
    if (_meetingId) {
      sessionStorage.setItem(STORAGE_KEY, _meetingId);
    }

    // Update URL if we got it from storage but not from URL
    if (_meetingId && !urlId) {
      _syncToUrl(_meetingId);
    }

    // Propagate to navigation links
    _propagateToLinks(_meetingId);

    _initialized = true;
    return _meetingId;
  }

  /**
   * Get current meeting_id
   * @returns {string|null}
   */
  function get() {
    if (!_initialized) init();
    return _meetingId;
  }

  /**
   * Set meeting_id and persist
   * @param {string|null} id - The meeting UUID or null to clear
   * @param {Object} options - Options { silent: boolean, updateUrl: boolean }
   */
  function set(id, options = {}) {
    const { silent = false, updateUrl = true } = options;
    const oldId = _meetingId;
    _meetingId = id || null;

    if (_meetingId) {
      sessionStorage.setItem(STORAGE_KEY, _meetingId);
    } else {
      sessionStorage.removeItem(STORAGE_KEY);
    }

    if (updateUrl) {
      _syncToUrl(_meetingId);
    }

    _propagateToLinks(_meetingId);

    if (!silent && oldId !== _meetingId) {
      _notifyListeners(oldId, _meetingId);
    }
  }

  /**
   * Clear meeting selection
   */
  function clear() {
    set(null);
  }

  /**
   * Register a change listener
   * @param {Function} callback - Called with (oldId, newId)
   * @returns {Function} Unsubscribe function
   */
  function onChange(callback) {
    _listeners.push(callback);
    return function unsubscribe() {
      const idx = _listeners.indexOf(callback);
      if (idx > -1) _listeners.splice(idx, 1);
    };
  }

  /**
   * Check if a meeting is currently selected
   * @returns {boolean}
   */
  function isSet() {
    return !!get();
  }

  /**
   * Get meeting_id for API calls (same as get, but explicit intent)
   * @returns {string|null}
   */
  function forApi() {
    return get();
  }

  // ─── Private helpers ───────────────────────────────────

  function _syncToUrl(id) {
    const url = new URL(window.location.href);
    if (id) {
      url.searchParams.set('meeting_id', id);
    } else {
      url.searchParams.delete('meeting_id');
    }
    // Use replaceState to avoid polluting history
    window.history.replaceState({}, '', url.toString());
  }

  function _propagateToLinks(meetingId) {
    if (!meetingId) return;

    document.querySelectorAll('a[href]').forEach(function(a) {
      const href = a.getAttribute('href');
      if (!href) return;
      // Skip external links
      if (href.startsWith('http') && !href.startsWith(window.location.origin)) return;
      // Only process .htmx.html and .php pages
      if (!href.endsWith('.htmx.html') && !href.endsWith('.php')) return;
      // Skip vote token links (they use their own token)
      if (href.startsWith('/vote.php')) return;
      // Skip admin pages (they don't need meeting context)
      if (href.includes('admin')) return;

      try {
        const u = new URL(href, window.location.origin);
        if (!u.searchParams.get('meeting_id')) {
          u.searchParams.set('meeting_id', meetingId);
          a.setAttribute('href', u.pathname + u.search);
        }
      } catch (e) {
        // Invalid URL, skip
      }
    });
  }

  function _notifyListeners(oldId, newId) {
    // Dispatch custom event for global listeners
    window.dispatchEvent(new CustomEvent(EVENT_NAME, {
      detail: { oldId: oldId, newId: newId }
    }));

    // Call registered callbacks
    _listeners.forEach(function(cb) {
      try {
        cb(oldId, newId);
      } catch (e) {
        console.error('MeetingContext listener error:', e);
      }
    });
  }

  // ─── Cross-tab synchronization ──────────────────────────
  // Listen for sessionStorage changes from other tabs to stay in sync.

  window.addEventListener('storage', function(e) {
    if (e.key !== STORAGE_KEY) return;
    const newId = e.newValue || null;
    if (newId !== _meetingId) {
      const oldId = _meetingId;
      _meetingId = newId;
      _propagateToLinks(_meetingId);
      _notifyListeners(oldId, _meetingId);
    }
  });

  // ─── Auto-initialize on DOMContentLoaded ───────────────

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    // DOM already ready
    init();
  }

  // ─── Public API ────────────────────────────────────────

  return {
    init: init,
    get: get,
    set: set,
    clear: clear,
    onChange: onChange,
    isSet: isSet,
    forApi: forApi,
    EVENT_NAME: EVENT_NAME
  };
})();
