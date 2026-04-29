<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\SetupController;
use AgVote\Controller\SetupRedirectException;
use AgVote\Repository\SetupRepository;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the 2026-04-29 hardening of /setup:
 *  - Opaque 404 response when any admin already exists (no info leak)
 *  - CSRF synchronizer token required on POST (defense-in-depth)
 *  - Fresh instance can still complete setup with a valid token
 *
 * These tests pin behaviour that is easy to break by accident later
 * (e.g. someone "fixes" the 404 back to a redirect, or removes the
 * CSRF gate because the legacy comment claimed it was unnecessary).
 */
final class SetupControllerHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the bootstrap flag is set even if this file is loaded
        // outside the standard PHPUnit run.
        if (!defined('PHPUNIT_RUNNING')) {
            define('PHPUNIT_RUNNING', true);
        }

        $_GET    = [];
        $_POST   = [];
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
        $_SERVER['REQUEST_URI']    = '/setup';
    }

    protected function tearDown(): void
    {
        $_GET    = [];
        $_POST   = [];
        $_SESSION = [];
        parent::tearDown();
    }

    /**
     * Build a SetupRepository mock with the desired hasAnyAdmin() value.
     */
    private function repo(bool $hasAdmin, bool $expectCreate = false): SetupRepository
    {
        $mock = $this->createMock(SetupRepository::class);
        $mock->method('hasAnyAdmin')->willReturn($hasAdmin);
        if ($expectCreate) {
            $mock->expects($this->once())
                ->method('createTenantAndAdmin')
                ->willReturn(['tenant_id' => 'tid-001', 'user_id' => 'uid-001']);
        } else {
            $mock->expects($this->never())->method('createTenantAndAdmin');
        }
        return $mock;
    }

    /**
     * Invoke SetupController::setup() and capture output + redirect.
     *
     * @return array{output: string, redirect: ?string, status: ?int}
     */
    private function invokeSetup(SetupRepository $repo): array
    {
        $redirect = null;
        $status   = null;
        ob_start();
        try {
            (new SetupController($repo))->setup();
        } catch (SetupRedirectException $e) {
            $redirect = $e->getLocation();
            $status   = $e->getStatusCode();
        }
        $output = (string) ob_get_clean();
        return ['output' => $output, 'redirect' => $redirect, 'status' => $status];
    }

    // =========================================================================
    // 1. GET /setup with an existing admin → opaque 404 (NOT 302 to /login)
    // =========================================================================

    public function testReturns404WhenAdminExists(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->invokeSetup($this->repo(hasAdmin: true));

        $this->assertSame(404, $result['status'], 'Configured instance must serve 404, not redirect.');
        $this->assertNotSame(302, $result['status'], 'A 302 would leak the init state.');
        $this->assertSame('/404', $result['redirect']);
    }

    // =========================================================================
    // 2. POST without csrf_token → form re-rendered with French banner
    // =========================================================================

    public function testRejectsPostWithoutCsrfToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        // Seed a session token so the server side has something to compare
        // against; the POST itself will deliberately omit csrf_token.
        $_SESSION['csrf_token']      = 'server-side-token-abc';
        $_SESSION['csrf_token_time'] = time();
        $_POST = [
            'organisation_name'      => 'Mon Association',
            'admin_name'             => 'Jean Dupont',
            'admin_email'            => 'admin@example.com',
            'admin_password'         => 'Secret123!',
            'admin_password_confirm' => 'Secret123!',
            // no csrf_token
        ];

        $result = $this->invokeSetup($this->repo(hasAdmin: false, expectCreate: false));

        $this->assertNull($result['redirect'], 'Must not redirect when CSRF check fails.');
        $this->assertStringContainsString(
            'Jeton de sécurité invalide',
            $result['output'],
            'Form must be re-rendered with the French CSRF error banner.',
        );
    }

    // =========================================================================
    // 3. POST with a token that does not match the session → rejected
    // =========================================================================

    public function testRejectsPostWithInvalidCsrfToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf_token']      = 'server-side-token-abc';
        $_SESSION['csrf_token_time'] = time();
        $_POST = [
            'csrf_token'             => 'attacker-supplied-token-xyz',
            'organisation_name'      => 'Mon Association',
            'admin_name'             => 'Jean Dupont',
            'admin_email'            => 'admin@example.com',
            'admin_password'         => 'Secret123!',
            'admin_password_confirm' => 'Secret123!',
        ];

        $result = $this->invokeSetup($this->repo(hasAdmin: false, expectCreate: false));

        $this->assertNull($result['redirect'], 'Must not redirect when CSRF tokens diverge.');
        $this->assertStringContainsString(
            'Jeton de sécurité invalide',
            $result['output'],
            'Form must be re-rendered with the French CSRF error banner.',
        );
    }

    // =========================================================================
    // 4. POST with a matching CSRF token + valid payload → tenant + admin created
    // =========================================================================

    public function testAcceptsPostWithValidCsrfToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $token = 'matching-token-' . bin2hex(random_bytes(16));
        $_SESSION['csrf_token']      = $token;
        $_SESSION['csrf_token_time'] = time();
        $_POST = [
            'csrf_token'             => $token,
            'organisation_name'      => 'Mon Association',
            'admin_name'             => 'Jean Dupont',
            'admin_email'            => 'admin@example.com',
            'admin_password'         => 'Secret123!',
            'admin_password_confirm' => 'Secret123!',
        ];

        $result = $this->invokeSetup($this->repo(hasAdmin: false, expectCreate: true));

        $this->assertNotNull($result['redirect'], 'Valid setup must redirect on success.');
        $this->assertStringContainsString('/login', $result['redirect']);
        $this->assertStringContainsString('setup=ok', $result['redirect']);
    }
}
