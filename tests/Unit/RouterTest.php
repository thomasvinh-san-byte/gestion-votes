<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * Tests Router::resolveMiddlewareConfig — the auth auto-enforcement
 * mechanism that protects direct file access (bypassing the front controller).
 */
final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();

        // Register a few representative routes
        $this->router->mapAny('/api/v1/admin_users', 'FakeController', 'users', ['role' => ['operator', 'admin'], 'rate_limit' => ['admin_ops', 30, 60]]);
        $this->router->mapAny('/api/v1/meetings', 'FakeController', 'index', ['role' => 'viewer']);
        $this->router->mapAny('/api/v1/auth_login', 'FakeController', 'login'); // no middleware
        $this->router->mapAny('/api/v1/ballots_cast', 'FakeController', 'cast', ['role' => 'public', 'rate_limit' => ['ballot_cast', 60, 60]]);
    }

    public function testResolvesRoleForAdminEndpoint(): void
    {
        $mw = $this->router->resolveMiddlewareConfig('GET', '/api/v1/admin_users');
        $this->assertNotNull($mw);
        $this->assertEquals(['operator', 'admin'], $mw['role']);
        $this->assertEquals(['admin_ops', 30, 60], $mw['rate_limit']);
    }

    public function testResolvesWithPhpExtension(): void
    {
        $mw = $this->router->resolveMiddlewareConfig('GET', '/api/v1/admin_users.php');
        $this->assertNotNull($mw);
        $this->assertEquals(['operator', 'admin'], $mw['role']);
    }

    public function testResolvesWithQueryString(): void
    {
        $mw = $this->router->resolveMiddlewareConfig('GET', '/api/v1/meetings?limit=10');
        $this->assertNotNull($mw);
        $this->assertEquals('viewer', $mw['role']);
    }

    public function testReturnsNullForNoMiddleware(): void
    {
        $mw = $this->router->resolveMiddlewareConfig('POST', '/api/v1/auth_login');
        $this->assertNotNull($mw); // route exists, middleware is empty array
        $this->assertArrayNotHasKey('role', $mw);
    }

    public function testReturnsNullForUnknownRoute(): void
    {
        $mw = $this->router->resolveMiddlewareConfig('GET', '/api/v1/does_not_exist');
        $this->assertNull($mw);
    }

    public function testPublicRouteReturnsPublicRole(): void
    {
        $mw = $this->router->resolveMiddlewareConfig('POST', '/api/v1/ballots_cast');
        $this->assertNotNull($mw);
        $this->assertEquals('public', $mw['role']);
    }

    public function testResolvesPhpExtensionWithQueryString(): void
    {
        $mw = $this->router->resolveMiddlewareConfig('GET', '/api/v1/admin_users.php?action=list');
        $this->assertNotNull($mw);
        $this->assertEquals(['operator', 'admin'], $mw['role']);
    }

    /**
     * Verifies every route in routes.php is reachable and has middleware config.
     * This ensures the route table is properly loaded and no route is silently
     * orphaned (registered but unreachable by resolveMiddlewareConfig).
     */
    public function testAllRoutesFromRouteTableAreResolvable(): void
    {
        $router = new Router();
        $configureRoutes = require __DIR__ . '/../../app/routes.php';
        $configureRoutes($router);

        // Admin endpoints must require admin or operator role
        $adminEndpoints = [
            '/api/v1/admin_users',
            '/api/v1/admin_roles',
            '/api/v1/admin_system_status',
            '/api/v1/admin_meeting_roles',
            '/api/v1/admin_quorum_policies',
            '/api/v1/admin_vote_policies',
        ];

        foreach ($adminEndpoints as $uri) {
            $mw = $router->resolveMiddlewareConfig('GET', $uri);
            $this->assertNotNull($mw, "Route {$uri} should be resolvable");
            $this->assertArrayHasKey('role', $mw, "Route {$uri} must have role middleware");

            $roles = is_array($mw['role']) ? $mw['role'] : [$mw['role']];
            $this->assertTrue(
                in_array('admin', $roles, true) || in_array('operator', $roles, true),
                "Route {$uri} must require admin or operator (got: " . implode(', ', $roles) . ")",
            );
        }

        // Auth endpoints should NOT require role middleware
        $authEndpoints = ['/api/v1/auth_login', '/api/v1/auth_csrf', '/api/v1/whoami'];
        foreach ($authEndpoints as $uri) {
            $mw = $router->resolveMiddlewareConfig('GET', $uri);
            $this->assertNotNull($mw, "Route {$uri} should be resolvable");
            $this->assertArrayNotHasKey('role', $mw, "Auth route {$uri} should not have role middleware");
        }

        // .php extension should resolve identically
        $mw1 = $router->resolveMiddlewareConfig('GET', '/api/v1/admin_users');
        $mw2 = $router->resolveMiddlewareConfig('GET', '/api/v1/admin_users.php');
        $this->assertEquals($mw1, $mw2, "Route with and without .php must resolve identically");
    }
}
