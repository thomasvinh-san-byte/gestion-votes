<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Http\ApiResponseException;
use AgVote\Core\Http\Request;
use AgVote\Core\Providers\RepositoryFactory;
use AgVote\Core\Security\AuthMiddleware;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Base TestCase for all controller unit tests.
 *
 * Provides:
 * - setUp/tearDown: reset global state (RepositoryFactory singleton,
 *   superglobals, Request::cachedRawBody, AuthMiddleware)
 * - injectRepos(array): inject mock repositories into RepositoryFactory via Reflection
 * - callController(string, string): instantiate controller and call handle($method),
 *   catching ApiResponseException and returning ['status' => int, 'body' => array]
 * - injectJsonBody(array): set Request::cachedRawBody for JSON body tests
 * - setHttpMethod(string): set $_SERVER['REQUEST_METHOD']
 * - setQueryParams(array): set $_GET
 * - setAuth(string, string, string): inject a test user into AuthMiddleware
 *
 * Pattern: RepositoryFactory is final and cannot be mocked. Instead, create a real
 * RepositoryFactory(null) and use Reflection to pre-populate its 'cache' property
 * with PHPUnit mocks of individual repository classes (which are NOT final).
 * Then set the factory as the singleton via Reflection on 'instance'. The cache-first
 * pattern in RepositoryFactory::get() ensures mocks are returned.
 */
abstract class ControllerTestCase extends TestCase
{
    // =========================================================================
    // SETUP / TEARDOWN
    // =========================================================================

    /** @var int Output buffer level captured at setUp() so tearDown can rewind to it. */
    private int $obLevelAtSetUp = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Snapshot ob level so tearDown can clean up any buffers left dangling by
        // controllers that exit through api_ok/api_fail (e.g. CSV/XLSX exports).
        $this->obLevelAtSetUp = ob_get_level();

        // Reset RepositoryFactory singleton so tests start with a clean state
        RepositoryFactory::reset();

        // Reset superglobals
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        $_FILES = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        // Reset Request::cachedRawBody via Reflection
        $ref = new ReflectionClass(Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        // Reset AuthMiddleware state
        AuthMiddleware::reset();

        // Ensure auth is disabled for controller tests (mirrors bootstrap.php)
        putenv('APP_AUTH_ENABLED=0');
    }

    protected function tearDown(): void
    {
        // Rewind any output buffers left dangling by the test (e.g. exports
        // that close their own buffer mid-execution). PHPUnit otherwise marks
        // the test as risky/failed with "closed output buffers other than its own".
        while (ob_get_level() > $this->obLevelAtSetUp) {
            @ob_end_clean();
        }

        // Reset AuthMiddleware first
        AuthMiddleware::reset();

        // Reset Request::cachedRawBody
        $ref = new ReflectionClass(Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        // Reset superglobals
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        $_FILES = [];

        // Reset RepositoryFactory singleton
        RepositoryFactory::reset();

        parent::tearDown();
    }

    // =========================================================================
    // REPOSITORY INJECTION
    // =========================================================================

    /**
     * Inject mock repositories into the RepositoryFactory singleton.
     *
     * Usage:
     *   $mockMeeting = $this->createMock(MeetingRepository::class);
     *   $this->injectRepos([MeetingRepository::class => $mockMeeting]);
     *
     * After calling this, any controller code that calls $this->repo()->meeting()
     * will receive $mockMeeting.
     *
     * @param array<class-string, object> $repoMocks Map of class name => mock instance
     */
    protected function injectRepos(array $repoMocks): void
    {
        // Create a fresh RepositoryFactory with no real PDO
        $factory = new RepositoryFactory(null);

        // Inject mocks into the cache property via Reflection
        $refFactory = new ReflectionClass(RepositoryFactory::class);

        $cacheProp = $refFactory->getProperty('cache');
        $cacheProp->setAccessible(true);
        $cacheProp->setValue($factory, $repoMocks);

        // Set this factory as the singleton so controllers get it
        $instanceProp = $refFactory->getProperty('instance');
        $instanceProp->setAccessible(true);
        $instanceProp->setValue(null, $factory);
    }

    // =========================================================================
    // CONTROLLER INVOCATION
    // =========================================================================

    /**
     * Instantiate a controller and call handle($method), capturing the response.
     *
     * Controllers always terminate via ApiResponseException (thrown by api_ok/api_fail).
     * This method catches that exception and returns a normalized response array.
     *
     * @param class-string $controllerClass Fully-qualified controller class name
     * @param string $method Method name to pass to handle()
     * @return array{status: int, body: array}
     */
    protected function callController(string $controllerClass, string $method): array
    {
        $controller = new $controllerClass();
        try {
            $controller->handle($method);
            // If handle() returns without throwing, something is wrong
            $this->fail("Expected ApiResponseException from {$controllerClass}::{$method}() was not thrown");
        } catch (ApiResponseException $e) {
            return [
                'status' => $e->getResponse()->getStatusCode(),
                'body' => $e->getResponse()->getBody(),
            ];
        }
        // Unreachable, but satisfies static analysis
        return ['status' => 500, 'body' => []];
    }

    // =========================================================================
    // REQUEST HELPERS
    // =========================================================================

    /**
     * Set the JSON request body for the next controller call.
     *
     * Injects the encoded JSON into Request::cachedRawBody so that Request::getRawBody()
     * and api_request() return the correct data without reading php://input.
     *
     * @param array $data Data to JSON-encode as the request body
     */
    protected function injectJsonBody(array $data): void
    {
        $ref = new ReflectionClass(Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, json_encode($data));
    }

    /**
     * Set the HTTP method for the next controller call.
     *
     * @param string $method HTTP method (GET, POST, PUT, PATCH, DELETE)
     */
    protected function setHttpMethod(string $method): void
    {
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
    }

    /**
     * Set GET query parameters for the next controller call.
     *
     * @param array $params Key-value pairs to set in $_GET
     */
    protected function setQueryParams(array $params): void
    {
        $_GET = $params;
    }

    // =========================================================================
    // AUTH HELPERS
    // =========================================================================

    /**
     * Inject a test user into AuthMiddleware for the next controller call.
     *
     * This bypasses the real authentication flow and injects a pre-built user array
     * directly into AuthMiddleware::$currentUser.
     *
     * @param string $userId User ID (UUID or test string)
     * @param string $role System role: 'admin', 'operator', 'auditor', 'viewer'
     * @param string $tenantId Tenant ID
     */
    protected function setAuth(string $userId, string $role, string $tenantId): void
    {
        AuthMiddleware::setCurrentUser([
            'id' => $userId,
            'role' => $role,
            'tenant_id' => $tenantId,
            'name' => 'Test User',
            'is_active' => true,
        ]);
    }
}
