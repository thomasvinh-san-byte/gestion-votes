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
   * @param {string} opts.body  - Raw HTML body content. Callers MUST escape user data with Utils.escapeHtml().
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

    var confirmBtn = modal.querySelector('.modal-confirm-btn');
    confirmBtn.addEventListener('click', async function () {
      if (opts.onConfirm) {
        confirmBtn.disabled = true;
        try {
          const result = await opts.onConfirm(modal);
          if (result === false) { confirmBtn.disabled = false; return; }
        } catch (e) {
          confirmBtn.disabled = false;
          return;
        }
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
  // SKELETON LOADING HELPERS
  // =========================================================================

  /**
   * Set a container to a skeleton loading state.
   * Replaces innerHTML with skeleton rows.
   * @param {HTMLElement} el - Container element
   * @param {object} [opts]
   * @param {number} [opts.rows=2] - Number of skeleton rows
   * @param {number} [opts.cols=3] - Cells per row
   */
  function skeleton(el, opts) {
    if (!el) return;
    opts = opts || {};
    var rows = opts.rows || 2;
    var cols = opts.cols || 3;
    var html = '';
    for (var r = 0; r < rows; r++) {
      html += '<div class="skeleton-row">';
      for (var c = 0; c < cols; c++) {
        html += '<div class="skeleton skeleton-cell"></div>';
      }
      html += '</div>';
    }
    el.innerHTML = html;
    el.setAttribute('aria-busy', 'true');
  }

  /**
   * Clear skeleton state from a container.
   * @param {HTMLElement} el - Container element
   */
  function clearSkeleton(el) {
    if (!el) return;
    el.removeAttribute('aria-busy');
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
  // FORM VALIDATION HELPERS
  // =========================================================================

  /**
   * Show an inline validation error on a form field.
   * Adds .is-invalid to the input and inserts/updates a .field-error message.
   * @param {HTMLElement} input - The form input element
   * @param {string} message - Error message to display
   */
  function fieldError(input, message) {
    if (!input) return;
    input.classList.add('is-invalid');
    input.classList.remove('is-valid');
    input.setAttribute('aria-invalid', 'true');
    var container = input.closest('.form-group') || input.parentElement;
    var errEl = container.querySelector('.field-error');
    if (!errEl) {
      errEl = document.createElement('div');
      errEl.className = 'field-error';
      errEl.setAttribute('role', 'alert');
      container.appendChild(errEl);
    }
    errEl.textContent = message;
  }

  /**
   * Clear inline validation error on a form field.
   * @param {HTMLElement} input - The form input element
   */
  function fieldClear(input) {
    if (!input) return;
    input.classList.remove('is-invalid', 'is-valid');
    input.removeAttribute('aria-invalid');
    var container = input.closest('.form-group') || input.parentElement;
    var errEl = container.querySelector('.field-error');
    if (errEl) errEl.remove();
  }

  /**
   * Mark a field as valid (green border).
   * @param {HTMLElement} input - The form input element
   */
  function fieldValid(input) {
    if (!input) return;
    fieldClear(input);
    input.classList.add('is-valid');
  }

  /**
   * Validate a single field and show/clear error inline.
   * @param {HTMLElement} input - The form input element
   * @param {Array} rules - Array of {test: fn(value) => bool, msg: string}
   * @returns {boolean} true if valid
   */
  function validateField(input, rules) {
    if (!input) return true;
    var value = (input.value || '').trim();
    for (var i = 0; i < rules.length; i++) {
      if (!rules[i].test(value)) {
        fieldError(input, rules[i].msg);
        return false;
      }
    }
    if (value) fieldValid(input);
    else fieldClear(input);
    return true;
  }

  /**
   * Attach live validation to a field (on blur and input).
   * @param {HTMLElement} input - The form input element
   * @param {Array} rules - Array of {test: fn(value) => bool, msg: string}
   */
  function liveValidate(input, rules) {
    if (!input) return;
    input.addEventListener('blur', function () { validateField(input, rules); });
    input.addEventListener('input', function () {
      if (input.classList.contains('is-invalid')) {
        validateField(input, rules);
      }
    });
  }

  /**
   * Validate multiple fields at once. Returns true if all pass.
   * @param {Array} fieldRules - Array of {input: HTMLElement, rules: Array}
   * @returns {boolean}
   */
  function validateAll(fieldRules) {
    var allValid = true;
    var firstInvalid = null;
    for (var i = 0; i < fieldRules.length; i++) {
      var ok = validateField(fieldRules[i].input, fieldRules[i].rules);
      if (!ok && allValid) {
        allValid = false;
        firstInvalid = fieldRules[i].input;
      }
    }
    if (firstInvalid) firstInvalid.focus();
    return allValid;
  }

  // =========================================================================
  // RETRY WRAPPER
  // =========================================================================

  /**
   * Wrap an async action with retry-on-failure and an optional "Réessayer" button.
   * @param {object} opts
   * @param {function} opts.action - Async function to execute
   * @param {HTMLElement} [opts.container] - Where to show retry button on failure
   * @param {string} [opts.errorMsg] - User-visible error message
   * @param {number} [opts.maxRetries=1] - Max automatic retries (0 = manual only)
   * @returns {Promise<*>} Result of action()
   */
  async function withRetry(opts) {
    var retries = opts.maxRetries || 0;
    var attempt = 0;
    while (true) {
      try {
        return await opts.action();
      } catch (e) {
        attempt++;
        if (attempt <= retries) continue;
        // Show retry button in container
        if (opts.container) {
          var msg = opts.errorMsg || 'Erreur de chargement';
          opts.container.innerHTML =
            '<div class="retry-block">' +
              '<p class="text-muted text-sm">' + Utils.escapeHtml(msg) + '</p>' +
              '<button class="btn btn-secondary btn-sm retry-btn" type="button">' +
                '<svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-refresh-cw"></use></svg>' +
                ' R\u00e9essayer' +
              '</button>' +
            '</div>';
          return new Promise(function (resolve) {
            opts.container.querySelector('.retry-btn').addEventListener('click', function () {
              opts.container.innerHTML = '<div class="text-center p-4 text-muted">Chargement\u2026</div>';
              resolve(withRetry(opts));
            });
          });
        }
        throw e;
      }
    }
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
    formatPct: formatPct,
    fieldError: fieldError,
    fieldClear: fieldClear,
    fieldValid: fieldValid,
    validateField: validateField,
    liveValidate: liveValidate,
    validateAll: validateAll,
    withRetry: withRetry,
    skeleton: skeleton,
    clearSkeleton: clearSkeleton
  };

})();
