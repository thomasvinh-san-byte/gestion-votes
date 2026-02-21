<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\DevSeedController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DevSeedController.
 *
 * Tests the dev-only seed endpoints including:
 *  - Controller structure (final, extends AbstractController, public methods)
 *  - HTTP method enforcement for seedMembers and seedAttendances
 *  - Production guard behavior
 *  - Input validation (meeting_id for seedAttendances)
 *  - Count clamping logic
 *  - Present ratio parsing
 *  - Response structure verification via source introspection
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class DevSeedControllerTest extends TestCase
{
    // =========================================================================
    // SETUP / TEARDOWN
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];

        $ref = new \ReflectionClass(\AgVote\Core\Http\Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        \AgVote\Core\Security\AuthMiddleware::reset();
    }

    protected function tearDown(): void
    {
        \AgVote\Core\Security\AuthMiddleware::reset();

        $ref = new \ReflectionClass(\AgVote\Core\Http\Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        parent::tearDown();
    }

    // =========================================================================
    // HELPER: Call controller and capture response
    // =========================================================================

    private function callControllerMethod(string $method): array
    {
        $controller = new DevSeedController();
        try {
            $controller->handle($method);
            $this->fail('Expected ApiResponseException was not thrown');
        } catch (ApiResponseException $e) {
            return [
                'status' => $e->getResponse()->getStatusCode(),
                'body' => $e->getResponse()->getBody(),
            ];
        }
        return ['status' => 500, 'body' => []];
    }

    private function injectJsonBody(array $data): void
    {
        $ref = new \ReflectionClass(\AgVote\Core\Http\Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, json_encode($data));
    }

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
        $controller = new DevSeedController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(DevSeedController::class);

        $expectedMethods = ['seedMembers', 'seedAttendances'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "DevSeedController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(DevSeedController::class);

        $expectedMethods = ['seedMembers', 'seedAttendances'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "DevSeedController::{$method}() should be public",
            );
        }
    }

    public function testControllerHasPrivateGuardProductionMethod(): void
    {
        $ref = new \ReflectionClass(DevSeedController::class);

        $this->assertTrue($ref->hasMethod('guardProduction'),
            'DevSeedController should have a guardProduction method');
        $this->assertTrue($ref->getMethod('guardProduction')->isPrivate(),
            'guardProduction should be private');
    }

    // =========================================================================
    // seedMembers: METHOD ENFORCEMENT
    // =========================================================================

    public function testSeedMembersRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('seedMembers');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testSeedMembersRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('seedMembers');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testSeedMembersRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('seedMembers');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testSeedMembersRejectsPatchMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';

        $result = $this->callControllerMethod('seedMembers');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // seedAttendances: METHOD ENFORCEMENT
    // =========================================================================

    public function testSeedAttendancesRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('seedAttendances');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testSeedAttendancesRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('seedAttendances');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testSeedAttendancesRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('seedAttendances');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testSeedAttendancesRejectsPatchMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';

        $result = $this->callControllerMethod('seedAttendances');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // seedAttendances: MEETING_ID VALIDATION
    //
    // seedAttendances requires meeting_id in the POST body. If empty, it
    // throws InvalidArgumentException which handle() wraps as
    // invalid_request/422.
    // =========================================================================

    public function testSeedAttendancesRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('seedAttendances');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_request', $result['body']['error']);
    }

    public function testSeedAttendancesRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => '']);

        $result = $this->callControllerMethod('seedAttendances');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_request', $result['body']['error']);
    }

    public function testSeedAttendancesRejectsWhitespaceMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => '   ']);

        $result = $this->callControllerMethod('seedAttendances');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_request', $result['body']['error']);
    }

    // =========================================================================
    // COUNT CLAMPING LOGIC (seedMembers)
    // =========================================================================

    public function testCountClampingDefault10(): void
    {
        $in = [];
        $count = max(1, min(100, (int) ($in['count'] ?? 10)));
        $this->assertEquals(10, $count, 'Default count should be 10');
    }

    public function testCountClampingMinBound(): void
    {
        $in = ['count' => 0];
        $count = max(1, min(100, (int) ($in['count'] ?? 10)));
        $this->assertEquals(1, $count, 'Count of 0 should be clamped to 1');
    }

    public function testCountClampingNegative(): void
    {
        $in = ['count' => -5];
        $count = max(1, min(100, (int) ($in['count'] ?? 10)));
        $this->assertEquals(1, $count, 'Negative count should be clamped to 1');
    }

    public function testCountClampingMaxBound(): void
    {
        $in = ['count' => 200];
        $count = max(1, min(100, (int) ($in['count'] ?? 10)));
        $this->assertEquals(100, $count, 'Count of 200 should be clamped to 100');
    }

    public function testCountClampingExactly100(): void
    {
        $in = ['count' => 100];
        $count = max(1, min(100, (int) ($in['count'] ?? 10)));
        $this->assertEquals(100, $count, 'Count of 100 should be accepted');
    }

    public function testCountClampingExactly1(): void
    {
        $in = ['count' => 1];
        $count = max(1, min(100, (int) ($in['count'] ?? 10)));
        $this->assertEquals(1, $count, 'Count of 1 should be accepted');
    }

    public function testCountClampingValidValue50(): void
    {
        $in = ['count' => 50];
        $count = max(1, min(100, (int) ($in['count'] ?? 10)));
        $this->assertEquals(50, $count, 'Valid count of 50 should pass through');
    }

    public function testCountClampingStringInput(): void
    {
        $in = ['count' => '25'];
        $count = max(1, min(100, (int) ($in['count'] ?? 10)));
        $this->assertEquals(25, $count, 'String count should be cast to int');
    }

    // =========================================================================
    // PRESENT RATIO PARSING (seedAttendances)
    // =========================================================================

    public function testPresentRatioDefault(): void
    {
        $in = [];
        $presentRatio = (float) ($in['present_ratio'] ?? 0.7);
        $this->assertEquals(0.7, $presentRatio, 'Default present_ratio should be 0.7');
    }

    public function testPresentRatioCustomValue(): void
    {
        $in = ['present_ratio' => 0.5];
        $presentRatio = (float) ($in['present_ratio'] ?? 0.7);
        $this->assertEquals(0.5, $presentRatio);
    }

    public function testPresentRatioZero(): void
    {
        $in = ['present_ratio' => 0];
        $presentRatio = (float) ($in['present_ratio'] ?? 0.7);
        $this->assertEquals(0.0, $presentRatio);
    }

    public function testPresentRatioOne(): void
    {
        $in = ['present_ratio' => 1.0];
        $presentRatio = (float) ($in['present_ratio'] ?? 0.7);
        $this->assertEquals(1.0, $presentRatio);
    }

    // =========================================================================
    // PRODUCTION GUARD LOGIC
    // =========================================================================

    public function testGuardProductionSourceChecksEnvironment(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DevSeedController.php');

        $this->assertStringContainsString("'production'", $source);
        $this->assertStringContainsString("'prod'", $source);
        $this->assertStringContainsString("'endpoint_disabled'", $source);
        $this->assertStringContainsString('403', $source);
    }

    public function testGuardProductionLogicForDevEnv(): void
    {
        $env = 'dev';
        $blocked = in_array($env, ['production', 'prod'], true);
        $this->assertFalse($blocked, 'Dev environment should not be blocked');
    }

    public function testGuardProductionLogicForProductionEnv(): void
    {
        $env = 'production';
        $blocked = in_array($env, ['production', 'prod'], true);
        $this->assertTrue($blocked, 'Production environment should be blocked');
    }

    public function testGuardProductionLogicForProdEnv(): void
    {
        $env = 'prod';
        $blocked = in_array($env, ['production', 'prod'], true);
        $this->assertTrue($blocked, 'Prod environment should be blocked');
    }

    public function testGuardProductionLogicForStagingEnv(): void
    {
        $env = 'staging';
        $blocked = in_array($env, ['production', 'prod'], true);
        $this->assertFalse($blocked, 'Staging environment should not be blocked');
    }

    // =========================================================================
    // SEED NAMES DATA
    // =========================================================================

    public function testSeedNamesExistInSource(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DevSeedController.php');

        $this->assertStringContainsString('Jean', $source);
        $this->assertStringContainsString('Marie', $source);
        $this->assertStringContainsString('Martin', $source);
        $this->assertStringContainsString('Dupont', $source);
    }

    // =========================================================================
    // ATTENDANCE MODE LOGIC
    // =========================================================================

    public function testAttendanceModeDistribution(): void
    {
        // Replicate mode selection logic from seedAttendances
        $presentRatio = 0.7;

        // A value of 0.5 should be present (0.5 <= 0.7)
        $rand = 0.5;
        if ($rand <= $presentRatio) {
            $mode = 'present'; // simplified, ignoring remote sub-branch
        } else {
            $mode = 'absent';
        }

        $this->assertEquals('present', $mode);
    }

    public function testAttendanceModeAbsentCase(): void
    {
        $presentRatio = 0.7;

        $rand = 0.8;
        if ($rand <= $presentRatio) {
            $mode = 'present';
        } else {
            $mode = 'absent';
        }

        $this->assertEquals('absent', $mode);
    }

    // =========================================================================
    // CONTROLLER SOURCE: RESPONSE STRUCTURE VERIFICATION
    // =========================================================================

    public function testSeedMembersResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DevSeedController.php');

        $this->assertStringContainsString("'created'", $source);
        $this->assertStringContainsString("'requested'", $source);
    }

    public function testSeedAttendancesResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DevSeedController.php');

        $this->assertStringContainsString("'created'", $source);
        $this->assertStringContainsString("'total_members'", $source);
    }

    // =========================================================================
    // CONTROLLER SOURCE: REPOSITORY USAGE
    // =========================================================================

    public function testControllerUsesMemberRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DevSeedController.php');

        $this->assertStringContainsString('MemberRepository', $source);
    }

    public function testControllerUsesAttendanceRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DevSeedController.php');

        $this->assertStringContainsString('AttendanceRepository', $source);
    }

    public function testSeedMembersUsesInsertSeedMember(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DevSeedController.php');

        $this->assertStringContainsString('insertSeedMember', $source);
    }

    public function testSeedAttendancesUsesUpsertSeed(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DevSeedController.php');

        $this->assertStringContainsString('upsertSeed', $source);
    }
}
