<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Repository\EmailQueueRepository;
use AgVote\Repository\PasswordResetRepository;
use AgVote\Repository\UserRepository;
use AgVote\Service\PasswordResetService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PasswordResetService.
 *
 * Strategy:
 *  - Mock all three repositories via createMock()
 *  - Inject mocks via constructor (optional params)
 *  - Verify token generation, silent enumeration protection, and password update flow
 */
class PasswordResetServiceTest extends TestCase
{
    // =========================================================================
    // HELPERS
    // =========================================================================

    /** @return array{id: string, tenant_id: string, email: string, name: string, is_active: bool, password_hash: string} */
    private function validUser(): array
    {
        return [
            'id'            => 'u1',
            'tenant_id'     => 't1',
            'email'         => 'test@example.com',
            'name'          => 'Test User',
            'is_active'     => true,
            'password_hash' => '$2y$10$xxx',
        ];
    }

    private function makeService(
        PasswordResetRepository $resetRepo,
        UserRepository $userRepo,
        EmailQueueRepository $emailQueueRepo,
    ): PasswordResetService {
        return new PasswordResetService($resetRepo, $userRepo, $emailQueueRepo);
    }

    // =========================================================================
    // requestReset — valid email
    // =========================================================================

    public function testRequestResetWithValidEmailCallsInsertAndEnqueue(): void
    {
        $resetRepo      = $this->createMock(PasswordResetRepository::class);
        $userRepo       = $this->createMock(UserRepository::class);
        $emailQueueRepo = $this->createMock(EmailQueueRepository::class);

        $userRepo->method('findByEmailGlobal')->with('test@example.com')->willReturn($this->validUser());

        $resetRepo->expects($this->once())->method('insert');
        $emailQueueRepo->expects($this->once())
            ->method('enqueue')
            ->with('t1', 'test@example.com', $this->anything(), $this->anything());

        $service = $this->makeService($resetRepo, $userRepo, $emailQueueRepo);
        $service->requestReset('test@example.com');
    }

    // =========================================================================
    // requestReset — unknown email: silent, no insert or enqueue
    // =========================================================================

    public function testRequestResetWithUnknownEmailIsSilent(): void
    {
        $resetRepo      = $this->createMock(PasswordResetRepository::class);
        $userRepo       = $this->createMock(UserRepository::class);
        $emailQueueRepo = $this->createMock(EmailQueueRepository::class);

        $userRepo->method('findByEmailGlobal')->willReturn(null);

        $resetRepo->expects($this->never())->method('insert');
        $emailQueueRepo->expects($this->never())->method('enqueue');

        $service = $this->makeService($resetRepo, $userRepo, $emailQueueRepo);
        $service->requestReset('nobody@example.com');
    }

    // =========================================================================
    // requestReset — inactive user: silent, no insert or enqueue
    // =========================================================================

    public function testRequestResetWithInactiveUserIsSilent(): void
    {
        $resetRepo      = $this->createMock(PasswordResetRepository::class);
        $userRepo       = $this->createMock(UserRepository::class);
        $emailQueueRepo = $this->createMock(EmailQueueRepository::class);

        $inactiveUser = $this->validUser();
        $inactiveUser['is_active'] = false;
        $userRepo->method('findByEmailGlobal')->willReturn($inactiveUser);

        $resetRepo->expects($this->never())->method('insert');
        $emailQueueRepo->expects($this->never())->method('enqueue');

        $service = $this->makeService($resetRepo, $userRepo, $emailQueueRepo);
        $service->requestReset('inactive@example.com');
    }

    // =========================================================================
    // validateToken — valid token: returns row
    // =========================================================================

    public function testValidateTokenWithValidTokenReturnsRow(): void
    {
        $resetRepo      = $this->createMock(PasswordResetRepository::class);
        $userRepo       = $this->createMock(UserRepository::class);
        $emailQueueRepo = $this->createMock(EmailQueueRepository::class);

        $tokenRow = ['id' => 'r1', 'tenant_id' => 't1', 'user_id' => 'u1', 'token_hash' => 'abc'];
        $resetRepo->method('findByHash')->willReturn($tokenRow);

        $service = $this->makeService($resetRepo, $userRepo, $emailQueueRepo);
        $result  = $service->validateToken('raw-token-value');

        $this->assertSame($tokenRow, $result);
    }

    // =========================================================================
    // validateToken — expired/used token: returns null
    // =========================================================================

    public function testValidateTokenWithExpiredTokenReturnsNull(): void
    {
        $resetRepo      = $this->createMock(PasswordResetRepository::class);
        $userRepo       = $this->createMock(UserRepository::class);
        $emailQueueRepo = $this->createMock(EmailQueueRepository::class);

        $resetRepo->method('findByHash')->willReturn(null);

        $service = $this->makeService($resetRepo, $userRepo, $emailQueueRepo);
        $result  = $service->validateToken('expired-or-used-token');

        $this->assertNull($result);
    }

    // =========================================================================
    // resetPassword — valid token: calls setPasswordHash, markUsed, deleteForUser
    // =========================================================================

    public function testResetPasswordWithValidTokenUpdatesPasswordAndInvalidatesToken(): void
    {
        $resetRepo      = $this->createMock(PasswordResetRepository::class);
        $userRepo       = $this->createMock(UserRepository::class);
        $emailQueueRepo = $this->createMock(EmailQueueRepository::class);

        $tokenRow = ['tenant_id' => 't1', 'user_id' => 'u1', 'token_hash' => 'abc123'];
        $resetRepo->method('findByHash')->willReturn($tokenRow);

        $userRepo->expects($this->once())->method('setPasswordHash')
            ->with('t1', 'u1', $this->anything());

        $resetRepo->expects($this->once())->method('markUsed');
        $resetRepo->expects($this->once())->method('deleteForUser')->with('u1');

        $service = $this->makeService($resetRepo, $userRepo, $emailQueueRepo);
        $result  = $service->resetPassword('valid-raw-token', 'NewPassword1!');

        $this->assertSame(['ok' => true, 'error' => null], $result);
    }

    // =========================================================================
    // resetPassword — invalid token: returns error, no password update
    // =========================================================================

    public function testResetPasswordWithInvalidTokenReturnsError(): void
    {
        $resetRepo      = $this->createMock(PasswordResetRepository::class);
        $userRepo       = $this->createMock(UserRepository::class);
        $emailQueueRepo = $this->createMock(EmailQueueRepository::class);

        $resetRepo->method('findByHash')->willReturn(null);

        $userRepo->expects($this->never())->method('setPasswordHash');

        $service = $this->makeService($resetRepo, $userRepo, $emailQueueRepo);
        $result  = $service->resetPassword('invalid-token', 'NewPassword1!');

        $this->assertSame(['ok' => false, 'error' => 'token_invalid'], $result);
    }
}
