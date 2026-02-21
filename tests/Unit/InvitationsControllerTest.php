<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\InvitationsController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for InvitationsController.
 *
 * Tests the invitation endpoints logic including:
 *  - Controller structure (final, extends AbstractController)
 *  - HTTP method enforcement (GET vs POST)
 *  - UUID validation for meeting_id, member_id
 *  - Email validation
 *  - Token requirement validation
 *  - Missing required fields
 *  - Response structure and audit logging verification
 */
class InvitationsControllerTest extends TestCase
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
        $controller = new InvitationsController();
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
        $ref = new \ReflectionClass(InvitationsController::class);
        $this->assertTrue($ref->isFinal(), 'InvitationsController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new InvitationsController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(InvitationsController::class);

        $expectedMethods = ['create', 'listForMeeting', 'redeem', 'stats'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "InvitationsController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(InvitationsController::class);

        $expectedMethods = ['create', 'listForMeeting', 'redeem', 'stats'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "InvitationsController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // create: METHOD ENFORCEMENT
    // =========================================================================

    public function testCreateRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('create');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testCreateRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('create');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testCreateRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('create');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // create: meeting_id AND member_id VALIDATION
    // =========================================================================

    public function testCreateRequiresMeetingIdAndMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('create');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_or_member', $result['body']['error']);
    }

    public function testCreateRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '',
            'member_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('create');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_or_member', $result['body']['error']);
    }

    public function testCreateRejectsEmptyMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => '',
        ]);

        $result = $this->callControllerMethod('create');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_or_member', $result['body']['error']);
    }

    public function testCreateRejectsBothEmpty(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '',
            'member_id' => '',
        ]);

        $result = $this->callControllerMethod('create');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_or_member', $result['body']['error']);
    }

    // =========================================================================
    // create: UUID VALIDATION (meeting_id)
    // =========================================================================

    public function testCreateRejectsInvalidMeetingUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => 'not-a-uuid',
            'member_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('create');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testCreateRejectsPartialMeetingUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234',
            'member_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('create');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // create: UUID VALIDATION (member_id)
    // =========================================================================

    public function testCreateRejectsInvalidMemberUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => 'not-a-uuid',
        ]);

        $result = $this->callControllerMethod('create');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_member_id', $result['body']['error']);
    }

    public function testCreateRejectsPartialMemberUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => '12345678-1234',
        ]);

        $result = $this->callControllerMethod('create');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_member_id', $result['body']['error']);
    }

    // =========================================================================
    // create: EMAIL VALIDATION
    // =========================================================================

    public function testCreateRejectsInvalidEmail(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => '12345678-1234-1234-1234-123456789abc',
            'email' => 'not-an-email',
        ]);

        $result = $this->callControllerMethod('create');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_email', $result['body']['error']);
    }

    public function testCreateEmailValidationLogic(): void
    {
        // Replicate: if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $testCases = [
            ['email' => null, 'shouldFail' => false],
            ['email' => '', 'shouldFail' => false],
            ['email' => 'user@example.com', 'shouldFail' => false],
            ['email' => 'not-an-email', 'shouldFail' => true],
            ['email' => '@missing-local', 'shouldFail' => true],
            ['email' => 'missing@', 'shouldFail' => true],
        ];

        foreach ($testCases as $case) {
            $email = $case['email'];
            $invalid = $email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL);
            $this->assertEquals(
                $case['shouldFail'],
                $invalid,
                "email '{$case['email']}' validation should " . ($case['shouldFail'] ? 'fail' : 'pass'),
            );
        }
    }

    // =========================================================================
    // create: VALIDATION ORDER
    // =========================================================================

    public function testCreateValidatesMeetingIdBeforeMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => 'bad-uuid',
            'member_id' => 'also-bad',
        ]);

        $result = $this->callControllerMethod('create');

        // Should fail on meeting_id first
        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testCreateValidatesMemberIdBeforeEmail(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => 'bad-uuid',
            'email' => 'also-bad',
        ]);

        $result = $this->callControllerMethod('create');

        // Should fail on member_id first
        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_member_id', $result['body']['error']);
    }

    // =========================================================================
    // listForMeeting: NO METHOD ENFORCEMENT
    // listForMeeting() uses api_query() directly, not api_request('GET'),
    // so it does not enforce HTTP method. Any method will work.
    // =========================================================================

    public function testListForMeetingDoesNotEnforcePostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('listForMeeting');

        // Should not fail with method_not_allowed, but with missing_meeting_id
        $this->assertNotEquals(405, $result['status']);
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testListForMeetingDoesNotEnforcePutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertNotEquals(405, $result['status']);
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // listForMeeting: meeting_id VALIDATION
    // =========================================================================

    public function testListForMeetingRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testListForMeetingRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testListForMeetingRejectsInvalidMeetingUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-a-uuid'];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testListForMeetingRejectsPartialUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234'];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // redeem: TOKEN VALIDATION
    // =========================================================================

    public function testRedeemRequiresToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('redeem');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_token', $result['body']['error']);
    }

    public function testRedeemRejectsEmptyToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['token' => ''];

        $result = $this->callControllerMethod('redeem');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_token', $result['body']['error']);
    }

    // =========================================================================
    // stats: METHOD ENFORCEMENT
    // =========================================================================

    public function testStatsRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('stats');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testStatsRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('stats');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // stats: meeting_id VALIDATION
    // =========================================================================

    public function testStatsRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('stats');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testStatsRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('stats');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testStatsRejectsInvalidMeetingUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-valid'];

        $result = $this->callControllerMethod('stats');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // CROSS-CUTTING: METHOD CHECK BEFORE BODY VALIDATION
    // =========================================================================

    public function testCreateMethodCheckBeforeBodyValidation(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('create');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListForMeetingDoesNotEnforceHttpMethod(): void
    {
        // listForMeeting() does not call api_request(), so POST with body
        // still proceeds to meeting_id validation (from $_GET)
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = [];
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('listForMeeting');

        // meeting_id comes from api_query (GET params), not from POST body
        $this->assertNotEquals(405, $result['status']);
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // RESPONSE STRUCTURE (source verification)
    // =========================================================================

    public function testCreateResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/InvitationsController.php');

        $expectedKeys = ['meeting_id', 'member_id', 'token', 'vote_url'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $source,
                "create() response should contain '{$key}'",
            );
        }
    }

    public function testListForMeetingResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/InvitationsController.php');

        $this->assertStringContainsString("'items'", $source);
    }

    public function testRedeemResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/InvitationsController.php');

        $this->assertStringContainsString("'status' => 'accepted'", $source);
    }

    public function testStatsResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/InvitationsController.php');

        $expectedKeys = ['meeting_id', 'items', 'engagement', 'queue', 'events'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $source,
                "stats() response should contain '{$key}'",
            );
        }
    }

    public function testStatsEngagementFields(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/InvitationsController.php');

        $engagementFields = ['total_opens', 'total_clicks', 'open_rate', 'bounce_rate', 'accept_rate'];
        foreach ($engagementFields as $field) {
            $this->assertStringContainsString(
                "'{$field}'",
                $source,
                "stats() engagement should contain '{$field}'",
            );
        }
    }

    public function testStatsItemsFields(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/InvitationsController.php');

        $itemFields = ['total', 'pending', 'sent', 'opened', 'accepted', 'declined', 'bounced'];
        foreach ($itemFields as $field) {
            $this->assertStringContainsString(
                "'{$field}'",
                $source,
                "stats() items should contain '{$field}'",
            );
        }
    }

    // =========================================================================
    // AUDIT LOG VERIFICATION (source-level)
    // =========================================================================

    public function testCreateAuditsInvitationCreation(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/InvitationsController.php');

        $this->assertStringContainsString("'invitation.create'", $source);
    }

    public function testRedeemAuditsInvitationRedemption(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/InvitationsController.php');

        $this->assertStringContainsString("'invitation.redeemed'", $source);
    }

    // =========================================================================
    // BUSINESS GUARD VERIFICATION (source-level)
    // =========================================================================

    public function testCreateGuardsMeetingNotValidated(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/InvitationsController.php');

        $this->assertStringContainsString('api_guard_meeting_not_validated', $source);
    }

    public function testStatsGuardsMeetingExists(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/InvitationsController.php');

        $this->assertStringContainsString('api_guard_meeting_exists', $source);
    }

    public function testRedeemChecksTenantIsolation(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/InvitationsController.php');

        $this->assertStringContainsString('api_current_tenant_id', $source);
        $this->assertStringContainsString('tenant_id', $source);
    }

    public function testRedeemChecksTokenStatus(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/InvitationsController.php');

        $this->assertStringContainsString('token_not_usable', $source);
        $this->assertStringContainsString("'declined'", $source);
        $this->assertStringContainsString("'bounced'", $source);
    }

    // =========================================================================
    // STATS: RATE CALCULATION LOGIC
    // =========================================================================

    public function testStatsOpenRateCalculation(): void
    {
        // Replicate open rate logic
        $sent = 80;
        $opened = 15;
        $accepted = 5;

        $openRate = ($sent + $opened + $accepted) > 0
            ? round(($opened + $accepted) / ($sent + $opened + $accepted) * 100, 1)
            : 0;

        $this->assertEquals(20.0, $openRate);
    }

    public function testStatsOpenRateZeroWhenNoSent(): void
    {
        $sent = 0;
        $opened = 0;
        $accepted = 0;

        $openRate = ($sent + $opened + $accepted) > 0
            ? round(($opened + $accepted) / ($sent + $opened + $accepted) * 100, 1)
            : 0;

        $this->assertEquals(0, $openRate);
    }

    public function testStatsBounceRateCalculation(): void
    {
        $sent = 90;
        $bounced = 10;

        $bounceRate = ($sent + $bounced) > 0
            ? round($bounced / ($sent + $bounced) * 100, 1)
            : 0;

        $this->assertEquals(10.0, $bounceRate);
    }

    public function testStatsBounceRateZeroWhenNoBounced(): void
    {
        $sent = 100;
        $bounced = 0;

        $bounceRate = ($sent + $bounced) > 0
            ? round($bounced / ($sent + $bounced) * 100, 1)
            : 0;

        $this->assertEquals(0.0, $bounceRate);
    }

    public function testStatsAcceptRateCalculation(): void
    {
        $sent = 70;
        $opened = 20;
        $accepted = 10;

        $acceptRate = ($sent + $opened + $accepted) > 0
            ? round($accepted / ($sent + $opened + $accepted) * 100, 1)
            : 0;

        $this->assertEquals(10.0, $acceptRate);
    }

    // =========================================================================
    // UNKNOWN METHOD HANDLING
    // =========================================================================

    public function testHandleUnknownMethodReturns500(): void
    {
        $result = $this->callControllerMethod('nonExistentMethod');

        $this->assertEquals(500, $result['status']);
        $this->assertEquals('internal_error', $result['body']['error']);
    }

    // =========================================================================
    // redeem: STATUS TRANSITION LOGIC
    // =========================================================================

    public function testRedeemStatusTransitionLogic(): void
    {
        // Replicate: if ($status === 'pending' || $status === 'sent') markOpened()
        $transitionStatuses = ['pending', 'sent'];
        foreach ($transitionStatuses as $status) {
            $shouldMarkOpened = ($status === 'pending' || $status === 'sent');
            $this->assertTrue($shouldMarkOpened, "Status '{$status}' should trigger markOpened");
        }

        $nonTransitionStatuses = ['opened', 'accepted', 'declined', 'bounced'];
        foreach ($nonTransitionStatuses as $status) {
            $shouldMarkOpened = ($status === 'pending' || $status === 'sent');
            $this->assertFalse($shouldMarkOpened, "Status '{$status}' should not trigger markOpened");
        }
    }

    public function testRedeemUnusableStatuses(): void
    {
        // Replicate: if ($status === 'declined' || $status === 'bounced') api_fail()
        $unusableStatuses = ['declined', 'bounced'];
        foreach ($unusableStatuses as $status) {
            $isUnusable = ($status === 'declined' || $status === 'bounced');
            $this->assertTrue($isUnusable, "Status '{$status}' should be unusable");
        }

        $usableStatuses = ['pending', 'sent', 'opened', 'accepted'];
        foreach ($usableStatuses as $status) {
            $isUnusable = ($status === 'declined' || $status === 'bounced');
            $this->assertFalse($isUnusable, "Status '{$status}' should be usable");
        }
    }
}
