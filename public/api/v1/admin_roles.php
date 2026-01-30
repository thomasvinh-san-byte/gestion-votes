<?php
/**
 * GET /api/v1/admin_roles.php
 *
 * Retourne la matrice complète RBAC :
 * - Rôles système et séance avec libellés
 * - Permissions par rôle
 * - Transitions d'état autorisées
 * - Comptage utilisateurs par rôle
 */
require __DIR__ . '/../../../app/api.php';

api_request('GET');
api_require_role('admin');

// Rôles
$systemRoleLabels = AuthMiddleware::getSystemRoleLabels();
$meetingRoleLabels = AuthMiddleware::getMeetingRoleLabels();
$statusLabels = AuthMiddleware::getMeetingStatusLabels();

// Permissions depuis la DB
$permissions = db_select_all(
    "SELECT role, permission, description FROM role_permissions ORDER BY role, permission"
);

// Transitions
$transitions = db_select_all(
    "SELECT from_status, to_status, required_role, description FROM meeting_state_transitions ORDER BY from_status, to_status"
);

// Users par rôle système
$usersByRole = db_select_all(
    "SELECT role, COUNT(*) as count FROM users WHERE tenant_id = ? AND is_active = true GROUP BY role ORDER BY role",
    [DEFAULT_TENANT_ID]
);

// Rôles de séance actifs (combien d'assignations actives par rôle)
$meetingRoleCounts = db_select_all(
    "SELECT role, COUNT(DISTINCT user_id) as users, COUNT(DISTINCT meeting_id) as meetings
     FROM meeting_roles WHERE tenant_id = ? AND revoked_at IS NULL GROUP BY role ORDER BY role",
    [DEFAULT_TENANT_ID]
);

// Matrice permissions groupées par rôle
$permByRole = [];
foreach ($permissions as $p) {
    $permByRole[$p['role']][] = [
        'permission' => $p['permission'],
        'description' => $p['description'],
    ];
}

api_ok([
    'system_roles' => $systemRoleLabels,
    'meeting_roles' => $meetingRoleLabels,
    'statuses' => $statusLabels,
    'permissions_by_role' => $permByRole,
    'state_transitions' => $transitions,
    'users_by_system_role' => $usersByRole,
    'meeting_role_counts' => $meetingRoleCounts,
]);
