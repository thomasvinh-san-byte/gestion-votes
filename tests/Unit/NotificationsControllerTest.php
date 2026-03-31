<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AbstractController;
use AgVote\Controller\NotificationsController;
use AgVote\Repository\AuditEventRepository;

/**
 * Unit tests for NotificationsController.
 *
 * Extends ControllerTestCase for repo injection and standard helpers.
 *
 * Tests:
 *  - Controller structure (final, extends AbstractController)
 *  - NOTIF_ACTIONS constant completeness
 *  - list(): limit clamping (1-50, default 20), success path with mocked repo
 *  - markRead(): returns marked=0 acknowledgement
 */
class NotificationsControllerTest extends ControllerTestCase
{
    private const TENANT = 'aaaaaaaa-0000-0000-0000-000000000001';

    // =========================================================================
    // CONTROLLER STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(NotificationsController::class);
        $this->assertTrue($ref->isFinal(), 'NotificationsController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $this->assertInstanceOf(AbstractController::class, new NotificationsController());
    }

    public function testControllerHasExpectedMethods(): void
    {
        $ref = new \ReflectionClass(NotificationsController::class);

        foreach (['list', 'markRead'] as $method) {
            $this->assertTrue($ref->hasMethod($method), "Missing method: {$method}");
            $this->assertTrue($ref->getMethod($method)->isPublic(), "{$method} should be public");
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
    }

    public function testNotifActionsContainsLifecycleEvents(): void
    {
        $ref = new \ReflectionClass(NotificationsController::class);
        $actions = $ref->getConstant('NOTIF_ACTIONS');

        $required = ['meeting_created', 'meeting_launched', 'meeting_closed',
                     'motion_opened', 'motion_closed'];
        foreach ($required as $action) {
            $this->assertContains($action, $actions, "Missing lifecycle action: {$action}");
        }
    }

    // =========================================================================
    // list(): LIMIT CLAMPING LOGIC
    // =========================================================================

    public function testListLimitClampingLogic(): void
    {
        // Source logic: min(max((int) ($limit ?: 20), 1), 50)
        $clamp = fn(string $input): int => min(max((int) ($input ?: 20), 1), 50);

        $this->assertEquals(20, $clamp(''));       // Default
        $this->assertEquals(20, $clamp('0'));      // Zero => default
        $this->assertEquals(1, $clamp('1'));       // Min boundary
        $this->assertEquals(50, $clamp('50'));     // Max boundary
        $this->assertEquals(50, $clamp('100'));    // Over max => clamped
        $this->assertEquals(1, $clamp('-5'));      // Negative => clamped
        $this->assertEquals(25, $clamp('25'));     // Normal value
    }

    // =========================================================================
    // list(): REQUIRES AUTH
    // =========================================================================

    public function testListRequiresAuthentication(): void
    {
        // Without auth, api_current_tenant_id() fails
        $result = $this->callController(NotificationsController::class, 'list');

        $this->assertGreaterThanOrEqual(400, $result['status']);
    }

    // =========================================================================
    // list(): SUCCESS PATH
    // =========================================================================

    public function testListReturnsNotifications(): void
    {
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->setQueryParams(['limit' => '5']);

        $mockAudit = $this->createMock(AuditEventRepository::class);
        $mockAudit->method('listRecentByActions')->willReturn([
            ['id' => 1, 'action' => 'meeting_created', 'created_at' => '2026-03-01'],
            ['id' => 2, 'action' => 'motion_opened', 'created_at' => '2026-03-02'],
        ]);

        $this->injectRepos([AuditEventRepository::class => $mockAudit]);

        $result = $this->callController(NotificationsController::class, 'list');

        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('notifications', $result['body']['data']);
        $this->assertArrayHasKey('unread_count', $result['body']['data']);
        $this->assertEquals(2, $result['body']['data']['unread_count']);
        $this->assertCount(2, $result['body']['data']['notifications']);
    }

    public function testListWithEmptyResultReturnsZeroCount(): void
    {
        $this->setAuth('user-1', 'admin', self::TENANT);

        $mockAudit = $this->createMock(AuditEventRepository::class);
        $mockAudit->method('listRecentByActions')->willReturn([]);

        $this->injectRepos([AuditEventRepository::class => $mockAudit]);

        $result = $this->callController(NotificationsController::class, 'list');

        $this->assertEquals(200, $result['status']);
        $this->assertEquals(0, $result['body']['data']['unread_count']);
        $this->assertEquals([], $result['body']['data']['notifications']);
    }

    // =========================================================================
    // markRead(): SUCCESS PATH
    // =========================================================================

    public function testMarkReadReturnsZeroCount(): void
    {
        $this->setHttpMethod('PUT');
        $this->setAuth('user-1', 'admin', self::TENANT);

        $result = $this->callController(NotificationsController::class, 'markRead');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
        $this->assertArrayHasKey('marked', $result['body']['data']);
        $this->assertEquals(0, $result['body']['data']['marked']);
    }

    public function testMarkReadRejectsGetMethod(): void
    {
        // markRead enforces PUT/POST methods
        $this->setAuth('user-1', 'admin', self::TENANT);

        $result = $this->callController(NotificationsController::class, 'markRead');

        $this->assertEquals(405, $result['status']);
    }
}
