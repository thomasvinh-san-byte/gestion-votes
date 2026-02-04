// public/assets/js/shell.js
(function(){
  const overlay = document.querySelector(".drawer-backdrop, [data-drawer-close]");
  const drawer = document.getElementById("drawer") || document.querySelector(".drawer");
  const dbody = document.getElementById("drawerBody");
  const titleEl = document.getElementById("drawerTitle");

  // Registry for page-specific drawer kinds
  const customKinds = {};

  function getMeetingId(){
    const el = document.querySelector("[data-meeting-id]");
    if (el && el.getAttribute("data-meeting-id")) return el.getAttribute("data-meeting-id");

    const input = document.querySelector('input[name="meeting_id"]');
    if (input && input.value) return input.value;

    const params = new URLSearchParams(window.location.search);
    if (params.get("meeting_id")) return params.get("meeting_id");

    if (window.Utils && window.Utils.getMeetingId) return window.Utils.getMeetingId() || "";

    return "";
  }

  function setTitle(t){ if (titleEl) titleEl.textContent = t; }

  function esc(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

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
    if (kind === "readiness") { setTitle("Préparation"); renderReadiness(meetingId); }
    else if (kind === "menu") { setTitle("Navigation"); renderMenu(meetingId); }
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

  // ---- Menu drawer (role-aware) ----
  function renderMenu(meetingId) {
    const mid = meetingId ? '?meeting_id=' + encodeURIComponent(meetingId) : '';
    const role = (window.Auth && window.Auth.role) || null;
    const mr = (window.Auth && window.Auth.meetingRoles) || [];
    const check = (window.Auth && window.Auth.hasAccess)
      ? function(r) { return window.Auth.hasAccess(r, role, mr); }
      : function() { return true; };

    const items = [
      { href: '/operator.htmx.html',    label: 'Opérateur',     req: 'operator',            useMid: true },
      { href: '/president.htmx.html',   label: 'Président',     req: 'president,operator',  useMid: true },
      { href: '/motions.htmx.html',     label: 'Résolutions',   req: 'operator',            useMid: true },
      { href: '/attendance.htmx.html',  label: 'Présences',     req: 'operator',            useMid: true },
      { href: '/proxies.htmx.html',     label: 'Procurations',  req: 'operator',            useMid: true },
      { href: '/invitations.htmx.html', label: 'Invitations',   req: 'operator',            useMid: true },
      { href: '/vote.htmx.html',        label: 'Vote',          req: 'voter,operator',      useMid: true },
      { href: '/trust.htmx.html',       label: 'Contrôle',      req: 'auditor,assessor',    useMid: true },
      { href: '/report.htmx.html',      label: 'PV / Export',   req: 'operator,president',  useMid: true },
      { sep: true },
      { href: '/meetings.htmx.html',    label: 'Séances',       req: 'viewer',              useMid: false },
      { href: '/members.htmx.html',     label: 'Membres',       req: 'operator',            useMid: false },
      { href: '/archives.htmx.html',    label: 'Archives',      req: 'viewer',              useMid: false },
      { href: '/admin.htmx.html',       label: 'Admin',         req: 'admin',               useMid: false }
    ];

    let html = '<nav style="display:flex;flex-direction:column;gap:6px;padding:4px 0;">';
    for (let i = 0; i < items.length; i++) {
      const it = items[i];
      if (it.sep) { html += '<hr style="border-color:var(--color-border,#ddd);margin:4px 0;">'; continue; }
      if (!check(it.req)) continue;
      const url = it.href + (it.useMid ? mid : '');
      html += '<a href="' + url + '" class="btn btn-ghost" style="justify-content:flex-start;">' + it.label + '</a>';
    }
    html += '</nav>';
    dbody.innerHTML = html;
  }

  // ---- Readiness drawer ----
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

  // ---- Infos drawer ----
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

  // ---- Anomalies drawer ----
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

  // Auto-load auth UI banner (login/logout + role visibility)
  const authScript = document.createElement('script');
  authScript.src = '/assets/js/auth-ui.js';
  document.head.appendChild(authScript);
})();
