(function(){
  window.Auth = window.Auth || { role: null, user: null, enabled: null };

  const ROLE_ORDER = ["readonly","operator","trust","admin"];
  function roleRank(r){ const i = ROLE_ORDER.indexOf(r||""); return i === -1 ? -1 : i; }

  function hasAccess(required, current){
    if(!required) return true;
    if(current === "admin") return true;
    if(required === current) return true;
    // readonly can only view
    // operator and trust are distinct; no implicit crossover
    return false;
  }

  function ensureBanner(){
    let b = document.getElementById("auth-banner");
    if(b) return b;
    b = document.createElement("div");
    b.id = "auth-banner";
    b.style.cssText = "position:sticky;top:0;z-index:20;background:#fff;border-bottom:1px solid #eee;padding:10px 12px;";
    b.innerHTML = "<div class='row between' style='gap:12px;flex-wrap:wrap;align-items:center;'>"
      + "<div><strong>Accès</strong> <span class='muted tiny' id='auth-status'>…</span></div>"
      + "<div class='row' style='gap:8px;flex-wrap:wrap;'>"
      + "  <button class='btn' id='auth-set-key'>Définir clé API</button>"
      + "  <button class='btn' id='auth-clear-key'>Effacer</button>"
      + "</div></div>";
    document.body.prepend(b);

    b.querySelector("#auth-set-key").addEventListener("click", () => {
      const k = prompt("Colle la clé API (X-API-Key) :");
      if(k !== null){
        Utils.setStoredApiKey(k.trim());
        boot();
      }
    });
    b.querySelector("#auth-clear-key").addEventListener("click", () => {
      Utils.setStoredApiKey("");
      boot();
    });
    return b;
  }

  function setStatus(text, type){
    const b = ensureBanner();
    const s = b.querySelector("#auth-status");
    s.textContent = "— " + text;
    b.style.borderBottomColor = (type === "danger") ? "#f3b3b3" : "#eee";
  }

  function applyVisibility(){
    const role = window.Auth.role;
    document.querySelectorAll("[data-requires-role]").forEach(el => {
      const req = el.getAttribute("data-requires-role");
      const ok = hasAccess(req, role);
      el.style.display = ok ? "" : "none";
    });
  }

  async function boot(){
    try {
      const r = await Utils.apiGet("/api/v1/whoami.php");
      window.Auth.enabled = !!r.auth_enabled;
      window.Auth.user = r.user || null;
      window.Auth.role = r.user ? r.user.role : null;

      if (!r.auth_enabled) {
        setStatus("auth désactivée", "ok");
      } else if (!r.user) {
        setStatus("clé API requise", "danger");
      } else {
        setStatus((r.user.name || r.user.email || "user") + " (" + r.user.role + ")", "ok");
      }
      applyVisibility();
    } catch(e) {
      // 401 => key missing/invalid
      const enabled = true;
      window.Auth.enabled = enabled;
      window.Auth.user = null;
      window.Auth.role = null;
      setStatus("clé API requise ou invalide", "danger");
      applyVisibility();
    }
  }

  // Always show banner; it becomes informative even when auth disabled.
  ensureBanner();
  boot();
})();
