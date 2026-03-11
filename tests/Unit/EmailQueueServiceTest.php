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
class EmailQueueServiceTest extends TestCase {
    /**
     * Verify MailerService send() returns error when not configured,
     * which is what EmailQueueService relies on in processQueue.
     */
    public function testMailerSendFailsGracefullyWhenNotConfigured(): void {
        $mailer = new MailerService([]);
        $result = $mailer->send('test@example.com', 'Subject', '<p>Body</p>');

        $this->assertFalse($result['ok']);
        $this->assertSame('smtp_not_configured', $result['error']);
    }

    /**
     * Verify MailerService handles connection failure gracefully.
     * EmailQueueService processQueue marks emails as failed on send error.
     */
    public function testMailerSendReturnsStructuredErrorOnFailure(): void {
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
    public function testMailerConfigurationWithAllSettings(): void {
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
