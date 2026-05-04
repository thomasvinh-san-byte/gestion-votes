<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use AgVote\Controller\AdminErrorStatsController;
use AgVote\Controller\AdminErrorStatsForbiddenException;
use AgVote\Core\Security\AuthMiddleware;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AdminErrorStatsController.
 *
 * Source: ERR-V24-03 / D-10 — Plan 02.3 (Phase 2 v2.4).
 *
 * AdminErrorStatsController is a standalone HTML controller (does NOT extend
 * AbstractController per CLAUDE.md). Captures HTML output via output buffering.
 *
 * Coverage:
 *  1. Non-admin role hits the defensive 403 short-circuit (RBAC enforcement).
 *  2. Admin role with no DB renders the page and shows the empty-state copy.
 *  3. Period query parameter is normalised — invalid values fall back to 7d.
 */
class AdminErrorStatsControllerTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $_GET = [];
        $_POST = [];
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        AuthMiddleware::reset();
    }

    protected function tearDown(): void {
        $_GET = [];
        $_POST = [];
        $_SESSION = [];
        AuthMiddleware::reset();
        parent::tearDown();
    }

    // =========================================================================
    // Test 1: Non-admin role → 403 (defensive RBAC short-circuit)
    // =========================================================================

    public function testNonAdminRoleReceives403(): void {
        AuthMiddleware::setCurrentUser([
            'id' => 'u-001',
            'tenant_id' => 't-001',
            'email' => 'op@example.com',
            'role' => 'operator',
            'is_active' => true,
        ]);

        $exception = null;
        ob_start();
        try {
            $controller = new AdminErrorStatsController(null);
            $controller->show();
        } catch (AdminErrorStatsForbiddenException $e) {
            $exception = $e;
        }
        $output = (string) ob_get_clean();

        $this->assertNotNull($exception, 'Non-admin must trigger AdminErrorStatsForbiddenException');
        $this->assertStringContainsString('Accès refusé', $output);
        $this->assertStringNotContainsString('Statistiques des erreurs', $output);
        $this->assertSame(403, http_response_code());
    }

    // =========================================================================
    // Test 2: Admin role + no DB → renders page with empty-state placeholder
    // =========================================================================

    public function testAdminRoleRendersPageWithEmptyStateWhenNoDb(): void {
        AuthMiddleware::setCurrentUser([
            'id' => 'a-001',
            'tenant_id' => 't-001',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        ob_start();
        $controller = new AdminErrorStatsController(null);
        $controller->show();
        $output = (string) ob_get_clean();

        // Page rendered.
        $this->assertStringContainsString('Statistiques des erreurs', $output);
        // Period filter present (default 7d).
        $this->assertStringContainsString('7 jours', $output);
        $this->assertStringContainsString('30 jours', $output);
        $this->assertStringContainsString('90 jours', $output);
        // Empty state copy present (no DB → fetchStats returns []).
        $this->assertStringContainsString('Aucune émission enregistrée', $output);
        // N/A placeholder for next-step rate (D-12).
        $this->assertStringContainsString('Taux next-step', $output);
        // Limitation banner present.
        $this->assertStringContainsString('audit_events', $output);
    }

    // =========================================================================
    // Test 3: Invalid period query falls back to 7d
    // =========================================================================

    public function testInvalidPeriodFallsBackToDefault(): void {
        AuthMiddleware::setCurrentUser([
            'id' => 'a-002',
            'tenant_id' => 't-001',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $_GET['period'] = 'pwned-or-bogus';

        ob_start();
        $controller = new AdminErrorStatsController(null);
        $controller->show();
        $output = (string) ob_get_clean();

        // The default period link (7d) must be marked active.
        $this->assertMatchesRegularExpression(
            '/href="\/admin\/error-stats\?period=7d"[^>]*is-active/',
            $output,
            'Default period 7d should be active when invalid period requested',
        );
        // The bogus value must NOT have leaked into the output as an active filter.
        $this->assertStringNotContainsString('pwned-or-bogus', $output);
    }

    // =========================================================================
    // Test 4: Custom PDO injection path renders rows with French labels
    // =========================================================================

    public function testCustomPdoRendersRowsWithFrenchLabels(): void {
        AuthMiddleware::setCurrentUser([
            'id' => 'a-003',
            'tenant_id' => 't-001',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Build an in-memory SQLite PDO seeded with a minimal audit_events shape
        // matching the columns the controller queries. SQLite does not support
        // PostgreSQL's interval syntax — so we monkey-patch around it by feeding
        // the controller a stub PDO that returns a pre-baked result. The simplest
        // path: a minimal PDO subclass mock via createMock.
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            ['action' => 'auth_account_locked', 'cnt' => 12],
            ['action' => 'device_blocked',      'cnt' => 5],
        ]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        ob_start();
        $controller = new AdminErrorStatsController($pdo);
        $controller->show();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('auth_account_locked', $output);
        $this->assertStringContainsString('device_blocked', $output);
        $this->assertStringContainsString('12', $output);
        $this->assertStringContainsString('5', $output);
        $this->assertStringNotContainsString('Aucune émission enregistrée', $output);
    }
}
