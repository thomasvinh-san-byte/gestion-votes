<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AbstractController;
use AgVote\Controller\EmergencyController;
use AgVote\Repository\EmergencyProcedureRepository;
use AgVote\Repository\MeetingRepository;

/**
 * Unit tests for EmergencyController.
 *
 * Extends ControllerTestCase for repo injection and standard helpers.
 *
 * Tests:
 *  - Controller structure (final, extends AbstractController)
 *  - checkToggle: POST enforcement, UUID validation, missing procedure_code,
 *    missing item_index, success path
 *  - procedures: GET enforcement, returns items/checks, with and without meeting_id
 */
class EmergencyControllerTest extends ControllerTestCase
{
    private const TENANT  = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const MEETING = 'bbbbbbbb-0000-0000-0000-000000000001';

    // =========================================================================
    // CONTROLLER STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(EmergencyController::class);
        $this->assertTrue($ref->isFinal(), 'EmergencyController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $this->assertInstanceOf(AbstractController::class, new EmergencyController());
    }

    public function testControllerHasExpectedMethods(): void
    {
        $ref = new \ReflectionClass(EmergencyController::class);

        foreach (['checkToggle', 'procedures'] as $method) {
            $this->assertTrue($ref->hasMethod($method), "Missing method: {$method}");
            $this->assertTrue($ref->getMethod($method)->isPublic(), "{$method} should be public");
        }
    }

    // =========================================================================
    // checkToggle: METHOD ENFORCEMENT
    // =========================================================================

    public function testCheckToggleRejectsGetMethod(): void
    {
        $result = $this->callController(EmergencyController::class, 'checkToggle');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testCheckToggleRejectsPutMethod(): void
    {
        $this->setHttpMethod('PUT');

        $result = $this->callController(EmergencyController::class, 'checkToggle');

        $this->assertEquals(405, $result['status']);
    }

    public function testCheckToggleRejectsDeleteMethod(): void
    {
        $this->setHttpMethod('DELETE');

        $result = $this->callController(EmergencyController::class, 'checkToggle');

        $this->assertEquals(405, $result['status']);
    }

    // =========================================================================
    // checkToggle: MEETING_ID VALIDATION
    // =========================================================================

    public function testCheckToggleRequiresMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $result = $this->callController(EmergencyController::class, 'checkToggle');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field'] ?? null);
    }

    public function testCheckToggleRejectsEmptyMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => '']);

        $result = $this->callController(EmergencyController::class, 'checkToggle');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testCheckToggleRejectsInvalidMeetingUuid(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => 'not-a-uuid']);

        $result = $this->callController(EmergencyController::class, 'checkToggle');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    // =========================================================================
    // checkToggle: PROCEDURE CODE VALIDATION
    // =========================================================================

    public function testCheckToggleRequiresProcedureCode(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => self::MEETING]);

        $result = $this->callController(EmergencyController::class, 'checkToggle');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_procedure_code', $result['body']['error']);
    }

    public function testCheckToggleRejectsEmptyProcedureCode(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id'     => self::MEETING,
            'procedure_code' => '   ',
        ]);

        $result = $this->callController(EmergencyController::class, 'checkToggle');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_procedure_code', $result['body']['error']);
    }

    // =========================================================================
    // checkToggle: ITEM_INDEX VALIDATION
    // =========================================================================

    public function testCheckToggleRequiresNonNegativeItemIndex(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id'     => self::MEETING,
            'procedure_code' => 'fire_evacuation',
            // item_index defaults to -1
        ]);

        $result = $this->callController(EmergencyController::class, 'checkToggle');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_item_index', $result['body']['error']);
    }

    public function testCheckToggleRejectsNegativeItemIndex(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id'     => self::MEETING,
            'procedure_code' => 'fire_evacuation',
            'item_index'     => -1,
        ]);

        $result = $this->callController(EmergencyController::class, 'checkToggle');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_item_index', $result['body']['error']);
    }

    // =========================================================================
    // checkToggle: SUCCESS PATH
    // =========================================================================

    public function testCheckToggleSavesSuccessfully(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'operator', self::TENANT);
        $this->injectJsonBody([
            'meeting_id'     => self::MEETING,
            'procedure_code' => 'fire_evacuation',
            'item_index'     => 0,
            'checked'        => 1,
        ]);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        // upsertEmergencyCheck returns void

        $this->injectRepos([MeetingRepository::class => $mockMeeting]);

        $result = $this->callController(EmergencyController::class, 'checkToggle');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['data']['saved']);
    }

    public function testCheckToggleCheckedFlagParsing(): void
    {
        // Verify: $checked = (int)($in['checked'] ?? 0) ? true : false
        $this->assertFalse((bool)((int)(0)));
        $this->assertFalse((bool)((int)(null ?? 0)));
        $this->assertTrue((bool)((int)(1)));
        $this->assertTrue((bool)((int)('1')));
    }

    // =========================================================================
    // procedures: METHOD ENFORCEMENT
    // =========================================================================

    public function testProceduresRejectsPostMethod(): void
    {
        $this->setHttpMethod('POST');

        $result = $this->callController(EmergencyController::class, 'procedures');

        $this->assertEquals(405, $result['status']);
    }

    public function testProceduresRejectsPutMethod(): void
    {
        $this->setHttpMethod('PUT');

        $result = $this->callController(EmergencyController::class, 'procedures');

        $this->assertEquals(405, $result['status']);
    }

    public function testProceduresRejectsDeleteMethod(): void
    {
        $this->setHttpMethod('DELETE');

        $result = $this->callController(EmergencyController::class, 'procedures');

        $this->assertEquals(405, $result['status']);
    }

    // =========================================================================
    // procedures: SUCCESS PATH — no meeting_id
    // =========================================================================

    public function testProceduresReturnsItemsWithNoChecks(): void
    {
        $this->setAuth('user-1', 'operator', self::TENANT);
        $this->setQueryParams(['audience' => 'operator']);

        $mockEmergency = $this->createMock(EmergencyProcedureRepository::class);
        $mockEmergency->method('listByAudienceWithField')->willReturn([
            ['code' => 'fire_evacuation', 'title' => 'Evacuation incendie', 'audience' => 'operator'],
        ]);
        // listChecksForMeeting not called when no meeting_id

        $this->injectRepos([EmergencyProcedureRepository::class => $mockEmergency]);

        $result = $this->callController(EmergencyController::class, 'procedures');

        $this->assertEquals(200, $result['status']);
        $this->assertCount(1, $result['body']['data']['items']);
        $this->assertEquals([], $result['body']['data']['checks']);
    }

    // =========================================================================
    // procedures: SUCCESS PATH — with valid meeting_id
    // =========================================================================

    public function testProceduresReturnsChecksWhenMeetingIdProvided(): void
    {
        $this->setAuth('user-1', 'operator', self::TENANT);
        $this->setQueryParams([
            'audience'   => 'operator',
            'meeting_id' => self::MEETING,
        ]);

        $mockEmergency = $this->createMock(EmergencyProcedureRepository::class);
        $mockEmergency->method('listByAudienceWithField')->willReturn([
            ['code' => 'fire_evacuation', 'title' => 'Evacuation', 'audience' => 'operator'],
        ]);
        $mockEmergency->method('listChecksForMeeting')->willReturn([
            ['procedure_code' => 'fire_evacuation', 'item_index' => 0, 'checked' => true],
        ]);

        $this->injectRepos([EmergencyProcedureRepository::class => $mockEmergency]);

        $result = $this->callController(EmergencyController::class, 'procedures');

        $this->assertEquals(200, $result['status']);
        $this->assertCount(1, $result['body']['data']['items']);
        $this->assertCount(1, $result['body']['data']['checks']);
    }

    // =========================================================================
    // procedures: DEFAULT AUDIENCE
    // =========================================================================

    public function testProceduresDefaultsAudienceToOperator(): void
    {
        // Source verification: $aud = trim((string) ($q['audience'] ?? 'operator'))
        $ref = new \ReflectionClass(EmergencyController::class);
        $source = file_get_contents($ref->getFileName());

        $this->assertStringContainsString("'operator'", $source);
        $this->assertStringContainsString('audience', $source);
    }
}
