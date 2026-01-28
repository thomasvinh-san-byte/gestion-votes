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
  // API KEY STORAGE (existant, conservé)
  // ==========================================================================

  const STORAGE_KEY = 'ag_vote_api_key';

  Utils.getStoredApiKey = function() {
    try {
      return localStorage.getItem(STORAGE_KEY) || '';
    } catch (e) {
      return '';
    }
  };

  Utils.setStoredApiKey = function(key) {
    try {
      if (key) {
        localStorage.setItem(STORAGE_KEY, key);
      } else {
        localStorage.removeItem(STORAGE_KEY);
      }
    } catch (e) {
      console.warn('localStorage unavailable');
    }
  };

  Utils.bindApiKeyInput = function(role, inputEl, onChange) {
    const storageKey = role + '.api_key';
    const saved = localStorage.getItem(storageKey) || '';
    if (inputEl && saved) inputEl.value = saved;
    
    if (inputEl) {
      inputEl.addEventListener('change', () => {
        localStorage.setItem(storageKey, inputEl.value || '');
        if (onChange) onChange();
      });
    }
  };

  // ==========================================================================
  // HTTP HELPERS (MIS À JOUR AVEC CSRF)
  // ==========================================================================

  function buildHeaders(extra = {}) {
    const headers = {
      ...getCsrfHeaders(),
      ...extra,
    };
    
    const apiKey = Utils.getStoredApiKey();
    if (apiKey) {
      headers['X-Api-Key'] = apiKey;
    }
    
    return headers;
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
    
    const apiKey = Utils.getStoredApiKey();
    if (apiKey) {
      e.detail.headers['X-Api-Key'] = apiKey;
    }
  });

  // Re-init CSRF après HTMX swap
  document.body.addEventListener('htmx:afterSwap', function() {
    Utils.initCsrfForms();
  });

  // Init au chargement
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', Utils.initCsrfForms);
  } else {
    Utils.initCsrfForms();
  }

})(window.Utils);
