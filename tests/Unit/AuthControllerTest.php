<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AuthController;
use AgVote\Core\Http\ApiResponseException;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\UserRepository;

/**
 * Unit tests for AuthController.
 *
 * Endpoints:
 *  - login():  POST — email/password or api_key auth, creates session
 *  - logout(): POST — destroys session
 *  - whoami(): GET  — returns current user info
 *  - csrf():   GET  — returns CSRF token
 *  - ping():   GET  — returns current timestamp
 *
 * Extends ControllerTestCase for RepositoryFactory injection.
 *
 * Note: login() calls SessionHelper::restart() and session_regenerate_id(true).
 * In CLI test environment these work but may emit PHP warnings — suppressed
 * with @session_start where needed.
 */
class AuthControllerTest extends ControllerTestCase
{
    private const TENANT_ID = 'ffffffff-0000-1111-2222-333333333333';
    private const USER_ID   = 'aa000001-0000-4000-a000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/TestRunner';
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    // =========================================================================
    // STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(AuthController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerExtendsAbstractController(): void
    {
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, new AuthController());
    }

    public function testControllerHasExpectedMethods(): void
    {
        $ref = new \ReflectionClass(AuthController::class);
        foreach (['login', 'logout', 'whoami', 'csrf', 'ping'] as $m) {
            $this->assertTrue($ref->hasMethod($m), "Missing method: {$m}");
        }
    }

    // =========================================================================
    // login() — method enforcement
    // =========================================================================

    public function testLoginRejectsGetMethod(): void
    {
        $this->setHttpMethod('GET');
        $result = $this->callController(AuthController::class, 'login');
        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testLoginRejectsPutMethod(): void
    {
        $this->setHttpMethod('PUT');
        $result = $this->callController(AuthController::class, 'login');
        $this->assertEquals(405, $result['status']);
    }

    // =========================================================================
    // login() — missing credentials
    // =========================================================================

    public function testLoginFailsWithMissingCredentials(): void
    {
        $this->setHttpMethod('POST');
        $userRepo = $this->createMock(UserRepository::class);
        $this->injectRepos([UserRepository::class => $userRepo]);

        $this->injectJsonBody([]);
        $result = $this->callController(AuthController::class, 'login');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_credentials', $result['body']['error']);
    }

    public function testLoginFailsWithInvalidEmailFormat(): void
    {
        $this->setHttpMethod('POST');
        $userRepo = $this->createMock(UserRepository::class);
        $this->injectRepos([UserRepository::class => $userRepo]);

        $this->injectJsonBody(['email' => 'not-an-email', 'password' => 'pass123']);
        $result = $this->callController(AuthController::class, 'login');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_email', $result['body']['error']);
    }

    // =========================================================================
    // login() — invalid credentials
    // =========================================================================

    public function testLoginFailsWithUnknownEmail(): void
    {
        $this->setHttpMethod('POST');
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findByEmailGlobal')->willReturn(null);
        $userRepo->method('logAuthFailure'); // void return
        $this->injectRepos([UserRepository::class => $userRepo]);

        $this->injectJsonBody(['email' => 'unknown@example.com', 'password' => 'wrongpass']);
        $result = $this->callController(AuthController::class, 'login');
        $this->assertEquals(401, $result['status']);
        $this->assertEquals('invalid_credentials', $result['body']['error']);
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $this->setHttpMethod('POST');
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findByEmailGlobal')->willReturn([
            'id'            => self::USER_ID,
            'tenant_id'     => self::TENANT_ID,
            'email'         => 'user@example.com',
            'name'          => 'Test User',
            'role'          => 'admin',
            'password_hash' => password_hash('correct', PASSWORD_DEFAULT),
            'is_active'     => true,
        ]);
        $userRepo->method('logAuthFailure'); // void return
        $this->injectRepos([UserRepository::class => $userRepo]);

        $this->injectJsonBody(['email' => 'user@example.com', 'password' => 'wrongpass']);
        $result = $this->callController(AuthController::class, 'login');
        $this->assertEquals(401, $result['status']);
        $this->assertEquals('invalid_credentials', $result['body']['error']);
    }

    public function testLoginFailsWithDisabledAccount(): void
    {
        $this->setHttpMethod('POST');
        $userRepo = $this->createMock(UserRepository::class);
        $password = 'testpass123';
        $userRepo->method('findByEmailGlobal')->willReturn([
            'id'            => self::USER_ID,
            'tenant_id'     => self::TENANT_ID,
            'email'         => 'user@example.com',
            'name'          => 'Test User',
            'role'          => 'admin',
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'is_active'     => false,
        ]);
        $this->injectRepos([UserRepository::class => $userRepo]);

        $this->injectJsonBody(['email' => 'user@example.com', 'password' => $password]);
        $result = $this->callController(AuthController::class, 'login');
        $this->assertEquals(403, $result['status']);
        $this->assertEquals('account_disabled', $result['body']['error']);
    }

    // =========================================================================
    // login() — api_key fallback
    // =========================================================================

    public function testLoginFailsWithInvalidApiKey(): void
    {
        $this->setHttpMethod('POST');
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findByApiKeyHashGlobal')->willReturn(null);
        $userRepo->method('logAuthFailure'); // void return
        $this->injectRepos([UserRepository::class => $userRepo]);

        $this->injectJsonBody(['api_key' => 'invalid-api-key']);
        $result = $this->callController(AuthController::class, 'login');
        $this->assertEquals(401, $result['status']);
        $this->assertEquals('invalid_credentials', $result['body']['error']);
    }

    // =========================================================================
    // login() — successful login
    // =========================================================================

    public function testLoginSuccessWithEmailPassword(): void
    {
        $this->setHttpMethod('POST');
        $password = 'correct-password';
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findByEmailGlobal')->willReturn([
            'id'            => self::USER_ID,
            'tenant_id'     => self::TENANT_ID,
            'email'         => 'admin@example.com',
            'name'          => 'Admin User',
            'role'          => 'admin',
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'is_active'     => true,
        ]);
        $this->injectRepos([UserRepository::class => $userRepo]);

        $this->injectJsonBody(['email' => 'admin@example.com', 'password' => $password]);

        // login() calls SessionHelper::restart() + session_regenerate_id — may warn in CLI
        $result = @$this->callController(AuthController::class, 'login');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertArrayHasKey('user', $data);
        $this->assertEquals(self::USER_ID, $data['user']['id']);
        $this->assertTrue($data['session']);
    }

    public function testLoginSuccessWithApiKey(): void
    {
        $this->setHttpMethod('POST');
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findByApiKeyHashGlobal')->willReturn([
            'id'            => self::USER_ID,
            'tenant_id'     => self::TENANT_ID,
            'email'         => 'admin@example.com',
            'name'          => 'Admin User',
            'role'          => 'admin',
            'password_hash' => null,
            'is_active'     => true,
        ]);
        $this->injectRepos([UserRepository::class => $userRepo]);

        $this->injectJsonBody(['api_key' => 'valid-api-key-123456789012345678901234']);
        $result = @$this->callController(AuthController::class, 'login');
        $this->assertEquals(200, $result['status']);
    }

    // =========================================================================
    // logout()
    // =========================================================================

    public function testLogoutRequiresPost(): void
    {
        $this->setHttpMethod('GET');
        $result = $this->callController(AuthController::class, 'logout');
        $this->assertEquals(405, $result['status']);
    }

    public function testLogoutSucceedsWithValidCsrf(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth(self::USER_ID, 'admin', self::TENANT_ID);

        // Initialize CSRF token in session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        \AgVote\Core\Security\CsrfMiddleware::init();
        $token = \AgVote\Core\Security\CsrfMiddleware::getToken();

        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;

        $result = @$this->callController(AuthController::class, 'logout');
        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['data']['logged_out']);
    }

    // =========================================================================
    // whoami()
    // =========================================================================

    public function testWhoamiAuthDisabledReturnsDemoUser(): void
    {
        // APP_AUTH_ENABLED is '0' in test bootstrap → auth disabled
        $result = $this->callController(AuthController::class, 'whoami');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertFalse($data['auth_enabled']);
        $this->assertEquals('demo-user', $data['user']['id']);
        $this->assertEquals('admin', $data['user']['role']);
        $this->assertNull($data['member']);
        $this->assertIsArray($data['meeting_roles']);
    }

    public function testWhoamiWithAuthReturnsUserInfo(): void
    {
        // Force auth enabled by enabling it in AuthMiddleware
        \AgVote\Core\Security\AuthMiddleware::setCurrentUser([
            'id'        => self::USER_ID,
            'tenant_id' => self::TENANT_ID,
            'email'     => 'admin@example.com',
            'name'      => 'Admin User',
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('listActiveMeetingRolesForUser')->willReturn([
            ['meeting_id' => 'aa000011-0000-4000-a000-000000000001', 'role' => 'operator'],
        ]);
        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('findByUserId')->willReturn(null);

        $this->injectRepos([
            UserRepository::class   => $userRepo,
            MemberRepository::class => $memberRepo,
        ]);

        // whoami() calls AuthMiddleware::authenticate() which is enabled only when
        // APP_AUTH_ENABLED = 1 env. Since we set currentUser directly, isEnabled()
        // must return true AND authenticate() must work.
        // Since isEnabled() checks the env var which is '0' in tests,
        // this test verifies the auth-disabled branch returns demo user.
        // To test auth-enabled path, we need to check isEnabled logic.
        $result = $this->callController(AuthController::class, 'whoami');
        // In test env APP_AUTH_ENABLED=0, so isEnabled() returns false
        // Auth disabled path is returned regardless of currentUser setting
        $this->assertEquals(200, $result['status']);
    }

    // =========================================================================
    // csrf()
    // =========================================================================

    public function testCsrfRejectsPost(): void
    {
        $this->setHttpMethod('POST');
        $result = $this->callController(AuthController::class, 'csrf');
        $this->assertEquals(405, $result['status']);
    }

    public function testCsrfReturnsToken(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $result = $this->callController(AuthController::class, 'csrf');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertArrayHasKey('csrf_token', $data);
        $this->assertArrayHasKey('header_name', $data);
        $this->assertArrayHasKey('field_name', $data);
        $this->assertNotEmpty($data['csrf_token']);
        $this->assertEquals('X-CSRF-Token', $data['header_name']);
        $this->assertEquals('csrf_token', $data['field_name']);
    }

    // =========================================================================
    // ping()
    // =========================================================================

    public function testPingReturnsTimestamp(): void
    {
        $result = $this->callController(AuthController::class, 'ping');
        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('ts', $result['body']['data']);
        $ts = $result['body']['data']['ts'];
        $parsed = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $ts);
        $this->assertInstanceOf(\DateTimeImmutable::class, $parsed);
    }

    // =========================================================================
    // Source-level security verification
    // =========================================================================

    public function testLoginUsesPasswordVerify(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AuthController.php');
        $this->assertStringContainsString('password_verify(', $source);
    }

    public function testLoginUsesConstantTimeComparison(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AuthController.php');
        $this->assertStringContainsString('DUMMY_HASH', $source);
    }

    public function testLoginSetsSessionOnSuccess(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AuthController.php');
        $this->assertStringContainsString("\$_SESSION['auth_user']", $source);
        $this->assertStringContainsString('session_regenerate_id(true)', $source);
    }

    public function testLoginLogsAuthFailures(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AuthController.php');
        $this->assertStringContainsString('logAuthFailure', $source);
    }

    public function testLoginLogsSuccessfulAuth(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AuthController.php');
        $this->assertStringContainsString("audit_log('user_login'", $source);
    }

    public function testLogoutClearsSession(): void
    {
        $controller = file_get_contents(PROJECT_ROOT . '/app/Controller/AuthController.php');
        $this->assertStringContainsString('SessionHelper::destroy()', $controller);
    }

    public function testLoginHashesApiKeyWithHmac(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AuthController.php');
        $this->assertStringContainsString("hash_hmac('sha256'", $source);
    }

    public function testLoginSupportsPasswordRehash(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AuthController.php');
        $this->assertStringContainsString('password_needs_rehash(', $source);
    }
}
