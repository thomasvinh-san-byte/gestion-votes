<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Security\AuthMiddleware;
use AgVote\Repository\AuditEventRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MeetingStatsRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\UserRepository;
use AgVote\Repository\VoteTokenRepository;
use Throwable;

/**
 * Consolidates 5 admin endpoints.
 *
 * Shared pattern: admin role, UserRepository, AuthMiddleware labels.
 */
final class AdminController extends AbstractController {
    public function users(): void {
        $method = api_method();

        // GET: operators can list users (needed for meeting role assignment)
        // POST: admin only
        if ($method === 'GET') {
        }

        $validSystemRoles = ['admin', 'operator', 'auditor', 'viewer'];
        $userRepo = new UserRepository();

        if ($method === 'GET') {
            api_request('GET');

            $roleFilter = api_query('role');
            $filterValue = ($roleFilter !== '' && in_array($roleFilter, $validSystemRoles, true)) ? $roleFilter : null;

            $rows = $userRepo->listByTenant(api_current_tenant_id(), $filterValue);

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

        if ($method === 'POST') {
            $in = api_request('POST');
            $action = trim((string) ($in['action'] ?? 'create'));

            if ($action === 'set_password') {
                $userId = api_require_uuid($in, 'user_id');
                $password = (string) ($in['password'] ?? '');

                if (strlen($password) < 8) {
                    api_fail('weak_password', 400, ['detail' => 'Le mot de passe doit contenir au moins 8 caractères.']);
                }

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $userRepo->setPasswordHash(api_current_tenant_id(), $userId, $hash);
                audit_log('admin.user.password_set', 'user', $userId, []);
                api_ok(['saved' => true, 'user_id' => $userId]);
            }

            if ($action === 'rotate_key') {
                $userId = api_require_uuid($in, 'user_id');
                $apiKey = bin2hex(random_bytes(16));
                $hash = AuthMiddleware::hashApiKey($apiKey);
                $userRepo->rotateApiKey(api_current_tenant_id(), $userId, $hash);
                audit_log('admin.user.key_rotated', 'user', $userId, []);
                api_ok(['rotated' => true, 'api_key' => $apiKey, 'user_id' => $userId]);
            }

            if ($action === 'revoke_key') {
                $userId = api_require_uuid($in, 'user_id');
                $userRepo->revokeApiKey(api_current_tenant_id(), $userId);
                audit_log('admin.user.key_revoked', 'user', $userId, []);
                api_ok(['revoked' => true, 'user_id' => $userId]);
            }

            if ($action === 'toggle') {
                $userId = api_require_uuid($in, 'user_id');

                $currentUserId = api_current_user_id();
                if ($userId === $currentUserId) {
                    api_fail('cannot_toggle_self', 400, ['detail' => 'Vous ne pouvez pas vous désactiver vous-même.']);
                }

                $active = (int) ($in['is_active'] ?? 1) ? true : false;
                $userRepo->toggleActive(api_current_tenant_id(), $userId, $active);
                audit_log('admin.user.toggled', 'user', $userId, ['is_active' => $active]);
                api_ok(['saved' => true, 'user_id' => $userId, 'is_active' => $active]);
            }

            if ($action === 'delete') {
                $userId = api_require_uuid($in, 'user_id');

                $currentUserId = api_current_user_id();
                if ($userId === $currentUserId) {
                    api_fail('cannot_delete_self', 400, ['detail' => 'Vous ne pouvez pas vous supprimer vous-même.']);
                }

                $userRepo->deleteUser(api_current_tenant_id(), $userId);
                audit_log('admin.user.deleted', 'user', $userId, []);
                api_ok(['deleted' => true, 'user_id' => $userId]);
            }

            if ($action === 'update') {
                $userId = api_require_uuid($in, 'user_id');
                $email = strtolower(trim((string) ($in['email'] ?? '')));
                $name = trim((string) ($in['name'] ?? ''));
                $role = trim((string) ($in['role'] ?? ''));

                if ($email === '' || $name === '') {
                    api_fail('missing_fields', 400, ['detail' => 'email et name sont requis.']);
                }
                if ($role !== '' && !in_array($role, $validSystemRoles, true)) {
                    api_fail('invalid_role', 400, [
                        'detail' => "Rôle système invalide : '{$role}'",
                        'valid' => $validSystemRoles,
                    ]);
                }

                $currentUserId = api_current_user_id();
                if ($userId === $currentUserId && $role !== '' && $role !== 'admin') {
                    api_fail('cannot_demote_self', 400, ['detail' => 'Vous ne pouvez pas changer votre propre rôle.']);
                }

                $userRepo->updateUser(api_current_tenant_id(), $userId, $email, $name, $role !== '' ? $role : null);
                audit_log('admin.user.updated', 'user', $userId, ['email' => $email, 'role' => $role]);
                api_ok(['saved' => true, 'user_id' => $userId]);
            }

            if ($action === 'create') {
                $email = strtolower(trim((string) ($in['email'] ?? '')));
                $name = trim((string) ($in['name'] ?? ''));
                $role = trim((string) ($in['role'] ?? 'viewer'));

                if ($email === '' || $name === '') {
                    api_fail('missing_fields', 400, ['detail' => 'email et name sont requis.']);
                }
                if (!in_array($role, $validSystemRoles, true)) {
                    api_fail('invalid_role', 400, [
                        'detail' => "Rôle système invalide : '{$role}'",
                        'valid' => $validSystemRoles,
                    ]);
                }

                $existing = $userRepo->findIdByEmail(api_current_tenant_id(), $email);
                if ($existing) {
                    api_fail('email_exists', 409, ['detail' => "Un utilisateur avec l'email '{$email}' existe déjà."]);
                }

                $password = trim((string) ($in['password'] ?? ''));
                if (strlen($password) < 8) {
                    api_fail('weak_password', 400, ['detail' => 'Le mot de passe doit contenir au moins 8 caractères.']);
                }

                $id = $userRepo->generateUuid();
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $userRepo->createUser($id, api_current_tenant_id(), $email, $name, $role, $passwordHash);
                audit_log('admin.user.created', 'user', $id, ['email' => $email, 'role' => $role]);
                api_ok(['saved' => true, 'user_id' => $id]);
            }

            api_fail('unknown_action', 400, ['detail' => "Action '{$action}' inconnue."]);
        }

        api_fail('method_not_allowed', 405);
    }

    public function roles(): void {
        api_request('GET');

        $userRepo = new UserRepository();
        $meetingRepo = new MeetingRepository();
        $statsRepo = new MeetingStatsRepository();

        $permissions = $userRepo->listRolePermissions();
        $transitions = $statsRepo->listStateTransitions();
        $usersByRole = $userRepo->countBySystemRole(api_current_tenant_id());
        $meetingRoleCounts = $userRepo->countByMeetingRole(api_current_tenant_id());

        $permByRole = [];
        foreach ($permissions as $p) {
            $permByRole[$p['role']][] = [
                'permission' => $p['permission'],
                'description' => $p['description'],
            ];
        }

        api_ok([
            'system_roles' => AuthMiddleware::getSystemRoleLabels(),
            'meeting_roles' => AuthMiddleware::getMeetingRoleLabels(),
            'statuses' => AuthMiddleware::getMeetingStatusLabels(),
            'permissions_by_role' => $permByRole,
            'state_transitions' => $transitions,
            'users_by_system_role' => $usersByRole,
            'meeting_role_counts' => $meetingRoleCounts,
        ]);
    }

    public function meetingRoles(): void {
        $method = api_method();
        $validMeetingRoles = ['president', 'assessor', 'voter'];
        $userRepo = new UserRepository();
        $meetingRepo = new MeetingRepository();

        if ($method === 'GET') {
            api_request('GET');

            $meetingId = api_query('meeting_id');

            if ($meetingId !== '' && api_is_uuid($meetingId)) {
                $rows = $userRepo->listMeetingRolesForMeeting(api_current_tenant_id(), $meetingId);
                api_ok([
                    'items' => $rows,
                    'meeting_id' => $meetingId,
                    'meeting_roles' => AuthMiddleware::getMeetingRoleLabels(),
                ]);
            }

            $rows = $userRepo->listMeetingRolesSummary(api_current_tenant_id());
            api_ok(['items' => $rows]);
        }

        if ($method === 'POST') {
            $in = api_request('POST');
            $action = trim((string) ($in['action'] ?? 'assign'));

            if ($action === 'assign') {
                $meetingId = api_require_uuid($in, 'meeting_id');
                $userId = api_require_uuid($in, 'user_id');
                $role = trim((string) ($in['role'] ?? ''));

                if (!in_array($role, $validMeetingRoles, true)) {
                    api_fail('invalid_meeting_role', 400, [
                        'detail' => "Rôle de séance invalide : '{$role}'",
                        'valid' => $validMeetingRoles,
                    ]);
                }

                $meeting = $meetingRepo->findByIdForTenant($meetingId, api_current_tenant_id());
                if (!$meeting) {
                    api_fail('meeting_not_found', 404);
                }

                $user = $userRepo->findActiveById($userId, api_current_tenant_id());
                if (!$user) {
                    api_fail('user_not_found', 404);
                }

                if ($role === 'president') {
                    if (api_current_role() !== 'admin') {
                        api_fail('admin_required_for_president', 403, [
                            'detail' => 'Seul un administrateur peut assigner le rôle de président.',
                        ]);
                    }
                    $existingPres = $userRepo->findExistingPresident(api_current_tenant_id(), $meetingId);
                    if ($existingPres && $existingPres !== $userId) {
                        $userRepo->revokePresidentRole(api_current_tenant_id(), $meetingId);
                    }
                }

                $userRepo->assignMeetingRole(
                    api_current_tenant_id(),
                    $meetingId,
                    $userId,
                    $role,
                    api_current_user_id(),
                );

                audit_log('admin.meeting_role.assigned', 'meeting', $meetingId, [
                    'user_id' => $userId,
                    'user_name' => $user['name'],
                    'role' => $role,
                ], $meetingId);

                api_ok(['assigned' => true, 'meeting_id' => $meetingId, 'user_id' => $userId, 'role' => $role]);
            }

            if ($action === 'revoke') {
                $meetingId = api_require_uuid($in, 'meeting_id');
                $userId = api_require_uuid($in, 'user_id');
                $role = trim((string) ($in['role'] ?? ''));

                if ($role !== '' && !in_array($role, $validMeetingRoles, true)) {
                    api_fail('invalid_meeting_role', 400);
                }

                $userRepo->revokeMeetingRole(
                    api_current_tenant_id(),
                    $meetingId,
                    $userId,
                    $role !== '' ? $role : null,
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
    }

    public function systemStatus(): void {
        api_request('GET');

        $userRepo = new UserRepository();
        $meetingRepo = new MeetingRepository();
        $motionRepo = new MotionRepository();
        $memberRepo = new MemberRepository();
        $voteTokenRepo = new VoteTokenRepository();

        $tenantId = api_current_tenant_id();
        $serverTime = date('c');

        $dbLat = $userRepo->dbPing();
        $active = $userRepo->dbActiveConnections();

        $path = __DIR__;
        try {
            $free = @disk_free_space($path);
            $total = @disk_total_space($path);
        } catch (Throwable $e) {
            if ($e instanceof \AgVote\Core\Http\ApiResponseException) {
                throw $e;
            }
            $free = null;
            $total = null;
        }

        $cntMeet = $meetingRepo->countForTenant($tenantId);
        $cntMot = $motionRepo->countAll($tenantId);
        $cntMembers = $memberRepo->countActive($tenantId);
        $cntLive = $meetingRepo->countLive($tenantId);
        $cntTok = $voteTokenRepo->countAll();
        $cntAud = $userRepo->countAuditEvents($tenantId);
        $fail15 = $userRepo->countAuthFailures15m();

        try {
            $userRepo->insertSystemMetric([
                'server_time' => $serverTime,
                'db_latency_ms' => $dbLat,
                'db_active_connections' => $active === null ? null : (int) $active,
                'disk_free_bytes' => $free,
                'disk_total_bytes' => $total,
                'count_meetings' => $cntMeet,
                'count_motions' => $cntMot,
                'count_vote_tokens' => $cntTok,
                'count_audit_events' => $cntAud,
                'auth_failures_15m' => $fail15,
            ]);
        } catch (Throwable $e) {
            if ($e instanceof \AgVote\Core\Http\ApiResponseException) {
                throw $e;
            }
        }

        $alertsToCreate = [];

        if ($fail15 !== null && $fail15 > 5) {
            $alertsToCreate[] = ['code' => 'auth_failures', 'severity' => 'warn', 'message' => 'Plus de 5 échecs de clé API sur 15 minutes.', 'details' => ['count' => $fail15]];
        }
        if ($dbLat !== null && $dbLat > 2000.0) {
            $alertsToCreate[] = ['code' => 'slow_db', 'severity' => 'critical', 'message' => 'Latence DB > 2s.', 'details' => ['db_latency_ms' => round($dbLat, 2)]];
        }
        if ($free !== null && $total) {
            $pct = ($free / $total) * 100.0;
            if ($pct < 10.0) {
                $alertsToCreate[] = ['code' => 'low_disk', 'severity' => 'critical', 'message' => 'Espace disque < 10%.', 'details' => ['free_pct' => round($pct, 2), 'free_bytes' => $free, 'total_bytes' => $total]];
            }
        }

        foreach ($alertsToCreate as $a) {
            try {
                if (!$userRepo->findRecentAlert($a['code'])) {
                    $userRepo->insertSystemAlert($a['code'], $a['severity'], $a['message'], json_encode($a['details']));
                }
            } catch (Throwable $e) {
                if ($e instanceof \AgVote\Core\Http\ApiResponseException) {
                    throw $e;
                }
            }
        }

        $recentAlerts = $userRepo->listRecentAlerts(20);

        api_ok([
            'system' => [
                'server_time' => $serverTime,
                'db_latency_ms' => $dbLat === null ? null : round($dbLat, 2),
                'db_active_connections' => $active === null ? null : (int) $active,
                'disk_free_bytes' => $free,
                'disk_total_bytes' => $total,
                'disk_free_pct' => ($free !== null && $total) ? round(($free / $total) * 100.0, 2) : null,
                'count_meetings' => $cntMeet,
                'count_motions' => $cntMot,
                'count_vote_tokens' => $cntTok,
                'count_audit_events' => $cntAud,
                'active_meetings' => $cntLive,
                'total_members' => $cntMembers,
                'php_version' => phpversion(),
                'memory_usage' => round(memory_get_usage(true) / 1048576, 1) . ' MB',
                'auth_failures_15m' => $fail15,
            ],
            'alerts' => $recentAlerts,
        ]);
    }

    public function auditLog(): void {
        api_request('GET');

        $tenantId = api_current_tenant_id();
        $limit = min(200, max(1, api_query_int('limit', 100)));
        $offset = max(0, api_query_int('offset', 0));
        $action = api_query('action');
        $q = api_query('q');

        $repo = new AuditEventRepository();

        $total = $repo->countAdminEvents($tenantId, $action ?: null, $q ?: null);
        $events = $repo->searchAdminEvents($tenantId, $action ?: null, $q ?: null, $limit, $offset);

        $actionLabels = [
            'admin.user.created' => 'Utilisateur créé',
            'admin.user.updated' => 'Utilisateur modifié',
            'admin.user.deleted' => 'Utilisateur supprimé',
            'admin.user.toggled' => 'Utilisateur activé/désactivé',
            'admin.user.password_set' => 'Mot de passe défini',
            'admin.user.key_rotated' => 'Clé API régénérée',
            'admin.user.key_revoked' => 'Clé API révoquée',
            'admin.meeting_role.assigned' => 'Rôle de séance assigné',
            'admin.meeting_role.revoked' => 'Rôle de séance révoqué',
            'admin_quorum_policy_saved' => 'Politique quorum enregistrée',
            'admin_quorum_policy_deleted' => 'Politique quorum supprimée',
            'admin_vote_policy_saved' => 'Politique de vote enregistrée',
            'admin_vote_policy_deleted' => 'Politique de vote supprimée',
        ];

        $formatted = [];
        foreach ($events as $e) {
            $payload = self::parsePayload($e['payload'] ?? null);

            $actionLabel = $actionLabels[$e['action']] ?? ucfirst(str_replace(['admin.', '_'], ['', ' '], $e['action']));

            $detail = '';
            if (isset($payload['email'])) {
                $detail .= $payload['email'];
            }
            if (isset($payload['role'])) {
                $detail .= ($detail ? ' — ' : '') . $payload['role'];
            }
            if (isset($payload['user_name'])) {
                $detail .= ($detail ? ' — ' : '') . $payload['user_name'];
            }
            if (isset($payload['is_active'])) {
                $detail .= ($detail ? ' — ' : '') . ($payload['is_active'] ? 'activé' : 'désactivé');
            }
            if (isset($payload['name'])) {
                $detail .= ($detail ? ' — ' : '') . $payload['name'];
            }

            $formatted[] = [
                'id' => $e['id'],
                'timestamp' => $e['created_at'],
                'action' => $e['action'],
                'action_label' => $actionLabel,
                'resource_type' => $e['resource_type'],
                'resource_id' => $e['resource_id'],
                'actor_role' => $e['actor_role'],
                'actor_user_id' => $e['actor_user_id'],
                'ip_address' => $e['ip_address'],
                'detail' => $detail,
                'payload' => $payload,
            ];
        }

        $actionTypes = [];
        $distinctActions = $repo->listDistinctAdminActions($tenantId);
        foreach ($distinctActions as $row) {
            $actionTypes[] = [
                'value' => $row['action'],
                'label' => $actionLabels[$row['action']] ?? $row['action'],
            ];
        }

        api_ok([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'items' => $formatted,
            'action_types' => $actionTypes,
        ]);
    }

    private static function parsePayload(mixed $payload): array {
        if (empty($payload)) {
            return [];
        }
        if (is_string($payload)) {
            return json_decode($payload, true) ?? [];
        }
        return (array) $payload;
    }
}
