<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AbstractController;
use AgVote\Controller\SettingsController;
use AgVote\Repository\SettingsRepository;

/**
 * Unit tests for SettingsController.
 *
 * Extends ControllerTestCase for repo injection and standard helpers.
 *
 * Tests:
 *  - Controller structure (extends AbstractController, public settings() method)
 *  - settings(): action dispatch for list, update, get_template, save_template,
 *    test_smtp, reset_templates, unknown action
 *  - update: missing key validation
 */
class SettingsControllerTest extends ControllerTestCase
{
    private const TENANT = 'aaaaaaaa-0000-0000-0000-000000000001';

    // =========================================================================
    // CONTROLLER STRUCTURE TESTS
    // =========================================================================

    public function testControllerExtendsAbstractController(): void
    {
        $this->assertInstanceOf(AbstractController::class, new SettingsController());
    }

    public function testControllerHasSettingsMethod(): void
    {
        $ref = new \ReflectionClass(SettingsController::class);
        $this->assertTrue($ref->hasMethod('settings'));
        $this->assertTrue($ref->getMethod('settings')->isPublic());
    }

    // =========================================================================
    // settings(): action=list
    // =========================================================================

    public function testListActionReturnsSettings(): void
    {
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody(['action' => 'list']);

        $mockSettings = $this->createMock(SettingsRepository::class);
        $mockSettings->method('listByTenant')->willReturn([
            'email_from' => 'noreply@example.com',
            'org_name'   => 'Test Org',
        ]);

        $this->injectRepos([SettingsRepository::class => $mockSettings]);

        $result = $this->callController(SettingsController::class, 'settings');

        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('data', $result['body']['data']);
    }

    // =========================================================================
    // settings(): action=update
    // =========================================================================

    public function testUpdateActionRequiresKey(): void
    {
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody(['action' => 'update', 'key' => '']);

        $mockSettings = $this->createMock(SettingsRepository::class);
        $this->injectRepos([SettingsRepository::class => $mockSettings]);

        $result = $this->callController(SettingsController::class, 'settings');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_key', $result['body']['error']);
    }

    public function testUpdateActionSavesSuccessfully(): void
    {
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([
            'action' => 'update',
            'key'    => 'email_from',
            'value'  => 'admin@example.com',
        ]);

        $mockSettings = $this->createMock(SettingsRepository::class);
        // upsert returns void

        $this->injectRepos([SettingsRepository::class => $mockSettings]);

        $result = $this->callController(SettingsController::class, 'settings');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['data']['saved']);
    }

    // =========================================================================
    // settings(): stub actions (get_template, save_template, test_smtp, reset_templates)
    // =========================================================================

    public function testGetTemplateActionReturnsStub(): void
    {
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody(['action' => 'get_template']);

        $mockSettings = $this->createMock(SettingsRepository::class);
        $this->injectRepos([SettingsRepository::class => $mockSettings]);

        $result = $this->callController(SettingsController::class, 'settings');

        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('data', $result['body']['data']);
    }

    public function testSaveTemplateActionReturnsStub(): void
    {
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody(['action' => 'save_template']);

        $mockSettings = $this->createMock(SettingsRepository::class);
        $this->injectRepos([SettingsRepository::class => $mockSettings]);

        $result = $this->callController(SettingsController::class, 'settings');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['data']['saved']);
    }

    public function testTestSmtpActionReturnsStub(): void
    {
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody(['action' => 'test_smtp']);

        $mockSettings = $this->createMock(SettingsRepository::class);
        $this->injectRepos([SettingsRepository::class => $mockSettings]);

        $result = $this->callController(SettingsController::class, 'settings');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['data']['sent']);
    }

    public function testResetTemplatesActionReturnsStub(): void
    {
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody(['action' => 'reset_templates']);

        $mockSettings = $this->createMock(SettingsRepository::class);
        $this->injectRepos([SettingsRepository::class => $mockSettings]);

        $result = $this->callController(SettingsController::class, 'settings');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['data']['reset']);
    }

    // =========================================================================
    // settings(): unknown action
    // =========================================================================

    public function testUnknownActionReturnsBadRequest(): void
    {
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody(['action' => 'nonexistent_action']);

        $mockSettings = $this->createMock(SettingsRepository::class);
        $this->injectRepos([SettingsRepository::class => $mockSettings]);

        $result = $this->callController(SettingsController::class, 'settings');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('unknown_action', $result['body']['error']);
    }

    public function testMissingActionDefaultsToUnknown(): void
    {
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([]);  // no action key

        $mockSettings = $this->createMock(SettingsRepository::class);
        $this->injectRepos([SettingsRepository::class => $mockSettings]);

        $result = $this->callController(SettingsController::class, 'settings');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('unknown_action', $result['body']['error']);
    }
}
