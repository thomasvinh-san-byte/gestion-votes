<?php
/**
 * /api/v1/admin_meeting_roles.php — Gestion des rôles de séance
 *
 * GET  ?meeting_id=uuid   → Liste les rôles assignés pour une séance
 * POST action=assign      → Assigner un rôle de séance à un utilisateur
 * POST action=revoke      → Révoquer un rôle de séance
 */
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\UserRepository;
use AgVote\Repository\MeetingRepository;

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
api_require_role(['admin', 'operator']);

$validMeetingRoles = ['president', 'assessor', 'voter'];

try {
    $userRepo    = new UserRepository();
    $meetingRepo = new MeetingRepository();

    // ─── GET : lister les rôles d'une séance ────────────────────────
    if ($method === 'GET') {
        api_request('GET');

        $meetingId = trim((string)($_GET['meeting_id'] ?? ''));

        // Si meeting_id fourni, filtrer par séance
        if ($meetingId !== '' && api_is_uuid($meetingId)) {
            $rows = $userRepo->listMeetingRolesForMeeting(api_current_tenant_id(), $meetingId);

            api_ok([
                'items' => $rows,
                'meeting_id' => $meetingId,
                'meeting_roles' => AuthMiddleware::getMeetingRoleLabels(),
            ]);
        }

        // Sinon, résumé par séance (toutes les séances avec des rôles assignés)
        $rows = $userRepo->listMeetingRolesSummary(api_current_tenant_id());

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
            $meeting = $meetingRepo->findByIdForTenant($meetingId, api_current_tenant_id());
            if (!$meeting) {
                api_fail('meeting_not_found', 404);
            }

            // Vérifier que l'utilisateur existe
            $user = $userRepo->findActiveById($userId, api_current_tenant_id());
            if (!$user) {
                api_fail('user_not_found', 404);
            }

            // President assignment requires admin role (prevent operator self-escalation)
            if ($role === 'president') {
                if (api_current_role() !== 'admin') {
                    api_fail('admin_required_for_president', 403, [
                        'detail' => 'Seul un administrateur peut assigner le rôle de président.',
                    ]);
                }
                // Contrainte président unique par séance
                $existingPres = $userRepo->findExistingPresident(api_current_tenant_id(), $meetingId);
                if ($existingPres && $existingPres !== $userId) {
                    // Révoquer l'ancien président
                    $userRepo->revokePresidentRole(api_current_tenant_id(), $meetingId);
                }
            }

            // Insérer ou réactiver (ON CONFLICT = UNIQUE(tenant_id, meeting_id, user_id, role))
            $userRepo->assignMeetingRole(
                api_current_tenant_id(),
                $meetingId,
                $userId,
                $role,
                api_current_user_id()
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

            $userRepo->revokeMeetingRole(
                api_current_tenant_id(),
                $meetingId,
                $userId,
                $role !== '' ? $role : null
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
} catch (Throwable $e) {
    error_log('Error in admin_meeting_roles.php: ' . $e->getMessage());
    api_fail('internal_error', 500);
}
