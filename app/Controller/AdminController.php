<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Security\AuthMiddleware;
use AgVote\Service\AdminService;
use InvalidArgumentException;
use RuntimeException;

/**
 * Consolidates 5 admin endpoints.
 *
 * Thin HTTP adapter — business logic delegated to AdminService.
 */
final class AdminController extends AbstractController {
    public function users(): void {
        $method = api_method();

        if ($method === 'GET') {
            api_request('GET');
            $tenantId = api_current_tenant_id();
            $userRepo = $this->repo()->user();

            $roleFilter = api_query('role');
            $validSystemRoles = ['admin', 'operator', 'auditor', 'viewer'];
            $filterValue = ($roleFilter !== '' && in_array($roleFilter, $validSystemRoles, true)) ? $roleFilter : null;

            $rows = $userRepo->listByTenant($tenantId, $filterValue);
            foreach ($rows as &$row) {
                $row['meeting_roles'] = $userRepo->listActiveMeetingRolesForUser($row['id'], $tenantId);
            }
            unset($row);

            api_ok([
                'items' => $rows,
                'system_roles' => AuthMiddleware::getSystemRoleLabels(),
                'meeting_roles' => AuthMiddleware::getMeetingRoleLabels(),
            ]);
        } elseif ($method === 'POST') {
            $in = api_request('POST');
            $action = trim((string) ($in['action'] ?? 'create'));
            $tenantId = api_current_tenant_id();
            $currentUserId = api_current_user_id();

            // Actions requiring confirmation
            if (in_array($action, ['set_password', 'delete', 'erase_member'], true)) {
                $this->requireConfirmation($in, $tenantId);
            }

            // UUID validation for actions that need it
            if (in_array($action, ['set_password', 'rotate_key', 'revoke_key', 'toggle', 'delete', 'update'], true)) {
                api_require_uuid($in, 'user_id');
            }
            if ($action === 'erase_member') {
                api_require_uuid($in, 'member_id');
            }

            try {
                $service = new AdminService($this->repo());
                $result = $service->handleUserAction($tenantId, $action, $in, $currentUserId);
                $this->auditUserAction($action, $result);
                api_ok($result);
            } catch (InvalidArgumentException $e) {
                $msg = $e->getMessage();
                $code = str_contains($msg, ':') ? explode(':', $msg)[0] : $msg;
                $detail = str_contains($msg, ':') ? explode(':', $msg, 2)[1] : null;
                $statusMap = ['weak_password' => 400, 'missing_fields' => 400, 'invalid_role' => 400, 'unknown_action' => 400];
                api_fail($code, $statusMap[$code] ?? 400, $detail ? ['detail' => $detail] : []);
            } catch (RuntimeException $e) {
                $code = $e->getMessage();
                $statusMap = ['cannot_toggle_self' => 400, 'cannot_delete_self' => 400, 'cannot_demote_self' => 400, 'email_exists' => 409, 'member_not_found' => 404];
                api_fail($code, $statusMap[$code] ?? 400);
            }
        } else {
            api_fail('method_not_allowed', 405);
        }
    }

    public function roles(): void {
        api_request('GET');

        $userRepo = $this->repo()->user();
        $statsRepo = $this->repo()->meetingStats();

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
        $tenantId = api_current_tenant_id();
        $currentUserId = api_current_user_id();
        $currentRole = api_current_role();

        if ($method === 'GET') {
            api_request('GET');
            $data = ['meeting_id' => api_query('meeting_id')];
        } elseif ($method === 'POST') {
            $data = api_request('POST');
            $action = trim((string) ($data['action'] ?? 'assign'));
            if ($action === 'assign' || $action === 'revoke') {
                api_require_uuid($data, 'meeting_id');
                api_require_uuid($data, 'user_id');
            }
        } else {
            api_fail('method_not_allowed', 405);
        }

        try {
            $service = new AdminService($this->repo());
            $result = $service->handleMeetingRole($tenantId, $method, $data, $currentUserId, $currentRole);

            if ($method === 'POST') {
                $action = trim((string) ($data['action'] ?? 'assign'));
                if ($action === 'assign') {
                    audit_log('admin.meeting_role.assigned', 'meeting', $result['meeting_id'], ['user_id' => $result['user_id'], 'user_name' => $result['user_name'] ?? '', 'role' => $result['role']], $result['meeting_id']);
                } elseif ($action === 'revoke') {
                    audit_log('admin.meeting_role.revoked', 'meeting', $result['meeting_id'], ['user_id' => $result['user_id'], 'role' => $data['role'] ?? 'all'], $result['meeting_id']);
                }
            }

            api_ok($result);
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $code = str_contains($msg, ':') ? explode(':', $msg)[0] : $msg;
            api_fail($code, 400);
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            $statusMap = ['meeting_not_found' => 404, 'user_not_found' => 404, 'admin_required_for_president' => 403];
            api_fail($code, $statusMap[$code] ?? 400);
        }
    }

    public function systemStatus(): void {
        api_request('GET');

        $service = new AdminService($this->repo());
        $result = $service->getSystemStatus(api_current_tenant_id());
        api_ok($result);
    }

    public function auditLog(): void {
        api_request('GET');

        $params = [
            'limit' => api_query_int('limit', 100),
            'offset' => api_query_int('offset', 0),
            'action' => api_query('action'),
            'q' => api_query('q'),
        ];

        $service = new AdminService($this->repo());
        $result = $service->getAuditLog(api_current_tenant_id(), $params);
        api_ok($result);
    }

    private function auditUserAction(string $action, array $result): void {
        $userId = $result['user_id'] ?? $result['member_id'] ?? '';
        $actionMap = [
            'set_password' => 'admin.user.password_set',
            'rotate_key' => 'admin.user.key_rotated',
            'revoke_key' => 'admin.user.key_revoked',
            'toggle' => 'admin.user.toggled',
            'delete' => 'admin.user.deleted',
            'erase_member' => 'admin.member.erased',
            'update' => 'admin.user.updated',
            'create' => 'admin.user.created',
        ];
        $auditAction = $actionMap[$action] ?? "admin.user.{$action}";
        $resourceType = $action === 'erase_member' ? 'member' : 'user';

        $context = [];
        if ($action === 'erase_member') {
            $context = ['full_name' => $result['full_name'] ?? '', 'email' => $result['email'] ?? '', 'rgpd' => true];
        } elseif (isset($result['is_active'])) {
            $context = ['is_active' => $result['is_active']];
        }

        audit_log($auditAction, $resourceType, (string) $userId, $context);
    }
}
