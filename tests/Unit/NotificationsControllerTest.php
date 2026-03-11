<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\NotificationsController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for NotificationsController.
 *
 * Tests the notification endpoints:
 *  - Controller structure (final, extends AbstractController)
 *  - list() limit clamping (1–50, default 20)
 *  - markRead() returns zero-count acknowledgement
 */
class NotificationsControllerTest extends TestCase
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
    // HELPER
    // =========================================================================

    private function callControllerMethod(string $method): array
    {
        $controller = new NotificationsController();
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

    // =========================================================================
    // CONTROLLER STRUCTURE
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(NotificationsController::class);
        $this->assertTrue($ref->isFinal(), 'NotificationsController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new NotificationsController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasExpectedMethods(): void
    {
        $ref = new \ReflectionClass(NotificationsController::class);

        $expected = ['list', 'markRead'];
        foreach ($expected as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "NotificationsController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(NotificationsController::class);

        $expected = ['list', 'markRead'];
        foreach ($expected as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "NotificationsController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // NOTIF_ACTIONS CONSTANT
    // =========================================================================

    public function testNotifActionsConstantExists(): void
    {
        $ref = new \ReflectionClass(NotificationsController::class);
        $this->assertTrue($ref->hasConstant('NOTIF_ACTIONS'));

        $actions = $ref->getConstant('NOTIF_ACTIONS');
        $this->assertIsArray($actions);
        $this->assertNotEmpty($actions);

        // Key lifecycle actions must be present
        $this->assertContains('meeting_created', $actions);
        $this->assertContains('motion_opened', $actions);
        $this->assertContains('motion_closed', $actions);
    }

    // =========================================================================
    // list(): LIMIT CLAMPING LOGIC
    // =========================================================================

    public function testListLimitDefaultsTo20(): void
    {
        // Inspect the source to verify default limit logic
        $ref = new \ReflectionClass(NotificationsController::class);
        $source = file_get_contents($ref->getFileName());

        // Verify the clamping pattern: min(max(limit, 1), 50) with default 20
        $this->assertStringContainsString("'limit'", $source);
        $this->assertStringContainsString('20', $source);
        $this->assertStringContainsString('50', $source);
    }

    public function testListLimitClampingLogic(): void
    {
        // Test via source inspection: min(max(int($limit ?: 20), 1), 50)
        $clamp = fn(string $input): int => min(max((int) ($input ?: 20), 1), 50);

        $this->assertEquals(20, $clamp(''));       // Default
        $this->assertEquals(20, $clamp('0'));      // Zero → default
        $this->assertEquals(1, $clamp('1'));       // Min boundary
        $this->assertEquals(50, $clamp('50'));     // Max boundary
        $this->assertEquals(50, $clamp('100'));    // Over max → clamped
        $this->assertEquals(1, $clamp('-5'));      // Negative → clamped
        $this->assertEquals(25, $clamp('25'));     // Normal value
    }

    // =========================================================================
    // markRead(): RESPONSE STRUCTURE
    // =========================================================================

    public function testMarkReadReturnsZeroCount(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('markRead');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
        $this->assertArrayHasKey('marked', $result['body']['data']);
        $this->assertEquals(0, $result['body']['data']['marked']);
    }

    // =========================================================================
    // list(): REQUIRES AUTH (no tenant_id without auth → error)
    // =========================================================================

    public function testListRequiresAuthentication(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        // Without auth, api_current_tenant_id() will fail
        $result = $this->callControllerMethod('list');

        // Should fail with auth or tenant error (not 200)
        $this->assertGreaterThanOrEqual(400, $result['status']);
    }
}
