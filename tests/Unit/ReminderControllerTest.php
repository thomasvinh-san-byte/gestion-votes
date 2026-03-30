<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AbstractController;
use AgVote\Controller\ReminderController;
use AgVote\Repository\EmailTemplateRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\ReminderScheduleRepository;

/**
 * Unit tests for ReminderController.
 *
 * Extends ControllerTestCase for repo injection and standard helpers.
 *
 * Tests:
 *  - Controller structure (final, extends AbstractController)
 *  - listForMeeting: GET method enforcement, meeting_id validation, success path
 *  - upsert: POST method enforcement, validation, setup_defaults action, success path
 *  - delete: meeting_id query validation, reminder not found, success path
 */
class ReminderControllerTest extends ControllerTestCase
{
    private const TENANT    = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const MEETING   = 'bbbbbbbb-0000-0000-0000-000000000001';
    private const REMINDER  = 'cccccccc-0000-0000-0000-000000000001';
    private const TEMPLATE  = 'dddddddd-0000-0000-0000-000000000001';

    // =========================================================================
    // CONTROLLER STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(ReminderController::class);
        $this->assertTrue($ref->isFinal(), 'ReminderController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $this->assertInstanceOf(AbstractController::class, new ReminderController());
    }

    public function testControllerHasExpectedMethods(): void
    {
        $ref = new \ReflectionClass(ReminderController::class);

        foreach (['listForMeeting', 'upsert', 'delete'] as $method) {
            $this->assertTrue($ref->hasMethod($method), "Missing method: {$method}");
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(ReminderController::class);

        foreach (['listForMeeting', 'upsert', 'delete'] as $method) {
            $this->assertTrue($ref->getMethod($method)->isPublic(), "{$method} should be public");
        }
    }

    // =========================================================================
    // listForMeeting: VALIDATION (no method enforcement — uses api_query not api_request)
    // =========================================================================

    public function testListForMeetingRequiresMeetingIdRegardlessOfMethod(): void
    {
        // listForMeeting uses api_query() not api_request(), so any HTTP method works
        $this->setHttpMethod('POST');

        $result = $this->callController(ReminderController::class, 'listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // listForMeeting: VALIDATION
    // =========================================================================

    public function testListForMeetingRequiresMeetingId(): void
    {
        $result = $this->callController(ReminderController::class, 'listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testListForMeetingRejectsInvalidUuid(): void
    {
        $this->setQueryParams(['meeting_id' => 'bad-id']);

        $result = $this->callController(ReminderController::class, 'listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // listForMeeting: SUCCESS PATH
    // =========================================================================

    public function testListForMeetingReturnsReminders(): void
    {
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->setQueryParams(['meeting_id' => self::MEETING]);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        // api_guard_meeting_exists calls findByIdForTenant
        $mockMeeting->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING, 'title' => 'Test AG', 'status' => 'open',
        ]);

        $mockReminder = $this->createMock(ReminderScheduleRepository::class);
        $mockReminder->method('listForMeeting')->willReturn([
            ['id' => self::REMINDER, 'days_before' => 7, 'send_time' => '09:00'],
        ]);

        $mockTemplate = $this->createMock(EmailTemplateRepository::class);
        $mockTemplate->method('listForTenant')->willReturn([]);

        $this->injectRepos([
            MeetingRepository::class          => $mockMeeting,
            ReminderScheduleRepository::class => $mockReminder,
            EmailTemplateRepository::class    => $mockTemplate,
        ]);

        $result = $this->callController(ReminderController::class, 'listForMeeting');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
        $this->assertEquals(self::MEETING, $result['body']['data']['meeting_id']);
        $this->assertCount(1, $result['body']['data']['reminders']);
    }

    // =========================================================================
    // upsert: METHOD ENFORCEMENT
    // =========================================================================

    public function testUpsertRejectsGetMethod(): void
    {
        $result = $this->callController(ReminderController::class, 'upsert');

        $this->assertEquals(405, $result['status']);
    }

    public function testUpsertRejectsPutMethod(): void
    {
        $this->setHttpMethod('PUT');

        $result = $this->callController(ReminderController::class, 'upsert');

        $this->assertEquals(405, $result['status']);
    }

    // =========================================================================
    // upsert: setup_defaults ACTION
    // =========================================================================

    public function testUpsertSetupDefaultsRequiresMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody(['action' => 'setup_defaults', 'meeting_id' => '']);

        $mockReminder = $this->createMock(ReminderScheduleRepository::class);
        $this->injectRepos([ReminderScheduleRepository::class => $mockReminder]);

        $result = $this->callController(ReminderController::class, 'upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testUpsertSetupDefaultsSuccess(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([
            'action'     => 'setup_defaults',
            'meeting_id' => self::MEETING,
        ]);

        $mockReminder = $this->createMock(ReminderScheduleRepository::class);
        // setupDefaults returns void — no return value needed
        $mockReminder->method('listForMeeting')->willReturn([
            ['id' => self::REMINDER, 'days_before' => 7],
            ['id' => self::REMINDER, 'days_before' => 3],
            ['id' => self::REMINDER, 'days_before' => 1],
        ]);

        $this->injectRepos([
            ReminderScheduleRepository::class => $mockReminder,
        ]);

        $result = $this->callController(ReminderController::class, 'upsert');

        $this->assertEquals(200, $result['status']);
        $this->assertEquals(self::MEETING, $result['body']['data']['meeting_id']);
        $this->assertCount(3, $result['body']['data']['reminders']);
    }

    // =========================================================================
    // upsert: VALIDATION
    // =========================================================================

    public function testUpsertRequiresMeetingIdForRegularUpsert(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody(['days_before' => 7, 'meeting_id' => '']);

        $mockReminder = $this->createMock(ReminderScheduleRepository::class);
        $this->injectRepos([ReminderScheduleRepository::class => $mockReminder]);

        $result = $this->callController(ReminderController::class, 'upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testUpsertRequiresDaysBefore(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody(['meeting_id' => self::MEETING]);

        $mockReminder = $this->createMock(ReminderScheduleRepository::class);
        $this->injectRepos([ReminderScheduleRepository::class => $mockReminder]);

        $result = $this->callController(ReminderController::class, 'upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_days_before', $result['body']['error']);
    }

    public function testUpsertRejectsDaysBeforeAbove30(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody(['meeting_id' => self::MEETING, 'days_before' => 31]);

        $mockReminder = $this->createMock(ReminderScheduleRepository::class);
        $this->injectRepos([ReminderScheduleRepository::class => $mockReminder]);

        $result = $this->callController(ReminderController::class, 'upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_days_before', $result['body']['error']);
    }

    public function testUpsertRejectsDaysBeforeNegative(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody(['meeting_id' => self::MEETING, 'days_before' => -1]);

        $mockReminder = $this->createMock(ReminderScheduleRepository::class);
        $this->injectRepos([ReminderScheduleRepository::class => $mockReminder]);

        $result = $this->callController(ReminderController::class, 'upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_days_before', $result['body']['error']);
    }

    public function testUpsertRejectsInvalidSendTime(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([
            'meeting_id'  => self::MEETING,
            'days_before' => 7,
            'send_time'   => 'not-a-time',
        ]);

        $mockReminder = $this->createMock(ReminderScheduleRepository::class);
        $this->injectRepos([ReminderScheduleRepository::class => $mockReminder]);

        $result = $this->callController(ReminderController::class, 'upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_send_time', $result['body']['error']);
    }

    // =========================================================================
    // upsert: SUCCESS PATH
    // =========================================================================

    public function testUpsertCreatesReminderSuccessfully(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([
            'meeting_id'  => self::MEETING,
            'days_before' => 7,
            'send_time'   => '09:00',
            'is_active'   => true,
        ]);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('isValidated')->willReturn(false);

        $mockReminder = $this->createMock(ReminderScheduleRepository::class);
        $mockReminder->method('upsert')->willReturn([
            'id'          => self::REMINDER,
            'meeting_id'  => self::MEETING,
            'days_before' => 7,
            'send_time'   => '09:00',
            'is_active'   => true,
        ]);

        $this->injectRepos([
            MeetingRepository::class          => $mockMeeting,
            ReminderScheduleRepository::class => $mockReminder,
        ]);

        $result = $this->callController(ReminderController::class, 'upsert');

        $this->assertEquals(201, $result['status']);
        $this->assertTrue($result['body']['ok']);
        $this->assertArrayHasKey('reminder', $result['body']['data']);
    }

    // =========================================================================
    // delete: VALIDATION
    // =========================================================================

    public function testDeleteRequiresId(): void
    {
        $result = $this->callController(ReminderController::class, 'delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_reminder_id', $result['body']['error']);
    }

    public function testDeleteRejectsInvalidUuid(): void
    {
        $this->setQueryParams(['id' => 'not-a-uuid']);

        $result = $this->callController(ReminderController::class, 'delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_reminder_id', $result['body']['error']);
    }

    // =========================================================================
    // delete: NOT FOUND
    // =========================================================================

    public function testDeleteReturnsNotFoundWhenReminderMissing(): void
    {
        $this->setAuth('user-1', 'admin', self::TENANT);
        $_GET['id'] = self::REMINDER;

        $mockReminder = $this->createMock(ReminderScheduleRepository::class);
        $mockReminder->method('delete')->willReturn(false);
        $this->injectRepos([ReminderScheduleRepository::class => $mockReminder]);

        $result = $this->callController(ReminderController::class, 'delete');

        $this->assertEquals(404, $result['status']);
        $this->assertEquals('reminder_not_found', $result['body']['error']);
    }

    // =========================================================================
    // delete: SUCCESS PATH
    // =========================================================================

    public function testDeleteSuccessfully(): void
    {
        $this->setAuth('user-1', 'admin', self::TENANT);
        $_GET['id'] = self::REMINDER;

        $mockReminder = $this->createMock(ReminderScheduleRepository::class);
        $mockReminder->method('delete')->willReturn(true);
        $this->injectRepos([ReminderScheduleRepository::class => $mockReminder]);

        $result = $this->callController(ReminderController::class, 'delete');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['data']['deleted'] ?? false);
    }
}
