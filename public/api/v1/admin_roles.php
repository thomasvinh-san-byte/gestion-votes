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

use AgVote\Repository\UserRepository;
use AgVote\Repository\MeetingRepository;

api_request('GET');
api_require_role('admin');

$userRepo    = new UserRepository();
$meetingRepo = new MeetingRepository();

// Rôles
$systemRoleLabels  = AuthMiddleware::getSystemRoleLabels();
$meetingRoleLabels = AuthMiddleware::getMeetingRoleLabels();
$statusLabels      = AuthMiddleware::getMeetingStatusLabels();

// Permissions depuis la DB
$permissions = $userRepo->listRolePermissions();

// Transitions
$transitions = $meetingRepo->listStateTransitions();

// Users par rôle système
$usersByRole = $userRepo->countBySystemRole(api_current_tenant_id());

// Rôles de séance actifs (combien d'assignations actives par rôle)
$meetingRoleCounts = $userRepo->countByMeetingRole(api_current_tenant_id());

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
