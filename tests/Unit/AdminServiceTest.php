<?php
declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Providers\RepositoryFactory;
use AgVote\Repository\AuditEventRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\SettingsRepository;
use AgVote\Repository\SystemRepository;
use AgVote\Repository\UserRepository;
use AgVote\Repository\VoteTokenRepository;
use AgVote\Service\AdminService;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for AdminService.
 *
 * All repository dependencies are mocked via RepositoryFactory; no database connection needed.
 */
class AdminServiceTest extends TestCase {
    private const TENANT = 'aaaaaaaa-1111-2222-3333-444444444444';
    private const USER_ID = 'bbbbbbbb-1111-2222-3333-444444444444';

    private UserRepository&MockObject $userRepo;
    private AdminService $service;

    protected function setUp(): void {
        $factory = new RepositoryFactory(null);
        $ref = new ReflectionClass(RepositoryFactory::class);
        $cacheProp = $ref->getProperty('cache');
        $cacheProp->setAccessible(true);

        $this->userRepo = $this->createMock(UserRepository::class);
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $motionRepo = $this->createMock(MotionRepository::class);
        $memberRepo = $this->createMock(MemberRepository::class);
        $sysRepo = $this->createMock(SystemRepository::class);
        $tokenRepo = $this->createMock(VoteTokenRepository::class);
        $auditRepo = $this->createMock(AuditEventRepository::class);
        $settingsRepo = $this->createMock(SettingsRepository::class);

        $cacheProp->setValue($factory, [
            UserRepository::class => $this->userRepo,
            MeetingRepository::class => $meetingRepo,
            MotionRepository::class => $motionRepo,
            MemberRepository::class => $memberRepo,
            SystemRepository::class => $sysRepo,
            VoteTokenRepository::class => $tokenRepo,
            AuditEventRepository::class => $auditRepo,
            SettingsRepository::class => $settingsRepo,
        ]);

        $this->service = new AdminService($factory);
    }

    // =========================================================================
    // Structural tests
    // =========================================================================

    public function testServiceIsFinal(): void {
        $ref = new ReflectionClass(AdminService::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testConstructorAcceptsNullableRepoFactory(): void {
        $ref = new ReflectionClass(AdminService::class);
        $constructor = $ref->getConstructor();
        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();
        $this->assertCount(1, $params);
        $this->assertTrue($params[0]->allowsNull());
    }

    public function testHasExpectedMethods(): void {
        $ref = new ReflectionClass(AdminService::class);
        foreach (['handleUserAction', 'handleMeetingRole', 'getSystemStatus', 'getAuditLog'] as $method) {
            $this->assertTrue($ref->hasMethod($method), "Missing method: {$method}");
        }
    }

    // =========================================================================
    // handleUserAction
    // =========================================================================

    public function testHandleUserActionThrowsOnInvalidAction(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown_action');
        $this->service->handleUserAction(self::TENANT, 'nonexistent_action', [], self::USER_ID);
    }

    public function testHandleUserActionThrowsOnWeakPassword(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('weak_password');
        $this->service->handleUserAction(self::TENANT, 'set_password', ['user_id' => self::USER_ID, 'password' => 'short'], self::USER_ID);
    }

    public function testHandleUserActionCannotToggleSelf(): void {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot_toggle_self');
        $this->service->handleUserAction(self::TENANT, 'toggle', ['user_id' => self::USER_ID, 'is_active' => 0], self::USER_ID);
    }

    public function testHandleUserActionCannotDeleteSelf(): void {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot_delete_self');
        $this->service->handleUserAction(self::TENANT, 'delete', ['user_id' => self::USER_ID], self::USER_ID);
    }
}
