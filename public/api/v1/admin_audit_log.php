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

use AgVote\Repository\AuditEventRepository;

api_require_role('admin');
api_request('GET');

try {

$tenantId = api_current_tenant_id();
$limit    = min(200, max(1, (int)($_GET['limit'] ?? 100)));
$offset   = max(0, (int)($_GET['offset'] ?? 0));
$action   = trim((string)($_GET['action'] ?? ''));
$q        = trim((string)($_GET['q'] ?? ''));

$repo = new AuditEventRepository();

$total  = $repo->countAdminEvents($tenantId, $action ?: null, $q ?: null);
$events = $repo->searchAdminEvents($tenantId, $action ?: null, $q ?: null, $limit, $offset);

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
$distinctActions = $repo->listDistinctAdminActions($tenantId);
foreach ($distinctActions as $row) {
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

} catch (Throwable $e) {
    error_log('Error in admin_audit_log.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => $e->getMessage()]);
}
