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

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
api_require_role('admin');

$validSystemRoles = ['admin', 'operator', 'auditor', 'viewer'];

// ─── GET : lister les utilisateurs ───────────────────────────────
if ($method === 'GET') {
    api_request('GET');

    $roleFilter = trim((string)($_GET['role'] ?? ''));
    $params = [api_current_tenant_id()];
    $where = "WHERE u.tenant_id = ?";

    if ($roleFilter !== '' && in_array($roleFilter, $validSystemRoles, true)) {
        $where .= " AND u.role = ?";
        $params[] = $roleFilter;
    }

    $rows = db_select_all(
        "SELECT u.id, u.email, u.name, u.role, u.is_active, u.created_at, u.updated_at,
                CASE WHEN u.api_key_hash IS NOT NULL THEN true ELSE false END AS has_api_key
         FROM users u
         {$where}
         ORDER BY u.role ASC, u.name ASC",
        $params
    );

    // Enrichir avec les rôles de séance actifs
    foreach ($rows as &$row) {
        $meetingRoles = db_select_all(
            "SELECT mr.role, mr.meeting_id, m.title AS meeting_title
             FROM meeting_roles mr
             JOIN meetings m ON m.id = mr.meeting_id
             WHERE mr.user_id = ? AND mr.tenant_id = ? AND mr.revoked_at IS NULL
             ORDER BY mr.assigned_at DESC",
            [$row['id'], api_current_tenant_id()]
        );
        $row['meeting_roles'] = $meetingRoles;
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
        db_execute(
            "UPDATE users SET api_key_hash = :h, updated_at = NOW() WHERE tenant_id = :t AND id = :id",
            [':h' => $hash, ':t' => api_current_tenant_id(), ':id' => $userId]
        );
        audit_log('admin.user.key_rotated', 'user', $userId, []);
        api_ok(['rotated' => true, 'api_key' => $apiKey, 'user_id' => $userId]);
    }

    // ── Révoquer la clé API ──
    if ($action === 'revoke_key') {
        $userId = api_require_uuid($in, 'user_id');
        db_execute(
            "UPDATE users SET api_key_hash = NULL, updated_at = NOW() WHERE tenant_id = :t AND id = :id",
            [':t' => api_current_tenant_id(), ':id' => $userId]
        );
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
        db_execute(
            "UPDATE users SET is_active = :a, updated_at = NOW() WHERE tenant_id = :t AND id = :id",
            [':a' => $active ? 'true' : 'false', ':t' => api_current_tenant_id(), ':id' => $userId]
        );
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

        $setClauses = ["email = :e", "name = :n", "updated_at = NOW()"];
        $params = [':e' => $email, ':n' => $name, ':t' => api_current_tenant_id(), ':id' => $userId];

        if ($role !== '') {
            $setClauses[] = "role = :r";
            $params[':r'] = $role;
        }

        $sql = "UPDATE users SET " . implode(', ', $setClauses) . " WHERE tenant_id = :t AND id = :id";
        db_execute($sql, $params);

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
        $existing = db_scalar(
            "SELECT id FROM users WHERE tenant_id = ? AND email = ?",
            [api_current_tenant_id(), $email]
        );
        if ($existing) {
            api_fail('email_exists', 409, ['detail' => "Un utilisateur avec l'email '$email' existe déjà."]);
        }

        $id = db_scalar("SELECT gen_random_uuid()");
        $apiKey = bin2hex(random_bytes(16));
        $hash = AuthService::hashKey($apiKey);

        db_execute(
            "INSERT INTO users (id, tenant_id, email, name, role, api_key_hash, is_active, created_at, updated_at)
             VALUES (:id, :t, :e, :n, :r, :h, true, NOW(), NOW())",
            [':id' => $id, ':t' => api_current_tenant_id(), ':e' => $email, ':n' => $name, ':r' => $role, ':h' => $hash]
        );

        audit_log('admin.user.created', 'user', $id, ['email' => $email, 'role' => $role]);
        api_ok(['saved' => true, 'user_id' => $id, 'api_key' => $apiKey]);
    }

    api_fail('unknown_action', 400, ['detail' => "Action '$action' inconnue."]);
}

api_fail('method_not_allowed', 405);
