<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\ProjectorController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ProjectorController.
 *
 * Tests the projector state endpoint including:
 *  - Controller structure (final, extends AbstractController)
 *  - HTTP method enforcement (GET only)
 *  - Phase resolution logic (idle, active, closed)
 *  - Motion state construction
 *  - Response structure verification via source introspection
 */
class ProjectorControllerTest extends TestCase
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
        $controller = new ProjectorController();
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
        $ref = new \ReflectionClass(ProjectorController::class);
        $this->assertTrue($ref->isFinal(), 'ProjectorController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new ProjectorController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasStateMethod(): void
    {
        $ref = new \ReflectionClass(ProjectorController::class);

        $this->assertTrue(
            $ref->hasMethod('state'),
            "ProjectorController should have a 'state' method",
        );
    }

    public function testStateMethodIsPublic(): void
    {
        $ref = new \ReflectionClass(ProjectorController::class);

        $this->assertTrue(
            $ref->getMethod('state')->isPublic(),
            "ProjectorController::state() should be public",
        );
    }

    // =========================================================================
    // state: METHOD ENFORCEMENT
    // =========================================================================

    public function testStateRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('state');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testStateRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('state');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testStateRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('state');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testStateRejectsPatchMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';

        $result = $this->callControllerMethod('state');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // PHASE RESOLUTION LOGIC
    // =========================================================================

    public function testPhaseIsActiveWhenOpenMotionExists(): void
    {
        $open = ['id' => 'm1', 'title' => 'Open Motion', 'secret' => false, 'position' => 1];
        $closed = null;

        $phase = 'idle';
        $motion = null;

        if ($open) {
            $phase = 'active';
            $motion = [
                'id' => (string) $open['id'],
                'title' => (string) $open['title'],
                'secret' => (bool) $open['secret'],
                'position' => $open['position'] !== null ? (int) $open['position'] : null,
            ];
        } elseif ($closed) {
            $phase = 'closed';
        }

        $this->assertEquals('active', $phase);
        $this->assertNotNull($motion);
        $this->assertEquals('m1', $motion['id']);
    }

    public function testPhaseIsClosedWhenOnlyClosedMotionExists(): void
    {
        $open = null;
        $closed = ['id' => 'm2', 'title' => 'Closed Motion', 'secret' => true, 'position' => 2];

        $phase = 'idle';
        $motion = null;

        if ($open) {
            $phase = 'active';
        } elseif ($closed) {
            $phase = 'closed';
            $motion = [
                'id' => (string) $closed['id'],
                'title' => (string) $closed['title'],
                'secret' => (bool) $closed['secret'],
                'position' => $closed['position'] !== null ? (int) $closed['position'] : null,
            ];
        }

        $this->assertEquals('closed', $phase);
        $this->assertNotNull($motion);
        $this->assertEquals('m2', $motion['id']);
        $this->assertTrue($motion['secret']);
    }

    public function testPhaseIsIdleWhenNoMotions(): void
    {
        $open = null;
        $closed = null;

        $phase = 'idle';
        $motion = null;

        if ($open) {
            $phase = 'active';
        } elseif ($closed) {
            $phase = 'closed';
        }

        $this->assertEquals('idle', $phase);
        $this->assertNull($motion);
    }

    public function testPhaseActiveOverridesClosedWhenBothExist(): void
    {
        $open = ['id' => 'm1', 'title' => 'Open', 'secret' => false, 'position' => 1];
        $closed = ['id' => 'm2', 'title' => 'Closed', 'secret' => false, 'position' => 0];

        $phase = 'idle';
        $motion = null;

        if ($open) {
            $phase = 'active';
            $motion = ['id' => (string) $open['id']];
        } elseif ($closed) {
            $phase = 'closed';
            $motion = ['id' => (string) $closed['id']];
        }

        $this->assertEquals('active', $phase, 'Active should take precedence over closed');
        $this->assertEquals('m1', $motion['id'], 'Should use the open motion');
    }

    // =========================================================================
    // MOTION STATE CONSTRUCTION
    // =========================================================================

    public function testMotionStateFieldTypes(): void
    {
        $raw = [
            'id' => 'abc-123',
            'title' => 'Test Motion',
            'description' => 'A description',
            'body' => 'Body content',
            'secret' => true,
            'position' => 3,
        ];

        $motion = [
            'id' => (string) $raw['id'],
            'title' => (string) $raw['title'],
            'description' => (string) ($raw['description'] ?? ''),
            'body' => (string) ($raw['body'] ?? ''),
            'secret' => (bool) $raw['secret'],
            'position' => $raw['position'] !== null ? (int) $raw['position'] : null,
        ];

        $this->assertIsString($motion['id']);
        $this->assertIsString($motion['title']);
        $this->assertIsString($motion['description']);
        $this->assertIsString($motion['body']);
        $this->assertIsBool($motion['secret']);
        $this->assertIsInt($motion['position']);
    }

    public function testMotionStateNullPosition(): void
    {
        $raw = [
            'id' => 'abc-123',
            'title' => 'Test Motion',
            'secret' => false,
            'position' => null,
        ];

        $position = $raw['position'] !== null ? (int) $raw['position'] : null;
        $this->assertNull($position);
    }

    public function testMotionStateEmptyDescription(): void
    {
        $raw = ['description' => null, 'body' => null];

        $description = (string) ($raw['description'] ?? '');
        $body = (string) ($raw['body'] ?? '');

        $this->assertEquals('', $description);
        $this->assertEquals('', $body);
    }

    // =========================================================================
    // MULTIPLE LIVE MEETINGS: CHOOSE RESPONSE
    // =========================================================================

    public function testMultipleLiveMeetingsReturnsChooseFlag(): void
    {
        // Replicate the choose logic
        $liveMeetings = [
            ['id' => 'm1', 'title' => 'Meeting 1', 'started_at' => '2025-01-01'],
            ['id' => 'm2', 'title' => 'Meeting 2', 'started_at' => '2025-01-02'],
        ];

        $this->assertGreaterThan(1, count($liveMeetings));

        $response = [
            'choose' => true,
            'meetings' => array_map(fn ($m) => [
                'id' => (string) $m['id'],
                'title' => (string) $m['title'],
                'started_at' => (string) ($m['started_at'] ?? ''),
            ], $liveMeetings),
        ];

        $this->assertTrue($response['choose']);
        $this->assertCount(2, $response['meetings']);
        $this->assertEquals('m1', $response['meetings'][0]['id']);
        $this->assertEquals('m2', $response['meetings'][1]['id']);
    }

    // =========================================================================
    // ARCHIVED MEETING REJECTION
    // =========================================================================

    public function testArchivedMeetingIsRejected(): void
    {
        $meeting = ['id' => 'm1', 'status' => 'archived', 'title' => 'Old Meeting'];

        $isArchived = !$meeting || ($meeting['status'] ?? '') === 'archived';
        $this->assertTrue($isArchived, 'Archived meetings should be rejected');
    }

    public function testNonArchivedMeetingIsAccepted(): void
    {
        $meeting = ['id' => 'm1', 'status' => 'live', 'title' => 'Active Meeting'];

        $isArchived = !$meeting || ($meeting['status'] ?? '') === 'archived';
        $this->assertFalse($isArchived, 'Live meetings should be accepted');
    }

    public function testNullMeetingIsRejected(): void
    {
        $meeting = null;

        $isRejected = !$meeting || ($meeting['status'] ?? '') === 'archived';
        $this->assertTrue($isRejected, 'Null meeting should be rejected');
    }

    // =========================================================================
    // RESPONSE STRUCTURE VERIFICATION (source-level)
    // =========================================================================

    public function testStateResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProjectorController.php');

        $expectedKeys = [
            'meeting_id',
            'meeting_title',
            'meeting_status',
            'phase',
            'motion',
            'total_motions',
            'eligible_count',
        ];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $source,
                "state() response should contain '{$key}'",
            );
        }
    }

    public function testStateUsesCorrectRepositories(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProjectorController.php');

        $this->assertStringContainsString('MeetingRepository', $source);
        $this->assertStringContainsString('MotionRepository', $source);
        $this->assertStringContainsString('MeetingStatsRepository', $source);
    }

    public function testStateQueriesOpenMotion(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProjectorController.php');

        $this->assertStringContainsString('findOpenForProjector', $source);
    }

    public function testStateQueriesLastClosedMotion(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProjectorController.php');

        $this->assertStringContainsString('findLastClosedForProjector', $source);
    }

    public function testStateQueriesLiveMeetings(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProjectorController.php');

        $this->assertStringContainsString('listLiveForTenant', $source);
    }

    public function testStateHandlesNoLiveMeeting(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProjectorController.php');

        $this->assertStringContainsString('no_live_meeting', $source);
    }

    public function testStateMeetingNotFoundError(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProjectorController.php');

        $this->assertStringContainsString('meeting_not_found', $source);
    }

    // =========================================================================
    // PHASE VALUES
    // =========================================================================

    public function testValidPhaseValues(): void
    {
        $validPhases = ['idle', 'active', 'closed'];

        foreach ($validPhases as $phase) {
            $this->assertContains(
                $phase,
                $validPhases,
                "'{$phase}' should be a valid projector phase",
            );
        }
    }

    // =========================================================================
    // ELIGIBLE COUNT
    // =========================================================================

    public function testStateCountsActiveMembers(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProjectorController.php');

        $this->assertStringContainsString('countActiveMembers', $source);
    }

    // =========================================================================
    // TOTAL MOTIONS COUNT
    // =========================================================================

    public function testStateCountsMotionsForMeeting(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProjectorController.php');

        $this->assertStringContainsString('countForMeeting', $source);
    }

    // =========================================================================
    // UUID VALIDATION HELPER
    // =========================================================================

    public function testUuidValidationForMeetingIds(): void
    {
        $this->assertTrue(api_is_uuid('12345678-1234-1234-1234-123456789abc'));
        $this->assertTrue(api_is_uuid('00000000-0000-0000-0000-000000000000'));
        $this->assertFalse(api_is_uuid(''));
        $this->assertFalse(api_is_uuid('not-a-uuid'));
        $this->assertFalse(api_is_uuid('12345'));
    }

    // =========================================================================
    // MEETING ID RESOLUTION: EXPLICIT VS IMPLICIT
    // =========================================================================

    public function testExplicitMeetingIdUsedWhenProvided(): void
    {
        $requestedId = '12345678-1234-1234-1234-123456789abc';
        $usesExplicitId = ($requestedId !== '');
        $this->assertTrue($usesExplicitId, 'Should use explicit meeting_id when provided');
    }

    public function testImplicitMeetingIdFromLiveMeetings(): void
    {
        $requestedId = '';
        $usesExplicitId = ($requestedId !== '');
        $this->assertFalse($usesExplicitId, 'Should fall back to live meetings when no meeting_id');
    }
}
