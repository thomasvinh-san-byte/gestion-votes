// public/assets/js/shell.js
(function(){
  const overlay = document.querySelector(".drawer-overlay");
  const drawer = document.querySelector(".drawer");
  const body = document.getElementById("drawerBody");
  const titleEl = document.querySelector(".drawer-title");

  function getMeetingId(){
    // Try to find a meeting_id in common places (no page changes required)
    const el = document.querySelector("[data-meeting-id]");
    if (el && el.getAttribute("data-meeting-id")) return el.getAttribute("data-meeting-id");

    const input = document.querySelector('input[name="meeting_id"]');
    if (input && input.value) return input.value;

    const params = new URLSearchParams(window.location.search);
    if (params.get("meeting_id")) return params.get("meeting_id");

    return "";
  }

  function setTitle(t){ if (titleEl) titleEl.textContent = t; }

  function openDrawer(kind){
    if (!overlay || !drawer || !body) return;

    overlay.hidden = false;
    drawer.hidden = false;
    drawer.setAttribute("data-open", "1");
    overlay.setAttribute("data-open", "1");

    const meetingId = getMeetingId();
    let url = "";
    if (kind === "readiness") { setTitle("Séance — Readiness"); url = "/fragments/drawer_readiness.php"; }
    if (kind === "menu")      { setTitle("Menu séance"); url = "/fragments/drawer_menu.php"; }
    if (kind === "infos")     { setTitle("Informations"); url = "/fragments/drawer_infos.php"; }
    if (kind === "anomalies") { setTitle("Anomalies"); url = "/fragments/drawer_anomalies.php"; }

    if (!url) return;

    if (meetingId) url += (url.includes("?") ? "&" : "?") + "meeting_id=" + encodeURIComponent(meetingId);

    // HTMX present? use it. Else fetch().
    if (window.htmx && body){
      body.setAttribute("hx-get", url);
      body.setAttribute("hx-trigger", "load");
      body.setAttribute("hx-target", "#drawerBody");
      window.htmx.process(body);
      // force load
      window.htmx.trigger(body, "load");
    } else {
      fetch(url, { credentials: "same-origin" })
        .then(r => r.text())
        .then(html => { body.innerHTML = html; })
        .catch(() => { body.innerHTML = "<div class='card' style='padding:12px;'>Impossible de charger le panneau.</div>"; });
    }
  }

  function closeDrawer(){
    if (!overlay || !drawer) return;
    overlay.hidden = true;
    drawer.hidden = true;
    drawer.removeAttribute("data-open");
    overlay.removeAttribute("data-open");
  }

  document.addEventListener("click", (e) => {
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

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeDrawer();
  });

  // Expose for debug
  window.ShellDrawer = { open: openDrawer, close: closeDrawer };
})();
