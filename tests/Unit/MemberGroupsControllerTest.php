<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\MemberGroupsController;
use AgVote\Repository\MemberGroupRepository;
use AgVote\Repository\MemberRepository;

/**
 * Unit tests for MemberGroupsController.
 *
 * Endpoints:
 *  - list():          GET — list all groups OR single group with members
 *  - create():        POST — create new group
 *  - update():        PATCH — update group
 *  - delete():        DELETE — delete group
 *  - assign():        POST — assign member to group
 *  - unassign():      DELETE (via query) — remove member from group
 *  - setMemberGroups(): PUT — bulk set groups for a member
 *
 * Extends ControllerTestCase for RepositoryFactory injection.
 */
class MemberGroupsControllerTest extends ControllerTestCase
{
    private const TENANT_ID  = 'aa000004-0000-4000-a000-000000000004';
    private const GROUP_ID   = 'aa000001-0000-4000-a000-000000000001';
    private const MEMBER_ID  = 'aa000002-0000-4000-a000-000000000002';
    private const USER_ID    = 'aa000003-0000-4000-a000-000000000003';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setAuth(self::USER_ID, 'admin', self::TENANT_ID);
    }

    // =========================================================================
    // STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(MemberGroupsController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerExtendsAbstractController(): void
    {
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, new MemberGroupsController());
    }

    public function testControllerHasExpectedMethods(): void
    {
        $ref = new \ReflectionClass(MemberGroupsController::class);
        foreach (['list', 'create', 'update', 'delete', 'assign', 'unassign', 'setMemberGroups'] as $m) {
            $this->assertTrue($ref->hasMethod($m), "Missing method: {$m}");
        }
    }

    // =========================================================================
    // list() — no id param
    // =========================================================================

    public function testListReturnsAllGroups(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('listForTenant')->willReturn([
            ['id' => self::GROUP_ID, 'name' => 'Board', 'member_count' => 3],
            ['id' => 'bb000002-0000-4000-a000-000000000002', 'name' => 'Staff', 'member_count' => 5],
        ]);
        $this->injectRepos([MemberGroupRepository::class => $groupRepo]);

        $this->setQueryParams([]);
        $result = $this->callController(MemberGroupsController::class, 'list');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertEquals(2, $data['total']);
    }

    public function testListWithIncludeInactive(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('listForTenant')->willReturn([]);
        $this->injectRepos([MemberGroupRepository::class => $groupRepo]);

        $this->setQueryParams(['include_inactive' => '1']);
        $result = $this->callController(MemberGroupsController::class, 'list');
        $this->assertEquals(200, $result['status']);
    }

    // =========================================================================
    // list() — with id param (single group)
    // =========================================================================

    public function testListSingleGroupInvalidId(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $this->injectRepos([MemberGroupRepository::class => $groupRepo]);

        $this->setQueryParams(['id' => 'not-a-uuid']);
        $result = $this->callController(MemberGroupsController::class, 'list');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_group_id', $result['body']['error']);
    }

    public function testListSingleGroupNotFound(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('findById')->willReturn(null);
        $this->injectRepos([MemberGroupRepository::class => $groupRepo]);

        $this->setQueryParams(['id' => self::GROUP_ID]);
        $result = $this->callController(MemberGroupsController::class, 'list');
        $this->assertEquals(404, $result['status']);
        $this->assertEquals('group_not_found', $result['body']['error']);
    }

    public function testListSingleGroupFound(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('findById')->willReturn([
            'id' => self::GROUP_ID, 'name' => 'Board', 'tenant_id' => self::TENANT_ID,
        ]);
        $groupRepo->method('listMembersInGroup')->willReturn([
            ['id' => self::MEMBER_ID, 'full_name' => 'Alice'],
        ]);
        $this->injectRepos([MemberGroupRepository::class => $groupRepo]);

        $this->setQueryParams(['id' => self::GROUP_ID]);
        $result = $this->callController(MemberGroupsController::class, 'list');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertArrayHasKey('group', $data);
        $this->assertArrayHasKey('members', $data['group']);
        $this->assertCount(1, $data['group']['members']);
    }

    // =========================================================================
    // create()
    // =========================================================================

    public function testCreateMethodEnforcement(): void
    {
        $this->setHttpMethod('GET');
        $result = $this->callController(MemberGroupsController::class, 'create');
        $this->assertEquals(405, $result['status']);
    }

    public function testCreateMissingName(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $this->injectRepos([MemberGroupRepository::class => $groupRepo]);

        $this->setHttpMethod('POST');
        $this->injectJsonBody(['name' => '']);
        $result = $this->callController(MemberGroupsController::class, 'create');
        // InvalidArgumentException → 422 invalid_request
        $this->assertEquals(422, $result['status']);
    }

    public function testCreateNameTooLong(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('nameExists')->willReturn(false);
        $this->injectRepos([MemberGroupRepository::class => $groupRepo]);

        $this->setHttpMethod('POST');
        $this->injectJsonBody(['name' => str_repeat('A', 101)]);
        $result = $this->callController(MemberGroupsController::class, 'create');
        $this->assertEquals(422, $result['status']);
    }

    public function testCreateInvalidColorFormat(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $this->injectRepos([MemberGroupRepository::class => $groupRepo]);

        $this->setHttpMethod('POST');
        $this->injectJsonBody(['name' => 'Test', 'color' => 'red']);
        $result = $this->callController(MemberGroupsController::class, 'create');
        $this->assertEquals(422, $result['status']);
    }

    public function testCreateDuplicateName(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('nameExists')->willReturn(true);
        $this->injectRepos([MemberGroupRepository::class => $groupRepo]);

        $this->setHttpMethod('POST');
        $this->injectJsonBody(['name' => 'Board']);
        $result = $this->callController(MemberGroupsController::class, 'create');
        $this->assertEquals(422, $result['status']);
    }

    public function testCreateSuccess(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('nameExists')->willReturn(false);
        $groupRepo->method('create')->willReturn([
            'id' => self::GROUP_ID,
            'name' => 'Board',
            'tenant_id' => self::TENANT_ID,
        ]);
        $this->injectRepos([MemberGroupRepository::class => $groupRepo]);

        $this->setHttpMethod('POST');
        $this->injectJsonBody(['name' => 'Board', 'color' => '#FF0000']);
        $result = $this->callController(MemberGroupsController::class, 'create');
        $this->assertEquals(201, $result['status']);
        $this->assertArrayHasKey('group', $result['body']['data']);
    }

    // =========================================================================
    // update()
    // =========================================================================

    public function testUpdateMethodEnforcement(): void
    {
        $this->setHttpMethod('GET');
        $result = $this->callController(MemberGroupsController::class, 'update');
        $this->assertEquals(405, $result['status']);
    }

    public function testUpdateInvalidGroupId(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $this->injectRepos([MemberGroupRepository::class => $groupRepo]);

        $this->setHttpMethod('PATCH');
        $this->injectJsonBody(['id' => 'bad-uuid', 'name' => 'Board']);
        $result = $this->callController(MemberGroupsController::class, 'update');
        $this->assertEquals(422, $result['status']);
    }

    public function testUpdateMissingName(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $this->injectRepos([MemberGroupRepository::class => $groupRepo]);

        $this->setHttpMethod('PATCH');
        $this->injectJsonBody(['id' => self::GROUP_ID, 'name' => '']);
        $result = $this->callController(MemberGroupsController::class, 'update');
        $this->assertEquals(422, $result['status']);
    }

    public function testUpdateGroupNotFound(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('findById')->willReturn(null);
        $this->injectRepos([MemberGroupRepository::class => $groupRepo]);

        $this->setHttpMethod('PATCH');
        $this->injectJsonBody(['id' => self::GROUP_ID, 'name' => 'Board']);
        $result = $this->callController(MemberGroupsController::class, 'update');
        $this->assertEquals(404, $result['status']);
        $this->assertEquals('group_not_found', $result['body']['error']);
    }

    public function testUpdateSuccess(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('findById')->willReturn(['id' => self::GROUP_ID, 'name' => 'OldName']);
        $groupRepo->method('nameExists')->willReturn(false);
        $groupRepo->method('update')->willReturn(['id' => self::GROUP_ID, 'name' => 'NewName']);
        $this->injectRepos([MemberGroupRepository::class => $groupRepo]);

        $this->setHttpMethod('PATCH');
        $this->injectJsonBody(['id' => self::GROUP_ID, 'name' => 'NewName']);
        $result = $this->callController(MemberGroupsController::class, 'update');
        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('group', $result['body']['data']);
    }

    // =========================================================================
    // delete()
    // =========================================================================

    public function testDeleteInvalidGroupId(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $this->injectRepos([MemberGroupRepository::class => $groupRepo]);

        $this->setQueryParams(['id' => 'not-uuid']);
        $result = $this->callController(MemberGroupsController::class, 'delete');
        $this->assertEquals(422, $result['status']);
    }

    public function testDeleteGroupNotFound(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('findById')->willReturn(null);
        $this->injectRepos([MemberGroupRepository::class => $groupRepo]);

        $this->setQueryParams(['id' => self::GROUP_ID]);
        $result = $this->callController(MemberGroupsController::class, 'delete');
        $this->assertEquals(404, $result['status']);
    }

    public function testDeleteSuccess(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('findById')->willReturn(['id' => self::GROUP_ID, 'name' => 'Board', 'member_count' => 0]);
        $groupRepo->method('delete')->willReturn(true);
        $this->injectRepos([MemberGroupRepository::class => $groupRepo]);

        $this->setQueryParams(['id' => self::GROUP_ID]);
        $result = $this->callController(MemberGroupsController::class, 'delete');
        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['data']['deleted']);
    }

    // =========================================================================
    // assign()
    // =========================================================================

    public function testAssignMethodEnforcement(): void
    {
        $this->setHttpMethod('GET');
        $result = $this->callController(MemberGroupsController::class, 'assign');
        $this->assertEquals(405, $result['status']);
    }

    public function testAssignInvalidMemberId(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $memberRepo = $this->createMock(MemberRepository::class);
        $this->injectRepos([
            MemberGroupRepository::class => $groupRepo,
            MemberRepository::class      => $memberRepo,
        ]);

        $this->setHttpMethod('POST');
        $this->injectJsonBody(['member_id' => 'bad', 'group_id' => self::GROUP_ID]);
        $result = $this->callController(MemberGroupsController::class, 'assign');
        $this->assertEquals(422, $result['status']);
    }

    public function testAssignInvalidGroupId(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $memberRepo = $this->createMock(MemberRepository::class);
        $this->injectRepos([
            MemberGroupRepository::class => $groupRepo,
            MemberRepository::class      => $memberRepo,
        ]);

        $this->setHttpMethod('POST');
        $this->injectJsonBody(['member_id' => self::MEMBER_ID, 'group_id' => 'bad']);
        $result = $this->callController(MemberGroupsController::class, 'assign');
        $this->assertEquals(422, $result['status']);
    }

    public function testAssignMemberNotFound(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('findByIdForTenant')->willReturn(null);
        $this->injectRepos([
            MemberGroupRepository::class => $groupRepo,
            MemberRepository::class      => $memberRepo,
        ]);

        $this->setHttpMethod('POST');
        $this->injectJsonBody(['member_id' => self::MEMBER_ID, 'group_id' => self::GROUP_ID]);
        $result = $this->callController(MemberGroupsController::class, 'assign');
        $this->assertEquals(404, $result['status']);
        $this->assertEquals('member_not_found', $result['body']['error']);
    }

    public function testAssignGroupNotFound(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('findById')->willReturn(null);
        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('findByIdForTenant')->willReturn(['id' => self::MEMBER_ID, 'full_name' => 'Alice']);
        $this->injectRepos([
            MemberGroupRepository::class => $groupRepo,
            MemberRepository::class      => $memberRepo,
        ]);

        $this->setHttpMethod('POST');
        $this->injectJsonBody(['member_id' => self::MEMBER_ID, 'group_id' => self::GROUP_ID]);
        $result = $this->callController(MemberGroupsController::class, 'assign');
        $this->assertEquals(404, $result['status']);
        $this->assertEquals('group_not_found', $result['body']['error']);
    }

    public function testAssignSuccess(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('findById')->willReturn(['id' => self::GROUP_ID, 'name' => 'Board']);
        $groupRepo->method('assignMember')->willReturn(true);
        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('findByIdForTenant')->willReturn(['id' => self::MEMBER_ID, 'full_name' => 'Alice']);
        $this->injectRepos([
            MemberGroupRepository::class => $groupRepo,
            MemberRepository::class      => $memberRepo,
        ]);

        $this->setHttpMethod('POST');
        $this->injectJsonBody(['member_id' => self::MEMBER_ID, 'group_id' => self::GROUP_ID]);
        $result = $this->callController(MemberGroupsController::class, 'assign');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertTrue($data['assigned']);
        $this->assertEquals(self::MEMBER_ID, $data['member_id']);
        $this->assertEquals(self::GROUP_ID, $data['group_id']);
    }

    // =========================================================================
    // unassign()
    // =========================================================================

    public function testUnassignInvalidMemberId(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $memberRepo = $this->createMock(MemberRepository::class);
        $this->injectRepos([
            MemberGroupRepository::class => $groupRepo,
            MemberRepository::class      => $memberRepo,
        ]);

        $this->setQueryParams(['member_id' => 'bad', 'group_id' => self::GROUP_ID]);
        $result = $this->callController(MemberGroupsController::class, 'unassign');
        $this->assertEquals(422, $result['status']);
    }

    public function testUnassignSuccess(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('findById')->willReturn(['id' => self::GROUP_ID, 'name' => 'Board']);
        $groupRepo->method('unassignMember')->willReturn(true);
        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('findByIdForTenant')->willReturn(['id' => self::MEMBER_ID, 'full_name' => 'Alice']);
        $this->injectRepos([
            MemberGroupRepository::class => $groupRepo,
            MemberRepository::class      => $memberRepo,
        ]);

        $this->setQueryParams(['member_id' => self::MEMBER_ID, 'group_id' => self::GROUP_ID]);
        $result = $this->callController(MemberGroupsController::class, 'unassign');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertTrue($data['removed']);
    }

    // =========================================================================
    // setMemberGroups()
    // =========================================================================

    public function testSetMemberGroupsMethodEnforcement(): void
    {
        $this->setHttpMethod('POST');
        $result = $this->callController(MemberGroupsController::class, 'setMemberGroups');
        $this->assertEquals(405, $result['status']);
    }

    public function testSetMemberGroupsInvalidMemberId(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $memberRepo = $this->createMock(MemberRepository::class);
        $this->injectRepos([
            MemberGroupRepository::class => $groupRepo,
            MemberRepository::class      => $memberRepo,
        ]);

        $this->setHttpMethod('PUT');
        $this->injectJsonBody(['member_id' => 'bad', 'group_ids' => []]);
        $result = $this->callController(MemberGroupsController::class, 'setMemberGroups');
        $this->assertEquals(422, $result['status']);
    }

    public function testSetMemberGroupsMemberNotFound(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('findByIdForTenant')->willReturn(null);
        $this->injectRepos([
            MemberGroupRepository::class => $groupRepo,
            MemberRepository::class      => $memberRepo,
        ]);

        $this->setHttpMethod('PUT');
        $this->injectJsonBody(['member_id' => self::MEMBER_ID, 'group_ids' => []]);
        $result = $this->callController(MemberGroupsController::class, 'setMemberGroups');
        $this->assertEquals(404, $result['status']);
        $this->assertEquals('member_not_found', $result['body']['error']);
    }

    public function testSetMemberGroupsSuccess(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('findById')->willReturn(['id' => self::GROUP_ID, 'name' => 'Board']);
        $groupRepo->method('setMemberGroups'); // void return
        $groupRepo->method('listGroupsForMember')->willReturn([
            ['id' => self::GROUP_ID, 'name' => 'Board'],
        ]);
        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('findByIdForTenant')->willReturn(['id' => self::MEMBER_ID, 'full_name' => 'Alice']);
        $this->injectRepos([
            MemberGroupRepository::class => $groupRepo,
            MemberRepository::class      => $memberRepo,
        ]);

        $this->setHttpMethod('PUT');
        $this->injectJsonBody(['member_id' => self::MEMBER_ID, 'group_ids' => [self::GROUP_ID]]);
        $result = $this->callController(MemberGroupsController::class, 'setMemberGroups');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertEquals(self::MEMBER_ID, $data['member_id']);
        $this->assertEquals(1, $data['total']);
    }

    public function testSetMemberGroupsClearAllGroups(): void
    {
        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('setMemberGroups'); // void return
        $groupRepo->method('listGroupsForMember')->willReturn([]);
        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('findByIdForTenant')->willReturn(['id' => self::MEMBER_ID, 'full_name' => 'Alice']);
        $this->injectRepos([
            MemberGroupRepository::class => $groupRepo,
            MemberRepository::class      => $memberRepo,
        ]);

        $this->setHttpMethod('PUT');
        $this->injectJsonBody(['member_id' => self::MEMBER_ID, 'group_ids' => []]);
        $result = $this->callController(MemberGroupsController::class, 'setMemberGroups');
        $this->assertEquals(200, $result['status']);
        $this->assertEquals(0, $result['body']['data']['total']);
    }
}
