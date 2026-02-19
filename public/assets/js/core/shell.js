/**
 * shell.js - Application shell with drawer system and mobile navigation.
 *
 * Provides:
 * - Side drawer system with built-in kinds (context, readiness, infos, anomalies)
 * - Custom drawer registration for page-specific content
 * - Mobile hamburger navigation with slide-in sidebar
 * - Auto-loads auth-ui.js for login/logout banner
 *
 * @module shell
 * @requires MeetingContext (optional)
 * @requires api (global function)
 *
 * @example
 * // Open a built-in drawer
 * ShellDrawer.open('context');
 *
 * // Register a custom drawer
 * ShellDrawer.register('myDrawer', 'My Title', (meetingId, bodyEl, escFn) => {
 *   bodyEl.innerHTML = 'Content here';
 * });
 */
(function(){
  'use strict';

  const overlay = document.querySelector(".drawer-backdrop, [data-drawer-close]");
  const drawer = document.getElementById("drawer") || document.querySelector(".drawer");
  const dbody = document.getElementById("drawerBody");
  const titleEl = document.getElementById("drawerTitle");

  /** @type {Object.<string, {title: string, render: Function}>} */
  const customKinds = {};

  /**
   * Get the current meeting ID from MeetingContext or fallback sources.
   * @returns {string} Meeting ID or empty string
   */
  function getMeetingId(){
    // Use MeetingContext as single source of truth
    if (typeof MeetingContext !== 'undefined' && MeetingContext.get()) {
      return MeetingContext.get();
    }

    // Fallback chain for pages that load before MeetingContext
    const el = document.querySelector("[data-meeting-id]");
    if (el && el.getAttribute("data-meeting-id")) return el.getAttribute("data-meeting-id");

    const input = document.querySelector('input[name="meeting_id"]');
    if (input && input.value) return input.value;

    const params = new URLSearchParams(window.location.search);
    if (params.get("meeting_id")) return params.get("meeting_id");

    if (window.Utils && window.Utils.getMeetingId) return window.Utils.getMeetingId() || "";

    return "";
  }

  /**
   * Set the drawer title text.
   * @param {string} t - Title text
   */
  function setTitle(t){ if (titleEl) titleEl.textContent = t; }

  /**
   * Escape HTML entities in a string.
   * @param {string} s - String to escape
   * @returns {string} Escaped string
   */
  function esc(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  /**
   * Open a drawer by kind name.
   * @param {string} kind - Drawer type: 'menu', 'readiness', 'infos', 'anomalies', or custom registered kind
   */
  function openDrawer(kind){
    if (!drawer || !dbody) return;

    drawer.classList.add("open");
    drawer.setAttribute("aria-hidden", "false");
    if (overlay) {
      overlay.classList.add("open");
      overlay.hidden = false;
    }

    const meetingId = getMeetingId();

    // Built-in kinds
    if (kind === "context") { setTitle("Séance en cours"); renderContext(meetingId); }
    else if (kind === "readiness") { setTitle("Préparation"); renderReadiness(meetingId); }
    else if (kind === "infos" || kind === "info") { setTitle("Informations séance"); renderInfos(meetingId); }
    else if (kind === "anomalies") { setTitle("Anomalies"); renderAnomalies(meetingId); }
    // Page-registered kinds
    else if (customKinds[kind]) {
      const custom = customKinds[kind];
      setTitle(custom.title || kind);
      custom.render(meetingId, dbody, esc);
    }
    else { dbody.innerHTML = ""; }
  }

  /**
   * Render the contextual session drawer.
   * Combines meeting info, readiness checks and anomalies in a single view.
   * @param {string} meetingId - Current meeting ID
   * @returns {Promise<void>}
   */
  async function renderContext(meetingId) {
    if (!meetingId) {
      dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Sélectionnez une séance pour voir le tableau de bord.</div>';
      return;
    }
    dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Chargement…</div>';

    var sections = [];

    // --- Info séance ---
    try {
      var res = await window.api('/api/v1/meetings.php?id=' + meetingId);
      var b = res.body;
      if (b && b.ok && b.data) {
        var m = b.data;
        var statusClass = m.status === 'live' ? 'badge-success' : (m.status === 'draft' ? 'badge-neutral' : 'badge-warning');
        sections.push(
          '<div style="margin-bottom:16px;">' +
            '<div style="font-size:11px;text-transform:uppercase;color:var(--color-text-muted,#888);letter-spacing:.3px;margin-bottom:6px;font-weight:600;">Informations</div>' +
            '<div style="font-weight:600;margin-bottom:4px;">' + esc(m.title) + '</div>' +
            '<div style="display:flex;gap:10px;align-items:center;margin-bottom:4px;">' +
              '<span class="badge ' + statusClass + '">' + esc(m.status) + '</span>' +
              (m.location ? '<span class="text-sm text-muted">' + esc(m.location) + '</span>' : '') +
            '</div>' +
            (m.president_name ? '<div class="text-sm"><span class="text-muted">Président :</span> ' + esc(m.president_name) + '</div>' : '') +
          '</div>'
        );
      }
    } catch(e) { /* silently skip section */ }

    // --- Readiness checks ---
    try {
      var res2 = await window.api('/api/v1/meeting_ready_check.php?meeting_id=' + meetingId);
      var b2 = res2.body;
      if (b2 && b2.ok && b2.data) {
        var d = b2.data;
        var checks = d.checks || [];
        if (checks.length > 0) {
          sections.push(
            '<div style="margin-bottom:16px;">' +
              '<div style="font-size:11px;text-transform:uppercase;color:var(--color-text-muted,#888);letter-spacing:.3px;margin-bottom:6px;font-weight:600;">Check-list</div>' +
              '<div class="badge ' + (d.ready ? 'badge-success' : 'badge-warning') + '" style="margin-bottom:8px;">' +
                (d.ready ? 'Prêt' : 'Non prêt') +
              '</div>' +
              checks.map(function(c) {
                return '<div style="display:flex;align-items:center;gap:8px;padding:3px 0;">' +
                  '<span>' + (c.passed ? icon('check-circle', 'icon-sm icon-success') : icon('x-circle', 'icon-sm icon-danger')) + '</span>' +
                  '<span class="text-sm">' + esc(c.label || '') + '</span>' +
                '</div>';
              }).join('') +
            '</div>'
          );
        }
      }
    } catch(e) { /* silently skip section */ }

    // --- Anomalies ---
    try {
      var res3 = await window.api('/api/v1/operator_anomalies.php?meeting_id=' + meetingId);
      var b3 = res3.body;
      if (b3 && b3.ok && b3.data) {
        var items = b3.data.anomalies || b3.data.items || [];
        if (items.length > 0) {
          sections.push(
            '<div style="margin-bottom:16px;">' +
              '<div style="font-size:11px;text-transform:uppercase;color:var(--color-text-muted,#888);letter-spacing:.3px;margin-bottom:6px;font-weight:600;">Anomalies</div>' +
              items.map(function(a) {
                return '<div style="padding:8px 10px;border-radius:6px;background:var(--color-bg-warning,#fff3cd);border-left:3px solid var(--color-warning,#e6a800);margin-bottom:6px;">' +
                  '<div style="font-weight:600;font-size:12px;color:var(--color-danger,#a05252);">' + esc(a.code || a.severity || 'Anomalie') + '</div>' +
                  '<div class="text-sm">' + esc(a.message || a.detail || '') + '</div>' +
                '</div>';
              }).join('') +
            '</div>'
          );
        }
      }
    } catch(e) { /* silently skip section */ }

    if (sections.length === 0) {
      dbody.innerHTML = '<div style="padding:16px;text-align:center;" class="text-muted">Aucune information disponible.</div>';
    } else {
      dbody.innerHTML = '<div style="padding:4px 0;">' + sections.join('') + '</div>';
    }
  }

  /**
   * Render the readiness check drawer.
   * Shows meeting preparation status and checklist.
   * @param {string} meetingId - Current meeting ID
   * @returns {Promise<void>}
   */
  async function renderReadiness(meetingId) {
    if (!meetingId) { dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Sélectionnez une séance.</div>'; return; }
    dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Chargement…</div>';
    try {
      const res = await window.api('/api/v1/meeting_ready_check.php?meeting_id=' + meetingId);
      const b = res.body;
      if (b && b.ok && b.data) {
        const d = b.data;
        const checks = d.checks || [];
        dbody.innerHTML =
          '<div style="padding:4px 0;display:flex;flex-direction:column;gap:10px;">' +
            '<div class="badge ' + (d.ready ? 'badge-success' : 'badge-warning') + '" style="font-size:14px;padding:6px 12px;">' +
              (d.ready ? 'Prêt' : 'Non prêt') +
            '</div>' +
            checks.map(function(c) {
              return '<div style="display:flex;align-items:center;gap:8px;">' +
                '<span>' + (c.passed ? icon('check-circle', 'icon-sm icon-success') : icon('x-circle', 'icon-sm icon-danger')) + '</span>' +
                '<span>' + esc(c.label || '') + '</span>' +
              '</div>';
            }).join('') +
          '</div>';
      } else {
        dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Statut indisponible.</div>';
      }
    } catch(e) {
      dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Erreur de chargement.</div>';
    }
  }

  /**
   * Render the meeting information drawer.
   * Shows meeting details: title, status, location, president, dates.
   * @param {string} meetingId - Current meeting ID
   * @returns {Promise<void>}
   */
  async function renderInfos(meetingId) {
    if (!meetingId) { dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Sélectionnez une séance.</div>'; return; }
    dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Chargement…</div>';
    try {
      const res = await window.api('/api/v1/meetings.php?id=' + meetingId);
      const b = res.body;
      if (b && b.ok && b.data) {
        const m = b.data;
        const statusBadge = m.status === 'live' ? 'badge-success' : (m.status === 'draft' ? 'badge-neutral' : 'badge-warning');
        dbody.innerHTML =
          '<div style="display:flex;flex-direction:column;gap:12px;padding:4px 0;">' +
            '<div><strong>' + esc(m.title) + '</strong></div>' +
            '<div class="text-sm"><span class="text-muted">Statut :</span> <span class="badge ' + statusBadge + '">' + esc(m.status) + '</span></div>' +
            '<div class="text-sm"><span class="text-muted">Lieu :</span> ' + esc(m.location || '—') + '</div>' +
            '<div class="text-sm"><span class="text-muted">Président :</span> ' + esc(m.president_name || '—') + '</div>' +
            '<div class="text-sm"><span class="text-muted">Convocation :</span> ' + esc(m.convocation_no || '—') + '</div>' +
            '<div class="text-sm"><span class="text-muted">Prévu le :</span> ' + (m.scheduled_at ? new Date(m.scheduled_at).toLocaleString('fr-FR') : '—') + '</div>' +
            '<div class="text-sm"><span class="text-muted">Démarré le :</span> ' + (m.started_at ? new Date(m.started_at).toLocaleString('fr-FR') : '—') + '</div>' +
            (m.description ? '<div class="text-sm" style="margin-top:8px;">' + esc(m.description) + '</div>' : '') +
          '</div>';
      } else {
        dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Séance introuvable.</div>';
      }
    } catch(e) {
      dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Erreur de chargement.</div>';
    }
  }

  /**
   * Render the anomalies drawer.
   * Shows detected issues and warnings for the meeting.
   * @param {string} meetingId - Current meeting ID
   * @returns {Promise<void>}
   */
  async function renderAnomalies(meetingId) {
    if (!meetingId) { dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Sélectionnez une séance.</div>'; return; }
    dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Chargement…</div>';
    try {
      const res = await window.api('/api/v1/operator_anomalies.php?meeting_id=' + meetingId);
      const b = res.body;
      if (b && b.ok && b.data) {
        const items = b.data.anomalies || b.data.items || [];
        if (items.length === 0) {
          dbody.innerHTML = '<div style="padding:16px;text-align:center;" class="text-muted">Aucune anomalie détectée.</div>';
        } else {
          dbody.innerHTML =
            '<div style="display:flex;flex-direction:column;gap:8px;padding:4px 0;">' +
              items.map(function(a) {
                return '<div style="padding:8px 12px;border-radius:8px;background:var(--color-bg,#f5f5f5);">' +
                  '<div style="font-weight:600;color:var(--color-danger,#a05252);">' + esc(a.code || a.severity || 'Anomalie') + '</div>' +
                  '<div class="text-sm">' + esc(a.message || a.detail || '') + '</div>' +
                '</div>';
              }).join('') +
            '</div>';
        }
      } else {
        dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Aucune anomalie.</div>';
      }
    } catch(e) {
      dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Erreur de chargement.</div>';
    }
  }

  /**
   * Close the drawer panel.
   */
  function closeDrawer(){
    if (!drawer) return;
    drawer.classList.remove("open");
    drawer.setAttribute("aria-hidden", "true");
    if (overlay) {
      overlay.classList.remove("open");
      overlay.hidden = true;
    }
  }

  document.addEventListener("click", function(e) {
    const t = e.target;
    if (!t) return;

    const btn = t.closest && t.closest("[data-drawer]");
    if (btn){
      openDrawer(btn.getAttribute("data-drawer"));
      return;
    }
    if (t.matches && t.matches("[data-drawer-close]")){ closeDrawer(); return; }
    if (overlay && t === overlay){ closeDrawer(); return; }
  });

  document.addEventListener("keydown", function(e) {
    if (e.key === "Escape") closeDrawer();
  });

  /**
   * Register a page-specific drawer kind.
   * @param {string} kind - drawer kind name (used in data-drawer="kind")
   * @param {string} title - drawer title
   * @param {function(meetingId, bodyEl, escFn)} renderFn - render function
   */
  function registerKind(kind, title, renderFn) {
    customKinds[kind] = { title: title, render: renderFn };
  }

  window.ShellDrawer = { open: openDrawer, close: closeDrawer, register: registerKind };

  // ==========================================================================
  // Mobile Navigation
  // ==========================================================================

  const sidebar = document.querySelector('.app-sidebar');

  // Create mobile nav toggle button
  const mobileNavToggle = document.createElement('button');
  mobileNavToggle.className = 'mobile-nav-toggle';
  mobileNavToggle.setAttribute('aria-label', 'Ouvrir le menu de navigation');
  mobileNavToggle.setAttribute('aria-expanded', 'false');
  mobileNavToggle.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>';
  document.body.appendChild(mobileNavToggle);

  // Create mobile close button (added to sidebar when opened)
  function createMobileCloseBtn() {
    const closeBtn = document.createElement('button');
    closeBtn.className = 'mobile-close';
    closeBtn.setAttribute('aria-label', 'Fermer le menu');
    closeBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
    return closeBtn;
  }

  function openMobileNav() {
    if (!sidebar) return;
    sidebar.classList.add('mobile-open');
    mobileNavToggle.setAttribute('aria-expanded', 'true');
    mobileNavToggle.setAttribute('aria-label', 'Fermer le menu de navigation');

    // Add close button
    let closeBtn = sidebar.querySelector('.mobile-close');
    if (!closeBtn) {
      closeBtn = createMobileCloseBtn();
      sidebar.insertBefore(closeBtn, sidebar.firstChild);
      closeBtn.addEventListener('click', closeMobileNav);
    }

    // Focus trap - focus first focusable element
    const firstFocusable = sidebar.querySelector('a, button');
    if (firstFocusable) firstFocusable.focus();

    // Prevent body scroll
    document.body.style.overflow = 'hidden';
  }

  function closeMobileNav() {
    if (!sidebar) return;
    sidebar.classList.remove('mobile-open');
    mobileNavToggle.setAttribute('aria-expanded', 'false');
    mobileNavToggle.setAttribute('aria-label', 'Ouvrir le menu de navigation');

    // Restore body scroll
    document.body.style.overflow = '';

    // Return focus to toggle button
    mobileNavToggle.focus();
  }

  mobileNavToggle.addEventListener('click', function() {
    if (sidebar && sidebar.classList.contains('mobile-open')) {
      closeMobileNav();
    } else {
      openMobileNav();
    }
  });

  // Close mobile nav on Escape
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && sidebar && sidebar.classList.contains('mobile-open')) {
      closeMobileNav();
    }
  });

  // Close mobile nav when clicking a link
  if (sidebar) {
    sidebar.addEventListener('click', function(e) {
      if (e.target.matches('a') && sidebar.classList.contains('mobile-open')) {
        closeMobileNav();
      }
    });
  }

  // Close on resize if no longer mobile
  window.addEventListener('resize', function() {
    if (window.innerWidth > 768 && sidebar && sidebar.classList.contains('mobile-open')) {
      closeMobileNav();
    }
  });

  window.MobileNav = { open: openMobileNav, close: closeMobileNav };

  // ==========================================================================
  // Theme Toggle (dark/light mode)
  // ==========================================================================

  const THEME_KEY = 'ag-vote-theme';

  /**
   * Apply the given theme to the document.
   * @param {'light'|'dark'|'auto'} theme
   */
  function applyTheme(theme) {
    if (theme === 'dark') {
      document.documentElement.setAttribute('data-theme', 'dark');
    } else if (theme === 'light') {
      document.documentElement.setAttribute('data-theme', 'light');
    } else {
      // auto — remove attribute and let prefers-color-scheme rule
      document.documentElement.removeAttribute('data-theme');
    }
  }

  /**
   * Toggle between light and dark themes.
   */
  function toggleTheme() {
    const current = localStorage.getItem(THEME_KEY);
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark' ||
      (!document.documentElement.hasAttribute('data-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
    const next = isDark ? 'light' : 'dark';
    localStorage.setItem(THEME_KEY, next);
    applyTheme(next);
  }

  // Restore saved theme on load
  const savedTheme = localStorage.getItem(THEME_KEY);
  if (savedTheme) {
    applyTheme(savedTheme);
  }

  // Bind toggle button (may be loaded dynamically via sidebar partial)
  function bindThemeToggle() {
    const btn = document.getElementById('btnToggleTheme');
    if (btn && !btn.dataset.themeBound) {
      btn.dataset.themeBound = 'true';
      btn.addEventListener('click', toggleTheme);
    }
  }

  // Try binding immediately and also observe for sidebar load
  bindThemeToggle();
  const sidebarEl = document.querySelector('[data-include-sidebar]');
  if (sidebarEl) {
    new MutationObserver(function(_, obs) {
      bindThemeToggle();
      if (document.getElementById('btnToggleTheme')) obs.disconnect();
    }).observe(sidebarEl, { childList: true, subtree: true });
  }

  window.ThemeToggle = { toggle: toggleTheme, apply: applyTheme };

  // Auto-load auth UI banner (login/logout + role visibility)
  const authScript = document.createElement('script');
  authScript.src = '/assets/js/pages/auth-ui.js';
  document.head.appendChild(authScript);
})();
