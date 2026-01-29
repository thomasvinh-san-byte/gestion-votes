/**
 * AG-VOTE Admin Application Shell
 *
 * Handles navigation, toast notifications, and API helpers.
 * All admin pages are loaded as HTMX fragments.
 */
'use strict';

const AdminApp = (function() {

  let currentPage = 'dashboard';

  const pageTitles = {
    dashboard:  'Tableau de bord',
    users:      'Gestion des utilisateurs',
    policies:   'Politiques de vote et quorum',
    meetings:   'Vue des seances',
    monitoring: 'Monitoring systeme',
    audit:      'Journal d\'audit',
    alerts:     'Alertes systeme',
  };

  /** Navigate to a page by loading its fragment */
  function navigate(page) {
    if (!pageTitles[page]) return;
    currentPage = page;

    // Update sidebar active state
    document.querySelectorAll('.admin-nav-item').forEach(function(el) {
      el.classList.toggle('active', el.getAttribute('data-page') === page);
    });

    // Update header title
    var titleEl = document.getElementById('page-title');
    if (titleEl) titleEl.textContent = pageTitles[page];

    // Load fragment via HTMX
    var container = document.getElementById('page-container');
    if (container) {
      htmx.ajax('GET', '/admin/fragments/' + page + '.php', {
        target: '#page-container',
        swap: 'innerHTML'
      });
    }
  }

  /** Show a toast notification */
  function toast(message, type, duration) {
    type = type || 'info';
    duration = duration || 4000;

    var container = document.getElementById('toast-container');
    if (!container) return;

    var el = document.createElement('div');
    el.className = 'toast toast-' + type;
    el.innerHTML = '<div><strong>' + _escapeHtml(_toastTitle(type)) + '</strong>' +
                   '<div class="text-sm mt-1">' + _escapeHtml(message) + '</div></div>' +
                   '<button class="btn btn-ghost btn-sm btn-icon" onclick="this.parentElement.remove()">' +
                   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">' +
                   '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>';

    container.appendChild(el);

    setTimeout(function() {
      if (el.parentElement) {
        el.style.opacity = '0';
        el.style.transform = 'translateX(100%)';
        el.style.transition = 'all 300ms';
        setTimeout(function() { el.remove(); }, 300);
      }
    }, duration);
  }

  function _toastTitle(type) {
    var titles = { success: 'Succes', danger: 'Erreur', warning: 'Attention', info: 'Information' };
    return titles[type] || 'Info';
  }

  /** Secure fetch wrapper with CSRF token */
  function apiFetch(url, options) {
    options = options || {};
    var headers = options.headers || {};
    headers['Content-Type'] = headers['Content-Type'] || 'application/json';

    // Add CSRF token
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
      headers['X-CSRF-Token'] = csrfMeta.getAttribute('content');
    }

    options.headers = headers;

    return fetch(url, options)
      .then(function(res) {
        return res.json().then(function(data) {
          return { status: res.status, ok: res.ok, data: data };
        });
      })
      .catch(function(err) {
        toast('Erreur reseau : ' + err.message, 'danger');
        throw err;
      });
  }

  /** Confirm dialog */
  function confirm(message, callback) {
    if (window.confirm(message)) {
      callback();
    }
  }

  /** Copy text to clipboard */
  function copyToClipboard(text) {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text).then(function() {
        toast('Copie dans le presse-papiers', 'success', 2000);
      });
    } else {
      // Fallback
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      toast('Copie dans le presse-papiers', 'success', 2000);
    }
  }

  /** Format date for display */
  function formatDate(dateStr) {
    if (!dateStr) return '--';
    var d = new Date(dateStr);
    return d.toLocaleDateString('fr-FR', {
      year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit'
    });
  }

  /** HTML escape helper */
  function _escapeHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  // Public API
  return {
    navigate: navigate,
    toast: toast,
    apiFetch: apiFetch,
    confirm: confirm,
    copyToClipboard: copyToClipboard,
    formatDate: formatDate,
    getCurrentPage: function() { return currentPage; }
  };

})();
