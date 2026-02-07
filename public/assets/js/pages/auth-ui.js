/**
 * auth-ui.js — Client-side authentication, per-page enforcement, role-aware sidebar.
 *
 * Loads /api/v1/whoami.php and:
 *   1. Displays the access bar (login/logout)
 *   2. Checks the role required by the page (data-page-role on <html> or <body>)
 *   3. Hides [data-requires-role] elements that are not accessible
 *   4. Filters the sidebar by role
 *   5. Exposes window.Auth for page-specific scripts
 */
(function () {
  'use strict';

  // =========================================================================
  // GLOBALS
  // =========================================================================

  window.Auth = {
    role: null,
    user: null,
    enabled: null,
    meetingRoles: [],
    ready: null // Promise resolved when boot completes
  };

  var _resolveReady;
  window.Auth.ready = new Promise(function (resolve) { _resolveReady = resolve; });

  // =========================================================================
  // ROLE HIERARCHY (system roles)
  // =========================================================================

  var SYSTEM_ROLE_LEVEL = { admin: 100, operator: 80, auditor: 50, viewer: 10 };
  var MEETING_ROLES = ['president', 'assessor', 'voter'];

  /**
   * Check if currentRole satisfies requiredRole.
   * Supports system roles (hierarchy) and meeting roles (exact match via meetingRoles array).
   */
  function hasAccess(required, currentSystemRole, meetingRoles) {
    if (!required) return true;

    // admin has access to everything
    if (currentSystemRole === 'admin') return true;

    // Multiple roles separated by comma: user needs at least one
    var parts = required.split(',').map(function (r) { return r.trim(); });
    for (var i = 0; i < parts.length; i++) {
      if (checkSingleRole(parts[i], currentSystemRole, meetingRoles)) return true;
    }
    return false;
  }

  function checkSingleRole(required, currentSystemRole, meetingRoles) {
    // System role check with hierarchy
    if (SYSTEM_ROLE_LEVEL[required] !== undefined) {
      var currentLevel = SYSTEM_ROLE_LEVEL[currentSystemRole] || 0;
      return currentLevel >= SYSTEM_ROLE_LEVEL[required];
    }

    // Meeting role check (exact match)
    if (MEETING_ROLES.indexOf(required) !== -1) {
      if (!meetingRoles || !meetingRoles.length) return false;
      for (var j = 0; j < meetingRoles.length; j++) {
        if (meetingRoles[j].role === required) return true;
      }
      return false;
    }

    // Unknown role
    return required === currentSystemRole;
  }

  // =========================================================================
  // ROLE LABELS (UI display)
  // =========================================================================

  var ROLE_LABELS = {
    admin: 'Administrateur',
    operator: 'Opérateur',
    auditor: 'Auditeur',
    viewer: 'Observateur',
    president: 'Président',
    assessor: 'Assesseur',
    voter: 'Électeur'
  };

  // =========================================================================
  // AUTH BANNER
  // =========================================================================

  function ensureBanner() {
    var b = document.getElementById('auth-banner');
    if (b) return b;

    b = document.createElement('div');
    b.id = 'auth-banner';
    b.style.cssText =
      'position:sticky;top:0;z-index:20;background:var(--color-surface,#fff);' +
      'border-bottom:1px solid var(--color-border,#eee);padding:8px 16px;' +
      'display:flex;align-items:center;justify-content:space-between;gap:12px;' +
      'flex-wrap:wrap;font-size:13px;';

    b.innerHTML =
      '<div>' +
      '  <strong>Accès</strong> ' +
      '  <span class="text-muted" id="auth-status" style="font-size:12px;">...</span>' +
      '</div>' +
      '<div style="display:flex;gap:8px;align-items:center;">' +
      '  <span id="auth-roles-badge" style="font-size:11px;display:none;"></span>' +
      '  <button class="btn btn-sm" id="auth-login-btn" style="font-size:12px;">Se connecter</button>' +
      '  <button class="btn btn-sm" id="auth-logout-btn" style="display:none;font-size:12px;">Déconnexion</button>' +
      '</div>';

    document.body.prepend(b);

    b.querySelector('#auth-login-btn').addEventListener('click', function () {
      window.location.href = '/login.html?redirect=' +
        encodeURIComponent(window.location.pathname + window.location.search);
    });

    b.querySelector('#auth-logout-btn').addEventListener('click', async function () {
      try {
        // Fetch CSRF token first
        var csrfResp = await fetch('/api/v1/auth_csrf.php', { credentials: 'same-origin' });
        var csrfData = await csrfResp.json();
        var csrfToken = csrfData.data ? csrfData.data.token : (csrfData.token || '');

        await fetch('/api/v1/auth_logout.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          }
        });
      } catch (e) { /* best effort */ }
      window.Auth.user = null;
      window.Auth.role = null;
      window.Auth.meetingRoles = [];
      window.location.href = '/login.html';
    });

    return b;
  }

  function setStatus(text, type, isLoggedIn) {
    var b = ensureBanner();
    var s = b.querySelector('#auth-status');
    s.textContent = '\u2014 ' + text;
    b.style.borderBottomColor = (type === 'danger') ? '#f3b3b3' : 'var(--color-border,#eee)';
    b.querySelector('#auth-login-btn').style.display = isLoggedIn ? 'none' : '';
    b.querySelector('#auth-logout-btn').style.display = isLoggedIn ? '' : 'none';
  }

  function showMeetingRolesBadge(meetingRoles) {
    var badge = document.getElementById('auth-roles-badge');
    if (!badge || !meetingRoles || !meetingRoles.length) return;

    var labels = meetingRoles.map(function (mr) {
      return (ROLE_LABELS[mr.role] || mr.role);
    });
    // Deduplicate
    var unique = [];
    for (var i = 0; i < labels.length; i++) {
      if (unique.indexOf(labels[i]) === -1) unique.push(labels[i]);
    }
    if (unique.length) {
      badge.textContent = 'Séance : ' + unique.join(', ');
      badge.style.display = '';
      badge.style.cssText += 'background:var(--color-primary-subtle,#e8eadf);padding:2px 8px;border-radius:4px;';
    }
  }

  // =========================================================================
  // VISIBILITY ENFORCEMENT
  // =========================================================================

  function applyVisibility() {
    var role = window.Auth.role;
    var meetingRoles = window.Auth.meetingRoles || [];

    document.querySelectorAll('[data-requires-role]').forEach(function (el) {
      var req = el.getAttribute('data-requires-role');
      el.style.display = hasAccess(req, role, meetingRoles) ? '' : 'none';
    });
  }

  // =========================================================================
  // PAGE-LEVEL ROLE ENFORCEMENT
  // =========================================================================

  /**
   * Read data-page-role from <html> or <body> and enforce.
   * If user lacks access, replace page content with access denied message.
   */
  function enforcePageRole() {
    var pageRole = document.documentElement.getAttribute('data-page-role') ||
                   document.body.getAttribute('data-page-role');
    if (!pageRole) return; // No restriction on this page

    var role = window.Auth.role;
    var meetingRoles = window.Auth.meetingRoles || [];

    // Auth disabled (dev mode) - allow everything
    if (!window.Auth.enabled) return;

    // Not logged in - redirect to login
    if (!window.Auth.user) {
      window.location.href = '/login.html?redirect=' +
        encodeURIComponent(window.location.pathname + window.location.search);
      return;
    }

    // Check access
    if (hasAccess(pageRole, role, meetingRoles)) return; // OK

    // Access denied - replace main content
    var main = document.querySelector('.app-main') || document.querySelector('main') || document.body;
    var roleLabel = ROLE_LABELS[role] || role;
    var requiredLabels = pageRole.split(',').map(function (r) {
      return ROLE_LABELS[r.trim()] || r.trim();
    }).join(' ou ');

    main.innerHTML =
      '<div style="max-width:480px;margin:80px auto;text-align:center;padding:var(--space-8);">' +
      '  <div style="margin-bottom:16px;"><svg class="icon" style="width:64px;height:64px;color:var(--color-danger);"><use href="/assets/icons.svg#icon-x-circle"></use></svg></div>' +
      '  <h2 style="margin-bottom:8px;">Accès refusé</h2>' +
      '  <p style="color:var(--color-text-secondary,#666);margin-bottom:24px;">' +
      '    Cette page nécessite le rôle <strong>' + requiredLabels + '</strong>.<br>' +
      '    Votre rôle actuel : <strong>' + roleLabel + '</strong>.' +
      '  </p>' +
      '  <div style="display:flex;gap:8px;justify-content:center;">' +
      '    <a href="/" class="btn btn-secondary">Retour à l\'accueil</a>' +
      '    <a href="/login.html" class="btn btn-primary">Changer de compte</a>' +
      '  </div>' +
      '</div>';
  }

  // =========================================================================
  // SIDEBAR ROLE FILTERING
  // =========================================================================

  function filterSidebar() {
    var sidebar = document.querySelector('[data-include-sidebar]');
    if (!sidebar) return;

    var role = window.Auth.role;
    var meetingRoles = window.Auth.meetingRoles || [];

    sidebar.querySelectorAll('[data-requires-role]').forEach(function (el) {
      el.style.display = hasAccess(el.getAttribute('data-requires-role'), role, meetingRoles) ? '' : 'none';
    });
  }

  // =========================================================================
  // BOOT
  // =========================================================================

  async function boot() {
    try {
      var resp = await fetch('/api/v1/whoami.php', { credentials: 'same-origin' });
      var data = await resp.json();

      var user = (data.data && data.data.user) ? data.data.user : (data.user || null);
      var authEnabled = data.auth_enabled !== undefined ? data.auth_enabled
        : (data.data ? data.data.auth_enabled : false);
      var meetingRoles = (data.data && data.data.meeting_roles) ? data.data.meeting_roles : [];

      window.Auth.enabled = !!authEnabled;
      window.Auth.user = user;
      window.Auth.role = user ? user.role : null;
      window.Auth.meetingRoles = meetingRoles;

      if (!authEnabled) {
        setStatus('auth désactivée (dev)', 'ok', false);
      } else if (!user) {
        setStatus('Non connecté', 'danger', false);
      } else {
        var label = (user.name || user.email || 'utilisateur');
        var roleLabel = ROLE_LABELS[user.role] || user.role;
        setStatus(label + ' (' + roleLabel + ')', 'ok', true);
        showMeetingRolesBadge(meetingRoles);
      }

      applyVisibility();
      enforcePageRole();

      // Filter sidebar after a short delay (sidebar loads async)
      setTimeout(filterSidebar, 300);

    } catch (e) {
      window.Auth.enabled = true;
      window.Auth.user = null;
      window.Auth.role = null;
      window.Auth.meetingRoles = [];
      setStatus('Non connecté', 'danger', false);
      applyVisibility();
      enforcePageRole();
    }

    _resolveReady();
  }

  // =========================================================================
  // EXPORTS
  // =========================================================================

  window.Auth.hasAccess = hasAccess;
  window.Auth.ROLE_LABELS = ROLE_LABELS;

  ensureBanner();
  boot();
})();
