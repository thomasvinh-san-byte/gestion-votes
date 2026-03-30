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
    private const TENANT    = 'tenant-uuid-001';
    private const MEMBER_ID = 'aaaaaaaa-1111-2222-3333-000000000080';
    private const USER_ID   = 'user-uuid-0080';

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
        $repo->method('listAll')->willReturn([
            ['id' => self::MEMBER_ID, 'full_name' => 'Alice Martin'],
        ]);

        $this->injectRepos([MemberRepository::class => $repo]);

        $res = $this->callController(MembersController::class, 'index');

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['body']['data']['items']);
        $this->assertSame(self::MEMBER_ID, $res['body']['data']['items'][0]['id']);
    }

    public function testIndexWithIncludeGroupsFetchesGroups(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['include_groups' => '1']);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('listAll')->willReturn([
            ['id' => self::MEMBER_ID, 'full_name' => 'Alice Martin'],
        ]);

        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('listGroupsForMember')->willReturn([
            ['id' => 'group-id-01', 'name' => 'Group A'],
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
}
