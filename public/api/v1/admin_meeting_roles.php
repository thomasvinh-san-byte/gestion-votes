<?php
/**
 * /api/v1/admin_meeting_roles.php — Gestion des rôles de séance
 *
 * GET  ?meeting_id=uuid   → Liste les rôles assignés pour une séance
 * POST action=assign      → Assigner un rôle de séance à un utilisateur
 * POST action=revoke      → Révoquer un rôle de séance
 */
require __DIR__ . '/../../../app/api.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
api_require_role(['admin', 'operator']);

$validMeetingRoles = ['president', 'assessor', 'voter'];

// ─── GET : lister les rôles d'une séance ────────────────────────
if ($method === 'GET') {
    api_request('GET');

    $meetingId = trim((string)($_GET['meeting_id'] ?? ''));

    // Si meeting_id fourni, filtrer par séance
    if ($meetingId !== '' && api_is_uuid($meetingId)) {
        $rows = db_select_all(
            "SELECT mr.id, mr.user_id, mr.role, mr.assigned_at, mr.revoked_at,
                    u.name AS user_name, u.email AS user_email, u.role AS system_role,
                    a.name AS assigned_by_name
             FROM meeting_roles mr
             JOIN users u ON u.id = mr.user_id
             LEFT JOIN users a ON a.id = mr.assigned_by
             WHERE mr.tenant_id = ? AND mr.meeting_id = ? AND mr.revoked_at IS NULL
             ORDER BY mr.role ASC, u.name ASC",
            [api_current_tenant_id(), $meetingId]
        );

        api_ok([
            'items' => $rows,
            'meeting_id' => $meetingId,
            'meeting_roles' => AuthMiddleware::getMeetingRoleLabels(),
        ]);
    }

    // Sinon, résumé par séance (toutes les séances avec des rôles assignés)
    $rows = db_select_all(
        "SELECT m.id AS meeting_id, m.title, m.status,
                json_agg(json_build_object(
                    'user_id', mr.user_id,
                    'user_name', u.name,
                    'role', mr.role
                ) ORDER BY mr.role, u.name) AS roles
         FROM meeting_roles mr
         JOIN meetings m ON m.id = mr.meeting_id
         JOIN users u ON u.id = mr.user_id
         WHERE mr.tenant_id = ? AND mr.revoked_at IS NULL
         GROUP BY m.id, m.title, m.status
         ORDER BY m.title",
        [api_current_tenant_id()]
    );

    api_ok(['items' => $rows]);
}

// ─── POST : assigner / révoquer ─────────────────────────────────
if ($method === 'POST') {
    $in = api_request('POST');
    $action = trim((string)($in['action'] ?? 'assign'));

    // ── Assigner un rôle ──
    if ($action === 'assign') {
        $meetingId = api_require_uuid($in, 'meeting_id');
        $userId    = api_require_uuid($in, 'user_id');
        $role      = trim((string)($in['role'] ?? ''));

        if (!in_array($role, $validMeetingRoles, true)) {
            api_fail('invalid_meeting_role', 400, [
                'detail' => "Rôle de séance invalide : '$role'",
                'valid' => $validMeetingRoles,
            ]);
        }

        // Vérifier que la séance existe
        $meeting = db_select_one(
            "SELECT id, status FROM meetings WHERE id = ? AND tenant_id = ?",
            [$meetingId, api_current_tenant_id()]
        );
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }

        // Vérifier que l'utilisateur existe
        $user = db_select_one(
            "SELECT id, name FROM users WHERE id = ? AND tenant_id = ? AND is_active = true",
            [$userId, api_current_tenant_id()]
        );
        if (!$user) {
            api_fail('user_not_found', 404);
        }

        // Contrainte président unique par séance
        if ($role === 'president') {
            $existingPres = db_scalar(
                "SELECT user_id FROM meeting_roles
                 WHERE tenant_id = ? AND meeting_id = ? AND role = 'president' AND revoked_at IS NULL",
                [api_current_tenant_id(), $meetingId]
            );
            if ($existingPres && $existingPres !== $userId) {
                // Révoquer l'ancien président
                db_execute(
                    "UPDATE meeting_roles SET revoked_at = NOW()
                     WHERE tenant_id = ? AND meeting_id = ? AND role = 'president' AND revoked_at IS NULL",
                    [api_current_tenant_id(), $meetingId]
                );
            }
        }

        // Insérer ou réactiver (ON CONFLICT = UNIQUE(tenant_id, meeting_id, user_id, role))
        db_execute(
            "INSERT INTO meeting_roles (tenant_id, meeting_id, user_id, role, assigned_by, assigned_at)
             VALUES (:t, :m, :u, :r, :by, NOW())
             ON CONFLICT (tenant_id, meeting_id, user_id, role) DO UPDATE
             SET revoked_at = NULL, assigned_by = :by, assigned_at = NOW()",
            [
                ':t' => api_current_tenant_id(),
                ':m' => $meetingId,
                ':u' => $userId,
                ':r' => $role,
                ':by' => api_current_user_id(),
            ]
        );

        audit_log('admin.meeting_role.assigned', 'meeting', $meetingId, [
            'user_id' => $userId,
            'user_name' => $user['name'],
            'role' => $role,
        ], $meetingId);

        api_ok(['assigned' => true, 'meeting_id' => $meetingId, 'user_id' => $userId, 'role' => $role]);
    }

    // ── Révoquer un rôle ──
    if ($action === 'revoke') {
        $meetingId = api_require_uuid($in, 'meeting_id');
        $userId    = api_require_uuid($in, 'user_id');
        $role      = trim((string)($in['role'] ?? ''));

        if ($role !== '' && !in_array($role, $validMeetingRoles, true)) {
            api_fail('invalid_meeting_role', 400);
        }

        $where = "tenant_id = ? AND meeting_id = ? AND user_id = ? AND revoked_at IS NULL";
        $params = [api_current_tenant_id(), $meetingId, $userId];

        if ($role !== '') {
            $where .= " AND role = ?";
            $params[] = $role;
        }

        db_execute(
            "UPDATE meeting_roles SET revoked_at = NOW() WHERE $where",
            $params
        );

        audit_log('admin.meeting_role.revoked', 'meeting', $meetingId, [
            'user_id' => $userId,
            'role' => $role ?: 'all',
        ], $meetingId);

        api_ok(['revoked' => true, 'meeting_id' => $meetingId, 'user_id' => $userId]);
    }

    api_fail('unknown_action', 400);
}

api_fail('method_not_allowed', 405);
