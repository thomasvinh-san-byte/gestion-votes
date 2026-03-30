<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AbstractController;
use AgVote\Controller\ProxiesController;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\ProxyRepository;

/**
 * Unit tests for ProxiesController.
 *
 * Extends ControllerTestCase for repo injection and standard helpers.
 *
 * Tests:
 *  - Controller structure (final, extends AbstractController, public methods)
 *  - listForMeeting: method enforcement, meeting_id UUID validation
 *  - upsert: method enforcement, meeting_id / giver_member_id UUID validation,
 *    receiver_member_id validation, revoke path (success via mocked ProxyRepository)
 *  - delete: method enforcement, meeting_id / proxy_id UUID validation,
 *    happy path (mocked meeting + proxy repos)
 */
class ProxiesControllerTest extends ControllerTestCase
{
    private const TENANT   = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const MEETING  = 'bbbbbbbb-0000-0000-0000-000000000001';
    private const GIVER    = 'cccccccc-0000-0000-0000-000000000001';
    private const RECEIVER = 'dddddddd-0000-0000-0000-000000000001';
    private const PROXY_ID = 'eeeeeeee-0000-0000-0000-000000000001';

    // =========================================================================
    // CONTROLLER STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(ProxiesController::class);
        $this->assertTrue($ref->isFinal(), 'ProxiesController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new ProxiesController();
        $this->assertInstanceOf(AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(ProxiesController::class);

        foreach (['listForMeeting', 'upsert', 'delete'] as $method) {
            $this->assertTrue($ref->hasMethod($method), "Missing method: {$method}");
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(ProxiesController::class);

        foreach (['listForMeeting', 'upsert', 'delete'] as $method) {
            $this->assertTrue($ref->getMethod($method)->isPublic(), "{$method} should be public");
        }
    }

    // =========================================================================
    // listForMeeting: METHOD ENFORCEMENT
    // =========================================================================

    public function testListForMeetingRejectsPostMethod(): void
    {
        $this->setHttpMethod('POST');

        $result = $this->callController(ProxiesController::class, 'listForMeeting');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListForMeetingRejectsPutMethod(): void
    {
        $this->setHttpMethod('PUT');

        $result = $this->callController(ProxiesController::class, 'listForMeeting');

        $this->assertEquals(405, $result['status']);
    }

    // =========================================================================
    // listForMeeting: meeting_id VALIDATION
    // =========================================================================

    public function testListForMeetingRequiresMeetingId(): void
    {
        $result = $this->callController(ProxiesController::class, 'listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field'] ?? null);
    }

    public function testListForMeetingRejectsInvalidUuid(): void
    {
        $this->setQueryParams(['meeting_id' => 'not-a-uuid']);

        $result = $this->callController(ProxiesController::class, 'listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testListForMeetingRejectsEmptyMeetingId(): void
    {
        $this->setQueryParams(['meeting_id' => '']);

        $result = $this->callController(ProxiesController::class, 'listForMeeting');

        $this->assertEquals(400, $result['status']);
    }

    // =========================================================================
    // listForMeeting: SUCCESS PATH
    // =========================================================================

    public function testListForMeetingReturnsProxies(): void
    {
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->setQueryParams(['meeting_id' => self::MEETING]);

        $mockProxy = $this->createMock(ProxyRepository::class);
        $mockProxy->method('listForMeeting')->willReturn([
            ['id' => self::PROXY_ID, 'giver_name' => 'Alice', 'receiver_name' => 'Bob'],
        ]);
        $this->injectRepos([ProxyRepository::class => $mockProxy]);

        $result = $this->callController(ProxiesController::class, 'listForMeeting');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
        $this->assertEquals(self::MEETING, $result['body']['data']['meeting_id']);
        $this->assertEquals(1, $result['body']['data']['count']);
    }

    public function testListForMeetingReturnsEmptyArray(): void
    {
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->setQueryParams(['meeting_id' => self::MEETING]);

        $mockProxy = $this->createMock(ProxyRepository::class);
        $mockProxy->method('listForMeeting')->willReturn([]);
        $this->injectRepos([ProxyRepository::class => $mockProxy]);

        $result = $this->callController(ProxiesController::class, 'listForMeeting');

        $this->assertEquals(200, $result['status']);
        $this->assertEquals(0, $result['body']['data']['count']);
        $this->assertEquals([], $result['body']['data']['items']);
    }

    // =========================================================================
    // upsert: METHOD ENFORCEMENT
    // =========================================================================

    public function testUpsertRejectsGetMethod(): void
    {
        $result = $this->callController(ProxiesController::class, 'upsert');

        $this->assertEquals(405, $result['status']);
    }

    public function testUpsertRejectsPutMethod(): void
    {
        $this->setHttpMethod('PUT');

        $result = $this->callController(ProxiesController::class, 'upsert');

        $this->assertEquals(405, $result['status']);
    }

    // =========================================================================
    // upsert: VALIDATION
    // =========================================================================

    public function testUpsertRequiresMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $result = $this->callController(ProxiesController::class, 'upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field'] ?? null);
    }

    public function testUpsertRequiresGiverMemberId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => self::MEETING]);

        $result = $this->callController(ProxiesController::class, 'upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('giver_member_id', $result['body']['field'] ?? null);
    }

    public function testUpsertRejectsInvalidReceiverUuid(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([
            'meeting_id'       => self::MEETING,
            'giver_member_id'  => self::GIVER,
            'receiver_member_id' => 'not-a-uuid',
        ]);

        $result = $this->callController(ProxiesController::class, 'upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_receiver_member_id', $result['body']['error']);
    }

    // =========================================================================
    // upsert: REVOKE PATH (empty receiver)
    // =========================================================================

    public function testUpsertWithEmptyReceiverRevokesProxy(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([
            'meeting_id'         => self::MEETING,
            'giver_member_id'    => self::GIVER,
            'receiver_member_id' => '',
        ]);

        $mockProxy = $this->createMock(ProxyRepository::class);
        $mockProxy->method('listForMeeting')->willReturn([]);
        $mockProxy->method('findWithNames')->willReturn(null);
        // revoke is called via ProxiesService — just needs to not throw
        $mockProxy->method('deleteProxy')->willReturn(0);
        $this->injectRepos([ProxyRepository::class => $mockProxy]);

        $result = $this->callController(ProxiesController::class, 'upsert');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['data']['revoked'] ?? false);
    }

    // =========================================================================
    // delete: METHOD ENFORCEMENT
    // =========================================================================

    public function testDeleteRejectsGetMethod(): void
    {
        $result = $this->callController(ProxiesController::class, 'delete');

        $this->assertEquals(405, $result['status']);
    }

    // =========================================================================
    // delete: VALIDATION
    // =========================================================================

    public function testDeleteRequiresMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $result = $this->callController(ProxiesController::class, 'delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testDeleteRequiresProxyId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => self::MEETING]);

        $result = $this->callController(ProxiesController::class, 'delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_proxy_id', $result['body']['error']);
    }

    public function testDeleteRejectsInvalidMeetingUuid(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => 'bad', 'proxy_id' => self::PROXY_ID]);

        $result = $this->callController(ProxiesController::class, 'delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testDeleteRejectsInvalidProxyUuid(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => self::MEETING, 'proxy_id' => 'bad-proxy-id']);

        $result = $this->callController(ProxiesController::class, 'delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_proxy_id', $result['body']['error']);
    }

    // =========================================================================
    // delete: SUCCESS PATH
    // =========================================================================

    public function testDeleteSuccessfully(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([
            'meeting_id' => self::MEETING,
            'proxy_id'   => self::PROXY_ID,
        ]);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('findByIdForTenant')->willReturn([
            'id'     => self::MEETING,
            'title'  => 'Test Meeting',
            'status' => 'open',
        ]);

        $mockProxy = $this->createMock(ProxyRepository::class);
        $mockProxy->method('findWithNames')->willReturn([
            'id'            => self::PROXY_ID,
            'giver_name'    => 'Alice',
            'receiver_name' => 'Bob',
        ]);
        $mockProxy->method('deleteProxy')->willReturn(1);

        $this->injectRepos([
            MeetingRepository::class => $mockMeeting,
            ProxyRepository::class   => $mockProxy,
        ]);

        $result = $this->callController(ProxiesController::class, 'delete');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['data']['deleted'] ?? false);
        $this->assertEquals(self::PROXY_ID, $result['body']['data']['proxy_id'] ?? null);
    }

    public function testDeleteReturnsMeetingNotFound(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([
            'meeting_id' => self::MEETING,
            'proxy_id'   => self::PROXY_ID,
        ]);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('findByIdForTenant')->willReturn(null);

        $this->injectRepos([MeetingRepository::class => $mockMeeting]);

        $result = $this->callController(ProxiesController::class, 'delete');

        $this->assertEquals(404, $result['status']);
        $this->assertEquals('meeting_not_found', $result['body']['error']);
    }

    public function testDeleteReturnsMeetingArchivedError(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([
            'meeting_id' => self::MEETING,
            'proxy_id'   => self::PROXY_ID,
        ]);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('findByIdForTenant')->willReturn([
            'id'     => self::MEETING,
            'title'  => 'Test Meeting',
            'status' => 'archived',
        ]);

        $this->injectRepos([MeetingRepository::class => $mockMeeting]);

        $result = $this->callController(ProxiesController::class, 'delete');

        $this->assertEquals(409, $result['status']);
        $this->assertEquals('meeting_archived', $result['body']['error']);
    }

    public function testDeleteReturnsProxyNotFound(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([
            'meeting_id' => self::MEETING,
            'proxy_id'   => self::PROXY_ID,
        ]);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING, 'title' => 'Test', 'status' => 'open',
        ]);

        $mockProxy = $this->createMock(ProxyRepository::class);
        $mockProxy->method('findWithNames')->willReturn(null);

        $this->injectRepos([
            MeetingRepository::class => $mockMeeting,
            ProxyRepository::class   => $mockProxy,
        ]);

        $result = $this->callController(ProxiesController::class, 'delete');

        $this->assertEquals(404, $result['status']);
        $this->assertEquals('proxy_not_found', $result['body']['error']);
    }
}
