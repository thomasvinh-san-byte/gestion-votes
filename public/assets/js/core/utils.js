/**
 * utils.js - JS utilities with built-in CSRF support
 *
 * REPLACES or EXTENDS the existing utils.js.
 * Adds automatic CSRF support to API calls.
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
  // HTTP HELPERS (WITH CSRF)
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
  // EXISTING HELPERS (preserved)
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
  // FUZZY SEARCH UTILITIES
  // ==========================================================================

  /**
   * Fuzzy search algorithm
   * Returns a score (higher = better match) or -1 for no match
   * @param {string} pattern - Search pattern
   * @param {string} str - String to search in
   * @returns {{score: number, matches: Array<[number, number]>}} Score and match ranges
   */
  Utils.fuzzyMatch = function(pattern, str) {
    if (!pattern) return { score: 1, matches: [] };
    if (!str) return { score: -1, matches: [] };

    pattern = pattern.toLowerCase();
    str = str.toLowerCase();

    // Exact match bonus
    if (str === pattern) {
      const idx = 0;
      return { score: 100, matches: [[idx, pattern.length]] };
    }
    if (str.includes(pattern)) {
      const idx = str.indexOf(pattern);
      return {
        score: 50 + (pattern.length / str.length) * 30,
        matches: [[idx, idx + pattern.length]]
      };
    }

    // Fuzzy matching with consecutive character bonus
    let patternIdx = 0;
    let score = 0;
    let consecutiveBonus = 0;
    let lastMatchIdx = -2;
    const matches = [];

    for (let i = 0; i < str.length && patternIdx < pattern.length; i++) {
      if (str[i] === pattern[patternIdx]) {
        // Consecutive match bonus
        if (i === lastMatchIdx + 1) {
          consecutiveBonus += 5;
        } else {
          consecutiveBonus = 0;
        }

        // Word boundary bonus
        const isWordStart = i === 0 || /[\s\-_.,@]/.test(str[i - 1]);
        const wordBonus = isWordStart ? 10 : 0;

        score += 1 + consecutiveBonus + wordBonus;
        matches.push([i, i + 1]);
        lastMatchIdx = i;
        patternIdx++;
      }
    }

    // All pattern characters must be found
    if (patternIdx !== pattern.length) {
      return { score: -1, matches: [] };
    }

    // Normalize score by pattern length
    score = score / pattern.length;

    return { score, matches: Utils.mergeMatchRanges(matches) };
  };

  /**
   * Merge adjacent match ranges
   * @param {Array<[number, number]>} matches - Array of [start, end] pairs
   * @returns {Array<[number, number]>} Merged ranges
   */
  Utils.mergeMatchRanges = function(matches) {
    if (!matches.length) return [];
    const sorted = matches.sort((a, b) => a[0] - b[0]);
    const merged = [sorted[0]];
    for (let i = 1; i < sorted.length; i++) {
      const last = merged[merged.length - 1];
      if (sorted[i][0] <= last[1]) {
        last[1] = Math.max(last[1], sorted[i][1]);
      } else {
        merged.push(sorted[i]);
      }
    }
    return merged;
  };

  /**
   * Highlight matched characters in text with <mark> tags
   * @param {string} text - Original text
   * @param {Array<[number, number]>} matches - Match ranges from fuzzyMatch
   * @returns {string} HTML string with highlighted matches
   */
  Utils.highlightMatches = function(text, matches) {
    if (!matches || !matches.length || !text) {
      return Utils.escapeHtml(text || '');
    }

    let result = '';
    let lastEnd = 0;

    for (const [start, end] of matches) {
      result += Utils.escapeHtml(text.substring(lastEnd, start));
      result += '<mark>' + Utils.escapeHtml(text.substring(start, end)) + '</mark>';
      lastEnd = end;
    }
    result += Utils.escapeHtml(text.substring(lastEnd));

    return result;
  };

  /**
   * Filter and sort an array of items by fuzzy matching against specified fields
   * @param {Array} items - Array of items to filter
   * @param {string} pattern - Search pattern
   * @param {Array<string>} fields - Fields to search in (e.g., ['name', 'email'])
   * @returns {Array} Filtered and sorted items with _score and _matches properties
   */
  Utils.fuzzyFilter = function(items, pattern, fields) {
    if (!pattern || !pattern.trim()) {
      return items.map(item => ({ ...item, _score: 1, _matches: {} }));
    }

    const results = [];
    for (const item of items) {
      let bestScore = -1;
      const fieldMatches = {};

      for (const field of fields) {
        const value = item[field];
        if (value && typeof value === 'string') {
          const match = Utils.fuzzyMatch(pattern, value);
          if (match.score > 0) {
            fieldMatches[field] = match.matches;
            if (match.score > bestScore) {
              bestScore = match.score;
            }
          }
        }
      }

      if (bestScore > 0) {
        results.push({
          ...item,
          _score: bestScore,
          _matches: fieldMatches
        });
      }
    }

    // Sort by score (descending)
    results.sort((a, b) => b._score - a._score);
    return results;
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

  // Auto-configure HTMX to send CSRF token
  document.body.addEventListener('htmx:configRequest', function(e) {
    const token = getCsrfToken();
    if (token) {
      e.detail.headers['X-CSRF-Token'] = token;
    }
  });

  // Re-init CSRF after HTMX swap
  document.body.addEventListener('htmx:afterSwap', function() {
    Utils.initCsrfForms();
  });

  // ==========================================================================
  // MISSING UTILITY FUNCTIONS
  // ==========================================================================

  /**
   * Show toast notification using unified AgToast system
   */
  Utils.toast = function(type, message, duration = 5000) {
    // Normalize type
    const normalizedType = (type === 'danger') ? 'error' : type;

    // Use AgToast directly if available
    if (typeof AgToast !== 'undefined' && AgToast.show) {
      AgToast.show(normalizedType, message, duration);
      return;
    }

    // Fallback to setNotif
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
   * Prefers 'message' (translated message) over 'error' (technical code)
   */
  Utils.humanizeError = function(err) {
    if (!err) return 'Unknown error';
    if (typeof err === 'string') return err;
    // Priority: message > detail > error > stringify
    return err.message || err.detail || err.error || JSON.stringify(err);
  };

  /**
   * Extract error message from API response body
   * @param {object} body - API response body
   * @param {string} fallback - Default message if no error found
   * @returns {string} Human-readable error message
   */
  Utils.getApiError = function(body, fallback = 'An error occurred') {
    if (!body) return fallback;
    // Priority: message (translated) > detail > error (code)
    return body.message || body.detail || body.error || fallback;
  };

  // ==========================================================================
  // VALIDATION UTILITIES
  // ==========================================================================

  /**
   * Validate email format
   * @param {string} email - Email to validate
   * @returns {boolean} True if valid email format
   */
  Utils.isValidEmail = function(email) {
    if (!email || typeof email !== 'string') return false;
    // RFC 5322 compliant regex (simplified)
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email.trim());
  };

  /**
   * Validate email and return error message if invalid
   * @param {string} email - Email to validate
   * @returns {{valid: boolean, error?: string}}
   */
  Utils.validateEmail = function(email) {
    if (!email || email.trim() === '') {
      return { valid: true }; // Empty is valid (not required)
    }
    if (!Utils.isValidEmail(email)) {
      return { valid: false, error: 'Invalid email format' };
    }
    return { valid: true };
  };

  /**
   * Add validation feedback to email input
   * @param {HTMLInputElement} input - Email input element
   */
  Utils.setupEmailValidation = function(input) {
    if (!input) return;

    const validate = () => {
      const result = Utils.validateEmail(input.value);
      input.classList.toggle('is-invalid', !result.valid);
      input.classList.toggle('is-valid', result.valid && input.value.trim() !== '');

      // Update or create error message
      let errorEl = input.parentElement?.querySelector('.validation-error');
      if (!result.valid) {
        if (!errorEl) {
          errorEl = document.createElement('div');
          errorEl.className = 'validation-error text-sm text-danger mt-1';
          input.parentElement?.appendChild(errorEl);
        }
        errorEl.textContent = result.error;
      } else if (errorEl) {
        errorEl.remove();
      }

      return result.valid;
    };

    input.addEventListener('blur', validate);
    input.addEventListener('input', Utils.debounce(validate, 500));

    return validate;
  };

  // ==========================================================================
  // CSV UTILITIES
  // ==========================================================================

  /**
   * Parse CSV content into array of objects
   * @param {string} content - CSV content
   * @param {object} options - Options: {delimiter, hasHeader}
   * @returns {{headers: string[], rows: object[], errors: string[]}}
   */
  Utils.parseCSV = function(content, options = {}) {
    const delimiter = options.delimiter || ',';
    const hasHeader = options.hasHeader !== false;

    const errors = [];
    const lines = content.trim().split(/\r?\n/).filter(l => l.trim() !== '');

    if (lines.length === 0) {
      return { headers: [], rows: [], errors: ['Empty CSV file'] };
    }

    // Parse header row
    const headerLine = hasHeader ? lines[0] : null;
    const headers = headerLine
      ? Utils.parseCSVLine(headerLine, delimiter)
      : ['name', 'email', 'voting_power'];

    // Parse data rows
    const dataLines = hasHeader ? lines.slice(1) : lines;
    const rows = [];

    dataLines.forEach((line, idx) => {
      const lineNum = hasHeader ? idx + 2 : idx + 1;
      const values = Utils.parseCSVLine(line, delimiter);

      if (values.length === 0) return;

      const row = {};
      headers.forEach((h, i) => {
        row[h.toLowerCase().trim()] = values[i]?.trim() || '';
      });

      // Basic validation
      const name = row['name'] || row['nom'] || row['full_name'] || '';
      const email = row['email'] || row['e-mail'] || row['mail'] || '';
      const power = row['voting_power'] || row['poids'] || row['weight'] || '';

      if (!name) {
        errors.push(`Line ${lineNum}: missing name`);
      }

      if (email && !Utils.isValidEmail(email)) {
        errors.push(`Line ${lineNum}: invalid email (${email})`);
      }

      if (power && isNaN(parseFloat(power))) {
        errors.push(`Line ${lineNum}: invalid voting power (${power})`);
      }

      rows.push({
        _line: lineNum,
        _raw: line,
        name,
        email,
        voting_power: power ? parseFloat(power) : 1,
      });
    });

    return { headers, rows, errors };
  };

  /**
   * Parse a single CSV line handling quotes
   * @param {string} line - CSV line
   * @param {string} delimiter - Field delimiter
   * @returns {string[]} Array of field values
   */
  Utils.parseCSVLine = function(line, delimiter = ',') {
    const values = [];
    let current = '';
    let inQuotes = false;

    for (let i = 0; i < line.length; i++) {
      const char = line[i];
      const nextChar = line[i + 1];

      if (inQuotes) {
        if (char === '"' && nextChar === '"') {
          current += '"';
          i++;
        } else if (char === '"') {
          inQuotes = false;
        } else {
          current += char;
        }
      } else {
        if (char === '"') {
          inQuotes = true;
        } else if (char === delimiter) {
          values.push(current);
          current = '';
        } else {
          current += char;
        }
      }
    }

    values.push(current);
    return values;
  };

  /**
   * Generate HTML preview table for CSV data
   * @param {object} parsed - Result from parseCSV
   * @param {number} maxRows - Maximum rows to show
   * @returns {string} HTML string
   */
  Utils.generateCSVPreview = function(parsed, maxRows = 10) {
    const { rows, errors } = parsed;

    if (rows.length === 0) {
      return '<div class="text-muted p-4 text-center">No data to import</div>';
    }

    let html = '';

    // Show errors if any
    if (errors.length > 0) {
      html += '<div class="alert alert-warning mb-4">';
      html += '<strong>Warning:</strong>';
      html += '<ul class="mb-0 mt-2">';
      errors.slice(0, 5).forEach(err => {
        html += `<li>${Utils.escapeHtml(err)}</li>`;
      });
      if (errors.length > 5) {
        html += `<li>... and ${errors.length - 5} other error(s)</li>`;
      }
      html += '</ul></div>';
    }

    // Summary
    html += `<div class="mb-4"><strong>${rows.length}</strong> member(s) to import</div>`;

    // Preview table
    html += '<div class="table-container" style="max-height:300px;overflow:auto;">';
    html += '<table class="table table-sm">';
    html += '<thead><tr><th>#</th><th>Name</th><th>Email</th><th>Weight</th></tr></thead>';
    html += '<tbody>';

    const displayRows = rows.slice(0, maxRows);
    displayRows.forEach((row, idx) => {
      const emailClass = row.email && !Utils.isValidEmail(row.email) ? 'text-danger' : '';
      html += '<tr>';
      html += `<td class="text-muted">${idx + 1}</td>`;
      html += `<td>${Utils.escapeHtml(row.name) || '<span class="text-danger">—</span>'}</td>`;
      html += `<td class="${emailClass}">${Utils.escapeHtml(row.email) || '—'}</td>`;
      html += `<td>${row.voting_power}</td>`;
      html += '</tr>';
    });

    if (rows.length > maxRows) {
      html += `<tr><td colspan="4" class="text-center text-muted">... and ${rows.length - maxRows} other(s)</td></tr>`;
    }

    html += '</tbody></table></div>';

    return html;
  };

  // Init on load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', Utils.initCsrfForms);
  } else {
    Utils.initCsrfForms();
  }

})(window.Utils);

/**
 * Global email validation function
 */
function isValidEmail(email) {
  return Utils.isValidEmail(email);
}

/**
 * Global CSV parse function
 */
function parseCSV(content, options) {
  return Utils.parseCSV(content, options);
}

// ==========================================================================
// GLOBAL CONVENIENCE FUNCTIONS
// ==========================================================================

/**
 * Global API function - simplified wrapper
 * @param {string} url - API endpoint
 * @param {object} data - Data to POST (null for GET)
 * @returns {Promise<{status: number, body: object}>}
 */
/**
 * Default timeout for API calls (15 seconds)
 */
const API_TIMEOUT_MS = 15000;

async function api(url, data = null, method = null, timeoutMs = API_TIMEOUT_MS) {
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

  // AbortController for timeout
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);

  try {
    const response = await fetch(url, {
      method: httpMethod,
      headers,
      credentials: 'same-origin',
      body: hasBody ? JSON.stringify(data) : undefined,
      signal: controller.signal,
    });

    const body = await response.json().catch(() => ({}));
    return { status: response.status, body };
  } catch (err) {
    if (err.name === 'AbortError') {
      console.error('API Timeout:', url);
      return { status: 0, body: { ok: false, error: 'timeout', message: 'La requête a expiré (délai dépassé)' } };
    }
    console.error('API Error:', err);
    return { status: 0, body: { ok: false, error: 'network_error', message: err.message } };
  } finally {
    clearTimeout(timer);
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
 * Uses AgToast web component for unified notification system.
 *
 * @param {string} type - 'success', 'error', 'warning', 'info'
 * @param {string} message - Message to display
 * @param {number} duration - Auto-dismiss duration in ms (0 = no auto-dismiss)
 */
function setNotif(type, message, duration = 5000) {
  // Normalize type (danger -> error for AgToast compatibility)
  const normalizedType = (type === 'danger') ? 'error' : type;

  // Use AgToast if available (preferred modern approach)
  if (typeof AgToast !== 'undefined' && AgToast.show) {
    AgToast.show(normalizedType, message, duration);
    return;
  }

  // Fallback: console logging if AgToast not loaded
  console[type === 'error' || type === 'danger' ? 'error' : 'log']('[toast]', message);
}

/**
 * Create notification container if not exists
 * Uses aria-live region for screen reader announcements
 */
function createNotifContainer() {
  let container = document.getElementById('notif_box');
  if (!container) {
    container = document.createElement('div');
    container.id = 'notif_box';
    container.className = 'toast-container';
    // ARIA live region for accessibility
    container.setAttribute('aria-live', 'polite');
    container.setAttribute('aria-atomic', 'false');
    container.setAttribute('aria-relevant', 'additions');
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
 * Prefers 'message' (translated) over 'error' (technical code)
 * @param {object} body - API response body
 * @param {string} fallback - Default message if no error found
 * @returns {string} Human-readable error message
 */
function getApiError(body, fallback = 'An error occurred') {
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
