<!doctype html>
<html lang="fr">
<head>
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#1650E0" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#0B0F1A" media="(prefers-color-scheme: dark)">
  <meta name="description" content="Nouveau mot de passe AG-VOTE">
  <title>AG-VOTE — Nouveau mot de passe</title>
  <script src="/assets/js/theme-init.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,300;12..96,400;12..96,500;12..96,600;12..96,700;12..96,800&family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,800&family=JetBrains+Mono:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="stylesheet" href="/assets/css/login.css">
</head>
<body>
  <a href="#newPasswordForm" class="skip-link">Aller au formulaire de nouveau mot de passe</a>

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

      <!-- Form: reset_newpassword_form -->
      <form class="login-form" id="newPasswordForm" action="/reset-password" method="POST" autocomplete="off" aria-labelledby="newpassword-heading">
        <h2 id="newpassword-heading">Nouveau mot de passe</h2>
        <p class="login-tagline" style="margin-bottom:1.5rem">Choisissez un nouveau mot de passe pour votre compte</p>

        <!-- Hidden token -->
        <input type="hidden" name="token" value="<?= htmlspecialchars((string) ($token ?? ''), ENT_QUOTES, 'UTF-8') ?>">

        <?php if (!empty($errors)): ?>
          <?php foreach ($errors as $error): ?>
            <div class="login-error" role="alert" aria-live="assertive" style="margin-bottom:0.75rem">
              <?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <!-- New password -->
        <div class="field-group" id="passwordGroup">
          <div class="field-input-wrap">
            <input type="password" id="password" name="password" class="field-input"
                   placeholder=" " required autocomplete="new-password" minlength="8">
            <button type="button" class="field-eye" id="togglePassword"
                    aria-label="Afficher le mot de passe" aria-pressed="false">
              <svg class="eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="eye-closed hidden" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <label for="password" class="field-label">Nouveau mot de passe</label>
        </div>

        <!-- Confirm password -->
        <div class="field-group" id="passwordConfirmGroup">
          <div class="field-input-wrap">
            <input type="password" id="password_confirm" name="password_confirm" class="field-input"
                   placeholder=" " required autocomplete="new-password" minlength="8">
            <button type="button" class="field-eye" id="togglePasswordConfirm"
                    aria-label="Afficher la confirmation du mot de passe" aria-pressed="false">
              <svg class="eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="eye-closed hidden" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <label for="password_confirm" class="field-label">Confirmer le mot de passe</label>
        </div>

        <!-- Submit -->
        <button type="submit" class="login-btn" id="submitBtn">
          <span class="login-btn-text">Reinitialiser le mot de passe</span>
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

  <script src="/assets/js/core/utils.js"></script>
  <script src="/assets/js/pages/login-theme-toggle.js"></script>
  <script>
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
    initEyeToggle('password',         'togglePassword');
    initEyeToggle('password_confirm', 'togglePasswordConfirm');
  </script>
</body>
</html>
