<!doctype html>
<html lang="fr">
<head>
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Vote déjà enregistré - AG-VOTE">
  <title>Vote déjà enregistré - AG-VOTE</title>
  <script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>" src="/assets/js/theme-init.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,300;12..96,400;12..96,500;12..96,600;12..96,700;12..96,800&family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,800&display=swap">
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="stylesheet" href="/assets/css/login.css">
</head>
<body class="login-page">
  <div class="login-card">
    <div class="login-brand">
      <div class="login-brand-mark">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>
      </div>
      <h1>AG-VOTE</h1>
      <p>Vote déjà enregistré</p>
    </div>

    <h2 style="text-align:center;margin-bottom:var(--space-4);">Votre vote a déjà été enregistré</h2>

    <p style="text-align:center;color:var(--color-text-muted);">
      Ce lien de vote a déjà été utilisé. Chaque lien ne peut être utilisé qu'une seule fois.
    </p>
  </div>
</body>
</html>
