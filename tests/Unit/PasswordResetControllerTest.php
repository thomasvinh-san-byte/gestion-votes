<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\PasswordResetController;
use AgVote\Service\PasswordResetService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PasswordResetController.
 *
 * Strategy:
 *  - Mock PasswordResetService
 *  - Use output buffering to capture HtmlView output
 *  - Verify correct templates are rendered per HTTP scenario
 *
 * Test scenarios:
 *  1. GET /reset-password (no token) → renders request form
 *  2. POST /reset-password (email submit) → calls requestReset, shows success
 *  3. GET /reset-password?token=VALID → renders new password form
 *  4. GET /reset-password?token=INVALID → renders request form with error
 *  5. POST /reset-password (with valid token + matching passwords) → renders success
 */
class PasswordResetControllerTest extends TestCase
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
    // HELPER
    // =========================================================================

    /**
     * Run the controller and capture output.
     *
     * @return array{output: string}
     */
    private function invoke(PasswordResetService $service, string $method = 'GET', array $get = [], array $post = []): array
    {
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_GET  = $get;
        $_POST = $post;

        ob_start();
        $controller = new PasswordResetController($service);
        $controller->resetPassword();
        $output = (string) ob_get_clean();

        return ['output' => $output];
    }

    // =========================================================================
    // Test 1: GET /reset-password (no token) → renders request form
    // =========================================================================

    public function testGetNoTokenRendersRequestForm(): void
    {
        $service = $this->createMock(PasswordResetService::class);
        $result  = $this->invoke($service, 'GET');

        $this->assertStringContainsString('Mot de passe oublie', $result['output']);
        $this->assertStringContainsString('email', $result['output']);
    }

    // =========================================================================
    // Test 2: POST /reset-password (email submit) → calls requestReset, shows success
    // =========================================================================

    public function testPostEmailCallsRequestResetAndShowsSuccess(): void
    {
        $service = $this->createMock(PasswordResetService::class);
        $service->expects($this->once())
            ->method('requestReset')
            ->with('user@example.com');

        $result = $this->invoke($service, 'POST', [], ['email' => 'user@example.com']);

        $this->assertStringContainsString('Si cette adresse', $result['output']);
    }

    // =========================================================================
    // Test 3: GET /reset-password?token=VALID → renders new password form
    // =========================================================================

    public function testGetValidTokenRendersNewPasswordForm(): void
    {
        $service = $this->createMock(PasswordResetService::class);
        $service->method('validateToken')->willReturn([
            'id'        => 'r1',
            'user_id'   => 'u1',
            'tenant_id' => 't1',
        ]);

        $result = $this->invoke($service, 'GET', ['token' => 'somevalidtoken']);

        $this->assertStringContainsString('Nouveau mot de passe', $result['output']);
        $this->assertStringContainsString('password', $result['output']);
    }

    // =========================================================================
    // Test 4: GET /reset-password?token=INVALID → renders request form with error
    // =========================================================================

    public function testGetInvalidTokenRendersRequestFormWithError(): void
    {
        $service = $this->createMock(PasswordResetService::class);
        $service->method('validateToken')->willReturn(null);

        $result = $this->invoke($service, 'GET', ['token' => 'expiredtoken']);

        $this->assertStringContainsString('invalide ou a expire', $result['output']);
    }

    // =========================================================================
    // Test 5: POST /reset-password (valid token + matching passwords) → success
    // =========================================================================

    public function testPostValidTokenAndPasswordsRendersSuccess(): void
    {
        $service = $this->createMock(PasswordResetService::class);
        $service->expects($this->once())
            ->method('resetPassword')
            ->with('validtoken', 'NewPassword1!')
            ->willReturn(['ok' => true, 'error' => null]);

        $result = $this->invoke($service, 'POST', [], [
            'token'            => 'validtoken',
            'password'         => 'NewPassword1!',
            'password_confirm' => 'NewPassword1!',
        ]);

        $this->assertStringContainsString('mis a jour avec succes', $result['output']);
    }
}
