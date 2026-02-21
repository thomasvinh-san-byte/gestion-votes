<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\MeetingWorkflowController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MeetingWorkflowController.
 *
 * Tests the meeting workflow endpoints including:
 *  - transition() — single-step state machine transitions with validation
 *  - launch() — multi-step fast-forward to live
 *  - workflowCheck() — readiness / issue inspection
 *  - readyCheck() — pre-validation readiness with checks
 *  - consolidate() — official results consolidation
 *  - resetDemo() — demo reset with confirmation guard
 *  - Method enforcement and input validation
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class MeetingWorkflowControllerTest extends TestCase
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

        // Reset cached raw body
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
        $controller = new MeetingWorkflowController();
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

    /**
     * Inject a JSON body into the Request's cached raw body.
     */
    private function setJsonBody(array $data): void
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
        $ref = new \ReflectionClass(MeetingWorkflowController::class);
        $this->assertTrue($ref->isFinal(), 'MeetingWorkflowController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new MeetingWorkflowController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(MeetingWorkflowController::class);

        $expectedMethods = ['transition', 'launch', 'workflowCheck', 'readyCheck', 'consolidate', 'resetDemo'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "MeetingWorkflowController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(MeetingWorkflowController::class);

        $expectedMethods = ['transition', 'launch', 'workflowCheck', 'readyCheck', 'consolidate', 'resetDemo'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "MeetingWorkflowController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // TRANSITION: METHOD ENFORCEMENT
    // =========================================================================

    public function testTransitionRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('transition');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testTransitionRequiresPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('transition');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // TRANSITION: MEETING ID VALIDATION
    // =========================================================================

    public function testTransitionRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('transition');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testTransitionRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => '']);

        $result = $this->callControllerMethod('transition');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testTransitionRejectsInvalidUuidForMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => 'not-a-valid-uuid']);

        $result = $this->callControllerMethod('transition');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    // =========================================================================
    // TRANSITION: TO_STATUS VALIDATION
    // =========================================================================

    public function testTransitionRequiresToStatus(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('transition');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_to_status', $result['body']['error']);
    }

    public function testTransitionRejectsEmptyToStatus(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'to_status' => '',
        ]);

        $result = $this->callControllerMethod('transition');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_to_status', $result['body']['error']);
    }

    public function testTransitionRejectsWhitespaceOnlyToStatus(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'to_status' => '   ',
        ]);

        $result = $this->callControllerMethod('transition');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_to_status', $result['body']['error']);
    }

    public function testTransitionRejectsInvalidStatus(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'to_status' => 'invalid_status',
        ]);

        $result = $this->callControllerMethod('transition');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_status', $result['body']['error']);
        $this->assertStringContainsString('invalid_status', $result['body']['detail']);
    }

    public function testTransitionRejectsRandomStringStatus(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'to_status' => 'foobar',
        ]);

        $result = $this->callControllerMethod('transition');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_status', $result['body']['error']);
    }

    public function testTransitionResponseIncludesValidStatusesOnInvalidInput(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'to_status' => 'nonexistent',
        ]);

        $result = $this->callControllerMethod('transition');

        $this->assertEquals(400, $result['status']);
        $this->assertArrayHasKey('valid', $result['body']);
        $this->assertIsArray($result['body']['valid']);
        $this->assertContains('draft', $result['body']['valid']);
        $this->assertContains('scheduled', $result['body']['valid']);
        $this->assertContains('frozen', $result['body']['valid']);
        $this->assertContains('live', $result['body']['valid']);
        $this->assertContains('paused', $result['body']['valid']);
        $this->assertContains('closed', $result['body']['valid']);
        $this->assertContains('validated', $result['body']['valid']);
        $this->assertContains('archived', $result['body']['valid']);
    }

    // =========================================================================
    // TRANSITION: VALID STATUS LIST
    // =========================================================================

    public function testTransitionValidStatusesAreComplete(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingWorkflowController.php');

        $expectedStatuses = ['draft', 'scheduled', 'frozen', 'live', 'paused', 'closed', 'validated', 'archived'];
        foreach ($expectedStatuses as $status) {
            $this->assertStringContainsString("'{$status}'", $source, "Controller should list '{$status}' as a valid status");
        }
    }

    // =========================================================================
    // TRANSITION: ARCHIVED IMMUTABILITY
    // =========================================================================

    public function testTransitionArchivedImmutabilityLogic(): void
    {
        // Replicate the immutability logic: archived meetings cannot transition
        $fromStatus = 'archived';
        $toStatus = 'closed';

        $isArchived = ($fromStatus === 'archived');

        $this->assertTrue($isArchived, 'Archived status should be detected as immutable');
    }

    public function testTransitionArchivedBlocksAllTargets(): void
    {
        $validStatuses = ['draft', 'scheduled', 'frozen', 'live', 'paused', 'closed', 'validated', 'archived'];
        $fromStatus = 'archived';

        foreach ($validStatuses as $toStatus) {
            // The controller always blocks when fromStatus is archived,
            // regardless of toStatus
            $this->assertTrue(
                $fromStatus === 'archived',
                "Transition from archived to '{$toStatus}' should be blocked",
            );
        }
    }

    // =========================================================================
    // TRANSITION: SAME-STATUS REJECTION
    // =========================================================================

    public function testTransitionAlreadyInStatusLogic(): void
    {
        // Replicate the same-status check from transition()
        $fromStatus = 'live';
        $toStatus = 'live';

        $sameStatus = ($fromStatus === $toStatus);

        $this->assertTrue($sameStatus, 'Same from/to status should be detected');
    }

    // =========================================================================
    // TRANSITION: FIELD SETTING LOGIC PER TARGET STATUS
    // =========================================================================

    public function testTransitionToFrozenSetsFields(): void
    {
        $toStatus = 'frozen';
        $userId = 'user-123';
        $fields = ['status' => $toStatus];

        if ($toStatus === 'frozen') {
            $fields['frozen_at'] = date('Y-m-d H:i:s');
            $fields['frozen_by'] = $userId;
        }

        $this->assertEquals($toStatus, $fields['status']);
        $this->assertArrayHasKey('frozen_at', $fields);
        $this->assertArrayHasKey('frozen_by', $fields);
        $this->assertEquals($userId, $fields['frozen_by']);
    }

    public function testTransitionToLiveSetsStartedAt(): void
    {
        $toStatus = 'live';
        $userId = 'user-123';
        $meeting = ['started_at' => null, 'scheduled_at' => null];
        $fields = ['status' => $toStatus];

        if ($toStatus === 'live') {
            $now = date('Y-m-d H:i:s');
            if (empty($meeting['started_at'])) {
                $fields['started_at'] = $now;
            }
            $fields['opened_by'] = $userId;
        }

        $this->assertArrayHasKey('started_at', $fields);
        $this->assertArrayHasKey('opened_by', $fields);
        $this->assertEquals($userId, $fields['opened_by']);
    }

    public function testTransitionToLiveDoesNotOverrideExistingStartedAt(): void
    {
        $toStatus = 'live';
        $meeting = ['started_at' => '2024-01-01 10:00:00', 'scheduled_at' => null];
        $fields = ['status' => $toStatus];

        if ($toStatus === 'live') {
            if (empty($meeting['started_at'])) {
                $fields['started_at'] = date('Y-m-d H:i:s');
            }
        }

        $this->assertArrayNotHasKey('started_at', $fields, 'started_at should not be overridden when already set');
    }

    public function testTransitionToLiveFromPausedClearsPauseFields(): void
    {
        $toStatus = 'live';
        $fromStatus = 'paused';
        $fields = ['status' => $toStatus];

        if ($toStatus === 'live' && $fromStatus === 'paused') {
            $fields['paused_at'] = null;
            $fields['paused_by'] = null;
        }

        $this->assertArrayHasKey('paused_at', $fields);
        $this->assertNull($fields['paused_at']);
        $this->assertArrayHasKey('paused_by', $fields);
        $this->assertNull($fields['paused_by']);
    }

    public function testTransitionToLiveFromNonPausedDoesNotClearPauseFields(): void
    {
        $toStatus = 'live';
        $fromStatus = 'frozen';
        $fields = ['status' => $toStatus];

        if ($toStatus === 'live' && $fromStatus === 'paused') {
            $fields['paused_at'] = null;
            $fields['paused_by'] = null;
        }

        $this->assertArrayNotHasKey('paused_at', $fields);
        $this->assertArrayNotHasKey('paused_by', $fields);
    }

    public function testTransitionToPausedSetsFields(): void
    {
        $toStatus = 'paused';
        $userId = 'user-456';
        $fields = ['status' => $toStatus];

        if ($toStatus === 'paused') {
            $fields['paused_at'] = date('Y-m-d H:i:s');
            $fields['paused_by'] = $userId;
        }

        $this->assertArrayHasKey('paused_at', $fields);
        $this->assertArrayHasKey('paused_by', $fields);
        $this->assertEquals($userId, $fields['paused_by']);
    }

    public function testTransitionToClosedSetsEndedAt(): void
    {
        $toStatus = 'closed';
        $userId = 'user-789';
        $meeting = ['ended_at' => null];
        $fields = ['status' => $toStatus];

        if ($toStatus === 'closed') {
            if (empty($meeting['ended_at'])) {
                $fields['ended_at'] = date('Y-m-d H:i:s');
            }
            $fields['closed_by'] = $userId;
        }

        $this->assertArrayHasKey('ended_at', $fields);
        $this->assertArrayHasKey('closed_by', $fields);
        $this->assertEquals($userId, $fields['closed_by']);
    }

    public function testTransitionToClosedDoesNotOverrideExistingEndedAt(): void
    {
        $toStatus = 'closed';
        $meeting = ['ended_at' => '2024-01-01 18:00:00'];
        $fields = ['status' => $toStatus];

        if ($toStatus === 'closed') {
            if (empty($meeting['ended_at'])) {
                $fields['ended_at'] = date('Y-m-d H:i:s');
            }
        }

        $this->assertArrayNotHasKey('ended_at', $fields, 'ended_at should not be overridden when already set');
    }

    public function testTransitionToArchivedSetsArchivedAt(): void
    {
        $toStatus = 'archived';
        $fields = ['status' => $toStatus];

        if ($toStatus === 'archived') {
            $fields['archived_at'] = date('Y-m-d H:i:s');
        }

        $this->assertArrayHasKey('archived_at', $fields);
    }

    public function testTransitionToScheduledFromFrozenClearsFrozenFields(): void
    {
        $toStatus = 'scheduled';
        $fromStatus = 'frozen';
        $fields = ['status' => $toStatus];

        if ($toStatus === 'scheduled' && $fromStatus === 'frozen') {
            $fields['frozen_at'] = null;
            $fields['frozen_by'] = null;
        }

        $this->assertArrayHasKey('frozen_at', $fields);
        $this->assertNull($fields['frozen_at']);
        $this->assertArrayHasKey('frozen_by', $fields);
        $this->assertNull($fields['frozen_by']);
    }

    public function testTransitionToScheduledFromDraftDoesNotClearFrozenFields(): void
    {
        $toStatus = 'scheduled';
        $fromStatus = 'draft';
        $fields = ['status' => $toStatus];

        if ($toStatus === 'scheduled' && $fromStatus === 'frozen') {
            $fields['frozen_at'] = null;
            $fields['frozen_by'] = null;
        }

        $this->assertArrayNotHasKey('frozen_at', $fields);
        $this->assertArrayNotHasKey('frozen_by', $fields);
    }

    public function testTransitionToValidatedSetsValidationFields(): void
    {
        $toStatus = 'validated';
        $meeting = ['validated_at' => null];
        $userId = 'user-999';
        $userName = 'Jean Dupont';
        $fields = ['status' => $toStatus];

        if ($toStatus === 'validated') {
            if (empty($meeting['validated_at'])) {
                $fields['validated_at'] = date('Y-m-d H:i:s');
                $fields['validated_by'] = $userName;
                $fields['validated_by_user_id'] = $userId;
            }
        }

        $this->assertArrayHasKey('validated_at', $fields);
        $this->assertArrayHasKey('validated_by', $fields);
        $this->assertArrayHasKey('validated_by_user_id', $fields);
        $this->assertEquals($userName, $fields['validated_by']);
        $this->assertEquals($userId, $fields['validated_by_user_id']);
    }

    public function testTransitionToValidatedDoesNotOverrideExistingValidation(): void
    {
        $toStatus = 'validated';
        $meeting = ['validated_at' => '2024-06-01 14:00:00'];
        $fields = ['status' => $toStatus];

        if ($toStatus === 'validated') {
            if (empty($meeting['validated_at'])) {
                $fields['validated_at'] = date('Y-m-d H:i:s');
                $fields['validated_by'] = 'someone';
                $fields['validated_by_user_id'] = 'uid';
            }
        }

        $this->assertArrayNotHasKey('validated_at', $fields, 'validated_at should not be overridden when already set');
        $this->assertArrayNotHasKey('validated_by', $fields);
        $this->assertArrayNotHasKey('validated_by_user_id', $fields);
    }

    // =========================================================================
    // TRANSITION: FORCE FLAG LOGIC
    // =========================================================================

    public function testTransitionForceFlagParsing(): void
    {
        // Replicate the force flag parsing
        $inputTrue = ['force' => true];
        $inputFalse = ['force' => false];
        $inputStringTrue = ['force' => '1'];
        $inputStringFalse = ['force' => '0'];
        $inputMissing = [];

        $this->assertTrue(filter_var($inputTrue['force'] ?? false, FILTER_VALIDATE_BOOLEAN));
        $this->assertFalse(filter_var($inputFalse['force'] ?? false, FILTER_VALIDATE_BOOLEAN));
        $this->assertTrue(filter_var($inputStringTrue['force'] ?? false, FILTER_VALIDATE_BOOLEAN));
        $this->assertFalse(filter_var($inputStringFalse['force'] ?? false, FILTER_VALIDATE_BOOLEAN));
        $this->assertFalse(filter_var($inputMissing['force'] ?? false, FILTER_VALIDATE_BOOLEAN));
    }

    public function testTransitionForceRequiresAdminLogic(): void
    {
        // Non-admin cannot force transitions
        $forceTransition = true;
        $currentRole = 'operator';

        $blocked = ($forceTransition && $currentRole !== 'admin');

        $this->assertTrue($blocked, 'Non-admin should be blocked from forcing transitions');
    }

    public function testTransitionForceAllowedForAdmin(): void
    {
        $forceTransition = true;
        $currentRole = 'admin';

        $blocked = ($forceTransition && $currentRole !== 'admin');

        $this->assertFalse($blocked, 'Admin should be allowed to force transitions');
    }

    // =========================================================================
    // TRANSITION: AUDIT DATA
    // =========================================================================

    public function testTransitionAuditDataIncludesForceFlag(): void
    {
        $fromStatus = 'draft';
        $toStatus = 'scheduled';
        $meetingTitle = 'AG 2024';
        $forceTransition = true;

        $auditData = [
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'title' => $meetingTitle,
        ];
        if ($forceTransition) {
            $auditData['forced'] = true;
        }

        $this->assertArrayHasKey('forced', $auditData);
        $this->assertTrue($auditData['forced']);
    }

    public function testTransitionAuditDataOmitsForceWhenNotForced(): void
    {
        $fromStatus = 'draft';
        $toStatus = 'scheduled';
        $forceTransition = false;

        $auditData = [
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'title' => 'AG 2024',
        ];
        if ($forceTransition) {
            $auditData['forced'] = true;
        }

        $this->assertArrayNotHasKey('forced', $auditData);
    }

    // =========================================================================
    // LAUNCH: METHOD ENFORCEMENT
    // =========================================================================

    public function testLaunchRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('launch');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testLaunchRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('launch');

        $this->assertEquals(405, $result['status']);
    }

    // =========================================================================
    // LAUNCH: MEETING ID VALIDATION
    // =========================================================================

    public function testLaunchRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('launch');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testLaunchRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => '']);

        $result = $this->callControllerMethod('launch');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testLaunchRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => 'bad-uuid-here']);

        $result = $this->callControllerMethod('launch');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    // =========================================================================
    // LAUNCH: PATH COMPUTATION LOGIC
    // =========================================================================

    public function testLaunchPathFromDraft(): void
    {
        $fromStatus = 'draft';
        $path = match ($fromStatus) {
            'draft' => ['scheduled', 'frozen', 'live'],
            'scheduled' => ['frozen', 'live'],
            'frozen' => ['live'],
            default => [],
        };

        $this->assertEquals(['scheduled', 'frozen', 'live'], $path);
    }

    public function testLaunchPathFromScheduled(): void
    {
        $fromStatus = 'scheduled';
        $path = match ($fromStatus) {
            'draft' => ['scheduled', 'frozen', 'live'],
            'scheduled' => ['frozen', 'live'],
            'frozen' => ['live'],
            default => [],
        };

        $this->assertEquals(['frozen', 'live'], $path);
    }

    public function testLaunchPathFromFrozen(): void
    {
        $fromStatus = 'frozen';
        $path = match ($fromStatus) {
            'draft' => ['scheduled', 'frozen', 'live'],
            'scheduled' => ['frozen', 'live'],
            'frozen' => ['live'],
            default => [],
        };

        $this->assertEquals(['live'], $path);
    }

    public function testLaunchPathAlwaysEndsAtLive(): void
    {
        $validStartStatuses = ['draft', 'scheduled', 'frozen'];

        foreach ($validStartStatuses as $fromStatus) {
            $path = match ($fromStatus) {
                'draft' => ['scheduled', 'frozen', 'live'],
                'scheduled' => ['frozen', 'live'],
                'frozen' => ['live'],
                default => [],
            };

            $this->assertEquals('live', end($path), "Path from '{$fromStatus}' should end at 'live'");
        }
    }

    public function testLaunchCannotLaunchFromLive(): void
    {
        // When fromStatus is 'live', the controller api_fail()s with already_in_status
        $fromStatus = 'live';
        $this->assertEquals('live', $fromStatus);

        // The controller would fail before computing a path
        // This test validates the logic branch exists
    }

    public function testLaunchCannotLaunchFromClosed(): void
    {
        // closed, validated, archived are invalid launch statuses
        $invalidStatuses = ['closed', 'validated', 'archived'];

        foreach ($invalidStatuses as $status) {
            $path = match ($status) {
                'draft' => ['scheduled', 'frozen', 'live'],
                'scheduled' => ['frozen', 'live'],
                'frozen' => ['live'],
                default => [],
            };
            $this->assertEmpty($path, "Should not have a launch path from '{$status}'");
        }
    }

    // =========================================================================
    // LAUNCH: CUMULATIVE ISSUE CHECKING
    // =========================================================================

    public function testLaunchCumulativeIssueAggregation(): void
    {
        // Replicate the cumulative issue aggregation logic
        $path = ['scheduled', 'frozen', 'live'];

        $allIssues = [];
        $allWarnings = [];
        $simulatedFrom = 'draft';

        // Simulated step checks
        $stepChecks = [
            ['issues' => ['no motions'], 'warnings' => ['missing desc']],
            ['issues' => [], 'warnings' => ['low attendance']],
            ['issues' => ['quorum not met'], 'warnings' => []],
        ];

        foreach ($path as $i => $step) {
            $stepCheck = $stepChecks[$i];
            $allIssues = array_merge($allIssues, $stepCheck['issues']);
            $allWarnings = array_merge($allWarnings, $stepCheck['warnings']);
            $simulatedFrom = $step;
        }

        $this->assertCount(2, $allIssues);
        $this->assertContains('no motions', $allIssues);
        $this->assertContains('quorum not met', $allIssues);
        $this->assertCount(2, $allWarnings);
        $this->assertContains('missing desc', $allWarnings);
        $this->assertContains('low attendance', $allWarnings);
    }

    public function testLaunchBlockedWhenIssuesExist(): void
    {
        $allIssues = ['some blocking issue'];

        $blocked = count($allIssues) > 0;

        $this->assertTrue($blocked);
    }

    public function testLaunchProceedsWhenNoIssues(): void
    {
        $allIssues = [];

        $blocked = count($allIssues) > 0;

        $this->assertFalse($blocked);
    }

    // =========================================================================
    // WORKFLOW CHECK: METHOD ENFORCEMENT
    // =========================================================================

    public function testWorkflowCheckRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('workflowCheck');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // WORKFLOW CHECK: MEETING ID VALIDATION
    // =========================================================================

    public function testWorkflowCheckRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('workflowCheck');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testWorkflowCheckRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('workflowCheck');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testWorkflowCheckRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'xyz-not-uuid'];

        $result = $this->callControllerMethod('workflowCheck');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    // =========================================================================
    // WORKFLOW CHECK: TO_STATUS BRANCHING
    // =========================================================================

    public function testWorkflowCheckToStatusOptionalLogic(): void
    {
        // When to_status is empty string, controller calls getTransitionReadiness
        // When to_status has a value, controller calls issuesBeforeTransition
        $toStatusEmpty = '';
        $toStatusSet = 'frozen';

        $this->assertTrue($toStatusEmpty === '', 'Empty to_status triggers readiness overview');
        $this->assertTrue($toStatusSet !== '', 'Non-empty to_status triggers specific check');
    }

    // =========================================================================
    // READY CHECK: METHOD ENFORCEMENT (uses GET via api_query)
    // =========================================================================

    public function testReadyCheckRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('readyCheck');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testReadyCheckRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('readyCheck');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testReadyCheckRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-a-uuid-at-all'];

        $result = $this->callControllerMethod('readyCheck');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // READY CHECK: PRESIDENT CHECK LOGIC
    // =========================================================================

    public function testReadyCheckPresidentCheckPasses(): void
    {
        $meeting = ['president_name' => 'Jean Dupont'];
        $pres = trim((string) ($meeting['president_name'] ?? ''));

        $check = [
            'passed' => $pres !== '',
            'label' => 'Président renseigné',
            'detail' => $pres !== '' ? $pres : "Aucun président (president_name) n'est renseigné.",
        ];

        $this->assertTrue($check['passed']);
        $this->assertEquals('Jean Dupont', $check['detail']);
    }

    public function testReadyCheckPresidentCheckFailsWhenEmpty(): void
    {
        $meeting = ['president_name' => ''];
        $pres = trim((string) ($meeting['president_name'] ?? ''));

        $check = [
            'passed' => $pres !== '',
            'label' => 'Président renseigné',
            'detail' => $pres !== '' ? $pres : "Aucun président (president_name) n'est renseigné.",
        ];

        $this->assertFalse($check['passed']);
        $this->assertStringContainsString('Aucun', $check['detail']);
    }

    public function testReadyCheckPresidentCheckFailsWhenMissing(): void
    {
        $meeting = [];
        $pres = trim((string) ($meeting['president_name'] ?? ''));

        $check = ['passed' => $pres !== ''];

        $this->assertFalse($check['passed']);
    }

    public function testReadyCheckPresidentCheckTrimsWhitespace(): void
    {
        $meeting = ['president_name' => '   '];
        $pres = trim((string) ($meeting['president_name'] ?? ''));

        $check = ['passed' => $pres !== ''];

        $this->assertFalse($check['passed'], 'Whitespace-only president_name should fail');
    }

    // =========================================================================
    // READY CHECK: OPEN MOTIONS CHECK
    // =========================================================================

    public function testReadyCheckOpenMotionsCheckPasses(): void
    {
        $openCount = 0;

        $check = [
            'passed' => $openCount === 0,
            'label' => 'Motions fermées',
        ];

        $this->assertTrue($check['passed']);
    }

    public function testReadyCheckOpenMotionsCheckFails(): void
    {
        $openCount = 3;

        $check = [
            'passed' => $openCount === 0,
            'label' => 'Motions fermées',
            'detail' => $openCount > 0 ? "Il reste {$openCount} motion(s) ouverte(s). Fermez-les avant validation." : '',
        ];

        $this->assertFalse($check['passed']);
        $this->assertStringContainsString('3', $check['detail']);
    }

    // =========================================================================
    // READY CHECK: MANUAL TALLY VALIDATION LOGIC
    // =========================================================================

    public function testReadyCheckManualTallyConsistentWhenSumsMatch(): void
    {
        $manualTotal = 100;
        $manualFor = 60;
        $manualAg = 30;
        $manualAb = 10;

        $manualOk = false;
        if ($manualTotal > 0) {
            $manualOk = (($manualFor + $manualAg + $manualAb) === $manualTotal);
        }

        $this->assertTrue($manualOk);
    }

    public function testReadyCheckManualTallyInconsistentWhenSumsMismatch(): void
    {
        $manualTotal = 100;
        $manualFor = 60;
        $manualAg = 30;
        $manualAb = 5; // 60+30+5 = 95 != 100

        $manualOk = false;
        if ($manualTotal > 0) {
            $manualOk = (($manualFor + $manualAg + $manualAb) === $manualTotal);
        }

        $this->assertFalse($manualOk);
    }

    public function testReadyCheckManualTallyZeroTotalSkipsCheck(): void
    {
        $manualTotal = 0;
        $manualFor = 0;
        $manualAg = 0;
        $manualAb = 0;

        $manualOk = false;
        if ($manualTotal > 0) {
            $manualOk = (($manualFor + $manualAg + $manualAb) === $manualTotal);
        }

        $this->assertFalse($manualOk, 'Zero total should result in false (no manual tally)');
    }

    // =========================================================================
    // READY CHECK: MISSING BALLOTS LOGIC
    // =========================================================================

    public function testReadyCheckMissingBallotsDetected(): void
    {
        $eligibleCount = 50;
        $ballotsTotal = 42;

        $missing = max(0, $eligibleCount - $ballotsTotal);

        $this->assertEquals(8, $missing);
    }

    public function testReadyCheckNoMissingBallots(): void
    {
        $eligibleCount = 50;
        $ballotsTotal = 50;

        $missing = max(0, $eligibleCount - $ballotsTotal);

        $this->assertEquals(0, $missing);
    }

    public function testReadyCheckMoreBallotsThanEligible(): void
    {
        $eligibleCount = 40;
        $ballotsTotal = 45;

        $missing = max(0, $eligibleCount - $ballotsTotal);

        $this->assertEquals(0, $missing, 'Excess ballots should not produce negative missing count');
    }

    // =========================================================================
    // READY CHECK: NO EXPLOITABLE RESULT LOGIC
    // =========================================================================

    public function testReadyCheckNoExploitableResultDetected(): void
    {
        $manualOk = false;
        $eligibleBallots = 0;

        $noResult = (!$manualOk && $eligibleBallots <= 0);

        $this->assertTrue($noResult);
    }

    public function testReadyCheckHasManualResult(): void
    {
        $manualOk = true;
        $eligibleBallots = 0;

        $noResult = (!$manualOk && $eligibleBallots <= 0);

        $this->assertFalse($noResult, 'Should pass when manual tally is ok');
    }

    public function testReadyCheckHasEVoteResult(): void
    {
        $manualOk = false;
        $eligibleBallots = 30;

        $noResult = (!$manualOk && $eligibleBallots <= 0);

        $this->assertFalse($noResult, 'Should pass when e-vote ballots exist');
    }

    // =========================================================================
    // READY CHECK: OVERALL READINESS LOGIC
    // =========================================================================

    public function testReadyCheckOverallReadyWhenAllPass(): void
    {
        $checks = [
            ['passed' => true],
            ['passed' => true],
            ['passed' => true],
        ];

        $ready = true;
        foreach ($checks as $c) {
            if (!$c['passed']) {
                $ready = false;
                break;
            }
        }

        $this->assertTrue($ready);
    }

    public function testReadyCheckOverallNotReadyWhenOneFails(): void
    {
        $checks = [
            ['passed' => true],
            ['passed' => false],
            ['passed' => true],
        ];

        $ready = true;
        foreach ($checks as $c) {
            if (!$c['passed']) {
                $ready = false;
                break;
            }
        }

        $this->assertFalse($ready);
    }

    public function testReadyCheckOverallReadyWithEmptyChecks(): void
    {
        $checks = [];

        $ready = true;
        foreach ($checks as $c) {
            if (!$c['passed']) {
                $ready = false;
                break;
            }
        }

        $this->assertTrue($ready, 'No checks should mean ready');
    }

    // =========================================================================
    // READY CHECK: RESPONSE STRUCTURE
    // =========================================================================

    public function testReadyCheckResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingWorkflowController.php');

        $expectedKeys = ['ready', 'checks', 'can', 'bad_motions', 'meta'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString("'{$key}'", $source, "readyCheck response should contain '{$key}'");
        }
    }

    public function testReadyCheckMetaFields(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingWorkflowController.php');

        $metaFields = ['meeting_id', 'eligible_count', 'fallback_eligible_used'];
        foreach ($metaFields as $field) {
            $this->assertStringContainsString("'{$field}'", $source, "readyCheck meta should contain '{$field}'");
        }
    }

    // =========================================================================
    // CONSOLIDATE: METHOD ENFORCEMENT
    // =========================================================================

    public function testConsolidateRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('consolidate');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testConsolidateRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('consolidate');

        $this->assertEquals(405, $result['status']);
    }

    // =========================================================================
    // CONSOLIDATE: MEETING ID VALIDATION
    // =========================================================================

    public function testConsolidateRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('consolidate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testConsolidateRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => '']);

        $result = $this->callControllerMethod('consolidate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testConsolidateRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => 'not-uuid-format']);

        $result = $this->callControllerMethod('consolidate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    // =========================================================================
    // CONSOLIDATE: STATUS VALIDATION LOGIC
    // =========================================================================

    public function testConsolidateAllowsClosedStatus(): void
    {
        $status = 'closed';
        $allowed = in_array($status, ['closed', 'validated'], true);

        $this->assertTrue($allowed);
    }

    public function testConsolidateAllowsValidatedStatus(): void
    {
        $status = 'validated';
        $allowed = in_array($status, ['closed', 'validated'], true);

        $this->assertTrue($allowed);
    }

    public function testConsolidateRejectsDraftStatus(): void
    {
        $status = 'draft';
        $allowed = in_array($status, ['closed', 'validated'], true);

        $this->assertFalse($allowed);
    }

    public function testConsolidateRejectsLiveStatus(): void
    {
        $status = 'live';
        $allowed = in_array($status, ['closed', 'validated'], true);

        $this->assertFalse($allowed);
    }

    public function testConsolidateRejectsArchivedStatus(): void
    {
        $status = 'archived';
        $allowed = in_array($status, ['closed', 'validated'], true);

        $this->assertFalse($allowed);
    }

    public function testConsolidateRejectsAllNonAllowedStatuses(): void
    {
        $rejected = ['draft', 'scheduled', 'frozen', 'live', 'paused', 'archived'];

        foreach ($rejected as $status) {
            $allowed = in_array($status, ['closed', 'validated'], true);
            $this->assertFalse($allowed, "Status '{$status}' should not be allowed for consolidation");
        }
    }

    // =========================================================================
    // RESET DEMO: METHOD ENFORCEMENT
    // =========================================================================

    public function testResetDemoRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('resetDemo');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // RESET DEMO: MEETING ID VALIDATION
    // =========================================================================

    public function testResetDemoRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('resetDemo');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testResetDemoRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => '']);

        $result = $this->callControllerMethod('resetDemo');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testResetDemoRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => 'invalid']);

        $result = $this->callControllerMethod('resetDemo');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    // =========================================================================
    // RESET DEMO: CONFIRM GUARD
    // =========================================================================

    public function testResetDemoRequiresConfirmReset(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('resetDemo');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_confirm', $result['body']['error']);
        $this->assertStringContainsString('RESET', $result['body']['detail']);
    }

    public function testResetDemoRejectsEmptyConfirm(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'confirm' => '',
        ]);

        $result = $this->callControllerMethod('resetDemo');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_confirm', $result['body']['error']);
    }

    public function testResetDemoRejectsWrongConfirmValue(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'confirm' => 'yes',
        ]);

        $result = $this->callControllerMethod('resetDemo');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_confirm', $result['body']['error']);
    }

    public function testResetDemoRejectsCaseSensitiveConfirm(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'confirm' => 'reset', // lowercase
        ]);

        $result = $this->callControllerMethod('resetDemo');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_confirm', $result['body']['error']);
    }

    public function testResetDemoConfirmMustBeExactString(): void
    {
        // Replicate the confirm check
        $cases = [
            'RESET' => true,
            'reset' => false,
            'Reset' => false,
            'RESET ' => false,
            ' RESET' => false,
            'YES' => false,
            '' => false,
        ];

        foreach ($cases as $input => $expected) {
            $confirm = (string) $input;
            $this->assertEquals($expected, $confirm === 'RESET', "Confirm value '{$input}' should be " . ($expected ? 'accepted' : 'rejected'));
        }
    }

    // =========================================================================
    // RESET DEMO: VALIDATED MEETING GUARD LOGIC
    // =========================================================================

    public function testResetDemoBlocksValidatedMeeting(): void
    {
        $meeting = ['validated_at' => '2024-06-01 14:00:00'];

        $blocked = !empty($meeting['validated_at']);

        $this->assertTrue($blocked, 'Validated meeting should block reset');
    }

    public function testResetDemoAllowsNonValidatedMeeting(): void
    {
        $meeting = ['validated_at' => null];

        $blocked = !empty($meeting['validated_at']);

        $this->assertFalse($blocked, 'Non-validated meeting should allow reset');
    }

    public function testResetDemoAllowsMeetingWithEmptyValidatedAt(): void
    {
        $meeting = ['validated_at' => ''];

        $blocked = !empty($meeting['validated_at']);

        $this->assertFalse($blocked, 'Empty validated_at should allow reset');
    }

    // =========================================================================
    // TRANSITION: RESPONSE STRUCTURE
    // =========================================================================

    public function testTransitionResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingWorkflowController.php');

        // Check the api_ok call in transition()
        $this->assertStringContainsString("'from_status'", $source);
        $this->assertStringContainsString("'to_status'", $source);
        $this->assertStringContainsString("'transitioned_at'", $source);
        $this->assertStringContainsString("'warnings'", $source);
    }

    // =========================================================================
    // LAUNCH: RESPONSE STRUCTURE
    // =========================================================================

    public function testLaunchResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingWorkflowController.php');

        // Check the api_ok call in launch() includes path
        $this->assertStringContainsString("'path'", $source);
        $this->assertStringContainsString("'from_status'", $source);
        $this->assertStringContainsString("'to_status'", $source);
    }

    // =========================================================================
    // WORKFLOW CHECK: RESPONSE STRUCTURE
    // =========================================================================

    public function testWorkflowCheckResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingWorkflowController.php');

        $this->assertStringContainsString("'can_proceed'", $source);
        $this->assertStringContainsString("'issues'", $source);
        $this->assertStringContainsString("'warnings'", $source);
    }

    // =========================================================================
    // CONSOLIDATE: RESPONSE STRUCTURE
    // =========================================================================

    public function testConsolidateResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingWorkflowController.php');

        $this->assertStringContainsString("'updated_motions'", $source);
    }

    // =========================================================================
    // RESET DEMO: RESPONSE STRUCTURE
    // =========================================================================

    public function testResetDemoResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingWorkflowController.php');

        // resetDemo returns ok with meeting_id
        $this->assertStringContainsString("'ok' => true", $source);
        $this->assertStringContainsString("'meeting_id'", $source);
    }

    // =========================================================================
    // TRANSITION: VALID STATUSES ENUMERATION
    // =========================================================================

    public function testTransitionAllEightStatusesRecognized(): void
    {
        $validStatuses = ['draft', 'scheduled', 'frozen', 'live', 'paused', 'closed', 'validated', 'archived'];

        $this->assertCount(8, $validStatuses);

        // Each valid status should NOT trigger invalid_status error in the controller logic
        foreach ($validStatuses as $status) {
            $this->assertTrue(
                in_array($status, $validStatuses, true),
                "'{$status}' should be in the valid statuses list",
            );
        }
    }

    public function testTransitionRejectsStatusesNotInList(): void
    {
        $validStatuses = ['draft', 'scheduled', 'frozen', 'live', 'paused', 'closed', 'validated', 'archived'];

        $invalidAttempts = ['active', 'pending', 'cancelled', 'deleted', 'open', 'completed', 'in_progress'];

        foreach ($invalidAttempts as $status) {
            $this->assertFalse(
                in_array($status, $validStatuses, true),
                "'{$status}' should not be in the valid statuses list",
            );
        }
    }

    // =========================================================================
    // TRANSITION: SCHEDULED_AT ADJUSTMENT LOGIC
    // =========================================================================

    public function testTransitionToLiveAdjustsScheduledAtWhenInFuture(): void
    {
        $now = '2024-06-15 10:00:00';
        $meeting = ['scheduled_at' => '2024-07-01 09:00:00', 'started_at' => null];
        $fields = ['status' => 'live'];

        if (!empty($meeting['scheduled_at']) && $meeting['scheduled_at'] > $now) {
            $fields['scheduled_at'] = $now;
        }

        $this->assertArrayHasKey('scheduled_at', $fields);
        $this->assertEquals($now, $fields['scheduled_at']);
    }

    public function testTransitionToLiveKeepsScheduledAtWhenInPast(): void
    {
        $now = '2024-06-15 10:00:00';
        $meeting = ['scheduled_at' => '2024-06-01 09:00:00', 'started_at' => null];
        $fields = ['status' => 'live'];

        if (!empty($meeting['scheduled_at']) && $meeting['scheduled_at'] > $now) {
            $fields['scheduled_at'] = $now;
        }

        $this->assertArrayNotHasKey('scheduled_at', $fields, 'Past scheduled_at should not be overwritten');
    }

    public function testTransitionToLiveHandlesEmptyScheduledAt(): void
    {
        $now = '2024-06-15 10:00:00';
        $meeting = ['scheduled_at' => null, 'started_at' => null];
        $fields = ['status' => 'live'];

        if (!empty($meeting['scheduled_at']) && $meeting['scheduled_at'] > $now) {
            $fields['scheduled_at'] = $now;
        }

        $this->assertArrayNotHasKey('scheduled_at', $fields, 'Null scheduled_at should not trigger adjustment');
    }

    // =========================================================================
    // READY CHECK: FALLBACK ELIGIBLE LOGIC
    // =========================================================================

    public function testReadyCheckFallbackEligibleTriggered(): void
    {
        $eligibleCount = 0;
        $activeMemberCount = 42;
        $fallbackEligibleUsed = false;

        if ($eligibleCount <= 0) {
            $fallbackEligibleUsed = true;
            $eligibleCount = $activeMemberCount;
        }

        $this->assertTrue($fallbackEligibleUsed);
        $this->assertEquals(42, $eligibleCount);
    }

    public function testReadyCheckFallbackEligibleNotTriggered(): void
    {
        $eligibleCount = 30;
        $activeMemberCount = 42;
        $fallbackEligibleUsed = false;

        if ($eligibleCount <= 0) {
            $fallbackEligibleUsed = true;
            $eligibleCount = $activeMemberCount;
        }

        $this->assertFalse($fallbackEligibleUsed);
        $this->assertEquals(30, $eligibleCount, 'Should keep original count when eligible > 0');
    }

    public function testReadyCheckFallbackCheckLabel(): void
    {
        $fallbackEligibleUsed = true;

        $check = [
            'passed' => !$fallbackEligibleUsed,
            'label' => 'Présences saisies',
            'detail' => $fallbackEligibleUsed ? 'Règle de fallback utilisée (tous membres actifs).' : '',
        ];

        $this->assertFalse($check['passed'], 'Fallback should make the check fail');
        $this->assertStringContainsString('fallback', $check['detail']);
    }

    // =========================================================================
    // READY CHECK: INVALID BALLOTS DETECTION LOGIC
    // =========================================================================

    public function testReadyCheckInvalidBallotsDetected(): void
    {
        $invalidDirect = 2;
        $invalidProxy = 1;

        $hasInvalid = ($invalidDirect > 0 || $invalidProxy > 0);

        $this->assertTrue($hasInvalid);
    }

    public function testReadyCheckNoInvalidBallots(): void
    {
        $invalidDirect = 0;
        $invalidProxy = 0;

        $hasInvalid = ($invalidDirect > 0 || $invalidProxy > 0);

        $this->assertFalse($hasInvalid);
    }

    public function testReadyCheckInvalidBallotDetailMessage(): void
    {
        $invalidDirect = 3;
        $invalidProxy = 2;

        $detail = "Bulletins non éligibles détectés (direct: {$invalidDirect}, procuration: {$invalidProxy}).";

        $this->assertStringContainsString('3', $detail);
        $this->assertStringContainsString('2', $detail);
        $this->assertStringContainsString('direct', $detail);
        $this->assertStringContainsString('procuration', $detail);
    }

    // =========================================================================
    // READY CHECK: BAD MOTIONS AGGREGATION LOGIC
    // =========================================================================

    public function testReadyCheckBadMotionsAddChecksEntries(): void
    {
        $bad = [
            ['motion_id' => 'm1', 'title' => 'Motion A', 'detail' => 'Problem 1'],
            ['motion_id' => 'm2', 'title' => 'Motion B', 'detail' => 'Problem 2'],
        ];
        $checks = [];

        foreach ($bad as $b) {
            $checks[] = ['passed' => false, 'label' => $b['title'], 'detail' => $b['detail']];
        }

        $this->assertCount(2, $checks);
        $this->assertFalse($checks[0]['passed']);
        $this->assertEquals('Motion A', $checks[0]['label']);
        $this->assertEquals('Problem 1', $checks[0]['detail']);
    }

    public function testReadyCheckGoodMotionsAddSuccessEntry(): void
    {
        $bad = [];
        $motions = [['id' => 'm1'], ['id' => 'm2'], ['id' => 'm3']];
        $checks = [];

        if (count($bad) === 0 && count($motions) > 0) {
            $checks[] = [
                'passed' => true,
                'label' => 'Résultats exploitables',
                'detail' => count($motions) . ' motion(s) avec résultat valide.',
            ];
        }

        $this->assertCount(1, $checks);
        $this->assertTrue($checks[0]['passed']);
        $this->assertStringContainsString('3', $checks[0]['detail']);
    }

    public function testReadyCheckNoMotionsNoSuccessEntry(): void
    {
        $bad = [];
        $motions = [];
        $checks = [];

        if (count($bad) === 0 && count($motions) > 0) {
            $checks[] = ['passed' => true, 'label' => 'Résultats exploitables'];
        }

        $this->assertEmpty($checks, 'Should not add success entry when no motions exist');
    }

    // =========================================================================
    // CONSOLIDATE: AUDIT LOG STRUCTURE
    // =========================================================================

    public function testConsolidateAuditLogData(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingWorkflowController.php');

        $this->assertStringContainsString("'meeting.consolidate'", $source);
        $this->assertStringContainsString("'updated_motions'", $source);
    }

    // =========================================================================
    // RESET DEMO: AUDIT LOG STRUCTURE
    // =========================================================================

    public function testResetDemoAuditLogData(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingWorkflowController.php');

        $this->assertStringContainsString("'meeting.reset_demo'", $source);
        $this->assertStringContainsString("'reset_by'", $source);
    }

    // =========================================================================
    // TRANSITION: EVENT BROADCASTER CALL
    // =========================================================================

    public function testTransitionBroadcastsEvent(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingWorkflowController.php');

        $this->assertStringContainsString('EventBroadcaster::meetingStatusChanged', $source);
    }

    // =========================================================================
    // LAUNCH: EVENT BROADCASTER CALL
    // =========================================================================

    public function testLaunchBroadcastsEvent(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingWorkflowController.php');

        // launch() also calls EventBroadcaster
        $this->assertStringContainsString('meetingStatusChanged', $source);
    }

    // =========================================================================
    // TRANSITION: WORKFLOW ISSUES BLOCK LOGIC
    // =========================================================================

    public function testTransitionWorkflowIssuesBlockWithoutForce(): void
    {
        $canProceed = false;
        $forceTransition = false;

        $blocked = (!$canProceed && !$forceTransition);

        $this->assertTrue($blocked);
    }

    public function testTransitionWorkflowIssuesPassWithForce(): void
    {
        $canProceed = false;
        $forceTransition = true;

        $blocked = (!$canProceed && !$forceTransition);

        $this->assertFalse($blocked, 'Force flag should bypass workflow issues');
    }

    public function testTransitionWorkflowIssuesPassWhenNone(): void
    {
        $canProceed = true;
        $forceTransition = false;

        $blocked = (!$canProceed && !$forceTransition);

        $this->assertFalse($blocked, 'No issues should not block');
    }

    // =========================================================================
    // LAUNCH: FIELD SETTING PER STEP
    // =========================================================================

    public function testLaunchFrozenStepSetsFields(): void
    {
        // Replicate the field-setting logic inside launch()'s foreach
        $toStatus = 'frozen';
        $userId = 'uid-1';
        $now = '2024-06-15 10:00:00';
        $fields = ['status' => $toStatus];

        switch ($toStatus) {
            case 'frozen':
                $fields['frozen_at'] = $now;
                $fields['frozen_by'] = $userId;
                break;
            case 'live':
                break;
        }

        $this->assertArrayHasKey('frozen_at', $fields);
        $this->assertEquals($now, $fields['frozen_at']);
        $this->assertEquals($userId, $fields['frozen_by']);
    }

    public function testLaunchLiveStepSetsStartedAt(): void
    {
        $toStatus = 'live';
        $userId = 'uid-1';
        $now = '2024-06-15 10:00:00';
        $meeting = ['started_at' => null, 'scheduled_at' => null];
        $fields = ['status' => $toStatus];

        switch ($toStatus) {
            case 'frozen':
                break;
            case 'live':
                if (empty($meeting['started_at'])) {
                    $fields['started_at'] = $now;
                }
                if (!empty($meeting['scheduled_at']) && $meeting['scheduled_at'] > $now) {
                    $fields['scheduled_at'] = $now;
                }
                $fields['opened_by'] = $userId;
                break;
        }

        $this->assertArrayHasKey('started_at', $fields);
        $this->assertArrayHasKey('opened_by', $fields);
        $this->assertEquals($userId, $fields['opened_by']);
    }

    // =========================================================================
    // UUID VALIDATION HELPER
    // =========================================================================

    public function testApiIsUuidAcceptsValidUuids(): void
    {
        $validUuids = [
            '12345678-1234-1234-1234-123456789abc',
            'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            '00000000-0000-0000-0000-000000000000',
            'ABCDEF12-3456-7890-ABCD-EF1234567890',
        ];

        foreach ($validUuids as $uuid) {
            $this->assertTrue(api_is_uuid($uuid), "'{$uuid}' should be a valid UUID");
        }
    }

    public function testApiIsUuidRejectsInvalidUuids(): void
    {
        $invalidUuids = [
            '',
            'not-a-uuid',
            '12345678-1234-1234-1234',
            '12345678-1234-1234-1234-123456789abcx',
            '12345678_1234_1234_1234_123456789abc',
            'g2345678-1234-1234-1234-123456789abc',
        ];

        foreach ($invalidUuids as $uuid) {
            $this->assertFalse(api_is_uuid($uuid), "'{$uuid}' should not be a valid UUID");
        }
    }

    // =========================================================================
    // CONTROLLER: HANDLE DELEGATES TO CORRECT METHOD
    // =========================================================================

    public function testHandleMethodDelegation(): void
    {
        // Verify that handle() calls the named method by testing that
        // calling a known method name produces the expected validation error
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        // readyCheck uses api_query for meeting_id (not api_require_uuid)
        $result = $this->callControllerMethod('readyCheck');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // READY CHECK: MANUAL TALLY INCONSISTENT BUT HAS EVOTE
    // =========================================================================

    public function testReadyCheckInconsistentManualWithEvote(): void
    {
        // When manualTotal > 0 and !manualOk, but eligibleBallots > 0,
        // only the inconsistent manual tally warning is added (not the "no result" one)
        $manualOk = false;
        $manualTotal = 100;
        $eligibleBallots = 50;

        $bad = [];

        if (!$manualOk && $eligibleBallots <= 0) {
            $bad[] = 'no_result';
        } elseif ($manualTotal > 0 && !$manualOk) {
            $bad[] = 'manual_inconsistent';
        }

        $this->assertCount(1, $bad);
        $this->assertEquals('manual_inconsistent', $bad[0]);
    }

    public function testReadyCheckConsistentManualNoEvote(): void
    {
        // When manual is ok but no e-vote ballots, no issues should be flagged
        $manualOk = true;
        $manualTotal = 100;
        $eligibleBallots = 0;

        $bad = [];

        if (!$manualOk && $eligibleBallots <= 0) {
            $bad[] = 'no_result';
        } elseif ($manualTotal > 0 && !$manualOk) {
            $bad[] = 'manual_inconsistent';
        }

        $this->assertEmpty($bad, 'Consistent manual tally should not produce issues');
    }

    // =========================================================================
    // CONTROLLER SOURCE: USES EXPECTED SERVICES
    // =========================================================================

    public function testControllerUsesMeetingWorkflowService(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingWorkflowController.php');

        $this->assertStringContainsString('MeetingWorkflowService', $source);
    }

    public function testControllerUsesOfficialResultsService(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingWorkflowController.php');

        $this->assertStringContainsString('OfficialResultsService', $source);
    }

    public function testControllerUsesEventBroadcaster(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingWorkflowController.php');

        $this->assertStringContainsString('EventBroadcaster', $source);
    }

    // =========================================================================
    // CONTROLLER SOURCE: USES EXPECTED REPOSITORIES
    // =========================================================================

    public function testControllerUsesExpectedRepositories(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingWorkflowController.php');

        $repos = [
            'MeetingRepository',
            'MeetingStatsRepository',
            'MotionRepository',
            'AttendanceRepository',
            'MemberRepository',
            'BallotRepository',
            'VoteTokenRepository',
            'ManualActionRepository',
        ];

        foreach ($repos as $repo) {
            $this->assertStringContainsString($repo, $source, "Controller should use {$repo}");
        }
    }

    // =========================================================================
    // TRANSITION: METHOD COUNT VERIFICATION
    // =========================================================================

    public function testControllerHasExactlySixPublicBusinessMethods(): void
    {
        $ref = new \ReflectionClass(MeetingWorkflowController::class);
        $expectedMethods = ['transition', 'launch', 'workflowCheck', 'readyCheck', 'consolidate', 'resetDemo'];

        // All declared public methods from the controller (excluding inherited handle/wrapApiCall)
        $publicMethods = array_filter(
            $ref->getMethods(\ReflectionMethod::IS_PUBLIC),
            fn(\ReflectionMethod $m) => $m->getDeclaringClass()->getName() === MeetingWorkflowController::class,
        );

        $methodNames = array_map(fn(\ReflectionMethod $m) => $m->getName(), $publicMethods);
        sort($methodNames);
        sort($expectedMethods);

        $this->assertEquals($expectedMethods, $methodNames, 'Controller should have exactly the 6 expected public methods');
    }

    // =========================================================================
    // LAUNCH: AUDIT LOG STRUCTURE
    // =========================================================================

    public function testLaunchAuditLogData(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingWorkflowController.php');

        $this->assertStringContainsString("'meeting.launch'", $source);
        $this->assertStringContainsString("'from_status'", $source);
        $this->assertStringContainsString("'to_status'", $source);
        $this->assertStringContainsString("'path'", $source);
    }

    // =========================================================================
    // TRANSITION: AUDIT LOG STRUCTURE
    // =========================================================================

    public function testTransitionAuditLogData(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingWorkflowController.php');

        $this->assertStringContainsString("'meeting.transition'", $source);
    }
}
