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

  // Toggle password visibility
  toggleBtn.addEventListener('click', function() {
    if (passwordInput.type === 'password') {
      passwordInput.type = 'text';
      toggleBtn.textContent = 'Masquer';
      toggleBtn.setAttribute('aria-label', 'Masquer le mot de passe');
      toggleBtn.setAttribute('aria-pressed', 'true');
    } else {
      passwordInput.type = 'password';
      toggleBtn.textContent = 'Voir';
      toggleBtn.setAttribute('aria-label', 'Afficher le mot de passe');
      toggleBtn.setAttribute('aria-pressed', 'false');
    }
  });

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
      window.location.href = '/operator.htmx.html?mode=president';
    } else if (isVoter) {
      window.location.href = '/vote.htmx.html';
    } else {
      var role = user.role || 'viewer';
      var defaultPage = {
        admin: '/admin.htmx.html',
        operator: '/meetings.htmx.html',
        auditor: '/trust.htmx.html',
        viewer: '/meetings.htmx.html'
      };
      window.location.href = defaultPage[role] || '/meetings.htmx.html';
    }
  }

  var roleLabel = { admin: 'Administrateur', operator: 'Opérateur', auditor: 'Auditeur', viewer: 'Observateur' };

  form.addEventListener('submit', async function(e) {
    e.preventDefault();

    var email = emailInput.value.trim();
    var password = passwordInput.value;

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

  // Auto-check : si deja connecte, rediriger selon le role
  api('/api/v1/whoami.php')
    .then(function(res) {
      var data = res.body;
      var user = (data.data && data.data.user) ? data.data.user : null;
      if (data.ok && user) {
        showSuccess('Déjà connecté : ' + (user.name || user.email) + ' (' + (roleLabel[user.role] || user.role) + '). Redirection...');
        setTimeout(function() {
          var mr = (data.data && data.data.meeting_roles) || [];
          redirectByRole(user, mr);
        }, 800);
      }
    });
})();
