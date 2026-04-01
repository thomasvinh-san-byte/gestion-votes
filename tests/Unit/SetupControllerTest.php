<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\SetupController;
use AgVote\Repository\SetupRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for SetupController.
 *
 * SetupController is a standalone HTML controller (not extending AbstractController).
 * It uses output buffering to capture HTML output and header() calls.
 *
 * Test coverage:
 *  - Guard logic: redirect to /login when admin already exists
 *  - GET render: show setup form when no admin exists
 *  - POST valid: create tenant + admin, redirect to /login?setup=ok
 *  - POST guard: redirect when admin already exists
 *  - POST validation: missing fields, invalid email, short password, mismatched confirmation
 *
 * Strategy:
 *  - Inject a mock SetupRepository into the controller via constructor injection
 *    (the controller accepts an optional SetupRepository parameter for testing)
 *  - Capture output buffer and thrown RedirectException to verify behavior
 */
class SetupControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_GET    = [];
        $_POST   = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $_GET  = [];
        $_POST = [];
        parent::tearDown();
    }

    // =========================================================================
    // HELPER: run the controller and capture output + redirect
    // =========================================================================

    /**
     * Invoke SetupController::setup() with a mock repository.
     * Returns ['output' => string, 'redirect' => string|null, 'status' => int|null].
     *
     * @param SetupRepository $repo       Mock repository
     * @param string          $method     HTTP method (GET|POST)
     * @param array           $postData   POST fields to inject
     * @return array{output: string, redirect: string|null, status: int|null}
     */
    private function runSetup(SetupRepository $repo, string $method = 'GET', array $postData = []): array
    {
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        if ($postData !== []) {
            $_POST = $postData;
        }

        $redirect = null;
        $status   = null;
        ob_start();
        try {
            $controller = new SetupController($repo);
            $controller->setup();
        } catch (\AgVote\Controller\SetupRedirectException $e) {
            $redirect = $e->getLocation();
            $status   = $e->getStatusCode();
        }
        $output = (string) ob_get_clean();

        return ['output' => $output, 'redirect' => $redirect, 'status' => $status];
    }

    // =========================================================================
    // SETUPTESTREPOSITORY — helper mock factory
    // =========================================================================

    private function mockRepo(bool $hasAdmin, bool $shouldCreate = false): SetupRepository
    {
        $mock = $this->createMock(SetupRepository::class);
        $mock->method('hasAnyAdmin')->willReturn($hasAdmin);
        if ($shouldCreate) {
            $mock->method('createTenantAndAdmin')->willReturn([
                'tenant_id' => 'tid-001',
                'user_id'   => 'uid-001',
            ]);
        }
        return $mock;
    }

    // =========================================================================
    // Test 1: hasAnyAdmin() returns false when no admin users exist
    // =========================================================================

    public function testHasAnyAdminReturnsFalseWhenNoAdmin(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn('0');
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new SetupRepository($pdo);
        $result = $repo->hasAnyAdmin();
        $this->assertFalse($result);
    }

    // =========================================================================
    // Test 2: hasAnyAdmin() returns true when admin users exist
    // =========================================================================

    public function testHasAnyAdminReturnsTrueWhenAdminExists(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn('1');
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new SetupRepository($pdo);
        $result = $repo->hasAnyAdmin();
        $this->assertTrue($result);
    }

    // =========================================================================
    // Test 3: GET /setup when admin exists -> redirect 302 to /login
    // =========================================================================

    public function testShowFormRedirectsWhenAdminExists(): void
    {
        $repo   = $this->mockRepo(hasAdmin: true);
        $result = $this->runSetup($repo, 'GET');

        $this->assertNotNull($result['redirect'], 'Expected a redirect');
        $this->assertStringContainsString('/login', $result['redirect']);
        $this->assertSame(302, $result['status']);
    }

    // =========================================================================
    // Test 4: GET /setup when no admin -> renders setup form (200)
    // =========================================================================

    public function testShowFormRendersFormWhenNoAdmin(): void
    {
        $repo   = $this->mockRepo(hasAdmin: false);
        $result = $this->runSetup($repo, 'GET');

        $this->assertNull($result['redirect'], 'Should not redirect');
        $this->assertStringContainsString('organisation_name', $result['output']);
        $this->assertStringContainsString('Configuration initiale', $result['output']);
    }

    // =========================================================================
    // Test 5: POST /setup with valid data when no admin -> creates tenant + admin, redirects
    // =========================================================================

    public function testPostValidDataCreatesAndRedirects(): void
    {
        $repo = $this->mockRepo(hasAdmin: false, shouldCreate: true);
        $repo->expects($this->once())->method('createTenantAndAdmin');

        $result = $this->runSetup($repo, 'POST', [
            'organisation_name'    => 'Mon Association',
            'admin_name'           => 'Jean Dupont',
            'admin_email'          => 'admin@example.com',
            'admin_password'       => 'Secret123!',
            'admin_password_confirm' => 'Secret123!',
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertStringContainsString('/login', $result['redirect']);
        $this->assertStringContainsString('setup=ok', $result['redirect']);
    }

    // =========================================================================
    // Test 6: POST /setup when admin already exists -> redirect 302 to /login (guard)
    // =========================================================================

    public function testPostGuardRedirectsWhenAdminExists(): void
    {
        $repo   = $this->mockRepo(hasAdmin: true);
        $result = $this->runSetup($repo, 'POST', [
            'organisation_name'    => 'Mon Association',
            'admin_name'           => 'Jean Dupont',
            'admin_email'          => 'admin@example.com',
            'admin_password'       => 'Secret123!',
            'admin_password_confirm' => 'Secret123!',
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertStringContainsString('/login', $result['redirect']);
        $this->assertSame(302, $result['status']);
    }

    // =========================================================================
    // Test 7: POST /setup with missing fields -> re-renders form with error message
    // =========================================================================

    public function testPostMissingFieldsReRendersFormWithError(): void
    {
        $repo   = $this->mockRepo(hasAdmin: false);
        $result = $this->runSetup($repo, 'POST', [
            'organisation_name' => '',
            'admin_name'        => '',
            'admin_email'       => '',
            'admin_password'    => '',
            'admin_password_confirm' => '',
        ]);

        $this->assertNull($result['redirect'], 'Should not redirect on validation failure');
        $this->assertStringContainsString('organisation_name', $result['output']);
        // Should show an error
        $this->assertStringContainsString('requis', strtolower($result['output']));
    }

    // =========================================================================
    // Test 8: POST /setup with invalid email -> re-renders form with error
    // =========================================================================

    public function testPostInvalidEmailReRendersWithError(): void
    {
        $repo   = $this->mockRepo(hasAdmin: false);
        $result = $this->runSetup($repo, 'POST', [
            'organisation_name'    => 'Mon Association',
            'admin_name'           => 'Jean Dupont',
            'admin_email'          => 'not-an-email',
            'admin_password'       => 'Secret123!',
            'admin_password_confirm' => 'Secret123!',
        ]);

        $this->assertNull($result['redirect'], 'Should not redirect on invalid email');
        $this->assertStringContainsString('organisation_name', $result['output']);
    }

    // =========================================================================
    // Test 9: POST /setup with password < 8 chars -> re-renders form with error
    // =========================================================================

    public function testPostShortPasswordReRendersWithError(): void
    {
        $repo   = $this->mockRepo(hasAdmin: false);
        $result = $this->runSetup($repo, 'POST', [
            'organisation_name'    => 'Mon Association',
            'admin_name'           => 'Jean Dupont',
            'admin_email'          => 'admin@example.com',
            'admin_password'       => 'short',
            'admin_password_confirm' => 'short',
        ]);

        $this->assertNull($result['redirect'], 'Should not redirect on short password');
        $this->assertStringContainsString('organisation_name', $result['output']);
    }

    // =========================================================================
    // Test 10: POST /setup with mismatched passwords -> re-renders form with error
    // =========================================================================

    public function testPostMismatchedPasswordsReRendersWithError(): void
    {
        $repo   = $this->mockRepo(hasAdmin: false);
        $result = $this->runSetup($repo, 'POST', [
            'organisation_name'    => 'Mon Association',
            'admin_name'           => 'Jean Dupont',
            'admin_email'          => 'admin@example.com',
            'admin_password'       => 'Secret123!',
            'admin_password_confirm' => 'DifferentPassword!',
        ]);

        $this->assertNull($result['redirect'], 'Should not redirect on mismatched passwords');
        $this->assertStringContainsString('organisation_name', $result['output']);
    }
}
