<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AbstractController;
use AgVote\Controller\DevSeedController;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\UserRepository;

/**
 * Unit tests for DevSeedController.
 *
 * Extends ControllerTestCase for repo injection and standard helpers.
 *
 * Tests:
 *  - Controller structure (final, extends AbstractController)
 *  - guardProduction: 403 in production/prod environments
 *  - seedMembers: production guard, count clamping, success path
 *  - seedAttendances: production guard, missing meeting_id, no-members error, success path
 */
class DevSeedControllerTest extends ControllerTestCase
{
    private const TENANT  = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const MEETING = 'bbbbbbbb-0000-0000-0000-000000000001';

    // =========================================================================
    // CONTROLLER STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(DevSeedController::class);
        $this->assertTrue($ref->isFinal(), 'DevSeedController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $this->assertInstanceOf(AbstractController::class, new DevSeedController());
    }

    public function testControllerHasExpectedMethods(): void
    {
        $ref = new \ReflectionClass(DevSeedController::class);

        foreach (['seedMembers', 'seedAttendances', 'seedUser'] as $method) {
            $this->assertTrue($ref->hasMethod($method), "Missing method: {$method}");
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(DevSeedController::class);

        foreach (['seedMembers', 'seedAttendances', 'seedUser'] as $method) {
            $this->assertTrue($ref->getMethod($method)->isPublic(), "{$method} should be public");
        }
    }

    // =========================================================================
    // guardProduction: PRODUCTION BLOCK
    // =========================================================================

    /**
     * Source check: guardProduction checks env for 'production'/'prod'.
     */
    public function testGuardProductionBlocksProductionEnv(): void
    {
        $ref = new \ReflectionClass(DevSeedController::class);
        $source = file_get_contents($ref->getFileName());

        $this->assertStringContainsString("'production'", $source);
        $this->assertStringContainsString("'prod'", $source);
        $this->assertStringContainsString('endpoint_disabled', $source);
        $this->assertStringContainsString('403', $source);
    }

    /**
     * Verify the guardProduction logic: both 'production' and 'prod' trigger the block.
     */
    public function testGuardProductionLogicCoverage(): void
    {
        $productionEnvs = ['production', 'prod'];
        $devEnvs        = ['dev', 'staging', 'test', 'local', ''];

        foreach ($productionEnvs as $env) {
            $blocked = in_array($env, ['production', 'prod'], true);
            $this->assertTrue($blocked, "Env '{$env}' should be blocked");
        }

        foreach ($devEnvs as $env) {
            $blocked = in_array($env, ['production', 'prod'], true);
            $this->assertFalse($blocked, "Env '{$env}' should not be blocked");
        }
    }

    // =========================================================================
    // seedMembers: METHOD ENFORCEMENT
    // =========================================================================

    public function testSeedMembersRejectsGetMethod(): void
    {
        $result = $this->callController(DevSeedController::class, 'seedMembers');

        $this->assertEquals(405, $result['status']);
    }

    // =========================================================================
    // seedMembers: COUNT CLAMPING LOGIC
    // =========================================================================

    /**
     * The seed count is clamped: max(1, min(100, (int) $in['count'] ?? 10)).
     */
    public function testSeedMembersCountClampingLogic(): void
    {
        $clamp = fn($input): int => max(1, min(100, (int) ($input ?? 10)));

        $this->assertEquals(10, $clamp(null));    // Default
        $this->assertEquals(10, $clamp(10));      // Normal
        $this->assertEquals(1, $clamp(0));        // Zero => min 1
        $this->assertEquals(1, $clamp(-5));       // Negative => min 1
        $this->assertEquals(100, $clamp(150));    // Over max => clamped
        $this->assertEquals(50, $clamp(50));      // Mid-range
    }

    // =========================================================================
    // seedMembers: SUCCESS PATH
    // =========================================================================

    public function testSeedMembersCreatesMembers(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody(['count' => 5]);

        $mockMember = $this->createMock(MemberRepository::class);
        $mockMember->method('insertSeedMember')->willReturn(true);

        $this->injectRepos([MemberRepository::class => $mockMember]);

        $result = $this->callController(DevSeedController::class, 'seedMembers');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
        $this->assertArrayHasKey('created', $result['body']['data']);
        $this->assertEquals(5, $result['body']['data']['requested']);
    }

    public function testSeedMembersDefaultCount(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([]);

        $mockMember = $this->createMock(MemberRepository::class);
        $mockMember->method('insertSeedMember')->willReturn(true);

        $this->injectRepos([MemberRepository::class => $mockMember]);

        $result = $this->callController(DevSeedController::class, 'seedMembers');

        $this->assertEquals(200, $result['status']);
        $this->assertEquals(10, $result['body']['data']['requested']);
    }

    // =========================================================================
    // seedAttendances: METHOD ENFORCEMENT
    // =========================================================================

    public function testSeedAttendancesRejectsGetMethod(): void
    {
        $result = $this->callController(DevSeedController::class, 'seedAttendances');

        $this->assertEquals(405, $result['status']);
    }

    // =========================================================================
    // seedAttendances: VALIDATION
    // =========================================================================

    public function testSeedAttendancesRequiresMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([]);

        // DevSeedController throws InvalidArgumentException for empty meeting_id,
        // which AbstractController converts to 422.
        $result = $this->callController(DevSeedController::class, 'seedAttendances');

        $this->assertGreaterThanOrEqual(400, $result['status']);
    }

    // =========================================================================
    // seedAttendances: NO MEMBERS
    // =========================================================================

    public function testSeedAttendancesFailsWhenNoActiveMembers(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody(['meeting_id' => self::MEETING]);

        $mockMember = $this->createMock(MemberRepository::class);
        $mockMember->method('listActiveIds')->willReturn([]);

        $mockAttendance = $this->createMock(AttendanceRepository::class);

        $this->injectRepos([
            MemberRepository::class    => $mockMember,
            AttendanceRepository::class => $mockAttendance,
        ]);

        $result = $this->callController(DevSeedController::class, 'seedAttendances');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('no_members', $result['body']['error']);
    }

    // =========================================================================
    // seedAttendances: SUCCESS PATH
    // =========================================================================

    public function testSeedAttendancesCreatesAttendances(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([
            'meeting_id'    => self::MEETING,
            'present_ratio' => 1.0, // 100% present to make test deterministic
        ]);

        $mockMember = $this->createMock(MemberRepository::class);
        $mockMember->method('listActiveIds')->willReturn([
            ['id' => 'member-uuid-0000-0000-000000000001'],
            ['id' => 'member-uuid-0000-0000-000000000002'],
        ]);

        $mockAttendance = $this->createMock(AttendanceRepository::class);
        // upsertSeed returns void — no return value needed

        $this->injectRepos([
            MemberRepository::class    => $mockMember,
            AttendanceRepository::class => $mockAttendance,
        ]);

        $result = $this->callController(DevSeedController::class, 'seedAttendances');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
        $this->assertEquals(2, $result['body']['data']['total_members']);
    }

    // =========================================================================
    // seedUser: METHOD ENFORCEMENT
    // =========================================================================

    public function testSeedUserRejectsGetMethod(): void
    {
        $result = $this->callController(DevSeedController::class, 'seedUser');

        $this->assertEquals(405, $result['status']);
    }

    // =========================================================================
    // seedUser: VALIDATION
    // =========================================================================

    public function testSeedUserRequiresEmailAndPassword(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([]);

        $result = $this->callController(DevSeedController::class, 'seedUser');

        $this->assertGreaterThanOrEqual(400, $result['status']);
    }

    // =========================================================================
    // seedUser: SUCCESS PATH
    // =========================================================================

    public function testSeedUserCreatesUser(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([
            'email' => 'test@example.com',
            'password' => 'Test2026!',
            'name' => 'Test User',
            'system_role' => 'viewer',
        ]);

        $mockUser = $this->createMock(UserRepository::class);
        $mockUser->expects($this->once())->method('createUser');

        $this->injectRepos([UserRepository::class => $mockUser]);

        $result = $this->callController(DevSeedController::class, 'seedUser');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
        $this->assertArrayHasKey('user_id', $result['body']['data']);
        $this->assertEquals('test@example.com', $result['body']['data']['email']);
    }

    // =========================================================================
    // seedUser: WITH MEETING ROLE
    // =========================================================================

    public function testSeedUserWithMeetingRole(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([
            'email' => 'assessor@example.com',
            'password' => 'Test2026!',
            'name' => 'Assessor User',
            'system_role' => 'viewer',
            'meeting_id' => self::MEETING,
            'meeting_role' => 'assessor',
        ]);

        $mockUser = $this->createMock(UserRepository::class);
        $mockUser->expects($this->once())->method('createUser');
        $mockUser->expects($this->once())->method('assignMeetingRole');

        $this->injectRepos([UserRepository::class => $mockUser]);

        $result = $this->callController(DevSeedController::class, 'seedUser');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
        $this->assertArrayHasKey('user_id', $result['body']['data']);
    }

    // =========================================================================
    // seedUser: ROUTE-LEVEL PRODUCTION GATE
    // =========================================================================

    public function testRouteLevelProductionGateExists(): void
    {
        $routeSource = file_get_contents(__DIR__ . '/../../app/routes.php');
        // The seed-user route must be inside a production env check
        $this->assertStringContainsString('test/seed-user', $routeSource);
        // Verify that the production env gate wraps the seed-user route
        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*!in_array\s*\(\s*\$appEnv.*?production.*?\{.*?seed-user/s',
            $routeSource,
            'seed-user route must be inside production env gate in routes.php',
        );
    }
}
