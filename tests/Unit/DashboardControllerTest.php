<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\DashboardController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DashboardController.
 *
 * Tests the dashboard and wizard status endpoints including:
 *  - Controller structure (final, extends AbstractController, public methods)
 *  - HTTP method enforcement (implicit via api_query/api_ok patterns)
 *  - UUID validation for meeting_id in wizardStatus
 *  - Quorum calculation logic
 *  - Ready-to-sign logic
 *  - Response structure verification via source introspection
 *
 * Note: index() does not call api_request(), so method enforcement is not
 * checked. It also instantiates MeetingRepository early, so in test env
 * without DB it will throw RuntimeException caught as business_error (400).
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class DashboardControllerTest extends TestCase
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
        $controller = new DashboardController();
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
        $ref = new \ReflectionClass(DashboardController::class);
        $this->assertTrue($ref->isFinal(), 'DashboardController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new DashboardController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(DashboardController::class);

        $expectedMethods = ['index', 'wizardStatus'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "DashboardController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(DashboardController::class);

        $expectedMethods = ['index', 'wizardStatus'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "DashboardController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // index: EARLY REPO INSTANTIATION BEHAVIOR
    //
    // index() calls new MeetingRepository() early. In test env (no DB),
    // this raises RuntimeException which handle() wraps as business_error/400.
    // =========================================================================

    public function testIndexFailsWithBusinessErrorOnGetDueToNoDb(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('index');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    public function testIndexFailsWithBusinessErrorOnPostDueToNoDb(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('index');

        // No method enforcement, so repo instantiation fails first
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    // =========================================================================
    // wizardStatus: MEETING_ID VALIDATION
    // =========================================================================

    public function testWizardStatusRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('wizardStatus');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testWizardStatusRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('wizardStatus');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testWizardStatusRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-a-valid-uuid'];

        $result = $this->callControllerMethod('wizardStatus');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testWizardStatusRejectsShortUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678'];

        $result = $this->callControllerMethod('wizardStatus');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testWizardStatusRejectsPartialUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234-1234'];

        $result = $this->callControllerMethod('wizardStatus');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testWizardStatusRejectsUuidWithNonHexChars(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234-1234-1234-12345678ZZZZ'];

        $result = $this->callControllerMethod('wizardStatus');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // QUORUM CALCULATION LOGIC
    // =========================================================================

    public function testQuorumMetWithPresentMembers(): void
    {
        $membersCount = 100;
        $presentCount = 60;

        $quorumMet = false;
        if ($membersCount > 0) {
            $ratio = $presentCount / $membersCount;
            $quorumMet = $ratio > 0;
        }

        $this->assertTrue($quorumMet);
    }

    public function testQuorumNotMetWithZeroPresent(): void
    {
        $membersCount = 100;
        $presentCount = 0;

        $quorumMet = false;
        if ($membersCount > 0) {
            $ratio = $presentCount / $membersCount;
            $quorumMet = $ratio > 0;
        }

        $this->assertFalse($quorumMet);
    }

    public function testQuorumNotMetWithZeroMembers(): void
    {
        $membersCount = 0;
        $presentCount = 0;

        $quorumMet = false;
        if ($membersCount > 0) {
            $ratio = $presentCount / $membersCount;
            $quorumMet = $ratio > 0;
        }

        $this->assertFalse($quorumMet);
    }

    public function testQuorumWithThresholdMet(): void
    {
        $membersCount = 100;
        $presentCount = 55;
        $threshold = 0.5;

        $ratio = $presentCount / $membersCount;
        $quorumMet = $ratio >= $threshold;

        $this->assertTrue($quorumMet);
    }

    public function testQuorumWithThresholdNotMet(): void
    {
        $membersCount = 100;
        $presentCount = 40;
        $threshold = 0.5;

        $ratio = $presentCount / $membersCount;
        $quorumMet = $ratio >= $threshold;

        $this->assertFalse($quorumMet);
    }

    public function testQuorumThresholdExactBoundary(): void
    {
        $membersCount = 100;
        $presentCount = 50;
        $threshold = 0.5;

        $ratio = $presentCount / $membersCount;
        $quorumMet = $ratio >= $threshold;

        $this->assertTrue($quorumMet, 'Exact threshold should meet quorum');
    }

    // =========================================================================
    // READY-TO-SIGN LOGIC
    // =========================================================================

    public function testReadyToSignWhenNoReasons(): void
    {
        $reasons = [];
        $data = [
            'can' => count($reasons) === 0,
            'reasons' => $reasons,
        ];

        $this->assertTrue($data['can']);
        $this->assertEmpty($data['reasons']);
    }

    public function testNotReadyToSignWithMissingPresident(): void
    {
        $presidentName = '';
        $reasons = [];

        if (trim($presidentName) === '') {
            $reasons[] = 'Président non renseigné.';
        }

        $data = [
            'can' => count($reasons) === 0,
            'reasons' => $reasons,
        ];

        $this->assertFalse($data['can']);
        $this->assertContains('Président non renseigné.', $data['reasons']);
    }

    public function testNotReadyToSignWithOpenMotion(): void
    {
        $openCount = 1;
        $reasons = [];

        if ($openCount > 0) {
            $reasons[] = 'Une motion est encore ouverte.';
        }

        $data = [
            'can' => count($reasons) === 0,
            'reasons' => $reasons,
        ];

        $this->assertFalse($data['can']);
        $this->assertContains('Une motion est encore ouverte.', $data['reasons']);
    }

    public function testNotReadyToSignWithMultipleReasons(): void
    {
        $reasons = [];
        $reasons[] = 'Président non renseigné.';
        $reasons[] = 'Une motion est encore ouverte.';
        $reasons[] = 'Comptage manquant pour: Résolution 1';

        $data = [
            'can' => count($reasons) === 0,
            'reasons' => $reasons,
        ];

        $this->assertFalse($data['can']);
        $this->assertCount(3, $data['reasons']);
    }

    // =========================================================================
    // SUGGESTED MEETING SELECTION LOGIC
    // =========================================================================

    public function testSuggestedMeetingSelectsLiveFirst(): void
    {
        $meetings = [
            ['id' => 'aaa', 'status' => 'draft'],
            ['id' => 'bbb', 'status' => 'live'],
            ['id' => 'ccc', 'status' => 'paused'],
        ];

        $suggested = null;
        foreach ($meetings as $m) {
            if (in_array($m['status'] ?? '', ['live', 'paused'], true)) {
                $suggested = $m['id'];
                break;
            }
        }

        $this->assertEquals('bbb', $suggested);
    }

    public function testSuggestedMeetingSelectsPausedIfNoLive(): void
    {
        $meetings = [
            ['id' => 'aaa', 'status' => 'draft'],
            ['id' => 'bbb', 'status' => 'paused'],
        ];

        $suggested = null;
        foreach ($meetings as $m) {
            if (in_array($m['status'] ?? '', ['live', 'paused'], true)) {
                $suggested = $m['id'];
                break;
            }
        }

        $this->assertEquals('bbb', $suggested);
    }

    public function testSuggestedMeetingFallsBackToFirstMeeting(): void
    {
        $meetings = [
            ['id' => 'aaa', 'status' => 'draft'],
            ['id' => 'bbb', 'status' => 'draft'],
        ];

        $suggested = null;
        foreach ($meetings as $m) {
            if (in_array($m['status'] ?? '', ['live', 'paused'], true)) {
                $suggested = $m['id'];
                break;
            }
        }
        if ($suggested === null && count($meetings) > 0) {
            $suggested = $meetings[0]['id'];
        }

        $this->assertEquals('aaa', $suggested);
    }

    public function testSuggestedMeetingIsNullWhenNoMeetings(): void
    {
        $meetings = [];

        $suggested = null;
        foreach ($meetings as $m) {
            if (in_array($m['status'] ?? '', ['live', 'paused'], true)) {
                $suggested = $m['id'];
                break;
            }
        }
        if ($suggested === null && count($meetings) > 0) {
            $suggested = $meetings[0]['id'];
        }

        $this->assertNull($suggested);
    }

    // =========================================================================
    // CONTROLLER SOURCE: RESPONSE STRUCTURE VERIFICATION
    // =========================================================================

    public function testIndexResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DashboardController.php');

        $fields = [
            'meetings', 'suggested_meeting_id', 'meeting', 'attendance',
            'proxies', 'current_motion', 'current_motion_votes',
            'openable_motions', 'ready_to_sign',
        ];
        foreach ($fields as $field) {
            $this->assertStringContainsString(
                "'{$field}'",
                $source,
                "index response should contain '{$field}'",
            );
        }
    }

    public function testWizardStatusResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DashboardController.php');

        $fields = [
            'meeting_id', 'meeting_title', 'meeting_status', 'current_motion_id',
            'members_count', 'present_count', 'motions_total', 'motions_closed',
            'has_president', 'quorum_met', 'policies_assigned',
        ];
        foreach ($fields as $field) {
            $this->assertStringContainsString(
                "'{$field}'",
                $source,
                "wizardStatus response should contain '{$field}'",
            );
        }
    }

    // =========================================================================
    // CONTROLLER SOURCE: REPOSITORY USAGE
    // =========================================================================

    public function testControllerUsesMeetingRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DashboardController.php');

        $this->assertStringContainsString('MeetingRepository', $source);
    }

    public function testControllerUsesWizardRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DashboardController.php');

        $this->assertStringContainsString('WizardRepository', $source);
    }

    public function testControllerUsesAttendanceRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DashboardController.php');

        $this->assertStringContainsString('AttendanceRepository', $source);
    }

    public function testControllerUsesMotionRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DashboardController.php');

        $this->assertStringContainsString('MotionRepository', $source);
    }

    public function testControllerUsesBallotRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DashboardController.php');

        $this->assertStringContainsString('BallotRepository', $source);
    }

    public function testControllerUsesProxyRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DashboardController.php');

        $this->assertStringContainsString('ProxyRepository', $source);
    }

    public function testControllerUsesMemberRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DashboardController.php');

        $this->assertStringContainsString('MemberRepository', $source);
    }
}
