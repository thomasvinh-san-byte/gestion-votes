<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\PoliciesController;
use AgVote\Repository\PolicyRepository;

/**
 * Unit tests for PoliciesController.
 *
 * Endpoints:
 *  - listQuorum():  GET  — public list of quorum policies
 *  - listVote():    GET  — public list of vote policies
 *  - adminQuorum(): GET/POST — admin CRUD for quorum policies
 *  - adminVote():   GET/POST — admin CRUD for vote policies
 *
 * Response structure:
 *   - api_ok($data)  => body['data'][...] (wrapped in 'data' key)
 *   - api_fail(...)  => body['error'] (direct)
 *
 * Pattern: extends ControllerTestCase, uses injectRepos() + callController().
 */
class PoliciesControllerTest extends ControllerTestCase
{
    private const TENANT    = 'tenant-uuid-001';
    private const POLICY_ID = 'aaaaaaaa-1111-2222-3333-000000000060';
    private const USER_ID   = 'user-uuid-0060';

    // =========================================================================
    // CONTROLLER STRUCTURE
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(PoliciesController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerHasRequiredMethods(): void
    {
        foreach (['listQuorum', 'listVote', 'adminQuorum', 'adminVote'] as $method) {
            $this->assertTrue(method_exists(PoliciesController::class, $method));
        }
    }

    // =========================================================================
    // listQuorum() — GET
    // =========================================================================

    public function testListQuorumRequiresGet(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $repo = $this->createMock(PolicyRepository::class);
        $this->injectRepos([PolicyRepository::class => $repo]);

        $res = $this->callController(PoliciesController::class, 'listQuorum');

        $this->assertSame(405, $res['status']);
    }

    public function testListQuorumReturnsItems(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $repo = $this->createMock(PolicyRepository::class);
        $repo->method('listQuorumPolicies')->willReturn([
            ['id' => self::POLICY_ID, 'name' => 'Quorum 50%'],
        ]);

        $this->injectRepos([PolicyRepository::class => $repo]);

        $res = $this->callController(PoliciesController::class, 'listQuorum');

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['body']['data']['items']);
        $this->assertSame(self::POLICY_ID, $res['body']['data']['items'][0]['id']);
    }

    // =========================================================================
    // listVote() — GET
    // =========================================================================

    public function testListVoteRequiresGet(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $repo = $this->createMock(PolicyRepository::class);
        $this->injectRepos([PolicyRepository::class => $repo]);

        $res = $this->callController(PoliciesController::class, 'listVote');

        $this->assertSame(405, $res['status']);
    }

    public function testListVoteReturnsItems(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $repo = $this->createMock(PolicyRepository::class);
        $repo->method('listVotePolicies')->willReturn([
            ['id' => self::POLICY_ID, 'name' => 'Majorite simple'],
        ]);

        $this->injectRepos([PolicyRepository::class => $repo]);

        $res = $this->callController(PoliciesController::class, 'listVote');

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['body']['data']['items']);
    }

    // =========================================================================
    // adminQuorum() — GET
    // =========================================================================

    public function testAdminQuorumGetReturnsItems(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $repo = $this->createMock(PolicyRepository::class);
        $repo->method('listQuorumPolicies')->willReturn([
            ['id' => self::POLICY_ID, 'name' => 'Quorum 33%', 'threshold' => 0.33],
        ]);

        $this->injectRepos([PolicyRepository::class => $repo]);

        $res = $this->callController(PoliciesController::class, 'adminQuorum');

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['body']['data']['items']);
    }

    // =========================================================================
    // adminQuorum() — POST delete action
    // =========================================================================

    public function testAdminQuorumDeleteMissingIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['action' => 'delete', 'id' => '']);

        $repo = $this->createMock(PolicyRepository::class);
        $this->injectRepos([PolicyRepository::class => $repo]);

        $res = $this->callController(PoliciesController::class, 'adminQuorum');

        $this->assertSame(400, $res['status']);
        $this->assertSame('missing_id', $res['body']['error']);
    }

    public function testAdminQuorumDeleteSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['action' => 'delete', 'id' => self::POLICY_ID]);

        $repo = $this->createMock(PolicyRepository::class);
        $repo->expects($this->once())->method('deleteQuorumPolicy');

        $this->injectRepos([PolicyRepository::class => $repo]);

        $res = $this->callController(PoliciesController::class, 'adminQuorum');

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['body']['data']['deleted']);
    }

    // =========================================================================
    // adminQuorum() — POST create/update
    // =========================================================================

    public function testAdminQuorumCreateValidationFailsWithMissingName(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'threshold' => 0.5,
        ]);

        $repo = $this->createMock(PolicyRepository::class);
        $this->injectRepos([PolicyRepository::class => $repo]);

        $res = $this->callController(PoliciesController::class, 'adminQuorum');

        $this->assertSame(422, $res['status']);
        $this->assertSame('validation_failed', $res['body']['error']);
    }

    public function testAdminQuorumCreateSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'name'      => 'Quorum 50%',
            'threshold' => 0.5,
        ]);

        $repo = $this->createMock(PolicyRepository::class);
        $repo->method('generateUuid')->willReturn(self::POLICY_ID);
        $repo->expects($this->once())->method('createQuorumPolicy');

        $this->injectRepos([PolicyRepository::class => $repo]);

        $res = $this->callController(PoliciesController::class, 'adminQuorum');

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['body']['data']['saved']);
        $this->assertSame(self::POLICY_ID, $res['body']['data']['id']);
    }

    public function testAdminQuorumUpdateSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'id'        => self::POLICY_ID,
            'name'      => 'Quorum 60%',
            'threshold' => 0.6,
        ]);

        $repo = $this->createMock(PolicyRepository::class);
        $repo->expects($this->once())->method('updateQuorumPolicy');

        $this->injectRepos([PolicyRepository::class => $repo]);

        $res = $this->callController(PoliciesController::class, 'adminQuorum');

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['body']['data']['saved']);
    }

    public function testAdminQuorumMethodNotAllowedReturns405(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('DELETE');

        $repo = $this->createMock(PolicyRepository::class);
        $this->injectRepos([PolicyRepository::class => $repo]);

        $res = $this->callController(PoliciesController::class, 'adminQuorum');

        $this->assertSame(405, $res['status']);
    }

    // =========================================================================
    // adminVote() — GET
    // =========================================================================

    public function testAdminVoteGetReturnsItems(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $repo = $this->createMock(PolicyRepository::class);
        $repo->method('listVotePolicies')->willReturn([
            ['id' => self::POLICY_ID, 'name' => 'Majorite 50%'],
        ]);

        $this->injectRepos([PolicyRepository::class => $repo]);

        $res = $this->callController(PoliciesController::class, 'adminVote');

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['body']['data']['items']);
    }

    public function testAdminVoteDeleteSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['action' => 'delete', 'id' => self::POLICY_ID]);

        $repo = $this->createMock(PolicyRepository::class);
        $repo->expects($this->once())->method('deleteVotePolicy');

        $this->injectRepos([PolicyRepository::class => $repo]);

        $res = $this->callController(PoliciesController::class, 'adminVote');

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['body']['data']['deleted']);
    }

    public function testAdminVoteCreateSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'name'      => 'Majorite simple',
            'threshold' => 0.5,
        ]);

        $repo = $this->createMock(PolicyRepository::class);
        $repo->method('generateUuid')->willReturn(self::POLICY_ID);
        $repo->expects($this->once())->method('createVotePolicy');

        $this->injectRepos([PolicyRepository::class => $repo]);

        $res = $this->callController(PoliciesController::class, 'adminVote');

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['body']['data']['saved']);
    }

    public function testAdminVoteUpdateSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'id'        => self::POLICY_ID,
            'name'      => 'Majorite qualifiee',
            'threshold' => 0.67,
        ]);

        $repo = $this->createMock(PolicyRepository::class);
        $repo->expects($this->once())->method('updateVotePolicy');

        $this->injectRepos([PolicyRepository::class => $repo]);

        $res = $this->callController(PoliciesController::class, 'adminVote');

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['body']['data']['saved']);
    }

    public function testAdminVoteMethodNotAllowedReturns405(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('DELETE');

        $repo = $this->createMock(PolicyRepository::class);
        $this->injectRepos([PolicyRepository::class => $repo]);

        $res = $this->callController(PoliciesController::class, 'adminVote');

        $this->assertSame(405, $res['status']);
    }
}
