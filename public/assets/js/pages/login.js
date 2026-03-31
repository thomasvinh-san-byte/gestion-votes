(function() {
  const form = document.getElementById('loginForm');
  const emailInput = document.getElementById('email');
  const passwordInput = document.getElementById('password');
  const errorBox = document.getElementById('errorBox');
  const successBox = document.getElementById('successBox');
  const submitBtn = document.getElementById('submitBtn');
  const toggleBtn = document.getElementById('togglePassword');
  const spinner = document.getElementById('loginSpinner');
  const btnText = submitBtn.querySelector('.login-btn-text');

  // Floating label support: toggle .has-value on .field-group when input has content.
  // Email field can rely on CSS :not(:placeholder-shown) for the direct-sibling case,
  // but password needs JS because its label is a sibling of .field-input-wrap (not input).
  // We apply updateHasValue to both fields for consistency.
  function updateHasValue(input) {
    var group = input.closest('.field-group');
    if (!group) return;
    if (input.value.length > 0) {
      group.classList.add('has-value');
    } else {
      group.classList.remove('has-value');
    }
  }

  // Clear field-level error and update floating label when user starts re-typing
  emailInput.addEventListener('input', function() {
    setFieldError(emailInput, false);
    updateHasValue(emailInput);
  });
  passwordInput.addEventListener('input', function() {
    setFieldError(passwordInput, false);
    updateHasValue(passwordInput);
  });

  // Handle browser autofill: check on page load after a short delay
  setTimeout(function() {
    updateHasValue(emailInput);
    updateHasValue(passwordInput);
  }, 100);

  // Toggle password visibility (eye icon)
  toggleBtn.addEventListener('click', function() {
    var eyeOpen = toggleBtn.querySelector('.eye-open');
    var eyeClosed = toggleBtn.querySelector('.eye-closed');
    if (passwordInput.type === 'password') {
      passwordInput.type = 'text';
      if (eyeOpen) eyeOpen.style.display = 'none';
      if (eyeClosed) eyeClosed.style.display = '';
      toggleBtn.setAttribute('aria-label', 'Masquer le mot de passe');
      toggleBtn.setAttribute('aria-pressed', 'true');
    } else {
      passwordInput.type = 'password';
      if (eyeOpen) eyeOpen.style.display = '';
      if (eyeClosed) eyeClosed.style.display = 'none';
      toggleBtn.setAttribute('aria-label', 'Afficher le mot de passe');
      toggleBtn.setAttribute('aria-pressed', 'false');
    }
  });

  // Field-level error state: add/remove .field-error class on .field-group wrapper.
  // Uses closest('.field-group') — works for both email (direct child) and password
  // (input is inside .field-input-wrap which is inside .field-group).
  function setFieldError(field, hasError) {
    if (!field) return;
    var group = field.closest('.field-group');
    if (!group) return;
    if (hasError) {
      group.classList.add('field-error');
    } else {
      group.classList.remove('field-error');
    }
  }

  function showError(msg) {
    errorBox.textContent = msg;
    errorBox.classList.add('visible');
    successBox.classList.remove('visible');
    // Accessibility: move focus to error so screen readers announce it
    errorBox.focus();
  }

  function showSuccess(msg) {
    successBox.textContent = msg;
    successBox.classList.add('visible');
    errorBox.classList.remove('visible');
  }

  function isSafeRedirect(url) {
    if (!url || typeof url !== 'string') return false;
    if (url[0] !== '/' || url[1] === '/') return false;
    try {
      var decoded = decodeURIComponent(url);
      if (/^\s*(javascript|data|vbscript)\s*:/i.test(decoded)) return false;
    } catch (_) { return false; }
    return true;
  }

  function redirectByRole(user, meetingRoles) {
    var redirect = new URLSearchParams(location.search).get('redirect');
    if (redirect && isSafeRedirect(redirect)) {
      window.location.href = redirect;
      return;
    }
    var mr = meetingRoles || [];
    var isPresident = mr.some(function(r) { return r.role === 'president'; });
    var isVoter = mr.some(function(r) { return r.role === 'voter'; });
    if (isPresident) {
      window.location.href = '/operator?mode=president';
    } else if (isVoter) {
      window.location.href = '/vote';
    } else {
      var role = user.role || 'viewer';
      var defaultPage = {
        admin: '/admin',
        operator: '/meetings',
        auditor: '/trust',
        viewer: '/meetings'
      };
      window.location.href = defaultPage[role] || '/meetings';
    }
  }

  var roleLabel = { admin: 'Administrateur', operator: 'Opérateur', auditor: 'Auditeur', viewer: 'Observateur' };

  form.addEventListener('submit', async function(e) {
    e.preventDefault();

    var email = emailInput.value.trim();
    var password = passwordInput.value;

    // Clear previous field-level errors on new submit attempt
    setFieldError(emailInput, false);
    setFieldError(passwordInput, false);

    if (!email || !password) {
      showError('Veuillez saisir votre email et votre mot de passe.');
      return;
    }

    if (window.Utils && Utils.isValidEmail && !Utils.isValidEmail(email)) {
      showError('Format d\u2019adresse e-mail invalide.');
      return;
    }

    if (window.Utils && Utils.isValidPassword) {
      var pwCheck = Utils.isValidPassword(password);
      if (!pwCheck.valid) {
        showError(pwCheck.message);
        return;
      }
    }

    submitBtn.disabled = true;
    btnText.textContent = 'Connexion...';
    spinner.style.display = 'inline-block';
    errorBox.classList.remove('visible');
    successBox.classList.remove('visible');

    var { status, body } = await api('/api/v1/auth_login.php', { email: email, password: password });

    if (status === 0) {
      // Timeout or network error — api() already caught it
      showError(body.message || 'Erreur réseau. Vérifiez votre connexion.');
      submitBtn.disabled = false;
      btnText.textContent = 'Se connecter';
      spinner.style.display = 'none';
      return;
    }

    if (body.ok) {
      var user = body.user || (body.data && body.data.user) || {};
      showSuccess('Connecté en tant que ' + (user.name || user.email || '') + ' (' + (roleLabel[user.role] || user.role) + ')');
      btnText.textContent = 'Connecté';
      spinner.style.display = 'none';

      setTimeout(function() {
        api('/api/v1/whoami.php')
          .then(function(res) {
            var who = res.body;
            var mr = (who.data && who.data.meeting_roles) || [];
            redirectByRole(user, mr);
          })
          .catch(function() {
            // whoami failed — redirect based on login response role alone
            redirectByRole(user, []);
          });
      }, 600);
    } else {
      var detail = Utils.getApiError(body, 'Email ou mot de passe incorrect.');
      showError(detail);

      // Apply field-level error highlighting based on error content
      var detailLower = (detail || '').toLowerCase();
      if (detailLower.includes('email') || detailLower.includes('identifiant')) {
        setFieldError(emailInput, true);
      }
      if (detailLower.includes('password') || detailLower.includes('mot de passe')) {
        setFieldError(passwordInput, true);
      }
      // Generic auth error (wrong credentials) — highlight both fields
      if (detailLower.includes('incorrect') || detailLower.includes('invalide')) {
        setFieldError(emailInput, true);
        setFieldError(passwordInput, true);
      }

      submitBtn.disabled = false;
      btnText.textContent = 'Se connecter';
      spinner.style.display = 'none';
    }
  });

  // Forgot password
  document.getElementById('forgotLink').addEventListener('click', function(e) {
    e.preventDefault();
    var msg = document.getElementById('forgotMsg');
    msg.textContent = 'Contactez votre administrateur pour réinitialiser votre mot de passe.';
    msg.classList.add('visible');
  });

  // Show demo credentials hint — populates and unhides #demoPanel (static div in HTML)
  function showDemoHint() {
    var panel = document.getElementById('demoPanel');
    if (!panel) return;
    var accounts = [
      { role: 'Admin', email: 'admin@ag-vote.local', pass: 'Admin2026!' },
      { role: 'Operateur', email: 'operator@ag-vote.local', pass: 'Operator2026!' },
      { role: 'Auditeur', email: 'auditor@ag-vote.local', pass: 'Auditor2026!' },
      { role: 'Votant', email: 'votant@ag-vote.local', pass: 'Votant2026!' }
    ];
    panel.innerHTML = '<div style="font-weight:600;margin-bottom:8px;">Comptes de demonstration</div>' +
      accounts.map(function(a) {
        return '<div style="display:flex;justify-content:space-between;align-items:center;padding:3px 0;">' +
          '<span><strong>' + a.role + '</strong> — <code style="font-size:12px;">' + a.email + '</code></span>' +
          '<button type="button" class="demo-fill-btn" data-email="' + a.email + '" data-pass="' + a.pass + '" ' +
          'style="font-size:11px;padding:2px 8px;border-radius:4px;border:1px solid var(--color-border,#d5dbd2);background:var(--color-surface,#fff);cursor:pointer;">Utiliser</button>' +
          '</div>';
      }).join('');
    panel.removeAttribute('hidden');
    panel.addEventListener('click', function(e) {
      var btn = e.target.closest('.demo-fill-btn');
      if (!btn) return;
      emailInput.value = btn.getAttribute('data-email');
      passwordInput.value = btn.getAttribute('data-pass');
      updateHasValue(emailInput);
      updateHasValue(passwordInput);
      emailInput.focus();
    });
  }

  // Auto-check : si deja connecte, rediriger selon le role
  api('/api/v1/whoami.php')
    .then(function(res) {
      var data = res.body;
      var d = data.data || data;
      var authEnabled = d.auth_enabled;
      var appEnv = d.app_env;
      var user = d.user || null;

      // Mode demo (auth desactivee) : rediriger directement sans login
      if (data.ok && authEnabled === false && user) {
        showSuccess('Mode démonstration — ' + (user.name || 'Demo') + '. Redirection...');
        setTimeout(function() {
          var mr = d.meeting_roles || [];
          redirectByRole(user, mr);
        }, 600);
        return;
      }

      // Show demo accounts hint when in demo env
      if (appEnv === 'demo' || appEnv === 'development') {
        showDemoHint();
      }

      if (data.ok && user) {
        showSuccess('Déjà connecté : ' + (user.name || user.email) + ' (' + (roleLabel[user.role] || user.role) + '). Redirection...');
        setTimeout(function() {
          var mr = d.meeting_roles || [];
          redirectByRole(user, mr);
        }, 800);
      }
    });
})();
