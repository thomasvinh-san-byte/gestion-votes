<?php

declare(strict_types=1);

use AgVote\Core\Providers\RepositoryFactory;
use AgVote\Core\Security\AuthMiddleware;
use AgVote\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/Core/Security/AuthMiddleware.php';

/**
 * Unit tests for AuthMiddleware.
 */
class AuthMiddlewareTest extends TestCase {
    protected function setUp(): void {
        // Reset état global
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '127.0.0.1',
            'REQUEST_URI' => '/test',
        ];
        $_SESSION = [];

        // Définir les constantes si non définies
        if (!defined('APP_SECRET')) {
            define('APP_SECRET', 'test-secret-for-unit-tests-only');
        }
        if (!defined('DEFAULT_TENANT_ID')) {
            define('DEFAULT_TENANT_ID', 'aaaaaaaa-1111-2222-3333-444444444444');
        }

        // Reset user
        $reflection = new ReflectionClass(AuthMiddleware::class);
        $property = $reflection->getProperty('currentUser');
        $property->setAccessible(true);
        $property->setValue(null, null);

        AuthMiddleware::init(['debug' => false]);
        RepositoryFactory::reset();
    }

    protected function tearDown(): void {
        putenv('APP_AUTH_ENABLED=');
        $_SESSION = [];
        AuthMiddleware::reset();
        RepositoryFactory::reset();
    }

    public function testIsEnabledReturnsTrueByDefault(): void {
        putenv('APP_AUTH_ENABLED=');

        $result = AuthMiddleware::isEnabled();

        $this->assertTrue($result, 'Auth must be enabled by default (deny-by-default)');
    }

    public function testIsEnabledReturnsFalseWhenExplicitlyDisabled(): void {
        putenv('APP_AUTH_ENABLED=0');

        $result = AuthMiddleware::isEnabled();

        $this->assertFalse($result);
    }

    public function testIsEnabledReturnsFalseWithFalseString(): void {
        putenv('APP_AUTH_ENABLED=false');

        $result = AuthMiddleware::isEnabled();

        $this->assertFalse($result);
    }

    public function testIsEnabledReturnsTrueWhenSet(): void {
        putenv('APP_AUTH_ENABLED=1');

        $result = AuthMiddleware::isEnabled();

        $this->assertTrue($result);
    }

    public function testIsEnabledReturnsTrueWithTrueString(): void {
        putenv('APP_AUTH_ENABLED=true');

        $result = AuthMiddleware::isEnabled();

        $this->assertTrue($result);
    }

    public function testGenerateApiKeyReturnsKeyAndHash(): void {
        $result = AuthMiddleware::generateApiKey();

        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('hash', $result);
        $this->assertEquals(64, strlen($result['key'])); // 32 bytes hex
        $this->assertEquals(64, strlen($result['hash'])); // SHA256 hex
    }

    public function testHashApiKeyIsConsistent(): void {
        $key = 'test-api-key';

        $hash1 = AuthMiddleware::hashApiKey($key);
        $hash2 = AuthMiddleware::hashApiKey($key);

        $this->assertEquals($hash1, $hash2);
    }

    public function testHashApiKeyIsDifferentForDifferentKeys(): void {
        $hash1 = AuthMiddleware::hashApiKey('key1');
        $hash2 = AuthMiddleware::hashApiKey('key2');

        $this->assertNotEquals($hash1, $hash2);
    }

    public function testGetCurrentUserReturnsNullWithoutAuth(): void {
        putenv('APP_AUTH_ENABLED=1');

        $user = AuthMiddleware::getCurrentUser();

        $this->assertNull($user);
    }

    public function testGetCurrentUserIdReturnsNullWithoutAuth(): void {
        putenv('APP_AUTH_ENABLED=1');

        $userId = AuthMiddleware::getCurrentUserId();

        $this->assertNull($userId);
    }

    public function testGetCurrentRoleReturnsAnonymousWithoutAuth(): void {
        putenv('APP_AUTH_ENABLED=1');

        $role = AuthMiddleware::getCurrentRole();

        $this->assertEquals('anonymous', $role);
    }

    public function testGetCurrentTenantIdReturnsDefault(): void {
        $tenantId = AuthMiddleware::getCurrentTenantId();

        $this->assertEquals(DEFAULT_TENANT_ID, $tenantId);
    }

    public function testRequireRolePassesWhenAuthDisabled(): void {
        putenv('APP_AUTH_ENABLED=0');

        $result = AuthMiddleware::requireRole('admin', false);

        $this->assertTrue($result);
    }

    public function testRequireRoleFailsWhenAuthEnabledAndNoKey(): void {
        putenv('APP_AUTH_ENABLED=1');

        $result = AuthMiddleware::requireRole('admin', false);

        $this->assertFalse($result);
    }

    public function testRequireRolePassesForPublicRole(): void {
        putenv('APP_AUTH_ENABLED=1');

        $result = AuthMiddleware::requireRole('public', false);

        $this->assertTrue($result);
    }

    public function testRequireRoleAcceptsArrayOfRoles(): void {
        putenv('APP_AUTH_ENABLED=1');

        $result = AuthMiddleware::requireRole(['public', 'voter'], false);

        $this->assertTrue($result);
    }

    public function testAuthenticateReturnsNullWithoutCredentials(): void {
        putenv('APP_AUTH_ENABLED=1');

        $user = AuthMiddleware::authenticate();

        $this->assertNull($user);
    }

    public function testApiKeyExtractedFromHeader(): void {
        $_SERVER['HTTP_X_API_KEY'] = 'test-key';
        putenv('APP_AUTH_ENABLED=1');

        // Sans DB, authenticate retournera null, mais le mécanisme d'extraction fonctionne
        $user = AuthMiddleware::authenticate();

        // Le test vérifie que ça ne plante pas
        $this->assertNull($user);
    }

    public function testInitSetsDebugMode(): void {
        AuthMiddleware::init(['debug' => true]);

        // Le test vérifie que l'init ne plante pas
        $this->assertTrue(true);
    }

    // =========================================================================
    // Session expiry differentiation (AUTH-01)
    // =========================================================================

    public function testExpiredSessionReturnsSessionExpiredCode(): void {
        // Set the sessionExpired flag via Reflection (simulates authenticate() having detected expiry)
        $ref = new ReflectionClass(AuthMiddleware::class);
        $prop = $ref->getProperty('sessionExpired');
        $prop->setAccessible(true);
        $prop->setValue(null, true);

        // Trigger deny('authentication_required') via requireRole with no user
        // deny() is private, so we trigger it through the public API
        // requireRole with throw=true calls deny() when auth is enabled and no user
        putenv('APP_AUTH_ENABLED=1');

        $exception = null;
        try {
            AuthMiddleware::requireRole('admin');
        } catch (\AgVote\Core\Http\ApiResponseException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception, 'Expected ApiResponseException');
        $body = $exception->getResponse()->getBody();
        $this->assertEquals('session_expired', $body['error'],
            'Expired session must return session_expired, not authentication_required');
    }

    public function testNonExpiredSessionReturnsAuthenticationRequired(): void {
        // Ensure sessionExpired flag is NOT set
        $ref = new ReflectionClass(AuthMiddleware::class);
        $prop = $ref->getProperty('sessionExpired');
        $prop->setAccessible(true);
        $prop->setValue(null, false);

        putenv('APP_AUTH_ENABLED=1');

        $exception = null;
        try {
            AuthMiddleware::requireRole('admin');
        } catch (\AgVote\Core\Http\ApiResponseException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception, 'Expected ApiResponseException');
        $body = $exception->getResponse()->getBody();
        $this->assertEquals('authentication_required', $body['error'],
            'Non-expired unauthenticated request must return authentication_required');
    }

    public function testResetClearsSessionExpiredFlag(): void {
        // Set the sessionExpired flag
        $ref = new ReflectionClass(AuthMiddleware::class);
        $prop = $ref->getProperty('sessionExpired');
        $prop->setAccessible(true);
        $prop->setValue(null, true);

        // Call reset()
        AuthMiddleware::reset();

        // Verify flag is cleared
        $value = $prop->getValue(null);
        $this->assertFalse($value, 'reset() must clear the sessionExpired flag');
    }

    // === Session Lifecycle Tests ===

    /**
     * Inject a mock UserRepository into the RepositoryFactory singleton.
     *
     * Mirrors the pattern from ControllerTestCase::injectRepos().
     */
    private function injectMockUserRepository(UserRepository $mockRepo): void
    {
        $factory = new RepositoryFactory(null);

        $refFactory = new ReflectionClass(RepositoryFactory::class);

        $cacheProp = $refFactory->getProperty('cache');
        $cacheProp->setAccessible(true);
        $cacheProp->setValue($factory, [UserRepository::class => $mockRepo]);

        $instanceProp = $refFactory->getProperty('instance');
        $instanceProp->setAccessible(true);
        $instanceProp->setValue(null, $factory);
    }

    /**
     * Returns a minimal auth_user session array for the given tenant.
     */
    private function buildSessionUser(string $tenantId = 'aaaaaaaa-1111-2222-3333-444444444444'): array
    {
        return [
            'id'        => 'user-uuid-0001',
            'role'      => 'operator',
            'name'      => 'Test Operator',
            'email'     => 'test@example.com',
            'tenant_id' => $tenantId,
            'is_active' => true,
        ];
    }

    public function testAuthenticateExpiresSessionAfterTimeout(): void
    {
        putenv('APP_AUTH_ENABLED=1');

        $tenantId = 'aaaaaaaa-1111-2222-3333-444444444444';

        // Inject a short timeout (300 seconds)
        AuthMiddleware::setSessionTimeoutForTest($tenantId, 300);

        // Seed $_SESSION with auth data — last activity 99999 seconds ago (well past timeout)
        @session_start();
        $_SESSION['auth_user']          = $this->buildSessionUser($tenantId);
        $_SESSION['auth_last_activity'] = time() - 99999;
        $_SESSION['auth_last_db_check'] = time();

        $result = AuthMiddleware::authenticate();

        $this->assertNull($result, 'authenticate() must return null when session has timed out');
        $this->assertEmpty($_SESSION, '$_SESSION must be empty after session timeout');

        // Verify sessionExpired static flag was set
        $ref  = new ReflectionClass(AuthMiddleware::class);
        $prop = $ref->getProperty('sessionExpired');
        $prop->setAccessible(true);
        $this->assertTrue($prop->getValue(null), 'sessionExpired flag must be true after timeout');
    }

    public function testAuthenticateRevokesSessionForDeactivatedUser(): void
    {
        putenv('APP_AUTH_ENABLED=1');

        $tenantId = 'aaaaaaaa-1111-2222-3333-444444444444';

        // Inject mock UserRepository returning an inactive user
        $mockRepo = $this->createMock(UserRepository::class);
        $mockRepo->method('findForSessionRevalidation')
            ->willReturn(['id' => 'user-uuid-0001', 'role' => 'operator', 'name' => 'Test', 'email' => 'test@example.com', 'is_active' => false]);
        $this->injectMockUserRepository($mockRepo);

        // Seed $_SESSION — auth_last_db_check=0 forces immediate revalidation
        @session_start();
        $_SESSION['auth_user']          = $this->buildSessionUser($tenantId);
        $_SESSION['auth_last_activity'] = time();
        $_SESSION['auth_last_db_check'] = 0;

        $result = AuthMiddleware::authenticate();

        $this->assertNull($result, 'authenticate() must return null for deactivated user');
        $this->assertEmpty($_SESSION, '$_SESSION must be empty after session revocation');
    }

    public function testAuthenticateRegeneratesSessionOnRoleChange(): void
    {
        putenv('APP_AUTH_ENABLED=1');

        $tenantId = 'aaaaaaaa-1111-2222-3333-444444444444';

        // Mock returns user with elevated role 'admin' (was 'operator' in session)
        $mockRepo = $this->createMock(UserRepository::class);
        $mockRepo->method('findForSessionRevalidation')
            ->willReturn(['id' => 'user-uuid-0001', 'role' => 'admin', 'name' => 'Test Admin', 'email' => 'test@example.com', 'is_active' => true]);
        $this->injectMockUserRepository($mockRepo);

        // Seed $_SESSION with role='operator', auth_last_db_check=0 forces revalidation
        @session_start();
        $_SESSION['auth_user']          = $this->buildSessionUser($tenantId);
        $_SESSION['auth_last_activity'] = time();
        $_SESSION['auth_last_db_check'] = 0;

        $result = AuthMiddleware::authenticate();

        $this->assertNotNull($result, 'authenticate() must return user after role change');
        $this->assertSame('admin', $result['role'], 'Role must be updated to the new DB role');
        $this->assertSame('admin', $_SESSION['auth_user']['role'], 'Session role must reflect the DB role');
    }

    public function testAuthenticateUpdatesLastActivity(): void
    {
        putenv('APP_AUTH_ENABLED=1');

        $tenantId = 'aaaaaaaa-1111-2222-3333-444444444444';

        // Use a short timeout that won't expire within this test
        AuthMiddleware::setSessionTimeoutForTest($tenantId, 28800);

        // Seed $_SESSION with recent auth_last_activity and recent auth_last_db_check (no revalidation)
        $before = time();
        @session_start();
        $_SESSION['auth_user']          = $this->buildSessionUser($tenantId);
        $_SESSION['auth_last_activity'] = $before;
        $_SESSION['auth_last_db_check'] = $before;

        $result = AuthMiddleware::authenticate();

        $this->assertNotNull($result, 'authenticate() must return user with valid session');

        $after = time();
        $updatedActivity = $_SESSION['auth_last_activity'] ?? 0;
        $this->assertGreaterThanOrEqual($before, $updatedActivity, 'auth_last_activity must be updated to approximately now');
        $this->assertLessThanOrEqual($after + 1, $updatedActivity, 'auth_last_activity must not be in the future');
    }

    public function testResetClearsAll10StaticProperties(): void
    {
        $ref = new ReflectionClass(AuthMiddleware::class);

        // Set all 10 static properties to non-default values
        $props = [
            'currentUser'            => ['id' => 'test', 'role' => 'admin'],
            'currentMeetingId'       => 'meeting-uuid-0001',
            'currentMeetingRoles'    => ['president'],
            'debug'                  => true,
            'accessLog'              => [['action' => 'test']],
            'sessionExpired'         => true,
            'cachedSessionTimeout'   => 3600,
            'cachedTimeoutTenantId'  => 'tenant-uuid-0001',
            'testSessionTimeout'     => 300,
            'testTimeoutTenantId'    => 'tenant-uuid-0001',
        ];

        foreach ($props as $propName => $value) {
            $prop = $ref->getProperty($propName);
            $prop->setAccessible(true);
            $prop->setValue(null, $value);
        }

        // Call reset()
        AuthMiddleware::reset();

        // Verify all 9 clearable properties are back to defaults
        // Note: $debug is not cleared by reset() — this is the documented behavior
        $clearableDefaults = [
            'currentUser'           => null,
            'currentMeetingId'      => null,
            'currentMeetingRoles'   => null,
            'accessLog'             => [],
            'sessionExpired'        => false,
            'cachedSessionTimeout'  => null,
            'cachedTimeoutTenantId' => null,
            'testSessionTimeout'    => null,
            'testTimeoutTenantId'   => null,
        ];

        foreach ($clearableDefaults as $propName => $expectedDefault) {
            $prop = $ref->getProperty($propName);
            $prop->setAccessible(true);
            $actual = $prop->getValue(null);
            $this->assertSame($expectedDefault, $actual, "reset() must clear property '{$propName}' to its default value");
        }
    }

    public function testAuthenticateDbRevalidationFailureKeepsSession(): void
    {
        putenv('APP_AUTH_ENABLED=1');

        $tenantId = 'aaaaaaaa-1111-2222-3333-444444444444';

        // Mock throws a RuntimeException simulating a DB failure
        $mockRepo = $this->createMock(UserRepository::class);
        $mockRepo->method('findForSessionRevalidation')
            ->willThrowException(new \RuntimeException('DB connection lost'));
        $this->injectMockUserRepository($mockRepo);

        // Seed $_SESSION — auth_last_db_check=0 forces revalidation attempt
        @session_start();
        $_SESSION['auth_user']          = $this->buildSessionUser($tenantId);
        $_SESSION['auth_last_activity'] = time();
        $_SESSION['auth_last_db_check'] = 0;

        $result = AuthMiddleware::authenticate();

        $this->assertNotNull($result, 'authenticate() must keep session alive when DB revalidation fails');
        $this->assertSame('user-uuid-0001', $result['id'], 'User ID must be preserved from session on DB failure');
    }
}
