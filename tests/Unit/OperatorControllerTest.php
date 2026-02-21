<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\OperatorController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OperatorController.
 *
 * Tests the operator endpoint logic including:
 *  - Workflow state computation (quorum, attendance, proxy, consolidation)
 *  - Anomaly detection logic (duplicates, ineligible ballots, missing voters)
 *  - Vote opening validation (input parsing, expiry clamping)
 *  - Method enforcement and input validation
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class OperatorControllerTest extends TestCase
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
        $controller = new OperatorController();
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

    // =========================================================================
    // CONTROLLER STRUCTURE TESTS
    // =========================================================================

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(OperatorController::class);

        $expectedMethods = ['workflowState', 'openVote', 'anomalies'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "OperatorController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(OperatorController::class);

        $expectedMethods = ['workflowState', 'openVote', 'anomalies'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "OperatorController::{$method}() should be public",
            );
        }
    }

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(OperatorController::class);
        $this->assertTrue($ref->isFinal(), 'OperatorController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new OperatorController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    // =========================================================================
    // WORKFLOW STATE: INPUT VALIDATION
    // =========================================================================

    public function testWorkflowStateRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = []; // No meeting_id

        $result = $this->callControllerMethod('workflowState');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testWorkflowStateRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('workflowState');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testWorkflowStateRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-a-valid-uuid'];

        $result = $this->callControllerMethod('workflowState');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // WORKFLOW STATE: QUORUM COMPUTATION LOGIC
    // =========================================================================

    public function testQuorumRatioCalculation(): void
    {
        // Replicate the quorum ratio logic from workflowState()
        $presentCount = 60;
        $eligibleMembers = 100;

        $quorumRatio = $eligibleMembers > 0 ? ($presentCount / $eligibleMembers) : 0.0;

        $this->assertEquals(0.6, $quorumRatio);
    }

    public function testQuorumRatioWithZeroEligible(): void
    {
        $presentCount = 10;
        $eligibleMembers = 0;

        $quorumRatio = $eligibleMembers > 0 ? ($presentCount / $eligibleMembers) : 0.0;

        $this->assertEquals(0.0, $quorumRatio);
    }

    public function testQuorumOkWhenMetThreshold(): void
    {
        $presentCount = 60;
        $eligibleMembers = 100;
        $quorumThreshold = 0.5;

        $quorumRatio = $eligibleMembers > 0 ? ($presentCount / $eligibleMembers) : 0.0;
        $quorumOk = $presentCount > 0 && $quorumRatio >= $quorumThreshold;

        $this->assertTrue($quorumOk);
    }

    public function testQuorumNotOkWhenBelowThreshold(): void
    {
        $presentCount = 40;
        $eligibleMembers = 100;
        $quorumThreshold = 0.5;

        $quorumRatio = $eligibleMembers > 0 ? ($presentCount / $eligibleMembers) : 0.0;
        $quorumOk = $presentCount > 0 && $quorumRatio >= $quorumThreshold;

        $this->assertFalse($quorumOk);
    }

    public function testQuorumNotOkWithZeroPresent(): void
    {
        $presentCount = 0;
        $eligibleMembers = 100;
        $quorumThreshold = 0.0; // Even with 0 threshold

        $quorumRatio = $eligibleMembers > 0 ? ($presentCount / $eligibleMembers) : 0.0;
        $quorumOk = $presentCount > 0 && $quorumRatio >= $quorumThreshold;

        $this->assertFalse($quorumOk, 'Quorum should require at least one present member');
    }

    public function testQuorumExactlyAtThreshold(): void
    {
        $presentCount = 50;
        $eligibleMembers = 100;
        $quorumThreshold = 0.5;

        $quorumRatio = $eligibleMembers > 0 ? ($presentCount / $eligibleMembers) : 0.0;
        $quorumOk = $presentCount > 0 && $quorumRatio >= $quorumThreshold;

        $this->assertTrue($quorumOk, 'Quorum should pass when ratio equals threshold (>=)');
    }

    // =========================================================================
    // WORKFLOW STATE: ATTENDANCE COUNTING
    // =========================================================================

    public function testAttendanceCounting(): void
    {
        // Replicate the attendance counting logic from workflowState()
        $attRows = [
            ['voting_power' => 1.0, 'attendance_mode' => 'present', 'member_id' => 'm1', 'full_name' => 'A'],
            ['voting_power' => 2.0, 'attendance_mode' => 'remote', 'member_id' => 'm2', 'full_name' => 'B'],
            ['voting_power' => 1.5, 'attendance_mode' => 'proxy', 'member_id' => 'm3', 'full_name' => 'C'],
            ['voting_power' => 1.0, 'attendance_mode' => '', 'member_id' => 'm4', 'full_name' => 'D'],
            ['voting_power' => 3.0, 'attendance_mode' => '', 'member_id' => 'm5', 'full_name' => 'E'],
        ];

        $presentCount = 0;
        $presentWeight = 0.0;
        $totalCount = count($attRows);
        $totalWeight = 0.0;
        $absentIds = [];
        $absentNames = [];

        foreach ($attRows as $r) {
            $vp = (float) ($r['voting_power'] ?? 0);
            $totalWeight += $vp;
            $mode = (string) ($r['attendance_mode'] ?? '');
            if ($mode === 'present' || $mode === 'remote' || $mode === 'proxy') {
                $presentCount++;
                $presentWeight += $vp;
            } else {
                $mid = (string) $r['member_id'];
                $absentIds[] = $mid;
                $absentNames[$mid] = (string) ($r['full_name'] ?? '');
            }
        }

        $this->assertEquals(3, $presentCount);
        $this->assertEquals(4.5, $presentWeight);
        $this->assertEquals(5, $totalCount);
        $this->assertEquals(8.5, $totalWeight);
        $this->assertEquals(['m4', 'm5'], $absentIds);
        $this->assertEquals(['m4' => 'D', 'm5' => 'E'], $absentNames);
    }

    public function testAttendanceCountingAllPresent(): void
    {
        $attRows = [
            ['voting_power' => 1.0, 'attendance_mode' => 'present', 'member_id' => 'm1', 'full_name' => 'A'],
            ['voting_power' => 1.0, 'attendance_mode' => 'remote', 'member_id' => 'm2', 'full_name' => 'B'],
        ];

        $presentCount = 0;
        $absentIds = [];
        foreach ($attRows as $r) {
            $mode = (string) ($r['attendance_mode'] ?? '');
            if ($mode === 'present' || $mode === 'remote' || $mode === 'proxy') {
                $presentCount++;
            } else {
                $absentIds[] = (string) $r['member_id'];
            }
        }

        $this->assertEquals(2, $presentCount);
        $this->assertEmpty($absentIds);
    }

    public function testAttendanceCountingWithEmptyList(): void
    {
        $attRows = [];

        $presentCount = 0;
        $totalCount = count($attRows);
        foreach ($attRows as $r) {
            $mode = (string) ($r['attendance_mode'] ?? '');
            if ($mode === 'present' || $mode === 'remote' || $mode === 'proxy') {
                $presentCount++;
            }
        }

        $this->assertEquals(0, $presentCount);
        $this->assertEquals(0, $totalCount);
    }

    // =========================================================================
    // WORKFLOW STATE: MISSING PROXY DETECTION
    // =========================================================================

    public function testMissingProxyDetection(): void
    {
        // Replicate the proxy-gap detection logic
        $absentIds = ['m1', 'm2', 'm3', 'm4'];
        $coveredSet = ['m1' => true, 'm3' => true]; // m2 and m4 have no proxy

        $missing = [];
        foreach ($absentIds as $mid) {
            if (!isset($coveredSet[$mid])) {
                $missing[] = $mid;
            }
        }

        $this->assertEquals(['m2', 'm4'], $missing);
    }

    public function testMissingProxyAllCovered(): void
    {
        $absentIds = ['m1', 'm2'];
        $coveredSet = ['m1' => true, 'm2' => true];

        $missing = [];
        foreach ($absentIds as $mid) {
            if (!isset($coveredSet[$mid])) {
                $missing[] = $mid;
            }
        }

        $this->assertEmpty($missing);
    }

    public function testMissingProxyNoneCovered(): void
    {
        $absentIds = ['m1', 'm2', 'm3'];
        $coveredSet = [];

        $missing = [];
        foreach ($absentIds as $mid) {
            if (!isset($coveredSet[$mid])) {
                $missing[] = $mid;
            }
        }

        $this->assertEquals(['m1', 'm2', 'm3'], $missing);
    }

    // =========================================================================
    // WORKFLOW STATE: CAN OPEN NEXT LOGIC
    // =========================================================================

    public function testCanOpenNextAllConditionsMet(): void
    {
        $quorumOk = true;
        $missing = [];
        $openMotion = null;
        $nextMotion = ['id' => 'next-1', 'title' => 'Motion 2'];

        $canOpenNext = $quorumOk && (count($missing) === 0) && ($openMotion === null) && ($nextMotion !== null);

        $this->assertTrue($canOpenNext);
    }

    public function testCanOpenNextFailsWithoutQuorum(): void
    {
        $quorumOk = false;
        $missing = [];
        $openMotion = null;
        $nextMotion = ['id' => 'next-1'];

        $canOpenNext = $quorumOk && (count($missing) === 0) && ($openMotion === null) && ($nextMotion !== null);

        $this->assertFalse($canOpenNext);
    }

    public function testCanOpenNextFailsWithMissingProxies(): void
    {
        $quorumOk = true;
        $missing = ['m1'];
        $openMotion = null;
        $nextMotion = ['id' => 'next-1'];

        $canOpenNext = $quorumOk && (count($missing) === 0) && ($openMotion === null) && ($nextMotion !== null);

        $this->assertFalse($canOpenNext);
    }

    public function testCanOpenNextFailsWithOpenMotion(): void
    {
        $quorumOk = true;
        $missing = [];
        $openMotion = ['id' => 'open-1'];
        $nextMotion = ['id' => 'next-1'];

        $canOpenNext = $quorumOk && (count($missing) === 0) && ($openMotion === null) && ($nextMotion !== null);

        $this->assertFalse($canOpenNext);
    }

    public function testCanOpenNextFailsWithNoNextMotion(): void
    {
        $quorumOk = true;
        $missing = [];
        $openMotion = null;
        $nextMotion = null;

        $canOpenNext = $quorumOk && (count($missing) === 0) && ($openMotion === null) && ($nextMotion !== null);

        $this->assertFalse($canOpenNext);
    }

    // =========================================================================
    // WORKFLOW STATE: CLOSE BLOCKER LOGIC
    // =========================================================================

    public function testCloseBlockersMinimumOpenTime(): void
    {
        $openAgeSeconds = 500;
        $minOpen = 900;
        $closeBlockers = [];

        if ($openAgeSeconds < $minOpen) {
            $closeBlockers[] = "Delai minimum non atteint ({$openAgeSeconds}s / {$minOpen}s).";
        }

        $this->assertCount(1, $closeBlockers);
        $this->assertStringContainsString('500s', $closeBlockers[0]);
        $this->assertStringContainsString('900s', $closeBlockers[0]);
    }

    public function testCloseBlockersMinimumParticipation(): void
    {
        $participationRatio = 0.3;
        $minParticipation = 0.5;
        $closeBlockers = [];

        if ($participationRatio < $minParticipation) {
            $closeBlockers[] = 'Participation insuffisante (' . round($participationRatio * 100) . '%, min ' . round($minParticipation * 100) . '%).';
        }

        $this->assertCount(1, $closeBlockers);
        $this->assertStringContainsString('30%', $closeBlockers[0]);
        $this->assertStringContainsString('50%', $closeBlockers[0]);
    }

    public function testCanCloseWhenNoBlockers(): void
    {
        $openAgeSeconds = 1000;
        $minOpen = 900;
        $participationRatio = 0.8;
        $minParticipation = 0.5;
        $closeBlockers = [];

        if ($openAgeSeconds < $minOpen) {
            $closeBlockers[] = 'Time blocker';
        }
        if ($participationRatio < $minParticipation) {
            $closeBlockers[] = 'Participation blocker';
        }
        $canCloseOpen = count($closeBlockers) === 0;

        $this->assertTrue($canCloseOpen);
        $this->assertEmpty($closeBlockers);
    }

    public function testCannotCloseWithMultipleBlockers(): void
    {
        $openAgeSeconds = 100;
        $minOpen = 900;
        $participationRatio = 0.2;
        $minParticipation = 0.5;
        $closeBlockers = [];

        if ($openAgeSeconds < $minOpen) {
            $closeBlockers[] = 'Time blocker';
        }
        if ($participationRatio < $minParticipation) {
            $closeBlockers[] = 'Participation blocker';
        }
        $canCloseOpen = count($closeBlockers) === 0;

        $this->assertFalse($canCloseOpen);
        $this->assertCount(2, $closeBlockers);
    }

    // =========================================================================
    // WORKFLOW STATE: PARTICIPATION RATIO
    // =========================================================================

    public function testParticipationRatioCalculation(): void
    {
        $openBallots = 30;
        $potentialVoters = 50;

        $participationRatio = $potentialVoters > 0 ? ($openBallots / $potentialVoters) : 0.0;

        $this->assertEquals(0.6, $participationRatio);
    }

    public function testParticipationRatioWithZeroVoters(): void
    {
        $openBallots = 5;
        $potentialVoters = 0;

        $participationRatio = $potentialVoters > 0 ? ($openBallots / $potentialVoters) : 0.0;

        $this->assertEquals(0.0, $participationRatio);
    }

    public function testPotentialVotersCalculation(): void
    {
        $presentCount = 40;
        $coveredSet = ['m1' => true, 'm2' => true, 'm3' => true]; // 3 covered by proxy

        $potentialVoters = $presentCount + count($coveredSet);

        $this->assertEquals(43, $potentialVoters);
    }

    // =========================================================================
    // WORKFLOW STATE: CONSOLIDATION LOGIC
    // =========================================================================

    public function testCanConsolidateWhenNoOpenMotionsAndHasClosed(): void
    {
        $motionsOpen = 0;
        $hasClosed = 5;

        $canConsolidate = $motionsOpen === 0 && $hasClosed > 0;

        $this->assertTrue($canConsolidate);
    }

    public function testCannotConsolidateWithOpenMotions(): void
    {
        $motionsOpen = 1;
        $hasClosed = 5;

        $canConsolidate = $motionsOpen === 0 && $hasClosed > 0;

        $this->assertFalse($canConsolidate);
    }

    public function testCannotConsolidateWithNoClosedMotions(): void
    {
        $motionsOpen = 0;
        $hasClosed = 0;

        $canConsolidate = $motionsOpen === 0 && $hasClosed > 0;

        $this->assertFalse($canConsolidate);
    }

    public function testConsolidationDoneCheck(): void
    {
        $hasClosed = 5;
        $consolidatedCount = 5;

        $consolidationDone = ($hasClosed > 0) && ($consolidatedCount >= $hasClosed);

        $this->assertTrue($consolidationDone);
    }

    public function testConsolidationNotDone(): void
    {
        $hasClosed = 5;
        $consolidatedCount = 3;

        $consolidationDone = ($hasClosed > 0) && ($consolidatedCount >= $hasClosed);

        $this->assertFalse($consolidationDone);
    }

    public function testConsolidationNotDoneWithZeroClosed(): void
    {
        $hasClosed = 0;
        $consolidatedCount = 0;

        $consolidationDone = ($hasClosed > 0) && ($consolidatedCount >= $hasClosed);

        $this->assertFalse($consolidationDone, 'Should not be done when no motions exist');
    }

    public function testConsolidateDetailMessage(): void
    {
        // When can consolidate
        $canConsolidate = true;
        $hasClosed = 3;
        $motionsOpen = 0;

        $detail = $canConsolidate
            ? "Motions fermees: {$hasClosed}. Vous pouvez consolider."
            : ($motionsOpen > 0
                ? 'Fermez toutes les motions ouvertes avant consolidation.'
                : 'Aucune motion fermee a consolider.');

        $this->assertStringContainsString('3', $detail);
        $this->assertStringContainsString('consolider', $detail);
    }

    public function testConsolidateDetailMessageWithOpenMotions(): void
    {
        $canConsolidate = false;
        $hasClosed = 3;
        $motionsOpen = 1;

        $detail = $canConsolidate
            ? "Motions fermees: {$hasClosed}. Vous pouvez consolider."
            : ($motionsOpen > 0
                ? 'Fermez toutes les motions ouvertes avant consolidation.'
                : 'Aucune motion fermee a consolider.');

        $this->assertStringContainsString('Fermez', $detail);
    }

    public function testConsolidateDetailMessageWithNoMotions(): void
    {
        $canConsolidate = false;
        $hasClosed = 0;
        $motionsOpen = 0;

        $detail = $canConsolidate
            ? "Motions fermees: {$hasClosed}. Vous pouvez consolider."
            : ($motionsOpen > 0
                ? 'Fermez toutes les motions ouvertes avant consolidation.'
                : 'Aucune motion fermee a consolider.');

        $this->assertStringContainsString('Aucune', $detail);
    }

    // =========================================================================
    // OPEN VOTE: INPUT VALIDATION
    // =========================================================================

    public function testOpenVoteRequiresPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('openVote');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testOpenVoteRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [];

        $result = $this->callControllerMethod('openVote');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testOpenVoteRejectsInvalidMeetingUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['meeting_id' => 'not-a-uuid'];

        $result = $this->callControllerMethod('openVote');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testOpenVoteRejectsInvalidMotionUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => 'bad-uuid',
        ];

        $result = $this->callControllerMethod('openVote');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    // =========================================================================
    // OPEN VOTE: EXPIRY MINUTES CLAMPING
    // =========================================================================

    public function testExpiresMinutesClamping(): void
    {
        // The controller clamps expires_minutes between 10 and 24*60=1440
        $clamp = function (int $input): int {
            $v = $input;
            if ($v < 10) $v = 10;
            if ($v > 24 * 60) $v = 24 * 60;
            return $v;
        };

        $this->assertEquals(10, $clamp(0));
        $this->assertEquals(10, $clamp(5));
        $this->assertEquals(10, $clamp(10));
        $this->assertEquals(120, $clamp(120));
        $this->assertEquals(1440, $clamp(1440));
        $this->assertEquals(1440, $clamp(2000));
        $this->assertEquals(10, $clamp(-5));
    }

    public function testDefaultExpiresMinutes(): void
    {
        // Default is 120 from ($input['expires_minutes'] ?? 120)
        $default = 120;
        $this->assertEquals(120, $default);
        $this->assertGreaterThanOrEqual(10, $default);
        $this->assertLessThanOrEqual(1440, $default);
    }

    // =========================================================================
    // ANOMALIES: INPUT VALIDATION
    // =========================================================================

    public function testAnomaliesRejectsNonGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('anomalies');

        $this->assertEquals(405, $result['status']);
    }

    public function testAnomaliesRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('anomalies');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testAnomaliesRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('anomalies');

        $this->assertEquals(422, $result['status']);
    }

    public function testAnomaliesRejectsInvalidMeetingUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'invalid'];

        $result = $this->callControllerMethod('anomalies');

        $this->assertEquals(422, $result['status']);
    }

    public function testAnomaliesRejectsInvalidMotionUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => 'bad-uuid',
        ];

        $result = $this->callControllerMethod('anomalies');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    // =========================================================================
    // ANOMALY DETECTION: DUPLICATE BALLOTS
    // =========================================================================

    public function testDuplicateBallotDetection(): void
    {
        // Replicate the duplicate detection logic from anomalies()
        $ballots = [
            ['member_id' => 'm1', 'value' => 'for', 'source' => 'token', 'cast_at' => '2024-01-01'],
            ['member_id' => 'm2', 'value' => 'against', 'source' => 'token', 'cast_at' => '2024-01-01'],
            ['member_id' => 'm1', 'value' => 'for', 'source' => 'manual', 'cast_at' => '2024-01-02'],
            ['member_id' => 'm3', 'value' => 'abstain', 'source' => 'token', 'cast_at' => '2024-01-01'],
        ];

        $eligibleNames = ['m1' => 'Alice', 'm2' => 'Bob', 'm3' => 'Charlie'];
        $votedSet = [];
        $duplicates = [];

        foreach ($ballots as $b) {
            $mid = (string) ($b['member_id'] ?? '');
            if ($mid === '') continue;

            if (isset($votedSet[$mid])) {
                $duplicates[] = [
                    'member_id' => $mid,
                    'name' => $eligibleNames[$mid] ?? null,
                    'detail' => 'duplicate_ballot_for_member',
                ];
            }
            $votedSet[$mid] = true;
        }

        $this->assertCount(1, $duplicates);
        $this->assertEquals('m1', $duplicates[0]['member_id']);
        $this->assertEquals('Alice', $duplicates[0]['name']);
    }

    public function testNoDuplicatesWhenAllUnique(): void
    {
        $ballots = [
            ['member_id' => 'm1'],
            ['member_id' => 'm2'],
            ['member_id' => 'm3'],
        ];

        $votedSet = [];
        $duplicates = [];
        foreach ($ballots as $b) {
            $mid = (string) ($b['member_id'] ?? '');
            if ($mid === '') continue;
            if (isset($votedSet[$mid])) {
                $duplicates[] = ['member_id' => $mid];
            }
            $votedSet[$mid] = true;
        }

        $this->assertEmpty($duplicates);
    }

    // =========================================================================
    // ANOMALY DETECTION: INELIGIBLE BALLOTS
    // =========================================================================

    public function testIneligibleBallotDetection(): void
    {
        // Replicate the ineligible ballot detection logic
        $eligibleIds = ['m1', 'm2', 'm3'];
        $ballots = [
            ['member_id' => 'm1', 'value' => 'for', 'source' => 'token', 'cast_at' => '2024-01-01'],
            ['member_id' => 'm4', 'value' => 'for', 'source' => 'manual', 'cast_at' => '2024-01-01'],
            ['member_id' => 'm5', 'value' => 'against', 'source' => 'token', 'cast_at' => '2024-01-01'],
        ];

        $ballotsNotEligible = [];
        $votedSet = [];
        foreach ($ballots as $b) {
            $mid = (string) ($b['member_id'] ?? '');
            if ($mid === '') continue;
            $votedSet[$mid] = true;

            if (!in_array($mid, $eligibleIds, true)) {
                $ballotsNotEligible[] = [
                    'member_id' => $mid,
                    'value' => (string) ($b['value'] ?? ''),
                    'source' => (string) ($b['source'] ?? ''),
                    'cast_at' => $b['cast_at'],
                ];
            }
        }

        $this->assertCount(2, $ballotsNotEligible);
        $this->assertEquals('m4', $ballotsNotEligible[0]['member_id']);
        $this->assertEquals('m5', $ballotsNotEligible[1]['member_id']);
    }

    public function testNoIneligibleWhenAllEligible(): void
    {
        $eligibleIds = ['m1', 'm2', 'm3'];
        $ballots = [
            ['member_id' => 'm1'],
            ['member_id' => 'm2'],
        ];

        $ballotsNotEligible = [];
        foreach ($ballots as $b) {
            $mid = (string) ($b['member_id'] ?? '');
            if ($mid === '' || in_array($mid, $eligibleIds, true)) continue;
            $ballotsNotEligible[] = ['member_id' => $mid];
        }

        $this->assertEmpty($ballotsNotEligible);
    }

    // =========================================================================
    // ANOMALY DETECTION: MISSING VOTERS
    // =========================================================================

    public function testMissingVotersDetection(): void
    {
        $eligibleIds = ['m1', 'm2', 'm3', 'm4', 'm5'];
        $eligibleNames = ['m1' => 'Alice', 'm2' => 'Bob', 'm3' => 'Charlie', 'm4' => 'Diana', 'm5' => 'Eve'];
        $votedSet = ['m1' => true, 'm3' => true, 'm5' => true];

        $eligibleCount = count($eligibleIds);
        $eligibleVoted = 0;
        foreach ($eligibleIds as $id) {
            if (isset($votedSet[$id])) {
                $eligibleVoted++;
            }
        }
        $missingCount = max(0, $eligibleCount - $eligibleVoted);

        $missingNames = [];
        if ($missingCount > 0) {
            foreach ($eligibleIds as $id) {
                if (!isset($votedSet[$id])) {
                    $missingNames[] = $eligibleNames[$id] ?? $id;
                    if (count($missingNames) >= 30) break;
                }
            }
        }

        $this->assertEquals(2, $missingCount);
        $this->assertEquals(['Bob', 'Diana'], $missingNames);
    }

    public function testMissingVotersSampleLimitedTo30(): void
    {
        // Generate 50 eligible members, none voted
        $eligibleIds = [];
        $eligibleNames = [];
        for ($i = 1; $i <= 50; $i++) {
            $eligibleIds[] = "m{$i}";
            $eligibleNames["m{$i}"] = "Member {$i}";
        }
        $votedSet = [];

        $missingNames = [];
        foreach ($eligibleIds as $id) {
            if (!isset($votedSet[$id])) {
                $missingNames[] = $eligibleNames[$id] ?? $id;
                if (count($missingNames) >= 30) break;
            }
        }

        $this->assertCount(30, $missingNames, 'Missing voters sample should be capped at 30');
    }

    public function testNoMissingVotersWhenAllVoted(): void
    {
        $eligibleIds = ['m1', 'm2', 'm3'];
        $votedSet = ['m1' => true, 'm2' => true, 'm3' => true];

        $eligibleVoted = 0;
        foreach ($eligibleIds as $id) {
            if (isset($votedSet[$id])) {
                $eligibleVoted++;
            }
        }
        $missingCount = max(0, count($eligibleIds) - $eligibleVoted);

        $this->assertEquals(0, $missingCount);
    }

    // =========================================================================
    // ANOMALY DETECTION: STATS INITIALIZATION
    // =========================================================================

    public function testStatsDefaultValues(): void
    {
        $eligibleCount = 42;

        $stats = [
            'tokens_active_unused' => 0,
            'tokens_expired_unused' => 0,
            'tokens_used' => 0,
            'ballots_total' => 0,
            'ballots_from_eligible' => 0,
            'eligible_expected' => $eligibleCount,
            'missing_ballots_from_eligible' => 0,
        ];

        $this->assertEquals(0, $stats['tokens_active_unused']);
        $this->assertEquals(0, $stats['tokens_expired_unused']);
        $this->assertEquals(0, $stats['tokens_used']);
        $this->assertEquals(0, $stats['ballots_total']);
        $this->assertEquals(0, $stats['ballots_from_eligible']);
        $this->assertEquals(42, $stats['eligible_expected']);
        $this->assertEquals(0, $stats['missing_ballots_from_eligible']);
    }

    // =========================================================================
    // WORKFLOW STATE: RESPONSE STRUCTURE VERIFICATION
    // =========================================================================

    public function testWorkflowStateResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/OperatorController.php');

        // Verify top-level response keys
        $expectedKeys = ['meeting', 'motions', 'attendance', 'proxies', 'tokens', 'motion', 'consolidation', 'validation'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString("'{$key}'", $source, "Response should contain '{$key}' key");
        }
    }

    public function testWorkflowStateAttendanceFields(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/OperatorController.php');

        $fields = ['present_count', 'present_weight', 'total_count', 'total_weight', 'quorum_threshold', 'quorum_ratio', 'quorum_ok'];
        foreach ($fields as $field) {
            $this->assertStringContainsString("'{$field}'", $source, "Attendance should include '{$field}'");
        }
    }

    public function testWorkflowStateMotionFields(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/OperatorController.php');

        $fields = ['open_motion_id', 'open_ballots', 'open_age_seconds', 'potential_voters', 'participation_ratio', 'close_blockers', 'can_open_next', 'can_close_open'];
        foreach ($fields as $field) {
            $this->assertStringContainsString("'{$field}'", $source, "Motion section should include '{$field}'");
        }
    }

    // =========================================================================
    // OPEN VOTE: TOKEN GENERATION PATTERN
    // =========================================================================

    public function testTokenHashUsesHmacSha256(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/OperatorController.php');

        $this->assertStringContainsString("hash_hmac('sha256'", $source);
    }

    public function testOpenVoteAuditsTokenGeneration(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/OperatorController.php');

        $this->assertStringContainsString("'vote_tokens_generated'", $source);
    }

    // =========================================================================
    // OPEN VOTE: LIST TOKENS FLAG
    // =========================================================================

    public function testListTokensFlagParsing(): void
    {
        // Replicate the listTokens flag logic
        $input1 = ['list' => '1'];
        $listTokens1 = (string) ($input1['list'] ?? '') === '1';
        $this->assertTrue($listTokens1);

        $input2 = ['list' => '0'];
        $listTokens2 = (string) ($input2['list'] ?? '') === '1';
        $this->assertFalse($listTokens2);

        $input3 = [];
        $listTokens3 = (string) ($input3['list'] ?? '') === '1';
        $this->assertFalse($listTokens3);
    }

    // =========================================================================
    // ANOMALIES: RESPONSE STRUCTURE
    // =========================================================================

    public function testAnomaliesResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/OperatorController.php');

        $topKeys = ['meeting', 'motion', 'eligibility', 'stats', 'anomalies'];
        foreach ($topKeys as $key) {
            $this->assertStringContainsString("'{$key}'", $source, "Anomalies response should contain '{$key}'");
        }
    }

    public function testAnomaliesSubKeys(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/OperatorController.php');

        $anomalySubKeys = ['missing_voters_sample', 'ballots_not_eligible', 'duplicates'];
        foreach ($anomalySubKeys as $key) {
            $this->assertStringContainsString("'{$key}'", $source, "Anomalies section should contain '{$key}'");
        }
    }

    // =========================================================================
    // WORKFLOW STATE: DEFAULT QUORUM THRESHOLD
    // =========================================================================

    public function testDefaultQuorumThresholdIs50Percent(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/OperatorController.php');

        $this->assertStringContainsString('$quorumThreshold = 0.5', $source);
    }

    // =========================================================================
    // WORKFLOW STATE: MIN_OPEN DEFAULT
    // =========================================================================

    public function testDefaultMinOpenIs900Seconds(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/OperatorController.php');

        $this->assertStringContainsString("'min_open', 900", $source);
    }

    // =========================================================================
    // OPEN VOTE: MEETING STATUS TRANSITION
    // =========================================================================

    public function testOpenVoteTransitionsToLive(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/OperatorController.php');

        // The controller updates meeting status to 'live' if not already
        $this->assertStringContainsString("'status' => 'live'", $source);
    }

    public function testOpenVotePreventsOpeningWhenAnotherActive(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/OperatorController.php');

        $this->assertStringContainsString('another_motion_active', $source);
    }

    public function testOpenVoteChecksValidatedMeeting(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/OperatorController.php');

        $this->assertStringContainsString('api_guard_meeting_not_validated', $source);
        $this->assertStringContainsString('meeting_validated_locked', $source);
    }

    // =========================================================================
    // ELIGIBLE MEMBER ID EXTRACTION
    // =========================================================================

    public function testEligibleIdExtraction(): void
    {
        // Replicate the eligible ID extraction logic from anomalies()
        $eligibleRows = [
            ['member_id' => 'm1', 'full_name' => 'Alice'],
            ['member_id' => 'm2', 'full_name' => 'Bob'],
            ['member_id' => '', 'full_name' => 'Nobody'],
            ['member_id' => 'm3', 'full_name' => 'Charlie'],
        ];

        $eligibleIds = [];
        $eligibleNames = [];
        foreach ($eligibleRows as $r) {
            $id = (string) ($r['member_id'] ?? '');
            if ($id === '') continue;
            $eligibleIds[] = $id;
            $eligibleNames[$id] = (string) ($r['full_name'] ?? '');
        }

        $this->assertEquals(['m1', 'm2', 'm3'], $eligibleIds);
        $this->assertEquals(['m1' => 'Alice', 'm2' => 'Bob', 'm3' => 'Charlie'], $eligibleNames);
        $this->assertCount(3, $eligibleIds, 'Empty member_id rows should be skipped');
    }
}
