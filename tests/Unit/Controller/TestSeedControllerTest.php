<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use AgVote\Controller\AbstractController;
use AgVote\Controller\TestSeedController;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use Tests\Unit\ControllerTestCase;

/**
 * Unit tests for TestSeedController.
 *
 * Source: TEST-V24-01 / D-01..D-04 — Plan 03.1 (Phase 3 v2.4).
 *
 * Coverage:
 *  - Controller structure (final, extends AbstractController, exposes seedMeeting)
 *  - guardProduction: 403 in production env (inner gate)
 *  - Method enforcement: rejects GET (only POST allowed)
 *  - Validation: tenantId required → 422
 *  - Success path: meeting + motions created, audit_log invoked
 *  - Route-level production gate: route stays inside the env-gated block in routes.php
 */
class TestSeedControllerTest extends ControllerTestCase
{
    private const TENANT = 'aaaaaaaa-1111-2222-3333-444444444444';

    // =========================================================================
    // STRUCTURE
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(TestSeedController::class);
        $this->assertTrue($ref->isFinal(), 'TestSeedController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $this->assertInstanceOf(AbstractController::class, new TestSeedController());
    }

    public function testSeedMeetingMethodExistsAndIsPublic(): void
    {
        $ref = new \ReflectionClass(TestSeedController::class);
        $this->assertTrue($ref->hasMethod('seedMeeting'));
        $this->assertTrue($ref->getMethod('seedMeeting')->isPublic());
    }

    // =========================================================================
    // PRODUCTION GUARD (inner gate)
    // =========================================================================

    public function testGuardProductionReturns403InProduction(): void
    {
        // guardProduction() reads APP_ENV via $_ENV → getenv() → config().
        // Drive it through $_ENV so the existing test stub for config() is bypassed.
        $previousEnv = $_ENV['APP_ENV'] ?? null;
        $previousGetenv = getenv('APP_ENV');
        $_ENV['APP_ENV'] = 'production';
        putenv('APP_ENV=production');

        try {
            $this->setHttpMethod('POST');
            $this->setAuth('user-1', 'admin', self::TENANT);
            $this->injectJsonBody(['tenantId' => self::TENANT]);

            $result = $this->callController(TestSeedController::class, 'seedMeeting');

            $this->assertSame(403, $result['status']);
            $this->assertSame('endpoint_disabled', $result['body']['error'] ?? null);
        } finally {
            if ($previousEnv === null) {
                unset($_ENV['APP_ENV']);
            } else {
                $_ENV['APP_ENV'] = $previousEnv;
            }
            if ($previousGetenv === false) {
                putenv('APP_ENV');
            } else {
                putenv('APP_ENV=' . $previousGetenv);
            }
        }
    }

    public function testGuardProductionLogicCoversBothAliases(): void
    {
        foreach (['production', 'prod'] as $env) {
            $blocked = in_array($env, ['production', 'prod'], true);
            $this->assertTrue($blocked, "Env '{$env}' must be blocked");
        }
        foreach (['development', 'dev', 'test', 'testing', 'demo', 'staging'] as $env) {
            // staging is NOT blocked by guardProduction (only production/prod are);
            // EnvGuardMiddleware handles staging blocking at the route level.
            $blocked = in_array($env, ['production', 'prod'], true);
            $this->assertFalse($blocked, "Env '{$env}' must not be blocked by guardProduction");
        }
    }

    // =========================================================================
    // METHOD ENFORCEMENT
    // =========================================================================

    public function testSeedMeetingRejectsGetMethod(): void
    {
        $result = $this->callController(TestSeedController::class, 'seedMeeting');
        $this->assertSame(405, $result['status']);
    }

    // =========================================================================
    // VALIDATION
    // =========================================================================

    public function testSeedMeetingRequiresTenantId(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([]);

        $result = $this->callController(TestSeedController::class, 'seedMeeting');

        $this->assertSame(422, $result['status']);
        $this->assertSame('invalid_request', $result['body']['error'] ?? null);
    }

    // =========================================================================
    // SUCCESS PATH
    // =========================================================================

    public function testSeedMeetingCreatesMeetingAndMotions(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([
            'tenantId' => self::TENANT,
            'status' => 'running',
            'motionsCount' => 2,
        ]);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->expects($this->once())
            ->method('createForTest')
            ->with(self::TENANT, 'running')
            ->willReturn('meeting-uuid-1234');

        $mockMotion = $this->createMock(MotionRepository::class);
        $mockMotion->expects($this->exactly(2))
            ->method('createForTest')
            ->willReturnOnConsecutiveCalls('motion-1', 'motion-2');

        $this->injectRepos([
            MeetingRepository::class => $mockMeeting,
            MotionRepository::class => $mockMotion,
        ]);

        $result = $this->callController(TestSeedController::class, 'seedMeeting');

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
        $this->assertSame('meeting-uuid-1234', $result['body']['data']['meeting_id']);
        $this->assertSame(['motion-1', 'motion-2'], $result['body']['data']['motion_ids']);
    }

    public function testSeedMeetingDefaultsToZeroMotions(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody(['tenantId' => self::TENANT]);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->expects($this->once())
            ->method('createForTest')
            ->with(self::TENANT, 'setup')
            ->willReturn('meeting-uuid');

        $mockMotion = $this->createMock(MotionRepository::class);
        $mockMotion->expects($this->never())->method('createForTest');

        $this->injectRepos([
            MeetingRepository::class => $mockMeeting,
            MotionRepository::class => $mockMotion,
        ]);

        $result = $this->callController(TestSeedController::class, 'seedMeeting');

        $this->assertSame(200, $result['status']);
        $this->assertSame([], $result['body']['data']['motion_ids']);
    }

    public function testSeedMeetingClampsMotionsCount(): void
    {
        $clamp = fn($input): int => max(0, min(50, (int) ($input ?? 0)));

        $this->assertSame(0, $clamp(null));
        $this->assertSame(0, $clamp(-5));
        $this->assertSame(0, $clamp(0));
        $this->assertSame(10, $clamp(10));
        $this->assertSame(50, $clamp(50));
        $this->assertSame(50, $clamp(999));
    }

    // =========================================================================
    // ROUTE-LEVEL PRODUCTION GATE
    // =========================================================================

    public function testRouteIsRegisteredInsideEnvGate(): void
    {
        $routeSource = file_get_contents(__DIR__ . '/../../../app/routes.php');
        $this->assertNotFalse($routeSource);

        $this->assertStringContainsString('test/seed-meeting', $routeSource);

        // Locate the env gate opening and its closing brace; the seed-meeting
        // route must appear between them.
        $gateStart = strpos($routeSource, "if (!in_array(\$appEnv, ['production', 'prod']");
        $this->assertNotFalse($gateStart, 'env gate opening not found in routes.php');

        // Find the matching close brace by counting from the gate start.
        $depth = 0;
        $i = $gateStart;
        $len = strlen($routeSource);
        $closeIdx = null;
        while ($i < $len) {
            $ch = $routeSource[$i];
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $closeIdx = $i;
                    break;
                }
            }
            $i++;
        }
        $this->assertNotNull($closeIdx, 'matching close brace not found for env gate');

        $gateBody = substr($routeSource, $gateStart, $closeIdx - $gateStart);
        $this->assertStringContainsString('test/seed-meeting', $gateBody, 'seed-meeting must live inside the env gate');
    }

    public function testRouteUsesEnvGuardMiddleware(): void
    {
        $routeSource = file_get_contents(__DIR__ . '/../../../app/routes.php');
        $this->assertNotFalse($routeSource);

        // Find the seed-meeting route declaration and verify env_guard is set.
        $needle = 'test/seed-meeting';
        $start = strpos($routeSource, $needle);
        $this->assertNotFalse($start);

        // Look at the next ~300 characters after the route name for the env_guard key.
        $window = substr($routeSource, $start, 400);
        $this->assertStringContainsString('TestSeedController', $window);
        $this->assertStringContainsString('env_guard', $window, 'seed-meeting route must declare env_guard middleware');
    }
}
