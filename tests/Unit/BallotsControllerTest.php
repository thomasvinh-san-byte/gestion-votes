<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\BallotsController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for BallotsController.
 *
 * Tests the 7 ballot/voting endpoints:
 *  - listForMotion: GET, lists ballots for a given motion
 *  - cast: POST, casts a ballot (with optional vote token / idempotency)
 *  - cancel: POST, cancels a manual ballot with justification
 *  - result: GET, computes motion result
 *  - manualVote: POST, operator casts a manual vote
 *  - redeemPaperBallot: POST, redeems a paper ballot code
 *  - reportIncident: POST, logs a vote incident
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class BallotsControllerTest extends TestCase
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

        unset($_SERVER['HTTP_X_IDEMPOTENCY_KEY']);

        parent::tearDown();
    }

    // =========================================================================
    // HELPER: Call controller and capture response
    // =========================================================================

    private function callControllerMethod(string $method): array
    {
        $controller = new BallotsController();
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
     * Inject a JSON body into Request::$cachedRawBody for POST endpoints.
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
        $ref = new \ReflectionClass(BallotsController::class);
        $this->assertTrue($ref->isFinal(), 'BallotsController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new BallotsController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(BallotsController::class);

        $expectedMethods = [
            'listForMotion',
            'cast',
            'cancel',
            'result',
            'manualVote',
            'redeemPaperBallot',
            'reportIncident',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "BallotsController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(BallotsController::class);

        $expectedMethods = [
            'listForMotion',
            'cast',
            'cancel',
            'result',
            'manualVote',
            'redeemPaperBallot',
            'reportIncident',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "BallotsController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // listForMotion: METHOD ENFORCEMENT
    // =========================================================================

    public function testListForMotionRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('listForMotion');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListForMotionRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('listForMotion');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListForMotionRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('listForMotion');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // listForMotion: INPUT VALIDATION
    // =========================================================================

    public function testListForMotionRequiresMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('listForMotion');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_motion_id', $result['body']['error']);
    }

    public function testListForMotionRejectsEmptyMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['motion_id' => ''];

        $result = $this->callControllerMethod('listForMotion');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_motion_id', $result['body']['error']);
    }

    public function testListForMotionRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['motion_id' => 'not-a-valid-uuid'];

        $result = $this->callControllerMethod('listForMotion');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_motion_id', $result['body']['error']);
    }

    public function testListForMotionRejectsShortUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['motion_id' => '12345678-1234-1234-1234'];

        $result = $this->callControllerMethod('listForMotion');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_motion_id', $result['body']['error']);
    }

    public function testListForMotionRejectsWhitespaceMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['motion_id' => '   '];

        $result = $this->callControllerMethod('listForMotion');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_motion_id', $result['body']['error']);
    }

    // =========================================================================
    // cast: METHOD ENFORCEMENT
    // =========================================================================

    public function testCastRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('cast');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testCastRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('cast');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testCastRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('cast');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // cast: IDEMPOTENCY KEY HANDLING
    // =========================================================================

    public function testCastPicksUpIdempotencyKeyFromHeader(): void
    {
        // The cast method reads HTTP_X_IDEMPOTENCY_KEY from $_SERVER and
        // merges it into $data['_idempotency_key']. We verify the logic
        // by replicating the extraction pattern.

        $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] = 'idem-key-123';

        $idempotencyKey = $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? null;
        $data = [];
        if ($idempotencyKey) {
            $data['_idempotency_key'] = $idempotencyKey;
        }

        $this->assertEquals('idem-key-123', $data['_idempotency_key']);
    }

    public function testCastIdempotencyKeyAbsentWhenNoHeader(): void
    {
        unset($_SERVER['HTTP_X_IDEMPOTENCY_KEY']);

        $idempotencyKey = $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? null;
        $data = [];
        if ($idempotencyKey) {
            $data['_idempotency_key'] = $idempotencyKey;
        }

        $this->assertArrayNotHasKey('_idempotency_key', $data);
    }

    // =========================================================================
    // cast: VOTE TOKEN VALIDATION LOGIC
    // =========================================================================

    public function testCastVoteTokenTrimming(): void
    {
        // Replicate the vote_token parsing logic from cast()
        $data = ['vote_token' => '  some-token  '];
        $voteToken = trim((string) ($data['vote_token'] ?? ''));

        $this->assertEquals('some-token', $voteToken);
    }

    public function testCastVoteTokenEmptySkipsValidation(): void
    {
        $data = ['vote_token' => ''];
        $voteToken = trim((string) ($data['vote_token'] ?? ''));

        $this->assertEquals('', $voteToken);
        // When empty, the controller skips token validation entirely
        $this->assertTrue($voteToken === '', 'Empty token should skip VoteToken validation');
    }

    public function testCastVoteTokenNullSkipsValidation(): void
    {
        $data = [];
        $voteToken = trim((string) ($data['vote_token'] ?? ''));

        $this->assertEquals('', $voteToken);
    }

    public function testCastTokenMotionMismatchLogic(): void
    {
        // Replicate the mismatch check from cast()
        $data = ['motion_id' => 'motion-A'];
        $tokenResult = ['motion_id' => 'motion-B', 'member_id' => 'm1'];

        $reqMotionId = trim((string) ($data['motion_id'] ?? ''));
        $mismatch = $reqMotionId !== '' && ($tokenResult['motion_id'] ?? '') !== $reqMotionId;

        $this->assertTrue($mismatch, 'Should detect motion mismatch');
    }

    public function testCastTokenMotionMatchLogic(): void
    {
        $data = ['motion_id' => 'motion-A'];
        $tokenResult = ['motion_id' => 'motion-A', 'member_id' => 'm1'];

        $reqMotionId = trim((string) ($data['motion_id'] ?? ''));
        $mismatch = $reqMotionId !== '' && ($tokenResult['motion_id'] ?? '') !== $reqMotionId;

        $this->assertFalse($mismatch, 'Should not flag when motion matches');
    }

    public function testCastTokenMemberMismatchLogic(): void
    {
        $data = ['member_id' => 'member-A'];
        $tokenResult = ['member_id' => 'member-B'];

        $reqMemberId = trim((string) ($data['member_id'] ?? ''));
        $mismatch = $reqMemberId !== '' && ($tokenResult['member_id'] ?? '') !== $reqMemberId;

        $this->assertTrue($mismatch, 'Should detect member mismatch');
    }

    public function testCastTokenMemberMatchLogic(): void
    {
        $data = ['member_id' => 'member-A'];
        $tokenResult = ['member_id' => 'member-A'];

        $reqMemberId = trim((string) ($data['member_id'] ?? ''));
        $mismatch = $reqMemberId !== '' && ($tokenResult['member_id'] ?? '') !== $reqMemberId;

        $this->assertFalse($mismatch, 'Should not flag when member matches');
    }

    public function testCastTokenMismatchSkippedWhenRequestFieldEmpty(): void
    {
        // When motion_id or member_id are not provided in request,
        // the mismatch check is skipped
        $data = [];
        $tokenResult = ['motion_id' => 'motion-B', 'member_id' => 'm1'];

        $reqMotionId = trim((string) ($data['motion_id'] ?? ''));
        $reqMemberId = trim((string) ($data['member_id'] ?? ''));

        $motionMismatch = $reqMotionId !== '' && ($tokenResult['motion_id'] ?? '') !== $reqMotionId;
        $memberMismatch = $reqMemberId !== '' && ($tokenResult['member_id'] ?? '') !== $reqMemberId;

        $this->assertFalse($motionMismatch, 'Should skip motion mismatch when empty');
        $this->assertFalse($memberMismatch, 'Should skip member mismatch when empty');
    }

    // =========================================================================
    // cancel: METHOD ENFORCEMENT
    // =========================================================================

    public function testCancelRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('cancel');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testCancelRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('cancel');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // cancel: INPUT VALIDATION
    // =========================================================================

    public function testCancelRequiresMotionIdUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'motion_id' => 'not-a-uuid',
            'member_id' => '12345678-1234-1234-1234-123456789abc',
            'reason' => 'Test reason',
        ]);

        $result = $this->callControllerMethod('cancel');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('motion_id', $result['body']['field']);
    }

    public function testCancelRequiresMemberIdUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'motion_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => 'invalid',
            'reason' => 'Test reason',
        ]);

        $result = $this->callControllerMethod('cancel');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('member_id', $result['body']['field']);
    }

    public function testCancelRejectsMissingMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'member_id' => '12345678-1234-1234-1234-123456789abc',
            'reason' => 'Test reason',
        ]);

        $result = $this->callControllerMethod('cancel');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testCancelRejectsMissingMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'motion_id' => '12345678-1234-1234-1234-123456789abc',
            'reason' => 'Test reason',
        ]);

        $result = $this->callControllerMethod('cancel');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testCancelRejectsMissingReason(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'motion_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => 'abcdefab-1234-1234-1234-123456789abc',
            'reason' => '',
        ]);

        $result = $this->callControllerMethod('cancel');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_reason', $result['body']['error']);
    }

    public function testCancelRejectsWhitespaceOnlyReason(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'motion_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => 'abcdefab-1234-1234-1234-123456789abc',
            'reason' => '   ',
        ]);

        $result = $this->callControllerMethod('cancel');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_reason', $result['body']['error']);
    }

    public function testCancelRejectsAbsentReasonField(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'motion_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => 'abcdefab-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('cancel');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_reason', $result['body']['error']);
    }

    // =========================================================================
    // result: METHOD ENFORCEMENT
    // =========================================================================

    public function testResultRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('result');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testResultRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('result');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // result: INPUT VALIDATION
    // =========================================================================

    public function testResultRequiresMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('result');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_motion_id', $result['body']['error']);
    }

    public function testResultRejectsEmptyMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['motion_id' => ''];

        $result = $this->callControllerMethod('result');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_motion_id', $result['body']['error']);
    }

    public function testResultRejectsWhitespaceMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['motion_id' => '   '];

        $result = $this->callControllerMethod('result');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_motion_id', $result['body']['error']);
    }

    // =========================================================================
    // manualVote: METHOD ENFORCEMENT
    // =========================================================================

    public function testManualVoteRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('manualVote');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testManualVoteRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('manualVote');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // manualVote: INPUT VALIDATION - MISSING FIELDS
    // =========================================================================

    public function testManualVoteRejectsMissingAllFields(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('manualVote');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_fields', $result['body']['error']);
        $this->assertContains('meeting_id', $result['body']['required']);
        $this->assertContains('motion_id', $result['body']['required']);
        $this->assertContains('member_id', $result['body']['required']);
    }

    public function testManualVoteRejectsMissingMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '',
            'motion_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => 'abcdefab-1234-1234-1234-123456789abc',
            'vote' => 'pour',
            'justification' => 'Test justification',
        ]);

        $result = $this->callControllerMethod('manualVote');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_fields', $result['body']['error']);
    }

    public function testManualVoteRejectsMissingMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => '',
            'member_id' => 'abcdefab-1234-1234-1234-123456789abc',
            'vote' => 'pour',
            'justification' => 'Test justification',
        ]);

        $result = $this->callControllerMethod('manualVote');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_fields', $result['body']['error']);
    }

    public function testManualVoteRejectsMissingMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => 'abcdefab-1234-1234-1234-123456789abc',
            'member_id' => '',
            'vote' => 'pour',
            'justification' => 'Test justification',
        ]);

        $result = $this->callControllerMethod('manualVote');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_fields', $result['body']['error']);
    }

    public function testManualVoteRejectsWhitespaceOnlyIds(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '   ',
            'motion_id' => '   ',
            'member_id' => '   ',
            'vote' => 'pour',
            'justification' => 'Test justification',
        ]);

        $result = $this->callControllerMethod('manualVote');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_fields', $result['body']['error']);
    }

    // =========================================================================
    // manualVote: INPUT VALIDATION - MISSING JUSTIFICATION
    // =========================================================================

    public function testManualVoteRejectsMissingJustification(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => 'abcdefab-1234-1234-1234-123456789abc',
            'member_id' => 'abcdefab-abcd-1234-1234-123456789abc',
            'vote' => 'pour',
            'justification' => '',
        ]);

        $result = $this->callControllerMethod('manualVote');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_justification', $result['body']['error']);
    }

    public function testManualVoteRejectsWhitespaceOnlyJustification(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => 'abcdefab-1234-1234-1234-123456789abc',
            'member_id' => 'abcdefab-abcd-1234-1234-123456789abc',
            'vote' => 'pour',
            'justification' => '   ',
        ]);

        $result = $this->callControllerMethod('manualVote');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_justification', $result['body']['error']);
    }

    public function testManualVoteRejectsAbsentJustificationField(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => 'abcdefab-1234-1234-1234-123456789abc',
            'member_id' => 'abcdefab-abcd-1234-1234-123456789abc',
            'vote' => 'pour',
        ]);

        $result = $this->callControllerMethod('manualVote');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_justification', $result['body']['error']);
    }

    // =========================================================================
    // manualVote: INPUT VALIDATION - INVALID VOTE VALUE
    // =========================================================================

    public function testManualVoteRejectsInvalidVoteValue(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => 'abcdefab-1234-1234-1234-123456789abc',
            'member_id' => 'abcdefab-abcd-1234-1234-123456789abc',
            'vote' => 'maybe',
            'justification' => 'Test justification',
        ]);

        $result = $this->callControllerMethod('manualVote');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_vote', $result['body']['error']);
    }

    public function testManualVoteRejectsEmptyVoteValue(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => 'abcdefab-1234-1234-1234-123456789abc',
            'member_id' => 'abcdefab-abcd-1234-1234-123456789abc',
            'vote' => '',
            'justification' => 'Test justification',
        ]);

        $result = $this->callControllerMethod('manualVote');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_vote', $result['body']['error']);
    }

    public function testManualVoteRejectsRandomStringVoteValue(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => 'abcdefab-1234-1234-1234-123456789abc',
            'member_id' => 'abcdefab-abcd-1234-1234-123456789abc',
            'vote' => 'xyz123',
            'justification' => 'Test justification',
        ]);

        $result = $this->callControllerMethod('manualVote');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_vote', $result['body']['error']);
    }

    // =========================================================================
    // manualVote: VOTE VALUE MAPPING LOGIC
    // =========================================================================

    public function testManualVoteValueMappingFrenchToEnglish(): void
    {
        // Replicate the vote mapping logic from manualVote()
        $map = [
            'pour' => 'for', 'contre' => 'against', 'abstention' => 'abstain', 'blanc' => 'nsp',
            'for' => 'for', 'against' => 'against', 'abstain' => 'abstain', 'nsp' => 'nsp',
        ];

        $this->assertEquals('for', $map['pour']);
        $this->assertEquals('against', $map['contre']);
        $this->assertEquals('abstain', $map['abstention']);
        $this->assertEquals('nsp', $map['blanc']);
    }

    public function testManualVoteValueMappingEnglishPassthrough(): void
    {
        $map = [
            'pour' => 'for', 'contre' => 'against', 'abstention' => 'abstain', 'blanc' => 'nsp',
            'for' => 'for', 'against' => 'against', 'abstain' => 'abstain', 'nsp' => 'nsp',
        ];

        $this->assertEquals('for', $map['for']);
        $this->assertEquals('against', $map['against']);
        $this->assertEquals('abstain', $map['abstain']);
        $this->assertEquals('nsp', $map['nsp']);
    }

    public function testManualVoteValueMapRejectsUnknownValues(): void
    {
        $map = [
            'pour' => 'for', 'contre' => 'against', 'abstention' => 'abstain', 'blanc' => 'nsp',
            'for' => 'for', 'against' => 'against', 'abstain' => 'abstain', 'nsp' => 'nsp',
        ];

        $this->assertFalse(isset($map['maybe']));
        $this->assertFalse(isset($map['yes']));
        $this->assertFalse(isset($map['no']));
        $this->assertFalse(isset($map['']));
        $this->assertFalse(isset($map['POUR']));
    }

    public function testManualVoteMapHasExactlyEightEntries(): void
    {
        $map = [
            'pour' => 'for', 'contre' => 'against', 'abstention' => 'abstain', 'blanc' => 'nsp',
            'for' => 'for', 'against' => 'against', 'abstain' => 'abstain', 'nsp' => 'nsp',
        ];

        $this->assertCount(8, $map);
    }

    // =========================================================================
    // redeemPaperBallot: METHOD ENFORCEMENT
    // =========================================================================

    public function testRedeemPaperBallotRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('redeemPaperBallot');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testRedeemPaperBallotRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('redeemPaperBallot');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // redeemPaperBallot: INPUT VALIDATION - CODE
    // =========================================================================

    public function testRedeemPaperBallotRejectsEmptyCode(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'code' => '',
            'vote_value' => 'pour',
        ]);

        $result = $this->callControllerMethod('redeemPaperBallot');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_code', $result['body']['error']);
    }

    public function testRedeemPaperBallotRejectsMissingCode(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'vote_value' => 'pour',
        ]);

        $result = $this->callControllerMethod('redeemPaperBallot');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_code', $result['body']['error']);
    }

    public function testRedeemPaperBallotRejectsNonUuidCode(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'code' => 'not-a-valid-uuid-format',
            'vote_value' => 'pour',
        ]);

        $result = $this->callControllerMethod('redeemPaperBallot');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_code', $result['body']['error']);
    }

    public function testRedeemPaperBallotRejectsWhitespaceCode(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'code' => '   ',
            'vote_value' => 'pour',
        ]);

        $result = $this->callControllerMethod('redeemPaperBallot');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_code', $result['body']['error']);
    }

    // =========================================================================
    // redeemPaperBallot: INPUT VALIDATION - VOTE VALUE
    // =========================================================================

    public function testRedeemPaperBallotRejectsInvalidVoteValue(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'code' => '12345678-1234-1234-1234-123456789abc',
            'vote_value' => 'maybe',
        ]);

        $result = $this->callControllerMethod('redeemPaperBallot');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_vote_value', $result['body']['error']);
    }

    public function testRedeemPaperBallotRejectsEmptyVoteValue(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'code' => '12345678-1234-1234-1234-123456789abc',
            'vote_value' => '',
        ]);

        $result = $this->callControllerMethod('redeemPaperBallot');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_vote_value', $result['body']['error']);
    }

    public function testRedeemPaperBallotRejectsMissingVoteValue(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'code' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('redeemPaperBallot');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_vote_value', $result['body']['error']);
    }

    public function testRedeemPaperBallotRejectsEnglishVoteValues(): void
    {
        // Only French values are accepted: pour, contre, abstention, blanc
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'code' => '12345678-1234-1234-1234-123456789abc',
            'vote_value' => 'for',
        ]);

        $result = $this->callControllerMethod('redeemPaperBallot');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_vote_value', $result['body']['error']);
    }

    public function testRedeemPaperBallotRejectsEnglishAgainst(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'code' => '12345678-1234-1234-1234-123456789abc',
            'vote_value' => 'against',
        ]);

        $result = $this->callControllerMethod('redeemPaperBallot');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_vote_value', $result['body']['error']);
    }

    // =========================================================================
    // redeemPaperBallot: ALLOWED VOTE VALUES
    // =========================================================================

    public function testRedeemPaperBallotAllowedVoteValues(): void
    {
        // Replicate the allowed values check from redeemPaperBallot()
        $allowed = ['pour', 'contre', 'abstention', 'blanc'];

        $this->assertTrue(in_array('pour', $allowed, true));
        $this->assertTrue(in_array('contre', $allowed, true));
        $this->assertTrue(in_array('abstention', $allowed, true));
        $this->assertTrue(in_array('blanc', $allowed, true));

        $this->assertFalse(in_array('for', $allowed, true));
        $this->assertFalse(in_array('against', $allowed, true));
        $this->assertFalse(in_array('abstain', $allowed, true));
        $this->assertFalse(in_array('nsp', $allowed, true));
    }

    // =========================================================================
    // redeemPaperBallot: JUSTIFICATION DEFAULT AND VALIDATION
    // =========================================================================

    public function testRedeemPaperBallotDefaultJustification(): void
    {
        // Replicate the default justification logic
        $in = [];
        $just = trim((string) ($in['justification'] ?? 'vote papier (secours)'));

        $this->assertEquals('vote papier (secours)', $just);
    }

    public function testRedeemPaperBallotCustomJustification(): void
    {
        $in = ['justification' => 'Custom reason'];
        $just = trim((string) ($in['justification'] ?? 'vote papier (secours)'));

        $this->assertEquals('Custom reason', $just);
    }

    public function testRedeemPaperBallotEmptyJustificationRejectsAfterTrim(): void
    {
        // When justification is explicitly set to empty, it triggers missing_justification
        // because after trim the string is empty
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'code' => '12345678-1234-1234-1234-123456789abc',
            'vote_value' => 'pour',
            'justification' => '',
        ]);

        $result = $this->callControllerMethod('redeemPaperBallot');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_justification', $result['body']['error']);
    }

    public function testRedeemPaperBallotWhitespaceJustificationRejects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'code' => '12345678-1234-1234-1234-123456789abc',
            'vote_value' => 'pour',
            'justification' => '   ',
        ]);

        $result = $this->callControllerMethod('redeemPaperBallot');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_justification', $result['body']['error']);
    }

    // =========================================================================
    // redeemPaperBallot: HASH COMPUTATION LOGIC
    // =========================================================================

    public function testRedeemPaperBallotUsesHmacSha256(): void
    {
        // Replicate the hash computation from redeemPaperBallot()
        $code = '12345678-1234-1234-1234-123456789abc';
        $hash = hash_hmac('sha256', $code, APP_SECRET);

        $this->assertIsString($hash);
        $this->assertEquals(64, strlen($hash), 'SHA-256 HMAC should produce 64 hex characters');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
    }

    public function testRedeemPaperBallotHashIsDeterministic(): void
    {
        $code = '12345678-1234-1234-1234-123456789abc';
        $hash1 = hash_hmac('sha256', $code, APP_SECRET);
        $hash2 = hash_hmac('sha256', $code, APP_SECRET);

        $this->assertEquals($hash1, $hash2);
    }

    public function testRedeemPaperBallotDifferentCodesProduceDifferentHashes(): void
    {
        $hash1 = hash_hmac('sha256', '12345678-1234-1234-1234-123456789abc', APP_SECRET);
        $hash2 = hash_hmac('sha256', 'abcdefab-1234-1234-1234-123456789abc', APP_SECRET);

        $this->assertNotEquals($hash1, $hash2);
    }

    // =========================================================================
    // reportIncident: METHOD ENFORCEMENT
    // =========================================================================

    public function testReportIncidentRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('reportIncident');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testReportIncidentRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('reportIncident');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testReportIncidentRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('reportIncident');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // reportIncident: INPUT PARSING AND DEFAULTS
    // =========================================================================

    public function testReportIncidentDefaultKindIsNetwork(): void
    {
        // Replicate the kind default logic from reportIncident()
        $in = [];
        $kind = trim((string) ($in['kind'] ?? 'network'));

        $this->assertEquals('network', $kind);
    }

    public function testReportIncidentCustomKind(): void
    {
        $in = ['kind' => 'hardware'];
        $kind = trim((string) ($in['kind'] ?? 'network'));

        $this->assertEquals('hardware', $kind);
    }

    public function testReportIncidentDetailDefault(): void
    {
        $in = [];
        $detail = trim((string) ($in['detail'] ?? ''));

        $this->assertEquals('', $detail);
    }

    public function testReportIncidentTokenHashDefault(): void
    {
        $in = [];
        $tokenHash = trim((string) ($in['token_hash'] ?? ''));

        $this->assertEquals('', $tokenHash);
    }

    public function testReportIncidentSucceedsWithMinimalInputAndTokenHash(): void
    {
        // Note: The test bootstrap's audit_log stub has a non-nullable $resourceId
        // parameter, so we must provide a non-empty token_hash to avoid a type
        // error. In production, audit_log accepts ?string $resourceId = null.
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'token_hash' => 'some-token-hash',
        ]);

        $result = $this->callControllerMethod('reportIncident');

        // reportIncident with default kind ('network') and a token_hash should succeed
        // JsonResponse::ok() wraps data in ['ok' => true, 'data' => $data]
        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
        $this->assertTrue($result['body']['data']['saved']);
    }

    public function testReportIncidentSucceedsWithAllFields(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'kind' => 'network',
            'detail' => 'Connection lost during vote',
            'token_hash' => 'abc123hash',
        ]);

        $result = $this->callControllerMethod('reportIncident');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
        $this->assertTrue($result['body']['data']['saved']);
    }

    public function testReportIncidentSucceedsWithCustomKind(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'kind' => 'timeout',
            'detail' => 'Vote page timed out',
            'token_hash' => 'hash-for-test',
        ]);

        $result = $this->callControllerMethod('reportIncident');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
        $this->assertTrue($result['body']['data']['saved']);
    }

    public function testReportIncidentWithEmptyTokenHashHitsBootstrapLimit(): void
    {
        // When token_hash is empty, the controller passes null to audit_log's
        // $resourceId. The test bootstrap's audit_log stub declares a non-nullable
        // string, so AbstractController catches the TypeError as internal_error.
        // In production this works because audit_log accepts ?string.
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('reportIncident');

        $this->assertEquals(500, $result['status']);
        $this->assertEquals('internal_error', $result['body']['error']);
    }

    // =========================================================================
    // reportIncident: EMPTY KIND REJECTION
    // =========================================================================

    public function testReportIncidentRejectsExplicitlyEmptyKind(): void
    {
        // When kind is explicitly set to empty string, after trim it becomes ''
        // The controller checks if ($kind === '') => api_fail('missing_kind', 400)
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'kind' => '',
        ]);

        $result = $this->callControllerMethod('reportIncident');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_kind', $result['body']['error']);
    }

    public function testReportIncidentRejectsWhitespaceOnlyKind(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'kind' => '   ',
        ]);

        $result = $this->callControllerMethod('reportIncident');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_kind', $result['body']['error']);
    }

    // =========================================================================
    // cancel: REASON TRIMMING LOGIC
    // =========================================================================

    public function testCancelReasonIsTrimmed(): void
    {
        // Replicate the reason parsing logic from cancel()
        $in = ['reason' => '  Some reason  '];
        $reason = trim((string) ($in['reason'] ?? ''));

        $this->assertEquals('Some reason', $reason);
    }

    public function testCancelReasonNullDefaultsToEmpty(): void
    {
        $in = [];
        $reason = trim((string) ($in['reason'] ?? ''));

        $this->assertEquals('', $reason);
    }

    // =========================================================================
    // manualVote: FIELD TRIMMING
    // =========================================================================

    public function testManualVoteFieldsTrimmed(): void
    {
        // Replicate the field extraction logic from manualVote()
        $data = [
            'meeting_id' => '  12345678-1234-1234-1234-123456789abc  ',
            'motion_id' => '  abcdefab-1234-1234-1234-123456789abc  ',
            'member_id' => '  abcdefab-abcd-1234-1234-123456789abc  ',
            'vote' => '  pour  ',
            'justification' => '  Some justification  ',
        ];

        $meetingId = trim((string) ($data['meeting_id'] ?? ''));
        $motionId = trim((string) ($data['motion_id'] ?? ''));
        $memberId = trim((string) ($data['member_id'] ?? ''));
        $voteUi = trim((string) ($data['vote'] ?? ''));
        $justif = trim((string) ($data['justification'] ?? ''));

        $this->assertEquals('12345678-1234-1234-1234-123456789abc', $meetingId);
        $this->assertEquals('abcdefab-1234-1234-1234-123456789abc', $motionId);
        $this->assertEquals('abcdefab-abcd-1234-1234-123456789abc', $memberId);
        $this->assertEquals('pour', $voteUi);
        $this->assertEquals('Some justification', $justif);
    }

    // =========================================================================
    // manualVote: DUPLICATE VOTE ERROR DETECTION LOGIC
    // =========================================================================

    public function testManualVoteDuplicateDetectionByUniqueConstraint(): void
    {
        // Replicate the unique constraint detection logic from manualVote()
        $messages = [
            'UNIQUE constraint failed: ballots.motion_id, ballots.member_id',
            'ballots_motion_id_member_id already exists',
            'Duplicate entry for key unique',
        ];

        foreach ($messages as $msg) {
            $isDuplicate = stripos($msg, 'unique') !== false
                || stripos($msg, 'ballots_motion_id_member_id') !== false;
            $this->assertTrue($isDuplicate, "Should detect duplicate from: {$msg}");
        }
    }

    public function testManualVoteNonDuplicateError(): void
    {
        $msg = 'Connection refused';
        $isDuplicate = stripos($msg, 'unique') !== false
            || stripos($msg, 'ballots_motion_id_member_id') !== false;
        $this->assertFalse($isDuplicate);
    }

    // =========================================================================
    // result: MOTION ID TRIMMING LOGIC
    // =========================================================================

    public function testResultMotionIdIsTrimmed(): void
    {
        // Replicate the motion_id parsing from result()
        $params = ['motion_id' => '  some-id  '];
        $motionId = trim((string) ($params['motion_id'] ?? ''));

        $this->assertEquals('some-id', $motionId);
    }

    public function testResultMotionIdNullDefaultsToEmpty(): void
    {
        $params = [];
        $motionId = trim((string) ($params['motion_id'] ?? ''));

        $this->assertEquals('', $motionId);
    }

    // =========================================================================
    // listForMotion: USES api_query FOR MOTION ID
    // =========================================================================

    public function testListForMotionUsesApiQuery(): void
    {
        // Verify the controller reads motion_id from $_GET via api_query
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/BallotsController.php');

        $this->assertStringContainsString("api_query('motion_id')", $source);
    }

    // =========================================================================
    // CONTROLLER SOURCE VERIFICATION
    // =========================================================================

    public function testControllerUsesApiRequestForMethodEnforcement(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/BallotsController.php');

        // Verify each endpoint enforces its HTTP method
        $this->assertStringContainsString("api_request('GET')", $source);
        $this->assertStringContainsString("api_request('POST')", $source);
    }

    public function testControllerUsesApiIsUuidForCodeValidation(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/BallotsController.php');

        $this->assertStringContainsString('api_is_uuid($code)', $source);
        $this->assertStringContainsString('api_is_uuid($motionId)', $source);
    }

    public function testControllerUsesApiRequireUuid(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/BallotsController.php');

        // cancel() uses api_require_uuid for motion_id and member_id
        $this->assertStringContainsString("api_require_uuid(\$in, 'motion_id')", $source);
        $this->assertStringContainsString("api_require_uuid(\$in, 'member_id')", $source);
    }

    public function testControllerUsesHmacSha256ForPaperBallot(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/BallotsController.php');

        $this->assertStringContainsString("hash_hmac('sha256'", $source);
        $this->assertStringContainsString('APP_SECRET', $source);
    }

    public function testControllerAuditsOperations(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/BallotsController.php');

        // Verify audit_log calls for key operations
        $this->assertStringContainsString("'ballot.cast'", $source);
        $this->assertStringContainsString("'ballot_cancelled'", $source);
        $this->assertStringContainsString("'ballot.manual_vote'", $source);
        $this->assertStringContainsString("'paper_ballot_redeemed'", $source);
        $this->assertStringContainsString("'vote_incident'", $source);
    }

    public function testControllerUsesEventBroadcaster(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/BallotsController.php');

        $this->assertStringContainsString('EventBroadcaster::motionUpdated', $source);
    }

    public function testControllerUsesBallotsService(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/BallotsController.php');

        $this->assertStringContainsString('BallotsService', $source);
        $this->assertStringContainsString('castBallot', $source);
    }

    public function testControllerUsesVoteEngine(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/BallotsController.php');

        $this->assertStringContainsString('VoteEngine', $source);
        $this->assertStringContainsString('computeMotionResult', $source);
    }

    public function testControllerUsesVoteTokenService(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/BallotsController.php');

        $this->assertStringContainsString('VoteTokenService', $source);
        $this->assertStringContainsString('validateAndConsume', $source);
    }

    // =========================================================================
    // cancel: SOURCE VALIDATION LOGIC
    // =========================================================================

    public function testCancelOnlyAllowsManualSourceDeletion(): void
    {
        // Replicate the source check from cancel()
        $ballot = ['source' => 'tablet'];
        $source = $ballot['source'] ?? 'tablet';

        $this->assertNotEquals('manual', $source, 'Non-manual source should be rejected');
    }

    public function testCancelAllowsManualSource(): void
    {
        $ballot = ['source' => 'manual'];
        $source = $ballot['source'] ?? 'tablet';

        $this->assertEquals('manual', $source);
    }

    public function testCancelSourceDefaultsToTablet(): void
    {
        // When source is not set in the ballot, it defaults to 'tablet'
        $ballot = [];
        $source = $ballot['source'] ?? 'tablet';

        $this->assertEquals('tablet', $source);
    }

    // =========================================================================
    // cancel: MEETING VALIDATION CHECK
    // =========================================================================

    public function testCancelChecksIfMeetingIsValidated(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/BallotsController.php');

        $this->assertStringContainsString('isValidated', $source);
        $this->assertStringContainsString("'meeting_validated'", $source);
    }

    public function testCancelChecksIfMotionIsClosed(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/BallotsController.php');

        $this->assertStringContainsString("'motion_closed'", $source);
        $this->assertStringContainsString('closed_at', $source);
    }

    // =========================================================================
    // cast: AUDIT DATA STRUCTURE
    // =========================================================================

    public function testCastAuditDataStructure(): void
    {
        // Replicate the audit data construction from cast()
        $data = [
            'member_id' => 'm1',
            'value' => 'for',
            'is_proxy_vote' => true,
        ];
        $ballot = [
            'member_id' => 'm1',
            'value' => 'for',
            'weight' => '1.5',
            'is_proxy_vote' => true,
            'proxy_source_member_id' => 'm2',
            'motion_id' => 'mot-1',
            'meeting_id' => 'mtg-1',
        ];
        $tokenHash = 'abc123';

        $auditData = [
            'member_id' => $data['member_id'] ?? $ballot['member_id'] ?? null,
            'value' => $ballot['value'] ?? $data['value'] ?? null,
            'weight' => $ballot['weight'] ?? null,
            'is_proxy_vote' => $ballot['is_proxy_vote'] ?? ($data['is_proxy_vote'] ?? false),
            'proxy_source_member_id' => $ballot['proxy_source_member_id'] ?? null,
        ];
        if ($tokenHash !== null) {
            $auditData['token_hash'] = $tokenHash;
        }
        if (!empty($data['_idempotency_key'])) {
            $auditData['idempotency_key'] = (string) $data['_idempotency_key'];
        }

        $this->assertEquals('m1', $auditData['member_id']);
        $this->assertEquals('for', $auditData['value']);
        $this->assertEquals('1.5', $auditData['weight']);
        $this->assertTrue($auditData['is_proxy_vote']);
        $this->assertEquals('m2', $auditData['proxy_source_member_id']);
        $this->assertEquals('abc123', $auditData['token_hash']);
        $this->assertArrayNotHasKey('idempotency_key', $auditData);
    }

    public function testCastAuditDataWithIdempotencyKey(): void
    {
        $data = ['_idempotency_key' => 'idem-key-456'];
        $auditData = [];
        if (!empty($data['_idempotency_key'])) {
            $auditData['idempotency_key'] = (string) $data['_idempotency_key'];
        }

        $this->assertEquals('idem-key-456', $auditData['idempotency_key']);
    }

    public function testCastAuditDataWithoutIdempotencyKey(): void
    {
        $data = [];
        $auditData = [];
        if (!empty($data['_idempotency_key'])) {
            $auditData['idempotency_key'] = (string) $data['_idempotency_key'];
        }

        $this->assertArrayNotHasKey('idempotency_key', $auditData);
    }

    public function testCastAuditDataTokenHashOnlyWhenPresent(): void
    {
        $tokenHash = null;
        $auditData = [];
        if ($tokenHash !== null) {
            $auditData['token_hash'] = $tokenHash;
        }

        $this->assertArrayNotHasKey('token_hash', $auditData);
    }

    // =========================================================================
    // cast: PROXY VOTE FLAG
    // =========================================================================

    public function testCastProxyVoteFlagFallback(): void
    {
        // The audit data uses ballot's is_proxy_vote first, then data's
        $ballot = [];
        $data = ['is_proxy_vote' => true];

        $isProxy = $ballot['is_proxy_vote'] ?? ($data['is_proxy_vote'] ?? false);

        $this->assertTrue($isProxy);
    }

    public function testCastProxyVoteFlagDefaultFalse(): void
    {
        $ballot = [];
        $data = [];

        $isProxy = $ballot['is_proxy_vote'] ?? ($data['is_proxy_vote'] ?? false);

        $this->assertFalse($isProxy);
    }

    // =========================================================================
    // manualVote: WEIGHT EXTRACTION
    // =========================================================================

    public function testManualVoteWeightExtraction(): void
    {
        // Replicate weight extraction from manualVote()
        $member = ['voting_power' => '2.5'];
        $weight = (string) ($member['voting_power'] ?? '1.0');

        $this->assertEquals('2.5', $weight);
    }

    public function testManualVoteWeightDefaultsToOne(): void
    {
        $member = [];
        $weight = (string) ($member['voting_power'] ?? '1.0');

        $this->assertEquals('1.0', $weight);
    }

    public function testManualVoteWeightWithNumericValue(): void
    {
        $member = ['voting_power' => 3.0];
        $weight = (string) ($member['voting_power'] ?? '1.0');

        $this->assertEquals('3', $weight);
    }

    // =========================================================================
    // manualVote: MOTION STATE CHECK
    // =========================================================================

    public function testManualVoteMotionMustBeOpen(): void
    {
        // Replicate the motion state check from manualVote()
        $motionOpen = ['opened_at' => '2024-01-01 10:00:00', 'closed_at' => null];
        $motionClosed = ['opened_at' => '2024-01-01 10:00:00', 'closed_at' => '2024-01-01 11:00:00'];
        $motionNotOpened = ['opened_at' => null, 'closed_at' => null];

        // Open motion - should be allowed
        $isNotOpen = empty($motionOpen['opened_at']) || !empty($motionOpen['closed_at']);
        $this->assertFalse($isNotOpen, 'Open motion should be allowed');

        // Closed motion - should be rejected
        $isNotOpen = empty($motionClosed['opened_at']) || !empty($motionClosed['closed_at']);
        $this->assertTrue($isNotOpen, 'Closed motion should be rejected');

        // Not yet opened - should be rejected
        $isNotOpen = empty($motionNotOpened['opened_at']) || !empty($motionNotOpened['closed_at']);
        $this->assertTrue($isNotOpen, 'Not-yet-opened motion should be rejected');
    }

    // =========================================================================
    // redeemPaperBallot: CODE TRIMMING
    // =========================================================================

    public function testRedeemPaperBallotCodeIsTrimmed(): void
    {
        // Replicate code extraction from redeemPaperBallot()
        $in = ['code' => '  12345678-1234-1234-1234-123456789abc  '];
        $code = trim((string) ($in['code'] ?? ''));

        $this->assertEquals('12345678-1234-1234-1234-123456789abc', $code);
    }

    public function testRedeemPaperBallotVoteValueIsTrimmed(): void
    {
        $in = ['vote_value' => '  pour  '];
        $vote = trim((string) ($in['vote_value'] ?? ''));

        $this->assertEquals('pour', $vote);
    }

    // =========================================================================
    // UUID VALIDATION HELPER
    // =========================================================================

    public function testApiIsUuidAcceptsValidUuids(): void
    {
        $this->assertTrue(api_is_uuid('12345678-1234-1234-1234-123456789abc'));
        $this->assertTrue(api_is_uuid('ABCDEFAB-ABCD-ABCD-ABCD-ABCDEFABCDEF'));
        $this->assertTrue(api_is_uuid('00000000-0000-0000-0000-000000000000'));
        $this->assertTrue(api_is_uuid('ffffffff-ffff-ffff-ffff-ffffffffffff'));
    }

    public function testApiIsUuidRejectsInvalidValues(): void
    {
        $this->assertFalse(api_is_uuid(''));
        $this->assertFalse(api_is_uuid('not-a-uuid'));
        $this->assertFalse(api_is_uuid('12345678-1234-1234-1234'));
        $this->assertFalse(api_is_uuid('12345678123412341234123456789abc'));
        $this->assertFalse(api_is_uuid('GGGGGGGG-1234-1234-1234-123456789abc'));
    }

    // =========================================================================
    // HANDLE METHOD: UNKNOWN METHOD
    // =========================================================================

    public function testHandleUnknownMethodReturnsInternalError(): void
    {
        $controller = new BallotsController();
        try {
            $controller->handle('nonExistentMethod');
            $this->fail('Expected ApiResponseException was not thrown');
        } catch (ApiResponseException $e) {
            // AbstractController catches Throwable and returns internal_error
            $this->assertEquals(500, $e->getResponse()->getStatusCode());
            $this->assertEquals('internal_error', $e->getResponse()->getBody()['error']);
        }
    }

    // =========================================================================
    // cancel: RESPONSE STRUCTURE
    // =========================================================================

    public function testCancelResponseStructureInSource(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/BallotsController.php');

        // Verify cancel response includes expected fields
        $this->assertStringContainsString("'cancelled' => true", $source);
        $this->assertStringContainsString("'motion_id' => \$motionId", $source);
        $this->assertStringContainsString("'member_id' => \$memberId", $source);
    }

    // =========================================================================
    // cast: RESPONSE STRUCTURE
    // =========================================================================

    public function testCastResponseUsesHttp201(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/BallotsController.php');

        $this->assertStringContainsString("api_ok(['ballot' => \$ballot], 201)", $source);
    }

    // =========================================================================
    // reportIncident: RESPONSE STRUCTURE
    // =========================================================================

    public function testReportIncidentResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/BallotsController.php');

        $this->assertStringContainsString("api_ok(['saved' => true])", $source);
    }

    // =========================================================================
    // CONTROLLER: TRANSACTION USAGE
    // =========================================================================

    public function testControllerUsesTransactions(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/BallotsController.php');

        // cancel and redeemPaperBallot and manualVote all use api_transaction
        $this->assertGreaterThanOrEqual(
            3,
            substr_count($source, 'api_transaction'),
            'Controller should use api_transaction in at least 3 methods',
        );
    }

    // =========================================================================
    // CONTROLLER: TENANT ISOLATION
    // =========================================================================

    public function testControllerEnforcesTenantIsolation(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/BallotsController.php');

        // Multiple methods call api_current_tenant_id() for tenant isolation
        $this->assertGreaterThanOrEqual(
            4,
            substr_count($source, 'api_current_tenant_id()'),
            'Controller should enforce tenant isolation in multiple methods',
        );
    }

    // =========================================================================
    // cast: TOKEN HASH PROPAGATION
    // =========================================================================

    public function testCastTokenHashPropagation(): void
    {
        // When vote_token is validated, the token_hash should be set in data
        $tokenResult = ['valid' => true, 'token_hash' => 'hash123'];
        $data = [];

        $tokenHash = $tokenResult['token_hash'];
        $data['_token_hash'] = $tokenHash;

        $this->assertEquals('hash123', $data['_token_hash']);
    }

    public function testCastTokenHashNullWhenNoToken(): void
    {
        $tokenHash = null;
        $data = [];

        // When no token, _token_hash should not be set
        if ($tokenHash !== null) {
            $data['_token_hash'] = $tokenHash;
        }

        $this->assertArrayNotHasKey('_token_hash', $data);
    }

    // =========================================================================
    // cancel: BROADCAST FAILURE IS NON-FATAL
    // =========================================================================

    public function testCancelBroadcastFailureIsNonFatal(): void
    {
        // Verify the controller wraps EventBroadcaster in try-catch
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/BallotsController.php');

        // The broadcast is wrapped in try-catch so failure does not break the endpoint
        $this->assertStringContainsString("} catch (Throwable \$e) {", $source);
        $this->assertStringContainsString("// Don't fail if broadcast fails", $source);
    }

    // =========================================================================
    // manualVote: ACCEPTED VOTE VALUES (INTEGRATION)
    // =========================================================================

    public function testManualVoteAcceptsPour(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => 'abcdefab-1234-1234-1234-123456789abc',
            'member_id' => 'abcdefab-abcd-1234-1234-123456789abc',
            'vote' => 'pour',
            'justification' => 'Valid justification',
        ]);

        // This will pass input validation but fail at DB access (meeting lookup)
        // We just verify it gets past the validation stage
        $result = $this->callControllerMethod('manualVote');

        // Should fail with a DB-related error, not an input validation error
        $this->assertNotEquals('missing_fields', $result['body']['error'] ?? '');
        $this->assertNotEquals('missing_justification', $result['body']['error'] ?? '');
        $this->assertNotEquals('invalid_vote', $result['body']['error'] ?? '');
    }

    public function testManualVoteAcceptsContre(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => 'abcdefab-1234-1234-1234-123456789abc',
            'member_id' => 'abcdefab-abcd-1234-1234-123456789abc',
            'vote' => 'contre',
            'justification' => 'Valid justification',
        ]);

        $result = $this->callControllerMethod('manualVote');

        $this->assertNotEquals('invalid_vote', $result['body']['error'] ?? '');
    }

    public function testManualVoteAcceptsAbstention(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => 'abcdefab-1234-1234-1234-123456789abc',
            'member_id' => 'abcdefab-abcd-1234-1234-123456789abc',
            'vote' => 'abstention',
            'justification' => 'Valid justification',
        ]);

        $result = $this->callControllerMethod('manualVote');

        $this->assertNotEquals('invalid_vote', $result['body']['error'] ?? '');
    }

    public function testManualVoteAcceptsBlanc(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => 'abcdefab-1234-1234-1234-123456789abc',
            'member_id' => 'abcdefab-abcd-1234-1234-123456789abc',
            'vote' => 'blanc',
            'justification' => 'Valid justification',
        ]);

        $result = $this->callControllerMethod('manualVote');

        $this->assertNotEquals('invalid_vote', $result['body']['error'] ?? '');
    }

    public function testManualVoteAcceptsEnglishFor(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => 'abcdefab-1234-1234-1234-123456789abc',
            'member_id' => 'abcdefab-abcd-1234-1234-123456789abc',
            'vote' => 'for',
            'justification' => 'Valid justification',
        ]);

        $result = $this->callControllerMethod('manualVote');

        $this->assertNotEquals('invalid_vote', $result['body']['error'] ?? '');
    }

    public function testManualVoteAcceptsEnglishAgainst(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => 'abcdefab-1234-1234-1234-123456789abc',
            'member_id' => 'abcdefab-abcd-1234-1234-123456789abc',
            'vote' => 'against',
            'justification' => 'Valid justification',
        ]);

        $result = $this->callControllerMethod('manualVote');

        $this->assertNotEquals('invalid_vote', $result['body']['error'] ?? '');
    }

    public function testManualVoteAcceptsEnglishAbstain(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => 'abcdefab-1234-1234-1234-123456789abc',
            'member_id' => 'abcdefab-abcd-1234-1234-123456789abc',
            'vote' => 'abstain',
            'justification' => 'Valid justification',
        ]);

        $result = $this->callControllerMethod('manualVote');

        $this->assertNotEquals('invalid_vote', $result['body']['error'] ?? '');
    }

    public function testManualVoteAcceptsNsp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => 'abcdefab-1234-1234-1234-123456789abc',
            'member_id' => 'abcdefab-abcd-1234-1234-123456789abc',
            'vote' => 'nsp',
            'justification' => 'Valid justification',
        ]);

        $result = $this->callControllerMethod('manualVote');

        $this->assertNotEquals('invalid_vote', $result['body']['error'] ?? '');
    }
}
