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

  // Auto-load auth UI banner
  const authScript = document.createElement('script');
  authScript.src = '/assets/js/pages/auth-ui.js';
  document.head.appendChild(authScript);
})();
