/**
 * shared.js — Shared utilities and sidebar loader for AG-VOTE admin pages.
 *
 * Must be loaded AFTER utils.js and BEFORE page-specific scripts.
 * Provides: sidebar include, role/status label maps, button loading,
 *           getInitials, modal helper.
 */
(function () {
  'use strict';

  // =========================================================================
  // SIDEBAR INCLUDE
  // =========================================================================

  /**
   * Load the shared sidebar partial and highlight the active page.
   * Usage: add <aside class="app-sidebar" data-include-sidebar data-page="meetings"></aside>
   */
  function initSidebar() {
    const sidebar = document.querySelector('[data-include-sidebar]');
    if (!sidebar) return;

    const page = sidebar.getAttribute('data-page') || '';

    fetch('/partials/sidebar.html')
      .then(function (r) { return r.text(); })
      .then(function (html) {
        sidebar.innerHTML = html;
        if (page) {
          const link = sidebar.querySelector('[data-page="' + page + '"]');
          if (link) link.classList.add('active');
        }
        // Propagate meeting_id to sidebar nav links using MeetingContext
        const mid = (typeof MeetingContext !== 'undefined') ? MeetingContext.get() : null;
        if (mid) {
          sidebar.querySelectorAll('a.nav-item[href]').forEach(function (a) {
            const href = a.getAttribute('href') || '';
            // Skip archives and admin — they don't need meeting context
            if (href.indexOf('admin') !== -1 || href.indexOf('archives') !== -1) return;
            a.href = href.split('?')[0] + '?meeting_id=' + encodeURIComponent(mid);
          });
        }
      })
      .catch(function () {
        // Fallback: leave empty sidebar (navigation still works via drawer menu)
        sidebar.innerHTML = '<div class="p-4 text-muted text-sm">Navigation unavailable</div>';
      });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { initSidebar(); });
  } else {
    initSidebar();
  }

  // =========================================================================
  // ROLE & STATUS LABEL MAPS
  // =========================================================================

  const ROLE_LABELS_SYSTEM = {
    admin: 'Administrateur',
    operator: 'Op\u00e9rateur',
    auditor: 'Auditeur',
    viewer: 'Observateur'
  };

  const ROLE_LABELS_MEETING = {
    president: 'Pr\u00e9sident',
    assessor: 'Assesseur',
    voter: '\u00c9lecteur'
  };

  /**
   * Persona descriptions — maps roles to the 7 UX personas.
   * Used in UI to clarify what each role does, not just what it's called.
   */
  const PERSONA_DESCRIPTIONS = {
    admin: 'Gestion du syst\u00e8me',
    operator: 'Pr\u00e9paration & pilotage de s\u00e9ance',
    auditor: 'Contr\u00f4le de conformit\u00e9',
    viewer: 'Consultation des donn\u00e9es',
    president: 'Supervision & validation',
    assessor: 'Contr\u00f4le de scrutin',
    voter: 'Vote en assembl\u00e9e'
  };

  const ROLE_LABELS_ALL = {};
  Object.keys(ROLE_LABELS_SYSTEM).forEach(function (k) { ROLE_LABELS_ALL[k] = ROLE_LABELS_SYSTEM[k]; });
  Object.keys(ROLE_LABELS_MEETING).forEach(function (k) { ROLE_LABELS_ALL[k] = ROLE_LABELS_MEETING[k]; });

  const MEETING_STATUS_MAP = {
    draft:     { badge: 'badge-neutral',          text: 'Brouillon' },
    scheduled: { badge: 'badge-info',             text: 'Programmée' },
    frozen:    { badge: 'badge-info',             text: 'Verrouillée' },
    live:      { badge: 'badge-danger badge-dot', text: 'En cours' },
    paused:    { badge: 'badge-warning',          text: 'En pause' },
    closed:    { badge: 'badge-success',          text: 'Terminée' },
    validated: { badge: 'badge-success',          text: 'Validée' },
    archived:  { badge: 'badge-neutral',          text: 'Archivée' }
  };

  // =========================================================================
  // UTILITY FUNCTIONS
  // =========================================================================

  /**
   * Set a button's loading state with spinner + disabled + aria-busy.
   * @param {HTMLButtonElement} btn
   * @param {boolean} loading
   */
  function btnLoading(btn, loading) {
    if (!btn) return;
    if (loading) {
      btn.disabled = true;
      btn.classList.add('loading');
      btn.setAttribute('aria-busy', 'true');
      btn._prevHtml = btn.innerHTML;
      const label = btn.textContent.trim();
      btn.innerHTML = '<span class="spinner spinner-sm"></span> <span>' + label + '</span>';
    } else {
      btn.disabled = false;
      btn.classList.remove('loading');
      btn.removeAttribute('aria-busy');
      if (btn._prevHtml) {
        btn.innerHTML = btn._prevHtml;
        delete btn._prevHtml;
      }
    }
  }

  // =========================================================================
  // MODAL HELPER
  // =========================================================================

  /**
   * Open a modal dialog (replacement for prompt/confirm).
   *
   * @param {object} opts
   * @param {string} opts.title - Modal title
   * @param {string} opts.body  - HTML body content
   * @param {string} [opts.confirmText='Confirmer']
   * @param {string} [opts.cancelText='Annuler']
   * @param {string} [opts.confirmClass='btn-primary']
   * @param {function} [opts.onConfirm] - called with the modal element; return false to prevent close
   * @returns {HTMLElement} the backdrop element
   */
  function openModal(opts) {
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop open';
    backdrop.setAttribute('role', 'dialog');
    backdrop.setAttribute('aria-modal', 'true');

    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML =
      '<div class="modal-header">' +
        '<div class="modal-title">' + Utils.escapeHtml(opts.title || '') + '</div>' +
        '<button class="btn btn-ghost btn-icon btn-sm modal-close-btn" type="button" aria-label="Fermer">&times;</button>' +
      '</div>' +
      '<div class="modal-body">' + (opts.body || '') + '</div>' +
      '<div class="modal-footer">' +
        '<button class="btn btn-secondary modal-cancel-btn" type="button">' + Utils.escapeHtml(opts.cancelText || 'Annuler') + '</button>' +
        '<button class="btn ' + (opts.confirmClass || 'btn-primary') + ' modal-confirm-btn" type="button">' + Utils.escapeHtml(opts.confirmText || 'Confirmer') + '</button>' +
      '</div>';

    backdrop.appendChild(modal);
    document.body.appendChild(backdrop);

    // Save previously focused element to restore on close
    var previousFocus = document.activeElement;

    // Focus first input, or confirm button as fallback
    var firstInput = modal.querySelector('input, select, textarea');
    var focusTarget = firstInput || modal.querySelector('.modal-confirm-btn');
    if (focusTarget) setTimeout(function () { focusTarget.focus(); }, 50);

    function close() {
      backdrop.classList.remove('open');
      document.removeEventListener('keydown', keyHandler);
      setTimeout(function () {
        backdrop.remove();
        // Restore focus to the element that opened the modal
        if (previousFocus && previousFocus.focus) {
          try { previousFocus.focus(); } catch (e) { /* element may be gone */ }
        }
      }, 200);
    }

    // Focus trap: cycle Tab within modal
    function keyHandler(e) {
      if (e.key === 'Escape') { close(); return; }
      if (e.key !== 'Tab') return;
      var focusable = modal.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
      if (focusable.length === 0) return;
      var first = focusable[0];
      var last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
    document.addEventListener('keydown', keyHandler);

    modal.querySelector('.modal-close-btn').addEventListener('click', close);
    modal.querySelector('.modal-cancel-btn').addEventListener('click', close);
    backdrop.addEventListener('click', function (e) {
      if (e.target === backdrop) close();
    });

    modal.querySelector('.modal-confirm-btn').addEventListener('click', function () {
      if (opts.onConfirm) {
        const result = opts.onConfirm(modal);
        if (result === false) return; // prevent close
      }
      close();
    });

    backdrop._close = close;
    return backdrop;
  }

  // =========================================================================
  // SHOW / HIDE UTILITIES
  // =========================================================================

  /**
   * Show an element (remove hidden class and inline display:none).
   * @param {HTMLElement} el
   * @param {string} [display] - Optional display value (default: '')
   */
  function show(el, display) {
    if (!el) return;
    el.classList.remove('hidden');
    el.style.display = display || '';
  }

  /**
   * Hide an element using the hidden class.
   * @param {HTMLElement} el
   */
  function hide(el) {
    if (!el) return;
    el.style.display = 'none';
  }

  // =========================================================================
  // EMPTY STATE HELPER
  // =========================================================================

  var EMPTY_SVG = {
    meetings: '<svg width="80" height="80" viewBox="0 0 80 80" fill="none"><rect x="12" y="16" width="56" height="48" rx="6" stroke="currentColor" stroke-width="2" opacity=".3"/><rect x="20" y="26" width="24" height="4" rx="2" fill="currentColor" opacity=".2"/><rect x="20" y="34" width="40" height="4" rx="2" fill="currentColor" opacity=".15"/><rect x="20" y="42" width="32" height="4" rx="2" fill="currentColor" opacity=".1"/><circle cx="58" cy="54" r="14" stroke="currentColor" stroke-width="2" opacity=".25"/><path d="M58 48v12M52 54h12" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity=".4"/></svg>',
    members: '<svg width="80" height="80" viewBox="0 0 80 80" fill="none"><circle cx="32" cy="28" r="10" stroke="currentColor" stroke-width="2" opacity=".3"/><path d="M16 56c0-8.8 7.2-16 16-16s16 7.2 16 16" stroke="currentColor" stroke-width="2" opacity=".2" stroke-linecap="round"/><circle cx="54" cy="32" r="8" stroke="currentColor" stroke-width="2" opacity=".2"/><path d="M42 58c0-6.6 5.4-12 12-12s12 5.4 12 12" stroke="currentColor" stroke-width="2" opacity=".15" stroke-linecap="round"/></svg>',
    votes: '<svg width="80" height="80" viewBox="0 0 80 80" fill="none"><rect x="18" y="12" width="44" height="56" rx="6" stroke="currentColor" stroke-width="2" opacity=".3"/><path d="M30 36l6 6 14-14" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" opacity=".35"/><rect x="26" y="52" width="28" height="4" rx="2" fill="currentColor" opacity=".15"/><rect x="26" y="60" width="20" height="4" rx="2" fill="currentColor" opacity=".1"/></svg>',
    archives: '<svg width="80" height="80" viewBox="0 0 80 80" fill="none"><rect x="14" y="20" width="52" height="12" rx="4" stroke="currentColor" stroke-width="2" opacity=".3"/><path d="M20 32v28a4 4 0 004 4h32a4 4 0 004-4V32" stroke="currentColor" stroke-width="2" opacity=".2"/><rect x="30" y="40" width="20" height="8" rx="3" stroke="currentColor" stroke-width="2" opacity=".25"/></svg>',
    generic: '<svg width="80" height="80" viewBox="0 0 80 80" fill="none"><circle cx="40" cy="40" r="24" stroke="currentColor" stroke-width="2" opacity=".2"/><path d="M40 28v12M40 48h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round" opacity=".3"/></svg>'
  };

  /**
   * Render an illustrated empty state.
   * @param {object} opts
   * @param {string} opts.icon - Key: meetings|members|votes|archives|generic
   * @param {string} opts.title - Title text
   * @param {string} [opts.description] - Description text
   * @param {string} [opts.actionHtml] - Optional HTML for action button
   * @returns {string} HTML string
   */
  function emptyState(opts) {
    var svg = EMPTY_SVG[opts.icon] || EMPTY_SVG.generic;
    var h = '<div class="empty-state animate-fade-in">';
    h += '<div class="empty-state-icon">' + svg + '</div>';
    h += '<div class="empty-state-title">' + (opts.title || '') + '</div>';
    if (opts.description) h += '<div class="empty-state-description">' + opts.description + '</div>';
    if (opts.actionHtml) h += opts.actionHtml;
    h += '</div>';
    return h;
  }

  // =========================================================================
  // NUMBER FORMATTING HELPERS
  // =========================================================================

  /**
   * Format a voting weight for display (1.0000 → "1", 1.5000 → "1.5").
   * @param {number|string} v - Raw weight value
   * @returns {string}
   */
  function formatWeight(v) {
    var n = parseFloat(v);
    if (isNaN(n)) return '1';
    return Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
  }

  /**
   * Format a percentage for display (66.6667 → "67", 0 → "0").
   * @param {number|string} v - Raw percentage value
   * @returns {string}
   */
  function formatPct(v) {
    var n = parseFloat(v);
    if (isNaN(n)) return '0';
    return String(Math.round(n));
  }

  // =========================================================================
  // EXPORTS
  // =========================================================================

  window.Shared = {
    ROLE_LABELS_SYSTEM: ROLE_LABELS_SYSTEM,
    ROLE_LABELS_MEETING: ROLE_LABELS_MEETING,
    ROLE_LABELS_ALL: ROLE_LABELS_ALL,
    PERSONA_DESCRIPTIONS: PERSONA_DESCRIPTIONS,
    MEETING_STATUS_MAP: MEETING_STATUS_MAP,
    btnLoading: btnLoading,
    openModal: openModal,
    show: show,
    hide: hide,
    emptyState: emptyState,
    formatWeight: formatWeight,
    formatPct: formatPct
  };

})();
