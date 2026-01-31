(function(){
  window.Auth = window.Auth || { role: null, user: null, enabled: null };

  // Dual role model: system roles hierarchy
  function hasAccess(required, current){
    if(!required) return true;
    if(current === "admin") return true;
    if(required === current) return true;
    const order = {admin:4, operator:3, auditor:2, viewer:1};
    return (order[current] || 0) >= (order[required] || 0);
  }

  function ensureBanner(){
    let b = document.getElementById("auth-banner");
    if(b) return b;
    b = document.createElement("div");
    b.id = "auth-banner";
    b.style.cssText = "position:sticky;top:0;z-index:20;background:var(--color-surface,#fff);border-bottom:1px solid var(--color-border,#eee);padding:8px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;font-size:13px;";
    b.innerHTML =
      "<div><strong>Acces</strong> <span class='muted' id='auth-status' style='font-size:12px;'>...</span></div>" +
      "<div style='display:flex;gap:8px;align-items:center;'>" +
      "  <button class='btn btn-sm' id='auth-login-btn' style='font-size:12px;'>Se connecter</button>" +
      "  <button class='btn btn-sm' id='auth-logout-btn' style='display:none;font-size:12px;'>Deconnexion</button>" +
      "</div>";
    document.body.prepend(b);

    b.querySelector("#auth-login-btn").addEventListener("click", function() {
      window.location.href = "/login.html?redirect=" + encodeURIComponent(window.location.pathname + window.location.search);
    });
    b.querySelector("#auth-logout-btn").addEventListener("click", async function() {
      try {
        await fetch("/api/v1/auth_logout.php", {
          method: "POST",
          credentials: "same-origin",
        });
      } catch(e) {}
      try { localStorage.removeItem("api_key"); } catch(e) {}
      window.Auth.user = null;
      window.Auth.role = null;
      window.location.href = "/login.html";
    });
    return b;
  }

  function setStatus(text, type, isLoggedIn){
    const b = ensureBanner();
    const s = b.querySelector("#auth-status");
    s.textContent = "— " + text;
    b.style.borderBottomColor = (type === "danger") ? "#f3b3b3" : "var(--color-border,#eee)";
    b.querySelector("#auth-login-btn").style.display = isLoggedIn ? "none" : "";
    b.querySelector("#auth-logout-btn").style.display = isLoggedIn ? "" : "none";
  }

  function applyVisibility(){
    const role = window.Auth.role;
    document.querySelectorAll("[data-requires-role]").forEach(function(el) {
      const req = el.getAttribute("data-requires-role");
      el.style.display = hasAccess(req, role) ? "" : "none";
    });
  }

  async function boot(){
    try {
      const headers = {};
      try {
        const storedKey = localStorage.getItem("api_key");
        if (storedKey) headers["X-Api-Key"] = storedKey;
      } catch(e) {}

      const resp = await fetch("/api/v1/whoami.php", {
        credentials: "same-origin",
        headers: headers,
      });
      const data = await resp.json();

      const user = (data.data && data.data.user) ? data.data.user : (data.user || null);
      const authEnabled = data.data ? data.data.auth_enabled : data.auth_enabled;

      window.Auth.enabled = !!authEnabled;
      window.Auth.user = user;
      window.Auth.role = user ? user.role : null;

      if (!authEnabled) {
        setStatus("auth desactivee (dev)", "ok", false);
      } else if (!user) {
        setStatus("Non connecte", "danger", false);
      } else {
        setStatus((user.name || user.email || "utilisateur") + " (" + user.role + ")", "ok", true);
      }
      applyVisibility();
    } catch(e) {
      window.Auth.enabled = true;
      window.Auth.user = null;
      window.Auth.role = null;
      setStatus("Non connecte", "danger", false);
      applyVisibility();
    }
  }

  ensureBanner();
  boot();
})();
