<!doctype html>
<html lang="fr">
<head>
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#1650E0" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#0B0F1A" media="(prefers-color-scheme: dark)">
  <meta name="description" content="Installation initiale de la plateforme AG-VOTE">
  <title>AG-VOTE — Installation</title>
  <script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>" src="/assets/js/theme-init.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,300;12..96,400;12..96,500;12..96,600;12..96,700;12..96,800&family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,800&family=JetBrains+Mono:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="stylesheet" href="/assets/css/login.css">
</head>
<body>
  <a href="#setupForm" class="skip-link">Aller au formulaire de configuration</a>

  <!-- Animated gradient orb -->
  <div class="login-orb" aria-hidden="true"></div>

  <main class="login-page" role="main">
    <div class="login-card">

      <!-- Brand -->
      <div class="login-brand">
        <div class="login-brand-mark">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>
        </div>
        <h1>AG-VOTE</h1>
        <p class="login-tagline">Gestion des assemblees deliberatives</p>
      </div>

      <!-- Form: setup_form — initial organisation and admin account creation -->
      <form class="login-form" id="setupForm" action="/setup" method="POST" autocomplete="on" aria-labelledby="setup-heading">
        <?= \AgVote\Core\Security\CsrfMiddleware::field() ?>
        <h2 id="setup-heading">Configuration initiale</h2>
        <p class="login-tagline" style="margin-bottom:1.5rem">Creez votre organisation et votre compte administrateur</p>

        <?php if (!empty($errors)): ?>
          <?php foreach ($errors as $error): ?>
            <div class="login-error" role="alert" aria-live="assertive" style="margin-bottom:0.75rem">
              <?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <!-- Organisation name -->
        <div class="field-group" id="orgNameGroup">
          <input type="text" id="organisation_name" name="organisation_name" class="field-input"
                 placeholder=" " required autocomplete="organization"
                 value="<?= htmlspecialchars((string) ($old['organisation_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          <label for="organisation_name" class="field-label">Nom de l'organisation</label>
        </div>

        <!-- Admin name -->
        <div class="field-group" id="adminNameGroup">
          <input type="text" id="admin_name" name="admin_name" class="field-input"
                 placeholder=" " required autocomplete="name"
                 value="<?= htmlspecialchars((string) ($old['admin_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          <label for="admin_name" class="field-label">Nom de l'administrateur</label>
        </div>

        <!-- Admin email -->
        <div class="field-group" id="adminEmailGroup">
          <input type="email" id="admin_email" name="admin_email" class="field-input"
                 placeholder=" " required autocomplete="email"
                 value="<?= htmlspecialchars((string) ($old['admin_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          <label for="admin_email" class="field-label">Adresse email</label>
        </div>

        <!-- Password -->
        <div class="field-group" id="adminPasswordGroup">
          <div class="field-input-wrap">
            <input type="password" id="admin_password" name="admin_password" class="field-input"
                   placeholder=" " required autocomplete="new-password">
            <button type="button" class="field-eye" id="togglePassword"
                    aria-label="Afficher le mot de passe" aria-pressed="false">
              <svg class="eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="eye-closed hidden" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <label for="admin_password" class="field-label">Mot de passe</label>
        </div>

        <!-- Password confirmation -->
        <div class="field-group" id="adminPasswordConfirmGroup">
          <div class="field-input-wrap">
            <input type="password" id="admin_password_confirm" name="admin_password_confirm" class="field-input"
                   placeholder=" " required autocomplete="new-password">
            <button type="button" class="field-eye" id="togglePasswordConfirm"
                    aria-label="Afficher la confirmation du mot de passe" aria-pressed="false">
              <svg class="eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="eye-closed hidden" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <label for="admin_password_confirm" class="field-label">Confirmer le mot de passe</label>
        </div>

        <!-- Submit -->
        <button type="submit" class="login-btn" id="submitBtn">
          <span class="login-btn-text">Creer l'organisation</span>
        </button>
      </form>
    </div><!-- .login-card -->

    <!-- Footer -->
    <div class="login-footer">
      <button type="button" class="login-theme-toggle" id="btnTheme" aria-label="Basculer le theme clair/sombre">
        <svg class="theme-icon-light" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        <svg class="theme-icon-dark" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
      </button>
      <span class="login-version">v7.0</span>
    </div>
  </main>

  <script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>" src="/assets/js/core/utils.js"></script>
  <script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>" src="/assets/js/pages/login-theme-toggle.js"></script>
  <script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>">
    // Password eye toggles
    function initEyeToggle(inputId, btnId) {
      const input = document.getElementById(inputId);
      const btn   = document.getElementById(btnId);
      if (!input || !btn) return;
      btn.addEventListener('click', function () {
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        btn.setAttribute('aria-pressed', String(isPassword));
        btn.querySelector('.eye-open').classList.toggle('hidden', isPassword);
        btn.querySelector('.eye-closed').classList.toggle('hidden', !isPassword);
      });
    }
    initEyeToggle('admin_password',         'togglePassword');
    initEyeToggle('admin_password_confirm',  'togglePasswordConfirm');
  </script>
</body>
</html>
