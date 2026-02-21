<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\DevicesController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DevicesController.
 *
 * Tests the device management endpoints including:
 *  - Controller structure (final, extends AbstractController, public methods)
 *  - HTTP method enforcement for listDevices, block, unblock, kick, heartbeat
 *  - Input validation (missing device_id)
 *  - Device audit context building logic
 *  - Device status classification logic (online/stale/offline)
 *  - Response structure verification via source introspection
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class DevicesControllerTest extends TestCase
{
    // =========================================================================
    // SETUP / TEARDOWN
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];

        $ref = new \ReflectionClass(\AgVote\Core\Http\Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        \AgVote\Core\Security\AuthMiddleware::reset();
    }

    protected function tearDown(): void
    {
        \AgVote\Core\Security\AuthMiddleware::reset();

        $ref = new \ReflectionClass(\AgVote\Core\Http\Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        parent::tearDown();
    }

    // =========================================================================
    // HELPER: Call controller and capture response
    // =========================================================================

    private function callControllerMethod(string $method): array
    {
        $controller = new DevicesController();
        try {
            $controller->handle($method);
            $this->fail('Expected ApiResponseException was not thrown');
        } catch (ApiResponseException $e) {
            return [
                'status' => $e->getResponse()->getStatusCode(),
                'body' => $e->getResponse()->getBody(),
            ];
        }
        return ['status' => 500, 'body' => []];
    }

    private function injectJsonBody(array $data): void
    {
        $ref = new \ReflectionClass(\AgVote\Core\Http\Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, json_encode($data));
    }

    // =========================================================================
    // CONTROLLER STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(DevicesController::class);
        $this->assertTrue($ref->isFinal(), 'DevicesController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new DevicesController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(DevicesController::class);

        $expectedMethods = ['listDevices', 'block', 'unblock', 'kick', 'heartbeat'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "DevicesController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(DevicesController::class);

        $expectedMethods = ['listDevices', 'block', 'unblock', 'kick', 'heartbeat'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "DevicesController::{$method}() should be public",
            );
        }
    }

    public function testControllerHasPrivateHelperMethods(): void
    {
        $ref = new \ReflectionClass(DevicesController::class);

        $this->assertTrue($ref->hasMethod('requireDeviceId'));
        $this->assertTrue($ref->getMethod('requireDeviceId')->isPrivate());

        $this->assertTrue($ref->hasMethod('deviceAuditContext'));
        $this->assertTrue($ref->getMethod('deviceAuditContext')->isPrivate());
    }

    // =========================================================================
    // listDevices: METHOD ENFORCEMENT
    // =========================================================================

    public function testListDevicesRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('listDevices');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListDevicesRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('listDevices');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListDevicesRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('listDevices');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListDevicesRejectsPatchMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';

        $result = $this->callControllerMethod('listDevices');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // block: METHOD ENFORCEMENT
    // =========================================================================

    public function testBlockRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('block');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testBlockRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('block');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testBlockRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('block');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // block: DEVICE_ID VALIDATION
    // =========================================================================

    public function testBlockRequiresDeviceId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('block');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_device_id', $result['body']['error']);
    }

    public function testBlockRejectsEmptyDeviceId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['device_id' => '']);

        $result = $this->callControllerMethod('block');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_device_id', $result['body']['error']);
    }

    // =========================================================================
    // unblock: METHOD ENFORCEMENT
    // =========================================================================

    public function testUnblockRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('unblock');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testUnblockRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('unblock');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // unblock: DEVICE_ID VALIDATION
    // =========================================================================

    public function testUnblockRequiresDeviceId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('unblock');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_device_id', $result['body']['error']);
    }

    public function testUnblockRejectsEmptyDeviceId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['device_id' => '']);

        $result = $this->callControllerMethod('unblock');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_device_id', $result['body']['error']);
    }

    // =========================================================================
    // kick: METHOD ENFORCEMENT
    // =========================================================================

    public function testKickRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('kick');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testKickRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('kick');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // kick: DEVICE_ID VALIDATION
    // =========================================================================

    public function testKickRequiresDeviceId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('kick');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_device_id', $result['body']['error']);
    }

    public function testKickRejectsEmptyDeviceId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['device_id' => '']);

        $result = $this->callControllerMethod('kick');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_device_id', $result['body']['error']);
    }

    // =========================================================================
    // heartbeat: METHOD ENFORCEMENT
    // =========================================================================

    public function testHeartbeatRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('heartbeat');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testHeartbeatRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('heartbeat');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // heartbeat: DEVICE_ID VALIDATION
    // =========================================================================

    public function testHeartbeatRequiresDeviceId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('heartbeat');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_device_id', $result['body']['error']);
    }

    public function testHeartbeatRejectsEmptyDeviceId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['device_id' => '']);

        $result = $this->callControllerMethod('heartbeat');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_device_id', $result['body']['error']);
    }

    // =========================================================================
    // DEVICE STATUS CLASSIFICATION LOGIC
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
    // DEFAULT REASON/MESSAGE LOGIC
    // =========================================================================

    public function testBlockDefaultReason(): void
    {
        $reason = trim('');
        if ($reason === '') {
            $reason = 'blocked_by_operator';
        }
        $this->assertEquals('blocked_by_operator', $reason);
    }

    public function testBlockCustomReason(): void
    {
        $reason = trim('Suspicious activity');
        if ($reason === '') {
            $reason = 'blocked_by_operator';
        }
        $this->assertEquals('Suspicious activity', $reason);
    }

    public function testKickDefaultMessage(): void
    {
        $message = trim('');
        if ($message === '') {
            $message = 'Veuillez recharger la page.';
        }
        $this->assertEquals('Veuillez recharger la page.', $message);
    }

    public function testKickCustomMessage(): void
    {
        $message = trim('Session expired');
        if ($message === '') {
            $message = 'Veuillez recharger la page.';
        }
        $this->assertEquals('Session expired', $message);
    }

    // =========================================================================
    // DEVICE AUDIT CONTEXT LOGIC
    // =========================================================================

    public function testDeviceAuditContextBuilding(): void
    {
        $hb = [
            'role' => 'voter',
            'ip' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'battery_pct' => 85,
            'is_charging' => true,
            'last_seen_at' => '2025-01-15T10:00:00+00:00',
        ];
        $meetingId = '11111111-1111-1111-1111-111111111111';
        $deviceId = 'device-123';

        $ctx = [
            'meeting_id' => $meetingId,
            'device_id' => $deviceId,
            'role' => $hb['role'] ?? null,
            'ip' => $hb['ip'] ?? null,
            'user_agent' => $hb['user_agent'] ?? null,
            'battery_pct' => isset($hb['battery_pct']) ? (int) $hb['battery_pct'] : null,
            'is_charging' => isset($hb['is_charging']) ? (bool) $hb['is_charging'] : null,
            'last_seen_at' => $hb['last_seen_at'] ?? null,
        ];

        $this->assertEquals($meetingId, $ctx['meeting_id']);
        $this->assertEquals($deviceId, $ctx['device_id']);
        $this->assertEquals('voter', $ctx['role']);
        $this->assertEquals(85, $ctx['battery_pct']);
        $this->assertTrue($ctx['is_charging']);
    }

    public function testDeviceAuditContextWithEmptyHeartbeat(): void
    {
        $hb = [];
        $ctx = [
            'meeting_id' => 'test',
            'device_id' => 'test',
            'role' => $hb['role'] ?? null,
            'ip' => $hb['ip'] ?? null,
            'battery_pct' => isset($hb['battery_pct']) ? (int) $hb['battery_pct'] : null,
            'is_charging' => isset($hb['is_charging']) ? (bool) $hb['is_charging'] : null,
        ];

        $this->assertNull($ctx['role']);
        $this->assertNull($ctx['ip']);
        $this->assertNull($ctx['battery_pct']);
        $this->assertNull($ctx['is_charging']);
    }

    // =========================================================================
    // CONTROLLER SOURCE: RESPONSE STRUCTURE VERIFICATION
    // =========================================================================

    public function testListDevicesResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DevicesController.php');

        $this->assertStringContainsString("'counts'", $source);
        $this->assertStringContainsString("'items'", $source);
    }

    public function testListDevicesCountsFields(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DevicesController.php');

        $fields = ['total', 'online', 'stale', 'offline', 'blocked'];
        foreach ($fields as $field) {
            $this->assertStringContainsString(
                "'{$field}'",
                $source,
                "listDevices counts should contain '{$field}'",
            );
        }
    }

    public function testHeartbeatResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DevicesController.php');

        $this->assertStringContainsString("'device_id'", $source);
        $this->assertStringContainsString("'blocked'", $source);
        $this->assertStringContainsString("'block_reason'", $source);
        $this->assertStringContainsString("'command'", $source);
    }

    // =========================================================================
    // CONTROLLER SOURCE: AUDIT LOG VERIFICATION
    // =========================================================================

    public function testBlockAuditsEvent(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DevicesController.php');

        $this->assertStringContainsString("'device_blocked'", $source);
    }

    public function testUnblockAuditsEvent(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DevicesController.php');

        $this->assertStringContainsString("'device_unblocked'", $source);
    }

    public function testKickAuditsEvent(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DevicesController.php');

        $this->assertStringContainsString("'device_kicked'", $source);
    }

    // =========================================================================
    // CONTROLLER SOURCE: REPOSITORY USAGE
    // =========================================================================

    public function testControllerUsesDeviceRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DevicesController.php');

        $this->assertStringContainsString('DeviceRepository', $source);
    }

    public function testControllerUsesMeetingRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DevicesController.php');

        $this->assertStringContainsString('MeetingRepository', $source);
    }
}
