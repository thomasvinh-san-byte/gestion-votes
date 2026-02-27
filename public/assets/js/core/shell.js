/**
 * shell.js - Application shell with sidebar pin/expand, drawer system, and mobile navigation.
 *
 * Provides:
 * - Sidebar rail/expand (58px→252px on hover, pin to persist)
 * - Sidebar scroll fade indicators
 * - Side drawer system with built-in kinds (context, readiness, infos, anomalies)
 * - Custom drawer registration for page-specific content
 * - Mobile hamburger navigation with slide-in sidebar + overlay
 * - Mobile bottom navigation bar
 * - Theme toggle (light/dark)
 * - Auto-loads auth-ui.js for login/logout banner
 *
 * @module shell
 */
(function(){
  'use strict';

  // ==========================================================================
  // Sidebar Pin & Scroll Fade
  // ==========================================================================

  const sidebar = document.querySelector('.app-sidebar');
  const PIN_KEY = 'ag-vote-sidebar-pinned';

  /**
   * Toggle sidebar pinned state.
   */
  function togglePin() {
    if (!sidebar) return;
    sidebar.classList.toggle('pinned');
    const pinned = sidebar.classList.contains('pinned');
    localStorage.setItem(PIN_KEY, pinned ? '1' : '0');
    // Update main content padding when pinned
    const main = document.querySelector('.app-main');
    if (main) {
      main.style.paddingLeft = pinned
        ? 'calc(var(--sidebar-expanded) + 22px)'
        : '';
    }
  }

  // Restore pin state from localStorage
  if (sidebar && localStorage.getItem(PIN_KEY) === '1') {
    sidebar.classList.add('pinned');
    const main = document.querySelector('.app-main');
    if (main) {
      main.style.paddingLeft = 'calc(var(--sidebar-expanded) + 22px)';
    }
  }

  // Bind pin button (may be loaded dynamically via sidebar partial)
  function bindPinButton() {
    const btn = document.getElementById('sidebarPin');
    if (btn && !btn.dataset.pinBound) {
      btn.dataset.pinBound = 'true';
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        togglePin();
      });
    }
  }
  bindPinButton();

  /**
   * Update scroll fade indicators on sidebar-fade container.
   */
  function updateScrollFade() {
    const fade = document.getElementById('sidebarFade');
    const scroll = document.getElementById('sidebarScroll');
    if (!fade || !scroll) return;

    const top = scroll.scrollTop > 4;
    const bottom = scroll.scrollTop + scroll.clientHeight < scroll.scrollHeight - 4;
    fade.classList.toggle('has-scroll-top', top);
    fade.classList.toggle('has-scroll-bottom', bottom);
  }

  function bindScrollFade() {
    const scroll = document.getElementById('sidebarScroll');
    if (scroll && !scroll.dataset.fadeBound) {
      scroll.dataset.fadeBound = 'true';
      scroll.addEventListener('scroll', updateScrollFade, { passive: true });
      // Initial check after a short delay (content may not be rendered yet)
      setTimeout(updateScrollFade, 100);
    }
  }
  bindScrollFade();

  // Observe sidebar for dynamic partial load
  const sidebarEl = document.querySelector('[data-include-sidebar]') || sidebar;
  if (sidebarEl) {
    new MutationObserver(function() {
      bindPinButton();
      bindScrollFade();
      bindThemeToggle();
      // Mark active page
      markActivePage();
    }).observe(sidebarEl, { childList: true, subtree: true });
  }

  /**
   * Mark the active nav item based on data-page attribute.
   */
  function markActivePage() {
    const page = (sidebarEl || sidebar)?.getAttribute('data-page');
    if (!page) return;
    const container = sidebarEl || sidebar;
    if (!container) return;
    container.querySelectorAll('.nav-item[data-page]').forEach(function(item) {
      item.classList.toggle('active', item.getAttribute('data-page') === page);
    });
  }

  window.SidebarPin = { toggle: togglePin };

  // ==========================================================================
  // Drawer System
  // ==========================================================================

  const overlay = document.querySelector(".drawer-backdrop, [data-drawer-close]");
  const drawer = document.getElementById("drawer") || document.querySelector(".drawer");
  const dbody = document.getElementById("drawerBody");
  const titleEl = document.getElementById("drawerTitle");

  /** @type {Object.<string, {title: string, render: Function}>} */
  const customKinds = {};

  function getMeetingId(){
    if (typeof MeetingContext !== 'undefined' && MeetingContext.get()) {
      return MeetingContext.get();
    }
    const el = document.querySelector("[data-meeting-id]");
    if (el && el.getAttribute("data-meeting-id")) return el.getAttribute("data-meeting-id");
    const input = document.querySelector('input[name="meeting_id"]');
    if (input && input.value) return input.value;
    const params = new URLSearchParams(window.location.search);
    if (params.get("meeting_id")) return params.get("meeting_id");
    return "";
  }

  function setTitle(t){ if (titleEl) titleEl.textContent = t; }
  function esc(s) { return typeof Utils !== 'undefined' ? Utils.escapeHtml(s) : String(s).replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; }); }

  function openDrawer(kind){
    if (!drawer || !dbody) return;
    drawer.classList.add("open");
    drawer.setAttribute("aria-hidden", "false");
    if (overlay) {
      overlay.classList.add("open");
      overlay.hidden = false;
    }
    const meetingId = getMeetingId();
    if (kind === "context") { setTitle("Séance en cours"); renderContext(meetingId); }
    else if (kind === "readiness") { setTitle("Préparation"); renderReadiness(meetingId); }
    else if (kind === "infos" || kind === "info") { setTitle("Informations séance"); renderInfos(meetingId); }
    else if (kind === "anomalies") { setTitle("Anomalies"); renderAnomalies(meetingId); }
    else if (customKinds[kind]) {
      const custom = customKinds[kind];
      setTitle(custom.title || kind);
      custom.render(meetingId, dbody, esc);
    }
    else { dbody.innerHTML = ""; }
  }

  async function renderContext(meetingId) {
    if (!meetingId) { dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Sélectionnez une séance pour voir le tableau de bord.</div>'; return; }
    dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Chargement…</div>';
    var sections = [];
    var failedSections = [];
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
    } catch(e) { failedSections.push('infos séance'); }
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
              '<div class="badge ' + (d.ready ? 'badge-success' : 'badge-warning') + '" style="margin-bottom:8px;">' + (d.ready ? 'Prêt' : 'Non prêt') + '</div>' +
              checks.map(function(c) {
                return '<div style="display:flex;align-items:center;gap:8px;padding:3px 0;"><span>' + (c.passed ? '✓' : '✗') + '</span><span class="text-sm">' + esc(c.label || '') + '</span></div>';
              }).join('') +
            '</div>'
          );
        }
      }
    } catch(e) { failedSections.push('check-list'); }
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
                return '<div style="padding:8px 10px;border-radius:6px;background:var(--color-bg-warning,#fff3cd);border-left:3px solid var(--color-warning,#e6a800);margin-bottom:6px;"><div style="font-weight:600;font-size:12px;color:var(--color-danger,#a05252);">' + esc(a.code || a.severity || 'Anomalie') + '</div><div class="text-sm">' + esc(a.message || a.detail || '') + '</div></div>';
              }).join('') +
            '</div>'
          );
        }
      }
    } catch(e) { failedSections.push('anomalies'); }
    if (failedSections.length) {
      sections.push('<div style="padding:8px 12px;font-size:11px;color:var(--color-text-muted,#999);border-top:1px solid var(--color-border,#eee);margin-top:8px;">Chargement partiel — sections indisponibles : ' + esc(failedSections.join(', ')) + '</div>');
    }
    if (sections.length === 0) {
      dbody.innerHTML = '<div style="padding:16px;text-align:center;" class="text-muted">Aucune information disponible.</div>';
    } else {
      dbody.innerHTML = '<div style="padding:4px 0;">' + sections.join('') + '</div>';
    }
  }

  async function renderReadiness(meetingId) {
    if (!meetingId) { dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Sélectionnez une séance.</div>'; return; }
    dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Chargement…</div>';
    try {
      const res = await window.api('/api/v1/meeting_ready_check.php?meeting_id=' + meetingId);
      const b = res.body;
      if (b && b.ok && b.data) {
        const d = b.data;
        const checks = d.checks || [];
        dbody.innerHTML = '<div style="padding:4px 0;display:flex;flex-direction:column;gap:10px;"><div class="badge ' + (d.ready ? 'badge-success' : 'badge-warning') + '" style="font-size:14px;padding:6px 12px;">' + (d.ready ? 'Prêt' : 'Non prêt') + '</div>' + checks.map(function(c) { return '<div style="display:flex;align-items:center;gap:8px;"><span>' + (c.passed ? '✓' : '✗') + '</span><span>' + esc(c.label || '') + '</span></div>'; }).join('') + '</div>';
      } else { dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Statut indisponible.</div>'; }
    } catch(e) { dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Erreur de chargement.</div>'; }
  }

  async function renderInfos(meetingId) {
    if (!meetingId) { dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Sélectionnez une séance.</div>'; return; }
    dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Chargement…</div>';
    try {
      const res = await window.api('/api/v1/meetings.php?id=' + meetingId);
      const b = res.body;
      if (b && b.ok && b.data) {
        const m = b.data;
        const statusBadge = m.status === 'live' ? 'badge-success' : (m.status === 'draft' ? 'badge-neutral' : 'badge-warning');
        dbody.innerHTML = '<div style="display:flex;flex-direction:column;gap:12px;padding:4px 0;"><div><strong>' + esc(m.title) + '</strong></div><div class="text-sm"><span class="text-muted">Statut :</span> <span class="badge ' + statusBadge + '">' + esc(m.status) + '</span></div><div class="text-sm"><span class="text-muted">Lieu :</span> ' + esc(m.location || '—') + '</div><div class="text-sm"><span class="text-muted">Président :</span> ' + esc(m.president_name || '—') + '</div></div>';
      } else { dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Séance introuvable.</div>'; }
    } catch(e) { dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Erreur de chargement.</div>'; }
  }

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
          dbody.innerHTML = '<div style="display:flex;flex-direction:column;gap:8px;padding:4px 0;">' + items.map(function(a) { return '<div style="padding:8px 12px;border-radius:8px;background:var(--color-bg,#f5f5f5);"><div style="font-weight:600;color:var(--color-danger,#a05252);">' + esc(a.code || a.severity || 'Anomalie') + '</div><div class="text-sm">' + esc(a.message || a.detail || '') + '</div></div>'; }).join('') + '</div>';
        }
      } else { dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Aucune anomalie.</div>'; }
    } catch(e) { dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Erreur de chargement.</div>'; }
  }

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
    if (btn){ openDrawer(btn.getAttribute("data-drawer")); return; }
    if (t.matches && t.matches("[data-drawer-close]")){ closeDrawer(); return; }
    if (overlay && t === overlay){ closeDrawer(); return; }
  });

  document.addEventListener("keydown", function(e) {
    if (e.key === "Escape") closeDrawer();
  });

  function registerKind(kind, title, renderFn) {
    customKinds[kind] = { title: title, render: renderFn };
  }

  window.ShellDrawer = { open: openDrawer, close: closeDrawer, register: registerKind };

  // ==========================================================================
  // Mobile Navigation — Hamburger + Sidebar Drawer + Overlay + Bottom Nav
  // ==========================================================================

  // Inject hamburger into header if not already present
  const header = document.querySelector('.app-header');
  if (header && !header.querySelector('.hamburger')) {
    const hb = document.createElement('button');
    hb.className = 'hamburger';
    hb.setAttribute('aria-label', 'Menu');
    hb.setAttribute('aria-expanded', 'false');
    hb.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>';
    header.insertBefore(hb, header.firstChild);
  }

  // Inject mobile bottom nav if not already present
  const shell = document.querySelector('.app-shell');
  if (shell && !shell.querySelector('.mobile-bnav')) {
    const bnav = document.createElement('nav');
    bnav.className = 'mobile-bnav';
    bnav.setAttribute('aria-label', 'Navigation rapide');
    const currentPage = (sidebar && sidebar.getAttribute('data-page')) || '';
    const items = [
      { href: '/meetings.htmx.html', page: 'meetings', icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>', label: 'Accueil' },
      { href: '/operator.htmx.html', page: 'operator', icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>', label: 'Fiche' },
      { href: '/vote.htmx.html', page: 'vote', icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>', label: 'Voter' },
      { href: '/admin.htmx.html', page: 'admin', icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>', label: 'Config' }
    ];
    bnav.innerHTML = items.map(function(it) {
      return '<a class="mobile-bnav-btn' + (currentPage === it.page ? ' act' : '') + '" href="' + it.href + '">' + it.icon + '<span>' + it.label + '</span></a>';
    }).join('');
    shell.appendChild(bnav);
  }

  const hamburger = document.querySelector('.hamburger');

  function openMobileNav() {
    if (!sidebar) return;
    sidebar.classList.add('open');
    // Show overlay
    var overlayEl = document.querySelector('.sidebar-overlay');
    if (!overlayEl) {
      overlayEl = document.createElement('div');
      overlayEl.className = 'sidebar-overlay';
      sidebar.parentNode.insertBefore(overlayEl, sidebar);
    }
    overlayEl.style.display = 'block';
    overlayEl.addEventListener('click', closeMobileNav);
    // Update hamburger
    if (hamburger) hamburger.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  }

  function closeMobileNav() {
    if (!sidebar) return;
    sidebar.classList.remove('open');
    var overlayEl = document.querySelector('.sidebar-overlay');
    if (overlayEl) overlayEl.style.display = 'none';
    if (hamburger) hamburger.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  }

  if (hamburger) {
    hamburger.addEventListener('click', function() {
      if (sidebar && sidebar.classList.contains('open')) {
        closeMobileNav();
      } else {
        openMobileNav();
      }
    });
  }

  // Close mobile nav on Escape
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
      closeMobileNav();
    }
  });

  // Close mobile nav when clicking a link
  if (sidebar) {
    sidebar.addEventListener('click', function(e) {
      if (e.target.closest('a') && sidebar.classList.contains('open')) {
        closeMobileNav();
      }
    });
  }

  // Close on resize if no longer mobile
  window.addEventListener('resize', function() {
    if (window.innerWidth > 768 && sidebar && sidebar.classList.contains('open')) {
      closeMobileNav();
    }
  });

  window.MobileNav = { open: openMobileNav, close: closeMobileNav };

  // ==========================================================================
  // Theme Toggle (dark/light mode)
  // ==========================================================================

  const THEME_KEY = 'ag-vote-theme';

  function applyTheme(theme) {
    if (theme === 'dark') {
      document.documentElement.setAttribute('data-theme', 'dark');
    } else if (theme === 'light') {
      document.documentElement.setAttribute('data-theme', 'light');
    } else {
      document.documentElement.removeAttribute('data-theme');
    }
  }

  function toggleTheme() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark' ||
      (!document.documentElement.hasAttribute('data-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
    const next = isDark ? 'light' : 'dark';
    localStorage.setItem(THEME_KEY, next);
    applyTheme(next);
  }

  const savedTheme = localStorage.getItem(THEME_KEY);
  if (savedTheme) {
    applyTheme(savedTheme);
  }

  function bindThemeToggle() {
    const btn = document.getElementById('btnToggleTheme');
    if (btn && !btn.dataset.themeBound) {
      btn.dataset.themeBound = 'true';
      btn.addEventListener('click', toggleTheme);
    }
  }

  bindThemeToggle();

  window.ThemeToggle = { toggle: toggleTheme, apply: applyTheme };

  // ==========================================================================
  // Scroll-to-top button
  // ==========================================================================

  const mainEl = document.querySelector('.app-main');
  if (mainEl) {
    const scrollTopBtn = document.createElement('button');
    scrollTopBtn.className = 'scroll-top';
    scrollTopBtn.setAttribute('aria-label', 'Remonter en haut');
    scrollTopBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>';
    document.body.appendChild(scrollTopBtn);

    mainEl.addEventListener('scroll', function() {
      scrollTopBtn.classList.toggle('visible', mainEl.scrollTop > 300);
    }, { passive: true });

    scrollTopBtn.addEventListener('click', function() {
      mainEl.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // ==========================================================================
  // Notifications Dropdown
  // ==========================================================================

  const NOTIF_POLL_INTERVAL = 60000; // 60s
  let notifPanel = null;
  let notifCount = 0;

  function createNotifBell() {
    const header = document.querySelector('.app-header');
    if (!header || header.querySelector('.notif-bell')) return;

    const bell = document.createElement('button');
    bell.className = 'notif-bell';
    bell.setAttribute('aria-label', 'Notifications');
    bell.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><span class="notif-count" style="display:none">0</span>';

    // Insert before last child or at end
    const ctx = header.querySelector('.header-ctx');
    if (ctx) {
      header.insertBefore(bell, ctx);
    } else {
      header.appendChild(bell);
    }

    // Create panel
    notifPanel = document.createElement('div');
    notifPanel.className = 'notif-panel';
    notifPanel.style.display = 'none';
    notifPanel.innerHTML = '<div style="padding:12px 16px;font-size:13px;font-weight:700;border-bottom:1px solid var(--color-border-subtle,#e8e7e2);">Notifications</div><div class="notif-list"></div>';
    bell.style.position = 'relative';
    bell.appendChild(notifPanel);

    bell.addEventListener('click', function(e) {
      e.stopPropagation();
      const isOpen = notifPanel.style.display !== 'none';
      notifPanel.style.display = isOpen ? 'none' : 'block';
      if (!isOpen) markNotificationsRead();
    });

    document.addEventListener('click', function() {
      if (notifPanel) notifPanel.style.display = 'none';
    });

    // Initial fetch
    fetchNotifications();
    setInterval(fetchNotifications, NOTIF_POLL_INTERVAL);
  }

  async function fetchNotifications() {
    try {
      if (!window.api) return;
      const res = await window.api('/api/v1/notifications.php');
      const b = res.body;
      if (b && b.ok && b.data) {
        renderNotifications(b.data);
      }
    } catch(e) { /* silent */ }
  }

  function renderNotifications(data) {
    const items = Array.isArray(data) ? data : (data.items || []);
    const unread = items.filter(function(n) { return !n.read; }).length;
    notifCount = unread;

    const countEl = document.querySelector('.notif-count');
    if (countEl) {
      countEl.textContent = String(unread);
      countEl.style.display = unread > 0 ? 'flex' : 'none';
    }

    const list = notifPanel && notifPanel.querySelector('.notif-list');
    if (!list) return;

    if (items.length === 0) {
      list.innerHTML = '<div style="padding:20px;text-align:center;font-size:13px;color:var(--color-text-muted,#95a3a4);">Aucune notification</div>';
      return;
    }

    list.innerHTML = items.slice(0, 10).map(function(n) {
      const dotColor = n.read ? 'var(--color-border,#d5dbd2)' : 'var(--color-primary,#1650E0)';
      return '<div class="notif-item">' +
        '<span class="notif-dot" style="background:' + dotColor + '"></span>' +
        '<div class="notif-body">' +
          '<div class="notif-msg">' + esc(n.message || n.title || '') + '</div>' +
          '<div class="notif-time">' + esc(n.time || n.created_at || '') + '</div>' +
        '</div></div>';
    }).join('');
  }

  async function markNotificationsRead() {
    try {
      if (!window.api || notifCount === 0) return;
      await window.api('/api/v1/notifications_read.php', { method: 'PUT' });
    } catch(e) { /* silent */ }
  }

  createNotifBell();

  window.Notifications = { fetch: fetchNotifications };

  // ==========================================================================
  // Global Search — Ctrl+K / Cmd+K
  // ==========================================================================

  const SEARCH_IDX = [
    { name: 'Séances', sub: 'Gestion des assemblées', href: '/meetings.htmx.html', icon: 'clipboard-list' },
    { name: 'Membres', sub: 'Annuaire des copropriétaires', href: '/members.htmx.html', icon: 'users' },
    { name: 'Fiche séance', sub: 'Préparer et piloter', href: '/operator.htmx.html', icon: 'file-text' },
    { name: 'Voter', sub: 'Participer au vote', href: '/vote.htmx.html', icon: 'vote' },
    { name: 'Projection', sub: 'Écran salle', href: '/public.htmx.html', icon: 'monitor' },
    { name: 'Clôture & PV', sub: 'Valider et archiver', href: '/postsession.htmx.html', icon: 'check-square' },
    { name: 'Exports', sub: 'Rapports et documents', href: '/report.htmx.html', icon: 'printer' },
    { name: 'Archives', sub: 'Historique des séances', href: '/archives.htmx.html', icon: 'archive' },
    { name: 'Audit', sub: 'Intégrité et traçabilité', href: '/trust.htmx.html', icon: 'shield-check' },
    { name: 'Statistiques', sub: 'Tableaux de bord', href: '/analytics.htmx.html', icon: 'bar-chart' },
    { name: 'Configuration', sub: 'Paramètres système', href: '/admin.htmx.html', icon: 'settings' },
    { name: 'Guide & FAQ', sub: 'Aide et documentation', href: '/help.htmx.html', icon: 'info' },
  ];

  let searchOverlay = null;
  let searchSelectedIdx = 0;
  let searchFiltered = [];

  function openSearch() {
    if (searchOverlay) return; // already open

    searchOverlay = document.createElement('div');
    searchOverlay.className = 'search-overlay';
    searchOverlay.innerHTML =
      '<div class="search-box">' +
        '<div class="search-input-row">' +
          '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>' +
          '<input type="text" placeholder="Rechercher une page…" autofocus />' +
          '<kbd style="font-size:11px;padding:2px 6px;border-radius:4px;border:1px solid var(--color-border,#d5dbd2);color:var(--color-text-muted,#95a3a4);">Esc</kbd>' +
        '</div>' +
        '<div class="search-results"></div>' +
      '</div>';
    document.body.appendChild(searchOverlay);

    searchFiltered = [...SEARCH_IDX];
    searchSelectedIdx = 0;
    renderSearchResults();

    const input = searchOverlay.querySelector('input');
    input.focus();

    input.addEventListener('input', function() {
      const q = input.value.trim().toLowerCase();
      if (!q) {
        searchFiltered = [...SEARCH_IDX];
      } else {
        searchFiltered = SEARCH_IDX.filter(function(item) {
          return item.name.toLowerCase().includes(q) || (item.sub && item.sub.toLowerCase().includes(q));
        });
      }
      searchSelectedIdx = 0;
      renderSearchResults();
    });

    input.addEventListener('keydown', function(e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); searchSelectedIdx = Math.min(searchSelectedIdx + 1, searchFiltered.length - 1); renderSearchResults(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); searchSelectedIdx = Math.max(searchSelectedIdx - 1, 0); renderSearchResults(); }
      else if (e.key === 'Enter') { e.preventDefault(); if (searchFiltered[searchSelectedIdx]) { window.location.href = searchFiltered[searchSelectedIdx].href; closeSearch(); } }
    });

    searchOverlay.addEventListener('click', function(e) {
      if (e.target === searchOverlay) closeSearch();
      const item = e.target.closest('.search-result-item');
      if (item && item.dataset.href) { window.location.href = item.dataset.href; closeSearch(); }
    });
  }

  function renderSearchResults() {
    if (!searchOverlay) return;
    const container = searchOverlay.querySelector('.search-results');
    if (!container) return;

    if (searchFiltered.length === 0) {
      container.innerHTML = '<div style="padding:20px;text-align:center;font-size:13px;color:var(--color-text-muted,#95a3a4);">Aucun résultat</div>';
      return;
    }

    container.innerHTML = searchFiltered.map(function(item, i) {
      return '<div class="search-result-item' + (i === searchSelectedIdx ? ' sel' : '') + '" data-href="' + item.href + '">' +
        '<div class="sr-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="/assets/icons.svg#icon-' + item.icon + '"></use></svg></div>' +
        '<div><div class="sr-name">' + esc(item.name) + '</div>' +
        (item.sub ? '<div class="sr-sub">' + esc(item.sub) + '</div>' : '') +
        '</div></div>';
    }).join('');

    const sel = container.querySelector('.sel');
    if (sel) sel.scrollIntoView({ block: 'nearest' });
  }

  function closeSearch() {
    if (searchOverlay) {
      searchOverlay.remove();
      searchOverlay = null;
    }
  }

  // Ctrl+K / Cmd+K shortcut
  document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
      e.preventDefault();
      if (searchOverlay) closeSearch();
      else openSearch();
    }
    if (e.key === 'Escape' && searchOverlay) {
      closeSearch();
    }
  });

  // Inject search trigger button in header
  (function() {
    var h = document.querySelector('.app-header');
    if (!h || h.querySelector('.search-trigger')) return;
    var trigger = document.createElement('button');
    trigger.className = 'search-trigger';
    trigger.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> Rechercher… <kbd style="font-size:10px;padding:1px 5px;border-radius:3px;border:1px solid var(--color-border,#d5dbd2);margin-left:8px;color:var(--color-text-muted,#95a3a4);">⌘K</kbd>';
    trigger.addEventListener('click', openSearch);
    // Insert after logo
    var logo = h.querySelector('.logo');
    if (logo && logo.nextSibling) {
      h.insertBefore(trigger, logo.nextSibling);
    } else {
      h.appendChild(trigger);
    }
  })();

  window.GlobalSearch = { open: openSearch, close: closeSearch };

  // Auto-load auth UI banner
  const authScript = document.createElement('script');
  authScript.src = '/assets/js/pages/auth-ui.js';
  document.head.appendChild(authScript);
})();
