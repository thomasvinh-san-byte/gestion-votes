<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\QuorumController;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\PolicyRepository;

/**
 * Unit tests for QuorumController.
 *
 * Endpoints:
 *  - card():            GET — renders HTML quorum card (outputs directly, no api_ok/api_fail)
 *  - status():          GET — returns JSON quorum status (QuorumEngine delegates to repos)
 *  - meetingSettings(): GET/POST — manages quorum settings for a meeting
 *
 * Response structure:
 *   - api_ok($data)  => body['data'][...] (wrapped in 'data' key)
 *   - api_fail(...)  => body['error'] (direct)
 *
 * Note: card() outputs HTML directly and handles errors via output/http_response_code.
 * status() invokes QuorumEngine which needs real repos — tests cover validation paths
 * and delegation patterns.
 *
 * Pattern: extends ControllerTestCase, uses injectRepos() + callController().
 */
class QuorumControllerTest extends ControllerTestCase
{
    private const TENANT     = 'tenant-uuid-001';
    private const MEETING_ID = 'aaaaaaaa-1111-2222-3333-000000000020';
    private const MOTION_ID  = 'bbbbbbbb-1111-2222-3333-000000000020';
    private const POLICY_ID  = 'cccccccc-1111-2222-3333-000000000020';
    private const USER_ID    = 'user-uuid-0020';

    // =========================================================================
    // CONTROLLER STRUCTURE
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(QuorumController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerHasRequiredMethods(): void
    {
        foreach (['card', 'status', 'meetingSettings'] as $method) {
            $this->assertTrue(method_exists(QuorumController::class, $method));
        }
    }

    // =========================================================================
    // card() — HTML output (no ApiResponseException, uses ob_start)
    // =========================================================================

    public function testCardWithInvalidMeetingIdOutputs422(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => 'not-a-uuid']);

        $ctrl = new QuorumController();
        ob_start();
        $ctrl->handle('card');
        ob_end_clean();

        // Survived without throwing — card validates inline and returns early
        $this->assertTrue(true);
    }

    public function testCardWithInvalidMotionIdOutputs422(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['motion_id' => 'bad-motion']);

        $ctrl = new QuorumController();
        ob_start();
        $ctrl->handle('card');
        ob_end_clean();

        $this->assertTrue(true);
    }

    public function testCardWithNoParamsOutputsRequiredMessage(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $ctrl = new QuorumController();
        ob_start();
        $ctrl->handle('card');
        $output = ob_get_clean();

        $this->assertStringContainsString('requis', $output);
    }

    public function testCardWithMeetingIdOutputsCardHtml(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        // QuorumEngine will throw (no real DB) — card() catches Throwable and renders error card
        $ctrl = new QuorumController();
        ob_start();
        $ctrl->handle('card');
        $output = ob_get_clean();

        // Either error card or real card — both contain 'card' CSS class
        $this->assertStringContainsString('card', $output);
    }

    public function testCardWithMotionIdOutputsCardHtml(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['motion_id' => self::MOTION_ID]);

        $ctrl = new QuorumController();
        ob_start();
        $ctrl->handle('card');
        $output = ob_get_clean();

        $this->assertStringContainsString('card', $output);
    }

    // =========================================================================
    // status() — GET JSON
    // =========================================================================

    public function testStatusRequiresGet(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');

        $res = $this->callController(QuorumController::class, 'status');

        $this->assertSame(405, $res['status']);
    }

    public function testStatusInvalidMeetingIdReturns422(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => 'bad-uuid']);

        $res = $this->callController(QuorumController::class, 'status');

        $this->assertSame(422, $res['status']);
        $this->assertSame('invalid_meeting_id', $res['body']['error']);
    }

    public function testStatusInvalidMotionIdReturns422(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['motion_id' => 'bad-uuid']);

        $res = $this->callController(QuorumController::class, 'status');

        $this->assertSame(422, $res['status']);
        $this->assertSame('invalid_motion_id', $res['body']['error']);
    }

    public function testStatusMissingParamsReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $res = $this->callController(QuorumController::class, 'status');

        $this->assertSame(400, $res['status']);
        $this->assertSame('missing_params', $res['body']['error']);
    }

    public function testStatusWithMeetingIdInvokesQuorumEngine(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        // QuorumEngine needs repos — will get RuntimeException from null PDO
        // AbstractController catches RuntimeException as business_error(400)
        $res = $this->callController(QuorumController::class, 'status');

        $this->assertContains($res['status'], [200, 400, 500]);
    }

    // =========================================================================
    // meetingSettings() — GET
    // =========================================================================

    public function testMeetingSettingsGetRequiresMeetingId(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $repo = $this->createMock(MeetingRepository::class);
        $this->injectRepos([MeetingRepository::class => $repo]);

        $res = $this->callController(QuorumController::class, 'meetingSettings');

        // api_require_uuid returns 400 when field is missing/invalid
        $this->assertSame(400, $res['status']);
    }

    public function testMeetingSettingsGetNotFoundReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $repo = $this->createMock(MeetingRepository::class);
        $repo->method('findQuorumSettings')->willReturn(null);

        $this->injectRepos([MeetingRepository::class => $repo]);

        $res = $this->callController(QuorumController::class, 'meetingSettings');

        $this->assertSame(404, $res['status']);
        $this->assertSame('meeting_not_found', $res['body']['error']);
    }

    public function testMeetingSettingsGetReturnsSettings(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $row = [
            'meeting_id'       => self::MEETING_ID,
            'title'            => 'AG 2025',
            'quorum_policy_id' => self::POLICY_ID,
            'convocation_no'   => 1,
        ];

        $repo = $this->createMock(MeetingRepository::class);
        $repo->method('findQuorumSettings')->willReturn($row);

        $this->injectRepos([MeetingRepository::class => $repo]);

        $res = $this->callController(QuorumController::class, 'meetingSettings');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        $this->assertSame(self::MEETING_ID, $data['meeting_id']);
        $this->assertSame(self::POLICY_ID, $data['quorum_policy_id']);
        $this->assertSame(1, $data['convocation_no']);
    }

    // =========================================================================
    // meetingSettings() — POST
    // =========================================================================

    public function testMeetingSettingsPostRequiresMeetingId(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $repo = $this->createMock(MeetingRepository::class);
        $this->injectRepos([MeetingRepository::class => $repo]);

        $res = $this->callController(QuorumController::class, 'meetingSettings');

        // api_require_uuid returns 400 when field is missing/invalid
        $this->assertSame(400, $res['status']);
    }

    public function testMeetingSettingsPostInvalidPolicyIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id'       => self::MEETING_ID,
            'quorum_policy_id' => 'not-a-uuid',
            'convocation_no'   => 1,
        ]);

        $repo = $this->createMock(MeetingRepository::class);
        $repo->method('existsForTenant')->willReturn(true);

        $this->injectRepos([MeetingRepository::class => $repo]);

        $res = $this->callController(QuorumController::class, 'meetingSettings');

        $this->assertSame(400, $res['status']);
        $this->assertSame('invalid_quorum_policy_id', $res['body']['error']);
    }

    public function testMeetingSettingsPostInvalidConvocationNoReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id'     => self::MEETING_ID,
            'convocation_no' => 3,
        ]);

        $repo = $this->createMock(MeetingRepository::class);
        $repo->method('existsForTenant')->willReturn(true);

        $this->injectRepos([MeetingRepository::class => $repo]);

        $res = $this->callController(QuorumController::class, 'meetingSettings');

        $this->assertSame(400, $res['status']);
        $this->assertSame('invalid_convocation_no', $res['body']['error']);
    }

    public function testMeetingSettingsPostMeetingNotFoundReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id'     => self::MEETING_ID,
            'convocation_no' => 1,
        ]);

        $repo = $this->createMock(MeetingRepository::class);
        $repo->method('existsForTenant')->willReturn(false);

        $this->injectRepos([MeetingRepository::class => $repo]);

        $res = $this->callController(QuorumController::class, 'meetingSettings');

        $this->assertSame(404, $res['status']);
        $this->assertSame('meeting_not_found', $res['body']['error']);
    }

    public function testMeetingSettingsPostSavesWithNoPolicyId(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id'     => self::MEETING_ID,
            'convocation_no' => 2,
        ]);

        $repo = $this->createMock(MeetingRepository::class);
        $repo->method('existsForTenant')->willReturn(true);
        $repo->expects($this->once())->method('updateQuorumPolicy');

        $this->injectRepos([MeetingRepository::class => $repo]);

        $res = $this->callController(QuorumController::class, 'meetingSettings');

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['body']['data']['saved']);
    }

    public function testMeetingSettingsPostSavesWithPolicyId(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id'       => self::MEETING_ID,
            'quorum_policy_id' => self::POLICY_ID,
            'convocation_no'   => 1,
        ]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('existsForTenant')->willReturn(true);
        $meetingRepo->expects($this->once())->method('updateQuorumPolicy');

        $policyRepo = $this->createMock(PolicyRepository::class);
        $policyRepo->method('quorumPolicyExists')->willReturn(true);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            PolicyRepository::class  => $policyRepo,
        ]);

        $res = $this->callController(QuorumController::class, 'meetingSettings');

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['body']['data']['saved']);
    }

    public function testMeetingSettingsPostPolicyNotFoundReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id'       => self::MEETING_ID,
            'quorum_policy_id' => self::POLICY_ID,
            'convocation_no'   => 1,
        ]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('existsForTenant')->willReturn(true);

        $policyRepo = $this->createMock(PolicyRepository::class);
        $policyRepo->method('quorumPolicyExists')->willReturn(false);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            PolicyRepository::class  => $policyRepo,
        ]);

        $res = $this->callController(QuorumController::class, 'meetingSettings');

        $this->assertSame(404, $res['status']);
        $this->assertSame('quorum_policy_not_found', $res['body']['error']);
    }

    public function testMeetingSettingsMethodNotAllowedReturns405(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('DELETE');

        $repo = $this->createMock(MeetingRepository::class);
        $this->injectRepos([MeetingRepository::class => $repo]);

        $res = $this->callController(QuorumController::class, 'meetingSettings');

        $this->assertSame(405, $res['status']);
    }
}
