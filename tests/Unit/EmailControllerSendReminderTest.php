<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\EmailController;
use AgVote\Core\Http\ApiResponseException;
use AgVote\Repository\SettingsRepository;

/**
 * Execution tests for EmailController::sendReminder().
 *
 * Injects a stub EmailQueueService via the constructor's optional
 * $emailQueueFactory parameter so no real SMTP or DB is needed.
 *
 * The factory receives the merged config array and returns the stub.
 */
class EmailControllerSendReminderTest extends ControllerTestCase
{
    private const TENANT_ID  = 'ffffffff-0000-1111-2222-333333333333';
    private const MEETING_ID = 'aa000001-0000-4000-a000-000000000001';
    private const USER_ID    = 'aa000002-0000-4000-a000-000000000002';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setAuth(self::USER_ID, 'admin', self::TENANT_ID);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Instantiate EmailController with an injected stub for EmailQueueService.
     *
     * EmailQueueService is final and cannot be mocked with createMock(). We use
     * an anonymous class that implements the same scheduleReminders() signature.
     *
     * Also injects a mock SettingsRepository so MailerService::buildMailerConfig()
     * (which is called inside sendReminder()) does not hit the real database.
     *
     * @param array{scheduled:int,errors:array} $stubResult
     */
    private function makeControllerWithStub(array $stubResult): EmailController
    {
        // Mock SettingsRepository so MailerService::buildMailerConfig() works
        $settingsRepo = $this->createMock(SettingsRepository::class);
        $settingsRepo->method('get')->willReturn(null);
        $this->injectRepos([SettingsRepository::class => $settingsRepo]);

        // Anonymous class stub: same public method signature as EmailQueueService
        $stubService = new class($stubResult) {
            public function __construct(private readonly array $result) {}
            public function scheduleReminders(string $tenantId, string $meetingId, ?string $templateId = null): array
            {
                return $this->result;
            }
        };

        return new EmailController(fn(array $config) => $stubService);
    }

    /**
     * Invoke EmailController::sendReminder() and capture the ApiResponseException.
     */
    private function invokeReminder(EmailController $controller): array
    {
        try {
            $controller->handle('sendReminder');
            $this->fail('Expected ApiResponseException from EmailController::sendReminder() was not thrown');
        } catch (ApiResponseException $e) {
            return [
                'status' => $e->getResponse()->getStatusCode(),
                'body'   => $e->getResponse()->getBody(),
            ];
        }
        return ['status' => 500, 'body' => []];
    }

    // =========================================================================
    // VALIDATION — missing / invalid fields
    // =========================================================================

    public function testSendReminderRequiresPostMethod(): void
    {
        $this->setHttpMethod('GET');
        $result = $this->callController(EmailController::class, 'sendReminder');
        $this->assertSame(405, $result['status']);
        $this->assertSame('method_not_allowed', $result['body']['error']);
    }

    public function testSendReminderMissingMeetingIdReturns400(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);
        $result = $this->callController(EmailController::class, 'sendReminder');
        $this->assertSame(400, $result['status']);
        $this->assertSame('missing_meeting_id', $result['body']['error']);
    }

    public function testSendReminderInvalidUuidReturns400(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => 'not-a-uuid']);
        $result = $this->callController(EmailController::class, 'sendReminder');
        $this->assertSame(400, $result['status']);
        $this->assertSame('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // HAPPY PATH — asserts real payload value
    // =========================================================================

    /**
     * Happy path: valid meeting_id + stub returning scheduled=3 →
     * 200 {ok:true, scheduled:3, errors:[]}
     *
     * Proves the return value of scheduleReminders() flows through api_ok()
     * and reaches the response body intact.
     */
    public function testSendReminderHappyPath(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => self::MEETING_ID]);

        $controller = $this->makeControllerWithStub(['scheduled' => 3, 'errors' => []]);
        $result = $this->invokeReminder($controller);

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
        // api_ok() wraps payload under 'data' key: {ok:true, data:{scheduled:3, errors:[]}}
        $this->assertSame(3, $result['body']['data']['scheduled']);
        $this->assertSame([], $result['body']['data']['errors']);
    }

    /**
     * Happy path with zero scheduled reminders (no unconfirmed invitations).
     */
    public function testSendReminderZeroScheduled(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => self::MEETING_ID]);

        $controller = $this->makeControllerWithStub(['scheduled' => 0, 'errors' => []]);
        $result = $this->invokeReminder($controller);

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
        $this->assertSame(0, $result['body']['data']['scheduled']);
    }

    /**
     * Partial errors: some reminders scheduled, some failed.
     */
    public function testSendReminderWithErrors(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => self::MEETING_ID]);

        $errors = [['member_id' => 'uuid-x', 'error' => 'SMTP failed']];
        $controller = $this->makeControllerWithStub(['scheduled' => 5, 'errors' => $errors]);
        $result = $this->invokeReminder($controller);

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
        $this->assertSame(5, $result['body']['data']['scheduled']);
        $this->assertSame($errors, $result['body']['data']['errors']);
    }
}
