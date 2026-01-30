<?php
/**
 * /api/v1/admin_users.php — CRUD utilisateurs
 *
 * GET              → Liste tous les utilisateurs
 * POST             → Créer un utilisateur
 * POST action=update  → Modifier un utilisateur
 * POST action=toggle  → Activer/désactiver
 * POST action=delete  → Désactiver (soft delete)
 * POST action=rotate_key → Générer une nouvelle clé API
 */
require __DIR__ . '/../../../app/api.php';
require_once __DIR__ . '/../../../app/services/AuthService.php';

use AgVote\Repository\UserRepository;

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
api_require_role('admin');

$validSystemRoles = ['admin', 'operator', 'auditor', 'viewer'];

$userRepo = new UserRepository();

// ─── GET : lister les utilisateurs ───────────────────────────────
if ($method === 'GET') {
    api_request('GET');

    $roleFilter = trim((string)($_GET['role'] ?? ''));
    $filterValue = ($roleFilter !== '' && in_array($roleFilter, $validSystemRoles, true)) ? $roleFilter : null;

    $rows = $userRepo->listByTenant(api_current_tenant_id(), $filterValue);

    // Enrichir avec les rôles de séance actifs
    foreach ($rows as &$row) {
        $row['meeting_roles'] = $userRepo->listActiveMeetingRolesForUser($row['id'], api_current_tenant_id());
    }
    unset($row);

    api_ok([
        'items' => $rows,
        'system_roles' => AuthMiddleware::getSystemRoleLabels(),
        'meeting_roles' => AuthMiddleware::getMeetingRoleLabels(),
    ]);
}

// ─── POST : actions CRUD ─────────────────────────────────────────
if ($method === 'POST') {
    $in = api_request('POST');
    $action = trim((string)($in['action'] ?? 'create'));

    // ── Générer une clé API ──
    if ($action === 'rotate_key') {
        $userId = api_require_uuid($in, 'user_id');
        $apiKey = bin2hex(random_bytes(16));
        $hash = AuthService::hashKey($apiKey);
        $userRepo->rotateApiKey(api_current_tenant_id(), $userId, $hash);
        audit_log('admin.user.key_rotated', 'user', $userId, []);
        api_ok(['rotated' => true, 'api_key' => $apiKey, 'user_id' => $userId]);
    }

    // ── Révoquer la clé API ──
    if ($action === 'revoke_key') {
        $userId = api_require_uuid($in, 'user_id');
        $userRepo->revokeApiKey(api_current_tenant_id(), $userId);
        audit_log('admin.user.key_revoked', 'user', $userId, []);
        api_ok(['revoked' => true, 'user_id' => $userId]);
    }

    // ── Activer / Désactiver ──
    if ($action === 'toggle') {
        $userId = api_require_uuid($in, 'user_id');

        // Protection : l'admin ne peut pas se désactiver lui-même
        $currentUserId = api_current_user_id();
        if ($userId === $currentUserId) {
            api_fail('cannot_toggle_self', 400, ['detail' => 'Vous ne pouvez pas vous désactiver vous-même.']);
        }

        $active = (int)($in['is_active'] ?? 1) ? true : false;
        $userRepo->toggleActive(api_current_tenant_id(), $userId, $active);
        audit_log('admin.user.toggled', 'user', $userId, ['is_active' => $active]);
        api_ok(['saved' => true, 'user_id' => $userId, 'is_active' => $active]);
    }

    // ── Modifier un utilisateur ──
    if ($action === 'update') {
        $userId = api_require_uuid($in, 'user_id');
        $email = strtolower(trim((string)($in['email'] ?? '')));
        $name  = trim((string)($in['name'] ?? ''));
        $role  = trim((string)($in['role'] ?? ''));

        if ($email === '' || $name === '') {
            api_fail('missing_fields', 400, ['detail' => 'email et name sont requis.']);
        }
        if ($role !== '' && !in_array($role, $validSystemRoles, true)) {
            api_fail('invalid_role', 400, [
                'detail' => "Rôle système invalide : '$role'",
                'valid' => $validSystemRoles,
            ]);
        }

        // Protection : l'admin ne peut pas changer son propre rôle
        $currentUserId = api_current_user_id();
        if ($userId === $currentUserId && $role !== '' && $role !== 'admin') {
            api_fail('cannot_demote_self', 400, ['detail' => 'Vous ne pouvez pas changer votre propre rôle.']);
        }

        $userRepo->updateUser(api_current_tenant_id(), $userId, $email, $name, $role !== '' ? $role : null);

        audit_log('admin.user.updated', 'user', $userId, ['email' => $email, 'role' => $role]);
        api_ok(['saved' => true, 'user_id' => $userId]);
    }

    // ── Créer un utilisateur ──
    if ($action === 'create') {
        $email = strtolower(trim((string)($in['email'] ?? '')));
        $name  = trim((string)($in['name'] ?? ''));
        $role  = trim((string)($in['role'] ?? 'viewer'));

        if ($email === '' || $name === '') {
            api_fail('missing_fields', 400, ['detail' => 'email et name sont requis.']);
        }
        if (!in_array($role, $validSystemRoles, true)) {
            api_fail('invalid_role', 400, [
                'detail' => "Rôle système invalide : '$role'",
                'valid' => $validSystemRoles,
            ]);
        }

        // Vérifier email unique
        $existing = $userRepo->findIdByEmail(api_current_tenant_id(), $email);
        if ($existing) {
            api_fail('email_exists', 409, ['detail' => "Un utilisateur avec l'email '$email' existe déjà."]);
        }

        $id = $userRepo->newUuid();
        $apiKey = bin2hex(random_bytes(16));
        $hash = AuthService::hashKey($apiKey);

        $userRepo->createUser($id, api_current_tenant_id(), $email, $name, $role, $hash);

        audit_log('admin.user.created', 'user', $id, ['email' => $email, 'role' => $role]);
        api_ok(['saved' => true, 'user_id' => $id, 'api_key' => $apiKey]);
    }

    api_fail('unknown_action', 400, ['detail' => "Action '$action' inconnue."]);
}

api_fail('method_not_allowed', 405);
