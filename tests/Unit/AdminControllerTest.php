<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AdminController;
use AgVote\Repository\AuditEventRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MeetingStatsRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\SystemRepository;
use AgVote\Repository\UserRepository;
use AgVote\Repository\VoteTokenRepository;
use ReflectionClass;

/**
 * Unit tests for AdminController.
 *
 * Tests all four endpoints with mocked repositories:
 * - users():        GET (list users) and POST (multiple actions)
 * - roles():        GET (list role permissions + stats)
 * - meetingRoles(): GET and POST (assign/revoke)
 * - systemStatus(): GET (system stats and alerts)
 * - auditLog():     GET (paginated audit events)
 */
class AdminControllerTest extends ControllerTestCase
{
    private const TENANT_ID  = 'tenant-admin-test';
    private const USER_ID    = 'aaaa0000-0000-0000-0000-000000000001';
    private const TARGET_UID = 'bbbb0000-0000-0000-0000-000000000002';
    private const MEETING_ID = 'cccc0000-0000-0000-0000-000000000003';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setAuth(self::USER_ID, 'admin', self::TENANT_ID);
    }

    // =========================================================================
    // CONTROLLER STRUCTURE
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new ReflectionClass(AdminController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerExtendsAbstractController(): void
    {
        $ref = new ReflectionClass(AdminController::class);
        $this->assertSame('AgVote\\Controller\\AbstractController', $ref->getParentClass()->getName());
    }

    public function testHasExpectedPublicMethods(): void
    {
        $ref = new ReflectionClass(AdminController::class);
        $methods = array_map(fn ($m) => $m->getName(), $ref->getMethods(\ReflectionMethod::IS_PUBLIC));
        foreach (['users', 'roles', 'meetingRoles', 'systemStatus', 'auditLog'] as $method) {
            $this->assertContains($method, $methods);
        }
    }

    // =========================================================================
    // users() — GET list
    // =========================================================================

    public function testUsersGetWrongMethod(): void
    {
        $this->setHttpMethod('DELETE');

        // userRepo is fetched before the method check
        $userRepo = $this->createMock(UserRepository::class);
        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(405, $resp['status']);
    }

    public function testUsersGetListReturnsItems(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $users = [
            ['id' => self::USER_ID, 'email' => 'admin@example.com', 'name' => 'Admin', 'role' => 'admin'],
        ];

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('listByTenant')->willReturn($users);
        $userRepo->method('listActiveMeetingRolesForUser')->willReturn([]);

        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('system_roles', $data);
        $this->assertArrayHasKey('meeting_roles', $data);
    }

    public function testUsersGetWithRoleFilter(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['role' => 'operator']);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('listByTenant')->with(self::TENANT_ID, 'operator')->willReturn([]);
        $userRepo->method('listActiveMeetingRolesForUser')->willReturn([]);

        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(200, $resp['status']);
    }

    public function testUsersGetWithInvalidRoleFilter(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['role' => 'superadmin']); // invalid role → no filter

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('listByTenant')->with(self::TENANT_ID, null)->willReturn([]);
        $userRepo->method('listActiveMeetingRolesForUser')->willReturn([]);

        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(200, $resp['status']);
    }

    // =========================================================================
    // users() — POST set_password
    // =========================================================================

    public function testUsersPostSetPasswordWeakPassword(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action' => 'set_password',
            'user_id' => self::TARGET_UID,
            'password' => 'short',
        ]);

        $userRepo = $this->createMock(UserRepository::class);
        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(400, $resp['status']);
        $this->assertSame('weak_password', $resp['body']['error']);
    }

    public function testUsersPostSetPasswordSuccess(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action' => 'set_password',
            'user_id' => self::TARGET_UID,
            'password' => 'StrongPassword123',
        ]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())->method('setPasswordHash');

        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(200, $resp['status']);
        $this->assertTrue($resp['body']['data']['saved']);
    }

    // =========================================================================
    // users() — POST rotate_key
    // =========================================================================

    public function testUsersPostRotateKey(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['action' => 'rotate_key', 'user_id' => self::TARGET_UID]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())->method('rotateApiKey');

        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(200, $resp['status']);
        $this->assertTrue($resp['body']['data']['rotated']);
        $this->assertArrayHasKey('api_key', $resp['body']['data']);
    }

    // =========================================================================
    // users() — POST revoke_key
    // =========================================================================

    public function testUsersPostRevokeKey(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['action' => 'revoke_key', 'user_id' => self::TARGET_UID]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())->method('revokeApiKey');

        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(200, $resp['status']);
        $this->assertTrue($resp['body']['data']['revoked']);
    }

    // =========================================================================
    // users() — POST toggle
    // =========================================================================

    public function testUsersPostToggleSelf(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['action' => 'toggle', 'user_id' => self::USER_ID, 'is_active' => 0]);

        $userRepo = $this->createMock(UserRepository::class);
        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(400, $resp['status']);
        $this->assertSame('cannot_toggle_self', $resp['body']['error']);
    }

    public function testUsersPostToggleSuccess(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['action' => 'toggle', 'user_id' => self::TARGET_UID, 'is_active' => 0]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())->method('toggleActive');

        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(200, $resp['status']);
        $this->assertTrue($resp['body']['data']['saved']);
        $this->assertFalse($resp['body']['data']['is_active']);
    }

    // =========================================================================
    // users() — POST delete
    // =========================================================================

    public function testUsersPostDeleteSelf(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['action' => 'delete', 'user_id' => self::USER_ID]);

        $userRepo = $this->createMock(UserRepository::class);
        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(400, $resp['status']);
        $this->assertSame('cannot_delete_self', $resp['body']['error']);
    }

    public function testUsersPostDeleteSuccess(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['action' => 'delete', 'user_id' => self::TARGET_UID]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())->method('deleteUser');

        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(200, $resp['status']);
        $this->assertTrue($resp['body']['data']['deleted']);
    }

    // =========================================================================
    // users() — POST update
    // =========================================================================

    public function testUsersPostUpdateMissingFields(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['action' => 'update', 'user_id' => self::TARGET_UID, 'email' => '', 'name' => '']);

        $userRepo = $this->createMock(UserRepository::class);
        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(400, $resp['status']);
        $this->assertSame('missing_fields', $resp['body']['error']);
    }

    public function testUsersPostUpdateInvalidRole(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action' => 'update',
            'user_id' => self::TARGET_UID,
            'email' => 'user@example.com',
            'name' => 'User Name',
            'role' => 'superadmin',
        ]);

        $userRepo = $this->createMock(UserRepository::class);
        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(400, $resp['status']);
        $this->assertSame('invalid_role', $resp['body']['error']);
    }

    public function testUsersPostUpdateCannotDemoteSelf(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action' => 'update',
            'user_id' => self::USER_ID,  // self
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'role' => 'viewer',  // demote from admin
        ]);

        $userRepo = $this->createMock(UserRepository::class);
        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(400, $resp['status']);
        $this->assertSame('cannot_demote_self', $resp['body']['error']);
    }

    public function testUsersPostUpdateSuccess(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action' => 'update',
            'user_id' => self::TARGET_UID,
            'email' => 'updated@example.com',
            'name' => 'Updated Name',
            'role' => 'operator',
        ]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())->method('updateUser');

        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(200, $resp['status']);
        $this->assertTrue($resp['body']['data']['saved']);
    }

    // =========================================================================
    // users() — POST create
    // =========================================================================

    public function testUsersPostCreateMissingFields(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['action' => 'create', 'email' => '', 'name' => '', 'password' => 'Pass1234!']);

        $userRepo = $this->createMock(UserRepository::class);
        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(400, $resp['status']);
        $this->assertSame('missing_fields', $resp['body']['error']);
    }

    public function testUsersPostCreateInvalidRole(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action' => 'create',
            'email' => 'new@example.com',
            'name' => 'New User',
            'role' => 'superadmin',
            'password' => 'StrongPass1',
        ]);

        $userRepo = $this->createMock(UserRepository::class);
        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(400, $resp['status']);
        $this->assertSame('invalid_role', $resp['body']['error']);
    }

    public function testUsersPostCreateEmailExists(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action' => 'create',
            'email' => 'existing@example.com',
            'name' => 'Existing User',
            'role' => 'viewer',
            'password' => 'StrongPass1',
        ]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findIdByEmail')->willReturn('existing-uuid-1234');

        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(409, $resp['status']);
        $this->assertSame('email_exists', $resp['body']['error']);
    }

    public function testUsersPostCreateWeakPassword(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action' => 'create',
            'email' => 'new@example.com',
            'name' => 'New User',
            'role' => 'viewer',
            'password' => 'weak',
        ]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findIdByEmail')->willReturn(null);

        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(400, $resp['status']);
        $this->assertSame('weak_password', $resp['body']['error']);
    }

    public function testUsersPostCreateSuccess(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action' => 'create',
            'email' => 'new@example.com',
            'name' => 'New User',
            'role' => 'viewer',
            'password' => 'StrongPassword1!',
        ]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findIdByEmail')->willReturn(null);
        $userRepo->method('generateUuid')->willReturn('new-uuid-1234-5678');
        $userRepo->expects($this->once())->method('createUser');

        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(200, $resp['status']);
        $this->assertTrue($resp['body']['data']['saved']);
    }

    public function testUsersPostUnknownAction(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['action' => 'nonexistent_action']);

        $userRepo = $this->createMock(UserRepository::class);
        $this->injectRepos([UserRepository::class => $userRepo]);

        $resp = $this->callController(AdminController::class, 'users');
        $this->assertSame(400, $resp['status']);
        $this->assertSame('unknown_action', $resp['body']['error']);
    }

    // =========================================================================
    // roles() — GET
    // =========================================================================

    public function testRolesWrongMethod(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $userRepo  = $this->createMock(UserRepository::class);
        $statsRepo = $this->createMock(MeetingStatsRepository::class);
        $meetingRepo = $this->createMock(MeetingRepository::class);

        $this->injectRepos([
            UserRepository::class         => $userRepo,
            MeetingStatsRepository::class => $statsRepo,
            MeetingRepository::class      => $meetingRepo,
        ]);

        $resp = $this->callController(AdminController::class, 'roles');
        $this->assertSame(405, $resp['status']);
    }

    public function testRolesGetReturnsRoleData(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('listRolePermissions')->willReturn([
            ['role' => 'admin', 'permission' => 'manage_users', 'description' => 'Manage users'],
        ]);
        $userRepo->method('countBySystemRole')->willReturn([['role' => 'admin', 'count' => 1]]);
        $userRepo->method('countByMeetingRole')->willReturn([]);

        $statsRepo = $this->createMock(MeetingStatsRepository::class);
        $statsRepo->method('listStateTransitions')->willReturn([]);

        $meetingRepo = $this->createMock(MeetingRepository::class);

        $this->injectRepos([
            UserRepository::class         => $userRepo,
            MeetingStatsRepository::class => $statsRepo,
            MeetingRepository::class      => $meetingRepo,
        ]);

        $resp = $this->callController(AdminController::class, 'roles');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertArrayHasKey('system_roles', $data);
        $this->assertArrayHasKey('permissions_by_role', $data);
        $this->assertArrayHasKey('state_transitions', $data);
        $this->assertArrayHasKey('users_by_system_role', $data);
    }

    // =========================================================================
    // meetingRoles() — GET
    // =========================================================================

    public function testMeetingRolesGetWrongMethod(): void
    {
        $this->setHttpMethod('DELETE');
        $this->injectRepos([
            UserRepository::class    => $this->createMock(UserRepository::class),
            MeetingRepository::class => $this->createMock(MeetingRepository::class),
        ]);

        $resp = $this->callController(AdminController::class, 'meetingRoles');
        $this->assertSame(405, $resp['status']);
    }

    public function testMeetingRolesGetWithMeetingId(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('listMeetingRolesForMeeting')->willReturn([
            ['user_id' => self::USER_ID, 'role' => 'president'],
        ]);

        $meetingRepo = $this->createMock(MeetingRepository::class);

        $this->injectRepos([
            UserRepository::class    => $userRepo,
            MeetingRepository::class => $meetingRepo,
        ]);

        $resp = $this->callController(AdminController::class, 'meetingRoles');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('meeting_id', $data);
    }

    public function testMeetingRolesGetSummary(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('listMeetingRolesSummary')->willReturn([]);

        $meetingRepo = $this->createMock(MeetingRepository::class);

        $this->injectRepos([
            UserRepository::class    => $userRepo,
            MeetingRepository::class => $meetingRepo,
        ]);

        $resp = $this->callController(AdminController::class, 'meetingRoles');
        $this->assertSame(200, $resp['status']);
        $this->assertArrayHasKey('items', $resp['body']['data']);
    }

    // =========================================================================
    // meetingRoles() — POST assign
    // =========================================================================

    public function testMeetingRolesPostAssignInvalidRole(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action' => 'assign',
            'meeting_id' => self::MEETING_ID,
            'user_id' => self::TARGET_UID,
            'role' => 'superpresident',
        ]);

        $userRepo    = $this->createMock(UserRepository::class);
        $meetingRepo = $this->createMock(MeetingRepository::class);

        $this->injectRepos([
            UserRepository::class    => $userRepo,
            MeetingRepository::class => $meetingRepo,
        ]);

        $resp = $this->callController(AdminController::class, 'meetingRoles');
        $this->assertSame(400, $resp['status']);
        $this->assertSame('invalid_meeting_role', $resp['body']['error']);
    }

    public function testMeetingRolesPostAssignMeetingNotFound(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action' => 'assign',
            'meeting_id' => self::MEETING_ID,
            'user_id' => self::TARGET_UID,
            'role' => 'voter',
        ]);

        $userRepo = $this->createMock(UserRepository::class);
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(null);

        $this->injectRepos([
            UserRepository::class    => $userRepo,
            MeetingRepository::class => $meetingRepo,
        ]);

        $resp = $this->callController(AdminController::class, 'meetingRoles');
        $this->assertSame(404, $resp['status']);
        $this->assertSame('meeting_not_found', $resp['body']['error']);
    }

    public function testMeetingRolesPostAssignUserNotFound(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action' => 'assign',
            'meeting_id' => self::MEETING_ID,
            'user_id' => self::TARGET_UID,
            'role' => 'voter',
        ]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findActiveById')->willReturn(null);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(['id' => self::MEETING_ID, 'title' => 'Test']);

        $this->injectRepos([
            UserRepository::class    => $userRepo,
            MeetingRepository::class => $meetingRepo,
        ]);

        $resp = $this->callController(AdminController::class, 'meetingRoles');
        $this->assertSame(404, $resp['status']);
        $this->assertSame('user_not_found', $resp['body']['error']);
    }

    public function testMeetingRolesPostAssignVoterSuccess(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action' => 'assign',
            'meeting_id' => self::MEETING_ID,
            'user_id' => self::TARGET_UID,
            'role' => 'voter',
        ]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findActiveById')->willReturn(['id' => self::TARGET_UID, 'name' => 'Target User']);
        $userRepo->expects($this->once())->method('assignMeetingRole');

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(['id' => self::MEETING_ID, 'title' => 'Test']);

        $this->injectRepos([
            UserRepository::class    => $userRepo,
            MeetingRepository::class => $meetingRepo,
        ]);

        $resp = $this->callController(AdminController::class, 'meetingRoles');
        $this->assertSame(200, $resp['status']);
        $this->assertTrue($resp['body']['data']['assigned']);
    }

    public function testMeetingRolesPostAssignPresidentRequiresAdmin(): void
    {
        // Use non-admin role
        $this->setAuth(self::USER_ID, 'operator', self::TENANT_ID);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action' => 'assign',
            'meeting_id' => self::MEETING_ID,
            'user_id' => self::TARGET_UID,
            'role' => 'president',
        ]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findActiveById')->willReturn(['id' => self::TARGET_UID, 'name' => 'Target User']);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(['id' => self::MEETING_ID, 'title' => 'Test']);

        $this->injectRepos([
            UserRepository::class    => $userRepo,
            MeetingRepository::class => $meetingRepo,
        ]);

        $resp = $this->callController(AdminController::class, 'meetingRoles');
        $this->assertSame(403, $resp['status']);
        $this->assertSame('admin_required_for_president', $resp['body']['error']);
    }

    public function testMeetingRolesPostAssignPresidentAdminSuccess(): void
    {
        $this->setAuth(self::USER_ID, 'admin', self::TENANT_ID);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action' => 'assign',
            'meeting_id' => self::MEETING_ID,
            'user_id' => self::TARGET_UID,
            'role' => 'president',
        ]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findActiveById')->willReturn(['id' => self::TARGET_UID, 'name' => 'Pres User']);
        $userRepo->method('findExistingPresident')->willReturn(null);
        $userRepo->expects($this->once())->method('assignMeetingRole');

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(['id' => self::MEETING_ID, 'title' => 'Test']);

        $this->injectRepos([
            UserRepository::class    => $userRepo,
            MeetingRepository::class => $meetingRepo,
        ]);

        $resp = $this->callController(AdminController::class, 'meetingRoles');
        $this->assertSame(200, $resp['status']);
        $this->assertTrue($resp['body']['data']['assigned']);
    }

    public function testMeetingRolesPostRevokeSuccess(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action' => 'revoke',
            'meeting_id' => self::MEETING_ID,
            'user_id' => self::TARGET_UID,
            'role' => 'voter',
        ]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())->method('revokeMeetingRole');

        $meetingRepo = $this->createMock(MeetingRepository::class);

        $this->injectRepos([
            UserRepository::class    => $userRepo,
            MeetingRepository::class => $meetingRepo,
        ]);

        $resp = $this->callController(AdminController::class, 'meetingRoles');
        $this->assertSame(200, $resp['status']);
        $this->assertTrue($resp['body']['data']['revoked']);
    }

    public function testMeetingRolesPostUnknownAction(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action' => 'unknown_action',
            'meeting_id' => self::MEETING_ID,
            'user_id' => self::TARGET_UID,
        ]);

        $userRepo    = $this->createMock(UserRepository::class);
        $meetingRepo = $this->createMock(MeetingRepository::class);

        $this->injectRepos([
            UserRepository::class    => $userRepo,
            MeetingRepository::class => $meetingRepo,
        ]);

        $resp = $this->callController(AdminController::class, 'meetingRoles');
        $this->assertSame(400, $resp['status']);
        $this->assertSame('unknown_action', $resp['body']['error']);
    }

    // =========================================================================
    // systemStatus() — GET
    // =========================================================================

    public function testSystemStatusWrongMethod(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $sysRepo     = $this->createMock(SystemRepository::class);
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $motionRepo  = $this->createMock(MotionRepository::class);
        $memberRepo  = $this->createMock(MemberRepository::class);
        $tokenRepo   = $this->createMock(VoteTokenRepository::class);

        $this->injectRepos([
            SystemRepository::class    => $sysRepo,
            MeetingRepository::class   => $meetingRepo,
            MotionRepository::class    => $motionRepo,
            MemberRepository::class    => $memberRepo,
            VoteTokenRepository::class => $tokenRepo,
        ]);

        $resp = $this->callController(AdminController::class, 'systemStatus');
        $this->assertSame(405, $resp['status']);
    }

    public function testSystemStatusGetReturnsSystemData(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $sysRepo = $this->createMock(SystemRepository::class);
        $sysRepo->method('dbPing')->willReturn(5.0);
        $sysRepo->method('dbActiveConnections')->willReturn(3);
        $sysRepo->method('countAuditEvents')->willReturn(100);
        $sysRepo->method('countAuthFailures15m')->willReturn(0);
        $sysRepo->method('listRecentAlerts')->willReturn([]);
        $sysRepo->method('insertSystemMetric')->willReturnSelf();
        $sysRepo->method('findRecentAlert')->willReturn(false);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('countForTenant')->willReturn(5);
        $meetingRepo->method('countLive')->willReturn(1);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('countAll')->willReturn(20);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(50);

        $tokenRepo = $this->createMock(VoteTokenRepository::class);
        $tokenRepo->method('countAll')->willReturn(200);

        $this->injectRepos([
            SystemRepository::class    => $sysRepo,
            MeetingRepository::class   => $meetingRepo,
            MotionRepository::class    => $motionRepo,
            MemberRepository::class    => $memberRepo,
            VoteTokenRepository::class => $tokenRepo,
        ]);

        $resp = $this->callController(AdminController::class, 'systemStatus');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertArrayHasKey('system', $data);
        $this->assertArrayHasKey('alerts', $data);
        $this->assertSame(5.0, $data['system']['db_latency_ms']);
    }

    public function testSystemStatusGeneratesAlertOnHighAuthFailures(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $sysRepo = $this->createMock(SystemRepository::class);
        $sysRepo->method('dbPing')->willReturn(10.0);
        $sysRepo->method('dbActiveConnections')->willReturn(1);
        $sysRepo->method('countAuditEvents')->willReturn(0);
        $sysRepo->method('countAuthFailures15m')->willReturn(10); // > 5 → alert
        $sysRepo->method('listRecentAlerts')->willReturn([]);
        $sysRepo->method('insertSystemMetric')->willReturnSelf();
        $sysRepo->method('findRecentAlert')->willReturn(false);
        $sysRepo->expects($this->atLeastOnce())->method('insertSystemAlert');

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('countForTenant')->willReturn(0);
        $meetingRepo->method('countLive')->willReturn(0);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('countAll')->willReturn(0);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(0);

        $tokenRepo = $this->createMock(VoteTokenRepository::class);
        $tokenRepo->method('countAll')->willReturn(0);

        $this->injectRepos([
            SystemRepository::class    => $sysRepo,
            MeetingRepository::class   => $meetingRepo,
            MotionRepository::class    => $motionRepo,
            MemberRepository::class    => $memberRepo,
            VoteTokenRepository::class => $tokenRepo,
        ]);

        $resp = $this->callController(AdminController::class, 'systemStatus');
        $this->assertSame(200, $resp['status']);
    }

    // =========================================================================
    // auditLog() — GET
    // =========================================================================

    public function testAuditLogWrongMethod(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $auditRepo = $this->createMock(AuditEventRepository::class);
        $this->injectRepos([AuditEventRepository::class => $auditRepo]);

        $resp = $this->callController(AdminController::class, 'auditLog');
        $this->assertSame(405, $resp['status']);
    }

    public function testAuditLogGetReturnsFormattedEvents(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['limit' => '10', 'offset' => '0']);

        $events = [
            [
                'id' => 'ev-001',
                'action' => 'admin.user.created',
                'resource_type' => 'user',
                'resource_id' => self::TARGET_UID,
                'actor_role' => 'admin',
                'actor_user_id' => self::USER_ID,
                'ip_address' => '127.0.0.1',
                'created_at' => '2026-01-01T10:00:00Z',
                'payload' => json_encode(['email' => 'new@example.com', 'role' => 'viewer']),
            ],
        ];

        $auditRepo = $this->createMock(AuditEventRepository::class);
        $auditRepo->method('countAdminEvents')->willReturn(1);
        $auditRepo->method('searchAdminEvents')->willReturn($events);
        $auditRepo->method('listDistinctAdminActions')->willReturn([
            ['action' => 'admin.user.created'],
        ]);

        $this->injectRepos([AuditEventRepository::class => $auditRepo]);

        $resp = $this->callController(AdminController::class, 'auditLog');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertSame(1, $data['total']);
        $this->assertCount(1, $data['items']);
        $event = $data['items'][0];
        $this->assertSame('admin.user.created', $event['action']);
        $this->assertSame('Utilisateur créé', $event['action_label']);
        $this->assertStringContainsString('new@example.com', $event['detail']);
    }

    public function testAuditLogLimitClamped(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['limit' => '9999']); // should be clamped to 200

        $auditRepo = $this->createMock(AuditEventRepository::class);
        $auditRepo->method('countAdminEvents')->willReturn(0);
        $auditRepo->method('searchAdminEvents')->willReturn([]);
        $auditRepo->method('listDistinctAdminActions')->willReturn([]);

        $this->injectRepos([AuditEventRepository::class => $auditRepo]);

        $resp = $this->callController(AdminController::class, 'auditLog');
        $this->assertSame(200, $resp['status']);
        $this->assertSame(200, $resp['body']['data']['limit']);
    }

    public function testAuditLogWithActionFilter(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['action' => 'admin.user.deleted']);

        $auditRepo = $this->createMock(AuditEventRepository::class);
        $auditRepo->method('countAdminEvents')->with(self::TENANT_ID, 'admin.user.deleted', null)->willReturn(0);
        $auditRepo->method('searchAdminEvents')->willReturn([]);
        $auditRepo->method('listDistinctAdminActions')->willReturn([]);

        $this->injectRepos([AuditEventRepository::class => $auditRepo]);

        $resp = $this->callController(AdminController::class, 'auditLog');
        $this->assertSame(200, $resp['status']);
    }
}
