<?php
declare(strict_types=1);

/**
 * admin_audit_log.php - Journal d'audit des actions admin
 *
 * GET /api/v1/admin_audit_log.php?limit=100&offset=0&action=&q=
 *
 * Retourne les événements d'audit admin (action LIKE 'admin.%')
 * avec pagination et recherche optionnelle.
 */

require __DIR__ . '/../../../app/api.php';

api_require_role('admin');
api_request('GET');

$tenantId = api_current_tenant_id();
$limit    = min(200, max(1, (int)($_GET['limit'] ?? 100)));
$offset   = max(0, (int)($_GET['offset'] ?? 0));
$action   = trim((string)($_GET['action'] ?? ''));
$q        = trim((string)($_GET['q'] ?? ''));

// Build query
$where  = "WHERE tenant_id = ?";
$params = [$tenantId];

// Only admin-prefixed actions
$where .= " AND action LIKE 'admin.%'";

// Optional action filter
if ($action !== '') {
    $where .= " AND action = ?";
    $params[] = $action;
}

// Optional text search
if ($q !== '') {
    $where .= " AND (action ILIKE ? OR CAST(payload AS text) ILIKE ? OR actor_role ILIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Count total
$stmtCount = db()->prepare("SELECT COUNT(*) FROM audit_events $where");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();

// Fetch events
$sql = "SELECT id, action, resource_type, resource_id, actor_user_id, actor_role,
               payload, ip_address, created_at
        FROM audit_events
        $where
        ORDER BY created_at DESC
        LIMIT $limit OFFSET $offset";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll(\PDO::FETCH_ASSOC);

// Action labels for admin events
$actionLabels = [
    'admin.user.created'          => 'Utilisateur créé',
    'admin.user.updated'          => 'Utilisateur modifié',
    'admin.user.deleted'          => 'Utilisateur supprimé',
    'admin.user.toggled'          => 'Utilisateur activé/désactivé',
    'admin.user.password_set'     => 'Mot de passe défini',
    'admin.user.key_rotated'      => 'Clé API régénérée',
    'admin.user.key_revoked'      => 'Clé API révoquée',
    'admin.meeting_role.assigned' => 'Rôle de séance assigné',
    'admin.meeting_role.revoked'  => 'Rôle de séance révoqué',
    'admin_quorum_policy_saved'   => 'Politique quorum enregistrée',
    'admin_quorum_policy_deleted' => 'Politique quorum supprimée',
    'admin_vote_policy_saved'     => 'Politique de vote enregistrée',
    'admin_vote_policy_deleted'   => 'Politique de vote supprimée',
];

// Format events
$formatted = [];
foreach ($events as $e) {
    $payload = [];
    if (!empty($e['payload'])) {
        $payload = is_string($e['payload'])
            ? (json_decode($e['payload'], true) ?? [])
            : (array)$e['payload'];
    }

    $actionLabel = $actionLabels[$e['action']] ?? ucfirst(str_replace(['admin.', '_'], ['', ' '], $e['action']));

    // Build detail string
    $detail = '';
    if (isset($payload['email'])) $detail .= $payload['email'];
    if (isset($payload['role'])) $detail .= ($detail ? ' — ' : '') . $payload['role'];
    if (isset($payload['user_name'])) $detail .= ($detail ? ' — ' : '') . $payload['user_name'];
    if (isset($payload['is_active'])) $detail .= ($detail ? ' — ' : '') . ($payload['is_active'] ? 'activé' : 'désactivé');
    if (isset($payload['name'])) $detail .= ($detail ? ' — ' : '') . $payload['name'];

    $formatted[] = [
        'id'           => $e['id'],
        'timestamp'    => $e['created_at'],
        'action'       => $e['action'],
        'action_label' => $actionLabel,
        'resource_type' => $e['resource_type'],
        'resource_id'  => $e['resource_id'],
        'actor_role'   => $e['actor_role'],
        'actor_user_id' => $e['actor_user_id'],
        'ip_address'   => $e['ip_address'],
        'detail'       => $detail,
        'payload'      => $payload,
    ];
}

// Distinct action types for filter dropdown
$actionTypes = [];
$stmtActions = db()->prepare(
    "SELECT DISTINCT action FROM audit_events WHERE tenant_id = ? AND action LIKE 'admin.%' ORDER BY action"
);
$stmtActions->execute([$tenantId]);
foreach ($stmtActions->fetchAll(\PDO::FETCH_ASSOC) as $row) {
    $actionTypes[] = [
        'value' => $row['action'],
        'label' => $actionLabels[$row['action']] ?? $row['action'],
    ];
}

api_ok([
    'total'        => $total,
    'limit'        => $limit,
    'offset'       => $offset,
    'events'       => $formatted,
    'action_types' => $actionTypes,
]);
