<?php
/**
 * GET /api/v1/admin_roles.php
 *
 * Retourne la liste des rôles, permissions, et transitions d'état.
 * Utilisé par l'interface admin pour afficher la matrice RBAC.
 */
require __DIR__ . '/../../../app/api.php';

api_request('GET');
api_require_role('admin');

// Rôles et libellés
$roleLabels = AuthMiddleware::getRoleLabels();
$statusLabels = AuthMiddleware::getMeetingStatusLabels();

// Permissions depuis la DB (table de référence)
$permissions = db_select_all(
    "SELECT role, permission, description FROM role_permissions ORDER BY role, permission"
);

// Transitions depuis la DB
$transitions = db_select_all(
    "SELECT from_status, to_status, required_role, description FROM meeting_state_transitions ORDER BY from_status, to_status"
);

// Compter les utilisateurs par rôle
$usersByRole = db_select_all(
    "SELECT role, COUNT(*) as count FROM users WHERE tenant_id = ? AND is_active = true GROUP BY role ORDER BY role",
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
    'roles' => $roleLabels,
    'statuses' => $statusLabels,
    'permissions_by_role' => $permByRole,
    'state_transitions' => $transitions,
    'users_by_role' => $usersByRole,
]);
