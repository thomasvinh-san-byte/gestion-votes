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

      fetch('/api/v1/auth/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email, password: password })
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.ok) {
          window.location.href = '/meetings.htmx.html';
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
      });
    });
  }
})();
