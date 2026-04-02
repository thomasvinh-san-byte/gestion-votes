<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\MembersController;
use AgVote\Repository\MemberGroupRepository;
use AgVote\Repository\MemberRepository;

/**
 * Unit tests for MembersController.
 *
 * Endpoints:
 *  - index():        GET           — list all members
 *  - create():       POST          — create a member
 *  - updateMember(): PATCH or PUT  — update a member
 *  - delete():       DELETE        — soft-delete a member
 *  - presidents():   GET           — list members eligible as president
 *
 * Response structure:
 *   - api_ok($data)  => body['data'][...] (wrapped in 'data' key)
 *   - api_fail(...)  => body['error'] (direct)
 *
 * Pattern: extends ControllerTestCase, uses injectRepos() + callController().
 */
class MembersControllerTest extends ControllerTestCase
{
    private const TENANT    = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const MEMBER_ID = 'aaaaaaaa-1111-2222-3333-000000000080';
    private const USER_ID   = 'aaaaaaaa-0000-0000-0000-000000000080';
    private const GROUP_ID  = 'bbbbbbbb-1111-2222-3333-000000000001';

    // =========================================================================
    // CONTROLLER STRUCTURE
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(MembersController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerHasRequiredMethods(): void
    {
        foreach (['index', 'create', 'updateMember', 'delete', 'presidents'] as $method) {
            $this->assertTrue(method_exists(MembersController::class, $method));
        }
    }

    // =========================================================================
    // index() — GET
    // =========================================================================

    public function testIndexRequiresGet(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $repo = $this->createMock(MemberRepository::class);
        $this->injectRepos([MemberRepository::class => $repo]);

        $res = $this->callController(MembersController::class, 'index');

        $this->assertSame(405, $res['status']);
    }

    public function testIndexReturnsMembers(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $repo = $this->createMock(MemberRepository::class);
        $repo->method('listPaginated')->willReturn([
            ['id' => self::MEMBER_ID, 'full_name' => 'Alice Martin'],
        ]);
        $repo->method('countAll')->willReturn(1);

        $this->injectRepos([MemberRepository::class => $repo]);

        $res = $this->callController(MembersController::class, 'index');

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['body']['data']['items']);
        $this->assertSame(self::MEMBER_ID, $res['body']['data']['items'][0]['id']);
    }

    public function testIndexReturnsPaginationMeta(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['page' => '1', 'per_page' => '50']);

        $repo = $this->createMock(MemberRepository::class);
        $repo->method('listPaginated')->willReturn([
            ['id' => 'aaa', 'full_name' => 'Alice'],
            ['id' => 'bbb', 'full_name' => 'Bob'],
        ]);
        $repo->method('countAll')->willReturn(47);

        $this->injectRepos([MemberRepository::class => $repo]);

        $res = $this->callController(MembersController::class, 'index');

        $this->assertSame(200, $res['status']);
        $pagination = $res['body']['data']['pagination'];
        $this->assertSame(47, $pagination['total']);
        $this->assertSame(1, $pagination['page']);
        $this->assertSame(50, $pagination['per_page']);
        $this->assertSame(1, $pagination['total_pages']);
    }

    public function testIndexCallsListPaginatedNotListAll(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $repo = $this->createMock(MemberRepository::class);
        $repo->expects($this->once())->method('listPaginated')->willReturn([]);
        $repo->expects($this->never())->method('listAll');
        $repo->method('countAll')->willReturn(0);

        $this->injectRepos([MemberRepository::class => $repo]);

        $res = $this->callController(MembersController::class, 'index');

        $this->assertSame(200, $res['status']);
    }

    public function testIndexWithIncludeGroupsFetchesGroups(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['include_groups' => '1']);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('listPaginated')->willReturn([
            ['id' => self::MEMBER_ID, 'full_name' => 'Alice Martin'],
        ]);
        $memberRepo->method('countAll')->willReturn(1);

        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('listGroupsForMembers')->willReturn([
            self::MEMBER_ID => [['id' => 'aaaabbbb-1111-2222-3333-000000000001', 'name' => 'Group A']],
        ]);

        $this->injectRepos([
            MemberRepository::class      => $memberRepo,
            MemberGroupRepository::class => $groupRepo,
        ]);

        $res = $this->callController(MembersController::class, 'index');

        $this->assertSame(200, $res['status']);
        $items = $res['body']['data']['items'];
        $this->assertCount(1, $items);
        $this->assertArrayHasKey('groups', $items[0]);
        $this->assertCount(1, $items[0]['groups']);
    }

    // =========================================================================
    // create() — POST
    // =========================================================================

    public function testCreateRequiresPost(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');

        $repo = $this->createMock(MemberRepository::class);
        $this->injectRepos([MemberRepository::class => $repo]);

        $res = $this->callController(MembersController::class, 'create');

        $this->assertSame(405, $res['status']);
    }

    public function testCreateValidationFailsWithMissingName(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['email' => 'alice@example.com']);

        $repo = $this->createMock(MemberRepository::class);
        $this->injectRepos([MemberRepository::class => $repo]);

        $res = $this->callController(MembersController::class, 'create');

        $this->assertSame(422, $res['status']);
        $this->assertSame('validation_failed', $res['body']['error']);
    }

    public function testCreateSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'full_name' => 'Alice Martin',
            'email'     => 'alice@example.com',
        ]);

        $repo = $this->createMock(MemberRepository::class);
        $repo->expects($this->once())->method('create');

        $this->injectRepos([MemberRepository::class => $repo]);

        $res = $this->callController(MembersController::class, 'create');

        $this->assertSame(201, $res['status']);
        $data = $res['body']['data'];
        $this->assertSame('Alice Martin', $data['full_name']);
        $this->assertArrayHasKey('member_id', $data);
    }

    // =========================================================================
    // updateMember() — PATCH / PUT
    // =========================================================================

    public function testUpdateMemberMissingIdReturns422(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('PATCH');
        $this->injectJsonBody(['full_name' => 'Updated Name']);

        $repo = $this->createMock(MemberRepository::class);
        $this->injectRepos([MemberRepository::class => $repo]);

        $res = $this->callController(MembersController::class, 'updateMember');

        $this->assertSame(422, $res['status']);
        $this->assertSame('missing_member_id', $res['body']['error']);
    }

    public function testUpdateMemberNotFoundReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('PATCH');
        $this->injectJsonBody(['id' => self::MEMBER_ID, 'full_name' => 'Alice']);

        $repo = $this->createMock(MemberRepository::class);
        $repo->method('existsForTenant')->willReturn(false);

        $this->injectRepos([MemberRepository::class => $repo]);

        $res = $this->callController(MembersController::class, 'updateMember');

        $this->assertSame(404, $res['status']);
        $this->assertSame('member_not_found', $res['body']['error']);
    }

    public function testUpdateMemberSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('PATCH');
        $this->injectJsonBody([
            'id'        => self::MEMBER_ID,
            'full_name' => 'Alice Updated',
        ]);

        $repo = $this->createMock(MemberRepository::class);
        $repo->method('existsForTenant')->willReturn(true);
        $repo->expects($this->once())->method('updateImport');

        $this->injectRepos([MemberRepository::class => $repo]);

        $res = $this->callController(MembersController::class, 'updateMember');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        $this->assertSame(self::MEMBER_ID, $data['member_id']);
        $this->assertSame('Alice Updated', $data['full_name']);
    }

    // =========================================================================
    // delete() — DELETE
    // =========================================================================

    public function testDeleteRequiresDelete(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');

        $repo = $this->createMock(MemberRepository::class);
        $this->injectRepos([MemberRepository::class => $repo]);

        $res = $this->callController(MembersController::class, 'delete');

        $this->assertSame(405, $res['status']);
    }

    public function testDeleteMissingIdReturns422(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('DELETE');
        $this->injectJsonBody([]);

        $repo = $this->createMock(MemberRepository::class);
        $this->injectRepos([MemberRepository::class => $repo]);

        $res = $this->callController(MembersController::class, 'delete');

        $this->assertSame(422, $res['status']);
        $this->assertSame('missing_member_id', $res['body']['error']);
    }

    public function testDeleteNotFoundReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('DELETE');
        $this->injectJsonBody(['id' => self::MEMBER_ID]);

        $repo = $this->createMock(MemberRepository::class);
        $repo->method('existsForTenant')->willReturn(false);

        $this->injectRepos([MemberRepository::class => $repo]);

        $res = $this->callController(MembersController::class, 'delete');

        $this->assertSame(404, $res['status']);
        $this->assertSame('member_not_found', $res['body']['error']);
    }

    public function testDeleteSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('DELETE');
        $this->injectJsonBody(['id' => self::MEMBER_ID]);

        $repo = $this->createMock(MemberRepository::class);
        $repo->method('existsForTenant')->willReturn(true);
        $repo->expects($this->once())->method('softDelete');

        $this->injectRepos([MemberRepository::class => $repo]);

        $res = $this->callController(MembersController::class, 'delete');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        $this->assertSame(self::MEMBER_ID, $data['member_id']);
        $this->assertTrue($data['deleted']);
    }

    // =========================================================================
    // presidents() — GET
    // =========================================================================

    public function testPresidentsRequiresGet(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $repo = $this->createMock(MemberRepository::class);
        $this->injectRepos([MemberRepository::class => $repo]);

        $res = $this->callController(MembersController::class, 'presidents');

        $this->assertSame(405, $res['status']);
    }

    public function testPresidentsReturnsItems(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $repo = $this->createMock(MemberRepository::class);
        $repo->method('listActiveForPresident')->willReturn([
            ['id' => self::MEMBER_ID, 'full_name' => 'Alice Martin'],
        ]);

        $this->injectRepos([MemberRepository::class => $repo]);

        $res = $this->callController(MembersController::class, 'presidents');

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['body']['data']['items']);
    }

    // =========================================================================
    // index() — search filter (Finding 4)
    // =========================================================================

    public function testIndexSearchFiltersResults(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['search' => 'dupont']);

        $repo = $this->createMock(MemberRepository::class);
        $repo->expects($this->once())->method('listPaginatedFiltered')
            ->willReturn([['id' => self::MEMBER_ID, 'full_name' => 'Dupont Jean']]);
        $repo->expects($this->once())->method('countFiltered')->willReturn(1);
        $repo->expects($this->never())->method('listPaginated');
        $repo->expects($this->never())->method('countAll');

        $this->injectRepos([MemberRepository::class => $repo]);

        $res = $this->callController(MembersController::class, 'index');

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['body']['data']['items']);
    }

    public function testIndexNoSearchUsesUnfiltered(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $repo = $this->createMock(MemberRepository::class);
        $repo->expects($this->once())->method('listPaginated')->willReturn([]);
        $repo->expects($this->once())->method('countAll')->willReturn(0);
        $repo->expects($this->never())->method('listPaginatedFiltered');
        $repo->expects($this->never())->method('countFiltered');

        $this->injectRepos([MemberRepository::class => $repo]);

        $res = $this->callController(MembersController::class, 'index');

        $this->assertSame(200, $res['status']);
    }

    // =========================================================================
    // index() — batch groups (Finding 3)
    // =========================================================================

    public function testIndexIncludeGroupsUsesBatch(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['include_groups' => '1']);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('listPaginated')->willReturn([
            ['id' => self::MEMBER_ID, 'full_name' => 'Alice Martin'],
        ]);
        $memberRepo->method('countAll')->willReturn(1);

        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->expects($this->once())->method('listGroupsForMembers')
            ->with([self::MEMBER_ID], self::TENANT)
            ->willReturn([self::MEMBER_ID => [['id' => 'grp-1', 'name' => 'Group A']]]);
        $groupRepo->expects($this->never())->method('listGroupsForMember');

        $this->injectRepos([
            MemberRepository::class      => $memberRepo,
            MemberGroupRepository::class => $groupRepo,
        ]);

        $res = $this->callController(MembersController::class, 'index');

        $this->assertSame(200, $res['status']);
        $items = $res['body']['data']['items'];
        $this->assertCount(1, $items);
        $this->assertArrayHasKey('groups', $items[0]);
    }

    // =========================================================================
    // bulk() — POST /api/v1/members_bulk (Finding 5)
    // =========================================================================

    public function testBulkAssignGroupSuccess(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'operation'  => 'assign_group',
            'member_ids' => [self::MEMBER_ID],
            'group_id'   => self::GROUP_ID,
        ]);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('filterExistingIds')->willReturn([self::MEMBER_ID]);

        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->expects($this->once())->method('bulkAssignToGroup')
            ->with(self::GROUP_ID, [self::MEMBER_ID])
            ->willReturn(1);

        $this->injectRepos([
            MemberRepository::class      => $memberRepo,
            MemberGroupRepository::class => $groupRepo,
        ]);

        $res = $this->callController(MembersController::class, 'bulk');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        $this->assertSame('assign_group', $data['operation']);
        $this->assertSame(1, $data['affected']);
    }

    public function testBulkUpdateVotingPowerSuccess(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'operation'    => 'update_voting_power',
            'member_ids'   => [self::MEMBER_ID],
            'voting_power' => 2.5,
        ]);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('filterExistingIds')->willReturn([self::MEMBER_ID]);
        $memberRepo->expects($this->once())->method('bulkUpdateVotingPower')
            ->with([self::MEMBER_ID], self::TENANT, 2.5)
            ->willReturn(1);

        $this->injectRepos([MemberRepository::class => $memberRepo]);

        $res = $this->callController(MembersController::class, 'bulk');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        $this->assertSame('update_voting_power', $data['operation']);
        $this->assertSame(1, $data['affected']);
    }

    public function testBulkMissingOperationFails(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'member_ids' => [self::MEMBER_ID],
        ]);

        $this->injectRepos([MemberRepository::class => $this->createMock(MemberRepository::class)]);

        $res = $this->callController(MembersController::class, 'bulk');

        $this->assertSame(422, $res['status']);
    }

    public function testBulkInvalidOperationFails(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'operation'  => 'delete_all',
            'member_ids' => [self::MEMBER_ID],
        ]);

        $this->injectRepos([MemberRepository::class => $this->createMock(MemberRepository::class)]);

        $res = $this->callController(MembersController::class, 'bulk');

        $this->assertSame(422, $res['status']);
    }
}
