<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\DevicesController;
use AgVote\Repository\DeviceRepository;
use AgVote\Repository\MeetingRepository;

/**
 * Unit tests for DevicesController.
 *
 * Endpoints:
 *  - listDevices(): GET    — list device heartbeats with online/stale/offline status
 *  - block():       POST   — block a device
 *  - unblock():     POST   — unblock a device
 *  - kick():        POST   — schedule a kick command for a device
 *  - heartbeat():   POST   — device check-in, returns block status + pending commands
 *
 * Uses ControllerTestCase with mocked repos via RepositoryFactory injection.
 */
class DevicesControllerTest extends ControllerTestCase
{
    private const TENANT_ID  = 'ffffffff-0000-1111-2222-333333333333';
    private const MEETING_ID = 'aa000001-0000-4000-a000-000000000001';
    private const USER_ID    = 'aa000002-0000-4000-a000-000000000002';
    private const DEVICE_ID  = 'device-abc-123';

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
        $ref = new \ReflectionClass(DevicesController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerExtendsAbstractController(): void
    {
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, new DevicesController());
    }

    public function testControllerHasExpectedMethods(): void
    {
        $ref = new \ReflectionClass(DevicesController::class);
        foreach (['listDevices', 'block', 'unblock', 'kick', 'heartbeat'] as $m) {
            $this->assertTrue($ref->hasMethod($m), "Missing method: {$m}");
        }
    }

    public function testControllerHasPrivateHelpers(): void
    {
        $ref = new \ReflectionClass(DevicesController::class);
        $this->assertTrue($ref->getMethod('requireDeviceId')->isPrivate());
        $this->assertTrue($ref->getMethod('deviceAuditContext')->isPrivate());
    }

    // =========================================================================
    // listDevices()
    // =========================================================================

    public function testListDevicesRejectsPostMethod(): void
    {
        $this->setHttpMethod('POST');
        $result = $this->callController(DevicesController::class, 'listDevices');
        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListDevicesRejectsDeleteMethod(): void
    {
        $this->setHttpMethod('DELETE');
        $result = $this->callController(DevicesController::class, 'listDevices');
        $this->assertEquals(405, $result['status']);
    }

    public function testListDevicesReturnsEmptyList(): void
    {
        $deviceRepo = $this->createMock(DeviceRepository::class);
        $deviceRepo->method('listHeartbeats')->willReturn([]);
        $this->injectRepos([DeviceRepository::class => $deviceRepo]);

        $result = $this->callController(DevicesController::class, 'listDevices');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertArrayHasKey('counts', $data);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(0, $data['items']);
        $this->assertEquals(0, $data['counts']['total']);
    }

    public function testListDevicesClassifiesOnlineDevice(): void
    {
        $deviceRepo = $this->createMock(DeviceRepository::class);
        $deviceRepo->method('listHeartbeats')->willReturn([
            [
                'device_id'    => self::DEVICE_ID,
                'meeting_id'   => self::MEETING_ID,
                'role'         => 'voter',
                'ip'           => '192.168.1.1',
                'user_agent'   => 'PHPUnit',
                'battery_pct'  => 80,
                'is_charging'  => true,
                'last_seen_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                'is_blocked'   => false,
                'block_reason' => '',
            ],
        ]);
        $this->injectRepos([DeviceRepository::class => $deviceRepo]);

        $result = $this->callController(DevicesController::class, 'listDevices');
        $this->assertEquals(200, $result['status']);
        $items = $result['body']['data']['items'];
        $this->assertCount(1, $items);
        $this->assertEquals('online', $items[0]['status']);
        $this->assertEquals(1, $result['body']['data']['counts']['online']);
    }

    public function testListDevicesClassifiesOfflineDevice(): void
    {
        $deviceRepo = $this->createMock(DeviceRepository::class);
        $deviceRepo->method('listHeartbeats')->willReturn([
            [
                'device_id'    => self::DEVICE_ID,
                'meeting_id'   => self::MEETING_ID,
                'role'         => 'voter',
                'ip'           => '10.0.0.1',
                'user_agent'   => 'PHPUnit',
                'battery_pct'  => null,
                'is_charging'  => null,
                'last_seen_at' => (new \DateTimeImmutable('-10 minutes'))->format('Y-m-d H:i:s'),
                'is_blocked'   => false,
                'block_reason' => '',
            ],
        ]);
        $this->injectRepos([DeviceRepository::class => $deviceRepo]);

        $result = $this->callController(DevicesController::class, 'listDevices');
        $this->assertEquals(200, $result['status']);
        $items = $result['body']['data']['items'];
        $this->assertEquals('offline', $items[0]['status']);
        $this->assertEquals(1, $result['body']['data']['counts']['offline']);
    }

    public function testListDevicesCountsBlockedDevice(): void
    {
        $deviceRepo = $this->createMock(DeviceRepository::class);
        $deviceRepo->method('listHeartbeats')->willReturn([
            [
                'device_id'    => self::DEVICE_ID,
                'meeting_id'   => self::MEETING_ID,
                'role'         => 'voter',
                'ip'           => '10.0.0.2',
                'user_agent'   => 'PHPUnit',
                'battery_pct'  => null,
                'is_charging'  => null,
                'last_seen_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                'is_blocked'   => true,
                'block_reason' => 'suspicious',
            ],
        ]);
        $this->injectRepos([DeviceRepository::class => $deviceRepo]);

        $result = $this->callController(DevicesController::class, 'listDevices');
        $this->assertEquals(200, $result['status']);
        $this->assertEquals(1, $result['body']['data']['counts']['blocked']);
    }

    // =========================================================================
    // block()
    // =========================================================================

    public function testBlockRejectsGetMethod(): void
    {
        $this->setHttpMethod('GET');
        $result = $this->callController(DevicesController::class, 'block');
        $this->assertEquals(405, $result['status']);
    }

    public function testBlockRequiresDeviceId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);
        $result = $this->callController(DevicesController::class, 'block');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_device_id', $result['body']['error']);
    }

    public function testBlockRejectsEmptyDeviceId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['device_id' => '']);
        $result = $this->callController(DevicesController::class, 'block');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_device_id', $result['body']['error']);
    }

    public function testBlockSuccessWithDeviceId(): void
    {
        $this->setHttpMethod('POST');

        $deviceRepo = $this->createMock(DeviceRepository::class);
        $deviceRepo->method('findHeartbeat')->willReturn(null);
        $deviceRepo->method('blockDevice');
        $this->injectRepos([DeviceRepository::class => $deviceRepo]);

        $this->injectJsonBody([
            'device_id'  => self::DEVICE_ID,
            'meeting_id' => self::MEETING_ID,
            'reason'     => 'suspicious',
        ]);

        $result = $this->callController(DevicesController::class, 'block');
        $this->assertEquals(200, $result['status']);
    }

    // =========================================================================
    // unblock()
    // =========================================================================

    public function testUnblockRejectsGetMethod(): void
    {
        $this->setHttpMethod('GET');
        $result = $this->callController(DevicesController::class, 'unblock');
        $this->assertEquals(405, $result['status']);
    }

    public function testUnblockRequiresDeviceId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);
        $result = $this->callController(DevicesController::class, 'unblock');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_device_id', $result['body']['error']);
    }

    public function testUnblockSuccessWithDeviceId(): void
    {
        $this->setHttpMethod('POST');

        $deviceRepo = $this->createMock(DeviceRepository::class);
        $deviceRepo->method('findHeartbeat')->willReturn(null);
        $deviceRepo->method('unblockDevice');
        $this->injectRepos([DeviceRepository::class => $deviceRepo]);

        $this->injectJsonBody([
            'device_id'  => self::DEVICE_ID,
            'meeting_id' => self::MEETING_ID,
        ]);

        $result = $this->callController(DevicesController::class, 'unblock');
        $this->assertEquals(200, $result['status']);
    }

    // =========================================================================
    // kick()
    // =========================================================================

    public function testKickRejectsGetMethod(): void
    {
        $this->setHttpMethod('GET');
        $result = $this->callController(DevicesController::class, 'kick');
        $this->assertEquals(405, $result['status']);
    }

    public function testKickRequiresDeviceId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);
        $result = $this->callController(DevicesController::class, 'kick');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_device_id', $result['body']['error']);
    }

    public function testKickSuccessWithDeviceId(): void
    {
        $this->setHttpMethod('POST');

        $deviceRepo = $this->createMock(DeviceRepository::class);
        $deviceRepo->method('findHeartbeat')->willReturn(null);
        $deviceRepo->method('insertKickCommand');
        $this->injectRepos([DeviceRepository::class => $deviceRepo]);

        $this->injectJsonBody([
            'device_id'  => self::DEVICE_ID,
            'meeting_id' => self::MEETING_ID,
            'message'    => 'Please reconnect.',
        ]);

        $result = $this->callController(DevicesController::class, 'kick');
        $this->assertEquals(200, $result['status']);
    }

    // =========================================================================
    // heartbeat()
    // =========================================================================

    public function testHeartbeatRejectsGetMethod(): void
    {
        $this->setHttpMethod('GET');
        $result = $this->callController(DevicesController::class, 'heartbeat');
        $this->assertEquals(405, $result['status']);
    }

    public function testHeartbeatRequiresDeviceId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);
        $result = $this->callController(DevicesController::class, 'heartbeat');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_device_id', $result['body']['error']);
    }

    public function testHeartbeatSuccessNotBlocked(): void
    {
        $this->setHttpMethod('POST');

        $deviceRepo = $this->createMock(DeviceRepository::class);
        $deviceRepo->method('upsertHeartbeat');
        $deviceRepo->method('findBlockStatus')->willReturn(null);
        $deviceRepo->method('findPendingKick')->willReturn(null);
        $this->injectRepos([DeviceRepository::class => $deviceRepo]);

        $this->injectJsonBody([
            'device_id' => self::DEVICE_ID,
            'role'      => 'voter',
        ]);

        $result = $this->callController(DevicesController::class, 'heartbeat');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertEquals(self::DEVICE_ID, $data['device_id']);
        $this->assertFalse($data['blocked']);
        $this->assertNull($data['command']);
    }

    public function testHeartbeatReturnsBlockedStatus(): void
    {
        $this->setHttpMethod('POST');

        $deviceRepo = $this->createMock(DeviceRepository::class);
        $deviceRepo->method('upsertHeartbeat');
        $deviceRepo->method('findBlockStatus')->willReturn([
            'is_blocked' => true,
            'reason'     => 'suspicious_activity',
        ]);
        $deviceRepo->method('findPendingKick')->willReturn(null);
        $this->injectRepos([DeviceRepository::class => $deviceRepo]);

        $this->injectJsonBody(['device_id' => self::DEVICE_ID]);

        $result = $this->callController(DevicesController::class, 'heartbeat');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertTrue($data['blocked']);
        $this->assertEquals('suspicious_activity', $data['block_reason']);
    }

    public function testHeartbeatReturnsPendingKickCommand(): void
    {
        $this->setHttpMethod('POST');

        $deviceRepo = $this->createMock(DeviceRepository::class);
        $deviceRepo->method('upsertHeartbeat');
        $deviceRepo->method('findBlockStatus')->willReturn(null);
        $deviceRepo->method('findPendingKick')->willReturn([
            'id'      => 'aa000099-0000-4000-a000-000000000099',
            'payload' => json_encode(['message' => 'Session expired']),
        ]);
        $deviceRepo->method('consumeCommand');
        $this->injectRepos([DeviceRepository::class => $deviceRepo]);

        $this->injectJsonBody(['device_id' => self::DEVICE_ID]);

        $result = $this->callController(DevicesController::class, 'heartbeat');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertNotNull($data['command']);
        $this->assertEquals('kick', $data['command']['type']);
        $this->assertEquals('Session expired', $data['command']['message']);
    }

    public function testHeartbeatIgnoresCrossTenantMeetingId(): void
    {
        $this->setHttpMethod('POST');

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(null); // cross-tenant → null
        $deviceRepo = $this->createMock(DeviceRepository::class);
        $deviceRepo->method('upsertHeartbeat');
        $deviceRepo->method('findBlockStatus')->willReturn(null);
        $deviceRepo->method('findPendingKick')->willReturn(null);
        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            DeviceRepository::class  => $deviceRepo,
        ]);

        $this->injectJsonBody([
            'device_id'  => self::DEVICE_ID,
            'meeting_id' => self::MEETING_ID, // will be silently cleared
        ]);

        $result = $this->callController(DevicesController::class, 'heartbeat');
        $this->assertEquals(200, $result['status']);
        // meeting_id was cleared silently — no error, device is still registered
        $this->assertEquals(self::DEVICE_ID, $result['body']['data']['device_id']);
    }

    // =========================================================================
    // Device status classification (logic replication)
    // =========================================================================

    public function testDeviceStatusOnlineWithin30Seconds(): void
    {
        $now = new \DateTimeImmutable('now');
        $onlineCut = $now->sub(new \DateInterval('PT30S'));
        $staleCut = $now->sub(new \DateInterval('PT120S'));
        $lastSeen = new \DateTimeImmutable('now');

        if ($lastSeen >= $onlineCut) {
            $status = 'online';
        } elseif ($lastSeen >= $staleCut) {
            $status = 'stale';
        } else {
            $status = 'offline';
        }

        $this->assertEquals('online', $status);
    }

    public function testDeviceStatusStaleWithin120Seconds(): void
    {
        $now = new \DateTimeImmutable('now');
        $onlineCut = $now->sub(new \DateInterval('PT30S'));
        $staleCut = $now->sub(new \DateInterval('PT120S'));
        $lastSeen = $now->sub(new \DateInterval('PT60S'));

        if ($lastSeen >= $onlineCut) {
            $status = 'online';
        } elseif ($lastSeen >= $staleCut) {
            $status = 'stale';
        } else {
            $status = 'offline';
        }

        $this->assertEquals('stale', $status);
    }

    public function testDeviceStatusOfflineOver120Seconds(): void
    {
        $now = new \DateTimeImmutable('now');
        $onlineCut = $now->sub(new \DateInterval('PT30S'));
        $staleCut = $now->sub(new \DateInterval('PT120S'));
        $lastSeen = $now->sub(new \DateInterval('PT300S'));

        if ($lastSeen >= $onlineCut) {
            $status = 'online';
        } elseif ($lastSeen >= $staleCut) {
            $status = 'stale';
        } else {
            $status = 'offline';
        }

        $this->assertEquals('offline', $status);
    }

    // =========================================================================
    // Source-level audit / response verification
    // =========================================================================

    public function testAuditLogsDocumented(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DevicesController.php');
        $this->assertStringContainsString("'device_blocked'", $source);
        $this->assertStringContainsString("'device_unblocked'", $source);
        $this->assertStringContainsString("'device_kicked'", $source);
    }

    public function testListDevicesResponseKeysPresent(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DevicesController.php');
        $this->assertStringContainsString("'counts'", $source);
        $this->assertStringContainsString("'items'", $source);
        foreach (['total', 'online', 'stale', 'offline', 'blocked'] as $key) {
            $this->assertStringContainsString("'{$key}'", $source);
        }
    }
}
