<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
AuthMiddleware::requireRole('admin');

header('Content-Type: text/html; charset=utf-8');

// ---------------------------------------------------------------------------
// Tenant context
// ---------------------------------------------------------------------------
$tenantId = AuthMiddleware::getCurrentTenantId();

// ---------------------------------------------------------------------------
// Dashboard statistics
// ---------------------------------------------------------------------------

try {
    $totalUsers = (int) db_scalar(
        'SELECT COUNT(*) FROM users WHERE tenant_id = :tid',
        [':tid' => $tenantId]
    );
} catch (\Throwable $e) {
    error_log('dashboard: totalUsers query failed: ' . $e->getMessage());
    $totalUsers = 0;
}

try {
    $activeMeetings = (int) db_scalar(
        "SELECT COUNT(*) FROM meetings WHERE tenant_id = :tid AND status IN ('draft','scheduled','live')",
        [':tid' => $tenantId]
    );
} catch (\Throwable $e) {
    error_log('dashboard: activeMeetings query failed: ' . $e->getMessage());
    $activeMeetings = 0;
}

try {
    $totalMembers = (int) db_scalar(
        'SELECT COUNT(*) FROM members WHERE tenant_id = :tid AND deleted_at IS NULL',
        [':tid' => $tenantId]
    );
} catch (\Throwable $e) {
    error_log('dashboard: totalMembers query failed: ' . $e->getMessage());
    $totalMembers = 0;
}

try {
    $recentAuditCount = (int) db_scalar(
        "SELECT COUNT(*) FROM audit_events WHERE tenant_id = :tid AND created_at > NOW() - INTERVAL '24 hours'",
        [':tid' => $tenantId]
    );
} catch (\Throwable $e) {
    error_log('dashboard: recentAuditCount query failed: ' . $e->getMessage());
    $recentAuditCount = 0;
}

try {
    $authFailures = (int) db_scalar(
        "SELECT COUNT(*) FROM auth_failures WHERE created_at > NOW() - INTERVAL '15 minutes'"
    );
} catch (\Throwable $e) {
    error_log('dashboard: authFailures query failed: ' . $e->getMessage());
    $authFailures = 0;
}

try {
    $systemAlerts = (int) db_scalar(
        "SELECT COUNT(*) FROM system_alerts WHERE created_at > NOW() - INTERVAL '24 hours'"
    );
} catch (\Throwable $e) {
    error_log('dashboard: systemAlerts query failed: ' . $e->getMessage());
    $systemAlerts = 0;
}

// ---------------------------------------------------------------------------
// Recent audit events (last 10)
// ---------------------------------------------------------------------------
try {
    $recentEvents = db_all(
        "SELECT created_at, action, resource_type, actor_role
           FROM audit_events
          WHERE tenant_id = :tid
          ORDER BY created_at DESC
          LIMIT 10",
        [':tid' => $tenantId]
    );
} catch (\Throwable $e) {
    error_log('dashboard: recentEvents query failed: ' . $e->getMessage());
    $recentEvents = [];
}

// ---------------------------------------------------------------------------
// Active meetings list
// ---------------------------------------------------------------------------
try {
    $activeMeetingsList = db_all(
        "SELECT title, status, created_at
           FROM meetings
          WHERE tenant_id = :tid
            AND status IN ('draft','scheduled','live')
          ORDER BY created_at DESC",
        [':tid' => $tenantId]
    );
} catch (\Throwable $e) {
    error_log('dashboard: activeMeetingsList query failed: ' . $e->getMessage());
    $activeMeetingsList = [];
}

// ---------------------------------------------------------------------------
// Helper: map meeting status to badge class
// ---------------------------------------------------------------------------
function statusBadgeClass(string $status): string
{
    return match ($status) {
        'live'      => 'badge badge-danger badge-dot',
        'scheduled' => 'badge badge-warning',
        'draft'     => 'badge badge-neutral',
        default     => 'badge badge-neutral',
    };
}
?>

<!-- Dashboard Fragment -->
<div id="dashboard-fragment">

  <!-- Page header -->
  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">Tableau de bord</h1>
      <p class="admin-page-subtitle">Vue d'ensemble du systeme</p>
    </div>
  </div>

  <!-- Stats grid -->
  <div class="admin-stats-grid">

    <!-- Total users -->
    <div class="admin-stat-card">
      <div class="admin-stat-icon primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
          <path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </div>
      <div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) $totalUsers, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="admin-stat-label">Utilisateurs</div>
      </div>
    </div>

    <!-- Active meetings -->
    <div class="admin-stat-card">
      <div class="admin-stat-icon success">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/>
          <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
      </div>
      <div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) $activeMeetings, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="admin-stat-label">Seances actives</div>
      </div>
    </div>

    <!-- Total members -->
    <div class="admin-stat-card">
      <div class="admin-stat-icon info">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </div>
      <div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) $totalMembers, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="admin-stat-label">Membres</div>
      </div>
    </div>

    <!-- Audit events (24 h) -->
    <div class="admin-stat-card">
      <div class="admin-stat-icon warning">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/>
          <line x1="16" y1="17" x2="8" y2="17"/>
        </svg>
      </div>
      <div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) $recentAuditCount, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="admin-stat-label">Evenements (24 h)</div>
      </div>
    </div>

    <!-- Auth failures (15 min) -->
    <div class="admin-stat-card">
      <div class="admin-stat-icon danger">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
      </div>
      <div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) $authFailures, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="admin-stat-label">Echecs auth (15 min)</div>
      </div>
    </div>

    <!-- System alerts (24 h) -->
    <div class="admin-stat-card">
      <div class="admin-stat-icon warning">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
      </div>
      <div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) $systemAlerts, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="admin-stat-label">Alertes systeme (24 h)</div>
      </div>
    </div>

  </div><!-- /.admin-stats-grid -->

  <!-- Two-column cards: Recent activity + Active meetings -->
  <div class="grid grid-cols-2 gap-4">

    <!-- Recent activity -->
    <div class="card"
         id="recent-activity"
         hx-get="/admin/fragments/dashboard.php"
         hx-trigger="every 30s"
         hx-swap="outerHTML"
         hx-select="#recent-activity">
      <div class="card-header">
        <h3 class="card-title">Activite recente</h3>
      </div>
      <div class="card-body">
        <?php if (empty($recentEvents)): ?>
          <p class="text-muted text-sm">Aucune donnee</p>
        <?php else: ?>
          <div class="table-wrapper">
            <table class="table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Action</th>
                  <th>Ressource</th>
                  <th>Role</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentEvents as $event): ?>
                <tr>
                  <td class="text-sm"><?= htmlspecialchars((string) ($event['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string) ($event['action'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string) ($event['resource_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <span class="badge badge-neutral"><?= htmlspecialchars((string) ($event['actor_role'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Active meetings -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Seances actives</h3>
      </div>
      <div class="card-body">
        <?php if (empty($activeMeetingsList)): ?>
          <p class="text-muted text-sm">Aucune donnee</p>
        <?php else: ?>
          <div class="table-wrapper">
            <table class="table">
              <thead>
                <tr>
                  <th>Titre</th>
                  <th>Statut</th>
                  <th>Cree le</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($activeMeetingsList as $meeting): ?>
                <tr>
                  <td><?= htmlspecialchars((string) ($meeting['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <span class="<?= statusBadgeClass((string) ($meeting['status'] ?? '')) ?>">
                      <?= htmlspecialchars((string) ($meeting['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  </td>
                  <td class="text-sm"><?= htmlspecialchars((string) ($meeting['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /.grid -->

</div><!-- /#dashboard-fragment -->
