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
    b.className = 'auth-banner';
    b.setAttribute('role', 'banner');
    b.setAttribute('aria-label', 'Barre d\'identification');

    b.innerHTML =
      '<div class="auth-banner-left">' +
      '  <span class="auth-banner-avatar" id="auth-avatar" aria-hidden="true">?</span>' +
      '  <div class="auth-banner-info">' +
      '    <span class="auth-banner-name" id="auth-user-name">...</span>' +
      '    <span class="auth-banner-role" id="auth-user-role"></span>' +
      '  </div>' +
      '  <span id="auth-roles-badge" class="auth-banner-meeting-badge" style="display:none;"></span>' +
      '</div>' +
      '<div class="auth-banner-right">' +
      '  <a href="/" class="btn btn-ghost btn-sm" id="auth-home-btn" aria-label="Retour \u00e0 l\'accueil">' +
      '    <svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-home"></use></svg>' +
      '    <span class="auth-btn-label">Accueil</span>' +
      '  </a>' +
      '  <button class="btn btn-ghost btn-sm" id="auth-login-btn">Se connecter</button>' +
      '  <button class="btn btn-ghost btn-sm auth-logout-btn" id="auth-logout-btn" style="display:none;">' +
      '    <svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-log-out"></use></svg>' +
      '    <span class="auth-btn-label">D\u00e9connexion</span>' +
      '  </button>' +
      '</div>';

    document.body.prepend(b);

    b.querySelector('#auth-login-btn').addEventListener('click', function () {
      window.location.href = '/login.html?redirect=' +
        encodeURIComponent(window.location.pathname + window.location.search);
    });

    b.querySelector('#auth-logout-btn').addEventListener('click', async function () {
      try {
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
      if (typeof MeetingContext !== 'undefined') {
        try { MeetingContext.clear(); } catch (e) {}
      }
      window.location.href = '/';
    });

    return b;
  }

  function setStatus(text, type, isLoggedIn) {
    var b = ensureBanner();
    isLoggedIn ? Shared.hide(b.querySelector('#auth-login-btn')) : Shared.show(b.querySelector('#auth-login-btn'));
    isLoggedIn ? Shared.show(b.querySelector('#auth-logout-btn')) : Shared.hide(b.querySelector('#auth-logout-btn'));
    isLoggedIn ? Shared.show(b.querySelector('#auth-home-btn')) : Shared.hide(b.querySelector('#auth-home-btn'));

    if (type === 'danger') {
      b.classList.add('auth-banner--disconnected');
    } else {
      b.classList.remove('auth-banner--disconnected');
    }
  }

  /**
   * Populate the banner with user identity (avatar, name, role).
   */
  function setUserIdentity(user, systemRole, meetingRoles) {
    var nameEl = document.getElementById('auth-user-name');
    var roleEl = document.getElementById('auth-user-role');
    var avatarEl = document.getElementById('auth-avatar');

    if (!user) {
      if (nameEl) nameEl.textContent = 'Non connect\u00e9';
      if (roleEl) roleEl.textContent = '';
      if (avatarEl) avatarEl.textContent = '?';
      return;
    }

    var displayName = user.name || user.email || 'Utilisateur';
    if (nameEl) nameEl.textContent = displayName;

    var roleLabel = ROLE_LABELS[systemRole] || systemRole || '';
    if (roleEl) roleEl.textContent = roleLabel;

    // Avatar initials
    if (avatarEl) {
      var initials = displayName.split(' ')
        .map(function (w) { return w.charAt(0); })
        .join('')
        .substring(0, 2)
        .toUpperCase();
      avatarEl.textContent = initials || '?';
    }
  }

  function showMeetingRolesBadge(meetingRoles) {
    var badge = document.getElementById('auth-roles-badge');
    if (!badge || !meetingRoles || !meetingRoles.length) return;

    var labels = meetingRoles.map(function (mr) {
      return (ROLE_LABELS[mr.role] || mr.role);
    });
    var unique = [];
    for (var i = 0; i < labels.length; i++) {
      if (unique.indexOf(labels[i]) === -1) unique.push(labels[i]);
    }
    if (unique.length) {
      badge.textContent = unique.join(', ');
      Shared.show(badge);
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
      hasAccess(req, role, meetingRoles) ? Shared.show(el) : Shared.hide(el);
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
    if (!pageRole) {
      // Even without page role, enforce voter confinement
      enforceVoterConfinement(window.Auth.role, window.Auth.meetingRoles);
      return;
    }

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
    if (hasAccess(pageRole, role, meetingRoles)) {
      // Voter confinement: voters can only access vote + public pages
      enforceVoterConfinement(role, meetingRoles);
      return; // OK
    }

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
  // VOTER CONFINEMENT
  // =========================================================================

  /**
   * Voters (meeting role only, no elevated system role) are confined to
   * /vote.htmx.html and /public.htmx.html. Redirect elsewhere → vote page.
   */
  function enforceVoterConfinement(systemRole, meetingRoles) {
    if (!meetingRoles || !meetingRoles.length) return;

    // Only confine users whose highest system role is viewer (or lower)
    var level = SYSTEM_ROLE_LEVEL[systemRole] || 0;
    if (level > SYSTEM_ROLE_LEVEL.viewer) return; // operator/admin/auditor → no confinement

    // Check if user has voter meeting role
    var isVoter = meetingRoles.some(function(mr) { return mr.role === 'voter'; });
    if (!isVoter) return;

    // President and assessor are not confined
    var hasGovRole = meetingRoles.some(function(mr) {
      return mr.role === 'president' || mr.role === 'assessor';
    });
    if (hasGovRole) return;

    // Allowed pages for voters
    var path = window.location.pathname;
    var allowed = ['/vote.htmx.html', '/public.htmx.html'];
    for (var i = 0; i < allowed.length; i++) {
      if (path === allowed[i]) return;
    }

    // Redirect to vote page
    window.location.href = '/vote.htmx.html' + window.location.search;
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
      hasAccess(el.getAttribute('data-requires-role'), role, meetingRoles) ? Shared.show(el) : Shared.hide(el);
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

      var linkedMember = (data.data && data.data.member) ? data.data.member : null;

      window.Auth.enabled = !!authEnabled;
      window.Auth.user = user;
      window.Auth.role = user ? user.role : null;
      window.Auth.member = linkedMember;
      window.Auth.meetingRoles = meetingRoles;

      if (!authEnabled) {
        setStatus('auth d\u00e9sactiv\u00e9e (dev)', 'ok', false);
        setUserIdentity(null, null, []);
      } else if (!user) {
        setStatus('Non connect\u00e9', 'danger', false);
        setUserIdentity(null, null, []);
      } else {
        setStatus('', 'ok', true);
        setUserIdentity(user, user.role, meetingRoles);
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
