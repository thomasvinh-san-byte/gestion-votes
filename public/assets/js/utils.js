/**
 * utils.js - Utilitaires JS avec support CSRF intégré
 * 
 * REMPLACE ou AUGMENTE le utils.js existant.
 * Ajoute le support CSRF automatique aux appels API.
 */

window.Utils = window.Utils || {};

(function(Utils) {
  'use strict';

  // ==========================================================================
  // CSRF SUPPORT
  // ==========================================================================

  function getCsrfToken() {
    // 1. Meta tag
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');
    
    // 2. window.CSRF
    if (window.CSRF && window.CSRF.token) return window.CSRF.token;
    
    return null;
  }

  function getCsrfHeaders() {
    const token = getCsrfToken();
    return token ? { 'X-CSRF-Token': token } : {};
  }

  // ==========================================================================
  // HTTP HELPERS (AVEC CSRF)
  // ==========================================================================

  function buildHeaders(extra = {}) {
    return {
      ...getCsrfHeaders(),
      ...extra,
    };
  }

  Utils.apiGet = async function(url, options = {}) {
    const response = await fetch(url, {
      method: 'GET',
      headers: buildHeaders(options.headers || {}),
      credentials: 'same-origin',
      ...options,
    });
    
    if (!response.ok) {
      const error = await response.json().catch(() => ({ error: 'network_error' }));
      throw new Error(error.error || 'request_failed');
    }
    
    return response.json();
  };

  Utils.apiPost = async function(url, data = {}, options = {}) {
    const response = await fetch(url, {
      method: 'POST',
      headers: buildHeaders({
        'Content-Type': 'application/json',
        ...(options.headers || {}),
      }),
      credentials: 'same-origin',
      body: JSON.stringify(data),
      ...options,
    });
    
    if (!response.ok) {
      const error = await response.json().catch(() => ({ error: 'network_error' }));
      throw new Error(error.error || 'request_failed');
    }
    
    return response.json();
  };

  Utils.apiDelete = async function(url, options = {}) {
    const response = await fetch(url, {
      method: 'DELETE',
      headers: buildHeaders(options.headers || {}),
      credentials: 'same-origin',
      ...options,
    });
    
    if (!response.ok) {
      const error = await response.json().catch(() => ({ error: 'network_error' }));
      throw new Error(error.error || 'request_failed');
    }
    
    return response.json();
  };

  // ==========================================================================
  // HELPERS EXISTANTS (conservés)
  // ==========================================================================

  Utils.escapeHtml = function(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  };

  Utils.formatDate = function(dateStr) {
    if (!dateStr) return '—';
    try {
      const d = new Date(dateStr);
      return d.toLocaleString('fr-FR', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      });
    } catch (e) {
      return dateStr;
    }
  };

  Utils.debounce = function(fn, delay = 300) {
    let timeout;
    return function(...args) {
      clearTimeout(timeout);
      timeout = setTimeout(() => fn.apply(this, args), delay);
    };
  };

  Utils.throttle = function(fn, limit = 100) {
    let inThrottle;
    return function(...args) {
      if (!inThrottle) {
        fn.apply(this, args);
        inThrottle = true;
        setTimeout(() => inThrottle = false, limit);
      }
    };
  };

  // ==========================================================================
  // CSRF HELPERS
  // ==========================================================================

  Utils.getCsrfToken = getCsrfToken;

  Utils.addCsrfToForm = function(form) {
    const token = getCsrfToken();
    if (!token) return;
    
    let input = form.querySelector('input[name="csrf_token"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'csrf_token';
      form.appendChild(input);
    }
    input.value = token;
  };

  Utils.initCsrfForms = function() {
    document.querySelectorAll('form[method="post"], form[method="POST"]').forEach(form => {
      Utils.addCsrfToForm(form);
    });
  };

  // ==========================================================================
  // HTMX INTEGRATION
  // ==========================================================================

  // Auto-configure HTMX pour envoyer le CSRF token
  document.body.addEventListener('htmx:configRequest', function(e) {
    const token = getCsrfToken();
    if (token) {
      e.detail.headers['X-CSRF-Token'] = token;
    }
  });

  // Re-init CSRF après HTMX swap
  document.body.addEventListener('htmx:afterSwap', function() {
    Utils.initCsrfForms();
  });

  // ==========================================================================
  // MISSING UTILITY FUNCTIONS
  // ==========================================================================

  /**
   * Show toast notification (aliased as Utils.toast for callers)
   */
  Utils.toast = function(type, message, duration) {
    if (typeof setNotif === 'function') {
      setNotif(type, message, duration);
    } else {
      console[type === 'error' ? 'error' : 'log']('[toast]', message);
    }
  };

  /**
   * Get meeting ID from URL or data attributes
   */
  Utils.getMeetingId = function() {
    const params = new URLSearchParams(window.location.search);
    const fromUrl = params.get('meeting_id');
    if (fromUrl) return fromUrl;

    const el = document.querySelector('[data-meeting-id]');
    if (el) return el.dataset.meetingId;

    try {
      return localStorage.getItem('operator.meeting_id') || localStorage.getItem('public.meeting_id') || '';
    } catch(e) {
      return '';
    }
  };

  /**
   * Humanize error messages from API
   * Préfère 'message' (message français traduit) sur 'error' (code technique)
   */
  Utils.humanizeError = function(err) {
    if (!err) return 'Erreur inconnue';
    if (typeof err === 'string') return err;
    // Priorité: message > detail > error > stringify
    return err.message || err.detail || err.error || JSON.stringify(err);
  };

  /**
   * Extract error message from API response body
   * @param {object} body - API response body
   * @param {string} fallback - Default message if no error found
   * @returns {string} Human-readable error message
   */
  Utils.getApiError = function(body, fallback = 'Une erreur est survenue') {
    if (!body) return fallback;
    // Priorité: message (FR traduit) > detail > error (code)
    return body.message || body.detail || body.error || fallback;
  };

  // Init au chargement
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', Utils.initCsrfForms);
  } else {
    Utils.initCsrfForms();
  }

})(window.Utils);

// ==========================================================================
// GLOBAL CONVENIENCE FUNCTIONS
// ==========================================================================

/**
 * Global API function - simplified wrapper
 * @param {string} url - API endpoint
 * @param {object} data - Data to POST (null for GET)
 * @returns {Promise<{status: number, body: object}>}
 */
async function api(url, data = null, method = null) {
  const hasBody = data !== null;
  const httpMethod = method || (hasBody ? 'POST' : 'GET');

  const headers = {
    'Content-Type': 'application/json',
  };

  // Add CSRF token
  if (window.Utils && window.Utils.getCsrfToken) {
    const token = window.Utils.getCsrfToken();
    if (token) headers['X-CSRF-Token'] = token;
  }

  try {
    const response = await fetch(url, {
      method: httpMethod,
      headers,
      credentials: 'same-origin',
      body: hasBody ? JSON.stringify(data) : undefined,
    });
    
    const body = await response.json().catch(() => ({}));
    return { status: response.status, body };
  } catch (err) {
    console.error('API Error:', err);
    return { status: 0, body: { ok: false, error: 'network_error', message: err.message } };
  }
}

/**
 * Create SVG icon from sprite
 * @param {string} name - Icon name (without 'icon-' prefix)
 * @param {string} className - Additional CSS classes
 * @returns {string} SVG markup
 */
function icon(name, className = '') {
  const classes = ['icon', className].filter(Boolean).join(' ');
  return `<svg class="${classes}" aria-hidden="true"><use href="/assets/icons.svg#icon-${name}"></use></svg>`;
}

// Make icon function globally available
window.icon = icon;

/**
 * Display notification toast
 * @param {string} type - 'success', 'error', 'warning', 'info'
 * @param {string} message - Message to display
 * @param {number} duration - Auto-dismiss duration in ms (0 = no auto-dismiss)
 */
function setNotif(type, message, duration = 5000) {
  const container = document.getElementById('notif_box') || createNotifContainer();

  // Map types to CSS classes
  const typeMap = {
    success: 'toast-success',
    error: 'toast-danger',
    danger: 'toast-danger',
    warning: 'toast-warning',
    info: 'toast-info',
  };

  // Create toast element
  const toast = document.createElement('div');
  toast.className = `toast ${typeMap[type] || 'toast-info'}`;
  toast.setAttribute('role', 'alert');

  // Icon based on type
  const iconMap = {
    success: 'check-circle',
    error: 'x-circle',
    danger: 'x-circle',
    warning: 'alert-triangle',
    info: 'info',
  };

  toast.innerHTML = `
    <span class="toast-icon">${icon(iconMap[type] || iconMap.info, 'icon-md')}</span>
    <span class="toast-message">${escapeHtml(message)}</span>
    <button class="toast-close btn btn-ghost btn-icon btn-sm" aria-label="Fermer">${icon('x', 'icon-sm')}</button>
  `;
  
  // Close button handler
  const closeBtn = toast.querySelector('.toast-close');
  closeBtn.addEventListener('click', () => {
    toast.style.animation = 'slideOutRight 0.3s ease-out forwards';
    setTimeout(() => toast.remove(), 300);
  });
  
  // Show container if hidden
  container.classList.remove('hidden');
  container.style.display = 'flex';

  // Limit to 3 toasts max - remove oldest if needed
  const MAX_TOASTS = 3;
  const existingToasts = container.querySelectorAll('.toast');
  if (existingToasts.length >= MAX_TOASTS) {
    // Remove oldest toasts (first ones in the container)
    for (let i = 0; i <= existingToasts.length - MAX_TOASTS; i++) {
      existingToasts[i].remove();
    }
  }

  // Add to container
  container.appendChild(toast);

  // Auto-dismiss
  if (duration > 0) {
    setTimeout(() => {
      if (toast.parentNode) {
        toast.style.animation = 'slideOutRight 0.3s ease-out forwards';
        setTimeout(() => toast.remove(), 300);
      }
    }, duration);
  }
}

/**
 * Create notification container if not exists
 */
function createNotifContainer() {
  let container = document.getElementById('notif_box');
  if (!container) {
    container = document.createElement('div');
    container.id = 'notif_box';
    container.className = 'toast-container';
    container.style.cssText = `
      position: fixed;
      top: 1rem;
      right: 1rem;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      pointer-events: none;
    `;
    document.body.appendChild(container);
  }
  return container;
}

/**
 * Escape HTML entities
 */
function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/**
 * Get URL parameter
 */
function getParam(name) {
  const params = new URLSearchParams(window.location.search);
  return params.get(name);
}

/**
 * Format date to French locale
 */
function formatDate(dateStr) {
  if (!dateStr) return '—';
  try {
    const d = new Date(dateStr);
    return d.toLocaleString('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch (e) {
    return dateStr;
  }
}

/**
 * Global loading state toggle
 */
function setLoading(on) {
  const el = document.getElementById('loading') || document.getElementById('spinner');
  if (el) el.style.display = on ? 'block' : 'none';
  document.body.classList.toggle('loading', !!on);
}

/**
 * Global log helper (debug console)
 */
function log(...args) {
  const box = document.getElementById('log_box');
  if (box) {
    const line = document.createElement('div');
    line.className = 'log-line';
    line.textContent = args.map(a => typeof a === 'object' ? JSON.stringify(a) : String(a)).join(' ');
    box.prepend(line);
    while (box.children.length > 50) box.removeChild(box.lastElementChild);
  }
  console.log('[AG-VOTE]', ...args);
}

/**
 * Extract error message from API response body
 * Préfère 'message' (FR traduit) sur 'error' (code technique)
 * @param {object} body - API response body
 * @param {string} fallback - Default message if no error found
 * @returns {string} Human-readable error message
 */
function getApiError(body, fallback = 'Une erreur est survenue') {
  if (!body) return fallback;
  return body.message || body.detail || body.error || fallback;
}

// Add slide-out animation
const style = document.createElement('style');
style.textContent = `
  @keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
  }
  .toast {
    pointer-events: auto;
  }
  .toast-icon {
    flex-shrink: 0;
  }
  .toast-message {
    flex: 1;
  }
  .toast-close {
    flex-shrink: 0;
    opacity: 0.7;
  }
  .toast-close:hover {
    opacity: 1;
  }
`;
document.head.appendChild(style);
