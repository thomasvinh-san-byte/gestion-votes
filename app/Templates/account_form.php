<!doctype html>
<html lang="fr">
<head>
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#1650E0" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#0B0F1A" media="(prefers-color-scheme: dark)">
  <meta name="description" content="Mon Compte AG-VOTE">
  <title>AG-VOTE — Mon Compte</title>
  <script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>" src="/assets/js/theme-init.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,300;12..96,400;12..96,500;12..96,600;12..96,700;12..96,800&family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,800&family=JetBrains+Mono:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="stylesheet" href="/assets/css/login.css">
  <style nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>">
    .account-profile { margin-bottom: 1.5rem; }
    .account-profile-row { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--color-border, #e2e8f0); }
    .account-profile-label { font-weight: 600; color: var(--color-text-secondary, #64748b); }
    .account-profile-value { text-align: right; }
  </style>
</head>
<body>
  <a href="#accountForm" class="skip-link">Aller au formulaire Mon Compte</a>

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

      <!-- Profile section (read-only) -->
      <?php
        $roleLabels = [
            'admin'    => 'Administrateur',
            'operator' => 'Operateur',
            'auditor'  => 'Auditeur',
            'viewer'   => 'Observateur',
        ];
        $roleLabel = $roleLabels[$user['role'] ?? ''] ?? htmlspecialchars((string) ($user['role'] ?? ''), ENT_QUOTES, 'UTF-8');
      ?>
      <div class="account-profile" aria-label="Informations du compte">
        <div class="account-profile-row">
          <span class="account-profile-label">Nom</span>
          <span class="account-profile-value"><?= htmlspecialchars((string) ($user['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="account-profile-row">
          <span class="account-profile-label">Email</span>
          <span class="account-profile-value"><?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="account-profile-row">
          <span class="account-profile-label">Role</span>
          <span class="account-profile-value"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </div>

      <hr style="border:none;border-top:1px solid var(--color-border,#e2e8f0);margin:0 0 1.5rem 0;">

      <!-- Form: account_form -->
      <!-- Password change form -->
      <form class="login-form" id="accountForm" action="/account" method="POST" autocomplete="on" aria-labelledby="account-heading">
        <h2 id="account-heading">Changer le mot de passe</h2>

        <?php if (!empty($errors)): ?>
          <?php foreach ($errors as $error): ?>
            <div class="login-error" role="alert" aria-live="assertive" style="margin-bottom:0.75rem">
              <?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
          <div class="login-success" role="status" aria-live="polite" style="margin-bottom:0.75rem">
            Mot de passe modifie avec succes.
          </div>
        <?php endif; ?>

        <!-- Mot de passe actuel -->
        <div class="field-group" id="currentPasswordGroup">
          <input type="password" id="current_password" name="current_password" class="field-input"
                 placeholder=" " required autocomplete="current-password">
          <label for="current_password" class="field-label">Mot de passe actuel</label>
        </div>

        <!-- Nouveau mot de passe -->
        <div class="field-group" id="newPasswordGroup">
          <input type="password" id="new_password" name="new_password" class="field-input"
                 placeholder=" " required minlength="8" autocomplete="new-password">
          <label for="new_password" class="field-label">Nouveau mot de passe</label>
        </div>

        <!-- Confirmation -->
        <div class="field-group" id="newPasswordConfirmGroup">
          <input type="password" id="new_password_confirm" name="new_password_confirm" class="field-input"
                 placeholder=" " required minlength="8" autocomplete="new-password">
          <label for="new_password_confirm" class="field-label">Confirmation du mot de passe</label>
        </div>

        <!-- Submit -->
        <button type="submit" class="login-btn" id="submitBtn">
          <span class="login-btn-text">Enregistrer</span>
        </button>
      </form>

      <!-- Back to home -->
      <p style="text-align:center; margin-top:1rem; font-size:0.875rem">
        <a href="/" class="login-link">Retour a l'accueil</a>
      </p>

    </div><!-- .login-card -->

    <!-- RGPD Export -->
    <div class="login-card" style="margin-top:1.5rem;">
      <h2 style="font-size:1rem;font-weight:600;margin-bottom:0.75rem;">Mes donnees (RGPD)</h2>
      <p style="font-size:0.875rem;color:var(--color-text-secondary,#64748b);margin-bottom:1rem;">
        Telechargez une copie de vos donnees personnelles : profil, votes enregistres et presences aux seances.
      </p>
      <a href="/api/v1/rgpd_export"
         download
         class="btn btn-secondary"
         style="display:inline-block;text-decoration:none;"
         aria-label="Exporter mes donnees personnelles (JSON)">
        Exporter mes donnees
      </a>
    </div>

    <!-- Footer -->
    <div class="login-footer">
      <button type="button" class="login-theme-toggle" id="btnTheme" aria-label="Basculer le theme clair/sombre">
        <svg class="theme-icon-light" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        <svg class="theme-icon-dark" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
      </button>
      <span class="login-version">v7.1</span>
    </div>
  </main>

  <script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>" src="/assets/js/core/utils.js"></script>
  <script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>" src="/assets/js/pages/login-theme-toggle.js"></script>
</body>
</html>
