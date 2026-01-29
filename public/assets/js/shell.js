// public/assets/js/shell.js
(function(){
  const overlay = document.querySelector(".drawer-backdrop, [data-drawer-close]");
  const drawer = document.getElementById("drawer") || document.querySelector(".drawer");
  const dbody = document.getElementById("drawerBody");
  const titleEl = document.getElementById("drawerTitle");

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

    if (kind === "readiness") { setTitle("Séance — Readiness"); renderReadiness(meetingId); }
    else if (kind === "menu") { setTitle("Menu séance"); renderMenu(meetingId); }
    else if (kind === "infos") { setTitle("Informations"); renderInfos(meetingId); }
    else if (kind === "anomalies") { setTitle("Anomalies"); renderAnomalies(meetingId); }
    else { dbody.innerHTML = ""; }
  }

  // ---- Menu drawer ----
  function renderMenu(meetingId) {
    var mid = meetingId ? '?meeting_id=' + encodeURIComponent(meetingId) : '';
    dbody.innerHTML =
      '<nav style="display:flex;flex-direction:column;gap:6px;padding:4px 0;">' +
        '<a href="/operator.htmx.html' + mid + '" class="btn btn-ghost" style="justify-content:flex-start;">Opérateur</a>' +
        '<a href="/president.htmx.html' + mid + '" class="btn btn-ghost" style="justify-content:flex-start;">Président</a>' +
        '<a href="/motions.htmx.html' + mid + '" class="btn btn-ghost" style="justify-content:flex-start;">Résolutions</a>' +
        '<a href="/attendance.htmx.html' + mid + '" class="btn btn-ghost" style="justify-content:flex-start;">Présences</a>' +
        '<a href="/proxies.htmx.html' + mid + '" class="btn btn-ghost" style="justify-content:flex-start;">Procurations</a>' +
        '<a href="/invitations.htmx.html' + mid + '" class="btn btn-ghost" style="justify-content:flex-start;">Invitations</a>' +
        '<a href="/trust.htmx.html' + mid + '" class="btn btn-ghost" style="justify-content:flex-start;">Contrôle</a>' +
        '<a href="/report.htmx.html' + mid + '" class="btn btn-ghost" style="justify-content:flex-start;">PV / Export</a>' +
        '<hr style="border-color:var(--color-border,#ddd);margin:4px 0;">' +
        '<a href="/meetings.htmx.html" class="btn btn-ghost" style="justify-content:flex-start;">Séances</a>' +
        '<a href="/members.htmx.html" class="btn btn-ghost" style="justify-content:flex-start;">Membres</a>' +
        '<a href="/admin.htmx.html" class="btn btn-ghost" style="justify-content:flex-start;">Admin</a>' +
      '</nav>';
  }

  // ---- Readiness drawer ----
  async function renderReadiness(meetingId) {
    if (!meetingId) { dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Sélectionnez une séance.</div>'; return; }
    dbody.innerHTML = '<div style="padding:16px;" class="text-muted">Chargement…</div>';
    try {
      var res = await window.api('/api/v1/meeting_ready_check.php?meeting_id=' + meetingId);
      var b = res.body;
      if (b && b.ok && b.data) {
        var d = b.data;
        var checks = d.checks || [];
        dbody.innerHTML =
          '<div style="padding:4px 0;display:flex;flex-direction:column;gap:10px;">' +
            '<div class="badge ' + (d.ready ? 'badge-success' : 'badge-warning') + '" style="font-size:14px;padding:6px 12px;">' +
              (d.ready ? 'Prêt' : 'Non prêt') +
            '</div>' +
            checks.map(function(c) {
              return '<div style="display:flex;align-items:center;gap:8px;">' +
                '<span>' + (c.ok ? '✅' : '❌') + '</span>' +
                '<span>' + esc(c.label || c.check || '') + '</span>' +
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
      var res = await window.api('/api/v1/meetings.php?id=' + meetingId);
      var b = res.body;
      if (b && b.ok && b.data) {
        var m = b.data;
        var statusBadge = m.status === 'live' ? 'badge-success' : (m.status === 'draft' ? 'badge-neutral' : 'badge-warning');
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
      var res = await window.api('/api/v1/operator_anomalies.php?meeting_id=' + meetingId);
      var b = res.body;
      if (b && b.ok && b.data) {
        var items = b.data.anomalies || b.data.items || [];
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
    var t = e.target;
    if (!t) return;

    var btn = t.closest && t.closest("[data-drawer]");
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

  // Expose for debug
  window.ShellDrawer = { open: openDrawer, close: closeDrawer };
})();
