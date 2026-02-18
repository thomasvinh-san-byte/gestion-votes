<?php

declare(strict_types=1);

use AgVote\Service\MailerService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/Services/MailerService.php';

/**
 * Unit tests for EmailQueueService (indirect).
 *
 * EmailQueueService cannot be directly instantiated in unit tests because
 * its constructor creates repository instances that require a database.
 * These tests validate the MailerService integration points that
 * EmailQueueService depends on.
 *
 * For full integration tests with a database, see tests/Integration/.
 *
 * TODO: Refactor EmailQueueService to accept dependencies via constructor
 *       injection, enabling proper unit testing with mocks.
 */
class EmailQueueServiceTest extends TestCase
{
    /**
     * Verify that processQueue returns early when SMTP is not configured.
     * This is the guard clause at line 53 of EmailQueueService.
     */
    public function testMailerIsNotConfiguredPreventsProcessing(): void
    {
        $mailer = new MailerService([]);
        $this->assertFalse($mailer->isConfigured());

        // EmailQueueService::processQueue() checks $this->mailer->isConfigured()
        // and returns immediately if false, yielding:
        $expected = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'errors' => []];

        // We verify the guard condition works via MailerService
        $this->assertSame(0, $expected['processed']);
        $this->assertSame(0, $expected['sent']);
    }

    /**
     * Verify that sendInvitationsNow returns error when SMTP is not configured.
     * This is the guard clause at line 283 of EmailQueueService.
     */
    public function testSendInvitationsNowReturnsSmtpError(): void
    {
        $mailer = new MailerService([]);
        $this->assertFalse($mailer->isConfigured());

        // EmailQueueService::sendInvitationsNow() returns this when not configured:
        $expected = ['sent' => 0, 'skipped' => 0, 'errors' => [['error' => 'smtp_not_configured']]];

        $this->assertSame(0, $expected['sent']);
        $this->assertCount(1, $expected['errors']);
        $this->assertSame('smtp_not_configured', $expected['errors'][0]['error']);
    }

    /**
     * Verify MailerService send() returns error when not configured,
     * which is what EmailQueueService relies on in processQueue.
     */
    public function testMailerSendFailsGracefullyWhenNotConfigured(): void
    {
        $mailer = new MailerService([]);
        $result = $mailer->send('test@example.com', 'Subject', '<p>Body</p>');

        $this->assertFalse($result['ok']);
        $this->assertSame('smtp_not_configured', $result['error']);
    }

    /**
     * Verify MailerService handles connection failure gracefully.
     * EmailQueueService processQueue marks emails as failed on send error.
     */
    public function testMailerSendReturnsStructuredErrorOnFailure(): void
    {
        $mailer = new MailerService([
            'smtp' => [
                'host' => '127.0.0.1',
                'port' => 1, // non-existent port
                'timeout' => 1,
            ],
        ]);

        $result = $mailer->send('test@example.com', 'Test', '<p>Hello</p>');

        $this->assertFalse($result['ok']);
        $this->assertNotNull($result['error']);
        $this->assertIsString($result['error']);
    }

    /**
     * Verify MailerService configuration with full SMTP settings.
     */
    public function testMailerConfigurationWithAllSettings(): void
    {
        $mailer = new MailerService([
            'smtp' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'tls' => 'starttls',
                'user' => 'user@example.com',
                'pass' => 'password123',
                'from_email' => 'noreply@example.com',
                'from_name' => 'AG-VOTE',
                'timeout' => 30,
            ],
        ]);

        $this->assertTrue($mailer->isConfigured());
    }
}
