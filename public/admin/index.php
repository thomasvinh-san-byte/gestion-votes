<?php
declare(strict_types=1);

/**
 * Admin Application - Main Entry Point
 *
 * Single-page-like admin interface using HTMX fragments.
 * All routes require the 'admin' role.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

// Enforce admin role on every request
AuthMiddleware::requireRole('admin');

$user = AuthMiddleware::getCurrentUser();
$userName = htmlspecialchars((string)($user['name'] ?? $user['email'] ?? 'Admin'), ENT_QUOTES, 'UTF-8');
$userRole = htmlspecialchars((string)($user['role'] ?? 'admin'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AG-VOTE - Administration</title>
  <link rel="stylesheet" href="/assets/css/design-system.css">
  <link rel="stylesheet" href="/assets/css/admin.css">
  <script src="https://unpkg.com/htmx.org@1.9.12"></script>
  <?php
  // CSRF meta tag for HTMX requests
  if (class_exists('CsrfMiddleware')) {
      echo CsrfMiddleware::metaTag();
  }
  ?>
</head>
<body>

<div class="admin-shell" id="admin-app">

  <!-- ═══════════════ SIDEBAR ═══════════════ -->
  <aside class="admin-sidebar">
    <div class="admin-sidebar-brand">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/>
        <path d="m9 12 2 2 4-4"/>
      </svg>
      AG-VOTE Admin
    </div>

    <nav class="admin-sidebar-nav">
      <div class="admin-sidebar-section">Principal</div>

      <button class="admin-nav-item active" data-page="dashboard" onclick="AdminApp.navigate('dashboard')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Tableau de bord
      </button>

      <button class="admin-nav-item" data-page="users" onclick="AdminApp.navigate('users')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Utilisateurs
      </button>

      <div class="admin-sidebar-section">Configuration</div>

      <button class="admin-nav-item" data-page="policies" onclick="AdminApp.navigate('policies')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Politiques
      </button>

      <button class="admin-nav-item" data-page="meetings" onclick="AdminApp.navigate('meetings')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Seances
      </button>

      <div class="admin-sidebar-section">Systeme</div>

      <button class="admin-nav-item" data-page="monitoring" onclick="AdminApp.navigate('monitoring')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Monitoring
      </button>

      <button class="admin-nav-item" data-page="audit" onclick="AdminApp.navigate('audit')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Journal d'audit
      </button>

      <button class="admin-nav-item" data-page="alerts" onclick="AdminApp.navigate('alerts')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        Alertes
        <span class="admin-nav-badge htmx-indicator" id="alerts-count" style="display:none;">0</span>
      </button>
    </nav>

    <div class="admin-sidebar-footer">
      <div><?= $userName ?></div>
      <div class="badge badge-primary mt-1"><?= $userRole ?></div>
    </div>
  </aside>

  <!-- ═══════════════ HEADER ═══════════════ -->
  <header class="admin-header">
    <div class="admin-header-title" id="page-title">Tableau de bord</div>
    <div class="admin-header-actions">
      <div class="status-indicator" id="system-status"
           hx-get="/admin/fragments/system_badge.php"
           hx-trigger="load, every 30s"
           hx-swap="innerHTML">
        <span class="status-dot"></span>
        <span>--</span>
      </div>
      <a href="/" class="btn btn-ghost btn-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Retour app
      </a>
    </div>
  </header>

  <!-- ═══════════════ MAIN CONTENT ═══════════════ -->
  <main class="admin-main" id="admin-content">
    <div id="page-container"
         hx-get="/admin/fragments/dashboard.php"
         hx-trigger="load"
         hx-swap="innerHTML"
         hx-indicator="#page-loader">
      <div id="page-loader" class="empty-state htmx-indicator">
        <div class="spinner spinner-lg"></div>
        <p class="text-muted mt-4">Chargement...</p>
      </div>
    </div>
  </main>

</div>

<!-- Toast container -->
<div class="toast-container" id="toast-container"></div>

<!-- CSRF integration for HTMX -->
<script>
<?php if (class_exists('CsrfMiddleware')): ?>
(function() {
  var csrfToken = document.querySelector('meta[name="csrf-token"]');
  if (csrfToken) {
    document.body.addEventListener('htmx:configRequest', function(e) {
      e.detail.headers['X-CSRF-Token'] = csrfToken.getAttribute('content');
    });
  }
})();
<?php endif; ?>
</script>

<script src="/assets/js/admin/admin-app.js"></script>

</body>
</html>
