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
        sidebar.innerHTML = '<div class="p-4 text-muted text-sm">Navigation indisponible</div>';
      });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebar);
  } else {
    initSidebar();
  }

  // =========================================================================
  // ROLE & STATUS LABEL MAPS
  // =========================================================================

  const ROLE_LABELS_SYSTEM = {
    admin: 'Administrateur',
    operator: 'Opérateur',
    auditor: 'Auditeur',
    viewer: 'Observateur'
  };

  const ROLE_LABELS_MEETING = {
    president: 'Président',
    assessor: 'Assesseur',
    voter: 'Électeur'
  };

  const ROLE_LABELS_ALL = {};
  Object.keys(ROLE_LABELS_SYSTEM).forEach(function (k) { ROLE_LABELS_ALL[k] = ROLE_LABELS_SYSTEM[k]; });
  Object.keys(ROLE_LABELS_MEETING).forEach(function (k) { ROLE_LABELS_ALL[k] = ROLE_LABELS_MEETING[k]; });

  const MEETING_STATUS_MAP = {
    draft:     { badge: 'badge-neutral',          text: 'Brouillon' },
    scheduled: { badge: 'badge-info',             text: 'Programmée' },
    frozen:    { badge: 'badge-info',             text: 'Verrouillée' },
    live:      { badge: 'badge-danger badge-dot', text: 'En cours' },
    closed:    { badge: 'badge-success',          text: 'Terminée' },
    validated: { badge: 'badge-success',          text: 'Validée' },
    archived:  { badge: 'badge-neutral',          text: 'Archivée' }
  };

  // =========================================================================
  // UTILITY FUNCTIONS
  // =========================================================================

  /**
   * Get initials from a full name (max 2 chars).
   */
  function getInitials(name) {
    return (name || '?')
      .split(' ')
      .map(function (w) { return w.charAt(0); })
      .join('')
      .substring(0, 2)
      .toUpperCase();
  }

  /**
   * Set a button's loading state with spinner + disabled.
   * @param {HTMLButtonElement} btn
   * @param {boolean} loading
   */
  function btnLoading(btn, loading) {
    if (!btn) return;
    if (loading) {
      btn.disabled = true;
      btn.classList.add('loading');
      btn._prevHtml = btn.innerHTML;
      const label = btn.textContent.trim();
      btn.innerHTML = '<span class="spinner spinner-sm"></span> <span>' + label + '</span>';
    } else {
      btn.disabled = false;
      btn.classList.remove('loading');
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
        '<div class="modal-title">' + (opts.title || '') + '</div>' +
        '<button class="btn btn-ghost btn-icon btn-sm modal-close-btn" type="button" aria-label="Fermer">&times;</button>' +
      '</div>' +
      '<div class="modal-body">' + (opts.body || '') + '</div>' +
      '<div class="modal-footer">' +
        '<button class="btn btn-secondary modal-cancel-btn" type="button">' + (opts.cancelText || 'Annuler') + '</button>' +
        '<button class="btn ' + (opts.confirmClass || 'btn-primary') + ' modal-confirm-btn" type="button">' + (opts.confirmText || 'Confirmer') + '</button>' +
      '</div>';

    backdrop.appendChild(modal);
    document.body.appendChild(backdrop);

    // Focus first input if any
    const firstInput = modal.querySelector('input, select, textarea');
    if (firstInput) setTimeout(function () { firstInput.focus(); }, 50);

    function close() {
      backdrop.classList.remove('open');
      setTimeout(function () { backdrop.remove(); }, 200);
    }

    modal.querySelector('.modal-close-btn').addEventListener('click', close);
    modal.querySelector('.modal-cancel-btn').addEventListener('click', close);
    backdrop.addEventListener('click', function (e) {
      if (e.target === backdrop) close();
    });
    document.addEventListener('keydown', function handler(e) {
      if (e.key === 'Escape') { close(); document.removeEventListener('keydown', handler); }
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
  // EXPORTS
  // =========================================================================

  window.Shared = {
    ROLE_LABELS_SYSTEM: ROLE_LABELS_SYSTEM,
    ROLE_LABELS_MEETING: ROLE_LABELS_MEETING,
    ROLE_LABELS_ALL: ROLE_LABELS_ALL,
    MEETING_STATUS_MAP: MEETING_STATUS_MAP,
    getInitials: getInitials,
    btnLoading: btnLoading,
    openModal: openModal
  };

})();
