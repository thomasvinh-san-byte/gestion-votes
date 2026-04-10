<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Core\Providers\RepositoryFactory;
use AgVote\Core\Security\AuthMiddleware;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Business logic extracted from AdminController.
 *
 * Handles user management, meeting roles, system status, and audit log retrieval.
 * HTTP-agnostic: receives data as parameters, returns arrays, throws exceptions.
 */
final class AdminService {
    private RepositoryFactory $repos;

    public function __construct(?RepositoryFactory $repos = null) {
        $this->repos = $repos ?? RepositoryFactory::getInstance();
    }

    /** @var list<string> */
    private const VALID_SYSTEM_ROLES = ['admin', 'operator', 'auditor', 'viewer'];
    private const VALID_MEETING_ROLES = ['president', 'assessor', 'voter'];

    /**
     * Handle a user action (CRUD operations on users).
     *
     * @return array{ok: bool, action: string, result: array}
     * @throws InvalidArgumentException for validation errors
     * @throws RuntimeException for business logic errors
     */
    public function handleUserAction(string $tenantId, string $action, array $data, string $currentUserId): array {
        $userRepo = $this->repos->user();

        return match ($action) {
            'set_password' => $this->setPassword($userRepo, $tenantId, $data),
            'rotate_key' => $this->rotateKey($userRepo, $tenantId, $data),
            'revoke_key' => $this->revokeKey($userRepo, $tenantId, $data),
            'toggle' => $this->toggleUser($userRepo, $tenantId, $data, $currentUserId),
            'delete' => $this->deleteUser($userRepo, $tenantId, $data, $currentUserId),
            'erase_member' => $this->eraseMember($tenantId, $data),
            'update' => $this->updateUser($userRepo, $tenantId, $data, $currentUserId),
            'create' => $this->createUser($userRepo, $tenantId, $data),
            default => throw new InvalidArgumentException("unknown_action:{$action}"),
        };
    }

    /**
     * Handle meeting role operations (list, assign, revoke).
     *
     * @return array
     * @throws InvalidArgumentException for validation errors
     * @throws RuntimeException for business logic errors
     */
    public function handleMeetingRole(string $tenantId, string $method, array $data, string $currentUserId, string $currentRole = ''): array {
        $userRepo = $this->repos->user();
        $meetingRepo = $this->repos->meeting();

        if ($method === 'GET') {
            $meetingId = $data['meeting_id'] ?? '';
            if ($meetingId !== '' && $this->isUuid($meetingId)) {
                return ['items' => $userRepo->listMeetingRolesForMeeting($tenantId, $meetingId), 'meeting_id' => $meetingId, 'meeting_roles' => AuthMiddleware::getMeetingRoleLabels()];
            }
            return ['items' => $userRepo->listMeetingRolesSummary($tenantId)];
        }

        $action = trim((string) ($data['action'] ?? 'assign'));
        if ($action === 'assign') {
            return $this->assignMeetingRole($userRepo, $meetingRepo, $tenantId, $data, $currentUserId, $currentRole);
        } elseif ($action === 'revoke') {
            return $this->revokeMeetingRole($userRepo, $tenantId, $data, $currentUserId);
        }
        throw new InvalidArgumentException('unknown_action');
    }

    /**
     * Assemble system status data.
     *
     * @return array{system: array, alerts: array}
     */
    public function getSystemStatus(string $tenantId): array {
        $sysRepo = $this->repos->system();
        $meetingRepo = $this->repos->meeting();
        $motionRepo = $this->repos->motion();
        $memberRepo = $this->repos->member();
        $voteTokenRepo = $this->repos->voteToken();
        $serverTime = date('c');

        $dbLat = $sysRepo->dbPing();
        $active = $sysRepo->dbActiveConnections();
        $free = @disk_free_space(__DIR__) ?: null;
        $total = @disk_total_space(__DIR__) ?: null;
        $cntMeet = $meetingRepo->countForTenant($tenantId);
        $cntMot = $motionRepo->countAll($tenantId);
        $cntMembers = $memberRepo->countActive($tenantId);
        $cntLive = $meetingRepo->countLive($tenantId);
        $cntTok = $voteTokenRepo->countAll();
        $cntAud = $sysRepo->countAuditEvents($tenantId);
        $fail15 = $sysRepo->countAuthFailures15m();

        try {
            $sysRepo->insertSystemMetric(['server_time' => $serverTime, 'db_latency_ms' => $dbLat, 'db_active_connections' => $active === null ? null : (int) $active, 'disk_free_bytes' => $free, 'disk_total_bytes' => $total, 'count_meetings' => $cntMeet, 'count_motions' => $cntMot, 'count_vote_tokens' => $cntTok, 'count_audit_events' => $cntAud, 'auth_failures_15m' => $fail15]);
        } catch (Throwable) {}

        $this->createAlerts($sysRepo, $fail15, $dbLat, $free, $total);
        $smtpConfigured = $this->checkSmtp($tenantId);

        return [
            'system' => ['server_time' => $serverTime, 'db_latency_ms' => $dbLat === null ? null : round($dbLat, 2), 'db_active_connections' => $active === null ? null : (int) $active, 'disk_free_bytes' => $free, 'disk_total_bytes' => $total, 'disk_free_pct' => ($free !== null && $total) ? round(($free / $total) * 100.0, 2) : null, 'count_meetings' => $cntMeet, 'count_motions' => $cntMot, 'count_vote_tokens' => $cntTok, 'count_audit_events' => $cntAud, 'active_meetings' => $cntLive, 'total_members' => $cntMembers, 'php_version' => phpversion(), 'memory_usage' => round(memory_get_usage(true) / 1048576, 1) . ' MB', 'auth_failures_15m' => $fail15, 'smtp_configured' => $smtpConfigured],
            'alerts' => $sysRepo->listRecentAlerts(20),
        ];
    }

    /**
     * Retrieve formatted audit log entries.
     *
     * @return array{total: int, limit: int, offset: int, items: array, action_types: array}
     */
    public function getAuditLog(string $tenantId, array $params): array {
        $limit = min(200, max(1, (int) ($params['limit'] ?? 100)));
        $offset = max(0, (int) ($params['offset'] ?? 0));
        $action = $params['action'] ?? '';
        $q = $params['q'] ?? '';
        $repo = $this->repos->auditEvent();

        $total = $repo->countAdminEvents($tenantId, $action ?: null, $q ?: null);
        $events = $repo->searchAdminEvents($tenantId, $action ?: null, $q ?: null, $limit, $offset);
        $formatted = array_map(fn ($e) => $this->formatAuditEvent($e), $events);

        $actionTypes = [];
        foreach ($repo->listDistinctAdminActions($tenantId) as $row) {
            $actionTypes[] = ['value' => $row['action'], 'label' => self::ACTION_LABELS[$row['action']] ?? $row['action']];
        }

        return ['total' => $total, 'limit' => $limit, 'offset' => $offset, 'items' => $formatted, 'action_types' => $actionTypes];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private const ACTION_LABELS = [
        'admin.user.created' => 'Utilisateur cree', 'admin.user.updated' => 'Utilisateur modifie', 'admin.user.deleted' => 'Utilisateur supprime',
        'admin.user.toggled' => 'Utilisateur active/desactive', 'admin.user.password_set' => 'Mot de passe defini',
        'admin.user.key_rotated' => 'Cle API regeneree', 'admin.user.key_revoked' => 'Cle API revoquee',
        'admin.meeting_role.assigned' => 'Role de seance assigne', 'admin.meeting_role.revoked' => 'Role de seance revoque',
        'admin_quorum_policy_saved' => 'Politique quorum enregistree', 'admin_quorum_policy_deleted' => 'Politique quorum supprimee',
        'admin_vote_policy_saved' => 'Politique de vote enregistree', 'admin_vote_policy_deleted' => 'Politique de vote supprimee',
    ];

    private function formatAuditEvent(array $e): array {
        $payload = self::parsePayload($e['payload'] ?? null);
        $label = self::ACTION_LABELS[$e['action']] ?? ucfirst(str_replace(['admin.', '_'], ['', ' '], $e['action']));
        $detail = '';
        foreach (['email', 'role', 'user_name'] as $k) { if (isset($payload[$k])) { $detail .= ($detail ? ' — ' : '') . $payload[$k]; } }
        if (isset($payload['is_active'])) { $detail .= ($detail ? ' — ' : '') . ($payload['is_active'] ? 'active' : 'desactive'); }
        if (isset($payload['name'])) { $detail .= ($detail ? ' — ' : '') . $payload['name']; }
        return ['id' => $e['id'], 'timestamp' => $e['created_at'], 'action' => $e['action'], 'action_label' => $label, 'resource_type' => $e['resource_type'], 'resource_id' => $e['resource_id'], 'actor_role' => $e['actor_role'], 'actor_user_id' => $e['actor_user_id'], 'ip_address' => $e['ip_address'], 'detail' => $detail, 'payload' => $payload];
    }

    private static function parsePayload(mixed $payload): array {
        if (empty($payload)) { return []; }
        if (is_string($payload)) { return json_decode($payload, true) ?? []; }
        return (array) $payload;
    }

    private function setPassword(mixed $userRepo, string $tenantId, array $data): array {
        $userId = (string) ($data['user_id'] ?? '');
        $password = (string) ($data['password'] ?? '');
        if (strlen($password) < 8) { throw new InvalidArgumentException('weak_password'); }
        $userRepo->setPasswordHash($tenantId, $userId, password_hash($password, PASSWORD_DEFAULT));
        return ['saved' => true, 'user_id' => $userId];
    }

    private function rotateKey(mixed $userRepo, string $tenantId, array $data): array {
        $userId = (string) ($data['user_id'] ?? '');
        $apiKey = bin2hex(random_bytes(16));
        $userRepo->rotateApiKey($tenantId, $userId, AuthMiddleware::hashApiKey($apiKey));
        return ['rotated' => true, 'api_key' => $apiKey, 'user_id' => $userId];
    }

    private function revokeKey(mixed $userRepo, string $tenantId, array $data): array {
        $userId = (string) ($data['user_id'] ?? '');
        $userRepo->revokeApiKey($tenantId, $userId);
        return ['revoked' => true, 'user_id' => $userId];
    }

    private function toggleUser(mixed $userRepo, string $tenantId, array $data, string $currentUserId): array {
        $userId = (string) ($data['user_id'] ?? '');
        if ($userId === $currentUserId) { throw new RuntimeException('cannot_toggle_self'); }
        $active = (int) ($data['is_active'] ?? 1) ? true : false;
        $userRepo->toggleActive($tenantId, $userId, $active);
        return ['saved' => true, 'user_id' => $userId, 'is_active' => $active];
    }

    private function deleteUser(mixed $userRepo, string $tenantId, array $data, string $currentUserId): array {
        $userId = (string) ($data['user_id'] ?? '');
        if ($userId === $currentUserId) { throw new RuntimeException('cannot_delete_self'); }
        $userRepo->deleteUser($tenantId, $userId);
        return ['deleted' => true, 'user_id' => $userId];
    }

    private function eraseMember(string $tenantId, array $data): array {
        $memberId = (string) ($data['member_id'] ?? '');
        $memberRepo = $this->repos->member();
        $member = $memberRepo->findByIdForTenant($memberId, $tenantId);
        if ($member === null) { throw new RuntimeException('member_not_found'); }
        $rows = $memberRepo->hardDeleteById($memberId, $tenantId);
        $this->repos->auditEvent()->anonymizeForResource('member', $memberId);
        return ['erased' => true, 'member_id' => $memberId, 'rows_deleted' => $rows, 'full_name' => $member['full_name'] ?? '', 'email' => $member['email'] ?? ''];
    }

    private function updateUser(mixed $userRepo, string $tenantId, array $data, string $currentUserId): array {
        $userId = (string) ($data['user_id'] ?? '');
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $name = trim((string) ($data['name'] ?? ''));
        $role = trim((string) ($data['role'] ?? ''));
        if ($email === '' || $name === '') { throw new InvalidArgumentException('missing_fields'); }
        if ($role !== '' && !in_array($role, self::VALID_SYSTEM_ROLES, true)) { throw new InvalidArgumentException("invalid_role:{$role}"); }
        if ($userId === $currentUserId && $role !== '' && $role !== 'admin') { throw new RuntimeException('cannot_demote_self'); }
        $userRepo->updateUser($tenantId, $userId, $email, $name, $role !== '' ? $role : null);
        return ['saved' => true, 'user_id' => $userId];
    }

    private function createUser(mixed $userRepo, string $tenantId, array $data): array {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $name = trim((string) ($data['name'] ?? ''));
        $role = trim((string) ($data['role'] ?? 'viewer'));
        if ($email === '' || $name === '') { throw new InvalidArgumentException('missing_fields'); }
        if (!in_array($role, self::VALID_SYSTEM_ROLES, true)) { throw new InvalidArgumentException("invalid_role:{$role}"); }
        $existing = $userRepo->findIdByEmail($tenantId, $email);
        if ($existing) { throw new RuntimeException('email_exists'); }
        $password = trim((string) ($data['password'] ?? ''));
        if (strlen($password) < 8) { throw new InvalidArgumentException('weak_password'); }
        $id = $userRepo->generateUuid();
        $userRepo->createUser($id, $tenantId, $email, $name, $role, password_hash($password, PASSWORD_DEFAULT));
        return ['saved' => true, 'user_id' => $id];
    }

    private function assignMeetingRole(mixed $userRepo, mixed $meetingRepo, string $tenantId, array $data, string $currentUserId, string $currentRole): array {
        $meetingId = (string) ($data['meeting_id'] ?? '');
        $userId = (string) ($data['user_id'] ?? '');
        $role = trim((string) ($data['role'] ?? ''));
        if (!in_array($role, self::VALID_MEETING_ROLES, true)) { throw new InvalidArgumentException("invalid_meeting_role:{$role}"); }
        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) { throw new RuntimeException('meeting_not_found'); }
        $user = $userRepo->findActiveById($userId, $tenantId);
        if (!$user) { throw new RuntimeException('user_not_found'); }
        if ($role === 'president') {
            if ($currentRole !== 'admin') { throw new RuntimeException('admin_required_for_president'); }
            $existingPres = $userRepo->findExistingPresident($tenantId, $meetingId);
            if ($existingPres && $existingPres !== $userId) { $userRepo->revokePresidentRole($tenantId, $meetingId); }
        }
        $userRepo->assignMeetingRole($tenantId, $meetingId, $userId, $role, $currentUserId);
        return ['assigned' => true, 'meeting_id' => $meetingId, 'user_id' => $userId, 'role' => $role, 'user_name' => $user['name']];
    }

    private function revokeMeetingRole(mixed $userRepo, string $tenantId, array $data, string $currentUserId): array {
        $meetingId = (string) ($data['meeting_id'] ?? '');
        $userId = (string) ($data['user_id'] ?? '');
        $role = trim((string) ($data['role'] ?? ''));
        if ($role !== '' && !in_array($role, self::VALID_MEETING_ROLES, true)) { throw new InvalidArgumentException('invalid_meeting_role'); }
        $userRepo->revokeMeetingRole($tenantId, $meetingId, $userId, $role !== '' ? $role : null);
        return ['revoked' => true, 'meeting_id' => $meetingId, 'user_id' => $userId];
    }

    private function createAlerts(mixed $sysRepo, ?int $fail15, ?float $dbLat, ?float $free, ?float $total): void {
        $alerts = [];
        if ($fail15 !== null && $fail15 > 5) { $alerts[] = ['code' => 'auth_failures', 'severity' => 'warn', 'message' => 'Plus de 5 echecs de cle API sur 15 minutes.', 'details' => ['count' => $fail15]]; }
        if ($dbLat !== null && $dbLat > 2000.0) { $alerts[] = ['code' => 'slow_db', 'severity' => 'critical', 'message' => 'Latence DB > 2s.', 'details' => ['db_latency_ms' => round($dbLat, 2)]]; }
        if ($free !== null && $total) {
            $pct = ($free / $total) * 100.0;
            if ($pct < 10.0) { $alerts[] = ['code' => 'low_disk', 'severity' => 'critical', 'message' => 'Espace disque < 10%.', 'details' => ['free_pct' => round($pct, 2), 'free_bytes' => $free, 'total_bytes' => $total]]; }
        }
        foreach ($alerts as $a) {
            try { if (!$sysRepo->findRecentAlert($a['code'])) { $sysRepo->insertSystemAlert($a['code'], $a['severity'], $a['message'], json_encode($a['details'])); } } catch (Throwable) {}
        }
    }

    private function checkSmtp(string $tenantId): bool {
        try {
            global $config;
            $mailerConfig = MailerService::buildMailerConfig($config ?? [], $this->repos->settings(), $tenantId);
            return (new MailerService($mailerConfig))->isConfigured();
        } catch (Throwable) { return false; }
    }

    private function isUuid(string $v): bool {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v);
    }
}
