<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AccountController;
use AgVote\Controller\AccountRedirectException;
use AgVote\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AccountController.
 *
 * AccountController is a standalone HTML controller (not extending AbstractController).
 * It uses output buffering to capture HTML output.
 *
 * Test coverage:
 *  1. GET /account with no session → redirect to /login?redirect=/account
 *  2. GET /account with valid session → renders account_form with user name, email, role
 *  3. POST /account with wrong current password → error "Le mot de passe actuel est incorrect"
 *  4. POST /account with new password < 8 chars → error "Le mot de passe doit contenir au moins 8 caracteres"
 *  5. POST /account with non-matching passwords → error "Les mots de passe ne correspondent pas"
 *  6. POST /account with correct current password + valid new password → calls setPasswordHash + shows success
 */
class AccountControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_GET    = [];
        $_POST   = [];
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $_GET     = [];
        $_POST    = [];
        $_SESSION = [];
        parent::tearDown();
    }

    // =========================================================================
    // HELPER
    // =========================================================================

    private function fakeUser(): array
    {
        return [
            'id'        => 'u-001',
            'tenant_id' => 't-001',
            'email'     => 'alice@example.com',
            'name'      => 'Alice Durand',
            'role'      => 'operator',
            'is_active' => true,
        ];
    }

    private function fakeDbUser(string $passwordHash = ''): array
    {
        $user = $this->fakeUser();
        $user['password_hash'] = $passwordHash ?: password_hash('OldPassword1!', PASSWORD_DEFAULT);
        return $user;
    }

    /**
     * Run the controller and capture output + redirect.
     *
     * @return array{output: string, redirect: string|null, status: int|null}
     */
    private function invoke(
        UserRepository $repo,
        string $method = 'GET',
        array $session = [],
        array $post = []
    ): array {
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SESSION = $session;
        if ($post !== []) {
            $_POST = $post;
        }

        $redirect = null;
        $status   = null;
        ob_start();
        try {
            $controller = new AccountController($repo);
            $controller->account();
        } catch (AccountRedirectException $e) {
            $redirect = $e->getLocation();
            $status   = $e->getStatusCode();
        }
        $output = (string) ob_get_clean();

        return ['output' => $output, 'redirect' => $redirect, 'status' => $status];
    }

    // =========================================================================
    // Test 1: GET /account with no session → redirect to /login?redirect=/account
    // =========================================================================

    public function testGetNoSessionRedirectsToLogin(): void
    {
        $repo   = $this->createMock(UserRepository::class);
        $result = $this->invoke($repo, 'GET', []);

        $this->assertNotNull($result['redirect'], 'Expected a redirect when not authenticated');
        $this->assertStringContainsString('/login', $result['redirect']);
        $this->assertStringContainsString('/account', $result['redirect']);
        $this->assertSame(302, $result['status']);
    }

    // =========================================================================
    // Test 2: GET /account with valid session → renders account form with user info
    // =========================================================================

    public function testGetWithSessionRendersAccountForm(): void
    {
        $repo   = $this->createMock(UserRepository::class);
        $result = $this->invoke($repo, 'GET', ['auth_user' => $this->fakeUser()]);

        $this->assertNull($result['redirect'], 'Should not redirect when authenticated');
        $this->assertStringContainsString('Alice Durand', $result['output']);
        $this->assertStringContainsString('alice@example.com', $result['output']);
        $this->assertStringContainsString('current_password', $result['output']);
        $this->assertStringContainsString('Mon Compte', $result['output']);
    }

    // =========================================================================
    // Test 3: POST /account with wrong current password → error message
    // =========================================================================

    public function testPostWrongCurrentPasswordShowsError(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findByEmailGlobal')
             ->willReturn($this->fakeDbUser(password_hash('OldPassword1!', PASSWORD_DEFAULT)));

        $result = $this->invoke(
            $repo,
            'POST',
            ['auth_user' => $this->fakeUser()],
            [
                'current_password'     => 'WrongPassword!',
                'new_password'         => 'NewPassword1!',
                'new_password_confirm' => 'NewPassword1!',
            ]
        );

        $this->assertNull($result['redirect'], 'Should not redirect on wrong password');
        $this->assertStringContainsString('mot de passe actuel est incorrect', $result['output']);
    }

    // =========================================================================
    // Test 4: POST /account with new password < 8 chars → validation error
    // =========================================================================

    public function testPostShortNewPasswordShowsValidationError(): void
    {
        $repo   = $this->createMock(UserRepository::class);
        $result = $this->invoke(
            $repo,
            'POST',
            ['auth_user' => $this->fakeUser()],
            [
                'current_password'     => 'OldPassword1!',
                'new_password'         => 'short',
                'new_password_confirm' => 'short',
            ]
        );

        $this->assertNull($result['redirect'], 'Should not redirect on validation failure');
        $this->assertStringContainsString('8 caract', $result['output']);
    }

    // =========================================================================
    // Test 5: POST /account with non-matching passwords → validation error
    // =========================================================================

    public function testPostMismatchedPasswordsShowsValidationError(): void
    {
        $repo   = $this->createMock(UserRepository::class);
        $result = $this->invoke(
            $repo,
            'POST',
            ['auth_user' => $this->fakeUser()],
            [
                'current_password'     => 'OldPassword1!',
                'new_password'         => 'NewPassword1!',
                'new_password_confirm' => 'DifferentPassword!',
            ]
        );

        $this->assertNull($result['redirect'], 'Should not redirect on password mismatch');
        $this->assertStringContainsString('ne correspondent pas', $result['output']);
    }

    // =========================================================================
    // Test 6: POST /account with correct current password + valid new → setPasswordHash called + success shown
    // =========================================================================

    public function testPostValidPasswordChangeCallsSetPasswordHashAndShowsSuccess(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findByEmailGlobal')
             ->willReturn($this->fakeDbUser(password_hash('OldPassword1!', PASSWORD_DEFAULT)));
        $repo->expects($this->once())
             ->method('setPasswordHash')
             ->with('t-001', 'u-001', $this->isType('string'));

        $result = $this->invoke(
            $repo,
            'POST',
            ['auth_user' => $this->fakeUser()],
            [
                'current_password'     => 'OldPassword1!',
                'new_password'         => 'NewPassword1!',
                'new_password_confirm' => 'NewPassword1!',
            ]
        );

        $this->assertNull($result['redirect'], 'Should not redirect on success');
        $this->assertStringContainsString('succes', $result['output']);
    }
}
