<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AgendaController;
use AgVote\Repository\AgendaRepository;
use AgVote\Repository\MeetingRepository;

/**
 * Unit tests for AgendaController.
 *
 * Endpoints:
 *  - listForMeeting():       GET  — list agenda items for a meeting
 *  - create():               POST — create an agenda item
 *  - lateRules():            GET/POST — read/update meeting late rules
 *  - listForMeetingPublic(): GET  — public list of agenda items
 *
 * Response structure:
 *   - api_ok($data)  => body['data'][...] (wrapped in 'data' key)
 *   - api_fail(...)  => body['error'] (direct)
 *
 * Notes:
 *  - api_require_uuid() returns 400 for missing/invalid fields
 *  - ValidationSchemas::agenda() returns 422 via failIfInvalid()
 *  - api_guard_meeting_not_validated() needs MeetingRepository in cache
 *
 * Pattern: extends ControllerTestCase, uses injectRepos() + callController().
 */
class AgendaControllerTest extends ControllerTestCase
{
    private const TENANT     = 'tenant-uuid-001';
    private const MEETING_ID = 'aaaaaaaa-1111-2222-3333-000000000090';
    private const AGENDA_ID  = 'bbbbbbbb-1111-2222-3333-000000000090';
    private const USER_ID    = 'user-uuid-0090';

    // =========================================================================
    // CONTROLLER STRUCTURE
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(AgendaController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerHasRequiredMethods(): void
    {
        foreach (['listForMeeting', 'create', 'lateRules', 'listForMeetingPublic'] as $method) {
            $this->assertTrue(method_exists(AgendaController::class, $method));
        }
    }

    // =========================================================================
    // listForMeeting() — GET
    // =========================================================================

    public function testListForMeetingRequiresGet(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $agendaRepo = $this->createMock(AgendaRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            AgendaRepository::class  => $agendaRepo,
        ]);

        $res = $this->callController(AgendaController::class, 'listForMeeting');

        $this->assertSame(405, $res['status']);
    }

    public function testListForMeetingMissingMeetingIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $agendaRepo = $this->createMock(AgendaRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            AgendaRepository::class  => $agendaRepo,
        ]);

        $res = $this->callController(AgendaController::class, 'listForMeeting');

        $this->assertSame(400, $res['status']);
    }

    public function testListForMeetingNotFoundReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('existsForTenant')->willReturn(false);

        $agendaRepo = $this->createMock(AgendaRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            AgendaRepository::class  => $agendaRepo,
        ]);

        $res = $this->callController(AgendaController::class, 'listForMeeting');

        $this->assertSame(404, $res['status']);
        $this->assertSame('meeting_not_found', $res['body']['error']);
    }

    public function testListForMeetingReturnsItems(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('existsForTenant')->willReturn(true);

        $agendaRepo = $this->createMock(AgendaRepository::class);
        $agendaRepo->method('listForMeeting')->willReturn([
            ['id' => self::AGENDA_ID, 'title' => 'Ouverture'],
        ]);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            AgendaRepository::class  => $agendaRepo,
        ]);

        $res = $this->callController(AgendaController::class, 'listForMeeting');

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['body']['data']['items']);
        $this->assertSame(self::AGENDA_ID, $res['body']['data']['items'][0]['id']);
    }

    // =========================================================================
    // create() — POST
    // =========================================================================

    public function testCreateRequiresPost(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $agendaRepo = $this->createMock(AgendaRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            AgendaRepository::class  => $agendaRepo,
        ]);

        $res = $this->callController(AgendaController::class, 'create');

        $this->assertSame(405, $res['status']);
    }

    public function testCreateValidationFailsWithMissingFields(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['title' => 'Ouverture']);  // missing meeting_id

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $agendaRepo = $this->createMock(AgendaRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            AgendaRepository::class  => $agendaRepo,
        ]);

        $res = $this->callController(AgendaController::class, 'create');

        $this->assertSame(422, $res['status']);
        $this->assertSame('validation_failed', $res['body']['error']);
    }

    public function testCreateMeetingNotFoundReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id' => self::MEETING_ID,
            'title'      => 'Ouverture',
        ]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(null);

        $agendaRepo = $this->createMock(AgendaRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            AgendaRepository::class  => $agendaRepo,
        ]);

        $res = $this->callController(AgendaController::class, 'create');

        $this->assertSame(404, $res['status']);
        $this->assertSame('meeting_not_found', $res['body']['error']);
    }

    public function testCreateMeetingValidatedReturns409(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id' => self::MEETING_ID,
            'title'      => 'Ouverture',
        ]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id'           => self::MEETING_ID,
            'status'       => 'live',
            'validated_at' => '2025-01-01 10:00:00',
        ]);

        $agendaRepo = $this->createMock(AgendaRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            AgendaRepository::class  => $agendaRepo,
        ]);

        $res = $this->callController(AgendaController::class, 'create');

        $this->assertSame(409, $res['status']);
        $this->assertSame('meeting_validated', $res['body']['error']);
    }

    public function testCreateSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id' => self::MEETING_ID,
            'title'      => 'Ouverture de la seance',
        ]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id'           => self::MEETING_ID,
            'status'       => 'live',
            'validated_at' => null,
        ]);

        $agendaRepo = $this->createMock(AgendaRepository::class);
        $agendaRepo->method('generateUuid')->willReturn(self::AGENDA_ID);
        $agendaRepo->method('nextIdx')->willReturn(1);
        $agendaRepo->expects($this->once())->method('create');

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            AgendaRepository::class  => $agendaRepo,
        ]);

        $res = $this->callController(AgendaController::class, 'create');

        $this->assertSame(201, $res['status']);
        $data = $res['body']['data'];
        $this->assertSame(self::AGENDA_ID, $data['agenda_id']);
        $this->assertSame('Ouverture de la seance', $data['title']);
    }

    // =========================================================================
    // lateRules() — GET
    // =========================================================================

    public function testLateRulesGetMissingMeetingIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        $res = $this->callController(AgendaController::class, 'lateRules');

        $this->assertSame(400, $res['status']);
    }

    public function testLateRulesGetNotFoundReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findLateRules')->willReturn(null);

        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        $res = $this->callController(AgendaController::class, 'lateRules');

        $this->assertSame(404, $res['status']);
        $this->assertSame('meeting_not_found', $res['body']['error']);
    }

    public function testLateRulesGetReturnsRules(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findLateRules')->willReturn([
            'id'               => self::MEETING_ID,
            'late_rule_quorum' => true,
            'late_rule_vote'   => false,
        ]);

        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        $res = $this->callController(AgendaController::class, 'lateRules');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        $this->assertSame(self::MEETING_ID, $data['meeting_id']);
        $this->assertTrue($data['late_rule_quorum']);
        $this->assertFalse($data['late_rule_vote']);
    }

    // =========================================================================
    // lateRules() — POST
    // =========================================================================

    public function testLateRulesPostMissingMeetingIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['late_rule_quorum' => 1]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        $res = $this->callController(AgendaController::class, 'lateRules');

        $this->assertSame(400, $res['status']);
    }

    public function testLateRulesPostSavesRules(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id'      => self::MEETING_ID,
            'late_rule_quorum' => 1,
            'late_rule_vote'   => 0,
        ]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('isValidated')->willReturn(false);
        $meetingRepo->expects($this->once())->method('updateLateRules');

        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        $res = $this->callController(AgendaController::class, 'lateRules');

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['body']['data']['saved']);
    }

    public function testLateRulesMethodNotAllowedReturns405(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('DELETE');

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        $res = $this->callController(AgendaController::class, 'lateRules');

        $this->assertSame(405, $res['status']);
    }

    // =========================================================================
    // listForMeetingPublic() — GET
    // =========================================================================

    public function testListForMeetingPublicMissingMeetingIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $agendaRepo = $this->createMock(AgendaRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            AgendaRepository::class  => $agendaRepo,
        ]);

        $res = $this->callController(AgendaController::class, 'listForMeetingPublic');

        $this->assertSame(400, $res['status']);
    }

    public function testListForMeetingPublicNotFoundReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('existsForTenant')->willReturn(false);

        $agendaRepo = $this->createMock(AgendaRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            AgendaRepository::class  => $agendaRepo,
        ]);

        $res = $this->callController(AgendaController::class, 'listForMeetingPublic');

        $this->assertSame(404, $res['status']);
        $this->assertSame('meeting_not_found', $res['body']['error']);
    }

    public function testListForMeetingPublicReturnsCompactItems(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('existsForTenant')->willReturn(true);

        $agendaRepo = $this->createMock(AgendaRepository::class);
        $agendaRepo->method('listForMeetingCompact')->willReturn([
            ['idx' => 1, 'title' => 'Ouverture'],
        ]);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            AgendaRepository::class  => $agendaRepo,
        ]);

        $res = $this->callController(AgendaController::class, 'listForMeetingPublic');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        $this->assertSame(self::MEETING_ID, $data['meeting_id']);
        $this->assertCount(1, $data['items']);
    }
}
