(function() {
  // Hamburger menu
  var btn = document.getElementById('hamburgerBtn');
  var nav = document.getElementById('landingNav');
  if (btn && nav) {
    btn.addEventListener('click', function() {
      var expanded = nav.classList.toggle('open');
      btn.setAttribute('aria-expanded', String(expanded));
    });
  }

  // Demo mode detection: if auth disabled, hide login form and show banner
  fetch('/api/v1/whoami.php')
    .then(function(r) { return r.json(); })
    .then(function(data) {
      var d = data.data || data;
      if (d.auth_enabled === false && d.user) {
        var card = document.getElementById('login-card');
        if (card) {
          card.innerHTML =
            '<div style="text-align:center;padding:var(--space-6);">' +
            '  <div style="font-size:2rem;margin-bottom:0.5rem;">&#9889;</div>' +
            '  <h2 class="login-title">Mode D\u00e9monstration</h2>' +
            '  <p style="color:var(--color-text-secondary);margin-bottom:1rem;">' +
            '    Authentification d\u00e9sactiv\u00e9e. Cliquez sur une interface ci-dessous pour explorer librement.' +
            '  </p>' +
            '  <a href="/meetings" class="btn btn-primary btn-lg" style="width:100%;">Explorer l\'application</a>' +
            '</div>';
        }
      }
    })
    .catch(function() { /* ignore — login form stays visible */ });

  // Login form
  var form = document.getElementById('loginForm');
  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      var email = document.getElementById('loginEmail').value.trim();
      var password = document.getElementById('loginPassword').value;
      if (!email || !password) return;
      var submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Connexion...'; }

      fetch('/api/v1/auth_login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email, password: password })
      })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.ok) {
          // Check for redirect/return_to param first
            var urlParams = new URLSearchParams(window.location.search);
            var returnTo = urlParams.get('return_to') || urlParams.get('redirect');
            if (returnTo && returnTo.startsWith('/') && !returnTo.startsWith('//')) {
              window.location.href = returnTo;
              return;
            }
          // Role-aware redirect after login
            var user = data.user || (data.data && data.data.user) || {};
            var role = user.role || 'viewer';
            var dest = { admin: '/dashboard', operator: '/dashboard', auditor: '/trust', viewer: '/meetings' };
            // Fetch meeting roles to detect voter/president
            fetch('/api/v1/whoami.php').then(function(r) { return r.json(); }).then(function(who) {
              var mr = (who.data && who.data.meeting_roles) || [];
              if (mr.some(function(r) { return r.role === 'president'; })) { window.location.href = '/operator?mode=president'; }
              else if (mr.some(function(r) { return r.role === 'voter'; })) { window.location.href = '/vote'; }
              else { window.location.href = dest[role] || '/meetings'; }
            }).catch(function() { window.location.href = dest[role] || '/meetings'; });
          } else {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg><span>Se connecter</span>'; }
            var msg = data.error || 'Identifiants incorrects';
            var errEl = form.querySelector('.login-error');
            if (!errEl) {
              errEl = document.createElement('div');
              errEl.className = 'login-error text-sm text-danger';
              errEl.style.marginTop = '0.5rem';
              form.appendChild(errEl);
            }
            errEl.textContent = msg;
          }
        })
        .catch(function() {
          if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg><span>Se connecter</span>'; }
          var errEl = form.querySelector('.login-error');
          if (!errEl) {
            errEl = document.createElement('div');
            errEl.className = 'login-error text-sm text-danger';
            errEl.style.marginTop = '0.5rem';
            form.appendChild(errEl);
          }
          errEl.textContent = 'Erreur réseau. Vérifiez votre connexion.';
        });
    });
  }
})();
